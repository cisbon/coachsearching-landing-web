<?php
/**
 * Notifications Endpoints
 * CoachSearching.com API
 */

$database = new Database();
$conn = $database->getConnection();

$route = $_GET['route'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// GET /notifications - Get user notifications
if ($route === 'notifications' && $method === 'GET') {
    $user = requireAuth();

    $page = $_GET['page'] ?? 1;
    $perPage = $_GET['per_page'] ?? 50;
    $pagination = paginate('', $page, $perPage);

    $stmt = $conn->prepare("
        SELECT * FROM notifications
        WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT {$pagination['limit']} OFFSET {$pagination['offset']}
    ");
    $stmt->execute([$user['user_id']]);
    $notifications = $stmt->fetchAll();

    // Get unread count
    $stmt = $conn->prepare("
        SELECT COUNT(*) as unread_count
        FROM notifications
        WHERE user_id = ? AND is_read = 0
    ");
    $stmt->execute([$user['user_id']]);
    $unreadCount = $stmt->fetch()['unread_count'];

    sendResponse([
        'notifications' => $notifications,
        'unread_count' => intval($unreadCount)
    ]);
}

// PUT /notifications/mark-read - Mark notifications as read
if ($route === 'notifications/mark-read' && $method === 'PUT') {
    $user = requireAuth();
    $data = getInputData();

    if (isset($data['notification_id'])) {
        // Mark single notification as read
        $stmt = $conn->prepare("
            UPDATE notifications
            SET is_read = 1, read_at = NOW()
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([$data['notification_id'], $user['user_id']]);
    } else {
        // Mark all as read
        $stmt = $conn->prepare("
            UPDATE notifications
            SET is_read = 1, read_at = NOW()
            WHERE user_id = ? AND is_read = 0
        ");
        $stmt->execute([$user['user_id']]);
    }

    sendResponse(['message' => 'Notifications marked as read']);
}

// If no route matched
sendError('Invalid endpoint or method', 404);
