<?php

declare(strict_types=1);

require_once __DIR__ . '/../uygulama/baslat.php';
Auth::requireSuperAdmin();

$pdo       = Database::getInstance()->getConnection();
$pageTitle = 'Denetim Günlüğü';
$activeNav = 'audit_log';

$actionLabels = [
    'create'         => ['label' => 'Oluşturma',          'cls' => 'success'],
    'update'         => ['label' => 'Güncelleme',         'cls' => 'info'],
    'delete'         => ['label' => 'Silme',              'cls' => 'danger'],
    'soft_delete'    => ['label' => 'Çöp Kutusuna Taşıma', 'cls' => 'danger'],
    'restore'        => ['label' => 'Geri Alma',          'cls' => 'success'],
    'verify'         => ['label' => 'Doğrulama',          'cls' => 'success'],
    'unverify'       => ['label' => 'Doğrulama Kaldırma', 'cls' => 'warning'],
    'login'          => ['label' => 'Giriş',              'cls' => 'secondary'],
    'login_fail'     => ['label' => 'Başarısız Giriş',    'cls' => 'warning'],
    'export'         => ['label' => 'Dışa Aktarım',       'cls' => 'info'],
    'access_denied'  => ['label' => 'Erişim Reddedildi',  'cls' => 'danger'],
    'role_change'    => ['label' => 'Rol Değişimi',       'cls' => 'warning'],
    'password_reset' => ['label' => 'Şifre Sıfırlama',    'cls' => 'warning'],
    'status_change'  => ['label' => 'Durum Değişimi',     'cls' => 'info'],
    'notify'         => ['label' => 'Bildirim',           'cls' => 'secondary'],
    'session_kill'   => ['label' => 'Oturum Kapatma',     'cls' => 'danger'],
    'file_upload'    => ['label' => 'Dosya Yükleme',      'cls' => 'info'],
    'file_download'  => ['label' => 'Dosya İndirme',      'cls' => 'info'],
    'mail_send'      => ['label' => 'E-posta Gönderimi',  'cls' => 'secondary'],
];

$entityLabels = [
    'actions'      => 'Eylem',
    'activities'   => 'Faaliyet',
    'kpis'         => 'KPI',
    'data_entries' => 'Veri Girişi',
    'entry_attachments' => 'Kanıt Dosyası',
    'users'        => 'Kullanıcı',
    'departments'  => 'Müdürlük',
    'auth'         => 'Kimlik Doğrulama',
    'admin_area'   => 'Yönetim Alanı',
    'super_admin_area' => 'Süper Admin Alanı',
    'notifications' => 'Bildirim',
    'email_queue' => 'E-posta Kuyruğu',
    'system_backups' => 'Sistem Yedeği',
    'exports' => 'Rapor Çıktısı',
    'operations' => 'Operasyon Merkezi',
];

$fieldLabels = [
    'responsible_department_id' => 'Sorumlu Müdürlük',
    'contributor_depts' => 'Ek Sorumlu Müdürlükler',
    'department_id' => 'Müdürlük',
    'code' => 'Kod',
    'title' => 'Başlık',
    'description' => 'Açıklama',
    'performance_indicators' => 'Performans Göstergeleri',
    'category' => 'Kategori',
    'start_year' => 'Başlangıç Yılı',
    'end_year' => 'Bitiş Yılı',
    'status' => 'Durum',
    'workflow_status' => 'Veri Durumu',
    'is_verified' => 'Onay',
    'review_comment' => 'Değerlendirme Yorumu',
    'verified_by' => 'Onaylayan',
    'kpi_id' => 'KPI',
    'action_id' => 'Eylem',
    'year' => 'Yıl',
    'value' => 'Değer',
    'notes' => 'Açıklama',
    'attachments_added' => 'Eklenen Kanıt',
    'username' => 'Kullanıcı Adı',
    'full_name' => 'Ad Soyad',
    'email' => 'E-posta',
    'role' => 'Rol',
    'is_active' => 'Aktiflik',
    'filename' => 'Dosya',
    'status' => 'Durum',
    'message' => 'Mesaj',
    'test' => 'Test Türü',
    'recipient' => 'Alıcı',
    'storage_path' => 'Depolama Yolu',
    'file_size' => 'Dosya Boyutu',
];

$roleLabels = [
    'super_admin' => 'Süper Admin',
    'admin' => 'İklim Admin',
    'climate_admin' => 'İklim Admin',
    'department_user' => 'Müdürlük Kullanıcısı',
];

$filterUser    = (int)($_GET['user_id'] ?? 0);
$filterEntity  = trim((string)($_GET['entity_type'] ?? ''));
$filterAction  = trim((string)($_GET['action'] ?? ''));
$filterDateFrom = trim((string)($_GET['date_from'] ?? ''));
$filterDateTo   = trim((string)($_GET['date_to'] ?? ''));
$filterIp      = trim((string)($_GET['ip'] ?? ''));
$filterSession = trim((string)($_GET['session_id'] ?? ''));
$filterSearch  = trim((string)($_GET['q'] ?? ''));
$perPage       = (int)($_GET['per_page'] ?? 50);
if (!in_array($perPage, [50, 100, 200], true)) $perPage = 50;
$page = max(1, (int)($_GET['page'] ?? 1));

$where  = [];
$params = [];

if ($filterUser > 0)            { $where[] = 'al.user_id = :uid';        $params[':uid']  = $filterUser; }
if ($filterEntity !== '')       { $where[] = 'al.entity_type = :etype';  $params[':etype'] = $filterEntity; }
if ($filterAction !== '')       { $where[] = 'al.action = :act';         $params[':act']   = $filterAction; }
if ($filterDateFrom !== '')     { $where[] = 'al.created_at >= :dfrom';  $params[':dfrom'] = $filterDateFrom . ' 00:00:00'; }
if ($filterDateTo !== '')       { $where[] = 'al.created_at <= :dto';    $params[':dto']   = $filterDateTo   . ' 23:59:59'; }
$likeEscape = static function (string $s): string {
    return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $s);
};

if ($filterIp !== '') {
    $where[] = "al.ip_address LIKE :ip ESCAPE '\\\\'";
    $params[':ip'] = '%' . $likeEscape($filterIp) . '%';
}
if ($filterSession !== '') {
    $where[] = "al.session_id LIKE :sid ESCAPE '\\\\'";
    $params[':sid'] = '%' . $likeEscape($filterSession) . '%';
}

$filterSearchTooShort = false;
$filterDeepSearch     = isset($_GET['deep_search']) && $_GET['deep_search'] === '1';
$searchLen = function_exists('mb_strlen')
    ? mb_strlen($filterSearch)
    : strlen($filterSearch);
if ($filterSearch !== '') {
    if ($searchLen < 3) {
        $filterSearchTooShort = true;
    } else {
        $escaped = $likeEscape($filterSearch);
        $pattern = '%' . $escaped . '%';
        
        if ($filterDeepSearch) {
            $where[] = "(al.old_value LIKE :q1 ESCAPE '\\\\' OR al.new_value LIKE :q2 ESCAPE '\\\\' OR al.actor_full_name LIKE :q3 ESCAPE '\\\\' OR al.request_uri LIKE :q4 ESCAPE '\\\\')";
            $params[':q1'] = $pattern;
            $params[':q2'] = $pattern;
            $params[':q3'] = $pattern;
            $params[':q4'] = $pattern;
        } else {
            $where[] = "(al.actor_full_name LIKE :q1 ESCAPE '\\\\' OR al.request_uri LIKE :q2 ESCAPE '\\\\')";
            $params[':q1'] = $pattern;
            $params[':q2'] = $pattern;
        }
    }
}

$whereSql = empty($where) ? '' : 'WHERE ' . implode(' AND ', $where);

$isCsvExport = isset($_GET['export']) && $_GET['export'] === 'csv';

if ($isCsvExport) {
    $sql = "SELECT al.*, u.full_name AS user_full_name, u.username AS user_username
            FROM audit_log al
            LEFT JOIN users u ON u.id = al.user_id
            {$whereSql}
            ORDER BY al.created_at DESC
            LIMIT 10000";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $activeFilterKeys = [];
    foreach ([
        'user_id' => $filterUser, 'entity_type' => $filterEntity, 'action' => $filterAction,
        'date_from' => $filterDateFrom, 'date_to' => $filterDateTo, 'ip' => $filterIp,
        'session_id' => $filterSession, 'q' => $filterSearch,
    ] as $fk => $fv) {
        if ($fv !== '' && $fv !== 0) {
            $activeFilterKeys[] = $fk;
        }
    }
    AuditLog::logExport($pdo, 'audit_log', [
        'filter_keys' => $activeFilterKeys,
        'deep_search' => $filterDeepSearch,
        'row_count'   => count($rows),
    ]);

    while (ob_get_level() > 0) { ob_end_clean(); }

    $csvSafe = static function ($v): string {
        $s = (string) ($v ?? '');
        if ($s !== '' && in_array($s[0], ['=', '+', '-', '@'], true)) {
            $s = "'" . $s;
        }
        return $s;
    };

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="denetim_gunlugu_' . date('Ymd_His') . '.csv"');
    header('X-Content-Type-Options: nosniff');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Kayıt No', 'Tarih', 'Kullanıcı', 'Rol', 'IP Adresi', 'İşlem', 'Kayıt Türü', 'İlgili Kayıt No', 'İşlem Yöntemi', 'Sayfa/İşlem Adresi', 'Oturum Kimliği', 'Tarayıcı Bilgisi', 'Eski Değer', 'Yeni Değer']);
    foreach ($rows as $r) {
        fputcsv($out, array_map($csvSafe, [
            $r['id'],
            $r['created_at'],
            $r['actor_full_name'] ?: ($r['user_full_name'] ?? '-'),
            audit_role_label($r['actor_role'] ?? null, $roleLabels),
            $r['ip_address'] ?? '-',
            $actionLabels[$r['action']]['label'] ?? $r['action'],
            $entityLabels[$r['entity_type']] ?? $r['entity_type'],
            $r['entity_id'],
            $r['request_method'] ?? '-',
            $r['request_uri'] ?? '-',
            $r['session_id'] ?? '-',
            $r['user_agent'] ?? '-',
            $r['old_value'] ?? '',
            $r['new_value'] ?? '',
        ]));
    }
    fclose($out);
    exit;
}

$countSql = "SELECT COUNT(*) FROM audit_log al {$whereSql}";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalRows = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($totalRows / $perPage));
if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $perPage;

$sql = "SELECT al.*, u.full_name AS user_full_name, u.username AS user_username
        FROM audit_log al
        LEFT JOIN users u ON u.id = al.user_id
        {$whereSql}
        ORDER BY al.created_at DESC
        LIMIT :lim OFFSET :off";
$stmt = $pdo->prepare($sql);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
}
$stmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':off', $offset, PDO::PARAM_INT);
$stmt->execute();
$logs = $stmt->fetchAll();

$users = $pdo->query(
    'SELECT u.id, u.full_name, u.username
     FROM users u
     WHERE EXISTS (SELECT 1 FROM audit_log al WHERE al.user_id = u.id)
     ORDER BY u.full_name'
)->fetchAll();

$entityTypes = $pdo->query('SELECT DISTINCT entity_type FROM audit_log ORDER BY entity_type')->fetchAll(PDO::FETCH_COLUMN);

function audit_parse_json($raw): array
{
    if ($raw === null || $raw === '') return [];
    $decoded = json_decode((string) $raw, true);
    return is_array($decoded) ? $decoded : ['_raw' => $raw];
}

function audit_diff(array $old, array $new): array
{
    $hidden = ['_audit_action', '_request_method', '_request_uri', '_actor_role'];
    $keys = array_unique(array_merge(array_keys($old), array_keys($new)));
    sort($keys);
    $rows = [];
    foreach ($keys as $k) {
        if (in_array($k, $hidden, true)) continue;
        $o = $old[$k] ?? null;
        $n = $new[$k] ?? null;
        if (!audit_changed($o, $n)) {
            continue;
        }
        $rows[] = [
            'key'     => $k,
            'old'     => $o,
            'new'     => $n,
            'changed' => true,
        ];
    }
    return $rows;
}

function audit_changed($a, $b): bool
{
    if (is_array($a) || is_array($b)) return json_encode($a) !== json_encode($b);
    return (string) $a !== (string) $b;
}

function audit_role_label(?string $role, array $roleLabels): string
{
    $role = (string) ($role ?? '');
    if ($role === '') {
        return '—';
    }
    return $roleLabels[$role] ?? $role;
}

function audit_fmt($v, string $key = '', array $roleLabels = []): string
{
    if ($v === null) return '<span class="text-muted">—</span>';
    if ($key === 'role' || $key === 'actor_role') {
        return htmlspecialchars(audit_role_label((string) $v, $roleLabels), ENT_QUOTES, 'UTF-8');
    }
    if ($key === 'is_active') {
        return ((int) $v === 1) ? 'Aktif' : 'Pasif';
    }
    if ($key === 'workflow_status') {
        $workflowLabels = [
            'submitted' => 'Onay Bekliyor',
            'needs_revision' => 'Düzeltme İstendi',
            'approved' => 'Onaylı',
            'rejected' => 'Reddedildi',
        ];
        return htmlspecialchars($workflowLabels[(string) $v] ?? (string) $v, ENT_QUOTES, 'UTF-8');
    }
    if (is_bool($v)) return $v ? 'Evet' : 'Hayır';
    if (is_array($v)) return '<code class="small">' . htmlspecialchars(json_encode($v, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), ENT_QUOTES, 'UTF-8') . '</code>';
    $s = (string) $v;
    if ($s === '') return '<span class="text-muted">(boş)</span>';
    return nl2br(htmlspecialchars($s, ENT_QUOTES, 'UTF-8'));
}

function audit_field_label(string $key, array $fieldLabels): string
{
    return $fieldLabels[$key] ?? str_replace('_', ' ', $key);
}

function audit_summary(array $row, array $old, array $new, array $entityLabels, array $actionLabels): string
{
    $action = $actionLabels[$row['action']]['label'] ?? (string) $row['action'];
    $entity = $entityLabels[$row['entity_type']] ?? (string) $row['entity_type'];
    $entityId = !empty($row['entity_id']) ? '#' . (int) $row['entity_id'] : '';

    $name = $new['title']
        ?? $new['name']
        ?? $new['username']
        ?? $new['filename']
        ?? $old['title']
        ?? $old['name']
        ?? $old['username']
        ?? null;

    $parts = [$action, trim($entity . ' ' . $entityId)];
    if ($name !== null && !is_array($name) && (string) $name !== '') {
        $parts[] = mb_strimwidth((string) $name, 0, 80, '…');
    }

    return implode(' · ', array_filter($parts));
}

require_once APP_ROOT . '/uygulama/yerlesim/ust.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h5 class="fw-bold mb-0"><i class="bi bi-shield-check me-2 text-primary"></i>Denetim Günlüğü</h5>
        <small class="text-muted">Sistem işlemleri, kullanıcı hareketleri ve güvenlik kayıtları · Toplam <?= number_format($totalRows, 0, ',', '.') ?> kayıt · Sayfa <?= $page ?>/<?= $totalPages ?></small>
    </div>
    <div class="d-flex gap-2">
        <a class="btn btn-outline-success btn-sm"
           href="?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>">
            <i class="bi bi-download me-1"></i>CSV Olarak İndir
        </a>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label mb-1 small">Kullanıcı</label>
                <select name="user_id" class="form-select form-select-sm">
                    <option value="0">Tümü</option>
                    <?php foreach ($users as $u): ?>
                    <option value="<?= $u['id'] ?>" <?= $filterUser === (int) $u['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($u['full_name'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label mb-1 small">Kayıt Türü</label>
                <select name="entity_type" class="form-select form-select-sm">
                    <option value="">Tümü</option>
                    <?php foreach ($entityTypes as $et): ?>
                    <option value="<?= htmlspecialchars($et, ENT_QUOTES, 'UTF-8') ?>" <?= $filterEntity === $et ? 'selected' : '' ?>>
                        <?= htmlspecialchars($entityLabels[$et] ?? $et, ENT_QUOTES, 'UTF-8') ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label mb-1 small">İşlem Türü</label>
                <select name="action" class="form-select form-select-sm">
                    <option value="">Tümü</option>
                    <?php foreach ($actionLabels as $k => $v): ?>
                    <option value="<?= $k ?>" <?= $filterAction === $k ? 'selected' : '' ?>><?= $v['label'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label mb-1 small">Başlangıç Tarihi</label>
                <input type="date" name="date_from" value="<?= htmlspecialchars($filterDateFrom, ENT_QUOTES, 'UTF-8') ?>" class="form-control form-control-sm">
            </div>
            <div class="col-md-2">
                <label class="form-label mb-1 small">Bitiş Tarihi</label>
                <input type="date" name="date_to" value="<?= htmlspecialchars($filterDateTo, ENT_QUOTES, 'UTF-8') ?>" class="form-control form-control-sm">
            </div>
            <div class="col-md-2">
                <label class="form-label mb-1 small">IP Adresi</label>
                <input type="text" name="ip" value="<?= htmlspecialchars($filterIp, ENT_QUOTES, 'UTF-8') ?>" class="form-control form-control-sm" placeholder="örn: 192.168">
            </div>
            <div class="col-md-3">
                <label class="form-label mb-1 small">Oturum Kimliği</label>
                <input type="text" name="session_id" value="<?= htmlspecialchars($filterSession, ENT_QUOTES, 'UTF-8') ?>" class="form-control form-control-sm" placeholder="Oturum kimliği parçası">
            </div>
            <div class="col-md-5">
                <label class="form-label mb-1 small">Arama (kullanıcı, sayfa/işlem adresi)</label>
                <input type="text" name="q" value="<?= htmlspecialchars($filterSearch, ENT_QUOTES, 'UTF-8') ?>" class="form-control form-control-sm" placeholder="en az 3 karakter...">
                <div class="form-check form-check-inline mt-1 small">
                    <input class="form-check-input" type="checkbox" name="deep_search" value="1" id="deepSearchToggle" <?= $filterDeepSearch ? 'checked' : '' ?>>
                    <label class="form-check-label text-muted" for="deepSearchToggle">Eski/yeni değer içeriğinde de ara (daha yavaş çalışabilir)</label>
                </div>
                <?php if ($filterSearchTooShort): ?>
                <div class="form-text text-warning small mt-1">Arama en az 3 karakter olmalı.</div>
                <?php endif; ?>
            </div>
            <div class="col-md-2">
                <label class="form-label mb-1 small">Sayfa Başına Kayıt</label>
                <select name="per_page" class="form-select form-select-sm">
                    <?php foreach ([50, 100, 200] as $pp): ?>
                    <option value="<?= $pp ?>" <?= $perPage === $pp ? 'selected' : '' ?>><?= $pp ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button class="btn btn-primary btn-sm flex-fill"><i class="bi bi-funnel me-1"></i>Filtrele</button>
                <a href="?" class="btn btn-outline-secondary btn-sm"><i class="bi bi-x"></i></a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0 yazi-orta align-middle">
            <thead class="table-light">
                <tr>
                    <th style="width:42px;"></th>
                    <th>Tarih</th>
                    <th>Kullanıcı</th>
                    <th>Rol</th>
                    <th>İşlem</th>
                    <th>Kayıt</th>
                    <th>Özet</th>
                    <th>IP Adresi</th>
                    <th>Sayfa/İşlem Adresi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                <tr>
                    <td colspan="9" class="text-center text-muted py-4">Kayıt bulunamadı.</td>
                </tr>
                <?php endif; ?>
                <?php foreach ($logs as $row):
                    $actCfg = $actionLabels[$row['action']] ?? ['label' => $row['action'], 'cls' => 'secondary'];
                    $entLbl = $entityLabels[$row['entity_type']] ?? $row['entity_type'];
                    $old    = audit_parse_json($row['old_value']);
                    $new    = audit_parse_json($row['new_value']);
                    $diff   = audit_diff($old, $new);
                    $summary = audit_summary($row, $old, $new, $entityLabels, $actionLabels);
                ?>
                <tr data-bs-toggle="collapse" data-bs-target="#audit-row-<?= (int) $row['id'] ?>"
                    aria-expanded="false" style="cursor:pointer;">
                    <td><i class="bi bi-chevron-down text-muted small"></i></td>
                    <td class="text-nowrap"><small><?= htmlspecialchars($row['created_at'], ENT_QUOTES, 'UTF-8') ?></small></td>
                    <td>
                        <div class="fw-medium"><?= htmlspecialchars($row['actor_full_name'] ?? $row['user_full_name'] ?? '—', ENT_QUOTES, 'UTF-8') ?></div>
                        <?php if (!empty($row['user_username'])): ?>
                        <small class="text-muted">@<?= htmlspecialchars($row['user_username'], ENT_QUOTES, 'UTF-8') ?></small>
                        <?php endif; ?>
                    </td>
                    <td><small class="text-muted"><?= htmlspecialchars(audit_role_label($row['actor_role'] ?? null, $roleLabels), ENT_QUOTES, 'UTF-8') ?></small></td>
                    <td><span class="badge bg-<?= $actCfg['cls'] ?>-subtle text-<?= $actCfg['cls'] ?>"><?= $actCfg['label'] ?></span></td>
                    <td>
                        <span class="fw-medium"><?= htmlspecialchars($entLbl, ENT_QUOTES, 'UTF-8') ?></span>
                        <?php if (!empty($row['entity_id'])): ?>
                        <small class="text-muted">#<?= (int) $row['entity_id'] ?></small>
                        <?php endif; ?>
                    </td>
                    <td><small><?= htmlspecialchars($summary, ENT_QUOTES, 'UTF-8') ?></small></td>
                    <td><small><?= htmlspecialchars($row['ip_address'] ?? '—', ENT_QUOTES, 'UTF-8') ?></small></td>
                    <td><small class="text-muted text-truncate d-inline-block" style="max-width: 260px;" title="<?= htmlspecialchars($row['request_uri'] ?? '', ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($row['request_uri'] ?? '—', ENT_QUOTES, 'UTF-8') ?></small></td>
                </tr>
                <tr class="collapse" id="audit-row-<?= (int) $row['id'] ?>">
                    <td colspan="9" class="bg-body-tertiary">
                        <div class="p-3">
                            <div class="row g-3 mb-3">
                                <div class="col-md-3">
                                    <div class="small text-muted">Kullanıcı</div>
                                    <div class="fw-medium"><?= htmlspecialchars($row['actor_full_name'] ?? '-', ENT_QUOTES, 'UTF-8') ?></div>
                                </div>
                                <div class="col-md-2">
                                    <div class="small text-muted">IP Adresi</div>
                                    <div><?= htmlspecialchars($row['ip_address'] ?? '-', ENT_QUOTES, 'UTF-8') ?></div>
                                </div>
                                <div class="col-md-2">
                                    <div class="small text-muted">İşlem Yöntemi</div>
                                    <div><code><?= htmlspecialchars($row['request_method'] ?? '-', ENT_QUOTES, 'UTF-8') ?></code></div>
                                </div>
                                <div class="col-md-5">
                                    <div class="small text-muted">Sayfa/İşlem Adresi</div>
                                    <div class="text-truncate"><code><?= htmlspecialchars($row['request_uri'] ?? '-', ENT_QUOTES, 'UTF-8') ?></code></div>
                                </div>
                                <div class="col-md-6">
                                    <div class="small text-muted">Tarayıcı Bilgisi</div>
                                    <div class="text-truncate small"><?= htmlspecialchars($row['user_agent'] ?? '-', ENT_QUOTES, 'UTF-8') ?></div>
                                </div>
                                <div class="col-md-3">
                                    <div class="small text-muted">Geldiği Sayfa</div>
                                    <div class="text-truncate small"><?= htmlspecialchars($row['referer'] ?? '-', ENT_QUOTES, 'UTF-8') ?></div>
                                </div>
                                <div class="col-md-3">
                                    <div class="small text-muted">Oturum Kimliği</div>
                                    <div class="text-truncate small"><code><?= htmlspecialchars($row['session_id'] ?? '-', ENT_QUOTES, 'UTF-8') ?></code></div>
                                </div>
                            </div>

                            <?php if (!empty($diff)): ?>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered mb-0" style="font-size:.82rem;">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width:22%;">Alan</th>
                                            <th style="width:39%;">Eski Değer</th>
                                            <th style="width:39%;">Yeni Değer</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($diff as $d): ?>
                                        <tr class="<?= $d['changed'] ? 'table-warning' : '' ?>">
                                            <td class="fw-medium"><?= htmlspecialchars(audit_field_label((string) $d['key'], $fieldLabels), ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= audit_fmt($d['old'], (string) $d['key'], $roleLabels) ?></td>
                                            <td><?= audit_fmt($d['new'], (string) $d['key'], $roleLabels) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php else: ?>
                            <div class="text-muted small">Değişen alan yok; bu kayıt işlem bağlamını izlemek için tutuldu.</div>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($totalPages > 1):
    $baseQuery = $_GET;
    unset($baseQuery['page']);
    $pageUrl = function (int $p) use ($baseQuery) {
        return '?' . http_build_query(array_merge($baseQuery, ['page' => $p]));
    };
?>
<nav class="mt-3">
    <ul class="pagination pagination-sm mb-0 justify-content-center">
        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
            <a class="page-link" href="<?= $pageUrl(max(1, $page - 1)) ?>">«</a>
        </li>
        <?php
        $start = max(1, $page - 3);
        $end   = min($totalPages, $page + 3);
        if ($start > 1): ?>
            <li class="page-item"><a class="page-link" href="<?= $pageUrl(1) ?>">1</a></li>
            <?php if ($start > 2): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
        <?php endif; ?>
        <?php for ($p = $start; $p <= $end; $p++): ?>
        <li class="page-item <?= $p === $page ? 'active' : '' ?>">
            <a class="page-link" href="<?= $pageUrl($p) ?>"><?= $p ?></a>
        </li>
        <?php endfor; ?>
        <?php if ($end < $totalPages): ?>
            <?php if ($end < $totalPages - 1): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
            <li class="page-item"><a class="page-link" href="<?= $pageUrl($totalPages) ?>"><?= $totalPages ?></a></li>
        <?php endif; ?>
        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
            <a class="page-link" href="<?= $pageUrl(min($totalPages, $page + 1)) ?>">»</a>
        </li>
    </ul>
</nav>
<?php endif; ?>

<?php require_once APP_ROOT . '/uygulama/yerlesim/alt.php'; ?>
