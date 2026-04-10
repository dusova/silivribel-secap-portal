<?php

require_once __DIR__ . '/../../bootstrap.php';
Auth::requireAdmin();

$pageTitle = 'Yardım';
$activeNav = 'help';

$summaryCards = [
    ['label' => 'Bu sayfa ne sunar?', 'value' => 'Tüm admin ekranlarının kullanım mantığı, alan açıklamaları ve iş kuralları'],
    ['label' => 'Kimler için?', 'value' => 'Yönetici rolündeki admin kullanıcılar'],
    ['label' => 'Odak alanları', 'value' => 'Eylem, faaliyet, KPI, veri onayı, raporlama ve kullanıcı yönetimi'],
];

$sections = [
    [
        'id' => 'rol-ozeti',
        'title' => 'Admin Rolünün Yetki Kapsamı',
        'intro' => 'Admin rolü sistemdeki en geniş yetkili roldür. Bu nedenle yapılan işlemler yalnızca operasyonel değil, kurumsal kayıt ve denetim açısından da önemlidir.',
        'bullets' => [
            'Tüm eylemleri, faaliyetleri, KPI kayıtlarını ve veri girişlerini görebilirsiniz.',
            'Yeni eylem, faaliyet, KPI ve kullanıcı oluşturabilirsiniz.',
            'Bekleyen veri girişlerini onaylayabilir veya mevcut onayı kaldırabilirsiniz.',
            'Müdürlükler adına manuel veri girişi yapabilirsiniz.',
            'Excel dışa aktarma işlemi ile kurumsal rapor üretebilirsiniz.',
        ],
        'notes' => [
            'Admin ekranı yalnızca listeleme amaçlı değildir; sistem konfigürasyonunun ana merkezidir.',
            'Özellikle kullanıcı rolü, aktiflik durumu ve veri onayı işlemleri kritik etkiye sahiptir.',
        ],
        'warning' => 'Yanlış bir admin işlemi birden fazla müdürlüğün iş akışına etki edebilir. İşlem yapmadan önce yıl, müdürlük ve ilgili kaydı ikinci kez kontrol etmek iyi pratiktir.',
    ],
    [
        'id' => 'oturum',
        'title' => 'Sisteme Giriş ve Oturum Kuralları',
        'intro' => 'Admin kullanıcı olarak sisteme giriş yaptığınızda ilk olarak Kontrol Merkezi ekranına yönlendirilirsiniz.',
        'steps' => [
            'Giriş ekranında kullanıcı adınızı girin.',
            'Şifrenizi yazın.',
            'Giriş Yap butonuna basın.',
            'Başarılı giriş sonrasında Kontrol Merkezi ekranı açılır.',
        ],
        'bullets' => [
            'Pasif hesaba sahip kullanıcılar sisteme giriş yapamaz.',
            'Çok fazla hatalı denemede sistem geçici kilit uygulayabilir.',
            'Uzun süre işlem yapılmadığında oturum kapanabilir.',
            'Şifre veya rol değişikliği sonrasında sistem yeniden giriş isteyebilir.',
        ],
        'notes' => [
            'Güvensiz şekilde tarayıcı açık bırakmamak için iş bitince Oturumu Kapat butonunu kullanın.',
            'Ortak bilgisayarda admin oturumunu açık bırakmak veri güvenliği riski oluşturur.',
        ],
    ],
    [
        'id' => 'ekran-yapisi',
        'title' => 'Genel Ekran Yapısı',
        'intro' => 'Sisteme giriş yaptıktan sonra solda ana menü, üstte sayfa başlığı ve tarih, altta ise aktif kullanıcı bilgisi bulunur.',
        'bullets' => [
            'Yönetim menüsü: Kontrol Merkezi, Eylemler, Veri Girişleri, Veri İzleme, Kullanıcılar, Yardım.',
            'Hızlı erişim: Veri Girişi ve Dışa Aktar.',
            'Sayfa başlığı üst bölümde hangi ekranda olduğunuzu gösterir.',
            'Sol alttaki Oturumu Kapat butonu güvenli çıkış için kullanılır.',
        ],
        'notes' => [
            'Yardım ekranı açık tutulurken diğer sayfalar yeni sekmede incelenebilir.',
            'Menüdeki aktif işaretleme hangi bölümde çalıştığınızı kolay takip etmenizi sağlar.',
        ],
    ],
    [
        'id' => 'kontrol-merkezi',
        'title' => 'Kontrol Merkezi',
        'intro' => 'Kontrol Merkezi sistemin genel özet ekranıdır. Günlük kontrole buradan başlamak en doğru yöntemdir.',
        'bullets' => [
            'Toplam eylem, faaliyet, KPI, veri girişi ve kullanıcı sayılarını gösterir.',
            'Onay bekleyen kayıtlar için hızlı durum görünümü sunar.',
            'Eylem durum dağılımı ve kategori dağılımı gibi yönetsel görünümler sağlar.',
            'Müdürlük bazlı ilerleme tablosu sayesinde hangi birimlerin veri konusunda geride kaldığı görülebilir.',
            'Son veri girişleri tablosu ile en son kim, ne zaman, hangi kaydı girmiş izlenebilir.',
        ],
        'tips' => [
            'Sabah ilk iş olarak bu ekranı kontrol edip bekleyen kayıt sayısını not etmek iyi bir rutindir.',
            'Genel özet yeterli değilse Veri Girişleri ve Veri İzleme ekranlarına geçin.',
        ],
    ],
    [
        'id' => 'eylemler',
        'title' => 'Eylemler Ekranı ve Yeni Eylem Oluşturma',
        'intro' => 'Eylemler ekranı sistemde tanımlı tüm eylemleri listelemek, filtrelemek ve yeni kayıt açmak için kullanılır.',
        'steps' => [
            'Eylemler menüsü üzerinden liste ekranını açın.',
            'Gerekirse Müdürlük ve Durum filtreleri ile listeyi daraltın.',
            'Yeni kayıt için Yeni Eylem butonuna basın.',
            'Form alanlarını doldurup Oluştur butonuna basın.',
        ],
        'fields' => [
            ['label' => 'Eylem Kodu', 'text' => 'Zorunludur. Sistem içinde benzersiz olmalıdır. Aynı kod ikinci kez kullanılamaz.'],
            ['label' => 'Eylem Başlığı', 'text' => 'Zorunludur. Raporlamada görünen ana isimdir.'],
            ['label' => 'Proje Açıklaması', 'text' => 'İsteğe bağlıdır. Eylemin kapsam ve amacını açıklamak için kullanılır.'],
            ['label' => 'Performans Göstergeleri', 'text' => 'İsteğe bağlıdır. Eylemin nasıl izleneceğini metinsel olarak belirtir.'],
            ['label' => 'Birincil Sorumlu Müdürlük', 'text' => 'Zorunludur. Eylemin asıl sahibi olan birimi tanımlar.'],
            ['label' => 'Ek Sorumlu Müdürlükler', 'text' => 'İsteğe bağlıdır. Katkıcı birimlerin eyleme erişimini açmak için kullanılır.'],
            ['label' => 'Başlangıç ve Bitiş Yılı', 'text' => 'Planlama dönemini belirler. Başlangıç yılı zorunlu, bitiş yılı opsiyoneldir.'],
            ['label' => 'Durum', 'text' => 'Planlandı, Devam Ediyor, Tamamlandı veya İptal olarak seçilebilir.'],
        ],
        'notes' => [
            'Birincil müdürlük ile katkıcı müdürlük mantığı farklıdır; ikisi aynı şey değildir.',
            'Katkıcı müdürlük eklemek, ilgili birimin eylem altındaki verilere erişimini açabilir.',
        ],
        'warning' => 'Eylem kodu kurumsal raporlamada referans alanıdır. Kod standardı belirlenmeden rastgele kodlama yapılmaması önerilir.',
    ],
    [
        'id' => 'eylem-duzenleme',
        'title' => 'Eylem Düzenleme Ekranı',
        'intro' => 'Mevcut bir eylemin sağındaki kalem ikonuna tıkladığınızda düzenleme ekranı açılır. Bu ekranda yalnızca üst bilgi değil, alt bağlı kayıtlar da yönetilir.',
        'bullets' => [
            'Eylemin temel alanları güncellenebilir.',
            'Alt bölümde faaliyetler listesi görülür.',
            'Alt bölümde KPI listesi görülür.',
            'Bu ekrandan yeni faaliyet ve yeni KPI açılışına geçilebilir.',
        ],
        'notes' => [
            'Eğer eylem zaten aktif kullanımdaysa kapsamlı değişikliklerde bağlı KPI ve veri akışına etkisi düşünülmelidir.',
            'Sorumlu müdürlük değişikliği yapıldığında sahadaki kullanıcıların görünür eylem listesi de değişebilir.',
        ],
    ],
    [
        'id' => 'faaliyetler',
        'title' => 'Faaliyet Ekleme ve Düzenleme',
        'intro' => 'Faaliyetler, bir eylemin altındaki alt çalışma kalemleridir. Daha ayrıntılı izleme ihtiyacı olan eylemlerde kullanılır.',
        'steps' => [
            'İlgili eylemin düzenleme ekranını açın.',
            'Faaliyetler bölümündeki Faaliyet Ekle butonuna basın.',
            'Faaliyet başlığını ve gerekiyorsa açıklama alanlarını doldurun.',
            'Sorumlu müdürlüğü seçin ve Oluştur butonuna basın.',
        ],
        'fields' => [
            ['label' => 'Faaliyet Başlığı', 'text' => 'Zorunludur. Faaliyetin listelerde görünecek ana adıdır.'],
            ['label' => 'Gerçekleştirilecek Eylemler', 'text' => 'İsteğe bağlıdır. Faaliyetin kapsamı veya alt işi burada anlatılabilir.'],
            ['label' => 'Sorumlu Müdürlük', 'text' => 'Zorunludur. Faaliyetin uygulama sahibini belirtir.'],
            ['label' => 'Sıra No', 'text' => 'İsteğe bağlıdır. Görünüm ve öncelik sırası için kullanılır.'],
        ],
        'notes' => [
            'Başarılı kayıt sonrasında sistem sizi ilgili eylemin düzenleme ekranına geri götürür.',
            'Faaliyet listesinde kalem ikonu ile mevcut faaliyetler güncellenebilir.',
        ],
    ],
    [
        'id' => 'kpi',
        'title' => 'KPI Ekleme, Düzenleme ve Pasifleştirme',
        'intro' => 'KPI, raporlanacak veri başlığını temsil eder. Veri toplama sürecinin temeli doğru KPI tanımıdır.',
        'steps' => [
            'İlgili eylemin düzenleme ekranını açın.',
            'KPI Ekle butonuna basın.',
            'KPI adını, birimini ve gerekli hedef alanlarını doldurun.',
            'Oluştur veya Güncelle butonu ile işlemi tamamlayın.',
        ],
        'fields' => [
            ['label' => 'KPI Adı', 'text' => 'Zorunludur. Girilecek verinin ne olduğunu net şekilde tanımlar.'],
            ['label' => 'Birim', 'text' => 'Zorunludur. Oran, adet, ton, kWh benzeri gösterim birimidir.'],
            ['label' => 'Bağlı Faaliyet', 'text' => 'İsteğe bağlıdır. KPI belirli bir faaliyete bağlanacaksa seçilir.'],
            ['label' => 'Açıklama', 'text' => 'İsteğe bağlıdır. Kullanıcıya KPI hakkında ek yönlendirme verir.'],
            ['label' => 'Hedef Değer ve Hedef Açıklaması', 'text' => 'İsteğe bağlıdır. Beklenen sonuç veya hedef seviye burada tanımlanır.'],
            ['label' => 'Başlangıç Değeri ve Referans Yılı', 'text' => 'İsteğe bağlıdır. Karşılaştırma ve raporlama için faydalıdır.'],
            ['label' => 'Kümülatif KPI', 'text' => 'Birikimli ilerleyen veriler için işaretlenir.'],
        ],
        'bullets' => [
            'KPI doğrudan eyleme bağlanabilir veya belli bir faaliyetin altına yerleştirilebilir.',
            'Düzenleme ekranından Deaktive Et butonu ile KPI tamamen silinmeden pasif duruma alınabilir.',
            'Pasifleştirme işlemi eski veri kayıtlarını korur, yalnızca yeni kullanımı durdurur.',
        ],
        'warning' => 'Yanlış birim veya belirsiz KPI adı, tüm veri kalitesini bozar. KPI adlarını teknik olarak değil, veri girecek personelin anlayacağı netlikte yazmak gerekir.',
    ],
    [
        'id' => 'admin-veri-girisi',
        'title' => 'Admin Olarak Veri Girişi Yapma',
        'intro' => 'Admin kullanıcı tüm aktif KPI kayıtları için manuel veri girebilir. Bu özellik eksik kalan birim verisini tamamlama veya kurumsal toplu veri işleme durumlarında kullanılır.',
        'steps' => [
            'Hızlı erişim veya veri girişi ekranı üzerinden forma gidin.',
            'KPI seçin.',
            'Yıl bilgisini yazın.',
            'Sayısal değeri girin.',
            'Veri kaynağı veya açıklama metnini ekleyin.',
            'Kaydet butonuna basın.',
        ],
        'fields' => [
            ['label' => 'KPI', 'text' => 'Zorunludur. Giriş yapılacak doğru veri başlığı seçilmelidir.'],
            ['label' => 'Yıl', 'text' => 'Zorunludur. Veri hangi raporlama yılına aitse o yıl girilmelidir.'],
            ['label' => 'Değer', 'text' => 'Zorunludur. Sayısal veri alanıdır.'],
            ['label' => 'Veri Kaynağını Açıklayın', 'text' => 'Zorunludur. Ölçüm yöntemi, belge, kaynak kurum veya kısa açıklama burada yazılır.'],
        ],
        'bullets' => [
            'Aynı KPI ve aynı yıl için ikinci kez veri girilirse yeni satır açılmaz; mevcut kayıt güncellenir.',
            'Mevcut kayıt güncellendiğinde önceki onay kaldırılır ve kayıt yeniden bekleyen duruma düşer.',
            'Kayıt sonrasında sistem genellikle Veri Girişleri ekranına geri döner.',
        ],
        'notes' => [
            'Sağ panelde seçilen KPI için hedef, başlangıç değeri ve geçmiş veri kayıtları görülebilir.',
            'Veri girmeden önce aynı yıl için geçmiş kayıt var mı kontrol etmek hata riskini azaltır.',
        ],
    ],
    [
        'id' => 'veri-girisleri',
        'title' => 'Veri Girişleri Ekranı ve Onay Süreci',
        'intro' => 'Bu ekran tüm veri kayıtlarını görüntülemek, filtrelemek ve onay sürecini yönetmek için kullanılır.',
        'steps' => [
            'Veri Girişleri ekranını açın.',
            'Yıl, Müdürlük ve Onay filtrelerini kullanarak listeyi daraltın.',
            'Tek bir kayıt için satırdaki onay ikonunu kullanın.',
            'Birden fazla kayıt için seçim kutuları ile toplu onay verin.',
            'Gerekirse onaylı kaydın onayını kaldırarak düzenlenebilir hale getirin.',
        ],
        'bullets' => [
            'Listede eylem, KPI, müdürlük, yıl, değer, girişi yapan kişi, açıklama ve onay durumu yer alır.',
            'Tekli onay ve tekli onay kaldırma satır bazlı hızlı kontrol için uygundur.',
            'Toplu onay, çok sayıda bekleyen kayıt olduğunda zaman kazandırır.',
            'Liste varsayılan olarak sınırlı sayıda kayıt gösterir; eski veriler için filtre kullanmak gerekir.',
        ],
        'notes' => [
            'Onay, yalnızca kaydın sayısal olarak dolu olması değil; mantıksal olarak da uygun olduğunun kabulüdür.',
            'Şüpheli veya açıklaması zayıf kayıtlarda önce müdürlükten teyit istemek daha doğrudur.',
        ],
        'warning' => 'Toplu onay işlemi hızlı olsa da rastgele kullanılmamalıdır. Filtreler doğru ayarlanmadan toplu işlem yapmak yanlış verilerin onaylanmasına neden olabilir.',
    ],
    [
        'id' => 'izleme',
        'title' => 'Veri İzleme Ekranı',
        'intro' => 'Veri İzleme ekranı, veri girişinden çok kontrol ve okuma amaçlı tasarlanmıştır. Eylem bazlı veri tamamlılık ve yıl bazlı görünüm burada izlenir.',
        'bullets' => [
            'Yıl ve müdürlük filtreleri ile görünüm daraltılabilir.',
            'Her eylem için KPI doluluk oranı ve ilgili veriler görülür.',
            'Eylem açıklaması, performans göstergeleri ve sorumlu müdürlükler izlenebilir.',
            'Boş kalan KPI alanları ile veri girilmiş KPI alanları kolayca ayırt edilir.',
        ],
        'tips' => [
            'Dönem sonu raporu öncesinde eksik veri taraması için bu ekran özellikle faydalıdır.',
            'Bir birimin veri performansını izlemek için önce müdürlük filtresi, sonra yıl filtresi kullanılabilir.',
        ],
    ],
    [
        'id' => 'export',
        'title' => 'Excel Dışa Aktarma',
        'intro' => 'Dışa Aktar ekranı sistem verilerini raporlanabilir Excel dosyasına dönüştürür.',
        'steps' => [
            'Hızlı erişimden veya ilgili ekrandan Dışa Aktar sayfasını açın.',
            'Rapor yılını seçin.',
            'Gerekirse belirli bir müdürlük seçin.',
            'Excel Olarak İndir butonuna basın.',
        ],
        'bullets' => [
            'İndirilen dosyada Genel Özet, Eylemler, KPI Detay, Veri Girişleri ve Faaliyetler sayfaları bulunur.',
            'Müdürlük seçimi yapılmadan alınan rapor daha geniş kapsamlıdır.',
            'Rapor alma işlemleri sistem tarafından kayıt altına alınabilir.',
        ],
        'notes' => [
            'Kurul sunumu, dönemsel rapor veya kurum içi paylaşım için en uygun çıktı formatıdır.',
            'Rapor almadan önce veri onay sürecinin tamamlandığından emin olmak daha tutarlı çıktı üretir.',
        ],
    ],
    [
        'id' => 'kullanicilar',
        'title' => 'Kullanıcı Yönetimi',
        'intro' => 'Kullanıcılar ekranı yeni hesap açmak, mevcut hesapları düzenlemek ve aktiflik durumunu yönetmek için kullanılır.',
        'steps' => [
            'Kullanıcılar ekranına gidin.',
            'Yeni Kullanıcı butonuna basın veya mevcut kayıt için kalem ikonunu kullanın.',
            'Form alanlarını doldurun.',
            'Oluştur ya da Güncelle butonuyla işlemi tamamlayın.',
        ],
        'fields' => [
            ['label' => 'Ad Soyad', 'text' => 'Kullanıcının tam adıdır ve listelerde görünür.'],
            ['label' => 'Kullanıcı Adı', 'text' => 'Benzersiz olmalıdır. Giriş için kullanılır.'],
            ['label' => 'E-posta', 'text' => 'Benzersiz olmalıdır. Kurumsal iletişim ve tanım amacıyla tutulur.'],
            ['label' => 'Müdürlük', 'text' => 'Kullanıcının bağlı olduğu birimi belirtir.'],
            ['label' => 'Rol', 'text' => 'Admin veya müdürlük kullanıcısı olarak yetki seviyesini belirler.'],
            ['label' => 'Şifre ve Şifre Tekrar', 'text' => 'Yeni kullanıcı oluşumunda zorunludur. Düzenlemede boş bırakılırsa mevcut şifre korunur.'],
            ['label' => 'Aktif Kullanıcı', 'text' => 'Hesabın sisteme giriş yapıp yapamayacağını belirler.'],
        ],
        'bullets' => [
            'Kullanıcı listesinde son giriş zamanı ve rol bilgisi görülebilir.',
            'Admin kendi hesabını pasifleştiremez.',
            'Şifre değişikliği düzenleme ekranından yapılabilir.',
        ],
        'warning' => 'Rol değişiklikleri kullanıcının görebildiği ekranları hemen etkileyebilir. Özellikle admin rolüne geçiş ve admin rolden çıkış işlemlerinde dikkatli olunmalıdır.',
    ],
    [
        'id' => 'yaygin-hatalar',
        'title' => 'Sık Yapılan Hatalar ve Çözümleri',
        'intro' => 'Aşağıdaki durumlar sahada en sık karşılaşılan kullanıcı hatalarıdır.',
        'faq' => [
            ['q' => 'Eylem kodu zaten kullanılıyor uyarısı ne demek?', 'a' => 'Aynı eylem kodu sistemde daha önce tanımlanmıştır. Farklı ve benzersiz bir kod kullanılmalıdır.'],
            ['q' => 'Veri kaydedildi ama onayı neden kayboldu?', 'a' => 'Aynı KPI ve yıl için mevcut kayıt güncellenince sistem kaydı yeniden bekleyen duruma alır. Bu normal iş kuralıdır.'],
            ['q' => 'Bazı KPI lar neden boş görünüyor?', 'a' => 'İlgili yıl için veri girilmemiş olabilir. Veri İzleme ekranı üzerinden eksik alanlar kontrol edilmelidir.'],
            ['q' => 'Bir müdürlük kaydı neden düzenleyemiyor?', 'a' => 'Kayıt onaylı olabilir veya kullanıcının ilgili eylemde yetkisi olmayabilir.'],
            ['q' => 'Kullanıcı neden giriş yapamıyor?', 'a' => 'Hesap pasif olabilir, şifre yanlış olabilir ya da geçici kilit uygulanmış olabilir.'],
        ],
        'tips' => [
            'Sahadan gelen destek taleplerinde önce kullanıcının rolü, ilgili yıl ve müdürlük filtresi kontrol edilmelidir.',
            'Yanlış veri problemlerinde kaydı hemen silmeye çalışmak yerine önce onay durumu ve geçmiş giriş kayıtları incelenmelidir.',
        ],
    ],
    [
        'id' => 'gunluk-akis',
        'title' => 'Önerilen Günlük Admin Çalışma Sırası',
        'intro' => 'Yönetim tarafında düzenli ve hatasız ilerlemek için aşağıdaki sıralama tavsiye edilir.',
        'steps' => [
            'Kontrol Merkezi ekranından genel durumu ve bekleyen kayıt sayısını kontrol edin.',
            'Veri Girişleri ekranında bekleyen kayıtları filtreleyin ve gözden geçirin.',
            'Eksik veya tutarsız veri alanlarını Veri İzleme ekranından tespit edin.',
            'Gerekiyorsa Eylemler ekranından eylem, faaliyet veya KPI yapısını güncelleyin.',
            'Yeni personel veya görev değişikliği varsa Kullanıcılar ekranından hesapları düzenleyin.',
            'Dönemsel ihtiyaçlarda Dışa Aktar ekranı üzerinden rapor alın.',
        ],
        'tip' => 'Bu sıralama hem veri kalitesini korur hem de gün sonunda bekleyen iş birikmesini azaltır.',
    ],
];

require_once APP_ROOT . '/templates/shared/header.php';
?>

<div class="help-hero p-4 p-md-5 mb-4">
    <div class="d-flex flex-column flex-lg-row justify-content-between gap-3">
        <div>
            <div class="help-kicker mb-2">Admin Yardım Merkezi</div>
            <h4 class="fw-bold mb-2">SECAP yönetim ekranlarını adım adım kullanın</h4>
            <p class="text-muted mb-0" style="max-width:760px;">
                Bu sayfa admin kullanıcılar için hazırlanmıştır. Eylem yönetimi, KPI tanımı, veri onayı,
                dışa aktarma ve kullanıcı yönetimi süreçlerini tek ekranda özetler.
            </p>
        </div>
        <div class="d-flex gap-2 align-items-start flex-wrap">
            <a href="<?= BASE_PATH ?>/public/admin/actions.php" class="btn btn-outline-secondary btn-sm">Eylemler</a>
            <a href="<?= BASE_PATH ?>/public/admin/entries.php" class="btn btn-outline-secondary btn-sm">Veri Girişleri</a>
            <a href="<?= BASE_PATH ?>/public/admin/users.php" class="btn btn-success btn-sm">Kullanıcılar</a>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <?php foreach ($summaryCards as $card): ?>
        <div class="col-12 col-md-4">
            <div class="help-mini-card">
                <div class="help-mini-label"><?= htmlspecialchars($card['label'], ENT_QUOTES, 'UTF-8') ?></div>
                <div class="help-mini-value"><?= htmlspecialchars($card['value'], ENT_QUOTES, 'UTF-8') ?></div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-4">
    <div class="col-12 col-xl-4">
        <div class="card help-section position-sticky" style="top: 88px;">
            <div class="card-body">
                <div class="help-kicker mb-2">Hızlı Başlıklar</div>
                <div class="help-toc">
                    <?php foreach ($sections as $index => $section): ?>
                        <a href="#<?= htmlspecialchars($section['id'], ENT_QUOTES, 'UTF-8') ?>">
                            <?= $index + 1 ?>. <?= htmlspecialchars($section['title'], ENT_QUOTES, 'UTF-8') ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-8">
        <?php foreach ($sections as $index => $section): ?>
            <div class="card help-section mb-4" id="<?= htmlspecialchars($section['id'], ENT_QUOTES, 'UTF-8') ?>">
                <div class="card-body p-4">
                    <div class="help-kicker mb-2">Bölüm <?= $index + 1 ?></div>
                    <h5 class="fw-bold mb-3"><?= htmlspecialchars($section['title'], ENT_QUOTES, 'UTF-8') ?></h5>

                    <?php if (!empty($section['intro'])): ?>
                        <p class="text-muted mb-3"><?= htmlspecialchars($section['intro'], ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endif; ?>

                    <?php if (!empty($section['steps'])): ?>
                        <div class="mb-3">
                            <div class="fw-semibold mb-2">Adımlar</div>
                            <ol class="mb-0 ps-3">
                                <?php foreach ($section['steps'] as $step): ?>
                                    <li class="mb-2"><?= $step ?></li>
                                <?php endforeach; ?>
                            </ol>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($section['bullets'])): ?>
                        <div class="mb-3">
                            <div class="fw-semibold mb-2">Önemli Noktalar</div>
                            <ul class="mb-0 ps-3">
                                <?php foreach ($section['bullets'] as $bullet): ?>
                                    <li class="mb-2"><?= htmlspecialchars($bullet, ENT_QUOTES, 'UTF-8') ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($section['fields'])): ?>
                        <div class="mb-3">
                            <div class="fw-semibold mb-2">Alan Açıklamaları</div>
                            <div class="help-table">
                                <?php foreach ($section['fields'] as $field): ?>
                                    <div class="help-table-row p-3">
                                        <div class="help-table-key mb-1"><?= htmlspecialchars($field['label'], ENT_QUOTES, 'UTF-8') ?></div>
                                        <div class="text-muted"><?= htmlspecialchars($field['text'], ENT_QUOTES, 'UTF-8') ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($section['notes'])): ?>
                        <div class="mb-3">
                            <div class="fw-semibold mb-2">Bilmeniz Gerekenler</div>
                            <ul class="mb-0 ps-3">
                                <?php foreach ($section['notes'] as $note): ?>
                                    <li class="mb-2"><?= htmlspecialchars($note, ENT_QUOTES, 'UTF-8') ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($section['faq'])): ?>
                        <div class="mb-3">
                            <div class="fw-semibold mb-2">Soru ve Cevaplar</div>
                            <?php foreach ($section['faq'] as $item): ?>
                                <div class="border rounded-3 p-3 mb-2">
                                    <div class="fw-semibold mb-1"><?= htmlspecialchars($item['q'], ENT_QUOTES, 'UTF-8') ?></div>
                                    <div class="text-muted mb-0"><?= htmlspecialchars($item['a'], ENT_QUOTES, 'UTF-8') ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($section['tips'])): ?>
                        <div class="mb-3">
                            <div class="fw-semibold mb-2">İpuçları</div>
                            <ul class="mb-0 ps-3">
                                <?php foreach ($section['tips'] as $tipItem): ?>
                                    <li class="mb-2"><?= htmlspecialchars($tipItem, ENT_QUOTES, 'UTF-8') ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($section['tip'])): ?>
                        <div class="help-note tip mt-3">
                            <strong>İpuçlu not:</strong> <?= htmlspecialchars($section['tip'], ENT_QUOTES, 'UTF-8') ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($section['warning'])): ?>
                        <div class="help-note warning mt-3">
                            <strong>Dikkat:</strong> <?= htmlspecialchars($section['warning'], ENT_QUOTES, 'UTF-8') ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php require_once APP_ROOT . '/templates/shared/footer.php'; ?>
