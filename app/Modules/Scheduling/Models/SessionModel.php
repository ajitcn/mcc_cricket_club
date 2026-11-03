<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Models;

use CodeIgniter\Model;

final class SessionModel extends Model
{
    protected $table = 'sessions';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = ['branch_id', 'program_id', 'batch_id', 'age_group_id', 'coach_user_id', 'ground_id', 'starts_at', 'ends_at', 'status', 'session_type', 'description', 'cancellation_reason', 'cancelled_by', 'cancelled_at', 'rescheduled_from_id', 'created_by', 'created_at', 'updated_at'];

    public function sessionRows(
        array $filters,
        array $branchIds,
        int $limit,
        int $offset,
        ?int $coachUserId = null
    ): array
    {
        if ($branchIds === []) {
            return [];
        }

        return $this->sessionBuilder($filters, $branchIds, $coachUserId)
            ->orderBy('s.starts_at', 'DESC')
            ->orderBy('s.id', 'DESC')
            ->limit($limit, $offset)
            ->get()
            ->getResultArray();
    }

    public function countSessionRows(
        array $filters,
        array $branchIds,
        ?int $coachUserId = null
    ): int
    {
        if ($branchIds === []) {
            return 0;
        }

        return (int) $this->sessionBuilder($filters, $branchIds, $coachUserId)
            ->countAllResults();
    }

    public function sessionFilterOptions(array $branchIds, ?int $coachUserId = null): array
    {
        if ($branchIds === []) {
            return [
                'branches' => [],
                'batches' => [],
                'coaches' => [],
                'grounds' => [],
            ];
        }

        $branchRows = $this->db->table('branches')
            ->select('id, name')
            ->whereIn('id', $branchIds)
            ->where('is_active', 1)
            ->orderBy('name', 'ASC')
            ->get()
            ->getResultArray();

        $rowsQuery = $this->db->table('sessions s')
            ->distinct()
            ->select('s.branch_id, b.name AS branch_name, s.batch_id, ba.name AS batch_name, s.age_group_id, ag.name AS age_group_name, s.coach_user_id, u.display_name AS coach_name, s.ground_id, g.name AS ground_name')
            ->join('branches b', 'b.id = s.branch_id')
            ->join('batches ba', 'ba.id = s.batch_id', 'left')
            ->join('admission_age_groups ag', 'ag.id = s.age_group_id', 'left')
            ->join('users u', 'u.id = s.coach_user_id')
            ->join('grounds g', 'g.id = s.ground_id', 'left')
            ->whereIn('s.branch_id', $branchIds);
        if ($coachUserId !== null) {
            $rowsQuery->where('s.coach_user_id', $coachUserId);
        }
        $rows = $rowsQuery
            ->orderBy('b.name', 'ASC')
            ->orderBy('ba.name', 'ASC')
            ->orderBy('u.display_name', 'ASC')
            ->get()
            ->getResultArray();

        $options = [
            'branches' => [],
            'batches' => [],
            'coaches' => [],
            'grounds' => [],
        ];
        foreach ($branchRows as $branchRow) {
            $options['branches'][(int) $branchRow['id']] = (string) $branchRow['name'];
        }
        foreach ($rows as $row) {
            $options['branches'][(int) $row['branch_id']] = (string) $row['branch_name'];
            if ($row['batch_id'] !== null) {
                $options['batches'][(int) $row['batch_id']] = (string) $row['batch_name'];
            }
            $options['coaches'][(int) $row['coach_user_id']] = (string) $row['coach_name'];
            if ($row['ground_id'] !== null) {
                $options['grounds'][(int) $row['ground_id']] = (string) $row['ground_name'];
            }
        }

        asort($options['batches']);
        asort($options['coaches']);
        asort($options['grounds']);

        return $options;
    }

    private function sessionBuilder(
        array $filters,
        array $branchIds,
        ?int $coachUserId = null
    ): object
    {
        $builder = $this->db->table('sessions s')
            ->select(
                's.*, '
                . 'b.name AS branch_name, '
                . 'ba.name AS batch_name, '
                . 'ag.name AS age_group_name, '
                . 'p.name AS program_name, '
                . 'CASE WHEN ub.user_id IS NULL THEN NULL ELSE u.display_name END AS coach_name, '
                . 'g.name AS ground_name, '
                . '(SELECT COUNT(DISTINCT sp.player_id) '
                . 'FROM session_players sp '
                . 'INNER JOIN players lp ON lp.id = sp.player_id '
                . 'WHERE sp.session_id = s.id '
                . 'AND lp.branch_id = s.branch_id) AS selected_player_count',
                false
            )
            ->join('branches b', 'b.id = s.branch_id')
            ->join('batches ba', 'ba.id = s.batch_id', 'left')
            ->join('admission_age_groups ag', 'ag.id = s.age_group_id', 'left')
            ->join('programs p', 'p.id = ba.program_id', 'left')
            ->join('users u', 'u.id = s.coach_user_id', 'left')
            ->join(
                'user_branches ub',
                'ub.user_id = s.coach_user_id AND ub.branch_id = s.branch_id',
                'left'
            )
            ->join('grounds g', 'g.id = s.ground_id', 'left')
            ->whereIn('s.branch_id', $branchIds);
        if ($coachUserId !== null) {
            $builder->where('s.coach_user_id', $coachUserId);
        }

        foreach (['branch_id', 'batch_id', 'coach_user_id', 'ground_id', 'status'] as $key) {
            if (isset($filters[$key])) {
                $builder->where('s.' . $key, $filters[$key]);
            }
        }
        if (isset($filters['date_from'], $filters['date_to'])) {
            $builder->where('s.starts_at >=', $filters['date_from']);
            $builder->where('s.starts_at <', $filters['date_to']);
        }
        if (isset($filters['q'])) {
            $builder->groupStart()
                ->like('s.id', $filters['q'])
                ->orLike('s.batch_id', $filters['q'])
                ->orLike('s.coach_user_id', $filters['q'])
                ->orLike('b.name', $filters['q'])
                ->orLike('ba.name', $filters['q'])
                ->orLike('u.display_name', $filters['q'])
                ->orLike('g.name', $filters['q'])
                ->groupEnd();
        }

        return $builder;
    }
}
