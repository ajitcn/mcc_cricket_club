<?php $goal = is_array($goal ?? null) ? $goal : []; ?>
<?= view('staff_header', [
    'title' => 'View Goal',
    'nav' => $nav,
    'message' => $message,
    'error' => $error,
    'pageActions' => '<a class="mcc-button mcc-button-secondary" href="' . esc(site_url('player-goals')) . '"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back to Goals</a>',
]) ?>
<section class="mcc-card mcc-detail-card" aria-labelledby="goal-detail-heading">
    <div class="mcc-card-heading">
        <div>
            <span class="mcc-eyebrow">Player development</span>
            <h2 id="goal-detail-heading">Goal details</h2>
        </div>
    </div>
    <dl class="mcc-detail-list">
        <dt>Player</dt><dd><?= esc((string) ($goal['player_name'] ?? 'Unnamed player')) ?></dd>
        <dt>Player ID</dt><dd><?= (int) ($goal['player_id'] ?? 0) ?></dd>
        <dt>Branch</dt><dd><?= esc((string) ($goal['branch_name'] ?? 'Authorized branch')) ?></dd>
        <dt>Coach</dt><dd><?= esc((string) ($goal['coach_name'] ?? 'Not assigned')) ?></dd>
        <dt>Created</dt><dd><?= esc(mcc_user_datetime($goal['created_at'] ?? null)) ?></dd>
        <dt>Updated</dt><dd><?= esc(mcc_user_datetime($goal['updated_at'] ?? null)) ?></dd>
        <dt>Goal title</dt><dd><?= esc((string) ($goal['title'] ?? '')) ?></dd>
        <dt>Goal description</dt><dd><?= nl2br(esc((string) ($goal['description'] ?? ''))) ?></dd>
    </dl>
    <div class="mcc-form-actions">
        <a class="mcc-button mcc-button-primary" href="<?= esc(site_url('player-goals/' . (int) $goal['id'] . '/edit')) ?>">Edit Goal</a>
        <a class="mcc-button mcc-button-secondary" href="<?= esc(site_url('player-goals')) ?>">Back to Goals</a>
    </div>
</section>
<?= view('staff_footer') ?>
