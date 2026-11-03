<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AchievementArchivalContractTest extends TestCase
{
    public function testArchivalIsForwardOnlyAndBranchPermissionControlled(): void
    {
        $migration = file_get_contents(ROOTPATH . 'app/Database/Migrations/20261007000200_AddAchievementArchival.php');
        $service = file_get_contents(ROOTPATH . 'app/Services/AchievementManagementService.php');
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Achievements/Config/Routes.php');
        $report = file_get_contents(ROOTPATH . 'app/Services/AchievementReportService.php');

        self::assertIsString($migration);
        self::assertIsString($service);
        self::assertIsString($routes);
        self::assertIsString($report);
        self::assertStringContainsString("'is_archived'", $migration);
        self::assertStringContainsString("'archived_by'", $migration);
        self::assertStringContainsString("'restored_by'", $migration);
        self::assertStringContainsString('achievement.archive', $migration);
        self::assertStringContainsString('achievement.restore', $migration);
        self::assertStringContainsString('archiveAward', $service);
        self::assertStringContainsString('restoreAward', $service);
        self::assertStringContainsString('achievement_award_archived', $service);
        self::assertStringContainsString('achievement_award_restored', $service);
        self::assertStringContainsString('auditPersistence', $service);
        self::assertStringContainsString('awards/(:num)/archive', $routes);
        self::assertStringContainsString('awards/(:num)/restore', $routes);
        self::assertStringContainsString('permission:achievement.archive', $routes);
        self::assertStringContainsString('permission:achievement.restore', $routes);
        self::assertStringContainsString("where('aa.is_archived', 0)", $report);
        foreach (
            ['is_archived', 'archived_by', 'archived_at', 'archive_reason', 'restored_by', 'restored_at', 'restore_reason']
            as $column
        ) {
            self::assertStringContainsString("'$column' => [", $migration);
        }
        self::assertStringContainsString('fieldExists($column, self::TABLE)', $migration);
        self::assertStringContainsString('private function indexExists', $migration);
        self::assertStringContainsString('private function foreignKeyExists', $migration);
        self::assertStringContainsString('achievement_awards_branch_archived_status', $migration);
        self::assertStringContainsString('achievement_awards_archived_at', $migration);
        self::assertStringContainsString('achievement_awards_archived_by_fk', $migration);
        self::assertStringContainsString('achievement_awards_restored_by_fk', $migration);
        self::assertStringContainsString('permissionExists', $migration);
        self::assertStringContainsString('processIndexes', $migration);
        self::assertStringNotContainsString('dropTable', $migration);
        self::assertStringNotContainsString('dropColumn', $migration);
        self::assertStringNotContainsString('truncate', strtolower($migration));
        self::assertStringNotContainsString('delete(', strtolower($service));
    }
}
