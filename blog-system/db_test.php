<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Veritabanı Test Sayfası</h2>";

// MySQL bağlantısını test et
try {
    $conn = new PDO("mysql:host=localhost", "root", "");
    echo "<p style='color: green;'>MySQL bağlantısı başarılı!</p>";
    
    // Mevcut veritabanlarını listele
    $databases = $conn->query("SHOW DATABASES")->fetchAll(PDO::FETCH_COLUMN);
    echo "<h3>Mevcut Veritabanları:</h3>";
    echo "<ul>";
    foreach ($databases as $database) {
        echo "<li>" . htmlspecialchars($database) . "</li>";
    }
    echo "</ul>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>MySQL bağlantı hatası: " . $e->getMessage() . "</p>";
    die();
}

// Config dosyasındaki ayarları göster
$config_file = __DIR__ . '/config/database.php';
echo "<h3>Config Dosyası Ayarları:</h3>";
if (file_exists($config_file)) {
    $config_content = file_get_contents($config_file);
    echo "<pre>";
    // Hassas bilgileri gizle
    $config_content = preg_replace('/(\$db_pass\s*=\s*["\']).*?(["\'])/', '$1*****$2', $config_content);
    echo htmlspecialchars($config_content);
    echo "</pre>";
} else {
    echo "<p style='color: red;'>Config dosyası bulunamadı!</p>";
}
?> 