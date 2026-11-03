<?php

declare (strict_types=1);
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
final class AddApprovedAdmissionLifecycleDeployment extends Migration
{
    public function up(): void
    {
        require_once __DIR__ . '/202608290002_AddApprovedAdmissionLifecycle.php';
        (new AddApprovedAdmissionLifecycle())->up();
    }
    public function down(): void
    {
        (new AddApprovedAdmissionLifecycle())->down();
    }
}
