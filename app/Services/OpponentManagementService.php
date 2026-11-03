<?php

declare(strict_types=1);

namespace App\Services;

use App\Modules\Admin\Models\BranchModel;
use App\Modules\Matches\Models\OpponentModel;
use Config\Database;
use RuntimeException;

final class OpponentManagementService
{
    public function list(array $filters = []): array
    {
        $branches = $this->authorizedBranches('opponent.view');
        $builder = Database::connect('default')->table('opponents o')
            ->select('o.*, b.name AS branch_name')
            ->join('branches b', 'b.id = o.branch_id')
            ->whereIn('o.branch_id', $branches);
        if (($query = trim((string) ($filters['q'] ?? ''))) !== '') {
            $builder->groupStart()->like('o.name', $query)->orLike('b.name', $query)->groupEnd();
        }
        $opponents = $builder->orderBy('o.name')->get()->getResultArray();
        foreach ($opponents as &$opponent) {
            $opponent['can_update'] = service('authorization')->can('opponent.update', (string) $opponent['branch_id']);
        }
        return ['opponents' => $opponents, 'filters' => $filters];
    }

    public function formOptions(string $permission = 'opponent.create'): array
    {
        $ids = $this->authorizedBranches($permission);
        return ['branches' => (new BranchModel())->whereIn('id', $ids)->orderBy('name')->findAll()];
    }

    public function find(int $id): array
    {
        $opponent = (new OpponentModel())->find($id);
        if (! is_array($opponent)) {
            throw new RuntimeException('Not found.');
        }
        $this->allow((int) $opponent['branch_id'], 'opponent.view');
        return $opponent;
    }

    public function save(array $input, ?int $id = null): int
    {
        $branch = (int) ($input['branch_id'] ?? 0);
        $permission = $id === null ? 'opponent.create' : 'opponent.update';
        $this->allow($branch, $permission);
        $name = trim((string) ($input['name'] ?? ''));
        $active = (int) ($input['is_active'] ?? 1);
        if ($branch < 1) {
            throw new RuntimeException('Invalid branch.');
        }
        if ($name === '' || strlen($name) > 150) {
            throw new RuntimeException('Invalid opponent name.');
        }
        if (! in_array($active, [0, 1], true)) {
            throw new RuntimeException('Invalid active status.');
        }
        $db = Database::connect('default');
        $duplicateBuilder = $db->table('opponents')->where(['branch_id' => $branch, 'name' => $name]);
        if ($id !== null) {
            $duplicateBuilder->where('id !=', $id);
        }
        $duplicate = $duplicateBuilder->countAllResults();
        if ($duplicate > 0) {
            throw new RuntimeException('Opponent name already exists in this branch.');
        }
        $data = ['branch_id' => $branch, 'name' => $name, 'is_active' => $active, 'updated_at' => gmdate('Y-m-d H:i:s')];
        if ($id !== null) {
            $existing = (new OpponentModel())->find($id);
            if (! is_array($existing) || (int) $existing['branch_id'] !== $branch) {
                throw new RuntimeException('Not found.');
            }
            $db->table('opponents')->where('id', $id)->update($data);
            service('auditPersistence')->record($this->actor(), 'opponent_updated', 'opponent', (string) $id, $branch, $existing, $data, null, $db);
            return $id;
        }
        $db->table('opponents')->insert($data + ['created_at' => gmdate('Y-m-d H:i:s')]);
        $id = (int) $db->insertID();
        service('auditPersistence')->record($this->actor(), 'opponent_created', 'opponent', (string) $id, $branch, null, $data, null, $db);
        return $id;
    }

    public function activeForBranches(array $branches): array
    {
        if ($branches === []) {
            return [];
        }
        return Database::connect('default')->table('opponents')
            ->whereIn('branch_id', $branches)->where('is_active', 1)->orderBy('name')->get()->getResultArray();
    }

    private function authorizedBranches(string $permission): array
    {
        $ids = service('branchContext')->authorizedBranchIds(true);
        return array_values(array_filter($ids, static fn (int $id): bool => service('authorization')->can($permission, (string) $id)));
    }

    private function allow(int $branch, string $permission): void
    {
        if ($branch < 1 || ! service('authorization')->can($permission, (string) $branch)) {
            throw new RuntimeException('Forbidden');
        }
    }

    private function actor(): ?int
    {
        $user = session('user');
        $userId = is_array($user) ? (int) ($user['id'] ?? 0) : (int) (session('user_id') ?? 0);

        return $userId > 0 ? $userId : null;
    }
}
