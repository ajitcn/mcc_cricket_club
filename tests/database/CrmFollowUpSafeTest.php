<?php

declare (strict_types=1);
namespace Tests\Database;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use RuntimeException;
final class CrmFollowUpSafeTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    protected $DBGroup = 'tests';
    protected $migrate = false;
    private int $lead;
    private int $branch;
    private int $actor;
    protected function setUp(): void
    {
        parent::setUp();
        self::assertSame('mcc_cricket_club_test', $this->db->getDatabase());
        $branch = $this->db->table('branches')->where('code', 'TEST_MCC_VNS')->get()->getRowArray();
        $lead = $this->db->table('leads')->where('full_name', 'TEST_MCC_FOLLOWUP')->get()->getRowArray();
        $actor = $this->db->table('users')->where('email', 'test.mcc.manager.vns@example.invalid')->get()->getRowArray();
        self::assertIsArray($branch);
        self::assertIsArray($lead);
        self::assertIsArray($actor);
        $this->branch = (int) $branch['id'];
        $this->lead = (int) $lead['id'];
        $this->actor = (int) $actor['id'];
        $this->asAuthorizedUser();
    }
    public function testCreateSupportsAllApprovedTypesAndHistoricalDates(): void
    {
        foreach (['call', 'whatsapp', 'email', 'meeting', 'other'] as $type) {
            $id = service('crm', false)->createFollowUp($this->lead, ['follow_up_type' => $type,
    'note' => 'TEST_MCC_' . $type . '_' . bin2hex(random_bytes(3)), 'follow_up_at' => '2020-01-02 03:04:05']);
            $row = $this->db->table('lead_follow_ups')->where('id', $id)->get()->getRowArray();
            self::assertSame($type, $row['follow_up_type']);
            self::assertSame('pending', $row['status']);
            self::assertNull($row['outcome']);
            self::assertSame('2020-01-02 03:04:05', $row['follow_up_at']);
        }
    }
    public function testPendingFollowUpCanBeUpdatedAndLeadDetailsShowsHistory(): void
    {
        $id = service('crm', false)->createFollowUp($this->lead, ['follow_up_type' => 'call', 'note' => 'TEST_MCC_pending_before']);
        service('crm', false)->updateFollowUp($id, ['follow_up_type' => 'email', 'note' => 'TEST_MCC_pending_after', 'follow_up_at' => null]);
        $row = $this->db->table('lead_follow_ups')->where('id', $id)->get()->getRowArray();
        self::assertSame('email', $row['follow_up_type']);
        self::assertSame('TEST_MCC_pending_after', $row['note']);
        self::assertSame('pending', $row['status']);
        self::assertNotNull($this->db->table('audit_logs')->where(['action' => 'lead_follow_up_updated', 'entity_id' => (string) $id])->get()->getRowArray());
        self::assertNotEmpty(service('crm', false)->leadDetails($this->lead)['follow_ups']);
    }
    public function testLeadDetailsFollowUpLinkUsesTheCanonicalGetRouteAndPostAction(): void
    {
        $page = $this->withSession(session()->get())->get('crm/leads/' . $this->lead);
        self::assertSame(200, $page->response()->getStatusCode());
        self::assertStringContainsString('/crm/leads/' . $this->lead . '/follow-ups/new', $page->getBody());
        $form = $this->withSession(session()->get())->get('crm/leads/' . $this->lead . '/follow-ups/new');
        self::assertSame(200, $form->response()->getStatusCode());
        self::assertStringContainsString('Add follow-up', $form->getBody());
        self::assertStringContainsString('/crm/leads/' . $this->lead . '/follow-ups"', $form->getBody());
        $response = $this->withSession(session()->get())->post('crm/leads/' . $this->lead . '/follow-ups', ['follow_up_type' => 'call',
    'note' => 'TEST_MCC_route_' . bin2hex(random_bytes(3)), csrf_token() => csrf_hash()]);
        self::assertSame(302, $response->response()->getStatusCode());
        self::assertStringContainsString('/crm/leads/' . $this->lead, (string) $response->response()->getHeaderLine('Location'));
    }
    public function testFollowUpListUsesSharedNavigationAndAuthorizedBranchScope(): void
    {
        $page = $this->withSession(session()->get())->get('crm/follow-ups');
        self::assertSame(200, $page->response()->getStatusCode());
        self::assertStringContainsString('TEST_MCC_FOLLOWUP', $page->getBody());
        self::assertStringContainsString('/crm/leads/' . $this->lead, $page->getBody());
        self::assertStringContainsString('href="' . site_url('crm/follow-ups') . '"', $page->getBody());
        self::assertStringContainsString('mcc-nav-link is-active', $page->getBody());
        session()->set('permissions', ['lead.update']);
        \Config\Services::resetSingle('authorization');
        $unauthorized = $this->withSession(session()->get())->get('crm/follow-ups');
        self::assertSame(403, $unauthorized->response()->getStatusCode());
        session()->set(['permissions' => ['lead.view'], 'authorizedBranches' => [(string) $this->db->table('branches')->where('code',
    'TEST_MCC_DEL')->get()->getRow('id')]]);
        \Config\Services::resetSingle('authorization');
        $otherBranch = $this->withSession(session()->get())->get('crm/follow-ups');
        self::assertSame(200, $otherBranch->response()->getStatusCode());
        self::assertStringNotContainsString('TEST_MCC_FOLLOWUP', $otherBranch->getBody());
    }
    public function testFollowUpFiltersAreServerSideAndPaginationPreservesQuery(): void
    {
        $service = service('crm', false);
        $filterMarker = 'TEST_MCC_FOLLOWUP_FILTER_' . bin2hex(random_bytes(4));
        $scheduled = $service->createFollowUp($this->lead, ['follow_up_type' => 'call', 'note' => $filterMarker . '_pending',
    'follow_up_at' => '2020-01-02 03:04:05']);
        $contacted = $service->createFollowUp($this->lead, ['follow_up_type' => 'other', 'note' => $filterMarker . '_completed']);
        $service->completeFollowUp($contacted, 'contacted');
        $byStatus = $service->paginatedFollowUps(['status' => 'pending'], 1, 100);
        self::assertNotEmpty($byStatus['followUps']);
        self::assertTrue(array_reduce($byStatus['followUps'], static fn(bool $ok, array $row): bool => $ok && $row['status'] === 'pending', true));
        $byType = $service->paginatedFollowUps(['follow_up_type' => 'call'], 1, 100);
        self::assertNotEmpty($byType['followUps']);
        self::assertTrue(array_reduce($byType['followUps'], static fn(bool $ok, array $row): bool => $ok && $row['follow_up_type'] === 'call', true));
        $byOutcome = $service->paginatedFollowUps(['outcome' => 'contacted'], 1, 100);
        self::assertNotEmpty($byOutcome['followUps']);
        self::assertTrue(array_reduce($byOutcome['followUps'], static fn(bool $ok, array $row): bool => $ok && $row['outcome'] === 'contacted', true));
        $byDate = $service->paginatedFollowUps(['scheduled_from' => '2020-01-02', 'scheduled_to' => '2020-01-02'], 1, 100);
        self::assertNotEmpty($byDate['followUps']);
        self::assertTrue(array_reduce($byDate['followUps'], static fn(bool $ok, array $row): bool => $ok && str_starts_with((string) $row['follow_up_at'],
    '2020-01-02'), true));
        $combined = $service->paginatedFollowUps(['status' => 'pending', 'follow_up_type' => 'call', 'lead' => 'TEST_MCC_FOLLOWUP',
    'branch_id' => $this->branch], 1, 100);
        self::assertNotEmpty($combined['followUps']);
        self::assertTrue(array_reduce($combined['followUps'], static fn(bool $ok,
    array $row): bool => $ok && $row['status'] === 'pending' && $row['follow_up_type'] === 'call' && $row['lead_id'] !== null, true));
        self::assertSame(0, $service->paginatedFollowUps(['follow_up_type' => 'not-approved'], 1, 100)['total']);
        self::assertSame(0, $service->paginatedFollowUps(['scheduled_from' => 'bad-date'], 1, 100)['total']);
        self::assertSame(0, $service->paginatedFollowUps(['scheduled_from' => '2025-01-02', 'scheduled_to' => '2020-01-02'], 1, 100)['total']);
        self::assertSame(0, $service->paginatedFollowUps(['lead' => 'TEST_MCC_NO_SUCH_LEAD'], 1, 100)['total']);
        $paged = $service->paginatedFollowUps(['status' => 'pending'], 2, 2);
        self::assertSame(['status' => 'pending'], $paged['filters']);
        self::assertLessThanOrEqual(2, count($paged['followUps']));
        self::assertGreaterThan(1, $paged['pages']);
    }
    public function testCompletionRequiresAndPersistsEveryApprovedOutcome(): void
    {
        foreach (['no_answer', 'contacted', 'interested', 'callback_requested', 'not_interested', 'invalid_contact'] as $outcome) {
            $id = service('crm', false)->createFollowUp($this->lead, ['follow_up_type' => 'other', 'note' => 'TEST_MCC_outcome_' . $outcome]);
            service('crm', false)->completeFollowUp($id, $outcome);
            $row = $this->db->table('lead_follow_ups')->where('id', $id)->get()->getRowArray();
            self::assertSame('completed', $row['status']);
            self::assertSame($outcome, $row['outcome']);
            self::assertNotNull($row['completed_at']);
            self::assertSame(1, $this->db->table('audit_logs')->where(['action' => 'lead_follow_up_completed',
    'entity_id' => (string) $id])->countAllResults());
        }
        $id = service('crm', false)->createFollowUp($this->lead, ['follow_up_type' => 'call', 'note' => 'TEST_MCC_missing_outcome']);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid follow-up outcome.');
        service('crm', false)->completeFollowUp($id, '');
    }
    public function testCancellationRequiresReasonAndCompletedCancelledRowsCannotBeUpdated(): void
    {
        $cancelled = service('crm', false)->createFollowUp($this->lead, ['follow_up_type' => 'meeting', 'note' => 'TEST_MCC_cancel']);
        try {
            service('crm', false)->cancelFollowUp($cancelled, '');
            self::fail('Cancellation without a reason was accepted.');
        } catch (RuntimeException $exception) {
            self::assertSame('Cancellation reason required.', $exception->getMessage());
        }
        service('crm', false)->cancelFollowUp($cancelled, 'TEST_MCC rescheduled by lead');
        $row = $this->db->table('lead_follow_ups')->where('id', $cancelled)->get()->getRowArray();
        self::assertSame('cancelled', $row['status']);
        try {
            service('crm', false)->updateFollowUp($cancelled, ['follow_up_type' => 'call', 'note' => 'TEST_MCC_mutate_cancelled']);
            self::fail('Cancelled Follow-Up was updated.');
        } catch (RuntimeException $exception) {
            self::assertSame('Completed or cancelled follow-ups are immutable.', $exception->getMessage());
        }
        $completed = service('crm', false)->createFollowUp($this->lead, ['follow_up_type' => 'call', 'note' => 'TEST_MCC_immutable_completed']);
        service('crm', false)->completeFollowUp($completed, 'contacted');
        try {
            service('crm', false)->updateFollowUp($completed, ['follow_up_type' => 'email', 'note' => 'TEST_MCC_mutate_completed']);
            self::fail('Completed Follow-Up was updated.');
        } catch (RuntimeException $exception) {
            self::assertSame('Completed or cancelled follow-ups are immutable.', $exception->getMessage());
        }
        service('crm', false)->correctFollowUp($completed, ['follow_up_type' => 'email', 'note' => 'TEST_MCC_corrected',
    'correction_reason' => 'TEST_MCC correction']);
        self::assertSame('TEST_MCC_corrected', $this->db->table('lead_follow_ups')->where('id', $completed)->get()->getRow('note'));
        self::assertSame(1, $this->db->table('audit_logs')->where(['action' => 'lead_follow_up_corrected',
    'entity_id' => (string) $completed])->countAllResults());
    }
    public function testBranchAndPermissionChecksAreServerSide(): void
    {
        $id = service('crm', false)->createFollowUp($this->lead, ['follow_up_type' => 'call', 'note' => 'TEST_MCC_security']);
        session()->set('permissions', ['lead.view']);
        \Config\Services::resetSingle('authorization');
        try {
            service('crm', false)->updateFollowUp($id, ['follow_up_type' => 'call', 'note' => 'TEST_MCC_denied']);
            self::fail('Update without permission was accepted.');
        } catch (RuntimeException $exception) {
            self::assertSame('Forbidden', $exception->getMessage());
        }
        $otherBranch = (int) $this->db->table('branches')->where('code', 'TEST_MCC_DEL')->get()->getRow('id');
        session()->set(['permissions' => ['lead.update'], 'authorizedBranches' => [(string) $otherBranch]]);
        \Config\Services::resetSingle('authorization');
        try {
            service('crm', false)->updateFollowUp($id, ['follow_up_type' => 'call', 'note' => 'TEST_MCC_cross_branch']);
            self::fail('Cross-branch update was accepted.');
        } catch (RuntimeException $exception) {
            self::assertSame('Forbidden', $exception->getMessage());
        }
    }
    public function testPhysicalDeletionIsNotExposed(): void
    {
        self::assertFalse(method_exists(service('crm', false), 'deleteFollowUp'));
        self::assertFalse(method_exists(service('crm', false), 'delete'));
    }
    private function asAuthorizedUser(): void
    {
        session()->set(['isLoggedIn' => true, 'accountEnabled' => true, 'user' => ['id' => $this->actor], 'session_version' => 1,
    'authorizedBranches' => [(string) $this->branch], 'permissions' => ['lead.view', 'lead.update']]);
        \Config\Services::resetSingle('authorization');
    }
}
