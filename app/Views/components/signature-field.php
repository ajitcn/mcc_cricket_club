<?php

$field = (string) ($field ?? '');
$id = (string) ($id ?? $field);
$label = (string) ($label ?? 'Signature');
$value = (string) ($value ?? '');
$error = (string) ($error ?? '');
?>
<div class="mcc-form-field col-12 mcc-signature-field <?= $error !== '' ? 'has-error' : '' ?>">
    <label for="<?= esc($id) ?>-canvas"><?= esc($label) ?></label>
    <canvas
        class="mcc-signature-canvas mcc-signature-border"
        id="<?= esc($id) ?>-canvas"
        width="600"
        height="180"
        data-mcc-signature-canvas
        data-mcc-signature-field="<?= esc($field) ?>"
        data-mcc-signature-input="<?= esc($id) ?>-input"
        aria-label="Draw <?= esc(strtolower($label)) ?>"
        role="img"
        tabindex="0"
    ></canvas>
    <input
        type="hidden"
        id="<?= esc($id) ?>-input"
        name="<?= esc($field) ?>"
        value="<?= esc($value) ?>"
        data-mcc-signature-input
    >
    <div class="mcc-signature-actions">
        <button class="mcc-button mcc-button-danger" type="button" data-mcc-signature-clear="<?= esc($id) ?>-canvas">
            Clear Signature
        </button>
    </div>
    <?php if ($value !== '' && ! str_starts_with($value, 'data:image/')): ?>
        <p class="mcc-help">Saved text fallback: <?= esc($value) ?></p>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
        <p class="mcc-field-error" role="alert"><?= esc($error) ?></p>
    <?php endif; ?>
</div>
