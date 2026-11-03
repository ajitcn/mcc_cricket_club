<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Models;

use CodeIgniter\Model;
use LogicException;

final class AttendanceHistoryModel extends Model
{
    protected $table = 'attendance_history';

    protected $primaryKey = 'id';

    protected $returnType = 'array';

    protected $allowedFields = [
        'attendance_id',
        'prior_status',
        'new_status',
        'actor_user_id',
        'reason',
        'changed_at',
        'prior_version',
        'new_version',
    ];

    public function update($id = null, $row = null): bool
    {
        throw new LogicException('Attendance history is append-only.');
    }

    public function delete($id = null, bool $purge = false): bool
    {
        throw new LogicException('Attendance history is append-only.');
    }
}
