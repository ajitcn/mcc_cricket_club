<?php

$record = is_array($admission ?? null) ? $admission : [];
$formatMoney = static function (mixed $amount): string {
    $value = trim((string) ($amount ?? ''));

    if ($value === '') {
        return '₹0.00';
    }

    $parts = array_pad(explode('.', $value, 2), 2, '00');
    $whole = $parts[0];
    $fraction = $parts[1];

    return '₹' . number_format((int) $whole, 0, '.', ',') . '.' . str_pad($fraction, 2, '0');
};
?>
<section class="mcc-card mcc-detail-card" aria-labelledby="renewal-fees-heading">
    <div class="mcc-card-heading">
        <span class="mcc-eyebrow">Fees</span>
        <h3 id="renewal-fees-heading">Current Fee Plan</h3>
    </div>
    <div class="row mcc-admission-fields">
        <div class="mcc-form-field col-12 col-md-4">
            <label for="renewal-coaching-fee">Coaching Fee</label>
            <input class="form-control" id="renewal-coaching-fee" name="training_fee" type="text" value="<?= esc((string) ($record['training_fee'] ?? '0.00')) ?>" readonly aria-readonly="true">
        </div>
        <div class="mcc-form-field col-12 col-md-4">
            <label for="renewal-hostel-fee">Hostel Fee</label>
            <input class="form-control" id="renewal-hostel-fee" name="hostel_charges" type="text" value="<?= esc((string) ($record['hostel_charges'] ?? '0.00')) ?>" readonly aria-readonly="true">
        </div>
        <div class="mcc-form-field col-12 col-md-4">
            <label for="renewal-total-fee">Total Coaching + Hostel Fee</label>
            <input class="form-control" id="renewal-total-fee" type="text" value="<?= esc($formatMoney($record['total_coaching_hostel_fee'] ?? null)) ?>" readonly aria-readonly="true" data-admission-fee-total>
        </div>
    </div>
    <?php foreach ([
        'coaching' => ['title' => 'Coaching Instalments', 'rows' => $record['installment_rows'] ?? []],
        'hostel' => ['title' => 'Hostel Instalments', 'rows' => $record['hostel_installment_rows'] ?? []],
    ] as $schedule): ?>
        <h4><?= esc($schedule['title']) ?></h4>
        <?php if ($schedule['rows'] === []): ?>
            <p class="mcc-empty-state">Not applicable</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table mcc-table">
                    <thead>
                        <tr>
                            <th>Year</th>
                            <th>Instalment</th>
                            <th>Due date</th>
                            <th>Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($schedule['rows'] as $row): ?>
                            <?php foreach (range(1, 4) as $number): ?>
                                <?php if (empty($row['installment_' . $number . '_date'])) { continue; } ?>
                                <tr>
                                    <td><?= esc((string) ($row['year_label'] ?? ('Year ' . ($row['year_number'] ?? '')))) ?></td>
                                    <td><?= $number ?></td>
                                    <td><?= esc(mcc_user_date($row['installment_' . $number . '_date'] ?? null, '—')) ?></td>
                                    <td><?= $formatMoney($row['installment_' . $number] ?? null) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>
</section>
