<?php

declare (strict_types=1);
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
final class CreateSchedulingTablesDeployment extends Migration
{
    public function up(): void
    {
        require_once __DIR__ . '/202608290005_CreateSchedulingTables.php';
        (new CreateSchedulingTables())->up();
    }
    public function down(): void
    {
        (new CreateSchedulingTables())->down();
    }
}
