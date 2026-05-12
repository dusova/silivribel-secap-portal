<p align="center">
  <img src="varliklar/belediye-logo.svg" width="80" alt="Silivri Belediyesi">
</p>

<h1 align="center">SECAP Portalı</h1>

<p align="center">
  <strong>Silivri Belediyesi — Sürdürülebilir Enerji ve İklim Eylem Planı Takip Sistemi</strong>
</p>

<p align="center">
  <img src="https://img.shields.io/badge/PHP-7.4+-8892BF?style=flat-square&logo=php&logoColor=white" alt="PHP 7.4+">
  <img src="https://img.shields.io/badge/MySQL-5.7+-4479A1?style=flat-square&logo=mysql&logoColor=white" alt="MySQL 5.7+">
  <img src="https://img.shields.io/badge/Bootstrap-5.3-7952B3?style=flat-square&logo=bootstrap&logoColor=white" alt="Bootstrap 5.3">
  <img src="https://img.shields.io/badge/Lisans-Kurum%20İçi-red?style=flat-square" alt="Lisans">
</p>

---

## Hakkında

SECAP Portalı, Silivri Belediyesi'nin **Covenant of Mayors** (Belediye Başkanları Sözleşmesi) kapsamında hazırladığı Sürdürülebilir Enerji ve İklim Eylem Planı'nın (SECAP) takibini sağlayan kurum içi web uygulamasıdır.

Sistem; SECAP eylemlerinin tanımlanması, müdürlüklere atanması, ilerleme verilerinin toplanması, KPI takibi, kanıt dosyası yönetimi ve Excel/PDF raporlama süreçlerini tek bir merkezden yönetir.

### Temel Özellikler

| Modül | Açıklama |
|-------|----------|
| **Eylem Yönetimi** | SECAP eylemlerini oluşturma, düzenleme, müdürlüklere atama ve durum takibi |
| **Veri Toplama** | Müdürlüklerden periyodik veri girişi toplama ve onay mekanizması |
| **KPI İzleme** | Her eylem için özel KPI tanımlama ve ilerleme ölçümü |
| **Faaliyet Kayıtları** | Eylemler altında faaliyet oluşturma ve kanıt dosyası ekleme |
| **Veri Onay Sistemi** | Admin tarafından gelen verilerin onay/red süreç yönetimi |
| **Veri İzleme** | Grafik tabanlı veri izleme ve trend analizi (Chart.js) |
| **Raporlama** | Excel ve PDF formatında detaylı rapor dışa aktarımı |
| **Bildirim Sistemi** | Kullanıcılara otomatik ve manuel bildirim gönderimi |
| **Denetim Günlüğü** | Tüm sistem işlemlerinin kayıt altına alınması |
| **Yedekleme** | Veritabanı yedekleme ve geri yükleme |
| **Çöp Kutusu** | Soft-delete ile silinen kayıtları geri yükleme |
| **Oturum Yönetimi** | Aktif oturumları izleme ve uzaktan sonlandırma |

---

## Mimari

```
secap-portal/
│
├── index.php                        # Giriş noktası → kontrol merkezine yönlendirir
├── giris.php                        # Oturum açma ekranı
├── kontrol-merkezi.php              # Ana panel (dashboard)
│
├── uygulama/                        # Çekirdek katman (web erişimine kapalı)
│   ├── baslat.php                   #   Bootstrap — tüm bağımlılıkları yükler
│   ├── php7-uyumluluk.php           #   PHP 7.4 polyfill (str_starts_with vb.)
│   ├── ayarlar/                     #   Yapılandırma sınıfları
│   │   ├── kimlik-dogrulama.php     #     Auth sınıfı, oturum, brute-force koruması
│   │   ├── uygulama-ayarlari.php    #     AppConfig — host/proxy yapılandırması
│   │   └── veritabani.php           #     Database — PDO singleton bağlantısı
│   ├── parcalar/                    #   Tekrar kullanılabilir UI bileşenleri
│   │   ├── cop-kutusu-modal.php     #     Geri yükleme onay modalı
│   │   └── yardim-merkezi.php       #     Kapsamlı kullanım kılavuzu (data-driven)
│   ├── yardimcilar/                 #   Servis katmanı
│   │   ├── bildirim-servisi.php     #     NotificationService — bildirim motoru
│   │   ├── cop-kutusu-servisi.php   #     TrashService — soft-delete yönetimi
│   │   ├── csrf.php                 #     CSRF token üretimi ve doğrulaması
│   │   ├── denetim-kaydi.php        #     AuditLog — tüm işlem kayıtları
│   │   ├── dogrulayici.php          #     Validator — form veri doğrulama
│   │   ├── excel-aktarim.php        #     Excel export (XML Spreadsheet 2003)
│   │   ├── istek-korumasi.php       #     RequestGuard — trusted host kontrolü
│   │   ├── istemci-ip.php           #     ClientIp — proxy arkası IP tespiti
│   │   ├── mesaj.php                #     Flash — tek seferlik bildirim mesajları
│   │   ├── pdf-rapor.php            #     PDF rapor oluşturma motoru
│   │   └── v2-yardimcilari.php      #     Genel yardımcı fonksiyonlar
│   └── yerlesim/                    #   Ana şablon
│       ├── ust.php                  #     Header, sidebar navigasyon, bildirim zili
│       └── alt.php                  #     Footer, sidebar JS, bildirim polling
│
├── yonetim/                         # Admin paneli (16 sayfa)
│   ├── eylemler.php                 #   SECAP eylem listesi ve filtreleme
│   ├── eylem-formu.php              #   Eylem oluşturma/düzenleme formu
│   ├── faaliyet-formu.php           #   Faaliyet oluşturma formu
│   ├── kpi-formu.php                #   KPI tanımlama ve düzenleme
│   ├── veri-onay.php                #   Gelen verileri onaylama/reddetme
│   ├── veri-izleme.php              #   Grafik tabanlı veri izleme
│   ├── disa-aktar.php               #   Excel/PDF rapor dışa aktarım
│   ├── kullanicilar.php             #   Kullanıcı listesi ve yönetimi
│   ├── kullanici-formu.php          #   Kullanıcı oluşturma/düzenleme
│   ├── operasyon.php                #   Operasyon merkezi (toplu işlemler)
│   ├── denetim-gunlugu.php          #   İşlem denetim geçmişi
│   ├── yedekler.php                 #   Veritabanı yedek yönetimi
│   ├── oturumlar.php                #   Aktif oturum izleme
│   ├── cop-kutusu.php               #   Silinen kayıtları geri yükleme
│   └── yardim.php                   #   Kullanım kılavuzu
│
├── mudurluk/                        # Müdürlük paneli (5 sayfa)
│   ├── eylemlerim.php               #   Müdürlüğe atanmış eylemler
│   ├── veri-girisi.php              #   Periyodik veri giriş formu
│   ├── veri-gecmisim.php            #   Geçmiş veri girişleri
│   ├── faaliyet-ekle.php            #   Faaliyet oluşturma
│   └── yardim.php                   #   Kullanım kılavuzu
│
├── varliklar/                       # Statik dosyalar (public)
│   ├── stiller/arayuz.css           #   Ana stil dosyası (Türkçe class isimleri)
│   ├── fontlar/                     #   Lokal fontlar (Manrope, Outfit)
│   ├── kutuphaneler/                #   Bootstrap 5, Bootstrap Icons, Chart.js
│   └── gorseller/                   #   Görseller
│
└── depolama/                        # Dinamik dosyalar (web erişimine kapalı)
    ├── gecici-dosyalar/             #   Geçici yükleme alanı
    ├── kanit-dosyalari/             #   Kanıt belgeleri (yıl/ay bazlı)
    └── sistem-yedekleri/            #   Veritabanı yedekleri
```

---

## Gereksinimler

| Bileşen | Minimum | Önerilen |
|---------|---------|----------|
| PHP | 7.4 | 8.1+ |
| MySQL / MariaDB | 5.7 / 10.3 | 8.0 / 10.6+ |
| Web Sunucusu | Apache 2.4 veya IIS 10 | — |

**PHP Eklentileri:** `pdo_mysql`, `mbstring`, `json`, `session`

> PHP 8.x ile tam uyumludur. `uygulama/php7-uyumluluk.php` dosyası PHP 7.4 ortamlar için `str_starts_with`, `str_ends_with`, `str_contains` polyfill'lerini sağlar.

---

## Kurulum

### 1. Dosyaları Yükleyin

```bash
git clone https://github.com/dusova/silivribel-secap-portal.git
```

### 2. Ortam Ayarlarını Yapılandırın

```bash
cp ornek-ortam-ayarlari.php ortam.php
```

`ortam.php` dosyasını düzenleyin:

```php
return [
    'DB_HOST'    => 'localhost',
    'DB_PORT'    => '3306',
    'DB_NAME'    => 'secap_portal',
    'DB_USER'    => 'kullanici',
    'DB_PASS'    => 'sifre',
    'DB_CHARSET' => 'utf8mb4',

    'BASE_PATH'  => '',          // Alt dizinde ise: '/secap'
    'PRODUCTION' => true,        // Canlıda true
    'APP_DEBUG'  => false,       // Canlıda false
];
```

### 3. Veritabanını Oluşturun

SQL şemasını veritabanınıza import edin.

### 4. Web Sunucusunu Yapılandırın

**Apache:** `.htaccess` dosyası otomatik çalışır. `mod_rewrite` aktif olmalıdır.

**IIS:** `web.config` dosyası otomatik çalışır. URL Rewrite modülü kurulu olmalıdır.

### 5. Dizin İzinlerini Ayarlayın

```bash
chmod 755 depolama/
chmod 755 depolama/gecici-dosyalar/
chmod 755 depolama/kanit-dosyalari/
chmod 755 depolama/sistem-yedekleri/
```

---

## Kullanıcı Rolleri

Sistem üç katmanlı bir yetkilendirme yapısına sahiptir:

| Rol | Erişim | Açıklama |
|-----|--------|----------|
| **Süper Admin** | Tüm sistem | Kullanıcı yönetimi, yedekleme, denetim günlüğü, oturum yönetimi, operasyon merkezi |
| **İklim Admin** | Eylem ve veri yönetimi | Eylem oluşturma, veri onaylama, raporlama, KPI tanımlama |
| **Müdürlük** | Kendi eylemleri | Atanmış eylemlere veri girişi, faaliyet oluşturma, kendi geçmişini görme |

---

## Güvenlik

| Önlem | Detay |
|-------|-------|
| **CSRF Koruması** | Tüm POST formlarında token doğrulaması |
| **Brute-Force Koruması** | Başarısız giriş denemelerinde IP/kullanıcı bazlı geçici kilitleme |
| **Oturum Güvenliği** | HttpOnly cookie, session fixation koruması, tek oturum politikası |
| **XSS Koruması** | Tüm çıktılarda `htmlspecialchars()` ile encoding |
| **SQL Injection** | PDO prepared statements ile parametrik sorgular |
| **Dizin Koruması** | `uygulama/` ve `depolama/` dizinleri web erişimine kapalı |
| **Güvenlik Başlıkları** | CSP, X-Frame-Options, X-Content-Type-Options, Referrer-Policy |
| **Dosya Erişim Engeli** | `.sql`, `.env`, `.log`, `.md`, `.bak` uzantıları ve hassas dosyalar engellenmiş |
| **Trusted Host** | İsteğe bağlı host whitelist ile domain kısıtlaması |
| **Denetim Kaydı** | Tüm CRUD işlemleri, giriş/çıkış ve yetki değişiklikleri loglanır |

---

## Teknoloji Altyapısı

| Katman | Teknoloji |
|--------|-----------|
| **Backend** | Vanilla PHP (framework'süz, sınıf tabanlı mimari) |
| **Veritabanı** | MySQL / MariaDB, PDO |
| **Frontend** | Bootstrap 5.3, Bootstrap Icons, Chart.js |
| **Tipografi** | Manrope (UI), Outfit (başlıklar) — lokal woff2 |
| **Sunucu** | Apache (.htaccess) veya IIS (web.config) desteği |
| **Dış Bağımlılık** | Yok — tüm kütüphaneler lokal, offline çalışır |

---

## Kod Yapısı

Proje tamamen **Türkçe isimlendirme** kullanır:

- **Dosya isimleri:** `kontrol-merkezi.php`, `eylem-formu.php`, `veri-onay.php`
- **Dizin isimleri:** `uygulama/`, `varliklar/`, `yonetim/`, `mudurluk/`, `depolama/`
- **CSS class'ları:** `uygulama-kapsayici`, `ust-cubuk`, `istatistik-karti`, `rozet-planli`
- **CSS değişkenleri:** `--arkaplan-govde`, `--renk-vurgu`, `--yazi-birincil`, `--kenar-acik`
- **HTML ID'leri:** `kenar-cubugu`, `ana-icerik`, `kenarDaraltBtn`
- **URL yolları:** `/kontrol-merkezi`, `/yonetim/eylemler`, `/mudurluk/veri-girisi`

> Bootstrap ve üçüncü parti kütüphane class isimleri (card, btn, badge, d-flex vb.) İngilizce olarak korunmuştur.

---

## Ekran Görüntüleri

> Ekran görüntüleri eklenecek.

---

## Lisans

Bu yazılım **T.C. Silivri Belediyesi Bilgi İşlem Müdürlüğü** tarafından kurum içi kullanım amacıyla geliştirilmiştir. Tüm hakları saklıdır.

---

<p align="center">
  <sub>Silivri Belediyesi Bilgi İşlem Müdürlüğü tarafından 💚 ile geliştirilmiştir.</sub>
</p>
