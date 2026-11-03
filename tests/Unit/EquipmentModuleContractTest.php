<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class EquipmentModuleContractTest extends TestCase
{
    public function testEquipmentUsesExplicitRoutesAndOperationPermissions(): void
    {
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Equipment/Config/Routes.php');
        $service = file_get_contents(ROOTPATH . 'app/Services/EquipmentService.php');

        self::assertIsString($routes);
        self::assertIsString($service);
        self::assertStringContainsString("Browser::index", $routes);
        self::assertStringContainsString("Browser::create", $routes);
        self::assertStringContainsString("Browser::update", $routes);
        self::assertStringContainsString("Browser::delete", $routes);
        self::assertStringContainsString('permission:equipment.view', $routes);
        self::assertStringContainsString('permission:equipment.create', $routes);
        self::assertStringContainsString('permission:equipment.update', $routes);
        self::assertStringContainsString('permission:equipment.delete', $routes);
        self::assertStringContainsString("where('e.deleted_at', null)", $service);
        self::assertStringContainsString('branchIdsForPermission', $service);
        self::assertStringContainsString("'equipment_created'", $service);
        self::assertStringContainsString("'equipment_updated'", $service);
        self::assertStringContainsString("'equipment_deleted'", $service);
    }

    public function testEquipmentFormsUseCsrfAndPreserveInputOnFailures(): void
    {
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Equipment/Controllers/Browser.php');
        $view = file_get_contents(ROOTPATH . 'app/Modules/Equipment/Views/create.php');

        self::assertIsString($controller);
        self::assertIsString($view);
        self::assertStringContainsString('csrf_field()', $view);
        self::assertStringContainsString("withInput()", $controller);
        self::assertStringContainsString("old('name'", $view);
        self::assertStringContainsString("old('quantity'", $view);
        self::assertStringContainsString('name="branch_id"', $view);
        self::assertStringContainsString('type="number"', $view);
    }

    public function testEquipmentModelRetainsSoftDeletedRecords(): void
    {
        $model = file_get_contents(ROOTPATH . 'app/Modules/Equipment/Models/EquipmentModel.php');

        self::assertIsString($model);
        self::assertStringContainsString('protected $useSoftDeletes = true;', $model);
        self::assertStringContainsString('protected $deletedField = \'deleted_at\';', $model);
        self::assertStringContainsString("'branch_id'", $model);
    }
}
