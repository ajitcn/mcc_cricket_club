<?php

declare(strict_types=1);

namespace Tests\Database;

use App\Database\Seeds\TestDatasetSeeder;
use App\Services\AuthService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use ReflectionClass;

final class SuperAdminRestoreVerificationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $DBGroup = 'tests';
    protected $migrate = false;

    public function testRestoredSuperAdminIsActiveAndCanLogIn(): void
    {
        self::assertSame('mcc_cricket_club_test', $this->db->getDatabase());

        $user = $this->db->table('users')
            ->where('email', 'test.mcc.superadmin@example.invalid')
            ->get()
            ->getRowArray();
        self::assertIsArray($user);
        self::assertSame(1, (int) $user['is_enabled']);
        self::assertSame(1, $this->db->table('users')
            ->where('email', 'test.mcc.superadmin@example.invalid')
            ->countAllResults());

        $password = $this->fixturePassword();
        self::assertNotSame($password, (string) $user['password_hash']);
        self::assertTrue(password_verify($password, (string) $user['password_hash']));
        self::assertTrue((new AuthService())->attempt('test.mcc.superadmin@example.invalid', $password));
    }

    public function testRestoredSuperAdminHasEveryExistingPermissionAndActiveBranch(): void
    {
        $userId = (int) $this->db->table('users')
            ->where('email', 'test.mcc.superadmin@example.invalid')
            ->get()
            ->getRow('id');
        $roleId = (int) $this->db->table('roles')
            ->where('name', 'Super Admin')
            ->get()
            ->getRow('id');

        self::assertGreaterThan(0, $userId);
        self::assertGreaterThan(0, $roleId);
        self::assertSame(1, $this->db->table('user_roles')->where([
            'user_id' => $userId,
            'role_id' => $roleId,
        ])->countAllResults());

        $defined = $this->db->table('role_permissions')
            ->select('permission')
            ->distinct()
            ->countAllResults();
        $mapped = $this->db->table('role_permissions')
            ->select('permission')
            ->where('role_id', $roleId)
            ->distinct()
            ->countAllResults();
        self::assertSame($defined, $mapped);

        $activeBranchCodes = $this->db->table('branches')
            ->where('is_active', 1)
            ->orderBy('code', 'ASC')
            ->get()
            ->getResultArray();
        $branchCodes = $this->db->table('branches b')
            ->select('b.code')
            ->join('user_branches ub', 'ub.branch_id = b.id')
            ->where('ub.user_id', $userId)
            ->where('b.is_active', 1)
            ->orderBy('b.code', 'ASC')
            ->get()
            ->getResultArray();
        self::assertSame(array_column($activeBranchCodes, 'code'), array_column($branchCodes, 'code'));
    }

    private function fixturePassword(): string
    {
        $reflection = new ReflectionClass(TestDatasetSeeder::class);
        $constant = $reflection->getReflectionConstant('PASSWORD');

        self::assertNotFalse($constant);

        return (string) $constant->getValue();
    }
}
