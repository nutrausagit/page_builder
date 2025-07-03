<?php
require_once 'includes/config.php';
require_once 'includes/database.php';
require_once 'includes/functions.php';
require_once 'classes/Project.php';
require_once 'classes/Page.php';
require_once 'classes/Element.php';

// Get domain from request
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';

// Remove query string
$path = parse_url($requestUri, PHP_URL_PATH);
$path = trim($path, '/');

// If path is empty, show homepage
if (empty($path)) {
    $path = 'home';
}

// Try to find project by domain
$project = Project::getByDomain($host);

// If no project found for this domain, show default project or error
if (!$project) {
    // Get first active project as fallback
    $projects = dbFetchAll("SELECT * FROM projects WHERE is_active = 1 ORDER BY created_at ASC LIMIT 1");
    if (!empty($projects)) {
        $project = $projects[0];
    } else {
        // No projects found, redirect to admin
        redirect('/admin/login.php', 'Tidak ada project yang tersedia', 'info');
    }
}

// Find page by slug
if ($path === 'home' || $path === '') {
    // Get homepage
    $page = Page::getHomepage($project['id']);
    if (!$page) {
        // Get first published page as fallback
        $pages = Page::getByProject($project['id'], true);
        $page = !empty($pages) ? $pages[0] : null;
    }
} else {
    $page = Page::getBySlug($project['id'], $path);
}

// If page not found, show 404
if (!$page) {
    http_response_code(404);
    include 'templates/404.php';
    exit;
}

// Check if preview mode
$isPreview = isset($_GET['preview']) && $_GET['preview'] == '1';

// If not preview mode and page is not published, show 404
if (!$isPreview && !$page['is_published']) {
    http_response_code(404);
    include 'templates/404.php';
    exit;
}

// Parse page content
$content = parseJSON($page['content']);

// Generate page HTML
$pageHtml = '';
foreach ($content as $element) {
    $pageHtml .= Element::renderElement($element);
}

// Set meta tags
$metaTitle = $page['meta_title'] ?: $page['title'];
$metaDescription = $page['meta_description'] ?: '';
$metaKeywords = $page['meta_keywords'] ?: '';

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($metaTitle) ?></title>
    
    <?php if ($metaDescription): ?>
    <meta name="description" content="<?= htmlspecialchars($metaDescription) ?>">
    <?php endif; ?>
    
    <?php if ($metaKeywords): ?>
    <meta name="keywords" content="<?= htmlspecialchars($metaKeywords) ?>">
    <?php endif; ?>
    
    <!-- Open Graph meta tags -->
    <meta property="og:title" content="<?= htmlspecialchars($metaTitle) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($metaDescription) ?>">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= getCurrentURL() ?>">
    
    <!-- Default styles -->
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #fff;
        }
        
        img {
            max-width: 100%;
            height: auto;
        }
        
        a {
            color: #007bff;
            text-decoration: none;
        }
        
        a:hover {
            text-decoration: underline;
        }
        
        .btn {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            background-color: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 0.375rem;
            border: none;
            cursor: pointer;
            font-size: 1rem;
            transition: background-color 0.2s ease;
        }
        
        .btn:hover {
            background-color: #0056b3;
            text-decoration: none;
        }
        
        /* Container styles */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1rem;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .container {
                padding: 0 0.5rem;
            }
        }
        
        /* Preview mode indicator */
        .preview-bar {
            background: #ffc107;
            color: #000;
            padding: 0.5rem;
            text-align: center;
            font-weight: bold;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 9999;
        }
        
        .preview-mode {
            margin-top: 40px;
        }
    </style>
    
    <!-- Custom CSS from page -->
    <?php if (!empty($page['custom_css'])): ?>
    <style>
        <?= $page['custom_css'] ?>
    </style>
    <?php endif; ?>
</head>
<body<?= $isPreview ? ' class="preview-mode"' : '' ?>>
    <?php if ($isPreview): ?>
    <div class="preview-bar">
        <i class="fas fa-eye"></i> PREVIEW MODE - This page is not published
        <a href="/admin/pages/edit.php?id=<?= $page['id'] ?>" style="margin-left: 1rem; color: #000;">
            Back to Editor
        </a>
    </div>
    <?php endif; ?>
    
    <!-- Page Content -->
    <?= $pageHtml ?>
    
    <!-- Custom JavaScript from page -->
    <?php if (!empty($page['custom_js'])): ?>
    <script>
        <?= $page['custom_js'] ?>
    </script>
    <?php endif; ?>
    
    <!-- Analytics and tracking scripts can go here -->
    
    <?php if ($isPreview): ?>
    <!-- Preview mode scripts -->
    <script>
        // Add visual indicators for preview mode
        document.addEventListener('DOMContentLoaded', function() {
            const style = document.createElement('style');
            style.textContent = `
                body::after {
                    content: 'PREVIEW';
                    position: fixed;
                    bottom: 20px;
                    right: 20px;
                    background: rgba(255, 193, 7, 0.9);
                    color: #000;
                    padding: 0.5rem 1rem;
                    border-radius: 4px;
                    font-weight: bold;
                    z-index: 9998;
                    font-size: 0.8rem;
                }
            `;
            document.head.appendChild(style);
        });
    </script>
    <?php endif; ?>
</body>
</html>
