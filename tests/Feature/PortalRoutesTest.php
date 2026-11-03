<?php

declare(strict_types=1);

namespace Tests\Feature;

use Config\Database;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

final class PortalRoutesTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    public function testPortalLoginPageRendersWithoutStaffSidebar(): void
    {
        $response = $this->get('student-login');

        self::assertSame(200, $response->response()->getStatusCode());
        self::assertStringContainsString('Login as Student', $response->getBody());
        self::assertStringContainsString('Login as Parent', $response->getBody());
        self::assertStringContainsString('Mobile number', $response->getBody());
        self::assertStringContainsString('Get OTP', $response->getBody());
        self::assertStringContainsString('mcc-auth-logo', $response->getBody());
        self::assertStringContainsString('alt="MCC Cricket Club"', $response->getBody());
        self::assertStringContainsString('name="mobile"', $response->getBody());
        self::assertStringContainsString('mcc-button-primary', $response->getBody());
        self::assertStringContainsString('csrf', strtolower($response->getBody()));
        self::assertStringNotContainsString('Development OTP — local testing only', $response->getBody());
        self::assertStringNotContainsString('mcc-sidebar', $response->getBody());
    }

    public function testStalePendingFlagDoesNotHideGetOtpState(): void
    {
        session()->destroy();
        session()->set('portal_otp_pending', true);

        $response = $this->get('student-login');

        self::assertStringContainsString('Get OTP', $response->getBody());
        self::assertStringNotContainsString('Verify OTP', $response->getBody());
        self::assertStringNotContainsString('name="otp"', $response->getBody());
        self::assertStringNotContainsString('Resend OTP', $response->getBody());
    }

    public function testConfiguredPortalSchemaReturnsAGenericEligibilityMessage(): void
    {
        session()->destroy();
        $db = Database::connect('default');
        $db->table('portal_otp_challenges')
            ->where([
                'account_type' => 'STUDENT',
                'session_hash' => hash('sha256', (string) session_id()),
            ])
            ->set('requested_at', gmdate('Y-m-d H:i:s', time() - 120))
            ->update();

        $response = $this->post('student-login', [
            'login_type' => 'student',
            'mobile' => '+919876543210',
            'action' => 'request',
            csrf_token() => csrf_hash(),
        ]);

        self::assertSame(302, $response->response()->getStatusCode());
        self::assertStringContainsString('student-login', (string) $response->response()->getHeaderLine('Location'));
        self::assertSame(
            'If the mobile number is eligible, an OTP has been sent.',
            session()->getFlashdata('message')
        );
        self::assertNull(session()->getFlashdata('error'));
        self::assertStringNotContainsString('PORTAL_STORAGE_UNAVAILABLE', $response->getBody());
    }

    public function testStudentMockOtpBrowserFlowRedirectsToStudentDashboard(): void
    {
        $this->resetPortalSession('STUDENT');
        $request = $this->requestOtpThroughBrowser('student', '+919900000101');
        self::assertStringContainsString('value="+919900000101"', $request->getBody());
        self::assertStringContainsString('value="student"', $request->getBody());
        $otp = $this->developmentOtp($request->getBody());

        $response = $this->post('student-login', [
            'login_type' => 'student',
            'mobile' => '+919900000101',
            'otp' => $otp,
            'action' => 'verify',
            csrf_token() => csrf_hash(),
        ]);

        self::assertSame(302, $response->response()->getStatusCode());
        self::assertStringEndsWith('/student-dashboard', (string) $response->response()->getHeaderLine('Location'));
        service('portalAuth')->logout();
    }

    public function testParentMockOtpBrowserFlowRedirectsToParentDashboard(): void
    {
        $this->resetPortalSession('PARENT');
        $request = $this->requestOtpThroughBrowser('parent', '+919900000102');
        self::assertStringContainsString('value="+919900000102"', $request->getBody());
        self::assertStringContainsString('value="parent"', $request->getBody());
        $otp = $this->developmentOtp($request->getBody());

        $response = $this->post('student-login', [
            'login_type' => 'parent',
            'mobile' => '+919900000102',
            'otp' => $otp,
            'action' => 'verify',
            csrf_token() => csrf_hash(),
        ]);

        self::assertSame(302, $response->response()->getStatusCode());
        self::assertStringEndsWith('/parent-dashboard', (string) $response->response()->getHeaderLine('Location'));
        service('portalAuth')->logout();
    }

    public function testStudentAchievementsRequiresPortalAuthentication(): void
    {
        session()->destroy();

        $response = $this->get('student-achievements');

        self::assertSame(302, $response->response()->getStatusCode());
        self::assertStringContainsString('student-login', (string) $response->response()->getHeaderLine('Location'));
    }

    public function testParentAchievementsRequiresPortalAuthentication(): void
    {
        session()->destroy();

        $response = $this->get('parent-achievements');

        self::assertSame(302, $response->response()->getStatusCode());
        self::assertStringContainsString('student-login', (string) $response->response()->getHeaderLine('Location'));
    }

    private function resetPortalSession(string $type): void
    {
        putenv('PORTAL_OTP_DEBUG=true');
        $_ENV['PORTAL_OTP_DEBUG'] = 'true';
        session()->destroy();
        Database::connect('default')->table('portal_otp_challenges')
            ->where([
                'account_type' => $type,
                'session_hash' => hash('sha256', (string) session_id()),
            ])
            ->set('requested_at', gmdate('Y-m-d H:i:s', time() - 120))
            ->update();
    }

    private function requestOtpThroughBrowser(string $type, string $mobile): object
    {
        $page = $this->get('student-login');
        $csrf = $this->csrfToken($page->getBody());

        return $this->post('student-login', [
            'login_type' => $type,
            'mobile' => $mobile,
            'action' => 'request',
            $csrf['name'] => $csrf['value'],
        ]);
    }

    private function developmentOtp(string $body): string
    {
        preg_match('/<span>(\d{6})<\/span>/', $body, $matches);
        self::assertArrayHasKey(1, $matches);
        return $matches[1];
    }

    /** @return array{name:string, value:string} */
    private function csrfToken(string $body): array
    {
        preg_match('/name="([^"]*csrf[^"]*)"[^>]*value="([^"]+)"/', $body, $matches);
        self::assertCount(3, $matches);
        return ['name' => $matches[1], 'value' => $matches[2]];
    }
}
