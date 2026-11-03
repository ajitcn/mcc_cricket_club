<?php

$feedback = is_array($feedback ?? null) ? $feedback : [];
$player = is_array($player ?? null) ? $player : $feedback;
$editing = $feedback !== [];
$year = (int) old('feedback_year', $feedback['feedback_year'] ?? date('Y'));
$quarter = (int) old('feedback_quarter', $feedback['feedback_quarter'] ?? 1);
$periods = [1 => 'Sep', 2 => 'Dec', 3 => 'Mar', 4 => 'Jun'];
$value = static fn (string $field): string => (string) old($field, $feedback[$field] ?? '');
?>
<?= view('staff_header', [
    'title' => $editing ? 'Edit Coach Feedback' : 'Add 3-Month Feedback',
    'nav' => $nav,
    'message' => $message,
    'error' => $error,
    'breadcrumbTrail' => [
        ['label' => 'Players', 'url' => site_url('players')],
        ['label' => 'Coach Feedback', 'url' => site_url('player-feedback')],
        ['label' => $editing ? 'Edit feedback' : 'Add feedback'],
    ],
]) ?>

<div class="mcc-player-feedback-form-page">
    <section class="mcc-card mcc-detail-card" aria-labelledby="feedback-form-heading">
        <div class="mcc-card-heading">
            <div>
                <span class="mcc-eyebrow">Coach workspace</span>
                <h2 id="feedback-form-heading"><?= $editing ? 'Edit Coach Feedback' : 'Add 3-Month Feedback' ?></h2>
            </div>
        </div>
        <form method="post" action="<?= esc($editing ? site_url('player-feedback/' . (int) $feedback['id']) : site_url('player-feedback')) ?>" class="mcc-player-feedback-form">
            <?= csrf_field() ?>
            <input type="hidden" name="player_id" value="<?= (int) ($player['id'] ?? 0) ?>">
            <input type="hidden" name="expected_version" value="<?= (int) ($feedback['version'] ?? 0) ?>">
            <div class="mcc-player-feedback-context">
                <div class="mcc-form-field">
                    <label>Player</label>
                    <div class="form-control-plaintext"><?= esc((string) ($player['player_name'] ?? 'Player')) ?></div>
                </div>
                <div class="mcc-form-field">
                    <label>Branch</label>
                    <div class="form-control-plaintext"><?= esc((string) ($player['branch_name'] ?? 'Authorized branch')) ?></div>
                </div>
                <div class="mcc-form-field">
                    <label for="feedback-year">Feedback year</label>
                    <select class="form-control" id="feedback-year" name="feedback_year" <?= $editing ? 'disabled' : '' ?> required>
                        <?php for ($optionYear = (int) date('Y') - 2; $optionYear <= (int) date('Y') + 1; $optionYear++): ?>
                            <option value="<?= $optionYear ?>" <?= $year === $optionYear ? 'selected' : '' ?>><?= $optionYear ?></option>
                        <?php endfor; ?>
                    </select>
                    <?php if ($editing): ?><input type="hidden" name="feedback_year" value="<?= $year ?>"><?php endif; ?>
                </div>
                <div class="mcc-form-field">
                    <label for="feedback-quarter">Feedback period</label>
                    <select class="form-control" id="feedback-quarter" name="feedback_quarter" <?= $editing ? 'disabled' : '' ?> required>
                        <?php foreach ($periods as $periodQuarter => $periodMonth): ?>
                            <option value="<?= $periodQuarter ?>" <?= $quarter === $periodQuarter ? 'selected' : '' ?>><?= esc($periodMonth . ' ' . $year . ' Feedback') ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($editing): ?><input type="hidden" name="feedback_quarter" value="<?= $quarter ?>"><?php endif; ?>
                </div>
            </div>
            <div class="mcc-player-feedback-form-grid">
                <?php foreach ([
                    'student_parent_feedback' => 'Student/Parent feedback',
                    'coach_feedback' => 'Coach feedback',
                    'next_focus_action' => 'Next focus/action',
                ] as $field => $label): ?>
                    <div class="mcc-form-field mcc-player-feedback-field-wide">
                        <label for="feedback-<?= esc($field) ?>"><?= esc($label) ?></label>
                        <textarea class="form-control" id="feedback-<?= esc($field) ?>" name="<?= esc($field) ?>" rows="6" maxlength="10000"><?= esc($value($field)) ?></textarea>
                    </div>
                <?php endforeach; ?>
                <?php if ($editing): ?>
                    <div class="mcc-form-field mcc-player-feedback-field-wide">
                        <label for="feedback-correction-reason">Correction reason</label>
                        <textarea class="form-control" id="feedback-correction-reason" name="correction_reason" rows="3" maxlength="255" required><?= esc((string) old('correction_reason')) ?></textarea>
                    </div>
                <?php endif; ?>
            </div>
            <div class="mcc-form-actions">
                <button class="mcc-button mcc-button-primary" type="submit"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Save Feedback</button>
                <a class="mcc-button mcc-button-secondary" href="<?= esc($editing ? site_url('player-feedback/' . (int) $feedback['id']) : site_url('players/' . (int) ($player['id'] ?? 0))) ?>">Cancel</a>
            </div>
        </form>
    </section>
</div>

<?= view('staff_footer') ?>
