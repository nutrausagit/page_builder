# Page Builder Pro

Page Builder Pro adalah aplikasi web page builder dengan fitur drag-and-drop yang memungkinkan Anda membuat halaman web tanpa perlu coding. Aplikasi ini dibangun dengan PHP, MySQL, dan JavaScript modern.

## ✨ Fitur Utama

### 🎨 Drag & Drop Editor
- Interface intuitif untuk membangun halaman
- Real-time preview saat editing
- Element library yang lengkap
- Property panel untuk customization

### 🧱 Element Types
- **Text & Heading** - Teks dan heading dengan styling
- **Image** - Upload dan manage gambar
- **Button** - Tombol dengan link dan styling
- **Container** - Layout containers dan rows
- **Video** - Embed video dengan controls
- **Form** - Form elements dengan validation
- **Spacer** - Spacing elements

### 📄 Page Management
- Multiple projects support
- Page versioning dan history
- SEO meta tags support
- Custom CSS dan JavaScript injection
- Publish/unpublish functionality

### 🎯 Template System
- Pre-built templates
- Template library dengan preview
- Export/import templates
- Template usage tracking

### 📁 Asset Management
- File upload dengan validation
- Image thumbnail generation
- Asset organization per project
- Multiple file type support

### 👥 User Management
- Role-based access control (Admin, Editor, Viewer)
- User permissions management
- Secure authentication system

## 🛠️ Teknologi

- **Backend**: PHP 7.4+ dengan PDO
- **Database**: MySQL/MariaDB
- **Frontend**: HTML5, CSS3, JavaScript ES6+
- **CSS Framework**: Custom CSS dengan Flexbox/Grid
- **Icons**: Font Awesome 6
- **Architecture**: MVC Pattern

## 📋 Requirement

### Server Requirements
- PHP 7.4 atau lebih tinggi
- MySQL 5.7+ atau MariaDB 10.2+
- Apache/Nginx web server
- mod_rewrite enabled (untuk Apache)

### PHP Extensions
- PDO
- PDO MySQL
- JSON
- GD (untuk image processing)
- File uploads enabled

## 🚀 Instalasi

### 1. Download & Extract
```bash
# Clone atau download source code
git clone [repository-url]
cd page_builder
```

### 2. Set Permissions
```bash
chmod 755 assets/uploads/
chmod 644 .htaccess
```

### 3. Database Setup
Jalankan installer dengan mengakses:
```
http://your-domain.com/install.php
```

Installer akan memandu Anda melalui:
1. System requirements check
2. Database configuration
3. Schema installation
4. Admin user creation

### 4. Security
Setelah instalasi selesai:
- Hapus file `install.php`
- Pastikan folder `includes/` dan `classes/` tidak dapat diakses public
- Konfigurasi SSL/HTTPS untuk production

## 🏗️ Struktur Project

```
page_builder/
├── index.php                  # Frontend entry point
├── install.php               # Installation script
├── .htaccess                 # URL rewriting rules
├── 
├── admin/                    # Admin panel
│   ├── login.php            # Login page
│   ├── dashboard.php        # Admin dashboard
│   ├── logout.php           # Logout handler
│   ├── pages/               # Page management
│   │   └── edit.php         # Drag & drop editor
│   └── api/                 # API endpoints
│       ├── elements.php     # Element management API
│       ├── pages.php        # Page management API
│       └── properties.php   # Properties panel API
│
├── includes/                # Core system files
│   ├── config.php          # Configuration
│   ├── database.php        # Database wrapper
│   ├── functions.php       # Utility functions
│   └── auth.php            # Authentication
│
├── classes/                 # PHP Classes
│   ├── Page.php            # Page management
│   ├── Element.php         # Element types
│   ├── Template.php        # Template system
│   ├── Asset.php           # Asset management
│   └── Project.php         # Project management
│
├── assets/                  # Static assets
│   ├── css/                # Stylesheets
│   ├── js/                 # JavaScript files
│   └── uploads/            # User uploads
│
└── templates/              # Page templates
    └── 404.php            # 404 error page
```

## 🎯 Penggunaan

### Login ke Admin Panel
1. Akses `/admin/login.php`
2. Login dengan akun admin yang dibuat saat instalasi

### Membuat Project Baru
1. Dari dashboard, klik "New Project"
2. Isi nama dan deskripsi project
3. Set domain jika diperlukan

### Membuat Halaman
1. Pilih project
2. Klik "New Page"
3. Masuk ke drag & drop editor
4. Drag elements dari library ke canvas
5. Customize properties di panel kanan
6. Save dan publish

### Menggunakan Templates
1. Buka template library
2. Preview template yang tersedia
3. Apply ke halaman baru
4. Customize sesuai kebutuhan

## 🔧 Konfigurasi

### Database Configuration
Edit `includes/config.php`:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'page_builder');
define('DB_USER', 'username');
define('DB_PASS', 'password');
```

### Upload Settings
```php
define('MAX_FILE_SIZE', 5242880); // 5MB
define('ALLOWED_FILE_TYPES', ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf']);
```

### Security Settings
```php
define('SESSION_LIFETIME', 7200); // 2 hours
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 300); // 5 minutes
```

## 🛡️ Keamanan

### Built-in Security Features
- Password hashing dengan PHP `password_hash()`
- SQL injection protection dengan prepared statements
- XSS protection dengan input sanitization
- CSRF protection untuk forms
- File upload validation
- Session security dengan httponly cookies
- Rate limiting untuk login attempts

### Security Headers
Aplikasi menyertakan security headers:
- X-Frame-Options
- X-Content-Type-Options
- X-XSS-Protection
- Content Security Policy (optional)

## 🔌 API Endpoints

### Pages API
```
GET  /api/pages.php?action=list          # List pages
GET  /api/pages.php?action=get&id=1      # Get page
POST /api/pages.php                      # Save/create page
```

### Elements API
```
GET  /api/elements.php                   # List element types
GET  /api/elements.php?action=get_default&type=text  # Get default element
POST /api/elements.php                   # Create/update element
```

### Properties API
```
GET  /api/properties.php?type=text       # Get element properties
POST /api/properties.php                 # Validate properties
```

## 🎨 Customization

### Adding Custom Elements
1. Tambah element type di database
2. Update `Element.php` class
3. Tambah rendering logic
4. Update JavaScript handler

### Custom Styling
- Edit `assets/css/admin.css` untuk admin panel
- Tambah custom CSS di halaman melalui editor
- Modify default element styles

### Custom JavaScript
- Edit `assets/js/admin.js` untuk editor functionality
- Tambah custom JS di halaman melalui editor

## 🔄 Backup & Restore

### Database Backup
```sql
mysqldump -u username -p page_builder > backup.sql
```

### File Backup
Backup folder berikut:
- `assets/uploads/` - User uploaded files
- `includes/config.php` - Configuration

### Restore
```sql
mysql -u username -p page_builder < backup.sql
```

## 🐛 Troubleshooting

### Common Issues

**1. Upload errors**
- Check file permissions pada `assets/uploads/`
- Verify `MAX_FILE_SIZE` setting
- Check PHP `upload_max_filesize`

**2. Database connection errors**
- Verify database credentials
- Check if database exists
- Ensure MySQL/MariaDB is running

**3. .htaccess issues**
- Ensure mod_rewrite is enabled
- Check file permissions
- Verify RewriteBase setting

**4. JavaScript errors**
- Check browser console
- Verify file paths
- Clear browser cache

### Debug Mode
Enable debug mode di `config.php`:
```php
define('DEBUG_MODE', true);
```

## 📝 Changelog

### Version 1.0.0
- Initial release
- Drag & drop editor
- Basic element types
- User management
- Template system
- Asset management

## 🤝 Contributing

1. Fork the repository
2. Create feature branch
3. Commit changes
4. Push to branch
5. Create Pull Request

## 📄 License

This project is licensed under the MIT License.

## 👥 Support

Untuk support dan dokumentasi lebih lanjut:
- Check documentation di `/docs`
- Submit issues di repository
- Contact: admin@pagebuilder.com

## 🙏 Credits

- Icons: Font Awesome
- Fonts: System fonts stack
- Inspiration: Modern page builders

---

**Page Builder Pro** - Professional drag-and-drop page builder for PHP applications.
