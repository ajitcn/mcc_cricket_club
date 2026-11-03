<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class NotificationContractTest extends TestCase
{
    public function testNotificationRoutesArePermissionControlled(): void
    {
        $routes = file_get_contents(APPPATH . 'Modules/Notifications/Config/Routes.php');
        $service = file_get_contents(APPPATH . 'Services/NotificationService.php');
        $controller = file_get_contents(APPPATH . 'Modules/Notifications/Controllers/Browser.php');
        $view = file_get_contents(APPPATH . 'Modules/Notifications/Views/index.php');

        self::assertStringContainsString('permission:notification.view', $routes);
        self::assertStringContainsString('permission:notification.read', $routes);
        self::assertStringContainsString('notification_recipients', $service);
        self::assertStringContainsString("<?= csrf_field() ?>", $view);
        self::assertStringContainsString("=== 'unread' ? 'selected' : ''", $view);
        self::assertStringContainsString("=== 'read' ? 'selected' : ''", $view);
        self::assertStringContainsString("name=\"state\"", $view);
        self::assertStringContainsString("name=\"category\"", $view);
        self::assertStringContainsString('http_build_query($filters)', $controller);
        self::assertStringContainsString("getPost('state')", $controller);
        self::assertStringContainsString("getPost('category')", $controller);
        self::assertStringContainsString('catch (Throwable $exception)', $controller);
        self::assertStringContainsString(
            '->with(\'error\', $this->safe($exception))',
            $controller
        );
        self::assertStringContainsString('notificationLocation', $controller);
        self::assertStringNotContainsString('WhatsApp', $service);
        self::assertStringNotContainsString('Firebase', $service);
    }
}
