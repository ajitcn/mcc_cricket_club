<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AdmissionPlanRemovalContractTest extends TestCase
{
    public function testPlanRoutesNavigationAndApplicationReferencesAreRemoved(): void
    {
        $root = dirname(__DIR__, 2);
        $routes = (string) file_get_contents($root . '/app/Modules/Admissions/Config/Routes.php');
        $navigation = (string) file_get_contents($root . '/app/Services/NavigationService.php');
        $service = (string) file_get_contents($root . '/app/Services/AdmissionService.php');
        $masterService = (string) file_get_contents($root . '/app/Services/AdmissionMasterService.php');

        foreach (['coaching', 'fee', 'hostel', 'academic'] as $type) {
            self::assertStringNotContainsString('admission-masters/' . $type, $routes);
            self::assertStringNotContainsString('admission-masters/' . $type, $navigation);
            self::assertStringNotContainsString($type . '_plan_id', $service);
            self::assertStringNotContainsString('admission_' . $type . '_plans', $masterService);
        }
    }

    public function testForwardMigrationSnapshotsLabelsAndRemovesOnlyPlanReferences(): void
    {
        $root = dirname(__DIR__, 2);
        $migration = (string) file_get_contents(
            $root . '/app/Database/Migrations/20261014000100_RemoveAdmissionPlanMasters.php',
        );

        self::assertStringContainsString('snapshotHistoricalLabels', $migration);
        self::assertStringContainsString('assertNoUnexpectedForeignKeys', $migration);
        self::assertStringContainsString('fieldExists', $migration);
        self::assertStringContainsString('tableExists', $migration);
        self::assertStringContainsString('dropColumn', $migration);
        self::assertStringContainsString('dropTable', $migration);
        self::assertStringNotContainsString('truncate', strtolower($migration));
        self::assertStringNotContainsString('DELETE FROM', $migration);
        self::assertStringContainsString('Forward-only', $migration);
    }

    public function testHistoricalLabelFieldsRemainInAdmissionModelsAndViews(): void
    {
        $root = dirname(__DIR__, 2);
        $admissionModel = (string) file_get_contents(
            $root . '/app/Modules/Admissions/Models/AdmissionModel.php',
        );
        $periodModel = (string) file_get_contents(
            $root . '/app/Modules/Admissions/Models/AdmissionPeriodModel.php',
        );
        $detail = (string) file_get_contents(
            $root . '/app/Modules/Admissions/Views/show.php',
        );
        $renew = (string) file_get_contents(
            $root . '/app/Modules/Admissions/Views/renew.php',
        );

        foreach (['coaching_plan_label', 'fee_plan_label', 'hostel_plan_label', 'academic_plan_label'] as $field) {
            self::assertStringContainsString("'$field'", $admissionModel);
            self::assertStringContainsString("'$field'", $periodModel);
            self::assertStringContainsString($field, $detail);
            self::assertStringContainsString($field, $renew);
        }
    }
}
