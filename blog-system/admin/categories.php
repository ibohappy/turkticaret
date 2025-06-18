<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Güvenlik başlıklarını ayarla
set_security_headers();

check_admin();

$page_title = 'Kategori Yönetimi';
$message = '';
$message_type = '';

// Form gönderildi mi kontrol et
if ($_POST && isset($_POST['action']) && $_POST['action'] == 'add') {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $message = 'Güvenlik hatası! Lütfen formu yeniden gönderin.';
        $message_type = 'danger';
    } else {
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $color = clean_input($_POST['color']);
        $icon = clean_input($_POST['icon']);

        if (strlen($name) >= 2 && strlen($name) <= 100) {
            $slug = create_slug($name);
            
            // Slug benzersizliği kontrol et
            $check_query = "SELECT id FROM categories WHERE slug = :slug";
            $check_stmt = $pdo->prepare($check_query);
            $check_stmt->bindValue(':slug', $slug);
            $check_stmt->execute();
            
            if (!$check_stmt->fetch()) {
                try {
                    $insert_query = "INSERT INTO categories (name, slug, description, color, icon, is_active, sort_order) 
                                    VALUES (:name, :slug, :description, :color, :icon, 1, 999)";
                    $stmt = $pdo->prepare($insert_query);
                    $stmt->bindValue(':name', $name);
                    $stmt->bindValue(':slug', $slug);
                    $stmt->bindValue(':description', $description);
                    $stmt->bindValue(':color', $color);
                    $stmt->bindValue(':icon', $icon);
                    
                    if ($stmt->execute()) {
                        $message = 'Kategori başarıyla eklendi!';
                        $message_type = 'success';
                    } else {
                        $message = 'Kategori eklenirken hata oluştu!';
                        $message_type = 'danger';
                    }
                } catch (PDOException $e) {
                    $message = 'Veritabanı hatası: ' . $e->getMessage();
                    $message_type = 'danger';
                }
            } else {
                $message = 'Bu kategori adı zaten kullanılıyor!';
                $message_type = 'danger';
            }
        } else {
            $message = 'Kategori adı 2-100 karakter arasında olmalıdır!';
            $message_type = 'danger';
        }
    }
}

// Kategori silme işlemi
if (isset($_GET['delete']) && $_GET['delete'] && isset($_GET['id'])) {
    $category_id = intval($_GET['id']);
    
    try {
        // Önce bu kategoride makale var mı kontrol et
        $article_check = $pdo->prepare("SELECT COUNT(*) as count FROM articles WHERE category_id = :id");
        $article_check->bindValue(':id', $category_id);
        $article_check->execute();
        $article_count = $article_check->fetch()['count'];
        
        if ($article_count > 0) {
            // Makaleleri category_id = null yap
            $update_articles = $pdo->prepare("UPDATE articles SET category_id = NULL WHERE category_id = :id");
            $update_articles->bindValue(':id', $category_id);
            $update_articles->execute();
        }
        
        // Kategoriyi sil
        $delete_query = "DELETE FROM categories WHERE id = :id";
        $delete_stmt = $pdo->prepare($delete_query);
        $delete_stmt->bindValue(':id', $category_id);
        
        if ($delete_stmt->execute()) {
            $message = 'Kategori başarıyla silindi!';
            $message_type = 'success';
        } else {
            $message = 'Kategori silinirken hata oluştu!';
            $message_type = 'danger';
        }
    } catch (PDOException $e) {
        $message = 'Veritabanı hatası: ' . $e->getMessage();
        $message_type = 'danger';
    }
}

// Kategorileri getir
try {
    $categories_query = "SELECT c.*, COUNT(a.id) as article_count 
                        FROM categories c
                        LEFT JOIN articles a ON c.id = a.category_id AND a.status = 'published'
                        GROUP BY c.id
                        ORDER BY c.sort_order, c.name";
    $categories_stmt = $pdo->prepare($categories_query);
    $categories_stmt->execute();
    $categories = $categories_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $categories = [];
    error_log("Categories fetch error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - Admin Paneli</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-shield-alt"></i> Admin Paneli
            </a>
            <div class="navbar-nav ms-auto">
                <span class="navbar-text me-3">
                    <i class="fas fa-user"></i> Hoşgeldin, <?php echo htmlspecialchars($_SESSION['admin_username']); ?>
                </span>
                <a href="logout.php" class="btn btn-outline-light btn-sm">
                    <i class="fas fa-sign-out-alt"></i> Çıkış
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block bg-light sidebar">
                <div class="position-sticky pt-3">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard.php">
                                <i class="fas fa-tachometer-alt"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="articles.php">
                                <i class="fas fa-newspaper"></i> Makaleler
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="add-article.php">
                                <i class="fas fa-plus"></i> Yeni Makale
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="categories.php">
                                <i class="fas fa-folder"></i> Kategoriler
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../index.php" target="_blank">
                                <i class="fas fa-external-link-alt"></i> Siteyi Görüntüle
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="fas fa-folder"></i> Kategori Yönetimi</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                            <i class="fas fa-plus"></i> Yeni Kategori
                        </button>
                    </div>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show">
                        <i class="fas fa-<?php echo ($message_type === 'success') ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                        <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-list"></i> Mevcut Kategoriler</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($categories)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-folder-open fa-3x text-muted mb-3"></i>
                                <h4 class="text-muted">Henüz kategori eklenmemiş</h4>
                                <p class="text-muted">İlk kategoriyi eklemek için "Yeni Kategori" butonuna tıklayın.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>İkon</th>
                                            <th>Kategori Adı</th>
                                            <th>Açıklama</th>
                                            <th>Makale Sayısı</th>
                                            <th>Durum</th>
                                            <th>İşlemler</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($categories as $category): ?>
                                            <tr>
                                                <td>
                                                    <i class="<?php echo htmlspecialchars($category['icon']); ?>" 
                                                       style="color: <?php echo htmlspecialchars($category['color']); ?>"></i>
                                                </td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($category['name']); ?></strong>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($category['slug']); ?></small>
                                                </td>
                                                <td><?php echo htmlspecialchars($category['description'] ?: 'Açıklama yok'); ?></td>
                                                <td>
                                                    <span class="badge bg-info"><?php echo $category['article_count']; ?> makale</span>
                                                </td>
                                                <td>
                                                    <?php if ($category['is_active']): ?>
                                                        <span class="badge bg-success">Aktif</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger">Pasif</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <a href="../index.php?category=<?php echo htmlspecialchars($category['slug']); ?>" 
                                                           class="btn btn-outline-info" target="_blank" title="Kategoriye Git">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <a href="?delete=1&id=<?php echo $category['id']; ?>" 
                                                           class="btn btn-outline-danger" 
                                                           onclick="return confirm('Bu kategoriyi silmek istediğinizden emin misiniz?')"
                                                           title="Sil">
                                                            <i class="fas fa-trash"></i>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Yeni Kategori Modal -->
    <div class="modal fade" id="addCategoryModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus-circle"></i> Yeni Kategori Ekle</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="add">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="name" class="form-label">Kategori Adı *</label>
                            <input type="text" class="form-control" id="name" name="name" required maxlength="100">
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">Açıklama</label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <label for="color" class="form-label">Renk</label>
                                <input type="color" class="form-control form-control-color" id="color" name="color" value="#007bff">
                            </div>
                            <div class="col-md-6">
                                <label for="icon" class="form-label">İkon (Font Awesome)</label>
                                <input type="text" class="form-control" id="icon" name="icon" value="fas fa-folder" placeholder="fas fa-folder">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-primary">Kategori Ekle</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 