<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class CrmRedirectContractTest extends TestCase
{
    public function testLeadBrowserFailuresUseExplicitLeadTargets(): void
    {
        $controller = file_get_contents(
            ROOTPATH . 'app/Modules/CRM/Controllers/Browser.php',
        );

        self::assertIsString($controller);
        self::assertStringNotContainsString('redirect()->back()', $controller);
        self::assertStringContainsString(
            "redirect()->to('/crm/leads/' . \$id . '/edit')",
            $controller,
        );
        self::assertStringContainsString(
            "redirect()->to('/crm/leads/new')",
            $controller,
        );
        self::assertStringContainsString(
            "->with('field_errors', $this->leadFieldErrors($d ?? [], $e))",
            $controller,
        );
        self::assertStringContainsString(
            "redirect()->to('/crm/leads/' . \$id)",
            $controller,
        );
        self::assertStringContainsString(
            "redirect()->to('/crm/leads/' . \$leadId)",
            $controller,
        );
        self::assertSame(
            substr_count($controller, '->withInput()'),
            substr_count($controller, "->with('error', \$this->safe(\$e))"),
        );
    }

    public function testLeadCreateViewRendersFieldLevelErrors(): void
    {
        $view = file_get_contents(ROOTPATH . 'app/Modules/CRM/Views/create.php');

        self::assertIsString($view);
        self::assertStringContainsString("session()->getFlashdata('field_errors')", $view);
        self::assertStringContainsString("fieldError('full_name')", $view);
        self::assertStringContainsString("fieldError('email')", $view);
        self::assertStringContainsString("fieldError('branch_id')", $view);
        self::assertStringContainsString("fieldError('source')", $view);
        self::assertStringContainsString("fieldError('status')", $view);
    }
}
