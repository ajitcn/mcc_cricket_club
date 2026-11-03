<?php
$editing = is_array($match);
$fieldErrors = session()->getFlashdata('field_errors') ?? [];
$options = $options ?? ['branches' => [], 'grounds' => [], 'coaches' => [], 'players' => [], 'opponents' => []];
$selectedBranch = (string) old('branch_id', $match['branch_id'] ?? '');
$selectedGround = (string) old('ground_id', $match['ground_id'] ?? '');
$selectedCoach = (string) old('coach_user_id', $match['coach_user_id'] ?? '');
$selectedOpponent = (string) old('opponent_id', $match['opponent_id'] ?? '');
$selectedYourTeam = (string) old('your_team_opponent_id', $match['your_team_opponent_id'] ?? '');
$selectedType = (string) old('match_type', $match['match_type'] ?? 'friendly');
$selectedCategory = (string) old('match_category', $match['match_category'] ?? 'regular');
$selectedStatus = (string) old('status', $match['status'] ?? 'scheduled');
$startLocal = (string) ($match['starts_at_local'] ?? '');
$endLocal = (string) ($match['ends_at_local'] ?? '');
$selectedStartDate = (string) old('starts_date', $startLocal === '' ? '' : substr($startLocal, 0, 10));
$selectedStartTime = (string) old('starts_time', $startLocal === '' ? '' : substr($startLocal, 11, 5));
$selectedEndDate = (string) old('ends_date', $endLocal === '' ? '' : substr($endLocal, 0, 10));
$selectedEndTime = (string) old('ends_time', $endLocal === '' ? '' : substr($endLocal, 11, 5));
$selectedScore = (string) old('score_summary', $match['score_summary'] ?? '');
$selectedRemarks = (string) old('coach_remarks', $match['coach_remarks'] ?? '');
$selectedCorrectionReason = (string) old('correction_reason', '');
$selectedSquad = array_map('intval', (array) old('player_ids', array_column($match['players'] ?? [], 'player_id')));
$selectedXi = array_map('intval', array_column(array_filter($match['players'] ?? [], static fn (array $player): bool => ($player['selection_type'] ?? '') === 'playing_xi'), 'player_id'));

if (old('playing_xi_ids') !== null) {
    $selectedXi = array_map('intval', (array) old('playing_xi_ids'));
}

$displayDate = static function (string $value): string {
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $parts)) {
        return $parts[3] . '-' . $parts[2] . '-' . $parts[1];
    }

    return $value;
};

$selectedStartDate = $displayDate($selectedStartDate);
$selectedEndDate = $displayDate($selectedEndDate);
$formAction = $editing ? 'matches/' . (int) $match['id'] : 'matches';
$isCompletedEdit = $editing && ($match['status'] ?? '') === 'completed';
$pageActions = '<div class="mcc-page-actions">'
    . '<a class="mcc-button mcc-button-secondary" href="' . site_url('matches') . '">'
    . '<i class="fa-solid fa-arrow-left" aria-hidden="true"></i>'
    . '<span class="mcc-button-label">Back to matches</span></a></div>';
?>

<?= view('staff_header', [
    'title' => $editing ? 'Edit match' : 'Create match',
    'pageSubtitle' => 'Set the fixture details, schedule, squad, and match notes.',
    'pageActions' => $pageActions,
]) ?>

<div class="mcc-match-form-page">

    <?php if ($message = session()->getFlashdata('message')): ?>
        <div class="mcc-alert mcc-alert-success" role="status"><?= esc($message) ?></div>
    <?php endif; ?>
    <?php if ($error = session()->getFlashdata('error')): ?>
        <div class="mcc-alert mcc-alert-danger" role="alert"><?= esc($error) ?></div>
    <?php endif; ?>

    <form method="post" action="<?= site_url($formAction) ?>" class="mcc-form-shell mcc-match-form" data-match-submit-form>
        <?= csrf_field() ?>
        <?php if ($isCompletedEdit): ?>
            <input type="hidden" name="expected_version" value="<?= (int) ($match['version'] ?? 1) ?>">
        <?php endif; ?>

        <section class="mcc-card mcc-form-section">
            <div class="mcc-section-heading">
                <div>
                    <span class="mcc-eyebrow">Fixture setup</span>
                    <h2>Match details</h2>
                </div>
            </div>
            <div class="mcc-form-grid">
                <div class="mcc-form-field">
                    <label for="match-branch">Branch <span class="mcc-required" aria-hidden="true">*</span></label>
                    <select id="match-branch" class="form-control" name="branch_id" required data-match-branch>
                        <option value="">Choose branch</option>
                        <?php foreach ($options['branches'] as $branch): ?>
                            <option value="<?= (int) $branch['id'] ?>" <?= $selectedBranch === (string) $branch['id'] ? 'selected' : '' ?>><?= esc($branch['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (! empty($fieldErrors['branch_id'])): ?><p class="mcc-field-error" role="alert"><?= esc($fieldErrors['branch_id']) ?></p><?php endif; ?>
                </div>

                <div class="mcc-form-field">
                    <label for="match-your-team">Your team <span class="mcc-required" aria-hidden="true">*</span></label>
                    <select id="match-your-team" class="form-control btSelect" name="your_team_opponent_id" required data-bt-select data-bt-select-placeholder="Choose your team" data-bt-select-search-placeholder="Search teams" data-match-branch-option>
                        <option value="">Choose your team</option>
                        <?php foreach ($options['opponents'] as $opponent): ?>
                            <option value="<?= (int) $opponent['id'] ?>" data-branch-id="<?= (int) $opponent['branch_id'] ?>" <?= $selectedYourTeam === (string) $opponent['id'] ? 'selected' : '' ?>><?= esc($opponent['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (! empty($fieldErrors['your_team'])): ?><p class="mcc-field-error" role="alert"><?= esc($fieldErrors['your_team']) ?></p><?php endif; ?>
                </div>

                <div class="mcc-form-field">
                    <label for="match-opponent">Opponent <span class="mcc-required" aria-hidden="true">*</span></label>
                    <select id="match-opponent" class="form-control btSelect" name="opponent_id" required data-bt-select data-bt-select-placeholder="Choose opponent" data-bt-select-search-placeholder="Search opponents" data-match-branch-option>
                        <option value="">Select opponent</option>
                        <?php foreach ($options['opponents'] as $opponent): ?>
                            <option value="<?= (int) $opponent['id'] ?>" data-branch-id="<?= (int) $opponent['branch_id'] ?>" <?= $selectedOpponent === (string) $opponent['id'] ? 'selected' : '' ?>><?= esc($opponent['name']) ?></option>
                        <?php endforeach; ?>
                        <?php if ($options['opponents'] === []): ?><option value="" disabled>No active opponents available for this branch.</option><?php endif; ?>
                    </select>
                    <?php if (! empty($fieldErrors['opponent_id'])): ?><p class="mcc-field-error" role="alert"><?= esc($fieldErrors['opponent_id']) ?></p><?php endif; ?>
                </div>

                <div class="mcc-form-field">
                    <label for="match-type">Match type</label>
                    <select id="match-type" class="form-control" name="match_type">
                        <?php foreach (['friendly', 'practice', 'league', 'knockout'] as $type): ?>
                            <option value="<?= esc($type) ?>" <?= $selectedType === $type ? 'selected' : '' ?>><?= esc(ucfirst($type)) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (! empty($fieldErrors['match_type'])): ?><p class="mcc-field-error" role="alert"><?= esc($fieldErrors['match_type']) ?></p><?php endif; ?>
                </div>

                <div class="mcc-form-field">
                    <label for="match-category">Match category</label>
                    <select id="match-category" class="form-control" name="match_category" required>
                        <option value="regular" <?= $selectedCategory === 'regular' ? 'selected' : '' ?>>Regular</option>
                        <option value="tournament" <?= $selectedCategory === 'tournament' ? 'selected' : '' ?>>Tournament</option>
                    </select>
                    <?php if (! empty($fieldErrors['match_category'])): ?><p class="mcc-field-error" role="alert"><?= esc($fieldErrors['match_category']) ?></p><?php endif; ?>
                </div>

                <div class="mcc-form-field">
                    <label for="match-status">Status</label>
                    <select id="match-status" class="form-control" name="status">
                        <?php foreach (['scheduled', 'completed', 'cancelled'] as $status): ?>
                            <option value="<?= esc($status) ?>" <?= $selectedStatus === $status ? 'selected' : '' ?>><?= esc(ucfirst($status)) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (! empty($fieldErrors['status'])): ?><p class="mcc-field-error" role="alert"><?= esc($fieldErrors['status']) ?></p><?php endif; ?>
                </div>
            </div>
        </section>

        <section class="mcc-card mcc-form-section">
            <div class="mcc-section-heading">
                <div>
                    <span class="mcc-eyebrow">Schedule and ownership</span>
                    <h2>Venue, Coach, and timing</h2>
                </div>
            </div>
            <div class="mcc-form-grid">
                <div class="mcc-form-field">
                    <label for="match-ground">Ground <span class="mcc-muted">(optional)</span></label>
                    <select id="match-ground" class="form-control" name="ground_id" data-match-branch-option>
                        <option value="">Choose ground</option>
                        <?php foreach ($options['grounds'] as $ground): ?>
                            <option value="<?= (int) $ground['id'] ?>" data-branch-id="<?= (int) $ground['branch_id'] ?>" <?= $selectedGround === (string) $ground['id'] ? 'selected' : '' ?>><?= esc($ground['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (! empty($fieldErrors['ground_id'])): ?><p class="mcc-field-error" role="alert"><?= esc($fieldErrors['ground_id']) ?></p><?php endif; ?>
                </div>

                <div class="mcc-form-field">
                    <label for="match-coach">Coach <span class="mcc-muted">(optional)</span></label>
                    <select id="match-coach" class="form-control" name="coach_user_id" data-match-branch-option>
                        <option value="">Choose Coach</option>
                        <?php foreach ($options['coaches'] as $coach): ?>
                            <option value="<?= (int) $coach['id'] ?>" data-branch-id="<?= (int) $coach['branch_id'] ?>" <?= $selectedCoach === (string) $coach['id'] ? 'selected' : '' ?>><?= esc($coach['display_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (! empty($fieldErrors['coach_user_id'])): ?><p class="mcc-field-error" role="alert"><?= esc($fieldErrors['coach_user_id']) ?></p><?php endif; ?>
                </div>

                <div class="mcc-form-field">
                    <label for="match-start-date">Start date <span class="mcc-required" aria-hidden="true">*</span></label>
                    <input id="match-start-date" class="form-control" name="starts_date" type="text" inputmode="numeric" autocomplete="off" value="<?= esc($selectedStartDate) ?>" data-mcc-date-picker data-mcc-date-format="d-m-Y" data-mcc-date-submit-format="Y-m-d" required placeholder="DD-MM-YYYY">
                    <?php if (! empty($fieldErrors['starts_at'])): ?><p class="mcc-field-error" role="alert"><?= esc($fieldErrors['starts_at']) ?></p><?php endif; ?>
                </div>

                <div class="mcc-form-field">
                    <label for="match-start-time">Start time <span class="mcc-required" aria-hidden="true">*</span></label>
                    <input id="match-start-time" class="form-control" name="starts_time" type="time" value="<?= esc($selectedStartTime) ?>" required>
                    <?php if (! empty($fieldErrors['starts_at'])): ?><p class="mcc-field-error" role="alert"><?= esc($fieldErrors['starts_at']) ?></p><?php endif; ?>
                </div>

                <div class="mcc-form-field">
                    <label for="match-end-date">End date <span class="mcc-required" aria-hidden="true">*</span></label>
                    <input id="match-end-date" class="form-control" name="ends_date" type="text" inputmode="numeric" autocomplete="off" value="<?= esc($selectedEndDate) ?>" data-mcc-date-picker data-mcc-date-format="d-m-Y" data-mcc-date-submit-format="Y-m-d" required placeholder="DD-MM-YYYY">
                    <?php if (! empty($fieldErrors['ends_at'])): ?><p class="mcc-field-error" role="alert"><?= esc($fieldErrors['ends_at']) ?></p><?php endif; ?>
                </div>

                <div class="mcc-form-field">
                    <label for="match-end-time">End time <span class="mcc-required" aria-hidden="true">*</span></label>
                    <input id="match-end-time" class="form-control" name="ends_time" type="time" value="<?= esc($selectedEndTime) ?>" required>
                    <?php if (! empty($fieldErrors['ends_at'])): ?><p class="mcc-field-error" role="alert"><?= esc($fieldErrors['ends_at']) ?></p><?php endif; ?>
                </div>
            </div>
        </section>

        <section class="mcc-card mcc-form-section">
            <div class="mcc-section-heading">
                <div>
                    <span class="mcc-eyebrow">Players</span>
                    <h2>Squad selection</h2>
                    <p class="mcc-muted">Choose the squad first, then select up to eleven Players for the Playing XI.</p>
                </div>
            </div>
            <div class="mcc-form-grid mcc-form-grid-two">
                <div class="mcc-form-field">
                    <label for="match-squad">Squad <span class="mcc-required" aria-hidden="true">*</span></label>
                    <select id="match-squad" class="form-control selectpicker btSelect" name="player_ids[]" multiple data-bt-select data-live-search="true" data-actions-box="true" title="Choose squad Players" required data-match-branch-option>
                        <?php if ($options['players'] === []): ?><option value="" disabled>No eligible Players are available.</option><?php endif; ?>
                        <?php foreach ($options['players'] as $player): ?>
                            <option value="<?= (int) $player['id'] ?>" data-player-id="<?= (int) $player['id'] ?>" data-branch-id="<?= (int) $player['branch_id'] ?>" <?= in_array((int) $player['id'], $selectedSquad, true) ? 'selected' : '' ?>><?= esc($player['full_name']) ?> (Player #<?= (int) $player['id'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (! empty($fieldErrors['player_ids'])): ?><p class="mcc-field-error" role="alert"><?= esc($fieldErrors['player_ids']) ?></p><?php endif; ?>
                </div>

                <div class="mcc-form-field">
                    <label for="match-playing-xi">Playing XI <span class="mcc-muted">(optional)</span></label>
                    <select id="match-playing-xi" class="form-control selectpicker btSelect" name="playing_xi_ids[]" multiple data-bt-select data-live-search="true" data-actions-box="true" title="Choose Playing XI" data-match-branch-option data-match-xi>
                        <?php if ($options['players'] === []): ?><option value="" disabled>No squad Players are available.</option><?php endif; ?>
                        <?php foreach ($options['players'] as $player): ?>
                            <option value="<?= (int) $player['id'] ?>" data-player-id="<?= (int) $player['id'] ?>" data-branch-id="<?= (int) $player['branch_id'] ?>" <?= in_array((int) $player['id'], $selectedXi, true) ? 'selected' : '' ?>><?= esc($player['full_name']) ?> (Player #<?= (int) $player['id'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (! empty($fieldErrors['playing_xi_ids'])): ?><p class="mcc-field-error" role="alert"><?= esc($fieldErrors['playing_xi_ids']) ?></p><?php endif; ?>
                </div>
            </div>
        </section>

        <section class="mcc-card mcc-form-section">
            <div class="mcc-section-heading">
                <div>
                    <span class="mcc-eyebrow">Notes</span>
                    <h2>Result and remarks</h2>
                </div>
            </div>
            <div class="mcc-form-grid mcc-form-grid-two">
                <div class="mcc-form-field">
                    <label for="match-score">Score or result summary <span class="mcc-muted">(optional)</span></label>
                    <input id="match-score" class="form-control" name="score_summary" maxlength="255" value="<?= esc($selectedScore) ?>">
                </div>
                <div class="mcc-form-field">
                    <label for="match-remarks">Coach remarks <span class="mcc-muted">(optional)</span></label>
                    <textarea id="match-remarks" class="form-control" name="coach_remarks" rows="4"><?= esc($selectedRemarks) ?></textarea>
                </div>
            </div>
            <?php if ($isCompletedEdit): ?>
                <div class="mcc-form-field mcc-form-field-full">
                    <label for="match-correction-reason">Correction reason <span class="mcc-required" aria-hidden="true">*</span></label>
                    <textarea id="match-correction-reason" class="form-control" name="correction_reason" rows="3" required><?= esc($selectedCorrectionReason) ?></textarea>
                    <?php if (! empty($fieldErrors['correction_reason'])): ?><p class="mcc-field-error" role="alert"><?= esc($fieldErrors['correction_reason']) ?></p><?php endif; ?>
                </div>
            <?php endif; ?>
        </section>

        <div class="mcc-alert mcc-match-form-feedback" data-match-response hidden role="status"></div>
        <div class="mcc-form-actions">
            <a class="mcc-button mcc-button-secondary" href="<?= site_url('matches') ?>">Cancel</a>
            <button class="mcc-button mcc-button-primary" type="submit"><?= $editing ? 'Update match' : 'Save match' ?></button>
        </div>
    </form>
</div>

<?= view('staff_footer') ?>
