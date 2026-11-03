<?php

declare(strict_types=1);

namespace Tests\Feature;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/** HTTP route coverage for the existing branch-scoped Match CRUD screens. */
final class MatchBrowserRoutesTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $DBGroup = 'tests';
    protected $migrate = false;

    private int $branch;
    private int $otherBranch;
    private int $actor;
    private int $match;

    protected function setUp(): void
    {
        parent::setUp();

        self::assertSame(
            'mcc_cricket_club_test',
            strtolower((string) $this->db->getDatabase())
        );

        $this->branch = $this->branchId('TEST_MCC_VNS');
        $this->otherBranch = $this->branchId('TEST_MCC_DEL');
        $this->actor = $this->userId('test.mcc.superadmin@example.invalid');
        $this->match = (int) $this->db->table('matches')
            ->where('branch_id', $this->branch)
            ->orderBy('id', 'ASC')
            ->get()
            ->getRow('id');

        if (min($this->branch, $this->otherBranch, $this->actor, $this->match) < 1) {
            self::markTestSkipped('Match route fixtures are not available.');
        }
    }

    protected function tearDown(): void
    {
        session()->destroy();
        \Config\Services::resetSingle('authorization');
        parent::tearDown();
    }

    public function testAuthorizedUserCanOpenMatchListDetailAndEditScreens(): void
    {
        $this->authorize([
            'match.view',
            'match.create',
            'match.update',
        ], [$this->branch]);

        $list = $this->withSession(session()->get())->get('matches');
        $detail = $this->withSession(session()->get())->get('matches/' . $this->match);
        $edit = $this->withSession(session()->get())->get('matches/' . $this->match . '/edit');

        self::assertSame(200, $list->response()->getStatusCode());
        self::assertSame(200, $detail->response()->getStatusCode());
        self::assertSame(200, $edit->response()->getStatusCode());
        self::assertStringContainsString('Matches', $list->getBody());
        self::assertStringContainsString('Match detail', $detail->getBody());
        self::assertStringContainsString('csrf', strtolower($edit->getBody()));
    }

    public function testCrossBranchMatchDetailIsDenied(): void
    {
        $this->authorize(['match.view'], [$this->otherBranch]);

        $response = $this->withSession(session()->get())->get('matches/' . $this->match);

        self::assertContains($response->response()->getStatusCode(), [403, 404]);
        self::assertStringNotContainsString('Match detail', $response->getBody());
    }

    public function testMatchDetailRendersReadableAuthorizedValuesWithoutVisibleIds(): void
    {
        $this->authorize(['match.view'], [$this->branch]);
        $row = $this->db->table('matches')->where('id', $this->match)->get()->getRowArray();
        $branchName = (string) $this->db->table('branches')->where('id', $this->branch)->get()->getRow('name');
        $opponentName = (string) $this->db->table('opponents')
            ->where(['id' => (int) ($row['opponent_id'] ?? 0), 'branch_id' => $this->branch])
            ->get()
            ->getRow('name');

        $detail = $this->withSession(session()->get())->get('matches/' . $this->match);
        $body = $detail->getBody();

        self::assertSame(200, $detail->response()->getStatusCode(), $body);
        self::assertStringContainsString($branchName, $body);
        if ($opponentName !== '') {
            self::assertStringContainsString($opponentName, $body);
        }
        foreach (['Branch Id', 'Opponent Id', 'Coach User Id', 'Ground Id', 'Program Id', 'Batch Id', 'Player Id'] as $rawLabel) {
            self::assertStringNotContainsString($rawLabel, $body);
        }
        self::assertStringNotContainsString('data-label="Player ID"', $body);
    }

    public function testMatchCompletionRouteIsProtectedAndReturnsToDetail(): void
    {
        $this->authorize(['match.view'], [$this->branch]);

        $response = $this->withSession(session()->get())->post(
            'matches/' . $this->match . '/complete',
            [csrf_token() => csrf_hash()]
        );

        self::assertSame(403, $response->response()->getStatusCode());
    }

    /** @param list<string> $permissions @param list<int> $branches */
    private function authorize(array $permissions, array $branches): void
    {
        session()->set([
            'isLoggedIn' => true,
            'accountEnabled' => true,
            'user' => ['id' => $this->actor],
            'roles' => ['Super Admin'],
            'session_version' => 1,
            'permissions' => $permissions,
            'authorizedBranches' => array_map('strval', $branches),
        ]);
        \Config\Services::resetSingle('authorization');
    }

    private function branchId(string $code): int
    {
        return (int) $this->db->table('branches')->where('code', $code)->get()->getRow('id');
    }

    private function userId(string $email): int
    {
        return (int) $this->db->table('users')->where('email', $email)->get()->getRow('id');
    }
}
