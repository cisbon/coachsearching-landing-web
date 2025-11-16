<?php
/**
 * Upload Endpoints
 * CoachSearching.com API
 */

$database = new Database();
$conn = $database->getConnection();

$route = $_GET['route'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// POST /upload - Upload file
if ($route === 'upload' && $method === 'POST') {
    $user = requireAuth();

    if (!isset($_FILES['file'])) {
        sendError('No file uploaded', 400);
    }

    $file = $_FILES['file'];
    $fileType = $_GET['type'] ?? 'image';

    $allowedTypes = explode(',', $_ENV['ALLOWED_IMAGE_TYPES'] ?? 'jpg,jpeg,png,gif,webp');

    $result = uploadFile($file, $allowedTypes);

    if (!$result['success']) {
        sendError($result['error'], 400);
    }

    sendResponse([
        'message' => 'File uploaded successfully',
        'url' => $result['url'],
        'filename' => $result['filename']
    ], 201);
}

// If no route matched
sendError('Invalid endpoint or method', 404);
