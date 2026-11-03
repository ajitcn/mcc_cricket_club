<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AdmissionCompleteFormContractTest extends TestCase
{
    public function testCompletePdfFieldGroupsUseSharedControlsAndExistingWorkflow(): void
    {
        $root = dirname(__DIR__, 2);
        $new = (string) file_get_contents($root . '/app/Modules/Admissions/Views/new.php');
        $edit = (string) file_get_contents($root . '/app/Modules/Admissions/Views/edit.php');
        $partial = (string) file_get_contents($root . '/app/Modules/Admissions/Views/_complete_form_fields.php');
        $actions = (string) file_get_contents($root . '/app/Modules/Admissions/Views/_form_actions.php');
        $sections = (string) file_get_contents($root . '/app/Modules/Admissions/Views/_form_sections.php');
        $fees = (string) file_get_contents($root . '/app/Modules/Admissions/Views/_fees_fields.php');
        $bottom = (string) file_get_contents($root . '/app/Modules/Admissions/Views/_declarations_signatures.php');
        $signatureComponent = (string) file_get_contents($root . '/app/Views/components/signature-field.php');
        $styles = (string) file_get_contents($root . '/public/assets/css/app.css');
        $service = (string) file_get_contents($root . '/app/Services/AdmissionService.php');
        $script = (string) file_get_contents($root . '/public/assets/js/app.js');
        $selectComponent = (string) file_get_contents($root . '/app/Views/components/admission-select.php');
        $formField = (string) file_get_contents($root . '/app/Views/components/form-field.php');
        $installmentMigration = (string) file_get_contents($root . '/app/Database/Migrations/20261014000200_CreateSeparateAdmissionInstallmentTables.php');
        $installmentModel = (string) file_get_contents($root . '/app/Modules/Admissions/Models/AdmissionInstallmentModel.php');
        $hostelInstallmentModel = (string) file_get_contents($root . '/app/Modules/Admissions/Models/AdmissionHostelInstallmentModel.php');

        foreach ([
            'academic_year', 'school_college',
            'current_address', 'permanent_address', 'correspondence_address',
            'previous_coaching_centre', 'playing_experience', 'hobbies', 'achievements',
            'ability_overall', 'ability_batting', 'ability_bowling', 'ability_wicketkeeping',
            'ability_fitness', 'batting_order', 'coach_user_id',
            'training_fee', 'installment_plan',
            'installment_due_dates', 'hostel_charges',
            'documents_submitted', 'declaration_accepted',
            'student_signature', 'parent_signature', 'authorized_signature',
            'personal_coaching_days', 'personal_coaching_duration', 'group_coaching_days', 'coaching_delivery',
            'gym_fitness_session',
            'fitness_plan', 'match_program', 'induction_plan', 'review_frequency', 'progress_report_frequency',
            'feedback_call_frequency', 'meditation_sessions', 'elite_camp', 'student_diary', 'welcome_kit',
        ] as $field) {
            $bottomFields = [
                'documents_submitted',
                'declaration_accepted',
                'student_signature',
                'parent_signature',
                'authorized_signature',
            ];
            self::assertStringContainsString(
                $field,
                in_array($field, $bottomFields, true) ? $bottom : $new . $edit . $partial . $fees
            );
            self::assertStringContainsString("'" . $field . "'", $service);
        }

        self::assertStringContainsString('_complete_form_fields', $new);
        self::assertStringContainsString('_complete_form_fields', $edit);
        self::assertStringContainsString('name="start_date"', $new);
        self::assertStringContainsString('name="end_date"', $new);
        self::assertStringContainsString('name="start_date"', $edit);
        self::assertStringContainsString('name="end_date"', $edit);
        self::assertStringContainsString('admission_date', $service);
        self::assertStringContainsString('data-mcc-date-picker', $new);
        self::assertStringContainsString('data-mcc-date-picker', $edit);
        self::assertStringContainsString("view('components/admission-select'", $partial);
        self::assertStringContainsString('str_ends_with($name, \'[]\')', $selectComponent);
        self::assertStringContainsString("'multiple' => true", $partial);
        self::assertStringContainsString('Indoor+Outdoor', $partial);
        self::assertStringContainsString('Tournaments (as per availability and performance)', $partial);
        self::assertStringContainsString("['Yes', 'No']", $partial);
        self::assertStringContainsString("'batting_order',", $partial);
        self::assertStringNotContainsString("'Choose order'", $partial);
        self::assertStringNotContainsString(
            'Branch ownership cannot be changed from the admission form.',
            $new . $edit
        );
        foreach ([
            'previous_coaching_centre',
            'playing_experience',
            'hobbies',
            'achievements',
            'current_address',
            'permanent_address',
        ] as $field) {
            self::assertStringContainsString("'$field'", $partial);
        }
        self::assertStringContainsString("'previous_coaching_centre', 'Previous coaching centre', 3, 'col-6'", $partial);
        self::assertStringContainsString("'playing_experience', 'Playing experience', 3, 'col-6'", $partial);
        self::assertStringContainsString("'hobbies', 'Hobbies', 3, 'col-6'", $partial);
        self::assertStringContainsString("'achievements', 'Achievements', 3, 'col-6'", $partial);
        self::assertStringContainsString("'current_address', 'Current / correspondence address', 3, 'col-6'", $partial);
        self::assertStringContainsString("'permanent_address', 'Permanent address', 3, 'col-6'", $partial);
        self::assertStringContainsString('$feePlanGroup(\'training_fee\', \'Coaching Fee\', \'installment_plan\', \'Payment Plan\');', $fees);
        self::assertStringContainsString('$feePlanGroup(\'hostel_charges\', \'Hostel Fee\', \'hostel_installment_plan\', \'Hostel Payment Plan\');', $fees);
        self::assertSame(2, substr_count($fees, 'class="col-12 col-md-6 mcc-admission-fee-column"'));
        self::assertStringNotContainsString('admission_fee', $fees);
        self::assertStringNotContainsString('Admission Fee', $fees);
        self::assertStringContainsString('class="input-group mcc-admission-fee-input-group"', $fees);
        self::assertStringContainsString('class="input-group-prepend mcc-admission-fee-plan-prepend"', $fees);
        self::assertStringContainsString('class="input-group-text">₹</span>', $fees);
        self::assertStringContainsString('data-admission-installment-plan', $fees);
        self::assertStringNotContainsString("'placeholder' => 'Choose plan'", $fees);
        self::assertStringNotContainsString("'placeholder' => 'Choose option'", $fees);
        self::assertStringNotContainsString("'name' => 'installment_plan'", $partial);
        self::assertStringNotContainsString("'name' => 'hostel_installment_plan'", $partial);
        self::assertStringContainsString("['value' => 'Full Payment', 'label' => 'Full Payment']", $fees);
        self::assertStringContainsString("['value' => 'Instalment Plan', 'label' => 'Instalment Plan']", $fees);
        self::assertStringContainsString('data-admission-installment-schedule', $partial);
        self::assertSame(4, substr_count($partial, 'data-admission-installment-section='));
        self::assertStringContainsString('data-admission-installment-section="coaching-installments"', $partial);
        self::assertStringContainsString('data-admission-installment-section="hostel-installments"', $partial);
        self::assertSame(2, substr_count($partial, 'data-admission-installment-empty'));
        self::assertStringContainsString('Not applicable &mdash; Coaching fee is not on an instalment plan.', $partial);
        self::assertStringContainsString('Not applicable &mdash; Hostel fee is not on an instalment plan.', $partial);
        self::assertStringContainsString('data-admission-installment-rows', $partial);
        self::assertStringContainsString('name="installment_year[]"', $partial);
        self::assertStringContainsString('name="installment_<?= $number ?>_date[]"', $partial);
        self::assertStringContainsString('name="installment_<?= $number ?>[]"', $partial);
        self::assertStringContainsString('name="hostel_installment_<?= $number ?>_date[]"', $partial);
        self::assertStringContainsString('name="hostel_installment_<?= $number ?>[]"', $partial);
        self::assertSame(4, substr_count($partial, 'class="input-group mcc-admission-installment-input-group"'));
        self::assertSame(4, substr_count($partial, 'class="input-group-prepend"'));
        self::assertStringContainsString('class="input-group-text">Date</span>', $partial);
        self::assertStringContainsString('class="input-group-text">Amount</span>', $partial);
        self::assertStringContainsString('data-mcc-date-picker', $partial);
        self::assertStringContainsString('data-mcc-date-submit-format="Y-m-d"', $partial);
        self::assertStringContainsString('data-admission-installment-add', $partial);
        self::assertStringContainsString('data-admission-installment-remove', $partial);
        self::assertStringContainsString('data-admission-installment-section', $script);
        self::assertStringContainsString('slot.appendChild(content);', $script);
        self::assertStringContainsString('var emptyState = schedule.parentElement', $script);
        self::assertStringContainsString('emptyState.hidden = visible;', $script);
        self::assertStringContainsString('schedule.hidden = !visible;', $script);
        self::assertStringContainsString("group.className = 'input-group mcc-admission-installment-input-group'", $script);
        self::assertStringContainsString("text.textContent = labelText", $script);
        self::assertStringContainsString('name="installment_due_dates"', $partial);
        self::assertStringContainsString('admission_coaching_installments', $installmentMigration);
        self::assertStringContainsString('admission_hostel_installments', $installmentMigration);
        self::assertStringContainsString('admission_id', $installmentMigration);
        self::assertStringContainsString('admission_period_id', $installmentMigration);
        self::assertStringContainsString('year_number', $installmentMigration);
        self::assertStringContainsString('installment_number', $installmentModel);
        self::assertStringContainsString('due_date', $installmentModel);
        self::assertStringContainsString('amount', $installmentModel);
        self::assertStringContainsString('admission_coaching_installments', $installmentModel);
        self::assertStringContainsString('admission_hostel_installments', $hostelInstallmentModel);
        self::assertStringNotContainsString('schedule_type', $installmentModel . $hostelInstallmentModel);
        self::assertStringContainsString('hostel_installment_plan', $service);
        self::assertStringContainsString('hostel_installment_rows', $partial);
        self::assertStringNotContainsString("'hostel_payment_plan', 'Hostel payment plan'", $partial);
        self::assertStringContainsString("'hostel_installment_plan', 'Hostel Payment Plan'", $fees);
        self::assertStringContainsString('Total Coaching + Hostel Fee', $fees);
        self::assertStringContainsString('data-admission-fee-total', $fees);
        self::assertStringContainsString('calculateFeeTotal', $service);
        self::assertStringContainsString('initAdmissionFeeTotal', $script);
        self::assertStringContainsString('.mcc-bt-select {', $styles);
        self::assertStringContainsString('.mcc-admission-form-shell .mcc-bt-select-menu', $styles);
        self::assertStringContainsString('z-index: 1003;', $styles);
        self::assertStringContainsString('isolation: isolate;', $styles);
        self::assertStringContainsString('overflow: visible;', $styles);
        self::assertStringContainsString(
            '.mcc-admission-form details[data-admission-section]:not([open]) > .mcc-admission-section-body',
            $styles
        );
        self::assertStringContainsString('.mcc-admission-form-shell .mcc-admission-section:has(.mcc-bt-select-menu:not([hidden]))', $styles);
        self::assertStringContainsString('.mcc-admission-form-shell .mcc-admission-section.mcc-bt-select-open', $styles);
        self::assertStringContainsString(
            '.mcc-admission-form-shell .mcc-admission-section.mcc-bt-select-open:has(.mcc-bt-select-menu:not([hidden]))',
            $styles,
        );
        self::assertStringContainsString('z-index: 5000;', $styles);
        self::assertStringContainsString('.mcc-admission-form-shell .mcc-admission-form > .mcc-admission-subsection', $styles);
        self::assertStringContainsString('position: absolute;', $styles);
        self::assertStringContainsString('z-index: 4001;', $styles);
        self::assertStringContainsString('z-index: 4002;', $styles);
        self::assertStringContainsString('max-width: min(34rem, calc(100vw - 2rem));', $styles);
        self::assertStringContainsString('.mcc-admission-form-shell .mcc-admission-section.row,', $styles);
        self::assertStringContainsString('grid-template-columns: repeat(2, minmax(0, 1fr));', $styles);
        self::assertStringContainsString('grid-column: 1 / -1;', $styles);
        self::assertStringContainsString('min-width: 0;', $styles);
        self::assertStringContainsString('box-sizing: border-box;', $styles);
        self::assertStringContainsString('resize: vertical;', $styles);
        self::assertStringContainsString('.mcc-admission-installment-schedule', $styles);
        self::assertStringContainsString('.mcc-admission-installment-table', $styles);
        self::assertStringContainsString('td > .mcc-date-picker', $styles);
        self::assertStringContainsString('td > .form-control', $styles);
        self::assertStringContainsString('td[data-label]::before', $styles);
        self::assertStringContainsString('.mcc-admission-form-shell .mcc-form-field:has(> textarea)', $styles);
        self::assertStringContainsString('.mcc-admission-form-shell .mcc-form-field.col-6:has(> textarea)', $styles);
        self::assertStringContainsString('.mcc-admission-form-shell .mcc-admission-fields > [class*="col-"]', $styles);
        self::assertStringContainsString('grid-template-columns: minmax(0, 1fr);', $styles);
        self::assertStringContainsString('.mcc-admission-form-shell .mcc-date-picker-popup', $styles);
        self::assertStringContainsString('position: fixed;', $styles);
        self::assertStringContainsString('z-index: 50000;', $styles);
        self::assertStringContainsString('data-mcc-date-picker-overlay', $script);
        self::assertStringContainsString('document.body.appendChild(popup)', $script);
        self::assertStringContainsString('getBoundingClientRect()', $script);
        self::assertStringContainsString("window.addEventListener('scroll', positionPopup, true)", $script);
        self::assertStringContainsString('.mcc-admission-form-shell .mcc-form-field:has(.mcc-bt-select).mcc-bt-select-open', $styles);
        self::assertStringContainsString('.mcc-admission-form-shell .mcc-form-field:has(.mcc-date-picker.is-open)', $styles);
        self::assertStringContainsString('.mcc-admission-form-shell .mcc-admission-actions', $styles);
        self::assertStringContainsString('@media (max-width: 767.98px)', $styles);
        self::assertStringContainsString("view('components/admission-select'", $new);
        self::assertStringContainsString("view('components/admission-select'", $edit);
        self::assertStringContainsString(
            "\$selectField('bowling_style', 'Bowling style'",
            $partial,
        );
        self::assertStringContainsString('$useBtSelect = ! $forceNativeSelect && ($forceBtSelect || $multiple || $realOptionCount >= 10);', $selectComponent);
        self::assertStringContainsString('$value === \'\'', $selectComponent);
        self::assertStringContainsString('! empty($option[\'disabled\'])', $selectComponent);
        foreach ([
            "'age_group_id' => 'Choose group'",
            "'gender' => 'Choose gender'",
            "'branch_id' => 'Choose branch'",
            "'batch_id' => 'Choose batch'",
            "'coach_user_id' => 'Choose coach'",
            "'personal_coach_user_id' => 'Choose coach'",
            "'lead_id' => 'Choose lead'",
            "'trial_id' => 'Choose trial'",
            "'batting_order' => 'Choose order'",
            "'installment_plan' => 'Choose plan'",
            "'hostel_installment_plan' => 'Choose plan'",
            "'ability_overall' => 'Choose rating'",
            "'ability_fitness' => 'Choose rating'",
        ] as $placeholder) {
            self::assertStringContainsString($placeholder, $selectComponent);
        }
        self::assertStringContainsString(
            "data-bt-select-placeholder=\"' . esc(\$placeholder) . '\"",
            $selectComponent
        );
        foreach ([$new, $edit, $partial] as $form) {
            self::assertStringNotContainsString('data-bt-select-placeholder="Select ', $form);
        }
        self::assertStringContainsString('data-bt-select-placeholder', $script);
        self::assertStringContainsString("'forceBtSelect' => true", $new);
        self::assertStringContainsString("#admission-batch-coach", $styles);
        self::assertStringContainsString("#admission-personal-coach", $styles);
        self::assertStringContainsString("'forceNativeSelect' => true", $new);
        self::assertStringNotContainsString("'id' => 'admission-status', 'name' => 'status', 'label' => 'Status', 'forceBtSelect' => true", $new);
        self::assertStringContainsString('max-width: 300px;', $styles);
        self::assertStringContainsString('width: min(300px, calc(100vw - 32px));', $styles);
        self::assertStringContainsString('min-width: 0;', $styles);
        self::assertStringContainsString('box-sizing: border-box;', $styles);
        self::assertStringContainsString("option.value !== ''", $script);
        self::assertStringContainsString('var placeholder', $script);
        foreach ([$new . $sections, $edit . $sections] as $form) {
            self::assertStringNotContainsString('Coaching plan preset', $form);
            self::assertStringNotContainsString('name="coaching_plan"', $form);
            self::assertStringContainsString("view('components/admission-select'", $form);
        }
        foreach (['year1_age_group', 'year1_plan', 'year2_age_group', 'year2_plan', 'year3_age_group', 'year3_plan'] as $field) {
            self::assertStringNotContainsString("textField('$field'", $partial);
            self::assertStringContainsString("'$field'", $service);
        }
        foreach ([
            'personal_coaching_days',
            'personal_coaching_duration',
            'group_coaching_days',
        ] as $field) {
            self::assertStringContainsString("selectField('$field'", $partial);
        }
        foreach ([
            'coaching_delivery',
            'induction_plan',
            'review_frequency',
            'progress_report_frequency',
            'feedback_call_frequency',
            'student_diary',
        ] as $field) {
            self::assertStringContainsString("radioGroup('$field'", $partial);
        }
        self::assertStringContainsString("checkboxGroup('meditation_sessions'", $partial);
        self::assertStringContainsString("'coaching_type',", $partial);
        self::assertStringContainsString(
            '$coachingTypeFields = ob_get_clean();',
            $partial,
        );
        self::assertStringContainsString('mcc-coaching-dependent-fields', $partial);
        self::assertStringNotContainsString("selectField('elite_camp'", $partial);
        self::assertStringNotContainsString('data-admission-elite-camp-control', $partial);
        self::assertStringContainsString('data-admission-coaching-dependent', $partial);
        self::assertSame(0, substr_count($partial, "textareaField('coaching_plan'"));
        self::assertStringContainsString('gym_fitness_session', $service);
        $masterService = (string) file_get_contents($root . '/app/Services/AdmissionMasterService.php');
        self::assertStringNotContainsString("'coaching' =>", $masterService);
        self::assertStringNotContainsString('admission_coaching_plans', $masterService);
        self::assertStringContainsString("'id' => 'branch'", $new);
        self::assertStringContainsString("'id' => 'admission-branch'", $edit);
        self::assertStringContainsString('Lead Information', $new . $edit);
        self::assertStringNotContainsString('Admission information</legend>', $new . $edit);
        foreach ([
            [$new, 'data-admission-subsection="coaching-admission" data-admission-subsection-target="lead-ratings" data-admission-subsection-order="20"'],
            [$edit, 'data-admission-subsection="coaching-admission" data-admission-subsection-target="lead-ratings" data-admission-subsection-order="20"'],
        ] as [$form, $coachingTarget]) {
            self::assertStringContainsString("'label' => 'Lead'", $form);
            self::assertStringContainsString("view('components/admission-select'", $form);
            self::assertStringContainsString("'name' => 'trial_id'", $form);
            self::assertGreaterThanOrEqual(1, substr_count($form, 'Lead Information'));
            self::assertSame(1, substr_count($form, "'name' => 'lead_id'"));
            self::assertStringContainsString($coachingTarget, $form);
        }
        self::assertStringContainsString("'id' => 'lead'", $new);
        self::assertStringContainsString("'id' => 'lead'", $edit);
        self::assertStringContainsString('.mcc-admission-form-shell .mcc-admission-subsection:has(#trial)', $styles);
        self::assertStringContainsString('.mcc-admission-form-shell .mcc-bt-select:has(#trial-id)', $styles);
        self::assertStringContainsString('z-index: 3101;', $styles);
        self::assertStringContainsString('Batch Coach', $new . $edit);
        self::assertStringNotContainsString('Admission Date', $new);
        self::assertStringNotContainsString('name="admission_date"', $new);
        self::assertStringContainsString('name="start_date"', $new);
        self::assertStringContainsString('name="end_date"', $new);
        self::assertStringContainsString('class="row mcc-admission-fields mcc-admission-branch-fields"', $new);
        self::assertStringContainsString('class="row mcc-admission-fields mcc-admission-branch-fields"', $edit);
        self::assertStringContainsString(
            ".mcc-admission-form-shell .mcc-admission-branch-fields {\n    grid-template-columns: repeat(3, minmax(0, 1fr));",
            $styles,
        );
        self::assertStringContainsString(
            ".mcc-admission-form-shell .mcc-admission-branch-fields {\n        grid-template-columns: minmax(0, 1fr);",
            $styles,
        );
        self::assertStringNotContainsString('Branch / ownership', $new . $edit);
        foreach ([$new, $edit] as $form) {
            self::assertStringContainsString("'name' => 'batch_id'", $form);
            self::assertStringContainsString("'name' => 'coach_user_id'", $form);
            self::assertStringContainsString("'name' => 'personal_coach_user_id'", $form);
            self::assertStringContainsString("view('components/admission-select'", $form);
        }
        self::assertStringContainsString('data-admission-branch-control', $new . $edit);
        self::assertStringContainsString("'data-branch-id'", $new . $edit);
        self::assertStringContainsString('.mcc-admission-form-shell .mcc-admission-subsection:has(#branch)', $styles);
        self::assertStringContainsString('.mcc-admission-form-shell .mcc-bt-select:has(#branch)', $styles);
        self::assertStringContainsString('z-index: 3001;', $styles);
        self::assertStringContainsString("'age' => [", $new);
        self::assertStringContainsString("'name' => 'age'", $edit);
        self::assertStringContainsString('min="0" max="120"', $new);
        self::assertStringNotContainsString('name="bowling_options"', $new . $edit . $partial);
        self::assertStringContainsString('min="0" max="120"', $edit);
        self::assertStringContainsString("'age'", $service);
        self::assertStringContainsString('Invalid age.', $service);
        self::assertStringContainsString('csrf_field()', $new);
        self::assertStringContainsString('csrf_field()', $edit);
        self::assertStringContainsString('_form_actions', $new);
        self::assertStringContainsString('_form_actions', $edit);
        self::assertStringContainsString('mcc-form-actions', $actions);
        self::assertStringNotContainsString('admission_number', $new);
        self::assertStringNotContainsString('admission_number', $edit);
        self::assertStringNotContainsString('$textField(\'admission_number\'', $partial);
        self::assertStringNotContainsString("'admission_number',", $service);
        self::assertStringNotContainsString('Uploaded documents and photo', $new);
        self::assertStringNotContainsString('name="profile_photo"', $new);
        self::assertStringNotContainsString('name="document"', $new);
        self::assertStringContainsString('<?php if (false): ?>', $edit);
        self::assertStringNotContainsString('Documents &amp; Photo', $new);
        self::assertStringNotContainsString('name="profile_photo"', $new);
        self::assertStringNotContainsString('name="document"', $new);

        $detail = (string) file_get_contents($root . '/app/Modules/Admissions/Views/show.php');
        self::assertStringContainsString('Admission #', $detail);
        self::assertStringContainsString('$a[\'id\']', $detail);

        foreach ([
            'personal-address',
            'cricket-information',
            'coaching-training-options',
        ] as $subsection) {
            self::assertStringContainsString('data-admission-subsection="' . $subsection . '"', $partial);
            self::assertStringContainsString('data-admission-subsection-target=', $partial);
        }
        self::assertStringNotContainsString('data-admission-subsection="coaching-fee-schedule"', $partial);
        self::assertStringNotContainsString('data-admission-subsection="hostel-schedule"', $partial);
        self::assertStringNotContainsString('<h3>Coaching Instalment Schedule</h3>', $partial);
        self::assertStringNotContainsString('<h3>Hostel Instalment Schedule</h3>', $partial);
        self::assertStringContainsString('data-admission-five-sections', $new);
        self::assertStringContainsString('data-admission-five-sections', $edit);
        foreach ([$new, $edit] as $form) {
            $formSource = $form . $sections . $partial;
            self::assertStringContainsString('class="mcc-admission-form"', $formSource);
            self::assertStringContainsString('class="row mcc-admission-fields"', $formSource);
            self::assertStringContainsString("view('components/admission-select'", $formSource);
            self::assertStringContainsString('data-mcc-date-picker', $formSource);
        }
        self::assertStringContainsString('mcc-admission-signature-section', $bottom);
        self::assertStringContainsString("'field_class' => 'col-12 mcc-admission-declaration-field'", $bottom);
        self::assertStringContainsString('mcc-admission-declaration-field', $bottom);
        self::assertStringContainsString('<span>I have read the rules', $bottom);
        $declarationMarkupStart = strpos($bottom, "'field_class' => 'col-12 mcc-admission-declaration-field'");
        $declarationMarkup = substr($bottom, $declarationMarkupStart, 220);
        self::assertStringNotContainsString('col-6', $declarationMarkup);
        self::assertStringNotContainsString('col-md-6', $declarationMarkup);
        self::assertStringContainsString('grid-column: 1 / -1;', $styles);
        self::assertStringContainsString('flex: 0 0 100%;', $styles);
        self::assertStringContainsString('mcc-admission-declaration-field', $styles);
        self::assertStringContainsString('width: 100%;', $styles);
        self::assertStringContainsString('align-items: center;', $styles);
        self::assertStringContainsString('display: flex;', $styles);
        self::assertStringContainsString('gap: 18px;', $styles);
        self::assertStringContainsString('height: 18px;', $styles);
        self::assertStringContainsString('margin: 0;', $styles);
        self::assertStringContainsString('line-height: 1.5;', $styles);
        self::assertStringContainsString('text-align: left;', $styles);
        self::assertStringContainsString('<div class="col-12 col-md-6">', $bottom);
        self::assertSame(2, substr_count($bottom, '<div class="col-12 col-md-6">'));
        self::assertStringNotContainsString(
            'Use touch or mouse to sign. A saved signature is retained after validation errors.',
            $bottom . $signatureComponent
        );
        self::assertStringContainsString('mcc-signature-field', $signatureComponent);
        foreach (['Student Signature', 'Parent/Guardian Signature', 'Club Authorized Person Signature'] as $label) {
            self::assertStringContainsString($label, $bottom);
        }
        self::assertStringContainsString('data-mcc-signature-canvas', $signatureComponent);
        self::assertStringContainsString('data-mcc-signature-clear', $signatureComponent);
        self::assertStringContainsString('data-mcc-signature-input', $signatureComponent);
        self::assertStringContainsString('mcc-button-danger', $signatureComponent);
        self::assertStringContainsString('Clear Signature', $signatureComponent);
        self::assertStringNotContainsString('Redraw', $signatureComponent);
        self::assertStringNotContainsString('data-mcc-signature-redraw', $signatureComponent);
        self::assertStringContainsString('mcc-signature-border', $signatureComponent);
        self::assertStringContainsString('.mcc-signature-border {', $styles);
        self::assertStringContainsString('border: 2px solid var(--app-border);', $styles);
        self::assertStringContainsString('border-color: var(--app-secondary);', $styles);
        self::assertStringContainsString('border-radius: var(--app-radius);', $styles);
        self::assertStringContainsString('padding: .35rem;', $styles);
        self::assertStringContainsString('width: 100%;', $styles);
        self::assertStringContainsString('mcc-admission-bottom-block', $bottom);
        self::assertStringContainsString('Documents submitted', $bottom);
        self::assertStringContainsString('includeDocumentRecord', $bottom);
        self::assertStringContainsString("'name' => 'status'", $new);
        self::assertStringContainsString("'name' => 'status'", $edit);
        self::assertStringContainsString("'status' => 'Choose status'", $selectComponent);
        self::assertStringContainsString('public function statusOptions', $service);
        self::assertStringContainsString('Pending Admission', $service);
        self::assertStringContainsString('Completed', $service);
    }

    public function testAdmissionFormsHaveOneCanonicalDateAndNoDuplicatePrimaryNames(): void
    {
        $root = dirname(__DIR__, 2);
        $new = (string) file_get_contents($root . '/app/Modules/Admissions/Views/new.php');
        $edit = (string) file_get_contents($root . '/app/Modules/Admissions/Views/edit.php');
        $partial = (string) file_get_contents($root . '/app/Modules/Admissions/Views/_complete_form_fields.php');
        $fees = (string) file_get_contents($root . '/app/Modules/Admissions/Views/_fees_fields.php');
        $service = (string) file_get_contents($root . '/app/Services/AdmissionService.php');
        $selectComponent = (string) file_get_contents($root . '/app/Views/components/admission-select.php');
        $editPrimary = explode('<section class="mcc-admission-documents-panel"', $edit, 2)[0];

        self::assertSame(0, substr_count($new, 'name="joining_date"'));
        self::assertSame(0, substr_count($edit, 'name="joining_date"'));
        self::assertSame(1, substr_count($new, 'name="start_date"'));
        self::assertSame(1, substr_count($new, 'name="end_date"'));
        self::assertStringNotContainsString('name="admission_date"', $partial);
        self::assertStringNotContainsString("'admission_date'", $partial);

        foreach ([$new, $edit] as $form) {
            foreach (['batch_id', 'trial_id'] as $field) {
                self::assertSame(
                    1,
                    substr_count($form, "'name' => '" . $field . "'"),
                    'Primary Admission form must configure ' . $field . ' once.'
                );
            }
            self::assertSame(0, substr_count($form, 'name="joining_date"'));
            self::assertSame(1, substr_count($form, 'name="start_date"'));
            self::assertSame(1, substr_count($form, 'name="end_date"'));
        }

        self::assertSame(1, substr_count($new, '<?= csrf_field() ?>'));
        self::assertSame(1, substr_count($editPrimary, '<?= csrf_field() ?>'));
        self::assertStringContainsString("'applicant_email'", $new);
        self::assertStringContainsString("'parent_email'", $new);
        self::assertStringContainsString("'current_address'", $partial);
        self::assertStringContainsString("'permanent_address'", $partial);
        self::assertStringNotContainsString("textareaField('correspondence_address'", $partial);
        self::assertStringContainsString('$field === \'current_address\'', $partial);
        self::assertStringNotContainsString("'admission_fee'", $fees);
        self::assertStringContainsString("'training_fee'", $fees);
        self::assertStringContainsString("'hostel_charges'", $fees);
        self::assertStringNotContainsString("textField('admission_fee'", $partial);
        self::assertStringNotContainsString("textField('training_fee'", $partial);
        self::assertStringNotContainsString("textField('hostel_charges'", $partial);
    }

    public function testCoachingSelectionMigrationIsForwardOnlyAndPreservesExistingStorage(): void
    {
        $root = dirname(__DIR__, 2);
        $migration = (string) file_get_contents(
            $root . '/app/Database/Migrations/20261013000100_AddAdmissionCoachingSelections.php',
        );

        self::assertStringContainsString("'gym_fitness_session'", $migration);
        self::assertStringContainsString("'fitness_plan'", $migration);
        self::assertStringContainsString("'match_program'", $migration);
        self::assertStringContainsString("'welcome_kit'", $migration);
        self::assertStringContainsString("'type' => 'TEXT'", $migration);
        self::assertStringContainsString('Forward-only', $migration);
        self::assertStringNotContainsString('dropColumn', $migration);
        self::assertStringNotContainsString('truncate', strtolower($migration));
    }

    public function testAgeIsSeparateFromDateOfBirthAndShownOnAdmissionDetail(): void
    {
        $root = dirname(__DIR__, 2);
        $new = (string) file_get_contents($root . '/app/Modules/Admissions/Views/new.php');
        $edit = (string) file_get_contents($root . '/app/Modules/Admissions/Views/edit.php');
        $partial = (string) file_get_contents($root . '/app/Modules/Admissions/Views/_complete_form_fields.php');
        $detail = (string) file_get_contents($root . '/app/Modules/Admissions/Views/show.php');

        self::assertStringContainsString('_complete_form_fields', $new);
        self::assertStringContainsString('_complete_form_fields', $edit);
        self::assertStringContainsString("'age' => [", $new);
        self::assertStringContainsString("'name' => 'age'", $edit);
        self::assertStringContainsString('min="0" max="120"', $new);
        self::assertStringContainsString('min="0" max="120"', $edit);
        self::assertStringContainsString('applicant_date_of_birth', $new);
        self::assertStringContainsString('applicant_date_of_birth', $edit);
        self::assertStringContainsString('<dt>Age</dt>', $detail);
        self::assertStringContainsString('$value(\'age\')', $detail);
    }

    public function testAdmissionSubsectionMapIsExplicitAndValidationOpensNestedSections(): void
    {
        $root = dirname(__DIR__, 2);
        $new = (string) file_get_contents($root . '/app/Modules/Admissions/Views/new.php');
        $edit = (string) file_get_contents($root . '/app/Modules/Admissions/Views/edit.php');
        $partial = (string) file_get_contents($root . '/app/Modules/Admissions/Views/_complete_form_fields.php');
        $sections = (string) file_get_contents($root . '/app/Modules/Admissions/Views/_form_sections.php');
        $javascript = (string) file_get_contents($root . '/public/assets/js/app.js');
        $styles = (string) file_get_contents($root . '/public/assets/css/app.css');
        $formField = (string) file_get_contents($root . '/app/Views/components/form-field.php');
        $service = (string) file_get_contents($root . '/app/Services/AdmissionService.php');

        self::assertSame(8, substr_count($new . $sections, 'data-admission-section="'));
        self::assertSame(8, substr_count($edit . $sections, 'data-admission-section="'));

        foreach ([$new . $sections, $edit . $sections] as $form) {
            self::assertStringContainsString('data-admission-subsection-target="personal"', $form);
        }

        foreach (['personal', 'cricket', 'coaching'] as $target) {
            self::assertStringContainsString(
                'data-admission-subsection-target="' . $target . '"',
                $new . $edit . $partial
            );
        }

        self::assertGreaterThanOrEqual(1, substr_count($styles, '.mcc-admission-subsection,'));
        self::assertSame(1, substr_count($styles, '.mcc-admission-subgroup {'));
        self::assertStringContainsString(
            ".mcc-admission-subsection,\n.mcc-admission-subgroup",
            $styles
        );

        foreach ([
            'Student Information',
            'Parent/Guardian Information',
            'Address',
            'Cricket Information',
            'Lead Information and Ratings',
            'Training Selection and Coaching Options',
        ] as $heading) {
            self::assertStringContainsString($heading, $new . $edit . $partial);
        }

        self::assertStringContainsString(
            'class="row mcc-admission-fields mcc-admission-training-fields"',
            $partial,
        );
        self::assertStringContainsString(
            ".mcc-admission-form-shell .mcc-admission-training-fields {\n    gap: 1rem;\n    grid-template-columns: minmax(0, 1fr);",
            $styles,
        );
        self::assertStringContainsString(
            ".mcc-admission-form-shell .mcc-admission-training-fields {\n        grid-template-columns: minmax(0, 1fr);",
            $styles,
        );
        self::assertStringContainsString(
            '.mcc-admission-form-shell .mcc-admission-training-fields > .mcc-form-field,',
            $styles,
        );
        self::assertStringContainsString('grid-column: 1 / -1;', $styles);
        self::assertStringContainsString('fieldset .form-check', $styles);
        self::assertStringContainsString('display: inline-flex;', $styles);
        self::assertStringContainsString('.mcc-admission-form-shell .form-check {', $styles);
        self::assertStringContainsString('align-items: center;', $styles);
        self::assertStringContainsString('column-gap: 0;', $styles);
        self::assertStringContainsString('gap: 0;', $styles);
        self::assertStringContainsString('margin-left: 18px;', $styles);
        self::assertStringContainsString(
            '.mcc-admission-form-shell .mcc-admission-signature-section .mcc-checkbox-label {',
            $styles,
        );
        self::assertStringContainsString('gap: 18px;', $styles);
        self::assertStringContainsString("'<div class=\"mcc-checkbox-options\">'", $partial);
        self::assertStringContainsString('.mcc-admission-form-shell .mcc-checkbox-options {', $styles);
        self::assertStringContainsString('flex-wrap: wrap;', $styles);
        self::assertStringContainsString('align-items: center;', $styles);
        self::assertStringContainsString('gap: .75rem 1.25rem;', $styles);
        self::assertStringContainsString('.mcc-admission-form-shell .mcc-checkbox-label {', $styles);
        self::assertStringContainsString('.mcc-admission-form-shell .mcc-coaching-dependent-fields {', $styles);
        self::assertStringContainsString('grid-template-columns: repeat(3, minmax(0, 1fr));', $styles);
        self::assertStringContainsString('grid-template-columns: repeat(2, minmax(0, 1fr));', $styles);
        self::assertStringContainsString('grid-template-columns: minmax(0, 1fr);', $styles);
        self::assertStringContainsString('data-admission-coaching-dependent-fields hidden', $partial);
        self::assertStringContainsString('var visibleFieldCount = 0;', $javascript);
        self::assertStringContainsString(
            "dependentFields.hidden = visibleFieldCount === 0;",
            $javascript,
        );
        self::assertStringContainsString('field.value = \'\';', $javascript);

        foreach ([
            'coaching_type',
            'fitness_plan',
            'gym_fitness_session',
            'match_program',
            'meditation_sessions',
            'welcome_kit',
        ] as $field) {
            if ($field === 'coaching_type') {
                self::assertStringContainsString("'coaching_type',", $partial);
                continue;
            }

            self::assertStringContainsString("checkboxGroup('$field'", $partial);
        }

        foreach ([
            'coaching_delivery',
            'induction_plan',
            'review_frequency',
            'progress_report_frequency',
            'feedback_call_frequency',
            'student_diary',
        ] as $field) {
            self::assertStringContainsString("radioGroup('$field'", $partial);
            self::assertStringNotContainsString("checkboxGroup('$field'", $partial);
        }

        foreach ([
            'personal_coaching_days',
            'personal_coaching_duration',
            'estimated_personal_session',
            'group_coaching_days',
            'elite_camp_days',
            'elite_camp_duration',
            'estimated_elite_session',
        ] as $field) {
            self::assertStringContainsString("'$field'", $partial);
        }
        self::assertSame(5, substr_count($partial, "false, 'col-lg-4 col-md-6 col-12'"));
        self::assertStringContainsString(
            '$layoutClass = $fieldClass === \'col-12\'',
            $formField,
        );

        self::assertStringNotContainsString('<h3>Training Selection</h3>', $new . $edit . $partial);
        self::assertStringNotContainsString('Fee Summary', $new . $edit . $partial);
        self::assertStringNotContainsString('Hostel Requirement', $new . $edit . $partial);
        self::assertStringNotContainsString('name="hostel_required"', $new . $edit . $partial);
        self::assertStringNotContainsString('name="admission_fee"', $new . $edit . $partial);
        self::assertStringNotContainsString('data-admission-subsection="coaching-training"', $new . $edit . $partial);
        self::assertStringContainsString("subsection.querySelector('.mcc-field-error, .has-error')", $javascript);
        self::assertStringContainsString('data-admission-subsection-order', $javascript);
        self::assertStringContainsString('mcc-bt-select-open', $javascript);
        self::assertStringContainsString('setStackingState(true)', $javascript);
        self::assertStringContainsString('setStackingState(false)', $javascript);
        self::assertStringContainsString('initAdmissionBranchScopedFields', $javascript);
        self::assertStringContainsString('initAdmissionInstallmentSchedule', $javascript);
        self::assertStringContainsString('clearInputs', $javascript);
        self::assertStringContainsString('Duplicate instalment year.', $service);

        $addressStart = strpos($partial, 'data-admission-subsection="personal-address"');
        self::assertNotFalse($addressStart);
        $cricketStart = strpos($partial, 'data-admission-subsection="cricket-information"');
        self::assertNotFalse($cricketStart);
        $addressBlock = substr($partial, $addressStart);
        self::assertStringContainsString('<h3>Address</h3>', $addressBlock);
        self::assertStringNotContainsString('academic_year', $addressBlock);
        self::assertStringNotContainsString('school_college', $addressBlock);
        self::assertStringContainsString('current_address', $addressBlock);
        self::assertStringContainsString('permanent_address', $addressBlock);
        self::assertStringNotContainsString('Academic and Application Details', $new . $edit . $partial);
        self::assertStringNotContainsString('data-admission-subsection="personal-application-records"', $new . $edit . $partial);
        self::assertSame(1, substr_count($new, "'academic_year' => ["));
        self::assertSame(1, substr_count($edit, "'name' => 'academic_year'"));
        self::assertSame(1, substr_count($new, "'school_college' => ["));
        self::assertSame(1, substr_count($edit, "'name' => 'school_college'"));
        self::assertStringContainsString('Applicant information', $new . $edit);

        foreach ([$new . $sections, $edit . $sections] as $form) {
            $sectionNames = [
                'personal',
                'coaching',
                'cricket',
                'lead-ratings',
                'fees',
                'coaching-installments',
                'hostel-installments',
                'declarations',
            ];
            $sectionPositions = array_map(
                static fn (string $section): int|false => strpos(
                    $form,
                    'data-admission-section="' . $section . '"',
                ),
                $sectionNames,
            );

            foreach ($sectionPositions as $position) {
                self::assertNotFalse($position);
            }
            for ($index = 1, $count = count($sectionPositions); $index < $count; $index++) {
                self::assertTrue($sectionPositions[$index - 1] < $sectionPositions[$index]);
            }
        }

        $coachingSectionPosition = strpos(
            $sections,
            'data-admission-section="coaching"',
        );
        $personalSectionPosition = strpos(
            $sections,
            'data-admission-section="personal"',
        );
        self::assertNotFalse($coachingSectionPosition);
        self::assertNotFalse($personalSectionPosition);
        self::assertLessThan($coachingSectionPosition, $personalSectionPosition);

        $coachingBranchPosition = strpos(
            $new . $partial,
            'data-admission-subsection="coaching-branch"',
        );
        $trainingOptionsPosition = strpos(
            $new . $partial,
            'data-admission-subsection="coaching-training-options"',
        );
        self::assertNotFalse($coachingBranchPosition);
        self::assertNotFalse($trainingOptionsPosition);
        self::assertLessThan($trainingOptionsPosition, $coachingBranchPosition);

        foreach ([$new . $sections, $edit . $sections] as $form) {
            $sectionsEnd = strpos($form, 'data-admission-section="hostel-installments"');
            $bottomStart = strpos($form, '_declarations_signatures');
            self::assertNotFalse($sectionsEnd);
            self::assertNotFalse($bottomStart);
            self::assertTrue($sectionsEnd < $bottomStart);
        }
        self::assertStringContainsString('.mcc-admission-subgroup {', $styles);
        self::assertStringContainsString('.mcc-admission-subsection,', $styles);
        self::assertStringContainsString('.mcc-admission-subgroup {', $styles);
        self::assertStringContainsString('background: #f8f9fc;', $styles);
        self::assertStringContainsString('border: 1px solid var(--app-border);', $styles);
        self::assertStringContainsString('border-radius: var(--app-radius);', $styles);
        self::assertStringContainsString('overflow: visible;', $styles);
        self::assertStringContainsString('padding: 1rem;', $styles);
        self::assertStringContainsString('color: var(--app-text);', $styles);
        self::assertStringContainsString('color: var(--app-danger);', $styles);
    }

    public function testAdmissionGridUsesExplicitColumnChildrenOnCreateAndEdit(): void
    {
        $root = dirname(__DIR__, 2);
        $new = (string) file_get_contents($root . '/app/Modules/Admissions/Views/new.php');
        $edit = (string) file_get_contents($root . '/app/Modules/Admissions/Views/edit.php');
        $fees = (string) file_get_contents($root . '/app/Modules/Admissions/Views/_fees_fields.php');
        $signature = (string) file_get_contents($root . '/app/Views/components/signature-field.php');
        $bottom = (string) file_get_contents($root . '/app/Modules/Admissions/Views/_declarations_signatures.php');
        $styles = (string) file_get_contents($root . '/public/assets/css/app.css');

        self::assertStringNotContainsString('fieldset class="mcc-admission-section row"', $edit);
        self::assertSame(2, substr_count($new, 'class="mcc-form-field col-6"'));
        self::assertSame(7, substr_count($new, 'col-12 col-md-4'));
        self::assertSame(2, substr_count($bottom, '<div class="col-12 col-md-6">'));
        self::assertStringNotContainsString('mcc-admission-fee-full-row', $fees);
        self::assertStringContainsString('<div class="mcc-form-field col-12 mcc-signature-field', $signature);
        self::assertStringContainsString(
            '.mcc-admission-form-shell .mcc-admission-fields > [class*="col-"]',
            $styles
        );
        self::assertStringContainsString('width: auto;', $styles);
        self::assertStringContainsString('min-width: 0;', $styles);
        self::assertStringContainsString('grid-template-columns: minmax(0, 1fr);', $styles);
    }
}
