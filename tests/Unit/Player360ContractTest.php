<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class Player360ContractTest extends TestCase
{
    public function testPlayer360IsReadOnlyAndKeepsUndefinedSectionsUnavailable(): void
    {
        $service = file_get_contents(APPPATH . 'Services/Player360Service.php');
        $routes = file_get_contents(APPPATH . 'Modules/Player360/Config/Routes.php');

        self::assertStringContainsString('player360.view', $service);
        self::assertStringContainsString('player360.view', $routes);
        self::assertStringContainsString("'performance'", $service);
        self::assertStringContainsString("'high_performance'", $service);
        self::assertStringContainsString("'documents'", $service);
        self::assertStringContainsString("'Not available'", $service);
        self::assertStringNotContainsString('->insert(', $service);
        self::assertStringNotContainsString('->update(', $service);
        self::assertStringNotContainsString('->delete(', $service);
    }

    public function testPlayer360RequiresSessionReadPermissionAndHidesUnresolvedPerformanceFormulas(): void
    {
        $service = file_get_contents(APPPATH . 'Services/Player360Service.php');

        self::assertStringContainsString("can('session.view'", $service);
        self::assertStringNotContainsString("can('session.update'", $service);
        self::assertStringContainsString('Performance-derived formulas are unavailable', $service);
        self::assertStringContainsString("service('development')->forPlayer", $service);
        self::assertStringNotContainsString("table('development_plans d')", $service);
        self::assertStringNotContainsString("return service('performance')->player", $service);
    }
}
