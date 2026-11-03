<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AchievementNotificationContractTest extends TestCase
{
    public function testAchievementNotificationsUseOnlyTheBoundedInAppWorkflow(): void
    {
        $notification = file_get_contents(ROOTPATH . 'app/Services/NotificationService.php');
        $achievement = file_get_contents(ROOTPATH . 'app/Services/AchievementManagementService.php');
        $migration = file_get_contents(ROOTPATH . 'app/Database/Migrations/20261007000100_CreateAchievementNotificationRecipients.php');

        self::assertIsString($notification);
        self::assertIsString($achievement);
        self::assertIsString($migration);
        self::assertStringContainsString('notifyAchievementAward', $notification);
        self::assertStringContainsString("['published', 'revoked']", $notification);
        self::assertStringContainsString('player_user_links', $notification);
        self::assertStringContainsString('parent_player_links', $notification);
        self::assertStringContainsString("'ppl.relationship_type' => 'guardian'", $notification);
        self::assertStringContainsString("'r.name' => 'Player'", $notification);
        self::assertStringContainsString("'r.name' => 'Parent'", $notification);
        self::assertStringContainsString("'category' => 'achievement'", $notification);
        self::assertStringNotContainsString('WhatsApp', $notification);
        self::assertStringNotContainsString('Firebase', $notification);
        self::assertStringNotContainsString('nomination_note', $notification);
        self::assertStringNotContainsString('lifecycle_reason', $notification);
        self::assertStringContainsString("notifyAchievementAward(\$id, \$to, \$db)", $achievement);
        self::assertStringContainsString("'notification.view'", $migration);
        self::assertStringContainsString("'notification.read'", $migration);
    }
}
