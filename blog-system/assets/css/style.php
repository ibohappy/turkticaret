<?php
// Hata raporlamayı aktif et
error_reporting(E_ALL);
ini_set('display_errors', 1);

// CSS dosyasının yolunu tanımla
$css_file = __DIR__ . '/style.core.css';

// CSS dosyasının son değiştirilme zamanını al
$last_modified_time = filemtime($css_file);

// Cache kontrolü için headerları ayarla
header("Content-type: text/css; charset=UTF-8");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Last-Modified: " . gmdate("D, d M Y H:i:s", $last_modified_time) . " GMT");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

// ETag oluştur
$etag = '"' . md5($last_modified_time . $css_file) . '"';
header("ETag: " . $etag);

// Tarayıcının gönderdiği If-Modified-Since ve If-None-Match headerlarını kontrol et
$if_modified_since = isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) ? 
    strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) : false;
$if_none_match = isset($_SERVER['HTTP_IF_NONE_MATCH']) ? 
    stripslashes($_SERVER['HTTP_IF_NONE_MATCH']) : false;

// Dosya değişmediyse 304 döndür
if (($if_modified_since && $if_modified_since >= $last_modified_time) ||
    ($if_none_match && $if_none_match == $etag)) {
    header("HTTP/1.1 304 Not Modified");
    exit;
}

// CSS dosyasını oku ve çıktıla
if (file_exists($css_file)) {
    $css_content = file_get_contents($css_file);
    // Google Fonts için versiyon ekle
    $css_content = str_replace(
        'fonts.googleapis.com/css2?family=Inter',
        'fonts.googleapis.com/css2?family=Inter&v=' . $last_modified_time,
        $css_content
    );
    echo $css_content;
} else {
    header("HTTP/1.1 404 Not Found");
    echo "/* CSS dosyası bulunamadı */";
}
?>
/* Google Fonts Import */
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap<?php echo $version; ?>');

/* Genel stil ayarları - Profesyonel Beyaz-Yeşil Tema */
:root {
    /* Ana Renkler */
    --primary-green: #059669;
    --secondary-green: #10b981;
    --light-green: #d1fae5;
    --dark-green: #047857;
    --white: #ffffff;
    --light-gray: #f8fafc;
    --dark-gray: #1f2937;
    
    /* Gradyanlar */
    --primary-gradient: linear-gradient(135deg, var(--primary-green) 0%, var(--secondary-green) 100%);
    --hero-gradient: linear-gradient(to right, var(--white) 0%, var(--light-green) 100%);
    --card-hover-shadow: 0 15px 35px rgba(5, 150, 105, 0.1), 0 5px 15px rgba(16, 185, 129, 0.07);
}

/* Temel Stiller */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    background-color: var(--white);
    color: var(--dark-gray);
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    line-height: 1.5;
}

/* Hero Section */
.hero-section {
    background: var(--light-gray);
    color: var(--dark-gray);
    padding: 80px 0 60px;
    position: relative;
    overflow: hidden;
    border-bottom: 1px solid rgba(5, 150, 105, 0.1);
}

.hero-section::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: var(--hero-gradient);
    opacity: 0.8;
}

.hero-content {
    position: relative;
    z-index: 2;
}

.hero-content h1 {
    color: var(--primary-green);
    font-size: 3.5rem;
    font-weight: 800;
    margin-bottom: 1.5rem;
    letter-spacing: -0.025em;
}

.hero-content .lead {
    color: var(--dark-gray);
    font-size: 1.25rem;
    font-weight: 500;
    opacity: 0.9;
}

/* Kartlar */
.article-card {
    background: var(--white);
    border-radius: var(--border-radius-lg);
    overflow: hidden;
    transition: var(--transition);
    height: 100%;
    border: 1px solid rgba(5, 150, 105, 0.1);
    box-shadow: var(--box-shadow);
}

.article-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--card-hover-shadow);
}

/* Butonlar */
.btn-primary {
    background: var(--primary-green);
    border: none;
    border-radius: var(--border-radius);
    padding: 0.75rem 1.5rem;
    font-weight: 600;
    color: var(--white);
    transition: var(--transition);
}

.btn-primary:hover {
    background: var(--dark-green);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(5, 150, 105, 0.2);
}

/* Footer */
footer {
    background: var(--light-gray);
    border-top: 1px solid rgba(5, 150, 105, 0.1);
    padding: 3rem 0;
    margin-top: 4rem;
    color: var(--dark-gray);
}

/* Responsive */
@media (max-width: 768px) {
    .hero-content h1 {
        font-size: 2.5rem;
    }
    
    .search-section {
        margin: -2rem 1rem 2rem;
        padding: 1.5rem;
    }
}

/* Animasyonlar */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

.hero-content, .search-section, .article-card {
    animation: fadeIn 0.6s ease-out forwards;
} 