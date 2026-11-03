<?php

declare (strict_types=1);
namespace App\Services;

use App\Modules\Scheduling\Models\SessionModel;
use Config\Database;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;
use Throwable;
final class SchedulingService
{
    private const SESSIONS_PER_PAGE = 20;

    private const SESSION_TYPES = ['one_to_one', 'group', 'elite'];

    /** @return array<string, array{completed: int, target: int|null}> */
    public function sessionProgressForAdmission(int $admissionId): array
    {
        $db = Database::connect('default');
        $admission = $db->table('admissions')
            ->select('id, converted_player_id, estimated_personal_session, estimated_elite_session')
            ->where('id', $admissionId)
            ->get()
            ->getRowArray();

        if (! is_array($admission)) {
            return $this->emptySessionProgress();
        }

        $playerId = (int) ($admission['converted_player_id'] ?? 0);
        if ($playerId < 1) {
            $playerId = (int) ($db->table('players')
                ->select('id')
                ->where('admission_id', $admissionId)
                ->orderBy('id', 'ASC')
                ->get()
                ->getRow('id') ?? 0);
        }

        return $this->sessionProgress(
            $playerId,
            $admission['estimated_personal_session'] ?? null,
            $admission['estimated_elite_session'] ?? null,
        );
    }

    /** @return array<string, array{completed: int, target: int|null}> */
    public function sessionProgressForPlayer(int $playerId): array
    {
        $db = Database::connect('default');
        $player = $db->table('players p')
            ->select('p.id, p.admission_id, a.estimated_personal_session, a.estimated_elite_session')
            ->join('admissions a', 'a.id = p.admission_id', 'left')
            ->where('p.id', $playerId)
            ->get()
            ->getRowArray();

        if (! is_array($player)) {
            return $this->emptySessionProgress();
        }

        return $this->sessionProgress(
            $playerId,
            $player['estimated_personal_session'] ?? null,
            $player['estimated_elite_session'] ?? null,
        );
    }

    /** @return array<string, array{completed: int, target: int|null}> */
    private function sessionProgress(int $playerId, mixed $personalTarget, mixed $eliteTarget): array
    {
        $progress = $this->emptySessionProgress();
        $progress['personal']['target'] = $this->positiveTarget($personalTarget);
        $progress['elite']['target'] = $this->positiveTarget($eliteTarget);

        if ($playerId < 1 || ! Database::connect('default')->tableExists('session_players')) {
            return $progress;
        }

        $rows = Database::connect('default')->table('session_players sp')
            ->select('s.session_type, COUNT(DISTINCT s.id) AS completed_count')
            ->join('sessions s', 's.id = sp.session_id')
            ->where('sp.player_id', $playerId)
            ->where("LOWER(s.status) = 'completed'", null, false)
            ->where("LOWER(s.session_type) IN ('one_to_one', 'personal', 'elite')", null, false)
            ->groupBy('s.session_type')
            ->get()
            ->getResultArray();

        foreach ($rows as $row) {
            $key = (string) ($row['session_type'] ?? '');
            $key = in_array($key, ['one_to_one', 'personal'], true) ? 'personal' : $key;
            if (array_key_exists($key, $progress)) {
                $progress[$key]['completed'] = (int) ($row['completed_count'] ?? 0);
            }
        }

        return $progress;
    }

    /** @return array<string, array{completed: int, target: int|null}> */
    private function emptySessionProgress(): array
    {
        return [
            'personal' => ['completed' => 0, 'target' => null],
            'elite' => ['completed' => 0, 'target' => null],
        ];
    }

    private function positiveTarget(mixed $value): ?int
    {
        $target = filter_var($value, FILTER_VALIDATE_INT);

        return $target !== false && $target > 0 ? $target : null;
    }

    public function sessions(array $filters = []): array
    {
        return $this->sessionPage($filters)['rows'];
    }

    public function sessionPage(array $filters = []): array
    {
        $model = new SessionModel();
        $branchIds = $this->authorizedBranchIds();
        $coachScopeUserId = $this->strictCoachActorId();
        $page = $this->pageNumber($filters['page'] ?? 1);
        $queryFilters = $this->sessionFilters($filters);
        $total = $model->countSessionRows($queryFilters, $branchIds, $coachScopeUserId);
        $pages = max(1, (int) ceil($total / self::SESSIONS_PER_PAGE));
        $page = min($page, $pages);
        $rows = $model->sessionRows(
            $queryFilters,
            $branchIds,
            self::SESSIONS_PER_PAGE,
            ($page - 1) * self::SESSIONS_PER_PAGE,
            $coachScopeUserId
        );
        foreach ($rows as &$row) {
            $row['starts_at_local'] = $this->local((string) $row['starts_at']);
            $row['ends_at_local'] = $row['ends_at'] === null
                ? null
                : $this->local((string) $row['ends_at']);
        }
        return [
            'rows' => $rows,
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'perPage' => self::SESSIONS_PER_PAGE,
        ];
    }

    public function sessionFilterOptions(): array
    {
        return (new SessionModel())->sessionFilterOptions(
            $this->authorizedBranchIds(),
            $this->strictCoachActorId()
        );
    }

    public function isCoachScoped(): bool
    {
        return $this->isCoachRole();
    }

    public function availabilityWindows(int $coachUserId, int $branchId, string $date): array
    {
        $canCreate = service('authorization')->can('session.create', (string) $branchId);
        $canUpdate = service('authorization')->can('session.update', (string) $branchId);
        if (! $canCreate && ! $canUpdate) {
            throw new RuntimeException('Forbidden');
        }

        $db = Database::connect('default');
        $this->assertCoachBranchScope(
            $branchId,
            $canCreate ? 'session.create' : 'session.update'
        );
        $this->assertCoachSelection($coachUserId, $branchId);
        $this->coach($db, $coachUserId, $branchId);
        $localDate = $this->calendarDate($date);
        $dateValue = $localDate->format('Y-m-d');
        $weekday = (int) $localDate->format('N');
        $records = [];

        if ($db->tableExists('coach_availability')) {
            $records = $db->table('coach_availability')
                ->select('start_time, end_time')
                ->where([
                    'coach_user_id' => $coachUserId,
                    'branch_id' => $branchId,
                    'cancelled_at' => null,
                ])
                ->groupStart()
                ->where('available_date', $dateValue)
                ->orGroupStart()
                ->where('available_date', null)
                ->where('weekday', $weekday)
                ->groupEnd()
                ->groupEnd()
                ->orderBy('start_time', 'ASC')
                ->get()
                ->getResultArray();
        }

        $startUtc = $localDate->setTime(0, 0)->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $endUtc = $localDate->modify('+1 day')->setTime(0, 0)->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $onLeave = is_array($db->table('coach_unavailability')
            ->where([
                'coach_user_id' => $coachUserId,
                'branch_id' => $branchId,
                'status' => 'APPROVED',
            ])
            ->where('unavailable_from <', $endUtc)
            ->where('unavailable_to >', $startUtc)
            ->get()
            ->getRowArray());

        return [
            'date' => $dateValue,
            'windows' => array_map(static fn(array $row): array => [
                'start' => substr((string) $row['start_time'], 0, 5),
                'end' => substr((string) $row['end_time'], 0, 5),
            ], $records),
            'on_leave' => $onLeave,
            'manage_url' => service('authorization')->can('coach.view', (string) $branchId)
                ? site_url('coaches/' . $coachUserId)
                : null,
        ];
    }

    public function sessionFormOptions(
        string $permission = 'session.create',
        ?int $branchId = null,
        bool $requireActiveBranch = false,
        ?int $selectedSkillOptionId = null,
        ?int $selectedAgeGroupId = null
    ): array
    {
        $branchIds = $this->authorizedBranchIds($permission);
        $needsActiveBranch = $requireActiveBranch
            && service('branchContext')->isBranchAdminContext()
            && service('branchContext')->activeBranchId() === null;
        if ($requireActiveBranch && service('branchContext')->isBranchAdminContext()) {
            $activeBranchId = service('branchContext')->activeBranchId();
            $branchIds = $activeBranchId === null ? [] : [$activeBranchId];
        }
        if ($branchId !== null) {
            $branchIds = in_array($branchId, $branchIds, true) ? [$branchId] : [];
        }
        if ($branchIds === []) {
            return [
                'branches' => [],
                'programs' => [],
                'batches' => [],
                'age_groups' => [],
                'coaches' => [],
                'default_branch_id' => null,
                'default_coach_user_id' => $this->defaultCoachUserId(),
                'grounds' => [],
                'players' => [],
                'skillOptions' => [],
                'needs_active_branch' => $needsActiveBranch,
            ];
        }

        $db = Database::connect('default');
        $skillOptions = [];
        if ($db->tableExists('session_skill_options')) {
            $skillQuery = $db->table('session_skill_options')
                ->select('id, area, name, is_active, retired_at, display_order')
                ->groupStart()
                ->where('is_active', 1);
            if ($selectedSkillOptionId !== null && $selectedSkillOptionId > 0) {
                $skillQuery->orWhere('id', $selectedSkillOptionId);
            }
            $skillQuery->groupEnd();
            $skillOptions = $skillQuery
                ->orderBy('area', 'ASC')
                ->orderBy('display_order', 'ASC')
                ->get()
                ->getResultArray();
        }
        $branches = $db->table('branches')
            ->select('id, name')
            ->whereIn('id', $branchIds)
            ->where('is_active', 1)
            ->orderBy('name', 'ASC')
            ->get()
            ->getResultArray();
        $defaultBranchId = $this->defaultCoachBranchId($branches);
        $batches = $db->table('batches ba')
            ->select('ba.id, ba.name')
            ->where('ba.is_active', 1)
            ->orderBy('ba.name', 'ASC')
            ->get()
            ->getResultArray();
        $ageGroups = $db->table('admission_age_groups ag')
            ->select('ag.id, ag.name')
            ->where('ag.is_active', 1)
            ->where('ag.branch_id', null)
            ->orderBy('ag.display_order', 'ASC')
            ->orderBy('ag.name', 'ASC')
            ->get()
            ->getResultArray();
        if ($selectedAgeGroupId !== null && $selectedAgeGroupId > 0) {
            $selectedAgeGroup = $db->table('admission_age_groups ag')
                ->select('ag.id, ag.name')
                ->where('ag.id', $selectedAgeGroupId)
                ->where('ag.branch_id', null)
                ->get()
                ->getRowArray();
            if (is_array($selectedAgeGroup)
                && ! in_array(
                    (int) $selectedAgeGroup['id'],
                    array_map(static fn (array $row): int => (int) $row['id'], $ageGroups),
                    true
                )) {
                $ageGroups[] = $selectedAgeGroup;
            }
        }
        $programs = $db->table('programs p')
            ->select('p.id, p.name')
            ->orderBy('p.name', 'ASC')
            ->get()
            ->getResultArray();
        $coaches = $db->table('user_branches ub')
            ->select('u.id, u.display_name, ub.branch_id, b.name AS branch_name')
            ->join('users u', 'u.id = ub.user_id')
            ->join('branches b', 'b.id = ub.branch_id')
            ->join('coach_profiles cp', 'cp.user_id = ub.user_id', 'left')
            ->join('user_roles ur', 'ur.user_id = ub.user_id')
            ->join('roles r', 'r.id = ur.role_id')
            ->whereIn('ub.branch_id', $branchIds)
            ->where('b.is_active', 1)
            ->whereIn('r.name', ['Coach', 'Fitness Coach'])
            ->where('u.is_enabled', 1)
            ->groupStart()
            ->where('cp.is_active', 1)
            ->orWhere('cp.id IS NULL', null, false)
            ->groupEnd()
            ->distinct();
        $coachScopeUserId = $this->strictCoachActorId();
        if ($coachScopeUserId !== null) {
            $coaches->where('ub.user_id', $coachScopeUserId);
        }
        $coaches = $coaches
            ->orderBy('u.display_name', 'ASC')
            ->get()
            ->getResultArray();
        $bookableBranchIds = array_values(array_filter(
            $branchIds,
            static fn (int $branchId): bool => service('authorization')->can(
                'ground.book',
                (string) $branchId
            )
        ));
        $grounds = $bookableBranchIds === []
            ? []
            : $db->table('grounds g')
                ->select('g.id, g.name, g.branch_id, b.name AS branch_name')
                ->join('branches b', 'b.id = g.branch_id')
                ->whereIn('g.branch_id', $bookableBranchIds)
                ->where('b.is_active', 1)
                ->where('g.is_active', 1)
                ->orderBy('g.name', 'ASC')
                ->get()
                ->getResultArray();
        $now = gmdate('Y-m-d H:i:s');
        $players = $db->table('players p')
            ->select(
                'p.id, pe.full_name, p.branch_id, b.name AS branch_name, '
                . '(SELECT pba.batch_id FROM player_batch_assignments pba '
                . 'WHERE pba.player_id = p.id AND pba.branch_id = p.branch_id '
                . 'AND pba.effective_from <= ' . $db->escape($now) . ' '
                . 'AND (pba.effective_to IS NULL OR pba.effective_to > ' . $db->escape($now) . ') '
                . 'ORDER BY pba.effective_from DESC, pba.id DESC LIMIT 1) AS batch_id, '
                . '(SELECT bt.name FROM player_batch_assignments pba '
                . 'INNER JOIN batches bt ON bt.id = pba.batch_id '
                . 'WHERE pba.player_id = p.id AND pba.branch_id = p.branch_id '
                . 'AND pba.effective_from <= ' . $db->escape($now) . ' '
                . 'AND (pba.effective_to IS NULL OR pba.effective_to > ' . $db->escape($now) . ') '
                . 'ORDER BY pba.effective_from DESC, pba.id DESC LIMIT 1) AS batch_name, '
                . '(SELECT pr.name FROM player_batch_assignments pba '
                . 'INNER JOIN batches bt ON bt.id = pba.batch_id '
                . 'INNER JOIN programs pr ON pr.id = bt.program_id '
                . 'WHERE pba.player_id = p.id AND pba.branch_id = p.branch_id '
                . 'AND pba.effective_from <= ' . $db->escape($now) . ' '
                . 'AND (pba.effective_to IS NULL OR pba.effective_to > ' . $db->escape($now) . ') '
                . 'ORDER BY pba.effective_from DESC, pba.id DESC LIMIT 1) AS program_name, '
                . 'a.age_group_id AS age_group_id, '
                . 'ag.name AS age_group_name, '
                . 'CASE WHEN a.id IS NOT NULL THEN '
                . 'CASE WHEN a.coach_user_id IS NOT NULL '
                . 'AND a.coach_user_id = a.personal_coach_user_id '
                . 'THEN CAST(a.coach_user_id AS CHAR) '
                . 'ELSE CONCAT_WS(\',\', NULLIF(a.coach_user_id, 0), '
                . 'NULLIF(a.personal_coach_user_id, 0)) END '
                . 'ELSE (SELECT GROUP_CONCAT(DISTINCT pca.coach_user_id) '
                . 'FROM player_coach_assignments pca '
                . 'WHERE pca.player_id = p.id AND pca.branch_id = p.branch_id '
                . 'AND pca.effective_from <= ' . $db->escape($now) . ' '
                . 'AND (pca.effective_to IS NULL OR pca.effective_to > ' . $db->escape($now) . ')) '
                . 'END AS coach_user_ids',
                false
            )
            ->join('persons pe', 'pe.id = p.person_id')
            ->join('branches b', 'b.id = p.branch_id')
            ->join('admissions a', 'a.id = p.admission_id', 'left')
            ->join('admission_age_groups ag', 'ag.id = a.age_group_id', 'left')
            ->whereIn('p.branch_id', $branchIds)
            ->where('b.is_active', 1)
            ->where('p.status', 'active')
            ->distinct();
        if ($coachScopeUserId !== null) {
            $players->where(
                '(a.id IS NOT NULL AND (a.coach_user_id = ' . $coachScopeUserId
                . ' OR a.personal_coach_user_id = ' . $coachScopeUserId . ')) '
                . 'OR (a.id IS NULL AND EXISTS (
                    SELECT 1 FROM player_coach_assignments pca
                    WHERE pca.player_id = p.id AND pca.branch_id = p.branch_id
                    AND pca.coach_user_id = ' . $coachScopeUserId . '
                    AND pca.effective_from <= ' . $db->escape($now) . '
                    AND (pca.effective_to IS NULL OR pca.effective_to > ' . $db->escape($now) . ')
                ))',
                null,
                false
            );
        }
        $players = $players
            ->orderBy('pe.full_name', 'ASC')
            ->get()
            ->getResultArray();

        return [
            'branches' => $branches,
            'programs' => $programs,
            'batches' => $batches,
            'age_groups' => $ageGroups,
            'coaches' => $coaches,
            'default_branch_id' => $defaultBranchId,
            'default_coach_user_id' => $this->defaultCoachUserId(),
            'grounds' => $grounds,
            'players' => $players,
            'skillOptions' => $skillOptions,
            'needs_active_branch' => false,
        ];
    }

    public function create(array $input): int
    {
        $this->validateStatus((string) ($input['status'] ?? 'scheduled'));
        $this->validateSessionType($input['session_type'] ?? null);
        $input['program_id'] = $this->normalizeProgramId($input['program_id'] ?? null);
        $input['description'] = $this->normalizeDescription($input['description'] ?? null);
        $input['starts_at'] = $this->utc((string) ($input['starts_at'] ?? ''));
        $input['ends_at'] = $this->optionalUtc($input['ends_at'] ?? null);
        $input['batch_id'] = $this->normalizeOptionalBatchId($input['batch_id'] ?? null);
        $input['age_group_id'] = $this->normalizeOptionalAgeGroupId($input['age_group_id'] ?? null);
        $this->window($input);
        $this->validateCreateInput($input);
        $db = Database::connect('default');
        $db->transStart();
        $lockedResources = [];
        try {
            $actor = $this->actor();
            if ($input['batch_id'] !== null) {
                $batch = $db->table('batches')
                    ->where('id', $input['batch_id'])
                    ->where('is_active', 1)
                    ->get()
                    ->getRowArray();
                if (!is_array($batch)) {
                    throw new RuntimeException('Invalid batch.');
                }
            }
            $this->validateAgeGroup($db, $input['age_group_id']);
            $branch = (int) ($input['branch_id'] ?? 0);
            $submittedBranch = (int) ($input['branch_id'] ?? 0);
            if ($submittedBranch <= 0 || $submittedBranch !== $branch) {
                throw new RuntimeException('Invalid branch.');
            }
            $this->assertCoachBranchScope($branch);
            $this->allow($branch, 'session.create');
            $this->validateProgram($db, $input['program_id'], $branch);
            $skill = $this->skillOption($db, $input, $branch);
            $groundId = (int) ($input['ground_id'] ?? 0);
            if ($groundId > 0) {
                $this->allow($branch, 'ground.book');
                $this->validateGround($db, $groundId, $branch);
            }
            $coach = (int) ($input['coach_user_id'] ?? 0);
            $this->coach($db, $coach, $branch);
            $playerIds = array_key_exists('player_ids', $input)
                ? $this->playerIds($input['player_ids'])
                : [];
            $this->assertCoachSelection($coach, $branch);
            $this->assertCoachPlayerScope($db, $playerIds, $branch, $coach);
            $this->validateRoster(
                $db,
                $playerIds,
                $input['batch_id'],
                $input['age_group_id'],
                $branch,
                $input['starts_at']
            );
            $lockedResources = $this->lockResources(
                $db,
                [$coach],
                $groundId > 0 ? [$groundId] : [],
                $playerIds
            );
            if ($groundId > 0 && $input['ends_at'] !== null) {
                service('ground')->ensureBookable($groundId, $branch, $input['starts_at'], $input['ends_at']);
            }
            $this->approvedLeaveConflict($db, $coach, $branch, $input['starts_at'], $input['ends_at']);
            $this->conflict($db, $coach, $branch, $input['starts_at'], $input['ends_at']);
            $this->playerConflicts($db, $playerIds, $branch, $input['starts_at'], $input['ends_at']);
            $id = $this->insert($db, $input, $branch, $actor, null, $skill);
            if ($playerIds !== []) {
                $this->roster($db, $id, $playerIds, $branch, $input['starts_at']);
            }
            service('auditPersistence')->record(
                $actor,
                'session_created',
                'session',
                (string) $id,
                $branch,
                null,
                [
                    'batch_id' => $input['batch_id'],
                    'age_group_id' => $input['age_group_id'],
                    'coach_user_id' => $coach,
                    'starts_at' => $input['starts_at'],
                    'ends_at' => $input['ends_at'],
                    'session_type' => $input['session_type'],
                    'description' => $input['description'],
                    'skill_area' => $skill['skill_area'],
                    'skill_option_id' => $skill['skill_option_id'],
                ],
                null,
                $db
            );
            $db->transComplete();
            if (!$db->transStatus()) {
                throw new RuntimeException('Unable to create session.');
            }
            $this->releaseResources($db, $lockedResources);
            return $id;
        } catch (Throwable $e) {
            $db->transRollback();
            $db->resetTransStatus();
            $this->releaseResources($db, $lockedResources);
            throw $e;
        }
    }
    public function find(int $id): array
    {
        $db = Database::connect('default');
        $hasProgramColumn = $db->fieldExists('program_id', 'sessions');
        $programSelect = $hasProgramColumn
            ? 's.program_id AS program_id'
            : 'ba.program_id AS program_id';
        $programJoin = $hasProgramColumn
            ? 'p.id = s.program_id'
            : 'p.id = ba.program_id';
        $r = $db->table('sessions s')
            ->select(
                's.*, b.name AS branch_name, ba.name AS batch_name, '
                . 'ag.name AS age_group_name, '
                . $programSelect
                . ', p.name AS program_name, u.display_name AS coach_name, '
                . 'g.name AS ground_name',
                false
            )
            ->join('branches b', 'b.id = s.branch_id')
            ->join('batches ba', 'ba.id = s.batch_id', 'left')
            ->join('admission_age_groups ag', 'ag.id = s.age_group_id', 'left')
            ->join('programs p', $programJoin, 'left')
            ->join('users u', 'u.id = s.coach_user_id', 'left')
            ->join('grounds g', 'g.id = s.ground_id', 'left')
            ->where('s.id', $id)
            ->get()
            ->getRowArray();
        if (!is_array($r)) {
            throw new RuntimeException('Not found.');
        }
        $r['skill_area'] = (string) ($r['skill_area'] ?? '');
        $r['skill_option_id'] = (int) ($r['skill_option_id'] ?? 0);
        $r['skill_option_label'] = (string) ($r['skill_option_label'] ?? '');
        if ($r['skill_option_id'] > 0 && $db->tableExists('session_skill_options')) {
            $skill = $db->table('session_skill_options')
                ->where('id', $r['skill_option_id'])
                ->get()
                ->getRowArray();
            if (is_array($skill)) {
                $r['skill_area'] = (string) $skill['area'];
                if ($r['skill_option_label'] === '') {
                    $r['skill_option_label'] = (string) $skill['name'];
                }
            }
        }
        $this->allowSessionRead((int) $r['branch_id']);
        $this->assertCoachSessionOwnership($r);
        $r['starts_at_local'] = $this->local((string) $r['starts_at']);
        $r['ends_at_local'] = $r['ends_at'] === null
            ? null
            : $this->local((string) $r['ends_at']);
        $r['players'] = $db->table('session_players sp')
            ->select('sp.player_id, p.branch_id, pe.full_name')
            ->join('players p', 'p.id = sp.player_id')
            ->join('persons pe', 'pe.id = p.person_id')
            ->where('sp.session_id', $id)
            ->where('p.branch_id', (int) $r['branch_id'])
            ->orderBy('sp.player_id', 'ASC')
            ->get()
            ->getResultArray();
        $snapshot = $db->table('attendance_roster_snapshots')
            ->where('session_id', $id)
            ->get()
            ->getRowArray();
        $r['snapshot_status'] = is_array($snapshot)
            && (int) $snapshot['branch_id'] === (int) $r['branch_id']
            ? 'ready'
            : 'missing';
        if ($r['snapshot_status'] === 'ready') {
            $snapshot['member_count'] = (int) $db->table('attendance_roster_snapshot_members')
                ->where('snapshot_id', (int) $snapshot['id'])
                ->countAllResults();
        }
        $r['snapshot'] = $r['snapshot_status'] === 'ready' ? $snapshot : null;
        $r['attendance_summary'] = [];
        if (service('authorization')->can('attendance.view', (string) $r['branch_id'])) {
            $r['attendance_summary'] = $db->table('attendance')
                ->select('status, COUNT(*) AS total', false)
                ->where(['session_id' => $id, 'branch_id' => (int) $r['branch_id']])
                ->groupBy('status')
                ->get()
                ->getResultArray();
        }
        $r['history'] = $this->sessionLineage($db, $id, (int) $r['branch_id']);
        $canViewAudit = service('authorization')->can('session.update', (string) $r['branch_id'])
            || service('authorization')->can('audit.view', (string) $r['branch_id']);
        $r['audit_history'] = $canViewAudit
            ? $this->sessionAuditHistory(
                $db,
                array_map('intval', array_column($r['history'], 'id')),
                (int) $r['branch_id']
            )
            : [];
        return $r;
    }

    private function sessionLineage(object $db, int $id, int $branch): array
    {
        $sessions = $db->table('sessions')
            ->where('branch_id', $branch)
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();
        $lineageIds = [$id];
        $changed = true;
        while ($changed) {
            $changed = false;
            foreach ($sessions as $session) {
                $sessionId = (int) $session['id'];
                $parentId = (int) ($session['rescheduled_from_id'] ?? 0);
                if (
                    in_array($sessionId, $lineageIds, true)
                    || ($parentId > 0 && in_array($parentId, $lineageIds, true))
                ) {
                    if (!in_array($sessionId, $lineageIds, true)) {
                        $lineageIds[] = $sessionId;
                        $changed = true;
                    }
                    if ($parentId > 0 && !in_array($parentId, $lineageIds, true)) {
                        $lineageIds[] = $parentId;
                        $changed = true;
                    }
                }
            }
        }

        return array_values(array_filter(
            $sessions,
            static fn(array $session): bool => in_array(
                (int) $session['id'],
                $lineageIds,
                true
            )
        ));
    }

    private function sessionAuditHistory(object $db, array $sessionIds, int $branch): array
    {
        if ($sessionIds === []) {
            return [];
        }

        $sessions = $db->table('sessions')
            ->select('id, cancelled_at')
            ->where('branch_id', $branch)
            ->whereIn('id', $sessionIds)
            ->get()
            ->getResultArray();
        $sessionById = [];
        foreach ($sessions as $session) {
            $sessionById[(int) $session['id']] = $session;
        }

        $rows = $db->table('audit_logs al')
            ->select('al.id, al.action, al.entity_id, al.branch_id, al.before_summary, al.after_summary, al.reason, al.created_at, al.actor_user_id, u.display_name AS actor_name')
            ->join('users u', 'u.id = al.actor_user_id', 'left')
            ->where('al.entity_type', 'session')
            ->where('al.branch_id', $branch)
            ->whereIn('al.entity_id', array_map('strval', $sessionIds))
            ->whereIn('al.action', [
                'session_created',
                'session_updated',
                'session_cancelled',
                'session_rescheduled',
                'session_completed',
            ])
            ->orderBy('al.id', 'ASC')
            ->get()
            ->getResultArray();

        foreach ($rows as &$row) {
            $beforeSummary = $row['before_summary'] ?? null;
            $afterSummary = $row['after_summary'] ?? null;
            $row['before_metadata'] = $this->auditMetadata($beforeSummary);
            $row['after_metadata'] = $this->auditMetadata($afterSummary);
            $row['before_display_metadata'] = $this->projectHistoryMetadata(
                $row['before_metadata'],
                $db,
                $branch,
            );
            $row['after_display_metadata'] = $this->projectHistoryMetadata(
                $row['after_metadata'],
                $db,
                $branch,
            );
            $row['before_metadata_malformed'] = $this->metadataMalformed($beforeSummary);
            $row['after_metadata_malformed'] = $this->metadataMalformed($afterSummary);
            $row['cancelled_at'] = $sessionById[(int) $row['entity_id']]['cancelled_at'] ?? null;
            unset($row['before_summary'], $row['after_summary']);
        }
        unset($row);

        return $rows;
    }

    private function projectHistoryMetadata(array $metadata, object $db, int $branch): array
    {
        if ($metadata === []) {
            return [];
        }

        $referenceIds = [
            'batch' => [],
            'coach' => [],
            'branch' => [],
            'program' => [],
            'ground' => [],
            'player' => [],
        ];
        $this->collectHistoryReferenceIds($metadata, null, $referenceIds);

        return $this->resolveHistoryMetadata(
            $metadata,
            null,
            $this->historyReferenceMaps($db, $branch, $referenceIds),
        );
    }

    private function collectHistoryReferenceIds(
        mixed $value,
        ?string $inheritedType,
        array &$referenceIds,
    ): void {
        if (is_array($value)) {
            foreach ($value as $key => $childValue) {
                $type = $this->historyReferenceType((string) $key) ?? $inheritedType;
                $this->collectHistoryReferenceIds($childValue, $type, $referenceIds);
            }

            return;
        }

        if ($inheritedType === null) {
            return;
        }

        $id = filter_var($value, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        if ($id !== false) {
            $referenceIds[$inheritedType][(int) $id] = (int) $id;
        }
    }

    private function historyReferenceMaps(object $db, int $branch, array $referenceIds): array
    {
        $maps = [
            'batch' => [],
            'coach' => [],
            'branch' => [],
            'program' => [],
            'ground' => [],
            'player' => [],
        ];

        if ($referenceIds['branch'] !== []) {
            $rows = $db->table('branches')
                ->select('id, name')
                ->where('id', $branch)
                ->whereIn('id', array_values($referenceIds['branch']))
                ->get()
                ->getResultArray();
            foreach ($rows as $row) {
                $maps['branch'][(int) $row['id']] = (string) $row['name'];
            }
        }

        $batchRows = [];
        if ($referenceIds['batch'] !== [] || $referenceIds['program'] !== []) {
            $batchQuery = $db->table('batches')
                ->select('id, name, program_id')
                ->groupStart()
                ->where('branch_id', $branch)
                ->orWhere('branch_id', null)
                ->groupEnd();
            if ($referenceIds['batch'] !== []) {
                $batchQuery->groupStart()->whereIn('id', array_values($referenceIds['batch']));
                if ($referenceIds['program'] !== []) {
                    $batchQuery->orWhereIn('program_id', array_values($referenceIds['program']));
                }
                $batchQuery->groupEnd();
            } else {
                $batchQuery->whereIn('program_id', array_values($referenceIds['program']));
            }
            $batchRows = $batchQuery->get()->getResultArray();
        }
        foreach ($batchRows as $row) {
            $batchId = (int) $row['id'];
            if (isset($referenceIds['batch'][$batchId])) {
                $maps['batch'][$batchId] = (string) $row['name'];
            }
            $programId = (int) ($row['program_id'] ?? 0);
            if ($programId > 0 && isset($referenceIds['program'][$programId])) {
                $program = $db->table('programs')
                    ->select('name')
                    ->where('id', $programId)
                    ->get()
                    ->getRowArray();
                if (is_array($program)) {
                    $maps['program'][$programId] = (string) $program['name'];
                }
            }
        }

        if ($referenceIds['coach'] !== []) {
            $rows = $db->table('users u')
                ->select('u.id, u.display_name')
                ->join('user_branches ub', 'ub.user_id = u.id')
                ->where('ub.branch_id', $branch)
                ->whereIn('u.id', array_values($referenceIds['coach']))
                ->get()
                ->getResultArray();
            foreach ($rows as $row) {
                $maps['coach'][(int) $row['id']] = (string) $row['display_name'];
            }
        }

        if ($referenceIds['ground'] !== []) {
            $rows = $db->table('grounds')
                ->select('id, name')
                ->where('branch_id', $branch)
                ->whereIn('id', array_values($referenceIds['ground']))
                ->get()
                ->getResultArray();
            foreach ($rows as $row) {
                $maps['ground'][(int) $row['id']] = (string) $row['name'];
            }
        }

        if ($referenceIds['player'] !== []) {
            $rows = $db->table('players p')
                ->select('p.id, pe.full_name')
                ->join('persons pe', 'pe.id = p.person_id')
                ->where('p.branch_id', $branch)
                ->whereIn('p.id', array_values($referenceIds['player']))
                ->get()
                ->getResultArray();
            foreach ($rows as $row) {
                $maps['player'][(int) $row['id']] = (string) $row['full_name'];
            }
        }

        return $maps;
    }

    private function resolveHistoryMetadata(
        mixed $value,
        ?string $inheritedType,
        array $maps,
    ): mixed {
        if (is_array($value)) {
            $resolved = [];
            foreach ($value as $key => $childValue) {
                $type = $this->historyReferenceType((string) $key) ?? $inheritedType;
                $resolved[$key] = $this->resolveHistoryMetadata($childValue, $type, $maps);
            }

            return $resolved;
        }

        if ($inheritedType === null || $value === null || $value === '') {
            return $value;
        }

        $id = filter_var($value, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        if ($id === false) {
            return $value;
        }

        return $maps[$inheritedType][$id] ?? 'Unknown ' . $inheritedType;
    }

    private function historyReferenceType(string $key): ?string
    {
        return match (strtolower((string) preg_replace('/[^a-z0-9]/i', '', $key))) {
            'batchid' => 'batch',
            'coachuserid' => 'coach',
            'branchid' => 'branch',
            'programid' => 'program',
            'groundid' => 'ground',
            'playerid', 'playerids', 'rosterplayerid', 'rosterplayerids' => 'player',
            default => null,
        };
    }

    public function completionEligible(int $id): bool
    {
        try {
            $session = Database::connect('default')->table('sessions')
                ->where('id', $id)
                ->get()
                ->getRowArray();
            if (! is_array($session) || (string) $session['status'] !== 'scheduled') {
                return false;
            }
            if (! service('authorization')->can('session.update', (string) $session['branch_id'])) {
                return false;
            }

            $db = Database::connect('default');
            $snapshot = $db->table('attendance_roster_snapshots')
                ->where(['session_id' => $id, 'branch_id' => (int) $session['branch_id']])
                ->get()
                ->getRowArray();
            if (! is_array($snapshot)) {
                return false;
            }

            $rosterCount = (int) $db->table('attendance_roster_snapshot_members')
                ->where('snapshot_id', (int) $snapshot['id'])
                ->countAllResults();
            $recordedCount = (int) $db->table('attendance a')
                ->join('attendance_roster_snapshot_members m', 'm.player_id = a.player_id')
                ->where([
                    'a.session_id' => $id,
                    'a.branch_id' => (int) $session['branch_id'],
                    'm.snapshot_id' => (int) $snapshot['id'],
                ])
                ->countAllResults();

            return $rosterCount > 0 && $recordedCount >= $rosterCount;
        } catch (Throwable) {
            return false;
        }
    }

    public function complete(int $id, string $reason = ''): void
    {
        $db = Database::connect('default');
        $db->transStart();
        try {
            $session = $this->lock($db, $id);
            $actor = $this->allow((int) $session['branch_id'], 'session.update');
            $this->assertCoachSessionOwnership($session, $actor);
            if ((string) $session['status'] === 'completed') {
                $db->transComplete();
                return;
            }
            if ((string) $session['status'] === 'cancelled') {
                throw new RuntimeException('Cancelled sessions cannot be completed.');
            }
            if ((string) $session['status'] !== 'scheduled') {
                throw new RuntimeException('Session is not active.');
            }
            $snapshot = $db->table('attendance_roster_snapshots')
                ->where(['session_id' => $id, 'branch_id' => (int) $session['branch_id']])
                ->get()
                ->getRowArray();
            if (! is_array($snapshot)) {
                throw new RuntimeException('Attendance roster snapshot required.');
            }
            $rosterCount = (int) $db->table('attendance_roster_snapshot_members')
                ->where('snapshot_id', (int) $snapshot['id'])
                ->countAllResults();
            if ($rosterCount === 0) {
                throw new RuntimeException('Attendance roster is empty.');
            }
            $recordedCount = (int) $db->table('attendance a')
                ->join('attendance_roster_snapshot_members m', 'm.player_id = a.player_id')
                ->where([
                    'a.session_id' => $id,
                    'a.branch_id' => (int) $session['branch_id'],
                    'm.snapshot_id' => (int) $snapshot['id'],
                ])
                ->countAllResults();
            if ($recordedCount < $rosterCount) {
                throw new RuntimeException('Attendance is incomplete.');
            }

            $now = gmdate('Y-m-d H:i:s');
            $updated = $db->table('sessions')->where(['id' => $id, 'status' => 'scheduled'])
                ->update(['status' => 'completed', 'updated_at' => $now]);
            if (! $updated) {
                throw new RuntimeException('Unable to complete session.');
            }
            service('auditPersistence')->record(
                $actor,
                'session_completed',
                'session',
                (string) $id,
                (int) $session['branch_id'],
                ['status' => 'scheduled'],
                ['status' => 'completed'],
                trim($reason) !== '' ? trim($reason) : null,
                $db
            );
            $db->transComplete();
            if (! $db->transStatus()) {
                throw new RuntimeException('Unable to complete session.');
            }
        } catch (Throwable $exception) {
            $db->transRollback();
            $db->resetTransStatus();
            throw $exception;
        }
    }

    private function auditMetadata(?string $summary): array
    {
        if ($summary === null || $summary === '') {
            return [];
        }
        $metadata = json_decode($summary, true);

        return is_array($metadata) ? $metadata : [];
    }

    private function metadataMalformed(?string $summary): bool
    {
        if ($summary === null || trim($summary) === '') {
            return false;
        }

        json_decode($summary, true);

        return json_last_error() !== JSON_ERROR_NONE;
    }
    public function reassignCoach(int $id, int $coachUserId, string $reason): void
    {
        $reason = trim($reason);
        $db = Database::connect('default');
        $db->transStart();
        $current = $this->lock($db, $id);
        $actor = $this->allow((int) $current['branch_id'], 'session.update');
        $this->assertCoachSessionOwnership($current, $actor);
        if ($current['status'] !== 'scheduled') {
            throw new RuntimeException('Session is not active.');
        }
        if (strtotime((string) $current['starts_at']) <= time() && $reason === '') {
            throw new RuntimeException('A reason is required after the session starts.');
        }
        $this->coach($db, $coachUserId, (int) $current['branch_id']);
        $this->assertCoachSelection($coachUserId, (int) $current['branch_id']);
        if ((int) $current['coach_user_id'] === $coachUserId) {
            throw new RuntimeException('The Coach is already assigned.');
        }
        $db->table('sessions')->where('id', $id)->update([
            'coach_user_id' => $coachUserId,
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ]);
        service('auditPersistence')->record($actor, 'session_coach_reassigned', 'session', (string) $id, (int) $current['branch_id'], ['coach_user_id' => (int) $current['coach_user_id']], ['coach_user_id' => $coachUserId], $reason, $db);
        $db->transComplete();
        if (! $db->transStatus()) {
            throw new RuntimeException('Unable to reassign Coach.');
        }
    }

    public function update(int $id, array $input): void
    {
        $this->validateStatus((string) ($input['status'] ?? 'scheduled'));
        $input['starts_at'] = $this->utc((string) ($input['starts_at'] ?? ''));
        $input['ends_at'] = $this->optionalUtc($input['ends_at'] ?? null);
        $input['batch_id'] = $this->normalizeOptionalBatchId($input['batch_id'] ?? null);
        if (array_key_exists('age_group_id', $input)) {
            $input['age_group_id'] = $this->normalizeOptionalAgeGroupId($input['age_group_id']);
        }
        $this->window($input);
        $db = Database::connect('default');
        $db->transStart();
        $lockedResources = [];
        try {
            $current = $this->lock($db, $id);
            $actor = $this->allow((int) $current['branch_id'], 'session.update');
            $this->assertCoachSessionOwnership($current, $actor);
            $sessionType = array_key_exists('session_type', $input)
                ? $input['session_type']
                : ($current['session_type'] ?? null);
            if (array_key_exists('session_type', $input)) {
                $this->validateSessionType($sessionType);
            }
            $input['session_type'] = $sessionType;
            $input['description'] = array_key_exists('description', $input)
                ? $this->normalizeDescription($input['description'])
                : (string) ($current['description'] ?? '');
            $input['program_id'] = array_key_exists('program_id', $input)
                ? $this->normalizeProgramId($input['program_id'])
                : $this->normalizeProgramId($current['program_id'] ?? null);
            if ($current['status'] !== 'scheduled') {
                throw new RuntimeException('Session is not active.');
            }
            if (
                isset($input['expected_updated_at'])
                && (string) $input['expected_updated_at'] !== (string) $current['updated_at']
            ) {
                throw new RuntimeException('Update conflict.');
            }
            $snapshot = $db->table('attendance_roster_snapshots')
                ->select('id')
                ->where('session_id', $id)
                ->get()
                ->getRowArray();
            if (is_array($snapshot)) {
                throw new RuntimeException(
                    'Session is locked because Attendance has started.'
                );
            }
            if ($input['batch_id'] !== null) {
                $batch = $db->table('batches')
                    ->where('id', $input['batch_id'])
                    ->where('is_active', 1)
                    ->get()
                    ->getRowArray();
                if (!is_array($batch)) {
                    throw new RuntimeException('Invalid batch.');
                }
            }
            $input['age_group_id'] = array_key_exists('age_group_id', $input)
                ? $input['age_group_id']
                : $this->normalizeOptionalAgeGroupId($current['age_group_id'] ?? null);
            $this->validateAgeGroup($db, $input['age_group_id']);
            $branch = (int) $current['branch_id'];
            if ((int) ($input['branch_id'] ?? 0) !== $branch) {
                throw new RuntimeException('Invalid branch.');
            }
            $this->validateProgram($db, $input['program_id'], $branch);
            $skill = $this->skillOption(
                $db,
                $input,
                $branch,
                (int) ($current['skill_option_id'] ?? 0)
            );
            $this->allow($branch, 'session.update');
            $groundId = (int) ($input['ground_id'] ?? 0);
            if ($groundId > 0) {
                $this->allow($branch, 'ground.book');
                $this->validateGround($db, $groundId, $branch);
            }
            $coach = (int) ($input['coach_user_id'] ?? 0);
            $this->coach($db, $coach, $branch);
            $hasRosterPayload = array_key_exists('player_ids', $input)
                || array_key_exists('player_ids_present', $input);
            $playerIds = $hasRosterPayload && array_key_exists('player_ids', $input)
                && $input['player_ids'] !== []
                ? $this->playerIds($input['player_ids'])
                : [];
            $this->assertCoachSelection($coach, $branch);
            $this->assertCoachPlayerScope($db, $playerIds, $branch, $coach);
            $oldPlayers = $db->table('session_players')
                ->where('session_id', $id)
                ->get()
                ->getResultArray();
            $oldPlayerIds = array_map('intval', array_column($oldPlayers, 'player_id'));
            $lockedResources = $this->lockResources(
                $db,
                array_values(array_unique([(int) $current['coach_user_id'], $coach])),
                array_values(array_filter(array_unique([
                    (int) ($current['ground_id'] ?? 0),
                    $groundId,
                ]))),
                array_values(array_unique(array_merge($oldPlayerIds, $playerIds)))
            );
            if ($groundId > 0 && $input['ends_at'] !== null) {
                service('ground')->ensureBookable($groundId, $branch, $input['starts_at'], $input['ends_at'], $id);
            }
            $this->approvedLeaveConflict($db, $coach, $branch, $input['starts_at'], $input['ends_at']);
            $this->conflict($db, $coach, $branch, $input['starts_at'], $input['ends_at'], $id);
            $this->playerConflicts($db, $playerIds, $branch, $input['starts_at'], $input['ends_at'], $id);
            if ($hasRosterPayload) {
                $this->validateRoster(
                    $db,
                    $playerIds,
                    $input['batch_id'],
                    $input['age_group_id'],
                    $branch,
                    $input['starts_at']
                );
            }
            $now = gmdate('Y-m-d H:i:s');
            $updates = [
                'batch_id' => $input['batch_id'],
                'age_group_id' => $input['age_group_id'],
                'coach_user_id' => $coach,
                'starts_at' => $input['starts_at'],
                'ends_at' => $input['ends_at'],
                'session_type' => $input['session_type'],
                'updated_at' => $now,
            ];

            if ($db->fieldExists('description', 'sessions')) {
                $updates['description'] = $input['description'];
            }

            if ($db->fieldExists('program_id', 'sessions')) {
                $updates['program_id'] = $input['program_id'];
            }

            if ($db->fieldExists('ground_id', 'sessions')) {
                $updates['ground_id'] = $groundId > 0 ? $groundId : null;
            }

            foreach (['skill_area', 'skill_option_id', 'skill_option_label'] as $field) {
                if ($db->fieldExists($field, 'sessions')) {
                    $updates[$field] = $skill[$field] ?? null;
                }
            }

            $db->table('sessions')
                ->where(['id' => $id, 'status' => 'scheduled'])
                ->update($updates);
            if ($hasRosterPayload) {
                $db->table('session_players')->where('session_id', $id)->delete();
                if ($playerIds !== []) {
                    $this->roster($db, $id, $playerIds, $branch, $input['starts_at']);
                }
            }
            service('auditPersistence')->record(
                $actor,
                'session_updated',
                'session',
                (string) $id,
                $branch,
                [
                    'batch_id' => $current['batch_id'] === null ? null : (int) $current['batch_id'],
                    'age_group_id' => $current['age_group_id'] === null ? null : (int) $current['age_group_id'],
                    'program_id' => ($current['program_id'] ?? null) === null
                        ? null
                        : (int) $current['program_id'],
                    'coach_user_id' => (int) $current['coach_user_id'],
                    'ground_id' => $current['ground_id'],
                    'starts_at' => $current['starts_at'],
                    'ends_at' => $current['ends_at'],
                    'session_type' => $current['session_type'] ?? null,
                    'description' => $current['description'] ?? null,
                    'skill_area' => $current['skill_area'] ?? null,
                    'skill_option_id' => $current['skill_option_id'] ?? null,
                    'skill_option_label' => $current['skill_option_label'] ?? null,
                ],
                [
                    'batch_id' => $input['batch_id'],
                    'age_group_id' => $input['age_group_id'],
                    'program_id' => $input['program_id'],
                    'coach_user_id' => $coach,
                    'ground_id' => $groundId > 0 ? $groundId : null,
                    'starts_at' => $input['starts_at'],
                    'ends_at' => $input['ends_at'],
                    'session_type' => $input['session_type'],
                    'description' => $input['description'],
                    'skill_area' => $skill['skill_area'],
                    'skill_option_id' => $skill['skill_option_id'],
                    'skill_option_label' => $skill['skill_option_label'],
                ]
            );
            $db->transComplete();
            if (!$db->transStatus()) {
                throw new RuntimeException('Unable to update session.');
            }
            $this->releaseResources($db, $lockedResources);
        } catch (Throwable $e) {
            $db->transRollback();
            $db->resetTransStatus();
            $this->releaseResources($db, $lockedResources);
            throw $e;
        }
    }
    public function cancel(int $id, string $reason): void
    {
        if (trim($reason) === '') {
            throw new RuntimeException('Reason required.');
        }
        $db = Database::connect('default');
        $db->transStart();
        try {
            $r = $this->lock($db, $id);
            $a = $this->allow((int) $r['branch_id'], 'session.cancel');
            $this->assertCoachSessionOwnership($r, $a);
            if ($r['status'] !== 'scheduled') {
                throw new RuntimeException('Session is not active.');
            }
            $now = gmdate('Y-m-d H:i:s');
            $db->table('sessions')->where(['id' => $id, 'status' => 'scheduled'])
                ->update(['status' => 'cancelled', 'cancellation_reason' => trim($reason), 'cancelled_by' => $a, 'cancelled_at' => $now, 'updated_at' => $now]);
            service('auditPersistence')->record($a, 'session_cancelled', 'session', (string) $id, (int) $r['branch_id'], ['status' => 'scheduled'],
                ['status' => 'cancelled'], trim($reason));
            $db->transComplete();
            if (!$db->transStatus()) {
                throw new RuntimeException('Unable to cancel session.');
            }
        } catch (Throwable $e) {
            $db->transRollback();
            $db->resetTransStatus();
            throw $e;
        }
    }
    public function reschedule(int $id, string $starts, ?string $ends, string $reason): int
    {
        $starts = $this->utc($starts);
        $ends = $this->optionalUtc($ends);
        $this->window(['starts_at' => $starts, 'ends_at' => $ends]);
        if (trim($reason) === '') {
            throw new RuntimeException('Reason required.');
        }
        $db = Database::connect('default');
        $db->transStart();
        $lockedResources = [];
        try {
            $r = $this->lock($db, $id);
            $a = $this->allow((int) $r['branch_id'], 'session.update');
            $this->assertCoachSessionOwnership($r, $a);
            if ($r['status'] !== 'scheduled') {
                throw new RuntimeException('Session is not active.');
            }
            $oldPlayers = $db->table('session_players')
                ->where('session_id', $id)
                ->get()
                ->getResultArray();
            $playerIds = array_map('intval', array_column($oldPlayers, 'player_id'));
            $lockedResources = $this->lockResources(
                $db,
                [(int) $r['coach_user_id']],
                (int) ($r['ground_id'] ?? 0) > 0 ? [(int) $r['ground_id']] : [],
                $playerIds
            );
            $this->approvedLeaveConflict($db, (int) $r['coach_user_id'], (int) $r['branch_id'], $starts, $ends);
            $this->conflict($db, (int) $r['coach_user_id'], (int) $r['branch_id'], $starts, $ends, $id);
            if ($ends !== null && (int) ($r['ground_id'] ?? 0) > 0) {
                $this->allow((int) $r['branch_id'], 'ground.book');
                service('ground')->ensureBookable((int) $r['ground_id'], (int) $r['branch_id'], $starts, $ends, $id);
            }
            $now = gmdate('Y-m-d H:i:s');
            $db->table('sessions')->where(['id' => $id, 'status' => 'scheduled'])
                ->update(['status' => 'cancelled', 'cancellation_reason' => trim($reason), 'cancelled_by' => $a, 'cancelled_at' => $now, 'updated_at' => $now]);
            $new = $this->insert($db, [
                'batch_id' => $r['batch_id'],
                'age_group_id' => $r['age_group_id'] ?? null,
                'coach_user_id' => $r['coach_user_id'],
                'ground_id' => $r['ground_id'] ?? null,
                'starts_at' => $starts,
                'ends_at' => $ends,
                'session_type' => $r['session_type'] ?? null,
                'description' => $r['description'] ?? null,
            ], (int) $r['branch_id'], $a, $id, [
                'skill_area' => (string) ($r['skill_area'] ?? '') ?: null,
                'skill_option_id' => (int) ($r['skill_option_id'] ?? 0) ?: null,
                'skill_option_label' => (string) ($r['skill_option_label'] ?? '') ?: null,
            ]);
            $this->playerConflicts($db, $playerIds, (int) $r['branch_id'], $starts, $ends, $id);
            $this->roster($db, $new, $playerIds, (int) $r['branch_id'], $starts);
            service('auditPersistence')->record($a, 'session_rescheduled', 'session', (string) $new, (int) $r['branch_id'],
                ['original_session_id' => $id], ['replacement_session_id' => $new], trim($reason));
            $db->transComplete();
            if (!$db->transStatus()) {
                throw new RuntimeException('Unable to reschedule session.');
            }
            $this->releaseResources($db, $lockedResources);
            return $new;
        } catch (Throwable $e) {
            $db->transRollback();
            $db->resetTransStatus();
            $this->releaseResources($db, $lockedResources);
            throw $e;
        }
    }

    private function skillOption(
        object $db,
        array $input,
        int $branch,
        int $existingSkillOptionId = 0
    ): array
    {
        $area = trim((string) ($input['skill_area'] ?? ''));
        $rawOptionId = $input['skill_option_id'] ?? null;
        $blankOption = $rawOptionId === null
            || trim((string) $rawOptionId) === ''
            || trim((string) $rawOptionId) === '0';
        $optionId = $area === '' && $blankOption
            ? null
            : $this->normalizeOptionalSkillOptionId($rawOptionId);
        if ($area === '') {
            if ($optionId !== null) {
                throw new RuntimeException('Invalid skill pairing.');
            }

            return [
                'skill_area' => null,
                'skill_option_id' => null,
                'skill_option_label' => null,
            ];
        }

        if ($optionId === null) {
            return [
                'skill_area' => $area,
                'skill_option_id' => null,
                'skill_option_label' => null,
            ];
        }

        if (! $db->tableExists('session_skill_options')) {
            throw new RuntimeException('Skill catalog unavailable.');
        }
        $option = $db->table('session_skill_options')
            ->where('id', $optionId)
            ->get()
            ->getRowArray();
        if (! is_array($option)) {
            throw new RuntimeException('Invalid skill selection.');
        }

        $isActive = (int) ($option['is_active'] ?? 0) === 1;
        if (! $isActive && $optionId !== $existingSkillOptionId) {
            throw new RuntimeException('Retired skill option.');
        }
        if ((string) $option['area'] !== $area) {
            throw new RuntimeException('Invalid skill pairing.');
        }

        if ($branch <= 0) {
            throw new RuntimeException('Invalid branch.');
        }

        return [
            'skill_area' => (string) $option['area'],
            'skill_option_id' => $optionId,
            'skill_option_label' => (string) $option['name'],
        ];
    }

    private function normalizeOptionalSkillOptionId(mixed $value): ?int
    {
        if ($value === null || (is_string($value) && trim($value) === '')) {
            return null;
        }

        if (is_int($value) && $value > 0) {
            return $value;
        }

        if (is_string($value) && ctype_digit($value) && (int) $value > 0) {
            return (int) $value;
        }

        throw new RuntimeException('Invalid skill selection.');
    }

    private function insert(
        object $db,
        array $i,
        int $b,
        int $a,
        ?int $from,
        array $skill = []
    ): int
    {
        $n = gmdate('Y-m-d H:i:s');
        $groundId = (int) ($i['ground_id'] ?? 0);
        $data = [
            'branch_id' => $b,
            'batch_id' => $i['batch_id'] ?? null,
            'coach_user_id' => (int) $i['coach_user_id'],
            'starts_at' => $i['starts_at'],
            'ends_at' => $i['ends_at'],
            'status' => 'scheduled',
            'session_type' => $i['session_type'] ?? null,
            'rescheduled_from_id' => $from,
            'created_by' => $a,
            'created_at' => $n,
            'updated_at' => $n,
        ];

        if ($db->fieldExists('age_group_id', 'sessions')) {
            $data['age_group_id'] = $i['age_group_id'] ?? null;
        }

        if ($db->fieldExists('program_id', 'sessions')) {
            $data['program_id'] = (int) ($i['program_id'] ?? 0);
        }

        if ($db->fieldExists('description', 'sessions')) {
            $data['description'] = $i['description'] ?? null;
        }

        if ($db->fieldExists('ground_id', 'sessions')) {
            $data['ground_id'] = $groundId > 0 ? $groundId : null;
        }

        foreach (['skill_area', 'skill_option_id', 'skill_option_label'] as $field) {
            if ($db->fieldExists($field, 'sessions')) {
                $data[$field] = $skill[$field] ?? null;
            }
        }

        $inserted = $db->table('sessions')->insert($data);
        if (! $inserted) {
            $error = $db->error();
            $detail = trim((string) ($error['message'] ?? ''));

            throw new RuntimeException(
                $detail === ''
                    ? 'Unable to create session.'
                    : 'Unable to create session: ' . $detail
            );
        }
        return (int) $db->insertID();
    }
    private function roster(object $db, int $s, mixed $ids, int $b, string $at): void
    {
        if (!is_array($ids)) {
            throw new RuntimeException('Invalid roster.');
        }
        foreach ($ids as $id) {
            $session = $db->table('sessions')
                ->select('batch_id')
                ->where('id', $s)
                ->get()
                ->getRowArray();
            $batchId = is_array($session) && $session['batch_id'] !== null
                ? (int) $session['batch_id']
                : null;
            if ($batchId === null) {
                $ok = $db->table('players')
                    ->select('id')
                    ->where(['id' => (int) $id, 'branch_id' => $b])
                    ->get()
                    ->getRowArray();
            } else {
                $ok = $db->query(
                    'SELECT p.id FROM players p INNER JOIN player_batch_assignments a ON a.player_id=p.id WHERE p.id=? AND p.branch_id=? AND a.batch_id=? AND a.effective_from<=? AND (a.effective_to IS NULL OR a.effective_to>?) LIMIT 1',
                    [(int) $id, $b, $batchId, $at, $at]
                )->getRowArray();
            }
            if (!is_array($ok)) {
                throw new RuntimeException('Player is not eligible.');
            }
            $db->table('session_players')->insert(['session_id' => $s, 'player_id' => (int) $id, 'created_at' => gmdate('Y-m-d H:i:s')]);
        }
    }
    private function coach(object $db, int $id, int $b): void
    {
        $coach = $db->table('user_branches ub')
            ->select('ub.user_id')
            ->join('users u', 'u.id = ub.user_id')
            ->join('coach_profiles cp', 'cp.user_id = ub.user_id', 'left')
            ->join('user_roles ur', 'ur.user_id = ub.user_id')
            ->join('roles r', 'r.id = ur.role_id')
            ->where(['ub.user_id' => $id, 'ub.branch_id' => $b])
            ->whereIn('r.name', ['Coach', 'Fitness Coach'])
            ->where('u.is_enabled', 1)
            ->groupStart()
            ->where('cp.is_active', 1)
            ->orWhere('cp.id IS NULL', null, false)
            ->groupEnd()
            ->get()
            ->getRowArray();
        if (!is_array($coach)) {
            throw new RuntimeException('Coach is not authorized for branch.');
        }
    }

    private function validateGround(object $db, int $groundId, int $branchId): void
    {
        $ground = $db->table('grounds')
            ->where('id', $groundId)
            ->get()
            ->getRowArray();
        if (! is_array($ground)) {
            throw new RuntimeException('Not found.');
        }
        if ((int) $ground['branch_id'] !== $branchId || (int) $ground['is_active'] !== 1) {
            throw new RuntimeException('Ground is not available.');
        }
    }
    private function approvedLeaveConflict(object $db, int $c, int $b, string $f, ?string $t): void
    {
        $sql = $t === null
            ? 'SELECT id FROM coach_unavailability WHERE coach_user_id=? AND branch_id=? AND status=? AND unavailable_from<=? AND unavailable_to>? LIMIT 1'
            : 'SELECT id FROM coach_unavailability WHERE coach_user_id=? AND branch_id=? AND status=? AND unavailable_from<? AND unavailable_to>? LIMIT 1';
        if (is_array($db->query($sql, [
                    $c,
                    $b,
                    'APPROVED',
                    $t ?? $f,
                    $f,
                ])->getRowArray())) {
            throw new RuntimeException('Coach unavailable.');
        }

    }

    public function validateCoachAvailabilityAndConflicts(
        object $db,
        int $coach,
        int $branch,
        string $from,
        string $to
    ): void {
        $this->approvedLeaveConflict($db, $coach, $branch, $from, $to);
        $this->conflict($db, $coach, $branch, $from, $to);
    }
    private function conflict(object $db, int $c, int $b, string $f, ?string $t, ?int $except = null): void
    {
        $q = $db->table('sessions')
            ->where(['branch_id' => $b, 'coach_user_id' => $c, 'status' => 'scheduled']);
        if ($t === null) {
            $q->where('starts_at', $f);
        } else {
            $q->where('starts_at <', $t)->where('ends_at >', $f);
        }
        if ($except !== null) {
            $q->where('id !=', $except);
        }
        if (is_array($q->get()->getRowArray())) {
            throw new RuntimeException('Scheduling conflict.');
        }
    }

    private function playerConflicts(object $db, array $playerIds, int $branch, string $from, ?string $to, ?int $except = null): void
    {
        foreach ($playerIds as $playerId) {
            $query = $db->table('session_players sp')
                ->join('sessions s', 's.id = sp.session_id')
                ->where([
                    'sp.player_id' => $playerId,
                    's.branch_id' => $branch,
                    's.status' => 'scheduled',
                ])
                ->groupStart();
            if ($to === null) {
                $query->where('s.starts_at', $from);
            } else {
                $query->where('s.starts_at <', $to)
                    ->where('s.ends_at >', $from);
            }
            $query->groupEnd();
            if ($except !== null) {
                $query->where('s.id !=', $except);
            }
            if (is_array($query->get()->getRowArray())) {
                throw new RuntimeException('Player scheduling conflict.');
            }
        }
    }

    private function lockResources(object $db, array $coaches, array $grounds, array $playerIds): array
    {
        $resourceNames = [];
        foreach (array_unique(array_map('intval', $coaches)) as $coach) {
            if ($coach > 0) {
                $resourceNames[] = 'mcc:scheduling:coach:' . $coach;
            }
        }
        foreach (array_unique(array_map('intval', $grounds)) as $ground) {
            if ($ground > 0) {
                $resourceNames[] = 'mcc:scheduling:ground:' . $ground;
            }
        }
        foreach (array_unique(array_map('intval', $playerIds)) as $playerId) {
            $resourceNames[] = 'mcc:scheduling:player:' . $playerId;
        }
        sort($resourceNames, SORT_STRING);

        $locked = [];
        try {
            foreach ($resourceNames as $resourceName) {
                $row = $db->query(
                    'SELECT GET_LOCK(?, 5) AS acquired',
                    [$resourceName]
                )->getRowArray();
                if (!is_array($row) || (int) ($row['acquired'] ?? 0) !== 1) {
                    throw new RuntimeException('Scheduling conflict.');
                }
                $locked[] = $resourceName;
            }
        } catch (Throwable $e) {
            $this->releaseResources($db, $locked);
            throw $e;
        }

        return $locked;
    }

    private function validateRoster(
        object $db,
        array $playerIds,
        ?int $batchId,
        ?int $ageGroupId,
        int $branch,
        string $at
    ): void
    {
        foreach ($playerIds as $playerId) {
            $query = $db->table('players p')
                ->select('p.id')
                ->join('admissions a', 'a.id = p.admission_id', 'left')
                ->where([
                    'p.id' => $playerId,
                    'p.branch_id' => $branch,
                    'p.status' => 'active',
                ]);
            if ($batchId !== null) {
                $query
                    ->join(
                        'player_batch_assignments pba',
                        'pba.player_id = p.id AND pba.branch_id = p.branch_id'
                    )
                    ->where('pba.batch_id', $batchId)
                    ->where('pba.effective_from <=', $at)
                    ->groupStart()
                    ->where('pba.effective_to IS NULL', null, false)
                    ->orWhere('pba.effective_to >', $at)
                    ->groupEnd();
            }
            if ($ageGroupId !== null) {
                $query->where('a.age_group_id', $ageGroupId);
            }
            $eligible = $query->get()->getRowArray();
            if (!is_array($eligible)) {
                throw new RuntimeException('Player is not eligible.');
            }
        }
    }

    private function releaseResources(object $db, array $resourceNames): void
    {
        foreach ($resourceNames as $resourceName) {
            $db->query('SELECT RELEASE_LOCK(?)', [$resourceName]);
        }
    }

    private function playerIds(mixed $value): array
    {
        if (is_string($value)) {
            $value = preg_split('/[\s,]+/', trim($value), -1, PREG_SPLIT_NO_EMPTY);
        }
        if (!is_array($value)) {
            throw new RuntimeException('Invalid roster.');
        }
        $ids = array_values(array_unique(array_map('intval', $value)));
        if ($ids === []) {
            throw new RuntimeException('Invalid roster.');
        }
        if (in_array(0, $ids, true)) {
            throw new RuntimeException('Invalid student.');
        }
        return $ids;
    }

    private function validateCreateInput(array $input): void
    {
        if ((int) ($input['branch_id'] ?? 0) <= 0) {
            throw new RuntimeException(
                $this->isCoachRole() ? 'Please select a branch.' : 'Invalid branch.'
            );
        }
        if ((int) ($input['coach_user_id'] ?? 0) <= 0) {
            throw new RuntimeException(
                $this->isCoachRole() ? 'Please select a coach.' : 'Invalid coach.'
            );
        }
        if (trim((string) ($input['starts_at'] ?? '')) === '') {
            throw new RuntimeException('Invalid calendar date.');
        }
    }

    private function normalizeOptionalBatchId(mixed $value): ?int
    {
        if ($value === null || (is_string($value) && trim($value) === '')) {
            return null;
        }

        if (is_int($value) && $value > 0) {
            return $value;
        }

        if (is_string($value) && ctype_digit($value) && (int) $value > 0) {
            return (int) $value;
        }

        throw new RuntimeException('Invalid batch.');
    }

    private function normalizeOptionalAgeGroupId(mixed $value): ?int
    {
        if ($value === null || (is_string($value) && trim($value) === '')) {
            return null;
        }

        if (is_int($value) && $value > 0) {
            return $value;
        }

        if (is_string($value) && ctype_digit($value) && (int) $value > 0) {
            return (int) $value;
        }

        throw new RuntimeException('Invalid age group.');
    }

    private function validateAgeGroup(object $db, ?int $ageGroupId): void
    {
        if ($ageGroupId === null) {
            return;
        }

        $ageGroup = $db->table('admission_age_groups')
            ->select('id')
            ->where([
                'id' => $ageGroupId,
                'branch_id' => null,
                'is_active' => 1,
            ])
            ->get()
            ->getRowArray();
        if (! is_array($ageGroup)) {
            throw new RuntimeException('Invalid age group.');
        }
    }

    private function normalizeProgramId(mixed $value): ?int
    {
        if ($value === null || (is_string($value) && trim($value) === '')) {
            return null;
        }

        if ($value === 0 || $value === '0') {
            return null;
        }

        if (is_int($value) && $value > 0) {
            return $value;
        }

        if (is_string($value) && ctype_digit($value) && (int) $value > 0) {
            return (int) $value;
        }

        throw new RuntimeException('Invalid program.');
    }

    private function normalizeDescription(mixed $value): ?string
    {
        $description = trim((string) ($value ?? ''));

        return $description === '' ? null : $description;
    }

    private function validateProgram(object $db, ?int $programId, int $branchId): void
    {
        if ($programId === null) {
            return;
        }

        $programQuery = $db->table('programs')
            ->select('id')
            ->where('id', $programId);
        if ($db->fieldExists('branch_id', 'programs')) {
            $programQuery->where('branch_id', $branchId);
        }
        $program = $programQuery->get()->getRowArray();
        if (! is_array($program)) {
            throw new RuntimeException('Invalid program.');
        }
    }

    private function defaultCoachUserId(): ?int
    {
        if (! $this->isCoachRole()) {
            return null;
        }

        $user = session('user');
        $id = is_array($user) ? (int) ($user['id'] ?? 0) : 0;

        return $id > 0 ? $id : null;
    }

    private function defaultCoachBranchId(array $branches): ?int
    {
        if (! $this->isCoachRole() || count($branches) !== 1) {
            return null;
        }

        $branchId = (int) ($branches[0]['id'] ?? 0);

        return $branchId > 0 ? $branchId : null;
    }

    private function strictCoachActorId(): ?int
    {
        if (! $this->isCoachRole()) {
            return null;
        }

        return $this->actor();
    }

    private function assertCoachBranchScope(
        int $branchId,
        string $permission = 'session.create'
    ): void
    {
        if ($this->strictCoachActorId() === null) {
            return;
        }

        if (! in_array($branchId, $this->authorizedBranchIds($permission), true)) {
            throw new RuntimeException('Invalid branch.');
        }
    }

    private function assertCoachSelection(int $coachUserId, int $branchId): void
    {
        $actor = $this->strictCoachActorId();
        if ($actor !== null && $coachUserId !== $actor) {
            throw new RuntimeException('Coach is not authorized for branch.');
        }
    }

    private function assertCoachPlayerScope(
        object $db,
        array $playerIds,
        int $branchId,
        int $coachUserId
    ): void
    {
        $actor = $this->strictCoachActorId();
        $scopeCoachId = $actor ?? $coachUserId;
        $now = gmdate('Y-m-d H:i:s');

        foreach ($playerIds as $playerId) {
            if ($actor === null) {
                $activePlayer = $db->table('players')
                    ->select('id')
                    ->where([
                        'id' => $playerId,
                        'branch_id' => $branchId,
                        'status' => 'active',
                    ])
                    ->get()
                    ->getRowArray();
                if (! is_array($activePlayer)) {
                    continue;
                }
            }

            $assigned = $db->query(
                'SELECT p.id FROM players p '
                . 'LEFT JOIN admissions a ON a.id = p.admission_id '
                . 'WHERE p.id = ? AND p.branch_id = ? AND p.status = ? '
                . 'AND ((a.id IS NOT NULL AND (a.coach_user_id = ? OR a.personal_coach_user_id = ?)) '
                . 'OR (a.id IS NULL AND EXISTS ('
                . 'SELECT 1 FROM player_coach_assignments pca '
                . 'WHERE pca.player_id = p.id AND pca.branch_id = p.branch_id '
                . 'AND pca.coach_user_id = ? AND pca.effective_from <= ? '
                . 'AND (pca.effective_to IS NULL OR pca.effective_to > ?)'
                . '))) LIMIT 1',
                [$playerId, $branchId, 'active', $scopeCoachId, $scopeCoachId, $scopeCoachId, $now, $now]
            )->getRowArray();
            if (!is_array($assigned)) {
                throw new RuntimeException('Player is not assigned to this Coach.');
            }
        }
    }

    private function assertCoachSessionOwnership(array $session, ?int $actor = null): void
    {
        if (! $this->isCoachRole()) {
            return;
        }

        $actor ??= $this->strictCoachActorId();
        if ($actor !== null && (int) ($session['coach_user_id'] ?? 0) !== $actor) {
            throw new RuntimeException('Forbidden');
        }
    }

    private function isCoachRole(): bool
    {
        $roles = (array) session('roles');

        return in_array('Coach', $roles, true)
            && ! in_array('Super Admin', $roles, true)
            && ! in_array('Branch Admin', $roles, true);
    }
    private function lock(object $db, int $id): array
    {
        $r = $db->query('SELECT * FROM sessions WHERE id=? LIMIT 1 FOR UPDATE', [$id])->getRowArray();
        if (!is_array($r)) {
            throw new RuntimeException('Not found.');
        }
        return $r;
    }
    private function allow(int $b, string $p): int
    {
        if (!service('authorization')->can($p, (string) $b)) {
            throw new RuntimeException('Forbidden');
        }
        return $this->actor();
    }
    private function actor(): int
    {
        $u = session('user');
        if (!is_array($u) || !isset($u['id'])) {
            throw new RuntimeException('Authentication required');
        }
        return (int) $u['id'];
    }

    private function validateStatus(string $status): void
    {
        if ($status !== 'scheduled') {
            throw new RuntimeException('Invalid session status.');
        }
    }

    private function validateSessionType(mixed $sessionType): void
    {
        if (! is_string($sessionType) || ! in_array($sessionType, self::SESSION_TYPES, true)) {
            throw new RuntimeException('Invalid session type.');
        }
    }
    private function sessionFilters(array $filters): array
    {
        $query = [];
        foreach (['branch_id', 'batch_id', 'coach_user_id', 'ground_id'] as $key) {
            if (($filters[$key] ?? '') === '') {
                continue;
            }
            if (!ctype_digit((string) $filters[$key]) || (int) $filters[$key] < 1) {
                throw new RuntimeException('Invalid session filter.');
            }
            $query[$key] = (int) $filters[$key];
        }
        if (($filters['status'] ?? '') !== '') {
            if (! in_array($filters['status'], ['scheduled', 'active', 'completed', 'cancelled', 'postponed'], true)) {
                throw new RuntimeException('Invalid session filter.');
            }
            $query['status'] = (string) $filters['status'];
        }
        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $query['q'] = $search;
        }
        if (($filters['date'] ?? '') !== '') {
            $date = (string) $filters['date'];
            try {
                $dateObject = $this->calendarDate($date);
            } catch (RuntimeException) {
                throw new RuntimeException('Invalid session filter.');
            }
            $query['date_from'] = $dateObject
                ->setTimezone(new DateTimeZone('UTC'))
                ->format('Y-m-d H:i:s');
            $query['date_to'] = $dateObject
                ->modify('+1 day')
                ->setTimezone(new DateTimeZone('UTC'))
                ->format('Y-m-d H:i:s');
        }
        return $query;
    }

    private function allowSessionRead(int $branchId): void
    {
        $authorization = service('authorization');
        if (
            ! $authorization->can('session.update', (string) $branchId)
            && ! $authorization->can('coach.view', (string) $branchId)
        ) {
            throw new RuntimeException('Forbidden');
        }

        $this->actor();
    }

    private function pageNumber(mixed $value): int
    {
        if (!ctype_digit((string) $value) || (int) $value < 1) {
            return 1;
        }

        return (int) $value;
    }

    private function authorizedBranchIds(string $permission = 'session.update'): array
    {
        $context = session()->get();
        $branches = $context['authorizedBranches'] ?? $context['branchIds'] ?? [];
        if (service('branchContext')->isSuperAdmin() || service('branchContext')->isBranchAdminContext()) {
            $branches = array_column(service('branchContext')->authorizedBranches(true), 'id');
        }
        if (!is_array($branches)) {
            return [];
        }

        $authorized = [];
        foreach ($branches as $branch) {
            if (!ctype_digit((string) $branch) || (int) $branch < 1) {
                continue;
            }

            $branchId = (int) $branch;
            if (service('authorization')->can($permission, (string) $branchId)) {
                $authorized[] = $branchId;
            }
        }

        $coachActor = $this->strictCoachActorId();
        if ($coachActor !== null && $authorized !== []) {
            $assignedBranches = Database::connect('default')
                ->table('user_branches ub')
                ->select('ub.branch_id')
                ->join('branches b', 'b.id = ub.branch_id')
                ->where('ub.user_id', $coachActor)
                ->where('b.is_active', 1)
                ->get()
                ->getResultArray();
            $assignedIds = array_map(
                static fn(array $row): int => (int) $row['branch_id'],
                $assignedBranches
            );
            $authorized = array_values(array_intersect($authorized, $assignedIds));
        }

        return array_values(array_unique($authorized));
    }

    private function utc(string $v): string
    {
        $v = trim(str_replace('T', ' ', $v));
        if ($v === '') {
            throw new RuntimeException('Invalid calendar date.');
        }

        $hasOffset = (bool) preg_match('/(?:Z|[+-]\d{2}:\d{2})$/', $v);
        $datePart = substr($v, 0, 10);
        $isoDate = $this->calendarDate($datePart)->format('Y-m-d');
        $v = $isoDate . substr($v, 10);

        if (trim(substr($v, 10)) === '') {
            throw new RuntimeException('Invalid session time.');
        }

        if (! $hasOffset && preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $v)) {
            $v .= ':00';
        }

        try {
            if ($hasOffset) {
                return (new DateTimeImmutable($v))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
            }

            if (! preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $v)) {
                throw new RuntimeException('Invalid session time.');
            }

            $local = DateTimeImmutable::createFromFormat(
                '!Y-m-d H:i:s',
                $v,
                new DateTimeZone((string) config('App')->appTimezone)
            );
            $errors = DateTimeImmutable::getLastErrors();
            if ($local === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
                throw new RuntimeException('Invalid session window.');
            }
            return $local->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        } catch (Throwable $exception) {
            if ($exception instanceof RuntimeException) {
                throw $exception;
            }
            throw new RuntimeException('Invalid session window.');
        }
    }

    private function optionalUtc(mixed $value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        return $this->utc((string) $value);
    }

    private function calendarDate(string $value): DateTimeImmutable
    {
        $value = trim($value);
        $iso = $value;
        if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $value, $parts)) {
            $iso = $parts[3] . '-' . $parts[2] . '-' . $parts[1];
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $iso)) {
            throw new RuntimeException('Invalid calendar date.');
        }

        $date = DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $iso,
            new DateTimeZone((string) config('App')->appTimezone)
        );
        $errors = DateTimeImmutable::getLastErrors();
        if (
            $date === false
            || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $date->format('Y-m-d') !== $iso
        ) {
            throw new RuntimeException('Invalid calendar date.');
        }

        return $date;
    }
    private function local(string $v): string
    {
        return (new DateTimeImmutable($v, new DateTimeZone('UTC')))->setTimezone(new DateTimeZone((string) config('App')->appTimezone))->format('Y-m-d H:i:sP');
    }
    private function window(array $i): void
    {
        $a = (string) ($i['starts_at'] ?? '');
        $b = $i['ends_at'] ?? null;
        if ($b === null || trim((string) $b) === '') {
            return;
        }
        $b = (string) $b;
        $x = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $a, new DateTimeZone('UTC'));
        $y = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $b, new DateTimeZone('UTC'));
        if (!$x || !$y || $x->format('Y-m-d H:i:s') !== $a || $y->format('Y-m-d H:i:s') !== $b || $b <= $a) {
            throw new RuntimeException('Invalid session window.');
        }
    }
}
