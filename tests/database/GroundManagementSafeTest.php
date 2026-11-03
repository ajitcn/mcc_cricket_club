<?php

declare(strict_types=1);

namespace Tests\Database;

use App\Services\GroundManagementService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Database;
use RuntimeException;

/** Non-destructive Ground coverage; fixtures are uniquely named and retained. */
final class GroundManagementSafeTest extends CIUnitTestCase
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
        $this->branch = $this->id('branches', 'TEST_MCC_VNS');
        $this->otherBranch = $this->id('branches', 'TEST_MCC_DEL');
        $this->actor = $this->userId('test.mcc.superadmin@example.invalid');
        $this->authorize([
            'ground.view',
            'ground.create',
            'ground.update',
            'ground.maintenance',
            'ground.book',
        ], [$this->branch, $this->otherBranch]);
    }

    protected function tearDown(): void
    {
        session()->destroy();
        \Config\Services::resetSingle('authorization');
        parent::tearDown();
    }

    public function testGroundListReconcilesContextBranchesWithCentralAuthorization(): void
    {
        $service = service('ground', false);
        $branchName = 'TEST_MCC safe authorized ' . bin2hex(random_bytes(4));
        $otherName = 'TEST_MCC safe unauthorized ' . bin2hex(random_bytes(4));
        $service->save([
            'branch_id' => $this->branch,
            'name' => $branchName,
            'ground_type' => 'indoor_net',
        ]);
        $service->save([
            'branch_id' => $this->otherBranch,
            'name' => $otherName,
            'ground_type' => 'venue',
        ]);

        $this->authorize(['ground.view'], [$this->branch]);
        session()->set('branchIds', [$this->otherBranch]);
        \Config\Services::resetSingle('authorization');
        $names = array_column($service->list(['q' => $branchName])['grounds'], 'name');
        $otherNames = array_column($service->list(['q' => $otherName])['grounds'], 'name');

        self::assertContains($branchName, $names);
        self::assertNotContains($otherName, $otherNames);

        $this->authorize([], [$this->branch]);
        self::assertSame([], $service->list()['grounds']);
    }

    public function testMaintenanceUsesGroundResourceLockAndRejectsSameBranchOverlap(): void
    {
        $service = service('ground', false);
        $ground = $service->save([
            'branch_id' => $this->branch,
            'name' => 'TEST_MCC maintenance lock ' . bin2hex(random_bytes(4)),
            'ground_type' => 'outdoor_net',
        ]);
        $period = [
            'starts_at' => '2100-01-01T09:00:00+05:30',
            'ends_at' => '2100-01-01T11:00:00+05:30',
            'reason' => 'TEST_MCC safe maintenance',
        ];
        $service->addMaintenance($ground, $period);

        try {
            $service->addMaintenance($ground, [
                'starts_at' => '2100-01-01T10:00:00+05:30',
                'ends_at' => '2100-01-01T12:00:00+05:30',
                'reason' => 'TEST_MCC overlapping maintenance',
            ]);
            self::fail('Overlapping maintenance was accepted.');
        } catch (RuntimeException $exception) {
            self::assertSame('Ground maintenance conflict.', $exception->getMessage());
        }
    }

    public function testAuditFailureRollsBackGroundCreation(): void
    {
        $name = 'TEST_MCC audit rollback ' . bin2hex(random_bytes(4));
        $failingAudit = static function (...$arguments): void {
            throw new RuntimeException('Audit persistence failed.');
        };
        $service = new GroundManagementService($failingAudit);

        try {
            $service->save([
                'branch_id' => $this->branch,
                'name' => $name,
                'ground_type' => 'venue',
            ]);
            self::fail('Ground creation succeeded despite audit failure.');
        } catch (RuntimeException $exception) {
            self::assertSame('Audit persistence failed.', $exception->getMessage());
        }

        self::assertSame(0, $this->db->table('grounds')->where('name', $name)->countAllResults());
    }

    public function testMismatchedMaintenanceBranchDoesNotAffectGroundAvailability(): void
    {
        $service = service('ground', false);
        $ground = $service->save([
            'branch_id' => $this->branch,
            'name' => 'TEST_MCC lineage ground ' . bin2hex(random_bytes(4)),
            'ground_type' => 'indoor_net',
        ]);
        Database::connect('default')->table('ground_unavailability')->insert([
            'ground_id' => $ground,
            'branch_id' => $this->otherBranch,
            'starts_at' => '2100-02-01 03:30:00',
            'ends_at' => '2100-02-01 05:30:00',
            'reason' => 'TEST_MCC mismatched lineage',
            'created_by' => $this->actor,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);

        $service->ensureBookable(
            $ground,
            $this->branch,
            '2100-02-01 04:00:00',
            '2100-02-01 05:00:00'
        );
        self::assertTrue(true);
    }

    public function testGroundUpdateStoresStatusAndBeforeAfterAuditMetadata(): void
    {
        $service = service('ground', false);
        $ground = $service->save([
            'branch_id' => $this->branch,
            'name' => 'TEST_MCC update ground ' . bin2hex(random_bytes(4)),
            'ground_type' => 'venue',
            'is_active' => 1,
        ]);
        $updatedName = 'TEST_MCC updated ground ' . bin2hex(random_bytes(4));
        $service->save([
            'branch_id' => $this->branch,
            'name' => $updatedName,
            'ground_type' => 'venue',
            'is_active' => 0,
        ], $ground);

        $detail = $service->find($ground);
        $events = array_values(array_filter(
            $detail['audit_history'],
            static fn(array $event): bool => $event['action'] === 'ground_updated'
        ));
        self::assertCount(1, $events);
        self::assertSame($updatedName, $detail['name']);
        self::assertSame(0, (int) $detail['is_active']);
        self::assertSame(1, (int) $events[0]['before_metadata']['is_active']);
        self::assertSame(0, (int) $events[0]['after_metadata']['is_active']);
    }

    public function testMaintenanceValidatesCalendarDatesAndAuditsReasonAndPeriod(): void
    {
        $service = service('ground', false);
        $ground = $service->save([
            'branch_id' => $this->branch,
            'name' => 'TEST_MCC date ground ' . bin2hex(random_bytes(4)),
            'ground_type' => 'indoor_net',
        ]);

        try {
            $service->addMaintenance($ground, [
                'starts_at' => '31-02-2100T09:00:00+05:30',
                'ends_at' => '31-02-2100T10:00:00+05:30',
                'reason' => 'TEST_MCC invalid date',
            ]);
            self::fail('Invalid maintenance calendar date was accepted.');
        } catch (RuntimeException $exception) {
            self::assertSame('Invalid maintenance period.', $exception->getMessage());
        }

        $service->addMaintenance($ground, [
            'starts_at' => '10-03-2100T09:00:00+05:30',
            'ends_at' => '10-03-2100T10:00:00+05:30',
            'reason' => 'TEST_MCC audited maintenance',
        ]);
        $detail = $service->find($ground);
        $events = array_values(array_filter(
            $detail['audit_history'],
            static fn(array $event): bool => $event['action'] === 'ground_maintenance_added'
        ));
        self::assertCount(1, $events);
        self::assertSame('TEST_MCC audited maintenance', $events[0]['reason']);
        self::assertSame('2100-03-10 03:30:00', $events[0]['after_metadata']['starts_at']);
        self::assertSame('2100-03-10 04:30:00', $events[0]['after_metadata']['ends_at']);
    }

    private function id(string $table, string $code): int
    {
        return (int) $this->db->table($table)->where('code', $code)->get()->getRow('id');
    }

    private function userId(string $email): int
    {
        return (int) $this->db->table('users')->where('email', $email)->get()->getRow('id');
    }

    private function authorize(array $permissions, array $branches): void
    {
        session()->set([
            'isLoggedIn' => true,
            'accountEnabled' => true,
            'user' => ['id' => $this->actor],
            'permissions' => $permissions,
            'authorizedBranches' => array_map('strval', $branches),
        ]);
        \Config\Services::resetSingle('authorization');
    }
}
