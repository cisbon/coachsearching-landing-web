<?php
/**
 * User Endpoints
 * CoachSearching.com API
 */

$database = new Database();
$conn = $database->getConnection();

$route = $_GET['route'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// GET /user/profile - Get user profile
if ($route === 'user/profile' && $method === 'GET') {
    $user = requireAuth(['user', 'business']);

    $stmt = $conn->prepare("
        SELECT id, email, role, first_name, last_name, phone, profile_image, created_at
        FROM users
        WHERE id = ?
    ");
    $stmt->execute([$user['user_id']]);
    $profile = $stmt->fetch();

    sendResponse(['profile' => $profile]);
}

// PUT /user/profile - Update user profile
if ($route === 'user/profile' && $method === 'PUT') {
    $user = requireAuth(['user', 'business']);
    $data = getInputData();

    $updates = [];
    $params = [];

    $allowedFields = ['first_name', 'last_name', 'phone', 'profile_image'];
    foreach ($allowedFields as $field) {
        if (isset($data[$field])) {
            $updates[] = "$field = ?";
            $params[] = $data[$field];
        }
    }

    if (empty($updates)) {
        sendError('No fields to update', 400);
    }

    $params[] = $user['user_id'];

    $stmt = $conn->prepare("
        UPDATE users
        SET " . implode(', ', $updates) . "
        WHERE id = ?
    ");
    $stmt->execute($params);

    sendResponse(['message' => 'Profile updated']);
}

// POST /user/follow - Follow a coach
if ($route === 'user/follow' && $method === 'POST') {
    $user = requireAuth(['user', 'business']);
    $data = getInputData();

    $errors = validateRequired($data, ['coach_id']);
    if (!empty($errors)) {
        sendError('Validation failed', 400, $errors);
    }

    // Check if coach exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE id = ? AND role = 'coach'");
    $stmt->execute([$data['coach_id']]);
    if (!$stmt->fetch()) {
        sendError('Coach not found', 404);
    }

    try {
        $stmt = $conn->prepare("
            INSERT INTO follows (user_id, coach_id)
            VALUES (?, ?)
        ");
        $stmt->execute([$user['user_id'], $data['coach_id']]);

        sendResponse(['message' => 'Coach followed'], 201);
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) { // Duplicate entry
            sendError('Already following this coach', 400);
        }
        error_log("Follow error: " . $e->getMessage());
        sendError('Failed to follow coach', 500);
    }
}

// DELETE /user/unfollow - Unfollow a coach
if ($route === 'user/unfollow' && $method === 'DELETE') {
    $user = requireAuth(['user', 'business']);
    $data = getInputData();

    $errors = validateRequired($data, ['coach_id']);
    if (!empty($errors)) {
        sendError('Validation failed', 400, $errors);
    }

    $stmt = $conn->prepare("
        DELETE FROM follows
        WHERE user_id = ? AND coach_id = ?
    ");
    $stmt->execute([$user['user_id'], $data['coach_id']]);

    sendResponse(['message' => 'Coach unfollowed']);
}

// GET /user/feed - Get personalized feed
if ($route === 'user/feed' && $method === 'GET') {
    $user = requireAuth(['user', 'business']);

    $page = $_GET['page'] ?? 1;
    $perPage = $_GET['per_page'] ?? 20;
    $pagination = paginate('', $page, $perPage);

    // Get followed coaches
    $stmt = $conn->prepare("SELECT coach_id FROM follows WHERE user_id = ?");
    $stmt->execute([$user['user_id']]);
    $followedCoaches = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($followedCoaches)) {
        // Get activity from followed coaches
        $placeholders = implode(',', array_fill(0, count($followedCoaches), '?'));
        $stmt = $conn->prepare("
            SELECT * FROM activity_feed
            WHERE user_id IN ($placeholders) AND is_visible = 1
            ORDER BY created_at DESC
            LIMIT {$pagination['limit']} OFFSET {$pagination['offset']}
        ");
        $stmt->execute($followedCoaches);
        $activities = $stmt->fetchAll();
    } else {
        // Get generic feed based on user's interests
        $stmt = $conn->prepare("
            SELECT * FROM activity_feed
            WHERE is_visible = 1
            ORDER BY created_at DESC
            LIMIT {$pagination['limit']} OFFSET {$pagination['offset']}
        ");
        $stmt->execute();
        $activities = $stmt->fetchAll();
    }

    foreach ($activities as &$activity) {
        $activity['activity_data'] = json_decode($activity['activity_data'] ?? '{}', true);
    }

    sendResponse(['feed' => $activities]);
}

// GET /user/bookings - Get user's bookings
if ($route === 'user/bookings' && $method === 'GET') {
    $user = requireAuth(['user', 'business']);

    $stmt = $conn->prepare("
        SELECT
            b.*,
            cs.title as session_title,
            cs.duration_minutes,
            u.first_name as coach_first_name,
            u.last_name as coach_last_name,
            u.profile_image as coach_profile_image
        FROM bookings b
        INNER JOIN coaching_sessions cs ON b.session_id = cs.id
        INNER JOIN users u ON b.coach_id = u.id
        WHERE b.client_id = ?
        ORDER BY b.requested_datetime DESC
    ");
    $stmt->execute([$user['user_id']]);
    $bookings = $stmt->fetchAll();

    sendResponse(['bookings' => $bookings]);
}

// If no route matched
sendError('Invalid endpoint or method', 404);
