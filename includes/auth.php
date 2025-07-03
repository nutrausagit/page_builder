<?php
require_once __DIR__ . '/config.php';
// =====================================================
// AUTHENTICATION & AUTHORIZATION
// =====================================================

class Auth {
    private static $loginAttempts = [];
    
    /**
     * Login user
     */
    public static function login($username, $password) {
        // Check if IP is locked
        if (self::isIPLocked()) {
            return ['success' => false, 'message' => 'Terlalu banyak percobaan login. Coba lagi dalam 5 menit.'];
        }
        
        // Find user
        $user = dbFetch(
            "SELECT * FROM users WHERE (username = :username OR email = :username) AND is_active = 1",
            ['username' => $username]
        );
        
        if (!$user) {
            self::recordFailedAttempt();
            return ['success' => false, 'message' => 'Username atau password salah.'];
        }
        
        // Verify password
        if (!password_verify($password, $user['password'])) {
            self::recordFailedAttempt();
            return ['success' => false, 'message' => 'Username atau password salah.'];
        }
        
        // Clear failed attempts
        self::clearFailedAttempts();
        
        // Set session
        self::setUserSession($user);
        
        // Log activity
        logActivity('user_login', "User {$user['username']} logged in", $user['id']);
        
        return ['success' => true, 'message' => 'Login berhasil.', 'user' => $user];
    }
    
    /**
     * Logout user
     */
    public static function logout() {
        if (isset($_SESSION['user_id'])) {
            logActivity('user_logout', "User logged out", $_SESSION['user_id']);
        }
        
        session_destroy();
        session_start();
        session_regenerate_id(true);
    }
    
    /**
     * Check if user is logged in
     */
    public static function isLoggedIn() {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }
    
    /**
     * Get current user
     */
    public static function getCurrentUser() {
        if (!self::isLoggedIn()) {
            return null;
        }
        
        static $user = null;
        if ($user === null) {
            $user = dbFetch(
                "SELECT id, username, email, role, created_at FROM users WHERE id = :id AND is_active = 1",
                ['id' => $_SESSION['user_id']]
            );
        }
        
        return $user;
    }
    
    /**
     * Get user ID
     */
    public static function getUserId() {
        return $_SESSION['user_id'] ?? null;
    }
    
    /**
     * Get user role
     */
    public static function getUserRole() {
        return $_SESSION['user_role'] ?? null;
    }
    
    /**
     * Check if user has role
     */
    public static function hasRole($role) {
        return self::getUserRole() === $role;
    }
    
    /**
     * Check if user is admin
     */
    public static function isAdmin() {
        return self::hasRole('admin');
    }
    
    /**
     * Require login
     */
    public static function requireLogin() {
        if (!self::isLoggedIn()) {
            if (isAjaxRequest()) {
                errorResponse('Login diperlukan', 401);
            } else {
                redirect('/admin/login.php', 'Silakan login terlebih dahulu');
            }
        }
    }
    
    /**
     * Require admin role
     */
    public static function requireAdmin() {
        self::requireLogin();
        if (!self::isAdmin()) {
            if (isAjaxRequest()) {
                errorResponse('Akses admin diperlukan', 403);
            } else {
                redirect('/admin/dashboard.php', 'Akses ditolak', 'error');
            }
        }
    }
    
    /**
     * Set user session
     */
    private static function setUserSession($user) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['login_time'] = time();
    }
    
    /**
     * Record failed login attempt
     */
    private static function recordFailedAttempt() {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        if (!isset(self::$loginAttempts[$ip])) {
            self::$loginAttempts[$ip] = [];
        }
        self::$loginAttempts[$ip][] = time();
        $_SESSION['login_attempts'] = self::$loginAttempts;
    }
    
    /**
     * Clear failed attempts for current IP
     */
    private static function clearFailedAttempts() {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        if (isset($_SESSION['login_attempts'][$ip])) {
            unset($_SESSION['login_attempts'][$ip]);
            self::$loginAttempts = $_SESSION['login_attempts'];
        }
    }
    
    /**
     * Check if current IP is locked
     */
    private static function isIPLocked() {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        
        if (isset($_SESSION['login_attempts'][$ip])) {
            self::$loginAttempts = $_SESSION['login_attempts'];
            $attempts = self::$loginAttempts[$ip];
            
            // Remove old attempts (older than lockout time)
            $cutoff = time() - LOGIN_LOCKOUT_TIME;
            $attempts = array_filter($attempts, function($timestamp) use ($cutoff) {
                return $timestamp > $cutoff;
            });
            
            self::$loginAttempts[$ip] = $attempts;
            $_SESSION['login_attempts'] = self::$loginAttempts;
            
            return count($attempts) >= MAX_LOGIN_ATTEMPTS;
        }
        
        return false;
    }
    
    /**
     * Register new user (if enabled)
     */
    public static function register($username, $email, $password, $role = 'editor') {
        // Check if registration is enabled
        $setting = dbFetch("SELECT setting_value FROM settings WHERE setting_key = 'enable_registration'");
        if (!$setting || $setting['setting_value'] !== 'true') {
            return ['success' => false, 'message' => 'Registrasi tidak diaktifkan.'];
        }
        
        // Validate input
        if (empty($username) || empty($email) || empty($password)) {
            return ['success' => false, 'message' => 'Semua field harus diisi.'];
        }
        
        if (!isValidEmail($email)) {
            return ['success' => false, 'message' => 'Format email tidak valid.'];
        }
        
        if (strlen($password) < 6) {
            return ['success' => false, 'message' => 'Password minimal 6 karakter.'];
        }
        
        // Check if username or email already exists
        if (dbExists('users', 'username = :username', ['username' => $username])) {
            return ['success' => false, 'message' => 'Username sudah digunakan.'];
        }
        
        if (dbExists('users', 'email = :email', ['email' => $email])) {
            return ['success' => false, 'message' => 'Email sudah digunakan.'];
        }
        
        // Hash password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert user
        $userId = dbInsert('users', [
            'username' => $username,
            'email' => $email,
            'password' => $hashedPassword,
            'role' => $role
        ]);
        
        if ($userId) {
            logActivity('user_register', "New user registered: {$username}", $userId);
            return ['success' => true, 'message' => 'Registrasi berhasil.', 'user_id' => $userId];
        }
        
        return ['success' => false, 'message' => 'Gagal membuat akun.'];
    }
    
    /**
     * Change password
     */
    public static function changePassword($userId, $currentPassword, $newPassword) {
        $user = dbFetch("SELECT password FROM users WHERE id = :id", ['id' => $userId]);
        if (!$user) {
            return ['success' => false, 'message' => 'User tidak ditemukan.'];
        }
        
        if (!password_verify($currentPassword, $user['password'])) {
            return ['success' => false, 'message' => 'Password lama salah.'];
        }
        
        if (strlen($newPassword) < 6) {
            return ['success' => false, 'message' => 'Password baru minimal 6 karakter.'];
        }
        
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $updated = dbUpdate('users', ['password' => $hashedPassword], 'id = :id', ['id' => $userId]);
        
        if ($updated) {
            logActivity('password_change', 'Password changed', $userId);
            return ['success' => true, 'message' => 'Password berhasil diubah.'];
        }
        
        return ['success' => false, 'message' => 'Gagal mengubah password.'];
    }
    
    /**
     * Reset password
     */
    public static function resetPassword($email) {
        $user = dbFetch("SELECT id, username FROM users WHERE email = :email AND is_active = 1", ['email' => $email]);
        if (!$user) {
            return ['success' => false, 'message' => 'Email tidak ditemukan.'];
        }
        
        // Generate new temporary password
        $tempPassword = generateRandomString(12);
        $hashedPassword = password_hash($tempPassword, PASSWORD_DEFAULT);
        
        $updated = dbUpdate('users', ['password' => $hashedPassword], 'id = :id', ['id' => $user['id']]);
        
        if ($updated) {
            // In a real application, send email here
            logActivity('password_reset', "Password reset for user: {$user['username']}", $user['id']);
            
            return [
                'success' => true, 
                'message' => 'Password baru telah dibuat.',
                'temp_password' => $tempPassword // Remove this in production
            ];
        }
        
        return ['success' => false, 'message' => 'Gagal reset password.'];
    }
}

// Helper functions
function auth() {
    return new Auth();
}

function isLoggedIn() {
    return Auth::isLoggedIn();
}

function getCurrentUser() {
    return Auth::getCurrentUser();
}

function getUserId() {
    return Auth::getUserId();
}

function getUserRole() {
    return Auth::getUserRole();
}

function requireLogin() {
    Auth::requireLogin();
}

function requireAdmin() {
    Auth::requireAdmin();
}
?>
