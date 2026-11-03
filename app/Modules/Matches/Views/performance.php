<?php

$result = is_array($result ?? null) ? $result : [];
$match = is_array($result['match'] ?? null) ? $result['match'] : [];
$players = is_array($result['players'] ?? null) ? $result['players'] : [];
$readOnly = (bool) ($result['read_only'] ?? false);
$canEdit = (bool) ($result['can_edit'] ?? false);
$oldPlayers = old('players');
$oldPlayers = is_array($oldPlayers) ? $oldPlayers : [];
$feedback = session()->getFlashdata('performance_feedback');
$feedback = is_array($feedback) ? $feedback : [];
$fieldErrors = session()->getFlashdata('field_errors');
$fieldErrors = is_array($fieldErrors) ? $fieldErrors : [];
$status = strtolower((string) ($match['status'] ?? 'unknown'));
$statusTone = match ($status) {
    'completed' => 'success',
    'cancelled' => 'danger',
    default => 'info',
};

$value = static function (array $values, string $field): string {
    return esc((string) ($values[$field] ?? ''));
};

$metricValue = static function (array $metrics, string $field): string {
    $value = $metrics[$field] ?? null;

    return $value === null ? 'Not available' : (string) $value;
};
$displayLocal = static function (string $value): string {
    $parsed = DateTimeImmutable::createFromFormat('!d-m-Y H:i', $value);

    return $parsed === false ? $value : $parsed->format('d-m-Y h:i A');
};
$history = is_array($result['audit_history'] ?? null) ? $result['audit_history'] : [];
$pageActions = '<div class="mcc-detail-actions">'
    . '<a class="mcc-button mcc-button-secondary" href="' . site_url('matches/' . (int) ($match['id'] ?? 0)) . '">'
    . '<i class="fa-solid fa-arrow-left" aria-hidden="true"></i><span>Back to match</span></a>'
    . '</div>';
?>

<?= view('staff_header', ['title' => 'Match Player Performance', 'pageActions' => $pageActions]) ?>

<main class="container py-4 mcc-match-performance mcc-match-detail">
    <section class="mcc-card mcc-detail-hero" aria-labelledby="performance-match-heading">
        <div>
            <span class="mcc-eyebrow">Match record</span>
            <h2 id="performance-match-heading"><?= esc($match['opponent'] ?? 'Match') ?></h2>
            <div class="mcc-detail-badges" aria-label="Match status">
                <span class="mcc-badge mcc-badge-<?= esc($statusTone) ?>">
                    <?= esc(ucfirst($status)) ?>
                </span>
                <?php if ($readOnly): ?>
                    <span class="mcc-badge mcc-badge-warning">Read-only completed Match</span>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <?php if ($readOnly && !$canEdit): ?>
        <div class="mcc-alert mcc-alert-info" role="status">
            This completed Match is read-only. Corrections require the existing performance update permission and a reason.
        </div>
    <?php elseif ($readOnly): ?>
        <div class="mcc-alert mcc-alert-info" role="status">
            This completed Match is in correction mode. Enter a reason for each changed Player record.
        </div>
    <?php elseif (!$canEdit): ?>
        <div class="mcc-alert mcc-alert-info" role="status">
            You have read-only access to this Match performance.
        </div>
    <?php endif; ?>

    <section class="mcc-card mcc-detail-card" aria-labelledby="performance-summary-heading">
        <div class="mcc-card-heading">
            <div>
                <span class="mcc-eyebrow">Match summary</span>
                <h2 id="performance-summary-heading">Schedule and assignment</h2>
            </div>
        </div>
        <dl class="mcc-detail-list">
            <dt>Branch</dt>
            <dd><?= esc($match['branch_name'] ?? 'Not available') ?></dd>
            <dt>Ground</dt>
            <dd><?= esc($match['ground_name'] ?? 'Not assigned') ?></dd>
            <dt>Start</dt>
            <dd><?= esc($displayLocal((string) ($match['starts_at_local'] ?? 'Not available'))) ?></dd>
            <dt>End</dt>
            <dd><?= esc($displayLocal((string) ($match['ends_at_local'] ?? 'Not available'))) ?></dd>
        </dl>
    </section>

    <section id="import-performance" class="mcc-card mcc-detail-card mcc-performance-import-card" aria-labelledby="performance-import-heading">
        <div class="mcc-card-heading mcc-performance-import-heading">
            <div>
                <span class="mcc-eyebrow">Bulk entry</span>
                <h2 id="performance-import-heading">CSV performance sheet</h2>
            </div>
            <a class="mcc-button mcc-button-secondary" href="<?= site_url('matches/' . (int) ($match['id'] ?? 0) . '/performance/template') ?>">
                <i class="fa-solid fa-download" aria-hidden="true"></i>
                <span>Download CSV template</span>
            </a>
        </div>
        <?php if ($canEdit): ?>
            <form method="post" action="<?= site_url('matches/' . (int) ($match['id'] ?? 0) . '/performance/import') ?>" enctype="multipart/form-data" class="mcc-performance-import-form">
                <?= csrf_field() ?>
                <div class="mcc-performance-import-field">
                    <label for="performance-csv">Completed performance CSV</label>
                    <input class="form-control" id="performance-csv" name="performance_csv" type="file" accept=".csv,text/csv" required>
                    <small class="mcc-form-help">Use the downloaded template and keep the player_id column unchanged.</small>
                </div>
                <div class="mcc-form-actions mcc-performance-import-actions">
                    <button class="mcc-button mcc-button-primary" type="submit">
                        <i class="fa-solid fa-upload" aria-hidden="true"></i>
                        <span>Import performance</span>
                    </button>
                </div>
            </form>
        <?php else: ?>
            <p class="mcc-muted">You have read-only access to performance records.</p>
        <?php endif; ?>
    </section>

    <?php if ($players === []): ?>
        <section class="mcc-card mcc-empty-state" aria-labelledby="performance-empty-heading">
            <h2 id="performance-empty-heading">No selected Players</h2>
            <p>No squad or Playing XI Players are available for this Match.</p>
        </section>
    <?php endif; ?>

    <section class="mcc-card mcc-detail-card" aria-labelledby="performance-players-heading">
        <div class="mcc-card-heading">
            <div>
                <span class="mcc-eyebrow">Selected Players</span>
                <h2 id="performance-players-heading">Player performance</h2>
            </div>
            <span class="mcc-muted"><?= count($players) ?> selected</span>
        </div>
        <div class="mcc-detail-grid mcc-performance-accordion-list">
        <?php foreach ($players as $index => $player): ?>
            <?php
            $playerId = (int) ($player['player_id'] ?? 0);
            $existing = is_array($player['performance'] ?? null) ? $player['performance'] : [];
            $values = is_array($oldPlayers[$playerId] ?? null) ? $oldPlayers[$playerId] : $existing;
            $metrics = is_array($player['metrics'] ?? null) ? $player['metrics'] : [];
            ?>
            <?php $panelId = 'performance-player-panel-' . $playerId; ?>
            <?php $hasPerformance = $existing !== []; ?>
            <?php if ($canEdit): ?>
                <form method="post" action="<?= site_url('matches/' . (int) ($match['id'] ?? 0) . '/performance/' . $playerId) ?>">
                    <?= csrf_field() ?>
            <?php endif; ?>
            <article class="mcc-card mcc-match-performance-card mcc-performance-accordion-item">
                <button
                    type="button"
                    class="mcc-performance-accordion-trigger mcc-card-heading"
                    aria-expanded="false"
                    aria-controls="<?= esc($panelId) ?>"
                >
                    <span>
                        <span class="mcc-eyebrow">
                            <?= esc(ucwords(str_replace('_', ' ', (string) ($player['selection_type'] ?? 'selected')))) ?>
                        </span>
                        <span class="mcc-performance-player-name">
                            <?= esc($player['full_name'] ?? 'Player') ?>
                        </span>
                        <span class="mcc-performance-player-id">Player #<?= $playerId ?></span>
                    </span>
                    <span class="mcc-performance-accordion-summary">
                        <span class="mcc-badge mcc-badge-info"><?= $hasPerformance ? 'Recorded' : 'Not recorded' ?></span>
                        <span class="mcc-performance-accordion-icon" aria-hidden="true">+</span>
                    </span>
                </button>

                <div id="<?= esc($panelId) ?>" class="mcc-performance-accordion-panel" hidden>
                    <?php if ((int) ($feedback['player_id'] ?? 0) === $playerId): ?>
                        <div class="mcc-alert mcc-alert-<?= ($feedback['type'] ?? '') === 'success' ? 'success' : 'danger' ?>" role="status">
                            <?= esc((string) ($feedback['message'] ?? '')) ?>
                        </div>
                    <?php endif; ?>
                    <div class="mcc-detail-grid">
                    <fieldset <?= $canEdit ? '' : 'disabled' ?>>
                        <legend>Batting</legend>
                        <label>
                            Batting status
                            <select name="players[<?= $playerId ?>][batting_status]" required>
                                <option value="">Select status</option>
                                <?php foreach (['batted', 'did_not_bat', 'retired_hurt', 'absent'] as $option): ?>
                                    <option value="<?= $option ?>" <?= (string) ($values['batting_status'] ?? '') === $option ? 'selected' : '' ?>>
                                        <?= esc(ucwords(str_replace('_', ' ', $option))) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <?php foreach (['runs' => 'Runs', 'balls_faced' => 'Balls faced', 'fours' => 'Fours', 'sixes' => 'Sixes', 'batting_position' => 'Batting position'] as $field => $label): ?>
                            <label>
                                <?= esc($label) ?>
                                <input type="number" min="0" name="players[<?= $playerId ?>][<?= $field ?>]" value="<?= $value($values, $field) ?>">
                            </label>
                        <?php endforeach; ?>
                        <label>
                            Dismissal details
                            <input name="players[<?= $playerId ?>][dismissal_type]" value="<?= $value($values, 'dismissal_type') ?>">
                        </label>
                        <div class="mcc-performance-metrics" aria-label="Batting calculations">
                            <span class="mcc-badge mcc-badge-light">Average: <?= esc($metricValue($metrics, 'batting_average')) ?></span>
                            <span class="mcc-badge mcc-badge-light">Strike rate: <?= esc($metricValue($metrics, 'strike_rate')) ?></span>
                            <span class="mcc-badge mcc-badge-light">Boundary runs: <?= esc($metricValue($metrics, 'boundary_runs')) ?></span>
                        </div>
                    </fieldset>

                    <fieldset <?= $canEdit ? '' : 'disabled' ?>>
                        <legend>Bowling</legend>
                        <label>
                            Bowling status
                            <select name="players[<?= $playerId ?>][bowling_status]" required>
                                <option value="">Select status</option>
                                <?php foreach (['bowled', 'did_not_bowl', 'not_available'] as $option): ?>
                                    <option value="<?= $option ?>" <?= (string) ($values['bowling_status'] ?? '') === $option ? 'selected' : '' ?>>
                                        <?= esc(ucwords(str_replace('_', ' ', $option))) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label for="legal-balls-<?= $playerId ?>">
                            <span class="mcc-performance-help-wrap">
                                <span>Legal balls bowled</span>
                                <button
                                    type="button"
                                    class="mcc-performance-help-trigger"
                                    data-mcc-help-toggle
                                    aria-label="Help for legal balls bowled"
                                    aria-controls="legal-balls-help-<?= $playerId ?>"
                                    aria-expanded="false"
                                    aria-haspopup="tooltip"
                                >?</button>
                                <span id="legal-balls-help-<?= $playerId ?>" class="mcc-performance-help-tooltip" role="tooltip" hidden>
                                    Enter the number of legal deliveries. Wides and no-balls are not counted. Overs are calculated automatically.<br>
                                    Example: 18 legal balls = 3 overs.
                                </span>
                            </span>
                            <span class="mcc-performance-unit-input">
                                <input id="legal-balls-<?= $playerId ?>" type="number" min="0" step="1" name="players[<?= $playerId ?>][legal_balls]" value="<?= $value($values, 'legal_balls') ?>" aria-describedby="legal-balls-help-<?= $playerId ?>">
                                <span aria-hidden="true">balls</span>
                            </span>
                        </label>
                        <?php foreach (['maidens' => 'Maidens', 'runs_conceded' => 'Runs conceded', 'wickets' => 'Wickets', 'wides' => 'Wides', 'no_balls' => 'No-balls', 'byes' => 'Byes', 'leg_byes' => 'Leg-byes'] as $field => $label): ?>
                            <label>
                                <?= esc($label) ?>
                                <input type="number" min="0" name="players[<?= $playerId ?>][<?= $field ?>]" value="<?= $value($values, $field) ?>">
                            </label>
                        <?php endforeach; ?>
                        <div class="mcc-performance-metrics" aria-label="Bowling calculations">
                            <span class="mcc-badge mcc-badge-light">Overs bowled: <?= esc($metricValue($metrics, 'overs')) ?></span>
                            <span class="mcc-badge mcc-badge-light">Economy: <?= esc($metricValue($metrics, 'economy')) ?></span>
                            <span class="mcc-badge mcc-badge-light">Average: <?= esc($metricValue($metrics, 'bowling_average')) ?></span>
                            <span class="mcc-badge mcc-badge-light">Strike rate: <?= esc($metricValue($metrics, 'bowling_strike_rate')) ?></span>
                        </div>
                    </fieldset>

                    <section class="mcc-performance-fielding" aria-labelledby="fielding-player-<?= $playerId ?>">
                    <div class="mcc-section-heading">
                        <h3 id="fielding-player-<?= $playerId ?>">Fielding</h3>
                        <?php if (($values['fielding_status'] ?? '') === ''): ?>
                            <span class="mcc-badge mcc-badge-light">Not recorded</span>
                        <?php else: ?>
                            <span class="mcc-badge mcc-badge-info">
                                <?= esc(ucwords(str_replace('_', ' ', (string) $values['fielding_status']))) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <fieldset <?= $canEdit ? '' : 'disabled' ?>>
                        <label>
                            Fielding status
                            <select name="players[<?= $playerId ?>][fielding_status]" required>
                                <option value="">Select status</option>
                                <?php foreach (['fielded', 'did_not_field', 'not_available'] as $option): ?>
                                    <option value="<?= $option ?>" <?= (string) ($values['fielding_status'] ?? '') === $option ? 'selected' : '' ?>>
                                        <?= esc(ucwords(str_replace('_', ' ', $option))) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <?php foreach (['catches' => 'Catches', 'stumpings' => 'Stumpings', 'run_outs_completed' => 'Run-outs completed', 'run_out_assists' => 'Run-out assists', 'direct_hit_run_outs' => 'Direct-hit run-outs', 'dropped_catches' => 'Dropped catches'] as $field => $label): ?>
                            <label>
                                <?= esc($label) ?>
                                <input type="number" min="0" name="players[<?= $playerId ?>][<?= $field ?>]" value="<?= $value($values, $field) ?>">
                            </label>
                        <?php endforeach; ?>
                        <label>
                            Fielding position
                            <input name="players[<?= $playerId ?>][fielding_position]" value="<?= $value($values, 'fielding_position') ?>">
                        </label>
                        <label>
                            Notes
                            <textarea name="players[<?= $playerId ?>][fielding_notes]" rows="2"><?= $value($values, 'fielding_notes') ?></textarea>
                        </label>
                        <div class="mcc-performance-metrics" aria-label="Fielding calculations">
                            <span class="mcc-badge mcc-badge-light">Total dismissals: <?= esc($metricValue($metrics, 'fielding_dismissals')) ?></span>
                            <span class="mcc-badge mcc-badge-light">Total contributions: <?= esc($metricValue($metrics, 'fielding_contributions')) ?></span>
                            <span class="mcc-badge mcc-badge-light">Dropped catches: <?= esc($metricValue($metrics, 'dropped_catches')) ?></span>
                        </div>
                    </fieldset>
                    </section>
                    </div>

                    <?php if ($hasPerformance && $canEdit): ?>
                        <input
                            type="hidden"
                            name="players[<?= $playerId ?>][version]"
                            value="<?= (int) ($existing['version'] ?? 1) ?>"
                        >
                        <label>
                            Correction reason
                            <textarea name="players[<?= $playerId ?>][correction_reason]" rows="2" maxlength="255" required aria-describedby="correction-reason-help-<?= $playerId ?>"><?= $value($values, 'correction_reason') ?></textarea>
                            <small id="correction-reason-help-<?= $playerId ?>" class="mcc-performance-help">Explain what was corrected and why.</small>
                            <?php if ((int) ($feedback['player_id'] ?? 0) === $playerId && ! empty($fieldErrors['correction_reason'])): ?>
                                <span class="mcc-field-error" role="alert"><?= esc($fieldErrors['correction_reason']) ?></span>
                            <?php endif; ?>
                        </label>
                    <?php endif; ?>
                    <?php if ($canEdit): ?>
                        <div class="mcc-form-actions">
                            <a class="mcc-button mcc-button-secondary" href="<?= site_url('matches/' . (int) ($match['id'] ?? 0)) ?>">Cancel</a>
                            <button class="mcc-button mcc-button-primary" type="submit">Save Player performance</button>
                        </div>
                    <?php endif; ?>
                </div>
            </article>
            <?php if ($canEdit): ?>
                </form>
            <?php endif; ?>
        <?php endforeach; ?>
        </div>
    </section>

    <section class="mcc-card mcc-detail-card" aria-labelledby="performance-history-heading">
        <div class="mcc-card-heading">
            <div>
                <span class="mcc-eyebrow">Audit trail</span>
                <h2 id="performance-history-heading">Performance history</h2>
            </div>
        </div>
        <?php if ($history === []): ?>
            <p class="mcc-empty-state">No performance history is available.</p>
        <?php else: ?>
            <div class="mcc-table-wrap">
                <table class="mcc-table">
                    <caption class="visually-hidden">Match Player Performance history</caption>
                    <thead>
                        <tr>
                            <th scope="col">Event</th>
                            <th scope="col">Actor</th>
                            <th scope="col">Timestamp</th>
                            <th scope="col">Reason</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($history as $event): ?>
                            <tr>
                                <th scope="row"><?= esc(ucwords(str_replace('_', ' ', (string) ($event['action'] ?? 'Event')))) ?></th>
                                <td><?= esc($event['actor_name'] ?? ('User #' . (int) ($event['actor_user_id'] ?? 0))) ?></td>
                                <td><?= esc(mcc_user_datetime($event['created_at'] ?? null)) ?></td>
                                <td><?= esc($event['reason'] ?? 'Not provided') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</main>

<?= view('staff_footer') ?>
