<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

echo "<h2>📝 Örnek Makale Ekleme Scripti</h2>";

try {
    // Mevcut makale sayısını kontrol et
    $count_stmt = $pdo->query("SELECT COUNT(*) as total FROM articles WHERE status = 'published'");
    $published_count = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    echo "<p>Mevcut yayınlanmış makale sayısı: <strong>$published_count</strong></p>";
    
    if ($published_count > 0) {
        echo "<div style='background: #e8f5e8; padding: 15px; border-radius: 5px;'>";
        echo "<h3>✅ Zaten yeterli makale var!</h3>";
        echo "<p>Arama sistemi test edilebilir.</p>";
        echo "</div>";
    } else {
        echo "<div style='background: #fff3e0; padding: 15px; border-radius: 5px;'>";
        echo "<h3>⚠️ Makale ekleniyor...</h3>";
        
        // Örnek makaleler
        $sample_articles = [
            [
                'title' => 'PHP Güvenlik Rehberi',
                'slug' => 'php-guvenlik-rehberi',
                'content' => 'PHP uygulamalarında güvenlik çok önemlidir. SQL injection, XSS ve CSRF saldırılarına karşı korunmak için çeşitli yöntemler kullanmalıyız. PDO prepared statements kullanarak SQL injection saldırılarını engelleyebiliriz. Ayrıca kullanıcı girdilerini her zaman temizlememiz gerekir.',
                'tags' => 'php, güvenlik, web, programlama',
                'status' => 'published'
            ],
            [
                'title' => 'Web Geliştirme Temelleri',
                'slug' => 'web-gelistirme-temelleri',
                'content' => 'Modern web geliştirme HTML, CSS ve JavaScript üçlüsü üzerine kuruludur. Bu teknolojiler sayesinde dinamik ve interaktif web siteleri oluşturabiliriz. Backend geliştirme için PHP, Python, Node.js gibi diller kullanılabilir.',
                'tags' => 'web, html, css, javascript, geliştirme',
                'status' => 'published'
            ],
            [
                'title' => 'Veritabanı Optimizasyonu',
                'slug' => 'veritabani-optimizasyonu',
                'content' => 'Veritabanı performansı web uygulamalarının hızını doğrudan etkiler. Index kullanımı, sorgu optimizasyonu ve normalizasyon gibi tekniklerle veritabanı performansını artırabiliriz. MySQL ve PostgreSQL gibi veritabanlarında farklı optimizasyon teknikleri vardır.',
                'tags' => 'database, mysql, optimizasyon, performans',
                'status' => 'published'
            ],
            [
                'title' => 'Modern CSS Teknikleri',
                'slug' => 'modern-css-teknikleri',
                'content' => 'CSS Grid ve Flexbox modern web tasarımının temel taşlarıdır. Bu teknolojiler sayesinde responsive ve esnek tasarımlar oluşturabiliriz. CSS değişkenleri ve animasyonlar da modern CSS\'in önemli parçalarıdır.',
                'tags' => 'css, design, frontend, responsive',
                'status' => 'published'
            ],
            [
                'title' => 'JavaScript ES6+ Özellikleri',
                'slug' => 'javascript-es6-ozellikleri',
                'content' => 'ES6 ve sonraki sürümler JavaScript\'e birçok yeni özellik getirdi. Arrow functions, template literals, destructuring ve async/await gibi özellikler modern JavaScript geliştirmeyi kolaylaştırır.',
                'tags' => 'javascript, es6, programlama, frontend',
                'status' => 'published'
            ]
        ];
        
        // Admin user ID'sini al (varsayılan olarak 1)
        $admin_id = 1;
        
        foreach ($sample_articles as $article) {
            $insert_query = "INSERT INTO articles (title, slug, content, tags, status, author_id, created_at, updated_at, published_at) 
                           VALUES (:title, :slug, :content, :tags, :status, :author_id, NOW(), NOW(), NOW())";
            
            $insert_stmt = $pdo->prepare($insert_query);
            $insert_stmt->execute([
                ':title' => $article['title'],
                ':slug' => $article['slug'],
                ':content' => $article['content'],
                ':tags' => $article['tags'],
                ':status' => $article['status'],
                ':author_id' => $admin_id
            ]);
            
            echo "<p>✅ Eklendi: {$article['title']}</p>";
        }
        
        echo "<h3>🎉 Örnek makaleler başarıyla eklendi!</h3>";
        echo "</div>";
    }
    
    // Son durumu göster
    $final_count_stmt = $pdo->query("SELECT COUNT(*) as total FROM articles WHERE status = 'published'");
    $final_count = $final_count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    echo "<div style='background: #e3f2fd; padding: 15px; margin: 10px 0; border-radius: 5px;'>";
    echo "<h3>📊 Son Durum</h3>";
    echo "<p>Toplam yayınlanmış makale: <strong>$final_count</strong></p>";
    echo "<p>Arama sistemi artık test edilebilir!</p>";
    echo "</div>";
    
} catch (PDOException $e) {
    echo "<div style='background: #ffebee; padding: 15px; border: 1px solid #f44336; border-radius: 5px;'>";
    echo "<h3 style='color: #d32f2f;'>❌ Hata</h3>";
    echo "<p>Makale eklenirken hata oluştu: " . $e->getMessage() . "</p>";
    echo "</div>";
}
?>

<div style="margin: 20px 0; padding: 15px; background: #f8f9fa; border-radius: 5px;">
    <h3>🔗 Test Linkleri</h3>
    <ul>
        <li><a href="debug_detailed_search.php">Detaylı Debug</a></li>
        <li><a href="test_search_simple.php">Basit Arama Testi</a></li>
        <li><a href="index.php">Ana Sayfa</a></li>
        <li><a href="search.php">Gelişmiş Arama</a></li>
        <li><a href="admin/login.php">Admin Panel</a></li>
    </ul>
</div> 