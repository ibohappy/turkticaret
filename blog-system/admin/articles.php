<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

check_admin();

$page_title = 'Makale Yönetimi';

// Silme işlemi
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $delete_id = (int)$_GET['id'];
    
    // Önce resmi sil
    $img_query = "SELECT featured_image FROM articles WHERE id = :id";
    $img_stmt = $pdo->prepare($img_query);
    $img_stmt->bindValue(':id', $delete_id, PDO::PARAM_INT);
    $img_stmt->execute();
    $img_result = $img_stmt->fetch();
    
    if ($img_result && $img_result['featured_image']) {
        $img_path = '../assets/images/' . $img_result['featured_image'];
        if (file_exists($img_path)) {
            unlink($img_path);
        }
    }
    
    // Makaleyi sil
    $delete_query = "DELETE FROM articles WHERE id = :id";
    $delete_stmt = $pdo->prepare($delete_query);
    $delete_stmt->bindValue(':id', $delete_id, PDO::PARAM_INT);
    $delete_stmt->execute();
    
    header('Location: articles.php?deleted=1');
    exit();
}

// Makaleleri al
$query = "SELECT a.*, c.name as category_name, c.color as category_color 
          FROM articles a 
          LEFT JOIN categories c ON a.category_id = c.id 
          ORDER BY a.created_at DESC";
$articles = $pdo->query($query)->fetchAll();

?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Blog Sistemi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-tachometer-alt"></i> Admin Paneli
            </a>
            <div class="navbar-nav ms-auto">
                <span class="navbar-text me-3">
                    Hoşgeldin, <?php echo htmlspecialchars($_SESSION['admin_username']); ?>
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
                            <a class="nav-link active" href="articles.php">
                                <i class="fas fa-newspaper"></i> Makaleler
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="add-article.php">
                                <i class="fas fa-plus"></i> Yeni Makale
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
                    <h1 class="h2">Makale Yönetimi</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="add-article.php" class="btn btn-success">
                            <i class="fas fa-plus"></i> Yeni Makale Ekle
                        </a>
                    </div>
                </div>

                <?php if (isset($_GET['deleted'])): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        Makale başarıyla silindi.
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (empty($articles)): ?>
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-newspaper fa-3x text-muted mb-3"></i>
                            <h4>Henüz makale yok</h4>
                            <p class="text-muted">İlk makalenizi eklemek için aşağıdaki butona tıklayın.</p>
                            <a href="add-article.php" class="btn btn-primary">
                                <i class="fas fa-plus"></i> İlk Makaleni Ekle
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-list"></i> Tüm Makaleler (<?php echo count($articles); ?>)</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Başlık</th>
                                            <th>Durum</th>
                                            <th>Oluşturulma</th>
                                            <th>Güncellenme</th>
                                            <th>İşlemler</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($articles as $article): ?>
                                            <tr>
                                                <td><?php echo $article['id']; ?></td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($article['title']); ?></strong>
                                                    <?php if ($article['featured_image']): ?>
                                                        <br><small class="text-muted"><i class="fas fa-image"></i> Resim var</small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($article['status'] == 'published'): ?>
                                                        <span class="badge bg-success">Yayınlandı</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning">Taslak</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <small><?php echo format_date($article['created_at']); ?></small>
                                                </td>
                                                <td>
                                                    <small><?php echo format_date($article['updated_at']); ?></small>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <a href="edit-article.php?id=<?php echo $article['id']; ?>" 
                                                           class="btn btn-sm btn-outline-primary" 
                                                           title="Düzenle">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        
                                                        <?php if ($article['status'] == 'published'): ?>
                                                            <a href="../article.php?slug=<?php echo $article['slug']; ?>" 
                                                               class="btn btn-sm btn-outline-success" 
                                                               target="_blank" 
                                                               title="Görüntüle">
                                                                <i class="fas fa-eye"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                        
                                                        <a href="?delete=1&id=<?php echo $article['id']; ?>" 
                                                           class="btn btn-sm btn-outline-danger" 
                                                           title="Sil"
                                                           onclick="return confirm('Bu makaleyi silmek istediğinizden emin misiniz?')">
                                                            <i class="fas fa-trash"></i>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 