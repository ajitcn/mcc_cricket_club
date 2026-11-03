<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

final class ProgramBatchRoutesTest extends TestCase
{
    public function testBatchEditIsExplicitlyProtectedAndUsesSharedFormControls(): void
    {
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Batches/Config/Routes.php');
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Batches/Controllers/Browser.php');
        $view = file_get_contents(ROOTPATH . 'app/Modules/Batches/Views/create.php');

        self::assertStringContainsString("(:num)/edit", $routes);
        self::assertStringContainsString("permission:batch.create", $routes);
        self::assertStringContainsString('public function editPage', $controller);
        self::assertStringContainsString('public function update', $controller);
        self::assertStringContainsString('csrf_field()', $view);
        self::assertStringContainsString('Batch Details', $view);
        self::assertStringContainsString('name="is_active"', $view);
        self::assertStringNotContainsString('name="program_id"', $view);
        self::assertStringNotContainsString('name="branch_id"', $view);
        self::assertStringContainsString('withInput()', $controller);
    }

    public function testProgramEditIsExplicitlyProtectedAndUsesSharedFormControls(): void
    {
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Programs/Config/Routes.php');
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Programs/Controllers/Browser.php');
        $view = file_get_contents(ROOTPATH . 'app/Modules/Programs/Views/create.php');

        self::assertStringContainsString("(:num)/edit", $routes);
        self::assertStringContainsString("permission:program.create", $routes);
        self::assertStringContainsString('public function editPage', $controller);
        self::assertStringContainsString('public function update', $controller);
        self::assertStringContainsString('withInput()', $controller);
        self::assertStringContainsString('csrf_field()', $view);
        self::assertStringContainsString('name="name"', $view);
        self::assertStringContainsString('name="description"', $view);
        self::assertStringContainsString('maxlength="5000"', $view);
        self::assertStringContainsString("old('description', \$program['description'] ?? '')", $view);
        self::assertStringContainsString("'description' => 'permit_empty|max_length[5000]'", $controller);
        self::assertStringContainsString("unset(\$input[csrf_token()])", $controller);
    }

    public function testBranchEditRemainsExplicitlyProtectedAndAudited(): void
    {
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Admin/Config/Routes.php');
        $service = file_get_contents(ROOTPATH . 'app/Services/AdminService.php');
        $view = file_get_contents(ROOTPATH . 'app/Modules/Admin/Views/branch_form.php');

        self::assertStringContainsString("branches/(:num)/edit", $routes);
        self::assertStringContainsString("permission:rbac.manage", $routes);
        self::assertStringContainsString('public function updateBranch', $service);
        self::assertStringContainsString('branch_updated', $service);
        self::assertStringContainsString('old(\'name\'', $view);
        self::assertStringContainsString('csrf_field()', $view);
    }
}
