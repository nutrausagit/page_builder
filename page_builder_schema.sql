-- =====================================================
-- SKEMA DATABASE PAGE BUILDER DRAG-AND-DROP
-- Kompatibel dengan MariaDB/MySQL
-- Created: 2025-07-02
-- =====================================================

-- Hapus database jika sudah ada (hati-hati!)
-- DROP DATABASE IF EXISTS page_builder;

-- Buat database baru
CREATE DATABASE IF NOT EXISTS page_builder 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE page_builder;

-- =====================================================
-- TABEL USERS - Mengelola pengguna sistem
-- =====================================================
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'editor', 'viewer') DEFAULT 'editor',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE
);

-- =====================================================
-- TABEL PROJECTS - Mengelola project/situs
-- =====================================================
CREATE TABLE projects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    domain VARCHAR(100),
    user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_domain (domain)
);

-- =====================================================
-- TABEL PAGES - Mengelola halaman dalam project
-- =====================================================
CREATE TABLE pages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    slug VARCHAR(200) NOT NULL,
    meta_title VARCHAR(200),
    meta_description TEXT,
    meta_keywords TEXT,
    content JSON,
    custom_css TEXT,
    custom_js TEXT,
    is_published BOOLEAN DEFAULT FALSE,
    is_homepage BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    INDEX idx_project_id (project_id),
    INDEX idx_slug (slug),
    INDEX idx_published (is_published),
    UNIQUE KEY unique_project_slug (project_id, slug)
);

-- =====================================================
-- TABEL ELEMENT_TYPES - Jenis-jenis elemen yang tersedia
-- =====================================================
CREATE TABLE element_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    display_name VARCHAR(100) NOT NULL,
    icon VARCHAR(50),
    category VARCHAR(50) NOT NULL,
    default_properties JSON,
    default_styles JSON,
    is_active BOOLEAN DEFAULT TRUE,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- =====================================================
-- TABEL TEMPLATES - Template halaman yang tersimpan
-- =====================================================
CREATE TABLE templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    thumbnail VARCHAR(255),
    category VARCHAR(50),
    content JSON NOT NULL,
    is_public BOOLEAN DEFAULT TRUE,
    user_id INT,
    usage_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_category (category),
    INDEX idx_public (is_public),
    INDEX idx_user_id (user_id)
);

-- =====================================================
-- TABEL ASSETS - File assets (gambar, dokumen, dll)
-- =====================================================
CREATE TABLE assets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT,
    filename VARCHAR(255) NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    file_type VARCHAR(50) NOT NULL,
    file_size INT NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    alt_text VARCHAR(255),
    user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_project_id (project_id),
    INDEX idx_file_type (file_type),
    INDEX idx_user_id (user_id)
);

-- =====================================================
-- TABEL PAGE_VERSIONS - Versioning halaman
-- =====================================================
CREATE TABLE page_versions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    page_id INT NOT NULL,
    version_number INT NOT NULL,
    content JSON NOT NULL,
    custom_css TEXT,
    custom_js TEXT,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    notes TEXT,
    FOREIGN KEY (page_id) REFERENCES pages(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_page_id (page_id),
    INDEX idx_version (page_id, version_number),
    UNIQUE KEY unique_page_version (page_id, version_number)
);

-- =====================================================
-- TABEL SETTINGS - Pengaturan global sistem
-- =====================================================
CREATE TABLE settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    setting_type ENUM('string', 'number', 'boolean', 'json') DEFAULT 'string',
    description TEXT,
    is_public BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- =====================================================
-- INSERT DATA AWAL
-- =====================================================

-- Insert default user admin
INSERT INTO users (username, email, password, role) VALUES 
('admin', 'admin@pagebuilder.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

-- Insert default element types
INSERT INTO element_types (name, display_name, icon, category, default_properties, default_styles) VALUES 
('text', 'Teks', 'fa-font', 'basic', '{"content": "Teks contoh", "tag": "p"}', '{"fontSize": "16px", "color": "#333333", "textAlign": "left"}'),
('heading', 'Heading', 'fa-heading', 'basic', '{"content": "Heading", "tag": "h2"}', '{"fontSize": "32px", "color": "#333333", "fontWeight": "bold"}'),
('image', 'Gambar', 'fa-image', 'media', '{"src": "", "alt": "Gambar"}', '{"width": "100%", "height": "auto"}'),
('button', 'Tombol', 'fa-hand-pointer', 'interactive', '{"text": "Klik di sini", "link": "#", "target": "_self"}', '{"backgroundColor": "#007bff", "color": "#ffffff", "padding": "10px 20px", "border": "none", "borderRadius": "4px"}'),
('container', 'Container', 'fa-square', 'layout', '{"columns": 1}', '{"padding": "20px", "margin": "0px", "backgroundColor": "transparent"}'),
('row', 'Baris', 'fa-grip-lines', 'layout', '{"columns": 2}', '{"display": "flex", "gap": "20px"}'),
('column', 'Kolom', 'fa-columns', 'layout', '{"width": "50%"}', '{"flex": "1", "padding": "10px"}'),
('video', 'Video', 'fa-video', 'media', '{"src": "", "controls": true}', '{"width": "100%", "height": "auto"}'),
('form', 'Form', 'fa-wpforms', 'interactive', '{"method": "POST", "action": ""}', '{"padding": "20px"}'),
('spacer', 'Spacer', 'fa-arrows-alt-v', 'layout', '{"height": "50px"}', '{"display": "block"}');

-- Insert default settings
INSERT INTO settings (setting_key, setting_value, setting_type, description, is_public) VALUES 
('site_name', 'Page Builder', 'string', 'Nama situs', TRUE),
('site_logo', '', 'string', 'URL logo situs', TRUE),
('max_file_size', '5242880', 'number', 'Maksimal ukuran file upload (bytes)', FALSE),
('allowed_file_types', '["jpg", "jpeg", "png", "gif", "webp", "pdf", "doc", "docx"]', 'json', 'Tipe file yang diizinkan', FALSE),
('enable_registration', 'false', 'boolean', 'Aktifkan registrasi pengguna baru', FALSE),
('default_role', 'editor', 'string', 'Role default untuk pengguna baru', FALSE);

-- Insert sample template
INSERT INTO templates (name, description, category, content, is_public) VALUES 
('Landing Page Sederhana', 'Template landing page dengan header, hero section, dan footer', 'business', 
'[
    {
        "type": "container",
        "id": "header",
        "properties": {"tag": "header"},
        "styles": {"backgroundColor": "#ffffff", "padding": "20px", "borderBottom": "1px solid #e0e0e0"},
        "children": [
            {
                "type": "row",
                "properties": {"columns": 2},
                "styles": {"alignItems": "center", "justifyContent": "space-between"},
                "children": [
                    {
                        "type": "heading",
                        "properties": {"content": "Brand Name", "tag": "h1"},
                        "styles": {"fontSize": "24px", "margin": "0"}
                    },
                    {
                        "type": "button",
                        "properties": {"text": "Kontak", "link": "#contact"},
                        "styles": {"backgroundColor": "#007bff", "color": "#ffffff"}
                    }
                ]
            }
        ]
    },
    {
        "type": "container",
        "id": "hero",
        "properties": {},
        "styles": {"backgroundColor": "#f8f9fa", "padding": "80px 20px", "textAlign": "center"},
        "children": [
            {
                "type": "heading",
                "properties": {"content": "Selamat Datang di Situs Kami", "tag": "h1"},
                "styles": {"fontSize": "48px", "marginBottom": "20px"}
            },
            {
                "type": "text",
                "properties": {"content": "Deskripsi singkat tentang layanan atau produk yang Anda tawarkan."},
                "styles": {"fontSize": "18px", "color": "#666666", "marginBottom": "30px"}
            },
            {
                "type": "button",
                "properties": {"text": "Mulai Sekarang", "link": "#start"},
                "styles": {"backgroundColor": "#28a745", "color": "#ffffff", "padding": "15px 30px", "fontSize": "18px"}
            }
        ]
    }
]', TRUE);

-- =====================================================
-- STORED PROCEDURES DAN FUNCTIONS
-- =====================================================

DELIMITER //

-- Function untuk generate slug dari title
CREATE FUNCTION generate_slug(input_title VARCHAR(200)) 
RETURNS VARCHAR(200)
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE slug VARCHAR(200);
    SET slug = LOWER(input_title);
    SET slug = REPLACE(slug, ' ', '-');
    SET slug = REPLACE(slug, 'ä', 'a');
    SET slug = REPLACE(slug, 'ö', 'o');
    SET slug = REPLACE(slug, 'ü', 'u');
    SET slug = REPLACE(slug, 'ß', 'ss');
    -- Hapus karakter non-alphanumeric kecuali dash
    SET slug = REGEXP_REPLACE(slug, '[^a-z0-9-]', '');
    -- Hapus multiple dash berurutan
    SET slug = REGEXP_REPLACE(slug, '-+', '-');
    -- Hapus dash di awal dan akhir
    SET slug = TRIM(BOTH '-' FROM slug);
    RETURN slug;
END//

-- Procedure untuk duplicate page
CREATE PROCEDURE duplicate_page(
    IN source_page_id INT,
    IN new_title VARCHAR(200),
    IN user_id INT
)
BEGIN
    DECLARE new_page_id INT;
    DECLARE source_project_id INT;
    DECLARE source_content JSON;
    DECLARE source_css TEXT;
    DECLARE source_js TEXT;
    DECLARE new_slug VARCHAR(200);
    
    -- Get source page data
    SELECT project_id, content, custom_css, custom_js 
    INTO source_project_id, source_content, source_css, source_js
    FROM pages WHERE id = source_page_id;
    
    -- Generate new slug
    SET new_slug = generate_slug(new_title);
    
    -- Insert new page
    INSERT INTO pages (project_id, title, slug, content, custom_css, custom_js, is_published)
    VALUES (source_project_id, new_title, new_slug, source_content, source_css, source_js, FALSE);
    
    SET new_page_id = LAST_INSERT_ID();
    
    -- Create initial version
    INSERT INTO page_versions (page_id, version_number, content, custom_css, custom_js, created_by)
    VALUES (new_page_id, 1, source_content, source_css, source_js, user_id);
    
    SELECT new_page_id as page_id;
END//

-- Procedure untuk backup page content
CREATE PROCEDURE backup_page_content(
    IN p_page_id INT,
    IN p_user_id INT,
    IN p_backup_notes TEXT
)
BEGIN
    DECLARE current_version INT DEFAULT 1;
    DECLARE page_content JSON;
    DECLARE page_css TEXT;
    DECLARE page_js TEXT;
    
    -- Get current max version
    SELECT COALESCE(MAX(version_number), 0) + 1 INTO current_version
    FROM page_versions WHERE page_id = p_page_id;
    
    -- Get current page content
    SELECT content, custom_css, custom_js
    INTO page_content, page_css, page_js
    FROM pages WHERE id = p_page_id;
    
    -- Insert backup version
    INSERT INTO page_versions (page_id, version_number, content, custom_css, custom_js, created_by, notes)
    VALUES (p_page_id, current_version, page_content, page_css, page_js, p_user_id, p_backup_notes);
    
    SELECT current_version as version_number;
END//

DELIMITER ;

-- =====================================================
-- VIEWS UNTUK KEMUDAHAN QUERY
-- =====================================================

-- View untuk halaman dengan informasi project
CREATE VIEW page_details AS
SELECT 
    p.id,
    p.title,
    p.slug,
    p.meta_title,
    p.meta_description,
    p.is_published,
    p.is_homepage,
    p.created_at,
    p.updated_at,
    pr.name as project_name,
    pr.domain as project_domain,
    u.username as created_by
FROM pages p
JOIN projects pr ON p.project_id = pr.id
JOIN users u ON pr.user_id = u.id;

-- View untuk statistik penggunaan template
CREATE VIEW template_stats AS
SELECT 
    t.id,
    t.name,
    t.category,
    t.usage_count,
    t.is_public,
    u.username as created_by,
    t.created_at
FROM templates t
LEFT JOIN users u ON t.user_id = u.id;

-- View untuk asset summary
CREATE VIEW asset_summary AS
SELECT 
    a.id,
    a.filename,
    a.original_filename,
    a.file_type,
    a.file_size,
    a.alt_text,
    a.created_at,
    p.name as project_name,
    u.username as uploaded_by
FROM assets a
LEFT JOIN projects p ON a.project_id = p.id
JOIN users u ON a.user_id = u.id;

-- =====================================================
-- INDEXES UNTUK PERFORMA
-- =====================================================

-- Index untuk pencarian content
ALTER TABLE pages ADD FULLTEXT INDEX idx_page_content (title, meta_title, meta_description);
ALTER TABLE templates ADD FULLTEXT INDEX idx_template_search (name, description);

-- Index komposit untuk query yang sering digunakan
CREATE INDEX idx_pages_project_published ON pages (project_id, is_published);
CREATE INDEX idx_assets_project_type ON assets (project_id, file_type);
CREATE INDEX idx_page_versions_page_created ON page_versions (page_id, created_at DESC);

-- =====================================================
-- TRIGGERS UNTUK DATA INTEGRITY
-- =====================================================

DELIMITER //

-- Trigger untuk auto-generate slug saat insert page
CREATE TRIGGER before_page_insert 
BEFORE INSERT ON pages
FOR EACH ROW
BEGIN
    IF NEW.slug IS NULL OR NEW.slug = '' THEN
        SET NEW.slug = generate_slug(NEW.title);
    END IF;
    
    -- Pastikan hanya satu homepage per project
    IF NEW.is_homepage = TRUE THEN
        UPDATE pages SET is_homepage = FALSE 
        WHERE project_id = NEW.project_id AND id != NEW.id;
    END IF;
END//

-- Trigger untuk update template usage count
CREATE TRIGGER after_template_usage 
AFTER INSERT ON pages
FOR EACH ROW
BEGIN
    -- Jika page dibuat dari template, increment usage count
    -- (implementasi ini memerlukan field template_id di tabel pages)
    -- UPDATE templates SET usage_count = usage_count + 1 WHERE id = NEW.template_id;
    NULL;
END//

DELIMITER ;

-- =====================================================
-- SAMPLE QUERIES UNTUK TESTING
-- =====================================================

/*
-- Query untuk mendapatkan semua halaman dalam project
SELECT * FROM page_details WHERE project_name = 'Website Utama';

-- Query untuk mencari template berdasarkan kategori
SELECT * FROM template_stats WHERE category = 'business' AND is_public = TRUE;

-- Query untuk mendapatkan asset berdasarkan project
SELECT * FROM asset_summary WHERE project_name = 'Website Utama' ORDER BY created_at DESC;

-- Query untuk mendapatkan versi halaman
SELECT pv.*, u.username as created_by_name 
FROM page_versions pv 
JOIN users u ON pv.created_by = u.id 
WHERE pv.page_id = 1 
ORDER BY pv.version_number DESC;

-- Query untuk statistik penggunaan element types
SELECT et.display_name, et.category, COUNT(*) as usage_count
FROM element_types et
-- JOIN dengan data usage dari JSON content pages
-- (memerlukan JSON query untuk menghitung penggunaan)
GROUP BY et.id, et.display_name, et.category;
*/

-- =====================================================
-- SECURITY NOTES
-- =====================================================

/*
CATATAN KEAMANAN:

1. Password harus di-hash menggunakan PHP password_hash()
2. Validasi semua input untuk mencegah SQL injection
3. Gunakan prepared statements dalam PHP
4. Implementasikan role-based access control
5. Sanitasi JSON content sebelum disimpan
6. Rate limiting untuk API endpoints
7. File upload validation dan scanning
8. HTTPS wajib untuk production
9. Regular backup database
10. Monitor untuk unusual activity

CONTOH USAGE DALAM PHP:
- $stmt = $pdo->prepare("SELECT * FROM pages WHERE project_id = ? AND is_published = 1");
- $stmt->execute([$project_id]);
- $hashed_password = password_hash($password, PASSWORD_DEFAULT);
*/
