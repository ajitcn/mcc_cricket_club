<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class StaffLoginUiContractTest extends TestCase
{
    private string $view;

    private string $styles;

    protected function setUp(): void
    {
        $this->view = file_get_contents(APPPATH . 'Views/login.php');
        $this->styles = file_get_contents(FCPATH . 'assets/css/app.css');
    }

    public function testLoginCardUsesAccessibleBrandingAndSafeFormFields(): void
    {
        self::assertStringContainsString("site_url('assets/img/logo.png')", $this->view);
        self::assertStringContainsString('alt="MCC Cricket Club"', $this->view);
        self::assertStringContainsString('class="mcc-auth-card"', $this->view);
        self::assertStringContainsString('name="email"', $this->view);
        self::assertStringContainsString('name="password"', $this->view);
        self::assertStringContainsString('csrf_field()', $this->view);
        self::assertStringNotContainsString('name="password" value=', $this->view);
    }

    public function testLoginCardAndLogoUseResponsiveSharedLayoutRules(): void
    {
        self::assertStringContainsString('.mcc-auth-logo-wrap', $this->styles);
        self::assertStringContainsString('.mcc-auth-logo', $this->styles);
        self::assertStringContainsString('object-fit: contain', $this->styles);
        self::assertStringContainsString('justify-self: center', $this->styles);
        self::assertStringContainsString('@media(max-width:991px)', $this->styles);
        self::assertStringContainsString('@media(max-width:575px)', $this->styles);
    }
}
