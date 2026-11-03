<?php

declare(strict_types=1);

namespace Tests\Database;

use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;
use RuntimeException;

final class Player360IntegrationTest extends CIUnitTestCase
{
    private object $database;
    private int $actor;
    private int $player;
    private int $branch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->database = Database::connect('tests');
        self::assertSame('mcc_cricket_club_test', strtolower((string) $this->database->getDatabase()));
        $this->actor = (int) $this->database->table('users')->where('email', 'test.mcc.superadmin@example.invalid')->get()->getRow('id');
        $this->player = (int) $this->database->table('players p')->select('p.id')->join('branches b', 'b.id = p.branch_id')->where('b.code', 'TEST_MCC_VNS')->get()->getRow('id');
        $this->branch = (int) $this->database->table('players')->where('id', $this->player)->get()->getRow('branch_id');
        self::assertGreaterThan(0, $this->actor);
        self::assertGreaterThan(0, $this->player);
    }

    protected function tearDown(): void
    {
        session()->destroy();
        \Config\Services::resetSingle('authorization');
        parent::tearDown();
    }

    public function testViewUsesAuthoritativePlayerAndLinkedRows(): void
    {
        $this->asAuthorizedUser([$this->branch]);
        $result = service('player360')->view($this->player);

        self::assertSame($this->player, (int) $result['player']['id']);
        self::assertSame($this->branch, (int) $result['player']['branch_id']);
        self::assertSame(
            $this->database->table('attendance')->where(['player_id' => $this->player, 'branch_id' => $this->branch])->countAllResults(),
            count($result['sections']['attendance']['rows'])
        );
        self::assertSame('Not available', $result['sections']['performance']['value']);
        self::assertSame('Not available', $result['sections']['documents']['value']);
    }

    public function testAuthorizedPlayer360AccessUsesThePlayersBranch(): void
    {
        $this->asAuthorizedUser([$this->branch], ['player360.view', 'player.view']);

        $result = service('player360')->view($this->player);

        self::assertSame($this->player, (int) $result['player']['id']);
        self::assertSame($this->branch, (int) $result['player']['branch_id']);
    }

    public function testPlayerIdOutsideAuthorizedBranchIsRejected(): void
    {
        $otherBranch = (int) $this->database->table('branches')
            ->where('code', 'TEST_MCC_DEL')
            ->get()->getRow('id');
        $this->asAuthorizedUser([$otherBranch]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Forbidden');
        service('player360')->view($this->player);
    }

    public function testPlayer360CrossBranchAccessIsDeniedEvenWithThePermission(): void
    {
        $otherBranch = (int) $this->database->table('branches')
            ->where('code', 'TEST_MCC_DEL')
            ->get()
            ->getRow('id');
        $this->asAuthorizedUser([$otherBranch], ['player360.view', 'player.view']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Forbidden');

        service('player360')->view($this->player);
    }

    public function testPermissionIsRequired(): void
    {
        $this->asAuthorizedUser([$this->branch], []);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Forbidden');
        service('player360')->view($this->player);
    }

    private function asAuthorizedUser(array $branches, ?array $permissions = null): void
    {
        session()->set([
            'isLoggedIn' => true,
            'accountEnabled' => true,
            'user' => ['id' => $this->actor],
            'session_version' => 1,
            'authorizedBranches' => array_map('strval', $branches),
            'permissions' => $permissions ?? [
                'player360.view', 'player.view', 'attendance.view', 'session.update',
                'match.view', 'tournament.view', 'development.view', 'fitness.view',
            ],
        ]);
        \Config\Services::resetSingle('authorization');
    }
}
