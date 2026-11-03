<?php

declare(strict_types=1);

namespace App\Services;

use App\Modules\Programs\Models\ProgramModel;
use Config\Database;
use RuntimeException;

final class ProgramManagementService
{
    private const DESCRIPTION_MAX_LENGTH = 5000;

    /** @return list<array<string, mixed>> */
    public function list(): array
    {
        $this->allow('program.view');

        return (new ProgramModel())
            ->orderBy('name', 'ASC')
            ->orderBy('id', 'DESC')
            ->findAll();
    }

    /** @param array<string, mixed> $input */
    public function create(array $input): int
    {
        $actor = $this->allow('program.create');
        $name = trim((string) ($input['name'] ?? ''));
        $description = $this->description($input['description'] ?? null);

        if ($name === '' || strlen($name) > 150) {
            throw new RuntimeException('Invalid program.');
        }

        $db = Database::connect('default');
        $this->assertUniqueName($db, $name);
        $db->transBegin();

        try {
            $now = gmdate('Y-m-d H:i:s');
            $model = new ProgramModel();
            $id = $model->insert([
                'name' => $name,
                'description' => $description,
                'created_by' => $actor,
                'created_at' => $now,
                'updated_at' => $now,
            ], true);

            if (! is_numeric($id) || (int) $id < 1) {
                throw new RuntimeException('Unable to save program.');
            }

            service('auditPersistence')->record(
                $actor,
                'program_created',
                'program',
                (string) $id,
                null,
                null,
                [
                    'name' => $name,
                    'description' => $description,
                ],
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
        $this->allow('program.create');
        $program = (new ProgramModel())->find($id);

        if (! is_array($program)) {
            throw new RuntimeException('Not found.');
        }

        return ['program' => $program];
    }

    /** @param array<string, mixed> $input */
    public function update(int $id, array $input): void
    {
        $actor = $this->allow('program.create');
        $model = new ProgramModel();
        $before = $model->find($id);

        if (! is_array($before)) {
            throw new RuntimeException('Not found.');
        }

        $name = trim((string) ($input['name'] ?? ''));
        $description = $this->description($input['description'] ?? null);
        if ($name === '' || strlen($name) > 150) {
            throw new RuntimeException('Invalid program.');
        }

        $db = Database::connect('default');
        $this->assertUniqueName($db, $name, $id);
        $db->transBegin();

        try {
            if (! $model->update($id, [
                'name' => $name,
                'description' => $description,
                'updated_at' => gmdate('Y-m-d H:i:s'),
            ])) {
                throw new RuntimeException('Unable to update program.');
            }

            service('auditPersistence')->record(
                $actor,
                'program_updated',
                'program',
                (string) $id,
                null,
                [
                    'name' => (string) $before['name'],
                    'description' => $before['description'] ?? null,
                ],
                [
                    'name' => $name,
                    'description' => $description,
                ],
                null,
                $db,
            );

            $db->transCommit();
        } catch (\Throwable $exception) {
            $db->transRollback();

            throw $exception;
        }
    }

    private function assertUniqueName(object $db, string $name, ?int $ignoreId = null): void
    {
        $query = $db->table('programs')->where('name', $name);
        if ($ignoreId !== null) {
            $query->where('id !=', $ignoreId);
        }

        if ($query->countAllResults() > 0) {
            throw new RuntimeException('Program already exists.');
        }
    }

    private function description(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (! is_string($value)) {
            throw new RuntimeException('Invalid program description.');
        }

        $description = trim($value);
        if (strlen($description) > self::DESCRIPTION_MAX_LENGTH) {
            throw new RuntimeException('Program description must not exceed 5,000 characters.');
        }

        return $description === '' ? null : $description;
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
}
