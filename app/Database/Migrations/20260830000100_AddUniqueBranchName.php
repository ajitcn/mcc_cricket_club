<?php

declare (strict_types=1);
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
final class AddUniqueBranchName extends Migration
{
    public function up(): void
    {
        $this->forge->addUniqueKey('name', 'uq_branches_name');
        $this->forge->processIndexes('branches');
    }
    public function down(): void
    {
        $this->forge->dropKey('branches', 'uq_branches_name');
    }
}
