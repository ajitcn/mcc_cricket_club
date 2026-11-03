<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateAttendanceRosterSnapshotsDeployment extends Migration
{
    public function up(): void
    {
        require_once __DIR__ . '/202609020001_CreateAttendanceRosterSnapshots.php';
        (new CreateAttendanceRosterSnapshots())->up();
    }

    public function down(): void
    {
        // Deployment migrations are forward-only under the data-preservation policy.
    }
}
