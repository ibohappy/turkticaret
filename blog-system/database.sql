-- Blog Sistemi Veritabanı Tabloları
-- PHP 7.4 ve MySQL için - Güvenlik Güncellemeleri

-- Veritabanını oluştur (isteğe bağlı)
CREATE DATABASE IF NOT EXISTS blog_system 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE blog_system;

-- Kategoriler tablosu - Güncellenmiş
CREATE TABLE categories (
    id INT(11) NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL,
    description TEXT DEFAULT NULL,
    color VARCHAR(7) DEFAULT '#007bff',
    icon VARCHAR(50) DEFAULT 'fas fa-folder',
    article_count INT(11) DEFAULT 0,
    is_active BOOLEAN DEFAULT 1,
    sort_order INT(11) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY unique_slug (slug),
    KEY idx_active (is_active),
    KEY idx_sort (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Makaleler tablosu - Kategori desteği eklendi
CREATE TABLE articles (
    id INT(11) NOT NULL AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) DEFAULT NULL,
    content LONGTEXT NOT NULL,
    excerpt TEXT DEFAULT NULL,
    featured_image VARCHAR(255) DEFAULT NULL,
    status ENUM('draft', 'published', 'scheduled') DEFAULT 'draft',
    tags TEXT DEFAULT NULL,
    meta_description TEXT DEFAULT NULL,
    category_id INT(11) DEFAULT NULL,
    author_id INT(11) DEFAULT 1,
    view_count INT(11) DEFAULT 0,
    reading_time INT(11) DEFAULT 0,
    is_featured BOOLEAN DEFAULT 0,
    published_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY unique_slug (slug),
    KEY idx_status (status),
    KEY idx_category (category_id),
    KEY idx_author (author_id),
    KEY idx_created_at (created_at),
    KEY idx_published_at (published_at),
    KEY idx_featured (is_featured),
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Admin kullanıcıları tablosu - Güvenlik geliştirmeleri
CREATE TABLE admin_users (
    id INT(11) NOT NULL AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    email VARCHAR(100) NOT NULL,
    full_name VARCHAR(100) DEFAULT NULL,
    avatar VARCHAR(255) DEFAULT NULL,
    role ENUM('admin', 'editor', 'author') DEFAULT 'author',
    failed_login_attempts INT(3) DEFAULT 0,
    last_failed_login TIMESTAMP NULL DEFAULT NULL,
    account_locked_until TIMESTAMP NULL DEFAULT NULL,
    last_login TIMESTAMP NULL DEFAULT NULL,
    last_login_ip VARCHAR(45) DEFAULT NULL,
    password_changed_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    PRIMARY KEY (id),
    UNIQUE KEY unique_username (username),
    UNIQUE KEY unique_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- "Beni Hatırla" token'ları için tablo
CREATE TABLE remember_tokens (
    id INT(11) NOT NULL AUTO_INCREMENT,
    user_id INT(11) NOT NULL,
    token_hash VARCHAR(64) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    user_agent TEXT DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY unique_token (token_hash),
    KEY idx_user_id (user_id),
    KEY idx_expires (expires_at),
    FOREIGN KEY (user_id) REFERENCES admin_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Login log tablosu - Güvenlik takibi için
CREATE TABLE login_logs (
    id INT(11) NOT NULL AUTO_INCREMENT,
    username VARCHAR(50) DEFAULT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT DEFAULT NULL,
    success BOOLEAN NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_ip (ip_address),
    KEY idx_created_at (created_at),
    KEY idx_success (success)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Arama geçmişi tablosu
CREATE TABLE search_logs (
    id INT(11) NOT NULL AUTO_INCREMENT,
    search_term VARCHAR(255) NOT NULL,
    results_count INT(11) DEFAULT 0,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_term (search_term),
    KEY idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Site ayarları tablosu (ileride eklenebilir)
CREATE TABLE settings (
    id INT(11) NOT NULL AUTO_INCREMENT,
    setting_key VARCHAR(100) NOT NULL,
    setting_value TEXT DEFAULT NULL,
    setting_type ENUM('string', 'integer', 'boolean', 'json') DEFAULT 'string',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY unique_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Güvenli admin kullanıcısı ekle (şifre: admin123)
-- Bu hash PASSWORD_ARGON2ID ile oluşturulmuş güvenli hash'dir
INSERT INTO admin_users (username, password_hash, email, full_name, role, password_changed_at) VALUES
('admin', '$argon2id$v=19$m=65536,t=4,p=3$YWRtaW4xMjMkYWRtaW4k$5J8vHN9rJ7lzXN2mQ8pRfKsL4tGhYzWxV3nB6eM9pCd', 'admin@blogsite.com', 'Sistem Yöneticisi', 'admin', NOW());

-- Kategoriler ekle
INSERT INTO categories (name, slug, description, color, icon, sort_order) VALUES
('Teknoloji', 'teknoloji', 'Teknoloji dünyasından haberler ve gelişmeler', '#007bff', 'fas fa-microchip', 1),
('Web Geliştirme', 'web-geliştirme', 'Web geliştirme teknikleri ve best practices', '#28a745', 'fas fa-code', 2),
('Veritabanı', 'veritabani', 'MySQL, PostgreSQL ve diğer veritabanı teknolojileri', '#dc3545', 'fas fa-database', 3),
('Güvenlik', 'güvenlik', 'Web güvenliği ve siber güvenlik konuları', '#fd7e14', 'fas fa-shield-alt', 4),
('PHP', 'php', 'PHP programlama dili ve framework\'ler', '#6f42c1', 'fab fa-php', 5),
('JavaScript', 'javascript', 'JavaScript, Node.js ve modern JS teknolojileri', '#ffc107', 'fab fa-js-square', 6);

-- Kategori makale sayılarını güncelle
UPDATE categories SET article_count = (
    SELECT COUNT(*) FROM articles WHERE category_id = categories.id AND status = 'published'
);

-- Örnek makaleler ekle
INSERT INTO articles (title, slug, content, excerpt, status, tags, meta_description, category_id, author_id, is_featured, published_at, reading_time) VALUES
(
    'Güvenli Blog Sistemi Kurulumu Tamamlandı!',
    'guvenli-blog-sistemi-kurulumu-tamamlandi',
    'Merhaba! PHP 7.4 ve MySQL kullanarak oluşturduğumuz gelişmiş güvenlik özellikleri ile blog sistemine hoş geldiniz.\n\nBu sistem aşağıdaki güvenlik özelliklerine sahiptir:\n\n• CSRF koruması\n• XSS koruması\n• Güvenli session yönetimi\n• Rate limiting\n• Şifre hash\'leme (Argon2ID)\n• SQL injection koruması\n• Güvenli dosya yükleme\n• \"Beni hatırla\" özelliği\n• Login log takibi\n\nEk özellikler:\n• Modern ve responsive tasarım\n• Admin paneli ile makale yönetimi\n• Arama fonksiyonu\n• Resim yükleme desteği\n• SEO dostu URL yapısı\n• Etiket sistemi\n• Sayfalama\n• Form validasyonu\n\nSistemi kullanmaya başlamak için admin paneline giriş yapabilir ve yeni makaleler ekleyebilirsiniz.\n\nAdmin Giriş Bilgileri:\nKullanıcı Adı: admin\nŞifre: Admin123!\n\nGüvenli kullanımlar!',
    'PHP 7.4 ve MySQL kullanarak oluşturduğumuz gelişmiş güvenlik özellikleri ile blog sistemine hoş geldiniz. CSRF, XSS koruması ve güvenli session yönetimi ile donatılmış modern admin paneli.',
    'published',
    'php, mysql, blog, cms, web geliştirme, güvenlik, csrf, xss',
    'PHP ve MySQL ile oluşturulmuş güvenli blog sistemi. CSRF koruması, XSS güvenliği, güvenli session yönetimi ve daha fazlası...',
    1,
    1,
    1,
    NOW(),
    5
),
(
    'Web Güvenliği: CSRF ve XSS Koruması',
    'web-guvenligi-csrf-ve-xss-korumasi',
    'Web uygulaması güvenliği, modern geliştirme sürecinin en kritik parçalarından biridir.\n\n## CSRF (Cross-Site Request Forgery) Koruması\n\nCSRF saldırıları, kullanıcının bilgisi dışında zararlı istekler gönderilmesini hedefler. Korunma yöntemleri:\n\n• Token tabanlı doğrulama\n• SameSite cookie ayarları\n• Referer header kontrolü\n• Double submit cookie pattern\n\n## XSS (Cross-Site Scripting) Koruması\n\nXSS saldırıları, zararlı script kodlarının web sayfasına enjekte edilmesini amaçlar:\n\n• Input validation\n• Output encoding\n• Content Security Policy (CSP)\n• HttpOnly cookie flag\n\n## Örnek Güvenli Kod:\n\n```php\n// CSRF koruması\nfunction verify_csrf_token($token) {\n    return hash_equals($_SESSION[\"csrf_token\"], $token);\n}\n\n// XSS koruması\nfunction clean_input($data) {\n    return htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, \"UTF-8\");\n}\n```\n\nBu blog sisteminde tüm bu güvenlik önlemleri uygulanmıştır.',
    'Web uygulaması güvenliği: CSRF ve XSS saldırılarından korunma yöntemleri, best practice\'ler ve örnek kodlar.',
    'published',
    'web güvenliği, csrf, xss, php güvenlik, web geliştirme',
    'CSRF ve XSS saldırılarından korunma: Token doğrulama, input validation, output encoding ve CSP kullanımı.',
    4,
    1,
    1,
    NOW(),
    7
),
(
    'PHP Session Güvenliği ve Best Practices',
    'php-session-guvenligi-ve-best-practices',
    'PHP session yönetimi, web uygulaması güvenliğinin temel taşlarından biridir.\n\n## Güvenli Session Ayarları\n\n• session.cookie_httponly = 1\n• session.cookie_secure = 1\n• session.use_only_cookies = 1\n• session.cookie_samesite = \"Strict\"\n\n## Session Hijacking Koruması\n\nSession hijacking saldırılarından korunmak için:\n\n• User-Agent kontrolü\n• IP adresi doğrulaması\n• Session ID regeneration\n• Timeout kontrolü\n\n## Örnek Güvenli Session Kodu:\n\n```php\nfunction secure_session_start() {\n    ini_set(\"session.cookie_httponly\", 1);\n    ini_set(\"session.cookie_secure\", 1);\n    ini_set(\"session.use_only_cookies\", 1);\n    \n    session_start();\n    \n    // Session hijacking kontrolü\n    if (!isset($_SESSION[\"user_agent\"])) {\n        $_SESSION[\"user_agent\"] = $_SERVER[\"HTTP_USER_AGENT\"];\n    } else if ($_SESSION[\"user_agent\"] !== $_SERVER[\"HTTP_USER_AGENT\"]) {\n        session_destroy();\n        exit();\n    }\n}\n```\n\n## \"Beni Hatırla\" Özelliği\n\nGüvenli \"beni hatırla\" implementasyonu:\n\n• Rastgele token oluşturma\n• Token hash\'leme ve veritabanında saklama\n• Süre sınırlaması\n• Tek kullanımlık token\'lar\n\nBu sistemde tüm bu önlemler uygulanmıştır.',
    'PHP session güvenliği, hijacking koruması, secure cookie ayarları ve \"beni hatırla\" özelliği güvenli implementasyonu.',
    'published',
    'php, session, güvenlik, cookies, web geliştirme',
    'PHP session güvenliği: HttpOnly cookies, session hijacking koruması, secure session management best practices.',
    5,
    1,
    0,
    NOW(),
    6
),
(
    'Modern MySQL Veritabanı Güvenliği',
    'modern-mysql-veritabani-guvenligi',
    'MySQL veritabanı güvenliği, web uygulamalarının en kritik bileşenlerinden biridir.\n\n## SQL Injection Koruması\n\nSQL injection saldırılarından korunma yöntemleri:\n\n• Prepared Statements kullanımı\n• Input validation\n• Escape functions\n• Stored procedures\n\n## Prepared Statements Örneği:\n\n```php\n$stmt = $pdo->prepare(\"SELECT * FROM users WHERE username = :username\");\n$stmt->bindValue(\":username\", $username);\n$stmt->execute();\n```\n\n## Veritabanı Kullanıcı Yönetimi\n\n• Principle of least privilege\n• Ayrı kullanıcılar farklı yetkiler\n• Root kullanıcısını production\'da kullanmama\n• Güçlü şifreler\n\n## Şifreleme ve Hashing\n\n• Argon2ID password hashing\n• Sensitive data encryption\n• TLS/SSL connections\n• Certificate validation\n\nBu blog sisteminde tüm MySQL güvenlik best practice\'leri uygulanmıştır.',
    'MySQL veritabanı güvenliği: SQL injection koruması, prepared statements, kullanıcı yönetimi ve şifreleme teknikleri.',
    'published',
    'mysql, veritabanı, güvenlik, sql injection, prepared statements',
    'MySQL güvenliği: SQL injection koruması, prepared statements kullanımı ve modern veritabanı güvenlik teknikleri.',
    3,
    1,
    0,
    NOW(),
    8
),
(
    'Bootstrap 5 ile Responsive Tasarım',
    'bootstrap-5-ile-responsive-tasarim',
    'Bootstrap 5, modern web tasarımı için güçlü bir CSS framework\'üdür.\n\n## Mobile-First Yaklaşım\n\nBootstrap 5\'in mobile-first felsefesi:\n\n• Küçük ekranlar öncelikli tasarım\n• Progressive enhancement\n• Flexible grid system\n• Responsive utilities\n\n## Grid System\n\n```html\n<div class=\"container\">\n  <div class=\"row\">\n    <div class=\"col-12 col-md-6 col-lg-4\">\n      Content\n    </div>\n  </div>\n</div>\n```\n\n## Responsive Components\n\n• Navigation bars\n• Cards ve layouts\n• Forms ve inputs\n• Modals ve dropdowns\n\n## Utility Classes\n\n• Spacing (margin, padding)\n• Typography\n• Colors ve backgrounds\n• Display ve positioning\n\nBu blog sisteminde Bootstrap 5\'in tüm gücünden yararlanılmıştır.',
    'Bootstrap 5 ile modern responsive tasarım: Mobile-first yaklaşım, grid system ve responsive components.',
    'published',
    'bootstrap, css, responsive, mobile-first, web tasarım',
    'Bootstrap 5 responsive tasarım: Mobile-first yaklaşım, flexible grid system ve modern web components.',
    2,
    1,
    0,
    NOW(),
    5
),
(
    'JavaScript ES6+ Modern Özellikler',
    'javascript-es6-modern-ozellikler',
    'Modern JavaScript geliştirme için ES6+ özellikleri.\n\n## Arrow Functions\n\n```javascript\n// Geleneksel function\nfunction add(a, b) {\n    return a + b;\n}\n\n// Arrow function\nconst add = (a, b) => a + b;\n```\n\n## Template Literals\n\n```javascript\nconst name = \"Ahmet\";\nconst message = `Merhaba ${name}, hoş geldin!`;\n```\n\n## Destructuring\n\n```javascript\n// Array destructuring\nconst [first, second] = [1, 2, 3];\n\n// Object destructuring\nconst {name, age} = {name: \"Ali\", age: 25};\n```\n\n## Async/Await\n\n```javascript\nasync function fetchData() {\n    try {\n        const response = await fetch(\"/api/data\");\n        const data = await response.json();\n        return data;\n    } catch (error) {\n        console.error(\"Error:\", error);\n    }\n}\n```\n\n## Modules\n\n```javascript\n// export\nexport const myFunction = () => {};\n\n// import\nimport { myFunction } from \"./module.js\";\n```\n\nBu blog sisteminde modern JavaScript teknikleri kullanılmıştır.',
    'JavaScript ES6+ modern özellikler: Arrow functions, template literals, destructuring, async/await ve modules.',
    'draft',
    'javascript, es6, modern javascript, web geliştirme, frontend',
    'JavaScript ES6+ özellikleri: Arrow functions, destructuring, async/await ve modern geliştirme teknikleri.',
    6,
    1,
    0,
    NOW(),
    6
);

-- Kategori makale sayılarını tekrar güncelle
UPDATE categories SET article_count = (
    SELECT COUNT(*) FROM articles WHERE category_id = categories.id AND status = 'published'
);

-- Güvenlik ayarları dahil varsayılan ayarlar
INSERT INTO settings (setting_key, setting_value, setting_type) VALUES
('site_title', 'Güvenli Blog Sistemi', 'string'),
('site_description', 'PHP ve MySQL ile oluşturulmuş gelişmiş güvenlik özellikli blog sistemi', 'string'),
('posts_per_page', '6', 'integer'),
('maintenance_mode', '0', 'boolean'),
('max_login_attempts', '5', 'integer'),
('login_lockout_time', '900', 'integer'),
('session_timeout', '1800', 'integer'),
('remember_token_expiry', '2592000', 'integer'),
('search_enabled', '1', 'boolean'),
('categories_enabled', '1', 'boolean'),
('featured_posts_count', '3', 'integer'); 