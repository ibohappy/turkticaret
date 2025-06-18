<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/database.php';
require_once 'includes/functions.php';

echo "<h1>🔍 Charset ve Collation Debug</h1>";

try {
    // 1. Veritabanı charset ve collation bilgileri
    echo "<div style='background: #e3f2fd; padding: 15px; margin: 10px 0; border-radius: 5px;'>";
    echo "<h2>📊 1. Veritabanı Charset/Collation</h2>";
    
    $db_info = $pdo->query("SELECT @@character_set_database, @@collation_database")->fetch(PDO::FETCH_NUM);
    echo "<p><strong>Database Charset:</strong> {$db_info[0]}</p>";
    echo "<p><strong>Database Collation:</strong> {$db_info[1]}</p>";
    
    $connection_info = $pdo->query("SELECT @@character_set_connection, @@collation_connection")->fetch(PDO::FETCH_NUM);
    echo "<p><strong>Connection Charset:</strong> {$connection_info[0]}</p>";
    echo "<p><strong>Connection Collation:</strong> {$connection_info[1]}</p>";
    echo "</div>";
    
    // 2. Articles tablosu yapısı
    echo "<div style='background: #f3e5f5; padding: 15px; margin: 10px 0; border-radius: 5px;'>";
    echo "<h2>🗃️ 2. Articles Tablosu Yapısı</h2>";
    
    $table_info = $pdo->query("SHOW CREATE TABLE articles")->fetch(PDO::FETCH_ASSOC);
    echo "<pre style='background: #f5f5f5; padding: 10px; border-radius: 3px; overflow-x: auto;'>";
    echo htmlspecialchars($table_info['Create Table']);
    echo "</pre>";
    echo "</div>";
    
    // 3. Örnek makaleler ve içerikleri
    echo "<div style='background: #e8f5e8; padding: 15px; margin: 10px 0; border-radius: 5px;'>";
    echo "<h2>📝 3. Mevcut Makaleler</h2>";
    
    $articles = $pdo->query("SELECT id, title, LEFT(content, 100) as content_preview, tags, status FROM articles ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($articles)) {
        echo "<p style='color: red;'>❌ Hiç makale bulunamadı!</p>";
    } else {
        echo "<p><strong>Toplam makale:</strong> " . count($articles) . "</p>";
        echo "<table border='1' style='width: 100%; border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>Başlık</th><th>İçerik (İlk 100 karakter)</th><th>Etiketler</th><th>Durum</th></tr>";
        
        foreach ($articles as $article) {
            echo "<tr>";
            echo "<td>{$article['id']}</td>";
            echo "<td>" . htmlspecialchars($article['title']) . "</td>";
            echo "<td>" . htmlspecialchars($article['content_preview']) . "...</td>";
            echo "<td>" . htmlspecialchars($article['tags']) . "</td>";
            echo "<td><span style='color: " . ($article['status'] == 'published' ? 'green' : 'orange') . ";'>{$article['status']}</span></td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    echo "</div>";
    
    // 4. Arama testleri
    echo "<div style='background: #fff3e0; padding: 15px; margin: 10px 0; border-radius: 5px;'>";
    echo "<h2>🔍 4. Arama Testleri</h2>";
    
    $test_terms = ['güvenli', 'güvenlik', 'PHP', 'web', 'geliştirme', 'javascript'];
    
    foreach ($test_terms as $term) {
        echo "<h4>Test Terimi: '$term'</h4>";
        
        // Test 1: Normal LIKE
        $normal_query = "SELECT id, title FROM articles WHERE status = 'published' AND (title LIKE :search OR content LIKE :search)";
        $normal_stmt = $pdo->prepare($normal_query);
        $normal_stmt->bindValue(':search', "%{$term}%");
        $normal_stmt->execute();
        $normal_results = $normal_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<p><strong>Normal LIKE:</strong> " . count($normal_results) . " sonuç</p>";
        
        // Test 2: Case-insensitive LIKE
        $case_query = "SELECT id, title FROM articles WHERE status = 'published' AND (LOWER(title) LIKE LOWER(:search) OR LOWER(content) LIKE LOWER(:search))";
        $case_stmt = $pdo->prepare($case_query);
        $case_stmt->bindValue(':search', "%{$term}%");
        $case_stmt->execute();
        $case_results = $case_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<p><strong>Case-insensitive LIKE:</strong> " . count($case_results) . " sonuç</p>";
        
        // Test 3: Binary search
        $binary_query = "SELECT id, title FROM articles WHERE status = 'published' AND (title LIKE BINARY :search OR content LIKE BINARY :search)";
        $binary_stmt = $pdo->prepare($binary_query);
        $binary_stmt->bindValue(':search', "%{$term}%");
        $binary_stmt->execute();
        $binary_results = $binary_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<p><strong>Binary LIKE:</strong> " . count($binary_results) . " sonuç</p>";
        
        // Sonuçları göster
        if (count($case_results) > 0) {
            echo "<ul>";
            foreach ($case_results as $result) {
                echo "<li>ID: {$result['id']} - {$result['title']}</li>";
            }
            echo "</ul>";
        }
        
        echo "<hr>";
    }
    echo "</div>";
    
    // 5. Karakter set testleri
    echo "<div style='background: #f0f4f8; padding: 15px; margin: 10px 0; border-radius: 5px;'>";
    echo "<h2>🔤 5. Karakter Set Testleri</h2>";
    
    // Türkçe karakter testleri
    $turkish_chars = ['ç', 'ğ', 'ı', 'ö', 'ş', 'ü', 'Ç', 'Ğ', 'I', 'İ', 'Ö', 'Ş', 'Ü'];
    
    echo "<h4>Türkçe Karakter Testleri:</h4>";
    foreach ($turkish_chars as $char) {
        $char_query = "SELECT COUNT(*) as count FROM articles WHERE status = 'published' AND (title LIKE :search OR content LIKE :search)";
        $char_stmt = $pdo->prepare($char_query);
        $char_stmt->bindValue(':search', "%{$char}%");
        $char_stmt->execute();
        $char_count = $char_stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        echo "<span style='margin: 5px; padding: 3px 8px; background: " . ($char_count > 0 ? '#e8f5e8' : '#ffebee') . "; border-radius: 3px;'>";
        echo "'$char': $char_count";
        echo "</span>";
    }
    echo "</div>";
    
    // 6. Collation test
    echo "<div style='background: #fce4ec; padding: 15px; margin: 10px 0; border-radius: 5px;'>";
    echo "<h2>🔀 6. Collation Testleri</h2>";
    
    // Farklı collation'larla test
    $collations = ['utf8mb4_general_ci', 'utf8mb4_unicode_ci', 'utf8mb4_turkish_ci'];
    
    foreach ($collations as $collation) {
        echo "<h4>Collation: $collation</h4>";
        try {
            $coll_query = "SELECT id, title FROM articles WHERE status = 'published' AND title COLLATE $collation LIKE :search";
            $coll_stmt = $pdo->prepare($coll_query);
            $coll_stmt->bindValue(':search', '%güvenlik%');
            $coll_stmt->execute();
            $coll_results = $coll_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo "<p>Sonuç: " . count($coll_results) . " makale</p>";
        } catch (PDOException $e) {
            echo "<p style='color: red;'>Hata: " . $e->getMessage() . "</p>";
        }
    }
    echo "</div>";
    
} catch (PDOException $e) {
    echo "<div style='background: #ffebee; padding: 15px; border: 1px solid #f44336; border-radius: 5px;'>";
    echo "<h3 style='color: #d32f2f;'>❌ Veritabanı Hatası</h3>";
    echo "<p><strong>Hata:</strong> " . $e->getMessage() . "</p>";
    echo "</div>";
}
?>

<div style="margin: 20px 0; padding: 15px; background: #f8f9fa; border-radius: 5px;">
    <h3>🔗 Test Linkleri</h3>
    <ul>
        <li><a href="debug_detailed_search.php">Detaylı Arama Debug</a></li>
        <li><a href="add_sample_articles.php">Örnek Makale Ekle</a></li>
        <li><a href="index.php">Ana Sayfa</a></li>
        <li><a href="search.php">Gelişmiş Arama</a></li>
    </ul>
</div> 