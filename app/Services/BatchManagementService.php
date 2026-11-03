<?php

declare(strict_types=1);

namespace App\Services;

use App\Modules\Players\Models\BatchModel;
use Config\Database;
use RuntimeException;

final class BatchManagementService
{
    /** @return list<array<string, mixed>> */
    public function list(): array
    {
        $this->allow('batch.view');

        return Database::connect('default')
            ->table('batches')
            ->select('id, name, is_active, created_at, updated_at')
            ->orderBy('name', 'ASC')
            ->orderBy('id', 'DESC')
            ->get()
            ->getResultArray();
    }

    /** @param array<string, mixed> $input */
    public function create(array $input): int
    {
        $actor = $this->allow('batch.create');
        $name = $this->name($input['name'] ?? null);
        $isActive = $this->activeValue($input['is_active'] ?? 1);
        $db = Database::connect('default');

        $this->assertUniqueName($db, $name);
        $db->transBegin();

        try {
            $now = gmdate('Y-m-d H:i:s');
            $id = (new BatchModel())->insert([
                'branch_id' => null,
                'program_id' => null,
                'name' => $name,
                'is_active' => $isActive,
                'created_at' => $now,
                'updated_at' => $now,
            ], true);

            if (! is_numeric($id) || (int) $id < 1) {
                throw new RuntimeException('Unable to save batch.');
            }

            service('auditPersistence')->record(
                $actor,
                'batch_created',
                'batch',
                (string) $id,
                null,
                null,
                ['name' => $name, 'is_active' => $isActive],
                null,
                $db,
            );

            $db->transCommit();

            return (int) $id;
        } catch (\Throwable $exception) {
            $db->transRollback();

            throw $exception;
        }
    }

    /** @return array<string, mixed> */
    public function editForm(int $id): array
    {
        $this->allow('batch.create');
        $batch = Database::connect('default')
            ->table('batches')
            ->where('id', $id)
            ->get()
            ->getRowArray();

        if (! is_array($batch)) {
            throw new RuntimeException('Not found.');
        }

        return ['batch' => $batch];
    }

    /** @param array<string, mixed> $input */
    public function update(int $id, array $input): void
    {
        $actor = $this->allow('batch.create');
        $db = Database::connect('default');
        $before = $db->table('batches')->where('id', $id)->get()->getRowArray();

        if (! is_array($before)) {
            throw new RuntimeException('Not found.');
        }

        $name = $this->name($input['name'] ?? null);
        $isActive = $this->activeValue($input['is_active'] ?? $before['is_active'] ?? 1);
        $this->assertUniqueName($db, $name, $id);
        $db->transBegin();

        try {
            if (! (new BatchModel())->update($id, [
                'name' => $name,
                'is_active' => $isActive,
                'updated_at' => gmdate('Y-m-d H:i:s'),
            ])) {
                throw new RuntimeException('Unable to update batch.');
            }

            service('auditPersistence')->record(
                $actor,
                'batch_updated',
                'batch',
                (string) $id,
                null,
                [
                    'name' => (string) $before['name'],
                    'is_active' => (int) ($before['is_active'] ?? 1),
                ],
                ['name' => $name, 'is_active' => $isActive],
                null,
                $db,
            );

            $db->transCommit();
        } catch (\Throwable $exception) {
            $db->transRollback();

            throw $exception;
        }
    }

    private function allow(string $permission): int
    {
        if (! service('authorization')->can($permission)) {
            throw new RuntimeException('Forbidden');
        }

        $user = session('user');
        $id = is_array($user) ? ($user['id'] ?? null) : null;

        if (! is_scalar($id) || ! ctype_digit((string) $id) || (int) $id < 1) {
            throw new RuntimeException('Forbidden');
        }

        return (int) $id;
    }

    private function assertUniqueName(object $db, string $name, ?int $ignoreId = null): void
    {
        $query = $db->table('batches')->where('name', $name);
        if ($ignoreId !== null) {
            $query->where('id !=', $ignoreId);
        }

        if ($query->countAllResults() > 0) {
            throw new RuntimeException('Batch already exists.');
        }
    }

    private function name(mixed $value): string
    {
        $name = trim((string) $value);

        if ($name === '' || strlen($name) > 150) {
            throw new RuntimeException('Invalid batch.');
        }

        return $name;
    }

    private function activeValue(mixed $value): int
    {
        return in_array((string) $value, ['0', '1'], true) ? (int) $value : 1;
    }
}
