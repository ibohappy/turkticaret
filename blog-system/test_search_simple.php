<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/database.php';

// PDO Yapılandırması
echo "<h1>PDO Yapılandırma Kontrolü</h1>";
echo "<pre>";
echo "PDO Driver Options:\n";
print_r($pdo->getAttribute(PDO::ATTR_DRIVER_OPTIONS));
echo "\nPDO Error Mode: " . $pdo->getAttribute(PDO::ATTR_ERRMODE) . "\n";
echo "PDO Connection Status: " . $pdo->getAttribute(PDO::ATTR_CONNECTION_STATUS) . "\n";
echo "PDO Server Info: " . $pdo->getAttribute(PDO::ATTR_SERVER_INFO) . "\n";
echo "PDO Server Version: " . $pdo->getAttribute(PDO::ATTR_SERVER_VERSION) . "\n";
echo "PDO Client Version: " . $pdo->getAttribute(PDO::ATTR_CLIENT_VERSION) . "\n";
echo "</pre>";

// PDO emülasyon modunu açalım
$pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
echo "<p>PDO Emülasyon Modu: " . ($pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES) ? 'Açık' : 'Kapalı') . "</p>";

// Veritabanı karakter seti kontrolü
echo "<h2>Veritabanı Karakter Seti</h2>";
$charset_query = $pdo->query("SHOW VARIABLES LIKE 'character_set%'");
echo "<pre>";
while ($row = $charset_query->fetch(PDO::FETCH_ASSOC)) {
    echo $row['Variable_name'] . ": " . $row['Value'] . "\n";
}
echo "</pre>";

echo "<h1>Arama Motoru Debug</h1>";

// 1. Form ve GET parametresi kontrolü
echo "<h2>1. Form ve GET Parametresi Kontrolü</h2>";
echo "<pre>";
echo "REQUEST_METHOD: " . $_SERVER['REQUEST_METHOD'] . "\n";
echo "GET parametreleri:\n";
var_dump($_GET);
echo "</pre>";

// 2. Arama terimi kontrolü
$search_term = isset($_GET['q']) ? trim($_GET['q']) : '';
echo "<h2>2. Arama Terimi Kontrolü</h2>";
echo "<pre>";
echo "Orijinal terim: ";
var_dump($search_term);
echo "Temizlenmiş terim: ";
var_dump(htmlspecialchars($search_term));
echo "</pre>";

try {
    if (!empty($search_term)) {
        // 3. Basit arama testi
        echo "<h2>3. Basit Arama Testi</h2>";
        
        // 3.1 Önce basit bir sorgu deneyelim
        $sql = "SELECT COUNT(*) as total FROM articles";
        $result = $pdo->query($sql)->fetch(PDO::FETCH_ASSOC);
        echo "<p>Toplam makale sayısı: " . $result['total'] . "</p>";
        
        // 3.2 Şimdi LIKE ile arama yapalım
        $sql = "SELECT * FROM articles WHERE title LIKE ? OR content LIKE ?";
        echo "<p>SQL Sorgusu: " . htmlspecialchars($sql) . "</p>";
        
        $stmt = $pdo->prepare($sql);
        if ($stmt === false) {
            echo "<p style='color: red;'>Sorgu hazırlanamadı!</p>";
            echo "<pre>";
            print_r($pdo->errorInfo());
            echo "</pre>";
        } else {
            $search_param = '%' . $search_term . '%';
            
            echo "<pre>";
            echo "Arama parametresi: " . htmlspecialchars($search_param) . "\n";
            echo "</pre>";
            
            // Her parametre için ayrı bind ve kontrol
            $bind1 = $stmt->bindValue(1, $search_param, PDO::PARAM_STR);
            $bind2 = $stmt->bindValue(2, $search_param, PDO::PARAM_STR);
            
            echo "<p>Parametre Bağlama Durumu:</p>";
            echo "<pre>";
            echo "Bind 1: " . ($bind1 ? 'Başarılı' : 'Başarısız') . "\n";
            echo "Bind 2: " . ($bind2 ? 'Başarılı' : 'Başarısız') . "\n";
            echo "</pre>";
            
            // Execute öncesi statement durumu
            echo "<p>Execute Öncesi Statement Durumu:</p>";
            echo "<pre>";
            print_r($stmt->debugDumpParams());
            echo "</pre>";
            
            try {
                $execute_result = $stmt->execute();
                echo "<p>Execute Sonucu: " . ($execute_result ? 'Başarılı' : 'Başarısız') . "</p>";
                
                if (!$execute_result) {
                    echo "<p style='color: red;'>Execute Hatası:</p>";
                    echo "<pre>";
                    print_r($stmt->errorInfo());
                    echo "</pre>";
                } else {
                    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    echo "<h3>Arama Sonuçları (" . count($results) . " sonuç):</h3>";
                    if ($results) {
                        foreach ($results as $row) {
                            echo "<div style='margin: 10px; padding: 10px; border: 1px solid #ccc;'>";
                            echo "<strong>ID:</strong> " . htmlspecialchars($row['id']) . "<br>";
                            echo "<strong>Başlık:</strong> " . htmlspecialchars($row['title']) . "<br>";
                            echo "<strong>İçerik:</strong> " . substr(htmlspecialchars($row['content']), 0, 100) . "...<br>";
                            echo "</div>";
                        }
                    } else {
                        echo "<p>Sonuç bulunamadı.</p>";
                    }
                }
            } catch (PDOException $e) {
                echo "<p style='color: red;'>Execute Exception:</p>";
                echo "<pre>";
                echo "Hata Mesajı: " . $e->getMessage() . "\n";
                echo "Hata Kodu: " . $e->getCode() . "\n";
                if (isset($e->errorInfo)) {
                    echo "SQL State: " . $e->errorInfo[0] . "\n";
                    echo "Driver Error Code: " . $e->errorInfo[1] . "\n";
                    echo "Driver Error Message: " . $e->errorInfo[2] . "\n";
                }
                echo "</pre>";
            }
        }
    }
    
    // 4. Tablo yapısı kontrolü
    echo "<h2>4. Tablo Yapısı Kontrolü</h2>";
    $table_info = $pdo->query("DESCRIBE articles");
    echo "<pre>";
    print_r($table_info->fetchAll(PDO::FETCH_ASSOC));
    echo "</pre>";
    
} catch (PDOException $e) {
    echo "<div style='color: red; padding: 10px; margin: 10px; border: 1px solid red;'>";
    echo "<h3>Veritabanı Hatası:</h3>";
    echo "<pre>";
    echo "Hata Mesajı: " . $e->getMessage() . "\n";
    echo "Hata Kodu: " . $e->getCode() . "\n";
    if (isset($e->errorInfo)) {
        echo "SQL State: " . $e->errorInfo[0] . "\n";
        echo "Driver Error Code: " . $e->errorInfo[1] . "\n";
        echo "Driver Error Message: " . $e->errorInfo[2] . "\n";
    }
    echo "</pre>";
    echo "</div>";
}
?>

<form method="GET" style="margin: 20px 0;">
    <input type="text" name="q" value="<?php echo htmlspecialchars($search_term); ?>" placeholder="Arama...">
    <button type="submit">Ara</button>
</form> 