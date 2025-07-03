<?php
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../classes/Project.php';
require_once '../classes/Page.php';
require_once '../classes/Asset.php';

// Require login
requireLogin();

// Get current user
$user = getCurrentUser();

// Get statistics
$stats = [
    'projects' => dbCount('projects', 'user_id = :user_id', ['user_id' => getUserId()]),
    'pages' => dbCount('pages', 'project_id IN (SELECT id FROM projects WHERE user_id = :user_id)', ['user_id' => getUserId()]),
    'assets' => dbCount('assets', 'user_id = :user_id', ['user_id' => getUserId()]),
    'templates' => dbCount('templates', 'user_id = :user_id OR is_public = 1', ['user_id' => getUserId()])
];

// Get recent projects
$recentProjects = Project::getByUser(getUserId());
$recentProjects = array_slice($recentProjects, 0, 5);

// Get recent pages
$recentPages = dbFetchAll(
    "SELECT p.*, pr.name as project_name 
     FROM pages p 
     JOIN projects pr ON p.project_id = pr.id 
     WHERE pr.user_id = :user_id 
     ORDER BY p.updated_at DESC 
     LIMIT 5",
    ['user_id' => getUserId()]
);

// Get recent assets
$recentAssets = Asset::getAll(null);
$recentAssets = array_filter($recentAssets, function($asset) {
    return $asset['user_id'] == getUserId();
});
$recentAssets = array_slice($recentAssets, 0, 5);

// Flash message
$flash = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body>
    <div class="admin-wrapper">
        <!-- Sidebar -->
        <nav class="sidebar">
            <div class="sidebar-header">
                <a href="/admin/dashboard.php" class="logo">
                    <i class="fas fa-cube"></i>
                    <?= APP_NAME ?>
                </a>
            </div>
            
            <div class="sidebar-nav">
                <div class="nav-item">
                    <a href="/admin/dashboard.php" class="nav-link active">
                        <i class="fas fa-tachometer-alt"></i>
                        Dashboard
                    </a>
                </div>
                <div class="nav-item">
                    <a href="/admin/projects.php" class="nav-link">
                        <i class="fas fa-folder"></i>
                        Projects
                    </a>
                </div>
                <div class="nav-item">
                    <a href="/admin/pages/" class="nav-link">
                        <i class="fas fa-file-alt"></i>
                        Pages
                    </a>
                </div>
                <div class="nav-item">
                    <a href="/admin/templates/" class="nav-link">
                        <i class="fas fa-layer-group"></i>
                        Templates
                    </a>
                </div>
                <div class="nav-item">
                    <a href="/admin/assets/" class="nav-link">
                        <i class="fas fa-images"></i>
                        Assets
                    </a>
                </div>
                <?php if (Auth::isAdmin()): ?>
                <div class="nav-item">
                    <a href="/admin/users.php" class="nav-link">
                        <i class="fas fa-users"></i>
                        Users
                    </a>
                </div>
                <div class="nav-item">
                    <a href="/admin/settings.php" class="nav-link">
                        <i class="fas fa-cog"></i>
                        Settings
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </nav>
        
        <!-- Main Content -->
        <main class="main-content">
            <!-- Top Bar -->
            <div class="topbar">
                <h1 class="page-title">Dashboard</h1>
                
                <div class="user-menu">
                    <span>Selamat datang, <?= htmlspecialchars($user['username']) ?></span>
                    <div class="user-avatar">
                        <?= strtoupper(substr($user['username'], 0, 1)) ?>
                    </div>
                    <a href="/admin/logout.php" class="btn btn-secondary btn-sm">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
            
            <!-- Content Area -->
            <div class="content-area">
                <?php if ($flash): ?>
                    <div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : $flash['type'] ?>">
                        <?= htmlspecialchars($flash['message']) ?>
                    </div>
                <?php endif; ?>
                
                <!-- Statistics Cards -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon primary">
                            <i class="fas fa-folder"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?= $stats['projects'] ?></h3>
                            <p>Projects</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon success">
                            <i class="fas fa-file-alt"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?= $stats['pages'] ?></h3>
                            <p>Pages</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon info">
                            <i class="fas fa-images"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?= $stats['assets'] ?></h3>
                            <p>Assets</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon warning">
                            <i class="fas fa-layer-group"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?= $stats['templates'] ?></h3>
                            <p>Templates</p>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Quick Actions</h2>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-3">
                                <a href="/admin/projects.php?action=create" class="btn btn-primary w-100">
                                    <i class="fas fa-plus"></i><br>
                                    New Project
                                </a>
                            </div>
                            <div class="col-3">
                                <a href="/admin/pages/?action=create" class="btn btn-success w-100">
                                    <i class="fas fa-file-plus"></i><br>
                                    New Page
                                </a>
                            </div>
                            <div class="col-3">
                                <a href="/admin/templates/?action=create" class="btn btn-info w-100">
                                    <i class="fas fa-layer-group"></i><br>
                                    New Template
                                </a>
                            </div>
                            <div class="col-3">
                                <a href="/admin/assets/?action=upload" class="btn btn-warning w-100">
                                    <i class="fas fa-upload"></i><br>
                                    Upload Asset
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Items -->
                <div class="row">
                    <!-- Recent Projects -->
                    <div class="col-4">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Recent Projects</h3>
                                <a href="/admin/projects.php" class="btn btn-sm btn-secondary">View All</a>
                            </div>
                            <div class="card-body">
                                <?php if (empty($recentProjects)): ?>
                                    <p class="text-center">Belum ada project.</p>
                                    <div class="text-center">
                                        <a href="/admin/projects.php?action=create" class="btn btn-primary">Create Project</a>
                                    </div>
                                <?php else: ?>
                                    <div class="list-group">
                                        <?php foreach ($recentProjects as $project): ?>
                                            <div class="list-item">
                                                <div class="list-content">
                                                    <h5><?= htmlspecialchars($project['name']) ?></h5>
                                                    <p><?= htmlspecialchars($project['description']) ?></p>
                                                    <small><?= timeAgo($project['updated_at']) ?></small>
                                                </div>
                                                <div class="list-actions">
                                                    <a href="/admin/projects.php?id=<?= $project['id'] ?>" class="btn btn-sm btn-primary">View</a>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Recent Pages -->
                    <div class="col-4">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Recent Pages</h3>
                                <a href="/admin/pages/" class="btn btn-sm btn-secondary">View All</a>
                            </div>
                            <div class="card-body">
                                <?php if (empty($recentPages)): ?>
                                    <p class="text-center">Belum ada halaman.</p>
                                    <div class="text-center">
                                        <a href="/admin/pages/?action=create" class="btn btn-primary">Create Page</a>
                                    </div>
                                <?php else: ?>
                                    <div class="list-group">
                                        <?php foreach ($recentPages as $page): ?>
                                            <div class="list-item">
                                                <div class="list-content">
                                                    <h5><?= htmlspecialchars($page['title']) ?></h5>
                                                    <p><?= htmlspecialchars($page['project_name']) ?></p>
                                                    <small>
                                                        <?= timeAgo($page['updated_at']) ?>
                                                        <?php if ($page['is_published']): ?>
                                                            <span class="badge badge-success">Published</span>
                                                        <?php else: ?>
                                                            <span class="badge badge-warning">Draft</span>
                                                        <?php endif; ?>
                                                    </small>
                                                </div>
                                                <div class="list-actions">
                                                    <a href="/admin/pages/edit.php?id=<?= $page['id'] ?>" class="btn btn-sm btn-primary">Edit</a>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Recent Assets -->
                    <div class="col-4">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Recent Assets</h3>
                                <a href="/admin/assets/" class="btn btn-sm btn-secondary">View All</a>
                            </div>
                            <div class="card-body">
                                <?php if (empty($recentAssets)): ?>
                                    <p class="text-center">Belum ada asset.</p>
                                    <div class="text-center">
                                        <a href="/admin/assets/?action=upload" class="btn btn-primary">Upload Asset</a>
                                    </div>
                                <?php else: ?>
                                    <div class="list-group">
                                        <?php foreach ($recentAssets as $asset): ?>
                                            <div class="list-item">
                                                <div class="list-content">
                                                    <h5><?= htmlspecialchars($asset['original_filename']) ?></h5>
                                                    <p><?= formatFileSize($asset['file_size']) ?> • <?= strtoupper($asset['file_type']) ?></p>
                                                    <small><?= timeAgo($asset['created_at']) ?></small>
                                                </div>
                                                <div class="list-actions">
                                                    <?php if (in_array($asset['file_type'], ['jpg', 'jpeg', 'png', 'gif', 'webp'])): ?>
                                                        <img src="<?= htmlspecialchars($asset['file_path']) ?>" alt="" style="width: 40px; height: 40px; object-fit: cover; border-radius: 4px;">
                                                    <?php else: ?>
                                                        <i class="fas fa-file text-muted" style="font-size: 2rem;"></i>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- System Info (Admin Only) -->
                <?php if (Auth::isAdmin()): ?>
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">System Information</h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-3">
                                <strong>PHP Version:</strong><br>
                                <?= PHP_VERSION ?>
                            </div>
                            <div class="col-3">
                                <strong>Memory Usage:</strong><br>
                                <?= formatFileSize(memory_get_usage(true)) ?>
                            </div>
                            <div class="col-3">
                                <strong>App Version:</strong><br>
                                <?= APP_VERSION ?>
                            </div>
                            <div class="col-3">
                                <strong>Database:</strong><br>
                                MySQL/MariaDB
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
    
    <style>
        .list-group {
            list-style: none;
            padding: 0;
        }
        
        .list-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 0;
            border-bottom: 1px solid #e9ecef;
        }
        
        .list-item:last-child {
            border-bottom: none;
        }
        
        .list-content h5 {
            margin: 0 0 0.25rem 0;
            font-size: 1rem;
            font-weight: 600;
        }
        
        .list-content p {
            margin: 0 0 0.25rem 0;
            color: #6c757d;
            font-size: 0.875rem;
        }
        
        .list-content small {
            color: #6c757d;
            font-size: 0.8rem;
        }
        
        .badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
            border-radius: 0.375rem;
            margin-left: 0.5rem;
        }
        
        .badge-success {
            background-color: #28a745;
            color: white;
        }
        
        .badge-warning {
            background-color: #ffc107;
            color: #212529;
        }
    </style>
    
    <script>
        // Auto-refresh statistics every 30 seconds
        setInterval(function() {
            fetch('/admin/api/stats.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update stat cards
                        const stats = data.stats;
                        document.querySelector('.stat-card:nth-child(1) h3').textContent = stats.projects;
                        document.querySelector('.stat-card:nth-child(2) h3').textContent = stats.pages;
                        document.querySelector('.stat-card:nth-child(3) h3').textContent = stats.assets;
                        document.querySelector('.stat-card:nth-child(4) h3').textContent = stats.templates;
                    }
                })
                .catch(error => console.error('Error updating stats:', error));
        }, 30000);
    </script>
</body>
</html>
