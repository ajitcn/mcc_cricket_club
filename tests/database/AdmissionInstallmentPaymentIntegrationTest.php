<?php

declare(strict_types=1);

namespace Tests\Database;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use RuntimeException;

final class AdmissionInstallmentPaymentIntegrationTest extends CIUnitTestCase
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

        if (! $this->db->tableExists('admission_installment_payments')) {
            self::markTestSkipped('The admission installment payment migration has not been applied.');
        }

        $branch = $this->db->table('branches')->where('code', 'TEST_MCC_VNS')->get()->getRowArray();
        $actor = $this->db->table('users')->where('email', 'test.mcc.manager.vns@example.invalid')->get()->getRowArray();
        self::assertIsArray($branch);
        self::assertIsArray($actor);
        $this->branch = (int) $branch['id'];
        $this->actor = (int) $actor['id'];
        $this->loginForBranch($this->branch);
    }

    protected function tearDown(): void
    {
        if ($this->admissionIds !== []) {
            $this->db->table('admissions')->whereIn('id', $this->admissionIds)->delete();
        }

        session()->destroy();
        parent::tearDown();
    }

    public function testPaymentLedgerCalculatesStatusesAndIsIdempotent(): void
    {
        $admissionId = $this->createAdmission('Payment Status Applicant');
        $installmentId = $this->insertInstallment($admissionId, '1000.00');

        $projection = service('admissionInstallmentPayment')->paymentForm('coaching', $installmentId);
        self::assertSame($installmentId, $projection['installment_id']);
        self::assertSame('Not captured', $projection['payment_status']);

        $firstId = service('admissionInstallmentPayment')->record('coaching', $installmentId, [
            'amount_received' => '500.00',
            'payment_date' => '18-10-2026',
            'payment_method' => 'UPI',
            'receipt_number' => 'TEST-RECEIPT-001',
            'reference_number' => 'UPI-001',
            'notes' => 'First receipt',
        ]);
        $projection = service('admissionInstallmentPayment')->paymentForm('coaching', $installmentId);
        self::assertSame('Partially paid', $projection['payment_status']);
        self::assertSame('500.00', $projection['total_received']);

        $secondId = service('admissionInstallmentPayment')->record('coaching', $installmentId, [
            'amount_received' => '500.00',
            'payment_date' => '18-10-2026',
            'payment_method' => 'UPI',
            'receipt_number' => 'TEST-RECEIPT-002',
            'reference_number' => 'UPI-002',
            'notes' => 'Second receipt',
        ]);
        $projection = service('admissionInstallmentPayment')->paymentForm('coaching', $installmentId);
        self::assertSame('Paid', $projection['payment_status']);
        self::assertSame('1000.00', $projection['total_received']);
        self::assertCount(2, $projection['payment_history']);

        $admissionNumber = (string) $this->db->table('admissions')
            ->where('id', $admissionId)
            ->get()
            ->getRow('admission_number');
        $paidRows = service('admissionInstallments')->paginatedForAuthorizedBranches([
            'admission_number' => $admissionNumber,
            'payment_status' => 'Paid',
        ]);
        self::assertSame(1, $paidRows['total']);
        self::assertSame('Paid', $paidRows['installments'][0]['payment_status']);

        $details = service('admission')->details($admissionId);
        self::assertCount(1, $details['installment_status_rows']);
        self::assertSame('Paid', $details['installment_status_rows'][0]['payment_status']);
        self::assertSame($installmentId, $details['installment_status_rows'][0]['installment_id']);

        $retryId = service('admissionInstallmentPayment')->record('coaching', $installmentId, [
            'amount_received' => '500.00',
            'payment_date' => '18-10-2026',
            'payment_method' => 'UPI',
            'receipt_number' => 'TEST-RECEIPT-002',
            'reference_number' => 'UPI-002',
            'notes' => 'Second receipt',
        ]);
        self::assertSame($secondId, $retryId);
        self::assertSame(2, $this->db->table('admission_installment_payments')
            ->where('installment_id', $installmentId)
            ->countAllResults());
        self::assertNotSame($firstId, $secondId);
    }

    public function testExcessPaymentIsReportedAsOverpaid(): void
    {
        $admissionId = $this->createAdmission('Overpaid Applicant');
        $installmentId = $this->insertInstallment($admissionId, '1000.00');

        service('admissionInstallmentPayment')->record('coaching', $installmentId, [
            'amount_received' => '1000.01',
            'payment_date' => '2026-10-18',
            'payment_method' => 'Cash',
            'receipt_number' => 'TEST-RECEIPT-OVERPAID',
        ]);

        $projection = service('admissionInstallmentPayment')->paymentForm('coaching', $installmentId);
        self::assertSame('Overpaid', $projection['payment_status']);
    }

    public function testHostelPaymentIsLinkedAndBranchIsolationBlocksUnauthorizedWrites(): void
    {
        $admissionId = $this->createAdmission('Hostel Payment Applicant');
        $installmentId = $this->insertInstallment($admissionId, '2500.00', 'admission_hostel_installments');
        $otherBranch = (int) $this->db->table('branches')
            ->where('code', 'TEST_MCC_DEL')
            ->get()
            ->getRow('id');
        $this->loginForBranch($otherBranch);

        $this->expectException(RuntimeException::class);
        service('admissionInstallmentPayment')->record('hostel', $installmentId, [
            'amount_received' => '2500.00',
            'payment_date' => '2026-10-18',
            'payment_method' => 'Cash',
            'receipt_number' => 'TEST-RECEIPT-BLOCKED',
        ]);
    }

    public function testInvalidPaymentRollsBackWithoutWritingHistory(): void
    {
        $admissionId = $this->createAdmission('Rollback Payment Applicant');
        $installmentId = $this->insertInstallment($admissionId, '1000.00');

        $this->expectException(RuntimeException::class);
        try {
            service('admissionInstallmentPayment')->record('coaching', $installmentId, [
                'amount_received' => '0.00',
                'payment_date' => '2026-10-18',
                'payment_method' => 'Cash',
                'receipt_number' => 'TEST-RECEIPT-ROLLBACK',
            ]);
        } finally {
            self::assertSame(0, $this->db->table('admission_installment_payments')
                ->where('installment_id', $installmentId)
                ->countAllResults());
        }
    }

    private function loginForBranch(int $branchId): void
    {
        session()->set([
            'isLoggedIn' => true,
            'accountEnabled' => true,
            'user' => ['id' => $this->actor],
            'session_version' => 1,
            'permissions' => ['admission.view', 'admission.create', 'admission.update'],
            'authorizedBranches' => [(string) $branchId],
        ]);
    }

    private function createAdmission(string $name): int
    {
        $id = service('admission')->createDirect([
            'branch_id' => $this->branch,
            'applicant_full_name' => $name . ' ' . uniqid(),
            'joining_date' => '01-01-2026',
            'year1_end_date' => '31-12-2026',
        ]);
        $this->admissionIds[] = $id;

        return $id;
    }

    private function insertInstallment(
        int $admissionId,
        string $amount,
        string $table = 'admission_coaching_installments',
    ): int {
        $period = $this->db->table('admission_periods')
            ->where(['admission_id' => $admissionId, 'status' => 'current'])
            ->get()
            ->getRowArray();
        self::assertIsArray($period);
        $this->db->table($table)->insert([
            'admission_id' => $admissionId,
            'admission_period_id' => (int) $period['id'],
            'year_number' => 1,
            'year_label' => 'Year 1',
            'installment_number' => 1,
            'due_date' => '2026-12-31',
            'amount' => $amount,
            'created_by' => $this->actor,
            'version' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }
}
