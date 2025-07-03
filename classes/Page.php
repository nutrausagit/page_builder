<?php
// =====================================================
// PAGE CLASS - Mengelola halaman
// =====================================================

class Page {
    private $id;
    private $data;
    
    public function __construct($id = null) {
        if ($id) {
            $this->id = $id;
            $this->load();
        }
    }
    
    /**
     * Load page data
     */
    private function load() {
        $this->data = dbFetch(
            "SELECT p.*, pr.name as project_name FROM pages p 
             JOIN projects pr ON p.project_id = pr.id 
             WHERE p.id = :id",
            ['id' => $this->id]
        );
    }
    
    /**
     * Get all pages by project
     */
    public static function getByProject($projectId, $publishedOnly = false) {
        $where = "project_id = :project_id";
        $params = ['project_id' => $projectId];
        
        if ($publishedOnly) {
            $where .= " AND is_published = 1";
        }
        
        return dbFetchAll(
            "SELECT * FROM pages WHERE {$where} ORDER BY created_at DESC",
            $params
        );
    }
    
    /**
     * Get page by slug
     */
    public static function getBySlug($projectId, $slug) {
        return dbFetch(
            "SELECT * FROM pages WHERE project_id = :project_id AND slug = :slug AND is_published = 1",
            ['project_id' => $projectId, 'slug' => $slug]
        );
    }
    
    /**
     * Get homepage
     */
    public static function getHomepage($projectId) {
        return dbFetch(
            "SELECT * FROM pages WHERE project_id = :project_id AND is_homepage = 1 AND is_published = 1",
            ['project_id' => $projectId]
        );
    }
    
    /**
     * Create new page
     */
    public static function create($data) {
        // Validate required fields
        $required = ['project_id', 'title'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new Exception("Field {$field} diperlukan");
            }
        }
        
        // Generate slug if not provided
        if (empty($data['slug'])) {
            $data['slug'] = createSlug($data['title']);
        }
        
        // Check if slug already exists in project
        $exists = dbExists(
            'pages', 
            'project_id = :project_id AND slug = :slug',
            ['project_id' => $data['project_id'], 'slug' => $data['slug']]
        );
        
        if ($exists) {
            $data['slug'] .= '-' . time();
        }
        
        // Set default content if not provided
        if (empty($data['content'])) {
            $data['content'] = json_encode([]);
        } elseif (is_array($data['content'])) {
            $data['content'] = json_encode($data['content']);
        }
        
        // Handle homepage setting
        if (!empty($data['is_homepage'])) {
            // Remove homepage flag from other pages in same project
            dbUpdate('pages', ['is_homepage' => 0], 'project_id = :project_id', ['project_id' => $data['project_id']]);
        }
        
        $pageId = dbInsert('pages', $data);
        
        if ($pageId) {
            // Create initial version
            self::createVersion($pageId, $data['content'], $data['custom_css'] ?? '', $data['custom_js'] ?? '');
            logActivity('page_create', "Page created: {$data['title']}", getUserId());
        }
        
        return $pageId;
    }
    
    /**
     * Update page
     */
    public function update($data) {
        if (!$this->id) {
            throw new Exception("Page ID tidak ditemukan");
        }
        
        // Generate slug if title changed
        if (!empty($data['title']) && empty($data['slug'])) {
            $data['slug'] = createSlug($data['title']);
        }
        
        // Check slug uniqueness
        if (!empty($data['slug'])) {
            $exists = dbExists(
                'pages', 
                'project_id = :project_id AND slug = :slug AND id != :id',
                [
                    'project_id' => $this->data['project_id'], 
                    'slug' => $data['slug'],
                    'id' => $this->id
                ]
            );
            
            if ($exists) {
                $data['slug'] .= '-' . time();
            }
        }
        
        // Handle content
        if (isset($data['content']) && is_array($data['content'])) {
            $data['content'] = json_encode($data['content']);
        }
        
        // Handle homepage setting
        if (!empty($data['is_homepage'])) {
            dbUpdate('pages', ['is_homepage' => 0], 'project_id = :project_id AND id != :id', [
                'project_id' => $this->data['project_id'],
                'id' => $this->id
            ]);
        }
        
        $updated = dbUpdate('pages', $data, 'id = :id', ['id' => $this->id]);
        
        if ($updated) {
            // Create new version if content changed
            if (isset($data['content'])) {
                self::createVersion(
                    $this->id, 
                    $data['content'], 
                    $data['custom_css'] ?? $this->data['custom_css'] ?? '',
                    $data['custom_js'] ?? $this->data['custom_js'] ?? ''
                );
            }
            
            logActivity('page_update', "Page updated: " . ($this->data['title'] ?? $this->id), getUserId());
            $this->load(); // Reload data
        }
        
        return $updated;
    }
    
    /**
     * Delete page
     */
    public function delete() {
        if (!$this->id) {
            throw new Exception("Page ID tidak ditemukan");
        }
        
        $deleted = dbDelete('pages', 'id = :id', ['id' => $this->id]);
        
        if ($deleted) {
            logActivity('page_delete', "Page deleted: " . ($this->data['title'] ?? $this->id), getUserId());
        }
        
        return $deleted;
    }
    
    /**
     * Publish/unpublish page
     */
    public function publish($publish = true) {
        return $this->update(['is_published' => $publish ? 1 : 0]);
    }
    
    /**
     * Duplicate page
     */
    public function duplicate($newTitle = null) {
        if (!$this->id) {
            throw new Exception("Page ID tidak ditemukan");
        }
        
        if (!$newTitle) {
            $newTitle = $this->data['title'] . ' (Copy)';
        }
        
        $data = [
            'project_id' => $this->data['project_id'],
            'title' => $newTitle,
            'content' => $this->data['content'],
            'custom_css' => $this->data['custom_css'],
            'custom_js' => $this->data['custom_js'],
            'meta_title' => $this->data['meta_title'],
            'meta_description' => $this->data['meta_description'],
            'meta_keywords' => $this->data['meta_keywords'],
            'is_published' => 0 // Always create as draft
        ];
        
        return self::create($data);
    }
    
    /**
     * Get page versions
     */
    public function getVersions() {
        if (!$this->id) {
            return [];
        }
        
        return dbFetchAll(
            "SELECT pv.*, u.username as created_by_name 
             FROM page_versions pv 
             JOIN users u ON pv.created_by = u.id 
             WHERE pv.page_id = :page_id 
             ORDER BY pv.version_number DESC",
            ['page_id' => $this->id]
        );
    }
    
    /**
     * Restore version
     */
    public function restoreVersion($versionNumber) {
        $version = dbFetch(
            "SELECT * FROM page_versions WHERE page_id = :page_id AND version_number = :version",
            ['page_id' => $this->id, 'version' => $versionNumber]
        );
        
        if (!$version) {
            throw new Exception("Versi tidak ditemukan");
        }
        
        return $this->update([
            'content' => $version['content'],
            'custom_css' => $version['custom_css'],
            'custom_js' => $version['custom_js']
        ]);
    }
    
    /**
     * Create page version
     */
    private static function createVersion($pageId, $content, $customCss = '', $customJs = '', $notes = '') {
        // Get next version number
        $maxVersion = dbFetch(
            "SELECT MAX(version_number) as max_version FROM page_versions WHERE page_id = :page_id",
            ['page_id' => $pageId]
        );
        
        $versionNumber = ($maxVersion['max_version'] ?? 0) + 1;
        
        return dbInsert('page_versions', [
            'page_id' => $pageId,
            'version_number' => $versionNumber,
            'content' => $content,
            'custom_css' => $customCss,
            'custom_js' => $customJs,
            'created_by' => getUserId(),
            'notes' => $notes
        ]);
    }
    
    /**
     * Search pages
     */
    public static function search($query, $projectId = null) {
        $where = "MATCH(title, meta_title, meta_description) AGAINST(:query IN NATURAL LANGUAGE MODE)";
        $params = ['query' => $query];
        
        if ($projectId) {
            $where .= " AND project_id = :project_id";
            $params['project_id'] = $projectId;
        }
        
        return dbFetchAll(
            "SELECT *, MATCH(title, meta_title, meta_description) AGAINST(:query IN NATURAL LANGUAGE MODE) as relevance 
             FROM pages 
             WHERE {$where} 
             ORDER BY relevance DESC, created_at DESC",
            $params
        );
    }
    
    /**
     * Get data
     */
    public function getData() {
        return $this->data;
    }
    
    /**
     * Get content as array
     */
    public function getContent() {
        return parseJSON($this->data['content'] ?? '');
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
