<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class GroundManagementContractTest extends TestCase
{
    public function testGroundModuleUsesExplicitPermissionsAndSessionGroundReference(): void
    {
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Grounds/Config/Routes.php');
        $migration = file_get_contents(ROOTPATH . 'app/Database/Migrations/20260904000200_CreateGroundManagement.php');
        $scheduling = file_get_contents(ROOTPATH . 'app/Services/SchedulingService.php');

        self::assertIsString($routes);
        self::assertIsString($migration);
        self::assertIsString($scheduling);
        self::assertStringContainsString('permission:ground.view', $routes);
        self::assertStringContainsString('permission:ground.maintenance', $routes);
        self::assertStringContainsString('ground_unavailability', $migration);
        self::assertStringContainsString('ensureBookable', $scheduling);
        $groundService = file_get_contents(ROOTPATH . 'app/Services/GroundManagementService.php');
        self::assertIsString($groundService);
        self::assertStringContainsString('GROUNDS_PER_PAGE', $groundService);
        self::assertStringContainsString("orderBy('g.id', 'DESC')", $groundService);
        self::assertStringContainsString('data-ground-submit-feedback', file_get_contents(ROOTPATH . 'app/Modules/Grounds/Views/form.php'));
        self::assertStringContainsString('audit_history', file_get_contents(ROOTPATH . 'app/Modules/Grounds/Views/show.php'));
        self::assertStringContainsString("ground.update", $routes);
        self::assertStringContainsString('use Throwable;', $groundService);
    }
}
