<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'search_engine_fixed.php';

echo "<h2>🔍 Detaylı Arama Debug</h2>";

// Test arama terimleri
$test_terms = ['güvenli', 'güvenlik', 'php', 'web'];

foreach ($test_terms as $term) {
    echo "<div style='background: #f8f9fa; padding: 15px; margin: 15px 0; border-radius: 5px;'>";
    echo "<h3>Test: '$term'</h3>";
    
    // 1. Veritabanındaki ham veriyi kontrol et
    echo "<h4>1. Veritabanı Kontrolü:</h4>";
    try {
        $raw_stmt = $pdo->prepare("
            SELECT id, title, content, tags 
            FROM articles 
            WHERE status = 'published' 
            AND (
                title LIKE :term 
                OR content LIKE :term 
                OR tags LIKE :term
            )
        ");
        $raw_stmt->execute([':term' => "%{$term}%"]);
        $raw_results = $raw_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<p>Ham SQL sonucu: " . count($raw_results) . " makale</p>";
        if (!empty($raw_results)) {
            echo "<ul>";
            foreach ($raw_results as $article) {
                echo "<li><strong>{$article['title']}</strong>";
                echo "<br><small>İçerik: " . substr($article['content'], 0, 100) . "...</small>";
                echo "<br><small>Etiketler: {$article['tags']}</small></li>";
            }
            echo "</ul>";
        }
    } catch (PDOException $e) {
        echo "<p style='color: red;'>Veritabanı hatası: " . $e->getMessage() . "</p>";
    }
    
    // 2. Arama motoru sonuçlarını kontrol et
    echo "<h4>2. Arama Motoru Sonuçları:</h4>";
    try {
        $search_engine = new AdvancedSearchEngine($pdo);
        $search_result = $search_engine->search($term, ['debug' => true]);
        
        echo "<p>Arama motoru sonucu: " . $search_result['total_results'] . " makale</p>";
        if (!empty($search_result['results'])) {
            echo "<ul>";
            foreach ($search_result['results'] as $article) {
                echo "<li><strong>{$article['title']}</strong>";
                if (isset($article['relevance_score'])) {
                    echo " (Alaka düzeyi: {$article['relevance_score']})";
                }
                echo "</li>";
            }
            echo "</ul>";
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>Arama motoru hatası: " . $e->getMessage() . "</p>";
    }
    
    // 3. Karakter kodlaması kontrolü
    echo "<h4>3. Karakter Kodlaması Kontrolü:</h4>";
    try {
        $charset_stmt = $pdo->query("SHOW VARIABLES LIKE 'character_set%'");
        $collation_stmt = $pdo->query("SHOW VARIABLES LIKE 'collation%'");
        
        echo "<strong>Karakter Setleri:</strong><br>";
        while ($row = $charset_stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "{$row['Variable_name']}: {$row['Value']}<br>";
        }
        
        echo "<br><strong>Collation Ayarları:</strong><br>";
        while ($row = $collation_stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "{$row['Variable_name']}: {$row['Value']}<br>";
        }
    } catch (PDOException $e) {
        echo "<p style='color: red;'>Karakter seti kontrolü hatası: " . $e->getMessage() . "</p>";
    }
    
    echo "</div>";
}

// 4. Veritabanı tablo yapısını kontrol et
echo "<div style='background: #e3f2fd; padding: 15px; margin: 15px 0; border-radius: 5px;'>";
echo "<h4>4. Tablo Yapısı Kontrolü:</h4>";
try {
    $tables_stmt = $pdo->query("
        SELECT TABLE_NAME, TABLE_COLLATION
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME IN ('articles', 'categories', 'search_logs')
    ");
    
    echo "<strong>Tablo Karakter Setleri:</strong><br>";
    while ($table = $tables_stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "{$table['TABLE_NAME']}: {$table['TABLE_COLLATION']}<br>";
        
        $columns_stmt = $pdo->query("
            SELECT COLUMN_NAME, DATA_TYPE, CHARACTER_SET_NAME, COLLATION_NAME
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = '{$table['TABLE_NAME']}'
            AND DATA_TYPE IN ('varchar', 'text', 'char')
        ");
        
        echo "<ul>";
        while ($column = $columns_stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "<li>{$column['COLUMN_NAME']} ({$column['DATA_TYPE']})";
            if ($column['CHARACTER_SET_NAME']) {
                echo " - {$column['CHARACTER_SET_NAME']} / {$column['COLLATION_NAME']}";
            }
            echo "</li>";
        }
        echo "</ul>";
    }
} catch (PDOException $e) {
    echo "<p style='color: red;'>Tablo yapısı kontrolü hatası: " . $e->getMessage() . "</p>";
}
echo "</div>";

// 5. Test Linkleri
echo "<div style='margin: 20px 0;'>";
echo "<h4>🔗 Test Linkleri:</h4>";
echo "<ul>";
echo "<li><a href='search.php'>Gelişmiş Arama Sayfası</a></li>";
echo "<li><a href='debug_charset_collation.php'>Karakter Seti Debug</a></li>";
echo "<li><a href='check_articles.php'>Makale Kontrolü</a></li>";
echo "</ul>";
echo "</div>";
?> 