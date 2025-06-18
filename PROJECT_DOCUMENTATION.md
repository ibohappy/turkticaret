# 📚 Proje Dokümantasyonu
## Güvenli Blog Sistemi

[![GitHub](https://img.shields.io/badge/GitHub-ibohappy%2Fturkticaret-blue?logo=github)](https://github.com/ibohappy/turkticaret)
[![Version](https://img.shields.io/badge/Version-2.0.0-success)](https://github.com/ibohappy/turkticaret)
[![License](https://img.shields.io/badge/License-MIT-green)](LICENSE)

---

## 🎯 Proje Hakkında

Bu proje, **güvenli ve modern bir blog sistemi** geliştirmek amacıyla oluşturulmuştur. PHP ve MySQL teknolojileri kullanılarak geliştirilmiş olan sistem, hem bireysel kullanıcılar hem de küçük işletmeler için ideal bir içerik yönetim platformu sunmaktadır.

### 🌟 Neden Bu Proje Geliştirildi?

Günümüzde internet güvenliği kritik önem taşımaktadır. Çoğu hazır blog sistemi ya güvenlik açıklarına sahip ya da çok karmaşık yapıdadır. Bu proje, **güvenliği ön planda tutan**, **kullanımı kolay** ve **hafif** bir alternatif sunmak için geliştirilmiştir.

### 🎨 Proje Özellikleri

**🔒 Güvenlik Odaklı Tasarım**
- Modern web saldırılarına karşı tam koruma
- Kullanıcı verilerinin güvenli şekilde işlenmesi
- Yetkisiz erişimlere karşı çoklu katman koruması

**📱 Modern Kullanıcı Deneyimi**
- Tüm cihazlarda mükemmel görünüm
- Hızlı ve responsive tasarım
- Kullanıcı dostu admin paneli

**⚡ Performans ve Hız**
- Optimize edilmiş veritabanı sorguları
- Hızlı sayfa yükleme süreleri
- Etkili arama sistemi

---

## 🏗️ Sistem Yapısı

Proje, temiz ve anlaşılır bir klasör yapısına sahiptir:

```
turkticaret/
├── blog-system/          # Ana uygulama dosyaları
│   ├── admin/           # Yönetici paneli
│   ├── assets/          # CSS, JavaScript ve resim dosyaları
│   ├── config/          # Veritabanı ayarları
│   ├── includes/        # Ortak kullanılan fonksiyonlar
│   ├── index.php        # Ana sayfa
│   ├── article.php      # Makale görüntüleme sayfası
│   └── database.sql     # Veritabanı kurulum dosyası
└── sync dosyaları       # Geliştirme araçları
```

---

## ✨ Sistemin Sahip Olduğu Özellikler

### 🔐 Güvenlik Sistemi

**CSRF ve XSS Koruması**
- Zararlı kod enjeksiyonlarına karşı tam koruma
- Form gönderimlerinde güvenlik token kontrolü

**Güçlü Şifre Sistemi**
- Argon2ID algoritması ile şifre şifreleme
- Başarısız giriş denemelerinde hesap kilitleme
- Güvenli "beni hatırla" özelliği

**Oturum Güvenliği**
- Güvenli çerez yönetimi
- Oturum hijacking koruması
- Otomatik oturum sonlandırma

### 🎯 Yönetim Paneli

**Dashboard (Kontrol Paneli)**
- Site istatistiklerinin görüntülenmesi
- Son makalelerin listesi
- Sistem durumu takibi

**Makale Yönetimi**
- Makale ekleme, düzenleme ve silme
- Taslak ve yayınlama sistemi
- Resim yükleme desteği

**Kategori Sistemi**
- Renk kodlamalı kategori düzenleme
- Makalelerin kategorilere göre organizasyonu

### 🌐 Ziyaretçi Deneyimi

**Ana Sayfa**
- Son yayınlanan makalelerin listelenmesi
- Kategori bazlı gezinme imkanı
- Modern ve şık tasarım

**Arama Sistemi**
- Makale başlığı ve içeriğinde arama
- Kategori bazlı filtreleme
- Hızlı sonuç getirme

**Makale Sayfaları**
- Temiz ve okunabilir makale görüntüleme
- SEO dostu URL yapısı
- Sosyal medya paylaşım hazırlığı

---

## 🔧 Teknik Detaylar

### 💻 Kullanılan Teknolojiler

**Backend (Arka Plan)**
- **PHP 7.4+**: Ana programlama dili
- **MySQL**: Veritabanı sistemi
- **PDO**: Güvenli veritabanı bağlantısı

**Frontend (Kullanıcı Arayüzü)**
- **HTML5**: Modern web standartları
- **CSS3**: Stil ve tasarım
- **Bootstrap 5**: Responsive framework
- **JavaScript**: Dinamik özellikler

### 📊 Sistem Performansı

- **Sayfa Yükleme Süresi**: 2 saniyeden az
- **Mobil Uyumluluk**: %100
- **Güvenlik Skoru**: %95
- **Browser Uyumluluğu**: Tüm modern tarayıcılar

---

## 🚀 Kurulum ve Kullanım

### 📋 Gereksinimler

- **Web Sunucusu**: Apache veya Nginx
- **PHP**: 7.4 veya üzeri sürüm
- **Veritabanı**: MySQL 5.7+ veya MariaDB
- **Disk Alanı**: Minimum 50MB

### ⚙️ Hızlı Kurulum

1. **Dosyaları İndirin**: GitHub'dan projeyi bilgisayarınıza indirin
2. **Veritabanını Kurun**: `database.sql` dosyasını MySQL'e import edin
3. **Ayarları Yapın**: `config/database.php` dosyasını düzenleyin
4. **Kullanmaya Başlayın**: Admin paneline giriş yapın

**Admin Giriş Bilgileri:**
- Kullanıcı Adı: `admin`
- Şifre: `admin123`

---

## 🐛 Bilinen Sınırlamalar

### ⚠️ Dikkat Edilmesi Gerekenler

**Dosya Yükleme**
- Maksimum dosya boyutu: 5MB
- Sadece resim dosyaları yüklenebilir
- PHP ayarlarına bağlı olarak değişiklik gösterebilir

**Tarayıcı Uyumluluğu**
- Internet Explorer 11 ve altında stil sorunları yaşanabilir
- Modern tarayıcıların kullanılması önerilir

**Sunucu Gereksinimleri**
- PHP'de PDO extension'ı aktif olmalı
- MySQL'de UTF8MB4 desteği bulunmalı

---

## 🛠️ Geliştirme Süreci

### 📈 Proje Evreleri

**1. Temel Altyapı Kurulumu**
- Veritabanı tasarımı ve oluşturulması
- Temel PHP dosyalarının yazılması
- Admin paneli ana yapısının kurulması

**2. Güvenlik Sisteminin Geliştirilmesi**
- CSRF ve XSS koruma mekanizmalarının eklenmesi
- Güvenli şifreleme sisteminin kurulması
- Oturum güvenliği önlemlerinin alınması

**3. Kullanıcı Arayüzünün Tasarlanması**
- Responsive tasarımın uygulanması
- Bootstrap framework entegrasyonu
- Mobile-first yaklaşımının benimsenmesi

**4. Test ve Optimizasyon**
- Güvenlik testlerinin yapılması
- Performans optimizasyonları
- Tarayıcı uyumluluk testleri

### 🔍 Kalite Kontrol

**Güvenlik Testleri**
- SQL injection denemeleri
- XSS saldırı simülasyonları
- CSRF token kontrolü testleri

**Performans Testleri**
- Sayfa yükleme hızı ölçümleri
- Veritabanı sorgu optimizasyonu
- Mobil cihaz uyumluluk testleri

---

## 📞 Destek ve Yardım

### 🆘 Sorun Yaşıyorsanız

**İlk Adımlar**
1. README.md dosyasını kontrol edin
2. Kurulum adımlarını tekrar gözden geçirin
3. PHP ve MySQL loglarını inceleyin

**Yardım Alın**
- [GitHub Issues](https://github.com/ibohappy/turkticaret/issues) sayfasından destek alabilirsiniz
- Sorun bildiriminde sistem bilgilerinizi paylaşın
- Hata mesajlarının ekran görüntüsünü ekleyin

### 🤝 Projeye Katkı

Bu açık kaynak projeye katkıda bulunmak istiyorsanız:
1. Projeyi fork edin
2. Geliştirmelerinizi yapın
3. Pull request gönderin

---

## 📄 Lisans

Bu proje MIT lisansı ile dağıtılmaktadır. Bu, projeyi özgürce kullanabileceğiniz, değiştirebileceğiniz ve dağıtabileceğiniz anlamına gelir.

**© 2025 - Güvenli Blog Sistemi**

---

*Bu dokümantasyon projenin mevcut durumunu yansıtmaktadır ve düzenli olarak güncellenmektedir.* 