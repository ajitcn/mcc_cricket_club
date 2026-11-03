<?php

$editing = is_array($plan ?? null);
$player = is_array($player ?? null) ? $player : [];
$fieldErrors = session()->getFlashdata('field_errors');
$fieldErrors = is_array($fieldErrors) ? $fieldErrors : [];
$fieldError = static fn (string $field): string => trim((string) ($fieldErrors[$field] ?? ''));
$value = static function (string $field, array $source = []) use ($plan): string {
    return (string) old($field, $source[$field] ?? ($plan[$field] ?? ''));
};
$topFields = [
    'problem' => 'Problem',
    'outcome' => 'Outcome',
    'method' => 'Method',
    'stage' => 'Stage',
    'area' => 'Area',
    'coaching_focus' => 'Coaching Focus',
    'safety_considerations' => 'Safety Considerations',
];
$practiceSections = [
    'level_1' => ['title' => 'Level 1 Practice', 'duration' => '2–5 mins'],
    'level_2' => ['title' => 'Level 2 Practice', 'duration' => '5–9 mins'],
    'debrief' => ['title' => 'Debrief', 'duration' => '9–10 mins'],
];
$practiceFields = [
    'fixed_variable' => 'Fixed / Variable',
    'coaching_tools' => 'Coaching Tools',
    'equipment' => 'Equipment',
    'easier' => 'Easier',
    'harder' => 'Harder',
];
$coachingTools = \App\Services\PlayerSessionPlanService::COACHING_TOOLS;
$coachingFocus = \App\Services\PlayerSessionPlanService::COACHING_FOCUS;
$selectedTools = static function (string $field) use ($plan): array {
    $raw = old($field, $plan[$field] ?? []);

    if (is_array($raw)) {
        return array_values(array_filter(array_map('strval', $raw)));
    }

    $decoded = json_decode((string) $raw, true);

    return is_array($decoded)
        ? array_values(array_filter(array_map('strval', $decoded)))
        : [];
};
$selectedFocus = static function () use ($plan, $coachingFocus): array {
    $raw = old('coaching_focus', $plan['coaching_focus'] ?? []);
    $decoded = is_array($raw) ? $raw : json_decode((string) $raw, true);

    if (is_array($decoded)) {
        return array_values(array_filter(
            array_map('strval', $decoded),
            static fn (string $focus): bool => in_array($focus, $coachingFocus, true),
        ));
    }

    $legacy = [];
    foreach ($coachingFocus as $focus) {
        if (trim((string) ($plan[strtolower($focus)] ?? '')) === $focus) {
            $legacy[] = $focus;
        }
    }

    return $legacy;
};
$assignedCoach = trim((string) ($player['personal_coach_name'] ?? ''));
if ($assignedCoach === '') {
    $assignedCoach = trim((string) ($player['group_coach_name'] ?? ''));
}
if ($assignedCoach === '') {
    $assignedCoach = trim((string) ($player['coach_name'] ?? ''));
}
if ($assignedCoach === '') {
    $assignedCoach = 'Not assigned';
}
$backUrl = site_url('players/' . (int) ($player['id'] ?? $player['player_id'] ?? 0));
$pageActions = '<div class="mcc-page-actions">
    <a class="mcc-button mcc-button-secondary" href="' . esc($backUrl) . '">
        <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
        Back
    </a>
</div>';
?>
<?= view('staff_header', [
    'title' => $editing ? 'Edit Session Plan' : 'Create Session Plan',
    'nav' => $nav,
    'message' => $message,
    'error' => $error,
    'breadcrumbTrail' => [
        ['label' => 'Players', 'url' => site_url('players')],
        ['label' => 'Session Plans', 'url' => site_url('player-session-plans')],
        ['label' => $editing ? 'Edit Session Plan' : 'Create Session Plan'],
    ],
    'pageActions' => $pageActions,
]) ?>

<div class="mcc-player-session-plan-form-page">
    <form
        method="post"
        action="<?= esc($editing ? site_url('player-session-plans/' . (int) $plan['id']) : site_url('player-session-plans')) ?>"
        class="mcc-card mcc-player-session-plan-form mcc-player-session-plan-form-card"
    >
        <?= csrf_field() ?>
        <input type="hidden" name="expected_version" value="<?= (int) ($editing ? ($plan['version'] ?? 0) : 0) ?>">

        <section class="mcc-player-session-plan-form-section" aria-labelledby="session-plan-details-heading">
            <div class="mcc-card-heading">
                <div>
                    <span class="mcc-eyebrow">Coach workspace</span>
                    <h1 id="session-plan-details-heading"><?= $editing ? 'Edit Session Plan' : 'Create Session Plan' ?></h1>
                    <h2>Player and Plan Details</h2>
                    <p class="mcc-card-subtitle">Record the plan details and practice progressions.</p>
                </div>
            </div>
            <div class="mcc-player-session-plan-form-grid">
                <div class="mcc-form-field mcc-player-session-plan-form-field-wide">
                    <label for="session-plan-player">Player</label>
                    <div class="mcc-readonly-field" aria-readonly="true">
                        <?= esc((string) ($player['player_name'] ?? 'Not available')) ?>
                    </div>
                    <input type="hidden" name="player_id" value="<?= (int) ($player['id'] ?? $player['player_id'] ?? 0) ?>">
                    <?php if ($fieldError('player_id') !== ''): ?>
                        <p class="mcc-field-error" role="alert"><?= esc($fieldError('player_id')) ?></p>
                    <?php endif; ?>
                </div>
                <?php foreach ($topFields as $field => $label): ?>
                    <div class="mcc-form-field <?= in_array($field, ['problem', 'outcome', 'method', 'safety_considerations'], true) ? 'mcc-player-session-plan-form-field-wide' : '' ?>">
                        <label for="session-plan-<?= esc($field) ?>">
                            <?= esc($label) ?>
                            <?php if (in_array($field, ['problem', 'outcome', 'method'], true)): ?><span aria-hidden="true">*</span><?php endif; ?>
                        </label>
                        <?php if ($field === 'coaching_focus'): ?>
                            <?php $selected = $selectedFocus(); ?>
                            <select
                                class="form-control btSelect"
                                id="session-plan-coaching-focus"
                                name="coaching_focus[]"
                                multiple
                                data-bt-select
                                data-bt-select-placeholder="Choose Coaching Focus"
                                data-bt-select-search-placeholder="Search Coaching Focus"
                            >
                                <?php foreach ($coachingFocus as $focus): ?>
                                    <option value="<?= esc($focus) ?>" <?= in_array($focus, $selected, true) ? 'selected' : '' ?>>
                                        <?= esc($focus) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php else: ?>
                            <textarea
                                class="form-control"
                                id="session-plan-<?= esc($field) ?>"
                                name="<?= esc($field) ?>"
                                rows="3"
                                maxlength="10000"
                                <?= in_array($field, ['problem', 'outcome', 'method'], true) ? 'required' : '' ?>
                            ><?= esc($value($field)) ?></textarea>
                        <?php endif; ?>
                        <?php if ($fieldError($field) !== ''): ?>
                            <p class="mcc-field-error" role="alert"><?= esc($fieldError($field)) ?></p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="mcc-player-session-plan-context-grid mcc-player-session-plan-form-context">
                <div><span class="mcc-eyebrow">Player ID</span><strong><?= (int) ($player['id'] ?? $player['player_id'] ?? 0) ?></strong></div>
                <div><span class="mcc-eyebrow">Branch</span><strong><?= esc((string) ($player['branch_name'] ?? 'Authorized branch')) ?></strong></div>
                <div><span class="mcc-eyebrow">Assigned Coach</span><strong><?= esc($assignedCoach) ?></strong></div>
            </div>
        </section>

        <?php foreach ($practiceSections as $prefix => $section): ?>
            <section class="mcc-player-session-plan-form-section mcc-player-session-practice-card" aria-labelledby="<?= esc($prefix) ?>-practice-heading">
                <div class="mcc-card-heading">
                    <div>
                        <span class="mcc-eyebrow">Practice section</span>
                        <h2 id="<?= esc($prefix) ?>-practice-heading"><?= esc($section['title']) ?></h2>
                        <p class="mcc-card-subtitle"><?= esc($section['duration']) ?></p>
                    </div>
                </div>
                <div class="mcc-player-session-plan-form-grid">
                    <?php foreach ($practiceFields as $suffix => $label): ?>
                        <?php $field = $prefix . '_' . $suffix; ?>
                        <div class="mcc-form-field">
                            <label for="session-plan-<?= esc($field) ?>"><?= esc($label) ?></label>
                            <?php if ($suffix === 'coaching_tools'): ?>
                                <?php $selected = $selectedTools($field); ?>
                                <select
                                    class="form-control btSelect"
                                    id="session-plan-<?= esc($field) ?>"
                                    name="<?= esc($field) ?>[]"
                                    multiple
                                    data-bt-select
                                    data-bt-select-placeholder="Choose coaching tools"
                                    data-bt-select-search-placeholder="Search coaching tools"
                                >
                                    <?php foreach ($coachingTools as $tool): ?>
                                        <option value="<?= esc($tool) ?>" <?= in_array($tool, $selected, true) ? 'selected' : '' ?>>
                                            <?= esc($tool) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php else: ?>
                                <textarea class="form-control" id="session-plan-<?= esc($field) ?>" name="<?= esc($field) ?>" rows="3" maxlength="10000"><?= esc($value($field)) ?></textarea>
                            <?php endif; ?>
                            <?php if ($fieldError($field) !== ''): ?>
                                <p class="mcc-field-error" role="alert"><?= esc($fieldError($field)) ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>

        <div class="mcc-form-actions mcc-player-session-plan-form-actions">
            <button class="mcc-button mcc-button-primary" type="submit">
                <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
                Save Session Plan
            </button>
            <a class="mcc-button mcc-button-secondary" href="<?= esc($backUrl) ?>">Cancel</a>
        </div>
    </form>
</div>

<?= view('staff_footer') ?>
