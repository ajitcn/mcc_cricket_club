<?php
$filters = is_array($filters ?? null) ? $filters : [];
$queryFilters = array_filter(
    $filters,
    static fn ($value, $key): bool => $key !== 'page'
        && $value !== null
        && $value !== '',
    ARRAY_FILTER_USE_BOTH
);
$pageUrl = static function (int $targetPage) use ($queryFilters): string {
    $query = http_build_query(array_merge($queryFilters, ['page' => $targetPage]));

    return site_url('attendance') . ($query === '' ? '' : '?' . $query);
};
$attendanceStatusMeta = [
    'PRESENT' => ['label' => 'Present', 'class' => 'mcc-status-badge-success'],
    'ABSENT' => ['label' => 'Absent', 'class' => 'mcc-status-badge-danger'],
    'LATE' => ['label' => 'Late', 'class' => 'mcc-status-badge-warning'],
    'LEAVE' => ['label' => 'Leave', 'class' => 'mcc-status-badge-info'],
    'MEDICAL' => ['label' => 'Medical', 'class' => 'mcc-status-badge-info'],
    'RAIN_CANCELLED' => ['label' => 'Rain cancelled', 'class' => 'mcc-status-badge-neutral'],
    'COACH_CANCELLED' => ['label' => 'Coach cancelled', 'class' => 'mcc-status-badge-neutral'],
];
$summaryFilters = $queryFilters;
unset($summaryFilters['attendance_status'], $summaryFilters['page']);
$summaryCards = [
    'PRESENT' => ['label' => 'Present', 'class' => 'mcc-player-status-active'],
    'ABSENT' => ['label' => 'Absent', 'class' => 'mcc-player-status-inactive'],
    'LATE' => ['label' => 'Late', 'class' => 'mcc-player-status-suspended'],
    'LEAVE' => ['label' => 'Leave', 'class' => 'mcc-player-status-left_academy'],
    'MEDICAL' => ['label' => 'Medical', 'class' => 'mcc-player-status-alumni'],
];
?>
<?= view('staff_header', [
    'title' => 'Attendance records',
    'nav' => $nav,
    'error' => $error,
    'message' => $message,
    'breadcrumbTrail' => [
        ['label' => 'Attendance'],
    ],
    'pageActions' => '<div class="mcc-page-actions">
        <button class="mcc-button mcc-button-secondary" type="button"
            data-filter-toggle data-show-label="Show Filters" data-hide-label="Hide Filters"
            aria-controls="attendance-filters" aria-expanded="false">
            <i class="fa-solid fa-filter" aria-hidden="true"></i>
            <span class="mcc-button-label">Show Filters</span>
        </button>
    </div>',
]) ?>
<?php if (! empty($error)): ?>
<div class="mcc-alert mcc-alert-danger" role="alert">
<?= esc($error) ?>
</div>
<?php endif ?>
<?php if (! empty($message)): ?>
<div class="mcc-alert mcc-alert-success" role="status">
<?= esc($message) ?>
</div>
<?php endif ?>
<section id="attendance-filters" class="mcc-card mcc-attendance-filter-card" hidden aria-labelledby="filter-heading">
<div class="mcc-card-heading">
<div>
<span class="mcc-eyebrow">Search and filter</span>
<h2 id="filter-heading">Find attendance records</h2>
</div>
</div>
<form class="filter-form mcc-form-grid mcc-attendance-filter-grid mcc-attendance-filter-form" method="get" action="<?= site_url('attendance') ?>">
<label for="session-player">Player or player number <input id="session-player" type="search" maxlength="100" name="player" value="<?= esc($filters['player'] ?? '') ?>" placeholder="Search player">
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
<label for="session-batch">Batch <select id="session-batch" name="batch_id">
<option value="">All authorized batches</option>
<?php foreach (($batchOptions ?? []) as $batchId => $batchName): ?>
<option value="<?= (int) $batchId ?>" <?= (string) ($filters['batch_id'] ?? '') === (string) $batchId ? 'selected' : '' ?>>
<?= esc($batchName) ?>
</option>
<?php endforeach ?>
</select>
</label>
<label for="session-coach">Coach <select id="session-coach" name="coach_user_id">
<option value="">All coaches</option>
<?php foreach (($coachOptions ?? []) as $coachId => $coachName): ?>
<option value="<?= (int) $coachId ?>" <?= (string) ($filters['coach_user_id'] ?? '') === (string) $coachId ? 'selected' : '' ?>><?= esc($coachName) ?></option>
<?php endforeach ?>
</select>
</label>
<label for="recorded-from">Recorded from <input id="recorded-from" type="text" inputmode="numeric" placeholder="DD-MM-YYYY" name="recorded_from" data-mcc-date-picker data-mcc-date-format="d-m-Y" data-mcc-date-submit-format="Y-m-d" value="<?= esc(mcc_user_date_input($filters['recorded_from'] ?? null)) ?>">
</label>
<label for="recorded-to">Recorded to <input id="recorded-to" type="text" inputmode="numeric" placeholder="DD-MM-YYYY" name="recorded_to" data-mcc-date-picker data-mcc-date-format="d-m-Y" data-mcc-date-submit-format="Y-m-d" value="<?= esc(mcc_user_date_input($filters['recorded_to'] ?? null)) ?>">
</label>
<label for="session-id">Session ID <input id="session-id" type="number" min="1" name="session_id" value="<?= esc($filters['session_id'] ?? '') ?>">
</label>
<div class="mcc-form-actions">
<button class="mcc-button mcc-button-primary" type="submit">Apply filters</button>
<a class="mcc-button mcc-button-secondary" href="<?= site_url('attendance') ?>">Clear filters</a>
</div>
</form>
</section>
<section class="mcc-card mcc-attendance-summary-card" aria-labelledby="attendance-summary-heading">
<div class="mcc-session-summary">
<div>
<span class="mcc-eyebrow">Current view</span>
<h2 id="attendance-summary-heading">Attendance summary</h2>
</div>
</div>
<div class="mcc-follow-up-summary-grid mcc-admission-status-summary-grid">
<?php foreach ($summaryCards as $status => $card): ?>
<?php $statusQuery = array_merge($summaryFilters, ['attendance_status' => $status]); ?>
<a class="mcc-follow-up-summary-card mcc-status-badge <?= esc($card['class']) ?>" href="<?= esc(site_url('attendance') . '?' . http_build_query($statusQuery)) ?>">
<span class="mcc-follow-up-summary-label"><?= esc($card['label']) ?></span>
<strong class="mcc-follow-up-summary-count"><?= (int) (($statusCounts ?? [])[$status] ?? 0) ?></strong>
</a>
<?php endforeach ?>
</div>
</section>
<section class="mcc-card mcc-attendance-results-card" aria-labelledby="attendance-records-heading">
<div class="mcc-session-summary">
<div>
<span class="mcc-eyebrow">Latest records</span>
<h2 id="attendance-records-heading">Player attendance</h2>
</div>
<span class="mcc-muted"><?= (int) ($total ?? 0) ?> result<?= (int) ($total ?? 0) === 1 ? '' : 's' ?></span>
</div>
<div class="mcc-table-wrap">
<table class="mcc-table">
<caption>Latest attendance records for your authorized branches</caption>
<thead>
<tr>
<th scope="col">Player</th>
<th scope="col">Branch</th>
<th scope="col">Attendance</th>
<th scope="col">Recorded</th>
<th scope="col">Session</th>
</tr>
</thead>
<tbody>
<?php foreach ($sessions as $item): ?>
<?php $statusKey = strtoupper((string) ($item['status'] ?? '')); ?>
<?php $statusMeta = $attendanceStatusMeta[$statusKey] ?? ['label' => ucwords(strtolower(str_replace('_', ' ', $statusKey))), 'class' => 'mcc-status-badge-neutral']; ?>
<tr>
<td data-label="Player">
<span class="mcc-table-primary-text"><?= esc($item['player_name']) ?></span>
<span class="mcc-table-secondary-text">Player #<?= (int) $item['player_id'] ?></span>
</td>
<td data-label="Branch">
<span class="mcc-table-primary-text"><?= esc($item['branch_name']) ?></span>
<span class="mcc-table-secondary-text">
<?= esc($item['batch_name'] ?? 'No batch') ?>
</span>
</td>
<td data-label="Attendance">
<span class="mcc-attendance-record-status <?= esc($statusMeta['class']) ?>">
<?= esc($statusMeta['label']) ?>
</span>
</td>
<td data-label="Recorded">
<span class="mcc-table-primary-text"><?= esc(mcc_user_datetime($item['recorded_at'] ?? null)) ?></span>
<span class="mcc-table-secondary-text">By <?= esc($item['recorded_by_name'] ?? 'Unknown') ?></span>
</td>
<td data-label="Session">
<span class="mcc-table-primary-text">Session #<?= (int) $item['session_id'] ?></span>
<span class="mcc-table-secondary-text">
<?= esc(mcc_user_datetime($item['starts_at'] ?? null)) ?>
</span>
<span class="mcc-table-secondary-text">
Coach: <?= esc($item['coach_name'] ?? 'No coach') ?>
</span>
<a class="mcc-button mcc-button-secondary mcc-button-small" href="<?= site_url('attendance/sessions/' . (int) $item['session_id']) ?>">View session</a>
</td>
</tr>
<?php endforeach ?>
<?php if ($sessions === []): ?>
<tr>
<td colspan="5" class="mcc-empty-state attendance-empty-state">
<i class="fa-solid fa-calendar-xmark" aria-hidden="true"></i>
<strong>No attendance records found</strong>
<span>No attendance records match the selected filters.</span>
<a href="<?= site_url('attendance') ?>">Clear filters and view all authorized records</a>
</td>
</tr>
<?php endif ?>
</tbody>
</table>
</div>
<?php if ((int) ($pages ?? 1) > 1): ?>
<nav class="mcc-pagination" aria-label="Attendance record pages">
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
