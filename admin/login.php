<?php
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirect('/admin/dashboard.php');
}

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['_token'] ?? '')) {
        $error = 'Token keamanan tidak valid.';
    } else {
        $username = sanitize($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (empty($username) || empty($password)) {
            $error = 'Username dan password harus diisi.';
        } else {
            $result = Auth::login($username, $password);
            if ($result['success']) {
                $redirectUrl = $_GET['redirect'] ?? '/admin/dashboard.php';
                redirect($redirectUrl, 'Selamat datang!', 'success');
            } else {
                $error = $result['message'];
            }
        }
    }
}

// Handle password reset
if (isset($_GET['action']) && $_GET['action'] === 'reset' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email'] ?? '');
    
    if (empty($email)) {
        $error = 'Email harus diisi.';
    } elseif (!isValidEmail($email)) {
        $error = 'Format email tidak valid.';
    } else {
        $result = Auth::resetPassword($email);
        if ($result['success']) {
            $success = 'Password baru telah dikirim ke email Anda. Temporary password: ' . $result['temp_password'];
        } else {
            $error = $result['message'];
        }
    }
}

$isResetMode = isset($_GET['action']) && $_GET['action'] === 'reset';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $isResetMode ? 'Reset Password' : 'Login' ?> - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }

        .login-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.2);
            overflow: hidden;
            width: 100%;
            max-width: 400px;
            animation: slideUp 0.6s ease;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .login-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 3rem 2rem 2rem;
            text-align: center;
            position: relative;
        }

        .login-header::before {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 0;
            height: 0;
            border-left: 20px solid transparent;
            border-right: 20px solid transparent;
            border-top: 20px solid #764ba2;
        }

        .logo {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
        }

        .login-title {
            font-size: 1.5rem;
            font-weight: 300;
            margin-bottom: 0.5rem;
        }

        .login-subtitle {
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .login-form {
            padding: 2rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
            position: relative;
        }

        .form-control {
            width: 100%;
            padding: 1rem 1rem 1rem 3rem;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }

        .form-control:focus {
            outline: none;
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
            font-size: 1.1rem;
        }

        .btn {
            width: 100%;
            padding: 1rem;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
            margin-top: 1rem;
        }

        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }

        .alert {
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
        }

        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .forgot-password {
            text-align: center;
            margin-top: 1.5rem;
        }

        .forgot-password a {
            color: #667eea;
            text-decoration: none;
            font-size: 0.9rem;
            transition: color 0.3s ease;
        }

        .forgot-password a:hover {
            color: #764ba2;
            text-decoration: underline;
        }

        .back-to-login {
            text-align: center;
            margin-bottom: 1rem;
        }

        .back-to-login a {
            color: #6c757d;
            text-decoration: none;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: color 0.3s ease;
        }

        .back-to-login a:hover {
            color: #495057;
        }

        .version-info {
            text-align: center;
            margin-top: 2rem;
            padding-top: 1rem;
            border-top: 1px solid #e9ecef;
            color: #6c757d;
            font-size: 0.8rem;
        }

        @media (max-width: 480px) {
            .login-container {
                margin: 1rem;
            }
            
            .login-header {
                padding: 2rem 1.5rem 1.5rem;
            }
            
            .login-form {
                padding: 1.5rem;
            }
            
            .logo {
                font-size: 2rem;
            }
            
            .login-title {
                font-size: 1.3rem;
            }
        }

        .loading {
            display: none;
            width: 20px;
            height: 20px;
            border: 2px solid #f3f3f3;
            border-top: 2px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <div class="logo">
                <i class="fas fa-cube"></i>
            </div>
            <h1 class="login-title"><?= APP_NAME ?></h1>
            <p class="login-subtitle">
                <?= $isResetMode ? 'Reset Password Anda' : 'Silakan masuk ke akun Anda' ?>
            </p>
        </div>
        
        <div class="login-form">
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>
            
            <?php if ($isResetMode): ?>
                <div class="back-to-login">
                    <a href="/admin/login.php">
                        <i class="fas fa-arrow-left"></i> Kembali ke Login
                    </a>
                </div>
                
                <form method="POST" id="resetForm">
                    <input type="hidden" name="_token" value="<?= generateCSRFToken() ?>">
                    
                    <div class="form-group">
                        <i class="fas fa-envelope form-icon"></i>
                        <input type="email" 
                               name="email" 
                               class="form-control" 
                               placeholder="Alamat Email" 
                               required
                               autocomplete="email">
                    </div>
                    
                    <button type="submit" class="btn btn-primary" id="resetBtn">
                        Reset Password
                    </button>
                </form>
            <?php else: ?>
                <form method="POST" id="loginForm">
                    <input type="hidden" name="_token" value="<?= generateCSRFToken() ?>">
                    
                    <div class="form-group">
                        <i class="fas fa-user form-icon"></i>
                        <input type="text" 
                               name="username" 
                               class="form-control" 
                               placeholder="Username atau Email" 
                               required
                               autocomplete="username"
                               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                    </div>
                    
                    <div class="form-group">
                        <i class="fas fa-lock form-icon"></i>
                        <input type="password" 
                               name="password" 
                               class="form-control" 
                               placeholder="Password" 
                               required
                               autocomplete="current-password">
                    </div>
                    
                    <button type="submit" class="btn btn-primary" id="loginBtn">
                        <span class="btn-text">Masuk</span>
                        <div class="loading"></div>
                    </button>
                </form>
                
                <div class="forgot-password">
                    <a href="/admin/login.php?action=reset">Lupa Password?</a>
                </div>
            <?php endif; ?>
            
            <div class="version-info">
                <?= APP_NAME ?> v<?= APP_VERSION ?><br>
                &copy; <?= date('Y') ?> - Professional Page Builder
            </div>
        </div>
    </div>

    <script>
        // Form submission with loading state
        document.addEventListener('DOMContentLoaded', function() {
            const loginForm = document.getElementById('loginForm');
            const resetForm = document.getElementById('resetForm');
            
            if (loginForm) {
                loginForm.addEventListener('submit', function() {
                    const btn = document.getElementById('loginBtn');
                    const btnText = btn.querySelector('.btn-text');
                    const loading = btn.querySelector('.loading');
                    
                    btn.disabled = true;
                    btnText.style.display = 'none';
                    loading.style.display = 'block';
                });
            }
            
            if (resetForm) {
                resetForm.addEventListener('submit', function() {
                    const btn = document.getElementById('resetBtn');
                    btn.disabled = true;
                    btn.innerHTML = '<div class="loading"></div> Mengirim...';
                });
            }
            
            // Auto-focus first input
            const firstInput = document.querySelector('.form-control');
            if (firstInput) {
                firstInput.focus();
            }
            
            // Enter key navigation
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    const form = e.target.closest('form');
                    if (form) {
                        const inputs = form.querySelectorAll('.form-control');
                        const currentIndex = Array.from(inputs).indexOf(e.target);
                        
                        if (currentIndex < inputs.length - 1) {
                            e.preventDefault();
                            inputs[currentIndex + 1].focus();
                        }
                    }
                }
            });
        });
        
        // Demo credentials hint (remove in production)
        <?php if (DEBUG_MODE): ?>
        console.log('Demo Credentials:');
        console.log('Username: admin');
        console.log('Password: password');
        <?php endif; ?>
    </script>
</body>
</html>
