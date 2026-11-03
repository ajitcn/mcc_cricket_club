<?php
$filters = (array) ($result['filters'] ?? []);
$options = (array) ($result['options'] ?? []);
$query = http_build_query(array_filter(
    $filters,
    static fn ($value): bool => $value !== null && $value !== ''
));
$csvUrl = site_url('achievements/reports/csv') . ($query === '' ? '' : '?' . $query);
$hasReportFilters = trim((string) ($filters['branch_id'] ?? '')) !== ''
    || trim((string) ($filters['player_id'] ?? '')) !== ''
    || trim((string) ($filters['coach_id'] ?? '')) !== ''
    || trim((string) ($filters['category'] ?? '')) !== ''
    || trim((string) ($filters['from'] ?? '')) !== ''
    || trim((string) ($filters['to'] ?? '')) !== '';
$pageActions = '<div class="mcc-page-actions">'
    . '<button class="mcc-button mcc-button-secondary" type="button" data-filter-toggle '
    . 'data-show-label="Show filters" data-hide-label="Hide filters" '
    . 'aria-controls="achievement-report-filters" aria-expanded="' . ($hasReportFilters ? 'true' : 'false') . '">'
    . '<i class="fa-solid fa-filter" aria-hidden="true"></i>'
    . '<span class="mcc-button-label">' . ($hasReportFilters ? 'Hide filters' : 'Show filters') . '</span>'
    . '</button>'
    . '<a class="mcc-button mcc-button-secondary" href="' . esc($csvUrl) . '">'
    . '<i class="fa-solid fa-download" aria-hidden="true"></i>'
    . '<span class="mcc-button-label">Export CSV</span>'
    . '</a></div>';
?>
<?= view('staff_header', ['title' => $title, 'pageActions' => $pageActions]) ?>
<main class="container py-4 mcc-achievements-page">
    <section id="achievement-report-filters" class="mcc-card" aria-labelledby="achievement-report-filters-heading" <?= $hasReportFilters ? '' : 'hidden' ?>>
        <div class="mcc-card-heading">
            <div>
                <span class="mcc-eyebrow">Report scope</span>
                <h2 id="achievement-report-filters-heading">Filters</h2>
            </div>
        </div>
        <form method="get" action="<?= site_url('achievements/reports') ?>" class="mcc-form-grid">
            <div class="mcc-form-field">
                <label for="achievement-report-branch">Branch</label>
                <select class="form-control" id="achievement-report-branch" name="branch_id">
                    <option value="">All authorized branches</option>
                    <?php foreach (($options['branches'] ?? []) as $branch): ?>
                        <option value="<?= (int) $branch['id'] ?>" <?= (string) ($filters['branch_id'] ?? '') === (string) $branch['id'] ? 'selected' : '' ?>>
                            <?= esc((string) $branch['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mcc-form-field">
                <label for="achievement-report-player">Player</label>
                <select class="form-control" id="achievement-report-player" name="player_id">
                    <option value="">All Players</option>
                    <?php foreach (($options['players'] ?? []) as $player): ?>
                        <option value="<?= (int) $player['id'] ?>" <?= (string) ($filters['player_id'] ?? '') === (string) $player['id'] ? 'selected' : '' ?>>
                            <?= esc((string) $player['full_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mcc-form-field">
                <label for="achievement-report-coach">Coach</label>
                <select class="form-control" id="achievement-report-coach" name="coach_id">
                    <option value="">All Coaches</option>
                    <?php foreach (($options['coaches'] ?? []) as $coach): ?>
                        <option value="<?= (int) $coach['id'] ?>" <?= (string) ($filters['coach_id'] ?? '') === (string) $coach['id'] ? 'selected' : '' ?>>
                            <?= esc((string) $coach['display_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mcc-form-field">
                <label for="achievement-report-category">Category</label>
                <select class="form-control" id="achievement-report-category" name="category">
                    <option value="">All categories</option>
                    <?php foreach (($options['categories'] ?? []) as $category): ?>
                        <option value="<?= esc((string) $category['category']) ?>" <?= (string) ($filters['category'] ?? '') === (string) $category['category'] ? 'selected' : '' ?>>
                            <?= esc((string) $category['category']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mcc-form-field">
                <label for="achievement-report-from">From</label>
                <input class="form-control" id="achievement-report-from" name="from" type="text" inputmode="numeric" autocomplete="off" data-mcc-date-picker data-mcc-date-format="d-m-Y" data-mcc-date-submit-format="Y-m-d" value="<?= esc(mcc_user_date_input($filters['from'] ?? null)) ?>">
            </div>
            <div class="mcc-form-field">
                <label for="achievement-report-to">To</label>
                <input class="form-control" id="achievement-report-to" name="to" type="text" inputmode="numeric" autocomplete="off" data-mcc-date-picker data-mcc-date-format="d-m-Y" data-mcc-date-submit-format="Y-m-d" value="<?= esc(mcc_user_date_input($filters['to'] ?? null)) ?>">
            </div>
            <div class="mcc-form-actions mcc-form-field-wide">
                <button class="mcc-button mcc-button-primary" type="submit">Apply filters</button>
                <a class="mcc-button mcc-button-secondary" href="<?= site_url('achievements/reports') ?>">Clear</a>
            </div>
        </form>
    </section>

    <section class="mcc-card" aria-labelledby="achievement-report-results-heading">
        <div class="mcc-card-heading">
            <div>
                <span class="mcc-eyebrow">Read-only results</span>
                <h2 id="achievement-report-results-heading">Published awards</h2>
            </div>
        </div>
        <?php if (($result['rows'] ?? []) === []): ?>
            <div class="mcc-empty-state" role="status">
                <strong>No published Achievement awards found.</strong>
                <span>Adjust the filters or wait until a published award is available.</span>
            </div>
        <?php else: ?>
            <div class="mcc-table-wrap">
                <table class="mcc-table">
                    <caption class="sr-only">Published Achievement awards</caption>
                    <thead>
                        <tr>
                            <th scope="col">Achievement</th>
                            <th scope="col">Category</th>
                            <th scope="col">Recipient</th>
                            <th scope="col">Branch</th>
                            <th scope="col">Published date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($result['rows'] as $row): ?>
                            <tr>
                                <th scope="row" data-label="Achievement"><?= esc((string) $row['achievement']) ?></th>
                                <td data-label="Category"><?= esc((string) $row['category']) ?></td>
                                <td data-label="Recipient"><?= esc((string) $row['recipient_name']) ?></td>
                                <td data-label="Branch"><?= esc((string) $row['branch_name']) ?></td>
                                <td data-label="Published date"><?= esc((string) $row['published_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</main>
<?= view('staff_footer') ?>
