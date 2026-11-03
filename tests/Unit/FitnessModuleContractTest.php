<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class FitnessModuleContractTest extends TestCase
{
    public function testFitnessModuleHasExplicitRoutesAndSeparateOwnedTables(): void
    {
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Fitness/Config/Routes.php');
        $migration = file_get_contents(ROOTPATH . 'app/Database/Migrations/20260904000100_CreateFitnessManagement.php');

        self::assertIsString($routes);
        self::assertIsString($migration);
        self::assertStringContainsString('permission:fitness.view', $routes);
        self::assertStringContainsString('permission:fitness.create', $routes);
        self::assertStringContainsString('permission:fitness.plan.create', $routes);
        self::assertStringContainsString('fitness_assessments', $migration);
        self::assertStringContainsString('fitness_plans', $migration);
    }

    public function testFitnessServiceDerivesBmiFromStoredMeasurementsOnly(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Services/FitnessManagementService.php');

        self::assertIsString($service);
        self::assertStringContainsString('$weight / (($height / 100) ** 2)', $service);
        self::assertStringContainsString("'latest_bmi' => \$latestBmi", $service);
        self::assertStringContainsString("'bmi_change'", $service);
        self::assertStringNotContainsString('risk_threshold', $service);
    }
}
