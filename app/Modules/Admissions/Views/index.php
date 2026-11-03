<?php

$filters = is_array($filters ?? null) ? $filters : [];
$admissions = is_array($admissions ?? null) ? $admissions : [];
$currentPage = (int) ($page ?? 1);
$pageCount = (int) ($pages ?? 1);
$totalAdmissions = (int) ($total ?? count($admissions));
$queryFilters = array_filter($filters, static fn ($value): bool => $value !== null && $value !== '');
$summaryFilters = $queryFilters;
unset($summaryFilters['status'], $summaryFilters['page']);
$pageWindow = mcc_follow_up_page_window($currentPage, $pageCount);
$pageUrl = static function (int $page) use ($queryFilters): string {
    $query = http_build_query(array_merge($queryFilters, ['page' => $page]));

    return site_url('admissions') . ($query === '' ? '' : '?' . $query);
};
$activeFilterValues = $filters;
unset($activeFilterValues['page']);
$hasFilters = count(array_filter($activeFilterValues, static fn ($value): bool => $value !== null && $value !== '')) > 0;
$statusSummary = is_array($statusSummary ?? null) ? $statusSummary : [];
$statusUrl = static function (string $status) use ($summaryFilters): string {
    $query = http_build_query(array_merge($summaryFilters, ['status' => $status]));

    return site_url('admissions') . ($query === '' ? '' : '?' . $query);
};

$pageActions = '<div class="mcc-page-actions">
    <button class="mcc-button mcc-button-secondary" type="button" data-filter-toggle
        aria-controls="admission-filters" aria-expanded="' . ($hasFilters ? 'true' : 'false') . '">
        <i class="fa-solid fa-filter" aria-hidden="true"></i>
        <span class="mcc-button-label">Filters</span>
    </button>
    <a class="mcc-button mcc-button-secondary" href="' . site_url('admissions?status=INTAKE') . '">Pending Admissions</a>'
    . (! empty($can_create) ? '<a class="mcc-button mcc-button-primary" href="' . site_url('admissions/new') . '">
        <i class="fa-solid fa-plus" aria-hidden="true"></i>
        <span class="mcc-button-label">Create admission</span>
    </a>' : '') . '
</div>';
?>
<?= view('staff_header', [
    'title' => 'Admissions',
    'nav' => $nav,
    'message' => $message,
    'error' => $error,
    'pageActions' => $pageActions,
]) ?>

<section id="admission-filters" class="mcc-card mcc-admission-filters" aria-labelledby="admission-filters-heading" <?= $hasFilters ? '' : 'hidden' ?>>
    <div class="mcc-card-heading">
        <span class="mcc-eyebrow">Search and filter</span>
        <h2 id="admission-filters-heading">Find an admission</h2>
    </div>
    <form method="get" action="<?= site_url('admissions') ?>" class="mcc-admission-filter-grid">
        <div class="mcc-form-field">
            <label for="admission-query">Applicant or lead</label>
            <input class="form-control" id="admission-query" name="q" type="search" maxlength="100" value="<?= esc($filters['q'] ?? '') ?>">
        </div>
        <div class="mcc-form-field">
            <label for="admission-status">Status</label>
            <select class="form-control" id="admission-status" name="status">
                <option value="">All statuses</option>
                <?php foreach ($statusOptions ?? [] as $statusOption): ?>
                    <option value="<?= esc($statusOption['value']) ?>" <?= ($filters['status'] ?? '') === $statusOption['value'] ? 'selected' : '' ?>>
                        <?= esc($statusOption['label']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mcc-form-field">
            <label for="admission-source">Source</label>
            <select class="form-control" id="admission-source" name="source">
                <option value="">All sources</option>
                <option value="direct" <?= ($filters['source'] ?? '') === 'direct' ? 'selected' : '' ?>>Direct Admission</option>
                <option value="lead" <?= ($filters['source'] ?? '') === 'lead' ? 'selected' : '' ?>>Lead-linked</option>
            </select>
        </div>
        <div class="mcc-admission-filter-actions">
            <button class="mcc-button mcc-button-primary" type="submit">Apply filters</button>
            <a class="mcc-button mcc-button-secondary" href="<?= site_url('admissions') ?>">Clear filters</a>
        </div>
    </form>
</section>

<section class="mcc-card mcc-admission-status-summary" aria-labelledby="admission-status-summary-heading">
    <div class="mcc-card-heading">
        <div>
            <span class="mcc-eyebrow">Admissions</span>
            <h2 id="admission-status-summary-heading">Status summary</h2>
        </div>
    </div>
    <div class="mcc-follow-up-summary-grid mcc-admission-status-summary-grid">
        <?php foreach ($statusSummary as $summaryCard): ?>
            <?php $isActive = ($filters['status'] ?? '') === ($summaryCard['filter'] ?? ''); ?>
            <a
                class="mcc-follow-up-summary-card mcc-status-badge <?= esc((string) ($summaryCard['class'] ?? '')) ?><?= $isActive ? ' is-active' : '' ?>"
                href="<?= esc($statusUrl((string) ($summaryCard['filter'] ?? ''))) ?>"
                aria-current="<?= $isActive ? 'page' : 'false' ?>"
            >
                <span class="mcc-follow-up-summary-label"><?= esc((string) ($summaryCard['label'] ?? 'Status')) ?></span>
                <strong class="mcc-follow-up-summary-count"><?= (int) ($summaryCard['count'] ?? 0) ?></strong>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<section class="mcc-card mcc-admission-list-card" aria-labelledby="admissions-title">
    <div class="mcc-card-heading mcc-admission-list-heading">
        <div>
            <span class="mcc-eyebrow">Admissions</span>
            <h2 id="admissions-title">Admission intake</h2>
        </div>
        <span class="mcc-muted"><?= $totalAdmissions ?> result<?= $totalAdmissions === 1 ? '' : 's' ?></span>
    </div>

    <?php if ($admissions === []): ?>
        <div class="mcc-empty-state mcc-admission-empty-state">
            <i class="fa-solid fa-inbox mcc-empty-icon" aria-hidden="true"></i>
            <h2>No admissions found</h2>
            <p>No admissions match the selected filters.</p>
            <?php if ($hasFilters): ?>
                <a class="mcc-button mcc-button-secondary" href="<?= site_url('admissions') ?>">Clear filters</a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="mcc-table-responsive">
            <div class="mcc-table-scroll">
                <table class="mcc-table mcc-admission-table">
                <caption class="visually-hidden">Branch-scoped admissions</caption>
                <thead>
                    <tr>
                        <th scope="col">Admission</th>
                        <th scope="col">Source</th>
                        <th scope="col">Branch</th>
                        <th scope="col">Status</th>
                        <th scope="col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($admissions as $admission): ?>
                        <tr>
                            <?php
                            $admissionNumber = trim((string) ($admission['admission_number'] ?? ''));
                            $applicantName = trim((string) ($admission['applicant_full_name'] ?? ''));
                            $leadName = trim((string) ($admission['lead_name'] ?? ''));
                            $leadReference = $leadName !== ''
                                ? $leadName
                                : 'Lead #' . (int) ($admission['lead_id'] ?? 0);
                            ?>
                            <th scope="row" class="mcc-admission-identity">
                                <span class="mcc-table-primary-text">
                                    <?= esc($applicantName !== '' ? $applicantName : 'Applicant') ?>
                                </span>
                                <span class="mcc-table-secondary-text">
                                    #<?= esc($admissionNumber !== '' ? $admissionNumber : 'Missing number') ?>
                                </span>
                                <?php if ($admission['lead_id'] !== null): ?>
                                    <a
                                        class="mcc-table-secondary-text"
                                        href="<?= esc(site_url('crm/leads/' . (int) $admission['lead_id'])) ?>"
                                    >
                                        Lead: <?= esc($leadReference) ?>
                                    </a>
                                <?php endif; ?>
                            </th>
                            <td>
                                <span class="mcc-badge mcc-badge-info">
                                    <?= $admission['lead_id'] === null ? 'Direct Admission' : 'Lead-linked' ?>
                                </span>
                                <?php if (! empty($admission['trial_id'])): ?>
                                    <span class="mcc-table-secondary-text">Trial linked</span>
                                <?php endif; ?>
                            </td>
                            <td class="mcc-admission-branch-cell">
                                <span class="mcc-table-primary-text"><?= esc($admission['branch_name'] ?? 'Authorized branch') ?></span>
                            </td>
                            <td>
                                <?php $statusPresentation = mcc_admission_status_presentation((string) ($admission['status'] ?? '')); ?>
                                <span class="mcc-badge mcc-status-badge <?= esc($statusPresentation['class']) ?>">
                                    <?= esc($statusPresentation['label']) ?>
                                </span>
                            </td>
                            <td>
                                <?php
                                $admissionId = (int) $admission['id'];
                                $branchId = (string) $admission['branch_id'];
                                $applicantName = (string) ($admission['lead_name'] ?? $admission['applicant_full_name'] ?? 'Applicant');
                                $canUpdate = service('authorization')->can('admission.update', $branchId);
                                $canConvert = service('authorization')->can('player.convert', $branchId);
                                $allowedTransitions = service('admission')->allowedTransitions((string) $admission['status']);
                                $canComplete = $canUpdate
                                    && $canConvert
                                    && (string) $admission['status'] === 'INTAKE';
                                ?>
                                <div class="mcc-admission-action-menu mcc-admission-list-action-menu" data-admission-actions>
                                    <button
                                        class="mcc-button mcc-button-secondary"
                                        type="button"
                                        data-admission-actions-toggle
                                        aria-controls="admission-actions-menu-<?= $admissionId ?>"
                                        aria-expanded="false"
                                        aria-haspopup="true"
                                        aria-label="Actions for <?= esc($applicantName) ?>"
                                    >
                                        Actions <span aria-hidden="true">▾</span>
                                    </button>
                                    <div
                                        class="mcc-admission-action-dropdown"
                                        id="admission-actions-menu-<?= $admissionId ?>"
                                        data-admission-actions-menu
                                        role="menu"
                                        hidden
                                    >
                                        <button
                                            type="button"
                                            role="menuitem"
                                            data-admission-action="view"
                                            data-admission-id="<?= $admissionId ?>"
                                            data-admission-name="<?= esc($applicantName) ?>"
                                            data-admission-url="<?= site_url('admissions/' . $admissionId . '/page?fragment=1') ?>"
                                        >
                                            <i class="fa-solid fa-eye" aria-hidden="true"></i>
                                            <span>View details</span>
                                        </button>
                                        <?php if (service('authorization')->can('admission.view', $branchId)): ?>
                                            <a
                                                class="mcc-admission-action-link"
                                                role="menuitem"
                                                href="<?= site_url('admissions/' . $admissionId . '/download') ?>"
                                            >
                                                <i class="fa-solid fa-file-pdf" aria-hidden="true"></i>
                                                <span>Download PDF</span>
                                            </a>
                                        <?php endif; ?>
                                        <?php if ($canUpdate && ! in_array((string) $admission['status'], ['REJECTED', 'CONVERTED'], true)): ?>
                                            <a
                                                class="mcc-admission-action-link"
                                                role="menuitem"
                                                href="<?= site_url('admissions/' . $admissionId . '/edit') ?>"
                                            >
                                                <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
                                                <span>Edit</span>
                                            </a>
                                        <?php endif; ?>
                                        <?php if ($canUpdate): ?>
                                            <a
                                                class="mcc-admission-action-link"
                                                role="menuitem"
                                                href="<?= site_url('admissions/' . $admissionId . '/update-coaches') ?>"
                                            >
                                                <i class="fa-solid fa-user-pen" aria-hidden="true"></i>
                                                <span>Update Coaches</span>
                                            </a>
                                        <?php endif; ?>
                                        <?php if ($canUpdate
                                            && ! in_array((string) $admission['status'], ['REJECTED', 'CONVERTED'], true)
                                            && service('authorization')->can('document.upload', $branchId)): ?>
                                            <button
                                                type="button"
                                                role="menuitem"
                                                data-admission-action="profile-photo"
                                                data-admission-id="<?= $admissionId ?>"
                                                data-admission-name="<?= esc($applicantName) ?>"
                                            >
                                                <i class="fa-solid fa-image" aria-hidden="true"></i>
                                                <span>Upload Profile Image</span>
                                            </button>
                                        <?php endif; ?>
                                        <?php if ($canUpdate): ?>
                                            <?php if ($allowedTransitions !== []): ?>
                                                <button
                                                    type="button"
                                                    role="menuitem"
                                                    data-admission-action="status"
                                                    data-admission-id="<?= $admissionId ?>"
                                                    data-admission-name="<?= esc($applicantName) ?>"
                                                    data-admission-current-status="<?= esc((string) $admission['status']) ?>"
                                                    data-admission-transitions="<?= esc(json_encode(array_values(array_filter($allowedTransitions, static fn (string $status): bool => $status !== 'CONVERTED')))) ?>"
                                                >
                                                    <i class="fa-solid fa-arrows-rotate" aria-hidden="true"></i>
                                                    <span>Change status</span>
                                                </button>
                                            <?php endif; ?>
                                            <?php if ($canComplete): ?>
                                                <button
                                                    type="button"
                                                    role="menuitem"
                                                    data-admission-action="complete"
                                                    data-admission-id="<?= $admissionId ?>"
                                                    data-admission-name="<?= esc($applicantName) ?>"
                                                    data-admission-url="<?= site_url('admissions/' . $admissionId . '/complete') ?>"
                                                >
                                                    <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                                                    <span>Complete admission</span>
                                                </button>
                                            <?php endif; ?>
                                            <?php if (service('authorization')->can('document.upload', $branchId)): ?>
                                                <button
                                                    type="button"
                                                    role="menuitem"
                                                    data-admission-action="document"
                                                    data-admission-id="<?= $admissionId ?>"
                                                    data-admission-name="<?= esc($applicantName) ?>"
                                                >
                                                    <i class="fa-solid fa-file-import" aria-hidden="true"></i>
                                                    <span>Upload document</span>
                                                </button>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        <?php if ($canConvert && (string) $admission['status'] === 'APPROVED' && $admission['converted_player_id'] === null): ?>
                                            <button
                                                type="button"
                                                role="menuitem"
                                                data-admission-action="convert"
                                                data-admission-id="<?= $admissionId ?>"
                                                data-admission-name="<?= esc($applicantName) ?>"
                                            >
                                                <i class="fa-solid fa-user-plus" aria-hidden="true"></i>
                                                <span>Convert to player</span>
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
        </div>
        <?php if ($pageCount > 1): ?>
            <nav class="mcc-pagination" aria-label="Admission pages">
                <span>Page <?= $currentPage ?> of <?= $pageCount ?></span>
                <div class="mcc-pagination-actions">
                    <?php if ($currentPage > 1): ?>
                        <a class="mcc-button mcc-button-secondary" href="<?= esc($pageUrl($currentPage - 1)) ?>">Previous</a>
                    <?php else: ?>
                        <span class="mcc-button mcc-button-secondary" aria-disabled="true">Previous</span>
                    <?php endif; ?>

                    <?php foreach ($pageWindow as $pageToken): ?>
                        <?php if (! is_int($pageToken)): ?>
                            <span class="mcc-pagination-ellipsis" aria-hidden="true"><?= esc((string) $pageToken) ?></span>
                        <?php else: ?>
                            <a
                                class="mcc-button <?= $pageToken === $currentPage ? 'mcc-button-primary' : 'mcc-button-secondary' ?>"
                                href="<?= esc($pageUrl($pageToken)) ?>"
                                <?= $pageToken === $currentPage ? 'aria-current="page"' : '' ?>
                            >
                                <?= $pageToken ?>
                            </a>
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

<div class="mcc-modal" data-admission-modal data-admission-base-url="<?= site_url('admissions') ?>" aria-hidden="true" hidden role="dialog" aria-modal="true" aria-labelledby="admission-modal-title" aria-describedby="admission-modal-body">
    <div class="mcc-modal-dialog mcc-admission-modal-dialog">
        <div class="mcc-modal-heading mcc-modal-header">
            <div>
                <span class="mcc-eyebrow" data-admission-modal-eyebrow>Admission action</span>
                <h2 id="admission-modal-title" data-admission-modal-title>Admission action</h2>
            </div>
            <button class="mcc-modal-close" type="button" data-admission-modal-close aria-label="Close">
                &times;
            </button>
        </div>
        <div class="mcc-modal-body" id="admission-modal-body" data-admission-modal-body></div>
        <div class="mcc-admission-modal-frame-wrap" data-admission-modal-frame-wrap hidden>
            <iframe title="Admission action" name="admission-action-frame" data-admission-modal-frame></iframe>
        </div>
        <div class="mcc-admission-modal-panel" data-admission-panel="status" hidden>
            <form method="post" data-admission-modal-form>
                <?= csrf_field() ?>
                <div class="mcc-form-field">
                    <label for="admission-modal-status">Next status</label>
                    <select id="admission-modal-status" name="status" required data-admission-status-options></select>
                </div>
                <div class="mcc-form-field">
                    <label for="admission-modal-reason">Reason <span class="mcc-muted">(when required)</span></label>
                    <input id="admission-modal-reason" name="reason" maxlength="255">
                </div>
                <p class="mcc-alert mcc-admission-response" data-admission-response hidden role="status"></p>
                <div class="mcc-modal-actions">
                    <button class="mcc-button mcc-button-secondary" type="button" data-admission-modal-close>Cancel</button>
                    <button class="mcc-button mcc-button-primary" type="submit">Change status</button>
                </div>
            </form>
        </div>
        <div class="mcc-admission-modal-panel" data-admission-panel="convert" hidden>
            <p>Convert this approved admission into a player record?</p>
            <form method="post" data-admission-modal-form>
                <?= csrf_field() ?>
                <p class="mcc-alert mcc-admission-response" data-admission-response hidden role="status"></p>
                <div class="mcc-modal-actions">
                    <button class="mcc-button mcc-button-secondary" type="button" data-admission-modal-close>Cancel</button>
                    <button class="mcc-button mcc-button-primary" type="submit">Convert to player</button>
                </div>
            </form>
        </div>
        <div class="mcc-admission-modal-panel" data-admission-panel="document" hidden>
            <form method="post" enctype="multipart/form-data" data-admission-modal-form>
                <?= csrf_field() ?>
                <label for="admission-modal-document">Document</label>
                <input class="form-control" id="admission-modal-document" name="document" type="file" accept="application/pdf,image/jpeg,image/png" required>
                <label for="admission-modal-document-type">Document type</label>
                <select class="form-control" id="admission-modal-document-type" name="document_type" required>
                    <option value="">Select document type</option>
                    <?php foreach (service('document')->types() as $documentType): ?>
                        <?php if ($documentType === 'player_photo') continue; ?>
                        <option value="<?= esc($documentType) ?>">
                            <?= esc(ucwords(str_replace('_', ' ', $documentType))) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="mcc-alert mcc-admission-response" data-admission-response hidden role="status"></p>
                <div class="mcc-modal-actions">
                    <button class="mcc-button mcc-button-secondary" type="button" data-admission-modal-close>Cancel</button>
                    <button class="mcc-button mcc-button-primary" type="submit">Upload document</button>
                </div>
            </form>
        </div>
        <div class="mcc-admission-modal-panel" data-admission-panel="profile-photo" hidden>
            <form method="post" enctype="multipart/form-data" data-admission-modal-form>
                <?= csrf_field() ?>
                <input type="hidden" name="document_type" value="player_photo">
                <input type="hidden" name="return_to" value="admissions">
                <label for="admission-modal-profile-photo">Profile image</label>
                <input
                    class="form-control"
                    id="admission-modal-profile-photo"
                    name="profile_photo"
                    type="file"
                    accept="image/jpeg,image/png"
                    required
                >
                <p class="mcc-alert mcc-admission-response" data-admission-response hidden role="status"></p>
                <div class="mcc-modal-actions">
                    <button class="mcc-button mcc-button-secondary" type="button" data-admission-modal-close>Cancel</button>
                    <button class="mcc-button mcc-button-primary" type="submit">Upload Profile Image</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?= view('staff_footer') ?>
