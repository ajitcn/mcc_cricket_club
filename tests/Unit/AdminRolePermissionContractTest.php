<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AdminRolePermissionContractTest extends TestCase
{
    public function testRolePermissionAssignmentUsesApprovedBtSelectMultiSelect(): void
    {
        $view = file_get_contents(ROOTPATH . 'app/Modules/Admin/Views/role.php');
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Admin/Controllers/Browser.php');
        $service = file_get_contents(ROOTPATH . 'app/Services/AdminService.php');
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Admin/Config/Routes.php');
        $script = file_get_contents(ROOTPATH . 'public/assets/js/app.js');
        $styles = file_get_contents(ROOTPATH . 'public/assets/css/app.css');

        self::assertStringContainsString('Assign approved permission', $view);
        self::assertStringContainsString('name="permissions[]"', $view);
        self::assertStringContainsString('multiple', $view);
        self::assertStringContainsString('selectpicker btSelect', $view);
        self::assertStringContainsString('expected_permission_version', $view);
        self::assertStringContainsString('permission_sync', $view);
        self::assertStringContainsString('No approved permissions available.', $view);
        self::assertStringContainsString('permissionCatalog()', $controller);
        self::assertStringContainsString('syncPermissions', $controller);
        self::assertStringContainsString('permissionVersion', $service);
        self::assertStringContainsString('transBegin()', $service);
        self::assertStringContainsString('transRollback()', $service);
        self::assertStringContainsString('role_permissions_updated', $service);
        self::assertStringContainsString('added', $service);
        self::assertStringContainsString('removed', $service);
        self::assertStringContainsString('permission:rbac.manage', $routes);
        self::assertStringContainsString('data-bt-select-search-placeholder', $view);
        self::assertStringContainsString("data-bt-select-search-placeholder') || 'Search options'", $script);
        self::assertStringContainsString('label.dataset.btSelectValue', $script);
        self::assertStringContainsString('.mcc-role-permission-form', $styles);
        self::assertStringContainsString('z-index: 1201', $styles);
    }

    public function testRolePermissionAssignmentRejectsUnknownValuesAndProtectsSelfCriticalAccess(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Services/AdminService.php');

        self::assertStringContainsString("!in_array(\$permission, self::PERMISSIONS, true)", $service);
        self::assertStringContainsString('Duplicate permission selection.', $service);
        self::assertStringContainsString("\$ownsRole && in_array('rbac.manage'", $service);
        self::assertStringContainsString("hash_equals(\$this->permissionVersion(\$currentAll), \$expectedVersion)", $service);
        self::assertStringContainsString("'permission' => \$permission", $service);
    }
}
