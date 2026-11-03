<?php

declare(strict_types=1);

namespace App\Services;

use App\Modules\Admissions\Models\AdmissionFollowUpModel;
use Config\Database;
use DateTimeImmutable;
use RuntimeException;
use Throwable;

final class AdmissionFollowUpService
{
    private const FOLLOW_UP_PROJECTION = 'admission_followups.id, admission_followups.admission_id,
        admission_followups.branch_id, admission_followups.follow_up_at,
        admission_followups.follow_up_type, admission_followups.status,
        admission_followups.notes, admission_followups.outcome,
        admission_followups.next_follow_up_at, admission_followups.assigned_user_id,
        admission_followups.created_by, admission_followups.updated_by,
        admission_followups.created_at, admission_followups.updated_at,
        a.admission_number AS linked_admission_number,
        a.applicant_full_name AS applicant_name,
        a.applicant_email AS applicant_email,
        a.applicant_phone AS applicant_phone,
        a.status AS admission_status,
        a.converted_player_id AS converted_player_id,
        b.name AS branch_name, u.display_name AS assigned_user_name';

    private const TYPES = ['call', 'whatsapp', 'email', 'meeting', 'other'];
    private const STATUSES = ['pending', 'scheduled', 'completed', 'cancelled'];
    private const FILTER_STATUSES = ['pending', 'scheduled', 'completed', 'cancelled'];
    private const CREATION_STATUS = 'scheduled';
    private const NOTES_MAX_LENGTH = 65535;

    public function options(): array
    {
        return [
            'types' => self::TYPES,
            'statuses' => self::STATUSES,
            'filter_statuses' => self::FILTER_STATUSES,
        ];
    }

    public function canCreateForAdmission(array $admission): bool
    {
        $status = $admission['admission_status'] ?? $admission['status'] ?? '';

        return ! in_array(
            strtoupper(trim((string) $status)),
            ['APPROVED', 'COMPLETED', 'CONVERTED'],
            true,
        ) && ($admission['converted_player_id'] ?? null) === null;
    }

    public function admissionsForSelection(): array
    {
        $branches = service('branchContext')->authorizedBranchIds(true);
        if ($branches === []) {
            return [];
        }

        return Database::connect('default')->table('admissions a')
            ->select('a.id, a.admission_number, a.applicant_full_name, a.branch_id, b.name AS branch_name')
            ->join('branches b', 'b.id = a.branch_id')
            ->whereIn('a.branch_id', $branches)
            ->whereNotIn('a.status', ['APPROVED', 'COMPLETED', 'CONVERTED'])
            ->where('a.converted_player_id IS NULL', null, false)
            ->orderBy('a.id', 'DESC')
            ->get()
            ->getResultArray();
    }

    public function assignableUsers(): array
    {
        $branches = service('branchContext')->authorizedBranchIds(true);
        if ($branches === []) {
            return [];
        }

        return Database::connect('default')->table('users u')
            ->distinct()
            ->select('u.id, u.display_name, u.email')
            ->join('user_branches ub', 'ub.user_id = u.id')
            ->whereIn('ub.branch_id', $branches)
            ->where('u.is_enabled', 1)
            ->orderBy('u.display_name')
            ->get()
            ->getResultArray();
    }

    public function paginated(array $filters, int $page = 1, int $perPage = 20): array
    {
        $branches = service('branchContext')->authorizedBranchIds(true);
        $page = max(1, $page);
        $perPage = min(100, max(1, $perPage));
        $filters = $this->normalizeFilters($filters);
        $filterErrors = $this->filterErrors($filters);

        if ($branches === []) {
            return [
                'followUps' => [],
                'total' => 0,
                'page' => 1,
                'pages' => 1,
                'filters' => $filters,
                'filterErrors' => $filterErrors,
            ];
        }

        if ($filterErrors !== []) {
            return [
                'followUps' => [],
                'total' => 0,
                'page' => 1,
                'pages' => 1,
                'filters' => $filters,
                'filterErrors' => $filterErrors,
            ];
        }

        $countQuery = $this->baseQuery($branches);
        if (! $this->applyFilters($countQuery, $filters, $branches)) {
            return [
                'followUps' => [],
                'total' => 0,
                'page' => 1,
                'pages' => 1,
                'filters' => $filters,
                'filterErrors' => [],
            ];
        }

        $total = $countQuery->countAllResults();
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);

        $query = $this->baseQuery($branches);
        $this->applyFilters($query, $filters, $branches);
        $rows = $query->orderBy('admission_followups.follow_up_at', 'DESC')
            ->orderBy('admission_followups.id', 'DESC')
            ->limit($perPage, ($page - 1) * $perPage)
            ->get()
            ->getResultArray();
        $rows = array_map([$this, 'normalizeFollowUpProjection'], $rows);

        return [
            'followUps' => $rows,
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'filters' => $filters,
            'filterErrors' => [],
        ];
    }

    public function summaryCounts(array $filters): array
    {
        $branches = service('branchContext')->authorizedBranchIds(true);
        $filters = $this->normalizeFilters($filters);
        $counts = ['all' => 0, 'pending' => 0, 'scheduled' => 0, 'completed' => 0, 'cancelled' => 0, 'overdue' => 0];
        if ($branches === []) {
            return $counts;
        }

        $baseFilters = $filters;
        unset($baseFilters['status'], $baseFilters['next_due']);
        $base = $this->baseQuery($branches);
        if (! $this->applyFilters($base, $baseFilters, $branches)) {
            return $counts;
        }
        $counts['all'] = (int) $base->countAllResults();

        foreach (['pending', 'completed', 'cancelled'] as $status) {
            $query = $this->baseQuery($branches);
            $this->applyFilters($query, $baseFilters, $branches);
            $counts[$status] = (int) $query->where('admission_followups.status', $status)->countAllResults();
        }

        $now = date('Y-m-d H:i:s');
        $scheduled = $this->baseQuery($branches);
        $this->applyFilters($scheduled, $baseFilters, $branches);
        $counts['scheduled'] = (int) $scheduled
            ->groupStart()
            ->where('admission_followups.status', 'scheduled')
            ->orGroupStart()
            ->where('admission_followups.status', 'pending')
            ->where('admission_followups.next_follow_up_at >', $now)
            ->groupEnd()
            ->groupEnd()
            ->countAllResults();

        $overdue = $this->baseQuery($branches);
        $this->applyFilters($overdue, $baseFilters, $branches);
        $counts['overdue'] = (int) $overdue
            ->where('admission_followups.status', 'pending')
            ->where('admission_followups.next_follow_up_at <=', $now)
            ->where('admission_followups.next_follow_up_at IS NOT NULL', null, false)
            ->countAllResults();

        return $counts;
    }

    public function find(int $id): array
    {
        $row = $this->query()->where('admission_followups.id', $id)->get()->getRowArray();
        if (! is_array($row)) {
            throw new RuntimeException('Not found.');
        }
        $row = $this->normalizeFollowUpProjection($row);
        $this->authorize((int) $row['branch_id'], 'admission.view');
        return $row;
    }

    public function forAdmission(int $admissionId): array
    {
        $rows = $this->query()->where('admission_followups.admission_id', $admissionId)
            ->orderBy('admission_followups.follow_up_at', 'DESC')
            ->orderBy('admission_followups.id', 'DESC')
            ->get()->getResultArray();
        $rows = array_map([$this, 'normalizeFollowUpProjection'], $rows);
        if ($rows !== []) {
            $this->authorize((int) $rows[0]['branch_id'], 'admission.view');
        }
        return $rows;
    }

    /** @param array<string, mixed> $row
     *  @return array<string, mixed>
     */
    private function normalizeFollowUpProjection(array $row): array
    {
        $row['admission_number'] = $row['linked_admission_number'] ?? null;
        unset($row['linked_admission_number']);

        return $row;
    }

    /** @return list<array<string, mixed>> */
    public function auditHistory(int $followUpId, int $branch): array
    {
        if (! service('authorization')->can('audit.view', (string) $branch)) {
            return [];
        }

        return Database::connect('default')->table('audit_logs al')
            ->select('al.action, al.reason, al.created_at, u.display_name AS actor_name')
            ->join('users u', 'u.id = al.actor_user_id', 'left')
            ->where('al.entity_type', 'admission_follow_up')
            ->where('al.entity_id', (string) $followUpId)
            ->where('al.branch_id', $branch)
            ->orderBy('al.created_at', 'DESC')
            ->orderBy('al.id', 'DESC')
            ->get()
            ->getResultArray();
    }

    private function normalizeFilters(array $filters): array
    {
        return array_intersect_key($filters, array_flip([
            'admission_id', 'branch_id', 'status', 'follow_up_type', 'assigned_user_id',
            'date_from', 'date_to', 'next_due', 'applicant',
        ]));
    }

    private function baseQuery(array $branches): object
    {
        return (new AdmissionFollowUpModel())
            ->select(self::FOLLOW_UP_PROJECTION)
            ->join('admissions a', 'a.id = admission_followups.admission_id')
            ->join('branches b', 'b.id = admission_followups.branch_id')
            ->join('users u', 'u.id = admission_followups.assigned_user_id', 'left')
            ->whereIn('admission_followups.branch_id', $branches)
            ->where('a.branch_id = admission_followups.branch_id', null, false);
    }

    /** @return array<string, string> */
    private function filterErrors(array $filters): array
    {
        $errors = [];
        $from = trim((string) ($filters['date_from'] ?? ''));
        $to = trim((string) ($filters['date_to'] ?? ''));

        foreach (['date_from' => 'Enter a valid Follow-up Start Date.', 'date_to' => 'Enter a valid Follow-up End Date.'] as $field => $message) {
            if (($filters[$field] ?? '') !== '' && ! $this->isDate((string) $filters[$field])) {
                $errors[$field] = $message;
            }
        }

        if ($from !== '' && $to !== '' && $this->isDate($from) && $this->isDate($to) && $from > $to) {
            $errors['date_from'] = 'Follow-up Start Date must be on or before Follow-up End Date.';
            $errors['date_to'] = 'Follow-up End Date must be on or after Follow-up Start Date.';
        }

        return $errors;
    }

    private function applyFilters(object $query, array $filters, array $branches): bool
    {
        $applicant = trim((string) ($filters['applicant'] ?? ''));
        if ($applicant !== '') {
            $search = Database::connect('default')->escapeLikeString(mb_strtolower($applicant));
            $query->where(
                "LOWER(a.applicant_full_name) LIKE '%" . $search . "%'",
                null,
                false,
            );
        }

        if (($filters['admission_id'] ?? '') !== '' && ctype_digit((string) $filters['admission_id'])) {
            $query->where('admission_followups.admission_id', (int) $filters['admission_id']);
        }
        if (($filters['branch_id'] ?? '') !== '') {
            $branch = (int) $filters['branch_id'];
            if (! in_array($branch, $branches, true)) {
                return false;
            }
            $query->where('admission_followups.branch_id', $branch);
        }
        if (($filters['status'] ?? '') !== '') {
            if (! in_array((string) $filters['status'], self::FILTER_STATUSES, true)) {
                return false;
            }
            $query->where('admission_followups.status', (string) $filters['status']);
        }
        if (($filters['follow_up_type'] ?? '') !== '') {
            if (! in_array((string) $filters['follow_up_type'], self::TYPES, true)) {
                return false;
            }
            $query->where('admission_followups.follow_up_type', (string) $filters['follow_up_type']);
        }
        if (($filters['assigned_user_id'] ?? '') !== '' && ctype_digit((string) $filters['assigned_user_id'])) {
            $query->where('admission_followups.assigned_user_id', (int) $filters['assigned_user_id']);
        }
        foreach (['date_from', 'date_to'] as $field) {
            if (($filters[$field] ?? '') !== '' && ! $this->isDate((string) $filters[$field])) {
                return false;
            }
        }
        if (($filters['date_from'] ?? '') !== '') {
            $query->where('admission_followups.follow_up_at >=', $filters['date_from'] . ' 00:00:00');
        }
        if (($filters['date_to'] ?? '') !== '') {
            $query->where('admission_followups.follow_up_at <', date('Y-m-d', strtotime($filters['date_to'] . ' +1 day')) . ' 00:00:00');
        }
        if (($filters['next_due'] ?? '') === '1' || ($filters['next_due'] ?? '') === 'overdue') {
            $query->where('admission_followups.next_follow_up_at <=', date('Y-m-d H:i:s'))
                ->where('admission_followups.next_follow_up_at IS NOT NULL', null, false);
        }
        if (($filters['next_due'] ?? '') === 'scheduled') {
            $query->groupStart()
                ->where('admission_followups.status', 'scheduled')
                ->orGroupStart()
                ->where('admission_followups.status', 'pending')
                ->where('admission_followups.next_follow_up_at >', date('Y-m-d H:i:s'))
                ->where('admission_followups.next_follow_up_at IS NOT NULL', null, false)
                ->groupEnd()
                ->groupEnd();
        }

        return true;
    }

    public function create(array $input): int
    {
        $admissionId = $this->positiveInt($input['admission_id'] ?? null);
        $db = Database::connect('default');
        $db->transStart();
        try {
            $admission = service('admission')->details($admissionId);
            $branch = (int) $admission['branch_id'];
            $actor = $this->authorize($branch, 'admission.create');
            $data = $this->validatedInput(
                $input,
                $branch,
                true,
                self::CREATION_STATUS,
            );
            $this->assertAdmissionCanCreateFollowUp($db, $admissionId, $branch);
            $now = gmdate('Y-m-d H:i:s');
            $data += ['admission_id' => $admissionId, 'branch_id' => $branch, 'created_by' => $actor, 'updated_by' => null, 'created_at' => $now, 'updated_at' => $now];
            $model = new AdmissionFollowUpModel();
            if ($model->insert($data) === false) {
                throw new RuntimeException('Unable to create Admission follow-up.');
            }
            $id = (int) $model->getInsertID();
            if ($id < 1) {
                throw new RuntimeException('Unable to create Admission follow-up.');
            }
            service('auditPersistence')->record(
                $actor,
                'admission_follow_up_created',
                'admission_follow_up',
                (string) $id,
                $branch,
                null,
                $this->auditData($data),
                null,
                $db
            );
            $db->transComplete();
            $this->assertTransaction($db, 'Unable to create Admission follow-up.');
            return $id;
        } catch (Throwable $exception) {
            $db->transRollback();
            throw $exception;
        }
    }

    public function update(int $id, array $input): ?int
    {
        $db = Database::connect('default');
        $db->transStart();
        try {
            $current = $this->findForUpdate($db, $id);
            $actor = $this->authorize((int) $current['branch_id'], 'admission.update');
            if (($input['admission_id'] ?? '') !== '' && (int) $input['admission_id'] !== (int) $current['admission_id']) {
                throw new RuntimeException('Invalid Admission.');
            }
            $branch = (int) $current['branch_id'];
            $scheduleNext = $this->scheduleNextRequested($input);
            $submittedStatus = strtolower(trim((string) ($input['status'] ?? '')));
            if ($scheduleNext && ! in_array($submittedStatus, self::STATUSES, true)) {
                $input['status'] = (string) $current['status'];
            }
            $data = $this->validatedInput($input, $branch, false);
            $expected = trim((string) ($input['updated_at'] ?? ''));
            if ($expected !== '' && $expected !== (string) $current['updated_at']) {
                throw new RuntimeException('Update conflict.');
            }
            $nextFollowUpAt = null;
            if ($scheduleNext) {
                try {
                    $nextFollowUpAt = $this->dateTime(
                        $input['next_follow_up_at'] ?? null,
                        $input['next_follow_up_time'] ?? null,
                        true,
                    );
                } catch (RuntimeException $exception) {
                    throw new RuntimeException('Invalid next follow-up date.', 0, $exception);
                }
            }
            $now = gmdate('Y-m-d H:i:s');
            $data['updated_by'] = $actor;
            $data['updated_at'] = $now;
            $builder = $db->table('admission_followups')->where('id', $id);
            if ($expected !== '') {
                $builder->where('updated_at', $expected);
            }
            $builder->update($data);
            if ($expected !== '' && $db->affectedRows() !== 1) {
                throw new RuntimeException('Update conflict.');
            }
            service('auditPersistence')->record(
                $actor,
                'admission_follow_up_updated',
                'admission_follow_up',
                (string) $id,
                (int) $current['branch_id'],
                $this->auditData($current),
                $this->auditData($data),
                null,
                $db
            );

            $nextId = null;
            if ($scheduleNext) {
                $nextId = $this->createNextFollowUp(
                    $db,
                    $current,
                    $data,
                    (string) $nextFollowUpAt,
                    $actor,
                    $now,
                );
            }

            $db->transComplete();
            $this->assertTransaction($db, 'Unable to update Admission follow-up.');

            return $nextId;
        } catch (Throwable $exception) {
            $db->transRollback();
            throw $exception;
        }
    }

    private function scheduleNextRequested(array $input): bool
    {
        if (! array_key_exists('schedule_next_follow_up', $input)) {
            return false;
        }

        $value = strtolower(trim((string) $input['schedule_next_follow_up']));
        if (! in_array($value, ['1', 'on', 'true'], true)) {
            throw new RuntimeException('Invalid schedule option.');
        }

        return true;
    }

    private function createNextFollowUp(
        object $db,
        array $current,
        array $data,
        string $followUpAt,
        int $actor,
        string $now,
    ): int {
        $branch = (int) $current['branch_id'];
        $this->assertAdmissionCanCreateFollowUp($db, (int) $current['admission_id'], $branch);
        $duplicateQuery = $db->table('admission_followups')
            ->where('admission_id', (int) $current['admission_id'])
            ->where('branch_id', $branch)
            ->where('follow_up_at', $followUpAt)
            ->where('follow_up_type', $data['follow_up_type'])
            ->where('status', 'scheduled')
            ->where('notes', $data['notes']);

        if ($data['assigned_user_id'] === null) {
            $duplicateQuery->where('assigned_user_id IS NULL', null, false);
        } else {
            $duplicateQuery->where('assigned_user_id', $data['assigned_user_id']);
        }

        $duplicate = $duplicateQuery->orderBy('id', 'DESC')->get()->getRowArray();
        if (is_array($duplicate)) {
            return (int) $duplicate['id'];
        }

        $nextData = [
            'admission_id' => (int) $current['admission_id'],
            'branch_id' => $branch,
            'follow_up_at' => $followUpAt,
            'follow_up_type' => $data['follow_up_type'],
            'status' => 'scheduled',
            'notes' => $data['notes'],
            'outcome' => $data['outcome'],
            'next_follow_up_at' => null,
            'assigned_user_id' => $data['assigned_user_id'],
            'created_by' => $actor,
            'updated_by' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ];
        $this->assertAdmissionCanCreateFollowUp($db, (int) $current['admission_id'], $branch);
        $model = new AdmissionFollowUpModel();
        if ($model->insert($nextData) === false) {
            throw new RuntimeException('Unable to schedule Admission follow-up.');
        }

        $nextId = (int) $model->getInsertID();
        if ($nextId < 1) {
            throw new RuntimeException('Unable to schedule Admission follow-up.');
        }

        service('auditPersistence')->record(
            $actor,
            'admission_follow_up_scheduled',
            'admission_follow_up',
            (string) $nextId,
            $branch,
            null,
            $this->auditData($nextData) + ['source_follow_up_id' => (int) $current['id']],
            null,
            $db,
        );

        return $nextId;
    }

    private function query(): object
    {
        return (new AdmissionFollowUpModel())
            ->select(self::FOLLOW_UP_PROJECTION)
            ->join('admissions a', 'a.id = admission_followups.admission_id')
            ->join('branches b', 'b.id = admission_followups.branch_id')
            ->join('users u', 'u.id = admission_followups.assigned_user_id', 'left')
            ->where('a.branch_id = admission_followups.branch_id', null, false);
    }

    private function validatedInput(
        array $input,
        int $branch,
        bool $includeNext = true,
        ?string $forcedStatus = null,
    ): array
    {
        $followUpAt = $this->dateTime($input['follow_up_at'] ?? null, $input['follow_up_time'] ?? null, true);
        $type = strtolower(trim((string) ($input['follow_up_type'] ?? '')));
        $submittedStatus = strtolower(trim((string) ($input['status'] ?? '')));
        $status = $forcedStatus ?? $submittedStatus;
        $notes = trim((string) ($input['notes'] ?? ''));
        $outcome = trim((string) ($input['outcome'] ?? ''));
        $statusIsValid = $forcedStatus === null
            ? in_array($status, self::STATUSES, true)
            : $forcedStatus === self::CREATION_STATUS;
        if (
            $type === 'reschedule'
            || $status === 'reschedule'
            || ! in_array($type, self::TYPES, true)
            || ! $statusIsValid
            || $notes === ''
            || strlen($notes) > self::NOTES_MAX_LENGTH
            || strlen($outcome) > 255
        ) {
            throw new RuntimeException('Invalid Admission follow-up.');
        }
        $assigned = $this->nullablePositiveInt($input['assigned_user_id'] ?? null);
        if ($assigned !== null && ! $this->userAuthorizedForBranch($assigned, $branch)) {
            throw new RuntimeException('Invalid assigned user.');
        }
        $data = [
            'follow_up_at' => $followUpAt,
            'follow_up_type' => $type,
            'status' => $status,
            'notes' => $notes,
            'outcome' => $outcome ?: null,
            'assigned_user_id' => $assigned,
        ];

        if ($includeNext) {
            $data['next_follow_up_at'] = $this->dateTime(
                $input['next_follow_up_at'] ?? null,
                $input['next_follow_up_time'] ?? null,
                false,
            );
        }

        return $data;
    }

    private function dateTime($date, $time, bool $required): ?string
    {
        $date = trim((string) $date);
        $time = trim((string) $time);
        if ($date === '' && $time === '' && ! $required) {
            return null;
        }
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})(?:[ T].*)?$/', $date, $matches) === 1) {
            $date = $matches[3] . '-' . $matches[2] . '-' . $matches[1];
        }

        if (! preg_match('/^\d{2}-\d{2}-\d{4}$/', $date) || ! preg_match('/^\d{2}:\d{2}(?::\d{2})?$/', $time)) {
            throw new RuntimeException('Invalid follow-up date.');
        }

        if (strlen($time) === 5) {
            $time .= ':00';
        }

        $parsed = DateTimeImmutable::createFromFormat('!d-m-Y H:i:s', $date . ' ' . $time);
        if (! $parsed || $parsed->format('d-m-Y H:i:s') !== $date . ' ' . $time) {
            throw new RuntimeException('Invalid follow-up date.');
        }
        return $parsed->format('Y-m-d H:i:s');
    }

    private function userAuthorizedForBranch(int $userId, int $branch): bool
    {
        return Database::connect('default')->table('user_branches')->where(['user_id' => $userId, 'branch_id' => $branch])->countAllResults() === 1
            && Database::connect('default')->table('users')->where(['id' => $userId, 'is_enabled' => 1])->countAllResults() === 1;
    }

    private function findForUpdate(object $db, int $id): ?array
    {
        return $db->query('SELECT * FROM admission_followups WHERE id = ? LIMIT 1 FOR UPDATE', [$id])->getRowArray() ?: null;
    }

    private function assertAdmissionCanCreateFollowUp(object $db, int $admissionId, int $branch): void
    {
        $admission = $db->query(
            'SELECT status, converted_player_id, branch_id
             FROM admissions
             WHERE id = ? AND branch_id = ?
             LIMIT 1',
            [$admissionId, $branch],
        )->getRowArray();

        if (! is_array($admission)) {
            throw new RuntimeException('Invalid Admission.');
        }

        if (in_array((string) $admission['status'], ['APPROVED', 'COMPLETED', 'CONVERTED'], true)
            || $admission['converted_player_id'] !== null) {
            throw new RuntimeException(
                'No new follow-up can be created after the Admission is completed or converted.',
            );
        }
    }

    private function authorize(int $branch, string $permission): int
    {
        if ($branch < 1 || ! service('authorization')->can($permission, (string) $branch)) {
            throw new RuntimeException('Forbidden');
        }
        return (int) (session()->get('user')['id'] ?? session()->get('user_id') ?? 0);
    }

    private function positiveInt($value): int
    {
        $value = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($value === false) {
            throw new RuntimeException('Invalid Admission.');
        }
        return (int) $value;
    }

    private function nullablePositiveInt($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        return $this->positiveInt($value);
    }

    private function isDate(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value;
    }

    private function auditData(array $data): array
    {
        return ['admission_id' => $data['admission_id'] ?? null, 'follow_up_type' => $data['follow_up_type'] ?? null,
            'status' => $data['status'] ?? null, 'assigned_user_id' => $data['assigned_user_id'] ?? null,
            'follow_up_at' => $data['follow_up_at'] ?? null, 'next_follow_up_at' => $data['next_follow_up_at'] ?? null];
    }

    private function assertTransaction(object $db, string $message): void
    {
        if (! $db->transStatus()) {
            throw new RuntimeException($message);
        }
    }
}
