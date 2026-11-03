<?php

$filters = is_array($filters ?? null) ? $filters : [];
$followUps = is_array($followUps ?? null) ? $followUps : [];
$branches = is_array($branches ?? null) ? $branches : [];
$admissions = is_array($admissions ?? null) ? $admissions : [];
$assignedUsers = is_array($assignedUsers ?? null) ? $assignedUsers : [];
$options = is_array($options ?? null) ? $options : ['types' => [], 'statuses' => [], 'filter_statuses' => []];
$summaryCounts = is_array($summaryCounts ?? null) ? $summaryCounts : [];
$filterErrors = is_array($filterErrors ?? null) ? $filterErrors : [];
$currentPage = (int) ($page ?? 1);
$queryFilters = array_filter($filters, static fn ($value): bool => $value !== null && $value !== '');
$actionFilters = $queryFilters;
unset($actionFilters['page']);
$actionUrl = static function (string $path, array $extra = []) use ($actionFilters): string {
    $query = http_build_query(array_merge($actionFilters, $extra));

    return site_url($path) . ($query === '' ? '' : '?' . $query);
};
$activeFilterCount = count($queryFilters);
$pageCount = (int) ($pages ?? 1);
$pageWindow = mcc_follow_up_page_window($currentPage, $pageCount);
$pageUrl = static function (int $page) use ($queryFilters): string {
    $query = http_build_query(array_merge($queryFilters, ['page' => $page]));

    return site_url('admissions/followups') . ($query === '' ? '' : '?' . $query);
};
$statusUrl = static function (?string $status, ?string $nextDue = null) use ($queryFilters): string {
    $linkFilters = $queryFilters;
    unset($linkFilters['status'], $linkFilters['next_due'], $linkFilters['page']);
    if ($status !== null) {
        $linkFilters['status'] = $status;
    }
    if ($nextDue !== null) {
        $linkFilters['next_due'] = $nextDue;
    }
    $query = http_build_query($linkFilters);

    return site_url('admissions/followups') . ($query === '' ? '' : '?' . $query);
};
$displayDate = static function ($value): string {
    return mcc_follow_up_datetime(
        $value === null ? null : (string) $value,
        '—',
    );
};
$summaryCards = [
    ['key' => 'all', 'label' => 'All Follow-ups', 'status' => null, 'nextDue' => null, 'class' => 'mcc-follow-up-status-all'],
    ['key' => 'pending', 'label' => 'Pending', 'status' => 'pending', 'nextDue' => null, 'class' => 'mcc-follow-up-status-pending'],
    ['key' => 'scheduled', 'label' => 'Scheduled', 'status' => 'scheduled', 'nextDue' => null, 'class' => 'mcc-follow-up-status-scheduled'],
    ['key' => 'completed', 'label' => 'Completed', 'status' => 'completed', 'nextDue' => null, 'class' => 'mcc-follow-up-status-completed'],
    ['key' => 'cancelled', 'label' => 'Cancelled', 'status' => 'cancelled', 'nextDue' => null, 'class' => 'mcc-follow-up-status-cancelled'],
    ['key' => 'overdue', 'label' => 'Overdue', 'status' => 'pending', 'nextDue' => 'overdue', 'class' => 'mcc-follow-up-status-overdue'],
];
$selectedSummary = ($filters['next_due'] ?? '') === 'scheduled'
    ? 'scheduled'
    : (($filters['next_due'] ?? '') === 'overdue' ? 'overdue' : (($filters['status'] ?? '') ?: 'all'));
?>
<?php
$pageActions = '<div class="mcc-page-actions">
    <button class="mcc-button mcc-button-secondary" type="button" data-filter-toggle
        data-show-label="Show filters" data-hide-label="Hide filters"
        aria-controls="admission-follow-up-filters" aria-expanded="false">
        <span class="mcc-button-label">Show filters</span>'
        . ($activeFilterCount > 0 ? '<span class="mcc-filter-count" aria-label="' . $activeFilterCount . ' active filters">' . $activeFilterCount . '</span>' : '') . '
    </button>
    ' . (! empty($canCreate) ? '<a class="mcc-button mcc-button-primary" href="' . site_url('admissions/followups/new') . '">Add follow-up</a>' : '') . '
</div>';
?>
<?= view('staff_header', ['title' => 'Admission Follow-ups', 'nav' => $nav, 'message' => $message, 'error' => $error, 'pageActions' => $pageActions]) ?>
<section class="mcc-card mcc-follow-up-summary" aria-labelledby="admission-follow-up-summary-heading">
    <div class="mcc-card-heading">
        <div>
            <span class="mcc-eyebrow">Admissions</span>
            <h1 id="admission-follow-up-summary-heading">Admission Follow-ups</h1>
        </div>
    </div>
    <div class="mcc-follow-up-summary-grid">
        <?php foreach ($summaryCards as $card): ?>
            <?php $isActive = $selectedSummary === $card['key']; ?>
            <a class="mcc-follow-up-summary-card mcc-status-badge <?= esc($card['class']) ?><?= $isActive ? ' is-active' : '' ?>" href="<?= esc($statusUrl($card['status'], $card['nextDue'])) ?>" aria-label="<?= $card['key'] === 'all' ? 'Show all follow-ups' : 'Show ' . esc(strtolower($card['label'])) . ' follow-ups' ?>" aria-current="<?= $isActive ? 'page' : 'false' ?>">
                <span class="mcc-follow-up-summary-label"><?= esc($card['label']) ?></span>
                <strong class="mcc-follow-up-summary-count"><?= (int) ($summaryCounts[$card['key']] ?? 0) ?></strong>
            </a>
        <?php endforeach; ?>
    </div>
</section>
<section id="admission-follow-up-filters" class="mcc-card mcc-follow-up-filters" aria-labelledby="admission-follow-up-filters-heading" hidden>
    <div class="mcc-card-heading">
        <div>
            <span class="mcc-eyebrow">Search and filter</span>
            <h2 id="admission-follow-up-filters-heading">Find a follow-up</h2>
        </div>
    </div>
    <form method="get" action="<?= site_url('admissions/followups') ?>" class="row">
        <div class="col-12 col-md-4 mb-3">
            <label for="followup-admission">Admission</label>
            <select class="form-control" id="followup-admission" name="admission_id">
                <option value="">All Admissions</option>
                <?php foreach ($admissions as $admission): ?>
                    <option value="<?= (int) $admission['id'] ?>" <?= (string) ($filters['admission_id'] ?? '') === (string) $admission['id'] ? 'selected' : '' ?>><?= esc((string) (($admission['admission_number'] ?: ('Admission #' . $admission['id'])) . ' — ' . $admission['applicant_full_name'])) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-md-4 mb-3">
            <label for="followup-applicant">Search Applicant</label>
            <input
                class="form-control"
                id="followup-applicant"
                name="applicant"
                type="search"
                maxlength="100"
                value="<?= esc((string) ($filters['applicant'] ?? '')) ?>"
            >
        </div>
        <div class="col-12 col-md-4 mb-3">
            <label for="followup-branch">Branch</label>
            <select class="form-control" id="followup-branch" name="branch_id">
                <option value="">All authorized branches</option>
                <?php foreach ($branches as $branch): ?>
                    <option value="<?= (int) $branch['id'] ?>" <?= (string) ($filters['branch_id'] ?? '') === (string) $branch['id'] ? 'selected' : '' ?>><?= esc((string) $branch['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-md-4 mb-3">
            <label for="followup-status">Status</label>
            <select class="form-control" id="followup-status" name="status">
                <option value="">All statuses</option>
                <?php foreach (($options['filter_statuses'] ?? $options['statuses']) as $status): ?>
                    <option value="<?= esc($status) ?>" <?= ($filters['status'] ?? '') === $status ? 'selected' : '' ?>><?= esc(ucwords(str_replace('_', ' ', $status))) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-md-4 mb-3">
            <label for="followup-type">Follow-up type</label>
            <select class="form-control" id="followup-type" name="follow_up_type">
                <option value="">All types</option>
                <?php foreach ($options['types'] as $type): ?>
                    <option value="<?= esc($type) ?>" <?= ($filters['follow_up_type'] ?? '') === $type ? 'selected' : '' ?>><?= esc(ucwords(str_replace('_', ' ', $type))) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-md-4 mb-3">
            <label for="followup-assigned">Assigned user</label>
            <select class="form-control" id="followup-assigned" name="assigned_user_id">
                <option value="">All assigned users</option>
                <?php foreach ($assignedUsers as $user): ?>
                    <option value="<?= (int) $user['id'] ?>" <?= (string) ($filters['assigned_user_id'] ?? '') === (string) $user['id'] ? 'selected' : '' ?>><?= esc((string) $user['display_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-md-2 mb-3">
            <label for="followup-date-from">Follow-up Start Date</label>
            <input
                class="form-control"
                id="followup-date-from"
                name="date_from"
                type="text"
                inputmode="numeric"
                placeholder="DD-MM-YYYY"
                autocomplete="off"
                data-mcc-date-picker
                data-mcc-date-picker-no-toggle
                data-mcc-date-format="d-m-Y"
                data-mcc-date-submit-format="Y-m-d"
                value="<?= esc(mcc_user_date_input($filters['date_from'] ?? null)) ?>"
            >
            <?php if (($filterErrors['date_from'] ?? '') !== ''): ?>
                <p class="mcc-field-error" role="alert">
                    <?= esc($filterErrors['date_from']) ?>
                </p>
            <?php endif; ?>
        </div>
        <div class="col-12 col-md-2 mb-3">
            <label for="followup-date-to">Follow-up End Date</label>
            <input
                class="form-control"
                id="followup-date-to"
                name="date_to"
                type="text"
                inputmode="numeric"
                placeholder="DD-MM-YYYY"
                autocomplete="off"
                data-mcc-date-picker
                data-mcc-date-picker-no-toggle
                data-mcc-date-format="d-m-Y"
                data-mcc-date-submit-format="Y-m-d"
                value="<?= esc(mcc_user_date_input($filters['date_to'] ?? null)) ?>"
            >
            <?php if (($filterErrors['date_to'] ?? '') !== ''): ?>
                <p class="mcc-field-error" role="alert">
                    <?= esc($filterErrors['date_to']) ?>
                </p>
            <?php endif; ?>
        </div>
        <div class="col-12 col-md-2 mb-3 d-flex align-items-end">
            <label><input type="checkbox" name="next_due" value="1" <?= ($filters['next_due'] ?? '') === '1' ? 'checked' : '' ?>> Next due</label>
        </div>
        <div class="col-12 mb-3">
            <button class="mcc-button mcc-button-primary" type="submit">Apply filters</button>
            <a class="mcc-button mcc-button-secondary" href="<?= site_url('admissions/followups') ?>">Clear filters</a>
        </div>
    </form>
</section>
<section class="mcc-card mcc-detail-card" aria-labelledby="admission-follow-up-list-heading">
    <div class="mcc-card-heading">
        <h2 id="admission-follow-up-list-heading">Follow-up history</h2>
    </div>
    <?php if ($followUps === []): ?>
        <p class="mcc-empty-state">No Admission follow-ups match the current filters.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table mcc-table">
                <caption class="visually-hidden">Admission follow-up records</caption>
                <thead>
                    <tr><th>Admission</th><th>Follow-up</th><th>Type</th><th>Status</th><th>Assigned</th><th>Updated</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($followUps as $followUp): ?>
                        <tr>
                            <td class="mcc-follow-up-admission-cell">
                                <span class="mcc-follow-up-applicant-name"><?= esc((string) ($followUp['applicant_name'] ?: 'Applicant')) ?></span>
                                <span>#<?= esc((string) ($followUp['admission_number'] ?: 'Missing number')) ?></span>
                                <span><?= esc((string) $followUp['branch_name']) ?></span>
                            </td>
                            <td><?= esc($displayDate($followUp['follow_up_at'])) ?></td>
                            <td><?= esc(ucwords(str_replace('_', ' ', (string) $followUp['follow_up_type']))) ?></td>
                            <?php
                            $statusPresentation = mcc_follow_up_status_presentation(
                                (string) ($followUp['status'] ?? ''),
                                (string) ($followUp['next_follow_up_at'] ?? ''),
                            );
                            ?>
                            <td>
                                <span class="mcc-badge mcc-status-badge <?= esc($statusPresentation['class']) ?>">
                                    <?= esc($statusPresentation['label']) ?>
                                </span>
                            </td>
                            <td><?= esc((string) ($followUp['assigned_user_name'] ?: 'Unassigned')) ?></td>
                            <td><?= esc($displayDate($followUp['updated_at'])) ?></td>
                            <td>
                                <?php
                                $followUpId = (int) $followUp['id'];
                                $admissionId = (int) $followUp['admission_id'];
                                $branchId = (string) $followUp['branch_id'];
                                $admissionListQuery = array_filter([
                                    'admission_number' => trim((string) ($followUp['admission_number'] ?? '')),
                                    'q' => trim((string) ($followUp['applicant_name'] ?? '')),
                                ], static fn ($value): bool => $value !== '');
                                $admissionListQuery = array_merge($actionFilters, $admissionListQuery);
                                $admissionListUrl = site_url('admissions') . (
                                    $admissionListQuery === []
                                        ? ''
                                        : '?' . http_build_query($admissionListQuery)
                                );
                                $canViewBranch = service('authorization')->can('admission.view', $branchId);
                                $canUpdateBranch = service('authorization')->can('admission.update', $branchId);
                                $canCreateBranch = service('authorization')->can('admission.create', $branchId);
                                ?>
                                <div class="mcc-follow-up-action-menu" data-follow-up-actions>
                                    <button class="mcc-button mcc-button-secondary" type="button" data-follow-up-actions-toggle aria-controls="follow-up-actions-menu-<?= $followUpId ?>" aria-expanded="false" aria-haspopup="true" aria-label="Actions for <?= esc((string) ($followUp['admission_number'] ?: 'admission')) ?>">Actions <span aria-hidden="true">▾</span></button>
                                    <div class="mcc-follow-up-action-dropdown" id="follow-up-actions-menu-<?= $followUpId ?>" data-follow-up-actions-menu role="menu" hidden>
                                        <?php if ($canViewBranch): ?>
                                            <a role="menuitem" href="<?= esc($actionUrl('admissions/followups/' . $followUpId)) ?>">View Follow-up</a>
                                            <a role="menuitem" href="<?= esc($admissionListUrl) ?>">View Admission</a>
                                        <?php endif; ?>
                                        <?php if ($canUpdateBranch): ?>
                                            <a role="menuitem" href="<?= esc($actionUrl('admissions/followups/' . $followUpId . '/edit')) ?>">Edit Follow-up</a>
                                        <?php endif; ?>
                                        <?php if ($canCreateBranch): ?>
                                            <a role="menuitem" href="<?= esc($actionUrl('admissions/followups/new', ['admission_id' => $admissionId])) ?>">Add Follow-up</a>
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
            <nav class="mcc-pagination" aria-label="Admission follow-up pages">
                <span>Page <?= $currentPage ?> of <?= $pageCount ?></span>
                <div class="mcc-pagination-actions">
                    <?php if ($currentPage > 1): ?>
                        <a
                            class="mcc-button mcc-button-secondary"
                            href="<?= esc($pageUrl($currentPage - 1)) ?>"
                        >
                            Previous
                        </a>
                    <?php else: ?>
                        <span class="mcc-button mcc-button-secondary" aria-disabled="true">Previous</span>
                    <?php endif; ?>

                    <?php foreach ($pageWindow as $pageToken): ?>
                        <?php if ($pageToken === '…'): ?>
                            <span class="mcc-pagination-ellipsis" aria-hidden="true">…</span>
                        <?php else: ?>
                            <a
                                class="mcc-button <?= $pageToken === $currentPage ? 'mcc-button-primary' : 'mcc-button-secondary' ?>"
                                href="<?= esc($pageUrl((int) $pageToken)) ?>"
                                <?= $pageToken === $currentPage ? 'aria-current="page"' : '' ?>
                            >
                                <?= (int) $pageToken ?>
                            </a>
                        <?php endif; ?>
                    <?php endforeach; ?>

                    <?php if ($currentPage < $pageCount): ?>
                        <a
                            class="mcc-button mcc-button-secondary"
                            href="<?= esc($pageUrl($currentPage + 1)) ?>"
                        >
                            Next
                        </a>
                    <?php else: ?>
                        <span class="mcc-button mcc-button-secondary" aria-disabled="true">Next</span>
                    <?php endif; ?>
                </div>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</section>
