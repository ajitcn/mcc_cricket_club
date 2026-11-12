<?php

$record = is_array($record ?? null) ? $record : [];
$recordType = strtolower((string) ($record['record_type'] ?? 'performance'));
$recordLabel = ucfirst($recordType);
$approved = (int) ($record['approved'] ?? 0) === 1;
$statusTone = $approved ? 'success' : 'warning';
$statusLabel = $approved ? 'Approved' : 'Pending approval';
$metrics = [
    'runs' => 'Runs',
    'balls_faced' => 'Balls faced',
    'dismissals' => 'Dismissals',
    'fours' => 'Fours',
    'sixes' => 'Sixes',
    'wickets' => 'Wickets',
    'runs_conceded' => 'Runs conceded',
    'legal_balls' => 'Legal balls',
    'catches' => 'Catches',
    'stumpings' => 'Stumpings',
    'run_outs' => 'Run outs',
];
$pageActions = '<div class="mcc-page-actions">'
    . '<a class="mcc-button mcc-button-secondary" href="' . site_url('performance') . '">'
    . '<i class="fa-solid fa-arrow-left" aria-hidden="true"></i><span class="mcc-button-label">Back to performance</span></a>'
    . '<a class="mcc-button mcc-button-primary" href="' . site_url('performance/' . (int) $record['id'] . '/edit') . '">'
    . '<i class="fa-solid fa-pen-to-square" aria-hidden="true"></i><span class="mcc-button-label">Edit record</span></a>'
    . '</div>';
?>
<?= view('staff_header', [
    'title' => $recordLabel . ' performance record',
    'nav' => $nav ?? null,
    'message' => $message ?? null,
    'error' => $error ?? null,
    'pageActions' => $pageActions,
]) ?>

<main class="mcc-performance-detail-page">
    <section class="mcc-card mcc-detail-hero" aria-labelledby="performance-heading">
        <div>
            <span class="mcc-eyebrow">Performance record</span>
            <h2 id="performance-heading"><?= esc($recordLabel) ?> performance</h2>
            <p class="mcc-muted">
                <?= esc((string) ($record['player_name'] ?? 'Player unavailable')) ?>
                · <?= esc(mcc_user_date($record['recorded_on'] ?? null)) ?>
            </p>
            <div class="mcc-detail-badges" aria-label="Performance status">
                <span class="mcc-badge mcc-badge-<?= esc($statusTone) ?>"><?= esc($statusLabel) ?></span>
                <span class="mcc-badge mcc-badge-info"><?= esc($recordLabel) ?></span>
            </div>
        </div>
        <div class="mcc-detail-hero-meta">
            <span class="mcc-eyebrow">Record ID</span>
            <strong>#<?= (int) $record['id'] ?></strong>
        </div>
    </section>

    <div class="mcc-detail-grid">
        <section class="mcc-card mcc-detail-card" aria-labelledby="performance-information-heading">
            <div class="mcc-card-heading">
                <div>
                    <span class="mcc-eyebrow">Record context</span>
                    <h2 id="performance-information-heading">Performance information</h2>
                </div>
            </div>
            <dl class="mcc-detail-list">
                <dt>Player</dt>
                <dd><?= esc((string) ($record['player_name'] ?? 'Not available')) ?></dd>
                <dt>Recorded date</dt>
                <dd><?= esc(mcc_user_date($record['recorded_on'] ?? null)) ?></dd>
                <dt>Recorded by</dt>
                <dd><?= esc((string) ($record['recorded_by_name'] ?? 'Not available')) ?></dd>
                <dt>Session</dt>
                <dd><?= ($record['session_id'] ?? null) !== null ? '#' . (int) $record['session_id'] : 'Not linked' ?></dd>
                <dt>Created</dt>
                <dd class="mcc-muted mcc-date-muted"><?= esc(mcc_user_datetime($record['created_at'] ?? null)) ?></dd>
                <dt>Updated</dt>
                <dd class="mcc-muted mcc-date-muted"><?= esc(mcc_user_datetime($record['updated_at'] ?? null)) ?></dd>
            </dl>
        </section>

        <section class="mcc-card mcc-detail-card" aria-labelledby="performance-metrics-heading">
            <div class="mcc-card-heading">
                <div>
                    <span class="mcc-eyebrow">Recorded values</span>
                    <h2 id="performance-metrics-heading">Metrics</h2>
                </div>
            </div>
            <?php $hasMetric = false; ?>
            <dl class="mcc-detail-list">
                <?php foreach ($metrics as $field => $label): ?>
                    <?php if (($record[$field] ?? null) !== null): ?>
                        <?php $hasMetric = true; ?>
                        <dt><?= esc($label) ?></dt>
                        <dd><?= esc((string) $record[$field]) ?></dd>
                    <?php endif; ?>
                <?php endforeach; ?>
            </dl>
            <?php if (! $hasMetric): ?>
                <p class="mcc-empty-state">No metrics have been recorded.</p>
            <?php endif; ?>
        </section>
    </div>

    <section class="mcc-card mcc-detail-card" aria-labelledby="performance-notes-heading">
        <div class="mcc-card-heading">
            <div>
                <span class="mcc-eyebrow">Additional context</span>
                <h2 id="performance-notes-heading">Notes and approval</h2>
            </div>
        </div>
        <div class="mcc-detail-grid">
            <div>
                <span class="mcc-eyebrow">Notes</span>
                <p><?= nl2br(esc((string) ($record['notes'] ?? 'Not provided'))) ?></p>
            </div>
            <?php if (! $approved): ?>
                <form method="post" action="<?= site_url('performance/' . (int) $record['id'] . '/approve') ?>" class="mcc-form-actions">
                    <?= csrf_field() ?>
                    <div class="mcc-form-field">
                        <label for="performance-approval-reason">Approval note (optional)</label>
                        <input class="form-control" id="performance-approval-reason" name="reason" maxlength="255">
                    </div>
                    <button class="mcc-button mcc-button-primary" type="submit">
                        <i class="fa-solid fa-check" aria-hidden="true"></i> Approve record
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </section>
</main>
<?= view('staff_footer') ?>
