<?php

$assignment = is_array($assignment ?? null) ? $assignment : [];
$status = (string) ($assignment['status'] ?? 'assigned');

?>
<?= view('staff_header', [
    'title' => $title,
    'pageActions' => '<a class="mcc-button mcc-button-secondary" href="' . site_url('equipment/assignments') . '"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back to assignments</a>',
]) ?>

<section class="mcc-card mcc-equipment-assignment-card" aria-labelledby="assignment-view-heading">
    <div class="mcc-card-heading">
        <div>
            <span class="mcc-eyebrow">Equipment management</span>
            <h2 id="assignment-view-heading">Equipment assignment #<?= (int) ($assignment['id'] ?? 0) ?></h2>
            <p class="mcc-card-subtitle">Review the equipment issued for this assignment.</p>
        </div>
        <span class="mcc-badge mcc-badge-<?= $status === 'returned' ? 'success' : 'warning' ?>">
            <?= esc(ucwords(str_replace('_', ' ', $status))) ?>
        </span>
    </div>

    <div class="mcc-detail-grid mcc-equipment-assignment-summary">
        <div><dt>Branch</dt><dd><?= esc((string) ($assignment['branch_name'] ?? '—')) ?></dd></div>
        <div><dt>Coach</dt><dd><?= esc((string) ($assignment['coach_name'] ?? '—')) ?></dd></div>
        <div><dt>Assigned date</dt><dd><?= esc(mcc_user_date((string) ($assignment['assigned_date'] ?? ''))) ?></dd></div>
        <div><dt>Returned date</dt><dd><?= $assignment['returned_date'] ? esc(mcc_user_date((string) $assignment['returned_date'])) : '—' ?></dd></div>
    </div>

    <div class="mcc-table-wrap">
        <table class="mcc-table">
            <thead>
                <tr>
                    <th>Equipment</th>
                    <th>Issued</th>
                    <th>Returned</th>
                    <th>Remaining</th>
                </tr>
            </thead>
            <tbody>
                <?php $items = is_array($assignment['items'] ?? null) ? $assignment['items'] : []; ?>
                <?php if ($items === []): ?>
                    <tr>
                        <td colspan="4">
                            <div class="mcc-empty-state" role="status">No equipment items were recorded for this assignment.</div>
                        </td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($items as $item): ?>
                    <?php $remaining = max(0, (int) $item['quantity_assigned'] - (int) $item['quantity_returned']); ?>
                    <tr>
                        <th><?= esc((string) ($item['equipment_name'] ?? 'Equipment')) ?></th>
                        <td><?= (int) $item['quantity_assigned'] ?></td>
                        <td><?= (int) $item['quantity_returned'] ?></td>
                        <td><?= $remaining ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

</section>

<?= view('staff_footer') ?>
