<?php

declare (strict_types=1);
namespace Tests\Database;

use App\Models\UserModel;
use App\Services\CrmService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use RuntimeException;
final class CrmLeadIntegrationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    protected $DBGroup = 'tests';
    protected $migrate = false;
    private int $branchOne;
    private int $branchTwo;
    private int $actor;
    private int $secondUser;
    protected function setUp(): void
    {
        parent::setUp();
        self::assertSame('mcc_cricket_club_test', $this->db->getDatabase());
        $this->db->table('audit_logs')->emptyTable();
        $this->db->table('lead_follow_ups')->emptyTable();
        $this->db->table('lead_status_history')->emptyTable();
        $this->db->table('leads')->emptyTable();
        $this->db->table('user_branches')->emptyTable();
        $this->db->table('users')->emptyTable();
        $this->db->table('branches')->emptyTable();
        $now = date('Y-m-d H:i:s');
        $this->db->table('branches')->insert(['name' => 'North', 'code' => 'north', 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);
        $this->branchOne = (int) $this->db->insertID();
        $this->db->table('branches')->insert(['name' => 'South', 'code' => 'south', 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);
        $this->branchTwo = (int) $this->db->insertID();
        $this->actor = $this->createUser('crm.actor@example.test');
        $this->secondUser = $this->createUser('crm.owner@example.test');
        $this->db->table('user_branches')->insert(['user_id' => $this->actor, 'branch_id' => $this->branchOne, 'created_at' => $now]);
        $this->db->table('user_branches')->insert(['user_id' => $this->secondUser, 'branch_id' => $this->branchOne, 'created_at' => $now]);
        $this->setSession($this->actor, [$this->branchOne], ['lead.create', 'lead.view', 'lead.update']);
    }
    protected function tearDown(): void
    {
        session()->destroy();
        parent::tearDown();
    }
    public function testAuthenticatedLeadCreationPersistsImmutableIdHistoryAndAudit(): void
    {
        $result = $this->crm()->create($this->validLead());
        self::assertIsInt($result['id']);
        self::assertFalse($result['duplicate_warning']);
        $lead = $this->db->table('leads')->where('id', $result['id'])->get()->getRowArray();
        self::assertSame($this->branchOne, (int) $lead['branch_id']);
        self::assertSame($this->actor, (int) $lead['owner_user_id']);
        self::assertSame('lead', $lead['status']);
        self::assertSame(1, $this->db->table('lead_status_history')->where('lead_id', $result['id'])->countAllResults());
        self::assertSame(1, $this->db->table('audit_logs')->where(['action' => 'lead_created', 'entity_id' => (string) $result['id']])->countAllResults());
    }
    public function testUnauthorizedAndCrossBranchAccessAreDenied(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Forbidden');
        $this->crm()->create(['branch_id' => $this->branchTwo, 'source' => 'web', 'full_name' => 'Blocked Lead']);
    }
    public function testPermissionIsRequiredForAuthenticatedHttpAccess(): void
    {
        self::assertTrue(service('auth')->attempt('crm.actor@example.test', 'Test password!'));
        session()->set(['authorizedBranches' => [(string) $this->branchOne], 'permissions' => []]);
        \Config\Services::resetSingle('authorization');
        $response = $this->withSession(session()->get())->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])->get('crm/leads/1?branch_id=' .
    $this->branchOne);
        self::assertSame(403, $response->response()->getStatusCode());
    }
    public function testValidationFailureDoesNotPersistLead(): void
    {
        try {
            $this->crm()->create(['branch_id' => $this->branchOne, 'source' => 'web', 'full_name' => '', 'email' => 'bad']);
            self::fail('Invalid lead was accepted.');
        } catch (RuntimeException $exception) {
            self::assertSame('Invalid lead.', $exception->getMessage());
        }
        self::assertSame(0, $this->db->table('leads')->countAllResults());
    }
    public function testDuplicateWarningDoesNotMergeRecords(): void
    {
        $first = $this->crm()->create($this->validLead());
        $second = $this->crm()->create($this->validLead());
        self::assertTrue($second['duplicate_warning']);
        self::assertNotSame($first['id'], $second['id']);
        self::assertSame(2, $this->db->table('leads')->countAllResults());
    }
    public function testStatusTransitionsRequireLostReasonAndPreserveHistory(): void
    {
        $id = $this->crm()->create($this->validLead())['id'];
        $this->expectException(RuntimeException::class);
        $this->crm()->status($id, $this->branchOne, 'lost');
    }
    public function testLostAndReopenTransitionsAreControlledAndAudited(): void
    {
        $id = $this->crm()->create($this->validLead())['id'];
        $this->crm()->status($id, $this->branchOne, 'lost', 'No response');
        $lead = $this->db->table('leads')->where('id', $id)->get()->getRowArray();
        self::assertSame('lost', $lead['status']);
        self::assertSame('No response', $lead['lost_reason']);
        self::assertNotNull($lead['closed_at']);
        $this->crm()->status($id, $this->branchOne, 'lead', 'Customer re-engaged');
        $lead = $this->db->table('leads')->where('id', $id)->get()->getRowArray();
        self::assertSame('lead', $lead['status']);
        self::assertNull($lead['lost_reason']);
        self::assertNull($lead['closed_at']);
        self::assertSame(3, $this->db->table('lead_status_history')->where('lead_id', $id)->countAllResults());
        self::assertSame(1, $this->db->table('audit_logs')->where(['action' => 'lead_status_changed', 'entity_id' => (string) $id])->countAllResults());
        self::assertSame(1, $this->db->table('audit_logs')->where(['action' => 'lead_reopened', 'entity_id' => (string) $id])->countAllResults());
    }
    public function testRejectedTransitionRollsBackAndPreservesLeadState(): void
    {
        $id = $this->crm()->create($this->validLead())['id'];
        $this->crm()->status($id, $this->branchOne, 'lost', 'No response');
        try {
            $this->crm()->status($id, $this->branchOne, 'lost', 'Still no response');
            self::fail('Repeated status transition was accepted.');
        } catch (RuntimeException $exception) {
            self::assertSame('Status transition rejected.', $exception->getMessage());
        }
        self::assertSame('lost', $this->db->table('leads')->where('id', $id)->get()->getRow()->status);
        self::assertSame(2, $this->db->table('lead_status_history')->where('lead_id', $id)->countAllResults());
    }
    public function testAssignmentUpdateRequiresSameBranchAndAuditsChange(): void
    {
        $id = $this->crm()->create($this->validLead())['id'];
        $this->crm()->assign($id, $this->branchOne, $this->secondUser);
        self::assertSame($this->secondUser, (int) $this->db->table('leads')->where('id', $id)->get()->getRow()->owner_user_id);
        self::assertSame(1, $this->db->table('audit_logs')->where(['action' => 'lead_reassigned', 'entity_id' => (string) $id])->countAllResults());
    }
    public function testFollowUpIsPersistedAndAudited(): void
    {
        $id = $this->crm()->create($this->validLead())['id'];
        $this->crm()->followUp($id, $this->branchOne, 'Call guardian', '2026-09-01 10:00:00');
        self::assertSame(1, $this->db->table('lead_follow_ups')->where('lead_id', $id)->countAllResults());
        self::assertSame(1, $this->db->table('audit_logs')->where(['action' => 'lead_follow_up_recorded', 'entity_id' => (string) $id])->countAllResults());
    }
    public function testBranchScopedFindDoesNotReturnOtherBranchRecord(): void
    {
        $id = $this->crm()->create($this->validLead())['id'];
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Forbidden');
        $this->crm()->find($id, $this->branchTwo);
    }
    private function crm(): CrmService
    {
        return service('crm', false);
    }
    private function validLead(): array
    {
        return ['branch_id' => $this->branchOne, 'source' => 'website', 'full_name' => 'Aarav Sharma', 'email' => 'aarav@example.test',
    'phone' => '9999999999', 'date_of_birth' => '2012-05-10'];
    }
    private function createUser(string $email): int
    {
        $model = new UserModel();
        $model->insert(['email' => $email, 'password_hash' => password_hash('Test password!', PASSWORD_DEFAULT), 'display_name' => 'CRM User',
    'is_enabled' => 1, 'failed_login_count' => 0, 'session_version' => 1, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
        return (int) $model->getInsertID();
    }
    private function setSession(int $user, array $branches, array $permissions): void
    {
        \Config\Services::resetSingle('authorization');
        session()->set(['isLoggedIn' => true, 'accountEnabled' => true, 'user' => ['id' => $user], 'session_version' => 1,
    'authorizedBranches' => array_map('strval', $branches), 'permissions' => $permissions]);
    }
}
