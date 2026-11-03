<?php

declare(strict_types=1);

namespace Tests\Feature;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

final class CoachProfileRoutesTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $DBGroup = 'tests';
    protected $migrate = false;

    private int $actor;
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

        $this->actor = $this->userId('test.mcc.manager.vns@example.invalid');
        $this->coach = $this->userId('test.mcc.coach@example.invalid');
        $this->branch = $this->branchId('TEST_MCC_VNS');
        $this->otherBranch = $this->unassignedBranchId($this->coach);

        self::assertGreaterThan(0, min(
            $this->actor,
            $this->coach,
            $this->branch,
            $this->otherBranch
        ));
    }

    protected function tearDown(): void
    {
        session()->destroy();
        \Config\Services::resetSingle('authorization');
        parent::tearDown();
    }

    public function testAuthorizedProfileRendersBranchScopedActions(): void
    {
        $this->authorize(
            ['coach.view', 'coach.update', 'coach.assign', 'coach.kpi.view'],
            [$this->branch]
        );

        $response = $this->withSession(session()->get())->get('coaches/' . $this->coach);

        self::assertSame(200, $response->response()->getStatusCode(), $response->getBody());
        self::assertStringContainsString('Coach profile', $response->getBody());
        self::assertTrue(
            str_contains($response->getBody(), 'Assign branch')
            || str_contains($response->getBody(), 'Remove assignment')
        );
        self::assertStringContainsString('Availability', $response->getBody());
        self::assertStringContainsString('Feedback history', $response->getBody());
    }

    public function testReadOnlyProfileDoesNotExposeStateChangingActions(): void
    {
        $this->authorize(['coach.view'], [$this->branch]);

        $response = $this->withSession(session()->get())->get('coaches/' . $this->coach);

        self::assertSame(200, $response->response()->getStatusCode(), $response->getBody());
        self::assertStringNotContainsString(
            'action="' . site_url('coaches/' . $this->coach . '/branches') . '"',
            $response->getBody()
        );
        self::assertStringNotContainsString(
            'action="' . site_url('coaches/' . $this->coach . '/leave') . '"',
            $response->getBody()
        );
        self::assertStringNotContainsString(
            'action="' . site_url('coaches/' . $this->coach . '/feedback') . '"',
            $response->getBody()
        );
    }

    public function testCrossBranchProfileAccessIsDenied(): void
    {
        $this->authorize(['coach.view'], [$this->otherBranch]);

        $response = $this->withSession(session()->get())->get('coaches/' . $this->coach);

        self::assertSame(403, $response->response()->getStatusCode(), $response->getBody());
    }

    /** @param list<string> $permissions @param list<int> $branches */
    private function authorize(array $permissions, array $branches): void
    {
        session()->set([
            'isLoggedIn' => true,
            'accountEnabled' => true,
            'user' => ['id' => $this->actor],
            'roles' => ['Branch Admin'],
            'session_version' => 1,
            'permissions' => $permissions,
            'authorizedBranches' => array_map('strval', $branches),
        ]);
        \Config\Services::resetSingle('authorization');
    }

    private function userId(string $email): int
    {
        return (int) $this->db->table('users')
            ->where('email', $email)
            ->get()
            ->getRow('id');
    }

    private function branchId(string $code): int
    {
        return (int) $this->db->table('branches')
            ->where('code', $code)
            ->get()
            ->getRow('id');
    }

    private function unassignedBranchId(int $coachUserId): int
    {
        $row = $this->db->table('branches b')
            ->select('b.id')
            ->join(
                'user_branches ub',
                'ub.branch_id = b.id AND ub.user_id = ' . $this->db->escape($coachUserId),
                'left'
            )
            ->where('ub.user_id', null)
            ->orderBy('b.id', 'ASC')
            ->get()
            ->getRow('id');

        return (int) $row;
    }
}
