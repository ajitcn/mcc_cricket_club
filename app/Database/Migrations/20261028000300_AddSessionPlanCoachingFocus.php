<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddSessionPlanCoachingFocus extends Migration
{
    public function up(): void
    {
        if ($this->db->tableExists('player_session_plans')
            && ! $this->db->fieldExists('coaching_focus', 'player_session_plans')) {
            $this->forge->addColumn('player_session_plans', [
                'coaching_focus' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
            ]);
        }
    }

    public function down(): void
    {
        // Forward-only: existing Session Plan history must remain readable.
    }
}
