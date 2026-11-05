<?php

declare (strict_types=1);
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
final class AddUniqueBranchName extends Migration
{
    public function up(): void
    {
        if (! $this->branchNameIndexExists()) {
            $this->forge->addUniqueKey('name', 'uq_branches_name');
            $this->forge->processIndexes('branches');
        }
    }
    public function down(): void
    {
        if ($this->namedIndexExists('uq_branches_name')) {
            $this->forge->dropKey('branches', 'uq_branches_name');
        }
    }

    private function branchNameIndexExists(): bool
    {
        $indexes = $this->db->query('SHOW INDEX FROM branches')->getResultArray();
        $columnsByKey = [];

        foreach ($indexes as $index) {
            $keyName = (string) ($index['Key_name'] ?? '');
            $columnName = (string) ($index['Column_name'] ?? '');

            if ($keyName === '' || $columnName === '') {
                continue;
            }

            $columnsByKey[$keyName][] = [
                'column' => $columnName,
                'unique' => (int) ($index['Non_unique'] ?? 1) === 0,
            ];
        }

        foreach ($columnsByKey as $columns) {
            if (count($columns) === 1
                && $columns[0]['unique'] === true
                && $columns[0]['column'] === 'name') {
                return true;
            }
        }

        return false;
    }

    private function namedIndexExists(string $name): bool
    {
        $escapedName = $this->db->escape($name);

        return $this->db->query(
            'SHOW INDEX FROM branches WHERE Key_name = ' . $escapedName
        )->getNumRows() > 0;
    }
}
