<?php
/**
 * API Entry Point
 * CoachSearching.com API
 */

// Load environment variables
if (file_exists(__DIR__ . '/.env')) {
    $env = parse_ini_file(__DIR__ . '/.env');
    foreach ($env as $key => $value) {
        $_ENV[$key] = $value;
    }
}

// Load configuration
require_once __DIR__ . '/config/config.php';

// Get route
$route = $_GET['route'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// Route mapping
$routes = [
    // Authentication
    'auth/register' => 'endpoints/auth.php',
    'auth/login' => 'endpoints/auth.php',
    'auth/logout' => 'endpoints/auth.php',
    'auth/me' => 'endpoints/auth.php',
    'auth/verify-email' => 'endpoints/auth.php',

    // Coaches
    'coaches' => 'endpoints/coaches.php',
    'coaches/search' => 'endpoints/coaches.php',
    'coaches/profile' => 'endpoints/coaches.php',
    'coaches/sessions' => 'endpoints/coaches.php',
    'coaches/availability' => 'endpoints/coaches.php',
    'coaches/articles' => 'endpoints/coaches.php',

    // Bookings
    'bookings' => 'endpoints/bookings.php',
    'bookings/create' => 'endpoints/bookings.php',
    'bookings/confirm' => 'endpoints/bookings.php',
    'bookings/cancel' => 'endpoints/bookings.php',

    // Reviews
    'reviews' => 'endpoints/reviews.php',
    'reviews/create' => 'endpoints/reviews.php',

    // Questionnaire
    'questionnaire' => 'endpoints/questionnaire.php',
    'questionnaire/submit' => 'endpoints/questionnaire.php',
    'questionnaire/match' => 'endpoints/questionnaire.php',

    // Messages
    'messages' => 'endpoints/messages.php',
    'messages/conversations' => 'endpoints/messages.php',
    'messages/send' => 'endpoints/messages.php',

    // User
    'user/profile' => 'endpoints/user.php',
    'user/follow' => 'endpoints/user.php',
    'user/unfollow' => 'endpoints/user.php',
    'user/feed' => 'endpoints/user.php',
    'user/bookings' => 'endpoints/user.php',

    // Business
    'business/profile' => 'endpoints/business.php',
    'business/invite' => 'endpoints/business.php',
    'business/team' => 'endpoints/business.php',

    // Coach Dashboard
    'coach/dashboard' => 'endpoints/coach.php',
    'coach/profile' => 'endpoints/coach.php',
    'coach/sessions' => 'endpoints/coach.php',
    'coach/availability' => 'endpoints/coach.php',
    'coach/bookings' => 'endpoints/coach.php',
    'coach/articles' => 'endpoints/coach.php',

    // Admin
    'admin/users' => 'endpoints/admin.php',
    'admin/coaches' => 'endpoints/admin.php',
    'admin/bookings' => 'endpoints/admin.php',
    'admin/reviews' => 'endpoints/admin.php',
    'admin/delegates' => 'endpoints/admin.php',
    'admin/stats' => 'endpoints/admin.php',

    // Payments
    'payments/create-intent' => 'endpoints/payments.php',
    'payments/webhook' => 'endpoints/payments.php',

    // Notifications
    'notifications' => 'endpoints/notifications.php',

    // Upload
    'upload' => 'endpoints/upload.php',
];

// Find matching route
$endpoint = null;
foreach ($routes as $pattern => $file) {
    if ($route === $pattern || strpos($route, $pattern) === 0) {
        $endpoint = $file;
        break;
    }
}

// Load endpoint or return 404
if ($endpoint && file_exists(__DIR__ . '/' . $endpoint)) {
    require_once __DIR__ . '/' . $endpoint;
} else {
    sendError('Endpoint not found', 404);
}
