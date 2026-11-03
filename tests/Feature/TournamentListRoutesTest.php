<?php

declare(strict_types=1);

namespace Tests\Feature;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

final class TournamentListRoutesTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $DBGroup = 'tests';
    protected $migrate = false;

    protected function setUp(): void
    {
        parent::setUp();

        self::assertSame('mcc_cricket_club_test', strtolower((string) $this->db->getDatabase()));

        $actor = $this->db->table('users')
            ->where('email', 'test.mcc.superadmin@example.invalid')
            ->get()
            ->getRow('id');
        $branches = $this->db->table('branches')
            ->select('id')
            ->get()
            ->getResultArray();

        if ($actor === null || $branches === []) {
            self::markTestSkipped('Approved Tournament test fixtures are unavailable.');
        }

        session()->set([
            'isLoggedIn' => true,
            'accountEnabled' => true,
            'session_version' => 1,
            'user' => ['id' => (int) $actor],
            'permissions' => ['tournament.view', 'tournament.create'],
            'authorizedBranches' => array_map(
                static fn (array $branch): string => (string) $branch['id'],
                $branches
            ),
        ]);
        \Config\Services::resetSingle('authorization');
    }

    protected function tearDown(): void
    {
        session()->destroy();
        \Config\Services::resetSingle('authorization');

        parent::tearDown();
    }

    public function testTournamentListLoadsExpectedViewData(): void
    {
        $response = $this->withSession(session()->get())->get('tournaments');

        self::assertSame(200, $response->response()->getStatusCode());
        self::assertStringContainsString('Tournaments', $response->getBody());
        self::assertStringContainsString('Competition records', $response->getBody());
    }

    public function testInvalidTournamentPageReturnsTheListWithSpecificError(): void
    {
        $response = $this->withSession(session()->get())->get('tournaments?page=invalid');

        self::assertSame(200, $response->response()->getStatusCode());
        self::assertStringContainsString('Enter a valid page number.', $response->getBody());
    }
}
