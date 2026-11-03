<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use Config\Database;
use RuntimeException;
use Throwable;

/**
 * Restores role-permission and user-role mappings recorded by the project.
 * Equipment mappings reflect a limited user direction, not client approval.
 *
 * This seeder never creates or changes users, roles, branches, or business
 * records. It inserts only missing rows in role_permissions and user_roles.
 */
final class AuthorizationRestoreSeeder extends Seeder
{
    /** @var array<string, list<string>> */
    private const ROLE_PERMISSIONS = [
        'Super Admin' => [
            'lead.view',
            'lead.create',
            'lead.update',
            'trial.book',
            'admission.view',
            'admission.create',
            'admission.update',
            'player.view',
            'player.update',
            'player.transfer',
            'player.convert',
            'player.batch.assign',
            'player.coach.assign',
            'player.goal.set',
            'player.session_plan.view',
            'player.session_plan.manage',
            'player.feedback.view',
            'player.feedback.manage',
            'session.create',
            'session.view',
            'session.update',
            'session.cancel',
            'attendance.view',
            'attendance.record',
            'attendance.correct',
            'rbac.manage',
            'audit.view',
            'coach.view',
            'coach.create',
            'coach.update',
            'coach.assign',
            'coach.kpi.view',
            'marketing.view',
            'player360.view',
            'development.view',
            'development.create',
            'development.update',
            'development.progress',
            'performance.view',
            'performance.create',
            'performance.update',
            'performance.delete',
            'fitness.view',
            'fitness.create',
            'fitness.update',
            'fitness.plan.view',
            'fitness.plan.create',
            'fitness.plan.update',
            'fitness.kpi.view',
            'ground.view',
            'ground.create',
            'ground.update',
            'ground.book',
            'ground.maintenance',
            'equipment.view',
            'equipment.create',
            'equipment.update',
            'equipment.delete',
            'program.view',
            'program.create',
            'batch.view',
            'batch.create',
            'match.view',
            'match.create',
            'match.update',
            'tournament.view',
            'tournament.create',
            'tournament.update',
            'opponent.view',
            'opponent.create',
            'opponent.update',
            'achievement.view',
            'achievement.create',
            'document.view',
            'document.upload',
            'document.download',
            'report.view',
            'notification.view',
            'notification.create',
            'notification.read',
        ],
        'Branch Admin' => [
            'lead.view',
            'lead.create',
            'lead.update',
            'trial.book',
            'admission.view',
            'admission.create',
            'admission.update',
            'player.view',
            'player.update',
            'player.transfer',
            'player.convert',
            'player.batch.assign',
            'player.coach.assign',
            'player.goal.set',
            'player.session_plan.view',
            'player.session_plan.manage',
            'player.feedback.view',
            'player.feedback.manage',
            'session.create',
            'session.view',
            'session.update',
            'session.cancel',
            'attendance.view',
            'attendance.record',
            'attendance.correct',
            'rbac.manage',
            'audit.view',
            'coach.view',
            'coach.create',
            'coach.update',
            'coach.assign',
            'coach.kpi.view',
            'marketing.view',
            'player360.view',
            'development.view',
            'development.create',
            'development.update',
            'development.progress',
            'performance.view',
            'performance.create',
            'performance.update',
            'performance.delete',
            'fitness.view',
            'fitness.create',
            'fitness.update',
            'fitness.plan.view',
            'fitness.plan.create',
            'fitness.plan.update',
            'fitness.kpi.view',
            'ground.view',
            'ground.create',
            'ground.update',
            'ground.book',
            'ground.maintenance',
            'equipment.view',
            'equipment.create',
            'equipment.update',
            'equipment.delete',
            'program.view',
            'program.create',
            'batch.view',
            'batch.create',
            'match.view',
            'match.create',
            'match.update',
            'tournament.view',
            'tournament.create',
            'tournament.update',
            'opponent.view',
            'opponent.create',
            'opponent.update',
            'achievement.view',
            'achievement.create',
            'document.view',
            'document.upload',
            'document.download',
            'report.view',
            'notification.view',
            'notification.create',
            'notification.read',
        ],
        'Admission Team' => [
            'lead.view',
            'lead.create',
            'lead.update',
            'trial.book',
            'admission.view',
            'admission.create',
            'admission.update',
            'player.view',
            'player.convert',
            'player.batch.assign',
            'player.coach.assign',
            'document.view',
            'document.upload',
            'document.download',
            'marketing.view',
        ],
        'Coach' => [
            'player.view',
            'player.goal.set',
            'player.session_plan.view',
            'player.session_plan.manage',
            'player.feedback.view',
            'player.feedback.manage',
            'session.view',
            'session.update',
            'attendance.view',
            'attendance.record',
            'attendance.correct',
            'coach.view',
            'coach.kpi.view',
            'player360.view',
            'development.view',
            'development.create',
            'development.update',
            'development.progress',
            'performance.view',
            'performance.create',
            'performance.update',
            'performance.delete',
            'fitness.view',
            'fitness.create',
            'fitness.update',
            'fitness.plan.view',
            'fitness.plan.create',
            'fitness.plan.update',
            'fitness.kpi.view',
            'ground.view',
            'ground.book',
            'match.view',
            'match.create',
            'match.update',
            'tournament.view',
            'tournament.create',
            'tournament.update',
            'opponent.view',
            'program.view',
            'program.create',
            'document.view',
            'document.upload',
            'document.download',
            'report.view',
            'notification.view',
            'notification.create',
            'notification.read',
        ],
        'Fitness Coach' => [
            'player.view',
            'attendance.view',
            'fitness.view',
            'fitness.create',
            'fitness.update',
            'fitness.plan.view',
            'fitness.plan.create',
            'fitness.plan.update',
            'fitness.kpi.view',
            'document.view',
            'document.upload',
            'document.download',
            'ground.view',
        ],
        'Nutritionist' => [
            'player.view',
        ],
        'Reception' => [
            'lead.view',
            'admission.view',
            'player.view',
        ],
        'Parent' => [
            'player.view',
            'parent_portal.view',
        ],
        'Player' => [
            'player.view',
        ],
    ];

    /** @var array<string, string> */
    private const USER_ROLES = [
        'test.mcc.superadmin@example.invalid' => 'Super Admin',
        'test.mcc.manager.vns@example.invalid' => 'Branch Admin',
        'test.mcc.staff.del@example.invalid' => 'Admission Team',
        'test.mcc.coach@example.invalid' => 'Coach',
        'test.mcc.disabled@example.invalid' => 'Coach',
        'test.mcc.fitness@example.invalid' => 'Fitness Coach',
        'test.mcc.nutritionist@example.invalid' => 'Nutritionist',
        'test.mcc.reception@example.invalid' => 'Reception',
        'test.mcc.parent@example.invalid' => 'Parent',
        'test.mcc.player@example.invalid' => 'Player',
    ];

    /** @var array<string, int> */
    private array $inserted = [
        'role_permissions' => 0,
        'user_roles' => 0,
    ];

    /** @var array<string, int> */
    private array $skipped = [
        'role_permissions' => 0,
        'user_roles' => 0,
    ];

    /**
     * Restore approved authorization mappings in the test database only.
     *
     * No table other than role_permissions and user_roles is written.
     */
    public function run(): void
    {
        $db = Database::connect('tests');
        $database = strtolower((string) $db->getDatabase());

        if ($database !== 'mcc_cricket_club_test') {
            throw new RuntimeException(
                'Refusing authorization restore outside mcc_cricket_club_test.',
            );
        }

        $roles = $this->findRoles($db);
        $users = $this->findUsers($db);

        $db->transBegin();

        try {
            $this->restoreRolePermissions($db, $roles);
            $this->restoreUserRoles($db, $roles, $users);

            if ($db->transStatus() === false) {
                throw new RuntimeException(
                    'Authorization restore transaction failed.',
                );
            }

            $db->transCommit();
        } catch (Throwable $exception) {
            $db->transRollback();

            throw $exception;
        }

        echo json_encode([
            'database' => 'mcc_cricket_club_test',
            'inserted' => $this->inserted,
            'skipped' => $this->skipped,
            'writable_tables' => [
                'role_permissions',
                'user_roles',
            ],
        ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT) . PHP_EOL;
    }

    /** @return array<string, int> */
    private function findRoles(object $db): array
    {
        $roles = [];

        foreach (array_keys(self::ROLE_PERMISSIONS) as $name) {
            $row = $db->table('roles')
                ->select('id')
                ->where('name', $name)
                ->get()
                ->getRowArray();

            if (! is_array($row)) {
                throw new RuntimeException(
                    'Expected role is missing: ' . $name,
                );
            }

            $roles[$name] = (int) $row['id'];
        }

        return $roles;
    }

    /** @return array<string, int> */
    private function findUsers(object $db): array
    {
        $users = [];

        foreach (self::USER_ROLES as $email => $role) {
            $row = $db->table('users')
                ->select('id')
                ->where('email', $email)
                ->get()
                ->getRowArray();

            if (! is_array($row)) {
                throw new RuntimeException(
                    'Expected user is missing: ' . $email,
                );
            }

            $users[$email] = (int) $row['id'];
        }

        return $users;
    }

    /** @param array<string, int> $roles */
    private function restoreRolePermissions(object $db, array $roles): void
    {
        foreach (self::ROLE_PERMISSIONS as $roleName => $permissions) {
            foreach ($permissions as $permission) {
                $exists = $db->table('role_permissions')
                    ->where([
                        'role_id' => $roles[$roleName],
                        'permission' => $permission,
                    ])
                    ->countAllResults();

                if ($exists > 0) {
                    $this->skipped['role_permissions']++;

                    continue;
                }

                $db->table('role_permissions')->insert([
                    'role_id' => $roles[$roleName],
                    'permission' => $permission,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
                $this->inserted['role_permissions']++;
            }
        }
    }

    /**
     * @param array<string, int> $roles
     * @param array<string, int> $users
     */
    private function restoreUserRoles(
        object $db,
        array $roles,
        array $users,
    ): void {
        foreach (self::USER_ROLES as $email => $roleName) {
            $mapping = [
                'user_id' => $users[$email],
                'role_id' => $roles[$roleName],
            ];
            $exists = $db->table('user_roles')
                ->where($mapping)
                ->countAllResults();

            if ($exists > 0) {
                $this->skipped['user_roles']++;

                continue;
            }

            $db->table('user_roles')->insert([
                'user_id' => $users[$email],
                'role_id' => $roles[$roleName],
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            $this->inserted['user_roles']++;
        }
    }
}
