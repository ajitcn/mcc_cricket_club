<?php $goal = is_array($goal ?? null) ? $goal : []; ?>
<?= view('staff_header', [
    'title' => 'Edit Goal',
    'nav' => $nav,
    'message' => $message,
    'error' => $error,
]) ?>
<section class="mcc-card mcc-detail-card" aria-labelledby="goal-edit-heading">
    <div class="mcc-card-heading">
        <div>
            <span class="mcc-eyebrow">Player development</span>
            <h2 id="goal-edit-heading">Edit Goal</h2>
            <p class="mcc-card-subtitle">For <?= esc((string) ($goal['player_name'] ?? 'Unnamed player')) ?> · <?= esc((string) ($goal['branch_name'] ?? 'Authorized branch')) ?></p>
        </div>
    </div>
    <form method="post" action="<?= esc(site_url('player-goals/' . (int) ($goal['id'] ?? 0) . '/edit')) ?>" class="mcc-player-goal-form">
        <?= csrf_field() ?>
        <input type="hidden" name="expected_version" value="<?= (int) ($goal['version'] ?? 0) ?>">
        <div class="mcc-form-field">
            <label for="goal-title">Goal Title</label>
            <input class="form-control" id="goal-title" name="title" type="text" maxlength="150" required value="<?= esc((string) ($goal['title'] ?? '')) ?>">
        </div>
        <div class="mcc-form-field">
            <label for="goal-description">Goal Description</label>
            <textarea class="form-control" id="goal-description" name="description" rows="6" maxlength="10000" required><?= esc((string) ($goal['description'] ?? '')) ?></textarea>
        </div>
        <div class="mcc-form-actions">
            <button class="mcc-button mcc-button-primary" type="submit">Save Goal</button>
            <a class="mcc-button mcc-button-secondary" href="<?= esc(site_url('player-goals/' . (int) ($goal['id'] ?? 0))) ?>">Cancel</a>
        </div>
    </form>
</section>
<?= view('staff_footer') ?>
