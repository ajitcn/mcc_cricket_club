<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

final class MatchTournamentLinkageRoutesTest extends TestCase
{
    public function testFixtureRoutesExposeLinkageAndStatusActions(): void
    {
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Tournaments/Config/Routes.php');
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Tournaments/Controllers/Browser.php');
        $fixtureForm = file_get_contents(ROOTPATH . 'app/Modules/Tournaments/Views/fixture_form.php');
        $fixtureShow = file_get_contents(ROOTPATH . 'app/Modules/Tournaments/Views/fixture_show.php');

        self::assertIsString($routes);
        self::assertIsString($controller);
        self::assertIsString($fixtureForm);
        self::assertIsString($fixtureShow);
        self::assertStringContainsString('fixtures/(:num)/postpone', $routes);
        self::assertStringContainsString('fixtures/(:num)/cancel', $routes);
        self::assertStringContainsString('fixtures/(:num)/complete', $routes);
        self::assertStringContainsString('expected_version', $controller);
        self::assertStringContainsString('expected_version', $fixtureForm);
        self::assertStringContainsString('expected_version', $fixtureShow);
        self::assertStringContainsString('correction_reason', $fixtureForm);
        self::assertStringContainsString('csrf_field()', $fixtureForm);
        self::assertStringContainsString('csrf_field()', $fixtureShow);
    }

    public function testMatchCorrectionFormRetainsExistingAuthorizationBoundary(): void
    {
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Matches/Config/Routes.php');
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Matches/Controllers/Browser.php');
        $form = file_get_contents(ROOTPATH . 'app/Modules/Matches/Views/form.php');

        self::assertIsString($routes);
        self::assertIsString($controller);
        self::assertIsString($form);
        self::assertStringContainsString('permission:match.update', $routes);
        self::assertStringContainsString('correction_reason', $controller);
        self::assertStringContainsString('expected_version', $form);
        self::assertStringContainsString('csrf_field()', $form);
    }
}
