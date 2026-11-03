<?php

$result = is_array($result ?? null) ? $result : [];
$feedbacks = is_array($result['feedbacks'] ?? null) ? $result['feedbacks'] : [];
$filters = is_array($result['filters'] ?? null) ? $result['filters'] : [];
$branches = is_array($result['branches'] ?? null) ? $result['branches'] : [];
$coaches = is_array($result['coaches'] ?? null) ? $result['coaches'] : [];
$currentPage = max(1, (int) ($result['page'] ?? 1));
$pageCount = max(1, (int) ($result['pages'] ?? 1));
$pageWindow = mcc_follow_up_page_window($currentPage, $pageCount);
$periodLabels = [
    1 => 'Sep period',
    2 => 'Dec period',
    3 => 'Mar period',
    4 => 'Jun period',
];

$queryFilters = array_filter(
    $filters,
    static fn ($value): bool => $value !== null && $value !== '',
);
unset($queryFilters['page']);

$pageUrl = static function (int $page) use ($queryFilters): string {
    $query = http_build_query(array_merge($queryFilters, ['page' => $page]));

    return site_url('player-feedback') . ($query === '' ? '' : '?' . $query);
};
?>
<?= view('staff_header', [
    'title' => 'Coach Feedback',
    'nav' => $nav,
    'message' => $message,
    'error' => $error,
    'breadcrumbTrail' => [
        ['label' => 'Players', 'url' => site_url('players')],
        ['label' => 'Coach Feedback'],
    ],
    'pageActions' => '<div class="mcc-page-actions">
        <a class="mcc-button mcc-button-primary" href="' . esc(site_url('player-feedback/new')) . '">
            <i class="fa-solid fa-plus" aria-hidden="true"></i>
            Add Feedback
        </a>
        <a class="mcc-button mcc-button-secondary" href="' . esc(site_url('player-feedback/annual')) . '">
            <i class="fa-solid fa-file-lines" aria-hidden="true"></i>
            Annual Feedback Report
        </a>
        <button class="mcc-button mcc-button-secondary" type="button"
            data-filter-toggle aria-controls="player-feedback-filters"
            aria-expanded="false">
            <i class="fa-solid fa-filter" aria-hidden="true"></i>
            Filters
        </button>
    </div>',
]) ?>

<section class="mcc-player-feedback-page">
    <section id="player-feedback-filters" class="mcc-card" hidden
        aria-labelledby="player-feedback-filters-heading">
        <div class="mcc-card-heading">
            <div>
                <span class="mcc-eyebrow">Search and filter</span>
                <h2 id="player-feedback-filters-heading">Feedback filters</h2>
            </div>
        </div>
        <form method="get" action="<?= esc(site_url('player-feedback')) ?>"
            class="row mcc-player-filter-grid">
            <div class="col-12 col-md-6 col-lg-4 mb-2 mcc-player-filter-field">
                <label for="feedback-player">Player name or ID</label>
                <input class="form-control" id="feedback-player" name="player"
                    type="search" maxlength="100"
                    placeholder="Search by Player name or ID"
                    value="<?= esc((string) ($filters['player'] ?? '')) ?>">
            </div>
            <div class="col-12 col-md-6 col-lg-4 mb-2 mcc-player-filter-field">
                <label for="feedback-coach">Coach</label>
                <select class="form-control" id="feedback-coach" name="coach_id">
                    <option value="">All coaches</option>
                    <?php foreach ($coaches as $coach): ?>
                        <option value="<?= (int) $coach['id'] ?>"
                            <?= (string) ($filters['coach_id'] ?? '') === (string) $coach['id'] ? 'selected' : '' ?>>
                            <?= esc((string) $coach['display_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if (count($branches) > 1): ?>
                <div class="col-12 col-md-6 col-lg-4 mb-2 mcc-player-filter-field">
                    <label for="feedback-branch">Branch</label>
                    <select class="form-control" id="feedback-branch" name="branch_id">
                        <option value="">All authorized branches</option>
                        <?php foreach ($branches as $branch): ?>
                            <option value="<?= (int) $branch['id'] ?>"
                                <?= (string) ($filters['branch_id'] ?? '') === (string) $branch['id'] ? 'selected' : '' ?>>
                                <?= esc((string) $branch['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            <div class="col-12 col-md-6 col-lg-4 mb-2 mcc-player-filter-field">
                <label for="feedback-year-filter">Year</label>
                <input class="form-control" id="feedback-year-filter" name="year"
                    type="number" min="2000" max="2100"
                    value="<?= esc((string) ($filters['year'] ?? '')) ?>">
            </div>
            <div class="col-12 col-md-6 col-lg-4 mb-2 mcc-player-filter-field">
                <label for="feedback-quarter-filter">Feedback period</label>
                <select class="form-control" id="feedback-quarter-filter" name="quarter">
                    <option value="">All periods</option>
                    <?php foreach ($periodLabels as $quarter => $label): ?>
                        <option value="<?= $quarter ?>"
                            <?= (string) ($filters['quarter'] ?? '') === (string) $quarter ? 'selected' : '' ?>>
                            <?= esc($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php foreach (['created_from' => 'Created from', 'created_to' => 'Created to'] as $field => $label): ?>
                <div class="col-12 col-md-6 col-lg-4 mb-2 mcc-player-filter-field">
                    <label for="feedback-<?= esc($field) ?>"><?= esc($label) ?></label>
                    <input class="form-control" id="feedback-<?= esc($field) ?>"
                        name="<?= esc($field) ?>" type="date"
                        value="<?= esc((string) ($filters[$field] ?? '')) ?>">
                </div>
            <?php endforeach; ?>
            <div class="col-12 mt-2 text-right">
                <button class="mcc-button mcc-button-primary" type="submit">
                    <i class="fa-solid fa-filter" aria-hidden="true"></i>
                    Apply filters
                </button>
                <a class="mcc-button mcc-button-secondary"
                    href="<?= esc(site_url('player-feedback')) ?>">
                    <i class="fa-solid fa-rotate-left" aria-hidden="true"></i>
                    Clear filters
                </a>
            </div>
        </form>
    </section>

    <section class="mcc-card mcc-detail-card" aria-labelledby="player-feedback-heading">
        <div class="mcc-card-heading">
            <div>
                <span class="mcc-eyebrow">Coach workspace</span>
                <h2 id="player-feedback-heading">Coach–Student/Parent Feedbacks</h2>
            </div>
            <span class="mcc-muted">
                <?= (int) ($result['total'] ?? 0) ?>
                result<?= (int) ($result['total'] ?? 0) === 1 ? '' : 's' ?>
            </span>
        </div>

        <?php if ($feedbacks === []): ?>
            <div class="mcc-empty-state" role="status">
                <i class="fa-regular fa-comments" aria-hidden="true"></i>
                <h2>No feedback found</h2>
                <p>No quarterly feedback matches the selected filters.</p>
            </div>
        <?php else: ?>
            <div class="mcc-table-wrap mcc-table-responsive mcc-player-table-wrap">
                <table class="mcc-table mcc-player-table mcc-player-feedback-table">
                    <caption class="visually-hidden">Authorized coach feedback</caption>
                    <thead>
                        <tr>
                            <th scope="col">Player</th>
                            <th scope="col">Feedback period</th>
                            <th scope="col">Coach</th>
                            <th scope="col">Created / Updated</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($feedbacks as $feedback): ?>
                            <?php
                            $quarter = (int) ($feedback['feedback_quarter'] ?? 0);
                            $periodMonth = [1 => 'Sep', 2 => 'Dec', 3 => 'Mar', 4 => 'Jun'][$quarter] ?? 'Unknown';
                            $period = $periodMonth . ' ' . (int) ($feedback['feedback_year'] ?? 0) . ' Feedback';
                            $canManageFeedback = service('authorization')->can(
                                'player.feedback.manage',
                                (string) ($feedback['branch_id'] ?? 0),
                            );
                            $feedbackId = (int) ($feedback['id'] ?? 0);
                            $playerId = (int) ($feedback['player_id'] ?? 0);
                            $playerName = (string) ($feedback['player_name'] ?? '');
                            $branchName = (string) ($feedback['branch_name'] ?? '');
                            $coachName = (string) ($feedback['coach_name'] ?? '');
                            ?>
                            <tr>
                                <td data-label="Player">
                                    <div class="mcc-player-table-value-group mcc-feedback-table-group">
                                        <strong class="mcc-feedback-primary-value">
                                            <?= esc($playerName) ?>
                                        </strong>
                                        <span class="mcc-table-secondary-text">
                                            Player ID <?= $playerId ?>
                                        </span>
                                        <span class="mcc-table-secondary-text">
                                            <?= esc($branchName) ?>
                                        </span>
                                    </div>
                                </td>
                                <td data-label="Feedback period">
                                    <span class="mcc-badge mcc-feedback-period-badge">
                                        <?= esc($period) ?>
                                    </span>
                                </td>
                                <td data-label="Coach">
                                    <div class="mcc-player-table-value-group mcc-feedback-table-group">
                                        <strong><?= esc($coachName) ?></strong>
                                    </div>
                                </td>
                                <td data-label="Created / Updated">
                                    <div class="mcc-player-table-value-group mcc-feedback-table-group">
                                        <span class="mcc-table-secondary-text">
                                            Created <?= esc(mcc_user_datetime($feedback['created_at'] ?? null)) ?>
                                        </span>
                                        <span class="mcc-table-secondary-text">
                                            Updated <?= esc(mcc_user_datetime($feedback['updated_at'] ?? null)) ?>
                                        </span>
                                    </div>
                                </td>
                                <td data-label="Actions">
                                    <div class="mcc-player-action-menu"
                                        data-player-actions data-player-feedback-action-menu
                                        data-player-name="<?= esc($playerName) ?>">
                                        <button class="mcc-button mcc-button-secondary" type="button"
                                            data-player-actions-toggle aria-expanded="false"
                                            aria-haspopup="true"
                                            aria-controls="feedback-actions-<?= $feedbackId ?>">
                                            <i class="fa-solid fa-ellipsis-vertical" aria-hidden="true"></i>
                                            Actions
                                        </button>
                                        <div class="mcc-player-action-dropdown"
                                            id="feedback-actions-<?= $feedbackId ?>"
                                            data-player-actions-menu hidden>
                                            <a href="<?= esc(site_url('player-feedback/' . $feedbackId)) ?>">
                                                <i class="fa-solid fa-eye" aria-hidden="true"></i>
                                                View Feedback
                                            </a>
                                            <?php if ($canManageFeedback): ?>
                                                <a href="<?= esc(site_url('players/' . $playerId . '/feedback/new')) ?>">
                                                    <i class="fa-solid fa-plus" aria-hidden="true"></i>
                                                    Add Feedback
                                                </a>
                                            <?php endif; ?>
                                            <a href="<?= esc(site_url('player-feedback/annual?player_id=' . $playerId . '&year=' . (int) ($feedback['feedback_year'] ?? 0))) ?>">
                                                <i class="fa-solid fa-file-lines" aria-hidden="true"></i>
                                                Annual Feedback Report
                                            </a>
                                            <?php if ($canManageFeedback): ?>
                                                <a href="<?= esc(site_url('player-feedback/' . $feedbackId . '/edit')) ?>">
                                                    <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
                                                    Edit Feedback
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <?php if ($pageCount > 1): ?>
            <nav class="mcc-pagination" aria-label="Coach Feedback pages">
                <span>Page <?= $currentPage ?> of <?= $pageCount ?></span>
                <div>
                    <?php if ($currentPage > 1): ?>
                        <a class="mcc-button mcc-button-secondary"
                            href="<?= esc($pageUrl($currentPage - 1)) ?>">Previous</a>
                    <?php endif; ?>
                    <?php foreach ($pageWindow as $page): ?>
                        <?php if (is_string($page)): ?>
                            <span class="mcc-button mcc-button-secondary" aria-hidden="true">&hellip;</span>
                        <?php else: ?>
                            <a class="mcc-button mcc-button-secondary"
                                href="<?= esc($pageUrl((int) $page)) ?>"
                                <?= (int) $page === $currentPage ? 'aria-current="page"' : '' ?>>
                                <?= (int) $page ?>
                            </a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <?php if ($currentPage < $pageCount): ?>
                        <a class="mcc-button mcc-button-secondary"
                            href="<?= esc($pageUrl($currentPage + 1)) ?>">Next</a>
                    <?php endif; ?>
                </div>
            </nav>
        <?php endif; ?>
    </section>
</section>

<?= view('staff_footer') ?>
