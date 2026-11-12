<?php

declare(strict_types=1);

namespace Tests\Database;

use PHPUnit\Framework\TestCase;

/** Contract coverage for portal visibility without mutating the persistent test database. */
final class AchievementPortalVisibilitySafeTest extends TestCase
{
    public function testPortalVisibilityUsesFinalPlayerAwardsAndBranchRelationship(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Services/PortalAuthService.php');

        self::assertIsString($service);
        self::assertStringContainsString("whereIn('aa.status', ['approved', 'published'])", $service);
        self::assertStringContainsString("where('aa.recipient_type', 'player')", $service);
        self::assertStringContainsString('whereIn(\'aa.player_id\', $playerIds)', $service);
        self::assertStringContainsString("where('aa.branch_id = p.branch_id', null, false)", $service);
        self::assertStringNotContainsString('nomination_note', $service);
        self::assertStringNotContainsString('lifecycle_reason', $service);
    }

    public function testPortalRoutesDoNotExposeStaffAchievementEndpoints(): void
    {
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Portal/Config/Routes.php');

        self::assertIsString($routes);
        self::assertStringContainsString("['filter' => 'portal']", $routes);
        self::assertStringNotContainsString('achievement.view', $routes);
        self::assertStringNotContainsString('achievement.update', $routes);
    }
}
