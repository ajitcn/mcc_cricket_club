<?php

$equipment = is_array($equipment ?? null) ? $equipment : [];
$canUpdate = (bool) ($canUpdate ?? false);
$canDelete = (bool) ($canDelete ?? false);
?>
<?= view('staff_header', [
    'title' => $title,
    'pageActions' => '<a class="mcc-button mcc-button-primary" href="' . site_url('equipment/new') . '"><i class="fa-solid fa-plus" aria-hidden="true"></i> Create equipment</a>',
]) ?>
<section class="mcc-card" aria-labelledby="equipment-list-heading">
    <div class="mcc-session-summary" aria-live="polite">
        <div>
            <span class="mcc-eyebrow">Grounds &amp; Equipment</span>
            <h2 id="equipment-list-heading">Equipment inventory</h2>
        </div>
        <div class="mcc-session-summary-meta">
            <span><strong><?= count($equipment) ?></strong> item<?= count($equipment) === 1 ? '' : 's' ?></span>
            <span class="mcc-muted">Authorized branches</span>
        </div>
    </div>

    <?php if ($equipment === []): ?>
        <div class="mcc-empty-state">
            <i class="fa-solid fa-box-open" aria-hidden="true"></i>
            <strong>No equipment records</strong>
            <span>No equipment records are available in the current branch scope.</span>
        </div>
    <?php else: ?>
        <div class="mcc-table-wrap">
            <table class="mcc-table mcc-equipment-table">
                <caption class="sr-only">Equipment records in authorized branches</caption>
                <thead>
                    <tr>
                            <th scope="col">Equipment</th>
                            <th scope="col">Stock summary</th>
                            <th scope="col">Branch</th>
                            <?php if ($canUpdate || $canDelete): ?>
                                <th scope="col">Actions</th>
                            <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($equipment as $record): ?>
                        <tr>
                            <th scope="row">
                                <span class="mcc-table-primary-text"><?= esc($record['name']) ?></span>
                                <span class="mcc-table-secondary-text">Equipment #<?= (int) $record['id'] ?></span>
                            </th>
                            <td data-label="Stock summary">
                                <span class="mcc-table-stack mcc-equipment-stock-summary">
                                    <span class="mcc-badge mcc-badge-info">Assigned <?= (int) ($record['quantity_assigned'] ?? 0) ?></span>
                                    <span class="mcc-badge mcc-badge-success">Available <?= (int) ($record['quantity_available'] ?? $record['quantity']) ?></span>
                                </span>
                            </td>
                            <td data-label="Branch"><span class="mcc-table-secondary-text"><?= esc($record['branch_name']) ?></span></td>
                            <?php if ($canUpdate || $canDelete): ?>
                                <td data-label="Actions">
                                    <div class="mcc-session-action-menu" data-session-actions>
                                        <button class="mcc-button mcc-button-secondary mcc-button-sm" type="button" data-session-actions-toggle aria-expanded="false" aria-haspopup="true" aria-controls="equipment-actions-<?= (int) $record['id'] ?>"><i class="fa-solid fa-ellipsis-vertical" aria-hidden="true"></i> Actions</button>
                                        <div class="mcc-session-action-dropdown" id="equipment-actions-<?= (int) $record['id'] ?>" data-session-actions-menu hidden>
                                            <?php if (! empty($record['can_update'])): ?><a href="<?= esc(site_url('equipment/' . (int) $record['id'] . '/edit')) ?>"><i class="fa-solid fa-pen-to-square" aria-hidden="true"></i> Edit</a><?php endif; ?>
                                            <?php if (! empty($record['can_delete'])): ?><a href="<?= esc(site_url('equipment/' . (int) $record['id'] . '/delete')) ?>"><i class="fa-solid fa-trash" aria-hidden="true"></i> Delete</a><?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?= view('staff_footer') ?>
