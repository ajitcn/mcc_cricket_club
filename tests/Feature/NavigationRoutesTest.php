<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

final class NavigationRoutesTest extends TestCase
{
    public function testCompetitionsNavigationUsesOpponentRouteAndPermission(): void
    {
        $sidebar = file_get_contents(ROOTPATH . 'app/Views/partials/sidebar.php');

        self::assertIsString($sidebar);
        self::assertStringContainsString("service('navigation')->staffGroups()", $sidebar);

        session()->set([
            'isLoggedIn' => true,
            'accountEnabled' => true,
            'permissions' => ['match.view', 'tournament.view', 'opponent.view'],
        ]);
        \Config\Services::resetSingle('authorization');

        $groups = service('navigation')->staffGroups();
        $competitions = array_values(array_filter(
            $groups,
            static fn (array $group): bool => $group['label'] === 'Competitions',
        ));

        self::assertCount(1, $competitions);
        self::assertSame(
            ['Matches', 'Tournaments', 'Opponents'],
            array_column($competitions[0]['items'], 'label'),
        );

        $opponents = $competitions[0]['items'][2];
        self::assertSame('matches/opponents', $opponents['url']);
        self::assertSame('matches/opponents', $opponents['active_prefix']);
        self::assertSame('opponent.view', $opponents['permission']);
        self::assertTrue(service('navigation')->isActive('matches/opponents', $opponents));
        self::assertTrue(service('navigation')->isActive('matches/opponents/9/edit', $opponents));

        session()->set('permissions', ['match.view', 'tournament.view']);
        \Config\Services::resetSingle('authorization');
        $withoutOpponentPermission = service('navigation')->staffGroups();
        $items = array_merge(
            ...array_map(static fn (array $group): array => $group['items'], $withoutOpponentPermission),
        );
        self::assertNotContains('Opponents', array_column($items, 'label'));

        session()->destroy();
        \Config\Services::resetSingle('authorization');
    }

    public function testPerformanceNavigationUsesImplementedRouteAndPermission(): void
    {
        $navigation = file_get_contents(ROOTPATH . 'app/Services/NavigationService.php');
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Performance/Config/Routes.php');
        $restoreSeeder = file_get_contents(ROOTPATH . 'app/Database/Seeds/AuthorizationRestoreSeeder.php');

        self::assertIsString($navigation);
        self::assertIsString($routes);
        self::assertIsString($restoreSeeder);
        self::assertStringContainsString("'label' => 'Coaching'", $navigation);
        self::assertStringContainsString(
            '$this->item(\'performance.view\', \'Performance\', \'performance\', \'performance\'',
            $navigation,
        );
        self::assertStringContainsString("\$routes->get('/',", $routes);
        self::assertStringContainsString("permission:performance.view", $routes);
        self::assertStringContainsString("'performance.view'", $restoreSeeder);
        self::assertStringNotContainsString("planned('Performance'", $navigation);
        self::assertStringNotContainsString("planned('Achievements'", $navigation);
    }

    public function testAchievementNavigationUsesExistingRoutesAndPermissions(): void
    {
        $navigation = file_get_contents(ROOTPATH . 'app/Services/NavigationService.php');
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Achievements/Config/Routes.php');

        self::assertIsString($navigation);
        self::assertIsString($routes);
        self::assertStringContainsString("'label' => 'Achievements'", $navigation);
        self::assertStringContainsString("'Achievement Catalog'", $navigation);
        self::assertStringContainsString("'Awards'", $navigation);
        self::assertStringContainsString("'Achievement Reports'", $navigation);
        self::assertStringContainsString("'achievement.view'", $navigation);
        self::assertStringContainsString("'report.view'", $navigation);
        self::assertStringContainsString("'achievements/awards'", $navigation);
        self::assertStringContainsString("'achievements/reports'", $navigation);
        self::assertStringContainsString('$routes->get(\'awards\'', $routes);
        self::assertStringContainsString('$routes->get(\'reports\'', $routes);
    }

    public function testAdmissionNavigationRetainsOnlyAgeGroupMasterRoute(): void
    {
        $navigation = file_get_contents(ROOTPATH . 'app/Services/NavigationService.php');
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Admissions/Config/Routes.php');

        self::assertIsString($navigation);
        self::assertIsString($routes);

        foreach (['Age Groups' => 'admission-masters/age_group'] as $label => $path) {
            self::assertStringContainsString(
                "'{$label}',",
                $navigation,
            );
            self::assertStringContainsString(
                "'{$path}',",
                $navigation,
            );
            self::assertStringContainsString(
                "'filter' => 'permission:admission.view'",
                $routes,
            );
        }

        foreach (['Coaching Plans', 'Fee Plans', 'Hostel Plans', 'Academic Plans'] as $label) {
            self::assertStringNotContainsString("'{$label}',", $navigation);
        }

        foreach (['coaching', 'fee', 'hostel', 'academic'] as $type) {
            self::assertStringNotContainsString("admission-masters/{$type}", $routes);
        }
    }

    public function testBatchesNavigationIsUnderAdmissionsWithPermissionFiltering(): void
    {
        session()->set([
            'isLoggedIn' => true,
            'accountEnabled' => true,
            'permissions' => ['admission.view', 'batch.view'],
        ]);
        \Config\Services::resetSingle('authorization');

        $groups = service('navigation')->staffGroups();
        $admissions = array_values(array_filter(
            $groups,
            static fn (array $group): bool => $group['label'] === 'Admissions',
        ));
        $batches = array_values(array_filter(
            $groups,
            static fn (array $group): bool => $group['label'] === 'Coaching',
        ));

        self::assertCount(1, $admissions);
        self::assertSame(
            ['Admissions', 'Fee Installments', 'Age Groups', 'Batches'],
            array_column($admissions[0]['items'], 'label'),
        );
        self::assertCount(0, $batches);

        $batchItem = $admissions[0]['items'][3];
        self::assertSame('batch.view', $batchItem['permission']);
        self::assertSame('batches', $batchItem['url']);
        self::assertSame('batches', $batchItem['active_prefix']);

        foreach (['batches', 'batches/create', 'batches/42/edit', 'batches/42'] as $path) {
            self::assertTrue(service('navigation')->isActive($path, $batchItem));
        }

        session()->set('permissions', ['admission.view']);
        \Config\Services::resetSingle('authorization');
        $withoutBatchPermission = service('navigation')->staffGroups();
        $visibleLabels = array_merge(
            ...array_map(
                static fn (array $group): array => array_column($group['items'], 'label'),
                $withoutBatchPermission,
            ),
        );
        self::assertNotContains('Batches', $visibleLabels);

        session()->destroy();
        \Config\Services::resetSingle('authorization');
    }

    public function testSidebarRestoresCoreMenuIconsAndUsesCentralNavigationGroups(): void
    {
        $sidebar = file_get_contents(ROOTPATH . 'app/Views/partials/sidebar.php');
        $navigation = file_get_contents(ROOTPATH . 'app/Services/NavigationService.php');

        self::assertIsString($sidebar);
        self::assertIsString($navigation);
        self::assertStringContainsString("service('navigation')->staffGroups()", $sidebar);
        self::assertStringContainsString("'clipboard' => 'fa-clipboard-list'", $sidebar);
        self::assertStringContainsString("'comments' => 'fa-comments'", $sidebar);
        self::assertStringContainsString("'layers' => 'fa-layer-group'", $sidebar);
        self::assertStringContainsString("'player.goal.set', 'Goals', 'player-goals'", $navigation);
        self::assertStringContainsString(
            "'player.session_plan.view', 'Session Plans', 'player-session-plans'",
            $navigation,
        );
        self::assertStringContainsString(
            "'player.feedback.view', 'Coach Feedback', 'player-feedback'",
            $navigation,
        );
        self::assertStringContainsString("'rbac.manage', 'Branches', 'admin/branches'", $navigation);
        self::assertStringContainsString("'report.view', 'Report Center', 'reports'", $navigation);
        self::assertStringContainsString("'notification.view', 'Notifications', 'notifications'", $navigation);
    }
}
