<?php
// =====================================================
// TEMPLATE CLASS - Mengelola template halaman
// =====================================================

class Template {
    private $id;
    private $data;
    
    public function __construct($id = null) {
        if ($id) {
            $this->id = $id;
            $this->load();
        }
    }
    
    /**
     * Load template data
     */
    private function load() {
        $this->data = dbFetch(
            "SELECT t.*, u.username as created_by_name FROM templates t 
             LEFT JOIN users u ON t.user_id = u.id 
             WHERE t.id = :id",
            ['id' => $this->id]
        );
    }
    
    /**
     * Get all templates
     */
    public static function getAll($publicOnly = false, $category = null) {
        $where = [];
        $params = [];
        
        if ($publicOnly) {
            $where[] = "is_public = 1";
        }
        
        if ($category) {
            $where[] = "category = :category";
            $params['category'] = $category;
        }
        
        $whereClause = empty($where) ? "" : "WHERE " . implode(" AND ", $where);
        
        return dbFetchAll(
            "SELECT t.*, u.username as created_by_name 
             FROM templates t 
             LEFT JOIN users u ON t.user_id = u.id 
             {$whereClause} 
             ORDER BY usage_count DESC, created_at DESC",
            $params
        );
    }
    
    /**
     * Get templates by user
     */
    public static function getByUser($userId, $includePublic = true) {
        $where = "user_id = :user_id";
        $params = ['user_id' => $userId];
        
        if ($includePublic) {
            $where = "({$where} OR is_public = 1)";
        }
        
        return dbFetchAll(
            "SELECT t.*, u.username as created_by_name 
             FROM templates t 
             LEFT JOIN users u ON t.user_id = u.id 
             WHERE {$where} 
             ORDER BY created_at DESC",
            $params
        );
    }
    
    /**
     * Get template categories
     */
    public static function getCategories() {
        return dbFetchAll("SELECT DISTINCT category FROM templates WHERE category IS NOT NULL ORDER BY category");
    }
    
    /**
     * Search templates
     */
    public static function search($query, $category = null) {
        $where = "MATCH(name, description) AGAINST(:query IN NATURAL LANGUAGE MODE)";
        $params = ['query' => $query];
        
        if ($category) {
            $where .= " AND category = :category";
            $params['category'] = $category;
        }
        
        return dbFetchAll(
            "SELECT t.*, u.username as created_by_name,
             MATCH(name, description) AGAINST(:query IN NATURAL LANGUAGE MODE) as relevance 
             FROM templates t 
             LEFT JOIN users u ON t.user_id = u.id 
             WHERE {$where} 
             ORDER BY relevance DESC, usage_count DESC",
            $params
        );
    }
    
    /**
     * Create new template
     */
    public static function create($data) {
        // Validate required fields
        $required = ['name', 'content'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new Exception("Field {$field} diperlukan");
            }
        }
        
        // Handle content
        if (is_array($data['content'])) {
            $data['content'] = json_encode($data['content']);
        }
        
        // Validate JSON content
        if (!isValidJSON($data['content'])) {
            throw new Exception("Format konten template tidak valid");
        }
        
        // Set user_id if not provided
        if (!isset($data['user_id'])) {
            $data['user_id'] = getUserId();
        }
        
        $templateId = dbInsert('templates', $data);
        
        if ($templateId) {
            logActivity('template_create', "Template created: {$data['name']}", getUserId());
        }
        
        return $templateId;
    }
    
    /**
     * Update template
     */
    public function update($data) {
        if (!$this->id) {
            throw new Exception("Template ID tidak ditemukan");
        }
        
        // Handle content
        if (isset($data['content'])) {
            if (is_array($data['content'])) {
                $data['content'] = json_encode($data['content']);
            }
            
            // Validate JSON content
            if (!isValidJSON($data['content'])) {
                throw new Exception("Format konten template tidak valid");
            }
        }
        
        $updated = dbUpdate('templates', $data, 'id = :id', ['id' => $this->id]);
        
        if ($updated) {
            logActivity('template_update', "Template updated: " . ($this->data['name'] ?? $this->id), getUserId());
            $this->load();
        }
        
        return $updated;
    }
    
    /**
     * Delete template
     */
    public function delete() {
        if (!$this->id) {
            throw new Exception("Template ID tidak ditemukan");
        }
        
        // Check permissions
        if ($this->data['user_id'] != getUserId() && !Auth::isAdmin()) {
            throw new Exception("Tidak memiliki izin untuk menghapus template ini");
        }
        
        $deleted = dbDelete('templates', 'id = :id', ['id' => $this->id]);
        
        if ($deleted) {
            // Delete thumbnail file if exists
            if (!empty($this->data['thumbnail'])) {
                $thumbnailPath = UPLOAD_PATH . basename($this->data['thumbnail']);
                if (file_exists($thumbnailPath)) {
                    unlink($thumbnailPath);
                }
            }
            
            logActivity('template_delete', "Template deleted: " . ($this->data['name'] ?? $this->id), getUserId());
        }
        
        return $deleted;
    }
    
    /**
     * Duplicate template
     */
    public function duplicate($newName = null) {
        if (!$this->id) {
            throw new Exception("Template ID tidak ditemukan");
        }
        
        if (!$newName) {
            $newName = $this->data['name'] . ' (Copy)';
        }
        
        $data = [
            'name' => $newName,
            'description' => $this->data['description'],
            'category' => $this->data['category'],
            'content' => $this->data['content'],
            'is_public' => 0, // Always create as private
            'user_id' => getUserId()
        ];
        
        return self::create($data);
    }
    
    /**
     * Increment usage count
     */
    public function incrementUsage() {
        if (!$this->id) {
            return false;
        }
        
        return dbUpdate('templates', ['usage_count' => $this->data['usage_count'] + 1], 'id = :id', ['id' => $this->id]);
    }
    
    /**
     * Apply template to page
     */
    public function applyToPage($pageId) {
        if (!$this->id) {
            throw new Exception("Template ID tidak ditemukan");
        }
        
        $page = new Page($pageId);
        if (!$page->getData()) {
            throw new Exception("Halaman tidak ditemukan");
        }
        
        // Update page content with template content
        $result = $page->update(['content' => $this->data['content']]);
        
        if ($result) {
            $this->incrementUsage();
            logActivity('template_apply', "Template applied to page: {$page->title}", getUserId());
        }
        
        return $result;
    }
    
    /**
     * Generate thumbnail from content
     */
    public function generateThumbnail() {
        // This is a placeholder - in a real implementation you might:
        // 1. Render the template content to HTML
        // 2. Use a service like Puppeteer to take a screenshot
        // 3. Save the thumbnail image
        
        $thumbnailPath = "/placeholder-thumbnail.jpg";
        $this->update(['thumbnail' => $thumbnailPath]);
        
        return $thumbnailPath;
    }
    
    /**
     * Export template as JSON
     */
    public function export() {
        if (!$this->data) {
            return null;
        }
        
        return [
            'name' => $this->data['name'],
            'description' => $this->data['description'],
            'category' => $this->data['category'],
            'content' => parseJSON($this->data['content']),
            'version' => APP_VERSION,
            'exported_at' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Import template from JSON
     */
    public static function import($jsonData, $name = null) {
        if (is_string($jsonData)) {
            $data = json_decode($jsonData, true);
            if (!$data) {
                throw new Exception("Format JSON tidak valid");
            }
        } else {
            $data = $jsonData;
        }
        
        // Validate required fields
        if (empty($data['content'])) {
            throw new Exception("Konten template diperlukan");
        }
        
        $templateData = [
            'name' => $name ?: ($data['name'] ?? 'Imported Template'),
            'description' => $data['description'] ?? 'Template yang diimpor',
            'category' => $data['category'] ?? 'imported',
            'content' => is_array($data['content']) ? json_encode($data['content']) : $data['content'],
            'is_public' => 0,
            'user_id' => getUserId()
        ];
        
        return self::create($templateData);
    }
    
    /**
     * Get template preview data
     */
    public function getPreviewData() {
        if (!$this->data) {
            return null;
        }
        
        $content = parseJSON($this->data['content']);
        
        return [
            'id' => $this->id,
            'name' => $this->data['name'],
            'description' => $this->data['description'],
            'thumbnail' => $this->data['thumbnail'],
            'category' => $this->data['category'],
            'usage_count' => $this->data['usage_count'],
            'content_preview' => $this->generateContentPreview($content),
            'element_count' => $this->countElements($content)
        ];
    }
    
    /**
     * Generate content preview text
     */
    private function generateContentPreview($content, $maxLength = 200) {
        $text = $this->extractTextFromContent($content);
        return truncateText($text, $maxLength);
    }
    
    /**
     * Extract text from content recursively
     */
    private function extractTextFromContent($content) {
        $text = '';
        
        if (is_array($content)) {
            foreach ($content as $item) {
                if (is_array($item)) {
                    if (isset($item['properties']['content'])) {
                        $text .= ' ' . $item['properties']['content'];
                    }
                    
                    if (isset($item['children'])) {
                        $text .= ' ' . $this->extractTextFromContent($item['children']);
                    }
                }
            }
        }
        
        return trim($text);
    }
    
    /**
     * Count elements in content
     */
    private function countElements($content) {
        $count = 0;
        
        if (is_array($content)) {
            foreach ($content as $item) {
                if (is_array($item) && isset($item['type'])) {
                    $count++;
                    
                    if (isset($item['children'])) {
                        $count += $this->countElements($item['children']);
                    }
                }
            }
        }
        
        return $count;
    }
    
    /**
     * Get content as array
     */
    public function getContent() {
        return parseJSON($this->data['content'] ?? '');
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
