<?php

declare (strict_types=1);
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
final class AddCrmLeadClosedAtDeployment extends Migration
{
    public function up(): void
    {
        require_once __DIR__ . '/202608290001_AddCrmLeadClosedAt.php';
        (new AddCrmLeadClosedAt())->up();
    }
    public function down(): void
    {
        (new AddCrmLeadClosedAt())->down();
    }
}
