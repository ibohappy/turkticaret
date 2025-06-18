<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Bağlantı Testi</h1>";

try {
    // MySQL sunucusuna bağlan
    $pdo = new PDO("mysql:host=localhost", "root", "");
    echo "<p style='color:green'>MySQL sunucusuna bağlantı başarılı!</p>";
    
    // Mevcut veritabanlarını listele
    $stmt = $pdo->query("SHOW DATABASES");
    $databases = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<h2>Mevcut Veritabanları:</h2>";
    echo "<ul>";
    foreach ($databases as $database) {
        if ($database == 'blog_system') {
            echo "<li style='color:green'><strong>{$database}</strong> (Hedef veritabanı)</li>";
        } else {
            echo "<li>{$database}</li>";
        }
    }
    echo "</ul>";
    
    // blog_system veritabanı yoksa oluştur
    if (!in_array('blog_system', $databases)) {
        $pdo->exec("CREATE DATABASE blog_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        echo "<p style='color:green'>blog_system veritabanı oluşturuldu!</p>";
        
        // SQL dosyasını oku ve çalıştır
        $pdo->exec("USE blog_system");
        $sql = file_get_contents(__DIR__ . '/database.sql');
        $pdo->exec($sql);
        echo "<p style='color:green'>Veritabanı tabloları oluşturuldu!</p>";
    }
    
    // blog_system veritabanına bağlan ve tabloları kontrol et
    $pdo = new PDO("mysql:host=localhost;dbname=blog_system;charset=utf8mb4", "root", "");
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<h2>blog_system Veritabanındaki Tablolar:</h2>";
    echo "<ul>";
    foreach ($tables as $table) {
        echo "<li>{$table}</li>";
    }
    echo "</ul>";
    
} catch (PDOException $e) {
    echo "<p style='color:red'>Hata: " . $e->getMessage() . "</p>";
}
?> 