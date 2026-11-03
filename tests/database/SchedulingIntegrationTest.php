<?php

declare (strict_types=1);
namespace Tests\Database;

use App\Models\UserModel;
use App\Services\SchedulingService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use RuntimeException;
final class SchedulingIntegrationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    protected $DBGroup = 'tests';
    protected $migrate = false;
    private int $north;
    private int $south;
    private int $actor;
    private int $coach;
    private int $batch;
    private int $player;
    protected function setUp(): void
    {
        parent::setUp();
        foreach (['coach_unavailability', 'session_players', 'sessions', 'player_batch_assignments', 'player_coach_assignments', 'batches',
    'player_branch_history', 'players', 'persons', 'admissions', 'admission_status_history', 'leads', 'audit_logs', 'user_branches', 'users',
    'branches'] as $t) {
            $this->db->table($t)->emptyTable();
        }
        $n = date('Y-m-d H:i:s');
        $this->db->table('branches')->insert(['name' => 'North', 'code' => 'sched-north', 'is_active' => 1, 'created_at' => $n, 'updated_at' => $n]);
        $this->north = (int) $this->db->insertID();
        $this->db->table('branches')->insert(['name' => 'South', 'code' => 'sched-south', 'is_active' => 1, 'created_at' => $n, 'updated_at' => $n]);
        $this->south = (int) $this->db->insertID();
        $this->actor = $this->user('scheduler@example.test');
        $this->coach = $this->user('coach@example.test');
        $this->db->table('user_branches')->insert(['user_id' => $this->actor, 'branch_id' => $this->north, 'created_at' => $n]);
        $this->db->table('user_branches')->insert(['user_id' => $this->coach, 'branch_id' => $this->north, 'created_at' => $n]);
        $this->batch = $this->batch($this->north);
        $this->player = $this->player();
        $this->session(['session.create', 'session.update', 'session.cancel', 'player.view'], [$this->north]);
    }
    protected function tearDown(): void
    {
        foreach (['coach_unavailability', 'session_players', 'sessions', 'player_batch_assignments', 'player_coach_assignments', 'batches',
    'player_branch_history', 'players', 'persons', 'admissions', 'admission_status_history', 'leads', 'audit_logs', 'user_branches', 'users',
    'branches'] as $t) {
            $this->db->table($t)->emptyTable();
        }
        session()->destroy();
        parent::tearDown();
    }
    public function testAuthorizedCreationPersistsBranchCoachBatchRosterAndAudit(): void
    {
        $id = $this->scheduling()->create($this->payload());
        $r = $this->db->table('sessions')->where('id', $id)->get()->getRowArray();
        self::assertSame($this->north, (int) $r['branch_id']);
        self::assertSame(1, $this->db->table('session_players')->where(['session_id' => $id, 'player_id' => $this->player])->countAllResults());
        self::assertSame(1, $this->db->table('audit_logs')->where(['action' => 'session_created', 'entity_id' => (string) $id])->countAllResults());
    }
    public function testUnauthorizedAndCrossBranchCreationDenied(): void
    {
        $this->session([], [$this->north]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Forbidden');
        $this->scheduling()->create($this->payload());
    }
    public function testPlayerEligibilityAndCoachBranchAreResolvedServerSide(): void
    {
        $bad = $this->batch($this->south);
        $p = $this->payload();
        $p['batch_id'] = $bad;
        try {
            $this->scheduling()->create($p);
            self::fail('Cross-branch batch accepted.');
        } catch (RuntimeException $e) {
            self::assertSame('Forbidden', $e->getMessage());
        }
        $p = $this->payload();
        $p['player_ids'] = [$this->player + 999];
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Player is not eligible.');
        $this->scheduling()->create($p);
    }
    public function testCoachConflictAndUnavailabilityAreRejected(): void
    {
        $this->scheduling()->create($this->payload());
        try {
            $this->scheduling()->create($this->payload());
            self::fail('Coach conflict accepted.');
        } catch (RuntimeException $e) {
            self::assertSame('Scheduling conflict.', $e->getMessage());
        }
        $this->db->table('coach_unavailability')->insert(['coach_user_id' => $this->coach, 'branch_id' => $this->north,
    'unavailable_from' => '2026-09-01 02:30:00', 'unavailable_to' => '2026-09-01 06:30:00', 'reason' => 'Leave', 'created_at' => gmdate('Y-m-d H:i:s')]);
        $p = $this->payload();
        $p['starts_at'] = '2026-09-01T09:00:00+05:30';
        $p['ends_at'] = '2026-09-01T10:00:00+05:30';
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Coach unavailable.');
        $this->scheduling()->create($p);
    }
    public function testCancellationPreservesRecordAndRescheduleLinksReplacement(): void
    {
        $id = $this->scheduling()->create($this->payload());
        $new = $this->scheduling()->reschedule($id, '2026-09-02T09:00:00+05:30', '2026-09-02T10:00:00+05:30', 'New slot');
        $old = $this->db->table('sessions')->where('id', $id)->get()->getRowArray();
        self::assertSame('cancelled', $old['status']);
        $replacement = $this->db->table('sessions')->where('id', $new)->get()->getRowArray();
        self::assertSame($id, (int) $replacement['rescheduled_from_id']);
        self::assertSame(2, $this->db->table('sessions')->countAllResults());
        self::assertSame(1, $this->db->table('session_players')->where('session_id', $new)->countAllResults());
        self::assertSame(1, $this->db->table('audit_logs')->where(['action' => 'session_rescheduled', 'entity_id' => (string) $new])->countAllResults());
    }
    public function testCancelledSessionCannotBeReusedOrRescheduledAgain(): void
    {
        $id = $this->scheduling()->create($this->payload());
        $this->scheduling()->cancel($id, 'Closed');
        try {
            $this->scheduling()->cancel($id, 'Again');
            self::fail('Cancelled session reused.');
        } catch (RuntimeException $e) {
            self::assertSame('Session is not active.', $e->getMessage());
        }
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Session is not active.');
        $this->scheduling()->reschedule($id, '2026-09-03T09:00:00+05:30', '2026-09-03T10:00:00+05:30', 'Again');
    }
    public function testRescheduleFailureRollsBackOriginalAndRoster(): void
    {
        $id = $this->scheduling()->create($this->payload());
        $n = gmdate('Y-m-d H:i:s');
        $this->db->table('coach_unavailability')->insert(['coach_user_id' => $this->coach, 'branch_id' => $this->north,
    'unavailable_from' => '2026-09-02 02:30:00', 'unavailable_to' => '2026-09-02 06:30:00', 'reason' => 'Leave', 'created_at' => $n]);
        try {
            $this->scheduling()->reschedule($id, '2026-09-02T09:00:00+05:30', '2026-09-02T10:00:00+05:30', 'Blocked');
            self::fail('Unavailable reschedule accepted.');
        } catch (RuntimeException $e) {
            self::assertSame('Coach unavailable.', $e->getMessage());
        }
        $old = $this->db->table('sessions')->where('id', $id)->get()->getRowArray();
        self::assertSame('scheduled', $old['status']);
        self::assertSame(1, $this->db->table('sessions')->countAllResults());
        self::assertSame(1, $this->db->table('session_players')->countAllResults());
    }
    public function testInvalidWindowRollsBackWithoutSessionOrRoster(): void
    {
        try {
            $this->scheduling()->create(array_merge($this->payload(), ['ends_at' => '2026-09-01T08:00:00+05:30']));
            self::fail('Invalid window accepted.');
        } catch (RuntimeException $e) {
            self::assertSame('Invalid session window.', $e->getMessage());
        }
        self::assertSame(0, $this->db->table('sessions')->countAllResults());
        self::assertSame(0, $this->db->table('session_players')->countAllResults());
    }
    public function testUtcPersistenceKolkataPresentationAndUtcAudit(): void
    {
        $id = $this->scheduling()->create($this->payload());
        $row = $this->db->table('sessions')->where('id', $id)->get()->getRowArray();
        self::assertSame('2026-09-01 03:30:00', $row['starts_at']);
        self::assertSame('2026-09-01 04:30:00', $row['ends_at']);
        $view = $this->scheduling()->find($id);
        self::assertSame('2026-09-01 09:00:00+05:30', $view['starts_at_local']);
        self::assertSame($row['starts_at'], $this->db->table('sessions')->where('id', $id)->get()->getRowArray()['starts_at']);
        $audit = $this->db->table('audit_logs')->where(['action' => 'session_created', 'entity_id' => (string) $id])->get()->getRowArray();
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $audit['created_at']);
        $auditTime = new \DateTimeImmutable($audit['created_at'], new \DateTimeZone('UTC'));
        self::assertLessThanOrEqual(5, abs(time() - $auditTime->getTimestamp()));
    }
    public function testMissingTimezoneIsRejectedWithoutPersistence(): void
    {
        try {
            $this->scheduling()->create(array_merge($this->payload(), ['starts_at' => '2026-09-01 09:00:00']));
            self::fail('Timezone-less datetime accepted.');
        } catch (RuntimeException $e) {
            self::assertSame('Datetime timezone is required.', $e->getMessage());
        }
        self::assertSame(0, $this->db->table('sessions')->countAllResults());
    }
    private function scheduling(): SchedulingService
    {
        return service('scheduling', false);
    }
    private function payload(): array
    {
        return ['batch_id' => $this->batch, 'session_type' => 'one_to_one', 'coach_user_id' => $this->coach, 'starts_at' => '2026-09-01T09:00:00+05:30',
    'ends_at' => '2026-09-01T10:00:00+05:30', 'player_ids' => [$this->player]];
    }
    private function user(string $email): int
    {
        $m = new UserModel();
        $m->insert(['email' => $email, 'password_hash' => password_hash('Test password!', PASSWORD_DEFAULT), 'display_name' => 'Scheduler',
    'is_enabled' => 1, 'failed_login_count' => 0, 'session_version' => 1, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
        return (int) $m->getInsertID();
    }
    private function batch(int $branch): int
    {
        $n = date('Y-m-d H:i:s');
        $this->db->table('batches')->insert(['branch_id' => $branch, 'name' => 'Morning ' . uniqid(), 'created_at' => $n, 'updated_at' => $n]);
        return (int) $this->db->insertID();
    }
    private function player(): int
    {
        $n = date('Y-m-d H:i:s');
        $this->db->table('leads')->insert(['branch_id' => $this->north, 'owner_user_id' => $this->actor, 'source' => 'web', 'full_name' => 'Candidate',
    'status' => 'lead', 'created_at' => $n, 'updated_at' => $n]);
        $lead = (int) $this->db->insertID();
        $this->db->table('admissions')->insert(['lead_id' => $lead, 'branch_id' => $this->north, 'status' => 'CONVERTED', 'checklist_complete' => 1,
    'created_at' => $n, 'updated_at' => $n]);
        $ad = (int) $this->db->insertID();
        $this->db->table('persons')->insert(['full_name' => 'Candidate', 'created_at' => $n, 'updated_at' => $n]);
        $person = (int) $this->db->insertID();
        $this->db->table('players')->insert(['person_id' => $person, 'lead_id' => $lead, 'admission_id' => $ad, 'branch_id' => $this->north,
    'status' => 'active', 'created_at' => $n, 'updated_at' => $n]);
        $p = (int) $this->db->insertID();
        $this->db->table('player_branch_history')->insert(['player_id' => $p, 'branch_id' => $this->north, 'effective_from' => $n, 'created_at' => $n]);
        $this->db->table('player_batch_assignments')->insert(['player_id' => $p, 'branch_id' => $this->north, 'batch_id' => $this->batch,
    'assigned_by' => $this->actor, 'effective_from' => '2026-01-01 00:00:00', 'created_at' => $n, 'updated_at' => $n]);
        return $p;
    }
    private function session(array $permissions, array $branches): void
    {
        \Config\Services::resetSingle('authorization');
        session()->set(['isLoggedIn' => true, 'accountEnabled' => true, 'user' => ['id' => $this->actor], 'authorizedBranches' => array_map('strval',
    $branches), 'permissions' => $permissions]);
    }
}
