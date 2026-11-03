<?php

declare(strict_types=1);

namespace App\Services;

use Config\Database;
use DateTimeImmutable;
use DateTimeZone;

final class AdmissionInstallmentTrackingService
{
    private const TABLES = [
        'coaching' => 'admission_coaching_installments',
        'hostel' => 'admission_hostel_installments',
    ];

    /**
     * @return array{
     *     installments: list<array<string, mixed>>,
     *     total: int,
     *     page: int,
     *     pages: int,
     *     filters: array<string, mixed>,
     *     branches: list<array<string, mixed>>,
     *     summary_counts: array{all: int, upcoming: int, today: int, overdue: int},
     *     payment_status_available: bool
     * }
     */
    public function paginatedForAuthorizedBranches(
        array $filters = [],
        int $page = 1,
        int $perPage = 25,
    ): array {
        $branches = service('branchContext')->authorizedBranches(true);
        $branchIds = array_map(
            static fn (array $branch): int => (int) $branch['id'],
            $branches,
        );
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $rows = $this->queryRows($filters, $branchIds);
        $total = count($rows);
        $summaryCounts = [
            'all' => $total,
            'upcoming' => 0,
            'today' => 0,
            'overdue' => 0,
        ];
        foreach ($rows as $row) {
            $state = (string) ($row['due_state'] ?? '');
            if ($state === 'Upcoming') {
                $summaryCounts['upcoming']++;
            } elseif ($state === 'Due today') {
                $summaryCounts['today']++;
            } elseif ($state === 'Overdue') {
                $summaryCounts['overdue']++;
            }
        }
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);
        $offset = ($page - 1) * $perPage;

        return [
            'installments' => array_slice($rows, $offset, $perPage),
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'filters' => $filters,
            'branches' => $branches,
            'summary_counts' => $summaryCounts,
            'payment_status_available' => true,
        ];
    }

    /** @return list<array<string, mixed>> */
    public function forAdmission(int $admissionId): array
    {
        if ($admissionId < 1) {
            return [];
        }

        $admission = Database::connect('default')->table('admissions')
            ->select('id, branch_id')
            ->where('id', $admissionId)
            ->get()
            ->getRowArray();
        if (! is_array($admission)) {
            return [];
        }

        $authorizedBranchIds = array_map(
            static fn (array $branch): int => (int) $branch['id'],
            service('branchContext')->authorizedBranches(true),
        );
        $branchId = (int) ($admission['branch_id'] ?? 0);
        if ($branchId < 1 || ! in_array($branchId, $authorizedBranchIds, true)) {
            return [];
        }

        return $this->queryRows(
            ['admission_id' => (string) $admissionId],
            [$branchId],
            true,
        );
    }

    /** @param list<int> $branchIds @return list<array<string, mixed>> */
    private function queryRows(array $filters, array $branchIds, bool $includePaymentDetails = false): array
    {
        if ($branchIds === []) {
            return [];
        }

        $selectedType = trim((string) ($filters['installment_type'] ?? ''));
        $types = $selectedType === '' ? array_keys(self::TABLES) : [$selectedType];
        $types = array_values(array_intersect($types, array_keys(self::TABLES)));
        if ($types === []) {
            return [];
        }

        $selectedBranch = trim((string) ($filters['branch_id'] ?? ''));
        if ($selectedBranch !== '') {
            if (! ctype_digit($selectedBranch) || ! in_array((int) $selectedBranch, $branchIds, true)) {
                return [];
            }
            $branchIds = [(int) $selectedBranch];
        }

        $dueFrom = $this->normalizeDate($filters['due_date_from'] ?? null);
        $dueTo = $this->normalizeDate($filters['due_date_to'] ?? null);
        $paymentStatus = trim((string) ($filters['payment_status'] ?? ''));
        $admissionId = trim((string) ($filters['admission_id'] ?? ''));
        $today = (new DateTimeImmutable('now', new DateTimeZone(config('App')->appTimezone)))->format('Y-m-d');
        $hasPaymentsTable = Database::connect('default')->tableExists('admission_installment_payments');
        $rows = [];

        foreach ($types as $type) {
            $table = self::TABLES[$type];
            $paymentTotal = $hasPaymentsTable
                ? ', (SELECT COALESCE(SUM(p.amount_received), 0) FROM admission_installment_payments p '
                    . 'WHERE p.installment_id = i.id AND p.admission_id = i.admission_id '
                    . 'AND p.payment_type = ' . Database::connect('default')->escape($type) . ') AS total_received'
                : ', 0 AS total_received';
            $query = Database::connect('default')
                ->table($table . ' i')
                ->select(
                    'i.id, i.admission_id, i.admission_period_id, i.year_number, i.year_label, '
                    . 'i.installment_number, i.due_date, i.amount, i.updated_at, '
                    . 'a.admission_number, a.applicant_full_name, a.branch_id, '
                    . 'a.installment_plan, a.hostel_installment_plan, '
                    . 'l.full_name AS lead_name, b.name AS branch_name'
                    . $paymentTotal,
                )
                ->join('admissions a', 'a.id = i.admission_id')
                ->join('branches b', 'b.id = a.branch_id')
                ->join('leads l', 'l.id = a.lead_id', 'left')
                ->whereIn('a.branch_id', $branchIds)
                ->whereNotIn('a.status', ['INTAKE', 'PENDING']);

            if ($admissionId !== '' && ctype_digit($admissionId) && (int) $admissionId > 0) {
                $query->where('i.admission_id', (int) $admissionId);
            }

            $query->where('i.due_date IS NOT NULL', null, false);
            $this->applyTextFilters($query, $filters);

            if (($filters['year'] ?? '') !== '' && ctype_digit((string) $filters['year'])) {
                $query->where('i.year_number', (int) $filters['year']);
            }
            if (($filters['installment_number'] ?? '') !== ''
                && ctype_digit((string) $filters['installment_number'])) {
                $query->where('i.installment_number', (int) $filters['installment_number']);
            }
            if ($dueFrom !== null) {
                $query->where('i.due_date >=', $dueFrom);
            }
            if ($dueTo !== null) {
                $query->where('i.due_date <=', $dueTo);
            }
            $this->applyDueStateFilter($query, (string) ($filters['due_state'] ?? ''), $today);

            foreach ($query->orderBy('i.due_date', 'ASC')->orderBy('i.id', 'ASC')->get()->getResultArray() as $row) {
                $row['installment_id'] = (int) ($row['id'] ?? 0);
                if ($row['installment_id'] < 1) {
                    continue;
                }
                $row['installment_type'] = $type;
                $row['applicant_name'] = trim((string) ($row['lead_name'] ?? '')) !== ''
                    ? $row['lead_name']
                    : ($row['applicant_full_name'] ?? 'Applicant');
                $row['payment_plan'] = $type === 'hostel'
                    ? ($row['hostel_installment_plan'] ?? null)
                    : ($row['installment_plan'] ?? null);
                $row['payment_status'] = service('admissionInstallmentPayment')->calculateStatus(
                    (string) ($row['amount'] ?? '0.00'),
                    (string) ($row['total_received'] ?? '0.00'),
                );
                if ($includePaymentDetails && $hasPaymentsTable) {
                    $latestPayment = Database::connect('default')->table('admission_installment_payments')
                        ->where([
                            'installment_id' => $row['installment_id'],
                            'admission_id' => (int) $row['admission_id'],
                            'payment_type' => $type,
                        ])
                        ->orderBy('id', 'DESC')
                        ->get()
                        ->getRowArray();
                    $row['latest_payment_date'] = is_array($latestPayment)
                        ? ($latestPayment['payment_date'] ?? null)
                        : null;
                    $row['latest_payment_receipt'] = is_array($latestPayment)
                        ? ($latestPayment['receipt_number'] ?? null)
                        : null;
                } else {
                    $row['latest_payment_date'] = null;
                    $row['latest_payment_receipt'] = null;
                }
                if ($paymentStatus !== '' && $row['payment_status'] !== $paymentStatus) {
                    continue;
                }
                $row['due_state'] = $this->dueState((string) ($row['due_date'] ?? ''), $today);
                $row['updated_at_display'] = $this->formatUpdatedAt($row['updated_at'] ?? null);
                $rows[] = $row;
            }
        }

        usort($rows, static function (array $left, array $right): int {
            $dateComparison = strcmp((string) $left['due_date'], (string) $right['due_date']);
            if ($dateComparison !== 0) {
                return $dateComparison;
            }

            return ((int) $left['id']) <=> ((int) $right['id']);
        });

        return $rows;
    }

    private function applyTextFilters(object $query, array $filters): void
    {
        $applicant = trim((string) ($filters['applicant'] ?? ''));
        if ($applicant !== '') {
            $query->groupStart()
                ->like('a.applicant_full_name', $applicant)
                ->orLike('l.full_name', $applicant)
                ->groupEnd();
        }

        $admissionNumber = trim((string) ($filters['admission_number'] ?? ''));
        if ($admissionNumber !== '') {
            $query->like('a.admission_number', $admissionNumber);
        }
    }

    private function applyDueStateFilter(object $query, string $state, string $today): void
    {
        if ($state === 'upcoming') {
            $query->where('i.due_date >', $today);
        } elseif ($state === 'today') {
            $query->where('i.due_date', $today);
        } elseif ($state === 'overdue') {
            $query->where('i.due_date <', $today);
        }
    }

    private function dueState(string $dueDate, string $today): string
    {
        if ($dueDate === $today) {
            return 'Due today';
        }

        return $dueDate < $today ? 'Overdue' : 'Upcoming';
    }

    private function formatUpdatedAt(mixed $value): string
    {
        if (! is_scalar($value) || trim((string) $value) === '') {
            return 'Not provided';
        }

        try {
            $timezone = new DateTimeZone(config('App')->appTimezone);
            $date = new DateTimeImmutable(trim((string) $value), $timezone);

            return $date->setTimezone($timezone)->format('d-m-Y, h:i A');
        } catch (\Exception) {
            return 'Not provided';
        }
    }

    private function normalizeDate(mixed $value): ?string
    {
        if (! is_scalar($value) || trim((string) $value) === '') {
            return null;
        }

        $value = trim((string) $value);
        foreach (['!Y-m-d', '!d-m-Y'] as $format) {
            $date = \DateTimeImmutable::createFromFormat(
                $format,
                $value,
                new DateTimeZone(config('App')->appTimezone),
            );
            $errors = \DateTimeImmutable::getLastErrors();
            if ($date !== false && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                return $date->format('Y-m-d');
            }
        }

        return null;
    }
}
