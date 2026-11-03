<?php
$tournamentId = (int) ($tournament['id'] ?? 0);
$fixtures = (array) ($tournament['fixtures'] ?? []);
$canUpdate = (bool) ($can_update ?? false);
$pageActions = '<div class="mcc-page-actions">'
    . '<a class="mcc-button mcc-button-secondary" href="' . site_url('tournaments/' . $tournamentId) . '">'
    . '<i class="fa-solid fa-arrow-left" aria-hidden="true"></i><span>Back to Tournament</span></a>';
if (service('authorization')->can('tournament.update', (string) ($tournament['branch_id'] ?? 0))) {
    $pageActions .= '<a class="mcc-button mcc-button-primary" href="' . site_url('tournaments/' . $tournamentId . '/fixtures/new') . '">'
        . '<i class="fa-solid fa-plus" aria-hidden="true"></i><span>Create fixture</span></a>';
}
$pageActions .= '</div>';
?>
<?= view('staff_header', ['title' => $title, 'pageActions' => $pageActions]) ?>

<div class="mcc-match-list mcc-tournament-fixtures-page">
    <section class="mcc-card mcc-match-table-card" aria-labelledby="fixtures-heading">
        <div class="mcc-card-heading">
            <div>
                <span class="mcc-eyebrow">Schedule</span>
                <h2 id="fixtures-heading"><?= esc($tournament['name'] ?? 'Tournament') ?> fixtures</h2>
                <p class="mcc-muted">Review scheduled, completed, postponed, and cancelled fixtures.</p>
            </div>
            <?php if ($fixtures !== []): ?><span class="mcc-badge mcc-badge-info"><?= count($fixtures) ?> total</span><?php endif; ?>
        </div>
        <div class="mcc-table-wrap">
            <table class="mcc-table mcc-match-table mcc-tournament-table" aria-label="Tournament fixtures">
                <thead>
                    <tr>
                        <th scope="col">Fixture</th>
                        <th scope="col">Match</th>
                        <th scope="col">Date and time</th>
                        <th scope="col">Ground</th>
                        <th scope="col">Status</th>
                        <th scope="col" class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($fixtures as $fixture): ?>
                        <?php
                        $status = strtolower((string) ($fixture['status'] ?? 'scheduled'));
                        $statusClass = match ($status) {
                            'completed' => 'mcc-badge-success',
                            'cancelled' => 'mcc-badge-danger',
                            'postponed' => 'mcc-badge-warning',
                            default => 'mcc-badge-info',
                        };
                        $matchNumber = $fixture['match_number'] ?? null;
                        $fixtureNumber = $fixture['fixture_number'] ?? $fixture['id'] ?? null;
                        ?>
                        <tr>
                            <th scope="row">
                                <span class="mcc-fixture-reference">Fixture #<?= esc($fixtureNumber ?? '—') ?></span>
                                <span class="mcc-table-meta">Round <?= esc($fixture['round'] ?? 'Unassigned') ?></span>
                            </th>
                            <td>
                                <div class="mcc-fixture-matchup">
                                    <?php if ($matchNumber !== null && $matchNumber !== ''): ?>
                                        <span class="mcc-table-meta">Match #<?= esc($matchNumber) ?></span>
                                    <?php endif; ?>
                                    <span class="mcc-fixture-team"><?= esc($fixture['home_team'] ?? 'Team 1') ?></span>
                                    <span class="mcc-fixture-vs" aria-label="versus">V/S</span>
                                    <span class="mcc-fixture-team"><?= esc($fixture['away_team'] ?? 'Team 2') ?></span>
                                </div>
                            </td>
                            <td>
                                <span class="mcc-table-primary"><?= esc($fixture['display_datetime'] ?? 'Not scheduled') ?></span>
                            </td>
                            <td><?= esc($fixture['ground_name'] ?? 'Not assigned') ?></td>
                            <td><span class="mcc-badge <?= esc($statusClass) ?>"><?= esc(ucfirst($status)) ?></span></td>
                            <td class="text-right">
                                <div class="mcc-match-actions">
                                    <a class="mcc-button mcc-button-secondary" href="<?= site_url('tournaments/' . $tournamentId . '/fixtures/' . (int) $fixture['id']) ?>">
                                        <i class="fa-solid fa-eye" aria-hidden="true"></i><span class="mcc-button-label">View</span>
                                    </a>
                                    <?php if ($canUpdate): ?>
                                        <a class="mcc-button mcc-button-secondary" href="<?= site_url('tournaments/' . $tournamentId . '/fixtures/' . (int) $fixture['id'] . '/edit') ?>">
                                            <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i><span class="mcc-button-label">Edit</span>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach ?>
                    <?php if ($fixtures === []): ?>
                        <tr><td colspan="6" class="mcc-empty-state">No fixtures are recorded for this Tournament.</td></tr>
                    <?php endif ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<?= view('staff_footer') ?>
