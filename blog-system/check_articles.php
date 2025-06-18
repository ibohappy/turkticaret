<?php
require_once 'config/database.php';

echo "<h2>Veritabanı Makale Kontrolü</h2>";

try {
    // Tüm makaleleri say
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM articles");
    $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    echo "<p>Toplam makale sayısı: <strong>$total</strong></p>";
    
    // Yayınlanmış makaleleri say
    $stmt = $pdo->query("SELECT COUNT(*) as published FROM articles WHERE status = 'published'");
    $published = $stmt->fetch(PDO::FETCH_ASSOC)['published'];
    echo "<p>Yayınlanmış makale sayısı: <strong>$published</strong></p>";
    
    if ($published == 0) {
        echo "<div style='background: #ffebee; padding: 15px; border: 1px solid #f44336; border-radius: 5px; margin: 10px 0;'>";
        echo "<h3 style='color: #d32f2f;'>⚠️ UYARI: Yayınlanmış makale bulunamadı!</h3>";
        echo "<p>Arama çalışması için en az bir yayınlanmış makale olmalı.</p>";
        echo "<p><strong>Çözüm:</strong> Admin panelinden makale ekleyin veya mevcut makalelerin durumunu 'published' yapın.</p>";
        echo "</div>";
        
        // Taslak makaleleri göster
        $stmt = $pdo->query("SELECT id, title, status FROM articles WHERE status = 'draft' LIMIT 5");
        $drafts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($drafts) > 0) {
            echo "<h4>Taslak Makaleler (Yayınlanabilir):</h4>";
            echo "<ul>";
            foreach ($drafts as $draft) {
                echo "<li>ID: {$draft['id']} - {$draft['title']} (Status: {$draft['status']})</li>";
            }
            echo "</ul>";
            echo "<p><em>Bu makaleleri admin panelinden 'published' durumuna getirebilirsiniz.</em></p>";
        }
    } else {
        // İlk 5 yayınlanmış makaleyi göster
        $stmt = $pdo->query("SELECT id, title, content FROM articles WHERE status = 'published' LIMIT 5");
        $articles = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<h4>Yayınlanmış Makaleler (İlk 5):</h4>";
        echo "<ul>";
        foreach ($articles as $article) {
            $excerpt = substr(strip_tags($article['content']), 0, 100);
            echo "<li><strong>{$article['title']}</strong><br>";
            echo "<small>$excerpt...</small></li><br>";
        }
        echo "</ul>";
        
        echo "<div style='background: #e8f5e8; padding: 15px; border: 1px solid #4caf50; border-radius: 5px; margin: 10px 0;'>";
        echo "<h3 style='color: #2e7d32;'>✅ Arama Sistemi Hazır!</h3>";
        echo "<p>Yayınlanmış makaleler mevcut. Arama sistemi çalışmalı.</p>";
        echo "</div>";
    }
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>Veritabanı hatası: " . $e->getMessage() . "</p>";
}
?>

<h3>Test Linkleri:</h3>
<ul>
    <li><a href="test_search_simple.php">Basit Arama Testi</a></li>
    <li><a href="debug_search.php">Detaylı Debug</a></li>
    <li><a href="index.php">Ana Sayfa</a></li>
    <li><a href="search.php">Gelişmiş Arama</a></li>
    <li><a href="admin/login.php">Admin Panel</a></li>
</ul> 