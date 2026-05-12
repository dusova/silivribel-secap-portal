<?php

declare(strict_types=1);

require_once __DIR__ . '/../uygulama/baslat.php';
Auth::requireSuperAdmin();

$pdo       = Database::getInstance()->getConnection();
$pageTitle = 'Çöp Kutusu';
$activeNav = 'trash';

$tabs = [
    'actions'      => ['label' => 'Eylemler',       'icon' => 'bi-lightning-charge'],
    'activities'   => ['label' => 'Faaliyetler',    'icon' => 'bi-list-task'],
    'kpis'         => ['label' => 'KPI\'lar',       'icon' => 'bi-bar-chart'],
    'data_entries' => ['label' => 'Veri Girişleri', 'icon' => 'bi-table'],
    'users'        => ['label' => 'Kullanıcılar',   'icon' => 'bi-people'],
];

$currentTab = $_GET['tab'] ?? 'actions';
if (!isset($tabs[$currentTab])) {
    $currentTab = 'actions';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restore_id'], $_POST['restore_table'])) {
    Csrf::check();
    $table = (string) $_POST['restore_table'];
    $id    = (int) $_POST['restore_id'];
    try {
        if (SoftDelete::restore($pdo, $table, $id)) {
            Flash::success('Kayıt başarıyla geri alındı.');
        } else {
            Flash::error('Geri alma işlemi gerçekleştirilemedi.');
        }
    } catch (InvalidArgumentException $e) {
        Flash::error($e->getMessage());
    } catch (Throwable $e) {
        error_log('[SECAP][SoftDelete][restore] ' . $e->getMessage());
        Flash::error('Geri alma sırasında bir hata oluştu.');
    }
    header('Location: ' . BASE_PATH . '/yonetim/cop-kutusu?tab=' . urlencode($table));
    exit;
}

$counts = SoftDelete::countTrashed($pdo);
$rows   = SoftDelete::fetchTrashed($pdo, $currentTab, 300);

require_once APP_ROOT . '/uygulama/yerlesim/ust.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h5 class="fw-bold mb-0"><i class="bi bi-trash me-2 text-danger"></i>Çöp Kutusu</h5>
        <small class="text-muted">Silinmiş kayıtları görüntüle, geri al. Toplam: <?= array_sum($counts) ?></small>
    </div>
</div>

<ul class="nav nav-tabs mb-3">
    <?php foreach ($tabs as $key => $cfg): ?>
    <li class="nav-item">
        <a class="nav-link <?= $currentTab === $key ? 'active' : '' ?>"
           href="?tab=<?= urlencode($key) ?>">
            <i class="bi <?= $cfg['icon'] ?> me-1"></i>
            <?= htmlspecialchars($cfg['label'], ENT_QUOTES, 'UTF-8') ?>
            <span class="badge bg-secondary ms-1"><?= (int) ($counts[$key] ?? 0) ?></span>
        </a>
    </li>
    <?php endforeach; ?>
</ul>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0 yazi-orta align-middle">
            <thead class="table-light">
                <?php if ($currentTab === 'users'): ?>
                <tr>
                    <th>Kullanıcı</th>
                    <th>Kullanıcı Adı</th>
                    <th>Rol</th>
                    <th>Müdürlük</th>
                    <th>Silen</th>
                    <th>Silinme</th>
                    <th>Sebep</th>
                    <th class="text-end">Aksiyon</th>
                </tr>
                <?php elseif ($currentTab === 'data_entries'): ?>
                <tr>
                    <th>KPI</th>
                    <th>Eylem</th>
                    <th>Müdürlük</th>
                    <th>Değer</th>
                    <th>Silen</th>
                    <th>Silinme</th>
                    <th>Sebep</th>
                    <th class="text-end">Aksiyon</th>
                </tr>
                <?php elseif ($currentTab === 'kpis'): ?>
                <tr>
                    <th>KPI</th>
                    <th>Eylem</th>
                    <th>Birim</th>
                    <th>Silen</th>
                    <th>Silinme</th>
                    <th>Sebep</th>
                    <th class="text-end">Aksiyon</th>
                </tr>
                <?php elseif ($currentTab === 'activities'): ?>
                <tr>
                    <th>Faaliyet</th>
                    <th>Eylem Kodu</th>
                    <th>Müdürlük</th>
                    <th>Silen</th>
                    <th>Silinme</th>
                    <th>Sebep</th>
                    <th class="text-end">Aksiyon</th>
                </tr>
                <?php else:  ?>
                <tr>
                    <th>Kod</th>
                    <th>Başlık</th>
                    <th>Müdürlük</th>
                    <th>Silen</th>
                    <th>Silinme</th>
                    <th>Sebep</th>
                    <th class="text-end">Aksiyon</th>
                </tr>
                <?php endif; ?>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                <tr>
                    <td colspan="9" class="text-center text-muted py-4">
                        <i class="bi bi-trash2 me-1"></i>Bu sekmede silinmiş kayıt bulunmuyor.
                    </td>
                </tr>
                <?php else: foreach ($rows as $r): ?>
                <tr>
                    <?php if ($currentTab === 'users'): ?>
                        <td class="fw-medium"><?= htmlspecialchars((string) ($r['full_name'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><small><?= htmlspecialchars((string) ($r['username'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></small></td>
                        <td><small class="text-muted"><?= htmlspecialchars((string) ($r['role'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></small></td>
                        <td><small><?= htmlspecialchars((string) ($r['dept_name'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></small></td>
                    <?php elseif ($currentTab === 'data_entries'): ?>
                        <td class="fw-medium"><?= htmlspecialchars((string) ($r['kpi_name'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><small><?= htmlspecialchars((string) ($r['action_code'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></small></td>
                        <td><small><?= htmlspecialchars((string) ($r['dept_name'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></small></td>
                        <td><?= htmlspecialchars((string) ($r['value'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                    <?php elseif ($currentTab === 'kpis'): ?>
                        <td class="fw-medium"><?= htmlspecialchars((string) ($r['name'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><small><?= htmlspecialchars((string) ($r['action_code'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></small></td>
                        <td><small><?= htmlspecialchars((string) ($r['unit'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></small></td>
                    <?php elseif ($currentTab === 'activities'): ?>
                        <td class="fw-medium"><?= htmlspecialchars((string) ($r['title'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><small><?= htmlspecialchars((string) ($r['action_code'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></small></td>
                        <td><small><?= htmlspecialchars((string) ($r['dept_name'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></small></td>
                    <?php else: ?>
                        <td><code><?= htmlspecialchars((string) ($r['code'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></code></td>
                        <td class="fw-medium text-truncate" style="max-width:340px;"><?= htmlspecialchars((string) ($r['title'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><small><?= htmlspecialchars((string) ($r['dept_name'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></small></td>
                    <?php endif; ?>

                    <td><small class="text-muted"><?= htmlspecialchars((string) ($r['deleter_name'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></small></td>
                    <td><small><?= htmlspecialchars((string) ($r['deleted_at'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></small></td>
                    <td><small class="text-truncate d-inline-block" style="max-width:260px;" title="<?= htmlspecialchars((string) ($r['delete_reason'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                        <?= htmlspecialchars((string) ($r['delete_reason'] ?? '—'), ENT_QUOTES, 'UTF-8') ?>
                    </small></td>
                    <td class="text-end">
                        <form method="POST" class="d-inline" onsubmit="return confirm('Kayıt geri alınacak. Onaylıyor musunuz?');">
                            <?= Csrf::field() ?>
                            <input type="hidden" name="restore_table" value="<?= htmlspecialchars($currentTab, ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="restore_id" value="<?= htmlentities((string) (int) $r['id'], ENT_QUOTES, 'UTF-8') ?>">
                            <button type="submit" class="btn btn-sm btn-outline-success">
                                <i class="bi bi-arrow-counterclockwise me-1"></i>Geri Al
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once APP_ROOT . '/uygulama/yerlesim/alt.php'; ?>
