<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

final class AchievementArchivalRoutesTest extends TestCase
{
    public function testArchiveAndRestoreRoutesUseSeparatePermissionsAndCsrfForms(): void
    {
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Achievements/Config/Routes.php');
        $view = file_get_contents(ROOTPATH . 'app/Modules/Achievements/Views/award_show.php');
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Achievements/Controllers/Browser.php');

        self::assertIsString($routes);
        self::assertIsString($view);
        self::assertIsString($controller);
        self::assertStringContainsString('permission:achievement.archive', $routes);
        self::assertStringContainsString('permission:achievement.restore', $routes);
        self::assertStringContainsString('archiveAward', $controller);
        self::assertStringContainsString('restoreAward', $controller);
        self::assertStringContainsString('Archive reason', $view);
        self::assertStringContainsString('Restore reason', $view);
        self::assertStringContainsString('history=1', file_get_contents(ROOTPATH . 'app/Modules/Achievements/Views/awards.php'));
        self::assertStringContainsString('csrf_field()', $view);
    }
}
