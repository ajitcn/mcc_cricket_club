<?php

$formCancelUrl = (string) ($cancelUrl ?? site_url('admissions'));
$formViewUrl = (string) ($viewUrl ?? '');
$formSubmitLabel = (string) ($submitLabel ?? 'Save Admission');
?>

<div class="mcc-form-actions mcc-admission-actions">
    <?php if ($formViewUrl !== ''): ?>
        <a class="mcc-button mcc-button-secondary" href="<?= esc($formViewUrl) ?>">
            View Admission
        </a>
    <?php endif; ?>
    <a class="mcc-button mcc-button-secondary" href="<?= esc($formCancelUrl) ?>">Cancel</a>
    <button class="mcc-button mcc-button-primary" type="submit">
        <?= esc($formSubmitLabel) ?>
    </button>
</div>
