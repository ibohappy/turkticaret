<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/database.php';
require_once 'includes/functions.php';

echo "<h2>Arama Debug Sayfası</h2>";

// Test arama terimi
$test_search = 'güvenlik';
echo "<h3>Test Arama Terimi: '$test_search'</h3>";

try {
    // Tüm makaleleri listele
    echo "<h4>1. Tüm Yayınlanmış Makaleler:</h4>";
    $all_stmt = $pdo->prepare("SELECT id, title, status FROM articles WHERE status = 'published' ORDER BY id");
    $all_stmt->execute();
    $all_articles = $all_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<ul>";
    foreach ($all_articles as $article) {
        echo "<li>ID: {$article['id']} - {$article['title']} ({$article['status']})</li>";
    }
    echo "</ul>";
    echo "<p>Toplam makale: " . count($all_articles) . "</p>";
    
    // Arama testi
    echo "<h4>2. Arama Testi (LIKE '%$test_search%'):</h4>";
    $search_stmt = $pdo->prepare("
        SELECT a.id, a.title, a.content, a.tags 
        FROM articles a 
        WHERE a.status = 'published' 
        AND (a.title LIKE :search OR a.content LIKE :search OR a.tags LIKE :search)
    ");
    $search_stmt->execute([':search' => "%{$test_search}%"]);
    $search_results = $search_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<ul>";
    foreach ($search_results as $result) {
        echo "<li>ID: {$result['id']} - {$result['title']}</li>";
        echo "<small>İçerik: " . substr($result['content'], 0, 100) . "...</small><br>";
        echo "<small>Etiketler: {$result['tags']}</small><br><br>";
    }
    echo "</ul>";
    echo "<p>Arama sonucu: " . count($search_results) . "</p>";
    
    // Veritabanı yapısını kontrol et
    echo "<h4>3. Articles Tablosu Yapısı:</h4>";
    $structure_stmt = $pdo->prepare("DESCRIBE articles");
    $structure_stmt->execute();
    $columns = $structure_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<ul>";
    foreach ($columns as $column) {
        echo "<li>{$column['Field']} - {$column['Type']} - {$column['Null']} - {$column['Key']}</li>";
    }
    echo "</ul>";
    
    // Kategoriler kontrolü
    echo "<h4>4. Kategoriler:</h4>";
    $cat_stmt = $pdo->prepare("SELECT * FROM categories WHERE is_active = 1");
    $cat_stmt->execute();
    $categories = $cat_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<ul>";
    foreach ($categories as $cat) {
        echo "<li>{$cat['name']} ({$cat['slug']})</li>";
    }
    echo "</ul>";
    
    // Admin users kontrolü
    echo "<h4>5. Admin Users:</h4>";
    $admin_stmt = $pdo->prepare("SELECT id, username, full_name FROM admin_users");
    $admin_stmt->execute();
    $admins = $admin_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<ul>";
    foreach ($admins as $admin) {
        echo "<li>ID: {$admin['id']} - {$admin['username']} ({$admin['full_name']})</li>";
    }
    echo "</ul>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>Hata: " . $e->getMessage() . "</p>";
}
?>

<a href="search.php">Search.php'ye dön</a> | 
<a href="index.php">Ana Sayfaya dön</a> 