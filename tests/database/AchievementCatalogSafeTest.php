<?php

declare(strict_types=1);

namespace Tests\Database;

use PHPUnit\Framework\TestCase;

/**
 * Read-only contract coverage for the insert-only Achievement catalog slice.
 * Runtime persistence coverage remains gated until the approved test migration
 * is applied; this test never writes or cleans the database.
 */
final class AchievementCatalogSafeTest extends TestCase
{
    public function testCatalogMigrationIsForwardOnlyAndBranchScoped(): void
    {
        $migration = file_get_contents(ROOTPATH . 'app/Database/Migrations/20261006000100_CreateAchievementCatalog.php');
        $service = file_get_contents(ROOTPATH . 'app/Services/AchievementManagementService.php');

        self::assertIsString($migration);
        self::assertIsString($service);
        self::assertStringContainsString("createTable('achievements', true)", $migration);
        self::assertStringContainsString("addForeignKey('branch_id', 'branches'", $migration);
        self::assertStringContainsString('Forward-only', $migration);
        self::assertStringContainsString("whereIn('a.branch_id', \$branchIds)", $service);
        self::assertStringContainsString("achievement.view", $service);
    }
}
