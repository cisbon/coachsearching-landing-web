<?php
/**
 * Helper Functions
 * CoachSearching.com API
 */

/**
 * Send JSON response
 */
function sendResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data);
    exit();
}

/**
 * Send error response
 */
function sendError($message, $statusCode = 400, $errors = []) {
    $response = ['error' => $message];
    if (!empty($errors)) {
        $response['errors'] = $errors;
    }
    sendResponse($response, $statusCode);
}

/**
 * Validate required fields
 */
function validateRequired($data, $required_fields) {
    $errors = [];
    foreach ($required_fields as $field) {
        if (!isset($data[$field]) || empty(trim($data[$field]))) {
            $errors[$field] = ucfirst($field) . ' is required';
        }
    }
    return $errors;
}

/**
 * Validate email
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

/**
 * Generate secure random string
 */
function generateRandomString($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

/**
 * Generate conversation ID from two user IDs
 */
function generateConversationId($userId1, $userId2) {
    $ids = [$userId1, $userId2];
    sort($ids);
    return hash('sha256', implode('_', $ids));
}

/**
 * Calculate distance between two coordinates (Haversine formula)
 */
function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    $earthRadius = 6371; // kilometers

    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);

    $a = sin($dLat/2) * sin($dLat/2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($dLon/2) * sin($dLon/2);

    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    $distance = $earthRadius * $c;

    return $distance;
}

/**
 * Upload file
 */
function uploadFile($file, $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'webp']) {
    if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
        return ['success' => false, 'error' => 'No file uploaded'];
    }

    // Check file size
    if ($file['size'] > UPLOAD_MAX_SIZE) {
        return ['success' => false, 'error' => 'File size exceeds maximum allowed size'];
    }

    // Check file type
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($file_ext, $allowed_types)) {
        return ['success' => false, 'error' => 'Invalid file type'];
    }

    // Generate unique filename
    $filename = uniqid() . '_' . time() . '.' . $file_ext;
    $upload_path = UPLOAD_PATH . '/' . $filename;

    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
        return ['success' => true, 'filename' => $filename, 'url' => '/uploads/' . $filename];
    }

    return ['success' => false, 'error' => 'Failed to upload file'];
}

/**
 * Sanitize input
 */
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

/**
 * Generate slug from string
 */
function generateSlug($string) {
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $string), '-'));
    return $slug;
}

/**
 * Paginate results
 */
function paginate($query, $page = 1, $perPage = 20) {
    $page = max(1, intval($page));
    $perPage = max(1, min(100, intval($perPage)));
    $offset = ($page - 1) * $perPage;

    return [
        'limit' => $perPage,
        'offset' => $offset,
        'page' => $page
    ];
}

/**
 * Calculate average rating
 */
function calculateAverageRating($conn, $coach_id) {
    $stmt = $conn->prepare("
        SELECT AVG(rating) as avg_rating, COUNT(*) as total_reviews
        FROM reviews
        WHERE coach_id = ? AND is_visible = 1
    ");
    $stmt->execute([$coach_id]);
    $result = $stmt->fetch();

    return [
        'average_rating' => round($result['avg_rating'] ?? 0, 2),
        'total_reviews' => intval($result['total_reviews'] ?? 0)
    ];
}

/**
 * Update coach rating
 */
function updateCoachRating($conn, $coach_id) {
    $rating = calculateAverageRating($conn, $coach_id);

    $stmt = $conn->prepare("
        UPDATE coach_profiles
        SET average_rating = ?, total_reviews = ?
        WHERE user_id = ?
    ");
    $stmt->execute([
        $rating['average_rating'],
        $rating['total_reviews'],
        $coach_id
    ]);
}

/**
 * Send notification
 */
function sendNotification($conn, $user_id, $type, $title, $message, $link = null) {
    $stmt = $conn->prepare("
        INSERT INTO notifications (user_id, type, title, message, link)
        VALUES (?, ?, ?, ?, ?)
    ");
    return $stmt->execute([$user_id, $type, $title, $message, $link]);
}

/**
 * Log activity to feed
 */
function logActivity($conn, $user_id, $activity_type, $activity_data) {
    $stmt = $conn->prepare("
        INSERT INTO activity_feed (user_id, activity_type, activity_data)
        VALUES (?, ?, ?)
    ");
    return $stmt->execute([$user_id, $activity_type, json_encode($activity_data)]);
}

/**
 * Get input data (handles JSON and form data)
 */
function getInputData() {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

    if (strpos($contentType, 'application/json') !== false) {
        $input = file_get_contents('php://input');
        return json_decode($input, true) ?? [];
    }

    return $_POST;
}
