<?php
/**
 * Bookings Endpoints
 * CoachSearching.com API
 */

$database = new Database();
$conn = $database->getConnection();

$route = $_GET['route'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// GET /bookings - Get user's bookings
if ($route === 'bookings' && $method === 'GET') {
    $user = requireAuth(['user', 'business', 'coach']);

    $status = $_GET['status'] ?? '';
    $where = ["(b.client_id = ? OR b.coach_id = ?)"];
    $params = [$user['user_id'], $user['user_id']];

    if (!empty($status)) {
        $where[] = "b.status = ?";
        $params[] = $status;
    }

    $whereClause = implode(' AND ', $where);

    $stmt = $conn->prepare("
        SELECT
            b.*,
            cs.title as session_title,
            cs.duration_minutes,
            coach.first_name as coach_first_name,
            coach.last_name as coach_last_name,
            coach.profile_image as coach_profile_image,
            client.first_name as client_first_name,
            client.last_name as client_last_name,
            client.profile_image as client_profile_image
        FROM bookings b
        INNER JOIN coaching_sessions cs ON b.session_id = cs.id
        INNER JOIN users coach ON b.coach_id = coach.id
        INNER JOIN users client ON b.client_id = client.id
        WHERE $whereClause
        ORDER BY b.requested_datetime DESC
    ");
    $stmt->execute($params);
    $bookings = $stmt->fetchAll();

    sendResponse(['bookings' => $bookings]);
}

// POST /bookings/create - Create booking
if ($route === 'bookings/create' && $method === 'POST') {
    $user = requireAuth(['user', 'business']);
    $data = getInputData();

    $errors = validateRequired($data, ['session_id', 'requested_datetime']);
    if (!empty($errors)) {
        sendError('Validation failed', 400, $errors);
    }

    // Get session details
    $stmt = $conn->prepare("
        SELECT cs.*, cp.auto_accept_bookings
        FROM coaching_sessions cs
        INNER JOIN coach_profiles cp ON cs.coach_id = cp.user_id
        WHERE cs.id = ? AND cs.is_active = 1
    ");
    $stmt->execute([$data['session_id']]);
    $session = $stmt->fetch();

    if (!$session) {
        sendError('Session not found', 404);
    }

    // Validate datetime format
    $requestedDatetime = DateTime::createFromFormat('Y-m-d H:i:s', $data['requested_datetime']);
    if (!$requestedDatetime) {
        sendError('Invalid datetime format. Use: Y-m-d H:i:s', 400);
    }

    // Check if datetime is in the future
    if ($requestedDatetime < new DateTime()) {
        sendError('Requested datetime must be in the future', 400);
    }

    try {
        $conn->beginTransaction();

        // Create booking
        $clientType = $user['role'] === 'business' ? 'business' : 'user';
        $status = $session['auto_accept_bookings'] ? 'confirmed' : 'pending';
        $confirmedDatetime = $session['auto_accept_bookings'] ? $data['requested_datetime'] : null;

        $stmt = $conn->prepare("
            INSERT INTO bookings (
                session_id, coach_id, client_id, client_type,
                requested_datetime, confirmed_datetime, status,
                total_price, currency, notes
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $data['session_id'],
            $session['coach_id'],
            $user['user_id'],
            $clientType,
            $data['requested_datetime'],
            $confirmedDatetime,
            $status,
            $session['price'],
            $session['currency'],
            $data['notes'] ?? null
        ]);

        $bookingId = $conn->lastInsertId();

        // Send notification to coach
        sendNotification(
            $conn,
            $session['coach_id'],
            'booking',
            'New Booking Request',
            'You have a new booking request',
            "/coach/bookings/{$bookingId}"
        );

        // If auto-accepted, send notification to client
        if ($status === 'confirmed') {
            sendNotification(
                $conn,
                $user['user_id'],
                'booking',
                'Booking Confirmed',
                'Your booking has been automatically confirmed',
                "/bookings/{$bookingId}"
            );
        }

        $conn->commit();

        sendResponse([
            'message' => $status === 'confirmed' ? 'Booking confirmed' : 'Booking request sent',
            'booking_id' => $bookingId,
            'status' => $status
        ], 201);

    } catch (PDOException $e) {
        $conn->rollBack();
        error_log("Booking creation error: " . $e->getMessage());
        sendError('Failed to create booking', 500);
    }
}

// POST /bookings/confirm - Confirm booking (coach only)
if ($route === 'bookings/confirm' && $method === 'POST') {
    $user = requireAuth(['coach']);
    $data = getInputData();

    $errors = validateRequired($data, ['booking_id']);
    if (!empty($errors)) {
        sendError('Validation failed', 400, $errors);
    }

    // Get booking
    $stmt = $conn->prepare("SELECT * FROM bookings WHERE id = ? AND coach_id = ?");
    $stmt->execute([$data['booking_id'], $user['user_id']]);
    $booking = $stmt->fetch();

    if (!$booking) {
        sendError('Booking not found', 404);
    }

    if ($booking['status'] !== 'pending') {
        sendError('Booking cannot be confirmed', 400);
    }

    try {
        $confirmedDatetime = $data['confirmed_datetime'] ?? $booking['requested_datetime'];

        $stmt = $conn->prepare("
            UPDATE bookings
            SET status = 'confirmed', confirmed_datetime = ?
            WHERE id = ?
        ");
        $stmt->execute([$confirmedDatetime, $data['booking_id']]);

        // Send notification to client
        sendNotification(
            $conn,
            $booking['client_id'],
            'booking',
            'Booking Confirmed',
            'Your booking has been confirmed',
            "/bookings/{$booking['id']}"
        );

        sendResponse(['message' => 'Booking confirmed']);
    } catch (PDOException $e) {
        error_log("Booking confirmation error: " . $e->getMessage());
        sendError('Failed to confirm booking', 500);
    }
}

// POST /bookings/cancel - Cancel booking
if ($route === 'bookings/cancel' && $method === 'POST') {
    $user = requireAuth(['user', 'business', 'coach']);
    $data = getInputData();

    $errors = validateRequired($data, ['booking_id']);
    if (!empty($errors)) {
        sendError('Validation failed', 400, $errors);
    }

    // Get booking
    $stmt = $conn->prepare("
        SELECT * FROM bookings
        WHERE id = ? AND (client_id = ? OR coach_id = ?)
    ");
    $stmt->execute([$data['booking_id'], $user['user_id'], $user['user_id']]);
    $booking = $stmt->fetch();

    if (!$booking) {
        sendError('Booking not found', 404);
    }

    if (!in_array($booking['status'], ['pending', 'confirmed'])) {
        sendError('Booking cannot be cancelled', 400);
    }

    try {
        $stmt = $conn->prepare("
            UPDATE bookings
            SET status = 'cancelled', cancellation_reason = ?
            WHERE id = ?
        ");
        $stmt->execute([$data['reason'] ?? null, $data['booking_id']]);

        // Send notification to the other party
        $notifyUserId = ($booking['coach_id'] == $user['user_id']) ? $booking['client_id'] : $booking['coach_id'];
        sendNotification(
            $conn,
            $notifyUserId,
            'booking',
            'Booking Cancelled',
            'A booking has been cancelled',
            "/bookings/{$booking['id']}"
        );

        sendResponse(['message' => 'Booking cancelled']);
    } catch (PDOException $e) {
        error_log("Booking cancellation error: " . $e->getMessage());
        sendError('Failed to cancel booking', 500);
    }
}

// If no route matched
sendError('Invalid endpoint or method', 404);
