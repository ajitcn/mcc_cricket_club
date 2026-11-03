<?php

declare(strict_types=1);

namespace Tests\Database;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use RuntimeException;

/**
 * Insert-only evidence for the complete Lead to Player conversion flow.
 *
 * This class creates uniquely marked fixtures and never deletes, truncates, or
 * rewrites existing test data.
 */
final class LeadTrialAdmissionPlayerFlowSafeTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $DBGroup = 'tests';
    protected $migrate = false;

    private int $actor;
    private int $branch;
    private int $otherBranch;
    private string $marker;

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
        $this->marker = 'TEST_MCC_FLOW_' . strtoupper(bin2hex(random_bytes(6)));

        self::assertGreaterThan(0, $this->actor);
        self::assertGreaterThan(0, $this->branch);
        self::assertGreaterThan(0, $this->otherBranch);

        $this->authorize([
            'lead.view',
            'lead.update',
            'trial.book',
            'admission.view',
            'admission.create',
            'admission.update',
            'player.convert',
        ], [$this->branch]);
    }

    protected function tearDown(): void
    {
        session()->destroy();
        \Config\Services::resetSingle('authorization');
        parent::tearDown();
    }

    public function testCompleteLeadTrialAdmissionConversionSequenceIsLineageSafe(): void
    {
        $lead = $this->createLead($this->branch, 'MAIN');
        $ground = $this->groundName($this->branch);
        $trial = service('trial')->book($lead, [
            'trial_date' => date('Y-m-d', strtotime('+2 days')),
            'trial_time' => '10:00',
            'location' => $ground,
            'remarks' => $this->marker . ' trial',
        ]);

        $booked = $this->db->table('trials')->where('id', $trial)->get()->getRowArray();
        self::assertSame('booked', $booked['status']);
        self::assertSame($lead, (int) $booked['lead_id']);
        self::assertSame($this->branch, (int) $booked['branch_id']);

        service('trial')->attendance($trial, 'attended');
        service('trial')->selection($trial, 'selected');
        service('trial')->admissionPending($trial);

        $admission = $this->db->table('admissions')
            ->where(['lead_id' => $lead, 'trial_id' => $trial])
            ->get()
            ->getRowArray();
        self::assertIsArray($admission);
        $admissionId = (int) $admission['id'];
        self::assertSame('INTAKE', $admission['status']);
        self::assertSame($this->branch, (int) $admission['branch_id']);

        service('admission')->updateChecklist($admissionId, [
            'checklist_identity_complete' => true,
            'checklist_contact_complete' => true,
            'checklist_admission_complete' => true,
            'checklist_branch_confirmed' => true,
        ]);
        service('admission')->transition($admissionId, 'APPROVED');
        self::assertSame(
            'APPROVED',
            $this->db->table('admissions')->where('id', $admissionId)->get()->getRow('status')
        );

        $player = service('admission')->convert($admissionId);
        $retryPlayer = service('admission')->convert($admissionId);

        self::assertSame($player, $retryPlayer);
        self::assertSame(
            1,
            $this->db->table('players')->where('admission_id', $admissionId)->countAllResults()
        );

        $lineage = $this->db->table('players')->where('id', $player)->get()->getRowArray();
        self::assertSame($lead, (int) $lineage['lead_id']);
        self::assertSame($admissionId, (int) $lineage['admission_id']);
        self::assertSame(
            'CONVERTED',
            $this->db->table('admissions')->where('id', $admissionId)->get()->getRow('status')
        );

        $contactsAfterFirstConversion = $this->db->table('player_contacts')
            ->where('player_id', $player)
            ->countAllResults();
        self::assertSame(
            $contactsAfterFirstConversion,
            $this->db->table('player_contacts')->where('player_id', $player)->countAllResults()
        );

        foreach ([
            'trial_booked',
            'trial_attendance_recorded',
            'trial_selection_recorded',
            'lead_transition_admission_pending',
            'admission_created_from_trial',
            'admission_status_changed',
            'admission_converted',
        ] as $action) {
            self::assertGreaterThanOrEqual(
                1,
                $this->db->table('audit_logs')->where([
                    'action' => $action,
                    'entity_id' => $action === 'lead_transition_admission_pending'
                        ? (string) $lead
                        : ($action === 'trial_booked' || $action === 'trial_attendance_recorded' || $action === 'trial_selection_recorded'
                            ? (string) $trial
                            : (string) $admissionId),
                ])->countAllResults()
            );
        }

        $this->assertInvalidTransition($trial, $admissionId);
    }

    public function testCrossBranchAndMissingPermissionRequestsAreRejected(): void
    {
        $crossBranchLead = $this->createLead($this->otherBranch, 'CROSS_BRANCH');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Forbidden');
        service('trial')->book($crossBranchLead, [
            'trial_date' => date('Y-m-d', strtotime('+3 days')),
            'trial_time' => '11:00',
            'location' => $this->groundName($this->otherBranch),
        ]);
    }

    public function testMissingTrialPermissionIsRejectedBeforePersistence(): void
    {
        $lead = $this->createLead($this->branch, 'MISSING_PERMISSION');
        $this->authorize(['lead.view'], [$this->branch]);

        try {
            service('trial')->book($lead, [
                'trial_date' => date('Y-m-d', strtotime('+4 days')),
                'trial_time' => '12:00',
                'location' => $this->groundName($this->branch),
            ]);
            self::fail('Trial booking without permission was accepted.');
        } catch (RuntimeException $exception) {
            self::assertSame('Forbidden', $exception->getMessage());
        }

        self::assertSame(0, $this->db->table('trials')->where('lead_id', $lead)->countAllResults());
    }

    public function testFailedConversionLeavesApprovedAdmissionAndPlayerWritesUnchanged(): void
    {
        $lead = $this->createLead($this->branch, 'ROLLBACK');
        $admission = service('admission')->createFromLead($lead);
        service('admission')->updateChecklist($admission, [
            'checklist_identity_complete' => true,
            'checklist_contact_complete' => true,
            'checklist_admission_complete' => true,
            'checklist_branch_confirmed' => true,
        ]);
        service('admission')->transition($admission, 'APPROVED');

        $now = date('Y-m-d H:i:s');
        $this->db->table('persons')->insert([
            'full_name' => $this->marker . ' conflicting person',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $person = (int) $this->db->insertID();
        $this->db->table('players')->insert([
            'person_id' => $person,
            'lead_id' => $lead,
            'admission_id' => null,
            'branch_id' => $this->branch,
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        try {
            service('admission')->convert($admission);
            self::fail('Conflicting lineage was accepted.');
        } catch (RuntimeException $exception) {
            self::assertSame('Conflicting player lineage.', $exception->getMessage());
        }

        self::assertSame('APPROVED', $this->db->table('admissions')->where('id', $admission)->get()->getRow('status'));
        self::assertNull($this->db->table('admissions')->where('id', $admission)->get()->getRow('converted_player_id'));
        self::assertSame(1, $this->db->table('players')->where('lead_id', $lead)->countAllResults());
    }

    private function createLead(int $branch, string $suffix): int
    {
        $now = date('Y-m-d H:i:s');
        $this->db->table('leads')->insert([
            'branch_id' => $branch,
            'owner_user_id' => $this->actor,
            'source' => 'TEST_MCC_FLOW',
            'full_name' => $this->marker . ' ' . $suffix,
            'email' => strtolower($this->marker . '_' . $suffix) . '@example.invalid',
            'phone' => null,
            'date_of_birth' => '2012-05-10',
            'status' => 'lead',
            'lost_reason' => null,
            'closed_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) $this->db->insertID();
    }

    private function groundName(int $branch): string
    {
        $ground = $this->db->table('grounds')
            ->where(['branch_id' => $branch, 'is_active' => 1])
            ->orderBy('id', 'ASC')
            ->get()
            ->getRowArray();

        if (is_array($ground)) {
            return (string) $ground['name'];
        }

        $name = $this->marker . ' Ground ' . $branch;
        $now = date('Y-m-d H:i:s');
        $this->db->table('grounds')->insert([
            'branch_id' => $branch,
            'name' => $name,
            'ground_type' => 'outdoor_ground',
            'description' => null,
            'is_active' => 1,
            'created_by' => $this->actor,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $name;
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

    private function assertInvalidTransition(int $trial, int $admission): void
    {
        try {
            service('trial')->selection($trial, 'selected');
            self::fail('A second Trial selection was accepted.');
        } catch (RuntimeException $exception) {
            self::assertSame('Only an attended trial can be selected.', $exception->getMessage());
        }

        try {
            service('admission')->transition($admission, 'APPROVED');
            self::fail('A terminal Admission transition was accepted.');
        } catch (RuntimeException $exception) {
            self::assertSame('Invalid transition.', $exception->getMessage());
        }
    }
}
