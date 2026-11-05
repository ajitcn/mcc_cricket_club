<?php

$branches = is_array($branches ?? null) ? $branches : [];
$filters = is_array($filters ?? null) ? $filters : [];
$query = trim((string) ($filters['q'] ?? ''));
$status = (string) ($filters['status'] ?? '');
$hasFilters = $query !== '' || $status !== '';
?>
<?= view('staff_header', [
    'title' => 'Branches',
    'nav' => $nav,
    'message' => $message ?? null,
    'error' => $error ?? null,
    'pageActions' => '<div class="mcc-page-actions">
        <button class="mcc-button mcc-button-secondary" type="button" data-filter-toggle
            data-show-label="Show Filters" data-hide-label="Hide Filters"
            aria-controls="branch-filters" aria-expanded="' . ($hasFilters ? 'true' : 'false') . '">
            <i class="fa-solid fa-filter" aria-hidden="true"></i>
            <span class="mcc-button-label">' . ($hasFilters ? 'Hide Filters' : 'Show Filters') . '</span>
        </button>
        <a class="mcc-button mcc-button-primary" href="' . site_url('admin/branches/create') . '">
            <i class="fa-solid fa-plus" aria-hidden="true"></i>
            <span class="mcc-button-label">Create branch</span>
        </a>
    </div>',
]) ?>

<section id="branch-filters" class="mcc-card mcc-admin-filter-card" aria-labelledby="branch-filters-heading" <?= $hasFilters ? '' : 'hidden' ?>>
    <div class="mcc-card-heading">
        <div>
            <span class="mcc-eyebrow">Search and filter</span>
            <h2 id="branch-filters-heading">Find a branch</h2>
        </div>
    </div>
    <form method="get" action="<?= esc(site_url('admin/branches')) ?>" class="row mcc-form" aria-label="Filter branches">
        <div class="mcc-form-field col-12 col-md-6">
            <label for="branch-search">Search</label>
            <input class="form-control" id="branch-search" name="q" value="<?= esc($query) ?>" placeholder="Branch name, code, or address">
        </div>
        <div class="mcc-form-field col-12 col-md-4">
            <label for="branch-status">Status</label>
            <select class="form-control" id="branch-status" name="status">
                <option value="">All statuses</option>
                <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
            </select>
        </div>
        <div class="mcc-form-actions col-12 col-md-2">
            <button class="mcc-button mcc-button-primary" type="submit"><i class="fa-solid fa-filter" aria-hidden="true"></i> Apply</button>
            <?php if ($hasFilters): ?>
                <a class="mcc-button mcc-button-secondary" href="<?= esc(site_url('admin/branches')) ?>">Clear</a>
            <?php endif; ?>
        </div>
    </form>
</section>

<section class="mcc-card mcc-admin-branches-card" aria-labelledby="branches-heading">
    <div class="mcc-card-heading">
        <div>
            <span class="mcc-eyebrow">Administration</span>
            <h1 id="branches-heading">Branches</h1>
            <p class="mcc-card-subtitle">Manage active academy locations, codes, and addresses.</p>
        </div>
        <span class="mcc-muted" aria-label="Branch count"><?= count($branches) ?> branch<?= count($branches) === 1 ? '' : 'es' ?></span>
    </div>

    <?php if ($branches === []): ?>
        <div class="mcc-empty-state" role="status">
            <i class="fa-solid fa-building" aria-hidden="true"></i>
            <strong>No branches found</strong>
            <span>Try changing the filters or create a new branch.</span>
        </div>
    <?php else: ?>
        <div class="mcc-table-wrap">
            <table class="mcc-table">
                <caption class="sr-only">Organization branches</caption>
                <thead>
                    <tr>
                        <th scope="col">Branch</th>
                        <th scope="col">Address</th>
                        <th scope="col">Status</th>
                        <th scope="col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($branches as $branch): ?>
                        <?php $active = (int) ($branch['is_active'] ?? 0) === 1; ?>
                        <tr>
                            <th scope="row" data-label="Branch">
                                <span class="mcc-table-primary-text"><?= esc((string) ($branch['name'] ?? 'Branch')) ?></span>
                                <span class="mcc-table-secondary-text">Code: <?= esc((string) ($branch['code'] ?? '—')) ?></span>
                            </th>
                            <td data-label="Address">
                                <span class="mcc-table-secondary-text">
                                    <?= esc((string) ($branch['address'] ?? 'Not provided')) ?>
                                </span>
                            </td>
                            <td data-label="Status">
                                <span class="mcc-badge mcc-badge-<?= $active ? 'success' : 'neutral' ?>">
                                    <?= $active ? 'Active' : 'Inactive' ?>
                                </span>
                            </td>
                            <td data-label="Actions">
                                <div class="mcc-session-action-menu" data-session-actions>
                                    <button
                                        class="mcc-button mcc-button-secondary mcc-button-sm"
                                        type="button"
                                        data-session-actions-toggle
                                        aria-expanded="false"
                                        aria-haspopup="true"
                                        aria-controls="branch-actions-<?= (int) $branch['id'] ?>"
                                    >
                                        <i class="fa-solid fa-ellipsis-vertical" aria-hidden="true"></i> Actions
                                    </button>
                                    <div
                                        class="mcc-session-action-dropdown"
                                        id="branch-actions-<?= (int) $branch['id'] ?>"
                                        data-session-actions-menu
                                        hidden
                                    >
                                        <a href="<?= esc(site_url('admin/branches/' . (int) $branch['id'])) ?>">
                                            <i class="fa-solid fa-eye" aria-hidden="true"></i>
                                            View branch
                                        </a>
                                        <a href="<?= esc(site_url('admin/branches/' . (int) $branch['id'] . '/edit')) ?>">
                                            <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
                                            Edit branch
                                        </a>
                                        <form
                                            method="post"
                                            action="<?= esc(site_url('admin/branches/' . (int) $branch['id'] . '/status')) ?>"
                                            onsubmit="return confirm('<?= $active ? 'Deactivate' : 'Reactivate' ?> this branch?');"
                                        >
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="active" value="<?= $active ? '0' : '1' ?>">
                                            <button type="submit">
                                                <i class="fa-solid fa-power-off" aria-hidden="true"></i>
                                                <?= $active ? 'Deactivate branch' : 'Reactivate branch' ?>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?= view('staff_footer') ?>
