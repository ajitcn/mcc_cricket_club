<?php
$achievements = is_array($achievements ?? null) ? $achievements : [];
$filters = is_array($filters ?? null) ? $filters : [];
$branches = is_array($branches ?? null) ? $branches : [];
$categories = is_array($categories ?? null) ? $categories : [];
$canEdit = service('authorization')->can('achievement.update') || service('authorization')->can('achievement.create');
$hasFilters = ($filters['branch_id'] ?? '') !== '' || ($filters['category'] ?? '') !== '' || ($filters['status'] ?? '') !== '';
?>
<?= view('staff_header', [
    'title' => $title,
    'pageActions' => '<div class="mcc-page-actions">
        <button class="mcc-button mcc-button-secondary" type="button" data-filter-toggle data-show-label="Show Filters" data-hide-label="Hide Filters" aria-controls="achievement-filters" aria-expanded="' . ($hasFilters ? 'true' : 'false') . '"><i class="fa-solid fa-filter" aria-hidden="true"></i><span class="mcc-button-label">' . ($hasFilters ? 'Hide Filters' : 'Show Filters') . '</span></button>'
        . (service('authorization')->can('achievement.create') ? '<a class="mcc-button mcc-button-primary" href="' . site_url('achievements/new') . '"><i class="fa-solid fa-plus" aria-hidden="true"></i><span class="mcc-button-label">Create Achievement</span></a>' : '')
        . '</div>',
]) ?>
<div class="mcc-achievements-page" id="main-content">
    <section id="achievement-filters" class="mcc-card mcc-achievement-filter-card" aria-labelledby="achievement-filter-heading" <?= $hasFilters ? '' : 'hidden' ?>>
        <div class="mcc-card-heading">
            <div>
                <span class="mcc-eyebrow">Search and filter</span>
                <h2 id="achievement-filter-heading">Find achievements</h2>
            </div>
        </div>
        <form method="get" action="<?= site_url('achievements') ?>" class="mcc-form-grid mcc-achievement-filter-form">
            <div class="mcc-form-field">
                <label for="achievement-filter-branch">Branch</label>
                <select class="form-control" id="achievement-filter-branch" name="branch_id">
                    <option value="">All authorized branches</option>
                    <?php foreach ($branches as $branch): ?>
                        <option value="<?= (int) $branch['id'] ?>" <?= (string) ($filters['branch_id'] ?? '') === (string) $branch['id'] ? 'selected' : '' ?>>
                            <?= esc($branch['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mcc-form-field">
                <label for="achievement-filter-category">Category</label>
                <select class="form-control" id="achievement-filter-category" name="category">
                    <option value="">All categories</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= esc($category) ?>" <?= (string) ($filters['category'] ?? '') === (string) $category ? 'selected' : '' ?>>
                            <?= esc($category) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mcc-form-field">
                <label for="achievement-filter-status">Status</label>
                <select class="form-control" id="achievement-filter-status" name="status">
                    <option value="">All statuses</option>
                    <option value="active" <?= ($filters['status'] ?? '') === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= ($filters['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
            <div class="mcc-form-actions">
                <button class="mcc-button mcc-button-primary" type="submit">Apply filters</button>
                <a class="mcc-button mcc-button-secondary" href="<?= site_url('achievements') ?>">Clear</a>
            </div>
        </form>
    </section>
    <section class="mcc-card mcc-scheduling-card mcc-achievement-results-card" aria-labelledby="achievement-list-heading">
        <div class="mcc-card-heading">
            <div>
                <span class="mcc-eyebrow">Authorized catalog</span>
                <h2 id="achievement-list-heading">Achievements</h2>
            </div>
            <span class="mcc-muted"><?= count($achievements) ?> configured</span>
        </div>
        <?php if ($achievements === []): ?>
            <div class="mcc-empty-state">
                <strong>No achievements configured</strong>
                <span>Create a branch-scoped Achievement title to begin the catalog.</span>
            </div>
        <?php else: ?>
            <div class="mcc-table-wrap">
                <table class="mcc-table mcc-achievement-table">
                    <caption class="sr-only">Authorized Achievement catalog</caption>
                    <thead>
                        <tr>
                            <th scope="col">Title</th>
                            <th scope="col">Category</th>
                            <th scope="col">Branch</th>
                            <th scope="col">Status</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($achievements as $achievement): ?>
                            <tr>
                                <th scope="row"><?= esc($achievement['title']) ?></th>
                                <td data-label="Category"><?= esc($achievement['category']) ?></td>
                                <td data-label="Branch"><?= esc($achievement['branch_name']) ?></td>
                                <td data-label="Status"><span class="mcc-badge mcc-badge-<?= (int) $achievement['is_active'] === 1 ? 'success' : 'neutral' ?>"><?= (int) $achievement['is_active'] === 1 ? 'Active' : 'Inactive' ?></span></td>
                                <td data-label="Actions">
                                    <a class="mcc-button mcc-button-secondary" href="<?= site_url('achievements/' . (int) $achievement['id']) ?>">View</a>
                                    <?php if ($canEdit): ?>
                                        <a class="mcc-button mcc-button-secondary" href="<?= site_url('achievements/' . (int) $achievement['id'] . '/edit') ?>">Edit</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>
<?= view('staff_footer') ?>
