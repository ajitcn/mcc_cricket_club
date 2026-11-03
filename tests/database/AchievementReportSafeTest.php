<?php

declare(strict_types=1);

namespace Tests\Database;

use PHPUnit\Framework\TestCase;

/** Contract coverage for the read-only published Achievement report/export slice. */
final class AchievementReportSafeTest extends TestCase
{
    public function testReportQueryIsPublishedAndBranchScoped(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Services/AchievementReportService.php');

        self::assertIsString($service);
        self::assertStringContainsString("where('aa.status', 'published')", $service);
        self::assertStringContainsString('whereIn(\'aa.branch_id\', $branches ?: [0])', $service);
        self::assertStringContainsString("where('aa.branch_id = b.id', null, false)", $service);
        self::assertStringContainsString('selectedBranches', $service);
        self::assertStringContainsString('achievement_report_exported', $service);
        self::assertStringNotContainsString('nomination_note', $service);
        self::assertStringNotContainsString('lifecycle_reason', $service);
    }

    public function testExportDoesNotRequireSchemaChangesOrDestructiveOperations(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Services/AchievementReportService.php');

        self::assertIsString($service);
        self::assertStringContainsString('fputcsv', $service);
        self::assertStringNotContainsString('delete(', $service);
        self::assertStringNotContainsString('truncate', strtolower($service));
        self::assertStringNotContainsString('dropTable', $service);
    }
}
