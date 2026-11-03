<?php

$editing = is_array($tournament);
$fieldErrors = session()->getFlashdata('field_errors') ?? [];
$options = $options ?? [
    'branches' => [],
    'grounds' => [],
    'players' => [],
    'availablePlayers' => [],
    'selectedPlayerIds' => [],
    'opponents' => [],
];
$participants = $tournament['participants'] ?? [];
$selectedOpponentIds = array_values(array_map(
    'strval',
    array_column(
        array_filter(
            $participants,
            static fn (array $row): bool => ($row['participant_type'] ?? '') === 'team'
                && (int) ($row['opponent_id'] ?? 0) > 0
        ),
        'opponent_id'
    )
));
$oldOpponentIds = old('opponent_ids', $selectedOpponentIds);
$selectedOpponentIds = array_values(array_map('strval', is_array($oldOpponentIds) ? $oldOpponentIds : [$oldOpponentIds]));

$selectedBranch = (string) old('branch_id', $tournament['branch_id'] ?? '');
$selectedName = (string) old('name', $tournament['name'] ?? '');
$selectedType = (string) old('tournament_type', $tournament['tournament_type'] ?? 'club');
$selectedStatus = (string) old('status', $tournament['status'] ?? 'planned');
$selectedStart = (string) old('starts_on', mcc_user_date_input($tournament['starts_on'] ?? null));
$selectedEnd = (string) old('ends_on', mcc_user_date_input($tournament['ends_on'] ?? null));
$selectedDescription = (string) old('description', $tournament['description'] ?? '');
$pageActions = '<div class="mcc-page-actions">'
    . '<a class="mcc-button mcc-button-secondary" href="'
    . site_url($editing ? 'tournaments/' . (int) $tournament['id'] : 'tournaments')
    . '"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i>'
    . '<span class="mcc-button-label">Back to Tournaments</span></a></div>';
?>
<?= view('staff_header', ['title' => $title, 'pageActions' => $pageActions]) ?>

<div class="mcc-tournament-form-page">

    <form
        method="post"
        action="<?= site_url($editing ? 'tournaments/' . (int) $tournament['id'] : 'tournaments') ?>"
        class="mcc-form mcc-tournament-form mcc-tournament-form-card"
        data-tournament-submit-form
    >
        <?= csrf_field() ?>

        <section class="mcc-card mcc-tournament-form-section" aria-labelledby="tournament-basic-heading">
            <div class="mcc-card-heading">
                <div>
                    <span class="mcc-eyebrow">Step 1</span>
                    <h2 id="tournament-basic-heading">Basic information</h2>
                </div>
            </div>
            <div class="mcc-tournament-form-grid">
                <div class="mcc-form-field">
                    <label for="tournament-name">Name</label>
                    <input class="form-control" id="tournament-name" name="name" maxlength="150" value="<?= esc($selectedName) ?>" required>
                    <?php if (! empty($fieldErrors['name'])): ?>
                        <p class="mcc-field-error" role="alert"><?= esc($fieldErrors['name']) ?></p>
                    <?php endif ?>
                </div>
                <div class="mcc-form-field">
                    <label for="tournament-type">Type</label>
                    <select class="form-control" id="tournament-type" name="tournament_type" required>
                        <?php foreach (['school', 'club', 'district', 'mca', 'state', 'national', 'international', 'england_tour'] as $type): ?>
                            <option value="<?= $type ?>" <?= $selectedType === $type ? 'selected' : '' ?>>
                                <?= esc(ucfirst(str_replace('_', ' ', $type))) ?>
                            </option>
                        <?php endforeach ?>
                    </select>
                    <?php if (! empty($fieldErrors['tournament_type'])): ?>
                        <p class="mcc-field-error" role="alert"><?= esc($fieldErrors['tournament_type']) ?></p>
                    <?php endif ?>
                </div>
                <div class="mcc-form-field">
                    <label for="tournament-status">Status</label>
                    <select class="form-control" id="tournament-status" name="status" required>
                        <?php foreach (['planned', 'active', 'completed', 'cancelled'] as $status): ?>
                            <option value="<?= $status ?>" <?= $selectedStatus === $status ? 'selected' : '' ?>>
                                <?= esc(ucfirst($status)) ?>
                            </option>
                        <?php endforeach ?>
                    </select>
                    <?php if (! empty($fieldErrors['status'])): ?>
                        <p class="mcc-field-error" role="alert"><?= esc($fieldErrors['status']) ?></p>
                    <?php endif ?>
                </div>
                <?php if ($editing): ?>
                    <div class="mcc-form-field">
                        <label for="tournament-reason">Reason <span class="mcc-form-optional">(required when cancelling)</span></label>
                        <textarea class="form-control" id="tournament-reason" name="reason" rows="3"><?= esc((string) old('reason')) ?></textarea>
                        <?php if (! empty($fieldErrors['reason'])): ?>
                            <p class="mcc-field-error" role="alert"><?= esc($fieldErrors['reason']) ?></p>
                        <?php endif ?>
                    </div>
                <?php endif ?>
            </div>
        </section>

        <section class="mcc-card mcc-tournament-form-section" aria-labelledby="tournament-schedule-heading">
            <div class="mcc-card-heading">
                <div>
                    <span class="mcc-eyebrow">Step 2</span>
                    <h2 id="tournament-schedule-heading">Schedule</h2>
                </div>
            </div>
            <div class="mcc-tournament-form-grid mcc-tournament-form-grid-two">
                <div class="mcc-form-field">
                    <label for="tournament-start">Start date</label>
                    <input
                        class="form-control"
                        id="tournament-start"
                        name="starts_on"
                        type="text"
                        inputmode="numeric"
                        autocomplete="off"
                        placeholder="DD-MM-YYYY"
                        data-mcc-date-picker
                        data-mcc-date-picker-no-toggle
                        data-mcc-date-format="d-m-Y"
                        data-mcc-date-submit-format="Y-m-d"
                        value="<?= esc($selectedStart) ?>"
                        required
                    >
                    <?php if (! empty($fieldErrors['starts_on'])): ?>
                        <p class="mcc-field-error" role="alert"><?= esc($fieldErrors['starts_on']) ?></p>
                    <?php endif ?>
                </div>
                <div class="mcc-form-field">
                    <label for="tournament-end">End date</label>
                    <input
                        class="form-control"
                        id="tournament-end"
                        name="ends_on"
                        type="text"
                        inputmode="numeric"
                        autocomplete="off"
                        placeholder="DD-MM-YYYY"
                        data-mcc-date-picker
                        data-mcc-date-picker-no-toggle
                        data-mcc-date-format="d-m-Y"
                        data-mcc-date-submit-format="Y-m-d"
                        value="<?= esc($selectedEnd) ?>"
                        required
                    >
                    <?php if (! empty($fieldErrors['ends_on'])): ?>
                        <p class="mcc-field-error" role="alert"><?= esc($fieldErrors['ends_on']) ?></p>
                    <?php endif ?>
                </div>
            </div>
        </section>

        <section class="mcc-card mcc-tournament-form-section" aria-labelledby="tournament-location-heading">
            <div class="mcc-card-heading">
                <div>
                    <span class="mcc-eyebrow">Step 3</span>
                    <h2 id="tournament-location-heading">Branch and Teams</h2>
                </div>
            </div>
            <div class="mcc-tournament-form-grid mcc-tournament-form-grid-two">
                <div class="mcc-form-field">
                    <label for="tournament-branch">Branch</label>
                    <select class="form-control" id="tournament-branch" name="branch_id" required data-tournament-branch>
                        <option value="">Select a branch</option>
                        <?php foreach ($options['branches'] as $branch): ?>
                            <option value="<?= (int) $branch['id'] ?>" <?= $selectedBranch === (string) $branch['id'] ? 'selected' : '' ?>><?= esc($branch['name']) ?></option>
                        <?php endforeach ?>
                    </select>
                    <?php if (! empty($fieldErrors['branch_id'])): ?>
                        <p class="mcc-field-error" role="alert"><?= esc($fieldErrors['branch_id']) ?></p>
                    <?php endif ?>
                </div>
                <div class="mcc-form-field mcc-form-field-wide">
                    <label for="tournament-opponents">Opponents</label>
                    <select
                        class="form-control btSelect"
                        id="tournament-opponents"
                        name="opponent_ids[]"
                        multiple
                        data-bt-select
                        data-bt-select-placeholder="Choose opponents"
                        data-bt-select-search-placeholder="Search opponents"
                    >
                        <?php foreach (($options['opponents'] ?? []) as $opponent): ?>
                            <option value="<?= (int) $opponent['id'] ?>" <?= in_array((string) $opponent['id'], $selectedOpponentIds, true) ? 'selected' : '' ?>>
                                <?= esc($opponent['name']) ?>
                            </option>
                        <?php endforeach ?>
                    </select>
                </div>
            </div>
        </section>

        <section class="mcc-card mcc-tournament-form-section" aria-labelledby="tournament-notes-heading">
            <div class="mcc-card-heading">
                <div>
                    <span class="mcc-eyebrow">Step 4</span>
                    <h2 id="tournament-notes-heading">Description and notes</h2>
                </div>
            </div>
            <div class="mcc-form-field">
                <label for="tournament-description">Description <span class="mcc-form-optional">(optional)</span></label>
                <textarea class="form-control" id="tournament-description" name="description" rows="4"><?= esc($selectedDescription) ?></textarea>
            </div>
        </section>

        <div class="mcc-alert mcc-tournament-form-feedback" data-tournament-response hidden role="status"></div>
        <div class="mcc-form-actions mcc-tournament-form-actions">
            <a class="mcc-button mcc-button-secondary" href="<?= site_url($editing ? 'tournaments/' . (int) $tournament['id'] : 'tournaments') ?>">Cancel</a>
            <button class="mcc-button mcc-button-primary" type="submit"><?= $editing ? 'Update tournament' : 'Create tournament' ?></button>
        </div>
    </form>
</div>

<?= view('staff_footer') ?>
