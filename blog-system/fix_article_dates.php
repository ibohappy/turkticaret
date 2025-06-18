<?php
require_once 'config/database.php';

echo "<h2>Makale Tarihlerini Düzeltme İşlemi</h2>";

try {
    // İlk önce mevcut durumu kontrol et
    echo "<h3>Mevcut Durum:</h3>";
    $check_query = "SELECT id, title, created_at, published_at FROM articles ORDER BY id";
    $check_stmt = $pdo->query($check_query);
    $articles = $check_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr><th>ID</th><th>Başlık</th><th>Created At</th><th>Published At</th></tr>";
    
    foreach ($articles as $article) {
        echo "<tr>";
        echo "<td>" . $article['id'] . "</td>";
        echo "<td>" . htmlspecialchars(substr($article['title'], 0, 30)) . "...</td>";
        echo "<td>" . ($article['created_at'] ?: 'NULL') . "</td>";
        echo "<td>" . ($article['published_at'] ?: 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";

    // 1. published_at NULL olan makaleleri created_at tarihi ile güncelle
    echo "<h3>İşlem 1: published_at NULL olanları güncelleme...</h3>";
    $update1 = "UPDATE articles 
                SET published_at = created_at 
                WHERE published_at IS NULL OR published_at = '' OR published_at = '0000-00-00 00:00:00'";
    $stmt1 = $pdo->prepare($update1);
    $result1 = $stmt1->execute();
    $affected1 = $stmt1->rowCount();
    echo "✅ {$affected1} makale güncellendi (published_at = created_at)<br>";

    // 2. created_at da NULL/boş olanları şu anki zaman ile güncelle
    echo "<h3>İşlem 2: created_at NULL olanları güncelleme...</h3>";
    $update2 = "UPDATE articles 
                SET created_at = NOW(), published_at = NOW() 
                WHERE created_at IS NULL OR created_at = '' OR created_at = '0000-00-00 00:00:00'";
    $stmt2 = $pdo->prepare($update2);
    $result2 = $stmt2->execute();
    $affected2 = $stmt2->rowCount();
    echo "✅ {$affected2} makale güncellendi (şu anki zaman ile)<br>";

    // 3. updated_at alanını da güncelle
    echo "<h3>İşlem 3: updated_at alanlarını güncelleme...</h3>";
    $update3 = "UPDATE articles 
                SET updated_at = published_at 
                WHERE updated_at IS NULL OR updated_at = '' OR updated_at = '0000-00-00 00:00:00'";
    $stmt3 = $pdo->prepare($update3);
    $result3 = $stmt3->execute();
    $affected3 = $stmt3->rowCount();
    echo "✅ {$affected3} makale güncellendi (updated_at)<br>";

    // Güncellenmiş durumu göster
    echo "<h3>Güncellenmiş Durum:</h3>";
    $final_check = $pdo->query($check_query);
    $updated_articles = $final_check->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr style='background: #f0f0f0;'><th>ID</th><th>Başlık</th><th>Created At</th><th>Published At</th></tr>";
    
    foreach ($updated_articles as $article) {
        echo "<tr>";
        echo "<td>" . $article['id'] . "</td>";
        echo "<td>" . htmlspecialchars(substr($article['title'], 0, 30)) . "...</td>";
        echo "<td style='color: green;'>" . $article['created_at'] . "</td>";
        echo "<td style='color: blue;'>" . $article['published_at'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";

    echo "<div style='background: #d4edda; padding: 15px; margin: 20px 0; border-radius: 5px;'>";
    echo "<h3 style='color: #155724; margin: 0;'>✅ İşlem Başarıyla Tamamlandı!</h3>";
    echo "<p style='margin: 10px 0 0 0; color: #155724;'>Tüm makale tarihleri düzeltildi. Artık 01.01.1970 tarihi gösterilmeyecek.</p>";
    echo "</div>";

} catch (PDOException $e) {
    echo "<div style='background: #f8d7da; padding: 15px; margin: 20px 0; border-radius: 5px;'>";
    echo "<h3 style='color: #721c24; margin: 0;'>❌ Hata Oluştu!</h3>";
    echo "<p style='margin: 10px 0 0 0; color: #721c24;'>Veritabanı hatası: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}
?>

<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    table { width: 100%; }
    th, td { padding: 8px; text-align: left; border: 1px solid #ddd; }
    th { background-color: #f2f2f2; }
</style>

<div style="margin-top: 30px; padding: 15px; background: #e7f3ff; border-radius: 5px;">
    <h4>📝 Yapılan İşlemler:</h4>
    <ul>
        <li>✅ NULL olan published_at değerleri created_at ile eşitlendi</li>
        <li>✅ NULL olan created_at değerleri şu anki zaman ile güncellendi</li>
        <li>✅ format_date() fonksiyonu geliştirildi</li>
        <li>✅ Geçersiz tarihlerde fallback mekanizması eklendi</li>
    </ul>
    
    <p><strong>Sonuç:</strong> Artık makalelerinizde doğru tarihler görünecek!</p>
    
    <a href="index.php" style="background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">🏠 Ana Sayfaya Dön</a>
    <a href="admin/dashboard.php" style="background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-left: 10px;">⚙️ Admin Paneli</a>
</div> 