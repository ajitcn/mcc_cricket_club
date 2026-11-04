<?php

$assignments = is_array($assignments ?? null) ? $assignments : [];
$filters = is_array($filters ?? null) ? $filters : [];
$hasFilters = trim((string) ($filters['q'] ?? '')) !== '' || trim((string) ($filters['status'] ?? '')) !== '';

?>
<?= view('staff_header', [
    'title' => $title,
    'pageActions' => '<div class="mcc-page-actions">'
        . '<a class="mcc-button mcc-button-primary" href="' . site_url('equipment/assignments/new') . '"><i class="fa-solid fa-plus" aria-hidden="true"></i> Assign equipment</a>'
        . '<button class="mcc-button mcc-button-secondary" type="button" data-filter-toggle data-show-label="Show Filters" data-hide-label="Hide Filters" aria-expanded="' . ($hasFilters ? 'true' : 'false') . '" aria-controls="equipment-assignment-filters"><i class="fa-solid fa-filter" aria-hidden="true"></i> <span class="mcc-button-label">' . ($hasFilters ? 'Hide Filters' : 'Show Filters') . '</span></button>'
        . '</div>',
]) ?>
<section class="mcc-card mcc-filter-card" id="equipment-assignment-filters" <?= $hasFilters ? '' : 'hidden' ?> aria-labelledby="equipment-assignment-filters-heading">
    <div class="mcc-card-heading">
        <div>
            <span class="mcc-eyebrow">Equipment management</span>
            <h2 id="equipment-assignment-filters-heading">Filter assignments</h2>
            <p class="mcc-card-subtitle">Search by Coach or branch and filter by return status.</p>
        </div>
    </div>
    <form method="get" action="<?= site_url('equipment/assignments') ?>" class="mcc-filter-grid">
        <div class="mcc-form-field">
            <label for="equipment-assignment-search">Search</label>
            <input class="form-control" id="equipment-assignment-search" name="q" type="search" value="<?= esc((string) ($filters['q'] ?? '')) ?>" placeholder="Search Coach or branch">
        </div>
        <div class="mcc-form-field">
            <label for="equipment-assignment-status">Status</label>
            <select class="form-control" id="equipment-assignment-status" name="status">
                <option value="">All statuses</option>
                <?php foreach (['assigned' => 'Assigned', 'partially_returned' => 'Partially returned', 'returned' => 'Returned'] as $value => $label): ?>
                    <option value="<?= esc($value) ?>" <?= (string) ($filters['status'] ?? '') === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mcc-form-actions">
            <button class="mcc-button mcc-button-primary" type="submit"><i class="fa-solid fa-filter" aria-hidden="true"></i> Apply filters</button>
            <a class="mcc-button mcc-button-secondary" href="<?= site_url('equipment/assignments') ?>">Clear</a>
        </div>
    </form>
</section>
<section class="mcc-card mcc-equipment-assignment-list" aria-labelledby="equipment-assignments-heading">
    <div class="mcc-card-heading"><div><span class="mcc-eyebrow">Grounds &amp; Equipment</span><h2 id="equipment-assignments-heading">Equipment assignments</h2><p class="mcc-card-subtitle">Track equipment issued to Coaches and returned items.</p></div></div>
    <?php if ($assignments === []): ?><div class="mcc-empty-state" role="status">No equipment assignments found.</div><?php else: ?>
        <div class="mcc-table-wrap"><table class="mcc-table"><thead><tr><th>Coach</th><th>Branch</th><th>Assigned</th><th>Issued qty</th><th>Returned qty</th><th>Status</th><th>Actions</th></tr></thead><tbody>
            <?php foreach ($assignments as $assignment): ?>
                <tr>
                    <th><?= esc($assignment['coach_name']) ?></th>
                    <td><?= esc($assignment['branch_name']) ?></td>
                    <td><span class="mcc-muted mcc-meta-text"><?= esc(mcc_user_date($assignment['assigned_date'])) ?></span></td>
                    <td><?= (int) ($assignment['quantity_assigned'] ?? 0) ?></td>
                    <td><?= (int) ($assignment['quantity_returned'] ?? 0) ?></td>
                    <td><span class="mcc-badge mcc-badge-<?= $assignment['status'] === 'returned' ? 'success' : 'warning' ?>"><?= esc(ucwords(str_replace('_', ' ', $assignment['status']))) ?></span></td>
                    <td>
                        <div class="mcc-session-action-menu" data-session-actions>
                            <button class="mcc-button mcc-button-secondary mcc-button-sm" type="button" data-session-actions-toggle aria-expanded="false" aria-haspopup="true" aria-controls="equipment-assignment-actions-<?= (int) $assignment['id'] ?>">
                                <i class="fa-solid fa-ellipsis-vertical" aria-hidden="true"></i> Actions
                            </button>
                            <div class="mcc-session-action-dropdown" id="equipment-assignment-actions-<?= (int) $assignment['id'] ?>" data-session-actions-menu hidden>
                                <a href="<?= esc(site_url('equipment/assignments/' . (int) $assignment['id'])) ?>"><i class="fa-solid fa-eye" aria-hidden="true"></i> View assignment</a>
                            <?php if (in_array($assignment['status'], ['assigned', 'partially_returned'], true)): ?>
                                <a href="<?= esc(site_url('equipment/assignments/' . (int) $assignment['id'] . '/edit')) ?>"><i class="fa-solid fa-pen-to-square" aria-hidden="true"></i> Edit assignment</a>
                                <a href="<?= esc(site_url('equipment/assignments/' . (int) $assignment['id'] . '/return')) ?>">
                                    <i class="fa-solid fa-rotate-left" aria-hidden="true"></i> Record return
                                </a>
                            <?php endif; ?>
                            </div>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody></table></div>
    <?php endif; ?>
</section>
<?= view('staff_footer') ?>
