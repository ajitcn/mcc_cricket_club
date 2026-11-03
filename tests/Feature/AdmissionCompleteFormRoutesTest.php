<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

final class AdmissionCompleteFormRoutesTest extends TestCase
{
    public function testCreateAndEditControllersLoadBranchScopedCoachOptions(): void
    {
        $root = dirname(__DIR__, 2);
        $controller = (string) file_get_contents($root . '/app/Modules/Admissions/Controllers/Browser.php');
        $service = (string) file_get_contents($root . '/app/Services/AdmissionService.php');

        self::assertStringContainsString("'coaches' => service('admission')->coachesForAuthorizedBranches()", $controller);
        self::assertStringContainsString("'coaches' => service('admission')->coachesForBranch", $controller);
        self::assertStringContainsString('authorizedBranchIds(true)', $service);
        self::assertStringContainsString("whereIn('r.name', ['Coach', 'Fitness Coach'])", $service);
        self::assertStringContainsString("join('coach_profiles cp', 'cp.user_id = u.id')", $service);
        self::assertStringContainsString("where('cp.is_active', 1)", $service);
        self::assertStringContainsString("'u.is_enabled' => 1", $service);
    }

    public function testAdmissionCoachOptionsUseBranchOwnershipAndEditOnlyHistoricalFallback(): void
    {
        $root = dirname(__DIR__, 2);
        $service = (string) file_get_contents($root . '/app/Services/AdmissionService.php');

        self::assertStringContainsString("'ub.branch_id' => ".'$branch', $service);
        self::assertStringContainsString('$historicalCoachIsValid = $coachId === $historicalId', $service);
        self::assertStringContainsString("throw new RuntimeException('Invalid admission Coach.')", $service);
        self::assertStringContainsString("->whereIn('ub.branch_id', ".'$branches)', $service);
    }

    public function testAdmissionFormsShareCoachMarkupAndSafeBranchFiltering(): void
    {
        $root = dirname(__DIR__, 2);
        $new = (string) file_get_contents($root . '/app/Modules/Admissions/Views/new.php');
        $edit = (string) file_get_contents($root . '/app/Modules/Admissions/Views/edit.php');
        $script = (string) file_get_contents($root . '/public/assets/js/app.js');
        $select = (string) file_get_contents($root . '/app/Views/components/admission-select.php');

        self::assertStringContainsString("'name' => 'coach_user_id'", $new);
        self::assertStringContainsString("'label' => 'Batch Coach'", $new);
        self::assertStringContainsString("'label' => 'Personal Coach'", $new);
        self::assertStringContainsString("'id' => 'admission-batch-coach'", $new);
        self::assertStringContainsString("'id' => 'admission-personal-coach'", $new);
        self::assertSame(2, substr_count($new, "'forceBtSelect' => true"));
        self::assertStringNotContainsString('name="admission_date"', $new);
        self::assertStringNotContainsString('id="admission-date"', $new);
        self::assertStringContainsString('name="start_date"', $new);
        self::assertStringContainsString('name="end_date"', $new);
        self::assertStringContainsString("view('App\\\\Modules\\\\Admissions\\\\Views\\\\new', get_defined_vars())", $edit);
        self::assertStringNotContainsString('name="admission_date"', $edit);

        self::assertStringContainsString("'coach_user_id' => 'Choose coach'", $select);
        self::assertStringContainsString("'personal_coach_user_id' => 'Choose coach'", $select);
        self::assertStringContainsString("String(option.getAttribute('data-branch-id') || '').trim()", $script);
        self::assertStringContainsString("select.value = '';", $script);
        self::assertStringContainsString("select.selectedIndex = -1;", $script);
        self::assertStringContainsString("label.classList.remove('is-selected', 'selected', 'checked');", $script);
        self::assertStringContainsString("input.removeAttribute('aria-checked');", $script);
        self::assertStringContainsString("input.setAttribute('aria-checked', 'true');", $script);
        self::assertStringContainsString("options.querySelectorAll('.mcc-bt-select-option')", $script);
        self::assertStringContainsString("#admission-batch-coach, #admission-personal-coach", $script);
        self::assertStringContainsString("select.dispatchEvent(new Event('bt-select-refresh'))", $script);
    }

    public function testAdmissionCoachContractIsBranchOnlyAcrossBatches(): void
    {
        $root = dirname(__DIR__, 2);
        $service = (string) file_get_contents($root . '/app/Services/AdmissionService.php');
        $script = (string) file_get_contents($root . '/public/assets/js/app.js');
        $new = (string) file_get_contents($root . '/app/Modules/Admissions/Views/new.php');
        $edit = (string) file_get_contents($root . '/app/Modules/Admissions/Views/edit.php');

        $authorizedCoachQuery = substr(
            $service,
            strpos($service, 'public function coachesForAuthorizedBranches(): array'),
            strpos($service, 'public function listForAuthorizedBranches(array $filters = []): array')
                - strpos($service, 'public function coachesForAuthorizedBranches(): array'),
        );
        $branchCoachQuery = substr(
            $service,
            strpos($service, 'public function coachesForBranch('),
            strpos($service, 'public function listForAuthorizedBranches(array $filters = []): array')
                - strpos($service, 'public function coachesForBranch(int $branch, ?int $selectedCoachId = null): array'),
        );
        $branchFilterScript = substr(
            $script,
            strpos($script, 'function initAdmissionBranchScopedFields()'),
            strpos($script, 'function initAdmissionTrialRatings()')
                - strpos($script, 'function initAdmissionBranchScopedFields()'),
        );

        self::assertNotFalse($authorizedCoachQuery);
        self::assertNotFalse($branchCoachQuery);
        self::assertNotFalse($branchFilterScript);
        self::assertStringNotContainsString('batch_id', $authorizedCoachQuery);
        self::assertStringNotContainsString('batch_id', substr($branchCoachQuery, 0, strpos($branchCoachQuery, 'return $coaches;')));
        self::assertStringNotContainsString('program', $authorizedCoachQuery);
        self::assertStringNotContainsString('program', $branchCoachQuery);
        self::assertStringNotContainsString("form.querySelector('#batch')", $branchFilterScript);
        self::assertStringNotContainsString('program', $branchFilterScript);
        self::assertStringNotContainsString('data-batch', $new . $edit);
        self::assertStringNotContainsString('data-program', $new . $edit);
        self::assertStringNotContainsString('data-admission-coach-branch-filter', $new);
        self::assertStringContainsString("'name' => 'coach_user_id'", $new);
        self::assertStringContainsString("'name' => 'personal_coach_user_id'", $new);
        self::assertStringContainsString("'name' => 'personal_coach_user_id'", $edit);
    }

    public function testAdmissionCoachValidationDoesNotReadBatchData(): void
    {
        $root = dirname(__DIR__, 2);
        $service = (string) file_get_contents($root . '/app/Services/AdmissionService.php');
        $validation = substr(
            $service,
            strpos($service, 'private function validateExtendedFields('),
            strpos($service, 'private function normalizeInstallmentRows(')
                - strpos($service, 'private function validateExtendedFields('),
        );

        self::assertNotFalse($validation);
        self::assertStringContainsString('coach_user_id', $validation);
        self::assertStringContainsString('personal_coach_user_id', $validation);
        self::assertStringContainsString('branch_id', $validation);
        self::assertStringNotContainsString('batch_id', $validation);
        self::assertStringNotContainsString('player_batch_assignments', $validation);
    }

    public function testCompletedAdmissionEditUsesCoachOnlyCorrectionContract(): void
    {
        $root = dirname(__DIR__, 2);
        $service = (string) file_get_contents($root . '/app/Services/AdmissionService.php');
        $view = (string) file_get_contents($root . '/app/Modules/Admissions/Views/new.php');
        $controller = (string) file_get_contents($root . '/app/Modules/Admissions/Controllers/Browser.php');

        self::assertStringContainsString(
            "[self::APPROVED, self::COMPLETED, self::CONVERTED]",
            $service,
        );
        self::assertStringContainsString('updateCompletedCoachAssignments', $service);
        self::assertStringContainsString('Correction reason is required for completed Admission coach changes.', $service);
        self::assertStringContainsString("'admission_completed_coaches_corrected'", $service);
        self::assertStringContainsString("'correction_reason'", $view);
        self::assertStringContainsString('Save Coach Assignments', $view);
        self::assertStringContainsString('withInput()', $controller);
    }

    public function testFocusedCoachCorrectionRouteUsesDedicatedFormAndExistingPermission(): void
    {
        $root = dirname(__DIR__, 2);
        $routes = (string) file_get_contents($root . '/app/Modules/Admissions/Config/Routes.php');
        $controller = (string) file_get_contents($root . '/app/Modules/Admissions/Controllers/Browser.php');
        $view = (string) file_get_contents($root . '/app/Modules/Admissions/Views/update_coaches.php');
        $service = (string) file_get_contents($root . '/app/Services/AdmissionService.php');
        $script = (string) file_get_contents($root . '/public/assets/js/app.js');

        self::assertStringContainsString("'(:num)/update-coaches'", $routes);
        self::assertStringContainsString('permission:admission.update', $routes);
        self::assertStringContainsString('updateCoachesPage', $controller);
        self::assertStringContainsString('updateCoaches', $controller);
        self::assertStringContainsString('coachCorrectionForm', $service);
        self::assertStringContainsString('updateCoachAssignments', $service);
        self::assertStringContainsString("name' => 'batch_coach_id'", $view);
        self::assertStringContainsString("name' => 'personal_coach_id'", $view);
        self::assertStringContainsString('name="correction_reason"', $view);
        self::assertStringContainsString('name="expected_version"', $view);
        self::assertStringContainsString("'/update-coaches'", $view);
        self::assertStringNotContainsString("'/edit'", $view);
        self::assertStringNotContainsString('name="coach_user_id"', $view);
        self::assertStringNotContainsString('name="personal_coach_user_id"', $view);
        self::assertStringNotContainsString('name="status"', $view);
        self::assertStringNotContainsString('name="admission_number"', $view);
        self::assertStringNotContainsString('update-coaches', $script);
        self::assertStringNotContainsString("input.name = controlId + '-option'", $script);
    }

    public function testCompletedAdmissionCoachCorrectionHasAStrictServerFieldAllowlist(): void
    {
        $service = (string) file_get_contents(
            dirname(__DIR__, 2) . '/app/Services/AdmissionService.php',
        );

        $helperStart = strpos($service, 'private function updateCompletedCoachAssignments(');
        $helperEnd = strpos($service, 'private function nullableCoachId(', $helperStart);
        self::assertNotFalse($helperStart);
        self::assertNotFalse($helperEnd);

        $helper = substr($service, $helperStart, $helperEnd - $helperStart);
        $allowlistStart = strpos($helper, '$allowedFields = [');
        $allowlistEnd = strpos($helper, '];', $allowlistStart);
        self::assertNotFalse($allowlistStart);
        self::assertNotFalse($allowlistEnd);
        $allowlist = substr($helper, $allowlistStart, $allowlistEnd - $allowlistStart);
        foreach (['coach_user_id', 'personal_coach_user_id', 'correction_reason', 'expected_version'] as $field) {
            self::assertStringContainsString("'" . $field . "'", $allowlist);
        }
        foreach (['status', 'admission_number', 'branch_id', 'batch_id', 'training_fee', 'installment_plan', 'signature_student'] as $field) {
            self::assertStringNotContainsString("'" . $field . "'", $allowlist);
        }
    }
}
