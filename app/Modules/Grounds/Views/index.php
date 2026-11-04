<?php

$result = is_array($result ?? null) ? $result : [];
$grounds = is_array($result['grounds'] ?? null) ? $result['grounds'] : [];
$filters = is_array($result['filters'] ?? null) ? $result['filters'] : [];
$hasFilters = ($filters['q'] ?? '') !== '' || ($filters['status'] ?? '') !== '';
$queryFilters = array_filter(
    $filters,
    static fn (mixed $value): bool => $value !== null && $value !== '' && $value !== 1
);
$pageUrl = static function (int $page) use ($queryFilters): string {
    $query = http_build_query(array_merge($queryFilters, ['page' => $page]));

    return site_url('grounds') . ($query === '' ? '' : '?' . $query);
};

?>
<?= view('staff_header', [
    'title' => $title,
    'pageActions' => '<div class="mcc-page-actions">'
        . '<a class="mcc-button mcc-button-primary" href="' . site_url('grounds/new') . '">'
        . '<i class="fa-solid fa-plus" aria-hidden="true"></i> Create ground</a>'
        . '<button class="mcc-button mcc-button-secondary" type="button" data-filter-toggle '
        . 'data-show-label="Show Filters" data-hide-label="Hide Filters" '
        . 'aria-expanded="' . ($hasFilters ? 'true' : 'false') . '" aria-controls="ground-filters">'
        . '<i class="fa-solid fa-filter" aria-hidden="true"></i> '
        . '<span class="mcc-button-label">' . ($hasFilters ? 'Hide Filters' : 'Show Filters') . '</span></button>'
        . '</div>',
]) ?>

<section class="mcc-card mcc-filter-card" id="ground-filters" <?= $hasFilters ? '' : 'hidden' ?> aria-labelledby="ground-filters-heading">
    <div class="mcc-card-heading">
        <div>
            <span class="mcc-eyebrow">Grounds &amp; Equipment</span>
            <h2 id="ground-filters-heading">Filter grounds</h2>
            <p class="mcc-card-subtitle">Search authorized grounds by name or status.</p>
        </div>
    </div>
    <form method="get" action="<?= site_url('grounds') ?>" class="mcc-filter-grid">
        <div class="mcc-form-field">
            <label for="ground-query">Search</label>
            <input class="form-control" id="ground-query" name="q" type="search" maxlength="100" value="<?= esc((string) ($filters['q'] ?? '')) ?>" placeholder="Search grounds">
        </div>
        <div class="mcc-form-field">
            <label for="ground-status-filter">Status</label>
            <select class="form-control" id="ground-status-filter" name="status">
                <option value="">All statuses</option>
                <option value="active" <?= ($filters['status'] ?? '') === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="inactive" <?= ($filters['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
            </select>
        </div>
        <div class="mcc-form-actions">
            <button class="mcc-button mcc-button-primary" type="submit"><i class="fa-solid fa-filter" aria-hidden="true"></i> Apply filters</button>
            <a class="mcc-button mcc-button-secondary" href="<?= site_url('grounds') ?>">Clear</a>
        </div>
    </form>
</section>

<section class="mcc-card" aria-labelledby="ground-list-heading">
    <div class="mcc-card-heading">
        <div>
            <span class="mcc-eyebrow">Authorized inventory</span>
            <h2 id="ground-list-heading">Grounds</h2>
            <p class="mcc-card-subtitle"><?= (int) ($result['total'] ?? count($grounds)) ?> ground(s) found.</p>
        </div>
    </div>

    <?php if ($grounds === []): ?>
        <div class="mcc-empty-state" role="status">
            <i class="fa-solid fa-building-circle-exclamation mcc-empty-icon" aria-hidden="true"></i>
            <strong>No grounds found</strong>
            <span>No grounds match the current search and status filters.</span>
        </div>
    <?php else: ?>
        <div class="mcc-table-wrap">
            <table class="mcc-table">
                <caption class="sr-only">Authorized grounds</caption>
                <thead>
                    <tr>
                        <th scope="col">Ground</th>
                        <th scope="col">Type</th>
                        <th scope="col">Branch</th>
                        <th scope="col">Status</th>
                        <th scope="col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($grounds as $ground): ?>
                        <?php $active = (bool) $ground['is_active']; ?>
                        <tr>
                            <th scope="row">
                                <span class="mcc-table-primary-text"><?= esc((string) $ground['name']) ?></span>
                                <span class="mcc-table-secondary-text">Ground #<?= (int) $ground['id'] ?></span>
                            </th>
                            <td><?= esc(ucwords(str_replace('_', ' ', (string) $ground['ground_type']))) ?></td>
                            <td><span class="mcc-table-secondary-text"><?= esc((string) $ground['branch_name']) ?></span></td>
                            <td><span class="mcc-badge mcc-badge-<?= $active ? 'success' : 'neutral' ?>"><?= $active ? 'Active' : 'Inactive' ?></span></td>
                            <td>
                                <div class="mcc-session-action-menu" data-session-actions>
                                    <button class="mcc-button mcc-button-secondary mcc-button-sm" type="button" data-session-actions-toggle aria-expanded="false" aria-haspopup="true" aria-controls="ground-actions-<?= (int) $ground['id'] ?>">
                                        <i class="fa-solid fa-ellipsis-vertical" aria-hidden="true"></i> Actions
                                    </button>
                                    <div class="mcc-session-action-dropdown" id="ground-actions-<?= (int) $ground['id'] ?>" data-session-actions-menu hidden>
                                        <a href="<?= esc(site_url('grounds/' . (int) $ground['id'])) ?>"><i class="fa-solid fa-eye" aria-hidden="true"></i> View ground</a>
                                        <?php if (! empty($ground['can_update'])): ?>
                                            <a href="<?= esc(site_url('grounds/' . (int) $ground['id'] . '/edit')) ?>"><i class="fa-solid fa-pen-to-square" aria-hidden="true"></i> Edit ground</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ((int) ($result['pages'] ?? 1) > 1): ?>
            <nav class="mcc-pagination" aria-label="Ground pages">
                <span>Page <?= (int) $result['page'] ?> of <?= (int) $result['pages'] ?></span>
                <div class="mcc-pagination-actions">
                    <?php if ((int) $result['page'] > 1): ?><a class="mcc-button mcc-button-secondary" href="<?= esc($pageUrl((int) $result['page'] - 1)) ?>">Previous</a><?php endif; ?>
                    <?php for ($page = 1; $page <= (int) $result['pages']; $page++): ?>
                        <a class="mcc-button <?= $page === (int) $result['page'] ? 'mcc-button-primary' : 'mcc-button-secondary' ?>" href="<?= esc($pageUrl($page)) ?>" <?= $page === (int) $result['page'] ? 'aria-current="page"' : '' ?>><?= $page ?></a>
                    <?php endfor; ?>
                    <?php if ((int) $result['page'] < (int) $result['pages']): ?><a class="mcc-button mcc-button-secondary" href="<?= esc($pageUrl((int) $result['page'] + 1)) ?>">Next</a><?php endif; ?>
                </div>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</section>

<?= view('staff_footer') ?>
