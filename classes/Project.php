<?php
// =====================================================
// PROJECT CLASS - Mengelola project/situs
// =====================================================

class Project {
    private $id;
    private $data;
    
    public function __construct($id = null) {
        if ($id) {
            $this->id = $id;
            $this->load();
        }
    }
    
    /**
     * Load project data
     */
    private function load() {
        $this->data = dbFetch(
            "SELECT p.*, u.username as created_by_name FROM projects p 
             JOIN users u ON p.user_id = u.id 
             WHERE p.id = :id",
            ['id' => $this->id]
        );
    }
    
    /**
     * Get all projects
     */
    public static function getAll($activeOnly = true) {
        $where = $activeOnly ? "WHERE p.is_active = 1" : "";
        
        return dbFetchAll(
            "SELECT p.*, u.username as created_by_name,
             (SELECT COUNT(*) FROM pages WHERE project_id = p.id) as page_count,
             (SELECT COUNT(*) FROM assets WHERE project_id = p.id) as asset_count
             FROM projects p 
             JOIN users u ON p.user_id = u.id 
             {$where} 
             ORDER BY p.created_at DESC"
        );
    }
    
    /**
     * Get projects by user
     */
    public static function getByUser($userId, $activeOnly = true) {
        $where = "p.user_id = :user_id";
        $params = ['user_id' => $userId];
        
        if ($activeOnly) {
            $where .= " AND p.is_active = 1";
        }
        
        return dbFetchAll(
            "SELECT p.*, u.username as created_by_name,
             (SELECT COUNT(*) FROM pages WHERE project_id = p.id) as page_count,
             (SELECT COUNT(*) FROM assets WHERE project_id = p.id) as asset_count
             FROM projects p 
             JOIN users u ON p.user_id = u.id 
             WHERE {$where} 
             ORDER BY p.created_at DESC",
            $params
        );
    }
    
    /**
     * Get project by domain
     */
    public static function getByDomain($domain) {
        return dbFetch(
            "SELECT p.*, u.username as created_by_name FROM projects p 
             JOIN users u ON p.user_id = u.id 
             WHERE p.domain = :domain AND p.is_active = 1",
            ['domain' => $domain]
        );
    }
    
    /**
     * Create new project
     */
    public static function create($data) {
        // Validate required fields
        $required = ['name'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new Exception("Field {$field} diperlukan");
            }
        }
        
        // Set user_id if not provided
        if (!isset($data['user_id'])) {
            $data['user_id'] = getUserId();
        }
        
        // Check domain uniqueness if provided
        if (!empty($data['domain'])) {
            if (dbExists('projects', 'domain = :domain', ['domain' => $data['domain']])) {
                throw new Exception("Domain sudah digunakan");
            }
        }
        
        $projectId = dbInsert('projects', $data);
        
        if ($projectId) {
            // Create default homepage
            $homepageData = [
                'project_id' => $projectId,
                'title' => 'Beranda',
                'slug' => 'home',
                'content' => json_encode([]),
                'is_homepage' => 1,
                'is_published' => 1
            ];
            
            Page::create($homepageData);
            
            logActivity('project_create', "Project created: {$data['name']}", getUserId());
        }
        
        return $projectId;
    }
    
    /**
     * Update project
     */
    public function update($data) {
        if (!$this->id) {
            throw new Exception("Project ID tidak ditemukan");
        }
        
        // Check domain uniqueness if changed
        if (isset($data['domain']) && $data['domain'] !== $this->data['domain']) {
            if (dbExists('projects', 'domain = :domain AND id != :id', ['domain' => $data['domain'], 'id' => $this->id])) {
                throw new Exception("Domain sudah digunakan");
            }
        }
        
        $updated = dbUpdate('projects', $data, 'id = :id', ['id' => $this->id]);
        
        if ($updated) {
            logActivity('project_update', "Project updated: " . ($this->data['name'] ?? $this->id), getUserId());
            $this->load();
        }
        
        return $updated;
    }
    
    /**
     * Delete project
     */
    public function delete() {
        if (!$this->id) {
            throw new Exception("Project ID tidak ditemukan");
        }
        
        // Check permissions
        if ($this->data['user_id'] != getUserId() && !Auth::isAdmin()) {
            throw new Exception("Tidak memiliki izin untuk menghapus project ini");
        }
        
        db()->beginTransaction();
        
        try {
            // Delete all assets files
            $assets = Asset::getAll($this->id);
            foreach ($assets as $assetData) {
                $asset = new Asset($assetData['id']);
                $asset->delete();
            }
            
            // Database will handle cascading deletes for pages, assets, etc.
            $deleted = dbDelete('projects', 'id = :id', ['id' => $this->id]);
            
            db()->commit();
            
            if ($deleted) {
                logActivity('project_delete', "Project deleted: " . ($this->data['name'] ?? $this->id), getUserId());
            }
            
            return $deleted;
        } catch (Exception $e) {
            db()->rollback();
            throw $e;
        }
    }
    
    /**
     * Duplicate project
     */
    public function duplicate($newName = null, $newDomain = null) {
        if (!$this->id) {
            throw new Exception("Project ID tidak ditemukan");
        }
        
        if (!$newName) {
            $newName = $this->data['name'] . ' (Copy)';
        }
        
        db()->beginTransaction();
        
        try {
            // Create new project
            $projectData = [
                'name' => $newName,
                'description' => $this->data['description'],
                'domain' => $newDomain,
                'user_id' => getUserId()
            ];
            
            $newProjectId = self::create($projectData);
            
            // Duplicate pages
            $pages = Page::getByProject($this->id);
            foreach ($pages as $pageData) {
                $page = new Page($pageData['id']);
                $newPageData = [
                    'project_id' => $newProjectId,
                    'title' => $pageData['title'],
                    'content' => $pageData['content'],
                    'custom_css' => $pageData['custom_css'],
                    'custom_js' => $pageData['custom_js'],
                    'meta_title' => $pageData['meta_title'],
                    'meta_description' => $pageData['meta_description'],
                    'meta_keywords' => $pageData['meta_keywords'],
                    'is_homepage' => $pageData['is_homepage'],
                    'is_published' => 0 // Create as draft
                ];
                
                Page::create($newPageData);
            }
            
            db()->commit();
            
            logActivity('project_duplicate', "Project duplicated: {$this->data['name']} -> {$newName}", getUserId());
            
            return $newProjectId;
        } catch (Exception $e) {
            db()->rollback();
            throw $e;
        }
    }
    
    /**
     * Archive/Unarchive project
     */
    public function archive($archive = true) {
        return $this->update(['is_active' => !$archive]);
    }
    
    /**
     * Get project statistics
     */
    public function getStats() {
        if (!$this->id) {
            return null;
        }
        
        // Page statistics
        $pageStats = dbFetch(
            "SELECT 
                COUNT(*) as total_pages,
                SUM(CASE WHEN is_published = 1 THEN 1 ELSE 0 END) as published_pages,
                SUM(CASE WHEN is_homepage = 1 THEN 1 ELSE 0 END) as homepage_count
             FROM pages WHERE project_id = :project_id",
            ['project_id' => $this->id]
        );
        
        // Asset statistics
        $assetStats = dbFetch(
            "SELECT 
                COUNT(*) as total_assets,
                SUM(file_size) as total_size,
                COUNT(DISTINCT file_type) as file_types
             FROM assets WHERE project_id = :project_id",
            ['project_id' => $this->id]
        );
        
        // Recent activity
        $recentPages = dbFetchAll(
            "SELECT title, updated_at FROM pages 
             WHERE project_id = :project_id 
             ORDER BY updated_at DESC LIMIT 5",
            ['project_id' => $this->id]
        );
        
        return [
            'pages' => [
                'total' => (int)$pageStats['total_pages'],
                'published' => (int)$pageStats['published_pages'],
                'draft' => (int)$pageStats['total_pages'] - (int)$pageStats['published_pages'],
                'has_homepage' => (int)$pageStats['homepage_count'] > 0
            ],
            'assets' => [
                'total' => (int)$assetStats['total_assets'],
                'total_size' => (int)$assetStats['total_size'],
                'total_size_formatted' => formatFileSize($assetStats['total_size']),
                'file_types' => (int)$assetStats['file_types']
            ],
            'recent_pages' => $recentPages
        ];
    }
    
    /**
     * Get project settings
     */
    public function getSettings() {
        // This could be extended to include project-specific settings
        return [
            'name' => $this->data['name'],
            'description' => $this->data['description'],
            'domain' => $this->data['domain'],
            'is_active' => $this->data['is_active']
        ];
    }
    
    /**
     * Update project settings
     */
    public function updateSettings($settings) {
        return $this->update($settings);
    }
    
    /**
     * Export project
     */
    public function export() {
        if (!$this->id) {
            throw new Exception("Project ID tidak ditemukan");
        }
        
        // Get all project data
        $projectData = $this->data;
        $pages = Page::getByProject($this->id);
        $assets = Asset::getAll($this->id);
        
        // Process pages
        $pagesData = [];
        foreach ($pages as $page) {
            $pageObj = new Page($page['id']);
            $pagesData[] = [
                'title' => $page['title'],
                'slug' => $page['slug'],
                'content' => $pageObj->getContent(),
                'custom_css' => $page['custom_css'],
                'custom_js' => $page['custom_js'],
                'meta_title' => $page['meta_title'],
                'meta_description' => $page['meta_description'],
                'meta_keywords' => $page['meta_keywords'],
                'is_homepage' => $page['is_homepage'],
                'is_published' => $page['is_published']
            ];
        }
        
        // Process assets (metadata only, not files)
        $assetsData = [];
        foreach ($assets as $asset) {
            $assetsData[] = [
                'filename' => $asset['filename'],
                'original_filename' => $asset['original_filename'],
                'file_type' => $asset['file_type'],
                'alt_text' => $asset['alt_text']
            ];
        }
        
        return [
            'project' => [
                'name' => $projectData['name'],
                'description' => $projectData['description']
            ],
            'pages' => $pagesData,
            'assets' => $assetsData,
            'version' => APP_VERSION,
            'exported_at' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Search within project
     */
    public function search($query) {
        if (!$this->id) {
            return [];
        }
        
        // Search pages
        $pages = Page::search($query, $this->id);
        
        // Search assets
        $assets = Asset::search($query, $this->id);
        
        return [
            'pages' => $pages,
            'assets' => $assets
        ];
    }
    
    /**
     * Get recent activity
     */
    public function getRecentActivity($limit = 10) {
        if (!$this->id) {
            return [];
        }
        
        // This is a simplified version - in a real app you might have an activity log table
        $activity = [];
        
        // Recent page updates
        $pages = dbFetchAll(
            "SELECT title, updated_at, 'page_update' as type 
             FROM pages 
             WHERE project_id = :project_id 
             ORDER BY updated_at DESC LIMIT :limit",
            ['project_id' => $this->id, 'limit' => $limit]
        );
        
        foreach ($pages as $page) {
            $activity[] = [
                'type' => 'page_update',
                'title' => $page['title'],
                'time' => $page['updated_at'],
                'time_ago' => timeAgo($page['updated_at'])
            ];
        }
        
        // Recent asset uploads
        $assets = dbFetchAll(
            "SELECT original_filename, created_at, 'asset_upload' as type 
             FROM assets 
             WHERE project_id = :project_id 
             ORDER BY created_at DESC LIMIT :limit",
            ['project_id' => $this->id, 'limit' => $limit]
        );
        
        foreach ($assets as $asset) {
            $activity[] = [
                'type' => 'asset_upload',
                'title' => $asset['original_filename'],
                'time' => $asset['created_at'],
                'time_ago' => timeAgo($asset['created_at'])
            ];
        }
        
        // Sort by time
        usort($activity, function($a, $b) {
            return strtotime($b['time']) - strtotime($a['time']);
        });
        
        return array_slice($activity, 0, $limit);
    }
    
    /**
     * Get data
     */
    public function getData() {
        return $this->data;
    }
    
    /**
     * Get property
     */
    public function __get($property) {
        return $this->data[$property] ?? null;
    }
    
    /**
     * Check if property exists
     */
    public function __isset($property) {
        return isset($this->data[$property]);
    }
}
?>
