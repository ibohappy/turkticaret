<?php
// Error reporting kapat (production)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once '../config/database.php';
require_once '../includes/functions.php';

// Güvenli session başlat - hata kontrolü ile
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$error = '';
$success = '';
$debug_info = '';

// Zaten giriş yapmışsa dashboard'a yönlendir
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: dashboard.php');
    exit();
}

// Login form işlemi - BASİT VERSİYON (GÜVENLİK KİLİTLEME YOK)
if ($_POST) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    
    // DEBUG: Gelen veriler
    $debug_info .= "📝 Gelen veriler: Kullanıcı='{$username}', Şifre uzunluğu=" . strlen($password) . "<br>";
    
    // Basit validasyon
    if (empty($username) || empty($password)) {
        $error = 'Kullanıcı adı ve şifre boş olamaz!';
    } else {
        try {
            // Kullanıcıyı veritabanından al - SADECE AKTİF KULLANICILAR
            $stmt = $pdo->prepare("SELECT id, username, password_hash, status FROM admin_users WHERE username = :username AND status = 'active'");
            $stmt->bindValue(':username', $username);
            $stmt->execute();
            
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // DEBUG: Veritabanı sonucu
            if ($user) {
                $debug_info .= "✅ Kullanıcı bulundu: ID={$user['id']}, Status={$user['status']}<br>";
                $debug_info .= "🔒 Hash uzunluğu: " . strlen($user['password_hash']) . "<br>";
                $debug_info .= "🔑 Hash başlangıcı: " . substr($user['password_hash'], 0, 20) . "...<br>";
            } else {
                $debug_info .= "❌ Kullanıcı bulunamadı veya aktif değil<br>";
            }
            
            // Şifre kontrolü - password_verify kullan
            if ($user && password_verify($password, $user['password_hash'])) {
                // BAŞARILI GİRİŞ
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_user_id'] = $user['id'];
                $_SESSION['admin_username'] = $user['username'];
                
                // Son login güncelle
                $update_stmt = $pdo->prepare("UPDATE admin_users SET last_login = NOW(), last_login_ip = :ip WHERE id = :id");
                $update_stmt->execute([
                    ':ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                    ':id' => $user['id']
                ]);
                
                $success = 'Giriş başarılı! Dashboard\'a yönlendiriliyorsunuz...';
                $debug_info .= "🎉 Şifre doğrulama başarılı!<br>";
                
                // Başarılı giriş sonrası yönlendirme
                echo "<script>
                    setTimeout(function() {
                        window.location.href = 'dashboard.php';
                    }, 1500);
                </script>";
                
            } else {
                $error = 'Kullanıcı adı veya şifre hatalı!';
                
                // DEBUG: Şifre kontrolü detayları
                if ($user) {
                    $debug_info .= "❌ Şifre eşleşmiyor<br>";
                    $debug_info .= "🔍 Test şifresi: '{$password}'<br>";
                    
                    // Manuel test
                    $test_passwords = ['admin123', 'Admin123!', 'admin', '123'];
                    foreach ($test_passwords as $test_pass) {
                        $test_result = password_verify($test_pass, $user['password_hash']);
                        $debug_info .= "🧪 Test '{$test_pass}': " . ($test_result ? "✅ DOĞRU" : "❌ YANLIŞ") . "<br>";
                    }
                } else {
                    $debug_info .= "❌ Kullanıcı bulunamadı<br>";
                }
            }
            
        } catch (PDOException $e) {
            $error = 'Veritabanı hatası: ' . $e->getMessage();
            $debug_info .= "💥 DB Hatası: " . $e->getMessage() . "<br>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Basit Admin Giriş - Blog Sistemi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            padding: 40px;
            width: 100%;
            max-width: 500px;
        }
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .login-header h2 {
            color: #2c3e50;
            font-weight: 600;
        }
        .form-control {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 12px 15px;
            font-size: 16px;
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 10px;
            padding: 12px;
            font-size: 16px;
            font-weight: 600;
            width: 100%;
            color: white;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        .alert {
            border-radius: 10px;
            border: none;
        }
        .dev-info {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
            border-left: 4px solid #28a745;
        }
        .debug-info {
            background: #fff3cd;
            border-radius: 10px;
            padding: 15px;
            margin: 15px 0;
            border-left: 4px solid #ffc107;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="login-card">
                    <div class="login-header">
                        <i class="fas fa-user-shield fa-3x text-primary mb-3"></i>
                        <h2>🔓 Basit Admin Giriş</h2>
                        <p class="text-muted">Güvenlik kilitleme devre dışı - Debug Mode</p>
                    </div>

                    <!-- DEVELOPMENT BİLGİSİ -->
                    <div class="dev-info">
                        <h6><i class="fas fa-info-circle"></i> Test Giriş Bilgileri</h6>
                        <p class="mb-1"><strong>👤 Kullanıcı:</strong> <code>admin</code></p>
                        <p class="mb-0"><strong>🔒 Şifre:</strong> <code>admin123</code></p>
                    </div>

                    <?php if ($error): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($debug_info && !$success): ?>
                        <div class="debug-info">
                            <h6><i class="fas fa-bug"></i> Debug Bilgileri</h6>
                            <?php echo $debug_info; ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="username" class="form-label">
                                <i class="fas fa-user"></i> Kullanıcı Adı
                            </label>
                            <input type="text" 
                                   class="form-control" 
                                   id="username" 
                                   name="username" 
                                   value="admin"
                                   placeholder="Kullanıcı adınızı girin" 
                                   required>
                        </div>

                        <div class="mb-4">
                            <label for="password" class="form-label">
                                <i class="fas fa-lock"></i> Şifre
                            </label>
                            <input type="password" 
                                   class="form-control" 
                                   id="password" 
                                   name="password" 
                                   value="admin123"
                                   placeholder="Şifrenizi girin" 
                                   required>
                        </div>

                        <button type="submit" class="btn btn-login">
                            <i class="fas fa-sign-in-alt"></i> Giriş Yap
                        </button>
                    </form>

                    <div class="text-center mt-4">
                        <hr>
                        <p class="text-muted">
                            <i class="fas fa-shield-alt"></i> Güvenlik kilitleme geçici olarak devre dışı
                        </p>
                        <a href="../unlock_admin.php" class="btn btn-warning btn-sm me-2">
                            <i class="fas fa-unlock"></i> Hesap Kilidi Kaldır
                        </a>
                        <a href="../index.php" class="text-decoration-none">
                            <i class="fas fa-home"></i> Ana Sayfaya Dön
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- OTO-DOLDUR SCRIPT -->
    <script>
        // Geliştirme kolaylığı için form otomatik doldur
        document.addEventListener('DOMContentLoaded', function() {
            const usernameField = document.getElementById('username');
            const passwordField = document.getElementById('password');
            
            // Eğer boşsa otomatik doldur
            if (!usernameField.value) usernameField.value = 'admin';
            if (!passwordField.value) passwordField.value = 'admin123';
        });
    </script>
</body>
</html> 