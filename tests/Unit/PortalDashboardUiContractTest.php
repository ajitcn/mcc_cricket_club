<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class PortalDashboardUiContractTest extends TestCase
{
    private string $view;

    private string $controller;

    protected function setUp(): void
    {
        $this->view = (string) file_get_contents(APPPATH . 'Modules/Portal/Views/dashboard.php');
        $this->controller = (string) file_get_contents(APPPATH . 'Modules/Portal/Controllers/Browser.php');
    }

    public function testSharedDashboardContainsThePortalSectionsAndPreviewMarker(): void
    {
        foreach ([
            'Student &amp; Parent Portal',
            'MCC Cricket Club',
            'Portal navigation',
            'Welcome back,',
            'Upcoming schedule',
            'Attendance summary',
            'Notices',
            'Documents',
            'Quick actions',
            '<?= esc($branchName) ?>',
            'Upcoming matches',
            'Tournaments',
            'Training schedule',
            'Player development',
            'Recent activity',
            'Important notices',
            'mcc-portal-footer',
            'MCC Cricket Club',
            'Need help? Contact the academy support desk.',
        ] as $content) {
            self::assertStringContainsString($content, $this->view);
        }
    }

    public function testDashboardUsesLivePortalValuesWithoutPlaceholderLanguage(): void
    {
        foreach ([
            '$portalPlayers',
            '$portalSessions',
            '$portalAttendance',
            '$branchName',
            '$programName',
            '$batchName',
        ] as $content) {
            self::assertStringContainsString($content, $this->view);
        }

        foreach (['Demo Player', 'Sample Player', 'Example User', 'Test Branch', 'Placeholder', 'Lorem ipsum', 'Item 1', 'Item 2'] as $placeholder) {
            self::assertStringNotContainsString($placeholder, $this->view);
        }

        self::assertStringNotContainsString('Secure portal', $this->view);
    }

    public function testPortalMenuHasGroupedNavigationAndSeparateLogoutArea(): void
    {
        foreach (['Overview', 'My Profile', 'Schedule', 'Attendance', 'Documents', 'mcc-portal-sidebar-footer'] as $content) {
            self::assertStringContainsString($content, $this->view);
        }
    }

    public function testPortalHeaderContainsIdentityNotificationAndLogoutControls(): void
    {
        foreach ([
            'mcc-portal-user-summary',
            'mcc-portal-notification',
            'aria-label="View 2 portal notifications"',
            'mcc-portal-logout-button',
            'mcc-portal-brand-copy',
            'mcc-portal-welcome-summary',
            'MCC Cricket Club',
            'Student &amp; Parent Portal',
            'assets/img/logo.png',
        ] as $content) {
            self::assertStringContainsString($content, $this->view);
        }
    }

    public function testDashboardLoadsRelationshipScopedData(): void
    {
        self::assertStringContainsString('$dashboard', $this->view);
        self::assertStringContainsString("'dashboard' => service('portalAuth')->dashboard()", $this->controller);
        self::assertStringContainsString("'portalType' => 'Student'", $this->controller);
        self::assertStringContainsString("'portalType' => 'Parent'", $this->controller);
    }

    public function testDashboardKeepsRealLogoutFormAndCsrfProtection(): void
    {
        self::assertStringContainsString('action="<?= site_url(\'portal-logout\') ?>"', $this->view);
        self::assertStringContainsString('<?= csrf_field() ?>', $this->view);
    }

    public function testDashboardUsesResponsiveSharedUiClasses(): void
    {
        foreach ([
            'mcc-portal-layout',
            'mcc-portal-sidebar',
            'mcc-portal-kpi-grid',
            'mcc-portal-content-grid',
            'mcc-card',
            'mcc-button',
            'mcc-status-badge',
        ] as $class) {
            self::assertStringContainsString($class, $this->view);
        }

        $css = (string) file_get_contents(FCPATH . 'assets/css/app.css');
        self::assertStringContainsString('.mcc-portal-dashboard', $css);
        self::assertStringContainsString('min-width: 0', $css);
        self::assertStringContainsString('@media (max-width: 991px)', $css);
    }

    public function testDashboardContainsAccessibleMobileNavigationControls(): void
    {
        foreach ([
            'data-portal-menu-toggle',
            'data-portal-menu-close',
            'id="portal-sidebar"',
            'class="mcc-portal-backdrop"',
            'aria-hidden="true"',
            'aria-label="Open portal navigation"',
            'aria-label="Close portal navigation"',
        ] as $content) {
            self::assertStringContainsString($content, $this->view);
        }

        $script = (string) file_get_contents(ROOTPATH . 'public/assets/js/app.js');
        self::assertStringContainsString('initPortalNavigation', $script);
        self::assertStringContainsString('mcc-portal-nav-open', $script);
        self::assertStringContainsString("assets/js/app.js", $this->view);
        self::assertStringContainsString("matchMedia('(max-width: 991px)')", $script);
        self::assertStringContainsString("backdrop.setAttribute('aria-hidden'", $script);
        self::assertStringContainsString("navigationLinks.forEach", $script);
        self::assertStringContainsString('event.key === \'Escape\'', $script);
        self::assertStringContainsString("document.body.classList.toggle('mcc-portal-nav-open'", $script);
    }

    public function testDashboardUsesTheRequestedSectionOrderAndGridStructure(): void
    {
        $markers = [
            'welcome' => 'id="portal-welcome-heading"',
            'kpis' => 'class="mcc-portal-kpi-grid"',
            'schedule' => 'id="schedule"',
            'attendance' => 'id="attendance"',
            'matches' => 'id="matches"',
            'tournaments' => 'id="tournaments"',
            'development' => 'id="development"',
            'coach' => 'id="coach"',
            'notices' => 'id="notices"',
            'documents' => 'id="documents"',
            'activity' => 'id="activity"',
        ];
        $positions = [];
        foreach ($markers as $key => $marker) {
            $positions[$key] = strpos($this->view, $marker);
            self::assertNotFalse($positions[$key], $marker . ' should be present.');
        }

        $css = (string) file_get_contents(FCPATH . 'assets/css/app.css');
        self::assertStringContainsString('grid-template-columns: minmax(0, 1.35fr) minmax(280px, .65fr)', $css);
        self::assertStringContainsString('.mcc-portal-main #schedule', $css);
        self::assertStringContainsString('.mcc-portal-main #attendance', $css);
        self::assertStringContainsString('.mcc-portal-main #matches', $css);
        self::assertStringContainsString('.mcc-portal-main #development', $css);
        self::assertStringContainsString('#matches {', $css);
        self::assertStringContainsString('order: 30;', $css);
        self::assertStringContainsString('#development {', $css);
        self::assertStringContainsString('order: 50;', $css);
        self::assertStringContainsString('#notices {', $css);
        self::assertStringContainsString('order: 70;', $css);
    }
}
