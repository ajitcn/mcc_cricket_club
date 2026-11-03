<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

final class DocumentManagementRoutesTest extends TestCase
{
    public function testVerificationRouteAndControllerPreserveSecurityBoundaries(): void
    {
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Documents/Config/Routes.php');
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Documents/Controllers/Browser.php');
        $view = file_get_contents(ROOTPATH . 'app/Modules/Documents/Views/show.php');

        self::assertIsString($routes);
        self::assertIsString($controller);
        self::assertIsString($view);
        self::assertStringContainsString("(:num)/verify", $routes);
        self::assertStringContainsString('permission:document.upload', $routes);
        self::assertStringContainsString("service('document')->verify", $controller);
        self::assertStringContainsString('csrf_field()', $view);
        self::assertStringContainsString('PENDING', $view);
        self::assertStringContainsString('VERIFIED', $view);
        self::assertStringContainsString('REJECTED', $view);
    }

    public function testMedicalDocumentsRequireAnExplicitPermission(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Services/DocumentManagementService.php');

        self::assertIsString($service);
        self::assertStringContainsString("document.medical.view", $service);
        self::assertStringContainsString("document_type'] === 'medical_certificate'", $service);
    }
}
