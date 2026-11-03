<?php

declare(strict_types=1);

namespace Tests\Database;

use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;
use RuntimeException;

final class PerformanceIntegrationTest extends CIUnitTestCase
{
    private object $database;
    private int $player;
    private int $branch;
    private int $otherBranch;
    private int $actor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->database = Database::connect('tests');
        $this->actor = (int) $this->database->table('users')->where('email', 'test.mcc.superadmin@example.invalid')->get()->getRow('id');
        $this->player = (int) $this->database->table('players p')->select('p.id')->join('branches b', 'b.id = p.branch_id')->where('b.code', 'TEST_MCC_VNS')->get()->getRow('id');
        $this->branch = (int) $this->database->table('players')->where('id', $this->player)->get()->getRow('branch_id');
        $this->otherBranch = (int) $this->database->table('branches')->where('code', 'TEST_MCC_DEL')->get()->getRow('id');
        $this->database->table('performance_records')->emptyTable();
        $this->login([$this->branch]);
    }

    protected function tearDown(): void
    {
        $this->database->table('performance_records')->emptyTable();
        session()->destroy();
        \Config\Services::resetSingle('authorization');
        parent::tearDown();
    }

    public function testCrudHistoryAndKpisUseOnlyStoredRows(): void
    {
        $service = service('performance', false);
        $service->save(['branch_id' => $this->branch, 'player_id' => $this->player, 'record_type' => 'batting', 'recorded_on' => '2026-09-01', 'runs' => 40, 'balls_faced' => 20, 'dismissals' => 1, 'fours' => 4]);
        $service->save(['branch_id' => $this->branch, 'player_id' => $this->player, 'record_type' => 'bowling', 'recorded_on' => '2026-09-02', 'wickets' => 2, 'runs_conceded' => 12, 'legal_balls' => 12]);
        $result = $service->player($this->player);
        self::assertSame(40, $result['kpis']['runs']);
        self::assertSame(200.0, $result['kpis']['strike_rate']);
        self::assertSame(40.0, $result['kpis']['average']);
        self::assertSame(2, $result['kpis']['wickets']);
        self::assertSame(6.0, $result['kpis']['economy']);
        self::assertCount(2, $result['rows']);
    }

    public function testCrossBranchAndIdorReadsAreDenied(): void
    {
        $this->login([$this->otherBranch]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Forbidden');
        service('performance', false)->player($this->player);
    }

    public function testSoftDeletedRowsDoNotRemainInHistory(): void
    {
        $service = service('performance', false);
        $id = $service->save(['branch_id' => $this->branch, 'player_id' => $this->player, 'record_type' => 'fielding', 'recorded_on' => '2026-09-03', 'catches' => 1]);
        $service->delete($id);
        self::assertSame(0, count($service->player($this->player)['rows']));
    }

    private function login(array $branches): void
    {
        session()->set(['isLoggedIn' => true, 'accountEnabled' => true, 'user' => ['id' => $this->actor], 'authorizedBranches' => array_map('strval', $branches), 'permissions' => ['performance.view', 'performance.create', 'performance.update', 'performance.delete'], 'roles' => ['Super Admin']]);
        \Config\Services::resetSingle('authorization');
    }
}
