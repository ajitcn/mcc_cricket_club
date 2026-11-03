<?php

$isEdit = ($formMode ?? 'new') === 'edit';
$formAdmission = is_array($admission ?? null) ? $admission : [];
$isCompletedEdit = $isEdit && in_array(
    strtoupper((string) ($formAdmission['status'] ?? '')),
    ['APPROVED', 'COMPLETED', 'CONVERTED'],
    true,
);

if ($isEdit) {
    $formAdmission['applicant_full_name'] = $formAdmission['applicant_full_name']
        ?? $formAdmission['lead_name']
        ?? '';
    $formAdmission['applicant_email'] = $formAdmission['applicant_email']
        ?? $formAdmission['lead_email']
        ?? '';
    $formAdmission['applicant_phone'] = $formAdmission['applicant_phone']
        ?? $formAdmission['lead_phone']
        ?? '';
    $formAdmission['applicant_date_of_birth'] = $formAdmission['applicant_date_of_birth']
        ?? $formAdmission['lead_date_of_birth']
        ?? '';

    $currentPeriod = is_array($formAdmission['current_period'] ?? null)
        ? $formAdmission['current_period']
        : [];
    $formAdmission['start_date'] = $formAdmission['start_date']
        ?? $currentPeriod['start_date']
        ?? $formAdmission['joining_date']
        ?? '';
    $formAdmission['end_date'] = $formAdmission['end_date']
        ?? $currentPeriod['end_date']
        ?? $formAdmission['year1_end_date']
        ?? '';
}

$old = static function (string $field, string $fallback = '') use ($formAdmission): string {
    $stored = $formAdmission[$field] ?? $fallback;
    $submitted = old($field, null);

    return esc((string) (($submitted === null || $submitted === '') ? $stored : $submitted));
};

$fieldError = static function (string $field): string {
    $message = function_exists('validation_show_error')
        ? trim((string) validation_show_error($field))
        : '';
    if ($message !== '') {
        return $message;
    }

    $errors = session()->getFlashdata('field_errors');
    return is_array($errors) ? trim((string) ($errors[$field] ?? '')) : '';
};

$admissionOptions = [
    'gender' => [
        'Male',
        'Female',
        'Other',
        'Prefer Not to Say',
    ],
    'playing_role' => [
        'Batter',
        'Bowler',
        'All-Rounder',
        'Wicketkeeper-Batter',
    ],
    'batting_style' => [
        'Right-Handed',
        'Left-Handed',
    ],
    'bowling_style' => [
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
    ],
    'strong_hand' => [
        'Right',
        'Left',
        'Ambidextrous',
    ],
];

$pageActions = '<div class="mcc-page-actions">
    <a class="mcc-button mcc-button-secondary" href="' . site_url('admissions') . '">
        Back to admissions
    </a>
</div>';
?>
<?= view('staff_header', [
    'title' => $isEdit ? 'Edit Admission' : 'New Admission',
    'nav' => $nav,
    'message' => $message,
    'error' => $error,
    'pageActions' => $pageActions,
    'suppressGlobalAlerts' => true,
]) ?>

<section class="mcc-admission-form-shell mcc-admission-new-form-shell" aria-labelledby="<?= $isEdit ? 'edit-admission-form-heading' : 'new-admission-form-heading' ?>">
    <div class="mcc-card-heading mcc-admission-new-heading">
        <div>
            <span class="mcc-eyebrow"><?= $isEdit ? 'Admissions' : 'Applicant intake' ?></span>
            <h2 id="<?= $isEdit ? 'edit-admission-form-heading' : 'new-admission-form-heading' ?>"><?= $isEdit ? 'Edit admission #' . (int) ($formAdmission['id'] ?? 0) : 'Application details' ?></h2>
            <p class="mcc-admission-new-intro">
                <?= $isCompletedEdit
                    ? 'This completed or converted Admission permits coach-assignment corrections only.'
                    : ($isEdit ? 'Update the admission intake details below.' : 'Enter applicant details and any available supporting information.') ?>
            </p>
        </div>
    </div>

    <form
        method="post"
        action="<?= site_url($isEdit ? 'admissions/' . (int) ($formAdmission['id'] ?? 0) . '/edit' : 'admissions') ?>"
        class="mcc-admission-form"
        data-admission-five-sections
        data-admission-submit-feedback
        enctype="multipart/form-data"
    >
        <?= csrf_field() ?>
        <?php if ($isCompletedEdit): ?>
            <input
                type="hidden"
                name="expected_version"
                value="<?= (int) ($formAdmission['renewal_version'] ?? 0) ?>"
            >
        <?php endif; ?>

        <?php if ($isCompletedEdit): ?>
            <fieldset disabled aria-disabled="true">
                <?= view('App\\Modules\\Admissions\\Views\\_form_sections', [
                    'admission' => $formAdmission,
                    'includeTerms' => false,
                ]) ?>
            </fieldset>
        <?php else: ?>
            <?= view('App\\Modules\\Admissions\\Views\\_form_sections', [
                'admission' => $formAdmission,
                'includeTerms' => ! $isEdit,
            ]) ?>
        <?php endif; ?>

        <details class="mcc-admission-subsection" data-admission-subsection="coaching-branch" data-admission-subsection-target="coaching" data-admission-subsection-order="5" open>
            <summary class="mcc-admission-section-summary"><span>Batch Coach</span></summary>
            <div class="mcc-admission-section-body">
                <fieldset class="mcc-admission-section">
            <legend>Batch Coach</legend>
            <div class="row mcc-admission-fields mcc-admission-branch-fields">
                <?php
                $branchOptions = array_map(static fn (array $branch): array => [
                    'value' => (string) $branch['id'],
                    'label' => $branch['name'],
                ], $branches ?? []);
                $batchOptions = array_map(static fn (array $batch): array => [
                    'value' => (string) $batch['id'],
                    'label' => $batch['name'],
                ], $batches ?? []);
                $coachOptions = array_map(static fn (array $coach): array => [
                    'value' => (string) ($coach['id'] ?? 0),
                    'label' => (string) ($coach['display_name'] ?? 'Coach'),
                    'attributes' => ['data-branch-id' => (string) ($coach['branch_id'] ?? 0)],
                ], $coaches ?? []);
                ?>
                <div class="mcc-form-field col-12 col-md-4">
                    <label for="branch">Branch</label>
                    <?= view('components/admission-select', ['id' => 'branch', 'name' => 'branch_id', 'label' => 'Branch', 'selected' => $old('branch_id'), 'options' => $branchOptions, 'multiple' => false, 'attributes' => ['data-admission-branch-control' => true, 'required' => true, 'disabled' => $isCompletedEdit]]) ?>
                    <?php if ($fieldError('branch_id') !== ''): ?>
                        <p class="mcc-field-error" role="alert"><?= esc($fieldError('branch_id')) ?></p>
                    <?php endif; ?>
                </div>
                <div class="mcc-form-field col-12 col-md-4">
                    <label for="batch">Batch</label>
                    <?= view('components/admission-select', ['id' => 'batch', 'name' => 'batch_id', 'label' => 'Batch', 'selected' => $old('batch_id'), 'options' => $batchOptions, 'multiple' => false, 'attributes' => ['disabled' => $isCompletedEdit]]) ?>
                    <?php if ($fieldError('batch_id') !== ''): ?>
                        <p class="mcc-field-error" role="alert"><?= esc($fieldError('batch_id')) ?></p>
                    <?php endif; ?>
                </div>
                <div class="mcc-form-field col-12 col-md-4">
                    <label for="admission-batch-coach">Batch Coach</label>
                    <?= view('components/admission-select', ['id' => 'admission-batch-coach', 'name' => 'coach_user_id', 'label' => 'Batch Coach', 'selected' => $old('coach_user_id'), 'multiple' => false, 'forceBtSelect' => true, 'options' => $coachOptions, 'attributes' => []]) ?>
                    <?php if ($fieldError('coach_user_id') !== ''): ?>
                        <p class="mcc-field-error" role="alert"><?= esc($fieldError('coach_user_id')) ?></p>
                    <?php endif; ?>
                </div>
                <div class="mcc-form-field col-12 col-md-4">
                    <label for="admission-personal-coach">Personal Coach</label>
                    <?= view('components/admission-select', ['id' => 'admission-personal-coach', 'name' => 'personal_coach_user_id', 'label' => 'Personal Coach', 'selected' => $old('personal_coach_user_id'), 'multiple' => false, 'forceBtSelect' => true, 'options' => $coachOptions, 'attributes' => []]) ?>
                    <?php if ($fieldError('personal_coach_user_id') !== ''): ?>
                        <p class="mcc-field-error" role="alert"><?= esc($fieldError('personal_coach_user_id')) ?></p>
                    <?php endif; ?>
                </div>
                <?php if ($isCompletedEdit): ?>
                    <div class="mcc-form-field col-12">
                        <label for="completed-coach-correction-reason">Correction reason</label>
                        <textarea
                            class="form-control"
                            id="completed-coach-correction-reason"
                            name="correction_reason"
                            maxlength="1000"
                            rows="3"
                            placeholder="Explain why the completed or converted Admission coach assignment is being corrected."
                        ><?= $old('correction_reason') ?></textarea>
                        <?php if ($fieldError('correction_reason') !== ''): ?>
                            <p class="mcc-field-error" role="alert"><?= esc($fieldError('correction_reason')) ?></p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <div class="mcc-form-field col-12 col-md-4">
                    <label for="admission-start-date">Admission Start Date</label>
                    <input
                        class="form-control"
                        id="admission-start-date"
                        name="start_date"
                        type="text"
                        inputmode="numeric"
                        autocomplete="off"
                        placeholder="DD-MM-YYYY"
                        data-mcc-date-picker
                        data-mcc-date-picker-no-toggle
                        data-mcc-date-format="d-m-Y"
                        data-mcc-date-submit-format="Y-m-d"
                        value="<?= esc(mcc_user_date_input($old('start_date'))) ?>"
                        <?= $isCompletedEdit ? 'disabled' : '' ?>
                    >
                    <?php if ($fieldError('start_date') !== ''): ?>
                        <p class="mcc-field-error" role="alert"><?= esc($fieldError('start_date')) ?></p>
                    <?php endif; ?>
                </div>
                <div class="mcc-form-field col-12 col-md-4">
                    <label for="admission-end-date">Admission End Date</label>
                    <input
                        class="form-control"
                        id="admission-end-date"
                        name="end_date"
                        type="text"
                        inputmode="numeric"
                        autocomplete="off"
                        placeholder="DD-MM-YYYY"
                        data-mcc-date-picker
                        data-mcc-date-picker-no-toggle
                        data-mcc-date-format="d-m-Y"
                        data-mcc-date-submit-format="Y-m-d"
                        value="<?= esc(mcc_user_date_input($old('end_date'))) ?>"
                        <?= $isCompletedEdit ? 'disabled' : '' ?>
                    >
                    <?php if ($fieldError('end_date') !== ''): ?>
                        <p class="mcc-field-error" role="alert"><?= esc($fieldError('end_date')) ?></p>
                    <?php endif; ?>
                </div>
                <?php
                $statusInput = view('components/admission-select', [
                    'id' => 'admission-status',
                    'name' => 'status',
                    'label' => 'Status',
                    'selected' => $old('status'),
                    'options' => is_array($statusOptions ?? null) ? $statusOptions : [],
                    'multiple' => false,
                    'forceNativeSelect' => true,
                    'attributes' => ['disabled' => $isCompletedEdit],
                ]);
                ?>
                <?= view('components/form-field', [
                    'id' => 'admission-status',
                    'label' => 'Status',
                    'input' => $statusInput,
                    'error' => $fieldError('status'),
                    'field_help' => null,
                    'field_class' => 'col-12 col-md-4',
                ]) ?>
            </div>
                </fieldset>
            </div>
        </details>

        <details class="mcc-admission-subsection" data-admission-subsection="personal-student" data-admission-subsection-target="personal" data-admission-subsection-order="10" open>
            <summary class="mcc-admission-section-summary"><span>Student Information</span></summary>
            <div class="mcc-admission-section-body">
                <fieldset class="mcc-admission-section">
            <legend>Applicant information</legend>
            <div class="row mcc-admission-fields">
                <?php
                $applicantFields = [
                    'applicant_full_name' => [
                        'id' => 'applicant-full-name',
                        'label' => 'Full name',
                        'required' => true,
                    ],
                    'father_name' => [
                        'id' => 'father-name',
                        'label' => 'Father name',
                    ],
                    'applicant_date_of_birth' => [
                        'id' => 'applicant-date-of-birth',
                        'label' => 'Date of birth',
                    ],
                    'age' => [
                        'id' => 'age',
                        'label' => 'Age',
                        'type' => 'number',
                    ],
                    'age_group_id' => [
                        'id' => 'age-group',
                        'label' => 'Age group',
                    ],
                    'applicant_email' => [
                        'id' => 'applicant-email',
                        'label' => 'Student email',
                        'type' => 'email',
                    ],
                    'applicant_phone' => [
                        'id' => 'applicant-phone',
                        'label' => 'Student phone',
                        'type' => 'tel',
                    ],
                    'gender' => [
                        'id' => 'gender',
                        'label' => 'Gender',
                    ],
                    'academic_year' => [
                        'id' => 'academic-year',
                        'label' => 'Academic year',
                    ],
                    'school_college' => [
                        'id' => 'school-college',
                        'label' => 'School / college',
                    ],
                    'blood_group' => [
                        'id' => 'blood-group',
                        'label' => 'Blood group',
                    ],
                ];
                ?>
                <?php foreach ($applicantFields as $field => $definition): ?>
                    <?php if ($field === 'gender'): ?>
                        <?php
                        $input = view('components/admission-select', [
                            'id' => $definition['id'],
                            'name' => 'gender',
                            'label' => $definition['label'],
                            'selected' => $old($field),
                            'multiple' => false,
                            'attributes' => [],
                            'options' => array_map(static fn (string $option): array => [
                                'value' => $option,
                                'label' => $option,
                            ], $admissionOptions['gender']),
                        ]);
                        ?>
                    <?php elseif ($field === 'applicant_date_of_birth'): ?>
                        <?php
                        $input = '<input class="form-control" id="' . $definition['id'] . '" name="' . $field . '"';
                        $input .= ' type="text" inputmode="numeric" autocomplete="off"';
                        $input .= ' data-mcc-date-picker data-mcc-date-picker-no-toggle';
                        $input .= ' data-mcc-date-format="d-m-Y" data-mcc-date-submit-format="Y-m-d"';
                        $input .= ' value="' . esc(mcc_user_date_input((string) old($field))) . '">';
                        ?>
                    <?php elseif ($field === 'age'): ?>
                        <?php
                        $input = '<input class="form-control" id="age" name="age" type="number" min="0" max="120" step="1" inputmode="numeric"';
                        $input .= ' value="' . $old('age') . '">';
                        ?>
                    <?php elseif ($field === 'age_group_id'): ?>
                        <?php
                        $ageGroupOptions = [];
                        foreach (($admissionMasters['age_group'] ?? []) as $option) {
                            $optionId = (string) (int) ($option['id'] ?? 0);
                            $optionLabel = (string) ($option['name'] ?? 'Age group');
                            $ageGroupOptions[] = ['value' => $optionId, 'label' => $optionLabel];
                        }
                        $input = view('components/admission-select', [
                            'id' => 'age-group',
                            'name' => 'age_group_id',
                            'label' => $definition['label'],
                            'selected' => $old('age_group_id'),
                            'multiple' => false,
                            'attributes' => [],
                            'options' => $ageGroupOptions,
                        ]);
                        ?>
                    <?php else: ?>
                        <?php
                        $input = '<input class="form-control" id="' . $definition['id'] . '" name="' . $field . '"';
                        $input .= ' value="' . $old($field) . '"';
                        if (! empty($definition['type'])) {
                            $input .= ' type="' . esc($definition['type']) . '"';
                        }
                        if (! empty($definition['required'])) {
                            $input .= ' required';
                        }
                        $input .= '>';
                        ?>
                    <?php endif; ?>
                    <?= view('components/form-field', [
                        'id' => $definition['id'],
                        'label' => $definition['label'],
                        'input' => $input,
                        'required' => ! empty($definition['required']),
                        'error' => $fieldError($field),
                    ]) ?>
                <?php endforeach; ?>
                <?= view('components/form-field', [
                    'id' => 'medical-conditions',
                    'label' => 'Medical conditions',
                    'input' => '<textarea class="form-control" id="medical-conditions" name="medical_conditions" maxlength="255" rows="3">'
                        . $old('medical_conditions') . '</textarea>',
                    'error' => $fieldError('medical_conditions'),
                    'field_class' => 'col-12',
                ]) ?>
            </div>
                </fieldset>
            </div>
        </details>

        <details class="mcc-admission-subsection" data-admission-subsection="coaching-admission" data-admission-subsection-target="lead-ratings" data-admission-subsection-order="20" open>
            <summary class="mcc-admission-section-summary"><span>Lead Information and Ratings</span></summary>
            <div class="mcc-admission-section-body">
                <fieldset class="mcc-admission-section">
            <legend>Lead Information and Ratings</legend>
            <div class="row mcc-admission-fields">
                <div class="mcc-form-field col-6">
                    <label for="lead">Lead</label>
                    <?php
                    $leadOptions = array_map(static fn (array $lead): array => [
                        'value' => (string) $lead['id'],
                        'label' => $lead['full_name'] . ' — ' . $lead['branch_name'],
                        'attributes' => [
                            'data-search-text' => implode(' ', array_filter([
                                '#' . (string) $lead['id'],
                                (string) ($lead['full_name'] ?? ''),
                                (string) ($lead['email'] ?? ''),
                                (string) ($lead['phone'] ?? ''),
                                (string) ($lead['branch_name'] ?? ''),
                            ])),
                        ],
                    ], $leads ?? []);
                    ?>
                    <?= view('components/admission-select', ['id' => 'lead', 'name' => 'lead_id', 'label' => 'Lead', 'selected' => $old('lead_id'), 'options' => $leadOptions, 'multiple' => false, 'attributes' => ['data-admission-lead-control' => true]]) ?>
                    <?php if ($fieldError('lead_id') !== ''): ?>
                        <p class="mcc-field-error" role="alert"><?= esc($fieldError('lead_id')) ?></p>
                    <?php endif; ?>
                </div>

                <div class="mcc-form-field col-6">
                    <label for="trial">Trial <span class="mcc-muted">(optional)</span></label>
                    <?php
                    $trialOptions = array_map(static fn (array $trial): array => [
                        'value' => (string) $trial['id'],
                        'attributes' => [
                            'data-lead-id' => (string) ($trial['lead_id'] ?? ''),
                            'data-rating-overall' => (string) ($trial['ability_overall'] ?? ''),
                            'data-rating-batting' => (string) ($trial['ability_batting'] ?? ''),
                            'data-rating-bowling' => (string) ($trial['ability_bowling'] ?? ''),
                            'data-rating-wicketkeeping' => (string) ($trial['ability_wicketkeeping'] ?? ''),
                            'data-rating-fitness' => (string) ($trial['ability_fitness'] ?? ''),
                        ],
                        'label' => '#' . (int) $trial['id'] . ' — ' . mcc_user_date($trial['trial_date'] ?? null, 'Not scheduled') . ' ' . ($trial['trial_time'] ?? '') . ' ' . ($trial['lead_name'] ?? 'Trial'),
                    ], $trials ?? []);
                    ?>
                    <?= view('components/admission-select', ['id' => 'trial', 'name' => 'trial_id', 'label' => 'Trial', 'selected' => $old('trial_id'), 'options' => $trialOptions, 'multiple' => false, 'attributes' => ['data-admission-trial-control' => true, 'data-admission-trial-ratings-url' => site_url('crm/trials/__TRIAL__/ratings-for-admission')]]) ?>
                    <?php if ($fieldError('trial_id') !== ''): ?>
                        <p class="mcc-field-error" role="alert"><?= esc($fieldError('trial_id')) ?></p>
                    <?php endif; ?>
                </div>

                <?= view('App\\Modules\\Admissions\\Views\\_lead_ratings_fields', ['admission' => $formAdmission, 'trials' => $trials ?? []]) ?>
            </div>
                </fieldset>
            </div>
        </details>

        <?= view('App\\Modules\\Admissions\\Views\\_complete_form_fields', [
            'coaches' => $coaches ?? [],
            'admission' => $formAdmission,
        ]) ?>

        <details class="mcc-admission-subsection mcc-admission-guardian-section" data-admission-subsection="personal-parent" data-admission-subsection-target="personal" data-admission-subsection-order="30" open>
            <summary class="mcc-admission-section-summary"><span>Parent/Guardian Information</span></summary>
            <div class="mcc-admission-section-body">
                <fieldset class="mcc-admission-section">
            <legend>Parent / guardian details</legend>
            <div class="row mcc-admission-fields">
                <?php
                $guardianFields = [
                    'mother_name' => 'Mother name',
                    'parent_mobile' => 'Parent mobile',
                    'parent_email' => 'Parent email',
                    'emergency_contact' => 'Emergency contact',
                ];
                ?>
                <?php foreach ($guardianFields as $field => $label): ?>
                    <?= view('components/form-field', [
                        'id' => $field,
                        'label' => $label,
                        'input' => '<input class="form-control" id="' . $field . '" name="' . $field . '" value="'
                            . $old($field) . '"' . ($field === 'parent_email' ? ' type="email"' : '') . '>',
                        'error' => $fieldError($field),
                    ]) ?>
                <?php endforeach; ?>
            </div>
                </fieldset>
            </div>
        </details>

        <details
            class="mcc-admission-section mcc-admission-print-terms"
            data-admission-terms-section
            aria-labelledby="admission-print-terms-heading"
        >
            <summary class="mcc-admission-section-summary" id="admission-print-terms-heading">
                <span>Terms and Conditions</span>
            </summary>
            <div class="mcc-admission-section-body">
                <?= view('App\\Modules\\Admissions\\Views\\_terms_and_conditions', ['idPrefix' => 'print-terms-']) ?>
            </div>
        </details>

        <?php if (! empty($message)): ?>
            <p class="mcc-alert mcc-alert-success mcc-admission-response" data-admission-response role="status">
                <?= esc($message) ?>
            </p>
        <?php elseif (! empty($error)): ?>
            <p class="mcc-alert mcc-alert-danger mcc-admission-response" data-admission-response role="alert">
                <?= esc($error) ?>
            </p>
        <?php else: ?>
            <p class="mcc-alert mcc-admission-response" data-admission-response hidden role="status"></p>
        <?php endif; ?>
        <?= view('App\\Modules\\Admissions\\Views\\_form_actions', [
            'cancelUrl' => site_url($isEdit ? 'admissions/' . (int) ($formAdmission['id'] ?? 0) . '/page' : 'admissions'),
            'viewUrl' => $isEdit ? site_url('admissions/' . (int) ($formAdmission['id'] ?? 0) . '/page') : null,
            'submitLabel' => $isCompletedEdit
                ? 'Save Coach Assignments'
                : ($isEdit ? 'Save Changes' : 'Save Admission'),
        ]) ?>
    </form>
</section>

<?php if (! $isEdit): ?>
    <div
        class="mcc-modal mcc-admission-terms-modal"
        id="admission-terms-modal"
        data-admission-terms-modal
        aria-hidden="true"
        hidden
        role="dialog"
        aria-modal="true"
        aria-labelledby="admission-terms-modal-title"
    >
        <div class="mcc-modal-dialog mcc-admission-terms-dialog">
            <div class="mcc-modal-heading">
                <div>
                    <span class="mcc-eyebrow">Admission information</span>
                    <h2 id="admission-terms-modal-title">Terms and Conditions</h2>
                </div>
                <button class="mcc-modal-close" type="button" data-admission-terms-close aria-label="Close Terms and Conditions">&times;</button>
            </div>
            <div class="mcc-admission-terms-modal-body">
                <?= view('App\\Modules\\Admissions\\Views\\_terms_and_conditions', ['idPrefix' => 'modal-terms-']) ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<?= view('staff_footer') ?>
