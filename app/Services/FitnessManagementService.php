<?php

declare(strict_types=1);

namespace App\Services;

use App\Modules\Fitness\Models\FitnessAssessmentModel;
use App\Modules\Fitness\Models\FitnessPlanModel;
use Config\Database;
use RuntimeException;

final class FitnessManagementService
{
    private const METRICS = [
        'height_cm', 'weight_kg', 'body_fat_percent', 'vertical_jump_cm',
        'sprint_time_seconds', 'agility_seconds', 'flexibility_cm', 'yoyo_level',
        'endurance_minutes',
    ];

    public function list(array $filters = []): array
    {
        $branches = $this->authorizedBranches();
        if ($branches === []) {
            return ['assessments' => [], 'total' => 0, 'page' => 1, 'pages' => 1, 'filters' => $filters];
        }

        $builder = Database::connect('default')->table('fitness_assessments fa')
            ->select('fa.*, pe.full_name, b.name AS branch_name')
            ->join('players p', 'p.id = fa.player_id')
            ->join('persons pe', 'pe.id = p.person_id')
            ->join('branches b', 'b.id = fa.branch_id')
            ->whereIn('fa.branch_id', $branches)
            ->where('fa.deleted_at', null);
        $this->restrictAssignedCoach($builder, 'fa.player_id');

        $query = trim((string) ($filters['q'] ?? ''));
        if ($query !== '') {
            $builder->like('pe.full_name', $query);
        }
        if (!empty($filters['branch_id']) && in_array((int) $filters['branch_id'], $branches, true)) {
            $builder->where('fa.branch_id', (int) $filters['branch_id']);
        }
        if (!empty($filters['from'])) {
            $builder->where('fa.assessment_date >=', (string) $filters['from']);
        }
        if (!empty($filters['to'])) {
            $builder->where('fa.assessment_date <=', (string) $filters['to']);
        }

        $total = (clone $builder)->countAllResults();
        $page = max(1, (int) ($filters['page'] ?? 1));
        $rows = $builder
            ->orderBy('fa.assessment_date', 'DESC')
            ->get(20, ($page - 1) * 20)
            ->getResultArray();

        return [
            'assessments' => array_map(fn(array $row): array => $this->presentAssessment($row), $rows),
            'total' => $total,
            'page' => $page,
            'pages' => max(1, (int) ceil($total / 20)),
            'filters' => $filters,
        ];
    }

    public function player(int $playerId): array
    {
        $player = $this->playerRow($playerId);
        $this->authorizePlayer($player, 'fitness.view');
        $db = Database::connect('default');
        $assessments = $db->table('fitness_assessments fa')
            ->select('fa.*, u.display_name AS recorder_name')
            ->join('users u', 'u.id = fa.recorded_by')
            ->where('fa.player_id', $playerId)
            ->where('fa.branch_id', $player['branch_id'])
            ->where('fa.deleted_at', null)
            ->orderBy('fa.assessment_date', 'DESC')
            ->get()
            ->getResultArray();
        $plans = $db->table('fitness_plans fp')
            ->select('fp.*, u.display_name AS coach_name')
            ->join('users u', 'u.id = fp.coach_user_id')
            ->where('fp.player_id', $playerId)
            ->where('fp.branch_id', $player['branch_id'])
            ->where('fp.deleted_at', null)
            ->orderBy('fp.starts_on', 'DESC')
            ->get()->getResultArray();

        return [
            'player' => $player,
            'assessments' => array_map(fn(array $row): array => $this->presentAssessment($row), $assessments),
            'plans' => $plans,
            'kpis' => $this->kpis($playerId, (int) $player['branch_id']),
            'monthly_trend' => $this->monthlyTrend($assessments),
        ];
    }

    public function saveAssessment(int $playerId, array $input, ?int $assessmentId = null): int
    {
        $player = $this->playerRow($playerId);
        $this->authorizePlayer($player, $assessmentId === null ? 'fitness.create' : 'fitness.update');
        $data = $this->validateAssessment($input);
        $now = date('Y-m-d H:i:s');
        $model = new FitnessAssessmentModel();
        if ($assessmentId !== null) {
            $existing = $model->where(['id' => $assessmentId, 'player_id' => $playerId, 'branch_id' => $player['branch_id'], 'deleted_at' => null])->first();
            if (!is_array($existing)) {
                throw new RuntimeException('Not found.');
            }
            $model->update($assessmentId, $data + ['updated_at' => $now]);
            $action = 'fitness_assessment_updated';
        } else {
            $assessmentId = (int) $model->insert($data + [
                'player_id' => $playerId,
                'branch_id' => $player['branch_id'],
                'recorded_by' => $this->actor(),
                'created_at' => $now,
                'updated_at' => $now,
            ], true);
            $action = 'fitness_assessment_created';
        }
        service('auditPersistence')->record(
            $this->actor(),
            $action,
            'fitness_assessment',
            (string) $assessmentId,
            (int) $player['branch_id']
        );
        return $assessmentId;
    }

    public function savePlan(int $playerId, array $input, ?int $planId = null): int
    {
        $player = $this->playerRow($playerId);
        $this->authorizePlayer($player, $planId === null ? 'fitness.plan.create' : 'fitness.plan.update');
        $name = trim((string) ($input['name'] ?? ''));
        $status = trim((string) ($input['status'] ?? 'active'));
        if ($name === '' || strlen($name) > 150 || !in_array($status, ['draft', 'active', 'completed', 'cancelled'], true)) {
            throw new RuntimeException('Invalid fitness plan.');
        }
        $startsOn = (string) ($input['starts_on'] ?? '');
        if (!$this->validDate($startsOn)) {
            throw new RuntimeException('Invalid fitness plan.');
        }
        $endsOn = trim((string) ($input['ends_on'] ?? ''));
        if ($endsOn !== '' && (!$this->validDate($endsOn) || $endsOn < $startsOn)) {
            throw new RuntimeException('Invalid fitness plan.');
        }
        $data = [
            'name' => $name,
            'goals' => $this->nullableText($input['goals'] ?? null, 10000),
            'starts_on' => $startsOn,
            'ends_on' => $endsOn === '' ? null : $endsOn,
            'status' => $status,
            'notes' => $this->nullableText($input['notes'] ?? null, 10000),
            'coach_user_id' => $this->planCoach($input, $player),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        $model = new FitnessPlanModel();
        if ($planId !== null) {
            $existing = $model->where(['id' => $planId, 'player_id' => $playerId, 'branch_id' => $player['branch_id'], 'deleted_at' => null])->first();
            if (!is_array($existing)) {
                throw new RuntimeException('Not found.');
            }
            $model->update($planId, $data);
            service('auditPersistence')->record(
                $this->actor(),
                'fitness_plan_updated',
                'fitness_plan',
                (string) $planId,
                (int) $player['branch_id']
            );
            return $planId;
        }
        $planId = (int) $model->insert($data + [
            'player_id' => $playerId,
            'branch_id' => $player['branch_id'],
            'created_by' => $this->actor(),
            'created_at' => date('Y-m-d H:i:s'),
        ], true);
        service('auditPersistence')->record(
            $this->actor(),
            'fitness_plan_created',
            'fitness_plan',
            (string) $planId,
            (int) $player['branch_id']
        );
        return $planId;
    }

    private function validateAssessment(array $input): array
    {
        $date = (string) ($input['assessment_date'] ?? '');
        if (!$this->validDate($date)) {
            throw new RuntimeException('Invalid fitness assessment.');
        }
        $data = ['assessment_date' => $date, 'coach_notes' => $this->nullableText($input['coach_notes'] ?? null, 10000)];
        foreach (self::METRICS as $metric) {
            $value = trim((string) ($input[$metric] ?? ''));
            if ($value === '') {
                $data[$metric] = null;
                continue;
            }
            if (!is_numeric($value) || (float) $value < 0) {
                throw new RuntimeException('Invalid fitness assessment.');
            }
            $data[$metric] = (float) $value;
        }
        return $data;
    }

    private function kpis(int $playerId, int $branchId): array
    {
        $db = Database::connect('default');
        $rows = $db->table('fitness_assessments')
            ->where(['player_id' => $playerId, 'branch_id' => $branchId])
            ->where('deleted_at', null)
            ->orderBy('assessment_date', 'DESC')
            ->get()
            ->getResultArray();
        $latest = $rows[0] ?? null;
        $previous = $rows[1] ?? null;
        $latestBmi = $this->bmi($latest);
        $previousBmi = $this->bmi($previous);
        return [
            'assessment_count' => count($rows),
            'active_plan_count' => $db->table('fitness_plans')
                ->where([
                    'player_id' => $playerId,
                    'branch_id' => $branchId,
                    'status' => 'active',
                ])
                ->where('deleted_at', null)
                ->countAllResults(),
            'latest_assessment_date' => $latest['assessment_date'] ?? null,
            'latest_bmi' => $latestBmi,
            'bmi_change' => $latestBmi !== null && $previousBmi !== null
                ? round($latestBmi - $previousBmi, 2)
                : null,
        ];
    }

    public function deleteAssessment(int $id): void
    {
        $row = Database::connect('default')->table('fitness_assessments')
            ->where(['id' => $id, 'deleted_at' => null])
            ->get()->getRowArray();
        if (!is_array($row)) {
            throw new RuntimeException('Not found.');
        }
        $this->authorizePlayer($this->playerRow((int) $row['player_id']), 'fitness.delete');
        $now = date('Y-m-d H:i:s');
        Database::connect('default')->table('fitness_assessments')->where('id', $id)->update(['deleted_at' => $now, 'updated_at' => $now]);
        service('auditPersistence')->record($this->actor(), 'fitness_assessment_deleted', 'fitness_assessment', (string) $id, (int) $row['branch_id']);
    }

    public function deletePlan(int $id): void
    {
        $row = Database::connect('default')->table('fitness_plans')
            ->where(['id' => $id, 'deleted_at' => null])
            ->get()->getRowArray();
        if (!is_array($row)) {
            throw new RuntimeException('Not found.');
        }
        $this->authorizePlayer($this->playerRow((int) $row['player_id']), 'fitness.plan.delete');
        $now = date('Y-m-d H:i:s');
        Database::connect('default')->table('fitness_plans')->where('id', $id)->update(['deleted_at' => $now, 'updated_at' => $now]);
        service('auditPersistence')->record($this->actor(), 'fitness_plan_deleted', 'fitness_plan', (string) $id, (int) $row['branch_id']);
    }

    private function presentAssessment(array $row): array
    {
        $row['bmi'] = $this->bmi($row);
        return $row;
    }

    /** @param list<array<string, mixed>> $assessments */
    private function monthlyTrend(array $assessments): array
    {
        $months = [];
        foreach ($assessments as $assessment) {
            $month = substr((string) $assessment['assessment_date'], 0, 7);
            $bmi = $this->bmi($assessment);
            if (!isset($months[$month])) {
                $months[$month] = ['month' => $month, 'assessment_count' => 0, 'bmi_total' => 0.0, 'bmi_count' => 0];
            }
            $months[$month]['assessment_count']++;
            if ($bmi !== null) {
                $months[$month]['bmi_total'] += $bmi;
                $months[$month]['bmi_count']++;
            }
        }
        foreach ($months as &$month) {
            $month['average_bmi'] = $month['bmi_count'] > 0
                ? round($month['bmi_total'] / $month['bmi_count'], 2)
                : null;
            unset($month['bmi_total'], $month['bmi_count']);
        }
        unset($month);
        return array_values($months);
    }

    private function bmi(?array $row): ?float
    {
        $height = (float) ($row['height_cm'] ?? 0);
        $weight = (float) ($row['weight_kg'] ?? 0);
        return $height > 0 && $weight > 0 ? round($weight / (($height / 100) ** 2), 2) : null;
    }

    private function planCoach(array $input, array $player): int
    {
        $coach = (int) ($input['coach_user_id'] ?? $this->actor());
        $valid = Database::connect('default')->table('user_branches ub')
            ->join('user_roles ur', 'ur.user_id = ub.user_id')
            ->join('roles r', 'r.id = ur.role_id')
            ->where(['ub.user_id' => $coach, 'ub.branch_id' => $player['branch_id']])
            ->whereIn('r.name', ['Coach', 'Fitness Coach'])
            ->countAllResults();
        if ($valid === 0 && $coach === $this->actor() && service('authorization')->can('fitness.create', (string) $player['branch_id'])) {
            $valid = 1;
        }
        if ($valid === 0) {
            throw new RuntimeException('Invalid coach.');
        }
        return $coach;
    }

    private function playerRow(int $id): array
    {
        $row = Database::connect('default')->table('players p')->select('p.id, p.branch_id, pe.full_name')
            ->join('persons pe', 'pe.id = p.person_id')->where('p.id', $id)->get()->getRowArray();
        if (!is_array($row)) {
            throw new RuntimeException('Not found.');
        }
        return $row;
    }

    private function authorizePlayer(array $player, string $permission): void
    {
        if (!service('authorization')->can($permission, (string) $player['branch_id'])) {
            throw new RuntimeException('Forbidden');
        }
        $roles = (array) session('roles');
        if (in_array('Coach', $roles, true) && !in_array('Fitness Coach', $roles, true) && !service('authorization')->can('rbac.manage')) {
            $assigned = Database::connect('default')->table('player_coach_assignments')->where([
                'player_id' => $player['id'],
                'branch_id' => $player['branch_id'],
                'coach_user_id' => $this->actor(),
                'effective_to' => null,
            ])->countAllResults();
            if ($assigned === 0) {
                throw new RuntimeException('Forbidden');
            }
        }
    }

    private function restrictAssignedCoach(object $builder, string $playerColumn): void
    {
        $roles = (array) session('roles');
        if (in_array('Coach', $roles, true) && !in_array('Fitness Coach', $roles, true) && !service('authorization')->can('rbac.manage')) {
            $builder->join('player_coach_assignments pca', "pca.player_id = {$playerColumn} AND pca.effective_to IS NULL", 'inner')
                ->where('pca.coach_user_id', $this->actor());
        }
    }

    private function actor(): int
    {
        $user = session('user');
        if (!is_array($user) || !isset($user['id'])) {
            throw new RuntimeException('Authentication required');
        }
        return (int) $user['id'];
    }

    private function authorizedBranches(): array
    {
        return service('branchContext')->authorizedBranchIds(true);
    }

    private function validDate(string $value): bool
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value;
    }

    private function nullableText(mixed $value, int $limit): ?string
    {
        $text = trim((string) ($value ?? ''));
        if (strlen($text) > $limit) {
            throw new RuntimeException('Invalid fitness data.');
        }
        return $text === '' ? null : $text;
    }
}
