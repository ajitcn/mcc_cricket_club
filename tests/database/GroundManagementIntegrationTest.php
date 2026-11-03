<?php

declare(strict_types=1);

namespace Tests\Database;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use RuntimeException;

final class GroundManagementIntegrationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $DBGroup = 'tests';
    protected $migrate = false;

    private int $branch;
    private int $otherBranch;
    private int $actor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->branch = $this->id('branches', ['code' => 'TEST_MCC_VNS']);
        $this->otherBranch = $this->id('branches', ['code' => 'TEST_MCC_DEL']);
        $this->actor = $this->id('users', ['email' => 'test.mcc.superadmin@example.invalid']);
        $this->db->table('sessions')->where('ground_id IS NOT NULL', null, false)->update(['ground_id' => null]);
        $this->db->table('ground_unavailability')->emptyTable();
        $this->db->table('grounds')->emptyTable();
        session()->set([
            'isLoggedIn' => true,
            'accountEnabled' => true,
            'user' => ['id' => $this->actor],
            'authorizedBranches' => [(string) $this->branch],
            'permissions' => ['ground.view', 'ground.create', 'ground.update', 'ground.maintenance'],
        ]);
        \Config\Services::resetSingle('authorization');
    }

    protected function tearDown(): void
    {
        $this->db->table('sessions')->where('ground_id IS NOT NULL', null, false)->update(['ground_id' => null]);
        $this->db->table('ground_unavailability')->emptyTable();
        $this->db->table('grounds')->emptyTable();
        session()->destroy();
        \Config\Services::resetSingle('authorization');
        parent::tearDown();
    }

    public function testCrudMaintenanceAndConflictUsePersistedBranchData(): void
    {
        $service = service('ground', false);
        $id = $service->save([
            'branch_id' => $this->branch,
            'name' => 'TEST_MCC Indoor Net',
            'ground_type' => 'indoor_net',
            'description' => 'Stored test ground',
        ]);
        $service->addMaintenance($id, [
            'starts_at' => '2026-09-10T09:00:00+05:30',
            'ends_at' => '2026-09-10T11:00:00+05:30',
            'reason' => 'TEST_MCC maintenance',
        ]);
        $detail = $service->find($id);

        self::assertSame('TEST_MCC Indoor Net', $detail['name']);
        self::assertCount(1, $detail['maintenance']);
        self::assertSame([], $detail['bookings']);
        self::assertSame('available', $detail['availability_status']);
        self::assertSame(0, $detail['kpis']['booking_count']);
        self::assertSame(1, $detail['kpis']['maintenance_period_count']);
        $this->expectExceptionMessage('Ground is unavailable.');
        $service->ensureBookable($id, $this->branch, '2026-09-10 03:30:00', '2026-09-10 04:30:00');
    }

    public function testBranchIsolationAndIdorProtectionApplyToGroundDetail(): void
    {
        $id = service('ground', false)->save([
            'branch_id' => $this->branch,
            'name' => 'TEST_MCC Branch Ground',
            'ground_type' => 'outdoor_net',
        ]);
        session()->set('authorizedBranches', [(string) $this->otherBranch]);
        \Config\Services::resetSingle('authorization');
        $this->expectExceptionMessage('Forbidden');
        service('ground', false)->find($id);
    }

    public function testGroundUpdateCannotMoveResourceAcrossBranchesThroughForgedInput(): void
    {
        $id = service('ground', false)->save([
            'branch_id' => $this->branch,
            'name' => 'TEST_MCC Immutable Branch Ground',
            'ground_type' => 'venue',
        ]);

        $this->expectExceptionMessage('Forbidden');
        service('ground', false)->save([
            'branch_id' => $this->otherBranch,
            'name' => 'TEST_MCC Forged Branch Ground',
            'ground_type' => 'venue',
        ], $id);
    }

    private function id(string $table, array $where): int
    {
        $row = $this->db->table($table)->where($where)->get()->getRowArray();
        if (!is_array($row)) {
            throw new RuntimeException('Test dataset is not seeded.');
        }
        return (int) $row['id'];
    }
}
