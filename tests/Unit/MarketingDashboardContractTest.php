<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class MarketingDashboardContractTest extends TestCase
{
    public function testMarketingDashboardIsReadOnlyAndPermissionControlled(): void
    {
        $service = file_get_contents(APPPATH . 'Services/MarketingDashboardService.php');
        $routes = file_get_contents(APPPATH . 'Modules/Marketing/Config/Routes.php');

        self::assertStringContainsString("marketing.view", $service);
        self::assertStringContainsString("marketing.view", $routes);
        self::assertStringContainsString("'roi' => 'Not available'", $service);
        self::assertStringContainsString("'campaign_attribution' => 'Not available'", $service);
        self::assertStringNotContainsString('INSERT', strtoupper($service));
        self::assertStringNotContainsString('UPDATE', strtoupper($service));
        self::assertStringNotContainsString('DELETE', strtoupper($service));
    }
}
