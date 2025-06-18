<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) : 'Blog Sistemi'; ?></title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="<?php echo ASSETS_URL; ?>/css/style.core.css" rel="stylesheet">
    <link href="<?php echo ASSETS_URL; ?>/css/theme.css" rel="stylesheet">
    <link href="<?php echo ASSETS_URL; ?>/css/mobile-first.css" rel="stylesheet">
    <?php if (isset($page_css)) echo $page_css; ?>
    
    <!-- Mobile Navigation JavaScript -->
    <script src="<?php echo ASSETS_URL; ?>/js/main.js" defer></script>
    <script src="<?php echo ASSETS_URL; ?>/js/mobile-navigation.js" defer></script>
</head>
<body>
    <header>
        <nav>
            <div class="logo">
                <a href="<?php echo BASE_URL; ?>/">Blog Sistemi</a>
            </div>
            <ul class="nav-links">
                <li><a href="<?php echo BASE_URL; ?>/">Ana Sayfa</a></li>
                <?php if (isset($categories) && !empty($categories)): ?>
                    <?php foreach ($categories as $cat): ?>
                        <li>
                            <a href="<?php echo BASE_URL; ?>/?category=<?php echo htmlspecialchars($cat['slug']); ?>">
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>
        </nav>
    </header>
    <main>
        <!-- Ana içerik buraya gelecek -->
    </main>
</body>
</html> 