<?php

declare(strict_types=1);

namespace Tests\Database;

use App\Services\PlayerService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use RuntimeException;

final class PlayerGoalWorkflowTest extends CIUnitTestCase
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
        $assignmentCount = $this->db->table('player_coach_assignments')
                ->where([
                    'player_id' => $this->player,
                    'coach_user_id' => $this->actor,
                ])
                ->where('effective_to IS NULL', null, false)
                ->countAllResults();
        if ($assignmentCount === 0) {
            $now = gmdate('Y-m-d H:i:s');
            $effectiveFrom = '2035-01-01 00:00:00';
            while ($this->db->table('player_coach_assignments')->where([
                'player_id' => $this->player,
                'effective_from' => $effectiveFrom,
            ])->countAllResults() > 0) {
                $effectiveFrom = gmdate(
                    'Y-m-d H:i:s',
                    strtotime($effectiveFrom) + 86400,
                );
            }
            $this->db->table('player_coach_assignments')->insert([
                'player_id' => $this->player,
                'branch_id' => $this->branch,
                'coach_user_id' => $this->actor,
                'assigned_by' => $this->actor,
                'effective_from' => $effectiveFrom,
                'effective_to' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $this->db->table('player_goals')->where('player_id', $this->player)->delete();
        $this->authorize(['player.view', 'player.goal.set'], [$this->branch]);
    }

    protected function tearDown(): void
    {
        session()->destroy();
        parent::tearDown();
    }

    public function testCoachCanCreateAndUpdateAnAssignedPlayerGoal(): void
    {
        $service = $this->players();
        $service->saveGoal($this->player, [
            'title' => 'Improve front foot defense',
            'description' => 'Complete two focused drills each week.',
            'expected_version' => 0,
        ]);

        $goal = $this->db->table('player_goals')
            ->where('player_id', $this->player)
            ->get()
            ->getRowArray();
        self::assertSame('Improve front foot defense', $goal['title']);
        self::assertSame(1, (int) $goal['version']);

        $service->saveGoal($this->player, [
            'title' => 'Improve front foot defense',
            'description' => 'Complete three focused drills each week.',
            'expected_version' => 1,
        ]);

        $updated = $this->db->table('player_goals')
            ->where('id', $goal['id'])
            ->get()
            ->getRowArray();
        self::assertSame('Complete three focused drills each week.', $updated['description']);
        self::assertSame(2, (int) $updated['version']);

        $detail = $service->find($this->player);
        self::assertSame('Improve front foot defense', $detail['goal']['title']);
        self::assertSame(2, (int) $detail['goal']['version']);
    }

    public function testAuthorizedAdminCanSetGoalWithoutPlayerAssignmentPermission(): void
    {
        $admin = (int) $this->db->table('user_roles ur')
            ->select('ur.user_id')
            ->join('roles r', 'r.id = ur.role_id')
            ->whereIn('r.name', ['Super Admin', 'Branch Admin'])
            ->orderBy('ur.user_id', 'ASC')
            ->get()
            ->getRow('user_id');

        self::assertGreaterThan(0, $admin);
        $this->actor = $admin;
        $this->authorize(['player.view', 'player.goal.set'], [$this->branch]);

        $this->players()->saveGoal($this->player, [
            'title' => 'Admin goal',
            'description' => 'Set within the authorized branch.',
            'expected_version' => 0,
        ]);

        $goal = $this->db->table('player_goals')
            ->where('player_id', $this->player)
            ->get()
            ->getRowArray();
        self::assertSame('Admin goal', $goal['title']);
    }

    public function testGoalRejectsInvalidValuesAndStaleVersionsWithoutChangingData(): void
    {
        $service = $this->players();

        try {
            $service->saveGoal($this->player, [
                'title' => '',
                'description' => 'Description',
                'expected_version' => 0,
            ]);
            self::fail('An empty goal title was accepted.');
        } catch (RuntimeException $exception) {
            self::assertSame('Invalid player goal.', $exception->getMessage());
        }

        try {
            $service->saveGoal($this->player, [
                'title' => 'Title',
                'description' => '',
                'expected_version' => 0,
            ]);
            self::fail('An empty goal description was accepted.');
        } catch (RuntimeException $exception) {
            self::assertSame('Invalid player goal.', $exception->getMessage());
        }

        $service->saveGoal($this->player, [
            'title' => 'Initial goal',
            'description' => 'Initial description',
            'expected_version' => 0,
        ]);
        $before = $this->db->table('player_goals')->where('player_id', $this->player)->get()->getRowArray();

        try {
            $service->saveGoal($this->player, [
                'title' => 'Stale goal',
                'description' => 'Should not save',
                'expected_version' => 0,
            ]);
            self::fail('A stale goal update was accepted.');
        } catch (RuntimeException $exception) {
            self::assertSame('Goal was changed by another user.', $exception->getMessage());
        }

        $after = $this->db->table('player_goals')->where('id', $before['id'])->get()->getRowArray();
        self::assertSame($before['title'], $after['title']);
        self::assertSame($before['version'], $after['version']);
    }

    public function testCoachMustHavePermissionAndAnAuthorizedPlayerAssignment(): void
    {
        $this->authorize([], [$this->branch]);

        try {
            $this->players()->saveGoal($this->player, [
                'title' => 'Blocked goal',
                'description' => 'Blocked description',
                'expected_version' => 0,
            ]);
            self::fail('Permission denial was not enforced.');
        } catch (RuntimeException $exception) {
            self::assertSame('Forbidden', $exception->getMessage());
        }

        $otherBranch = (int) $this->db->table('branches')
            ->where('code', 'TEST_MCC_DEL')
            ->get()
            ->getRow('id');
        $this->authorize(['player.view', 'player.goal.set'], [$otherBranch]);

        try {
            $this->players()->saveGoal($this->player, [
                'title' => 'Cross branch goal',
                'description' => 'Should be rejected',
                'expected_version' => 0,
            ]);
            self::fail('Cross-branch goal creation was accepted.');
        } catch (RuntimeException $exception) {
            self::assertSame('Forbidden', $exception->getMessage());
        }

        $this->db->table('player_coach_assignments')
            ->where([
                'player_id' => $this->player,
                'coach_user_id' => $this->actor,
            ])
            ->where('effective_to IS NULL', null, false)
            ->update(['effective_to' => gmdate('Y-m-d H:i:s')]);
        $this->authorize(['player.view', 'player.goal.set'], [$this->branch]);

        try {
            $this->players()->saveGoal($this->player, [
                'title' => 'Unassigned goal',
                'description' => 'Should be rejected',
                'expected_version' => 0,
            ]);
            self::fail('An unassigned Player goal was accepted.');
        } catch (RuntimeException $exception) {
            self::assertSame('Coach is not assigned to this player.', $exception->getMessage());
        }
    }

    public function testGoalSaveAuditsAndRollsBackOnValidationFailure(): void
    {
        $service = $this->players();
        $service->saveGoal($this->player, [
            'title' => 'Audited goal',
            'description' => '<script>alert(1)</script>',
            'expected_version' => 0,
        ]);

        $goal = $this->db->table('player_goals')->where('player_id', $this->player)->get()->getRowArray();
        self::assertSame(1, $this->db->table('audit_logs')->where([
            'action' => 'player_goal_created',
            'entity_id' => (string) $goal['id'],
        ])->countAllResults());

        try {
            $service->saveGoal($this->player, [
                'title' => 'Invalid update',
                'description' => str_repeat('x', 10001),
                'expected_version' => 1,
            ]);
            self::fail('An oversized description was accepted.');
        } catch (RuntimeException $exception) {
            self::assertSame('Invalid player goal.', $exception->getMessage());
        }

        self::assertSame(1, $this->db->table('player_goals')->where('player_id', $this->player)->countAllResults());
        self::assertSame(1, (int) $this->db->table('player_goals')
            ->where('player_id', $this->player)
            ->get()
            ->getRow('version'));
    }

    public function testGoalRouteRejectsMissingOrInvalidCsrf(): void
    {
        try {
            $this->withSession(session()->get())->post(
                'players/' . $this->player . '/goal',
                [
                    'title' => 'Missing CSRF',
                    'description' => 'Should be rejected.',
                    'expected_version' => 0,
                ],
            );
            self::fail('Missing CSRF was accepted.');
        } catch (\CodeIgniter\Security\Exceptions\SecurityException) {
            self::assertTrue(true);
        }

        try {
            $this->withSession(session()->get())->post(
                'players/' . $this->player . '/goal',
                [
                    csrf_token() => 'invalid-token',
                    'title' => 'Invalid CSRF',
                    'description' => 'Should be rejected.',
                    'expected_version' => 0,
                ],
            );
            self::fail('Invalid CSRF was accepted.');
        } catch (\CodeIgniter\Security\Exceptions\SecurityException) {
            self::assertTrue(true);
        }

        self::assertSame(
            0,
            $this->db->table('player_goals')->where('player_id', $this->player)->countAllResults(),
        );
    }

    public function testGoalListFiltersByPlayerBranchCoachTitleDatesAndScope(): void
    {
        $this->players()->saveGoal($this->player, [
            'title' => 'Filterable goal',
            'description' => 'A goal visible in the dedicated list.',
            'expected_version' => 0,
        ]);

        $player = $this->players()->find($this->player);
        $playerName = (string) ($player['profile']['full_name'] ?? '');
        $result = $this->players()->goalList([
            'player' => $playerName,
            'branch_id' => (string) $this->branch,
            'coach_id' => (string) $this->actor,
            'title' => 'Filterable',
            'created_from' => date('Y-m-d'),
            'created_to' => date('Y-m-d'),
            'updated_from' => date('Y-m-d'),
            'updated_to' => date('Y-m-d'),
            'page' => 1,
        ]);

        self::assertCount(1, $result['goals']);
        self::assertSame('Filterable goal', $result['goals'][0]['title']);
        self::assertSame($this->branch, (int) $result['goals'][0]['branch_id']);
        self::assertSame(1, $result['page']);
        self::assertSame(1, $result['pages']);
        self::assertNotEmpty($result['coaches']);

        $otherBranch = (int) $this->db->table('branches')
            ->where('code', 'TEST_MCC_DEL')
            ->get()
            ->getRow('id');
        $this->authorize(['player.goal.set'], [$otherBranch]);
        self::assertSame([], $this->players()->goalList()['goals']);

        $this->authorize(['player.view', 'player.goal.set'], [$this->branch]);
        $this->db->table('player_coach_assignments')
            ->where([
                'player_id' => $this->player,
                'coach_user_id' => $this->actor,
            ])
            ->where('effective_to IS NULL', null, false)
            ->update(['effective_to' => gmdate('Y-m-d H:i:s')]);
        self::assertSame([], $this->players()->goalList()['goals']);
    }

    public function testGoalPlayerSearchUsesNameOrIdAndCoachAssignmentScope(): void
    {
        $player = $this->players()->goalPlayerContext($this->player);
        $byName = $this->players()->searchGoalPlayers((string) $player['player_name']);
        $byId = $this->players()->searchGoalPlayers((string) $this->player);

        self::assertNotEmpty($byName);
        self::assertNotEmpty($byId);
        self::assertSame($this->player, (int) $byId[0]['id']);
        self::assertSame($this->branch, (int) $byId[0]['branch_id']);

        $this->db->table('player_coach_assignments')
            ->where([
                'player_id' => $this->player,
                'coach_user_id' => $this->actor,
            ])
            ->where('effective_to IS NULL', null, false)
            ->update(['effective_to' => gmdate('Y-m-d H:i:s')]);

        self::assertSame([], $this->players()->searchGoalPlayers((string) $this->player));
    }

    private function players(): PlayerService
    {
        return service('player', false);
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
