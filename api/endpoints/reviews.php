<?php
/**
 * Reviews Endpoints
 * CoachSearching.com API
 */

$database = new Database();
$conn = $database->getConnection();

$route = $_GET['route'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// GET /reviews?coach_id=123
if ($route === 'reviews' && $method === 'GET') {
    $coachId = $_GET['coach_id'] ?? null;

    if (!$coachId) {
        sendError('Coach ID required', 400);
    }

    $page = $_GET['page'] ?? 1;
    $perPage = $_GET['per_page'] ?? 20;
    $pagination = paginate('', $page, $perPage);

    // Get total count
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total
        FROM reviews
        WHERE coach_id = ? AND is_visible = 1
    ");
    $stmt->execute([$coachId]);
    $totalCount = $stmt->fetch()['total'];

    // Get reviews
    $stmt = $conn->prepare("
        SELECT
            r.*,
            u.first_name,
            u.last_name,
            u.profile_image
        FROM reviews r
        INNER JOIN users u ON r.reviewer_id = u.id
        WHERE r.coach_id = ? AND r.is_visible = 1
        ORDER BY r.created_at DESC
        LIMIT {$pagination['limit']} OFFSET {$pagination['offset']}
    ");
    $stmt->execute([$coachId]);
    $reviews = $stmt->fetchAll();

    // Get rating breakdown
    $stmt = $conn->prepare("
        SELECT
            rating,
            COUNT(*) as count
        FROM reviews
        WHERE coach_id = ? AND is_visible = 1
        GROUP BY rating
        ORDER BY rating DESC
    ");
    $stmt->execute([$coachId]);
    $ratingBreakdown = $stmt->fetchAll();

    sendResponse([
        'reviews' => $reviews,
        'rating_breakdown' => $ratingBreakdown,
        'pagination' => [
            'page' => $pagination['page'],
            'per_page' => $pagination['limit'],
            'total' => intval($totalCount),
            'total_pages' => ceil($totalCount / $pagination['limit'])
        ]
    ]);
}

// POST /reviews/create
if ($route === 'reviews/create' && $method === 'POST') {
    $user = requireAuth(['user', 'business']);
    $data = getInputData();

    $errors = validateRequired($data, ['booking_id', 'rating', 'comment']);
    if (!empty($errors)) {
        sendError('Validation failed', 400, $errors);
    }

    // Validate rating
    $rating = intval($data['rating']);
    if ($rating < 1 || $rating > 5) {
        sendError('Rating must be between 1 and 5', 400);
    }

    // Get booking
    $stmt = $conn->prepare("
        SELECT * FROM bookings
        WHERE id = ? AND client_id = ? AND status = 'completed'
    ");
    $stmt->execute([$data['booking_id'], $user['user_id']]);
    $booking = $stmt->fetch();

    if (!$booking) {
        sendError('Booking not found or not completed', 404);
    }

    // Check if review already exists
    $stmt = $conn->prepare("SELECT id FROM reviews WHERE booking_id = ?");
    $stmt->execute([$data['booking_id']]);
    if ($stmt->fetch()) {
        sendError('Review already exists for this booking', 400);
    }

    try {
        $conn->beginTransaction();

        // Create review
        $stmt = $conn->prepare("
            INSERT INTO reviews (coach_id, reviewer_id, booking_id, rating, title, comment)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $booking['coach_id'],
            $user['user_id'],
            $data['booking_id'],
            $rating,
            $data['title'] ?? null,
            $data['comment']
        ]);

        $reviewId = $conn->lastInsertId();

        // Update coach rating
        updateCoachRating($conn, $booking['coach_id']);

        // Send notification to coach
        sendNotification(
            $conn,
            $booking['coach_id'],
            'review',
            'New Review',
            "You received a {$rating}-star review",
            "/coach/reviews/{$reviewId}"
        );

        $conn->commit();

        sendResponse([
            'message' => 'Review created successfully',
            'review_id' => $reviewId
        ], 201);

    } catch (PDOException $e) {
        $conn->rollBack();
        error_log("Review creation error: " . $e->getMessage());
        sendError('Failed to create review', 500);
    }
}

// If no route matched
sendError('Invalid endpoint or method', 404);
