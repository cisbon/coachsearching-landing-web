<?php
/**
 * Coach Dashboard Endpoints
 * CoachSearching.com API
 */

$database = new Database();
$conn = $database->getConnection();

$route = $_GET['route'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// GET /coach/dashboard - Get dashboard stats
if ($route === 'coach/dashboard' && $method === 'GET') {
    $user = requireAuth(['coach']);

    // Get stats
    $stmt = $conn->prepare("SELECT * FROM coach_profiles WHERE user_id = ?");
    $stmt->execute([$user['user_id']]);
    $profile = $stmt->fetch();

    // Get pending bookings count
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM bookings WHERE coach_id = ? AND status = 'pending'");
    $stmt->execute([$user['user_id']]);
    $pendingBookings = $stmt->fetch()['count'];

    // Get upcoming bookings
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count
        FROM bookings
        WHERE coach_id = ? AND status = 'confirmed' AND confirmed_datetime > NOW()
    ");
    $stmt->execute([$user['user_id']]);
    $upcomingBookings = $stmt->fetch()['count'];

    // Get unread messages count
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM messages WHERE to_user_id = ? AND is_read = 0");
    $stmt->execute([$user['user_id']]);
    $unreadMessages = $stmt->fetch()['count'];

    // Get this month's revenue
    $stmt = $conn->prepare("
        SELECT SUM(total_price) as revenue
        FROM bookings
        WHERE coach_id = ? AND status = 'completed'
        AND MONTH(confirmed_datetime) = MONTH(NOW())
        AND YEAR(confirmed_datetime) = YEAR(NOW())
    ");
    $stmt->execute([$user['user_id']]);
    $monthlyRevenue = $stmt->fetch()['revenue'] ?? 0;

    sendResponse([
        'stats' => [
            'pending_bookings' => intval($pendingBookings),
            'upcoming_bookings' => intval($upcomingBookings),
            'unread_messages' => intval($unreadMessages),
            'monthly_revenue' => floatval($monthlyRevenue),
            'average_rating' => floatval($profile['average_rating'] ?? 0),
            'total_reviews' => intval($profile['total_reviews'] ?? 0),
            'total_sessions' => intval($profile['total_sessions'] ?? 0),
            'profile_views' => intval($profile['profile_views'] ?? 0)
        ]
    ]);
}

// GET /coach/profile - Get coach profile
if ($route === 'coach/profile' && $method === 'GET') {
    $user = requireAuth(['coach']);

    $stmt = $conn->prepare("SELECT * FROM coach_profiles WHERE user_id = ?");
    $stmt->execute([$user['user_id']]);
    $profile = $stmt->fetch();

    if ($profile) {
        $profile['specializations'] = json_decode($profile['specializations'] ?? '[]', true);
        $profile['certifications'] = json_decode($profile['certifications'] ?? '[]', true);
        $profile['languages'] = json_decode($profile['languages'] ?? '[]', true);
    }

    sendResponse(['profile' => $profile]);
}

// PUT /coach/profile - Update coach profile
if ($route === 'coach/profile' && $method === 'PUT') {
    $user = requireAuth(['coach']);
    $data = getInputData();

    $updates = [];
    $params = [];

    $allowedFields = [
        'bio', 'tagline', 'hourly_rate', 'currency', 'years_experience',
        'location_city', 'location_country', 'location_lat', 'location_lng',
        'offers_in_person', 'offers_online', 'auto_accept_bookings'
    ];

    foreach ($allowedFields as $field) {
        if (isset($data[$field])) {
            $updates[] = "$field = ?";
            $params[] = $data[$field];
        }
    }

    // Handle JSON fields
    $jsonFields = ['specializations', 'certifications', 'languages'];
    foreach ($jsonFields as $field) {
        if (isset($data[$field])) {
            $updates[] = "$field = ?";
            $params[] = json_encode($data[$field]);
        }
    }

    if (empty($updates)) {
        sendError('No fields to update', 400);
    }

    $params[] = $user['user_id'];

    $stmt = $conn->prepare("
        UPDATE coach_profiles
        SET " . implode(', ', $updates) . "
        WHERE user_id = ?
    ");
    $stmt->execute($params);

    sendResponse(['message' => 'Profile updated']);
}

// GET /coach/sessions - Get coaching sessions
if ($route === 'coach/sessions' && $method === 'GET') {
    $user = requireAuth(['coach']);

    $stmt = $conn->prepare("
        SELECT * FROM coaching_sessions
        WHERE coach_id = ?
        ORDER BY created_at DESC
    ");
    $stmt->execute([$user['user_id']]);
    $sessions = $stmt->fetchAll();

    sendResponse(['sessions' => $sessions]);
}

// POST /coach/sessions - Create coaching session
if ($route === 'coach/sessions' && $method === 'POST') {
    $user = requireAuth(['coach']);
    $data = getInputData();

    $errors = validateRequired($data, ['title', 'duration_minutes', 'price']);
    if (!empty($errors)) {
        sendError('Validation failed', 400, $errors);
    }

    try {
        $stmt = $conn->prepare("
            INSERT INTO coaching_sessions (
                coach_id, title, description, session_type, duration_minutes,
                price, currency, max_participants, location_type
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $user['user_id'],
            $data['title'],
            $data['description'] ?? null,
            $data['session_type'] ?? 'individual',
            $data['duration_minutes'],
            $data['price'],
            $data['currency'] ?? 'EUR',
            $data['max_participants'] ?? 1,
            $data['location_type'] ?? 'online'
        ]);

        $sessionId = $conn->lastInsertId();

        sendResponse([
            'message' => 'Session created',
            'session_id' => $sessionId
        ], 201);

    } catch (PDOException $e) {
        error_log("Session creation error: " . $e->getMessage());
        sendError('Failed to create session', 500);
    }
}

// GET /coach/availability - Get availability
if ($route === 'coach/availability' && $method === 'GET') {
    $user = requireAuth(['coach']);

    $stmt = $conn->prepare("
        SELECT * FROM coach_availability
        WHERE coach_id = ?
        ORDER BY day_of_week ASC, start_time ASC
    ");
    $stmt->execute([$user['user_id']]);
    $availability = $stmt->fetchAll();

    sendResponse(['availability' => $availability]);
}

// POST /coach/availability - Set availability
if ($route === 'coach/availability' && $method === 'POST') {
    $user = requireAuth(['coach']);
    $data = getInputData();

    $errors = validateRequired($data, ['day_of_week', 'start_time', 'end_time']);
    if (!empty($errors)) {
        sendError('Validation failed', 400, $errors);
    }

    try {
        $stmt = $conn->prepare("
            INSERT INTO coach_availability (coach_id, day_of_week, start_time, end_time)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            $user['user_id'],
            $data['day_of_week'],
            $data['start_time'],
            $data['end_time']
        ]);

        sendResponse(['message' => 'Availability added'], 201);

    } catch (PDOException $e) {
        error_log("Availability error: " . $e->getMessage());
        sendError('Failed to add availability', 500);
    }
}

// GET /coach/bookings - Get coach bookings
if ($route === 'coach/bookings' && $method === 'GET') {
    $user = requireAuth(['coach']);

    $status = $_GET['status'] ?? '';
    $where = ["coach_id = ?"];
    $params = [$user['user_id']];

    if (!empty($status)) {
        $where[] = "status = ?";
        $params[] = $status;
    }

    $whereClause = implode(' AND ', $where);

    $stmt = $conn->prepare("
        SELECT
            b.*,
            cs.title as session_title,
            cs.duration_minutes,
            u.first_name as client_first_name,
            u.last_name as client_last_name,
            u.profile_image as client_profile_image
        FROM bookings b
        INNER JOIN coaching_sessions cs ON b.session_id = cs.id
        INNER JOIN users u ON b.client_id = u.id
        WHERE $whereClause
        ORDER BY b.requested_datetime DESC
    ");
    $stmt->execute($params);
    $bookings = $stmt->fetchAll();

    sendResponse(['bookings' => $bookings]);
}

// GET /coach/articles - Get articles
if ($route === 'coach/articles' && $method === 'GET') {
    $user = requireAuth(['coach']);

    $stmt = $conn->prepare("
        SELECT * FROM articles
        WHERE coach_id = ?
        ORDER BY created_at DESC
    ");
    $stmt->execute([$user['user_id']]);
    $articles = $stmt->fetchAll();

    foreach ($articles as &$article) {
        $article['tags'] = json_decode($article['tags'] ?? '[]', true);
    }

    sendResponse(['articles' => $articles]);
}

// POST /coach/articles - Create article
if ($route === 'coach/articles' && $method === 'POST') {
    $user = requireAuth(['coach']);
    $data = getInputData();

    $errors = validateRequired($data, ['title', 'content']);
    if (!empty($errors)) {
        sendError('Validation failed', 400, $errors);
    }

    $slug = generateSlug($data['title']);

    try {
        $stmt = $conn->prepare("
            INSERT INTO articles (
                coach_id, title, slug, content, excerpt, featured_image,
                tags, is_published, published_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $isPublished = $data['is_published'] ?? false;
        $publishedAt = $isPublished ? date('Y-m-d H:i:s') : null;

        $stmt->execute([
            $user['user_id'],
            $data['title'],
            $slug,
            $data['content'],
            $data['excerpt'] ?? null,
            $data['featured_image'] ?? null,
            json_encode($data['tags'] ?? []),
            $isPublished ? 1 : 0,
            $publishedAt
        ]);

        $articleId = $conn->lastInsertId();

        // Log activity if published
        if ($isPublished) {
            logActivity($conn, $user['user_id'], 'article', [
                'article_id' => $articleId,
                'title' => $data['title']
            ]);
        }

        sendResponse([
            'message' => 'Article created',
            'article_id' => $articleId
        ], 201);

    } catch (PDOException $e) {
        error_log("Article creation error: " . $e->getMessage());
        sendError('Failed to create article', 500);
    }
}

// If no route matched
sendError('Invalid endpoint or method', 404);
