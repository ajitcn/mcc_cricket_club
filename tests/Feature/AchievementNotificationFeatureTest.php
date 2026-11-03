<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

final class AchievementNotificationFeatureTest extends TestCase
{
    public function testLifecycleEventsDelegateToTheExistingInAppNotificationService(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Services/AchievementManagementService.php');
        $notification = file_get_contents(ROOTPATH . 'app/Services/NotificationService.php');

        self::assertIsString($service);
        self::assertIsString($notification);
        self::assertStringContainsString("if (in_array(\$to, ['published', 'revoked'], true))", $service);
        self::assertStringContainsString("service('notification')->notifyAchievementAward", $service);
        self::assertStringContainsString("'source_type' => \$event", $notification);
        self::assertStringContainsString("'source_id' => (string) \$awardId", $notification);
        self::assertStringContainsString('recipient_user_id', $notification);
        self::assertStringContainsString('read_at', $notification);
    }
}
