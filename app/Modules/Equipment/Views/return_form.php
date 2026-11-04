<?php

$assignment = is_array($assignment ?? null) ? $assignment : [];
$status = (string) ($assignment['status'] ?? 'assigned');
$canReturn = in_array($status, ['assigned', 'partially_returned'], true);
$items = is_array($assignment['items'] ?? null) ? $assignment['items'] : [];

?>
<?= view('staff_header', [
    'title' => $title,
    'pageActions' => '<a class="mcc-button mcc-button-secondary" href="'
        . site_url('equipment/assignments/' . (int) ($assignment['id'] ?? 0))
        . '"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> View assignment</a>',
]) ?>

<section class="mcc-card mcc-equipment-assignment-card" aria-labelledby="equipment-return-heading">
    <div class="mcc-card-heading">
        <div>
            <span class="mcc-eyebrow">Equipment management</span>
            <h2 id="equipment-return-heading">Record equipment return</h2>
            <p class="mcc-card-subtitle">Enter the quantity returned for each item. A partial return keeps the assignment open.</p>
        </div>
        <span class="mcc-badge mcc-badge-<?= $status === 'returned' ? 'success' : 'warning' ?>">
            <?= esc(ucwords(str_replace('_', ' ', $status))) ?>
        </span>
    </div>

    <div class="mcc-detail-grid mcc-equipment-assignment-summary">
        <div><dt>Branch</dt><dd><?= esc((string) ($assignment['branch_name'] ?? '—')) ?></dd></div>
        <div><dt>Coach</dt><dd><?= esc((string) ($assignment['coach_name'] ?? '—')) ?></dd></div>
        <div><dt>Assigned date</dt><dd><?= esc(mcc_user_date((string) ($assignment['assigned_date'] ?? ''))) ?></dd></div>
    </div>

    <?php if ($canReturn && $items !== []): ?>
        <form id="equipment-return-form" method="post" action="<?= site_url('equipment/assignments/' . (int) ($assignment['id'] ?? 0) . '/return') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="returned_date" value="<?= esc(date('Y-m-d')) ?>">
            <div class="mcc-table-wrap">
                <table class="mcc-table">
                    <thead>
                        <tr>
                            <th>Equipment</th>
                            <th>Issued</th>
                            <th>Returned</th>
                            <th>Remaining</th>
                            <th>Return now</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                            <?php $remaining = max(0, (int) $item['quantity_assigned'] - (int) $item['quantity_returned']); ?>
                            <tr>
                                <th><?= esc((string) ($item['equipment_name'] ?? 'Equipment')) ?></th>
                                <td><?= (int) $item['quantity_assigned'] ?></td>
                                <td><?= (int) $item['quantity_returned'] ?></td>
                                <td><?= $remaining ?></td>
                                <td>
                                    <input
                                        class="form-control mcc-equipment-return-input"
                                        type="number"
                                        min="0"
                                        max="<?= $remaining ?>"
                                        name="returned[<?= (int) $item['id'] ?>]"
                                        value="<?= esc((string) old('returned.' . (int) $item['id'], '0')) ?>"
                                        aria-label="Return quantity for <?= esc((string) ($item['equipment_name'] ?? 'equipment')) ?>"
                                    >
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="mcc-form-actions">
                <button class="mcc-button mcc-button-primary" type="submit">
                    <i class="fa-solid fa-rotate-left" aria-hidden="true"></i> Confirm return
                </button>
                <a class="mcc-button mcc-button-secondary" href="<?= site_url('equipment/assignments/' . (int) ($assignment['id'] ?? 0)) ?>">Cancel</a>
            </div>
        </form>
    <?php else: ?>
        <div class="mcc-empty-state" role="status">This assignment has no remaining equipment to return.</div>
    <?php endif; ?>
</section>

<?= view('staff_footer') ?>
