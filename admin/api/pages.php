<?php
require_once '../../includes/config.php';
require_once '../../includes/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';
require_once '../../classes/Page.php';
require_once '../../classes/Project.php';
require_once '../../classes/Element.php';

// Require login
requireLogin();

// Set JSON header
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? 'save';
        
        switch ($action) {
            case 'save':
            case 'autosave':
                $pageId = $input['page_id'] ?? null;
                $content = $input['content'] ?? [];
                
                if (!$pageId) {
                    errorResponse('Page ID required');
                }
                
                // Load page
                $page = new Page($pageId);
                if (!$page->getData()) {
                    errorResponse('Page not found');
                }
                
                // Check permissions
                $pageData = $page->getData();
                $project = new Project($pageData['project_id']);
                if ($project->user_id != getUserId() && !Auth::isAdmin()) {
                    errorResponse('Access denied', 403);
                }
                
                // Validate content structure
                if (!is_array($content)) {
                    errorResponse('Content must be an array');
                }
                
                // Validate each element in content
                foreach ($content as $element) {
                    $errors = Element::validateElementData($element);
                    if (!empty($errors)) {
                        errorResponse('Invalid element data: ' . implode(', ', $errors));
                    }
                }
                
                // Update page content
                $updateData = [
                    'content' => json_encode($content),
                    'updated_at' => date('Y-m-d H:i:s')
                ];
                
                // Add custom CSS and JS if provided
                if (isset($input['custom_css'])) {
                    $updateData['custom_css'] = $input['custom_css'];
                }
                
                if (isset($input['custom_js'])) {
                    $updateData['custom_js'] = $input['custom_js'];
                }
                
                $updated = $page->update($updateData);
                
                if ($updated) {
                    if ($action === 'autosave') {
                        successResponse([], 'Page auto-saved successfully');
                    } else {
                        successResponse(['page_id' => $pageId], 'Page saved successfully');
                    }
                } else {
                    errorResponse('Failed to save page');
                }
                break;
                
            case 'create':
                requirePermission('create_page');
                
                $required = ['project_id', 'title'];
                foreach ($required as $field) {
                    if (empty($input[$field])) {
                        errorResponse("Field {$field} is required");
                    }
                }
                
                // Check project permissions
                $project = new Project($input['project_id']);
                if (!$project->getData()) {
                    errorResponse('Project not found');
                }
                
                if ($project->user_id != getUserId() && !Auth::isAdmin()) {
                    errorResponse('Access denied', 403);
                }
                
                // Set default content if not provided
                if (empty($input['content'])) {
                    $input['content'] = [];
                }
                
                $pageId = Page::create($input);
                successResponse(['page_id' => $pageId], 'Page created successfully');
                break;
                
            case 'duplicate':
                requirePermission('create_page');
                
                $pageId = $input['page_id'] ?? null;
                $newTitle = $input['new_title'] ?? null;
                
                if (!$pageId) {
                    errorResponse('Page ID required');
                }
                
                $page = new Page($pageId);
                if (!$page->getData()) {
                    errorResponse('Page not found');
                }
                
                // Check permissions
                $pageData = $page->getData();
                $project = new Project($pageData['project_id']);
                if ($project->user_id != getUserId() && !Auth::isAdmin()) {
                    errorResponse('Access denied', 403);
                }
                
                $newPageId = $page->duplicate($newTitle);
                successResponse(['page_id' => $newPageId], 'Page duplicated successfully');
                break;
                
            case 'publish':
                requirePermission('edit_page');
                
                $pageId = $input['page_id'] ?? null;
                $publish = $input['publish'] ?? true;
                
                if (!$pageId) {
                    errorResponse('Page ID required');
                }
                
                $page = new Page($pageId);
                if (!$page->getData()) {
                    errorResponse('Page not found');
                }
                
                // Check permissions
                $pageData = $page->getData();
                $project = new Project($pageData['project_id']);
                if ($project->user_id != getUserId() && !Auth::isAdmin()) {
                    errorResponse('Access denied', 403);
                }
                
                $page->publish($publish);
                $status = $publish ? 'published' : 'unpublished';
                successResponse([], "Page {$status} successfully");
                break;
                
            case 'delete':
                requirePermission('delete_page');
                
                $pageId = $input['page_id'] ?? null;
                
                if (!$pageId) {
                    errorResponse('Page ID required');
                }
                
                $page = new Page($pageId);
                if (!$page->getData()) {
                    errorResponse('Page not found');
                }
                
                // Check permissions
                $pageData = $page->getData();
                $project = new Project($pageData['project_id']);
                if ($project->user_id != getUserId() && !Auth::isAdmin()) {
                    errorResponse('Access denied', 403);
                }
                
                $page->delete();
                successResponse([], 'Page deleted successfully');
                break;
                
            case 'restore_version':
                requirePermission('edit_page');
                
                $pageId = $input['page_id'] ?? null;
                $versionNumber = $input['version_number'] ?? null;
                
                if (!$pageId || !$versionNumber) {
                    errorResponse('Page ID and version number required');
                }
                
                $page = new Page($pageId);
                if (!$page->getData()) {
                    errorResponse('Page not found');
                }
                
                // Check permissions
                $pageData = $page->getData();
                $project = new Project($pageData['project_id']);
                if ($project->user_id != getUserId() && !Auth::isAdmin()) {
                    errorResponse('Access denied', 403);
                }
                
                $page->restoreVersion($versionNumber);
                successResponse([], 'Version restored successfully');
                break;
                
            default:
                errorResponse('Invalid action');
        }
        
    } elseif ($method === 'GET') {
        $action = $_GET['action'] ?? 'get';
        
        switch ($action) {
            case 'get':
                $pageId = $_GET['id'] ?? null;
                
                if (!$pageId) {
                    errorResponse('Page ID required');
                }
                
                $page = new Page($pageId);
                if (!$page->getData()) {
                    errorResponse('Page not found');
                }
                
                // Check permissions
                $pageData = $page->getData();
                $project = new Project($pageData['project_id']);
                if ($project->user_id != getUserId() && !Auth::isAdmin()) {
                    errorResponse('Access denied', 403);
                }
                
                $data = $page->getData();
                $data['content'] = $page->getContent();
                
                successResponse($data, 'Page data retrieved');
                break;
                
            case 'list':
                $projectId = $_GET['project_id'] ?? null;
                $publishedOnly = isset($_GET['published_only']) ? (bool)$_GET['published_only'] : false;
                
                if ($projectId) {
                    // Check project permissions
                    $project = new Project($projectId);
                    if (!$project->getData()) {
                        errorResponse('Project not found');
                    }
                    
                    if ($project->user_id != getUserId() && !Auth::isAdmin()) {
                        errorResponse('Access denied', 403);
                    }
                    
                    $pages = Page::getByProject($projectId, $publishedOnly);
                } else {
                    // Get all pages for user's projects
                    $pages = dbFetchAll(
                        "SELECT p.*, pr.name as project_name 
                         FROM pages p 
                         JOIN projects pr ON p.project_id = pr.id 
                         WHERE pr.user_id = :user_id 
                         " . ($publishedOnly ? "AND p.is_published = 1" : "") . "
                         ORDER BY p.updated_at DESC",
                        ['user_id' => getUserId()]
                    );
                }
                
                successResponse($pages, 'Pages retrieved');
                break;
                
            case 'versions':
                $pageId = $_GET['page_id'] ?? null;
                
                if (!$pageId) {
                    errorResponse('Page ID required');
                }
                
                $page = new Page($pageId);
                if (!$page->getData()) {
                    errorResponse('Page not found');
                }
                
                // Check permissions
                $pageData = $page->getData();
                $project = new Project($pageData['project_id']);
                if ($project->user_id != getUserId() && !Auth::isAdmin()) {
                    errorResponse('Access denied', 403);
                }
                
                $versions = $page->getVersions();
                successResponse($versions, 'Page versions retrieved');
                break;
                
            case 'search':
                $query = $_GET['q'] ?? '';
                $projectId = $_GET['project_id'] ?? null;
                
                if (empty($query)) {
                    errorResponse('Search query required');
                }
                
                $pages = Page::search($query, $projectId);
                
                // Filter by user permissions
                $filteredPages = [];
                foreach ($pages as $page) {
                    $project = new Project($page['project_id']);
                    if ($project->user_id == getUserId() || Auth::isAdmin()) {
                        $filteredPages[] = $page;
                    }
                }
                
                successResponse($filteredPages, 'Search results retrieved');
                break;
                
            default:
                errorResponse('Invalid action');
        }
        
    } else {
        errorResponse('Method not allowed', 405);
    }
    
} catch (Exception $e) {
    if (DEBUG_MODE) {
        errorResponse($e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine(), 500);
    } else {
        errorResponse('Internal server error', 500);
    }
}
?>
