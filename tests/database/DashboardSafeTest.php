<?php

declare(strict_types=1);

namespace Tests\Database;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/** Insert-free dashboard scope coverage for the approved test database. */
final class DashboardSafeTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $DBGroup = 'tests';
    protected $migrate = false;

    private int $actor;
    private int $branch;

    protected function setUp(): void
    {
        parent::setUp();
        self::assertSame('mcc_cricket_club_test', strtolower((string) $this->db->getDatabase()));
        $this->actor = (int) $this->db->table('users')
            ->where('email', 'test.mcc.superadmin@example.invalid')
            ->get()
            ->getRow('id');
        $this->branch = (int) $this->db->table('branches')
            ->where('code', 'TEST_MCC_VNS')
            ->get()
            ->getRow('id');
    }

    protected function tearDown(): void
    {
        session()->destroy();
        \Config\Services::resetSingle('authorization');
        parent::tearDown();
    }

    public function testDashboardReturnsEmptyScopedDataWithoutAuthorizedBranches(): void
    {
        $this->authorize(['player.view', 'attendance.view'], []);

        $data = service('dashboard', false)->data();

        self::assertCount(2, $data['cards']);
        self::assertSame(0, $data['cards'][0]['value']);
        self::assertSame(0, $data['cards'][1]['value']);
        self::assertTrue($data['analytics']['playersVisible']);
        self::assertSame([], $data['analytics']['players']);
        self::assertTrue($data['analytics']['attendanceVisible']);
        self::assertSame(0, $data['analytics']['attendance']['recorded']);
    }

    public function testDashboardAttendanceSummaryKeepsApprovedStatusBuckets(): void
    {
        $this->authorize(['attendance.view'], [$this->branch]);

        $attendance = service('dashboard', false)->data()['analytics']['attendance'];

        foreach ([
            'recorded',
            'present',
            'absent',
            'late',
            'leave',
            'medical',
            'rain_cancelled',
            'coach_cancelled',
        ] as $status) {
            self::assertArrayHasKey($status, $attendance);
        }
        self::assertArrayNotHasKey('excused', $attendance);
    }

    public function testDashboardCoachAndCoachingPanelsRemainScopedAndSafeWhenEmpty(): void
    {
        $this->authorize(['coach.view', 'session.update'], [$this->branch]);

        $data = service('dashboard', false)->data([
            'branch_id' => (string) $this->branch,
            'month' => '2026-10',
            'date' => '2026-10-11',
        ]);

        self::assertTrue($data['analytics']['coachesVisible']);
        self::assertTrue($data['analytics']['coachingVisible']);
        self::assertIsArray($data['analytics']['coaches']);
        self::assertArrayHasKey('group', $data['analytics']['coaching']);
        self::assertArrayHasKey('personal', $data['analytics']['coaching']);
        self::assertIsArray($data['analytics']['coachingByBranch']);
        self::assertIsArray($data['analytics']['coachingByBatch']);
        foreach ($data['analytics']['coaches'] as $coach) {
            self::assertSame('TEST_MCC_VNS', $this->db->table('branches')->where('name', $coach['branch_name'])->get()->getRow('code'));
        }
    }

    public function testUpcomingMatchSummariesRemainBranchScoped(): void
    {
        $this->authorize(['match.view'], [$this->branch]);

        $rows = (new \App\Models\DashboardModel())->upcomingRows(
            'matches',
            'starts_at',
            [$this->branch]
        );

        foreach ($rows as $row) {
            self::assertSame($this->branch, (int) $row['branch_id']);
        }
    }

    /** @param list<string> $permissions @param list<int> $branches */
    private function authorize(array $permissions, array $branches): void
    {
        session()->set([
            'isLoggedIn' => true,
            'accountEnabled' => true,
            'user' => ['id' => $this->actor],
            'session_version' => 1,
            'permissions' => $permissions,
            'authorizedBranches' => array_map('strval', $branches),
        ]);
        \Config\Services::resetSingle('authorization');
    }
}
