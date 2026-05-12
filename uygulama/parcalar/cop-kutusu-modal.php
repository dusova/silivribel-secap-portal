<?php

declare(strict_types=1);

$sdModalId    = $sdModalId    ?? 'softDeleteModal';
$sdFormAction = $sdFormAction ?? '';
$sdHiddenName = $sdHiddenName ?? 'soft_delete_id';
$sdTitle      = $sdTitle      ?? 'Kaydı Çöp Kutusuna Taşı';
$sdLabel      = $sdLabel      ?? 'Kayıt';
$sdExtra      = $sdExtra      ?? '';
?>
<div class="modal fade" id="<?= htmlspecialchars($sdModalId, ENT_QUOTES, 'UTF-8') ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="<?= htmlspecialchars($sdFormAction, ENT_QUOTES, 'UTF-8') ?>" novalidate>
                <?= Csrf::field() ?>
                <input type="hidden" name="<?= htmlspecialchars($sdHiddenName, ENT_QUOTES, 'UTF-8') ?>" id="<?= htmlspecialchars($sdModalId, ENT_QUOTES, 'UTF-8') ?>_id" value="">
                <?= $sdExtra ?>
                <div class="modal-header bg-danger-subtle">
                    <h5 class="modal-title text-danger">
                        <i class="bi bi-trash me-2"></i><?= htmlspecialchars($sdTitle, ENT_QUOTES, 'UTF-8') ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2">
                        <strong id="<?= htmlspecialchars($sdModalId, ENT_QUOTES, 'UTF-8') ?>_label"><?= htmlspecialchars($sdLabel, ENT_QUOTES, 'UTF-8') ?></strong>
                        <span class="text-muted">kaydı çöp kutusuna taşınacak.</span>
                    </p>
                    <div class="alert alert-warning d-flex gap-2 py-2 mb-3" style="font-size:.82rem;">
                        <i class="bi bi-info-circle"></i>
                        <div>
                            Kayıt fiziksel olarak silinmez. Süper admin <strong>Çöp Kutusu</strong> üzerinden geri alabilir.
                            Silme işlemi <strong>Denetim Günlüğü</strong>'ne kaydedilir.
                        </div>
                    </div>
                    <label class="form-label fw-semibold">Silme sebebi <span class="text-danger">*</span></label>
                    <textarea name="delete_reason" class="form-control" rows="3" minlength="3" maxlength="500" required
                              placeholder="Örn: Yanlışlıkla oluşturuldu, mükerrer kayıt, vb."></textarea>
                    <div class="form-text">En az 3 karakter.</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Vazgeç</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-trash me-1"></i>Çöp Kutusuna Taşı
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
(function(){
    const modalEl = document.getElementById(<?= json_encode($sdModalId) ?>);
    if (!modalEl) return;
    modalEl.addEventListener('show.bs.modal', function (event) {
        const trigger = event.relatedTarget;
        if (!trigger) return;
        const id = trigger.getAttribute('data-sd-id') || '';
        const label = trigger.getAttribute('data-sd-label') || <?= json_encode($sdLabel) ?>;
        const entity = trigger.getAttribute('data-sd-entity');
        modalEl.querySelector('#' + <?= json_encode($sdModalId) ?> + '_id').value = id;
        const labelEl = modalEl.querySelector('#' + <?= json_encode($sdModalId) ?> + '_label');
        if (labelEl) labelEl.textContent = label;

        const form = modalEl.querySelector('form');
        if (form) {
            let entityInput = form.querySelector('input[name="soft_delete_entity"]');
            if (entity) {
                if (!entityInput) {
                    entityInput = document.createElement('input');
                    entityInput.type = 'hidden';
                    entityInput.name = 'soft_delete_entity';
                    form.appendChild(entityInput);
                }
                entityInput.value = entity;
            } else if (entityInput) {
                entityInput.value = '';
            }
        }
    });
})();
</script>
