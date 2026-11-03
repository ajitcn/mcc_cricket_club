<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class MatchTournamentLinkageContractTest extends TestCase
{
    public function testLinkageMigrationIsForwardOnlyAndIdempotent(): void
    {
        $migration = file_get_contents(
            ROOTPATH . 'app/Database/Migrations/20261011000300_AddMatchTournamentLinkageVersion.php'
        );

        self::assertIsString($migration);
        self::assertStringContainsString("fieldExists('version', 'matches')", $migration);
        self::assertStringContainsString("fieldExists('version', 'tournament_fixtures')", $migration);
        self::assertStringContainsString("addUniqueKey('match_id', 'uq_tournament_fixtures_match_id')", $migration);
        self::assertStringContainsString('Forward-only', $migration);
        self::assertStringContainsString('indexExists', $migration);
    }

    public function testServicesEnforceOwnershipStatusAndVersionRules(): void
    {
        $match = file_get_contents(ROOTPATH . 'app/Services/MatchManagementService.php');
        $tournament = file_get_contents(ROOTPATH . 'app/Services/TournamentManagementService.php');

        self::assertIsString($match);
        self::assertIsString($tournament);
        self::assertStringContainsString('validateCompletedCorrection', $match);
        self::assertStringContainsString('version + 1', $match);
        self::assertStringContainsString('Completed Matches cannot be reopened', $match);
        self::assertStringContainsString('Only completed Fixtures may link a Match result.', $tournament);
        self::assertStringContainsString('assertFixtureVersion', $tournament);
        self::assertStringContainsString('Fixture correction is stale', $tournament);
        self::assertStringContainsString("m.status = \\'completed\\'", $tournament);
    }

    public function testExistingPermissionsAndCsrfRoutesRemainTheBoundary(): void
    {
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Tournaments/Config/Routes.php');
        $matchRoutes = file_get_contents(ROOTPATH . 'app/Modules/Matches/Config/Routes.php');
        $fixtureForm = file_get_contents(ROOTPATH . 'app/Modules/Tournaments/Views/fixture_form.php');

        self::assertIsString($routes);
        self::assertIsString($matchRoutes);
        self::assertIsString($fixtureForm);
        self::assertStringContainsString('permission:tournament.update', $routes);
        self::assertStringContainsString('permission:match.update', $matchRoutes);
        self::assertStringContainsString('csrf_field()', $fixtureForm);
    }
}
