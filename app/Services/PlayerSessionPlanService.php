<?php

declare(strict_types=1);

namespace App\Services;

use Config\Database;
use RuntimeException;

final class PlayerSessionPlanService
{
    private const VIEW_PERMISSION = 'player.session_plan.view';
    private const MANAGE_PERMISSION = 'player.session_plan.manage';

    /** @var list<string> */
    public const COACHING_TOOLS = [
        'Instruct',
        'Demonstrate',
        'Observe',
        'Question',
        'Analyse',
        'Feedback',
        'Use Silence',
        'Praise',
    ];

    /** @var list<string> */
    public const COACHING_FOCUS = [
        'Technical',
        'Tactical',
        'Mental',
        'Physical',
    ];

    /** @var list<string> */
    private const TEXT_FIELDS = [
        'problem',
        'outcome',
        'method',
        'stage',
        'area',
        'coaching_focus',
        'safety_considerations',
        'technical',
        'tactical',
        'mental',
        'physical',
        'level_1_fixed_variable',
        'level_1_coaching_tools',
        'level_1_equipment',
        'level_1_easier',
        'level_1_harder',
        'level_2_fixed_variable',
        'level_2_coaching_tools',
        'level_2_equipment',
        'level_2_easier',
        'level_2_harder',
        'debrief_fixed_variable',
        'debrief_coaching_tools',
        'debrief_equipment',
        'debrief_easier',
        'debrief_harder',
    ];

    /** @return array<string, mixed> */
    public function list(array $filters = []): array
    {
        $db = Database::connect('default');
        $branches = $this->authorizedBranches(self::VIEW_PERMISSION);
        $filters = array_intersect_key($filters, array_flip([
            'player',
            'coach_id',
            'branch_id',
            'created_from',
            'created_to',
            'page',
        ]));

        if ($branches === []) {
            return $this->emptyList($filters, [], $db);
        }

        $query = $db->table('player_session_plans sp')
            ->select('sp.*, pe.full_name AS player_name, b.name AS branch_name, u.display_name AS coach_name')
            ->join('players p', 'p.id = sp.player_id')
            ->join('persons pe', 'pe.id = p.person_id')
            ->join('branches b', 'b.id = sp.branch_id')
            ->join('users u', 'u.id = sp.coach_user_id')
            ->whereIn('sp.branch_id', $branches);

        $this->applyActorScope($query, $db);

        $branchId = $this->validBranchFilter($filters['branch_id'] ?? null, $branches);
        if (($filters['branch_id'] ?? '') !== '' && $branchId === null) {
            return $this->emptyList($filters, $branches, $db);
        }
        if ($branchId !== null) {
            $query->where('sp.branch_id', $branchId);
        }

        $playerFilter = trim((string) ($filters['player'] ?? ''));
        if ($playerFilter !== '') {
            $query->groupStart()
                ->like('pe.full_name', $playerFilter)
                ->orLike('sp.player_id', $playerFilter)
                ->groupEnd();
        }

        $coachFilter = trim((string) ($filters['coach_id'] ?? ''));
        if ($coachFilter !== '') {
            $coachId = filter_var($coachFilter, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($coachId === false || ! $this->isAuthorizedCoach($db, (int) $coachId, $branches)) {
                return $this->emptyList($filters, $branches, $db);
            }
            $query->where('sp.coach_user_id', $coachId);
        }

        foreach (['created_from' => '>=', 'created_to' => '<='] as $field => $operator) {
            $date = $this->dateFilter($filters[$field] ?? null);
            if (($filters[$field] ?? '') !== '' && $date === null) {
                return $this->emptyList($filters, $branches, $db);
            }
            if ($date !== null) {
                $query->where('sp.created_at ' . $operator, $date . ($field === 'created_from' ? ' 00:00:00' : ' 23:59:59'));
            }
        }

        $total = (clone $query)->countAllResults();
        $perPage = 20;
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($pages, max(1, (int) ($filters['page'] ?? 1)));
        $plans = $query
            ->orderBy('sp.updated_at', 'DESC')
            ->orderBy('sp.id', 'DESC')
            ->get($perPage, ($page - 1) * $perPage)
            ->getResultArray();

        return [
            'plans' => $plans,
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'filters' => $filters,
            'branches' => $this->branchOptions($db, $branches),
            'coaches' => $this->coachOptions($db, $branches),
        ];
    }

    /** @return array<string, mixed> */
    public function plan(int $id, bool $manage = false): array
    {
        $db = Database::connect('default');
        $plan = $db->table('player_session_plans sp')
            ->select('sp.*, pe.full_name AS player_name, b.name AS branch_name, u.display_name AS coach_name')
            ->join('players p', 'p.id = sp.player_id')
            ->join('persons pe', 'pe.id = p.person_id')
            ->join('branches b', 'b.id = sp.branch_id')
            ->join('users u', 'u.id = sp.coach_user_id')
            ->where('sp.id', $id)
            ->get()
            ->getRowArray();

        if (! is_array($plan)) {
            throw new RuntimeException('Not found.');
        }

        $this->assertActor((int) $plan['branch_id'], (int) $plan['player_id'], $manage);
        $plan['history'] = $this->history($db, $id);

        return $plan;
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

        return $db->table('player_session_plans sp')
            ->select('sp.*, u.display_name AS coach_name')
            ->join('users u', 'u.id = sp.coach_user_id')
            ->where([
                'sp.player_id' => $playerId,
                'sp.branch_id' => (int) $player['branch_id'],
            ])
            ->orderBy('sp.updated_at', 'DESC')
            ->orderBy('sp.id', 'DESC')
            ->get()
            ->getResultArray();
    }

    /** @return array<string, mixed> */
    public function playerForCreate(int $playerId): array
    {
        $db = Database::connect('default');
        $player = $db->table('players p')
            ->select("p.id, p.branch_id, pe.full_name AS player_name, b.name AS branch_name,
                (SELECT u.display_name
                 FROM users u
                 INNER JOIN user_branches ub ON ub.user_id = u.id
                 INNER JOIN user_roles ur ON ur.user_id = u.id
                 INNER JOIN roles r ON r.id = ur.role_id
                 WHERE u.id = a.personal_coach_user_id
                 AND ub.branch_id = p.branch_id
                 AND r.name IN ('Coach', 'Fitness Coach')
                 LIMIT 1) AS personal_coach_name,
                (SELECT u.display_name
                 FROM users u
                 INNER JOIN user_branches ub ON ub.user_id = u.id
                 INNER JOIN user_roles ur ON ur.user_id = u.id
                 INNER JOIN roles r ON r.id = ur.role_id
                 WHERE u.id = a.coach_user_id
                 AND ub.branch_id = p.branch_id
                 AND r.name IN ('Coach', 'Fitness Coach')
                 LIMIT 1) AS group_coach_name")
            ->join('persons pe', 'pe.id = p.person_id')
            ->join('branches b', 'b.id = p.branch_id')
            ->join('admissions a', 'a.id = p.admission_id', 'left')
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
    public function createablePlayers(): array
    {
        $db = Database::connect('default');
        $branches = $this->authorizedBranches(self::MANAGE_PERMISSION);

        if ($branches === []) {
            return [];
        }

        $query = $db->table('players p')
            ->select('p.id, p.branch_id, pe.full_name AS player_name, b.name AS branch_name')
            ->join('persons pe', 'pe.id = p.person_id')
            ->join('branches b', 'b.id = p.branch_id')
            ->whereIn('p.branch_id', $branches);

        $actor = (int) (session('user.id') ?? 0);
        $roles = $db->table('user_roles ur')
            ->select('r.name')
            ->join('roles r', 'r.id = ur.role_id')
            ->where('ur.user_id', $actor)
            ->get()
            ->getResultArray();
        $roleNames = array_column($roles, 'name');

        if (array_intersect(['Super Admin', 'Branch Admin'], $roleNames) === []) {
            if (! in_array('Coach', $roleNames, true)) {
                return [];
            }

            $query->where("EXISTS (
                SELECT 1 FROM player_coach_assignments create_scope_pca
                WHERE create_scope_pca.player_id = p.id
                AND create_scope_pca.branch_id = p.branch_id
                AND create_scope_pca.coach_user_id = " . $actor . "
                AND create_scope_pca.effective_to IS NULL
            )", null, false);
        }

        return $query
            ->orderBy('pe.full_name', 'ASC')
            ->get()
            ->getResultArray();
    }

    /** @return list<array<string, int|string|null>> */
    public function searchCreateablePlayers(string $term): array
    {
        $term = trim($term);
        if ($term === '') {
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
            if (! in_array('Coach', $roleNames, true)) {
                return [];
            }

            $query->where("EXISTS (
                SELECT 1 FROM player_coach_assignments search_scope_pca
                WHERE search_scope_pca.player_id = p.id
                AND search_scope_pca.branch_id = p.branch_id
                AND search_scope_pca.coach_user_id = " . $actor . "
                AND search_scope_pca.effective_to IS NULL
            )", null, false);
        }

        return $query
            ->orderBy('pe.full_name', 'ASC')
            ->orderBy('p.id', 'ASC')
            ->get(10)
            ->getResultArray();
    }

    public function save(array $input, ?int $id = null): int
    {
        $db = Database::connect('default');
        $existing = $id === null ? null : $this->plan($id, true);
        $playerId = $existing === null
            ? $this->positiveInt($input['player_id'] ?? null)
            : (int) $existing['player_id'];

        if ($playerId === null) {
            throw new RuntimeException('Invalid player session plan.');
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

        if ($expectedVersion === null) {
            throw new RuntimeException('A current session plan version is required.');
        }
        if ($existing !== null && $expectedVersion !== (int) $existing['version']) {
            throw new RuntimeException('Session plan was changed by another user.');
        }

        $data = [
            'player_id' => $playerId,
            'coach_user_id' => $actor,
            'branch_id' => $branchId,
            'players' => $existing === null ? '' : (string) ($existing['players'] ?? ''),
        ];
        foreach (self::TEXT_FIELDS as $field) {
            if ($field === 'coaching_focus') {
                if (! array_key_exists($field, $input)) {
                    $data[$field] = $existing === null ? '' : (string) ($existing[$field] ?? '');
                    continue;
                }

                $data[$field] = $this->validatedCoachingFocus($input[$field]);
                continue;
            }

            if (in_array($field, $this->coachingToolFields(), true)) {
                if (! array_key_exists($field, $input)) {
                    $data[$field] = $existing === null ? '' : (string) ($existing[$field] ?? '');
                    continue;
                }

                $data[$field] = $this->validatedCoachingTools($input[$field]);
                continue;
            }

            $value = array_key_exists($field, $input)
                ? trim((string) $input[$field])
                : (string) ($existing[$field] ?? '');
            if (strlen($value) > 10000) {
                throw new RuntimeException('Invalid player session plan.');
            }
            $data[$field] = $value;
        }

        if ($existing !== null) {
            $data['coach_user_id'] = (int) $existing['coach_user_id'];
        }

        if ($data['problem'] === '' || $data['outcome'] === '' || $data['method'] === '') {
            throw new RuntimeException('Invalid player session plan.');
        }

        $now = gmdate('Y-m-d H:i:s');
        $db->transBegin();

        try {
            if ($existing === null) {
                $db->table('player_session_plans')->insert($data + [
                    'version' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $planId = (int) $db->insertID();
                $event = 'player_session_plan_created';
                $before = null;
                $after = $data + ['version' => 1];
            } else {
                $db->table('player_session_plan_history')->insert([
                    'plan_id' => (int) $existing['id'],
                    'version' => (int) $existing['version'],
                    'snapshot' => json_encode($existing, JSON_THROW_ON_ERROR),
                    'created_by' => $actor,
                    'created_at' => $now,
                ]);
                $nextVersion = (int) $existing['version'] + 1;
                $updated = $db->table('player_session_plans')
                    ->where([
                        'id' => (int) $existing['id'],
                        'player_id' => $playerId,
                        'branch_id' => $branchId,
                        'version' => $expectedVersion,
                    ])
                    ->update($data + [
                        'version' => $nextVersion,
                        'updated_at' => $now,
                    ]);

                if ($updated !== true || $db->affectedRows() !== 1) {
                    throw new RuntimeException('Session plan was changed by another user.');
                }

                $planId = (int) $existing['id'];
                $event = 'player_session_plan_updated';
                $before = [
                    'player_id' => $playerId,
                    'branch_id' => $branchId,
                    'version' => $expectedVersion,
                ];
                $after = $data + ['version' => $nextVersion];
            }

            service('auditPersistence')->record(
                $actor,
                $event,
                'player_session_plan',
                (string) $planId,
                $branchId,
                $before,
                $after,
                null,
                $db,
            );

            if (! $db->transStatus()) {
                throw new RuntimeException('Unable to save player session plan.');
            }

            $db->transCommit();
        } catch (\Throwable $exception) {
            $db->transRollback();
            throw $exception;
        }

        return $planId;
    }

    /** @return list<string> */
    private function coachingToolFields(): array
    {
        return [
            'level_1_coaching_tools',
            'level_2_coaching_tools',
            'debrief_coaching_tools',
        ];
    }

    private function validatedCoachingTools(mixed $value): string
    {
        if (! is_array($value)) {
            throw new RuntimeException('Invalid coaching tools.');
        }

        $selected = [];

        foreach ($value as $tool) {
            if (! is_scalar($tool)) {
                throw new RuntimeException('Invalid coaching tools.');
            }

            $tool = trim((string) $tool);

            if ($tool === '' || ! in_array($tool, self::COACHING_TOOLS, true)) {
                throw new RuntimeException('Invalid coaching tools.');
            }

            if (! in_array($tool, $selected, true)) {
                $selected[] = $tool;
            }
        }

        return json_encode($selected, JSON_THROW_ON_ERROR);
    }

    private function validatedCoachingFocus(mixed $value): string
    {
        if (! is_array($value)) {
            throw new RuntimeException('Invalid coaching focus.');
        }

        $selected = [];

        foreach ($value as $focus) {
            if (! is_scalar($focus)) {
                throw new RuntimeException('Invalid coaching focus.');
            }

            $focus = trim((string) $focus);

            if ($focus === '' || ! in_array($focus, self::COACHING_FOCUS, true)) {
                throw new RuntimeException('Invalid coaching focus.');
            }

            if (! in_array($focus, $selected, true)) {
                $selected[] = $focus;
            }
        }

        return json_encode($selected, JSON_THROW_ON_ERROR);
    }

    /** @return list<array<string, mixed>> */
    private function history(object $db, int $planId): array
    {
        $rows = $db->table('player_session_plan_history h')
            ->select('h.*, u.display_name AS created_by_name')
            ->join('users u', 'u.id = h.created_by', 'left')
            ->where('h.plan_id', $planId)
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

    private function assertActor(int $branchId, int $playerId, bool $manage): int
    {
        $permission = $manage ? self::MANAGE_PERMISSION : self::VIEW_PERMISSION;
        if (! service('authorization')->can($permission, (string) $branchId)) {
            throw new RuntimeException('Forbidden');
        }

        $db = Database::connect('default');
        $actor = (int) (session('user.id') ?? 0);
        $roles = $db->table('user_roles ur')
            ->select('r.name')
            ->join('roles r', 'r.id = ur.role_id')
            ->where('ur.user_id', $actor)
            ->get()
            ->getResultArray();
        $roleNames = array_column($roles, 'name');
        $isAdmin = (bool) array_intersect(['Super Admin', 'Branch Admin'], $roleNames);

        if (! $isAdmin && in_array('Coach', $roleNames, true)) {
            $assigned = $db->table('player_coach_assignments')
                ->where([
                    'player_id' => $playerId,
                    'branch_id' => $branchId,
                    'coach_user_id' => $actor,
                ])
                ->where('effective_to IS NULL', null, false)
                ->countAllResults();

            if ($assigned < 1) {
                throw new RuntimeException('Coach is not assigned to this player.');
            }
        }

        return $actor;
    }

    private function applyActorScope(object $query, object $db): void
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
        if (! in_array('Coach', $roleNames, true)) {
            $query->where('1 = 0', null, false);
            return;
        }

        $query->where("EXISTS (
            SELECT 1 FROM player_coach_assignments scope_pca
            WHERE scope_pca.player_id = sp.player_id
            AND scope_pca.branch_id = sp.branch_id
            AND scope_pca.coach_user_id = " . $actor . "
            AND scope_pca.effective_to IS NULL
        )", null, false);
    }

    /** @param list<int> $branches */
    private function authorizedBranches(string $permission): array
    {
        return array_values(array_filter(
            service('branchContext')->authorizedBranchIds(true),
            static fn (int $branchId): bool => service('authorization')->can(
                $permission,
                (string) $branchId,
            ),
        ));
    }

    /** @param list<int> $branches */
    private function branchOptions(object $db, array $branches): array
    {
        return $db->table('branches')
            ->whereIn('id', $branches)
            ->orderBy('name')
            ->get()
            ->getResultArray();
    }

    /** @param list<int> $branches */
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

    /** @param list<int> $branches */
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

    /** @param list<int> $branches */
    private function validBranchFilter(mixed $value, array $branches): ?int
    {
        if (trim((string) $value) === '') {
            return null;
        }

        $branchId = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return $branchId !== false && in_array($branchId, $branches, true) ? $branchId : null;
    }

    private function dateFilter(mixed $value): ?string
    {
        $value = trim((string) $value);
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $value !== '' && $date !== false && $date->format('Y-m-d') === $value ? $value : null;
    }

    private function positiveInt(mixed $value, bool $allowZero = false): ?int
    {
        $minimum = $allowZero ? 0 : 1;
        $number = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => $minimum]]);

        return $number === false ? null : (int) $number;
    }

    /** @param list<int> $branches @return array<string, mixed> */
    private function emptyList(array $filters, array $branches, object $db): array
    {
        return [
            'plans' => [],
            'total' => 0,
            'page' => 1,
            'pages' => 1,
            'filters' => $filters,
            'branches' => $branches === [] ? [] : $this->branchOptions($db, $branches),
            'coaches' => $branches === [] ? [] : $this->coachOptions($db, $branches),
        ];
    }
}
