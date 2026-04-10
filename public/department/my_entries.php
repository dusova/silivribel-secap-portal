<?php
require_once __DIR__ . '/../../bootstrap.php';
Auth::requireLogin();

$pdo       = Database::getInstance()->getConnection();
$deptId    = Auth::getDepartmentId();
$pageTitle = 'Veri Geçmişim';
$activeNav = 'my_entries';

$filterYear = (int)($_GET['year'] ?? 0);

$params = [':dept_id' => $deptId];
$yearWhere = '';
if ($filterYear > 0) {
    $yearWhere           = 'AND de.year = :year';
    $params[':year']     = $filterYear;
}

$stmt = $pdo->prepare(
    "SELECT de.id, de.kpi_id, de.year, de.value, de.notes, de.is_verified, de.created_at, de.updated_at,
            k.name AS kpi_name, k.unit, k.target_value,
            a.code AS action_code, a.title AS action_title,
            u.full_name AS entered_by_name,
            vu.full_name AS verified_by_name, de.verified_at
     FROM   data_entries de
     JOIN   kpis        k  ON k.id  = de.kpi_id
     JOIN   actions     a  ON a.id  = de.action_id
     JOIN   users       u  ON u.id  = de.entered_by
     LEFT JOIN users    vu ON vu.id = de.verified_by
     WHERE  de.department_id = :dept_id
     {$yearWhere}
     ORDER  BY de.year DESC, a.code, k.name"
);
$stmt->execute($params);
$entries = $stmt->fetchAll();

$summary = $pdo->prepare(
    "SELECT
        COUNT(*) AS total,
        SUM(is_verified) AS verified,
        MIN(year) AS min_year,
        MAX(year) AS max_year
     FROM data_entries
     WHERE department_id = :dept_id"
);
$summary->execute([':dept_id' => $deptId]);
$summary = $summary->fetch();

$years = range((int)($summary['max_year'] ?: date('Y')), max(2020, (int)($summary['min_year'] ?: 2020)));

require_once APP_ROOT . '/templates/shared/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h5 class="fw-bold mb-0">Veri Geçmişim</h5>
        <small class="text-muted">
            Toplam <?= (int)$summary['total'] ?> kayıt ·
            <?= (int)$summary['verified'] ?> onaylı ·
            <?= (int)$summary['total'] - (int)$summary['verified'] ?> bekliyor
        </small>
    </div>
    <a href="<?= BASE_PATH ?>/public/department/data_form.php" class="btn btn-success">
        <i class="bi bi-plus-lg me-1"></i>Yeni Veri Gir
    </a>
</div>

<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="d-flex gap-2 align-items-end flex-wrap filter-bar">
            <div>
                <label class="form-label mb-1 small">Yıl Filtrele</label>
                <select name="year" class="form-select filter-year">
                    <option value="0">Tüm Yıllar</option>
                    <?php foreach ($years as $y): ?>
                    <option value="<?= $y ?>" <?= $filterYear===$y ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <button type="submit" class="btn btn-link">Seçimleri Uygula</button>
                <a href="my_entries.php" class="btn btn-link">Filtreleri Temizle</a>
            </div>
        </form>
    </div>
</div>

<?php if (empty($entries)): ?>
<div class="card">
    <div class="card-body text-center py-5 text-muted">
        <i class="bi bi-inbox d-block mb-2" style="font-size:2.5rem;"></i>
        Henüz veri girişi bulunamadı.
    </div>
</div>
<?php else: ?>

<?php
$byYear = [];
foreach ($entries as $e) {
    $byYear[$e['year']][] = $e;
}
?>

<?php foreach ($byYear as $year => $yearEntries): ?>
<div class="mb-3">
    <div class="d-flex align-items-center gap-2 mb-2">
        <h6 class="fw-bold mb-0 text-success"><?= $year ?></h6>
        <span class="badge bg-secondary-subtle text-secondary"><?= count($yearEntries) ?> kayıt</span>
    </div>
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 text-md">
                    <thead class="table-light">
                        <tr>
                            <th>Eylem</th>
                            <th>KPI</th>
                            <th class="text-end">Değer</th>
                            <th class="text-end">Hedef</th>
                            <th class="text-center">Onay</th>
                            <th>Tarih</th>
                            <th class="text-center">Düzenle</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($yearEntries as $e): ?>
                        <?php
                            $progress = null;
                            if ($e['target_value'] > 0) {
                                $progress = min(100, (float)$e['value'] / (float)$e['target_value'] * 100);
                            }
                        ?>
                        <tr>
                            <td>
                                <code class="text-success"><?= htmlspecialchars($e['action_code'], ENT_QUOTES, 'UTF-8') ?></code>
                            </td>
                            <td>
                                <div><?= htmlspecialchars($e['kpi_name'], ENT_QUOTES, 'UTF-8') ?></div>
                                <?php if ($e['notes']): ?>
                                <small class="text-muted" title="<?= htmlspecialchars($e['notes'], ENT_QUOTES, 'UTF-8') ?>">
                                    <i class="bi bi-chat-left-text me-1"></i><?= htmlspecialchars(mb_strimwidth($e['notes'],0,40,'…'), ENT_QUOTES, 'UTF-8') ?>
                                </small>
                                <?php endif; ?>
                            </td>
                            <td class="text-end fw-bold">
                                <?= number_format((float)$e['value'],2) ?>
                                <small class="text-muted"><?= htmlspecialchars($e['unit'], ENT_QUOTES, 'UTF-8') ?></small>
                                <?php if ($progress !== null): ?>
                                <div class="progress mt-1" style="height:4px;">
                                    <div class="progress-bar bg-success" style="width:<?= $progress ?>%"></div>
                                </div>
                                <?php endif; ?>
                            </td>
                            <td class="text-end text-muted">
                                <?= $e['target_value'] !== null ? number_format((float)$e['target_value'],2) : '—' ?>
                            </td>
                            <td class="text-center">
                                <?php if ($e['is_verified']): ?>
                                    <span class="badge bg-success-subtle text-success"
                                          title="Onaylayan: <?= htmlspecialchars($e['verified_by_name'] ?? '', ENT_QUOTES, 'UTF-8') ?> · <?= $e['verified_at'] ?>">
                                        <i class="bi bi-check2-circle me-1"></i>Onaylı
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-warning-subtle text-warning">
                                        <i class="bi bi-hourglass me-1"></i>Bekliyor
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size:.78rem; white-space:nowrap;">
                                <?= date('d.m.Y', strtotime($e['updated_at'])) ?>
                            </td>
                            <td class="text-center">
                                <?php if (!$e['is_verified']): ?>
                                <a href="<?= BASE_PATH ?>/public/department/data_form.php?kpi_id=<?= (int) $e['kpi_id'] ?>&year=<?= (int) $e['year'] ?>"
                                   class="btn btn-sm btn-outline-secondary py-0 px-2">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <?php else: ?>
                                <span class="text-muted" title="Onaylanmış kayıt düzenlenemez"><i class="bi bi-lock text-md"></i></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php endforeach; ?>
<?php endif; ?>

<?php require_once APP_ROOT . '/templates/shared/footer.php'; ?>
