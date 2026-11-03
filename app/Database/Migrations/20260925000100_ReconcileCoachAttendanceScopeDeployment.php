<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class ReconcileCoachAttendanceScopeDeployment extends Migration
{
    public function up(): void
    {
        require_once __DIR__ . '/202609250001_ReconcileCoachAttendanceScope.php';
        (new ReconcileCoachAttendanceScope())->up();
    }

    public function down(): void
    {
        // Deployment migrations are forward-only under the data-preservation policy.
    }
}
