<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class TournamentManagementContractTest extends TestCase
{
    public function testTournamentRoutesAndStandingsContractExist(): void
    {
        $routes = file_get_contents(APPPATH . 'Modules/Tournaments/Config/Routes.php');
        $service = file_get_contents(APPPATH . 'Services/TournamentManagementService.php');

        self::assertStringContainsString('permission:tournament.view', $routes);
        self::assertStringContainsString('permission:tournament.update', $routes);
        self::assertStringContainsString('tournament_fixtures', $service);
        self::assertStringContainsString("'no_result'", $service);
        self::assertStringContainsString("where('tf.status', 'completed')", $service);
        self::assertStringContainsString('emptyStanding', $service);
        self::assertStringContainsString('seenFixtures', $service);
        self::assertStringContainsString("'position'", $service);
        self::assertStringNotContainsString('Tournament Management', $service);
    }

    public function testTournamentReadsAndWritesUseBranchLineage(): void
    {
        $service = file_get_contents(APPPATH . 'Services/TournamentManagementService.php');

        self::assertIsString($service);
        self::assertStringContainsString("authorizedBranches('tournament.view')", $service);
        self::assertStringContainsString("->can(\$permission", $service);
        self::assertStringNotContainsString("session('authorizedBranches')", $service);
        self::assertStringContainsString('p.branch_id', $service);
        self::assertStringContainsString('m.branch_id', $service);
        self::assertStringContainsString('g.branch_id', $service);
    }

    public function testTeamTextareaInputIsSplitIntoSeparateParticipantRecords(): void
    {
        $service = file_get_contents(APPPATH . 'Services/TournamentManagementService.php');

        self::assertStringContainsString("preg_split('/\\R/'", $service);
        self::assertStringContainsString('array_unique($teams)', $service);
    }

    public function testFixtureWorkflowIsTransactionalAndPreventsDuplicateMatchLinks(): void
    {
        $service = file_get_contents(APPPATH . 'Services/TournamentManagementService.php');

        self::assertIsString($service);
        self::assertStringContainsString('transStart', $service);
        self::assertStringContainsString('postponeFixture', $service);
        self::assertStringContainsString('cancelFixture', $service);
        self::assertStringContainsString('completeFixture', $service);
        self::assertStringContainsString('transRollback', $service);
        self::assertStringContainsString('auditPersistence', $service);
    }

    public function testFixtureDetailAndFormKeepStatusActionsAndReadableHistoryContracts(): void
    {
        $controller = file_get_contents(APPPATH . 'Modules/Tournaments/Controllers/Browser.php');
        $service = file_get_contents(APPPATH . 'Services/TournamentManagementService.php');
        $detail = file_get_contents(APPPATH . 'Modules/Tournaments/Views/fixture_show.php');
        $form = file_get_contents(APPPATH . 'Modules/Tournaments/Views/fixture_form.php');

        self::assertStringContainsString('fixtureFailure', $controller);
        self::assertStringContainsString('Cancelled fixtures cannot be completed.', $service);
        self::assertStringContainsString('entity_type, al.entity_id', $service);
        self::assertStringContainsString('Fixture history', $detail);
        self::assertStringContainsString('Previous values', $detail);
        self::assertStringContainsString('New values', $detail);
        self::assertStringContainsString('Back to Tournament', $detail);
        self::assertStringContainsString('data-mcc-date-picker', $form);
        self::assertStringContainsString('name="start_time"', $form);
        self::assertStringContainsString('name="match_number"', $form);
        self::assertStringContainsString('Match Number', $form);
        self::assertStringContainsString("'match_number'", $service);
        self::assertStringContainsString('name="opponent_ids[]"', $form);
        self::assertStringContainsString('data-bt-select-placeholder="Choose opponents"', $form);
        self::assertStringContainsString("'opponent_ids'", $service);
        self::assertStringNotContainsString('json_encode(', $detail);
    }

    public function testTournamentMediumWorkflowsAreCovered(): void
    {
        $service = file_get_contents(APPPATH . 'Services/TournamentManagementService.php');
        $form = file_get_contents(APPPATH . 'Modules/Tournaments/Views/form.php');
        $list = file_get_contents(APPPATH . 'Modules/Tournaments/Views/index.php');
        $detail = file_get_contents(APPPATH . 'Modules/Tournaments/Views/show.php');

        self::assertIsString($service);
        self::assertIsString($form);
        self::assertIsString($list);
        self::assertIsString($detail);
        self::assertStringContainsString('validateExistingFixtures', $service);
        self::assertStringContainsString('calendarDate', $service);
        self::assertStringContainsString('localFixtureDateTime', $service);
        self::assertStringContainsString('orderBy(\'t.starts_on\', \'DESC\')', $service);
        self::assertStringContainsString('data-tournament-submit-form', $form);
        self::assertStringContainsString('data-mcc-date-picker', $list);
        self::assertStringContainsString('mcc-pagination', $list);
        self::assertStringContainsString('Tournament history', $detail);
    }

    public function testTournamentCalendarDateParsesDatepickerValues(): void
    {
        $service = new \App\Services\TournamentManagementService();
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('calendarDate');
        $method->setAccessible(true);

        $date = $method->invoke($service, '01-10-2026');

        self::assertInstanceOf(\DateTimeImmutable::class, $date);
        self::assertSame('2026-10-01', $date->format('Y-m-d'));

        $this->expectException(\RuntimeException::class);
        $method->invoke($service, '31-02-2026');
    }

    public function testTournamentPageNumberRejectsInvalidValues(): void
    {
        $service = new \App\Services\TournamentManagementService();
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('pageNumber');
        $method->setAccessible(true);

        self::assertSame(2, $method->invoke($service, '2'));

        $this->expectException(\RuntimeException::class);
        $method->invoke($service, '0');
    }

    public function testTournamentCrudViewsUseSharedActionAndCardPatterns(): void
    {
        $index = file_get_contents(APPPATH . 'Modules/Tournaments/Views/index.php');
        $form = file_get_contents(APPPATH . 'Modules/Tournaments/Views/form.php');
        $detail = file_get_contents(APPPATH . 'Modules/Tournaments/Views/show.php');

        self::assertIsString($index);
        self::assertIsString($form);
        self::assertIsString($detail);
        self::assertStringContainsString("'pageActions' => \$pageActions", $index);
        self::assertStringContainsString('mcc-tournament-filter-card', $index);
        self::assertStringContainsString('mcc-tournament-table-card', $index);
        self::assertStringContainsString('mcc-tournament-form-card', $form);
        self::assertStringContainsString('mcc-form-actions', $form);
        self::assertStringContainsString('Basic information', $form);
        self::assertStringContainsString('Branch and Teams', $form);
        self::assertStringContainsString('name="opponent_ids[]"', $form);
        self::assertStringContainsString("'pageActions' => \$pageActions", $detail);
        self::assertStringContainsString('mcc-tournament-hero', $detail);
        self::assertStringContainsString('mcc-card-heading', $detail);
        self::assertStringContainsString('Back to Tournaments', $detail);
        self::assertStringContainsString('mcc-tournament-detail-header', $detail);
        self::assertStringContainsString('mcc-tournament-history-table', $detail);
        self::assertStringContainsString('Previous values', $detail);
        self::assertStringContainsString('New values', $detail);
        self::assertStringNotContainsString('<details>', $detail);
        self::assertStringNotContainsString('json_encode(', $detail);
        self::assertStringNotContainsString('Asia/Kolkata', $detail);
    }

    public function testTournamentFormPreservesSubmittedValuesAfterValidationErrors(): void
    {
        $form = file_get_contents(APPPATH . 'Modules/Tournaments/Views/form.php');

        self::assertIsString($form);
        self::assertStringContainsString("old('branch_id'", $form);
        self::assertStringContainsString("old('name'", $form);
        self::assertStringContainsString("old('starts_on'", $form);
        self::assertStringContainsString("old('ends_on'", $form);
        self::assertStringContainsString("old('teams'", $form);
        self::assertStringContainsString("old('description'", $form);
        self::assertStringContainsString('Back to Tournaments', $form);
        self::assertStringContainsString('data-mcc-date-picker', $form);
        self::assertStringNotContainsString('id="tournament-ground"', $form);
        self::assertStringNotContainsString('id="tournament-players"', $form);
        self::assertStringNotContainsString('Asia/Kolkata', $form);

        $script = file_get_contents(ROOTPATH . 'public/assets/js/app.js');
        self::assertIsString($script);
        self::assertStringContainsString('mcc-bt-select-select-all', $script);
        self::assertStringContainsString("select.dispatchEvent(new Event('bt-select-refresh'))", $script);

        $css = file_get_contents(ROOTPATH . 'public/assets/css/app.css');
        self::assertIsString($css);
        self::assertStringContainsString('.mcc-form-field .mcc-bt-select-option input[type="checkbox"]', $css);
        self::assertStringContainsString('max-width: 16px;', $css);
        self::assertStringContainsString('width: 100%;', $css);
    }

    public function testTournamentValidationExposesFieldSpecificErrors(): void
    {
        $controller = file_get_contents(APPPATH . 'Modules/Tournaments/Controllers/Browser.php');
        $service = file_get_contents(APPPATH . 'Services/TournamentManagementService.php');

        self::assertIsString($controller);
        self::assertIsString($service);
        self::assertStringContainsString("with('field_errors'", $controller);
        self::assertStringContainsString('Invalid tournament start date.', $controller);
        self::assertStringContainsString('Invalid tournament end date.', $controller);
        self::assertStringContainsString('Invalid tournament ground.', $controller);
        self::assertStringContainsString('Invalid tournament name.', $service);
        self::assertStringContainsString('Invalid tournament type.', $service);
        self::assertStringContainsString('Invalid tournament status.', $service);
        self::assertStringContainsString('validateStatusTransition', $service);
        self::assertStringContainsString('New tournaments must start in the planned status.', $service);
        self::assertStringContainsString('This Tournament status transition is not allowed.', $service);
        self::assertStringContainsString('A reason is required when cancelling a Tournament.', $service);
    }

    public function testTournamentListKeepsFiltersWhenDateValidationFails(): void
    {
        $controller = file_get_contents(APPPATH . 'Modules/Tournaments/Controllers/Browser.php');

        self::assertIsString($controller);
        self::assertStringContainsString('$filters = $this->request->getGet();', $controller);
        self::assertStringContainsString("'filters' => \$filters", $controller);
        self::assertStringContainsString('Invalid tournament start date.', $controller);
        self::assertStringContainsString('private function tournamentError', $controller);
    }

    public function testTournamentOptionsAndParticipantsUseExistingActiveRecordRules(): void
    {
        $service = file_get_contents(APPPATH . 'Services/TournamentManagementService.php');

        self::assertIsString($service);
        self::assertStringContainsString("->where('is_active', 1)", $service);
        self::assertStringContainsString("'is_active' => 1", $service);
        self::assertStringContainsString("->where('p.status', 'active')", $service);
        self::assertStringContainsString("'status' => 'active'", $service);
        self::assertStringContainsString('availablePlayers', $service);
        self::assertStringContainsString('$branchId = null', $service);
    }

    public function testTournamentEditKeepsAuthorizedParticipantDataAvailableToTheService(): void
    {
        $controller = file_get_contents(APPPATH . 'Modules/Tournaments/Controllers/Browser.php');
        $form = file_get_contents(APPPATH . 'Modules/Tournaments/Views/form.php');

        self::assertIsString($controller);
        self::assertIsString($form);
        self::assertStringContainsString("'selectedPlayerIds' => \$selectedPlayerIds", $controller);
        self::assertStringContainsString("formOptions(\n                    'tournament.update',", $controller);
        self::assertStringContainsString('name="opponent_ids[]"', $form);
        self::assertStringNotContainsString('name="player_ids[]"', $form);
        self::assertStringNotContainsString('name="ground_id"', $form);
    }
}
