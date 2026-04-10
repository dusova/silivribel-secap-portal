<?php
require_once __DIR__ . '/../../bootstrap.php';
Auth::requireLogin();

if (Auth::isAdmin()) {
    header('Location: ' . BASE_PATH . '/public/admin/help.php');
    exit;
}

$pageTitle = 'Yardım';
$activeNav = 'help';

$summaryCards = [
    ['label' => 'Bu sayfa ne için?', 'value' => 'Müdürlük kullanıcılarının günlük veri girişi ve takip işlemleri'],
    ['label' => 'Kimler kullanır?', 'value' => 'Normal rol veya müdürlük yetkilisi olarak giriş yapan personel'],
    ['label' => 'Ana konular', 'value' => 'Eylemlerim, veri girişi, veri geçmişi, faaliyet ekleme ve yetki sınırları'],
];

$sections = [
    [
        'id' => 'rol-ozeti',
        'title' => 'Müdürlük Kullanıcısı Neler Yapabilir?',
        'intro' => 'Müdürlük kullanıcısı kendi yetkili olduğu eylemler ve KPI kayıtları üzerinde çalışır. Bu rol veri toplama ve saha girişi açısından temel kullanıcı rolüdür.',
        'bullets' => [
            'Yetkili olduğu eylemleri görebilir.',
            'Yetkili olduğu KPI lar için veri girebilir.',
            'Daha önce girilen verileri Veri Geçmişim ekranında takip edebilir.',
            'Onaylanmamış kayıtları düzenleyebilir.',
            'Yetkili olduğu eylemlere yeni faaliyet ekleyebilir.',
        ],
        'notes' => [
            'Kullanıcı açamaz veya düzenleyemezsiniz.',
            'Veri onaylayamazsınız.',
            'Admin ekranlarına erişemezsiniz.',
            'Onaylı kayıtları doğrudan değiştiremezsiniz.',
        ],
        'warning' => 'Sistemde göremiyor olmanız her zaman hata olduğu anlamına gelmez; çoğu zaman ilgili eylem size tanımlı değildir.',
    ],
    [
        'id' => 'giris',
        'title' => 'Sisteme Giriş ve Oturum Kuralları',
        'intro' => 'Başarılı giriş sonrasında Kontrol Merkezi ekranı açılır. Günlük kullanıma buradan başlanır.',
        'steps' => [
            'Kullanıcı adınızı girin.',
            'Şifrenizi yazın.',
            'Giriş Yap butonuna basın.',
            'Başarılı giriş sonrasında Kontrol Merkezi ekranını kontrol edin.',
        ],
        'bullets' => [
            'Pasif hesaplar sisteme giriş yapamaz.',
            'Çok sayıda hatalı deneme geçici bekleme yaratabilir.',
            'Uzun süre işlem yapılmadığında oturum kapanabilir.',
            'Şifre veya yetki değişikliğinde yeniden giriş istenebilir.',
        ],
        'notes' => [
            'Özellikle ortak kullanılan bilgisayarlarda iş bitince Oturumu Kapat butonunu kullanın.',
            'Tarayıcıyı kapatmak yerine doğrudan çıkış yapmak daha güvenlidir.',
        ],
    ],
    [
        'id' => 'ekran-yapisi',
        'title' => 'Genel Ekran Yapısı',
        'intro' => 'Soldaki menü günlük akışın ana merkezidir. Hangi ekranda ne yapılacağını bilmek zamanı azaltır.',
        'bullets' => [
            'Ana menü: Kontrol Merkezi, Eylemlerim, Veri Geçmişim, Yardım.',
            'Hızlı erişim: Yeni Veri Gir.',
            'Üst bölümde sayfa başlığı ve tarih yer alır.',
            'Sol alt bölümde aktif kullanıcı bilgisi ve Oturumu Kapat butonu vardır.',
        ],
        'tips' => [
            'Yardım sayfasını açık bir sekmede tutup diğer işleri ayrıca takip etmek faydalı olabilir.',
            'Sistemi ilk kez kullanan personel önce Eylemlerim ve Veri Geçmişim ekranlarını incelemelidir.',
        ],
    ],
    [
        'id' => 'kontrol-merkezi',
        'title' => 'Kontrol Merkezi',
        'intro' => 'Bu ekran müdürlüğünüzün genel durumunu özetler ve hangi alanlarda eksik veri olduğunu fark etmenizi kolaylaştırır.',
        'bullets' => [
            'Yetkili olduğunuz eylem sayısı görünür.',
            'Yetkili olduğunuz KPI sayısı görünür.',
            'Toplam veri girişiniz ve bu yıl girilen veri sayısı listelenir.',
            'Eylem ilerleme tablosu ile hangi eylemlerde veri eksiği olduğu izlenebilir.',
            'Son veri girişleri sayesinde son yaptığınız işleri kontrol edebilirsiniz.',
        ],
        'tip' => 'Günlük çalışmaya önce bu ekrandan başlamak, eksik KPI veya unutulan yıl kayıtlarını daha hızlı fark ettirir.',
    ],
    [
        'id' => 'eylemlerim',
        'title' => 'Eylemlerim Ekranı',
        'intro' => 'Eylemlerim ekranı müdürlük kullanıcısının ana çalışma ekranıdır. Veri girişi, düzenleme ve faaliyet ekleme işlemleri genellikle burada başlar.',
        'bullets' => [
            'Yetkili olduğunuz tüm eylemler burada listelenir.',
            'Her eylemin altında ilgili faaliyetler ve KPI kayıtları yer alır.',
            'KPI satırında mevcut yıl değeri, hedef ve onay bilgisi görülebilir.',
            'O yıl için kayıt yoksa Gir, varsa Düzenle butonu görünür.',
        ],
        'notes' => [
            'Bir eylemi görebilmeniz için ya birincil sorumlu müdürlük olmanız ya da katkıcı müdürlük olarak tanımlanmış olmanız gerekir.',
            'Bazı eylemler doğrudan sizin biriminize ait olmasa da size veri girişi görevi verilmiş olabilir.',
        ],
        'warning' => 'Yanlış KPI ya veri girmemek için satırdaki eylem kodu, KPI adı ve birim bilgisine birlikte bakmak gerekir.',
    ],
    [
        'id' => 'veri-girisi',
        'title' => 'Yeni Veri Girişi Yapma',
        'intro' => 'Yeni veri girişi iki yoldan açılabilir: soldaki Yeni Veri Gir bağlantısı veya Eylemlerim ekranındaki ilgili KPI satırındaki Gir butonu.',
        'steps' => [
            'Veri girişi ekranını açın.',
            'Doğru KPI yi seçin.',
            'Yıl bilgisini yazın.',
            'Sayısal değeri girin.',
            'Veri kaynağını veya açıklama metnini ekleyin.',
            'Kaydet butonuna basın.',
        ],
        'fields' => [
            ['label' => 'KPI', 'text' => 'Zorunludur. Girilecek verinin ait olduğu veri başlığı seçilir.'],
            ['label' => 'Yıl', 'text' => 'Zorunludur. Veri hangi yıla aitse o yıl girilmelidir.'],
            ['label' => 'Değer', 'text' => 'Zorunludur. Sayısal değer alanıdır.'],
            ['label' => 'Veri Açıklaması', 'text' => 'Zorunludur. Kaynak belge, ölçüm yöntemi veya kısa açıklama burada yazılır.'],
        ],
        'bullets' => [
            'Veri açıklaması boş bırakılamaz.',
            'Başarılı kayıt sonrasında sistem sizi genellikle Eylemlerim ekranına geri götürür.',
            'Sağ panelde hedef, başlangıç değeri ve geçmiş girişler izlenebilir.',
        ],
    ],
    [
        'id' => 'kayit-mantigi',
        'title' => 'Aynı KPI ve Yıl İçin Kayıt Mantığı',
        'intro' => 'Sistem aynı KPI ve aynı yıl için ikinci bir ayrı satır açmaz. Tek kayıt mantığı ile çalışır.',
        'bullets' => [
            'Aynı KPI ve aynı yıl için yeni veri girerseniz mevcut kayıt güncellenir.',
            'Yeni satır oluşmaz; önceki kaydın içeriği değişir.',
            'Kayıt güncellenince onay durumu tekrar bekleyen hale dönebilir.',
        ],
        'notes' => [
            'Bu kural veri bütünlüğünü korumak için uygulanır.',
            'Özellikle geçmiş yıl verilerinde yanlış yıl seçimi en sık hatalardan biridir.',
        ],
        'warning' => 'Daha önce onaylanmış bir kaydı güncelliyorsanız onayın sıfırlanması normaldir. Kaydetmeden önce KPI ve yıl seçimini mutlaka tekrar kontrol edin.',
    ],
    [
        'id' => 'sag-panel',
        'title' => 'Veri Girişi Ekranındaki Sağ Panel',
        'intro' => 'Veri girişi ekranındaki sağ taraf bilgi paneli, veri girmeden önce bağlamı görmeniz için tasarlanmıştır.',
        'bullets' => [
            'Eylem kodu ve eylem başlığı görünür.',
            'Kategori ve sorumlu müdürlük bilgisi yer alabilir.',
            'KPI açıklaması, hedef değer ve başlangıç değeri görülebilir.',
            'Aynı KPI için daha önce girilen geçmiş kayıtlar listelenebilir.',
        ],
        'tips' => [
            'Geçmiş kayıtları kontrol etmek aynı veri üzerinde yinelenen veya tutarsız girişi azaltır.',
            'Hedef ve başlangıç değerini okumak girilen sayının mantıklı olup olmadığını anlamayı kolaylaştırır.',
        ],
    ],
    [
        'id' => 'veri-duzenleme',
        'title' => 'Mevcut Veriyi Düzenleme',
        'intro' => 'Daha önce girilmiş bir veriyi iki farklı yerden düzenleyebilirsiniz: Eylemlerim ekranındaki Düzenle butonu veya Veri Geçmişim ekranındaki kalem ikonu.',
        'steps' => [
            'Eylemlerim veya Veri Geçmişim ekranından ilgili kaydı bulun.',
            'Düzenle butonuna veya kalem ikonuna basın.',
            'Güncel değer ve açıklama alanlarını düzeltin.',
            'Kaydet butonuyla işlemi tamamlayın.',
        ],
        'bullets' => [
            'Sadece bekleyen durumdaki kayıtlar düzenlenebilir.',
            'Onaylı kayıtlarda sistem kilit veya düzenlenemez durumu gösterir.',
            'Düzenleme yapıldığında kaydın denetim izi korunur.',
        ],
    ],
    [
        'id' => 'veri-gecmisim',
        'title' => 'Veri Geçmişim Ekranı',
        'intro' => 'Bu ekran müdürlüğünüze ait tüm veri girişlerini toplu olarak izlemek için kullanılır.',
        'steps' => [
            'Veri Geçmişim menüsünü açın.',
            'Gerekirse yıl filtresi seçin.',
            'Listeyi kayıt, onay durumu ve tarih bazında inceleyin.',
            'Bekleyen kayıtlarda gerekiyorsa düzenleme yapın.',
        ],
        'bullets' => [
            'Ekranın üst bölümünde toplam, onaylı ve bekleyen kayıt sayıları görünür.',
            'Listede eylem kodu, KPI adı, girilmiş değer, hedef değer, onay durumu ve tarih gibi alanlar yer alır.',
            'Onaylı kayıtlar düzenlenemez, bekleyen kayıtlar düzenlenebilir.',
        ],
        'notes' => [
            'Bu ekran yıllık veri takibi ve geçmiş hata kontrolü için oldukça faydalıdır.',
            'Yıl filtresi kullanılmadığında listede daha çok kayıt görülebilir, bu nedenle hedef yıl ile çalışmak kolaylık sağlar.',
        ],
    ],
    [
        'id' => 'onay-farki',
        'title' => 'Onaylı ve Bekleyen Kayıt Farkı',
        'intro' => 'Müdürlük kullanıcısı veri girer, admin ise bu veriyi kontrol ederek onaylar. Bu nedenle onay durumu kaydın işlenme aşamasını gösterir.',
        'faq' => [
            ['q' => 'Bekleyen kayıt ne demektir?', 'a' => 'Veri sisteme girilmiştir ancak admin tarafından henüz kontrol edilmemiştir.'],
            ['q' => 'Onaylı kayıt ne demektir?', 'a' => 'Veri incelenmiş ve resmi kabul edilmiş durumdadır.'],
            ['q' => 'Onaylı kayıt neden düzenlenemiyor?', 'a' => 'Onaylı kayıtlar veri bütünlüğü için kilitlenir. Gerekirse admin önce onayı kaldırmalıdır.'],
            ['q' => 'Yanlış onaylı veri varsa ne yapmalıyım?', 'a' => 'İlgili admin kullanıcıya bilgi verip onayın kaldırılmasını istemelisiniz.'],
        ],
        'tip' => 'Veri Geçmişim ekranında bekleyen kayıtlarınızı düzenli kontrol ederek admin onayı gelmeden önce hataları yakalayabilirsiniz.',
    ],
    [
        'id' => 'faaliyet-ekleme',
        'title' => 'Yeni Faaliyet Ekleme',
        'intro' => 'Yetkili olduğunuz eylemlere yeni faaliyet ekleyebilirsiniz. Bu özellik sahadaki yeni alt işlerin sisteme kaydedilmesi için kullanılır.',
        'steps' => [
            'Eylemlerim ekranında ilgili eylemi bulun.',
            'Ekle butonuna basın.',
            'Faaliyet başlığını yazın.',
            'Gerekirse alt eylem veya açıklama alanını doldurun.',
            'Oluştur butonuna basın.',
        ],
        'fields' => [
            ['label' => 'Faaliyet Başlığı', 'text' => 'Zorunludur. Ekranlarda görünecek çalışma başlığıdır.'],
            ['label' => 'Alt Eylemler veya Açıklama', 'text' => 'İsteğe bağlıdır. Detaylı açıklama veya kapsam bilgisidir.'],
        ],
        'notes' => [
            'Başarılı kayıt sonrasında sistem sizi tekrar Eylemlerim ekranına götürür.',
            'Eklenen faaliyetlerin daha sonra düzenlenmesi veya silinmesi admin tarafında yapılır.',
        ],
    ],
    [
        'id' => 'yetki-sinirlari',
        'title' => 'Yetki Sınırları ve Sık Sorulan Sorular',
        'intro' => 'Müdürlük kullanıcısı olarak yalnızca size açık olan alanlarda işlem yapabilirsiniz.',
        'bullets' => [
            'Admin ekranlarına doğrudan erişemezsiniz.',
            'Kullanıcı oluşturamaz veya düzenleyemezsiniz.',
            'Veri onaylayamazsınız.',
            'Yetkiniz olmayan bir eyleme veri giremezsiniz.',
            'Yetkiniz olmayan eyleme faaliyet ekleyemezsiniz.',
        ],
        'faq' => [
            ['q' => 'Neden bazı eylemleri görüyorum ama bazılarını görmüyorum?', 'a' => 'Yalnızca size birincil veya katkıcı müdürlük olarak tanımlanmış eylemler listelenir.'],
            ['q' => 'Veri girdim ama neden hala bekliyor?', 'a' => 'Çünkü kayıt admin tarafından henüz onaylanmamıştır.'],
            ['q' => 'Faaliyet ekledim ama neden düzenleyemiyorum?', 'a' => 'Faaliyet ekleme yetkiniz vardır ancak ileri yönetim işlemleri admin tarafındadır.'],
            ['q' => 'Bir kayıt veya eylem hiç görünmüyor, bu hata mı?', 'a' => 'Önce yıl filtresi ve yetki durumunu kontrol edin. Çoğu zaman görünmeme nedeni yetki kapsamı dışında olmasıdır.'],
        ],
        'warning' => 'Erişim reddi gördüğünüzde tarayıcı hatası gibi düşünmeyin; sistem sizi yetki dışı bir işlemden koruyor olabilir.',
    ],
    [
        'id' => 'gunluk-akis',
        'title' => 'Önerilen Günlük Çalışma Sırası',
        'intro' => 'Düzenli veri girişi ve eksik kayıtları kaçırmamak için aşağıdaki akış tavsiye edilir.',
        'steps' => [
            'Kontrol Merkezi ekranından genel durumu kontrol edin.',
            'Eylemlerim ekranında eksik veri olan KPI ları bulun.',
            'Gerekli veri girişlerini yapın.',
            'Veri Geçmişim ekranından kayıtlarınızın durumunu takip edin.',
            'Gerekiyorsa ilgili eyleme yeni faaliyet ekleyin.',
        ],
        'tip' => 'Veriyi topladığınız anda sisteme girmek, dönem sonu toplu girişlerde oluşacak hata riskini ciddi biçimde azaltır.',
    ],
];

require_once APP_ROOT . '/templates/shared/header.php';
?>

<div class="help-hero p-4 p-md-5 mb-4">
    <div class="d-flex flex-column flex-lg-row justify-content-between gap-3">
        <div>
            <div class="help-kicker mb-2">Müdürlük Yardım Merkezi</div>
            <h4 class="fw-bold mb-2">Günlük işlemleri daha hızlı ve doğru yapın</h4>
            <p class="text-muted mb-0" style="max-width:760px;">
                Bu sayfa müdürlük kullanıcıları için hazırlanmıştır. Veri girişi, veri geçmişi,
                eylem takibi ve faaliyet ekleme süreçlerini tek ekranda açıklar.
            </p>
        </div>
        <div class="d-flex gap-2 align-items-start flex-wrap">
            <a href="<?= BASE_PATH ?>/public/department/my_actions.php" class="btn btn-outline-secondary btn-sm">Eylemlerim</a>
            <a href="<?= BASE_PATH ?>/public/department/my_entries.php" class="btn btn-outline-secondary btn-sm">Veri Geçmişim</a>
            <a href="<?= BASE_PATH ?>/public/department/data_form.php" class="btn btn-success btn-sm">Yeni Veri Gir</a>
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
