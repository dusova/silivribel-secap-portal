<?php

declare(strict_types=1);

require_once __DIR__ . '/../uygulama/baslat.php';
Auth::requireAdmin();

$pdo      = Database::getInstance()->getConnection();
$actionId = (int)($_GET['action_id'] ?? 0);
$kpiId    = (int)($_GET['id'] ?? 0);
$isEdit   = $kpiId > 0;
$kpi      = null;
$errors   = [];

if (!$actionId) {
    Flash::error('Eylem belirtilmedi.');
    header('Location: ' . BASE_PATH . '/yonetim/eylemler');
    exit;
}

$action = $pdo->prepare('SELECT id, code, title FROM actions WHERE id = :id AND deleted_at IS NULL LIMIT 1');
$action->execute([':id' => $actionId]);
$action = $action->fetch();
if (!$action) {
    Flash::error('Eylem bulunamadı.');
    header('Location: ' . BASE_PATH . '/yonetim/eylemler');
    exit;
}

if ($isEdit) {
    $stmt = $pdo->prepare('SELECT * FROM kpis WHERE id = :id AND action_id = :aid AND deleted_at IS NULL LIMIT 1');
    $stmt->execute([':id' => $kpiId, ':aid' => $actionId]);
    $kpi = $stmt->fetch();
    if (!$kpi) {
        Flash::error('KPI bulunamadı.');
        header("Location: " . BASE_PATH . "/yonetim/eylem-formu?id={$actionId}");
        exit;
    }
}

$pageTitle = $isEdit ? 'KPI Düzenle' : 'Yeni KPI';
$activeNav = 'actions';

$activities = $pdo->prepare("SELECT id, title FROM activities WHERE action_id = :aid AND is_active = 1 AND deleted_at IS NULL ORDER BY sort_order");
$activities->execute([':aid' => $actionId]);
$activities = $activities->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_kpi'])) {
    Csrf::check();
    if ($isEdit) {
        $oldAudit = [
            'action_id' => $actionId,
            'activity_id' => $kpi['activity_id'] ? (int) $kpi['activity_id'] : null,
            'name' => $kpi['name'],
            'is_active' => (int) $kpi['is_active'],
        ];
        $pdo->prepare('UPDATE kpis SET is_active = 0, updated_at = NOW() WHERE id = :id')->execute([':id' => $kpiId]);
        AuditLog::log($pdo, 'delete', 'kpis', $kpiId, $oldAudit, [
            'action_id' => $actionId,
            'name' => $kpi['name'],
            'is_active' => 0,
        ]);
        Flash::warning('KPI deaktive edildi.');
        header("Location: " . BASE_PATH . "/yonetim/eylem-formu?id={$actionId}");
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::check();

    $data = [
        'activity_id'    => (int)($_POST['activity_id'] ?? 0) ?: null,
        'name'           => Validator::text($_POST['name'] ?? '', 200),
        'unit'           => Validator::text($_POST['unit'] ?? '', 50),
        'description'    => Validator::textarea($_POST['description'] ?? '', 5000),
        'measurement_method' => Validator::textarea($_POST['measurement_method'] ?? '', 5000),
        'data_source'    => Validator::text($_POST['data_source'] ?? '', 255),
        'formula'        => Validator::textarea($_POST['formula'] ?? '', 5000),
        'responsible_note' => Validator::text($_POST['responsible_note'] ?? '', 255),
        'baseline_value' => $_POST['baseline_value'] !== '' ? (float)$_POST['baseline_value'] : null,
        'target_value'   => $_POST['target_value'] !== '' ? (float)$_POST['target_value'] : null,
        'target_label'   => Validator::text($_POST['target_label'] ?? '', 200) ?: null,
        'baseline_year'  => !empty($_POST['baseline_year']) ? (int)$_POST['baseline_year'] : null,
        'is_cumulative'  => isset($_POST['is_cumulative']) ? 1 : 0,
        'evidence_required' => isset($_POST['evidence_required']) ? 1 : 0,
    ];

    if ($data['name'] === '') $errors[] = 'KPI adı boş olamaz.';
    if ($data['unit'] === '') $errors[] = 'Birim boş olamaz.';

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            if ($isEdit) {
                $oldAudit = [
                    'activity_id' => $kpi['activity_id'] ? (int) $kpi['activity_id'] : null,
                    'name' => $kpi['name'],
                    'unit' => $kpi['unit'],
                    'description' => $kpi['description'],
                    'measurement_method' => $kpi['measurement_method'] ?? null,
                    'data_source' => $kpi['data_source'] ?? null,
                    'formula' => $kpi['formula'] ?? null,
                    'responsible_note' => $kpi['responsible_note'] ?? null,
                    'baseline_value' => $kpi['baseline_value'] !== null ? (float) $kpi['baseline_value'] : null,
                    'target_value' => $kpi['target_value'] !== null ? (float) $kpi['target_value'] : null,
                    'target_label' => $kpi['target_label'],
                    'baseline_year' => $kpi['baseline_year'] ? (int) $kpi['baseline_year'] : null,
                    'is_cumulative' => (int) $kpi['is_cumulative'],
                    'evidence_required' => (int) ($kpi['evidence_required'] ?? 1),
                ];

                $stmt = $pdo->prepare(
                    "UPDATE kpis SET activity_id=:act_id, name=:name, unit=:unit, description=:desc,
                     measurement_method=:mm, data_source=:ds, formula=:formula,
                     responsible_note=:rn, baseline_value=:bv, target_value=:tv, target_label=:tl, baseline_year=:by,
                     is_cumulative=:ic, evidence_required=:er WHERE id=:id"
                );
                $stmt->execute([
                    ':act_id' => $data['activity_id'], ':name' => $data['name'], ':unit' => $data['unit'],
                    ':desc' => $data['description'] ?: null, ':bv' => $data['baseline_value'],
                    ':tv' => $data['target_value'], ':tl' => $data['target_label'],
                    ':mm' => $data['measurement_method'] ?: null, ':ds' => $data['data_source'] ?: null,
                    ':formula' => $data['formula'] ?: null,
                    ':rn' => $data['responsible_note'] ?: null,
                    ':by' => $data['baseline_year'], ':ic' => $data['is_cumulative'],
                    ':er' => $data['evidence_required'],
                    ':id' => $kpiId
                ]);

                AuditLog::log($pdo, 'update', 'kpis', $kpiId, $oldAudit, [
                    'activity_id' => $data['activity_id'],
                    'name' => $data['name'],
                    'unit' => $data['unit'],
                    'description' => $data['description'] ?: null,
                    'measurement_method' => $data['measurement_method'] ?: null,
                    'data_source' => $data['data_source'] ?: null,
                    'formula' => $data['formula'] ?: null,
                    'responsible_note' => $data['responsible_note'] ?: null,
                    'baseline_value' => $data['baseline_value'],
                    'target_value' => $data['target_value'],
                    'target_label' => $data['target_label'],
                    'baseline_year' => $data['baseline_year'],
                    'is_cumulative' => $data['is_cumulative'],
                    'evidence_required' => $data['evidence_required'],
                ]);
                Flash::success('KPI güncellendi.');
            } else {
                $stmt = $pdo->prepare(
                    "INSERT INTO kpis (action_id, activity_id, name, unit, description, baseline_value,
                     measurement_method, data_source, formula, responsible_note,
                     target_value, target_label, baseline_year, is_cumulative, evidence_required)
                     VALUES (:aid, :act_id, :name, :unit, :desc, :bv, :mm, :ds, :formula, :rn, :tv, :tl, :by, :ic, :er)"
                );
                $stmt->execute([
                    ':aid' => $actionId, ':act_id' => $data['activity_id'], ':name' => $data['name'],
                    ':unit' => $data['unit'], ':desc' => $data['description'] ?: null,
                    ':bv' => $data['baseline_value'], ':mm' => $data['measurement_method'] ?: null,
                    ':ds' => $data['data_source'] ?: null, ':formula' => $data['formula'] ?: null,
                    ':rn' => $data['responsible_note'] ?: null,
                    ':tv' => $data['target_value'],
                    ':tl' => $data['target_label'], ':by' => $data['baseline_year'],
                    ':ic' => $data['is_cumulative'], ':er' => $data['evidence_required']
                ]);
                $newKpiId = (int) $pdo->lastInsertId();
                AuditLog::log($pdo, 'create', 'kpis', $newKpiId, null, [
                    'action_id' => $actionId,
                    'activity_id' => $data['activity_id'],
                    'name' => $data['name'],
                    'unit' => $data['unit'],
                    'description' => $data['description'] ?: null,
                    'measurement_method' => $data['measurement_method'] ?: null,
                    'data_source' => $data['data_source'] ?: null,
                    'formula' => $data['formula'] ?: null,
                    'responsible_note' => $data['responsible_note'] ?: null,
                    'baseline_value' => $data['baseline_value'],
                    'target_value' => $data['target_value'],
                    'target_label' => $data['target_label'],
                    'baseline_year' => $data['baseline_year'],
                    'is_cumulative' => $data['is_cumulative'],
                    'evidence_required' => $data['evidence_required'],
                ]);
                NotificationService::notifyActionDepartments(
                    $pdo,
                    $actionId,
                    'kpi_assigned',
                    'Yeni KPI atandı',
                    "{$action['code']} eylemine \"{$data['name']}\" KPI kaydı eklendi.",
                    BASE_PATH . '/mudurluk/veri-girisi?kpi_id=' . $newKpiId,
                    'normal',
                    'kpis',
                    $newKpiId,
                    'kpi_assigned:' . $newKpiId
                );
                Flash::success('KPI oluşturuldu.');
                $kpiId = $newKpiId;
            }

            $pdo->commit();
            header("Location: " . BASE_PATH . "/yonetim/eylem-formu?id={$actionId}");
            exit;
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[SECAP][DB] kpi_form hatası: ' . $e->getMessage());
            $errors[] = 'Kayıt sırasında bir hata oluştu. Lütfen tekrar deneyin.';
        }
    }

    $kpi = array_merge($kpi ?? [], $data);
}

$f = fn(string $k) => htmlspecialchars((string)($kpi[$k] ?? ''), ENT_QUOTES, 'UTF-8');

require_once APP_ROOT . '/uygulama/yerlesim/ust.php';
?>

<div class="d-flex align-items-center gap-2 mb-4">
    <a href="<?= BASE_PATH ?>/yonetim/eylem-formu?id=<?= $actionId ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
    <div>
        <h5 class="fw-bold mb-0"><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></h5>
        <small class="text-muted">
            <code class="eylem-kodu"><?= htmlspecialchars($action['code'], ENT_QUOTES, 'UTF-8') ?></code>
            <?= htmlspecialchars($action['title'], ENT_QUOTES, 'UTF-8') ?>
        </small>
    </div>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
    <ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<form method="POST" novalidate>
    <?= Csrf::field() ?>
    <div class="row g-4">

    <!-- ═══ SOL KOLON: Temel KPI Bilgileri ═══ -->
    <div class="col-12 col-lg-6">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-bar-chart me-2 text-primary"></i>KPI Bilgileri</div>
            <div class="card-body">
                <div class="row g-3">
                <div class="col-12">
                    <label class="form-label fw-semibold">KPI Adı (Bildirilecek Veri) <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" value="<?= $f('name') ?>"
                           placeholder="Örn: Faaliyet sayısı" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Birim <span class="text-danger">*</span></label>
                    <input type="text" name="unit" class="form-control" value="<?= $f('unit') ?>"
                           placeholder="Adet, kWh, ton..." required>
                </div>
                <div class="col-md-8">
                    <label class="form-label fw-semibold">Bağlı Faaliyet</label>
                    <select name="activity_id" class="form-select">
                        <option value="">— Doğrudan Eyleme Bağlı —</option>
                        <?php foreach ($activities as $act): ?>
                        <option value="<?= $act['id'] ?>" <?= (int)($kpi['activity_id'] ?? 0) === (int)$act['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($act['title'], ENT_QUOTES, 'UTF-8') ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold">Açıklama</label>
                    <textarea name="description" class="form-control" rows="2"><?= $f('description') ?></textarea>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Hedef Değer</label>
                    <input type="number" name="target_value" class="form-control" step="0.01"
                           value="<?= $kpi['target_value'] ?? '' ?>">
                </div>
                <div class="col-md-8">
                    <label class="form-label fw-semibold">Hedef Açıklaması</label>
                    <input type="text" name="target_label" class="form-control" value="<?= $f('target_label') ?>"
                           placeholder="Örn: 30 adet GES uygulaması">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Başlangıç Değeri</label>
                    <input type="number" name="baseline_value" class="form-control" step="0.01"
                           value="<?= $kpi['baseline_value'] ?? '' ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Referans Yılı</label>
                    <input type="number" name="baseline_year" class="form-control" min="2015" max="2050"
                           value="<?= $kpi['baseline_year'] ?? '' ?>">
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="is_cumulative" id="isCumulative"
                               <?= ($kpi['is_cumulative'] ?? 0) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="isCumulative">Kümülatif KPI</label>
                    </div>
                </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══ SAĞ KOLON: Veri Sözlüğü ve Ayarlar ═══ -->
    <div class="col-12 col-lg-6">
        <!-- Veri Sözlüğü -->
        <div class="card mb-4">
            <div class="card-header"><i class="bi bi-journal-text me-2 text-primary"></i>KPI Veri Sözlüğü</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Ölçüm Yöntemi</label>
                        <textarea name="measurement_method" class="form-control" rows="2"
                                  placeholder="Veri nasıl ölçülüyor veya doğrulanıyor?"><?= $f('measurement_method') ?></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Veri Kaynağı</label>
                        <input type="text" name="data_source" class="form-control" value="<?= $f('data_source') ?>"
                               placeholder="Örn: sayaç raporu, saha formu, kurum yazısı">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Formül</label>
                        <textarea name="formula" class="form-control" rows="2"
                                  placeholder="Varsa hesaplama yöntemi"><?= $f('formula') ?></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Sorumlu / Not</label>
                        <input type="text" name="responsible_note" class="form-control" value="<?= $f('responsible_note') ?>"
                               placeholder="Birim içi sorumlu">
                    </div>
                    <div class="col-12">
                        <div class="form-check form-switch mt-2">
                            <input class="form-check-input" type="checkbox" name="evidence_required" id="evidenceRequired"
                                   <?= (int)($kpi['evidence_required'] ?? 1) === 1 ? 'checked' : '' ?>>
                            <label class="form-check-label fw-semibold" for="evidenceRequired">Kanıt dosyası zorunlu</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    </div><!-- /row -->

    <div class="mt-4 d-flex gap-2">
        <button type="submit" class="btn btn-success px-4">
            <i class="bi bi-<?= $isEdit ? 'check-lg' : 'plus-lg' ?> me-1"></i><?= $isEdit ? 'Güncelle' : 'Oluştur' ?>
        </button>
        <a href="<?= BASE_PATH ?>/yonetim/eylem-formu?id=<?= $actionId ?>" class="btn btn-outline-secondary">İptal</a>
        <?php if ($isEdit): ?>
        <button type="submit" name="delete_kpi" value="1"
                class="btn btn-outline-danger ms-auto"
                onclick="return confirm('Bu KPI\'yı deaktive etmek istediğinize emin misiniz?')">
            <i class="bi bi-trash me-1"></i>Deaktive Et
        </button>
        <?php endif; ?>
    </div>
</form>

<?php require_once APP_ROOT . '/uygulama/yerlesim/alt.php'; ?>
