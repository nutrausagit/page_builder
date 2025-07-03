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

// Get page ID
$pageId = $_GET['id'] ?? null;
if (!$pageId) {
    redirect('/admin/pages/', 'Page ID tidak ditemukan', 'error');
}

// Load page
$page = new Page($pageId);
if (!$page->getData()) {
    redirect('/admin/pages/', 'Page tidak ditemukan', 'error');
}

// Check permissions
$pageData = $page->getData();
$project = new Project($pageData['project_id']);
if ($project->user_id != getUserId() && !Auth::isAdmin()) {
    redirect('/admin/pages/', 'Akses ditolak', 'error');
}

// Get element types
$elementTypes = Element::getAll();

// Get page content
$content = $page->getContent();

// Flash message
$flash = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Page: <?= htmlspecialchars($pageData['title']) ?> - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/admin.css">
    <style>
        .editor-wrapper {
            height: 100vh;
            overflow: hidden;
        }
        
        .editor-topbar {
            background: white;
            padding: 1rem 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #e9ecef;
        }
        
        .page-info h1 {
            font-size: 1.5rem;
            margin: 0 0 0.25rem 0;
            color: #2c3e50;
        }
        
        .page-info p {
            margin: 0;
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .editor-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
        }
        
        .save-status {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #6c757d;
            font-size: 0.875rem;
        }
        
        .save-status.saving {
            color: #ffc107;
        }
        
        .save-status.saved {
            color: #28a745;
        }
        
        .editor-container {
            height: calc(100vh - 80px);
        }
        
        .element-library {
            background: #f8f9fa;
            border-right: 1px solid #e9ecef;
        }
        
        .properties-panel {
            background: #f8f9fa;
            border-left: 1px solid #e9ecef;
        }
        
        .canvas-area {
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        
        .canvas-toolbar {
            background: white;
            border-bottom: 1px solid #e9ecef;
            padding: 0.75rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .toolbar-group {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }
        
        .canvas {
            flex: 1;
            overflow-y: auto;
            background: #f0f0f0;
            background-image: 
                linear-gradient(rgba(0,0,0,.1) 1px, transparent 1px),
                linear-gradient(90deg, rgba(0,0,0,.1) 1px, transparent 1px);
            background-size: 20px 20px;
            padding: 2rem;
        }
        
        .canvas-content {
            background: white;
            min-height: 600px;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            position: relative;
            padding: 2rem;
        }
        
        .canvas-content.empty::before {
            content: 'Drag elements from the library to start building your page';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: #6c757d;
            font-size: 1.1rem;
            text-align: center;
            pointer-events: none;
        }
        
        /* Element Styles */
        .page-element {
            position: relative;
            min-height: 20px;
            margin: 0.5rem 0;
            transition: all 0.2s ease;
        }
        
        .page-element:hover {
            outline: 2px solid #28a745;
            outline-offset: 2px;
        }
        
        .page-element.selected {
            outline: 2px solid #007bff;
            outline-offset: 2px;
        }
        
        .element-container,
        .element-row,
        .element-column {
            min-height: 60px;
            border: 2px dashed transparent;
            border-radius: 4px;
            transition: all 0.2s ease;
        }
        
        .element-container.empty,
        .element-row.empty,
        .element-column.empty {
            border-color: #dee2e6;
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .element-container.empty::before,
        .element-row.empty::before,
        .element-column.empty::before {
            content: 'Drop elements here';
        }
        
        .drag-over {
            border-color: #007bff !important;
            background: rgba(0, 123, 255, 0.1) !important;
        }
        
        /* Responsive adjustments */
        @media (max-width: 1200px) {
            .element-library,
            .properties-panel {
                width: 250px;
            }
        }
        
        @media (max-width: 992px) {
            .editor-container {
                flex-direction: column;
            }
            
            .element-library,
            .properties-panel {
                width: 100%;
                height: 200px;
                overflow-y: auto;
            }
        }
    </style>
</head>
<body>
    <div class="editor-wrapper">
        <!-- Top Bar -->
        <div class="editor-topbar">
            <div class="page-info">
                <h1><?= htmlspecialchars($pageData['title']) ?></h1>
                <p><?= htmlspecialchars($project->name) ?> • <?= $pageData['is_published'] ? 'Published' : 'Draft' ?></p>
            </div>
            
            <div class="editor-actions">
                <div class="save-status" id="saveStatus">
                    <i class="fas fa-check-circle"></i>
                    <span>Saved</span>
                </div>
                
                <button type="button" class="btn btn-secondary" data-action="undo" disabled>
                    <i class="fas fa-undo"></i> Undo
                </button>
                
                <button type="button" class="btn btn-secondary" data-action="redo" disabled>
                    <i class="fas fa-redo"></i> Redo
                </button>
                
                <button type="button" class="btn btn-info" data-action="preview">
                    <i class="fas fa-eye"></i> Preview
                </button>
                
                <button type="button" class="btn btn-success" data-action="save">
                    <i class="fas fa-save"></i> Save
                </button>
                
                <a href="/admin/pages/" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>
        
        <!-- Editor Container -->
        <div class="editor-container">
            <!-- Element Library -->
            <div class="element-library">
                <div class="panel-header">
                    <i class="fas fa-cubes"></i> Element Library
                </div>
                <div class="panel-content">
                    <?php
                    $categories = [];
                    foreach ($elementTypes as $element) {
                        $categories[$element['category']][] = $element;
                    }
                    
                    foreach ($categories as $categoryName => $elements):
                    ?>
                        <div class="element-category">
                            <div class="category-header">
                                <?= ucfirst($categoryName) ?>
                            </div>
                            <div class="element-list">
                                <?php foreach ($elements as $element): ?>
                                    <div class="element-item" 
                                         draggable="true" 
                                         data-element-type="<?= htmlspecialchars($element['name']) ?>">
                                        <div class="element-icon">
                                            <i class="fas <?= htmlspecialchars($element['icon'] ?? 'fa-square') ?>"></i>
                                        </div>
                                        <div class="element-info">
                                            <div class="element-name"><?= htmlspecialchars($element['display_name']) ?></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Canvas Area -->
            <div class="canvas-area">
                <div class="canvas-toolbar">
                    <div class="toolbar-group">
                        <select class="form-control" id="deviceMode">
                            <option value="desktop">Desktop</option>
                            <option value="tablet">Tablet</option>
                            <option value="mobile">Mobile</option>
                        </select>
                        
                        <span class="toolbar-separator">|</span>
                        
                        <button type="button" class="btn btn-sm btn-secondary" id="zoomOut">
                            <i class="fas fa-search-minus"></i>
                        </button>
                        <span id="zoomLevel">100%</span>
                        <button type="button" class="btn btn-sm btn-secondary" id="zoomIn">
                            <i class="fas fa-search-plus"></i>
                        </button>
                    </div>
                    
                    <div class="toolbar-group">
                        <button type="button" class="btn btn-sm btn-secondary" data-action="clear">
                            <i class="fas fa-trash"></i> Clear All
                        </button>
                    </div>
                </div>
                
                <div class="canvas">
                    <div class="canvas-content <?= empty($content) ? 'empty' : '' ?>" id="pageCanvas">
                        <?php if (!empty($content)): ?>
                            <?php foreach ($content as $element): ?>
                                <?= Element::renderElement($element) ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Properties Panel -->
            <div class="properties-panel">
                <div class="panel-header">
                    <i class="fas fa-cog"></i> Properties
                </div>
                <div class="panel-content">
                    <p class="text-center">Select an element to edit properties</p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Hidden form for page data -->
    <form id="pageForm" style="display: none;">
        <input type="hidden" name="page_id" value="<?= $pageId ?>">
        <input type="hidden" name="title" value="<?= htmlspecialchars($pageData['title']) ?>">
        <input type="hidden" name="project_id" value="<?= $pageData['project_id'] ?>">
    </form>
    
    <script src="/assets/js/admin.js"></script>
    <script>
        // Page-specific configurations
        window.pageConfig = {
            pageId: <?= $pageId ?>,
            projectId: <?= $pageData['project_id'] ?>,
            initialContent: <?= json_encode($content) ?>,
            elementTypes: <?= json_encode($elementTypes) ?>
        };
        
        // Device mode switching
        document.getElementById('deviceMode').addEventListener('change', function() {
            const canvas = document.querySelector('.canvas-content');
            const mode = this.value;
            
            canvas.classList.remove('device-desktop', 'device-tablet', 'device-mobile');
            canvas.classList.add('device-' + mode);
            
            switch (mode) {
                case 'tablet':
                    canvas.style.maxWidth = '768px';
                    canvas.style.margin = '0 auto';
                    break;
                case 'mobile':
                    canvas.style.maxWidth = '375px';
                    canvas.style.margin = '0 auto';
                    break;
                default:
                    canvas.style.maxWidth = 'none';
                    canvas.style.margin = '0';
            }
        });
        
        // Zoom functionality
        let zoomLevel = 100;
        const zoomStep = 10;
        const minZoom = 50;
        const maxZoom = 200;
        
        document.getElementById('zoomIn').addEventListener('click', function() {
            if (zoomLevel < maxZoom) {
                zoomLevel += zoomStep;
                applyZoom();
            }
        });
        
        document.getElementById('zoomOut').addEventListener('click', function() {
            if (zoomLevel > minZoom) {
                zoomLevel -= zoomStep;
                applyZoom();
            }
        });
        
        function applyZoom() {
            const canvas = document.querySelector('.canvas-content');
            canvas.style.transform = `scale(${zoomLevel / 100})`;
            canvas.style.transformOrigin = 'top left';
            document.getElementById('zoomLevel').textContent = zoomLevel + '%';
        }
        
        // Auto-save indicator
        function updateSaveStatus(status) {
            const saveStatus = document.getElementById('saveStatus');
            const icon = saveStatus.querySelector('i');
            const text = saveStatus.querySelector('span');
            
            saveStatus.className = 'save-status ' + status;
            
            switch (status) {
                case 'saving':
                    icon.className = 'fas fa-spinner fa-spin';
                    text.textContent = 'Saving...';
                    break;
                case 'saved':
                    icon.className = 'fas fa-check-circle';
                    text.textContent = 'Saved';
                    break;
                case 'error':
                    icon.className = 'fas fa-exclamation-circle';
                    text.textContent = 'Save Error';
                    break;
            }
        }
        
        // Override page builder save function to update status
        if (window.pageBuilder) {
            const originalSave = pageBuilder.save;
            pageBuilder.save = function() {
                updateSaveStatus('saving');
                originalSave.call(this);
            };
            
            const originalAutoSave = pageBuilder.autoSave;
            pageBuilder.autoSave = function() {
                updateSaveStatus('saving');
                originalAutoSave.call(this);
            };
        }
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey || e.metaKey) {
                switch (e.key) {
                    case 's':
                        e.preventDefault();
                        if (window.pageBuilder) {
                            pageBuilder.save();
                        }
                        break;
                    case 'z':
                        e.preventDefault();
                        if (e.shiftKey) {
                            document.querySelector('[data-action="redo"]').click();
                        } else {
                            document.querySelector('[data-action="undo"]').click();
                        }
                        break;
                }
            }
        });
        
        // Initial setup
        document.addEventListener('DOMContentLoaded', function() {
            // Load initial content if exists
            if (window.pageConfig.initialContent.length > 0) {
                document.querySelector('.canvas-content').classList.remove('empty');
            }
            
            // Set up auto-save
            setInterval(function() {
                if (window.pageBuilder) {
                    pageBuilder.autoSave();
                }
            }, 30000);
        });
    </script>
</body>
</html>
