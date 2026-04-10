DROP DATABASE IF EXISTS secap_portal;

CREATE DATABASE secap_portal
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_turkish_ci;

USE secap_portal;

CREATE TABLE departments (
    id          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    name        VARCHAR(150)    NOT NULL COMMENT 'Müdürlük adı',
    slug        VARCHAR(100)    NOT NULL COMMENT 'URL dostu kısa ad',
    description TEXT                     COMMENT 'Açıklama / kısa not',
    is_active   TINYINT(1)      NOT NULL DEFAULT 1,
    created_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_departments_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci
  COMMENT='Belediye müdürlükleri';

CREATE TABLE users (
    id              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    department_id   INT UNSIGNED    NOT NULL COMMENT 'Bağlı olduğu müdürlük',
    username        VARCHAR(80)     NOT NULL,
    email           VARCHAR(180)    NOT NULL,
    password_hash   VARCHAR(255)    NOT NULL,
    full_name       VARCHAR(150)    NOT NULL,
    role            ENUM('admin','department_user') NOT NULL DEFAULT 'department_user',
    is_active       TINYINT(1)      NOT NULL DEFAULT 1,
    last_login_at   TIMESTAMP                NULL,
    created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_users_username (username),
    UNIQUE KEY uq_users_email    (email),

    CONSTRAINT fk_users_department
        FOREIGN KEY (department_id) REFERENCES departments (id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci
  COMMENT='Sistem kullanıcıları';

CREATE TABLE actions (
    id                        INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    responsible_department_id  INT UNSIGNED    NOT NULL COMMENT 'Birincil sorumlu müdürlük',
    code                      VARCHAR(30)     NOT NULL COMMENT 'A.1.H.1.1 gibi eylem kodu',
    title                     VARCHAR(500)    NOT NULL COMMENT 'Eylem başlığı',
    description               TEXT                     COMMENT 'Proje açıklaması',
    performance_indicators    TEXT                     COMMENT 'Performans göstergeleri özeti',
    category                  VARCHAR(100)             COMMENT 'Enerji, Ulaşım, Atık vb.',
    start_year                SMALLINT UNSIGNED        COMMENT 'Başlangıç yılı',
    end_year                  SMALLINT UNSIGNED        COMMENT 'Bitiş / hedef yılı',
    status                    ENUM('planned','ongoing','completed','cancelled')
                              NOT NULL DEFAULT 'planned',
    created_by                INT UNSIGNED             COMMENT 'Oluşturan admin user_id',
    created_at                TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at                TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_actions_code (code),

    CONSTRAINT fk_actions_department
        FOREIGN KEY (responsible_department_id) REFERENCES departments (id)
        ON DELETE RESTRICT ON UPDATE CASCADE,

    CONSTRAINT fk_actions_creator
        FOREIGN KEY (created_by) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci
  COMMENT='SECAP eylemleri';

CREATE TABLE action_departments (
    action_id       INT UNSIGNED NOT NULL,
    department_id   INT UNSIGNED NOT NULL,
    role_type       ENUM('primary','contributor') NOT NULL DEFAULT 'contributor',
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (action_id, department_id),

    CONSTRAINT fk_ad_action
        FOREIGN KEY (action_id) REFERENCES actions (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_ad_department
        FOREIGN KEY (department_id) REFERENCES departments (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci
  COMMENT='Eyleme atanmış müdürlükler (çoklu sorumluluk)';

CREATE TABLE activities (
    id              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    action_id       INT UNSIGNED    NOT NULL COMMENT 'Bağlı eylem',
    department_id   INT UNSIGNED    NOT NULL COMMENT 'Faaliyetten sorumlu müdürlük',
    title           VARCHAR(500)    NOT NULL COMMENT 'Faaliyet başlığı (Sorumlu Olduğu Faaliyet)',
    sub_actions     TEXT                     COMMENT 'Gerçekleştirilecek eylemler açıklaması',
    sort_order      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    is_active       TINYINT(1)      NOT NULL DEFAULT 1,
    created_by      INT UNSIGNED             COMMENT 'Oluşturan kullanıcı',
    created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),

    CONSTRAINT fk_activities_action
        FOREIGN KEY (action_id) REFERENCES actions (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_activities_department
        FOREIGN KEY (department_id) REFERENCES departments (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_activities_creator
        FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci
  COMMENT='Faaliyetler (Eylem altındaki alt-faaliyetler)';

CREATE TABLE kpis (
    id              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    action_id       INT UNSIGNED    NOT NULL COMMENT 'Bağlı eylem',
    activity_id     INT UNSIGNED             COMMENT 'Bağlı faaliyet (opsiyonel)',
    name            VARCHAR(200)    NOT NULL COMMENT 'KPI adı (Bildirilecek Veri)',
    unit            VARCHAR(50)     NOT NULL COMMENT 'Adet, kWp, ton, % vb.',
    description     TEXT                     COMMENT 'Gösterge açıklaması',
    baseline_value  DECIMAL(15,4)            COMMENT 'Başlangıç (referans) değeri',
    target_value    DECIMAL(15,4)            COMMENT 'Yıllık hedef değer',
    target_label    VARCHAR(200)             COMMENT 'Hedef açıklaması (ör: 30 adet GES uygulaması)',
    baseline_year   SMALLINT UNSIGNED        COMMENT 'Referans yılı',
    is_cumulative   TINYINT(1)      NOT NULL DEFAULT 0 COMMENT 'Kümülatif mi?',
    is_active       TINYINT(1)      NOT NULL DEFAULT 1,
    created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),

    CONSTRAINT fk_kpis_action
        FOREIGN KEY (action_id) REFERENCES actions (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_kpis_activity
        FOREIGN KEY (activity_id) REFERENCES activities (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci
  COMMENT='Performans göstergeleri (KPI)';

CREATE TABLE data_entries (
    id              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    kpi_id          INT UNSIGNED    NOT NULL COMMENT 'Bağlı KPI',
    action_id       INT UNSIGNED    NOT NULL COMMENT 'Denormalize: bağlı eylem',
    department_id   INT UNSIGNED    NOT NULL COMMENT 'Denormalize: girişi yapan müdürlük',
    entered_by      INT UNSIGNED    NOT NULL COMMENT 'Girişi yapan kullanıcı',
    year            SMALLINT UNSIGNED NOT NULL COMMENT 'Verinin ait olduğu yıl',
    value           DECIMAL(15,4)   NOT NULL COMMENT 'Ölçülen değer',
    notes           TEXT            NOT NULL COMMENT 'Veri açıklaması (zorunlu)',
    is_verified     TINYINT(1)      NOT NULL DEFAULT 0 COMMENT 'Admin onayı',
    verified_by     INT UNSIGNED             COMMENT 'Onaylayan admin user_id',
    verified_at     TIMESTAMP                NULL,
    created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),

    UNIQUE KEY uq_data_entry_kpi_year (kpi_id, year),

    CONSTRAINT fk_entries_kpi
        FOREIGN KEY (kpi_id) REFERENCES kpis (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_entries_action
        FOREIGN KEY (action_id) REFERENCES actions (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_entries_department
        FOREIGN KEY (department_id) REFERENCES departments (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_entries_user
        FOREIGN KEY (entered_by) REFERENCES users (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_entries_verifier
        FOREIGN KEY (verified_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci
  COMMENT='Yıllık KPI veri girişleri';

CREATE INDEX idx_actions_dept      ON actions       (responsible_department_id);
CREATE INDEX idx_actions_status    ON actions       (status);
CREATE INDEX idx_activities_action ON activities    (action_id);
CREATE INDEX idx_activities_dept   ON activities    (department_id);
CREATE INDEX idx_kpis_action       ON kpis          (action_id);
CREATE INDEX idx_kpis_activity     ON kpis          (activity_id);
CREATE INDEX idx_entries_year      ON data_entries  (year);
CREATE INDEX idx_entries_dept      ON data_entries  (department_id);
CREATE INDEX idx_entries_user      ON data_entries  (entered_by);
CREATE INDEX idx_entries_verified  ON data_entries  (is_verified);

INSERT INTO departments (name, slug, description) VALUES
('İklim Değişikliği ve Sıfır Atık Müdürlüğü', 'iklim-degisikligi',   'Sistem yöneticisi ve koordinatör birim. SECAP eylem planı koordinasyonu.'),
('Fen İşleri Müdürlüğü',                       'fen-isleri',           'Altyapı, yapı işleri, aydınlatma, bina enerji verimliliği'),
('Park ve Bahçeler Müdürlüğü',                 'park-bahceler',        'Yeşil alan, peyzaj, ağaçlandırma'),
('Temizlik İşleri Müdürlüğü',                  'temizlik-isleri',      'Atık yönetimi, geri dönüşüm, kompostlama'),
('Ulaşım Hizmetleri Müdürlüğü',               'ulasim-hizmetleri',    'Toplu taşıma, trafik, bisiklet yolları'),
('Basın Yayın ve Halkla İlişkiler Müdürlüğü',  'basin-yayin',          'Halkla ilişkiler, bilinçlendirme görselleri, sosyal medya'),
('Destek Hizmetleri Müdürlüğü',                'destek-hizmetleri',    'Araç filosu, lojistik, hizmet binası yönetimi'),
('İmar ve Şehircilik Müdürlüğü',               'imar-sehircilik',      'İmar planları, yeşil bina standartları'),
('Zabıta Müdürlüğü',                           'zabita',               'Çevre denetimi, ruhsat kontrolü'),
('Mali Hizmetler Müdürlüğü',                   'mali-hizmetler',       'Bütçe, finansman, yeşil tahvil');

INSERT INTO departments (name, slug, description, is_active) VALUES
('BEDAŞ (Dış Paydaş)',  'bedas-dis-paydas',   'Boğaziçi Elektrik Dağıtım A.Ş. — Elektrik tüketim verileri',   1),
('İGDAŞ (Dış Paydaş)',  'igdas-dis-paydas',   'İstanbul Gaz Dağıtım A.Ş. — Doğalgaz tüketim verileri',       1),
('İSKİ (Dış Paydaş)',   'iski-dis-paydas',    'İstanbul Su ve Kanalizasyon İdaresi — Su tüketim verileri',    1),
('İETT (Dış Paydaş)',   'iett-dis-paydas',    'İstanbul Elektrik Tramvay ve Tünel — Toplu taşıma verileri',  1);

INSERT INTO users (department_id, username, email, password_hash, full_name, role) VALUES
(1,  'admin',           'admin@silivri.bel.tr',         '$2y$12$placeholder_hash_admin',      'Sistem Yöneticisi',               'admin'),
(1,  'iklim_user',      'iklim@silivri.bel.tr',         '$2y$12$placeholder_hash_iklim',      'İklim Müdürlüğü Yetkilisi',       'department_user'),
(2,  'fen_user',        'fen@silivri.bel.tr',           '$2y$12$placeholder_hash_fen',        'Fen İşleri Yetkilisi',            'department_user'),
(3,  'park_user',       'park@silivri.bel.tr',          '$2y$12$placeholder_hash_park',       'Park Bahçeler Yetkilisi',         'department_user'),
(4,  'temizlik_user',   'temizlik@silivri.bel.tr',      '$2y$12$placeholder_hash_tmz',        'Temizlik İşleri Yetkilisi',       'department_user'),
(5,  'ulasim_user',     'ulasim@silivri.bel.tr',        '$2y$12$placeholder_hash_ula',        'Ulaşım Hizmetleri Yetkilisi',     'department_user'),
(6,  'basin_user',      'basin@silivri.bel.tr',         '$2y$12$placeholder_hash_basin',      'Basın Yayın Yetkilisi',           'department_user'),
(7,  'destek_user',     'destek@silivri.bel.tr',        '$2y$12$placeholder_hash_destek',     'Destek Hizmetleri Yetkilisi',     'department_user'),
(8,  'imar_user',       'imar@silivri.bel.tr',          '$2y$12$placeholder_hash_imar',       'İmar Şehircilik Yetkilisi',       'department_user'),
(9,  'zabita_user',     'zabita@silivri.bel.tr',        '$2y$12$placeholder_hash_zabita',     'Zabıta Yetkilisi',                'department_user'),
(10, 'mali_user',       'mali@silivri.bel.tr',          '$2y$12$placeholder_hash_mali',       'Mali Hizmetler Yetkilisi',        'department_user');

INSERT INTO actions (responsible_department_id, code, title, description, performance_indicators, category, start_year, end_year, status) VALUES

(1, 'A.1.H.1.1', 'Enerji Verimli Davranışlar Kazandırmak Amacıyla Bilinçlendirme Çalışmaları',
 'Bina sakinlerini enerji tasarrufu hakkında bilinçlendirerek enerji verimli davranışlar kazandırmak amacıyla eğitim, atölye, yarışma vb farkındalık oluşturacak etkinliklerin düzenlenmesi hedeflenmektedir.',
 'Düzenlenen eğitim ve etkinlik sayısı, Konut elektrik tüketimlerindeki değişim, Konut doğalgaz tüketimlerindeki değişim',
 'Enerji Verimliliği', 2024, 2030, 'ongoing'),

(2, 'A.1.H.2.1', 'Binalarda Enerji Verimliliği Sertifikasyonu',
 'Belediye hizmet binalarında ve konutlarda enerji verimliliği denetimi yapılması ve sertifikalandırılması.',
 'Sertifikalandırılan bina sayısı, Enerji tasarrufu oranı',
 'Enerji Verimliliği', 2024, 2030, 'ongoing'),

(2, 'A.1.H.2.2', 'Yenilenebilir Enerjiyle Çalışan Sokak Aydınlatma Uygulamaları',
 'Yenilenebilir enerjiyle çalışan sokak aydınlatmaları, enerji tasarrufu sağlamak ve karbon ayak izini azaltmak amacıyla tasarlanmış sistemlerdir. Park, bahçe ve sokak aydınlatmalarda güneş enerjili sistemlerinin uygulanması planlanmaktadır.',
 'Uygulama gerçekleştirilen aydınlatma direk sayısı, Elektrik tüketimindeki değişim (%)',
 'Yenilenebilir Enerji', 2024, 2030, 'ongoing'),

(2, 'A.1.H.2.3', 'Kamu Binalarında Güneş Enerjisi Sistemleri (GES) Kurulumu',
 'Belediye hizmet binalarının çatılarına güneş enerjisi sistemleri kurularak elektrik ihtiyacının yenilenebilir kaynaklardan karşılanması.',
 'Kurulan GES kapasitesi (kWp), Üretilen elektrik miktarı (kWh)',
 'Yenilenebilir Enerji', 2024, 2030, 'planned'),

(2, 'A.1.H.2.4', 'Binalarda Isı Yalıtımı ve Enerji Tasarrufu Uygulamaları',
 'Belediye binalarında ve toplu konut alanlarında ısı yalıtımı uygulamaları ile enerji tasarrufu sağlanması.',
 'Yalıtım uygulanan bina sayısı, Enerji tasarrufu oranı (%)',
 'Enerji Verimliliği', 2024, 2030, 'planned'),

(2, 'A.1.H.2.5', 'LED Aydınlatma Dönüşümü',
 'Sokak ve park aydınlatmalarında LED dönüşümü gerçekleştirilerek enerji tasarrufu sağlanması.',
 'Dönüştürülen aydınlatma sayısı, Enerji tasarrufu (kWh)',
 'Enerji Verimliliği', 2024, 2030, 'ongoing'),

(5, 'A.1.H.3.1', 'Bisikletli ve Yaya Ulaşımının Geliştirilmesi ve İyileştirilmesi',
 'Şehir içi bisiklet yolları ve yaya dostu güzergahların oluşturulması, paylaşımlı bisiklet sistemlerinin kurulması.',
 'Yapılan bisiklet yolu uzunluğu (km), Bisiklet istasyonu sayısı',
 'Ulaşım', 2024, 2030, 'ongoing'),

(5, 'A.1.H.3.2', 'Toplu Taşıma Araçlarında Elektrikli Dönüşüm',
 'Belediye otobüs ve servis araçlarının elektrikli araçlarla değiştirilmesi.',
 'Elektrikli araç sayısı, Azaltılan CO₂ emisyonu (ton)',
 'Ulaşım', 2024, 2030, 'ongoing'),

(5, 'A.1.H.3.3', 'Akıllı Trafik Yönetim Sistemi Kurulumu',
 'Trafik yoğunluğunu azaltmak için akıllı sinyalizasyon ve trafik yönetim sistemleri kurulması.',
 'Kurulan akıllı kavşak sayısı, Trafik sıkışıklığındaki azalma (%)',
 'Ulaşım', 2025, 2030, 'planned'),

(5, 'A.1.H.3.4', 'Şehirlerdeki Trafik Yoğunluğunun Azaltılması: Otomobil Kullanımının Azaltılması',
 'Paylaşımlı yolculuk, park et-devam et uygulamaları ile otomobil kullanımının azaltılması.',
 'Paylaşımlı yolculuk kullanıcı sayısı, Otomobil trafiğindeki azalma (%)',
 'Ulaşım', 2024, 2030, 'planned'),

(3, 'A.2.H.1.1', 'Kentsel Ormancılık ve Ağaçlandırma Çalışmaları',
 'Kent içi ve çevresi ağaçlandırma, yeşil koridor oluşturma ve kent ormanı geliştirme çalışmaları.',
 'Dikilen ağaç sayısı, Oluşturulan yeşil alan (m²)',
 'Yeşil Alan', 2024, 2030, 'ongoing'),

(3, 'A.2.H.1.2', 'Yağmur Suyu Hasadı ve Gri Su Geri Dönüşüm Sistemleri',
 'Park, bahçe ve kamu binalarında yağmur suyu toplama sistemleri kurulması ve gri su geri dönüşümü.',
 'Kurulan sistem sayısı, Toplanan su miktarı (m³)',
 'Su Yönetimi', 2025, 2030, 'planned'),

(3, 'A.2.H.1.3', 'Park ve Bahçelerde Akıllı Sulama Sistemleri',
 'Sensör tabanlı akıllı sulama sistemleri ile su tasarrufu sağlanması.',
 'Akıllı sulama sistemi kurulan alan (m²), Su tasarrufu (%)',
 'Su Yönetimi', 2024, 2030, 'ongoing'),

(3, 'A.2.H.1.4', 'Kentsel Tarım ve Toplum Bahçeleri Oluşturulması',
 'Atıl arazilerde toplum bahçeleri kurulması, kentsel tarım faaliyetlerinin desteklenmesi.',
 'Oluşturulan toplum bahçesi sayısı, Katılımcı sayısı',
 'Yeşil Alan', 2025, 2030, 'planned'),

(4, 'A.3.H.1.1', 'Organik Atık Kompostlama Tesisi Kurulumu',
 'Belediye sınırları içinde toplanan organik atıkların kompostlama tesisinde değerlendirilmesi.',
 'Kompostlanan atık miktarı (ton), Üretilen kompost miktarı (ton)',
 'Atık Yönetimi', 2025, 2028, 'planned'),

(4, 'A.3.H.1.2', 'Sıfır Atık Yönetim Sistemi Yaygınlaştırılması',
 'Kamu binalarında, okullarda ve mahallelerde sıfır atık uygulamalarının yaygınlaştırılması.',
 'Sıfır atık belgeli bina sayısı, Geri dönüşüm oranı (%)',
 'Atık Yönetimi', 2024, 2030, 'ongoing'),

(4, 'A.3.H.1.3', 'Ambalaj Atıklarının Kaynağında Ayrıştırılması',
 'Konut ve işyerlerinde ambalaj atıklarının kaynağında ayrıştırılması için altyapı ve bilinçlendirme.',
 'Ayrıştırma noktası sayısı, Toplanan ambalaj atığı (ton)',
 'Atık Yönetimi', 2024, 2030, 'ongoing'),

(4, 'A.3.H.1.4', 'Atık Yağ ve Elektronik Atık Toplama Sistemi',
 'Atık yağ ve e-atık toplama noktalarının oluşturulması ve düzenli toplama programı.',
 'Toplama noktası sayısı, Toplanan atık miktarı (ton)',
 'Atık Yönetimi', 2024, 2030, 'ongoing'),

(1, 'A.4.H.1.1', 'İklim Değişikliği Eylem Planı İzleme ve Değerlendirme',
 'SECAP eylem planının düzenli izlenmesi, raporlanması ve değerlendirilmesi.',
 'Hazırlanan rapor sayısı, İzleme toplantısı sayısı',
 'Yönetişim', 2024, 2030, 'ongoing'),

(1, 'A.4.H.1.2', 'İklim Değişikliği Farkındalık ve Eğitim Programları',
 'Okullarda, mahallelerde ve STK ile ortak iklim değişikliği farkındalık programları düzenlenmesi.',
 'Düzenlenen etkinlik sayısı, Katılımcı sayısı',
 'Bilinçlendirme', 2024, 2030, 'ongoing'),

(1, 'A.4.H.1.3', 'Paydaş İşbirliği ve Koordinasyon Platformu',
 'Belediye birimleri, özel sektör, STK ve üniversiteler arası koordinasyon platformu oluşturulması.',
 'Yapılan toplantı sayısı, İmzalanan protokol sayısı',
 'Yönetişim', 2024, 2030, 'ongoing'),

(7, 'A.5.H.1.1', 'Belediye Araç Filosunun Elektrikli/Hibrit Dönüşümü',
 'Belediye hizmet araçlarının elektrikli veya hibrit araçlarla değiştirilmesi.',
 'Dönüştürülen araç sayısı, Yakıt tasarrufu (lt), CO₂ azaltımı (ton)',
 'Ulaşım', 2024, 2030, 'ongoing'),

(7, 'A.5.H.1.2', 'Hizmet Binalarında Enerji Yönetim Sistemi Kurulması',
 'Belediye hizmet binalarında enerji izleme ve yönetim sistemleri kurulması.',
 'Sistem kurulan bina sayısı, Enerji tasarrufu (%)',
 'Enerji Verimliliği', 2025, 2030, 'planned'),

(8, 'A.6.H.1.1', 'Yeşil Bina Standartlarının Teşviki',
 'Yeni yapılarda yeşil bina standartlarının uygulanması için imar planları ve teşvik mekanizmaları.',
 'Yeşil bina belgeli yapı sayısı, Teşvik verilen proje sayısı',
 'Enerji Verimliliği', 2025, 2030, 'planned'),

(8, 'A.6.H.1.2', 'İmar Planlarında İklim Değişikliği Uyum Tedbirleri',
 'İmar planlarında yeşil alan oranı, yağmur suyu yönetimi ve ısı adası etkisi azaltma tedbirlerinin entegrasyonu.',
 'Güncellenen imar planı sayısı, Yeşil alan artış oranı (%)',
 'Yönetişim', 2025, 2030, 'planned'),

(9, 'A.7.H.1.1', 'Çevre Denetimi ve Hava Kalitesi İzleme',
 'Çevresel denetim faaliyetleri ve hava kalitesi izleme istasyonlarının kurulması.',
 'Yapılan denetim sayısı, Hava kalitesi ölçüm istasyonu sayısı',
 'Yönetişim', 2024, 2030, 'ongoing'),

(10, 'A.8.H.1.1', 'Yeşil Finansman ve AB Hibe Programlarına Başvuru',
 'İklim değişikliği projelerine AB fonları, yeşil tahvil ve ulusal hibelerden finansman sağlanması.',
 'Başvurulan proje sayısı, Sağlanan hibe miktarı (TL/EUR)',
 'Yönetişim', 2024, 2030, 'ongoing');

INSERT INTO action_departments (action_id, department_id, role_type) VALUES
(1, 1, 'primary'), (1, 6, 'contributor'), (1, 11, 'contributor'), (1, 12, 'contributor'),
(3, 2, 'primary'),
(7, 5, 'primary'), (7, 2, 'contributor'),
(8, 5, 'primary'), (8, 7, 'contributor'),
(11, 3, 'primary'), (11, 1, 'contributor'),
(16, 4, 'primary'), (16, 1, 'contributor'),
(19, 1, 'primary'),
(20, 1, 'primary'), (20, 6, 'contributor');

INSERT INTO activities (action_id, department_id, title, sub_actions, sort_order) VALUES
(1, 1, 'Bilinçlendirme Faaliyeti: Konutlarda enerji verimliliği',
 'Mahalle Evi Eğitimleri\nÇevre Haftası Etkinlikleri\nEnerji Verimliliği Haftası (Görsel Çalışmalar)', 1),
(1, 1, 'İklim ve Enerji Kılavuzu: Konutlarda enerji verimliliği temalı bilinçlendirme kitabı hazırlanması ve yayımlanması',
 'Konutlar için İklim ve Enerji Kılavuzu hazırlanması ve dağıtımı', 2),
(1, 6, 'Konutlarda enerji verimliliği konusunda bilinçlendirme çalışmalarına görsel destek verilmesi',
 'Post vb. yayımlanması', 3),
(1, 11, 'Konutlardaki elektrik tüketim miktarlarının bildirilmesi', '-', 4),

(3, 2, 'Aydınlatmalarda güneş enerjisi sistemleri kullanılması',
 'Sokak ve hizmet binalarında güneş enerjisiyle çalışan aydınlatmalar kurulması', 1),

(11, 3, 'Kent ağaçlandırma programı', 'Yıllık ağaçlandırma planlaması ve uygulaması', 1),
(11, 3, 'Park ve yeşil alan geliştirme', 'Yeni parklar ve peyzaj alanları oluşturulması', 2);

INSERT INTO kpis (action_id, activity_id, name, unit, description, baseline_value, target_value, target_label, baseline_year, is_cumulative) VALUES
(1, 1, 'Faaliyet sayısı',        'Adet',     'Düzenlenen eğitim ve etkinlik sayısı',             0, 2,    '2 adet faaliyet', 2024, 0),
(1, 1, 'Ulaşılan kişi sayısı',   'Adet',     'Bilinçlendirme faaliyetine katılan kişi sayısı',   0, 1000, '1000 kişi',       2024, 1),
(1, 2, 'Yayın sayısı',           'Adet',     'Hazırlanan kılavuz ve yayın sayısı',               0, 1,    '1 adet yayın',    2024, 0),
(1, 2, 'Ulaşılan kişi sayısı',   'Adet',     'Kılavuz dağıtılan kişi sayısı',                    0, 300,  '300 kişi',        2024, 1),
(1, 3, 'Yapılan çalışma sayısı',  'Adet',     'Baskı, post, haber vb.',                           0, 2,    '2 adet çalışma',  2024, 0),
(1, 4, 'Konut abonman sayısı',    'Adet',     'Konut elektrik abonman sayısı',                     0, NULL, NULL,              2024, 0),
(1, 4, 'Konut toplam elektrik tüketimi', 'kWh/yıl', 'Konutlardaki toplam elektrik tüketim verisi', 0, NULL, NULL,            2024, 0),

(2, NULL, 'Sertifikalandırılan bina sayısı',  'Adet', 'Enerji kimlik belgesi alan binalar',    0, 50,   '50 bina',   2023, 1),

(3, 5, 'Uygulama yapılan GES sayısı',            'Adet', 'GES uygulanan aydınlatma noktası',           0, 30,   '30 adet GES uygulaması', 2024, 0),
(3, 5, 'Uygulanan GES kapasitesi',               'kWp',  'Güneş enerjisi sistemi kapasitesi',          0, 500,  NULL,                     2024, 1),
(3, 5, 'Toplam GES li aydınlatma sayısı',         'Adet', 'Kümülatif GES aydınlatma',                  0, 2000, NULL,                     2024, 1),
(3, 5, 'Uygulama yapılan LED sayısı',             'Adet', 'LED dönüşümü yapılan aydınlatma',           0, NULL, NULL,                     2024, 0),
(3, 5, 'LED li aydınlatma sayısı (kümülatif)',     'Adet', 'Kümülatif LED aydınlatma',                  0, NULL, NULL,                     2024, 1),
(3, 5, 'Toplam Aydınlatma Sayısı (kümülatif)',    'Adet', 'Tüm aydınlatma sayısı toplam',              0, NULL, NULL,                     2024, 1),

(4, NULL, 'Kurulan GES kapasitesi',       'kWp',  'Çatı GES kapasitesi',                0, 200,   NULL, 2024, 1),
(4, NULL, 'Üretilen elektrik miktarı',    'kWh',  'GES ile üretilen yıllık elektrik',    0, NULL,  NULL, 2024, 0),

(7, NULL, 'Yapılan bisiklet yolu uzunluğu', 'km',   'Yeni bisiklet yolu',    0, 10,  NULL, 2024, 1),
(7, NULL, 'Bisiklet istasyonu sayısı',       'Adet', 'Paylaşımlı bisiklet',   0, 20,  NULL, 2024, 1),

(8, NULL, 'Elektrikli otobüs sayısı',    'Adet',     'Aktif elektrikli otobüs',                0, 30,   NULL, 2023, 1),
(8, NULL, 'Azaltılan CO₂ emisyonu',      'ton/yıl',  'Elektrikli araçlarla sağlanan azaltım',  0, 2500, NULL, 2023, 0),

(11, 6, 'Dikilen ağaç sayısı',       'Adet', 'Yıllık ağaçlandırma miktarı',     0, 5000,  NULL, 2023, 1),
(11, 7, 'Oluşturulan yeşil alan',    'm²',   'Yeni parklar ve peyzaj alanları',  0, 50000, NULL, 2023, 1),

(15, NULL, 'Kompostlanan organik atık miktarı', 'ton',  'Yıllık kompost üretimi',  0, 1000, NULL, 2024, 0),
(15, NULL, 'Üretilen kompost miktarı',          'ton',  'Kompost çıktısı',         0, 500,  NULL, 2024, 0),

(16, NULL, 'Sıfır atık belgeli bina sayısı', 'Adet', 'Belgelendirilen binalar',  0, 50,  NULL, 2024, 1),
(16, NULL, 'Geri dönüşüm oranı',            '%',    'Toplam geri dönüşüm oranı', 0, 35,  NULL, 2024, 0),

(19, NULL, 'Hazırlanan rapor sayısı',    'Adet', 'SECAP izleme raporları',         0, 2,  NULL, 2024, 0),
(19, NULL, 'İzleme toplantısı sayısı',   'Adet', 'Paydaş izleme toplantıları',     0, 4,  NULL, 2024, 0),

(20, NULL, 'Düzenlenen etkinlik sayısı',  'Adet', 'Eğitim ve farkındalık etkinlikleri', 0, 10, NULL, 2024, 0),
(20, NULL, 'Katılımcı sayısı',            'Adet', 'Toplam katılımcı',                    0, 2000, NULL, 2024, 1);

CREATE TABLE audit_log (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id     INT UNSIGNED             COMMENT 'İşlemi yapan kullanıcı (login_fail için NULL olabilir)',
    action      ENUM('create','update','delete','verify','unverify','login','login_fail','export','access_denied','role_change','password_reset','status_change') NOT NULL,
    entity_type VARCHAR(50)     NOT NULL COMMENT 'Etkilenen tablo: data_entries, actions, users, kpis',
    entity_id   INT UNSIGNED    NOT NULL COMMENT 'Etkilenen kaydın ID si',
    old_value   JSON                     COMMENT 'Değişiklik öncesi değer',
    new_value   JSON                     COMMENT 'Değişiklik sonrası değer',
    ip_address  VARCHAR(45)              COMMENT 'İstek IP adresi',
    created_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    INDEX idx_audit_entity (entity_type, entity_id),
    INDEX idx_audit_user   (user_id),
    INDEX idx_audit_time   (created_at),
    INDEX idx_audit_action (action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci
  COMMENT='Denetim kaydı — kritik işlem logları';

CREATE TABLE login_attempts (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    ip_address   VARCHAR(45)  NOT NULL,
    username     VARCHAR(80)  NOT NULL COMMENT 'Denenen kullanıcı adı',
    success      TINYINT(1)   NOT NULL DEFAULT 0,
    attempted_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    INDEX idx_login_ip_time (ip_address, attempted_at),
    INDEX idx_login_user_ip_time (username, ip_address, attempted_at),
    INDEX idx_login_user_time (username, attempted_at),
    INDEX idx_login_time    (attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci
  COMMENT='Giriş denemeleri — brute force koruması';

CREATE INDEX idx_entries_dept_year ON data_entries (department_id, year);
