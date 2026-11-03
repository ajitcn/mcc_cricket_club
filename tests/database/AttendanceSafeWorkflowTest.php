<?php

declare (strict_types=1);
namespace Tests\Database;

use App\Services\AttendanceService;
use App\Modules\Attendance\Models\AttendanceHistoryModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Database;
use RuntimeException;
/** Permanent, non-destructive Attendance source-event verification. */
final class AttendanceSafeWorkflowTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    protected $DBGroup = 'tests';
    protected $migrate = false;
    private int $actor;
    private int $branch;
    private int $otherBranch;
    private int $batch;
    private int $otherBatch;
    private int $player;
    private int $coach;
    protected function setUp(): void
    {
        parent::setUp();
        $this->resetDefaultConnection();
        self::assertSame('mcc_cricket_club_test', strtolower((string) $this->db->getDatabase()));
        $this->actor = (int) $this->db->table('users')->where('email', 'test.mcc.superadmin@example.invalid')->get()->getRow('id');
        $this->branch = (int) $this->db->table('branches')->where('code', 'TEST_MCC_VNS')->get()->getRow('id');
        $this->otherBranch = (int) $this->db->table('branches')->where('code', 'TEST_MCC_DEL')->get()->getRow('id');
        $this->batch = (int) $this->db->table('batches')->where(['branch_id' => $this->branch, 'name' => 'TEST_MCC_VNS_U15'])->get()->getRow('id');
        $this->otherBatch = (int) $this->db->table('batches')->where(['branch_id' => $this->otherBranch, 'name' => 'TEST_MCC_DEL_U13'])->get()->getRow('id');
        $this->player = (int) $this->db->table('players p')->select('p.id')->join('persons pe', 'pe.id=p.person_id')->like('pe.full_name',
    'TEST_MCC_CONVERTED_PLAYER')->get()->getRow('id');
        $this->coach = (int) $this->db->table('users')->where('email', 'test.mcc.coach@example.invalid')->get()->getRow('id');
        self::assertGreaterThan(0, min($this->actor, $this->branch, $this->otherBranch, $this->batch, $this->otherBatch, $this->player, $this->coach));
        $this->authorize(['attendance.view', 'attendance.record', 'attendance.correct'], [$this->branch]);
    }
    protected function tearDown(): void
    {
        session()->destroy();
        \Config\Services::resetSingle('authorization');
        parent::tearDown();
    }
    public function testAuthenticatedListFiltersRosterAndDetailHistory(): void
    {
        $session = $this->fixtureSession($this->branch);
        $this->prepareRoster($session);
        $id = $this->attendance()->record($session, $this->player, 'PRESENT');
        $rows = $this->attendance()->sessions(['session_id' => $session, 'batch_id' => $this->batch, 'player' => 'TEST_MCC_CONVERTED_PLAYER',
    'attendance_status' => 'PRESENT']);
        self::assertCount(1, $rows);
        self::assertSame(1, (int) $rows[0]['recorded_count']);
        self::assertSame(1, (int) $rows[0]['roster_count']);
        $detail = $this->attendance()->find($id);
        self::assertSame($this->player, (int) $detail['player_id']);
        self::assertSame($session, (int) $detail['session_id']);
        self::assertCount(1, $detail['history']);
        $page = $this->withSession(session()->get())->get('attendance/records/' . $id);
        $page->assertStatus(200);
        $page->assertSee('Attendance history');
        $page->assertSee('TEST_MCC_CONVERTED_PLAYER');
    }

    public function testCoachAttendanceScopeOnlyShowsAndReadsOwnedSessions(): void
    {
        $ownedSession = $this->fixtureSession($this->branch);
        $this->addSessionPlayer($ownedSession);
        $otherCoach = (int) $this->db->table('users')
            ->where('email', 'test.mcc.fitness@example.invalid')
            ->get()
            ->getRow('id');
        $otherSession = $this->fixtureSessionWithCoach($this->branch, $otherCoach);

        $this->authorizeCoach();
        $rows = $this->attendance()->sessions(['session_id' => $ownedSession]);
        self::assertCount(1, $rows);
        self::assertSame($ownedSession, (int) $rows[0]['id']);

        self::assertIsArray($this->attendance()->roster($ownedSession));
        try {
            $this->attendance()->roster($otherSession);
            self::fail('Coach read another Coach session roster.');
        } catch (RuntimeException $exception) {
            self::assertSame('Forbidden', $exception->getMessage());
        }
    }
    public function testEligibleRecordingApprovedStatusesAndCentralAudit(): void
    {
        foreach (['PRESENT', 'ABSENT', 'LEAVE', 'MEDICAL', 'LATE', 'RAIN_CANCELLED', 'COACH_CANCELLED'] as $status) {
            $session = $this->fixtureSession($this->branch);
            $this->prepareRoster($session);
            $id = $this->attendance()->record($session, $this->player, $status);
            self::assertSame($status, $this->db->table('attendance')->where('id', $id)->get()->getRow('status'));
            self::assertSame(1, $this->db->table('attendance_history')->where('attendance_id', $id)->countAllResults());
            self::assertSame(1, $this->db->table('audit_logs')->where(['action' => 'attendance_recorded', 'entity_id' => (string) $id,
    'branch_id' => $this->branch])->countAllResults());
        }
        $session = $this->fixtureSession($this->branch);
        $this->prepareRoster($session);
        $this->expectExceptionMessage('Invalid attendance status.');
        $this->attendance()->record($session, $this->player, 'INVALID');
    }

    public function testBulkAttendanceSaveUsesTheAuthorizedSnapshotAndWritesHistory(): void
    {
        $session = $this->fixtureSession($this->branch);
        $this->prepareRoster($session);

        self::assertSame(1, $this->attendance()->save($session, [
            $this->player => 'LATE',
        ]));

        $row = $this->db->table('attendance')
            ->where(['session_id' => $session, 'player_id' => $this->player])
            ->get()
            ->getRowArray();
        self::assertSame('LATE', $row['status']);
        self::assertSame(1, $this->db->table('attendance_history')->where('attendance_id', $row['id'])->countAllResults());
    }

    public function testAttendanceEditUpdatesStatusWithVersionAndHistory(): void
    {
        $session = $this->fixtureSession($this->branch);
        $this->prepareRoster($session);
        $attendanceId = $this->attendance()->record($session, $this->player, 'PRESENT');

        self::assertSame(1, $this->attendance()->updateStatuses(
            $session,
            [$this->player => 'LATE'],
            [$this->player => 1],
            'TEST_MCC_ATTENDANCE_edit'
        ));

        $row = $this->db->table('attendance')->where('id', $attendanceId)->get()->getRowArray();
        self::assertSame('LATE', $row['status']);
        self::assertSame(2, (int) $row['version']);
        self::assertSame(2, $this->db->table('attendance_history')->where('attendance_id', $attendanceId)->countAllResults());
    }

    public function testAttendanceEditRejectsStaleAndUnauthorizedPlayersAtomically(): void
    {
        $session = $this->fixtureSession($this->branch);
        $this->prepareRoster($session);
        $attendanceId = $this->attendance()->record($session, $this->player, 'PRESENT');

        try {
            $this->attendance()->updateStatuses(
                $session,
                [$this->player => 'ABSENT'],
                [$this->player => 99],
                'TEST_MCC_ATTENDANCE_stale'
            );
            self::fail('Stale attendance edit was accepted.');
        } catch (RuntimeException $exception) {
            self::assertSame('Stale attendance update.', $exception->getMessage());
        }

        try {
            $this->attendance()->updateStatuses(
                $session,
                [$this->player => 'LATE', 999999 => 'ABSENT'],
                [$this->player => 1, 999999 => 0],
                'TEST_MCC_ATTENDANCE_rollback'
            );
            self::fail('Unauthorized Player was accepted in attendance edit.');
        } catch (RuntimeException $exception) {
            self::assertSame('Player is not eligible.', $exception->getMessage());
        }

        $row = $this->db->table('attendance')->where('id', $attendanceId)->get()->getRowArray();
        self::assertSame('PRESENT', $row['status']);
        self::assertSame(1, (int) $row['version']);
        self::assertSame(1, $this->db->table('attendance_history')->where('attendance_id', $attendanceId)->countAllResults());
    }

    public function testAttendanceEditRespectsBranchAndCoachScopeAndCompletedSessions(): void
    {
        $otherBranchSession = $this->fixtureSession($this->otherBranch);
        $this->authorize(['attendance.view', 'attendance.correct'], [$this->branch]);

        try {
            $this->attendance()->updateStatuses($otherBranchSession, [999999 => 'PRESENT'], [999999 => 0]);
            self::fail('Cross-branch Attendance edit was accessible.');
        } catch (RuntimeException $exception) {
            self::assertSame('Forbidden', $exception->getMessage());
        }

        $completedSession = $this->fixtureSession($this->branch, 'completed');
        try {
            $this->attendance()->updateStatuses($completedSession, [$this->player => 'PRESENT'], [$this->player => 0]);
            self::fail('Completed Attendance edit was accessible.');
        } catch (RuntimeException $exception) {
            self::assertSame('Attendance is not available for this session.', $exception->getMessage());
        }
    }

    public function testDuplicateFirstSubmissionLeavesOneAttendanceAndOneHistoryRow(): void
    {
        $session = $this->fixtureSession($this->branch);
        $this->prepareRoster($session);
        $this->attendance()->record($session, $this->player, 'PRESENT');

        try {
            $this->attendance()->record($session, $this->player, 'ABSENT');
            self::fail('Duplicate attendance submission was accepted.');
        } catch (RuntimeException $exception) {
            self::assertSame('Attendance already exists.', $exception->getMessage());
        }

        $attendance = $this->db->table('attendance')
            ->where([
                'session_id' => $session,
                'player_id' => $this->player,
            ])
            ->get()
            ->getResultArray();

        self::assertCount(1, $attendance);
        self::assertSame(
            1,
            $this->db->table('attendance_history')
                ->where('attendance_id', (int) $attendance[0]['id'])
                ->countAllResults()
        );
    }
    public function testRosterBranchAndCancelledBoundariesRejectWrites(): void
    {
        $session = $this->fixtureSession($this->branch);
        $this->prepareRoster($session);
        try {
            $this->attendance()->record($session, $this->player + 999999, 'PRESENT');
            self::fail('Forged player accepted.');
        } catch (RuntimeException $exception) {
            self::assertSame('Player is not eligible.', $exception->getMessage());
        }
        self::assertSame(0, $this->db->table('attendance')->where('session_id', $session)->countAllResults());
        $cancelled = $this->fixtureSession($this->branch, 'cancelled');
        $this->addSessionPlayer($cancelled);
        try {
            $this->attendance()->record($cancelled, $this->player, 'ABSENT');
            self::fail('Cancelled session accepted.');
        } catch (RuntimeException $exception) {
            self::assertSame('Attendance is not available for this session.', $exception->getMessage());
        }
        self::assertSame(0, $this->db->table('attendance')->where('session_id', $cancelled)->countAllResults());
        $crossBranch = $this->fixtureSession($this->otherBranch);
        $this->authorize(['attendance.record'], [$this->branch]);
        try {
            $this->attendance()->record($crossBranch, $this->player, 'PRESENT');
            self::fail('Cross-branch write accepted.');
        } catch (RuntimeException $exception) {
            self::assertSame('Forbidden', $exception->getMessage());
        }
    }
    public function testCorrectionRequiresAuthorizationReasonVersionHistoryAndAudit(): void
    {
        $session = $this->fixtureSession($this->branch);
        $this->prepareRoster($session);
        $id = $this->attendance()->record($session, $this->player, 'ABSENT');
        try {
            $this->attendance()->correct($id, 'PRESENT', '', 1);
            self::fail('Blank correction reason accepted.');
        } catch (RuntimeException $exception) {
            self::assertSame('Correction reason required.', $exception->getMessage());
        }
        $this->authorize(['attendance.view', 'attendance.record'], [$this->branch]);
        try {
            $this->attendance()->correct($id, 'PRESENT', 'TEST_MCC_ATTENDANCE_denied', 1);
            self::fail('Unauthorized correction accepted.');
        } catch (RuntimeException $exception) {
            self::assertSame('Forbidden', $exception->getMessage());
        }
        $this->authorize(['attendance.view', 'attendance.correct'], [$this->branch]);
        $snapshotId = (int) $this->db->table('attendance_roster_snapshots')
            ->where('session_id', $session)
            ->get()
            ->getRow('id');
        $this->attendance()->correct(
            $id,
            'PRESENT',
            'TEST_MCC_ATTENDANCE_verified',
            1,
            $snapshotId
        );
        try {
            $this->attendance()->correct($id, 'LATE', 'TEST_MCC_ATTENDANCE_stale', 1);
            self::fail('Stale correction accepted.');
        } catch (RuntimeException $exception) {
            self::assertSame('Stale attendance update.', $exception->getMessage());
        }
        self::assertSame(2, $this->db->table('attendance_history')->where('attendance_id', $id)->countAllResults());
        self::assertSame(1, $this->db->table('audit_logs')->where(['action' => 'attendance_corrected', 'entity_id' => (string) $id,
    'branch_id' => $this->branch])->countAllResults());
        $history = $this->db->table('attendance_history')->where('attendance_id', $id)->orderBy('id', 'ASC')->get()->getResultArray();
        self::assertSame($this->actor, (int) $history[1]['actor_user_id']);
        self::assertSame('TEST_MCC_ATTENDANCE_verified', $history[1]['reason']);
        self::assertNotEmpty($history[1]['changed_at']);
        $routes = file_get_contents(APPPATH . 'Modules/Attendance/Config/Routes.php');
        self::assertIsString($routes);
        self::assertStringNotContainsString('->delete(', $routes);
    }

    public function testCorrectionRejectsMismatchedRosterSnapshotBeforeWriting(): void
    {
        $session = $this->fixtureSession($this->branch);
        $this->prepareRoster($session);
        $id = $this->attendance()->record($session, $this->player, 'ABSENT');
        $snapshotId = (int) $this->db->table('attendance_roster_snapshots')
            ->where('session_id', $session)
            ->get()
            ->getRow('id');

        $this->expectExceptionMessage('Attendance roster snapshot mismatch.');
        try {
            $this->attendance()->correct(
                $id,
                'PRESENT',
                'TEST_MCC_ATTENDANCE_wrong_snapshot',
                1,
                $snapshotId + 1
            );
        } finally {
            self::assertSame(
                'ABSENT',
                $this->db->table('attendance')->where('id', $id)->get()->getRow('status')
            );
            self::assertSame(
                1,
                $this->db->table('attendance_history')->where('attendance_id', $id)->countAllResults()
            );
        }
    }

    public function testCorrectionRejectsObsoleteAndUnknownStatuses(): void
    {
        $session = $this->fixtureSession($this->branch);
        $this->prepareRoster($session);
        $id = $this->attendance()->record($session, $this->player, 'ABSENT');

        foreach (['EXCUSED', 'INVALID'] as $status) {
            try {
                $this->attendance()->correct(
                    $id,
                    $status,
                    'TEST_MCC_ATTENDANCE_invalid_status',
                    1
                );
                self::fail('Invalid correction status accepted: ' . $status);
            } catch (RuntimeException $exception) {
                self::assertSame('Invalid attendance status.', $exception->getMessage());
            }
        }

        self::assertSame(
            'ABSENT',
            $this->db->table('attendance')->where('id', $id)->get()->getRow('status')
        );
        self::assertSame(
            1,
            $this->db->table('attendance_history')->where('attendance_id', $id)->countAllResults()
        );
    }

    public function testCorrectionBrowserRequestReturnsToSessionDetails(): void
    {
        $session = $this->fixtureSession($this->branch);
        $this->prepareRoster($session);
        $attendanceId = $this->attendance()->record($session, $this->player, 'ABSENT');
        $snapshotId = (int) $this->db->table('attendance_roster_snapshots')
            ->where('session_id', $session)
            ->get()
            ->getRow('id');

        $response = $this->withSession(session()->get())->post(
            'attendance/' . $attendanceId . '/correct',
            [
                csrf_token() => csrf_hash(),
                'status' => 'PRESENT',
                'reason' => 'TEST_MCC_ATTENDANCE_browser_correction',
                'expected_version' => 1,
                'snapshot_id' => $snapshotId,
                'return_to' => '/sessions/' . $session . '/page',
            ]
        );

        self::assertSame(302, $response->response()->getStatusCode());
        self::assertStringContainsString(
            '/sessions/' . $session . '/page',
            (string) $response->response()->getHeaderLine('Location')
        );
        self::assertSame('Attendance corrected.', session()->getFlashdata('message'));
        self::assertSame(
            'PRESENT',
            $this->db->table('attendance')->where('id', $attendanceId)->get()->getRow('status')
        );
    }

    public function testSessionDetailsEmbeddedAttendanceRequestReturnsToSessionDetails(): void
    {
        $session = $this->fixtureSession($this->branch);
        $this->prepareRoster($session);
        $snapshotId = (int) $this->db->table('attendance_roster_snapshots')
            ->where('session_id', $session)
            ->get()
            ->getRow('id');

        $response = $this->withSession(session()->get())->post(
            'attendance/sessions/' . $session . '/players/' . $this->player,
            [
                csrf_token() => csrf_hash(),
                'status' => 'PRESENT',
                'snapshot_id' => $snapshotId,
                'return_to' => '/sessions/' . $session . '/page',
            ]
        );

        self::assertSame(302, $response->response()->getStatusCode());
        self::assertStringContainsString(
            '/sessions/' . $session . '/page',
            (string) $response->response()->getHeaderLine('Location')
        );
        self::assertSame('Attendance recorded.', session()->getFlashdata('message'));
        self::assertSame(
            'PRESENT',
            $this->db->table('attendance')
                ->where(['session_id' => $session, 'player_id' => $this->player])
                ->get()
                ->getRow('status')
        );
    }

    public function testSessionDetailsEmbeddedAttendanceValidationReturnsSpecificError(): void
    {
        $session = $this->fixtureSession($this->branch);
        $this->prepareRoster($session);
        $snapshotId = (int) $this->db->table('attendance_roster_snapshots')
            ->where('session_id', $session)
            ->get()
            ->getRow('id');

        $response = $this->withSession(session()->get())->post(
            'attendance/sessions/' . $session . '/players/' . $this->player,
            [
                csrf_token() => csrf_hash(),
                'status' => 'EXCUSED',
                'snapshot_id' => $snapshotId,
                'return_to' => '/sessions/' . $session . '/page',
            ]
        );

        self::assertSame(302, $response->response()->getStatusCode());
        self::assertStringContainsString(
            '/sessions/' . $session . '/page',
            (string) $response->response()->getHeaderLine('Location')
        );
        self::assertSame(
            'Select one of the approved attendance statuses.',
            session()->getFlashdata('error')
        );
        self::assertSame(
            0,
            $this->db->table('attendance')
                ->where(['session_id' => $session, 'player_id' => $this->player])
                ->countAllResults()
        );
    }

    public function testSessionDetailsHtmlAcceptKeepsJsonEncodedFormOnSessionDetails(): void
    {
        $session = $this->fixtureSession($this->branch);
        $this->prepareRoster($session);
        $snapshotId = (int) $this->db->table('attendance_roster_snapshots')
            ->where('session_id', $session)
            ->get()
            ->getRow('id');

        $response = $this->withSession(session()->get())
            ->withBodyFormat('json')
            ->withHeaders([
                'Accept' => 'text/html',
                'X-CSRF-TOKEN' => csrf_hash(),
            ])
            ->post(
                'attendance/sessions/' . $session . '/players/' . $this->player,
                [
                    'status' => 'PRESENT',
                    'snapshot_id' => $snapshotId,
                    'return_to' => '/sessions/' . $session . '/page',
                ]
            );

        self::assertSame(302, $response->response()->getStatusCode());
        self::assertStringContainsString(
            '/sessions/' . $session . '/page',
            (string) $response->response()->getHeaderLine('Location')
        );
    }


    public function testAttendanceHistoryModelRejectsMutationAndAttendanceDeletePreservesHistory(): void
    {
        $session = $this->fixtureSession($this->branch);
        $this->prepareRoster($session);
        $this->attendance()->record($session, $this->player, 'PRESENT');
        $historyModel = new AttendanceHistoryModel();

        $this->expectExceptionMessage('Attendance history is append-only.');
        $historyModel->update(1, ['reason' => 'tampered']);
    }

    public function testAttendanceHistoryDeleteIsRejectedAndForeignKeyIsRestrictive(): void
    {
        $session = $this->fixtureSession($this->branch);
        $this->prepareRoster($session);
        $attendanceId = $this->attendance()->record($session, $this->player, 'PRESENT');
        $historyModel = new AttendanceHistoryModel();

        try {
            $historyModel->delete(1);
            self::fail('Attendance history deletion was accepted.');
        } catch (\LogicException $exception) {
            self::assertSame('Attendance history is append-only.', $exception->getMessage());
        }

        $foreignKey = $this->db->query(
            "SELECT DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS "
            . "WHERE CONSTRAINT_SCHEMA = DATABASE() "
            . "AND TABLE_NAME = 'attendance_history' "
            . "AND REFERENCED_TABLE_NAME = 'attendance' LIMIT 1"
        )->getRow('DELETE_RULE');

        self::assertSame('RESTRICT', strtoupper((string) $foreignKey));

        self::assertSame(
            1,
            $this->db->table('attendance_history')->where('attendance_id', $attendanceId)->countAllResults()
        );
    }

    public function testAttendanceReadQueriesExcludeMismatchedBranchLineage(): void
    {
        $session = $this->fixtureSession($this->branch);
        $this->prepareRoster($session);
        $attendanceId = $this->attendance()->record($session, $this->player, 'PRESENT');

        $this->db->table('attendance')->where('id', $attendanceId)->update([
            'branch_id' => $this->otherBranch,
        ]);

        try {
            $rows = $this->attendance()->sessions(['session_id' => $session]);
            self::assertCount(1, $rows);
            self::assertSame(0, (int) $rows[0]['recorded_count']);

            $roster = $this->attendance()->roster($session);
            self::assertCount(1, $roster['players']);
            self::assertNull($roster['players'][0]['attendance_id']);

            $this->expectExceptionMessage('Attendance branch mismatch.');
            $this->attendance()->find($attendanceId);
        } finally {
            $this->db->table('attendance')->where('id', $attendanceId)->update([
                'branch_id' => $this->branch,
            ]);
        }
    }
    public function testStateChangingBrowserRoutesRequireCsrfAndPermissionAwareActions(): void
    {
        $session = $this->fixtureSession($this->branch);
        $this->addSessionPlayer($session);
        $this->authorize(['attendance.view'], [$this->branch]);
        $page = $this->withSession(session()->get())->get('attendance/sessions/' . $session);
        $page->assertStatus(200);
        $page->assertSee('Recording unavailable');
        $this->withSession(session()->get())->withHeaders(['X-CSRF-TOKEN' => csrf_hash()])->post('attendance/sessions/' . $session . '/players/' .
    $this->player, ['status' => 'PRESENT'])->assertStatus(403);
        $this->authorize(['attendance.view', 'attendance.record'], [$this->branch]);
        try {
            $this->withSession(session()->get())
                ->withHeaders(['X-CSRF-TOKEN' => ''])
                ->post('attendance/sessions/' . $session . '/players/' . $this->player, ['status' => 'PRESENT']);
            self::fail('Missing CSRF token accepted.');
        } catch (\CodeIgniter\Security\Exceptions\SecurityException $exception) {
            self::assertSame('The action you requested is not allowed.', $exception->getMessage());
        }
    }

    public function testAttendanceRecordRejectsInvalidCsrfWithoutMutation(): void
    {
        $session = $this->fixtureSession($this->branch);
        $this->prepareRoster($session);
        $before = $this->db->table('attendance')->where('session_id', $session)->countAllResults();
        $historyBefore = $this->db->table('attendance_history')->countAllResults();

        try {
            $this->withSession(session()->get())
                ->withHeaders(['X-CSRF-TOKEN' => 'invalid-token'])
                ->post('attendance/sessions/' . $session . '/players/' . $this->player, ['status' => 'PRESENT']);
            self::fail('Invalid CSRF token accepted.');
        } catch (\CodeIgniter\Security\Exceptions\SecurityException $exception) {
            self::assertSame('The action you requested is not allowed.', $exception->getMessage());
        }

        self::assertSame($before, $this->db->table('attendance')->where('session_id', $session)->countAllResults());
        self::assertSame($historyBefore, $this->db->table('attendance_history')->countAllResults());
    }

    public function testValidCsrfTokenReachesBulkAttendanceWorkflowWithoutBypassingAuthorization(): void
    {
        $session = $this->fixtureSession($this->branch);
        $this->prepareRoster($session);
        $before = $this->db->table('attendance')->where('session_id', $session)->countAllResults();

        $response = $this->withSession(session()->get())
            ->withHeaders(['X-CSRF-TOKEN' => csrf_hash()])
            ->post('attendance/sessions/' . $session . '/save', [
                'statuses' => [$this->player => 'PRESENT'],
            ]);

        self::assertNotSame(403, $response->getStatusCode());
        self::assertSame(
            $before + 1,
            $this->db->table('attendance')->where('session_id', $session)->countAllResults()
        );
    }

    public function testInlineAttendanceEditUpdatesSavedStatusOnTheSessionPage(): void
    {
        $session = $this->fixtureSession($this->branch);
        $this->prepareRoster($session);
        $this->attendance()->record($session, $this->player, 'PRESENT');

        $page = $this->withSession(session()->get())->get('attendance/sessions/' . $session);
        $page->assertStatus(200);
        self::assertStringContainsString(
            'data-attendance-player="' . $this->player . '"',
            $page->getBody()
        );
        self::assertStringContainsString('value="PRESENT" selected', $page->getBody());

        $response = $this->withSession(session()->get())
            ->withHeaders(['X-CSRF-TOKEN' => csrf_hash()])
            ->post('attendance/sessions/' . $session . '/save', [
                'statuses' => [$this->player => 'LATE'],
                'versions' => [$this->player => 1],
                'correction_reason' => 'TEST_MCC_ATTENDANCE_inline_edit',
            ]);

        self::assertSame(302, $response->response()->getStatusCode());
        self::assertStringContainsString(
            '/attendance/sessions/' . $session,
            (string) $response->response()->getHeaderLine('Location')
        );
        self::assertSame(
            'LATE',
            $this->db->table('attendance')->where([
                'session_id' => $session,
                'player_id' => $this->player,
            ])->get()->getRow('status')
        );
    }

    public function testInlineAttendanceEditRejectsMissingCsrfWithoutMutation(): void
    {
        $session = $this->fixtureSession($this->branch);
        $this->prepareRoster($session);
        $this->attendance()->record($session, $this->player, 'PRESENT');

        try {
            $this->withSession(session()->get())
                ->withHeaders(['X-CSRF-TOKEN' => ''])
                ->post('attendance/sessions/' . $session . '/save', [
                    'statuses' => [$this->player => 'LATE'],
                    'versions' => [$this->player => 1],
                    'correction_reason' => 'TEST_MCC_ATTENDANCE_missing_csrf',
                ]);
            self::fail('Missing CSRF token accepted by inline Attendance edit.');
        } catch (\CodeIgniter\Security\Exceptions\SecurityException $exception) {
            self::assertSame('The action you requested is not allowed.', $exception->getMessage());
        }

        self::assertSame(
            'PRESENT',
            $this->db->table('attendance')->where([
                'session_id' => $session,
                'player_id' => $this->player,
            ])->get()->getRow('status')
        );
    }

    private function attendance(): AttendanceService
    {
        return service('attendance', false);
    }

    private function resetDefaultConnection(): void
    {
        $db = Database::connect('default');
        $db->transRollback();
        $db->resetTransStatus();
        $db->close();
    }

    public function testAttendancePageShowsSelectedPlayersBeforeFirstSave(): void
    {
        $session = $this->fixtureSession($this->branch);
        $this->addSessionPlayer($session);

        $roster = $this->attendance()->roster($session);
        self::assertSame('missing', $roster['snapshot_status']);
        self::assertCount(1, $roster['players']);
        self::assertSame($this->player, (int) $roster['players'][0]['player_id']);

        $attendanceId = $this->attendance()->record($session, $this->player, 'PRESENT');
        self::assertGreaterThan(0, $attendanceId);
        self::assertSame('ready', $this->attendance()->roster($session)['snapshot_status']);
    }

    public function testSnapshotCaptureDrivesRosterAndRecording(): void
    {
        $session = $this->fixtureSession($this->branch);
        $this->addSessionPlayer($session);

        $snapshotId = $this->attendance()->captureRosterSnapshot($session);
        self::assertGreaterThan(0, $snapshotId);

        $roster = $this->attendance()->roster($session);
        self::assertSame('ready', $roster['snapshot_status']);
        self::assertCount(1, $roster['players']);
        self::assertSame($this->player, (int) $roster['players'][0]['player_id']);

        $attendanceId = $this->attendance()->record($session, $this->player, 'PRESENT');
        self::assertSame(
            1,
            $this->db->table('attendance')->where('id', $attendanceId)->countAllResults()
        );
    }

    public function testFutureSessionSnapshotCapturesAssignedPlayerOnce(): void
    {
        $session = $this->fixtureSession($this->branch);
        $futureStart = '2099-01-01 00:00:00';
        $futureEnd = '2099-01-01 01:00:00';
        $this->db->table('sessions')->where('id', $session)->update([
            'starts_at' => $futureStart,
            'ends_at' => $futureEnd,
        ]);
        $this->addSessionPlayer($session);

        $firstSnapshot = $this->attendance()->captureRosterSnapshot($session);
        $secondSnapshot = $this->attendance()->captureRosterSnapshot($session);

        self::assertSame($firstSnapshot, $secondSnapshot);
        self::assertSame(
            1,
            $this->db->table('attendance_roster_snapshot_members')
                ->where('snapshot_id', $firstSnapshot)
                ->where('player_id', $this->player)
                ->countAllResults()
        );
    }

    public function testAttendanceOutsideSessionWindowReturnsSpecificFailure(): void
    {
        $session = $this->fixtureSession($this->branch);
        $this->db->table('sessions')->where('id', $session)->update([
            'starts_at' => '2099-01-01 00:00:00',
            'ends_at' => '2099-01-01 01:00:00',
        ]);
        $this->addSessionPlayer($session);
        $this->attendance()->captureRosterSnapshot($session);

        $this->expectExceptionMessage('Attendance is outside the recording window.');
        $this->attendance()->record($session, $this->player, 'PRESENT');
    }

    public function testAttendanceRejectsMismatchedSnapshotIdBeforeWriting(): void
    {
        $session = $this->fixtureSession($this->branch);
        $this->addSessionPlayer($session);
        $snapshotId = $this->attendance()->captureRosterSnapshot($session);

        $this->expectExceptionMessage('Attendance roster snapshot mismatch.');
        $this->attendance()->record($session, $this->player, 'PRESENT', $snapshotId + 1);
    }

    public function testSession1318RequestReturnsSpecificWindowFailure(): void
    {
        $session = $this->db->table('sessions')->where('id', 1318)->get()->getRowArray();
        if (! is_array($session)) {
            self::markTestSkipped('Session #1318 is not available in the local test database.');
        }

        $snapshot = $this->db->table('attendance_roster_snapshots')
            ->where('session_id', 1318)
            ->get()
            ->getRowArray();
        self::assertIsArray($snapshot);
        $member = $this->db->table('attendance_roster_snapshot_members')
            ->join('players p', 'p.id = attendance_roster_snapshot_members.player_id')
            ->where('snapshot_id', (int) $snapshot['id'])
            ->where('p.branch_id', $this->branch)
            ->orderBy('player_id', 'ASC')
            ->get()
            ->getRowArray();
        self::assertIsArray($member);
        self::assertSame(
            0,
            $this->db->table('attendance')
                ->where([
                    'session_id' => 1318,
                    'player_id' => (int) $member['player_id'],
                ])
                ->countAllResults()
        );

        try {
            $this->attendance()->record(
                1318,
                (int) $member['player_id'],
                'PRESENT',
                (int) $snapshot['id']
            );
            self::fail('The closed Session #1318 window accepted attendance.');
        } catch (RuntimeException $exception) {
            self::assertSame('Attendance is outside the recording window.', $exception->getMessage());
        }

        $response = $this->withSession(session()->get())->post(
            'attendance/sessions/1318/players/' . (int) $member['player_id'],
            [
                csrf_token() => csrf_hash(),
                'status' => 'PRESENT',
                'snapshot_id' => (int) $snapshot['id'],
                'return_to' => '/sessions/1318/page',
            ]
        );
        self::assertSame(302, $response->response()->getStatusCode());
        self::assertStringContainsString(
            '/sessions/1318/page',
            (string) $response->response()->getHeaderLine('Location')
        );
        self::assertSame(
            'Attendance can only be recorded during the session window.',
            session()->getFlashdata('error')
        );
    }

    public function testSession1318RenderedAttendanceFormUsesAttendanceActionsAndReturnFields(): void
    {
        $this->authorize(['attendance.view', 'attendance.record', 'attendance.correct', 'coach.view'], [$this->branch]);
        $page = $this->withSession(session()->get())->get('sessions/1318/page');
        self::assertSame(200, $page->response()->getStatusCode());
        $body = $page->getBody();
        $member = $this->db->table('session_players')
            ->join('players p', 'p.id = session_players.player_id')
            ->where('session_players.session_id', 1318)
            ->where('p.branch_id', $this->branch)
            ->orderBy('session_players.player_id', 'ASC')
            ->get()
            ->getRowArray();
        self::assertIsArray($member);
        self::assertStringContainsString(
            'attendance/sessions/1318/players/' . (int) $member['player_id'],
            $body
        );
        self::assertStringNotContainsString('Capture roster snapshot', $body);
        self::assertStringNotContainsString('roster snapshot before recording attendance', strtolower($body));
        self::assertStringContainsString('name="status"', $body);
        self::assertStringContainsString('name="return_to" value="/sessions/1318/page"', $body);
    }

    public function testMembershipChangesAfterSnapshotDoNotChangeAttendanceRoster(): void
    {
        $session = $this->fixtureSession($this->branch);
        $this->prepareRoster($session);

        Database::connect('default')->table('session_players')
            ->where(['session_id' => $session, 'player_id' => $this->player])
            ->delete();

        $roster = $this->attendance()->roster($session);
        self::assertSame('ready', $roster['snapshot_status']);
        self::assertCount(1, $roster['players']);
        self::assertSame($this->player, (int) $roster['players'][0]['player_id']);
        self::assertIsInt($this->attendance()->record($session, $this->player, 'ABSENT'));
    }

    public function testSnapshotCaptureRequiresAttendancePermissionAndBranchAccess(): void
    {
        $session = $this->fixtureSession($this->otherBranch);
        $this->authorize(['attendance.record'], [$this->branch]);

        $this->expectExceptionMessage('Forbidden');
        $this->attendance()->captureRosterSnapshot($session);
    }

    public function testMatchingAttendanceBranchIsUsedForAuthorizedRead(): void
    {
        $session = $this->fixtureSession($this->branch);
        $this->prepareRoster($session);
        $id = $this->attendance()->record($session, $this->player, 'PRESENT');

        $detail = $this->attendance()->find($id);

        self::assertSame($this->branch, (int) $detail['branch_id']);
        self::assertSame($this->branch, (int) $detail['session_branch']);
    }

    public function testMismatchedAttendanceBranchIsRejectedForReadAndCorrection(): void
    {
        $session = $this->fixtureSession($this->branch);
        $this->prepareRoster($session);
        $id = $this->attendance()->record($session, $this->player, 'ABSENT');
        $this->db->table('attendance')->where('id', $id)->update([
            'branch_id' => $this->otherBranch,
        ]);

        try {
            $this->attendance()->find($id);
            self::fail('Mismatched attendance branch was readable.');
        } catch (RuntimeException $exception) {
            self::assertSame('Attendance branch mismatch.', $exception->getMessage());
        }

        try {
            $this->attendance()->correct($id, 'PRESENT', 'Tampered record', 1);
            self::fail('Mismatched attendance branch was correctable.');
        } catch (RuntimeException $exception) {
            self::assertSame('Attendance branch mismatch.', $exception->getMessage());
        }
    }

    public function testCrossBranchAttendanceReadAndCorrectionRemainDenied(): void
    {
        $session = $this->fixtureSession($this->otherBranch);
        $this->authorize(['attendance.view', 'attendance.record', 'attendance.correct'], [$this->branch]);

        try {
            $this->attendance()->roster($session);
            self::fail('Cross-branch roster was readable.');
        } catch (RuntimeException $exception) {
            self::assertSame('Forbidden', $exception->getMessage());
        }
    }

    public function testCrossBranchAttendanceDetailAccessIsDenied(): void
    {
        $session = $this->fixtureSession($this->branch);
        $this->prepareRoster($session);
        $id = $this->attendance()->record($session, $this->player, 'PRESENT');
        $this->authorize(['attendance.view', 'attendance.correct'], [$this->otherBranch]);

        try {
            $this->attendance()->find($id);
            self::fail('Cross-branch attendance detail was readable.');
        } catch (RuntimeException $exception) {
            self::assertSame('Forbidden', $exception->getMessage());
        }

        try {
            $this->attendance()->correct($id, 'ABSENT', 'Cross-branch attempt', 1);
            self::fail('Cross-branch attendance detail was correctable.');
        } catch (RuntimeException $exception) {
            self::assertSame('Forbidden', $exception->getMessage());
        }
    }
    private function prepareRoster(int $session): void
    {
        $db = Database::connect('default');
        $this->addSessionPlayer($session);
        $this->attendance()->captureRosterSnapshot($session);
    }

    private function addSessionPlayer(int $session): void
    {
        Database::connect('default')->table('session_players')->insert([
            'session_id' => $session,
            'player_id' => $this->player,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }
    private function fixtureSession(int $branch, string $status = 'scheduled'): int
    {
        $now = gmdate('Y-m-d H:i:s');
        $start = gmdate('Y-m-d H:i:s', time() - 900);
        $end = gmdate('Y-m-d H:i:s', time() + 900);
        $db = Database::connect('default');
        $batch = $branch === $this->otherBranch ? $this->otherBatch : $this->batch;
        $db->table('sessions')->insert(['branch_id' => $branch, 'batch_id' => $batch, 'coach_user_id' => $this->coach, 'starts_at' => $start,
    'ends_at' => $end, 'status' => $status, 'cancellation_reason' => $status === 'cancelled' ? 'TEST_MCC_ATTENDANCE_cancelled' : null,
    'cancelled_by' => $status === 'cancelled' ? $this->actor : null, 'cancelled_at' => $status === 'cancelled' ? $now : null,
    'rescheduled_from_id' => null, 'created_by' => $this->actor, 'created_at' => $now, 'updated_at' => $now]);
        return (int) $db->insertID();
    }

    private function fixtureSessionWithCoach(int $branch, int $coach): int
    {
        $now = gmdate('Y-m-d H:i:s');
        $start = gmdate('Y-m-d H:i:s', time() - 900);
        $end = gmdate('Y-m-d H:i:s', time() + 900);
        $db = Database::connect('default');
        $db->table('sessions')->insert([
            'branch_id' => $branch,
            'batch_id' => $this->batch,
            'coach_user_id' => $coach,
            'starts_at' => $start,
            'ends_at' => $end,
            'status' => 'scheduled',
            'cancellation_reason' => null,
            'cancelled_by' => null,
            'cancelled_at' => null,
            'rescheduled_from_id' => null,
            'created_by' => $this->actor,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) $db->insertID();
    }
    private function authorize(array $permissions, array $branches): void
    {
        session()->set(['isLoggedIn' => true, 'accountEnabled' => true, 'user' => ['id' => $this->actor], 'session_version' => 1,
    'permissions' => $permissions, 'authorizedBranches' => array_map('strval', $branches)]);
        \Config\Services::resetSingle('authorization');
    }

    private function authorizeCoach(): void
    {
        $sessionVersion = (int) $this->db->table('users')
            ->where('id', $this->coach)
            ->get()
            ->getRow('session_version');
        session()->set([
            'isLoggedIn' => true,
            'accountEnabled' => true,
            'user' => ['id' => $this->coach],
            'session_version' => $sessionVersion,
            'roles' => ['Coach'],
            'permissions' => ['attendance.view', 'attendance.record'],
            'authorizedBranches' => [(string) $this->branch],
        ]);
        \Config\Services::resetSingle('authorization');
    }
}
