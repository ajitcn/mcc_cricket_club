<?php

declare (strict_types=1);
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
final class AddBranchAddress extends Migration
{
    public function up(): void
    {
        if (! $this->db->fieldExists('address', 'branches')) {
            $this->forge->addColumn('branches', [
                'address' => [
                    'type' => 'VARCHAR',
                    'constraint' => 255,
                    'null' => true,
                    'after' => 'code',
                ],
            ]);
        }
    }

    public function down(): void
    {
        if ($this->db->fieldExists('address', 'branches')) {
            $this->forge->dropColumn('branches', 'address');
        }
    }
}
