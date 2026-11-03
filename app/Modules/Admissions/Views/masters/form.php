<?php

$row = is_array($row ?? null) ? $row : [];
$type = (string) ($type ?? '');
$definition = is_array($definition ?? null) ? $definition : [];
$editing = isset($row['id']);
$programs = is_array($programs ?? null) ? $programs : [];
$batches = is_array($batches ?? null) ? $batches : [];
$branches = is_array($branches ?? null) ? $branches : [];
$fieldErrors = session()->getFlashdata('field_errors');
$fieldErrors = is_array($fieldErrors) ? $fieldErrors : [];
$fieldError = static function (string $name) use ($fieldErrors): string {
    return trim((string) ($fieldErrors[$name] ?? ''));
};
$value = static function (string $name, mixed $fallback = '') use ($row): string {
    return (string) old($name, $row[$name] ?? $fallback);
};
$dateValue = static function (string $name) use ($value): string {
    $date = $value($name);

    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $parts) === 1) {
        return $parts[3] . '-' . $parts[2] . '-' . $parts[1];
    }

    return $date;
};
?>
<?= view('staff_header', [
    'title' => ($editing ? 'Edit ' : 'Create ') . (string) ($definition['label'] ?? 'Admission master'),
    'message' => $message ?? null,
    'error' => $error ?? null,
]) ?>
<section class="mcc-card" aria-labelledby="admission-master-form-heading">
    <div class="mcc-card-heading">
        <span class="mcc-eyebrow">Admissions</span>
        <h2 id="admission-master-form-heading">
            <?= $editing ? 'Edit' : 'Create' ?> <?= esc((string) ($definition['label'] ?? 'Master')) ?>
        </h2>
    </div>
    <form
        class="row"
        method="post"
        action="<?= site_url('admission-masters/' . $type . ($editing ? '/' . (int) $row['id'] : '')) ?>"
    >
        <?= csrf_field() ?>

        <?php if ($type === 'age_group'): ?>
            <div class="col-12 col-md-4">
                <label for="master-age-group-name">Age group name</label>
                <input
                    class="form-control mb-2"
                    id="master-age-group-name"
                    name="name"
                    type="text"
                    required
                    value="<?= esc($value('name')) ?>"
                >
                <?php if ($fieldError('name') !== ''): ?>
                    <p class="mcc-field-error" role="alert"><?= esc($fieldError('name')) ?></p>
                <?php endif; ?>
            </div>
            <div class="col-12 col-md-4">
                <label for="master-age-group-display-order">Display order</label>
                <input
                    class="form-control mb-2"
                    id="master-age-group-display-order"
                    name="display_order"
                    type="number"
                    min="0"
                    step="1"
                    value="<?= esc($value('display_order', 0)) ?>"
                >
                <?php if ($fieldError('display_order') !== ''): ?>
                    <p class="mcc-field-error" role="alert"><?= esc($fieldError('display_order')) ?></p>
                <?php endif; ?>
            </div>
        <?php elseif ($type === 'academic'): ?>
            <div class="col-12 col-md-4">
                <label for="master-academic-year">Academic year</label>
                <input
                    class="form-control mb-2"
                    id="master-academic-year"
                    name="academic_year"
                    type="text"
                    required
                    value="<?= esc($value('academic_year')) ?>"
                >
                <?php if ($fieldError('academic_year') !== ''): ?>
                    <p class="mcc-field-error" role="alert"><?= esc($fieldError('academic_year')) ?></p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="col-12 col-md-4">
                <label for="master-plan-name">Plan name</label>
                <input
                    class="form-control mb-2"
                    id="master-plan-name"
                    name="plan_name"
                    type="text"
                    required
                    value="<?= esc($value('plan_name')) ?>"
                >
                <?php if ($fieldError('plan_name') !== ''): ?>
                    <p class="mcc-field-error" role="alert"><?= esc($fieldError('plan_name')) ?></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($type === 'academic' || $type === 'coaching'): ?>
            <div class="col-12 col-md-4">
                <label for="master-program-id">Program</label>
                <select class="form-control mb-2" id="master-program-id" name="program_id" data-bt-select data-bt-select-clear <?= $type === 'academic' ? 'required' : '' ?>>
                    <option value="">Not applicable</option>
                    <?php foreach ($programs as $program): ?>
                        <option value="<?= (int) ($program['id'] ?? 0) ?>" <?= $value('program_id') === (string) ($program['id'] ?? '') ? 'selected' : '' ?>>
                            <?= esc((string) ($program['name'] ?? '')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($fieldError('program_id') !== ''): ?>
                    <p class="mcc-field-error" role="alert"><?= esc($fieldError('program_id')) ?></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($type === 'academic'): ?>
            <div class="col-12 col-md-4">
                <label for="master-batch-id">Batch <span class="mcc-muted">(optional)</span></label>
                <select class="form-control mb-2" id="master-batch-id" name="batch_id" data-bt-select data-bt-select-clear>
                    <option value="">Not applicable</option>
                    <?php foreach ($batches as $batch): ?>
                        <option value="<?= (int) ($batch['id'] ?? 0) ?>" <?= $value('batch_id') === (string) ($batch['id'] ?? '') ? 'selected' : '' ?>>
                            <?= esc((string) ($batch['name'] ?? '')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($fieldError('batch_id') !== ''): ?>
                    <p class="mcc-field-error" role="alert"><?= esc($fieldError('batch_id')) ?></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($type === 'coaching'): ?>
            <div class="col-12 col-md-4">
                <label for="master-plan-type">Program / type</label>
                <input class="form-control mb-2" id="master-plan-type" name="plan_type" type="text" required value="<?= esc($value('plan_type')) ?>">
            </div>
            <div class="col-12 col-md-4">
                <label for="master-display-order">Display order</label>
                <input class="form-control mb-2" id="master-display-order" name="display_order" type="number" min="0" value="<?= esc($value('display_order', 0)) ?>">
            </div>
            <div class="col-12">
                <label for="master-description">Description <span class="mcc-muted">(optional)</span></label>
                <textarea class="form-control mb-2" id="master-description" name="description" rows="5"><?= esc($value('description')) ?></textarea>
            </div>
        <?php elseif ($type === 'fee'): ?>
            <div class="col-12 col-md-4">
                <label for="master-admission-fee">Admission fee</label>
                <input class="form-control mb-2" id="master-admission-fee" name="admission_fee" type="number" min="0" step="0.01" value="<?= esc($value('admission_fee')) ?>">
            </div>
            <div class="col-12 col-md-4">
                <label for="master-training-fee">Training fee</label>
                <input class="form-control mb-2" id="master-training-fee" name="training_fee" type="number" min="0" step="0.01" value="<?= esc($value('training_fee')) ?>">
            </div>
            <div class="col-12 col-md-4">
                <label for="master-installment-count">Installment count</label>
                <input class="form-control mb-2" id="master-installment-count" name="installment_count" type="number" min="0" value="<?= esc($value('installment_count')) ?>">
            </div>
            <div class="col-12 col-md-4">
                <label for="master-installment-frequency">Installment frequency</label>
                <input class="form-control mb-2" id="master-installment-frequency" name="installment_frequency" type="text" value="<?= esc($value('installment_frequency')) ?>">
            </div>
            <div class="col-12 col-md-8">
                <label for="master-due-date-rule">Due-date rule</label>
                <input class="form-control mb-2" id="master-due-date-rule" name="due_date_rule" type="text" value="<?= esc($value('due_date_rule')) ?>">
            </div>
        <?php elseif ($type === 'hostel'): ?>
            <div class="col-12 col-md-4">
                <label for="master-hostel-available">Hostel availability</label>
                <label class="mcc-checkbox-label mb-2" for="master-hostel-available">
                    <input type="hidden" name="hostel_available" value="0">
                    <input
                        id="master-hostel-available"
                        name="hostel_available"
                        type="checkbox"
                        value="1"
                        <?= (int) old('hostel_available', $row['hostel_available'] ?? 1) === 1 ? 'checked' : '' ?>
                    >
                    <span>Hostel available</span>
                </label>
            </div>
            <div class="col-12 col-md-4">
                <label for="master-monthly-charge">Monthly charge</label>
                <input class="form-control mb-2" id="master-monthly-charge" name="monthly_charge" type="number" min="0" step="0.01" value="<?= esc($value('monthly_charge')) ?>">
            </div>
            <div class="col-12 col-md-4">
                <label for="master-mess-charge">Mess charge</label>
                <input class="form-control mb-2" id="master-mess-charge" name="mess_charge" type="number" min="0" step="0.01" value="<?= esc($value('mess_charge')) ?>">
            </div>
            <div class="col-12 col-md-4">
                <label for="master-payment-frequency">Payment frequency</label>
                <input class="form-control mb-2" id="master-payment-frequency" name="payment_frequency" type="text" value="<?= esc($value('payment_frequency')) ?>">
            </div>
        <?php endif; ?>

        <?php if ($type === 'academic' || $type === 'fee' || $type === 'hostel'): ?>
            <?php $dateFields = $type === 'academic'
                ? ['start_date' => 'Start date', 'end_date' => 'End date']
                : ['effective_from' => 'Effective from', 'effective_to' => 'Effective to']; ?>
            <?php foreach ($dateFields as $name => $label): ?>
                <div class="col-12 col-md-4">
                    <label for="master-<?= esc($name) ?>"><?= esc($label) ?></label>
                    <input
                        class="form-control mb-2"
                        id="master-<?= esc($name) ?>"
                        name="<?= esc($name) ?>"
                        type="text"
                        inputmode="numeric"
                        placeholder="DD-MM-YYYY"
                        autocomplete="off"
                        data-mcc-date-picker
                        data-mcc-date-picker-no-toggle
                        data-mcc-date-format="d-m-Y"
                        data-mcc-date-submit-format="Y-m-d"
                        value="<?= esc($dateValue($name)) ?>"
                    >
                    <?php if ($fieldError($name) !== ''): ?>
                        <p class="mcc-field-error" role="alert"><?= esc($fieldError($name)) ?></p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if ($type !== 'age_group'): ?>
        <div class="col-12 col-md-4">
            <label for="master-branch-id">Branch</label>
            <select class="form-control mb-2" id="master-branch-id" name="branch_id" data-bt-select data-bt-select-clear>
                <option value="">Shared across authorized branches</option>
                <?php foreach ($branches as $branch): ?>
                    <option value="<?= (int) ($branch['id'] ?? 0) ?>" <?= $value('branch_id') === (string) ($branch['id'] ?? '') ? 'selected' : '' ?>>
                        <?= esc((string) ($branch['name'] ?? 'Authorized branch')) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if ($fieldError('branch_id') !== ''): ?>
                <p class="mcc-field-error" role="alert"><?= esc($fieldError('branch_id')) ?></p>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="col-12 col-md-4">
            <label for="master-active">Status</label>
            <label class="mcc-checkbox-label mb-2" for="master-active">
                <input type="hidden" name="is_active" value="0">
                <input id="master-active" name="is_active" type="checkbox" value="1" <?= (int) old('is_active', $row['is_active'] ?? 1) === 1 ? 'checked' : '' ?>>
                <span>Active</span>
            </label>
            <?php if ($type === 'age_group'): ?>
                <span class="mcc-badge <?= (int) old('is_active', $row['is_active'] ?? 1) === 1 ? 'mcc-badge-success' : 'mcc-badge-danger' ?>">
                    <?= (int) old('is_active', $row['is_active'] ?? 1) === 1 ? 'Active' : 'Inactive' ?>
                </span>
            <?php endif; ?>
        </div>

        <div class="col-12 mt-2 text-right">
            <button class="mcc-button mcc-button-primary" type="submit">
                <?= $editing ? 'Save changes' : ($type === 'age_group' ? 'Create age group' : 'Create plan') ?>
            </button>
            <a class="mcc-button mcc-button-secondary ml-2" href="<?= site_url('admission-masters/' . $type) ?>">Cancel</a>
        </div>
    </form>
</section>
<?= view('staff_footer') ?>
