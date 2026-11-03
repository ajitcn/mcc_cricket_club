<?php

declare (strict_types=1);
namespace Tests\Database;

use App\Models\UserModel;
use App\Services\AttendanceService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Database;
use RuntimeException;
final class AttendanceIntegrationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    protected $DBGroup = 'tests';
    protected $migrate = false;
    private int $north;
    private int $south;
    private int $actor;
    private int $batch;
    private int $player;
    private int $attendanceSession;
    private string $fixtureKey;
    protected function setUp(): void
    {
        parent::setUp();
        $this->resetDefaultConnection();
        $this->db = Database::connect('default');
        $this->fixtureKey = str_replace('.', '', uniqid('att_', true));
        $n = gmdate('Y-m-d H:i:s');
        $this->db->table('branches')->insert([
            'name' => 'North ' . $this->fixtureKey,
            'code' => 'att-north-' . $this->fixtureKey,
            'is_active' => 1,
            'created_at' => $n,
            'updated_at' => $n,
        ]);
        $this->north = (int) $this->db->insertID();
        $this->db->table('branches')->insert([
            'name' => 'South ' . $this->fixtureKey,
            'code' => 'att-south-' . $this->fixtureKey,
            'is_active' => 1,
            'created_at' => $n,
            'updated_at' => $n,
        ]);
        $this->south = (int) $this->db->insertID();
        $this->actor = $this->user('attendance-' . $this->fixtureKey . '@example.test');
        $this->db->table('user_branches')->insert([
            'user_id' => $this->actor,
            'branch_id' => $this->north,
            'created_at' => $n,
        ]);
        $this->db->table('batches')->insert([
            'branch_id' => $this->north,
            'name' => 'Attendance batch ' . $this->fixtureKey,
            'created_at' => $n,
            'updated_at' => $n,
        ]);
        $this->batch = (int) $this->db->insertID();
        $this->db->table('leads')->insert([
            'branch_id' => $this->north,
            'owner_user_id' => $this->actor,
            'source' => 'test',
            'full_name' => 'Attendance Lead ' . $this->fixtureKey,
            'status' => 'lead',
            'created_at' => $n,
            'updated_at' => $n,
        ]);
        $lead = (int) $this->db->insertID();
        $this->db->table('persons')->insert([
            'full_name' => 'Attendance Player ' . $this->fixtureKey,
            'created_at' => $n,
            'updated_at' => $n,
        ]);
        $person = (int) $this->db->insertID();
        $this->db->table('players')->insert([
            'person_id' => $person,
            'lead_id' => $lead,
            'branch_id' => $this->north,
            'status' => 'active',
            'created_at' => $n,
            'updated_at' => $n,
        ]);
        $this->player = (int) $this->db->insertID();
        $this->db->table('player_batch_assignments')->insert([
            'player_id' => $this->player,
            'branch_id' => $this->north,
            'batch_id' => $this->batch,
            'assigned_by' => $this->actor,
            'effective_from' => '2026-01-01 00:00:00',
            'created_at' => $n,
            'updated_at' => $n,
        ]);
        $this->attendanceSession = $this->makeSession($this->north, 'scheduled');
        $this->db->table('session_players')->insert(['session_id' => $this->attendanceSession, 'player_id' => $this->player, 'created_at' => $n]);
        $this->login(['attendance.view', 'attendance.record', 'attendance.correct'], [$this->north]);
        $this->captureSnapshot($this->attendanceSession);
    }
    protected function tearDown(): void
    {
        session()->destroy();
        \Config\Services::resetSingle('authorization');
        \Config\Services::resetSingle('request');
        \Config\Services::resetSingle('response');
        parent::tearDown();
    }
    public function testAuthorizedRecordPersistsHistoryAuditAndUtcPresentation(): void
    {
        $id = $this->attendance()->record($this->attendanceSession, $this->player, 'PRESENT');
        $r = $this->db->table('attendance')->where('id', $id)->get()->getRowArray();
        self::assertSame('PRESENT', $r['status']);
        self::assertSame($this->north, (int) $r['branch_id']);
        self::assertSame(1, $this->db->table('attendance_history')->where('attendance_id', $id)->countAllResults());
        self::assertSame(1, $this->db->table('audit_logs')->where(['action' => 'attendance_recorded', 'entity_id' => (string) $id])->countAllResults());
        $v = $this->attendance()->find($id);
        self::assertSame($r['recorded_at'], $v['recorded_at_utc']);
        self::assertArrayHasKey('recorded_at_local', $v);
    }
    public function testApprovedStatusesAcceptedAndExtraStatusRejected(): void
    {
        foreach (['PRESENT', 'ABSENT', 'LEAVE', 'MEDICAL', 'LATE', 'RAIN_CANCELLED', 'COACH_CANCELLED'] as $status) {
            $s = $this->makeSession($this->north, 'scheduled');
            $this->db->table('session_players')->insert(['session_id' => $s, 'player_id' => $this->player, 'created_at' => gmdate('Y-m-d H:i:s')]);
            $this->captureSnapshot($s);
            self::assertIsInt($this->attendance()->record($s, $this->player, $status));
        }
        $this->expectExceptionMessage('Invalid attendance status.');
        $this->attendance()->record($this->attendanceSession, $this->player, 'INVALID');
    }
    public function testUnauthorizedAndCrossBranchReadsAreDenied(): void
    {
        $this->login([], [$this->north]);
        $this->expectExceptionMessage('Forbidden');
        $this->attendance()->record($this->attendanceSession, $this->player, 'ABSENT');
    }
    public function testDuplicateAndForgedPlayerAreRejected(): void
    {
        $this->attendance()->record($this->attendanceSession, $this->player, 'PRESENT');
        try {
            $this->attendance()->record($this->attendanceSession, $this->player, 'ABSENT');
            self::fail('Duplicate accepted.');
        } catch (RuntimeException $e) {
            self::assertSame('Attendance already exists.', $e->getMessage());
        }
        $this->expectExceptionMessage('Player is not eligible.');
        $this->attendance()->record($this->attendanceSession, $this->player + 999, 'ABSENT');
    }
    public function testCancelledSessionCannotBeRecorded(): void
    {
        $this->db->table('sessions')->where('id', $this->attendanceSession)->update(['status' => 'cancelled']);
        $this->expectExceptionMessage('Attendance is not available for this session.');
        $this->attendance()->record($this->attendanceSession, $this->player, 'ABSENT');
    }
    public function testCorrectionReasonHistoryAuditAndStaleProtection(): void
    {
        $id = $this->attendance()->record($this->attendanceSession, $this->player, 'ABSENT');
        $this->attendance()->correct($id, 'PRESENT', 'Verified', 1);
        $r = $this->db->table('attendance')->where('id', $id)->get()->getRowArray();
        self::assertSame('PRESENT', $r['status']);
        self::assertSame(2, (int) $r['version']);
        self::assertSame(2, $this->db->table('attendance_history')->where('attendance_id', $id)->countAllResults());
        self::assertSame(1, $this->db->table('audit_logs')->where(['action' => 'attendance_corrected', 'entity_id' => (string) $id])->countAllResults());
        $this->expectExceptionMessage('Stale attendance update.');
        $this->attendance()->correct($id, 'LEAVE', 'Stale', 1);
    }
    public function testCorrectionRequiresReasonAndPermission(): void
    {
        $id = $this->attendance()->record($this->attendanceSession, $this->player, 'ABSENT');
        try {
            $this->attendance()->correct($id, 'PRESENT', '', 1);
            self::fail('Missing correction reason accepted.');
        } catch (RuntimeException $e) {
            self::assertSame('Correction reason required.', $e->getMessage());
        }
        $this->login(['attendance.view'], [$this->north]);
        $this->expectExceptionMessage('Forbidden');
        $this->attendance()->correct($id, 'PRESENT', 'Denied', 1);
    }
    public function testInvalidRosterRollsBackAttendanceAndHistory(): void
    {
        try {
            $this->attendance()->record($this->attendanceSession, $this->player + 999, 'ABSENT');
            self::fail('Invalid player accepted.');
        } catch (RuntimeException $e) {
            self::assertSame('Player is not eligible.', $e->getMessage());
        }
        self::assertSame(0, $this->attendanceCount());
        self::assertSame(0, $this->attendanceHistoryCount());
    }
    public function testCrossBranchSessionCannotBeRecorded(): void
    {
        $s = $this->makeSession($this->south, 'scheduled');
        $this->login(['attendance.view', 'attendance.record'], [$this->north]);
        $this->expectExceptionMessage('Forbidden');
        $this->attendance()->record($s, $this->player, 'ABSENT');
    }
    public function testBeforeStartAndAfterEndAreRejected(): void
    {
        $this->db->table('sessions')->where('id', $this->attendanceSession)->update(['starts_at' => '2099-01-01 00:00:00', 'ends_at' => '2099-01-01 01:00:00']);
        $this->expectExceptionMessage('Attendance is outside the recording window.');
        $this->attendance()->record($this->attendanceSession, $this->player, 'PRESENT');
    }
    public function testAuthorizedAttendancePagesRenderRosterAndCsrfForm(): void
    {
        $response = $this->withSession(session()->get())->get('attendance/sessions/' . $this->attendanceSession);
        $response->assertStatus(200);
        $response->assertSee('Attendance roster');
        $response->assertSee('Attendance Player');
        $response->assertSee('csrf_test_name');
    }
    public function testUnauthenticatedAttendancePageIsDenied(): void
    {
        $response = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])->get('attendance');
        $response->assertStatus(401);
    }
    public function testAuthorizedSessionListRendersBranchBatchCoachAndLocalTime(): void
    {
        $response = $this->withSession(session()->get())->get('attendance');
        $response->assertStatus(200);
        $response->assertSee('Attendance sessions');
        $response->assertSee('North');
        $response->assertSee('Attendance batch');
        $response->assertSee('Attendance User');
        $response->assertDontSee('MCC time');
    }
    public function testSessionListFiltersRemainServerSideAndSafe(): void
    {
        $response = $this->withSession(session()->get())->get('attendance?status=cancelled');
        $response->assertStatus(200);
        $response->assertSee('No sessions match the selected filters.');
        $response = $this->withSession(session()->get())->get('attendance?branch_id=' . $this->south);
        $response->assertStatus(200);
        $response->assertSee('No sessions match the selected filters.');
        $response = $this->withSession(session()->get())->get('attendance?status=unknown');
        $response->assertStatus(422);
        $response->assertSee('Attendance request could not be completed.');
    }
    public function testPermissionAwareRosterControlsAreVisibleOnlyWhenAllowed(): void
    {
        $this->login(['attendance.view'], [$this->north]);
        $response = $this->withSession(session()->get())->get('attendance/sessions/' . $this->attendanceSession);
        $response->assertStatus(200);
        $response->assertSee('Not recorded');
        $response->assertSee('Recording unavailable');
        $response->assertDontSee('name="status"');
        $this->login(['attendance.view', 'attendance.record'], [$this->north]);
        $response = $this->withSession(session()->get())->get('attendance/sessions/' . $this->attendanceSession);
        $response->assertSee('Record attendance');
        $this->attendance()->record($this->attendanceSession, $this->player, 'ABSENT');
        $this->login(['attendance.view', 'attendance.correct'], [$this->north]);
        $response = $this->withSession(session()->get())->get('attendance/sessions/' . $this->attendanceSession);
        $response->assertSee('ABSENT');
        $response->assertSee('Submit correction');
        $response->assertSee('name="reason"');
        $response->assertSee('value="ABSENT" selected');
    }
    public function testBrowserForgedPlayerAndCorrectionIdsCannotBypassSecurity(): void
    {
        $response = $this->browserPost('attendance/sessions/' . $this->attendanceSession . '/players/' . ($this->player + 999), ['status' => 'PRESENT']);
        $response->assertStatus(302);
        self::assertSame(0, $this->attendanceCount());
        $this->attendance()->record($this->attendanceSession, $this->player, 'ABSENT');
        $response = $this->browserPost('attendance/999999/correct', ['status' => 'PRESENT', 'reason' => 'Nope', 'expected_version' => 1]);
        $response->assertStatus(302);
        self::assertSame(1, $this->attendanceCount());
        $id = (int) $this->db->table('attendance')
            ->where('session_id', $this->attendanceSession)
            ->get()
            ->getRow()
            ->id;
        $response = $this->browserPost('attendance/' . $id . '/correct', ['status' => 'INVALID', 'reason' => 'Bad status', 'expected_version' => 1]);
        $response->assertStatus(302);
        self::assertSame(
            'ABSENT',
            $this->db->table('attendance')
                ->where('session_id', $this->attendanceSession)
                ->get()
                ->getRow()
                ->status
        );
    }
    public function testBrowserRecordingAcceptsEveryApprovedStatus(): void
    {
        foreach (['PRESENT', 'ABSENT', 'LEAVE', 'MEDICAL', 'LATE', 'RAIN_CANCELLED', 'COACH_CANCELLED'] as $status) {
            $s = $this->makeSession($this->north, 'scheduled');
            $this->db->table('session_players')->insert(['session_id' => $s, 'player_id' => $this->player, 'created_at' => gmdate('Y-m-d H:i:s')]);
            $this->captureSnapshot($s);
            $response = $this->browserPost('attendance/sessions/' . $s . '/players/' . $this->player, ['status' => $status]);
            $response->assertStatus(302);
            self::assertSame(1, $this->db->table('attendance')->where(['session_id' => $s, 'player_id' => $this->player])->countAllResults());
        }
    }
    public function testBrowserInvalidMissingAndDuplicateRecordingAreSafe(): void
    {
        $response = $this->browserPost('attendance/sessions/' . $this->attendanceSession . '/players/' . $this->player, ['status' => 'INVALID']);
        $response->assertStatus(302);
        self::assertSame(0, $this->attendanceCount());
        $this->attendance()->record($this->attendanceSession, $this->player, 'PRESENT');
        $response = $this->browserPost('attendance/sessions/' . $this->attendanceSession . '/players/' . $this->player, ['status' => 'ABSENT']);
        $response->assertStatus(302);
        self::assertSame(1, $this->attendanceCount());
        $response = $this->browserPost('attendance/sessions/' . $this->attendanceSession . '/players/' . $this->player, []);
        $response->assertStatus(302);
        self::assertSame(1, $this->attendanceCount());
    }
    public function testBrowserCsrfFailureDoesNotPersistAttendance(): void
    {
        try {
            $this->withSession(session()->get())->post('attendance/sessions/' . $this->attendanceSession . '/players/' . $this->player,
    ['status' => 'PRESENT']);
            self::fail('Missing CSRF token accepted.');
        } catch (\CodeIgniter\Security\Exceptions\SecurityException $exception) {
            self::assertSame('The action you requested is not allowed.', $exception->getMessage());
        }
        self::assertSame(0, $this->attendanceCount());
        self::assertSame(0, $this->auditCount('attendance_recorded'));
    }
    public function testBrowserCancelledAndCrossBranchFailuresAreSafe(): void
    {
        $this->db->table('sessions')->where('id', $this->attendanceSession)->update(['status' => 'cancelled']);
        $response = $this->browserPost('attendance/sessions/' . $this->attendanceSession . '/players/' . $this->player, ['status' => 'ABSENT']);
        $response->assertStatus(302);
        self::assertSame(0, $this->attendanceCount());
        $this->login(['attendance.view', 'attendance.record'], [$this->south]);
        $response = $this->browserPost('attendance/sessions/' . $this->attendanceSession . '/players/' . $this->player, ['status' => 'ABSENT']);
        $response->assertStatus(403);
        self::assertSame(0, $this->attendanceCount());
    }
    public function testBrowserCorrectionCoversPermissionReasonStaleAndSuccess(): void
    {
        $id = $this->attendance()->record($this->attendanceSession, $this->player, 'ABSENT');
        $this->login(['attendance.view', 'attendance.record'], [$this->north]);
        $response = $this->browserPost('attendance/' . $id . '/correct', ['status' => 'PRESENT', 'reason' => 'Denied', 'expected_version' => 1]);
        $response->assertStatus(403);
        $this->login(['attendance.view', 'attendance.record', 'attendance.correct'], [$this->north]);
        $response = $this->browserPost('attendance/' . $id . '/correct', ['status' => 'PRESENT', 'reason' => '', 'expected_version' => 1]);
        $response->assertStatus(302);
        $response = $this->browserPost('attendance/' . $id . '/correct', ['status' => 'PRESENT', 'reason' => 'Stale', 'expected_version' => 0]);
        $response->assertStatus(302);
        $response = $this->browserPost('attendance/' . $id . '/correct', ['status' => 'PRESENT', 'reason' => 'Verified', 'expected_version' => 1]);
        $response->assertStatus(302);
        $row = $this->db->table('attendance')->where('id', $id)->get()->getRowArray();
        self::assertSame('PRESENT', $row['status']);
        self::assertSame(2, (int) $row['version']);
        self::assertSame(2, $this->db->table('attendance_history')->where('attendance_id', $id)->countAllResults());
    }
    public function testBrowserRedirectsRenderSafeFlashMessagesOnNextRosterGet(): void
    {
        $response = $this->browserPost('attendance/sessions/' . $this->attendanceSession . '/players/' . $this->player, ['status' => 'PRESENT']);
        $response->assertStatus(302);
        $page = $this->withSession($_SESSION)->get('attendance/sessions/' . $this->attendanceSession);
        $page->assertStatus(200);
        $page->assertSee('Attendance recorded.');
        $response = $this->browserPost('attendance/sessions/' . $this->attendanceSession . '/players/' . $this->player, ['status' => 'ABSENT']);
        $response->assertStatus(302);
        $page = $this->withSession($_SESSION)->get('attendance/sessions/' . $this->attendanceSession);
        $page->assertSee('Attendance already recorded for every player in this roster.');
        $id = (int) $this->db->table('attendance')
            ->where('session_id', $this->attendanceSession)
            ->get()
            ->getRow()
            ->id;
        $response = $this->browserPost('attendance/' . $id . '/correct', ['status' => 'LATE', 'reason' => 'Verified', 'expected_version' => 1]);
        $response->assertStatus(302);
        $page = $this->withSession($_SESSION)->get('attendance/sessions/' . $this->attendanceSession);
        $page->assertSee('Attendance corrected.');
        $response = $this->browserPost('attendance/' . $id . '/correct', ['status' => 'ABSENT', 'reason' => '', 'expected_version' => 2]);
        $response->assertStatus(302);
        $page = $this->withSession($_SESSION)->get('attendance/sessions/' . $this->attendanceSession);
        $page->assertSee('A correction reason is required.');
    }
    public function testCancelledStaleAndForgedFailuresRenderSafeMessages(): void
    {
        $response = $this->browserPost('attendance/sessions/' . $this->attendanceSession . '/players/' . ($this->player + 999), ['status' => 'PRESENT']);
        $response->assertStatus(302);
        $page = $this->withSession($_SESSION)->get('attendance/sessions/' . $this->attendanceSession);
        $page->assertSee('This player is not on the persisted session roster.');
        $this->db->table('sessions')->where('id', $this->attendanceSession)->update(['status' => 'cancelled']);
        $response = $this->browserPost('attendance/sessions/' . $this->attendanceSession . '/players/' . $this->player, ['status' => 'ABSENT']);
        $response->assertStatus(302);
        $page = $this->withSession($_SESSION)->get('attendance/sessions/' . $this->attendanceSession);
        $page->assertSee('Attendance cannot be changed for this session.');
        $this->db->table('sessions')->where('id', $this->attendanceSession)->update(['status' => 'scheduled']);
        $id = $this->attendance()->record($this->attendanceSession, $this->player, 'ABSENT');
        $this->attendance()->correct($id, 'PRESENT', 'First correction', 1);
        $response = $this->browserPost('attendance/' . $id . '/correct', ['status' => 'LATE', 'reason' => 'Stale attempt', 'expected_version' => 1]);
        $response->assertStatus(302);
        $page = $this->withSession($_SESSION)->get('attendance/sessions/' . $this->attendanceSession);
        $page->assertSee('This attendance record changed in another session. Reload the latest attendance data and try again.');
    }
    public function testAttendanceMarkupUsesAccessibleSemanticsAndEscapesUserData(): void
    {
        $response = $this->withSession(session()->get())->get('attendance');
        $response->assertStatus(200);
        $body = $response->getBody();
        self::assertStringContainsString('<table>', $body);
        self::assertStringContainsString('scope="col"', $body);
        self::assertStringContainsString('for="session-date"', $body);
        self::assertStringContainsString('aria-labelledby="filter-heading"', $body);
        $roster = $this->withSession(session()->get())->get('attendance/sessions/' . $this->attendanceSession);
        $body = $roster->getBody();
        self::assertStringContainsString('<caption class="visually-hidden">Attendance status and remarks by Player</caption>', $body);
        self::assertStringContainsString('scope="row"', $body);
        self::assertStringContainsString('for="status-', $body);
        self::assertStringContainsString('data-session-feedback-form', $body);
        $this->attendance()->record($this->attendanceSession, $this->player, 'ABSENT');
        $body = $this->withSession(session()->get())->get('attendance/sessions/' . $this->attendanceSession)->getBody();
        self::assertStringContainsString('for="reason-', $body);
    }
    public function testBrowserUnauthorizedAndMissingPagesAreDenied(): void
    {
        $this->login([], [$this->north]);
        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])->get('attendance')->assertStatus(401);
        $this->login(['attendance.view'], [$this->north]);
        $response = $this->withSession(session()->get())->get('attendance/sessions/999999');
        $response->assertStatus(404);
        self::assertStringNotContainsString('SQL', $response->getBody());
    }
    public function testMutationRoutesArePostOnly(): void
    {
        try {
            $this->withSession(session()->get())->get('attendance/sessions/' . $this->attendanceSession . '/players/' . $this->player);
            self::fail('GET mutation route was accepted.');
        } catch (\CodeIgniter\Exceptions\PageNotFoundException $exception) {
            self::assertStringContainsString('GET: attendance/sessions/', $exception->getMessage());
        }
        try {
            $this->withSession(session()->get())->get('attendance/999999/correct');
            self::fail('GET correction route was accepted.');
        } catch (\CodeIgniter\Exceptions\PageNotFoundException $exception) {
            self::assertStringContainsString('GET: attendance/', $exception->getMessage());
        }
        self::assertSame(0, $this->attendanceCount());
    }
    public function testDashboardAndImplementedModulePagesAreReachable(): void
    {
        $this->login(['lead.view', 'admission.view', 'player.view', 'session.update', 'attendance.view'], [$this->north]);
        foreach ([['/', 'Dashboard'], ['crm/leads', 'CRM leads'], ['admissions', 'Admissions'], ['players', 'Players'], ['sessions',
    'Scheduling sessions'], ['attendance', 'Attendance sessions']] as $case) {
            $response = $this->withSession(session()->get())->get($case[0]);
            $response->assertStatus(200);
            $response->assertSee($case[1]);
        }
    }
    public function testAttendanceDatabaseConstraintsProtectLineageAndUniqueness(): void
    {
        $columns = $this->db->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND
    TABLE_NAME='attendance'")->getResultArray();
        $names = array_map(static fn(array $row): string => (string) $row['COLUMN_NAME'], $columns);
        foreach (['session_id', 'player_id', 'branch_id', 'recorded_by', 'status', 'recorded_at', 'version'] as $name) {
            self::assertContains($name, $names);
        }
        $indexes = $this->db->query("SHOW INDEX FROM attendance WHERE Non_unique=0")->getResultArray();
        $uniquePairs = [];
        foreach ($indexes as $index) {
            $uniquePairs[(string) $index['Key_name']] = true;
        }
        self::assertTrue(count($uniquePairs) >= 2);
        $foreignKeys = $this->db->query("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND
    TABLE_NAME='attendance' AND REFERENCED_TABLE_NAME IS NOT NULL")->getResultArray();
        self::assertGreaterThanOrEqual(4, count($foreignKeys));
        $historyForeignKeys = $this->db->query("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND
    TABLE_NAME='attendance_history' AND REFERENCED_TABLE_NAME IS NOT NULL")->getResultArray();
        self::assertGreaterThanOrEqual(2, count($historyForeignKeys));
        $this->attendance()->record($this->attendanceSession, $this->player, 'PRESENT');
        $this->expectException(\Throwable::class);
        $this->db->table('attendance')->insert(['session_id' => $this->attendanceSession, 'player_id' => $this->player, 'branch_id' => $this->north,
    'status' => 'ABSENT', 'recorded_by' => $this->actor, 'recorded_at' => gmdate('Y-m-d H:i:s'), 'version' => 1, 'created_at' => gmdate('Y-m-d H:i:s'),
    'updated_at' => gmdate('Y-m-d H:i:s')]);
    }
    private function browserPost(string $path, array $data): \CodeIgniter\Test\TestResponse
    {
        return $this->withSession(session()->get())->withHeaders(['X-CSRF-TOKEN' => csrf_hash()])->post($path, $data);
    }
    private function attendance(): AttendanceService
    {
        return service('attendance', false);
    }

    private function captureSnapshot(int $session): void
    {
        $this->attendance()->captureRosterSnapshot($session);
    }

    private function attendanceCount(): int
    {
        return $this->db->table('attendance')
            ->where('session_id', $this->attendanceSession)
            ->countAllResults();
    }

    private function attendanceHistoryCount(): int
    {
        return $this->db->table('attendance_history ah')
            ->join('attendance a', 'a.id = ah.attendance_id')
            ->where('a.session_id', $this->attendanceSession)
            ->countAllResults();
    }

    private function auditCount(string $action): int
    {
        return $this->db->table('audit_logs')
            ->where([
                'action' => $action,
                'branch_id' => $this->north,
            ])
            ->countAllResults();
    }

    private function resetDefaultConnection(): void
    {
        $db = Database::connect('default');
        $db->transRollback();
        $db->resetTransStatus();
        $db->close();
    }

    private function user(string $email): int
    {
        $m = new UserModel();
        $m->insert(['email' => $email, 'password_hash' => password_hash('Test password!', PASSWORD_DEFAULT), 'display_name' => 'Attendance User',
    'is_enabled' => 1, 'failed_login_count' => 0, 'session_version' => 1, 'created_at' => gmdate('Y-m-d H:i:s'), 'updated_at' => gmdate('Y-m-d H:i:s')]);
        return (int) $m->getInsertID();
    }
    private function makeSession(int $branch, string $status): int
    {
        $n = gmdate('Y-m-d H:i:s');
        $start = gmdate('Y-m-d H:i:s', time() - 1800);
        $end = gmdate('Y-m-d H:i:s', time() + 1800);
        $this->db->table('sessions')->insert(['branch_id' => $branch, 'batch_id' => $this->batch, 'coach_user_id' => $this->actor, 'starts_at' => $start,
    'ends_at' => $end, 'status' => $status, 'created_by' => $this->actor, 'created_at' => $n, 'updated_at' => $n]);
        return (int) $this->db->insertID();
    }
    private function login(array $permissions, array $branches): void
    {
        \Config\Services::resetSingle('authorization');
        session()->set(['isLoggedIn' => true, 'accountEnabled' => true, 'user' => ['id' => $this->actor], 'session_version' => 1,
    'authorizedBranches' => array_map('strval', $branches), 'permissions' => $permissions]);
    }
}

