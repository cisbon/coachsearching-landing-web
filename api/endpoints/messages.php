<?php
/**
 * Messages Endpoints
 * CoachSearching.com API
 */

$database = new Database();
$conn = $database->getConnection();

$route = $_GET['route'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// GET /messages/conversations - Get all conversations
if ($route === 'messages/conversations' && $method === 'GET') {
    $user = requireAuth();

    $stmt = $conn->prepare("
        SELECT
            m.conversation_id,
            m.from_user_id,
            m.to_user_id,
            m.message,
            m.created_at,
            m.is_read,
            u1.first_name as other_first_name,
            u1.last_name as other_last_name,
            u1.profile_image as other_profile_image,
            u1.role as other_role
        FROM messages m
        INNER JOIN (
            SELECT conversation_id, MAX(id) as last_message_id
            FROM messages
            WHERE from_user_id = ? OR to_user_id = ?
            GROUP BY conversation_id
        ) latest ON m.id = latest.last_message_id
        INNER JOIN users u1 ON (
            CASE
                WHEN m.from_user_id = ? THEN m.to_user_id
                ELSE m.from_user_id
            END = u1.id
        )
        ORDER BY m.created_at DESC
    ");
    $stmt->execute([$user['user_id'], $user['user_id'], $user['user_id']]);
    $conversations = $stmt->fetchAll();

    // Get unread count for each conversation
    foreach ($conversations as &$conversation) {
        $stmt = $conn->prepare("
            SELECT COUNT(*) as unread_count
            FROM messages
            WHERE conversation_id = ? AND to_user_id = ? AND is_read = 0
        ");
        $stmt->execute([$conversation['conversation_id'], $user['user_id']]);
        $conversation['unread_count'] = intval($stmt->fetch()['unread_count']);
    }

    sendResponse(['conversations' => $conversations]);
}

// GET /messages?conversation_id=xxx or /messages?user_id=123
if ($route === 'messages' && $method === 'GET') {
    $user = requireAuth();

    $conversationId = $_GET['conversation_id'] ?? null;
    $otherUserId = $_GET['user_id'] ?? null;

    if (!$conversationId && !$otherUserId) {
        sendError('conversation_id or user_id required', 400);
    }

    // Generate conversation ID if not provided
    if (!$conversationId && $otherUserId) {
        $conversationId = generateConversationId($user['user_id'], $otherUserId);
    }

    // Get messages
    $stmt = $conn->prepare("
        SELECT
            m.*,
            u.first_name,
            u.last_name,
            u.profile_image
        FROM messages m
        INNER JOIN users u ON m.from_user_id = u.id
        WHERE m.conversation_id = ?
        ORDER BY m.created_at ASC
    ");
    $stmt->execute([$conversationId]);
    $messages = $stmt->fetchAll();

    // Mark messages as read
    $stmt = $conn->prepare("
        UPDATE messages
        SET is_read = 1, read_at = NOW()
        WHERE conversation_id = ? AND to_user_id = ? AND is_read = 0
    ");
    $stmt->execute([$conversationId, $user['user_id']]);

    sendResponse(['messages' => $messages]);
}

// POST /messages/send - Send message
if ($route === 'messages/send' && $method === 'POST') {
    $user = requireAuth();
    $data = getInputData();

    $errors = validateRequired($data, ['to_user_id', 'message']);
    if (!empty($errors)) {
        sendError('Validation failed', 400, $errors);
    }

    // Check if recipient exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE id = ? AND is_active = 1");
    $stmt->execute([$data['to_user_id']]);
    if (!$stmt->fetch()) {
        sendError('Recipient not found', 404);
    }

    // Generate conversation ID
    $conversationId = generateConversationId($user['user_id'], $data['to_user_id']);

    try {
        $stmt = $conn->prepare("
            INSERT INTO messages (conversation_id, from_user_id, to_user_id, message)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            $conversationId,
            $user['user_id'],
            $data['to_user_id'],
            $data['message']
        ]);

        $messageId = $conn->lastInsertId();

        // Send notification
        sendNotification(
            $conn,
            $data['to_user_id'],
            'message',
            'New Message',
            'You have a new message',
            "/messages?conversation_id={$conversationId}"
        );

        sendResponse([
            'message' => 'Message sent',
            'message_id' => $messageId
        ], 201);

    } catch (PDOException $e) {
        error_log("Message send error: " . $e->getMessage());
        sendError('Failed to send message', 500);
    }
}

// If no route matched
sendError('Invalid endpoint or method', 404);
