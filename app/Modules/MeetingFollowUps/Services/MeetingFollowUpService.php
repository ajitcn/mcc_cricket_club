<?php

declare(strict_types=1);

namespace App\Modules\MeetingFollowUps\Services;

use App\Modules\MeetingFollowUps\Models\MeetingFollowUpModel;
use Config\Database;
use DateTimeImmutable;
use RuntimeException;
use Throwable;

final class MeetingFollowUpService
{
    private const TYPES = ['call', 'whatsapp', 'email', 'meeting', 'other'];
    private const STATUSES = ['pending', 'scheduled', 'completed', 'cancelled'];

    public function options(): array
    {
        return ['types' => self::TYPES, 'statuses' => self::STATUSES];
    }

    public function list(array $filters, int $page = 1, int $perPage = 20): array
    {
        $branches = service('branchContext')->authorizedBranchIds(true);
        $filters = $this->normalizeFilters($filters);
        $page = max(1, $page);
        if ($branches === []) {
            return ['followUps' => [], 'total' => 0, 'page' => 1, 'pages' => 1, 'filters' => $filters];
        }
        $count = $this->query($branches, $filters)->countAllResults();
        $pages = max(1, (int) ceil($count / $perPage));
        $page = min($page, $pages);
        $rows = $this->query($branches, $filters)
            ->orderBy('f.start_at', 'DESC')
            ->orderBy('f.id', 'DESC')
            ->limit($perPage, ($page - 1) * $perPage)
            ->get()
            ->getResultArray();

        return ['followUps' => $rows, 'total' => $count, 'page' => $page, 'pages' => $pages, 'filters' => $filters];
    }

    public function admissionTestsForSelection(): array
    {
        $branches = service('branchContext')->authorizedBranchIds(true);
        if ($branches === []) {
            return [];
        }
        return Database::connect('default')->table('admission_tests at')
            ->select('at.id, at.applicant_full_name, at.branch_id, at.status, at.trial_id, b.name AS branch_name')
            ->join('branches b', 'b.id = at.branch_id')
            ->join('trials t', 't.id = at.trial_id')
            ->whereIn('at.branch_id', $branches)
            ->where('t.branch_id = at.branch_id', null, false)
            ->orderBy('at.id', 'DESC')
            ->get()->getResultArray();
    }

    public function assignableUsers(): array
    {
        $branches = service('branchContext')->authorizedBranchIds(true);
        if ($branches === []) {
            return [];
        }
        return Database::connect('default')->table('users u')
            ->distinct()->select('u.id, u.display_name, u.email')
            ->join('user_branches ub', 'ub.user_id = u.id')
            ->whereIn('ub.branch_id', $branches)->where('u.is_enabled', 1)
            ->orderBy('u.display_name')->get()->getResultArray();
    }

    public function find(int $id): array
    {
        $row = $this->query(service('branchContext')->authorizedBranchIds(true), [])
            ->where('f.id', $id)->get()->getRowArray();
        if (! is_array($row)) {
            throw new RuntimeException('Not found.');
        }
        $this->authorize((int) $row['branch_id'], 'admission.view');
        return $row;
    }

    public function create(array $input): int
    {
        $testId = $this->positiveInt($input['admission_test_id'] ?? null);
        $db = Database::connect('default');
        $test = $this->test($db, $testId);
        $actor = $this->authorize((int) $test['branch_id'], 'admission.create');
        $data = $this->validated($input, $test, true);
        $now = gmdate('Y-m-d H:i:s');
        $db->transStart();
        try {
            $data += [
                'admission_test_id' => $testId,
                'trial_id' => (int) $test['trial_id'],
                'branch_id' => (int) $test['branch_id'],
                'created_by' => $actor,
                'updated_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $model = new MeetingFollowUpModel();
            if ($model->insert($data) === false) {
                throw new RuntimeException('Unable to create Meeting Follow-up.');
            }
            $id = (int) $model->getInsertID();
            service('auditPersistence')->record($actor, 'meeting_follow_up_created', 'meeting_follow_up', (string) $id, (int) $test['branch_id'], null, $this->auditData($data), null, $db);
            $db->transComplete();
            $this->assertTransaction($db);
            return $id;
        } catch (Throwable $exception) {
            $db->transRollback();
            throw $exception;
        }
    }

    public function update(int $id, array $input): void
    {
        $db = Database::connect('default');
        $current = $this->findForUpdate($db, $id);
        $actor = $this->authorize((int) $current['branch_id'], 'admission.update');
        if (array_key_exists('admission_test_id', $input) && (int) $input['admission_test_id'] !== (int) $current['admission_test_id']) {
            throw new RuntimeException('Meeting Follow-up linkage cannot be changed.');
        }
        $expected = trim((string) ($input['version'] ?? ''));
        if ($expected === '' || (int) $expected !== (int) $current['version']) {
            throw new RuntimeException('Update conflict.');
        }
        $data = $this->validated($input, $current, false);
        $data['version'] = (int) $current['version'] + 1;
        $data['updated_by'] = $actor;
        $data['updated_at'] = gmdate('Y-m-d H:i:s');
        $db->transStart();
        try {
            $db->table('admission_test_followups')->where('id', $id)->where('version', (int) $current['version'])->update($data);
            if ($db->affectedRows() !== 1) {
                throw new RuntimeException('Update conflict.');
            }
            service('auditPersistence')->record($actor, 'meeting_follow_up_updated', 'meeting_follow_up', (string) $id, (int) $current['branch_id'], $this->auditData($current), $this->auditData($data), null, $db);
            $db->transComplete();
            $this->assertTransaction($db);
        } catch (Throwable $exception) {
            $db->transRollback();
            throw $exception;
        }
    }

    private function query(array $branches, array $filters): object
    {
        $query = Database::connect('default')->table('admission_test_followups f')
            ->select('f.*, at.applicant_full_name, at.status AS admission_test_status, t.trial_date, b.name AS branch_name, u.display_name AS assigned_user_name, a.id AS admission_id, a.admission_number')
            ->join('admission_tests at', 'at.id = f.admission_test_id')
            ->join('trials t', 't.id = f.trial_id')
            ->join('branches b', 'b.id = f.branch_id')
            ->join('users u', 'u.id = f.assigned_user_id', 'left')
            ->join('admissions a', 'a.admission_test_id = f.admission_test_id', 'left')
            ->whereIn('f.branch_id', $branches)
            ->where('at.branch_id = f.branch_id', null, false)
            ->where('t.branch_id = f.branch_id', null, false);
        if (($filters['applicant'] ?? '') !== '') {
            $query->like('at.applicant_full_name', $filters['applicant']);
        }
        foreach (['admission_test_id', 'trial_id', 'branch_id', 'status', 'assigned_user_id'] as $field) {
            if (($filters[$field] ?? '') !== '') {
                $query->where('f.' . $field, $filters[$field]);
            }
        }
        if (($filters['date_from'] ?? '') !== '') {
            $query->where('f.start_at >=', $filters['date_from'] . ' 00:00:00');
        }
        if (($filters['date_to'] ?? '') !== '') {
            $query->where('f.start_at <', date('Y-m-d', strtotime($filters['date_to'] . ' +1 day')) . ' 00:00:00');
        }
        return $query;
    }

    private function test(object $db, int $id): array
    {
        $row = $db->table('admission_tests at')->select('at.*, t.branch_id AS trial_branch_id')
            ->join('trials t', 't.id = at.trial_id')->where('at.id', $id)->get()->getRowArray();
        if (! is_array($row) || (int) $row['branch_id'] !== (int) $row['trial_branch_id']) {
            throw new RuntimeException('Not found.');
        }
        return $row;
    }

    private function findForUpdate(object $db, int $id): array
    {
        $row = $this->query(service('branchContext')->authorizedBranchIds(true), [])->where('f.id', $id)->get()->getRowArray();
        if (! is_array($row)) {
            throw new RuntimeException('Not found.');
        }
        return $row;
    }

    private function validated(array $input, array $test, bool $creating): array
    {
        $typeValue = $input['follow_up_type'] ?? null;
        $statusValue = $input['status'] ?? 'scheduled';
        $timeValue = $input['start_time'] ?? null;
        $type = is_scalar($typeValue) ? strtolower(trim((string) $typeValue)) : '';
        $status = is_scalar($statusValue) ? strtolower(trim((string) $statusValue)) : '';
        $time = is_scalar($timeValue) ? trim((string) $timeValue) : '';
        $fieldErrors = [];

        if (! in_array($type, self::TYPES, true)) {
            $fieldErrors['follow_up_type'] = 'Select a valid follow-up type.';
        }
        if (! in_array($status, self::STATUSES, true)) {
            $fieldErrors['status'] = 'Select a valid status.';
        }
        if (array_key_exists('start_time', $input)
            && ! preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time)) {
            $fieldErrors['start_time'] = 'Enter a valid start time.';
        }

        $start = null;
        try {
            $start = $this->dateTime($input['start_at'] ?? ($input['follow_up_at'] ?? null));
        } catch (RuntimeException) {
            $fieldErrors['start_at'] = 'Enter a valid start date.';
        }

        $assigned = $input['assigned_user_id'] ?? null;
        $hasAssignedUser = $assigned !== null && $assigned !== '';
        if ($hasAssignedUser && (! is_scalar($assigned) || ! is_numeric($assigned))) {
            $fieldErrors['assigned_user_id'] = 'Select an authorized assigned user.';
        } elseif ($hasAssignedUser) {
            $valid = Database::connect('default')->table('user_branches')
                ->where(['user_id' => (int) $assigned, 'branch_id' => (int) $test['branch_id']])->countAllResults();
            if ($valid < 1) {
                $fieldErrors['assigned_user_id'] = 'Select an authorized assigned user.';
            }
        }

        if ($fieldErrors !== []) {
            throw new MeetingFollowUpValidationException($fieldErrors);
        }

        return [
            'follow_up_at' => $start,
            'start_at' => $start,
            'follow_up_type' => $type,
            'status' => $status,
            'assigned_user_id' => $assigned !== null && $assigned !== '' ? (int) $assigned : null,
            // The existing table keeps notes as NOT NULL. Preserve a blank
            // browser submission as an empty string instead of sending NULL.
            'notes' => trim((string) ($input['notes'] ?? '')),
            'outcome' => trim((string) ($input['outcome'] ?? '')) ?: null,
            'next_action' => trim((string) ($input['next_action'] ?? '')) ?: null,
        ];
    }

    private function dateTime(mixed $value, bool $nullable = false): ?string
    {
        if (! is_scalar($value)) {
            throw new RuntimeException('Invalid follow-up date.');
        }

        $value = str_replace('T', ' ', trim((string) $value));
        if ($value === '' && $nullable) {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat('Y-m-d H:i', $value)
            ?: DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value);
        if (! $date || $date->format('Y-m-d H:i') !== substr($value, 0, 16)) {
            throw new RuntimeException('Invalid follow-up date.');
        }
        return $date->format('Y-m-d H:i:s');
    }

    private function normalizeFilters(array $filters): array
    {
        return array_intersect_key($filters, array_flip(['applicant', 'admission_test_id', 'trial_id', 'branch_id', 'status', 'assigned_user_id', 'date_from', 'date_to']));
    }

    private function positiveInt(mixed $value): int
    {
        if (! is_scalar($value) || ! ctype_digit((string) $value) || (int) $value < 1) {
            throw new RuntimeException('Invalid Admission Test.');
        }
        return (int) $value;
    }

    private function authorize(int $branch, string $permission): int
    {
        if ($branch < 1 || ! service('authorization')->can($permission, (string) $branch)) {
            throw new RuntimeException('Forbidden');
        }
        $user = session('user');
        if (! is_array($user) || (int) ($user['id'] ?? 0) < 1) {
            throw new RuntimeException('Authentication required');
        }
        return (int) $user['id'];
    }

    private function auditData(array $data): array
    {
        return ['admission_test_id' => $data['admission_test_id'] ?? null, 'trial_id' => $data['trial_id'] ?? null, 'version' => $data['version'] ?? null, 'status' => $data['status'] ?? null];
    }

    private function assertTransaction(object $db): void
    {
        if (! $db->transStatus()) {
            $db->resetTransStatus();
            throw new RuntimeException('Unable to save Meeting Follow-up.');
        }
    }
}
