<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class PlayerDevelopmentContractTest extends TestCase
{
    public function testDevelopmentRoutesUsePermissionsAndDoNotOwnPerformanceData(): void
    {
        $routes = file_get_contents(APPPATH . 'Modules/Development/Config/Routes.php');
        $service = file_get_contents(APPPATH . 'Services/PlayerDevelopmentService.php');
        self::assertStringContainsString('permission:development.view', $routes);
        self::assertStringContainsString('permission:development.progress', $routes);
        self::assertStringContainsString('development_progress_history', $service);
        self::assertStringNotContainsString('performance_records', $service);
    }

    public function testDevelopmentMutationsUseTransactionsAndActiveAuditConnection(): void
    {
        $service = file_get_contents(APPPATH . 'Services/PlayerDevelopmentService.php');
        $audit = file_get_contents(APPPATH . 'Services/AuditPersistenceService.php');

        self::assertStringContainsString('$db->transBegin()', $service);
        self::assertStringContainsString('$db->transCommit()', $service);
        self::assertStringContainsString('$db->transRollback()', $service);
        self::assertStringContainsString("'development_goal_created'", $service);
        self::assertStringContainsString("'development_progress_recorded'", $service);
        self::assertStringContainsString("                \$db\n            );", $service);
        self::assertStringContainsString('$connection = $db ?? Database::connect(\'default\');', $audit);
        self::assertStringContainsString('Unable to persist audit event.', $audit);
    }

    public function testProgressUsesOptimisticVersionAndDoesNotDefineAProgressScale(): void
    {
        $service = file_get_contents(APPPATH . 'Services/PlayerDevelopmentService.php');
        $migration = file_get_contents(APPPATH . 'Database/Migrations/20260915000100_AddDevelopmentGoalVersion.php');

        self::assertStringContainsString("'version' => (int) \$expectedVersion", $service);
        self::assertStringContainsString("'version' => \$nextVersion", $service);
        self::assertStringContainsString('$db->affectedRows() !== 1', $service);
        self::assertStringContainsString('Development goal was changed by another user.', $service);
        self::assertStringContainsString('public function planIdForGoal', $service);
        self::assertStringContainsString('Completed development plans cannot be edited or extended.', $service);
        self::assertStringContainsString('public function editable', $service);
        self::assertStringContainsString("'version' => [", $migration);
        self::assertStringNotContainsString('rating', strtolower($service));
        self::assertStringNotContainsString('formula', strtolower($service));
    }

    public function testCoachMustBeEnabledCoachWithActiveProfileAndBranchAuthorization(): void
    {
        $service = file_get_contents(APPPATH . 'Services/PlayerDevelopmentService.php');

        self::assertStringContainsString("'u.is_enabled' => 1", $service);
        self::assertStringContainsString("'cp.is_active' => 1", $service);
        self::assertStringContainsString("'r.name' => 'Coach'", $service);
        self::assertStringContainsString("'ub.branch_id' => \$branch", $service);
    }

    public function testPlayerMustBelongToTheSubmittedBranch(): void
    {
        $service = file_get_contents(APPPATH . 'Services/PlayerDevelopmentService.php');

        self::assertStringContainsString("->where(['id' => \$player, 'branch_id' => \$branch])", $service);
        self::assertStringContainsString('Player is not eligible.', $service);
    }

    public function testDevelopmentReadsUsePermissionLineageAndPagination(): void
    {
        $service = file_get_contents(APPPATH . 'Services/PlayerDevelopmentService.php');

        self::assertStringContainsString('private function lineageQuery', $service);
        self::assertStringContainsString('$this->lineageQuery(Database::connect(\'default\'))', $service);
        self::assertStringContainsString('public function forPlayer', $service);
        self::assertStringContainsString("can('development.view'", $service);
        self::assertStringContainsString('p.id = d.player_id AND p.branch_id = d.branch_id', $service);
        self::assertStringContainsString('eligibleCoachExists', $service);
        self::assertStringContainsString('countAllResults()', $service);
        self::assertStringContainsString('->get($perPage, ($page - 1) * $perPage)', $service);
        self::assertStringContainsString("'branch_id'", $service);
        self::assertStringContainsString("'status'", $service);
        self::assertStringContainsString('public function formOptions', $service);
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Development/Controllers/Browser.php');
        self::assertStringContainsString('$formRoute = $id === null', $controller);
        self::assertStringContainsString('redirect()->to($formRoute)', $controller);
        self::assertStringContainsString("'/development/' . \$id . '/edit'", $controller);
        self::assertStringContainsString("->withInput()", $controller);
        self::assertStringContainsString("->with('error', \$this->safe(\$exception))", $controller);
        self::assertStringContainsString("redirect()->to('/development/' . \$planId)", $controller);
        self::assertStringNotContainsString('redirect()->back()', $controller);
        self::assertStringContainsString('$id = (int) $db->insertID();', $service);
        self::assertStringNotContainsString('$id = (int) $db->table(\'development_plans\')->insert(', $service);
    }

    public function testDevelopmentSelectorsAreBranchScopedAndCoachAuthorized(): void
    {
        $service = file_get_contents(APPPATH . 'Services/PlayerDevelopmentService.php');
        $view = file_get_contents(ROOTPATH . 'app/Modules/Development/Views/form.php');
        $script = file_get_contents(ROOTPATH . 'public/assets/js/app.js');
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Development/Controllers/Browser.php');

        self::assertStringContainsString('public function formOptions', $service);
        self::assertStringContainsString("'options' => service('development')->formOptions(", $controller);
        self::assertStringContainsString("'options' => \$development->formOptions((int) \$plan['branch_id'])", $controller);
        self::assertStringContainsString("'u.is_enabled' => 1", $service);
        self::assertStringContainsString("'cp.is_active' => 1", $service);
        self::assertStringContainsString("'r.name' => 'Coach'", $service);
        self::assertStringContainsString("->whereIn('ub.branch_id', \$branches)", $service);
        self::assertStringContainsString("\$coachQuery->where('ub.branch_id', \$selectedBranch)", $service);
        self::assertStringContainsString('data-development-player', $view);
        self::assertStringContainsString('data-development-coach', $view);
        self::assertStringContainsString('class="form-control selectpicker btSelect"', $view);
        self::assertStringContainsString('data-bt-select', $view);
        self::assertStringContainsString('data-live-search="true"', $view);
        self::assertStringContainsString('data-actions-box="true"', $view);
        self::assertStringNotContainsString('data-bt-select-search-placeholder', $view);
        self::assertStringNotContainsString('data-development-branch-option', $view);
        self::assertStringContainsString('title="Select a Player"', $view);
        self::assertStringContainsString('title="Select a Coach"', $view);
        self::assertStringContainsString('<option value="" hidden selected>Select a Player</option>', $view);
        self::assertStringContainsString('<option value="" hidden selected>Select an eligible Coach</option>', $view);
        self::assertStringContainsString('No eligible Players are available.', $view);
        self::assertStringContainsString('No eligible Coaches are available.', $view);
        self::assertStringNotContainsString('id="development-player"\n                name="player_id"\n                multiple', $view);
        self::assertStringNotContainsString('id="development-coach"\n                name="coach_user_id"\n                multiple', $view);
        self::assertStringContainsString('name="branch_id"', $view);
        self::assertStringContainsString('name="player_id"', $view);
        self::assertStringContainsString('name="coach_user_id"', $view);
        self::assertStringContainsString('name="program_id"', $view);
        self::assertStringContainsString('name="batch_id"', $view);
        self::assertStringContainsString("'programs' =>", $service);
        self::assertStringContainsString("'batches' =>", $service);
        self::assertStringContainsString("->whereIn('p.branch_id', \$branches)", $service);
        self::assertStringContainsString("->whereIn('ub.branch_id', \$branches)", $service);
        self::assertStringContainsString("->whereIn('ba.branch_id', \$branches)", $service);
        self::assertStringContainsString("foreach (\$options['players'] as \$player)", $view);
        self::assertStringContainsString("foreach (\$options['coaches'] as \$coach)", $view);
        self::assertStringContainsString("(string) \$coach['id'] === \$selectedCoach ? 'selected' : ''", $view);
        self::assertStringContainsString('data-development-coach', $view);
        self::assertStringContainsString("var visible = ! branchId || option.dataset.branchId === branchId;", $script);
        self::assertStringContainsString('select.multiple', $script);
        self::assertStringContainsString("input.type = multiple ? 'checkbox' : 'radio'", $script);
        self::assertStringContainsString("search.placeholder = 'Search players'", $script);
        self::assertStringContainsString('select.selectedOptions[0].textContent.trim()', $script);
        self::assertStringContainsString("empty.textContent = 'No results found.'", $script);
        self::assertStringContainsString("event.key === 'ArrowDown'", $script);
        self::assertStringContainsString('data-branch-id=', $view);
        self::assertStringContainsString('data-player-id=', $view);
    }

    public function testDevelopmentFormUsesSharedLayoutDatepickerErrorsAndPreservedValues(): void
    {
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Development/Controllers/Browser.php');
        $view = file_get_contents(ROOTPATH . 'app/Modules/Development/Views/form.php');
        $styles = file_get_contents(ROOTPATH . 'public/assets/css/app.css');
        $script = file_get_contents(ROOTPATH . 'public/assets/js/app.js');

        self::assertStringContainsString("'pageActions' =>", $view);
        self::assertStringContainsString('mcc-card-heading', $view);
        self::assertStringContainsString('mcc-form-grid', $view);
        self::assertStringContainsString('mcc-form-actions', $view);
        self::assertStringContainsString('csrf_field()', $view);
        self::assertStringContainsString('data-mcc-date-picker', $view);
        self::assertStringNotContainsString('type="date"', $view);
        self::assertStringNotContainsString('type="datetime-local"', $view);
        self::assertStringContainsString("old('title'", $view);
        self::assertStringContainsString("old('target_date'", $view);
        self::assertStringContainsString('field_errors', $controller);
        self::assertStringContainsString('mcc-field-error', $view);
        self::assertStringContainsString('data-bt-select', $view);
        self::assertStringContainsString('aria-label', $script);
        self::assertStringContainsString('.mcc-bt-select-menu', $styles);
        self::assertStringNotContainsString('.mcc-development-form .mcc-bt-select-menu', $styles);
        self::assertStringContainsString('.mcc-development-form .mcc-form-field', $styles);
    }

    public function testDatesAndProgressAreStrictlyValidated(): void
    {
        $service = file_get_contents(APPPATH . 'Services/PlayerDevelopmentService.php');

        self::assertStringContainsString('Invalid development date.', $service);
        self::assertStringContainsString('Invalid development progress.', $service);
        self::assertStringContainsString('! $this->validDate($value)', $service);
        self::assertStringContainsString('! is_numeric($rawValue)', $service);
        self::assertStringContainsString('FILTER_VALIDATE_INT', $service);
    }

    public function testRemainingDevelopmentPolicyIsNotInvented(): void
    {
        $decisions = file_get_contents(ROOTPATH . 'docs/DECISIONS.md');

        self::assertStringContainsString('DEV-01', $decisions);
        self::assertStringContainsString('DEV-02', $decisions);
        self::assertStringContainsString('PA-02', $decisions);
    }
}
