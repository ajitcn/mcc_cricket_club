<?php

$result = is_array($result ?? null) ? $result : [];
$filters = is_array($result['filters'] ?? null) ? $result['filters'] : [];
$records = is_array($result['records'] ?? null) ? $result['records'] : [];
$hasFilters = trim((string) ($filters['q'] ?? '')) !== ''
    || trim((string) ($filters['record_type'] ?? '')) !== ''
    || trim((string) ($filters['from'] ?? '')) !== ''
    || trim((string) ($filters['to'] ?? '')) !== '';
$pageActions = '<div class="mcc-page-actions">'
    . '<button class="mcc-button mcc-button-secondary" type="button" data-filter-toggle '
    . 'data-show-label="Show Filters" data-hide-label="Hide Filters" '
    . 'aria-controls="performance-filters" aria-expanded="' . ($hasFilters ? 'true' : 'false') . '">'
    . '<i class="fa-solid fa-filter" aria-hidden="true"></i>'
    . '<span class="mcc-button-label">' . ($hasFilters ? 'Hide Filters' : 'Show Filters') . '</span>'
    . '</button>'
    . '<a class="mcc-button mcc-button-primary" href="' . site_url('performance/new') . '">'
    . '<i class="fa-solid fa-plus" aria-hidden="true"></i>'
    . '<span class="mcc-button-label">Record performance</span>'
    . '</a></div>';
$typeLabels = [
    'batting' => ['label' => 'Batting', 'class' => 'info'],
    'bowling' => ['label' => 'Bowling', 'class' => 'success'],
    'fielding' => ['label' => 'Fielding', 'class' => 'warning'],
];
?>
<?= view('staff_header', [
    'title' => $title,
    'nav' => $nav ?? null,
    'message' => $message ?? null,
    'error' => $error ?? null,
    'pageActions' => $pageActions,
]) ?>
<main class="container py-4 mcc-performance-page">
    <section id="performance-filters" class="mcc-card mcc-performance-filters" aria-labelledby="performance-filters-heading" <?= $hasFilters ? '' : 'hidden' ?>>
        <div class="mcc-card-heading">
            <div>
                <span class="mcc-eyebrow">Search and filter</span>
                <h2 id="performance-filters-heading">Find performance records</h2>
            </div>
        </div>
        <form method="get" action="<?= esc(site_url('performance')) ?>" class="mcc-form-grid" aria-label="Filter performance records">
            <div class="mcc-form-field">
                <label for="performance-filter-player">Player</label>
                <input class="form-control" id="performance-filter-player" name="q" type="search" value="<?= esc((string) ($filters['q'] ?? '')) ?>" placeholder="Search player">
            </div>
            <div class="mcc-form-field">
                <label for="performance-filter-type">Type</label>
                <select class="form-control" id="performance-filter-type" name="record_type">
                    <option value="">All types</option>
                    <?php foreach ($typeLabels as $value => $type): ?>
                        <option value="<?= esc($value) ?>" <?= (string) ($filters['record_type'] ?? '') === $value ? 'selected' : '' ?>><?= esc($type['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mcc-form-field">
                <label for="performance-filter-from">From</label>
                <input class="form-control" id="performance-filter-from" name="from" type="text" inputmode="numeric" autocomplete="off" data-mcc-date-picker data-mcc-date-format="d-m-Y" data-mcc-date-submit-format="Y-m-d" value="<?= esc(mcc_user_date_input($filters['from'] ?? null)) ?>">
            </div>
            <div class="mcc-form-field">
                <label for="performance-filter-to">To</label>
                <input class="form-control" id="performance-filter-to" name="to" type="text" inputmode="numeric" autocomplete="off" data-mcc-date-picker data-mcc-date-format="d-m-Y" data-mcc-date-submit-format="Y-m-d" value="<?= esc(mcc_user_date_input($filters['to'] ?? null)) ?>">
            </div>
            <div class="mcc-form-actions mcc-form-field-wide">
                <button class="mcc-button mcc-button-primary" type="submit">Apply filters</button>
                <a class="mcc-button mcc-button-secondary" href="<?= site_url('performance') ?>">Clear</a>
            </div>
        </form>
    </section>

    <section class="mcc-card" aria-labelledby="performance-results-heading">
        <div class="mcc-card-heading">
            <div>
                <span class="mcc-eyebrow">Performance records</span>
                <h2 id="performance-results-heading">Player performance</h2>
            </div>
            <span class="mcc-muted" aria-label="Performance record count"><?= count($records) ?> record<?= count($records) === 1 ? '' : 's' ?></span>
        </div>
        <?php if ($records === []): ?>
            <div class="mcc-empty-state" role="status">
                <i class="fa-solid fa-chart-line" aria-hidden="true"></i>
                <strong>No performance records found</strong>
                <span>Adjust the filters or record a performance entry.</span>
            </div>
        <?php else: ?>
            <div class="mcc-table-wrap">
                <table class="mcc-table">
                    <caption class="sr-only">Player performance records</caption>
                    <thead>
                        <tr>
                            <th scope="col">Player</th>
                            <th scope="col">Recorded</th>
                            <th scope="col">Type</th>
                            <th scope="col">Runs</th>
                            <th scope="col">Wickets</th>
                            <th scope="col" class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($records as $record): ?>
                            <?php $type = $typeLabels[(string) ($record['record_type'] ?? '')] ?? ['label' => ucfirst((string) ($record['record_type'] ?? 'Other')), 'class' => 'muted']; ?>
                            <tr>
                                <th scope="row" data-label="Player"><?= esc((string) ($record['player_name'] ?? 'Not available')) ?></th>
                                <td data-label="Recorded"><span class="mcc-muted mcc-date-muted"><?= esc(mcc_user_date($record['recorded_on'] ?? null)) ?></span></td>
                                <td data-label="Type"><span class="mcc-badge mcc-badge-<?= esc($type['class']) ?>"><?= esc($type['label']) ?></span></td>
                                <td data-label="Runs"><?= esc((string) ($record['runs'] ?? '—')) ?></td>
                                <td data-label="Wickets"><?= esc((string) ($record['wickets'] ?? '—')) ?></td>
                                <td data-label="Actions" class="text-end">
                                    <a class="mcc-button mcc-button-secondary mcc-button-small" href="<?= site_url('performance/' . (int) ($record['id'] ?? 0)) ?>">
                                        <i class="fa-solid fa-eye" aria-hidden="true"></i>
                                        View
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</main>
<?= view('staff_footer') ?>
