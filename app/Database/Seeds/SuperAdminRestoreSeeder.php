<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use Config\Database;
use ReflectionClass;
use RuntimeException;
use Throwable;

/**
 * Restores the dedicated development/test Super Admin fixture without
 * changing production data or removing any existing rows.
 */
final class SuperAdminRestoreSeeder extends Seeder
{
    private const EMAIL = 'test.mcc.superadmin@example.invalid';
    private const DISPLAY_NAME = 'TEST_MCC_SUPER_ADMIN';
    private const ROLE = 'Super Admin';

    public function run(): void
    {
        if (! in_array(ENVIRONMENT, ['development', 'testing'], true)) {
            throw new RuntimeException('Refusing Super Admin restore outside development/testing.');
        }

        $db = Database::connect('tests');
        $databaseName = strtolower((string) $db->getDatabase());
        if ($databaseName !== 'mcc_cricket_club_test') {
            throw new RuntimeException('Refusing Super Admin restore outside mcc_cricket_club_test.');
        }

        $password = $this->testFixturePassword();
        $now = date('Y-m-d H:i:s');
        $db->transBegin();

        try {
            $roleId = $this->restoreRole($db, $now);
            $userId = $this->restoreUser($db, $password, $now);
            $this->restoreRoleAssignment($db, $userId, $roleId, $now);
            $branchCount = $this->restoreBranchAssignments($db, $userId, $now);
            $permissionCount = $this->restorePermissions($db, $roleId, $now);

            if ($db->transStatus() === false) {
                throw new RuntimeException('Super Admin restore transaction failed.');
            }

            $db->transCommit();
        } catch (Throwable $exception) {
            $db->transRollback();
            throw $exception;
        }

        echo json_encode([
            'database' => 'mcc_cricket_club_test',
            'user_id' => $userId,
            'role' => self::ROLE,
            'branch_access_count' => $branchCount,
            'permission_count' => $permissionCount,
            'password' => 'hashed',
        ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT) . PHP_EOL;
    }

    private function restoreRole(object $db, string $now): int
    {
        $role = $db->table('roles')->where('name', self::ROLE)->get()->getRowArray();
        if (is_array($role)) {
            return (int) $role['id'];
        }

        $db->table('roles')->insert([
            'name' => self::ROLE,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) $db->insertID();
    }

    private function restoreUser(object $db, string $password, string $now): int
    {
        $table = $db->table('users');
        $user = $table->where('email', self::EMAIL)->get()->getRowArray();
        $values = [
            'email' => self::EMAIL,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'display_name' => self::DISPLAY_NAME,
            'is_enabled' => 1,
            'failed_login_count' => 0,
            'locked_until' => null,
            'updated_at' => $now,
        ];

        if (is_array($user)) {
            $table->where('id', (int) $user['id'])->update($values);

            return (int) $user['id'];
        }

        $table->insert($values + [
            'session_version' => 1,
            'last_login_at' => null,
            'created_at' => $now,
        ]);

        return (int) $db->insertID();
    }

    private function restoreRoleAssignment(object $db, int $userId, int $roleId, string $now): void
    {
        $exists = $db->table('user_roles')
            ->where(['user_id' => $userId, 'role_id' => $roleId])
            ->countAllResults();

        if ($exists === 0) {
            $db->table('user_roles')->insert([
                'user_id' => $userId,
                'role_id' => $roleId,
                'created_at' => $now,
            ]);
        }
    }

    private function restoreBranchAssignments(object $db, int $userId, string $now): int
    {
        $branches = $db->table('branches')
            ->where('is_active', 1)
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();

        if ($branches === []) {
            throw new RuntimeException('No active branches are available for Super Admin access.');
        }

        foreach ($branches as $branch) {
            $exists = $db->table('user_branches')
                ->where(['user_id' => $userId, 'branch_id' => (int) $branch['id']])
                ->countAllResults();

            if ($exists === 0) {
                $db->table('user_branches')->insert([
                    'user_id' => $userId,
                    'branch_id' => (int) $branch['id'],
                    'created_at' => $now,
                ]);
            }
        }

        return count($branches);
    }

    private function restorePermissions(object $db, int $roleId, string $now): int
    {
        $permissions = $db->table('role_permissions')
            ->select('permission')
            ->distinct()
            ->orderBy('permission', 'ASC')
            ->get()
            ->getResultArray();

        foreach ($permissions as $row) {
            $permission = (string) $row['permission'];
            $exists = $db->table('role_permissions')
                ->where(['role_id' => $roleId, 'permission' => $permission])
                ->countAllResults();

            if ($exists === 0) {
                $db->table('role_permissions')->insert([
                    'role_id' => $roleId,
                    'permission' => $permission,
                    'created_at' => $now,
                ]);
            }
        }

        return count($permissions);
    }

    private function testFixturePassword(): string
    {
        $reflection = new ReflectionClass(TestDatasetSeeder::class);
        $constant = $reflection->getReflectionConstant('PASSWORD');
        if ($constant === false) {
            throw new RuntimeException('The existing test fixture password source is unavailable.');
        }

        return (string) $constant->getValue();
    }
}
