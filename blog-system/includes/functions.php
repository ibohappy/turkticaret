<?php
// Temel dizin yollarını tanımla
define('BASE_DIR', realpath(__DIR__ . '/..'));
define('INCLUDES_DIR', BASE_DIR . '/includes');
define('CONFIG_DIR', BASE_DIR . '/config');
define('ASSETS_DIR', BASE_DIR . '/assets');

// URL yollarını tanımla
define('BASE_URL', '/blog-system');
define('ASSETS_URL', BASE_URL . '/assets');

// Gerekli dosyaları dahil et
require_once CONFIG_DIR . '/database.php';

// Güvenli oturum başlatma
function secure_session_start() {
    if (session_status() == PHP_SESSION_NONE) {
        // Güvenli session ayarları
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_secure', 1);
        ini_set('session.use_only_cookies', 1);
        ini_set('session.cookie_samesite', 'Strict');
        
        // Session timeout (30 dakika)
        ini_set('session.gc_maxlifetime', 1800);
        
        session_start();
        
        // Session ID güvenlik yenileme
        if (!isset($_SESSION['initiated'])) {
            session_regenerate_id(true);
            $_SESSION['initiated'] = true;
        }
        
        // Session hijacking koruması
        if (!isset($_SESSION['user_agent'])) {
            $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
        } else if ($_SESSION['user_agent'] !== $_SERVER['HTTP_USER_AGENT']) {
            session_destroy();
            header('Location: /blog-system/admin/login.php');
            exit();
        }
        
        // Session timeout kontrolü
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
            session_destroy();
            header('Location: /blog-system/admin/login.php?timeout=1');
            exit();
        }
        $_SESSION['last_activity'] = time();
    }
}

// CSRF Token oluştur
function generate_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// CSRF Token doğrula
function verify_csrf_token($token) {
    if (!isset($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

// CSRF Hidden Input
function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . generate_csrf_token() . '">';
}

// Güvenli string fonksiyonu
function clean_input($data) {
    if (is_array($data)) {
        return array_map('clean_input', $data);
    }
    
    $data = trim($data);
    $data = stripslashes($data);
    
    // XSS koruması için HTML özel karakterleri encode et
    $data = htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    
    // Zararlı karakterleri filtrele
    $data = preg_replace('/[<>"\']/', '', $data);
    
    return $data;
}

// Güvenli HTML içerik temizleme (editör içeriği için)
function clean_html_content($data) {
    // İzin verilen HTML etiketleri
    $allowed_tags = '<p><br><strong><em><u><ol><ul><li><h1><h2><h3><h4><h5><h6><blockquote><a><img>';
    
    $data = strip_tags($data, $allowed_tags);
    
    // Script etiketlerini tamamen kaldır
    $data = preg_replace('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi', '', $data);
    
    // On* event handler'ları kaldır
    $data = preg_replace('/\son\w+\s*=\s*["\'][^"\']*["\']/i', '', $data);
    
    // Javascript: protokolünü kaldır
    $data = preg_replace('/javascript:/i', '', $data);
    
    return $data;
}

// Form validasyon fonksiyonları
class FormValidator {
    
    // Makale başlığı validasyonu
    public static function validate_title($title) {
        $errors = [];
        
        if (empty($title)) {
            $errors[] = 'Makale başlığı boş olamaz.';
        } elseif (strlen($title) < 5) {
            $errors[] = 'Makale başlığı en az 5 karakter olmalıdır.';
        } elseif (strlen($title) > 255) {
            $errors[] = 'Makale başlığı en fazla 255 karakter olabilir.';
        }
        
        return $errors;
    }
    
    // Makale içeriği validasyonu
    public static function validate_content($content) {
        $errors = [];
        
        if (empty($content)) {
            $errors[] = 'Makale içeriği boş olamaz.';
        } elseif (strlen(strip_tags($content)) < 50) {
            $errors[] = 'Makale içeriği en az 50 karakter olmalıdır.';
        }
        
        return $errors;
    }
    
    // Yazar validasyonu
    public static function validate_author($author) {
        $errors = [];
        
        if (empty($author)) {
            $errors[] = 'Yazar adı boş olamaz.';
        } elseif (strlen($author) < 2) {
            $errors[] = 'Yazar adı en az 2 karakter olmalıdır.';
        } elseif (strlen($author) > 100) {
            $errors[] = 'Yazar adı en fazla 100 karakter olabilir.';
        }
        
        return $errors;
    }
    
    // Email validasyonu
    public static function validate_email($email) {
        $errors = [];
        
        if (empty($email)) {
            $errors[] = 'Email adresi boş olamaz.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Geçerli bir email adresi girin.';
        }
        
        return $errors;
    }
    
    // Şifre validasyonu
    public static function validate_password($password) {
        $errors = [];
        
        if (empty($password)) {
            $errors[] = 'Şifre boş olamaz.';
        } elseif (strlen($password) < 8) {
            $errors[] = 'Şifre en az 8 karakter olmalıdır.';
        } elseif (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Şifre en az bir büyük harf içermelidir.';
        } elseif (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'Şifre en az bir küçük harf içermelidir.';
        } elseif (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'Şifre en az bir rakam içermelidir.';
        } elseif (!preg_match('/[^a-zA-Z0-9]/', $password)) {
            $errors[] = 'Şifre en az bir özel karakter içermelidir.';
        }
        
        return $errors;
    }
}

// Güvenli şifre hash'leme
function hash_password($password) {
    return password_hash($password, PASSWORD_ARGON2ID, [
        'memory_cost' => 65536, // 64 MB
        'time_cost' => 4,       // 4 iterasyon
        'threads' => 3          // 3 thread
    ]);
}

// Şifre doğrulama
function verify_password($password, $hash) {
    return password_verify($password, $hash);
}

// "Beni Hatırla" token oluştur
function generate_remember_token() {
    return bin2hex(random_bytes(32));
}

// Remember token hash'le
function hash_remember_token($token) {
    return hash('sha256', $token);
}

// Güvenli logout
function secure_logout() {
    // Session verilerini temizle
    $_SESSION = array();
    
    // Session cookie'sini sil
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Remember me cookie'sini sil
    if (isset($_COOKIE['remember_token'])) {
        setcookie('remember_token', '', time() - 3600, '/', '', true, true);
    }
    
    // Session'ı yok et
    session_destroy();
}

// IP adresi al
function get_client_ip() {
    $ip_keys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
    foreach ($ip_keys as $key) {
        if (array_key_exists($key, $_SERVER) === true) {
            foreach (explode(',', $_SERVER[$key]) as $ip) {
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                    return $ip;
                }
            }
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

// Gelişmiş rate limiting kontrol sistemi
function check_rate_limit($action, $limit = 5, $time_window = 300) {
    $ip = get_client_ip();
    $key = $action . '_' . $ip;
    
    if (!isset($_SESSION['rate_limit'])) {
        $_SESSION['rate_limit'] = [];
    }
    
    $now = time();
    
    // Eski kayıtları temizle
    if (isset($_SESSION['rate_limit'][$key])) {
        $_SESSION['rate_limit'][$key] = array_filter($_SESSION['rate_limit'][$key], function($time) use ($now, $time_window) {
            return ($now - $time) < $time_window;
        });
    } else {
        $_SESSION['rate_limit'][$key] = [];
    }
    
    // Limit kontrolü
    if (count($_SESSION['rate_limit'][$key]) >= $limit) {
        // Saldırgan IP'yi logla
        error_log("Rate limit exceeded for IP: $ip, Action: $action");
        return false;
    }
    
    // Yeni girişimi kaydet
    $_SESSION['rate_limit'][$key][] = $now;
    return true;
}

// Rate limiting kalan süresini hesapla
function get_rate_limit_remaining_time($action, $limit = 5, $time_window = 300) {
    $ip = get_client_ip();
    $key = $action . '_' . $ip;
    
    if (!isset($_SESSION['rate_limit']) || !isset($_SESSION['rate_limit'][$key])) {
        return 0;
    }
    
    $now = time();
    
    // Eski kayıtları temizle
    $_SESSION['rate_limit'][$key] = array_filter($_SESSION['rate_limit'][$key], function($time) use ($now, $time_window) {
        return ($now - $time) < $time_window;
    });
    
    // Eğer limit aşılmışsa, en eski girişimden itibaren kalan süreyi hesapla
    if (count($_SESSION['rate_limit'][$key]) >= $limit) {
        $oldest_attempt = min($_SESSION['rate_limit'][$key]);
        $remaining_seconds = $time_window - ($now - $oldest_attempt);
        return max(0, $remaining_seconds);
    }
    
    return 0;
}

// Süreyi dakika:saniye formatında göster
function format_remaining_time($seconds) {
    if ($seconds <= 0) {
        return "0:00";
    }
    
    $minutes = floor($seconds / 60);
    $seconds = $seconds % 60;
    
    return sprintf("%d:%02d", $minutes, $seconds);
}

// IP bazlı güvenlik kontrolü
function is_ip_blocked($ip) {
    // Kara listedeki IP'leri kontrol et
    $blocked_ips = [
        // Bilinen saldırgan IP'ler eklenebilir
    ];
    
    return in_array($ip, $blocked_ips);
}

// Güvenli dosya indirme kontrolü
function secure_download($file_path, $file_name) {
    // Dosya varlığı kontrolü
    if (!file_exists($file_path)) {
        http_response_code(404);
        die('Dosya bulunamadı');
    }
    
    // Path traversal saldırısı kontrolü
    $real_path = realpath($file_path);
    $allowed_dir = realpath(dirname(__FILE__) . '/../assets/');
    
    if (strpos($real_path, $allowed_dir) !== 0) {
        http_response_code(403);
        die('Yetkisiz erişim');
    }
    
    // Güvenli download
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($file_name) . '"');
    header('Content-Length: ' . filesize($file_path));
    
    readfile($file_path);
    exit();
}

// Tarih formatla
function format_date($date) {
    // Tarih kontrolü - NULL, boş veya 1970 tarihi ise
    if (empty($date) || $date === '0000-00-00 00:00:00' || strtotime($date) <= 0) {
        return date('d.m.Y H:i'); // Şu anki zaman
    }
    
    $timestamp = strtotime($date);
    if ($timestamp === false || $timestamp <= 0) {
        return date('d.m.Y H:i'); // Geçersiz tarih ise şu anki zaman
    }
    
    return date('d.m.Y H:i', $timestamp);
}

// Özet oluştur
function create_excerpt($text, $limit = 200) {
    $text = strip_tags($text); // HTML etiketlerini kaldır
    if (strlen($text) > $limit) {
        return substr($text, 0, $limit) . '...';
    }
    return $text;
}

// Gelişmiş admin kontrolü
function check_admin() {
    secure_session_start();
    
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
        exit();
    }
    
    // Session timeout kontrolü zaten secure_session_start() içinde yapılıyor
}

// SEO dostu URL oluştur
function create_slug($text) {
    // Türkçe karakterleri değiştir
    $tr = array('ş','Ş','ı','I','İ','ğ','Ğ','ü','Ü','ö','Ö','Ç','ç');
    $en = array('s','S','i','I','I','g','G','u','U','o','O','C','c');
    $text = str_replace($tr, $en, $text);
    
    // Küçük harfe çevir ve özel karakterleri temizle
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
    $text = preg_replace('/[\s-]+/', '-', $text);
    $text = trim($text, '-');
    
    return $text;
}

// Benzersiz dosya adı oluştur
function generate_unique_filename($original_name) {
    $extension = pathinfo($original_name, PATHINFO_EXTENSION);
    $filename = pathinfo($original_name, PATHINFO_FILENAME);
    
    // Dosya adını temizle
    $filename = preg_replace('/[^a-zA-Z0-9-_]/', '_', $filename);
    $filename = preg_replace('/_+/', '_', $filename);
    $filename = trim($filename, '_');
    
    // Benzersiz ID ekle
    $unique_id = uniqid();
    $timestamp = time();
    
    return $filename . '_' . $timestamp . '_' . $unique_id . '.' . strtolower($extension);
}

// Flash mesaj göster
function show_message($type, $message) {
    return "<div class='alert alert-{$type} alert-dismissible fade show' role='alert'>
                " . htmlspecialchars($message) . "
                <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
            </div>";
}

// Güvenli dosya yükleme
function secure_file_upload($file, $allowed_types, $max_size, $upload_dir, $new_name = null) {
    $errors = [];
    
    // Dosya kontrolü
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Dosya yükleme hatası.';
        return ['success' => false, 'errors' => $errors];
    }
    
    // Dosya boyutu kontrolü
    if ($file['size'] > $max_size) {
        $errors[] = 'Dosya çok büyük. Maximum ' . ($max_size / 1024 / 1024) . 'MB olmalıdır.';
    }
    
    // MIME type kontrolü
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mime_type, $allowed_types)) {
        $errors[] = 'Geçersiz dosya türü.';
    }
    
    // Dosya uzantısı kontrolü
    $allowed_extensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp'
    ];
    
    if (!isset($allowed_extensions[$mime_type])) {
        $errors[] = 'Desteklenmeyen dosya uzantısı.';
    }
    
    if (!empty($errors)) {
        return ['success' => false, 'errors' => $errors];
    }
    
    // Dosya adı oluştur
    if ($new_name) {
        $filename = $new_name . '.' . $allowed_extensions[$mime_type];
    } else {
        $filename = uniqid() . '.' . $allowed_extensions[$mime_type];
    }
    
    $upload_path = $upload_dir . '/' . $filename;
    
    // Dosyayı taşı
    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
        return ['success' => true, 'filename' => $filename];
    } else {
        return ['success' => false, 'errors' => ['Dosya yüklenirken hata oluştu.']];
    }
}

// Content Security Policy için güvenlik başlıkları
function set_security_headers() {
    // XSS koruması
    header("X-XSS-Protection: 1; mode=block");
    
    // Content type sniffing koruması
    header("X-Content-Type-Options: nosniff");
    
    // Clickjacking koruması
    header("X-Frame-Options: DENY");
    
    // HTTPS yönlendirmesi (production için)
    header("Strict-Transport-Security: max-age=31536000; includeSubDomains");
    
    // Content Security Policy
    $csp = "default-src 'self'; ";
    $csp .= "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; ";
    $csp .= "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com; ";
    $csp .= "font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; ";
    $csp .= "img-src 'self' data: https:; ";
    $csp .= "connect-src 'self';";
    
    header("Content-Security-Policy: " . $csp);
}
?> 