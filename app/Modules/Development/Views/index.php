<?php

$result = is_array($result ?? null) ? $result : [];
$filters = is_array($result['filters'] ?? null) ? $result['filters'] : [];
$plans = is_array($result['plans'] ?? null) ? $result['plans'] : [];
$branches = is_array($result['branches'] ?? null) ? $result['branches'] : [];
$statusLabels = [
    'active' => 'Active',
    'completed' => 'Completed',
    'cancelled' => 'Cancelled',
];
$queryFilters = array_filter(
    $filters,
    static fn (mixed $value): bool => $value !== null && $value !== '' && $value !== 1,
);
$hasDevelopmentFilters = ($filters['q'] ?? '') !== ''
    || ($filters['status'] ?? '') !== ''
    || ($filters['branch_id'] ?? '') !== '';
$pageUrl = static function (int $page) use ($queryFilters): string {
    $query = http_build_query(array_merge($queryFilters, ['page' => $page]));

    return site_url('development') . ($query === '' ? '' : '?' . $query);
};
$pageActions = '<div class="mcc-page-actions">
    <button class="mcc-button mcc-button-secondary" type="button"
        data-filter-toggle data-show-label="Show filters" data-hide-label="Hide filters"
        aria-controls="development-filters" aria-expanded="' . ($hasDevelopmentFilters ? 'true' : 'false') . '">
        <i class="fa-solid fa-filter" aria-hidden="true"></i>
        <span class="mcc-button-label">' . ($hasDevelopmentFilters ? 'Hide filters' : 'Show filters') . '</span>
    </button>
    <a class="mcc-button mcc-button-primary" href="' . site_url('development/new') . '">Create plan</a>
</div>';
?>
<?= view('staff_header', ['title' => $title, 'pageActions' => $pageActions]) ?>
<main class="container py-4 mcc-development-page">
    <section id="development-filters" class="mcc-card mcc-development-filter-card" aria-labelledby="development-filters-heading" <?= $hasDevelopmentFilters ? '' : 'hidden' ?>>
        <div class="mcc-card-heading">
            <div>
                <span class="mcc-eyebrow">Search and filter</span>
                <h2 id="development-filters-heading">Find development plans</h2>
            </div>
        </div>

        <form method="get" action="<?= site_url('development') ?>" class="mcc-development-filter-form" data-development-submit-form>
            <div class="mcc-form-field">
                <label for="development-search">Player or plan</label>
                <input class="form-control" id="development-search" name="q" type="search" maxlength="100" value="<?= esc((string) ($filters['q'] ?? '')) ?>">
            </div>
            <div class="mcc-form-field">
                <label for="development-status">Status</label>
                <select class="form-control" id="development-status" name="status">
                    <option value="">All statuses</option>
                    <?php foreach ($statusLabels as $value => $label): ?>
                        <option value="<?= esc($value) ?>" <?= ($filters['status'] ?? '') === $value ? 'selected' : '' ?>>
                            <?= esc($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if (count($branches) > 1): ?>
                <div class="mcc-form-field">
                    <label for="development-branch-filter">Authorized branch</label>
                    <select class="form-control" id="development-branch-filter" name="branch_id">
                        <option value="">All authorized branches</option>
                        <?php foreach ($branches as $branch): ?>
                            <option value="<?= (int) $branch['id'] ?>" <?= (string) ($filters['branch_id'] ?? '') === (string) $branch['id'] ? 'selected' : '' ?>>
                                <?= esc($branch['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            <div class="mcc-form-actions mcc-development-actions">
                <button class="mcc-button mcc-button-primary" type="submit">Apply filters</button>
                <a class="mcc-button mcc-button-secondary" href="<?= site_url('development') ?>">Clear filters</a>
            </div>
        </form>
    </section>

    <section class="mcc-card mcc-development-results-card" aria-labelledby="development-list-heading">
        <div class="mcc-card-heading">
            <div>
                <span class="mcc-eyebrow">Authorized records</span>
                <h2 id="development-list-heading">Plans</h2>
            </div>
            <span class="mcc-muted"><?= (int) ($result['total'] ?? 0) ?> result<?= (int) ($result['total'] ?? 0) === 1 ? '' : 's' ?></span>
        </div>

        <div class="mcc-table-wrap">
            <table class="mcc-table development-table">
                <caption class="visually-hidden">Authorized player development plans</caption>
                <thead>
                    <tr>
                        <th scope="col">Player / assignment</th>
                        <th scope="col">Plan</th>
                        <th scope="col">Status</th>
                        <th scope="col">Target</th>
                        <th scope="col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($plans as $plan): ?>
                        <?php $status = (string) ($plan['status'] ?? ''); ?>
                        <tr>
                            <th scope="row" data-label="Player / assignment">
                                <strong><?= esc($plan['full_name'] ?? 'Not available') ?></strong>
                                <span class="mcc-development-assignment-meta">
                                    <span class="mcc-development-assignment-meta-badge"><b>Branch:</b><em><?= esc($plan['branch_name'] ?? 'Not available') ?></em></span>
                                    <span class="mcc-development-assignment-meta-badge"><b>Coach:</b><em><?= esc($plan['coach_name'] ?? 'Not available') ?></em></span>
                                </span>
                            </th>
                            <td data-label="Plan"><?= esc($plan['title'] ?? '') ?></td>
                            <td data-label="Status">
                                <span class="mcc-badge mcc-badge-<?= $status === 'active' ? 'success' : ($status === 'cancelled' ? 'danger' : 'neutral') ?>">
                                    <?= esc($statusLabels[$status] ?? $status) ?>
                                </span>
                            </td>
                            <td data-label="Target"><?= esc(mcc_user_date($plan['target_date'] ?? null, 'Not set')) ?></td>
                            <td data-label="Actions">
                                <a class="mcc-button mcc-button-secondary" href="<?= site_url('development/' . (int) $plan['id']) ?>">Open</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($plans === []): ?>
                        <tr>
                            <td colspan="5" class="mcc-empty-state">
                                <i class="fa-solid fa-person-running mcc-empty-icon" aria-hidden="true"></i>
                                <h2>No development plans found</h2>
                                <p>There are no plans matching the authorized branches and selected filters.</p>
                                <a href="<?= site_url('development') ?>">Clear filters</a>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ((int) ($result['pages'] ?? 1) > 1): ?>
            <nav class="mcc-pagination" aria-label="Development plan pages">
                <span>Page <?= (int) ($result['page'] ?? 1) ?> of <?= (int) ($result['pages'] ?? 1) ?></span>
                <div>
                    <?php if ((int) ($result['page'] ?? 1) > 1): ?>
                        <a class="mcc-button mcc-button-secondary" href="<?= esc($pageUrl((int) $result['page'] - 1)) ?>">Previous</a>
                    <?php endif; ?>
                    <?php for ($targetPage = 1; $targetPage <= (int) ($result['pages'] ?? 1); $targetPage++): ?>
                        <a
                            class="mcc-button mcc-button-secondary"
                            href="<?= esc($pageUrl($targetPage)) ?>"
                            <?= $targetPage === (int) ($result['page'] ?? 1) ? 'aria-current="page"' : '' ?>
                        >
                            <?= $targetPage ?>
                        </a>
                    <?php endfor; ?>
                    <?php if ((int) ($result['page'] ?? 1) < (int) ($result['pages'] ?? 1)): ?>
                        <a class="mcc-button mcc-button-secondary" href="<?= esc($pageUrl((int) $result['page'] + 1)) ?>">Next</a>
                    <?php endif; ?>
                </div>
            </nav>
        <?php endif; ?>
    </section>
</main>
<?= view('staff_footer') ?>
