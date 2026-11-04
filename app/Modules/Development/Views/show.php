<?php

$statusLabels = [
    'active' => 'Active',
    'completed' => 'Completed',
    'cancelled' => 'Cancelled',
];
$status = (string) ($plan['status'] ?? '');
$completed = $status === 'completed';

?>
<?= view('staff_header', [
    'title' => $title,
    'pageActions' => '<div class="mcc-page-actions">'
        . ($completed ? '' : '<a class="mcc-button mcc-button-primary" href="' . site_url('development/' . (int) $plan['id'] . '/edit') . '"><i class="fa-solid fa-pen" aria-hidden="true"></i> Edit plan</a>')
        . '<a class="mcc-button mcc-button-secondary" href="' . site_url('development') . '"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back to plans</a>'
        . '</div>',
]) ?>

<div class="mcc-development-page mcc-development-detail-page">
    <section class="mcc-card mcc-development-overview" aria-labelledby="development-detail-heading">
        <div class="mcc-card-heading mcc-development-detail-heading">
            <div>
                <span class="mcc-eyebrow">Player development</span>
                <h1 id="development-detail-heading"><?= esc($plan['title'] ?? 'Development plan') ?></h1>
                <p class="mcc-card-subtitle">
                    <?= esc($plan['player_name'] ?? ('Player #' . (int) ($plan['player_id'] ?? 0))) ?>
                    <span aria-hidden="true"> · </span>
                    <?= esc($plan['branch_name'] ?? ('Branch #' . (int) ($plan['branch_id'] ?? 0))) ?>
                </p>
            </div>
            <span class="mcc-badge mcc-badge-<?= $status === 'active' || $status === 'completed' ? 'success' : 'danger' ?>">
                <?= esc($statusLabels[$status] ?? $status) ?>
            </span>
        </div>

        <?php if ($message): ?>
            <div class="mcc-alert mcc-alert-success" role="status"><?= esc($message) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="mcc-alert mcc-alert-danger" role="alert"><?= esc($error) ?></div>
        <?php endif; ?>
        <?php if ($completed): ?>
            <div class="mcc-alert mcc-alert-info" role="status">Completed development plans cannot be edited.</div>
        <?php endif; ?>

        <dl class="mcc-development-summary">
            <div>
                <dt>Player</dt>
                <dd><?= esc($plan['player_name'] ?? ('Player #' . (int) ($plan['player_id'] ?? 0))) ?></dd>
            </div>
            <div>
                <dt>Branch</dt>
                <dd><?= esc($plan['branch_name'] ?? 'Not available') ?></dd>
            </div>
            <div>
                <dt>Coach</dt>
                <dd><?= esc($plan['coach_name'] ?? 'Not available') ?></dd>
            </div>
            <div>
                <dt>Target date</dt>
                <dd><?= esc(mcc_user_date($plan['target_date'] ?? null, 'Not set')) ?></dd>
            </div>
            <div>
                <dt>Last updated</dt>
                <dd class="mcc-meta-text"><?= esc(mcc_user_date($plan['updated_at'] ?? null, 'Not available')) ?></dd>
            </div>
        </dl>

        <?php if (! empty($plan['notes'])): ?>
            <div class="mcc-development-notes">
                <h2>Plan details</h2>
                <p><?= nl2br(esc($plan['notes'])) ?></p>
            </div>
        <?php endif; ?>
    </section>
</div>

<?= view('staff_footer') ?>
