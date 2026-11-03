<?php

declare(strict_types=1);

namespace Tests\Database;

use App\Database\Migrations\CreatePlayerSessionPlans;
use App\Database\Migrations\AddSessionPlanCoachingFocus;
use App\Services\PlayerSessionPlanService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Database;
use RuntimeException;

final class PlayerSessionPlanWorkflowTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $DBGroup = 'tests';
    protected $migrate = false;

    private int $actor;
    private int $branch;
    private int $player;

    protected function setUp(): void
    {
        parent::setUp();

        require_once APPPATH . 'Database/Migrations/20261027000100_CreatePlayerSessionPlans.php';
        require_once APPPATH . 'Database/Migrations/20261028000300_AddSessionPlanCoachingFocus.php';

        if (! $this->db->tableExists('player_session_plans')) {
            $migration = new CreatePlayerSessionPlans(Database::forge('tests'));
            $migration->up();
        }
        $coachingFocusColumn = $this->db
            ->query("SHOW COLUMNS FROM player_session_plans LIKE 'coaching_focus'")
            ->getRowArray();
        if (! is_array($coachingFocusColumn)) {
            $migration = new AddSessionPlanCoachingFocus(Database::forge('tests'));
            $migration->up();
        }

        $this->actor = (int) $this->db->table('users')
            ->where('email', 'test.mcc.coach@example.invalid')
            ->get()
            ->getRow('id');
        $this->branch = (int) $this->db->table('branches')
            ->where('code', 'TEST_MCC_VNS')
            ->get()
            ->getRow('id');
        $this->player = (int) $this->db->table('players')
            ->where('branch_id', $this->branch)
            ->orderBy('id', 'ASC')
            ->get()
            ->getRow('id');

        self::assertGreaterThan(0, $this->actor);
        self::assertGreaterThan(0, $this->branch);
        self::assertGreaterThan(0, $this->player);
        $this->ensureAssignment();
        $planIds = array_column(
            $this->db->table('player_session_plans')
                ->select('id')
                ->where('player_id', $this->player)
                ->get()
                ->getResultArray(),
            'id',
        );
        if ($planIds !== []) {
            $this->db->table('player_session_plan_history')->whereIn('plan_id', $planIds)->delete();
            $this->db->table('player_session_plans')->whereIn('id', $planIds)->delete();
        }
        $this->authorize(['player.view', 'player.session_plan.view', 'player.session_plan.manage'], [$this->branch]);
    }

    protected function tearDown(): void
    {
        session()->destroy();
        parent::tearDown();
    }

    public function testCoachCreatesEditsAndRetainsLinkedHistory(): void
    {
        $service = $this->plans();
        $service->save($this->payload(0, 'Initial outcome'), null);

        $plan = $this->db->table('player_session_plans')
            ->where('player_id', $this->player)
            ->get()
            ->getRowArray();
        self::assertSame($this->player, (int) $plan['player_id']);
        self::assertSame($this->branch, (int) $plan['branch_id']);
        self::assertSame($this->actor, (int) $plan['coach_user_id']);
        self::assertSame(1, (int) $plan['version']);
        self::assertSame('["Instruct","Observe"]', $plan['level_1_coaching_tools']);
        self::assertSame('["Technical","Mental"]', $plan['coaching_focus']);

        $updatedPayload = $this->payload(1, 'Updated outcome');
        $updatedPayload['expected_version'] = 1;
        $service->save($updatedPayload, (int) $plan['id']);

        $updated = $this->db->table('player_session_plans')->where('id', $plan['id'])->get()->getRowArray();
        self::assertSame('Updated outcome', $updated['outcome']);
        self::assertSame(2, (int) $updated['version']);
        self::assertSame(1, $this->db->table('player_session_plan_history')->where('plan_id', $plan['id'])->countAllResults());
        self::assertSame(2, $this->db->table('audit_logs')->where('entity_type', 'player_session_plan')->where('entity_id', (string) $plan['id'])->countAllResults());
        self::assertCount(1, $service->currentForPlayer($this->player));
        self::assertCount(1, $service->plan((int) $plan['id'])['history']);
    }

    public function testUnknownCoachingToolIsRejected(): void
    {
        $payload = $this->payload(0);
        $payload['level_1_coaching_tools'] = ['Unknown tool'];

        $this->expectExceptionObject(new RuntimeException('Invalid coaching tools.'));
        $this->plans()->save($payload, null);
    }

    public function testUnknownCoachingFocusIsRejected(): void
    {
        $payload = $this->payload(0);
        $payload['coaching_focus'] = ['Fitness'];

        $this->expectExceptionObject(new RuntimeException('Invalid coaching focus.'));
        $this->plans()->save($payload, null);
    }

    public function testPlayerCanHaveMultiplePlansAndEditingOneLeavesTheOtherUnchanged(): void
    {
        $service = $this->plans();
        $firstPayload = $this->payload(0, 'First outcome');
        $secondPayload = $this->payload(0, 'Second outcome');
        $firstId = $service->save($firstPayload, null);
        $secondId = $service->save($secondPayload, null);

        self::assertNotSame($firstId, $secondId);
        self::assertCount(2, $service->currentForPlayer($this->player));

        $firstUpdate = $this->payload(1, 'First outcome updated');
        $firstUpdate['expected_version'] = 1;
        $service->save($firstUpdate, $firstId);

        $first = $this->db->table('player_session_plans')->where('id', $firstId)->get()->getRowArray();
        $second = $this->db->table('player_session_plans')->where('id', $secondId)->get()->getRowArray();
        self::assertSame('First outcome updated', $first['outcome']);
        self::assertSame(2, (int) $first['version']);
        self::assertSame('Second outcome', $second['outcome']);
        self::assertSame(1, (int) $second['version']);

        $result = $service->list(['player' => (string) $this->player, 'page' => 1]);
        self::assertCount(2, $result['plans']);
    }

    public function testUnauthorizedCoachCrossBranchAndPermissionAreRejected(): void
    {
        $service = $this->plans();
        $this->authorize([], [$this->branch]);

        $this->expectExceptionObject(new RuntimeException('Forbidden'));
        $service->save($this->payload(0), null);
    }

    public function testCrossBranchSessionPlanCreationIsRejected(): void
    {
        $otherBranch = (int) $this->db->table('branches')
            ->where('code', 'TEST_MCC_DEL')
            ->get()
            ->getRow('id');
        $this->authorize(
            ['player.view', 'player.session_plan.view', 'player.session_plan.manage'],
            [$otherBranch],
        );

        try {
            $this->plans()->save($this->payload(0), null);
            self::fail('Cross-branch Session Plan creation was accepted.');
        } catch (RuntimeException $exception) {
            self::assertSame('Forbidden', $exception->getMessage());
        }
    }

    public function testAssignedCoachScopeAndOptimisticConcurrencyAreEnforced(): void
    {
        $service = $this->plans();
        $service->save($this->payload(0), null);
        $plan = $this->db->table('player_session_plans')->where('player_id', $this->player)->get()->getRowArray();

        $current = $this->payload(1, 'Current outcome');
        $current['expected_version'] = 1;
        $service->save($current, (int) $plan['id']);

        $stale = $this->payload(1, 'Stale outcome');
        $stale['expected_version'] = 1;
        try {
            $service->save($stale, (int) $plan['id']);
            self::fail('A stale update was accepted.');
        } catch (RuntimeException $exception) {
            self::assertSame('Session plan was changed by another user.', $exception->getMessage());
        }

        $this->db->table('player_coach_assignments')
            ->where(['player_id' => $this->player, 'coach_user_id' => $this->actor])
            ->where('effective_to IS NULL', null, false)
            ->update(['effective_to' => gmdate('Y-m-d H:i:s')]);
        try {
            $service->save($this->payload(0), null);
            self::fail('An unassigned Coach was accepted.');
        } catch (RuntimeException $exception) {
            self::assertSame('Coach is not assigned to this player.', $exception->getMessage());
        }
    }

    public function testMissingCsrfIsRejectedBySessionPlanRoute(): void
    {
        try {
            $this->withSession(session()->get())->post('player-session-plans', $this->payload(0));
            self::fail('Missing CSRF was accepted.');
        } catch (\CodeIgniter\Security\Exceptions\SecurityException) {
            self::assertTrue(true);
        }

        self::assertSame(0, $this->db->table('player_session_plans')->where('player_id', $this->player)->countAllResults());
    }

    public function testFiltersPaginationAndEscapedOutputContractAreAvailable(): void
    {
        $service = $this->plans();
        $payload = $this->payload(0, '<script>alert(1)</script>');
        $service->save($payload, null);
        $result = $service->list([
            'player' => (string) $this->player,
            'coach_id' => (string) $this->actor,
            'branch_id' => (string) $this->branch,
            'created_from' => date('Y-m-d'),
            'created_to' => date('Y-m-d'),
            'page' => 1,
        ]);

        self::assertCount(1, $result['plans']);
        self::assertSame($this->branch, (int) $result['plans'][0]['branch_id']);
        self::assertStringContainsString('<script>', $result['plans'][0]['outcome']);
        self::assertSame(1, $result['pages']);
    }

    private function plans(): PlayerSessionPlanService
    {
        return service('playerSessionPlan', false);
    }

    /** @return array<string, mixed> */
    private function payload(int $expectedVersion, string $outcome = 'A measurable outcome'): array
    {
        $payload = [
            'player_id' => $this->player,
            'expected_version' => $expectedVersion,
            'problem' => 'Front foot defence needs consistency.',
            'outcome' => $outcome,
            'method' => 'Progressive front foot practice.',
            'players' => 'One-to-one',
            'stage' => 'Foundation',
            'area' => 'Batting',
            'safety_considerations' => 'Use safe spacing and check the surface.',
            'coaching_focus' => ['Technical', 'Mental'],
            'technical' => 'Head still and eyes level.',
            'tactical' => 'Choose the scoring option early.',
            'mental' => 'Use a clear reset routine.',
            'physical' => 'Balanced base and controlled movement.',
        ];

        foreach (['level_1', 'level_2', 'debrief'] as $prefix) {
            foreach (['fixed_variable', 'coaching_tools', 'equipment', 'easier', 'harder'] as $suffix) {
                $payload[$prefix . '_' . $suffix] = $suffix === 'coaching_tools'
                    ? ['Instruct', 'Observe']
                    : ucfirst(str_replace('_', ' ', $suffix));
            }
        }

        return $payload;
    }

    private function ensureAssignment(): void
    {
        $exists = $this->db->table('player_coach_assignments')
            ->where(['player_id' => $this->player, 'coach_user_id' => $this->actor, 'branch_id' => $this->branch])
            ->where('effective_to IS NULL', null, false)
            ->countAllResults();
        if ($exists > 0) {
            return;
        }

        $effectiveFrom = gmdate('Y-m-d H:i:s');
        while ($this->db->table('player_coach_assignments')->where([
            'player_id' => $this->player,
            'effective_from' => $effectiveFrom,
        ])->countAllResults() > 0) {
            $effectiveFrom = gmdate('Y-m-d H:i:s', strtotime($effectiveFrom) + 1);
        }

        $this->db->table('player_coach_assignments')->insert([
            'player_id' => $this->player,
            'branch_id' => $this->branch,
            'coach_user_id' => $this->actor,
            'assigned_by' => $this->actor,
            'effective_from' => $effectiveFrom,
            'effective_to' => null,
            'created_at' => gmdate('Y-m-d H:i:s'),
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    /** @param list<string> $permissions @param list<int> $branches */
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
