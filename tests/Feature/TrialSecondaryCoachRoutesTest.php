<?php

declare(strict_types=1);

namespace Tests\Feature;

use CodeIgniter\Test\CIUnitTestCase;

final class TrialSecondaryCoachRoutesTest extends CIUnitTestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = dirname(__DIR__, 2);
    }

    public function testSecondaryCoachRouteTargetsTheTrialBrowserControllerWithPermission(): void
    {
        $routes = file_get_contents($this->root . '/app/Modules/CRM/Config/Routes.php');
        $controller = file_get_contents($this->root . '/app/Modules/Trials/Controllers/Browser.php');

        self::assertIsString($routes);
        self::assertIsString($controller);
        self::assertStringContainsString("'trials/(:num)/coaches'", $routes);
        self::assertStringContainsString('Browser::coaches/$1', $routes);
        self::assertStringContainsString("'trials/(:num)/coaches'", $routes);
        self::assertStringContainsString("Browser::coaches/$1", $routes);
        self::assertStringContainsString("['filter' => 'auth']", $routes);
        self::assertStringContainsString('public function coaches(int $id)', $controller);
        self::assertStringContainsString("service('trial')->updateSecondaryCoach(\$id", $controller);
    }

    public function testSecondaryCoachFormUsesTrialIdAndOnlyItsAssignmentFields(): void
    {
        $view = file_get_contents($this->root . '/app/Modules/Trials/Views/show.php');

        self::assertIsString($view);
        self::assertStringContainsString("site_url('crm/trials/' . (int) \$trial['id'] . '/coaches')", $view);
        self::assertStringContainsString('name="secondary_coach_user_id"', $view);
        self::assertStringContainsString('name="expected_updated_at"', $view);
        self::assertStringContainsString('<?= csrf_field() ?>', $view);
        self::assertStringNotContainsString("\$trial['lead_id'] . '/coaches'", $view);
        self::assertStringNotContainsString("\$trial['branch_id'] . '/coaches'", $view);
    }
}
