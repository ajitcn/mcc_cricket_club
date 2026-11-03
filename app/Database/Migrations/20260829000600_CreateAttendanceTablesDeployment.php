<?php

declare (strict_types=1);
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
final class CreateAttendanceTablesDeployment extends Migration
{
    public function up(): void
    {
        require_once __DIR__ . '/202608290006_CreateAttendanceTables.php';
        (new CreateAttendanceTables())->up();
    }
    public function down(): void
    {
        (new CreateAttendanceTables())->down();
    }
}
