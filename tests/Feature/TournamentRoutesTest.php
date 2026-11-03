<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

final class TournamentRoutesTest extends TestCase
{
    public function testTournamentDetailUsesAuthorizedActionsAndReadableHistoryMarkup(): void
    {
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Tournaments/Config/Routes.php');
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Tournaments/Controllers/Browser.php');
        $view = file_get_contents(ROOTPATH . 'app/Modules/Tournaments/Views/show.php');

        self::assertIsString($routes);
        self::assertIsString($controller);
        self::assertIsString($view);
        self::assertStringContainsString("permission:tournament.view", $routes);
        self::assertStringContainsString("permission:tournament.update", $routes);
        self::assertStringContainsString("'can_update'", $controller);
        self::assertStringContainsString('mcc-tournament-detail-header', $view);
        self::assertStringContainsString('mcc-table-wrap', $view);
        self::assertStringContainsString('mcc-empty-state', $view);
        self::assertStringContainsString('No result', $view);
        self::assertStringContainsString('Tied', $view);
        self::assertStringContainsString('Position', $view);
        self::assertStringContainsString('Points', $view);
        self::assertStringContainsString('Select a completed Match result from this Tournament branch.', $controller);
        self::assertStringNotContainsString('View raw metadata', $view);
        self::assertStringNotContainsString('json_encode(', $view);
    }
}
