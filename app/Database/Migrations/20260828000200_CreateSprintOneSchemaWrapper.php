<?php

declare (strict_types=1);
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
/**
 * Makes the existing Sprint 1 schema migration discoverable by CodeIgniter.
 * The original file predates the required full timestamp filename format.
 */
final class CreateSprintOneSchemaWrapper extends Migration
{
    public function up(): void
    {
        require_once __DIR__ . '/202608280001_CreateSprintOneSchema.php';
        (new CreateSprintOneSchema())->up();
    }
    public function down(): void
    {
        (new CreateSprintOneSchema())->down();
    }
}
