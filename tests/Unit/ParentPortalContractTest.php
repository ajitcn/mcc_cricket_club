<?php
declare(strict_types=1);
namespace Tests\Unit;
use PHPUnit\Framework\TestCase;
final class ParentPortalContractTest extends TestCase
{
    public function testPortalUsesParentPermissionAndPersistedLink(): void
    {
        $routes = file_get_contents(APPPATH . 'Modules/ParentPortal/Config/Routes.php');
        $service = file_get_contents(APPPATH . 'Services/ParentPortalService.php');
        self::assertStringContainsString('permission:parent_portal.view', $routes);
        self::assertStringContainsString('parent_player_links', $service);
        self::assertStringNotContainsString('parent_id=', $routes);
    }
}
