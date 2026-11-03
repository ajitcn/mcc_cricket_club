<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

final class AchievementRoutesTest extends TestCase
{
    public function testAchievementNavigationAndRoutesUseCentralPermissions(): void
    {
        $navigation = file_get_contents(ROOTPATH . 'app/Services/NavigationService.php');
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Achievements/Config/Routes.php');
        $sidebar = file_get_contents(ROOTPATH . 'app/Views/partials/sidebar.php');

        self::assertIsString($navigation);
        self::assertIsString($routes);
        self::assertIsString($sidebar);
        self::assertStringContainsString("'achievement.view',", $navigation);
        self::assertStringContainsString("'Achievement Catalog'", $navigation);
        self::assertStringContainsString("permission:achievement.view", $routes);
        self::assertStringContainsString("permission:achievement.create", $routes);
        self::assertStringContainsString("service('navigation')->staffGroups()", $sidebar);
    }

    public function testAchievementViewsUseTheExistingAuthorizationServiceBoundary(): void
    {
        $views = [
            'index.php',
            'awards.php',
            'award_show.php',
        ];

        foreach ($views as $viewName) {
            $view = file_get_contents(ROOTPATH . 'app/Modules/Achievements/Views/' . $viewName);

            self::assertIsString($view);
            self::assertStringContainsString('->can', $view, $viewName);
            self::assertDoesNotMatchRegularExpression('/(?<!->)can\s*\(/', $view, $viewName);
        }
    }
}
