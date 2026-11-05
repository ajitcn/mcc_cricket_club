<?php

declare(strict_types=1);

namespace App\Services;

use App\Modules\Admissions\Models\AdmissionAgeGroupModel;
use Config\Database;
use RuntimeException;

final class AdmissionMasterService
{
    private const DEFINITIONS = [
        'age_group' => [
            'label' => 'Age Group',
            'table' => 'admission_age_groups',
            'model' => AdmissionAgeGroupModel::class,
            'idField' => 'age_group_id',
        ],
    ];

    /** @return array<string, array<string, string>> */
    public function definitions(): array
    {
        return self::DEFINITIONS;
    }

    /** @return list<array<string, mixed>> */
    public function list(string $type): array
    {
        $definition = $this->definition($type);
        $this->guardView();
        $branches = service('branchContext')->authorizedBranchIds(true);
        $query = Database::connect('default')
            ->table($definition['table'])
            ->select($definition['table'] . '.*, branches.name AS branch_name')
            ->join('branches', 'branches.id = ' . $definition['table'] . '.branch_id', 'left');

        if ($branches === []) {
            return [];
        }

        $query
            ->where($definition['table'] . '.branch_id', null)
            ->orderBy('is_active', 'DESC')
            ->orderBy('display_order', 'ASC')
            ->orderBy('name', 'ASC');

        return $query->orderBy('id', 'DESC')->get()->getResultArray();
    }

    /** @return array<string, list<array<string, mixed>>> */
    public function activeForAdmission(int $branchId): array
    {
        if ($branchId < 1 || ! service('authorization')->can('admission.view', (string) $branchId)) {
            throw new RuntimeException('Forbidden');
        }

        return $this->activeForBranches([$branchId]);
    }

    /** @return array<string, list<array<string, mixed>>> */
    public function forAdmissionEdit(int $branchId, array $admission): array
    {
        if ($branchId < 1 || ! service('authorization')->can('admission.view', (string) $branchId)) {
            throw new RuntimeException('Forbidden');
        }

        $result = $this->activeForBranches([$branchId]);
        $db = Database::connect('default');

        foreach (self::DEFINITIONS as $type => $definition) {
            $field = $definition['idField'];
            $selectedId = (int) ($admission[$field] ?? 0);
            if ($selectedId < 1) {
                continue;
            }

            $selected = $db->table($definition['table'])
                ->where(['id' => $selectedId, 'branch_id' => null])
                ->get()
                ->getRowArray();
            if (! is_array($selected)) {
                continue;
            }

            $existingIds = array_map(
                static fn (array $row): int => (int) ($row['id'] ?? 0),
                $result[$type] ?? []
            );
            if (! in_array($selectedId, $existingIds, true)) {
                $result[$type][] = $selected;
            }
        }

        return $result;
    }

    /** @return array<string, list<array<string, mixed>>> */
    public function activeForAuthorizedBranches(): array
    {
        $this->guardView();

        return $this->activeForBranches(service('branchContext')->authorizedBranchIds(true));
    }

    /** @param list<int> $branches */
    private function activeForBranches(array $branches): array
    {
        if ($branches === []) {
            return array_fill_keys(array_keys(self::DEFINITIONS), []);
        }

        $result = [];
        foreach (array_keys(self::DEFINITIONS) as $type) {
            $definition = self::DEFINITIONS[$type];
            $query = Database::connect('default')->table($definition['table'])
                ->where(['is_active' => 1, 'branch_id' => null])
                ->orderBy('display_order', 'ASC')
                ->orderBy('name', 'ASC');
            $result[$type] = $query->get()->getResultArray();
        }

        return $result;
    }

    /** @param array<string, mixed> $input */
    public function create(string $type, array $input): int
    {
        $definition = $this->definition($type);
        $actor = $this->guardManage(
            $type === 'age_group' ? null : ($input['branch_id'] ?? null),
            false,
            $type === 'age_group'
        );
        $data = $this->normalize($type, $input);
        $data['created_by'] = $actor;
        $data['created_at'] = gmdate('Y-m-d H:i:s');
        $data['updated_at'] = $data['created_at'];
        $modelClass = $definition['model'];
        $model = new $modelClass();
        $id = $model->insert($data, true);

        if (! is_numeric($id) || (int) $id < 1) {
            throw new RuntimeException('Unable to save Admission master.');
        }

        return (int) $id;
    }

    /** @param array<string, mixed> $input */
    public function update(string $type, int $id, array $input): void
    {
        $definition = $this->definition($type);
        $modelClass = $definition['model'];
        $model = new $modelClass();
        $before = $model->find($id);
        if (! is_array($before)) {
            throw new RuntimeException('Not found.');
        }

        $actor = $this->guardManage(
            $type === 'age_group' ? null : ($before['branch_id'] ?? null),
            false,
            $type === 'age_group'
        );
        $data = $this->normalize($type, $input, $before);
        $this->validateRelationships($type, $data);
        $data['updated_at'] = gmdate('Y-m-d H:i:s');
        if (! $model->update($id, $data)) {
            throw new RuntimeException('Unable to update Admission master.');
        }

        service('auditPersistence')->record($actor, 'admission_master_updated', $type, (string) $id, $this->nullableInt($data['branch_id'] ?? ($before['branch_id'] ?? null)), $before, $data);
    }

    public function setActive(string $type, int $id, bool $active): void
    {
        $definition = $this->definition($type);
        $modelClass = $definition['model'];
        $model = new $modelClass();
        $before = $model->find($id);
        if (! is_array($before)) {
            throw new RuntimeException('Not found.');
        }

        $actor = $this->guardManage(
            $type === 'age_group' ? null : ($before['branch_id'] ?? null),
            ! $active,
            $type === 'age_group'
        );
        $model->update($id, ['is_active' => $active ? 1 : 0, 'updated_at' => gmdate('Y-m-d H:i:s')]);
        service('auditPersistence')->record($actor, $active ? 'admission_master_activated' : 'admission_master_retired', $type, (string) $id, $this->nullableInt($before['branch_id'] ?? null), $before, ['is_active' => $active ? 1 : 0]);
    }

    /** @return array<string, mixed> */
    private function normalize(string $type, array $input, array $before = []): array
    {
        $data = [];
        $branch = $type === 'age_group'
            ? null
            : ($input['branch_id'] ?? ($before['branch_id'] ?? null));
        $data['branch_id'] = $this->nullableInt($branch);
        $data['is_active'] = ! array_key_exists('is_active', $input) ? (int) ($before['is_active'] ?? 1) : (! empty($input['is_active']) ? 1 : 0);

        $data += [
            'name' => $this->text($input['name'] ?? ($before['name'] ?? null), 100),
            'display_order' => max(0, (int) ($input['display_order'] ?? ($before['display_order'] ?? 0))),
        ];

        return $data;
    }

    private function guardView(): void
    {
        if (! service('authorization')->can('admission.view')) {
            throw new RuntimeException('Forbidden');
        }
    }

    private function guardManage(mixed $branch, bool $retire, bool $global = false): int
    {
        $context = service('branchContext');
        if (! $context->isSuperAdmin() && ! $context->isBranchAdminContext()) {
            throw new RuntimeException('Forbidden');
        }

        $branchId = $this->nullableInt($branch);
        if (! $global && $context->isBranchAdminContext() && $branchId === null) {
            throw new RuntimeException('Forbidden');
        }
        if ($branchId !== null && ! in_array($branchId, $context->authorizedBranchIds(true), true)) {
            throw new RuntimeException('Forbidden');
        }
        if (! $global && $retire && $context->isBranchAdminContext() && $branchId === null) {
            throw new RuntimeException('Forbidden');
        }
        if (! service('authorization')->can('admission.update', $branchId === null ? null : (string) $branchId)) {
            throw new RuntimeException('Forbidden');
        }

        $user = session('user');
        $userId = is_array($user) ? ($user['id'] ?? null) : null;
        if (! is_scalar($userId) || ! ctype_digit((string) $userId)) {
            throw new RuntimeException('Forbidden');
        }

        return (int) $userId;
    }

    /** @return array<string, string> */
    private function definition(string $type): array
    {
        if (! isset(self::DEFINITIONS[$type])) {
            throw new RuntimeException('Invalid Admission master.');
        }

        return self::DEFINITIONS[$type];
    }

    private function text(mixed $value, int $max): string
    {
        $value = trim((string) $value);
        if ($value === '' || strlen($value) > $max) {
            throw new RuntimeException('Invalid Admission master.');
        }

        return $value;
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (! is_scalar($value) || ! ctype_digit((string) $value)) {
            throw new RuntimeException('Invalid Admission master.');
        }

        return (int) $value;
    }

}
