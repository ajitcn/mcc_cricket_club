<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AuthorizationRestoreSeederContractTest extends TestCase
{
    public function testAuthorizationRestoreIsTestDatabaseScopedAndIdempotent(): void
    {
        $seeder = file_get_contents(
            ROOTPATH . 'app/Database/Seeds/AuthorizationRestoreSeeder.php',
        );

        self::assertIsString($seeder);
        self::assertStringContainsString(
            "if (\$database !== 'mcc_cricket_club_test')",
            $seeder,
        );
        self::assertStringContainsString('$db->transBegin();', $seeder);
        self::assertStringContainsString("->where([", $seeder);
        self::assertStringContainsString("->insert([", $seeder);
        self::assertStringNotContainsString('truncate', strtolower($seeder));
        self::assertStringNotContainsString('->delete(', $seeder);
    }

    public function testRestoreDefinitionContainsCurrentNavigationPermissionGrants(): void
    {
        $seeder = file_get_contents(
            ROOTPATH . 'app/Database/Seeds/AuthorizationRestoreSeeder.php',
        );

        self::assertIsString($seeder);

        foreach ([
            'player.goal.set',
            'player.session_plan.view',
            'player.feedback.view',
            'coach.view',
            'program.view',
            'performance.view',
            'achievement.view',
            'opponent.view',
            'report.view',
            'notification.view',
        ] as $permission) {
            self::assertStringContainsString("'{$permission}'", $seeder);
        }
    }
}
