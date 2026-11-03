<?php

declare(strict_types=1);

namespace App\Services;

use Config\Database;
use RuntimeException;

final class PerformanceService
{
    private const TYPES = ['batting', 'bowling', 'fielding'];
    private const METRICS = [
        'runs', 'balls_faced', 'dismissals', 'fours', 'sixes', 'wickets',
        'runs_conceded', 'legal_balls', 'catches', 'stumpings', 'run_outs',
    ];

    /** @return array<string, list<array<string, mixed>>> */
    public function formOptions(): array
    {
        $db = Database::connect('default');
        $branches = $this->branches();
        $players = $db->table('players p')
            ->select('p.id, p.branch_id, pe.full_name, b.name AS branch_name')
            ->join('persons pe', 'pe.id = p.person_id')
            ->join('branches b', 'b.id = p.branch_id')
            ->whereIn('p.branch_id', $branches ?: [0])
            ->where('p.status', 'active')
            ->orderBy('pe.full_name', 'ASC')
            ->get()->getResultArray();
        $sessions = $db->table('sessions s')
            ->select('s.id, s.branch_id, s.starts_at, s.status, b.name AS branch_name')
            ->join('branches b', 'b.id = s.branch_id')
            ->whereIn('s.branch_id', $branches ?: [0])
            ->whereIn('s.status', ['scheduled', 'active', 'completed'])
            ->orderBy('s.starts_at', 'DESC')
            ->limit(200)
            ->get()->getResultArray();

        return [
            'branches' => $db->table('branches')
                ->whereIn('id', $branches ?: [0])
                ->where('is_active', 1)
                ->orderBy('name', 'ASC')
                ->get()->getResultArray(),
            'players' => $players,
            'sessions' => $sessions,
        ];
    }

    public function list(array $filters = []): array
    {
        $branches = $this->branches();
        if ($branches === []) {
            return ['records' => [], 'filters' => $filters];
        }
        $builder = $this->query()->whereIn('r.branch_id', $branches);
        if (in_array('Coach', (array) session('roles'), true)) {
            $actor = $this->actor();
            $builder->join(
                'player_coach_assignments pca',
                'pca.player_id = r.player_id AND pca.branch_id = r.branch_id AND pca.effective_to IS NULL',
                'left'
            );
            $builder->join(
                'sessions cs',
                'cs.id = r.session_id AND cs.branch_id = r.branch_id',
                'left'
            );
            $builder->groupStart()
                ->where('pca.coach_user_id', $actor)
                ->orWhere('cs.coach_user_id', $actor)
                ->groupEnd();
        }
        if (isset($filters['branch_id']) && in_array((int) $filters['branch_id'], $branches, true)) {
            $builder->where('r.branch_id', (int) $filters['branch_id']);
        }
        if (in_array(($filters['record_type'] ?? ''), self::TYPES, true)) {
            $builder->where('r.record_type', $filters['record_type']);
        }
        if (($q = trim((string) ($filters['q'] ?? ''))) !== '') {
            $builder->groupStart()->like('pe.full_name', $q)->orLike('r.player_id', $q)->groupEnd();
        }
        if (($from = trim((string) ($filters['from'] ?? ''))) !== '') {
            $builder->where('r.recorded_on >=', $from);
        }
        if (($to = trim((string) ($filters['to'] ?? ''))) !== '') {
            $builder->where('r.recorded_on <=', $to);
        }
        return ['records' => $builder->orderBy('r.recorded_on', 'DESC')->orderBy('r.id', 'DESC')->get()->getResultArray(), 'filters' => $filters];
    }

    public function find(int $id): array
    {
        $row = $this->query()->where('r.id', $id)->get()->getRowArray();
        if (!is_array($row)) {
            throw new RuntimeException('Not found.');
        }
        $this->authorize((int) $row['branch_id'], 'performance.view', (int) $row['player_id'], (int) ($row['session_id'] ?? 0));
        return $row;
    }

    public function save(array $input, ?int $id = null): int
    {
        $db = Database::connect('default');
        $existing = $id === null ? null : $this->findForWrite($id);
        $branch = (int) ($input['branch_id'] ?? ($existing['branch_id'] ?? 0));
        $player = (int) ($input['player_id'] ?? ($existing['player_id'] ?? 0));
        $session = ($input['session_id'] ?? ($existing['session_id'] ?? null));
        $session = $session === '' || $session === null ? null : (int) $session;
        $this->authorize($branch, $id === null ? 'performance.create' : 'performance.update', $player, $session ?? 0);
        if (!$this->playerInBranch($player, $branch)) {
            throw new RuntimeException('Invalid player.');
        }
        if ($session !== null && !$this->sessionEligible($session, $player, $branch)) {
            throw new RuntimeException('Invalid session.');
        }
        $type = (string) ($input['record_type'] ?? ($existing['record_type'] ?? ''));
        $date = trim((string) ($input['recorded_on'] ?? ($existing['recorded_on'] ?? '')));
        if (!in_array($type, self::TYPES, true) || !$this->validDate($date)) {
            throw new RuntimeException('Invalid performance record.');
        }
        $correctionReason = trim((string) ($input['correction_reason'] ?? ''));
        if ($id !== null && $correctionReason === '') {
            throw new RuntimeException('A correction reason is required.');
        }
        $data = ['branch_id' => $branch, 'player_id' => $player, 'session_id' => $session, 'record_type' => $type, 'recorded_on' => $date, 'notes' => trim((string) ($input['notes'] ?? '')) ?: null];
        foreach (self::METRICS as $metric) {
            $value = $input[$metric] ?? ($existing[$metric] ?? null);
            if ($value === '' || $value === null) {
                $data[$metric] = null;
                continue;
            }
            if (filter_var($value, FILTER_VALIDATE_INT) === false || (int) $value < 0) {
                throw new RuntimeException('Invalid performance metric.');
            }
            $data[$metric] = (int) $value;
        }
        $now = gmdate('Y-m-d H:i:s');
        if ($id === null) {
            $data += ['recorded_by' => $this->actor(), 'created_at' => $now, 'updated_at' => $now];
            $db->table('performance_records')->insert($data);
            $id = (int) $db->insertID();
            $event = 'performance_record_created';
        } else {
            $data['approved'] = (int) ($existing['approved'] ?? 0);
            $data['approved_by'] = $existing['approved_by'] ?? null;
            $data['approved_at'] = $existing['approved_at'] ?? null;
            $db->table('performance_records')->where('id', $id)->update($data + ['updated_at' => $now]);
            $event = 'performance_record_updated';
        }
        service('auditPersistence')->record(
            $this->actor(),
            $event,
            'performance_record',
            (string) $id,
            $branch,
            $existing,
            $data,
            $id !== null && $event === 'performance_record_updated' ? $correctionReason : null
        );
        return $id;
    }

    public function approve(int $id, string $reason = ''): void
    {
        $row = $this->findForWrite($id);
        $branch = (int) $row['branch_id'];
        $actor = $this->actor();
        $this->authorize($branch, 'performance.update', (int) $row['player_id'], (int) ($row['session_id'] ?? 0));
        if ((int) ($row['approved'] ?? 0) === 1) {
            throw new RuntimeException('Performance record is already approved.');
        }

        $now = gmdate('Y-m-d H:i:s');
        $after = [
            'approved' => 1,
            'approved_by' => $actor,
            'approved_at' => $now,
            'updated_at' => $now,
        ];
        $db = Database::connect('default');
        $db->transStart();
        $db->table('performance_records')->where('id', $id)->update($after);
        service('auditPersistence')->record(
            $actor,
            'performance_record_approved',
            'performance_record',
            (string) $id,
            $branch,
            $row,
            $after,
            trim($reason) !== '' ? trim($reason) : null,
            $db
        );
        $db->transComplete();
        if ($db->transStatus() === false) {
            throw new RuntimeException('Unable to approve performance record.');
        }
    }

    public function coachImprovementIndex(int $coachUserId): ?float
    {
        $branches = array_values(array_filter(
            $this->branches(),
            fn(int $branch): bool => service('authorization')->can('coach.kpi.view', (string) $branch)
        ));
        if ($branches === []) {
            return null;
        }

        $builder = $this->query()
            ->select('r.*')
            ->join(
                'player_coach_assignments pca',
                'pca.player_id = r.player_id AND pca.branch_id = r.branch_id AND pca.effective_to IS NULL',
                'left'
            )
            ->join(
                'sessions cs',
                'cs.id = r.session_id AND cs.branch_id = r.branch_id',
                'left'
            )
            ->where('r.approved', 1)
            ->whereIn('r.branch_id', $branches)
            ->groupStart()
            ->where('pca.coach_user_id', $coachUserId)
            ->orWhere('cs.coach_user_id', $coachUserId)
            ->groupEnd()
            ->groupBy('r.id')
            ->orderBy('r.recorded_on', 'ASC')
            ->orderBy('r.id', 'ASC');
        $rows = $builder->get()->getResultArray();
        if ($rows === []) {
            return null;
        }

        $comparisons = [];
        foreach ($rows as $row) {
            $skill = (string) ($row['record_type'] ?? '');
            $playerId = (int) ($row['player_id'] ?? 0);
            foreach (self::METRICS as $metric) {
                $value = $row[$metric] ?? null;
                if (! is_numeric($value) || (float) $value < 0) {
                    continue;
                }
                $key = $playerId . ':' . $skill . ':' . $metric;
                $comparisons[$key][] = (float) $value;
            }
        }

        $improvements = [];
        foreach ($comparisons as $values) {
            if (count($values) < 2) {
                continue;
            }
            $baseline = (float) $values[0];
            $latest = (float) $values[count($values) - 1];
            if ($baseline == 0.0) {
                continue;
            }
            $improvements[] = (($latest - $baseline) / abs($baseline)) * 100;
        }

        return $improvements === [] ? null : round(array_sum($improvements) / count($improvements), 2);
    }

    public function delete(int $id): void
    {
        $row = $this->findForWrite($id);
        $this->authorize((int) $row['branch_id'], 'performance.delete', (int) $row['player_id'], (int) ($row['session_id'] ?? 0));
        Database::connect('default')->table('performance_records')->where('id', $id)->update(['deleted_at' => gmdate('Y-m-d H:i:s'), 'updated_at' => gmdate('Y-m-d H:i:s')]);
        service('auditPersistence')->record($this->actor(), 'performance_record_deleted', 'performance_record', (string) $id, (int) $row['branch_id']);
    }

    public function player(int $playerId): array
    {
        $player = Database::connect('default')->table('players')->where('id', $playerId)->get()->getRowArray();
        if (!is_array($player)) {
            throw new RuntimeException('Not found.');
        }
        $this->authorize((int) $player['branch_id'], 'performance.view', $playerId);
        $rows = $this->query()->where(['r.player_id' => $playerId, 'r.branch_id' => $player['branch_id']])->orderBy('r.recorded_on', 'DESC')->get()->getResultArray();
        return ['rows' => $rows, 'kpis' => $this->kpis($rows)];
    }

    private function kpis(array $rows): array
    {
        $batting = array_filter($rows, static fn(array $row): bool => $row['record_type'] === 'batting');
        $bowling = array_filter($rows, static fn(array $row): bool => $row['record_type'] === 'bowling');
        $sum = static fn(array $items, string $field): int => array_sum(array_map(static fn(array $row): int => (int) ($row[$field] ?? 0), $items));
        $runs = $sum($batting, 'runs');
        $balls = $sum($batting, 'balls_faced');
        $dismissals = $sum($batting, 'dismissals');
        $legalBalls = $sum($bowling, 'legal_balls');
        $conceded = $sum($bowling, 'runs_conceded');
        return ['batting_records' => count($batting), 'runs' => $runs, 'balls_faced' => $balls, 'strike_rate' => $balls > 0 ? round($runs * 100 / $balls, 2) : null, 'average' => $dismissals > 0 ? round($runs / $dismissals, 2) : null, 'highest_score' => $batting === [] ? null : max(array_map(static fn(array $row): int => (int) ($row['runs'] ?? 0), $batting)), 'fours' => $sum($batting, 'fours'), 'sixes' => $sum($batting, 'sixes'), 'wickets' => $sum($bowling, 'wickets'), 'economy' => $legalBalls > 0 ? round($conceded * 6 / $legalBalls, 2) : null, 'catches' => $sum($rows, 'catches'), 'stumpings' => $sum($rows, 'stumpings'), 'run_outs' => $sum($rows, 'run_outs')];
    }

    private function query(): object { return Database::connect('default')->table('performance_records r')->select('r.*, pe.full_name AS player_name, u.display_name AS recorded_by_name')->join('players p', 'p.id = r.player_id')->join('persons pe', 'pe.id = p.person_id')->join('users u', 'u.id = r.recorded_by')->where('r.deleted_at', null); }
    private function findForWrite(int $id): array { $row = $this->query()->where('r.id', $id)->get()->getRowArray(); if (!is_array($row)) { throw new RuntimeException('Not found.'); } return $row; }
    private function branches(): array { return service('branchContext')->authorizedBranchIds(true); }
    private function actor(): int { $user = session('user'); if (!is_array($user) || !isset($user['id'])) { throw new RuntimeException('Authentication required'); } return (int) $user['id']; }
    private function playerInBranch(int $player, int $branch): bool { return Database::connect('default')->table('players')->where(['id' => $player, 'branch_id' => $branch])->countAllResults() > 0; }
    private function sessionEligible(int $session, int $player, int $branch): bool { return Database::connect('default')->table('sessions s')->join('session_players sp', 'sp.session_id = s.id')->where(['s.id' => $session, 's.branch_id' => $branch, 'sp.player_id' => $player])->countAllResults() > 0; }
    private function authorize(int $branch, string $permission, int $player, int $session = 0): void
    {
        if ($branch < 1 || !service('authorization')->can($permission, (string) $branch)) { throw new RuntimeException('Forbidden'); }
        $roles = (array) session('roles');
        if (in_array('Coach', $roles, true)) {
            $actor = $this->actor();
            $assigned = Database::connect('default')->table('player_coach_assignments')->where(['player_id' => $player, 'branch_id' => $branch, 'coach_user_id' => $actor, 'effective_to' => null])->countAllResults() > 0;
            $sessionCoach = $session > 0 && Database::connect('default')->table('sessions')->where(['id' => $session, 'branch_id' => $branch, 'coach_user_id' => $actor])->countAllResults() > 0;
            if (!$assigned && !$sessionCoach) { throw new RuntimeException('Forbidden'); }
        }
    }
    private function validDate(string $date): bool { $parsed = date_create_from_format('!Y-m-d', $date); return $parsed !== false && $parsed->format('Y-m-d') === $date; }
}
