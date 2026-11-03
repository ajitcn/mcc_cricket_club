<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateIndividualSessionSkillCatalog extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => [
                'type' => 'BIGINT',
                'constraint' => 20,
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'area' => [
                'type' => 'VARCHAR',
                'constraint' => 40,
            ],
            'name' => [
                'type' => 'VARCHAR',
                'constraint' => 120,
            ],
            'display_order' => [
                'type' => 'INT',
                'constraint' => 10,
                'unsigned' => true,
            ],
            'version' => [
                'type' => 'INT',
                'constraint' => 10,
                'unsigned' => true,
                'default' => 1,
            ],
            'is_active' => [
                'type' => 'TINYINT',
                'constraint' => 1,
                'default' => 1,
            ],
            'retired_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
            ],
            'updated_at' => [
                'type' => 'DATETIME',
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['area', 'name', 'version']);
        $this->forge->addKey(['area', 'is_active', 'display_order']);
        $this->forge->createTable('session_skill_options', true);

        $this->forge->addColumn('sessions', [
            'skill_area' => [
                'type' => 'VARCHAR',
                'constraint' => 40,
                'null' => true,
                'after' => 'ground_id',
            ],
            'skill_option_id' => [
                'type' => 'BIGINT',
                'constraint' => 20,
                'unsigned' => true,
                'null' => true,
                'after' => 'skill_area',
            ],
            'skill_option_label' => [
                'type' => 'VARCHAR',
                'constraint' => 120,
                'null' => true,
                'after' => 'skill_option_id',
            ],
        ]);
        $this->db->query(
            'ALTER TABLE sessions ADD CONSTRAINT fk_sessions_skill_option '
            . 'FOREIGN KEY (skill_option_id) REFERENCES session_skill_options(id) '
            . 'ON UPDATE CASCADE ON DELETE RESTRICT'
        );

        $now = gmdate('Y-m-d H:i:s');
        $options = [
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
        $rows = [];

        foreach ($options as $area => $names) {
            foreach ($names as $index => $name) {
                $rows[] = [
                    'area' => $area,
                    'name' => $name,
                    'display_order' => $index + 1,
                    'version' => 1,
                    'is_active' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        $this->db->table('session_skill_options')->insertBatch($rows);
    }

    public function down(): void
    {
        $this->db->query('ALTER TABLE sessions DROP FOREIGN KEY fk_sessions_skill_option');
        $this->forge->dropColumn('sessions', ['skill_area', 'skill_option_id', 'skill_option_label']);
        $this->forge->dropTable('session_skill_options', true);
    }
}
