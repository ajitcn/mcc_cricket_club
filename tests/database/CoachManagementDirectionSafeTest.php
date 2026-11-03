<?php

declare(strict_types=1);

namespace Tests\Database;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Database;
use RuntimeException;

/**
 * Insert-only behavioral coverage for the user-directed Coach slice.
 */
final class CoachManagementDirectionSafeTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $DBGroup = 'tests';
    protected $migrate = false;

    private object $database;
    private int $actor;
    private int $branch;
    private int $otherBranch;
    private int $batch;
    private int $player;
    private int $coach;

    protected function setUp(): void
    {
        parent::setUp();

        $this->database = Database::connect('default');
        self::assertSame('mcc_cricket_club_test', strtolower((string) $this->database->getDatabase()));

        $this->actor = $this->id('users', ['email' => 'test.mcc.superadmin@example.invalid']);
        $this->branch = $this->id('branches', ['code' => 'TEST_MCC_VNS']);
        $this->otherBranch = $this->id('branches', ['code' => 'TEST_MCC_DEL']);
        $this->batch = $this->id('batches', ['name' => 'TEST_MCC_VNS_U15']);
        $this->player = (int) $this->database->table('players p')
            ->select('p.id')
            ->join('persons pe', 'pe.id = p.person_id')
            ->like('pe.full_name', 'TEST_MCC_CONVERTED_PLAYER')
            ->get()
            ->getRow('id');
        $this->coach = $this->id('users', ['email' => 'test.mcc.coach@example.invalid']);

        self::assertGreaterThan(0, min(
            $this->actor,
            $this->branch,
            $this->otherBranch,
            $this->batch,
            $this->player,
            $this->coach
        ));
    }

    protected function tearDown(): void
    {
        session()->destroy();
        \Config\Services::resetSingle('authorization');
        parent::tearDown();
    }

    public function testCoachBranchAssignmentUsesPermissionBranchAndAuditScope(): void
    {
        $newCoach = $this->createCoach();
        $this->authorize(['coach.assign'], [$this->branch]);

        service('coach', false)->assignBranch($newCoach, $this->branch, true, 'TEST_MCC branch assignment');

        self::assertSame(1, $this->database->table('user_branches')->where([
            'user_id' => $newCoach,
            'branch_id' => $this->branch,
        ])->countAllResults());
        $audit = $this->database->table('audit_logs')->where([
            'action' => 'coach_branch_assigned',
            'entity_type' => 'coach',
            'entity_id' => (string) $newCoach,
            'actor_user_id' => $this->actor,
            'branch_id' => $this->branch,
        ])->orderBy('id', 'DESC')->get()->getRowArray();
        self::assertIsArray($audit);
        self::assertSame('TEST_MCC branch assignment', $audit['reason']);
        self::assertNotEmpty($audit['created_at']);
        self::assertNotEmpty($audit['before_summary']);
        self::assertNotEmpty($audit['after_summary']);

        $this->expectExceptionMessage('Forbidden');
        service('coach', false)->assignBranch($newCoach, $this->otherBranch, true, 'TEST_MCC forged branch');
    }

    public function testSessionReassignmentValidatesTimingCoachBranchAndAudit(): void
    {
        $replacementCoach = $this->createCoach($this->branch);
        $this->authorize(['session.update'], [$this->branch]);

        $futureSession = $this->createSession($this->coach, '2099-01-01 10:00:00', '2099-01-01 11:00:00');
        service('scheduling', false)->reassignCoach($futureSession, $replacementCoach, 'TEST_MCC pre-start reassignment');
        self::assertSame(
            $replacementCoach,
            (int) $this->database->table('sessions')->where('id', $futureSession)->get()->getRow('coach_user_id')
        );
        $audit = $this->database->table('audit_logs')->where([
            'action' => 'session_coach_reassigned',
            'entity_id' => (string) $futureSession,
            'actor_user_id' => $this->actor,
            'branch_id' => $this->branch,
        ])->orderBy('id', 'DESC')->get()->getRowArray();
        self::assertIsArray($audit);
        self::assertSame('TEST_MCC pre-start reassignment', $audit['reason']);
        self::assertNotEmpty($audit['before_summary']);
        self::assertNotEmpty($audit['after_summary']);
        self::assertNotEmpty($audit['created_at']);

        $startedSession = $this->createSession($this->coach, '2020-01-01 10:00:00', '2020-01-01 11:00:00');
        try {
            service('scheduling', false)->reassignCoach($startedSession, $replacementCoach, '');
            self::fail('A post-start reassignment without a reason was accepted.');
        } catch (RuntimeException $exception) {
            self::assertSame('A reason is required after the session starts.', $exception->getMessage());
        }
        service('scheduling', false)->reassignCoach($startedSession, $replacementCoach, 'TEST_MCC post-start correction');

        $crossBranchCoach = $this->createCoach($this->otherBranch);
        $crossBranchSession = $this->createSession($this->coach, '2099-01-02 10:00:00', '2099-01-02 11:00:00');
        $this->expectExceptionMessage('Coach is not authorized for branch.');
        service('scheduling', false)->reassignCoach($crossBranchSession, $crossBranchCoach, 'TEST_MCC cross branch');
    }

    public function testLeaveOverlapIsRejectedAndCancellationRetainsHistory(): void
    {
        $this->authorize(['coach.update'], [$this->branch]);
        $leaveId = service('coach', false)->saveLeave($this->coach, $this->branch, [
            'unavailable_from' => '2099-02-01 09:00:00',
            'unavailable_to' => '2099-02-03 09:00:00',
            'reason' => 'TEST_MCC planned leave',
        ]);
        self::assertSame('PENDING', $this->database->table('coach_unavailability')->where('id', $leaveId)->get()->getRow('status'));
        service('coach', false)->approveLeave($leaveId);

        try {
            service('coach', false)->saveLeave($this->coach, $this->branch, [
                'unavailable_from' => '2099-02-02 09:00:00',
                'unavailable_to' => '2099-02-04 09:00:00',
                'reason' => 'TEST_MCC overlap',
            ]);
            self::fail('Overlapping leave was accepted.');
        } catch (RuntimeException $exception) {
            self::assertSame('Overlapping leave is not allowed.', $exception->getMessage());
        }

        $attendanceBefore = (int) $this->database->table('attendance')->countAllResults();
        service('coach', false)->cancelLeave($leaveId, 'TEST_MCC cancellation');
        $leave = $this->database->table('coach_unavailability')->where('id', $leaveId)->get()->getRowArray();
        self::assertIsArray($leave);
        self::assertSame('CANCELLED', $leave['status']);
        self::assertNotEmpty($leave['cancelled_at']);
        self::assertSame($attendanceBefore, (int) $this->database->table('attendance')->countAllResults());

        $audit = $this->database->table('audit_logs')->where([
            'action' => 'coach_leave_cancelled',
            'entity_id' => (string) $leaveId,
            'actor_user_id' => $this->actor,
            'branch_id' => $this->branch,
        ])->orderBy('id', 'DESC')->get()->getRowArray();
        self::assertIsArray($audit);
        self::assertSame('TEST_MCC cancellation', $audit['reason']);
        self::assertNotEmpty($audit['before_summary']);
        self::assertNotEmpty($audit['after_summary']);
        self::assertNotEmpty($audit['created_at']);
    }

    public function testLeaveEditRequiresReasonExcludesSelfAndPreservesAudit(): void
    {
        $this->authorize(['coach.update'], [$this->branch]);
        $leaveId = service('coach', false)->saveLeave($this->coach, $this->branch, [
            'unavailable_from' => '2099-05-01 09:00:00',
            'unavailable_to' => '2099-05-03 09:00:00',
            'reason' => 'TEST_MCC original leave',
        ]);
        $secondLeaveId = service('coach', false)->saveLeave($this->coach, $this->branch, [
            'unavailable_from' => '2099-05-05 09:00:00',
            'unavailable_to' => '2099-05-07 09:00:00',
            'reason' => 'TEST_MCC second leave',
        ]);

        try {
            service('coach', false)->saveLeave($this->coach, $this->branch, [
                'unavailable_from' => '2099-05-01 09:00:00',
                'unavailable_to' => '2099-05-04 09:00:00',
                'reason' => 'TEST_MCC attempted edit',
            ], $leaveId);
            self::fail('An edit without a correction reason was accepted.');
        } catch (RuntimeException $exception) {
            self::assertSame('A correction reason is required.', $exception->getMessage());
        }

        try {
            service('coach', false)->saveLeave($this->coach, $this->branch, [
                'unavailable_from' => '2099-05-06 09:00:00',
                'unavailable_to' => '2099-05-08 09:00:00',
                'reason' => 'TEST_MCC overlapping edit',
                'correction_reason' => 'TEST_MCC overlap correction',
            ], $leaveId);
            self::fail('An overlapping leave edit was accepted.');
        } catch (RuntimeException $exception) {
            self::assertSame('Overlapping leave is not allowed.', $exception->getMessage());
        }

        service('coach', false)->saveLeave($this->coach, $this->branch, [
            'unavailable_from' => '2099-05-01 09:00:00',
            'unavailable_to' => '2099-05-03 09:00:00',
            'reason' => 'TEST_MCC edited leave',
            'correction_reason' => 'TEST_MCC valid correction',
        ], $leaveId);
        $leave = $this->database->table('coach_unavailability')->where('id', $leaveId)->get()->getRowArray();
        self::assertIsArray($leave);
        self::assertSame('TEST_MCC edited leave', $leave['reason']);
        self::assertSame('PENDING', $leave['status']);
        self::assertGreaterThan(0, $secondLeaveId);

        $audit = $this->database->table('audit_logs')->where([
            'action' => 'coach_leave_updated',
            'entity_id' => (string) $leaveId,
            'actor_user_id' => $this->actor,
            'branch_id' => $this->branch,
        ])->orderBy('id', 'DESC')->get()->getRowArray();
        self::assertIsArray($audit);
        self::assertSame('TEST_MCC valid correction', $audit['reason']);
        self::assertSame('TEST_MCC original leave', json_decode((string) $audit['before_summary'], true)['reason']);
        self::assertSame('TEST_MCC edited leave', json_decode((string) $audit['after_summary'], true)['reason']);
        self::assertNotEmpty($audit['created_at']);

        $this->authorize(['coach.update'], [$this->otherBranch]);
        $this->expectExceptionMessage('Forbidden');
        service('coach', false)->saveLeave($this->coach, $this->branch, [
            'unavailable_from' => '2099-05-01 09:00:00',
            'unavailable_to' => '2099-05-03 09:00:00',
            'reason' => 'TEST_MCC forged edit',
            'correction_reason' => 'TEST_MCC forged reason',
        ], $leaveId);
    }

    public function testAvailabilityUsesBranchScopeOverlapCorrectionCancellationAndLeavePrecedence(): void
    {
        $this->authorize(['coach.update'], [$this->branch]);
        $weeklyId = service('coach', false)->saveAvailability($this->coach, $this->branch, [
            'weekday' => '1',
            'start_time' => '09:00',
            'end_time' => '12:00',
            'reason' => 'TEST_MCC weekly availability',
        ]);

        try {
            service('coach', false)->saveAvailability($this->coach, $this->branch, [
                'weekday' => '1',
                'start_time' => '11:00',
                'end_time' => '13:00',
                'reason' => 'TEST_MCC overlap',
            ]);
            self::fail('Overlapping availability was accepted.');
        } catch (RuntimeException $exception) {
            self::assertSame('Overlapping availability is not allowed.', $exception->getMessage());
        }

        service('coach', false)->saveAvailability($this->coach, $this->branch, [
            'weekday' => '1',
            'start_time' => '09:00',
            'end_time' => '12:00',
            'reason' => 'TEST_MCC edited weekly availability',
            'correction_reason' => 'TEST_MCC availability correction',
        ], $weeklyId);
        $audit = $this->database->table('audit_logs')->where([
            'action' => 'coach_availability_updated',
            'entity_id' => (string) $weeklyId,
            'actor_user_id' => $this->actor,
            'branch_id' => $this->branch,
        ])->orderBy('id', 'DESC')->get()->getRowArray();
        self::assertIsArray($audit);
        self::assertSame('TEST_MCC availability correction', $audit['reason']);
        self::assertSame('TEST_MCC weekly availability', json_decode((string) $audit['before_summary'], true)['reason']);
        self::assertSame('TEST_MCC edited weekly availability', json_decode((string) $audit['after_summary'], true)['reason']);

        $oneTimeId = service('coach', false)->saveAvailability($this->coach, $this->branch, [
            'available_date' => '2099-06-10',
            'start_time' => '09:00',
            'end_time' => '12:00',
            'reason' => 'TEST_MCC one-time availability',
        ]);
        $records = service('coach', false)->availability($this->coach);
        self::assertNotEmpty($records);
        self::assertContains($oneTimeId, array_map('intval', array_column($records, 'id')));

        $leaveId = service('coach', false)->saveLeave($this->coach, $this->branch, [
            'unavailable_from' => '2099-06-10 04:30:00',
            'unavailable_to' => '2099-06-10 05:30:00',
            'reason' => 'TEST_MCC leave precedence',
        ]);
        service('coach', false)->approveLeave($leaveId);
        try {
            service('scheduling', false)->validateCoachAvailabilityAndConflicts(
                $this->database,
                $this->coach,
                $this->branch,
                '2099-06-10 04:30:00',
                '2099-06-10 05:30:00'
            );
            self::fail('Approved leave did not take precedence.');
        } catch (RuntimeException $exception) {
            self::assertSame('Coach unavailable.', $exception->getMessage());
        }
        service('coach', false)->cancelLeave($leaveId, 'TEST_MCC leave cleanup marker');
        service('coach', false)->cancelAvailability($oneTimeId, 'TEST_MCC availability cancellation');
        $cancelled = $this->database->table('coach_availability')->where('id', $oneTimeId)->get()->getRowArray();
        self::assertSame($this->branch, (int) $cancelled['branch_id']);
        self::assertNotEmpty($cancelled['cancelled_at']);

        $this->authorize(['coach.update'], [$this->otherBranch]);
        self::assertSame([], service('coach', false)->availability($this->coach));
    }

    public function testLeaveApprovalRejectionReasonsBranchScopeAndAuditMetadata(): void
    {
        $this->authorize(['coach.update'], [$this->branch]);
        $leaveId = service('coach', false)->saveLeave($this->coach, $this->branch, [
            'unavailable_from' => '2099-07-01 09:00:00',
            'unavailable_to' => '2099-07-02 09:00:00',
            'reason' => 'TEST_MCC approval request',
        ]);

        try {
            service('coach', false)->rejectLeave($leaveId, '');
            self::fail('A leave rejection without a reason was accepted.');
        } catch (RuntimeException $exception) {
            self::assertSame('A rejection reason is required.', $exception->getMessage());
        }

        service('coach', false)->approveLeave($leaveId, 'TEST_MCC approval note');
        $approved = $this->database->table('coach_unavailability')->where('id', $leaveId)->get()->getRowArray();
        self::assertSame('APPROVED', $approved['status']);
        self::assertSame($this->actor, (int) $approved['status_changed_by']);
        self::assertNotEmpty($approved['status_changed_at']);

        $audit = $this->database->table('audit_logs')->where([
            'action' => 'coach_leave_approved',
            'entity_id' => (string) $leaveId,
            'actor_user_id' => $this->actor,
            'branch_id' => $this->branch,
        ])->orderBy('id', 'DESC')->get()->getRowArray();
        self::assertIsArray($audit);
        self::assertSame('PENDING', json_decode((string) $audit['before_summary'], true)['status']);
        self::assertSame('APPROVED', json_decode((string) $audit['after_summary'], true)['status']);
        self::assertSame('TEST_MCC approval note', $audit['reason']);

        $this->authorize(['coach.update'], [$this->otherBranch]);
        try {
            service('coach', false)->cancelLeave($leaveId, 'TEST_MCC forged cancellation');
            self::fail('A cross-branch leave cancellation was accepted.');
        } catch (RuntimeException $exception) {
            self::assertSame('Forbidden', $exception->getMessage());
        }

        $this->authorize(['coach.update'], [$this->branch]);
        $pendingId = service('coach', false)->saveLeave($this->coach, $this->branch, [
            'unavailable_from' => '2099-07-10 09:00:00',
            'unavailable_to' => '2099-07-11 09:00:00',
            'reason' => 'TEST_MCC coach cancellation request',
        ]);
        session()->set([
            'isLoggedIn' => true,
            'accountEnabled' => true,
            'user' => ['id' => $this->coach],
            'roles' => ['Coach'],
            'permissions' => ['coach.view'],
            'authorizedBranches' => [(string) $this->branch],
        ]);
        \Config\Services::resetSingle('authorization');
        service('coach', false)->cancelLeave($pendingId, 'TEST_MCC coach own cancellation');
        $cancelled = $this->database->table('coach_unavailability')->where('id', $pendingId)->get()->getRowArray();
        self::assertSame('CANCELLED', $cancelled['status']);
        self::assertSame($this->coach, (int) $cancelled['cancelled_by']);
    }

    public function testFeedbackRequiresCompletedRelatedPlayerAndAuthorizedBranch(): void
    {
        if (! $this->database->fieldExists('version', 'coach_feedback')) {
            self::markTestSkipped('Coach feedback version migration is not applied.');
        }

        $feedbackCoach = $this->createCoach($this->branch);
        $this->authorize(['coach.update'], [$this->branch]);
        $completedSession = $this->createSession($feedbackCoach, '2020-02-01 10:00:00', '2020-02-01 11:00:00');
        $this->database->table('session_players')->insert([
            'session_id' => $completedSession,
            'player_id' => $this->player,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);
        $now = gmdate('Y-m-d H:i:s');
        $this->database->table('attendance_roster_snapshots')->insert([
            'session_id' => $completedSession,
            'branch_id' => $this->branch,
            'captured_at' => $now,
        ]);
        $snapshotId = (int) $this->database->insertID();
        $this->database->table('attendance_roster_snapshot_members')->insert([
            'snapshot_id' => $snapshotId,
            'player_id' => $this->player,
            'branch_id' => $this->branch,
        ]);
        $this->database->table('attendance')->insert([
            'session_id' => $completedSession,
            'player_id' => $this->player,
            'branch_id' => $this->branch,
            'status' => 'PRESENT',
            'recorded_by' => $this->actor,
            'recorded_at' => $now,
            'version' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        try {
            service('coach', false)->saveFeedback($feedbackCoach, [
                'session_id' => $completedSession,
                'player_id' => $this->player,
                'rating' => 6,
                'reason' => 'TEST_MCC invalid rating',
            ]);
            self::fail('An out-of-range feedback rating was accepted.');
        } catch (RuntimeException $exception) {
            self::assertSame('A rating from 1 to 5 and reason are required.', $exception->getMessage());
        }

        $feedbackId = service('coach', false)->saveFeedback($feedbackCoach, [
            'session_id' => $completedSession,
            'player_id' => $this->player,
            'rating' => 4,
            'reason' => 'TEST_MCC completed session rating',
        ]);
        $feedback = $this->database->table('coach_feedback')->where('id', $feedbackId)->get()->getRowArray();
        self::assertIsArray($feedback);
        self::assertSame(1, (int) $feedback['approved']);
        self::assertSame($this->branch, (int) $feedback['branch_id']);
        self::assertSame(1, (int) $feedback['version']);

        service('coach', false)->saveFeedback($feedbackCoach, [
            'feedback_id' => $feedbackId,
            'session_id' => $completedSession,
            'player_id' => $this->player,
            'rating' => 5,
            'reason' => 'TEST_MCC correction reason',
            'expected_version' => 1,
        ], $feedbackId);
        self::assertSame(2, (int) $this->database->table('coach_feedback')->where('id', $feedbackId)->get()->getRow('version'));

        try {
            service('coach', false)->saveFeedback($feedbackCoach, [
                'feedback_id' => $feedbackId,
                'session_id' => $completedSession,
                'player_id' => $this->player,
                'rating' => 3,
                'reason' => 'TEST_MCC stale correction',
                'expected_version' => 1,
            ], $feedbackId);
            self::fail('A stale feedback correction was accepted.');
        } catch (RuntimeException $exception) {
            self::assertSame(
                'This feedback record changed in another session. Reload the latest feedback data and try again.',
                $exception->getMessage(),
            );
        }
        $correctionAudit = $this->database->table('audit_logs')->where([
            'action' => 'coach_feedback_corrected',
            'entity_id' => (string) $feedbackId,
            'actor_user_id' => $this->actor,
            'branch_id' => $this->branch,
        ])->orderBy('id', 'DESC')->get()->getRowArray();
        self::assertIsArray($correctionAudit);
        self::assertSame('TEST_MCC correction reason', $correctionAudit['reason']);
        self::assertNotEmpty($correctionAudit['before_summary']);
        self::assertNotEmpty($correctionAudit['after_summary']);
        self::assertNotEmpty($correctionAudit['created_at']);
        self::assertSame(4, (int) json_decode((string) $correctionAudit['before_summary'], true)['rating']);
        self::assertSame(5, (int) json_decode((string) $correctionAudit['after_summary'], true)['rating']);

        $feedbackRows = service('coach', false)->feedback($feedbackCoach);
        self::assertNotEmpty($feedbackRows);
        self::assertSame($feedbackId, (int) $feedbackRows[0]['id']);
        self::assertSame('TEST_MCC correction reason', $feedbackRows[0]['comment']);

        $this->authorize(['coach.update'], [$this->otherBranch]);
        self::assertSame([], service('coach', false)->feedback($feedbackCoach));
        $this->expectExceptionMessage('Forbidden');
        service('coach', false)->saveFeedback($feedbackCoach, [
            'session_id' => $completedSession,
            'player_id' => $this->player,
            'rating' => 5,
            'reason' => 'TEST_MCC forged branch feedback',
        ]);
    }

    public function testFeedbackKpiUsesApprovedRatingsAndIsUnavailableWithoutRatings(): void
    {
        $feedbackCoach = $this->createCoach($this->branch);
        $this->authorize(['coach.view', 'coach.kpi.view', 'coach.update'], [$this->branch]);
        $withoutRatings = service('coach', false)->find($feedbackCoach);
        self::assertNull($withoutRatings['kpis']['player_feedback_rating']);

        $session = $this->createSession($feedbackCoach, '2020-03-01 10:00:00', '2020-03-01 11:00:00');
        $this->database->table('session_players')->insert([
            'session_id' => $session,
            'player_id' => $this->player,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);
        $now = gmdate('Y-m-d H:i:s');
        $this->database->table('attendance_roster_snapshots')->insert([
            'session_id' => $session,
            'branch_id' => $this->branch,
            'captured_at' => $now,
        ]);
        $snapshotId = (int) $this->database->insertID();
        $this->database->table('attendance_roster_snapshot_members')->insert([
            'snapshot_id' => $snapshotId,
            'player_id' => $this->player,
            'branch_id' => $this->branch,
        ]);
        $this->database->table('attendance')->insert([
            'session_id' => $session,
            'player_id' => $this->player,
            'branch_id' => $this->branch,
            'status' => 'PRESENT',
            'recorded_by' => $this->actor,
            'recorded_at' => $now,
            'version' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        service('coach', false)->saveFeedback($feedbackCoach, [
            'session_id' => $session,
            'player_id' => $this->player,
            'rating' => 5,
            'reason' => 'TEST_MCC approved rating',
        ]);

        $withRating = service('coach', false)->find($feedbackCoach);
        self::assertSame(5.0, (float) $withRating['kpis']['player_feedback_rating']);
    }

    public function testDirectionMigrationDefinesRetainedLeaveAndFeedbackSources(): void
    {
        $migration = file_get_contents(ROOTPATH . 'app/Database/Migrations/20260925000200_AddCoachManagementDirection.php');

        self::assertStringContainsString('coach_unavailability', $migration);
        self::assertStringContainsString('coach_feedback', $migration);
        self::assertStringContainsString("'rating'", $migration);
        self::assertStringContainsString("'status'", $migration);
        self::assertStringContainsString('Forward-only', $migration);
    }

    private function id(string $table, array $where): int
    {
        return (int) $this->database->table($table)->where($where)->get()->getRow('id');
    }

    private function createCoach(?int $branch = null): int
    {
        $suffix = str_replace('.', '', uniqid('', true));
        $now = gmdate('Y-m-d H:i:s');
        $this->database->table('users')->insert([
            'email' => 'test.mcc.coach.' . $suffix . '@example.invalid',
            'password_hash' => password_hash('TestOnly!2026', PASSWORD_DEFAULT),
            'display_name' => 'TEST_MCC_COACH_' . $suffix,
            'is_enabled' => 1,
            'failed_login_count' => 0,
            'session_version' => 1,
            'locked_until' => null,
            'last_login_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $userId = (int) $this->database->insertID();
        $this->database->table('user_roles')->insert([
            'user_id' => $userId,
            'role_id' => $this->id('roles', ['name' => 'Coach']),
            'created_at' => $now,
        ]);
        $this->database->table('coach_profiles')->insert([
            'user_id' => $userId,
            'specialization' => null,
            'experience_years' => null,
            'qualification' => null,
            'certifications' => null,
            'availability' => null,
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        if ($branch !== null) {
            $this->database->table('user_branches')->insert([
                'user_id' => $userId,
                'branch_id' => $branch,
                'created_at' => $now,
            ]);
        }

        return $userId;
    }

    private function createSession(int $coach, string $startsAt, string $endsAt): int
    {
        $now = gmdate('Y-m-d H:i:s');
        $this->database->table('sessions')->insert([
            'branch_id' => $this->branch,
            'batch_id' => $this->batch,
            'coach_user_id' => $coach,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'status' => 'scheduled',
            'cancellation_reason' => null,
            'cancelled_by' => null,
            'cancelled_at' => null,
            'rescheduled_from_id' => null,
            'created_by' => $this->actor,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) $this->database->insertID();
    }

    /** @param list<string> $permissions @param list<int> $branches */
    private function authorize(array $permissions, array $branches): void
    {
        $userId = $this->actor;
        if ($branches === [$this->otherBranch]) {
            $branchUsers = $this->database->table('user_branches')
                ->where('branch_id', $this->otherBranch)
                ->where('user_id !=', $this->actor)
                ->get()
                ->getResultArray();

            foreach ($branchUsers as $branchUser) {
                $assignedBranchCount = $this->database->table('user_branches')
                    ->where('user_id', $branchUser['user_id'])
                    ->countAllResults();

                if ($assignedBranchCount === 1) {
                    $userId = (int) $branchUser['user_id'];
                    break;
                }
            }
        }

        session()->set([
            'isLoggedIn' => true,
            'accountEnabled' => true,
            'user' => ['id' => $userId],
            'roles' => ['Super Admin'],
            'session_version' => 1,
            'permissions' => $permissions,
            'authorizedBranches' => array_map('strval', $branches),
        ]);
        \Config\Services::resetSingle('authorization');
        \Config\Services::resetSingle('branchContext');
    }
}
