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
                'select' => "a.admission_number, pe.full_name, pe.email, pe.phone, p.status, b.name AS branch_name,
                    COALESCE(NULLIF(ag.name, ''), NULLIF(a.age_group, '')) AS age_group,
                    (SELECT ba.name FROM player_batch_assignments pba
                        INNER JOIN batches ba ON ba.id = pba.batch_id
                        WHERE pba.player_id = p.id AND pba.effective_to IS NULL
                        ORDER BY pba.effective_from DESC LIMIT 1) AS batch_name,
                    (SELECT u.display_name FROM users u WHERE u.id = a.coach_user_id LIMIT 1) AS group_coach_name,
                    (SELECT u.display_name FROM users u WHERE u.id = a.personal_coach_user_id LIMIT 1) AS personal_coach_name,
                    (SELECT COUNT(*) FROM player_contacts pc WHERE pc.player_id = p.id) AS contact_count,
                    p.created_at, p.updated_at",
                'joins' => [
                    ['persons pe', 'pe.id = p.person_id'],
                    ['branches b', 'b.id = p.branch_id'],
                    ['admissions a', 'a.id = p.admission_id', 'left'],
                    ['admission_age_groups ag', 'ag.id = a.age_group_id', 'left'],
                ],
                'date' => 'p.created_at',
                'alias' => 'p',
            ],
            'admissions' => [
                'table' => 'admissions a',
                'select' => 'a.admission_number, a.applicant_full_name AS full_name, a.applicant_email, a.applicant_phone, a.parent_mobile, a.gender, a.age, a.age_group, a.playing_role, a.status, b.name AS branch_name, ba.name AS batch_name, a.coaching_type, cg.display_name AS group_coach_name, cp.display_name AS personal_coach_name, a.admission_date, a.joining_date, a.total_taxable_fee, a.tax_rate, a.calculated_tax_amount, a.total_including_tax, a.installment_plan, a.hostel_payment_plan, a.created_at, a.updated_at',
                'joins' => [
                    ['branches b', 'b.id = a.branch_id'],
                    ['batches ba', 'ba.id = a.batch_id', 'left'],
                    ['users cg', 'cg.id = a.coach_user_id', 'left'],
                    ['users cp', 'cp.id = a.personal_coach_user_id', 'left'],
                ],
                'date' => 'a.created_at',
                'alias' => 'a',
            ],
            'attendance' => [
                'table' => 'attendance a',
                'select' => 'ad.admission_number, pe.full_name AS player_name, b.name AS branch_name, ba.name AS batch_name, ag.name AS age_group_name, g.name AS ground_name, u.display_name AS coach_name, ru.display_name AS recorded_by_name, s.session_type, s.description AS session_notes, s.status AS session_status, s.starts_at, s.ends_at, a.status, a.recorded_at',
                'joins' => [
                    ['sessions s', 's.id = a.session_id AND a.branch_id = s.branch_id'],
                    ['players p', 'p.id = a.player_id'],
                    ['persons pe', 'pe.id = p.person_id'],
                    ['admissions ad', 'ad.id = p.admission_id', 'left'],
                    ['branches b', 'b.id = s.branch_id'],
                    ['batches ba', 'ba.id = s.batch_id', 'left'],
                    ['admission_age_groups ag', 'ag.id = s.age_group_id', 'left'],
                    ['grounds g', 'g.id = s.ground_id AND g.branch_id = s.branch_id', 'left'],
                    ['users u', 'u.id = s.coach_user_id', 'left'],
                    ['users ru', 'ru.id = a.recorded_by', 'left'],
                ],
                'date' => 'a.recorded_at',
                'alias' => 'a',
            ],
            'sessions' => [
                'table' => 'sessions s',
                'select' => 'b.name AS branch_name, ba.name AS batch_name, u.display_name AS coach_name, s.session_type, s.status, s.description, s.starts_at, s.ends_at',
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
                'select' => 'cm.display_name AS coach_name, b.name AS branch_name, g.name AS ground_name, m.your_team, m.opponent, m.match_category, m.status, m.result, m.score_summary, m.coach_remarks, m.starts_at, m.ends_at',
                'joins' => [
                    ['branches b', 'b.id = m.branch_id'],
                    ['grounds g', 'g.id = m.ground_id AND g.branch_id = m.branch_id', 'left'],
                    ['users cm', 'cm.id = m.coach_user_id', 'left'],
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
