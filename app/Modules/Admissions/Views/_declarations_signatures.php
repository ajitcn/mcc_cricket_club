<?php

$record = is_array($admission ?? null) ? $admission : [];
$includeDocumentRecord = (bool) ($includeDocumentRecord ?? true);
$includeTerms = (bool) ($includeTerms ?? false);
$sectionHeadingId = $includeDocumentRecord
    ? 'admission-record-heading'
    : 'admission-declarations-heading';
$valueFor = static function (string $field, string $fallback = '') use ($record): string {
    return esc((string) old($field, $record[$field] ?? $fallback));
};
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
$textareaField = static function (string $field, string $label, int $rows = 3, string $fieldClass = 'col-12') use ($valueFor, $errorFor): void {
    $id = 'admission-' . str_replace('_', '-', $field);
    $input = '<textarea class="form-control" id="' . esc($id) . '" name="' . esc($field)
        . '" rows="' . $rows . '">' . $valueFor($field) . '</textarea>';

    echo view('components/form-field', [
        'id' => $id,
        'label' => $label,
        'input' => $input,
        'error' => $errorFor($field),
        'field_class' => $fieldClass,
    ]);
};
$signatureField = static function (string $field, string $label) use ($valueFor, $errorFor): void {
    echo view('components/signature-field', [
        'id' => 'admission-' . str_replace('_', '-', $field),
        'field' => $field,
        'label' => $label,
        'value' => $valueFor($field),
        'error' => $errorFor($field),
    ]);
};
?>

<section class="mcc-admission-bottom-block" aria-labelledby="<?= $sectionHeadingId ?>">
    <?php if ($includeDocumentRecord): ?>
        <div class="mcc-admission-subgroup mcc-admission-documents-capture-section">
            <h3 id="admission-record-heading">Document Record</h3>
            <div class="mcc-admission-section-body">
                <div class="row mcc-admission-fields">
                    <?php $textareaField('documents_submitted', 'Documents submitted', 3); ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="mcc-admission-subgroup mcc-admission-signature-section" aria-labelledby="admission-declarations-heading">
        <h3 id="admission-declarations-heading">Declarations and Signatures</h3>
        <div class="mcc-admission-section-body">
            <?php if ($includeTerms): ?>
                <button
                    class="mcc-button mcc-button-secondary mcc-admission-terms-trigger"
                    type="button"
                    data-admission-terms-open
                    aria-controls="admission-terms-modal"
                >
                    Read Terms and Conditions
                </button>
            <?php endif; ?>
            <div class="row mcc-admission-fields">
                <?php
                $declarationChecked = (string) old('declaration_accepted', $record['declaration_accepted'] ?? '') === '1' ? ' checked' : '';
                $declarationInput = '<label class="mcc-checkbox-label" for="admission-declaration">'
                    . '<input id="admission-declaration" name="declaration_accepted" type="checkbox" value="1"'
                    . $declarationChecked . ' required>'
                    . '<span>I have read the rules and Regulations carefully and shall be binding on me.</span>'
                    . '</label>';
                echo view('components/form-field', [
                    'id' => 'admission-declaration',
                    'label' => 'Declaration',
                    'input' => $declarationInput,
                    'error' => $errorFor('declaration_accepted'),
                    'field_class' => 'col-12 mcc-admission-declaration-field',
                ]);
                ?>
                <?php $textareaField('confirmation_notes', 'Confirmation notes', 3); ?>
                <div class="col-12 col-md-6">
                    <?php $signatureField('student_signature', 'Student Signature'); ?>
                </div>
                <div class="col-12 col-md-6">
                    <?php $signatureField('parent_signature', 'Parent/Guardian Signature'); ?>
                </div>
                <?php $signatureField('authorized_signature', 'Club Authorized Person Signature'); ?>
            </div>
        </div>
    </div>
</section>
