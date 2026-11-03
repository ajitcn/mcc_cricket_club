<?php

declare(strict_types=1);

namespace App\Services;

use Closure;
use Config\Database;
use RuntimeException;

final class PlayerDevelopmentService
{
    private ?Closure $auditRecorder;

    public function __construct(?Closure $auditRecorder = null)
    {
        $this->auditRecorder = $auditRecorder;
    }

    public function list(array $filters = []): array
    {
        $branches = $this->developmentBranches();
        if ($branches === []) {
            return [
                'plans' => [],
                'total' => 0,
                'page' => 1,
                'pages' => 1,
                'filters' => $filters,
                'branches' => [],
            ];
        }

        $builder = $this->lineageQuery(Database::connect('default'))
            ->select('d.*, pe.full_name, b.name AS branch_name')
            ->whereIn('d.branch_id', $branches);

        $branchFilter = $this->validatedBranchFilter($filters['branch_id'] ?? null, $branches);
        if ($branchFilter !== null) {
            $builder->where('d.branch_id', $branchFilter);
        }

        if (($filters['q'] ?? '') !== '') {
            $builder->groupStart()
                ->like('d.title', trim((string) $filters['q']))
                ->orLike('pe.full_name', trim((string) $filters['q']))
                ->groupEnd();
        }

        if (in_array($filters['status'] ?? '', ['active', 'completed', 'cancelled'], true)) {
            $builder->where('d.status', $filters['status']);
        }

        $total = (clone $builder)->countAllResults();
        $perPage = 20;
        $page = max(1, (int) ($filters['page'] ?? 1));

        return [
            'plans' => $builder
                ->orderBy('d.updated_at', 'DESC')
                ->orderBy('d.id', 'DESC')
                ->get($perPage, ($page - 1) * $perPage)
                ->getResultArray(),
            'total' => $total,
            'page' => $page,
            'pages' => max(1, (int) ceil($total / $perPage)),
            'filters' => $filters,
            'branches' => $this->branchOptions($branches),
        ];
    }

    public function find(int $id): array
    {
        $plan = $this->plan($id);
        $this->allow((int) $plan['branch_id'], 'development.view');

        $plan['goals'] = $this->goalsWithHistory(Database::connect('default'), $id);

        return $plan;
    }

    /** @return array{plans: list<array<string, mixed>>} */
    public function forPlayer(int $playerId, int $branchId): array
    {
        $this->allow($branchId, 'development.view');

        $db = Database::connect('default');
        $plans = $this->lineageQuery($db)
            ->select(
                'd.id, d.title, d.status, d.target_date, d.updated_at, '
                . 'pe.full_name AS player_name, b.name AS branch_name, '
                . 'cu.display_name AS coach_name'
            )
            ->where([
                'd.player_id' => $playerId,
                'd.branch_id' => $branchId,
            ])
            ->orderBy('d.updated_at', 'DESC')
            ->get()
            ->getResultArray();

        foreach ($plans as &$plan) {
            $plan['goals'] = $this->goalsWithHistory($db, (int) $plan['id']);
        }
        unset($plan);

        return ['plans' => $plans];
    }

    /** @return array{branches: list<array<string, mixed>>, players: list<array<string, mixed>>, coaches: list<array<string, mixed>>, programs: list<array<string, mixed>>, batches: list<array<string, mixed>>} */
    public function formOptions(?int $branchId = null): array
    {
        $branches = $this->developmentBranches();
        if ($branches === []) {
            return [
                'branches' => [],
                'players' => [],
                'coaches' => [],
                'programs' => [],
                'batches' => [],
            ];
        }

        $selectedBranch = $this->validatedBranchFilter($branchId, $branches);
        $db = Database::connect('default');
        $branchQuery = $db->table('branches')
            ->whereIn('id', $branches)
            ->orderBy('name');

        $playerQuery = $db->table('players p')
            ->select('p.id, p.branch_id, pe.full_name, b.name AS branch_name')
            ->join('persons pe', 'pe.id = p.person_id')
            ->join('branches b', 'b.id = p.branch_id')
            ->whereIn('p.branch_id', $branches)
            ->orderBy('b.name')
            ->orderBy('pe.full_name');

        $coachQuery = $db->table('users u')
            ->select('u.id, u.display_name, ub.branch_id, b.name AS branch_name')
            ->join('user_roles ur', 'ur.user_id = u.id')
            ->join('roles r', 'r.id = ur.role_id')
            ->join('user_branches ub', 'ub.user_id = u.id')
            ->join('branches b', 'b.id = ub.branch_id')
            ->join('coach_profiles cp', 'cp.user_id = u.id')
            ->where([
                'u.is_enabled' => 1,
                'cp.is_active' => 1,
                'r.name' => 'Coach',
            ])
            ->whereIn('ub.branch_id', $branches)
            ->orderBy('b.name')
            ->orderBy('u.display_name');

        $programQuery = $db->table('programs')
            ->orderBy('name');

        $batchQuery = $db->table('batches ba')
            ->select('ba.id, ba.name')
            ->where('ba.is_active', 1)
            ->orderBy('ba.name');

        if ($selectedBranch !== null) {
            $playerQuery->where('p.branch_id', $selectedBranch);
            $coachQuery->where('ub.branch_id', $selectedBranch);
        }

        return [
            'branches' => $branchQuery->get()->getResultArray(),
            'players' => $playerQuery->get()->getResultArray(),
            'coaches' => $coachQuery->get()->getResultArray(),
            'programs' => $programQuery->get()->getResultArray(),
            'batches' => $batchQuery->get()->getResultArray(),
        ];
    }

    public function save(array $input, ?int $id = null): int
    {
        $branch = (int) ($input['branch_id'] ?? 0);
        $existing = $id === null ? null : $this->plan($id);

        if ($existing !== null) {
            $this->allow((int) $existing['branch_id'], 'development.update');
            if ($branch !== (int) $existing['branch_id']) {
                throw new RuntimeException('Invalid development plan.');
            }
            $this->assertPlanEditable($existing);
        } else {
            $this->allow($branch, 'development.create');
        }

        $player = (int) ($input['player_id'] ?? 0);
        $db = Database::connect('default');

        if ($db->table('players')
            ->where(['id' => $player, 'branch_id' => $branch])
            ->countAllResults() === 0) {
            throw new RuntimeException('Player is not eligible.');
        }

        $coach = (int) ($input['coach_user_id'] ?? 0);
        $this->assertEligibleCoach($db, $coach, $branch);

        $targetDate = $this->targetDate($input['target_date'] ?? null);
        $data = [
            'branch_id' => $branch,
            'player_id' => $player,
            'coach_user_id' => $coach,
            'title' => trim((string) ($input['title'] ?? '')),
            'status' => trim((string) ($input['status'] ?? 'active')),
            'target_date' => $targetDate,
            'notes' => trim((string) ($input['notes'] ?? '')) ?: null,
        ];

        if ($data['title'] === '' || ! in_array($data['status'], ['active', 'completed', 'cancelled'], true)) {
            throw new RuntimeException('Invalid development plan.');
        }

        $now = gmdate('Y-m-d H:i:s');
        $db->transBegin();

        try {
            if ($id === null) {
                $db->table('development_plans')->insert(
                    $data + [
                        'created_by' => $this->actor(),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                    true
                );
                $id = (int) $db->insertID();
                $event = 'development_plan_created';
            } else {
                $db->table('development_plans')
                    ->where('id', $id)
                    ->update($data + ['updated_at' => $now]);
                $event = 'development_plan_updated';
            }

            $this->audit(
                $this->actor(),
                $event,
                'development_plan',
                (string) $id,
                $branch,
                $existing,
                $data,
                $db
            );

            if (! $db->transStatus()) {
                throw new RuntimeException('Unable to save development plan.');
            }

            $db->transCommit();
        } catch (\Throwable $exception) {
            $db->transRollback();
            throw $exception;
        }

        return $id;
    }

    public function goal(int $planId, array $input): int
    {
        $plan = $this->plan($planId);
        $this->allow((int) $plan['branch_id'], 'development.update');
        $this->assertPlanEditable($plan);

        $goal = trim((string) ($input['goal'] ?? ''));
        if ($goal === '') {
            throw new RuntimeException('Invalid development goal.');
        }

        $db = Database::connect('default');
        $now = gmdate('Y-m-d H:i:s');
        $targetDate = $this->targetDate($input['target_date'] ?? null);
        $data = [
            'plan_id' => $planId,
            'goal' => $goal,
            'status' => 'open',
            'target_date' => $targetDate,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $db->transBegin();

        try {
            $db->table('development_goals')->insert($data);
            $goalId = (int) $db->insertID();

            $this->audit(
                $this->actor(),
                'development_goal_created',
                'development_goal',
                (string) $goalId,
                (int) $plan['branch_id'],
                null,
                $data,
                $db
            );

            if (! $db->transStatus()) {
                throw new RuntimeException('Unable to create development goal.');
            }

            $db->transCommit();
        } catch (\Throwable $exception) {
            $db->transRollback();
            throw $exception;
        }

        return $goalId;
    }

    public function progress(int $goalId, array $input): void
    {
        $row = $this->goalRow($goalId);
        $this->allow((int) $row['branch_id'], 'development.progress');
        $plan = $this->plan((int) $row['plan_id']);
        $this->assertPlanEditable($plan);

        $expectedVersion = filter_var(
            $input['version'] ?? $input['expected_version'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );
        if ($expectedVersion === false) {
            throw new RuntimeException('A current development goal version is required.');
        }

        $rawValue = $input['progress_value'] ?? '';
        if ($rawValue !== '' && ! is_numeric($rawValue)) {
            throw new RuntimeException('Invalid development progress.');
        }

        $value = $rawValue === '' ? null : (float) $rawValue;
        $note = trim((string) ($input['note'] ?? '')) ?: null;
        $now = gmdate('Y-m-d H:i:s');
        $nextVersion = (int) $row['version'] + 1;
        $db = Database::connect('default');

        $db->transBegin();

        try {
            $db->table('development_progress_history')->insert([
                'goal_id' => $goalId,
                'progress_value' => $value,
                'note' => $note,
                'recorded_by' => $this->actor(),
                'recorded_at' => $now,
            ]);

            $updated = $db->table('development_goals')
                ->where(['id' => $goalId, 'version' => (int) $expectedVersion])
                ->update([
                    'progress_value' => $value,
                    'version' => $nextVersion,
                    'updated_at' => $now,
                ]);

            if ($updated !== true || $db->affectedRows() !== 1) {
                throw new RuntimeException('Development goal was changed by another user.');
            }

            $this->audit(
                $this->actor(),
                'development_progress_recorded',
                'development_goal',
                (string) $goalId,
                (int) $row['branch_id'],
                [
                    'progress_value' => $row['progress_value'],
                    'version' => $row['version'],
                ],
                [
                    'progress_value' => $value,
                    'version' => $nextVersion,
                    'note' => $note,
                ],
                $db
            );

            if (! $db->transStatus()) {
                throw new RuntimeException('Unable to record development progress.');
            }

            $db->transCommit();
        } catch (\Throwable $exception) {
            $db->transRollback();
            throw $exception;
        }
    }

    public function planIdForGoal(int $goalId): int
    {
        $row = $this->goalRow($goalId);
        $this->allow((int) $row['branch_id'], 'development.progress');

        return (int) $row['plan_id'];
    }

    public function editable(int $id): array
    {
        $plan = $this->plan($id);
        $this->allow((int) $plan['branch_id'], 'development.update');
        $this->assertPlanEditable($plan);

        return $plan;
    }

    private function plan(int $id): array
    {
        $row = $this->lineageQuery(Database::connect('default'))
            ->select('d.*, pe.full_name AS player_name, b.name AS branch_name, cu.display_name AS coach_name')
            ->where('d.id', $id)
            ->get()
            ->getRowArray();

        if (! is_array($row)) {
            throw new RuntimeException('Not found.');
        }

        return $row;
    }

    private function goalRow(int $goalId): array
    {
        $row = $this->lineageQuery(Database::connect('default'))
            ->select('g.*, d.branch_id, d.id AS plan_id')
            ->join('development_goals g', 'g.plan_id = d.id')
            ->where('g.id', $goalId)
            ->get()
            ->getRowArray();

        if (! is_array($row)) {
            throw new RuntimeException('Not found.');
        }

        return $row;
    }

    private function assertPlanEditable(array $plan): void
    {
        if ((string) ($plan['status'] ?? '') === 'completed') {
            throw new RuntimeException('Completed development plans cannot be edited or extended.');
        }
    }

    private function lineageQuery(object $db): object
    {
        return $db->table('development_plans d')
            ->join('players p', 'p.id = d.player_id AND p.branch_id = d.branch_id')
            ->join('persons pe', 'pe.id = p.person_id')
            ->join('branches b', 'b.id = d.branch_id')
            ->join('users cu', 'cu.id = d.coach_user_id')
            ->where($this->eligibleCoachExists('d'), null, false);
    }

    private function goalsWithHistory(object $db, int $planId): array
    {
        $goals = $db->table('development_goals')
            ->where('plan_id', $planId)
            ->orderBy('id')
            ->get()
            ->getResultArray();

        foreach ($goals as &$goal) {
            $goal['history'] = $db->table('development_progress_history')
                ->where('goal_id', $goal['id'])
                ->orderBy('recorded_at', 'DESC')
                ->get()
                ->getResultArray();
        }
        unset($goal);

        return $goals;
    }

    private function assertEligibleCoach(object $db, int $coach, int $branch): void
    {
        $eligible = $db->table('users u')
            ->join('user_roles ur', 'ur.user_id = u.id')
            ->join('roles r', 'r.id = ur.role_id')
            ->join('user_branches ub', 'ub.user_id = u.id')
            ->join('coach_profiles cp', 'cp.user_id = u.id')
            ->where([
                'u.id' => $coach,
                'u.is_enabled' => 1,
                'ub.branch_id' => $branch,
                'cp.is_active' => 1,
                'r.name' => 'Coach',
            ])
            ->countAllResults();

        if ($eligible === 0) {
            throw new RuntimeException('Invalid coach.');
        }
    }

    private function eligibleCoachExists(string $planAlias): string
    {
        return "EXISTS (SELECT 1 FROM users cu
            INNER JOIN user_roles cur ON cur.user_id = cu.id
            INNER JOIN roles cr ON cr.id = cur.role_id
            INNER JOIN user_branches cub ON cub.user_id = cu.id
            INNER JOIN coach_profiles ccp ON ccp.user_id = cu.id
            WHERE cu.id = {$planAlias}.coach_user_id
            AND cu.is_enabled = 1
            AND cub.branch_id = {$planAlias}.branch_id
            AND ccp.is_active = 1
            AND cr.name = 'Coach')";
    }

    private function developmentBranches(): array
    {
        return array_values(array_filter(
            $this->branches(),
            fn (int $branch): bool => service('authorization')->can('development.view', (string) $branch),
        ));
    }

    private function branchOptions(array $branches): array
    {
        return Database::connect('default')
            ->table('branches')
            ->whereIn('id', $branches)
            ->where('is_active', 1)
            ->orderBy('name')
            ->get()
            ->getResultArray();
    }

    private function validatedBranchFilter(mixed $value, array $branches): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_scalar($value) || ! ctype_digit((string) $value)) {
            return 0;
        }

        $branch = (int) $value;
        return in_array($branch, $branches, true) ? $branch : 0;
    }

    private function targetDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_string($value) || ! $this->validDate($value)) {
            throw new RuntimeException('Invalid development date.');
        }

        return $value;
    }

    private function validDate(string $value): bool
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value;
    }

    private function audit(
        ?int $actor,
        string $action,
        string $type,
        string $id,
        ?int $branch,
        ?array $before,
        ?array $after,
        object $db
    ): void {
        if ($this->auditRecorder !== null) {
            ($this->auditRecorder)(
                $actor,
                $action,
                $type,
                $id,
                $branch,
                $before,
                $after,
                $db
            );
            return;
        }

        service('auditPersistence')->record(
            $actor,
            $action,
            $type,
            $id,
            $branch,
            $before,
            $after,
            null,
            $db
        );
    }

    private function branches(): array
    {
        return service('branchContext')->authorizedBranchIds(true);
    }

    private function allow(int $branch, string $permission): void
    {
        if (! service('authorization')->can($permission, (string) $branch)) {
            throw new RuntimeException('Forbidden');
        }
    }

    private function actor(): int
    {
        $user = session('user');
        if (! is_array($user) || ! isset($user['id'])) {
            throw new RuntimeException('Authentication required');
        }

        return (int) $user['id'];
    }
}
