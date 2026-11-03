<?php

declare (strict_types=1);
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
final class AddCrmLeadClosedAt extends Migration
{
    public function up(): void
    {
        if (!$this->db->fieldExists('closed_at', 'leads')) {
            $this->forge->addColumn('leads', ['closed_at' => ['type' => 'DATETIME', 'null' => true, 'after' => 'lost_reason']]);
        }
    }
    public function down(): void
    {
        if ($this->db->fieldExists('closed_at', 'leads')) {
            $this->forge->dropColumn('leads', 'closed_at');
        }
    }
}
