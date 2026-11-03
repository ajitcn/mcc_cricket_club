<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use Config\Database;
use RuntimeException;
use Throwable;

/**
 * Permanent, reusable browser/UAT fixtures for the implemented MCC slice.
 *
 * This seeder is insert-only for positively identified TEST_MCC fixtures. It
 * never clears, deletes, or overwrites rows owned by another data source.
 */
final class TestDatasetSeeder extends Seeder
{
    private const PREFIX = 'TEST_MCC_';
    private const PASSWORD = 'MccTest!2026-SeedOnly';

    /** @var array<string, int> */
    private array $inserted = [];

    /** @var array<string, int> */
    private array $skipped = [];

    /** @var array<string, int> */
    private array $updated = [];

    /** @var array<string, int> */
    private array $ids = [];

    public function run(): void
    {
        $db = Database::connect('tests');
        if (strtolower((string) $db->getDatabase()) !== 'mcc_cricket_club_test') {
            throw new RuntimeException('Refusing test dataset seed outside mcc_cricket_club_test.');
        }

        $now = '2026-08-30 10:00:00';
        $db->transBegin();

        try {
            $this->branches($db, $now);
            $this->roles($db, $now);
            $this->users($db, $now);
            $this->roleAssignments($db, $now);
            $this->leadConfiguration($db, $now);
            $this->leadsAdmissionsPlayers($db, $now);
            $this->schedulingAttendance($db, $now);
            $this->session1318Fixture($db, $now);
            $this->trials($db, $now);
            $this->auditMarker($db, $now);

            if ($db->transStatus() === false) {
                throw new RuntimeException('Test dataset transaction failed.');
            }
            $db->transCommit();
        } catch (Throwable $exception) {
            $db->transRollback();
            throw $exception;
        }

        echo json_encode([
            'database' => 'mcc_cricket_club_test',
            'inserted' => $this->inserted,
            'updated' => $this->updated,
            'skipped' => $this->skipped,
        ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT) . PHP_EOL;
    }

    private function branches(object $db, string $now): void
    {
        $this->ids['vns'] = $this->id($db, 'branches', ['code' => 'TEST_MCC_VNS'], [
            'name' => 'MCC Varanasi', 'code' => 'TEST_MCC_VNS', 'is_active' => 1,
            'created_at' => $now, 'updated_at' => $now,
        ], 'branches');
        $this->ids['del'] = $this->id($db, 'branches', ['code' => 'TEST_MCC_DEL'], [
            'name' => 'MCC Delhi', 'code' => 'TEST_MCC_DEL', 'is_active' => 1,
            'created_at' => $now, 'updated_at' => $now,
        ], 'branches');
    }

    private function roles(object $db, string $now): void
    {
        foreach (['Super Admin', 'Branch Admin', 'Admission Team', 'Coach', 'Fitness Coach', 'Nutritionist', 'Reception', 'Parent', 'Player'] as $name) {
            $this->ids['role_' . strtolower(str_replace(' ', '_', $name))] = $this->id(
                $db, 'roles', ['name' => $name], ['name' => $name, 'created_at' => $now, 'updated_at' => $now], 'roles'
            );
        }

        $all = [
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
            'session.create',
            'session.update',
            'session.cancel',
            'attendance.view',
            'attendance.record',
            'attendance.correct',
            'rbac.manage',
            'coach.view',
            'coach.create',
            'coach.update',
            'coach.assign',
            'coach.kpi.view',
            'equipment.view',
            'equipment.create',
            'equipment.update',
            'equipment.delete',
            'program.view',
            'program.create',
            'batch.view',
            'batch.create',
        ];
        $this->permissions($db, $this->ids['role_super_admin'], $all, $now);
        $this->permissions($db, $this->ids['role_branch_admin'], $all, $now);
        $this->permissions(
            $db,
            $this->ids['role_admission_team'],
            [
                'lead.view',
                'lead.create',
                'lead.update',
                'trial.book',
                'admission.view',
                'admission.create',
                'admission.update',
                'player.view',
                'player.convert',
                'document.view',
                'document.upload',
                'document.download',
            ],
            $now
        );
        $this->permissions(
            $db,
            $this->ids['role_coach'],
            [
                'player.view',
                'session.update',
                'attendance.view',
                'attendance.record',
                'attendance.correct',
                'coach.view',
                'coach.kpi.view',
                'program.view',
                'program.create',
            ],
            $now,
        );
        $this->permissions($db, $this->ids['role_fitness_coach'], ['player.view', 'attendance.view'], $now);
        $this->permissions($db, $this->ids['role_nutritionist'], ['player.view'], $now);
        $this->permissions($db, $this->ids['role_reception'], ['lead.view', 'admission.view', 'player.view'], $now);
        $this->permissions($db, $this->ids['role_parent'], ['player.view'], $now);
        $this->permissions($db, $this->ids['role_player'], ['player.view'], $now);
    }

    private function leadConfiguration(object $db, string $now): void
    {
        foreach (['TEST_MCC_SEED', 'TEST_MCC_WEBSITE', 'TEST_MCC_REFERRAL', 'TEST_MCC_SOCIAL'] as $order => $name) {
            $this->row($db, 'lead_sources', ['name' => $name], ['name' => $name, 'is_active' => 1, 'sort_order' => $order, 'created_at' => $now, 'updated_at' => $now], 'lead_sources');
        }
        foreach (['lead', 'trial booked', 'trial attended', 'selected', 'admission pending', 'converted', 'approved', 'follow-up', 'lost'] as $order => $name) {
            $this->row($db, 'lead_statuses', ['name' => $name], ['name' => $name, 'is_active' => 1, 'sort_order' => $order, 'created_at' => $now, 'updated_at' => $now], 'lead_statuses');
        }
    }

    /** @param list<string> $permissions */
    private function permissions(object $db, int $role, array $permissions, string $now): void
    {
        foreach ($permissions as $permission) {
            $this->row($db, 'role_permissions', ['role_id' => $role, 'permission' => $permission], [
                'role_id' => $role, 'permission' => $permission, 'created_at' => $now,
            ], 'role_permissions');
        }
    }

    private function users(object $db, string $now): void
    {
        $users = [
            'super' => ['test.mcc.superadmin@example.invalid', 'TEST_MCC_SUPER_ADMIN', 1],
            'vns_manager' => ['test.mcc.manager.vns@example.invalid', 'TEST_MCC_MANAGER_VNS', 1],
            'del_staff' => ['test.mcc.staff.del@example.invalid', 'TEST_MCC_STAFF_DEL', 1],
            'coach' => ['test.mcc.coach@example.invalid', 'TEST_MCC_COACH', 1],
            'fitness' => ['test.mcc.fitness@example.invalid', 'TEST_MCC_FITNESS_COACH', 1],
            'nutritionist' => ['test.mcc.nutritionist@example.invalid', 'TEST_MCC_NUTRITIONIST', 1],
            'reception' => ['test.mcc.reception@example.invalid', 'TEST_MCC_RECEPTION', 1],
            'parent' => ['test.mcc.parent@example.invalid', 'TEST_MCC_PARENT', 1],
            'player' => ['test.mcc.player@example.invalid', 'TEST_MCC_PLAYER', 1],
            'disabled' => ['test.mcc.disabled@example.invalid', 'TEST_MCC_DISABLED', 0],
        ];
        foreach ($users as $key => [$email, $displayName, $enabled]) {
            $this->ids['user_' . $key] = $this->id($db, 'users', ['email' => $email], [
                'email' => $email, 'password_hash' => password_hash(self::PASSWORD, PASSWORD_DEFAULT),
                'display_name' => $displayName, 'is_enabled' => $enabled, 'failed_login_count' => 0,
                'session_version' => 1, 'locked_until' => null, 'last_login_at' => null,
                'created_at' => $now, 'updated_at' => $now,
            ], 'users');
        }

        $availableCoach = $db->query(
            "SELECT u.id FROM users u "
            . "LEFT JOIN player_coach_assignments pca ON pca.coach_user_id = u.id "
            . "WHERE u.email LIKE 'test.mcc.coach.%' AND pca.id IS NULL "
            . "ORDER BY u.id ASC LIMIT 1"
        )->getRowArray();
        if (is_array($availableCoach)) {
            $this->ids['user_coach_kpi'] = (int) $availableCoach['id'];
        } else {
            $suffix = bin2hex(random_bytes(6));
            $this->ids['user_coach_kpi'] = $this->id(
                $db,
                'users',
                ['email' => 'test.mcc.coach.kpi.' . $suffix . '@example.invalid'],
                [
                    'email' => 'test.mcc.coach.kpi.' . $suffix . '@example.invalid',
                    'password_hash' => password_hash(self::PASSWORD, PASSWORD_DEFAULT),
                    'display_name' => 'TEST_MCC_COACH_KPI_' . strtoupper($suffix),
                    'is_enabled' => 1,
                    'failed_login_count' => 0,
                    'session_version' => 1,
                    'locked_until' => null,
                    'last_login_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                'users'
            );
        }
    }

    private function roleAssignments(object $db, string $now): void
    {
        $roles = [
            ['user_super', 'role_super_admin'], ['user_vns_manager', 'role_branch_admin'],
            ['user_del_staff', 'role_admission_team'], ['user_coach', 'role_coach'], ['user_disabled', 'role_coach'],
            ['user_coach_kpi', 'role_coach'],
            ['user_fitness', 'role_fitness_coach'], ['user_nutritionist', 'role_nutritionist'], ['user_reception', 'role_reception'],
            ['user_parent', 'role_parent'], ['user_player', 'role_player'],
        ];
        foreach ($roles as [$user, $role]) {
            $this->row($db, 'user_roles', ['user_id' => $this->ids[$user], 'role_id' => $this->ids[$role]], [
                'user_id' => $this->ids[$user], 'role_id' => $this->ids[$role], 'created_at' => $now,
            ], 'user_roles');
        }
        foreach ([['user_super', 'vns'], ['user_super', 'del'], ['user_vns_manager', 'vns'], ['user_del_staff', 'del'], ['user_coach', 'vns'], ['user_coach', 'del'], ['user_coach_kpi', 'vns'], ['user_coach_kpi', 'del'], ['user_fitness', 'vns'], ['user_nutritionist', 'vns'], ['user_reception', 'vns'], ['user_parent', 'vns'], ['user_player', 'vns']] as [$user, $branch]) {
            $this->row($db, 'user_branches', ['user_id' => $this->ids[$user], 'branch_id' => $this->ids[$branch]], [
                'user_id' => $this->ids[$user], 'branch_id' => $this->ids[$branch], 'created_at' => $now,
            ], 'user_branches');
        }
    }

    private function leadsAdmissionsPlayers(object $db, string $now): void
    {
        $this->lead($db, 'new', $this->ids['vns'], $this->ids['user_vns_manager'], 'TEST_MCC_NEW_PLAYER', 'test.mcc.new@example.invalid', 'trial booked', null, $now);
        $this->lead($db, 'followup', $this->ids['vns'], $this->ids['user_vns_manager'], 'TEST_MCC_FOLLOWUP', 'test.mcc.followup@example.invalid', 'lead', null, $now);
        $this->lead($db, 'lost', $this->ids['del'], $this->ids['user_del_staff'], 'TEST_MCC_LOST', 'test.mcc.lost@example.invalid', 'lost', 'No response after follow-up', $now);
        $convertedLead = $this->lead($db, 'converted', $this->ids['vns'], $this->ids['user_vns_manager'], 'TEST_MCC_CONVERTED', 'test.mcc.converted@example.invalid', 'lead', null, $now);
        $approvedLead = $this->lead($db, 'approved', $this->ids['del'], $this->ids['user_del_staff'], 'TEST_MCC_APPROVED', 'test.mcc.approved@example.invalid', 'lead', null, $now);

        $this->admissionAndPlayer($db, $convertedLead, $this->ids['vns'], $this->ids['user_vns_manager'], 'CONVERTED', 'TEST_MCC_CONVERTED_PLAYER', $now, true);
        $this->admissionAndPlayer($db, $approvedLead, $this->ids['del'], $this->ids['user_del_staff'], 'APPROVED', null, $now, false);
        $direct = $this->id($db, 'admissions', ['applicant_email' => 'test.mcc.direct.admission@example.invalid'], [
            'lead_id' => null, 'applicant_full_name' => 'TEST_MCC_DIRECT_ADMISSION', 'applicant_email' => 'test.mcc.direct.admission@example.invalid',
            'applicant_phone' => '9999912999', 'applicant_date_of_birth' => '2013-05-20', 'branch_id' => $this->ids['vns'], 'status' => 'INTAKE',
            'checklist_complete' => 0, 'checklist_identity_complete' => 0, 'checklist_contact_complete' => 0,
            'checklist_admission_complete' => 0, 'checklist_branch_confirmed' => 0, 'checklist_approval_recorded' => 0,
            'converted_player_id' => null, 'approved_by' => null, 'approved_at' => null, 'created_at' => $now, 'updated_at' => $now,
        ], 'admissions');
        $this->ids['admission_direct'] = $direct;
        $this->row($db, 'admission_status_history', ['admission_id' => $direct, 'new_status' => 'INTAKE'], ['admission_id' => $direct, 'actor_user_id' => $this->ids['user_vns_manager'], 'previous_status' => null, 'new_status' => 'INTAKE', 'reason' => null, 'created_at' => $now], 'admission_status_history');

        $this->ids['batch_vns'] = $this->id($db, 'batches', ['name' => 'TEST_MCC_VNS_U15'], ['branch_id' => $this->ids['vns'], 'name' => 'TEST_MCC_VNS_U15', 'created_at' => $now, 'updated_at' => $now], 'batches');
        $this->ids['batch_del'] = $this->id($db, 'batches', ['name' => 'TEST_MCC_DEL_U13'], ['branch_id' => $this->ids['del'], 'name' => 'TEST_MCC_DEL_U13', 'created_at' => $now, 'updated_at' => $now], 'batches');
        $this->row($db, 'player_batch_assignments', ['player_id' => $this->ids['player_converted'], 'effective_from' => '2026-08-01 00:00:00'], [
            'player_id' => $this->ids['player_converted'], 'branch_id' => $this->ids['vns'], 'batch_id' => $this->ids['batch_vns'], 'assigned_by' => $this->ids['user_vns_manager'], 'effective_from' => '2026-08-01 00:00:00', 'effective_to' => null, 'created_at' => $now, 'updated_at' => $now,
        ], 'player_batch_assignments');
        $this->row($db, 'player_coach_assignments', ['player_id' => $this->ids['player_converted'], 'effective_from' => '2026-08-01 00:00:00'], [
            'player_id' => $this->ids['player_converted'], 'branch_id' => $this->ids['vns'], 'coach_user_id' => $this->ids['user_coach'], 'assigned_by' => $this->ids['user_vns_manager'], 'effective_from' => '2026-08-01 00:00:00', 'effective_to' => null, 'created_at' => $now, 'updated_at' => $now,
        ], 'player_coach_assignments');
    }

    private function trials(object $db, string $now): void
    {
        $booked = $this->id($db, 'trials', ['lead_id' => $this->ids['lead_new'], 'trial_date' => '2026-09-12', 'trial_time' => '10:00:00'], [
            'lead_id' => $this->ids['lead_new'], 'branch_id' => $this->ids['vns'], 'trial_date' => '2026-09-12', 'trial_time' => '10:00:00', 'location' => 'TEST_MCC VNS Main Ground', 'coach_user_id' => $this->ids['user_coach'], 'status' => 'booked', 'selection_outcome' => 'pending', 'remarks' => 'Synthetic booked trial fixture.', 'cancellation_reason' => null, 'rescheduled_from_id' => null, 'created_by' => $this->ids['user_vns_manager'], 'created_at' => $now, 'updated_at' => $now,
        ], 'trials');
        $this->row($db, 'trial_history', ['trial_id' => $booked, 'event_type' => 'booked'], [
            'trial_id' => $booked, 'lead_id' => $this->ids['lead_new'], 'actor_user_id' => $this->ids['user_vns_manager'], 'event_type' => 'booked', 'previous_date' => null, 'previous_time' => null, 'new_date' => '2026-09-12', 'new_time' => '10:00:00', 'previous_status' => null, 'new_status' => 'booked', 'selection_outcome' => null, 'reason' => null, 'created_at' => $now,
        ], 'trial_history');

        $attended = $this->id($db, 'trials', ['lead_id' => $this->ids['lead_converted'], 'trial_date' => '2026-08-15', 'trial_time' => '09:00:00'], [
            'lead_id' => $this->ids['lead_converted'], 'branch_id' => $this->ids['vns'], 'trial_date' => '2026-08-15', 'trial_time' => '09:00:00', 'location' => 'TEST_MCC VNS Main Ground', 'coach_user_id' => $this->ids['user_coach'], 'status' => 'attended', 'selection_outcome' => 'selected', 'remarks' => 'Synthetic completed trial fixture.', 'cancellation_reason' => null, 'rescheduled_from_id' => null, 'created_by' => $this->ids['user_vns_manager'], 'created_at' => $now, 'updated_at' => $now,
        ], 'trials');
        $this->row($db, 'trial_history', ['trial_id' => $attended, 'event_type' => 'booked'], ['trial_id' => $attended, 'lead_id' => $this->ids['lead_converted'], 'actor_user_id' => $this->ids['user_vns_manager'], 'event_type' => 'booked', 'previous_date' => null, 'previous_time' => null, 'new_date' => '2026-08-15', 'new_time' => '09:00:00', 'previous_status' => null, 'new_status' => 'booked', 'selection_outcome' => null, 'reason' => null, 'created_at' => $now], 'trial_history');
        $this->row($db, 'trial_history', ['trial_id' => $attended, 'event_type' => 'attendance_recorded'], ['trial_id' => $attended, 'lead_id' => $this->ids['lead_converted'], 'actor_user_id' => $this->ids['user_coach'], 'event_type' => 'attendance_recorded', 'previous_date' => '2026-08-15', 'previous_time' => '09:00:00', 'new_date' => null, 'new_time' => null, 'previous_status' => 'booked', 'new_status' => 'attended', 'selection_outcome' => null, 'reason' => null, 'created_at' => $now], 'trial_history');
        $this->row($db, 'trial_history', ['trial_id' => $attended, 'event_type' => 'selection_recorded'], ['trial_id' => $attended, 'lead_id' => $this->ids['lead_converted'], 'actor_user_id' => $this->ids['user_vns_manager'], 'event_type' => 'selection_recorded', 'previous_date' => null, 'previous_time' => null, 'new_date' => null, 'new_time' => null, 'previous_status' => 'attended', 'new_status' => 'attended', 'selection_outcome' => 'selected', 'reason' => null, 'created_at' => $now], 'trial_history');
    }

    private function lead(object $db, string $key, int $branch, int $owner, string $name, string $email, string $status, ?string $reason, string $now): int
    {
        $id = $this->id($db, 'leads', ['email' => $email], ['branch_id' => $branch, 'owner_user_id' => $owner, 'source' => 'TEST_MCC_SEED', 'full_name' => $name, 'email' => $email, 'phone' => '99999' . str_pad((string) count($this->ids), 5, '0', STR_PAD_LEFT), 'date_of_birth' => '2012-04-15', 'status' => $status, 'lost_reason' => $reason, 'closed_at' => $status === 'lost' ? $now : null, 'created_at' => $now, 'updated_at' => $now], 'leads');
        $this->ids['lead_' . $key] = $id;
        $this->row($db, 'lead_status_history', ['lead_id' => $id, 'new_status' => $status], ['lead_id' => $id, 'actor_user_id' => $owner, 'previous_status' => null, 'new_status' => $status, 'reason' => $reason, 'created_at' => $now], 'lead_status_history');
        return $id;
    }

    private function admissionAndPlayer(object $db, int $lead, int $branch, int $actor, string $status, ?string $playerName, string $now, bool $player): void
    {
        $admission = $this->id($db, 'admissions', ['lead_id' => $lead], ['lead_id' => $lead, 'branch_id' => $branch, 'status' => $status, 'checklist_complete' => 1, 'checklist_identity_complete' => 1, 'checklist_contact_complete' => 1, 'checklist_admission_complete' => 1, 'checklist_branch_confirmed' => 1, 'checklist_approval_recorded' => $status !== 'CONVERTED' ? 1 : 1, 'converted_player_id' => null, 'approved_by' => $actor, 'approved_at' => $now, 'created_at' => $now, 'updated_at' => $now], 'admissions');
        $this->ids['admission_' . ($player ? 'converted' : 'approved')] = $admission;
        $this->row($db, 'admission_status_history', ['admission_id' => $admission, 'new_status' => $status], ['admission_id' => $admission, 'actor_user_id' => $actor, 'previous_status' => null, 'new_status' => $status, 'reason' => null, 'created_at' => $now], 'admission_status_history');
        if (! $player) { return; }
        $person = $this->id($db, 'persons', ['email' => 'test.mcc.converted@example.invalid'], ['full_name' => $playerName, 'email' => 'test.mcc.converted@example.invalid', 'phone' => '9999912345', 'date_of_birth' => '2012-04-15', 'created_at' => $now, 'updated_at' => $now], 'persons');
        $playerId = $this->id($db, 'players', ['lead_id' => $lead], ['person_id' => $person, 'lead_id' => $lead, 'admission_id' => $admission, 'branch_id' => $branch, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now, 'profile_revision' => 1], 'players');
        $this->ids['player_converted'] = $playerId;
        $guardian = $this->id($db, 'persons', ['email' => 'test.mcc.guardian@example.invalid'], ['full_name' => 'TEST_MCC_GUARDIAN', 'email' => 'test.mcc.guardian@example.invalid', 'phone' => '9999912346', 'date_of_birth' => null, 'created_at' => $now, 'updated_at' => $now], 'guardians');
        $this->row($db, 'player_contacts', ['player_id' => $playerId, 'contact_person_id' => $guardian, 'relationship_type' => 'guardian'], ['player_id' => $playerId, 'contact_person_id' => $guardian, 'relationship_type' => 'guardian', 'is_primary' => 1, 'created_at' => $now, 'updated_at' => $now], 'player_contacts');
        $admissionRow = $db->table('admissions')->where('id', $admission)->get()->getRowArray();
        if (is_array($admissionRow) && $admissionRow['converted_player_id'] === null) {
            $db->table('admissions')->where('id', $admission)->where('converted_player_id', null)->update(['converted_player_id' => $playerId]);
            $this->updated['admissions'] = ($this->updated['admissions'] ?? 0) + 1;
        }
        $this->row($db, 'player_branch_history', ['player_id' => $playerId, 'effective_from' => '2026-08-01 00:00:00'], ['player_id' => $playerId, 'branch_id' => $branch, 'effective_from' => '2026-08-01 00:00:00', 'effective_to' => null, 'created_at' => $now], 'player_branch_history');
    }

    private function schedulingAttendance(object $db, string $now): void
    {
        $this->ids['session_scheduled'] = $this->id($db, 'sessions', ['starts_at' => '2026-09-05 03:30:00', 'branch_id' => $this->ids['vns']], ['branch_id' => $this->ids['vns'], 'batch_id' => $this->ids['batch_vns'], 'coach_user_id' => $this->ids['user_coach'], 'starts_at' => '2026-09-05 03:30:00', 'ends_at' => '2026-09-05 04:30:00', 'status' => 'scheduled', 'cancellation_reason' => null, 'cancelled_by' => null, 'cancelled_at' => null, 'rescheduled_from_id' => null, 'created_by' => $this->ids['user_vns_manager'], 'created_at' => $now, 'updated_at' => $now], 'sessions');
        $this->ids['session_cancelled'] = $this->id($db, 'sessions', ['starts_at' => '2026-09-06 03:30:00', 'branch_id' => $this->ids['vns']], ['branch_id' => $this->ids['vns'], 'batch_id' => $this->ids['batch_vns'], 'coach_user_id' => $this->ids['user_coach'], 'starts_at' => '2026-09-06 03:30:00', 'ends_at' => '2026-09-06 04:30:00', 'status' => 'cancelled', 'cancellation_reason' => 'TEST_MCC cancelled sample', 'cancelled_by' => $this->ids['user_vns_manager'], 'cancelled_at' => $now, 'rescheduled_from_id' => null, 'created_by' => $this->ids['user_vns_manager'], 'created_at' => $now, 'updated_at' => $now], 'sessions');
        $this->row($db, 'session_players', ['session_id' => $this->ids['session_scheduled'], 'player_id' => $this->ids['player_converted']], ['session_id' => $this->ids['session_scheduled'], 'player_id' => $this->ids['player_converted'], 'created_at' => $now], 'session_players');
        $this->row($db, 'attendance', ['session_id' => $this->ids['session_scheduled'], 'player_id' => $this->ids['player_converted']], ['session_id' => $this->ids['session_scheduled'], 'player_id' => $this->ids['player_converted'], 'branch_id' => $this->ids['vns'], 'status' => 'PRESENT', 'recorded_by' => $this->ids['user_coach'], 'recorded_at' => $now, 'version' => 1, 'created_at' => $now, 'updated_at' => $now], 'attendance');
        $attendance = $db->table('attendance')->where(['session_id' => $this->ids['session_scheduled'], 'player_id' => $this->ids['player_converted']])->get()->getRowArray();
        if (is_array($attendance)) {
            $this->row($db, 'attendance_history', ['attendance_id' => (int) $attendance['id'], 'new_status' => 'PRESENT'], ['attendance_id' => (int) $attendance['id'], 'prior_status' => null, 'new_status' => 'PRESENT', 'actor_user_id' => $this->ids['user_coach'], 'reason' => null, 'changed_at' => $now, 'prior_version' => 0, 'new_version' => 1], 'attendance_history');
        }
    }

    private function session1318Fixture(object $db, string $now): void
    {
        $session = $db->table('sessions')->where('id', 1318)->get()->getRowArray();
        if (! is_array($session)) {
            $this->row($db, 'sessions', ['id' => 1318], [
                'id' => 1318,
                'branch_id' => $this->ids['vns'],
                'batch_id' => $this->ids['batch_vns'],
                'coach_user_id' => $this->ids['user_coach'],
                'starts_at' => '2026-09-01 03:30:00',
                'ends_at' => '2026-09-01 04:30:00',
                'status' => 'scheduled',
                'cancellation_reason' => null,
                'cancelled_by' => null,
                'cancelled_at' => null,
                'rescheduled_from_id' => null,
                'created_by' => $this->ids['user_vns_manager'],
                'created_at' => $now,
                'updated_at' => $now,
            ], 'sessions');
        } else {
            $this->skipped['sessions'] = ($this->skipped['sessions'] ?? 0) + 1;
        }

        $this->row($db, 'session_players', ['session_id' => 1318, 'player_id' => 1], [
            'session_id' => 1318,
            'player_id' => 1,
            'created_at' => $now,
        ], 'session_players');
        $this->row($db, 'session_players', [
            'session_id' => 1318,
            'player_id' => $this->ids['player_converted'],
        ], [
            'session_id' => 1318,
            'player_id' => $this->ids['player_converted'],
            'created_at' => $now,
        ], 'session_players');

        $snapshot = $db->table('attendance_roster_snapshots')->where('session_id', 1318)->get()->getRowArray();
        if (! is_array($snapshot)) {
            $this->row($db, 'attendance_roster_snapshots', ['id' => 409], [
                'id' => 409,
                'session_id' => 1318,
                'branch_id' => $this->ids['vns'],
                'captured_at' => '2026-09-01 03:20:00',
            ], 'attendance_roster_snapshots');
            $snapshot = ['id' => 409];
        } else {
            $this->skipped['attendance_roster_snapshots'] = ($this->skipped['attendance_roster_snapshots'] ?? 0) + 1;
        }

        $this->row($db, 'attendance_roster_snapshot_members', [
            'snapshot_id' => (int) $snapshot['id'],
            'player_id' => 1,
        ], [
            'snapshot_id' => (int) $snapshot['id'],
            'player_id' => 1,
            'branch_id' => $this->ids['vns'],
        ], 'attendance_roster_snapshot_members');
        $this->row($db, 'attendance_roster_snapshot_members', [
            'snapshot_id' => (int) $snapshot['id'],
            'player_id' => $this->ids['player_converted'],
        ], [
            'snapshot_id' => (int) $snapshot['id'],
            'player_id' => $this->ids['player_converted'],
            'branch_id' => $this->ids['vns'],
        ], 'attendance_roster_snapshot_members');
    }

    private function auditMarker(object $db, string $now): void
    {
        $this->row($db, 'audit_logs', ['action' => 'test_dataset_seeded', 'entity_type' => 'test_dataset', 'entity_id' => 'TEST_MCC_DATASET_V1'], ['actor_user_id' => $this->ids['user_super'], 'action' => 'test_dataset_seeded', 'entity_type' => 'test_dataset', 'entity_id' => 'TEST_MCC_DATASET_V1', 'branch_id' => null, 'before_summary' => null, 'after_summary' => '{"dataset":"TEST_MCC_V1"}', 'reason' => 'Permanent test dataset marker', 'created_at' => $now], 'audit_logs');
    }

    private function id(object $db, string $table, array $where, array $data, string $counter): int
    {
        $existing = $db->table($table)->where($where)->get()->getRowArray();
        if (is_array($existing)) { $this->skipped[$counter] = ($this->skipped[$counter] ?? 0) + 1; return (int) $existing['id']; }
        $this->row($db, $table, $where, $data, $counter);
        return (int) $db->insertID();
    }

    private function row(object $db, string $table, array $where, array $data, string $counter): void
    {
        if ($db->table($table)->where($where)->countAllResults() > 0) { $this->skipped[$counter] = ($this->skipped[$counter] ?? 0) + 1; return; }
        if (! $db->table($table)->insert($data)) { throw new RuntimeException('Unable to seed ' . $table . '.'); }
        $this->inserted[$counter] = ($this->inserted[$counter] ?? 0) + 1;
    }
}
