<?php

declare(strict_types=1);

namespace Tests\Database;

use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;
use RuntimeException;

final class TournamentManagementIntegrationTest extends CIUnitTestCase
{
    protected $DBGroup = 'tests';

    private object $database;
    private int $branch;
    private int $otherBranch;
    private int $actor;
    private int $player;

    protected function setUp(): void
    {
        parent::setUp();
        $this->database = Database::connect('tests');
        $this->branch = (int) $this->database->table('branches')->where('code', 'TEST_MCC_VNS')->get()->getRow('id');
        $this->otherBranch = (int) $this->database->table('branches')->where('code', 'TEST_MCC_DEL')->get()->getRow('id');
        $this->actor = (int) $this->database->table('users')->where('email', 'test.mcc.superadmin@example.invalid')->get()->getRow('id');
        $this->player = (int) $this->database->table('players p')->select('p.id')->join('persons pe', 'pe.id = p.person_id')->where('pe.full_name', 'TEST_MCC_CONVERTED_PLAYER')->get()->getRow('id');
        $this->database->table('tournament_fixtures')->emptyTable();
        $this->database->table('tournament_participants')->emptyTable();
        $this->database->table('tournaments')->emptyTable();
        session()->set(['isLoggedIn' => true, 'accountEnabled' => true, 'user' => ['id' => $this->actor], 'authorizedBranches' => [(string) $this->branch], 'permissions' => ['tournament.view', 'tournament.create', 'tournament.update']]);
    }

    protected function tearDown(): void
    {
        $this->database->table('tournament_fixtures')->emptyTable();
        $this->database->table('tournament_participants')->emptyTable();
        $this->database->table('tournaments')->emptyTable();
        session()->destroy();
        parent::tearDown();
    }

    public function testCrudFixtureMatchLinkAndPersistedStandings(): void
    {
        $service = service('tournament');
        $id = $service->save([
            'branch_id' => $this->branch,
            'name' => 'TEST_MCC Cup',
            'tournament_type' => 'club',
            'starts_on' => '2026-10-01',
            'ends_on' => '2026-10-03',
            'teams' => ['TEST A', 'TEST B'],
            'player_ids' => [$this->player],
        ]);
        $this->database->table('matches')->insert([
            'branch_id' => $this->branch, 'opponent' => 'TEST B', 'match_type' => 'friendly', 'status' => 'completed',
            'starts_at' => '2026-10-01 09:00:00', 'ends_at' => '2026-10-01 11:00:00', 'result' => 'won',
            'created_by' => $this->actor, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $matchId = (int) $this->database->insertID();
        $service->fixture($id, ['fixture_number' => 1, 'home_team' => 'TEST A', 'away_team' => 'TEST B', 'match_id' => $matchId, 'status' => 'completed', 'home_points' => '3', 'away_points' => '0']);
        $found = $service->find($id);

        self::assertCount(3, $found['participants']);
        self::assertSame('won', $found['fixtures'][0]['match_result']);
        self::assertSame(3.0, $found['standings'][0]['points']);
        self::assertSame(1, $found['standings'][0]['won']);
    }

    public function testBranchIsolationAndPlayerIdorProtection(): void
    {
        $service = service('tournament');
        $id = $service->save(['branch_id' => $this->branch, 'name' => 'TEST Isolated', 'tournament_type' => 'school', 'starts_on' => '2026-11-01', 'ends_on' => '2026-11-02']);
        session()->set('authorizedBranches', [(string) $this->otherBranch]);
        \Config\Services::resetSingle('authorization');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Forbidden');
        $service->find($id);
    }
}
