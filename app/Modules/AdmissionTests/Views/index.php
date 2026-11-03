<?php

$tests = is_array($tests ?? null) ? $tests : [];
$filters = is_array($filters ?? null) ? $filters : [];
$branches = is_array($branches ?? null) ? $branches : [];
$statuses = is_array($statuses ?? null) ? $statuses : [];
$paperSets = is_array($paperSets ?? null) ? $paperSets : [];
$statusCounts = is_array($statusCounts ?? null) ? $statusCounts : [];
$currentPage = max(1, (int) ($page ?? 1));
$pageCount = max(1, (int) ($pages ?? 1));
$total = max(0, (int) ($total ?? 0));
$queryFilters = array_filter(
    $filters,
    static fn ($value): bool => $value !== null && $value !== '' && $value !== [],
);
$activeFilterCount = count($queryFilters);
$pageWindow = mcc_follow_up_page_window($currentPage, $pageCount);
$dateTime = static fn (?string $date): string => mcc_follow_up_datetime($date, 'Not available');
$statusLabels = static fn (string $status): string => mcc_admission_test_status_presentation($status)['label'];
$pageUrl = static function (int $targetPage) use ($queryFilters): string {
    $query = http_build_query(array_merge($queryFilters, ['page' => $targetPage]));

    return site_url('admission-tests') . ($query === '' ? '' : '?' . $query);
};
$filterValue = static function (string $field) use ($filters): string {
    return esc((string) ($filters[$field] ?? ''));
};
$statusCount = static fn (string $key): int => (int) ($statusCounts[$key] ?? 0);

$pageActions = '<div class="mcc-page-actions">
    <button class="mcc-button mcc-button-secondary" type="button" data-filter-toggle
        data-show-label="Show filters" data-hide-label="Hide filters"
        aria-controls="admission-test-filters" aria-expanded="false">
        <i class="fa-solid fa-filter" aria-hidden="true"></i>
        <span class="mcc-button-label">Show filters</span>'
        . ($activeFilterCount > 0
            ? '<span class="mcc-filter-count" aria-label="' . $activeFilterCount . ' active filters">'
                . $activeFilterCount . '</span>'
            : '') . '
    </button>
</div>';
?>
<?= view('staff_header', [
    'title' => 'Admission Test',
    'nav' => $nav ?? [],
    'message' => $message ?? null,
    'error' => $error ?? null,
    'pageActions' => $pageActions,
    'breadcrumbTrail' => [
        ['label' => 'CRM', 'url' => site_url('crm')],
        ['label' => 'Admission Test'],
    ],
]) ?>

<div class="mcc-alert mcc-alert-success mcc-admission-status-page-message" data-admission-status-page-message role="status" hidden></div>

<section class="mcc-card mcc-admission-test-status-summary" aria-labelledby="admission-test-status-summary-heading">
    <div class="mcc-card-heading">
        <div>
            <span class="mcc-eyebrow">Overview</span>
            <h2 id="admission-test-status-summary-heading">Admission Test status</h2>
        </div>
        <span class="mcc-muted"><?= $total ?> result<?= $total === 1 ? '' : 's' ?></span>
    </div>
    <div class="mcc-admission-test-status-counts">
        <?php foreach ([
            ['key' => 'all', 'label' => 'All', 'class' => 'mcc-badge-neutral'],
            ['key' => 'test_submitted', 'label' => 'Test submitted', 'class' => 'mcc-badge-warning'],
            ['key' => 'approved_for_admission', 'label' => 'Approved for admission', 'class' => 'mcc-badge-success'],
            ['key' => 'rejected', 'label' => 'Rejected', 'class' => 'mcc-badge-danger'],
            ['key' => 'on_hold', 'label' => 'On hold', 'class' => 'mcc-badge-neutral'],
        ] as $card): ?>
            <div class="mcc-admission-test-status-count">
                <span class="mcc-badge <?= esc($card['class']) ?>"><?= esc($card['label']) ?></span>
                <strong><?= $statusCount($card['key']) ?></strong>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<section id="admission-test-filters" class="mcc-card mcc-admission-test-filters" aria-labelledby="admission-test-filters-heading" hidden>
    <div class="mcc-card-heading">
        <div>
            <span class="mcc-eyebrow">Search and filter</span>
            <h2 id="admission-test-filters-heading">Find an Admission Test</h2>
        </div>
    </div>
    <form method="get" action="<?= site_url('admission-tests') ?>" class="mcc-admission-test-filter-form">
        <div class="mcc-admission-test-filter-field">
            <label for="admission-test-reference">Test reference</label>
            <input class="form-control" id="admission-test-reference" name="test_reference" type="search" inputmode="numeric" value="<?= $filterValue('test_reference') ?>">
        </div>
        <div class="mcc-admission-test-filter-field">
            <label for="admission-test-applicant">Applicant name</label>
            <input class="form-control" id="admission-test-applicant" name="applicant" type="search" maxlength="100" value="<?= $filterValue('applicant') ?>">
        </div>
        <div class="mcc-admission-test-filter-field">
            <label for="admission-test-trial">Trial reference</label>
            <input class="form-control" id="admission-test-trial" name="trial_reference" type="search" inputmode="numeric" value="<?= $filterValue('trial_reference') ?>">
        </div>
        <div class="mcc-admission-test-filter-field">
            <label for="admission-test-branch">Branch</label>
            <select class="form-control" id="admission-test-branch" name="branch_id">
                <option value="">All authorized branches</option>
                <?php foreach ($branches as $branch): ?>
                    <option value="<?= (int) $branch['id'] ?>" <?= $filterValue('branch_id') === (string) $branch['id'] ? 'selected' : '' ?>><?= esc((string) $branch['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mcc-admission-test-filter-field">
            <label for="admission-test-status">Test status</label>
            <select class="form-control" id="admission-test-status" name="status">
                <option value="">All statuses</option>
                <?php foreach ($statuses as $status): ?>
                    <option value="<?= esc($status) ?>" <?= $filterValue('status') === $status ? 'selected' : '' ?>><?= esc($statusLabels($status)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mcc-admission-test-filter-field">
            <label for="admission-test-paper-set">Paper set</label>
            <select class="form-control" id="admission-test-paper-set" name="paper_set">
                <option value="">All paper sets</option>
                <?php foreach ($paperSets as $value => $label): ?>
                    <option value="<?= esc((string) $value) ?>" <?= $filterValue('paper_set') === (string) $value ? 'selected' : '' ?>><?= esc((string) $label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php foreach ([
            ['created_from', 'Created from', 'admission-test-created-from'],
            ['created_to', 'Created to', 'admission-test-created-to'],
            ['updated_from', 'Updated from', 'admission-test-updated-from'],
            ['updated_to', 'Updated to', 'admission-test-updated-to'],
        ] as [$field, $label, $id]): ?>
            <div class="mcc-admission-test-filter-field">
                <label for="<?= $id ?>"><?= $label ?></label>
                <input class="form-control" id="<?= $id ?>" name="<?= $field ?>" type="text" inputmode="numeric" placeholder="DD-MM-YYYY" autocomplete="off" data-mcc-date-picker data-mcc-date-picker-no-toggle data-mcc-date-format="d-m-Y" data-mcc-date-submit-format="Y-m-d" value="<?= esc(mcc_user_date_input($filters[$field] ?? null)) ?>">
            </div>
        <?php endforeach; ?>
        <div class="mcc-admission-test-filter-actions">
            <button class="mcc-button mcc-button-primary" type="submit"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i> Search</button>
            <a class="mcc-button mcc-button-secondary" href="<?= site_url('admission-tests') ?>"><i class="fa-solid fa-rotate-left" aria-hidden="true"></i> Clear filters</a>
        </div>
    </form>
</section>

<section class="mcc-card mcc-admission-test-list-card" aria-labelledby="admission-tests-heading">
    <div class="mcc-card-heading">
        <div>
            <span class="mcc-eyebrow">History</span>
            <h2 id="admission-tests-heading">Admission Tests</h2>
        </div>
        <span class="mcc-muted">Page <?= $currentPage ?> of <?= $pageCount ?></span>
    </div>
    <?php if ($tests === []): ?>
        <div class="mcc-empty-state">
            <h3>No Admission Tests found</h3>
            <p>Try changing the filters or start an Admission Test from an authorized Trial.</p>
        </div>
    <?php else: ?>
        <div class="mcc-table-wrap mcc-admission-test-table-wrap">
            <table class="mcc-table mcc-admission-test-list-table">
                <caption class="visually-hidden">Branch-scoped Admission Tests</caption>
                <thead>
                    <tr>
                        <th scope="col">Test / Applicant</th>
                        <th scope="col">Branch</th>
                        <th scope="col">Paper set</th>
                        <th scope="col">Status</th>
                        <th scope="col">Created / updated</th>
                        <th scope="col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tests as $test): ?>
                        <?php
                        $testId = (int) ($test['id'] ?? 0);
                        $status = (string) ($test['status'] ?? '');
                        $statusPresentation = mcc_admission_test_status_presentation($status);
                        $canView = service('authorization')->can('admission.view', (string) ($test['branch_id'] ?? ''));
                        $canUpdate = service('authorization')->can('admission.update', (string) ($test['branch_id'] ?? ''));
                        $canCreate = service('authorization')->can('admission.create', (string) ($test['branch_id'] ?? ''));
                        $pendingEligible = strtoupper($status) === 'APPROVED_FOR_ADMISSION'
                            && (int) ($test['admission_id'] ?? 0) === 0
                            && $canCreate;
                        ?>
                        <tr data-admission-test-row="<?= $testId ?>">
                            <th scope="row">
                                <div class="mcc-admission-test-identity">
                                    <a class="mcc-table-secondary-text" href="<?= esc(site_url('admission-tests/' . $testId)) ?>">
                                        Test #<?= $testId ?>
                                    </a>
                                    <a class="mcc-table-primary-text" href="<?= esc(site_url('admission-tests/' . $testId)) ?>">
                                        <?= esc((string) ($test['applicant_full_name'] ?: 'Unnamed applicant')) ?>
                                    </a>
                                    <?php if ((int) ($test['trial_id'] ?? 0) > 0): ?>
                                        <a class="mcc-table-secondary-text" href="<?= esc(site_url('crm/trials/' . (int) $test['trial_id'])) ?>">
                                            Trial #<?= (int) $test['trial_id'] ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="mcc-table-secondary-text">Trial not linked</span>
                                    <?php endif; ?>
                                </div>
                            </th>
                            <td><?= esc((string) ($test['branch_name'] ?? 'Not available')) ?></td>
                            <td><?= esc($test['test_variant'] ? 'Paper Set ' . $test['test_variant'] : 'Not selected') ?></td>
                            <td><span class="mcc-badge mcc-status-badge <?= esc($statusPresentation['class']) ?>" data-admission-status-badge><?= esc($statusPresentation['label']) ?></span></td>
                            <td>
                                <span class="mcc-table-secondary-text">Created: <?= esc($dateTime($test['created_at'] ?? null)) ?></span>
                                <span class="mcc-table-secondary-text">Updated: <?= esc($dateTime($test['updated_at'] ?? null)) ?></span>
                            </td>
                            <td>
                                <div class="mcc-admission-actions mcc-admission-action-menu" data-admission-actions>
                                    <button class="mcc-button mcc-button-secondary" type="button" data-admission-actions-toggle aria-controls="admission-test-actions-<?= $testId ?>" aria-expanded="false" aria-haspopup="true"><i class="fa-solid fa-ellipsis-vertical" aria-hidden="true"></i><span>Actions</span></button>
                                    <div class="mcc-admission-action-dropdown" id="admission-test-actions-<?= $testId ?>" data-admission-actions-menu role="menu" hidden>
                                        <?php if ($canView): ?><a class="mcc-admission-action-link" role="menuitem" href="<?= site_url('admission-tests/' . $testId) ?>"><i class="fa-solid fa-eye" aria-hidden="true"></i> View Test</a><?php endif; ?>
                                        <?php if ($canUpdate): ?><a class="mcc-admission-action-link" role="menuitem" href="<?= site_url('admission-tests/' . $testId . '#personal-details') ?>"><i class="fa-solid fa-pen-to-square" aria-hidden="true"></i> Edit</a><a class="mcc-admission-action-link" role="menuitem" href="<?= site_url('admission-tests/' . $testId . '#meeting-follow-ups') ?>"><i class="fa-solid fa-calendar-plus" aria-hidden="true"></i> Add Meeting Follow-up</a><?php endif; ?>
                                        <?php if ($canUpdate && in_array(strtoupper($status), ['TEST_SUBMITTED', 'MEETING_FOLLOW_UP', 'APPROVED_FOR_ADMISSION', 'REJECTED', 'ON_HOLD'], true)): ?>
                                            <button class="mcc-admission-action-link" type="button" role="menuitem" data-admission-status-open
                                                data-admission-status-id="<?= $testId ?>"
                                                data-admission-status-action="<?= site_url('admission-tests/' . $testId . '/status') ?>"
                                                data-admission-status-version="<?= (int) ($test['version'] ?? 1) ?>"
                                                data-admission-status-current="<?= esc(strtoupper($status)) ?>">
                                                <i class="fa-solid fa-arrow-right-arrow-left" aria-hidden="true"></i> Change Status
                                            </button>
                                        <?php endif; ?>
                                        <?php if ($pendingEligible): ?><a class="mcc-admission-action-link" role="menuitem" href="<?= site_url('admission-tests/' . $testId . '#pending-admission') ?>"><i class="fa-solid fa-file-circle-check" aria-hidden="true"></i> Move to Admission Pending</a><?php endif; ?>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($pageCount > 1): ?>
            <nav class="mcc-pagination" aria-label="Admission Test pages">
                <span>Page <?= $currentPage ?> of <?= $pageCount ?></span>
                <div class="mcc-pagination-actions">
                    <?php if ($currentPage > 1): ?><a class="mcc-button mcc-button-secondary" href="<?= esc($pageUrl($currentPage - 1)) ?>" aria-label="Previous Admission Test page">Previous</a><?php else: ?><span class="mcc-button mcc-button-secondary" aria-disabled="true">Previous</span><?php endif; ?>
                    <?php foreach ($pageWindow as $pageToken): ?>
                        <?php if (is_string($pageToken)): ?><span class="mcc-pagination-ellipsis" aria-hidden="true">…</span><?php else: ?><a class="mcc-button <?= $pageToken === $currentPage ? 'mcc-button-primary' : 'mcc-button-secondary' ?>" href="<?= esc($pageUrl((int) $pageToken)) ?>" <?= $pageToken === $currentPage ? 'aria-current="page"' : '' ?>><?= (int) $pageToken ?></a><?php endif; ?>
                    <?php endforeach; ?>
                    <?php if ($currentPage < $pageCount): ?><a class="mcc-button mcc-button-secondary" href="<?= esc($pageUrl($currentPage + 1)) ?>" aria-label="Next Admission Test page">Next</a><?php else: ?><span class="mcc-button mcc-button-secondary" aria-disabled="true">Next</span><?php endif; ?>
                </div>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</section>
<div class="mcc-modal mcc-admission-status-modal" data-admission-status-modal hidden aria-hidden="true">
    <div class="mcc-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="admission-status-modal-heading">
        <div class="mcc-modal-heading">
            <div>
                <span class="mcc-eyebrow">Admission Test</span>
                <h2 id="admission-status-modal-heading">Change Status</h2>
            </div>
            <button class="mcc-modal-close" type="button" data-admission-status-close aria-label="Close Change Status modal">&times;</button>
        </div>
        <p class="mcc-modal-instruction">Update the workflow status for Test <strong data-admission-status-reference></strong>. Test answers and personal details are not changed.</p>
        <div class="mcc-alert mcc-alert-danger" data-admission-status-form-error role="alert" hidden></div>
        <form method="post" class="mcc-admission-status-modal-form" data-admission-status-form>
            <?= csrf_field() ?>
            <input type="hidden" name="version" data-admission-status-version-input>
            <div class="mcc-form-field">
                <label for="admission-status-modal-status">Status</label>
                <select class="form-control" id="admission-status-modal-status" name="status" data-admission-status-select required>
                    <?php foreach (service('admissionTest')->managedStatuses() as $managedStatus): ?>
                        <option value="<?= esc($managedStatus) ?>"><?= esc(mcc_admission_test_status_presentation($managedStatus)['label']) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="invalid-feedback" data-admission-status-error="status" role="alert" hidden></div>
            </div>
            <div class="mcc-form-field">
                <label for="admission-status-modal-reason">Reason <span class="mcc-muted">(required for Rejected and On hold)</span></label>
                <textarea class="form-control" id="admission-status-modal-reason" name="reason" rows="4" maxlength="255" data-admission-status-reason placeholder="Add a correction or review note"></textarea>
                <div class="invalid-feedback" data-admission-status-error="reason" role="alert" hidden></div>
            </div>
            <div class="mcc-modal-actions">
                <button class="mcc-button mcc-button-secondary" type="button" data-admission-status-cancel>Cancel</button>
                <button class="mcc-button mcc-button-primary" type="submit" data-admission-status-submit>Save status</button>
            </div>
        </form>
    </div>
</div>
<?= view('staff_footer') ?>
