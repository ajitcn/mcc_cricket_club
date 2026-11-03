<?php
$tournamentId = (int) ($tournament['id'] ?? 0);
$fixtureId = (int) ($fixture['id'] ?? 0);
$isEdit = $fixtureId > 0;
$fieldErrors = session('field_errors') ?? [];
$value = static function (string $key, mixed $default = '') use ($fixture): mixed {
    $old = old($key);

    return $old !== null ? $old : (is_array($fixture) ? ($fixture[$key] ?? $default) : $default);
};
$status = strtolower((string) $value('status', 'scheduled'));
$status = $status === 'planned' ? 'scheduled' : $status;
$matchDate = old('match_date');
$matchDate = $matchDate !== null
    ? (string) $matchDate
    : mcc_user_date_input(is_array($fixture) ? ($fixture['match_date'] ?? null) : null);
$showReason = in_array($status, ['cancelled', 'postponed'], true);
$showRescheduleReason = $isEdit
    && (string) ($fixture['status'] ?? '') === 'postponed'
    && $status === 'scheduled';
$showCorrectionReason = $isEdit && (string) ($fixture['status'] ?? '') === 'completed';
$pageActions = '<div class="mcc-page-actions">'
    . '<a class="mcc-button mcc-button-secondary" href="' . site_url('tournaments/' . $tournamentId) . '">'
    . '<i class="fa-solid fa-arrow-left" aria-hidden="true"></i><span>Back to Tournament</span></a>'
    . '</div>';
?>
<?= view('staff_header', [
    'title' => $isEdit ? 'Edit fixture' : 'Create fixture',
    'pageSubtitle' => ($tournament['name'] ?? 'Tournament') . ' · Keep the fixture schedule and result reference up to date.',
    'pageActions' => $pageActions,
]) ?>

<div class="mcc-tournament-form-page">

    <?php if ($error = session('error')): ?>
        <div class="mcc-alert mcc-alert-danger mcc-tournament-form-feedback" role="alert">
            <?= esc($error) ?>
        </div>
    <?php endif ?>

    <form method="post" action="<?= site_url('tournaments/' . $tournamentId . '/fixtures' . ($isEdit ? '/' . $fixtureId : '')) ?>" class="mcc-form mcc-tournament-form" data-fixture-match-options-url="<?= esc(site_url('tournaments/' . $tournamentId . '/fixtures/match-options')) ?>">
        <?= csrf_field() ?>
        <?php if ($isEdit): ?>
            <input type="hidden" name="expected_version" value="<?= (int) ($fixture['version'] ?? 1) ?>">
        <?php endif ?>

        <section class="mcc-card mcc-tournament-form-section" aria-labelledby="fixture-details-heading">
            <div class="mcc-card-heading">
                <div>
                    <h2 id="fixture-details-heading">Fixture details</h2>
                    <p class="mcc-muted">Set the round and participating teams.</p>
                </div>
            </div>
            <div class="mcc-tournament-form-grid">
                <div class="mcc-form-field">
                    <label for="fixture-number">Fixture number</label>
                    <input id="fixture-number" class="form-control" name="fixture_number" type="number" min="1" value="<?= esc((string) $value('fixture_number')) ?>" required>
                    <?php if (isset($fieldErrors['fixture_number'])): ?><div class="mcc-field-error"><?= esc($fieldErrors['fixture_number']) ?></div><?php endif ?>
                </div>
                <div class="mcc-form-field">
                    <label for="fixture-round">Round</label>
                    <input id="fixture-round" class="form-control" name="round" maxlength="100" value="<?= esc((string) $value('round')) ?>" required>
                    <?php if (isset($fieldErrors['round'])): ?><div class="mcc-field-error"><?= esc($fieldErrors['round']) ?></div><?php endif ?>
                </div>
                <div class="mcc-form-field">
                    <label for="fixture-match-number">Match Number <span class="mcc-form-optional">(optional)</span></label>
                    <input id="fixture-match-number" class="form-control" name="match_number" type="number" min="1" value="<?= esc((string) $value('match_number')) ?>">
                    <?php if (isset($fieldErrors['match_number'])): ?><div class="mcc-field-error"><?= esc($fieldErrors['match_number']) ?></div><?php endif ?>
                </div>
                <div class="mcc-form-field">
                    <label for="fixture-home">Team 1</label>
                    <select id="fixture-home" class="form-control" name="home_team" required>
                        <option value="">Select team</option>
                        <?php foreach (($options['teams'] ?? []) as $team): ?>
                            <option value="<?= esc($team['team_name']) ?>" <?= $value('home_team') === $team['team_name'] ? 'selected' : '' ?>><?= esc($team['team_name']) ?></option>
                        <?php endforeach ?>
                    </select>
                    <?php if (isset($fieldErrors['home_team'])): ?><div class="mcc-field-error"><?= esc($fieldErrors['home_team']) ?></div><?php endif ?>
                </div>
                <div class="mcc-form-field">
                    <label for="fixture-away">Team 2</label>
                    <select id="fixture-away" class="form-control" name="away_team" required>
                        <option value="">Select team</option>
                        <?php foreach (($options['teams'] ?? []) as $team): ?>
                            <option value="<?= esc($team['team_name']) ?>" <?= $value('away_team') === $team['team_name'] ? 'selected' : '' ?>><?= esc($team['team_name']) ?></option>
                        <?php endforeach ?>
                    </select>
                    <?php if (isset($fieldErrors['away_team'])): ?><div class="mcc-field-error"><?= esc($fieldErrors['away_team']) ?></div><?php endif ?>
                </div>
            </div>
        </section>

        <section class="mcc-card mcc-tournament-form-section" aria-labelledby="fixture-schedule-heading">
            <div class="mcc-card-heading">
                <div>
                    <h2 id="fixture-schedule-heading">Schedule and ground</h2>
                    <p class="mcc-muted">Choose a date within the Tournament schedule.</p>
                </div>
            </div>
            <div class="mcc-tournament-form-grid mcc-tournament-form-grid-two">
                <div class="mcc-form-field">
                    <label for="fixture-date">Match date</label>
                    <input
                        id="fixture-date"
                        class="form-control"
                        name="match_date"
                        type="text"
                        inputmode="numeric"
                        autocomplete="off"
                        placeholder="DD-MM-YYYY"
                        data-mcc-date-picker
                        data-mcc-date-format="d-m-Y"
                        value="<?= esc($matchDate) ?>"
                        required
                    >
                    <?php if (isset($fieldErrors['match_date'])): ?><div class="mcc-field-error"><?= esc($fieldErrors['match_date']) ?></div><?php endif ?>
                </div>
                <div class="mcc-form-field">
                    <label for="fixture-time">Start time</label>
                    <input id="fixture-time" class="form-control" name="start_time" type="time" value="<?= esc((string) $value('start_time')) ?>" required>
                    <?php if (isset($fieldErrors['start_time'])): ?><div class="mcc-field-error"><?= esc($fieldErrors['start_time']) ?></div><?php endif ?>
                </div>
                <div class="mcc-form-field">
                    <label for="fixture-ground">Ground <span class="mcc-form-optional">(optional)</span></label>
                    <select id="fixture-ground" class="form-control" name="ground_id">
                        <option value="">Select Ground</option>
                        <?php foreach (($options['grounds'] ?? []) as $ground): ?>
                            <option value="<?= (int) $ground['id'] ?>" <?= (int) $value('ground_id') === (int) $ground['id'] ? 'selected' : '' ?>><?= esc($ground['name']) ?></option>
                        <?php endforeach ?>
                    </select>
                    <?php if (isset($fieldErrors['ground_id'])): ?><div class="mcc-field-error"><?= esc($fieldErrors['ground_id']) ?></div><?php endif ?>
                </div>
                <div class="mcc-form-field">
                    <label for="fixture-status">Status</label>
                    <select id="fixture-status" class="form-control" name="status" required>
                        <?php foreach (['scheduled', 'completed', 'cancelled', 'postponed'] as $option): ?>
                            <option value="<?= $option ?>" <?= $status === $option ? 'selected' : '' ?>><?= ucfirst($option) ?></option>
                        <?php endforeach ?>
                    </select>
                    <?php if (isset($fieldErrors['status'])): ?><div class="mcc-field-error"><?= esc($fieldErrors['status']) ?></div><?php endif ?>
                </div>
            </div>
        </section>

        <section class="mcc-card mcc-tournament-form-section" aria-labelledby="fixture-result-heading">
            <div class="mcc-card-heading">
                <div>
                        <h2 id="fixture-result-heading">Connected Match</h2>
                </div>
            </div>
            <div class="mcc-tournament-form-grid mcc-tournament-form-grid-two">
                <div class="mcc-form-field" data-fixture-match-wrapper>
                    <label for="fixture-match">Match <span class="mcc-form-optional">(optional)</span></label>
                    <select id="fixture-match" class="form-control" name="match_id">
                        <option value="">Select Match</option>
                        <?php if ((string) $value('match_id') !== ''): ?><option value="<?= (int) $value('match_id') ?>" selected>Loading selected Match...</option><?php endif ?>
                    </select>
                    <?php if (isset($fieldErrors['match_id'])): ?><div class="mcc-field-error"><?= esc($fieldErrors['match_id']) ?></div><?php endif ?>
                </div>
                <?php if ($showReason || $showRescheduleReason): ?>
                    <div class="mcc-form-field">
                        <label for="fixture-reason"><?= $status === 'cancelled' ? 'Cancellation reason' : ($showRescheduleReason ? 'Rescheduling reason' : 'Postponement reason') ?></label>
                        <textarea id="fixture-reason" class="form-control" name="reason" rows="3" required><?= esc((string) $value('reason')) ?></textarea>
                        <?php if (isset($fieldErrors['reason'])): ?><div class="mcc-field-error"><?= esc($fieldErrors['reason']) ?></div><?php endif ?>
                    </div>
                <?php endif ?>
                <?php if ($showCorrectionReason): ?>
                    <div class="mcc-form-field">
                        <label for="fixture-correction-reason">Correction reason</label>
                        <textarea id="fixture-correction-reason" class="form-control" name="correction_reason" rows="3" required><?= esc((string) $value('correction_reason')) ?></textarea>
                        <?php if (isset($fieldErrors['correction_reason'])): ?><div class="mcc-field-error"><?= esc($fieldErrors['correction_reason']) ?></div><?php endif ?>
                    </div>
                <?php endif ?>
            </div>
        </section>

        <div class="mcc-form-actions mcc-tournament-form-actions">
            <a class="mcc-button mcc-button-secondary" href="<?= site_url('tournaments/' . $tournamentId) ?>">Cancel</a>
            <button class="mcc-button mcc-button-primary" type="submit"><?= $isEdit ? 'Save fixture' : 'Create fixture' ?></button>
        </div>
    </form>
</div>

<?= view('staff_footer') ?>
