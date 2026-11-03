<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Models;

use CodeIgniter\Model;

final class AttendanceRosterSnapshotMemberModel extends Model
{
    protected $table = 'attendance_roster_snapshot_members';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'snapshot_id',
        'player_id',
        'branch_id',
    ];
}
