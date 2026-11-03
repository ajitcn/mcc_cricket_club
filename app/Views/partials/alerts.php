<?php if (! empty($message)): ?>
    <div class="mcc-alert mcc-alert-success" role="status"><?= esc($message) ?></div>
<?php endif; ?>
<?php if (! empty($error)): ?>
    <div class="mcc-alert mcc-alert-danger" role="alert"><?= esc($error) ?></div>
<?php endif; ?>
