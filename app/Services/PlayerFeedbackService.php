<?php

declare(strict_types=1);

namespace App\Services;

use Config\Database;
use RuntimeException;

final class PlayerFeedbackService
{
    private const VIEW_PERMISSION = 'player.feedback.view';
    private const MANAGE_PERMISSION = 'player.feedback.manage';

    /** @var list<string> */
    private const TEXT_FIELDS = [
        'student_parent_feedback',
        'coach_feedback',
        'next_focus_action',
    ];

    /** @return array<string, mixed> */
    public function list(array $filters = []): array
    {
        $db = Database::connect('default');
        $branches = $this->authorizedBranches(self::VIEW_PERMISSION);
        $filters = array_intersect_key($filters, array_flip([
            'player',
            'player_id',
            'coach_id',
            'branch_id',
            'year',
            'quarter',
            'created_from',
            'created_to',
            'page',
        ]));

        if ($branches === []) {
            return $this->emptyList($filters, [], $db);
        }

        $query = $db->table('player_feedbacks pf')
            ->select('pf.*, pe.full_name AS player_name, b.name AS branch_name, u.display_name AS coach_name')
            ->join('players p', 'p.id = pf.player_id')
            ->join('persons pe', 'pe.id = p.person_id')
            ->join('branches b', 'b.id = pf.branch_id')
            ->join('users u', 'u.id = pf.coach_user_id')
            ->whereIn('pf.branch_id', $branches);

        $this->applyActorScope($query, $db);

        $branchId = $this->validBranchFilter($filters['branch_id'] ?? null, $branches);
        if (($filters['branch_id'] ?? '') !== '' && $branchId === null) {
            return $this->emptyList($filters, $branches, $db);
        }
        if ($branchId !== null) {
            $query->where('pf.branch_id', $branchId);
        }

        $playerId = $this->positiveInt($filters['player_id'] ?? null);
        if (($filters['player_id'] ?? '') !== '' && $playerId === null) {
            return $this->emptyList($filters, $branches, $db);
        }
        if ($playerId !== null) {
            $query->where('pf.player_id', $playerId);
        }

        $playerSearch = trim((string) ($filters['player'] ?? ''));
        if ($playerSearch !== '') {
            $query->groupStart()
                ->like('pe.full_name', $playerSearch)
                ->orLike('pf.player_id', $playerSearch)
                ->groupEnd();
        }

        $coachId = $this->positiveInt($filters['coach_id'] ?? null);
        if (($filters['coach_id'] ?? '') !== '' && ($coachId === null || ! $this->isAuthorizedCoach($db, $coachId, $branches))) {
            return $this->emptyList($filters, $branches, $db);
        }
        if ($coachId !== null) {
            $query->where('pf.coach_user_id', $coachId);
        }

        $year = $this->validYear($filters['year'] ?? null);
        $quarter = $this->validQuarter($filters['quarter'] ?? null);
        if (($filters['year'] ?? '') !== '' && $year === null) {
            return $this->emptyList($filters, $branches, $db);
        }
        if (($filters['quarter'] ?? '') !== '' && $quarter === null) {
            return $this->emptyList($filters, $branches, $db);
        }
        if ($year !== null) {
            $query->where('pf.feedback_year', $year);
        }
        if ($quarter !== null) {
            $query->where('pf.feedback_quarter', $quarter);
        }

        foreach (['created_from' => '>=', 'created_to' => '<='] as $field => $operator) {
            $date = $this->dateFilter($filters[$field] ?? null);
            if (($filters[$field] ?? '') !== '' && $date === null) {
                return $this->emptyList($filters, $branches, $db);
            }
            if ($date !== null) {
                $query->where('pf.created_at ' . $operator, $date . ($field === 'created_from' ? ' 00:00:00' : ' 23:59:59'));
            }
        }

        $total = (clone $query)->countAllResults();
        $perPage = 20;
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($pages, max(1, (int) ($filters['page'] ?? 1)));
        $feedbacks = $query
            ->orderBy('pf.feedback_year', 'DESC')
            ->orderBy('pf.feedback_quarter', 'DESC')
            ->orderBy('pf.id', 'DESC')
            ->get($perPage, ($page - 1) * $perPage)
            ->getResultArray();

        return [
            'feedbacks' => $feedbacks,
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'filters' => $filters,
            'branches' => $this->branchOptions($db, $branches),
            'coaches' => $this->coachOptions($db, $branches),
        ];
    }

    /** @return array<string, mixed> */
    public function playerForCreate(int $playerId): array
    {
        $db = Database::connect('default');
        $player = $db->table('players p')
            ->select('p.id, p.branch_id, pe.full_name AS player_name, b.name AS branch_name')
            ->join('persons pe', 'pe.id = p.person_id')
            ->join('branches b', 'b.id = p.branch_id')
            ->where('p.id', $playerId)
            ->get()
            ->getRowArray();

        if (! is_array($player)) {
            throw new RuntimeException('Not found.');
        }

        $this->assertActor((int) $player['branch_id'], $playerId, true);

        return $player;
    }

    /** @return list<array<string, mixed>> */
    public function searchCreateablePlayers(string $term): array
    {
        $term = trim($term);
        if ($term === '' || strlen($term) > 100) {
            return [];
        }

        $db = Database::connect('default');
        $branches = $this->authorizedBranches(self::MANAGE_PERMISSION);
        if ($branches === []) {
            return [];
        }

        $query = $db->table('players p')
            ->select('p.id, p.branch_id, pe.full_name AS player_name, b.name AS branch_name')
            ->join('persons pe', 'pe.id = p.person_id')
            ->join('branches b', 'b.id = p.branch_id')
            ->whereIn('p.branch_id', $branches)
            ->groupStart()
            ->like('pe.full_name', $term)
            ->orLike('p.id', $term)
            ->groupEnd();

        $actor = (int) (session('user.id') ?? 0);
        $roles = $db->table('user_roles ur')
            ->select('r.name')
            ->join('roles r', 'r.id = ur.role_id')
            ->where('ur.user_id', $actor)
            ->get()
            ->getResultArray();
        $roleNames = array_column($roles, 'name');
        if (array_intersect(['Super Admin', 'Branch Admin'], $roleNames) === []) {
            $query->where("EXISTS (
                SELECT 1
                FROM player_coach_assignments pca
                WHERE pca.player_id = p.id
                AND pca.branch_id = p.branch_id
                AND pca.coach_user_id = " . $actor . "
                AND pca.effective_to IS NULL
            )", null, false);
        }

        return $query
            ->orderBy('pe.full_name', 'ASC')
            ->limit(15)
            ->get()
            ->getResultArray();
    }

    /** @return list<array<string, mixed>> */
    public function reportPlayerOptions(): array
    {
        $db = Database::connect('default');
        $branches = $this->authorizedBranches(self::VIEW_PERMISSION);
        if ($branches === []) {
            return [];
        }

        $query = $db->table('players p')
            ->select('p.id, pe.full_name AS player_name, b.name AS branch_name')
            ->join('persons pe', 'pe.id = p.person_id')
            ->join('branches b', 'b.id = p.branch_id')
            ->whereIn('p.branch_id', $branches);
        $this->applyPlayerActorScope($query, $db);

        return $query
            ->orderBy('pe.full_name', 'ASC')
            ->get()
            ->getResultArray();
    }

    /** @return list<array<string, mixed>> */
    public function searchReportPlayers(string $term): array
    {
        $term = trim($term);
        if ($term === '' || strlen($term) > 100) {
            return [];
        }

        $db = Database::connect('default');
        $branches = $this->authorizedBranches(self::VIEW_PERMISSION);
        if ($branches === []) {
            return [];
        }

        $query = $db->table('players p')
            ->select('p.id, p.branch_id, pe.full_name AS player_name, b.name AS branch_name')
            ->join('persons pe', 'pe.id = p.person_id')
            ->join('branches b', 'b.id = p.branch_id')
            ->whereIn('p.branch_id', $branches)
            ->where('p.status', 'active')
            ->groupStart()
            ->like('pe.full_name', $term)
            ->orLike('p.id', $term)
            ->groupEnd();
        $this->applyPlayerActorScope($query, $db);

        return $query
            ->orderBy('pe.full_name', 'ASC')
            ->limit(15)
            ->get()
            ->getResultArray();
    }

    /** @return array<string, mixed> */
    public function reportPlayerContext(int $playerId): array
    {
        $db = Database::connect('default');
        $player = $db->table('players p')
            ->select('p.id, p.branch_id, pe.full_name AS player_name, b.name AS branch_name')
            ->join('persons pe', 'pe.id = p.person_id')
            ->join('branches b', 'b.id = p.branch_id')
            ->where('p.id', $playerId)
            ->where('p.status', 'active')
            ->get()
            ->getRowArray();

        if (! is_array($player)) {
            throw new RuntimeException('Not found.');
        }

        $this->assertActor((int) $player['branch_id'], $playerId, false);

        return $player;
    }

    public function canViewPlayer(int $playerId): bool
    {
        try {
            $player = Database::connect('default')
                ->table('players')
                ->where('id', $playerId)
                ->get()
                ->getRowArray();

            if (! is_array($player)) {
                return false;
            }

            $this->assertActor((int) $player['branch_id'], $playerId, false);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function canManagePlayer(int $playerId): bool
    {
        try {
            $player = Database::connect('default')
                ->table('players')
                ->where('id', $playerId)
                ->get()
                ->getRowArray();

            if (! is_array($player)) {
                return false;
            }

            $this->assertActor((int) $player['branch_id'], $playerId, true);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /** @return list<array<string, mixed>> */
    public function currentForPlayer(int $playerId): array
    {
        $db = Database::connect('default');
        $player = $db->table('players')->where('id', $playerId)->get()->getRowArray();
        if (! is_array($player)) {
            throw new RuntimeException('Not found.');
        }

        $this->assertActor((int) $player['branch_id'], $playerId, false);

        return $db->table('player_feedbacks pf')
            ->select('pf.*, u.display_name AS coach_name')
            ->join('users u', 'u.id = pf.coach_user_id')
            ->where([
                'pf.player_id' => $playerId,
                'pf.branch_id' => (int) $player['branch_id'],
            ])
            ->orderBy('pf.feedback_year', 'DESC')
            ->orderBy('pf.feedback_quarter', 'DESC')
            ->get()
            ->getResultArray();
    }

    /** @return array<string, mixed> */
    public function feedback(int $id, bool $manage = false): array
    {
        $db = Database::connect('default');
        $feedback = $db->table('player_feedbacks pf')
            ->select('pf.*, pe.full_name AS player_name, b.name AS branch_name, u.display_name AS coach_name')
            ->join('players p', 'p.id = pf.player_id')
            ->join('persons pe', 'pe.id = p.person_id')
            ->join('branches b', 'b.id = pf.branch_id')
            ->join('users u', 'u.id = pf.coach_user_id')
            ->where('pf.id', $id)
            ->get()
            ->getRowArray();

        if (! is_array($feedback)) {
            throw new RuntimeException('Not found.');
        }

        $this->assertActor((int) $feedback['branch_id'], (int) $feedback['player_id'], $manage);
        $feedback['history'] = $this->history($db, $id);

        return $feedback;
    }

    /** @return array<string, mixed> */
    public function save(array $input, ?int $id = null): int
    {
        $db = Database::connect('default');
        $existing = $id === null ? null : $this->feedback($id, true);
        $playerId = $existing === null
            ? $this->positiveInt($input['player_id'] ?? null)
            : (int) $existing['player_id'];
        $year = $existing === null
            ? $this->validYear($input['feedback_year'] ?? null)
            : (int) $existing['feedback_year'];
        $quarter = $existing === null
            ? $this->validQuarter($input['feedback_quarter'] ?? null)
            : (int) $existing['feedback_quarter'];

        if ($playerId === null || $year === null || $quarter === null) {
            throw new RuntimeException('Invalid player feedback.');
        }

        $player = $db->table('players')->where('id', $playerId)->get()->getRowArray();
        if (! is_array($player)) {
            throw new RuntimeException('Player is not eligible.');
        }

        $branchId = (int) $player['branch_id'];
        $actor = $this->assertActor($branchId, $playerId, true);
        $expectedVersion = $existing === null
            ? $this->positiveInt($input['expected_version'] ?? 0, true)
            : $this->positiveInt($input['expected_version'] ?? null);

        if ($expectedVersion === null || ($existing !== null && $expectedVersion !== (int) $existing['version'])) {
            throw new RuntimeException('Feedback was changed by another user.');
        }

        if ($existing !== null && trim((string) ($input['correction_reason'] ?? '')) === '') {
            throw new RuntimeException('Correction reason required.');
        }
        if (strlen(trim((string) ($input['correction_reason'] ?? ''))) > 255) {
            throw new RuntimeException('Correction reason is too long.');
        }

        foreach (self::TEXT_FIELDS as $field) {
            $value = trim((string) ($input[$field] ?? ''));
            if (strlen($value) > 10000) {
                throw new RuntimeException('Invalid player feedback.');
            }
        }

        $data = [
            'player_id' => $playerId,
            'coach_user_id' => $existing === null ? $actor : (int) $existing['coach_user_id'],
            'branch_id' => $branchId,
            'feedback_year' => $year,
            'feedback_quarter' => $quarter,
            'student_parent_feedback' => trim((string) ($input['student_parent_feedback'] ?? '')),
            'coach_feedback' => trim((string) ($input['coach_feedback'] ?? '')),
            'next_focus_action' => trim((string) ($input['next_focus_action'] ?? '')),
        ];
        $now = gmdate('Y-m-d H:i:s');
        $db->transBegin();

        try {
            if ($existing === null) {
                $duplicate = $db->table('player_feedbacks')
                    ->where([
                        'player_id' => $playerId,
                        'feedback_year' => $year,
                        'feedback_quarter' => $quarter,
                    ])
                    ->countAllResults();
                if ($duplicate > 0) {
                    throw new RuntimeException('Feedback already exists for this period.');
                }

                $db->table('player_feedbacks')->insert($data + [
                    'version' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $feedbackId = (int) $db->insertID();
                $event = 'player_feedback_created';
                $before = null;
                $after = $data + ['version' => 1];
            } else {
                $db->table('player_feedback_history')->insert([
                    'feedback_id' => (int) $existing['id'],
                    'version' => (int) $existing['version'],
                    'snapshot' => json_encode($existing, JSON_THROW_ON_ERROR),
                    'correction_reason' => trim((string) $input['correction_reason']),
                    'created_by' => $actor,
                    'created_at' => $now,
                ]);
                $nextVersion = (int) $existing['version'] + 1;
                $updated = $db->table('player_feedbacks')
                    ->where([
                        'id' => (int) $existing['id'],
                        'version' => $expectedVersion,
                        'player_id' => $playerId,
                        'branch_id' => $branchId,
                    ])
                    ->update($data + ['version' => $nextVersion, 'updated_at' => $now]);

                if ($updated !== true || $db->affectedRows() !== 1) {
                    throw new RuntimeException('Feedback was changed by another user.');
                }

                $feedbackId = (int) $existing['id'];
                $event = 'player_feedback_corrected';
                $before = ['version' => $expectedVersion, 'correction_reason' => trim((string) $input['correction_reason'])];
                $after = $data + ['version' => $nextVersion];
            }

            service('auditPersistence')->record(
                $actor,
                $event,
                'player_feedback',
                (string) $feedbackId,
                $branchId,
                $before,
                $after,
                null,
                $db,
            );

            if (! $db->transStatus()) {
                throw new RuntimeException('Unable to save player feedback.');
            }
            $db->transCommit();
        } catch (\Throwable $exception) {
            $db->transRollback();
            throw $exception;
        }

        return $feedbackId;
    }

    /** @return array<string, mixed> */
    public function annual(int $playerId, int $year): array
    {
        $db = Database::connect('default');
        $player = $this->reportPlayerContext($playerId);
        if ($this->validYear($year) === null) {
            throw new RuntimeException('Invalid feedback year.');
        }
        $rows = $db->table('player_feedbacks pf')
            ->select('pf.*, u.display_name AS coach_name')
            ->join('users u', 'u.id = pf.coach_user_id')
            ->where(['pf.player_id' => $playerId, 'pf.feedback_year' => $year])
            ->orderBy('pf.feedback_quarter', 'ASC')
            ->get()
            ->getResultArray();
        $byQuarter = [];
        foreach ($rows as $row) {
            $byQuarter[(int) $row['feedback_quarter']] = $row;
        }
        $quarters = [];
        for ($quarter = 1; $quarter <= 4; $quarter++) {
            $quarters[] = $byQuarter[$quarter] ?? [
                'feedback_quarter' => $quarter,
                'feedback_year' => $year,
                'period_label' => $this->periodLabel($year, $quarter),
                'missing' => true,
            ];
        }

        return ['player' => $player, 'year' => $year, 'quarters' => $quarters];
    }

    public function periodLabel(int $year, int $quarter): string
    {
        return ['Sep', 'Dec', 'Mar', 'Jun'][$quarter - 1] . ' ' . $year . ' Feedback';
    }

    private function assertActor(int $branchId, int $playerId, bool $manage): int
    {
        $permission = $manage ? self::MANAGE_PERMISSION : self::VIEW_PERMISSION;
        if (! service('authorization')->can($permission, (string) $branchId)) {
            throw new RuntimeException('Forbidden');
        }
        $db = Database::connect('default');
        $actor = (int) (session('user.id') ?? 0);
        $roles = $db->table('user_roles ur')->select('r.name')->join('roles r', 'r.id = ur.role_id')->where('ur.user_id', $actor)->get()->getResultArray();
        $roleNames = array_column($roles, 'name');
        if (array_intersect(['Super Admin', 'Branch Admin'], $roleNames) === [] && in_array('Coach', $roleNames, true)) {
            $assigned = $db->table('player_coach_assignments')->where([
                'player_id' => $playerId,
                'branch_id' => $branchId,
                'coach_user_id' => $actor,
            ])->where('effective_to IS NULL', null, false)->countAllResults();
            if ($assigned < 1) {
                throw new RuntimeException('Coach is not assigned to this player.');
            }
        }
        return $actor;
    }

    private function applyActorScope(object $query, object $db): void
    {
        $actor = (int) (session('user.id') ?? 0);
        $roles = $db->table('user_roles ur')->select('r.name')->join('roles r', 'r.id = ur.role_id')->where('ur.user_id', $actor)->get()->getResultArray();
        $roleNames = array_column($roles, 'name');
        if (array_intersect(['Super Admin', 'Branch Admin'], $roleNames) !== []) {
            return;
        }
        if (! in_array('Coach', $roleNames, true)) {
            $query->where('1 = 0', null, false);
            return;
        }
        $query->where(
            "EXISTS (
                SELECT 1
                FROM player_coach_assignments pca
                WHERE pca.player_id = pf.player_id
                AND pca.branch_id = pf.branch_id
                AND pca.coach_user_id = " . $actor . "
                AND pca.effective_to IS NULL
            )",
            null,
            false,
        );
    }

    private function applyPlayerActorScope(object $query, object $db): void
    {
        $actor = (int) (session('user.id') ?? 0);
        $roles = $db->table('user_roles ur')
            ->select('r.name')
            ->join('roles r', 'r.id = ur.role_id')
            ->where('ur.user_id', $actor)
            ->get()
            ->getResultArray();
        $roleNames = array_column($roles, 'name');

        if (array_intersect(['Super Admin', 'Branch Admin'], $roleNames) !== []) {
            return;
        }

        $query->where("EXISTS (
            SELECT 1
            FROM player_coach_assignments report_pca
            WHERE report_pca.player_id = p.id
            AND report_pca.branch_id = p.branch_id
            AND report_pca.coach_user_id = " . $actor . "
            AND report_pca.effective_to IS NULL
        )", null, false);
    }

    private function authorizedBranches(string $permission): array
    {
        return array_values(array_filter(
            service('branchContext')->authorizedBranchIds(true),
            static fn (int $id): bool => service('authorization')->can($permission, (string) $id),
        ));
    }

    private function branchOptions(object $db, array $branches): array
    {
        return $db->table('branches')->whereIn('id', $branches)->orderBy('name')->get()->getResultArray();
    }

    private function coachOptions(object $db, array $branches): array
    {
        return $db->table('user_branches ub')
            ->select('u.id, u.display_name')
            ->join('users u', 'u.id = ub.user_id')
            ->join('user_roles ur', 'ur.user_id = u.id')
            ->join('roles r', 'r.id = ur.role_id')
            ->join('coach_profiles cp', 'cp.user_id = u.id')
            ->whereIn('ub.branch_id', $branches)
            ->where('u.is_enabled', 1)
            ->where('cp.is_active', 1)
            ->where('r.name', 'Coach')
            ->groupBy(['u.id', 'u.display_name'])
            ->orderBy('u.display_name')
            ->get()
            ->getResultArray();
    }

    private function playerOptions(object $db, array $branches): array
    {
        return $db->table('players p')
            ->select('p.id, pe.full_name AS player_name')
            ->join('persons pe', 'pe.id = p.person_id')
            ->whereIn('p.branch_id', $branches)
            ->orderBy('pe.full_name')
            ->get()
            ->getResultArray();
    }

    private function isAuthorizedCoach(object $db, int $coachId, array $branches): bool
    {
        return $db->table('user_branches ub')
            ->join('users u', 'u.id = ub.user_id')
            ->join('user_roles ur', 'ur.user_id = u.id')
            ->join('roles r', 'r.id = ur.role_id')
            ->join('coach_profiles cp', 'cp.user_id = u.id')
            ->where('ub.user_id', $coachId)
            ->whereIn('ub.branch_id', $branches)
            ->where('u.is_enabled', 1)
            ->where('cp.is_active', 1)
            ->where('r.name', 'Coach')
            ->countAllResults() > 0;
    }

    private function history(object $db, int $id): array
    {
        $rows = $db->table('player_feedback_history h')
            ->select('h.*, u.display_name AS created_by_name')
            ->join('users u', 'u.id = h.created_by', 'left')
            ->where('h.feedback_id', $id)
            ->orderBy('h.version', 'DESC')
            ->get()
            ->getResultArray();
        foreach ($rows as &$row) {
            $decoded = json_decode((string) $row['snapshot'], true);
            $row['snapshot_data'] = is_array($decoded) ? $decoded : [];
            unset($row['snapshot']);
        }
        return $rows;
    }

    private function emptyList(array $filters, array $branches, object $db): array
    {
        return [
            'feedbacks' => [],
            'total' => 0,
            'page' => 1,
            'pages' => 1,
            'filters' => $filters,
            'branches' => $branches === [] ? [] : $this->branchOptions($db, $branches),
            'coaches' => $branches === [] ? [] : $this->coachOptions($db, $branches),
        ];
    }

    private function validBranchFilter(mixed $value, array $branches): ?int
    {
        $id = $this->positiveInt($value);
        return $id !== null && in_array($id, $branches, true) ? $id : null;
    }

    private function validYear(mixed $value): ?int
    {
        $year = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 2000, 'max_range' => 2100]]);
        return $year === false ? null : (int) $year;
    }

    private function validQuarter(mixed $value): ?int
    {
        $quarter = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 4]]);
        return $quarter === false ? null : (int) $quarter;
    }

    private function dateFilter(mixed $value): ?string
    {
        $value = trim((string) $value);
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $value !== '' && $date !== false && $date->format('Y-m-d') === $value ? $value : null;
    }

    private function positiveInt(mixed $value, bool $allowZero = false): ?int
    {
        $number = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => $allowZero ? 0 : 1]]);
        return $number === false ? null : (int) $number;
    }
}
