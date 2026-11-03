<?= view('staff_header', ['title' => $title]) ?>

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
$dateFields = ['created_at', 'recorded_at', 'starts_at', 'ends_at'];
$statusClasses = [
    'active' => 'mcc-status-approved',
    'approved' => 'mcc-status-approved',
    'completed' => 'mcc-status-completed',
    'converted' => 'mcc-status-converted',
    'scheduled' => 'mcc-status-in_review',
    'pending' => 'mcc-status-documents_pending',
    'on_hold' => 'mcc-status-documents_pending',
    'cancelled' => 'mcc-status-rejected',
    'rejected' => 'mcc-status-rejected',
    'failed' => 'mcc-status-rejected',
];
foreach ($rows as $row) {
    if (isset($row['status']) && trim((string) $row['status']) !== '') {
        $statusOptions[(string) $row['status']] = (string) $row['status'];
    }
}
ksort($statusOptions);
?>

<main class="mcc-page-container mcc-report-page" id="main-content">
    <div class="mcc-breadcrumbs">
        <a href="<?= site_url('/') ?>">Dashboard</a>
        <span aria-hidden="true">/</span>
        <a href="<?= site_url('reports') ?>">Reports</a>
        <span aria-hidden="true">/</span>
        <span><?= esc($result['title'] ?? 'Report') ?></span>
    </div>

    <div class="mcc-page-heading">
        <div>
            <span class="mcc-eyebrow">Report center</span>
            <h1><?= esc($result['title'] ?? 'Report') ?></h1>
            <p class="mcc-muted"><?= count($rows) ?> record<?= count($rows) === 1 ? '' : 's' ?> in the authorized scope.</p>
        </div>
        <div class="mcc-page-actions">
            <button class="mcc-button mcc-button-secondary" type="button" data-filter-toggle aria-controls="report-filters" aria-expanded="false">
                <i class="fa-solid fa-filter" aria-hidden="true"></i>
                <span data-show-label="Show filters" data-hide-label="Hide filters">Show filters</span>
            </button>
            <a class="mcc-button mcc-button-primary" href="<?= esc($csvUrl) ?>">
                <i class="fa-solid fa-file-csv" aria-hidden="true"></i>
                Export CSV
            </a>
        </div>
    </div>

    <?php if (! empty($error)): ?>
        <div class="mcc-alert mcc-alert-danger" role="alert"><?= esc($error) ?></div>
    <?php endif; ?>

    <section class="mcc-card mcc-report-filter-card" id="report-filters" hidden>
        <form method="get" class="mcc-filter-grid">
            <div class="mcc-form-field">
                <label for="report-from">From</label>
                <input class="form-control" id="report-from" name="from" type="text" inputmode="numeric" autocomplete="off" data-mcc-date-picker data-mcc-date-picker-no-toggle data-mcc-date-format="d-m-Y" data-mcc-date-submit-format="Y-m-d" value="<?= esc(mcc_user_date_input($filters['from'] ?? null)) ?>">
            </div>
            <div class="mcc-form-field">
                <label for="report-to">To</label>
                <input class="form-control" id="report-to" name="to" type="text" inputmode="numeric" autocomplete="off" data-mcc-date-picker data-mcc-date-picker-no-toggle data-mcc-date-format="d-m-Y" data-mcc-date-submit-format="Y-m-d" value="<?= esc(mcc_user_date_input($filters['to'] ?? null)) ?>">
            </div>
            <div class="mcc-form-field">
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
            <div class="mcc-form-field">
                <label for="report-status">Status</label>
                <select class="form-control" id="report-status" name="status">
                    <option value="">All statuses</option>
                    <?php foreach ($statusOptions as $status): ?>
                        <option value="<?= esc($status) ?>" <?= (string) ($filters['status'] ?? '') === $status ? 'selected' : '' ?>><?= esc(ucwords(str_replace('_', ' ', $status))) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mcc-form-actions">
                <button class="mcc-button mcc-button-primary" type="submit">Apply filters</button>
                <a class="mcc-button mcc-button-secondary" href="<?= site_url('reports/' . rawurlencode($reportType)) ?>">Clear</a>
            </div>
        </form>
    </section>

    <section class="mcc-card mcc-report-results-card">
        <?php if ($rows === []): ?>
            <div class="mcc-empty-state" role="status">
                <strong>No records found.</strong>
                <span>Try a different date, branch, or status filter.</span>
            </div>
        <?php else: ?>
            <div class="mcc-table-wrap">
                <table class="mcc-table mcc-report-table">
                    <thead>
                        <tr>
                            <?php foreach (array_keys($rows[0]) as $key): ?>
                                <th><?= esc(ucwords(str_replace('_', ' ', $key))) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <?php foreach ($row as $key => $value): ?>
                                    <td>
                                        <?php if ($key === 'status'): ?>
                                            <?php
                                            $statusValue = strtolower(trim((string) $value));
                                            $statusClass = $statusClasses[$statusValue] ?? 'mcc-status-intake';
                                            ?>
                                            <span class="mcc-status-badge <?= esc($statusClass) ?>"><?= esc(ucwords(str_replace('_', ' ', (string) $value))) ?></span>
                                        <?php else: ?>
                                            <?php if (in_array($key, $dateFields, true)): ?>
                                                <?= esc(mcc_user_datetime($value !== null ? (string) $value : null, 'Not available')) ?>
                                            <?php else: ?>
                                                <?= esc((string) ($value ?? '—')) ?>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</main>

<?= view('staff_footer') ?>
