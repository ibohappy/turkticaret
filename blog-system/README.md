# 🛡️ Güvenli Blog Sistemi

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

## 🚀 Hızlı Kurulum

### Sistem Gereksinimleri
- PHP 7.4+
- MySQL 5.7+ / MariaDB 10.2+
- Web Server (Apache/Nginx)

### Kurulum Adımları
1. **Veritabanı oluşturun**: `mysql -u root -p < database.sql`
2. **Ayarları yapılandırın**: `config/database.php` dosyasını düzenleyin
3. **Admin girişi**: `admin/login.php` - Kullanıcı: `admin`, Şifre: `Admin123!`

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

### Güvenlik Altyapısı
- Session timeout ve güvenli cookie yönetimi
- "Beni Hatırla" özelliği ile güvenli otomatik giriş
- Tüm giriş denemelerinin loglanması
- Input validasyonu ve sanitization

### Performans
- Optimize edilmiş veritabanı sorguları
- Responsive resim yükleme
- Hızlı arama algoritması
- Efficient session management

## 📄 Lisans

MIT Lisansı ile lisanslanmıştır.

---

**⚠️ Güvenlik Notu**: Production kullanımında HTTPS kullanın ve düzenli güvenlik güncellemeleri yapın. 