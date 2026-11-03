<?php

declare(strict_types=1);

namespace App\Services;

use Config\Database;
use RuntimeException;

final class ReportService
{
    private const CATALOG = [
        'players' => 'Players',
        'admissions' => 'Admissions',
        'attendance' => 'Attendance',
        'sessions' => 'Sessions',
        'matches' => 'Matches',
    ];

    public function catalog(): array
    {
        $this->authorize();

        return self::CATALOG;
    }

    public function run(string $type, array $filters = []): array
    {
        if (! isset(self::CATALOG[$type])) {
            throw new RuntimeException('Invalid report.');
        }

        $this->authorize();
        $db = Database::connect('default');
        $branches = $this->branches($filters);
        $from = trim((string) ($filters['from'] ?? ''));
        $to = trim((string) ($filters['to'] ?? ''));

        if (($from !== '' && ! $this->validDate($from))
            || ($to !== '' && ! $this->validDate($to))
            || ($from !== '' && $to !== '' && $from > $to)
        ) {
            throw new RuntimeException('Invalid report date filter.');
        }
        $queries = [
            'players' => [
                'table' => 'players p',
                'select' => 'p.id, pe.full_name, p.status, b.name AS branch_name, p.created_at',
                'joins' => [
                    ['persons pe', 'pe.id = p.person_id'],
                    ['branches b', 'b.id = p.branch_id'],
                ],
                'date' => 'p.created_at',
                'alias' => 'p',
            ],
            'admissions' => [
                'table' => 'admissions a',
                'select' => 'a.id, a.admission_number, a.applicant_full_name AS full_name, a.status, b.name AS branch_name, a.created_at',
                'joins' => [
                    ['branches b', 'b.id = a.branch_id'],
                ],
                'date' => 'a.created_at',
                'alias' => 'a',
            ],
            'attendance' => [
                'table' => 'attendance a',
                'select' => 'a.id, pe.full_name AS player_name, b.name AS branch_name, s.starts_at, a.status, a.recorded_at',
                'joins' => [
                    ['sessions s', 's.id = a.session_id AND a.branch_id = s.branch_id'],
                    ['players p', 'p.id = a.player_id'],
                    ['persons pe', 'pe.id = p.person_id'],
                    ['branches b', 'b.id = s.branch_id'],
                ],
                'date' => 'a.recorded_at',
                'alias' => 'a',
            ],
            'sessions' => [
                'table' => 'sessions s',
                'select' => 's.id, b.name AS branch_name, ba.name AS batch_name, u.display_name AS coach_name, s.status, s.starts_at, s.ends_at',
                'joins' => [
                    ['branches b', 'b.id = s.branch_id'],
                    ['batches ba', 'ba.id = s.batch_id', 'left'],
                    ['users u', 'u.id = s.coach_user_id', 'left'],
                ],
                'date' => 's.starts_at',
                'alias' => 's',
            ],
            'matches' => [
                'table' => 'matches m',
                'select' => 'm.id, b.name AS branch_name, m.opponent, m.status, m.result, m.starts_at',
                'joins' => [
                    ['branches b', 'b.id = m.branch_id'],
                ],
                'date' => 'm.starts_at',
                'alias' => 'm',
            ],
        ];
        $definition = $queries[$type];
        $builder = $db->table($definition['table'])->select($definition['select']);

        foreach ($definition['joins'] as $join) {
            $builder->join($join[0], $join[1], $join[2] ?? 'inner');
        }

        $branchColumn = $type === 'attendance'
            ? 's.branch_id'
            : $definition['alias'] . '.branch_id';
        $builder->whereIn($branchColumn, $branches ?: [0]);

        if ($from !== '') {
            $builder->where($definition['date'] . ' >=', $from . ' 00:00:00');
        }
        if ($to !== '') {
            $builder->where($definition['date'] . ' <=', $to . ' 23:59:59');
        }
        if (($filters['status'] ?? '') !== '') {
            $builder->where($definition['alias'] . '.status', $filters['status']);
        }
        if (($filters['player_id'] ?? '') !== '' && in_array($type, ['attendance', 'players'], true)) {
            $playerColumn = $type === 'attendance' ? 'a.player_id' : 'p.id';
            $builder->where($playerColumn, (int) $filters['player_id']);
        }

        return [
            'type' => $type,
            'title' => self::CATALOG[$type],
            'rows' => $builder->orderBy($definition['date'], 'DESC')->get()->getResultArray(),
            'filters' => $filters,
        ];
    }

    private function validDate(string $value): bool
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value;
    }

    private function authorize(): void
    {
        if (! service('authorization')->can('report.view')) {
            throw new RuntimeException('Forbidden');
        }
    }

    /** @return list<int> */
    private function branches(array $filters): array
    {
        $authorized = array_values(array_filter(array_map(
            'intval',
            service('branchContext')->authorizedBranchIds(true)
        )));
        $requested = trim((string) ($filters['branch_id'] ?? ''));

        if ($requested === '') {
            return $authorized;
        }
        if (! ctype_digit($requested) || ! in_array((int) $requested, $authorized, true)) {
            throw new RuntimeException('Forbidden');
        }

        return [(int) $requested];
    }
}
