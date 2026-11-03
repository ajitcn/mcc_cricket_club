<?php

declare(strict_types=1);

namespace Tests\Database;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Database;
use RuntimeException;

final class MatchPlayerPerformanceSafeTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $DBGroup = 'tests';
    protected $migrate = false;

    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function tearDown(): void
    {
        session()->destroy();
        \Config\Services::resetSingle('authorization');
        parent::tearDown();
    }

    public function testPerformancePersistsSelectedPlayersAndAuditsIdempotentCorrections(): void
    {
        $database = Database::connect('default');
        self::assertSame('mcc_cricket_club_test', strtolower((string) $database->getDatabase()));
        if (!$database->tableExists('match_player_performances') || !in_array('fielding_status', $database->getFieldNames('match_player_performances'), true)) {
            self::markTestSkipped('The Match Player Fielding migration is pending in the test database.');
        }

        $branch = (int) $database->table('branches')->where('code', 'TEST_MCC_VNS')->get()->getRow('id');
        $actor = (int) $database->table('users')->where('email', 'test.mcc.superadmin@example.invalid')->get()->getRow('id');
        $player = (int) $database->table('players')->where('branch_id', $branch)->orderBy('id')->get()->getRow('id');
        self::assertGreaterThan(0, min($branch, $actor, $player));

        session()->set([
            'isLoggedIn' => true,
            'accountEnabled' => true,
            'user' => ['id' => $actor],
            'permissions' => ['match.view', 'match.update', 'performance.update'],
            'authorizedBranches' => [(string) $branch],
        ]);
        \Config\Services::resetSingle('authorization');

        $database->table('matches')->insert([
            'branch_id' => $branch,
            'opponent' => 'TEST_MCC performance ' . bin2hex(random_bytes(4)),
            'match_type' => 'friendly',
            'status' => 'scheduled',
            'starts_at' => '2032-11-10 06:00:00',
            'ends_at' => '2032-11-10 08:00:00',
            'created_by' => $actor,
            'created_at' => gmdate('Y-m-d H:i:s'),
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ]);
        $match = (int) $database->insertID();
        $database->table('match_players')->insert([
            'match_id' => $match,
            'player_id' => $player,
            'selection_type' => 'playing_xi',
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);

        $service = service('matchPlayerPerformance', false);
        $payload = [
            'players' => [
                $player => [
                    'batting_status' => 'batted',
                    'runs' => 42,
                    'balls_faced' => 30,
                    'fours' => 4,
                    'sixes' => 1,
                    'dismissal_type' => 'not_out',
                    'bowling_status' => 'did_not_bowl',
                    'fielding_status' => 'fielded',
                    'catches' => 2,
                    'run_out_assists' => 1,
                    'dropped_catches' => 0,
                ],
            ],
        ];
        $service->save($match, $payload);
        $row = $database->table('match_player_performances')->where(['match_id' => $match, 'player_id' => $player])->get()->getRowArray();
        self::assertIsArray($row);
        self::assertSame(42, (int) $row['runs']);
        self::assertSame(2, (int) $row['catches']);
        self::assertSame(1, (int) $row['version']);
        self::assertSame(3, $service->metrics($row)['fielding_contributions']);
        self::assertSame(1, $service->forMatch($match)['players'][0]['player_id'] > 0 ? 1 : 0);

        $createdAudit = $database->table('audit_logs')->where([
            'entity_type' => 'match_player_performance',
            'entity_id' => (string) $row['id'],
            'action' => 'match_player_performance_created',
        ])->countAllResults();
        self::assertSame(1, $createdAudit);

        $service->save($match, $payload);
        self::assertSame($createdAudit, $database->table('audit_logs')->where([
            'entity_type' => 'match_player_performance',
            'entity_id' => (string) $row['id'],
        ])->countAllResults());

        $payload['players'][$player]['runs'] = 50;
        $payload['players'][$player]['version'] = (int) $row['version'];
        $payload['players'][$player]['correction_reason'] = 'Corrected scorebook entry';
        $service->save($match, $payload);
        $corrected = $database->table('match_player_performances')->where('id', $row['id'])->get()->getRowArray();
        self::assertIsArray($corrected);
        self::assertSame(50, (int) $corrected['runs']);
        self::assertSame(2, (int) $corrected['version']);
        self::assertSame(1, $database->table('audit_logs')->where([
            'entity_type' => 'match_player_performance',
            'entity_id' => (string) $row['id'],
            'action' => 'match_player_performance_corrected',
        ])->countAllResults());

        $this->expectExceptionMessage('A correction reason is required.');
        $payload['players'][$player]['runs'] = 51;
        $payload['players'][$player]['version'] = (int) $corrected['version'];
        unset($payload['players'][$player]['correction_reason']);
        $service->save($match, $payload);
    }

    public function testStaleCorrectionIsRejectedWithoutOverwritingCurrentRecord(): void
    {
        $database = Database::connect('default');
        $branch = (int) $database->table('branches')->where('code', 'TEST_MCC_VNS')->get()->getRow('id');
        $actor = (int) $database->table('users')->where('email', 'test.mcc.superadmin@example.invalid')->get()->getRow('id');
        $player = (int) $database->table('players')->where('branch_id', $branch)->orderBy('id')->get()->getRow('id');
        self::assertGreaterThan(0, min($branch, $actor, $player));

        $this->authenticate($branch, $actor);
        $match = $this->createMatch($database, $branch, $actor, 'stale');
        $database->table('match_players')->insert([
            'match_id' => $match,
            'player_id' => $player,
            'selection_type' => 'playing_xi',
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);

        $service = service('matchPlayerPerformance', false);
        $payload = $this->performancePayload('batted');
        $service->savePlayer($match, $player, $payload);

        $first = $database->table('match_player_performances')
            ->where(['match_id' => $match, 'player_id' => $player])
            ->get()
            ->getRowArray();
        self::assertIsArray($first);
        self::assertSame(1, (int) $first['version']);

        $current = $payload;
        $current['runs'] = 20;
        $current['version'] = 1;
        $current['correction_reason'] = 'Updated scorebook entry';
        $service->savePlayer($match, $player, $current);

        $stale = $current;
        $stale['runs'] = 30;
        $stale['version'] = 1;
        $stale['correction_reason'] = 'Stale scorebook correction';

        $this->expectExceptionMessage('Stale Match Player Performance submission.');
        try {
            $service->savePlayer($match, $player, $stale);
        } finally {
            $latest = $database->table('match_player_performances')
                ->where('id', (int) $first['id'])
                ->get()
                ->getRowArray();
            self::assertIsArray($latest);
            self::assertSame(20, (int) $latest['runs']);
            self::assertSame(2, (int) $latest['version']);
        }
    }

    public function testCrossBranchBattingBowlingAndFieldingWritesAreRejected(): void
    {
        $database = Database::connect('default');
        $branch = (int) $database->table('branches')->where('code', 'TEST_MCC_VNS')->get()->getRow('id');
        $otherBranch = (int) $database->table('branches')->where('code', 'TEST_MCC_DEL')->get()->getRow('id');
        $actor = (int) $database->table('users')->where('email', 'test.mcc.superadmin@example.invalid')->get()->getRow('id');
        $foreignPlayer = (int) $database->table('players')->where('branch_id', $otherBranch)->orderBy('id')->get()->getRow('id');
        self::assertGreaterThan(0, min($branch, $otherBranch, $actor, $foreignPlayer));

        $this->authenticate($branch, $actor);
        $match = $this->createMatch($database, $branch, $actor, 'cross-branch');
        $database->table('match_players')->insert([
            'match_id' => $match,
            'player_id' => $foreignPlayer,
            'selection_type' => 'playing_xi',
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);

        $service = service('matchPlayerPerformance', false);
        foreach (['batted', 'bowled', 'fielded'] as $operation) {
            try {
                $service->savePlayer($match, $foreignPlayer, $this->performancePayload($operation));
                self::fail('A cross-branch ' . $operation . ' write was accepted.');
            } catch (RuntimeException $exception) {
                self::assertSame('Invalid Match performance Player.', $exception->getMessage());
            }
        }

        self::assertSame(0, $database->table('match_player_performances')->where('match_id', $match)->countAllResults());
    }

    public function testDatabaseFailureRollsBackEarlierPerformanceInserts(): void
    {
        $database = Database::connect('default');
        $branch = (int) $database->table('branches')->where('code', 'TEST_MCC_VNS')->get()->getRow('id');
        $actor = (int) $database->table('users')->where('email', 'test.mcc.superadmin@example.invalid')->get()->getRow('id');
        $players = $database->table('players')->where('branch_id', $branch)->orderBy('id')->limit(2)->get()->getResultArray();
        self::assertGreaterThanOrEqual(2, count($players));

        $this->authenticate($branch, $actor);
        $match = $this->createMatch($database, $branch, $actor, 'rollback');
        foreach ($players as $player) {
            $database->table('match_players')->insert([
                'match_id' => $match,
                'player_id' => (int) $player['id'],
                'selection_type' => 'playing_xi',
                'created_at' => gmdate('Y-m-d H:i:s'),
            ]);
        }

        $payload = [
            'players' => [
                (int) $players[0]['id'] => $this->performancePayload('batted'),
                (int) $players[1]['id'] => array_merge(
                    $this->performancePayload('fielded'),
                    ['runs' => '999999999999']
                ),
            ],
        ];

        $service = service('matchPlayerPerformance', false);
        $this->expectException(RuntimeException::class);
        try {
            $service->save($match, $payload);
        } finally {
            self::assertSame(0, $database->table('match_player_performances')->where('match_id', $match)->countAllResults());
        }
    }

    private function authenticate(int $branch, int $actor): void
    {
        session()->set([
            'isLoggedIn' => true,
            'accountEnabled' => true,
            'user' => ['id' => $actor],
            'permissions' => ['match.view', 'match.update', 'performance.update'],
            'authorizedBranches' => [(string) $branch],
        ]);
        \Config\Services::resetSingle('authorization');
    }

    private function createMatch(object $database, int $branch, int $actor, string $label): int
    {
        $now = gmdate('Y-m-d H:i:s');
        $database->table('matches')->insert([
            'branch_id' => $branch,
            'opponent' => 'TEST_MCC performance ' . $label . ' ' . bin2hex(random_bytes(4)),
            'match_type' => 'friendly',
            'status' => 'scheduled',
            'starts_at' => '2032-11-10 06:00:00',
            'ends_at' => '2032-11-10 08:00:00',
            'created_by' => $actor,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) $database->insertID();
    }

    private function performancePayload(string $operation): array
    {
        return [
            'batting_status' => $operation === 'batted' ? 'batted' : 'did_not_bat',
            'bowling_status' => $operation === 'bowled' ? 'bowled' : 'did_not_bowl',
            'fielding_status' => $operation === 'fielded' ? 'fielded' : 'did_not_field',
            'runs' => $operation === 'batted' ? 10 : null,
            'balls_faced' => $operation === 'batted' ? 8 : null,
            'legal_balls' => $operation === 'bowled' ? 6 : null,
            'wickets' => $operation === 'bowled' ? 1 : null,
            'catches' => $operation === 'fielded' ? 1 : null,
        ];
    }

}
