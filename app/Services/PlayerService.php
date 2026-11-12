<?php

declare (strict_types=1);
namespace App\Services;

use Config\Database;
use RuntimeException;
use Throwable;
final class PlayerService
{
    /** These are the profile columns already approved by the conversion schema. */
    private const PROFILE_FIELDS = ['full_name', 'email', 'phone', 'date_of_birth'];
    public const STATUSES = ['active', 'inactive', 'suspended', 'left_academy', 'alumni'];

    public function list(array $filters = []): array
    {
        $branches = service('branchContext')->authorizedBranchIds(true);

        if ($branches === []) {
            return [
                'players' => [],
                'total' => 0,
                'page' => 1,
                'pages' => 1,
                'filters' => $filters,
                'branches' => [],
                'batches' => [],
                'age_groups' => [],
                'coaches' => [],
                'status_counts' => array_fill_keys(self::STATUSES, 0),
            ];
        }

        $db = Database::connect('default');
        $query = $db->table('players p')
            ->select("p.id, p.branch_id, p.status, p.updated_at, p.admission_id, p.lead_id,
                a.trial_id, a.status AS admission_status, a.admission_number AS player_reference,
                COALESCE(NULLIF(ag.name, ''), NULLIF(a.age_group, '')) AS age_group,
                pe.full_name, pe.email, pe.phone, b.name AS branch_name,
                (SELECT ba.name
                    FROM player_batch_assignments pba
                    INNER JOIN batches ba ON ba.id = pba.batch_id
                    WHERE pba.player_id = p.id AND pba.effective_to IS NULL
                    ORDER BY pba.effective_from DESC LIMIT 1) AS batch_name,
                (SELECT pba.batch_id
                    FROM player_batch_assignments pba
                    WHERE pba.player_id = p.id AND pba.effective_to IS NULL
                    ORDER BY pba.effective_from DESC LIMIT 1) AS batch_id,
                a.coach_user_id AS group_coach_id,
                a.personal_coach_user_id AS personal_coach_id,
                (SELECT u.display_name
                    FROM users u
                    INNER JOIN user_branches ub ON ub.user_id = u.id
                    INNER JOIN user_roles ur ON ur.user_id = u.id
                    INNER JOIN roles r ON r.id = ur.role_id
                    INNER JOIN coach_profiles cp ON cp.user_id = u.id
                    WHERE u.id = a.coach_user_id
                    AND ub.branch_id = p.branch_id
                    AND r.name IN ('Coach', 'Fitness Coach')
                    LIMIT 1) AS group_coach_name,
                (SELECT u.display_name
                    FROM users u
                    INNER JOIN user_branches ub ON ub.user_id = u.id
                    INNER JOIN user_roles ur ON ur.user_id = u.id
                    INNER JOIN roles r ON r.id = ur.role_id
                    INNER JOIN coach_profiles cp ON cp.user_id = u.id
                    WHERE u.id = a.personal_coach_user_id
                    AND ub.branch_id = p.branch_id
                    AND r.name IN ('Coach', 'Fitness Coach')
                    LIMIT 1) AS personal_coach_name,
                (SELECT COUNT(*) FROM player_contacts pc WHERE pc.player_id = p.id) AS contact_count")
            ->join('persons pe', 'pe.id = p.person_id')
            ->join('branches b', 'b.id = p.branch_id')
            ->join('admissions a', 'a.id = p.admission_id')
            ->join('admission_age_groups ag', 'ag.id = a.age_group_id', 'left')
            ->whereIn('p.branch_id', $branches);

        $branchFilter = trim((string) ($filters['branch_id'] ?? ''));
        if ($branchFilter !== '') {
            $branchId = filter_var($branchFilter, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($branchId === false || ! in_array($branchId, $branches, true)) {
                return $this->emptyListResult($filters, $branches, $db);
            }

            $query->where('p.branch_id', $branchId);
        }

        $statusFilter = trim((string) ($filters['status'] ?? ''));
        if ($statusFilter !== '' && ! in_array($statusFilter, self::STATUSES, true)) {
            return $this->emptyListResult($filters, $branches, $db);
        }

        if (($q = trim((string) ($filters['q'] ?? ''))) !== '') {
            $query->groupStart()
                ->like('pe.full_name', $q)
                ->orLike('pe.email', $q)
                ->orLike('pe.phone', $q)
                ->orLike('a.admission_number', $q)
                ->orLike('p.id', $q)
                ->groupEnd();
        }

        $ageGroupFilter = trim((string) ($filters['age_group'] ?? ''));
        if ($ageGroupFilter !== '') {
            $query->where(
                "COALESCE(NULLIF(ag.name, ''), NULLIF(a.age_group, '')) = " . $db->escape($ageGroupFilter),
                null,
                false,
            );
        }

        $batchFilter = trim((string) ($filters['batch_id'] ?? ''));
        if ($batchFilter !== '') {
            $batchId = filter_var($batchFilter, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($batchId === false || ! $this->isAuthorizedBatch($db, (int) $batchId, $branches)) {
                return $this->emptyListResult($filters, $branches, $db);
            }

            $query->where("EXISTS (
                SELECT 1 FROM player_batch_assignments filter_pba
                WHERE filter_pba.player_id = p.id
                AND filter_pba.batch_id = " . (int) $batchId . "
                AND filter_pba.effective_to IS NULL
            )", null, false);
        }

        $coachFilter = trim((string) ($filters['coach_id'] ?? ''));
        if ($coachFilter !== '') {
            $coachId = filter_var($coachFilter, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($coachId === false || ! $this->isAuthorizedCoach($db, (int) $coachId, $branches)) {
                return $this->emptyListResult($filters, $branches, $db);
            }

            $query->where("EXISTS (
                SELECT 1 FROM admissions filter_a
                WHERE filter_a.id = p.admission_id
                AND (filter_a.coach_user_id = " . (int) $coachId . "
                    OR filter_a.personal_coach_user_id = " . (int) $coachId . ")
            )", null, false);
        }

        $summaryQuery = clone $query;
        $statusCounts = array_fill_keys(self::STATUSES, 0);
        foreach (self::STATUSES as $status) {
            $statusCounts[$status] = (clone $summaryQuery)
                ->where('p.status', $status)
                ->countAllResults();
        }

        if ($statusFilter !== '') {
            $query->where('p.status', $statusFilter);
        }

        $total = (clone $query)->countAllResults();
        $perPage = 20;
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($pages, max(1, (int) ($filters['page'] ?? 1)));
        $rows = $query->orderBy('p.id', 'DESC')->get($perPage, ($page - 1) * $perPage)->getResultArray();

        return [
            'players' => $rows,
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'filters' => $filters,
            'branches' => $this->authorizedBranchOptions($db, $branches),
            'batches' => $this->authorizedBatchOptions($db, $branches),
            'age_groups' => $this->authorizedAgeGroupOptions($db, $branches),
            'coaches' => $this->authorizedCoachOptions($db, $branches),
            'status_counts' => $statusCounts,
        ];
    }

    /** @return array<string, mixed> */
    public function goalList(array $filters = []): array
    {
        $branches = array_values(array_filter(
            service('branchContext')->authorizedBranchIds(true),
            static fn (int $branchId): bool => service('authorization')->can(
                'player.goal.set',
                (string) $branchId,
            ),
        ));
        $filters = array_intersect_key($filters, array_flip([
            'player',
            'branch_id',
            'coach_id',
            'title',
            'created_from',
            'created_to',
            'updated_from',
            'updated_to',
            'page',
        ]));

        if ($branches === []) {
            return $this->emptyGoalList($filters, [], Database::connect('default'));
        }

        $db = Database::connect('default');
        $query = $db->table('player_goals pg')
            ->select('pg.id, pg.player_id, pg.branch_id, pg.coach_user_id, pg.title, pg.description,
                pg.version, pg.created_at, pg.updated_at, pe.full_name AS player_name,
                a.admission_number AS player_reference,
                b.name AS branch_name, u.display_name AS coach_name')
            ->join('players p', 'p.id = pg.player_id')
            ->join('persons pe', 'pe.id = p.person_id')
            ->join('admissions a', 'a.id = p.admission_id', 'left')
            ->join('branches b', 'b.id = pg.branch_id')
            ->join('users u', 'u.id = pg.coach_user_id', 'left')
            ->whereIn('pg.branch_id', $branches);

        $this->applyGoalActorScope($query, $db);

        $branchFilter = trim((string) ($filters['branch_id'] ?? ''));
        if ($branchFilter !== '') {
            $branchId = filter_var($branchFilter, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($branchId === false || ! in_array($branchId, $branches, true)) {
                return $this->emptyGoalList($filters, $branches, $db);
            }
            $query->where('pg.branch_id', $branchId);
        }

        $playerFilter = trim((string) ($filters['player'] ?? ''));
        if ($playerFilter !== '') {
            $query->groupStart()
                ->like('pe.full_name', $playerFilter)
                ->orLike('pg.player_id', $playerFilter)
                ->groupEnd();
        }

        $coachFilter = trim((string) ($filters['coach_id'] ?? ''));
        if ($coachFilter !== '') {
            $coachId = filter_var($coachFilter, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($coachId === false || ! $this->isAuthorizedGoalCoach($db, (int) $coachId, $branches)) {
                return $this->emptyGoalList($filters, $branches, $db);
            }
            $query->where('pg.coach_user_id', $coachId);
        }

        $title = trim((string) ($filters['title'] ?? ''));
        if ($title !== '') {
            $query->like('pg.title', $title);
        }

        foreach ([
            'created_from' => ['column' => 'pg.created_at', 'operator' => '>=', 'suffix' => ' 00:00:00'],
            'created_to' => ['column' => 'pg.created_at', 'operator' => '<=', 'suffix' => ' 23:59:59'],
            'updated_from' => ['column' => 'pg.updated_at', 'operator' => '>=', 'suffix' => ' 00:00:00'],
            'updated_to' => ['column' => 'pg.updated_at', 'operator' => '<=', 'suffix' => ' 23:59:59'],
        ] as $key => $dateFilter) {
            $date = $this->goalDateFilter($filters[$key] ?? null);
            if (($filters[$key] ?? '') !== '' && $date === null) {
                return $this->emptyGoalList($filters, $branches, $db);
            }
            if ($date !== null) {
                $query->where(
                    $dateFilter['column'] . ' ' . $dateFilter['operator'],
                    $date . $dateFilter['suffix'],
                );
            }
        }

        $total = (clone $query)->countAllResults();
        $perPage = 20;
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($pages, max(1, (int) ($filters['page'] ?? 1)));
        $goals = $query
            ->orderBy('pg.updated_at', 'DESC')
            ->orderBy('pg.id', 'DESC')
            ->get($perPage, ($page - 1) * $perPage)
            ->getResultArray();

        return [
            'goals' => $goals,
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'filters' => $filters,
            'branches' => $this->authorizedBranchOptions($db, $branches),
            'coaches' => $this->goalCoachOptions($db, $branches),
        ];
    }

    /** @return list<array<string, int|string|null>> */
    public function searchGoalPlayers(string $term): array
    {
        $term = trim($term);
        if ($term === '') {
            return [];
        }

        $branches = array_values(array_filter(
            service('branchContext')->authorizedBranchIds(true),
            static fn (int $branchId): bool => service('authorization')->can(
                'player.goal.set',
                (string) $branchId,
            ),
        ));

        if ($branches === []) {
            return [];
        }

        $db = Database::connect('default');
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
        if ($actor > 0 && $this->isGoalCoach($db, $actor)) {
            $query->where("EXISTS (
                SELECT 1 FROM player_coach_assignments search_pca
                WHERE search_pca.player_id = p.id
                AND search_pca.branch_id = p.branch_id
                AND search_pca.coach_user_id = " . $actor . "
                AND search_pca.effective_to IS NULL
            )", null, false);
        }

        return $query
            ->orderBy('pe.full_name', 'ASC')
            ->orderBy('p.id', 'ASC')
            ->get(10)
            ->getResultArray();
    }

    /** @return array<string, int|string> */
    public function goalPlayerContext(int $playerId): array
    {
        $player = $this->load($playerId, false);
        $this->goalActor((int) $player['branch_id'], $player);

        return [
            'id' => (int) $player['id'],
            'player_name' => (string) ($player['full_name'] ?? 'Unnamed player'),
            'branch_id' => (int) $player['branch_id'],
            'branch_name' => (string) ($player['branch_name'] ?? 'Authorized branch'),
        ];
    }

    /** @return array<string, mixed> */
    public function goalDetails(int $goalId): array
    {
        $goal = Database::connect('default')->table('player_goals pg')
            ->select('pg.*, pe.full_name AS player_name, b.name AS branch_name, u.display_name AS coach_name')
            ->join('players p', 'p.id = pg.player_id')
            ->join('persons pe', 'pe.id = p.person_id')
            ->join('branches b', 'b.id = pg.branch_id')
            ->join('users u', 'u.id = pg.coach_user_id', 'left')
            ->where('pg.id', $goalId)
            ->get()
            ->getRowArray();

        if (! is_array($goal)) {
            throw new RuntimeException('Not found.');
        }

        $this->goalActor((int) $goal['branch_id'], ['id' => (int) $goal['player_id']]);

        return $goal;
    }

    private function applyGoalActorScope(object $query, object $db): void
    {
        $actor = (int) (session('user.id') ?? 0);
        if ($actor < 1 || ! $this->isGoalCoach($db, $actor)) {
            return;
        }

        $query->where("EXISTS (
            SELECT 1 FROM player_coach_assignments scope_pca
            WHERE scope_pca.player_id = pg.player_id
            AND scope_pca.branch_id = pg.branch_id
            AND scope_pca.coach_user_id = " . $actor . "
            AND scope_pca.effective_to IS NULL
        )", null, false);
    }

    private function isGoalCoach(object $db, int $actor): bool
    {
        return $db->table('user_roles ur')
            ->join('roles r', 'r.id = ur.role_id')
            ->where('ur.user_id', $actor)
            ->whereIn('r.name', ['Coach', 'Fitness Coach'])
            ->countAllResults() > 0;
    }

    private function isAuthorizedGoalCoach(object $db, int $coachId, array $branches): bool
    {
        return $db->table('user_branches ub')
            ->join('users u', 'u.id = ub.user_id')
            ->join('user_roles ur', 'ur.user_id = u.id')
            ->join('roles r', 'r.id = ur.role_id')
            ->where('ub.user_id', $coachId)
            ->whereIn('ub.branch_id', $branches)
            ->where('u.is_enabled', 1)
            ->whereIn('r.name', ['Coach', 'Fitness Coach'])
            ->countAllResults() > 0;
    }

    /** @return list<array<string, mixed>> */
    private function goalCoachOptions(object $db, array $branches): array
    {
        return $db->table('user_branches ub')
            ->select('u.id, u.display_name')
            ->join('users u', 'u.id = ub.user_id')
            ->join('user_roles ur', 'ur.user_id = u.id')
            ->join('roles r', 'r.id = ur.role_id')
            ->whereIn('ub.branch_id', $branches)
            ->where('u.is_enabled', 1)
            ->whereIn('r.name', ['Coach', 'Fitness Coach'])
            ->groupBy(['u.id', 'u.display_name'])
            ->orderBy('u.display_name')
            ->get()
            ->getResultArray();
    }

    private function goalDateFilter(mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value ? $value : null;
    }

    /** @return array<string, mixed> */
    private function emptyGoalList(array $filters, array $branches, object $db): array
    {
        return [
            'goals' => [],
            'total' => 0,
            'page' => 1,
            'pages' => 1,
            'filters' => $filters,
            'branches' => $branches === [] ? [] : $this->authorizedBranchOptions($db, $branches),
            'coaches' => $branches === [] ? [] : $this->goalCoachOptions($db, $branches),
        ];
    }

    private function emptyListResult(array $filters, array $branches, object $db): array
    {
        return [
            'players' => [],
            'total' => 0,
            'page' => 1,
            'pages' => 1,
            'filters' => $filters,
            'branches' => $this->authorizedBranchOptions($db, $branches),
            'batches' => $this->authorizedBatchOptions($db, $branches),
            'age_groups' => $this->authorizedAgeGroupOptions($db, $branches),
            'coaches' => $this->authorizedCoachOptions($db, $branches),
            'status_counts' => array_fill_keys(self::STATUSES, 0),
        ];
    }

    private function authorizedBranchOptions(object $db, array $branches): array
    {
        return $db->table('branches')
            ->whereIn('id', $branches)
            ->orderBy('name')
            ->get()
            ->getResultArray();
    }

    private function authorizedBatchOptions(object $db, array $branches): array
    {
        return $db->table('batches')
            ->where('is_active', 1)
            ->groupStart()
                ->where('branch_id IS NULL', null, false)
                ->orWhereIn('branch_id', $branches)
            ->groupEnd()
            ->orderBy('name')
            ->get()
            ->getResultArray();
    }

    private function authorizedAgeGroupOptions(object $db, array $branches): array
    {
        if ($branches === []) {
            return [];
        }

        $rows = $db->table('admission_age_groups')
            ->select('name AS age_group')
            ->where('is_active', 1)
            ->groupStart()
                ->where('branch_id IS NULL', null, false)
                ->orWhereIn('branch_id', $branches)
            ->groupEnd()
            ->where('name !=', '')
            ->groupBy('name')
            ->orderBy('display_order')
            ->orderBy('name')
            ->get()
            ->getResultArray();

        return array_values(array_filter(array_map(
            static fn (array $row): string => trim((string) ($row['age_group'] ?? '')),
            $rows,
        )));
    }

    private function authorizedCoachOptions(object $db, array $branches): array
    {
        return $db->table('user_branches ub')
            ->select('u.id, u.display_name')
            ->join('users u', 'u.id = ub.user_id')
            ->whereIn('ub.branch_id', $branches)
            ->where('u.is_enabled', 1)
            ->groupBy(['u.id', 'u.display_name'])
            ->orderBy('u.display_name')
            ->get()
            ->getResultArray();
    }

    private function isAuthorizedBatch(object $db, int $batchId, array $branches): bool
    {
        return (bool) $db->table('batches')
            ->where('id', $batchId)
            ->where('is_active', 1)
            ->groupStart()
                ->where('branch_id IS NULL', null, false)
                ->orWhereIn('branch_id', $branches)
            ->groupEnd()
            ->countAllResults();
    }

    private function isAuthorizedCoach(object $db, int $coachId, array $branches): bool
    {
        return (bool) $db->table('user_branches')
            ->where('user_id', $coachId)
            ->whereIn('branch_id', $branches)
            ->countAllResults();
    }
    public function find(int $playerId): array
    {
        $player = $this->load($playerId, false);
        $this->authorize((int) $player['branch_id'], 'player.view');

        $profile = $this->view(
            $player,
            $this->branchHistory($playerId),
            $this->batchHistory($playerId),
            $this->coachHistory($playerId)
        );
        $profile['profile_photo_id'] = service('document')->authorizedProfilePhotoId(
            $playerId,
            (int) $player['branch_id']
        );

        return $profile;
    }

    public function canSetGoal(int $playerId): bool
    {
        try {
            $player = $this->load($playerId, false);
            $this->goalActor((int) $player['branch_id'], $player);

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    public function saveGoal(int $playerId, array $input): int
    {
        $title = trim((string) ($input['title'] ?? ''));
        $description = trim((string) ($input['description'] ?? ''));
        $expectedVersion = filter_var(
            $input['expected_version'] ?? 0,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 0]],
        );

        if ($title === '' || strlen($title) > 150
            || $description === '' || strlen($description) > 10000
            || $expectedVersion === false) {
            throw new RuntimeException('Invalid player goal.');
        }

        $db = Database::connect('default');
        $db->transStart();

        try {
            $player = $this->load($playerId, true);
            $actor = $this->goalActor((int) $player['branch_id'], $player);
            $existing = $db->table('player_goals')
                ->where('player_id', $playerId)
                ->get()
                ->getRowArray();
            $now = gmdate('Y-m-d H:i:s');

            if (! is_array($existing)) {
                if ((int) $expectedVersion !== 0) {
                    throw new RuntimeException('Goal was changed by another user.');
                }

                $db->table('player_goals')->insert([
                    'player_id' => $playerId,
                    'branch_id' => (int) $player['branch_id'],
                    'coach_user_id' => $actor,
                    'title' => $title,
                    'description' => $description,
                    'version' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $goalId = (int) $db->insertID();
                $event = 'player_goal_created';
                $before = null;
                $after = [
                    'title' => $title,
                    'description' => $description,
                    'version' => 1,
                ];
            } else {
                if ((int) $existing['branch_id'] !== (int) $player['branch_id']
                    || (int) $existing['version'] !== (int) $expectedVersion) {
                    throw new RuntimeException('Goal was changed by another user.');
                }

                $nextVersion = (int) $existing['version'] + 1;
                $updated = $db->table('player_goals')
                    ->where([
                        'id' => (int) $existing['id'],
                        'player_id' => $playerId,
                        'version' => (int) $expectedVersion,
                    ])
                    ->update([
                        'coach_user_id' => $actor,
                        'title' => $title,
                        'description' => $description,
                        'version' => $nextVersion,
                        'updated_at' => $now,
                    ]);

                if ($updated !== true || $db->affectedRows() !== 1) {
                    throw new RuntimeException('Goal was changed by another user.');
                }

                $goalId = (int) $existing['id'];
                $event = 'player_goal_updated';
                $before = [
                    'title' => $existing['title'],
                    'description' => $existing['description'],
                    'version' => (int) $existing['version'],
                ];
                $after = [
                    'title' => $title,
                    'description' => $description,
                    'version' => $nextVersion,
                ];
            }

            service('auditPersistence')->record(
                $actor,
                $event,
                'player_goal',
                (string) $goalId,
                (int) $player['branch_id'],
                $before,
                $after,
                null,
                $db,
            );

            $db->transComplete();
            if (! $db->transStatus()) {
                throw new RuntimeException('Unable to save player goal.');
            }

            return $goalId;
        } catch (Throwable $exception) {
            $db->transRollback();
            $db->resetTransStatus();
            throw $exception;
        }
    }
    /** @return array{batches: list<array<string, mixed>>, coaches: list<array<string, mixed>>} */
    public function assignmentOptions(int $branchId, ?int $currentBatchId = null): array
    {
        $this->authorize($branchId, 'player.view');
        $db = Database::connect('default');
        return ['branches' => $db->table('branches')->whereIn('id', service('branchContext')->authorizedBranchIds(true))->orderBy('name')->get()->getResultArray(), 'batches' => $this->assignmentBatchOptions($db, $currentBatchId), 'coaches' => $db->table('user_branches ub')
                    ->select('u.id,u.display_name')->join('users u', 'u.id = ub.user_id')->where('ub.branch_id', $branchId)->where('u.is_enabled', 1)
                        ->orderBy('u.display_name')->get()->getResultArray()];
    }

    public function canManageBatchAssignment(int $playerId): bool
    {
        try {
            $player = $this->load($playerId, false);
            $this->assignmentActor(
                (int) $player['branch_id'],
                $player,
                ['player.update', 'player.batch.assign'],
            );

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    public function canManageCoachAssignment(int $playerId): bool
    {
        try {
            $player = $this->load($playerId, false);
            $this->assignmentActor(
                (int) $player['branch_id'],
                $player,
                ['player.update', 'player.coach.assign'],
            );

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    public function canManageBranchAssignment(int $playerId): bool
    {
        try {
            $player = $this->load($playerId, false);
            $this->assignmentActor(
                (int) $player['branch_id'],
                $player,
                ['player.update', 'player.transfer'],
            );

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function assignmentBatchOptions(object $db, ?int $currentBatchId): array
    {
        $batches = $db->table('batches')
            ->where('is_active', 1)
            ->orderBy('name')
            ->get()
            ->getResultArray();
        $loadedIds = array_map(static fn (array $batch): int => (int) $batch['id'], $batches);

        if ($currentBatchId === null || in_array($currentBatchId, $loadedIds, true)) {
            return $batches;
        }

        $historical = $db->table('batches')
            ->where('id', $currentBatchId)
            ->get()
            ->getRowArray();
        if (! is_array($historical)) {
            return $batches;
        }

        $historical['disabled'] = true;
        $historical['historical'] = true;
        $batches[] = $historical;

        return $batches;
    }
    public function changeStatus(int $playerId, string $status, ?string $reason, string $expectedUpdatedAt, int $expectedRevision): void
    {
        if (!in_array($status, self::STATUSES, true) || trim($expectedUpdatedAt) === '' || $expectedRevision < 1 || trim((string) $reason) === '') {
            throw new RuntimeException('Invalid transition.');
        }
        $db = Database::connect('default');
        $db->transStart();
        try {
            $player = $this->load($playerId, true);
            $actor = $this->authorize((int) $player['branch_id'], 'player.update');
            if (
                $status === (string) $player['status']
                || (string) $player['updated_at'] !== $expectedUpdatedAt
                || (int) $player['profile_revision'] !== $expectedRevision
            ) {
                throw new RuntimeException(
                    (string) $player['updated_at'] !== $expectedUpdatedAt
                    || (int) $player['profile_revision'] !== $expectedRevision
                        ? 'Update conflict.'
                        : 'Invalid transition.'
                );
            }
            $now = date('Y-m-d H:i:s');
            if (!$db->table('players')->where(['id' => $playerId, 'updated_at' => $expectedUpdatedAt, 'profile_revision' => $expectedRevision])
                ->update(['status' => $status, 'updated_at' => $now, 'profile_revision' => $expectedRevision + 1])) {
                throw new RuntimeException('Update conflict.');
            }
            $db->table('player_status_history')->insert(['player_id' => $playerId, 'previous_status' => $player['status'], 'new_status' => $status,
                'reason' => trim((string) $reason), 'actor_user_id' => $actor, 'created_at' => $now]);
            service('auditPersistence')->record($actor, 'player_status_changed', 'player', (string) $playerId, (int) $player['branch_id'],
                ['status' => $player['status']], ['status' => $status, 'reason' => trim((string) $reason)]);
            $db->transComplete();
            if (!$db->transStatus()) {
                throw new RuntimeException('Unable to change player status.');
            }
        } catch (Throwable $e) {
            $db->transRollback();
            $db->resetTransStatus();
            throw $e;
        }
    }
    public function addContact(int $playerId, array $contact): void
    {
        $type = (string) ($contact['relationship_type'] ?? '');
        $name = trim((string) ($contact['full_name'] ?? ''));
        $email = trim((string) ($contact['email'] ?? ''));
        $phone = trim((string) ($contact['phone'] ?? ''));
        $this->validateContactValues($type, $name, $email, $phone);
        $db = Database::connect('default');
        $db->transStart();
        try {
            $player = $this->load($playerId, true);
            $actor = $this->authorize((int) $player['branch_id'], 'player.update');
            $now = date('Y-m-d H:i:s');
            $this->persistContactWithinTransaction($db, $playerId, (int) $player['branch_id'], $actor, $type, $name, $email, $phone, $now,
                'player_contact_added');
            $db->transComplete();
            if (!$db->transStatus()) {
                throw new RuntimeException('Unable to add contact.');
            }
        } catch (Throwable $e) {
            $db->transRollback();
            $db->resetTransStatus();
            throw $e;
        }
    }
    public function linkContactWithinTransaction(
        object $db,
        int $playerId,
        int $branchId,
        int $actor,
        string $type,
        string $name,
        ?string $email,
        ?string $phone,
        string $source,
        string $now,
    ): bool {
        $name = trim($name);
        $email = trim((string) $email);
        $phone = trim((string) $phone);
        $this->validateContactValues($type, $name, $email, $phone);
        $player = $db->table('players')
            ->where(['id' => $playerId, 'branch_id' => $branchId])
            ->get()
            ->getRowArray();
        if (!is_array($player)) {
            throw new RuntimeException('Invalid player.');
        }
        $existing = $db->query(
            'SELECT pc.contact_person_id, pe.full_name, pe.email, pe.phone
             FROM player_contacts pc
             INNER JOIN persons pe ON pe.id = pc.contact_person_id
             WHERE pc.player_id = ? AND pc.relationship_type = ?
             FOR UPDATE',
            [$playerId, $type],
        )->getResultArray();

        $normalizedName = $this->normalizeContactIdentity($name, false);
        $normalizedEmail = $this->normalizeContactIdentity($email, false);
        $normalizedPhone = $this->normalizeContactIdentity($phone, true);
        foreach ($existing as $row) {
            if ($this->normalizeContactIdentity((string) $row['full_name'], false) !== $normalizedName) {
                continue;
            }

            $existingEmail = $this->normalizeContactIdentity((string) ($row['email'] ?? ''), false);
            $existingPhone = $this->normalizeContactIdentity((string) ($row['phone'] ?? ''), true);
            if (($existingEmail !== '' && $normalizedEmail !== '' && $existingEmail !== $normalizedEmail)
                || ($existingPhone !== '' && $normalizedPhone !== '' && $existingPhone !== $normalizedPhone)) {
                continue;
            }

            $missingMetadata = [];
            if ($existingEmail === '' && $normalizedEmail !== '') {
                $missingMetadata['email'] = $email;
            }
            if ($existingPhone === '' && $normalizedPhone !== '') {
                $missingMetadata['phone'] = $phone;
            }
            if ($missingMetadata !== []) {
                $missingMetadata['updated_at'] = $now;
                $db->table('persons')
                    ->where('id', (int) $row['contact_person_id'])
                    ->update($missingMetadata);
            }

            return false;
        }
        $this->persistContactWithinTransaction($db, $playerId, $branchId, $actor, $type, $name, $email, $phone, $now,
            'player_contact_linked_from_admission', $source);
        return true;
    }
    private function normalizeContactIdentity(string $value, bool $phone): string
    {
        $value = trim($value);
        if ($phone) {
            return (string) preg_replace('/\D+/', '', $value);
        }

        return strtolower((string) preg_replace('/\s+/', ' ', $value));
    }
    private function validateContactValues(string $type, string $name, string $email, string $phone): void
    {
        if (!in_array($type, ['guardian', 'emergency-contact', 'primary-contact'], true)
            || $name === '' || strlen($name) > 150
            || ($email !== '' && (strlen($email) > 190 || filter_var($email, FILTER_VALIDATE_EMAIL) === false))
            || strlen($phone) > 40) {
            throw new RuntimeException('Invalid contact.');
        }
    }
    private function persistContactWithinTransaction(
        object $db,
        int $playerId,
        int $branchId,
        int $actor,
        string $type,
        string $name,
        string $email,
        string $phone,
        string $now,
        string $action,
        ?string $source = null,
    ): void {
        $db->table('persons')->insert([
            'full_name' => $name,
            'email' => $email === '' ? null : $email,
            'phone' => $phone === '' ? null : $phone,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $personId = (int) $db->insertID();
        $db->table('player_contacts')->insert([
            'player_id' => $playerId,
            'contact_person_id' => $personId,
            'relationship_type' => $type,
            'is_primary' => $type === 'primary-contact' ? 1 : 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $after = ['relationship_type' => $type];
        if ($source !== null) {
            $after['source_admission_id'] = (int) $source;
        }
        service('auditPersistence')->record($actor, $action, 'player', (string) $playerId, $branchId, null, $after, null, $db);
    }
    /** @return array{player: array<string, mixed>, coaches: list<array<string, mixed>>} */
    public function coachCorrectionForm(int $playerId): array
    {
        $player = $this->load($playerId, false);
        $this->assignmentActor(
            (int) $player['branch_id'],
            $player,
            ['player.update', 'player.coach.assign'],
        );

        return [
            'player' => $player,
            'coaches' => $this->coachOptionsForBranch(
                (int) $player['branch_id'],
                (int) ($player['admission_group_coach_id'] ?? 0),
                (int) ($player['admission_personal_coach_id'] ?? 0),
            ),
        ];
    }

    public function updateCoachAssignments(int $playerId, array $input): bool
    {
        $db = Database::connect('default');
        $db->transStart();

        try {
            $allowedFields = [
                csrf_token(),
                'group_coach_id',
                'personal_coach_id',
                'correction_reason',
                'expected_updated_at',
                'expected_profile_revision',
            ];
            foreach (array_keys($input) as $field) {
                if (! in_array((string) $field, $allowedFields, true)) {
                    throw new RuntimeException('Only coach assignments may be changed.');
                }
            }

            $player = $this->load($playerId, true);
            $actor = $this->assignmentActor(
                (int) $player['branch_id'],
                $player,
                ['player.update', 'player.coach.assign'],
                $db,
            );
            $expectedUpdatedAt = trim((string) ($input['expected_updated_at'] ?? ''));
            $expectedRevision = (int) ($input['expected_profile_revision'] ?? 0);
            if ($expectedUpdatedAt === '' || $expectedRevision < 1
                || (string) $player['updated_at'] !== $expectedUpdatedAt
                || (int) $player['profile_revision'] !== $expectedRevision) {
                throw new RuntimeException('Update conflict.');
            }

            $currentGroupCoachId = (int) ($player['admission_group_coach_id'] ?? 0);
            $currentPersonalCoachId = (int) ($player['admission_personal_coach_id'] ?? 0);
            $currentGroupCoach = $currentGroupCoachId > 0 ? $currentGroupCoachId : null;
            $currentPersonalCoach = $currentPersonalCoachId > 0 ? $currentPersonalCoachId : null;
            $groupCoachId = $this->nullableCoachId(
                array_key_exists('group_coach_id', $input) ? $input['group_coach_id'] : $currentGroupCoach,
            );
            $personalCoachId = $this->nullableCoachId(
                array_key_exists('personal_coach_id', $input) ? $input['personal_coach_id'] : $currentPersonalCoach,
            );

            if ($groupCoachId === $currentGroupCoach && $personalCoachId === $currentPersonalCoach) {
                throw new RuntimeException('No coach assignment changes were submitted.');
            }

            $reason = trim((string) ($input['correction_reason'] ?? ''));
            if ($reason === '') {
                throw new RuntimeException('Correction reason is required for coach changes.');
            }
            if (strlen($reason) > 1000) {
                throw new RuntimeException('Correction reason is too long.');
            }

            $this->validatePlayerCoaches($db, (int) $player['branch_id'], [$groupCoachId, $personalCoachId]);
            $now = date('Y-m-d H:i:s');
            $db->table('admissions')
                ->where('id', (int) $player['admission_id'])
                ->where('branch_id', (int) $player['branch_id'])
                ->update([
                    'coach_user_id' => $groupCoachId,
                    'personal_coach_user_id' => $personalCoachId,
                    'updated_at' => $now,
                ]);

            if (! $db->table('players')
                ->where(['id' => $playerId, 'updated_at' => $expectedUpdatedAt, 'profile_revision' => $expectedRevision])
                ->update(['updated_at' => $now, 'profile_revision' => $expectedRevision + 1])) {
                throw new RuntimeException('Update conflict.');
            }

            service('auditPersistence')->record(
                $actor,
                'player_coach_assignments_changed',
                'player',
                (string) $playerId,
                (int) $player['branch_id'],
                [
                    'group_coach_id' => $currentGroupCoach,
                    'personal_coach_id' => $currentPersonalCoach,
                ],
                [
                    'group_coach_id' => $groupCoachId,
                    'personal_coach_id' => $personalCoachId,
                ],
                $reason,
                $db,
            );

            $db->transComplete();
            if (! $db->transStatus()) {
                throw new RuntimeException('Unable to update player coaches.');
            }

            return true;
        } catch (Throwable $exception) {
            $db->transRollback();
            $db->resetTransStatus();
            throw $exception;
        }
    }

    private function coachOptionsForBranch(int $branchId, int $groupCoachId, int $personalCoachId): array
    {
        $db = Database::connect('default');
        $selectedIds = array_values(array_unique(array_filter([$groupCoachId, $personalCoachId])));
        $rows = $db->table('users u')
            ->select('u.id, u.display_name, u.is_enabled, cp.is_active AS coach_profile_active')
            ->join('user_branches ub', 'ub.user_id = u.id')
            ->join('user_roles ur', 'ur.user_id = u.id')
            ->join('roles r', 'r.id = ur.role_id')
            ->join('coach_profiles cp', 'cp.user_id = u.id')
            ->where('ub.branch_id', $branchId)
            ->whereIn('r.name', ['Coach', 'Fitness Coach'])
            ->groupBy(['u.id', 'u.display_name', 'u.is_enabled', 'cp.is_active'])
            ->orderBy('u.display_name')
            ->get()
            ->getResultArray();

        $loadedIds = array_map(static fn (array $row): int => (int) $row['id'], $rows);
        foreach ($selectedIds as $selectedId) {
            if (in_array($selectedId, $loadedIds, true)) {
                continue;
            }

            $historical = $db->table('users u')
                ->select('u.id, u.display_name, u.is_enabled, cp.is_active AS coach_profile_active')
                ->join('user_branches ub', 'ub.user_id = u.id')
                ->join('user_roles ur', 'ur.user_id = u.id')
                ->join('roles r', 'r.id = ur.role_id')
                ->join('coach_profiles cp', 'cp.user_id = u.id')
                ->where(['u.id' => $selectedId, 'ub.branch_id' => $branchId])
                ->whereIn('r.name', ['Coach', 'Fitness Coach'])
                ->groupBy(['u.id', 'u.display_name', 'u.is_enabled', 'cp.is_active'])
                ->get()
                ->getRowArray();
            if (is_array($historical)) {
                $rows[] = $historical;
                $loadedIds[] = $selectedId;
            }
        }

        return array_map(static function (array $row) use ($selectedIds): array {
            $isActive = (int) ($row['is_enabled'] ?? 0) === 1 && (int) ($row['coach_profile_active'] ?? 0) === 1;

            return [
                'id' => (int) $row['id'],
                'display_name' => (string) $row['display_name'],
                'disabled' => ! $isActive,
                'historical' => ! $isActive,
            ];
        }, $rows);
    }

    private function nullableCoachId(mixed $value): ?int
    {
        $coachId = trim((string) ($value ?? ''));
        if ($coachId === '') {
            return null;
        }
        if (! ctype_digit($coachId) || (int) $coachId < 1) {
            throw new RuntimeException('Invalid Coach.');
        }

        return (int) $coachId;
    }

    private function validatePlayerCoaches(object $db, int $branchId, array $coachIds): void
    {
        foreach (array_unique(array_filter($coachIds)) as $coachId) {
            $valid = $db->table('user_branches ub')
                ->join('users u', 'u.id = ub.user_id')
                ->join('user_roles ur', 'ur.user_id = u.id')
                ->join('roles r', 'r.id = ur.role_id')
                ->join('coach_profiles cp', 'cp.user_id = ur.user_id')
                ->where([
                    'ub.user_id' => (int) $coachId,
                    'ub.branch_id' => $branchId,
                    'u.is_enabled' => 1,
                    'cp.is_active' => 1,
                ])
                ->whereIn('r.name', ['Coach', 'Fitness Coach'])
                ->countAllResults() > 0;
            if (! $valid) {
                throw new RuntimeException('Coach is not active and enabled for the player branch.');
            }
        }
    }

    public function assignBatch(int $playerId, ?int $batchId, string $effectiveFrom, ?string $effectiveTo, string $expectedUpdatedAt,
        int $expectedRevision): void
    {
        $this->assignPlacement('batch', $playerId, $batchId, null, $effectiveFrom, $effectiveTo, $expectedUpdatedAt, $expectedRevision);
    }
    public function assignCoach(int $playerId, ?int $coachUserId, string $effectiveFrom, ?string $effectiveTo, string $expectedUpdatedAt,
        int $expectedRevision): void
    {
        $this->assignPlacement('coach', $playerId, null, $coachUserId, $effectiveFrom, $effectiveTo, $expectedUpdatedAt, $expectedRevision);
    }
    private function assignPlacement(string $type, int $playerId, ?int $batchId, ?int $coachUserId, string $effectiveFrom, ?string $effectiveTo,
        string $expectedUpdatedAt, int $expectedRevision): void
    {
        if (!$this->validDateTime($effectiveFrom) || $effectiveTo !== null && !$this
            ->validDateTime($effectiveTo)
            || ($effectiveTo !== null && $effectiveTo <= $effectiveFrom)
            || trim($expectedUpdatedAt) === ''
            || $expectedRevision < 1
        ) {
            throw new RuntimeException('Invalid assignment details.');
        }
        $db = Database::connect('default');
        $db->transStart();
        try {
            $player = $this->load($playerId, true);
            $actor = $this->assignmentActor(
                (int) $player['branch_id'],
                $player,
                $type === 'batch'
                    ? ['player.update', 'player.batch.assign']
                    : ['player.update', 'player.coach.assign'],
                $db,
            );
            $current = $db->table($type === 'batch' ? 'player_batch_assignments' : 'player_coach_assignments')
                ->where(['player_id' => $playerId, 'effective_to' => null])->orderBy('effective_from', 'DESC')->get()->getRowArray();
            if (is_array($current) && $effectiveFrom <= (string) $current['effective_from']) {
                throw new RuntimeException('Assignment effective date must be later than the current assignment.');
            }
            if ((string) $player['updated_at'] !== $expectedUpdatedAt || (int) $player['profile_revision'] !== $expectedRevision) {
                throw new RuntimeException('Update conflict.');
            }
            $table = $type === 'batch' ? 'player_batch_assignments' : 'player_coach_assignments';
            if ($type === 'batch') {
                if ($batchId === null) {
                    if (!is_array($current)) {
                        throw new RuntimeException('No active batch assignment exists to remove.');
                    }
                } else {
                    $target = $db->table('batches')
                        ->where('id', $batchId)
                        ->get()
                        ->getRowArray();
                    if (!is_array($target)) {
                        throw new RuntimeException('Invalid batch.');
                    }
                    if ((int) ($target['is_active'] ?? 0) !== 1
                        && (! is_array($current) || (int) $current['batch_id'] !== $batchId)
                    ) {
                        throw new RuntimeException('Batch is inactive.');
                    }
                    if (is_array($current) && (int) $current['batch_id'] === $batchId) {
                        throw new RuntimeException('Duplicate active batch assignment.');
                    }
                }
            } else if ($coachUserId === null) {
                if (!is_array($current)) {
                    throw new RuntimeException('No active coach assignment exists to remove.');
                }
            } else {
                $target = $db->table('user_branches ub')
                    ->select('ub.user_id')
                    ->join('users u', 'u.id = ub.user_id')
                    ->where(['ub.user_id' => $coachUserId, 'ub.branch_id' => (int) $player['branch_id'], 'u.is_enabled' => 1])
                    ->get()
                    ->getRowArray();
                if (!is_array($target)) {
                    throw new RuntimeException('Coach is not assigned to the player branch.');
                }
                if (is_array($current) && (int) $current['coach_user_id'] === $coachUserId) {
                    throw new RuntimeException('Duplicate active coach assignment.');
                }
            }
            $now = date('Y-m-d H:i:s');
            if (is_array($current)) {
                $db->table($table)->where('id', (int) $current['id'])->update(['effective_to' => $effectiveFrom, 'updated_at' => $now]);
            }
            if ($type === 'batch' && $batchId !== null || $type === 'coach' && $coachUserId !== null) {
                $db->table($table)->insert(['player_id' => $playerId, 'branch_id' => (int) $player['branch_id'],
                    $type === 'batch' ? 'batch_id' : 'coach_user_id' => $type === 'batch' ? $batchId : $coachUserId, 'assigned_by' => $actor,
                        'effective_from' => $effectiveFrom, 'effective_to' => $effectiveTo, 'created_at' => $now, 'updated_at' => $now]);
            }
            if (!$db->table('players')->where(['id' => $playerId, 'updated_at' => $expectedUpdatedAt, 'profile_revision' => $expectedRevision])
                ->update(['updated_at' => $now, 'profile_revision' => $expectedRevision + 1])) {
                throw new RuntimeException('Update conflict.');
            }
            service('auditPersistence')->record($actor, $type === 'batch' ? 'player_batch_assignment_changed' : 'player_coach_assignment_changed',
                'player', (string) $playerId, (int) $player['branch_id'],
                    ['previous_assignment_id' => is_array($current) ? (int) $current['id'] : null],
                        ['assignment_id' => $type === 'batch' ? $batchId : $coachUserId, 'effective_from' => $effectiveFrom, 'effective_to' => $effectiveTo]);
            $db->transComplete();
            if (!$db->transStatus()) {
                throw new RuntimeException('Unable to change assignment.');
            }
        } catch (Throwable $exception) {
            $db->transRollback();
            $db->resetTransStatus();
            $user = session('user');
            $actor = is_array($user) && isset($user['id']) ? (int) $user['id'] : null;
            service('auditPersistence')->record($actor, $type === 'batch' ? 'player_batch_assignment_rejected' : 'player_coach_assignment_rejected',
                'player', (string) $playerId, isset($player['branch_id']) ? (int) $player['branch_id'] : null, null, null, $exception->getMessage());
            throw $exception;
        }
    }
    public function transferBranch(int $playerId, int $targetBranch, string $effectiveFrom, string $expectedUpdatedAt, int $expectedRevision): void
    {
        $player = $this->load($playerId, false);
        $this->assignmentActor(
            (int) $player['branch_id'],
            $player,
            ['player.update', 'player.transfer'],
        );

        if ($targetBranch === (int) $player['branch_id']) {
            throw new RuntimeException('Invalid branch transfer.');
        }

        $db = Database::connect('default');
        $db->transStart();
        try {
            $player = $this->load($playerId, true);
            $actor = $this->assignmentActor(
                (int) $player['branch_id'],
                $player,
                ['player.update', 'player.transfer'],
                $db,
            );

            if (! $db->table('branches')->where('id', $targetBranch)->get()->getRowArray()) {
                throw new RuntimeException('Invalid branch transfer.');
            }

            $this->authorizeAny($targetBranch, ['player.update', 'player.transfer']);
            if (
                $targetBranch === (int) $player['branch_id']
                || (string) $player['updated_at'] !== $expectedUpdatedAt
                || (int) $player['profile_revision'] !== $expectedRevision
            ) {
                throw new RuntimeException(
                    (string) $player['updated_at'] !== $expectedUpdatedAt
                    || (int) $player['profile_revision'] !== $expectedRevision
                        ? 'Update conflict.'
                        : 'Invalid transition.'
                );
            }

            $current = $db->table('player_branch_history')
                ->where([
                    'player_id' => $playerId,
                    'effective_to' => null,
                ])
                ->orderBy('effective_from', 'DESC')
                ->get()
                ->getRowArray();

            if (
                ! is_array($current)
                || (int) $current['branch_id'] !== (int) $player['branch_id']
                || $effectiveFrom <= (string) $current['effective_from']
            ) {
                throw new RuntimeException('Invalid branch transfer.');
            }

            $now = date('Y-m-d H:i:s');

            $db->table('player_branch_history')
                ->where([
                    'player_id' => $playerId,
                    'effective_from' => $current['effective_from'],
                    'effective_to' => null,
                ])
                ->update(['effective_to' => $effectiveFrom]);

            $db->table('player_branch_history')->insert([
                'player_id' => $playerId,
                'branch_id' => $targetBranch,
                'effective_from' => $effectiveFrom,
                'effective_to' => null,
                'created_at' => $now,
            ]);

            $updated = $db->table('players')
                ->where([
                    'id' => $playerId,
                    'branch_id' => $player['branch_id'],
                    'updated_at' => $expectedUpdatedAt,
                    'profile_revision' => $expectedRevision,
                ])
                ->update([
                    'branch_id' => $targetBranch,
                    'updated_at' => $now,
                    'profile_revision' => $expectedRevision + 1,
                ]);

            if (! $updated) {
                throw new RuntimeException('Update conflict.');
            }

            service('auditPersistence')->record(
                $actor,
                'player_branch_transferred',
                'player',
                (string) $playerId,
                (int) $player['branch_id'],
                ['branch_id' => (int) $player['branch_id']],
                [
                    'branch_id' => $targetBranch,
                    'effective_from' => $effectiveFrom,
                ],
            );
            $db->transComplete();
            if (!$db->transStatus()) {
                throw new RuntimeException('Unable to transfer player branch.');
            }
        } catch (Throwable $exception) {
            $db->transRollback();
            $db->resetTransStatus();
            throw $exception;
        }
    }
    /** @param array<string, mixed> $profile */
    public function updateProfile(int $playerId, array $profile, string $expectedUpdatedAt, int $expectedRevision): void
    {
        $unknown = array_diff(array_keys($profile), array_merge(self::PROFILE_FIELDS, ['expected_updated_at', 'expected_profile_revision']));
        if ($unknown !== [] || trim($expectedUpdatedAt) === '' || $expectedRevision < 1) {
            throw new RuntimeException('Invalid player profile.');
        }
        $updates = $this->validateProfile($profile);
        if ($updates === []) {
            throw new RuntimeException('Invalid player profile.');
        }
        $db = Database::connect('default');
        $db->transStart();
        try {
            $player = $this->load($playerId, true);
            $actor = $this->authorize((int) $player['branch_id'], 'player.update');
            if ((string) $player['updated_at'] !== $expectedUpdatedAt || (int) $player['profile_revision'] !== $expectedRevision) {
                throw new RuntimeException('Update conflict.');
            }
            $now = date('Y-m-d H:i:s');
            $updates['updated_at'] = $now;
            $db->table('persons')->where('id', (int) $player['person_id'])->update($updates);
            if (!$db->table('players')->where(['id' => $playerId, 'updated_at' => $expectedUpdatedAt, 'profile_revision' => $expectedRevision])
                ->update(['updated_at' => $now, 'profile_revision' => $expectedRevision + 1])) {
                throw new RuntimeException('Update conflict.');
            }
            service('auditPersistence')->record($actor, 'player_profile_updated', 'player', (string) $playerId, (int) $player['branch_id'], $this
                ->changedFields($profile), $this->changedFields($profile));
            $db->transComplete();
            if (!$db->transStatus()) {
                throw new RuntimeException('Unable to update player profile.');
            }
        } catch (Throwable $exception) {
            $db->transRollback();
            $db->resetTransStatus();
            throw $exception;
        }
    }
    private function load(int $id, bool $lock): array
    {
        if ($id < 1) {
            throw new RuntimeException('Not found.');
        }
        $sql = 'SELECT p.id, p.person_id, p.lead_id, p.admission_id, p.branch_id, p.status, p.created_at, p.updated_at, p.profile_revision,
                       pe.full_name, pe.email, pe.phone, pe.date_of_birth,
                       a.status AS admission_status, a.trial_id, a.admission_number,
                       a.coach_user_id AS admission_group_coach_id,
                       a.personal_coach_user_id AS admission_personal_coach_id,
                       a.age_group AS admission_age_group,
                       admission_batch.name AS admission_batch_name,
                       (SELECT u.display_name
                        FROM users u
                        INNER JOIN user_branches ub ON ub.user_id = u.id
                        INNER JOIN user_roles ur ON ur.user_id = u.id
                        INNER JOIN roles r ON r.id = ur.role_id
                        INNER JOIN coach_profiles cp ON cp.user_id = u.id
                        WHERE u.id = a.coach_user_id
                        AND ub.branch_id = p.branch_id
                        AND r.name IN (\'Coach\', \'Fitness Coach\')
                        LIMIT 1) AS group_coach_name,
                       (SELECT u.display_name
                        FROM users u
                        INNER JOIN user_branches ub ON ub.user_id = u.id
                        INNER JOIN user_roles ur ON ur.user_id = u.id
                        INNER JOIN roles r ON r.id = ur.role_id
                        INNER JOIN coach_profiles cp ON cp.user_id = u.id
                        WHERE u.id = a.personal_coach_user_id
                        AND ub.branch_id = p.branch_id
                        AND r.name IN (\'Coach\', \'Fitness Coach\')
                        LIMIT 1) AS personal_coach_name,
                       admission_branch.name AS admission_branch_name,
                       current_period.period_number AS admission_current_period_number,
                       current_period.start_date AS admission_current_period_start_date,
                       current_period.end_date AS admission_current_period_end_date,
                       current_period.age_group_label AS admission_current_period_age_group,
                       (SELECT h.created_at FROM admission_status_history h
                        WHERE h.admission_id = a.id AND h.new_status = \'CONVERTED\'
                        ORDER BY h.id ASC LIMIT 1) AS admission_conversion_date,
                       l.source AS lead_source, b.name AS branch_name
                FROM players p
                INNER JOIN persons pe ON pe.id = p.person_id
                INNER JOIN admissions a ON a.id = p.admission_id
                INNER JOIN branches b ON b.id = p.branch_id
                LEFT JOIN branches admission_branch ON admission_branch.id = a.branch_id
                LEFT JOIN batches admission_batch ON admission_batch.id = a.batch_id
                LEFT JOIN admission_periods current_period ON current_period.id = (
                    SELECT ap.id FROM admission_periods ap
                    WHERE ap.admission_id = a.id AND ap.status = \'current\'
                    ORDER BY ap.period_number DESC LIMIT 1
                )
                LEFT JOIN leads l ON l.id = p.lead_id
                WHERE p.id = ?
                LIMIT 1' . ($lock ? ' FOR UPDATE' : '');
        $row = Database::connect('default')->query($sql, [$id])->getRowArray();
        if (!is_array($row)) {
            throw new RuntimeException('Not found.');
        }
        return $row;
    }
    private function assignmentActor(
        int $branch,
        array $player,
        array $permissions,
        ?object $db = null,
    ): int
    {
        $connection = $db ?? Database::connect('default');
        $actor = $this->authorizeAny($branch, $permissions);
        $isCoach = $connection->table('user_roles ur')
            ->join('roles r', 'r.id = ur.role_id')
            ->where('ur.user_id', $actor)
            ->whereIn('r.name', ['Coach', 'Fitness Coach'])
            ->countAllResults() > 0;

        if (! $isCoach) {
            return $actor;
        }

        $assigned = $connection->table('player_coach_assignments')
            ->where([
                'player_id' => (int) $player['id'],
                'branch_id' => $branch,
                'coach_user_id' => $actor,
            ])
            ->where('effective_to IS NULL', null, false)
            ->countAllResults();

        if ($assigned < 1) {
            throw new RuntimeException('Coach is not assigned to this player.');
        }

        return $actor;
    }

    private function authorizeAny(int $branch, array $permissions): int
    {
        foreach ($permissions as $permission) {
            if (service('authorization')->can($permission, (string) $branch)) {
                $user = session('user');
                if (! is_array($user) || ! isset($user['id'])) {
                    throw new RuntimeException('Authentication required');
                }

                return (int) $user['id'];
            }
        }

        throw new RuntimeException('Forbidden');
    }

    private function authorize(int $branch, string $permission): int
    {
        if ($branch < 1 || !service('authorization')->can($permission, (string) $branch)) {
            throw new RuntimeException('Forbidden');
        }
        $user = session('user');
        if (!is_array($user) || !isset($user['id'])) {
            throw new RuntimeException('Authentication required');
        }
        return (int) $user['id'];
    }
    /** @param array<string, mixed> $profile @return array<string, mixed> */
    private function validateProfile(array $profile): array
    {
        $updates = [];
        foreach (self::PROFILE_FIELDS as $field) {
            if (!array_key_exists($field, $profile)) {
                continue;
            }
            if (!is_string($profile[$field])) {
                throw new RuntimeException('Invalid player profile.');
            }
            $value = trim($profile[$field]);
            if ($field === 'full_name' && ($value === '' || strlen($value) > 150)) {
                throw new RuntimeException('Invalid player profile.');
            }
            if ($field === 'email' && $value !== '' && (strlen($value) > 190 || filter_var($value, FILTER_VALIDATE_EMAIL) === false)) {
                throw new RuntimeException('Invalid player profile.');
            }
            if ($field === 'phone' && ($value !== '' && strlen($value) > 40)) {
                throw new RuntimeException('Invalid player profile.');
            }
            if ($field === 'date_of_birth' && ($value !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value))) {
                throw new RuntimeException('Invalid player profile.');
            }
            $updates[$field] = $value === '' ? null : $value;
        }
        return $updates;
    }
    /** @param list<array<string, mixed>> $history */
    private function view(array $player, array $history, array $batchHistory, array $coachHistory): array
    {
        $currentBatch = array_values(array_filter($batchHistory, static fn(array $row): bool => $row['effective_to'] === null))[0] ?? null;
        $currentCoach = array_values(array_filter($coachHistory, static fn(array $row): bool => $row['effective_to'] === null))[0] ?? null;
        $canViewAdmission = service('authorization')->can('admission.view', (string) $player['branch_id']);

        return ['id' => (int) $player['id'], 'admission_id' => (int) $player['admission_id'],
            'can_view_admission' => $canViewAdmission,
            'admission_number' => $canViewAdmission ? ($player['admission_number'] ?? null) : null,
            'admission_branch_name' => $canViewAdmission ? ($player['admission_branch_name'] ?? null) : null,
            'admission_age_group' => $canViewAdmission ? ($player['admission_age_group'] ?? null) : null,
            'admission_batch_name' => $canViewAdmission ? ($currentBatch['batch_name'] ?? null) : null,
            'group_coach_name' => $player['group_coach_name'] ?? null,
            'personal_coach_name' => $player['personal_coach_name'] ?? null,
            'admission_conversion_date' => $canViewAdmission ? ($player['admission_conversion_date'] ?? null) : null,
            'admission_current_period' => $canViewAdmission ? [
                'number' => $player['admission_current_period_number'] ?? null,
                'start_date' => $player['admission_current_period_start_date'] ?? null,
                'end_date' => $player['admission_current_period_end_date'] ?? null,
                'age_group' => $player['admission_current_period_age_group'] ?? null,
            ] : null,
            'lead_id' => $player['lead_id'] === null ? null : (int) $player['lead_id'],
                'trial_id' => $player['trial_id'] === null ? null : (int) $player['trial_id'], 'branch_id' => (int) $player['branch_id'],
                    'branch_name' => $player['branch_name'] ?? null, 'status' => $player['status'],
                        'admission_status' => $player['admission_status'] ?? null, 'profile' => ['full_name' => $player['full_name'],
                            'email' => $player['email'], 'phone' => $player['phone'], 'date_of_birth' => $player['date_of_birth']],
                                'updated_at' => $player['updated_at'], 'profile_revision' => (int) $player['profile_revision'],
                                    'branch_history' => $history, 'batch_history' => $batchHistory, 'coach_history' => $coachHistory,
                                        'current_batch' => $currentBatch,
                                        'current_coach' => $currentCoach,
                                        'session_progress' => service('scheduling')->sessionProgressForPlayer((int) $player['id']),
                                        'goal' => $this->currentGoal(
                                            (int) $player['id'],
                                            (int) $player['branch_id'],
                                        ),
                                        'performance' => service('authorization')->can('performance.view', (string) $player['branch_id'])
                                            ? service('performance')->player((int) $player['id'])
                                            : null,
                                        'contacts' => Database::connect('default')
                                            ->table('player_contacts pc')->select('pc.relationship_type,pc.is_primary,pe.full_name,pe.email,pe.phone')
                                                ->join('persons pe', 'pe.id = pc.contact_person_id')->where('pc.player_id', (int) $player['id'])
                                                    ->orderBy('pc.relationship_type', 'ASC')->orderBy('pc.contact_person_id', 'ASC')->get()
                                                        ->getResultArray(), 'status_history' => Database::connect('default')
                                                            ->table('player_status_history psh')->select('psh.*, u.display_name AS actor_name')
                                                                ->join('users u', 'u.id = psh.actor_user_id', 'left')
                                                                    ->where('psh.player_id', (int) $player['id'])->orderBy('psh.created_at', 'ASC')
                                                                        ->get()->getResultArray()];
    }

    private function currentGoal(int $playerId, int $branchId): ?array
    {
        $goal = Database::connect('default')->table('player_goals')
            ->where([
                'player_id' => $playerId,
                'branch_id' => $branchId,
            ])
            ->get()
            ->getRowArray();

        return is_array($goal) ? $goal : null;
    }

    private function goalActor(int $branchId, array $player): int
    {
        $connection = Database::connect('default');
        $actor = $this->authorize($branchId, 'player.goal.set');
        $isCoach = $connection->table('user_roles ur')
            ->join('roles r', 'r.id = ur.role_id')
            ->where('ur.user_id', $actor)
            ->whereIn('r.name', ['Coach', 'Fitness Coach'])
            ->countAllResults() > 0;

        if (! $isCoach) {
            return $actor;
        }

        $assignment = $connection->table('player_coach_assignments')
            ->where([
                'player_id' => (int) $player['id'],
                'branch_id' => $branchId,
                'coach_user_id' => $actor,
            ])
            ->where('effective_to IS NULL', null, false)
            ->countAllResults();

        if ($assignment < 1) {
            throw new RuntimeException('Coach is not assigned to this player.');
        }

        return $actor;
    }

    /** @return list<array<string, mixed>> */
    private function branchHistory(int $playerId): array
    {
        $rows = Database::connect('default')->table('player_branch_history pbh')->select('pbh.*, b.name AS branch_name')
            ->join('branches b', 'b.id = pbh.branch_id')->where('pbh.player_id', $playerId)->orderBy('pbh.effective_from', 'ASC')->get()->getResultArray();
        foreach ($rows as $row) {
            if (!service('authorization')->can('player.view', (string) $row['branch_id'])) {
                throw new RuntimeException('Forbidden');
            }
        }
        return array_map(static fn(array $row): array => ['branch_id' => (int) $row['branch_id'], 'branch_name' => $row['branch_name'] ?? null,
            'effective_from' => $row['effective_from'], 'effective_to' => $row['effective_to']], $rows);
    }
    private function batchHistory(int $playerId): array
    {
        $rows = Database::connect('default')->table('player_batch_assignments pba')->select('pba.*, b.name AS batch_name')
            ->join('batches b', 'b.id = pba.batch_id')->where('pba.player_id', $playerId)->orderBy('pba.effective_from', 'ASC')->get()->getResultArray();
        foreach ($rows as $row) {
            if (!service('authorization')->can('player.view', (string) $row['branch_id'])) {
                throw new RuntimeException('Forbidden');
            }
        }
        return array_map(static fn(array $row): array => ['batch_id' => (int) $row['batch_id'], 'batch_name' => $row['batch_name'] ?? null,
            'branch_id' => (int) $row['branch_id'], 'effective_from' => $row['effective_from'], 'effective_to' => $row['effective_to']], $rows);
    }
    private function coachHistory(int $playerId): array
    {
        $rows = Database::connect('default')->table('player_coach_assignments pca')->select('pca.*, u.display_name AS coach_name')
            ->join('users u', 'u.id = pca.coach_user_id')->where('pca.player_id', $playerId)->orderBy('pca.effective_from', 'ASC')->get()->getResultArray();
        foreach ($rows as $row) {
            if (!service('authorization')->can('player.view', (string) $row['branch_id'])) {
                throw new RuntimeException('Forbidden');
            }
        }
        return array_map(static fn(array $row): array => ['coach_user_id' => (int) $row['coach_user_id'], 'coach_name' => $row['coach_name'] ?? null,
            'branch_id' => (int) $row['branch_id'], 'effective_from' => $row['effective_from'], 'effective_to' => $row['effective_to']], $rows);
    }
    private function validDateTime(string $value): bool
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value);
        return $date !== false && $date->format('Y-m-d H:i:s') === $value;
    }
    /** @param array<string, mixed> $profile @return array<string, string> */
    private function changedFields(array $profile): array
    {
        return ['fields' => implode(',', array_values(array_intersect(self::PROFILE_FIELDS, array_keys($profile))))];
    }
}
