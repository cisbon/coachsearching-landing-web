<?php
/**
 * Configuration File
 * CoachSearching.com API
 */

// Error Reporting
if ($_ENV['APP_DEBUG'] === 'true') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Timezone
date_default_timezone_set('UTC');

// CORS Headers
$allowed_origins = explode(',', $_ENV['CORS_ALLOWED_ORIGINS'] ?? '');
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if (in_array($origin, $allowed_origins)) {
    header("Access-Control-Allow-Origin: $origin");
} else {
    header("Access-Control-Allow-Origin: " . ($allowed_origins[0] ?? '*'));
}

header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Credentials: true");
header("Content-Type: application/json; charset=UTF-8");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Constants
define('APP_NAME', 'CoachSearching');
define('APP_VERSION', '1.0.0');
define('JWT_SECRET', $_ENV['JWT_SECRET']);
define('SESSION_LIFETIME', intval($_ENV['SESSION_LIFETIME'] ?? 7200));
define('UPLOAD_PATH', __DIR__ . '/../../uploads');
define('UPLOAD_MAX_SIZE', intval($_ENV['UPLOAD_MAX_SIZE'] ?? 5242880)); // 5MB
define('PLATFORM_FEE_PERCENTAGE', floatval($_ENV['PLATFORM_FEE_PERCENTAGE'] ?? 15));

// Ensure upload directory exists
if (!file_exists(UPLOAD_PATH)) {
    mkdir(UPLOAD_PATH, 0755, true);
}

// Include required files
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
