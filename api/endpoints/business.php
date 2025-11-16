<?php
/**
 * Business Endpoints
 * CoachSearching.com API
 */

$database = new Database();
$conn = $database->getConnection();

$route = $_GET['route'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// GET /business/profile - Get business profile
if ($route === 'business/profile' && $method === 'GET') {
    $user = requireAuth(['business']);

    $stmt = $conn->prepare("SELECT * FROM business_profiles WHERE user_id = ?");
    $stmt->execute([$user['user_id']]);
    $profile = $stmt->fetch();

    sendResponse(['profile' => $profile]);
}

// PUT /business/profile - Update business profile
if ($route === 'business/profile' && $method === 'PUT') {
    $user = requireAuth(['business']);
    $data = getInputData();

    $updates = [];
    $params = [];

    $allowedFields = ['company_name', 'company_size', 'industry', 'website', 'description', 'address', 'city', 'country', 'vat_number'];
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
        UPDATE business_profiles
        SET " . implode(', ', $updates) . "
        WHERE user_id = ?
    ");
    $stmt->execute($params);

    sendResponse(['message' => 'Profile updated']);
}

// POST /business/invite - Invite team member
if ($route === 'business/invite' && $method === 'POST') {
    $user = requireAuth(['business']);
    $data = getInputData();

    $errors = validateRequired($data, ['email']);
    if (!empty($errors)) {
        sendError('Validation failed', 400, $errors);
    }

    if (!validateEmail($data['email'])) {
        sendError('Invalid email address', 400);
    }

    // Get business profile
    $stmt = $conn->prepare("SELECT id FROM business_profiles WHERE user_id = ?");
    $stmt->execute([$user['user_id']]);
    $businessProfile = $stmt->fetch();

    if (!$businessProfile) {
        sendError('Business profile not found', 404);
    }

    // Check if user exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$data['email']]);
    $invitedUser = $stmt->fetch();

    $invitedUserId = $invitedUser['id'] ?? null;
    $inviteToken = generateRandomString(64);

    try {
        $stmt = $conn->prepare("
            INSERT INTO business_team_members (business_id, user_id, invite_token, invite_email, status)
            VALUES (?, ?, ?, ?, 'pending')
        ");
        $stmt->execute([
            $businessProfile['id'],
            $invitedUserId,
            $inviteToken,
            $data['email']
        ]);

        // TODO: Send invitation email

        sendResponse([
            'message' => 'Invitation sent',
            'invite_token' => $inviteToken
        ], 201);

    } catch (PDOException $e) {
        error_log("Invite error: " . $e->getMessage());
        sendError('Failed to send invitation', 500);
    }
}

// GET /business/team - Get team members
if ($route === 'business/team' && $method === 'GET') {
    $user = requireAuth(['business']);

    $stmt = $conn->prepare("SELECT id FROM business_profiles WHERE user_id = ?");
    $stmt->execute([$user['user_id']]);
    $businessProfile = $stmt->fetch();

    if (!$businessProfile) {
        sendError('Business profile not found', 404);
    }

    $stmt = $conn->prepare("
        SELECT
            btm.*,
            u.first_name,
            u.last_name,
            u.email,
            u.profile_image
        FROM business_team_members btm
        LEFT JOIN users u ON btm.user_id = u.id
        WHERE btm.business_id = ?
        ORDER BY btm.invited_at DESC
    ");
    $stmt->execute([$businessProfile['id']]);
    $teamMembers = $stmt->fetchAll();

    sendResponse(['team_members' => $teamMembers]);
}

// If no route matched
sendError('Invalid endpoint or method', 404);
