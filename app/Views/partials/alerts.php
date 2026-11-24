<?php
$validationErrors = session()->getFlashdata('field_errors');

if (! is_array($validationErrors) || $validationErrors === []) {
    $validationErrors = session()->getFlashdata('errors');
}

$hasValidationErrors = is_array($validationErrors) && $validationErrors !== [];
$showValidationSummary = (bool) ($showValidationSummary ?? false);
?>
<?php if (! empty($message)): ?>
    <div class="mcc-alert mcc-alert-success" role="status">
        <?= esc($message) ?>
    </div>
<?php endif; ?>
<?php if ($showValidationSummary && $hasValidationErrors): ?>
    <div class="mcc-alert mcc-alert-danger" role="alert">
        Please correct the highlighted fields and try again.
    </div>
<?php elseif (! empty($error)): ?>
    <div class="mcc-alert mcc-alert-danger" role="alert">
        <?= esc($error) ?>
    </div>
<?php endif; ?>
