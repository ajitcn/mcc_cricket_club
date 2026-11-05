<?php
declare(strict_types=1);
namespace App\Modules\Attendance\Models;
use CodeIgniter\Model;
final class AttendanceModel extends Model
{
    protected $table = 'attendance';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = ['session_id', 'player_id', 'branch_id', 'status', 'recorded_by', 'recorded_at', 'version', 'created_at', 'updated_at'];

    public function attendanceRows(
        array $filters,
        array $branchIds,
        int $limit,
        int $offset,
        ?int $coachUserId = null
    ): array {
        if ($branchIds === []) {
            return [];
        }

        return $this->attendanceBuilder($filters, $branchIds, $coachUserId)
            ->orderBy('a.recorded_at', 'DESC')
            ->orderBy('a.id', 'DESC')
            ->limit($limit, $offset)
            ->get()
            ->getResultArray();
    }

    public function countAttendanceRows(
        array $filters,
        array $branchIds,
        ?int $coachUserId = null
    ): int {
        if ($branchIds === []) {
            return 0;
        }

        return (int) $this->attendanceBuilder($filters, $branchIds, $coachUserId)
            ->countAllResults();
    }

    /**
     * Return attendance totals grouped by status for the active filters.
     *
     * @return array<string, int>
     */
    public function attendanceStatusCounts(
        array $filters,
        array $branchIds,
        ?int $coachUserId = null
    ): array {
        if ($branchIds === []) {
            return [];
        }

        $rows = $this->attendanceBuilder($filters, $branchIds, $coachUserId)
            ->select('a.status, COUNT(*) AS total', false)
            ->groupBy('a.status')
            ->get()
            ->getResultArray();

        $counts = [];
        foreach ($rows as $row) {
            $counts[strtoupper((string) $row['status'])] = (int) $row['total'];
        }

        return $counts;
    }

    public function attendanceFilterOptions(array $branchIds, ?int $coachUserId = null): array
    {
        if ($branchIds === []) {
            return ['branches' => [], 'batches' => [], 'coaches' => []];
        }

        $builder = $this->db->table('sessions s')
            ->select('DISTINCT s.branch_id, b.name AS branch_name, s.batch_id, ba.name AS batch_name, s.coach_user_id, cu.display_name AS coach_name', false)
            ->join('branches b', 'b.id = s.branch_id')
            ->join('batches ba', 'ba.id = s.batch_id', 'left')
            ->join('users cu', 'cu.id = s.coach_user_id', 'left')
            ->whereIn('s.branch_id', $branchIds);
        if ($coachUserId !== null) {
            $builder->where('s.coach_user_id', $coachUserId);
        }

        $rows = $builder->orderBy('b.name', 'ASC')->get()->getResultArray();
        $branches = [];
        $batches = [];
        $coaches = [];
        foreach ($rows as $row) {
            $branches[(int) $row['branch_id']] = (string) $row['branch_name'];
            if ($row['batch_id'] !== null) {
                $batches[(int) $row['batch_id']] = (string) $row['batch_name'];
            }
            if ($row['coach_user_id'] !== null) {
                $coaches[(int) $row['coach_user_id']] = (string) $row['coach_name'];
            }
        }

        $batchBuilder = $this->db->table('batches')
            ->select('id, name')
            ->orderBy('name', 'ASC');

        $batchRows = $batchBuilder->get()->getResultArray();
        foreach ($batchRows as $batchRow) {
            $batches[(int) $batchRow['id']] = (string) $batchRow['name'];
        }

        return ['branches' => $branches, 'batches' => $batches, 'coaches' => $coaches];
    }

    private function attendanceBuilder(array $filters, array $branchIds, ?int $coachUserId = null): object
    {
        $builder = $this->db->table('attendance a')
            ->select(
                'a.id, a.status, a.recorded_at, a.updated_at, a.version, '
                . 'a.player_id, pe.full_name AS player_name, s.id AS session_id, '
                . 's.starts_at, s.ends_at, s.status AS session_status, s.batch_id, '
                . 'b.name AS branch_name, ba.name AS batch_name, '
                . 'cu.display_name AS coach_name, ru.display_name AS recorded_by_name'
            )
            ->join('sessions s', 's.id = a.session_id')
            ->join('branches b', 'b.id = a.branch_id AND b.id = s.branch_id')
            ->join('batches ba', 'ba.id = s.batch_id', 'left')
            ->join('players p', 'p.id = a.player_id AND p.branch_id = a.branch_id')
            ->join('persons pe', 'pe.id = p.person_id')
            ->join('users cu', 'cu.id = s.coach_user_id', 'left')
            ->join('users ru', 'ru.id = a.recorded_by', 'left')
            ->whereIn('a.branch_id', $branchIds);

        if ($coachUserId !== null) {
            $builder->where('s.coach_user_id', $coachUserId);
        }
        if (! empty($filters['branch_id'])) {
            $builder->where('a.branch_id', (int) $filters['branch_id']);
        }
        if (! empty($filters['batch_id'])) {
            $builder->where('s.batch_id', (int) $filters['batch_id']);
        }
        if (! empty($filters['coach_user_id'])) {
            $builder->where('s.coach_user_id', (int) $filters['coach_user_id']);
        }
        if (! empty($filters['session_id'])) {
            $builder->where('a.session_id', (int) $filters['session_id']);
        }
        if (! empty($filters['attendance_status'])) {
            $builder->where('a.status', (string) $filters['attendance_status']);
        }
        if (! empty($filters['player'])) {
            $builder->groupStart()
                ->like('pe.full_name', (string) $filters['player'])
                ->orLike('a.player_id', (string) $filters['player'])
                ->groupEnd();
        }
        if (! empty($filters['recorded_from'])) {
            $builder->where('a.recorded_at >=', (string) $filters['recorded_from'] . ' 00:00:00');
        }
        if (! empty($filters['recorded_to'])) {
            $builder->where('a.recorded_at <', date('Y-m-d', strtotime((string) $filters['recorded_to'] . ' +1 day')) . ' 00:00:00');
        }

        return $builder;
    }

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

        return $this->sessionBuilder($filters, $branchIds, true, $coachUserId)
            ->groupBy('s.id')
            ->orderBy('s.starts_at', 'DESC')
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

        return (int) $this->sessionBuilder($filters, $branchIds, false, $coachUserId)
            ->countAllResults();
    }

    public function sessionFilterOptions(array $branchIds, ?int $coachUserId = null): array
    {
        if ($branchIds === []) {
            return [
                'branches' => [],
                'batches' => [],
            ];
        }

        $branchRows = $this->db->table('branches')
            ->select('id, name')
            ->whereIn('id', $branchIds)
            ->where('is_active', 1)
            ->orderBy('name', 'ASC')
            ->get()
            ->getResultArray();

        $rowsQuery = $this->db->table('sessions')
            ->select(
                'DISTINCT sessions.branch_id, b.name AS branch_name, sessions.batch_id, ba.name AS batch_name',
                false
            )
            ->join('branches b', 'b.id = sessions.branch_id')
            ->join('batches ba', 'ba.id = sessions.batch_id', 'left')
            ->whereIn('sessions.branch_id', $branchIds);
        if ($coachUserId !== null) {
            $rowsQuery->where('sessions.coach_user_id', $coachUserId);
        }
        $rows = $rowsQuery
            ->orderBy('b.name', 'ASC')
            ->orderBy('ba.name', 'ASC')
            ->get()
            ->getResultArray();

        $branches = [];
        foreach ($branchRows as $branchRow) {
            $branches[(int) $branchRow['id']] = (string) $branchRow['name'];
        }
        $batches = [];
        $batchesByBranch = [];

        foreach ($rows as $row) {
            if ($row['batch_id'] === null) {
                continue;
            }
            $branchId = (int) $row['branch_id'];
            $batchId = (int) $row['batch_id'];
            $batchName = (string) $row['batch_name'];

            $branches[$branchId] = (string) $row['branch_name'];
            $batches[$batchId] = $batchName;
            $batchesByBranch[$branchId][$batchId] = $batchName;
        }

        return [
            'branches' => $branches,
            'batches' => $batches,
            'batchesByBranch' => $batchesByBranch,
        ];
    }

    private function sessionBuilder(
        array $filters,
        array $branchIds,
        bool $withAggregates,
        ?int $coachUserId = null
    ): object
    {
        $builder = $this->db->table('sessions s')
            ->join('branches b', 'b.id = s.branch_id')
            ->join('batches ba', 'ba.id = s.batch_id', 'left')
            ->join('users u', 'u.id = s.coach_user_id')
            ->join('session_players sp', 'sp.session_id = s.id', 'left')
            ->join(
                'players roster_p',
                'roster_p.id = sp.player_id AND roster_p.branch_id = s.branch_id',
                'left'
            )
            ->join(
                'attendance a',
                'a.session_id = s.id AND a.player_id = sp.player_id AND a.branch_id = s.branch_id',
                'left'
            )
            ->whereIn('s.branch_id', $branchIds);
        if ($coachUserId !== null) {
            $builder->where('s.coach_user_id', $coachUserId);
        }

        if ($withAggregates) {
            $builder->select(
                's.id, s.branch_id, s.batch_id, s.coach_user_id, s.starts_at, '
                . 's.ends_at, s.status, b.name AS branch_name, ba.name AS batch_name, '
                . 'u.display_name AS coach_name, '
                . 'COUNT(DISTINCT roster_p.id) AS roster_count, '
                . 'COUNT(DISTINCT a.id) AS recorded_count'
            );
        } else {
            $builder->select('s.id')->distinct();
        }

        if (isset($filters['branch_id'])) {
            $builder->where('s.branch_id', $filters['branch_id']);
        }
        if (isset($filters['batch_id'])) {
            $builder->where('s.batch_id', $filters['batch_id']);
        }
        if (isset($filters['coach_user_id'])) {
            $builder->where('s.coach_user_id', $filters['coach_user_id']);
        }
        if (isset($filters['session_id'])) {
            $builder->where('s.id', $filters['session_id']);
        }
        if (isset($filters['status'])) {
            $builder->where('s.status', $filters['status']);
        }
        if (isset($filters['date_from'])) {
            $builder->where('s.starts_at >=', $filters['date_from']);
            $builder->where('s.starts_at <', $filters['date_to']);
        }

        if (isset($filters['player']) || isset($filters['attendance_status'])) {
            $builder
                ->join('session_players filter_sp', 'filter_sp.session_id = s.id')
                ->join(
                    'players filter_p',
                    'filter_p.id = filter_sp.player_id AND filter_p.branch_id = s.branch_id'
                )
                ->join('persons filter_pe', 'filter_pe.id = filter_p.person_id')
                ->join(
                    'attendance filter_a',
                    'filter_a.session_id = filter_sp.session_id '
                    . 'AND filter_a.player_id = filter_sp.player_id '
                    . 'AND filter_a.branch_id = s.branch_id',
                    'left'
                );
        }
        if (isset($filters['player'])) {
            $builder->groupStart()
                ->like('filter_pe.full_name', $filters['player'])
                ->orLike('filter_sp.player_id', $filters['player'])
                ->groupEnd();
        }
        if (isset($filters['attendance_status'])) {
            $builder->where('filter_a.status', $filters['attendance_status']);
        }

        return $builder;
    }

    public function sessionHasRosterMatch(int $sessionId, string $playerSearch = '', string $attendanceStatus = ''): bool
    {
        $builder = $this->db->table('session_players sp')
            ->join('players p', 'p.id = sp.player_id')
            ->join('persons pe', 'pe.id = p.person_id')
            ->join('sessions s', 's.id = sp.session_id')
            ->join(
                'attendance a',
                'a.session_id = sp.session_id AND a.player_id = sp.player_id AND a.branch_id = s.branch_id',
                'left'
            )
            ->where('s.branch_id = p.branch_id')
            ->where('sp.session_id', $sessionId);
        if ($playerSearch !== '') {
            $builder->groupStart()->like('pe.full_name', $playerSearch)->orLike('p.id', $playerSearch)->groupEnd();
        }
        if ($attendanceStatus !== '') {
            $builder->where('a.status', $attendanceStatus);
        }
        return $builder->countAllResults() > 0;
    }

    public function sessionRoster(int $sessionId): array
    {
        return $this->db->table('session_players sp')
            ->select('sp.player_id, p.person_id, pe.full_name, a.id AS attendance_id, a.status AS attendance_status, a.version AS attendance_version')
            ->join('players p', 'p.id = sp.player_id')
            ->join('persons pe', 'pe.id = p.person_id')
            ->join('sessions s', 's.id = sp.session_id')
            ->join(
                'attendance a',
                'a.session_id = sp.session_id AND a.player_id = sp.player_id AND a.branch_id = s.branch_id',
                'left'
            )
            ->where('s.branch_id = p.branch_id')
            ->where('sp.session_id', $sessionId)
            ->orderBy('pe.full_name', 'ASC')
            ->get()->getResultArray();
    }

    public function snapshotRoster(int $sessionId, int $branchId): array
    {
        return $this->db->table('attendance_roster_snapshot_members m')
            ->select(
                'm.player_id, p.person_id, pe.full_name, '
                . 'a.id AS attendance_id, a.status AS attendance_status, '
                . 'a.version AS attendance_version, a.updated_at AS attendance_updated_at, '
                . '(SELECT h.reason FROM attendance_history h '
                . 'WHERE h.attendance_id = a.id ORDER BY h.id DESC LIMIT 1) AS attendance_reason'
            )
            ->join('attendance_roster_snapshots s', 's.id = m.snapshot_id')
            ->join('players p', 'p.id = m.player_id')
            ->join('persons pe', 'pe.id = p.person_id')
            ->join(
                'attendance a',
                'a.session_id = s.session_id AND a.player_id = m.player_id AND a.branch_id = s.branch_id',
                'left'
            )
            ->where([
                's.session_id' => $sessionId,
                's.branch_id' => $branchId,
                'm.branch_id' => $branchId,
                'p.branch_id' => $branchId,
            ])
            ->orderBy('pe.full_name', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * Build the attendance candidate list for sessions created without a roster.
     * Legacy session_players rows remain the source for sessions that have them.
     */
    public function eligiblePlayersForSession(array $session, ?int $coachUserId = null): array
    {
        $builder = $this->db->table('players p')
            ->select(
                'p.id AS player_id, p.person_id, pe.full_name, '
                . 'NULL AS attendance_id, NULL AS attendance_status, NULL AS attendance_version'
            )
            ->join('persons pe', 'pe.id = p.person_id')
            ->where([
                'p.branch_id' => (int) $session['branch_id'],
                'p.status' => 'active',
            ]);

        $batchId = $session['batch_id'] ?? null;
        if ($batchId !== null && (int) $batchId > 0) {
            $now = gmdate('Y-m-d H:i:s');
            $builder
                ->join(
                    'player_batch_assignments pba',
                    'pba.player_id = p.id AND pba.effective_from <= '
                    . $this->db->escape($now)
                    . ' AND (pba.effective_to IS NULL OR pba.effective_to > '
                    . $this->db->escape($now) . ')'
                )
                ->where('pba.batch_id', (int) $batchId);
        }

        $coachId = $coachUserId ?? 0;
        if ($coachId > 0) {
            $builder->where(
                'EXISTS (
                    SELECT 1
                    FROM player_coach_assignments pca
                    WHERE pca.player_id = p.id
                      AND pca.branch_id = p.branch_id
                      AND pca.coach_user_id = ' . $coachId . '
                      AND pca.effective_to IS NULL
                )',
                null,
                false
            );
        }

        return $builder
            ->distinct()
            ->orderBy('pe.full_name', 'ASC')
            ->get()
            ->getResultArray();
    }

    public function sessionDetails(int $sessionId): ?array
    {
        $row = $this->db->table('sessions s')
            ->select('s.*, b.name AS branch_name, ba.name AS batch_name, u.display_name AS coach_name')
            ->join('branches b', 'b.id = s.branch_id')
            ->join('batches ba', 'ba.id = s.batch_id', 'left')
            ->join('users u', 'u.id = s.coach_user_id')
            ->where('s.id', $sessionId)
            ->get()->getRowArray();

        return is_array($row) ? $row : null;
    }

    public function attendanceDetails(int $id): ?array
    {
        $row = $this->db->table('attendance a')
            ->select('a.*, s.branch_id AS session_branch, s.starts_at, s.ends_at, s.status AS session_status, b.name AS branch_name, ba.name AS batch_name, pe.full_name AS player_name, ru.display_name AS recorded_by_name, u.display_name AS coach_name')
            ->join('sessions s', 's.id = a.session_id')
            ->join('branches b', 'b.id = a.branch_id')
            ->join('batches ba', 'ba.id = s.batch_id', 'left')
            ->join('players p', 'p.id = a.player_id')
            ->join('persons pe', 'pe.id = p.person_id')
            ->join('users ru', 'ru.id = a.recorded_by')
            ->join('users u', 'u.id = s.coach_user_id')
            ->where('a.id', $id)->get()->getRowArray();
        return is_array($row) ? $row : null;
    }

    public function attendanceHistory(int $id): array
    {
        return $this->db->table('attendance_history h')
            ->select('h.*, u.display_name AS actor_name')
            ->join('attendance a', 'a.id = h.attendance_id')
            ->join('sessions s', 's.id = a.session_id AND a.branch_id = s.branch_id')
            ->join('users u', 'u.id = h.actor_user_id')
            ->where('h.attendance_id', $id)
            ->orderBy('h.id', 'ASC')->get()->getResultArray();
    }
}
