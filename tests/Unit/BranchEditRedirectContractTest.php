<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class BranchEditRedirectContractTest extends TestCase
{
    public function testBranchCreateUsesExplicitCreateRouteAndFieldErrors(): void
    {
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Admin/Controllers/Browser.php');
        $view = file_get_contents(ROOTPATH . 'app/Modules/Admin/Views/branch_form.php');

        self::assertIsString($controller);
        self::assertIsString($view);
        self::assertStringContainsString("->to('/admin/branches/create')", $controller);
        self::assertStringContainsString('public function createBranch(): ResponseInterface|string', $controller);
        self::assertStringContainsString('branchSafe($e)', $controller);
        self::assertStringContainsString('$errors[\'name\']', $view);
        self::assertStringContainsString('$errors[\'code\']', $view);
        self::assertStringContainsString('$errors[\'address\']', $view);
    }

    public function testBranchUpdateUsesExplicitEditRouteAndPreservesInput(): void
    {
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Admin/Controllers/Browser.php');
        $view = file_get_contents(ROOTPATH . 'app/Modules/Admin/Views/branch_form.php');

        self::assertIsString($controller);
        self::assertIsString($view);
        self::assertStringContainsString('->to(\'/admin/branches/\' . $id . \'/edit\')', $controller);
        self::assertStringContainsString('->withInput()', $controller);
        $updateStart = strpos($controller, 'public function updateBranch');
        $statusStart = strpos($controller, 'public function setBranchActive');
        self::assertIsInt($updateStart);
        self::assertIsInt($statusStart);
        $updateMethod = substr($controller, $updateStart, $statusStart - $updateStart);
        self::assertStringNotContainsString('redirect()->back()', $updateMethod);
        self::assertStringContainsString('$errors = session()->getFlashdata(\'errors\') ?? [];', $view);
        self::assertStringContainsString('$errors[\'name\']', $view);
        self::assertStringContainsString('$errors[\'code\']', $view);
        self::assertStringContainsString('$errors[\'address\']', $view);
    }
}
