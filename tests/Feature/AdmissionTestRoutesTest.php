<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

final class AdmissionTestRoutesTest extends TestCase
{
    public function testTrialActionAndAdmissionTestRoutesUseExplicitControllersAndPermissions(): void
    {
        $root = dirname(__DIR__, 2);
        $crmRoutes = file_get_contents($root . '/app/Modules/CRM/Config/Routes.php');
        $routes = file_get_contents($root . '/app/Modules/AdmissionTests/Config/Routes.php');
        $controller = file_get_contents($root . '/app/Modules/AdmissionTests/Controllers/Browser.php');

        self::assertIsString($crmRoutes);
        self::assertIsString($routes);
        self::assertIsString($controller);
        self::assertStringContainsString("Browser::start/\$1", $crmRoutes);
        self::assertStringContainsString("permission:admission.create", $crmRoutes);
        self::assertStringContainsString("Browser::personalDetails/\$1", $routes);
        self::assertStringContainsString("Browser::personalDetailsPage/\$1", $routes);
        self::assertStringContainsString("Browser::basicTest/\$1", $routes);
        self::assertStringContainsString("Browser::wizard/\$1", $routes);
        self::assertStringContainsString("Browser::paperSet/\$1", $routes);
        self::assertStringContainsString("Browser::selectPaperSet/\$1", $routes);
        self::assertStringContainsString("Browser::wizardPersonalDetails/\$1", $routes);
        self::assertStringContainsString("Browser::wizardSubmit/\$1", $routes);
        self::assertStringContainsString("Browser::pendingAdmission/\$1", $routes);
        self::assertStringContainsString("Browser::fees/\$1", $routes);
        self::assertStringContainsString("Browser::assessmentRemarks/\$1", $routes);
        self::assertStringContainsString("permission:admission.update", $routes);
        self::assertStringContainsString("service('admissionTest')->createFromTrial", $controller);
        self::assertStringContainsString("site_url('admission-tests/' . \$id)", $controller);
        self::assertStringContainsString("service('admissionTest')->selectPaperSet", $controller);
        self::assertStringContainsString("service('admissionTest')->saveBasicTest", $controller);
        self::assertStringContainsString("site_url('admission-tests/' . \$id . '/wizard')", $controller);
        self::assertStringContainsString("service('admissionTest')->submitWizard", $controller);
        self::assertStringContainsString("service('admissionTest')->generateStudentLink", $controller);
        self::assertStringContainsString("admission-tests/student/(:segment)", $routes);
        self::assertStringContainsString("service('admissionTest')->savePersonalDetails", $controller);
        self::assertStringContainsString("service('admissionTest')->saveFees", $controller);
        self::assertStringContainsString("/wizard?step=2", $controller);
    }

    public function testAdmissionTestListKeepsBranchScopedServiceAndExistingViewActions(): void
    {
        $root = dirname(__DIR__, 2);
        $controller = file_get_contents($root . '/app/Modules/AdmissionTests/Controllers/Browser.php');
        $service = file_get_contents($root . '/app/Modules/AdmissionTests/Services/AdmissionTestService.php');
        $view = file_get_contents($root . '/app/Modules/AdmissionTests/Views/index.php');

        foreach ([$controller, $service, $view] as $source) {
            self::assertIsString($source);
        }
        self::assertStringContainsString('listForAuthorizedBranches', $controller);
        self::assertStringContainsString('authorizedBranchIds', $service);
        self::assertStringContainsString("site_url('crm/trials/' . (int) \$test['trial_id'])", $view);
        self::assertStringContainsString("site_url('admission-tests/' . \$testId)", $view);
        self::assertStringContainsString('mcc-admission-test-identity', $view);
        self::assertStringNotContainsString('<th scope="col">Trial</th>', $view);
        self::assertStringContainsString('mcc-table-wrap', $view);
    }
}
