<?php

$match = is_array($match ?? null) ? $match : [];
$players = is_array($match['players'] ?? null) ? $match['players'] : [];
$performancePlayers = is_array($match['performance_players'] ?? null) ? $match['performance_players'] : [];
$history = is_array($match['audit_history'] ?? null) ? $match['audit_history'] : [];
$canUpdate = (bool) ($match['can_update'] ?? false);
$status = strtolower((string) ($match['status'] ?? 'unknown'));
$statusTone = match ($status) {
    'completed' => 'success',
    'cancelled' => 'danger',
    default => 'info',
};
$resultValue = strtolower((string) ($match['result'] ?? ''));
$resultTone = match ($resultValue) {
    'won' => 'success',
    'lost' => 'danger',
    'draw' => 'warning',
    'no_result' => 'neutral',
    default => 'info',
};
$startsLocal = (string) ($match['starts_at_local'] ?? '');
$endsLocal = (string) ($match['ends_at_local'] ?? '');
$datePart = static function (string $value): string {
    return preg_match('/^(\d{2}-\d{2}-\d{4})/', $value, $parts) ? $parts[1] : 'Not available';
};
$timePart = static function (string $value): string {
    if (! preg_match('/(?:T| )(\d{2}:\d{2})/', $value, $parts)) {
        return 'Not available';
    }

    $parsed = DateTimeImmutable::createFromFormat('!H:i', $parts[1]);

    return $parsed === false ? $parts[1] : $parsed->format('h:i A');
};
$displayLocal = static function (string $value): string {
    $parsed = DateTimeImmutable::createFromFormat('!d-m-Y H:i', $value);

    return $parsed === false ? $value : $parsed->format('d-m-Y h:i A');
};
$metadataLabel = static fn (string $key): string => ucwords(str_replace(['_', '-'], ' ', $key));
$renderMetadata = static function (mixed $value, ?string $key = null) use (&$renderMetadata, $metadataLabel): string {
    $sensitive = '/(?:password|token|secret|credential|authorization|document|file[_-]?contents?|storage[_-]?path)/i';
    if ($key !== null && preg_match($sensitive, $key)) {
        return '';
    }
    if (is_array($value)) {
        if ($value === []) {
            return '<span class="mcc-muted">Not available</span>';
        }
        $html = '<dl class="mcc-match-metadata-list">';
        foreach ($value as $childKey => $childValue) {
            $childHtml = $renderMetadata($childValue, (string) $childKey);
            if ($childHtml === '') {
                continue;
            }
            $html .= '<div><dt>' . esc($metadataLabel((string) $childKey)) . '</dt><dd>' . $childHtml . '</dd></div>';
        }
        return $html . '</dl>';
    }
    if ($value === null || $value === '') {
        return '<span class="mcc-muted">Not available</span>';
    }

    return esc(is_bool($value) ? ($value ? 'Yes' : 'No') : (string) $value);
};
$pageActions = '<div class="mcc-detail-actions">'
    . '<a class="mcc-button mcc-button-secondary" href="' . site_url('matches') . '">'
    . '<i class="fa-solid fa-arrow-left" aria-hidden="true"></i><span class="mcc-button-label">Back to matches</span></a>';
if ($canUpdate) {
    $pageActions .= '<a class="mcc-button mcc-button-primary" href="' . site_url('matches/' . (int) $match['id'] . '/edit') . '">'
        . '<i class="fa-solid fa-pen-to-square" aria-hidden="true"></i><span class="mcc-button-label">Edit match</span></a>';
    if ($status === 'scheduled') {
        $pageActions .= '<form method="post" action="' . site_url('matches/' . (int) $match['id'] . '/complete') . '" class="mcc-inline-form" onsubmit="return confirm(\'Complete this Match?\');">'
            . csrf_field()
            . '<button class="mcc-button mcc-button-success" type="submit">'
            . '<i class="fa-solid fa-circle-check" aria-hidden="true"></i><span class="mcc-button-label">Complete match</span></button></form>';
    }
}
$pageActions .= '</div>';
?>
<?= view('staff_header', ['title' => 'Match details', 'pageActions' => $pageActions]) ?>

<main class="container py-4 mcc-match-detail">
    <section class="mcc-card mcc-detail-hero mcc-match-detail-hero" aria-labelledby="match-heading">
        <div>
            <span class="mcc-eyebrow">Match record</span>
                <h2 id="match-heading">
                    <span><?= esc($match['your_team'] ?? 'Your team') ?></span>
                    <span class="mcc-match-detail-vs">V/S</span>
                    <span><?= esc($match['opponent_name'] ?? $match['opponent'] ?? 'Opponent unavailable') ?></span>
                </h2>
            <div class="mcc-muted">Match #<?= (int) $match['id'] ?></div>
            <div class="mcc-detail-badges" aria-label="Match status and branch">
                <span class="mcc-badge mcc-badge-<?= esc($statusTone) ?>"><?= esc(ucfirst($status)) ?></span>
                <span class="mcc-badge mcc-badge-info"><?= esc($match['branch_name'] ?? 'Branch unavailable') ?></span>
                <?php if (! empty($match['tournament_name'])): ?>
                    <span class="mcc-badge mcc-badge-neutral">
                        <i class="fa-solid fa-trophy" aria-hidden="true"></i>
                        <?= esc($match['tournament_name']) ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>
        <div class="mcc-match-detail-type">
            <span class="mcc-eyebrow">Match type</span>
            <strong><?= esc(ucfirst((string) ($match['match_type'] ?? 'Not available'))) ?></strong>
            <span class="mcc-badge <?= ($match['match_category'] ?? 'regular') === 'tournament' ? 'mcc-badge-info' : 'mcc-badge-neutral' ?>">
                <?= esc(ucfirst((string) ($match['match_category'] ?? 'regular'))) ?>
            </span>
        </div>
    </section>

    <div class="mcc-detail-grid">
        <section class="mcc-card mcc-detail-card" aria-labelledby="match-information-heading">
            <div class="mcc-card-heading"><div><span class="mcc-eyebrow">Schedule and assignment</span><h2 id="match-information-heading">Match information</h2></div></div>
            <dl class="mcc-detail-list">
                <dt>Start date</dt><dd><?= esc($datePart($startsLocal)) ?></dd>
                <dt>Start time</dt><dd><?= esc($timePart($startsLocal)) ?></dd>
                <dt>End date</dt><dd><?= esc($datePart($endsLocal)) ?></dd>
                <dt>End time</dt><dd><?= esc($timePart($endsLocal)) ?></dd>
                <dt>Branch</dt><dd><?= esc($match['branch_name'] ?? 'Unavailable') ?></dd>
                <dt>Your team</dt><dd><?= esc($match['your_team'] ?? 'Not captured') ?></dd>
                <dt>Opponent</dt><dd><?= esc($match['opponent_name'] ?? $match['opponent'] ?? 'Unavailable') ?></dd>
                <dt>Ground</dt><dd><?= esc($match['ground_name'] ?? 'Unavailable') ?></dd>
                <dt>Coach</dt><dd><?= esc($match['coach_name'] ?? 'Unavailable') ?></dd>
                <dt>Program</dt><dd><?= esc($match['program_name'] ?? 'Unavailable') ?></dd>
                <dt>Batch</dt><dd><?= esc($match['batch_name'] ?? 'Unavailable') ?></dd>
                <dt>Tournament</dt><dd><?= esc($match['tournament_name'] ?? 'Unavailable') ?></dd>
                <dt>Match number</dt><dd><?= ($match['match_number'] ?? null) !== null ? '#' . (int) $match['match_number'] : 'Not assigned' ?></dd>
                <dt>Fixture</dt><dd><?= esc($match['fixture_details'] ?? 'Unavailable') ?></dd>
                <dt>Result</dt>
                <dd>
                    <?php if ($resultValue !== ''): ?>
                        <span class="mcc-badge mcc-badge-<?= esc($resultTone) ?>">
                            <?= esc(ucfirst(str_replace('_', ' ', $resultValue))) ?>
                        </span>
                    <?php else: ?>
                        <span class="mcc-muted">Not recorded</span>
                    <?php endif; ?>
                </dd>
            </dl>
        </section>

        <section class="mcc-card mcc-detail-card" aria-labelledby="match-result-heading">
            <div class="mcc-card-heading"><div><span class="mcc-eyebrow">Outcome</span><h2 id="match-result-heading">Result and notes</h2></div></div>
            <dl class="mcc-detail-list">
                <dt>Score summary</dt>
                <dd>
                    <?php if (($match['score_summary'] ?? '') !== ''): ?>
                        <span class="mcc-badge mcc-badge-neutral">Score: <?= esc((string) $match['score_summary']) ?></span>
                    <?php else: ?>
                        <span class="mcc-muted">Not recorded</span>
                    <?php endif; ?>
                </dd>
                <dt>Coach remarks</dt><dd><?= esc($match['coach_remarks'] ?? 'No remarks recorded') ?></dd>
            </dl>
        </section>
    </div>

    <section class="mcc-card mcc-detail-card" aria-labelledby="match-players-heading">
        <div class="mcc-card-heading"><div><span class="mcc-eyebrow">Selected Players</span><h2 id="match-players-heading">Squad and Playing XI</h2></div><span class="mcc-muted"><?= count($players) ?> selected</span></div>
        <?php if ($players === []): ?>
            <p class="mcc-empty-state">No squad has been selected for this Match.</p>
        <?php else: ?>
            <div class="mcc-table-wrap">
                <table class="mcc-table mcc-match-players-table">
                    <caption class="visually-hidden">Match Players</caption>
                    <thead><tr><th scope="col">Player</th><th scope="col">Selection</th></tr></thead>
                    <tbody>
                        <?php foreach ($players as $player): ?>
                            <tr>
                                <th scope="row" data-label="Player"><?= esc($player['full_name'] ?? 'Unavailable') ?></th>
                                <td data-label="Selection"><span class="mcc-badge mcc-badge-<?= ($player['selection_type'] ?? '') === 'playing_xi' ? 'success' : 'info' ?>"><?= esc(($player['selection_type'] ?? '') === 'playing_xi' ? 'Playing XI' : 'Squad') ?></span></td>
                            </tr>
                        <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        <?php endif ?>
    </section>

    <section class="mcc-card mcc-detail-card" aria-labelledby="match-performance-heading">
        <div class="mcc-card-heading">
            <div>
                <span class="mcc-eyebrow">Player records</span>
                <h2 id="match-performance-heading">Match Player Performance</h2>
            </div>
            <a class="mcc-button mcc-button-secondary" href="<?= site_url('matches/' . (int) $match['id'] . '/performance') ?>">
                <i class="fa-solid fa-chart-line" aria-hidden="true"></i>
                <span>Open performance</span>
            </a>
        </div>
        <?php if ($performancePlayers === []): ?>
            <p class="mcc-muted">No Player performance has been recorded.</p>
        <?php else: ?>
            <div class="mcc-table-wrap">
                <table class="mcc-table mcc-match-performance-summary-table">
                    <caption class="visually-hidden">Match Player Performance Players</caption>
                    <thead><tr><th scope="col">Player</th><th scope="col">Innings</th><th scope="col">Team side</th></tr></thead>
                    <tbody>
                        <?php foreach ($performancePlayers as $performance): ?>
                            <tr>
                                <th scope="row" data-label="Player"><?= esc($performance['full_name'] ?? 'Unavailable') ?></th>
                                <td data-label="Innings"><?= (int) ($performance['innings_number'] ?? 0) ?></td>
                                <td data-label="Team side"><?= esc($performance['team_side'] ?? 'Unavailable') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="mcc-card mcc-detail-card" aria-labelledby="match-history-heading">
        <div class="mcc-card-heading"><div><span class="mcc-eyebrow">Audit trail</span><h2 id="match-history-heading">Match history</h2></div></div>
        <?php if ($history === []): ?>
            <p class="mcc-empty-state">No Match audit events are available.</p>
        <?php else: ?>
            <div class="mcc-table-wrap">
                <table class="mcc-table mcc-match-history-table">
                    <caption class="visually-hidden">Match audit history</caption>
                    <thead><tr><th scope="col">Action</th><th scope="col">Actor</th><th scope="col">Timestamp</th><th scope="col">Reason</th><th scope="col">Changes</th></tr></thead>
                    <tbody>
                        <?php foreach ($history as $event): ?>
                            <tr>
                                <th scope="row" data-label="Action"><?= esc(ucwords(str_replace('_', ' ', (string) ($event['action'] ?? 'Event')))) ?></th>
                                <td data-label="Actor"><?= esc($event['actor_name'] ?? 'Unavailable') ?></td>
                                <td data-label="Timestamp"><?= esc(mcc_user_datetime($event['created_at'] ?? null)) ?></td>
                                <td data-label="Reason"><?= esc($event['reason'] ?? 'Not provided') ?></td>
                                <td data-label="Changes" class="mcc-match-history-changes">
                                    <div><strong>Previous values</strong><?= $renderMetadata($event['before_metadata'] ?? []) ?></div>
                                    <div><strong>New values</strong><?= $renderMetadata($event['after_metadata'] ?? []) ?></div>
                                </td>
                            </tr>
                        <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        <?php endif ?>
    </section>
</main>

<?= view('staff_footer') ?>
