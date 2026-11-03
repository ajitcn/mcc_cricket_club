<?php

declare(strict_types=1);

namespace App\Services;

use Config\Database;
use DateTimeImmutable;
use RuntimeException;

/** USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL. Published Achievement reports only. */
final class AchievementReportService
{
    /** @return array<string, list<array<string, mixed>>> */
    public function options(): array
    {
        $this->authorize();
        $db = Database::connect('default');
        $branches = $this->authorizedBranches();

        return [
            'branches' => $db->table('branches')
                ->whereIn('id', $branches ?: [0])
                ->orderBy('name', 'ASC')
                ->get()->getResultArray(),
            'players' => $db->table('players p')
                ->select('p.id, pe.full_name, p.branch_id')
                ->join('persons pe', 'pe.id = p.person_id')
                ->whereIn('p.branch_id', $branches ?: [0])
                ->where('p.status', 'active')
                ->orderBy('pe.full_name', 'ASC')
                ->get()->getResultArray(),
            'coaches' => $db->table('users u')
                ->select('u.id, u.display_name, ub.branch_id')
                ->join('user_branches ub', 'ub.user_id = u.id')
                ->join('user_roles ur', 'ur.user_id = u.id')
                ->join('roles r', 'r.id = ur.role_id')
                ->whereIn('ub.branch_id', $branches ?: [0])
                ->where('r.name', 'Coach')
                ->where('u.is_enabled', 1)
                ->groupBy('u.id, u.display_name, ub.branch_id')
                ->orderBy('u.display_name', 'ASC')
                ->get()->getResultArray(),
            'categories' => $db->table('achievements a')
                ->select('a.category')
                ->join('achievement_awards aa', 'aa.achievement_id = a.id')
                ->whereIn('aa.branch_id', $branches ?: [0])
                ->where('a.branch_id = aa.branch_id', null, false)
                ->where('aa.status', 'published')
                ->where('aa.is_archived', 0)
                ->distinct()
                ->orderBy('a.category', 'ASC')
                ->get()->getResultArray(),
        ];
    }

    /** @param array<string, mixed> $filters */
    public function run(array $filters = []): array
    {
        $this->authorize();
        $db = Database::connect('default');
        $branches = $this->selectedBranches($filters);
        $from = $this->dateFilter($filters['from'] ?? '');
        $to = $this->dateFilter($filters['to'] ?? '');
        if ($from !== '' && $to !== '' && $from > $to) {
            throw new RuntimeException('Invalid Achievement report date filter.');
        }

        $builder = $db->table('achievement_awards aa')
            ->select("aa.id, a.title AS achievement, a.category, aa.recipient_type,
                COALESCE(pe.full_name, u.display_name) AS recipient_name,
                b.name AS branch_name, aa.published_at")
            ->join('achievements a', 'a.id = aa.achievement_id')
            ->join('branches b', 'b.id = aa.branch_id')
            ->join('players p', 'p.id = aa.player_id', 'left')
            ->join('persons pe', 'pe.id = p.person_id', 'left')
            ->join('users u', 'u.id = aa.coach_id', 'left')
            ->join('user_branches ub', 'ub.user_id = aa.coach_id AND ub.branch_id = aa.branch_id', 'left', false)
            ->where('aa.status', 'published')
            ->where('aa.is_archived', 0)
            ->whereIn('aa.branch_id', $branches ?: [0])
            ->where('a.branch_id = aa.branch_id', null, false)
            ->where('aa.branch_id = b.id', null, false);
        $builder->where('(aa.player_id IS NULL OR aa.branch_id = p.branch_id)', null, false);
        $builder->where('(aa.coach_id IS NULL OR ub.user_id IS NOT NULL)', null, false);

        $this->applyFilters($builder, $filters, $from, $to);

        return [
            'rows' => $builder->orderBy('aa.published_at', 'DESC')->orderBy('aa.id', 'DESC')->get()->getResultArray(),
            'filters' => $filters,
            'branches' => $branches,
        ];
    }

    /** @param array<string, mixed> $filters */
    public function export(array $filters = []): string
    {
        $result = $this->run($filters);
        $handle = fopen('php://temp', 'r+');
        $headers = ['Achievement', 'Category', 'Recipient type', 'Recipient', 'Branch', 'Published date'];
        fputcsv($handle, $headers);
        foreach ($result['rows'] as $row) {
            fputcsv($handle, [
                $row['achievement'], $row['category'], $row['recipient_type'],
                $row['recipient_name'], $row['branch_name'], $row['published_at'],
            ]);
        }
        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        service('auditPersistence')->record(
            $this->actor(),
            'achievement_report_exported',
            'achievement_report',
            'published_awards',
            count($result['branches']) === 1 ? (int) $result['branches'][0] : null,
            null,
            [
                'row_count' => count($result['rows']),
                'has_branch_filter' => trim((string) ($filters['branch_id'] ?? '')) !== '',
                'has_player_filter' => trim((string) ($filters['player_id'] ?? '')) !== '',
                'has_coach_filter' => trim((string) ($filters['coach_id'] ?? '')) !== '',
                'has_category_filter' => trim((string) ($filters['category'] ?? '')) !== '',
                'has_date_filter' => trim((string) ($filters['from'] ?? '')) !== ''
                    || trim((string) ($filters['to'] ?? '')) !== '',
            ],
            null,
        );

        return $csv;
    }

    private function authorize(): void
    {
        if (! service('authorization')->can('report.view')) {
            throw new RuntimeException('Forbidden');
        }
    }

    /** @return list<int> */
    private function authorizedBranches(): array
    {
        return service('branchContext')->authorizedBranchIds(true);
    }

    /** @param array<string, mixed> $filters @return list<int> */
    private function selectedBranches(array $filters): array
    {
        $authorized = $this->authorizedBranches();
        $requested = trim((string) ($filters['branch_id'] ?? ''));
        if ($requested === '') {
            return $authorized;
        }
        if (! ctype_digit($requested) || ! in_array((int) $requested, $authorized, true)) {
            throw new RuntimeException('Forbidden');
        }

        return [(int) $requested];
    }

    private function dateFilter(mixed $value): string
    {
        $date = trim((string) $value);
        if ($date === '') {
            return '';
        }
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if ($parsed === false || $parsed->format('Y-m-d') !== $date) {
            throw new RuntimeException('Invalid Achievement report date filter.');
        }

        return $date;
    }

    private function applyFilters(object $builder, array $filters, string $from, string $to): void
    {
        if ($from !== '') {
            $builder->where('aa.published_at >=', $from . ' 00:00:00');
        }
        if ($to !== '') {
            $builder->where('aa.published_at <=', $to . ' 23:59:59');
        }
        if (trim((string) ($filters['player_id'] ?? '')) !== '') {
            $builder->where('aa.player_id', $this->positiveId($filters['player_id']));
        }
        if (trim((string) ($filters['coach_id'] ?? '')) !== '') {
            $builder->where('aa.coach_id', $this->positiveId($filters['coach_id']));
        }
        if (trim((string) ($filters['category'] ?? '')) !== '') {
            $builder->where('a.category', trim((string) $filters['category']));
        }
    }

    private function positiveId(mixed $value): int
    {
        $id = filter_var($value, FILTER_VALIDATE_INT);
        if ($id === false || $id < 1) {
            throw new RuntimeException('Invalid Achievement report filter.');
        }

        return (int) $id;
    }

    private function actor(): ?int
    {
        $user = session('user');

        return is_array($user) && isset($user['id']) ? (int) $user['id'] : null;
    }
}
