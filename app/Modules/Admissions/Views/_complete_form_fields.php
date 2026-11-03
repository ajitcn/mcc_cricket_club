<?php

$record = is_array($admission ?? null) ? $admission : [];
$rawValueFor = static function (string $field, mixed $fallback = '') use ($record): mixed {
    return old($field, $record[$field] ?? $fallback);
};
$valueFor = static function (string $field, string $fallback = '') use ($record): string {
    $default = $record[$field] ?? $fallback;

    if ($field === 'current_address' && trim((string) $default) === '') {
        $default = $record['correspondence_address'] ?? $fallback;
    }

    return esc((string) old($field, $default));
};
$multiValues = static function (string $field) use ($rawValueFor, $record): array {
    $value = $rawValueFor($field);

    if ($field === 'coaching_type' && ($value === null || $value === '')) {
        $legacyTypes = [];
        if (($record['personal_coaching_days'] ?? null) !== null
            || ($record['personal_coaching_duration'] ?? null) !== null) {
            $legacyTypes[] = 'Personal coaching';
        }
        if (($record['group_coaching_days'] ?? null) !== null) {
            $legacyTypes[] = 'Group coaching';
        }
        if (($record['elite_camp'] ?? null) === 'Yes') {
            $legacyTypes[] = 'Elite';
        }

        if ($legacyTypes !== []) {
            return $legacyTypes;
        }
    }

    if ($field === 'gym_fitness_session' && ($value === null || $value === '')
        && ($record['fitness_plan'] ?? null) === 'Strengthening/Fitness session at GYM (Personal session)') {
        return ['Fitness session at GYM (Personal session)'];
    }

    if (is_array($value)) {
        return array_values(array_unique(array_map('strval', $value)));
    }

    if (! is_scalar($value) || trim((string) $value) === '') {
        return [];
    }

    $decoded = json_decode((string) $value, true);
    if (is_array($decoded)) {
        return array_values(array_unique(array_map('strval', $decoded)));
    }

    $legacy = trim((string) $value);
    $legacyMap = [
        'Group Strengthening/Fitness on Ground' => ['Group Strengthening', 'Fitness on Ground'],
        '1 whites' => ['1 Whites'],
        '2 Whites' => ['2 Whites'],
        '3 whites' => ['3 Whites'],
    ];

    return $legacyMap[$legacy] ?? [$legacy];
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
$dateValue = static function (string $field) use ($valueFor): string {
    $value = $valueFor($field);

    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1
        ? date('d-m-Y', strtotime($value))
        : $value;
};
$textField = static function (
    string $field,
    string $label,
    string $type = 'text',
    array $attributes = [],
    string $fieldClass = '',
) use ($valueFor, $errorFor): void {
    $id = 'admission-' . str_replace('_', '-', $field);
    $attributeMarkup = '';
    foreach ($attributes as $attribute => $attributeValue) {
        if ($attributeValue === null || $attributeValue === false) {
            continue;
        }

        $attributeMarkup .= ' ' . esc((string) $attribute);
        if ($attributeValue !== true) {
            $attributeMarkup .= '="' . esc((string) $attributeValue) . '"';
        }
    }
    $input = '<input class="form-control" id="' . esc($id) . '" name="' . esc($field) . '" type="'
        . esc($type) . '"' . $attributeMarkup . ' value="' . $valueFor($field) . '">';

    if ($field === 'age') {
        $input = '<input class="form-control" id="' . esc($id) . '" name="' . esc($field)
            . '" type="number" min="0" max="120" step="1" inputmode="numeric" value="'
            . $valueFor($field) . '"' . $attributeMarkup . '>';
    }

    if (in_array($field, ['estimated_personal_session', 'estimated_elite_session'], true)) {
        $input = '<input class="form-control" id="' . esc($id) . '" name="' . esc($field)
            . '" type="number" min="0" max="9999" step="1" inputmode="numeric"'
            . $attributeMarkup . ' placeholder="Number of sessions" value="' . $valueFor($field) . '">';
    }

    echo view('components/form-field', [
        'id' => $id,
        'label' => $label,
        'input' => $input,
        'error' => $errorFor($field),
        'field_class' => $fieldClass,
    ]);
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
$selectField = static function (
    string $field,
    string $label,
    array $options,
    array $attributes = [],
    bool $hidden = false,
    string $fieldClass = '',
) use ($valueFor, $errorFor): void {
    $id = 'admission-' . str_replace('_', '-', $field);
    $selectedValue = $valueFor($field);
    $legacyValueMap = [
        'Indoors + Outdoors' => 'Indoor+Outdoor',
        'Induction after the admission' => 'Yes',
        'High Performance Elite Camp' => 'Yes',
        'Student Diary' => 'Yes',
    ];
    $selectedValue = $legacyValueMap[$selectedValue] ?? $selectedValue;
    $input = view('components/admission-select', [
        'id' => $id,
        'name' => $field,
        'label' => $label,
        'selected' => $selectedValue,
        'multiple' => false,
        'attributes' => $attributes,
        'options' => array_map(static fn (string $option): array => [
            'value' => $option,
            'label' => $option,
        ], $options),
    ]);
    echo view('components/form-field', [
        'id' => $id,
        'label' => $label,
        'input' => $input,
        'error' => $errorFor($field),
        'hidden' => $hidden,
        'field_class' => $fieldClass,
    ]);
};
$multiSelectField = static function (string $field, string $label, array $options) use ($multiValues, $errorFor): void {
    $id = 'admission-' . str_replace('_', '-', $field);
    $selectedValues = $multiValues($field);
    $input = view('components/admission-select', [
        'id' => $id,
        'name' => $field,
        'label' => $label,
        'selected' => $selectedValues,
        'options' => array_map(static fn (string $option): array => [
            'value' => $option,
            'label' => $option,
        ], $options),
        'multiple' => true,
        'attributes' => [],
    ]);
    echo view('components/form-field', [
        'id' => $id,
        'label' => $label,
        'input' => $input,
        'error' => $errorFor($field),
    ]);
};
$checkboxGroup = static function (
    string $field,
    string $label,
    array $options,
    bool $multiple = true,
    array $attributes = [],
    string $additionalContent = '',
) use ($multiValues, $errorFor): void {
    $id = 'admission-' . str_replace('_', '-', $field);
    $selectedValues = $multiValues($field);
    $inputName = $multiple ? $field . '[]' : $field;
    $items = [];

    foreach ($options as $index => $option) {
        $optionId = $id . '-' . $index;
        $items[] = '<div class="form-check">'
            . '<input class="form-check-input"'
            . ' id="' . esc($optionId) . '"'
            . ' name="' . esc($inputName) . '"'
            . ' type="checkbox"'
            . ' value="' . esc($option) . '"'
            . (in_array($option, $selectedValues, true) ? ' checked' : '')
            . '>'
            . '<label class="form-check-label" for="' . esc($optionId) . '">'
            . esc($option)
            . '</label>'
            . '</div>';
    }

    $attributeMarkup = '';
    foreach ($attributes as $attribute => $value) {
        if ($value === null || $value === false) {
            continue;
        }

        $attributeMarkup .= ' ' . esc((string) $attribute);
        if ($value !== true) {
            $attributeMarkup .= '="' . esc((string) $value) . '"';
        }
    }

    echo '<fieldset class="mcc-form-field col-12 mb-2"'
        . ' id="' . esc($id) . '"'
        . $attributeMarkup
        . '>'
        . '<legend class="form-label">' . esc($label) . '</legend>'
        . '<div class="mcc-checkbox-options">'
        . implode('', $items)
        . '</div>'
        . $additionalContent
        . ($errorFor($field) !== ''
            ? '<p class="mcc-field-error" role="alert">' . esc($errorFor($field)) . '</p>'
            : '')
        . '</fieldset>';
};
$radioGroup = static function (
    string $field,
    string $label,
    array $options,
) use ($multiValues, $errorFor): void {
    $id = 'admission-' . str_replace('_', '-', $field);
    $selectedValues = $multiValues($field);
    $items = [];

    foreach ($options as $index => $option) {
        $optionId = $id . '-' . $index;
        $items[] = '<div class="form-check">'
            . '<input class="form-check-input"'
            . ' id="' . esc($optionId) . '"'
            . ' name="' . esc($field) . '"'
            . ' type="radio"'
            . ' value="' . esc($option) . '"'
            . (in_array($option, $selectedValues, true) ? ' checked' : '')
            . '>'
            . '<label class="form-check-label" for="' . esc($optionId) . '">'
            . esc($option)
            . '</label>'
            . '</div>';
    }

    echo '<fieldset class="mcc-form-field col-12 mb-2" id="' . esc($id) . '">'
        . '<legend class="form-label">' . esc($label) . '</legend>'
        . '<div class="mcc-checkbox-options">'
        . implode('', $items)
        . '</div>'
        . ($errorFor($field) !== ''
            ? '<p class="mcc-field-error" role="alert">' . esc($errorFor($field)) . '</p>'
            : '')
        . '</fieldset>';
};
$masterField = static function (string $field, string $label, array $options) use ($valueFor, $errorFor): void {
    $id = 'admission-' . str_replace('_', '-', $field);
    $selectOptions = [];
    foreach ($options as $option) {
        $optionId = (string) (int) ($option['id'] ?? 0);
        $optionLabel = (string) ($option['plan_name'] ?? $option['academic_year'] ?? 'Admission plan');
        $selectOptions[] = ['value' => $optionId, 'label' => $optionLabel];
    }
    $input = view('components/admission-select', [
        'id' => $id,
        'name' => $field,
        'label' => $label,
        'selected' => $valueFor($field),
        'options' => $selectOptions,
        'multiple' => false,
        'attributes' => [],
    ]);
    echo view('components/form-field', [
        'id' => $id,
        'label' => $label,
        'input' => $input,
        'error' => $errorFor($field),
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
$section = (string) ($section ?? 'all');
$showSection = static function (string $name) use ($section): bool {
    return $section === 'all' || $section === $name;
};
?>

<?php if ($showSection('cricket')): ?>
<div class="mcc-admission-subgroup mcc-admission-academic-section" data-admission-subsection="cricket-information" data-admission-subsection-target="cricket" data-admission-subsection-order="10">
    <h3>Cricket Information</h3>
    <div class="mcc-admission-section-body">
    <div class="mcc-admission-textarea-block" data-admission-field-group="cricket-textareas">
        <div class="row mcc-admission-fields">
        <?php $textareaField('previous_coaching_centre', 'Previous coaching centre', 3, 'col-6'); ?>
        <?php $textareaField('playing_experience', 'Playing experience', 3, 'col-6'); ?>
        <?php $textareaField('hobbies', 'Hobbies', 3, 'col-6'); ?>
        <?php $textareaField('achievements', 'Achievements', 3, 'col-6'); ?>
        </div>
    </div>
    <div class="mcc-admission-cricket-controls" data-admission-field-group="cricket-controls">
        <div class="row mcc-admission-fields">
        <?php $selectField('playing_role', 'Playing role', ['Batter', 'Bowler', 'All-Rounder', 'Wicketkeeper-Batter']); ?>
        <?php $selectField('batting_style', 'Batting style', ['Right-Handed', 'Left-Handed']); ?>
        <?php $selectField('bowling_style', 'Bowling style', [
            'Right-Arm Fast',
            'Right-Arm Fast-Medium',
            'Right-Arm Medium-Fast',
            'Right-Arm Medium',
            'Right-Arm Medium-Slow',
            'Right-Arm Off-Break',
            'Right-Arm Leg-Break',
            'Right-Arm Leg-Break Googly',
            'Left-Arm Fast',
            'Left-Arm Fast-Medium',
            'Left-Arm Medium-Fast',
            'Left-Arm Medium',
            'Left-Arm Medium-Slow',
            'Left-Arm Orthodox',
            'Left-Arm Chinaman',
            'Does Not Bowl',
        ]); ?>
        <?php $selectField('strong_hand', 'Strong hand', ['Right', 'Left', 'Ambidextrous']); ?>
        <?php $textField('jersey_size', 'Jersey size'); ?>
        <?php
        $selectField(
            'batting_order',
            'Batting order',
            ['Opening', '2nd', '3rd', 'Middle'],
            []
        );
        ?>
        </div>
    </div>
</div>
</div>
<?php endif; ?>

<?php if ($showSection('personal')): ?>
<div class="mcc-admission-subgroup mcc-admission-address-section" data-admission-subsection="personal-address" data-admission-subsection-target="personal" data-admission-subsection-order="30">
    <h3>Address</h3>
    <div class="mcc-admission-section-body">
    <div class="row mcc-admission-fields">
        <?php $textareaField('current_address', 'Current / correspondence address', 3, 'col-6'); ?>
        <?php $textareaField('permanent_address', 'Permanent address', 3, 'col-6'); ?>
    </div>
</div>
</div>
<?php endif; ?>

<?php if ($showSection('coaching')): ?>
<div class="mcc-admission-subgroup mcc-admission-coaching-section" data-admission-subsection="coaching-training-options" data-admission-subsection-target="coaching" data-admission-subsection-order="20">
    <h3>Training Selection and Coaching Options</h3>
    <div class="mcc-admission-section-body">
    <div class="row mcc-admission-fields mcc-admission-training-fields">
        <?php
        ob_start();
        ?>
        <div class="mcc-coaching-dependent-fields" data-admission-coaching-dependent-fields hidden>
            <?php $selectField('personal_coaching_days', 'Personal Coaching Days', ['1 Day', '2 Days', '3 Days', '4 Days', '5 Days'], ['data-admission-coaching-dependent' => 'Personal coaching'], false, 'col-lg-4 col-md-6 col-12'); ?>
            <?php $selectField('personal_coaching_duration', 'Personal Coaching Duration', ['1 hour', '1.5 hours', '2 hours'], ['data-admission-coaching-dependent' => 'Personal coaching'], false, 'col-lg-4 col-md-6 col-12'); ?>
            <?php $textField('estimated_personal_session', 'Estimated Personal Session', 'number', ['data-admission-coaching-dependent' => 'Personal coaching'], 'col-lg-4 col-md-6 col-12'); ?>
            <?php $selectField('group_coaching_days', 'Group Coaching Days', ['2 Days', '3 Days', '4 Days', '6 Days'], ['data-admission-coaching-dependent' => 'Group coaching'], false, 'col-lg-4 col-md-6 col-12'); ?>
            <?php $selectField('elite_camp_days', 'Elite Camp Days', ['1 Day', '2 Days', '3 Days', '4 Days', '5 Days'], ['data-admission-coaching-dependent' => 'Elite'], false, 'col-lg-4 col-md-6 col-12'); ?>
            <?php $selectField('elite_camp_duration', 'Elite Camp Duration', ['1 hour', '1.5 hours', '2 hours'], ['data-admission-coaching-dependent' => 'Elite'], false, 'col-lg-4 col-md-6 col-12'); ?>
            <?php $textField('estimated_elite_session', 'Estimated Elite Session', 'number', ['data-admission-coaching-dependent' => 'Elite'], 'col-lg-4 col-md-6 col-12'); ?>
        </div>
        <?php
        $coachingTypeFields = ob_get_clean();
        $checkboxGroup(
            'coaching_type',
            'Coaching type',
            ['Personal coaching', 'Group coaching', 'Elite'],
            true,
            ['data-admission-coaching-type-control' => true],
            $coachingTypeFields,
        );
        ?>
        <?php $radioGroup('coaching_delivery', 'Indoor / Outdoor Coaching', ['Indoor', 'Outdoor', 'Indoor+Outdoor']); ?>
        <?php $checkboxGroup('fitness_plan', 'Group Strengthening / Fitness on Ground', ['Group Strengthening', 'Fitness on Ground']); ?>
        <?php $checkboxGroup('gym_fitness_session', 'Strengthening / Fitness Session at GYM (Personal Session)', ['Strengthening', 'Fitness session at GYM (Personal session)']); ?>
        <?php $checkboxGroup('match_program', 'Match Simulation / Practice Match / Tournaments', ['Match Simulation', 'Practice Match', 'Tournaments (as per availability and performance)']); ?>
        <?php $radioGroup('induction_plan', 'Induction after the admission', ['Yes', 'No']); ?>
        <?php $radioGroup('review_frequency', 'Head Coach review frequency', ['3 months', '6 months']); ?>
        <?php $radioGroup('progress_report_frequency', 'Progress report frequency', ['3 months', '6 months']); ?>
        <?php $radioGroup('feedback_call_frequency', 'Feedback call frequency', ['3 months', '6 months']); ?>
        <?php $checkboxGroup('meditation_sessions', 'Meditation sessions', ['Weekly once'], false); ?>
        <?php $radioGroup('student_diary', 'Student Diary', ['Yes', 'No']); ?>
        <?php $checkboxGroup('welcome_kit', 'Welcome Kit', ['1 Whites', '2 Whites', '3 Whites', '1 Colored', '2 Colored', '1 Cap', '1 Kit Bag']); ?>
    </div>
</div>
</div>
<?php endif; ?>

<?php if ($showSection('coaching-installments')): ?>
    <?php
    $installmentPlan = (string) old('installment_plan', $record['installment_plan'] ?? '');
    $storedInstallmentRows = is_array($record['installment_rows'] ?? null) ? $record['installment_rows'] : [];
    $postedYears = old('installment_year');
    $postedColumns = [];
    foreach (range(1, 4) as $number) {
        $postedColumns[$number . '_date'] = old('installment_' . $number . '_date');
        $postedColumns[$number] = old('installment_' . $number);
    }
    $installmentRows = [];
    if (is_array($postedYears)) {
        foreach ($postedYears as $index => $year) {
            $row = ['year_number' => (string) $year];
            foreach (range(1, 4) as $number) {
                $dateColumn = $postedColumns[$number . '_date'];
                $row['installment_' . $number . '_date'] = is_array($dateColumn)
                    ? (string) ($dateColumn[$index] ?? '')
                    : '';
                $column = $postedColumns[$number];
                $row['installment_' . $number] = is_array($column) ? (string) ($column[$index] ?? '') : '';
            }
            $installmentRows[] = $row;
        }
    } elseif ($storedInstallmentRows !== []) {
        $installmentRows = $storedInstallmentRows;
    } else {
        $installmentRows = [
            ['year_number' => '1', 'installment_1_date' => '', 'installment_1' => '', 'installment_2_date' => '', 'installment_2' => '', 'installment_3_date' => '', 'installment_3' => '', 'installment_4_date' => '', 'installment_4' => ''],
            ['year_number' => '2', 'installment_1_date' => '', 'installment_1' => '', 'installment_2_date' => '', 'installment_2' => '', 'installment_3_date' => '', 'installment_3' => '', 'installment_4_date' => '', 'installment_4' => ''],
        ];
    }
    $showInstallmentSchedule = $installmentPlan === 'Instalment Plan';
    $legacyInstallmentDueDates = (string) old(
        'installment_due_dates',
        $record['installment_due_dates'] ?? '',
    );
    if ($showInstallmentSchedule) {
        $legacyInstallmentDueDates = json_encode(
            $installmentRows,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        ) ?: $legacyInstallmentDueDates;
    }
    ?>
    <div class="mcc-empty-state mcc-admission-installment-empty" data-admission-installment-section="coaching-installments" data-admission-installment-empty<?= $showInstallmentSchedule ? ' hidden' : '' ?>>
        Not applicable &mdash; Coaching fee is not on an instalment plan.
    </div>
    <div class="mcc-admission-installment-schedule" data-admission-installment-section="coaching-installments" data-admission-installment-schedule data-admission-installment-prefix="installment_"<?= $showInstallmentSchedule ? '' : ' hidden' ?>>
        <input type="hidden" name="installment_due_dates" value="<?= esc($legacyInstallmentDueDates) ?>">
        <div class="table-responsive">
            <table class="mcc-table mcc-admission-installment-table">
                <caption class="mcc-sr-only">Yearly instalment schedule</caption>
                <thead>
                    <tr>
                        <th scope="col">Year</th>
                        <th scope="col">1st Instalment</th>
                        <th scope="col">2nd Instalment</th>
                        <th scope="col">3rd Instalment</th>
                        <th scope="col">4th Instalment</th>
                        <th scope="col"><span class="mcc-sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody data-admission-installment-rows>
                    <?php foreach ($installmentRows as $row): ?>
                        <tr data-admission-installment-row>
                            <td data-label="Year">
                                <label class="mcc-sr-only" for="admission-installment-year-<?= esc((string) ($row['year_number'] ?? '')) ?>">Year</label>
                                <input class="form-control" id="admission-installment-year-<?= esc((string) ($row['year_number'] ?? '')) ?>" name="installment_year[]" type="number" min="1" max="99" step="1" value="<?= esc((string) ($row['year_number'] ?? '')) ?>" required>
                            </td>
                            <?php foreach (range(1, 4) as $number): ?>
                                <td data-label="<?= $number ?><?= $number === 1 ? 'st' : ($number === 2 ? 'nd' : ($number === 3 ? 'rd' : 'th')) ?> Instalment">
                                    <div class="input-group mcc-admission-installment-input-group">
                                        <span class="input-group-prepend"><span class="input-group-text">Date</span></span>
                                        <label class="mcc-sr-only" for="admission-installment-<?= $number ?>-date-<?= esc((string) ($row['year_number'] ?? '')) ?>">Date</label>
                                        <input class="form-control" id="admission-installment-<?= $number ?>-date-<?= esc((string) ($row['year_number'] ?? '')) ?>" name="installment_<?= $number ?>_date[]" type="text" inputmode="numeric" autocomplete="off" placeholder="DD-MM-YYYY" data-mcc-date-picker data-mcc-date-picker-no-toggle data-mcc-date-format="d-m-Y" data-mcc-date-submit-format="Y-m-d" value="<?= esc(mcc_user_date_input((string) ($row['installment_' . $number . '_date'] ?? ''))) ?>">
                                    </div>
                                    <div class="input-group mcc-admission-installment-input-group">
                                        <span class="input-group-prepend"><span class="input-group-text">Amount</span></span>
                                        <label class="mcc-sr-only" for="admission-installment-<?= $number ?>-amount-<?= esc((string) ($row['year_number'] ?? '')) ?>">Amount</label>
                                        <input class="form-control" id="admission-installment-<?= $number ?>-amount-<?= esc((string) ($row['year_number'] ?? '')) ?>" name="installment_<?= $number ?>[]" type="number" min="0" step="0.01" inputmode="decimal" placeholder="0.00" value="<?= esc((string) ($row['installment_' . $number] ?? '')) ?>">
                                    </div>
                                </td>
                            <?php endforeach; ?>
                            <td data-label="Actions">
                                <button class="mcc-button mcc-button-secondary" type="button" data-admission-installment-remove>Remove</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <button class="mcc-button mcc-button-secondary" type="button" data-admission-installment-add>Add More</button>
        <?php if ($errorFor('installment_rows') !== ''): ?>
            <p class="mcc-field-error" role="alert"><?= esc($errorFor('installment_rows')) ?></p>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php if ($showSection('hostel-installments')): ?>
    <?php
    $hostelInstallmentPlan = (string) old('hostel_installment_plan', $record['hostel_installment_plan'] ?? '');
    $storedHostelInstallmentRows = is_array($record['hostel_installment_rows'] ?? null)
        ? $record['hostel_installment_rows']
        : [];
    $hostelPostedYears = old('hostel_installment_year');
    $hostelPostedColumns = [];
    foreach (range(1, 4) as $number) {
        $hostelPostedColumns[$number . '_date'] = old('hostel_installment_' . $number . '_date');
        $hostelPostedColumns[$number] = old('hostel_installment_' . $number);
    }
    $hostelInstallmentRows = [];
    if (is_array($hostelPostedYears)) {
        foreach ($hostelPostedYears as $index => $year) {
            $row = ['year_number' => (string) $year];
            foreach (range(1, 4) as $number) {
                $dateColumn = $hostelPostedColumns[$number . '_date'];
                $row['installment_' . $number . '_date'] = is_array($dateColumn)
                    ? (string) ($dateColumn[$index] ?? '')
                    : '';
                $column = $hostelPostedColumns[$number];
                $row['installment_' . $number] = is_array($column)
                    ? (string) ($column[$index] ?? '')
                    : '';
            }
            $hostelInstallmentRows[] = $row;
        }
    } elseif ($storedHostelInstallmentRows !== []) {
        $hostelInstallmentRows = $storedHostelInstallmentRows;
    } else {
        $hostelInstallmentRows = [
            ['year_number' => '1', 'installment_1_date' => '', 'installment_1' => '', 'installment_2_date' => '', 'installment_2' => '', 'installment_3_date' => '', 'installment_3' => '', 'installment_4_date' => '', 'installment_4' => ''],
        ];
    }
    $showHostelInstallmentSchedule = $hostelInstallmentPlan === 'Instalment Plan';
    ?>
    <div class="mcc-empty-state mcc-admission-installment-empty" data-admission-installment-section="hostel-installments" data-admission-installment-empty<?= $showHostelInstallmentSchedule ? ' hidden' : '' ?>>
        Not applicable &mdash; Hostel fee is not on an instalment plan.
    </div>
    <div class="mcc-admission-installment-schedule" data-admission-installment-section="hostel-installments" data-admission-installment-schedule data-admission-installment-prefix="hostel_installment_"<?= $showHostelInstallmentSchedule ? '' : ' hidden' ?>>
        <div class="table-responsive">
            <table class="mcc-table mcc-admission-installment-table">
                <caption class="mcc-sr-only">Yearly hostel instalment schedule</caption>
                <thead>
                    <tr>
                        <th scope="col">Year</th>
                        <th scope="col">1st Instalment</th>
                        <th scope="col">2nd Instalment</th>
                        <th scope="col">3rd Instalment</th>
                        <th scope="col">4th Instalment</th>
                        <th scope="col"><span class="mcc-sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody data-admission-installment-rows>
                    <?php foreach ($hostelInstallmentRows as $row): ?>
                        <tr data-admission-installment-row>
                            <td data-label="Year">
                                <label class="mcc-sr-only" for="admission-hostel-installment-year-<?= esc((string) ($row['year_number'] ?? '')) ?>">Year</label>
                                <input class="form-control" id="admission-hostel-installment-year-<?= esc((string) ($row['year_number'] ?? '')) ?>" name="hostel_installment_year[]" type="number" min="1" max="99" step="1" value="<?= esc((string) ($row['year_number'] ?? '')) ?>" required>
                            </td>
                            <?php foreach (range(1, 4) as $number): ?>
                                <td data-label="<?= $number ?><?= $number === 1 ? 'st' : ($number === 2 ? 'nd' : ($number === 3 ? 'rd' : 'th')) ?> Instalment">
                                    <div class="input-group mcc-admission-installment-input-group">
                                        <span class="input-group-prepend"><span class="input-group-text">Date</span></span>
                                        <label class="mcc-sr-only" for="admission-hostel-installment-<?= $number ?>-date-<?= esc((string) ($row['year_number'] ?? '')) ?>">Date</label>
                                        <input class="form-control" id="admission-hostel-installment-<?= $number ?>-date-<?= esc((string) ($row['year_number'] ?? '')) ?>" name="hostel_installment_<?= $number ?>_date[]" type="text" inputmode="numeric" autocomplete="off" placeholder="DD-MM-YYYY" data-mcc-date-picker data-mcc-date-picker-no-toggle data-mcc-date-format="d-m-Y" data-mcc-date-submit-format="Y-m-d" value="<?= esc(mcc_user_date_input((string) ($row['installment_' . $number . '_date'] ?? ''))) ?>">
                                    </div>
                                    <div class="input-group mcc-admission-installment-input-group">
                                        <span class="input-group-prepend"><span class="input-group-text">Amount</span></span>
                                        <label class="mcc-sr-only" for="admission-hostel-installment-<?= $number ?>-amount-<?= esc((string) ($row['year_number'] ?? '')) ?>">Amount</label>
                                        <input class="form-control" id="admission-hostel-installment-<?= $number ?>-amount-<?= esc((string) ($row['year_number'] ?? '')) ?>" name="hostel_installment_<?= $number ?>[]" type="number" min="0" step="0.01" inputmode="decimal" placeholder="0.00" value="<?= esc((string) ($row['installment_' . $number] ?? '')) ?>">
                                    </div>
                                </td>
                            <?php endforeach; ?>
                            <td data-label="Actions">
                                <button class="mcc-button mcc-button-secondary" type="button" data-admission-installment-remove>Remove</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <button class="mcc-button mcc-button-secondary" type="button" data-admission-installment-add>Add More Year</button>
        <?php if ($errorFor('hostel_installment_rows') !== ''): ?>
            <p class="mcc-field-error" role="alert"><?= esc($errorFor('hostel_installment_rows')) ?></p>
        <?php endif; ?>
    </div>
<?php endif; ?>

