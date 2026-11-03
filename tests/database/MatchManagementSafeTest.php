<?php

declare(strict_types=1);

namespace Tests\Database;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Database;
use RuntimeException;

/** Non-destructive Match CRUD coverage using unique persistent fixtures. */
final class MatchManagementSafeTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $DBGroup = 'tests';
    protected $migrate = false;

    private object $database;
    private int $branch;
    private int $otherBranch;
    private int $actor;
    private int $opponent;
    private string $opponentName;

    protected function setUp(): void
    {
        parent::setUp();
        $this->database = Database::connect('default');
        self::assertSame('mcc_cricket_club_test', strtolower((string) $this->database->getDatabase()));
        $this->branch = (int) $this->database->table('branches')->where('code', 'TEST_MCC_VNS')->get()->getRow('id');
        $this->otherBranch = (int) $this->database->table('branches')->where('code', 'TEST_MCC_DEL')->get()->getRow('id');
        $this->actor = (int) $this->database->table('users')->where('email', 'test.mcc.superadmin@example.invalid')->get()->getRow('id');
        $this->opponent = $this->createOpponent($this->branch);
        $this->authorize(['match.view', 'match.create', 'match.update'], [$this->branch, $this->otherBranch]);
    }

    protected function tearDown(): void
    {
        session()->destroy();
        \Config\Services::resetSingle('authorization');
        parent::tearDown();
    }

    public function testMatchCrudListDetailAndCompletionAreBranchScopedAndAudited(): void
    {
        $opponent = $this->opponentName;
        $service = service('match', false);
        $id = $service->save([
            'branch_id' => $this->branch,
            'opponent_id' => $this->opponent,
            'opponent' => $opponent,
            'match_type' => 'friendly',
            'starts_date' => '15-10-2030',
            'starts_time' => '11:30',
            'ends_date' => '15-10-2030',
            'ends_time' => '12:30',
        ]);

        $list = $service->list(['q' => $opponent]);
        self::assertCount(1, $list['matches']);
        self::assertSame($id, (int) $list['matches'][0]['id']);
        self::assertSame($opponent, $service->find($id)['opponent']);

        self::assertSame($id, $service->complete($id));
        self::assertSame($id, $service->complete($id));
        self::assertSame('completed', $service->find($id)['status']);
        self::assertSame(1, $this->database->table('audit_logs')->where([
            'action' => 'match_completed',
            'entity_type' => 'match',
            'entity_id' => (string) $id,
            'branch_id' => $this->branch,
        ])->countAllResults());

        $this->authorize(['match.view'], [$this->otherBranch]);
        self::assertSame([], service('match', false)->list(['q' => $opponent])['matches']);

        $this->expectExceptionMessage('Forbidden');
        service('match', false)->find($id);
    }

    public function testLocalDateTimePersistsAsUtcAndForgedSelectionRollsBack(): void
    {
        $service = service('match', false);
        $opponent = 'TEST_MCC UTC match ' . bin2hex(random_bytes(4));
        $id = $service->save([
            'branch_id' => $this->branch,
            'opponent_id' => $this->opponent,
            'opponent' => $opponent,
            'match_type' => 'practice',
            'starts_date' => '20-10-2030',
            'starts_time' => '11:30',
            'ends_date' => '20-10-2030',
            'ends_time' => '12:30',
        ]);
        $row = $this->database->table('matches')->where('id', $id)->get()->getRowArray();
        self::assertSame('2030-10-20 06:00:00', $row['starts_at']);
        self::assertSame('2030-10-20 07:00:00', $row['ends_at']);

        $forgedOpponent = 'TEST_MCC forged selection ' . bin2hex(random_bytes(4));
        try {
            $service->save([
                'branch_id' => $this->branch,
                'opponent_id' => $this->opponent,
                'opponent' => $forgedOpponent,
                'match_type' => 'friendly',
                'starts_date' => '21-10-2030',
                'starts_time' => '11:30',
                'ends_date' => '21-10-2030',
                'ends_time' => '12:30',
                'player_ids' => [999999999],
                'playing_xi_ids' => [999999999],
            ]);
            self::fail('A forged Player selection was accepted.');
        } catch (RuntimeException $exception) {
            self::assertSame('Player is not eligible for match.', $exception->getMessage());
        }

        self::assertSame(0, $this->database->table('matches')->where('opponent', $forgedOpponent)->countAllResults());
    }

    private function createOpponent(int $branch): int
    {
        $now = gmdate('Y-m-d H:i:s');
        $this->opponentName = 'TEST_MCC_MATCH_OPPONENT_' . bin2hex(random_bytes(5));
        $this->database->table('opponents')->insert([
            'branch_id' => $branch,
            'name' => $this->opponentName,
            'is_active' => 1,
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
            'session_version' => 1,
            'permissions' => $permissions,
            'authorizedBranches' => array_map('strval', $branches),
        ]);
        \Config\Services::resetSingle('authorization');
    }
}
