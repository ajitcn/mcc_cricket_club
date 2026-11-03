<?php

declare (strict_types=1);
namespace App\Services;

use App\Modules\CRM\Models\LeadFollowUpModel;
use App\Modules\CRM\Models\LeadModel;
use App\Modules\CRM\Models\LeadStatusHistoryModel;
use App\Modules\Admin\Models\BranchModel;
use App\Modules\CRM\Models\LeadSourceModel;
use App\Modules\CRM\Models\LeadStatusModel;
use CodeIgniter\HTTP\Files\UploadedFile;
use Config\Database;
use DateTimeImmutable;
use RuntimeException;
use Throwable;
final class CrmService
{
    private const STATUS_LEAD = 'lead';
    private const STATUS_LOST = 'lost';
    private const FOLLOW_UP_TYPES = ['call', 'whatsapp', 'email', 'meeting', 'other'];
    private const FOLLOW_UP_STATUSES = ['pending', 'completed', 'cancelled'];
    private const FOLLOW_UP_OUTCOMES = ['no_answer', 'contacted', 'interested', 'callback_requested', 'not_interested', 'invalid_contact'];
    public function availableBranches(): array
    {
        $ids = service('branchContext')->authorizedBranchIds(true);
        if ($ids === []) {
            return [];
        }
        return (new BranchModel())->whereIn('id', $ids)->where('is_active', 1)->orderBy('name')->findAll();
    }
    public function activeLeadSources(): array
    {
        return (new LeadSourceModel())->where('is_active', 1)->orderBy('sort_order')->orderBy('name')->findAll();
    }
    public function activeLeadStatuses(): array
    {
        return (new LeadStatusModel())->where('is_active', 1)->orderBy('sort_order')->orderBy('name')->findAll();
    }

    /** @return array{total_rows: int, imported: int, failed: int, errors: list<array{row: int, message: string}>, warnings: list<array{row: int, message: string}>} */
    public function importCsv(?UploadedFile $file): array
    {
        if (!service('authorization')->can('lead.create')) {
            throw new RuntimeException('Forbidden');
        }
        if (!$file instanceof UploadedFile || !$file->isValid() || $file->hasMoved()) {
            throw new RuntimeException('The uploaded CSV file is invalid.');
        }
        if ($file->getSize() > 5 * 1024 * 1024) {
            throw new RuntimeException('The CSV file exceeds the 5 MB limit.');
        }
        if (strtolower((string) $file->getClientExtension()) !== 'csv'
            || !in_array(strtolower((string) $file->getMimeType()), ['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'], true)) {
            throw new RuntimeException('Only CSV files are supported.');
        }

        $handle = fopen($file->getTempName(), 'rb');
        if ($handle === false) {
            throw new RuntimeException('The uploaded CSV file could not be read.');
        }

        try {
            $headers = fgetcsv($handle);
            if (!is_array($headers)) {
                throw new RuntimeException('The CSV file is empty.');
            }
            $mapping = $this->mapImportHeaders($headers);
            if (!array_key_exists('full_name', $mapping) || !array_key_exists('branch', $mapping)) {
                throw new RuntimeException('The CSV must include Name and Branch columns.');
            }

            $result = ['total_rows' => 0, 'imported' => 0, 'failed' => 0, 'errors' => [], 'warnings' => []];
            $csvRow = 1;
            while (($values = fgetcsv($handle)) !== false) {
                $csvRow++;
                if ($this->isEmptyImportRow($values)) {
                    continue;
                }
                $result['total_rows']++;
                $rowNumber = $csvRow;
                try {
                    $lead = $this->mapImportRow($mapping, $values);
                    $created = $this->create($lead);
                    $result['imported']++;
                    if (!empty($created['duplicate_warning'])) {
                        $result['warnings'][] = ['row' => $rowNumber, 'message' => 'Possible duplicate detected.'];
                    }
                } catch (Throwable $exception) {
                    $result['failed']++;
                    $result['errors'][] = ['row' => $rowNumber, 'message' => $this->importError($exception)];
                }
            }
            if ($result['total_rows'] === 0) {
                throw new RuntimeException('The CSV file contains no data rows.');
            }
            return $result;
        } finally {
            fclose($handle);
        }
    }

    /** @param list<string> $headers @return array<string, string> */
    private function mapImportHeaders(array $headers): array
    {
        $aliases = [
            'name' => 'full_name', 'full_name' => 'full_name', 'phone' => 'phone', 'mobile' => 'phone',
            'email' => 'email', 'source' => 'source', 'status' => 'status', 'branch' => 'branch',
            'branch_name' => 'branch', 'branch_code' => 'branch', 'assigned_to' => 'assigned_to',
            'assign_to' => 'assigned_to', 'owner' => 'assigned_to', 'notes' => 'remarks', 'remarks' => 'remarks',
            'date_of_birth' => 'date_of_birth', 'dob' => 'date_of_birth',
        ];
        $mapping = [];
        foreach ($headers as $index => $header) {
            $normalized = strtolower(trim((string) $header));
            $normalized = preg_replace('/^\xEF\xBB\xBF/', '', $normalized) ?? $normalized;
            $normalized = str_replace([' ', '-'], '_', $normalized);
            if (isset($aliases[$normalized])) {
                $mapping[$aliases[$normalized]] = (string) $index;
            }
        }
        return $mapping;
    }

    /** @param list<string|null> $values */
    private function isEmptyImportRow(array $values): bool
    {
        foreach ($values as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }
        return true;
    }

    /** @param array<string, string> $mapping @param list<string|null> $values @return array<string, mixed> */
    private function mapImportRow(array $mapping, array $values): array
    {
        $value = static function (string $field) use ($mapping, $values): string {
            return trim((string) ($values[(int) ($mapping[$field] ?? -1)] ?? ''));
        };
        $branch = $this->resolveImportBranch($value('branch'));
        $owner = $value('assigned_to');
        return [
            'branch_id' => $branch,
            'owner_user_id' => $owner === '' ? null : $this->resolveImportOwner($owner),
            'full_name' => $value('full_name'),
            'phone' => $value('phone') ?: null,
            'email' => $value('email') ?: null,
            'source' => $value('source'),
            'status' => $value('status'),
            'date_of_birth' => $value('date_of_birth') ?: null,
            'remarks' => $value('remarks') ?: null,
        ];
    }

    private function resolveImportBranch(string $value): int
    {
        $authorized = service('branchContext')->authorizedBranchIds(true);
        if ($value === '' || $authorized === []) {
            throw new RuntimeException('Branch is required and must be authorized.');
        }
        $rows = Database::connect('default')->table('branches')
            ->whereIn('id', $authorized)->where('is_active', 1)->groupStart()
            ->where('name', $value)->orWhere('code', $value)->groupEnd()->get()->getResultArray();
        if (count($rows) !== 1) {
            throw new RuntimeException('Branch is invalid or unauthorized.');
        }
        return (int) $rows[0]['id'];
    }

    private function resolveImportOwner(string $value): int
    {
        $users = $this->assignableUsers();
        $matches = array_values(array_filter($users, static fn (array $user): bool =>
            strcasecmp((string) $user['email'], $value) === 0 || strcasecmp((string) $user['display_name'], $value) === 0));
        if (count($matches) !== 1) {
            throw new RuntimeException('Assigned user is invalid.');
        }
        return (int) $matches[0]['id'];
    }

    private function importError(Throwable $exception): string
    {
        $allowed = [
            'Invalid lead.', 'Invalid owner.', 'Branch is required and must be authorized.',
            'Branch is invalid or unauthorized.', 'Assigned user is invalid.',
        ];
        return in_array($exception->getMessage(), $allowed, true) ? $exception->getMessage() : 'Row failed validation.';
    }

    public function assignableUsers(): array
    {
        $query = Database::connect('default')->table('users u')
            ->select('u.id, u.display_name, u.email')
            ->where('u.is_enabled', 1)
            ->orderBy('u.display_name');

        return $query->get()->getResultArray();
    }
    public function paginatedLeads(array $filters, int $page = 1, int $perPage = 20): array
    {
        $authorizedBranches = service('branchContext')->authorizedBranchIds(true);
        $page = max(1, $page);
        $perPage = min(100, max(1, $perPage));
        $filters = array_intersect_key($filters, array_flip(['q', 'source', 'status', 'branch_id', 'created_from', 'created_to']));
        if ($authorizedBranches === [] && service('authorization')->can('rbac.manage')) {
            return ['leads' => [], 'total' => 0, 'page' => 1, 'pages' => 1, 'filters' => $filters];
        }
        $sources = $this->activeLeadSources();
        $statuses = $this->activeLeadStatuses();
        $sourceNames = array_column($sources, 'name');
        $statusNames = array_column($statuses, 'name');
        $branchIds = $authorizedBranches;
        $query = (new LeadModel())
            ->select('leads.*, branches.name AS branch_name, owners.display_name AS owner_name')
            ->select('(SELECT COUNT(*) FROM lead_follow_ups WHERE lead_follow_ups.lead_id = leads.id) AS follow_up_count', false)
            ->select('(SELECT COUNT(*) FROM trials WHERE trials.lead_id = leads.id AND trials.branch_id = leads.branch_id) AS trial_count', false)
            ->select('(SELECT COUNT(*) FROM admissions WHERE admissions.lead_id = leads.id AND admissions.branch_id = leads.branch_id) AS admission_count', false)
            ->join('branches', 'branches.id = leads.branch_id')
            ->join('users owners', 'owners.id = leads.owner_user_id', 'left');
        if (service('authorization')->can('rbac.manage')) {
            $query->whereIn('leads.branch_id', $authorizedBranches);
        }
        $this->applyLeadVisibility($query);
        $q = trim((string) ($filters['q'] ?? ''));
        if (strlen($q) > 100) {
            $q = substr($q, 0, 100);
        }
        if ($q !== '') {
            $query->groupStart()->like('leads.full_name', $q)->orLike('leads.email', $q)->orLike('leads.phone', $q)->groupEnd();
        }
        if (($filters['source'] ?? '') !== '') {
            if (!in_array($filters['source'], $sourceNames, true)) {
                return ['leads' => [], 'total' => 0, 'page' => 1, 'pages' => 1, 'filters' => $filters];
            }
            $query->where('leads.source', $filters['source']);
        }
        if (($filters['status'] ?? '') !== '') {
            if (!in_array($filters['status'], $statusNames, true)) {
                return ['leads' => [], 'total' => 0, 'page' => 1, 'pages' => 1, 'filters' => $filters];
            }
            $query->where('leads.status', $filters['status']);
        }
        if (($filters['branch_id'] ?? '') !== '') {
            $branch = filter_var($filters['branch_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($branch === false || !in_array($branch, $branchIds, true)) {
                return ['leads' => [], 'total' => 0, 'page' => 1, 'pages' => 1, 'filters' => $filters];
            }
            $query->where('leads.branch_id', $branch);
        }
        foreach (['created_from', 'created_to'] as $dateFilter) {
            if (($filters[$dateFilter] ?? '') !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $filters[$dateFilter])) {
                return ['leads' => [], 'total' => 0, 'page' => 1, 'pages' => 1, 'filters' => $filters];
            }
        }
        if (($filters['created_from'] ?? '') !== '') {
            $query->where('leads.created_at >=', $filters['created_from'] . ' 00:00:00');
        }
        if (($filters['created_to'] ?? '') !== '') {
            $query->where('leads.created_at <', date('Y-m-d', strtotime($filters['created_to'] . ' +1 day')) . ' 00:00:00');
        }
        if (($filters['created_from'] ?? '') !== '' && ($filters['created_to'] ?? '') !== '' && $filters['created_from'] > $filters['created_to']) {
            return ['leads' => [], 'total' => 0, 'page' => 1, 'pages' => 1, 'filters' => $filters];
        }
        $total = $query->countAllResults(false);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);
        $rows = $query->orderBy('leads.id', 'DESC')->limit($perPage, ($page - 1) * $perPage)->get()->getResultArray();
        foreach ($rows as &$row) {
            $row['can_book_trial'] = service('trial')->isLeadBookable($row);
        }
        unset($row);
        return ['leads' => $rows, 'total' => $total, 'page' => $page, 'pages' => $pages, 'filters' => $filters];
    }
    public function create(array $data): array
    {
        $branch = $this->positiveInt($data['branch_id'] ?? null);
        $actor = $this->authorize($branch, 'lead.create');
        $input = $this->validateLeadInput($data);
        $owner = $this->resolveOwner($input['owner_user_id'] ?? null, $actor, $branch);
        $now = date('Y-m-d H:i:s');
        $duplicate = $this->hasDuplicate($branch, $input);
        $db = Database::connect('default');
        $db->transStart();
        $leads = new LeadModel();
        $leads->insert(['branch_id' => $branch, 'created_by_user_id' => $actor, 'owner_user_id' => $owner, 'source' => $input['source'], 'full_name' => $input['full_name'],
            'email' => $input['email'], 'phone' => $input['phone'], 'date_of_birth' => $input['date_of_birth'], 'status' => $input['status'],
                'remarks' => $input['remarks'],
            'lost_reason' => null, 'closed_at' => null, 'created_at' => $now, 'updated_at' => $now]);
        $id = (int) $leads->getInsertID();
        (new LeadStatusHistoryModel())->insert(['lead_id' => $id, 'actor_user_id' => $actor, 'previous_status' => null,
            'new_status' => $input['status'], 'reason' => null, 'created_at' => $now]);
        service('auditPersistence')->record($actor, 'lead_created', 'lead', (string) $id, $branch, null, ['owner_user_id' => $owner,
            'source' => $input['source'], 'status' => $input['status'], 'duplicate_warning' => $duplicate]);
        $db->transComplete();
        $this->assertTransactionSucceeded($db, 'Unable to create lead.');
        return ['id' => $id, 'duplicate_warning' => $duplicate];
    }
    public function find(int $id, int $branch): ?array
    {
        $lead = (new LeadModel())->where(['id' => $id, 'branch_id' => $branch])->first();
        if (!is_array($lead) || !$this->canViewLead($lead)) {
            throw new RuntimeException('Not found.');
        }
        return $lead;
    }
    public function leadDetails(int $id): array
    {
        $lead = (new LeadModel())->find($id);
        if (!is_array($lead) || !$this->canViewLead($lead)) {
            throw new RuntimeException('Not found.');
        }
        $lead['history'] = (new LeadStatusHistoryModel())->where('lead_id', $id)->orderBy('id', 'DESC')->findAll();
        $db = Database::connect('default');
        $lead['branch_name'] = (string) ($db->table('branches')->where('id', (int) $lead['branch_id'])->get()->getRow('name') ?? '');
        $owner = $db->table('users')
            ->select('display_name, email')
            ->where('id', (int) ($lead['owner_user_id'] ?? 0))
            ->get()->getRowArray() ?: [];
        $lead['owner_name'] = (string) ($owner['display_name'] ?? '');
        $lead['owner_email'] = (string) ($owner['email'] ?? '');
        $lead['trials'] = $db->table('trials')->where(['lead_id' => $id, 'branch_id' => (int) $lead['branch_id']])->orderBy('trial_date', 'DESC')
            ->orderBy('trial_time', 'DESC')->get()->getResultArray();
        $lead['admissions'] = $db->table('admissions')->where(['lead_id' => $id, 'branch_id' => (int) $lead['branch_id']])->orderBy('id', 'DESC')
            ->get()->getResultArray();
        $lead['follow_ups'] = (new LeadFollowUpModel())
            ->select('lead_follow_ups.*, actors.display_name AS actor_name, editors.display_name AS updated_by_name')->join('users actors',
                'actors.id = lead_follow_ups.actor_user_id', 'left')->join('users editors', 'editors.id = lead_follow_ups.updated_by', 'left')
                    ->where('lead_follow_ups.lead_id', $id)->orderBy('lead_follow_ups.id', 'DESC')->findAll();
        $lead['can_manage_follow_ups'] = service('authorization')->can('lead.update', (string) $lead['branch_id']);
        return $lead;
    }
    /** @return array{followUps: list<array<string, mixed>>, total: int, page: int, pages: int, filters: array<string, mixed>} */
    public function paginatedFollowUps(array $filters, int $page = 1, int $perPage = 20): array
    {
        $authorizedBranches = service('branchContext')->authorizedBranchIds(true);
        $page = max(1, $page);
        $perPage = min(100, max(1, $perPage));
        $filters = array_intersect_key($filters, array_flip(['status', 'follow_up_type', 'outcome', 'scheduled_from', 'scheduled_to', 'lead', 'branch_id']));
        if ($authorizedBranches === []) {
            return ['followUps' => [], 'total' => 0, 'page' => 1, 'pages' => 1, 'filters' => $filters];
        }
        $query = (new LeadFollowUpModel())
            ->select('lead_follow_ups.*, leads.full_name AS lead_name, leads.branch_id, branches.name AS branch_name, actors.display_name AS actor_name, editors.display_name AS updated_by_name')
            ->join('leads', 'leads.id = lead_follow_ups.lead_id')
            ->join('branches', 'branches.id = leads.branch_id')
            ->join('users actors', 'actors.id = lead_follow_ups.actor_user_id', 'left')
            ->join('users editors', 'editors.id = lead_follow_ups.updated_by', 'left')
            ->whereIn('leads.branch_id', $authorizedBranches);
        $this->applyLeadVisibility($query);
        foreach (['status' => self::FOLLOW_UP_STATUSES, 'follow_up_type' => self::FOLLOW_UP_TYPES,
            'outcome' => self::FOLLOW_UP_OUTCOMES] as $field => $allowed) {
            if (($filters[$field] ?? '') !== '') {
                $value = strtolower(trim((string) $filters[$field]));
                if (!in_array($value, $allowed, true)) {
                    return ['followUps' => [], 'total' => 0, 'page' => 1, 'pages' => 1, 'filters' => $filters];
                }
                $query->where('lead_follow_ups.' . $field, $value);
            }
        }
        foreach (['scheduled_from', 'scheduled_to'] as $dateFilter) {
            if (($filters[$dateFilter] ?? '') !== '') {
                $date = (string) $filters[$dateFilter];
                $parsed = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? DateTimeImmutable::createFromFormat('!Y-m-d', $date) : false;
                if (!$parsed || $parsed->format('Y-m-d') !== $date) {
                    return ['followUps' => [], 'total' => 0, 'page' => 1, 'pages' => 1, 'filters' => $filters];
                }
            }
        }
        if (($filters['scheduled_from'] ?? '') !== '' && ($filters['scheduled_to'] ?? '') !== '' && $filters['scheduled_from'] > $filters['scheduled_to']) {
            return ['followUps' => [], 'total' => 0, 'page' => 1, 'pages' => 1, 'filters' => $filters];
        }
        if (($filters['scheduled_from'] ?? '') !== '') {
            $query->where('lead_follow_ups.follow_up_at >=', $filters['scheduled_from'] . ' 00:00:00');
        }
        if (($filters['scheduled_to'] ?? '') !== '') {
            $query->where('lead_follow_ups.follow_up_at <', date('Y-m-d', strtotime($filters['scheduled_to'] . ' +1 day')) . ' 00:00:00');
        }
        if (($filters['lead'] ?? '') !== '') {
            $lead = trim((string) $filters['lead']);
            if (strlen($lead) > 100) {
                $lead = substr($lead, 0, 100);
            }
            $query->groupStart()->like('leads.full_name', $lead)->orLike('leads.email', $lead)->orLike('leads.phone', $lead)->groupEnd();
        }
        if (($filters['branch_id'] ?? '') !== '') {
            $branch = filter_var($filters['branch_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($branch === false || !in_array($branch, $authorizedBranches, true)) {
                return ['followUps' => [], 'total' => 0, 'page' => 1, 'pages' => 1, 'filters' => $filters];
            }
            $query->where('leads.branch_id', $branch);
        }
        $total = $query->countAllResults(false);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);
        $rows = $query->orderBy('lead_follow_ups.follow_up_at', 'ASC')->orderBy('lead_follow_ups.id', 'DESC')->limit($perPage, ($page - 1) * $perPage)
            ->get()->getResultArray();
        return ['followUps' => $rows, 'total' => $total, 'page' => $page, 'pages' => $pages, 'filters' => $filters];
    }

    /** @return array{all: int, pending: int, scheduled: int, completed: int, cancelled: int, overdue: int} */
    public function followUpStatusCounts(): array
    {
        $counts = [
            'all' => 0,
            'pending' => 0,
            'scheduled' => 0,
            'completed' => 0,
            'cancelled' => 0,
            'overdue' => 0,
        ];
        $authorizedBranches = service('branchContext')->authorizedBranchIds(true);

        if ($authorizedBranches === []) {
            return $counts;
        }

        $query = (new LeadFollowUpModel())
            ->select('lead_follow_ups.status, lead_follow_ups.follow_up_at')
            ->join('leads', 'leads.id = lead_follow_ups.lead_id')
            ->whereIn('leads.branch_id', $authorizedBranches);
        $this->applyLeadVisibility($query);
        $rows = $query->findAll();

        foreach ($rows as $row) {
            $counts['all']++;
            $presentation = mcc_follow_up_status_presentation(
                $row['status'] ?? null,
                $row['follow_up_at'] ?? null
            );
            $key = $presentation['overdue'] ? 'overdue' : strtolower($presentation['label']);

            if (array_key_exists($key, $counts)) {
                $counts[$key]++;
            }
        }

        return $counts;
    }
    /** @return array{types: list<string>, statuses: list<string>, outcomes: list<string>} */
    public function followUpFilterOptions(): array
    {
        return ['types' => self::FOLLOW_UP_TYPES, 'statuses' => self::FOLLOW_UP_STATUSES, 'outcomes' => self::FOLLOW_UP_OUTCOMES];
    }
    public function updateLead(int $id, array $data): void
    {
        $lead = $this->leadDetails($id);
        $actor = $this->authorize((int) $lead['branch_id'], 'lead.update');
        if (array_diff(array_keys($data), ['full_name', 'email', 'phone', 'date_of_birth', 'source', 'remarks', 'owner_user_id']) !== []) {
            throw new RuntimeException('Invalid lead.');
        }
        $input = $this->validateLeadInput(array_merge($lead, $data), true);
        $model = new LeadModel();
        $before = ['full_name' => $lead['full_name'], 'email' => $lead['email'], 'phone' => $lead['phone'], 'date_of_birth' => $lead['date_of_birth'],
            'source' => $lead['source'], 'remarks' => $lead['remarks'] ?? null];
        $after = ['full_name' => $input['full_name'], 'email' => $input['email'], 'phone' => $input['phone'],
            'date_of_birth' => $input['date_of_birth'], 'source' => $input['source'], 'remarks' => $input['remarks']];
        $model->update($id, $after + ['updated_at' => date('Y-m-d H:i:s')]);
        service('auditPersistence')->record($actor, 'lead_updated', 'lead', (string) $id, (int) $lead['branch_id'], $before, $after);

        if (array_key_exists('owner_user_id', $data)) {
            $owner = $this->nullableOwner($data['owner_user_id'], (int) $lead['branch_id']);
            $this->assign((int) $id, (int) $lead['branch_id'], $owner);
        }
    }
    public function assign(int $id, int $branch, ?int $owner): void
    {
        $actor = $this->authorize($branch, 'lead.update');
        if ($owner !== null) {
            $this->assertOwnerExists($owner);
        }
        $db = Database::connect('default');
        $db->transStart();
        $lead = $this->lockLead($id, $branch);
        if ($lead === null) {
            $db->transRollback();
            throw new RuntimeException('Not found.');
        }
        if (($lead['owner_user_id'] === null ? null : (int) $lead['owner_user_id']) === $owner) {
            $db->transComplete();
            return;
        }
        $now = date('Y-m-d H:i:s');
        $update = $db->table('leads')->where(['id' => $id, 'branch_id' => $branch]);
        if ($lead['owner_user_id'] === null) {
            $update->where('owner_user_id IS NULL', null, false);
        } else {
            $update->where('owner_user_id', $lead['owner_user_id']);
        }
        $update->update(['owner_user_id' => $owner, 'updated_at' => $now]);
        service('auditPersistence')->record($actor, 'lead_reassigned', 'lead', (string) $id, $branch,
            ['owner_user_id' => $lead['owner_user_id'] === null ? null : (int) $lead['owner_user_id']], ['owner_user_id' => $owner]);
        $db->transComplete();
        $this->assertTransactionSucceeded($db, 'Unable to assign lead.');
    }
    public function status(int $id, int $branch, string $newStatus, ?string $reason = null): void
    {
        $actor = $this->authorize($branch, 'lead.update');
        $newStatus = strtolower(trim($newStatus));
        $configuredStatus = (new LeadStatusModel())->where(['name' => $newStatus, 'is_active' => 1])->first();
        if (!is_array($configuredStatus)) {
            throw new RuntimeException('Invalid status.');
        }
        $reason = $reason === null ? null : trim($reason);
        if ($newStatus === self::STATUS_LOST && ($reason === null || $reason === '')) {
            throw new RuntimeException('Lost reason required.');
        }
        $db = Database::connect('default');
        $db->transStart();
        $lead = $this->lockLead($id, $branch);
        if ($lead === null) {
            $db->transRollback();
            throw new RuntimeException('Not found.');
        }
        $oldStatus = (string) $lead['status'];
        if ($oldStatus === $newStatus || $oldStatus === self::STATUS_LOST && $newStatus !== self::STATUS_LEAD) {
            $db->transRollback();
            throw new RuntimeException('Status transition rejected.');
        }
        $now = date('Y-m-d H:i:s');
        $db->table('leads')->where(['id' => $id, 'branch_id' => $branch, 'status' => $oldStatus])
            ->update(['status' => $newStatus, 'lost_reason' => $newStatus === self::STATUS_LOST ? $reason : null,
                'closed_at' => $newStatus === self::STATUS_LOST ? $now : null, 'updated_at' => $now]);
        $db->table('lead_status_history')->insert(['lead_id' => $id, 'actor_user_id' => $actor, 'previous_status' => $oldStatus,
            'new_status' => $newStatus, 'reason' => $reason, 'created_at' => $now]);
        service('auditPersistence')->record($actor, $newStatus === self::STATUS_LEAD ? 'lead_reopened' : 'lead_status_changed', 'lead', (string) $id,
            $branch, ['status' => $oldStatus], ['status' => $newStatus], $reason);
        $db->transComplete();
        $this->assertTransactionSucceeded($db, 'Unable to change lead status.');
    }
    public function followUp(int $id, int $branch, string $note, ?string $at = null): void
    {
        // Compatibility boundary for the original CRM endpoint. New callers use
        // createFollowUp(), while legacy note-only calls become the approved
        // controlled "other" type and pending state.
        $this->createFollowUp($id, ['follow_up_type' => 'other', 'note' => $note, 'follow_up_at' => $at]);
    }
    public function createFollowUp(int $id, array $input): int
    {
        $db = Database::connect('default');
        $db->transStart();
        try {
            $lead = $this->lockLeadForFollowUp($db, $id);
            if ($lead === null) {
                throw new RuntimeException('Not found.');
            }
            $branch = (int) $lead['branch_id'];
            $actor = $this->authorize($branch, 'lead.update');
            $data = $this->validateFollowUpInput($input);
            $now = gmdate('Y-m-d H:i:s');
            $db->table('lead_follow_ups')->insert(['lead_id' => $id, 'actor_user_id' => $actor, 'follow_up_type' => $data['follow_up_type'],
                'note' => $data['note'], 'follow_up_at' => $data['follow_up_at'], 'status' => 'pending', 'outcome' => null,
                    'cancellation_reason' => null, 'completed_at' => null, 'cancelled_at' => null, 'updated_by' => null, 'updated_at' => null,
                        'created_at' => $now]);
            $followUpId = (int) $db->insertID();
            service('auditPersistence')->record($actor, 'lead_follow_up_recorded', 'lead', (string) $id, $branch, null, ['follow_up_id' => $followUpId,
                'follow_up_type' => $data['follow_up_type'], 'status' => 'pending', 'follow_up_at' => $data['follow_up_at']]);
            $db->transComplete();
            $this->assertTransactionSucceeded($db, 'Unable to record follow-up.');
            return $followUpId;
        } catch (Throwable $exception) {
            $db->transRollback();
            throw $exception;
        }
    }
    public function updateFollowUp(int $id, array $input): void
    {
        $db = Database::connect('default');
        $db->transStart();
        try {
            $followUp = $this->lockFollowUp($db, $id);
            if ($followUp === null) {
                throw new RuntimeException('Not found.');
            }
            $actor = $this->authorize((int) $followUp['branch_id'], 'lead.update');
            if (($followUp['status'] ?? 'pending') !== 'pending') {
                throw new RuntimeException('Completed or cancelled follow-ups are immutable.');
            }
            $data = $this->validateFollowUpInput($input);
            $now = gmdate('Y-m-d H:i:s');
            $db->table('lead_follow_ups')->where('id', $id)->where('status', 'pending')
                ->update(['follow_up_type' => $data['follow_up_type'], 'note' => $data['note'], 'follow_up_at' => $data['follow_up_at'],
                    'updated_by' => $actor, 'updated_at' => $now]);
            service('auditPersistence')->record($actor, 'lead_follow_up_updated', 'lead_follow_up', (string) $id, (int) $followUp['branch_id'],
                ['follow_up_type' => $followUp['follow_up_type'], 'note' => $followUp['note'], 'follow_up_at' => $followUp['follow_up_at']],
                    ['follow_up_type' => $data['follow_up_type'], 'note' => $data['note'], 'follow_up_at' => $data['follow_up_at']]);
            $db->transComplete();
            $this->assertTransactionSucceeded($db, 'Unable to update follow-up.');
        } catch (Throwable $exception) {
            $db->transRollback();
            throw $exception;
        }
    }
    public function correctFollowUp(int $id, array $input): void
    {
        $reason = trim((string) ($input['correction_reason'] ?? ''));
        if ($reason === '' || strlen($reason) > 255) {
            throw new RuntimeException('Correction reason required.');
        }
        $db = Database::connect('default');
        $db->transStart();
        try {
            $followUp = $this->lockFollowUp($db, $id);
            if ($followUp === null) {
                throw new RuntimeException('Not found.');
            }
            $actor = $this->authorize((int) $followUp['branch_id'], 'lead.update');
            if (!in_array($followUp['status'], ['completed', 'cancelled'], true)) {
                throw new RuntimeException('Only completed or cancelled follow-ups can be corrected.');
            }
            $data = $this->validateFollowUpInput($input);
            $now = gmdate('Y-m-d H:i:s');
            $db->table('lead_follow_ups')->where('id', $id)->update(['follow_up_type' => $data['follow_up_type'], 'note' => $data['note'],
                'follow_up_at' => $data['follow_up_at'], 'updated_by' => $actor, 'updated_at' => $now]);
            service('auditPersistence')->record($actor, 'lead_follow_up_corrected', 'lead_follow_up', (string) $id, (int) $followUp['branch_id'],
                ['follow_up_type' => $followUp['follow_up_type'], 'note' => $followUp['note'], 'follow_up_at' => $followUp['follow_up_at']],
                    ['follow_up_type' => $data['follow_up_type'], 'note' => $data['note'], 'follow_up_at' => $data['follow_up_at']], $reason);
            $db->transComplete();
            $this->assertTransactionSucceeded($db, 'Unable to correct follow-up.');
        } catch (Throwable $exception) {
            $db->transRollback();
            throw $exception;
        }
    }
    public function completeFollowUp(int $id, string $outcome): void
    {
        $outcome = strtolower(trim($outcome));
        if (!in_array($outcome, self::FOLLOW_UP_OUTCOMES, true)) {
            throw new RuntimeException('Invalid follow-up outcome.');
        }
        $this->transitionFollowUp($id, 'completed', ['outcome' => $outcome, 'completed_at' => gmdate('Y-m-d H:i:s'), 'cancelled_at' => null,
            'cancellation_reason' => null], 'lead_follow_up_completed');
    }
    public function cancelFollowUp(int $id, string $reason): void
    {
        $reason = trim($reason);
        if ($reason === '' || strlen($reason) > 255) {
            throw new RuntimeException('Cancellation reason required.');
        }
        $this->transitionFollowUp($id, 'cancelled', ['outcome' => null, 'completed_at' => null, 'cancelled_at' => gmdate('Y-m-d H:i:s'),
            'cancellation_reason' => $reason], 'lead_follow_up_cancelled', $reason);
    }
    private function transitionFollowUp(int $id, string $status, array $changes, string $auditAction, ?string $reason = null): void
    {
        $db = Database::connect('default');
        $db->transStart();
        try {
            $followUp = $this->lockFollowUp($db, $id);
            if ($followUp === null) {
                throw new RuntimeException('Not found.');
            }
            $actor = $this->authorize((int) $followUp['branch_id'], 'lead.update');
            if (($followUp['status'] ?? 'pending') !== 'pending') {
                throw new RuntimeException('Follow-up is no longer pending.');
            }
            $changes += ['status' => $status, 'updated_by' => $actor, 'updated_at' => gmdate('Y-m-d H:i:s')];
            $db->table('lead_follow_ups')->where(['id' => $id, 'status' => 'pending'])->update($changes);
            service('auditPersistence')->record($actor, $auditAction, 'lead_follow_up', (string) $id, (int) $followUp['branch_id'],
                ['status' => 'pending', 'outcome' => $followUp['outcome']], ['status' => $status, 'outcome' => $changes['outcome']], $reason);
            $db->transComplete();
            $this->assertTransactionSucceeded($db, 'Unable to update follow-up.');
        } catch (Throwable $exception) {
            $db->transRollback();
            throw $exception;
        }
    }
    private function validateFollowUpInput(array $input): array
    {
        $type = strtolower(trim((string) ($input['follow_up_type'] ?? '')));
        $note = trim((string) ($input['note'] ?? ''));
        $at = $input['follow_up_at'] ?? null;
        if (!in_array($type, self::FOLLOW_UP_TYPES, true) || $note === '' || strlen($note) > 65535) {
            throw new RuntimeException('Invalid follow-up.');
        }
        if ($at !== null && $at !== '') {
            $at = trim((string) $at);
            $parsed = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $at) ?: DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $at);
            if (!$parsed || $parsed->format(strlen($at) === 16 && str_contains($at, 'T') ? 'Y-m-d\TH:i' : 'Y-m-d H:i:s') !== $at) {
                throw new RuntimeException('Invalid follow-up.');
            }
            $at = $parsed->format('Y-m-d H:i:s');
        } else {
            $at = null;
        }
        return ['follow_up_type' => $type, 'note' => $note, 'follow_up_at' => $at];
    }
    private function lockLeadForFollowUp(object $db, int $id): ?array
    {
        return $db->query('SELECT id, branch_id FROM leads WHERE id = ? LIMIT 1 FOR UPDATE', [$id])->getRowArray() ?: null;
    }
    private function lockFollowUp(object $db, int $id): ?array
    {
        return $db->query('SELECT f.*, l.branch_id FROM lead_follow_ups f INNER JOIN leads l ON l.id = f.lead_id WHERE f.id = ? LIMIT 1 FOR UPDATE',
            [$id])->getRowArray() ?: null;
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
    private function validateLeadInput(array $data, bool $editing = false): array
    {
        $source = trim((string) ($data['source'] ?? ''));
        $name = trim((string) ($data['full_name'] ?? ''));
        $status = strtolower(trim((string) ($data['status'] ?? self::STATUS_LEAD)));
        $sourceValid = (new LeadSourceModel())->where(['name' => $source, 'is_active' => 1])->first() !== null;
        if ($editing && !$sourceValid && $source === (string) ($data['source'] ?? '')) {
            $sourceValid = (new LeadSourceModel())->where('name', $source)->first() !== null;
        }
        $statusValid = (new LeadStatusModel())->where(['name' => $status, 'is_active' => 1])->first() !== null;
        if ($editing && !$statusValid && $status === (string) ($data['status'] ?? '')) {
            $statusValid = (new LeadStatusModel())->where('name', $status)->first() !== null;
        }
        if ($source === '' || strlen($source) > 100 || !$sourceValid || $status === '' || !$statusValid || $name === '' || strlen($name) > 150) {
            throw new RuntimeException('Invalid lead.');
        }
        $email = isset($data['email']) && $data['email'] !== '' ? strtolower(trim((string) $data['email'])) : null;
        if ($email !== null && (strlen($email) > 190 || filter_var($email, FILTER_VALIDATE_EMAIL) === false)) {
            throw new RuntimeException('Invalid lead.');
        }
        $phone = isset($data['phone']) && $data['phone'] !== '' ? trim((string) $data['phone']) : null;
        if ($phone !== null && strlen($phone) > 40) {
            throw new RuntimeException('Invalid lead.');
        }
        $dob = isset($data['date_of_birth']) && $data['date_of_birth'] !== '' ? trim((string) $data['date_of_birth']) : null;
        if ($dob !== null) {
            if (preg_match('/^\d{2}-\d{2}-\d{4}$/', $dob)) {
                $parsedDob = DateTimeImmutable::createFromFormat('!d-m-Y', $dob);
                if ($parsedDob === false || $parsedDob->format('d-m-Y') !== $dob) {
                    throw new RuntimeException('Invalid lead.');
                }
                $dob = $parsedDob->format('Y-m-d');
            } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob)) {
                throw new RuntimeException('Invalid lead.');
            }
        }
        $remarks = isset($data['remarks']) ? trim((string) $data['remarks']) : null;
        if ($remarks !== null && strlen($remarks) > 65535) {
            throw new RuntimeException('Invalid lead.');
        }
        return ['source' => $source, 'status' => $status, 'full_name' => $name, 'email' => $email, 'phone' => $phone, 'date_of_birth' => $dob,
            'remarks' => $remarks === '' ? null : $remarks,
            'owner_user_id' => array_key_exists('owner_user_id', $data) && $data['owner_user_id'] !== ''
                ? $this->positiveInt($data['owner_user_id']) : null];
    }
    private function resolveOwner(?int $owner, int $actor, int $branch): int
    {
        if ($owner === null) {
            $owner = $actor;
            $this->assertOwnerExists($owner);
        } else {
            $this->assertOwnerExists($owner);
        }
        return $owner;
    }
    private function assertOwnerExists(int $owner): void
    {
        if ($owner < 1 || Database::connect('default')->table('users')->where(['id' => $owner, 'is_enabled' => 1])->countAllResults() !== 1) {
            throw new RuntimeException('Invalid owner.');
        }
    }

    private function nullableOwner(mixed $value, int $branch): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $owner = $this->positiveInt($value);
        $this->assertOwnerExists($owner);
        return $owner;
    }

    private function canViewLead(array $lead): bool
    {
        $branch = (string) ($lead['branch_id'] ?? '');
        if (!service('authorization')->can('lead.view')) {
            return false;
        }

        $actor = (int) (session('user.id') ?? 0);
        if ($actor > 0 && ($actor === (int) ($lead['owner_user_id'] ?? 0)
            || $actor === (int) ($lead['created_by_user_id'] ?? 0))) {
            return true;
        }

        return service('authorization')->can('lead.view', $branch)
            && service('authorization')->can('rbac.manage');
    }

    private function applyLeadVisibility(object $query): void
    {
        if (service('authorization')->can('rbac.manage')) {
            return;
        }

        $actor = (int) (session('user.id') ?? 0);
        $query->groupStart()
            ->where('leads.owner_user_id', $actor)
            ->orWhere('leads.created_by_user_id', $actor)
            ->groupEnd();
    }
    private function hasDuplicate(int $branch, array $input): bool
    {
        $query = Database::connect('default')->table('leads')->where('branch_id', $branch)->groupStart();
        $hasCondition = false;
        foreach (['email', 'phone', 'date_of_birth'] as $field) {
            if ($input[$field] !== null) {
                $hasCondition ? $query->orWhere($field, $input[$field]) : $query->where($field, $input[$field]);
                $hasCondition = true;
            }
        }
        if (!$hasCondition) {
            return false;
        }
        return $query->groupEnd()->countAllResults() > 0;
    }
    private function lockLead(int $id, int $branch): ?array
    {
        return Database::connect('default')->query('SELECT * FROM leads WHERE id = ? AND branch_id = ? LIMIT 1 FOR UPDATE', [$id, $branch])
            ->getRowArray() ?: null;
    }
    private function positiveInt(mixed $value): int
    {
        if (!is_int($value) && !ctype_digit((string) $value)) {
            throw new RuntimeException('Invalid lead.');
        }
        $value = (int) $value;
        if ($value < 1) {
            throw new RuntimeException('Invalid lead.');
        }
        return $value;
    }
    private function assertTransactionSucceeded(object $db, string $message): void
    {
        if (!$db->transStatus()) {
            throw new RuntimeException($message);
        }
    }
}
