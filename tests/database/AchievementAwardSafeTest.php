<?php

declare(strict_types=1);

namespace Tests\Database;

use PHPUnit\Framework\TestCase;

/** Read-only contract coverage; no cleanup or database writes are performed. */
final class AchievementAwardSafeTest extends TestCase
{
    public function testDraftAwardMigrationIsBranchScopedAndForwardOnly(): void
    {
        $migration = file_get_contents(ROOTPATH . 'app/Database/Migrations/20261006000200_CreateAchievementAwardDrafts.php');
        $service = file_get_contents(ROOTPATH . 'app/Services/AchievementManagementService.php');

        self::assertIsString($migration);
        self::assertIsString($service);
        self::assertStringContainsString("createTable('achievement_awards', true)", $migration);
        self::assertStringContainsString('Forward-only', $migration);
        self::assertStringContainsString("'branch_id' => \$branchId", $service);
        self::assertStringContainsString("'status' => 'active'", $service);
        self::assertStringContainsString('chk_achievement_award_recipient', $migration);
        self::assertStringContainsString("addForeignKey('achievement_id', 'achievements'", $migration);
        self::assertStringContainsString("addForeignKey('branch_id', 'branches'", $migration);
        self::assertStringContainsString("addForeignKey('player_id', 'players'", $migration);
        self::assertStringContainsString("addForeignKey('coach_id', 'users'", $migration);
        self::assertStringContainsString("addKey(['branch_id', 'status'])", $migration);
        self::assertStringContainsString("addKey(['achievement_id', 'status'])", $migration);
        self::assertStringContainsString("addKey(['player_id', 'status'])", $migration);
        self::assertStringContainsString("addKey(['coach_id', 'status'])", $migration);
        self::assertStringContainsString("transBegin()", $service);
        self::assertStringContainsString('auditPersistence', $service);
        self::assertStringNotContainsString('dropTable', $migration);
        self::assertStringNotContainsString('truncate', strtolower($migration));
        self::assertStringNotContainsString('delete from', strtolower($migration));
    }

    public function testLifecycleMigrationAddsAuditFieldsAndPermissionsWithoutDestructiveOperations(): void
    {
        $migration = file_get_contents(ROOTPATH . 'app/Database/Migrations/20261006000300_AddAchievementApprovalLifecycle.php');
        $service = file_get_contents(ROOTPATH . 'app/Services/AchievementManagementService.php');

        self::assertIsString($migration);
        self::assertIsString($service);
        self::assertStringContainsString("addColumn('achievement_awards'", $migration);
        self::assertStringContainsString("'version'", $migration);
        self::assertStringContainsString("'approved_by'", $migration);
        self::assertStringContainsString("'published_by'", $migration);
        self::assertStringContainsString("'revoked_by'", $migration);
        self::assertStringContainsString('achievement.approve', $migration);
        self::assertStringContainsString('achievement.publish', $migration);
        self::assertStringContainsString('achievement.revoke', $migration);
        self::assertStringContainsString('Forward-only', $migration);
        self::assertStringNotContainsString('dropTable', $migration);
        self::assertStringNotContainsString('truncate', strtolower($migration));
        self::assertStringContainsString('updateWithVersion', $service);
        self::assertStringContainsString('lifecycle_reason', $service);
        self::assertStringContainsString('auditPersistence', $service);
    }

    public function testCertificateMigrationIsPrivateBranchScopedAndForwardOnly(): void
    {
        $migration = file_get_contents(ROOTPATH . 'app/Database/Migrations/20261006000400_CreateAchievementCertificates.php');
        $service = file_get_contents(ROOTPATH . 'app/Services/AchievementCertificateService.php');

        self::assertIsString($migration);
        self::assertIsString($service);
        self::assertStringContainsString("createTable('achievement_certificates', true)", $migration);
        self::assertStringContainsString("addUniqueKey('award_id')", $migration);
        self::assertStringContainsString("addForeignKey('award_id', 'achievement_awards'", $migration);
        self::assertStringContainsString("addForeignKey('branch_id', 'branches'", $migration);
        self::assertStringContainsString("'achievement.certificate.generate'", $migration);
        self::assertStringContainsString("'achievement.certificate.download'", $migration);
        self::assertStringContainsString('LocalPrivateStorage', $service);
        self::assertStringContainsString('achievement-certificates/', $service);
        self::assertStringNotContainsString('dropTable', $migration);
        self::assertStringNotContainsString('truncate', strtolower($migration));
    }
}
