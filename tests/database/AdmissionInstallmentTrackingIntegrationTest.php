<?php

declare(strict_types=1);

namespace Tests\Database;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use DateTimeImmutable;
use DateTimeZone;

final class AdmissionInstallmentTrackingIntegrationTest extends CIUnitTestCase
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
        if ($this->admissionIds !== []) {
            $this->db->table('admissions')->whereIn('id', $this->admissionIds)->delete();
        }

        session()->destroy();
        parent::tearDown();
    }

    public function testCoachingAndHostelRowsProjectWithAdmissionNumberAndDueStateFilters(): void
    {
        $admissionId = $this->createAdmission();
        $period = $this->db->table('admission_periods')
            ->where(['admission_id' => $admissionId, 'status' => 'current'])
            ->get()
            ->getRowArray();
        self::assertIsArray($period);

        $today = new DateTimeImmutable('now', new DateTimeZone(config('App')->appTimezone));
        $dates = [
            'past' => $today->modify('-1 day')->format('Y-m-d'),
            'today' => $today->format('Y-m-d'),
            'future' => $today->modify('+1 day')->format('Y-m-d'),
        ];
        $before = $this->db->table('admission_coaching_installments')
            ->where('admission_id', $admissionId)
            ->countAllResults();

        $this->insertInstallment('admission_coaching_installments', $admissionId, (int) $period['id'], 1, 1, $dates['past'], '1000.00');
        $this->insertInstallment('admission_coaching_installments', $admissionId, (int) $period['id'], 1, 2, $dates['future'], '2000.00');
        $this->insertInstallment('admission_hostel_installments', $admissionId, (int) $period['id'], 1, 1, $dates['today'], '3000.00');

        $admission = $this->db->table('admissions')->where('id', $admissionId)->get()->getRowArray();
        self::assertIsArray($admission);
        $scopedFilters = ['admission_number' => $admission['admission_number']];
        $all = service('admissionInstallments')->paginatedForAuthorizedBranches($scopedFilters, 1, 25);
        self::assertSame(3, $all['total']);
        self::assertCount(3, $all['installments']);
        self::assertSame('Coaching', ucfirst((string) $all['installments'][0]['installment_type']));
        self::assertSame($admission['admission_number'], $all['installments'][0]['admission_number']);

        $overdue = service('admissionInstallments')->paginatedForAuthorizedBranches(
            $scopedFilters + ['due_state' => 'overdue'],
        );
        self::assertSame(1, $overdue['total']);
        self::assertSame('Overdue', $overdue['installments'][0]['due_state']);

        $hostel = service('admissionInstallments')->paginatedForAuthorizedBranches(
            $scopedFilters + ['installment_type' => 'hostel'],
        );
        self::assertSame(1, $hostel['total']);
        self::assertSame('hostel', $hostel['installments'][0]['installment_type']);

        $details = service('admission')->details($admissionId);
        self::assertIsArray($details);
        self::assertCount(3, $details['installment_status_rows']);
        $detailTypes = array_column($details['installment_status_rows'], 'installment_type');
        sort($detailTypes);
        self::assertSame(['coaching', 'coaching', 'hostel'], $detailTypes);
        self::assertSame(
            $admission['admission_number'],
            $details['admission_number'],
        );

        $otherBranch = (int) $this->db->table('branches')
            ->where('code', 'TEST_MCC_DEL')
            ->get()
            ->getRow('id');
        $unauthorizedBranch = service('admissionInstallments')->paginatedForAuthorizedBranches([
            'branch_id' => (string) $otherBranch,
        ]);
        self::assertSame(0, $unauthorizedBranch['total']);

        $after = $this->db->table('admission_coaching_installments')
            ->where('admission_id', $admissionId)
            ->countAllResults();
        self::assertSame($before + 2, $after);
    }

    private function createAdmission(): int
    {
        $id = service('admission')->createDirect([
            'branch_id' => $this->branch,
            'applicant_full_name' => 'Installment Tracking Applicant ' . uniqid(),
            'joining_date' => '01-01-2026',
            'year1_end_date' => '31-12-2026',
        ]);
        $this->admissionIds[] = $id;

        return $id;
    }

    private function insertInstallment(
        string $table,
        int $admissionId,
        int $periodId,
        int $year,
        int $number,
        string $dueDate,
        string $amount,
    ): void {
        $now = date('Y-m-d H:i:s');
        $this->db->table($table)->insert([
            'admission_id' => $admissionId,
            'admission_period_id' => $periodId,
            'year_number' => $year,
            'year_label' => 'Year ' . $year,
            'installment_number' => $number,
            'due_date' => $dueDate,
            'amount' => $amount,
            'created_by' => $this->actor,
            'version' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
