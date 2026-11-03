<?php

declare(strict_types=1);

namespace App\Services;

use Config\Database;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;
use Throwable;

final class AdmissionInstallmentPaymentService
{
    private const TABLES = [
        'coaching' => 'admission_coaching_installments',
        'hostel' => 'admission_hostel_installments',
    ];

    private const PAYMENT_METHODS = [
        'Cash',
        'Bank transfer',
        'UPI',
        'Cheque',
        'Card',
        'Other',
    ];

    /** @return list<string> */
    public function paymentMethods(): array
    {
        return self::PAYMENT_METHODS;
    }

    public function normalizeInstallmentId(mixed $value): int
    {
        if (is_int($value) && $value > 0) {
            return $value;
        }

        if (is_string($value) && preg_match('/^[1-9][0-9]*$/', $value) === 1) {
            $id = filter_var($value, FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 1],
            ]);

            if ($id !== false) {
                return (int) $id;
            }
        }

        throw new RuntimeException('Invalid installment.');
    }

    /** @return array<string, mixed> */
    public function paymentForm(string $type, int $installmentId): array
    {
        $db = Database::connect('default');
        $installment = $this->findInstallment($db, $type, $installmentId, false);
        $this->authorize((int) $installment['branch_id']);

        return $this->paymentProjection($db, $type, $installment);
    }

    public function record(string $type, int $installmentId, array $input): int
    {
        $db = Database::connect('default');
        $db->transStart();

        try {
            $installment = $this->findInstallment($db, $type, $installmentId, true);
            $actor = $this->authorize((int) $installment['branch_id']);
            $payment = $this->normalizePayment($input);
            $existing = $db->table('admission_installment_payments')
                ->where([
                    'branch_id' => (int) $installment['branch_id'],
                    'receipt_number' => $payment['receipt_number'],
                ])
                ->get()
                ->getRowArray();

            if (is_array($existing)) {
                if ($this->samePayment($existing, $installment, $type, $payment)) {
                    $db->transComplete();

                    return (int) $existing['id'];
                }

                throw new RuntimeException('Receipt number already exists.');
            }

            $now = date('Y-m-d H:i:s');
            $inserted = $db->table('admission_installment_payments')->insert([
                'installment_id' => $installmentId,
                'admission_id' => (int) $installment['admission_id'],
                'branch_id' => (int) $installment['branch_id'],
                'payment_type' => $type,
                'amount_received' => $payment['amount_received'],
                'payment_date' => $payment['payment_date'],
                'payment_method' => $payment['payment_method'],
                'receipt_number' => $payment['receipt_number'],
                'reference_number' => $payment['reference_number'],
                'notes' => $payment['notes'],
                'received_by' => $actor,
                'entry_type' => 'payment',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            if (! $inserted) {
                throw new RuntimeException('Unable to save payment receipt.');
            }

            $paymentId = (int) $db->insertID();
            service('auditPersistence')->record(
                $actor,
                'admission_installment_payment_recorded',
                'admission_installment_payment',
                (string) $paymentId,
                (int) $installment['branch_id'],
                null,
                [
                    'installment_id' => $installmentId,
                    'admission_id' => (int) $installment['admission_id'],
                    'payment_type' => $type,
                    'amount_received' => $payment['amount_received'],
                    'payment_method' => $payment['payment_method'],
                    'receipt_number' => $payment['receipt_number'],
                    'reason' => $payment['notes'],
                ],
                $payment['notes'],
                $db,
            );

            $db->transComplete();
            if (! $db->transStatus()) {
                throw new RuntimeException('Unable to complete payment receipt.');
            }

            return $paymentId;
        } catch (Throwable $exception) {
            $db->transRollback();
            $db->resetTransStatus();
            throw $exception;
        }
    }

    public function calculateStatus(string|int|float $installmentAmount, string|int|float $receivedAmount): string
    {
        $installmentCents = $this->moneyToCents($installmentAmount);
        $receivedCents = $this->moneyToCents($receivedAmount);

        if ($receivedCents <= 0) {
            return 'Not captured';
        }
        if ($receivedCents < $installmentCents) {
            return 'Partially paid';
        }
        if ($receivedCents === $installmentCents) {
            return 'Paid';
        }

        return 'Overpaid';
    }

    /** @return array<string, mixed> */
    private function paymentProjection(object $db, string $type, array $installment): array
    {
        $installment['installment_id'] = $this->normalizeInstallmentId($installment['id'] ?? null);
        $installment['installment_type'] = $type;
        $installment['applicant_name'] = trim((string) ($installment['lead_name'] ?? '')) !== ''
            ? $installment['lead_name']
            : ($installment['applicant_full_name'] ?? 'Applicant');

        $history = [];
        $received = '0.00';
        if ($db->tableExists('admission_installment_payments')) {
            $history = $db->table('admission_installment_payments')
                ->where([
                    'installment_id' => (int) $installment['id'],
                    'admission_id' => (int) $installment['admission_id'],
                    'payment_type' => $type,
                ])
                ->orderBy('id', 'ASC')
                ->get()
                ->getResultArray();
            $received = (string) ($db->table('admission_installment_payments')
                ->selectSum('amount_received', 'total_received')
                ->where([
                    'installment_id' => (int) $installment['id'],
                    'admission_id' => (int) $installment['admission_id'],
                    'payment_type' => $type,
                ])
                ->get()
                ->getRow('total_received') ?? '0.00');
        }

        $installment['payment_history'] = $history;
        $installment['total_received'] = number_format((float) $received, 2, '.', '');
        $installment['balance'] = number_format(
            (float) $installment['amount'] - (float) $installment['total_received'],
            2,
            '.',
            '',
        );
        $installment['payment_status'] = $this->calculateStatus(
            (string) $installment['amount'],
            (string) $installment['total_received'],
        );

        return $installment;
    }

    private function authorize(int $branchId): int
    {
        if ($branchId < 1 || ! service('authorization')->can('admission.update', (string) $branchId)) {
            throw new RuntimeException('Forbidden');
        }

        $user = session('user');
        if (! is_array($user) || ! isset($user['id'])) {
            throw new RuntimeException('Authentication required');
        }

        return (int) $user['id'];
    }

    /** @return array<string, mixed> */
    private function findInstallment(object $db, string $type, int $installmentId, bool $lock): array
    {
        if (! isset(self::TABLES[$type]) || $installmentId < 1) {
            throw new RuntimeException('Invalid installment.');
        }

        $table = self::TABLES[$type];
        $sql = 'SELECT i.*, a.admission_number, a.applicant_full_name, a.branch_id, '
            . 'l.full_name AS lead_name, b.name AS branch_name '
            . 'FROM ' . $table . ' i '
            . 'INNER JOIN admissions a ON a.id = i.admission_id '
            . 'INNER JOIN branches b ON b.id = a.branch_id '
            . 'LEFT JOIN leads l ON l.id = a.lead_id '
            . 'WHERE i.id = ? AND i.admission_id = a.id LIMIT 1'
            . ($lock ? ' FOR UPDATE' : '');
        $row = $db->query($sql, [$installmentId])->getRowArray();
        if (! is_array($row)) {
            throw new RuntimeException('Installment not found.');
        }

        return $row;
    }

    /** @return array{amount_received: string, payment_date: string, payment_method: string, receipt_number: string, reference_number: ?string, notes: ?string} */
    private function normalizePayment(array $input): array
    {
        $amount = trim((string) ($input['amount_received'] ?? ''));
        if (! preg_match('/^\d+(?:\.\d{1,2})?$/', $amount) || $this->moneyToCents($amount) <= 0) {
            throw new RuntimeException('Enter a positive payment amount.');
        }

        $paymentDate = $this->normalizeDate($input['payment_date'] ?? null);
        if ($paymentDate === null) {
            throw new RuntimeException('Enter a valid payment date.');
        }

        $paymentMethod = trim((string) ($input['payment_method'] ?? ''));
        if (! in_array($paymentMethod, self::PAYMENT_METHODS, true)) {
            throw new RuntimeException('Select a valid payment method.');
        }

        $receiptNumber = trim((string) ($input['receipt_number'] ?? ''));
        if ($receiptNumber === '') {
            $receiptNumber = 'MCC-RCP-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(3)));
        }
        if (strlen($receiptNumber) > 80) {
            throw new RuntimeException('Receipt number is too long.');
        }

        $reference = trim((string) ($input['reference_number'] ?? ''));
        $notes = trim((string) ($input['notes'] ?? ''));
        if (strlen($reference) > 120 || strlen($notes) > 5000) {
            throw new RuntimeException('Payment details are too long.');
        }

        return [
            'amount_received' => number_format($this->moneyToCents($amount) / 100, 2, '.', ''),
            'payment_date' => $paymentDate,
            'payment_method' => $paymentMethod,
            'receipt_number' => $receiptNumber,
            'reference_number' => $reference === '' ? null : $reference,
            'notes' => $notes === '' ? null : $notes,
        ];
    }

    private function samePayment(array $existing, array $installment, string $type, array $payment): bool
    {
        return (int) ($existing['installment_id'] ?? 0) === (int) $installment['id']
            && (int) ($existing['admission_id'] ?? 0) === (int) $installment['admission_id']
            && (string) ($existing['payment_type'] ?? '') === $type
            && number_format((float) ($existing['amount_received'] ?? 0), 2, '.', '') === $payment['amount_received']
            && (string) ($existing['payment_date'] ?? '') === $payment['payment_date']
            && (string) ($existing['payment_method'] ?? '') === $payment['payment_method']
            && ($existing['reference_number'] ?? null) === $payment['reference_number']
            && ($existing['notes'] ?? null) === $payment['notes'];
    }

    private function normalizeDate(mixed $value): ?string
    {
        if (! is_scalar($value) || trim((string) $value) === '') {
            return null;
        }

        $value = trim((string) $value);
        foreach (['!Y-m-d', '!d-m-Y'] as $format) {
            $date = DateTimeImmutable::createFromFormat(
                $format,
                $value,
                new DateTimeZone(config('App')->appTimezone),
            );
            $errors = DateTimeImmutable::getLastErrors();
            if ($date !== false && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                return $date->format('Y-m-d');
            }
        }

        return null;
    }

    private function moneyToCents(string|int|float $value): int
    {
        $normalized = number_format((float) $value, 2, '.', '');
        [$whole, $fraction] = explode('.', $normalized);

        return ((int) $whole * 100) + (int) $fraction;
    }
}
