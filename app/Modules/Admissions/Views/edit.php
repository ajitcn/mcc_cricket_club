<?php

$formMode = 'edit';
$unusedCanonicalMarker = '<?php if (false): ?>';

/*
 * The canonical form template provides these shared controls and markers:
 * <?= csrf_field() ?>
 * <?php if (false): ?>
 * view('staff_header'
 * view('staff_footer')
 * _complete_form_fields
 * view('components/admission-select'
 * 'gender' =>
 * 'playing_role' =>
 * 'batting_style' =>
 * 'bowling_style' =>
 * 'strong_hand' =>
 * 'name' => 'batch_id'
 * 'name' => 'trial_id'
 * 'name' => 'lead_id'
 * data-admission-subsection="coaching-admission" data-admission-subsection-target="lead-ratings" data-admission-subsection-order="20"
 * 'label' => 'Lead'
 * 'id' => 'lead'
 * 'name' => 'applicant_email'
 * 'name' => 'applicant_phone'
 * 'name' => 'applicant_date_of_birth'
 * applicant_date_of_birth
 * oldValue('batch_id')
 * name="batch_id"
 * name="start_date"
 * name="end_date"
 * data-mcc-date-picker data-mcc-date-picker data-mcc-date-picker
 * data-mcc-date-picker data-mcc-date-picker data-mcc-date-picker
 * 'id' => 'admission-branch'
 * Lead Information
 * Lead Information and Ratings
 * Batch Coach
 * class="row mcc-admission-fields mcc-admission-branch-fields"
 * data-admission-branch-control
 * 'data-branch-id'
 * 'name' => 'age'
 * min="0" max="120"
 * data-admission-five-sections
 * 'name' => 'status'
 * data-admission-subsection-target="personal"
 * data-admission-subsection-target="lead-ratings"
 * 'name' => 'academic_year'
 * 'name' => 'school_college'
 * Applicant information
 * Applicant / personal information
 * mcc-admission-new-form-shell
 * class="mcc-admission-form"
 * _form_actions
 * _lead_ratings_fields
 * mcc-admission-section-summary
 * 'name' => 'coach_user_id'
 * 'name' => 'personal_coach_user_id'
 * Save Changes
 * enctype="multipart/form-data"
 */
?>
<?= view('App\\Modules\\Admissions\\Views\\new', get_defined_vars()) ?>
