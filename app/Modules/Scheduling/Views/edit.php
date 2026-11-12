<?php
$fieldErrors = session()->getFlashdata('field_errors');
$fieldErrors = is_array($fieldErrors) ? $fieldErrors : [];
$fieldError = static fn (string $field): string => (string) ($fieldErrors[$field] ?? '');
$isCoachScoped = (bool) ($isCoachScoped ?? false);
$selectedBranch = (string) old('branch_id', $session['branch_id'] ?? '');
$selectedCoach = (string) old('coach_user_id', $session['coach_user_id'] ?? '');
$selectedAgeGroup = (string) old('age_group_id', $session['age_group_id'] ?? '');
$selectedPlayers = old('player_ids', array_column($session['players'] ?? [], 'player_id'));
$selectedPlayers = is_array($selectedPlayers) ? array_map('strval', $selectedPlayers) : [];
$selectedSessionType = (string) old('session_type', $session['session_type'] ?? '');
$selectedDescription = (string) old('description', $session['description'] ?? '');
$branchLocked = $isCoachScoped;
$coachLocked = $isCoachScoped;
$datePart = static function (string $value): string {
    if (preg_match('/^(\d{2})-(\d{2})-(\d{4})/', $value)) {
        return substr($value, 0, 10);
    }
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $value, $matches)) {
        return $matches[3] . '-' . $matches[2] . '-' . $matches[1];
    }
    return '';
};
$timePart = static fn (string $value): string => preg_match('/(?:T| )(\d{2}:\d{2})/', $value, $matches) ? $matches[1] : '';
$startsValue = (string) old('starts_at', mcc_user_datetime_input($session['starts_at_local'] ?? null));
$selectedEndTime = (string) old(
    'ends_time',
    $timePart(mcc_user_datetime_input($session['ends_at_local'] ?? null))
);
$skillAreas = ['batting' => 'Batting', 'bowling' => 'Bowling', 'fielding' => 'Fielding', 'wicketkeeping' => 'Wicketkeeping', 'fitness' => 'Fitness', 'game_awareness' => 'Game awareness'];
?>
<?= view('staff_header', ['title' => 'Edit Session', 'nav' => $nav, 'message' => $message, 'error' => $error]) ?>
<div class="mcc-scheduling-card mcc-session-create-card">
    <form id="session-edit-form" method="post" action="<?= site_url('sessions/' . (int) $session['id'] . '/edit') ?>" class="mcc-session-create-form mcc-session-edit-form" data-session-edit-form data-session-submit-form>
        <?= csrf_field() ?>
        <input type="hidden" name="status" value="scheduled">
        <input type="hidden" name="expected_updated_at" value="<?= esc((string) ($session['updated_at'] ?? '')) ?>">
        <input type="hidden" name="player_ids_present" value="1">
        <section class="mcc-card mcc-session-form-section" aria-labelledby="edit-session-details-heading">
            <div class="mcc-session-form-section-heading"><h3 id="edit-session-details-heading">Session Details</h3><p>Choose the program for this coaching session.</p></div>
            <div class="mcc-session-create-grid">
                <div class="mcc-session-form-field">
                    <label for="edit-session-type">Session Type</label>
                    <select class="form-control" id="edit-session-type" name="session_type" aria-describedby="edit-session-type-error" required>
                        <option value="">Choose session type</option>
                        <option value="one_to_one" <?= $selectedSessionType === 'one_to_one' ? 'selected' : '' ?>>Personal</option>
                        <option value="group" <?= $selectedSessionType === 'group' ? 'selected' : '' ?>>Group</option>
                        <option value="elite" <?= $selectedSessionType === 'elite' ? 'selected' : '' ?>>Elite</option>
                    </select>
                    <?php if ($fieldError('session_type') !== ''): ?>
                        <p class="mcc-field-error" id="edit-session-type-error" role="alert">
                            <?= esc($fieldError('session_type')) ?>
                        </p>
                    <?php endif ?>
                </div>
                <div class="mcc-session-form-field"><label for="edit-program-id">Program</label><select class="form-control" id="edit-program-id" name="program_id" data-session-branch-options aria-describedby="edit-program-error" required><option value="">Choose program</option><?php foreach ($formOptions['programs'] as $program): ?><option value="<?= (int) $program['id'] ?>" <?= (string) old('program_id', $session['program_id'] ?? '') === (string) $program['id'] ? 'selected' : '' ?>><?= esc($program['name']) ?></option><?php endforeach ?></select><?php if ($fieldError('program_id') !== ''): ?><p class="mcc-field-error" id="edit-program-error" role="alert"><?= esc($fieldError('program_id')) ?></p><?php endif ?></div>
                <div class="mcc-session-form-field mcc-session-description-field">
                    <label for="edit-session-description">Description / Notes</label>
                    <textarea
                        class="form-control"
                        id="edit-session-description"
                        name="description"
                        rows="4"
                        aria-describedby="edit-session-description-help"
                    ><?= esc($selectedDescription) ?></textarea>
                    <p class="mcc-form-help" id="edit-session-description-help">
                        Add optional context for this Session.
                    </p>
                </div>
            </div>
        </section>
        <section class="mcc-card mcc-session-form-section" aria-labelledby="edit-branch-coach-heading">
            <div class="mcc-session-form-section-heading"><h3 id="edit-branch-coach-heading">Branch / Session Category / Coach</h3><p>Limit the session to an authorized branch, Batch, Age Group, and Coach.</p></div>
            <div class="mcc-session-create-grid">
                <div class="mcc-session-form-field"><label for="edit-branch-id">Branch</label><select class="form-control" id="edit-branch-id" name="branch_id" data-session-branch aria-describedby="edit-branch-error" <?= $branchLocked ? 'disabled' : '' ?> required><option value="">Choose branch</option><?php foreach ($formOptions['branches'] as $branch): ?><option value="<?= (int) $branch['id'] ?>" <?= $selectedBranch === (string) $branch['id'] ? 'selected' : '' ?>><?= esc($branch['name']) ?></option><?php endforeach ?></select><?php if ($branchLocked): ?><input type="hidden" name="branch_id" value="<?= (int) $selectedBranch ?>"><?php endif ?><?php if ($fieldError('branch_id') !== ''): ?><p class="mcc-field-error" id="edit-branch-error" role="alert"><?= esc($fieldError('branch_id')) ?></p><?php endif ?></div>
                <div class="mcc-session-form-field mcc-session-batch-field">
                    <label for="edit-batch-id">
                        Batch <span class="mcc-form-optional">(optional)</span>
                    </label>
                    <select
                        class="form-control btSelect"
                        id="edit-batch-id"
                        name="batch_id"
                        data-bt-select
                        data-bt-select-placeholder="Choose batch"
                        data-bt-select-search-placeholder="Search batches"
                        aria-describedby="edit-batch-error"
                    >
                        <option value="">Choose batch</option>
                        <?php foreach ($formOptions['batches'] as $batch): ?>
                            <option
                                value="<?= (int) $batch['id'] ?>"
                                <?= (string) old('batch_id', $session['batch_id'] ?? '') === (string) $batch['id'] ? 'selected' : '' ?>
                            >
                                <?= esc($batch['name']) ?>
                            </option>
                        <?php endforeach ?>
                    </select>
                    <?php if ($fieldError('batch_id') !== ''): ?>
                        <p class="mcc-field-error" id="edit-batch-error" role="alert">
                            <?= esc($fieldError('batch_id')) ?>
                        </p>
                    <?php endif ?>
                </div>
                <div class="mcc-session-form-field mcc-session-age-group-field">
                    <label for="edit-age-group-id">
                        Age Group <span class="mcc-form-optional">(optional)</span>
                    </label>
                    <select
                        class="form-control btSelect"
                        id="edit-age-group-id"
                        name="age_group_id"
                        data-bt-select
                        data-bt-select-placeholder="Choose age group"
                        data-bt-select-search-placeholder="Search age groups"
                        aria-describedby="edit-age-group-error"
                    >
                        <option value="">Choose age group</option>
                        <?php foreach (($formOptions['age_groups'] ?? []) as $ageGroup): ?>
                            <option
                                value="<?= (int) $ageGroup['id'] ?>"
                                <?= $selectedAgeGroup === (string) $ageGroup['id'] ? 'selected' : '' ?>
                            >
                                <?= esc($ageGroup['name']) ?>
                            </option>
                        <?php endforeach ?>
                    </select>
                    <?php if ($fieldError('age_group_id') !== ''): ?>
                        <p class="mcc-field-error" id="edit-age-group-error" role="alert">
                            <?= esc($fieldError('age_group_id')) ?>
                        </p>
                    <?php endif ?>
                </div>
                <div class="mcc-session-form-field mcc-session-coach-field"><label for="edit-coach-user-id">Coach</label><select class="form-control btSelect" id="edit-coach-user-id" name="coach_user_id" data-bt-select data-bt-select-placeholder="Choose coach" data-bt-select-search-placeholder="Search coaches" data-session-branch-options aria-describedby="edit-coach-error" <?= $coachLocked ? 'disabled' : '' ?> required><option value="">Choose coach</option><?php foreach ($formOptions['coaches'] as $coach): ?><option value="<?= (int) $coach['id'] ?>" data-branch-id="<?= (int) $coach['branch_id'] ?>" <?= $selectedCoach === (string) $coach['id'] ? 'selected' : '' ?>><?= esc($coach['branch_name'] . ' · ' . $coach['display_name']) ?></option><?php endforeach ?></select><?php if ($coachLocked): ?><input type="hidden" name="coach_user_id" value="<?= (int) $selectedCoach ?>"><?php endif ?><?php if ($fieldError('coach_user_id') !== ''): ?><p class="mcc-field-error" id="edit-coach-error" role="alert"><?= esc($fieldError('coach_user_id')) ?></p><?php endif ?><div class="mcc-form-help mcc-session-availability" data-session-availability data-session-availability-url="<?= site_url('sessions/availability') ?>" aria-live="polite"><span data-session-availability-message>Select a Coach and date to view configured availability.</span><a href="#" data-session-availability-link hidden>Manage Coach availability</a></div></div>
            </div>
        </section>
        <section class="mcc-card mcc-session-form-section" aria-labelledby="edit-players-heading"><div class="mcc-session-form-section-heading"><h3 id="edit-players-heading">Selected Players</h3><p>Choose active Players authorized for this branch, Coach, Batch, and Age Group.</p></div><div class="mcc-session-create-grid"><div class="mcc-session-form-field mcc-session-player-field"><label for="edit-player-ids">Players <span class="mcc-form-optional">(optional)</span></label><select class="form-control btSelect" id="edit-player-ids" name="player_ids[]" multiple data-bt-select data-bt-select-placeholder="Choose players" data-bt-select-search-placeholder="Search players" data-session-branch-options aria-describedby="edit-player-ids-error"><?php foreach (($formOptions['players'] ?? []) as $player): ?><option value="<?= (int) $player['id'] ?>" data-branch-id="<?= (int) $player['branch_id'] ?>" data-coach-ids="<?= esc((string) ($player['coach_user_ids'] ?? '')) ?>" data-player-batch="<?= (int) ($player['batch_id'] ?? 0) ?>" data-player-age-group="<?= (int) ($player['age_group_id'] ?? 0) ?>" data-search-text="<?= esc((string) $player['full_name']) ?>" <?= in_array((string) $player['id'], $selectedPlayers, true) ? 'selected' : '' ?>><?= esc((string) $player['full_name']) ?></option><?php endforeach ?></select><?php if ($fieldError('player_ids') !== ''): ?><p class="mcc-field-error" id="edit-player-ids-error" role="alert"><?= esc($fieldError('player_ids')) ?></p><?php endif ?><p class="mcc-session-empty-state mcc-form-help" data-session-player-empty hidden role="status">Select a branch, Coach, Batch, or Age Group to load authorized Players.</p><p class="mcc-form-help" data-session-group-size aria-live="polite"><strong><?= count($selectedPlayers) ?> players selected</strong></p></div></div></section>
        <section class="mcc-card mcc-session-form-section" aria-labelledby="edit-date-time-heading">
            <div class="mcc-session-form-section-heading">
                <h3 id="edit-date-time-heading">Date and Time</h3>
                <p>Use MCC local date and time values for the session window.</p>
            </div>
            <div class="mcc-session-datetime-grid">
                <div class="mcc-session-datetime-field">
                    <span class="mcc-session-datetime-label">Start</span>
                    <div class="mcc-session-datetime-inputs">
                        <div class="mcc-session-form-field">
                            <label for="edit-starts-at">Start date</label>
                            <input
                                class="form-control"
                                id="edit-starts-at"
                                name="starts_at"
                                type="text"
                                inputmode="numeric"
                                autocomplete="off"
                                value="<?= esc($datePart($startsValue)) ?>"
                                data-mcc-date-picker
                                data-mcc-date-picker-no-toggle
                                data-mcc-date-format="d-m-Y"
                                data-mcc-date-submit-format="Y-m-d"
                                data-mcc-date-picker-time-input="edit-starts-time"
                                aria-describedby="edit-starts-at-error"
                                placeholder="DD-MM-YYYY"
                                required
                            >
                            <label for="edit-starts-time">Start time</label>
                            <input
                                class="form-control"
                                id="edit-starts-time"
                                name="starts_time"
                                type="time"
                                value="<?= esc($timePart($startsValue)) ?>"
                                aria-describedby="edit-starts-at-error"
                                required
                            >
                        </div>
                    </div>
                    <?php $startError = $fieldError('starts_at') ?: $fieldError('starts_time'); ?>
                    <?php if ($startError !== ''): ?>
                        <p class="mcc-field-error" id="edit-starts-at-error" role="alert">
                            <?= esc($startError) ?>
                        </p>
                    <?php endif ?>
                </div>
                <div class="mcc-session-datetime-field">
                    <span class="mcc-session-datetime-label">End</span>
                    <div class="mcc-session-datetime-inputs mcc-session-end-time-inputs">
                        <div class="mcc-session-form-field">
                            <label for="edit-ends-time">End time</label>
                            <input
                                class="form-control"
                                id="edit-ends-time"
                                name="ends_time"
                                type="time"
                                value="<?= esc($selectedEndTime) ?>"
                                aria-describedby="edit-ends-time-error"
                            >
                        </div>
                    </div>
                    <?php if ($fieldError('ends_time') !== '' || $fieldError('starts_at') !== ''): ?>
                        <p class="mcc-field-error" id="edit-ends-time-error" role="alert">
                            <?= esc($fieldError('ends_time') ?: $fieldError('starts_at')) ?>
                        </p>
                    <?php endif ?>
                </div>
            </div>
        </section>
        <section class="mcc-card mcc-session-form-section" aria-labelledby="edit-ground-heading"><div class="mcc-session-form-section-heading"><h3 id="edit-ground-heading">Ground</h3><p>Select a ground when the session requires one.</p></div><div class="mcc-session-create-grid"><div class="mcc-session-form-field"><label for="edit-ground-id">Ground <span class="mcc-form-optional">(optional)</span></label><select class="form-control" id="edit-ground-id" name="ground_id" data-session-branch-options aria-describedby="edit-ground-error"><option value="">Choose ground</option><?php foreach ($formOptions['grounds'] as $ground): ?><option value="<?= (int) $ground['id'] ?>" data-branch-id="<?= (int) $ground['branch_id'] ?>" <?= (string) old('ground_id', $session['ground_id'] ?? '') === (string) $ground['id'] ? 'selected' : '' ?>><?= esc($ground['branch_name'] . ' · ' . $ground['name']) ?></option><?php endforeach ?></select><?php if ($fieldError('ground_id') !== ''): ?><p class="mcc-field-error" id="edit-ground-error" role="alert"><?= esc($fieldError('ground_id')) ?></p><?php endif ?></div></div></section>
        <section class="mcc-card mcc-session-form-section" aria-labelledby="edit-skill-heading"><div class="mcc-session-form-section-heading"><h3 id="edit-skill-heading">Skill Area and Skill / Shot</h3><p>Optionally focus this session on a skill area and skill.</p></div><div class="mcc-session-create-grid"><div class="mcc-session-form-field"><label for="edit-skill-area">Skill area <span class="mcc-form-optional">(optional)</span></label><select class="form-control" id="edit-skill-area" name="skill_area" data-session-skill-area aria-describedby="edit-skill-area-error"><option value="">Choose skill area</option><?php foreach ($skillAreas as $areaValue => $areaLabel): ?><option value="<?= esc($areaValue) ?>" <?= (string) old('skill_area', $session['skill_area'] ?? '') === $areaValue ? 'selected' : '' ?>><?= esc($areaLabel) ?></option><?php endforeach ?></select><?php if ($fieldError('skill_area') !== ''): ?><p class="mcc-field-error" id="edit-skill-area-error" role="alert"><?= esc($fieldError('skill_area')) ?></p><?php endif ?></div><div class="mcc-session-form-field mcc-session-skill-field"><label for="edit-skill-option-id">Skill / Shot <span class="mcc-form-optional">(optional)</span></label><select class="form-control btSelect" id="edit-skill-option-id" name="skill_option_id" data-session-skill-option data-bt-select data-bt-select-placeholder="Choose skill" data-bt-select-search-placeholder="Search skills / shots" aria-describedby="edit-skill-option-error" disabled><option value="">Choose skill</option><?php foreach (($formOptions['skillOptions'] ?? []) as $skillOption): ?><option value="<?= (int) $skillOption['id'] ?>" data-skill-area="<?= esc((string) $skillOption['area']) ?>" data-skill-active="<?= (int) ($skillOption['is_active'] ?? 0) ?>" data-skill-retired="<?= (int) ($skillOption['is_active'] ?? 0) === 1 ? '0' : '1' ?>" <?= (string) old('skill_option_id', $session['skill_option_id'] ?? '') === (string) $skillOption['id'] ? 'selected' : '' ?>><?= esc((string) $skillOption['name']) ?></option><?php endforeach ?></select><?php if ($fieldError('skill_option_id') !== ''): ?><p class="mcc-field-error" id="edit-skill-option-error" role="alert"><?= esc($fieldError('skill_option_id')) ?></p><?php endif ?><p class="mcc-form-help">Optional. Choose an area first to filter the available skills.</p></div></div></section>
        <div class="mcc-session-form-feedback mcc-alert" data-session-response hidden role="status"></div>
        <div class="mcc-session-form-actions mcc-form-actions"><button class="mcc-button mcc-button-primary" type="submit"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i><span class="mcc-button-label">Save Session</span></button><a class="mcc-button mcc-button-secondary" href="<?= site_url('sessions/' . (int) $session['id'] . '/page') ?>">Cancel</a></div>
    </form>
</div>
<?= view('staff_footer') ?>
