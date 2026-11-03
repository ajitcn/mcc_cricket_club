<?php

$pageActions = '<div class="mcc-page-actions">'
    . '<a class="mcc-button mcc-button-secondary" href="' . site_url('tournaments') . '">'
    . '<i class="fa-solid fa-arrow-left" aria-hidden="true"></i>'
    . '<span class="mcc-button-label">Back to Tournaments</span></a>';
if (($can_update ?? false) === true) {
    $pageActions .= '<a class="mcc-button mcc-button-primary" href="'
        . site_url('tournaments/' . (int) $tournament['id'] . '/edit')
        . '"><i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>'
        . '<span class="mcc-button-label">Edit tournament</span></a>';
}
$pageActions .= '<a class="mcc-button mcc-button-secondary" href="'
    . site_url('tournaments/' . (int) $tournament['id'] . '/fixtures')
    . '"><i class="fa-solid fa-calendar-days" aria-hidden="true"></i>'
    . '<span class="mcc-button-label">View fixtures</span></a>';
$pageActions .= '</div>';

$sensitiveKey = static function (string $key): bool {
    return preg_match('/password|token|secret|credential|authorization|document.?contents?/i', $key) === 1;
};
$labelFor = static function (string $key): string {
    return ucfirst(str_replace(['_', '-'], ' ', $key));
};
$renderMetadata = null;
$renderMetadata = static function (mixed $value) use (&$renderMetadata, $sensitiveKey, $labelFor): string {
    if (! is_array($value)) {
        return esc($value === null || $value === '' ? '—' : (string) $value);
    }

    $items = [];
    foreach ($value as $key => $item) {
        $keyText = (string) $key;
        if ($sensitiveKey($keyText)) {
            continue;
        }
        $items[] = '<div class="mcc-tournament-history-value">'
            . '<dt>' . esc($labelFor($keyText)) . '</dt>'
            . '<dd>' . $renderMetadata($item) . '</dd>'
            . '</div>';
    }

    return $items === []
        ? '<span class="mcc-muted">No safe values available</span>'
        : '<dl class="mcc-tournament-history-values">' . implode('', $items) . '</dl>';
};
$participants = [];
foreach ((array) ($tournament['participants'] ?? []) as $participant) {
    if (($participant['participant_type'] ?? '') !== 'team') {
        $participants[] = $participant;
        continue;
    }

    foreach (preg_split('/\R/', (string) ($participant['team_name'] ?? '')) ?: [] as $teamName) {
        $teamName = trim($teamName);
        if ($teamName !== '') {
            $participant['team_name'] = $teamName;
            $participants[] = $participant;
        }
    }
}
$teamCount = count(array_filter(
    $participants,
    static fn (array $participant): bool => ($participant['participant_type'] ?? '') === 'team'
));
$playerCount = count(array_filter(
    $participants,
    static fn (array $participant): bool => ($participant['participant_type'] ?? '') === 'player'
));
$fixtureCount = count((array) ($tournament['fixtures'] ?? []));
?>
<?= view('staff_header', ['title' => $title, 'pageActions' => $pageActions]) ?>

<div class="mcc-tournament-detail">
    <header class="mcc-page-heading mcc-tournament-detail-header">
        <div>
            <span class="mcc-eyebrow">Tournament</span>
            <h1><?= esc($tournament['name']) ?></h1>
            <p class="mcc-page-subtitle">Review tournament details, participants, fixtures, and history.</p>
        </div>
        <?php $status = strtolower((string) ($tournament['status'] ?? 'planned')); ?>
        <span class="mcc-badge <?= in_array($status, ['completed', 'active'], true) ? 'mcc-badge-success' : ($status === 'cancelled' ? 'mcc-badge-danger' : 'mcc-badge-info') ?>">
            <?= esc(ucfirst((string) $tournament['status'])) ?>
        </span>
    </header>

    <section class="mcc-card mcc-tournament-detail-card mcc-tournament-hero" aria-labelledby="tournament-information-heading">
        <div class="mcc-card-heading">
            <h2 id="tournament-information-heading">Tournament information</h2>
        </div>
        <dl class="mcc-detail-list mcc-tournament-detail-list">
            <div><dt>Name</dt><dd><?= esc($tournament['name']) ?></dd></div>
            <div><dt>Type</dt><dd><?= esc(ucfirst((string) $tournament['tournament_type'])) ?></dd></div>
            <div><dt>Status</dt><dd><?= esc(ucfirst((string) $tournament['status'])) ?></dd></div>
            <div><dt>Start date</dt><dd><?= esc(mcc_user_date($tournament['starts_on'] ?? null)) ?></dd></div>
            <div><dt>End date</dt><dd><?= esc(mcc_user_date($tournament['ends_on'] ?? null)) ?></dd></div>
            <div><dt>Branch</dt><dd><?= esc($tournament['branch_name'] ?? 'Not available') ?></dd></div>
            <div><dt>Ground</dt><dd><?= esc($tournament['ground_name'] ?? 'Not assigned') ?></dd></div>
            <div class="mcc-tournament-detail-description"><dt>Description</dt><dd><?= nl2br(esc($tournament['description'] ?? 'No description available.')) ?></dd></div>
        </dl>
    </section>

    <section class="mcc-card mcc-tournament-detail-card mcc-tournament-overview-card" aria-labelledby="tournament-overview-heading">
        <div class="mcc-card-heading">
            <div>
                <span class="mcc-eyebrow">At a glance</span>
                <h2 id="tournament-overview-heading">Tournament overview</h2>
            </div>
        </div>
        <div class="mcc-tournament-overview-grid">
            <div><span>Participants</span><strong><?= $playerCount + $teamCount ?></strong><small><?= $playerCount ?> Players · <?= $teamCount ?> teams</small></div>
            <div><span>Fixtures</span><strong><?= $fixtureCount ?></strong><small>Scheduled and recorded fixtures</small></div>
            <div><span>Last updated</span><strong><?= esc(mcc_user_datetime($tournament['updated_at'] ?? null, 'Not available')) ?></strong><small>Created <?= esc(mcc_user_datetime($tournament['created_at'] ?? null, 'Not available')) ?></small></div>
        </div>
    </section>

    <section class="mcc-card mcc-tournament-detail-card" aria-labelledby="tournament-participants-heading">
        <div class="mcc-card-heading">
            <div>
                <span class="mcc-eyebrow">Roster</span>
                <h2 id="tournament-participants-heading">Participants</h2>
                <p class="mcc-muted">Teams and Players currently linked to this Tournament.</p>
            </div>
            <?php if ($participants !== []): ?>
                <div class="mcc-tournament-participant-counts" aria-label="Participant totals">
                    <span class="mcc-badge mcc-badge-info"><?= $playerCount ?> Players</span>
                    <span class="mcc-badge mcc-badge-neutral"><?= $teamCount ?> Teams</span>
                </div>
            <?php endif; ?>
        </div>
        <?php if ($participants === []): ?>
            <p class="mcc-empty-state">No Players or teams are assigned.</p>
        <?php else: ?>
            <div class="mcc-table-wrap">
                <table class="mcc-table mcc-tournament-table">
                    <caption class="visually-hidden">Tournament participants</caption>
                    <thead><tr><th scope="col">Type</th><th scope="col">Name</th><th scope="col">Reference</th></tr></thead>
                    <tbody>
                        <?php foreach ($participants as $participant): ?>
                            <?php $isPlayer = ($participant['participant_type'] ?? '') === 'player'; ?>
                            <tr>
                                <td><span class="mcc-badge <?= $isPlayer ? 'mcc-badge-info' : 'mcc-badge-neutral' ?>"><?= $isPlayer ? 'Player' : 'Team' ?></span></td>
                                <th scope="row"><?= esc($isPlayer ? ($participant['player_name'] ?? 'Player') : ($participant['team_name'] ?? 'Team')) ?></th>
                                <td><?= $isPlayer ? 'Player #' . (int) $participant['player_id'] : '—' ?></td>
                            </tr>
                        <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        <?php endif ?>
    </section>

    <section class="mcc-card mcc-tournament-detail-card" aria-labelledby="tournament-fixtures-heading">
        <div class="mcc-card-heading"><div><h2 id="tournament-fixtures-heading">Fixtures</h2><p class="mcc-muted">Manual fixture schedule for this Tournament.</p></div><a class="mcc-button mcc-button-secondary" href="<?= site_url('tournaments/' . (int) $tournament['id'] . '/fixtures') ?>">Open fixtures</a></div>
        <?php if (($tournament['fixtures'] ?? []) === []): ?>
            <p class="mcc-empty-state">No fixtures are recorded.</p>
        <?php else: ?>
            <div class="mcc-table-wrap">
                <table class="mcc-table mcc-tournament-table">
                    <caption class="visually-hidden">Tournament fixtures</caption>
                    <thead><tr><th scope="col">Match</th><th scope="col">Round</th><th scope="col">Teams</th><th scope="col">Date and time</th><th scope="col">Ground</th><th scope="col">Status</th><th scope="col">Result</th></tr></thead>
                    <tbody>
                        <?php foreach ($tournament['fixtures'] as $fixture): ?>
                            <tr>
                                <th scope="row">#<?= ($fixture['match_number'] ?? null) !== null ? (int) $fixture['match_number'] : (int) $fixture['fixture_number'] ?></th>
                                <td><?= esc($fixture['round'] ?? ('Fixture #' . (int) $fixture['fixture_number'])) ?></td>
                                <td><strong><?= esc($fixture['home_team']) ?></strong> <span class="mcc-muted">V/S</span> <strong><?= esc($fixture['away_team']) ?></strong></td>
                                <td><?= esc($fixture['display_datetime'] ?? 'Not scheduled') ?></td>
                                <td><?= esc($fixture['ground_name'] ?? 'Not assigned') ?></td>
                                <?php $fixtureStatus = strtolower((string) ($fixture['status'] ?? 'planned')); ?>
                                <td><span class="mcc-badge mcc-badge-<?= $fixtureStatus === 'completed' ? 'success' : ($fixtureStatus === 'cancelled' ? 'danger' : 'info') ?>"><?= esc(ucfirst($fixtureStatus)) ?></span></td>
                                <td><?= $fixture['match_result'] ? esc((string) $fixture['match_result']) : 'Not recorded' ?><?= ($fixture['score_summary'] ?? '') !== '' ? ': ' . esc((string) $fixture['score_summary']) : '' ?></td>
                            </tr>
                        <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        <?php endif ?>
    </section>

    <section class="mcc-card mcc-tournament-detail-card" aria-labelledby="tournament-history-heading">
        <div class="mcc-card-heading"><h2 id="tournament-history-heading">Tournament history</h2></div>
        <?php if (($tournament['audit_history'] ?? []) === []): ?>
            <p class="mcc-empty-state">No Tournament audit events are available.</p>
        <?php else: ?>
            <div class="mcc-table-wrap">
                <table class="mcc-table mcc-tournament-history-table">
                    <caption class="visually-hidden">Tournament audit events</caption>
                    <thead><tr><th scope="col">Event</th><th scope="col">Actor</th><th scope="col">Timestamp</th><th scope="col">Reason</th><th scope="col">Previous values</th><th scope="col">New values</th></tr></thead>
                    <tbody>
                        <?php foreach ($tournament['audit_history'] as $event): ?>
                            <tr>
                                <th scope="row"><?= esc(str_replace('_', ' ', $event['action'])) ?></th>
                                <td><?= esc($event['actor_name'] ?? ('User #' . (int) $event['actor_user_id'])) ?></td>
                                <td><?= esc(mcc_user_datetime($event['created_at'])) ?></td>
                                <td><?= ($event['reason'] ?? '') !== '' ? esc($event['reason']) : '—' ?></td>
                                <td><?= $renderMetadata($event['before_metadata'] ?? []) ?></td>
                                <td><?= $renderMetadata($event['after_metadata'] ?? []) ?></td>
                            </tr>
                        <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        <?php endif ?>
    </section>
</div>

<?= view('staff_footer') ?>
