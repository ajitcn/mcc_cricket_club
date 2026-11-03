<?php
$filters = is_array($filters ?? null) ? $filters : [];
$queryFilters = array_filter(
    $filters,
    static fn ($value, $key): bool => $key !== 'page'
        && $value !== null
        && $value !== '',
    ARRAY_FILTER_USE_BOTH
);
$selectedBranchId = (int) ($filters['branch_id'] ?? 0);
$visibleBatchOptions = $selectedBranchId > 0
    ? ($batchesByBranch[$selectedBranchId] ?? [])
    : $batchOptions;
$pageUrl = static function (int $targetPage) use ($queryFilters): string {
    $query = http_build_query(array_merge($queryFilters, ['page' => $targetPage]));

    return site_url('attendance') . ($query === '' ? '' : '?' . $query);
};
$sessionStatusMeta = [
    'scheduled' => [
        'label' => 'Scheduled',
        'class' => 'mcc-attendance-status-ready',
        'icon' => 'fa-calendar-check',
    ],
    'cancelled' => [
        'label' => 'Cancelled',
        'class' => 'mcc-attendance-status-cancelled',
        'icon' => 'fa-lock',
    ],
];
?>
<?= view('staff_header', [
    'title' => 'Attendance sessions',
    'nav' => $nav,
    'error' => $error,
    'message' => $message,
    'breadcrumbTrail' => [
        ['label' => 'Attendance'],
    ],
    'pageActions' => '<div class="mcc-page-actions">
        <button class="mcc-button mcc-button-secondary" type="button"
            data-filter-toggle aria-controls="attendance-filters" aria-expanded="false">
            <i class="fa-solid fa-filter" aria-hidden="true"></i>
            Filters
        </button>
    </div>',
]) ?>
<?php if (! empty($error)): ?>
<p class="message" role="alert">
<?= esc($error) ?>
</p>
<?php endif ?>
<?php if (! empty($message)): ?>
<p class="message" role="status">
<?= esc($message) ?>
</p>
<?php endif ?>
<section id="attendance-filters" class="mcc-card" hidden aria-labelledby="filter-heading">
<div class="mcc-card-heading">
<div>
<span class="mcc-eyebrow">Search and filter</span>
<h2 id="filter-heading">Find a session or roster record</h2>
</div>
</div>
<form class="filter-form mcc-form-grid" method="get" action="<?= site_url('attendance') ?>">
<label for="session-date">Date <input id="session-date" type="text" inputmode="numeric" placeholder="DD-MM-YYYY" name="date"
    data-mcc-date-picker data-mcc-date-format="d-m-Y" data-mcc-date-submit-format="Y-m-d"
    value="<?= esc(mcc_user_date_input($filters['date'] ?? null)) ?>">
</label>
<label for="session-id">Session ID <input id="session-id" type="number" min="1" name="session_id" value="<?= esc($filters['session_id'] ?? '') ?>">
</label>
<label for="session-player">Player <input id="session-player" type="search" maxlength="100" name="player" value="<?= esc($filters['player'] ?? '') ?>">
</label>
<label for="attendance-status">Attendance status <select id="attendance-status" name="attendance_status">
<option value="">All attendance states</option>
<?php foreach (($attendanceStatuses ?? ['PRESENT', 'ABSENT', 'LEAVE', 'MEDICAL', 'LATE', 'RAIN_CANCELLED', 'COACH_CANCELLED']) as $state): ?>
<option value="<?= $state ?>" <?= ($filters['attendance_status'] ?? '') === $state ? 'selected' : '' ?>>
<?= esc(ucwords(strtolower(str_replace('_', ' ', $state)))) ?>
</option>
<?php endforeach ?>
</select>
</label>
<label for="session-status">Session status <select id="session-status" name="status">
<option value="">All session statuses</option>
<option value="scheduled" <?= ($filters['status'] ?? '') === 'scheduled' ? 'selected' : '' ?>>Scheduled</option>
<option value="cancelled" <?= ($filters['status'] ?? '') === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
</select>
</label>
<?php if (count($branchOptions) > 1): ?>
<label for="session-branch">Branch <select id="session-branch" name="branch_id">
<option value="">All authorized branches</option>
<?php foreach ($branchOptions as $branchId => $branchName): ?>
<option value="<?= (int) $branchId ?>" <?= (string) ($filters['branch_id'] ?? '') === (string) $branchId ? 'selected' : '' ?>>
<?= esc($branchName) ?>
</option>
<?php endforeach ?>
</select>
</label>
<?php endif ?>
<label for="session-batch">Batch <select id="session-batch" name="batch_id"
    data-mcc-attendance-batches-by-branch="<?= esc(json_encode($batchesByBranch ?? [])) ?>">
<option value="">All authorized batches</option>
<?php foreach ($visibleBatchOptions as $batchId => $batchName): ?>
<option value="<?= (int) $batchId ?>" <?= (string) ($filters['batch_id'] ?? '') === (string) $batchId ? 'selected' : '' ?>>
<?= esc($batchName) ?>
</option>
<?php endforeach ?>
</select>
</label>
<button type="submit">Apply filters</button>
<a href="<?= site_url('attendance') ?>">Clear filters</a>
</form>
</section>
<section class="mcc-card" aria-labelledby="attendance-report-heading">
<div class="mcc-card-heading">
<h2 id="attendance-report-heading">Attendance summary</h2>
<span class="mcc-muted">Configured local calendar period</span>
</div>
<form class="filter-form mcc-form-grid" method="get" action="<?= site_url('attendance') ?>">
<label for="attendance-period">Reporting period
<select id="attendance-period" name="period">
<?php foreach (($reportingPeriods ?? []) as $periodKey => $periodLabel): ?>
<option value="<?= esc($periodKey) ?>" <?= ($filters['period'] ?? 'monthly') === $periodKey ? 'selected' : '' ?>><?= esc($periodLabel) ?></option>
<?php endforeach ?>
</select>
</label>
<label for="attendance-report-date">Anchor date
<input id="attendance-report-date" type="text" inputmode="numeric" placeholder="DD-MM-YYYY" autocomplete="off" data-mcc-date-picker data-mcc-date-picker-no-toggle data-mcc-date-format="d-m-Y" data-mcc-date-submit-format="Y-m-d" name="report_date" value="<?= esc(mcc_user_date_input($filters['report_date'] ?? null)) ?>">
</label>
<button type="submit">Refresh summary</button>
</form>
<?php $metrics = is_array($attendanceMetrics ?? null) ? $attendanceMetrics : []; ?>
<p class="mcc-muted">
<?= esc((string) ($metrics['from'] ?? '')) ?> to <?= esc((string) ($metrics['to'] ?? '')) ?>:
<?= $metrics['percentage'] === null ? 'No data' : esc((string) $metrics['percentage']) . '%' ?>
(<?= (int) ($metrics['attended'] ?? 0) ?> attended of <?= (int) ($metrics['denominator'] ?? 0) ?> eligible records)
</p>
<?php if (($attendanceAlerts ?? []) !== []): ?>
<ul class="mcc-alert-list" aria-label="Attendance alerts">
<?php foreach ($attendanceAlerts as $alert): ?>
<li><strong><?= esc($alert['player_name']) ?></strong>: <?= esc(implode(', ', $alert['alerts'])) ?></li>
<?php endforeach ?>
</ul>
<?php else: ?>
<p class="mcc-empty-state">No attendance alerts for this period.</p>
<?php endif ?>
</section>
<section class="mcc-card" aria-labelledby="attendance-sessions-heading">
<div class="mcc-card-heading">
<h2 id="attendance-sessions-heading">Attendance sessions</h2>
<span class="mcc-muted"><?= (int) ($total ?? 0) ?> result<?= (int) ($total ?? 0) === 1 ? '' : 's' ?></span>
</div>
<div class="mcc-table-wrap">
<table class="mcc-table">
<caption>Sessions available to your authorized branches</caption>
<thead>
<tr>
<th scope="col">When</th>
<th scope="col">Branch</th>
<th scope="col">Batch</th>
<th scope="col">Coach</th>
<th scope="col">Recorded / roster</th>
<th scope="col">Status and action</th>
</tr>
</thead>
<tbody>
<?php foreach ($sessions as $item): ?>
<?php $sessionStatus = strtolower((string) $item['status']); ?>
<?php $sessionMeta = $sessionStatusMeta[$sessionStatus] ?? $sessionStatusMeta['cancelled']; ?>
<tr class="<?= $sessionStatus === 'cancelled' ? 'cancelled attendance-session-cancelled' : '' ?>">
<td data-label="When">
<?= esc(mcc_user_datetime($item['starts_at_local'] ?? null)) ?> &ndash; <?= esc(mcc_user_datetime($item['ends_at_local'] ?? null)) ?>
</td>
<td data-label="Branch">
<?= esc($item['branch_name']) ?>
</td>
<td data-label="Batch">
<?= esc((string) ($item['batch_name'] ?? 'Not assigned')) ?>
</td>
<td data-label="Coach">
<?= esc($item['coach_name']) ?>
</td>
<td data-label="Recorded / roster">
<?= (int) $item['recorded_count'] ?> / <?= (int) $item['roster_count'] ?>
</td>
<td data-label="Status and action">
<span class="mcc-badge mcc-status-badge <?= esc($sessionMeta['class']) ?>">
<i class="fa-solid <?= esc($sessionMeta['icon']) ?>" aria-hidden="true"></i>
<?= esc($sessionMeta['label']) ?>
</span>
<?php if ($sessionStatus === 'cancelled'): ?>
<br>
<span class="muted"><i class="fa-solid fa-eye" aria-hidden="true"></i> Read-only session</span>
<?php endif ?>
<br>
<a href="<?= site_url('attendance/sessions/' . (int) $item['id']) ?>">Open roster</a>
</td>
</tr>
<?php endforeach ?>
<?php if ($sessions === []): ?>
<tr>
<td colspan="6" class="mcc-empty-state attendance-empty-state">
<i class="fa-solid fa-calendar-xmark" aria-hidden="true"></i>
<strong>No attendance sessions found</strong>
<span>No sessions match the selected filters.</span>
<a href="<?= site_url('attendance') ?>">Clear filters and view all authorized sessions</a>
</td>
</tr>
<?php endif ?>
</tbody>
</table>
</div>
<?php if ((int) ($pages ?? 1) > 1): ?>
<nav class="mcc-pagination" aria-label="Attendance session pages">
<span>Page <?= (int) ($page ?? 1) ?> of <?= (int) ($pages ?? 1) ?></span>
<div>
<?php if ((int) ($page ?? 1) > 1): ?>
<a class="mcc-button mcc-button-secondary" href="<?= esc($pageUrl((int) $page - 1)) ?>">Previous</a>
<?php endif; ?>
<?php for ($targetPage = 1; $targetPage <= (int) ($pages ?? 1); $targetPage++): ?>
<a class="mcc-button mcc-button-secondary" href="<?= esc($pageUrl($targetPage)) ?>" <?= $targetPage === (int) ($page ?? 1) ? 'aria-current="page"' : '' ?>>
<?= $targetPage ?>
</a>
<?php endfor; ?>
<?php if ((int) ($page ?? 1) < (int) ($pages ?? 1)): ?>
<a class="mcc-button mcc-button-secondary" href="<?= esc($pageUrl((int) $page + 1)) ?>">Next</a>
<?php endif; ?>
</div>
</nav>
<?php endif; ?>
</section>
<?= view('staff_footer') ?>
