<?php

declare(strict_types=1);

namespace Tests\Database;

use App\Services\FitnessManagementService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use RuntimeException;

final class FitnessManagementIntegrationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $DBGroup = 'tests';
    protected $migrate = false;

    private int $branch;
    private int $otherBranch;
    private int $player;
    private int $fitnessCoach;

    protected function setUp(): void
    {
        parent::setUp();
        $this->branch = $this->id('branches', ['code' => 'TEST_MCC_VNS']);
        $this->otherBranch = $this->id('branches', ['code' => 'TEST_MCC_DEL']);
        $this->player = $this->id('persons', ['full_name' => 'TEST_MCC_CONVERTED_PLAYER']);
        $this->player = (int) $this->db->table('players p')->select('p.id')->join('persons pe', 'pe.id = p.person_id')
            ->where('pe.full_name', 'TEST_MCC_CONVERTED_PLAYER')->get()->getRowArray()['id'];
        $this->fitnessCoach = $this->id('users', ['email' => 'test.mcc.fitness@example.invalid']);
        $this->db->table('fitness_plans')->emptyTable();
        $this->db->table('fitness_assessments')->emptyTable();
    }

    protected function tearDown(): void
    {
        $this->db->table('fitness_plans')->emptyTable();
        $this->db->table('fitness_assessments')->emptyTable();
        session()->destroy();
        \Config\Services::resetSingle('authorization');
        parent::tearDown();
    }

    public function testFitnessCoachCanCreateHistoryAndKpisUseRealRows(): void
    {
        $this->login(
            ['fitness.view', 'fitness.create', 'fitness.update', 'fitness.plan.create', 'fitness.plan.view'],
            $this->branch,
            $this->fitnessCoach,
            ['Fitness Coach']
        );
        $service = service('fitness', false);
        $first = $service->saveAssessment($this->player, [
            'assessment_date' => '2026-08-01', 'height_cm' => '150', 'weight_kg' => '45',
            'vertical_jump_cm' => '30', 'coach_notes' => 'Initial stored assessment',
        ]);
        $service->saveAssessment($this->player, [
            'assessment_date' => '2026-09-01', 'height_cm' => '150', 'weight_kg' => '48',
        ]);
        $plan = $service->savePlan($this->player, [
            'name' => 'September conditioning', 'starts_on' => '2026-09-01', 'coach_user_id' => $this->fitnessCoach,
        ]);
        $result = $service->player($this->player);

        self::assertGreaterThan(0, $first);
        self::assertGreaterThan(0, $plan);
        self::assertSame(2, $result['kpis']['assessment_count']);
        self::assertSame(1, $result['kpis']['active_plan_count']);
        self::assertSame(21.33, $result['kpis']['latest_bmi']);
        self::assertSame(1.33, $result['kpis']['bmi_change']);
    }

    public function testCrossBranchPlayerIsDeniedEvenWithFitnessPermission(): void
    {
        $this->login(['fitness.view', 'fitness.create'], $this->otherBranch, $this->fitnessCoach, ['Fitness Coach']);
        $this->expectExceptionMessage('Forbidden');
        service('fitness', false)->player($this->player);
    }

    public function testCoachCannotUseFitnessIdToReadUnassignedPlayer(): void
    {
        $coach = $this->id('users', ['email' => 'test.mcc.coach@example.invalid']);
        $this->login(['fitness.view'], $this->branch, $coach, ['Coach']);
        $this->expectExceptionMessage('Not found.');
        service('fitness', false)->player($this->player + 999999);
    }

    private function login(array $permissions, int $branch, int $user, array $roles): void
    {
        session()->set([
            'isLoggedIn' => true, 'accountEnabled' => true, 'user' => ['id' => $user],
            'authorizedBranches' => [(string) $branch], 'permissions' => $permissions, 'roles' => $roles,
        ]);
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
