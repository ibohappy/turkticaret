<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

// Arama parametreleri
$search_query = isset($_GET['search']) ? trim(clean_input($_GET['search'])) : '';
$category_filter = isset($_GET['category']) ? clean_input($_GET['category']) : '';
$author_filter = isset($_GET['author']) ? clean_input($_GET['author']) : '';
$date_from = isset($_GET['date_from']) ? clean_input($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? clean_input($_GET['date_to']) : '';
$sort_by = isset($_GET['sort']) ? clean_input($_GET['sort']) : 'relevance';

// Sayfalama
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$posts_per_page = 8;
$offset = ($page - 1) * $posts_per_page;

$results = [];
$total_results = 0;
$total_pages = 0;
$search_time_start = microtime(true);

if ($search_query || $category_filter || $author_filter || $date_from || $date_to) {
    try {
        // Gelişmiş arama motoru kullan
        require_once 'search_engine_fixed.php';
        $search_engine = new AdvancedSearchEngine($pdo);
        
        $search_options = [
            'category_filter' => $category_filter,
            'author_filter' => $author_filter,
            'date_from' => $date_from,
            'date_to' => $date_to,
            'sort_by' => $sort_by,
            'page' => $page,
            'posts_per_page' => $posts_per_page
        ];
        
        $search_result = $search_engine->search($search_query, $search_options);
        
        $results = $search_result['results'];
        $total_results = $search_result['total_results'];
                $total_pages = $search_result['total_pages'];
        
        // Arama logunu kaydet (isteğe bağlı - tablo yoksa hata vermez)
        if ($search_query) {
            try {
                $log_stmt = $pdo->prepare("INSERT INTO search_logs (search_term, ip_address, user_agent, results_count) VALUES (:term, :ip, :user_agent, :count)");
                $log_stmt->execute([
                    ':term' => $search_query,
                    ':ip' => get_client_ip(),
                    ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                    ':count' => $total_results
                ]);
            } catch (PDOException $e) {
                // Search logs tablosu yoksa sessizce devam et
                error_log("Search log warning: " . $e->getMessage());
            }
        }
        
    } catch (PDOException $e) {
        error_log("Search error: " . $e->getMessage());
        $results = [];
        $total_results = 0;
    }
}

$search_time = round((microtime(true) - $search_time_start) * 1000, 2);

// Kategorileri getir (filtre için)
try {
    $categories_stmt = $pdo->prepare("
        SELECT c.*, COUNT(a.id) as article_count 
        FROM categories c
        LEFT JOIN articles a ON c.id = a.category_id AND a.status = 'published'
        WHERE c.is_active = 1
        GROUP BY c.id, c.name, c.slug, c.color, c.icon, c.sort_order
        ORDER BY c.sort_order, c.name
    ");
    $categories_stmt->execute();
    $categories = $categories_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $categories = [];
}

// Yazarları getir (filtre için)
try {
    $authors_stmt = $pdo->prepare("
        SELECT DISTINCT au.username, au.full_name, COUNT(a.id) as article_count
        FROM admin_users au 
        INNER JOIN articles a ON au.id = a.author_id 
        WHERE a.status = 'published'
        GROUP BY au.id, au.username, au.full_name
        ORDER BY au.full_name
    ");
    $authors_stmt->execute();
    $authors = $authors_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $authors = [];
}

$page_title = 'Gelişmiş Arama';
if ($search_query) {
    $page_title = 'Arama: ' . $search_query;
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - Güvenli Blog Sistemi</title>
    <meta name="description" content="Gelişmiş arama ile blog makalelerinde kategori, yazar ve tarih filtreleri kullanarak arama yapın.">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <link href="assets/css/mobile-first.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --card-hover-shadow: 0 15px 35px rgba(50, 50, 93, 0.1), 0 5px 15px rgba(0, 0, 0, 0.07);
        }
        
        .search-hero {
            background: var(--primary-gradient);
            color: white;
            padding: 60px 0;
            position: relative;
            overflow: hidden;
        }
        
        .search-hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 100" fill="white" opacity="0.1"><polygon points="1000,100 1000,0 0,100"/></svg>');
            background-size: cover;
        }
        
        .search-form-container {
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 30px;
            margin: -30px auto 30px;
            position: relative;
            z-index: 3;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
        }
        
        .search-input {
            border-radius: 15px;
            border: 2px solid #e9ecef;
            padding: 15px 20px;
            font-size: 1.1rem;
            transition: all 0.3s ease;
        }
        
        .search-input:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.25rem rgba(102, 126, 234, 0.25);
        }
        
        .filter-section {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }
        
        .filter-chip {
            background: #667eea;
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            display: inline-block;
            margin: 5px;
            font-size: 0.875rem;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .filter-chip:hover {
            background: #5a6fd8;
            color: white;
            transform: translateY(-2px);
        }
        
        .filter-chip.active {
            background: #28a745;
        }
        
        .result-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
        }
        
        .result-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--card-hover-shadow);
        }
        
        .highlight {
            background: #fff3cd;
            padding: 2px 4px;
            border-radius: 3px;
            font-weight: 600;
        }
        
        .search-stats {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px 20px;
            margin-bottom: 20px;
            border-left: 4px solid #667eea;
        }
        
        .sort-options {
            background: white;
            border-radius: 10px;
            padding: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .no-results {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }
        
        .no-results i {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.5;
        }
        
        .category-badge {
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 600;
            color: white;
            text-decoration: none;
            display: inline-block;
            margin-right: 8px;
        }
        
        .author-badge {
            background: #6c757d;
            color: white;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.75rem;
            text-decoration: none;
            display: inline-block;
            margin-right: 8px;
        }
        
        .date-range-inputs {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .search-feature-card {
            transition: all 0.3s ease;
        }
        
        .search-feature-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
            border-color: #667eea;
        }
        
        @media (max-width: 768px) {
            .search-hero {
                padding: 40px 0;
            }
            
            .search-form-container {
                margin: -20px 15px 20px;
                padding: 20px;
            }
            
            .filter-section {
                padding: 20px;
            }
            
            .date-range-inputs {
                flex-direction: column;
                gap: 15px;
            }
            
            .filter-chip {
                display: block;
                margin: 5px 0;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <!-- Search Hero -->
    <section class="search-hero">
        <div class="container">
            <div class="text-center position-relative" style="z-index: 2;">
                <h1 class="display-4 fw-bold mb-3">
                    <i class="fas fa-search"></i> Gelişmiş Arama
                </h1>
                <p class="lead mb-0">
                    Kapsamlı filtreler ile istediğiniz içerikleri kolayca bulun
                </p>
            </div>
        </div>
    </section>

    <!-- Advanced Search Form -->
    <div class="container">
        <div class="search-form-container">
            <form method="GET" id="searchForm">
                <div class="row g-3">
                    <!-- Ana Arama -->
                    <div class="col-md-12">
                        <label for="search" class="form-label fw-bold">
                            <i class="fas fa-search text-primary"></i> Arama Terimi
                        </label>
                        <input type="text" class="form-control search-input" id="search" name="search" 
                               value="<?php echo htmlspecialchars($search_query); ?>" 
                               placeholder="Başlık, içerik, etiketlerde ara...">
                    </div>
                    
                    <!-- Kategori Filtresi -->
                    <div class="col-md-4">
                        <label for="category" class="form-label fw-bold">
                            <i class="fas fa-folder text-success"></i> Kategori
                        </label>
                        <select class="form-select" id="category" name="category">
                            <option value="">Tüm Kategoriler</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat['slug']); ?>"
                                        <?php echo $category_filter === $cat['slug'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['name']); ?> (<?php echo $cat['article_count']; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Yazar Filtresi -->
                    <div class="col-md-4">
                        <label for="author" class="form-label fw-bold">
                            <i class="fas fa-user text-info"></i> Yazar
                        </label>
                        <select class="form-select" id="author" name="author">
                            <option value="">Tüm Yazarlar</option>
                            <?php foreach ($authors as $author): ?>
                                <option value="<?php echo htmlspecialchars($author['username']); ?>"
                                        <?php echo $author_filter === $author['username'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($author['full_name'] ?? $author['username']); ?> (<?php echo $author['article_count']; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Sıralama -->
                    <div class="col-md-4">
                        <label for="sort" class="form-label fw-bold">
                            <i class="fas fa-sort text-warning"></i> Sıralama
                        </label>
                        <select class="form-select" id="sort" name="sort">
                            <option value="relevance" <?php echo $sort_by === 'relevance' ? 'selected' : ''; ?>>En İlgili</option>
                            <option value="date_desc" <?php echo $sort_by === 'date_desc' ? 'selected' : ''; ?>>En Yeni</option>
                            <option value="date_asc" <?php echo $sort_by === 'date_asc' ? 'selected' : ''; ?>>En Eski</option>
                            <option value="title_asc" <?php echo $sort_by === 'title_asc' ? 'selected' : ''; ?>>Başlık (A-Z)</option>
                            <option value="title_desc" <?php echo $sort_by === 'title_desc' ? 'selected' : ''; ?>>Başlık (Z-A)</option>
                            <option value="views_desc" <?php echo $sort_by === 'views_desc' ? 'selected' : ''; ?>>En Popüler</option>
                        </select>
                    </div>
                    
                    <!-- Tarih Aralığı -->
                    <div class="col-md-6">
                        <label class="form-label fw-bold">
                            <i class="fas fa-calendar text-danger"></i> Tarih Aralığı
                        </label>
                        <div class="date-range-inputs">
                            <input type="date" class="form-control" name="date_from" 
                                   value="<?php echo htmlspecialchars($date_from); ?>" placeholder="Başlangıç">
                            <span class="mx-2">-</span>
                            <input type="date" class="form-control" name="date_to" 
                                   value="<?php echo htmlspecialchars($date_to); ?>" placeholder="Bitiş">
                        </div>
                    </div>
                    
                    <!-- Arama Butonları -->
                    <div class="col-md-6 d-flex align-items-end">
                        <div class="w-100">
                            <button type="submit" class="btn btn-primary btn-lg w-100 mb-2">
                                <i class="fas fa-search"></i> Ara
                            </button>
                            <a href="search.php" class="btn btn-outline-secondary w-100">
                                <i class="fas fa-eraser"></i> Temizle
                            </a>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="container my-5">
        <!-- Active Filters -->
        <?php if ($search_query || $category_filter || $author_filter || $date_from || $date_to): ?>
            <div class="filter-section">
                <h6 class="mb-3"><i class="fas fa-filter"></i> Aktif Filtreler:</h6>
                <div>
                    <?php if ($search_query): ?>
                        <span class="filter-chip">
                            <i class="fas fa-search"></i> "<?php echo htmlspecialchars($search_query); ?>"
                        </span>
                    <?php endif; ?>
                    
                    <?php if ($category_filter): ?>
                        <span class="filter-chip">
                            <i class="fas fa-folder"></i> Kategori: <?php echo htmlspecialchars($category_filter); ?>
                        </span>
                    <?php endif; ?>
                    
                    <?php if ($author_filter): ?>
                        <span class="filter-chip">
                            <i class="fas fa-user"></i> Yazar: <?php echo htmlspecialchars($author_filter); ?>
                        </span>
                    <?php endif; ?>
                    
                    <?php if ($date_from): ?>
                        <span class="filter-chip">
                            <i class="fas fa-calendar"></i> Başlangıç: <?php echo htmlspecialchars($date_from); ?>
                        </span>
                    <?php endif; ?>
                    
                    <?php if ($date_to): ?>
                        <span class="filter-chip">
                            <i class="fas fa-calendar"></i> Bitiş: <?php echo htmlspecialchars($date_to); ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Search Results -->
        <?php if ($search_query || $category_filter || $author_filter || $date_from || $date_to): ?>
            <!-- Search Statistics -->
            <div class="search-stats">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <strong><?php echo number_format($total_results); ?></strong> sonuç bulundu
                        <?php if ($search_query): ?>
                            "<strong><?php echo htmlspecialchars($search_query); ?></strong>" için
                        <?php endif; ?>
                        <small class="text-muted">(<?php echo $search_time; ?> ms)</small>
                    </div>
                    <div class="col-md-4 text-end">
                        <?php if ($total_pages > 1): ?>
                            Sayfa <?php echo $page; ?> / <?php echo $total_pages; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php if (empty($results)): ?>
                <!-- No Results -->
                <div class="no-results">
                    <i class="fas fa-search-minus"></i>
                    <h4>Hiç sonuç bulunamadı</h4>
                    <p class="text-muted mb-4">
                        Arama kriterlerinizi genişletmeyi deneyin:
                    </p>
                    <ul class="list-unstyled">
                        <li><i class="fas fa-check text-success"></i> Farklı anahtar kelimeler kullanın</li>
                        <li><i class="fas fa-check text-success"></i> Daha genel terimler deneyin</li>
                        <li><i class="fas fa-check text-success"></i> Filtreleri kaldırın veya değiştirin</li>
                        <li><i class="fas fa-check text-success"></i> Yazım hatası olmadığından emin olun</li>
                    </ul>
                    <a href="search.php" class="btn btn-primary">
                        <i class="fas fa-search"></i> Yeni Arama Yap
                    </a>
                </div>
            <?php else: ?>
                <!-- Results -->
                <div class="row">
                    <?php foreach ($results as $article): ?>
                        <div class="col-12">
                            <article class="result-card">
                                <div class="row">
                                    <div class="col-md-9">
                                        <h5 class="mb-2">
                                            <a href="article.php?slug=<?php echo htmlspecialchars($article['slug']); ?>" 
                                               class="text-decoration-none text-dark">
                                                <?php 
                                                $title = htmlspecialchars($article['title']);
                                                if ($search_query) {
                                                    $title = preg_replace('/(' . preg_quote($search_query, '/') . ')/i', '<span class="highlight">$1</span>', $title);
                                                }
                                                echo $title;
                                                ?>
                                            </a>
                                        </h5>
                                        
                                        <p class="text-muted mb-3">
                                            <?php 
                                            $excerpt = create_excerpt($article['content'], 200);
                                            if ($search_query) {
                                                $excerpt = preg_replace('/(' . preg_quote($search_query, '/') . ')/i', '<span class="highlight">$1</span>', $excerpt);
                                            }
                                            echo $excerpt;
                                            ?>
                                        </p>
                                        
                                        <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                                            <?php if (isset($article['category_name']) && $article['category_name']): ?>
                                                <a href="?category=<?php echo htmlspecialchars($article['category_slug']); ?>" 
                                                   class="category-badge" style="background: <?php echo htmlspecialchars($article['category_color']); ?>">
                                                    <i class="<?php echo htmlspecialchars($article['category_icon']); ?>"></i>
                                                    <?php echo htmlspecialchars($article['category_name']); ?>
                                                </a>
                                            <?php endif; ?>
                                            
                                            <?php if (isset($article['author_name']) && $article['author_name']): ?>
                                                <a href="?author=<?php echo htmlspecialchars($article['author_username']); ?>" 
                                                   class="author-badge">
                                                    <i class="fas fa-user"></i>
                                                    <?php echo htmlspecialchars($article['author_name']); ?>
                                                </a>
                                            <?php endif; ?>
                                            
                                            <span class="badge bg-light text-dark">
                                                <i class="fas fa-calendar"></i>
                                                <?php echo format_date($article['published_at']); ?>
                                            </span>
                                            
                                            <span class="badge bg-light text-dark">
                                                <i class="fas fa-eye"></i>
                                                <?php echo number_format($article['view_count']); ?> görüntüleme
                                            </span>
                                            
                                            <?php if ($article['reading_time']): ?>
                                                <span class="badge bg-light text-dark">
                                                    <i class="fas fa-clock"></i>
                                                    <?php echo $article['reading_time']; ?> dk okuma
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <?php if ($article['tags']): ?>
                                            <div class="mt-2">
                                                <?php 
                                                $tags = explode(',', $article['tags']);
                                                foreach (array_slice($tags, 0, 5) as $tag): 
                                                    $tag = trim($tag);
                                                    if ($tag):
                                                ?>
                                                    <span class="badge bg-secondary me-1">
                                                        <i class="fas fa-tag"></i> <?php echo htmlspecialchars($tag); ?>
                                                    </span>
                                                <?php 
                                                    endif;
                                                endforeach; 
                                                ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="col-md-3 text-end">
                                        <a href="article.php?slug=<?php echo htmlspecialchars($article['slug']); ?>" 
                                           class="btn btn-outline-primary">
                                            <i class="fas fa-arrow-right"></i> Devamını Oku
                                        </a>
                                        
                                        <?php if ($article['is_featured']): ?>
                                            <div class="mt-2">
                                                <span class="badge bg-warning">
                                                    <i class="fas fa-star"></i> Öne Çıkan
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </article>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="d-flex justify-content-center mt-5">
                        <nav aria-label="Arama sonuçları sayfa navigasyonu">
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
        <?php else: ?>
            <!-- Initial State -->
            <div class="no-results">
                <i class="fas fa-search"></i>
                <h4>Gelişmiş Arama</h4>
                <p class="text-muted mb-4">
                    Yukarıdaki formu kullanarak detaylı arama yapabilir ve sonuçlarınızı filtreyebilirsiniz.
                </p>
                <div class="row justify-content-center">
                    <div class="col-md-8">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="card h-100 search-feature-card" style="cursor: pointer;" onclick="document.getElementById('search').focus();">
                                    <div class="card-body text-center">
                                        <i class="fas fa-search fa-2x text-primary mb-3"></i>
                                        <h6>Anahtar Kelime</h6>
                                        <p class="small text-muted">Başlık, içerik ve etiketlerde arama</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="card h-100 search-feature-card" style="cursor: pointer;" onclick="document.getElementById('category').focus();">
                                    <div class="card-body text-center">
                                        <i class="fas fa-filter fa-2x text-success mb-3"></i>
                                        <h6>Kategoriye Göre</h6>
                                        <p class="small text-muted">Belirli kategorilerden arama</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="card h-100 search-feature-card" style="cursor: pointer;" onclick="document.getElementById('author').focus();">
                                    <div class="card-body text-center">
                                        <i class="fas fa-user fa-2x text-info mb-3"></i>
                                        <h6>Yazara Göre</h6>
                                        <p class="small text-muted">Belirli yazarların makalelerinde arama</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="card h-100 search-feature-card" style="cursor: pointer;" onclick="document.querySelector('input[name=date_from]').focus();">
                                    <div class="card-body text-center">
                                        <i class="fas fa-calendar fa-2x text-warning mb-3"></i>
                                        <h6>Tarih Aralığı</h6>
                                        <p class="small text-muted">Belirli tarih aralığında arama</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="d-flex gap-2 justify-content-center">
                <a href="index.php" class="btn btn-primary">
                    <i class="fas fa-home"></i> Ana Sayfaya Dön
                </a>
                    <a href="search.php" class="btn btn-outline-secondary">
                        <i class="fas fa-search"></i> Yeni Arama
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <footer class="bg-dark text-white py-4 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5><i class="fas fa-shield-alt"></i> Güvenli Blog Sistemi</h5>
                    <p class="mb-0">PHP ve MySQL ile oluşturulmuş gelişmiş güvenlik özellikli blog sistemi</p>
                </div>
                <div class="col-md-6 text-end">
                    <p class="mb-0">
                        <i class="fas fa-search"></i> Gelişmiş arama özelliği<br>
                        <i class="fas fa-filter"></i> Çoklu filtre desteği
                    </p>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-submit form on filter change
            const filterInputs = document.querySelectorAll('#category, #author, #sort');
            filterInputs.forEach(input => {
                input.addEventListener('change', function() {
                    // Auto-submit after a short delay to prevent too many requests
                    setTimeout(() => {
                        document.getElementById('searchForm').submit();
                    }, 500);
                });
            });
            
            // Enhanced search input
            const searchInput = document.getElementById('search');
            if (searchInput) {
                let searchTimeout;
                
                searchInput.addEventListener('input', function() {
                    clearTimeout(searchTimeout);
                    
                    // Show loading indicator
                    this.style.backgroundImage = 'url("data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'20\' height=\'20\' viewBox=\'0 0 20 20\' fill=\'%23666\'%3E%3Cpath d=\'M10 2a8 8 0 100 16 8 8 0 000-16zm0 2a6 6 0 110 12 6 6 0 010-12z\' opacity=\'.3\'/%3E%3Cpath d=\'M14 10a4 4 0 11-8 0 4 4 0 018 0z\'/%3E%3C/svg%3E")';
                    this.style.backgroundRepeat = 'no-repeat';
                    this.style.backgroundPosition = 'right 10px center';
                });
                
                searchInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        document.getElementById('searchForm').submit();
                    }
                });
            }
            
            // Date range validation
            const dateFrom = document.querySelector('input[name="date_from"]');
            const dateTo = document.querySelector('input[name="date_to"]');
            
            if (dateFrom && dateTo) {
                dateFrom.addEventListener('change', function() {
                    if (this.value && dateTo.value && this.value > dateTo.value) {
                        dateTo.value = this.value;
                    }
                });
                
                dateTo.addEventListener('change', function() {
                    if (this.value && dateFrom.value && this.value < dateFrom.value) {
                        dateFrom.value = this.value;
                    }
                });
            }
            
            // Smooth scroll to results
            if (window.location.search && document.querySelector('.search-stats')) {
                document.querySelector('.search-stats').scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    </script>
</body>
</html> 