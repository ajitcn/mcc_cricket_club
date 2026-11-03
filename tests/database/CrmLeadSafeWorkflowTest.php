<?php

declare (strict_types=1);
namespace Tests\Database;

use CodeIgniter\Security\Exceptions\SecurityException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Database;
use RuntimeException;
/**
 * Persistent, non-destructive Lead workflow verification.
 *
 * Fixtures intentionally remain in the approved test database and are marked
 * with TEST_MCC_LEAD_<unique-id> for later inspection.
 */
final class CrmLeadSafeWorkflowTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    protected $DBGroup = 'tests';
    protected $migrate = false;
    private object $database;
    private int $branch;
    private int $otherBranch;
    private int $actor;
    private string $marker;
    private array $createdLeadIds = [];
    protected function setUp(): void
    {
        parent::setUp();
        $this->database = Database::connect('tests');
        self::assertSame('mcc_cricket_club_test', strtolower((string) $this->database->getDatabase()));
        $this->marker = 'TEST_MCC_LEAD_' . strtoupper(bin2hex(random_bytes(5)));
        $this->branch = (int) $this->database->table('branches')->where('code', 'TEST_MCC_VNS')->get()->getRow('id');
        $this->otherBranch = (int) $this->database->table('branches')->where('code', 'TEST_MCC_DEL')->get()->getRow('id');
        $this->actor = (int) $this->database->table('users')->where('email', 'test.mcc.superadmin@example.invalid')->get()->getRow('id');
        self::assertGreaterThan(0, $this->branch);
        self::assertGreaterThan(0, $this->otherBranch);
        self::assertGreaterThan(0, $this->actor);
        $this->asAuthorizedUser();
    }
    protected function tearDown(): void
    {
        // Persistent fixtures are deliberately retained. Only session state is reset.
        session()->destroy();
        \Config\Services::resetSingle('authorization');
        parent::tearDown();
    }
    public function testAuthenticatedListFiltersAndCanonicalLegacyDetailRedirect(): void
    {
        $leadId = $this->createLead('List');
        $result = service('crm', false)->paginatedLeads(['q' => $this->marker, 'source' => 'TEST_MCC_SEED', 'status' => 'lead',
    'branch_id' => (string) $this->branch, 'created_from' => date('Y-m-d'), 'created_to' => date('Y-m-d')], 1, 1);
        self::assertSame(1, $result['total']);
        self::assertSame($leadId, (int) $result['leads'][0]['id']);
        self::assertSame(['q' => $this->marker, 'source' => 'TEST_MCC_SEED', 'status' => 'lead', 'branch_id' => (string) $this->branch,
    'created_from' => date('Y-m-d'), 'created_to' => date('Y-m-d')], $result['filters']);
        self::assertSame(0, service('crm', false)->paginatedLeads(['branch_id' => (string) $this->otherBranch, 'q' => $this->marker])['total']);
        $canonical = $this->withSession(session()->get())->get('crm/leads/' . $leadId);
        self::assertSame(200, $canonical->response()->getStatusCode());
        self::assertStringContainsString('Trials', $canonical->getBody());
        self::assertStringContainsString('Admissions', $canonical->getBody());
        $legacy = $this->withSession(session()->get())->get('crm/leads/' . $leadId . '/page');
        self::assertSame(301, $legacy->response()->getStatusCode());
        self::assertStringEndsWith('/crm/leads/' . $leadId, (string) $legacy->response()->getHeaderLine('Location'));
    }
    public function testUnauthenticatedAndUnauthorizedAccessAreRejected(): void
    {
        session()->destroy();
        \Config\Services::resetSingle('authorization');
        self::assertContains($this->get('crm/leads')->response()->getStatusCode(), [302, 401, 403]);
        $this->asAuthorizedUser(['lead.update']);
        self::assertSame(403, $this->withSession(session()->get())->get('crm/leads')->response()->getStatusCode());
    }
    public function testCreatePersistsUniqueFixtureHistoryAuditAndDuplicateWarning(): void
    {
        $email = strtolower($this->marker) . '@example.invalid';
        $first = service('crm', false)->create($this->validLead(['email' => $email]));
        $second = service('crm', false)->create($this->validLead(['email' => $email, 'full_name' => $this->marker . ' duplicate']));
        $this->createdLeadIds = [(int) $first['id'], (int) $second['id']];
        self::assertTrue($second['duplicate_warning']);
        self::assertNotSame($first['id'], $second['id']);
        self::assertSame(1, $this->database->table('lead_status_history')->where('lead_id', $first['id'])->countAllResults());
        self::assertSame(1, $this->database->table('audit_logs')->where(['action' => 'lead_created', 'entity_id' => (string) $first['id']])->countAllResults());
        $audit = $this->database->table('audit_logs')->where(['action' => 'lead_created', 'entity_id' => (string) $second['id']])->get()->getRowArray();
        self::assertStringContainsString('duplicate', strtolower((string) json_encode($audit)));
    }

    public function testCreatorAssignedUserAndAdminVisibilityAreEnforced(): void
    {
        $assignedUser = (int) $this->database->table('users')
            ->where('email', 'test.mcc.reception@example.invalid')
            ->get()->getRow('id');
        $crossBranchUser = (int) $this->database->table('users')
            ->where('email', 'test.mcc.staff.del@example.invalid')
            ->get()->getRow('id');
        $this->asAuthorizedUser(['lead.create', 'lead.view', 'lead.update'], [$this->branch, $this->otherBranch]);
        $leadId = (int) service('crm', false)->create($this->validLead([
            'full_name' => $this->marker . ' Assigned',
            'owner_user_id' => $crossBranchUser,
        ]))['id'];
        $otherLeadId = (int) service('crm', false)->create($this->validLead([
            'branch_id' => $this->otherBranch,
            'full_name' => $this->marker . ' Assigned Back',
            'owner_user_id' => $assignedUser,
        ]))['id'];

        $row = $this->database->table('leads')->where('id', $leadId)->get()->getRowArray();
        self::assertSame($this->actor, (int) $row['created_by_user_id']);
        self::assertSame($crossBranchUser, (int) $row['owner_user_id']);
        self::assertSame($assignedUser, (int) $this->database->table('leads')->where('id', $otherLeadId)->get()->getRow('owner_user_id'));

        $assignedSession = [
            'isLoggedIn' => true,
            'accountEnabled' => true,
            'user' => ['id' => $crossBranchUser],
            'session_version' => 1,
            'authorizedBranches' => [(string) $this->otherBranch],
            'permissions' => ['lead.view'],
        ];
        session()->set($assignedSession);
        \Config\Services::resetSingle('authorization');
        self::assertSame($leadId, (int) service('crm', false)->paginatedLeads([
            'q' => $this->marker . ' Assigned',
        ])['leads'][0]['id']);
        $assignedDetails = service('crm', false)->leadDetails($leadId);
        self::assertSame($leadId, (int) $assignedDetails['id']);
        self::assertSame('TEST_MCC_STAFF_DEL', $assignedDetails['owner_name']);
        self::assertSame('test.mcc.staff.del@example.invalid', $assignedDetails['owner_email']);
        self::assertSame(0, service('crm', false)->paginatedLeads([
            'q' => $this->marker . ' Assigned Back',
        ])['total']);

        $creatorSession = [
            'isLoggedIn' => true,
            'accountEnabled' => true,
            'user' => ['id' => $this->actor],
            'session_version' => 1,
            'authorizedBranches' => [(string) $this->branch, (string) $this->otherBranch],
            'permissions' => ['lead.view'],
        ];
        session()->set($creatorSession);
        \Config\Services::resetSingle('authorization');
        self::assertSame($leadId, (int) service('crm', false)->leadDetails($leadId)['id']);
        self::assertSame($otherLeadId, (int) service('crm', false)->leadDetails($otherLeadId)['id']);

        $unrelatedUser = (int) $this->database->table('users')
            ->where('email', 'test.mcc.coach@example.invalid')
            ->get()->getRow('id');
        session()->set([
            'isLoggedIn' => true,
            'accountEnabled' => true,
            'user' => ['id' => $unrelatedUser],
            'session_version' => 1,
            'authorizedBranches' => [(string) $this->branch],
            'permissions' => ['lead.view'],
        ]);
        \Config\Services::resetSingle('authorization');
        self::assertSame(0, service('crm', false)->paginatedLeads([
            'q' => $this->marker . ' Assigned',
        ])['total']);
        try {
            service('crm', false)->leadDetails($leadId);
            self::fail('Unrelated user was allowed to view the lead.');
        } catch (RuntimeException $exception) {
            self::assertSame('Not found.', $exception->getMessage());
        }

        $this->asAuthorizedUser(['lead.view', 'lead.update', 'rbac.manage'], [$this->branch, $this->otherBranch]);
        self::assertSame(2, service('crm', false)->paginatedLeads([
            'q' => $this->marker . ' Assigned',
        ])['total']);

        service('crm', false)->updateLead($leadId, ['owner_user_id' => '']);
        $row = $this->database->table('leads')->where('id', $leadId)->get()->getRowArray();
        self::assertNull($row['owner_user_id']);
        $details = service('crm', false)->leadDetails($leadId);
        self::assertSame('', $details['owner_name']);
        self::assertSame('', $details['owner_email']);

        service('crm', false)->updateLead($otherLeadId, ['owner_user_id' => '']);
        self::assertNull($this->database->table('leads')->where('id', $otherLeadId)->get()->getRow('owner_user_id'));
    }
    public function testValidationAndCsrfRejectWithoutPersistence(): void
    {
        $email = strtolower($this->marker) . '.invalid@example.invalid';
        try {
            service('crm', false)->create($this->validLead(['full_name' => '', 'email' => $email]));
            self::fail('Invalid Lead was accepted.');
        } catch (RuntimeException $exception) {
            self::assertSame('Invalid lead.', $exception->getMessage());
        }
        self::assertSame(0, $this->database->table('leads')->where('email', $email)->countAllResults());
        $this->expectException(SecurityException::class);
        $this->withSession(session()->get())->post('crm/leads', $this->validLead());
    }
    public function testAssignmentRequiresPermissionAndSupportsSameBranchUsers(): void
    {
        $leadId = $this->createLead('Same_branch_assignment');
        $sameBranchUser = (int) $this->database->table('users')
            ->where('email', 'test.mcc.reception@example.invalid')
            ->get()->getRow('id');

        service('crm', false)->assign($leadId, $this->branch, $sameBranchUser);
        self::assertSame($sameBranchUser, (int) $this->database->table('leads')->where('id', $leadId)->get()->getRow('owner_user_id'));

        session()->set('permissions', ['lead.view']);
        \Config\Services::resetSingle('authorization');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Forbidden');
        service('crm', false)->assign($leadId, $this->branch, $this->actor);
    }
    public function testEditPreservesBranchAndAuditsAuthorizedUpdate(): void
    {
        $leadId = $this->createLead('Edit');
        service('crm', false)->updateLead($leadId, ['full_name' => $this->marker . ' edited',
    'email' => strtolower($this->marker) . '.edited@example.invalid', 'phone' => '7000000001', 'date_of_birth' => '2012-05-10', 'source' => 'TEST_MCC_SEED']);
        $row = $this->database->table('leads')->where('id', $leadId)->get()->getRowArray();
        self::assertSame($this->marker . ' edited', $row['full_name']);
        self::assertSame($this->branch, (int) $row['branch_id']);
        self::assertSame(1, $this->database->table('audit_logs')->where(['action' => 'lead_updated', 'entity_id' => (string) $leadId])->countAllResults());
    }
    public function testLifecycleRequiresReasonAndPreservesAppendOnlyHistory(): void
    {
        $leadId = $this->createLead('Lifecycle');
        try {
            service('crm', false)->status($leadId, $this->branch, 'lost');
            self::fail('Lost transition without reason was accepted.');
        } catch (RuntimeException $exception) {
            self::assertSame('Lost reason required.', $exception->getMessage());
        }
        service('crm', false)->status($leadId, $this->branch, 'lost', 'TEST_MCC verification');
        service('crm', false)->status($leadId, $this->branch, 'lead', 'TEST_MCC reopened');
        self::assertSame('lead', $this->database->table('leads')->where('id', $leadId)->get()->getRow('status'));
        self::assertSame(3, $this->database->table('lead_status_history')->where('lead_id', $leadId)->countAllResults());
        self::assertSame(1, $this->database->table('audit_logs')->where(['action' => 'lead_status_changed',
    'entity_id' => (string) $leadId])->countAllResults());
        self::assertSame(1, $this->database->table('audit_logs')->where(['action' => 'lead_reopened', 'entity_id' => (string) $leadId])->countAllResults());
    }
    public function testBranchIsolationAndPermissionApplyToDetailsAndMutation(): void
    {
        $leadId = $this->createLead('Security');
        $this->asAuthorizedUser(['lead.view'], [$this->otherBranch]);
        self::assertContains($this->withSession(session()->get())->get('crm/leads/' . $leadId)->response()->getStatusCode(), [403, 404]);
        try {
            service('crm', false)->updateLead($leadId, ['full_name' => $this->marker . ' blocked', 'source' => 'TEST_MCC_SEED']);
            self::fail('Cross-branch update was accepted.');
        } catch (RuntimeException $exception) {
            self::assertContains($exception->getMessage(), ['Forbidden', 'Not found.']);
        }
    }
    public function testRelationshipsRemainLeadScopedAndNoPhysicalDeletionWorkflowExists(): void
    {
        $leadId = $this->createLead('Relationships');
        $details = service('crm', false)->leadDetails($leadId);
        self::assertSame($leadId, (int) $details['id']);
        self::assertIsArray($details['follow_ups']);
        self::assertIsArray($details['trials']);
        self::assertIsArray($details['admissions']);
        foreach ([$details['trials'], $details['admissions']] as $rows) {
            foreach ($rows as $row) {
                self::assertSame($leadId, (int) $row['lead_id']);
                self::assertSame($this->branch, (int) $row['branch_id']);
            }
        }
        self::assertFalse(method_exists(service('crm', false), 'delete'));
    }
    private function createLead(string $suffix): int
    {
        $result = service('crm', false)->create($this->validLead(['full_name' => $this->marker . ' ' . $suffix,
    'email' => strtolower($this->marker . '.' . strtolower($suffix)) . '@example.invalid']));
        $id = (int) $result['id'];
        $this->createdLeadIds[] = $id;
        return $id;
    }
    private function validLead(array $overrides = []): array
    {
        return array_merge(['branch_id' => $this->branch, 'source' => 'TEST_MCC_SEED', 'status' => 'lead', 'full_name' => $this->marker, 'email' => null,
    'phone' => null, 'date_of_birth' => '2012-05-10'], $overrides);
    }
    private function asAuthorizedUser(array $permissions = ['lead.create', 'lead.view', 'lead.update'], ?array $branches = null): void
    {
        session()->set(['isLoggedIn' => true, 'accountEnabled' => true, 'user' => ['id' => $this->actor], 'session_version' => 1,
    'authorizedBranches' => array_map('strval', $branches ?? [$this->branch]), 'permissions' => $permissions]);
        \Config\Services::resetSingle('authorization');
    }
}
