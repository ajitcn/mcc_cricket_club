<?php

declare(strict_types=1);

namespace Tests\Database;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use RuntimeException;

/**
 * Read-only Coach Management branch-scope regression coverage.
 */
final class CoachManagementSafeTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $DBGroup = 'tests';
    protected $migrate = false;

    private int $coach;
    private int $branch;
    private int $otherBranch;

    protected function setUp(): void
    {
        parent::setUp();

        self::assertSame(
            'mcc_cricket_club_test',
            strtolower((string) $this->db->getDatabase())
        );

        $this->coach = (int) $this->db->table('users')
            ->where('email', 'test.mcc.coach@example.invalid')
            ->get()
            ->getRow('id');
        $this->branch = (int) $this->db->table('branches')
            ->where('code', 'TEST_MCC_VNS')
            ->get()
            ->getRow('id');
        $this->otherBranch = (int) $this->db->table('branches')
            ->where('code', 'TEST_MCC_DEL')
            ->get()
            ->getRow('id');

        self::assertGreaterThan(0, min($this->coach, $this->branch, $this->otherBranch));
    }

    protected function tearDown(): void
    {
        session()->destroy();
        \Config\Services::resetSingle('authorization');
        parent::tearDown();
    }

    public function testMultiBranchCoachUsesAuthorizedScopeDeterministically(): void
    {
        $this->authorize(['coach.view'], [$this->branch, $this->otherBranch]);

        $coach = service('coach', false)->find($this->coach);

        self::assertSame('All Authorized Branches', $coach['branch_scope']);
        self::assertSame('All Authorized Branches', $coach['branch_name']);
        self::assertCount(2, $coach['branches']);
        self::assertEqualsCanonicalizing(
            [$this->branch, $this->otherBranch],
            array_map('intval', array_column($coach['branches'], 'id'))
        );
    }

    public function testCrossBranchCoachAccessIsDenied(): void
    {
        $assignedBranches = array_map(
            'intval',
            array_column(
                $this->db->table('user_branches')
                    ->select('branch_id')
                    ->where('user_id', $this->coach)
                    ->get()
                    ->getResultArray(),
                'branch_id'
            )
        );
        $unauthorizedBranch = (int) $this->db->table('branches b')
            ->select('b.id')
            ->join(
                'user_branches ub',
                'ub.branch_id = b.id AND ub.user_id = ' . $this->coach,
                'left'
            )
            ->where('ub.branch_id', null)
            ->orderBy('b.id', 'ASC')
            ->get()
            ->getRow('id');

        if ($unauthorizedBranch < 1) {
            $unauthorizedBranch = max($assignedBranches) + 999999;
        }

        $this->authorize(['coach.view'], [$unauthorizedBranch]);

        try {
            service('coach', false)->find($this->coach);
            self::fail('Cross-branch Coach access was accepted.');
        } catch (RuntimeException $exception) {
            self::assertSame('Forbidden', $exception->getMessage());
        }
    }

    public function testInvalidBranchFilterDoesNotReturnAllAuthorizedCoaches(): void
    {
        $this->authorize(['coach.view'], [$this->branch, $this->otherBranch]);

        $result = service('coach', false)->list([
            'branch_id' => (string) ($this->otherBranch + 999999),
        ]);

        self::assertSame(0, $result['total']);
        self::assertSame([], $result['coaches']);
    }

    /** @param list<string> $permissions @param list<int> $branches */
    private function authorize(array $permissions, array $branches): void
    {
        session()->set([
            'isLoggedIn' => true,
            'accountEnabled' => true,
            'user' => ['id' => $this->coach],
            'session_version' => 1,
            'permissions' => $permissions,
            'authorizedBranches' => array_map('strval', $branches),
        ]);
        \Config\Services::resetSingle('authorization');
    }
}
