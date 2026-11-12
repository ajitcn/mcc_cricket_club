<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class PortalAuthenticationContractTest extends TestCase
{
    public function testPortalBoundaryUsesSeparateRoutesAndSessionFields(): void
    {
        $routes = file_get_contents(APPPATH . 'Modules/Portal/Config/Routes.php');
        $filter = file_get_contents(APPPATH . 'Filters/PortalAuth.php');
        $service = file_get_contents(APPPATH . 'Services/PortalAuthService.php');

        self::assertStringContainsString("student-login", $routes);
        self::assertStringContainsString("student-dashboard", $routes);
        self::assertStringContainsString("parent-dashboard", $routes);
        self::assertStringContainsString("student-achievements", $routes);
        self::assertStringContainsString("parent-achievements", $routes);
        self::assertStringContainsString("'portal'", file_get_contents(APPPATH . 'Config/Filters.php'));
        self::assertStringContainsString('portal_authenticated', $filter . $service);
        self::assertStringContainsString('password_hash', $service);
        self::assertStringContainsString('password_verify', $service);
        self::assertStringContainsString('mobile_verified_at', $service);
        self::assertStringContainsString('relationship_type', $service);
        self::assertStringNotContainsString("session()->set(['isLoggedIn'", $service);
    }

    public function testPortalAchievementVisibilityIsPublishedAndRelationshipScoped(): void
    {
        $service = file_get_contents(APPPATH . 'Services/PortalAuthService.php');
        $view = file_get_contents(APPPATH . 'Modules/Portal/Views/dashboard.php');

        self::assertStringContainsString("whereIn('aa.status', ['approved', 'published'])", $service);
        self::assertStringContainsString("where('aa.recipient_type', 'player')", $service);
        self::assertStringContainsString('whereIn(\'aa.player_id\', $playerIds)', $service);
        self::assertStringContainsString("where('aa.branch_id = p.branch_id', null, false)", $service);
        self::assertStringNotContainsString("nomination_note", $service);
        self::assertStringNotContainsString("lifecycle_reason", $service);
        self::assertStringContainsString('No published achievements available', $view);
        self::assertStringContainsString('Published recognition', $view);
    }

    public function testPortalLoginViewKeepsRadioChoiceAndCsrfProtection(): void
    {
        $view = file_get_contents(APPPATH . 'Modules/Portal/Views/login.php');

        self::assertStringContainsString('name="login_type"', $view);
        self::assertStringContainsString('value="student"', $view);
        self::assertStringContainsString('value="parent"', $view);
        self::assertStringContainsString('csrf_field()', $view);
        self::assertStringContainsString('Get OTP', $view);
        self::assertStringContainsString('Verify OTP', $view);
    }
}
