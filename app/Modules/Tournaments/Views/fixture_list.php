<?php

$result = is_array($result ?? null) ? $result : [];
$fixtures = is_array($result['fixtures'] ?? null) ? $result['fixtures'] : [];
$filters = is_array($result['filters'] ?? null) ? $result['filters'] : [];
$status = static function (string $value): array {
    return match ($value) {
        'completed' => ['Completed', 'mcc-badge-success'],
        'cancelled' => ['Cancelled', 'mcc-badge-danger'],
        'postponed' => ['Postponed', 'mcc-badge-warning'],
        default => ['Scheduled', 'mcc-badge-info'],
    };
};
$queryUrl = static fn (array $values): string => site_url('tournaments/fixtures') . '?' . http_build_query($values);
$page = max(1, (int) ($result['page'] ?? 1));
$pages = max(1, (int) ($result['pages'] ?? 1));
$pageActions = '<div class="mcc-page-actions">'
    . '<button class="mcc-button mcc-button-secondary" type="button" data-filter-toggle aria-expanded="false" aria-controls="fixture-filters">'
    . '<i class="fa-solid fa-filter" aria-hidden="true"></i><span>Show filters</span></button>'
    . '<a class="mcc-button mcc-button-secondary" href="' . site_url('tournaments') . '">'
    . '<i class="fa-solid fa-arrow-left" aria-hidden="true"></i><span>Back to tournaments</span></a>'
    . '</div>';
?>
<?= view('staff_header', ['title' => $title, 'pageActions' => $pageActions]) ?>

<div class="mcc-match-list mcc-tournament-fixtures-page">
    <?php if (($error ?? null) !== null): ?>
        <div class="mcc-alert mcc-alert-danger" role="alert"><?= esc((string) $error) ?></div>
    <?php endif; ?>

    <section id="fixture-filters" class="mcc-card mcc-filter-card" hidden aria-labelledby="fixture-filters-heading">
        <div class="mcc-card-heading">
            <div>
                <span class="mcc-eyebrow">Search</span>
                <h2 id="fixture-filters-heading">Filter fixtures</h2>
            </div>
        </div>
        <form method="get" action="<?= site_url('tournaments/fixtures') ?>" class="row g-3">
            <div class="col-12 col-md-6 col-lg-5">
                <label for="fixture-query">Tournament or team</label>
                <input id="fixture-query" class="form-control" name="q" value="<?= esc((string) ($filters['q'] ?? '')) ?>" placeholder="Search tournament or team">
            </div>
            <div class="col-12 col-md-6 col-lg-3">
                <label for="fixture-status">Status</label>
                <select id="fixture-status" class="form-control" name="status">
                    <option value="">All statuses</option>
                    <?php foreach (['scheduled' => 'Scheduled', 'postponed' => 'Postponed', 'completed' => 'Completed', 'cancelled' => 'Cancelled'] as $value => $label): ?>
                        <option value="<?= esc($value) ?>" <?= ($filters['status'] ?? '') === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-lg-4 d-flex align-items-end gap-2">
                <button class="mcc-button mcc-button-primary" type="submit">Apply filters</button>
                <a class="mcc-button mcc-button-secondary" href="<?= site_url('tournaments/fixtures') ?>">Clear</a>
            </div>
        </form>
    </section>

    <section class="mcc-card mcc-match-table-card" aria-labelledby="fixtures-heading">
        <div class="mcc-card-heading">
            <div>
                <span class="mcc-eyebrow">Fixture schedule</span>
                <h2 id="fixtures-heading">Fixture results</h2>
                <p class="mcc-muted">Review scheduled and completed fixtures across your authorized branches.</p>
            </div>
            <span class="mcc-muted"><?= (int) ($result['total'] ?? 0) ?> results</span>
        </div>
        <div class="mcc-table-wrap">
            <table class="mcc-table mcc-tournament-table" aria-label="All authorized tournament fixtures">
                <thead>
                    <tr>
                        <th scope="col">Tournament</th>
                        <th scope="col">Match</th>
                        <th scope="col">Date and time</th>
                        <th scope="col">Branch</th>
                        <th scope="col">Status</th>
                        <th scope="col" class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($fixtures as $fixture): ?>
                        <?php [$statusLabel, $statusClass] = $status((string) ($fixture['status'] ?? 'scheduled')); ?>
                        <tr>
                            <th scope="row">
                                <span class="mcc-table-primary"><?= esc($fixture['tournament_name'] ?? 'Tournament unavailable') ?></span>
                                <span class="mcc-table-meta">Fixture #<?= (int) ($fixture['fixture_number'] ?? $fixture['id'] ?? 0) ?></span>
                            </th>
                            <td>
                                <div class="mcc-fixture-matchup">
                                    <span class="mcc-fixture-team"><?= esc($fixture['home_team'] ?? 'Team 1') ?></span>
                                    <span class="mcc-fixture-vs" aria-label="versus">V/S</span>
                                    <span class="mcc-fixture-team"><?= esc($fixture['away_team'] ?? 'Team 2') ?></span>
                                </div>
                                <?php if (($fixture['match_number'] ?? null) !== null): ?><span class="mcc-table-meta">Match #<?= (int) $fixture['match_number'] ?></span><?php endif; ?>
                            </td>
                            <td><?= esc($fixture['display_datetime'] ?? 'Not scheduled') ?></td>
                            <td><?= esc($fixture['branch_name'] ?? 'Not available') ?></td>
                            <td><span class="mcc-badge <?= esc($statusClass) ?>"><?= esc($statusLabel) ?></span></td>
                            <td class="text-right">
                                <div class="mcc-match-actions">
                                    <a class="mcc-button mcc-button-secondary" href="<?= site_url('tournaments/' . (int) ($fixture['tournament_id'] ?? 0) . '/fixtures/' . (int) ($fixture['id'] ?? 0)) ?>">
                                        <i class="fa-solid fa-eye" aria-hidden="true"></i><span class="mcc-button-label">View</span>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($fixtures === []): ?>
                        <tr><td colspan="6" class="mcc-empty-state">No fixtures are available in your authorized branches.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($pages > 1): ?>
            <nav class="mcc-pagination" aria-label="Fixture pages">
                <?php for ($number = 1; $number <= $pages; $number++): ?>
                    <a class="<?= $number === $page ? 'is-active' : '' ?>" href="<?= esc($queryUrl(array_merge($filters, ['page' => $number]))) ?>"><?= $number ?></a>
                <?php endfor; ?>
            </nav>
        <?php endif; ?>
    </section>
</div>

<?= view('staff_footer') ?>
