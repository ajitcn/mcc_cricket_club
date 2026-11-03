<?php

$plan = is_array($plan ?? null) ? $plan : [];
$canManage = (bool) ($can_manage ?? false);
$history = is_array($plan['history'] ?? null) ? $plan['history'] : [];
$topFields = [
    'problem' => 'Problem',
    'outcome' => 'Outcome',
    'method' => 'Method',
    'players' => 'Players',
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
$displayText = static function (mixed $value): string {
    $value = trim((string) $value);

    return $value === '' ? 'Not provided' : nl2br(esc($value));
};
$displayTools = static function (mixed $value) use ($coachingTools, $displayText): string {
    $decoded = json_decode((string) $value, true);

    if (! is_array($decoded)) {
        return $displayText($value);
    }

    $tools = array_values(array_filter(
        array_map('strval', $decoded),
        static fn (string $tool): bool => in_array($tool, $coachingTools, true),
    ));

    return $displayText(implode(', ', $tools));
};
$displayFocus = static function (mixed $value) use ($coachingFocus, $displayText): string {
    $decoded = json_decode((string) $value, true);

    if (! is_array($decoded)) {
        return $displayText($value);
    }

    $focus = array_values(array_filter(
        array_map('strval', $decoded),
        static fn (string $item): bool => in_array($item, $coachingFocus, true),
    ));

    return $displayText(implode(', ', $focus));
};
?>
<?= view('staff_header', [
    'title' => 'View Session Plan',
    'nav' => $nav,
    'message' => $message,
    'error' => $error,
    'breadcrumbTrail' => [
        ['label' => 'Players', 'url' => site_url('players')],
        ['label' => 'Session Plans', 'url' => site_url('player-session-plans')],
        ['label' => 'View Session Plan'],
    ],
    'pageActions' => '<div class="mcc-page-actions">'
        . ($canManage ? '<a class="mcc-button mcc-button-primary" href="' . esc(site_url('player-session-plans/' . (int) ($plan['id'] ?? 0) . '/edit')) . '">
            <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
            Edit Plan
        </a>' : '')
        . '
        <a class="mcc-button mcc-button-secondary" href="' . esc(site_url('player-session-plans')) . '">
            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
            Back to Session Plans
        </a>
    </div>',
]) ?>

<div class="mcc-player-session-plan-detail-page">
    <section class="mcc-card" aria-labelledby="session-plan-detail-heading">
        <div class="mcc-card-heading">
            <div>
                <span class="mcc-eyebrow">Player Session Plan</span>
                <h2 id="session-plan-detail-heading"><?= esc((string) ($plan['player_name'] ?? 'Player')) ?></h2>
                <p class="mcc-card-subtitle">
                    Player #<?= (int) ($plan['player_id'] ?? 0) ?> ·
                    <?= esc((string) ($plan['branch_name'] ?? 'Authorized branch')) ?> ·
                    <?= esc((string) ($plan['coach_name'] ?? 'Not available')) ?>
                </p>
            </div>
        </div>
        <div class="mcc-player-session-plan-meta">
            <span>Created <?= esc(mcc_user_datetime($plan['created_at'] ?? null)) ?></span>
            <span>Updated <?= esc(mcc_user_datetime($plan['updated_at'] ?? null)) ?></span>
        </div>
    </section>

    <section class="mcc-card" aria-labelledby="session-plan-context-heading">
        <div class="mcc-card-heading">
            <div>
                <span class="mcc-eyebrow">Session plan</span>
                <h2 id="session-plan-context-heading">Plan context</h2>
            </div>
        </div>
        <div class="mcc-player-session-plan-detail-grid">
            <?php foreach ($topFields as $field => $label): ?>
                <div class="mcc-player-session-plan-detail-field">
                    <dt><?= esc($label) ?></dt>
                    <dd><?= $field === 'coaching_focus'
                        ? $displayFocus($plan[$field] ?? '')
                        : $displayText($plan[$field] ?? '') ?></dd>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
        $legacyFocusFields = [
            'technical' => 'Historical Technical',
            'tactical' => 'Historical Tactical',
            'mental' => 'Historical Mental',
            'physical' => 'Historical Physical',
        ];
        $hasLegacyFocus = false;
        foreach ($legacyFocusFields as $legacyField => $legacyLabel) {
            if (trim((string) ($plan[$legacyField] ?? '')) !== '') {
                $hasLegacyFocus = true;
                break;
            }
        }
        ?>
        <?php if ($hasLegacyFocus): ?>
            <div class="mcc-player-session-plan-legacy-focus" aria-label="Historical coaching focus values">
                <?php foreach ($legacyFocusFields as $legacyField => $legacyLabel): ?>
                    <?php if (trim((string) ($plan[$legacyField] ?? '')) !== ''): ?>
                        <div class="mcc-player-session-plan-detail-field">
                            <dt><?= esc($legacyLabel) ?></dt>
                            <dd><?= $displayText($plan[$legacyField]) ?></dd>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <?php foreach ($practiceSections as $prefix => $section): ?>
        <section class="mcc-card" aria-labelledby="<?= esc($prefix) ?>-practice-detail-heading">
            <div class="mcc-card-heading">
                <div>
                    <span class="mcc-eyebrow">Practice section</span>
                    <h2 id="<?= esc($prefix) ?>-practice-detail-heading"><?= esc($section['title']) ?></h2>
                    <p class="mcc-card-subtitle"><?= esc($section['duration']) ?></p>
                </div>
            </div>
            <div class="mcc-player-session-plan-detail-grid">
                <?php foreach ($practiceFields as $suffix => $label): ?>
                    <?php $field = $prefix . '_' . $suffix; ?>
                    <div class="mcc-player-session-plan-detail-field">
                        <dt><?= esc($label) ?></dt>
                        <dd><?= $suffix === 'coaching_tools'
                            ? $displayTools($plan[$field] ?? '')
                            : $displayText($plan[$field] ?? '') ?></dd>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endforeach; ?>

    <section class="mcc-card" aria-labelledby="session-plan-history-heading">
        <div class="mcc-card-heading">
            <div>
                <span class="mcc-eyebrow">Audit-preserved history</span>
                <h2 id="session-plan-history-heading">Previous versions</h2>
            </div>
        </div>
        <?php if ($history === []): ?>
            <div class="mcc-empty-state">
                <i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i>
                <strong>No previous versions</strong>
                <span>Earlier versions will appear here after an edit.</span>
            </div>
        <?php else: ?>
            <div class="mcc-table-wrap mcc-table-responsive">
                <table class="mcc-table mcc-player-session-plan-history-table">
                    <caption class="visually-hidden">Previous Session Plan versions</caption>
                    <thead>
                        <tr>
                            <th scope="col">Problem</th>
                            <th scope="col">Outcome</th>
                            <th scope="col">Saved by</th>
                            <th scope="col">Saved at</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($history as $entry): ?>
                            <?php $snapshot = is_array($entry['snapshot_data'] ?? null) ? $entry['snapshot_data'] : []; ?>
                            <tr>
                                <td data-label="Problem"><?= $displayText($snapshot['problem'] ?? '') ?></td>
                                <td data-label="Outcome"><?= $displayText($snapshot['outcome'] ?? '') ?></td>
                                <td data-label="Saved by"><?= esc((string) ($entry['created_by_name'] ?? 'Not available')) ?></td>
                                <td data-label="Saved at"><?= esc(mcc_user_datetime($entry['created_at'] ?? null)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>

<?= view('staff_footer') ?>
