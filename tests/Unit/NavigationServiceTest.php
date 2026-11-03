<?php

declare (strict_types=1);
namespace Tests\Unit;

use App\Services\NavigationService;
use PHPUnit\Framework\TestCase;
final class NavigationServiceTest extends TestCase
{
    public function testTrialRouteGroupUsesOneActivePrefix(): void
    {
        $navigation = new NavigationService();
        $trial = ['url' => 'trials', 'active_prefix' => 'trials'];
        foreach (['trials', 'trials/from-lead/12', 'trials/42', 'trials/42/reschedule', 'trials/42/cancel', 'trials/42/attendance', 'trials/42/selection',
    'trials/42/admission-pending'] as $path) {
            self::assertTrue($navigation->isActive($path, $trial), $path . ' should activate Trials.');
        }
        foreach (['', 'crm/leads', 'admissions', 'sessions', 'trialsome'] as $path) {
            self::assertFalse($navigation->isActive($path, $trial), $path . ' should not activate Trials.');
        }
    }
    public function testCanonicalTrialRouteDoesNotActivateCrmLeads(): void
    {
        $navigation = new NavigationService();
        $crm = ['url' => 'crm/leads', 'active_prefix' => 'crm/leads'];
        $trial = ['url' => 'crm/trials', 'active_prefix' => 'crm/trials'];
        self::assertTrue($navigation->isActive('crm/trials/42', $trial));
        self::assertFalse($navigation->isActive('crm/trials/42', $crm));
        self::assertTrue($navigation->isActive('crm/leads/42', $crm));
        self::assertFalse($navigation->isActive('crm/leads/42', $trial));
    }
    public function testApprovedModulePathsActivateTheirCentralNavigationItems(): void
    {
        $navigation = new NavigationService();
        $paths = [
            'crm/trials/42' => 'crm/trials',
            'crm/follow-ups/42' => 'crm/follow-ups',
            'admissions/42' => 'admissions',
            'players/42' => 'players',
            'sessions/42/page' => 'sessions',
            'attendance/42' => 'attendance',
            'matches/42' => 'matches',
            'matches/opponents/12/edit' => 'matches/opponents',
            'admin/branches/42' => 'admin',
        ];
        foreach ($paths as $path => $url) {
            self::assertTrue($navigation->isActive($path, ['url' => $url, 'active_prefix' => $url]), $path . ' should activate ' . $url . '.');
        }
    }

    public function testCompetitionsGroupContainsPermissionAwareMatchTournamentAndOpponentItems(): void
    {
        session()->set([
            'isLoggedIn' => true,
            'accountEnabled' => true,
            'permissions' => ['match.view', 'tournament.view', 'opponent.view'],
        ]);
        \Config\Services::resetSingle('authorization');

        $groups = (new NavigationService())->staffGroups();
        $competitions = array_values(array_filter(
            $groups,
            static fn (array $group): bool => $group['label'] === 'Competitions',
        ));

        self::assertCount(1, $competitions);
        self::assertSame(
            ['Matches', 'Tournaments', 'Opponents'],
            array_column($competitions[0]['items'], 'label'),
        );
        self::assertSame('matches/opponents', $competitions[0]['items'][2]['url']);
        self::assertSame('matches/opponents', $competitions[0]['items'][2]['active_prefix']);
        self::assertSame('opponent.view', $competitions[0]['items'][2]['permission']);

        session()->destroy();
        \Config\Services::resetSingle('authorization');
    }

    public function testOpponentNavigationRequiresItsCentralPermission(): void
    {
        session()->set([
            'isLoggedIn' => true,
            'accountEnabled' => true,
            'permissions' => ['match.view', 'tournament.view'],
        ]);
        \Config\Services::resetSingle('authorization');

        $items = array_merge(
            ...array_map(static fn (array $group): array => $group['items'], (new NavigationService())->staffGroups()),
        );

        self::assertNotContains('Opponents', array_column($items, 'label'));

        session()->destroy();
        \Config\Services::resetSingle('authorization');
    }
    public function testSharedSidebarUsesCentralGroupsAndAccessibleCompactControls(): void
    {
        $sidebar = file_get_contents(ROOTPATH . 'app/Views/partials/sidebar.php');
        $script = file_get_contents(ROOTPATH . 'public/assets/js/app.js');
        $styles = file_get_contents(ROOTPATH . 'public/assets/css/app.css');
        self::assertStringContainsString("service('navigation')->staffGroups()", $sidebar);
        self::assertStringContainsString('data-sidebar-collapse', $sidebar);
        self::assertStringContainsString('data-nav-group-toggle', $sidebar);
        self::assertStringContainsString('aria-controls', $sidebar);
        self::assertStringContainsString("state'] === 'implemented'", $sidebar);
        self::assertStringContainsString('fa-solid fa-chevron-', $sidebar);
        self::assertStringContainsString("'down'", $sidebar);
        self::assertStringContainsString("'right'", $sidebar);
        self::assertStringNotContainsString('>âŒ„<', $sidebar);
        self::assertStringContainsString('mcc-coming-soon', $sidebar);
        self::assertStringContainsString('aria-disabled="true"', $sidebar);
        self::assertStringContainsString("site_url('assets/img/logo.png')", $sidebar);
        self::assertStringContainsString('alt="MCC Cricket Club"', $sidebar);
        self::assertStringNotContainsString('mcc-brand-copy', $sidebar);
        self::assertStringNotContainsString('mcc-brand-mark', $sidebar);
        self::assertStringContainsString('mcc-brand-logo', $styles);
        self::assertStringContainsString('mcc.sidebar.collapsed', $script);
        self::assertStringContainsString('mcc.nav.', $script);
        self::assertStringContainsString('fa-chevron-down', $script);
        self::assertStringContainsString('fa-chevron-right', $script);
        self::assertStringContainsString('sidebar-collapsed', $styles);
        self::assertStringContainsString('@media(max-width:991px)', $styles);
    }
    public function testNavigationModelExcludesUndeferredCampaignsWithoutDestinations(): void
    {
        session()->set(['isLoggedIn' => true, 'accountEnabled' => true, 'permissions' => ['lead.view', 'admission.view', 'player.view', 'session.update',
    'attendance.view', 'rbac.manage']]);
        \Config\Services::resetSingle('authorization');
        $groups = (new NavigationService())->staffGroups();
        $items = array_merge(...array_map(static fn(array $group): array => $group['items'], $groups));
        $leads = array_values(array_filter($items, static fn(array $item): bool => $item['label'] === 'Leads'))[0];
        self::assertNotContains('Campaigns', array_column($items, 'label'));
        self::assertSame('implemented', $leads['state']);
        self::assertSame('crm/leads', $leads['url']);
        self::assertSame('lead.view', $leads['permission']);
        session()->destroy();
        \Config\Services::resetSingle('authorization');
    }
    public function testPermissionFilteringAppliesOnlyToImplementedItems(): void
    {
        session()->set(['isLoggedIn' => true, 'accountEnabled' => true, 'permissions' => ['lead.view']]);
        \Config\Services::resetSingle('authorization');
        $groups = (new NavigationService())->staffGroups();
        $labels = array_merge(...array_map(static fn(array $group): array => array_column($group['items'], 'label'), $groups));
        self::assertContains('Leads', $labels);
        self::assertNotContains('Admissions', $labels);
        self::assertNotContains('Campaigns', $labels);
        self::assertContains('Alerts', $labels);
        session()->destroy();
        \Config\Services::resetSingle('authorization');
    }

    public function testAdmissionMasterNavigationUsesExistingPermissionAndRoutes(): void
    {
        session()->set([
            'isLoggedIn' => true,
            'accountEnabled' => true,
            'permissions' => ['admission.view', 'batch.view'],
        ]);
        \Config\Services::resetSingle('authorization');

        $groups = (new NavigationService())->staffGroups();
        $admissions = array_values(array_filter(
            $groups,
            static fn (array $group): bool => $group['label'] === 'Admissions',
        ));

        self::assertCount(1, $admissions);

        $items = $admissions[0]['items'];
        $labels = array_column($items, 'label');
        self::assertSame(
            ['Admissions', 'Fee Installments', 'Age Groups', 'Batches'],
            $labels,
        );

        $expected = [
            'Age Groups' => [
                'path' => 'admission-masters/age_group',
                'permission' => 'admission.view',
            ],
            'Batches' => [
                'path' => 'batches',
                'permission' => 'batch.view',
            ],
        ];

        foreach ($expected as $label => $definition) {
            $path = $definition['path'];
            $item = array_values(array_filter(
                $items,
                static fn (array $candidate): bool => $candidate['label'] === $label,
            ))[0];

            self::assertSame($definition['permission'], $item['permission']);
            self::assertSame($path, $item['url']);
            self::assertSame($path, $item['active_prefix']);
            self::assertTrue((new NavigationService())->isActive($path . '/new', $item));
        }

        foreach (['batches', 'batches/create', 'batches/42/edit', 'batches/42'] as $path) {
            self::assertTrue(
                (new NavigationService())->isActive($path, $items[3]),
                'Batches should be active for ' . $path,
            );
        }

        $allLabels = array_merge(
            ...array_map(
                static fn (array $group): array => array_column($group['items'], 'label'),
                (new NavigationService())->staffGroups(),
            ),
        );
        self::assertSame(1, count(array_filter($allLabels, static fn (string $label): bool => $label === 'Batches')));

        session()->set('permissions', []);
        \Config\Services::resetSingle('authorization');
        $withoutPermission = (new NavigationService())->staffGroups();
        $visibleLabels = array_merge(
            ...array_map(
                static fn (array $group): array => array_column($group['items'], 'label'),
                $withoutPermission,
            ),
        );

        self::assertNotContains('Coaching Plans', $visibleLabels);
        self::assertNotContains('Fee Plans', $visibleLabels);
        self::assertNotContains('Hostel Plans', $visibleLabels);
        self::assertNotContains('Academic Plans', $visibleLabels);
        self::assertNotContains('Batches', $visibleLabels);

        session()->destroy();
        \Config\Services::resetSingle('authorization');
    }
    public function testComingSoonItemsNeverBecomeActive(): void
    {
        $navigation = new NavigationService();
        self::assertFalse($navigation->isActive('reports', ['url' => null, 'active_prefix' => null]));
        self::assertFalse($navigation->isActive('admin/settings', ['url' => null, 'active_prefix' => null]));
    }

    public function testUnapprovedStatisticsSelectionAndAwardsEntriesAreNotAdvertised(): void
    {
        session()->set([
            'isLoggedIn' => true,
            'accountEnabled' => true,
            'permissions' => ['tournament.view'],
        ]);
        \Config\Services::resetSingle('authorization');

        $groups = (new NavigationService())->staffGroups();
        $labels = array_merge(
            ...array_map(
                static fn (array $group): array => array_column($group['items'], 'label'),
                $groups,
            ),
        );

        self::assertNotContains('Statistics', $labels);
        self::assertNotContains('Selection', $labels);
        self::assertNotContains('Awards & Certificates', $labels);

        session()->destroy();
        \Config\Services::resetSingle('authorization');
    }

    public function testImplementedDocumentAndParentPortalItemsUseCanonicalRoutes(): void
    {
        session()->set([
            'isLoggedIn' => true,
            'accountEnabled' => true,
            'permissions' => ['document.view', 'parent_portal.view', 'equipment.view'],
        ]);
        \Config\Services::resetSingle('authorization');

        $groups = (new NavigationService())->staffGroups();
        $items = array_merge(
            ...array_map(static fn (array $group): array => $group['items'], $groups)
        );
        $byLabel = [];
        foreach ($items as $item) {
            $byLabel[$item['label']] = $item;
        }

        self::assertSame('implemented', $byLabel['Documents']['state']);
        self::assertSame('documents', $byLabel['Documents']['url']);
        self::assertSame('document.view', $byLabel['Documents']['permission']);
        self::assertSame('implemented', $byLabel['Parent Portal']['state']);
        self::assertSame('parent-portal', $byLabel['Parent Portal']['url']);
        self::assertSame('parent_portal.view', $byLabel['Parent Portal']['permission']);
        self::assertSame('implemented', $byLabel['Equipment']['state']);
        self::assertSame('equipment', $byLabel['Equipment']['url']);
        self::assertSame('equipment.view', $byLabel['Equipment']['permission']);

        session()->destroy();
        \Config\Services::resetSingle('authorization');
    }

    public function testImplementedItemsRemainPermissionAwareAndDeferredItemsStayDisabled(): void
    {
        session()->set([
            'isLoggedIn' => true,
            'accountEnabled' => true,
            'permissions' => ['document.view', 'report.view', 'performance.view'],
        ]);
        \Config\Services::resetSingle('authorization');

        $groups = (new NavigationService())->staffGroups();
        $items = array_merge(
            ...array_map(static fn (array $group): array => $group['items'], $groups)
        );
        $labels = array_column($items, 'label');

        self::assertContains('Documents', $labels);
        self::assertContains('Performance', $labels);
        self::assertContains('Report Center', $labels);
        self::assertNotContains('Parent Portal', $labels);
        self::assertNotContains('Audit Logs', $labels);

        $performance = array_values(array_filter(
            $items,
            static fn (array $item): bool => $item['label'] === 'Performance'
        ))[0];
        self::assertSame('implemented', $performance['state']);
        self::assertSame('performance', $performance['url']);
        self::assertSame('performance.view', $performance['permission']);
        self::assertNotContains('Achievements', $labels);

        session()->destroy();
        \Config\Services::resetSingle('authorization');
    }

    public function testPerformanceIsVisibleOnlyWithPermissionAndBelongsToCoaching(): void
    {
        session()->set([
            'isLoggedIn' => true,
            'accountEnabled' => true,
            'permissions' => ['performance.view'],
        ]);
        \Config\Services::resetSingle('authorization');

        $groups = (new NavigationService())->staffGroups();
        $coaching = array_values(array_filter(
            $groups,
            static fn (array $group): bool => $group['label'] === 'Coaching',
        ));

        self::assertCount(1, $coaching);
        self::assertSame(
            ['Performance'],
            array_values(array_filter(
                array_column($coaching[0]['items'], 'label'),
                static fn (string $label): bool => $label === 'Performance',
            )),
        );

        session()->set('permissions', []);
        \Config\Services::resetSingle('authorization');
        $withoutPermission = array_merge(
            ...array_map(
                static fn (array $group): array => $group['items'],
                (new NavigationService())->staffGroups(),
            ),
        );
        self::assertNotContains('Performance', array_column($withoutPermission, 'label'));

        session()->destroy();
        \Config\Services::resetSingle('authorization');
    }

    public function testAuditLogsUsesItsExistingPermissionAndRoute(): void
    {
        session()->set([
            'isLoggedIn' => true,
            'accountEnabled' => true,
            'permissions' => ['audit.view'],
        ]);
        \Config\Services::resetSingle('authorization');

        $groups = (new NavigationService())->staffGroups();
        $items = array_merge(
            ...array_map(static fn (array $group): array => $group['items'], $groups)
        );
        $auditItems = array_values(array_filter(
            $items,
            static fn (array $item): bool => $item['label'] === 'Audit Logs'
        ));

        self::assertCount(1, $auditItems);
        self::assertSame('implemented', $auditItems[0]['state']);
        self::assertSame('admin/audit', $auditItems[0]['url']);
        self::assertSame('audit.view', $auditItems[0]['permission']);

        session()->destroy();
        \Config\Services::resetSingle('authorization');
    }

    public function testImplementedNavigationActivePrefixesMatchRoutes(): void
    {
        $navigation = new NavigationService();

        self::assertTrue($navigation->isActive('documents/12', [
            'url' => 'documents',
            'active_prefix' => 'documents',
        ]));
        self::assertTrue($navigation->isActive('parent-portal/children/12', [
            'url' => 'parent-portal',
            'active_prefix' => 'parent-portal',
        ]));
        self::assertTrue($navigation->isActive('reports/attendance', [
            'url' => 'reports',
            'active_prefix' => 'reports',
        ]));
        self::assertTrue($navigation->isActive('performance/42/edit', [
            'url' => 'performance',
            'active_prefix' => 'performance',
        ]));
        self::assertTrue($navigation->isActive('matches/opponents', [
            'url' => 'matches/opponents',
            'active_prefix' => 'matches/opponents',
        ]));
        self::assertTrue($navigation->isActive('matches/opponents/12/edit', [
            'url' => 'matches/opponents',
            'active_prefix' => 'matches/opponents',
        ]));
    }

    public function testAchievementNavigationGroupsCatalogAwardsAndReports(): void
    {
        session()->set([
            'isLoggedIn' => true,
            'accountEnabled' => true,
            'permissions' => ['achievement.view', 'report.view'],
        ]);
        \Config\Services::resetSingle('authorization');

        $groups = (new NavigationService())->staffGroups();
        $achievementGroups = array_values(array_filter(
            $groups,
            static fn (array $group): bool => $group['label'] === 'Achievements',
        ));

        self::assertCount(1, $achievementGroups);
        self::assertSame(
            ['Achievement Catalog', 'Awards', 'Achievement Reports'],
            array_column($achievementGroups[0]['items'], 'label'),
        );
        self::assertSame(
            ['achievements', 'achievements/awards', 'achievements/reports'],
            array_column($achievementGroups[0]['items'], 'url'),
        );
        self::assertSame(
            ['achievement.view', 'achievement.view', 'report.view'],
            array_column($achievementGroups[0]['items'], 'permission'),
        );

        $items = $achievementGroups[0]['items'];
        foreach ([
            'achievements',
            'achievements/new',
        ] as $path) {
            self::assertTrue((new NavigationService())->isActive($path, $items[0]));
        }
        foreach ([
            'achievements/awards',
            'achievements/awards/42',
            'achievements/awards/42/edit',
            'achievements/awards/42/approve',
            'achievements/awards/42/publish',
            'achievements/awards/42/revoke',
            'achievements/awards/42/certificate',
        ] as $path) {
            self::assertTrue((new NavigationService())->isActive($path, $items[1]));
            self::assertFalse((new NavigationService())->isActive($path, $items[0]));
        }
        self::assertTrue((new NavigationService())->isActive('achievements/reports', $items[2]));
        self::assertFalse((new NavigationService())->isActive('achievements/reports', $items[0]));

        session()->destroy();
        \Config\Services::resetSingle('authorization');
    }

    public function testMarketingDashboardReflectsRefreshedSessionPermissions(): void
    {
        session()->set([
            'isLoggedIn' => true,
            'accountEnabled' => true,
            'permissions' => ['player.view'],
        ]);
        \Config\Services::resetSingle('authorization');
        service('authorization')->can('marketing.view');

        session()->set('permissions', ['player.view', 'marketing.view']);
        $labels = array_merge(...array_map(
            static fn (array $group): array => array_column($group['items'], 'label'),
            (new NavigationService())->staffGroups(),
        ));

        self::assertContains('Marketing Dashboard', $labels);
        self::assertContains('Players', $labels);

        session()->set('permissions', ['player.view']);
        \Config\Services::resetSingle('authorization');
        $labels = array_merge(...array_map(
            static fn (array $group): array => array_column($group['items'], 'label'),
            (new NavigationService())->staffGroups(),
        ));

        self::assertNotContains('Marketing Dashboard', $labels);
        self::assertContains('Players', $labels);
        session()->destroy();
        \Config\Services::resetSingle('authorization');
    }

    public function testRestoredStaffMenuKeepsCoreItemsPermissionAware(): void
    {
        session()->set([
            'isLoggedIn' => true,
            'accountEnabled' => true,
            'permissions' => [
                'lead.view',
                'admission.view',
                'player.view',
                'player.goal.set',
                'player.session_plan.view',
                'player.feedback.view',
                'coach.view',
                'program.view',
                'session.update',
                'attendance.view',
                'performance.view',
                'report.view',
                'notification.view',
                'rbac.manage',
            ],
        ]);
        \Config\Services::resetSingle('authorization');

        $groups = (new NavigationService())->staffGroups();
        $items = array_merge(
            ...array_map(
                static fn (array $group): array => $group['items'],
                $groups,
            ),
        );
        $byLabel = [];
        foreach ($items as $item) {
            $byLabel[$item['label']] = $item;
        }

        foreach ([
            'Players' => ['player.view', 'players'],
            'Goals' => ['player.goal.set', 'player-goals'],
            'Session Plans' => ['player.session_plan.view', 'player-session-plans'],
            'Coach Feedback' => ['player.feedback.view', 'player-feedback'],
            'Coaches' => ['coach.view', 'coaches'],
            'Programs' => ['program.view', 'programs'],
            'Sessions' => ['session.update', 'sessions'],
            'Attendance' => ['attendance.view', 'attendance'],
            'Performance' => ['performance.view', 'performance'],
            'Report Center' => ['report.view', 'reports'],
            'Notifications' => ['notification.view', 'notifications'],
            'Branches' => ['rbac.manage', 'admin/branches'],
        ] as $label => [$permission, $url]) {
            self::assertArrayHasKey($label, $byLabel);
            self::assertSame($permission, $byLabel[$label]['permission']);
            self::assertSame($url, $byLabel[$label]['url']);
            self::assertSame('implemented', $byLabel[$label]['state']);
        }

        session()->set('permissions', ['player.view']);
        \Config\Services::resetSingle('authorization');
        $limitedItems = array_merge(
            ...array_map(
                static fn (array $group): array => $group['items'],
                (new NavigationService())->staffGroups(),
            ),
        );
        $limitedLabels = array_column($limitedItems, 'label');

        self::assertContains('Players', $limitedLabels);
        self::assertNotContains('Coaches', $limitedLabels);
        self::assertNotContains('Session Plans', $limitedLabels);
        self::assertNotContains('Branches', $limitedLabels);

        session()->destroy();
        \Config\Services::resetSingle('authorization');
    }

}
