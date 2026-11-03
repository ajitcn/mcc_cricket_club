<?php

$record = is_array($admission ?? null) ? $admission : [];
$errorFor = static function (string $field): string {
    if (function_exists('validation_show_error')) {
        $message = trim((string) validation_show_error($field));
        if ($message !== '') {
            return $message;
        }
    }

    $errors = session()->getFlashdata('field_errors');

    return is_array($errors) ? trim((string) ($errors[$field] ?? '')) : '';
};
$valueFor = static function (string $field) use ($record): string {
    return esc((string) old($field, $record[$field] ?? ''));
};
$coachingFee = (string) old('training_fee', $record['training_fee'] ?? '');
$hostelFee = (string) old('hostel_charges', $record['hostel_charges'] ?? '');
$feeTotal = service('admission')->calculateFeeTotal([
    'training_fee' => $coachingFee,
    'hostel_charges' => $hostelFee,
]);
$taxableFee = (string) old(
    'total_taxable_fee',
    $record['total_taxable_fee'] ?? $feeTotal,
);
if ($taxableFee === '') {
    $taxableFee = $feeTotal;
}
$taxRate = (string) old('tax_rate', $record['tax_rate'] ?? '');
$storedTaxableFee = (string) ($record['total_taxable_fee'] ?? '');
$taxableFeeManual = (string) old(
    'taxable_fee_manual',
    $storedTaxableFee !== '' && $storedTaxableFee !== $feeTotal ? '1' : '0',
);
$feePlanGroup = static function (
    string $amountField,
    string $amountLabel,
    string $planField,
    string $planLabel,
) use ($valueFor, $errorFor): void {
    $amountId = 'admission-' . str_replace('_', '-', $amountField);
    $planId = 'admission-' . str_replace('_', '-', $planField);
    $planInput = view('components/admission-select', [
        'id' => $planId,
        'name' => $planField,
        'label' => $planLabel,
        'selected' => $valueFor($planField),
        'multiple' => false,
        'attributes' => ['data-admission-installment-plan' => true],
        'options' => [
            ['value' => 'Full Payment', 'label' => 'Full Payment'],
            ['value' => 'Instalment Plan', 'label' => 'Instalment Plan'],
        ],
    ]);
    $amountInput = '<input class="form-control" id="' . esc($amountId) . '" name="'
        . esc($amountField) . '" type="number" min="0" step="0.01" inputmode="decimal"'
        . ' placeholder="0.00" value="' . $valueFor($amountField) . '">';
    $error = $errorFor($amountField) ?: $errorFor($planField);
    $input = '<div class="input-group mcc-admission-fee-input-group">'
        . '<div class="input-group-prepend mcc-admission-fee-plan-prepend">'
        . '<label class="mcc-sr-only" for="' . esc($planId) . '">' . esc($planLabel) . '</label>'
        . $planInput
        . '</div>'
        . $amountInput
        . '</div>';

    echo view('components/form-field', [
        'id' => $amountId,
        'label' => $amountLabel,
        'input' => $input,
        'error' => $error,
        'field_class' => 'col-12',
    ]);
};
$feeField = static function (string $field, string $label) use ($valueFor, $errorFor): void {
    $id = 'admission-' . str_replace('_', '-', $field);
    $input = '<div class="input-group mcc-admission-fee-input-group">'
        . '<div class="input-group-prepend"><span class="input-group-text">₹</span></div>'
        . '<input class="form-control" id="' . esc($id) . '" name="' . esc($field)
        . '" type="number" min="0" step="0.01" inputmode="decimal" placeholder="0.00" value="'
        . $valueFor($field) . '"></div>';

    echo view('components/form-field', [
        'id' => $id,
        'label' => $label,
        'input' => $input,
        'error' => $errorFor($field),
        'field_class' => 'col-12',
    ]);
};
?>

<div class="col-12 col-md-6 mcc-admission-fee-column">
    <?php $feePlanGroup('training_fee', 'Coaching Fee', 'installment_plan', 'Payment Plan'); ?>
</div>
<div class="col-12 col-md-6 mcc-admission-fee-column">
    <?php $feePlanGroup('hostel_charges', 'Hostel Fee', 'hostel_installment_plan', 'Hostel Payment Plan'); ?>
</div>
<div class="mcc-form-field col-12 mcc-admission-fee-total-field">
    <label for="admission-total-coaching-hostel-fee">Total Coaching + Hostel Fee</label>
    <div class="input-group mcc-admission-fee-input-group">
        <div class="input-group-prepend"><span class="input-group-text">₹</span></div>
        <input
            class="form-control"
            id="admission-total-coaching-hostel-fee"
            type="text"
            value="₹<?= esc($feeTotal) ?>"
            readonly
            aria-readonly="true"
            data-admission-fee-total
        >
    </div>
</div>
<div class="col-12 col-md-4 mcc-admission-fee-column mcc-admission-fee-tax-column">
    <div class="mcc-form-field">
        <label for="admission-total-taxable-fee">Taxable Amount</label>
        <div class="input-group mcc-admission-fee-input-group">
            <div class="input-group-prepend"><span class="input-group-text">&#8377;</span></div>
            <input
                class="form-control"
                id="admission-total-taxable-fee"
                name="total_taxable_fee"
                type="number"
                min="0"
                step="0.01"
                inputmode="decimal"
                placeholder="0.00"
                value="<?= esc($taxableFee) ?>"
                data-admission-taxable-fee
            >
        </div>
        <input
            type="hidden"
            name="taxable_fee_manual"
            value="<?= esc($taxableFeeManual) ?>"
            data-admission-taxable-fee-manual
        >
        <p class="mcc-field-error mcc-admission-fee-error-slot" role="alert"><?= esc($errorFor('total_taxable_fee')) ?></p>
    </div>
</div>
<div class="col-12 col-md-4 mcc-admission-fee-column mcc-admission-fee-tax-column">
    <div class="mcc-form-field">
        <label for="admission-tax-rate">Tax Rate</label>
        <div class="input-group mcc-admission-fee-input-group">
            <input
                class="form-control"
                id="admission-tax-rate"
                name="tax_rate"
                type="number"
                min="0"
                max="100"
                step="0.01"
                inputmode="decimal"
                placeholder="Enter tax rate"
                value="<?= esc($taxRate) ?>"
                data-admission-tax-rate
            >
            <div class="input-group-append"><span class="input-group-text">%</span></div>
        </div>
        <p class="mcc-field-error mcc-admission-fee-error-slot" role="alert"><?= esc($errorFor('tax_rate')) ?></p>
    </div>
</div>
<div class="col-12 col-md-4 mcc-admission-fee-column mcc-admission-fee-tax-column">
    <div class="mcc-form-field">
        <label for="admission-calculated-tax-amount">Calculated Tax Amount</label>
        <div class="input-group mcc-admission-fee-input-group">
            <div class="input-group-prepend"><span class="input-group-text">&#8377;</span></div>
            <input
                class="form-control"
                id="admission-calculated-tax-amount"
                type="text"
                value="&#8377;<?= esc((string) ($record['calculated_tax_amount'] ?? '0.00')) ?>"
                readonly
                aria-readonly="true"
                data-admission-calculated-tax
            >
        </div>
        <p class="mcc-field-error mcc-admission-fee-error-slot" aria-hidden="true"></p>
    </div>
</div>
