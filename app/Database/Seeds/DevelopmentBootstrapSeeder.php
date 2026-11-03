<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Guarded entry point for the reusable synthetic development dataset.
 */
final class DevelopmentBootstrapSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(TestDatasetSeeder::class);
    }
}
