<?php

declare (strict_types=1);
namespace Tests\Database;

use CodeIgniter\Security\Exceptions\SecurityException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Database;
use RuntimeException;
final class LeadConfigurationIntegrationTest extends CIUnitTestCase
{
    use FeatureTestTrait;
    private object $database;
    private int $admin;
    private int $coach;
    private array $sourceIds = [];
    private array $statusIds = [];
    private array $leadIds = [];
    protected function setUp(): void
    {
        parent::setUp();
        $this->database = Database::connect('tests');
        self::assertSame('mcc_cricket_club_test', strtolower((string) $this->database->getDatabase()));
        $this->admin = (int) $this->database->table('users')->where('email', 'test.mcc.superadmin@example.invalid')->get()->getRow('id');
        $this->coach = (int) $this->database->table('users')->where('email', 'test.mcc.coach@example.invalid')->get()->getRow('id');
    }
    protected function tearDown(): void
    {
        foreach ($this->leadIds as $id) {
            $this->database->table('lead_status_history')->where('lead_id', $id)->delete();
            $this->database->table('leads')->where('id', $id)->delete();
        }
        foreach ($this->sourceIds as $id) {
            $this->database->table('lead_sources')->where('id', $id)->delete();
        }
        foreach ($this->statusIds as $id) {
            $this->database->table('lead_statuses')->where('id', $id)->delete();
        }
        session()->destroy();
        \Config\Services::resetSingle('authorization');
        parent::tearDown();
    }
    public function testAdminCanManageSourceAndStatusLifecycle(): void
    {
        $this->adminSession();
        $source = 'TEST_MCC_SOURCE_' . bin2hex(random_bytes(3));
        $status = 'test_mcc_status_' . bin2hex(random_bytes(3));
        $sid = $this->sourceIds[] = service('admin')->createLeadSource($source, 2);
        $tid = $this->statusIds[] = service('admin')->createLeadStatus($status, 3);
        service('admin')->updateLeadSource($sid, $source . 'X', 4);
        service('admin')->updateLeadStatus($tid, $status . 'x', 5);
        service('admin')->setLeadSourceActive($sid, false);
        service('admin')->setLeadStatusActive($tid, false);
        self::assertSame(0, (int) $this->database->table('lead_sources')->where('id', $sid)->get()->getRow('is_active'));
        self::assertSame(0, (int) $this->database->table('lead_statuses')->where('id', $tid)->get()->getRow('is_active'));
        service('admin')->setLeadSourceActive($sid, true);
        service('admin')->setLeadStatusActive($tid, true);
        self::assertSame(1, (int) $this->database->table('lead_sources')->where('id', $sid)->get()->getRow('is_active'));
        self::assertSame(1, (int) $this->database->table('lead_statuses')->where('id', $tid)->get()->getRow('is_active'));
        self::assertGreaterThanOrEqual(1, $this->database->table('audit_logs')->where(['entity_type' => 'lead_source', 'entity_id' => (string) $sid,
    'action' => 'lead_source_deactivated'])->countAllResults());
        self::assertGreaterThanOrEqual(1, $this->database->table('audit_logs')->where(['entity_type' => 'lead_status', 'entity_id' => (string) $tid,
    'action' => 'lead_status_reactivated'])->countAllResults());
    }
    public function testUnauthorizedConfigurationManagementIsDenied(): void
    {
        $this->coachSession();
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Forbidden');
        service('admin')->createLeadSource('TEST_MCC_DENIED_' . bin2hex(random_bytes(2)));
    }
    public function testConfigurationPostRequiresCsrf(): void
    {
        $this->adminSession();
        $this->expectException(SecurityException::class);
        $this->withSession(session()->get())->post('admin/lead-sources', ['name' => 'TEST_MCC_CSRF']);
    }
    public function testLeadFormUsesActiveDatabaseValues(): void
    {
        $this->adminSession();
        $sid = $this->sourceIds[] = service('admin')->createLeadSource('TEST_MCC_ACTIVE_' . bin2hex(random_bytes(2)));
        $tid = $this->statusIds[] = service('admin')->createLeadStatus('test_mcc_active_' . bin2hex(random_bytes(2)));
        $response = $this->withSession(session()->get())->get('crm/leads/new');
        self::assertSame(200, $response->response()->getStatusCode());
        self::assertStringContainsString('TEST_MCC_ACTIVE_', $response->getBody());
        service('admin')->setLeadSourceActive($sid, false);
        service('admin')->setLeadStatusActive($tid, false);
    }
    public function testLeadRejectsInvalidConfiguredValuesAndAcceptsValidValues(): void
    {
        $this->adminSession();
        $branch = (int) $this->database->table('user_branches')->where('user_id', $this->admin)->get()->getRow('branch_id');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid lead.');
        service('crm')->create(['branch_id' => $branch, 'source' => 'not-configured', 'status' => 'not-configured', 'full_name' => 'TEST_MCC Invalid']);
    }
    public function testValidConfiguredLeadPersistsAndInactiveHistoricalValueRemains(): void
    {
        $this->adminSession();
        $source = 'TEST_MCC_HIST_' . bin2hex(random_bytes(2));
        $status = 'test_mcc_hist_' . bin2hex(random_bytes(2));
        $sid = service('admin')->createLeadSource($source);
        $this->sourceIds[] = $sid;
        $tid = service('admin')->createLeadStatus($status);
        $this->statusIds[] = $tid;
        $branch = (int) $this->database->table('user_branches')->where('user_id', $this->admin)->get()->getRow('branch_id');
        $result = service('crm')->create(['branch_id' => $branch, 'source' => $source, 'status' => $status, 'full_name' => 'TEST_MCC Configured Lead',
    'email' => 'test.mcc.config.' . bin2hex(random_bytes(2)) . '@example.invalid']);
        $id = (int) $result['id'];
        $this->leadIds[] = $id;
        service('admin')->setLeadSourceActive($sid, false);
        service('admin')->setLeadStatusActive($tid, false);
        $row = $this->database->table('leads')->where('id', $id)->get()->getRowArray();
        self::assertSame($source, $row['source']);
        self::assertSame($status, $row['status']);
        self::assertSame(1, $this->database->table('audit_logs')->where(['action' => 'lead_created', 'entity_id' => (string) $id])->countAllResults());
    }
    private function adminSession(): void
    {
        $branchIds = array_map('strval', array_column(
            $this->database->table('user_branches')->where('user_id', $this->admin)->get()->getResultArray(),
            'branch_id'
        ));
        session()->set(['isLoggedIn' => true, 'accountEnabled' => true, 'user' => ['id' => $this->admin], 'session_version' => 1,
    'authorizedBranches' => $branchIds, 'permissions' => ['rbac.manage', 'lead.create', 'lead.view']]);
        \Config\Services::resetSingle('authorization');
    }
    private function coachSession(): void
    {
        session()->set(['isLoggedIn' => true, 'accountEnabled' => true, 'user' => ['id' => $this->coach], 'session_version' => 1,
    'authorizedBranches' => ['5475'], 'permissions' => ['lead.view']]);
        \Config\Services::resetSingle('authorization');
    }
}
