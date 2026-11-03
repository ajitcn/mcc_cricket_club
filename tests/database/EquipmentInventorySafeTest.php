<?php

declare(strict_types=1);

namespace Tests\Database;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use RuntimeException;

/** Persistent Equipment inventory security coverage; fixtures are not deleted. */
final class EquipmentInventorySafeTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $DBGroup = 'tests';
    protected $migrate = false;

    private int $superAdmin;
    private int $branchAdmin;
    private int $branch;
    private int $otherBranch;

    protected function setUp(): void
    {
        parent::setUp();
        self::assertSame(
            'mcc_cricket_club_test',
            strtolower((string) $this->db->getDatabase())
        );

        $this->superAdmin = $this->userId('test.mcc.superadmin@example.invalid');
        $this->branchAdmin = $this->userId('test.mcc.manager.vns@example.invalid');
        $this->branch = $this->branchId('TEST_MCC_VNS');
        $this->otherBranch = $this->branchId('TEST_MCC_DEL');
        self::assertGreaterThan(0, min(
            $this->superAdmin,
            $this->branchAdmin,
            $this->branch,
            $this->otherBranch
        ));
    }

    protected function tearDown(): void
    {
        session()->destroy();
        \Config\Services::resetSingle('authorization');
        parent::tearDown();
    }

    public function testSuperAdminCreatesForAnAuthorizedSelectedBranch(): void
    {
        $this->authorize($this->superAdmin, ['equipment.view', 'equipment.create']);
        $name = $this->uniqueName('super');

        $id = service('equipment', false)->create([
            'name' => $name,
            'quantity' => '4',
            'branch_id' => (string) $this->otherBranch,
        ]);

        $record = $this->db->table('equipment')->where('id', $id)->get()->getRowArray();
        self::assertSame($name, $record['name']);
        self::assertSame(4, (int) $record['quantity']);
        self::assertSame($this->otherBranch, (int) $record['branch_id']);
        self::assertSame(1, $this->db->table('audit_logs')->where([
            'action' => 'equipment_created',
            'entity_id' => (string) $id,
        ])->countAllResults());
    }

    public function testBranchAdminIgnoresForgedSubmittedBranchId(): void
    {
        $this->authorize($this->branchAdmin, ['equipment.view', 'equipment.create']);
        $name = $this->uniqueName('manager');

        $id = service('equipment', false)->create([
            'name' => $name,
            'quantity' => '2',
            'branch_id' => (string) $this->otherBranch,
        ]);

        $record = $this->db->table('equipment')->where('id', $id)->get()->getRowArray();
        self::assertSame($this->branch, (int) $record['branch_id']);
        self::assertNotSame($this->otherBranch, (int) $record['branch_id']);
    }

    public function testBranchAdminWithoutCreatePermissionIsDenied(): void
    {
        $this->authorize($this->branchAdmin, ['equipment.view']);

        try {
            service('equipment', false)->create([
                'name' => $this->uniqueName('denied'),
                'quantity' => '1',
                'branch_id' => (string) $this->branch,
            ]);
            self::fail('Equipment creation without permission was accepted.');
        } catch (RuntimeException $exception) {
            self::assertSame('Forbidden', $exception->getMessage());
        }
    }

    public function testBranchAdminUpdateCannotChangeTheEquipmentBranch(): void
    {
        $this->authorize($this->branchAdmin, [
            'equipment.view',
            'equipment.create',
            'equipment.update',
        ]);
        $name = $this->uniqueName('update');
        $id = service('equipment', false)->create([
            'name' => $name,
            'quantity' => '2',
            'branch_id' => (string) $this->otherBranch,
        ]);

        service('equipment', false)->update($id, [
            'name' => $name . '_UPDATED',
            'quantity' => '5',
            'branch_id' => (string) $this->otherBranch,
        ]);

        $record = $this->db->table('equipment')->where('id', $id)->get()->getRowArray();
        self::assertSame($this->branch, (int) $record['branch_id']);
        self::assertSame($name . '_UPDATED', $record['name']);
        self::assertSame(5, (int) $record['quantity']);
        self::assertSame(1, $this->db->table('audit_logs')->where([
            'action' => 'equipment_updated',
            'entity_id' => (string) $id,
        ])->countAllResults());
    }

    public function testBranchAdminCannotUpdateAnEquipmentRecordInAnotherBranch(): void
    {
        $this->authorize($this->superAdmin, [
            'equipment.view',
            'equipment.create',
        ]);
        $id = service('equipment', false)->create([
            'name' => $this->uniqueName('other_branch'),
            'quantity' => '1',
            'branch_id' => (string) $this->otherBranch,
        ]);
        $this->authorize($this->branchAdmin, ['equipment.update']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Not found.');
        service('equipment', false)->findForUpdate($id);
    }

    public function testBranchAdminCannotDeleteAnEquipmentRecordInAnotherBranch(): void
    {
        $this->authorize($this->superAdmin, [
            'equipment.view',
            'equipment.create',
        ]);
        $id = service('equipment', false)->create([
            'name' => $this->uniqueName('other_branch_delete'),
            'quantity' => '1',
            'branch_id' => (string) $this->otherBranch,
        ]);
        $this->authorize($this->branchAdmin, ['equipment.delete']);

        try {
            service('equipment', false)->softDelete($id);
            self::fail('Cross-branch Equipment deletion was accepted.');
        } catch (RuntimeException $exception) {
            self::assertSame('Not found.', $exception->getMessage());
        }

        $record = $this->db->table('equipment')->where('id', $id)->get()->getRowArray();
        self::assertNull($record['deleted_at']);
    }

    public function testDeleteSoftDeletesAndAuditsEquipment(): void
    {
        $this->authorize($this->branchAdmin, [
            'equipment.view',
            'equipment.create',
            'equipment.delete',
        ]);
        $name = $this->uniqueName('delete');
        $id = service('equipment', false)->create([
            'name' => $name,
            'quantity' => '3',
            'branch_id' => (string) $this->otherBranch,
        ]);

        service('equipment', false)->softDelete($id);

        $record = $this->db->table('equipment')->where('id', $id)->get()->getRowArray();
        self::assertNotNull($record['deleted_at']);
        self::assertSame(0, $this->db->table('equipment')->where([
            'id' => $id,
            'deleted_at' => null,
        ])->countAllResults());
        self::assertSame(1, $this->db->table('audit_logs')->where([
            'action' => 'equipment_deleted',
            'entity_id' => (string) $id,
        ])->countAllResults());
        self::assertSame([], array_filter(
            service('equipment', false)->list(),
            static fn (array $row): bool => (int) $row['id'] === $id
        ));
    }

    public function testUpdateRequiresItsDedicatedPermission(): void
    {
        $this->authorize($this->branchAdmin, [
            'equipment.view',
            'equipment.create',
        ]);
        $id = service('equipment', false)->create([
            'name' => $this->uniqueName('update_denied'),
            'quantity' => '1',
            'branch_id' => (string) $this->branch,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Forbidden');
        service('equipment', false)->update($id, [
            'name' => 'Unauthorized update',
            'quantity' => '2',
        ]);
    }

    public function testMultiBranchAdminMustChooseAnActiveBranchAndUsesThatContext(): void
    {
        $userId = $this->createMultiBranchAdmin();
        $this->authorize($userId, ['equipment.view', 'equipment.create']);
        $context = service('branchContext', false);

        $state = $context->navigationState();
        self::assertTrue($state['show_selector']);
        self::assertTrue($state['needs_selection']);
        self::assertNull($state['active_branch_id']);

        $context->selectActiveBranch((string) $this->otherBranch);
        self::assertSame($this->otherBranch, $context->activeBranchId());

        $id = service('equipment', false)->create([
            'name' => $this->uniqueName('multi'),
            'quantity' => '3',
            'branch_id' => (string) $this->branch,
        ]);
        $record = $this->db->table('equipment')->where('id', $id)->get()->getRowArray();

        self::assertSame($this->otherBranch, (int) $record['branch_id']);
    }

    private function authorize(int $userId, array $permissions): void
    {
        session()->set([
            'isLoggedIn' => true,
            'accountEnabled' => true,
            'user' => ['id' => $userId],
            'session_version' => 1,
            'permissions' => $permissions,
            'authorizedBranches' => [(string) $this->otherBranch],
        ]);
        \Config\Services::resetSingle('authorization');
    }

    private function userId(string $email): int
    {
        return (int) $this->db->table('users')->where('email', $email)->get()->getRow('id');
    }

    private function branchId(string $code): int
    {
        return (int) $this->db->table('branches')->where('code', $code)->get()->getRow('id');
    }

    private function uniqueName(string $type): string
    {
        return 'TEST_MCC_EQUIPMENT_' . $type . '_' . strtoupper(bin2hex(random_bytes(5)));
    }

    private function createMultiBranchAdmin(): int
    {
        $token = strtoupper(bin2hex(random_bytes(5)));
        $now = gmdate('Y-m-d H:i:s');
        $this->db->table('users')->insert([
            'email' => 'test.mcc.equipment.branch.' . strtolower($token) . '@example.invalid',
            'password_hash' => password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
            'display_name' => 'TEST_MCC_EQUIPMENT_BRANCH_' . $token,
            'is_enabled' => 1,
            'failed_login_count' => 0,
            'session_version' => 1,
            'locked_until' => null,
            'last_login_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $userId = (int) $this->db->insertID();
        $roleId = (int) $this->db->table('roles')
            ->where('name', 'Branch Admin')
            ->get()
            ->getRow('id');

        $this->db->table('user_roles')->insert([
            'user_id' => $userId,
            'role_id' => $roleId,
            'created_at' => $now,
        ]);

        foreach ([$this->branch, $this->otherBranch] as $branchId) {
            $this->db->table('user_branches')->insert([
                'user_id' => $userId,
                'branch_id' => $branchId,
                'created_at' => $now,
            ]);
        }

        return $userId;
    }
}
