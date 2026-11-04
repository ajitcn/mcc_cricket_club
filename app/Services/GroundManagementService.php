<?php

declare(strict_types=1);

namespace App\Services;

use App\Modules\Admin\Models\BranchModel;
use Config\Database;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;
use Throwable;

final class GroundManagementService
{
    private const GROUNDS_PER_PAGE = 20;

    /** @var callable|null */
    private $auditRecorder;

    public function __construct(?callable $auditRecorder = null)
    {
        $this->auditRecorder = $auditRecorder;
    }

    /** @return list<array<string, mixed>> */
    public function availableBranchesForCreation(): array
    {
        $branchIds = array_values(array_unique(array_filter(
            service('branchContext')->authorizedBranchIds(true),
            static fn (int $branchId): bool => $branchId > 0,
        )));
        $authorizedBranchIds = [];

        foreach ($branchIds as $branchId) {
            if (service('authorization')->can('ground.create', (string) $branchId)) {
                $authorizedBranchIds[] = $branchId;
            }
        }

        if ($authorizedBranchIds === []) {
            return [];
        }

        return (new BranchModel())
            ->whereIn('id', $authorizedBranchIds)
            ->orderBy('name')
            ->findAll();
    }

    public function list(array $filters = []): array
    {
        $branches = $this->branches();
        if ($branches === []) {
            return [
                'grounds' => [],
                'total' => 0,
                'page' => 1,
                'pages' => 1,
                'filters' => $filters,
            ];
        }

        $builder = Database::connect('default')->table('grounds g')
            ->select('g.*, b.name AS branch_name')
            ->join('branches b', 'b.id = g.branch_id')
            ->whereIn('g.branch_id', $branches);
        $query = trim((string) ($filters['q'] ?? ''));
        if ($query !== '') {
            $builder->groupStart()->like('g.name', $query)->orLike('g.ground_type', $query)->orLike('b.name', $query)->groupEnd();
        }
        if (isset($filters['status']) && in_array($filters['status'], ['active', 'inactive'], true)) {
            $builder->where('g.is_active', $filters['status'] === 'active' ? 1 : 0);
        }
        $total = (clone $builder)->countAllResults();
        $page = max(1, (int) ($filters['page'] ?? 1));
        $pages = max(1, (int) ceil($total / self::GROUNDS_PER_PAGE));
        $grounds = $builder
            ->orderBy('g.name', 'ASC')
            ->orderBy('g.id', 'DESC')
            ->get(self::GROUNDS_PER_PAGE, ($page - 1) * self::GROUNDS_PER_PAGE)
            ->getResultArray();
        foreach ($grounds as &$ground) {
            $ground['can_update'] = service('authorization')->can(
                'ground.update',
                (string) $ground['branch_id']
            );
        }
        unset($ground);

        return [
            'grounds' => $grounds,
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'filters' => $filters,
        ];
    }

    public function find(int $id): array
    {
        $ground = $this->ground($id);
        $this->allow((int) $ground['branch_id'], 'ground.view');
        $ground['can_update'] = service('authorization')->can(
            'ground.update',
            (string) $ground['branch_id']
        );
        $db = Database::connect('default');
        $ground['maintenance'] = $db->table('ground_unavailability')
            ->where(['ground_id' => $id, 'branch_id' => $ground['branch_id']])
            ->orderBy('starts_at', 'DESC')
            ->get()->getResultArray();
        $ground['bookings'] = $db->table('sessions')
            ->where(['ground_id' => $id, 'branch_id' => $ground['branch_id']])
            ->orderBy('starts_at', 'DESC')
            ->get()->getResultArray();
        $ground['audit_history'] = $this->auditHistory($db, $id, (int) $ground['branch_id']);
        $now = gmdate('Y-m-d H:i:s');
        $inMaintenance = $db->table('ground_unavailability')
            ->where(['ground_id' => $id, 'branch_id' => $ground['branch_id']])
            ->where('starts_at <=', $now)
            ->where('ends_at >', $now)
            ->countAllResults() > 0;
        $ground['availability_status'] = (int) $ground['is_active'] !== 1 ? 'inactive' : ($inMaintenance ? 'maintenance' : 'available');
        $ground['kpis'] = [
            'booking_count' => count($ground['bookings']),
            'maintenance_period_count' => count($ground['maintenance']),
        ];
        return $ground;
    }

    public function save(array $input, ?int $id = null): int
    {
        $branch = (int) ($input['branch_id'] ?? 0);
        $this->allow($branch, $id === null ? 'ground.create' : 'ground.update');
        $name = trim((string) ($input['name'] ?? ''));
        $type = trim((string) ($input['ground_type'] ?? ''));
        if ($branch < 1 || $name === '' || strlen($name) > 150 || !in_array($type, ['indoor_net', 'outdoor_net', 'venue'], true)) {
            throw new RuntimeException('Invalid ground.');
        }
        $data = ['branch_id' => $branch, 'name' => $name, 'ground_type' => $type, 'description' => $this->text($input['description'] ?? null), 'is_active' => (int) ($input['is_active'] ?? 1), 'updated_at' => gmdate('Y-m-d H:i:s')];
        if (!in_array($data['is_active'], [0, 1], true)) {
            throw new RuntimeException('Invalid ground.');
        }
        $db = Database::connect('default');
        $db->transStart();
        try {
            if ($id !== null) {
                $ground = $this->ground($id);
                $this->allow((int) $ground['branch_id'], 'ground.update');
                if ($branch !== (int) $ground['branch_id']) {
                    throw new RuntimeException('Invalid ground.');
                }
                $db->table('grounds')->where('id', $id)->update($data);
                $this->recordAudit(
                    $this->actor(),
                    'ground_updated',
                    'ground',
                    (string) $id,
                    $branch,
                    [
                        'branch_id' => (int) $ground['branch_id'],
                        'name' => $ground['name'],
                        'ground_type' => $ground['ground_type'],
                        'description' => $ground['description'],
                        'is_active' => (int) $ground['is_active'],
                    ],
                    $data,
                    null,
                    $db
                );
            } else {
                $db->table('grounds')->insert($data + [
                    'created_by' => $this->actor(),
                    'created_at' => gmdate('Y-m-d H:i:s'),
                ]);
                $id = (int) $db->insertID();
                $this->recordAudit(
                    $this->actor(),
                    'ground_created',
                    'ground',
                    (string) $id,
                    $branch,
                    null,
                    $data,
                    null,
                    $db
                );
            }
            $db->transComplete();
            if (! $db->transStatus()) {
                throw new RuntimeException('Unable to save ground.');
            }
            return $id;
        } catch (Throwable $exception) {
            $db->transRollback();
            $db->resetTransStatus();
            throw $exception;
        }
    }

    public function addMaintenance(int $groundId, array $input): int
    {
        [$starts, $ends] = $this->window((string) ($input['starts_at'] ?? ''), (string) ($input['ends_at'] ?? ''));
        $reason = trim((string) ($input['reason'] ?? ''));
        if ($reason === '' || strlen($reason) > 255) {
            throw new RuntimeException('Invalid maintenance period.');
        }
        $db = Database::connect('default');
        $db->transStart();
        $lockName = 'mcc:scheduling:ground:' . $groundId;
        $locked = false;
        try {
            $lock = $db->query(
                'SELECT GET_LOCK(?, 5) AS acquired',
                [$lockName]
            )->getRowArray();
            if (! is_array($lock) || (int) ($lock['acquired'] ?? 0) !== 1) {
                throw new RuntimeException('Ground maintenance conflict.');
            }
            $locked = true;
            $ground = $db->query(
                'SELECT * FROM grounds WHERE id=? LIMIT 1 FOR UPDATE',
                [$groundId]
            )->getRowArray();
            if (! is_array($ground)) {
                throw new RuntimeException('Not found.');
            }
            $this->allow((int) $ground['branch_id'], 'ground.maintenance');
            $conflict = $db->table('ground_unavailability')
                ->where([
                    'ground_id' => $groundId,
                    'branch_id' => (int) $ground['branch_id'],
                ])
                ->where('starts_at <', $ends)
                ->where('ends_at >', $starts)
                ->countAllResults();
            if ($conflict > 0) {
                throw new RuntimeException('Ground maintenance conflict.');
            }
            // Existing bookings are observed but not rejected until the unresolved policy is approved.
            $db->table('sessions')
                ->where([
                    'ground_id' => $groundId,
                    'branch_id' => (int) $ground['branch_id'],
                    'status' => 'scheduled',
                ])
                ->where('starts_at <', $ends)
                ->where('ends_at >', $starts)
                ->countAllResults();
            $db->table('ground_unavailability')->insert([
                'ground_id' => $groundId,
                'branch_id' => $ground['branch_id'],
                'starts_at' => $starts,
                'ends_at' => $ends,
                'reason' => $reason,
                'created_by' => $this->actor(),
                'created_at' => gmdate('Y-m-d H:i:s'),
            ]);
            $id = (int) $db->insertID();
            $this->recordAudit(
                $this->actor(),
                'ground_maintenance_added',
                'ground_unavailability',
                (string) $id,
                (int) $ground['branch_id'],
                null,
                [
                    'ground_id' => $groundId,
                    'starts_at' => $starts,
                    'ends_at' => $ends,
                ],
                $reason,
                $db
            );
            $db->transComplete();
            if (! $db->transStatus()) {
                throw new RuntimeException('Unable to save maintenance period.');
            }
            return $id;
        } catch (Throwable $exception) {
            $db->transRollback();
            $db->resetTransStatus();
            throw $exception;
        } finally {
            if ($locked) {
                $db->query('SELECT RELEASE_LOCK(?)', [$lockName]);
            }
        }
    }

    public function ensureBookable(int $groundId, int $branch, string $starts, string $ends, ?int $exceptSession = null): void
    {
        $ground = $this->ground($groundId);
        if ((int) $ground['branch_id'] !== $branch || (int) $ground['is_active'] !== 1) {
            throw new RuntimeException('Ground is not available.');
        }
        $db = Database::connect('default');
        if ($db->table('ground_unavailability')->where([
            'ground_id' => $groundId,
            'branch_id' => $branch,
        ])->where('starts_at <', $ends)->where('ends_at >', $starts)->countAllResults() > 0) {
            throw new RuntimeException('Ground is unavailable.');
        }
        $bookings = $db->table('sessions')->where(['ground_id' => $groundId, 'branch_id' => $branch, 'status' => 'scheduled'])
            ->where('starts_at <', $ends)->where('ends_at >', $starts);
        if ($exceptSession !== null) {
            $bookings->where('id !=', $exceptSession);
        }
        if ($bookings->countAllResults() > 0) {
            throw new RuntimeException('Ground scheduling conflict.');
        }
    }

    private function ground(int $id): array
    {
        $row = Database::connect('default')
            ->table('grounds g')
            ->select('g.*, b.name AS branch_name')
            ->join('branches b', 'b.id = g.branch_id', 'left')
            ->where('g.id', $id)
            ->get()
            ->getRowArray();
        if (!is_array($row)) {
            throw new RuntimeException('Not found.');
        }
        return $row;
    }

    private function allow(int $branch, string $permission): void
    {
        if (!service('authorization')->can($permission, (string) $branch)) {
            throw new RuntimeException('Forbidden');
        }
    }

    private function branches(): array
    {
        $context = session()->get();
        $branches = $context['authorizedBranches'] ?? $context['branchIds'] ?? [];
        if (! is_array($branches)) {
            return [];
        }
        $authorized = [];
        foreach ($branches as $branch) {
            if (! ctype_digit((string) $branch) || (int) $branch < 1) {
                continue;
            }
            $branchId = (int) $branch;
            if (service('authorization')->can('ground.view', (string) $branchId)) {
                $authorized[] = $branchId;
            }
        }
        return array_values(array_unique($authorized));
    }

    private function actor(): int
    {
        $user = session('user');
        if (!is_array($user) || !isset($user['id'])) {
            throw new RuntimeException('Authentication required');
        }
        return (int) $user['id'];
    }

    private function auditHistory(object $db, int $groundId, int $branch): array
    {
        $maintenanceIds = array_map(
            'strval',
            array_column(
                $db->table('ground_unavailability')
                    ->select('id')
                    ->where(['ground_id' => $groundId, 'branch_id' => $branch])
                    ->get()
                    ->getResultArray(),
                'id'
            )
        );
        $entityIds = array_merge([(string) $groundId], $maintenanceIds);
        $rows = $db->table('audit_logs al')
            ->select('al.id, al.action, al.entity_type, al.entity_id, al.branch_id, al.before_summary, al.after_summary, al.reason, al.created_at, al.actor_user_id, u.display_name AS actor_name')
            ->join('users u', 'u.id = al.actor_user_id', 'left')
            ->where('al.branch_id', $branch)
            ->groupStart()
                ->groupStart()
                    ->where('al.entity_type', 'ground')
                    ->where('al.entity_id', (string) $groundId)
                ->groupEnd()
                ->orGroupStart()
                    ->where('al.entity_type', 'ground_unavailability')
                    ->whereIn('al.entity_id', $maintenanceIds === [] ? ['-1'] : $maintenanceIds)
                ->groupEnd()
            ->groupEnd()
            ->whereIn('al.action', [
                'ground_created',
                'ground_updated',
                'ground_maintenance_added',
            ])
            ->orderBy('al.id', 'ASC')
            ->get()
            ->getResultArray();

        foreach ($rows as &$row) {
            $row['before_metadata'] = $this->auditMetadata($row['before_summary'] ?? null);
            $row['after_metadata'] = $this->auditMetadata($row['after_summary'] ?? null);
            unset($row['before_summary'], $row['after_summary']);
        }
        unset($row);

        return $rows;
    }

    private function auditMetadata(?string $summary): array
    {
        if ($summary === null || $summary === '') {
            return [];
        }
        $metadata = json_decode($summary, true);

        return is_array($metadata) ? $metadata : [];
    }

    private function recordAudit(
        ?int $actor,
        string $action,
        string $type,
        string $id,
        ?int $branch,
        ?array $before,
        ?array $after,
        ?string $reason,
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
                $reason,
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
            $reason,
            $db
        );
    }

    private function text(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));
        if (strlen($text) > 10000) {
            throw new RuntimeException('Invalid ground.');
        }
        return $text === '' ? null : $text;
    }

    private function window(string $starts, string $ends): array
    {
        foreach ([$starts, $ends] as $value) {
            if (!preg_match('/(?:Z|[+-]\d{2}:\d{2})$/', $value)) {
                throw new RuntimeException('Datetime timezone is required.');
            }
        }
        $starts = $this->normalizeCalendarDate($starts);
        $ends = $this->normalizeCalendarDate($ends);
        $from = (new DateTimeImmutable($starts))->setTimezone(new DateTimeZone('UTC'));
        $to = (new DateTimeImmutable($ends))->setTimezone(new DateTimeZone('UTC'));
        if ($to <= $from) {
            throw new RuntimeException('Invalid maintenance period.');
        }
        return [$from->format('Y-m-d H:i:s'), $to->format('Y-m-d H:i:s')];
    }

    private function normalizeCalendarDate(string $value): string
    {
        $value = trim($value);
        $datePart = substr($value, 0, 10);
        $isoDate = $datePart;
        if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $datePart, $parts)) {
            $isoDate = $parts[3] . '-' . $parts[2] . '-' . $parts[1];
        }
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $isoDate)) {
            throw new RuntimeException('Invalid maintenance period.');
        }
        $date = DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $isoDate,
            new DateTimeZone('Asia/Kolkata')
        );
        $errors = DateTimeImmutable::getLastErrors();
        if (
            $date === false
            || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $date->format('Y-m-d') !== $isoDate
        ) {
            throw new RuntimeException('Invalid maintenance period.');
        }

        return $isoDate . substr($value, 10);
    }
}
