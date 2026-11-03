<?php

$fieldValue = static function (string $field) use ($test): string {
    return esc((string) old($field, $test[$field] ?? ''));
};
$planValue = static function (string $field) use ($test): string {
    return (string) old($field, $test[$field] ?? '');
};
$rowsFor = static function (string $prefix, string $key) use ($test): array {
    $years = old($prefix . 'year');
    $stored = is_array($test[$key] ?? null) ? $test[$key] : [];
    $idsField = $prefix === 'installment_'
        ? 'coaching_installment_ids'
        : 'hostel_installment_ids';
    $legacyIdsField = $prefix . 'ids';
    if (! is_array($years)) {
        return $stored;
    }
    $rows = [];
    foreach ($years as $index => $year) {
        $savedIds = old($idsField, old($legacyIdsField));
        $row = [
            'year_number' => $year,
            'installment_ids' => is_array($savedIds)
                ? (json_decode((string) ($savedIds[$index] ?? '{}'), true) ?: [])
                : ($stored[$index]['installment_ids'] ?? []),
        ];
        for ($number = 1; $number <= 4; $number++) {
            $row['installment_' . $number . '_date'] = old($prefix . $number . '_date')[$index] ?? '';
            $row['installment_' . $number] = old($prefix . $number)[$index] ?? '';
        }
        $rows[] = $row;
    }
    return $rows;
};
$coachingPlan = $planValue('installment_plan');
$hostelPlan = $planValue('hostel_installment_plan');
$installmentPlanValue = 'Instalment Plan';
$coachingRows = $rowsFor('installment_', 'coaching_installment_rows');
$hostelRows = $rowsFor('hostel_installment_', 'hostel_installment_rows');
$defaultRows = [['year_number' => 1]];
$coachingSavedRows = $coachingRows;
$hostelSavedRows = $hostelRows;
$coachingRows = $coachingPlan === $installmentPlanValue
    ? ($coachingRows !== [] ? $coachingRows : $defaultRows)
    : [];
$hostelRows = $hostelPlan === $installmentPlanValue
    ? ($hostelRows !== [] ? $hostelRows : $defaultRows)
    : [];
$dateValue = static function (string $value): string {
    return esc(mcc_user_date_input($value));
};
$errorFor = static function (string $field): string {
    $errors = session()->getFlashdata('field_errors');
    return is_array($errors) ? esc((string) ($errors[$field] ?? '')) : '';
};
$renderSchedule = static function (string $prefix, string $title, array $rows) use ($dateValue): void {
    $idsField = $prefix === 'installment_'
        ? 'coaching_installment_ids[]'
        : 'hostel_installment_ids[]';
    ?>
    <div class="mcc-admission-installment-schedule-heading">
        <h3><?= esc($title) ?></h3>
        <span class="mcc-muted">Enter dates and amounts when available.</span>
    </div>
    <div class="mcc-table-wrap mcc-admission-installment-table-wrap">
        <table class="table mcc-table mcc-admission-installment-table">
            <thead>
                <tr>
                    <th scope="col">Year</th>
                    <th scope="col">Installment 1</th>
                    <th scope="col">Installment 2</th>
                    <th scope="col">Installment 3</th>
                    <th scope="col">Installment 4</th>
                    <th scope="col">Actions</th>
                </tr>
            </thead>
            <tbody data-admission-installment-rows>
    <?php
    foreach ($rows as $index => $row) {
        $persistedIds = json_encode($row['installment_ids'] ?? [], JSON_THROW_ON_ERROR);
        ?>
                <tr data-admission-installment-row data-admission-test-row-id="test-<?= esc($prefix) ?>row-<?= esc((string) $index) ?>" data-admission-installment-persisted-ids="<?= esc($persistedIds) ?>">
                    <td>
                        <label class="mcc-sr-only" for="test-<?= esc($prefix) ?>year-<?= $index ?>">Year</label>
                        <input type="hidden" name="<?= esc($idsField) ?>" value="<?= esc($persistedIds) ?>" data-admission-installment-ids>
                        <input class="form-control" id="test-<?= esc($prefix) ?>year-<?= $index ?>" name="<?= esc($prefix) ?>year[]" type="number" min="1" max="99" value="<?= esc((string) ($row['year_number'] ?? '')) ?>" required>
                    </td>
        <?php
        for ($number = 1; $number <= 4; $number++) {
            $date = (string) ($row['installment_' . $number . '_date'] ?? '');
            ?>
                    <td>
                        <div class="input-group mcc-admission-installment-input-group">
                            <input class="form-control" name="<?= esc($prefix . $number . '_date[]') ?>" type="text" inputmode="numeric" autocomplete="off" placeholder="DD-MM-YYYY" data-mcc-date-picker data-mcc-date-picker-no-toggle data-mcc-date-format="d-m-Y" data-mcc-date-submit-format="Y-m-d" value="<?= $dateValue($date) ?>">
                            <input class="form-control" name="<?= esc($prefix . $number . '[]') ?>" type="number" min="0" step="0.01" placeholder="0.00" value="<?= esc((string) ($row['installment_' . $number] ?? '')) ?>">
                        </div>
                    </td>
            <?php
        }
        ?>
                    <td>
                        <button class="mcc-button mcc-button-secondary" type="button" data-admission-installment-remove data-admission-test-remove-row="test-<?= esc($prefix) ?>row-<?= esc((string) $index) ?>">Remove</button>
                    </td>
                </tr>
        <?php
    }
    ?>
            </tbody>
        </table>
    </div>
    <?php
};
?>

<section class="mcc-admission-section mcc-admission-test-fees" data-admission-section="fees">
    <div class="mcc-card-heading">
        <div>
            <span class="mcc-eyebrow">Capture only</span>
            <h2 id="admission-test-fees-heading">Fees</h2>
        </div>
        <span class="mcc-muted">No payment is recorded here.</span>
    </div>
    <form method="post" action="<?= site_url('admission-tests/' . (int) $test['id'] . '/fees') ?>" id="admission-test-fees-form">
        <?= csrf_field() ?>
        <input type="hidden" name="version" value="<?= (int) ($test['version'] ?? 1) ?>">
        <div class="row">
            <div class="col-12 col-md-6 mcc-admission-fee-column">
                <div class="mcc-form-field">
                    <label for="admission-test-training-fee">Coaching Fee</label>
                    <div class="input-group mcc-admission-fee-input-group">
                        <div class="input-group-prepend mcc-admission-fee-plan-prepend">
                            <select class="form-control" id="admission-test-installment-plan" name="installment_plan" data-admission-installment-plan>
                                <option value="">Choose plan</option>
                                <option value="Full Payment" <?= $coachingPlan === 'Full Payment' ? 'selected' : '' ?>>Full Payment</option>
                                <option value="<?= esc($installmentPlanValue) ?>" <?= $coachingPlan === $installmentPlanValue ? 'selected' : '' ?>>Instalment Plan</option>
                            </select>
                        </div>
                        <input class="form-control" id="admission-test-training-fee" name="training_fee" type="number" min="0" step="0.01" placeholder="0.00" value="<?= $fieldValue('training_fee') ?>">
                    </div>
                    <p class="mcc-field-error"><?= $errorFor('training_fee') ?: $errorFor('installment_plan') ?></p>
                </div>
            </div>
            <div class="col-12 col-md-6 mcc-admission-fee-column">
                <div class="mcc-form-field">
                    <label for="admission-test-hostel-fee">Hostel Fee</label>
                    <div class="input-group mcc-admission-fee-input-group">
                        <div class="input-group-prepend mcc-admission-fee-plan-prepend">
                            <select class="form-control" id="admission-test-hostel-installment-plan" name="hostel_installment_plan" data-admission-installment-plan>
                                <option value="">Choose plan</option>
                                <option value="Full Payment" <?= $hostelPlan === 'Full Payment' ? 'selected' : '' ?>>Full Payment</option>
                                <option value="<?= esc($installmentPlanValue) ?>" <?= $hostelPlan === $installmentPlanValue ? 'selected' : '' ?>>Instalment Plan</option>
                            </select>
                        </div>
                        <input class="form-control" id="admission-test-hostel-fee" name="hostel_charges" type="number" min="0" step="0.01" placeholder="0.00" value="<?= $fieldValue('hostel_charges') ?>">
                    </div>
                    <p class="mcc-field-error"><?= $errorFor('hostel_charges') ?: $errorFor('hostel_installment_plan') ?></p>
                </div>
            </div>
            <div class="mcc-form-field col-12 mcc-admission-fee-total-field">
                <label for="admission-test-total-fee">Total Coaching + Hostel Fee</label>
                <div class="input-group mcc-admission-fee-input-group">
                    <div class="input-group-prepend"><span class="input-group-text">₹</span></div>
                    <input class="form-control" id="admission-test-total-fee" type="text" value="₹<?= esc((string) ($test['fee_total'] ?? '0.00')) ?>" readonly aria-readonly="true" data-admission-fee-total>
                </div>
            </div>
            <div class="col-12" data-admission-test-schedule="coaching">
                <div class="mcc-empty-state" data-admission-installment-empty <?= $coachingPlan === $installmentPlanValue ? 'hidden' : '' ?>>Installments: Not applicable</div>
                <div class="mcc-admission-installment-schedule" data-admission-installment-schedule data-admission-installment-prefix="installment_" data-admission-installment-plan-field="installment_plan" data-admission-installment-plan-value="<?= esc($installmentPlanValue) ?>" data-admission-installment-ids-field="coaching_installment_ids[]" data-admission-removed-field="removed_installment_ids" data-admission-test-saved-rows="<?= esc(json_encode($coachingSavedRows, JSON_THROW_ON_ERROR)) ?>" <?= $coachingPlan === $installmentPlanValue ? '' : 'hidden' ?>>
                    <?php $renderSchedule('installment_', 'Coaching Installment Schedule', $coachingRows); ?>
                    <div class="mcc-admission-installment-actions">
                        <button class="mcc-button mcc-button-secondary" type="button" data-admission-installment-add>Add More</button>
                    </div>
                </div>
            </div>
            <div class="col-12" data-admission-test-schedule="hostel">
                <div class="mcc-admission-installment-schedule" data-admission-installment-schedule data-admission-installment-prefix="hostel_installment_" data-admission-installment-plan-field="hostel_installment_plan" data-admission-installment-plan-value="<?= esc($installmentPlanValue) ?>" data-admission-installment-ids-field="hostel_installment_ids[]" data-admission-removed-field="removed_hostel_installment_ids" data-admission-test-saved-rows="<?= esc(json_encode($hostelSavedRows, JSON_THROW_ON_ERROR)) ?>" <?= $hostelPlan === $installmentPlanValue ? '' : 'hidden' ?>>
                    <?php $renderSchedule('hostel_installment_', 'Hostel Installment Schedule', $hostelRows); ?>
                    <div class="mcc-admission-installment-actions">
                        <button class="mcc-button mcc-button-secondary" type="button" data-admission-installment-add>Add More</button>
                    </div>
                </div>
            </div>
            <div class="col-12">
                <label for="admission-test-fee-reason">Reason for fee update</label>
                <textarea class="form-control" id="admission-test-fee-reason" name="fee_update_reason" rows="2" required><?= esc((string) old('fee_update_reason', '')) ?></textarea>
            </div>
            <div class="col-12 mt-3">
                <button class="mcc-button mcc-button-primary" type="submit">Save Fees</button>
            </div>
        </div>
    </form>
</section>
