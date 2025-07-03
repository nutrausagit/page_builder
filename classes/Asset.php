<?php
// =====================================================
// ASSET CLASS - Mengelola file assets
// =====================================================

class Asset {
    private $id;
    private $data;
    
    public function __construct($id = null) {
        if ($id) {
            $this->id = $id;
            $this->load();
        }
    }
    
    /**
     * Load asset data
     */
    private function load() {
        $this->data = dbFetch(
            "SELECT a.*, p.name as project_name, u.username as uploaded_by_name 
             FROM assets a 
             LEFT JOIN projects p ON a.project_id = p.id 
             JOIN users u ON a.user_id = u.id 
             WHERE a.id = :id",
            ['id' => $this->id]
        );
    }
    
    /**
     * Get all assets
     */
    public static function getAll($projectId = null, $fileType = null) {
        $where = [];
        $params = [];
        
        if ($projectId) {
            $where[] = "a.project_id = :project_id";
            $params['project_id'] = $projectId;
        }
        
        if ($fileType) {
            $where[] = "a.file_type = :file_type";
            $params['file_type'] = $fileType;
        }
        
        $whereClause = empty($where) ? "" : "WHERE " . implode(" AND ", $where);
        
        return dbFetchAll(
            "SELECT a.*, p.name as project_name, u.username as uploaded_by_name 
             FROM assets a 
             LEFT JOIN projects p ON a.project_id = p.id 
             JOIN users u ON a.user_id = u.id 
             {$whereClause} 
             ORDER BY a.created_at DESC",
            $params
        );
    }
    
    /**
     * Get assets by type
     */
    public static function getByType($fileType, $projectId = null) {
        return self::getAll($projectId, $fileType);
    }
    
    /**
     * Get image assets
     */
    public static function getImages($projectId = null) {
        $imageTypes = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $where = "a.file_type IN ('" . implode("','", $imageTypes) . "')";
        $params = [];
        
        if ($projectId) {
            $where .= " AND a.project_id = :project_id";
            $params['project_id'] = $projectId;
        }
        
        return dbFetchAll(
            "SELECT a.*, p.name as project_name, u.username as uploaded_by_name 
             FROM assets a 
             LEFT JOIN projects p ON a.project_id = p.id 
             JOIN users u ON a.user_id = u.id 
             WHERE {$where} 
             ORDER BY a.created_at DESC",
            $params
        );
    }
    
    /**
     * Upload file
     */
    public static function upload($file, $projectId = null, $altText = '') {
        // Validate file
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            throw new Exception("File tidak valid");
        }
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("Error upload file: " . self::getUploadErrorMessage($file['error']));
        }
        
        if ($file['size'] > MAX_FILE_SIZE) {
            throw new Exception("Ukuran file terlalu besar. Maksimal " . formatFileSize(MAX_FILE_SIZE));
        }
        
        if (!isAllowedFileType($file['name'])) {
            throw new Exception("Tipe file tidak diizinkan");
        }
        
        // Generate unique filename
        $filename = generateUniqueFilename($file['name']);
        $filePath = UPLOAD_PATH . $filename;
        
        // Create directory if not exists
        $dir = dirname($filePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $filePath)) {
            throw new Exception("Gagal menyimpan file");
        }
        
        // Get file info
        $fileType = getFileExtension($filename);
        $fileSize = filesize($filePath);
        
        // Create thumbnail for images
        if (in_array($fileType, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
            self::createThumbnail($filePath, $filename);
        }
        
        // Save to database
        $assetData = [
            'project_id' => $projectId,
            'filename' => $filename,
            'original_filename' => $file['name'],
            'file_type' => $fileType,
            'file_size' => $fileSize,
            'file_path' => UPLOAD_URL . $filename,
            'alt_text' => $altText,
            'user_id' => getUserId()
        ];
        
        $assetId = dbInsert('assets', $assetData);
        
        if ($assetId) {
            logActivity('asset_upload', "File uploaded: {$file['name']}", getUserId());
        }
        
        return $assetId;
    }
    
    /**
     * Update asset
     */
    public function update($data) {
        if (!$this->id) {
            throw new Exception("Asset ID tidak ditemukan");
        }
        
        $updated = dbUpdate('assets', $data, 'id = :id', ['id' => $this->id]);
        
        if ($updated) {
            logActivity('asset_update', "Asset updated: " . ($this->data['original_filename'] ?? $this->id), getUserId());
            $this->load();
        }
        
        return $updated;
    }
    
    /**
     * Delete asset
     */
    public function delete() {
        if (!$this->id) {
            throw new Exception("Asset ID tidak ditemukan");
        }
        
        // Delete physical file
        $filePath = UPLOAD_PATH . $this->data['filename'];
        if (file_exists($filePath)) {
            unlink($filePath);
        }
        
        // Delete thumbnail if exists
        $thumbnailPath = self::getThumbnailPath($this->data['filename']);
        if (file_exists($thumbnailPath)) {
            unlink($thumbnailPath);
        }
        
        $deleted = dbDelete('assets', 'id = :id', ['id' => $this->id]);
        
        if ($deleted) {
            logActivity('asset_delete', "Asset deleted: " . ($this->data['original_filename'] ?? $this->id), getUserId());
        }
        
        return $deleted;
    }
    
    /**
     * Get file URL
     */
    public function getUrl() {
        return $this->data['file_path'] ?? '';
    }
    
    /**
     * Get thumbnail URL
     */
    public function getThumbnailUrl() {
        if (!$this->isImage()) {
            return $this->getDefaultThumbnail();
        }
        
        $thumbnailFile = 'thumb_' . $this->data['filename'];
        $thumbnailPath = UPLOAD_PATH . 'thumbnails/' . $thumbnailFile;
        
        if (file_exists($thumbnailPath)) {
            return UPLOAD_URL . 'thumbnails/' . $thumbnailFile;
        }
        
        return $this->getUrl();
    }
    
    /**
     * Check if asset is image
     */
    public function isImage() {
        $imageTypes = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        return in_array($this->data['file_type'] ?? '', $imageTypes);
    }
    
    /**
     * Check if asset is video
     */
    public function isVideo() {
        $videoTypes = ['mp4', 'mov', 'avi', 'wmv', 'flv'];
        return in_array($this->data['file_type'] ?? '', $videoTypes);
    }
    
    /**
     * Check if asset is document
     */
    public function isDocument() {
        $docTypes = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'];
        return in_array($this->data['file_type'] ?? '', $docTypes);
    }
    
    /**
     * Get file icon
     */
    public function getIcon() {
        if ($this->isImage()) {
            return 'fa-image';
        } elseif ($this->isVideo()) {
            return 'fa-video';
        } elseif ($this->isDocument()) {
            return 'fa-file-alt';
        } else {
            return 'fa-file';
        }
    }
    
    /**
     * Get default thumbnail for non-images
     */
    private function getDefaultThumbnail() {
        $type = $this->data['file_type'] ?? 'file';
        return "/assets/images/file-types/{$type}.png";
    }
    
    /**
     * Create thumbnail for image
     */
    private static function createThumbnail($sourceFile, $filename) {
        $thumbnailDir = UPLOAD_PATH . 'thumbnails/';
        if (!is_dir($thumbnailDir)) {
            mkdir($thumbnailDir, 0755, true);
        }
        
        $thumbnailFile = $thumbnailDir . 'thumb_' . $filename;
        $fileType = getFileExtension($filename);
        
        // Create image resource
        switch ($fileType) {
            case 'jpg':
            case 'jpeg':
                $source = imagecreatefromjpeg($sourceFile);
                break;
            case 'png':
                $source = imagecreatefrompng($sourceFile);
                break;
            case 'gif':
                $source = imagecreatefromgif($sourceFile);
                break;
            case 'webp':
                $source = imagecreatefromwebp($sourceFile);
                break;
            default:
                return false;
        }
        
        if (!$source) {
            return false;
        }
        
        // Get original dimensions
        $originalWidth = imagesx($source);
        $originalHeight = imagesy($source);
        
        // Calculate new dimensions
        $ratio = min(THUMBNAIL_WIDTH / $originalWidth, THUMBNAIL_HEIGHT / $originalHeight);
        $newWidth = $originalWidth * $ratio;
        $newHeight = $originalHeight * $ratio;
        
        // Create thumbnail
        $thumbnail = imagecreatetruecolor($newWidth, $newHeight);
        
        // Preserve transparency for PNG and GIF
        if ($fileType === 'png' || $fileType === 'gif') {
            imagealphablending($thumbnail, false);
            imagesavealpha($thumbnail, true);
            $transparent = imagecolorallocatealpha($thumbnail, 255, 255, 255, 127);
            imagefilledrectangle($thumbnail, 0, 0, $newWidth, $newHeight, $transparent);
        }
        
        // Resize image
        imagecopyresampled($thumbnail, $source, 0, 0, 0, 0, $newWidth, $newHeight, $originalWidth, $originalHeight);
        
        // Save thumbnail
        switch ($fileType) {
            case 'jpg':
            case 'jpeg':
                imagejpeg($thumbnail, $thumbnailFile, IMAGE_QUALITY);
                break;
            case 'png':
                imagepng($thumbnail, $thumbnailFile);
                break;
            case 'gif':
                imagegif($thumbnail, $thumbnailFile);
                break;
            case 'webp':
                imagewebp($thumbnail, $thumbnailFile, IMAGE_QUALITY);
                break;
        }
        
        // Clean up
        imagedestroy($source);
        imagedestroy($thumbnail);
        
        return true;
    }
    
    /**
     * Get thumbnail path
     */
    private static function getThumbnailPath($filename) {
        return UPLOAD_PATH . 'thumbnails/thumb_' . $filename;
    }
    
    /**
     * Get upload error message
     */
    private static function getUploadErrorMessage($errorCode) {
        switch ($errorCode) {
            case UPLOAD_ERR_INI_SIZE:
                return 'File terlalu besar (melebihi upload_max_filesize)';
            case UPLOAD_ERR_FORM_SIZE:
                return 'File terlalu besar (melebihi MAX_FILE_SIZE)';
            case UPLOAD_ERR_PARTIAL:
                return 'File hanya terupload sebagian';
            case UPLOAD_ERR_NO_FILE:
                return 'Tidak ada file yang dipilih';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'Folder temporary tidak ditemukan';
            case UPLOAD_ERR_CANT_WRITE:
                return 'Gagal menulis file ke disk';
            case UPLOAD_ERR_EXTENSION:
                return 'Upload dihentikan oleh ekstensi PHP';
            default:
                return 'Error upload tidak dikenal';
        }
    }
    
    /**
     * Search assets
     */
    public static function search($query, $projectId = null, $fileType = null) {
        $where = "(a.filename LIKE :query OR a.original_filename LIKE :query OR a.alt_text LIKE :query)";
        $params = ['query' => "%{$query}%"];
        
        if ($projectId) {
            $where .= " AND a.project_id = :project_id";
            $params['project_id'] = $projectId;
        }
        
        if ($fileType) {
            $where .= " AND a.file_type = :file_type";
            $params['file_type'] = $fileType;
        }
        
        return dbFetchAll(
            "SELECT a.*, p.name as project_name, u.username as uploaded_by_name 
             FROM assets a 
             LEFT JOIN projects p ON a.project_id = p.id 
             JOIN users u ON a.user_id = u.id 
             WHERE {$where} 
             ORDER BY a.created_at DESC",
            $params
        );
    }
    
    /**
     * Get storage statistics
     */
    public static function getStorageStats($projectId = null) {
        $where = "1=1";
        $params = [];
        
        if ($projectId) {
            $where = "project_id = :project_id";
            $params['project_id'] = $projectId;
        }
        
        $stats = dbFetch(
            "SELECT 
                COUNT(*) as total_files,
                SUM(file_size) as total_size,
                AVG(file_size) as avg_size,
                MAX(file_size) as max_size
             FROM assets 
             WHERE {$where}",
            $params
        );
        
        // Get file type breakdown
        $typeStats = dbFetchAll(
            "SELECT file_type, COUNT(*) as count, SUM(file_size) as size 
             FROM assets 
             WHERE {$where} 
             GROUP BY file_type 
             ORDER BY count DESC",
            $params
        );
        
        return [
            'total_files' => (int)$stats['total_files'],
            'total_size' => (int)$stats['total_size'],
            'total_size_formatted' => formatFileSize($stats['total_size']),
            'avg_size' => (int)$stats['avg_size'],
            'avg_size_formatted' => formatFileSize($stats['avg_size']),
            'max_size' => (int)$stats['max_size'],
            'max_size_formatted' => formatFileSize($stats['max_size']),
            'by_type' => $typeStats
        ];
    }
    
    /**
     * Clean up orphaned files
     */
    public static function cleanupOrphanedFiles() {
        $uploadDir = UPLOAD_PATH;
        $thumbnailDir = $uploadDir . 'thumbnails/';
        
        // Get all files in database
        $dbFiles = dbFetchAll("SELECT filename FROM assets");
        $dbFilenames = array_column($dbFiles, 'filename');
        
        $orphanedFiles = [];
        $totalSize = 0;
        
        // Check upload directory
        if (is_dir($uploadDir)) {
            $files = scandir($uploadDir);
            foreach ($files as $file) {
                if ($file === '.' || $file === '..' || is_dir($uploadDir . $file)) {
                    continue;
                }
                
                if (!in_array($file, $dbFilenames)) {
                    $filePath = $uploadDir . $file;
                    $fileSize = filesize($filePath);
                    $orphanedFiles[] = [
                        'file' => $file,
                        'path' => $filePath,
                        'size' => $fileSize
                    ];
                    $totalSize += $fileSize;
                }
            }
        }
        
        return [
            'files' => $orphanedFiles,
            'count' => count($orphanedFiles),
            'total_size' => $totalSize,
            'total_size_formatted' => formatFileSize($totalSize)
        ];
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
