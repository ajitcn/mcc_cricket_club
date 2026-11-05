<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;
use DateTimeImmutable;
use DateTimeZone;

final class DashboardModel extends Model
{
    protected $table = 'users';

    protected $primaryKey = 'id';

    protected $returnType = 'array';

    protected $allowedFields = [];

    /** @param list<int> $branches */
    public function authorizedBranchOptions(array $branches): array
    {
        if ($branches === []) {
            return [];
        }

        return $this->db->table('branches')
            ->select('id, name')
            ->whereIn('id', $branches)
            ->where('is_active', 1)
            ->orderBy('name', 'ASC')
            ->get()
            ->getResultArray();
    }

    /** @param list<int> $branches */
    public function countActiveCoaches(array $branches): int
    {
        if ($branches === []) {
            return 0;
        }

        return (int) $this->db->table('users u')
            ->select('u.id')
            ->join('user_roles ur', 'ur.user_id = u.id')
            ->join('roles r', 'r.id = ur.role_id')
            ->join('user_branches ub', 'ub.user_id = u.id')
            ->join('coach_profiles cp', 'cp.user_id = u.id', 'left')
            ->whereIn('ub.branch_id', $branches)
            ->whereIn('r.name', ['Coach', 'Fitness Coach'])
            ->where('u.is_enabled', 1)
            ->groupStart()
            ->where('cp.is_active', 1)
            ->orWhere('cp.id IS NULL', null, false)
            ->groupEnd()
            ->groupBy('u.id')
            ->get()
            ->getNumRows();
    }

    /** @param list<int> $branches */
    public function coachDirectory(array $branches, int $limit = 8): array
    {
        if ($branches === []) {
            return [];
        }

        return $this->db->table('users u')
            ->select(
                "u.id, u.display_name, "
                . "GROUP_CONCAT(DISTINCT r.name ORDER BY r.name SEPARATOR ', ') AS role_names, "
                . 'cp.specialization, cp.is_active AS profile_active, b.name AS branch_name'
            )
            ->join('user_roles ur', 'ur.user_id = u.id')
            ->join('roles r', 'r.id = ur.role_id')
            ->join('user_branches ub', 'ub.user_id = u.id')
            ->join('branches b', 'b.id = ub.branch_id')
            ->join('coach_profiles cp', 'cp.user_id = u.id', 'left')
            ->whereIn('ub.branch_id', $branches)
            ->whereIn('r.name', ['Coach', 'Fitness Coach'])
            ->where('u.is_enabled', 1)
            ->groupStart()
            ->where('cp.is_active', 1)
            ->orWhere('cp.id IS NULL', null, false)
            ->groupEnd()
            ->groupBy(['u.id', 'u.display_name', 'cp.specialization', 'cp.is_active', 'b.name'])
            ->orderBy('b.name', 'ASC')
            ->orderBy('u.display_name', 'ASC')
            ->limit($limit)
            ->get()
            ->getResultArray();
    }

    /** @param list<int> $branches */
    public function batchDistribution(array $branches): array
    {
        if ($branches === []) {
            return [];
        }

        return $this->db->table('player_batch_assignments pba')
            ->select('pba.batch_id, b.name AS batch_name, br.name AS branch_name, COUNT(DISTINCT p.id) AS total')
            ->join('players p', 'p.id = pba.player_id')
            ->join('batches b', 'b.id = pba.batch_id')
            ->join('branches br', 'br.id = pba.branch_id')
            ->whereIn('pba.branch_id', $branches)
            ->where('p.branch_id = pba.branch_id', null, false)
            ->where('b.branch_id = pba.branch_id', null, false)
            ->where('br.is_active', 1)
            ->where('p.status', 'active')
            ->where('pba.effective_from <=', gmdate('Y-m-d H:i:s'))
            ->groupStart()
            ->where('pba.effective_to IS NULL', null, false)
            ->orWhere('pba.effective_to >', gmdate('Y-m-d H:i:s'))
            ->groupEnd()
            ->groupBy(['pba.batch_id', 'b.name', 'br.name'])
            ->orderBy('br.name', 'ASC')
            ->orderBy('b.name', 'ASC')
            ->get()
            ->getResultArray();
    }

    /** @param list<int> $branches */
    public function ageGroupDistribution(array $branches): array
    {
        if ($branches === []) {
            return [];
        }

        return $this->db->table('players p')
            ->select("COALESCE(NULLIF(ag.name, ''), NULLIF(a.age_group, ''), 'Not assigned') AS age_group, COUNT(DISTINCT p.id) AS total", false)
            ->join('admissions a', 'a.id = p.admission_id', 'left')
            ->join('admission_age_groups ag', 'ag.id = a.age_group_id', 'left')
            ->whereIn('p.branch_id', $branches)
            ->where('p.status', 'active')
            ->groupBy("COALESCE(NULLIF(ag.name, ''), NULLIF(a.age_group, ''), 'Not assigned')", false)
            ->orderBy('age_group', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * Return active Player and assigned Coach totals for each authorized branch.
     * Each identity is counted once within its owning branch.
     *
     * @param list<int> $branches
     * @return list<array{branch_id: int, branch_name: string, players: int, coaches: int}>
     */
    public function branchDistribution(array $branches, ?string $asOfUtc = null): array
    {
        $branchOptions = $this->authorizedBranchOptions($branches);
        $distribution = [];

        foreach ($branchOptions as $branch) {
            $branchId = (int) $branch['id'];
            $players = $this->db->table('players')
                ->where('branch_id', $branchId)
                ->where('status', 'active');

            if ($asOfUtc !== null) {
                $players->where('created_at <=', $asOfUtc);
            }

            $playerTotal = (int) $players->countAllResults();
            $coaches = $this->db->table('users u')
                ->select('u.id')
                ->join('user_roles ur', 'ur.user_id = u.id')
                ->join('roles r', 'r.id = ur.role_id')
                ->join('user_branches ub', 'ub.user_id = u.id')
                ->join('coach_profiles cp', 'cp.user_id = u.id', 'left')
                ->where('ub.branch_id', $branchId)
                ->whereIn('r.name', ['Coach', 'Fitness Coach'])
                ->where('u.is_enabled', 1)
                ->groupStart()
                ->where('cp.is_active', 1)
                ->orWhere('cp.id IS NULL', null, false)
                ->groupEnd()
                ->groupBy('u.id');

            if ($asOfUtc !== null) {
                $coaches->where('u.created_at <=', $asOfUtc);
            }

            $coachTotal = (int) $coaches->get()->getNumRows();

            $distribution[] = [
                'branch_id' => $branchId,
                'branch_name' => (string) $branch['name'],
                'players' => $playerTotal,
                'coaches' => $coachTotal,
            ];
        }

        return $distribution;
    }

    /**
     * Group session roster counts into the dashboard's existing coaching types.
     *
     * @param list<int> $branches
     * @return array{by_branch: array<int, array{group: int, personal: int}>, by_batch: array<string, array{batch_name: string, group: int, personal: int, total: int}>}
     */
    public function coachingDistribution(
        array $branches,
        string $periodStartUtc,
        string $periodEndUtc,
    ): array {
        $result = [
            'by_branch' => [],
            'by_batch' => [],
        ];
        if ($branches === [] || $periodStartUtc >= $periodEndUtc) {
            return $result;
        }

        $sessions = $this->db->table('sessions s')
            ->select('s.id, s.branch_id, s.batch_id, b.name AS batch_name, COUNT(DISTINCT p.id) AS player_total')
            ->join('batches b', 'b.id = s.batch_id', 'left')
            ->join('session_players sp', 'sp.session_id = s.id')
            ->join('players p', 'p.id = sp.player_id')
            ->whereIn('s.branch_id', $branches)
            ->whereIn('s.status', ['scheduled', 'active', 'completed'])
            ->where('s.starts_at >=', $periodStartUtc)
            ->where('s.starts_at <', $periodEndUtc)
            ->groupStart()
            ->where('b.id IS NULL', null, false)
            ->orWhere('b.branch_id = s.branch_id', null, false)
            ->groupEnd()
            ->where('p.branch_id = s.branch_id', null, false)
            ->where('p.status', 'active')
            ->groupBy(['s.id', 's.branch_id', 's.batch_id', 'b.name'])
            ->get()
            ->getResultArray();

        foreach ($sessions as $session) {
            $playerTotal = (int) $session['player_total'];
            if ($playerTotal < 1) {
                continue;
            }
            $type = $playerTotal > 1 ? 'group' : 'personal';
            $branchId = (int) $session['branch_id'];
            $batchKey = (string) ($session['batch_id'] ?? 'unassigned');
            $result['by_branch'][$branchId] ??= ['group' => 0, 'personal' => 0];
            $result['by_branch'][$branchId][$type]++;
            $result['by_batch'][$batchKey] ??= [
                'batch_name' => (string) ($session['batch_name'] ?? 'Batch not assigned'),
                'group' => 0,
                'personal' => 0,
                'total' => 0,
            ];
            $result['by_batch'][$batchKey][$type]++;
            $result['by_batch'][$batchKey]['total']++;
        }

        return $result;
    }

    /**
     * @param list<int> $branches
     * @param list<string>|null $statuses
     */
    public function countBranchRows(string $table, array $branches, ?array $statuses = null): int
    {
        $allowedTables = [
            'leads', 'admissions', 'players', 'sessions', 'trials',
            'matches', 'tournaments', 'development_plans',
        ];

        if ($branches === [] || ! in_array($table, $allowedTables, true)) {
            return 0;
        }

        $query = $this->db->table($table)->whereIn('branch_id', $branches);

        if ($statuses !== null) {
            $query->whereIn('status', $statuses);
        }

        return (int) $query->countAllResults();
    }

    /**
     * @param list<int> $branches
     */
    public function countCreatedSince(string $table, array $branches, string $since): int
    {
        $allowedTables = ['leads', 'admissions', 'players'];

        if ($branches === [] || ! in_array($table, $allowedTables, true)) {
            return 0;
        }

        return (int) $this->db->table($table)
            ->whereIn('branch_id', $branches)
            ->where('created_at >=', $since)
            ->countAllResults();
    }

    /** @param list<int> $branches */
    public function countEnabledUsers(array $branches): int
    {
        if ($branches === []) {
            return 0;
        }

        return (int) $this->db->table('users u')
            ->select('u.id')
            ->join('user_branches ub', 'ub.user_id = u.id')
            ->whereIn('ub.branch_id', $branches)
            ->where('u.is_enabled', 1)
            ->groupBy('u.id')
            ->get()
            ->getNumRows();
    }

    /** @param list<int> $branches */
    public function upcomingRows(string $table, string $dateColumn, array $branches, int $limit = 5): array
    {
        $allowed = ['matches' => 'm', 'tournaments' => 't', 'development_plans' => 'd'];
        if ($branches === [] || ! isset($allowed[$table])) {
            return [];
        }
        $alias = $allowed[$table];
        $timezone = new DateTimeZone(config('App')->appTimezone);
        $today = new DateTimeImmutable('today', $timezone);
        $minimumDate = $table === 'matches' && $dateColumn === 'starts_at'
            ? $today->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s')
            : $today->format('Y-m-d');

        return $this->db->table($table . ' ' . $alias)
            ->whereIn($alias . '.branch_id', $branches)
            ->where($alias . '.' . $dateColumn . ' >=', $minimumDate)
            ->whereNotIn($alias . '.status', ['cancelled', 'completed'])
            ->orderBy($alias . '.' . $dateColumn, 'ASC')
            ->limit($limit)
            ->get()
            ->getResultArray();
    }

    /**
     * @param list<int> $branches
     * @param list<string> $statuses
     * @return array<string, int>
     */
    public function statusSummary(string $table, array $branches, array $statuses): array
    {
        if ($branches === [] || $statuses === []) {
            return [];
        }

        $rows = $this->db->table($table)
            ->select('status, COUNT(*) AS total')
            ->whereIn('branch_id', $branches)
            ->whereIn('status', $statuses)
            ->groupBy('status')
            ->get()
            ->getResultArray();
        $summary = [];

        foreach ($rows as $row) {
            $summary[(string) $row['status']] = (int) $row['total'];
        }

        return $summary;
    }

    /**
     * @param list<int> $branches
     * @return array{pending: int, overdue: int, today: int}
     */
    public function followUpSummary(array $branches, string $now, string $today): array
    {
        if ($branches === []) {
            return ['pending' => 0, 'overdue' => 0, 'today' => 0];
        }

        $base = $this->db->table('lead_follow_ups f')
            ->join('leads l', 'l.id = f.lead_id')
            ->whereIn('l.branch_id', $branches)
            ->where('f.status', 'pending');
        $pending = (int) (clone $base)->countAllResults();
        $overdue = (int) (clone $base)
            ->where('f.follow_up_at <', $now)
            ->where('f.follow_up_at IS NOT NULL', null, false)
            ->countAllResults();
        $todayCount = (int) (clone $base)
            ->where('f.follow_up_at >=', $today . ' 00:00:00')
            ->where('f.follow_up_at <=', $today . ' 23:59:59')
            ->countAllResults();

        return ['pending' => $pending, 'overdue' => $overdue, 'today' => $todayCount];
    }

    /** @param list<int> $branches */
    public function upcomingSessions(array $branches, int $limit = 5): array
    {
        if ($branches === []) {
            return [];
        }

        return $this->db->table('sessions s')
            ->select('s.id,s.starts_at,s.ends_at,s.status,b.name AS branch_name,ba.name AS batch_name,u.display_name AS coach_name')
            ->join('branches b', 'b.id = s.branch_id')
            ->join('batches ba', 'ba.id = s.batch_id')
            ->join('users u', 'u.id = s.coach_user_id')
            ->whereIn('s.branch_id', $branches)
            ->where('s.starts_at >=', gmdate('Y-m-d H:i:s'))
            ->where('s.status !=', 'cancelled')
            ->orderBy('s.starts_at', 'ASC')
            ->limit($limit)
            ->get()->getResultArray();
    }

    /**
     * Return scheduled sessions strictly after the current instant within the
     * selected MCC-local reporting period. Stored session timestamps are UTC.
     *
     * @param list<int> $branches
     */
    public function upcomingSessionsForPeriod(
        array $branches,
        string $periodStartUtc,
        string $periodEndUtc,
        string $nowUtc,
        int $limit = 5
    ): array {
        if ($branches === [] || $periodStartUtc >= $periodEndUtc) {
            return [];
        }

        $fromUtc = $periodStartUtc > $nowUtc ? $periodStartUtc : $nowUtc;

        if ($fromUtc >= $periodEndUtc) {
            return [];
        }

        return $this->db->table('sessions s')
            ->select('s.id,s.starts_at,s.ends_at,s.status,b.name AS branch_name,ba.name AS batch_name,u.display_name AS coach_name')
            ->join('branches b', 'b.id = s.branch_id')
            ->join('batches ba', 'ba.id = s.batch_id')
            ->join('users u', 'u.id = s.coach_user_id')
            ->whereIn('s.branch_id', $branches)
            ->where('s.status', 'scheduled')
            ->where('s.starts_at >=', $fromUtc)
            ->where('s.starts_at <', $periodEndUtc)
            ->orderBy('s.starts_at', 'ASC')
            ->limit($limit)
            ->get()
            ->getResultArray();
    }

    /**
     * Count every qualifying scheduled session in the period. The display list
     * is intentionally limited, but the dashboard KPI must use the full count.
     *
     * @param list<int> $branches
     */
    public function countUpcomingSessionsForPeriod(
        array $branches,
        string $periodStartUtc,
        string $periodEndUtc,
        string $nowUtc
    ): int {
        if ($branches === [] || $periodStartUtc >= $periodEndUtc) {
            return 0;
        }

        $fromUtc = $periodStartUtc > $nowUtc ? $periodStartUtc : $nowUtc;

        if ($fromUtc >= $periodEndUtc) {
            return 0;
        }

        return (int) $this->db->table('sessions s')
            ->whereIn('s.branch_id', $branches)
            ->where('s.status', 'scheduled')
            ->where('s.starts_at >=', $fromUtc)
            ->where('s.starts_at <', $periodEndUtc)
            ->countAllResults();
    }

    /**
     * Count sessions with more than one active roster Player in the period.
     * Session IDs are grouped before counting so roster rows cannot inflate it.
     *
     * @param list<int> $branches
     */
    public function countGroupSessionsForPeriod(
        array $branches,
        string $periodStartUtc,
        string $periodEndUtc
    ): int {
        if ($branches === [] || $periodStartUtc >= $periodEndUtc) {
            return 0;
        }

        return $this->db->table('sessions s')
            ->select('s.id')
            ->join('session_players sp', 'sp.session_id = s.id')
            ->join('players p', 'p.id = sp.player_id')
            ->whereIn('s.branch_id', $branches)
            ->whereIn('s.status', ['scheduled', 'active', 'completed'])
            ->where('s.starts_at >=', $periodStartUtc)
            ->where('s.starts_at <', $periodEndUtc)
            ->where('p.branch_id = s.branch_id', null, false)
            ->where('p.status', 'active')
            ->groupBy('s.id')
            ->having('COUNT(DISTINCT p.id) >', 1, false)
            ->get()
            ->getNumRows();
    }

    /**
     * Count sessions with exactly one active roster Player in the period.
     * This uses roster cardinality only and does not infer a session type.
     *
     * @param list<int> $branches
     */
    public function countPersonalSessionsForPeriod(
        array $branches,
        string $periodStartUtc,
        string $periodEndUtc
    ): int {
        if ($branches === [] || $periodStartUtc >= $periodEndUtc) {
            return 0;
        }

        return $this->db->table('sessions s')
            ->select('s.id')
            ->join('session_players sp', 'sp.session_id = s.id')
            ->join('players p', 'p.id = sp.player_id')
            ->whereIn('s.branch_id', $branches)
            ->whereIn('s.status', ['scheduled', 'active', 'completed'])
            ->where('s.starts_at >=', $periodStartUtc)
            ->where('s.starts_at <', $periodEndUtc)
            ->where('p.branch_id = s.branch_id', null, false)
            ->where('p.status', 'active')
            ->groupBy('s.id')
            ->having('COUNT(DISTINCT p.id) =', 1, false)
            ->get()
            ->getNumRows();
    }

    /** @param list<int> $branches */
    public function upcomingTrials(array $branches, string $today, int $limit = 5): array
    {
        if ($branches === []) {
            return [];
        }

        return $this->db->table('trials t')
            ->select('t.id,t.trial_date,t.trial_time,t.status,t.selection_outcome,l.full_name,b.name AS branch_name')
            ->join('leads l', 'l.id = t.lead_id')
            ->join('branches b', 'b.id = t.branch_id')
            ->whereIn('t.branch_id', $branches)
            ->where('t.trial_date >=', $today)
            ->where('t.status', 'booked')
            ->orderBy('t.trial_date', 'ASC')->orderBy('t.trial_time', 'ASC')
            ->limit($limit)->get()->getResultArray();
    }

    /**
     * @param list<int> $branches
     * @return array{pending: int, attended: int, no_show: int}
     */
    public function trialSummary(array $branches): array
    {
        $summary = $this->statusSummary('trials', $branches, ['booked', 'attended', 'no_show', 'cancelled', 'rescheduled']);

        return [
            'pending' => (int) ($summary['booked'] ?? 0),
            'attended' => (int) ($summary['attended'] ?? 0),
            'no_show' => (int) ($summary['no_show'] ?? 0),
        ];
    }

    /**
     * @param list<int> $branches
     * @return array{recorded: int, present: int, absent: int, late: int, leave: int, medical: int, rain_cancelled: int, coach_cancelled: int}
     */
    public function attendanceSummary(array $branches): array
    {
        $result = [
            'recorded' => 0,
            'present' => 0,
            'absent' => 0,
            'late' => 0,
            'leave' => 0,
            'medical' => 0,
            'rain_cancelled' => 0,
            'coach_cancelled' => 0,
        ];

        if ($branches === []) {
            return $result;
        }

        $rows = $this->db->table('attendance')
            ->select('status, COUNT(*) AS total')
            ->whereIn('branch_id', $branches)->groupBy('status')->get()->getResultArray();

        foreach ($rows as $row) {
            $status = strtolower((string) $row['status']);
            $total = (int) $row['total'];
            $result['recorded'] += $total;

            if (array_key_exists($status, $result)) {
                $result[$status] = $total;
            }
        }

        return $result;
    }

    /** @param list<int> $branches */
    public function recentLeads(array $branches, int $limit = 5): array
    {
        if ($branches === []) {
            return [];
        }

        return $this->db->table('leads l')
            ->select('l.id,l.full_name,l.source,l.status,l.created_at,b.name AS branch_name')
            ->join('branches b', 'b.id = l.branch_id')->whereIn('l.branch_id', $branches)
            ->orderBy('l.created_at', 'DESC')->limit($limit)->get()->getResultArray();
    }

    /** @param list<int> $branches */
    public function recentAdmissions(array $branches, int $limit = 5): array
    {
        if ($branches === []) {
            return [];
        }

        return $this->db->table('admissions a')
            ->select('a.id,a.status,a.updated_at,COALESCE(l.full_name,a.applicant_full_name) AS full_name,b.name AS branch_name')
            ->join('leads l', 'l.id = a.lead_id', 'left')->join('branches b', 'b.id = a.branch_id')
            ->whereIn('a.branch_id', $branches)->orderBy('a.updated_at', 'DESC')->limit($limit)
            ->get()->getResultArray();
    }

    /** @param list<int> $branches */
    public function recentFollowUps(array $branches, int $limit = 5): array
    {
        if ($branches === []) {
            return [];
        }

        return $this->db->table('lead_follow_ups f')
            ->select('f.id,f.follow_up_at,f.status,f.follow_up_type,l.id AS lead_id,l.full_name,b.name AS branch_name')
            ->join('leads l', 'l.id = f.lead_id')->join('branches b', 'b.id = l.branch_id')
            ->whereIn('l.branch_id', $branches)->orderBy('f.created_at', 'DESC')->limit($limit)
            ->get()->getResultArray();
    }

    /** @param list<int> $branches */
    public function overdueFollowUps(array $branches, string $now, int $limit = 5): array
    {
        if ($branches === []) {
            return [];
        }

        return $this->db->table('lead_follow_ups f')
            ->select('f.id,f.follow_up_at,f.follow_up_type,l.id AS lead_id,l.full_name,b.name AS branch_name')
            ->join('leads l', 'l.id = f.lead_id')->join('branches b', 'b.id = l.branch_id')
            ->whereIn('l.branch_id', $branches)->where('f.status', 'pending')
            ->where('f.follow_up_at <', $now)->where('f.follow_up_at IS NOT NULL', null, false)
            ->orderBy('f.follow_up_at', 'ASC')->limit($limit)->get()->getResultArray();
    }

    /** @param list<int> $branches */
    public function trialsAwaitingSelection(array $branches, int $limit = 5): array
    {
        if ($branches === []) {
            return [];
        }

        return $this->db->table('trials t')
            ->select('t.id,t.trial_date,l.full_name,b.name AS branch_name')
            ->join('leads l', 'l.id = t.lead_id')->join('branches b', 'b.id = t.branch_id')
            ->whereIn('t.branch_id', $branches)->where('t.status', 'attended')
            ->where('t.selection_outcome', 'pending')->orderBy('t.trial_date', 'ASC')
            ->limit($limit)->get()->getResultArray();
    }
}
