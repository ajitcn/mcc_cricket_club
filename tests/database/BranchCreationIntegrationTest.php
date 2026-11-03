<?php

declare (strict_types=1);
namespace Tests\Database;

use CodeIgniter\Security\Exceptions\SecurityException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Database;
use RuntimeException;
final class BranchCreationIntegrationTest extends CIUnitTestCase
{
    use FeatureTestTrait;
    /** @var object */
    private object $database;
    private int $superAdmin;
    private int $coach;
    /** @var list<string> */
    private array $ownedCodes = [];
    protected function setUp(): void
    {
        parent::setUp();
        $this->database = Database::connect('tests');
        self::assertSame('mcc_cricket_club_test', strtolower((string) $this->database->getDatabase()));
        self::assertSame('mcc_cricket_club_test', strtolower((string) Database::connect('default')->getDatabase()));
        $this->superAdmin = (int) $this->database->table('users')->where('email', 'test.mcc.superadmin@example.invalid')->get()->getRow('id');
        $this->coach = (int) $this->database->table('users')->where('email', 'test.mcc.coach@example.invalid')->get()->getRow('id');
        self::assertGreaterThan(0, $this->superAdmin);
        self::assertGreaterThan(0, $this->coach);
    }
    protected function tearDown(): void
    {
        foreach ($this->ownedCodes as $code) {
            $row = $this->database->table('branches')->where('code', $code)->get()->getRowArray();
            if (is_array($row)) {
                $this->database->table('user_branches')->where('branch_id', (int) $row['id'])->delete();
                $this->database->table('branches')->where('id', (int) $row['id'])->delete();
            }
        }
        session()->destroy();
        \Config\Services::resetSingle('authorization');
        parent::tearDown();
    }
    public function testAuthorizedUserCanCreateBranch(): void
    {
        $this->asUser($this->superAdmin, ['rbac.manage'], []);
        $id = $this->create(['name' => 'Test Creation ' . uniqid(), 'code' => $this->code()]);
        self::assertGreaterThan(0, $id);
    }
    public function testUnauthorizedUserIsDenied(): void
    {
        $this->asUser($this->coach, ['attendance.view'], []);
        $response = $this->withSession(session()->get())->get('admin/branches/create');
        self::assertContains($response->response()->getStatusCode(), [401, 403, 302]);
    }
    public function testUnauthenticatedUserIsDenied(): void
    {
        session()->destroy();
        \Config\Services::resetSingle('authorization');
        $response = $this->get('admin/branches/create');
        self::assertContains($response->response()->getStatusCode(), [401, 403, 302]);
    }
    public function testMissingNameFailsValidation(): void
    {
        $this->asUser($this->superAdmin, ['rbac.manage'], []);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid branch details.');
        $this->create(['name' => '', 'code' => $this->code()]);
    }
    public function testMissingCodeFailsValidation(): void
    {
        $this->asUser($this->superAdmin, ['rbac.manage'], []);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid branch details.');
        $this->create(['name' => 'Missing Code ' . uniqid(), 'code' => '']);
    }
    public function testMissingAddressFailsValidation(): void
    {
        $this->asUser($this->superAdmin, ['rbac.manage'], []);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid branch details.');
        service('admin')->createBranch(['name' => 'Missing Address ' . uniqid(), 'code' => $this->code(), 'address' => '']);
    }
    public function testDuplicateNameIsRejected(): void
    {
        $this->asUser($this->superAdmin, ['rbac.manage'], []);
        $name = 'Duplicate Name ' . uniqid();
        $this->create(['name' => $name, 'code' => $this->code()]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Branch already exists.');
        $this->create(['name' => $name, 'code' => $this->code()]);
    }
    public function testDuplicateCodeIsRejected(): void
    {
        $this->asUser($this->superAdmin, ['rbac.manage'], []);
        $code = $this->code();
        $this->create(['name' => 'Duplicate Code One ' . uniqid(), 'code' => $code]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Branch already exists.');
        $this->create(['name' => 'Duplicate Code Two ' . uniqid(), 'code' => $code]);
    }
    public function testClientCannotOverrideIsActive(): void
    {
        $this->asUser($this->superAdmin, ['rbac.manage'], []);
        $code = $this->code();
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid branch details.');
        $this->create(['name' => 'Override Active ' . uniqid(), 'code' => $code, 'is_active' => 0]);
    }
    public function testClientCannotMassAssignInternalFields(): void
    {
        $this->asUser($this->superAdmin, ['rbac.manage'], []);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid branch details.');
        $this->create(['name' => 'Mass Assignment ' . uniqid(), 'code' => $this->code(), 'id' => 1, 'created_at' => '2000-01-01 00:00:00']);
    }
    public function testSuccessfulBranchIsPersistedAsActive(): void
    {
        $this->asUser($this->superAdmin, ['rbac.manage'], []);
        $code = $this->code();
        $id = $this->create(['name' => 'Active Branch ' . uniqid(), 'code' => $code]);
        $row = $this->database->table('branches')->where('id', $id)->get()->getRowArray();
        self::assertSame(1, (int) $row['is_active']);
    }
    public function testSuccessfulCreationGeneratesNonSensitiveAuditEvent(): void
    {
        $this->asUser($this->superAdmin, ['rbac.manage'], []);
        $code = $this->code();
        $id = $this->create(['name' => 'Audited Branch ' . uniqid(), 'code' => $code]);
        $audit = $this->database->table('audit_logs')->where(['action' => 'branch_created', 'entity_type' => 'branch',
    'entity_id' => (string) $id])->get()->getRowArray();
        self::assertIsArray($audit);
        self::assertStringContainsString($code, (string) $audit['after_summary']);
        self::assertStringNotContainsString('password', strtolower((string) $audit['after_summary']));
        self::assertStringNotContainsString('token', strtolower((string) $audit['after_summary']));
    }
    public function testPostWithoutCsrfIsRejected(): void
    {
        $this->asUser($this->superAdmin, ['rbac.manage'], []);
        $this->expectException(SecurityException::class);
        $this->withSession(session()->get())->post('admin/branches/create', ['name' => 'CSRF Branch ' . uniqid(), 'code' => $this->code()]);
    }
    public function testAuthorizedUserCanListAndViewBranches(): void
    {
        $this->asUser($this->superAdmin, ['rbac.manage'], []);
        $this->create(['name' => 'List Branch ' . uniqid(), 'code' => $this->code()]);
        $response = $this->withSession(session()->get())->get('admin/branches');
        self::assertSame(200, $response->response()->getStatusCode());
        self::assertStringContainsString('Branches', $response->getBody());
    }
    public function testAuthorizedUserCanUpdateBranchAddress(): void
    {
        $this->asUser($this->superAdmin, ['rbac.manage'], []);
        $code = $this->code();
        $id = $this->create(['name' => 'Update Branch ' . uniqid(), 'code' => $code]);
        service('admin')->updateBranch($id, ['name' => 'Updated Branch ' . uniqid(), 'code' => $code, 'address' => 'Updated test address']);
        $row = $this->database->table('branches')->where('id', $id)->get()->getRowArray();
        self::assertSame('Updated test address', $row['address']);
    }
    public function testUnauthorizedUserCannotUpdateBranch(): void
    {
        $this->asUser($this->superAdmin, ['rbac.manage'], []);
        $id = $this->create(['name' => 'Denied Update ' . uniqid(), 'code' => $this->code()]);
        $this->asUser($this->coach, ['attendance.view'], []);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Forbidden');
        service('admin')->updateBranch($id, ['name' => 'Denied', 'code' => $this->code(), 'address' => 'Denied']);
    }
    public function testDuplicateNameIsRejectedOnUpdate(): void
    {
        $this->asUser($this->superAdmin, ['rbac.manage'], []);
        $name = 'Existing Update Name ' . uniqid();
        $this->create(['name' => $name, 'code' => $this->code()]);
        $id = $this->create(['name' => 'Second Update Name ' . uniqid(), 'code' => $this->code()]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Branch already exists.');
        service('admin')->updateBranch($id, ['name' => $name, 'code' => $this->code(), 'address' => 'Address']);
    }
    public function testUpdateCannotMassAssignProtectedFields(): void
    {
        $this->asUser($this->superAdmin, ['rbac.manage'], []);
        $id = $this->create(['name' => 'Protected Update ' . uniqid(), 'code' => $this->code()]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid branch details.');
        service('admin')->updateBranch($id, ['name' => 'Protected', 'code' => $this->code(), 'address' => 'Address', 'is_active' => 0]);
    }
    public function testSuccessfulUpdateGeneratesAuditEvent(): void
    {
        $this->asUser($this->superAdmin, ['rbac.manage'], []);
        $code = $this->code();
        $id = $this->create(['name' => 'Audit Update ' . uniqid(), 'code' => $code]);
        service('admin')->updateBranch($id, ['name' => 'Audited Updated ' . uniqid(), 'code' => $code, 'address' => 'Audit address']);
        self::assertSame(1, $this->database->table('audit_logs')->where(['action' => 'branch_updated', 'entity_type' => 'branch',
    'entity_id' => (string) $id])->countAllResults());
    }
    public function testAuthorizedUserCanDeactivateAndPreserveRelatedRecords(): void
    {
        $this->asUser($this->superAdmin, ['rbac.manage'], []);
        $code = $this->code();
        $id = $this->create(['name' => 'Deactivate Branch ' . uniqid(), 'code' => $code]);
        $this->database->table('user_branches')->insert(['user_id' => $this->coach, 'branch_id' => $id, 'created_at' => date('Y-m-d H:i:s')]);
        service('admin')->setBranchActive($id, false);
        $row = $this->database->table('branches')->where('id', $id)->get()->getRowArray();
        self::assertSame(0, (int) $row['is_active']);
        self::assertSame(1, $this->database->table('user_branches')->where(['user_id' => $this->coach, 'branch_id' => $id])->countAllResults());
        self::assertSame(1, $this->database->table('audit_logs')->where(['action' => 'branch_deactivated', 'entity_id' => (string) $id])->countAllResults());
    }
    public function testUnauthorizedUserCannotDeactivateBranch(): void
    {
        $this->asUser($this->superAdmin, ['rbac.manage'], []);
        $id = $this->create(['name' => 'Denied Deactivate ' . uniqid(), 'code' => $this->code()]);
        $this->asUser($this->coach, ['attendance.view'], []);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Forbidden');
        service('admin')->setBranchActive($id, false);
    }
    public function testStatusPostRequiresCsrf(): void
    {
        $this->asUser($this->superAdmin, ['rbac.manage'], []);
        $id = $this->create(['name' => 'CSRF Status ' . uniqid(), 'code' => $this->code()]);
        $this->expectException(SecurityException::class);
        $this->withSession(session()->get())->post('admin/branches/' . $id . '/status', ['active' => '0']);
    }
    public function testAuthorizedUserCanReactivateAndPreserveBranchData(): void
    {
        $this->asUser($this->superAdmin, ['rbac.manage'], []);
        $code = $this->code();
        $id = $this->create(['name' => 'Reactivate Branch ' . uniqid(), 'code' => $code, 'address' => 'Preserved address']);
        service('admin')->setBranchActive($id, false);
        service('admin')->setBranchActive($id, true);
        $row = $this->database->table('branches')->where('id', $id)->get()->getRowArray();
        self::assertSame(1, (int) $row['is_active']);
        self::assertSame($code, $row['code']);
        self::assertSame('Preserved address', $row['address']);
        self::assertSame(1, $this->database->table('audit_logs')->where(['action' => 'branch_reactivated', 'entity_id' => (string) $id])->countAllResults());
    }
    public function testUnauthorizedUserCannotReactivateBranch(): void
    {
        $this->asUser($this->superAdmin, ['rbac.manage'], []);
        $id = $this->create(['name' => 'Denied Reactivate ' . uniqid(), 'code' => $this->code()]);
        service('admin')->setBranchActive($id, false);
        $this->asUser($this->coach, ['attendance.view'], []);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Forbidden');
        service('admin')->setBranchActive($id, true);
    }
    /** @param array<string, mixed> $input */
    private function create(array $input): int
    {
        $input['address'] ??= 'TEST_MCC address, test only';
        $this->ownedCodes[] = (string) ($input['code'] ?? '');
        return service('admin')->createBranch($input);
    }
    private function code(): string
    {
        $code = 'TEST_MCC_BRANCH_' . strtoupper(bin2hex(random_bytes(5)));
        $this->ownedCodes[] = $code;
        return $code;
    }
    /** @param list<string> $permissions */
    private function asUser(int $id, array $permissions, array $branches): void
    {
        session()->set(['isLoggedIn' => true, 'user' => ['id' => $id], 'session_version' => 1, 'permissions' => $permissions,
    'authorizedBranches' => $branches, 'accountEnabled' => true]);
        \Config\Services::resetSingle('authorization');
    }
}
