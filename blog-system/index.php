<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Session başlat
session_start();

// Gerekli dosyaları dahil et
if (!file_exists('config/database.php') || !file_exists('includes/functions.php')) {
    die('Gerekli sistem dosyaları bulunamadı.');
}

require_once 'config/database.php';
require_once 'includes/functions.php';

// Güvenlik başlıklarını ayarla
set_security_headers();

// Sayfa parametreleri
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$category = isset($_GET['category']) ? clean_input($_GET['category']) : '';
$search = isset($_GET['search']) ? clean_input($_GET['search']) : '';
$posts_per_page = 6;
$offset = ($page - 1) * $posts_per_page;

// WHERE koşulları oluştur
$where_conditions = ["a.status = 'published'"];
$params = [];

if ($category) {
    $where_conditions[] = "c.slug = :category";
    $params[':category'] = $category;
}

if (!empty($search)) {
    // Gelişmiş arama motoru kullan
    require_once 'search_engine_fixed.php';
    $search_engine = new AdvancedSearchEngine($pdo);
    
    $search_result = $search_engine->search($search, [
        'page' => $page,
        'posts_per_page' => $posts_per_page
    ]);
    
    $articles = $search_result['results'];
    $total_articles = $search_result['total_results'];
    $total_pages = $search_result['total_pages'];
    $is_search = true;
    
    // Arama durumunda da kategorileri ve diğer değişkenleri tanımla
    $categories = [];
    $featured_articles = [];
    $site_settings = [];
    
    try {
        // Kategorileri getir
        $categories_query = "SELECT c.*, COUNT(a.id) as article_count 
                            FROM categories c
                            LEFT JOIN articles a ON c.id = a.category_id AND a.status = 'published'
                            WHERE c.is_active = 1
                            GROUP BY c.id, c.name, c.slug, c.color, c.icon
                            ORDER BY c.sort_order, c.name";
        $categories_stmt = $pdo->prepare($categories_query);
        $categories_stmt->execute();
        $categories = $categories_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Site ayarlarını getir
        $settings_query = "SELECT setting_key, setting_value FROM settings";
        $settings_stmt = $pdo->prepare($settings_query);
        $settings_stmt->execute();
        while ($setting = $settings_stmt->fetch(PDO::FETCH_ASSOC)) {
            $site_settings[$setting['setting_key']] = $setting['setting_value'];
        }
    } catch (PDOException $e) {
        error_log("Search page error: " . $e->getMessage());
        $categories = [];
        $site_settings = [];
    }
} else {
    $where_clause = implode(' AND ', $where_conditions);

    try {
        // Toplam makale sayısını al
        $count_query = "SELECT COUNT(*) as total 
                        FROM articles a 
                        LEFT JOIN categories c ON a.category_id = c.id 
                        WHERE {$where_clause}";
        $count_stmt = $pdo->prepare($count_query);
        foreach ($params as $key => $value) {
            $count_stmt->bindValue($key, $value);
        }
        $count_stmt->execute();
        $total_articles = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Toplam sayfa sayısı
        $total_pages = ceil($total_articles / $posts_per_page);
        
        // Makaleleri getir
        $query = "SELECT a.*, c.name as category_name, c.slug as category_slug, c.color as category_color, c.icon as category_icon,
                         au.full_name as author_name
                  FROM articles a 
                  LEFT JOIN categories c ON a.category_id = c.id 
                  LEFT JOIN admin_users au ON a.author_id = au.id
                  WHERE {$where_clause}
                  ORDER BY a.is_featured DESC, a.published_at DESC 
                  LIMIT :limit OFFSET :offset";
        
        $stmt = $pdo->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $posts_per_page, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $articles = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Kategorileri ve makale sayılarını getir
        $categories_query = "SELECT c.*, COUNT(a.id) as article_count 
                            FROM categories c
                            LEFT JOIN articles a ON c.id = a.category_id AND a.status = 'published'
                            WHERE c.is_active = 1
                            GROUP BY c.id, c.name, c.slug, c.color, c.icon
                            ORDER BY c.sort_order, c.name";
        $categories_stmt = $pdo->prepare($categories_query);
        $categories_stmt->execute();
        $categories = $categories_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Öne çıkan makaleler (sadece ana sayfada)
        $featured_articles = [];
        if ($page == 1 && !$category && !$search) {
            $featured_query = "SELECT a.*, c.name as category_name, c.slug as category_slug, c.color as category_color, c.icon as category_icon,
                                      au.full_name as author_name
                               FROM articles a 
                               LEFT JOIN categories c ON a.category_id = c.id 
                               LEFT JOIN admin_users au ON a.author_id = au.id
                               WHERE a.status = 'published' AND a.is_featured = 1
                               ORDER BY a.published_at DESC 
                               LIMIT 3";
            $featured_stmt = $pdo->prepare($featured_query);
            $featured_stmt->execute();
            $featured_articles = $featured_stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        // Site ayarlarını getir
        $settings_query = "SELECT setting_key, setting_value FROM settings";
        $settings_stmt = $pdo->prepare($settings_query);
        $settings_stmt->execute();
        $site_settings = [];
        while ($setting = $settings_stmt->fetch(PDO::FETCH_ASSOC)) {
            $site_settings[$setting['setting_key']] = $setting['setting_value'];
        }
        
    } catch (PDOException $e) {
        error_log("Index page error: " . $e->getMessage());
        $articles = [];
        $featured_articles = [];
        $categories = [];
        $total_articles = 0;
        $total_pages = 0;
    }
}

// Güvenlik kontrolü - değişkenlerin tanımlı olduğundan emin ol
$categories = $categories ?? [];
$featured_articles = $featured_articles ?? [];
$site_settings = $site_settings ?? [];
$articles = $articles ?? [];
$total_articles = $total_articles ?? 0;
$total_pages = $total_pages ?? 0;
$is_search = $is_search ?? false;

$page_title = 'Ana Sayfa';
if ($category) {
    $page_title = 'Kategori: ' . ucwords(str_replace('-', ' ', $category));
}
if ($search) {
    $page_title = 'Arama: ' . $search;
}

$site_title = $site_settings['site_title'] ?? 'Güvenli Blog Sistemi';
$site_description = $site_settings['site_description'] ?? 'Modern Blog Sistemi';
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title . ' - ' . $site_title); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($site_description); ?>">
    
    <!-- SEO Meta Tags -->
    <meta property="og:title" content="<?php echo htmlspecialchars($page_title . ' - ' . $site_title); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($site_description); ?>">
    <meta property="og:type" content="website">
    
    <!-- Cache Busting -->
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css?v=<?php echo time(); ?>" rel="stylesheet">
    <link href="assets/css/mobile-first.css?v=<?php echo time(); ?>" rel="stylesheet">
    
    <style>
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
        
        body {
            background-color: var(--white);
            color: var(--dark-gray);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
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
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--primary-green);
            display: block;
            margin-bottom: 0.5rem;
        }
        
        .search-section {
            background: var(--white);
            border-radius: 1rem;
            padding: 2rem;
            margin: -3rem auto 3rem;
            position: relative;
            z-index: 3;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            border: 1px solid rgba(5, 150, 105, 0.1);
        }
        
        .search-input {
            border: 2px solid var(--light-green);
            border-radius: 0.75rem;
            padding: 1rem 1.5rem;
            transition: all 0.3s ease;
            background: var(--white);
            font-size: 1rem;
        }
        
        .search-input:focus {
            border-color: var(--primary-green);
            box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.1);
            outline: none;
        }
        
        .btn-primary {
            background: var(--primary-green);
            border: none;
            border-radius: 0.75rem;
            padding: 1rem 2rem;
            font-weight: 600;
            color: var(--white);
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            background: var(--dark-green);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(5, 150, 105, 0.2);
        }
        
        .category-filter {
            background: var(--white);
            border-radius: 1rem;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
            border: 1px solid rgba(5, 150, 105, 0.1);
        }
        
        .category-btn {
            background: var(--light-green);
            color: var(--primary-green);
            border: none;
            border-radius: 0.75rem;
            padding: 0.75rem 1.5rem;
            margin: 0.25rem;
            transition: all 0.3s ease;
            text-decoration: none;
            font-weight: 500;
        }
        
        .category-btn:hover, 
        .category-btn.active {
            background: var(--primary-green);
            color: var(--white);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(5, 150, 105, 0.2);
        }
        
        .article-card {
            background: var(--white);
            border-radius: 1rem;
            overflow: hidden;
            transition: all 0.3s ease;
            height: 100%;
            border: 1px solid rgba(5, 150, 105, 0.1);
        }
        
        .article-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--card-hover-shadow);
        }
        
        .article-image {
            height: 200px;
            background: var(--light-gray);
            position: relative;
            overflow: hidden;
        }
        
        .article-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }
        
        .category-badge {
            position: absolute;
            top: 1rem;
            left: 1rem;
            background: var(--primary-green);
            color: var(--white);
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            font-weight: 600;
            z-index: 2;
        }
        
        .featured-badge {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: var(--secondary-green);
            color: var(--white);
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            font-weight: 600;
            z-index: 2;
        }
        
        .card-body {
            padding: 1.5rem;
        }
        
        .card-title {
            color: var(--dark-gray);
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 1rem;
            line-height: 1.4;
        }
        
        .card-title a {
            color: var(--dark-gray);
            text-decoration: none;
            transition: color 0.3s ease;
        }
        
        .card-title a:hover {
            color: var(--primary-green);
        }
        
        .pagination .page-link {
            color: var(--primary-green);
            border: 1px solid var(--light-green);
            margin: 0 0.25rem;
            border-radius: 0.5rem;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }
        
        .pagination .page-item.active .page-link {
            background: var(--primary-green);
            border-color: var(--primary-green);
            color: var(--white);
        }
        
        .pagination .page-link:hover {
            background: var(--light-green);
            transform: translateY(-2px);
        }
        
        footer {
            background: var(--light-gray);
            border-top: 1px solid rgba(5, 150, 105, 0.1);
            padding: 3rem 0;
            margin-top: 4rem;
        }
        
        /* Animasyonlar */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .hero-content, .search-section, .article-card {
            animation: fadeIn 0.6s ease-out forwards;
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
            
            .category-filter {
                padding: 1rem;
            }
            
            .category-btn {
                padding: 0.5rem 1rem;
                font-size: 0.875rem;
            }
        }
    </style>
</head>
<body>
    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <div class="hero-content text-center">
                <h1 class="display-4 fw-bold mb-3">
                    <i class="fas fa-shield-alt"></i> <?php echo htmlspecialchars($site_title); ?>
                </h1>
                <p class="lead mb-4"><?php echo htmlspecialchars($site_description); ?></p>
                <div class="row">
                    <div class="col-md-4">
                        <div class="stat-item">
                            <span class="stat-number"><?php echo number_format($total_articles); ?></span>
                            <span>Toplam Makale</span>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-item">
                            <span class="stat-number"><?php echo count($categories); ?></span>
                            <span>Kategori</span>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-item">
                            <span class="stat-number"><i class="fas fa-lock"></i></span>
                            <span>Güvenli Sistem</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Search Section -->
    <div class="container">
        <div class="search-section">
            <form method="GET" class="row g-3 align-items-center">
                <div class="col-md-8">
                    <div class="input-group">
                        <span class="input-group-text bg-transparent border-0">
                            <i class="fas fa-search"></i>
                        </span>
                        <input type="text" class="form-control search-input border-start-0" 
                               name="search" placeholder="Makalelerde ara..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary w-100" style="border-radius: 25px;">
                        <i class="fas fa-search"></i> Ara
                    </button>
                    <div class="mt-2 text-center">
                        <a href="search.php" class="btn btn-link btn-sm text-decoration-none">
                            <i class="fas fa-sliders-h"></i> Gelişmiş Arama
                        </a>
                    </div>
                </div>
                <?php if ($category): ?>
                    <input type="hidden" name="category" value="<?php echo htmlspecialchars($category); ?>">
                <?php endif; ?>
            </form>
        </div>
    </div>

    <div class="container my-5">
        <!-- Category Filter -->
        <div class="container mt-4">
            <div class="row">
                <div class="col-12">
                    <div class="categories-wrapper">
                        <h5 class="categories-title mb-4">
                            <i class="fas fa-tags me-2"></i> Kategoriler
                        </h5>
                        <div class="categories-grid">
                            <a href="index.php" class="category-card <?php echo !$category ? 'active' : ''; ?>">
                                <div class="category-icon">
                                    <i class="fas fa-th-large"></i>
                                </div>
                                <div class="category-info">
                                    <span class="category-name">Tümü</span>
                                    <span class="category-count"><?php echo $total_articles; ?></span>
                                </div>
                            </a>
                            <?php foreach ($categories as $cat): ?>
                                <a href="index.php?category=<?php echo htmlspecialchars($cat['slug']); ?>" 
                                   class="category-card <?php echo $category === $cat['slug'] ? 'active' : ''; ?>"
                                   style="--category-color: <?php echo htmlspecialchars($cat['color']); ?>">
                                    <div class="category-icon">
                                        <i class="<?php echo htmlspecialchars($cat['icon']); ?>"></i>
                                    </div>
                                    <div class="category-info">
                                        <span class="category-name"><?php echo htmlspecialchars($cat['name']); ?></span>
                                        <span class="category-count"><?php echo $cat['article_count']; ?></span>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Featured Articles (only on homepage) -->
        <?php if (!empty($featured_articles)): ?>
            <div class="featured-section">
                <h2 class="mb-4 text-center">
                    <i class="fas fa-star text-warning"></i> Öne Çıkan Makaleler
                </h2>
                <div class="row">
                    <?php foreach ($featured_articles as $article): ?>
                        <div class="col-md-4 mb-4">
                            <div class="featured-card">
                                <div class="article-image">
                                    <?php if ($article['featured_image']): ?>
                                        <img src="assets/images/<?php echo htmlspecialchars($article['featured_image']); ?>" 
                                             alt="<?php echo htmlspecialchars($article['title']); ?>">
                                    <?php else: ?>
                                        <i class="placeholder-icon <?php echo htmlspecialchars($article['category_icon'] ?? 'fas fa-newspaper'); ?>"></i>
                                    <?php endif; ?>
                                    
                                    <?php if (isset($article['category_name']) && $article['category_name']): ?>
                                        <span class="category-badge" style="background: <?php echo htmlspecialchars($article['category_color']); ?>">
                                            <i class="<?php echo htmlspecialchars($article['category_icon']); ?>"></i>
                                            <?php echo htmlspecialchars($article['category_name']); ?>
                                        </span>
                                    <?php endif; ?>
                                    
                                    <span class="featured-badge">
                                        <i class="fas fa-star"></i> ÖNE ÇIKAN
                                    </span>
                                </div>
                                <div class="card-body p-3">
                                    <h6 class="card-title">
                                        <a href="article.php?slug=<?php echo htmlspecialchars($article['slug']); ?>" 
                                           class="text-decoration-none text-dark">
                                            <?php echo htmlspecialchars($article['title']); ?>
                                        </a>
                                    </h6>
                                    <p class="card-text small text-muted">
                                        <?php echo htmlspecialchars(create_excerpt($article['content'], 80)); ?>
                                    </p>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <small class="text-muted reading-time">
                                            <i class="fas fa-clock"></i> <?php echo $article['reading_time']; ?> dk
                                        </small>
                                        <small class="text-muted">
                                            <?php echo format_date($article['published_at']); ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Page Title -->
        <div class="row mb-4">
            <div class="col-md-8">
                <h2>
                    <?php if ($category): ?>
                        <i class="fas fa-folder-open"></i> Kategori: <?php echo ucwords(str_replace('-', ' ', $category)); ?>
                    <?php elseif ($search): ?>
                        <i class="fas fa-search"></i> Arama Sonuçları: "<?php echo htmlspecialchars($search); ?>"
                    <?php else: ?>
                        <i class="fas fa-newspaper"></i> Tüm Makaleler
                    <?php endif; ?>
                </h2>
                <p class="text-muted">
                    <?php echo number_format($total_articles); ?> makale bulundu
                    <?php if ($total_pages > 1): ?>
                        - Sayfa <?php echo $page; ?> / <?php echo $total_pages; ?>
                    <?php endif; ?>
                </p>
            </div>
            <div class="col-md-4 text-end">
                <a href="admin/login.php" class="btn btn-outline-primary">
                    <i class="fas fa-shield-alt"></i> Admin Paneli
                </a>
            </div>
        </div>

        <!-- Articles Grid -->
        <?php if (empty($articles)): ?>
            <div class="text-center py-5">
                <i class="fas fa-search fa-3x text-muted mb-3"></i>
                <h4>Hiç makale bulunamadı</h4>
                <p class="text-muted">
                    <?php if ($search): ?>
                        "<?php echo htmlspecialchars($search); ?>" için arama sonucu bulunamadı.
                    <?php elseif ($category): ?>
                        Bu kategoride henüz makale bulunmuyor.
                    <?php else: ?>
                        Henüz hiç makale eklenmemiş.
                    <?php endif; ?>
                </p>
                <?php if ($search || $category): ?>
                    <a href="?" class="btn btn-primary">
                        <i class="fas fa-home"></i> Ana Sayfaya Dön
                    </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($articles as $article): ?>
                    <div class="col-lg-4 col-md-6 mb-4">
                        <article class="card article-card h-100">
                            <div class="article-image">
                                <?php if ($article['featured_image']): ?>
                                    <img src="assets/images/<?php echo htmlspecialchars($article['featured_image']); ?>" 
                                         alt="<?php echo htmlspecialchars($article['title']); ?>">
                                <?php else: ?>
                                    <i class="placeholder-icon <?php echo htmlspecialchars($article['category_icon'] ?? 'fas fa-newspaper'); ?>"></i>
                                <?php endif; ?>
                                
                                <?php if (isset($article['category_name']) && $article['category_name']): ?>
                                    <span class="category-badge" style="background: <?php echo htmlspecialchars($article['category_color']); ?>">
                                        <i class="<?php echo htmlspecialchars($article['category_icon']); ?>"></i>
                                        <?php echo htmlspecialchars($article['category_name']); ?>
                                    </span>
                                <?php endif; ?>
                                
                                <?php if ($article['is_featured']): ?>
                                    <span class="featured-badge">
                                        <i class="fas fa-star"></i> ÖNE ÇIKAN
                                    </span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="card-body d-flex flex-column">
                                <h5 class="card-title">
                                    <a href="article.php?slug=<?php echo htmlspecialchars($article['slug']); ?>" 
                                       class="text-decoration-none text-dark">
                                        <?php echo htmlspecialchars($article['title']); ?>
                                    </a>
                                </h5>
                                
                                <p class="card-text text-muted flex-grow-1">
                                    <?php echo htmlspecialchars(create_excerpt($article['content'], 120)); ?>
                                </p>
                                
                                <div class="mt-auto">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <small class="text-muted">
                                            <i class="fas fa-user"></i> <?php echo htmlspecialchars($article['author_name'] ?? 'Admin'); ?>
                                        </small>
                                        <small class="text-muted reading-time">
                                            <i class="fas fa-clock"></i> <?php echo $article['reading_time']; ?> dk okuma
                                        </small>
                                    </div>
                                    
                                    <div class="d-flex justify-content-between align-items-center">
                                        <small class="text-muted">
                                            <i class="fas fa-calendar"></i> <?php echo format_date($article['published_at']); ?>
                                        </small>
                                        <small class="text-muted">
                                            <i class="fas fa-eye"></i> <?php echo number_format($article['view_count']); ?> görüntüleme
                                        </small>
                                    </div>
                                    
                                    <?php if ($article['tags']): ?>
                                        <div class="mt-2">
                                            <?php 
                                            $tags = explode(',', $article['tags']);
                                            foreach (array_slice($tags, 0, 3) as $tag): 
                                                $tag = trim($tag);
                                                if ($tag):
                                            ?>
                                                <span class="badge bg-light text-dark me-1">
                                                    <i class="fas fa-tag"></i> <?php echo htmlspecialchars($tag); ?>
                                                </span>
                                            <?php 
                                                endif;
                                            endforeach; 
                                            ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </article>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Advanced Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination-wrapper">
                    <nav aria-label="Sayfa navigasyonu">
                        <ul class="pagination pagination-lg">
                            <!-- İlk sayfa -->
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>">
                                        <i class="fas fa-angle-double-left"></i>
                                    </a>
                                </li>
                                <li class="page-item">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                                        <i class="fas fa-angle-left"></i>
                                    </a>
                                </li>
                            <?php endif; ?>

                            <!-- Sayfa numaraları -->
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>

                            <!-- Son sayfa -->
                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                                        <i class="fas fa-angle-right"></i>
                                    </a>
                                </li>
                                <li class="page-item">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $total_pages])); ?>">
                                        <i class="fas fa-angle-double-right"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <footer class="bg-dark text-white py-4 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5><i class="fas fa-shield-alt"></i> <?php echo htmlspecialchars($site_title); ?></h5>
                    <p class="mb-0"><?php echo htmlspecialchars($site_description); ?></p>
                </div>
                <div class="col-md-6 text-end">
                    <p class="mb-0">
                        <i class="fas fa-code"></i> PHP 7.4+ & MySQL ile geliştirildi<br>
                        <i class="fas fa-lock"></i> Gelişmiş güvenlik özellikleri
                    </p>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
    
    <script>
        // Mobile-first responsive enhancements
        document.addEventListener('DOMContentLoaded', function() {
            // Mobile navigation toggle
            const navToggler = document.querySelector('.navbar-toggler');
            const navCollapse = document.querySelector('.navbar-collapse');
            
            if (navToggler && navCollapse) {
                navToggler.addEventListener('click', function() {
                    navCollapse.classList.toggle('show');
                });
                
                // Close mobile menu when clicking outside
                document.addEventListener('click', function(e) {
                    if (!navToggler.contains(e.target) && !navCollapse.contains(e.target)) {
                        navCollapse.classList.remove('show');
                    }
                });
            }
            
            // Smooth scrolling for anchor links
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function (e) {
                    e.preventDefault();
                    const target = document.querySelector(this.getAttribute('href'));
                    if (target) {
                        target.scrollIntoView({
                        behavior: 'smooth'
                    });
                    }
                });
            });
            
            // Search input enhancement
            const searchInput = document.querySelector('input[name="search"]');
            if (searchInput) {
                searchInput.addEventListener('focus', function() {
                    this.closest('.search-section').style.transform = 'scale(1.02)';
                });
                
                searchInput.addEventListener('blur', function() {
                    this.closest('.search-section').style.transform = 'scale(1)';
                });
            }
            
            // Touch device optimizations
            if ('ontouchstart' in window) {
                document.querySelectorAll('.article-card').forEach(card => {
                    card.addEventListener('touchstart', function() {
                        this.style.transform = 'translateY(-5px)';
                    });
                    
                    card.addEventListener('touchend', function() {
                        setTimeout(() => {
                            this.style.transform = 'translateY(0)';
                        }, 150);
                    });
                });
                
                // Remove hover effects on touch devices
                document.body.classList.add('touch-device');
            }
            
            // Lazy loading for images
            if ('IntersectionObserver' in window) {
                const imageObserver = new IntersectionObserver((entries, observer) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            const img = entry.target;
                            img.src = img.dataset.src;
                            img.classList.remove('lazy');
                            observer.unobserve(img);
                        }
                    });
                });
                
                document.querySelectorAll('img[data-src]').forEach(img => {
                    imageObserver.observe(img);
                });
            }
            
            // Performance optimization: reduce repaints
            let ticking = false;
            function updateScrolled() {
                const scrolled = window.scrollY > 50;
                document.body.classList.toggle('scrolled', scrolled);
                ticking = false;
            }
            
            window.addEventListener('scroll', function() {
                if (!ticking) {
                    requestAnimationFrame(updateScrolled);
                    ticking = true;
                }
            });
        });
    </script>
</body>
</html> 