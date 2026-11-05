<?php
$tournamentId = (int) ($tournament['id'] ?? 0);
$fixtureId = (int) ($fixture['id'] ?? 0);
$status = strtolower((string) ($fixture['status'] ?? 'scheduled'));
$status = $status === 'planned' ? 'scheduled' : $status;
$history = $history ?? [];
$latestReason = '';
foreach (array_reverse($history) as $event) {
    if (($event['reason'] ?? '') !== '') {
        $latestReason = (string) $event['reason'];
        break;
    }
}
$statusClass = match ($status) {
    'completed' => 'mcc-badge-success',
    'cancelled' => 'mcc-badge-danger',
    'postponed' => 'mcc-badge-warning',
    default => 'mcc-badge-info',
};
$pageActions = '<div class="mcc-page-actions">'
    . '<a class="mcc-button mcc-button-secondary" href="' . site_url('tournaments/' . $tournamentId) . '">'
    . '<i class="fa-solid fa-arrow-left" aria-hidden="true"></i><span>Back to Tournament</span></a>'
    . '<a class="mcc-button mcc-button-secondary" href="' . site_url('tournaments/' . $tournamentId . '/fixtures') . '">'
    . '<i class="fa-solid fa-calendar-days" aria-hidden="true"></i><span>All fixtures</span></a>';
if (($can_update ?? false) === true && $status !== 'cancelled') {
    $pageActions .= '<a class="mcc-button mcc-button-primary" href="'
        . site_url('tournaments/' . $tournamentId . '/fixtures/' . $fixtureId . '/edit')
        . '"><i class="fa-solid fa-pen-to-square" aria-hidden="true"></i><span>'
        . ($status === 'completed' ? 'Correct fixture' : 'Edit fixture') . '</span></a>';
}
$pageActions .= '</div>';
$labelFor = static fn (string $key): string => ucfirst(str_replace(['_', '-'], ' ', $key));
$sensitiveKey = static fn (string $key): bool => preg_match('/password|token|secret|credential|authorization|document.?contents?/i', $key) === 1;
$renderValue = null;
$renderValue = static function (mixed $value) use (&$renderValue, $labelFor, $sensitiveKey): string {
    if (! is_array($value)) {
        return esc($value === null || $value === '' ? '—' : (string) $value);
    }
    $items = [];
    foreach ($value as $key => $item) {
        if ($sensitiveKey((string) $key)) {
            continue;
        }
        $items[] = '<div class="mcc-tournament-history-value"><dt>'
            . esc($labelFor((string) $key)) . '</dt><dd>' . $renderValue($item) . '</dd></div>';
    }

    return $items === []
        ? '<span class="mcc-muted">No safe values available</span>'
        : '<dl class="mcc-tournament-history-values">' . implode('', $items) . '</dl>';
};
?>
<?= view('staff_header', ['title' => $title, 'pageActions' => $pageActions]) ?>

<main class="container py-4 mcc-tournament-detail">
    <section class="mcc-card mcc-detail-hero mcc-fixture-detail-hero" aria-labelledby="fixture-heading">
        <div>
            <span class="mcc-eyebrow">Tournament fixture</span>
            <h2 id="fixture-heading">
                <span><?= esc($fixture['home_team'] ?? 'Home team') ?></span>
                <span class="mcc-fixture-vs">V/S</span>
                <span><?= esc($fixture['away_team'] ?? 'Away team') ?></span>
            </h2>
            <div class="mcc-muted mcc-fixture-reference">
                Match #<?= ($fixture['match_number'] ?? null) !== null ? (int) $fixture['match_number'] : (int) $fixtureId ?>
            </div>
            <div class="mcc-detail-badges" aria-label="Fixture references">
                <span class="mcc-badge mcc-badge-info"><?= esc($tournament['name'] ?? 'Tournament') ?></span>
                <span class="mcc-badge mcc-badge-neutral">Fixture #<?= (int) ($fixture['fixture_number'] ?? 0) ?></span>
            </div>
        </div>
        <div class="mcc-fixture-detail-status">
            <span class="mcc-eyebrow">Fixture status</span>
            <span class="mcc-badge <?= $statusClass ?>"><?= esc(ucfirst($status)) ?></span>
        </div>
    </section>

    <?php if ($message = session('message')): ?>
        <div class="mcc-alert mcc-alert-success" role="status"><?= esc($message) ?></div>
    <?php endif ?>
    <?php if ($error = session('error')): ?>
        <div class="mcc-alert mcc-alert-danger" role="alert"><?= esc($error) ?></div>
    <?php endif ?>

    <section class="mcc-card mcc-tournament-detail-card" aria-labelledby="fixture-summary-heading">
        <div class="mcc-card-heading">
            <div>
                <span class="mcc-eyebrow">Fixture overview</span>
                <h2 id="fixture-summary-heading">Fixture summary</h2>
            </div>
        </div>
        <dl class="mcc-detail-list mcc-tournament-detail-list">
            <div><dt>Tournament</dt><dd><?= esc($tournament['name'] ?? 'Not available') ?></dd></div>
            <div><dt>Round</dt><dd><?= esc($fixture['round'] ?? 'Not assigned') ?></dd></div>
            <div><dt>Match Number</dt><dd><?= ($fixture['match_number'] ?? null) !== null ? (int) $fixture['match_number'] : 'Not assigned' ?></dd></div>
            <div><dt>Fixture number</dt><dd><?= (int) ($fixture['fixture_number'] ?? 0) ?></dd></div>
            <div><dt>Status</dt><dd><span class="mcc-badge <?= $statusClass ?>"><?= esc(ucfirst($status)) ?></span></dd></div>
            <div><dt>Created</dt><dd><?= esc(mcc_user_datetime($fixture['created_at'] ?? null)) ?></dd></div>
            <div><dt>Last updated</dt><dd><?= esc(mcc_user_datetime($fixture['updated_at'] ?? null)) ?></dd></div>
        </dl>
    </section>

    <section class="mcc-card mcc-tournament-detail-card" aria-labelledby="fixture-teams-heading">
        <div class="mcc-card-heading">
            <div>
                <span class="mcc-eyebrow">Match-up</span>
                <h2 id="fixture-teams-heading">Teams</h2>
            </div>
        </div>
        <dl class="mcc-detail-list mcc-tournament-detail-list">
            <div><dt>Team 1</dt><dd><?= esc($fixture['home_team'] ?? 'Not assigned') ?></dd></div>
            <div><dt>Team 2</dt><dd><?= esc($fixture['away_team'] ?? 'Not assigned') ?></dd></div>
        </dl>
    </section>

    <section class="mcc-card mcc-tournament-detail-card" aria-labelledby="fixture-schedule-heading">
        <div class="mcc-card-heading">
            <div>
                <span class="mcc-eyebrow">Schedule and assignment</span>
                <h2 id="fixture-schedule-heading">Schedule and location</h2>
            </div>
        </div>
        <dl class="mcc-detail-list mcc-tournament-detail-list">
            <div><dt>Match date</dt><dd><?= esc(($fixture['match_date'] ?? '') !== '' ? mcc_user_date($fixture['match_date']) : 'Not scheduled') ?></dd></div>
            <?php
            $startTime = (string) ($fixture['start_time'] ?? '');
            $startTimeDisplay = $startTime;
            if ($startTime !== '') {
                $parsedStartTime = DateTimeImmutable::createFromFormat('!H:i', substr($startTime, 0, 5));
                if ($parsedStartTime !== false) {
                    $startTimeDisplay = $parsedStartTime->format('h:i A');
                }
            }
            ?>
            <div><dt>Start time</dt><dd><?= esc($startTimeDisplay !== '' ? $startTimeDisplay : 'Not scheduled') ?></dd></div>
            <div><dt>Ground</dt><dd><?= esc($fixture['ground_name'] ?? 'Not assigned') ?></dd></div>
            <div><dt>Cancellation or postponement reason</dt><dd><?= $latestReason !== '' ? esc($latestReason) : '—' ?></dd></div>
        </dl>
    </section>

    <section class="mcc-card mcc-tournament-detail-card" aria-labelledby="fixture-result-heading">
        <div class="mcc-card-heading">
            <div>
                <span class="mcc-eyebrow">Outcome</span>
                <h2 id="fixture-result-heading">Result</h2>
            </div>
        </div>
        <dl class="mcc-detail-list mcc-tournament-detail-list">
            <div><dt>Result reference</dt><dd><?= ($fixture['match_id'] ?? null) !== null ? 'Match #' . (int) $fixture['match_id'] : 'Not recorded' ?></dd></div>
            <div><dt>Match result</dt><dd><?= esc($fixture['match_result'] ?? 'Not recorded') ?></dd></div>
            <div><dt>Score summary</dt><dd><?= esc($fixture['score_summary'] ?? 'Not available') ?></dd></div>
        </dl>
    </section>

    <?php if (($can_update ?? false) === true && in_array($status, ['scheduled', 'postponed'], true)): ?>
        <section class="mcc-card mcc-tournament-detail-card" aria-labelledby="fixture-workflow-heading">
            <div class="mcc-card-heading">
                <div>
                    <h2 id="fixture-workflow-heading">Fixture actions</h2>
                    <p class="mcc-muted">Every status change requires a reason.</p>
                </div>
            </div>
            <div class="mcc-form-actions mcc-fixture-workflow">
                <?php if ($status === 'scheduled'): ?>
                    <form method="post" action="<?= site_url('tournaments/' . $tournamentId . '/fixtures/' . $fixtureId . '/postpone') ?>" class="mcc-inline-form">
                        <?= csrf_field() ?>
                        <label class="visually-hidden" for="postpone-reason">Postponement reason</label>
                        <input id="postpone-reason" class="form-control" name="reason" placeholder="Postponement reason" required>
                        <button class="mcc-button mcc-button-secondary" type="submit">Postpone</button>
                    </form>
                <?php endif ?>
                <form method="post" action="<?= site_url('tournaments/' . $tournamentId . '/fixtures/' . $fixtureId . '/cancel') ?>" class="mcc-inline-form">
                    <?= csrf_field() ?>
                    <label class="visually-hidden" for="cancel-reason">Cancellation reason</label>
                    <input id="cancel-reason" class="form-control" name="reason" placeholder="Cancellation reason" required>
                    <button class="mcc-button mcc-button-danger" type="submit">Cancel fixture</button>
                </form>
                <form method="post" action="<?= site_url('tournaments/' . $tournamentId . '/fixtures/' . $fixtureId . '/complete') ?>" class="mcc-inline-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="expected_version" value="<?= (int) ($fixture['version'] ?? 1) ?>">
                    <label class="visually-hidden" for="complete-match">Completed Match result</label>
                    <select id="complete-match" class="form-control" name="match_id" required>
                        <option value="">Select completed Match</option>
                        <?php foreach (($options['matches'] ?? []) as $match): ?>
                            <option
                                value="<?= (int) $match['id'] ?>"
                                <?= (int) ($fixture['match_id'] ?? 0) === (int) $match['id'] ? 'selected' : '' ?>
                            >
                                Match #<?= (int) $match['id'] ?> — <?= esc($match['opponent'] ?? 'Completed Match') ?>
                            </option>
                        <?php endforeach ?>
                    </select>
                    <button class="mcc-button mcc-button-primary" type="submit">Complete fixture</button>
                </form>
            </div>
        </section>
    <?php elseif ($status === 'completed'): ?>
        <div class="mcc-alert mcc-alert-info" role="status">This fixture is completed. Corrections require authorized access and a reason.</div>
    <?php elseif ($status === 'cancelled'): ?>
        <div class="mcc-alert mcc-alert-info" role="status">This fixture is cancelled and is read-only.</div>
    <?php endif ?>

    <section class="mcc-card mcc-tournament-detail-card" aria-labelledby="fixture-history-heading">
        <div class="mcc-card-heading"><h2 id="fixture-history-heading">Fixture history</h2></div>
        <?php if ($history === []): ?>
            <p class="mcc-empty-state">No fixture history is available.</p>
        <?php else: ?>
            <div class="mcc-table-wrap">
                <table class="mcc-table mcc-tournament-history-table">
                    <caption class="visually-hidden">Fixture audit history</caption>
                    <thead><tr><th scope="col">Event</th><th scope="col">Actor</th><th scope="col">Timestamp</th><th scope="col">Reason</th><th scope="col">Previous values</th><th scope="col">New values</th></tr></thead>
                    <tbody>
                        <?php foreach ($history as $event): ?>
                            <tr>
                                <th scope="row"><?= esc(str_replace('_', ' ', (string) ($event['action'] ?? 'Event'))) ?></th>
                                <td><?= esc($event['actor_name'] ?? ('User #' . (int) ($event['actor_user_id'] ?? 0))) ?></td>
                                <td><?= esc(mcc_user_datetime($event['created_at'] ?? null)) ?></td>
                                <td><?= ($event['reason'] ?? '') !== '' ? esc($event['reason']) : '—' ?></td>
                                <td><?= $renderValue($event['before_metadata'] ?? []) ?></td>
                                <td><?= $renderValue($event['after_metadata'] ?? []) ?></td>
                            </tr>
                        <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        <?php endif ?>
    </section>
</main>

<?= view('staff_footer') ?>
