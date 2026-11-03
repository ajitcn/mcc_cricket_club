<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\GroundManagementService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/** Route coverage for the existing, non-destructive Ground workflows. */
final class GroundManagementRoutesTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $DBGroup = 'tests';
    protected $migrate = false;

    private int $branch;
    private int $otherBranch;
    private int $actor;

    protected function setUp(): void
    {
        parent::setUp();
        self::assertSame('mcc_cricket_club_test', strtolower((string) $this->db->getDatabase()));
        $this->branch = $this->branchId('TEST_MCC_VNS');
        $this->otherBranch = $this->branchId('TEST_MCC_DEL');
        $this->actor = $this->userId('test.mcc.superadmin@example.invalid');
    }

    protected function tearDown(): void
    {
        session()->destroy();
        \Config\Services::resetSingle('authorization');
        parent::tearDown();
    }

    public function testAuthorizedGroundRoutesRenderAndStateFormsIncludeCsrf(): void
    {
        $this->authorize([
            'ground.view',
            'ground.create',
            'ground.update',
            'ground.maintenance',
        ], [$this->branch]);

        $ground = $this->createGround($this->branch);
        $list = $this->withSession(session()->get())->get('grounds');
        $create = $this->withSession(session()->get())->get('grounds/new');
        $detail = $this->withSession(session()->get())->get('grounds/' . $ground);
        $edit = $this->withSession(session()->get())->get('grounds/' . $ground . '/edit');

        self::assertSame(200, $list->response()->getStatusCode());
        self::assertSame(200, $create->response()->getStatusCode());
        self::assertSame(200, $detail->response()->getStatusCode());
        self::assertSame(200, $edit->response()->getStatusCode());
        self::assertStringContainsString('csrf', strtolower($create->getBody()));
        self::assertStringContainsString('csrf', strtolower($edit->getBody()));
        self::assertStringContainsString('csrf', strtolower($detail->getBody()));
    }

    public function testCrossBranchGroundDetailIsDeniedEvenWithGlobalViewPermission(): void
    {
        $ground = $this->createGround($this->otherBranch);
        $this->authorize(['ground.view'], [$this->branch]);

        $response = $this->withSession(session()->get())->get('grounds/' . $ground);

        self::assertContains($response->response()->getStatusCode(), [403, 404]);
        self::assertStringNotContainsString('TEST_MCC cross-branch', $response->getBody());
    }

    public function testInvalidCreateAndUpdateRequestsReturnThroughTheGroundWorkflow(): void
    {
        $this->authorize(['ground.view', 'ground.create', 'ground.update'], [$this->branch]);
        $ground = $this->createGround($this->branch);

        $create = $this->withSession(session()->get())->post('grounds', [
            'branch_id' => $this->branch,
            'name' => '',
            'ground_type' => 'venue',
            csrf_token() => csrf_hash(),
        ]);
        $update = $this->withSession(session()->get())->post('grounds/' . $ground, [
            'branch_id' => $this->otherBranch,
            'name' => 'TEST_MCC forged update',
            'ground_type' => 'venue',
            csrf_token() => csrf_hash(),
        ]);

        self::assertSame(302, $create->response()->getStatusCode());
        self::assertSame(302, $update->response()->getStatusCode());
        self::assertNotSame('', trim((string) session('error')));
    }

    public function testMaintenanceRouteRequiresPermissionAndAuthorizedBranch(): void
    {
        $ground = $this->createGround($this->branch);
        $this->authorize(['ground.view'], [$this->branch]);

        $response = $this->withSession(session()->get())->post('grounds/' . $ground . '/maintenance', [
            'starts_at' => '01-01-2100T09:00:00+05:30',
            'ends_at' => '01-01-2100T10:00:00+05:30',
            'reason' => 'TEST_MCC unauthorized maintenance',
            csrf_token() => csrf_hash(),
        ]);

        self::assertSame(403, $response->response()->getStatusCode());
        self::assertSame(0, $this->db->table('ground_unavailability')->where('reason', 'TEST_MCC unauthorized maintenance')->countAllResults());
    }

    public function testCreateRoutePersistsAnAuditEventThroughTheExistingTransaction(): void
    {
        $this->authorize(['ground.view', 'ground.create'], [$this->branch]);
        $name = 'TEST_MCC feature audit ' . bin2hex(random_bytes(4));

        $response = $this->withSession(session()->get())->post('grounds', [
            'branch_id' => $this->branch,
            'name' => $name,
            'ground_type' => 'venue',
            'is_active' => '1',
            csrf_token() => csrf_hash(),
        ]);

        self::assertSame(302, $response->response()->getStatusCode());
        $ground = $this->db->table('grounds')->where('name', $name)->get()->getRowArray();
        self::assertIsArray($ground);
        self::assertSame(1, $this->db->table('audit_logs')->where([
            'action' => 'ground_created',
            'entity_type' => 'ground',
            'entity_id' => (string) $ground['id'],
            'branch_id' => $this->branch,
        ])->countAllResults());
    }

    private function createGround(int $branch): int
    {
        $this->authorize([
            'ground.view',
            'ground.create',
            'ground.update',
            'ground.maintenance',
        ], [$this->branch, $this->otherBranch]);

        return service('ground', false)->save([
            'branch_id' => $branch,
            'name' => 'TEST_MCC ' . ($branch === $this->otherBranch ? 'cross-branch ' : '') . bin2hex(random_bytes(4)),
            'ground_type' => 'venue',
        ]);
    }

    private function authorize(array $permissions, array $branches): void
    {
        session()->set([
            'isLoggedIn' => true,
            'accountEnabled' => true,
            'user' => ['id' => $this->actor],
            'session_version' => 1,
            'permissions' => $permissions,
            'authorizedBranches' => array_map('strval', $branches),
        ]);
        \Config\Services::resetSingle('authorization');
    }

    private function branchId(string $code): int
    {
        return (int) $this->db->table('branches')->where('code', $code)->get()->getRow('id');
    }

    private function userId(string $email): int
    {
        return (int) $this->db->table('users')->where('email', $email)->get()->getRow('id');
    }
}
