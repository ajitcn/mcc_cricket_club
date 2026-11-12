<?php

declare(strict_types=1);

namespace App\Services;

use App\Modules\Coaches\Models\CoachProfileModel;
use Config\Database;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

final class CoachManagementService
{
    private const ROLE = 'Coach';

    public function list(array $filters = []): array
    {
        $builder = $this->coachQuery();
        $branches = $this->authorizedBranches();

        if ($branches === []) {
            return [
                'coaches' => [],
                'total' => 0,
                'page' => 1,
                'pages' => 1,
                'filters' => $filters,
                'branches' => [],
            ];
        }

        $builder->whereIn('ub.branch_id', $branches);
        $this->restrictCoachViewer($builder);

        $query = trim((string) ($filters['q'] ?? ''));
        if ($query !== '') {
            $builder->groupStart()
                ->like('u.display_name', $query)
                ->orLike('u.email', $query)
                ->orLike('cp.specialization', $query)
                ->groupEnd();
        }

        $requestedBranchValue = $filters['branch_id'] ?? '';
        $requestedBranch = is_scalar($requestedBranchValue)
            ? trim((string) $requestedBranchValue)
            : '__invalid__';
        if ($requestedBranch !== '') {
            if (!ctype_digit($requestedBranch) || !in_array((int) $requestedBranch, $branches, true)) {
                return [
                    'coaches' => [],
                    'total' => 0,
                    'page' => 1,
                    'pages' => 1,
                    'filters' => $filters,
                    'branches' => $this->branches($branches),
                ];
            }

            $builder->where('ub.branch_id', (int) $requestedBranch);
        }

        if (($filters['status'] ?? '') === 'active') {
            $builder->where('cp.is_active', 1);
        } elseif (($filters['status'] ?? '') === 'inactive') {
            $builder->where('cp.is_active', 0);
        }

        $total = (clone $builder)->countAllResults();
        $perPage = 20;
        $page = max(1, (int) ($filters['page'] ?? 1));
        $rows = $builder
            ->orderBy('u.display_name', 'ASC')
            ->get($perPage, ($page - 1) * $perPage)
            ->getResultArray();

        return [
            'coaches' => $rows,
            'total' => $total,
            'page' => $page,
            'pages' => max(1, (int) ceil($total / $perPage)),
            'filters' => $filters,
            'branches' => $this->branches($branches),
        ];
    }

    public function find(int $userId): array
    {
        $authorizedBranches = $this->authorizedBranches();
        if ($authorizedBranches === []) {
            throw new RuntimeException('Not found.');
        }

        $builder = $this->coachQuery()
            ->where('u.id', $userId)
            ->whereIn('ub.branch_id', $authorizedBranches)
            ->orderBy('ub.branch_id', 'ASC');
        $this->restrictCoachViewer($builder);

        $coaches = $builder->get()->getResultArray();
        if ($coaches === []) {
            $exists = Database::connect('default')->table('users u')
                ->select('u.id')
                ->join('user_roles ur', 'ur.user_id = u.id')
                ->join('roles r', 'r.id = ur.role_id')
                ->where('u.id', $userId)
                ->where('r.name', self::ROLE)
                ->get()
                ->getRowArray();

            if (is_array($exists)) {
                throw new RuntimeException('Forbidden');
            }

            throw new RuntimeException('Not found.');
        }

        $coach = $coaches[0];
        $this->authorize((int) $coach['branch_id'], 'coach.view');

        $db = Database::connect('default');
        $coach['branches'] = $db->table('user_branches ub')
            ->select('b.id, b.name, b.code')
            ->join('branches b', 'b.id = ub.branch_id')
            ->where('ub.user_id', $userId)
            ->whereIn('ub.branch_id', $authorizedBranches)
            ->orderBy('b.name')
            ->get()
            ->getResultArray();
        $coach['branch_scope'] = count($coach['branches']) > 1
            ? 'All Authorized Branches'
            : ($coach['branches'][0]['name'] ?? $coach['branch_name']);
        $coach['branch_name'] = $coach['branch_scope'];
        $coach['assigned_players_count'] = $this->assignedPlayersCount($userId);
        $coach['assigned_sessions_count'] = $this->assignedSessionsCount($userId);
        $coach['leave'] = $this->leave($userId);
        $coach['availability_records'] = $this->availability($userId);
        $coach['feedback_options'] = $this->feedbackOptions($userId);
        $canViewKpis = service('authorization')->can('coach.kpi.view', (string) $coach['branch_id']);
        $coach['kpis'] = $canViewKpis ? $this->kpis($userId) : null;
        $coach['kpi_visible'] = $canViewKpis;

        return $coach;
    }

    public function sessionsPage(int $userId, array $filters = []): array
    {
        $coach = $this->find($userId);

        $branches = $this->authorizedBranches();
        $builder = $this->sessionBuilderForCoach($userId, $branches, $filters);
        $total = (clone $builder)->countAllResults();
        $perPage = 20;
        $page = max(1, (int) ($filters['page'] ?? 1));
        $rows = $builder
            ->orderBy('s.starts_at', 'DESC')
            ->orderBy('s.id', 'DESC')
            ->get($perPage, ($page - 1) * $perPage)
            ->getResultArray();

        return [
            'coach' => $coach,
            'sessions' => $rows,
            'total' => $total,
            'page' => $page,
            'pages' => max(1, (int) ceil($total / $perPage)),
            'filters' => $filters,
            'branches' => $this->branches($branches),
        ];
    }

    /** @return array<string, mixed> */
    public function sessionDetail(int $userId, int $sessionId): array
    {
        $this->find($userId);
        $branches = $this->authorizedBranches();
        if ($branches === []) {
            throw new RuntimeException('Not found.');
        }

        $row = $this->sessionBuilderForCoach($userId, $branches, [])
            ->where('s.id', $sessionId)
            ->get()
            ->getRowArray();
        if (! is_array($row)) {
            throw new RuntimeException('Not found.');
        }

        $this->authorize((int) $row['branch_id'], 'coach.view');

        return $row;
    }

    public function playersPage(int $userId, array $filters = []): array
    {
        $coach = $this->find($userId);

        $branches = $this->authorizedBranches();
        $builder = $this->playerAssignmentBuilder($userId, $branches, $filters);
        $total = (clone $builder)->countAllResults();
        $perPage = 20;
        $page = max(1, (int) ($filters['page'] ?? 1));
        $rows = $builder
            ->orderBy('pe.full_name', 'ASC')
            ->orderBy('pca.id', 'DESC')
            ->get($perPage, ($page - 1) * $perPage)
            ->getResultArray();

        return [
            'coach' => $coach,
            'players' => $rows,
            'total' => $total,
            'page' => $page,
            'pages' => max(1, (int) ceil($total / $perPage)),
            'filters' => $filters,
            'branches' => $this->branches($branches),
            'batchOptions' => Database::connect('default')
                ->table('batches')
                ->select('id, name')
                ->where('is_active', 1)
                ->orderBy('name', 'ASC')
                ->get()
                ->getResultArray(),
        ];
    }

    public function saveProfile(int $userId, array $input, bool $create = false): void
    {
        $permission = $create ? 'coach.create' : 'coach.update';
        $user = $this->eligibleUser($userId);
        $this->authorize((int) $user['branch_id'], $permission);

        $specialization = trim((string) ($input['specialization'] ?? ''));
        $qualification = trim((string) ($input['qualification'] ?? ''));
        $certifications = trim((string) ($input['certifications'] ?? ''));
        $availability = trim((string) ($input['availability'] ?? ''));
        $experience = trim((string) ($input['experience_years'] ?? ''));
        $active = (int) ($input['is_active'] ?? 0);
        $reason = trim((string) ($input['reason'] ?? ''));

        if (strlen($specialization) > 150 || strlen($qualification) > 10000
            || strlen($certifications) > 10000 || strlen($availability) > 10000
            || strlen($experience) > 150 || !in_array($active, [0, 1], true)
            || (!$create && $reason === '')) {
            throw new RuntimeException('Invalid coach profile.');
        }

        $data = [
            'specialization' => $specialization !== '' ? $specialization : null,
            'experience_years' => $experience !== '' ? $experience : null,
            'qualification' => $qualification !== '' ? $qualification : null,
            'certifications' => $certifications !== '' ? $certifications : null,
            'availability' => $availability !== '' ? $availability : null,
            'is_active' => $active,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $model = new CoachProfileModel();
        $existing = $model->where('user_id', $userId)->first();
        if (is_array($existing)) {
            $model->update((int) $existing['id'], $data);
            service('auditPersistence')->record(
                $this->actor(),
                'coach_profile_updated',
                'coach',
                (string) $userId,
                (int) $user['branch_id'],
                $existing,
                $data,
                $reason
            );
            return;
        }

        $model->insert($data + [
            'user_id' => $userId,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        service('auditPersistence')->record(
            $this->actor(),
            'coach_profile_created',
            'coach',
            (string) $userId,
            (int) $user['branch_id'],
            null,
            $data
        );
    }

    public function eligibleUsers(): array
    {
        $branches = $this->authorizedBranches();
        if ($branches === []) {
            return [];
        }

        return Database::connect('default')->table('users u')
            ->select('u.id, u.display_name, u.email, ub.branch_id')
            ->join('user_roles ur', 'ur.user_id = u.id')
            ->join('roles r', 'r.id = ur.role_id')
            ->join('user_branches ub', 'ub.user_id = u.id')
            ->where('r.name', self::ROLE)
            ->where('u.is_enabled', 1)
            ->whereIn('ub.branch_id', $branches)
            ->orderBy('u.display_name')
            ->get()
            ->getResultArray();
    }

    public function managementBranches(?string $permission = null): array
    {
        $branches = $this->authorizedBranches();
        if ($permission !== null) {
            $branches = array_values(array_filter(
                $branches,
                fn(int $branchId): bool => $this->canManageBranch($branchId, $permission)
            ));
        }

        return $this->branches($branches);
    }

    public function canManageBranch(int $branchId, string $permission): bool
    {
        if ($branchId < 1 || !in_array($branchId, $this->authorizedBranches(), true)) {
            return false;
        }

        return service('authorization')->can($permission, (string) $branchId);
    }

    public function assignBranch(int $coachUserId, int $branchId, bool $assign, string $reason): void
    {
        $actor = $this->actor();
        $reason = trim($reason);
        if ($reason === '') {
            throw new RuntimeException('A reason is required.');
        }

        $db = Database::connect('default');
        $this->assertCoach($db, $coachUserId);
        $this->authorize($branchId, 'coach.assign');
        if ((int) $db->table('branches')->where(['id' => $branchId, 'is_active' => 1])->countAllResults() !== 1) {
            throw new RuntimeException('Invalid branch.');
        }

        $before = $this->branchIds($db, $coachUserId);
        if ($assign) {
            $db->table('user_branches')->ignore(true)->insert([
                'user_id' => $coachUserId,
                'branch_id' => $branchId,
                'created_at' => gmdate('Y-m-d H:i:s'),
            ]);
        } else {
            if (count($before) < 2) {
                throw new RuntimeException('A Coach must retain one active branch.');
            }
            $db->table('user_branches')->where([
                'user_id' => $coachUserId,
                'branch_id' => $branchId,
            ])->delete();
        }

        $after = $this->branchIds($db, $coachUserId);
        service('auditPersistence')->record(
            $actor,
            $assign ? 'coach_branch_assigned' : 'coach_branch_revoked',
            'coach',
            (string) $coachUserId,
            $branchId,
            ['branches' => $before],
            ['branches' => $after],
            $reason
        );
    }

    public function saveLeave(int $coachUserId, int $branchId, array $input, ?int $id = null): int
    {
        $actor = $this->actor();
        $this->authorize($branchId, 'coach.update');
        $this->assertCoachBranch($coachUserId, $branchId);
        $from = $this->dateTime((string) ($input['unavailable_from'] ?? ''));
        $to = $this->dateTime((string) ($input['unavailable_to'] ?? ''));
        $reason = trim((string) ($input['reason'] ?? ''));
        $correctionReason = trim((string) ($input['correction_reason'] ?? ''));
        if ($from === null || $to === null || $reason === '' || $from >= $to) {
            throw new RuntimeException('Leave dates and reason are required.');
        }
        if ($id !== null && $correctionReason === '') {
            throw new RuntimeException('A correction reason is required.');
        }

        $db = Database::connect('default');
        $query = $db->table('coach_unavailability')->where([
            'coach_user_id' => $coachUserId,
            'branch_id' => $branchId,
        ])->whereIn('status', ['PENDING', 'APPROVED'])
            ->where('unavailable_from <', $to)
            ->where('unavailable_to >', $from);
        if ($id !== null) {
            $query->where('id !=', $id);
        }
        if ($query->countAllResults() > 0) {
            throw new RuntimeException('Overlapping leave is not allowed.');
        }

        $now = gmdate('Y-m-d H:i:s');
        $existing = null;
        $data = [
            'coach_user_id' => $coachUserId,
            'branch_id' => $branchId,
            'unavailable_from' => $from,
            'unavailable_to' => $to,
            'reason' => $reason,
            'status' => 'PENDING',
            'updated_at' => $now,
        ];
        if ($id === null) {
            $data['created_by'] = $actor;
            $data['created_at'] = $now;
            $db->table('coach_unavailability')->insert($data);
            $id = (int) $db->insertID();
            $action = 'coach_leave_created';
        } else {
            $existing = $db->table('coach_unavailability')->where([
                'id' => $id,
                'coach_user_id' => $coachUserId,
                'branch_id' => $branchId,
            ])->get()->getRowArray();
            if (! is_array($existing) || in_array(($existing['status'] ?? ''), ['CANCELLED', 'REJECTED'], true)) {
                throw new RuntimeException('Leave record not found.');
            }
            $data['status'] = $existing['status'];
            $data['status_changed_by'] = $existing['status_changed_by'] ?? null;
            $data['status_changed_at'] = $existing['status_changed_at'] ?? null;
            $data['status_reason'] = $existing['status_reason'] ?? null;
            $db->table('coach_unavailability')->where('id', $id)->update($data);
            $action = 'coach_leave_updated';
        }
        service('auditPersistence')->record(
            $actor,
            $action,
            'coach_leave',
            (string) $id,
            $branchId,
            $existing,
            $data,
            $id !== null && $action === 'coach_leave_updated'
                ? $correctionReason
                : $reason
        );

        return $id;
    }

    public function approveLeave(int $id, string $reason = ''): void
    {
        $this->transitionLeave($id, 'APPROVED', trim($reason), false);
    }

    public function rejectLeave(int $id, string $reason): void
    {
        $this->transitionLeave($id, 'REJECTED', trim($reason), true);
    }

    private function transitionLeave(int $id, string $newStatus, string $reason, bool $reasonRequired): void
    {
        $db = Database::connect('default');
        $leave = $db->table('coach_unavailability')->where('id', $id)->get()->getRowArray();
        if (! is_array($leave)) {
            throw new RuntimeException('Leave record not found.');
        }
        $branchId = (int) $leave['branch_id'];
        $this->authorize($branchId, 'coach.update');
        if (($leave['status'] ?? '') !== 'PENDING') {
            throw new RuntimeException('Only pending leave can be approved or rejected.');
        }
        if ($reasonRequired && $reason === '') {
            throw new RuntimeException('A rejection reason is required.');
        }
        if ($newStatus === 'APPROVED') {
            $overlap = $db->table('coach_unavailability')
                ->where([
                    'coach_user_id' => (int) $leave['coach_user_id'],
                    'branch_id' => $branchId,
                    'status' => 'APPROVED',
                ])
                ->where('id !=', $id)
                ->where('unavailable_from <', $leave['unavailable_to'])
                ->where('unavailable_to >', $leave['unavailable_from'])
                ->countAllResults();
            if ($overlap > 0) {
                throw new RuntimeException('Overlapping leave is not allowed.');
            }
        }

        $actor = $this->actor();
        $now = gmdate('Y-m-d H:i:s');
        $after = [
            'status' => $newStatus,
            'status_changed_by' => $actor,
            'status_changed_at' => $now,
            'status_reason' => $reason !== '' ? $reason : null,
            'updated_at' => $now,
        ];
        $db->transStart();
        $db->table('coach_unavailability')->where('id', $id)->update($after);
        service('auditPersistence')->record(
            $actor,
            'coach_leave_' . strtolower($newStatus),
            'coach_leave',
            (string) $id,
            $branchId,
            $leave,
            $after,
            $reason !== '' ? $reason : null,
            $db
        );
        $db->transComplete();
        if ($db->transStatus() === false) {
            throw new RuntimeException('Unable to update leave status.');
        }
    }

    public function cancelLeave(int $id, string $reason): void
    {
        $reason = trim($reason);
        $db = Database::connect('default');
        $leave = $db->table('coach_unavailability')->where('id', $id)->get()->getRowArray();
        if (! is_array($leave)) {
            throw new RuntimeException('Leave record not found.');
        }
        if ($reason === '') {
            throw new RuntimeException('A cancellation reason is required.');
        }
        $actor = $this->actor();
        $status = (string) ($leave['status'] ?? '');
        $isOwnPending = (int) ($leave['coach_user_id'] ?? 0) === $actor
            && $status === 'PENDING'
            && in_array(self::ROLE, (array) session('roles'), true)
            && service('authorization')->canAccessBranch((string) $leave['branch_id']);
        if (! $isOwnPending) {
            $this->authorize((int) $leave['branch_id'], 'coach.update');
            if (! in_array($status, ['PENDING', 'APPROVED'], true)) {
                throw new RuntimeException('Only pending or approved leave can be cancelled.');
            }
        }
        $now = gmdate('Y-m-d H:i:s');
        $after = [
            'status' => 'CANCELLED',
            'cancelled_by' => $actor,
            'cancelled_at' => $now,
            'status_changed_by' => $actor,
            'status_changed_at' => $now,
            'status_reason' => $reason,
            'updated_at' => $now,
        ];
        $db->transStart();
        $db->table('coach_unavailability')->where('id', $id)->update($after);
        service('auditPersistence')->record($actor, 'coach_leave_cancelled', 'coach_leave', (string) $id, (int) $leave['branch_id'], $leave, $after, $reason, $db);
        $db->transComplete();
        if ($db->transStatus() === false) {
            throw new RuntimeException('Unable to cancel leave.');
        }
    }

    public function saveAvailability(int $coachUserId, int $branchId, array $input, ?int $id = null): int
    {
        $actor = $this->actor();
        $this->authorize($branchId, 'coach.update');
        $this->assertCoachBranch($coachUserId, $branchId);

        $weekdayValue = trim((string) ($input['weekday'] ?? ''));
        $dateValue = trim((string) ($input['available_date'] ?? ''));
        $weekday = $weekdayValue === '' ? null : (int) $weekdayValue;
        $availableDate = $dateValue === '' ? null : $this->availabilityDate($dateValue);
        $startTime = $this->availabilityTime((string) ($input['start_time'] ?? ''));
        $endTime = $this->availabilityTime((string) ($input['end_time'] ?? ''));
        $reason = trim((string) ($input['reason'] ?? ''));
        $correctionReason = trim((string) ($input['correction_reason'] ?? ''));

        if (($weekday === null) === ($availableDate === null)
            || ($weekday !== null && ($weekday < 1 || $weekday > 7))
            || $startTime === null
            || $endTime === null
            || $startTime >= $endTime
            || $reason === '') {
            throw new RuntimeException('Availability type, times, and reason are required.');
        }
        if ($id !== null && $correctionReason === '') {
            throw new RuntimeException('A correction reason is required.');
        }

        $db = Database::connect('default');
        if (! $db->tableExists('coach_availability')) {
            throw new RuntimeException('Availability storage is not available.');
        }
        $query = $db->table('coach_availability')
            ->where([
                'coach_user_id' => $coachUserId,
                'branch_id' => $branchId,
                'cancelled_at' => null,
            ])
            ->where('start_time <', $endTime)
            ->where('end_time >', $startTime);
        if ($weekday !== null) {
            $query->where('weekday', $weekday)->where('available_date', null);
        } else {
            $query->where('available_date', $availableDate)->where('weekday', null);
        }
        if ($id !== null) {
            $query->where('id !=', $id);
        }
        if ($query->countAllResults() > 0) {
            throw new RuntimeException('Overlapping availability is not allowed.');
        }

        $now = gmdate('Y-m-d H:i:s');
        $data = [
            'branch_id' => $branchId,
            'coach_user_id' => $coachUserId,
            'weekday' => $weekday,
            'available_date' => $availableDate,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'reason' => $reason,
            'updated_at' => $now,
        ];
        $existing = null;
        if ($id === null) {
            $data['created_by'] = $actor;
            $data['created_at'] = $now;
            $db->table('coach_availability')->insert($data);
            $id = (int) $db->insertID();
            $action = 'coach_availability_created';
        } else {
            $existing = $db->table('coach_availability')->where([
                'id' => $id,
                'coach_user_id' => $coachUserId,
                'branch_id' => $branchId,
                'cancelled_at' => null,
            ])->get()->getRowArray();
            if (! is_array($existing)) {
                throw new RuntimeException('Availability record not found.');
            }
            $db->table('coach_availability')->where('id', $id)->update($data);
            $action = 'coach_availability_updated';
        }

        service('auditPersistence')->record(
            $actor,
            $action,
            'coach_availability',
            (string) $id,
            $branchId,
            $existing,
            $data,
            $id !== null && $action === 'coach_availability_updated'
                ? $correctionReason
                : $reason
        );

        return $id;
    }

    public function cancelAvailability(int $id, string $reason): void
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new RuntimeException('A cancellation reason is required.');
        }
        $db = Database::connect('default');
        $availability = $db->table('coach_availability')->where([
            'id' => $id,
            'cancelled_at' => null,
        ])->get()->getRowArray();
        if (! is_array($availability)) {
            throw new RuntimeException('Availability record not found.');
        }
        $this->authorize((int) $availability['branch_id'], 'coach.update');
        $now = gmdate('Y-m-d H:i:s');
        $db->table('coach_availability')->where('id', $id)->update([
            'cancelled_at' => $now,
            'updated_at' => $now,
        ]);
        service('auditPersistence')->record(
            $this->actor(),
            'coach_availability_cancelled',
            'coach_availability',
            (string) $id,
            (int) $availability['branch_id'],
            $availability,
            ['cancelled_at' => $now],
            $reason
        );
    }

    /** @return list<array<string, mixed>> */
    public function availability(int $coachUserId): array
    {
        $branches = $this->authorizedBranches();
        $db = Database::connect('default');
        if ($branches === [] || ! $db->tableExists('coach_availability')) {
            return [];
        }

        return $db->table('coach_availability ca')
            ->select('ca.id, ca.branch_id, ca.weekday, ca.available_date, ca.start_time, ca.end_time, ca.reason, ca.created_at, ca.updated_at, ca.cancelled_at, b.name AS branch_name')
            ->join('branches b', 'b.id = ca.branch_id')
            ->where('ca.coach_user_id', $coachUserId)
            ->whereIn('ca.branch_id', $branches)
            ->orderBy('ca.cancelled_at', 'ASC')
            ->orderBy('ca.available_date', 'ASC')
            ->orderBy('ca.weekday', 'ASC')
            ->orderBy('ca.start_time', 'ASC')
            ->get()
            ->getResultArray();
    }

    public function saveFeedback(int $coachUserId, array $input, ?int $id = null): int
    {
        $sessionId = (int) ($input['session_id'] ?? 0);
        $playerId = (int) ($input['player_id'] ?? 0);
        $rating = (int) ($input['rating'] ?? 0);
        $reason = trim((string) ($input['reason'] ?? ''));
        $db = Database::connect('default');
        $session = $db->table('sessions')
            ->where(['id' => $sessionId, 'coach_user_id' => $coachUserId])
            ->whereIn('status', ['scheduled', 'active', 'completed'])
            ->get()
            ->getRowArray();
        if (! is_array($session)) {
            throw new RuntimeException('Feedback is not available for this session.');
        }
        $branch = (int) $session['branch_id'];
        $this->authorize($branch, 'coach.update');
        if ($rating < 1 || $rating > 5 || $reason === '') {
            throw new RuntimeException('A rating from 1 to 5 and reason are required.');
        }
        if ($db->table('session_players')->where(['session_id' => $sessionId, 'player_id' => $playerId])->countAllResults() !== 1) {
            throw new RuntimeException('Player is not part of this session.');
        }
        $snapshot = $db->table('attendance_roster_snapshots ars')
            ->select('ars.id')
            ->where(['ars.session_id' => $sessionId, 'ars.branch_id' => $branch])
            ->get()
            ->getRowArray();
        if (! is_array($snapshot)) {
            throw new RuntimeException('Attendance roster snapshot required.');
        }
        if ($db->table('attendance_roster_snapshot_members')->where([
            'snapshot_id' => (int) $snapshot['id'],
            'player_id' => $playerId,
            'branch_id' => $branch,
        ])->countAllResults() !== 1) {
            throw new RuntimeException('Player is not part of the attendance roster.');
        }
        if ($db->table('attendance')->where([
            'session_id' => $sessionId,
            'player_id' => $playerId,
            'branch_id' => $branch,
        ])->countAllResults() !== 1) {
            throw new RuntimeException('Attendance must be recorded before feedback.');
        }
        if (! $db->fieldExists('version', 'coach_feedback')) {
            throw new RuntimeException('Feedback version support is unavailable.');
        }

        $existing = null;
        if ($id === null) {
            $existing = $db->table('coach_feedback')->where([
                'branch_id' => $branch,
                'coach_user_id' => $coachUserId,
                'player_id' => $playerId,
                'session_id' => $sessionId,
                'deleted_at' => null,
            ])->get()->getRowArray();
            if (is_array($existing)) {
                return (int) $existing['id'];
            }
        }

        $now = gmdate('Y-m-d H:i:s');
        $data = ['rating' => $rating, 'updated_at' => $now, 'approved' => 1];
        $db->transStart();
        try {
            if ($id === null) {
                $data += [
                    'branch_id' => $branch,
                    'coach_user_id' => $coachUserId,
                    'player_id' => $playerId,
                    'session_id' => $sessionId,
                    'created_by' => $this->actor(),
                    'created_at' => $now,
                    'version' => 1,
                ];
                $db->table('coach_feedback')->insert($data);
                $id = (int) $db->insertID();
                $action = 'coach_feedback_created';
            } else {
                $existing = $db->table('coach_feedback')->where([
                    'id' => $id,
                    'branch_id' => $branch,
                    'coach_user_id' => $coachUserId,
                    'deleted_at' => null,
                ])->get()->getRowArray();
                if (! is_array($existing)) {
                    throw new RuntimeException('Feedback record not found.');
                }
                $expectedVersion = isset($input['expected_version'])
                    && (string) $input['expected_version'] !== ''
                    ? (int) $input['expected_version']
                    : 0;
                if ($expectedVersion < 1) {
                    throw new RuntimeException('Feedback version required.');
                }
                if ((int) $existing['version'] !== $expectedVersion) {
                    throw new RuntimeException(
                        'This feedback record changed in another session. Reload the latest feedback data and try again.',
                    );
                }
                $nextVersion = $expectedVersion + 1;
                $data['version'] = $nextVersion;
                $updated = $db->table('coach_feedback')
                    ->where(['id' => $id, 'version' => $expectedVersion])
                    ->update($data);
                if ($updated !== true || $db->affectedRows() !== 1) {
                    throw new RuntimeException(
                        'This feedback record changed in another session. Reload the latest feedback data and try again.',
                    );
                }
                $action = 'coach_feedback_corrected';
            }

            service('auditPersistence')->record(
                $this->actor(),
                $action,
                'coach_feedback',
                (string) $id,
                $branch,
                $existing ?? null,
                ['rating' => $rating, 'version' => (int) ($data['version'] ?? 1)],
                $reason
            );
            $db->transComplete();
        } catch (Throwable $exception) {
            $db->transRollback();
            throw $exception;
        }
        if ($db->transStatus() === false) {
            throw new RuntimeException('Feedback could not be saved.');
        }

        return $id;
    }

    /** @return list<array<string, mixed>> */
    public function feedback(int $coachUserId): array
    {
        $branches = $this->authorizedBranches();
        if ($branches === []) {
            return [];
        }

        $db = Database::connect('default');
        $rows = $db->table('coach_feedback cf')
            ->select('cf.id, cf.branch_id, cf.session_id, cf.player_id, cf.rating, cf.version, cf.approved, '
                . 'cf.created_at, cf.updated_at, s.starts_at, s.ends_at, '
                . 'pe.full_name AS player_name, b.name AS branch_name')
            ->join('sessions s', 's.id = cf.session_id')
            ->join('players p', 'p.id = cf.player_id')
            ->join('persons pe', 'pe.id = p.person_id')
            ->join('branches b', 'b.id = cf.branch_id')
            ->where('cf.coach_user_id', $coachUserId)
            ->whereIn('cf.branch_id', $branches)
            ->where('cf.approved', 1)
            ->where('cf.deleted_at', null)
            ->orderBy('cf.created_at', 'DESC')
            ->get()
            ->getResultArray();

        foreach ($rows as &$row) {
            $audit = $db->table('audit_logs')
                ->select('reason')
                ->where([
                    'entity_type' => 'coach_feedback',
                    'entity_id' => (string) $row['id'],
                ])
                ->whereIn('action', ['coach_feedback_created', 'coach_feedback_corrected'])
                ->orderBy('id', 'DESC')
                ->get()
                ->getRowArray();
            $row['comment'] = is_array($audit) ? ($audit['reason'] ?? null) : null;
        }
        unset($row);

        return $rows;
    }

    /**
     * Return the attendance and feedback state for every immutable roster
     * member in a session. The controller remains responsible for deciding
     * whether the current user may edit the feedback.
     *
     * @return list<array<string, mixed>>
     */
    public function sessionFeedback(int $sessionId): array
    {
        $db = Database::connect('default');
        $session = $db->table('sessions')
            ->select('id, branch_id, coach_user_id, status')
            ->where('id', $sessionId)
            ->get()
            ->getRowArray();

        if (! is_array($session)) {
            return [];
        }

        $branchId = (int) $session['branch_id'];
        if (! service('authorization')->can('attendance.view', (string) $branchId)
            && ! service('authorization')->can('coach.update', (string) $branchId)) {
            return [];
        }

        $snapshot = $db->table('attendance_roster_snapshots')
            ->select('id')
            ->where(['session_id' => $sessionId, 'branch_id' => $branchId])
            ->get()
            ->getRowArray();
        if (! is_array($snapshot)) {
            return [];
        }

        $rows = $db->table('attendance_roster_snapshot_members arsm')
            ->select('arsm.player_id, pe.full_name, a.id AS attendance_id, '
                . 'a.status AS attendance_status, cf.id AS feedback_id, cf.rating, cf.version AS feedback_version, '
                . 'cf.updated_at AS feedback_updated_at, '
                . '(SELECT al.reason FROM audit_logs al WHERE al.entity_type = \'coach_feedback\' '
                . 'AND al.entity_id = CAST(cf.id AS CHAR) ORDER BY al.id DESC LIMIT 1) AS feedback_reason')
            ->join('players p', 'p.id = arsm.player_id')
            ->join('persons pe', 'pe.id = p.person_id')
            ->join('attendance a', 'a.session_id = ' . $sessionId . ' AND a.player_id = arsm.player_id AND a.branch_id = arsm.branch_id', 'left', false)
            ->join('coach_feedback cf', 'cf.session_id = ' . $sessionId . ' AND cf.player_id = arsm.player_id AND cf.branch_id = arsm.branch_id AND cf.deleted_at IS NULL', 'left', false)
            ->where('arsm.snapshot_id', (int) $snapshot['id'])
            ->where('arsm.branch_id', $branchId)
            ->orderBy('pe.full_name', 'ASC')
            ->get()
            ->getResultArray();

        foreach ($rows as &$row) {
            $row['can_update'] = service('authorization')->can('coach.update', (string) $branchId)
                && in_array((string) $session['status'], ['scheduled', 'active', 'completed'], true)
                && $row['attendance_id'] !== null;
        }
        unset($row);

        return $rows;
    }

    private function coachQuery(): object
    {
        return Database::connect('default')->table('users u')
            ->select('u.id, u.display_name, u.email, u.is_enabled, ub.branch_id, b.name AS branch_name, '
                . 'COALESCE(cp.specialization, \'Not provided\') AS specialization, '
                . 'cp.experience_years, cp.qualification, cp.certifications, cp.availability, '
                . 'COALESCE(cp.is_active, 1) AS is_active, '
                . '(SELECT COUNT(*) FROM player_coach_assignments pca '
                . 'WHERE pca.coach_user_id = u.id AND pca.branch_id = ub.branch_id '
                . 'AND pca.effective_to IS NULL) AS assigned_players, '
                . '(SELECT COUNT(*) FROM sessions s '
                . 'WHERE s.coach_user_id = u.id AND s.branch_id = ub.branch_id '
                . 'AND s.status = \'scheduled\') AS assigned_sessions')
            ->join('user_roles ur', 'ur.user_id = u.id')
            ->join('roles r', 'r.id = ur.role_id')
            ->join('user_branches ub', 'ub.user_id = u.id')
            ->join('branches b', 'b.id = ub.branch_id')
            ->join('coach_profiles cp', 'cp.user_id = u.id', 'left')
            ->where('r.name', self::ROLE)
            ->groupBy('u.id, u.display_name, u.email, u.is_enabled, ub.branch_id, b.name, cp.specialization, '
                . 'cp.experience_years, cp.qualification, cp.certifications, cp.availability, cp.is_active');
    }

    private function eligibleUser(int $userId): array
    {
        $row = $this->coachQuery()
            ->where('u.id', $userId)
            ->whereIn('ub.branch_id', $this->authorizedBranches())
            ->orderBy('ub.branch_id', 'ASC')
            ->get()
            ->getRowArray();

        if (!is_array($row)) {
            throw new RuntimeException('Invalid coach.');
        }

        return $row;
    }

    private function restrictCoachViewer(object $builder): void
    {
        $roles = (array) session('roles');
        if (in_array(self::ROLE, $roles, true)
            && !service('authorization')->can('rbac.manage')) {
            $user = session('user');
            $builder->where('u.id', is_array($user) ? (int) ($user['id'] ?? 0) : 0);
        }
    }

    private function authorize(int $branchId, string $permission): void
    {
        if (!service('authorization')->can($permission, (string) $branchId)) {
            throw new RuntimeException('Forbidden');
        }
    }

    private function authorizedBranches(): array
    {
        return service('branchContext')->authorizedBranchIds(true);
    }

    private function branches(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return Database::connect('default')->table('branches')
            ->whereIn('id', $ids)
            ->orderBy('name')
            ->get()
            ->getResultArray();
    }

    private function assignedPlayersCount(int $userId): int
    {
        $branches = $this->authorizedBranches();
        if ($branches === []) {
            return 0;
        }

        return (int) Database::connect('default')->table('player_coach_assignments')
            ->where('coach_user_id', $userId)
            ->where('effective_to', null)
            ->whereIn('branch_id', $branches)
            ->countAllResults();
    }

    private function assignedSessionsCount(int $userId): int
    {
        $branches = $this->authorizedBranches();
        if ($branches === []) {
            return 0;
        }

        return (int) Database::connect('default')->table('sessions')
            ->where('coach_user_id', $userId)
            ->whereIn('branch_id', $branches)
            ->countAllResults();
    }

    private function sessionBuilderForCoach(int $userId, array $branches, array $filters): object
    {
        $builder = Database::connect('default')->table('sessions s')
            ->select('s.id, s.starts_at, s.ends_at, s.status, s.branch_id, s.batch_id, '
                . 's.coach_user_id, b.name AS branch_name, ba.name AS batch_name, '
                . 'p.name AS program_name, u.display_name AS coach_name, g.name AS ground_name, '
                . '(SELECT COUNT(*) FROM attendance_roster_snapshots ars '
                . 'WHERE ars.session_id = s.id AND ars.branch_id = s.branch_id) AS roster_snapshot_count, '
                . '(SELECT COUNT(*) FROM attendance a '
                . 'WHERE a.session_id = s.id AND a.branch_id = s.branch_id) AS attendance_recorded_count')
            ->join('branches b', 'b.id = s.branch_id')
            ->join('batches ba', 'ba.id = s.batch_id')
            ->join('programs p', 'p.id = ba.program_id', 'left')
            ->join('users u', 'u.id = s.coach_user_id')
            ->join('grounds g', 'g.id = s.ground_id', 'left')
            ->where('s.coach_user_id', $userId)
            ->whereIn('s.branch_id', $branches);

        $this->applyBranchFilter($builder, 's.branch_id', $filters, $branches);
        if (($filters['status'] ?? '') !== '') {
            $builder->where('s.status', (string) $filters['status']);
        }

        return $builder;
    }

    private function playerAssignmentBuilder(int $userId, array $branches, array $filters): object
    {
        $builder = Database::connect('default')->table('player_coach_assignments pca')
            ->select('pca.player_id, pca.branch_id, pca.effective_from, p.status, '
                . 'pe.full_name, b.name AS branch_name, '
                . '(SELECT ba.name FROM player_batch_assignments pba '
                . 'INNER JOIN batches ba ON ba.id = pba.batch_id '
                . 'WHERE pba.player_id = pca.player_id AND pba.effective_to IS NULL '
                . 'ORDER BY pba.effective_from DESC LIMIT 1) AS batch_name, '
                . '(SELECT pr.name FROM player_batch_assignments pba '
                . 'INNER JOIN batches ba ON ba.id = pba.batch_id '
                . 'INNER JOIN programs pr ON pr.id = ba.program_id '
                . 'WHERE pba.player_id = pca.player_id AND pba.effective_to IS NULL '
                . 'ORDER BY pba.effective_from DESC LIMIT 1) AS program_name')
            ->join('players p', 'p.id = pca.player_id')
            ->join('persons pe', 'pe.id = p.person_id')
            ->join('branches b', 'b.id = pca.branch_id')
            ->where('pca.coach_user_id', $userId)
            ->where('pca.effective_to', null)
            ->whereIn('pca.branch_id', $branches);

        $this->applyBranchFilter($builder, 'pca.branch_id', $filters, $branches);
        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $builder->like('pe.full_name', $search);
        }

        $batchValue = trim((string) ($filters['batch_id'] ?? ''));
        if ($batchValue !== '' && ! ctype_digit($batchValue)) {
            $builder->where('pca.player_id', 0);
        } elseif ($batchValue !== '') {
            $builder->where(
                'EXISTS (SELECT 1 FROM player_batch_assignments pba_filter '
                    . 'WHERE pba_filter.player_id = pca.player_id '
                    . 'AND pba_filter.effective_to IS NULL '
                    . 'AND pba_filter.batch_id = ' . (int) $batchValue . ')',
                null,
                false
            );
        }

        if (($filters['status'] ?? '') !== '') {
            $builder->where('p.status', (string) $filters['status']);
        }

        return $builder;
    }

    private function applyBranchFilter(object $builder, string $column, array $filters, array $branches): void
    {
        $branchValue = trim((string) ($filters['branch_id'] ?? ''));
        if ($branchValue === '') {
            return;
        }

        if (! ctype_digit($branchValue) || ! in_array((int) $branchValue, $branches, true)) {
            $builder->where($column, 0);
            return;
        }

        $builder->where($column, (int) $branchValue);
    }

    private function leave(int $userId): array
    {
        return Database::connect('default')->table('coach_unavailability cu')
            ->select('cu.id, cu.branch_id, cu.unavailable_from, cu.unavailable_to, cu.reason, cu.status, cu.status_changed_by, cu.status_changed_at, cu.status_reason, cu.cancelled_by, cu.cancelled_at, b.name AS branch_name')
            ->join('branches b', 'b.id = cu.branch_id')
            ->where('cu.coach_user_id', $userId)
            ->whereIn('cu.branch_id', $this->authorizedBranches())
            ->orderBy('cu.unavailable_from')
            ->get()
            ->getResultArray();
    }

    private function feedbackOptions(int $userId): array
    {
        return Database::connect('default')->table('sessions s')
            ->select('s.id AS session_id, s.branch_id, s.ends_at, sp.player_id, pe.full_name')
            ->join('session_players sp', 'sp.session_id = s.id')
            ->join('players p', 'p.id = sp.player_id')
            ->join('persons pe', 'pe.id = p.person_id')
            ->join('attendance_roster_snapshots ars', 'ars.session_id = s.id AND ars.branch_id = s.branch_id')
            ->join('attendance_roster_snapshot_members arsm', 'arsm.snapshot_id = ars.id AND arsm.player_id = sp.player_id AND arsm.branch_id = s.branch_id')
            ->join('attendance a', 'a.session_id = s.id AND a.player_id = sp.player_id AND a.branch_id = s.branch_id')
            ->where('s.coach_user_id', $userId)
            ->whereIn('s.status', ['scheduled', 'active', 'completed'])
            ->whereIn('s.branch_id', $this->authorizedBranches())
            ->orderBy('s.ends_at', 'DESC')
            ->limit(100)
            ->get()
            ->getResultArray();
    }

    private function kpis(int $userId): array
    {
        $db = Database::connect('default');
        $branches = $this->authorizedBranches();
        $conducted = $db->table('sessions')
            ->where('coach_user_id', $userId)
            ->whereIn('branch_id', $branches)
            ->where('status !=', 'cancelled')
            ->where('ends_at <=', gmdate('Y-m-d H:i:s'))
            ->countAllResults();
        $players = $db->table('player_coach_assignments')
            ->where('coach_user_id', $userId)
            ->whereIn('branch_id', $branches)
            ->where('effective_to', null)
            ->countAllResults();
        $roster = $db->query('SELECT COUNT(*) AS total FROM session_players sp INNER JOIN sessions s ON s.id = sp.session_id '
            . 'LEFT JOIN attendance a ON a.session_id = sp.session_id AND a.player_id = sp.player_id AND a.branch_id = s.branch_id '
            . 'WHERE s.coach_user_id = ? AND s.branch_id IN ('
            . implode(',', array_fill(0, count($branches), '?'))
            . ') AND s.status <> ? AND s.ends_at <= '
            . '? AND (a.status IS NULL OR a.status NOT IN (\'LEAVE\', \'MEDICAL\', \'RAIN_CANCELLED\', \'COACH_CANCELLED\'))',
            array_merge([$userId], $branches, ['cancelled', gmdate('Y-m-d H:i:s')]))->getRowArray();
        $recorded = $db->query("SELECT COUNT(*) AS total FROM attendance a INNER JOIN sessions s ON s.id = a.session_id AND a.branch_id = s.branch_id WHERE s.coach_user_id = ? AND s.branch_id IN ("
            . implode(',', array_fill(0, count($branches), '?'))
            . ") AND s.status <> ? AND s.ends_at <= ? AND a.status IN ('PRESENT', 'LATE')",
            array_merge([$userId], $branches, ['cancelled', gmdate('Y-m-d H:i:s')]))->getRowArray();
        $rosterTotal = (int) ($roster['total'] ?? 0);
        $compliance = $rosterTotal > 0
            ? round(((int) ($recorded['total'] ?? 0) / $rosterTotal) * 100, 1)
            : null;
        $feedback = [];
        if ($db->tableExists('coach_feedback')) {
            $feedback = $db->table('coach_feedback')
                ->selectAvg('rating', 'average_rating')
                ->where(['coach_user_id' => $userId, 'approved' => 1])
                ->whereIn('branch_id', $branches)
                ->where('deleted_at', null)
                ->get()
                ->getRowArray();
        }

        return [
            'sessions_conducted' => $conducted,
            'assigned_players' => $players,
            'attendance_compliance' => $compliance,
            'player_feedback_rating' => isset($feedback['average_rating']) && $feedback['average_rating'] !== null
                ? round((float) $feedback['average_rating'], 2)
                : null,
            'performance_improvement_index' => service('performance', false)->coachImprovementIndex($userId),
        ];
    }

    private function actor(): int
    {
        $user = session('user');
        if (! is_array($user) || (int) ($user['id'] ?? 0) < 1) {
            throw new RuntimeException('Authentication required.');
        }

        return (int) $user['id'];
    }

    private function dateTime(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        $parsed = date_create($value);

        return $parsed === false ? null : $parsed->format('Y-m-d H:i:s');
    }

    private function availabilityDate(string $value): ?string
    {
        $timezone = new DateTimeZone(config('App')->appTimezone ?: 'Asia/Kolkata');
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', trim($value), $timezone);
        $errors = DateTimeImmutable::getLastErrors();
        if (
            $date === false
            || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
        ) {
            return null;
        }

        return $date->format('Y-m-d');
    }

    private function availabilityTime(string $value): ?string
    {
        $time = DateTimeImmutable::createFromFormat('!H:i', trim($value));
        $errors = DateTimeImmutable::getLastErrors();
        if (
            $time === false
            || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
        ) {
            return null;
        }

        return $time->format('H:i:s');
    }

    private function assertCoach(object $db, int $userId): void
    {
        $exists = $db->table('users u')
            ->join('user_roles ur', 'ur.user_id = u.id')
            ->join('roles r', 'r.id = ur.role_id')
            ->where(['u.id' => $userId, 'r.name' => self::ROLE])
            ->countAllResults();
        if ($exists !== 1) {
            throw new RuntimeException('Invalid Coach.');
        }
    }

    private function assertCoachBranch(int $coachUserId, int $branchId): void
    {
        $db = Database::connect('default');
        $this->assertCoach($db, $coachUserId);
        if ($db->table('user_branches')->where(['user_id' => $coachUserId, 'branch_id' => $branchId])->countAllResults() !== 1) {
            throw new RuntimeException('Coach is not assigned to this branch.');
        }
    }

    private function branchIds(object $db, int $userId): array
    {
        return array_map(
            'intval',
            array_column($db->table('user_branches')->select('branch_id')->where('user_id', $userId)->orderBy('branch_id')->get()->getResultArray(), 'branch_id')
        );
    }
}
