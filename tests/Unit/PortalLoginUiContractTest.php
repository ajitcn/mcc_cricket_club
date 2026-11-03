<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class PortalLoginUiContractTest extends TestCase
{
    private string $view;

    protected function setUp(): void
    {
        $this->view = file_get_contents(APPPATH . 'Modules/Portal/Views/login.php');
    }

    public function testPortalLoginUsesTheAdminLoginStructureAndSharedControls(): void
    {
        self::assertStringContainsString('class="mcc-auth-shell"', $this->view);
        self::assertStringContainsString('class="mcc-auth-card"', $this->view);
        self::assertStringContainsString('class="mcc-auth-logo"', $this->view);
        self::assertStringContainsString("site_url('assets/img/logo.png')", $this->view);
        self::assertStringContainsString('name="mobile"', $this->view);
        self::assertStringContainsString('Get OTP', $this->view);
        self::assertStringContainsString('class="mcc-button mcc-button-primary mcc-button-block"', $this->view);
        self::assertStringContainsString('<?= csrf_field() ?>', $this->view);
    }

    public function testPortalLoginKeepsBothModesAndNonSensitiveInputContract(): void
    {
        self::assertStringContainsString('value="student"', $this->view);
        self::assertStringContainsString('value="parent"', $this->view);
        self::assertStringContainsString('name="otp"', $this->view);
        self::assertStringContainsString('Resend OTP', $this->view);
        self::assertStringContainsString('mcc-alert mcc-alert-danger', $this->view);
    }
}
