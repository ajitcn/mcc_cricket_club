<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddMatchPlayerFieldingDetails extends Migration
{
    public function up(): void
    {
        $fields = $this->db->getFieldNames('match_player_performances');
        $add = [];

        if (!in_array('fielding_status', $fields, true)) {
            $add['fielding_status'] = ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true];
            $add['catches'] = ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true];
            $add['stumpings'] = ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true];
            $add['run_outs_completed'] = ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true];
            $add['run_out_assists'] = ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true];
            $add['direct_hit_run_outs'] = ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true];
            $add['dropped_catches'] = ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true];
            $add['fielding_position'] = ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true];
            $add['fielding_notes'] = ['type' => 'TEXT', 'null' => true];
        }

        if ($add !== []) {
            $this->forge->addColumn('match_player_performances', $add);
        }
    }

    public function down(): void
    {
        // Forward-only: Match Player Performance history must not be removed by rollback.
    }
}
