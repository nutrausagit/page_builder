<?php
/**
 * =====================================================
 * PAGE BUILDER INSTALLER
 * =====================================================
 * This script will install the database schema and create
 * default admin user for the page builder application.
 */

// Check if already installed
if (file_exists('config.lock')) {
    die('Application is already installed. Delete config.lock file to reinstall.');
}

require_once 'includes/config.php';

$step = $_GET['step'] ?? 1;
$errors = [];
$success = [];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($step) {
        case 1:
            // Database connection test
            $dbHost = $_POST['db_host'] ?? 'localhost';
            $dbName = $_POST['db_name'] ?? 'page_builder';
            $dbUser = $_POST['db_user'] ?? 'root';
            $dbPass = $_POST['db_pass'] ?? '';
            
            try {
                $dsn = "mysql:host={$dbHost};charset=utf8mb4";
                $pdo = new PDO($dsn, $dbUser, $dbPass);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                
                // Try to create database if it doesn't exist
                $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                
                $success[] = 'Database connection successful!';
                $step = 2;
                
                // Store database config in session
                session_start();
                $_SESSION['install_config'] = [
                    'db_host' => $dbHost,
                    'db_name' => $dbName,
                    'db_user' => $dbUser,
                    'db_pass' => $dbPass
                ];
                
            } catch (PDOException $e) {
                $errors[] = 'Database connection failed: ' . $e->getMessage();
            }
            break;
            
        case 2:
            // Install database schema
            session_start();
            $config = $_SESSION['install_config'] ?? null;
            
            if (!$config) {
                $errors[] = 'Database configuration not found. Please restart installation.';
                $step = 1;
                break;
            }
            
            try {
                $dsn = "mysql:host={$config['db_host']};dbname={$config['db_name']};charset=utf8mb4";
                $pdo = new PDO($dsn, $config['db_user'], $config['db_pass']);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                
                // Execute SQL schema file
                runSqlFile($pdo, 'page_builder_schema.sql');
                
                $success[] = 'Database schema installed successfully!';
                $step = 3;
                
            } catch (PDOException $e) {
                $errors[] = 'Schema installation failed: ' . $e->getMessage();
            }
            break;
            
        case 3:
            // Create admin user
            session_start();
            $config = $_SESSION['install_config'] ?? null;
            
            if (!$config) {
                $errors[] = 'Database configuration not found. Please restart installation.';
                $step = 1;
                break;
            }
            
            $username = $_POST['admin_username'] ?? '';
            $email = $_POST['admin_email'] ?? '';
            $password = $_POST['admin_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';
            
            // Validation
            if (empty($username) || empty($email) || empty($password)) {
                $errors[] = 'All fields are required.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Invalid email format.';
            } elseif (strlen($password) < 6) {
                $errors[] = 'Password must be at least 6 characters.';
            } elseif ($password !== $confirmPassword) {
                $errors[] = 'Passwords do not match.';
            } else {
                try {
                    $dsn = "mysql:host={$config['db_host']};dbname={$config['db_name']};charset=utf8mb4";
                    $pdo = new PDO($dsn, $config['db_user'], $config['db_pass']);
                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    
                    // Delete default admin user
                    $pdo->exec("DELETE FROM users WHERE username = 'admin'");
                    
                    // Create new admin user
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, 'admin')");
                    $stmt->execute([$username, $email, $hashedPassword]);
                    
                    $success[] = 'Admin user created successfully!';
                    $step = 4;
                    
                } catch (PDOException $e) {
                    $errors[] = 'Failed to create admin user: ' . $e->getMessage();
                }
            }
            break;
            
        case 4:
            // Finalize installation
            session_start();
            $config = $_SESSION['install_config'] ?? null;
            
            if ($config) {
                // Update config.php file with database settings
                updateConfigFile($config);
                
                // Create lock file
                file_put_contents('config.lock', date('Y-m-d H:i:s'));
                
                // Clear session
                unset($_SESSION['install_config']);
                
                $success[] = 'Installation completed successfully!';
                $step = 5;
            }
            break;
    }
}

function updateConfigFile($config) {
    $configFile = 'includes/config.php';
    $content = file_get_contents($configFile);
    
    $content = str_replace("define('DB_HOST', 'localhost');", "define('DB_HOST', '{$config['db_host']}');", $content);
    $content = str_replace("define('DB_NAME', 'page_builder');", "define('DB_NAME', '{$config['db_name']}');", $content);
    $content = str_replace("define('DB_USER', 'root');", "define('DB_USER', '{$config['db_user']}');", $content);
    $content = str_replace("define('DB_PASS', '');", "define('DB_PASS', '{$config['db_pass']}');", $content);
    
    file_put_contents($configFile, $content);
}

function runSqlFile(PDO $pdo, $file) {
    $sql = file_get_contents($file);

    // Remove database creation commands
    $sql = preg_replace('/CREATE DATABASE.*?;/is', '', $sql);
    $sql = preg_replace('/USE\s+page_builder;/i', '', $sql);

    $delimiter = ';';
    $statement = '';
    foreach (preg_split("/(\r?\n)/", $sql) as $line) {
        if (preg_match('/^\s*--/', $line) || preg_match('/^\s*#/', $line)) {
            continue;
        }

        if (preg_match('/^\s*DELIMITER\s+(.+)$/i', $line, $m)) {
            $delimiter = $m[1];
            continue;
        }

        $statement .= $line . "\n";
        if (substr(trim($line), -strlen($delimiter)) === $delimiter) {
            $exec = substr($statement, 0, -strlen($delimiter));
            if (trim($exec) !== '') {
                $pdo->exec($exec);
            }
            $statement = '';
        }
    }
}

function checkRequirements() {
    $requirements = [
        'PHP Version >= 7.4' => version_compare(PHP_VERSION, '7.4.0', '>='),
        'PDO Extension' => extension_loaded('pdo'),
        'PDO MySQL Extension' => extension_loaded('pdo_mysql'),
        'JSON Extension' => extension_loaded('json'),
        'GD Extension' => extension_loaded('gd'),
        'File Upload Enabled' => ini_get('file_uploads'),
        'Directory Writable' => is_writable('.'),
        'Uploads Directory Writable' => is_writable('assets/uploads') || mkdir('assets/uploads', 0755, true)
    ];
    
    return $requirements;
}

$requirements = checkRequirements();
$allRequirementsMet = !in_array(false, $requirements);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Install <?= APP_NAME ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 2rem;
        }
        
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        
        .header h1 {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        
        .step-indicator {
            display: flex;
            justify-content: center;
            margin-top: 1rem;
        }
        
        .step {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: rgba(255,255,255,0.3);
            margin: 0 0.25rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        
        .step.active {
            background: white;
            color: #667eea;
        }
        
        .step.completed {
            background: #28a745;
        }
        
        .content {
            padding: 2rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e9ecef;
            border-radius: 6px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .btn {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            text-decoration: none;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 500;
            transition: transform 0.2s ease;
        }
        
        .btn:hover {
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background: #6c757d;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1rem;
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
        
        .requirements {
            list-style: none;
        }
        
        .requirement {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem 0;
            border-bottom: 1px solid #e9ecef;
        }
        
        .requirement:last-child {
            border-bottom: none;
        }
        
        .status {
            font-weight: bold;
        }
        
        .status.ok {
            color: #28a745;
        }
        
        .status.error {
            color: #dc3545;
        }
        
        .footer {
            padding: 1rem 2rem;
            background: #f8f9fa;
            border-top: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .text-center {
            text-align: center;
        }
        
        .mb-0 {
            margin-bottom: 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><?= APP_NAME ?> Installer</h1>
            <p>Selamat datang! Mari setup aplikasi page builder Anda</p>
            
            <div class="step-indicator">
                <div class="step <?= $step >= 1 ? ($step == 1 ? 'active' : 'completed') : '' ?>">1</div>
                <div class="step <?= $step >= 2 ? ($step == 2 ? 'active' : 'completed') : '' ?>">2</div>
                <div class="step <?= $step >= 3 ? ($step == 3 ? 'active' : 'completed') : '' ?>">3</div>
                <div class="step <?= $step >= 4 ? ($step == 4 ? 'active' : 'completed') : '' ?>">4</div>
                <div class="step <?= $step >= 5 ? 'active' : '' ?>">5</div>
            </div>
        </div>
        
        <div class="content">
            <?php foreach ($errors as $error): ?>
                <div class="alert alert-danger">
                    <strong>Error:</strong> <?= htmlspecialchars($error) ?>
                </div>
            <?php endforeach; ?>
            
            <?php foreach ($success as $message): ?>
                <div class="alert alert-success">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endforeach; ?>
            
            <?php if ($step == 1): ?>
                <h2>Step 1: System Requirements</h2>
                
                <div class="requirements">
                    <?php foreach ($requirements as $requirement => $status): ?>
                        <div class="requirement">
                            <span><?= $requirement ?></span>
                            <span class="status <?= $status ? 'ok' : 'error' ?>">
                                <?= $status ? '✓ OK' : '✗ Failed' ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <?php if ($allRequirementsMet): ?>
                    <h3>Database Configuration</h3>
                    <form method="POST">
                        <div class="form-group">
                            <label class="form-label">Database Host</label>
                            <input type="text" name="db_host" class="form-control" value="<?= isset($_POST['db_host']) ? htmlspecialchars($_POST['db_host']) : '' ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Database Name</label>
                            <input type="text" name="db_name" class="form-control" value="<?= isset($_POST['db_name']) ? htmlspecialchars($_POST['db_name']) : '' ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Database Username</label>
                            <input type="text" name="db_user" class="form-control" value="<?= isset($_POST['db_user']) ? htmlspecialchars($_POST['db_user']) : '' ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Database Password</label>
                            <input type="password" name="db_pass" class="form-control" value="<?= isset($_POST['db_pass']) ? htmlspecialchars($_POST['db_pass']) : '' ?>">
                        </div>
                        
                        <button type="submit" class="btn">Test Connection & Continue</button>
                    </form>
                <?php else: ?>
                    <div class="alert alert-danger">
                        <strong>Requirements Not Met!</strong><br>
                        Please fix the failed requirements before continuing.
                    </div>
                <?php endif; ?>
                
            <?php elseif ($step == 2): ?>
                <h2>Step 2: Install Database Schema</h2>
                <p>Database connection successful! Now we'll install the database schema.</p>
                
                <form method="POST">
                    <button type="submit" class="btn">Install Database Schema</button>
                </form>
                
            <?php elseif ($step == 3): ?>
                <h2>Step 3: Create Admin User</h2>
                <p>Create your administrator account:</p>
                
                <form method="POST">
                    <div class="form-group">
                        <label class="form-label">Username</label>
                        <input type="text" name="admin_username" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" name="admin_email" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Password</label>
                        <input type="password" name="admin_password" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Confirm Password</label>
                        <input type="password" name="confirm_password" class="form-control" required>
                    </div>
                    
                    <button type="submit" class="btn">Create Admin User</button>
                </form>
                
            <?php elseif ($step == 4): ?>
                <h2>Step 4: Finalize Installation</h2>
                <p>Almost done! Click the button below to complete the installation.</p>
                
                <form method="POST">
                    <button type="submit" class="btn">Complete Installation</button>
                </form>
                
            <?php elseif ($step == 5): ?>
                <div class="text-center">
                    <h2>🎉 Installation Complete!</h2>
                    <p class="mb-0">Your page builder is now ready to use.</p>
                </div>
                
            <?php endif; ?>
        </div>
        
        <?php if ($step == 5): ?>
        <div class="footer">
            <div>
                <strong>Next Steps:</strong>
                <ul style="margin: 0.5rem 0 0 1rem;">
                    <li>Delete install.php file for security</li>
                    <li>Configure your web server</li>
                    <li>Start building pages!</li>
                </ul>
            </div>
            <div>
                <a href="/admin/login.php" class="btn">Go to Admin Panel</a>
            </div>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
