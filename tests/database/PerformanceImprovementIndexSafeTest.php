<?php

declare(strict_types=1);

namespace Tests\Database;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Database;
use RuntimeException;

final class PerformanceImprovementIndexSafeTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $DBGroup = 'tests';
    protected $migrate = false;

    private object $database;
    private int $actor;
    private int $branch;
    private int $otherBranch;
    private int $player;
    private int $coach;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resetDefaultConnection();
        $this->database = Database::connect('default');
        $this->actor = (int) $this->database->table('users')
            ->where('email', 'test.mcc.superadmin@example.invalid')
            ->get()
            ->getRow('id');
        $this->branch = (int) $this->database->table('branches')
            ->where('code', 'TEST_MCC_VNS')
            ->get()
            ->getRow('id');
        $this->otherBranch = (int) $this->database->table('branches')
            ->where('code', 'TEST_MCC_DEL')
            ->get()
            ->getRow('id');
        $this->coach = (int) $this->database->query(
            "SELECT u.id FROM users u "
            . "LEFT JOIN player_coach_assignments pca ON pca.coach_user_id = u.id "
            . "WHERE u.email LIKE 'test.mcc.coach.%' AND pca.id IS NULL "
            . "ORDER BY u.id ASC LIMIT 1"
        )->getRow('id');
        if ($this->coach < 1) {
            $this->coach = $this->createUnassignedSyntheticCoach();
        }
        $this->player = $this->createSyntheticPlayer();

        self::assertGreaterThan(0, min(
            $this->actor,
            $this->branch,
            $this->otherBranch,
            $this->player,
            $this->coach
        ));
        $effectiveFrom = gmdate('Y-m-d H:i:s', time() + random_int(1, 1000000));
        $this->database->table('player_coach_assignments')->insert([
            'player_id' => $this->player,
            'branch_id' => $this->branch,
            'coach_user_id' => $this->coach,
            'assigned_by' => $this->actor,
            'effective_from' => $effectiveFrom,
            'effective_to' => null,
            'created_at' => gmdate('Y-m-d H:i:s'),
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    protected function tearDown(): void
    {
        session()->destroy();
        \Config\Services::resetSingle('authorization');
        parent::tearDown();
    }

    private function resetDefaultConnection(): void
    {
        $db = Database::connect('default');
        $db->transRollback();
        $db->resetTransStatus();
        $db->close();
    }

    public function testIndexUsesEarliestAndLatestApprovedRecordsAndPreservesDecline(): void
    {
        $this->authorize(['coach.kpi.view', 'performance.view'], [$this->branch]);
        $this->insertApproved('2099-10-01', 10, 'batting', 'runs');
        $this->insertApproved('2099-10-02', 5, 'batting', 'runs');

        self::assertSame(
            -50.0,
            service('performance', false)->coachImprovementIndex($this->coach)
        );
    }

    public function testUnapprovedAndZeroBaselineRecordsAreUnavailable(): void
    {
        $this->authorize(['coach.kpi.view', 'performance.create', 'performance.update'], [$this->branch]);
        $id = service('performance', false)->save([
            'branch_id' => $this->branch,
            'player_id' => $this->player,
            'record_type' => 'fielding',
            'recorded_on' => '2099-11-01',
            'catches' => 0,
        ]);
        service('performance', false)->save([
            'branch_id' => $this->branch,
            'player_id' => $this->player,
            'record_type' => 'fielding',
            'recorded_on' => '2099-11-02',
            'catches' => 2,
        ]);

        self::assertSame(0, (int) $this->database->table('performance_records')->where('id', $id)->get()->getRow('approved'));
    }

    public function testApprovalCorrectionAuditAndBranchPermissionAreEnforced(): void
    {
        $this->authorize(['performance.create', 'performance.update', 'coach.kpi.view'], [$this->branch]);
        $correctionReason = 'TEST_MCC_PII_correction_' . bin2hex(random_bytes(4));
        $id = service('performance', false)->save([
            'branch_id' => $this->branch,
            'player_id' => $this->player,
            'record_type' => 'bowling',
            'recorded_on' => '2099-12-01',
            'wickets' => 1,
        ]);
        service('performance', false)->approve($id, 'TEST_MCC approval');

        service('performance', false)->save([
            'branch_id' => $this->branch,
            'player_id' => $this->player,
            'record_type' => 'bowling',
            'recorded_on' => '2099-12-01',
            'wickets' => 2,
            'correction_reason' => $correctionReason,
        ], $id);
        $audit = $this->database->table('audit_logs')->where([
            'action' => 'performance_record_updated',
            'entity_id' => (string) $id,
            'branch_id' => $this->branch,
        ])->orderBy('id', 'DESC')->get()->getRowArray();
        self::assertIsArray($audit);
        self::assertSame($correctionReason, $audit['reason']);
        self::assertNotEmpty($audit['before_summary']);
        self::assertNotEmpty($audit['after_summary']);

        $this->authorize(['coach.kpi.view'], [$this->otherBranch]);
        self::assertNull(service('performance', false)->coachImprovementIndex($this->coach));
    }

    private function insertApproved(string $date, int $value, string $recordType, string $metric): void
    {
        $now = gmdate('Y-m-d H:i:s');
        $data = [
            'branch_id' => $this->branch,
            'player_id' => $this->player,
            'session_id' => null,
            'record_type' => $recordType,
            'recorded_on' => $date,
            'runs' => null,
            'balls_faced' => null,
            'dismissals' => null,
            'fours' => null,
            'sixes' => null,
            'wickets' => null,
            'runs_conceded' => null,
            'legal_balls' => null,
            'catches' => null,
            'stumpings' => null,
            'run_outs' => null,
            'notes' => 'TEST_MCC PII fixture ' . bin2hex(random_bytes(4)),
            'recorded_by' => $this->actor,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
            'approved' => 1,
            'approved_by' => $this->actor,
            'approved_at' => $now,
        ];
        $data[$metric] = $value;
        $this->database->table('performance_records')->insert($data);
    }

    private function createSyntheticPlayer(): int
    {
        $token = bin2hex(random_bytes(6));
        $now = gmdate('Y-m-d H:i:s');
        $email = 'test.mcc.pii.' . $token . '@example.invalid';
        $this->database->table('leads')->insert([
            'branch_id' => $this->branch,
            'owner_user_id' => $this->actor,
            'source' => 'TEST_MCC_PII',
            'full_name' => 'TEST_MCC_PII_PLAYER_' . $token,
            'email' => $email,
            'phone' => '99999' . random_int(10000, 99999),
            'date_of_birth' => '2012-01-01',
            'status' => 'created',
            'lost_reason' => null,
            'closed_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $lead = (int) $this->database->insertID();
        $this->database->table('persons')->insert([
            'full_name' => 'TEST_MCC_PII_PLAYER_' . $token,
            'email' => $email,
            'phone' => '99999' . random_int(10000, 99999),
            'date_of_birth' => '2012-01-01',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $person = (int) $this->database->insertID();
        $this->database->table('players')->insert([
            'person_id' => $person,
            'lead_id' => $lead,
            'admission_id' => null,
            'branch_id' => $this->branch,
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
            'profile_revision' => 1,
        ]);

        return (int) $this->database->insertID();
    }

    private function createUnassignedSyntheticCoach(): int
    {
        $token = bin2hex(random_bytes(6));
        $now = gmdate('Y-m-d H:i:s');
        $this->database->table('users')->insert([
            'email' => 'test.mcc.coach.pii.' . $token . '@example.invalid',
            'password_hash' => password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
            'display_name' => 'TEST_MCC_PII_COACH_' . $token,
            'is_enabled' => 1,
            'failed_login_count' => 0,
            'session_version' => 1,
            'locked_until' => null,
            'last_login_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) $this->database->insertID();
    }

    private function authorize(array $permissions, array $branches): void
    {
        session()->set([
            'isLoggedIn' => true,
            'accountEnabled' => true,
            'user' => ['id' => $this->actor],
            'roles' => ['Super Admin'],
            'permissions' => $permissions,
            'authorizedBranches' => array_map('strval', $branches),
        ]);
        \Config\Services::resetSingle('authorization');
    }
}
