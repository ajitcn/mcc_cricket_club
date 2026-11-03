<?php

declare(strict_types=1);

namespace Tests\Database;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use RuntimeException;

final class MatchManagementIntegrationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $DBGroup = 'tests';
    protected $migrate = false;

    private int $branch;
    private int $otherBranch;
    private int $player;
    private int $actor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->branch = $this->id('branches', ['code' => 'TEST_MCC_VNS']);
        $this->otherBranch = $this->id('branches', ['code' => 'TEST_MCC_DEL']);
        $this->player = (int) $this->db->table('players p')->select('p.id')->join('persons pe', 'pe.id = p.person_id')->where('pe.full_name', 'TEST_MCC_CONVERTED_PLAYER')->get()->getRow('id');
        $this->actor = $this->id('users', ['email' => 'test.mcc.superadmin@example.invalid']);
        $this->db->table('match_players')->emptyTable();
        $this->db->table('matches')->emptyTable();
        session()->set(['isLoggedIn' => true, 'accountEnabled' => true, 'user' => ['id' => $this->actor], 'authorizedBranches' => [(string) $this->branch], 'permissions' => ['match.view', 'match.create', 'match.update']]);
        \Config\Services::resetSingle('authorization');
    }

    protected function tearDown(): void
    {
        $this->db->table('match_players')->emptyTable();
        $this->db->table('matches')->emptyTable();
        session()->destroy();
        \Config\Services::resetSingle('authorization');
        parent::tearDown();
    }

    public function testMatchCrudAndPlayingXiUsePersistedPlayers(): void
    {
        $service = service('match', false);
        $id = $service->save([
            'branch_id' => $this->branch,
            'opponent' => 'TEST_MCC Opponent',
            'match_type' => 'friendly',
            'starts_at' => '2026-10-01T09:00:00+05:30',
            'ends_at' => '2026-10-01T13:00:00+05:30',
            'player_ids' => [$this->player],
            'playing_xi_ids' => [$this->player],
        ]);
        $service->save(['branch_id' => $this->branch, 'opponent' => 'TEST_MCC Opponent', 'match_type' => 'friendly', 'status' => 'completed', 'starts_at' => '2026-10-01T09:00:00+05:30', 'ends_at' => '2026-10-01T13:00:00+05:30', 'result' => 'won', 'score_summary' => 'TEST_MCC 2-1'], $id);
        $detail = $service->find($id);
        self::assertSame('completed', $detail['status']);
        self::assertSame('won', $detail['result']);
        self::assertCount(1, $detail['players']);
        self::assertSame('playing_xi', $detail['players'][0]['selection_type']);
    }

    public function testCrossBranchAndInvalidSelectionAreDenied(): void
    {
        $service = service('match', false);
        try {
            $service->save(['branch_id' => $this->branch, 'opponent' => 'Opponent', 'match_type' => 'friendly', 'starts_at' => '2026-10-02T09:00:00+05:30', 'ends_at' => '2026-10-02T10:00:00+05:30', 'player_ids' => [$this->player], 'playing_xi_ids' => [$this->player + 999]]);
            self::fail('Invalid player accepted.');
        } catch (RuntimeException $exception) {
            self::assertSame('Playing XI must be selected from squad.', $exception->getMessage());
        }
        session()->set('authorizedBranches', [(string) $this->otherBranch]);
        \Config\Services::resetSingle('authorization');
        $this->expectExceptionMessage('Forbidden');
        $service->save(['branch_id' => $this->branch, 'opponent' => 'Opponent', 'match_type' => 'friendly', 'starts_at' => '2026-10-03T09:00:00+05:30', 'ends_at' => '2026-10-03T10:00:00+05:30']);
    }

    public function testInvalidMatchInputRemainsRejectedByTheService(): void
    {
        $service = service('match', false);

        $this->expectExceptionMessage('Invalid opponent.');
        $service->save([
            'branch_id' => $this->branch,
            'opponent' => '',
            'match_type' => 'friendly',
            'starts_at' => '2026-10-04T09:00:00+05:30',
            'ends_at' => '2026-10-04T10:00:00+05:30',
        ]);
    }

    public function testLocalDateAndTimeInputsPersistAsUtc(): void
    {
        $service = service('match', false);
        $id = $service->save([
            'branch_id' => $this->branch,
            'opponent' => 'TEST_MCC_LOCAL_TIME_MATCH',
            'match_type' => 'friendly',
            'starts_date' => '05-10-2026',
            'starts_time' => '11:30',
            'ends_date' => '05-10-2026',
            'ends_time' => '12:30',
        ]);
        $row = $this->db->table('matches')->where('id', $id)->get()->getRowArray();

        self::assertSame('2026-10-05 06:00:00', $row['starts_at']);
        self::assertSame('2026-10-05 07:00:00', $row['ends_at']);
    }

    private function id(string $table, array $where): int
    {
        $row = $this->db->table($table)->where($where)->get()->getRowArray();
        if (!is_array($row)) {
            throw new RuntimeException('Test dataset is not seeded.');
        }
        return (int) $row['id'];
    }
}
