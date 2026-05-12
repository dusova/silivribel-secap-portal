<?php

declare(strict_types=1);

$B = BASE_PATH;
$helpRole = Auth::isSuperAdmin()
    ? 'super_admin'
    : (Auth::isAdmin() ? 'climate_admin' : 'department_user');

if (!function_exists('help_h')) {
    function help_h($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

$roleGuides = [
    'super_admin' => [
        'kicker' => 'Süper Admin Rehberi',
        'headline' => 'SECAP Portalı Süper Admin Kullanım Kılavuzu',
        'intro' => 'Bu rehber sistemi belediye lokal ağı içinde yöneten Süper Admin kullanıcısı için hazırlanmıştır. Kullanıcılar, oturumlar, bildirimler, SMTP, yedekler, denetim günlüğü, çöp kutusu ve operasyon kontrolleri adım adım anlatılır.',
        'demo_note' => 'Eğitimde önce Operasyon ekranını açın, sonra Bildirimler, Kullanıcılar, Oturumlar, Yedekler ve Denetim Günlüğü sırasıyla ilerleyin. Böylece teknik sağlık, erişim ve kayıt izi aynı akış içinde görülür.',
        'summary' => [
            ['label' => 'Rol Amacı', 'value' => 'Portalın güvenli, erişilebilir, yedekli ve kurum içi kullanım kurallarına uygun çalışmasını sağlamak.'],
            ['label' => 'Günlük Kontrol', 'value' => 'Bildirimler, operasyon uyarıları, yedek durumu, aktif oturumlar, kullanıcı değişiklikleri ve kritik denetim kayıtları.'],
            ['label' => 'Yetki Alanı', 'value' => 'Tüm yönetim ekranlarını görür; kullanıcı, yedek, log, çöp kutusu ve sistem operasyonu işlemlerini yürütür.'],
            ['label' => 'Güvenlik Kuralı', 'value' => 'Sistem dışa açılmadan lokal belediye ağı içinde çalışır; host kısıtı, depolama izinleri ve tek aktif oturum politikası korunur.'],
        ],
        'links' => [
            ['label' => 'Operasyon', 'href' => $B . '/yonetim/operasyon', 'class' => 'btn-success', 'icon' => 'bi-activity'],
            ['label' => 'Bildirimler', 'href' => $B . '/bildirimler', 'class' => 'btn-outline-secondary', 'icon' => 'bi-bell-fill'],
            ['label' => 'Kullanıcılar', 'href' => $B . '/yonetim/kullanicilar', 'class' => 'btn-outline-secondary', 'icon' => 'bi-people-fill'],
            ['label' => 'Oturumlar', 'href' => $B . '/yonetim/oturumlar', 'class' => 'btn-outline-secondary', 'icon' => 'bi-broadcast'],
            ['label' => 'Yedekler', 'href' => $B . '/yonetim/yedekler', 'class' => 'btn-outline-secondary', 'icon' => 'bi-database-down'],
            ['label' => 'Denetim Günlüğü', 'href' => $B . '/yonetim/denetim-gunlugu', 'class' => 'btn-outline-secondary', 'icon' => 'bi-shield-check'],
        ],
        'sections' => [
            [
                'id' => 'super-rol-gunluk',
                'title' => 'Rol, Sorumluluk ve Günlük Rutin',
                'icon' => 'bi-compass',
                'intro' => 'Süper Admin veri üretmekten çok sistemin çalışırlığını, güvenliğini ve kullanıcı erişimlerini yönetir. Veri süreçlerine müdahale edebilir; ancak her kritik işlem denetim günlüğüne düşer.',
                'blocks' => [
                    [
                        'type' => 'cards',
                        'title' => 'Süper Admin hangi işlerden sorumludur?',
                        'items' => [
                            ['title' => 'Sistem Sağlığı', 'text' => 'PHP, veritabanı, GD eklentisi, host kısıtı, depolama klasörleri ve e-posta kuyruğu kontrol edilir.', 'icon' => 'bi-heart-pulse'],
                            ['title' => 'Kullanıcı Yönetimi', 'text' => 'Kullanıcı oluşturma, rol atama, aktif/pasif yapma, şifre yenileme ve son aktif Süper Admin koruması yönetilir.', 'icon' => 'bi-people'],
                            ['title' => 'Güvenlik İzleri', 'text' => 'Denetim günlüğü, aktif oturumlar, başarısız girişler, rol değişiklikleri ve çöp kutusu kayıtları takip edilir.', 'icon' => 'bi-shield-check'],
                            ['title' => 'Operasyon Sürekliliği', 'text' => 'Yedekler alınır, başarısız yedekler incelenir, SMTP ve panel bildirimleri test edilir.', 'icon' => 'bi-database-check'],
                        ],
                    ],
                    [
                        'type' => 'checklist',
                        'title' => 'Günün ilk kontrol listesi',
                        'items' => [
                            'Sağ üst bildirim zilinde okunmamış kritik uyarı var mı bakın.',
                            'Operasyon ekranında sistem sağlığı ve depolama yazılabilirlik durumunu kontrol edin.',
                            'Yedekler ekranında son başarılı yedek tarihini ve son 7 gün hata sayısını inceleyin.',
                            'Oturumlar ekranında kullanıcı başına tek aktif oturum göründüğünü doğrulayın.',
                            'Denetim Günlüğü ekranında rol değişikliği, şifre sıfırlama, erişim reddi ve başarısız giriş kayıtlarını gözden geçirin.',
                        ],
                    ],
                    [
                        'type' => 'do_dont',
                        'title' => 'Çalışma prensibi',
                        'do_title' => 'Yap',
                        'dont_title' => 'Dikkat Et',
                        'do' => [
                            'Kullanıcı işlemlerinde doğru müdürlük ve rol bilgisini kontrol edin.',
                            'Yedek almadan büyük veri temizliği veya migration çalıştırmayın.',
                            'SMTP sorunu varsa önce Operasyon ekranından test e-postası gönderin.',
                            'Silme işlemlerinde gerekçe yazın; kayıt çöp kutusuna taşındığı için geri alma izlenebilir olur.',
                        ],
                        'dont' => [
                            'Son aktif Süper Admin kullanıcısını pasifleştirmeye veya silmeye çalışmayın.',
                            'Depolama, veritabani veya uygulama klasörlerini web erişimine açmayın.',
                            'Denetim günlüğündeki IP ve oturum bilgisini tek başına suçlama kanıtı gibi yorumlamayın; tarih, kullanıcı ve işlemle birlikte değerlendirin.',
                            'SMTP kapalı diye panel bildirimlerinin de çalışmadığını varsaymayın; ikisi ayrı kanaldır.',
                        ],
                    ],
                ],
            ],
            [
                'id' => 'super-operasyon',
                'title' => 'Operasyon Merkezi',
                'icon' => 'bi-activity',
                'intro' => 'Operasyon ekranı lokal belediye kurulumunda teknik durumun tek yerden okunması için kullanılır. Bu ekran özellikle kurulum sonrası, bakım öncesi, yedek hatasında ve e-posta problemi olduğunda ilk bakılacak yerdir.',
                'blocks' => [
                    [
                        'type' => 'table',
                        'title' => 'Sistem sağlığı alanları',
                        'rows' => [
                            ['label' => 'PHP Sürümü', 'text' => 'Uygulamanın çalıştığı PHP sürümünü gösterir. Beklenmeyen eski sürüm form, PDF veya e-posta davranışını etkileyebilir.'],
                            ['label' => 'GD Eklentisi', 'text' => 'Görsel ve PDF tarafında kullanılan grafik işlemleri için gereklidir. Kapalıysa sunucu PHP eklentileri kontrol edilmelidir.'],
                            ['label' => 'Veritabanı', 'text' => 'MySQL bağlantısının kurulup kurulmadığını gösterir. Bağlantı yoksa giriş, rapor, bildirim ve veri girişi çalışmaz.'],
                            ['label' => 'Host Kısıtı', 'text' => 'ortam.php içindeki ALLOWED_HOSTS değerinin lokal belediye adresleriyle sınırlı olup olmadığını kontrol eder.'],
                            ['label' => '.htaccess / web.config', 'text' => 'Apache ve IIS tarafında hassas klasörlerin webden okunmasını engelleyen yapıların varlığını gösterir.'],
                            ['label' => 'Tek Aktif Oturum', 'text' => 'Kullanıcı başına tek oturum politikasının veritabanında desteklenip desteklenmediğini kontrol eder.'],
                        ],
                    ],
                    [
                        'type' => 'steps',
                        'title' => 'Panel bildirimi nasıl test edilir?',
                        'items' => [
                            'Süper Admin menüsünden Operasyon ekranını açın.',
                            'Panel bildirimi testi butonunu çalıştırın.',
                            'İşlem başarı mesajı verdiyse Bildirimler ekranına geçin.',
                            'Test bildiriminin listede göründüğünü ve sağ üst zil sayısını artırdığını doğrulayın.',
                            'Okundu işaretleyerek sadece kendi hesabınızdaki okunmamış sayının düştüğünü kontrol edin.',
                        ],
                    ],
                    [
                        'type' => 'steps',
                        'title' => 'SMTP test e-postası nasıl gönderilir?',
                        'items' => [
                            'Operasyon ekranında SMTP test alıcısını kontrol edin. Varsayılan alıcı giriş yapan Süper Admin e-postasıdır.',
                            'Gerekirse farklı bir kurum içi test adresi yazın.',
                            'Test e-postasını gönderin.',
                            'E-posta kuyruğu özetinde durumun Gönderildi, Hatalı, Gönderim Atlandı veya Bekliyor olarak kaydedildiğini kontrol edin.',
                            'Hatalı görünürse hata mesajını SMTP sunucu adresi, port, kullanıcı adı, şifre, güvenlik protokolü ve ağ erişimiyle birlikte değerlendirin.',
                        ],
                    ],
                    [
                        'type' => 'table',
                        'title' => 'E-posta kuyruğu durumları',
                        'rows' => [
                            ['label' => 'Gönderildi', 'text' => 'E-posta başarıyla gönderildi. Alıcı kutusunda spam/karantina kontrolü yapılabilir.'],
                            ['label' => 'Hatalı', 'text' => 'Gönderim denendi ancak SMTP veya ağ hatası nedeniyle başarısız oldu. Son hata mesajı incelenmelidir.'],
                            ['label' => 'Gönderim Atlandı', 'text' => 'SMTP kapalı veya yapılandırılmamış olduğu için e-posta gönderimi atlandı. Panel bildirimi bundan etkilenmez.'],
                            ['label' => 'Bekliyor', 'text' => 'Kuyrukta bekleyen kayıt var. Gönderim işleyicisi veya manuel test tekrar kontrol edilmelidir.'],
                        ],
                    ],
                    [
                        'type' => 'note',
                        'tone' => 'warning',
                        'text' => 'Operasyon ekranındaki kırmızı uyarılar yalnızca bilgi amaçlı değildir. Özellikle veritabanı, depolama yazılabilirliği, host kısıtı ve yedek klasörü hataları canlı kullanım öncesi çözülmelidir.',
                    ],
                ],
            ],
            [
                'id' => 'super-bildirimler',
                'title' => 'Bildirimler',
                'icon' => 'bi-bell-fill',
                'intro' => 'Süper Admin bildirimleri hem sağ üst zil menüsünden hem de Süper Admin menü grubundaki Bildirimler bağlantısından takip eder. Sistem olayları, operasyon testleri, yedek hataları ve güvenlik bildirimleri burada görünür.',
                'blocks' => [
                    [
                        'type' => 'table',
                        'title' => 'Bildirim ekranı nasıl okunur?',
                        'rows' => [
                            ['label' => 'Olay Filtresi', 'text' => 'Bildirimleri olay tipine göre daraltır. Yedek hatası, veri gönderimi, rol değişimi veya genel sistem bildirimi ayrıştırılabilir.'],
                            ['label' => 'Durum Filtresi', 'text' => 'Okunmuş ve okunmamış bildirimleri ayırır. Okundu yapmak bildirimi silmez.'],
                            ['label' => 'Git Butonu', 'text' => 'Bildirim ilgili ekranla bağlantılıysa doğrudan o kayıt veya listeye götürür.'],
                            ['label' => 'Sağ Üst Zil', 'text' => 'Son bildirimleri hızlı açar ve okunmamış sayısını gösterir. Liste düzenli olarak yenilenir.'],
                        ],
                    ],
                    [
                        'type' => 'bullets',
                        'title' => 'Süper Admin hangi bildirimleri bekler?',
                        'items' => [
                            'Yedekleme başarısız olduğunda kritik bildirim alır.',
                            'Operasyon ekranından panel bildirimi testi yaptığında kendi hesabına test bildirimi düşer.',
                            'Rol değişikliği, kullanıcı oluşturma ve şifre sıfırlama gibi güvenlik işlemleri denetim bildirimleriyle izlenir.',
                            'SMTP açıksa bazı önemli olaylar e-posta kuyruğuna da alınabilir; SMTP kapalıysa panel bildirimi yine çalışır.',
                        ],
                    ],
                    [
                        'type' => 'faq',
                        'title' => 'Bildirim soruları',
                        'items' => [
                            ['q' => 'Bildirim gelmiyor gibi görünüyorsa neye bakmalıyım?', 'a' => 'Önce Operasyon ekranından panel bildirimi testi gönderin. Test bildirimi geliyorsa panel kanalı çalışıyor demektir; sorun olayın oluşmaması veya filtrede kalması olabilir.'],
                            ['q' => 'SMTP başarısızsa panel bildirimi de başarısız olur mu?', 'a' => 'Hayır. Panel bildirimi veritabanına yazılır; SMTP yalnızca e-posta kanalıdır.'],
                            ['q' => 'Tümünü okundu işaretlemek kayıtları siler mi?', 'a' => 'Silmez. Yalnızca kendi kullanıcınız için okunmamış sayısını sıfırlar.'],
                        ],
                    ],
                ],
            ],
            [
                'id' => 'super-kullanici-oturum',
                'title' => 'Kullanıcı, Rol ve Oturum Yönetimi',
                'icon' => 'bi-people-fill',
                'intro' => 'Kullanıcılar ekranı hesapları yönetir; Oturumlar ekranı ise aktif girişleri gösterir. Sistem kişi başına tek aktif oturum mantığıyla çalışır: aynı kullanıcı tekrar giriş yaparsa eski oturum geçersiz olur.',
                'blocks' => [
                    [
                        'type' => 'table',
                        'title' => 'Roller ve erişim',
                        'rows' => [
                            ['label' => 'Süper Admin', 'text' => 'Kullanıcılar, operasyon, bildirimler, yedekler, denetim günlüğü, çöp kutusu ve oturumlar dahil tüm yönetim alanlarını görür.'],
                            ['label' => 'İklim Admin', 'text' => 'Eylem, faaliyet, KPI, veri onay, veri izleme, bildirim ve dışa aktar süreçlerini yönetir.'],
                            ['label' => 'Müdürlük Kullanıcısı', 'text' => 'Kendi müdürlüğüne tanımlanan eylem/KPI verilerini görür, veri gönderir ve kendi veri geçmişini takip eder.'],
                        ],
                    ],
                    [
                        'type' => 'steps',
                        'title' => 'Yeni kullanıcı oluşturma',
                        'items' => [
                            'Kullanıcılar ekranında Yeni Kullanıcı butonuna basın.',
                            'Ad soyad, kullanıcı adı ve geçerli e-posta adresini yazın.',
                            'Müdürlüğü seçin. Müdürlük seçimi veri görünürlüğünü etkiler.',
                            'Rolü belirleyin: Süper Admin, İklim Admin veya Müdürlük Kullanıcısı.',
                            'En az 8 karakterli şifre girin ve tekrarını yazın.',
                            'Aktif kutusu işaretliyse kullanıcı giriş yapabilir; pasifse hesap tanımlıdır ama kullanılamaz.',
                        ],
                    ],
                    [
                        'type' => 'bullets',
                        'title' => 'Oturum ekranında ne görülür?',
                        'items' => [
                            'Her aktif kullanıcı için tek satır beklenir.',
                            'Bu oturum etiketi sizin kullandığınız mevcut oturumu gösterir.',
                            'IP adresi ve tarayıcı bilgisi kurum içi erişim izini anlamaya yardım eder.',
                            'Son aktivite 15 dakikadan eskiyse oturum uyku modu gibi görünebilir; 8 saatten eski kayıtlar temizlenir.',
                            'Yeni giriş eski oturumu düşürür; eski sekme yeni istek attığında giriş ekranına yönlenir.',
                        ],
                    ],
                    [
                        'type' => 'do_dont',
                        'title' => 'Kullanıcı yönetimi kuralları',
                        'do_title' => 'Doğru uygulama',
                        'dont_title' => 'Kaçınılacak durum',
                        'do' => [
                            'Rol değişikliği yapmadan önce kullanıcının iş tanımını doğrulayın.',
                            'Pasifleştirme işlemini silme yerine tercih edin; geçici erişim kapatma için daha uygundur.',
                            'Kullanıcı silinecekse çöp kutusuna taşıma gerekçesini net yazın.',
                            'Şifre sıfırlama sonrası kullanıcıya kurum içi güvenli kanaldan bilgi verin.',
                        ],
                        'dont' => [
                            'Ortak kullanıcı hesabı oluşturmayın; denetim izi kişiye bağlı olmalıdır.',
                            'Müdürlük kullanıcısına gereksiz admin rolü vermeyin.',
                            'Son aktif Süper Admin hesabını pasifleştirmeyin.',
                            'Aynı kullanıcıyı birden fazla kişiye kullandırmayın; tek aktif oturum politikası bunu zaten zorlaştırır.',
                        ],
                    ],
                ],
            ],
            [
                'id' => 'super-yedek-guvenlik',
                'title' => 'Yedekler, Depolama ve Lokal Güvenlik',
                'icon' => 'bi-database-down',
                'intro' => 'Portal dışa açık olmayacak şekilde belediye lokalinde çalışır. Buna rağmen yedek dosyaları, kanıt ekleri ve hassas klasörler düzenli kontrol edilmelidir.',
                'blocks' => [
                    [
                        'type' => 'steps',
                        'title' => 'Manuel yedek alma',
                        'items' => [
                            'Süper Admin menüsünden Yedekler ekranını açın.',
                            'Son başarılı yedek tarihini kontrol edin.',
                            'Yedek Oluştur butonuna basın ve onay verin.',
                            'Durum başarılıysa dosya boyutu oluşur ve İndir butonu görünür.',
                            'Durum hatalıysa mesaj alanındaki mysqldump, izin veya veritabanı hatasını okuyun.',
                        ],
                    ],
                    [
                        'type' => 'table',
                        'title' => 'Depolama klasörleri',
                        'rows' => [
                            ['label' => 'kanit-dosyalari', 'text' => 'Müdürlüklerin yüklediği kanıt dosyaları burada tutulur. Yazılabilir olmalı, webden doğrudan listelenmemelidir.'],
                            ['label' => 'sistem-yedekleri', 'text' => 'SQL yedekleri burada oluşur. Sadece Süper Admin indirebilir; düzenli olarak kurumun güvenli yedek alanına alınmalıdır.'],
                            ['label' => 'gecici-dosyalar', 'text' => 'Geçici rapor, çıktı veya işlem dosyaları için kullanılır. Yazılabilirlik sorunu rapor üretimini etkileyebilir.'],
                        ],
                    ],
                    [
                        'type' => 'checklist',
                        'title' => 'Lokal güvenlik kontrolü',
                        'items' => [
                            'ortam.php içinde ALLOWED_HOSTS belediye lokal adresleriyle sınırlı olsun.',
                            'uygulama, veritabani, depolama ve ortam.php webden okunamasın.',
                            '.htaccess ve web.config dosyalarının canlı klasörde durduğunu doğrulayın.',
                            'Yedek dosyalarını aynı sunucuda tek kopya bırakmayın; kurum politikalarına göre ayrı güvenli alana taşıyın.',
                            'Sunucu kullanıcı izinlerinde PHP’nin yazması gereken klasörler dışında geniş yazma yetkisi vermeyin.',
                        ],
                    ],
                    [
                        'type' => 'faq',
                        'title' => 'Yedek hata örnekleri',
                        'items' => [
                            ['q' => 'Permission denied görürsem ne yapmalıyım?', 'a' => 'depolama/sistem-yedekleri klasörünün web sunucusu kullanıcısı tarafından yazılabilir olduğundan emin olun. Operasyon ekranındaki depolama kontrolü de bu problemi gösterir.'],
                            ['q' => 'mysqldump bulunamadıysa?', 'a' => 'XAMPP ortamında /Applications/XAMPP/xamppfiles/bin/mysqldump yolu tercih edilir. Yol yoksa sistem PATH veya XAMPP kurulumu kontrol edilmelidir.'],
                            ['q' => 'Yedek başarılı ama indirme görünmüyorsa?', 'a' => 'Kayıt status=success olmalı ve dosya depolama/sistem-yedekleri altında fiziksel olarak bulunmalıdır. Dosya boyutu 0 ise başarısız sayılır.'],
                        ],
                    ],
                ],
            ],
            [
                'id' => 'super-log-cop',
                'title' => 'Denetim Günlüğü ve Çöp Kutusu',
                'icon' => 'bi-shield-check',
                'intro' => 'Denetim Günlüğü kimin, ne zaman, hangi ekranda, hangi kayıt üzerinde işlem yaptığını gösterir. Çöp Kutusu ise silinen kayıtları geri almayı sağlar.',
                'blocks' => [
                    [
                        'type' => 'table',
                        'title' => 'Denetim günlüğü filtreleri',
                        'rows' => [
                            ['label' => 'Kullanıcı', 'text' => 'Belirli kişinin yaptığı işlemleri listeler.'],
                            ['label' => 'Kayıt Türü', 'text' => 'Eylem, KPI, kullanıcı, veri girişi gibi kayıt türlerini ayırır.'],
                            ['label' => 'İşlem', 'text' => 'Oluşturma, güncelleme, silme, doğrulama, giriş, başarısız giriş, rol değişimi gibi olayları filtreler.'],
                            ['label' => 'Tarih Aralığı', 'text' => 'İnceleme dönemini daraltır. Şüpheli işlem araştırmasında ilk kullanılacak filtredir.'],
                            ['label' => 'IP / Oturum', 'text' => 'Aynı cihaz veya aynı ağdan gelen işlemleri takip etmeye yardım eder.'],
                            ['label' => 'Arama', 'text' => 'Kullanıcı adı, sayfa/işlem adresi veya derin arama seçiliyse eski/yeni değer alanlarında arama yapar.'],
                        ],
                    ],
                    [
                        'type' => 'bullets',
                        'title' => 'Okunabilir log nasıl yorumlanır?',
                        'items' => [
                            'Liste ekranındaki özet alanı işlemi Türkçe ve kısa şekilde anlatır.',
                            'Detay açıldığında yalnızca değişen alanlar eski ve yeni değerleriyle görünür.',
                            'Rol değişikliği, şifre sıfırlama, kullanıcı pasifleştirme ve yedek hatası gibi olaylar özellikle izlenmelidir.',
                            'CSV çıktısı filtrelenen kayıtları kurum içi inceleme için indirir.',
                        ],
                    ],
                    [
                        'type' => 'steps',
                        'title' => 'Çöp kutusundan kayıt geri alma',
                        'items' => [
                            'Çöp Kutusu ekranını açın.',
                            'Eylemler, Faaliyetler, KPI kayıtları, Veri Girişleri veya Kullanıcılar sekmesini seçin.',
                            'Silinen kaydı, silen kullanıcıyı, tarih bilgisini ve sebebi kontrol edin.',
                            'Geri Al butonuyla kaydı eski görünür durumuna taşıyın.',
                            'Geri alma sonrası ilgili ekranda kaydın tekrar göründüğünü doğrulayın.',
                        ],
                    ],
                    [
                        'type' => 'note',
                        'tone' => 'tip',
                        'text' => 'Çöp kutusu geri alma kolaylığı sağlar; ancak veri bütünlüğü için eylem, faaliyet ve KPI kayıtlarını geri alırken ilişkili kayıtların da beklenen sırada durduğunu kontrol edin.',
                    ],
                ],
            ],
        ],
    ],
    'climate_admin' => [
        'kicker' => 'İklim Admin Rehberi',
        'headline' => 'SECAP Portalı İklim Admin Kullanım Kılavuzu',
        'intro' => 'Bu rehber SECAP eylem planı yapısını kuran, müdürlükleri eylem/KPI ile eşleştiren, veri girişlerini kontrol eden, bildirimleri takip eden ve rapor üreten İklim Admin kullanıcıları içindir.',
        'demo_note' => 'Eğitimde tek bir eylem açın, altına bir faaliyet ve iki KPI ekleyin, bir müdürlük kullanıcısı adına veri gönderip onay sürecini ve PDF/Excel çıktısını gösterin.',
        'summary' => [
            ['label' => 'Rol Amacı', 'value' => 'Eylem, faaliyet ve KPI yapısını yönetmek; müdürlüklerden gelen veriyi kalite kontrolünden geçirmek.'],
            ['label' => 'Günlük Kontrol', 'value' => 'Veri Onay, Bildirimler, Veri İzleme, Dışa Aktar, eylem/KPI bakım işleri ve eksik veri takibi.'],
            ['label' => 'Rapor Kuralı', 'value' => 'Kurumsal raporlarda güvenilir veri olarak onaylı kayıtlar esas alınır; bekleyen ve düzeltme istenen kayıtlar izleme amacıyla takip edilir.'],
            ['label' => 'Sorumluluk', 'value' => 'Müdürlüklerden gelen verinin açıklama, değer, yıl, birim ve kanıt bakımından anlamlı olduğundan emin olmak.'],
        ],
        'links' => [
            ['label' => 'Eylemler', 'href' => $B . '/yonetim/eylemler', 'class' => 'btn-outline-secondary', 'icon' => 'bi-lightning-charge'],
            ['label' => 'Veri Onay', 'href' => $B . '/yonetim/veri-onay', 'class' => 'btn-success', 'icon' => 'bi-check2-circle'],
            ['label' => 'Veri İzleme', 'href' => $B . '/yonetim/veri-izleme', 'class' => 'btn-outline-secondary', 'icon' => 'bi-graph-up'],
            ['label' => 'Bildirimler', 'href' => $B . '/bildirimler', 'class' => 'btn-outline-secondary', 'icon' => 'bi-bell'],
            ['label' => 'Dışa Aktar', 'href' => $B . '/yonetim/disa-aktar', 'class' => 'btn-outline-secondary', 'icon' => 'bi-download'],
        ],
        'sections' => [
            [
                'id' => 'admin-rol-gunluk',
                'title' => 'Rol, Menü ve Günlük Akış',
                'icon' => 'bi-compass',
                'intro' => 'İklim Admin, SECAP planının içerik sahibi gibi çalışır. Eylem/KPI tanımlarını hazırlar, müdürlükleri görevlendirir, verileri değerlendirir ve raporları üretir.',
                'blocks' => [
                    [
                        'type' => 'cards',
                        'title' => 'Ana ekranlar',
                        'items' => [
                            ['title' => 'Kontrol Merkezi', 'text' => 'Toplam eylem, faaliyet, KPI, veri girişi, onay bekleyen kayıtlar ve müdürlük bazlı ilerlemeyi gösterir.', 'icon' => 'bi-grid-1x2'],
                            ['title' => 'Eylemler', 'text' => 'SECAP eylemleri, faaliyetleri ve KPI kayıtları burada kurulur ve düzenlenir.', 'icon' => 'bi-lightning-charge'],
                            ['title' => 'Veri Onay', 'text' => 'Müdürlüklerden gelen veriler burada onaylanır, reddedilir veya düzeltmeye gönderilir.', 'icon' => 'bi-check2-circle'],
                            ['title' => 'Veri İzleme', 'text' => 'Eylem/KPI bazında doluluk, kanıt, durum ve son veri bilgileri izlenir.', 'icon' => 'bi-graph-up'],
                            ['title' => 'Dışa Aktar', 'text' => 'Excel ve PDF raporları yıl, müdürlük ve onay durumu filtresiyle üretilir.', 'icon' => 'bi-download'],
                            ['title' => 'Bildirimler', 'text' => 'Yeni veri, yeniden gönderim ve süreç uyarıları buradan takip edilir.', 'icon' => 'bi-bell'],
                        ],
                    ],
                    [
                        'type' => 'checklist',
                        'title' => 'Günlük çalışma kontrolü',
                        'items' => [
                            'Bildirimleri açıp yeni gönderilen veya tekrar gönderilen verileri kontrol edin.',
                            'Veri Onay ekranında Onay Bekliyor ve Düzeltme İstendi kayıtlarını süzün.',
                            'Kanıt zorunlu KPI kayıtlarında dosya sayısını ve dosya içeriğini kontrol edin.',
                            'Veri İzleme ekranında yıl bazında eksik KPI veya müdürlük var mı bakın.',
                            'Rapor almadan önce filtrelerin yıl, müdürlük ve onay durumuna göre doğru seçildiğini doğrulayın.',
                        ],
                    ],
                    [
                        'type' => 'note',
                        'tone' => 'tip',
                        'text' => 'İklim Admin için en sağlıklı rutin: önce bildirim, sonra veri onay, ardından izleme ve rapor. Eylem/KPI değişikliği ise ihtiyaç oldukça yapılmalıdır.',
                    ],
                ],
            ],
            [
                'id' => 'admin-eylem',
                'title' => 'Eylem Yönetimi',
                'icon' => 'bi-lightning-charge',
                'intro' => 'Eylem, SECAP planındaki ana iş başlığıdır. Kod, başlık, sorumlu müdürlük, katkı veren müdürlükler, dönem, kategori ve durum bilgileri üzerinden yönetilir.',
                'blocks' => [
                    [
                        'type' => 'table',
                        'title' => 'Eylem formu alanları',
                        'rows' => [
                            ['label' => 'Eylem Kodu', 'text' => 'Raporlarda ana referanstır. Benzersiz olmalıdır ve kurumun plan kodlama mantığıyla uyumlu yazılmalıdır.'],
                            ['label' => 'Eylem Başlığı', 'text' => 'Kısa ama anlaşılır olmalıdır. Müdürlük kullanıcıları bu başlığa göre hangi işe veri gireceğini anlar.'],
                            ['label' => 'Proje Açıklaması', 'text' => 'Eylemin kapsamını ve beklenen çıktısını anlatır. Rapor ve eğitimlerde kullanıcıya bağlam verir.'],
                            ['label' => 'Performans Göstergeleri', 'text' => 'Eylemin hangi göstergelerle takip edileceğini özetler. Ayrıntılı ölçüm KPI kayıtlarında yapılır.'],
                            ['label' => 'Birincil Sorumlu Müdürlük', 'text' => 'Eylemin ana sahibidir. Kontrol Merkezi ve rapor özetlerinde eylem bu müdürlük altında görünür.'],
                            ['label' => 'Ek Sorumlu Müdürlükler', 'text' => 'Veri katkısı yapacak diğer müdürlükleri belirler. Bu müdürlükler de ilgili eylemi görebilir.'],
                            ['label' => 'Başlangıç / Bitiş Yılı', 'text' => 'Eylemin plan dönemini gösterir. Bitiş yılı boş bırakılırsa açık uçlu takip edilebilir.'],
                            ['label' => 'Durum', 'text' => 'Planlandı, Devam Ediyor, Tamamlandı veya İptal Edildi olarak seçilir. İptal edilen eylemler ana izleme dışında kalır.'],
                        ],
                    ],
                    [
                        'type' => 'steps',
                        'title' => 'Yeni eylem oluşturma',
                        'items' => [
                            'Eylemler ekranından Yeni Eylem butonuna basın.',
                            'Eylem kodunu ve başlığını girin.',
                            'Birincil sorumlu müdürlüğü seçin.',
                            'Veri girecek başka müdürlükler varsa ek sorumlu olarak işaretleyin.',
                            'Başlangıç yılı, varsa bitiş yılı, kategori ve durum alanlarını tamamlayın.',
                            'Kaydedin; yeni atanan müdürlüklere bildirim gider.',
                        ],
                    ],
                    [
                        'type' => 'do_dont',
                        'title' => 'Eylem tanımlarken kalite ölçütü',
                        'do_title' => 'İyi tanım',
                        'dont_title' => 'Sorun çıkaran tanım',
                        'do' => [
                            'Kod ve başlık birbirini tamamlar.',
                            'Sorumlu müdürlük doğru seçilir.',
                            'Eylemin dönemi gerçekçi girilir.',
                            'Veri katkısı yapacak müdürlükler baştan eklenir.',
                        ],
                        'dont' => [
                            'Aynı kodla ikinci eylem açmaya çalışmak.',
                            'Her müdürlüğü gereksiz yere ek sorumlu yapmak.',
                            'Başlığı çok genel bırakmak.',
                            'Tamamlanmış eylemi hala Devam Ediyor durumunda bırakmak.',
                        ],
                    ],
                ],
            ],
            [
                'id' => 'admin-faaliyet-kpi',
                'title' => 'Faaliyet ve KPI Kurulumu',
                'icon' => 'bi-bar-chart',
                'intro' => 'Faaliyet eylemin alt iş kırılımıdır; KPI ise müdürlüklerin yıl bazında veri gireceği ölçülebilir göstergedir. Rapor kalitesi KPI sözlüğünün ne kadar net kurulduğuna bağlıdır.',
                'blocks' => [
                    [
                        'type' => 'table',
                        'title' => 'Faaliyet alanları',
                        'rows' => [
                            ['label' => 'Faaliyet Başlığı', 'text' => 'Eylemin altındaki somut iş veya alt başlık. Müdürlük kullanıcıları hangi iş için veri istediğinizi buradan anlar.'],
                            ['label' => 'Gerçekleştirilecek Eylemler', 'text' => 'Alt adımlar, uygulama notları veya iş kapsamı yazılır.'],
                            ['label' => 'Sorumlu Müdürlük', 'text' => 'Faaliyetin sahibi olan müdürlüktür.'],
                            ['label' => 'Sıra No', 'text' => 'Aynı eylem altındaki faaliyetlerin listelenme sırasını belirler.'],
                        ],
                    ],
                    [
                        'type' => 'table',
                        'title' => 'KPI alanları',
                        'rows' => [
                            ['label' => 'KPI Adı', 'text' => 'Müdürlüğün veri gireceği göstergedir. Örneğin "Yıllık elektrik tüketimi" veya "Düzenlenen eğitim sayısı".'],
                            ['label' => 'Birim', 'text' => 'Değerin anlamını belirler: adet, kWh, ton CO2e, m2, kişi, km gibi.'],
                            ['label' => 'Bağlı Faaliyet', 'text' => 'KPI doğrudan eyleme veya eylem altındaki bir faaliyete bağlanabilir.'],
                            ['label' => 'Açıklama', 'text' => 'KPI’nın neyi ölçtüğünü kullanıcıya anlatır.'],
                            ['label' => 'Hedef Değer / Hedef Açıklaması', 'text' => 'Plan dönemindeki beklenen sonucu gösterir. Hedef açıklaması sayısal hedefin ne anlama geldiğini netleştirir.'],
                            ['label' => 'Başlangıç Değeri / Referans Yılı', 'text' => 'İlerleme oranı ve karşılaştırma için başlangıç seviyesini belirler.'],
                            ['label' => 'Kümülatif KPI', 'text' => 'Değer yıllar içinde birikerek ilerliyorsa işaretlenir. Yıllık ayrı değer giriliyorsa işaretlenmez.'],
                            ['label' => 'Ölçüm Yöntemi', 'text' => 'Verinin nasıl hesaplanacağını ve nasıl doğrulanacağını açıklar.'],
                            ['label' => 'Veri Kaynağı', 'text' => 'Fatura, sayaç, kurum yazısı, saha formu, ihale hakedişi, faaliyet raporu gibi kaynağı belirtir.'],
                            ['label' => 'Formül', 'text' => 'Gerekirse hesaplama yöntemi yazılır. Örneğin toplam tüketim = fatura değerleri toplamı.'],
                            ['label' => 'Kanıt Dosyası Zorunlu', 'text' => 'İşaretliyse müdürlük kullanıcısı dosya yüklemeden veriyi onaya gönderemez.'],
                        ],
                    ],
                    [
                        'type' => 'steps',
                        'title' => 'KPI kurulum sırası',
                        'items' => [
                            'Önce eylemi oluşturun veya mevcut eylemi açın.',
                            'Eylem alt kırılımı gerekiyorsa faaliyet oluşturun.',
                            'KPI Ekle butonuyla ölçülebilir göstergeyi tanımlayın.',
                            'Birim, hedef, başlangıç ve veri sözlüğü alanlarını doldurun.',
                            'Kanıt zorunluluğuna karar verin.',
                            'Kaydedin; ilgili müdürlükler yeni KPI bildirimi alır.',
                        ],
                    ],
                    [
                        'type' => 'note',
                        'tone' => 'warning',
                        'text' => 'KPI adı ve birimi net değilse müdürlük yanlış değer girebilir. Form açılmadan önce KPI sözlüğünü mümkün olduğunca açıklayıcı yazmak veri onay yükünü azaltır.',
                    ],
                ],
            ],
            [
                'id' => 'admin-veri-onay',
                'title' => 'Veri Onay Süreci',
                'icon' => 'bi-check2-circle',
                'intro' => 'Veri Onay ekranı müdürlüklerden gelen kayıtların kalite kontrol noktasıdır. Burada karar yalnızca sayıya göre değil, açıklama, dönem, birim, kanıt ve KPI sözlüğüyle uyuma göre verilir.',
                'blocks' => [
                    [
                        'type' => 'table',
                        'title' => 'Onay durumları',
                        'rows' => [
                            ['label' => 'Onay Bekliyor', 'text' => 'Müdürlük kaydı göndermiştir. İklim Admin kontrol edip onay, düzeltme veya red kararı verir.'],
                            ['label' => 'Düzeltme İstendi', 'text' => 'Kayıt müdürlüğe yorumla geri gönderilmiştir. Kullanıcı aynı kayıt üzerinde düzenleme yapıp tekrar onaya gönderebilir.'],
                            ['label' => 'Onaylı', 'text' => 'Kayıt güvenilir veri olarak kabul edilmiştir. Müdürlük kullanıcısı bu kaydı doğrudan değiştiremez.'],
                            ['label' => 'Reddedildi', 'text' => 'Kayıt uygun bulunmamıştır. Gerekçe yorumda belirtilmelidir. Müdürlük doğru veriyle yeniden süreç başlatabilir.'],
                        ],
                    ],
                    [
                        'type' => 'checklist',
                        'title' => 'Onay vermeden önce kontrol edin',
                        'items' => [
                            'Yıl doğru mu? Veri gerçekten seçilen raporlama yılına mı ait?',
                            'KPI birimiyle girilen değer uyumlu mu?',
                            'Açıklama veri kaynağını, dönemi ve hesaplama mantığını anlatıyor mu?',
                            'Kanıt zorunluysa en az bir dosya var mı ve dosya açılabiliyor mu?',
                            'Kanıt dosyasındaki sayı ile formdaki değer birbirini destekliyor mu?',
                            'Aynı yıl ve KPI için daha önce onaylı veri varsa yeni kayıt onu bilerek mi güncelliyor?',
                            'Değer olağan dışı yüksek veya düşükse müdürlük açıklama yazmış mı?',
                        ],
                    ],
                    [
                        'type' => 'steps',
                        'title' => 'Düzeltme isteme',
                        'items' => [
                            'Veri Onay ekranında ilgili kaydı bulun.',
                            'Eksik veya tutarsız noktayı net biçimde belirleyin.',
                            'Düzeltme yorumu alanına neyin düzeltilmesi gerektiğini yazın.',
                            'Düzeltme İste kararını verin.',
                            'Müdürlük kullanıcısı bildirim alır ve kaydı düzenleyip yeniden gönderir.',
                        ],
                    ],
                    [
                        'type' => 'do_dont',
                        'title' => 'Karar verirken',
                        'do_title' => 'Uygun karar',
                        'dont_title' => 'Belirsiz bırakma',
                        'do' => [
                            'Küçük eksikte düzeltme isteyin; tamamen yanlış kayıt için red verin.',
                            'Onay yorumlarını kısa ve anlaşılır yazın.',
                            'Kanıt dosyasını açmadan onay vermeyin.',
                            'KPI sözlüğü yetersizse KPI tanımını da güncelleyin.',
                        ],
                        'dont' => [
                            'Müdürlüğün anlayamayacağı kadar genel düzeltme yorumu yazmayın.',
                            'Birimi belirsiz değeri onaylamayın.',
                            'Yanlış yıla ait veriyi doğru kabul etmeyin.',
                            'Onaylı kayıtta hata varsa müdürlüğü formu zorlamaya yönlendirmeyin; önce onayı kaldırın.',
                        ],
                    ],
                ],
            ],
            [
                'id' => 'admin-izleme-export',
                'title' => 'Veri İzleme ve Dışa Aktar',
                'icon' => 'bi-download',
                'intro' => 'Veri İzleme sahadaki doluluk ve durum takibi içindir. Dışa Aktar ekranı ise toplantı, kurum içi paylaşım ve arşiv için Excel/PDF raporu üretir.',
                'blocks' => [
                    [
                        'type' => 'table',
                        'title' => 'İzleme ve rapor farkı',
                        'rows' => [
                            ['label' => 'Veri İzleme', 'text' => 'Eylem/KPI bazında son veri, onay durumu, kanıt sayısı ve müdürlük ilerlemesini ekranda incelemek için kullanılır.'],
                            ['label' => 'Excel Raporu', 'text' => 'Genel Özet, Eylemler, KPI Detay, Veri Girişleri ve Faaliyetler olmak üzere 5 sayfalık detaylı çıktı üretir.'],
                            ['label' => 'PDF Raporu', 'text' => 'Hızlı paylaşım ve yazdırma için kurumsal başlıklı, sayfa numaralı, sade veri listesi üretir.'],
                            ['label' => 'Boş Sonuç', 'text' => 'Filtre sonucu kayıt yoksa rapor yine okunabilir şekilde boş sonuç mesajıyla oluşur.'],
                        ],
                    ],
                    [
                        'type' => 'steps',
                        'title' => 'Doğru rapor alma',
                        'items' => [
                            'Dışa Aktar ekranını açın.',
                            'Yılı seçin.',
                            'Gerekirse müdürlük filtresiyle raporu daraltın.',
                            'Onay durumunu seçin. Kurumsal kesin rapor için Onaylı tercih edilir.',
                            'Detaylı analiz için Excel, hızlı paylaşım için PDF indirin.',
                            'Raporu açıp Türkçe karakterlerin, filtre özetinin ve kolonların doğru göründüğünü kontrol edin.',
                        ],
                    ],
                    [
                        'type' => 'bullets',
                        'title' => 'Rapor yorumlama',
                        'items' => [
                            'Doluluk oranı, o yıl girilen veri sayısının beklenen KPI sayısına göre durumunu gösterir.',
                            'Onaylı sayısı rapora güvenilir şekilde girebilecek kayıtları gösterir.',
                            'Bekleyen kayıtlar raporda takip edilebilir ama kalite kontrol tamamlanmamıştır.',
                            'Kanıt sayısı 0 olan kayıtlar özellikle kanıt zorunlu KPI için incelenmelidir.',
                        ],
                    ],
                    [
                        'type' => 'faq',
                        'title' => 'Rapor soruları',
                        'items' => [
                            ['q' => 'Toplantı için hangi raporu almalıyım?', 'a' => 'Genel değerlendirme ve tablo ihtiyacı varsa Excel, hızlı çıktı veya üst yazı eki için PDF daha uygundur.'],
                            ['q' => 'Sadece bir müdürlüğü göstermek istiyorum.', 'a' => 'Müdürlük filtresini seçip raporu öyle indirin.'],
                            ['q' => 'Rapor boş geldi.', 'a' => 'Seçilen yıl, müdürlük ve onay durumu kombinasyonunda kayıt olmayabilir. Önce Tümü veya farklı yıl ile deneyin.'],
                        ],
                    ],
                ],
            ],
            [
                'id' => 'admin-bildirim-kalite',
                'title' => 'Bildirimler ve Veri Kalitesi',
                'icon' => 'bi-bell',
                'intro' => 'Bildirimler İklim Admin’in günlük iş kuyruğu gibi kullanılmalıdır. Veri kalitesi ise müdürlüklerin tekrar tekrar düzeltmeye düşmemesi için KPI sözlüğü ve onay yorumlarıyla yönetilir.',
                'blocks' => [
                    [
                        'type' => 'bullets',
                        'title' => 'İklim Admin hangi bildirimleri alır?',
                        'items' => [
                            'Müdürlük yeni veri gönderdiğinde bildirim gelir.',
                            'Düzeltme sonrası yeniden gönderimler ayrı bildirim olarak görünür.',
                            'Yeni eylem, faaliyet veya KPI tanımlarından etkilenen müdürlüklerde atama bildirimleri oluşur.',
                            'Okundu işaretlemek bildirimi silmez; yalnızca okunmamış sayınızı düşürür.',
                        ],
                    ],
                    [
                        'type' => 'checklist',
                        'title' => 'Veri kalitesi için iyi pratikler',
                        'items' => [
                            'KPI adlarını müdürlüklerin kullandığı dile yakın yazın.',
                            'Birimleri standartlaştırın: kWh, ton CO2e, adet, kişi gibi.',
                            'Kanıt zorunlu KPI’larda kabul edilebilir dosya örneklerini eğitimde gösterin.',
                            'Düzeltme yorumlarında tek cümleyle net aksiyon yazın.',
                            'Onaylı kayıtlarda hata fark edilirse önce onayı kaldırıp süreci tekrar açın.',
                        ],
                    ],
                    [
                        'type' => 'faq',
                        'title' => 'İklim Admin sık sorulanlar',
                        'items' => [
                            ['q' => 'Müdürlük eylemi göremiyor, neyi kontrol etmeliyim?', 'a' => 'Eylemin birincil sorumlu veya ek sorumlu müdürlük listesinde ilgili müdürlüğün olup olmadığını kontrol edin.'],
                            ['q' => 'Kanıtı olmayan veriyi onaylayabilir miyim?', 'a' => 'KPI kanıt zorunluysa onaylamayın. Kanıt zorunlu değilse açıklama, değer ve kaynak tutarlılığına göre karar verin.'],
                            ['q' => 'Aynı KPI için yanlış yıl seçilmişse?', 'a' => 'Düzeltme isteyin veya kayıt onaylıysa onayı kaldırıp doğru yıl/değerle yeniden gönderilmesini sağlayın.'],
                            ['q' => 'KPI tanımı yanlış kurulmuşsa?', 'a' => 'KPI formunu düzenleyin. Sonrasında müdürlüklere neyin değiştiğini bildirin.'],
                        ],
                    ],
                ],
            ],
        ],
    ],
    'department_user' => [
        'kicker' => 'Müdürlük Kullanıcı Rehberi',
        'headline' => 'SECAP Portalı Müdürlük Kullanım Kılavuzu',
        'intro' => 'Bu rehber müdürlük kullanıcılarının kendilerine tanımlı eylemleri görmesi, KPI verisi göndermesi, kanıt dosyası eklemesi, bildirimleri takip etmesi ve düzeltme istenen kayıtları tamamlaması için hazırlanmıştır.',
        'demo_note' => 'Eğitimde Müdürlük Eylemleri ekranından bir KPI seçin, veri formunu açın, değer/açıklama/kanıt ekleyip Onaya Gönder butonuyla süreci tamamlayın. Ardından Veri Girişi Geçmişi ve Bildirimler ekranlarında durum takibi gösterin.',
        'summary' => [
            ['label' => 'Rol Amacı', 'value' => 'Müdürlüğe tanımlı KPI verilerini doğru değer, açıklama ve kanıtla onaya göndermek.'],
            ['label' => 'Günlük Kontrol', 'value' => 'Bildirimler, Müdürlük Eylemleri, Yeni Veri Kaydı, Veri Girişi Geçmişi ve düzeltme istenen kayıtlar.'],
            ['label' => 'Gönderim Kuralı', 'value' => 'Portalda hazır veri onaya gönderilir; açıklama zorunludur, KPI kanıt istiyorsa dosya da zorunludur.'],
            ['label' => 'Sorumluluk', 'value' => 'Verinin yılını, değerini, birimini, kaynağını ve kanıtını doğru girmek.'],
        ],
        'links' => [
            ['label' => 'Müdürlük Eylemleri', 'href' => $B . '/mudurluk/eylemlerim', 'class' => 'btn-outline-secondary', 'icon' => 'bi-lightning-charge'],
            ['label' => 'Yeni Veri Kaydı', 'href' => $B . '/mudurluk/veri-girisi', 'class' => 'btn-success', 'icon' => 'bi-plus-circle'],
            ['label' => 'Veri Girişi Geçmişi', 'href' => $B . '/mudurluk/veri-gecmisim', 'class' => 'btn-outline-secondary', 'icon' => 'bi-clock-history'],
            ['label' => 'Bildirimler', 'href' => $B . '/bildirimler', 'class' => 'btn-outline-secondary', 'icon' => 'bi-bell'],
        ],
        'sections' => [
            [
                'id' => 'mudurluk-rol-gunluk',
                'title' => 'Rol, Menü ve Günlük Kullanım',
                'icon' => 'bi-compass',
                'intro' => 'Müdürlük kullanıcısı yalnızca kendi müdürlüğüne tanımlı eylem ve KPI kayıtlarını görür. Ana görevi ilgili yılın verisini doğru kaynakla onaya göndermek ve sonucunu takip etmektir.',
                'blocks' => [
                    [
                        'type' => 'cards',
                        'title' => 'Müdürlük ekranları',
                        'items' => [
                            ['title' => 'Kontrol Merkezi', 'text' => 'Müdürlüğünüze ait eylem, KPI ve veri giriş sayılarının özetini gösterir.', 'icon' => 'bi-grid-1x2'],
                            ['title' => 'Müdürlük Eylemleri', 'text' => 'Size tanımlanan eylemleri, KPI kayıtlarını, kanıt durumunu ve veri giriş butonlarını listeler.', 'icon' => 'bi-lightning-charge'],
                            ['title' => 'Yeni Veri Kaydı', 'text' => 'KPI seçip yıl, değer, açıklama ve kanıt dosyasıyla kaydı onaya gönderdiğiniz formdur.', 'icon' => 'bi-plus-circle'],
                            ['title' => 'Veri Girişi Geçmişi', 'text' => 'Gönderdiğiniz kayıtların durumunu, yorumunu ve düzenlenebilir olup olmadığını gösterir.', 'icon' => 'bi-clock-history'],
                            ['title' => 'Bildirimler', 'text' => 'Yeni atamalar, düzeltme talepleri, onay ve red kararlarını takip edersiniz.', 'icon' => 'bi-bell'],
                        ],
                    ],
                    [
                        'type' => 'checklist',
                        'title' => 'Her girişte kontrol edin',
                        'items' => [
                            'Sağ üst bildirim zilinde yeni bildirim var mı bakın.',
                            'Düzeltme istenen kayıt varsa önce onu tamamlayın.',
                            'Müdürlük Eylemleri ekranında bu yıl veri bekleyen KPI var mı kontrol edin.',
                            'Veri girmeden önce kaynak belgenin doğru yıla ait olduğundan emin olun.',
                            'Onaya gönderim sonrası Veri Girişi Geçmişi ekranından durumunu takip edin.',
                        ],
                    ],
                    [
                        'type' => 'note',
                        'tone' => 'tip',
                        'text' => 'Bir eylemi veya KPI’yı göremiyorsanız bu genelde yetki atamasıyla ilgilidir. İklim Admin’in müdürlüğünüzü eyleme birincil veya ek sorumlu olarak eklemesi gerekir.',
                    ],
                ],
            ],
            [
                'id' => 'mudurluk-eylemlerim',
                'title' => 'Müdürlük Eylemleri Ekranı',
                'icon' => 'bi-lightning-charge',
                'intro' => 'Müdürlük Eylemleri ekranı sizin iş listenizdir. Burada hangi eylem ve KPI için veri beklendiğini, mevcut kaydın durumunu ve kanıt bilgisini görürsünüz.',
                'blocks' => [
                    [
                        'type' => 'table',
                        'title' => 'Listede gördüğünüz bilgiler',
                        'rows' => [
                            ['label' => 'Eylem Kodu ve Başlığı', 'text' => 'Verinin hangi SECAP eylemine ait olduğunu gösterir.'],
                            ['label' => 'KPI Adı', 'text' => 'Hangi ölçüm değerinin girileceğini belirtir.'],
                            ['label' => 'Birim', 'text' => 'Değerin hangi ölçüyle yazılacağını gösterir. Değer alanına yalnızca sayı girilir; birim ayrı görünür.'],
                            ['label' => 'Kanıt Durumu', 'text' => 'KPI kanıt zorunluysa dosya eklemeden gönderim tamamlanmaz.'],
                            ['label' => 'Durum Rozeti', 'text' => 'Onay Bekliyor, Düzeltme İstendi, Onaylı veya Reddedildi bilgisini gösterir.'],
                            ['label' => 'Gir / Düzenle', 'text' => 'Kayıt yoksa veri formunu açar. Onaylı olmayan mevcut kayıtta düzenleme yapmanızı sağlar.'],
                        ],
                    ],
                    [
                        'type' => 'bullets',
                        'title' => 'Butonlar ne zaman değişir?',
                        'items' => [
                            'Kayıt yoksa Gir butonu görünür.',
                            'Onay Bekliyor, Düzeltme İstendi veya Reddedildi durumundaki kayıtlar düzenlenebilir.',
                            'Onaylı kayıtlar kilitlidir; değişiklik gerekiyorsa İklim Admin ile iletişime geçin.',
                            'KPI aktif değilse veya müdürlüğünüze atanmadıysa listede görünmez.',
                        ],
                    ],
                ],
            ],
            [
                'id' => 'mudurluk-veri-formu',
                'title' => 'Veri Girişi Formu',
                'icon' => 'bi-plus-circle',
                'intro' => 'Veri formu tek aşamalıdır: kayıt hazır olduğunda Onaya Gönder butonuyla İklim Admin kontrolüne gider. Formda ayrı kayıt bekletme akışı yoktur.',
                'blocks' => [
                    [
                        'type' => 'steps',
                        'title' => 'Veri gönderme adımları',
                        'items' => [
                            'Yeni Veri Kaydı ekranını açın veya Müdürlük Eylemleri ekranından ilgili KPI için Gir/Düzenle butonuna basın.',
                            'KPI seçimini kontrol edin. Sağdaki bilgi panelinde eylem, müdürlük, hedef, başlangıç, veri kaynağı ve kanıt zorunluluğu görünür.',
                            'Yıl alanına verinin ait olduğu yılı yazın.',
                            'Değer alanına sadece sayısal değeri girin. Birim sistemde KPI’dan gelir.',
                            'Açıklama alanına veri kaynağını, dönemi ve hesaplama mantığını yazın.',
                            'KPI kanıt zorunluysa en az bir dosya ekleyin. Zorunlu değilse yine de destekleyici dosya eklemek kaliteyi artırır.',
                            'Onaya Gönder butonuna basın.',
                            'Gönderimden sonra Veri Girişi Geçmişi ekranında durumu takip edin.',
                        ],
                    ],
                    [
                        'type' => 'table',
                        'title' => 'Form alanları',
                        'rows' => [
                            ['label' => 'KPI', 'text' => 'Raporlanacak gösterge. Yanlış KPI seçimi raporun yanlış eyleme bağlanmasına neden olur.'],
                            ['label' => 'Yıl', 'text' => 'Verinin ait olduğu raporlama yılı. Fatura, faaliyet raporu veya ölçüm belgesindeki yılla aynı olmalıdır.'],
                            ['label' => 'Değer', 'text' => 'Sayısal ölçüm değeridir. Nokta/virgül kullanımına dikkat edin; metin veya birim yazmayın.'],
                            ['label' => 'Açıklama', 'text' => 'Zorunludur. Verinin nereden alındığını, hangi dönemi kapsadığını ve nasıl hesaplandığını anlatır.'],
                            ['label' => 'Kanıt Dosyası', 'text' => 'PDF, Excel, CSV, görsel veya Word dosyası olabilir. KPI ayarında zorunluysa gönderim için şarttır.'],
                            ['label' => 'Mevcut Kanıtlar', 'text' => 'Daha önce yüklenen dosyalar formda listelenir; kayıt onaylı değilse yeni dosya ekleyebilirsiniz.'],
                        ],
                    ],
                    [
                        'type' => 'cards',
                        'title' => 'İyi açıklama örnekleri',
                        'items' => [
                            ['title' => 'Enerji Tüketimi', 'text' => '2025 yılı hizmet binası elektrik faturalarının Ocak-Aralık toplamıdır. Kaynak: EDAŞ fatura dökümü.', 'icon' => 'bi-lightning'],
                            ['title' => 'Eğitim Sayısı', 'text' => '2025 yılında müdürlük tarafından düzenlenen iklim farkındalık eğitimlerinin toplam oturum sayısıdır. Kaynak: katılım listeleri.', 'icon' => 'bi-mortarboard'],
                            ['title' => 'Atık Miktarı', 'text' => '2025 yılı lisanslı bertaraf firmasına teslim edilen atık miktarıdır. Kaynak: teslim tutanakları.', 'icon' => 'bi-recycle'],
                            ['title' => 'Yeşil Alan', 'text' => '2025 yılında tamamlanan park düzenlemeleriyle eklenen net yeşil alan toplamıdır. Kaynak: hakediş ve saha ölçüm raporu.', 'icon' => 'bi-tree'],
                        ],
                    ],
                    [
                        'type' => 'note',
                        'tone' => 'warning',
                        'text' => 'Açıklama alanına sadece "girildi", "tamamlandı" veya "ekte" yazmak yeterli değildir. İklim Admin’in değeri doğrulayabilmesi için kaynak, dönem ve hesaplama bilgisi açık olmalıdır.',
                    ],
                ],
            ],
            [
                'id' => 'mudurluk-kanit',
                'title' => 'Kanıt Dosyaları ve Veri Kalitesi',
                'icon' => 'bi-paperclip',
                'intro' => 'Kanıt dosyası, gönderdiğiniz değerin doğrulanmasını sağlar. Kanıt zorunlu KPI’larda sistem dosya olmadan gönderime izin vermez; zorunlu olmayan KPI’larda da kanıt eklemek önerilir.',
                'blocks' => [
                    [
                        'type' => 'table',
                        'title' => 'Kabul edilen dosya türleri',
                        'rows' => [
                            ['label' => 'PDF', 'text' => 'Resmi yazı, rapor, fatura dökümü, tutanak veya imzalı belge için uygundur.'],
                            ['label' => 'Excel / CSV', 'text' => 'Sayaç, envanter, aylık toplam, tablo veya hesaplama listeleri için uygundur.'],
                            ['label' => 'Word', 'text' => 'Açıklama metni, rapor taslağı veya yazılı değerlendirme için kullanılabilir.'],
                            ['label' => 'Görsel', 'text' => 'Saha fotoğrafı veya görsel kanıt için kullanılabilir; ancak sayısal veriyi açıklama ve belgeyle desteklemek gerekir.'],
                        ],
                    ],
                    [
                        'type' => 'checklist',
                        'title' => 'Dosya eklemeden önce',
                        'items' => [
                            'Dosya doğru yıla ait mi?',
                            'Dosyada formdaki değeri destekleyen sayı veya açıklama var mı?',
                            'Dosya adı anlaşılır mı? Örneğin enerji_tuketimi_2025.pdf gibi.',
                            'Dosya kişisel veri veya paylaşılmaması gereken hassas bilgi içeriyor mu?',
                            'Birden fazla dosya gerekiyorsa hepsini aynı gönderimde eklediniz mi?',
                        ],
                    ],
                    [
                        'type' => 'do_dont',
                        'title' => 'Kanıt kalitesi',
                        'do_title' => 'Uygun',
                        'dont_title' => 'Uygun değil',
                        'do' => [
                            'Yıl ve değer görülebilen belge eklemek.',
                            'Excel tablosunda toplam satırını anlaşılır bırakmak.',
                            'Açıklamada dosyanın hangi bölümüne bakılacağını belirtmek.',
                            'Birden fazla kaynağı tek KPI için gerekiyorsa birlikte yüklemek.',
                        ],
                        'dont' => [
                            'Boş veya ilgisiz dosya yüklemek.',
                            'Sadece ekran görüntüsüyle karmaşık hesaplamayı açıklamasız bırakmak.',
                            'Yanlış yıla ait faturayı kanıt diye eklemek.',
                            'Kişisel veri içeren dosyayı gereksiz paylaşmak.',
                        ],
                    ],
                ],
            ],
            [
                'id' => 'mudurluk-durum-duzeltme',
                'title' => 'Durumlar, Düzeltme ve Veri Girişi Geçmişi',
                'icon' => 'bi-arrow-repeat',
                'intro' => 'Gönderdiğiniz veri İklim Admin tarafından incelendikçe durum değiştirir. Veri Girişi Geçmişi ekranı bu süreci takip ettiğiniz ana yerdir.',
                'blocks' => [
                    [
                        'type' => 'table',
                        'title' => 'Durumlar ne anlama gelir?',
                        'rows' => [
                            ['label' => 'Onay Bekliyor', 'text' => 'Kayıt gönderildi, İklim Admin kontrolü bekliyor. Gerekirse onaylanmadan önce düzenleme yapılabilir.'],
                            ['label' => 'Düzeltme İstendi', 'text' => 'İklim Admin eksik veya hatalı noktayı yorumla belirtmiştir. Kaydı açıp düzeltin ve tekrar onaya gönderin.'],
                            ['label' => 'Onaylı', 'text' => 'Kayıt kabul edilmiştir ve raporlar için güvenilir veri kabul edilir. Bu durumda form kilitlenir.'],
                            ['label' => 'Reddedildi', 'text' => 'Kayıt uygun bulunmamıştır. Red gerekçesini okuyup doğru veriyle yeniden gönderim yapmanız gerekir.'],
                        ],
                    ],
                    [
                        'type' => 'steps',
                        'title' => 'Düzeltme istenirse ne yapılır?',
                        'items' => [
                            'Bildirimdeki Git butonuyla ilgili kaydı açın veya Veri Girişi Geçmişi ekranına gidin.',
                            'İklim Admin yorumunu dikkatle okuyun.',
                            'Hatalı değeri, yılı, açıklamayı veya kanıt dosyasını düzeltin.',
                            'Yeni kanıt gerekiyorsa dosyayı ekleyin.',
                            'Onaya Gönder butonuyla kaydı tekrar gönderin.',
                            'Durumun yeniden Onay Bekliyor olduğunu kontrol edin.',
                        ],
                    ],
                    [
                        'type' => 'bullets',
                        'title' => 'Onaylı kayıt neden kilitlenir?',
                        'items' => [
                            'Onaylı kayıtlar raporlara girebildiği için doğrudan değiştirilmez.',
                            'Hata fark edilirse İklim Admin önce onayı kaldırır veya düzeltme sürecini açar.',
                            'Bu kural raporların sonradan sessizce değişmesini engeller.',
                        ],
                    ],
                ],
            ],
            [
                'id' => 'mudurluk-bildirim',
                'title' => 'Bildirimler',
                'icon' => 'bi-bell',
                'intro' => 'Bildirimler size atanan yeni işler, veri değerlendirme sonuçları ve düzeltme talepleri için kısa yoldur. Sağ üst zil son bildirimleri gösterir; Bildirimler ekranı tüm listeyi ve filtreleri içerir.',
                'blocks' => [
                    [
                        'type' => 'table',
                        'title' => 'Müdürlük kullanıcısına gelen bildirimler',
                        'rows' => [
                            ['label' => 'Yeni Eylem Atandı', 'text' => 'Müdürlüğünüz bir eyleme birincil veya ek sorumlu olarak eklenmiştir. Müdürlük Eylemleri ekranında görünür.'],
                            ['label' => 'Yeni Faaliyet Atandı', 'text' => 'Bir eylemin alt faaliyeti müdürlüğünüze bağlanmıştır.'],
                            ['label' => 'Yeni KPI Atandı', 'text' => 'Veri girmeniz beklenen yeni gösterge tanımlanmıştır.'],
                            ['label' => 'Düzeltme İstendi', 'text' => 'Gönderdiğiniz kayıtta açıklama, değer, yıl veya kanıt tarafında düzeltme beklenir.'],
                            ['label' => 'Veri Onaylandı', 'text' => 'Gönderdiğiniz kayıt kabul edilmiştir.'],
                            ['label' => 'Veri Reddedildi', 'text' => 'Kayıt uygun bulunmamıştır; gerekçeyi okuyup doğru kayıtla tekrar ilerleyin.'],
                        ],
                    ],
                    [
                        'type' => 'steps',
                        'title' => 'Bildirimden işe gitme',
                        'items' => [
                            'Sağ üst zil simgesine basın veya Bildirimler ekranını açın.',
                            'Okunmamış bildirimleri önceliklendirin.',
                            'Bildirimde Git butonu varsa ilgili ekrana geçin.',
                            'İşlemi tamamladıktan sonra bildirimi okundu işaretleyin.',
                            'Gerekirse Bildirimler ekranında olay veya okunma durumuna göre filtreleyin.',
                        ],
                    ],
                ],
            ],
            [
                'id' => 'mudurluk-hata-sss',
                'title' => 'Sık Sorulan Sorular ve Hata Çözümü',
                'icon' => 'bi-question-circle',
                'intro' => 'Veri gönderirken alınan hataların çoğu eksik açıklama, yanlış yıl, kanıt zorunluluğu veya yetki atamasıyla ilgilidir.',
                'blocks' => [
                    [
                        'type' => 'faq',
                        'title' => 'Müdürlük sık sorulanlar',
                        'items' => [
                            ['q' => 'Eylemim görünmüyor, ne yapmalıyım?', 'a' => 'İklim Admin’in müdürlüğünüzü ilgili eyleme birincil veya ek sorumlu olarak eklemesi gerekir. Kendi ekranınızdan bu atamayı değiştiremezsiniz.'],
                            ['q' => 'Onaya gönderirken neden hata alıyorum?', 'a' => 'Açıklama boş olabilir, değer sayısal olmayabilir, KPI kanıt zorunlu olabilir veya dosya türü kabul edilmiyor olabilir. Alanları tek tek kontrol edin.'],
                            ['q' => 'Dosya yükledim ama kanıt hatası devam ediyor.', 'a' => 'Dosya yükleme başarısız olmuş, dosya boyutu sınırı aşılmış veya tarayıcı dosyayı seçmemiş olabilir. Dosyayı tekrar seçip gönderin.'],
                            ['q' => 'Yanlış değer gönderdim, ne yapmalıyım?', 'a' => 'Kayıt onaylı değilse düzenleyip tekrar onaya gönderin. Onaylıysa İklim Admin ile iletişime geçin; onayın kaldırılması gerekir.'],
                            ['q' => 'Aynı kullanıcıyla ikinci bilgisayardan giriş yaptım ve ilk ekran çıkışa attı.', 'a' => 'Sistem kişi başına tek aktif oturum tutar. Yeni giriş eski oturumu güvenlik amacıyla geçersiz yapar.'],
                            ['q' => 'Değer alanına virgüllü sayı girebilir miyim?', 'a' => 'Form sayısal değer bekler. Tarayıcı davranışına göre nokta kullanmak daha sorunsuz olabilir. Birimi değer alanına yazmayın.'],
                            ['q' => 'KPI kanıt istemiyor, yine dosya eklemeli miyim?', 'a' => 'Zorunlu değilse sistem şart koşmaz; ancak değeri doğrulayan belge varsa eklemek onay sürecini hızlandırır.'],
                        ],
                    ],
                    [
                        'type' => 'checklist',
                        'title' => 'Gönderim öncesi son kontrol',
                        'items' => [
                            'Doğru KPI seçildi.',
                            'Yıl doğru.',
                            'Değer yalnızca sayı olarak yazıldı.',
                            'Açıklama kaynak, dönem ve hesaplamayı anlatıyor.',
                            'Kanıt zorunluysa dosya eklendi.',
                            'Dosya doğru yılı ve değeri destekliyor.',
                            'Onaya Gönder sonrası Veri Girişi Geçmişi ekranında kayıt görünüyor.',
                        ],
                    ],
                    [
                        'type' => 'note',
                        'tone' => 'tip',
                        'text' => 'Takıldığınız noktada İklim Admin’e yazarken eylem kodunu, KPI adını, yılı ve ekrandaki hata mesajını birlikte iletin. Bu bilgiler sorunun hızlı çözülmesini sağlar.',
                    ],
                ],
            ],
        ],
    ],
];

$guide = $roleGuides[$helpRole];
$summaryCards = $guide['summary'];
$quickLinks = $guide['links'];
$sections = $guide['sections'];

require_once APP_ROOT . '/uygulama/yerlesim/ust.php';
?>

<div class="yardim-tanitim p-4 p-md-5 mb-4">
    <div class="d-flex flex-column flex-lg-row justify-content-between gap-3">
        <div>
            <div class="yardim-ust-etiket mb-2"><?= help_h($guide['kicker']) ?></div>
            <h4 class="fw-bold mb-2"><?= help_h($guide['headline']) ?></h4>
            <p class="text-muted mb-0" style="max-width:920px;"><?= help_h($guide['intro']) ?></p>
        </div>
        <div class="d-flex gap-2 align-items-start flex-wrap">
            <?php foreach ($quickLinks as $link): ?>
            <a href="<?= help_h($link['href']) ?>" class="btn <?= help_h($link['class']) ?>">
                <i class="bi <?= help_h($link['icon']) ?> me-1"></i><?= help_h($link['label']) ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <?php foreach ($summaryCards as $card): ?>
    <div class="col-12 col-md-6 col-xl-3">
        <div class="yardim-mini-kart">
            <div class="yardim-mini-etiket"><?= help_h($card['label']) ?></div>
            <div class="yardim-mini-deger"><?= help_h($card['value']) ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="row g-4">
    <div class="col-12 col-xl-4">
        <div class="card yardim-bolum position-sticky" style="top: 88px;">
            <div class="card-body">
                <div class="yardim-ust-etiket mb-2">Hızlı Başlıklar</div>
                <div class="yardim-dizi">
                    <?php foreach ($sections as $index => $section): ?>
                    <a href="#<?= help_h($section['id']) ?>">
                        <i class="bi <?= help_h($section['icon']) ?> me-2"></i><?= $index + 1 ?>. <?= help_h($section['title']) ?>
                    </a>
                    <?php endforeach; ?>
                </div>
                <div class="yardim-not tip mt-3">
                    <strong>Eğitim notu:</strong> <?= help_h($guide['demo_note']) ?>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-8">
        <?php foreach ($sections as $index => $section): ?>
        <div class="card yardim-bolum mb-4" id="<?= help_h($section['id']) ?>">
            <div class="card-body p-4">
                <div class="d-flex align-items-start gap-3 mb-3">
                    <div class="yardim-simge"><i class="bi <?= help_h($section['icon']) ?>"></i></div>
                    <div>
                        <div class="yardim-ust-etiket mb-1">Bölüm <?= $index + 1 ?></div>
                        <h5 class="fw-bold mb-0"><?= help_h($section['title']) ?></h5>
                    </div>
                </div>

                <?php if (!empty($section['intro'])): ?>
                <p class="text-muted mb-4"><?= help_h($section['intro']) ?></p>
                <?php endif; ?>

                <?php foreach (($section['blocks'] ?? []) as $block): ?>
                    <?php if (!empty($block['title'])): ?>
                    <div class="yardim-blok-baslik"><?= help_h($block['title']) ?></div>
                    <?php endif; ?>

                    <?php if (($block['type'] ?? '') === 'steps'): ?>
                    <ol class="yardim-adimlar mb-4">
                        <?php foreach (($block['items'] ?? []) as $step): ?>
                        <li><?= help_h($step) ?></li>
                        <?php endforeach; ?>
                    </ol>
                    <?php endif; ?>

                    <?php if (($block['type'] ?? '') === 'bullets'): ?>
                    <ul class="mb-4 ps-3">
                        <?php foreach (($block['items'] ?? []) as $bullet): ?>
                        <li class="mb-2"><?= help_h($bullet) ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>

                    <?php if (($block['type'] ?? '') === 'checklist'): ?>
                    <div class="yardim-kontrol-listesi mb-4">
                        <?php foreach (($block['items'] ?? []) as $item): ?>
                        <div class="yardim-kontrol-oge">
                            <i class="bi bi-check2-circle"></i>
                            <span><?= help_h($item) ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <?php if (($block['type'] ?? '') === 'table'): ?>
                    <div class="yardim-tablo mb-4">
                        <?php foreach (($block['rows'] ?? []) as $field): ?>
                        <div class="yardim-tablo-satir p-3">
                            <div class="yardim-tablo-anahtar mb-1"><?= help_h($field['label']) ?></div>
                            <div class="text-muted"><?= help_h($field['text']) ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <?php if (($block['type'] ?? '') === 'cards'): ?>
                    <div class="row g-3 mb-4">
                        <?php foreach (($block['items'] ?? []) as $item): ?>
                        <div class="col-12 col-md-6">
                            <div class="yardim-satir-kart h-100">
                                <div class="yardim-satir-simge"><i class="bi <?= help_h($item['icon'] ?? 'bi-info-circle') ?>"></i></div>
                                <div>
                                    <div class="fw-bold mb-1"><?= help_h($item['title']) ?></div>
                                    <div class="text-muted small"><?= help_h($item['text']) ?></div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <?php if (($block['type'] ?? '') === 'do_dont'): ?>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <div class="yardim-kural-kart positive h-100">
                                <div class="fw-bold mb-2"><i class="bi bi-check-circle me-1"></i><?= help_h($block['do_title'] ?? 'Yap') ?></div>
                                <ul class="mb-0 ps-3">
                                    <?php foreach (($block['do'] ?? []) as $item): ?>
                                    <li class="mb-2"><?= help_h($item) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="yardim-kural-kart caution h-100">
                                <div class="fw-bold mb-2"><i class="bi bi-exclamation-triangle me-1"></i><?= help_h($block['dont_title'] ?? 'Dikkat Et') ?></div>
                                <ul class="mb-0 ps-3">
                                    <?php foreach (($block['dont'] ?? []) as $item): ?>
                                    <li class="mb-2"><?= help_h($item) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (($block['type'] ?? '') === 'faq'): ?>
                    <div class="mb-4">
                        <?php foreach (($block['items'] ?? []) as $item): ?>
                        <div class="yardim-sss-oge">
                            <div class="fw-semibold mb-1"><?= help_h($item['q']) ?></div>
                            <div class="text-muted"><?= help_h($item['a']) ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <?php if (($block['type'] ?? '') === 'note'): ?>
                    <div class="yardim-not <?= help_h($block['tone'] ?? 'tip') ?> mb-4">
                        <?= help_h($block['text'] ?? '') ?>
                    </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<?php require_once APP_ROOT . '/uygulama/yerlesim/alt.php'; ?>
