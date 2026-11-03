<?php

$editing = is_array($plan ?? null);
$options = is_array($options ?? null)
    ? $options
    : [
        'branches' => [],
        'players' => [],
        'coaches' => [],
        'programs' => [],
        'batches' => [],
    ];
$fieldErrors = session()->getFlashdata('field_errors');
$fieldErrors = is_array($fieldErrors) ? $fieldErrors : [];
$fieldError = static function (string $field) use ($fieldErrors): string {
    return trim((string) ($fieldErrors[$field] ?? ''));
};
$selectedBranch = (string) old('branch_id', $plan['branch_id'] ?? '');
$selectedPlayer = (string) old('player_id', $plan['player_id'] ?? '');
$selectedCoach = (string) old('coach_user_id', $plan['coach_user_id'] ?? '');
$selectedProgram = (string) old('program_id', '');
$selectedBatch = (string) old('batch_id', '');
$selectedStatus = (string) old('status', $plan['status'] ?? 'active');
$pageActions = '<div class="mcc-page-actions">'
    . '<a class="mcc-button mcc-button-secondary" href="'
    . site_url($editing ? 'development/' . (int) $plan['id'] : 'development')
    . '">'
    . '<i class="fa-solid fa-arrow-left" aria-hidden="true"></i>'
    . '<span class="mcc-button-label">Back to development</span>'
    . '</a></div>';
?>
<?= view('staff_header', ['title' => $title, 'pageActions' => $pageActions]) ?>

<section class="mcc-card mcc-development-form-card" aria-labelledby="development-form-heading">
    <div class="mcc-card-heading">
        <div>
            <span class="mcc-eyebrow">Player development</span>
            <h2 id="development-form-heading">
                <?= $editing ? 'Edit development plan' : 'Create development plan' ?>
            </h2>
            <p class="mcc-card-subtitle">
                Choose the authorized development context and plan details.
            </p>
        </div>
    </div>

    <?php if (! empty($error)): ?>
        <div class="mcc-alert mcc-alert-danger" role="alert">
            <?= esc($error) ?>
        </div>
    <?php endif; ?>

    <form
        method="post"
        action="<?= site_url($editing ? 'development/' . (int) $plan['id'] : 'development') ?>"
        class="mcc-form-grid mcc-development-form"
        data-development-submit-form
    >
        <?= csrf_field() ?>

        <?php if ($editing): ?>
            <input type="hidden" name="branch_id" value="<?= esc($selectedBranch) ?>">
        <?php endif; ?>

        <div class="mcc-form-field mcc-form-field-wide">
            <label for="development-title">Plan title</label>
            <input
                class="form-control"
                id="development-title"
                name="title"
                type="text"
                maxlength="150"
                value="<?= esc(old('title', $plan['title'] ?? '')) ?>"
                aria-describedby="development-title-error"
                required
            >
            <?php if ($fieldError('title') !== ''): ?>
                <p class="mcc-field-error" id="development-title-error" role="alert">
                    <?= esc($fieldError('title')) ?>
                </p>
            <?php endif; ?>
        </div>

        <div class="mcc-form-field">
            <label for="development-branch">Branch</label>
            <select
                class="form-control"
                id="development-branch"
                name="branch_id"
                data-development-branch
                <?= $editing ? 'disabled' : '' ?>
                aria-describedby="development-branch-error"
                required
            >
                <option value="">Select a branch</option>
                <?php foreach ($options['branches'] as $branch): ?>
                    <option
                        value="<?= (int) $branch['id'] ?>"
                        <?= (string) $branch['id'] === $selectedBranch ? 'selected' : '' ?>
                    >
                        <?= esc($branch['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if ($fieldError('branch_id') !== ''): ?>
                <p class="mcc-field-error" id="development-branch-error" role="alert">
                    <?= esc($fieldError('branch_id')) ?>
                </p>
            <?php endif; ?>
        </div>

        <div class="mcc-form-field">
            <label for="development-program">Program context</label>
            <select
                class="form-control"
                id="development-program"
                name="program_id"
                data-development-program
            >
                <option value="">All authorized Programs</option>
                <?php foreach ($options['programs'] as $program): ?>
                    <option
                        value="<?= (int) $program['id'] ?>"
                        <?= (string) $program['id'] === $selectedProgram ? 'selected' : '' ?>
                    >
                        <?= esc($program['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if ($fieldError('program_id') !== ''): ?>
                <p class="mcc-field-error" role="alert"><?= esc($fieldError('program_id')) ?></p>
            <?php endif; ?>
        </div>

        <div class="mcc-form-field">
            <label for="development-batch">Batch context</label>
            <select
                class="form-control"
                id="development-batch"
                name="batch_id"
                data-development-batch
            >
                <option value="">All authorized Batches</option>
                <?php foreach ($options['batches'] as $batch): ?>
                    <option
                        value="<?= (int) $batch['id'] ?>"
                        data-branch-id="<?= (int) ($batch['branch_id'] ?? 0) ?>"
                        data-program-id="<?= (int) ($batch['program_id'] ?? 0) ?>"
                        <?= (string) $batch['id'] === $selectedBatch ? 'selected' : '' ?>
                    >
                        <?= esc(($batch['branch_name'] ?? '') . ' - ' . $batch['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if ($fieldError('batch_id') !== ''): ?>
                <p class="mcc-field-error" role="alert"><?= esc($fieldError('batch_id')) ?></p>
            <?php endif; ?>
        </div>

        <div class="mcc-form-field">
            <label for="development-player">Player</label>
            <select
                class="form-control selectpicker btSelect"
                id="development-player"
                name="player_id"
                data-development-player
                data-bt-select
                data-live-search="true"
                data-actions-box="true"
                title="Select a Player"
                aria-describedby="development-player-error"
                required
            >
                <option value="" hidden selected>Select a Player</option>
                <?php if ($options['players'] === []): ?>
                    <option value="" disabled>No eligible Players are available.</option>
                <?php endif; ?>
                <?php foreach ($options['players'] as $player): ?>
                    <option value="<?= (int) $player['id'] ?>" data-player-id="<?= (int) $player['id'] ?>" data-branch-id="<?= (int) $player['branch_id'] ?>" <?= (string) $player['id'] === $selectedPlayer ? 'selected' : '' ?>><?= esc($player['full_name']) ?> (Player #<?= (int) $player['id'] ?>)</option>
                <?php endforeach; ?>
            </select>
            <?php if ($fieldError('player_id') !== ''): ?>
                <p class="mcc-field-error" id="development-player-error" role="alert">
                    <?= esc($fieldError('player_id')) ?>
                </p>
            <?php endif; ?>
        </div>

        <div class="mcc-form-field">
            <label for="development-coach">Coach</label>
            <select
                class="form-control selectpicker btSelect"
                id="development-coach"
                name="coach_user_id"
                data-development-coach
                data-bt-select
                data-live-search="true"
                data-actions-box="true"
                title="Select a Coach"
                aria-describedby="development-coach-error"
                required
            >
                <option value="" hidden selected>Select an eligible Coach</option>
                <?php if ($options['coaches'] === []): ?>
                    <option value="" disabled>No eligible Coaches are available.</option>
                <?php endif; ?>
                <?php foreach ($options['coaches'] as $coach): ?>
                    <option value="<?= (int) $coach['id'] ?>" data-branch-id="<?= (int) $coach['branch_id'] ?>" <?= (string) $coach['id'] === $selectedCoach ? 'selected' : '' ?>><?= esc($coach['display_name']) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if ($fieldError('coach_user_id') !== ''): ?>
                <p class="mcc-field-error" id="development-coach-error" role="alert">
                    <?= esc($fieldError('coach_user_id')) ?>
                </p>
            <?php endif; ?>
        </div>

        <div class="mcc-form-field">
            <label for="development-status">Status</label>
            <select
                class="form-control"
                id="development-status"
                name="status"
                aria-describedby="development-status-error"
            >
                <?php foreach (['active' => 'Active', 'completed' => 'Completed', 'cancelled' => 'Cancelled'] as $value => $label): ?>
                    <option value="<?= esc($value) ?>" <?= $selectedStatus === $value ? 'selected' : '' ?>>
                        <?= esc($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if ($fieldError('status') !== ''): ?>
                <p class="mcc-field-error" id="development-status-error" role="alert">
                    <?= esc($fieldError('status')) ?>
                </p>
            <?php endif; ?>
        </div>

        <div class="mcc-form-field">
            <label for="development-target-date">Target date</label>
            <input
                class="form-control"
                id="development-target-date"
                name="target_date"
                type="text"
                inputmode="numeric"
                autocomplete="off"
                placeholder="DD-MM-YYYY"
                data-mcc-date-picker
                data-mcc-date-picker-no-toggle
                data-mcc-date-format="d-m-Y"
                data-mcc-date-submit-format="Y-m-d"
                value="<?= esc(mcc_user_date_input(old('target_date', $plan['target_date'] ?? null))) ?>"
                aria-describedby="development-target-date-error"
            >
            <?php if ($fieldError('target_date') !== ''): ?>
                <p class="mcc-field-error" id="development-target-date-error" role="alert">
                    <?= esc($fieldError('target_date')) ?>
                </p>
            <?php endif; ?>
        </div>

        <div class="mcc-form-field mcc-form-field-wide">
            <label for="development-notes">Notes</label>
            <textarea
                class="form-control"
                id="development-notes"
                name="notes"
                maxlength="10000"
                aria-describedby="development-notes-error"
            ><?= esc(old('notes', $plan['notes'] ?? '')) ?></textarea>
            <?php if ($fieldError('notes') !== ''): ?>
                <p class="mcc-field-error" id="development-notes-error" role="alert">
                    <?= esc($fieldError('notes')) ?>
                </p>
            <?php endif; ?>
        </div>

        <div class="mcc-form-actions mcc-development-actions">
            <div class="mcc-alert mcc-development-form-feedback" data-development-response hidden role="status"></div>
            <button class="mcc-button mcc-button-primary" type="submit">
                <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
                <span class="mcc-button-label"><?= $editing ? 'Save changes' : 'Save plan' ?></span>
            </button>
            <a class="mcc-button mcc-button-secondary" href="<?= site_url($editing ? 'development/' . (int) $plan['id'] : 'development') ?>">
                Cancel
            </a>
        </div>
    </form>
</section>

<?= view('staff_footer') ?>
