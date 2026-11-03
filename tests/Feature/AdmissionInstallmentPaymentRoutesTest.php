<?php

declare(strict_types=1);

namespace Tests\Feature;

use CodeIgniter\Security\Exceptions\SecurityException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

final class AdmissionInstallmentPaymentRoutesTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $DBGroup = 'tests';
    protected $migrate = false;

    private int $branch;
    private int $actor;
    private int $installment;
    /** @var list<string> */
    private array $receipts = [];

    protected function setUp(): void
    {
        parent::setUp();

        if (! $this->db->tableExists('admission_installment_payments')) {
            self::markTestSkipped('The admission installment payment migration has not been applied.');
        }

        $branch = $this->db->table('branches')->where('code', 'TEST_MCC_VNS')->get()->getRowArray();
        $actor = $this->db->table('users')->where('email', 'test.mcc.manager.vns@example.invalid')->get()->getRowArray();
        $installment = $this->db->table('admission_coaching_installments i')
            ->select('i.id')
            ->join('admissions a', 'a.id = i.admission_id')
            ->where('a.branch_id', (int) ($branch['id'] ?? 0))
            ->orderBy('i.id', 'ASC')
            ->get()
            ->getRowArray();
        self::assertIsArray($branch);
        self::assertIsArray($actor);
        if (! is_array($installment)) {
            self::markTestSkipped('No coaching installment is available for payment route tests.');
        }

        $this->branch = (int) $branch['id'];
        $this->actor = (int) $actor['id'];
        $this->installment = (int) $installment['id'];
        $this->authorize();
    }

    protected function tearDown(): void
    {
        if ($this->receipts !== []) {
            $this->db->table('admission_installment_payments')
                ->whereIn('receipt_number', $this->receipts)
                ->delete();
        }

        session()->destroy();
        \Config\Services::resetSingle('authorization');
        parent::tearDown();
    }

    public function testInvalidPaymentRedirectsToTheFormWithInputAndVisibleError(): void
    {
        $response = $this->withSession(session()->get())->post(
            $this->paymentPath(),
            [
                'amount_received' => '0.00',
                'payment_date' => '18-10-2026',
                'payment_method' => 'Cash',
                'receipt_number' => 'FEATURE-INVALID-PAYMENT',
                csrf_token() => csrf_hash(),
            ],
        );

        self::assertSame(302, $response->response()->getStatusCode());
        self::assertStringContainsString('/payment', $response->response()->getHeaderLine('Location'));
        self::assertSame('Enter a positive payment amount.', session()->getFlashdata('error'));
        self::assertSame(
            'Enter a positive payment amount.',
            session()->getFlashdata('field_errors')['amount_received'] ?? null,
        );
        self::assertSame(
            0,
            $this->db->table('admission_installment_payments')
                ->where('receipt_number', 'FEATURE-INVALID-PAYMENT')
                ->countAllResults(),
        );
    }

    public function testSuccessfulPaymentRedirectsWithoutFallingIntoTheErrorView(): void
    {
        $receipt = 'FEATURE-PAYMENT-' . strtoupper(bin2hex(random_bytes(3)));
        $this->receipts[] = $receipt;
        $response = $this->withSession(session()->get())->post(
            $this->paymentPath(),
            [
                'amount_received' => '1.00',
                'payment_date' => '18-10-2026',
                'payment_method' => 'Cash',
                'receipt_number' => $receipt,
                csrf_token() => csrf_hash(),
            ],
        );

        self::assertSame(302, $response->response()->getStatusCode());
        self::assertStringContainsString('/payment', $response->response()->getHeaderLine('Location'));
        self::assertSame(
            1,
            $this->db->table('admission_installment_payments')
                ->where('receipt_number', $receipt)
                ->countAllResults(),
        );
        self::assertStringNotContainsString('The error view file was not specified', $response->response()->getBody());
    }

    public function testMissingCsrfIsRejectedBeforeAnyPaymentWrite(): void
    {
        $this->expectException(SecurityException::class);

        $this->withSession(session()->get())->post($this->paymentPath(), [
            'amount_received' => '1.00',
            'payment_date' => '18-10-2026',
            'payment_method' => 'Cash',
            'receipt_number' => 'FEATURE-CSRF-PAYMENT',
        ]);
    }

    public function testInvalidPaymentIdsReturnSafeResponsesWithoutTheUndefinedErrorView(): void
    {
        foreach (['0', 'not-a-number'] as $invalidId) {
            $response = $this->withSession(session()->get())->get(
                'admissions/fee-installments/coaching/' . $invalidId . '/payment'
            );

            self::assertSame(404, $response->response()->getStatusCode());
            self::assertStringContainsString('The installment record was not found.', $response->getBody());
            self::assertStringNotContainsString('The error view file was not specified', $response->getBody());
        }

        $missingId = $this->withSession(session()->get())->get(
            'admissions/fee-installments/coaching/payment'
        );
        self::assertSame(404, $missingId->response()->getStatusCode());
        self::assertStringContainsString('The installment record was not found.', $missingId->getBody());
    }

    public function testPaymentFormActionUsesTheRealCoachingInstallmentId(): void
    {
        $response = $this->withSession(session()->get())->get($this->paymentPath());

        self::assertSame(200, $response->response()->getStatusCode());
        self::assertStringContainsString(
            '/admissions/fee-installments/coaching/' . $this->installment . '/payment',
            $response->getBody(),
        );
        self::assertStringNotContainsString('/fee-installments/coaching/0/payment', $response->getBody());
    }

    public function testFeeInstallmentsListUsesTheRealHostelInstallmentId(): void
    {
        $hostel = $this->db->table('admission_hostel_installments i')
            ->select('i.id')
            ->join('admissions a', 'a.id = i.admission_id')
            ->where('a.branch_id', $this->branch)
            ->orderBy('i.id', 'ASC')
            ->get()
            ->getRowArray();
        if (! is_array($hostel)) {
            self::markTestSkipped('No hostel installment is available for the payment link contract.');
        }

        $response = $this->withSession(session()->get())->get('admissions/fee-installments');

        self::assertSame(200, $response->response()->getStatusCode());
        self::assertStringContainsString(
            '/admissions/fee-installments/hostel/' . (int) $hostel['id'] . '/payment',
            $response->getBody(),
        );
        self::assertStringNotContainsString('/fee-installments/hostel/0/payment', $response->getBody());
    }

    private function authorize(): void
    {
        session()->set([
            'isLoggedIn' => true,
            'accountEnabled' => true,
            'user' => ['id' => $this->actor],
            'session_version' => 1,
            'permissions' => ['admission.view', 'admission.update'],
            'authorizedBranches' => [(string) $this->branch],
        ]);
    }

    private function paymentPath(): string
    {
        return 'admissions/fee-installments/coaching/' . $this->installment . '/payment';
    }
}
