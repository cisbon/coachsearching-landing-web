<?php
/**
 * Authentication Functions
 * CoachSearching.com API
 */

/**
 * Generate JWT token
 */
function generateJWT($userId, $email, $role) {
    $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
    $payload = json_encode([
        'user_id' => $userId,
        'email' => $email,
        'role' => $role,
        'iat' => time(),
        'exp' => time() + SESSION_LIFETIME
    ]);

    $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
    $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));

    $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, JWT_SECRET, true);
    $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));

    return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
}

/**
 * Verify JWT token
 */
function verifyJWT($token) {
    if (empty($token)) {
        return false;
    }

    $tokenParts = explode('.', $token);
    if (count($tokenParts) !== 3) {
        return false;
    }

    $header = base64_decode($tokenParts[0]);
    $payload = base64_decode($tokenParts[1]);
    $signatureProvided = $tokenParts[2];

    $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
    $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));

    $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, JWT_SECRET, true);
    $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));

    if ($base64UrlSignature !== $signatureProvided) {
        return false;
    }

    $payloadData = json_decode($payload, true);

    if (!isset($payloadData['exp']) || $payloadData['exp'] < time()) {
        return false;
    }

    return $payloadData;
}

/**
 * Get current user from JWT token
 */
function getCurrentUser() {
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';

    if (empty($authHeader)) {
        return null;
    }

    // Extract token from "Bearer TOKEN"
    $token = str_replace('Bearer ', '', $authHeader);

    return verifyJWT($token);
}

/**
 * Require authentication
 */
function requireAuth($allowedRoles = []) {
    $user = getCurrentUser();

    if (!$user) {
        sendError('Authentication required', 401);
    }

    if (!empty($allowedRoles) && !in_array($user['role'], $allowedRoles)) {
        sendError('Access denied', 403);
    }

    return $user;
}

/**
 * Check if user is admin or admin_delegate
 */
function isAdmin($user) {
    return in_array($user['role'], ['admin', 'admin_delegate']);
}

/**
 * Check if user is owner admin (not delegate)
 */
function isOwnerAdmin($user) {
    return $user['role'] === 'admin';
}

/**
 * Hash password
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT);
}

/**
 * Verify password
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Create session
 */
function createSession($conn, $userId, $token) {
    $sessionId = generateRandomString(128);
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

    $stmt = $conn->prepare("
        INSERT INTO sessions (id, user_id, ip_address, user_agent, payload)
        VALUES (?, ?, ?, ?, ?)
    ");

    $payload = json_encode(['token' => $token]);

    $stmt->execute([$sessionId, $userId, $ipAddress, $userAgent, $payload]);

    return $sessionId;
}

/**
 * Clean expired sessions
 */
function cleanExpiredSessions($conn) {
    $stmt = $conn->prepare("
        DELETE FROM sessions
        WHERE last_activity < DATE_SUB(NOW(), INTERVAL ? SECOND)
    ");
    $stmt->execute([SESSION_LIFETIME]);
}
