<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

// Makale slug'ını al
$slug = isset($_GET['slug']) ? clean_input($_GET['slug']) : '';

if (empty($slug)) {
    header('Location: index.php');
    exit();
}

// Makaleyi veritabanından al
$query = "SELECT a.*, c.name as category_name, c.slug as category_slug, c.color as category_color, c.icon as category_icon,
                 au.full_name as author_name, au.username as author_username
          FROM articles a 
          LEFT JOIN categories c ON a.category_id = c.id 
          LEFT JOIN admin_users au ON a.author_id = au.id 
          WHERE a.slug = :slug AND a.status = 'published'";
$stmt = $pdo->prepare($query);
$stmt->bindValue(':slug', $slug);
$stmt->execute();
$article = $stmt->fetch();

if (!$article) {
    header('Location: index.php');
    exit();
}

// Görüntülenme sayısını artır
$view_query = "UPDATE articles SET view_count = view_count + 1 WHERE id = :id";
$view_stmt = $pdo->prepare($view_query);
$view_stmt->bindValue(':id', $article['id'], PDO::PARAM_INT);
$view_stmt->execute();

// İlgili makaleleri getir (aynı kategoriden)
$related_query = "SELECT a.*, c.name as category_name, c.color as category_color 
                  FROM articles a 
                  LEFT JOIN categories c ON a.category_id = c.id 
                  WHERE a.status = 'published' AND a.id != :current_id 
                  AND (a.category_id = :category_id OR a.category_id IS NULL) 
                  ORDER BY a.created_at DESC LIMIT 3";
$related_stmt = $pdo->prepare($related_query);
$related_stmt->bindValue(':current_id', $article['id'], PDO::PARAM_INT);
$related_stmt->bindValue(':category_id', $article['category_id'], PDO::PARAM_INT);
$related_stmt->execute();
$related_articles = $related_stmt->fetchAll();

$page_title = $article['title'];
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - Güvenli Blog Sistemi</title>
    <meta name="description" content="<?php echo htmlspecialchars(create_excerpt($article['content'], 150)); ?>">
    <meta name="keywords" content="<?php echo htmlspecialchars($article['tags']); ?>">
    
    <!-- Open Graph Meta Tags -->
    <meta property="og:title" content="<?php echo htmlspecialchars($article['title']); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars(create_excerpt($article['content'], 150)); ?>">
    <meta property="og:type" content="article">
    <?php if ($article['featured_image']): ?>
    <meta property="og:image" content="<?php echo 'http://' . $_SERVER['HTTP_HOST'] . '/turkticaretblog/assets/images/' . $article['featured_image']; ?>">
    <?php endif; ?>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <link href="assets/css/mobile-first.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --text-primary: #2d3748;
            --text-secondary: #4a5568;
            --text-muted: #718096;
            --border-color: #e2e8f0;
            --bg-light: #f7fafc;
            --shadow-sm: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            line-height: 1.7;
            color: var(--text-primary);
            background: #f8fafc;
        }

        .article-hero {
            position: relative;
            margin-bottom: 3rem;
            overflow: hidden;
            border-radius: 20px;
            box-shadow: var(--shadow-lg);
        }

        .article-hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(180deg, transparent 0%, rgba(0,0,0,0.7) 100%);
            z-index: 2;
        }

        .article-hero-image {
            width: 100%;
            height: 400px;
            object-fit: cover;
            object-position: center;
        }

        .article-hero-content {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 3rem;
            color: white;
            z-index: 3;
        }

        .article-hero-title {
            font-size: 2.5rem;
            font-weight: 700;
            line-height: 1.2;
            margin-bottom: 1rem;
            text-shadow: 0 2px 4px rgba(0,0,0,0.3);
        }

        .article-meta {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            margin-bottom: 1rem;
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .article-meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .article-content-wrapper {
            background: white;
            border-radius: 20px;
            padding: 3rem;
            box-shadow: var(--shadow-md);
            margin-bottom: 2rem;
        }

        .article-content {
            font-size: 1.125rem;
            line-height: 1.8;
            color: var(--text-primary);
        }

        .article-content p {
            margin-bottom: 1.5rem;
        }

        .article-content h1, .article-content h2 {
            color: var(--text-primary);
            font-weight: 600;
            margin: 2rem 0 1rem 0;
        }

        .article-content h1 {
            font-size: 1.875rem;
            border-bottom: 3px solid #667eea;
            padding-bottom: 0.5rem;
        }

        .article-content h2 {
            font-size: 1.5rem;
        }

        .category-badge-large {
            background: var(--primary-gradient);
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 25px;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-sm);
        }

        .category-badge-large:hover {
            color: white;
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .tag-cloud {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid var(--border-color);
        }

        .tag-item {
            background: var(--bg-light);
            color: var(--text-secondary);
            padding: 0.5rem 1rem;
            border-radius: 15px;
            font-size: 0.875rem;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .tag-item:hover {
            background: #667eea;
            color: white;
            transform: translateY(-1px);
        }

        .sidebar-card {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: var(--shadow-md);
            margin-bottom: 2rem;
        }

        .sidebar-card h5 {
            color: var(--text-primary);
            font-weight: 600;
            margin-bottom: 1.5rem;
            font-size: 1.25rem;
        }

        .related-article {
            display: flex;
            gap: 1rem;
            padding: 1rem;
            border-radius: 15px;
            transition: all 0.3s ease;
            text-decoration: none;
            color: inherit;
        }

        .related-article:hover {
            background: var(--bg-light);
            transform: translateX(5px);
            color: inherit;
        }

        .related-article-image {
            width: 80px;
            height: 80px;
            border-radius: 10px;
            object-fit: cover;
            flex-shrink: 0;
        }

        .related-article-content h6 {
            font-size: 0.95rem;
            font-weight: 500;
            line-height: 1.4;
            margin-bottom: 0.5rem;
        }

        .related-article-meta {
            font-size: 0.8rem;
            color: var(--text-muted);
        }

        .search-box {
            position: relative;
        }

        .search-input {
            border: 2px solid var(--border-color);
            border-radius: 15px;
            padding: 1rem 1.25rem;
            font-size: 1rem;
            transition: all 0.3s ease;
            width: 100%;
        }

        .search-input:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .search-btn {
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            background: var(--primary-gradient);
            border: none;
            border-radius: 10px;
            padding: 0.75rem 1rem;
            color: white;
            transition: all 0.3s ease;
        }

        .search-btn:hover {
            transform: translateY(-50%) scale(1.05);
        }

        .back-button {
            background: var(--primary-gradient);
            border: none;
            border-radius: 15px;
            padding: 1rem 2rem;
            color: white;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-sm);
        }

        .back-button:hover {
            color: white;
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .reading-progress {
            position: fixed;
            top: 0;
            left: 0;
            width: 0%;
            height: 4px;
            background: var(--primary-gradient);
            z-index: 1000;
            transition: width 0.3s ease;
        }

        .article-stats {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1.5rem;
            background: var(--bg-light);
            border-radius: 15px;
            margin: 2rem 0;
        }

        .stat-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        .share-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }

        .share-btn {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            border: none;
            color: white;
            font-size: 1.25rem;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .share-btn.facebook { background: #3b5998; }
        .share-btn.twitter { background: #1da1f2; }
        .share-btn.linkedin { background: #0077b5; }
        .share-btn.whatsapp { background: #25d366; }

        .share-btn:hover {
            transform: translateY(-3px) scale(1.1);
        }

        @media (max-width: 768px) {
            .article-hero-content {
                padding: 2rem;
            }
            
            .article-hero-title {
                font-size: 1.75rem;
            }
            
            .article-content-wrapper {
                padding: 2rem;
            }
            
            .sidebar-card {
                padding: 1.5rem;
            }
        }
    </style>
</head>

<body>
    <div class="reading-progress"></div>
    
    <?php include 'includes/header.php'; ?>

    <div class="container my-5">
        <!-- Article Hero Section -->
        <div class="article-hero">
            <?php if ($article['featured_image']): ?>
                <img src="assets/images/<?php echo htmlspecialchars($article['featured_image']); ?>" 
                     class="article-hero-image" 
                     alt="<?php echo htmlspecialchars($article['title']); ?>">
            <?php else: ?>
                <div class="article-hero-image" style="background: var(--primary-gradient);"></div>
            <?php endif; ?>
            
            <div class="article-hero-content">
                <?php if (isset($article['category_name']) && $article['category_name']): ?>
                    <a href="?category=<?php echo htmlspecialchars($article['category_slug']); ?>" 
                       class="category-badge-large">
                        <i class="<?php echo htmlspecialchars($article['category_icon'] ?? 'fas fa-folder'); ?>"></i>
                        <?php echo htmlspecialchars($article['category_name']); ?>
                    </a>
                <?php endif; ?>
                
                <h1 class="article-hero-title"><?php echo htmlspecialchars($article['title']); ?></h1>
                
                <div class="article-meta">
                    <div class="article-meta-item">
                        <i class="fas fa-user"></i>
                        <span><?php echo htmlspecialchars($article['author_name'] ?? 'Admin'); ?></span>
                    </div>
                                         <div class="article-meta-item">
                         <i class="fas fa-calendar"></i>
                         <span><?php 
                             $display_date = $article['published_at'] ?: $article['created_at'];
                             echo format_date($display_date); 
                         ?></span>
                     </div>
                    <div class="article-meta-item">
                        <i class="fas fa-clock"></i>
                        <span><?php echo $article['reading_time']; ?> dk okuma</span>
                    </div>
                    <div class="article-meta-item">
                        <i class="fas fa-eye"></i>
                        <span><?php echo number_format($article['view_count']); ?> görüntüleme</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Main Content -->
            <div class="col-lg-8">
                <div class="article-content-wrapper">
                    <div class="article-content">
                        <?php echo nl2br(htmlspecialchars($article['content'])); ?>
                    </div>

                    <!-- Article Stats -->
                    <div class="article-stats">
                        <div class="stat-item">
                            <i class="fas fa-eye"></i>
                            <span><?php echo number_format($article['view_count']); ?> görüntüleme</span>
                        </div>
                        <?php if ($article['updated_at'] != $article['created_at']): ?>
                        <div class="stat-item">
                            <i class="fas fa-edit"></i>
                            <span>Son güncelleme: <?php echo format_date($article['updated_at']); ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="stat-item">
                            <i class="fas fa-share-alt"></i>
                            <span>Paylaş</span>
                        </div>
                    </div>

                    <!-- Tags -->
                    <?php if ($article['tags']): ?>
                        <div class="tag-cloud">
                            <?php
                            $tags = explode(',', $article['tags']);
                            foreach ($tags as $tag):
                                $tag = trim($tag);
                                if ($tag):
                            ?>
                                <a href="search.php?search=<?php echo urlencode($tag); ?>" class="tag-item">
                                    <i class="fas fa-tag"></i> <?php echo htmlspecialchars($tag); ?>
                                </a>
                            <?php 
                                endif;
                            endforeach; 
                            ?>
                        </div>
                    <?php endif; ?>

                    <!-- Share Buttons -->
                    <div class="share-buttons">
                        <button class="share-btn facebook" onclick="shareOnFacebook()">
                            <i class="fab fa-facebook-f"></i>
                        </button>
                        <button class="share-btn twitter" onclick="shareOnTwitter()">
                            <i class="fab fa-twitter"></i>
                        </button>
                        <button class="share-btn linkedin" onclick="shareOnLinkedIn()">
                            <i class="fab fa-linkedin-in"></i>
                        </button>
                        <button class="share-btn whatsapp" onclick="shareOnWhatsApp()">
                            <i class="fab fa-whatsapp"></i>
                        </button>
                    </div>
                </div>

                <!-- Back Button -->
                <div class="mb-4">
                    <a href="index.php" class="back-button">
                        <i class="fas fa-arrow-left"></i> Ana Sayfaya Dön
                    </a>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="col-lg-4">
                <!-- Related Articles -->
                <?php if (!empty($related_articles)): ?>
                <div class="sidebar-card">
                    <h5><i class="fas fa-newspaper"></i> İlgili Makaleler</h5>
                    <div class="related-articles">
                        <?php foreach ($related_articles as $related): ?>
                            <a href="article.php?slug=<?php echo htmlspecialchars($related['slug']); ?>" 
                               class="related-article">
                                <?php if ($related['featured_image']): ?>
                                    <img src="assets/images/<?php echo htmlspecialchars($related['featured_image']); ?>" 
                                         class="related-article-image" 
                                         alt="<?php echo htmlspecialchars($related['title']); ?>">
                                <?php else: ?>
                                    <div class="related-article-image" style="background: var(--primary-gradient); display: flex; align-items: center; justify-content: center; color: white;">
                                        <i class="fas fa-newspaper"></i>
                                    </div>
                                <?php endif; ?>
                                <div class="related-article-content">
                                    <h6><?php echo htmlspecialchars($related['title']); ?></h6>
                                    <div class="related-article-meta">
                                        <i class="fas fa-calendar"></i>
                                        <?php echo format_date($related['created_at']); ?>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Quick Navigation -->
                <div class="sidebar-card">
                    <h5><i class="fas fa-compass"></i> Hızlı Erişim</h5>
                    <div class="d-grid gap-2">
                        <a href="index.php" class="btn btn-outline-primary">
                            <i class="fas fa-home"></i> Ana Sayfa
                        </a>
                        <a href="search.php" class="btn btn-outline-success">
                            <i class="fas fa-search"></i> Gelişmiş Arama
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Reading Progress Bar
        window.addEventListener('scroll', function() {
            const article = document.querySelector('.article-content');
            if (article) {
                const scrollTop = window.pageYOffset;
                const docHeight = document.documentElement.scrollHeight - window.innerHeight;
                const scrollPercent = (scrollTop / docHeight) * 100;
                document.querySelector('.reading-progress').style.width = scrollPercent + '%';
            }
        });

        // Share Functions
        function shareOnFacebook() {
            const url = encodeURIComponent(window.location.href);
            window.open(`https://www.facebook.com/sharer/sharer.php?u=${url}`, '_blank', 'width=600,height=400');
        }

        function shareOnTwitter() {
            const url = encodeURIComponent(window.location.href);
            const text = encodeURIComponent(document.title);
            window.open(`https://twitter.com/intent/tweet?url=${url}&text=${text}`, '_blank', 'width=600,height=400');
        }

        function shareOnLinkedIn() {
            const url = encodeURIComponent(window.location.href);
            window.open(`https://www.linkedin.com/sharing/share-offsite/?url=${url}`, '_blank', 'width=600,height=400');
        }

        function shareOnWhatsApp() {
            const url = encodeURIComponent(window.location.href);
            const text = encodeURIComponent(document.title);
            window.open(`https://wa.me/?text=${text} ${url}`, '_blank');
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

        // Enhanced mobile experience
        if ('ontouchstart' in window) {
            document.querySelectorAll('.related-article, .tag-item').forEach(element => {
                element.addEventListener('touchstart', function() {
                    this.style.transform = 'scale(0.98)';
                });
                
                element.addEventListener('touchend', function() {
                    setTimeout(() => {
                        this.style.transform = '';
                    }, 150);
                });
            });
        }
    </script>
</body>
</html> 