<?php

$result = is_array($result ?? null) ? $result : [];
$filters = is_array($result['filters'] ?? null) ? $result['filters'] : [];
$players = is_array($result['players'] ?? null) ? $result['players'] : [];
$branches = is_array($result['branches'] ?? null) ? $result['branches'] : [];
$batches = is_array($result['batches'] ?? null) ? $result['batches'] : [];
$ageGroups = is_array($result['age_groups'] ?? null) ? $result['age_groups'] : [];
$coaches = is_array($result['coaches'] ?? null) ? $result['coaches'] : [];
$statusCounts = is_array($result['status_counts'] ?? null) ? $result['status_counts'] : [];
$currentPage = max(1, (int) ($result['page'] ?? 1));
$pageCount = max(1, (int) ($result['pages'] ?? 1));
$pageWindow = mcc_follow_up_page_window($currentPage, $pageCount);

$statusLabels = [
    'active' => 'Active',
    'inactive' => 'Inactive',
    'suspended' => 'Suspended',
    'left_academy' => 'Left academy',
    'alumni' => 'Alumni',
];

$queryFilters = array_filter(
    $filters,
    static fn ($value): bool => $value !== null && $value !== '' && $value !== 1,
);
unset($queryFilters['page']);
$summaryFilters = $queryFilters;
unset($summaryFilters['status']);

$pageUrl = static function (int $page) use ($queryFilters): string {
    $query = http_build_query(array_merge($queryFilters, ['page' => $page]));

    return site_url('players') . ($query === '' ? '' : '?' . $query);
};

$statusCards = [
    'active' => ['label' => 'Active', 'class' => 'mcc-player-status-active'],
    'inactive' => ['label' => 'Inactive', 'class' => 'mcc-player-status-inactive'],
    'suspended' => ['label' => 'Suspended', 'class' => 'mcc-player-status-suspended'],
    'left_academy' => ['label' => 'Left academy', 'class' => 'mcc-player-status-left_academy'],
    'alumni' => ['label' => 'Alumni', 'class' => 'mcc-player-status-alumni'],
];

$actionPermission = static function (string $permission, string $branchId): bool {
    return service('authorization')->can($permission, $branchId);
};
?>
<?= view('staff_header', [
    'title' => 'Players',
    'nav' => $nav,
    'message' => $message,
    'error' => $error,
    'pageActions' => '<div class="mcc-page-actions">
        <button class="mcc-button mcc-button-secondary" type="button" data-filter-toggle aria-controls="player-filters" aria-expanded="false">
            <i class="fa-solid fa-filter" aria-hidden="true"></i> Filters
        </button>
    </div>',
]) ?>

<section id="player-filters" class="mcc-card" aria-labelledby="player-filters-heading" hidden>
    <div class="mcc-card-heading">
        <div>
            <span class="mcc-eyebrow">Search and filter</span>
            <h2 id="player-filters-heading">Player filters</h2>
        </div>
    </div>
    <form method="get" action="<?= esc(site_url('players')) ?>" class="row mcc-player-filter-grid">
        <div class="col-12 col-md-6 col-lg-4 mb-2 mcc-player-filter-field">
            <label for="player-query">Player name or reference</label>
            <input class="form-control" id="player-query" name="q" type="search" maxlength="100" value="<?= esc((string) ($filters['q'] ?? '')) ?>">
        </div>
        <div class="col-12 col-md-6 col-lg-4 mb-2 mcc-player-filter-field">
            <label for="player-status">Status</label>
            <select class="form-control" id="player-status" name="status">
                <option value="">All statuses</option>
                <?php foreach ($statusLabels as $value => $label): ?>
                    <option value="<?= esc($value) ?>" <?= (string) ($filters['status'] ?? '') === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php if (count($branches) > 1): ?>
            <div class="col-12 col-md-6 col-lg-4 mb-2 mcc-player-filter-field">
                <label for="player-branch">Branch</label>
                <select class="form-control" id="player-branch" name="branch_id">
                    <option value="">All authorized branches</option>
                    <?php foreach ($branches as $branch): ?>
                        <option value="<?= (int) $branch['id'] ?>" <?= (string) ($filters['branch_id'] ?? '') === (string) $branch['id'] ? 'selected' : '' ?>><?= esc((string) $branch['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
        <div class="col-12 col-md-6 col-lg-4 mb-2 mcc-player-filter-field">
            <label for="player-batch">Batch</label>
            <select class="form-control" id="player-batch" name="batch_id">
                <option value="">All batches</option>
                <?php foreach ($batches as $batch): ?>
                    <option value="<?= (int) $batch['id'] ?>" <?= (string) ($filters['batch_id'] ?? '') === (string) $batch['id'] ? 'selected' : '' ?>><?= esc((string) $batch['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-md-6 col-lg-4 mb-2 mcc-player-filter-field">
            <label for="player-age-group">Age group</label>
            <select class="form-control" id="player-age-group" name="age_group">
                <option value="">All age groups</option>
                <?php foreach ($ageGroups as $ageGroup): ?>
                    <option value="<?= esc($ageGroup) ?>" <?= (string) ($filters['age_group'] ?? '') === $ageGroup ? 'selected' : '' ?>><?= esc($ageGroup) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-md-6 col-lg-4 mb-2 mcc-player-filter-field">
            <label for="player-coach">Assigned coach</label>
            <select class="form-control" id="player-coach" name="coach_id">
                <option value="">All coaches</option>
                <?php foreach ($coaches as $coach): ?>
                    <option value="<?= (int) $coach['id'] ?>" <?= (string) ($filters['coach_id'] ?? '') === (string) $coach['id'] ? 'selected' : '' ?>><?= esc((string) $coach['display_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 mt-2 text-right">
            <button class="mcc-button mcc-button-primary" type="submit"><i class="fa-solid fa-filter" aria-hidden="true"></i> Apply filters</button>
            <a class="mcc-button mcc-button-secondary" href="<?= esc(site_url('players')) ?>"><i class="fa-solid fa-rotate-left" aria-hidden="true"></i> Clear filters</a>
        </div>
    </form>
</section>

<section class="mcc-card mcc-admission-status-summary" aria-labelledby="player-status-summary-heading">
    <div class="mcc-card-heading">
        <div>
            <span class="mcc-eyebrow">Players</span>
            <h2 id="player-status-summary-heading">Status summary</h2>
        </div>
    </div>
    <div class="mcc-follow-up-summary-grid mcc-admission-status-summary-grid">
    <?php foreach ($statusCards as $status => $card): ?>
        <?php
        $statusQuery = array_merge($summaryFilters, ['status' => $status]);
        $statusUrl = site_url('players') . '?' . http_build_query($statusQuery);
        $isSelectedStatus = (string) ($filters['status'] ?? '') === $status;
        ?>
        <a class="mcc-follow-up-summary-card mcc-status-badge <?= esc($card['class']) ?><?= $isSelectedStatus ? ' is-active' : '' ?>"
           href="<?= esc($statusUrl) ?>" aria-current="<?= $isSelectedStatus ? 'page' : 'false' ?>">
            <span class="mcc-follow-up-summary-label"><?= esc($card['label']) ?></span>
            <strong class="mcc-follow-up-summary-count"><?= (int) ($statusCounts[$status] ?? 0) ?></strong>
        </a>
    <?php endforeach; ?>
    </div>
</section>

<section class="mcc-card mcc-detail-card mcc-player-list-card" aria-labelledby="players-heading">
    <div class="mcc-card-heading">
        <div>
            <span class="mcc-eyebrow">Player management</span>
            <h2 id="players-heading">Branch-scoped players</h2>
        </div>
        <span class="mcc-muted"><?= (int) ($result['total'] ?? 0) ?> result<?= (int) ($result['total'] ?? 0) === 1 ? '' : 's' ?></span>
    </div>

    <?php if ($players === []): ?>
        <div class="mcc-empty-state">No players match the selected filters.</div>
    <?php else: ?>
        <div class="mcc-table-wrap mcc-table-responsive mcc-player-table-wrap">
            <table class="mcc-table mcc-player-table">
                <caption class="visually-hidden">Authorized player list</caption>
                <thead>
                    <tr>
                        <th scope="col">Player</th>
                        <th scope="col">Placement</th>
                        <th scope="col">Assigned coaches</th>
                        <th scope="col">Status</th>
                        <th scope="col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($players as $player): ?>
                        <?php
                        $playerId = filter_var($player['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                        if ($playerId === false) {
                            continue;
                        }
                        $branchId = (string) ($player['branch_id'] ?? '');
                        $statusCode = (string) ($player['status'] ?? '');
                        $statusLabel = $statusLabels[$statusCode] ?? 'Unknown';
                        $statusClass = 'mcc-player-status-' . preg_replace('/[^a-z0-9_]+/i', '', $statusCode);
                        $playerName = trim((string) ($player['full_name'] ?? '')) ?: 'Unnamed player';
                        $reference = trim((string) ($player['player_reference'] ?? ''));
                        $branchName = trim((string) ($player['branch_name'] ?? '')) ?: 'Authorized branch';
                        $batchName = trim((string) ($player['batch_name'] ?? '')) ?: 'Unassigned';
                        $ageGroup = trim((string) ($player['age_group'] ?? '')) ?: 'Not provided';
                        $groupCoachName = trim((string) ($player['group_coach_name'] ?? '')) ?: 'Not assigned';
                        $personalCoachName = trim((string) ($player['personal_coach_name'] ?? '')) ?: 'Not assigned';
                        $canUpdatePlayer = $actionPermission('player.update', $branchId);
                        $canTransferPlayer = $actionPermission('player.update', $branchId) && count($branches) > 1;
                        $canBatchPlayer = $actionPermission('player.update', $branchId);
                        $canCoachPlayer = $actionPermission('player.update', $branchId);
                        $canViewPlayer360 = $actionPermission('player360.view', $branchId);
                        ?>
                        <tr>
                            <th scope="row" class="mcc-player-identity-cell">
                                <button class="mcc-lead-name mcc-player-list-name" type="button" data-player-action="view" data-player-id="<?= $playerId ?>" data-player-url="<?= esc(site_url('players/' . $playerId)) ?>" data-player-name="<?= esc($playerName) ?>"><?= esc($playerName) ?></button>
                                <span class="mcc-table-secondary-text">#<?= $playerId ?> · <?= esc($reference !== '' ? $reference : 'No reference') ?></span>
                                <?php if (! empty($player['email']) || ! empty($player['phone'])): ?>
                                    <span class="mcc-lead-contact mcc-player-contact-summary" aria-label="Player contact details">
                                        <?php if (! empty($player['email'])): ?>
                                            <span class="mcc-player-contact-line">
                                                <i class="fa-solid fa-envelope" aria-hidden="true"></i>
                                                <a href="mailto:<?= esc((string) $player['email']) ?>"><?= esc((string) $player['email']) ?></a>
                                            </span>
                                        <?php endif; ?>
                                        <?php if (! empty($player['phone'])): ?>
                                            <span class="mcc-player-contact-line">
                                                <i class="fa-solid fa-phone" aria-hidden="true"></i>
                                                <a href="tel:<?= esc((string) $player['phone']) ?>"><?= esc((string) $player['phone']) ?></a>
                                            </span>
                                        <?php endif; ?>
                                    </span>
                                <?php endif; ?>
                            </th>
                            <td class="mcc-player-placement-cell">
                                <span class="mcc-table-primary-text"><?= esc($branchName) ?></span>
                                <span class="mcc-table-secondary-text">Batch: <?= esc($batchName) ?></span>
                                <span class="mcc-table-secondary-text">Age group: <?= esc($ageGroup) ?></span>
                            </td>
                            <td>
                                <div class="mcc-player-coach-summary">
                                    <span class="mcc-player-coach-item">
                                        <strong>Group Coach</strong>
                                        <span class="mcc-player-coach-name"><?= esc($groupCoachName) ?></span>
                                    </span>
                                    <span class="mcc-player-coach-item">
                                        <strong>Personal Coach</strong>
                                        <span class="mcc-player-coach-name"><?= esc($personalCoachName) ?></span>
                                    </span>
                                </div>
                            </td>
                            <td class="mcc-player-status-cell">
                                <span class="mcc-badge mcc-status-badge <?= esc($statusClass) ?>"><?= esc($statusLabel) ?></span>
                            </td>
                            <td>
                                <div class="mcc-player-action-menu mcc-player-list-action-menu" data-player-actions data-player-name="<?= esc($playerName) ?>">
                                    <button class="mcc-button mcc-button-secondary" type="button" data-player-actions-toggle aria-expanded="false" aria-haspopup="true" aria-controls="player-actions-<?= $playerId ?>"><i class="fa-solid fa-ellipsis-vertical" aria-hidden="true"></i> Actions</button>
                                    <div class="mcc-player-action-dropdown" id="player-actions-<?= $playerId ?>" data-player-actions-menu hidden>
                                        <button type="button" data-player-action="view" data-player-id="<?= $playerId ?>" data-player-url="<?= esc(site_url('players/' . $playerId)) ?>"><i class="fa-solid fa-eye" aria-hidden="true"></i> View details</button>
                                        <?php if ($canUpdatePlayer): ?>
                                            <button type="button" data-player-action="profile" data-player-id="<?= $playerId ?>"><i class="fa-solid fa-pen-to-square" aria-hidden="true"></i> Edit player</button>
                                            <button type="button" data-player-action="status" data-player-id="<?= $playerId ?>"><i class="fa-solid fa-toggle-on" aria-hidden="true"></i> Update status</button>
                                            <button type="button" data-player-action="contacts" data-player-id="<?= $playerId ?>"><i class="fa-solid fa-address-book" aria-hidden="true"></i> Manage contacts</button>
                                        <?php endif; ?>
                                        <?php if ($canTransferPlayer): ?>
                                            <button type="button" data-player-action="branch" data-player-id="<?= $playerId ?>">
                                                <i class="fa-solid fa-code-branch" aria-hidden="true"></i> Assign branch
                                            </button>
                                        <?php endif; ?>
                                        <?php if ($canBatchPlayer): ?>
                                            <button type="button" data-player-action="batch" data-player-id="<?= $playerId ?>">
                                                <i class="fa-solid fa-people-group" aria-hidden="true"></i> Assign batch
                                            </button>
                                        <?php endif; ?>
                                        <?php if ($canCoachPlayer): ?>
                                            <button type="button" data-player-action="coaches" data-player-id="<?= $playerId ?>">
                                                <i class="fa-solid fa-users-gear" aria-hidden="true"></i> Update Coaches
                                            </button>
                                        <?php endif; ?>
                                        <?php if ($canViewPlayer360): ?>
                                            <button type="button" data-player-action="360" data-player-id="<?= $playerId ?>">
                                                <i class="fa-solid fa-chart-line" aria-hidden="true"></i> Player 360&deg;
                                            </button>
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
        <nav class="mcc-pagination" aria-label="Player pages">
            <span>Page <?= $currentPage ?> of <?= $pageCount ?></span>
            <div>
                <?php if ($currentPage > 1): ?><a class="mcc-button mcc-button-secondary" href="<?= esc($pageUrl($currentPage - 1)) ?>">Previous</a><?php endif; ?>
                <?php foreach ($pageWindow as $page): ?>
                    <?php if ($page === '…'): ?>
                        <span class="mcc-button mcc-button-secondary" aria-hidden="true">…</span>
                    <?php else: ?>
                        <a class="mcc-button mcc-button-secondary" href="<?= esc($pageUrl((int) $page)) ?>" <?= $page === $currentPage ? 'aria-current="page"' : '' ?>><?= (int) $page ?></a>
                    <?php endif; ?>
                <?php endforeach; ?>
                <?php if ($currentPage < $pageCount): ?><a class="mcc-button mcc-button-secondary" href="<?= esc($pageUrl($currentPage + 1)) ?>">Next</a><?php endif; ?>
            </div>
        </nav>
    <?php endif; ?>
</section>

<div class="mcc-modal mcc-player-modal" data-player-modal data-player-base-url="<?= esc(site_url('players')) ?>" hidden aria-hidden="true">
    <div class="mcc-modal-dialog mcc-player-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="player-modal-title">
        <div class="mcc-modal-heading">
            <div><span class="mcc-eyebrow">Player management</span><h2 id="player-modal-title" data-player-modal-title>Player action</h2></div>
            <button class="mcc-modal-close" type="button" data-player-modal-close aria-label="Close player action"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
        </div>
        <div class="mcc-modal-body mcc-player-modal-body" data-player-modal-body><div class="mcc-player-modal-loading" data-player-modal-loading hidden><i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i> Loading&hellip;</div></div>
    </div>
</div>

<?= view('staff_footer') ?>
