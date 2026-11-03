<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

final class TournamentManagementRoutesTest extends TestCase
{
    public function testTournamentWriteRoutesAndFormsKeepAuthorizationAndCsrf(): void
    {
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Tournaments/Config/Routes.php');
        $form = file_get_contents(ROOTPATH . 'app/Modules/Tournaments/Views/form.php');

        self::assertIsString($routes);
        self::assertIsString($form);
        self::assertStringContainsString("permission:tournament.create", $routes);
        self::assertStringContainsString("permission:tournament.update", $routes);
        self::assertStringContainsString('csrf_field()', $form);
        self::assertStringContainsString('withInput()', file_get_contents(ROOTPATH . 'app/Modules/Tournaments/Controllers/Browser.php'));
    }

    public function testTournamentActiveRecordChecksRemainServerSide(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Services/TournamentManagementService.php');

        self::assertIsString($service);
        self::assertStringContainsString("->where('is_active', 1)", $service);
        self::assertStringContainsString("->where('p.status', 'active')", $service);
        self::assertStringContainsString("'status' => 'active'", $service);
        self::assertStringContainsString("'is_active' => 1", $service);
    }

    public function testFixtureRoutesExposeOnlyExplicitBranchScopedWorkflowActions(): void
    {
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Tournaments/Config/Routes.php');
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Tournaments/Controllers/Browser.php');
        $form = file_get_contents(ROOTPATH . 'app/Modules/Tournaments/Views/fixture_form.php');
        $detail = file_get_contents(ROOTPATH . 'app/Modules/Tournaments/Views/fixture_show.php');

        self::assertStringContainsString("fixtures/(:num)/postpone", $routes);
        self::assertStringContainsString("fixtures/(:num)/cancel", $routes);
        self::assertStringContainsString("fixtures/(:num)/complete", $routes);
        self::assertStringContainsString('permission:tournament.update', $routes);
        self::assertStringContainsString('fixtureOptions', $controller);
        self::assertStringContainsString('csrf_field()', $form);
        self::assertStringContainsString('csrf_field()', $detail);
        self::assertStringContainsString('data-mcc-date-picker', $form);
        self::assertStringContainsString('name="start_time"', $form);
    }

    public function testFixtureFormsUseExplicitErrorTargetsAndSharedResponsiveMarkup(): void
    {
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Tournaments/Controllers/Browser.php');
        $form = file_get_contents(ROOTPATH . 'app/Modules/Tournaments/Views/fixture_form.php');
        $detail = file_get_contents(ROOTPATH . 'app/Modules/Tournaments/Views/fixture_show.php');

        self::assertStringContainsString("'/tournaments/' . \$id . '/fixtures/new'", $controller);
        self::assertStringContainsString("'/tournaments/' . \$tournamentId . '/fixtures/' . \$fixtureId . '/edit'", $controller);
        self::assertStringContainsString('Back to Tournament', $form);
        self::assertStringContainsString('mcc-tournament-form-section', $form);
        self::assertStringContainsString('mcc-form-actions', $form);
        self::assertStringContainsString('data-mcc-date-picker', $form);
        self::assertStringContainsString('name="start_time"', $form);
        self::assertStringContainsString('Fixture history', $detail);
        self::assertStringContainsString('Back to Tournament', $detail);
        self::assertStringContainsString('Previous values', $detail);
        self::assertStringContainsString('New values', $detail);
        self::assertStringContainsString('Cancelled fixtures cannot be completed.', $controller);
        self::assertStringNotContainsString('json_encode(', $detail);
        self::assertStringNotContainsString('Asia/Kolkata', $detail);
    }

    public function testFixtureServiceValidatesTeamsReasonsAndStandingsRules(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Services/TournamentManagementService.php');
        $view = file_get_contents(ROOTPATH . 'app/Modules/Tournaments/Views/form.php');

        self::assertStringContainsString('Home and away teams must belong to this Tournament.', $service);
        self::assertStringContainsString('A reason is required when cancelling or postponing a fixture.', $service);
        self::assertStringContainsString('This Match is already linked to another fixture.', $service);
        self::assertStringContainsString("'scheduled', 'completed', 'cancelled', 'postponed'", $service);
        self::assertStringContainsString('Fixture date must be within the Tournament dates.', $service);
        self::assertStringContainsString('name="reason"', $view);
        self::assertStringContainsString("\$table[\$home]['no_result']++", $service);
        self::assertStringContainsString("\$table[\$home]['points'] += 2", $service);
        self::assertStringContainsString("[\$right['points'], \$right['won'], \$left['team_name']]", $service);
    }
}
