<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ProgramBatchModuleContractTest extends TestCase
{
    public function testProgramAndBatchRoutesUseCentralizedPermissions(): void
    {
        $programRoutes = file_get_contents(ROOTPATH . 'app/Modules/Programs/Config/Routes.php');
        $batchRoutes = file_get_contents(ROOTPATH . 'app/Modules/Batches/Config/Routes.php');

        self::assertIsString($programRoutes);
        self::assertIsString($batchRoutes);
        self::assertStringContainsString('permission:program.view', $programRoutes);
        self::assertStringContainsString('permission:program.create', $programRoutes);
        self::assertStringContainsString('(:num)/edit', $programRoutes);
        self::assertStringContainsString('permission:program.create', $programRoutes);
        self::assertStringContainsString('permission:batch.view', $batchRoutes);
        self::assertStringContainsString('permission:batch.create', $batchRoutes);
        self::assertStringContainsString('(:num)/edit', $batchRoutes);
        self::assertStringContainsString('permission:batch.create', $batchRoutes);
    }

    public function testBatchCreationIsGlobalAndDoesNotRequireBranchOrProgram(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Services/BatchManagementService.php');

        self::assertIsString($service);
        self::assertStringContainsString("\$this->allow('batch.create')", $service);
        self::assertStringContainsString("'branch_id' => null", $service);
        self::assertStringContainsString("'program_id' => null", $service);
        self::assertStringContainsString("'is_active' => \$isActive", $service);
    }

    public function testBatchSchemaDoesNotReintroduceCapacity(): void
    {
        $model = file_get_contents(ROOTPATH . 'app/Modules/Players/Models/BatchModel.php');
        $migration = file_get_contents(ROOTPATH . 'app/Database/Migrations/20260922000200_CreateProgramsAndLinkBatches.php');
        $globalMigration = file_get_contents(ROOTPATH . 'app/Database/Migrations/20261012000300_MakeBatchesGlobal.php');

        self::assertIsString($model);
        self::assertIsString($migration);
        self::assertIsString($globalMigration);
        self::assertStringContainsString("'program_id'", $model);
        self::assertStringNotContainsString("'capacity'", $model);
        self::assertStringContainsString('program_id', $migration);
        self::assertStringContainsString('programs', $migration);
        self::assertStringContainsString('is_active', $globalMigration);
        self::assertStringContainsString('MODIFY branch_id BIGINT UNSIGNED NULL', $globalMigration);
    }

    public function testBatchEditUsesGlobalAuthorizationAndAuditsStatus(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Services/BatchManagementService.php');
        $view = file_get_contents(ROOTPATH . 'app/Modules/Batches/Views/create.php');
        $list = file_get_contents(ROOTPATH . 'app/Modules/Batches/Views/index.php');

        self::assertStringContainsString('public function editForm', $service);
        self::assertStringContainsString('public function update', $service);
        self::assertStringContainsString("\$this->allow('batch.create')", $service);
        self::assertStringContainsString('batch_updated', $service);
        self::assertStringContainsString("'is_active' => (int) (\$before['is_active'] ?? 1)", $service);
        self::assertStringContainsString("\$editing ? 'Edit batch' : 'Create batch'", $view);
        self::assertStringContainsString('Batch Details', $view);
        self::assertStringNotContainsString('name="branch_id"', $view);
        self::assertStringNotContainsString('name="program_id"', $view);
        self::assertStringContainsString('is_active', $view);
        self::assertStringContainsString("batches/' . (int) \$batch['id'] . '/edit", $list);
        self::assertStringNotContainsString('branch_name', $list);
        self::assertStringNotContainsString('program_name', $list);
    }

    public function testProgramEditPreservesRelationshipsAndUsesCentralizedAuthorization(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Services/ProgramManagementService.php');
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Programs/Controllers/Browser.php');
        $view = file_get_contents(ROOTPATH . 'app/Modules/Programs/Views/index.php');
        $form = file_get_contents(ROOTPATH . 'app/Modules/Programs/Views/create.php');

        self::assertIsString($service);
        self::assertIsString($controller);
        self::assertIsString($view);
        self::assertIsString($form);
        self::assertStringContainsString('public function editForm', $service);
        self::assertStringContainsString('public function update', $service);
        self::assertStringContainsString("\$this->allow('program.create')", $service);
        self::assertStringContainsString('program_updated', $service);
        self::assertStringContainsString('public function editPage', $controller);
        self::assertStringContainsString('public function update', $controller);
        self::assertStringContainsString('programs/\' . (int) $program[\'id\'] . \'/edit', $view);
        self::assertStringContainsString('csrf_field()', $form);
        self::assertStringContainsString("old('name', \$program['name'] ?? '')", $form);
    }

    public function testProgramDescriptionUsesTheSharedFormAndEscapedListOutput(): void
    {
        $migration = file_get_contents(ROOTPATH . 'app/Database/Migrations/20261022000100_AddProgramDescription.php');
        $model = file_get_contents(ROOTPATH . 'app/Modules/Programs/Models/ProgramModel.php');
        $service = file_get_contents(ROOTPATH . 'app/Services/ProgramManagementService.php');
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Programs/Controllers/Browser.php');
        $form = file_get_contents(ROOTPATH . 'app/Modules/Programs/Views/create.php');
        $list = file_get_contents(ROOTPATH . 'app/Modules/Programs/Views/index.php');
        $styles = file_get_contents(FCPATH . 'assets/css/app.css');

        self::assertStringContainsString("fieldExists('description', 'programs')", $migration);
        self::assertStringContainsString("'type' => 'TEXT'", $migration);
        self::assertStringContainsString("'description'", $model);
        self::assertStringContainsString("name=\"description\"", $form);
        self::assertStringContainsString('<textarea', $form);
        self::assertStringContainsString('maxlength="5000"', $form);
        self::assertStringContainsString("old('description', \$program['description'] ?? '')", $form);
        self::assertStringContainsString('nl2br(esc((string) $program[\'description\']))', $list);
        self::assertStringContainsString('DESCRIPTION_MAX_LENGTH = 5000', $service);
        self::assertStringContainsString("'description' => \$description", $service);
        self::assertStringContainsString("'description' => 'permit_empty|max_length[5000]'", $controller);
        self::assertStringContainsString('Program description must not exceed 5,000 characters.', $controller);
        self::assertStringContainsString('.mcc-program-form-fields {', $styles);
        self::assertStringContainsString('grid-template-columns: minmax(0, 1fr);', $styles);
    }

    public function testCoachProgramAccessUsesExistingPermissionsAndGlobalScope(): void
    {
        $migration = file_get_contents(ROOTPATH . 'app/Database/Migrations/20261022000200_GrantCoachProgramPermissions.php');
        $seed = file_get_contents(ROOTPATH . 'app/Database/Seeds/TestDatasetSeeder.php');
        $navigation = file_get_contents(ROOTPATH . 'app/Services/NavigationService.php');
        $service = file_get_contents(ROOTPATH . 'app/Services/ProgramManagementService.php');
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Programs/Config/Routes.php');

        self::assertStringContainsString("'program.view'", $migration);
        self::assertStringContainsString("'program.create'", $migration);
        self::assertStringContainsString("->where('name', 'Coach')", $migration);
        self::assertStringContainsString("'program.view',", $seed);
        self::assertStringContainsString("'program.create',", $seed);
        self::assertStringContainsString('$this->item(\'program.view\', \'Programs\', \'programs\'', $navigation);
        self::assertStringContainsString('$this->allow(\'program.view\')', $service);
        self::assertStringContainsString('$this->allow(\'program.create\')', $service);
        self::assertStringContainsString('permission:program.view', $routes);
        self::assertStringContainsString('permission:program.create', $routes);
        self::assertStringNotContainsString('program.delete', $migration);
        self::assertStringNotContainsString('batch.create', $migration);
        self::assertStringNotContainsString('branch.manage', $migration);
    }
}
