<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Güvenlik başlıklarını ayarla
set_security_headers();

// Güvenli session başlat (functions.php'den)
secure_session_start();

$error = '';
$success = '';
$login_attempts_exceeded = false;

// Zaten giriş yapmışsa dashboard'a yönlendir
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: dashboard.php');
    exit();
}

// Remember token kontrolü
if (!isset($_SESSION['admin_logged_in']) && isset($_COOKIE['remember_token'])) {
    $token = $_COOKIE['remember_token'];
    $token_hash = hash_remember_token($token);
    
    try {
        $stmt = $pdo->prepare("
            SELECT rt.user_id, au.username, au.status 
            FROM remember_tokens rt 
            JOIN admin_users au ON rt.user_id = au.id 
            WHERE rt.token_hash = :token_hash 
            AND rt.expires_at > NOW() 
            AND au.status = 'active'
        ");
        $stmt->bindValue(':token_hash', $token_hash);
        $stmt->execute();
        
        if ($user = $stmt->fetch(PDO::FETCH_ASSOC)) {
            // Otomatik login
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_user_id'] = $user['user_id'];
            $_SESSION['admin_username'] = $user['username'];
            
            // Son login bilgilerini güncelle
            $update_stmt = $pdo->prepare("
                UPDATE admin_users 
                SET last_login = NOW(), last_login_ip = :ip 
                WHERE id = :user_id
            ");
            $update_stmt->bindValue(':ip', get_client_ip());
            $update_stmt->bindValue(':user_id', $user['user_id']);
            $update_stmt->execute();
            
            // Yeni token oluştur (güvenlik için)
            $new_token = generate_remember_token();
            $new_token_hash = hash_remember_token($new_token);
            
            // Eski token'ı sil ve yenisini ekle
            $pdo->prepare("DELETE FROM remember_tokens WHERE user_id = :user_id")->execute([':user_id' => $user['user_id']]);
            
            $insert_stmt = $pdo->prepare("
                INSERT INTO remember_tokens (user_id, token_hash, expires_at, user_agent, ip_address) 
                VALUES (:user_id, :token_hash, DATE_ADD(NOW(), INTERVAL 30 DAY), :user_agent, :ip)
            ");
            $insert_stmt->execute([
                ':user_id' => $user['user_id'],
                ':token_hash' => $new_token_hash,
                ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                ':ip' => get_client_ip()
            ]);
            
            setcookie('remember_token', $new_token, time() + (30 * 24 * 60 * 60), '/', '', true, true);
            
            header('Location: dashboard.php');
            exit();
        } else {
            // Geçersiz token, cookie'yi temizle
            setcookie('remember_token', '', time() - 3600, '/', '', true, true);
        }
    } catch (PDOException $e) {
        error_log("Remember token error: " . $e->getMessage());
    }
}

// Login form işlemi
if ($_POST) {
    // CSRF token kontrolü
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $error = 'Güvenlik hatası! Lütfen formu yeniden gönderin.';
    } else {
        // Rate limiting kontrolü
        if (!check_rate_limit('login', 5, 300)) {
            $login_attempts_exceeded = true;
            $remaining_time = get_rate_limit_remaining_time('login', 5, 300);
            $time_formatted = format_remaining_time($remaining_time);
            $error = "Çok fazla başarısız giriş denemesi. Lütfen {$time_formatted} dakika bekleyip tekrar deneyin.";
        } else {
            $username = clean_input($_POST['username']);
            $password = $_POST['password']; // Şifre temizlenmez, hash kontrolü için gerekli
            $remember_me = isset($_POST['remember_me']) ? true : false;
            
            // Kullanıcı adı ve şifre validasyonu
            if (empty($username) || empty($password)) {
                $error = 'Kullanıcı adı ve şifre boş olamaz!';
            } else {
                try {
                    // Kullanıcıyı veritabanından al
                    $stmt = $pdo->prepare("
                        SELECT id, username, password_hash, status, failed_login_attempts, 
                               account_locked_until, last_failed_login 
                        FROM admin_users 
                        WHERE username = :username
                    ");
                    $stmt->bindValue(':username', $username);
                    $stmt->execute();
                    
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    // Hesap kilidi kontrolü - DEVRE DIŞI BIRAKILIYOR
                    /*
                    if ($user && $user['account_locked_until'] && 
                        strtotime($user['account_locked_until']) > time()) {
                        $error = 'Hesabınız geçici olarak kilitlenmiştir. Lütfen daha sonra tekrar deneyin.';
                        
                        // Login log
                        $log_stmt = $pdo->prepare("
                            INSERT INTO login_logs (username, ip_address, user_agent, success) 
                            VALUES (:username, :ip, :user_agent, 0)
                        ");
                        $log_stmt->execute([
                            ':username' => $username,
                            ':ip' => get_client_ip(),
                            ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                        ]);
                    } 
                    */
                    
                    // Kullanıcı var ve şifre doğru - KILITLEME KONTROLSÜZ
                    if ($user && $user['status'] === 'active' && 
                             verify_password($password, $user['password_hash'])) {
                        
                        // Session ID yenile (güvenlik için)
                        session_regenerate_id(true);
                        
                        // Başarılı login
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
                            ':ip' => get_client_ip(),
                            ':id' => $user['id']
                        ]);
                        
                        // Beni hatırla özelliği
                        if ($remember_me) {
                            $remember_token = generate_remember_token();
                            $token_hash = hash_remember_token($remember_token);
                            
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
                                ':ip' => get_client_ip()
                            ]);
                            
                            // Cookie ayarla (30 gün)
                            setcookie('remember_token', $remember_token, 
                                time() + (30 * 24 * 60 * 60), '/', '', true, true);
                        }
                        
                        // Başarılı login log
                        $log_stmt = $pdo->prepare("
                            INSERT INTO login_logs (username, ip_address, user_agent, success) 
                            VALUES (:username, :ip, :user_agent, 1)
                        ");
                        $log_stmt->execute([
                            ':username' => $username,
                            ':ip' => get_client_ip(),
                            ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                        ]);
                        
                        // Redirect parametresi varsa oraya yönlendir
                        $redirect = isset($_GET['redirect']) ? $_GET['redirect'] : 'dashboard.php';
                        $redirect = filter_var($redirect, FILTER_SANITIZE_URL);
                        header('Location: ' . $redirect);
                        exit();
                        
                    } else {
                        // Başarısız login - HESAP KİLİTLEME DEVRE DIŞI
                        if ($user) {
                            $failed_attempts = $user['failed_login_attempts'] + 1;
                            $lock_account = false; // HİÇBİR ZAMAN KİLİTLEME
                            
                            /*
                            // 5 başarısız denemeden sonra hesabı kilitle - DEVRE DIŞI
                            if ($failed_attempts >= 5) {
                                $lock_account = true;
                                $error = 'Çok fazla başarısız giriş denemesi. Hesabınız 15 dakika kilitlenmiştir.';
                            } else {
                                $remaining = 5 - $failed_attempts;
                                $error = "Kullanıcı adı veya şifre hatalı! Kalan deneme hakkı: {$remaining}";
                            }
                            */
                            
                            if ($failed_attempts >= 4) {
                                $error = 'Kullanıcı adı veya şifre hatalı! Bir sonraki yanlış girişte 5 dakika beklemeniz gerekecek.';
                            } else {
                                $remaining_attempts = 5 - $failed_attempts;
                                $error = "Kullanıcı adı veya şifre hatalı! Kalan deneme hakkı: {$remaining_attempts}";
                            }
                            
                            // Failed attempts güncelle - KİLİTLEME OLMADAN
                            $update_stmt = $pdo->prepare("
                                UPDATE admin_users 
                                SET failed_login_attempts = :attempts,
                                    last_failed_login = NOW(),
                                    account_locked_until = NULL
                                WHERE id = :id
                            ");
                            $update_stmt->execute([
                                ':attempts' => $failed_attempts,
                                ':id' => $user['id']
                            ]);
                        } else {
                            $error = 'Kullanıcı adı veya şifre hatalı!';
                        }
                        
                        // Başarısız login log
                        $log_stmt = $pdo->prepare("
                            INSERT INTO login_logs (username, ip_address, user_agent, success) 
                            VALUES (:username, :ip, :user_agent, 0)
                        ");
                        $log_stmt->execute([
                            ':username' => $username,
                            ':ip' => get_client_ip(),
                            ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                        ]);
                    }
                } catch (PDOException $e) {
                    error_log("Login error: " . $e->getMessage());
                    $error = 'Sistem hatası! Lütfen daha sonra tekrar deneyin.';
                }
            }
        }
    }
}

// URL parametrelerine göre mesajlar
if (isset($_GET['timeout']) && $_GET['timeout'] == '1') {
    $error = 'Oturum süreniz dolmuş. Güvenlik için tekrar giriş yapmanız gerekiyor.';
}

if (isset($_GET['logout']) && $_GET['logout'] == '1') {
    $success = 'Başarıyla çıkış yaptınız.';
}

$page_title = 'Admin Girişi';
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - Güvenli Blog Sistemi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #2563eb 0%, #059669 100%);
            min-height: 100vh;
            font-family: 'Inter', 'Segoe UI', -apple-system, BlinkMacSystemFont, sans-serif;
        }
        .login-card {
            border: none;
            border-radius: 1rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            backdrop-filter: blur(16px);
        }
        .card-header {
            background: linear-gradient(135deg, #1e40af 0%, #047857 100%);
            border-radius: 1rem 1rem 0 0 !important;
            position: relative;
        }
        .card-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, rgba(255,255,255,0.1) 0%, transparent 50%);
        }
        .card-header h3, .card-header p {
            position: relative;
            z-index: 1;
        }
        .form-control {
            border: 2px solid #e2e8f0;
            border-radius: 0.5rem;
            padding: 0.875rem 1rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            background: #ffffff;
        }
        .form-control:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
            outline: none;
        }
        .form-control:hover {
            border-color: #cbd5e1;
        }
        .form-label {
            color: #374151;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        .btn-primary {
            background: linear-gradient(135deg, #2563eb, #059669);
            border: none;
            border-radius: 0.5rem;
            padding: 0.875rem 1.5rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.025em;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #3b82f6, #10b981);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(37, 99, 235, 0.3);
        }
        .btn-outline-light {
            border: 2px solid rgba(255, 255, 255, 0.3);
            color: rgba(255, 255, 255, 0.9);
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(8px);
            border-radius: 0.5rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .btn-outline-light:hover {
            background: rgba(255, 255, 255, 0.2);
            border-color: rgba(255, 255, 255, 0.5);
            color: #ffffff;
            transform: translateY(-1px);
        }
        .security-features {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 0.75rem;
            backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .alert-danger {
            background: linear-gradient(135deg, rgba(220, 38, 38, 0.1), rgba(239, 68, 68, 0.05));
            color: #dc2626;
            border: none;
            border-left: 4px solid #dc2626;
            border-radius: 0.5rem;
        }
        .alert-success {
            background: linear-gradient(135deg, rgba(5, 150, 105, 0.1), rgba(16, 185, 129, 0.05));
            color: #059669;
            border: none;
            border-left: 4px solid #059669;
            border-radius: 0.5rem;
        }
        .form-check-input:checked {
            background-color: #2563eb;
            border-color: #2563eb;
        }
        .form-check-input:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 0.25rem rgba(37, 99, 235, 0.25);
        }
        .text-success {
            color: #10b981 !important;
        }
        .card-body {
            background: #ffffff;
            padding: 2.5rem;
        }
        .invalid-feedback {
            color: #dc2626;
        }
        .form-control.is-invalid {
            border-color: #dc2626;
        }
        .btn-outline-secondary {
            border-color: #6b7280;
            color: #6b7280;
            background: rgba(107, 114, 128, 0.1);
        }
        .btn-outline-secondary:hover {
            background: #6b7280;
            border-color: #6b7280;
            color: #ffffff;
        }
        /* Animasyonlar */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translate3d(0, 40px, 0);
            }
            to {
                opacity: 1;
                transform: translate3d(0, 0, 0);
            }
        }
        .login-card {
            animation: fadeInUp 0.6s ease-out;
        }
        .security-features {
            animation: fadeInUp 0.8s ease-out;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center min-vh-100 align-items-center">
            <div class="col-md-8 col-lg-6">
                <div class="card login-card">
                    <div class="card-header text-white text-center py-4">
                        <h3><i class="fas fa-shield-alt"></i> Güvenli Admin Girişi</h3>
                        <p class="mb-0">Gelişmiş güvenlik önlemleri ile korumalı</p>
                    </div>
                    <div class="card-body p-5">
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
                        
                        <?php if (!$login_attempts_exceeded): ?>
                            <form method="POST" class="needs-validation" novalidate>
                                <?php echo csrf_field(); ?>
                                
                                <div class="mb-4">
                                    <label for="username" class="form-label">
                                        <i class="fas fa-user"></i> Kullanıcı Adı
                                    </label>
                                    <input type="text" class="form-control form-control-lg" 
                                           id="username" name="username" 
                                           value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                                           required autocomplete="username">
                                    <div class="invalid-feedback">
                                        Lütfen kullanıcı adınızı girin.
                                    </div>
                                </div>
                                
                                <div class="mb-4">
                                    <label for="password" class="form-label">
                                        <i class="fas fa-lock"></i> Şifre
                                    </label>
                                    <input type="password" class="form-control form-control-lg" 
                                           id="password" name="password" 
                                           required autocomplete="current-password">
                                    <div class="invalid-feedback">
                                        Lütfen şifrenizi girin.
                                    </div>
                                </div>
                                
                                <div class="mb-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" 
                                               id="remember_me" name="remember_me">
                                        <label class="form-check-label" for="remember_me">
                                            <i class="fas fa-heart"></i> Beni Hatırla (30 gün)
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary btn-lg">
                                        <i class="fas fa-sign-in-alt"></i> Güvenli Giriş Yap
                                    </button>
                                </div>
                            </form>
                        <?php else: ?>
                            <div class="text-center">
                                <i class="fas fa-clock fa-3x text-warning mb-3"></i>
                                <h5>Güvenlik Bekleme Süresi</h5>
                                <p class="text-muted">Çok fazla başarısız giriş denemesi yaptınız.</p>
                                <div class="alert alert-warning">
                                    <i class="fas fa-hourglass-half"></i> 
                                    Kalan süre: <span id="countdown" class="fw-bold"><?php echo format_remaining_time(get_rate_limit_remaining_time('login', 5, 300)); ?></span>
                                </div>
                                <button class="btn btn-outline-secondary" onclick="location.reload()">
                                    <i class="fas fa-refresh"></i> Sayfayı Yenile
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Güvenlik Özellikleri Bilgisi -->
                <div class="security-features text-white p-4 mt-4">
                    <h6><i class="fas fa-shield-alt"></i> Güvenlik Özellikleri:</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <ul class="list-unstyled mb-0">
                                <li><i class="fas fa-check text-success"></i> CSRF Koruması</li>
                                <li><i class="fas fa-check text-success"></i> Rate Limiting</li>
                                <li><i class="fas fa-check text-success"></i> Session Güvenliği</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <ul class="list-unstyled mb-0">
                                <li><i class="fas fa-check text-success"></i> Argon2ID Hash</li>
                                <li><i class="fas fa-check text-success"></i> Hesap Kilitleme</li>
                                <li><i class="fas fa-check text-success"></i> Login Takibi</li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <div class="text-center mt-4">
                    <a href="../index.php" class="btn btn-outline-light">
                        <i class="fas fa-arrow-left"></i> Ana Sayfaya Dön
                    </a>
                </div>
                
                <!-- Test Bilgileri GÜNCEL -->
                <div class="text-center mt-3">
                    <small class="text-white-50">
                        <i class="fas fa-key"></i> Test Hesabı: <strong>admin / admin123</strong>
                    </small>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Form validation
        (function() {
            'use strict';
            window.addEventListener('load', function() {
                var forms = document.getElementsByClassName('needs-validation');
                var validation = Array.prototype.filter.call(forms, function(form) {
                    form.addEventListener('submit', function(event) {
                        if (form.checkValidity() === false) {
                            event.preventDefault();
                            event.stopPropagation();
                        }
                        form.classList.add('was-validated');
                    }, false);
                });
            }, false);
        })();
        
        // Countdown timer for rate limit
        <?php if ($login_attempts_exceeded): ?>
        var remainingSeconds = <?php echo get_rate_limit_remaining_time('login', 5, 300); ?>;
        
        function updateCountdown() {
            if (remainingSeconds <= 0) {
                document.getElementById('countdown').textContent = '0:00';
                location.reload(); // Süre bitince sayfayı yenile
                return;
            }
            
            var minutes = Math.floor(remainingSeconds / 60);
            var seconds = remainingSeconds % 60;
            var timeString = minutes + ':' + (seconds < 10 ? '0' : '') + seconds;
            
            document.getElementById('countdown').textContent = timeString;
            remainingSeconds--;
        }
        
        // Her saniye güncelle
        updateCountdown(); // İlk çalıştırma
        setInterval(updateCountdown, 1000);
        <?php endif; ?>
    </script>
</body>
</html> 