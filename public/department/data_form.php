<?php
require_once __DIR__ . '/../../bootstrap.php';
Auth::requireLogin();

$pdo     = Database::getInstance()->getConnection();
$errors  = [];

$preKpiId = (int)($_GET['kpi_id'] ?? 0);
$preYear  = (int)($_GET['year']   ?? date('Y'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::check();

    $kpiId = filter_input(INPUT_POST, 'kpi_id', FILTER_VALIDATE_INT);
    $year  = filter_input(INPUT_POST, 'year',   FILTER_VALIDATE_INT);
    $value = filter_input(INPUT_POST, 'value',  FILTER_VALIDATE_FLOAT);
    $notes = Validator::textarea($_POST['notes'] ?? '', 2000, true);

    if (!$kpiId || $kpiId <= 0)                  $errors[] = 'Geçerli bir KPI seçin.';
    if (!$year  || $year < 2020 || $year > 2050)  $errors[] = 'Geçerli bir yıl girin (2020–2050).';
    if ($value === false || $value === null)       $errors[] = 'Geçerli bir sayısal değer girin.';
    if ($notes === '')                             $errors[] = 'Veri açıklaması zorunludur. Lütfen veri kaynağını veya açıklamasını yazın.';

    if (empty($errors)) {
        $stmt = $pdo->prepare(
            "SELECT k.id, k.name, k.unit, k.action_id,
                    a.responsible_department_id, a.code, a.title
             FROM   kpis k
             JOIN   actions a ON a.id = k.action_id
             WHERE  k.id = :id AND k.is_active = 1
             LIMIT 1"
        );
        $stmt->execute([':id' => $kpiId]);
        $kpi = $stmt->fetch();

        if (!$kpi) {
            $errors[] = 'Seçilen KPI bulunamadı veya aktif değil.';
        }
    }

    if (empty($errors) && isset($kpi)) {
        if (!Auth::canAccessAction($pdo, (int)$kpi['action_id'])) {
            Auth::denyAccess($pdo, 'actions', (int) $kpi['action_id'], [
                'message' => 'Bu eyleme veri girme yetkiniz bulunmuyor.',
                'action_code' => $kpi['code'],
            ]);
        }
    }

    if (empty($errors) && isset($kpi)) {
        try {
            $oldStmt = $pdo->prepare(
                "SELECT id, value, notes, entered_by FROM data_entries WHERE kpi_id = :kpi AND year = :year LIMIT 1"
            );
            $oldStmt->execute([':kpi' => $kpi['id'], ':year' => $year]);
            $oldEntry = $oldStmt->fetch();

            $stmt = $pdo->prepare(
                "INSERT INTO data_entries
                    (kpi_id, action_id, department_id, entered_by, year, value, notes)
                 VALUES
                    (:kpi_id, :action_id, :dept_id, :user_id, :year, :value, :notes)
                 ON DUPLICATE KEY UPDATE
                    value      = VALUES(value),
                    notes      = VALUES(notes),
                    entered_by = VALUES(entered_by),
                    is_verified = 0,
                    updated_at = NOW()"
            );
            $stmt->execute([
                ':kpi_id'   => $kpi['id'],
                ':action_id'=> $kpi['action_id'],
                ':dept_id'  => Auth::isAdmin()
                                ? $kpi['responsible_department_id']
                                : Auth::getDepartmentId(),
                ':user_id'  => Auth::getUserId(),
                ':year'     => $year,
                ':value'    => $value,
                ':notes'    => $notes,
            ]);

            $entryId = $oldEntry ? (int)$oldEntry['id'] : (int)$pdo->lastInsertId();
            AuditLog::log(
                $pdo,
                $oldEntry ? 'update' : 'create',
                'data_entries',
                $entryId,
                $oldEntry ? ['value' => $oldEntry['value'], 'notes' => $oldEntry['notes']] : null,
                ['kpi_id' => $kpi['id'], 'year' => $year, 'value' => $value, 'notes' => $notes]
            );

            Flash::success("Veri kaydedildi: {$kpi['name']} · {$year} · " . number_format((float)$value, 2) . " {$kpi['unit']}");

            $redirectTo = Auth::isAdmin()
                ? BASE_PATH . '/public/admin/entries.php'
                : BASE_PATH . '/public/department/my_actions.php';
            header("Location: {$redirectTo}");
            exit;

        } catch (PDOException $e) {
            error_log('[SECAP][DB] data_entries hatası: ' . $e->getMessage());
            $errors[] = 'Kayıt sırasında bir hata oluştu. Lütfen tekrar deneyin.';
        }
    }
}

if (Auth::isAdmin()) {
    $kpiStmt = $pdo->query(
        "SELECT k.id, k.name, k.unit, k.description AS kpi_desc,
                k.baseline_value, k.baseline_year, k.target_value, k.target_label,
                k.is_cumulative,
                a.code AS action_code, a.title AS action_title, a.category AS action_category,
                a.start_year, a.end_year, a.status AS action_status,
                d.name AS dept_name
         FROM   kpis k
         JOIN   actions a     ON a.id = k.action_id
         JOIN   departments d ON d.id = a.responsible_department_id
         WHERE  k.is_active = 1
         ORDER  BY a.code, k.name"
    );
} else {
    $kpiStmt = $pdo->prepare(
        "SELECT DISTINCT k.id, k.name, k.unit, k.description AS kpi_desc,
                k.baseline_value, k.baseline_year, k.target_value, k.target_label,
                k.is_cumulative,
                a.code AS action_code, a.title AS action_title, a.category AS action_category,
                a.start_year, a.end_year, a.status AS action_status,
                d.name AS dept_name
         FROM   kpis k
         JOIN   actions a     ON a.id = k.action_id
         JOIN   departments d ON d.id = a.responsible_department_id
         LEFT JOIN action_departments ad ON ad.action_id = a.id AND ad.department_id = :dept_id2
         WHERE  k.is_active = 1
           AND  (a.responsible_department_id = :dept_id OR ad.department_id IS NOT NULL)
         ORDER  BY a.code, k.name"
    );
    $kpiStmt->execute([':dept_id' => Auth::getDepartmentId(), ':dept_id2' => Auth::getDepartmentId()]);
}
$availableKpis = $kpiStmt->fetchAll();

$kpiHistory = [];
if (!empty($availableKpis)) {
    $kpiIds = array_column($availableKpis, 'id');
    $placeholders = implode(',', array_fill(0, count($kpiIds), '?'));
    $histStmt = $pdo->prepare(
        "SELECT de.kpi_id, de.year, de.value, de.is_verified, de.notes,
                u.full_name AS entered_by_name, de.created_at
         FROM   data_entries de
         JOIN   users u ON u.id = de.entered_by
         WHERE  de.kpi_id IN ({$placeholders})
         ORDER  BY de.kpi_id, de.year DESC"
    );
    $histStmt->execute(array_map('intval', $kpiIds));
    foreach ($histStmt->fetchAll() as $h) {
        $kpiHistory[(int)$h['kpi_id']][] = $h;
    }
}

$selectedKpiId = (int)($_POST['kpi_id'] ?? $preKpiId);
$selectedYear  = (int)($_POST['year']   ?? $preYear);

$kpiDetailsJson = [];
foreach ($availableKpis as $k) {
    $kid = (int)$k['id'];
    $history = $kpiHistory[$kid] ?? [];
    $kpiDetailsJson[$kid] = [
        'name'           => $k['name'],
        'unit'           => $k['unit'],
        'desc'           => $k['kpi_desc'] ?: null,
        'action_code'    => $k['action_code'],
        'action_title'   => $k['action_title'],
        'action_category'=> $k['action_category'] ?: null,
        'action_status'  => $k['action_status'],
        'start_year'     => $k['start_year'],
        'end_year'       => $k['end_year'],
        'dept'           => $k['dept_name'],
        'baseline'       => $k['baseline_value'] !== null ? (float)$k['baseline_value'] : null,
        'baseline_year'  => $k['baseline_year'],
        'target'         => $k['target_value'] !== null ? (float)$k['target_value'] : null,
        'target_label'   => $k['target_label'] ?: null,
        'cumulative'     => (bool)$k['is_cumulative'],
        'history'        => array_map(fn($h) => [
            'year'       => (int)$h['year'],
            'value'      => (float)$h['value'],
            'verified'   => (bool)$h['is_verified'],
            'by'         => $h['entered_by_name'],
            'date'       => date('d.m.Y', strtotime($h['created_at'])),
            'notes'      => $h['notes'] ? mb_strimwidth($h['notes'], 0, 80, '…') : null,
        ], $history),
    ];
}

$statusLabels = [
    'planned'   => 'Planlandı',
    'ongoing'   => 'Devam Ediyor',
    'completed' => 'Tamamlandı',
];

$pageTitle = 'Veri Girişi';
$activeNav = Auth::isAdmin() ? 'data_form' : 'my_actions';

require_once APP_ROOT . '/templates/shared/header.php';
?>

<div class="d-flex align-items-center gap-2 mb-4">
    <?php if (!Auth::isAdmin()): ?>
    <a href="<?= BASE_PATH ?>/public/department/my_actions.php" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left"></i>
    </a>
    <?php endif; ?>
    <div>
        <h5 class="fw-bold mb-0">
            <?= Auth::isAdmin() ? 'Dış Paydaş / Manuel Veri Girişi' : 'Veri Girişi' ?>
        </h5>
        <small class="text-muted">
            <?= Auth::isAdmin()
                ? 'Admin olarak tüm müdürlüklere veri girebilirsiniz.'
                : 'Yalnızca müdürlüğünüze ait eylemler listeleniyor.' ?>
        </small>
    </div>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
    <ul class="mb-0">
        <?php foreach ($errors as $e): ?>
            <li><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<?php if (empty($availableKpis)): ?>
<div class="card" style="max-width:640px;">
    <div class="card-body text-center py-5">
        <i class="bi bi-exclamation-triangle d-block mb-2 text-warning" style="font-size:2.5rem;"></i>
        <div class="fw-semibold mb-1">Aktif KPI Bulunamadı</div>
        <div class="text-muted text-md">Müdürlüğünüze ait aktif KPI tanımlanmamış. Lütfen İklim Değişikliği Müdürlüğü ile iletişime geçin.</div>
    </div>
</div>
<?php else: ?>

<div class="row g-4">
    <div class="col-12 col-xl-7">
        <div class="card">
            <div class="card-body">
                <form method="POST" action="" novalidate>
                    <?= Csrf::field() ?>

                    <div class="mb-4">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="badge bg-dark step-badge">1</span>
                            <label class="form-label fw-semibold mb-0">KPI Seçin</label>
                        </div>
                        <select name="kpi_id" id="kpi_id" class="form-select" required>
                            <option value="">— Bir KPI seçin —</option>
                            <?php
                            $lastAction = null;
                            foreach ($availableKpis as $k):
                                $actionLabel = "[{$k['action_code']}] " . mb_strimwidth($k['action_title'], 0, 60, '…');
                                if ($actionLabel !== $lastAction):
                                    if ($lastAction !== null) echo '</optgroup>';
                                    $lastAction = $actionLabel;
                                    echo '<optgroup label="' . htmlspecialchars($actionLabel . ' — ' . $k['dept_name'], ENT_QUOTES, 'UTF-8') . '">';
                                endif;
                            ?>
                            <option value="<?= (int)$k['id'] ?>"
                                <?= $selectedKpiId === (int)$k['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($k['name'], ENT_QUOTES, 'UTF-8') ?>
                                (<?= htmlspecialchars($k['unit'], ENT_QUOTES, 'UTF-8') ?>)
                            </option>
                            <?php endforeach; ?>
                            <?php if ($lastAction !== null) echo '</optgroup>'; ?>
                        </select>
                    </div>

                    <div class="mb-4">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="badge bg-dark step-badge">2</span>
                            <label class="form-label fw-semibold mb-0">Yıl ve Değer Girin</label>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Yıl</label>
                                <input type="number" name="year" class="form-control"
                                       min="2020" max="2050" placeholder="<?= date('Y') ?>"
                                       value="<?= $selectedYear ?: date('Y') ?>" required>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label">Değer</label>
                                <div class="input-group">
                                    <input type="number" name="value" id="valueInput" class="form-control"
                                           step="0.0001" placeholder="Sayısal değer girin"
                                           value="<?= htmlspecialchars((string)($_POST['value'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                           required>
                                    <span class="input-group-text" id="unitLabel">birim</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="badge bg-dark step-badge">3</span>
                            <label class="form-label fw-semibold mb-0">Veri Kaynağını Açıklayın</label>
                        </div>
                        <textarea name="notes" class="form-control" rows="3" required
                                  placeholder="Örn: 2025 yılı enerji tüketim verileri, EDAŞ fatura bilgileri esas alınmıştır..."
                        ><?= htmlspecialchars($_POST['notes'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                        <div class="form-text">
                            <i class="bi bi-info-circle me-1"></i>Veri kaynağı, ölçüm yöntemi veya dönemi belirtin. Bu alan zorunludur.
                        </div>
                    </div>

                    <hr class="my-3" style="border-color:var(--border-light);">

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-success px-4">
                            <i class="bi bi-floppy me-1"></i>Kaydet
                        </button>
                        <a href="<?= Auth::isAdmin() ? BASE_PATH . '/public/admin/entries.php' : BASE_PATH . '/public/department/my_actions.php' ?>"
                           class="btn btn-outline-secondary">İptal</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-5">
        <div id="infoEmpty" class="card">
            <div class="card-body text-center py-5">
                <i class="bi bi-arrow-left-circle d-block mb-2" style="font-size:2rem; color:var(--border-light);"></i>
                <div class="text-muted text-md">Detayları görmek için sol taraftan bir KPI seçin.</div>
            </div>
        </div>

        <div id="infoPanel" style="display:none;">

            <div class="card mb-3" style="border-left:3px solid var(--clr-accent);">
                <div class="card-body py-3 px-3">
                    <div class="d-flex align-items-start gap-3">
                        <div class="s-icon flex-shrink-0" style="background:#E8F5E9; color:#2E7D32; width:40px; height:40px; font-size:1.1rem; margin-bottom:0;">
                            <i class="bi bi-lightning-charge-fill"></i>
                        </div>
                        <div style="min-width:0; flex:1;">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <code class="action-code text-2xs" id="pActionCode"></code>
                                <span id="pStatus"></span>
                            </div>
                            <div id="pActionTitle" class="fw-bold" style="font-size:.9rem;"></div>
                            <div style="display:grid; grid-template-columns:auto 1fr; gap:.25rem .75rem; font-size:.78rem; margin-top:.5rem;">
                                <span class="text-muted fw-semibold">Kategori</span>
                                <span id="pActionCategory"></span>
                                <span class="text-muted fw-semibold">Müdürlük</span>
                                <span id="pDept" class="fw-medium"></span>
                                <span class="text-muted fw-semibold">Dönem</span>
                                <span id="pPeriod"></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-3" style="border-left:3px solid var(--clr-accent);">
                <div class="card-body py-3 px-3">
                    <div class="d-flex align-items-start gap-3">
                        <div class="s-icon flex-shrink-0" style="background:#E3F2FD; color:#1565C0; width:40px; height:40px; font-size:1.1rem; margin-bottom:0;">
                            <i class="bi bi-bar-chart-fill"></i>
                        </div>
                        <div style="min-width:0; flex:1;">
                            <div id="pKpiName" class="fw-bold" style="font-size:.9rem;"></div>
                            <div id="pKpiDesc" class="text-muted" style="font-size:.78rem;"></div>
                            <div class="d-flex gap-2 flex-wrap mt-2" id="pBadges"></div>
                        </div>
                    </div>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:.5rem; margin-top:.75rem;">
                        <div class="p-2 rounded" style="background:var(--bg-hover);">
                            <div class="text-muted" style="font-size:.65rem; font-weight:700; text-transform:uppercase;">Hedef</div>
                            <div class="fw-bold" style="font-size:1rem;" id="pTarget">—</div>
                            <div class="text-muted" style="font-size:.68rem;" id="pTargetLabel"></div>
                        </div>
                        <div class="p-2 rounded" style="background:var(--bg-hover);">
                            <div class="text-muted" style="font-size:.65rem; font-weight:700; text-transform:uppercase;">Başlangıç</div>
                            <div class="fw-bold" style="font-size:1rem;" id="pBaseline">—</div>
                            <div class="text-muted" style="font-size:.68rem;" id="pBaselineYear"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card" id="pHistoryCard" style="border-left:3px solid var(--clr-accent);">
                <div class="card-body py-3 px-3">
                    <div class="d-flex align-items-center gap-3 mb-2">
                        <div class="s-icon flex-shrink-0" style="background:#FFF8E1; color:#FF8F00; width:40px; height:40px; font-size:1.1rem; margin-bottom:0;">
                            <i class="bi bi-clock-history"></i>
                        </div>
                        <div class="fw-bold" style="font-size:.9rem;">Geçmiş Veri Girişleri</div>
                    </div>
                </div>
                <div id="pHistory" class="p-0"></div>
            </div>

        </div>
    </div>
</div>

<?php endif; ?>

<?php
$kpiDetailsEncoded = json_encode($kpiDetailsJson, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
$extraJs = "<script>
(function () {
    var sel       = document.getElementById('kpi_id');
    var label     = document.getElementById('unitLabel');
    var infoEmpty = document.getElementById('infoEmpty');
    var infoPanel = document.getElementById('infoPanel');
    if (!sel) return;

    var details = {$kpiDetailsEncoded};

    var statusMap = " . json_encode($statusLabels, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) . ";
    var statusClsMap = {planned:'badge-planned',ongoing:'badge-ongoing',completed:'badge-completed'};

    function fmt(v) { return v !== null ? Number(v).toLocaleString('tr-TR', {minimumFractionDigits:2, maximumFractionDigits:2}) : '—'; }

    function setBadge(target, className, text, iconClass) {
        target.replaceChildren();
        var span = document.createElement('span');
        span.className = 'badge ' + className;
        if (iconClass) {
            var icon = document.createElement('i');
            icon.className = 'bi ' + iconClass;
            span.appendChild(icon);
            span.appendChild(document.createTextNode(' '));
        }
        span.appendChild(document.createTextNode(text));
        target.appendChild(span);
    }

    function buildHistoryTable(history, unit) {
        var table = document.createElement('table');
        table.className = 'table table-hover mb-0';
        table.style.fontSize = '.82rem';

        var thead = document.createElement('thead');
        thead.className = 'table-light';
        var headerRow = document.createElement('tr');
        ['Yıl', 'Değer', 'Onay', 'Giren', 'Tarih'].forEach(function(label) {
            var th = document.createElement('th');
            if (label === 'Değer') th.className = 'text-end';
            if (label === 'Onay') th.className = 'text-center';
            th.textContent = label;
            headerRow.appendChild(th);
        });
        thead.appendChild(headerRow);
        table.appendChild(thead);

        var tbody = document.createElement('tbody');
        history.forEach(function(h) {
            var tr = document.createElement('tr');

            var yearTd = document.createElement('td');
            yearTd.className = 'fw-bold';
            yearTd.textContent = String(h.year);
            tr.appendChild(yearTd);

            var valueTd = document.createElement('td');
            valueTd.className = 'text-end fw-bold';
            valueTd.style.color = 'var(--clr-accent)';
            valueTd.appendChild(document.createTextNode(fmt(h.value) + ' '));
            var small = document.createElement('small');
            small.className = 'text-muted';
            small.textContent = unit;
            valueTd.appendChild(small);
            tr.appendChild(valueTd);

            var verifiedTd = document.createElement('td');
            verifiedTd.className = 'text-center';
            var statusWrap = document.createElement('span');
            statusWrap.className = 'badge ' + (h.verified ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning');
            var statusIcon = document.createElement('i');
            statusIcon.className = 'bi ' + (h.verified ? 'bi-check-circle' : 'bi-hourglass');
            statusWrap.appendChild(statusIcon);
            verifiedTd.appendChild(statusWrap);
            tr.appendChild(verifiedTd);

            var byTd = document.createElement('td');
            byTd.textContent = h.by || '—';
            tr.appendChild(byTd);

            var dateTd = document.createElement('td');
            dateTd.className = 'text-muted';
            dateTd.textContent = h.date || '—';
            tr.appendChild(dateTd);

            tbody.appendChild(tr);
        });

        table.appendChild(tbody);
        return table;
    }

    function update() {
        var kid = parseInt(sel.value);
        var d = details[kid];

        if (!d) {
            label.textContent = 'birim';
            if (infoEmpty) infoEmpty.style.display = '';
            if (infoPanel) infoPanel.style.display = 'none';
            return;
        }

        label.textContent = d.unit || 'birim';
        if (infoEmpty) infoEmpty.style.display = 'none';
        if (infoPanel) infoPanel.style.display = '';

        document.getElementById('pActionCode').textContent = d.action_code;
        document.getElementById('pActionTitle').textContent = d.action_title;
        document.getElementById('pActionCategory').textContent = d.action_category || '—';
        document.getElementById('pDept').textContent = d.dept;
        document.getElementById('pPeriod').textContent = d.start_year + ' – ' + (d.end_year || '?');
        var statusEl = document.getElementById('pStatus');
        setBadge(statusEl, (statusClsMap[d.action_status] || ''), (statusMap[d.action_status] || d.action_status), '');

        document.getElementById('pKpiName').textContent = d.name;
        var descEl = document.getElementById('pKpiDesc');
        descEl.textContent = d.desc || '';
        descEl.style.display = d.desc ? '' : 'none';

        document.getElementById('pTarget').textContent = d.target !== null ? fmt(d.target) + ' ' + d.unit : '—';
        document.getElementById('pTargetLabel').textContent = d.target_label || '';
        document.getElementById('pBaseline').textContent = d.baseline !== null ? fmt(d.baseline) + ' ' + d.unit : '—';
        document.getElementById('pBaselineYear').textContent = d.baseline_year ? 'Referans yılı: ' + d.baseline_year : '';

        var badgesEl = document.getElementById('pBadges');
        badgesEl.replaceChildren();
        var typeBadgeTarget = document.createElement('span');
        setBadge(typeBadgeTarget, d.cumulative ? 'bg-primary-subtle text-primary' : 'bg-secondary-subtle text-secondary', d.cumulative ? 'Kümülatif' : 'Tekil (yıllık)', d.cumulative ? 'bi-layers' : '');
        badgesEl.appendChild(typeBadgeTarget.firstChild);
        var unitBadge = document.createElement('span');
        unitBadge.className = 'badge bg-secondary-subtle text-secondary';
        unitBadge.textContent = d.unit;
        badgesEl.appendChild(unitBadge);

        var hEl = document.getElementById('pHistory');
        var hCard = document.getElementById('pHistoryCard');
        if (!d.history || d.history.length === 0) {
            hCard.style.display = 'none';
        } else {
            hCard.style.display = '';
            hEl.replaceChildren(buildHistoryTable(d.history, d.unit));
        }
    }

    sel.addEventListener('change', update);
    update();
})();
</script>";
?>
<?php require_once APP_ROOT . '/templates/shared/footer.php'; ?>
