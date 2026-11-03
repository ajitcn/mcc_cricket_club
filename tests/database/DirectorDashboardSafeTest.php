<?php

declare(strict_types=1);

namespace Tests\Database;

use PHPUnit\Framework\TestCase;

final class DirectorDashboardSafeTest extends TestCase
{
    public function testDashboardQueriesUseBranchAndStatusBoundaries(): void
    {
        $model = file_get_contents(ROOTPATH . 'app/Models/DashboardModel.php');

        self::assertIsString($model);
        self::assertStringContainsString('whereIn(\'branch_id\'', $model);
        self::assertStringContainsString('whereIn(\'ub.branch_id\'', $model);
        self::assertStringContainsString("where('u.is_enabled', 1)", $model);
        self::assertStringContainsString("whereIn('r.name', ['Coach', 'Fitness Coach'])", $model);
        self::assertStringContainsString("whereNotIn(\$alias . '.status', ['cancelled', 'completed'])", $model);
        self::assertStringContainsString("where('s.status', 'scheduled')", $model);
        self::assertStringContainsString("where('s.starts_at >=', \$fromUtc)", $model);
        self::assertStringContainsString("where('s.starts_at <', \$periodEndUtc)", $model);
        self::assertStringContainsString('countUpcomingSessionsForPeriod', $model);
        self::assertStringContainsString('countGroupSessionsForPeriod', $model);
        self::assertStringContainsString('countPersonalSessionsForPeriod', $model);
        self::assertStringContainsString('branchDistribution', $model);
        self::assertStringContainsString("where('status', 'active')", $model);
        self::assertStringContainsString("whereIn('s.status', ['scheduled', 'active', 'completed'])", $model);
        self::assertStringContainsString("where('p.branch_id = s.branch_id', null, false)", $model);
    }
}
