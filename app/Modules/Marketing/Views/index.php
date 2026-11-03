<?php
$columnLabel = static function (string $column): string {
    return ucwords(str_replace('_', ' ', $column));
};
$hasMarketingFilters = ($result['filters']['branch_id'] ?? '') !== ''
    || ($result['filters']['date_range'] ?? 'all_time') !== 'all_time'
    || ($result['filters']['from'] ?? '') !== ''
    || ($result['filters']['to'] ?? '') !== ''
    || ($result['filters']['source'] ?? '') !== ''
    || ($result['filters']['lead_status'] ?? '') !== ''
    || ($result['filters']['admission_status'] ?? '') !== ''
    || ($result['filters']['q'] ?? '') !== '';
$pageActions = '<div class="mcc-page-actions">
    <button class="mcc-button mcc-button-secondary" type="button"
        data-filter-toggle data-show-label="Show filters" data-hide-label="Hide filters"
        aria-controls="marketing-filters" aria-expanded="' . ($hasMarketingFilters ? 'true' : 'false') . '">
        <i class="fa-solid fa-filter" aria-hidden="true"></i>
        <span class="mcc-button-label">' . ($hasMarketingFilters ? 'Hide filters' : 'Show filters') . '</span>
    </button>
</div>';
?>
<?= view('staff_header', ['title' => $title, 'pageActions' => $pageActions]) ?>

<div class="mcc-marketing-dashboard">
    <section class="mcc-card mcc-marketing-filters" id="marketing-filters" aria-labelledby="marketing-filters-heading" <?= $hasMarketingFilters ? '' : 'hidden' ?>>
        <div class="mcc-card-heading">
            <div>
                <span class="mcc-eyebrow">Marketing filters</span>
                <h2 id="marketing-filters-heading">Filter performance</h2>
            </div>
        </div>
        <form method="get" class="row mcc-marketing-filter-form">
            <div class="col-12 col-md-4 mb-2">
                <label for="marketing-branch">Branch</label>
                <select class="form-control" id="marketing-branch" name="branch_id">
                    <option value="">All authorized branches</option>
                    <?php foreach ($result['branches'] as $branch): ?>
                        <option
                            value="<?= esc((string) $branch['id']) ?>"
                            <?= $result['filters']['branch_id'] === (string) $branch['id'] ? 'selected' : '' ?>
                        >
                            <?= esc($branch['name'] . (! empty($branch['code']) ? ' (' . $branch['code'] . ')' : '')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-4 mb-2">
                <label for="marketing-date-range">Date range</label>
                <select class="form-control" id="marketing-date-range" name="date_range">
                    <?php foreach ([
                        'all_time' => 'All Time',
                        'today' => 'Today',
                        'this_week' => 'This Week',
                        'this_month' => 'This Month',
                        'last_month' => 'Last Month',
                        'custom' => 'Custom Range',
                    ] as $value => $label): ?>
                        <option value="<?= esc($value) ?>" <?= $result['filters']['date_range'] === $value ? 'selected' : '' ?>>
                            <?= esc($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-4 mb-2 mcc-marketing-custom-range" id="marketing-custom-range" <?= $result['filters']['date_range'] === 'custom' ? '' : 'hidden' ?>>
                <div class="row">
                    <div class="col-12 col-sm-6 mb-2 mb-sm-0">
                        <label for="marketing-from">Start date</label>
                        <input
                            class="form-control"
                            id="marketing-from"
                            type="text"
                            inputmode="numeric"
                            autocomplete="off"
                            data-mcc-date-picker
                            data-mcc-date-picker-no-toggle
                        data-mcc-date-format="d-m-Y"
                        data-mcc-date-submit-format="Y-m-d"
                            name="from"
                            value="<?= esc($result['filters']['from']) ?>"
                        >
                    </div>
                    <div class="col-12 col-sm-6">
                        <label for="marketing-to">End date</label>
                        <input
                            class="form-control"
                            id="marketing-to"
                            type="text"
                            inputmode="numeric"
                            autocomplete="off"
                            data-mcc-date-picker
                            data-mcc-date-picker-no-toggle
                        data-mcc-date-format="d-m-Y"
                        data-mcc-date-submit-format="Y-m-d"
                            name="to"
                            value="<?= esc($result['filters']['to']) ?>"
                        >
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-4 mb-2">
                <label for="marketing-source">Lead source</label>
                <select class="form-control" id="marketing-source" name="source">
                    <option value="">All lead sources</option>
                    <?php foreach ($result['filter_options']['sources'] as $source): ?>
                        <option value="<?= esc($source) ?>" <?= $result['filters']['source'] === $source ? 'selected' : '' ?>>
                            <?= esc($source) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-4 mb-2">
                <label for="marketing-lead-status">Lead status</label>
                <select class="form-control" id="marketing-lead-status" name="lead_status">
                    <option value="">All lead statuses</option>
                    <?php foreach ($result['filter_options']['lead_statuses'] as $status): ?>
                        <option value="<?= esc($status) ?>" <?= $result['filters']['lead_status'] === $status ? 'selected' : '' ?>>
                            <?= esc($status) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-4 mb-2">
                <label for="marketing-admission-status">Admission status</label>
                <select class="form-control" id="marketing-admission-status" name="admission_status">
                    <option value="">All admission statuses</option>
                    <?php foreach ($result['filter_options']['admission_statuses'] as $status): ?>
                        <option value="<?= esc($status) ?>" <?= $result['filters']['admission_status'] === $status ? 'selected' : '' ?>>
                            <?= esc($status) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-8 mb-2">
                <label for="marketing-search">Search</label>
                <input
                    class="form-control"
                    id="marketing-search"
                    name="q"
                    value="<?= esc($result['filters']['q']) ?>"
                >
            </div>
            <div class="col-12 mcc-form-actions">
                <button class="mcc-button mcc-button-primary" type="submit">
                    <i class="fa-solid fa-filter" aria-hidden="true"></i>
                    <span>Apply filters</span>
                </button>
                <a class="mcc-button mcc-button-secondary" href="<?= site_url('marketing') ?>">
                    <i class="fa-solid fa-rotate-left" aria-hidden="true"></i>
                    <span>Clear filters</span>
                </a>
            </div>
        </form>
    </section>

    <section class="mcc-card mcc-marketing-section" aria-labelledby="marketing-totals-heading">
        <div class="mcc-card-heading">
            <div>
                <span class="mcc-eyebrow">Operational summary</span>
                <h2 id="marketing-totals-heading">Operational totals</h2>
            </div>
        </div>
        <div class="metric-grid mcc-marketing-kpi-grid">
            <div class="metric-card">
                <span class="mcc-eyebrow">Leads</span>
                <strong><?= esc((string) $result['kpis']['leads']) ?></strong>
            </div>
            <div class="metric-card">
                <span class="mcc-eyebrow">Admissions</span>
                <strong><?= esc((string) $result['kpis']['admissions']) ?></strong>
            </div>
            <div class="metric-card">
                <span class="mcc-eyebrow">Trials</span>
                <strong><?= esc((string) $result['kpis']['trials']) ?></strong>
            </div>
            <div class="metric-card">
                <span class="mcc-eyebrow">Converted admissions</span>
                <strong><?= esc((string) $result['kpis']['converted_admissions']) ?></strong>
            </div>
        </div>
    </section>

    <section class="mcc-card mcc-marketing-section" aria-labelledby="marketing-branch-heading">
        <div class="mcc-card-heading">
            <div>
                <span class="mcc-eyebrow">Branch scope</span>
                <h2 id="marketing-branch-heading">Branch comparison</h2>
            </div>
        </div>
        <?php if ($result['branch_comparison'] === []): ?>
            <p class="mcc-empty-state">No authorized branch data is available.</p>
        <?php else: ?>
            <div class="mcc-table-wrap">
                <table class="mcc-table mcc-marketing-table">
                    <caption class="visually-hidden">Marketing branch comparison</caption>
                    <thead>
                        <tr>
                            <?php foreach (array_keys($result['branch_comparison'][0]) as $column): ?>
                                <?php if ($column === 'branch_id') { continue; } ?>
                                <th scope="col"><?= esc($columnLabel($column)) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($result['branch_comparison'] as $row): ?>
                            <tr>
                                <?php foreach ($row as $column => $value): ?>
                                    <?php if ($column === 'branch_id') { continue; } ?>
                                    <td><?= esc((string) $value) ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <?php foreach ([
        'lead_source_performance' => 'Lead source performance',
        'lead_status_summary' => 'Lead lifecycle/status summary',
        'admission_funnel' => 'Admission status counts',
        'trial_summary' => 'Trial status summary',
        'monthly_trends' => 'Created-month trends',
    ] as $key => $heading): ?>
        <section class="mcc-card mcc-marketing-section" aria-labelledby="marketing-<?= esc($key) ?>-heading">
            <div class="mcc-card-heading">
                <div>
                    <span class="mcc-eyebrow">Marketing analysis</span>
                    <h2 id="marketing-<?= esc($key) ?>-heading"><?= esc($heading) ?></h2>
                </div>
            </div>
            <?php if ($result[$key] === []): ?>
                <p class="mcc-empty-state">No persisted data matches the selected filters.</p>
            <?php else: ?>
                <div class="mcc-table-wrap">
                    <table class="mcc-table mcc-marketing-table">
                        <caption class="visually-hidden"><?= esc($heading) ?></caption>
                        <thead>
                            <tr>
                                <?php foreach (array_keys($result[$key][0]) as $column): ?>
                                    <th scope="col"><?= esc($columnLabel($column)) ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($result[$key] as $row): ?>
                                <tr>
                                    <?php foreach ($row as $value): ?>
                                        <td><?= esc((string) $value) ?></td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const dateRange = document.getElementById('marketing-date-range');
        const customRange = document.getElementById('marketing-custom-range');

        if (! dateRange || ! customRange) {
            return;
        }

        const syncCustomRange = function () {
            customRange.hidden = dateRange.value !== 'custom';
        };

        dateRange.addEventListener('change', syncCustomRange);
        syncCustomRange();
    });
</script>

<?= view('staff_footer') ?>
