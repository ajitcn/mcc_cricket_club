<?php

$feedback = is_array($feedback ?? null) ? $feedback : [];
$feedbackId = (int) ($feedback['id'] ?? 0);
$quarter = (int) ($feedback['feedback_quarter'] ?? 0);
$periodMonth = [1 => 'Sep', 2 => 'Dec', 3 => 'Mar', 4 => 'Jun'][$quarter] ?? 'Unknown';
$period = $periodMonth . ' ' . (int) ($feedback['feedback_year'] ?? 0) . ' Feedback';
$playerName = (string) ($feedback['player_name'] ?? 'Player');
$branchName = (string) ($feedback['branch_name'] ?? 'Authorized branch');
$coachName = (string) ($feedback['coach_name'] ?? 'Not available');
$playerId = (int) ($feedback['player_id'] ?? 0);
$createdAt = mcc_user_datetime($feedback['created_at'] ?? null);
$updatedAt = mcc_user_datetime($feedback['updated_at'] ?? null);
?>
<?= view('staff_header', [
    'title' => 'View Coach Feedback',
    'nav' => $nav,
    'message' => $message,
    'error' => $error,
    'breadcrumbTrail' => [
        ['label' => 'Players', 'url' => site_url('players')],
        ['label' => 'Coach Feedback', 'url' => site_url('player-feedback')],
        ['label' => 'View feedback'],
    ],
    'pageActions' => '<div class="mcc-page-actions">
        ' . (($can_manage ?? false)
            ? '<a class="mcc-button mcc-button-primary" href="'
                . esc(site_url('player-feedback/' . $feedbackId . '/edit'))
                . '"><i class="fa-solid fa-pen-to-square" aria-hidden="true"></i> Edit Feedback</a>'
            : '') . '
        <a class="mcc-button mcc-button-secondary" href="'
            . esc(site_url('player-feedback'))
            . '"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back to Feedback</a>
    </div>',
]) ?>

<div class="mcc-player-feedback-detail-page">
    <section class="mcc-card mcc-detail-card" aria-labelledby="feedback-detail-heading">
        <div class="mcc-card-heading">
            <div>
                <span class="mcc-eyebrow">Coach workspace</span>
                <h2 id="feedback-detail-heading">Feedback details</h2>
                <p class="mcc-card-subtitle">Feedback #<?= $feedbackId ?> · <?= esc($period) ?></p>
            </div>
            <span class="mcc-badge mcc-feedback-period-badge">
                <?= esc($period) ?>
            </span>
        </div>

        <dl class="mcc-player-feedback-summary-grid mcc-detail-list">
            <div>
                <dt>Player</dt>
                <dd><?= esc($playerName) ?></dd>
            </div>
            <div>
                <dt>Player ID</dt>
                <dd><?= $playerId ?></dd>
            </div>
            <div>
                <dt>Branch</dt>
                <dd><?= esc($branchName) ?></dd>
            </div>
            <div>
                <dt>Coach</dt>
                <dd><?= esc($coachName) ?></dd>
            </div>
            <div>
                <dt>Feedback period</dt>
                <dd><span class="mcc-badge mcc-feedback-period-badge"><?= esc($period) ?></span></dd>
            </div>
            <div>
                <dt>Created</dt>
                <dd class="mcc-detail-meta-date"><?= esc($createdAt) ?></dd>
            </div>
            <div>
                <dt>Updated</dt>
                <dd class="mcc-detail-meta-date"><?= esc($updatedAt) ?></dd>
            </div>
        </dl>
    </section>

    <?php foreach ([
        'student_parent_feedback' => 'Student/Parent Feedback',
        'coach_feedback' => 'Coach Feedback',
        'next_focus_action' => 'Next Focus/Action',
    ] as $field => $heading): ?>
        <section class="mcc-card mcc-detail-card mcc-player-feedback-content-card"
            aria-labelledby="feedback-<?= esc($field) ?>-heading">
            <div class="mcc-card-heading">
                <div>
                    <span class="mcc-eyebrow">Feedback record</span>
                    <h2 id="feedback-<?= esc($field) ?>-heading"><?= esc($heading) ?></h2>
                </div>
            </div>
            <div class="mcc-player-feedback-content" tabindex="0">
                <?php $content = trim((string) ($feedback[$field] ?? '')); ?>
                <?php if ($content === ''): ?>
                    <span class="mcc-muted">No feedback recorded.</span>
                <?php else: ?>
                    <?= nl2br(esc($content)) ?>
                <?php endif; ?>
            </div>
        </section>
    <?php endforeach; ?>
</div>

<?= view('staff_footer') ?>
