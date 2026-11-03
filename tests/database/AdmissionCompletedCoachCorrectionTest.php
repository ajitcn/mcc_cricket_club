<?php

declare(strict_types=1);

namespace Tests\Database;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use RuntimeException;

final class AdmissionCompletedCoachCorrectionTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $DBGroup = 'tests';
    protected $migrate = false;

    private int $branch;
    private int $actor;
    /** @var list<int> */
    private array $admissionIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        $branch = $this->db->table('branches')
            ->where('code', 'TEST_MCC_VNS')
            ->get()
            ->getRowArray();
        $actor = $this->db->table('users')
            ->where('email', 'test.mcc.manager.vns@example.invalid')
            ->get()
            ->getRowArray();

        self::assertIsArray($branch);
        self::assertIsArray($actor);
        $this->branch = (int) $branch['id'];
        $this->actor = (int) $actor['id'];
        session()->set([
            'isLoggedIn' => true,
            'accountEnabled' => true,
            'user' => ['id' => $this->actor],
            'session_version' => 1,
            'permissions' => ['admission.view', 'admission.create', 'admission.update'],
            'authorizedBranches' => [(string) $this->branch],
        ]);
    }

    protected function tearDown(): void
    {
        foreach ($this->admissionIds as $admissionId) {
            $this->db->table('admissions')->where('id', $admissionId)->delete();
        }

        session()->destroy();
        parent::tearDown();
    }

    public function testCompletedAdmissionCoachChangesAreAuditedAndKeepStatusAndNumber(): void
    {
        [$groupCoach, $personalCoach] = $this->activeBranchCoaches();
        $admissionId = $this->createCompletedAdmission();
        $details = service('admission')->details($admissionId);
        self::assertSame('APPROVED', $details['status']);
        self::assertNull($details['coach_user_id']);
        self::assertNull($details['personal_coach_user_id']);
        $before = $this->db->table('admissions')->where('id', $admissionId)->get()->getRowArray();
        self::assertIsArray($before);

        self::assertTrue(service('admission')->updateIntake($admissionId, [
            'coach_user_id' => $groupCoach,
            'personal_coach_user_id' => $personalCoach,
            'correction_reason' => 'Corrected completed Admission coach assignments.',
            'expected_version' => 0,
        ]));

        $after = $this->db->table('admissions')->where('id', $admissionId)->get()->getRowArray();
        self::assertSame($before['admission_number'], $after['admission_number']);
        self::assertSame('APPROVED', $after['status']);
        self::assertSame($groupCoach, (int) $after['coach_user_id']);
        self::assertSame($personalCoach, (int) $after['personal_coach_user_id']);
        self::assertSame(1, $this->db->table('audit_logs')->where([
            'entity_type' => 'admission',
            'entity_id' => (string) $admissionId,
            'action' => 'admission_completed_coaches_corrected',
        ])->countAllResults());

        $audit = $this->db->table('audit_logs')->where([
            'entity_type' => 'admission',
            'entity_id' => (string) $admissionId,
            'action' => 'admission_completed_coaches_corrected',
        ])->get()->getRowArray();
        self::assertIsArray($audit);
        self::assertSame($this->branch, (int) $audit['branch_id']);
        self::assertSame('Corrected completed Admission coach assignments.', $audit['reason']);
        self::assertStringContainsString((string) $before['admission_number'], (string) $audit['before_summary']);
        self::assertStringContainsString((string) $before['admission_number'], (string) $audit['after_summary']);

        self::assertFalse(service('admission')->updateIntake($admissionId, [
            'coach_user_id' => $groupCoach,
            'personal_coach_user_id' => $personalCoach,
        ]));
        self::assertSame(1, $this->db->table('audit_logs')->where([
            'entity_type' => 'admission',
            'entity_id' => (string) $admissionId,
            'action' => 'admission_completed_coaches_corrected',
        ])->countAllResults());
    }

    public function testCompletedCoachChangeRequiresReasonAndRollsBack(): void
    {
        [$groupCoach] = $this->activeBranchCoaches();
        self::assertSame(1, $this->db->table('user_branches ub')
            ->join('users u', 'u.id = ub.user_id')
            ->join('user_roles ur', 'ur.user_id = u.id')
            ->join('roles r', 'r.id = ur.role_id')
            ->join('coach_profiles cp', 'cp.user_id = u.id')
            ->where([
                'ub.user_id' => $groupCoach,
                'ub.branch_id' => $this->branch,
                'u.is_enabled' => 1,
                'cp.is_active' => 1,
            ])
            ->whereIn('r.name', ['Coach', 'Fitness Coach'])
            ->countAllResults());
        $admissionId = $this->createCompletedAdmission();
        $before = $this->db->table('admissions')->where('id', $admissionId)->get()->getRowArray();
        self::assertIsArray($before);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Correction reason is required');
        try {
            service('admission')->updateIntake($admissionId, [
                'coach_user_id' => $groupCoach,
                'personal_coach_user_id' => null,
            ]);
        } finally {
            $after = $this->db->table('admissions')->where('id', $admissionId)->get()->getRowArray();
            self::assertSame($before['admission_number'], $after['admission_number']);
            self::assertSame($before['coach_user_id'], $after['coach_user_id']);
            self::assertSame($before['personal_coach_user_id'], $after['personal_coach_user_id']);
        }
    }

    public function testCompletedAdmissionRejectsUnrelatedFieldsAndInvalidCoachOwnership(): void
    {
        [$groupCoach] = $this->activeBranchCoaches();
        $admissionId = $this->createCompletedAdmission();

        $this->expectExceptionMessage('Completed Admission only permits coach assignment changes.');
        try {
            service('admission')->updateIntake($admissionId, [
                'applicant_full_name' => 'Forged unrelated change',
                'coach_user_id' => $groupCoach,
                'correction_reason' => 'Attempted unrelated edit.',
            ]);
        } catch (RuntimeException $exception) {
            self::assertSame('Completed Admission only permits coach assignment changes.', $exception->getMessage());
            throw $exception;
        }
    }

    public function testCompletedAdmissionRejectsEveryNonCoachFieldAndPreservesSnapshot(): void
    {
        $admissionId = $this->createCompletedAdmission();
        $before = $this->db->table('admissions')->where('id', $admissionId)->get()->getRowArray();
        self::assertIsArray($before);

        $forbiddenFields = [
            'status' => 'INTAKE',
            'admission_number' => 'FORGED-NUMBER',
            'branch_id' => $this->branch,
            'batch_id' => 1,
            'age_group_id' => 1,
            'start_date' => '01-01-2027',
            'end_date' => '31-12-2027',
            'training_fee' => '99999.00',
            'hostel_charges' => '99999.00',
            'installment_plan' => 'Full Payment',
            'hostel_installment_plan' => 'Full Payment',
            'ability_overall' => 10,
            'signature_student' => 'forged-signature',
            'coaching_plan' => 'forged-plan',
            'installment_rows' => [['amount' => 1]],
        ];

        foreach ($forbiddenFields as $field => $value) {
            try {
                service('admission')->updateIntake($admissionId, [
                    $field => $value,
                    'correction_reason' => 'Rejected non-coach field.',
                ]);
                self::fail('Expected completed Admission field rejection for ' . $field);
            } catch (RuntimeException $exception) {
                self::assertSame(
                    'Completed Admission only permits coach assignment changes.',
                    $exception->getMessage(),
                );
            }
        }

        self::assertSame(
            $before,
            $this->db->table('admissions')->where('id', $admissionId)->get()->getRowArray(),
        );
    }

    public function testCompletedAdmissionRejectsInvalidAndCrossBranchCoachIds(): void
    {
        $admissionId = $this->createCompletedAdmission();
        $otherBranch = (int) $this->db->table('branches')
            ->where('code', 'TEST_MCC_DEL')
            ->get()
            ->getRow('id');
        $crossBranchCoach = $this->db->table('users u')
            ->select('u.id')
            ->join('user_branches ub', 'ub.user_id = u.id')
            ->join('user_roles ur', 'ur.user_id = u.id')
            ->join('roles r', 'r.id = ur.role_id')
            ->join('coach_profiles cp', 'cp.user_id = u.id')
            ->where([
                'ub.branch_id' => $otherBranch,
                'u.is_enabled' => 1,
                'cp.is_active' => 1,
            ])
            ->whereIn('r.name', ['Coach', 'Fitness Coach'])
            ->get()
            ->getRow('id');

        self::assertGreaterThan(0, (int) $crossBranchCoach);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid admission Coach.');

        service('admission')->updateIntake($admissionId, [
            'coach_user_id' => (int) $crossBranchCoach,
            'correction_reason' => 'Rejected cross-branch coach assignment.',
        ]);
    }

    public function testCompletedAdmissionRejectsStaleRenewalVersion(): void
    {
        [$groupCoach] = $this->activeBranchCoaches();
        $admissionId = $this->createCompletedAdmission();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Admission version is stale.');

        service('admission')->updateIntake($admissionId, [
            'coach_user_id' => $groupCoach,
            'correction_reason' => 'Rejected stale version.',
            'expected_version' => 999,
        ]);
    }

    public function testConvertedAdmissionCoachChangesAreAuditedWithoutChangingStatus(): void
    {
        [$groupCoach, $personalCoach] = $this->activeBranchCoaches();
        $admissionId = $this->createTerminalAdmission('CONVERTED');

        $changed = service('admission')->updateIntake($admissionId, [
            'coach_user_id' => $groupCoach,
            'personal_coach_user_id' => $personalCoach,
            'correction_reason' => 'Correct converted Admission coach assignments.',
            'expected_version' => 0,
        ]);

        self::assertTrue($changed);
        $saved = $this->db->table('admissions')->where('id', $admissionId)->get()->getRowArray();
        self::assertSame('CONVERTED', $saved['status']);
        self::assertSame($groupCoach, (int) $saved['coach_user_id']);
        self::assertSame($personalCoach, (int) $saved['personal_coach_user_id']);

        $audit = $this->db->table('audit_logs')
            ->where('action', 'admission_completed_coaches_corrected')
            ->like('entity_id', (string) $admissionId)
            ->countAllResults();
        self::assertSame(1, $audit);
    }

    public function testDedicatedCoachUpdateWorkflowPreservesAdmissionIdentity(): void
    {
        [$groupCoach, $personalCoach] = $this->activeBranchCoaches();
        $admissionId = $this->createTerminalAdmission('COMPLETED');
        $form = service('admission')->coachCorrectionForm($admissionId);

        self::assertSame($admissionId, (int) $form['admission']['id']);
        self::assertNotEmpty($form['coaches']);
        $before = $this->db->table('admissions')->where('id', $admissionId)->get()->getRowArray();

        self::assertTrue(service('admission')->updateCoachAssignments($admissionId, [
            'batch_coach_id' => $groupCoach,
            'personal_coach_id' => $personalCoach,
            'correction_reason' => 'Dedicated coach correction workflow.',
            'expected_version' => (int) ($before['renewal_version'] ?? 0),
        ]));

        $after = $this->db->table('admissions')->where('id', $admissionId)->get()->getRowArray();
        self::assertSame($before['admission_number'], $after['admission_number']);
        self::assertSame('COMPLETED', $after['status']);
        self::assertSame($groupCoach, (int) $after['coach_user_id']);
        self::assertSame($personalCoach, (int) $after['personal_coach_user_id']);
    }

    /** @return array{0: int, 1: int} */
    private function activeBranchCoaches(): array
    {
        $rows = $this->db->table('users u')
            ->select('u.id')
            ->join('user_branches ub', 'ub.user_id = u.id')
            ->join('user_roles ur', 'ur.user_id = u.id')
            ->join('roles r', 'r.id = ur.role_id')
            ->join('coach_profiles cp', 'cp.user_id = u.id')
            ->where([
                'ub.branch_id' => $this->branch,
                'u.is_enabled' => 1,
                'cp.is_active' => 1,
            ])
            ->whereIn('r.name', ['Coach', 'Fitness Coach'])
            ->groupBy('u.id')
            ->orderBy('u.id', 'ASC')
            ->get()
            ->getResultArray();

        self::assertGreaterThanOrEqual(2, count($rows));

        return [(int) $rows[0]['id'], (int) $rows[1]['id']];
    }

    private function createCompletedAdmission(): int
    {
        return $this->createTerminalAdmission('APPROVED');
    }

    private function createTerminalAdmission(string $status): int
    {
        $admissionId = service('admission')->createDirect([
            'branch_id' => $this->branch,
            'applicant_full_name' => 'Completed Coach Correction ' . uniqid(),
            'joining_date' => '01-01-2026',
            'year1_end_date' => '31-12-2026',
        ]);
        $this->admissionIds[] = $admissionId;
        $this->db->table('admissions')
            ->where('id', $admissionId)
            ->update(['status' => $status]);

        return $admissionId;
    }
}
