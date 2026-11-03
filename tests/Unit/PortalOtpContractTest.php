<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class PortalOtpContractTest extends TestCase
{
    public function testOtpBoundaryUsesHashesCooldownAndProviderConfiguration(): void
    {
        $service = file_get_contents(APPPATH . 'Services/PortalAuthService.php');
        $provider = file_get_contents(APPPATH . 'Services/ConfiguredWhatsAppOtpProvider.php');
        $migration = file_get_contents(APPPATH . 'Database/Migrations/20261002000200_AddPortalOtpAuthentication.php');

        self::assertStringContainsString('random_int(100000, 999999)', $service);
        self::assertStringContainsString('password_hash($otp', $service);
        self::assertStringContainsString('password_verify(trim($otp)', $service);
        self::assertStringContainsString('RESEND_COOLDOWN_SECONDS', $service);
        self::assertStringContainsString('whatsapp.apiUrl', $provider);
        self::assertStringContainsString('whatsapp.token', $provider);
        self::assertStringContainsString('portal_otp_challenges', $migration);
        self::assertStringContainsString('mobile_verified_at', $migration);
        self::assertStringContainsString('session();', $service);
    }

    public function testOtpSourceDoesNotLogPlaintextOtpOrMobile(): void
    {
        $service = file_get_contents(APPPATH . 'Services/PortalAuthService.php');

        self::assertStringNotContainsString('log_message(\'error\', \'$mobile', $service);
        self::assertStringNotContainsString('log_message(\'error\', \'$otp', $service);
        self::assertStringNotContainsString("'otp' => \$otp", $service);
    }

    public function testDevelopmentOtpDisplayRequiresAllThreeServerSideGates(): void
    {
        $debug = file_get_contents(APPPATH . 'Services/PortalOtpDebugService.php');
        $view = file_get_contents(APPPATH . 'Modules/Portal/Views/login.php');

        self::assertStringContainsString("['development', 'testing']", $debug);
        self::assertStringContainsString("'mcc_cricket_club_test'", $debug);
        self::assertStringContainsString("PORTAL_OTP_DEBUG", $debug);
        self::assertStringContainsString('Development OTP — local testing only', $view);
        self::assertStringContainsString('developmentOtp', $view);
    }

    public function testOtpDebugHelperRejectsWrongDatabaseAndDisabledFlag(): void
    {
        $helper = new \App\Services\PortalOtpDebugService();
        $database = new class {
            public function getDatabase(): string
            {
                return 'mcc_cricket_club_test';
            }
        };
        $wrongDatabase = new class {
            public function getDatabase(): string
            {
                return 'mcc_cricket_club';
            }
        };

        $_ENV['PORTAL_OTP_DEBUG'] = 'true';
        self::assertTrue($helper->canDisplay($database));
        self::assertFalse($helper->canDisplay($wrongDatabase));
        self::assertFalse($helper->canDisplay($database, 'production', true));
        self::assertTrue($helper->canDisplay($database, 'testing', true));
        $_ENV['PORTAL_OTP_DEBUG'] = 'false';
        self::assertFalse($helper->canDisplay($database));
    }

    public function testMockProviderRequiresTheApprovedLocalGatesAndMakesNoHttpRequest(): void
    {
        $providerSource = file_get_contents(APPPATH . 'Services/MockWhatsAppOtpProvider.php');
        self::assertStringNotContainsString('curl_', $providerSource);

        $helper = new \App\Services\PortalOtpDebugService();
        $provider = new \App\Services\MockWhatsAppOtpProvider(
            new class {
                public function getDatabase(): string
                {
                    return 'mcc_cricket_club_test';
                }
            },
            $helper
        );

        putenv('PORTAL_OTP_DEBUG=true');
        $_ENV['PORTAL_OTP_DEBUG'] = 'true';
        self::assertTrue($provider->sendOtp('+919900000101', '123456'));

        $wrongDatabaseProvider = new \App\Services\MockWhatsAppOtpProvider(
            new class {
                public function getDatabase(): string
                {
                    return 'mcc_cricket_club';
                }
            },
            $helper
        );
        self::assertFalse($wrongDatabaseProvider->sendOtp('+919900000101', '123456'));

        putenv('PORTAL_OTP_DEBUG=false');
        $_ENV['PORTAL_OTP_DEBUG'] = 'false';
        self::assertFalse($provider->sendOtp('+919900000101', '123456'));
    }
}
