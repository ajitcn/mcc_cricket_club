<?php

$filters = is_array($result['filters'] ?? null) ? $result['filters'] : [];
$queryFilters = array_filter(
    $filters,
    static fn ($value, $key): bool => ! in_array($key, ['page', 'created_match'], true)
        && $value !== null
        && $value !== '',
    ARRAY_FILTER_USE_BOTH
);
$pageUrl = static function (int $page) use ($queryFilters): string {
    $query = http_build_query(array_merge($queryFilters, ['page' => $page]));

    return site_url('matches') . ($query === '' ? '' : '?' . $query);
};
$hasFilters = $queryFilters !== [];
$pageActions = '<div class="mcc-page-actions">'
    . '<button class="mcc-button mcc-button-secondary" type="button" data-filter-toggle data-show-label="Show filters" data-hide-label="Hide filters" aria-controls="match-filters" aria-expanded="' . ($hasFilters ? 'true' : 'false') . '">'
    . '<i class="fa-solid fa-filter" aria-hidden="true"></i><span class="mcc-button-label">' . ($hasFilters ? 'Hide filters' : 'Show filters') . '</span></button>'
    . '<a class="mcc-button mcc-button-primary" href="' . site_url('matches/new') . '">'
    . '<i class="fa-solid fa-plus" aria-hidden="true"></i><span class="mcc-button-label">Create match</span></a>'
    . '</div>';
?>
<?= view('staff_header', ['title' => $title, 'pageActions' => $pageActions]) ?>

<main class="mcc-match-list">
    <section id="match-filters" class="mcc-card mcc-match-filter-card" aria-labelledby="match-filters-heading" <?= $hasFilters ? '' : 'hidden' ?>>
        <div class="mcc-card-heading">
            <div>
                <span class="mcc-eyebrow">Matches</span>
                <h2 id="match-filters-heading">Search and filter</h2>
            </div>
            <span class="mcc-muted"><?= $hasFilters ? 'Filters applied' : 'All authorized branches' ?></span>
        </div>
        <form method="get" action="<?= site_url('matches') ?>" class="row" aria-label="Filter matches">
            <div class="col-12 col-md-4 mb-3">
                <label for="match-search">Search</label>
                <input class="form-control" id="match-search" name="q" type="search" maxlength="150" value="<?= esc($filters['q'] ?? '') ?>" placeholder="Opponent or match type">
            </div>
            <div class="col-12 col-md-4 mb-3">
                <label for="match-number">Match number</label>
                <input class="form-control" id="match-number" name="match_number" type="number" min="1" step="1" value="<?= esc($filters['match_number'] ?? '') ?>" placeholder="e.g. 12">
            </div>
            <div class="col-12 col-md-4 mb-3">
                <label for="match-filter-category">Category</label>
                <select class="form-control" id="match-filter-category" name="match_category">
                    <option value="">All categories</option>
                    <?php foreach (['regular' => 'Regular', 'tournament' => 'Tournament'] as $categoryValue => $categoryLabel): ?>
                        <option value="<?= esc($categoryValue) ?>" <?= ($filters['match_category'] ?? '') === $categoryValue ? 'selected' : '' ?>><?= esc($categoryLabel) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="col-12 col-md-4 mb-3">
                <label for="match-filter-tournament">Tournament</label>
                <select class="form-control" id="match-filter-tournament" name="tournament_id">
                    <option value="">All tournaments</option>
                    <?php foreach (($tournaments ?? []) as $tournament): ?>
                        <option value="<?= (int) $tournament['id'] ?>" <?= (string) ($filters['tournament_id'] ?? '') === (string) $tournament['id'] ? 'selected' : '' ?>><?= esc($tournament['name']) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="col-12 col-md-4 mb-3">
                <label for="match-date">Match date</label>
                <input class="form-control" id="match-date" name="date" type="text" inputmode="numeric" autocomplete="off" placeholder="DD-MM-YYYY" data-mcc-date-picker data-mcc-date-format="d-m-Y" data-mcc-date-submit-format="Y-m-d" value="<?= esc($filters['date'] ?? '') ?>">
            </div>
            <div class="col-12 col-md-2 mb-3">
                <label for="match-filter-status">Status</label>
                <select class="form-control" id="match-filter-status" name="status">
                    <option value="">All statuses</option>
                    <?php foreach (['scheduled', 'completed', 'cancelled'] as $status): ?>
                        <option value="<?= esc($status) ?>" <?= ($filters['status'] ?? '') === $status ? 'selected' : '' ?>><?= esc(ucfirst($status)) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="col-12 col-md-2 mb-3">
                <label for="match-filter-type">Match type</label>
                <select class="form-control" id="match-filter-type" name="match_type">
                    <option value="">All types</option>
                    <?php foreach (['friendly', 'practice', 'league', 'knockout'] as $type): ?>
                        <option value="<?= esc($type) ?>" <?= ($filters['match_type'] ?? '') === $type ? 'selected' : '' ?>><?= esc(ucfirst($type)) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="col-12 mt-1 mcc-form-actions">
                <button class="mcc-button mcc-button-primary" type="submit">
                    <i class="fa-solid fa-filter" aria-hidden="true"></i><span class="mcc-button-label">Apply filters</span>
                </button>
                <?php if ($hasFilters): ?>
                    <a class="mcc-button mcc-button-secondary" href="<?= site_url('matches') ?>">Clear filters</a>
                <?php endif ?>
            </div>
        </form>
    </section>

    <section class="mcc-card mcc-match-table-card" aria-labelledby="matches-heading">
        <div class="mcc-card-heading">
            <div>
                <span class="mcc-eyebrow">Competition records</span>
                <h2 id="matches-heading">Matches</h2>
            </div>
            <span class="mcc-muted"><?= (int) ($result['total'] ?? 0) ?> result<?= (int) ($result['total'] ?? 0) === 1 ? '' : 's' ?></span>
        </div>
        <div class="mcc-table-wrap">
            <table class="mcc-table mcc-match-table">
                <caption class="visually-hidden">Branch-scoped matches</caption>
                <thead>
                    <tr>
                        <th scope="col">Matchup</th>
                        <th scope="col">Type</th>
                        <th scope="col">Schedule</th>
                        <th scope="col">Assignment</th>
                        <th scope="col">Status</th>
                        <th scope="col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($result['matches'] as $match): ?>
                        <?php
                        $status = (string) ($match['status'] ?? 'scheduled');
                        $statusTone = match ($status) {
                            'completed' => 'success',
                            'cancelled' => 'danger',
                            'postponed' => 'warning',
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
                        ?>
                        <tr>
                            <th scope="row" class="mcc-match-opponent" data-label="Matchup">
                                <div class="mcc-match-reference">Match #<?= (int) $match['id'] ?></div>
                                <div class="mcc-match-team mcc-match-team-home"><?= esc($match['your_team'] ?? 'Not captured') ?></div>
                                <div class="mcc-match-vs">V/S</div>
                                <div class="mcc-match-team mcc-match-team-away"><?= esc($match['opponent']) ?></div>
                                <?php if (! empty($match['tournament_name'])): ?>
                                    <div class="mcc-match-linkage-tags">
                                        <span class="mcc-badge mcc-badge-info"><?= esc($match['tournament_name']) ?></span>
                                        <?php if (! empty($match['fixture_round'])): ?><span class="mcc-badge mcc-badge-neutral"><?= esc($match['fixture_round']) ?></span><?php endif ?>
                                        <?php if (! empty($match['fixture_number'])): ?><span class="mcc-badge mcc-badge-neutral">Fixture #<?= (int) $match['fixture_number'] ?></span><?php endif ?>
                                    </div>
                                <?php endif ?>
                            </th>
                            <td data-label="Type">
                                <div><?= esc(ucfirst((string) $match['match_type'])) ?></div>
                                <span class="mcc-badge <?= ($match['match_category'] ?? 'regular') === 'tournament' ? 'mcc-badge-info' : 'mcc-badge-neutral' ?>">
                                    <?= esc(ucfirst((string) ($match['match_category'] ?? 'regular'))) ?>
                                </span>
                            </td>
                            <td class="mcc-match-date" data-label="Schedule">
                                <span class="mcc-table-primary-text"><time datetime="<?= esc((string) ($match['starts_at'] ?? '')) ?>"><?= esc(mcc_user_datetime($match['starts_at'] ?? null)) ?></time></span>
                            </td>
                            <td data-label="Assignment">
                                <div class="mcc-match-assignment">
                                    <span class="mcc-table-primary-text"><?= esc($match['branch_name'] ?? 'Branch unavailable') ?></span>
                                    <span class="mcc-table-secondary-text">Ground: <?= esc($match['ground_name'] ?? 'Not assigned') ?></span>
                                    <span class="mcc-table-secondary-text">Coach: <?= esc($match['coach_name'] ?? 'Not assigned') ?></span>
                                </div>
                            </td>
                            <td data-label="Status">
                                <div class="mcc-match-status-stack">
                                <span class="mcc-badge mcc-badge-<?= esc($statusTone) ?>"><?= esc(ucfirst($status)) ?></span>
                                <?php if ($resultValue !== ''): ?>
                                    <span class="mcc-badge mcc-badge-<?= esc($resultTone) ?>">
                                        <?= esc(ucfirst(str_replace('_', ' ', $resultValue))) ?>
                                    </span>
                                <?php endif; ?>
                                <?php if (($match['score_summary'] ?? '') !== ''): ?>
                                    <span class="mcc-badge mcc-badge-neutral">Score: <?= esc((string) $match['score_summary']) ?></span>
                                <?php endif; ?>
                                </div>
                            </td>
                            <td data-label="Actions">
                                <div class="mcc-session-action-menu" data-session-actions>
                                    <button
                                        class="mcc-button mcc-button-secondary"
                                        type="button"
                                        data-session-actions-toggle
                                        aria-expanded="false"
                                        aria-haspopup="true"
                                        aria-controls="match-actions-<?= (int) $match['id'] ?>"
                                    >
                                        <i class="fa-solid fa-ellipsis-vertical" aria-hidden="true"></i>
                                        <span>Actions</span>
                                    </button>
                                    <div class="mcc-session-action-dropdown" id="match-actions-<?= (int) $match['id'] ?>" data-session-actions-menu hidden>
                                        <a href="<?= site_url('matches/' . (int) $match['id']) ?>">
                                            <i class="fa-solid fa-eye" aria-hidden="true"></i>
                                            View match
                                        </a>
                                        <a href="<?= site_url('matches/' . (int) $match['id'] . '/performance/template') ?>">
                                            <i class="fa-solid fa-download" aria-hidden="true"></i>
                                            Download CSV
                                        </a>
                                        <?php if (! empty($match['can_update'])): ?>
                                            <a href="<?= site_url('matches/' . (int) $match['id'] . '/performance#import-performance') ?>">
                                                <i class="fa-solid fa-upload" aria-hidden="true"></i>
                                                Upload CSV
                                            </a>
                                            <a href="<?= site_url('matches/' . (int) $match['id'] . '/edit') ?>">
                                                <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
                                                Edit match
                                            </a>
                                        <?php endif ?>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach ?>
                    <?php if (($result['matches'] ?? []) === []): ?>
                        <tr><td colspan="6" class="mcc-empty-state">No matches are available in your authorized branches.</td></tr>
                    <?php endif ?>
                </tbody>
            </table>
        </div>
        <?php if ((int) ($result['pages'] ?? 1) > 1): ?>
            <nav class="mcc-pagination" aria-label="Match pages">
                <span>Page <?= (int) ($result['page'] ?? 1) ?> of <?= (int) ($result['pages'] ?? 1) ?></span>
                <div class="mcc-pagination-actions">
                    <?php if ((int) ($result['page'] ?? 1) > 1): ?><a class="mcc-button mcc-button-secondary" href="<?= esc($pageUrl((int) $result['page'] - 1)) ?>">Previous</a><?php endif ?>
                    <?php if ((int) ($result['page'] ?? 1) < (int) ($result['pages'] ?? 1)): ?><a class="mcc-button mcc-button-secondary" href="<?= esc($pageUrl((int) $result['page'] + 1)) ?>">Next</a><?php endif ?>
                </div>
            </nav>
        <?php endif ?>
    </section>
</main>

<?= view('staff_footer') ?>
