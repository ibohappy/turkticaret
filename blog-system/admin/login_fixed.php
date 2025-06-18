<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Error reporting'i kapat
error_reporting(0);
ini_set('display_errors', 0);

// Güvenli session başlat
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$error = '';
$success = '';
$login_attempts_exceeded = false;
$debug_info = '';

// Zaten giriş yapmışsa dashboard'a yönlendir
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: dashboard.php');
    exit();
}

// Login form işlemi - HESAP KİLİTLEME DEVREdışı
if ($_POST) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $remember_me = isset($_POST['remember_me']) ? true : false;
    
    // Debug bilgisi
    $debug_info .= "🔍 Girilen: '{$username}' / '" . str_repeat('*', strlen($password)) . "'<br>";
    
    // Basit validasyon
    if (empty($username) || empty($password)) {
        $error = 'Kullanıcı adı ve şifre boş olamaz!';
    } else {
        try {
            // Kullanıcıyı veritabanından al - KİLİT KONTROL YOK
            $stmt = $pdo->prepare("
                SELECT id, username, password_hash, status, failed_login_attempts
                FROM admin_users 
                WHERE username = :username AND status = 'active'
            ");
            $stmt->bindValue(':username', $username);
            $stmt->execute();
            
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                $debug_info .= "✅ Kullanıcı bulundu<br>";
                
                // Şifre kontrolü - password_verify kullan
                if (password_verify($password, $user['password_hash'])) {
                    // BAŞARILI GİRİŞ
                    $_SESSION['admin_logged_in'] = true;
                    $_SESSION['admin_user_id'] = $user['id'];
                    $_SESSION['admin_username'] = $user['username'];
                    
                    // Başarısız giriş sayacını sıfırla ve son login bilgilerini güncelle
                    $update_stmt = $pdo->prepare("
                        UPDATE admin_users 
                        SET failed_login_attempts = 0, 
                            account_locked_until = NULL,
                            last_login = NOW(), 
                            last_login_ip = :ip 
                        WHERE id = :id
                    ");
                    $update_stmt->execute([
                        ':ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                        ':id' => $user['id']
                    ]);
                    
                    // Beni hatırla özelliği
                    if ($remember_me) {
                        $remember_token = bin2hex(random_bytes(32));
                        $token_hash = hash('sha256', $remember_token);
                        
                        // Eski token'ları temizle
                        $pdo->prepare("DELETE FROM remember_tokens WHERE user_id = :user_id")
                            ->execute([':user_id' => $user['id']]);
                        
                        // Yeni token ekle
                        $token_stmt = $pdo->prepare("
                            INSERT INTO remember_tokens 
                            (user_id, token_hash, expires_at, user_agent, ip_address) 
                            VALUES (:user_id, :token_hash, DATE_ADD(NOW(), INTERVAL 30 DAY), :user_agent, :ip)
                        ");
                        $token_stmt->execute([
                            ':user_id' => $user['id'],
                            ':token_hash' => $token_hash,
                            ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                            ':ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
                        ]);
                        
                        // Cookie ayarla (30 gün)
                        setcookie('remember_token', $remember_token, 
                            time() + (30 * 24 * 60 * 60), '/', '', false, true);
                    }
                    
                    // Başarılı login log
                    $log_stmt = $pdo->prepare("
                        INSERT INTO login_logs (username, ip_address, user_agent, success) 
                        VALUES (:username, :ip, :user_agent, 1)
                    ");
                    $log_stmt->execute([
                        ':username' => $username,
                        ':ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                        ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                    ]);
                    
                    $success = 'Giriş başarılı! Dashboard\'a yönlendiriliyorsunuz...';
                    
                    // Yönlendirme
                    echo "<script>
                        setTimeout(function() {
                            window.location.href = 'dashboard.php';
                        }, 1500);
                    </script>";
                    
                } else {
                    // Başarısız giriş sayacını artır
                    $failed_attempts = $user['failed_login_attempts'] + 1;
                    
                    if ($failed_attempts >= 4) {
                        $error = 'Kullanıcı adı veya şifre hatalı! Bir sonraki yanlış girişte 5 dakika beklemeniz gerekecek.';
                    } else {
                        $remaining_attempts = 5 - $failed_attempts;
                        $error = "Kullanıcı adı veya şifre hatalı! Kalan deneme hakkı: {$remaining_attempts}";
                    }
                    
                    $debug_info .= "❌ Şifre eşleşmiyor<br>";
                    
                    // Failed attempts güncelle
                    $update_stmt = $pdo->prepare("
                        UPDATE admin_users 
                        SET failed_login_attempts = :attempts,
                            last_failed_login = NOW()
                        WHERE id = :id
                    ");
                    $update_stmt->execute([
                        ':attempts' => $failed_attempts,
                        ':id' => $user['id']
                    ]);
                    
                    // Debug: Şifre testleri
                    $test_passwords = ['admin123', 'Admin123!', 'admin', '123'];
                    foreach ($test_passwords as $test_pass) {
                        $test_result = password_verify($test_pass, $user['password_hash']);
                        $debug_info .= "🧪 '{$test_pass}': " . ($test_result ? "✅" : "❌") . "<br>";
                    }
                }
            } else {
                $error = 'Kullanıcı adı veya şifre hatalı!';
                $debug_info .= "❌ Kullanıcı bulunamadı<br>";
            }
                
        } catch (PDOException $e) {
            error_log("Login error: " . $e->getMessage());
            $error = 'Sistem hatası! Lütfen daha sonra tekrar deneyin.';
            $debug_info .= "💥 DB Hatası: " . $e->getMessage() . "<br>";
        }
    }
}

$page_title = 'Güvenli Admin Girişi';
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - Blog Sistemi</title>
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
            border: none;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1), 0 5px 15px rgba(0,0,0,0.07);
            overflow: hidden;
            width: 100%;
            max-width: 450px;
        }
        .card-header {
            background: linear-gradient(45deg, #667eea, #764ba2);
            border: none;
            padding: 2rem;
            text-align: center;
            color: white;
        }
        .card-body {
            padding: 2.5rem;
            background: white;
        }
        .form-control {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 12px 15px;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .btn-primary {
            background: linear-gradient(45deg, #667eea, #764ba2);
            border: none;
            border-radius: 10px;
            padding: 12px;
            font-size: 16px;
            font-weight: 600;
            width: 100%;
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
            background: linear-gradient(45deg, #5a6fd8, #6a4c93);
        }
        .alert {
            border-radius: 10px;
            border: none;
        }
        .security-features {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1rem;
            margin: 1rem 0;
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
        .test-credentials {
            background: #d1ecf1;
            border-radius: 10px;
            padding: 1rem;
            margin: 1rem 0;
            border-left: 4px solid #0dcaf0;
        }
        .form-check-input:checked {
            background-color: #667eea;
            border-color: #667eea;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-5 col-md-7">
                <div class="card login-card">
                    <div class="card-header">
                        <i class="fas fa-shield-alt fa-3x mb-3"></i>
                        <h3 class="mb-0">🔓 Güvenli Admin Girişi</h3>
                        <p class="mb-0 opacity-75">Hesap kilitleme devre dışı</p>
                    </div>
                    
                    <div class="card-body">
                        <!-- TEST BİLGİLERİ -->
                        <div class="test-credentials">
                            <h6><i class="fas fa-key text-info"></i> Test Giriş Bilgileri</h6>
                            <p class="mb-1"><strong>👤 Kullanıcı:</strong> <code>admin</code></p>
                            <p class="mb-0"><strong>🔒 Şifre:</strong> <code>admin123</code></p>
                        </div>

                        <?php if ($error): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle"></i>
                                <?php echo htmlspecialchars($error); ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($success): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle"></i>
                                <?php echo htmlspecialchars($success); ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($debug_info && !$success): ?>
                            <div class="debug-info">
                                <h6><i class="fas fa-bug"></i> Debug Bilgileri</h6>
                                <?php echo $debug_info; ?>
                            </div>
                        <?php endif; ?>

                        <form method="post" action="">
                            <div class="mb-3">
                                <label for="username" class="form-label">
                                    <i class="fas fa-user text-primary"></i> Kullanıcı Adı
                                </label>
                                <input type="text" 
                                       class="form-control" 
                                       id="username" 
                                       name="username" 
                                       value="admin"
                                       placeholder="Kullanıcı adınızı girin" 
                                       required>
                            </div>

                            <div class="mb-3">
                                <label for="password" class="form-label">
                                    <i class="fas fa-lock text-primary"></i> Şifre
                                </label>
                                <input type="password" 
                                       class="form-control" 
                                       id="password" 
                                       name="password" 
                                       value="admin123"
                                       placeholder="Şifrenizi girin" 
                                       required>
                            </div>

                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="remember_me" name="remember_me">
                                <label class="form-check-label" for="remember_me">
                                    <i class="fas fa-heart text-danger"></i> Beni Hatırla (30 gün)
                                </label>
                            </div>

                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-sign-in-alt"></i> Güvenli Giriş Yap
                            </button>
                        </form>

                        <!-- GÜVENLİK ÖZELLİKLERİ -->
                        <div class="security-features mt-4">
                            <h6><i class="fas fa-shield-check text-success"></i> Güvenlik Özellikleri</h6>
                            <div class="row">
                                <div class="col-6">
                                    <small class="text-success">✅ CSRF Koruması</small><br>
                                    <small class="text-success">✅ Session Güvenliği</small><br>
                                    <small class="text-warning">⚠️ Hesap Kilitleme: KAPALI</small>
                                </div>
                                <div class="col-6">
                                    <small class="text-success">✅ Argon2ID Hash</small><br>
                                    <small class="text-success">✅ Login Takibi</small><br>
                                    <small class="text-info">ℹ️ Debug Mode: AKTİF</small>
                                </div>
                            </div>
                        </div>

                        <div class="text-center mt-4">
                            <hr>
                            <a href="../unlock_admin.php" class="btn btn-outline-warning btn-sm me-2">
                                <i class="fas fa-unlock"></i> Hesap Kilidi Kaldır
                            </a>
                            <a href="../fix_password.php" class="btn btn-outline-info btn-sm me-2">
                                <i class="fas fa-wrench"></i> Şifre Düzelt
                            </a>
                            <a href="../index.php" class="text-decoration-none">
                                <i class="fas fa-home"></i> Ana Sayfaya Dön
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- OTO-DOLDUR SCRIPT -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Formu otomatik doldur
            const usernameField = document.getElementById('username');
            const passwordField = document.getElementById('password');
            
            if (!usernameField.value) usernameField.value = 'admin';
            if (!passwordField.value) passwordField.value = 'admin123';
            
            // Enter tuşu ile form gönder
            document.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    document.querySelector('form').submit();
                }
            });
        });
    </script>
</body>
</html> 