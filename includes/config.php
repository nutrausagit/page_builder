<?php
// =====================================================
// KONFIGURASI APLIKASI PAGE BUILDER
// =====================================================

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'page_builder');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Application Settings
define('APP_NAME', 'Page Builder Pro');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'http://localhost');
define('BASE_PATH', '/page_builder');

// Security Settings
define('SESSION_LIFETIME', 7200); // 2 hours
define('CSRF_TOKEN_NAME', '_token');
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 300); // 5 minutes

// File Upload Settings
define('UPLOAD_PATH', __DIR__ . '/../assets/uploads/');
define('UPLOAD_URL', BASE_PATH . '/assets/uploads/');
define('MAX_FILE_SIZE', 5242880); // 5MB
define('ALLOWED_FILE_TYPES', ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx', 'mp4', 'mov', 'avi']);

// Image Settings
define('THUMBNAIL_WIDTH', 300);
define('THUMBNAIL_HEIGHT', 200);
define('IMAGE_QUALITY', 85);

// Debug Mode
define('DEBUG_MODE', true);
define('LOG_ERRORS', true);
define('ERROR_LOG_FILE', __DIR__ . '/../logs/error.log');

// Timezone
date_default_timezone_set('Asia/Jakarta');

// Error Reporting
if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Session Configuration
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path' => '/',
        'secure' => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    session_start();
}

// Auto-create upload directory
if (!is_dir(UPLOAD_PATH)) {
    mkdir(UPLOAD_PATH, 0755, true);
}

// Auto-create logs directory
$log_dir = dirname(ERROR_LOG_FILE);
if (!is_dir($log_dir)) {
    mkdir($log_dir, 0755, true);
}
?>
