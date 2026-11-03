<?php

declare(strict_types=1);

namespace Tests\Feature;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

final class BranchEditRoutesTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $DBGroup = 'tests';
    protected $migrate = false;

    private int $actor;
    private int $branch;
    private int $otherBranch;
    /** @var list<int> */
    private array $createdBranches = [];

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

        self::assertGreaterThan(0, min($this->actor, $this->branch, $this->otherBranch));
    }

    protected function tearDown(): void
    {
        foreach ($this->createdBranches as $branchId) {
            $this->db->table('user_branches')->where('branch_id', $branchId)->delete();
            $this->db->table('branches')->where('id', $branchId)->delete();
        }
        session()->destroy();
        \Config\Services::resetSingle('authorization');
        parent::tearDown();
    }

    public function testAuthorizedCreateFormLoads(): void
    {
        $this->authorize([$this->branch]);

        $response = $this->withSession(session()->get())->get('admin/branches/create');

        self::assertSame(200, $response->response()->getStatusCode());
        self::assertStringContainsString('Create branch', $response->getBody());
        self::assertStringContainsString('name="name"', $response->getBody());
        self::assertStringContainsString('name="code"', $response->getBody());
        self::assertStringContainsString('name="address"', $response->getBody());
    }

    public function testCreateValidationFailureReturnsToCreateForm(): void
    {
        $this->authorize([$this->branch]);

        $response = $this->postCreate(['name' => '', 'code' => '', 'address' => '']);

        self::assertSame(302, $response->response()->getStatusCode());
        self::assertStringEndsWith('/admin/branches/create', $response->response()->getHeaderLine('Location'));
    }

    public function testCreateSaveFailureReturnsToCreateForm(): void
    {
        $this->authorize([$this->branch]);

        $response = $this->postCreate([
            'name' => 'TEST_MCC_CREATE_CONFLICT',
            'code' => 'TEST_MCC_DEL',
            'address' => 'Conflict address',
        ]);

        self::assertSame(302, $response->response()->getStatusCode());
        self::assertStringEndsWith('/admin/branches/create', $response->response()->getHeaderLine('Location'));
    }

    public function testSuccessfulCreateUsesExistingDetailWorkflow(): void
    {
        $this->authorize([$this->branch]);
        $code = 'TEST_MCC_CREATE_' . strtoupper(bin2hex(random_bytes(4)));

        $response = $this->postCreate([
            'name' => 'TEST_MCC Create ' . $code,
            'code' => $code,
            'address' => 'Created branch address',
        ]);

        self::assertSame(302, $response->response()->getStatusCode());
        $created = $this->db->table('branches')->where('code', $code)->get()->getRowArray();
        self::assertIsArray($created);
        $this->createdBranches[] = (int) $created['id'];
        self::assertStringEndsWith('/admin/branches/' . $created['id'], $response->response()->getHeaderLine('Location'));
    }

    public function testAuthorizedEditFormLoads(): void
    {
        $this->authorize([$this->branch]);

        $response = $this->withSession(session()->get())->get('admin/branches/' . $this->branch . '/edit');

        self::assertSame(200, $response->response()->getStatusCode());
        self::assertStringContainsString('Edit branch', $response->getBody());
        self::assertStringContainsString('name="name"', $response->getBody());
        self::assertStringContainsString('name="code"', $response->getBody());
        self::assertStringContainsString('name="address"', $response->getBody());
    }

    public function testValidationFailureReturnsToTheSameEditForm(): void
    {
        $this->authorize([$this->branch]);

        $response = $this->postUpdate(['name' => '', 'code' => '', 'address' => '']);

        self::assertSame(302, $response->response()->getStatusCode());
        self::assertStringEndsWith('/admin/branches/' . $this->branch . '/edit', $response->response()->getHeaderLine('Location'));
    }

    public function testSaveFailureReturnsToTheSameEditForm(): void
    {
        $this->authorize([$this->branch]);

        $response = $this->postUpdate([
            'name' => 'TEST_MCC_BRANCH_CONFLICT',
            'code' => 'TEST_MCC_DEL',
            'address' => 'Conflict address',
        ]);

        self::assertSame(302, $response->response()->getStatusCode());
        self::assertStringEndsWith('/admin/branches/' . $this->branch . '/edit', $response->response()->getHeaderLine('Location'));
    }

    public function testCrossBranchEditIsDenied(): void
    {
        $this->authorize([$this->branch]);

        $response = $this->withSession(session()->get())->get('admin/branches/' . $this->otherBranch . '/edit');

        self::assertSame(404, $response->response()->getStatusCode());
    }

    public function testMissingPermissionIsDenied(): void
    {
        $this->authorize([$this->branch], []);

        $response = $this->withSession(session()->get())->get('admin/branches/' . $this->branch . '/edit');

        self::assertContains($response->response()->getStatusCode(), [302, 403]);
    }

    public function testSuccessfulUpdateUsesExistingDetailWorkflow(): void
    {
        $this->authorize([$this->branch]);
        $before = $this->db->table('branches')->where('id', $this->branch)->get()->getRowArray();
        self::assertIsArray($before);

        try {
            $response = $this->postUpdate([
                'name' => $before['name'],
                'code' => $before['code'],
                'address' => 'TEST_MCC branch edit test address',
            ]);

            self::assertSame(302, $response->response()->getStatusCode());
            self::assertStringEndsWith('/admin/branches/' . $this->branch, $response->response()->getHeaderLine('Location'));
        } finally {
            $this->db->table('branches')->where('id', $this->branch)->update([
                'name' => $before['name'],
                'code' => $before['code'],
                'address' => $before['address'],
                'updated_at' => $before['updated_at'],
            ]);
        }
    }

    /** @param list<int> $branches */
    private function authorize(array $branches, ?array $permissions = null): void
    {
        session()->set([
            'isLoggedIn' => true,
            'accountEnabled' => true,
            'user' => ['id' => $this->actor],
            'session_version' => 1,
            'permissions' => $permissions ?? ['rbac.manage'],
            'authorizedBranches' => array_map('strval', $branches),
        ]);
        \Config\Services::resetSingle('authorization');
    }

    /** @param array<string, string> $input */
    private function postUpdate(array $input): object
    {
        $input[csrf_token()] = csrf_hash();

        return $this->withSession(session()->get())->post(
            'admin/branches/' . $this->branch,
            $input,
        );
    }

    /** @param array<string, string> $input */
    private function postCreate(array $input): object
    {
        $input[csrf_token()] = csrf_hash();

        return $this->withSession(session()->get())->post('admin/branches/create', $input);
    }
}
