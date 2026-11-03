<?php

namespace Tests\Unit;

use App\Services\AuthorizationService;
use PHPUnit\Framework\TestCase;
final class AuthorizationServiceTest extends TestCase
{
    public function testUnauthenticatedContextIsDenied(): void
    {
        $service = new AuthorizationService(['isLoggedIn' => false, 'permissions' => ['player.view'], 'authorizedBranches' => ['branch-a']]);
        self::assertFalse($service->can('player.view', 'branch-a'));
    }
    public function testMissingPermissionIsDenied(): void
    {
        $service = new AuthorizationService(['isLoggedIn' => true, 'permissions' => [], 'authorizedBranches' => ['branch-a']]);
        self::assertFalse($service->can('player.view', 'branch-a'));
    }
    public function testWrongBranchIsDeniedServerSide(): void
    {
        $service = new AuthorizationService(['isLoggedIn' => true, 'permissions' => ['player.view'], 'authorizedBranches' => ['branch-a']]);
        self::assertFalse($service->can('player.view', 'branch-b'));
        self::assertTrue($service->can('player.view', 'branch-a'));
    }
    public function testDisabledAccountIsDenied(): void
    {
        $service = new AuthorizationService(['isLoggedIn' => true, 'isDisabled' => true, 'permissions' => ['player.view'],
    'authorizedBranches' => ['branch-a']]);
        self::assertFalse($service->can('player.view', 'branch-a'));
    }
    public function testEmptyPermissionDoesNotGrantAccess(): void
    {
        $service = new AuthorizationService(['isLoggedIn' => true, 'permissions' => ['player.view'], 'authorizedBranches' => ['branch-a']]);
        self::assertFalse($service->can('', 'branch-a'));
    }
}
