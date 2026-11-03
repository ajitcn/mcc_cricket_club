<?php

$filters = is_array($filters ?? null) ? $filters : [];
$followUps = is_array($followUps ?? null) ? $followUps : [];
$branches = is_array($branches ?? null) ? $branches : [];
$admissionTests = is_array($admissionTests ?? null) ? $admissionTests : [];
$assignedUsers = is_array($assignedUsers ?? null) ? $assignedUsers : [];
$options = is_array($options ?? null) ? $options : ['types' => [], 'statuses' => []];
$currentPage = max(1, (int) ($page ?? 1));
$pageCount = max(1, (int) ($pages ?? 1));
$queryFilters = array_filter(
    $filters,
    static fn ($value): bool => $value !== null && $value !== '' && $value !== [],
);
$activeFilterCount = count(array_diff_key($queryFilters, ['page' => true]));
$pageWindow = mcc_follow_up_page_window($currentPage, $pageCount);

$pageUrl = static function (int $targetPage) use ($queryFilters): string {
    $query = http_build_query(array_merge($queryFilters, ['page' => $targetPage]));

    return site_url('meeting-followups') . ($query === '' ? '' : '?' . $query);
};

$statusUrl = static function (?string $status) use ($queryFilters): string {
    $linkFilters = $queryFilters;
    unset($linkFilters['status'], $linkFilters['page']);

    if ($status !== null) {
        $linkFilters['status'] = $status;
    }

    $query = http_build_query($linkFilters);

    return site_url('meeting-followups') . ($query === '' ? '' : '?' . $query);
};

$statusCounts = [
    'all' => count($followUps),
    'pending' => 0,
    'scheduled' => 0,
    'completed' => 0,
    'cancelled' => 0,
];

foreach ($followUps as $followUp) {
    $status = strtolower((string) ($followUp['status'] ?? ''));

    if (array_key_exists($status, $statusCounts)) {
        $statusCounts[$status]++;
    }
}

$summaryCards = [
    ['key' => 'all', 'label' => 'All', 'class' => 'mcc-follow-up-status-all', 'status' => null],
    ['key' => 'pending', 'label' => 'Pending', 'class' => 'mcc-follow-up-status-pending', 'status' => 'pending'],
    ['key' => 'scheduled', 'label' => 'Scheduled', 'class' => 'mcc-follow-up-status-scheduled', 'status' => 'scheduled'],
    ['key' => 'completed', 'label' => 'Completed', 'class' => 'mcc-follow-up-status-completed', 'status' => 'completed'],
    ['key' => 'cancelled', 'label' => 'Cancelled', 'class' => 'mcc-follow-up-status-cancelled', 'status' => 'cancelled'],
];
$selectedStatus = (string) ($filters['status'] ?? '');
$selectedSummary = $selectedStatus === '' ? 'all' : $selectedStatus;

$pageActions = '<div class="mcc-page-actions">
    <button class="mcc-button mcc-button-secondary" type="button" data-filter-toggle
        data-show-label="Show filters" data-hide-label="Hide filters"
        aria-controls="meeting-follow-up-filters" aria-expanded="false">
        <i class="fa-solid fa-filter" aria-hidden="true"></i>
        <span class="mcc-button-label">Show filters</span>'
        . ($activeFilterCount > 0
            ? '<span class="mcc-filter-count" aria-label="' . $activeFilterCount . ' active filters">'
                . $activeFilterCount . '</span>'
            : '') . '
    </button>
    <a class="mcc-button mcc-button-primary" href="' . site_url('meeting-followups/new') . '">
        <i class="fa-solid fa-plus" aria-hidden="true"></i> Add follow-up
    </a>
</div>';
?>
<?= view('staff_header', [
    'title' => 'Meeting Follow-up',
    'nav' => $nav,
    'message' => $message ?? null,
    'error' => $error ?? null,
    'pageActions' => $pageActions,
    'breadcrumbTrail' => [
        ['label' => 'CRM', 'url' => site_url('crm')],
        ['label' => 'Admission Test', 'url' => site_url('admission-tests')],
        ['label' => 'Meeting Follow-up'],
    ],
]) ?>

<section class="mcc-card mcc-follow-up-summary" aria-labelledby="meeting-follow-up-summary-heading">
    <div class="mcc-card-heading">
        <div>
            <span class="mcc-eyebrow">Admission Test</span>
            <h2 id="meeting-follow-up-summary-heading">Meeting Follow-up</h2>
        </div>
    </div>
    <div class="mcc-follow-up-summary-grid">
        <?php foreach ($summaryCards as $card): ?>
            <?php $isActive = $selectedSummary === $card['key']; ?>
            <a
                class="mcc-follow-up-summary-card mcc-status-badge <?= esc($card['class']) ?><?= $isActive ? ' is-active' : '' ?>"
                href="<?= esc($statusUrl($card['status'])) ?>"
                aria-label="Show <?= esc(strtolower($card['label'])) ?> meeting follow-ups"
                aria-current="<?= $isActive ? 'page' : 'false' ?>"
            >
                <span class="mcc-follow-up-summary-label"><?= esc($card['label']) ?></span>
                <strong class="mcc-follow-up-summary-count"><?= (int) $statusCounts[$card['key']] ?></strong>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<section
    id="meeting-follow-up-filters"
    class="mcc-card mcc-follow-up-filters"
    aria-labelledby="meeting-follow-up-filters-heading"
    hidden
>
    <div class="mcc-card-heading">
        <div>
            <span class="mcc-eyebrow">Search and filter</span>
            <h2 id="meeting-follow-up-filters-heading">Find a meeting follow-up</h2>
        </div>
    </div>
    <form method="get" action="<?= site_url('meeting-followups') ?>" class="row mcc-follow-up-filter-form">
        <div class="col-12 col-md-6 col-xl-4 mb-3">
            <label for="meeting-applicant">Applicant</label>
            <input class="form-control" id="meeting-applicant" name="applicant" type="search" maxlength="100" value="<?= esc((string) ($filters['applicant'] ?? '')) ?>">
        </div>
        <div class="col-12 col-md-6 col-xl-4 mb-3">
            <label for="meeting-test">Admission Test reference</label>
            <select class="form-control" id="meeting-test" name="admission_test_id">
                <option value="">All Admission Tests</option>
                <?php foreach ($admissionTests as $test): ?>
                    <option value="<?= (int) $test['id'] ?>" <?= (string) ($filters['admission_test_id'] ?? '') === (string) $test['id'] ? 'selected' : '' ?>>Test #<?= (int) $test['id'] ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-md-6 col-xl-4 mb-3">
            <label for="meeting-trial">Trial reference</label>
            <input class="form-control" id="meeting-trial" name="trial_id" inputmode="numeric" value="<?= esc((string) ($filters['trial_id'] ?? '')) ?>">
        </div>
        <div class="col-12 col-md-6 col-xl-4 mb-3">
            <label for="meeting-branch">Branch</label>
            <select class="form-control" id="meeting-branch" name="branch_id">
                <option value="">All authorized branches</option>
                <?php foreach ($branches as $branch): ?>
                    <option value="<?= (int) $branch['id'] ?>" <?= (string) ($filters['branch_id'] ?? '') === (string) $branch['id'] ? 'selected' : '' ?>><?= esc((string) $branch['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-md-6 col-xl-4 mb-3">
            <label for="meeting-status">Status</label>
            <select class="form-control" id="meeting-status" name="status">
                <option value="">All statuses</option>
                <?php foreach ($options['statuses'] as $status): ?>
                    <option value="<?= esc($status) ?>" <?= ($filters['status'] ?? '') === $status ? 'selected' : '' ?>><?= esc(ucwords(str_replace('_', ' ', $status))) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-md-6 col-xl-4 mb-3">
            <label for="meeting-assigned">Assigned user</label>
            <select class="form-control" id="meeting-assigned" name="assigned_user_id">
                <option value="">All assigned users</option>
                <?php foreach ($assignedUsers as $user): ?>
                    <option value="<?= (int) $user['id'] ?>" <?= (string) ($filters['assigned_user_id'] ?? '') === (string) $user['id'] ? 'selected' : '' ?>><?= esc((string) $user['display_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-md-6 col-xl-3 mb-3">
            <label for="meeting-from">Start date</label>
            <input class="form-control" id="meeting-from" name="date_from" type="text" inputmode="numeric" placeholder="DD-MM-YYYY" autocomplete="off" data-mcc-date-picker data-mcc-date-picker-no-toggle data-mcc-date-format="d-m-Y" data-mcc-date-submit-format="Y-m-d" value="<?= esc(mcc_user_date_input($filters['date_from'] ?? null)) ?>">
        </div>
        <div class="col-12 col-md-6 col-xl-3 mb-3">
            <label for="meeting-to">End date</label>
            <input class="form-control" id="meeting-to" name="date_to" type="text" inputmode="numeric" placeholder="DD-MM-YYYY" autocomplete="off" data-mcc-date-picker data-mcc-date-picker-no-toggle data-mcc-date-format="d-m-Y" data-mcc-date-submit-format="Y-m-d" value="<?= esc(mcc_user_date_input($filters['date_to'] ?? null)) ?>">
        </div>
        <div class="col-12 mb-3 mcc-follow-up-filter-actions">
            <button class="mcc-button mcc-button-primary" type="submit"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i> Search</button>
            <a class="mcc-button mcc-button-secondary" href="<?= site_url('meeting-followups') ?>"><i class="fa-solid fa-rotate-left" aria-hidden="true"></i> Clear filters</a>
        </div>
    </form>
</section>

<section class="mcc-card mcc-detail-card" aria-labelledby="meeting-follow-up-list-heading">
    <div class="mcc-card-heading">
        <div>
            <span class="mcc-eyebrow">History</span>
            <h2 id="meeting-follow-up-list-heading">Meeting Follow-up history</h2>
        </div>
        <span class="mcc-muted"><?= count($followUps) ?> shown</span>
    </div>
    <?php if ($followUps === []): ?>
        <p class="mcc-empty-state">No Meeting Follow-up records match the current filters.</p>
    <?php else: ?>
        <div class="mcc-table-wrap mcc-meeting-follow-up-table-wrap">
            <table class="table mcc-table">
                <caption class="visually-hidden">Meeting Follow-up records</caption>
                <thead>
                    <tr>
                        <th scope="col">Admission Test</th>
                        <th scope="col">Applicant</th>
                        <th scope="col">Trial</th>
                        <th scope="col">Branch</th>
                        <th scope="col">Follow-up Type</th>
                        <th scope="col">Status</th>
                        <th scope="col">Start Date</th>
                        <th scope="col">Assigned</th>
                        <th scope="col">Next Action</th>
                        <th scope="col">Updated</th>
                        <th scope="col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($followUps as $followUp): ?>
                        <?php
                        $followUpId = (int) ($followUp['id'] ?? 0);
                        $branchId = (string) ($followUp['branch_id'] ?? '');
                        $testReference = 'Test #' . (int) ($followUp['admission_test_id'] ?? 0);
                        $trialReference = 'Trial #' . (int) ($followUp['trial_id'] ?? 0);
                        $statusPresentation = mcc_follow_up_status_presentation((string) ($followUp['status'] ?? ''));
                        $canView = service('authorization')->can('admission.view', $branchId);
                        $canEdit = service('authorization')->can('admission.update', $branchId);
                        ?>
                        <tr>
                            <td class="mcc-meeting-follow-up-reference">
                                <a href="<?= site_url('admission-tests/' . (int) ($followUp['admission_test_id'] ?? 0)) ?>"><?= esc($testReference) ?></a>
                                <?php if (! empty($followUp['admission_number'])): ?>
                                    <span class="mcc-table-secondary-text"><?= esc((string) $followUp['admission_number']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="mcc-meeting-follow-up-applicant"><?= esc((string) ($followUp['applicant_full_name'] ?? 'Applicant')) ?></td>
                            <td><a href="<?= site_url('crm/trials/' . (int) ($followUp['trial_id'] ?? 0)) ?>"><?= esc($trialReference) ?></a></td>
                            <td><?= esc((string) ($followUp['branch_name'] ?? '')) ?></td>
                            <td><?= esc(ucwords(str_replace('_', ' ', (string) ($followUp['follow_up_type'] ?? '')))) ?></td>
                            <td><span class="mcc-badge mcc-status-badge <?= esc($statusPresentation['class']) ?>"><?= esc($statusPresentation['label']) ?></span></td>
                            <td><?= esc(mcc_follow_up_datetime($followUp['start_at'] ?? null, '—')) ?></td>
                            <td><?= esc((string) ($followUp['assigned_user_name'] ?: 'Unassigned')) ?></td>
                            <td class="mcc-meeting-follow-up-next-action"><?= esc((string) ($followUp['next_action'] ?: '—')) ?></td>
                            <td><?= esc(mcc_follow_up_datetime($followUp['updated_at'] ?? null, '—')) ?></td>
                            <td>
                                <div class="mcc-follow-up-action-menu" data-follow-up-actions>
                                    <button class="mcc-button mcc-button-secondary" type="button" data-follow-up-actions-toggle aria-controls="meeting-follow-up-actions-<?= $followUpId ?>" aria-expanded="false" aria-haspopup="true" aria-label="Actions for <?= esc($testReference) ?>">
                                        <i class="fa-solid fa-ellipsis-vertical" aria-hidden="true"></i>
                                        <span class="mcc-button-label">Actions</span>
                                    </button>
                                    <div class="mcc-follow-up-action-dropdown" id="meeting-follow-up-actions-<?= $followUpId ?>" data-follow-up-actions-menu role="menu" hidden>
                                        <?php if ($canView): ?>
                                            <a role="menuitem" href="<?= site_url('meeting-followups/' . $followUpId) ?>"><i class="fa-solid fa-eye" aria-hidden="true"></i> View</a>
                                        <?php endif; ?>
                                        <?php if ($canEdit): ?>
                                            <a role="menuitem" href="<?= site_url('meeting-followups/' . $followUpId . '/edit') ?>"><i class="fa-solid fa-pen-to-square" aria-hidden="true"></i> Edit</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($pageCount > 1): ?>
            <nav class="mcc-pagination" aria-label="Meeting Follow-up pages">
                <span>Page <?= $currentPage ?> of <?= $pageCount ?></span>
                <div class="mcc-pagination-actions">
                    <?php if ($currentPage > 1): ?>
                        <a class="mcc-button mcc-button-secondary" href="<?= esc($pageUrl($currentPage - 1)) ?>">Previous</a>
                    <?php else: ?>
                        <span class="mcc-button mcc-button-secondary" aria-disabled="true">Previous</span>
                    <?php endif; ?>
                    <?php foreach ($pageWindow as $pageToken): ?>
                        <?php if ($pageToken === '…'): ?>
                            <span class="mcc-pagination-ellipsis" aria-hidden="true">…</span>
                        <?php else: ?>
                            <a class="mcc-button <?= $pageToken === $currentPage ? 'mcc-button-primary' : 'mcc-button-secondary' ?>" href="<?= esc($pageUrl((int) $pageToken)) ?>" <?= $pageToken === $currentPage ? 'aria-current="page"' : '' ?>><?= (int) $pageToken ?></a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <?php if ($currentPage < $pageCount): ?>
                        <a class="mcc-button mcc-button-secondary" href="<?= esc($pageUrl($currentPage + 1)) ?>">Next</a>
                    <?php else: ?>
                        <span class="mcc-button mcc-button-secondary" aria-disabled="true">Next</span>
                    <?php endif; ?>
                </div>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</section>
<?= view('staff_footer') ?>
