<?php

declare (strict_types=1);
namespace App\Services;

use App\Modules\Admin\Models\AdminUserModel;
use App\Modules\Admin\Models\BranchModel;
use App\Modules\Admin\Models\RoleModel;
use App\Modules\CRM\Models\LeadSourceModel;
use App\Modules\CRM\Models\LeadStatusModel;
use Config\Database;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\Exceptions\DatabaseException;
use RuntimeException;
final class AdminService
{
    /** These identifiers are the permissions already used by the implemented application. */
    private const PERMISSIONS = [
        'admission.create', 'admission.update', 'admission.view',
        'attendance.correct', 'attendance.record', 'attendance.view',
        'lead.create', 'lead.update', 'lead.view', 'trial.book',
        'player.batch.assign', 'player.coach.assign', 'player.convert',
        'player.transfer', 'player.update', 'player.view', 'player360.view',
        'player.session_plan.view', 'player.session_plan.manage',
        'player.feedback.view', 'player.feedback.manage',
        'audit.view', 'rbac.manage',
        'session.cancel', 'session.create', 'session.update',
        'coach.view', 'coach.create', 'coach.update', 'coach.assign', 'coach.kpi.view',
        'development.view', 'development.create', 'development.update', 'development.progress',
        'fitness.view', 'fitness.create', 'fitness.update', 'fitness.plan.view',
        'fitness.plan.create', 'fitness.plan.update', 'fitness.kpi.view',
        'ground.view', 'ground.create', 'ground.update', 'ground.book', 'ground.maintenance',
        'match.view', 'match.create', 'match.update',
        'tournament.view', 'tournament.create', 'tournament.update',
        'parent_portal.view',
        'document.view', 'document.upload', 'document.download',
        'report.view', 'notification.view', 'notification.create', 'notification.read',
        'marketing.view',
        'equipment.view', 'equipment.create', 'equipment.update', 'equipment.delete',
        'program.view', 'program.create', 'batch.view', 'batch.create',
    ];
    private const PERMISSION_DESCRIPTIONS = ['admission.create' => 'Create admission intake', 'admission.update' => 'Update admission lifecycle',
        'admission.view' => 'View admissions', 'attendance.correct' => 'Correct attendance', 'attendance.record' => 'Record attendance',
            'attendance.view' => 'View attendance', 'lead.create' => 'Create CRM leads', 'lead.update' => 'Update CRM leads',
                'lead.view' => 'View CRM leads', 'trial.book' => 'Book eligible Trials',
                'player.batch.assign' => 'Assign players to batches',
                    'player.coach.assign' => 'Assign primary coaches', 'player.convert' => 'Convert approved admissions',
                        'player.transfer' => 'Transfer players between branches', 'player.update' => 'Update player profiles',
                            'player.view' => 'View players', 'audit.view' => 'View authorized audit records',
                                    'player.session_plan.view' => 'View assigned-player Session Plans',
                                    'player.session_plan.manage' => 'Manage assigned-player Session Plans',
                                    'player.feedback.view' => 'View Coach Feedback',
                                    'player.feedback.manage' => 'Manage Coach Feedback',
                                    'rbac.manage' => 'Manage users, roles, permissions, and branch access', 'session.cancel' => 'Cancel sessions',
                                    'session.create' => 'Create sessions', 'session.update' => 'Update and view sessions',
                                        'coach.view' => 'View coach profiles and assignments', 'coach.create' => 'Create coach profiles',
                                            'coach.update' => 'Update coach profiles', 'coach.assign' => 'Manage coach assignments',
                                                'coach.kpi.view' => 'View coach KPI summaries',
                                                'player360.view' => 'View consolidated player records',
                                                'development.view' => 'View player development records',
                                                'development.create' => 'Create development plans',
                                                'development.update' => 'Update development plans',
                                                'development.progress' => 'Record development progress',
                                                'fitness.view' => 'View fitness records',
                                                'fitness.create' => 'Create fitness assessments',
                                                'fitness.update' => 'Update fitness assessments',
                                                'fitness.plan.view' => 'View fitness plans',
                                                'fitness.plan.create' => 'Create fitness plans',
                                                'fitness.plan.update' => 'Update fitness plans',
                                                'fitness.kpi.view' => 'View fitness summaries',
                                                'ground.view' => 'View grounds',
                                                'ground.create' => 'Create grounds',
                                                'ground.update' => 'Update grounds',
                                                'ground.book' => 'Book grounds',
                                                'ground.maintenance' => 'Manage ground maintenance',
                                                'equipment.view' => 'View equipment inventory',
                                                'equipment.create' => 'Create equipment inventory records',
                                                'equipment.update' => 'Update equipment inventory records',
                                                'equipment.delete' => 'Soft-delete equipment inventory records',
                                                'program.view' => 'View Programs',
                                                'program.create' => 'Create Programs',
                                                'batch.view' => 'View Batches',
                                                'batch.create' => 'Create Batches',
                                                'match.view' => 'View matches',
                                                'match.create' => 'Create matches',
                                                'match.update' => 'Update matches',
                                                'tournament.view' => 'View tournaments',
                                                'tournament.create' => 'Create tournaments',
                                                'tournament.update' => 'Update tournaments',
                                                'parent_portal.view' => 'View parent portal',
                                                'document.view' => 'View document metadata',
                                                'document.upload' => 'Upload documents',
                                                'document.download' => 'Download documents',
                                                'report.view' => 'View reports',
                                                'notification.view' => 'View notifications',
                                                'notification.create' => 'Create notifications',
                                                'notification.read' => 'Mark notifications read',
                                                'marketing.view' => 'View marketing dashboard'];
    public function permissionCatalog(): array
    {
        $this->guard();
        $db = Database::connect('default');
        $rows = $db->table('role_permissions')->select('permission, COUNT(*) AS role_count')->groupBy('permission')->get()->getResultArray();
        $counts = [];
        foreach ($rows as $row) {
            $counts[(string) $row['permission']] = (int) $row['role_count'];
        }
        return array_map(static fn(string $permission): array => ['permission' => $permission, 'role_count' => $counts[$permission] ?? 0,
            'category' => ucfirst(strtok($permission, '.')),
                'description' => self::PERMISSION_DESCRIPTIONS[$permission] ?? 'Approved application permission'], self::PERMISSIONS);
    }
    public function users(?string $query = null): array
    {
        $this->guard();
        $model = (new AdminUserModel())
            ->select(
                'users.id,users.email,users.display_name,users.is_enabled,users.failed_login_count,'
                . 'users.session_version,users.locked_until,users.last_login_at,users.created_at,users.updated_at',
            )
            ->orderBy('display_name');
        if (service('branchContext')->isBranchAdminContext()) {
            $branchIds = service('branchContext')->authorizedBranchIds(false);
            if ($branchIds === []) {
                return [];
            }

            $model->distinct()
                ->join('user_branches ub', 'ub.user_id = users.id')
                ->whereIn('ub.branch_id', $branchIds);
        }
        $query = trim((string) $query);
        if ($query !== '') {
            $model->groupStart()->like('display_name', $query)->orLike('email', $query)->groupEnd();
        }
        $users = $model->findAll();
        foreach ($users as $index => $user) {
            $users[$index] = $this->withUserAssignments($user);
        }
        return $users;
    }
    public function statistics(): array
    {
        $this->guard();
        $db = Database::connect('default');
        return ['users' => (int) $db->table('users')->countAllResults(), 'enabled_users' => (int) $db->table('users')->where('is_enabled', 1)
            ->countAllResults(), 'disabled_users' => (int) $db->table('users')->where('is_enabled', 0)->countAllResults(), 'roles' => (int) $db
                ->table('roles')->countAllResults(), 'branches' => (int) $db->table('branches')->where('is_active', 1)->countAllResults()];
    }
    public function branches(): array
    {
        $this->guard();
        $ids = service('branchContext')->authorizedBranchIds(true);
        $model = new BranchModel();
        if ($ids === []) {
            return [];
        }
        return $model->whereIn('id', $ids)->where('is_active', 1)->orderBy('name')->findAll();
    }
    public function managedBranches(array $filters = []): array
    {
        $this->guard();
        $ids = service('branchContext')->authorizedBranchIds(false);
        if ($ids === []) {
            return [];
        }
        $model = new BranchModel();
        $model->whereIn('id', $ids);
        if (($filters['q'] ?? '') !== '') {
            $query = trim((string) $filters['q']);
            $model->groupStart()->like('name', $query)->orLike('code', $query)->orLike('address', $query)->groupEnd();
        }
        if (in_array($filters['status'] ?? '', ['active', 'inactive'], true)) {
            $model->where('is_active', $filters['status'] === 'active' ? 1 : 0);
        }
        return $model->orderBy('name')->findAll();
    }
    public function leadSources(bool $activeOnly = false): array
    {
        $this->guard();
        $model = new LeadSourceModel();
        if ($activeOnly) {
            $model->where('is_active', 1);
        }
        return $model->orderBy('sort_order')->orderBy('name')->findAll();
    }
    public function leadStatuses(bool $activeOnly = false): array
    {
        $this->guard();
        $model = new LeadStatusModel();
        if ($activeOnly) {
            $model->where('is_active', 1);
        }
        return $model->orderBy('sort_order')->orderBy('name')->findAll();
    }
    public function createLeadSource(string $name, int $sortOrder = 0): int
    {
        return $this->createLeadConfig(new LeadSourceModel(), 'lead_source', $name, $sortOrder);
    }
    public function createLeadStatus(string $name, int $sortOrder = 0): int
    {
        return $this->createLeadConfig(new LeadStatusModel(), 'lead_status', $name, $sortOrder);
    }
    public function updateLeadSource(int $id, string $name, int $sortOrder = 0): void
    {
        $this->updateLeadConfig(new LeadSourceModel(), 'lead_source', $id, $name, $sortOrder);
    }
    public function updateLeadStatus(int $id, string $name, int $sortOrder = 0): void
    {
        $this->updateLeadConfig(new LeadStatusModel(), 'lead_status', $id, $name, $sortOrder);
    }
    public function setLeadSourceActive(int $id, bool $active): void
    {
        $this->setLeadConfigActive(new LeadSourceModel(), 'lead_source', $id, $active);
    }
    public function setLeadStatusActive(int $id, bool $active): void
    {
        $this->setLeadConfigActive(new LeadStatusModel(), 'lead_status', $id, $active);
    }
    public function branch(int $id): array
    {
        $this->guard();
        if (! service('authorization')->canAccessBranch((string) $id)) {
            throw new RuntimeException('Forbidden');
        }
        $branch = (new BranchModel())->find($id);
        if (!is_array($branch)) {
            throw new RuntimeException('Not found.');
        }
        return $branch;
    }
    public function createBranch(array $input): int
    {
        $actor = $this->guard();
        if (array_diff(array_keys($input), ['name', 'code', 'address']) !== []) {
            throw new RuntimeException('Invalid branch details.');
        }

        $name = preg_replace('/\s+/', ' ', trim((string) ($input['name'] ?? '')));
        $code = strtoupper(trim((string) ($input['code'] ?? '')));
        $address = preg_replace('/\s+/', ' ', trim((string) ($input['address'] ?? '')));

        if (
            $name === null
            || $name === ''
            || strlen($name) > 120
            || $code === ''
            || strlen($code) > 80
            || $address === null
            || $address === ''
            || strlen($address) > 255
        ) {
            throw new RuntimeException('Invalid branch details.');
        }

        $model = new BranchModel();
        if (
            $model->where('name', $name)->first() !== null
            || $model->where('code', $code)->first() !== null
        ) {
            throw new RuntimeException('Branch already exists.');
        }

        $now = date('Y-m-d H:i:s');
        $db = Database::connect('default');
        $db->transStart();

        try {
            $model->insert([
                'name' => $name,
                'code' => $code,
                'address' => $address,
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $id = (int) $model->getInsertID();
            service('auditPersistence')->record(
                $actor,
                'branch_created',
                'branch',
                (string) $id,
                null,
                null,
                [
                    'name' => $name,
                    'code' => $code,
                    'address' => $address,
                    'is_active' => 1,
                ],
                null,
                $db,
            );

            if (service('branchContext')->isSuperAdmin()) {
                $this->persistBranchAssignment($actor, $actor, $id, true, $db);
            }

            $db->transComplete();

            if (! $db->transStatus()) {
                throw new RuntimeException('Unable to create branch.');
            }

            return $id;
        } catch (DatabaseException $exception) {
            $db->transRollback();

            if (str_contains(strtolower($exception->getMessage()), 'duplicate')) {
                throw new RuntimeException('Branch already exists.');
            }

            throw new RuntimeException('Unable to create branch.');
        } catch (RuntimeException $exception) {
            $db->transRollback();

            throw $exception;
        }
    }
    public function updateBranch(int $id, array $input): void
    {
        $actor = $this->guard();
        if ($id < 1 || array_diff(array_keys($input), ['name', 'code', 'address']) !== []) {
            throw new RuntimeException('Invalid branch details.');
        }
        $name = preg_replace('/\s+/', ' ', trim((string) ($input['name'] ?? '')));
        $code = strtoupper(trim((string) ($input['code'] ?? '')));
        $address = preg_replace('/\s+/', ' ', trim((string) ($input['address'] ?? '')));
        if (
            $name === null
            || $name === ''
            || strlen($name) > 120
            || $code === ''
            || strlen($code) > 80
            || $address === null
            || $address === ''
            || strlen($address) > 255
        ) {
            throw new RuntimeException('Invalid branch details.');
        }
        $model = new BranchModel();
        $before = $model->find($id);
        if (!is_array($before)) {
            throw new RuntimeException('Not found.');
        }
        if ($model->where('name', $name)->where('id !=', $id)->first() !== null || $model->where('code', $code)->where('id !=', $id)->first() !== null) {
            throw new RuntimeException('Branch already exists.');
        }
        try {
            $model->update($id, ['name' => $name, 'code' => $code, 'address' => $address, 'updated_at' => date('Y-m-d H:i:s')]);
        } catch (DatabaseException $exception) {
            if (str_contains(strtolower($exception->getMessage()), 'duplicate')) {
                throw new RuntimeException('Branch already exists.');
            }
            throw new RuntimeException('Unable to update branch.');
        }
        service('auditPersistence')->record($actor, 'branch_updated', 'branch', (string) $id, null, ['name' => $before['name'],
            'code' => $before['code'], 'address' => $before['address'] ?? null], ['name' => $name, 'code' => $code, 'address' => $address]);
    }
    public function setBranchActive(int $id, bool $active): void
    {
        $actor = $this->guard();
        $model = new BranchModel();
        $branch = $model->find($id);
        if (!is_array($branch)) {
            throw new RuntimeException('Not found.');
        }
        $current = (int) $branch['is_active'] === 1;
        if ($current === $active) {
            throw new RuntimeException('Branch status is already set.');
        }
        try {
            $model->update($id, ['is_active' => $active ? 1 : 0, 'updated_at' => date('Y-m-d H:i:s')]);
        } catch (DatabaseException) {
            throw new RuntimeException('Unable to update branch status.');
        }
        service('auditPersistence')->record($actor, $active ? 'branch_reactivated' : 'branch_deactivated', 'branch', (string) $id, null,
            ['is_active' => $current ? 1 : 0], ['is_active' => $active ? 1 : 0]);
    }
    public function user(int $id): array
    {
        $this->guard();
        $this->assertUserInBranchScope($id);
        $user = (new AdminUserModel())
            ->select(
                'id,email,display_name,is_enabled,failed_login_count,session_version,locked_until,last_login_at,created_at,updated_at',
            )
            ->find($id);
        if (! is_array($user)) {
            throw new RuntimeException('Not found.');
        }
        $user = $this->withUserAssignments($user);
        $db = Database::connect('default');
        $user['permissions'] = $db->table('role_permissions rp')
            ->select('rp.permission')
            ->join('user_roles ur', 'ur.role_id=rp.role_id')
            ->where('ur.user_id', $id)
            ->distinct()
            ->orderBy('rp.permission')
            ->get()
            ->getResultArray();
        return $user;
    }

    private function withUserAssignments(array $user): array
    {
        $db = Database::connect('default');
        $id = (int) $user['id'];
        $user['roles'] = $db->table('user_roles ur')
            ->select('r.id,r.name')
            ->join('roles r', 'r.id=ur.role_id')
            ->where('ur.user_id', $id)
            ->orderBy('r.name')
            ->get()
            ->getResultArray();
        $user['branches'] = $db->table('user_branches ub')
            ->select('b.id,b.name,b.code')
            ->join('branches b', 'b.id=ub.branch_id')
            ->where('ub.user_id', $id)
            ->orderBy('b.name')
            ->get()
            ->getResultArray();
        return $user;
    }
    public function createUser(array $input): int
    {
        $actor = $this->guard();
        $email = strtolower(trim((string) ($input['email'] ?? '')));
        $name = trim((string) ($input['display_name'] ?? ''));
        $password = (string) ($input['password'] ?? '');
        if ($name === '' || strlen($name) > 150 || filter_var($email,
            FILTER_VALIDATE_EMAIL) === false || strlen($email) > 190 || strlen($password) < 16 || strlen($password) > 1024) {
            throw new RuntimeException('Invalid user details.');
        }
        $now = date('Y-m-d H:i:s');
        $db = Database::connect('default');
        $db->transStart();
        $model = new AdminUserModel();
        if ($model->where('email', $email)->first() !== null) {
            throw new RuntimeException('Email already exists.');
        }
        $model->insert(['email' => $email, 'password_hash' => password_hash($password, PASSWORD_DEFAULT), 'display_name' => $name, 'is_enabled' => 1,
            'failed_login_count' => 0, 'session_version' => 1, 'locked_until' => null, 'last_login_at' => null, 'created_at' => $now, 'updated_at' => $now]);
        $id = (int) $model->getInsertID();
        service('auditPersistence')->record($actor, 'user_created', 'user', (string) $id, null, null, ['email' => $email, 'display_name' => $name]);
        $db->transComplete();
        $this->assertTransaction($db, 'Unable to create user.');
        return $id;
    }
    public function updateUser(int $id, array $input): void
    {
        $actor = $this->guard();
        $this->assertUserInBranchScope($id);
        $name = trim((string) ($input['display_name'] ?? ''));
        $email = strtolower(trim((string) ($input['email'] ?? '')));
        $newPassword = (string) ($input['new_password'] ?? '');
        if ($id < 1 || $name === '' || strlen($name) > 150 || filter_var($email, FILTER_VALIDATE_EMAIL) === false || strlen($email) > 190
            || ($newPassword !== '' && (strlen($newPassword) < 16 || strlen($newPassword) > 1024))) {
            throw new RuntimeException('Invalid user details.');
        }
        $model = new AdminUserModel();
        $before = $model->find($id);
        if (!is_array($before)) {
            throw new RuntimeException('Not found.');
        }
        $conflict = $model->where('email', $email)->where('id !=', $id)->first();
        if ($conflict !== null) {
            throw new RuntimeException('Email already exists.');
        }
        $updates = [
            'email' => $email,
            'display_name' => $name,
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        if ($newPassword !== '') {
            $updates['password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
            $updates['session_version'] = (int) $before['session_version'] + 1;
            $updates['failed_login_count'] = 0;
            $updates['locked_until'] = null;
        }
        $model->update($id, $updates);
        service('auditPersistence')->record($actor, 'user_updated', 'user', (string) $id, null, ['email' => $before['email'],
            'display_name' => $before['display_name']], ['email' => $email, 'display_name' => $name]);
    }
    public function setEnabled(int $id, bool $enabled): void
    {
        $actor = $this->guard();
        $this->assertUserInBranchScope($id);
        if ($id === $actor) {
            throw new RuntimeException('You cannot disable your own account.');
        }
        $model = new AdminUserModel();
        $user = $model->find($id);
        if (!is_array($user)) {
            throw new RuntimeException('Not found.');
        }
        $model->update($id, ['is_enabled' => $enabled ? 1 : 0, 'session_version' => (int) $user['session_version'] + 1, 'updated_at' => date('Y-m-d H:i:s')]);
        service('auditPersistence')->record($actor, $enabled ? 'user_enabled' : 'user_disabled', 'user', (string) $id, null,
            ['is_enabled' => (int) $user['is_enabled']], ['is_enabled' => $enabled ? 1 : 0]);
    }
    public function roles(): array
    {
        $this->guard();
        return (new RoleModel())->orderBy('name')->findAll();
    }
    public function role(int $id): array
    {
        $this->guard();
        $role = (new RoleModel())->find($id);
        if (!is_array($role)) {
            throw new RuntimeException('Not found.');
        }
        $db = Database::connect('default');
        $assignedPermissions = $db->table('role_permissions')
            ->select('permission')
            ->where('role_id', $id)
            ->orderBy('permission')
            ->get()
            ->getResultArray();
        $role['permissions'] = array_values(array_filter(
            $assignedPermissions,
            static fn (array $permission): bool => in_array($permission['permission'] ?? null, self::PERMISSIONS, true),
        ));
        $role['permission_version'] = $this->permissionVersion(array_column($assignedPermissions, 'permission'));
        $role['users'] = $db->table('user_roles ur')->select('u.id,u.email,u.display_name')->join('users u', 'u.id=ur.user_id')
            ->where('ur.role_id', $id)->orderBy('u.display_name')->get()->getResultArray();
        return $role;
    }
    public function createRole(string $name): int
    {
        $actor = $this->guard();
        $name = trim($name);
        if ($name === '' || strlen($name) > 80) {
            throw new RuntimeException('Invalid role name.');
        }
        $model = new RoleModel();
        if ($model->where('name', $name)->first() !== null) {
            throw new RuntimeException('Role already exists.');
        }
        $model->insert(['name' => $name, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
        $id = (int) $model->getInsertID();
        service('auditPersistence')->record($actor, 'role_created', 'role', (string) $id, null, null, ['name' => $name]);
        return $id;
    }
    public function updateRole(int $id, string $name): void
    {
        $actor = $this->guard();
        $name = trim($name);
        $model = new RoleModel();
        $before = $model->find($id);
        if (!is_array($before) || $name === '' || strlen($name) > 80) {
            throw new RuntimeException('Invalid role.');
        }
        if ($model->where('name', $name)->where('id !=', $id)->first() !== null) {
            throw new RuntimeException('Role already exists.');
        }
        $model->update($id, ['name' => $name, 'updated_at' => date('Y-m-d H:i:s')]);
        service('auditPersistence')->record($actor, 'role_updated', 'role', (string) $id, null, ['name' => $before['name']], ['name' => $name]);
    }
    public function assignRole(int $userId, int $roleId, bool $assign): void
    {
        $actor = $this->guard();
        $this->assertUserInBranchScope($userId);
        if ($userId < 1 || $roleId < 1 || $userId === $actor) {
            throw new RuntimeException('Self privilege changes are not allowed.');
        }
        $db = Database::connect('default');
        if ((new AdminUserModel())->find($userId) === null || (new RoleModel())->find($roleId) === null) {
            throw new RuntimeException('Not found.');
        }
        $exists = $db->table('user_roles')->where(['user_id' => $userId, 'role_id' => $roleId])->countAllResults() > 0;
        if ($exists === $assign) {
            throw new RuntimeException($assign ? 'Role already assigned.' : 'Role is not assigned.');
        }
        if ($assign) {
            $db->table('user_roles')->insert(['user_id' => $userId, 'role_id' => $roleId, 'created_at' => date('Y-m-d H:i:s')]);
        } else {
            $db->table('user_roles')->where(['user_id' => $userId, 'role_id' => $roleId])->delete();
        }
        service('auditPersistence')->record($actor, $assign ? 'role_assigned' : 'role_revoked', 'user', (string) $userId, null, null, ['role_id' => $roleId]);
    }
    public function assignPermission(int $roleId, string $permission, bool $assign): void
    {
        $actor = $this->guard();
        $permission = trim($permission);
        if (!in_array($permission, self::PERMISSIONS, true)) {
            throw new RuntimeException('Invalid permission.');
        }
        $role = $this->role($roleId);
        $db = Database::connect('default');
        $own = $db->table('user_roles')->where(['user_id' => $actor, 'role_id' => $roleId])->countAllResults() > 0;
        if ($own && $permission === 'rbac.manage') {
            throw new RuntimeException('Self privilege changes are not allowed.');
        }
        $exists = $db->table('role_permissions')->where(['role_id' => $roleId, 'permission' => $permission])->countAllResults() > 0;
        if ($exists === $assign) {
            throw new RuntimeException($assign ? 'Permission already assigned.' : 'Permission is not assigned.');
        }
        if ($assign) {
            $db->table('role_permissions')->insert(['role_id' => $roleId, 'permission' => $permission, 'created_at' => date('Y-m-d H:i:s')]);
        } else {
            $db->table('role_permissions')->where(['role_id' => $roleId, 'permission' => $permission])->delete();
        }
        service('auditPersistence')->record($actor, $assign ? 'permission_assigned' : 'permission_revoked', 'role', (string) $roleId, null, null,
            ['permission' => $permission]);
    }

    /**
     * Synchronize a role's approved permission set in one transaction.
     *
     * @param mixed $permissions
     */
    public function syncPermissions(int $roleId, mixed $permissions, string $expectedVersion): void
    {
        $actor = $this->guard();

        if ($roleId < 1 || trim($expectedVersion) === '') {
            throw new RuntimeException('Invalid permission selection.');
        }

        if (!is_array($permissions)) {
            throw new RuntimeException('Invalid permission selection.');
        }

        $selected = [];
        foreach ($permissions as $permission) {
            if (!is_string($permission)) {
                throw new RuntimeException('Invalid permission selection.');
            }

            $permission = trim($permission);
            if ($permission === '' || !in_array($permission, self::PERMISSIONS, true)) {
                throw new RuntimeException('Invalid permission.');
            }

            if (isset($selected[$permission])) {
                throw new RuntimeException('Duplicate permission selection.');
            }

            $selected[$permission] = true;
        }

        $role = (new RoleModel())->find($roleId);
        if (!is_array($role)) {
            throw new RuntimeException('Not found.');
        }

        $db = Database::connect('default');
        $currentRows = $db->table('role_permissions')
            ->select('permission')
            ->where('role_id', $roleId)
            ->orderBy('permission')
            ->get()
            ->getResultArray();
        $currentAll = array_values(array_filter(
            array_map(static fn (array $row): string => (string) ($row['permission'] ?? ''), $currentRows),
            static fn (string $permission): bool => $permission !== '',
        ));

        if (!hash_equals($this->permissionVersion($currentAll), $expectedVersion)) {
            throw new RuntimeException('Role permissions changed. Reload and try again.');
        }

        $currentApproved = array_values(array_filter(
            $currentAll,
            static fn (string $permission): bool => in_array($permission, self::PERMISSIONS, true),
        ));
        $desired = array_keys($selected);
        sort($currentApproved);
        sort($desired);
        $added = array_values(array_diff($desired, $currentApproved));
        $removed = array_values(array_diff($currentApproved, $desired));

        $ownsRole = $db->table('user_roles')
            ->where([
                'user_id' => $actor,
                'role_id' => $roleId,
            ])
            ->countAllResults() > 0;
        if ($ownsRole && in_array('rbac.manage', array_merge($added, $removed), true)) {
            throw new RuntimeException('Self privilege changes are not allowed.');
        }

        if ($added === [] && $removed === []) {
            return;
        }

        $db->transBegin();

        try {
            foreach ($removed as $permission) {
                $db->table('role_permissions')
                    ->where([
                        'role_id' => $roleId,
                        'permission' => $permission,
                    ])
                    ->delete();
            }

            foreach ($added as $permission) {
                $db->table('role_permissions')->insert([
                    'role_id' => $roleId,
                    'permission' => $permission,
                    'created_at' => gmdate('Y-m-d H:i:s'),
                ]);
            }

            service('auditPersistence')->record(
                $actor,
                'role_permissions_updated',
                'role',
                (string) $roleId,
                null,
                [
                    'permissions' => $currentApproved,
                ],
                [
                    'permissions' => $desired,
                    'added' => $added,
                    'removed' => $removed,
                ],
                null,
                $db,
            );

            if ($db->transStatus() === false) {
                throw new RuntimeException('Unable to update role permissions.');
            }

            $db->transCommit();
        } catch (\Throwable $exception) {
            $db->transRollback();

            throw $exception;
        }
    }

    /** @param list<string> $permissions */
    private function permissionVersion(array $permissions): string
    {
        $normalized = array_values(array_unique(array_map('strval', $permissions)));
        sort($normalized);

        return hash('sha256', json_encode($normalized, JSON_THROW_ON_ERROR));
    }

    public function assignBranch(int $userId, int $branchId, bool $assign): void
    {
        $actor = $this->guard();
        $this->assertUserInBranchScope($userId);

        if ($userId < 1 || $branchId < 1) {
            throw new RuntimeException('Invalid branch assignment.');
        }

        $db = Database::connect('default');
        $mayAssignOwnCreatedBranch = $assign
            && $userId === $actor
            && service('branchContext')->isSuperAdmin()
            && $this->wasCreatedBy($actor, $branchId, $db);

        if (
            ! service('authorization')->canAccessBranch((string) $branchId)
            && ! $mayAssignOwnCreatedBranch
        ) {
            throw new RuntimeException('Branch is not authorized.');
        }

        if (
            (new AdminUserModel())->find($userId) === null
            || (new BranchModel())->find($branchId) === null
        ) {
            throw new RuntimeException('Not found.');
        }

        $this->persistBranchAssignment($actor, $userId, $branchId, $assign, $db);
    }

    private function wasCreatedBy(int $userId, int $branchId, BaseConnection $db): bool
    {
        return $db->table('audit_logs')
            ->where([
                'actor_user_id' => $userId,
                'action' => 'branch_created',
                'entity_type' => 'branch',
                'entity_id' => (string) $branchId,
            ])
            ->countAllResults() === 1;
    }

    private function persistBranchAssignment(
        int $actor,
        int $userId,
        int $branchId,
        bool $assign,
        BaseConnection $db,
    ): void {
        $exists = $db->table('user_branches')
            ->where([
                'user_id' => $userId,
                'branch_id' => $branchId,
            ])
            ->countAllResults() > 0;

        if ($exists === $assign) {
            throw new RuntimeException($assign ? 'Branch already assigned.' : 'Branch is not assigned.');
        }

        if ($assign) {
            $db->table('user_branches')->insert([
                'user_id' => $userId,
                'branch_id' => $branchId,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } else {
            $db->table('user_branches')
                ->where(['user_id' => $userId, 'branch_id' => $branchId])
                ->delete();
        }

        service('auditPersistence')->record(
            $actor,
            $assign ? 'branch_assigned' : 'branch_revoked',
            'user',
            (string) $userId,
            $branchId,
            null,
            ['branch_id' => $branchId],
            null,
            $db,
        );
    }
    private function guard(): int
    {
        $user = session('user');
        if (!is_array($user) || !isset($user['id']) || !service('authorization')->can('rbac.manage')) {
            throw new RuntimeException('Forbidden');
        }
        return (int) $user['id'];
    }

    private function assertUserInBranchScope(int $userId): void
    {
        if (! service('branchContext')->isBranchAdminContext()) {
            return;
        }

        $branchIds = service('branchContext')->authorizedBranchIds(false);
        if ($userId < 1 || $branchIds === []) {
            throw new RuntimeException('Forbidden');
        }

        $visible = Database::connect('default')
            ->table('user_branches')
            ->where('user_id', $userId)
            ->whereIn('branch_id', $branchIds)
            ->countAllResults() > 0;
        if (! $visible) {
            throw new RuntimeException('Forbidden');
        }
    }
    private function createLeadConfig(object $model, string $entity, string $name, int $sortOrder): int
    {
        $actor = $this->guard();
        $name = trim($name);
        if ($name === '' || strlen($name) > ($entity === 'lead_status' ? 40 : 100) || $sortOrder < 0) {
            throw new RuntimeException('Invalid lead configuration.');
        }
        if ($model->where('name', $name)->first() !== null) {
            throw new RuntimeException('Lead configuration already exists.');
        }
        $now = date('Y-m-d H:i:s');
        $model->insert(['name' => $name, 'is_active' => 1, 'sort_order' => $sortOrder, 'created_at' => $now, 'updated_at' => $now]);
        $id = (int) $model->getInsertID();
        service('auditPersistence')->record($actor, $entity . '_created', $entity, (string) $id, null, null, ['name' => $name,
            'sort_order' => $sortOrder, 'is_active' => 1]);
        return $id;
    }
    private function updateLeadConfig(object $model, string $entity, int $id, string $name, int $sortOrder): void
    {
        $actor = $this->guard();
        $name = trim($name);
        $before = $model->find($id);
        if (!is_array($before) || $name === '' || strlen($name) > ($entity === 'lead_status' ? 40 : 100) || $sortOrder < 0) {
            throw new RuntimeException('Invalid lead configuration.');
        }
        if ($model->where('name', $name)->where('id !=', $id)->first() !== null) {
            throw new RuntimeException('Lead configuration already exists.');
        }
        $model->update($id, ['name' => $name, 'sort_order' => $sortOrder, 'updated_at' => date('Y-m-d H:i:s')]);
        service('auditPersistence')->record($actor, $entity . '_updated', $entity, (string) $id, null, ['name' => $before['name'],
            'sort_order' => (int) $before['sort_order']], ['name' => $name, 'sort_order' => $sortOrder]);
    }
    private function setLeadConfigActive(object $model, string $entity, int $id, bool $active): void
    {
        $actor = $this->guard();
        $before = $model->find($id);
        if (!is_array($before) || ((int) $before['is_active'] === 1) === $active) {
            throw new RuntimeException('Lead configuration status is already set.');
        }
        $model->update($id, ['is_active' => $active ? 1 : 0, 'updated_at' => date('Y-m-d H:i:s')]);
        service('auditPersistence')->record($actor, $entity . ($active ? '_reactivated' : '_deactivated'), $entity, (string) $id, null,
            ['is_active' => (int) $before['is_active']], ['is_active' => $active ? 1 : 0]);
    }
    private function assertTransaction(object $db, string $message): void
    {
        if (!$db->transStatus()) {
            throw new RuntimeException($message);
        }
    }
}
