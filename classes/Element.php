<?php
// =====================================================
// ELEMENT CLASS - Mengelola element types
// =====================================================

class Element {
    private $id;
    private $data;
    
    public function __construct($id = null) {
        if ($id) {
            $this->id = $id;
            $this->load();
        }
    }
    
    /**
     * Load element data
     */
    private function load() {
        $this->data = dbFetch("SELECT * FROM element_types WHERE id = :id", ['id' => $this->id]);
    }
    
    /**
     * Get all element types
     */
    public static function getAll($activeOnly = true) {
        $where = $activeOnly ? "WHERE is_active = 1" : "";
        return dbFetchAll("SELECT * FROM element_types {$where} ORDER BY category, sort_order, display_name");
    }
    
    /**
     * Get elements by category
     */
    public static function getByCategory($category = null, $activeOnly = true) {
        $where = [];
        $params = [];
        
        if ($activeOnly) {
            $where[] = "is_active = 1";
        }
        
        if ($category) {
            $where[] = "category = :category";
            $params['category'] = $category;
        }
        
        $whereClause = empty($where) ? "" : "WHERE " . implode(" AND ", $where);
        
        return dbFetchAll(
            "SELECT * FROM element_types {$whereClause} ORDER BY sort_order, display_name",
            $params
        );
    }
    
    /**
     * Get element categories
     */
    public static function getCategories() {
        return dbFetchAll("SELECT DISTINCT category FROM element_types WHERE is_active = 1 ORDER BY category");
    }
    
    /**
     * Get element by name
     */
    public static function getByName($name) {
        return dbFetch("SELECT * FROM element_types WHERE name = :name", ['name' => $name]);
    }
    
    /**
     * Create new element type
     */
    public static function create($data) {
        // Validate required fields
        $required = ['name', 'display_name', 'category'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new Exception("Field {$field} diperlukan");
            }
        }
        
        // Check if name already exists
        if (dbExists('element_types', 'name = :name', ['name' => $data['name']])) {
            throw new Exception("Nama element sudah digunakan");
        }
        
        // Handle JSON fields
        if (isset($data['default_properties']) && is_array($data['default_properties'])) {
            $data['default_properties'] = json_encode($data['default_properties']);
        }
        
        if (isset($data['default_styles']) && is_array($data['default_styles'])) {
            $data['default_styles'] = json_encode($data['default_styles']);
        }
        
        $elementId = dbInsert('element_types', $data);
        
        if ($elementId) {
            logActivity('element_create', "Element type created: {$data['display_name']}", getUserId());
        }
        
        return $elementId;
    }
    
    /**
     * Update element type
     */
    public function update($data) {
        if (!$this->id) {
            throw new Exception("Element ID tidak ditemukan");
        }
        
        // Check name uniqueness if changed
        if (isset($data['name']) && $data['name'] !== $this->data['name']) {
            if (dbExists('element_types', 'name = :name AND id != :id', ['name' => $data['name'], 'id' => $this->id])) {
                throw new Exception("Nama element sudah digunakan");
            }
        }
        
        // Handle JSON fields
        if (isset($data['default_properties']) && is_array($data['default_properties'])) {
            $data['default_properties'] = json_encode($data['default_properties']);
        }
        
        if (isset($data['default_styles']) && is_array($data['default_styles'])) {
            $data['default_styles'] = json_encode($data['default_styles']);
        }
        
        $updated = dbUpdate('element_types', $data, 'id = :id', ['id' => $this->id]);
        
        if ($updated) {
            logActivity('element_update', "Element type updated: " . ($this->data['display_name'] ?? $this->id), getUserId());
            $this->load();
        }
        
        return $updated;
    }
    
    /**
     * Delete element type
     */
    public function delete() {
        if (!$this->id) {
            throw new Exception("Element ID tidak ditemukan");
        }
        
        // Check if element is used in any pages
        $usage = $this->getUsageCount();
        if ($usage > 0) {
            throw new Exception("Element masih digunakan di {$usage} halaman");
        }
        
        $deleted = dbDelete('element_types', 'id = :id', ['id' => $this->id]);
        
        if ($deleted) {
            logActivity('element_delete', "Element type deleted: " . ($this->data['display_name'] ?? $this->id), getUserId());
        }
        
        return $deleted;
    }
    
    /**
     * Toggle active status
     */
    public function toggleActive() {
        return $this->update(['is_active' => !$this->data['is_active']]);
    }
    
    /**
     * Get usage count (approximate)
     */
    public function getUsageCount() {
        if (!$this->id || !$this->data) {
            return 0;
        }
        
        // This is an approximate count since we need to search JSON content
        // In a real application, you might want to maintain a separate usage tracking table
        $pages = dbFetchAll("SELECT content FROM pages WHERE content IS NOT NULL");
        $count = 0;
        
        foreach ($pages as $page) {
            $content = parseJSON($page['content']);
            $count += $this->countElementInContent($content, $this->data['name']);
        }
        
        return $count;
    }
    
    /**
     * Count element usage in content recursively
     */
    private function countElementInContent($content, $elementName) {
        $count = 0;
        
        if (is_array($content)) {
            foreach ($content as $item) {
                if (is_array($item)) {
                    if (isset($item['type']) && $item['type'] === $elementName) {
                        $count++;
                    }
                    
                    if (isset($item['children'])) {
                        $count += $this->countElementInContent($item['children'], $elementName);
                    }
                }
            }
        }
        
        return $count;
    }
    
    /**
     * Get default element data
     */
    public function getDefaultData() {
        if (!$this->data) {
            return null;
        }
        
        return [
            'type' => $this->data['name'],
            'id' => 'element-' . uniqid(),
            'properties' => parseJSON($this->data['default_properties']),
            'styles' => parseJSON($this->data['default_styles']),
            'children' => []
        ];
    }
    
    /**
     * Validate element data
     */
    public static function validateElementData($elementData) {
        $errors = [];
        
        if (!isset($elementData['type'])) {
            $errors[] = "Element type diperlukan";
        } else {
            $elementType = self::getByName($elementData['type']);
            if (!$elementType) {
                $errors[] = "Element type tidak valid: " . $elementData['type'];
            }
        }
        
        if (!isset($elementData['id'])) {
            $errors[] = "Element ID diperlukan";
        }
        
        // Validate properties based on element type
        if (isset($elementData['type'])) {
            $errors = array_merge($errors, self::validateElementProperties($elementData));
        }
        
        return $errors;
    }
    
    /**
     * Validate element properties based on type
     */
    private static function validateElementProperties($elementData) {
        $errors = [];
        $type = $elementData['type'];
        $properties = $elementData['properties'] ?? [];
        
        switch ($type) {
            case 'text':
                if (empty($properties['content'])) {
                    $errors[] = "Konten teks diperlukan";
                }
                break;
                
            case 'heading':
                if (empty($properties['content'])) {
                    $errors[] = "Konten heading diperlukan";
                }
                if (!in_array($properties['tag'] ?? '', ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'])) {
                    $errors[] = "Tag heading tidak valid";
                }
                break;
                
            case 'image':
                if (empty($properties['src'])) {
                    $errors[] = "Sumber gambar diperlukan";
                }
                break;
                
            case 'button':
                if (empty($properties['text'])) {
                    $errors[] = "Teks tombol diperlukan";
                }
                break;
                
            case 'video':
                if (empty($properties['src'])) {
                    $errors[] = "Sumber video diperlukan";
                }
                break;
        }
        
        return $errors;
    }
    
    /**
     * Render element HTML
     */
    public static function renderElement($elementData) {
        $type = $elementData['type'];
        $properties = $elementData['properties'] ?? [];
        $styles = $elementData['styles'] ?? [];
        $children = $elementData['children'] ?? [];
        $id = $elementData['id'] ?? '';
        
        // Convert styles array to CSS string
        $styleStr = '';
        foreach ($styles as $property => $value) {
            $property = preg_replace('/([A-Z])/', '-$1', $property);
            $property = strtolower($property);
            $styleStr .= "{$property}: {$value}; ";
        }
        
        $html = '';
        
        switch ($type) {
            case 'text':
                $content = htmlspecialchars($properties['content'] ?? '');
                $tag = $properties['tag'] ?? 'p';
                $html = "<{$tag} id=\"{$id}\" style=\"{$styleStr}\">{$content}</{$tag}>";
                break;
                
            case 'heading':
                $content = htmlspecialchars($properties['content'] ?? '');
                $tag = $properties['tag'] ?? 'h2';
                $html = "<{$tag} id=\"{$id}\" style=\"{$styleStr}\">{$content}</{$tag}>";
                break;
                
            case 'image':
                $src = htmlspecialchars($properties['src'] ?? '');
                $alt = htmlspecialchars($properties['alt'] ?? '');
                $html = "<img id=\"{$id}\" src=\"{$src}\" alt=\"{$alt}\" style=\"{$styleStr}\" />";
                break;
                
            case 'button':
                $text = htmlspecialchars($properties['text'] ?? '');
                $link = htmlspecialchars($properties['link'] ?? '#');
                $target = $properties['target'] ?? '_self';
                $html = "<a id=\"{$id}\" href=\"{$link}\" target=\"{$target}\" style=\"{$styleStr}\">{$text}</a>";
                break;
                
            case 'container':
            case 'row':
            case 'column':
                $tag = $properties['tag'] ?? 'div';
                $html = "<{$tag} id=\"{$id}\" style=\"{$styleStr}\">";
                foreach ($children as $child) {
                    $html .= self::renderElement($child);
                }
                $html .= "</{$tag}>";
                break;
                
            case 'video':
                $src = htmlspecialchars($properties['src'] ?? '');
                $controls = !empty($properties['controls']) ? 'controls' : '';
                $html = "<video id=\"{$id}\" src=\"{$src}\" {$controls} style=\"{$styleStr}\"></video>";
                break;
                
            case 'spacer':
                $height = $properties['height'] ?? '50px';
                $html = "<div id=\"{$id}\" style=\"height: {$height}; {$styleStr}\"></div>";
                break;
                
            default:
                $html = "<div id=\"{$id}\" style=\"{$styleStr}\">Unknown element type: {$type}</div>";
        }
        
        return $html;
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
