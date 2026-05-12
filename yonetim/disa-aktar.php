<?php

declare(strict_types=1);

require_once __DIR__ . '/../uygulama/baslat.php';
require_once APP_ROOT . '/uygulama/yardimcilar/excel-aktarim.php';
require_once APP_ROOT . '/uygulama/yardimcilar/pdf-rapor.php';
Auth::requireAdmin();

$pdo       = Database::getInstance()->getConnection();
$pageTitle = 'Dışa Aktar';
$activeNav = 'export';

$requestData = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
$filterYear = (int)($requestData['year'] ?? date('Y'));
$filterDept = (int)($requestData['dept_id'] ?? 0);
$filterWorkflow = (string)($requestData['workflow_status'] ?? '');
$allowedWorkflow = ['', 'submitted', 'needs_revision', 'approved', 'rejected'];
if (!in_array($filterWorkflow, $allowedWorkflow, true)) $filterWorkflow = '';

$statusLabels = [
    'planned'   => 'Planlandı',
    'ongoing'   => 'Devam Ediyor',
    'completed' => 'Tamamlandı',
    'cancelled' => 'İptal Edildi',
];
$statusStyles = [
    'planned'   => 'statusPlanned',
    'ongoing'   => 'statusOngoing',
    'completed' => 'statusCompleted',
    'cancelled' => 'statusPlanned',
];
$workflowLabels = [
    '' => 'Tümü',
    'submitted' => 'Onay Bekliyor',
    'needs_revision' => 'Düzeltme İstendi',
    'approved' => 'Onaylı',
    'rejected' => 'Reddedildi',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['download_pdf'])) {
    Csrf::check();

    $stmt = $pdo->prepare(
        "SELECT de.year, de.value, de.workflow_status,
                a.code AS action_code, a.title AS action_title,
                d.name AS dept_name, k.name AS kpi_name, k.unit,
                (SELECT COUNT(*) FROM entry_attachments ea WHERE ea.data_entry_id = de.id AND ea.deleted_at IS NULL) AS attachment_count
         FROM data_entries de
         JOIN kpis k ON k.id = de.kpi_id
         JOIN actions a ON a.id = de.action_id
         JOIN departments d ON d.id = de.department_id
         WHERE de.deleted_at IS NULL
           AND a.deleted_at IS NULL
           AND (:year_filter_on = 0 OR de.year = :year_value)
           AND (:dept_filter_on = 0 OR de.department_id = :dept_id)
           AND (:workflow_filter_on = 0 OR de.workflow_status = :workflow_value)
         ORDER BY d.name, a.code, k.name, de.year DESC
         LIMIT 240"
    );
    $stmt->execute([
        ':year_filter_on' => $filterYear > 0 ? 1 : 0,
        ':year_value' => $filterYear,
        ':dept_filter_on' => $filterDept > 0 ? 1 : 0,
        ':dept_id' => $filterDept,
        ':workflow_filter_on' => $filterWorkflow !== '' ? 1 : 0,
        ':workflow_value' => $filterWorkflow,
    ]);
    $pdfRows = [];
    foreach ($stmt->fetchAll() as $row) {
        $pdfRows[] = [
            'year' => (string)$row['year'],
            'action' => $row['action_code'] . ' ' . $row['action_title'],
            'dept' => $row['dept_name'],
            'kpi' => $row['kpi_name'],
            'value' => number_format((float)$row['value'], 2, ',', '.') . ' ' . $row['unit'],
            'status' => $workflowLabels[$row['workflow_status']] ?? $row['workflow_status'],
            'evidence' => (string)(int)$row['attachment_count'],
        ];
    }

    AuditLog::logExport($pdo, 'exports', [
        'format' => 'pdf',
        'year' => $filterYear,
        'department_id' => $filterDept ?: null,
        'record_count' => count($pdfRows),
    ]);

    PdfReport::download(
        'SECAP Veri Raporu',
        'Filtre: ' . ($filterYear ?: 'Tüm Yıllar') . ' / ' . ($workflowLabels[$filterWorkflow] ?? 'Tüm Durumlar'),
        [
            ['key' => 'year', 'label' => 'Yıl', 'ratio' => 0.06],
            ['key' => 'action', 'label' => 'Eylem', 'ratio' => 0.24],
            ['key' => 'dept', 'label' => 'Müdürlük', 'ratio' => 0.18],
            ['key' => 'kpi', 'label' => 'KPI', 'ratio' => 0.22],
            ['key' => 'value', 'label' => 'Değer', 'ratio' => 0.13],
            ['key' => 'status', 'label' => 'Durum', 'ratio' => 0.11],
            ['key' => 'evidence', 'label' => 'Kanıt', 'ratio' => 0.06],
        ],
        $pdfRows,
        'SECAP_Rapor_' . ($filterYear ?: 'Tum') . '_' . date('Ymd_His') . '.pdf'
    );
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['download'])) {
    Csrf::check();

    $yearFilter = $filterYear > 0;
    $deptFilter = $filterDept > 0;

    $deptLabel = 'Tüm Müdürlükler';
    if ($deptFilter) {
        $deptLabel = $pdo->prepare("SELECT name FROM departments WHERE id = ?");
        $deptLabel->execute([$filterDept]);
        $deptLabel = $deptLabel->fetchColumn() ?: 'Tüm Müdürlükler';
    }

    $footer = 'Bu rapor SECAP Portalı tarafından otomatik oluşturulmuştur. © ' . date('Y') . ' T.C. Silivri Belediyesi Bilgi İşlem Müdürlüğü';

    $excel = new ExcelExport(
        'SECAP Veri Raporu — T.C. Silivri Belediyesi',
        'T.C. Silivri Belediyesi - SECAP Portalı'
    );

    $genStats = $pdo->query("SELECT
        (SELECT COUNT(*) FROM actions WHERE status != 'cancelled' AND deleted_at IS NULL) AS actions,
        (SELECT COUNT(*) FROM activities WHERE is_active = 1 AND deleted_at IS NULL) AS activities,
        (SELECT COUNT(*) FROM kpis WHERE is_active = 1 AND deleted_at IS NULL) AS kpis,
        (SELECT COUNT(*) FROM data_entries WHERE deleted_at IS NULL) AS entries,
        (SELECT COUNT(*) FROM data_entries WHERE workflow_status = 'approved' AND deleted_at IS NULL) AS verified,
        (SELECT COUNT(*) FROM data_entries WHERE workflow_status IN ('submitted','needs_revision') AND deleted_at IS NULL) AS pending_entries,
        (SELECT COUNT(*) FROM departments WHERE is_active = 1) AS depts,
        (SELECT COUNT(*) FROM users WHERE is_active = 1 AND deleted_at IS NULL) AS users
    ")->fetch();

    $deptSummary = $pdo->query("
        SELECT d.name AS dept_name,
               COUNT(DISTINCT a.id) AS action_count,
               COUNT(DISTINCT k.id) AS kpi_count,
               COUNT(DISTINCT de.id) AS entry_count,
               SUM(CASE WHEN de.workflow_status = 'approved' THEN 1 ELSE 0 END) AS verified_count,
               SUM(CASE WHEN de.year = YEAR(NOW()) THEN 1 ELSE 0 END) AS this_year
        FROM departments d
        LEFT JOIN actions a ON a.responsible_department_id = d.id AND a.status != 'cancelled' AND a.deleted_at IS NULL
        LEFT JOIN kpis k ON k.action_id = a.id AND k.is_active = 1 AND k.deleted_at IS NULL
        LEFT JOIN data_entries de ON de.department_id = d.id AND de.deleted_at IS NULL
        WHERE d.is_active = 1 AND d.slug NOT LIKE '%-dis-paydas'
        GROUP BY d.id
        ORDER BY entry_count DESC
    ")->fetchAll();

    $catDist = $pdo->query("
        SELECT COALESCE(category, 'Diğer') AS category,
               COUNT(*) AS cnt,
               SUM(status = 'completed') AS completed,
               SUM(status = 'ongoing') AS ongoing,
               SUM(status = 'planned') AS planned
        FROM actions WHERE status != 'cancelled' AND deleted_at IS NULL
        GROUP BY category ORDER BY cnt DESC
    ")->fetchAll();

    function buildBrandedHeader(int $mergeCount, string $reportTitle, string $reportSubtitle): array {
        $rows = [];
        $rows[] = '<Row ss:Height="22"><Cell ss:StyleID="brandBar" ss:MergeAcross="' . $mergeCount . '"><Data ss:Type="String">T.C. SİLİVRİ BELEDİYESİ</Data></Cell></Row>';
        $rows[] = '<Row ss:Height="40"><Cell ss:StyleID="title" ss:MergeAcross="' . $mergeCount . '"><Data ss:Type="String">' . htmlspecialchars($reportTitle, ENT_QUOTES, 'UTF-8') . '</Data></Cell></Row>';
        $rows[] = '<Row ss:Height="24"><Cell ss:StyleID="subtitle" ss:MergeAcross="' . $mergeCount . '"><Data ss:Type="String">' . htmlspecialchars($reportSubtitle, ENT_QUOTES, 'UTF-8') . '</Data></Cell></Row>';
        $rows[] = '<Row ss:Height="6"><Cell ss:StyleID="spacer"/></Row>';
        return $rows;
    }

    $summaryHeaderRows = buildBrandedHeader(7,
        'SECAP Genel Özet Raporu',
        'Sürdürülebilir Enerji ve İklim Eylem Planı · Rapor Tarihi: ' . date('d.m.Y H:i')
    );

    $summaryHeaderRows[] = '<Row ss:Height="28">'
        . '<Cell ss:StyleID="sumLabel"><Data ss:Type="String">  Toplam Eylem</Data></Cell>'
        . '<Cell ss:StyleID="sumValueBig"><Data ss:Type="Number">' . $genStats['actions'] . '</Data></Cell>'
        . '<Cell ss:StyleID="sumLabel"><Data ss:Type="String">  Faaliyet</Data></Cell>'
        . '<Cell ss:StyleID="sumValueBig"><Data ss:Type="Number">' . $genStats['activities'] . '</Data></Cell>'
        . '<Cell ss:StyleID="sumLabel"><Data ss:Type="String">  KPI Sayısı</Data></Cell>'
        . '<Cell ss:StyleID="sumValueBig"><Data ss:Type="Number">' . $genStats['kpis'] . '</Data></Cell>'
        . '<Cell ss:StyleID="sumLabel"><Data ss:Type="String">  Kullanıcı</Data></Cell>'
        . '<Cell ss:StyleID="sumValueBig"><Data ss:Type="Number">' . $genStats['users'] . '</Data></Cell>'
        . '</Row>';
    $summaryHeaderRows[] = '<Row ss:Height="28">'
        . '<Cell ss:StyleID="sumLabel"><Data ss:Type="String">  Veri Girişi</Data></Cell>'
        . '<Cell ss:StyleID="sumValueBig"><Data ss:Type="Number">' . $genStats['entries'] . '</Data></Cell>'
        . '<Cell ss:StyleID="sumLabel"><Data ss:Type="String">  Onaylı</Data></Cell>'
        . '<Cell ss:StyleID="sumValueBig"><Data ss:Type="Number">' . $genStats['verified'] . '</Data></Cell>'
        . '<Cell ss:StyleID="sumLabel"><Data ss:Type="String">  Bekleyen</Data></Cell>'
        . '<Cell ss:StyleID="sumValueBig"><Data ss:Type="Number">' . $genStats['pending_entries'] . '</Data></Cell>'
        . '<Cell ss:StyleID="sumLabel"><Data ss:Type="String">  Müdürlük</Data></Cell>'
        . '<Cell ss:StyleID="sumValueBig"><Data ss:Type="Number">' . $genStats['depts'] . '</Data></Cell>'
        . '</Row>';
    $summaryHeaderRows[] = '<Row ss:Height="8"><Cell ss:StyleID="spacer"/></Row>';
    $summaryHeaderRows[] = '<Row ss:Height="28"><Cell ss:StyleID="section" ss:MergeAcross="7"><Data ss:Type="String">  Müdürlük Bazlı Performans Özeti</Data></Cell></Row>';

    $summaryRows = [];
    foreach ($deptSummary as $i => $ds) {
        $alt = $i % 2 === 1;
        $s = $alt ? 'cellAlt' : 'cell';
        $ns = $alt ? 'numAlt' : 'num';
        $kpiCount = (int)$ds['kpi_count'];
        $thisYear = (int)$ds['this_year'];
        $pct = $kpiCount > 0 ? $thisYear / $kpiCount : 0;
        $summaryRows[] = [
            ['value' => $ds['dept_name'], 'style' => $s],
            ['value' => (int)$ds['action_count'], 'type' => 'Number', 'style' => $ns],
            ['value' => $kpiCount, 'type' => 'Number', 'style' => $ns],
            ['value' => (int)$ds['entry_count'], 'type' => 'Number', 'style' => $ns],
            ['value' => (int)$ds['verified_count'], 'type' => 'Number', 'style' => $alt ? 'yearAlt' : 'year'],
            ['value' => $thisYear, 'type' => 'Number', 'style' => $ns],
            ['value' => $pct, 'type' => 'Number', 'style' => $alt ? 'pctAlt' : 'pct'],
            ['value' => $pct >= 0.8 ? 'Yeterli' : ($pct >= 0.4 ? 'Orta' : 'Yetersiz'), 'style' => $pct >= 0.8 ? 'verified' : ($pct >= 0.4 ? 'statusOngoing' : 'pending')],
        ];
    }

    $excel->addSheet('Genel Özet', [
        ['label' => 'Müdürlük', 'width' => 220],
        ['label' => 'Eylem', 'width' => 65],
        ['label' => 'KPI', 'width' => 65],
        ['label' => 'Veri Girişi', 'width' => 80],
        ['label' => 'Onaylı', 'width' => 65],
        ['label' => 'Bu Yıl (' . date('Y') . ')', 'width' => 85],
        ['label' => 'Doluluk %', 'width' => 75],
        ['label' => 'Değerlendirme', 'width' => 100],
    ], $summaryRows, [
        'headerRows' => $summaryHeaderRows,
        'freezeRow'  => count($summaryHeaderRows) + 1,
        'footer'     => $footer,
    ]);

    $allActions = $pdo->query("
        SELECT a.code, a.title, a.description, a.category, a.start_year, a.end_year, a.status,
               a.performance_indicators,
               d.name AS dept_name,
               (SELECT COUNT(*) FROM activities act WHERE act.action_id = a.id AND act.is_active = 1 AND act.deleted_at IS NULL) AS act_count,
               (SELECT COUNT(*) FROM kpis k WHERE k.action_id = a.id AND k.is_active = 1 AND k.deleted_at IS NULL) AS kpi_count,
               (SELECT COUNT(*) FROM data_entries de WHERE de.action_id = a.id AND de.deleted_at IS NULL) AS entry_count,
               (SELECT COUNT(*) FROM data_entries de WHERE de.action_id = a.id AND de.year = YEAR(NOW()) AND de.deleted_at IS NULL) AS this_year_entries
        FROM actions a
        JOIN departments d ON d.id = a.responsible_department_id
        WHERE a.status != 'cancelled' AND a.deleted_at IS NULL
        ORDER BY a.code
    ")->fetchAll();

    $actionHeaderRows = buildBrandedHeader(11,
        'SECAP Eylem Listesi',
        'Toplam ' . count($allActions) . ' eylem · Rapor Tarihi: ' . date('d.m.Y')
    );

    $actionRows = [];
    foreach ($allActions as $i => $a) {
        $alt = $i % 2 === 1;
        $s = $alt ? 'cellAlt' : 'cell';
        $cs = $alt ? 'codeAlt' : 'code';
        $ns = $alt ? 'numAlt' : 'num';
        $ys = $alt ? 'yearAlt' : 'year';
        $st = $statusStyles[$a['status']] ?? 'cell';
        $actionRows[] = [
            ['value' => $a['code'], 'style' => $cs],
            ['value' => $a['title'], 'style' => $s],
            ['value' => $a['category'] ?? '—', 'style' => $s],
            ['value' => $a['dept_name'], 'style' => $s],
            ['value' => $a['description'] ?? '', 'style' => $s],
            ['value' => $a['performance_indicators'] ?? '', 'style' => $s],
            ['value' => ($a['start_year'] ?? '') . '–' . ($a['end_year'] ?? '?'), 'style' => $ys],
            ['value' => $statusLabels[$a['status']] ?? $a['status'], 'style' => $st],
            ['value' => (int)$a['act_count'], 'type' => 'Number', 'style' => $ns],
            ['value' => (int)$a['kpi_count'], 'type' => 'Number', 'style' => $ns],
            ['value' => (int)$a['entry_count'], 'type' => 'Number', 'style' => $ns],
            ['value' => (int)$a['this_year_entries'], 'type' => 'Number', 'style' => $ns],
        ];
    }

    $excel->addSheet('Eylemler', [
        ['label' => 'Eylem Kodu', 'width' => 85],
        ['label' => 'Eylem Başlığı', 'width' => 280],
        ['label' => 'Kategori', 'width' => 110],
        ['label' => 'Sorumlu Müdürlük', 'width' => 200],
        ['label' => 'Proje Açıklaması', 'width' => 300],
        ['label' => 'Performans Göstergeleri', 'width' => 250],
        ['label' => 'Dönem', 'width' => 70],
        ['label' => 'Durum', 'width' => 90],
        ['label' => 'Faaliyet', 'width' => 65],
        ['label' => 'KPI', 'width' => 55],
        ['label' => 'Veri Girişi', 'width' => 75],
        ['label' => 'Bu Yıl', 'width' => 60],
    ], $actionRows, [
        'headerRows' => $actionHeaderRows,
        'freezeRow'  => count($actionHeaderRows) + 1,
        'footer'     => $footer,
    ]);

    $allKpis = $pdo->query("
        SELECT k.id, k.name, k.unit, k.description AS kpi_desc, k.baseline_value, k.baseline_year,
               k.target_value, k.target_label, k.is_cumulative,
               a.code AS action_code, a.title AS action_title, a.category,
               d.name AS dept_name,
               act.title AS activity_title,
               (SELECT de.value FROM data_entries de WHERE de.kpi_id = k.id AND de.year = YEAR(NOW()) AND de.deleted_at IS NULL LIMIT 1) AS current_value,
               (SELECT de.is_verified FROM data_entries de WHERE de.kpi_id = k.id AND de.year = YEAR(NOW()) AND de.deleted_at IS NULL LIMIT 1) AS current_verified,
               (SELECT COUNT(*) FROM data_entries de WHERE de.kpi_id = k.id AND de.deleted_at IS NULL) AS total_entries
        FROM kpis k
        JOIN actions a ON a.id = k.action_id
        JOIN departments d ON d.id = a.responsible_department_id
        LEFT JOIN activities act ON act.id = k.activity_id
        WHERE k.is_active = 1
          AND k.deleted_at IS NULL
          AND a.deleted_at IS NULL
        ORDER BY a.code, k.name
    ")->fetchAll();

    $kpiHeaderRows = buildBrandedHeader(14,
        'KPI (Gösterge) Detay Raporu',
        'Toplam ' . count($allKpis) . ' aktif KPI · Mevcut Yıl: ' . date('Y') . ' · Rapor Tarihi: ' . date('d.m.Y')
    );

    $kpiRows = [];
    foreach ($allKpis as $i => $k) {
        $alt = $i % 2 === 1;
        $s = $alt ? 'cellAlt' : 'cell';
        $cs = $alt ? 'codeAlt' : 'code';
        $ns = $alt ? 'numAlt' : 'num';
        $progress = null;
        if ($k['current_value'] !== null && $k['target_value'] > 0) {
            $progress = (float)$k['current_value'] / (float)$k['target_value'];
        }
        $kpiRows[] = [
            ['value' => $k['action_code'], 'style' => $cs],
            ['value' => $k['action_title'], 'style' => $s],
            ['value' => $k['category'] ?? '—', 'style' => $s],
            ['value' => $k['dept_name'], 'style' => $s],
            ['value' => $k['activity_title'] ?? '—', 'style' => $s],
            ['value' => $k['name'], 'style' => $s],
            ['value' => $k['kpi_desc'] ?? '', 'style' => $s],
            ['value' => $k['unit'], 'style' => $alt ? 'yearAlt' : 'year'],
            ['value' => $k['baseline_value'] !== null ? (float)$k['baseline_value'] : '—', 'type' => $k['baseline_value'] !== null ? 'Number' : 'String', 'style' => $ns],
            ['value' => $k['baseline_year'] ?? '—', 'style' => $s],
            ['value' => $k['target_value'] !== null ? (float)$k['target_value'] : '—', 'type' => $k['target_value'] !== null ? 'Number' : 'String', 'style' => $ns],
            ['value' => $k['target_label'] ?? '', 'style' => $s],
            ['value' => $k['current_value'] !== null ? (float)$k['current_value'] : '—', 'type' => $k['current_value'] !== null ? 'Number' : 'String', 'style' => $ns],
            ['value' => $progress !== null ? $progress : '—', 'type' => $progress !== null ? 'Number' : 'String', 'style' => $progress !== null ? ($alt ? 'pctAlt' : 'pct') : $s],
            ['value' => $k['is_cumulative'] ? 'Kümülatif' : 'Tekil', 'style' => $s],
        ];
    }

    $excel->addSheet('KPI Detay', [
        ['label' => 'Eylem Kodu', 'width' => 85],
        ['label' => 'Eylem Başlığı', 'width' => 220],
        ['label' => 'Kategori', 'width' => 100],
        ['label' => 'Müdürlük', 'width' => 200],
        ['label' => 'Faaliyet', 'width' => 200],
        ['label' => 'KPI Adı', 'width' => 200],
        ['label' => 'KPI Açıklaması', 'width' => 250],
        ['label' => 'Birim', 'width' => 65],
        ['label' => 'Başlangıç Değeri', 'width' => 90],
        ['label' => 'Ref. Yılı', 'width' => 65],
        ['label' => 'Hedef Değer', 'width' => 85],
        ['label' => 'Hedef Açıklaması', 'width' => 180],
        ['label' => date('Y') . ' Değeri', 'width' => 85],
        ['label' => 'İlerleme %', 'width' => 75],
        ['label' => 'Tür', 'width' => 70],
    ], $kpiRows, [
        'headerRows' => $kpiHeaderRows,
        'freezeRow'  => count($kpiHeaderRows) + 1,
        'footer'     => $footer,
    ]);

    $entryStmt = $pdo->prepare("
        SELECT a.code AS action_code, a.title AS action_title, a.category,
               d.name AS dept_name,
               k.name AS kpi_name, k.unit, k.target_value, k.baseline_value,
               de.year, de.value, de.notes,
               de.workflow_status,
               de.review_comment,
               (SELECT COUNT(*) FROM entry_attachments ea WHERE ea.data_entry_id = de.id AND ea.deleted_at IS NULL) AS attachment_count,
               u.full_name AS entered_by,
               de.created_at,
               vu.full_name AS verified_by,
               de.verified_at
        FROM data_entries de
        JOIN kpis k ON k.id = de.kpi_id
        JOIN actions a ON a.id = de.action_id
        JOIN departments d ON d.id = de.department_id
        JOIN users u ON u.id = de.entered_by
        LEFT JOIN users vu ON vu.id = de.verified_by
        WHERE de.deleted_at IS NULL
          AND a.deleted_at IS NULL
          AND (:year_filter_on = 0 OR de.year = :year_value)
          AND (:dept_filter_on = 0 OR de.department_id = :dept_id)
          AND (:workflow_filter_on = 0 OR de.workflow_status = :workflow_value)
        ORDER BY d.name, a.code, k.name, de.year DESC
    ");
    $entryStmt->execute([
        ':year_filter_on' => $yearFilter ? 1 : 0,
        ':year_value' => $filterYear,
        ':dept_filter_on' => $deptFilter ? 1 : 0,
        ':dept_id' => $filterDept,
        ':workflow_filter_on' => $filterWorkflow !== '' ? 1 : 0,
        ':workflow_value' => $filterWorkflow,
    ]);
    $entries = $entryStmt->fetchAll();

    $entryHeaderRows = buildBrandedHeader(17,
        'Veri Girişleri Detay Raporu',
        count($entries) . ' kayıt · Filtre: ' . ($filterYear ?: 'Tüm Yıllar') . ' / ' . $deptLabel .
        ' / ' . ($workflowLabels[$filterWorkflow] ?? 'Tümü') . ' · Rapor Tarihi: ' . date('d.m.Y')
    );

    $entryRows = [];
    foreach ($entries as $i => $e) {
        $alt = $i % 2 === 1;
        $s  = $alt ? 'cellAlt' : 'cell';
        $cs = $alt ? 'codeAlt' : 'code';
        $ns = $alt ? 'numAlt' : 'num';
        $ys = $alt ? 'yearAlt' : 'year';
        $ds = $alt ? 'dateAlt' : 'date';
        $verifyStatus = $workflowLabels[$e['workflow_status']] ?? $e['workflow_status'];
        $vs = $e['workflow_status'] === 'approved' ? 'verified' : ($e['workflow_status'] === 'rejected' ? 'statusPlanned' : 'pending');

        $entryRows[] = [
            ['value' => $e['action_code'], 'style' => $cs],
            ['value' => $e['action_title'], 'style' => $s],
            ['value' => $e['category'] ?? '—', 'style' => $s],
            ['value' => $e['dept_name'], 'style' => $s],
            ['value' => $e['kpi_name'], 'style' => $s],
            ['value' => $e['unit'], 'style' => $s],
            ['value' => $e['target_value'] !== null ? (float)$e['target_value'] : '—', 'type' => $e['target_value'] !== null ? 'Number' : 'String', 'style' => $ns],
            ['value' => $e['baseline_value'] !== null ? (float)$e['baseline_value'] : '—', 'type' => $e['baseline_value'] !== null ? 'Number' : 'String', 'style' => $ns],
            ['value' => (int)$e['year'], 'type' => 'Number', 'style' => $ys],
            ['value' => (float)$e['value'], 'type' => 'Number', 'style' => $ns],
            ['value' => (int)$e['attachment_count'], 'type' => 'Number', 'style' => $ns],
            ['value' => $e['notes'] ?? '', 'style' => $s],
            ['value' => $verifyStatus, 'style' => $vs],
            ['value' => $e['review_comment'] ?? '', 'style' => $s],
            ['value' => $e['entered_by'], 'style' => $s],
            ['value' => date('d.m.Y H:i', strtotime($e['created_at'])), 'style' => $ds],
            ['value' => $e['verified_by'] ?? '—', 'style' => $s],
            ['value' => $e['verified_at'] ? date('d.m.Y H:i', strtotime($e['verified_at'])) : '—', 'style' => $ds],
        ];
    }

    $excel->addSheet('Veri Girişleri', [
        ['label' => 'Eylem Kodu', 'width' => 85],
        ['label' => 'Eylem Başlığı', 'width' => 220],
        ['label' => 'Kategori', 'width' => 100],
        ['label' => 'Müdürlük', 'width' => 200],
        ['label' => 'KPI (Gösterge)', 'width' => 200],
        ['label' => 'Birim', 'width' => 65],
        ['label' => 'Hedef', 'width' => 80],
        ['label' => 'Başlangıç', 'width' => 80],
        ['label' => 'Yıl', 'width' => 50],
        ['label' => 'Raporlanan Değer', 'width' => 100],
        ['label' => 'Kanıt Sayısı', 'width' => 80],
        ['label' => 'Veri Açıklaması', 'width' => 250],
        ['label' => 'Workflow Durumu', 'width' => 100],
        ['label' => 'İnceleme Yorumu', 'width' => 180],
        ['label' => 'Giren Kullanıcı', 'width' => 130],
        ['label' => 'Giriş Tarihi', 'width' => 100],
        ['label' => 'Onaylayan', 'width' => 130],
        ['label' => 'Onay Tarihi', 'width' => 100],
    ], $entryRows, [
        'headerRows' => $entryHeaderRows,
        'freezeRow'  => count($entryHeaderRows) + 1,
        'footer'     => $footer,
    ]);

    $allActivities = $pdo->query("
        SELECT act.title, act.sub_actions,
               a.code AS action_code, a.title AS action_title,
               d.name AS dept_name,
               (SELECT COUNT(*) FROM kpis k WHERE k.activity_id = act.id AND k.is_active = 1 AND k.deleted_at IS NULL) AS kpi_count,
               u.full_name AS created_by_name,
               act.created_at
        FROM activities act
        JOIN actions a ON a.id = act.action_id
        JOIN departments d ON d.id = act.department_id
        LEFT JOIN users u ON u.id = act.created_by
        WHERE act.is_active = 1
          AND act.deleted_at IS NULL
          AND a.deleted_at IS NULL
        ORDER BY a.code, act.sort_order, act.id
    ")->fetchAll();

    $actHeaderRows = buildBrandedHeader(7,
        'Faaliyet Listesi',
        'Toplam ' . count($allActivities) . ' faaliyet · Rapor Tarihi: ' . date('d.m.Y')
    );

    $actRows = [];
    foreach ($allActivities as $i => $ac) {
        $alt = $i % 2 === 1;
        $s  = $alt ? 'cellAlt' : 'cell';
        $cs = $alt ? 'codeAlt' : 'code';
        $ns = $alt ? 'numAlt' : 'num';
        $ds = $alt ? 'dateAlt' : 'date';
        $actRows[] = [
            ['value' => $ac['action_code'], 'style' => $cs],
            ['value' => $ac['action_title'], 'style' => $s],
            ['value' => $ac['dept_name'], 'style' => $s],
            ['value' => $ac['title'], 'style' => $s],
            ['value' => $ac['sub_actions'] ?? '', 'style' => $s],
            ['value' => (int)$ac['kpi_count'], 'type' => 'Number', 'style' => $ns],
            ['value' => $ac['created_by_name'] ?? '—', 'style' => $s],
            ['value' => date('d.m.Y', strtotime($ac['created_at'])), 'style' => $ds],
        ];
    }

    $excel->addSheet('Faaliyetler', [
        ['label' => 'Eylem Kodu', 'width' => 85],
        ['label' => 'Eylem Başlığı', 'width' => 220],
        ['label' => 'Sorumlu Müdürlük', 'width' => 200],
        ['label' => 'Faaliyet Başlığı', 'width' => 250],
        ['label' => 'Gerçekleştirilecek Eylemler', 'width' => 300],
        ['label' => 'Bağlı KPI', 'width' => 65],
        ['label' => 'Oluşturan', 'width' => 130],
        ['label' => 'Tarih', 'width' => 80],
    ], $actRows, [
        'headerRows' => $actHeaderRows,
        'freezeRow'  => count($actHeaderRows) + 1,
        'footer'     => $footer,
    ]);

    AuditLog::logExport($pdo, 'exports', [
        'format' => 'xls',
        'year' => $filterYear,
        'department_id' => $filterDept ?: null,
        'record_count' => count($entries),
        'sheet_count' => 5,
    ]);

    $fileName = 'SECAP_Rapor_' . ($filterYear ?: 'Tum') . '_' . date('Ymd_His') . '.xls';
    $excel->download($fileName);
}

$departments = $pdo->query("SELECT id, name FROM departments WHERE is_active=1 ORDER BY name")->fetchAll();
$years = range((int)date('Y'), 2020);

$entryCounts = $pdo->query(
    "SELECT de.year, COUNT(*) AS cnt, SUM(CASE WHEN de.workflow_status = 'approved' THEN 1 ELSE 0 END) AS verified
     FROM data_entries de
     WHERE de.deleted_at IS NULL
     GROUP BY de.year
     ORDER BY de.year DESC"
)->fetchAll();

require_once APP_ROOT . '/uygulama/yerlesim/ust.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h5 class="fw-bold mb-0">Veri Dışa Aktarma</h5>
        <small class="text-muted">SECAP verilerini detaylı Excel veya kurumsal PDF raporu olarak indirin</small>
    </div>
</div>

<div class="row g-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-file-earmark-spreadsheet me-2 text-success"></i>Excel Raporu İndir
            </div>
            <div class="card-body">
                <form method="POST">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="download" value="1">

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Yıl</label>
                        <select name="year" class="form-select filtre-yil">
                            <?php foreach ($years as $y): ?>
                            <option value="<?= $y ?>" <?= $filterYear === (int) $y ? 'selected' : '' ?>><?= $y ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Müdürlük (Opsiyonel)</label>
                        <select name="dept_id" class="form-select filtre-mudurluk">
                            <option value="0">Tüm Müdürlükler</option>
                            <?php foreach ($departments as $d): ?>
                            <option value="<?= $d['id'] ?>" <?= $filterDept === (int) $d['id'] ? 'selected' : '' ?>><?= htmlspecialchars($d['name'], ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Workflow</label>
                        <select name="workflow_status" class="form-select">
                            <?php foreach ($workflowLabels as $v => $label): ?>
                            <option value="<?= htmlspecialchars($v, ENT_QUOTES, 'UTF-8') ?>" <?= $filterWorkflow === $v ? 'selected' : '' ?>>
                                <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="d-grid gap-2 mt-3">
                        <button type="submit" name="download" value="1" class="btn btn-success">
                            <i class="bi bi-download me-2"></i>Excel Olarak İndir (.xls)
                        </button>
                        <button type="submit" name="download_pdf" value="1" class="btn btn-outline-primary">
                            <i class="bi bi-filetype-pdf me-2"></i>PDF Olarak İndir
                        </button>
                    </div>
                </form>

                <div class="mt-3 p-3 rounded" style="background:var(--arkaplan-gecis); font-size:.78rem;">
                    <div class="fw-bold mb-2" style="font-size:.82rem;"><i class="bi bi-file-earmark-richtext me-1"></i>Rapor İçeriği (5 Sayfa)</div>
                    <div class="d-flex flex-column gap-1">
                        <span><i class="bi bi-1-circle me-1 text-success"></i><strong>Genel Özet</strong> — Müdürlük performans tablosu, doluluk oranları</span>
                        <span><i class="bi bi-2-circle me-1 text-success"></i><strong>Eylemler</strong> — Tüm SECAP eylemleri, açıklamaları, durumları</span>
                        <span><i class="bi bi-3-circle me-1 text-success"></i><strong>KPI Detay</strong> — Göstergeler, hedefler, başlangıç değerleri, ilerleme %</span>
                        <span><i class="bi bi-4-circle me-1 text-success"></i><strong>Veri Girişleri</strong> — Tüm raporlanan veriler, onay bilgileri</span>
                        <span><i class="bi bi-5-circle me-1 text-success"></i><strong>Faaliyetler</strong> — Eylem bazlı faaliyet detayları</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-bar-chart me-2 text-primary"></i>Yıl Bazlı Veri Özeti
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Yıl</th>
                                <th class="text-center">Toplam Giriş</th>
                                <th class="text-center">Onaylı</th>
                                <th class="text-center">Bekleyen</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($entryCounts)): ?>
                            <tr><td colspan="4" class="text-center text-muted py-3">Henüz veri girişi yok.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($entryCounts as $ec): ?>
                            <tr>
                                <td class="fw-bold"><?= $ec['year'] ?></td>
                                <td class="text-center"><?= $ec['cnt'] ?></td>
                                <td class="text-center">
                                    <span class="badge bg-success-subtle text-success"><?= (int)$ec['verified'] ?></span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-warning-subtle text-warning"><?= (int)$ec['cnt'] - (int)$ec['verified'] ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once APP_ROOT . '/uygulama/yerlesim/alt.php'; ?>
