# 🛡️ Güvenli Blog Sistemi

[![GitHub](https://img.shields.io/badge/GitHub-ibohappy%2Fturkticaret-blue?logo=github)](https://github.com/ibohappy/turkticaret)
[![PHP](https://img.shields.io/badge/PHP-87.4%25-777bb4?logo=php)](https://www.php.net/)
[![JavaScript](https://img.shields.io/badge/JavaScript-7.9%25-f7df1e?logo=javascript)](https://developer.mozilla.org/en-US/docs/Web/JavaScript)
[![CSS](https://img.shields.io/badge/CSS-4.6%25-1572b6?logo=css3)](https://developer.mozilla.org/en-US/docs/Web/CSS)

Modern PHP ve MySQL teknolojileri kullanılarak geliştirilmiş, gelişmiş güvenlik özellikleri ile donatılmış blog yönetim sistemi.

## ✨ Önemli Özellikler

### 🔒 Güvenlik
- **CSRF ve XSS Koruması** - Modern web saldırılarına karşı tam koruma
- **SQL Injection Koruması** - PDO Prepared Statements ile güvenli veritabanı işlemleri
- **Güvenli Kimlik Doğrulama** - Argon2ID şifreleme ve session güvenliği
- **Rate Limiting** - Brute force saldırılarına karşı otomatik koruma
- **Hesap Kilitleme** - Başarısız giriş denemelerine karşı güvenlik

### 🎯 Admin Paneli
- **Dashboard** - Sistem genel görünümü ve istatistikler
- **Makale Yönetimi** - Ekle, düzenle, sil ve listele
- **Kategori Yönetimi** - Makale organizasyonu
- **Güvenli Dosya Yükleme** - Resim yükleme ve boyut kontrolü

### 📱 Kullanıcı Deneyimi
- **Responsive Tasarım** - Mobile-first yaklaşım ile tüm cihazlarda uyumlu
- **Gelişmiş Arama** - Başlık, içerik ve etiketlerde hızlı arama
- **SEO Dostu** - Arama motorları için optimize edilmiş yapı
- **Modern UI** - Bootstrap 5 ile şık ve kullanışlı arayüz

### 📝 İçerik Yönetimi
- **Etiket Sistemi** - Makale kategorilendirme ve filtreleme
- **Resim Desteği** - Güvenli resim yükleme ve yönetimi
- **Rich Content** - HTML içerik desteği ve güvenlik filtreleme
- **Tarih Yönetimi** - Otomatik tarih takibi

## 🚀 Kurulum Rehberi

### 📋 Sistem Gereksinimleri
- **PHP**: 7.4 veya üzeri (PHP 8.0+ önerilir)
- **MySQL**: 5.7+ veya MariaDB 10.2+
- **Web Server**: Apache 2.4+ / Nginx 1.18+
- **PHP Extensions**: PDO, PDO_MySQL, fileinfo, mbstring
- **Disk Alanı**: Minimum 50MB

### 📦 1. Adım: Projeyi İndirin

#### Git ile indirme:
```bash
git clone https://github.com/ibohappy/turkticaret.git
cd turkticaret
```

#### Alternatif: ZIP dosyası ile
1. GitHub'dan ZIP olarak indirin
2. Web server dizininize çıkarın (`htdocs`, `www` vb.)

### 🗄️ 2. Adım: Veritabanı Kurulumu

#### MySQL/MariaDB'de veritabanı oluşturun:
```sql
mysql -u root -p
CREATE DATABASE blog_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
EXIT;
```

#### Tabloları ve örnek verileri içe aktarın:
```bash
mysql -u root -p blog_system < database.sql
```

### ⚙️ 3. Adım: Yapılandırma Dosyası

#### `config/database.php` dosyasını oluşturun:
```php
<?php
$host = 'localhost';
$dbname = 'blog_system';
$username = 'root';  // Kendi kullanıcı adınız
$password = '';      // Kendi şifreniz
$charset = 'utf8mb4';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=$charset", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    die("Veritabanı bağlantı hatası: " . $e->getMessage());
}
?>
```

### 📁 4. Adım: Dizin İzinleri

#### Linux/Mac:
```bash
chmod 755 assets/images/
chmod 644 config/database.php
chmod 644 .htaccess
```

#### Windows (XAMPP):
- `assets/images/` klasörüne yazma izni verin
- Web server'ın bu klasöre erişebilmesini sağlayın

### 🌐 5. Adım: Web Server Yapılandırması

#### Apache (.htaccess zaten mevcut):
```apache
# URL Rewriting için mod_rewrite aktif olmalı
# Apache konfigürasyonunda AllowOverride All olmalı
```

#### Nginx (örnek konfigürasyon):
```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}

location ~ \.php$ {
    fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
    fastcgi_index index.php;
    include fastcgi_params;
}
```

### 🚀 6. Adım: İlk Çalıştırma

#### XAMPP Kullanıcıları:
1. XAMPP Control Panel'i açın
2. Apache ve MySQL'i başlatın
3. Tarayıcıda `http://localhost/turkticaret/blog-system` adresine gidin

#### Live Server Kullanıcıları:
1. Dosyaları hosting sağlayıcınıza yükleyin
2. Veritabanı bilgilerini hosting ayarlarınıza göre güncelleyin
3. Domain adresinizi ziyaret edin

### 👤 7. Adım: Admin Girişi

**Admin Panel URL**: `http://your-domain.com/blog-system/admin/login.php`

**Varsayılan Giriş Bilgileri**:
- **Kullanıcı Adı**: `admin`
- **Şifre**: `admin123`

**⚠️ Önemli**: İlk girişten sonra mutlaka şifrenizi değiştirin!

### 🔧 8. Adım: Güvenlik Ayarları (Önerilen)

#### Şifre değiştirme:
1. Admin panele giriş yapın
2. Profil ayarlarından şifrenizi değiştirin

#### Production için ek ayarlar:
```php
// config/database.php'ye ekleyin
ini_set('display_errors', 0);  // Hataları gizle
error_reporting(0);            // Error reporting'i kapat
```

### 🛠️ Sorun Giderme

#### ❌ "Veritabanı bağlantı hatası" alıyorum:
- MySQL/MariaDB servisinin çalıştığını kontrol edin
- `config/database.php` dosyasındaki bilgileri doğrulayın
- Veritabanı kullanıcısının yeterli yetkiye sahip olduğunu kontrol edin

#### ❌ "404 Not Found" hatası alıyorum:
- `.htaccess` dosyasının mevcut olduğunu kontrol edin
- Apache'de `mod_rewrite` modülünün aktif olduğunu kontrol edin
- `AllowOverride All` ayarının yapıldığını kontrol edin

#### ❌ Resim yükleme çalışmıyor:
- `assets/images/` klasörüne yazma izni verin
- PHP'de `fileinfo` extension'ının aktif olduğunu kontrol edin
- `upload_max_filesize` ve `post_max_size` değerlerini kontrol edin

#### ❌ Session hataları alıyorum:
- PHP'de session'ların çalıştığını kontrol edin
- Session klasörüne yazma izni olduğunu kontrol edin
- Tarayıcı cookie'lerini temizleyin

### 📞 Destek

Sorun yaşıyorsanız:
1. Önce sorun giderme bölümünü kontrol edin
2. PHP ve MySQL error log'larını inceleyin
3. [GitHub issues](https://github.com/ibohappy/turkticaret/issues) kısmında sorununuzu paylaşın

### 🎯 Kurulum Sonrası

Başarılı kurulum sonrası yapabilecekleriniz:
- ✅ İlk makalenizi yazın
- ✅ Kategorileri özelleştirin
- ✅ Site ayarlarını yapılandırın
- ✅ Güvenlik ayarlarını gözden geçirin

## 🎯 Kullanım

### Admin İşlemleri
- **📊 Dashboard**: Sistem durumu ve hızlı erişim
- **📝 Makale Ekle**: Gelişmiş form validasyonu ile içerik oluşturma
- **📋 Makale Listesi**: Tüm içerikleri görüntüleme ve yönetme
- **🏷️ Kategori Yönetimi**: İçerik organizasyonu

### Ziyaretçi Deneyimi
- **Ana Sayfa**: Son makaleler ve öne çıkan içerikler
- **Makale Detay**: Tam içerik görüntüleme
- **Arama**: Gelişmiş filtreleme seçenekleri
- **Kategori Browsing**: Konu bazlı gezinme

## 🔧 Teknik Özellikler

### 💻 Teknoloji Stack
- **PHP** (87.4%) - Ana backend teknolojisi
- **JavaScript** (7.9%) - Frontend etkileşim
- **CSS** (4.6%) - Responsive tasarım ve stil
- **Diğer** (0.1%) - Konfigürasyon dosyaları

### 🔒 Güvenlik Altyapısı
- Session timeout ve güvenli cookie yönetimi
- "Beni Hatırla" özelliği ile güvenli otomatik giriş
- Tüm giriş denemelerinin loglanması
- Input validasyonu ve sanitization

### ⚡ Performans
- Optimize edilmiş veritabanı sorguları
- Responsive resim yükleme
- Hızlı arama algoritması
- Efficient session management

## 📄 Lisans

MIT Lisansı ile lisanslanmıştır.

---

**⚠️ Güvenlik Notu**: Production kullanımında HTTPS kullanın ve düzenli güvenlik güncellemeleri yapın. 