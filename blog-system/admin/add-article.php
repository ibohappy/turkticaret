<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Güvenlik başlıklarını ayarla
set_security_headers();

check_admin();

$page_title = 'Yeni Makale Ekle';
$message = '';
$message_type = '';
$validation_errors = [];

// Mevcut kategorileri getir
try {
    $categories_query = "SELECT id, name FROM categories WHERE is_active = 1 ORDER BY sort_order, name";
    $categories_stmt = $pdo->prepare($categories_query);
    $categories_stmt->execute();
    $categories = $categories_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $categories = [];
    error_log("Categories fetch error: " . $e->getMessage());
}

if ($_POST) {
    // CSRF token kontrolü
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $message = 'Güvenlik hatası! Lütfen formu yeniden gönderin.';
        $message_type = 'danger';
    } else {
        // Form verilerini al ve temizle
        $title = isset($_POST['title']) ? trim($_POST['title']) : '';
        $content = isset($_POST['content']) ? trim($_POST['content']) : '';
        $status = isset($_POST['status']) ? clean_input($_POST['status']) : 'draft';
        $tags = isset($_POST['tags']) ? clean_input($_POST['tags']) : '';
        $meta_description = isset($_POST['meta_description']) ? clean_input($_POST['meta_description']) : '';
        $category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : null;
        $new_category_name = isset($_POST['new_category_name']) ? trim($_POST['new_category_name']) : '';
        
        // Boş category_id'yi NULL yap
        if ($category_id == 0) {
            $category_id = null;
        }
        
        // Form validasyonu
        $title_errors = FormValidator::validate_title($title);
        $content_errors = FormValidator::validate_content($content);
        
        $validation_errors = array_merge($title_errors, $content_errors);
        
        // Status validasyonu
        if (!in_array($status, ['draft', 'published'])) {
            $validation_errors[] = 'Geçersiz makale durumu seçildi.';
        }
        
        // Meta description validasyonu
        if (!empty($meta_description) && strlen($meta_description) > 160) {
            $validation_errors[] = 'Meta açıklama 160 karakterden uzun olamaz.';
        }
        
        // Tags validasyonu
        if (!empty($tags) && strlen($tags) > 500) {
            $validation_errors[] = 'Etiketler çok uzun. Maksimum 500 karakter olmalıdır.';
        }
        
        // Kategori işlemleri
        if (!empty($new_category_name)) {
            // Yeni kategori oluştur
            $category_slug = create_slug($new_category_name);
            
            // Kategori slug'ının benzersiz olup olmadığını kontrol et
            $category_check = $pdo->prepare("SELECT id FROM categories WHERE slug = :slug");
            $category_check->bindValue(':slug', $category_slug);
            $category_check->execute();
            
            if ($category_check->fetch()) {
                $validation_errors[] = 'Bu kategori zaten mevcut!';
            } else {
                try {
                    $category_insert = $pdo->prepare("INSERT INTO categories (name, slug, is_active, sort_order) VALUES (:name, :slug, 1, 999)");
                    $category_insert->bindValue(':name', $new_category_name);
                    $category_insert->bindValue(':slug', $category_slug);
                    
                    if ($category_insert->execute()) {
                        $category_id = $pdo->lastInsertId();
                    } else {
                        $validation_errors[] = 'Yeni kategori oluşturulamadı!';
                    }
                } catch (PDOException $e) {
                    error_log("Category creation error: " . $e->getMessage());
                    $validation_errors[] = 'Kategori oluşturulurken hata oluştu: ' . $e->getMessage();
                }
            }
        }
        
        if (empty($validation_errors)) {
            // İçeriği güvenli temizle (HTML editör için)
            $content = clean_html_content($content);
            
            // Slug oluştur
            $slug = create_slug($title);
            
            // Slug'un benzersiz olup olmadığını kontrol et
            try {
                $slug_check = $pdo->prepare("SELECT id FROM articles WHERE slug = :slug");
                $slug_check->bindValue(':slug', $slug);
                $slug_check->execute();
                
                if ($slug_check->fetch()) {
                    $slug = $slug . '-' . time();
                }
                
                $featured_image = '';
                
                // Resim yükleme işlemi - Güvenli dosya yükleme
                if (isset($_FILES['featured_image']) && $_FILES['featured_image']['error'] == UPLOAD_ERR_OK) {
                    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                    $max_size = 5 * 1024 * 1024; // 5MB
                    
                    $upload_result = secure_file_upload(
                        $_FILES['featured_image'],
                        $allowed_types,
                        $max_size,
                        '../assets/images',
                        $slug
                    );
                    
                    if ($upload_result['success']) {
                        $featured_image = $upload_result['filename'];
                    } else {
                        $validation_errors = array_merge($validation_errors, $upload_result['errors']);
                    }
                }
                
                if (empty($validation_errors)) {
                    // Veritabanına ekleme işlemi
                    $query = "INSERT INTO articles 
                             (title, slug, content, featured_image, status, tags, meta_description, category_id, author_id) 
                             VALUES (:title, :slug, :content, :featured_image, :status, :tags, :meta_description, :category_id, :author_id)";
                    
                    $stmt = $pdo->prepare($query);
                    $stmt->bindValue(':title', $title);
                    $stmt->bindValue(':slug', $slug);
                    $stmt->bindValue(':content', $content);
                    $stmt->bindValue(':featured_image', $featured_image);
                    $stmt->bindValue(':status', $status);
                    $stmt->bindValue(':tags', $tags);
                    $stmt->bindValue(':meta_description', $meta_description);
                    if ($category_id === null) {
                        $stmt->bindValue(':category_id', null, PDO::PARAM_NULL);
                    } else {
                        $stmt->bindValue(':category_id', $category_id, PDO::PARAM_INT);
                    }
                    $stmt->bindValue(':author_id', isset($_SESSION['admin_user_id']) ? $_SESSION['admin_user_id'] : 1, PDO::PARAM_INT);
                    
                    if ($stmt->execute()) {
                        // Kategori makale sayısını güncelle
                        if ($category_id) {
                            try {
                                $update_category = $pdo->prepare("UPDATE categories SET article_count = (SELECT COUNT(*) FROM articles WHERE category_id = :category_id AND status = 'published') WHERE id = :category_id");
                                $update_category->bindValue(':category_id', $category_id, PDO::PARAM_INT);
                                $update_category->execute();
                            } catch (PDOException $e) {
                                error_log("Category count update error: " . $e->getMessage());
                            }
                        }
                        
                        $message = 'Makale başarıyla eklendi!';
                        $message_type = 'success';
                        
                        // Başarılı ekleme sonrası formu temizle
                        $_POST = array();
                        
                        // 2 saniye sonra makaleler sayfasına yönlendir
                        echo "<script>
                                setTimeout(function() {
                                    window.location.href = 'articles.php';
                                }, 2000);
                              </script>";
                    } else {
                        $validation_errors[] = 'Makale eklenirken bir hata oluştu.';
                    }
                }
            } catch (PDOException $e) {
                error_log("Add article error: " . $e->getMessage());
                $validation_errors[] = 'Veritabanı hatası oluştu: ' . $e->getMessage();
            }
        }
        
        if (!empty($validation_errors)) {
            $message = implode('<br>', $validation_errors);
            $message_type = 'danger';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - Güvenli Blog Sistemi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .validation-error {
            color: #dc3545;
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }
        .character-count {
            font-size: 0.875rem;
            color: #6c757d;
            float: right;
        }
        .form-control.is-invalid {
            border-color: #dc3545;
        }
        .preview-image {
            max-width: 200px;
            max-height: 150px;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 5px;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-shield-alt"></i> Güvenli Admin Paneli
            </a>
            <div class="navbar-nav ms-auto">
                <span class="navbar-text me-3">
                    <i class="fas fa-user"></i> Hoşgeldin, <?php echo htmlspecialchars($_SESSION['admin_username'] ?? 'Admin'); ?>
                </span>
                <a href="logout.php" class="btn btn-outline-light btn-sm">
                    <i class="fas fa-sign-out-alt"></i> Güvenli Çıkış
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
                            <a class="nav-link active" href="add-article.php">
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
                    <h1 class="h2"><i class="fas fa-plus-circle"></i> Yeni Makale Ekle</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="articles.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left"></i> Geri Dön
                        </a>
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
                        <h5 class="mb-0"><i class="fas fa-edit"></i> Makale Bilgileri</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate id="articleForm">
                            <?php echo csrf_field(); ?>
                            
                            <div class="row">
                                <div class="col-md-8">
                                    <!-- Makale Başlığı -->
                                    <div class="mb-4">
                                        <label for="title" class="form-label">
                                            <i class="fas fa-heading"></i> Makale Başlığı *
                                        </label>
                                        <input type="text" 
                                               class="form-control <?php echo in_array('Makale başlığı', array_map(function($e) { return substr($e, 0, 14); }, $validation_errors)) ? 'is-invalid' : ''; ?>" 
                                               id="title" name="title" 
                                               value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>" 
                                               maxlength="255"
                                               required>
                                        <div class="character-count">
                                            <span id="titleCount">0</span>/255 karakter
                                        </div>
                                        <div class="invalid-feedback">
                                            Makale başlığı 5-255 karakter arasında olmalıdır.
                                        </div>
                                        <small class="text-muted">Makale başlığı minimum 5, maksimum 255 karakter olmalıdır.</small>
                                    </div>



                                    <!-- Makale İçeriği -->
                                    <div class="mb-4">
                                        <label for="content" class="form-label">
                                            <i class="fas fa-paragraph"></i> Makale İçeriği *
                                        </label>
                                        <textarea class="form-control <?php echo in_array('Makale içeriği', array_map(function($e) { return substr($e, 0, 15); }, $validation_errors)) ? 'is-invalid' : ''; ?>" 
                                                  id="content" name="content" rows="15" 
                                                  required><?php echo isset($_POST['content']) ? htmlspecialchars($_POST['content']) : ''; ?></textarea>
                                        <div class="character-count">
                                            <span id="contentCount">0</span> karakter
                                        </div>
                                        <div class="invalid-feedback">
                                            Makale içeriği minimum 50 karakter olmalıdır.
                                        </div>
                                        <small class="text-muted">Makale içeriği minimum 50 karakter olmalıdır. HTML etiketleri otomatik olarak temizlenecektir.</small>
                                    </div>

                                    <!-- Meta Açıklama -->
                                    <div class="mb-4">
                                        <label for="meta_description" class="form-label">
                                            <i class="fas fa-search"></i> Meta Açıklama (SEO)
                                        </label>
                                        <textarea class="form-control" id="meta_description" name="meta_description" 
                                                  rows="3" maxlength="160" 
                                                  placeholder="SEO için kısa açıklama (max 160 karakter)"><?php echo isset($_POST['meta_description']) ? htmlspecialchars($_POST['meta_description']) : ''; ?></textarea>
                                        <div class="character-count">
                                            <span id="metaCount">0</span>/160 karakter
                                        </div>
                                        <small class="text-muted">Google arama sonuçlarında görünecek açıklama</small>
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <!-- Makale Durumu -->
                                    <div class="mb-4">
                                        <label for="status" class="form-label">
                                            <i class="fas fa-toggle-on"></i> Makale Durumu *
                                        </label>
                                        <select class="form-select" id="status" name="status" required>
                                            <option value="draft" <?php echo (isset($_POST['status']) && $_POST['status'] == 'draft') ? 'selected' : ''; ?>>
                                                <i class="fas fa-edit"></i> Taslak
                                            </option>
                                            <option value="published" <?php echo (isset($_POST['status']) && $_POST['status'] == 'published') ? 'selected' : ''; ?>>
                                                <i class="fas fa-check"></i> Yayınla
                                            </option>
                                        </select>
                                    </div>

                                    <!-- Kategori Seçimi -->
                                    <div class="mb-4">
                                        <label for="category_id" class="form-label">
                                            <i class="fas fa-folder"></i> Kategori
                                        </label>
                                        <select class="form-select" id="category_id" name="category_id">
                                            <option value="">Kategori Seçin (Opsiyonel)</option>
                                            <?php foreach ($categories as $category): ?>
                                                <option value="<?php echo $category['id']; ?>" 
                                                        <?php echo (isset($_POST['category_id']) && $_POST['category_id'] == $category['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($category['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <small class="text-muted">Mevcut kategorilerden birini seçin veya aşağıda yeni kategori oluşturun</small>
                                    </div>

                                    <!-- Yeni Kategori Ekleme -->
                                    <div class="mb-4">
                                        <label for="new_category_name" class="form-label">
                                            <i class="fas fa-plus-circle"></i> Yeni Kategori Oluştur
                                        </label>
                                        <input type="text" class="form-control" id="new_category_name" name="new_category_name" 
                                               value="<?php echo isset($_POST['new_category_name']) ? htmlspecialchars($_POST['new_category_name']) : ''; ?>"
                                               maxlength="100"
                                               placeholder="Yeni kategori adı">
                                        <small class="text-muted">Yeni kategori oluşturmak için buraya yazın (yukarıdaki seçimi geçersiz kılar)</small>
                                    </div>

                                    <!-- Öne Çıkan Resim -->
                                    <div class="mb-4">
                                        <label for="featured_image" class="form-label">
                                            <i class="fas fa-image"></i> Öne Çıkan Resim
                                        </label>
                                        <input type="file" class="form-control" id="featured_image" name="featured_image" 
                                               accept="image/jpeg,image/png,image/gif,image/webp">
                                        <small class="text-muted">Max 5MB, JPG/PNG/GIF/WebP formatları destekleniyor</small>
                                        <div id="image-preview" style="display: none;" class="mt-2"></div>
                                    </div>

                                    <!-- Etiketler -->
                                    <div class="mb-4">
                                        <label for="tags" class="form-label">
                                            <i class="fas fa-tags"></i> Etiketler
                                        </label>
                                        <input type="text" class="form-control" id="tags" name="tags" 
                                               value="<?php echo isset($_POST['tags']) ? htmlspecialchars($_POST['tags']) : ''; ?>"
                                               maxlength="500"
                                               placeholder="php, mysql, web geliştirme">
                                        <div class="character-count">
                                            <span id="tagsCount">0</span>/500 karakter
                                        </div>
                                        <small class="text-muted">Virgülle ayırarak birden fazla etiket ekleyebilirsiniz</small>
                                    </div>

                                    <!-- Form Butonları -->
                                    <div class="d-grid gap-2">
                                        <button type="submit" class="btn btn-primary btn-lg">
                                            <i class="fas fa-save"></i> Makaleyi Güvenli Kaydet
                                        </button>
                                        <a href="articles.php" class="btn btn-outline-secondary">
                                            <i class="fas fa-times"></i> İptal Et
                                        </a>
                                    </div>
                                    
                                    <!-- Güvenlik Bilgisi -->
                                    <div class="mt-3 p-3 bg-light rounded">
                                        <h6><i class="fas fa-shield-alt text-success"></i> Güvenlik Özellikleri:</h6>
                                        <ul class="list-unstyled mb-0 small">
                                            <li><i class="fas fa-check text-success"></i> CSRF Koruması</li>
                                            <li><i class="fas fa-check text-success"></i> XSS Koruması</li>
                                            <li><i class="fas fa-check text-success"></i> Form Validasyonu</li>
                                            <li><i class="fas fa-check text-success"></i> Güvenli Dosya Yükleme</li>
                                        </ul>
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
        // Form validation
        (function() {
            'use strict';
            window.addEventListener('load', function() {
                const form = document.getElementById('articleForm');
                
                // Character count functions
                function updateCharCount(input, countElement, maxLength = null) {
                    const count = input.value.length;
                    countElement.textContent = count;
                    if (maxLength && count > maxLength * 0.9) {
                        countElement.style.color = '#dc3545';
                    } else {
                        countElement.style.color = '#6c757d';
                    }
                }
                
                // Title character count
                const titleInput = document.getElementById('title');
                const titleCount = document.getElementById('titleCount');
                titleInput.addEventListener('input', function() {
                    updateCharCount(this, titleCount, 255);
                });
                updateCharCount(titleInput, titleCount, 255);
                
                // Content character count
                const contentInput = document.getElementById('content');
                const contentCount = document.getElementById('contentCount');
                contentInput.addEventListener('input', function() {
                    updateCharCount(this, contentCount);
                });
                updateCharCount(contentInput, contentCount);
                
                // Meta description character count
                const metaInput = document.getElementById('meta_description');
                const metaCount = document.getElementById('metaCount');
                metaInput.addEventListener('input', function() {
                    updateCharCount(this, metaCount, 160);
                });
                updateCharCount(metaInput, metaCount, 160);
                
                // Tags character count
                const tagsInput = document.getElementById('tags');
                const tagsCount = document.getElementById('tagsCount');
                tagsInput.addEventListener('input', function() {
                    updateCharCount(this, tagsCount, 500);
                });
                updateCharCount(tagsInput, tagsCount, 500);
                
                // Image preview
                document.getElementById('featured_image').addEventListener('change', function(e) {
                    const file = e.target.files[0];
                    const preview = document.getElementById('image-preview');
                    
                    if (file) {
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            preview.innerHTML = '<img src="' + e.target.result + '" class="preview-image" alt="Preview">';
                            preview.style.display = 'block';
                        };
                        reader.readAsDataURL(file);
                    } else {
                        preview.style.display = 'none';
                    }
                });
                
                // Form validation
                form.addEventListener('submit', function(event) {
                    const title = titleInput.value.trim();
                    const content = contentInput.value.trim();
                    
                    let isValid = true;
                    
                    // Title validation
                    if (title.length < 5 || title.length > 255) {
                        titleInput.classList.add('is-invalid');
                        isValid = false;
                    } else {
                        titleInput.classList.remove('is-invalid');
                    }
                    
                    // Content validation (strip HTML for counting)
                    const tempDiv = document.createElement('div');
                    tempDiv.innerHTML = content;
                    const textContent = tempDiv.textContent || tempDiv.innerText || '';
                    
                    if (textContent.length < 50) {
                        contentInput.classList.add('is-invalid');
                        isValid = false;
                    } else {
                        contentInput.classList.remove('is-invalid');
                    }
                    
                    if (!isValid) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    
                    form.classList.add('was-validated');
                }, false);
            }, false);
        })();
    </script>
</body>
</html> 