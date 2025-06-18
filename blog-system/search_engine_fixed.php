<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

/**
 * Gelişmiş Arama Motoru
 * Case-insensitive, collation-aware, multiple pattern matching
 */
class AdvancedSearchEngine {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        // PDO emülasyon modunu açalım
        $this->pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
    }
    
    /**
     * Ana arama fonksiyonu
     */
    public function search($search_query, $options = []) {
        try {
            $defaults = [
                'category_filter' => '',
                'author_filter' => '',
                'date_from' => '',
                'date_to' => '',
                'sort_by' => 'relevance',
                'page' => 1,
                'posts_per_page' => 8,
                'debug' => false
            ];
            
            $options = array_merge($defaults, $options);
            
            // Debug bilgisi
            if ($options['debug']) {
                echo "<div style='background: #f8f9fa; padding: 10px; margin: 10px;'>";
                echo "<h4>Debug Bilgisi:</h4>";
                echo "Arama terimi: " . htmlspecialchars($search_query) . "<br>";
                echo "Kategori: " . htmlspecialchars($options['category_filter']) . "<br>";
                echo "Yazar: " . htmlspecialchars($options['author_filter']) . "<br>";
                echo "</div>";
            }
            
            // Temel SQL sorgusu
            $sql = "SELECT DISTINCT a.* FROM articles a";
            $params = [];
            $where = ["a.status = 'published'"];
            $param_count = 0;
            
            // JOIN'ler
            if ($options['category_filter']) {
                $sql .= " LEFT JOIN categories c ON a.category_id = c.id";
            }
            if ($options['author_filter']) {
                $sql .= " LEFT JOIN admin_users au ON a.author_id = au.id";
            }
            
            // Arama terimi
            if (!empty($search_query)) {
                $where[] = "(a.title LIKE ? OR a.content LIKE ?)";
                $params[] = '%' . $search_query . '%';
                $params[] = '%' . $search_query . '%';
            }
            
            // Kategori filtresi
            if ($options['category_filter']) {
                $where[] = "c.slug = ?";
                $params[] = $options['category_filter'];
            }
            
            // Yazar filtresi
            if ($options['author_filter']) {
                $where[] = "au.username = ?";
                $params[] = $options['author_filter'];
            }
            
            // Tarih filtreleri
            if ($options['date_from']) {
                $where[] = "DATE(a.published_at) >= ?";
                $params[] = $options['date_from'];
            }
            
            if ($options['date_to']) {
                $where[] = "DATE(a.published_at) <= ?";
                $params[] = $options['date_to'];
            }
            
            // WHERE koşullarını ekle
            if (!empty($where)) {
                $sql .= " WHERE " . implode(" AND ", $where);
            }
            
            // Debug - SQL göster
            if ($options['debug']) {
                echo "<div style='background: #e9ecef; padding: 10px; margin: 10px; font-family: monospace;'>";
                echo "<strong>SQL:</strong><br>" . htmlspecialchars($sql) . "<br><br>";
                echo "<strong>Parametreler:</strong><br>";
                echo "<pre>" . print_r($params, true) . "</pre>";
                echo "</div>";
            }
            
            // Toplam sonuç sayısı
            $count_sql = preg_replace('/^SELECT DISTINCT a\.\*/', 'SELECT COUNT(DISTINCT a.id) as total', $sql);
            $count_stmt = $this->pdo->prepare($count_sql);
            
            // Parametreleri bağla
            foreach ($params as $key => $value) {
                $count_stmt->bindValue($key + 1, $value);
            }
            
            $count_stmt->execute();
            $total_results = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Sayfalama
            $total_pages = ceil($total_results / $options['posts_per_page']);
            $offset = ($options['page'] - 1) * $options['posts_per_page'];
            
            // Sıralama ve limit
            $sql .= " ORDER BY a.published_at DESC LIMIT ? OFFSET ?";
            
            $stmt = $this->pdo->prepare($sql);
            
            // Parametreleri bağla
            foreach ($params as $key => $value) {
                $stmt->bindValue($key + 1, $value);
            }
            
            // Sayfalama parametrelerini ekle
            $stmt->bindValue(count($params) + 1, (int)$options['posts_per_page'], PDO::PARAM_INT);
            $stmt->bindValue(count($params) + 2, (int)$offset, PDO::PARAM_INT);
            
            // Debug - son SQL
            if ($options['debug']) {
                echo "<div style='background: #e9ecef; padding: 10px; margin: 10px; font-family: monospace;'>";
                echo "<strong>Son SQL:</strong><br>" . htmlspecialchars($sql) . "<br><br>";
                echo "<strong>Toplam parametre sayısı:</strong> " . (count($params) + 2) . "<br>";
                echo "<strong>Limit:</strong> " . $options['posts_per_page'] . "<br>";
                echo "<strong>Offset:</strong> " . $offset . "<br>";
                echo "</div>";
            }
            
            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Debug - sonuçları göster
            if ($options['debug']) {
                echo "<div style='background: #e9ecef; padding: 10px; margin: 10px;'>";
                echo "<strong>Bulunan sonuç sayısı:</strong> " . count($results) . "<br>";
                if (!empty($results)) {
                    echo "<strong>İlk sonuç:</strong><br>";
                    echo "<pre>" . print_r($results[0], true) . "</pre>";
                }
                echo "</div>";
            }
            
            return [
                'results' => $results,
                'total_results' => $total_results,
                'total_pages' => $total_pages,
                'current_page' => $options['page']
            ];
            
        } catch (PDOException $e) {
            error_log("Arama hatası: " . $e->getMessage());
            if ($options['debug']) {
                echo "<div style='background: #f8d7da; padding: 10px; margin: 10px;'>";
                echo "<strong>Hata:</strong> " . $e->getMessage() . "<br>";
                if (isset($e->errorInfo)) {
                    echo "<strong>SQL State:</strong> " . $e->errorInfo[0] . "<br>";
                    echo "<strong>Driver Error Code:</strong> " . $e->errorInfo[1] . "<br>";
                    echo "<strong>Driver Error Message:</strong> " . $e->errorInfo[2] . "<br>";
                }
                echo "</div>";
            }
            return [
                'results' => [],
                'total_results' => 0,
                'total_pages' => 0,
                'current_page' => 1,
                'error' => $e->getMessage()
            ];
        }
    }
}

// Test fonksiyonu
function testSearchEngine() {
    global $pdo;
    
    echo "<h2>🔍 Gelişmiş Arama Motoru Test</h2>";
    
    $engine = new AdvancedSearchEngine($pdo);
    
    $test_terms = ['güvenlik', 'güvenli', 'PHP', 'web', 'javascript'];
    
    foreach ($test_terms as $term) {
        echo "<div style='background: #f0f0f0; padding: 15px; margin: 10px 0; border-radius: 5px;'>";
        echo "<h3>Test: '$term'</h3>";
        
        $result = $engine->search($term, ['debug' => true]);
        
        echo "<p><strong>Sonuç:</strong> {$result['total_results']} makale bulundu</p>";
        
        if (!empty($result['results'])) {
            echo "<ul>";
            foreach ($result['results'] as $article) {
                echo "<li><strong>{$article['title']}</strong> - " . substr($article['content'], 0, 100) . "...</li>";
            }
            echo "</ul>";
        }
        
        if (isset($result['error'])) {
            echo "<p style='color: red;'><strong>Hata:</strong> {$result['error']}</p>";
        }
        
        echo "</div>";
    }
}

// Eğer doğrudan çağrılırsa test et
if (basename(__FILE__) == basename($_SERVER['SCRIPT_NAME'])) {
    testSearchEngine();
}
?> 