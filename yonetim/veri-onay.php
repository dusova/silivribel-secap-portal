<?php

declare(strict_types=1);

require_once __DIR__ . '/../uygulama/baslat.php';
Auth::requireAdmin();

$pdo       = Database::getInstance()->getConnection();
$pageTitle = 'Veri Girişleri';
$activeNav = 'entries';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['soft_delete_id'])) {
    Auth::requireSuperAdmin();
    Csrf::check();
    $targetId = (int) $_POST['soft_delete_id'];
    $reason   = Validator::text($_POST['delete_reason'] ?? '', 500);
    try {
        $snapStmt = $pdo->prepare(
            'SELECT de.*, k.name AS kpi_name, a.code AS action_code
             FROM data_entries de
             LEFT JOIN kpis k ON k.id = de.kpi_id
             LEFT JOIN actions a ON a.id = de.action_id
             WHERE de.id = :id AND de.deleted_at IS NULL
             LIMIT 1'
        );
        $snapStmt->execute([':id' => $targetId]);
        $snapshot = $snapStmt->fetch() ?: [];
        if (empty($snapshot)) {
            Flash::error('Kayıt bulunamadı veya zaten silinmiş.');
        } elseif (SoftDelete::delete($pdo, 'data_entries', $targetId, $reason, $snapshot)) {
            Flash::success('Veri girişi çöp kutusuna taşındı.');
        } else {
            Flash::error('Silme işlemi gerçekleştirilemedi.');
        }
    } catch (InvalidArgumentException $e) {
        Flash::error($e->getMessage());
    } catch (Throwable $e) {
        error_log('[SECAP][SoftDelete][data_entries] ' . $e->getMessage());
        Flash::error('Silme işlemi sırasında bir hata oluştu.');
    }
    header('Location: ' . BASE_PATH . '/yonetim/veri-onay');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_verify'])) {
    Csrf::check();
    $bulkComment = Validator::textarea($_POST['bulk_review_comment'] ?? '', 1000, true);
    if ($bulkComment === '') {
        Flash::error('Toplu onay için değerlendirme notu zorunludur.');
        header('Location: ' . BASE_PATH . '/yonetim/veri-onay');
        exit;
    }
    $ids = $_POST['entry_ids'] ?? [];
    if (!empty($ids)) {
        $intIds = array_map('intval', $ids);
        $placeholders = implode(',', array_fill(0, count($intIds), '?'));
        $stmt = $pdo->prepare(
            "UPDATE data_entries
             SET workflow_status = 'approved',
                 is_verified = 1,
                 verified_by = ?,
                 verified_at = NOW(),
                 reviewed_by = ?,
                 reviewed_at = NOW(),
                 review_comment = ?
             WHERE id IN ({$placeholders})
               AND workflow_status IN ('submitted','needs_revision')
               AND deleted_at IS NULL
               AND (SELECT COUNT(*) FROM entry_attachments ea WHERE ea.data_entry_id = data_entries.id AND ea.deleted_at IS NULL) > 0"
        );
        $stmt->execute(array_merge([Auth::getUserId(), Auth::getUserId(), $bulkComment], $intIds));

        foreach ($intIds as $eid) {
            AuditLog::log($pdo, 'verify', 'data_entries', $eid, [
                'workflow_status' => 'submitted',
            ], [
                'workflow_status' => 'approved',
                'review_comment' => $bulkComment,
                'verified_by' => Auth::getUserId(),
                'bulk' => true,
            ]);
        }

        Flash::success(count($intIds) . ' kayıt onaylandı.');
    }
    header('Location: ' . BASE_PATH . '/yonetim/veri-onay');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['review_entry_id'])) {
    Csrf::check();
    $eid = (int) $_POST['review_entry_id'];
    $reviewAction = Validator::enum($_POST['review_action'] ?? '', ['approve', 'revision', 'reject', 'unapprove'], 'revision');
    $comment = Validator::textarea($_POST['review_comment'] ?? '', 1000, true);

    if ($comment === '') {
        Flash::error('Değerlendirme yorumu zorunludur.');
        header('Location: ' . BASE_PATH . '/yonetim/veri-onay');
        exit;
    }

    $entryStmt = $pdo->prepare(
        "SELECT de.*, k.name AS kpi_name, a.code AS action_code
         FROM data_entries de
         JOIN kpis k ON k.id = de.kpi_id
         JOIN actions a ON a.id = de.action_id
         WHERE de.id = :id AND de.deleted_at IS NULL
         LIMIT 1"
    );
    $entryStmt->execute([':id' => $eid]);
    $entry = $entryStmt->fetch();
    if (!$entry) {
        Flash::error('Veri kaydı bulunamadı.');
        header('Location: ' . BASE_PATH . '/yonetim/veri-onay');
        exit;
    }

    if ($reviewAction === 'approve' && EntryAttachment::countForEntry($pdo, $eid) === 0) {
        Flash::error('Kanıt dosyası olmayan veri onaylanamaz.');
        header('Location: ' . BASE_PATH . '/yonetim/veri-onay');
        exit;
    }

    $statusMap = [
        'approve' => ['status' => 'approved', 'verified' => 1, 'audit' => 'verify', 'flash' => 'Veri onaylandı.'],
        'revision' => ['status' => 'needs_revision', 'verified' => 0, 'audit' => 'update', 'flash' => 'Düzeltme istendi.'],
        'reject' => ['status' => 'rejected', 'verified' => 0, 'audit' => 'unverify', 'flash' => 'Veri reddedildi.'],
        'unapprove' => ['status' => 'submitted', 'verified' => 0, 'audit' => 'unverify', 'flash' => 'Onay kaldırıldı.'],
    ];
    $next = $statusMap[$reviewAction];

    $pdo->prepare(
        "UPDATE data_entries
         SET workflow_status = :status,
             is_verified = :verified,
             verified_by = CASE WHEN :verified2 = 1 THEN :uid ELSE NULL END,
             verified_at = CASE WHEN :verified3 = 1 THEN NOW() ELSE NULL END,
             reviewed_by = :uid2,
             reviewed_at = NOW(),
             review_comment = :comment
         WHERE id = :id AND deleted_at IS NULL"
    )->execute([
        ':status' => $next['status'],
        ':verified' => $next['verified'],
        ':verified2' => $next['verified'],
        ':verified3' => $next['verified'],
        ':uid' => Auth::getUserId(),
        ':uid2' => Auth::getUserId(),
        ':comment' => $comment,
        ':id' => $eid,
    ]);

    AuditLog::log($pdo, $next['audit'], 'data_entries', $eid, [
        'workflow_status' => $entry['workflow_status'],
        'is_verified' => (int) $entry['is_verified'],
    ], [
        'workflow_status' => $next['status'],
        'is_verified' => $next['verified'],
        'review_comment' => $comment,
    ]);

    $eventKey = [
        'approved' => 'entry_approved',
        'needs_revision' => 'entry_revision',
        'rejected' => 'entry_rejected',
        'submitted' => 'pending_review',
    ][$next['status']] ?? 'generic';
    NotificationService::notifyUser(
        $pdo,
        (int) $entry['entered_by'],
        $eventKey,
        'Veri değerlendirmesi güncellendi',
        $entry['action_code'] . ' / ' . $entry['kpi_name'] . ' veriniz için durum: ' . V2::ENTRY_STATUSES[$next['status']]['label'] . ". Not: {$comment}",
        BASE_PATH . '/mudurluk/veri-gecmisim',
        in_array($next['status'], ['approved', 'needs_revision', 'rejected'], true) ? 'high' : 'normal',
        'data_entries',
        $eid
    );
    Flash::success($next['flash']);
    header('Location: ' . BASE_PATH . '/yonetim/veri-onay');
    exit;
}

$filterYear = (int)($_GET['year'] ?? 0);
$filterDept = (int)($_GET['dept_id'] ?? 0);
$filterVerified = $_GET['verified'] ?? '';
$filterWorkflow = (string)($_GET['workflow_status'] ?? '');
$workflowOptions = ['' => 'Tümü', 'submitted' => 'Onay Bekliyor', 'needs_revision' => 'Düzeltme İstendi', 'approved' => 'Onaylı', 'rejected' => 'Reddedildi'];
if (!array_key_exists($filterWorkflow, $workflowOptions)) {
    $filterWorkflow = '';
}

$stmt = $pdo->prepare(
    "SELECT de.*, k.name AS kpi_name, k.unit, a.code AS action_code, a.title AS action_title,
            d.name AS dept_name, u.full_name AS entered_by_name,
            vu.full_name AS verified_by_name,
            de.workflow_status, de.review_comment, de.reviewed_at,
            (SELECT COUNT(*) FROM entry_attachments ea WHERE ea.data_entry_id = de.id AND ea.deleted_at IS NULL) AS attachment_count
     FROM   data_entries de
     JOIN   kpis    k  ON k.id  = de.kpi_id
     JOIN   actions a  ON a.id  = de.action_id
     JOIN   departments d  ON d.id  = de.department_id
     JOIN   users   u  ON u.id  = de.entered_by
     LEFT JOIN users vu ON vu.id = de.verified_by
     WHERE  de.deleted_at IS NULL
       AND  a.deleted_at IS NULL
       AND  (:year_filter_on = 0 OR de.year = :year_value)
       AND  (:dept_filter_on = 0 OR de.department_id = :dept_id)
       AND  (:verified_filter_on = 0 OR de.is_verified = :verified_value)
       AND  (:workflow_filter_on = 0 OR de.workflow_status = :workflow_value)
     ORDER  BY de.created_at DESC
     LIMIT 200"
);
$stmt->execute([
    ':year_filter_on' => $filterYear > 0 ? 1 : 0,
    ':year_value' => $filterYear,
    ':dept_filter_on' => $filterDept > 0 ? 1 : 0,
    ':dept_id' => $filterDept,
    ':verified_filter_on' => $filterVerified !== '' ? 1 : 0,
    ':verified_value' => (int) $filterVerified,
    ':workflow_filter_on' => $filterWorkflow !== '' ? 1 : 0,
    ':workflow_value' => $filterWorkflow,
]);
$entries = $stmt->fetchAll();

$departments = $pdo->query("SELECT id, name FROM departments WHERE is_active=1 ORDER BY name")->fetchAll();

$pendingCount = count(array_filter($entries, fn($e) => in_array((string)($e['workflow_status'] ?? ''), ['submitted', 'needs_revision'], true)));
$attachmentsByEntry = [];
if (!empty($entries)) {
    $entryIds = array_map('intval', array_column($entries, 'id'));
    $in = implode(',', array_fill(0, count($entryIds), '?'));
    $attStmt = $pdo->prepare(
        "SELECT id, data_entry_id, original_name, file_size, created_at
         FROM entry_attachments
         WHERE data_entry_id IN ({$in}) AND deleted_at IS NULL
         ORDER BY created_at DESC"
    );
    $attStmt->execute($entryIds);
    foreach ($attStmt->fetchAll() as $att) {
        $attachmentsByEntry[(int) $att['data_entry_id']][] = $att;
    }
}

require_once APP_ROOT . '/uygulama/yerlesim/ust.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h5 class="fw-bold mb-0">Veri Girişleri</h5>
        <small class="text-muted"><?= count($entries) ?> kayıt · <?= $pendingCount ?> onay bekliyor</small>
    </div>
    <a href="<?= BASE_PATH ?>/mudurluk/veri-girisi" class="btn btn-success">
        <i class="bi bi-plus-lg me-1"></i>Yeni Giriş
    </a>
</div>

<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end filtre-cubugu">
            <div class="col-auto">
                <label class="form-label mb-1 small">Yıl</label>
                <select name="year" class="form-select filtre-yil">
                    <option value="0">Tümü</option>
                    <?php for ($y = (int)date('Y'); $y >= 2020; $y--): ?>
                    <option value="<?= $y ?>" <?= $filterYear===$y ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-auto">
                <label class="form-label mb-1 small">Müdürlük</label>
                <select name="dept_id" class="form-select filtre-mudurluk">
                    <option value="0">Tümü</option>
                    <?php foreach ($departments as $d): ?>
                    <option value="<?= $d['id'] ?>" <?= $filterDept===(int)$d['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($d['name'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <label class="form-label mb-1 small">Onay</label>
                <select name="verified" class="form-select filtre-durum">
                    <option value="">Tümü</option>
                    <option value="0" <?= $filterVerified==='0' ? 'selected' : '' ?>>Bekleyen</option>
                    <option value="1" <?= $filterVerified==='1' ? 'selected' : '' ?>>Onaylı</option>
                </select>
            </div>
            <div class="col-auto">
                <label class="form-label mb-1 small">Workflow</label>
                <select name="workflow_status" class="form-select filtre-durum">
                    <?php foreach ($workflowOptions as $v => $label): ?>
                    <option value="<?= htmlspecialchars($v, ENT_QUOTES, 'UTF-8') ?>" <?= $filterWorkflow === $v ? 'selected' : '' ?>>
                        <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-link">Seçimleri Uygula</button>
                <a href="entries" class="btn btn-link">Filtreleri Temizle</a>
            </div>
        </form>
    </div>
</div>

<?php if (empty($entries)): ?>
<div class="card">
    <div class="bos-durum">
        <i class="bi bi-inbox"></i>Filtrelere uygun kayıt bulunamadı.
    </div>
</div>
<?php else: ?>

<form method="POST">
    <?= Csrf::field() ?>

    <div class="d-flex justify-content-end align-items-center gap-2 mb-2 flex-wrap">
        <input type="text" name="bulk_review_comment" class="form-control form-control-sm"
               style="max-width:360px;" placeholder="Toplu onay değerlendirme notu">
        <button type="submit" name="bulk_verify" value="1" class="btn btn-sm btn-success"
                onclick="return confirm('Seçili kayıtları onaylamak istediğinize emin misiniz?')">
            <i class="bi bi-check2-all me-1"></i>Seçilenleri Onayla
        </button>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width:30px;"><input type="checkbox" id="selectAll" class="form-check-input"></th>
                            <th>Eylem</th>
                            <th>KPI</th>
                            <th>Müdürlük</th>
                            <th class="text-center">Yıl</th>
                            <th class="text-end">Değer</th>
                            <th>Giren</th>
                            <th>Açıklama</th>
                            <th class="text-center">Kanıt</th>
                            <th class="text-center">Durum</th>
                            <th class="text-end">İşlem</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($entries as $e): ?>
                        <tr>
                            <td>
                                <?php if (in_array($e['workflow_status'], ['submitted','needs_revision'], true) && (int)$e['attachment_count'] > 0): ?>
                                <input type="checkbox" name="entry_ids[]" value="<?= $e['id'] ?>" class="form-check-input entry-cb">
                                <?php endif; ?>
                            </td>
                            <td><code class="eylem-kodu"><?= htmlspecialchars($e['action_code'], ENT_QUOTES, 'UTF-8') ?></code></td>
                            <td>
                                <div class="fw-medium yazi-kucuk-arti"><?= htmlspecialchars($e['kpi_name'], ENT_QUOTES, 'UTF-8') ?></div>
                            </td>
                            <td class="yazi-kucuk">
                                <?= htmlspecialchars($e['dept_name'], ENT_QUOTES, 'UTF-8') ?>
                            </td>
                            <td class="text-center fw-bold"><?= $e['year'] ?></td>
                            <td class="text-end fw-bold text-success">
                                <?= number_format((float)$e['value'], 2) ?>
                                <small class="text-muted"><?= htmlspecialchars($e['unit'], ENT_QUOTES, 'UTF-8') ?></small>
                            </td>
                            <td class="yazi-kucuk" style="white-space:nowrap;">
                                <?= htmlspecialchars($e['entered_by_name'], ENT_QUOTES, 'UTF-8') ?>
                                <div class="text-muted yazi-2xs"><?= date('d.m.Y', strtotime($e['created_at'])) ?></div>
                            </td>
                            <td style="max-width:200px; font-size:.78rem;">
                                <?php if ($e['notes']): ?>
                                <span title="<?= htmlspecialchars($e['notes'], ENT_QUOTES, 'UTF-8') ?>">
                                    <?= htmlspecialchars(mb_strimwidth($e['notes'], 0, 50, '…'), ENT_QUOTES, 'UTF-8') ?>
                                </span>
                                <?php else: ?>
                                <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php $rowAttachments = $attachmentsByEntry[(int)$e['id']] ?? []; ?>
                                <?php if (!empty($rowAttachments)): ?>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary py-0 px-2 dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                            <?= count($rowAttachments) ?> dosya
                                        </button>
                                        <div class="dropdown-menu dropdown-menu-end">
                                            <?php foreach ($rowAttachments as $att): ?>
                                            <a class="dropdown-item small" href="<?= BASE_PATH ?>/kanit-indir?id=<?= (int)$att['id'] ?>">
                                                <i class="bi bi-paperclip me-1"></i><?= htmlspecialchars($att['original_name'], ENT_QUOTES, 'UTF-8') ?>
                                            </a>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <span class="badge bg-danger-subtle text-danger">Yok</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?= V2::entryStatusBadge($e['workflow_status'] ?? ($e['is_verified'] ? 'approved' : 'submitted')) ?>
                                <?php if (!empty($e['review_comment'])): ?>
                                <div class="text-muted yazi-2xs mt-1" title="<?= htmlspecialchars($e['review_comment'], ENT_QUOTES, 'UTF-8') ?>">
                                    <?= htmlspecialchars(mb_strimwidth($e['review_comment'], 0, 36, '…'), ENT_QUOTES, 'UTF-8') ?>
                                </div>
                                <?php endif; ?>
                            </td>
                            <td class="text-end" style="white-space:nowrap;">
                                <button type="button" class="btn btn-sm btn-outline-success py-0 px-2"
                                        data-bs-toggle="modal" data-bs-target="#reviewModal"
                                        data-review-id="<?= (int)$e['id'] ?>" data-review-action="approve"
                                        data-review-label="<?= htmlspecialchars($e['action_code'] . ' / ' . $e['kpi_name'], ENT_QUOTES, 'UTF-8') ?>"
                                        title="Onayla"><i class="bi bi-check-lg"></i></button>
                                <button type="button" class="btn btn-sm btn-outline-info py-0 px-2"
                                        data-bs-toggle="modal" data-bs-target="#reviewModal"
                                        data-review-id="<?= (int)$e['id'] ?>" data-review-action="revision"
                                        data-review-label="<?= htmlspecialchars($e['action_code'] . ' / ' . $e['kpi_name'], ENT_QUOTES, 'UTF-8') ?>"
                                        title="Düzeltme İste"><i class="bi bi-arrow-repeat"></i></button>
                                <button type="button" class="btn btn-sm btn-outline-danger py-0 px-2"
                                        data-bs-toggle="modal" data-bs-target="#reviewModal"
                                        data-review-id="<?= (int)$e['id'] ?>" data-review-action="reject"
                                        data-review-label="<?= htmlspecialchars($e['action_code'] . ' / ' . $e['kpi_name'], ENT_QUOTES, 'UTF-8') ?>"
                                        title="Reddet"><i class="bi bi-x-lg"></i></button>
                                <?php if ($e['workflow_status'] === 'approved'): ?>
                                <button type="button" class="btn btn-sm btn-outline-warning py-0 px-2"
                                        data-bs-toggle="modal" data-bs-target="#reviewModal"
                                        data-review-id="<?= (int)$e['id'] ?>" data-review-action="unapprove"
                                        data-review-label="<?= htmlspecialchars($e['action_code'] . ' / ' . $e['kpi_name'], ENT_QUOTES, 'UTF-8') ?>"
                                        title="Onayı Kaldır"><i class="bi bi-slash-circle"></i></button>
                                <?php endif; ?>
                                <?php if (Auth::isSuperAdmin()): ?>
                                <button type="button" class="btn btn-sm btn-outline-danger py-0 px-2"
                                        data-bs-toggle="modal" data-bs-target="#softDeleteModal"
                                        data-sd-id="<?= (int) $e['id'] ?>"
                                        data-sd-label="<?= htmlspecialchars($e['action_code'] . ' / ' . $e['kpi_name'] . ' · ' . $e['year'], ENT_QUOTES, 'UTF-8') ?>"
                                        title="Çöp Kutusuna Taşı">
                                    <i class="bi bi-trash"></i>
                                </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</form>
<?php endif; ?>

<div class="modal fade" id="reviewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <?= Csrf::field() ?>
            <input type="hidden" name="review_entry_id" id="reviewEntryId" value="">
            <input type="hidden" name="review_action" id="reviewAction" value="">
            <div class="modal-header">
                <h5 class="modal-title">Veri Değerlendirme</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <div class="modal-body">
                <div class="small text-muted mb-2" id="reviewLabel"></div>
                <label class="form-label fw-semibold">Değerlendirme Yorumu <span class="text-danger">*</span></label>
                <textarea name="review_comment" class="form-control" rows="4" required
                          placeholder="Onay, düzeltme veya red gerekçesini yazın."></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Vazgeç</button>
                <button type="submit" class="btn btn-success" id="reviewSubmitBtn">Kaydet</button>
            </div>
        </form>
    </div>
</div>

<?php
$extraJs = '<script>
document.getElementById("selectAll")?.addEventListener("change", function() {
    document.querySelectorAll(".entry-cb").forEach(cb => cb.checked = this.checked);
});
document.getElementById("reviewModal")?.addEventListener("show.bs.modal", function(event) {
    const btn = event.relatedTarget;
    const action = btn?.getAttribute("data-review-action") || "revision";
    const labels = {
        approve: ["Onayla", "btn-success"],
        revision: ["Düzeltme İste", "btn-info"],
        reject: ["Reddet", "btn-danger"],
        unapprove: ["Onayı Kaldır", "btn-warning"]
    };
    const cfg = labels[action] || labels.revision;
    this.querySelector("#reviewEntryId").value = btn?.getAttribute("data-review-id") || "";
    this.querySelector("#reviewAction").value = action;
    this.querySelector("#reviewLabel").textContent = btn?.getAttribute("data-review-label") || "";
    const submit = this.querySelector("#reviewSubmitBtn");
    submit.textContent = cfg[0];
    submit.className = "btn " + cfg[1];
});
</script>';
?>

<?php if (Auth::isSuperAdmin()):
    $sdFormAction = BASE_PATH . '/yonetim/veri-onay';
    $sdTitle      = 'Veri Girişini Çöp Kutusuna Taşı';
    $sdLabel      = 'Veri Girişi';
    require APP_ROOT . '/uygulama/parcalar/cop-kutusu-modal.php';
endif; ?>

<?php require_once APP_ROOT . '/uygulama/yerlesim/alt.php'; ?>
