<?php
$reportType = (string) ($result['type'] ?? 'report');
$filters = (array) ($result['filters'] ?? []);
$rows = (array) ($result['rows'] ?? []);
$activeFilters = array_filter(
    $filters,
    static fn ($value): bool => $value !== null && $value !== ''
);
$csvQuery = http_build_query($activeFilters);
$csvUrl = site_url('reports/' . rawurlencode($reportType) . '/csv')
    . ($csvQuery !== '' ? '?' . $csvQuery : '');
$statusOptions = [];
$statusClasses = [
    'active' => 'mcc-badge-success',
    'present' => 'mcc-badge-success',
    'absent' => 'mcc-badge-danger',
    'late' => 'mcc-badge-warning',
    'leave' => 'mcc-badge-warning',
    'medical' => 'mcc-badge-warning',
    'excused' => 'mcc-badge-info',
    'rain_cancelled' => 'mcc-badge-danger',
    'coach_cancelled' => 'mcc-badge-danger',
    'approved' => 'mcc-badge-success',
    'completed' => 'mcc-badge-success',
    'converted' => 'mcc-badge-success',
    'scheduled' => 'mcc-badge-info',
    'pending' => 'mcc-badge-warning',
    'on_hold' => 'mcc-badge-warning',
    'cancelled' => 'mcc-badge-danger',
    'rejected' => 'mcc-badge-danger',
    'failed' => 'mcc-badge-danger',
];
foreach ($rows as $row) {
    if (isset($row['status']) && trim((string) $row['status']) !== '') {
        $statusOptions[(string) $row['status']] = (string) $row['status'];
    }
}
ksort($statusOptions);
$reportColumns = static function (array $row, string $type) use ($statusClasses): array {
    $value = static fn($item, string $fallback = '—'): string => trim((string) ($item ?? '')) === '' ? $fallback : (string) $item;
    $date = static fn($item): string => mcc_user_datetime($item !== null ? (string) $item : null, 'Not available');
    $status = static function ($item) use ($statusClasses): string {
        $statusValue = strtolower(trim((string) $item));
        $statusClass = $statusClasses[$statusValue] ?? 'mcc-badge-neutral';

        return '<span class="mcc-badge ' . esc($statusClass) . '">'
            . esc(ucwords(str_replace('_', ' ', (string) $item)))
            . '</span>';
    };

    return match ($type) {
        'players' => [
            'Player' => '<strong>' . esc($value($row['full_name'] ?? null, 'Unnamed player')) . '</strong><span class="mcc-table-secondary-text">' . esc($value($row['admission_number'] ?? null, 'Reference not assigned')) . '</span>',
            'Contact' => esc($value($row['email'] ?? null, 'Email not available')) . '<span class="mcc-table-secondary-text">' . esc($value($row['phone'] ?? null, 'Phone not available')) . '</span>',
            'Placement' => '<span>' . esc($value($row['branch_name'] ?? null)) . '</span><span class="mcc-table-secondary-text">' . esc($value($row['batch_name'] ?? null, 'Batch not assigned')) . ' · ' . esc($value($row['age_group'] ?? null, 'Age group not assigned')) . '</span>',
            'Coaches' => esc($value($row['group_coach_name'] ?? null, 'Group coach not assigned')) . '<span class="mcc-table-secondary-text">' . esc($value($row['personal_coach_name'] ?? null, 'Personal coach not assigned')) . '</span>',
            'Status / Created' => $status($row['status'] ?? null) . '<span class="mcc-table-secondary-text">' . esc($date($row['created_at'] ?? null)) . '</span>',
        ],
        'admissions' => [
            'Admission' => '<strong>' . esc($value($row['full_name'] ?? null, 'Unnamed applicant')) . '</strong><span class="mcc-table-secondary-text">' . esc($value($row['admission_number'] ?? null)) . '</span>',
            'Branch / Batch' => esc($value($row['branch_name'] ?? null)) . '<span class="mcc-table-secondary-text">' . esc($value($row['batch_name'] ?? null, 'Batch not assigned')) . '</span>',
            'Profile' => '<span>' . esc($value($row['gender'] ?? null, 'Gender not set')) . ' · ' . esc($value($row['age'] ?? null, 'Age not set')) . '</span><span class="mcc-table-secondary-text">' . esc($value($row['age_group'] ?? null, 'Age group not set')) . ' · ' . esc($value($row['playing_role'] ?? null, 'Role not set')) . '</span>',
            'Coaching' => '<span>' . esc(ucwords(str_replace('_', ' ', $value($row['coaching_type'] ?? null, 'Coaching not set')))) . '</span><span class="mcc-table-secondary-text">' . esc($value($row['group_coach_name'] ?? null, 'Group coach not assigned')) . ' · ' . esc($value($row['personal_coach_name'] ?? null, 'Personal coach not assigned')) . '</span>',
            'Status' => $status($row['status'] ?? null),
            'Dates' => '<span>' . esc($date($row['admission_date'] ?? null)) . '</span><span class="mcc-table-secondary-text">Created ' . esc($date($row['created_at'] ?? null)) . '</span>',
        ],
        'attendance' => [
            'Player' => '<strong>' . esc($value($row['player_name'] ?? null, 'Unknown player')) . '</strong><span class="mcc-table-secondary-text">' . esc($value($row['admission_number'] ?? null, 'Reference not assigned')) . '</span>',
            'Session' => '<span>' . esc($date($row['starts_at'] ?? null)) . '</span><span class="mcc-table-secondary-text">End ' . esc($date($row['ends_at'] ?? null)) . '</span><span class="mcc-table-secondary-text">' . esc($value($row['batch_name'] ?? null, 'Batch not assigned')) . ' · ' . esc(ucwords(str_replace('_', ' ', $value($row['session_type'] ?? null, 'Session')))) . '</span><span class="mcc-table-secondary-text">' . esc($value($row['program_name'] ?? null, 'Program not assigned')) . ' · ' . esc($value($row['ground_name'] ?? null, 'Ground not assigned')) . '</span>',
            'Coach / Recorded by' => esc($value($row['coach_name'] ?? null, 'Not assigned')) . '<span class="mcc-table-secondary-text">' . esc($value($row['recorded_by_name'] ?? null, 'Not available')) . '</span>',
            'Status' => $status($row['status'] ?? null) . '<span class="mcc-table-secondary-text">Session: ' . esc(ucwords(str_replace('_', ' ', $value($row['session_status'] ?? null, 'Not available')))) . '</span>',
            'Recorded' => esc($date($row['recorded_at'] ?? null)),
        ],
        'sessions' => [
            'Session' => '<strong>' . esc(ucwords(str_replace('_', ' ', $value($row['session_type'] ?? null, 'Session')))) . '</strong><span class="mcc-table-secondary-text">' . esc($value($row['program_name'] ?? null, 'Program not assigned')) . '</span>',
            'Branch / Batch' => esc($value($row['branch_name'] ?? null)) . '<span class="mcc-table-secondary-text">' . esc($value($row['batch_name'] ?? null, 'Batch not assigned')) . '</span>',
            'Program / Coach' => esc($value($row['program_name'] ?? null, 'Program not assigned')) . '<span class="mcc-table-secondary-text">' . esc($value($row['coach_name'] ?? null, 'Coach not assigned')) . '</span>',
            'Status' => $status($row['status'] ?? null),
            'Schedule' => '<span>' . esc($date($row['starts_at'] ?? null)) . '</span><span class="mcc-table-secondary-text">End ' . esc($date($row['ends_at'] ?? null)) . '</span>',
        ],
        'matches' => [
            'Match' => '<strong>' . esc(ucwords(str_replace('_', ' ', $value($row['match_category'] ?? null, 'Regular')))) . '</strong><span class="mcc-table-secondary-text">Match record</span>',
            'Teams' => '<strong>' . esc($value($row['your_team'] ?? null, 'Team not set')) . '</strong><span class="mcc-table-secondary-text">vs ' . esc($value($row['opponent'] ?? null, 'Opponent not set')) . '</span>',
            'Branch / Ground' => esc($value($row['branch_name'] ?? null)) . '<span class="mcc-table-secondary-text">' . esc($value($row['ground_name'] ?? null, 'Ground not assigned')) . '</span>',
            'Status' => $status($row['status'] ?? null),
            'Result / Score' => esc(ucwords(str_replace('_', ' ', $value($row['result'] ?? null, 'Not recorded')))) . '<span class="mcc-table-secondary-text">' . esc($value($row['score_summary'] ?? null, 'Score not recorded')) . '</span>',
            'Scheduled' => esc($date($row['starts_at'] ?? null)),
        ],
        default => [],
    };
};
$pageActions = '<div class="mcc-page-actions">'
    . '<button class="mcc-button mcc-button-secondary" type="button" data-filter-toggle '
    . 'data-show-label="Show Filters" data-hide-label="Hide Filters" '
    . 'aria-controls="report-filters" aria-expanded="false">'
    . '<i class="fa-solid fa-filter" aria-hidden="true"></i><span class="mcc-button-label">Show Filters</span></button>'
    . '<a class="mcc-button mcc-button-primary" href="' . esc($csvUrl) . '">'
    . '<i class="fa-solid fa-file-csv" aria-hidden="true"></i><span class="mcc-button-label">Export CSV</span></a>'
    . '</div>';
?>
<?= view('staff_header', [
    'title' => ($result['title'] ?? 'Report') . ' report',
    'nav' => $nav ?? null,
    'message' => $message ?? null,
    'error' => $error ?? null,
    'pageActions' => $pageActions,
]) ?>

<div class="mcc-report-page">

    <?php if (! empty($error)): ?>
        <div class="mcc-alert mcc-alert-danger" role="alert"><?= esc($error) ?></div>
    <?php endif; ?>

    <section class="mcc-card mcc-scheduling-card mcc-session-filters-card" id="report-filters" hidden>
        <div class="mcc-card-heading">
            <div>
                <span class="mcc-eyebrow">Search and filter</span>
                <h2>Find <?= esc(strtolower((string) ($result['title'] ?? 'report'))) ?> records</h2>
            </div>
        </div>
        <form method="get" class="row mcc-scheduling-filter-form">
            <div class="col-12 col-md-3 mb-2 mcc-form-field">
                <label for="report-from">From</label>
                <input class="form-control" id="report-from" name="from" type="text" inputmode="numeric" autocomplete="off" data-mcc-date-picker data-mcc-date-picker-no-toggle data-mcc-date-format="d-m-Y" data-mcc-date-submit-format="Y-m-d" value="<?= esc(mcc_user_date_input($filters['from'] ?? null)) ?>">
            </div>
            <div class="col-12 col-md-3 mb-2 mcc-form-field">
                <label for="report-to">To</label>
                <input class="form-control" id="report-to" name="to" type="text" inputmode="numeric" autocomplete="off" data-mcc-date-picker data-mcc-date-picker-no-toggle data-mcc-date-format="d-m-Y" data-mcc-date-submit-format="Y-m-d" value="<?= esc(mcc_user_date_input($filters['to'] ?? null)) ?>">
            </div>
            <div class="col-12 col-md-3 mb-2 mcc-form-field">
                <label for="report-branch">Branch</label>
                <select class="form-control" id="report-branch" name="branch_id">
                    <option value="">All authorized branches</option>
                    <?php foreach ((array) ($branches ?? []) as $branch): ?>
                        <option value="<?= esc((string) $branch['id']) ?>" <?= (string) ($filters['branch_id'] ?? '') === (string) $branch['id'] ? 'selected' : '' ?>>
                            <?= esc((string) $branch['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-3 mb-2 mcc-form-field">
                <label for="report-status">Status</label>
                <select class="form-control" id="report-status" name="status">
                    <option value="">All statuses</option>
                    <?php foreach ($statusOptions as $status): ?>
                        <option value="<?= esc($status) ?>" <?= (string) ($filters['status'] ?? '') === $status ? 'selected' : '' ?>><?= esc(ucwords(str_replace('_', ' ', $status))) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mcc-form-actions col-12 mt-2">
                <button class="mcc-button mcc-button-primary" type="submit">Apply filters</button>
                <a class="mcc-button mcc-button-secondary" href="<?= site_url('reports/' . rawurlencode($reportType)) ?>">Clear</a>
            </div>
        </form>
    </section>

    <section class="mcc-card mcc-scheduling-card mcc-session-results-card mcc-report-results-card" aria-labelledby="report-results-heading">
        <div class="mcc-session-summary">
            <div>
                <span class="mcc-eyebrow">Report center</span>
                <h2 id="report-results-heading"><?= esc($result['title'] ?? 'Report') ?></h2>
            </div>
            <div class="mcc-session-summary-meta">
                <span><strong><?= count($rows) ?></strong> record<?= count($rows) === 1 ? '' : 's' ?></span>
                <span class="mcc-muted">Authorized scope</span>
            </div>
        </div>
        <?php if ($rows === []): ?>
            <div class="mcc-empty-state" role="status">
                <strong>No records found.</strong>
                <span>Try a different date, branch, or status filter.</span>
            </div>
        <?php else: ?>
            <div class="mcc-table-wrap mcc-session-table-wrap">
                <table class="mcc-table mcc-session-list-table mcc-report-table">
                    <thead>
                        <tr>
                            <?php foreach (array_keys($reportColumns($rows[0], $reportType)) as $label): ?>
                                <th scope="col"><?= esc($label) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row): ?>
                            <?php $columns = $reportColumns($row, $reportType); ?>
                            <tr>
                                <?php foreach ($columns as $label => $html): ?>
                                    <td data-label="<?= esc($label) ?>"><span class="mcc-report-cell-stack"><?= $html ?></span></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>

<?= view('staff_footer') ?>
