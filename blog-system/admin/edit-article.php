<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Güvenlik başlıklarını ayarla
set_security_headers();

check_admin();

$page_title = 'Makale Düzenle';
$message = '';
$message_type = '';
$validation_errors = [];

// Makale ID'sini al
$article_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($article_id <= 0) {
    header('Location: articles.php');
    exit();
}

// Form gönderildi mi kontrol et
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF token kontrolü
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $message = 'Güvenlik hatası! Lütfen formu yeniden gönderin.';
        $message_type = 'danger';
    } else {
    // Temel form alanlarını kontrol et
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $status = $_POST['status'] ?? '';
    $category_id = (int)($_POST['category_id'] ?? 0);
    $tags = trim($_POST['tags'] ?? '');
    $meta_description = trim($_POST['meta_description'] ?? '');
    
    // Form doğrulama
    if (strlen($title) < 5 || strlen($title) > 255) {
        $validation_errors[] = 'Makale başlığı 5-255 karakter arasında olmalıdır.';
    }
    
    if (strlen($content) < 50) {
        $validation_errors[] = 'Makale içeriği minimum 50 karakter olmalıdır.';
    }
    
    if (!in_array($status, ['published', 'draft'])) {
        $validation_errors[] = 'Geçersiz makale durumu.';
    }
    
    // Eğer hata yoksa güncellemeyi yap
    if (empty($validation_errors)) {
        try {
                // Mevcut makale bilgilerini al
                $current_query = "SELECT * FROM articles WHERE id = :id";
                $current_stmt = $pdo->prepare($current_query);
                $current_stmt->bindValue(':id', $article_id, PDO::PARAM_INT);
                $current_stmt->execute();
                $current_article = $current_stmt->fetch();
                
                if (!$current_article) {
                    $validation_errors[] = 'Makale bulunamadı.';
                } else {
            // Slug oluştur (eğer başlık değiştiyse)
                    $slug = $current_article['slug'];
                    if ($title !== $current_article['title']) {
                $new_slug = create_slug($title);
                
                // Slug'un benzersiz olup olmadığını kontrol et
                $slug_check = $pdo->prepare("SELECT id FROM articles WHERE slug = :slug AND id != :id");
                $slug_check->execute([':slug' => $new_slug, ':id' => $article_id]);
                
                if ($slug_check->fetch()) {
                    $slug = $new_slug . '-' . time();
                } else {
                    $slug = $new_slug;
                }
            }
            
            // Dosya yükleme işlemi
                    $featured_image = $current_article['featured_image'];
            if (isset($_FILES['featured_image']) && $_FILES['featured_image']['error'] === UPLOAD_ERR_OK) {
                $image = $_FILES['featured_image'];
                
                // Dosya türü kontrolü
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime_type = finfo_file($finfo, $image['tmp_name']);
                finfo_close($finfo);
                
                if (!in_array($mime_type, $allowed_types)) {
                    $validation_errors[] = 'Geçersiz dosya türü. Sadece JPG, PNG, GIF ve WebP formatları desteklenir.';
                }
                
                // Dosya boyutu kontrolü (5MB)
                if ($image['size'] > 5 * 1024 * 1024) {
                    $validation_errors[] = 'Dosya boyutu çok büyük. Maksimum 5MB olmalıdır.';
                }
                
                if (empty($validation_errors)) {
                    $image_name = generate_unique_filename($image['name']);
                    $upload_path = '../assets/images/' . $image_name;
                    
                    // Eski görseli sil
                            if ($current_article['featured_image'] && file_exists('../assets/images/' . $current_article['featured_image'])) {
                                unlink('../assets/images/' . $current_article['featured_image']);
                    }
                    
                    // Yeni görseli yükle
                    if (move_uploaded_file($image['tmp_name'], $upload_path)) {
                        $featured_image = $image_name;
                    } else {
                        $validation_errors[] = 'Dosya yüklenirken bir hata oluştu.';
                    }
                }
            }
            
            if (empty($validation_errors)) {
                        // Eski kategori ID'sini al (kategori sayılarını güncellemek için)
                        $old_category_id = $current_article['category_id'];
                        
                // Makaleyi güncelle
                $update_query = "UPDATE articles SET 
                    title = :title,
                    slug = :slug,
                    content = :content,
                    status = :status,
                    category_id = :category_id,
                    tags = :tags,
                    meta_description = :meta_description,
                    featured_image = :featured_image,
                    updated_at = NOW()
                    WHERE id = :id";
                
                $stmt = $pdo->prepare($update_query);
                        $success = $stmt->execute([
                    ':title' => $title,
                    ':slug' => $slug,
                    ':content' => $content,
                    ':status' => $status,
                    ':category_id' => $category_id ?: null,
                    ':tags' => $tags,
                    ':meta_description' => $meta_description,
                    ':featured_image' => $featured_image,
                    ':id' => $article_id
                ]);
                
                        if ($success) {
                            // Kategori değişikliği varsa makale sayılarını güncelle
                            if ($old_category_id != $category_id) {
                                try {
                                    // Eski kategorinin makale sayısını güncelle
                                    if ($old_category_id) {
                                        $update_old_cat = $pdo->prepare("UPDATE categories SET article_count = (SELECT COUNT(*) FROM articles WHERE category_id = :category_id AND status = 'published') WHERE id = :category_id");
                                        $update_old_cat->bindValue(':category_id', $old_category_id);
                                        $update_old_cat->execute();
                                    }
                                    
                                    // Yeni kategorinin makale sayısını güncelle
                                    if ($category_id) {
                                        $update_new_cat = $pdo->prepare("UPDATE categories SET article_count = (SELECT COUNT(*) FROM articles WHERE category_id = :category_id AND status = 'published') WHERE id = :category_id");
                                        $update_new_cat->bindValue(':category_id', $category_id);
                                        $update_new_cat->execute();
                                    }
                                } catch (PDOException $e) {
                                    error_log("Category count update error: " . $e->getMessage());
                                }
                            }
                            
                            $message = 'Makale başarıyla güncellendi!';
                            $message_type = 'success';
                        } else {
                            $validation_errors[] = 'Makale güncellenirken bir hata oluştu.';
                        }
                    }
            }
        } catch (PDOException $e) {
            error_log("Article update error: " . $e->getMessage());
            $validation_errors[] = 'Makale güncellenirken bir hata oluştu. Lütfen tekrar deneyin.';
            }
        }
        
        if (!empty($validation_errors)) {
            $message = implode('<br>', $validation_errors);
            $message_type = 'danger';
        }
    }
}

// Makaleyi veritabanından al
try {
    $query = "SELECT * FROM articles WHERE id = :id";
    $stmt = $pdo->prepare($query);
    $stmt->bindValue(':id', $article_id, PDO::PARAM_INT);
    $stmt->execute();
    $article = $stmt->fetch();
    
    if (!$article) {
        header('Location: articles.php?error=not_found');
        exit();
    }
    
    // Kategorileri al
    $categories_query = "SELECT * FROM categories WHERE is_active = 1 ORDER BY name";
    $categories = $pdo->query($categories_query)->fetchAll();
    
} catch (PDOException $e) {
    error_log("Article fetch error: " . $e->getMessage());
    header('Location: articles.php?error=db_error');
    exit();
}
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
                            <a class="nav-link" href="categories.php">
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
                    <h1 class="h2">Makale Düzenle: <?php echo htmlspecialchars($article['title']); ?></h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <a href="articles.php" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left"></i> Geri Dön
                            </a>
                            <?php if ($article['status'] == 'published'): ?>
                                <a href="../article.php?id=<?php echo $article['id']; ?>" class="btn btn-outline-success" target="_blank">
                                    <i class="fas fa-eye"></i> Görüntüle
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show">
                        <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-body">
                        <form action="" method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                            <?php echo csrf_field(); ?>
                            <div class="row">
                                <div class="col-md-8">
                                    <div class="mb-3">
                                        <label for="title" class="form-label">Makale Başlığı *</label>
                                        <input type="text" class="form-control" 
                                               id="title" name="title" 
                                               value="<?php echo htmlspecialchars($article['title']); ?>" required>
                                        <div class="invalid-feedback">
                                            Makale başlığı 5-255 karakter arasında olmalıdır.
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="content" class="form-label">Makale İçeriği *</label>
                                        <textarea class="form-control" 
                                                  id="content" name="content" rows="10" required><?php echo htmlspecialchars($article['content']); ?></textarea>
                                        <div class="invalid-feedback">
                                            Makale içeriği minimum 50 karakter olmalıdır.
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="meta_description" class="form-label">Meta Açıklama</label>
                                        <textarea class="form-control" id="meta_description" name="meta_description" 
                                                  rows="2"><?php echo htmlspecialchars($article['meta_description']); ?></textarea>
                                        <div class="form-text">SEO için kısa bir açıklama (maksimum 160 karakter)</div>
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="status" class="form-label">Durum *</label>
                                        <select class="form-select" id="status" name="status" required>
                                            <option value="draft" <?php echo $article['status'] == 'draft' ? 'selected' : ''; ?>>Taslak</option>
                                            <option value="published" <?php echo $article['status'] == 'published' ? 'selected' : ''; ?>>Yayınla</option>
                                        </select>
                                        <div class="invalid-feedback">
                                            Lütfen bir durum seçin.
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="category_id" class="form-label">Kategori</label>
                                        <select class="form-select" id="category_id" name="category_id">
                                            <option value="">Kategori Seçin (Opsiyonel)</option>
                                            <?php foreach ($categories as $category): ?>
                                                <option value="<?php echo $category['id']; ?>" 
                                                        <?php echo ($article['category_id'] == $category['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($category['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="form-text">Bu makaleyi bir kategori altında gruplandırın</div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="featured_image" class="form-label">Öne Çıkan Görsel</label>
                                        <?php if ($article['featured_image']): ?>
                                            <div class="mb-2">
                                                <img src="../assets/images/<?php echo htmlspecialchars($article['featured_image']); ?>" 
                                                     alt="Mevcut görsel" class="img-thumbnail" style="max-height: 200px;">
                                            </div>
                                        <?php endif; ?>
                                        <input type="file" class="form-control" id="featured_image" name="featured_image" 
                                               accept="image/jpeg,image/png,image/gif,image/webp">
                                        <div class="form-text">Maksimum 5MB. Desteklenen formatlar: JPG, PNG, GIF, WebP</div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="tags" class="form-label">Etiketler</label>
                                        <input type="text" class="form-control" id="tags" name="tags" 
                                               value="<?php echo htmlspecialchars($article['tags']); ?>" 
                                               placeholder="Etiketleri virgülle ayırın">
                                        <div class="form-text">Örnek: php, mysql, web geliştirme</div>
                                    </div>

                                    <div class="mb-3">
                                        <small class="text-muted">
                                            <strong>Oluşturulma:</strong> <?php echo format_date($article['created_at']); ?><br>
                                            <strong>Son Güncelleme:</strong> <?php echo format_date($article['updated_at']); ?><br>
                                            <strong>URL:</strong> <?php echo htmlspecialchars($article['slug']); ?>
                                        </small>
                                    </div>

                                    <div class="d-grid gap-2">
                                        <button type="submit" class="btn btn-primary btn-lg">
                                            <i class="fas fa-save"></i> Değişiklikleri Kaydet
                                        </button>
                                        <a href="articles.php" class="btn btn-outline-secondary">
                                            <i class="fas fa-times"></i> İptal Et
                                        </a>
                                        <hr>
                                        <a href="articles.php?delete=1&id=<?php echo $article['id']; ?>" 
                                           class="btn btn-outline-danger"
                                           onclick="return confirm('Bu makaleyi silmek istediğinizden emin misiniz?')">
                                            <i class="fas fa-trash"></i> Makaleyi Sil
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Form doğrulama için Bootstrap validation
    (function () {
        'use strict'
        var forms = document.querySelectorAll('.needs-validation')
        Array.prototype.slice.call(forms).forEach(function (form) {
            form.addEventListener('submit', function (event) {
                if (!form.checkValidity()) {
                    event.preventDefault()
                    event.stopPropagation()
                }
                form.classList.add('was-validated')
            }, false)
        })
    })()
    </script>
</body>
</html> 