<?php

declare (strict_types=1);
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
final class CreatePlayerAssignmentTablesDeployment extends Migration
{
    public function up(): void
    {
        require_once __DIR__ . '/202608290004_CreatePlayerAssignmentTables.php';
        (new CreatePlayerAssignmentTables())->up();
    }
    public function down(): void
    {
        (new CreatePlayerAssignmentTables())->down();
    }
}
