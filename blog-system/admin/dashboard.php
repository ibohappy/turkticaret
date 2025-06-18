<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

check_admin();

$page_title = 'Dashboard';

try {
    // Temel istatistikler
    $stats = [];
    
    // Toplam makale sayısı (yayınlanan ve taslak)
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM articles");
    $stats['total_articles'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Yayınlanan makale sayısı
    $stmt = $pdo->query("SELECT COUNT(*) as published FROM articles WHERE status = 'published'");
    $stats['published_articles'] = $stmt->fetch(PDO::FETCH_ASSOC)['published'];
    
    // Taslak makale sayısı
    $stmt = $pdo->query("SELECT COUNT(*) as draft FROM articles WHERE status = 'draft'");
    $stats['draft_articles'] = $stmt->fetch(PDO::FETCH_ASSOC)['draft'];
    
    // Toplam kategori sayısı
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM categories WHERE is_active = 1");
    $stats['total_categories'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Toplam görüntüleme sayısı
    $stmt = $pdo->query("SELECT SUM(view_count) as total_views FROM articles WHERE status = 'published'");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['total_views'] = $result['total_views'] ?? 0;
    
    // Ortalama görüntüleme
    $stats['avg_views'] = $stats['published_articles'] > 0 ? round($stats['total_views'] / $stats['published_articles']) : 0;
    
    // Bu ay eklenen makale sayısı
    $stmt = $pdo->query("SELECT COUNT(*) as this_month FROM articles WHERE MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())");
    $stats['this_month_articles'] = $stmt->fetch(PDO::FETCH_ASSOC)['this_month'];
    
    // Son 30 günlük makale istatistikleri
    $daily_stats_query = "
        SELECT 
            DATE(created_at) as date,
            COUNT(*) as count 
        FROM articles 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY DATE(created_at)
        ORDER BY date DESC
    ";
    $stmt = $pdo->query($daily_stats_query);
    $daily_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Son 7 günlük login istatistikleri
    $login_stats_query = "
        SELECT 
            DATE(created_at) as date,
            COUNT(*) as total_attempts,
            SUM(CASE WHEN success = 1 THEN 1 ELSE 0 END) as successful_logins
        FROM login_logs 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY DATE(created_at)
        ORDER BY date DESC
    ";
    $stmt = $pdo->query($login_stats_query);
    $login_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Kategori bazında makale dağılımı
    $category_stats_query = "
        SELECT 
            c.name,
            c.color,
            c.icon,
            COUNT(a.id) as article_count
        FROM categories c
        LEFT JOIN articles a ON c.id = a.category_id AND a.status = 'published'
        WHERE c.is_active = 1
        GROUP BY c.id, c.name, c.color, c.icon
        ORDER BY article_count DESC
    ";
    $stmt = $pdo->query($category_stats_query);
    $category_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // En popüler makaleler (son 30 gün)
    $popular_articles_query = "
        SELECT title, slug, view_count, published_at
        FROM articles 
        WHERE status = 'published' 
        ORDER BY view_count DESC 
        LIMIT 10
    ";
    $stmt = $pdo->query($popular_articles_query);
    $popular_articles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Son makaleler
    $recent_articles_query = "
        SELECT a.title, a.slug, a.status, a.created_at, c.name as category_name, c.color as category_color
        FROM articles a
        LEFT JOIN categories c ON a.category_id = c.id
        ORDER BY a.created_at DESC 
        LIMIT 10
    ";
    $stmt = $pdo->query($recent_articles_query);
    $recent_articles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // En çok aranan terimler (son 7 gün)
    $search_terms_query = "
        SELECT search_term, COUNT(*) as search_count, SUM(results_count) as total_results
        FROM search_logs 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY search_term
        ORDER BY search_count DESC
        LIMIT 10
    ";
    $stmt = $pdo->query($search_terms_query);
    $search_terms = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Sistem sağlık durumu
    $health_checks = [
        'database' => true,
        'file_permissions' => is_writable('../assets/images/'),
        'session' => session_status() === PHP_SESSION_ACTIVE,
        'php_version' => version_compare(PHP_VERSION, '7.4.0', '>='),
        'memory_usage' => memory_get_usage(true) / 1024 / 1024 // MB
    ];
    
} catch (PDOException $e) {
    error_log("Dashboard error: " . $e->getMessage());
    $stats = [
        'total_articles' => 0,
        'published_articles' => 0,
        'draft_articles' => 0,
        'total_categories' => 0,
        'total_views' => 0,
        'avg_views' => 0,
        'this_month_articles' => 0
    ];
    $daily_stats = [];
    $login_stats = [];
    $category_stats = [];
    $popular_articles = [];
    $recent_articles = [];
    $search_terms = [];
    $health_checks = ['database' => false];
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - Güvenli Blog Sistemi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --success-gradient: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            --warning-gradient: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
            --danger-gradient: linear-gradient(135deg, #dc3545 0%, #e83e8c 100%);
            --info-gradient: linear-gradient(135deg, #17a2b8 0%, #6f42c1 100%);
            --card-shadow: 0 10px 30px rgba(0,0,0,0.1);
            --hover-shadow: 0 15px 35px rgba(50, 50, 93, 0.1), 0 5px 15px rgba(0, 0, 0, 0.07);
        }
        
        body {
            background: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .navbar {
            background: var(--primary-gradient) !important;
            box-shadow: var(--card-shadow);
        }
        
        .sidebar {
            background: white;
            box-shadow: var(--card-shadow);
            border-radius: 15px;
            padding: 20px 0;
            margin-right: 20px;
            height: fit-content;
            position: sticky;
            top: 20px;
        }
        
        .nav-link {
            border-radius: 10px;
            margin: 5px 15px;
            transition: all 0.3s ease;
            color: #495057;
        }
        
        .nav-link:hover, .nav-link.active {
            background: var(--primary-gradient);
            color: white !important;
            transform: translateX(5px);
        }
        
        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: var(--card-shadow);
            transition: all 0.3s ease;
            height: 100%;
            border: none;
            overflow: hidden;
            position: relative;
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--hover-shadow);
        }
        
        .stats-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--primary-gradient);
        }
        
        .stats-card.success::before { background: var(--success-gradient); }
        .stats-card.warning::before { background: var(--warning-gradient); }
        .stats-card.danger::before { background: var(--danger-gradient); }
        .stats-card.info::before { background: var(--info-gradient); }
        
        .stats-number {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 10px;
            display: block;
        }
        
        .stats-icon {
            font-size: 3rem;
            opacity: 0.2;
            position: absolute;
            right: 20px;
            top: 20px;
        }
        
        .chart-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: var(--card-shadow);
            margin-bottom: 30px;
        }
        
        .table-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: var(--card-shadow);
        }
        
        .table-card .table {
            margin-bottom: 0;
        }
        
        .table thead th {
            background: var(--primary-gradient);
            color: white;
            border: none;
            font-weight: 600;
        }
        
        .badge-status {
            padding: 8px 15px;
            border-radius: 20px;
            font-weight: 500;
        }
        
        .health-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 8px;
        }
        
        .health-good { background: #28a745; }
        .health-warning { background: #ffc107; }
        .health-bad { background: #dc3545; }
        
        .category-item {
            display: flex;
            align-items: center;
            padding: 12px;
            border-radius: 10px;
            margin-bottom: 10px;
            transition: all 0.3s ease;
        }
        
        .category-item:hover {
            transform: translateX(10px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .welcome-section {
            background: var(--primary-gradient);
            color: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
        }
        
        .welcome-section::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 300px;
            height: 300px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
        }
        
        .quick-actions {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            margin-top: 20px;
        }
        
        .quick-action-btn {
            background: rgba(255,255,255,0.2);
            color: white;
            border: 2px solid rgba(255,255,255,0.3);
            border-radius: 10px;
            padding: 10px 20px;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .quick-action-btn:hover {
            background: rgba(255,255,255,0.3);
            color: white;
            transform: translateY(-2px);
        }
        
        @media (max-width: 768px) {
            .sidebar {
                margin-right: 0;
                margin-bottom: 20px;
            }
            
            .stats-card {
                margin-bottom: 20px;
            }
            
            .quick-actions {
                flex-direction: column;
            }
            
            .stats-number {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold" href="dashboard.php">
                <i class="fas fa-shield-alt"></i> Güvenli Admin Paneli
            </a>
            <div class="navbar-nav ms-auto">
                <span class="navbar-text me-3">
                    <i class="fas fa-user-shield"></i> Hoşgeldin, <strong><?php echo htmlspecialchars($_SESSION['admin_username']); ?></strong>
                </span>
                <a href="logout.php" class="btn btn-outline-light btn-sm">
                    <i class="fas fa-sign-out-alt"></i> Güvenli Çıkış
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid py-4">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-lg-2 col-md-3">
                <nav class="sidebar">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link active" href="dashboard.php">
                                <i class="fas fa-tachometer-alt"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="articles.php">
                                <i class="fas fa-newspaper"></i> Makaleler
                                <span class="badge bg-primary ms-2"><?php echo $stats['total_articles']; ?></span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="add-article.php">
                                <i class="fas fa-plus-circle"></i> Yeni Makale
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../index.php" target="_blank">
                                <i class="fas fa-external-link-alt"></i> Siteyi Görüntüle
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>

            <!-- Main Content -->
            <div class="col-lg-10 col-md-9">
                <!-- Welcome Section -->
                <div class="welcome-section">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h1 class="h2 mb-3">
                                <i class="fas fa-chart-line"></i> Dashboard
                            </h1>
                            <p class="lead mb-0">
                                Blog sisteminizin genel durumu ve istatistikleri
                            </p>
                            <div class="quick-actions">
                                <a href="add-article.php" class="quick-action-btn">
                                    <i class="fas fa-plus"></i> Yeni Makale
                                </a>
                                <a href="articles.php" class="quick-action-btn">
                                    <i class="fas fa-list"></i> Makaleleri Yönet
                                </a>
                                <a href="../index.php" target="_blank" class="quick-action-btn">
                                    <i class="fas fa-eye"></i> Siteyi Görüntüle
                                </a>
                            </div>
                        </div>
                        <div class="col-md-4 text-end">
                            <i class="fas fa-chart-pie" style="font-size: 5rem; opacity: 0.3;"></i>
                        </div>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="stats-card">
                            <span class="stats-number text-primary"><?php echo number_format($stats['total_articles']); ?></span>
                            <div>
                                <strong>Toplam Makale</strong><br>
                                <small class="text-muted">Tüm durumlar</small>
                            </div>
                            <i class="fas fa-newspaper stats-icon text-primary"></i>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="stats-card success">
                            <span class="stats-number text-success"><?php echo number_format($stats['published_articles']); ?></span>
                            <div>
                                <strong>Yayınlanan</strong><br>
                                <small class="text-muted">Aktif makaleler</small>
                            </div>
                            <i class="fas fa-check-circle stats-icon text-success"></i>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="stats-card warning">
                            <span class="stats-number text-warning"><?php echo number_format($stats['draft_articles']); ?></span>
                            <div>
                                <strong>Taslaklar</strong><br>
                                <small class="text-muted">Beklemede</small>
                            </div>
                            <i class="fas fa-edit stats-icon text-warning"></i>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="stats-card info">
                            <span class="stats-number text-info"><?php echo number_format($stats['total_views']); ?></span>
                            <div>
                                <strong>Toplam Görüntüleme</strong><br>
                                <small class="text-muted">Ort: <?php echo number_format($stats['avg_views']); ?> / makale</small>
                            </div>
                            <i class="fas fa-eye stats-icon text-info"></i>
                        </div>
                    </div>
                </div>

                <!-- Additional Stats Row -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="stats-card info">
                            <span class="stats-number text-info"><?php echo number_format($stats['total_categories']); ?></span>
                            <div>
                                <strong>Aktif Kategoriler</strong><br>
                                <small class="text-muted">Düzenlenmiş</small>
                            </div>
                            <i class="fas fa-folder stats-icon text-info"></i>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="stats-card success">
                            <span class="stats-number text-success"><?php echo number_format($stats['this_month_articles']); ?></span>
                            <div>
                                <strong>Bu Ay</strong><br>
                                <small class="text-muted">Yeni makaleler</small>
                            </div>
                            <i class="fas fa-calendar-plus stats-icon text-success"></i>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="stats-card warning">
                            <span class="stats-number text-warning"><?php echo round($health_checks['memory_usage'], 1); ?>MB</span>
                            <div>
                                <strong>Bellek Kullanımı</strong><br>
                                <small class="text-muted">PHP Memory</small>
                            </div>
                            <i class="fas fa-memory stats-icon text-warning"></i>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="stats-card <?php echo $health_checks['database'] ? 'success' : 'danger'; ?>">
                            <span class="stats-number <?php echo $health_checks['database'] ? 'text-success' : 'text-danger'; ?>">
                                <i class="fas fa-<?php echo $health_checks['database'] ? 'check' : 'times'; ?>"></i>
                            </span>
                            <div>
                                <strong>Sistem Durumu</strong><br>
                                <small class="text-muted"><?php echo $health_checks['database'] ? 'Çevrimiçi' : 'Çevrimdışı'; ?></small>
                            </div>
                            <i class="fas fa-server stats-icon <?php echo $health_checks['database'] ? 'text-success' : 'text-danger'; ?>"></i>
                        </div>
                    </div>
                </div>

                <!-- Charts Row -->
                <div class="row mb-4">
                    <!-- Daily Articles Chart -->
                    <div class="col-lg-8">
                        <div class="chart-card">
                            <h5 class="mb-4">
                                <i class="fas fa-chart-line text-primary"></i> Son 30 Günlük Makale İstatistikleri
                            </h5>
                            <canvas id="dailyArticlesChart" height="100"></canvas>
                        </div>
                    </div>
                    
                    <!-- Category Distribution -->
                    <div class="col-lg-4">
                        <div class="chart-card">
                            <h5 class="mb-4">
                                <i class="fas fa-chart-pie text-info"></i> Kategori Dağılımı
                            </h5>
                            <canvas id="categoryChart" height="200"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Data Tables Row -->
                <div class="row">
                    <!-- Popular Articles -->
                    <div class="col-lg-6 mb-4">
                        <div class="table-card">
                            <div class="card-header bg-primary text-white">
                                <h6 class="mb-0">
                                    <i class="fas fa-fire"></i> En Popüler Makaleler
                                </h6>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Makale</th>
                                            <th>Görüntüleme</th>
                                            <th>Tarih</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($popular_articles)): ?>
                                            <tr>
                                                <td colspan="3" class="text-center text-muted">
                                                    <i class="fas fa-info-circle"></i> Henüz popüler makale bulunmuyor
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach (array_slice($popular_articles, 0, 8) as $index => $article): ?>
                                                <tr>
                                                    <td>
                                                        <?php if ($index < 3): ?>
                                                            <i class="fas fa-trophy text-warning me-2"></i>
                                                        <?php endif; ?>
                                                        <a href="../article.php?slug=<?php echo htmlspecialchars($article['slug']); ?>" 
                                                           target="_blank" class="text-decoration-none">
                                                            <?php echo htmlspecialchars(substr($article['title'], 0, 40)); ?>
                                                            <?php echo strlen($article['title']) > 40 ? '...' : ''; ?>
                                                        </a>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-info">
                                                            <i class="fas fa-eye"></i> <?php echo number_format($article['view_count']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <small class="text-muted">
                                                            <?php echo format_date($article['published_at']); ?>
                                                        </small>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Articles -->
                    <div class="col-lg-6 mb-4">
                        <div class="table-card">
                            <div class="card-header bg-success text-white">
                                <h6 class="mb-0">
                                    <i class="fas fa-clock"></i> Son Makaleler
                                </h6>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Makale</th>
                                            <th>Durum</th>
                                            <th>Tarih</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($recent_articles)): ?>
                                            <tr>
                                                <td colspan="3" class="text-center text-muted">
                                                    <i class="fas fa-info-circle"></i> Henüz makale bulunmuyor
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach (array_slice($recent_articles, 0, 8) as $article): ?>
                                                <tr>
                                                    <td>
                                                        <a href="edit-article.php?id=<?php echo $article['slug']; ?>" 
                                                           class="text-decoration-none">
                                                            <?php echo htmlspecialchars(substr($article['title'], 0, 35)); ?>
                                                            <?php echo strlen($article['title']) > 35 ? '...' : ''; ?>
                                                        </a>
                                                        <?php if ($article['category_name']): ?>
                                                            <br>
                                                            <small style="color: <?php echo htmlspecialchars($article['category_color']); ?>">
                                                                <i class="fas fa-folder"></i> <?php echo htmlspecialchars($article['category_name']); ?>
                                                            </small>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($article['status'] === 'published'): ?>
                                                            <span class="badge bg-success">Yayınlandı</span>
                                                        <?php elseif ($article['status'] === 'draft'): ?>
                                                            <span class="badge bg-warning">Taslak</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-info"><?php echo htmlspecialchars($article['status']); ?></span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <small class="text-muted">
                                                            <?php echo format_date($article['created_at']); ?>
                                                        </small>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Additional Info Row -->
                <div class="row">
                    <!-- Search Terms -->
                    <?php if (!empty($search_terms)): ?>
                        <div class="col-lg-6 mb-4">
                            <div class="table-card">
                                <div class="card-header bg-info text-white">
                                    <h6 class="mb-0">
                                        <i class="fas fa-search"></i> Popüler Arama Terimleri (7 Gün)
                                    </h6>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Arama Terimi</th>
                                                <th>Arama Sayısı</th>
                                                <th>Sonuç</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach (array_slice($search_terms, 0, 8) as $term): ?>
                                                <tr>
                                                    <td>
                                                        <i class="fas fa-search text-muted me-2"></i>
                                                        <?php echo htmlspecialchars($term['search_term']); ?>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-primary">
                                                            <?php echo number_format($term['search_count']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <small class="text-muted">
                                                            <?php echo number_format($term['total_results']); ?> sonuç
                                                        </small>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- System Health -->
                    <div class="col-lg-6 mb-4">
                        <div class="table-card">
                            <div class="card-header bg-warning text-dark">
                                <h6 class="mb-0">
                                    <i class="fas fa-heartbeat"></i> Sistem Sağlığı
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-12 mb-3">
                                        <div class="d-flex align-items-center">
                                            <span class="health-indicator <?php echo $health_checks['database'] ? 'health-good' : 'health-bad'; ?>"></span>
                                            <strong>Veritabanı Bağlantısı:</strong>
                                            <span class="ms-auto"><?php echo $health_checks['database'] ? 'Aktif' : 'Hata'; ?></span>
                                        </div>
                                    </div>
                                    <div class="col-12 mb-3">
                                        <div class="d-flex align-items-center">
                                            <span class="health-indicator <?php echo $health_checks['file_permissions'] ? 'health-good' : 'health-bad'; ?>"></span>
                                            <strong>Dosya İzinleri:</strong>
                                            <span class="ms-auto"><?php echo $health_checks['file_permissions'] ? 'Yazılabilir' : 'Hata'; ?></span>
                                        </div>
                                    </div>
                                    <div class="col-12 mb-3">
                                        <div class="d-flex align-items-center">
                                            <span class="health-indicator <?php echo $health_checks['session'] ? 'health-good' : 'health-bad'; ?>"></span>
                                            <strong>Session Durumu:</strong>
                                            <span class="ms-auto"><?php echo $health_checks['session'] ? 'Aktif' : 'Hata'; ?></span>
                                        </div>
                                    </div>
                                    <div class="col-12 mb-3">
                                        <div class="d-flex align-items-center">
                                            <span class="health-indicator <?php echo $health_checks['php_version'] ? 'health-good' : 'health-warning'; ?>"></span>
                                            <strong>PHP Sürümü:</strong>
                                            <span class="ms-auto"><?php echo PHP_VERSION; ?></span>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="d-flex align-items-center">
                                            <span class="health-indicator <?php echo $health_checks['memory_usage'] < 128 ? 'health-good' : ($health_checks['memory_usage'] < 256 ? 'health-warning' : 'health-bad'); ?>"></span>
                                            <strong>Bellek Kullanımı:</strong>
                                            <span class="ms-auto"><?php echo round($health_checks['memory_usage'], 1); ?> MB</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Daily Articles Chart
        const dailyCtx = document.getElementById('dailyArticlesChart').getContext('2d');
        const dailyLabels = <?php echo json_encode(array_reverse(array_column($daily_stats, 'date'))); ?>;
        const dailyData = <?php echo json_encode(array_reverse(array_column($daily_stats, 'count'))); ?>;
        
        new Chart(dailyCtx, {
            type: 'line',
            data: {
                labels: dailyLabels,
                datasets: [{
                    label: 'Günlük Makale Sayısı',
                    data: dailyData,
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#667eea',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2,
                    pointRadius: 6
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0,0,0,0.1)'
                        }
                    },
                    x: {
                        grid: {
                            color: 'rgba(0,0,0,0.1)'
                        }
                    }
                },
                elements: {
                    point: {
                        hoverRadius: 8
                    }
                }
            }
        });

        // Category Chart
        const categoryCtx = document.getElementById('categoryChart').getContext('2d');
        const categoryLabels = <?php echo json_encode(array_column($category_stats, 'name')); ?>;
        const categoryData = <?php echo json_encode(array_column($category_stats, 'article_count')); ?>;
        const categoryColors = <?php echo json_encode(array_column($category_stats, 'color')); ?>;
        
        new Chart(categoryCtx, {
            type: 'doughnut',
            data: {
                labels: categoryLabels,
                datasets: [{
                    data: categoryData,
                    backgroundColor: categoryColors,
                    borderWidth: 3,
                    borderColor: '#ffffff',
                    hoverBorderWidth: 4,
                    hoverBorderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 15,
                            usePointStyle: true
                        }
                    }
                },
                cutout: '60%'
            }
        });
        
        // Auto-refresh dashboard data every 5 minutes
        setInterval(function() {
            location.reload();
        }, 300000);
        
        // Animate counters on page load
        document.addEventListener('DOMContentLoaded', function() {
            const counters = document.querySelectorAll('.stats-number');
            counters.forEach(counter => {
                const target = parseInt(counter.innerText.replace(/,/g, ''));
                if (!isNaN(target)) {
                    let current = 0;
                    const increment = target / 50;
                    const timer = setInterval(() => {
                        current += increment;
                        if (current >= target) {
                            counter.innerText = target.toLocaleString();
                            clearInterval(timer);
                        } else {
                            counter.innerText = Math.floor(current).toLocaleString();
                        }
                    }, 50);
                }
            });
        });
    </script>
</body>
</html> 