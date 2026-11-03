<?php

declare (strict_types=1);
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
final class TrialModuleContractTest extends TestCase
{
    private string $root;
    protected function setUp(): void
    {
        parent::setUp();
        $this->root = dirname(__DIR__, 2);
    }
    public function testTrialDetailsHasOneCanonicalBrowserRoute(): void
    {
        $crmRoutes = file_get_contents($this->root . '/app/Modules/CRM/Config/Routes.php');
        $trialRoutes = file_get_contents($this->root . '/app/Modules/Trials/Config/Routes.php');
        self::assertIsString($crmRoutes);
        self::assertIsString($trialRoutes);
        self::assertStringContainsString("\$routes->get('trials/(:num)'", $crmRoutes);
        self::assertStringNotContainsString("\$routes->get('trials/(:num)'", $trialRoutes);
        self::assertStringContainsString("Browser::show/\$1", $crmRoutes);
    }
    public function testTrialBrowserUsesTheSharedMvcLayoutAndService(): void
    {
        $controller = file_get_contents($this->root . '/app/Modules/Trials/Controllers/Browser.php');
        $view = file_get_contents($this->root . '/app/Modules/Trials/Views/show.php');
        self::assertIsString($controller);
        self::assertIsString($view);
        self::assertStringContainsString("extends StaffController", $controller);
        self::assertStringContainsString("service('trial')->details", $controller);
        self::assertStringContainsString("view('staff_header'", $view);
        self::assertStringContainsString("view('staff_footer')", $view);
        self::assertSame(substr_count($view, '<section'), substr_count($view, '</section>'));
    }
    public function testTrialWorkflowKeepsAuthorizationHistoryAuditAndDuplicateGuardInService(): void
    {
        $service = file_get_contents($this->root . '/app/Services/TrialService.php');
        self::assertIsString($service);
        self::assertStringContainsString("service('authorization')->can(\$permission", $service);
        self::assertStringContainsString('whereIn(\'trials.branch_id\', $branches)', $service);
        self::assertStringContainsString('futureBookingExists', $service);
        self::assertStringContainsString("\$trial['selection_outcome'] !== 'pending'", $service);
        self::assertStringContainsString("\$this->history(\$db", $service);
        self::assertStringContainsString("\$this->audit(\$db", $service);
        self::assertStringContainsString('FOR UPDATE', $service);
    }

    public function testTrialListProjectsAdmissionTestCompletionAndKeepsTheActionBranchScoped(): void
    {
        $service = file_get_contents($this->root . '/app/Services/TrialService.php');
        $view = file_get_contents($this->root . '/app/Modules/Trials/Views/index.php');

        self::assertIsString($service);
        self::assertIsString($view);
        foreach (['admission_test_id', 'admission_test_status', 'admission_test_submitted_at', 'admission_test_completed'] as $field) {
            self::assertStringContainsString($field, $service);
        }
        self::assertStringContainsString('Test Completed', $view);
        self::assertStringContainsString('View completed Admission Test', $view);
        self::assertStringContainsString("'crm/trials/' . (int) \$trial['id'] . '/admission-test'", $view);
        self::assertStringContainsString("'admission-tests/' . (int) \$trial['admission_test_id']", $view);
    }

    public function testTrialDetailsDoesNotRenderPendingAdmissionConversionAction(): void
    {
        $view = file_get_contents($this->root . '/app/Modules/Trials/Views/show.php');

        self::assertIsString($view);
        self::assertStringNotContainsString('Move to Admission Pending', $view);
        self::assertStringNotContainsString("/admission-pending'", $view);
        self::assertStringNotContainsString("'admission_pending'", $view);
        self::assertStringNotContainsString('Admission pending', $view);
    }
    public function testInternalTrialDetailsLinksUseCanonicalCrmPrefix(): void
    {
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root . '/app'));
        foreach ($files as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $contents = file_get_contents($file->getPathname());
            self::assertIsString($contents);
            self::assertDoesNotMatchRegularExpression("~(?:site_url|url_to)\\(\\s*['\"]trials/~", $contents, $file->getPathname());
        }
    }

    public function testTrialFormsUseBranchScopedDropdownsAndPreserveSubmittedValues(): void
    {
        $controller = file_get_contents($this->root . '/app/Modules/Trials/Controllers/Browser.php');
        $detail = file_get_contents($this->root . '/app/Modules/Trials/Views/show.php');
        $lead = file_get_contents($this->root . '/app/Modules/CRM/Views/show.php');
        $booking = file_get_contents($this->root . '/app/Modules/Trials/Views/book.php');

        self::assertIsString($controller);
        self::assertIsString($detail);
        self::assertIsString($lead);
        self::assertIsString($booking);
        self::assertStringContainsString("availableLocations(", $controller);
        self::assertStringContainsString('id="reschedule-location" name="location"', $detail);
        self::assertStringNotContainsString('id="reschedule-location" name="location" maxlength="255"', $detail);
        self::assertStringContainsString("old('location', (string) (\$trial['location'] ?? ''))", $detail);
        self::assertStringContainsString("old('trial_date')", $lead);
        self::assertStringContainsString("old('trial_time')", $lead);
        self::assertStringContainsString('id="trial-location" name="location"', $booking);
        self::assertStringNotContainsString('name="location" maxlength="255"', $booking);
    }

    public function testTrialSubmissionKeepsBranchAndOptionValidationOnReschedule(): void
    {
        $controller = file_get_contents($this->root . '/app/Modules/Trials/Controllers/Browser.php');
        $service = file_get_contents($this->root . '/app/Services/TrialService.php');

        self::assertIsString($controller);
        self::assertIsString($service);
        self::assertStringContainsString('redirectWithTrialError', $controller);
        self::assertStringContainsString("with('field_errors'", $controller);
        self::assertStringContainsString("\$this->validatedSchedule(\$input, (int) \$old['branch_id'])", $service);
        self::assertStringContainsString("'branch_id' => \$branch, 'is_active' => 1, 'name' => \$location", $service);
        self::assertStringContainsString("'ub.branch_id' => \$branch, 'u.is_enabled' => 1", $service);
        self::assertStringContainsString("config('App')->appTimezone", $service);
    }

    public function testSecondaryCoachContractUsesTheExistingTrialWorkflow(): void
    {
        $migration = file_get_contents($this->root . '/app/Database/Migrations/20261019000100_AddTrialSecondaryCoach.php');
        $service = file_get_contents($this->root . '/app/Services/TrialService.php');
        $media = file_get_contents($this->root . '/app/Services/TrialMediaService.php');
        $routes = file_get_contents($this->root . '/app/Modules/CRM/Config/Routes.php');
        $browser = file_get_contents($this->root . '/app/Modules/Trials/Controllers/Browser.php');
        $booking = file_get_contents($this->root . '/app/Modules/Trials/Views/book.php');
        $detail = file_get_contents($this->root . '/app/Modules/Trials/Views/show.php');
        $admission = file_get_contents($this->root . '/app/Modules/Admissions/Views/show.php');

        foreach ([$migration, $service, $media, $routes, $browser, $booking, $detail, $admission] as $source) {
            self::assertIsString($source);
        }

        self::assertStringContainsString('secondary_coach_user_id', $migration);
        self::assertStringContainsString("whereIn('r.name', ['Coach', 'Fitness Coach'])", $service);
        self::assertStringContainsString('Primary and secondary coaches must be different.', $service);
        self::assertStringContainsString('updateSecondaryCoach', $service);
        self::assertStringContainsString('authorizeSecondaryCoachChange', $service);
        self::assertStringContainsString('canViewAllTrials', $service);
        self::assertStringContainsString('canReviewTrial', $service);
        self::assertStringContainsString('secondary_coach_user_id', $media);
        self::assertStringContainsString('authorizeTrialReview', $media);
        self::assertStringContainsString('authorizeTrialMediaUpload', $media);
        self::assertStringContainsString("trials/(:num)/coaches", $routes);
        self::assertStringContainsString('Browser::coaches/$1', $routes);
        self::assertStringContainsString("service('trial')->updateSecondaryCoach", $browser);
        self::assertStringContainsString('Primary Coach / Evaluator', $booking);
        self::assertStringContainsString('mcc-muted">(optional)</span>', $booking);
        self::assertStringContainsString('name="secondary_coach_user_id"', $booking);
        self::assertStringContainsString('name="secondary_coach_user_id"', $detail);
        self::assertStringContainsString('Secondary Coach / Evaluator', $admission);
    }

    public function testSecondaryCoachFormsUseExplicitTrialRoutesAndCsrf(): void
    {
        $detail = file_get_contents($this->root . '/app/Modules/Trials/Views/show.php');
        $booking = file_get_contents($this->root . '/app/Modules/CRM/Views/show.php');

        self::assertIsString($detail);
        self::assertIsString($booking);
        self::assertStringContainsString("site_url('crm/trials/' . (int) \$trial['id'] . '/coaches')", $detail);
        self::assertStringContainsString('name="expected_updated_at"', $detail);
        self::assertStringContainsString('<?= csrf_field() ?>', $detail);
        self::assertStringContainsString('name="secondary_coach_user_id"', $booking);
        self::assertStringContainsString('availableCoaches', file_get_contents($this->root . '/app/Modules/Trials/Controllers/Browser.php'));
    }
}
