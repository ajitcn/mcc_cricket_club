<?php

$defaultBranchId = (int) ($formOptions['default_branch_id'] ?? 0);
$selectedBranch = (string) old('branch_id', $defaultBranchId > 0 ? $defaultBranchId : '');
$defaultCoachUserId = (int) ($formOptions['default_coach_user_id'] ?? 0);
$selectedCoachUserId = (string) old('coach_user_id', $defaultCoachUserId > 0 ? $defaultCoachUserId : '');
$selectedAgeGroupId = (string) old('age_group_id', '');
$submittedPlayerIds = old('player_ids', []);
$selectedPlayerIds = is_array($submittedPlayerIds)
    ? array_map('strval', $submittedPlayerIds)
    : [];
$selectedSessionType = (string) old('session_type', '');
$selectedDescription = (string) old('description', '');
$fieldErrors = session()->getFlashdata('field_errors');
$fieldErrors = is_array($fieldErrors) ? $fieldErrors : [];
$fieldError = static fn(string $field): string => (string) ($fieldErrors[$field] ?? '');
$isBranchAdmin = (bool) ($isBranchAdmin ?? false);
$isCoachScoped = (bool) ($isCoachScoped ?? false);
$activeBranchId = ($activeBranchId ?? null) === null ? null : (int) $activeBranchId;
$branchLocked = $isBranchAdmin || ($isCoachScoped && $selectedBranch !== '');
$coachLocked = $isCoachScoped && $selectedCoachUserId !== '';

$datePart = static function (string $value): string {
    if (preg_match('/^(\d{2})-(\d{2})-(\d{4})/', $value, $matches)) {
        return $matches[0];
    }

    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $value, $matches)) {
        return $matches[3] . '-' . $matches[2] . '-' . $matches[1];
    }

    return '';
};

$timePart = static function (string $value): string {
    return preg_match('/(?:T| )(\d{2}:\d{2})/', $value, $matches)
        ? $matches[1]
        : '';
};

$startsValue = (string) old('starts_at', '');
$selectedEndTime = (string) old('ends_time', '');
$skillAreas = [
    'batting' => 'Batting',
    'bowling' => 'Bowling',
    'fielding' => 'Fielding',
    'wicketkeeping' => 'Wicketkeeping',
    'fitness' => 'Fitness',
    'game_awareness' => 'Game awareness',
];
?>
<?= view('staff_header', [
    'title' => 'Create Session',
    'nav' => $nav,
    'message' => $message,
    'error' => $error,
]) ?>
<div class="mcc-scheduling-card mcc-session-create-card">
    <?php if (! empty($formOptions['needs_active_branch'])): ?>
        <p class="mcc-alert mcc-alert-info" role="status">
            Select an active branch in the shared navigation before creating a session.
        </p>
    <?php endif ?>

    <form
        id="session-create-form"
        method="post"
        action="<?= site_url('sessions') ?>"
        class="mcc-session-create-form"
        data-session-submit-form
    >
        <?= csrf_field() ?>
        <input type="hidden" name="status" value="scheduled">
        <input type="hidden" name="player_ids_present" value="1">

        <section class="mcc-card mcc-session-form-section" aria-labelledby="session-details-heading">
            <div class="mcc-session-form-section-heading">
                <h3 id="session-details-heading">Session Details</h3>
                <p>Choose the program for this coaching session.</p>
            </div>
            <div class="mcc-session-create-grid">
                <div class="mcc-session-form-field">
                    <label for="session-type">Session Type</label>
                    <select
                        class="form-control"
                        id="session-type"
                        name="session_type"
                        aria-describedby="session-type-error"
                        required
                    >
                        <option value="">Choose session type</option>
                        <option value="one_to_one" <?= $selectedSessionType === 'one_to_one' ? 'selected' : '' ?>>Personal</option>
                        <option value="group" <?= $selectedSessionType === 'group' ? 'selected' : '' ?>>Group</option>
                        <option value="elite" <?= $selectedSessionType === 'elite' ? 'selected' : '' ?>>Elite</option>
                    </select>
                    <?php if ($fieldError('session_type') !== ''): ?>
                        <p class="mcc-field-error" id="session-type-error" role="alert">
                            <?= esc($fieldError('session_type')) ?>
                        </p>
                    <?php endif ?>
                </div>
                <div class="mcc-session-form-field">
                    <label for="program-id">Program</label>
                    <select
                        class="form-control"
                        id="program-id"
                        name="program_id"
                        data-session-branch-options
                        aria-describedby="program-id-error"
                        required
                    >
                        <option value="">Choose program</option>
                        <?php foreach ($formOptions['programs'] as $program): ?>
                            <option
                                value="<?= (int) $program['id'] ?>"
                                <?= (string) old('program_id', '') === (string) $program['id'] ? 'selected' : '' ?>
                            >
                                <?= esc($program['name']) ?>
                            </option>
                        <?php endforeach ?>
                    </select>
                    <?php if ($fieldError('program_id') !== ''): ?>
                        <p class="mcc-field-error" id="program-id-error" role="alert">
                            <?= esc($fieldError('program_id')) ?>
                        </p>
                    <?php endif ?>
                </div>
                <div class="mcc-session-form-field mcc-session-description-field">
                    <label for="session-description">Description / Notes</label>
                    <textarea
                        class="form-control"
                        id="session-description"
                        name="description"
                        rows="4"
                        aria-describedby="session-description-help"
                    ><?= esc($selectedDescription) ?></textarea>
                    <p class="mcc-form-help" id="session-description-help">
                        Add optional context for this Session.
                    </p>
                </div>
            </div>
        </section>

        <section class="mcc-card mcc-session-form-section" aria-labelledby="session-branch-coach-heading">
            <div class="mcc-session-form-section-heading">
                <h3 id="session-branch-coach-heading">Branch / Session Category / Coach</h3>
                <p>Limit the session to an authorized branch, Batch, Age Group, and Coach.</p>
            </div>
            <div class="mcc-session-create-grid">
                <div class="mcc-session-form-field">
                    <label for="branch-id">Branch</label>
                    <select
                        class="form-control"
                        id="branch-id"
                        name="branch_id"
                        data-session-branch
                        aria-describedby="branch-id-error"
                        <?= $branchLocked ? 'disabled' : '' ?>
                        required
                    >
                        <option value="">Choose branch</option>
                        <?php foreach ($formOptions['branches'] as $branch): ?>
                            <option
                                value="<?= (int) $branch['id'] ?>"
                                <?= ($isBranchAdmin && $activeBranchId === (int) $branch['id'])
                                    || (! $isBranchAdmin && $selectedBranch === (string) $branch['id'])
                                    ? 'selected'
                                    : '' ?>
                            >
                                <?= esc($branch['name']) ?>
                            </option>
                        <?php endforeach ?>
                    </select>
                    <?php if ($branchLocked): ?>
                        <input type="hidden" name="branch_id" value="<?= (int) $selectedBranch ?>">
                    <?php endif ?>
                    <?php if ($fieldError('branch_id') !== ''): ?>
                        <p class="mcc-field-error" id="branch-id-error" role="alert">
                            <?= esc($fieldError('branch_id')) ?>
                        </p>
                    <?php endif ?>
                </div>

                <div class="mcc-session-form-field mcc-session-batch-field">
                    <label for="batch-id">
                        Batch <span class="mcc-form-optional">(optional)</span>
                    </label>
                    <select
                        class="form-control"
                        id="batch-id"
                        name="batch_id"
                        data-bt-select
                        data-bt-select-placeholder="Choose batch"
                        data-bt-select-search-placeholder="Search batches"
                        aria-describedby="batch-id-error"
                    >
                        <option value="">Choose batch</option>
                        <?php foreach ($formOptions['batches'] as $batch): ?>
                            <option
                                value="<?= (int) $batch['id'] ?>"
                                <?= (string) old('batch_id', '') === (string) $batch['id'] ? 'selected' : '' ?>
                            >
                                <?= esc($batch['name']) ?>
                            </option>
                        <?php endforeach ?>
                    </select>
                    <?php if ($fieldError('batch_id') !== ''): ?>
                        <p class="mcc-field-error" id="batch-id-error" role="alert">
                            <?= esc($fieldError('batch_id')) ?>
                        </p>
                    <?php endif ?>
                </div>

                <div class="mcc-session-form-field mcc-session-age-group-field">
                    <label for="age-group-id">
                        Age Group <span class="mcc-form-optional">(optional)</span>
                    </label>
                    <select
                        class="form-control btSelect"
                        id="age-group-id"
                        name="age_group_id"
                        data-bt-select
                        data-bt-select-placeholder="Choose age group"
                        data-bt-select-search-placeholder="Search age groups"
                        aria-describedby="age-group-id-error"
                    >
                        <option value="">Choose age group</option>
                        <?php foreach (($formOptions['age_groups'] ?? []) as $ageGroup): ?>
                            <option
                                value="<?= (int) $ageGroup['id'] ?>"
                                <?= $selectedAgeGroupId === (string) $ageGroup['id'] ? 'selected' : '' ?>
                            >
                                <?= esc($ageGroup['name']) ?>
                            </option>
                        <?php endforeach ?>
                    </select>
                    <?php if ($fieldError('age_group_id') !== ''): ?>
                        <p class="mcc-field-error" id="age-group-id-error" role="alert">
                            <?= esc($fieldError('age_group_id')) ?>
                        </p>
                    <?php endif ?>
                </div>

                <div class="mcc-session-form-field mcc-session-coach-field">
                    <label for="coach-user-id">Coach</label>
                    <select
                        class="form-control btSelect"
                        id="coach-user-id"
                        name="coach_user_id"
                        data-bt-select
                        data-bt-select-placeholder="Choose coach"
                        data-bt-select-search-placeholder="Search coaches"
                        data-session-branch-options
                        aria-describedby="coach-user-id-error"
                        <?= $coachLocked ? 'disabled' : '' ?>
                        required
                    >
                        <option value="">Choose coach</option>
                        <?php foreach ($formOptions['coaches'] as $coach): ?>
                            <option
                                value="<?= (int) $coach['id'] ?>"
                                data-branch-id="<?= (int) $coach['branch_id'] ?>"
                                <?= $selectedCoachUserId === (string) $coach['id'] ? 'selected' : '' ?>
                            >
                                <?= esc($coach['branch_name'] . ' · ' . $coach['display_name']) ?>
                            </option>
                        <?php endforeach ?>
                    </select>
                    <?php if ($coachLocked): ?>
                        <input type="hidden" name="coach_user_id" value="<?= (int) $selectedCoachUserId ?>">
                    <?php endif ?>
                    <?php if ($fieldError('coach_user_id') !== ''): ?>
                        <p class="mcc-field-error" id="coach-user-id-error" role="alert">
                            <?= esc($fieldError('coach_user_id')) ?>
                        </p>
                    <?php endif ?>
                    <?php if (empty($formOptions['coaches'])): ?>
                        <p class="mcc-form-help" role="status">
                            <?= empty($selectedBranch) && $activeBranchId === null
                                ? 'Select a branch to load available Coaches.'
                                : 'No active authorized Coaches are available for this branch.' ?>
                        </p>
                    <?php endif ?>
                    <div
                        class="mcc-form-help mcc-session-availability"
                        data-session-availability
                        data-session-availability-url="<?= site_url('sessions/availability') ?>"
                        aria-live="polite"
                    >
                        <span data-session-availability-message>
                            Select a Coach and date to view configured availability.
                        </span>
                        <a href="#" data-session-availability-link hidden>
                            Manage Coach availability
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <section class="mcc-card mcc-session-form-section" aria-labelledby="session-players-heading">
            <div class="mcc-session-form-section-heading">
                <h3 id="session-players-heading">Selected Players</h3>
                <p>Choose active Players authorized for this branch, Coach, Batch, and Age Group.</p>
            </div>
            <div class="mcc-session-create-grid">
                <div class="mcc-session-form-field mcc-session-player-field">
                    <label for="player-ids">
                        Players <span class="mcc-form-optional">(optional)</span>
                    </label>
                    <select
                        class="form-control btSelect"
                        id="player-ids"
                        name="player_ids[]"
                        multiple
                        data-bt-select
                        data-bt-select-placeholder="Choose players"
                        data-bt-select-search-placeholder="Search players"
                        data-session-branch-options
                        aria-describedby="player-ids-error"
                    >
                        <?php foreach (($formOptions['players'] ?? []) as $player): ?>
                            <option
                                value="<?= (int) $player['id'] ?>"
                                data-branch-id="<?= (int) $player['branch_id'] ?>"
                                data-coach-ids="<?= esc((string) ($player['coach_user_ids'] ?? '')) ?>"
                                data-player-batch="<?= (int) ($player['batch_id'] ?? 0) ?>"
                                data-player-age-group="<?= (int) ($player['age_group_id'] ?? 0) ?>"
                                data-search-text="<?= esc((string) $player['full_name']) ?>"
                                <?= in_array((string) $player['id'], $selectedPlayerIds, true) ? 'selected' : '' ?>
                            >
                                <?= esc((string) $player['full_name']) ?>
                            </option>
                        <?php endforeach ?>
                    </select>
                    <?php if ($fieldError('player_ids') !== ''): ?>
                        <p class="mcc-field-error" id="player-ids-error" role="alert">
                            <?= esc($fieldError('player_ids')) ?>
                        </p>
                    <?php endif ?>
                    <p class="mcc-session-empty-state mcc-form-help" data-session-player-empty hidden role="status">
                        Select a branch, Coach, Batch, or Age Group to load authorized Players.
                    </p>
                    <p class="mcc-form-help" data-session-group-size aria-live="polite">
                        <strong>0 players selected</strong>
                    </p>
                </div>
            </div>
        </section>

        <section class="mcc-card mcc-session-form-section" aria-labelledby="session-date-time-heading">
            <div class="mcc-session-form-section-heading">
                <h3 id="session-date-time-heading">Date and Time</h3>
                <p>Use MCC local date and time values for the session window.</p>
            </div>
            <div class="mcc-session-datetime-grid">
                <div class="mcc-session-datetime-field">
                    <span class="mcc-session-datetime-label">Start</span>
                    <div class="mcc-session-datetime-inputs">
                        <div class="mcc-session-form-field">
                            <label for="starts-at">Start date</label>
                            <input
                                class="form-control"
                                id="starts-at"
                                name="starts_at"
                                type="text"
                                inputmode="numeric"
                                autocomplete="off"
                                value="<?= esc($datePart($startsValue)) ?>"
                                data-mcc-date-picker
                                data-mcc-date-picker-no-toggle
                                data-mcc-date-format="d-m-Y"
                                data-mcc-date-submit-format="Y-m-d"
                                data-mcc-date-picker-time-input="starts-time"
                                aria-describedby="starts-at-error"
                                placeholder="DD-MM-YYYY"
                                required
                            >
                        </div>
                        <div class="mcc-session-form-field">
                            <label for="starts-time">Start time</label>
                            <input
                                class="form-control"
                                id="starts-time"
                                name="starts_time"
                                type="time"
                                value="<?= esc($timePart($startsValue)) ?>"
                                aria-describedby="starts-at-error"
                                required
                            >
                        </div>
                    </div>
                    <?php if ($fieldError('starts_at') !== ''): ?>
                        <p class="mcc-field-error" id="starts-at-error" role="alert">
                            <?= esc($fieldError('starts_at')) ?>
                        </p>
                    <?php endif ?>
                </div>
                <div class="mcc-session-datetime-field">
                    <span class="mcc-session-datetime-label">End</span>
                    <div class="mcc-session-datetime-inputs mcc-session-end-time-inputs">
                        <div class="mcc-session-form-field">
                            <label for="ends-time">End time</label>
                            <input
                                class="form-control"
                                id="ends-time"
                                name="ends_time"
                                type="time"
                                value="<?= esc($selectedEndTime) ?>"
                                aria-describedby="ends-time-error"
                            >
                        </div>
                    </div>
                    <?php if ($fieldError('ends_time') !== '' || $fieldError('starts_at') !== ''): ?>
                        <p class="mcc-field-error" id="ends-time-error" role="alert">
                            <?= esc($fieldError('ends_time') ?: $fieldError('starts_at')) ?>
                        </p>
                    <?php endif ?>
                </div>
            </div>
        </section>

        <section class="mcc-card mcc-session-form-section" aria-labelledby="session-ground-heading">
            <div class="mcc-session-form-section-heading">
                <h3 id="session-ground-heading">Ground</h3>
                <p>Select a ground when the session requires one.</p>
            </div>
            <div class="mcc-session-create-grid">
                <div class="mcc-session-form-field">
                    <label for="ground-id">
                        Ground <span class="mcc-form-optional">(optional)</span>
                    </label>
                    <select
                        class="form-control"
                        id="ground-id"
                        name="ground_id"
                        data-session-branch-options
                        aria-describedby="ground-id-error"
                    >
                        <option value="">Choose ground</option>
                        <?php foreach ($formOptions['grounds'] as $ground): ?>
                            <option
                                value="<?= (int) $ground['id'] ?>"
                                data-branch-id="<?= (int) $ground['branch_id'] ?>"
                                <?= (string) old('ground_id', '') === (string) $ground['id'] ? 'selected' : '' ?>
                            >
                                <?= esc($ground['branch_name'] . ' · ' . $ground['name']) ?>
                            </option>
                        <?php endforeach ?>
                    </select>
                    <?php if ($fieldError('ground_id') !== ''): ?>
                        <p class="mcc-field-error" id="ground-id-error" role="alert">
                            <?= esc($fieldError('ground_id')) ?>
                        </p>
                    <?php endif ?>
                </div>
            </div>
        </section>

        <section class="mcc-card mcc-session-form-section" aria-labelledby="session-skill-heading">
            <div class="mcc-session-form-section-heading">
                <h3 id="session-skill-heading">Skill Area and Skill / Shot</h3>
                <p>Optionally focus this session on a skill area and skill.</p>
            </div>
            <div class="mcc-session-create-grid">
                <div class="mcc-session-form-field">
                    <label for="skill-area">
                        Skill area <span class="mcc-form-optional">(optional)</span>
                    </label>
                    <select
                        class="form-control"
                        id="skill-area"
                        name="skill_area"
                        data-session-skill-area
                        aria-describedby="skill-area-error"
                    >
                        <option value="">Choose skill area</option>
                        <?php foreach ($skillAreas as $areaValue => $areaLabel): ?>
                            <option
                                value="<?= esc($areaValue) ?>"
                                <?= old('skill_area', '') === $areaValue ? 'selected' : '' ?>
                            >
                                <?= esc($areaLabel) ?>
                            </option>
                        <?php endforeach ?>
                    </select>
                    <?php if ($fieldError('skill_area') !== ''): ?>
                        <p class="mcc-field-error" id="skill-area-error" role="alert">
                            <?= esc($fieldError('skill_area')) ?>
                        </p>
                    <?php endif ?>
                </div>

                <div class="mcc-session-form-field mcc-session-skill-field">
                    <label for="skill-option-id">
                        Skill / Shot <span class="mcc-form-optional">(optional)</span>
                    </label>
                    <select
                        class="form-control btSelect"
                        id="skill-option-id"
                        name="skill_option_id"
                        data-session-skill-option
                        data-bt-select
                        data-bt-select-placeholder="Choose skill"
                        data-bt-select-search-placeholder="Search skills / shots"
                        aria-describedby="skill-option-error"
                        disabled
                    >
                        <option value="">Choose skill</option>
                        <?php foreach (($formOptions['skillOptions'] ?? []) as $skillOption): ?>
                            <option
                                value="<?= (int) $skillOption['id'] ?>"
                                data-skill-area="<?= esc((string) $skillOption['area']) ?>"
                                data-skill-active="<?= (int) ($skillOption['is_active'] ?? 0) ?>"
                                data-skill-retired="<?= (int) ($skillOption['is_active'] ?? 0) === 1 ? '0' : '1' ?>"
                                <?= (string) old('skill_option_id', '') === (string) $skillOption['id'] ? 'selected' : '' ?>
                            >
                                <?= esc((string) $skillOption['name']) ?>
                            </option>
                        <?php endforeach ?>
                    </select>
                    <?php if ($fieldError('skill_option_id') !== ''): ?>
                        <p class="mcc-field-error" id="skill-option-error" role="alert">
                            <?= esc($fieldError('skill_option_id')) ?>
                        </p>
                    <?php endif ?>
                    <p class="mcc-form-help">
                        Optional. Choose an area first to filter the available skills.
                    </p>
                </div>
            </div>
        </section>

        <div class="mcc-session-form-feedback mcc-alert" data-session-response hidden role="status"></div>
        <div class="mcc-session-form-actions mcc-form-actions">
            <button class="mcc-button mcc-button-primary" type="submit">
                <i class="fa-solid fa-calendar-plus" aria-hidden="true"></i>
                <span class="mcc-button-label">Save Session</span>
            </button>
            <a class="mcc-button mcc-button-secondary" href="<?= site_url('sessions') ?>">
                Cancel
            </a>
        </div>
    </form>
</div>
<?= view('staff_footer') ?>
