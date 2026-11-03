<?php

$result = is_array($result ?? null) ? $result : [];
$filters = is_array($result['filters'] ?? null) ? $result['filters'] : [];
$plans = is_array($result['plans'] ?? null) ? $result['plans'] : [];
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

    return site_url('player-session-plans') . ($query === '' ? '' : '?' . $query);
};
?>
<?= view('staff_header', [
    'title' => 'Player Session Plans',
    'nav' => $nav,
    'message' => $message,
    'error' => $error,
    'breadcrumbTrail' => [
        ['label' => 'Players', 'url' => site_url('players')],
        ['label' => 'Session Plans'],
    ],
    'pageActions' => '<div class="mcc-page-actions">
        ' . (! empty($can_manage)
            ? '<a class="mcc-button mcc-button-primary" href="' . esc(site_url('player-session-plans/new')) . '">
                <i class="fa-solid fa-plus" aria-hidden="true"></i>
                Create New Plan
            </a>'
            : '') . '
        <button class="mcc-button mcc-button-secondary" type="button" data-filter-toggle
            data-show-label="Show Filters" data-hide-label="Hide Filters"
            aria-controls="player-session-plan-filters" aria-expanded="false">
            <i class="fa-solid fa-filter" aria-hidden="true"></i>
            <span class="mcc-button-label">Show Filters</span>
        </button>
    </div>',
]) ?>

<div class="mcc-player-session-plans-page">
    <section id="player-session-plan-filters" class="mcc-card mcc-player-session-plan-filter" aria-labelledby="player-session-plan-filters-heading" hidden>
        <div class="mcc-card-heading">
            <div>
                <span class="mcc-eyebrow">Search and filter</span>
                <h2 id="player-session-plan-filters-heading">Session Plan filters</h2>
            </div>
        </div>
        <form method="get" action="<?= esc(site_url('player-session-plans')) ?>" class="row mcc-player-filter-grid">
            <div class="col-12 col-md-6 col-lg-3 mb-2">
                <label for="session-plan-player">Player name or ID</label>
                <input class="form-control" id="session-plan-player" name="player" type="search" maxlength="100" value="<?= esc((string) ($filters['player'] ?? '')) ?>">
            </div>
            <?php if (count($branches) > 1): ?>
                <div class="col-12 col-md-6 col-lg-3 mb-2">
                    <label for="session-plan-branch">Branch</label>
                    <select class="form-control" id="session-plan-branch" name="branch_id">
                        <option value="">All authorized branches</option>
                        <?php foreach ($branches as $branch): ?>
                            <option
                                value="<?= (int) $branch['id'] ?>"
                                <?= (string) ($filters['branch_id'] ?? '') === (string) $branch['id'] ? 'selected' : '' ?>
                            >
                                <?= esc((string) $branch['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            <div class="col-12 col-md-6 col-lg-3 mb-2">
                <label for="session-plan-coach">Coach</label>
                <select class="form-control" id="session-plan-coach" name="coach_id">
                    <option value="">All Coaches</option>
                    <?php foreach ($coaches as $coach): ?>
                        <option
                            value="<?= (int) $coach['id'] ?>"
                            <?= (string) ($filters['coach_id'] ?? '') === (string) $coach['id'] ? 'selected' : '' ?>
                        >
                            <?= esc((string) $coach['display_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-6 col-lg-3 mb-2">
                <label for="session-plan-created-from">Created from</label>
                <input
                    class="form-control"
                    id="session-plan-created-from"
                    name="created_from"
                    type="text"
                    inputmode="numeric"
                    placeholder="DD-MM-YYYY"
                    autocomplete="off"
                    data-mcc-date-picker
                    data-mcc-date-picker-no-toggle
                    data-mcc-date-format="d-m-Y"
                    data-mcc-date-submit-format="Y-m-d"
                    value="<?= esc(mcc_user_date_input($filters['created_from'] ?? null)) ?>"
                >
            </div>
            <div class="col-12 col-md-6 col-lg-3 mb-2">
                <label for="session-plan-created-to">Created to</label>
                <input
                    class="form-control"
                    id="session-plan-created-to"
                    name="created_to"
                    type="text"
                    inputmode="numeric"
                    placeholder="DD-MM-YYYY"
                    autocomplete="off"
                    data-mcc-date-picker
                    data-mcc-date-picker-no-toggle
                    data-mcc-date-format="d-m-Y"
                    data-mcc-date-submit-format="Y-m-d"
                    value="<?= esc(mcc_user_date_input($filters['created_to'] ?? null)) ?>"
                >
            </div>
            <div class="col-12 mt-2 text-right">
                <button class="mcc-button mcc-button-primary" type="submit">
                    <i class="fa-solid fa-filter" aria-hidden="true"></i>
                    Apply filters
                </button>
                <a class="mcc-button mcc-button-secondary" href="<?= esc(site_url('player-session-plans')) ?>">
                    <i class="fa-solid fa-rotate-left" aria-hidden="true"></i>
                    Clear filters
                </a>
            </div>
        </form>
    </section>

    <section class="mcc-card mcc-detail-card" aria-labelledby="player-session-plans-heading">
        <div class="mcc-card-heading">
            <div>
                <span class="mcc-eyebrow">Coach workspace</span>
                <h2 id="player-session-plans-heading">Session Plans</h2>
            </div>
            <div class="mcc-player-session-plan-heading-actions">
                <span class="mcc-muted"><?= (int) ($result['total'] ?? 0) ?> result<?= (int) ($result['total'] ?? 0) === 1 ? '' : 's' ?></span>
            </div>
        </div>

        <?php if ($plans === []): ?>
            <div class="mcc-empty-state" role="status">
                <i class="fa-solid fa-clipboard-list" aria-hidden="true"></i>
                <h2>No Session Plans found</h2>
                <p>No Session Plans match the selected filters.</p>
            </div>
        <?php else: ?>
            <div class="mcc-table-wrap mcc-table-responsive mcc-player-session-plan-table-wrap">
                <table class="mcc-table mcc-player-session-plan-table">
                    <caption class="visually-hidden">Authorized Player Session Plans</caption>
                    <thead>
                        <tr>
                            <th scope="col">Player</th>
                            <th scope="col">Plan details</th>
                            <th scope="col">Coach &amp; updates</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($plans as $plan): ?>
                            <?php
                            $planId = (int) ($plan['id'] ?? 0);
                            $canView = service('authorization')->can(
                                'player.session_plan.view',
                                (string) ($plan['branch_id'] ?? 0),
                            );
                            $canManage = service('authorization')->can(
                                'player.session_plan.manage',
                                (string) ($plan['branch_id'] ?? 0),
                            );
                            ?>
                            <tr>
                                <td data-label="Player">
                                    <div class="mcc-session-plan-table-group">
                                        <strong class="mcc-session-plan-primary-value">
                                            <?= esc((string) ($plan['player_name'] ?? 'Not available')) ?>
                                        </strong>
                                        <span class="mcc-session-plan-table-value mcc-muted">
                                            Player #<?= (int) ($plan['player_id'] ?? 0) ?>
                                        </span>
                                        <span class="mcc-session-plan-table-value mcc-muted">
                                            Branch: <?= esc((string) ($plan['branch_name'] ?? 'Not available')) ?>
                                        </span>
                                    </div>
                                </td>
                                <td data-label="Plan details">
                                    <div class="mcc-player-session-plan-summary">
                                        <strong class="mcc-session-plan-primary-value">
                                            Problem: <?= esc((string) ($plan['problem'] ?? 'Not recorded')) ?>
                                        </strong>
                                        <span class="mcc-session-plan-table-value">
                                            Outcome: <?= esc((string) ($plan['outcome'] ?? 'Not recorded')) ?>
                                        </span>
                                    </div>
                                </td>
                                <td data-label="Coach &amp; updates">
                                    <div class="mcc-session-plan-table-group">
                                        <strong class="mcc-session-plan-table-value">
                                            Coach: <?= esc((string) ($plan['coach_name'] ?? 'Not available')) ?>
                                        </strong>
                                        <span class="mcc-session-plan-table-value mcc-muted mcc-session-plan-timeline">
                                            <span>Created <?= esc(mcc_user_datetime($plan['created_at'] ?? null)) ?></span>
                                            <span>Updated <?= esc(mcc_user_datetime($plan['updated_at'] ?? null)) ?></span>
                                        </span>
                                    </div>
                                </td>
                                <td data-label="Actions">
                                    <details
                                        class="mcc-detail-action-menu mcc-player-session-plan-actions"
                                        data-session-plan-action-menu
                                    >
                                        <summary
                                            class="mcc-button mcc-button-secondary"
                                            data-player-actions-toggle
                                            aria-expanded="false"
                                            aria-haspopup="true"
                                            aria-controls="session-plan-actions-<?= $planId ?>"
                                        >
                                            <i class="fa-solid fa-ellipsis-vertical" aria-hidden="true"></i>
                                            <span>Actions</span>
                                        </summary>
                                        <?php if ($canView || $canManage): ?>
                                            <div
                                                id="session-plan-actions-<?= $planId ?>"
                                                class="mcc-detail-action-dropdown"
                                                role="menu"
                                                aria-label="Session Plan actions"
                                            >
                                                <?php if ($canView): ?>
                                                    <a href="<?= esc(site_url('player-session-plans/' . $planId)) ?>" role="menuitem">
                                                        <i class="fa-solid fa-eye" aria-hidden="true"></i>
                                                        <span>View Plan</span>
                                                    </a>
                                                <?php endif; ?>
                                                <?php if ($canManage): ?>
                                                    <a href="<?= esc(site_url('player-session-plans/' . $planId . '/edit')) ?>" role="menuitem">
                                                        <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
                                                        <span>Edit Plan</span>
                                                    </a>
                                                <?php endif; ?>
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
            <nav class="mcc-pagination" aria-label="Player Session Plan pages">
                <span>Page <?= $currentPage ?> of <?= $pageCount ?></span>
                <div class="mcc-pagination-actions">
                    <?php if ($currentPage > 1): ?>
                        <a class="mcc-button mcc-button-secondary" href="<?= esc($pageUrl($currentPage - 1)) ?>">Previous</a>
                    <?php endif; ?>
                    <?php foreach ($pageWindow as $page): ?>
                        <?php if (is_string($page)): ?>
                            <span class="mcc-pagination-ellipsis" aria-hidden="true">&hellip;</span>
                        <?php else: ?>
                            <a
                                class="mcc-button <?= (int) $page === $currentPage ? 'mcc-button-primary' : 'mcc-button-secondary' ?>"
                                href="<?= esc($pageUrl((int) $page)) ?>"
                                <?= (int) $page === $currentPage ? 'aria-current="page"' : '' ?>
                            >
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
