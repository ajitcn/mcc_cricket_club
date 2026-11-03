<?php
$result = is_array($result ?? null) ? $result : [
    'grounds' => [],
    'total' => 0,
    'page' => 1,
    'pages' => 1,
    'filters' => [],
];
$filters = is_array($result['filters'] ?? null) ? $result['filters'] : [];
$queryFilters = array_filter(
    $filters,
    static fn (mixed $value): bool => $value !== null && $value !== '' && $value !== 1
);
$pageUrl = static function (int $page) use ($queryFilters): string {
    $query = http_build_query(array_merge($queryFilters, ['page' => $page]));
    return site_url('grounds') . ($query === '' ? '' : '?' . $query);
};
?>
<?= view('staff_header', ['title' => $title]) ?>
<main class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1>Grounds</h1>
        <a class="mcc-button mcc-button-primary" href="<?= site_url('grounds/new') ?>">
            <i class="fa-solid fa-plus" aria-hidden="true"></i>
            Create ground
        </a>
    </div>
    <section class="mcc-card" aria-labelledby="ground-filters-heading">
        <h2 id="ground-filters-heading" class="sr-only">Ground filters</h2>
        <form method="get" action="<?= site_url('grounds') ?>" class="row">
            <div class="col-12 col-md-6 mb-2">
                <label for="ground-query">Search</label>
                <input class="form-control" id="ground-query" name="q" type="search" maxlength="100" value="<?= esc((string) ($filters['q'] ?? '')) ?>">
            </div>
            <div class="col-12 col-md-6 mb-2">
                <label for="ground-status-filter">Status</label>
                <select class="form-control" id="ground-status-filter" name="status">
                    <option value="">All statuses</option>
                    <option value="active" <?= ($filters['status'] ?? '') === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= ($filters['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
            <div class="col-12 text-right">
                <button class="mcc-button mcc-button-secondary" type="submit">
                    <i class="fa-solid fa-filter" aria-hidden="true"></i>
                    Filter grounds
                </button>
            </div>
        </form>
    </section>
    <section class="mcc-card" aria-labelledby="ground-list-heading">
        <h2 id="ground-list-heading">Available grounds</h2>
        <?php if ($result['grounds'] === []): ?>
            <div class="mcc-empty-state">
                <i class="fa-solid fa-building-circle-exclamation" aria-hidden="true"></i>
                <strong>No grounds found</strong>
                <span>No grounds match the current search and status filters.</span>
            </div>
        <?php else: ?>
            <div class="mcc-table-wrap">
                <table class="mcc-table">
                    <caption class="sr-only">Authorized grounds</caption>
                    <thead>
                        <tr>
                            <th scope="col">Name</th>
                            <th scope="col">Type</th>
                            <th scope="col">Branch</th>
                            <th scope="col">Status</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($result['grounds'] as $ground): ?>
                            <tr>
                                <th scope="row"><?= esc($ground['name']) ?></th>
                                <td><?= esc(ucwords(str_replace('_', ' ', (string) $ground['ground_type']))) ?></td>
                                <td><?= esc($ground['branch_name']) ?></td>
                                <td><?= $ground['is_active'] ? 'Active' : 'Inactive' ?></td>
                                <td>
                                    <a href="<?= site_url('grounds/' . (int) $ground['id']) ?>">View</a>
                                    <?php if (! empty($ground['can_update'])): ?>
                                        <span aria-hidden="true"> · </span>
                                        <a href="<?= site_url('grounds/' . (int) $ground['id'] . '/edit') ?>">Edit</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if ((int) $result['pages'] > 1): ?>
                <nav class="mcc-pagination" aria-label="Ground pages">
                    <span>Page <?= (int) $result['page'] ?> of <?= (int) $result['pages'] ?></span>
                    <div>
                        <?php if ((int) $result['page'] > 1): ?>
                            <a class="mcc-button mcc-button-secondary" href="<?= esc($pageUrl((int) $result['page'] - 1)) ?>">Previous</a>
                        <?php endif; ?>
                        <?php for ($page = 1; $page <= (int) $result['pages']; $page++): ?>
                            <a class="mcc-button mcc-button-secondary" href="<?= esc($pageUrl($page)) ?>" <?= $page === (int) $result['page'] ? 'aria-current="page"' : '' ?>><?= $page ?></a>
                        <?php endfor; ?>
                        <?php if ((int) $result['page'] < (int) $result['pages']): ?>
                            <a class="mcc-button mcc-button-secondary" href="<?= esc($pageUrl((int) $result['page'] + 1)) ?>">Next</a>
                        <?php endif; ?>
                    </div>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </section>
</main>
<?= view('staff_footer') ?>
