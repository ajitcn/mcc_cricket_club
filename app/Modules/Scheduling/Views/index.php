<?php

$filters = is_array($filters ?? null) ? $filters : [];
$queryFilters = array_filter(
    $filters,
    static fn ($value, $key): bool => $key !== 'page'
        && $value !== null
        && $value !== '',
    ARRAY_FILTER_USE_BOTH
);
$hasFilters = $queryFilters !== [];
$activeFilterCount = count($queryFilters);
$currentPage = max(1, (int) ($page ?? 1));
$pageCount = max(1, (int) ($pages ?? 1));
$pageWindow = mcc_follow_up_page_window($currentPage, $pageCount);
$pageUrl = static function (int $targetPage) use ($queryFilters): string {
    $query = http_build_query(array_merge($queryFilters, ['page' => $targetPage]));

    return site_url('sessions') . ($query === '' ? '' : '?' . $query);
};
$statusDefinitions = [
    'scheduled' => ['label' => 'Scheduled', 'class' => 'info'],
    'active' => ['label' => 'Active', 'class' => 'success'],
    'completed' => ['label' => 'Completed', 'class' => 'success'],
    'cancelled' => ['label' => 'Cancelled', 'class' => 'danger'],
    'postponed' => ['label' => 'Postponed', 'class' => 'warning'],
];
$formatSessionDateTime = static function (?string $value): string {
    $value = trim((string) $value);

    if ($value === '') {
        return 'Not available';
    }

    $date = date_create($value);

    return $date === false ? 'Not available' : $date->format('d-m-Y, h:i A');
};
$displayValue = static function ($value): string {
    return trim((string) $value) === '' ? '—' : (string) $value;
};
$activeFilterLabels = [];
foreach ([
    'q' => 'Search',
    'date' => 'Date',
    'status' => 'Status',
    'branch_id' => 'Branch',
    'batch_id' => 'Batch',
    'coach_user_id' => 'Coach',
    'ground_id' => 'Ground',
] as $key => $label) {
    if (($filters[$key] ?? '') !== '') {
        $activeFilterLabels[] = $label;
    }
}
?>
<?= view('staff_header', [
    'title' => 'Sessions',
    'nav' => $nav,
    'message' => $message,
    'error' => $error,
    'pageActions' => '<div class="mcc-page-actions">
        <button class="mcc-button mcc-button-secondary" type="button" data-filter-toggle
            data-show-label="Show Filters" data-hide-label="Hide Filters"
            aria-controls="session-filters" aria-expanded="' . ($hasFilters ? 'true' : 'false') . '">
            <i class="fa-solid fa-filter" aria-hidden="true"></i>
            <span class="mcc-button-label">' . ($hasFilters ? 'Hide Filters' : 'Show Filters') . '</span>
        </button>
        <a class="mcc-button mcc-button-primary" href="' . site_url('sessions/new') . '">
            <i class="fa-solid fa-calendar-plus" aria-hidden="true"></i>
            <span class="mcc-button-label">Create session</span>
        </a>
    </div>',
]) ?>
<section
    id="session-filters"
    class="mcc-card mcc-scheduling-card mcc-session-filters-card"
    aria-labelledby="session-filters-heading"
    <?= $hasFilters ? '' : 'hidden' ?>
>
    <div class="mcc-card-heading">
        <div>
            <span class="mcc-eyebrow">Search and filter</span>
            <h2 id="session-filters-heading">Find a session</h2>
        </div>
    </div>
    <form
        method="get"
        action="<?= esc(site_url('sessions')) ?>"
        class="row mcc-scheduling-filter-form"
        aria-label="Filter sessions"
    >
        <div class="col-12 col-md-6 mb-2">
            <label for="session-search">Search</label>
            <input class="form-control" id="session-search" name="q" value="<?= esc($filters['q'] ?? '') ?>" placeholder="Batch, coach, or branch">
        </div>
        <div class="col-12 col-md-3 mb-2">
            <label for="session-date">Date</label>
            <input
                class="form-control"
                id="session-date"
                name="date"
                inputmode="numeric"
                placeholder="DD-MM-YYYY"
                data-mcc-date-picker
                data-mcc-date-format="d-m-Y"
                data-mcc-date-submit-format="Y-m-d"
                value="<?= esc(mcc_user_date_input($filters['date'] ?? null)) ?>"
            >
        </div>
        <div class="col-12 col-md-3 mb-2">
            <label for="session-status">Status</label>
            <select class="form-control" id="session-status" name="status">
                <option value="">All statuses</option>
                <?php foreach ($statusDefinitions as $status => $definition): ?>
                    <option
                        value="<?= esc($status) ?>"
                        <?= ($filters['status'] ?? '') === $status ? 'selected' : '' ?>
                    >
                        <?= esc($definition['label']) ?>
                    </option>
                <?php endforeach ?>
            </select>
        </div>
<?php if (count($branchOptions ?? []) > 1): ?>
        <div class="col-12 col-md-3 mb-2">
            <label for="session-branch">Branch</label>
            <select class="form-control" id="session-branch" name="branch_id">
                <option value="">All authorized branches</option>
                <?php foreach (($branchOptions ?? []) as $branchId => $branchName): ?>
                    <option
                        value="<?= (int) $branchId ?>"
                        <?= (string) ($filters['branch_id'] ?? '') === (string) $branchId ? 'selected' : '' ?>
                    >
                        <?= esc($branchName) ?>
                    </option>
                <?php endforeach ?>
            </select>
        </div>
<?php endif ?>
        <div class="col-12 col-md-3 mb-2">
            <label for="session-batch">Batch</label>
            <select class="form-control" id="session-batch" name="batch_id">
                <option value="">All batches</option>
                <?php foreach (($batchOptions ?? []) as $batchId => $batchName): ?>
                    <option value="<?= (int) $batchId ?>" <?= (string) ($filters['batch_id'] ?? '') === (string) $batchId ? 'selected' : '' ?>><?= esc($batchName) ?></option>
                <?php endforeach ?>
            </select>
        </div>
        <div class="col-12 col-md-3 mb-2">
            <label for="session-coach">Coach</label>
            <select class="form-control" id="session-coach" name="coach_user_id">
                <option value="">All coaches</option>
                <?php foreach (($coachOptions ?? []) as $coachId => $coachName): ?>
                    <option value="<?= (int) $coachId ?>" <?= (string) ($filters['coach_user_id'] ?? '') === (string) $coachId ? 'selected' : '' ?>><?= esc($coachName) ?></option>
                <?php endforeach ?>
            </select>
        </div>
        <div class="col-12 col-md-3 mb-2">
            <label for="session-ground">Ground</label>
            <select class="form-control" id="session-ground" name="ground_id">
                <option value="">All grounds</option>
                <?php foreach (($groundOptions ?? []) as $groundId => $groundName): ?>
                    <option value="<?= (int) $groundId ?>" <?= (string) ($filters['ground_id'] ?? '') === (string) $groundId ? 'selected' : '' ?>><?= esc($groundName) ?></option>
                <?php endforeach ?>
            </select>
        </div>
        <div class="col-12 mt-2 mcc-form-actions">
            <button class="mcc-button mcc-button-primary" type="submit">Apply filters</button>
            <a class="mcc-button mcc-button-secondary" href="<?= esc(site_url('sessions')) ?>">Clear filters</a>
        </div>
    </form>
</section>
<section class="mcc-card mcc-scheduling-card mcc-session-results-card" aria-labelledby="sessions-results-heading">
    <div class="mcc-session-summary" aria-live="polite">
        <div>
            <span class="mcc-eyebrow">Sessions</span>
            <h2 id="sessions-results-heading">Session results</h2>
        </div>
        <div class="mcc-session-summary-meta">
            <span><strong><?= (int) ($total ?? 0) ?></strong> result<?= (int) ($total ?? 0) === 1 ? '' : 's' ?></span>
            <?php if ($hasFilters): ?>
                <span class="mcc-badge mcc-badge-info"><?= (int) $activeFilterCount ?> active filter<?= $activeFilterCount === 1 ? '' : 's' ?></span>
                <span class="mcc-muted">(<?= esc(implode(', ', $activeFilterLabels)) ?>)</span>
            <?php else: ?>
                <span class="mcc-muted">All authorized branches</span>
            <?php endif ?>
        </div>
    </div>
    <?php if (($sessions ?? []) === []): ?>
        <div class="mcc-empty-state" role="status">
            <i class="fa-regular fa-calendar-xmark mcc-empty-icon" aria-hidden="true"></i>
            <h2>No sessions match your filters</h2>
            <p>Try changing or clearing the filters to see more sessions.</p>
        </div>
    <?php else: ?>
        <div class="mcc-table-wrap mcc-session-table-wrap">
            <table class="mcc-table mcc-session-list-table">
                <caption class="visually-hidden">Sessions</caption>
                <thead>
                    <tr>
                        <th scope="col">Session/date</th>
                        <th scope="col">Branch</th>
                        <th scope="col">Program</th>
                        <th scope="col">Session Type</th>
                        <th scope="col">Category</th>
                        <th scope="col">Coach</th>
                        <th scope="col">Selected Players</th>
                        <th scope="col">Status</th>
                        <th scope="col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (($sessions ?? []) as $session): ?>
                        <?php
                        $status = strtolower(trim((string) ($session['status'] ?? '')));
                        $statusMeta = $statusDefinitions[$status] ?? ['label' => 'Unknown', 'class' => 'neutral'];
                        $sessionType = match ((string) ($session['session_type'] ?? '')) {
                            'one_to_one' => 'One-to-One',
                            'group' => 'Group',
                            'elite' => 'Elite',
                            default => 'Not set',
                        };
                        $branchId = (int) ($session['branch_id'] ?? 0);
                        $sessionId = (int) ($session['id'] ?? 0);
                        $canUpdate = $branchId > 0 && service('authorization')->can('session.update', (string) $branchId);
                        $canAttendanceView = $branchId > 0 && service('authorization')->can('attendance.view', (string) $branchId);
                        ?>
                        <tr>
                            <th scope="row" data-label="Session/date">
                                <span class="mcc-session-date">
                                    <?= esc($formatSessionDateTime($session['starts_at_local'] ?? $session['starts_at'] ?? null)) ?>
                                </span>
                            </th>
                            <td data-label="Branch">
                                <?= esc($displayValue($session['branch_name'] ?? null)) ?>
                            </td>
                            <td data-label="Program">
                                <?= esc($displayValue($session['program_name'] ?? null)) ?>
                            </td>
                            <td data-label="Session Type">
                                <?= esc($sessionType) ?>
                            </td>
                            <td data-label="Category">
                                <?= esc(
                                    trim((string) ($session['batch_name'] ?? '')) !== ''
                                        && trim((string) ($session['age_group_name'] ?? '')) !== ''
                                        ? $session['batch_name'] . ' · ' . $session['age_group_name']
                                        : '—'
                                ) ?>
                            </td>
                            <td data-label="Coach">
                                <?= esc($displayValue($session['coach_name'] ?? null)) ?>
                            </td>
                            <td data-label="Selected Players">
                                <?= (int) ($session['selected_player_count'] ?? 0) ?>
                            </td>
                            <td data-label="Status">
                                <span class="mcc-badge mcc-badge-<?= esc($statusMeta['class']) ?>">
                                    <?= esc($statusMeta['label']) ?>
                                </span>
                            </td>
                            <td data-label="Actions">
                                <div class="mcc-session-action-menu" data-session-actions>
                                    <button
                                        class="mcc-button mcc-button-secondary"
                                        type="button"
                                        data-session-actions-toggle
                                        aria-expanded="false"
                                        aria-haspopup="true"
                                        aria-controls="session-actions-<?= $sessionId ?>"
                                    >
                                        <i class="fa-solid fa-ellipsis-vertical" aria-hidden="true"></i>
                                        <span>Actions</span>
                                    </button>
                                    <div class="mcc-session-action-dropdown" id="session-actions-<?= $sessionId ?>" data-session-actions-menu hidden>
                                        <a href="<?= esc(site_url('sessions/' . $sessionId . '/page')) ?>">
                                            <i class="fa-solid fa-eye" aria-hidden="true"></i>
                                            View session
                                        </a>
                                        <?php if ($canUpdate && $status === 'scheduled'): ?>
                                            <a href="<?= esc(site_url('sessions/' . $sessionId . '/edit')) ?>">
                                                <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
                                                Edit session
                                            </a>
                                        <?php endif ?>
                                        <?php if ($canAttendanceView): ?>
                                            <a href="<?= esc(site_url('attendance/sessions/' . $sessionId)) ?>">
                                                <i class="fa-solid fa-clipboard-check" aria-hidden="true"></i>
                                                Attendance
                                            </a>
                                        <?php endif ?>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        </div>
    <?php endif ?>
    <?php if ($pageCount > 1): ?>
        <nav class="mcc-pagination" aria-label="Session pages">
            <span>Page <?= $currentPage ?> of <?= $pageCount ?></span>
            <div class="mcc-pagination-actions">
                <?php if ($currentPage > 1): ?>
                    <a
                        class="mcc-button mcc-button-secondary"
                        href="<?= esc($pageUrl($currentPage - 1)) ?>"
                        aria-label="Previous page"
                    >
                        Previous
                    </a>
                <?php else: ?>
                    <span class="mcc-button mcc-button-secondary" aria-disabled="true" aria-label="Previous page">
                        Previous
                    </span>
                <?php endif ?>
                <?php foreach ($pageWindow as $pageToken): ?>
                    <?php if ($pageToken === '…'): ?>
                        <span class="mcc-pagination-ellipsis" aria-hidden="true">…</span>
                    <?php else: ?>
                        <a
                            class="mcc-button <?= (int) $pageToken === $currentPage ? 'mcc-button-primary' : 'mcc-button-secondary' ?>"
                            href="<?= esc($pageUrl((int) $pageToken)) ?>"
                            <?= (int) $pageToken === $currentPage ? 'aria-current="page"' : '' ?>
                            aria-label="Page <?= (int) $pageToken ?>"
                        >
                            <?= (int) $pageToken ?>
                        </a>
                    <?php endif ?>
                <?php endforeach ?>
                <?php if ($currentPage < $pageCount): ?>
                    <a
                        class="mcc-button mcc-button-secondary"
                        href="<?= esc($pageUrl($currentPage + 1)) ?>"
                        aria-label="Next page"
                    >
                        Next
                    </a>
                <?php else: ?>
                    <span class="mcc-button mcc-button-secondary" aria-disabled="true" aria-label="Next page">
                        Next
                    </span>
                <?php endif ?>
            </div>
        </nav>
    <?php endif ?>
</section>
<?= view('staff_footer') ?>
