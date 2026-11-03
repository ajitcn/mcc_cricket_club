<?php
$fieldClass = trim((string) ($field_class ?? ''));
$layoutClass = $fieldClass === 'col-12'
    ? 'col-12 mb-2'
    : 'col-12 col-md-4 mb-2' . ($fieldClass !== '' ? ' ' . $fieldClass : '');
?>
<div class="mcc-form-field <?= esc($layoutClass) ?> <?= ! empty($error) ? 'has-error' : '' ?>"<?= ! empty($hidden) ? ' hidden' : '' ?>>
    <label for="<?= esc($id ?? '') ?>">
        <?= esc($label ?? '') ?>
        <?php if (! empty($required)): ?>
            <span aria-hidden="true">*</span>
            <span class="mcc-sr-only">required</span>
        <?php endif; ?>
    </label>
    <?= $input ?? '' ?>
    <?php if (! empty($field_help)): ?>
        <p class="mcc-help" id="<?= esc($id ?? '') ?>-help"><?= esc($field_help) ?></p>
    <?php endif; ?>
    <?php if (! empty($error)): ?>
        <p class="mcc-field-error" role="alert"><?= esc($error) ?></p>
    <?php endif; ?>
</div>
