<?php

declare(strict_types=1);

namespace App\Services;

use App\Modules\CRM\Models\LeadSourceModel;
use App\Modules\CRM\Models\LeadStatusModel;
use Config\Database;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

final class MarketingDashboardService
{
    public function dashboard(array $filters = []): array
    {
        $this->authorize();

        $db = Database::connect('default');
        $branches = $this->authorizedBranches($filters);
        $filters = $this->normalizeFilters($filters, $branches);

        return [
            'filters' => $filters,
            'branches' => $this->branchOptions($db, $branches),
            'filter_options' => $this->filterOptions($db, $branches),
            'kpis' => $this->kpis($db, $branches, $filters),
            'lead_source_performance' => $this->groupLeads($db, $branches, $filters, 'source'),
            'lead_status_summary' => $this->groupLeads($db, $branches, $filters, 'status'),
            'admission_funnel' => $this->groupAdmissions($db, $branches, $filters),
            'trial_summary' => $this->groupTrials($db, $branches, $filters),
            'branch_comparison' => $this->branchComparison($db, $branches, $filters),
            'monthly_trends' => $this->monthlyTrends($db, $branches, $filters),
            'unavailable' => [
                'campaign_attribution' => 'Not available',
                'roi' => 'Not available',
                'cost_per_admission' => 'Not available',
                'conversion_rate' => 'Not available',
            ],
        ];
    }

    private function authorize(): void
    {
        if (! service('authorization')->can('marketing.view')) {
            throw new RuntimeException('Forbidden');
        }
    }

    /** @return list<int> */
    private function authorizedBranches(array $filters): array
    {
        $available = array_values(array_filter(array_map(
            'intval',
            service('branchContext')->authorizedBranchIds(true)
        )));
        $requested = (int) ($filters['branch_id'] ?? 0);

        if ($requested > 0) {
            if (! in_array($requested, $available, true)) {
                throw new RuntimeException('Forbidden');
            }

            return [$requested];
        }

        return $available;
    }

    private function normalizeFilters(array $filters, array $branches): array
    {
        $dateRange = trim((string) ($filters['date_range'] ?? ''));
        $from = trim((string) ($filters['from'] ?? ''));
        $to = trim((string) ($filters['to'] ?? ''));

        if ($dateRange === '') {
            $dateRange = $from !== '' || $to !== '' ? 'custom' : 'all_time';
        }

        [$from, $to] = $this->resolveDateRange($dateRange, $from, $to);

        $legacyStatus = trim((string) ($filters['status'] ?? ''));

        return [
            'branch_id' => count($branches) === 1 ? (string) $branches[0] : (string) ($filters['branch_id'] ?? ''),
            'date_range' => $dateRange,
            'from' => $from,
            'to' => $to,
            'source' => trim((string) ($filters['source'] ?? '')),
            'lead_status' => trim((string) ($filters['lead_status'] ?? $legacyStatus)),
            'admission_status' => trim((string) ($filters['admission_status'] ?? $legacyStatus)),
            'status' => $legacyStatus,
            'q' => trim((string) ($filters['q'] ?? '')),
        ];
    }

    /** @return array{0: string, 1: string} */
    private function resolveDateRange(string $dateRange, string $from, string $to): array
    {
        $allowed = ['all_time', 'today', 'this_week', 'this_month', 'last_month', 'custom'];
        if (! in_array($dateRange, $allowed, true)) {
            throw new RuntimeException('Invalid date filter.');
        }

        if ($dateRange === 'custom') {
            if ($from === '' || $to === '' || ! $this->validDate($from) || ! $this->validDate($to) || $from > $to) {
                throw new RuntimeException('Invalid date filter.');
            }

            return [$from, $to];
        }

        if ($dateRange === 'all_time') {
            return ['', ''];
        }

        $today = new DateTimeImmutable('today', $this->applicationTimezone());

        return match ($dateRange) {
            'today' => [$today->format('Y-m-d'), $today->format('Y-m-d')],
            'this_week' => [
                $today->modify('monday this week')->format('Y-m-d'),
                $today->modify('sunday this week')->format('Y-m-d'),
            ],
            'this_month' => [
                $today->modify('first day of this month')->format('Y-m-d'),
                $today->modify('last day of this month')->format('Y-m-d'),
            ],
            'last_month' => [
                $today->modify('first day of last month')->format('Y-m-d'),
                $today->modify('last day of last month')->format('Y-m-d'),
            ],
        };
    }

    private function validDate(string $value): bool
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value;
    }

    private function applicationTimezone(): DateTimeZone
    {
        return new DateTimeZone(config('App')->appTimezone ?: 'Asia/Kolkata');
    }

    /** @return array{0: string|null, 1: string|null} */
    private function dateBounds(array $filters): array
    {
        if ($filters['from'] === '' || $filters['to'] === '') {
            return [null, null];
        }

        $timezone = $this->applicationTimezone();
        $from = new DateTimeImmutable($filters['from'] . ' 00:00:00', $timezone);
        $to = new DateTimeImmutable($filters['to'] . ' 00:00:00', $timezone);

        return [
            $from->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
            $to->modify('+1 day')->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
        ];
    }

    private function branchOptions(object $db, array $branches): array
    {
        if ($branches === []) {
            return [];
        }

        return $db->table('branches')
            ->select('id, name, code')
            ->whereIn('id', $branches)
            ->orderBy('name')
            ->get()
            ->getResultArray();
    }

    /** @return array{sources: list<string>, lead_statuses: list<string>, admission_statuses: list<string>} */
    private function filterOptions(object $db, array $branches): array
    {
        return [
            'sources' => $this->mergeOptions(
                array_column((new LeadSourceModel())->select('name')->findAll(), 'name'),
                $this->distinctBranchValues($db, 'leads', 'source', $branches)
            ),
            'lead_statuses' => $this->mergeOptions(
                array_column((new LeadStatusModel())->select('name')->findAll(), 'name'),
                $this->distinctBranchValues($db, 'leads', 'status', $branches)
            ),
            'admission_statuses' => $this->mergeOptions(
                service('admission')->statuses(),
                $this->distinctBranchValues($db, 'admissions', 'status', $branches)
            ),
        ];
    }

    /** @return list<string> */
    private function distinctBranchValues(object $db, string $table, string $column, array $branches): array
    {
        if ($branches === []) {
            return [];
        }

        $rows = $db->table($table)
            ->select($column)
            ->whereIn('branch_id', $branches)
            ->where($column . ' !=', '')
            ->distinct()
            ->orderBy($column)
            ->get()
            ->getResultArray();

        return array_column($rows, $column);
    }

    /** @param list<string> ...$groups @return list<string> */
    private function mergeOptions(array ...$groups): array
    {
        $options = [];
        foreach ($groups as $group) {
            foreach ($group as $value) {
                $value = trim((string) $value);
                if ($value !== '') {
                    $options[$value] = $value;
                }
            }
        }

        natcasesort($options);

        return array_values($options);
    }

    private function kpis(object $db, array $branches, array $filters): array
    {
        return [
            'leads' => $this->count($this->leadQuery($db, $branches, $filters)),
            'admissions' => $this->count($this->admissionQuery($db, $branches, $filters)),
            'trials' => $this->count($this->trialQuery($db, $branches, $filters)),
            'converted_admissions' => $this->count(
                $this->admissionQuery($db, $branches, $filters)->where('a.status', 'CONVERTED')
            ),
        ];
    }

    private function count(object $query): int
    {
        return (int) $query->countAllResults();
    }

    private function groupLeads(object $db, array $branches, array $filters, string $field): array
    {
        $query = $this->leadQuery($db, $branches, $filters)
            ->select('l.' . $field . ' AS label, COUNT(*) AS total', false)
            ->groupBy('l.' . $field)
            ->orderBy('total', 'DESC');

        return $query->get()->getResultArray();
    }

    private function groupAdmissions(object $db, array $branches, array $filters): array
    {
        return $this->admissionQuery($db, $branches, $filters)
            ->select('a.status AS label, COUNT(*) AS total', false)
            ->groupBy('a.status')
            ->orderBy('a.status')
            ->get()
            ->getResultArray();
    }

    private function groupTrials(object $db, array $branches, array $filters): array
    {
        return $this->trialQuery($db, $branches, $filters)
            ->select('t.status AS label, COUNT(*) AS total', false)
            ->groupBy('t.status')
            ->orderBy('t.status')
            ->get()
            ->getResultArray();
    }

    private function branchComparison(object $db, array $branches, array $filters): array
    {
        if ($branches === []) {
            return [];
        }

        $rows = [];
        foreach ($this->branchOptions($db, $branches) as $branch) {
            $branchId = (int) $branch['id'];
            $branchFilters = $filters;
            $branchFilters['branch_id'] = (string) $branchId;
            $rows[] = [
                'branch_id' => $branchId,
                'branch_name' => $branch['name'],
                'leads' => $this->count($this->leadQuery($db, [$branchId], $branchFilters)),
                'admissions' => $this->count($this->admissionQuery($db, [$branchId], $branchFilters)),
                'trials' => $this->count($this->trialQuery($db, [$branchId], $branchFilters)),
            ];
        }

        return $rows;
    }

    private function monthlyTrends(object $db, array $branches, array $filters): array
    {
        $leadRows = $this->leadQuery($db, $branches, $filters)
            ->select("DATE_FORMAT(l.created_at, '%Y-%m') AS month, COUNT(*) AS leads", false)
            ->groupBy("DATE_FORMAT(l.created_at, '%Y-%m')")
            ->get()
            ->getResultArray();
        $admissionRows = $this->admissionQuery($db, $branches, $filters)
            ->select("DATE_FORMAT(a.created_at, '%Y-%m') AS month, COUNT(*) AS admissions", false)
            ->groupBy("DATE_FORMAT(a.created_at, '%Y-%m')")
            ->get()
            ->getResultArray();
        $months = [];

        foreach (array_merge($leadRows, $admissionRows) as $row) {
            $month = (string) $row['month'];
            $months[$month] ??= ['month' => $month, 'leads' => 0, 'admissions' => 0];
            $months[$month]['leads'] += (int) ($row['leads'] ?? 0);
            $months[$month]['admissions'] += (int) ($row['admissions'] ?? 0);
        }

        ksort($months);

        return array_values($months);
    }

    private function leadQuery(object $db, array $branches, array $filters): object
    {
        $query = $db->table('leads l')->whereIn('l.branch_id', $branches ?: [0]);
        $this->applyCommonFilters($query, 'l.created_at', $filters);

        if ($filters['source'] !== '') {
            $query->where('l.source', $filters['source']);
        }

        if ($filters['lead_status'] !== '') {
            $query->where('l.status', $filters['lead_status']);
        }

        if ($filters['q'] !== '') {
            $query->groupStart()
                ->like('l.full_name', $filters['q'])
                ->orLike('l.email', $filters['q'])
                ->orLike('l.phone', $filters['q'])
                ->groupEnd();
        }

        return $query;
    }

    private function admissionQuery(object $db, array $branches, array $filters): object
    {
        $query = $db->table('admissions a')->whereIn('a.branch_id', $branches ?: [0]);
        $this->applyCommonFilters($query, 'a.created_at', $filters);

        if ($filters['admission_status'] !== '') {
            $query->where('a.status', $filters['admission_status']);
        }

        return $query;
    }

    private function trialQuery(object $db, array $branches, array $filters): object
    {
        $query = $db->table('trials t')->whereIn('t.branch_id', $branches ?: [0]);
        $this->applyCommonFilters($query, 't.created_at', $filters);

        if ($filters['status'] !== '') {
            $query->where('t.status', $filters['status']);
        }

        return $query;
    }

    private function applyCommonFilters(object $query, string $column, array $filters): void
    {
        [$from, $toExclusive] = $this->dateBounds($filters);

        if ($from !== null) {
            $query->where($column . ' >=', $from);
        }

        if ($toExclusive !== null) {
            $query->where($column . ' <', $toExclusive);
        }
    }
}
