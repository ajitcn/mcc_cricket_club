<?php

declare(strict_types=1);

namespace Tests\Feature;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

final class AdmissionPlayerConnectivityRoutesTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    public function testConnectivityUsesExistingPermissionGatedBrowserRoutes(): void
    {
        $admissionRoutes = file_get_contents(APPPATH . 'Modules/Admissions/Config/Routes.php');
        $playerRoutes = file_get_contents(APPPATH . 'Modules/Players/Config/Routes.php');

        self::assertStringContainsString(
            "Browser::convert/\$1', ['filter' => 'permission:player.convert']",
            $admissionRoutes,
        );
        self::assertStringContainsString(
            "Browser::showPage/\$1', ['filter' => 'permission:player.view']",
            $playerRoutes,
        );
        self::assertStringContainsString(
            "Browser::showPage/\$1', ['filter' => 'permission:admission.view']",
            $admissionRoutes,
        );
    }
}
