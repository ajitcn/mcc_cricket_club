<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class ReconcileSessionSkillCatalog extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('session_skill_options')) {
            return;
        }

        $catalog = [
            'batting' => [
                'Front-foot drive', 'Back-foot defence', 'Pull shot', 'Cut shot',
                'Sweep shot', 'Running between wickets',
            ],
            'bowling' => [
                'Line and length', 'Seam movement', 'Swing control', 'Spin control',
                'Yorker', 'Bouncer',
            ],
            'fielding' => [
                'Ground fielding', 'Catching', 'Throwing accuracy', 'High catches',
                'Boundary fielding', 'Direct hit',
            ],
            'wicketkeeping' => [
                'Stance and footwork', 'Taking behind the stumps', 'Glovework',
                'Standing up to the stumps', 'Standing back', 'Stumping technique',
            ],
            'fitness' => [
                'Speed and agility', 'Strength', 'Endurance', 'Mobility',
                'Core stability', 'Recovery',
            ],
            'game_awareness' => [
                'Shot selection', 'Strike rotation', 'Field placement', 'Bowling plans',
                'Match awareness', 'Decision-making',
            ],
        ];
        $now = gmdate('Y-m-d H:i:s');

        foreach ($catalog as $area => $names) {
            foreach ($names as $index => $name) {
                $exists = $this->db->table('session_skill_options')
                    ->where([
                        'area' => $area,
                        'name' => $name,
                        'version' => 1,
                    ])
                    ->countAllResults();
                if ($exists > 0) {
                    continue;
                }

                $this->db->table('session_skill_options')->insert([
                    'area' => $area,
                    'name' => $name,
                    'display_order' => $index + 1,
                    'version' => 1,
                    'is_active' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        // Forward-only catalog reconciliation. Existing taxonomy records are preserved.
    }
}
