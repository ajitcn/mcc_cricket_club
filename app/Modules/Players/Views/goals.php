<?php

$result = is_array($result ?? null) ? $result : [];
$filters = is_array($result['filters'] ?? null) ? $result['filters'] : [];
$goals = is_array($result['goals'] ?? null) ? $result['goals'] : [];
$branches = is_array($result['branches'] ?? null) ? $result['branches'] : [];
$coaches = is_array($result['coaches'] ?? null) ? $result['coaches'] : [];
$currentPage = max(1, (int) ($result['page'] ?? 1));
$pageCount = max(1, (int) ($result['pages'] ?? 1));
$pageWindow = mcc_follow_up_page_window($currentPage, $pageCount);
$queryFilters = array_filter(
    $filters,
    static fn ($value, $key): bool => $key !== 'page' && $value !== null && $value !== '',
    ARRAY_FILTER_USE_BOTH,
);
$pageUrl = static function (int $page) use ($queryFilters): string {
    $query = http_build_query(array_merge($queryFilters, ['page' => $page]));

    return site_url('player-goals') . ($query === '' ? '' : '?' . $query);
};
?>
<?= view('staff_header', [
    'title' => 'Player Goals',
    'nav' => $nav,
    'message' => $message,
    'error' => $error,
    'breadcrumbTrail' => [
        [
            'label' => 'Players',
            'url' => site_url('players'),
        ],
        [
            'label' => 'Player Goals',
        ],
    ],
    'pageActions' => '<div class="mcc-page-actions">
        <a class="mcc-button mcc-button-primary" href="' . esc(site_url('player-goals/new')) . '">
            <i class="fa-solid fa-plus" aria-hidden="true"></i>
            Create New Goal
        </a>
        <button class="mcc-button mcc-button-secondary" type="button" data-filter-toggle
            data-show-label="Show Filters" data-hide-label="Hide Filters"
            aria-controls="player-goal-filters" aria-expanded="false">
            <i class="fa-solid fa-filter" aria-hidden="true"></i>
            <span class="mcc-button-label">Show Filters</span>
        </button>
    </div>',
]) ?>

<div class="mcc-player-goals-page">
    <section id="player-goal-filters" class="mcc-card mcc-player-goals-filter" aria-labelledby="player-goal-filters-heading" hidden>
        <div class="mcc-card-heading">
            <div>
                <span class="mcc-eyebrow">Search and filter</span>
                <h2 id="player-goal-filters-heading">Goal filters</h2>
            </div>
        </div>
        <form method="get" action="<?= esc(site_url('player-goals')) ?>" class="row mcc-player-filter-grid">
            <div class="col-12 col-md-6 col-lg-4 mb-2">
                <label for="goal-player">Player name or ID</label>
                <input class="form-control" id="goal-player" name="player" type="search" maxlength="100" value="<?= esc((string) ($filters['player'] ?? '')) ?>">
            </div>
            <?php if (count($branches) > 1): ?>
                <div class="col-12 col-md-6 col-lg-4 mb-2">
                    <label for="goal-branch">Branch</label>
                    <select class="form-control" id="goal-branch" name="branch_id">
                        <option value="">All authorized branches</option>
                        <?php foreach ($branches as $branch): ?>
                            <option value="<?= (int) $branch['id'] ?>" <?= (string) ($filters['branch_id'] ?? '') === (string) $branch['id'] ? 'selected' : '' ?>><?= esc((string) $branch['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            <div class="col-12 col-md-6 col-lg-4 mb-2">
                <label for="goal-coach">Coach</label>
                <select class="form-control" id="goal-coach" name="coach_id">
                    <option value="">All Coaches</option>
                    <?php foreach ($coaches as $coach): ?>
                        <option value="<?= (int) $coach['id'] ?>" <?= (string) ($filters['coach_id'] ?? '') === (string) $coach['id'] ? 'selected' : '' ?>><?= esc((string) $coach['display_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-6 col-lg-4 mb-2">
                <label for="goal-title">Goal title</label>
                <input class="form-control" id="goal-title" name="title" type="search" maxlength="150" value="<?= esc((string) ($filters['title'] ?? '')) ?>">
            </div>
            <?php foreach ([
                'created_from' => 'Created from',
                'created_to' => 'Created to',
                'updated_from' => 'Updated from',
                'updated_to' => 'Updated to',
            ] as $field => $label): ?>
                <div class="col-12 col-md-6 col-lg-3 mb-2">
                    <label for="goal-<?= esc($field) ?>"><?= esc($label) ?></label>
                    <input
                        class="form-control"
                        id="goal-<?= esc($field) ?>"
                        name="<?= esc($field) ?>"
                        type="text"
                        inputmode="numeric"
                        placeholder="DD-MM-YYYY"
                        autocomplete="off"
                        data-mcc-date-picker
                        data-mcc-date-picker-no-toggle
                        data-mcc-date-format="d-m-Y"
                        data-mcc-date-submit-format="Y-m-d"
                        value="<?= esc(mcc_user_date_input($filters[$field] ?? null)) ?>"
                    >
                </div>
            <?php endforeach; ?>
            <div class="col-12 mt-2 text-right">
                <button class="mcc-button mcc-button-primary" type="submit">
                    <i class="fa-solid fa-filter" aria-hidden="true"></i>
                    Apply filters
                </button>
                <a class="mcc-button mcc-button-secondary" href="<?= esc(site_url('player-goals')) ?>">
                    <i class="fa-solid fa-rotate-left" aria-hidden="true"></i>
                    Clear filters
                </a>
            </div>
        </form>
    </section>

    <section class="mcc-card mcc-detail-card" aria-labelledby="player-goals-heading">
        <div class="mcc-card-heading">
            <div>
                <span class="mcc-eyebrow">Player development</span>
                <h2 id="player-goals-heading">Player goals</h2>
            </div>
            <span class="mcc-muted">
                <?= (int) ($result['total'] ?? 0) ?> result<?= (int) ($result['total'] ?? 0) === 1 ? '' : 's' ?>
            </span>
        </div>

        <?php if ($goals === []): ?>
            <div class="mcc-empty-state" role="status">
                <i class="fa-regular fa-circle-check" aria-hidden="true"></i>
                <h2>No player goals found</h2>
                <p>No goals match the selected filters.</p>
            </div>
        <?php else: ?>
            <div class="mcc-table-wrap mcc-table-responsive mcc-player-goals-table-wrap">
                <table class="mcc-table mcc-player-goals-table">
                    <caption class="visually-hidden">Authorized Player goals</caption>
                    <thead>
                        <tr>
                            <th scope="col">Player</th>
                            <th scope="col">Goal details</th>
                            <th scope="col">Coach &amp; updates</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($goals as $goal): ?>
                            <?php
                            $preview = trim((string) ($goal['description'] ?? ''));
                            $playerReference = trim((string) ($goal['player_reference'] ?? ''));
                            $canManageGoal = service('authorization')->can(
                                'player.goal.set',
                                (string) ($goal['branch_id'] ?? 0),
                            );
                            ?>
                            <tr>
                                <td data-label="Player">
                                    <div class="mcc-goal-table-group">
                                        <strong class="mcc-goal-table-value mcc-goal-primary-value">
                                            <?= esc((string) ($goal['player_name'] ?? 'Unnamed player')) ?>
                                        </strong>
                                        <span class="mcc-goal-table-value mcc-muted">
                                            #<?= esc($playerReference !== '' ? $playerReference : (string) ((int) ($goal['player_id'] ?? 0))) ?>
                                        </span>
                                        <span class="mcc-goal-table-value mcc-muted">
                                            Branch: <?= esc((string) ($goal['branch_name'] ?? 'Authorized branch')) ?>
                                        </span>
                                    </div>
                                </td>
                                <td data-label="Goal details">
                                    <div class="mcc-goal-table-group">
                                        <strong class="mcc-goal-table-value mcc-goal-primary-value">
                                            <?= esc((string) ($goal['title'] ?? '')) ?>
                                        </strong>
                                        <span class="mcc-goal-table-value mcc-goal-description" title="<?= esc($preview) ?>">
                                            <?= esc($preview !== '' ? $preview : 'No description recorded.') ?>
                                        </span>
                                    </div>
                                </td>
                                <td data-label="Coach &amp; updates">
                                    <div class="mcc-goal-table-group">
                                        <strong class="mcc-goal-table-value">
                                            Coach: <?= esc((string) ($goal['coach_name'] ?? 'Not assigned')) ?>
                                        </strong>
                                        <span class="mcc-goal-table-value mcc-muted mcc-goal-timeline">
                                            <span>Created <?= esc(mcc_user_datetime($goal['created_at'] ?? null)) ?></span>
                                            <span>Updated <?= esc(mcc_user_datetime($goal['updated_at'] ?? null)) ?></span>
                                        </span>
                                    </div>
                                </td>
                                <td data-label="Actions">
                                    <details class="mcc-detail-action-menu mcc-goal-actions">
                                        <summary class="mcc-button mcc-button-secondary">
                                            <i class="fa-solid fa-ellipsis-vertical" aria-hidden="true"></i>
                                            <span>Actions</span>
                                        </summary>
                                        <?php if ($canManageGoal): ?>
                                            <div class="mcc-detail-action-dropdown" role="menu" aria-label="Goal actions">
                                                <a href="<?= esc(site_url('player-goals/' . (int) $goal['id'])) ?>" role="menuitem">
                                                    <i class="fa-solid fa-eye" aria-hidden="true"></i>
                                                    <span>View Goal</span>
                                                </a>
                                                <a href="<?= esc(site_url('player-goals/' . (int) $goal['id'] . '/edit')) ?>" role="menuitem">
                                                    <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
                                                    <span>Edit Goal</span>
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                    </details>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <?php if ($pageCount > 1): ?>
            <nav class="mcc-pagination" aria-label="Player Goal pages">
                <span>Page <?= $currentPage ?> of <?= $pageCount ?></span>
                <div class="mcc-pagination-actions">
                    <?php if ($currentPage > 1): ?>
                        <a class="mcc-button mcc-button-secondary" href="<?= esc($pageUrl($currentPage - 1)) ?>">Previous</a>
                    <?php endif; ?>
                    <?php foreach ($pageWindow as $page): ?>
                        <?php if (is_string($page)): ?>
                            <span class="mcc-pagination-ellipsis" aria-hidden="true">&hellip;</span>
                        <?php else: ?>
                            <a class="mcc-button <?= (int) $page === $currentPage ? 'mcc-button-primary' : 'mcc-button-secondary' ?>"
                                href="<?= esc($pageUrl((int) $page)) ?>"
                                <?= (int) $page === $currentPage ? 'aria-current="page"' : '' ?>>
                                <?= (int) $page ?>
                            </a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <?php if ($currentPage < $pageCount): ?>
                        <a class="mcc-button mcc-button-secondary" href="<?= esc($pageUrl($currentPage + 1)) ?>">Next</a>
                    <?php endif; ?>
                </div>
            </nav>
        <?php endif; ?>
    </section>
</div>

<?= view('staff_footer') ?>
