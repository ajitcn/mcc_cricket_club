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
            ->select('e.id, e.name, e.quantity, e.branch_id, b.name AS branch_name')
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
        if (
            ! $branchContext->isCurrentlyAuthorizedBranch($branchId, true)
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
