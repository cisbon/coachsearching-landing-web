<?php
/**
 * Admin Endpoints
 * CoachSearching.com API
 */

$database = new Database();
$conn = $database->getConnection();

$route = $_GET['route'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// GET /admin/stats - Get platform statistics
if ($route === 'admin/stats' && $method === 'GET') {
    $user = requireAuth(['admin', 'admin_delegate']);

    // Total users by role
    $stmt = $conn->query("
        SELECT role, COUNT(*) as count
        FROM users
        WHERE is_active = 1
        GROUP BY role
    ");
    $userStats = $stmt->fetchAll();

    // Total bookings by status
    $stmt = $conn->query("
        SELECT status, COUNT(*) as count
        FROM bookings
        GROUP BY status
    ");
    $bookingStats = $stmt->fetchAll();

    // Total revenue
    $stmt = $conn->query("
        SELECT SUM(total_price) as total_revenue
        FROM bookings
        WHERE status = 'completed'
    ");
    $totalRevenue = $stmt->fetch()['total_revenue'] ?? 0;

    // This month's revenue
    $stmt = $conn->query("
        SELECT SUM(total_price) as monthly_revenue
        FROM bookings
        WHERE status = 'completed'
        AND MONTH(confirmed_datetime) = MONTH(NOW())
        AND YEAR(confirmed_datetime) = YEAR(NOW())
    ");
    $monthlyRevenue = $stmt->fetch()['monthly_revenue'] ?? 0;

    // Top coaches
    $stmt = $conn->query("
        SELECT
            u.id,
            u.first_name,
            u.last_name,
            cp.average_rating,
            cp.total_reviews,
            cp.total_sessions
        FROM users u
        INNER JOIN coach_profiles cp ON u.id = cp.user_id
        WHERE u.is_active = 1
        ORDER BY cp.total_sessions DESC
        LIMIT 10
    ");
    $topCoaches = $stmt->fetchAll();

    sendResponse([
        'stats' => [
            'user_stats' => $userStats,
            'booking_stats' => $bookingStats,
            'total_revenue' => floatval($totalRevenue),
            'monthly_revenue' => floatval($monthlyRevenue),
            'top_coaches' => $topCoaches
        ]
    ]);
}

// GET /admin/users - Get all users
if ($route === 'admin/users' && $method === 'GET') {
    $user = requireAuth(['admin', 'admin_delegate']);

    $page = $_GET['page'] ?? 1;
    $perPage = $_GET['per_page'] ?? 50;
    $role = $_GET['role'] ?? '';
    $search = $_GET['search'] ?? '';

    $pagination = paginate('', $page, $perPage);
    $where = [];
    $params = [];

    if (!empty($role)) {
        $where[] = "role = ?";
        $params[] = $role;
    }

    if (!empty($search)) {
        $where[] = "(first_name LIKE ? OR last_name LIKE ? OR email LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    // Get total count
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM users $whereClause");
    $stmt->execute($params);
    $totalCount = $stmt->fetch()['total'];

    // Get users
    $stmt = $conn->prepare("
        SELECT id, email, role, first_name, last_name, is_active, created_at, last_login
        FROM users
        $whereClause
        ORDER BY created_at DESC
        LIMIT {$pagination['limit']} OFFSET {$pagination['offset']}
    ");
    $stmt->execute($params);
    $users = $stmt->fetchAll();

    sendResponse([
        'users' => $users,
        'pagination' => [
            'page' => $pagination['page'],
            'per_page' => $pagination['limit'],
            'total' => intval($totalCount),
            'total_pages' => ceil($totalCount / $pagination['limit'])
        ]
    ]);
}

// PUT /admin/users/:id - Update user
if (preg_match('#^admin/users/(\d+)$#', $route, $matches) && $method === 'PUT') {
    $user = requireAuth(['admin', 'admin_delegate']);
    $targetUserId = $matches[1];
    $data = getInputData();

    $updates = [];
    $params = [];

    $allowedFields = ['is_active', 'email_verified'];
    foreach ($allowedFields as $field) {
        if (isset($data[$field])) {
            $updates[] = "$field = ?";
            $params[] = $data[$field];
        }
    }

    if (empty($updates)) {
        sendError('No fields to update', 400);
    }

    $params[] = $targetUserId;

    $stmt = $conn->prepare("
        UPDATE users
        SET " . implode(', ', $updates) . "
        WHERE id = ?
    ");
    $stmt->execute($params);

    sendResponse(['message' => 'User updated']);
}

// GET /admin/coaches - Get all coaches
if ($route === 'admin/coaches' && $method === 'GET') {
    $user = requireAuth(['admin', 'admin_delegate']);

    $page = $_GET['page'] ?? 1;
    $perPage = $_GET['per_page'] ?? 50;
    $pagination = paginate('', $page, $perPage);

    $stmt = $conn->prepare("
        SELECT
            u.id,
            u.first_name,
            u.last_name,
            u.email,
            cp.average_rating,
            cp.total_reviews,
            cp.total_sessions,
            cp.is_verified,
            cp.is_featured
        FROM users u
        INNER JOIN coach_profiles cp ON u.id = cp.user_id
        WHERE u.is_active = 1
        ORDER BY u.created_at DESC
        LIMIT {$pagination['limit']} OFFSET {$pagination['offset']}
    ");
    $stmt->execute();
    $coaches = $stmt->fetchAll();

    sendResponse(['coaches' => $coaches]);
}

// PUT /admin/coaches/:id - Update coach
if (preg_match('#^admin/coaches/(\d+)$#', $route, $matches) && $method === 'PUT') {
    $user = requireAuth(['admin', 'admin_delegate']);
    $coachId = $matches[1];
    $data = getInputData();

    $updates = [];
    $params = [];

    $allowedFields = ['is_verified', 'is_featured'];
    foreach ($allowedFields as $field) {
        if (isset($data[$field])) {
            $updates[] = "$field = ?";
            $params[] = $data[$field];
        }
    }

    if (empty($updates)) {
        sendError('No fields to update', 400);
    }

    $params[] = $coachId;

    $stmt = $conn->prepare("
        UPDATE coach_profiles
        SET " . implode(', ', $updates) . "
        WHERE user_id = ?
    ");
    $stmt->execute($params);

    sendResponse(['message' => 'Coach updated']);
}

// GET /admin/bookings - Get all bookings
if ($route === 'admin/bookings' && $method === 'GET') {
    $user = requireAuth(['admin', 'admin_delegate']);

    $page = $_GET['page'] ?? 1;
    $perPage = $_GET['per_page'] ?? 50;
    $pagination = paginate('', $page, $perPage);

    $stmt = $conn->prepare("
        SELECT
            b.*,
            cs.title as session_title,
            coach.first_name as coach_first_name,
            coach.last_name as coach_last_name,
            client.first_name as client_first_name,
            client.last_name as client_last_name
        FROM bookings b
        INNER JOIN coaching_sessions cs ON b.session_id = cs.id
        INNER JOIN users coach ON b.coach_id = coach.id
        INNER JOIN users client ON b.client_id = client.id
        ORDER BY b.created_at DESC
        LIMIT {$pagination['limit']} OFFSET {$pagination['offset']}
    ");
    $stmt->execute();
    $bookings = $stmt->fetchAll();

    sendResponse(['bookings' => $bookings]);
}

// GET /admin/reviews - Get all reviews
if ($route === 'admin/reviews' && $method === 'GET') {
    $user = requireAuth(['admin', 'admin_delegate']);

    $page = $_GET['page'] ?? 1;
    $perPage = $_GET['per_page'] ?? 50;
    $pagination = paginate('', $page, $perPage);

    $stmt = $conn->prepare("
        SELECT
            r.*,
            coach.first_name as coach_first_name,
            coach.last_name as coach_last_name,
            reviewer.first_name as reviewer_first_name,
            reviewer.last_name as reviewer_last_name
        FROM reviews r
        INNER JOIN users coach ON r.coach_id = coach.id
        INNER JOIN users reviewer ON r.reviewer_id = reviewer.id
        ORDER BY r.created_at DESC
        LIMIT {$pagination['limit']} OFFSET {$pagination['offset']}
    ");
    $stmt->execute();
    $reviews = $stmt->fetchAll();

    sendResponse(['reviews' => $reviews]);
}

// PUT /admin/reviews/:id - Update review visibility
if (preg_match('#^admin/reviews/(\d+)$#', $route, $matches) && $method === 'PUT') {
    $user = requireAuth(['admin', 'admin_delegate']);
    $reviewId = $matches[1];
    $data = getInputData();

    if (!isset($data['is_visible'])) {
        sendError('is_visible field required', 400);
    }

    $stmt = $conn->prepare("UPDATE reviews SET is_visible = ? WHERE id = ?");
    $stmt->execute([$data['is_visible'], $reviewId]);

    sendResponse(['message' => 'Review updated']);
}

// GET /admin/delegates - Get admin delegates
if ($route === 'admin/delegates' && $method === 'GET') {
    $user = requireAuth(['admin']); // Only owner admin

    $stmt = $conn->query("
        SELECT
            ad.*,
            u.first_name,
            u.last_name,
            u.email
        FROM admin_delegates ad
        INNER JOIN users u ON ad.delegate_user_id = u.id
        ORDER BY ad.assigned_at DESC
    ");
    $delegates = $stmt->fetchAll();

    sendResponse(['delegates' => $delegates]);
}

// POST /admin/delegates - Assign admin delegate
if ($route === 'admin/delegates' && $method === 'POST') {
    $user = requireAuth(['admin']); // Only owner admin
    $data = getInputData();

    $errors = validateRequired($data, ['user_id']);
    if (!empty($errors)) {
        sendError('Validation failed', 400, $errors);
    }

    try {
        $conn->beginTransaction();

        // Update user role
        $stmt = $conn->prepare("UPDATE users SET role = 'admin_delegate' WHERE id = ?");
        $stmt->execute([$data['user_id']]);

        // Create delegate record
        $stmt = $conn->prepare("
            INSERT INTO admin_delegates (admin_id, delegate_user_id)
            VALUES (?, ?)
        ");
        $stmt->execute([$user['user_id'], $data['user_id']]);

        $conn->commit();

        sendResponse(['message' => 'Admin delegate assigned'], 201);

    } catch (PDOException $e) {
        $conn->rollBack();
        error_log("Delegate assignment error: " . $e->getMessage());
        sendError('Failed to assign delegate', 500);
    }
}

// DELETE /admin/delegates/:id - Remove admin delegate
if (preg_match('#^admin/delegates/(\d+)$#', $route, $matches) && $method === 'DELETE') {
    $user = requireAuth(['admin']); // Only owner admin
    $delegateUserId = $matches[1];

    try {
        $conn->beginTransaction();

        // Update user role back to user
        $stmt = $conn->prepare("UPDATE users SET role = 'user' WHERE id = ?");
        $stmt->execute([$delegateUserId]);

        // Delete delegate record
        $stmt = $conn->prepare("DELETE FROM admin_delegates WHERE delegate_user_id = ?");
        $stmt->execute([$delegateUserId]);

        $conn->commit();

        sendResponse(['message' => 'Admin delegate removed']);

    } catch (PDOException $e) {
        $conn->rollBack();
        error_log("Delegate removal error: " . $e->getMessage());
        sendError('Failed to remove delegate', 500);
    }
}

// If no route matched
sendError('Invalid endpoint or method', 404);
