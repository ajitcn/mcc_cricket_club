<?php

$filters = is_array($filters ?? null) ? $filters : [];
$installments = is_array($installments ?? null) ? $installments : [];
$branches = is_array($branches ?? null) ? $branches : [];
$currentPage = (int) ($page ?? 1);
$pageCount = (int) ($pages ?? 1);
$totalInstallments = (int) ($total ?? count($installments));
$queryFilters = array_filter(
    $filters,
    static fn ($value): bool => $value !== null && $value !== '' && $value !== [],
);
$pageWindow = mcc_follow_up_page_window($currentPage, $pageCount);
$pageUrl = static function (int|string $pageNumber) use ($queryFilters): string {
    $query = http_build_query(array_merge($queryFilters, ['page' => $pageNumber]));

    return site_url('admissions/fee-installments') . ($query === '' ? '' : '?' . $query);
};
$activeFilterValues = $filters;
unset($activeFilterValues['page']);
$hasFilters = count(array_filter(
    $activeFilterValues,
    static fn ($value): bool => $value !== null && $value !== '' && $value !== [],
)) > 0;
$summaryCounts = is_array($summary_counts ?? null) ? $summary_counts : [
    'all' => $totalInstallments,
    'upcoming' => 0,
    'today' => 0,
    'overdue' => 0,
];
$summaryUrl = static function (?string $dueState) use ($queryFilters): string {
    $summaryFilters = $queryFilters;
    unset($summaryFilters['due_state'], $summaryFilters['page']);
    if ($dueState !== null) {
        $summaryFilters['due_state'] = $dueState;
    }
    $query = http_build_query($summaryFilters);

    return site_url('admissions/fee-installments') . ($query === '' ? '' : '?' . $query);
};

$pageActions = '<div class="mcc-page-actions">
    <button class="mcc-button mcc-button-secondary" type="button" data-filter-toggle
        data-show-label="Show Filters" data-hide-label="Hide Filters"
        aria-controls="admission-installment-filters" aria-expanded="false">
        <i class="fa-solid fa-filter" aria-hidden="true"></i>
        <span class="mcc-button-label">Show Filters</span>'
        . ($hasFilters ? '<span class="mcc-filter-count" aria-label="' . count($activeFilterValues) . ' active filters">' . count($activeFilterValues) . '</span>' : '') . '
    </button>
    <a class="mcc-button mcc-button-secondary" href="' . site_url('admissions') . '">Admissions</a>
</div>';
?>
<?= view('staff_header', [
    'title' => 'Fee Installments',
    'nav' => $nav,
    'message' => $message ?? null,
    'error' => $error ?? null,
    'pageActions' => $pageActions,
    'pageSubtitle' => 'Track coaching and hostel installment records by Admission, branch, and due state.',
]) ?>

<section class="mcc-card mcc-follow-up-summary mcc-installment-summary" aria-labelledby="fee-installment-summary-heading">
    <div class="mcc-card-heading">
        <div>
            <span class="mcc-eyebrow">Installment overview</span>
            <h2 id="fee-installment-summary-heading">Fee schedule status</h2>
        </div>
    </div>
    <div class="mcc-follow-up-summary-grid">
        <?php foreach ([
            ['key' => 'all', 'label' => 'All installments', 'filter' => null, 'class' => 'mcc-installment-status-all'],
            ['key' => 'upcoming', 'label' => 'Upcoming', 'filter' => 'upcoming', 'class' => 'mcc-installment-status-upcoming'],
            ['key' => 'today', 'label' => 'Due today', 'filter' => 'today', 'class' => 'mcc-installment-status-today'],
            ['key' => 'overdue', 'label' => 'Overdue', 'filter' => 'overdue', 'class' => 'mcc-installment-status-overdue'],
        ] as $card): ?>
            <a class="mcc-follow-up-summary-card <?= esc($card['class']) ?>" href="<?= esc($summaryUrl($card['filter'])) ?>">
                <span class="mcc-follow-up-summary-label"><?= esc($card['label']) ?></span>
                <strong class="mcc-follow-up-summary-count"><?= (int) ($summaryCounts[$card['key']] ?? 0) ?></strong>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<section
    id="admission-installment-filters"
    class="mcc-card mcc-admission-filters mcc-admission-installment-filters"
    aria-labelledby="admission-installment-filters-heading"
    hidden
>
    <div class="mcc-card-heading">
        <span class="mcc-eyebrow">Search and filter</span>
        <h2 id="admission-installment-filters-heading">Find fee installments</h2>
    </div>
    <form method="get" action="<?= site_url('admissions/fee-installments') ?>" class="mcc-admission-filter-grid">
        <div class="mcc-form-field">
            <label for="installment-applicant">Applicant name</label>
            <input
                class="form-control"
                id="installment-applicant"
                name="applicant"
                type="search"
                maxlength="100"
                value="<?= esc($filters['applicant'] ?? '') ?>"
            >
        </div>
        <div class="mcc-form-field">
            <label for="installment-admission-number">Admission number</label>
            <input
                class="form-control"
                id="installment-admission-number"
                name="admission_number"
                type="search"
                maxlength="50"
                value="<?= esc($filters['admission_number'] ?? '') ?>"
            >
        </div>
        <div class="mcc-form-field">
            <label for="installment-branch">Branch</label>
            <select class="form-control" id="installment-branch" name="branch_id">
                <option value="">All authorized branches</option>
                <?php foreach ($branches as $branch): ?>
                    <option
                        value="<?= (int) $branch['id'] ?>"
                        <?= (string) ($filters['branch_id'] ?? '') === (string) $branch['id'] ? 'selected' : '' ?>
                    >
                        <?= esc($branch['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mcc-form-field">
            <label for="installment-type">Installment type</label>
            <select class="form-control" id="installment-type" name="installment_type">
                <option value="">All types</option>
                <option value="coaching" <?= ($filters['installment_type'] ?? '') === 'coaching' ? 'selected' : '' ?>>Coaching</option>
                <option value="hostel" <?= ($filters['installment_type'] ?? '') === 'hostel' ? 'selected' : '' ?>>Hostel</option>
            </select>
        </div>
        <div class="mcc-form-field">
            <label for="installment-year">Year</label>
            <input class="form-control" id="installment-year" name="year" type="number" min="1" value="<?= esc($filters['year'] ?? '') ?>">
        </div>
        <div class="mcc-form-field">
            <label for="installment-number">Installment number</label>
            <input class="form-control" id="installment-number" name="installment_number" type="number" min="1" value="<?= esc($filters['installment_number'] ?? '') ?>">
        </div>
        <div class="mcc-form-field">
            <label for="installment-due-state">Due state</label>
            <select class="form-control" id="installment-due-state" name="due_state">
                <option value="">All due states</option>
                <option value="upcoming" <?= ($filters['due_state'] ?? '') === 'upcoming' ? 'selected' : '' ?>>Upcoming</option>
                <option value="today" <?= ($filters['due_state'] ?? '') === 'today' ? 'selected' : '' ?>>Due today</option>
                <option value="overdue" <?= ($filters['due_state'] ?? '') === 'overdue' ? 'selected' : '' ?>>Overdue</option>
            </select>
        </div>
        <div class="mcc-form-field">
            <label for="installment-payment-status">Payment status</label>
            <select class="form-control" id="installment-payment-status" name="payment_status">
                <option value="">All payment statuses</option>
                <?php foreach (['Not captured', 'Partially paid', 'Paid', 'Overpaid'] as $paymentStatus): ?>
                    <option
                        value="<?= esc($paymentStatus) ?>"
                        <?= ($filters['payment_status'] ?? '') === $paymentStatus ? 'selected' : '' ?>
                    >
                        <?= esc($paymentStatus) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mcc-form-field">
            <label for="installment-due-from">Due date from</label>
            <input
                class="form-control"
                id="installment-due-from"
                name="due_date_from"
                type="text"
                inputmode="numeric"
                placeholder="DD-MM-YYYY"
                autocomplete="off"
                data-mcc-date-picker
                data-mcc-date-format="d-m-Y"
                data-mcc-date-submit-format="Y-m-d"
                value="<?= esc(mcc_user_date_input($filters['due_date_from'] ?? null)) ?>"
            >
        </div>
        <div class="mcc-form-field">
            <label for="installment-due-to">Due date to</label>
            <input
                class="form-control"
                id="installment-due-to"
                name="due_date_to"
                type="text"
                inputmode="numeric"
                placeholder="DD-MM-YYYY"
                autocomplete="off"
                data-mcc-date-picker
                data-mcc-date-format="d-m-Y"
                data-mcc-date-submit-format="Y-m-d"
                value="<?= esc(mcc_user_date_input($filters['due_date_to'] ?? null)) ?>"
            >
        </div>
        <div class="mcc-admission-filter-actions">
            <button class="mcc-button mcc-button-primary" type="submit">Apply filters</button>
            <a class="mcc-button mcc-button-secondary mcc-clear-filters-action" href="<?= site_url('admissions/fee-installments') ?>">Clear Filters</a>
        </div>
    </form>
</section>

<section class="mcc-card mcc-admission-list-card" aria-labelledby="admission-installments-title">
    <div class="mcc-card-heading mcc-admission-list-heading">
        <div>
            <span class="mcc-eyebrow">Admissions</span>
            <h2 id="admission-installments-title">Fee Installments</h2>
        </div>
        <span class="mcc-muted">
            <?= $totalInstallments ?> record<?= $totalInstallments === 1 ? '' : 's' ?>
        </span>
    </div>

    <?php if ($installments === []): ?>
        <div class="mcc-empty-state mcc-admission-empty-state">
            <i class="fa-solid fa-receipt mcc-empty-icon" aria-hidden="true"></i>
            <h2>No fee installments found</h2>
            <p>No stored installments match the selected filters.</p>
            <?php if ($hasFilters): ?>
                <a class="mcc-button mcc-button-secondary" href="<?= site_url('admissions/fee-installments') ?>">Clear filters</a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="mcc-table-responsive">
            <div class="mcc-table-scroll">
                <table class="mcc-table mcc-admission-installment-tracking-table">
                    <caption class="visually-hidden">Branch-scoped Admission fee installments</caption>
                    <thead>
                        <tr>
                            <th scope="col">Admission</th>
                            <th scope="col">Type</th>
                            <th scope="col">Year</th>
                            <th scope="col">Installment</th>
                            <th scope="col" class="mcc-amount-column">Amount</th>
                            <th scope="col">Due date</th>
                            <th scope="col">Due state</th>
                            <th scope="col">Payment status</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($installments as $installment): ?>
                            <?php
                            $admissionId = (int) ($installment['admission_id'] ?? 0);
                            $installmentId = (int) ($installment['installment_id'] ?? 0);
                            $type = (string) ($installment['installment_type'] ?? '');
                            $typeLabel = $type === 'hostel' ? 'Hostel' : 'Coaching';
                            $dueState = (string) ($installment['due_state'] ?? '');
                            $statusLabel = $installment['payment_status'] ?? null;
                            $statusPresentation = mcc_admission_payment_status_presentation($statusLabel);
                            $canRecordPayment = service('authorization')->can(
                                'admission.update',
                                (string) ($installment['branch_id'] ?? ''),
                            );
                            ?>
                            <tr>
                                <th scope="row" class="mcc-installment-admission-cell">
                                    <a class="mcc-installment-applicant-name" href="<?= esc(site_url('admissions/' . $admissionId . '/page')) ?>">
                                        <?= esc((string) ($installment['applicant_name'] ?? 'Not provided')) ?>
                                    </a>
                                    <span>#<?= esc((string) ($installment['admission_number'] ?? 'Not provided')) ?></span>
                                    <span><?= esc((string) ($installment['branch_name'] ?? 'Not provided')) ?></span>
                                </th>
                                <td><span class="mcc-badge mcc-badge-info"><?= esc($typeLabel) ?></span></td>
                                <td><?= esc((string) ($installment['year_label'] ?? 'Year ' . ($installment['year_number'] ?? 'Not provided'))) ?></td>
                                <td><?= esc((string) ($installment['installment_number'] ?? 'Not provided')) ?></td>
                                <td class="mcc-amount-column">&#8377;<?= esc(number_format((float) ($installment['amount'] ?? 0), 2)) ?></td>
                                <td><?= esc(mcc_user_date($installment['due_date'] ?? null, 'Not provided')) ?></td>
                                <td>
                                    <span class="mcc-badge mcc-installment-due-badge mcc-installment-due-<?= esc(strtolower(str_replace(' ', '-', $dueState))) ?>">
                                        <?= esc($dueState) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="mcc-badge mcc-status-badge mcc-installment-payment-status <?= esc($statusPresentation['class']) ?>">
                                        <?= esc($statusPresentation['label']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="mcc-admission-action-menu" data-admission-actions>
                                        <button
                                            class="mcc-button mcc-button-secondary"
                                            type="button"
                                            data-admission-actions-toggle
                                            aria-controls="installment-actions-menu-<?= esc($type) ?>-<?= $installmentId ?>"
                                            aria-expanded="false"
                                            aria-haspopup="true"
                                            aria-label="Actions for <?= esc((string) ($installment['admission_number'] ?? 'Admission')) ?>"
                                        >
                                            <span>Actions</span>
                                            <span class="mcc-action-caret" aria-hidden="true"></span>
                                        </button>
                                        <div
                                            class="mcc-admission-action-dropdown"
                                            id="installment-actions-menu-<?= esc($type) ?>-<?= $installmentId ?>"
                                            data-admission-actions-menu
                                            role="menu"
                                            hidden
                                        >
                                            <a
                                                class="mcc-admission-action-link"
                                                role="menuitem"
                                                href="<?= esc(site_url('admissions/' . $admissionId . '/page')) ?>"
                                            >
                                                <i class="fa-solid fa-eye" aria-hidden="true"></i>
                                                <span>View Admission</span>
                                            </a>
                                            <?php if ($canRecordPayment && $installmentId > 0): ?>
                                                <a
                                                    class="mcc-admission-action-link"
                                                    role="menuitem"
                                                    href="<?= esc(site_url('admissions/fee-installments/' . rawurlencode($type) . '/' . $installmentId . '/payment')) ?>"
                                                >
                                                    <i class="fa-solid fa-receipt" aria-hidden="true"></i>
                                                    <span>Record Payment</span>
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
        </div>
    <?php endif; ?>

    <?php if ($pageCount > 1): ?>
        <nav class="mcc-pagination" aria-label="Fee installment pages">
            <span>Page <?= $currentPage ?> of <?= $pageCount ?></span>
            <div>
                <?php if ($currentPage > 1): ?>
                    <a class="mcc-button mcc-button-secondary" href="<?= esc($pageUrl($currentPage - 1)) ?>">Previous</a>
                <?php endif; ?>
                <?php foreach ($pageWindow as $pageToken): ?>
                    <?php if (is_string($pageToken)): ?>
                        <span class="mcc-pagination-ellipsis" aria-hidden="true"><?= esc($pageToken) ?></span>
                    <?php else: ?>
                        <a
                            class="mcc-button <?= (int) $pageToken === $currentPage ? 'mcc-button-primary' : 'mcc-button-secondary' ?>"
                            href="<?= esc($pageUrl($pageToken)) ?>"
                            <?= (int) $pageToken === $currentPage ? 'aria-current="page"' : '' ?>
                        >
                            <?= (int) $pageToken ?>
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

<?= view('staff_footer') ?>
