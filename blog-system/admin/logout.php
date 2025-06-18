<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

secure_session_start();

// Admin kontrolü
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    try {
        // Remember token'ları temizle
        if (isset($_SESSION['admin_user_id'])) {
            $stmt = $pdo->prepare("DELETE FROM remember_tokens WHERE user_id = :user_id");
            $stmt->execute([':user_id' => $_SESSION['admin_user_id']]);
        }
    } catch (PDOException $e) {
        error_log("Logout error: " . $e->getMessage());
    }
}

// Güvenli logout işlemi
secure_logout();

// Login sayfasına yönlendir
header('Location: login.php?logout=success');
exit();
?> 