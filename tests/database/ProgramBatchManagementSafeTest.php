<?php

declare(strict_types=1);

namespace Tests\Database;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use RuntimeException;

/** Safe persistent coverage for the user-directed Batch edit workflow. */
final class ProgramBatchManagementSafeTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $DBGroup = 'tests';
    protected $migrate = false;

    private int $actor;
    private int $branch;
    private int $otherBranch;
    private int $program;

    protected function setUp(): void
    {
        parent::setUp();
        self::assertSame('mcc_cricket_club_test', strtolower((string) $this->db->getDatabase()));

        $this->actor = (int) $this->db->table('users')
            ->where('email', 'test.mcc.superadmin@example.invalid')
            ->get()
            ->getRow('id');
        $this->branch = (int) $this->db->table('branches')
            ->where('code', 'TEST_MCC_VNS')
            ->get()
            ->getRow('id');
        $this->otherBranch = (int) $this->db->table('branches')
            ->where('code', 'TEST_MCC_DEL')
            ->get()
            ->getRow('id');
        $this->program = (int) $this->db->table('programs')->orderBy('id', 'ASC')->get()->getRow('id');

        self::assertGreaterThan(0, min($this->actor, $this->branch, $this->otherBranch, $this->program));
        $this->authorize([$this->branch]);
    }

    protected function tearDown(): void
    {
        session()->destroy();
        \Config\Services::resetSingle('authorization');
        parent::tearDown();
    }

    public function testAuthorizedGlobalBatchEditPreservesLegacyOwnershipAndAuditsStatus(): void
    {
        $name = 'TEST_MCC_BATCH_EDIT_' . strtoupper(bin2hex(random_bytes(5)));
        $id = service('batch')->create([
            'name' => $name,
            'is_active' => 1,
        ]);

        service('batch')->update($id, [
            'name' => $name . '_UPDATED',
            'is_active' => 0,
        ]);

        $row = $this->db->table('batches')->where('id', $id)->get()->getRowArray();
        self::assertSame($name . '_UPDATED', $row['name']);
        self::assertNull($row['branch_id']);
        self::assertNull($row['program_id']);
        self::assertSame(0, (int) $row['is_active']);
        self::assertSame(1, $this->db->table('audit_logs')->where([
            'action' => 'batch_updated',
            'entity_id' => (string) $id,
            'branch_id' => null,
        ])->countAllResults());
    }

    public function testGlobalBatchCreationIgnoresForgedLegacyOwnershipValues(): void
    {
        $name = 'TEST_MCC_BATCH_SCOPE_' . strtoupper(bin2hex(random_bytes(5)));
        $id = service('batch')->create([
            'name' => $name,
            'program_id' => 999999,
            'branch_id' => $this->otherBranch,
        ]);

        $row = $this->db->table('batches')->where('id', $id)->get()->getRowArray();
        self::assertNull($row['branch_id']);
        self::assertNull($row['program_id']);
    }

    public function testProgramEditPreservesRelatedBatchesAndAuditsChange(): void
    {
        $oldName = 'TEST_MCC_PROGRAM_EDIT_' . strtoupper(bin2hex(random_bytes(5)));
        $newName = $oldName . '_UPDATED';
        $programId = service('program')->create(['name' => $oldName]);
        $batchId = service('batch')->create([
            'name' => 'TEST_MCC_PROGRAM_BATCH_' . strtoupper(bin2hex(random_bytes(5))),
            'program_id' => $programId,
            'branch_id' => $this->branch,
        ]);

        service('program')->update($programId, ['name' => $newName]);

        self::assertSame($newName, $this->db->table('programs')->where('id', $programId)->get()->getRow('name'));
        self::assertNull($this->db->table('batches')->where('id', $batchId)->get()->getRow('program_id'));
        self::assertSame(1, $this->db->table('audit_logs')->where([
            'action' => 'program_updated',
            'entity_id' => (string) $programId,
        ])->countAllResults());
    }

    public function testProgramDescriptionIsOptionalPersistedUpdatedAndLengthValidated(): void
    {
        $name = 'TEST_MCC_PROGRAM_DESCRIPTION_' . strtoupper(bin2hex(random_bytes(5)));
        $description = '<script>alert("program");</script>\nStructured coaching plan.';
        $programId = service('program')->create([
            'name' => $name,
            'description' => $description,
        ]);

        $created = $this->db->table('programs')->where('id', $programId)->get()->getRowArray();
        self::assertSame($description, $created['description']);

        service('program')->update($programId, [
            'name' => $name,
            'description' => 'Updated program description.',
        ]);

        $updated = $this->db->table('programs')->where('id', $programId)->get()->getRowArray();
        self::assertSame('Updated program description.', $updated['description']);

        $optionalName = $name . '_OPTIONAL';
        $optionalId = service('program')->create(['name' => $optionalName]);
        self::assertNull($this->db->table('programs')->where('id', $optionalId)->get()->getRow('description'));

        try {
            service('program')->update($programId, [
                'name' => $name,
                'description' => str_repeat('x', 5001),
            ]);
            self::fail('An overlong Program description was accepted.');
        } catch (RuntimeException $exception) {
            self::assertSame('Program description must not exceed 5,000 characters.', $exception->getMessage());
        }
    }

    public function testProgramEditRejectsEmptyDuplicateAndUnauthorizedUpdates(): void
    {
        $name = 'TEST_MCC_PROGRAM_VALIDATION_' . strtoupper(bin2hex(random_bytes(5)));
        $duplicate = 'TEST_MCC_PROGRAM_DUPLICATE_' . strtoupper(bin2hex(random_bytes(5)));
        $programId = service('program')->create(['name' => $name]);
        service('program')->create(['name' => $duplicate]);

        try {
            service('program')->update($programId, ['name' => '']);
            self::fail('Empty Program name was accepted.');
        } catch (RuntimeException $exception) {
            self::assertSame('Invalid program.', $exception->getMessage());
        }

        try {
            service('program')->update($programId, ['name' => $duplicate]);
            self::fail('Duplicate Program name was accepted.');
        } catch (RuntimeException $exception) {
            self::assertSame('Program already exists.', $exception->getMessage());
        }

        $this->authorize([$this->branch], []);
        try {
            service('program')->update($programId, ['name' => $name . '_FORBIDDEN']);
            self::fail('Unauthorized Program update was accepted.');
        } catch (RuntimeException $exception) {
            self::assertSame('Forbidden', $exception->getMessage());
        }
    }

    public function testCoachProgramPermissionsAllowOnlyTheGlobalProgramWorkflowAndAuditChanges(): void
    {
        $coach = (int) $this->db->table('users')
            ->where('email', 'test.mcc.coach@example.invalid')
            ->get()
            ->getRow('id');
        $coachRole = (int) $this->db->table('roles')
            ->where('name', 'Coach')
            ->get()
            ->getRow('id');

        self::assertGreaterThan(0, $coach);
        self::assertGreaterThan(0, $coachRole);
        self::assertSame(1, $this->db->table('role_permissions')->where([
            'role_id' => $coachRole,
            'permission' => 'program.view',
        ])->countAllResults());
        self::assertSame(1, $this->db->table('role_permissions')->where([
            'role_id' => $coachRole,
            'permission' => 'program.create',
        ])->countAllResults());

        $this->authorize(
            [$this->otherBranch],
            ['program.view', 'program.create'],
            $coach,
            ['Coach'],
        );

        $name = 'TEST_MCC_COACH_PROGRAM_' . strtoupper(bin2hex(random_bytes(5)));
        $programId = service('program')->create([
            'name' => $name,
            'description' => 'Created by an authorized Coach.',
        ]);
        service('program')->update($programId, [
            'name' => $name . '_UPDATED',
            'description' => 'Updated by an authorized Coach.',
        ]);

        self::assertNotEmpty(service('program')->list());
        self::assertSame(1, $this->db->table('audit_logs')->where([
            'action' => 'program_created',
            'entity_id' => (string) $programId,
            'actor_user_id' => $coach,
        ])->countAllResults());
        self::assertSame(1, $this->db->table('audit_logs')->where([
            'action' => 'program_updated',
            'entity_id' => (string) $programId,
            'actor_user_id' => $coach,
        ])->countAllResults());

        $this->authorize([$this->otherBranch], ['program.view'], $coach, ['Coach']);

        try {
            service('program')->update($programId, ['name' => $name . '_FORBIDDEN']);
            self::fail('Coach updated a Program without program.create.');
        } catch (RuntimeException $exception) {
            self::assertSame('Forbidden', $exception->getMessage());
        }
    }

    /** @param list<int> $branches */
    /** @param list<string> $permissions */
    private function authorize(
        array $branches,
        array $permissions = ['batch.view', 'batch.create', 'program.view', 'program.create'],
        ?int $actor = null,
        array $roles = ['Super Admin'],
    ): void
    {
        session()->set([
            'isLoggedIn' => true,
            'accountEnabled' => true,
            'user' => ['id' => $actor ?? $this->actor],
            'roles' => $roles,
            'permissions' => $permissions,
            'authorizedBranches' => array_map('strval', $branches),
        ]);
        \Config\Services::resetSingle('authorization');
    }
}
