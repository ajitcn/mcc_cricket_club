<?php

declare(strict_types=1);

namespace Tests\Database;

use PHPUnit\Framework\TestCase;

final class SchedulingGroupSessionSafeTest extends TestCase
{
    public function testExistingGroupSessionWorkflowKeepsBranchScopedRosterAndSnapshotRules(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Services/SchedulingService.php');
        $attendance = file_get_contents(ROOTPATH . 'app/Services/AttendanceService.php');

        self::assertIsString($service);
        self::assertIsString($attendance);
        self::assertStringContainsString("whereIn('p.branch_id', \$branchIds)", $service);
        self::assertStringContainsString("where('ba.is_active', 1)", $service);
        self::assertStringContainsString('attendance_roster_snapshots', $attendance);
        self::assertStringContainsString('attendance_roster_snapshot_members', $attendance);
        self::assertStringContainsString('transStart', $attendance);
    }
}
