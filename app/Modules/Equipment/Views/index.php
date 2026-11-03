<?php

$equipment = is_array($equipment ?? null) ? $equipment : [];
$canUpdate = (bool) ($canUpdate ?? false);
$canDelete = (bool) ($canDelete ?? false);
?>
<?= view('staff_header', ['title' => $title]) ?>
<section class="mcc-card" aria-labelledby="equipment-list-heading">
    <div class="mcc-card-heading">
        <div>
            <span class="mcc-eyebrow">Grounds &amp; Equipment</span>
            <h2 id="equipment-list-heading">Equipment inventory</h2>
            <p class="mcc-card-subtitle">Equipment records in your authorized branch scope.</p>
        </div>
        <a class="mcc-button mcc-button-primary" href="<?= site_url('equipment/new') ?>">
            Create equipment
        </a>
    </div>

    <?php if ($equipment === []): ?>
        <div class="mcc-empty-state">
            <i class="fa-solid fa-box-open" aria-hidden="true"></i>
            <strong>No equipment records</strong>
            <span>No equipment records are available in the current branch scope.</span>
        </div>
    <?php else: ?>
        <div class="mcc-table-wrap">
            <table class="mcc-table">
                <caption class="sr-only">Equipment records in authorized branches</caption>
                <thead>
                    <tr>
                            <th scope="col">Name</th>
                            <th scope="col">Quantity</th>
                            <th scope="col">Branch</th>
                            <?php if ($canUpdate || $canDelete): ?>
                                <th scope="col">Actions</th>
                            <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($equipment as $record): ?>
                        <tr>
                            <th scope="row"><?= esc($record['name']) ?></th>
                            <td><?= (int) $record['quantity'] ?></td>
                            <td><?= esc($record['branch_name']) ?></td>
                            <?php if ($canUpdate || $canDelete): ?>
                                <td>
                                    <?php if (! empty($record['can_update'])): ?>
                                        <a href="<?= site_url('equipment/' . (int) $record['id'] . '/edit') ?>">
                                            Edit
                                        </a>
                                    <?php endif; ?>
                                    <?php if (! empty($record['can_update']) && ! empty($record['can_delete'])): ?>
                                        <span aria-hidden="true"> · </span>
                                    <?php endif; ?>
                                    <?php if (! empty($record['can_delete'])): ?>
                                        <a href="<?= site_url('equipment/' . (int) $record['id'] . '/delete') ?>">
                                            Delete
                                        </a>
                                    <?php endif; ?>
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
