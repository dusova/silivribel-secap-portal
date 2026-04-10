<?php

require_once __DIR__ . '/../../bootstrap.php';
Auth::requireAdmin();

$pdo    = Database::getInstance()->getConnection();
$id     = (int)($_GET['id'] ?? 0);
$isEdit = $id > 0;
$action = null;
$errors = [];

if ($isEdit) {
    $stmt = $pdo->prepare('SELECT * FROM actions WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $action = $stmt->fetch();
    if (!$action) {
        Flash::error('Eylem bulunamadı.');
        header('Location: ' . BASE_PATH . '/public/admin/actions.php');
        exit;
    }
    $adStmt = $pdo->prepare('SELECT department_id FROM action_departments WHERE action_id = :id');
    $adStmt->execute([':id' => $id]);
    $assignedDepts = array_column($adStmt->fetchAll(), 'department_id');
}

$pageTitle = $isEdit ? 'Eylem Düzenle' : 'Yeni Eylem';
$activeNav = 'actions';

$departments = $pdo->query("SELECT id, name FROM departments WHERE is_active=1 ORDER BY name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::check();

    $data = [
        'responsible_department_id' => (int)($_POST['responsible_department_id'] ?? 0),
        'code'                      => Validator::text($_POST['code'] ?? '', 30),
        'title'                     => Validator::text($_POST['title'] ?? '', 500),
        'description'               => Validator::textarea($_POST['description'] ?? '', 5000),
        'performance_indicators'    => Validator::textarea($_POST['performance_indicators'] ?? '', 5000),
        'category'                  => Validator::text($_POST['category'] ?? '', 100),
        'start_year'                => (int)($_POST['start_year'] ?? 0),
        'end_year'                  => !empty($_POST['end_year']) ? (int)$_POST['end_year'] : null,
        'status'                    => Validator::enum($_POST['status'] ?? '', ['planned', 'ongoing', 'completed', 'cancelled'], 'planned'),
    ];
    $contributorDepts = Validator::intArray($_POST['contributor_depts'] ?? []);

    if ($data['responsible_department_id'] <= 0) $errors[] = 'Birincil sorumlu müdürlük seçilmeli.';
    if ($data['code'] === '')   $errors[] = 'Eylem kodu boş olamaz.';
    if ($data['title'] === '')  $errors[] = 'Eylem başlığı boş olamaz.';
    if ($data['start_year'] < 2020 || $data['start_year'] > 2050) $errors[] = 'Geçerli bir başlangıç yılı girin.';

    if (empty($errors)) {
        $dup = $pdo->prepare('SELECT id FROM actions WHERE code = :c AND id != :id LIMIT 1');
        $dup->execute([':c' => $data['code'], ':id' => $isEdit ? $id : 0]);
        if ($dup->fetch()) $errors[] = "'{$data['code']}' kodu zaten kullanılıyor.";
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            $auditBefore = null;

            if ($isEdit) {
                $auditBefore = [
                    'responsible_department_id' => (int) $action['responsible_department_id'],
                    'code' => $action['code'],
                    'title' => $action['title'],
                    'description' => $action['description'],
                    'performance_indicators' => $action['performance_indicators'],
                    'category' => $action['category'],
                    'start_year' => $action['start_year'],
                    'end_year' => $action['end_year'],
                    'status' => $action['status'],
                    'contributor_depts' => array_map('intval', $assignedDepts ?? []),
                ];
                $stmt = $pdo->prepare(
                    "UPDATE actions SET responsible_department_id=:dept, code=:code, title=:title,
                     description=:desc, performance_indicators=:perf, category=:cat,
                     start_year=:sy, end_year=:ey, status=:st WHERE id=:id"
                );
                $stmt->execute([
                    ':dept' => $data['responsible_department_id'], ':code' => $data['code'],
                    ':title' => $data['title'], ':desc' => $data['description'] ?: null,
                    ':perf' => $data['performance_indicators'] ?: null, ':cat' => $data['category'] ?: null,
                    ':sy' => $data['start_year'], ':ey' => $data['end_year'], ':st' => $data['status'],
                    ':id' => $id
                ]);
            } else {
                $stmt = $pdo->prepare(
                    "INSERT INTO actions (responsible_department_id, code, title, description,
                     performance_indicators, category, start_year, end_year, status, created_by)
                     VALUES (:dept, :code, :title, :desc, :perf, :cat, :sy, :ey, :st, :cb)"
                );
                $stmt->execute([
                    ':dept' => $data['responsible_department_id'], ':code' => $data['code'],
                    ':title' => $data['title'], ':desc' => $data['description'] ?: null,
                    ':perf' => $data['performance_indicators'] ?: null, ':cat' => $data['category'] ?: null,
                    ':sy' => $data['start_year'], ':ey' => $data['end_year'], ':st' => $data['status'],
                    ':cb' => Auth::getUserId()
                ]);
                $id = (int)$pdo->lastInsertId();
            }

            $pdo->prepare('DELETE FROM action_departments WHERE action_id = :id')->execute([':id' => $id]);
            $insAd = $pdo->prepare('INSERT INTO action_departments (action_id, department_id, role_type) VALUES (:a, :d, :r)');
            $insAd->execute([':a' => $id, ':d' => $data['responsible_department_id'], ':r' => 'primary']);
            foreach ($contributorDepts as $cdId) {
                if ($cdId > 0 && $cdId !== $data['responsible_department_id']) {
                    $insAd->execute([':a' => $id, ':d' => $cdId, ':r' => 'contributor']);
                }
            }

            $auditAfter = [
                'responsible_department_id' => $data['responsible_department_id'],
                'code' => $data['code'],
                'title' => $data['title'],
                'description' => $data['description'] ?: null,
                'performance_indicators' => $data['performance_indicators'] ?: null,
                'category' => $data['category'] ?: null,
                'start_year' => $data['start_year'],
                'end_year' => $data['end_year'],
                'status' => $data['status'],
                'contributor_depts' => array_values(array_filter(
                    $contributorDepts,
                    static fn(int $deptId): bool => $deptId !== $data['responsible_department_id']
                )),
            ];

            AuditLog::log(
                $pdo,
                $isEdit ? 'update' : 'create',
                'actions',
                $id,
                $auditBefore,
                $auditAfter
            );

            $pdo->commit();
            Flash::success(($isEdit ? 'Güncellendi' : 'Oluşturuldu') . ": {$data['code']}");
            header('Location: ' . BASE_PATH . '/public/admin/actions.php');
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log('[SECAP][DB] action_form hatası: ' . $e->getMessage());
            $errors[] = 'Kayıt sırasında bir hata oluştu. Lütfen tekrar deneyin.';
        }
    }

    $action = array_merge($action ?? [], $data);
    $assignedDepts = $contributorDepts;
}

$f = fn(string $k) => htmlspecialchars((string)($action[$k] ?? ''), ENT_QUOTES, 'UTF-8');
$assignedDepts = $assignedDepts ?? [];

require_once APP_ROOT . '/templates/shared/header.php';
?>

<div class="d-flex align-items-center gap-2 mb-4">
    <a href="<?= BASE_PATH ?>/public/admin/actions.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
    <h5 class="fw-bold mb-0"><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></h5>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
    <ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<div class="card" style="max-width:700px;">
    <div class="card-body">
        <form method="POST" novalidate>
            <?= Csrf::field() ?>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Eylem Kodu <span class="text-danger">*</span></label>
                    <input type="text" name="code" class="form-control font-monospace" value="<?= $f('code') ?>" placeholder="A.1.H.2.2" required>
                </div>
                <div class="col-md-8">
                    <label class="form-label fw-semibold">Eylem Başlığı <span class="text-danger">*</span></label>
                    <input type="text" name="title" class="form-control" value="<?= $f('title') ?>" required>
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold">Proje Açıklaması</label>
                    <textarea name="description" class="form-control" rows="3" placeholder="Eylem hakkında detaylı açıklama..."><?= $f('description') ?></textarea>
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold">Performans Göstergeleri</label>
                    <textarea name="performance_indicators" class="form-control" rows="2" placeholder="KPI özet metni..."><?= $f('performance_indicators') ?></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Birincil Sorumlu Müdürlük <span class="text-danger">*</span></label>
                    <select name="responsible_department_id" class="form-select" required>
                        <option value="">Seçiniz…</option>
                        <?php foreach ($departments as $d): ?>
                        <option value="<?= $d['id'] ?>" <?= (int)($action['responsible_department_id'] ?? 0) === (int)$d['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($d['name'], ENT_QUOTES, 'UTF-8') ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Kategori</label>
                    <input type="text" name="category" class="form-control" value="<?= $f('category') ?>" placeholder="Enerji, Ulaşım, Atık...">
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold">Ek Sorumlu Müdürlükler</label>
                    <div class="row g-2">
                        <?php foreach ($departments as $d): ?>
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="contributor_depts[]"
                                       value="<?= $d['id'] ?>" id="cd<?= $d['id'] ?>"
                                       <?= in_array($d['id'], $assignedDepts) ? 'checked' : '' ?>>
                                <label class="form-check-label text-sm-plus" for="cd<?= $d['id'] ?>">
                                    <?= htmlspecialchars($d['name'], ENT_QUOTES, 'UTF-8') ?>
                                </label>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Başlangıç Yılı <span class="text-danger">*</span></label>
                    <input type="number" name="start_year" class="form-control" min="2020" max="2050"
                           value="<?= (int)($action['start_year'] ?? date('Y')) ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Bitiş Yılı</label>
                    <input type="number" name="end_year" class="form-control" min="2020" max="2050"
                           value="<?= ($action['end_year'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Durum</label>
                    <select name="status" class="form-select">
                        <?php foreach (['planned'=>'Planlandı','ongoing'=>'Devam Ediyor','completed'=>'Tamamlandı','cancelled'=>'İptal'] as $v=>$l): ?>
                        <option value="<?= $v ?>" <?= ($action['status'] ?? 'planned') === $v ? 'selected' : '' ?>><?= $l ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-success px-4">
                    <i class="bi bi-<?= $isEdit ? 'check-lg' : 'plus-lg' ?> me-1"></i><?= $isEdit ? 'Güncelle' : 'Oluştur' ?>
                </button>
                <a href="<?= BASE_PATH ?>/public/admin/actions.php" class="btn btn-outline-secondary">İptal</a>
            </div>
        </form>
    </div>
</div>

<?php if ($isEdit): ?>
<div class="row g-3 mt-3" style="max-width:700px;">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-layers me-2 text-success"></i>Faaliyetler</span>
                <a href="<?= BASE_PATH ?>/public/admin/activity_form.php?action_id=<?= $id ?>" class="btn btn-sm btn-outline-success">
                    <i class="bi bi-plus me-1"></i>Faaliyet Ekle
                </a>
            </div>
            <div class="card-body p-0">
                <?php
                $acts = $pdo->prepare("SELECT act.*, d.name AS dept_name FROM activities act JOIN departments d ON d.id = act.department_id WHERE act.action_id = :id ORDER BY act.sort_order");
                $acts->execute([':id' => $id]);
                $acts = $acts->fetchAll();
                ?>
                <?php if (empty($acts)): ?>
                <div class="text-center text-muted py-3 text-md">Henüz faaliyet eklenmemiş.</div>
                <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($acts as $act): ?>
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <div class="fw-medium text-md"><?= htmlspecialchars($act['title'], ENT_QUOTES, 'UTF-8') ?></div>
                            <small class="text-muted"><?= htmlspecialchars($act['dept_name'], ENT_QUOTES, 'UTF-8') ?></small>
                        </div>
                        <a href="<?= BASE_PATH ?>/public/admin/activity_form.php?id=<?= $act['id'] ?>" class="btn btn-sm btn-outline-secondary py-0 px-2">
                            <i class="bi bi-pencil"></i>
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-bar-chart me-2 text-primary"></i>KPI'lar</span>
                <a href="<?= BASE_PATH ?>/public/admin/kpi_form.php?action_id=<?= $id ?>" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-plus me-1"></i>KPI Ekle
                </a>
            </div>
            <div class="card-body p-0">
                <?php
                $kpis = $pdo->prepare("SELECT * FROM kpis WHERE action_id = :id AND is_active = 1 ORDER BY id");
                $kpis->execute([':id' => $id]);
                $kpis = $kpis->fetchAll();
                ?>
                <?php if (empty($kpis)): ?>
                <div class="text-center text-muted py-3 text-md">Henüz KPI tanımlanmamış.</div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0 text-md">
                        <thead class="table-light"><tr><th>KPI</th><th>Birim</th><th>Hedef</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($kpis as $k): ?>
                        <tr>
                            <td class="fw-medium"><?= htmlspecialchars($k['name'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($k['unit'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= $k['target_value'] !== null ? number_format((float)$k['target_value'], 0) : '—' ?></td>
                            <td class="text-end">
                                <a href="<?= BASE_PATH ?>/public/admin/kpi_form.php?action_id=<?= $id ?>&id=<?= $k['id'] ?>"
                                   class="btn btn-sm btn-outline-secondary py-0 px-2"><i class="bi bi-pencil"></i></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once APP_ROOT . '/templates/shared/footer.php'; ?>
