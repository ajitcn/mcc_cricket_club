<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AdmissionPlayerConnectivityContractTest extends TestCase
{
    public function testServicesProjectCanonicalLineageAndGuardConversionPlacement(): void
    {
        $admission = file_get_contents(APPPATH . 'Services/AdmissionService.php');
        $player = file_get_contents(APPPATH . 'Services/PlayerService.php');

        self::assertStringContainsString('converted_player_name', $admission);
        self::assertStringContainsString('conversion_date', $admission);
        self::assertStringContainsString('assertConversionPlacementConsistency', $admission);
        self::assertStringContainsString('admission_number', $player);
        self::assertStringContainsString('admission_current_period', $player);
        self::assertStringContainsString('admission_conversion_date', $player);
        self::assertStringContainsString('Invalid assignment.', $player);
    }

    public function testViewsLinkBothDirectionsWithoutUsingAdmissionPrimaryIdAsReference(): void
    {
        $admission = file_get_contents(APPPATH . 'Modules/Admissions/Views/show.php');
        $player = file_get_contents(APPPATH . 'Modules/Players/Views/show.php');

        self::assertStringContainsString('View conversion history/status', $admission);
        self::assertStringContainsString('converted_player_name', $admission);
        self::assertStringContainsString("site_url('admissions/' . (int) \$player['admission_id'] . '/page')", $player);
        self::assertStringContainsString("\$player['admission_number']", $player);
        self::assertStringNotContainsString('<dd>#<?= (int) $player[\'admission_id\'] ?></dd>', $player);
    }

    public function testConversionRouteRemainsCanonicalAndPermissionGated(): void
    {
        $routes = file_get_contents(APPPATH . 'Modules/Admissions/Config/Routes.php');

        self::assertStringContainsString("Browser::convert/\$1", $routes);
        self::assertStringContainsString('permission:player.convert', $routes);
        self::assertStringContainsString("Browser::showPage/\$1", file_get_contents(APPPATH . 'Modules/Players/Config/Routes.php'));
    }
}
