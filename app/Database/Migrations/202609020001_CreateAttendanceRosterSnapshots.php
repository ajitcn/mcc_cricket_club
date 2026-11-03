<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateAttendanceRosterSnapshots extends Migration
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
            'session_id' => [
                'type' => 'BIGINT',
                'constraint' => 20,
                'unsigned' => true,
            ],
            'branch_id' => [
                'type' => 'BIGINT',
                'constraint' => 20,
                'unsigned' => true,
            ],
            'captured_at' => [
                'type' => 'DATETIME',
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('session_id', 'uq_attendance_roster_snapshots_session');
        $this->forge->addKey(['branch_id', 'session_id']);
        $this->forge->addForeignKey('session_id', 'sessions', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('branch_id', 'branches', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('attendance_roster_snapshots', true);

        $this->forge->addField([
            'id' => [
                'type' => 'BIGINT',
                'constraint' => 20,
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'snapshot_id' => [
                'type' => 'BIGINT',
                'constraint' => 20,
                'unsigned' => true,
            ],
            'player_id' => [
                'type' => 'BIGINT',
                'constraint' => 20,
                'unsigned' => true,
            ],
            'branch_id' => [
                'type' => 'BIGINT',
                'constraint' => 20,
                'unsigned' => true,
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['snapshot_id', 'player_id'], 'uq_attendance_roster_snapshot_player');
        $this->forge->addKey(['branch_id', 'player_id']);
        $this->forge->addForeignKey('snapshot_id', 'attendance_roster_snapshots', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('player_id', 'players', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('branch_id', 'branches', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('attendance_roster_snapshot_members', true);

        $snapshotTriggers = $this->db->query(
            "SHOW TRIGGERS LIKE 'attendance_roster_snapshots'"
        )->getResultArray();
        $snapshotTriggerNames = array_map(
            static fn(array $row): string => (string) ($row['Trigger'] ?? ''),
            $snapshotTriggers
        );
        if (! in_array('attendance_roster_snapshots_no_update', $snapshotTriggerNames, true)) {
            $this->db->query(
                "CREATE TRIGGER attendance_roster_snapshots_no_update "
                . "BEFORE UPDATE ON attendance_roster_snapshots FOR EACH ROW "
                . "SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Attendance roster snapshots are immutable'"
            );
        }
        if (! in_array('attendance_roster_snapshots_no_delete', $snapshotTriggerNames, true)) {
            $this->db->query(
                "CREATE TRIGGER attendance_roster_snapshots_no_delete "
                . "BEFORE DELETE ON attendance_roster_snapshots FOR EACH ROW "
                . "SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Attendance roster snapshots are immutable'"
            );
        }

        $memberTriggers = $this->db->query(
            "SHOW TRIGGERS LIKE 'attendance_roster_snapshot_members'"
        )->getResultArray();
        $memberTriggerNames = array_map(
            static fn(array $row): string => (string) ($row['Trigger'] ?? ''),
            $memberTriggers
        );
        if (! in_array('attendance_roster_snapshot_members_no_update', $memberTriggerNames, true)) {
            $this->db->query(
                "CREATE TRIGGER attendance_roster_snapshot_members_no_update "
                . "BEFORE UPDATE ON attendance_roster_snapshot_members FOR EACH ROW "
                . "SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Attendance roster snapshot members are immutable'"
            );
        }
        if (! in_array('attendance_roster_snapshot_members_no_delete', $memberTriggerNames, true)) {
            $this->db->query(
                "CREATE TRIGGER attendance_roster_snapshot_members_no_delete "
                . "BEFORE DELETE ON attendance_roster_snapshot_members FOR EACH ROW "
                . "SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Attendance roster snapshot members are immutable'"
            );
        }
    }

    public function down(): void
    {
        // Snapshot history is permanent and rollback is intentionally unavailable.
    }
}
