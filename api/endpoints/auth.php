<?php
/**
 * Authentication Endpoints
 * CoachSearching.com API
 */

$database = new Database();
$conn = $database->getConnection();

$route = $_GET['route'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// GET /auth/me - Get current user
if ($route === 'auth/me' && $method === 'GET') {
    $user = getCurrentUser();

    if (!$user) {
        sendError('Not authenticated', 401);
    }

    // Get full user details
    $stmt = $conn->prepare("
        SELECT id, email, role, first_name, last_name, phone, profile_image, email_verified, created_at
        FROM users
        WHERE id = ? AND is_active = 1
    ");
    $stmt->execute([$user['user_id']]);
    $userData = $stmt->fetch();

    if (!$userData) {
        sendError('User not found', 404);
    }

    // Get role-specific data
    $roleData = null;
    if ($userData['role'] === 'coach') {
        $stmt = $conn->prepare("SELECT * FROM coach_profiles WHERE user_id = ?");
        $stmt->execute([$userData['id']]);
        $roleData = $stmt->fetch();
    } elseif ($userData['role'] === 'business') {
        $stmt = $conn->prepare("SELECT * FROM business_profiles WHERE user_id = ?");
        $stmt->execute([$userData['id']]);
        $roleData = $stmt->fetch();
    }

    sendResponse([
        'user' => $userData,
        'profile' => $roleData
    ]);
}

// POST /auth/register - Register new user
if ($route === 'auth/register' && $method === 'POST') {
    $data = getInputData();

    // Validate required fields
    $errors = validateRequired($data, ['email', 'password', 'first_name', 'last_name', 'role']);
    if (!empty($errors)) {
        sendError('Validation failed', 400, $errors);
    }

    // Validate email
    if (!validateEmail($data['email'])) {
        sendError('Invalid email address', 400);
    }

    // Validate role
    $allowedRoles = ['user', 'business', 'coach'];
    if (!in_array($data['role'], $allowedRoles)) {
        sendError('Invalid role', 400);
    }

    // Validate password strength
    if (strlen($data['password']) < 8) {
        sendError('Password must be at least 8 characters long', 400);
    }

    // Check if email already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$data['email']]);
    if ($stmt->fetch()) {
        sendError('Email already registered', 400);
    }

    // Hash password
    $passwordHash = hashPassword($data['password']);

    // Create user
    try {
        $stmt = $conn->prepare("
            INSERT INTO users (email, password_hash, role, first_name, last_name, phone)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['email'],
            $passwordHash,
            $data['role'],
            $data['first_name'],
            $data['last_name'],
            $data['phone'] ?? null
        ]);

        $userId = $conn->lastInsertId();

        // Create role-specific profile
        if ($data['role'] === 'coach') {
            $stmt = $conn->prepare("INSERT INTO coach_profiles (user_id) VALUES (?)");
            $stmt->execute([$userId]);
        } elseif ($data['role'] === 'business') {
            $stmt = $conn->prepare("
                INSERT INTO business_profiles (user_id, company_name)
                VALUES (?, ?)
            ");
            $stmt->execute([$userId, $data['company_name'] ?? '']);
        }

        // Generate JWT token
        $token = generateJWT($userId, $data['email'], $data['role']);

        // Create session
        createSession($conn, $userId, $token);

        sendResponse([
            'message' => 'Registration successful',
            'token' => $token,
            'user' => [
                'id' => $userId,
                'email' => $data['email'],
                'role' => $data['role'],
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name']
            ]
        ], 201);
    } catch (PDOException $e) {
        error_log("Registration error: " . $e->getMessage());
        sendError('Registration failed', 500);
    }
}

// POST /auth/login - Login user
if ($route === 'auth/login' && $method === 'POST') {
    $data = getInputData();

    // Validate required fields
    $errors = validateRequired($data, ['email', 'password']);
    if (!empty($errors)) {
        sendError('Validation failed', 400, $errors);
    }

    // Find user
    $stmt = $conn->prepare("
        SELECT id, email, password_hash, role, first_name, last_name, is_active
        FROM users
        WHERE email = ?
    ");
    $stmt->execute([$data['email']]);
    $user = $stmt->fetch();

    if (!$user) {
        sendError('Invalid credentials', 401);
    }

    // Check if user is active
    if (!$user['is_active']) {
        sendError('Account is inactive', 403);
    }

    // Verify password
    if (!verifyPassword($data['password'], $user['password_hash'])) {
        sendError('Invalid credentials', 401);
    }

    // Generate JWT token
    $token = generateJWT($user['id'], $user['email'], $user['role']);

    // Create session
    createSession($conn, $user['id'], $token);

    // Update last login
    $stmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
    $stmt->execute([$user['id']]);

    sendResponse([
        'message' => 'Login successful',
        'token' => $token,
        'user' => [
            'id' => $user['id'],
            'email' => $user['email'],
            'role' => $user['role'],
            'first_name' => $user['first_name'],
            'last_name' => $user['last_name']
        ]
    ]);
}

// POST /auth/logout - Logout user
if ($route === 'auth/logout' && $method === 'POST') {
    $user = requireAuth();

    // Delete user sessions
    $stmt = $conn->prepare("DELETE FROM sessions WHERE user_id = ?");
    $stmt->execute([$user['user_id']]);

    sendResponse(['message' => 'Logout successful']);
}

// If no route matched
sendError('Invalid endpoint or method', 404);
