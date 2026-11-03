<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

final class AchievementEligibilityRoutesTest extends TestCase
{
    public function testEvaluationAndRetryRoutesAreExplicitlyPermissionFiltered(): void
    {
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Achievements/Config/Routes.php');
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Achievements/Controllers/Browser.php');

        self::assertIsString($routes);
        self::assertIsString($controller);
        self::assertStringContainsString("eligibility/evaluate',", $routes);
        self::assertStringContainsString("eligibility/(:num)/retry',", $routes);
        self::assertSame(2, substr_count($routes, 'permission:achievement.eligibility.evaluate'));
        self::assertStringContainsString('$routes->post', $routes);
        self::assertStringContainsString('service(\'achievementEligibility\')->evaluate', $controller);
        self::assertStringContainsString('service(\'achievementEligibility\')->retry', $controller);
    }
}
