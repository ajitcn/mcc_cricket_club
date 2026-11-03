<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Models;

use CodeIgniter\Model;

final class AttendanceRosterSnapshotModel extends Model
{
    protected $table = 'attendance_roster_snapshots';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'session_id',
        'branch_id',
        'captured_at',
    ];

    public function forSession(int $sessionId): ?array
    {
        $row = $this->db->table($this->table)
            ->where('session_id', $sessionId)
            ->get()
            ->getRowArray();

        return is_array($row) ? $row : null;
    }

    public function membersForSession(int $sessionId, int $branchId): array
    {
        return $this->db->table('attendance_roster_snapshot_members m')
            ->select('m.player_id, m.branch_id')
            ->join(
                'attendance_roster_snapshots s',
                's.id = m.snapshot_id'
            )
            ->where([
                's.session_id' => $sessionId,
                's.branch_id' => $branchId,
                'm.branch_id' => $branchId,
            ])
            ->orderBy('m.player_id', 'ASC')
            ->get()
            ->getResultArray();
    }
}
