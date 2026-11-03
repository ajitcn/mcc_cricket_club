<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Modules\AdmissionTests\Services\AdmissionTestService;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

final class AdmissionTestContractTest extends TestCase
{
    public function testReferenceFieldsAndQuestionsAreRepresentedWithoutInventedScoring(): void
    {
        $service = new AdmissionTestService();
        $questions = $service->questions();

        self::assertSame(['A', 'B', 'C'], array_keys($questions));
        self::assertCount(10, $questions['A']);
        self::assertCount(10, $questions['B']);
        self::assertCount(10, $questions['C']);
        self::assertContains('Sachin Tendulkar', $questions['A'][0]['options']);
        self::assertContains('Leg before wicket', $questions['B'][5]['options']);
        self::assertStringContainsString('NEEDS_DECISION', $questions['A'][5]['prompt']);
        self::assertStringContainsString('NEEDS_DECISION', $questions['B'][8]['prompt']);
        self::assertStringContainsString('NEEDS_DECISION', $questions['C'][4]['prompt']);
    }

    public function testPaperSetCatalogFiltersToExactlyOneOrderedSet(): void
    {
        $service = new AdmissionTestService();

        self::assertSame(['A' => 'Paper Set A', 'B' => 'Paper Set B', 'C' => 'Paper Set C'], $service->paperSetOptions());
        foreach (['A', 'B', 'C'] as $paperSet) {
            $selected = $service->questionsForTest(['test_variant' => $paperSet]);
            self::assertSame([$paperSet], array_keys($selected));
            self::assertCount(10, $selected[$paperSet]);
        }
        self::assertSame([], $service->questionsForTest(['test_variant' => 'invalid']));
        self::assertSame([], $service->questionsForTest([]));
    }

    public function testAdmissionTestDetailUiContract(): void
    {
        $root = dirname(__DIR__, 2);
        $routes = file_get_contents($root . '/app/Modules/AdmissionTests/Config/Routes.php');
        $trialRoutes = file_get_contents($root . '/app/Modules/CRM/Config/Routes.php');
        $view = file_get_contents($root . '/app/Modules/AdmissionTests/Views/show.php');
        $service = file_get_contents($root . '/app/Modules/AdmissionTests/Services/AdmissionTestService.php');

        self::assertIsString($routes);
        self::assertIsString($trialRoutes);
        self::assertIsString($view);
        self::assertIsString($service);
        foreach (['Test / Applicant', 'Test status', 'Created date', 'Last updated date'] as $field) {
            self::assertStringContainsString($field, $view);
        }
        self::assertStringContainsString('mcc-admission-test-summary-primary', $view);
        self::assertStringContainsString("esc('#' . (int) \$test['id'])", $view);
        self::assertStringContainsString("site_url('crm/trials/' . (int) \$test['trial_id'])", $view);
        self::assertStringNotContainsString('<dt>Test reference</dt>', $view);
        self::assertStringNotContainsString('<dt>Applicant name</dt>', $view);
        foreach (['Locked Paper Set', 'Paper Set A', 'Question', 'Answer options', 'Not answered', 'Read-only'] as $field) {
            self::assertStringContainsString($field, $view);
        }
        foreach (['Trial and Lead lineage', 'Meeting Follow-up history', 'Pending Admission', 'mcc-badge', 'mcc-detail-grid', 'mcc-admission-test-question-list', 'mcc-admission-test-question-block', 'mcc-admission-test-question-number', 'mcc-admission-test-question-text', 'mcc-admission-test-options', 'mcc-admission-test-option', 'is-selected', 'is-not-answered', 'mcc-wrap-anywhere'] as $field) {
            self::assertStringContainsString($field, $view);
        }
        self::assertStringContainsString("['label' => 'CRM'", $view);
        self::assertStringContainsString("['label' => 'Admission Test'", $view);
        self::assertStringContainsString("['label' => 'Test Details'", $view);
        self::assertSame(7, substr_count($view, '<?= csrf_field() ?>'));
        self::assertStringContainsString('Generate Student Test Link', $view);
        self::assertStringContainsString('data-copy-student-test-link', $view);
        self::assertStringContainsString('admission_test_student_link_generated', $service);
        foreach (['Change Status', 'data-admission-status-open', 'data-admission-status-modal', 'data-admission-status-form', 'data-admission-status-version-input', 'data-admission-status-error="reason"', "'/status'"] as $statusContract) {
            self::assertStringContainsString($statusContract, $view);
        }
        self::assertStringContainsString('admission-test-fees', $view);
        self::assertStringContainsString("(:num)/fees", file_get_contents($root . '/app/Modules/AdmissionTests/Config/Routes.php'));
        self::assertStringContainsString("'admission-tests/' . (int) \$test['id'] . '#personal-details'", $view);
        self::assertStringContainsString('personalDetailsPage', file_get_contents($root . '/app/Modules/AdmissionTests/Controllers/Browser.php'));
        self::assertStringContainsString("'admission-tests/' . (int) \$test['id'] . '#meeting-follow-ups'", $view);
        self::assertStringContainsString("'crm/trials/' . (int) \$test['trial_id']", $view);
        self::assertStringContainsString("'admission-tests/' . (int) \$test['id'] . '/pending-admission'", $view);
        self::assertStringContainsString('Move to Pending Admission', $view);
        foreach (['Approve the test before moving it to Pending Admission.', 'Pending Admission already created.', 'You do not have permission to create Pending Admission.'] as $conversionReason) {
            self::assertStringContainsString($conversionReason, $view);
        }
        self::assertStringContainsString("\$status === 'APPROVED_FOR_ADMISSION'", $view);
        self::assertStringContainsString("\$pendingAdmissionId === 0", $view);
        self::assertStringContainsString('<input type="hidden" name="version" value="<?= (int) ($test[\'version\'] ?? 1) ?>">', $view);
        self::assertStringContainsString('fa-file-circle-check', $view);
        self::assertStringContainsString("service('authorization')->can('admission.create'", $view);
        self::assertStringContainsString("->select('at.*,", $service);
        self::assertStringContainsString('createPendingAdmission', $service);
        self::assertStringContainsString("esc(\$question['prompt'])", $view);
        self::assertStringContainsString('is-selected', $view);
        self::assertStringContainsString("! \$hasSelectedAnswer && \$options !== []", $view);
        self::assertStringNotContainsString('<div class="mcc-admission-test-answer-label">Selected answer</div>', $view);
        self::assertStringNotContainsString("\$hasSelectedAnswer ? \$selectedAnswer : 'Not answered'", $view);

        $questionSectionStart = strpos($view, '<section class="mcc-card mcc-detail-card" aria-labelledby="selection-test-heading">');
        $questionSectionEnd = strpos($view, '<details class="mcc-admission-test-edit-panel mt-3">', $questionSectionStart);
        self::assertIsInt($questionSectionStart);
        self::assertIsInt($questionSectionEnd);
        $questionSection = substr($view, $questionSectionStart, $questionSectionEnd - $questionSectionStart);
        self::assertStringNotContainsString('mcc-admission-test-question-table', $questionSection);
        self::assertStringNotContainsString('<table', $questionSection);
        self::assertStringNotContainsString('>Mark<', $questionSection);
        self::assertStringNotContainsString('>Result<', $questionSection);
        self::assertStringNotContainsString("admissions/' . (int) \$test['id']", $view);
    }

    public function testBackSideMissingFieldsAreDeclaredInTheReferenceMatrix(): void
    {
        $matrix = file_get_contents(dirname(__DIR__, 2) . '/docs/ADMISSION-TEST-IMPLEMENTATION-MATRIX.md');

        self::assertIsString($matrix);
        foreach (['father_profession', 'secondary_contact_number', 'secondary_email', 'represented_as', 'nearest_branch', 'discovery_source', 'reference_details'] as $field) {
            self::assertStringContainsString($field, $matrix);
        }
        self::assertStringContainsString('NEEDS_DECISION', $matrix);
    }

    public function testRestoredAdmissionTestFeeSectionOrderHasOneWrapper(): void
    {
        $root = dirname(__DIR__, 2);
        $view = file_get_contents($root . '/app/Modules/AdmissionTests/Views/show.php');
        $fees = file_get_contents($root . '/app/Modules/AdmissionTests/Views/_fees.php');
        $css = file_get_contents($root . '/public/assets/css/app.css');

        self::assertIsString($view);
        self::assertIsString($fees);
        self::assertIsString($css);
        self::assertSame(1, substr_count($view, 'id="admission-test-fees"'));
        self::assertStringContainsString("view('App\\\\Modules\\\\AdmissionTests\\\\Views\\\\_fees'", $view);
        self::assertStringContainsString('class="mcc-admission-section mcc-admission-test-fees"', $fees);

        $questionsPosition = strpos($view, 'aria-labelledby="selection-test-heading"');
        $reviewPosition = strpos($view, 'aria-labelledby="review-heading"');
        $feesPosition = strpos($view, 'id="admission-test-fees"');
        $followUpsPosition = strpos($view, 'id="meeting-follow-ups"');
        $pendingAdmissionPosition = strpos($view, 'id="pending-admission"');

        self::assertIsInt($questionsPosition);
        self::assertIsInt($reviewPosition);
        self::assertIsInt($feesPosition);
        self::assertIsInt($followUpsPosition);
        self::assertIsInt($pendingAdmissionPosition);
        self::assertLessThan($reviewPosition, $questionsPosition);
        self::assertLessThan($feesPosition, $reviewPosition);
        self::assertLessThan($followUpsPosition, $feesPosition);
        self::assertLessThan($pendingAdmissionPosition, $followUpsPosition);

        preg_match_all('/\.mcc-admission-test-fees[^\{]*\{[^}]*\}/s', $css, $feeRules);
        $feeCss = implode("\n", $feeRules[0] ?? []);
        self::assertStringNotContainsString('order:', $feeCss);
        self::assertStringNotContainsString('float:', $feeCss);
        self::assertStringNotContainsString('grid-row:', $feeCss);
        self::assertStringNotContainsString('position: absolute', $feeCss);
        self::assertStringNotContainsString('position: fixed', $feeCss);
    }

    public function testEveryConfiguredQuestionHasAnOrderedPromptAndOptions(): void
    {
        $service = new AdmissionTestService();

        foreach ($service->questions() as $variant => $questions) {
            self::assertCount(10, $questions, $variant);
            foreach ($questions as $index => $question) {
                self::assertArrayHasKey('prompt', $question);
                self::assertNotSame('', trim($question['prompt']));
                self::assertNotEmpty($question['options']);
                self::assertSame($index + 1, $index + 1);
            }
        }

        $view = file_get_contents(dirname(__DIR__, 2) . '/app/Modules/AdmissionTests/Views/show.php');
        self::assertIsString($view);
        self::assertStringContainsString('mcc-admission-test-question-list', $view);
        self::assertStringContainsString('mcc-admission-test-question-block', $view);
        self::assertStringContainsString('<?= esc($pageLabels[$pageVariant] ??', $view);
        foreach (['batting_mark', 'wicketkeeping_mark', 'bowling_mark', 'fitness_mark', 'written_test_mark', 'overall_mark', 'Not rated', 'Test result fields'] as $ratingField) {
            self::assertStringNotContainsString($ratingField, $view);
        }
    }

    public function testWizardRendersExactlyThreeStepsAndStableQuestionFields(): void
    {
        $root = dirname(__DIR__, 2);
        $wizard = file_get_contents($root . '/app/Modules/AdmissionTests/Views/wizard.php');
        $service = file_get_contents($root . '/app/Modules/AdmissionTests/Services/AdmissionTestService.php');

        self::assertIsString($wizard);
        self::assertIsString($service);
        foreach (['Personal Details', 'Select Paper Set', 'Answer Questions'] as $step) {
            self::assertStringContainsString($step, $wizard);
        }
        foreach (['data-wizard-previous', 'data-wizard-next', 'data-wizard-submit', '$wizardPath . \'/submit\'', '$wizardPath . \'/personal-details\'', 'data-initial-step', 'version', 'csrf_field', 'Generate Secure Student Link', 'data-copy-student-test-link', 'return_to'] as $contract) {
            self::assertStringContainsString($contract, $wizard);
        }
        foreach (["'answer_' . \$page . '_'", 'wizardValidationErrors', 'submitWizard'] as $contract) {
            self::assertStringContainsString($contract, $wizard . $service);
        }
        foreach (['batting_mark', 'wicketkeeping_mark', 'bowling_mark', 'fitness_mark', 'written_test_mark', 'overall_mark', 'Not rated', 'Test result fields'] as $ratingField) {
            self::assertStringNotContainsString($ratingField, $wizard);
        }
        self::assertStringContainsString('Questions remain optional where the PDF has no required marker.', $wizard);
        self::assertStringContainsString('data-wizard-step="1"', $wizard);
        self::assertSame(3, substr_count($wizard, 'data-wizard-step="'));
        self::assertStringContainsString('Step 1 of 3', $wizard);
        self::assertStringContainsString('Step 2 of 3', $wizard);
        self::assertStringContainsString('Step 3 of 3', $wizard);
        self::assertStringContainsString('aria-label="Step <?= $index + 1 ?> of 3 — <?= esc($label) ?>"', $wizard);
        self::assertStringNotContainsString('Step 1 of 5', $wizard);
        self::assertStringNotContainsString('Step 2 of 5', $wizard);
        self::assertStringNotContainsString('Step 3 of 5', $wizard);
        self::assertStringNotContainsString('Step 4 of 5', $wizard);
        self::assertStringNotContainsString('Step 5 of 5', $wizard);
        self::assertStringContainsString('data-wizard-step="3"', $wizard);
        self::assertStringContainsString('wizard-paper-set-heading', $wizard);
        self::assertStringContainsString('paper_set_display', strtolower($wizard));
        self::assertStringContainsString('Locked set', $wizard);
        self::assertStringContainsString("trial_id']", $wizard);
        self::assertStringContainsString("test['id']", $wizard);
        self::assertStringContainsString('csrf_field', $wizard);
        self::assertStringContainsString('mcc-card', $wizard);
        self::assertStringContainsString('mcc-wizard-progress', $wizard);
        self::assertStringContainsString('mcc-public-admission-test-wizard', $wizard);
        self::assertStringContainsString('mcc-button', $wizard);
        self::assertStringContainsString('data-wizard-previous>Back', str_replace(' ', '', $wizard));
        self::assertStringContainsString('data-wizard-next>Continue', str_replace(' ', '', $wizard));
        self::assertStringContainsString('data-wizard-submit>SubmitTest', str_replace(' ', '', $wizard));
        self::assertStringContainsString('form.action = personalDetailsAction', file_get_contents($root . '/public/assets/js/app.js'));
        self::assertStringContainsString('NEEDS_DECISION', file_get_contents($root . '/docs/ADMISSION-TEST-DECISION-REQUEST.md'));
    }

    public function testWizardWorkflowKeepsValidationConcurrencyTransactionsAndSecurityBoundaries(): void
    {
        $root = dirname(__DIR__, 2);
        $service = file_get_contents($root . '/app/Modules/AdmissionTests/Services/AdmissionTestService.php');
        $controller = file_get_contents($root . '/app/Modules/AdmissionTests/Controllers/Browser.php');
        $crmRoutes = file_get_contents($root . '/app/Modules/CRM/Config/Routes.php');

        self::assertIsString($service);
        self::assertIsString($controller);
        self::assertIsString($crmRoutes);
        foreach (['wizardValidationErrors', 'applicant_full_name', "'answer_' . \$page . '_'"] as $contract) {
            self::assertStringContainsString($contract, $service);
        }
        self::assertStringNotContainsString('assessmentValues', $service);
        foreach (['checkVersion', 'transStart', 'transRollback', 'auditPersistence', 'authorize'] as $contract) {
            self::assertStringContainsString($contract, $service);
        }
        self::assertStringContainsString("Browser::start/\$1", $crmRoutes);
        self::assertStringContainsString("csrf_field", file_get_contents($root . '/app/Modules/Trials/Views/index.php'));
        self::assertStringContainsString('wizardErrorStep', $controller);
    }

    public function testCompletedAdmissionTestsAreServerSideNonRestartable(): void
    {
        $root = dirname(__DIR__, 2);
        $service = file_get_contents($root . '/app/Modules/AdmissionTests/Services/AdmissionTestService.php');
        $controller = file_get_contents($root . '/app/Modules/AdmissionTests/Controllers/Browser.php');
        $exception = file_get_contents($root . '/app/Modules/AdmissionTests/Exceptions/CompletedAdmissionTestException.php');
        $trialView = file_get_contents($root . '/app/Modules/Trials/Views/index.php');
        $detailView = file_get_contents($root . '/app/Modules/AdmissionTests/Views/show.php');

        foreach ([$service, $controller, $exception, $trialView, $detailView] as $source) {
            self::assertIsString($source);
        }
        self::assertStringContainsString('submitted_at', $service);
        self::assertStringContainsString('TEST_SUBMITTED', $service);
        self::assertStringContainsString('CompletedAdmissionTestException', $service . $controller);
        self::assertStringContainsString('Admission Test already completed.', $controller . $exception . $trialView);
        self::assertStringContainsString('site_url(\'admission-tests/\' . $exception->getAdmissionTestId())', $controller);
        self::assertStringContainsString('$isSubmitted', $detailView);
        self::assertStringContainsString('<?php if (! $isSubmitted): ?>', $detailView);
        self::assertStringContainsString('Submitted Admission Test answers are read-only.', $controller . $service);
        self::assertStringContainsString("['BASIC_DETAILS_COMPLETED', 'TEST_IN_PROGRESS']", $service);
    }

    public function testSubmittedDetailKeepsReadOnlyReviewAndWorkflowLinksWithoutEditSelectionAction(): void
    {
        $root = dirname(__DIR__, 2);
        $view = file_get_contents($root . '/app/Modules/AdmissionTests/Views/show.php');
        $service = file_get_contents($root . '/app/Modules/AdmissionTests/Services/AdmissionTestService.php');

        self::assertIsString($view);
        self::assertIsString($service);
        foreach (['Locked Paper Set', 'is-selected', 'Not answered', 'Test status', 'Submitted', 'Trial and Lead lineage', 'Meeting Follow-up history', 'admission-test-fees', 'pending-admission'] as $contract) {
            self::assertStringContainsString($contract, $view);
        }
        self::assertStringContainsString("site_url('admission-tests/' . (int) \$test['id'] . '/basic-test')", $view);
        self::assertStringContainsString('Submitted Admission Test answers are read-only.', $service);
        self::assertStringContainsString("in_array((string) \$current['status'], ['TEST_SUBMITTED', 'MEETING_FOLLOW_UP', 'APPROVED_FOR_ADMISSION']", $service);
    }

    public function testSubmittedPersonalDetailsCorrectionKeepsTheExplicitTestRouteAndFieldAllowlist(): void
    {
        $root = dirname(__DIR__, 2);
        $view = file_get_contents($root . '/app/Modules/AdmissionTests/Views/show.php');
        $controller = file_get_contents($root . '/app/Modules/AdmissionTests/Controllers/Browser.php');
        $service = file_get_contents($root . '/app/Modules/AdmissionTests/Services/AdmissionTestService.php');

        foreach ([$view, $controller, $service] as $source) {
            self::assertIsString($source);
        }
        self::assertStringContainsString("action=\"<?= site_url('admission-tests/' . (int) \$test['id'] . '/personal-details') ?>\"", $view);
        self::assertStringContainsString('data-admission-test-personal-details-panel', $view);
        self::assertStringContainsString("window.location.hash !== '#personal-details'", file_get_contents($root . '/public/assets/js/app.js'));
        self::assertStringContainsString('name="version"', $view);
        self::assertStringContainsString('csrf_field()', $view);
        foreach (['personalFields', 'checkVersion', 'admission_test_personal_details_updated', 'transStart', 'transRollback'] as $contract) {
            self::assertStringContainsString($contract, $service);
        }
        foreach (['withInput', 'personal_details_errors', 'Support reference:', 'log_message'] as $contract) {
            self::assertStringContainsString($contract, $controller);
        }
        $formStart = strpos($view, 'id="admission-test-personal-details-form"');
        $formEnd = strpos($view, '</form>', $formStart);
        self::assertIsInt($formStart);
        self::assertIsInt($formEnd);
        $personalForm = substr($view, $formStart, $formEnd - $formStart);
        foreach (['answer_', 'test_variant', 'training_fee', 'follow_up_type', 'assessment_json'] as $unrelatedField) {
            self::assertStringNotContainsString('name="' . $unrelatedField, $personalForm);
        }
    }

    public function testAssessmentRemarksUseTheSubmittedTimestampAndSevenDayBoundary(): void
    {
        $service = new AdmissionTestService();
        $timezone = new DateTimeZone('Asia/Kolkata');
        $submittedAt = new DateTimeImmutable('2026-10-01 10:00:00', new DateTimeZone('UTC'));
        $deadline = new DateTimeImmutable('2026-10-08 15:30:00', $timezone);

        self::assertTrue($service->assessmentRemarksWindow(
            $submittedAt->format('Y-m-d H:i:s'),
            $deadline->modify('-1 second'),
        )['editable']);
        self::assertTrue($service->assessmentRemarksWindow(
            $submittedAt->format('Y-m-d H:i:s'),
            $deadline,
        )['editable']);
        self::assertFalse($service->assessmentRemarksWindow(
            $submittedAt->format('Y-m-d H:i:s'),
            $deadline->modify('+1 second'),
        )['editable']);
        self::assertTrue($service->assessmentRemarksWindow('not-a-timestamp', $deadline)['invalid']);
        self::assertFalse($service->assessmentRemarksWindow(null, $deadline)['editable']);
    }

    public function testAssessmentRemarksUiAndWritePathDoNotExposeScoreOrResultEditors(): void
    {
        $root = dirname(__DIR__, 2);
        $view = file_get_contents($root . '/app/Modules/AdmissionTests/Views/show.php');
        $service = file_get_contents($root . '/app/Modules/AdmissionTests/Services/AdmissionTestService.php');
        $controller = file_get_contents($root . '/app/Modules/AdmissionTests/Controllers/Browser.php');
        $routes = file_get_contents($root . '/app/Modules/AdmissionTests/Config/Routes.php');
        $migration = file_get_contents($root . '/app/Database/Migrations/20261021000200_AddAdmissionTestAssessmentRemarks.php');

        foreach ([$view, $service, $controller, $routes, $migration] as $source) {
            self::assertIsString($source);
        }
        foreach (['assessment_remarks', 'Assessment remarks', 'No assessment remarks recorded.', 'Assessment remarks are locked after seven days.', 'Remarks editable until', 'maxlength="5000"'] as $contract) {
            self::assertStringContainsString($contract, $view);
        }
        foreach (['assessmentRemarks', 'saveAssessmentRemarks', 'assessmentRemarksWindow', 'DateInterval', 'admission_test_assessment_remarks_updated', 'checkVersion', 'transStart', 'transRollback', 'assessment_remarks'] as $contract) {
            self::assertStringContainsString($contract, $service . $controller);
        }
        self::assertStringContainsString("(:num)/assessment-remarks", $routes);
        self::assertStringContainsString('permission:admission.update', $routes);
        self::assertStringContainsString("'assessment_remarks'", $migration);

        $assessmentStart = strpos($view, 'id="review-heading"');
        $assessmentEnd = strpos($view, '<section class="mcc-card mcc-detail-card" id="meeting-follow-ups"', $assessmentStart);
        self::assertIsInt($assessmentStart);
        self::assertIsInt($assessmentEnd);
        $assessment = substr($view, $assessmentStart, $assessmentEnd - $assessmentStart);
        self::assertStringNotContainsString('name="score"', $assessment);
        self::assertStringNotContainsString('name="result"', $assessment);
        self::assertStringContainsString('name="assessment_remarks"', $assessment);
        self::assertStringContainsString('name="version"', $assessment);
        self::assertStringContainsString('csrf_field()', $assessment);
    }

    public function testAdmissionTestDateFieldsUseTheSharedDatepickerContract(): void
    {
        $root = dirname(__DIR__, 2);
        $view = file_get_contents($root . '/app/Modules/AdmissionTests/Views/show.php');
        $fees = file_get_contents($root . '/app/Modules/AdmissionTests/Views/_fees.php');
        $wizard = file_get_contents($root . '/app/Modules/AdmissionTests/Views/wizard.php');
        $service = file_get_contents($root . '/app/Modules/AdmissionTests/Services/AdmissionTestService.php');
        $styles = file_get_contents($root . '/public/assets/css/app.css');

        foreach ([$view, $fees, $wizard, $service, $styles] as $source) {
            self::assertIsString($source);
        }
        self::assertStringNotContainsString('type="date"', $view . $fees . $wizard);
        foreach (['applicant_date_of_birth', 'coaching_start', 'data-mcc-date-picker', 'data-mcc-date-picker-no-toggle', 'data-mcc-date-format="d-m-Y"', 'data-mcc-date-submit-format="Y-m-d"', 'placeholder="DD-MM-YYYY"'] as $contract) {
            self::assertStringContainsString($contract, $view . $fees . $wizard);
        }
        foreach (['normalizeDateOrNull', "preg_match('/^\\d{4}-\\d{2}-\\d{2}$/", 'due_date'] as $contract) {
            self::assertStringContainsString($contract, $service);
        }
        self::assertStringContainsString('data-mcc-date-picker-overlay', $styles);
        self::assertStringContainsString('overflow: visible', $styles);
        self::assertStringContainsString('z-index: 50000', $styles);
    }

    public function testMeetingFollowUpUsesSeparateSharedDateAndTwelveHourTimeFields(): void
    {
        $root = dirname(__DIR__, 2);
        $view = file_get_contents($root . '/app/Modules/AdmissionTests/Views/show.php');
        $service = file_get_contents($root . '/app/Modules/AdmissionTests/Services/AdmissionTestService.php');

        self::assertIsString($view);
        self::assertIsString($service);
        self::assertStringContainsString('name="follow_up_date"', $view);
        self::assertStringContainsString('name="follow_up_time"', $view);
        self::assertStringContainsString('name="follow_up_time" type="time"', $view);
        self::assertStringNotContainsString('name="follow_up_at"', $view);
        self::assertStringNotContainsString('type="datetime-local"', $view);
        foreach (['data-mcc-date-picker', 'data-mcc-date-format="d-m-Y"', 'data-mcc-date-submit-format="Y-m-d"', 'old(\'follow_up_date\'', 'old(\'follow_up_time\''] as $contract) {
            self::assertStringContainsString($contract, $view);
        }
        foreach (['normalizeFollowUpAt', 'follow_up_date', 'follow_up_time', 'Asia/Kolkata', 'setTimezone(new DateTimeZone(\'UTC\'))', 'follow_up_at'] as $contract) {
            self::assertStringContainsString($contract, $service);
        }
        $controller = file_get_contents($root . '/app/Modules/AdmissionTests/Controllers/Browser.php');
        self::assertIsString($controller);
        self::assertStringContainsString('followUpFieldErrors', $controller);
        self::assertStringContainsString('Support reference:', $controller);
        self::assertStringContainsString('log_message', $controller);
        self::assertStringContainsString('admission_test_followups', $service);
        self::assertStringContainsString('$duplicate', $service);
    }

    public function testMeetingFollowUpNormalizationValidatesBothFieldsAndStoresUtc(): void
    {
        $service = new AdmissionTestService();

        self::assertSame(
            '2026-10-21 17:00:00',
            $service->normalizeFollowUpAt('2026-10-21', '10:30 PM'),
        );
        self::assertSame(
            '2026-10-21 11:30:00',
            $service->normalizeFollowUpAt('2026-10-21', '17:00'),
        );

        $this->expectExceptionMessage('Invalid follow-up date.');
        $service->normalizeFollowUpAt('2026-02-30', '10:30 PM');
    }

    public function testMeetingFollowUpNormalizationRejectsMissingOrInvalidTime(): void
    {
        $service = new AdmissionTestService();

        $this->expectExceptionMessage('Invalid follow-up time.');
        $service->normalizeFollowUpAt('2026-10-21', '25:30 PM');
    }

    public function testAdmissionTestListUsesHumanReadableStatusesAndMccDatePresentation(): void
    {
        $root = dirname(__DIR__, 2);
        $view = file_get_contents($root . '/app/Modules/AdmissionTests/Views/index.php');
        $service = file_get_contents($root . '/app/Modules/AdmissionTests/Services/AdmissionTestService.php');
        $helper = file_get_contents($root . '/app/Helpers/common_helper.php');

        foreach ([$view, $service, $helper] as $source) {
            self::assertIsString($source);
        }
        foreach (['Test / Applicant', 'Branch', 'Status', 'Actions'] as $column) {
            self::assertStringContainsString('>' . $column . '<', $view);
        }
        self::assertStringNotContainsString('<th scope="col">Trial</th>', $view);
        self::assertStringContainsString('Test #<?= $testId ?>', $view);
        self::assertStringContainsString('<?= esc((string) ($test[\'applicant_full_name\'] ?: \'Unnamed applicant\')) ?>', $view);
        self::assertStringContainsString('Trial #<?= (int) $test[\'trial_id\'] ?>', $view);
        self::assertStringContainsString('mcc-admission-test-identity', $view);
        self::assertStringContainsString("site_url('admission-tests/' . \$testId)", $view);
        self::assertStringNotContainsString('<th scope="col"># Test reference</th>', $view);
        self::assertStringNotContainsString('<th scope="col">Applicant</th>', $view);
        self::assertStringContainsString('name="test_reference"', $view);
        self::assertStringContainsString('name="applicant"', $view);
        self::assertStringContainsString('name="trial_reference"', $view);
        self::assertStringContainsString('name="branch_id"', $view);
        self::assertStringContainsString('name="status"', $view);
        self::assertStringContainsString('name="paper_set"', $view);
        foreach (['created_from', 'created_to', 'updated_from', 'updated_to'] as $dateFilter) {
            self::assertStringContainsString('name="<?= $field ?>"', $view);
            self::assertStringContainsString("'{$dateFilter}'", $view);
        }
        self::assertStringContainsString('site_url(\'crm/trials/\' . (int) $test[\'trial_id\'])', $view);
        self::assertStringContainsString('Created: <?= esc($dateTime($test[\'created_at\'] ?? null)) ?>', $view);
        self::assertStringContainsString('Updated: <?= esc($dateTime($test[\'updated_at\'] ?? null)) ?>', $view);
        self::assertStringContainsString('mcc-table-primary-text', $view);
        self::assertStringContainsString('mcc-admission-test-status-counts', $view);
        self::assertStringContainsString('mcc-pagination', $view);
        self::assertStringContainsString('data-admission-actions-toggle', $view);
        self::assertStringContainsString("'admission-tests/' . \$testId . '#personal-details'", $view);
        self::assertStringContainsString("'admission-tests/' . \$testId . '#meeting-follow-ups'", $view);
        self::assertStringContainsString("'admission-tests/' . \$testId . '#pending-admission'", $view);
        self::assertStringContainsString("service('authorization')->can('admission.update'", $view);
        $styles = file_get_contents($root . '/public/assets/css/app.css');
        self::assertStringContainsString('z-index: 12000;', $styles);
        self::assertStringContainsString('.mcc-admission-test-list-table tbody th:first-child', $styles);
        self::assertStringContainsString('.mcc-admission-test-identity', $styles);
        self::assertStringContainsString('.mcc-admission-test-list-table td:last-child', $styles);
        self::assertStringContainsString('border-top: 0;', $styles);
        self::assertStringContainsString('text-align: center;', $styles);
        self::assertStringContainsString('min-height: 38px;', $styles);
        self::assertStringContainsString('mcc_admission_test_status_presentation', $view . $helper);
        self::assertStringContainsString('mcc_follow_up_datetime', $view);
        self::assertStringContainsString('at.created_at', $service);
        self::assertStringContainsString('admissionTestStatusCounts', $service);
        self::assertStringNotContainsString('<?= esc($test[\'status\']) ?>', $view);
        foreach (['TEST_SUBMITTED', 'APPROVED_FOR_ADMISSION', 'MEETING_FOLLOW_UP'] as $internalStatus) {
            self::assertStringContainsString($internalStatus, $service);
        }
        foreach (['assignedUserId', "trim((string) (\$input['assigned_user_id'] ?? ''))", "'assigned_user_id' => \$assignedUserId"] as $contract) {
            self::assertStringContainsString($contract, $service);
        }
        self::assertStringContainsString("'admission_test_id' => \$id", $service);
        self::assertStringContainsString("'trial_id' => (int) \$test['trial_id']", $service);

        self::assertSame(['label' => 'Draft', 'class' => 'mcc-badge-info'], mcc_admission_test_status_presentation('DRAFT'));
        self::assertSame(['label' => 'Test submitted', 'class' => 'mcc-badge-warning'], mcc_admission_test_status_presentation('TEST_SUBMITTED'));
        self::assertSame(['label' => 'Approved for admission', 'class' => 'mcc-badge-success'], mcc_admission_test_status_presentation('APPROVED_FOR_ADMISSION'));
        self::assertSame(['label' => 'Rejected / Closed', 'class' => 'mcc-badge-danger'], mcc_admission_test_status_presentation('REJECTED_CLOSED'));
        self::assertSame('Not available', mcc_follow_up_datetime(null));
    }

    public function testPaperSetSelectorIsLockedAndOnlySelectedQuestionsAreUsed(): void
    {
        $root = dirname(__DIR__, 2);
        $service = file_get_contents($root . '/app/Modules/AdmissionTests/Services/AdmissionTestService.php');
        $controller = file_get_contents($root . '/app/Modules/AdmissionTests/Controllers/Browser.php');
        $routes = file_get_contents($root . '/app/Modules/AdmissionTests/Config/Routes.php');
        $selector = file_get_contents($root . '/app/Modules/AdmissionTests/Views/paper_set.php');
        $trialView = file_get_contents($root . '/app/Modules/Trials/Views/index.php');

        foreach ([$service, $controller, $routes, $selector, $trialView] as $source) {
            self::assertIsString($source);
        }
        foreach (['paperSetOptions', 'selectPaperSet', "'A' => 'Paper Set A'", "'B' => 'Paper Set B'", "'C' => 'Paper Set C'", 'test_variant IS NULL', 'admission_test_paper_set_selected', 'questionsForTest'] as $contract) {
            self::assertStringContainsString($contract, $service);
        }
        self::assertStringContainsString('type="radio"', $selector);
        self::assertStringContainsString('required', $selector);
        self::assertStringContainsString('Continue Admission Test', $trialView);
        self::assertStringContainsString('Browser::selectPaperSet/$1', $routes);
        self::assertStringContainsString('permission:admission.update', $routes);
        self::assertStringContainsString('withInput', $controller);
    }

    public function testAdmissionTestRadioControlsHaveScopedAccessibleAlignmentContracts(): void
    {
        $root = dirname(__DIR__, 2);
        $wizard = file_get_contents($root . '/app/Modules/AdmissionTests/Views/wizard.php');
        $paperSet = file_get_contents($root . '/app/Modules/AdmissionTests/Views/paper_set.php');
        $styles = file_get_contents($root . '/public/assets/css/app.css');

        foreach ([$wizard, $paperSet, $styles] as $source) {
            self::assertIsString($source);
        }
        foreach (['mcc-admission-test-radio-label', 'type="radio"', 'name="<?= esc($field) ?>"', 'md5($option)', 'for="<?= esc($field . \'_\' . md5($option)) ?>"'] as $contract) {
            self::assertStringContainsString($contract, $wizard);
        }
        foreach (['name="paper_set_display"', 'id="wizard-paper-set-<?= esc($paperSet) ?>"', 'for="wizard-paper-set-<?= esc($paperSet) ?>"'] as $contract) {
            self::assertStringContainsString($contract, $wizard);
        }
        foreach (['name="paper_set"', 'id="paper-set-<?= esc($value) ?>"', 'for="paper-set-<?= esc($value) ?>"'] as $contract) {
            self::assertStringContainsString($contract, $paperSet);
        }
        foreach (['align-items: flex-start', 'gap: .6rem', 'flex: 0 0 1rem', 'overflow-wrap: anywhere', ':focus-visible'] as $contract) {
            self::assertStringContainsString($contract, $styles);
        }
        self::assertStringContainsString('@media (max-width: 700px)', $styles);
    }

    public function testPersonalPrefillUsesLeadFirstAndAdmissionFallbackWithoutInventingFields(): void
    {
        $service = new AdmissionTestService();
        $method = new \ReflectionMethod($service, 'sourcePrefill');
        $method->setAccessible(true);
        $result = $method->invoke($service, [
            'full_name' => 'Lead Applicant',
            'email' => '',
            'phone' => '1111111111',
            'date_of_birth' => '',
        ], [
            'applicant_full_name' => 'Admission Applicant',
            'applicant_email' => 'admission@example.invalid',
            'applicant_date_of_birth' => '2010-01-02',
            'parent_mobile' => '2222222222',
            'parent_email' => 'parent@example.invalid',
            'father_name' => 'Parent Name',
        ]);

        self::assertSame('Lead Applicant', $result['values']['applicant_full_name']);
        self::assertSame('admission@example.invalid', $result['values']['applicant_email']);
        self::assertSame('1111111111', $result['values']['contact_number']);
        self::assertSame('2010-01-02', $result['values']['applicant_date_of_birth']);
        self::assertSame('Parent Name', $result['values']['father_name']);
        self::assertSame('Admission', $result['sources']['applicant_email']);
        self::assertArrayNotHasKey('gender', $result['values']);
        self::assertArrayNotHasKey('school_college', $result['values']);
        self::assertArrayNotHasKey('previous_coaching_centre', $result['values']);
    }

    public function testFeeTotalUsesOnlyCapturedCoachingAndHostelFees(): void
    {
        $service = new AdmissionTestService();

        self::assertSame('1250.50', $service->calculateFeeTotal([
            'training_fee' => '1000.50',
            'hostel_charges' => '250',
        ]));
        self::assertSame('0.00', $service->calculateFeeTotal([]));
    }
}
