<?php

declare (strict_types=1);
namespace Tests\Database;

use App\Modules\Admin\Models\AdminUserModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use RuntimeException;
final class AdminRbacIntegrationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    protected $DBGroup = 'tests';
    protected $migrate = false;
    private string $runPrefix;
    private int $admin;
    private int $role;
    private int $branch;
    private array $ownedUsers = [];
    private array $ownedRoles = [];
    private array $ownedBranches = [];
    protected function setUp(): void
    {
        parent::setUp();
        $this->runPrefix = 'TEST_MCC_RBAC_' . strtoupper(bin2hex(random_bytes(6)));
        $this->assertTargetDatabase();
        $now = date('Y-m-d H:i:s');
        $this->branch = $this->insertOwnedBranch($this->runPrefix . '_BRANCH', $this->runPrefix . '_B');
        $this->admin = $this->createUser($this->email('ADMIN'), 'Test Admin');
        $this->assertTargetDatabase();
        $this->db->table('user_branches')->insert(['user_id' => $this->admin, 'branch_id' => $this->branch, 'created_at' => $now]);
        $this->assertTargetDatabase();
        $this->db->table('roles')->insert(['name' => $this->runPrefix . '_ROLE', 'created_at' => $now, 'updated_at' => $now]);
        $this->role = (int) $this->db->insertID();
        $this->ownedRoles[] = $this->role;
        $this->assertTargetDatabase();
        $this->db->table('role_permissions')->insert(['role_id' => $this->role, 'permission' => 'rbac.manage', 'created_at' => $now]);
        $this->assertTargetDatabase();
        $this->db->table('user_roles')->insert(['user_id' => $this->admin, 'role_id' => $this->role, 'created_at' => $now]);
        $this->setSession($this->admin, [$this->branch], ['rbac.manage']);
        \Config\Services::resetSingle('authorization');
    }
    protected function tearDown(): void
    {
        try {
            $this->cleanupOwnedRecords();
        } finally {
            session()->destroy();
            parent::tearDown();
        }
    }
    public function testAdminPageRequiresPermissionAndAuthorizedPageRenders(): void
    {
        $response = $this->withSession(session()->get())->get('admin');
        $response->assertStatus(200);
        $response->assertSee('Administration');
        $response->assertSee('Users');
        $response->assertSee('Roles');
        $response->assertSee('Permissions');
        self::assertSame(200, $this->withSession(session()->get())->get('admin/users/' . $this->admin)->response()->getStatusCode());
        session()->set('permissions', []);
        \Config\Services::resetSingle('authorization');
        self::assertSame(403, $this->withSession(session()->get())->get('admin')->response()->getStatusCode());
    }

    public function testRoleDetailDirectUrlRequiresRbacPermission(): void
    {
        $allowed = $this->withSession(session()->get())->get('admin/roles/' . $this->role);
        self::assertSame(200, $allowed->response()->getStatusCode());
        $allowed->assertSee('Assign approved permission');
        $allowed->assertSee('multiple');

        session()->set('permissions', []);
        \Config\Services::resetSingle('authorization');
        $denied = $this->withSession(session()->get())->get('admin/roles/' . $this->role);
        self::assertSame(403, $denied->response()->getStatusCode());
    }
    public function testUserCreationHashesPasswordAndAuditsWithoutSecret(): void
    {
        $id = $this->createServiceUser('NEW_STAFF');
        $row = $this->db->table('users')->where('id', $id)->get()->getRowArray();
        self::assertIsArray($row);
        self::assertNotSame($this->password(), $row['password_hash']);
        self::assertTrue(password_verify($this->password(), $row['password_hash']));
        $audit = json_encode($this->ownedAuditRows(), JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString($this->password(), $audit);
        self::assertStringNotContainsString('password_hash', $audit);
    }
    public function testUserUpdateWithoutNewPasswordKeepsExistingPassword(): void
    {
        $id = $this->createServiceUser('UNCHANGED_PASSWORD');
        $before = $this->db->table('users')->where('id', $id)->get()->getRowArray();
        self::assertIsArray($before);
        service('admin')->updateUser($id, [
            'email' => $this->email('UNCHANGED_PASSWORD_UPDATED'),
            'display_name' => $this->runPrefix . '_UPDATED',
            'new_password' => '',
        ]);
        $after = $this->db->table('users')->where('id', $id)->get()->getRowArray();
        self::assertIsArray($after);
        self::assertSame($before['password_hash'], $after['password_hash']);
        self::assertSame(1, (int) $after['session_version']);
    }
    public function testUserUpdateWithNewPasswordHashesAndInvalidatesSessions(): void
    {
        $id = $this->createServiceUser('UPDATED_PASSWORD');
        $newPassword = 'New secure password 2026!';
        service('admin')->updateUser($id, [
            'email' => $this->email('UPDATED_PASSWORD'),
            'display_name' => $this->runPrefix . '_UPDATED_PASSWORD',
            'new_password' => $newPassword,
        ]);
        $after = $this->db->table('users')->where('id', $id)->get()->getRowArray();
        self::assertIsArray($after);
        self::assertNotSame($newPassword, $after['password_hash']);
        self::assertTrue(password_verify($newPassword, $after['password_hash']));
        self::assertSame(2, (int) $after['session_version']);
        self::assertSame(0, (int) $after['failed_login_count']);
        self::assertNull($after['locked_until']);
        $audit = json_encode($this->ownedAuditRows(), JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString($newPassword, $audit);
        self::assertStringNotContainsString('password_hash', $audit);
    }
    public function testRoleAndPermissionAssignmentResolvesEffectiveAccess(): void
    {
        $id = $this->createServiceUser('STAFF');
        $this->assertTargetDatabase();
        service('admin')->assignRole($id, $this->role, true);
        $this->assertTargetDatabase();
        service('admin')->assignPermission($this->role, 'lead.create', true);
        $user = service('admin')->user($id);
        self::assertSame($this->email('STAFF'), $user['email']);
        self::assertContains(['permission' => 'lead.create'], $user['permissions']);
        self::assertSame(1, $this->ownedAuditCount('role_assigned', (string) $id));
        self::assertSame(1, $this->ownedAuditCount('permission_assigned', (string) $this->role));
    }

    public function testBulkPermissionAssignmentAddsRemovesAndRetriesIdempotently(): void
    {
        $version = (string) service('admin')->role($this->role)['permission_version'];
        service('admin')->syncPermissions(
            $this->role,
            ['rbac.manage', 'lead.view', 'lead.create'],
            $version,
        );

        $assigned = array_column(
            $this->db->table('role_permissions')->where('role_id', $this->role)->get()->getResultArray(),
            'permission',
        );
        self::assertContains('lead.view', $assigned);
        self::assertContains('lead.create', $assigned);

        $updatedVersion = (string) service('admin')->role($this->role)['permission_version'];
        service('admin')->syncPermissions(
            $this->role,
            ['rbac.manage', 'lead.update'],
            $updatedVersion,
        );

        $afterMixedUpdate = array_column(
            $this->db->table('role_permissions')->where('role_id', $this->role)->get()->getResultArray(),
            'permission',
        );
        self::assertNotContains('lead.view', $afterMixedUpdate);
        self::assertNotContains('lead.create', $afterMixedUpdate);
        self::assertContains('lead.update', $afterMixedUpdate);

        $retryVersion = (string) service('admin')->role($this->role)['permission_version'];
        $auditCount = $this->ownedAuditCount('role_permissions_updated', (string) $this->role);
        service('admin')->syncPermissions($this->role, ['rbac.manage', 'lead.update'], $retryVersion);
        self::assertSame($auditCount, $this->ownedAuditCount('role_permissions_updated', (string) $this->role));
    }

    public function testBulkPermissionAssignmentRejectsMalformedUnknownDuplicateAndStaleSelections(): void
    {
        $version = (string) service('admin')->role($this->role)['permission_version'];

        foreach ([
            [['lead.view', 'lead.view'], 'Duplicate permission selection.'],
            [['not-approved.permission'], 'Invalid permission.'],
            [['lead.view', 12], 'Invalid permission selection.'],
        ] as [$permissions, $message]) {
            try {
                service('admin')->syncPermissions($this->role, $permissions, $version);
                self::fail('Invalid permission selection was accepted.');
            } catch (RuntimeException $exception) {
                self::assertSame($message, $exception->getMessage());
            }
        }

        try {
            service('admin')->syncPermissions($this->role, ['lead.view'], str_repeat('0', 64));
            self::fail('A stale permission version was accepted.');
        } catch (RuntimeException $exception) {
            self::assertSame('Role permissions changed. Reload and try again.', $exception->getMessage());
        }
    }

    public function testBulkPermissionAssignmentProtectsSelfRbacPermissionAndRollsBackOnDenial(): void
    {
        $before = array_column(
            $this->db->table('role_permissions')->where('role_id', $this->role)->get()->getResultArray(),
            'permission',
        );
        $version = (string) service('admin')->role($this->role)['permission_version'];

        try {
            service('admin')->syncPermissions($this->role, [], $version);
            self::fail('Self rbac.manage removal was accepted.');
        } catch (RuntimeException $exception) {
            self::assertSame('Self privilege changes are not allowed.', $exception->getMessage());
        }

        $after = array_column(
            $this->db->table('role_permissions')->where('role_id', $this->role)->get()->getResultArray(),
            'permission',
        );
        sort($before);
        sort($after);
        self::assertSame($before, $after);
    }

    public function testBulkPermissionAssignmentRouteRejectsMissingCsrf(): void
    {
        $version = (string) service('admin')->role($this->role)['permission_version'];

        $this->expectException(\CodeIgniter\Security\Exceptions\SecurityException::class);
        $this->withSession(session()->get())
            ->withBodyFormat('json')
            ->post('admin/roles/' . $this->role . '/permissions', [
                'permission_sync' => '1',
                'permissions' => ['rbac.manage'],
                'expected_permission_version' => $version,
            ]);
    }
    public function testUserListIncludesAssignedRoleAndBranch(): void
    {
        $id = $this->createServiceUser('LIST_ASSIGNMENTS');
        service('admin')->assignRole($id, $this->role, true);
        service('admin')->assignBranch($id, $this->branch, true);
        $response = $this->withSession(session()->get())->get('admin/users');
        $response->assertStatus(200);
        $response->assertSee($this->runPrefix . '_ROLE');
        $response->assertSee($this->runPrefix . '_BRANCH');
    }
    public function testSelfPrivilegeEscalationAndUnauthorizedBranchAreRejected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Self privilege changes are not allowed.');
        service('admin')->assignRole($this->admin, $this->role, true);
    }
    public function testBranchAssignmentUsesAuthorizedBranchScope(): void
    {
        $id = $this->createServiceUser('STAFF');
        $other = $this->insertOwnedBranch($this->runPrefix . '_OTHER', $this->runPrefix . '_O');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Branch is not authorized.');
        service('admin')->assignBranch($id, $other, true);
    }
    public function testDisablementInvalidatesSessionsAndAudits(): void
    {
        $id = $this->createServiceUser('STAFF');
        $this->assertTargetDatabase();
        service('admin')->setEnabled($id, false);
        $row = $this->db->table('users')->where('id', $id)->get()->getRowArray();
        self::assertSame(0, (int) $row['is_enabled']);
        self::assertSame(2, (int) $row['session_version']);
        self::assertSame(1, $this->ownedAuditCount('user_disabled', (string) $id));
    }
    public function testBrowserUserCreationUsesCsrfProtectedRoute(): void
    {
        $this->expectException(\CodeIgniter\Security\Exceptions\SecurityException::class);
        $this->assertTargetDatabase();
        $this->withSession(session()->get())->withBodyFormat('json')->post('admin/users', ['email' => $this->email('BAD'), 'display_name' => 'Bad',
    'password' => 'short']);
        self::assertSame(0, $this->db->table('users')->where('email', $this->email('BAD'))->countAllResults());
    }
    public function testLeadCreationPermissionIsManagedByAdminAndEnforcedByTheRoute(): void
    {
        $denied = $this->withSession(session()->get())->get('crm/leads/new');
        self::assertSame(403, $denied->response()->getStatusCode());
        $this->assertTargetDatabase();
        service('admin')->assignPermission($this->role, 'lead.create', true);
        session()->set('permissions', ['rbac.manage', 'lead.create']);
        \Config\Services::resetSingle('authorization');
        $allowed = $this->withSession(session()->get())->get('crm/leads/new');
        self::assertSame(200, $allowed->response()->getStatusCode());
        $allowed->assertSee('Create lead');
    }
    private function createServiceUser(string $suffix): int
    {
        $this->assertTargetDatabase();
        $id = service('admin')->createUser(['email' => $this->email($suffix), 'display_name' => $this->runPrefix . '_' . $suffix,
    'password' => $this->password()]);
        $this->ownedUsers[] = $id;
        return $id;
    }
    private function createUser(string $suffix, string $displayName): int
    {
        $this->assertTargetDatabase();
        $model = new AdminUserModel();
        $model->insert(['email' => $this->email($suffix), 'password_hash' => password_hash($this->password(), PASSWORD_DEFAULT),
    'display_name' => $this->runPrefix . '_' . $displayName, 'is_enabled' => 1, 'failed_login_count' => 0, 'session_version' => 1, 'locked_until' => null,
    'last_login_at' => null, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
        $id = (int) $model->getInsertID();
        $this->ownedUsers[] = $id;
        return $id;
    }
    private function insertOwnedBranch(string $name, string $code): int
    {
        $this->assertTargetDatabase();
        $now = date('Y-m-d H:i:s');
        $this->db->table('branches')->insert(['name' => $name, 'code' => $code, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);
        $id = (int) $this->db->insertID();
        $this->ownedBranches[] = $id;
        return $id;
    }
    private function cleanupOwnedRecords(): void
    {
        $userIds = array_values(array_unique(array_map('intval', $this->ownedUsers)));
        $roleIds = array_values(array_unique(array_map('intval', $this->ownedRoles)));
        $branchIds = array_values(array_unique(array_map('intval', $this->ownedBranches)));
        if ($userIds !== []) {
            $this->assertTargetDatabase();
            $this->db->table('user_branches')->whereIn('user_id', $userIds)->delete();
            $this->assertTargetDatabase();
            $this->db->table('user_roles')->whereIn('user_id', $userIds)->delete();
            $this->assertTargetDatabase();
            $this->db->table('users')->whereIn('id', $userIds)->delete();
        }
        if ($roleIds !== []) {
            $this->assertTargetDatabase();
            $this->db->table('role_permissions')->whereIn('role_id', $roleIds)->delete();
            $this->assertTargetDatabase();
            $this->db->table('roles')->whereIn('id', $roleIds)->delete();
        }
        if ($branchIds !== []) {
            $this->assertTargetDatabase();
            $this->db->table('branches')->whereIn('id', $branchIds)->delete();
        }
    }
    private function ownedAuditRows(): array
    {
        $ids = array_map('strval', array_merge($this->ownedUsers, $this->ownedRoles, $this->ownedBranches));
        return $ids === [] ? [] : $this->db->table('audit_logs')->whereIn('entity_id', $ids)->get()->getResultArray();
    }
    private function ownedAuditCount(string $action, string $entityId): int
    {
        return (int) $this->db->table('audit_logs')->where(['action' => $action, 'entity_id' => $entityId])->countAllResults();
    }
    private function assertTargetDatabase(): void
    {
        $database = $this->db->query('SELECT DATABASE() AS database_name')->getRowArray()['database_name'] ?? null;
        self::assertSame('mcc_cricket_club_test', $database, 'Admin RBAC test must use mcc_cricket_club_test.');
    }
    private function email(string $suffix): string
    {
        return strtolower($this->runPrefix . '_' . $suffix) . '@example.invalid';
    }
    private function password(): string
    {
        return 'TEST_MCC_RBAC_password_2026!';
    }
    private function setSession(int $user, array $branches, array $permissions): void
    {
        session()->set(['isLoggedIn' => true, 'user' => ['id' => $user, 'email' => $this->email('ADMIN'), 'display_name' => $this->runPrefix . '_ADMIN'],
    'session_version' => 1, 'roles' => [$this->runPrefix . '_ROLE'], 'permissions' => $permissions, 'authorizedBranches' => array_map('strval', $branches),
    'accountEnabled' => true]);
    }
}
