<?php

declare(strict_types=1);

namespace Tests\Feature;

use CodeIgniter\Test\CIUnitTestCase;

final class PlayerActionsMenuRoutesTest extends CIUnitTestCase
{
    public function testEveryRenderedPlayerActionHasAnExplicitAuthorizedRoute(): void
    {
        $routes = file_get_contents(APPPATH . 'Modules/Players/Config/Routes.php');
        $player360Routes = file_get_contents(APPPATH . 'Modules/Player360/Config/Routes.php');
        $view = file_get_contents(APPPATH . 'Modules/Players/Views/index.php');

        $actions = [
            'view' => ['GET', 'Browser::showPage/$1', 'permission:player.view'],
            'profile' => ['POST', 'Browser::update/$1', 'permission:player.update'],
            'status' => ['POST', 'Browser::status/$1', 'permission:player.update'],
            'branch' => ['POST', 'Browser::branch/$1', 'permission:player.view'],
            'batch' => ['POST', 'Browser::batch/$1', 'permission:player.view'],
            'coaches' => ['POST', 'Browser::coaches/$1', 'permission:player.view'],
            'contacts' => ['POST', 'Browser::contact/$1', 'permission:player.update'],
        ];

        foreach ($actions as $action => [$method, $controller, $permission]) {
            self::assertStringContainsString('data-player-action="' . $action . '"', $view);
            self::assertStringContainsString('$routes->' . strtolower($method), $routes);
            self::assertStringContainsString($controller, $routes);
            self::assertStringContainsString($permission, $routes);
        }

        self::assertStringContainsString('data-player-action="360"', $view);
        self::assertStringContainsString('Player360', $player360Routes);
        self::assertStringContainsString('Browser::show/$1', $player360Routes);
        self::assertStringContainsString('permission:player360.view', $player360Routes);
        self::assertStringNotContainsString('data-player-action="attendance"', $view);
        self::assertStringNotContainsString('data-player-action="sessions"', $view);
        self::assertStringNotContainsString('data-player-action="performance"', $view);
        self::assertStringNotContainsString('data-player-action="documents"', $view);
        self::assertStringNotContainsString('data-player-action="admission"', $view);
        self::assertStringNotContainsString('Set Goal', $view);
        self::assertStringNotContainsString('Create Session Plan', $view);
    }

    public function testWriteRoutesRemainPostOnlyAndFormsRetainCsrfProtection(): void
    {
        $routes = file_get_contents(APPPATH . 'Modules/Players/Config/Routes.php');
        $show = file_get_contents(APPPATH . 'Modules/Players/Views/show.php');
        $browser = file_get_contents(APPPATH . 'Modules/Players/Controllers/Browser.php');

        foreach (['profile', 'branch', 'batch', 'coaches', 'status', 'contacts'] as $action) {
            self::assertStringContainsString(
                '$routes->post(\'(:num)/' . $action . '\'',
                $routes,
            );
        }

        self::assertStringNotContainsString(
            '$routes->post(\'(:num)/coach\'',
            $routes,
        );

        self::assertStringContainsString('csrf_field()', $show);
    }

    public function testPlayerListRendersScopedFiltersStatusLinksAndSafeActionPayloads(): void
    {
        $view = file_get_contents(APPPATH . 'Modules/Players/Views/index.php');

        foreach (['name="q"', 'name="status"', 'name="branch_id"', 'name="batch_id"', 'name="age_group"', 'name="coach_id"'] as $filter) {
            self::assertStringContainsString($filter, $view);
        }

        self::assertStringContainsString("['status' => \$status]", $view);
        foreach (['Active', 'Inactive', 'Suspended', 'Left academy', 'Alumni'] as $label) {
            self::assertStringContainsString("'label' => '" . $label . "'", $view);
        }
        foreach (['mcc-player-status-active', 'mcc-player-status-inactive', 'mcc-player-status-suspended', 'mcc-player-status-left_academy', 'mcc-player-status-alumni'] as $class) {
            self::assertStringContainsString($class, $view);
        }
        self::assertStringContainsString('data-player-id="<?= $playerId ?>"', $view);
        self::assertStringContainsString('data-player-actions-menu', $view);
        self::assertStringContainsString('hidden', $view);
    }

    public function testPlayerDetailActionsUseTheCurrentPlayerAndPermissionAwareTargets(): void
    {
        $show = file_get_contents(APPPATH . 'Modules/Players/Views/show.php');
        $browser = file_get_contents(APPPATH . 'Modules/Players/Controllers/Browser.php');

        foreach ([
            'player-profile-form-',
            'player-coaches-form-',
            'player-branch-form-',
            'player-batch-form-',
            'documents?player_id=',
            'site_url(\'admissions/\' . (int) $player[\'admission_id\'] . \'/page\')',
        ] as $target) {
            self::assertStringContainsString($target, $show);
        }

        self::assertStringContainsString('mcc-detail-action-menu', $show);
        self::assertStringContainsString('role="menuitem"', $show);
        self::assertStringContainsString("'can_documents' => service('authorization')->can('document.view', \$branch)", $browser);
    }
}
