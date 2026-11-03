<?php

declare (strict_types=1);
namespace Tests\Database;

use App\Services\AdmissionService;
use App\Services\PlayerService;
use App\Services\SchedulingService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use RuntimeException;
/**
 * Non-destructive Player write coverage.
 *
 * Every fixture is a new TEST_MCC_PLAYER_WRITE_* record. This suite never
 * clears tables, replaces seeded rows, or deletes test data.
 */
final class PlayerWriteWorkflowSafeTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    protected $DBGroup = 'tests';
    protected $migrate = false;
    private int $actor;
    private int $vns;
    private int $del;
    private string $marker;
    protected function setUp(): void
    {
        parent::setUp();
        self::assertSame('mcc_cricket_club_test', strtolower((string) $this->db->getDatabase()));
        $this->actor = (int) $this->db->table('users')->where('email', 'test.mcc.superadmin@example.invalid')->get()->getRow('id');
        $this->vns = (int) $this->db->table('branches')->where('code', 'TEST_MCC_VNS')->get()->getRow('id');
        $this->del = (int) $this->db->table('branches')->where('code', 'TEST_MCC_DEL')->get()->getRow('id');
        self::assertGreaterThan(0, $this->actor);
        self::assertGreaterThan(0, $this->vns);
        self::assertGreaterThan(0, $this->del);
        $this->marker = 'TEST_MCC_PLAYER_WRITE_' . bin2hex(random_bytes(6));
        $this->authorize(['admission.create', 'admission.update', 'player.convert', 'player.view', 'player.update', 'player.batch.assign', 'session.create',
    'player.coach.assign', 'rbac.manage'], [$this->vns, $this->del]);
    }
    protected function tearDown(): void
    {
        session()->destroy();
        parent::tearDown();
    }

    public function testPlayerListCountsAndBranchIsolationAreReadOnly(): void
    {
        $result = $this->players()->list(['page' => 1]);

        self::assertGreaterThanOrEqual(1, $result['status_counts']['active']);
        self::assertSame(0, $result['status_counts']['suspended']);
        self::assertNotEmpty($result['players']);
        self::assertSame($this->vns, (int) $result['players'][0]['branch_id']);

        $unauthorized = $this->players()->list(['branch_id' => 999999, 'page' => 1]);

        self::assertSame([], $unauthorized['players']);
        self::assertSame(0, $unauthorized['total']);
        self::assertSame(0, $unauthorized['status_counts']['active']);
    }

    public function testPlayerListStatusFilterAndZeroStatePreserveFilterSemantics(): void
    {
        $active = $this->players()->list(['status' => 'active', 'page' => 1]);
        $empty = $this->players()->list(['status' => 'suspended', 'page' => 1]);

        self::assertNotEmpty($active['players']);
        self::assertSame(0, $empty['total']);
        self::assertSame([], $empty['players']);
        self::assertSame(1, $empty['page']);
        self::assertSame(1, $empty['pages']);
        self::assertGreaterThanOrEqual(1, $empty['status_counts']['active']);
    }
    public function testLeadAndDirectConversionAreLineageSafeAndIdempotent(): void
    {
        $leadEmail = $this->marker . '_LEAD@example.invalid';
        $now = date('Y-m-d H:i:s');
        $this->db->table('leads')->insert(['branch_id' => $this->vns, 'owner_user_id' => $this->actor, 'source' => 'TEST_MCC_SEED',
    'full_name' => $this->marker . '_LEAD', 'email' => $leadEmail, 'phone' => '999' . random_int(1000000, 9999999), 'date_of_birth' => '2012-05-10',
    'status' => 'lead', 'created_at' => $now, 'updated_at' => $now]);
        $lead = (int) $this->db->insertID();
        $admission = $this->approve($this->admission()->createFromLead($lead));
        $player = $this->admission()->convert($admission);
        self::assertSame($player, $this->admission()->convert($admission));
        self::assertSame(1, $this->db->table('players')->where('admission_id', $admission)->countAllResults());
        $lineage = $this->db->table('players')->where('id', $player)->get()->getRowArray();
        self::assertSame($admission, (int) $lineage['admission_id']);
        self::assertSame($lead, (int) $lineage['lead_id']);
        self::assertSame('CONVERTED', $this->db->table('admissions')->where('id', $admission)->get()->getRow('status'));
        self::assertSame(1, $this->db->table('audit_logs')->where(['action' => 'admission_converted', 'entity_id' => (string) $admission])->countAllResults());
        $direct = $this->approve($this->admission()->createDirect(['branch_id' => $this->vns, 'applicant_full_name' => $this->marker . '_DIRECT',
    'applicant_email' => $this->marker . '_DIRECT@example.invalid', 'applicant_phone' => '9888888888', 'applicant_date_of_birth' => '2013-04-15']));
        $directPlayer = $this->admission()->convert($direct);
        $directRow = $this->db->table('players')->where('id', $directPlayer)->get()->getRowArray();
        self::assertNull($directRow['lead_id']);
        self::assertSame(0, $this->db->table('leads')->where('full_name', $this->marker . '_DIRECT')->countAllResults());
    }
    public function testAdmissionConversionTransfersContactsIdempotentlyAndPreservesManualContacts(): void
    {
        $admission = $this->admission()->createDirect([
            'branch_id' => $this->vns,
            'applicant_full_name' => $this->marker . '_CONTACT_PLAYER',
            'applicant_email' => $this->marker . '_CONTACT@example.invalid',
            'applicant_phone' => '9333333333',
            'applicant_date_of_birth' => '2012-06-01',
            'father_name' => $this->marker . '_FATHER',
            'mother_name' => $this->marker . '_MOTHER',
            'parent_mobile' => '9222222222',
            'parent_email' => $this->marker . '_PARENT@example.invalid',
            'emergency_contact' => $this->marker . '_EMERGENCY',
        ]);
        $player = $this->admission()->convert($this->approve($admission));
        $this->players()->addContact($player, [
            'relationship_type' => 'guardian',
            'full_name' => $this->marker . '_MANUAL_GUARDIAN',
        ]);

        self::assertSame(4, $this->db->table('player_contacts')->where('player_id', $player)->countAllResults());
        $contacts = $this->db->table('player_contacts pc')
            ->select('pc.contact_person_id, pc.relationship_type, pe.full_name, pe.email, pe.phone')
            ->join('persons pe', 'pe.id = pc.contact_person_id')
            ->where('pc.player_id', $player)
            ->orderBy('pc.contact_person_id', 'ASC')
            ->get()
            ->getResultArray();
        self::assertSame('guardian', $contacts[0]['relationship_type']);
        self::assertSame($this->marker . '_FATHER', $contacts[0]['full_name']);
        self::assertSame($this->marker . '_PARENT@example.invalid', $contacts[0]['email']);
        self::assertSame('9222222222', $contacts[0]['phone']);
        self::assertSame('guardian', $contacts[1]['relationship_type']);
        self::assertNull($contacts[1]['email']);
        self::assertNull($contacts[1]['phone']);
        self::assertSame('emergency-contact', $contacts[2]['relationship_type']);

        $contactIdsBeforeRetry = array_column($contacts, 'contact_person_id');
        self::assertSame($player, $this->admission()->convert($admission));
        self::assertSame(4, $this->db->table('player_contacts')->where('player_id', $player)->countAllResults());
        $contactsAfterRetry = $this->db->table('player_contacts pc')
            ->select('pc.contact_person_id, pc.relationship_type, pe.full_name, pe.email, pe.phone')
            ->join('persons pe', 'pe.id = pc.contact_person_id')
            ->where('pc.player_id', $player)
            ->orderBy('pc.contact_person_id', 'ASC')
            ->get()
            ->getResultArray();
        self::assertSame($contactIdsBeforeRetry, array_column($contactsAfterRetry, 'contact_person_id'));
        self::assertSame($this->marker . '_PARENT@example.invalid', $contactsAfterRetry[1]['email']);
        self::assertSame('9222222222', $contactsAfterRetry[1]['phone']);
        self::assertSame(3, $this->db->table('audit_logs')->where([
            'action' => 'player_contact_linked_from_admission',
            'entity_id' => (string) $player,
        ])->countAllResults());
        $audit = $this->db->table('audit_logs')->where([
            'action' => 'player_contact_linked_from_admission',
            'entity_id' => (string) $player,
        ])->orderBy('id', 'ASC')->get()->getRowArray();
        self::assertStringContainsString('source_admission_id', (string) $audit['after_summary']);
        self::assertSame(1, $this->db->table('player_contacts pc')
            ->join('persons pe', 'pe.id = pc.contact_person_id')
            ->where(['pc.player_id' => $player, 'pc.relationship_type' => 'guardian', 'pe.full_name' => $this->marker . '_MANUAL_GUARDIAN'])
            ->countAllResults());
    }
    public function testConversionRejectsUnauthorizedAndCrossBranchAdmissions(): void
    {
        $admission = $this->approve($this->admission()->createDirect(['branch_id' => $this->del, 'applicant_full_name' => $this->marker . '_DEL',
    'applicant_email' => $this->marker . '_DEL@example.invalid', 'applicant_phone' => '9777777777', 'applicant_date_of_birth' => '2014-01-01']));
        $this->authorize([], [$this->vns]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Forbidden');
        $this->admission()->convert($admission);
        self::assertSame(0, $this->db->table('players')->where('admission_id', $admission)->countAllResults());
    }
    public function testLifecycleRequiresAuthorizationReasonAndPreservesHistory(): void
    {
        $player = $this->convertedPlayer($this->vns);
        $before = $this->db->table('players')->where('id', $player)->get()->getRowArray();
        $this->players()->changeStatus($player, 'suspended', 'TEST_MCC_PLAYER_WRITE suspension', (string) $before['updated_at'],
    (int) $before['profile_revision']);
        $history = $this->db->table('player_status_history')->where(['player_id' => $player, 'new_status' => 'suspended'])->get()->getRowArray();
        self::assertSame($this->actor, (int) $history['actor_user_id']);
        self::assertNotEmpty($history['created_at']);
        self::assertSame(1, $this->db->table('audit_logs')->where(['action' => 'player_status_changed', 'entity_id' => (string) $player])->countAllResults());
        $current = $this->db->table('players')->where('id', $player)->get()->getRowArray();
        try {
            $this->players()->changeStatus($player, 'suspended', 'duplicate', (string) $current['updated_at'], (int) $current['profile_revision']);
            self::fail('Same-status transition accepted.');
        } catch (RuntimeException $e) {
            self::assertSame('Invalid transition.', $e->getMessage());
        }
        $this->authorize(['player.update', 'rbac.manage'], [$this->del]);
        try {
            $this->players()->changeStatus($player, 'inactive', 'cross branch', (string) $current['updated_at'], (int) $current['profile_revision']);
            self::fail('Cross-branch lifecycle transition accepted.');
        } catch (RuntimeException $e) {
            self::assertSame('Forbidden', $e->getMessage());
        }
    }
    public function testAuthorizedSameBranchStatusChangeUsesPlayerUpdatePermission(): void
    {
        $player = $this->convertedPlayer($this->vns);
        $before = $this->db->table('players')->where('id', $player)->get()->getRowArray();
        $this->authorize(['player.update'], [$this->vns]);

        $this->players()->changeStatus(
            $player,
            'inactive',
            'TEST_MCC_PLAYER_WRITE authorized status update',
            (string) $before['updated_at'],
            (int) $before['profile_revision'],
        );

        self::assertSame(
            'inactive',
            $this->db->table('players')->where('id', $player)->get()->getRow('status'),
        );
    }
    public function testUnauthorizedStatusChangeIsDenied(): void
    {
        $player = $this->convertedPlayer($this->vns);
        $before = $this->db->table('players')->where('id', $player)->get()->getRowArray();
        $this->authorize([], [$this->vns]);

        try {
            $this->players()->changeStatus(
                $player,
                'inactive',
                'TEST_MCC_PLAYER_WRITE unauthorized status update',
                (string) $before['updated_at'],
                (int) $before['profile_revision'],
            );
            self::fail('Unauthorized status change accepted.');
        } catch (RuntimeException $exception) {
            self::assertSame('Forbidden', $exception->getMessage());
        }
    }
    public function testCrossBranchStatusChangeIsDenied(): void
    {
        $player = $this->convertedPlayer($this->vns);
        $before = $this->db->table('players')->where('id', $player)->get()->getRowArray();
        $this->authorize(['player.update'], [$this->del]);

        try {
            $this->players()->changeStatus(
                $player,
                'inactive',
                'TEST_MCC_PLAYER_WRITE cross branch status update',
                (string) $before['updated_at'],
                (int) $before['profile_revision'],
            );
            self::fail('Cross-branch status change accepted.');
        } catch (RuntimeException $exception) {
            self::assertSame('Forbidden', $exception->getMessage());
        }
    }
    public function testPlayerUpdateWithoutRbacManageCanChangeSameBranchStatus(): void
    {
        $player = $this->convertedPlayer($this->vns);
        $before = $this->db->table('players')->where('id', $player)->get()->getRowArray();
        $this->authorize(['player.update'], [$this->vns]);

        $this->players()->changeStatus(
            $player,
            'suspended',
            'TEST_MCC_PLAYER_WRITE status update without rbac manage',
            (string) $before['updated_at'],
            (int) $before['profile_revision'],
        );

        $history = $this->db->table('player_status_history')
            ->where(['player_id' => $player, 'new_status' => 'suspended'])
            ->get()
            ->getRowArray();

        self::assertSame($this->actor, (int) $history['actor_user_id']);
        self::assertSame(
            1,
            $this->db->table('audit_logs')
                ->where(['action' => 'player_status_changed', 'entity_id' => (string) $player])
                ->countAllResults(),
        );
    }
    public function testProfileAndContactsEnforceOwnershipValidationAndPrimaryType(): void
    {
        $player = $this->convertedPlayer($this->vns);
        $before = $this->db->table('players')->where('id', $player)->get()->getRowArray();
        try {
            $this->players()->updateProfile($player, ['branch_id' => $this->del], (string) $before['updated_at'], (int) $before['profile_revision']);
            self::fail('Submitted branch escalation accepted.');
        } catch (RuntimeException $e) {
            self::assertSame('Invalid player profile.', $e->getMessage());
        }
        $this->players()->updateProfile($player, ['full_name' => $this->marker . '_UPDATED', 'phone' => '9666666666'], (string) $before['updated_at'],
    (int) $before['profile_revision']);
        $person = $this->db->table('persons')->where('id', $before['person_id'])->get()->getRowArray();
        self::assertSame($this->marker . '_UPDATED', $person['full_name']);
        self::assertSame($this->vns, (int) $this->db->table('players')->where('id', $player)->get()->getRow('branch_id'));
        $this->players()->addContact($player, ['full_name' => $this->marker . '_GUARDIAN', 'relationship_type' => 'guardian',
    'email' => $this->marker . '_G@example.invalid']);
        $this->players()->addContact($player, ['full_name' => $this->marker . '_PRIMARY', 'relationship_type' => 'primary-contact', 'phone' => '9555555555']);
        $contacts = $this->db->table('player_contacts')->where('player_id', $player)->get()->getResultArray();
        self::assertCount(2, $contacts);
        self::assertSame(1, (int) array_values(array_filter($contacts,
    static fn(array $row): bool => $row['relationship_type'] === 'primary-contact'))[0]['is_primary']);
        try {
            $this->players()->addContact($player, ['full_name' => '', 'relationship_type' => 'guardian']);
            self::fail('Invalid contact accepted.');
        } catch (RuntimeException $e) {
            self::assertSame('Invalid contact.', $e->getMessage());
        }
        $this->authorize([], [$this->vns]);
        try {
            $this->players()->addContact($player, ['full_name' => 'Blocked', 'relationship_type' => 'guardian']);
            self::fail('Unauthorized contact accepted.');
        } catch (RuntimeException $e) {
            self::assertSame('Forbidden', $e->getMessage());
        }
    }
    public function testBatchAndCoachAssignmentsUseGlobalBatchMasterAndCoachPermissions(): void
    {
        $player = $this->convertedPlayer($this->vns);
        $vnsBatch = $this->ensureBatch($this->vns, 'TEST_MCC_VNS_U15');
        $delBatch = $this->ensureBatch($this->del, 'TEST_MCC_DEL_U13');
        $coach = (int) $this->db->table('users')->where('email', 'test.mcc.coach@example.invalid')->get()->getRow('id');
        $before = $this->db->table('players')->where('id', $player)->get()->getRowArray();
        $this->players()->assignBatch($player, $vnsBatch, '2030-01-01 09:00:00', null, (string) $before['updated_at'], (int) $before['profile_revision']);
        $after = $this->db->table('players')->where('id', $player)->get()->getRowArray();
        $this->players()->assignCoach($player, $coach, '2030-02-01 09:00:00', null, (string) $after['updated_at'], (int) $after['profile_revision']);
        self::assertSame(1, $this->db->table('player_batch_assignments')->where(['player_id' => $player, 'batch_id' => $vnsBatch])->countAllResults());
        self::assertSame(1, $this->db->table('player_coach_assignments')->where(['player_id' => $player, 'coach_user_id' => $coach])->countAllResults());
        $current = $this->db->table('players')->where('id', $player)->get()->getRowArray();
        $this->players()->assignBatch($player, $delBatch, '2030-03-01 09:00:00', null, (string) $current['updated_at'], (int) $current['profile_revision']);
        self::assertSame(1, $this->db->table('player_batch_assignments')->where(['player_id' => $player, 'batch_id' => $delBatch])->countAllResults());
    }

    public function testGlobalBatchAssignmentIsIndependentOfProgramAndProjectsAfterReload(): void
    {
        $player = $this->convertedPlayer($this->vns);
        $batchName = $this->marker . '_GLOBAL_BATCH';
        $now = date('Y-m-d H:i:s');
        $this->db->table('batches')->insert([
            'branch_id' => null,
            'name' => $batchName,
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $globalBatch = (int) $this->db->insertID();

        $options = $this->players()->assignmentOptions($this->vns);
        $optionIds = array_map(static fn (array $batch): int => (int) $batch['id'], $options['batches']);
        self::assertContains($globalBatch, $optionIds);

        $before = $this->db->table('players')->where('id', $player)->get()->getRowArray();
        $this->players()->assignBatch(
            $player,
            $globalBatch,
            '2031-01-01 09:00:00',
            null,
            (string) $before['updated_at'],
            (int) $before['profile_revision'],
        );

        $this->db->table('batches')->where('id', $globalBatch)->update(['is_active' => 0]);
        $after = $this->db->table('players')->where('id', $player)->get()->getRowArray();
        $historicalOptions = $this->players()->assignmentOptions($this->vns, $globalBatch);
        $historical = array_values(array_filter(
            $historicalOptions['batches'],
            static fn (array $batch): bool => (int) ($batch['id'] ?? 0) === $globalBatch,
        ))[0] ?? null;

        self::assertIsArray($historical);
        self::assertTrue((bool) ($historical['disabled'] ?? false));
        self::assertTrue((bool) ($historical['historical'] ?? false));

        $list = $this->players()->list(['q' => $this->marker . '_PLAYER_' . $this->vns]);
        $listed = array_values(array_filter(
            $list['players'],
            static fn (array $row): bool => (int) ($row['id'] ?? 0) === $player,
        ))[0] ?? null;
        self::assertSame($batchName, $listed['batch_name']);

        $detail = $this->players()->find($player);
        self::assertSame($batchName, $detail['current_batch']['batch_name']);
        self::assertSame($before['status'], $after['status']);
        self::assertSame($before['admission_id'], $after['admission_id']);
    }

    public function testDedicatedPlayerCoachCorrectionUpdatesBothAssignmentsAndAudits(): void
    {
        $player = $this->convertedPlayer($this->vns);
        $beforePlayer = $this->db->table('players')->where('id', $player)->get()->getRowArray();
        $beforeAdmission = $this->db->table('admissions')->where('id', (int) $beforePlayer['admission_id'])->get()->getRowArray();
        $groupCoach = (int) $this->db->table('users')->where('email', 'test.mcc.coach@example.invalid')->get()->getRow('id');
        $personalCoach = (int) $this->db->table('users u')
            ->select('u.id')
            ->join('user_roles ur', 'ur.user_id = u.id')
            ->join('roles r', 'r.id = ur.role_id')
            ->join('user_branches ub', 'ub.user_id = u.id')
            ->where('ub.branch_id', $this->vns)
            ->where('r.name', 'Coach')
            ->where('u.is_enabled', 1)
            ->where('u.id !=', $groupCoach)
            ->get()
            ->getRow('id');
        self::assertGreaterThan(0, $groupCoach);
        self::assertGreaterThan(0, $personalCoach);
        $this->ensureCoachProfile($groupCoach);
        $this->ensureCoachProfile($personalCoach);

        $this->players()->updateCoachAssignments($player, [
            'group_coach_id' => $groupCoach,
            'personal_coach_id' => $personalCoach,
            'correction_reason' => 'Player coach correction test.',
            'expected_updated_at' => $beforePlayer['updated_at'],
            'expected_profile_revision' => $beforePlayer['profile_revision'],
        ]);

        $afterPlayer = $this->db->table('players')->where('id', $player)->get()->getRowArray();
        $afterAdmission = $this->db->table('admissions')->where('id', (int) $beforePlayer['admission_id'])->get()->getRowArray();
        self::assertSame($beforePlayer['branch_id'], $afterPlayer['branch_id']);
        self::assertSame($beforePlayer['status'], $afterPlayer['status']);
        self::assertSame($beforePlayer['admission_id'], $afterPlayer['admission_id']);
        self::assertSame($beforeAdmission['batch_id'], $afterAdmission['batch_id']);
        self::assertSame($groupCoach, (int) $afterAdmission['coach_user_id']);
        self::assertSame($personalCoach, (int) $afterAdmission['personal_coach_user_id']);
        self::assertSame(1, $this->db->table('audit_logs')->where(['action' => 'player_coach_assignments_changed', 'entity_id' => (string) $player, 'reason' => 'Player coach correction test.'])->countAllResults());

        $list = $this->players()->list(['q' => $this->marker . '_PLAYER_' . $this->vns]);
        $listed = array_values(array_filter(
            $list['players'],
            static fn (array $row): bool => (int) ($row['id'] ?? 0) === $player,
        ))[0] ?? null;
        self::assertIsArray($listed);
        self::assertSame($this->coachName($groupCoach), $listed['group_coach_name']);
        self::assertSame($this->coachName($personalCoach), $listed['personal_coach_name']);

        $detail = $this->players()->find($player);
        self::assertSame($listed['group_coach_name'], $detail['group_coach_name']);
        self::assertSame($listed['personal_coach_name'], $detail['personal_coach_name']);
    }

    public function testDedicatedPlayerCoachCorrectionAllowsSameCoachAndRejectsStaleVersion(): void
    {
        $player = $this->convertedPlayer($this->vns);
        $before = $this->db->table('players')->where('id', $player)->get()->getRowArray();
        $coach = (int) $this->db->table('users')->where('email', 'test.mcc.coach@example.invalid')->get()->getRow('id');

        $this->players()->updateCoachAssignments($player, [
            'group_coach_id' => $coach,
            'personal_coach_id' => $coach,
            'correction_reason' => 'Same authorized Coach is valid for both assignments.',
            'expected_updated_at' => $before['updated_at'],
            'expected_profile_revision' => $before['profile_revision'],
        ]);

        $after = $this->db->table('players')->where('id', $player)->get()->getRowArray();
        $admission = $this->db->table('admissions')->where('id', (int) $after['admission_id'])->get()->getRowArray();
        self::assertSame($coach, (int) $admission['coach_user_id']);
        self::assertSame($coach, (int) $admission['personal_coach_user_id']);
        $audit = $this->db->table('audit_logs')
            ->where(['action' => 'player_coach_assignments_changed', 'entity_id' => (string) $player])
            ->orderBy('id', 'DESC')
            ->get()
            ->getRowArray();
        self::assertIsArray($audit);
        self::assertSame($coach, (int) json_decode((string) $audit['after_summary'], true)['group_coach_id']);
        self::assertSame($coach, (int) json_decode((string) $audit['after_summary'], true)['personal_coach_id']);

        $this->expectExceptionMessage('Update conflict.');
        $this->players()->updateCoachAssignments($player, [
            'group_coach_id' => $coach,
            'personal_coach_id' => null,
            'correction_reason' => 'Stale coach update.',
            'expected_updated_at' => $before['updated_at'],
            'expected_profile_revision' => $before['profile_revision'],
        ]);
    }

    public function testSchedulingPlayerOptionsUseCurrentAdmissionCoachAssignmentsWithoutBatchMatching(): void
    {
        $player = $this->convertedPlayer($this->vns);
        $before = $this->db->table('players')->where('id', $player)->get()->getRowArray();
        $coach = (int) $this->db->table('users')->where('email', 'test.mcc.coach@example.invalid')->get()->getRow('id');

        $this->players()->updateCoachAssignments($player, [
            'group_coach_id' => $coach,
            'personal_coach_id' => $coach,
            'correction_reason' => 'Scheduling visibility test.',
            'expected_updated_at' => $before['updated_at'],
            'expected_profile_revision' => $before['profile_revision'],
        ]);

        $options = $this->scheduling()->sessionFormOptions('session.create', $this->vns);
        $rows = array_values(array_filter(
            $options['players'],
            static fn (array $row): bool => (int) ($row['id'] ?? 0) === $player,
        ));
        self::assertCount(1, $rows);
        self::assertSame((string) $coach, (string) $rows[0]['coach_user_ids']);

        $otherCoach = (int) $this->db->table('users u')
            ->select('u.id')
            ->join('user_branches ub', 'ub.user_id = u.id')
            ->join('user_roles ur', 'ur.user_id = u.id')
            ->join('roles r', 'r.id = ur.role_id')
            ->where('ub.branch_id', $this->vns)
            ->where('u.id !=', $coach)
            ->where('u.is_enabled', 1)
            ->whereIn('r.name', ['Coach', 'Fitness Coach'])
            ->get()
            ->getRow('id');
        self::assertGreaterThan(0, $otherCoach);
        $this->ensureCoachProfile($otherCoach);

        $current = $this->db->table('players')->where('id', $player)->get()->getRowArray();
        $this->players()->updateCoachAssignments($player, [
            'group_coach_id' => $otherCoach,
            'personal_coach_id' => $otherCoach,
            'correction_reason' => 'Scheduling reassignment visibility test.',
            'expected_updated_at' => $current['updated_at'],
            'expected_profile_revision' => $current['profile_revision'],
        ]);

        $options = $this->scheduling()->sessionFormOptions('session.create', $this->vns);
        $rows = array_values(array_filter(
            $options['players'],
            static fn (array $row): bool => (int) ($row['id'] ?? 0) === $player,
        ));
        self::assertCount(1, $rows);
        self::assertSame((string) $otherCoach, (string) $rows[0]['coach_user_ids']);
    }
    private function convertedPlayer(int $branch): int
    {
        $id = $this->admission()->createDirect(['branch_id' => $branch, 'applicant_full_name' => $this->marker . '_PLAYER_' . $branch,
    'applicant_email' => $this->marker . '_' . $branch . '@example.invalid', 'applicant_phone' => '9444444444', 'applicant_date_of_birth' => '2012-06-01']);
        return $this->admission()->convert($this->approve($id));
    }
    private function approve(int $admission): int
    {
        $this->admission()->updateChecklist($admission, ['checklist_identity_complete' => true, 'checklist_contact_complete' => true,
    'checklist_admission_complete' => true, 'checklist_branch_confirmed' => true]);
        $this->admission()->transition($admission, 'APPROVED');
        return $admission;
    }
    private function admission(): AdmissionService
    {
        return service('admission', false);
    }
    private function players(): PlayerService
    {
        return service('player', false);
    }

    private function scheduling(): SchedulingService
    {
        return service('scheduling', false);
    }
    private function ensureBatch(int $branch, string $name): int
    {
        $existing = $this->db->table('batches')
            ->where(['branch_id' => $branch, 'name' => $name])
            ->get()
            ->getRow('id');

        if ($existing !== null) {
            return (int) $existing;
        }

        $now = date('Y-m-d H:i:s');
        $this->db->table('batches')->insert([
            'branch_id' => $branch,
            'name' => $name,
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) $this->db->insertID();
    }

    private function ensureCoachProfile(int $userId): void
    {
        if ($this->db->table('coach_profiles')->where('user_id', $userId)->countAllResults() > 0) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $this->db->table('coach_profiles')->insert([
            'user_id' => $userId,
            'specialization' => 'Test coaching profile',
            'experience_years' => null,
            'qualification' => null,
            'certifications' => null,
            'availability' => null,
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function coachName(int $userId): string
    {
        return (string) $this->db->table('users')
            ->where('id', $userId)
            ->get()
            ->getRow('display_name');
    }

    private function authorize(array $permissions, array $branches): void
    {
        session()->set(['isLoggedIn' => true, 'accountEnabled' => true, 'user' => ['id' => $this->actor], 'session_version' => 1,
    'permissions' => $permissions, 'authorizedBranches' => array_map('strval', $branches)]);
        \Config\Services::resetSingle('authorization');
    }
}
