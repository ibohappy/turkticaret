<?php
// Admin paneli ana giriş dosyası
// Kullanıcı /admin/ linkiyle geldiğinde otomatik olarak login sayfasına yönlendir

session_start();

// Eğer kullanıcı zaten giriş yapmışsa dashboard'a yönlendir
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: dashboard.php');
    exit();
}

// Eğer giriş yapmamışsa login sayfasına yönlendir
header('Location: login.php');
exit();
?> 