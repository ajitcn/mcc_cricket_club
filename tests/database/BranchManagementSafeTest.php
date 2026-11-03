<?php

declare (strict_types=1);
namespace Tests\Database;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use RuntimeException;
/** Safe Branch-management coverage; persistent unique fixtures, no cleanup. */
final class BranchManagementSafeTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    protected $DBGroup = 'tests';
    protected $migrate = false;
    private int $actor;
    private int $coach;
    protected function setUp(): void
    {
        parent::setUp();
        self::assertSame('mcc_cricket_club_test', strtolower((string) $this->db->getDatabase()));
        $this->actor = (int) $this->db->table('users')->where('email', 'test.mcc.superadmin@example.invalid')->get()->getRow('id');
        $this->coach = (int) $this->db->table('users')->where('email', 'test.mcc.coach@example.invalid')->get()->getRow('id');
        self::assertGreaterThan(0, min($this->actor, $this->coach));
        $this->authorize(['rbac.manage']);
    }
    protected function tearDown(): void
    {
        session()->destroy();
        \Config\Services::resetSingle('authorization');
        parent::tearDown();
    }
    public function testAuthorizedCreateUpdateAndStatusLifecyclePreservesBranchAndAudits(): void
    {
        $code = 'TEST_MCC_BRANCH_WRITE_' . strtoupper(bin2hex(random_bytes(5)));
        $id = service('admin')->createBranch(['name' => $code, 'code' => $code, 'address' => 'TEST_MCC safe address']);
        self::assertSame(1, (int) $this->db->table('branches')->where('id', $id)->get()->getRow('is_active'));
        service('admin')->updateBranch($id, ['name' => $code . ' UPDATED', 'code' => $code, 'address' => 'TEST_MCC updated address']);
        service('admin')->setBranchActive($id, false);
        service('admin')->setBranchActive($id, true);
        $row = $this->db->table('branches')->where('id', $id)->get()->getRowArray();
        self::assertSame($code . ' UPDATED', $row['name']);
        self::assertSame('TEST_MCC updated address', $row['address']);
        self::assertSame(1, (int) $row['is_active']);
        self::assertSame(1, $this->db->table('audit_logs')->where(['action' => 'branch_created', 'entity_id' => (string) $id])->countAllResults());
        self::assertSame(1, $this->db->table('audit_logs')->where(['action' => 'branch_updated', 'entity_id' => (string) $id])->countAllResults());
        self::assertSame(1, $this->db->table('audit_logs')->where(['action' => 'branch_deactivated', 'entity_id' => (string) $id])->countAllResults());
        self::assertSame(1, $this->db->table('audit_logs')->where(['action' => 'branch_reactivated', 'entity_id' => (string) $id])->countAllResults());
    }
    public function testAuthorizationAndValidationRejectUnsafeWrites(): void
    {
        $this->authorize([]);
        try {
            service('admin')->createBranch(['name' => 'TEST_MCC denied', 'code' => 'TEST_MCC_DENIED_' . bin2hex(random_bytes(3)), 'address' => 'Denied']);
            self::fail('Unauthorized create accepted.');
        } catch (RuntimeException $e) {
            self::assertSame('Forbidden', $e->getMessage());
        }
        $this->authorize(['rbac.manage']);
        try {
            service('admin')->createBranch(['name' => '', 'code' => 'TEST_MCC_INVALID_' . bin2hex(random_bytes(3)), 'address' => 'Invalid']);
            self::fail('Invalid branch accepted.');
        } catch (RuntimeException $e) {
            self::assertSame('Invalid branch details.', $e->getMessage());
        }
        try {
            service('admin')->createBranch(['name' => 'TEST_MCC mass assignment', 'code' => 'TEST_MCC_INVALID_' . bin2hex(random_bytes(3)),
    'address' => 'Invalid', 'is_active' => 0]);
            self::fail('Client status override accepted.');
        } catch (RuntimeException $e) {
            self::assertSame('Invalid branch details.', $e->getMessage());
        }
    }
    public function testExistingBranchCanBeReadWithoutReplacingIt(): void
    {
        $branchId = (int) $this->db->table('branches')->where('code', 'TEST_MCC_VNS')->get()->getRow('id');
        session()->set('authorizedBranches', [(string) $branchId]);
        \Config\Services::resetSingle('authorization');
        $branch = service('admin')->branch($branchId);
        self::assertSame('TEST_MCC_VNS', $branch['code']);
        self::assertContains($branch, service('admin')->managedBranches());
    }
    public function testCanonicalBranchPagesUseSharedLayoutAndCsrfForms(): void
    {
        $list = $this->withSession(session()->get())->get('admin/branches');
        self::assertSame(200, $list->response()->getStatusCode());
        self::assertStringContainsString('mcc-card', $list->getBody());
        self::assertStringContainsString('mcc-table', $list->getBody());
        self::assertStringContainsString('mcc-breadcrumbs', $list->getBody());
        self::assertStringContainsString('mcc-footer', $list->getBody());
        self::assertStringContainsString('Create branch', $list->getBody());
        $form = $this->withSession(session()->get())->get('admin/branches/create');
        self::assertSame(200, $form->response()->getStatusCode());
        self::assertStringContainsString('name="csrf_test_name"', $form->getBody());
        self::assertStringContainsString('mcc-form', $form->getBody());
        self::assertStringContainsString('admin/branches', $form->getBody());
    }
    public function testUnauthenticatedAndNonAdminAccessIsDenied(): void
    {
        session()->destroy();
        \Config\Services::resetSingle('authorization');
        $guest = $this->get('admin/branches');
        self::assertContains($guest->response()->getStatusCode(), [302, 401, 403]);
        $this->authorize(['attendance.view']);
        $coach = $this->withSession(session()->get())->get('admin/branches/create');
        self::assertContains($coach->response()->getStatusCode(), [302, 401, 403]);
    }
    private function authorize(array $permissions): void
    {
        session()->set(['isLoggedIn' => true, 'accountEnabled' => true, 'user' => ['id' => $this->actor], 'session_version' => 1,
    'permissions' => $permissions, 'authorizedBranches' => []]);
        \Config\Services::resetSingle('authorization');
    }
}
