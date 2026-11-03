<?php

declare(strict_types=1);

namespace App\Modules\Admin\Models;

use CodeIgniter\Model;

final class AdminUserModel extends Model
{
    protected $table = 'users';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = ['email', 'password_hash', 'display_name', 'is_enabled', 'failed_login_count', 'session_version', 'locked_until', 'last_login_at', 'created_at', 'updated_at'];
}
