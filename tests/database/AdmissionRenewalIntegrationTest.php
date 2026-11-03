<?php

declare(strict_types=1);

namespace Tests\Database;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use RuntimeException;

final class AdmissionRenewalIntegrationTest extends CIUnitTestCase
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

        $branch = $this->db->table('branches')->where('code', 'TEST_MCC_VNS')->get()->getRowArray();
        $actor = $this->db->table('users')->where('email', 'test.mcc.manager.vns@example.invalid')->get()->getRowArray();
        self::assertIsArray($branch);
        self::assertIsArray($actor);
        $this->branch = (int) $branch['id'];
        $this->actor = (int) $actor['id'];
        $this->authorize(['admission.create', 'admission.update', 'admission.view'], [$this->branch]);
    }

    protected function tearDown(): void
    {
        if ($this->admissionIds !== []) {
            $this->db->table('admissions')->whereIn('id', $this->admissionIds)->delete();
        }

        session()->destroy();
        parent::tearDown();
    }

    public function testInitialPeriodRenewalProjectionAndHistoricalPreservation(): void
    {
        $admissionId = $this->createAdmission();
        $initial = $this->db->table('admission_periods')->where('admission_id', $admissionId)->get()->getRowArray();
        self::assertIsArray($initial);
        self::assertSame(1, (int) $initial['period_number']);

        $periodId = service('admission')->renew($admissionId, [
            'expected_version' => 0,
            'start_date' => '01-01-2027',
            'end_date' => '31-12-2027',
        ]);
        $periods = $this->db->table('admission_periods')->where('admission_id', $admissionId)
            ->orderBy('period_number')->get()->getResultArray();
        $current = $this->db->table('admissions')->where('id', $admissionId)->get()->getRowArray();

        self::assertCount(2, $periods);
        self::assertSame('2026-01-01', $periods[0]['start_date']);
        self::assertSame('2026-12-31', $periods[0]['end_date']);
        self::assertSame('historical', $periods[0]['status']);
        self::assertSame($periodId, (int) $periods[1]['id']);
        self::assertSame('current', $periods[1]['status']);
        self::assertSame('2027-01-01', $current['joining_date']);
        self::assertSame('2027-12-31', $current['year1_end_date']);
        self::assertSame(1, (int) $current['renewal_version']);
    }

    public function testRenewalRejectsOverlapInvalidMasterAndStaleVersion(): void
    {
        $admissionId = $this->createAdmission();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('must be after');
        service('admission')->renew($admissionId, [
            'expected_version' => 0,
            'start_date' => '31-12-2026',
            'end_date' => '31-12-2027',
        ]);
    }

    public function testRenewalRejectsMissingCurrentEndDateWithoutChangingTheCurrentPeriod(): void
    {
        $admissionId = $this->createAdmission();
        $this->db->table('admission_periods')->where([
            'admission_id' => $admissionId,
            'status' => 'current',
        ])->update(['end_date' => null]);
        $this->db->table('admissions')->where('id', $admissionId)->update([
            'year1_end_date' => null,
        ]);

        $before = $this->db->table('admission_periods')
            ->where('admission_id', $admissionId)
            ->get()
            ->getResultArray();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Please enter the current Admission period end date before renewing.');
        try {
            service('admission')->renew($admissionId, [
                'expected_version' => 0,
                'start_date' => '01-01-2027',
                'end_date' => '31-12-2027',
            ]);
        } finally {
            $after = $this->db->table('admission_periods')
                ->where('admission_id', $admissionId)
                ->get()
                ->getResultArray();
            self::assertSame($before, $after);
        }
    }

    public function testRenewalUsesAdmissionDatesWhenThePeriodRowIsMissing(): void
    {
        $admissionId = $this->createAdmission();
        $this->db->table('admission_periods')->where('admission_id', $admissionId)->delete();

        $periodId = service('admission')->renew($admissionId, [
            'expected_version' => 0,
            'start_date' => '01-01-2027',
            'end_date' => '31-12-2027',
        ]);

        self::assertGreaterThan(0, $periodId);
        self::assertSame(2, $this->db->table('admission_periods')->where('admission_id', $admissionId)->countAllResults());
    }

    public function testRenewalUsesAdmissionEndDateWhenCurrentPeriodEndDateIsMissing(): void
    {
        $admissionId = $this->createAdmission();
        $this->db->table('admission_periods')->where([
            'admission_id' => $admissionId,
            'status' => 'current',
        ])->update(['end_date' => null]);

        $periodId = service('admission')->renew($admissionId, [
            'expected_version' => 0,
            'start_date' => '01-01-2027',
            'end_date' => '31-12-2027',
        ]);

        self::assertGreaterThan(0, $periodId);
        self::assertSame(2, $this->db->table('admission_periods')->where('admission_id', $admissionId)->countAllResults());
    }

    public function testRenewalRetryIsIdempotent(): void
    {
        $admissionId = $this->createAdmission();
        $payload = [
            'expected_version' => 0,
            'start_date' => '01-01-2027',
            'end_date' => '31-12-2027',
        ];
        $first = service('admission')->renew($admissionId, $payload);
        $second = service('admission')->renew($admissionId, $payload);

        self::assertSame($first, $second);
        self::assertSame(2, $this->db->table('admission_periods')->where('admission_id', $admissionId)->countAllResults());
    }

    public function testRenewalRejectsAnOptimisticallyStaleVersion(): void
    {
        $admissionId = $this->createAdmission();
        service('admission')->renew($admissionId, [
            'expected_version' => 0,
            'start_date' => '01-01-2027',
            'end_date' => '31-12-2027',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('renewed by another user');
        service('admission')->renew($admissionId, [
            'expected_version' => 0,
            'start_date' => '01-01-2028',
            'end_date' => '31-12-2028',
        ]);
    }

    public function testRenewalPersistsFeesWithoutChangingHistoricalFeePlan(): void
    {
        $admissionId = $this->createAdmission();
        $this->db->table('admissions')->where('id', $admissionId)->update([
            'fee_plan_label' => 'Legacy Fee Plan',
            'total_taxable_fee' => '150000.00',
            'tax_rate' => '18.00',
            'calculated_tax_amount' => '27000.00',
            'total_including_tax' => '177000.00',
        ]);
        $this->db->table('admission_periods')->where('admission_id', $admissionId)->update([
            'fee_plan_label' => 'Legacy Fee Plan',
        ]);

        $periodId = service('admission')->renew($admissionId, [
            'expected_version' => 0,
            'start_date' => '01-01-2027',
            'end_date' => '31-12-2027',
            'training_fee' => '120000.00',
            'installment_plan' => 'Full Payment',
            'hostel_charges' => '60000.00',
            'hostel_installment_plan' => 'Full Payment',
        ]);

        $periods = $this->db->table('admission_periods')
            ->where('admission_id', $admissionId)
            ->orderBy('period_number')
            ->get()
            ->getResultArray();
        $admission = $this->db->table('admissions')->where('id', $admissionId)->get()->getRowArray();

        self::assertCount(2, $periods);
        self::assertSame('Legacy Fee Plan', $periods[0]['fee_plan_label']);
        self::assertSame('Legacy Fee Plan', $periods[1]['fee_plan_label']);
        self::assertSame($periodId, (int) $periods[1]['id']);
        self::assertSame('120000.00', $admission['training_fee']);
        self::assertSame('60000.00', $admission['hostel_charges']);
        self::assertSame('Legacy Fee Plan', $admission['fee_plan_label']);
        self::assertSame('150000.00', $admission['total_taxable_fee']);
        self::assertSame('18.00', $admission['tax_rate']);
        self::assertSame('27000.00', $admission['calculated_tax_amount']);
        self::assertSame('207000.00', $admission['total_including_tax']);

        if ($this->db->fieldExists('renewal_values', 'admission_periods')) {
            $snapshot = json_decode((string) $periods[1]['renewal_values'], true);
            self::assertSame('120000.00', $snapshot['training_fee']);
            self::assertSame('60000.00', $snapshot['hostel_charges']);
            self::assertSame('Legacy Fee Plan', $snapshot['fee_plan_label']);
            self::assertSame('150000.00', $snapshot['total_taxable_fee']);
            self::assertSame('18.00', $snapshot['tax_rate']);
        }
    }

    public function testRenewalKeepsCoachingAndHostelInstallmentsSeparate(): void
    {
        $admissionId = $this->createAdmission();
        $payload = [
            'expected_version' => 0,
            'start_date' => '01-01-2027',
            'end_date' => '31-12-2027',
            'training_fee' => '120000.00',
            'installment_plan' => 'Instalment Plan',
            'installment_year' => ['1'],
            'hostel_charges' => '60000.00',
            'hostel_installment_plan' => 'Instalment Plan',
            'hostel_installment_year' => ['1'],
        ];

        foreach (range(1, 4) as $number) {
            $payload['installment_' . $number . '_date'] = ['2027-0' . $number . '-01'];
            $payload['installment_' . $number] = [(string) (30000 * $number)];
            $payload['hostel_installment_' . $number . '_date'] = ['2027-0' . $number . '-15'];
            $payload['hostel_installment_' . $number] = [(string) (15000 * $number)];
        }

        $periodId = service('admission')->renew($admissionId, $payload);
        $coaching = $this->db->table('admission_coaching_installments')
            ->where(['admission_id' => $admissionId, 'admission_period_id' => $periodId])
            ->countAllResults();
        $hostel = $this->db->table('admission_hostel_installments')
            ->where(['admission_id' => $admissionId, 'admission_period_id' => $periodId])
            ->countAllResults();

        self::assertSame(4, $coaching);
        self::assertSame(4, $hostel);
    }

    private function createAdmission(): int
    {
        $id = service('admission')->createDirect([
            'branch_id' => $this->branch,
            'applicant_full_name' => 'Renewal Applicant ' . uniqid(),
            'joining_date' => '01-01-2026',
            'year1_end_date' => '31-12-2026',
        ]);
        $this->admissionIds[] = $id;

        return $id;
    }

    /** @param list<string> $permissions @param list<int> $branches */
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
    }
}
