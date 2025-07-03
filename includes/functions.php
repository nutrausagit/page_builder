<?php
// =====================================================
// UTILITY FUNCTIONS
// =====================================================

/**
 * Generate CSRF token
 */
function generateCSRFToken() {
    if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

/**
 * Verify CSRF token
 */
function verifyCSRFToken($token) {
    return isset($_SESSION[CSRF_TOKEN_NAME]) && hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
}

/**
 * Sanitize input
 */
function sanitize($data) {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Validate email
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Generate random string
 */
function generateRandomString($length = 10) {
    return bin2hex(random_bytes($length / 2));
}

/**
 * Create slug from string
 */
function createSlug($string) {
    $slug = strtolower(trim($string));
    $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
    $slug = preg_replace('/-+/', '-', $slug);
    return trim($slug, '-');
}

/**
 * Format file size
 */
function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    
    $bytes /= pow(1024, $pow);
    
    return round($bytes, 2) . ' ' . $units[$pow];
}

/**
 * Get file extension
 */
function getFileExtension($filename) {
    return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
}

/**
 * Check if file type is allowed
 */
function isAllowedFileType($filename) {
    $extension = getFileExtension($filename);
    return in_array($extension, ALLOWED_FILE_TYPES);
}

/**
 * Generate unique filename
 */
function generateUniqueFilename($originalName) {
    $extension = getFileExtension($originalName);
    $name = pathinfo($originalName, PATHINFO_FILENAME);
    $name = preg_replace('/[^a-zA-Z0-9_-]/', '', $name);
    return $name . '_' . time() . '_' . generateRandomString(6) . '.' . $extension;
}

/**
 * Redirect with message
 */
function redirect($url, $message = null, $type = 'info') {
    if ($message) {
        $_SESSION['flash_message'] = $message;
        $_SESSION['flash_type'] = $type;
    }
    header('Location: ' . $url);
    exit;
}

/**
 * Get flash message
 */
function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        $type = $_SESSION['flash_type'] ?? 'info';
        unset($_SESSION['flash_message'], $_SESSION['flash_type']);
        return ['message' => $message, 'type' => $type];
    }
    return null;
}

/**
 * JSON response
 */
function jsonResponse($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Error response
 */
function errorResponse($message, $status = 400) {
    jsonResponse(['success' => false, 'error' => $message], $status);
}

/**
 * Success response
 */
function successResponse($data = [], $message = 'Success') {
    jsonResponse(['success' => true, 'message' => $message, 'data' => $data]);
}

/**
 * Validate JSON
 */
function isValidJSON($string) {
    json_decode($string);
    return json_last_error() === JSON_ERROR_NONE;
}

/**
 * Parse JSON safely
 */
function parseJSON($string, $default = []) {
    if (empty($string)) return $default;
    $decoded = json_decode($string, true);
    return $decoded !== null ? $decoded : $default;
}

/**
 * Log activity
 */
function logActivity($action, $details = '', $userId = null) {
    if (!$userId && isset($_SESSION['user_id'])) {
        $userId = $_SESSION['user_id'];
    }
    
    $logData = [
        'user_id' => $userId,
        'action' => $action,
        'details' => $details,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    // Log to file for now (could be database table later)
    $logEntry = json_encode($logData) . "\n";
    file_put_contents(__DIR__ . '/../logs/activity.log', $logEntry, FILE_APPEND | LOCK_EX);
}

/**
 * Check if user has permission
 */
function hasPermission($permission, $userRole = null) {
    if (!$userRole && isset($_SESSION['user_role'])) {
        $userRole = $_SESSION['user_role'];
    }
    
    $permissions = [
        'admin' => ['*'], // Admin has all permissions
        'editor' => ['view_pages', 'create_page', 'edit_page', 'delete_page', 'view_templates', 'upload_assets'],
        'viewer' => ['view_pages', 'view_templates']
    ];
    
    if (!isset($permissions[$userRole])) {
        return false;
    }
    
    return in_array('*', $permissions[$userRole]) || in_array($permission, $permissions[$userRole]);
}

/**
 * Require permission
 */
function requirePermission($permission) {
    if (!hasPermission($permission)) {
        if (isAjaxRequest()) {
            errorResponse('Akses ditolak', 403);
        } else {
            redirect('/admin/login.php', 'Akses ditolak', 'error');
        }
    }
}

/**
 * Check if request is AJAX
 */
function isAjaxRequest() {
    return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/**
 * Get current URL
 */
function getCurrentURL() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    return $protocol . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
}

/**
 * Time ago format
 */
function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'baru saja';
    if ($time < 3600) return floor($time/60) . ' menit yang lalu';
    if ($time < 86400) return floor($time/3600) . ' jam yang lalu';
    if ($time < 2592000) return floor($time/86400) . ' hari yang lalu';
    if ($time < 31536000) return floor($time/2592000) . ' bulan yang lalu';
    
    return floor($time/31536000) . ' tahun yang lalu';
}

/**
 * Truncate text
 */
function truncateText($text, $length = 100, $suffix = '...') {
    if (strlen($text) <= $length) {
        return $text;
    }
    return substr($text, 0, $length) . $suffix;
}

/**
 * Get gravatar URL
 */
function getGravatarURL($email, $size = 80) {
    $hash = md5(strtolower(trim($email)));
    return "https://www.gravatar.com/avatar/{$hash}?s={$size}&d=identicon";
}

/**
 * Debug dump
 */
function dd($data) {
    echo '<pre>';
    var_dump($data);
    echo '</pre>';
    die();
}
?>
