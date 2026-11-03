<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

final class AdminRolePermissionRoutesTest extends TestCase
{
    public function testRolePermissionRouteAndViewUseCentralizedAuthorizationAndBulkPayload(): void
    {
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Admin/Config/Routes.php');
        $view = file_get_contents(ROOTPATH . 'app/Modules/Admin/Views/role.php');
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Admin/Controllers/Browser.php');

        self::assertStringContainsString("roles/(:num)/permissions", $routes);
        self::assertStringContainsString("filter' => 'permission:rbac.manage'", $routes);
        self::assertStringContainsString('name="permissions[]"', $view);
        self::assertStringContainsString('csrf_field()', $view);
        self::assertStringContainsString('expected_permission_version', $view);
        self::assertStringContainsString("getPost('permissions') ?? []", $controller);
        self::assertStringContainsString("getPost('permission_sync')", $controller);
        self::assertStringContainsString("withInput()", $controller);
    }
}
