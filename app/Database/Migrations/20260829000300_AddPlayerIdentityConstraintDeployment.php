<?php

declare (strict_types=1);
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
final class AddPlayerIdentityConstraintDeployment extends Migration
{
    public function up(): void
    {
        require_once __DIR__ . '/202608290003_AddPlayerIdentityConstraint.php';
        (new AddPlayerIdentityConstraint())->up();
    }
    public function down(): void
    {
        (new AddPlayerIdentityConstraint())->down();
    }
}
