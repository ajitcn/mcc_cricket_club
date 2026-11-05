<?php

declare(strict_types=1);

namespace App\Services;

use App\Modules\Equipment\Models\EquipmentModel;
use Config\Database;
use RuntimeException;

final class EquipmentService
{
    /** @return list<array<string, mixed>> */
    public function list(): array
    {
        $branches = $this->branchIdsForPermission('equipment.view');
        if ($branches === []) {
            return [];
        }

        $records = Database::connect('default')
            ->table('equipment e')
            ->select(
                'e.id, e.name, e.quantity, e.branch_id, b.name AS branch_name, '
                . '(SELECT COALESCE(SUM(GREATEST(eai.quantity_assigned - eai.quantity_returned, 0)), 0) '
                . 'FROM equipment_assignment_items eai '
                . 'INNER JOIN equipment_assignments ea ON ea.id = eai.assignment_id '
                . 'WHERE eai.equipment_id = e.id '
                . "AND ea.deleted_at IS NULL AND ea.status IN ('assigned', 'partially_returned')) AS quantity_assigned",
                false
            )
            ->join('branches b', 'b.id = e.branch_id')
            ->whereIn('e.branch_id', $branches)
            ->where('e.deleted_at', null)
            ->orderBy('b.name', 'ASC')
            ->orderBy('e.name', 'ASC')
            ->orderBy('e.id', 'DESC')
            ->get()
            ->getResultArray();

        $updateBranches = $this->branchIdsIfPermitted('equipment.update');
        $deleteBranches = $this->branchIdsIfPermitted('equipment.delete');
        foreach ($records as &$record) {
            $record['quantity_assigned'] = (int) ($record['quantity_assigned'] ?? 0);
            $record['quantity_available'] = max(0, (int) $record['quantity'] - $record['quantity_assigned']);
            $branchId = (int) $record['branch_id'];
            $record['can_update'] = in_array($branchId, $updateBranches, true);
            $record['can_delete'] = in_array($branchId, $deleteBranches, true);
        }
        unset($record);

        return $records;
    }

    /** @return array<string, mixed> */
    public function findForUpdate(int $equipmentId): array
    {
        return $this->findAuthorizedRecord($equipmentId, 'equipment.update');
    }

    /** @return array<string, mixed> */
    public function findForDelete(int $equipmentId): array
    {
        return $this->findAuthorizedRecord($equipmentId, 'equipment.delete');
    }

    /** @return list<array<string, mixed>> */
    public function branchesForSuperAdmin(): array
    {
        $this->requireSuperAdmin();

        return array_values(array_filter(
            service('branchContext')->authorizedBranches(true),
            static fn (array $branch): bool => service('authorization')->can(
                'equipment.create'
            )
        ));
    }

    public function hasAuthorizedBranchForCreation(): bool
    {
        return $this->branchesForSuperAdmin() !== [];
    }

    public function assignments(array $filters = []): array
    {
        $branches = $this->branchIdsForPermission('equipment.view');
        if ($branches === []) {
            return [];
        }
        $builder = Database::connect('default')->table('equipment_assignments ea')
            ->select(
                'ea.*, b.name AS branch_name, u.display_name AS coach_name, '
                . 'COALESCE(SUM(eai.quantity_assigned), 0) AS quantity_assigned, '
                . 'COALESCE(SUM(eai.quantity_returned), 0) AS quantity_returned'
            )
            ->join('branches b', 'b.id = ea.branch_id')
            ->join('users u', 'u.id = ea.coach_user_id')
            ->join('equipment_assignment_items eai', 'eai.assignment_id = ea.id', 'left')
            ->whereIn('ea.branch_id', $branches)
            ->where('ea.deleted_at', null);
        $query = trim((string) ($filters['q'] ?? ''));
        if ($query !== '') {
            $builder->groupStart()
                ->like('u.display_name', $query)
                ->orLike('b.name', $query)
                ->groupEnd();
        }
        $status = (string) ($filters['status'] ?? '');
        if (in_array($status, ['assigned', 'partially_returned', 'returned'], true)) {
            $builder->where('ea.status', $status);
        }

        return $builder
            ->groupBy('ea.id')
            ->orderBy('ea.assigned_date', 'DESC')
            ->orderBy('ea.id', 'DESC')
            ->get()
            ->getResultArray();
    }

    public function assignmentOptions(): array
    {
        $db = Database::connect('default');
        $branches = $this->assignmentBranchIds($db);
        if ($branches === [] && service('authorization')->can('equipment.update')) {
            $branches = array_map(
                static fn (array $branch): int => (int) $branch['id'],
                $db->table('branches')->select('id')->where('is_active', 1)->orderBy('name')->get()->getResultArray(),
            );
        }
        if ($branches === []) {
            throw new RuntimeException('No authorized branch.');
        }

        return [
            'coaches' => $db->table('users u')->select('u.id, u.display_name, ub.branch_id')
                ->join('user_branches ub', 'ub.user_id = u.id')
                ->join('user_roles ur', 'ur.user_id = u.id')
                ->join('roles r', 'r.id = ur.role_id')
                ->where('u.is_enabled', 1)->whereIn('r.name', ['Coach', 'Fitness Coach'])
                ->whereIn('ub.branch_id', $branches)->orderBy('u.display_name')->get()->getResultArray(),
            'branches' => $db->table('branches')->whereIn('id', $branches)->where('is_active', 1)->orderBy('name')->get()->getResultArray(),
            'equipment' => $db->table('equipment')->whereIn('branch_id', $branches)->where('deleted_at', null)->orderBy('name')->get()->getResultArray(),
        ];
    }

    /** @return array<string, mixed> */
    public function findAssignmentForUpdate(int $id): array
    {
        $db = Database::connect('default');
        $assignment = $db->table('equipment_assignments ea')
            ->select('ea.*, b.name AS branch_name, u.display_name AS coach_name')
            ->join('branches b', 'b.id = ea.branch_id')
            ->join('users u', 'u.id = ea.coach_user_id')
            ->where('ea.id', $id)
            ->where('ea.deleted_at', null)
            ->get()
            ->getRowArray();

        if (! is_array($assignment)) {
            throw new RuntimeException('Not found.');
        }

        $this->allow((int) $assignment['branch_id'], 'equipment.update');

        if ((string) $assignment['status'] !== 'assigned') {
            throw new RuntimeException('Assignment already returned.');
        }

        $assignment['items'] = $db->table('equipment_assignment_items')
            ->where('assignment_id', $id)
            ->get()
            ->getResultArray();

        return $assignment;
    }

    /** @return array<string, mixed> */
    public function findAssignment(int $id): array
    {
        $db = Database::connect('default');
        $assignment = $db->table('equipment_assignments ea')
            ->select('ea.*, b.name AS branch_name, u.display_name AS coach_name')
            ->join('branches b', 'b.id = ea.branch_id')
            ->join('users u', 'u.id = ea.coach_user_id')
            ->where('ea.id', $id)
            ->where('ea.deleted_at', null)
            ->get()
            ->getRowArray();

        if (! is_array($assignment)) {
            throw new RuntimeException('Not found.');
        }

        $this->allow((int) $assignment['branch_id'], 'equipment.view');
        $assignment['items'] = $db->table('equipment_assignment_items ai')
            ->select("ai.*, COALESCE(e.name, CONCAT('Equipment #', ai.equipment_id)) AS equipment_name")
            ->join('equipment e', 'e.id = ai.equipment_id', 'left')
            ->where('ai.assignment_id', $id)
            ->orderBy('e.name', 'ASC')
            ->get()
            ->getResultArray();

        return $assignment;
    }

    public function updateAssignment(int $id, array $input): void
    {
        $branchId = (int) ($input['branch_id'] ?? 0);
        $coachId = (int) ($input['coach_user_id'] ?? 0);
        $date = trim((string) ($input['assigned_date'] ?? ''));
        $items = is_array($input['equipment'] ?? null) ? $input['equipment'] : [];
        $version = (int) ($input['version'] ?? 0);

        if ($branchId < 1 || $coachId < 1 || ! $this->validDate($date) || $version < 1) {
            throw new RuntimeException('Invalid equipment assignment.');
        }

        $db = Database::connect('default');
        $db->transBegin();

        try {
            $assignment = $db->table('equipment_assignments')
                ->where(['id' => $id, 'deleted_at' => null])
                ->get()
                ->getRowArray();

            if (! is_array($assignment)) {
                throw new RuntimeException('Not found.');
            }

            $this->allow((int) $assignment['branch_id'], 'equipment.update');

            if ((string) $assignment['status'] !== 'assigned') {
                throw new RuntimeException('Assignment already returned.');
            }

            if ((int) $assignment['version'] !== $version) {
                throw new RuntimeException('Update conflict.');
            }

            $this->allow($branchId, 'equipment.update');
            $this->assertCoachBranch($db, $coachId, $branchId);
            $now = gmdate('Y-m-d H:i:s');

            foreach ($items as $equipmentId => $quantity) {
                $equipmentId = (int) $equipmentId;
                $quantity = (int) $quantity;

                if ($quantity < 1) {
                    continue;
                }

                $equipment = $db->table('equipment')
                    ->where([
                        'id' => $equipmentId,
                        'branch_id' => $branchId,
                        'deleted_at' => null,
                    ])
                    ->get()
                    ->getRowArray();

                if (! is_array($equipment) || $quantity > (int) $equipment['quantity']) {
                    throw new RuntimeException('Invalid equipment quantity.');
                }
            }

            $updated = $db->table('equipment_assignments')
                ->where('id', $id)
                ->where('version', $version)
                ->update([
                    'branch_id' => $branchId,
                    'coach_user_id' => $coachId,
                    'assigned_date' => $date,
                    'version' => $version + 1,
                    'updated_at' => $now,
                ]);

            if (! $updated) {
                throw new RuntimeException('Update conflict.');
            }

            $db->table('equipment_assignment_items')->where('assignment_id', $id)->delete();
            foreach ($items as $equipmentId => $quantity) {
                $quantity = (int) $quantity;
                if ($quantity < 1) {
                    continue;
                }

                $db->table('equipment_assignment_items')->insert([
                    'assignment_id' => $id,
                    'equipment_id' => (int) $equipmentId,
                    'quantity_assigned' => $quantity,
                    'quantity_returned' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            service('auditPersistence')->record(
                $this->actorId(),
                'equipment_assignment_updated',
                'equipment_assignment',
                (string) $id,
                $branchId,
                $assignment,
                ['branch_id' => $branchId, 'coach_user_id' => $coachId, 'assigned_date' => $date],
                null,
                $db
            );

            $db->transCommit();
        } catch (\Throwable $exception) {
            $db->transRollback();
            throw $exception;
        }
    }

    /** @return list<int> */
    private function assignmentBranchIds(object $db): array
    {
        if (service('branchContext')->isSuperAdmin()) {
            return array_map(
                static fn (array $branch): int => (int) $branch['id'],
                $db->table('branches')->select('id')->where('is_active', 1)->get()->getResultArray(),
            );
        }

        $branchId = service('branchContext')->activeBranchId();
        return $branchId === null ? [] : [$branchId];
    }

    public function createAssignment(array $input): int
    {
        $branchId = (int) ($input['branch_id'] ?? 0);
        $coachId = (int) ($input['coach_user_id'] ?? 0);
        $date = trim((string) ($input['assigned_date'] ?? ''));
        $items = is_array($input['equipment'] ?? null) ? $input['equipment'] : [];
        $selectedItems = [];
        foreach ($items as $equipmentId => $quantity) {
            $quantity = (int) $quantity;
            if ($quantity > 0) {
                $selectedItems[(int) $equipmentId] = $quantity;
            }
        }

        if ($branchId < 1 || $coachId < 1 || ! $this->validDate($date) || $selectedItems === []) {
            throw new RuntimeException('Invalid equipment assignment.');
        }
        $this->allow($branchId, 'equipment.update');
        $db = Database::connect('default');
        $db->transBegin();
        try {
            $this->assertCoachBranch($db, $coachId, $branchId);
            $now = gmdate('Y-m-d H:i:s');
            $assignmentData = [
                'branch_id' => $branchId, 'coach_user_id' => $coachId, 'assigned_date' => $date,
                'status' => 'assigned', 'version' => 1, 'created_by' => $this->actorId(),
                'created_at' => $now, 'updated_at' => $now,
            ];
            if (! $db->table('equipment_assignments')->insert($assignmentData)) {
                throw new RuntimeException('Unable to record equipment assignment.');
            }
            $assignmentId = (int) $db->insertID();
            if ($assignmentId < 1) {
                throw new RuntimeException('Unable to record equipment assignment.');
            }
            foreach ($selectedItems as $equipmentId => $quantity) {
                $equipment = $db->table('equipment')->where(['id' => $equipmentId, 'branch_id' => $branchId, 'deleted_at' => null])->get()->getRowArray();
                if (! is_array($equipment) || $quantity > (int) $equipment['quantity']) throw new RuntimeException('Invalid equipment quantity.');
                if (! $db->table('equipment_assignment_items')->insert(['assignment_id' => $assignmentId, 'equipment_id' => $equipmentId, 'quantity_assigned' => $quantity, 'quantity_returned' => 0, 'created_at' => $now, 'updated_at' => $now])) {
                    throw new RuntimeException('Unable to record assigned equipment.');
                }
            }
            service('auditPersistence')->record($this->actorId(), 'equipment_assigned', 'equipment_assignment', (string) $assignmentId, $branchId, null, ['coach_user_id' => $coachId, 'assigned_date' => $date], null, $db);
            $db->transCommit();
            return $assignmentId;
        } catch (\Throwable $exception) { $db->transRollback(); throw $exception; }
    }

    public function returnAssignment(int $id, array $input): void
    {
        $returnDate = trim((string) ($input['returned_date'] ?? ''));
        if (! $this->validDate($returnDate)) throw new RuntimeException('Invalid return date.');
        $db = Database::connect('default');
        $db->transBegin();
        try {
            $assignment = $db->table('equipment_assignments')->where(['id' => $id, 'deleted_at' => null])->get()->getRowArray();
            if (! is_array($assignment)) throw new RuntimeException('Not found.');
            $this->allow((int) $assignment['branch_id'], 'equipment.update');
            if (! in_array((string) $assignment['status'], ['assigned', 'partially_returned'], true)) {
                throw new RuntimeException('Assignment already returned.');
            }
            $items = $db->table('equipment_assignment_items')->where('assignment_id', $id)->get()->getResultArray();
            $requested = is_array($input['returned'] ?? null) ? $input['returned'] : [];
            if ($requested !== [] && array_sum(array_map('intval', $requested)) < 1) {
                throw new RuntimeException('Invalid return quantity.');
            }
            $allReturned = true;
            foreach ($items as $item) {
                $remaining = max(0, (int) $item['quantity_assigned'] - (int) $item['quantity_returned']);
                $quantity = $requested === []
                    ? $remaining
                    : max(0, min($remaining, (int) ($requested[(int) $item['id']] ?? 0)));
                $db->table('equipment_assignment_items')->where('id', (int) $item['id'])->update([
                    'quantity_returned' => (int) $item['quantity_returned'] + $quantity,
                    'updated_at' => gmdate('Y-m-d H:i:s'),
                ]);
                if ($quantity < $remaining) {
                    $allReturned = false;
                }
            }
            $status = $allReturned ? 'returned' : 'partially_returned';
            $db->table('equipment_assignments')
                ->where('id', $id)
                ->update([
                    'status' => $status,
                    'returned_date' => $returnDate,
                    'version' => ((int) $assignment['version']) + 1,
                    'updated_at' => gmdate('Y-m-d H:i:s'),
                ]);
            service('auditPersistence')->record($this->actorId(), 'equipment_returned', 'equipment_assignment', (string) $id, (int) $assignment['branch_id'], ['status' => $assignment['status']], ['status' => $status, 'returned_date' => $returnDate], null, $db);
            $db->transCommit();
        } catch (\Throwable $exception) { $db->transRollback(); throw $exception; }
    }

    private function assertCoachBranch(object $db, int $coachId, int $branchId): void
    {
        if ($db->table('user_branches ub')->join('user_roles ur', 'ur.user_id = ub.user_id')->join('roles r', 'r.id = ur.role_id')->where(['ub.user_id' => $coachId, 'ub.branch_id' => $branchId])->whereIn('r.name', ['Coach', 'Fitness Coach'])->countAllResults() < 1) throw new RuntimeException('Invalid Coach.');
    }

    private function validDate(string $date): bool
    {
        $parsed = date_create_from_format('Y-m-d', $date);
        return $parsed !== false && $parsed->format('Y-m-d') === $date;
    }

    public function activeBranchNameForBranchAdmin(): ?string
    {
        $this->requireBranchAdminRole();
        $branchId = service('branchContext')->activeBranchId();
        if ($branchId === null) {
            return null;
        }

        $this->allow($branchId, 'equipment.create');

        return service('branchContext')->activeBranchName();
    }

    /** @param array<string, mixed> $input */
    public function create(array $input): int
    {
        $branchContext = service('branchContext');
        if ($branchContext->isSuperAdmin()) {
            $branchId = $this->selectedSuperAdminBranch($input['branch_id'] ?? null);
        } elseif ($branchContext->isBranchAdminContext()) {
            $branchId = $branchContext->activeBranchId();
            if ($branchId === null) {
                throw new RuntimeException('Branch selection required.');
            }
        } else {
            throw new RuntimeException('Forbidden');
        }

        $values = $this->validatedValues($input);

        $this->allow($branchId, 'equipment.create');
        $actorId = $this->actorId();
        $db = Database::connect('default');
        $db->transBegin();

        try {
            $model = new EquipmentModel();
            $equipmentId = $model->insert([
                'name' => $values['name'],
                'quantity' => $values['quantity'],
                'branch_id' => $branchId,
                'created_by' => $actorId,
                'created_at' => gmdate('Y-m-d H:i:s'),
                'updated_at' => gmdate('Y-m-d H:i:s'),
            ], true);

            if (! is_numeric($equipmentId) || (int) $equipmentId < 1) {
                throw new RuntimeException('Invalid equipment.');
            }
            $equipmentId = (int) $equipmentId;

            service('auditPersistence')->record(
                $actorId,
                'equipment_created',
                'equipment',
                (string) $equipmentId,
                $branchId,
                null,
                $values,
                null,
                $db
            );

            $db->transCommit();

            return $equipmentId;
        } catch (\Throwable $exception) {
            $db->transRollback();

            throw $exception;
        }
    }

    /** @param array<string, mixed> $input */
    public function update(int $equipmentId, array $input): void
    {
        $values = $this->validatedValues($input);
        $db = Database::connect('default');
        $db->transBegin();

        try {
            $before = $this->findAuthorizedRecord(
                $equipmentId,
                'equipment.update',
                $db
            );
            $branchId = (int) $before['branch_id'];
            $after = [
                'name' => $values['name'],
                'quantity' => $values['quantity'],
            ];

            $db->table('equipment')
                ->where([
                    'id' => $equipmentId,
                    'branch_id' => $branchId,
                    'deleted_at' => null,
                ])
                ->update($after + ['updated_at' => gmdate('Y-m-d H:i:s')]);

            service('auditPersistence')->record(
                $this->actorId(),
                'equipment_updated',
                'equipment',
                (string) $equipmentId,
                $branchId,
                [
                    'name' => $before['name'],
                    'quantity' => (int) $before['quantity'],
                ],
                $after,
                null,
                $db
            );

            $db->transCommit();
        } catch (\Throwable $exception) {
            $db->transRollback();

            throw $exception;
        }
    }

    public function softDelete(int $equipmentId): void
    {
        $db = Database::connect('default');
        $db->transBegin();

        try {
            $record = $this->findAuthorizedRecord(
                $equipmentId,
                'equipment.delete',
                $db
            );
            $branchId = (int) $record['branch_id'];
            $now = gmdate('Y-m-d H:i:s');

            $db->table('equipment')
                ->where([
                    'id' => $equipmentId,
                    'branch_id' => $branchId,
                    'deleted_at' => null,
                ])
                ->update([
                    'deleted_at' => $now,
                    'updated_at' => $now,
                ]);

            service('auditPersistence')->record(
                $this->actorId(),
                'equipment_deleted',
                'equipment',
                (string) $equipmentId,
                $branchId,
                [
                    'name' => $record['name'],
                    'quantity' => (int) $record['quantity'],
                ],
                ['deleted_at' => $now],
                null,
                $db
            );

            $db->transCommit();
        } catch (\Throwable $exception) {
            $db->transRollback();

            throw $exception;
        }
    }

    private function selectedSuperAdminBranch(mixed $branchValue): int
    {
        if (! is_scalar($branchValue) || ! ctype_digit((string) $branchValue)) {
            throw new RuntimeException('Invalid branch selection.');
        }

        $branchId = (int) $branchValue;
        $branches = service('branchContext')->authorizedBranches(true);
        $authorizedIds = array_map(
            static fn (array $branch): int => (int) $branch['id'],
            $branches
        );

        if ($branchId < 1 || ! in_array($branchId, $authorizedIds, true)) {
            throw new RuntimeException('Invalid branch selection.');
        }

        $this->allow($branchId, 'equipment.create');

        return $branchId;
    }

    /** @return array<string, mixed> */
    private function findAuthorizedRecord(
        int $equipmentId,
        string $permission,
        ?object $db = null
    ): array {
        $branchIds = $this->branchIdsForPermission($permission);
        if ($branchIds === []) {
            throw new RuntimeException('Forbidden');
        }

        $record = ($db ?? Database::connect('default'))
            ->table('equipment e')
            ->select('e.id, e.name, e.quantity, e.branch_id, b.name AS branch_name')
            ->join('branches b', 'b.id = e.branch_id')
            ->where('e.id', $equipmentId)
            ->whereIn('e.branch_id', $branchIds)
            ->where('e.deleted_at', null)
            ->get()
            ->getRowArray();

        if (! is_array($record)) {
            throw new RuntimeException('Not found.');
        }

        return $record;
    }

    /** @return list<int> */
    private function branchIdsForPermission(string $permission): array
    {
        if (! service('authorization')->can($permission)) {
            throw new RuntimeException('Forbidden');
        }

        return $this->branchIdsForCurrentRole();
    }

    /** @return list<int> */
    private function branchIdsIfPermitted(string $permission): array
    {
        if (! service('authorization')->can($permission)) {
            return [];
        }

        try {
            return $this->branchIdsForCurrentRole();
        } catch (RuntimeException $exception) {
            if ($exception->getMessage() === 'Forbidden') {
                return [];
            }

            throw $exception;
        }
    }

    /** @return list<int> */
    private function branchIdsForCurrentRole(): array
    {
        $branchContext = service('branchContext');
        if ($branchContext->isSuperAdmin()) {
            $branches = $branchContext->authorizedBranches(true);
        } elseif ($branchContext->isBranchAdminContext()) {
            $activeBranchId = $branchContext->activeBranchId();
            $branches = $activeBranchId === null
                ? []
                : [['id' => $activeBranchId]];
        } else {
            throw new RuntimeException('Forbidden');
        }

        $branchIds = [];
        foreach ($branches as $branch) {
            $branchId = (int) $branch['id'];
            if ($branchId > 0) {
                $branchIds[] = $branchId;
            }
        }

        return array_values(array_unique($branchIds));
    }

    /** @param array<string, mixed> $input
     *  @return array{name: string, quantity: int}
     */
    private function validatedValues(array $input): array
    {
        $nameValue = $input['name'] ?? null;
        $quantityValue = $input['quantity'] ?? null;
        if (! is_scalar($nameValue) || ! is_scalar($quantityValue)) {
            throw new RuntimeException('Invalid equipment.');
        }

        $name = trim((string) $nameValue);
        $quantityText = (string) $quantityValue;
        if (
            $name === ''
            || mb_strlen($name, 'UTF-8') > 150
            || ! ctype_digit($quantityText)
            || (int) $quantityText < 1
            || (int) $quantityText > 4_294_967_295
        ) {
            throw new RuntimeException('Invalid equipment.');
        }

        return [
            'name' => $name,
            'quantity' => (int) $quantityText,
        ];
    }

    private function requireSuperAdmin(): void
    {
        if (! service('branchContext')->isSuperAdmin()) {
            throw new RuntimeException('Forbidden');
        }
    }

    private function requireBranchAdminRole(): void
    {
        if (! service('branchContext')->isBranchAdminContext()) {
            throw new RuntimeException('Forbidden');
        }
    }

    private function allow(int $branchId, string $permission): void
    {
        $branchContext = service('branchContext');
        $superAdminBranch = $branchContext->isSuperAdmin()
            && Database::connect('default')->table('branches')->where(['id' => $branchId, 'is_active' => 1])->countAllResults() === 1;
        if (
            ! $superAdminBranch
            && ! $branchContext->isCurrentlyAuthorizedBranch($branchId, true)
            || ! service('authorization')->can($permission)
        ) {
            throw new RuntimeException('Forbidden');
        }
    }

    private function actorId(): int
    {
        $user = session('user');
        $userId = is_array($user) ? ($user['id'] ?? null) : null;
        if (! is_scalar($userId) || ! ctype_digit((string) $userId) || (int) $userId < 1) {
            throw new RuntimeException('Forbidden');
        }

        return (int) $userId;
    }
}
