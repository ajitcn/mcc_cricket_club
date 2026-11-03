<?php

declare(strict_types=1);

namespace Tests\Database;

use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;
use RuntimeException;

final class PlayerDevelopmentLineageSafeTest extends CIUnitTestCase
{
    protected $DBGroup = 'tests';

    private object $database;

    private int $actor;

    private int $branch;

    private int $otherBranch;

    private int $player;

    private int $otherPlayer;

    private int $coach;

    protected function setUp(): void
    {
        parent::setUp();
        $this->database = Database::connect('tests');

        if (strtolower((string) $this->database->getDatabase()) !== 'mcc_cricket_club_test') {
            self::markTestSkipped('Safe lineage tests require the isolated test database.');
        }

        if (! $this->database->fieldExists('version', 'development_goals')) {
            self::markTestSkipped('Safe lineage tests require the goal-version migration.');
        }

        $this->actor = $this->userId('test.mcc.superadmin@example.invalid');
        $this->coach = $this->userId('test.mcc.coach@example.invalid');
        $this->branch = $this->branchId('TEST_MCC_VNS');
        $this->otherBranch = $this->branchId('TEST_MCC_DEL');
        $this->player = $this->playerId($this->branch);
        $this->otherPlayer = $this->playerId($this->otherBranch);

        $this->ensureCoachProfile($this->coach, true);
        $this->authorize([$this->branch]);
    }

    protected function tearDown(): void
    {
        session()->destroy();
        \Config\Services::resetSingle('authorization');
        parent::tearDown();
    }

    public function testValidProgressAccessAndStaleVersionRejection(): void
    {
        $goalId = $this->goalId();
        $goal = $this->database->table('development_goals')->where('id', $goalId)->get()->getRowArray();
        $version = (int) $goal['version'];
        $historyBefore = $this->historyCount($goalId);

        service('development')->progress($goalId, [
            'version' => $version,
            'progress_value' => '1.25',
            'note' => 'TEST_MCC_SAFE_PROGRESS',
        ]);

        self::assertSame($historyBefore + 1, $this->historyCount($goalId));
        self::assertSame($version + 1, (int) $this->goal($goalId)['version']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('changed by another user');
        service('development')->progress($goalId, [
            'version' => $version,
            'progress_value' => '2.00',
        ]);
    }

    public function testCrossBranchAndMalformedLineageAreDenied(): void
    {
        $goalId = $this->goalId();
        $this->authorize([$this->otherBranch]);

        try {
            service('development')->progress($goalId, [
                'version' => (int) $this->goal($goalId)['version'],
                'progress_value' => '1.00',
            ]);
            self::fail('Expected cross-branch denial.');
        } catch (RuntimeException $exception) {
            self::assertSame('Forbidden', $exception->getMessage());
        }

        $malformed = $this->malformedPlanId();
        $this->authorize([$this->branch]);

        self::assertSame('Not found.', $this->readPlanFailure($malformed));
    }

    public function testInactiveAndNonCoachUsersAreRejected(): void
    {
        $fitness = $this->userId('test.mcc.fitness@example.invalid');
        $disabled = $this->userId('test.mcc.disabled@example.invalid');
        $staleCoach = $this->staleCoachId();
        $service = service('development');

        try {
            $service->save($this->planInput($fitness));
            self::fail('Expected non-Coach denial.');
        } catch (RuntimeException $exception) {
            self::assertSame('Invalid coach.', $exception->getMessage());
        }

        self::assertSame('Invalid coach.', $this->saveFailure($service, $this->planInput($disabled)));
        self::assertSame('Invalid coach.', $this->saveFailure($service, $this->planInput($staleCoach)));
    }

    public function testAuditFailureRollsBackPlanAndGoalMutations(): void
    {
        $failing = new \App\Services\PlayerDevelopmentService(
            static function (...$arguments): void {
                throw new RuntimeException('synthetic audit failure');
            }
        );
        $title = 'TEST_MCC_SAFE_AUDIT_ROLLBACK';
        $beforePlans = $this->database->table('development_plans')->where('title', $title)->countAllResults();

        try {
            $failing->save($this->planInput($this->coach, $title));
            self::fail('Expected audit failure.');
        } catch (RuntimeException $exception) {
            self::assertSame('synthetic audit failure', $exception->getMessage());
        }

        self::assertSame(
            $beforePlans,
            $this->database->table('development_plans')
                ->where('title', $title)
                ->countAllResults()
        );

        $planId = $this->planId();
        $goalTitle = 'TEST_MCC_SAFE_AUDIT_GOAL';
        $beforeGoals = $this->database->table('development_goals')
            ->where(['plan_id' => $planId, 'goal' => $goalTitle])
            ->countAllResults();

        try {
            $failing->goal($planId, ['goal' => $goalTitle]);
            self::fail('Expected goal audit failure.');
        } catch (RuntimeException $exception) {
            self::assertSame('synthetic audit failure', $exception->getMessage());
        }

        self::assertSame(
            $beforeGoals,
            $this->database->table('development_goals')
                ->where(['plan_id' => $planId, 'goal' => $goalTitle])
                ->countAllResults()
        );
    }

    private function goalId(): int
    {
        $planId = $this->planId();
        $row = $this->database->table('development_goals')->where('plan_id', $planId)->orderBy('id')->get()->getRowArray();

        if (is_array($row)) {
            return (int) $row['id'];
        }

        return service('development')->goal($planId, [
            'goal' => 'TEST_MCC_SAFE_GOAL',
            'target_date' => '2026-12-31',
        ]);
    }

    private function planId(): int
    {
        $row = $this->database->table('development_plans')
            ->where([
                'title' => 'TEST_MCC_SAFE_LINEAGE_PLAN',
                'branch_id' => $this->branch,
                'player_id' => $this->player,
                'coach_user_id' => $this->coach,
            ])
            ->orderBy('id', 'DESC')
            ->get()
            ->getRowArray();

        if (is_array($row)) {
            return (int) $row['id'];
        }

        return service('development')->save($this->planInput($this->coach, 'TEST_MCC_SAFE_LINEAGE_PLAN'));
    }

    private function malformedPlanId(): int
    {
        $title = 'TEST_MCC_SAFE_MALFORMED_LINEAGE';
        $row = $this->database->table('development_plans')->where('title', $title)->get()->getRowArray();

        if (is_array($row)) {
            return (int) $row['id'];
        }

        $this->database->table('development_plans')->insert([
            'branch_id' => $this->branch,
            'player_id' => $this->otherPlayer,
            'coach_user_id' => $this->coach,
            'title' => $title,
            'status' => 'active',
            'target_date' => '2026-12-31',
            'notes' => 'Safe malformed lineage fixture.',
            'created_by' => $this->actor,
            'created_at' => '2026-08-30 10:00:00',
            'updated_at' => '2026-08-30 10:00:00',
        ]);

        return (int) $this->database->insertID();
    }

    private function readPlanFailure(int $planId): string
    {
        try {
            service('development')->find($planId);
        } catch (RuntimeException $exception) {
            return $exception->getMessage();
        }

        self::fail('Expected malformed lineage denial.');
    }

    private function saveFailure(object $service, array $input): string
    {
        try {
            $service->save($input);
        } catch (RuntimeException $exception) {
            return $exception->getMessage();
        }

        self::fail('Expected invalid coach denial.');
    }

    private function planInput(int $coach, string $title = 'TEST_MCC_SAFE_LINEAGE_PLAN'): array
    {
        return [
            'branch_id' => $this->branch,
            'player_id' => $this->player,
            'coach_user_id' => $coach,
            'title' => $title,
            'status' => 'active',
            'target_date' => '2026-12-31',
            'notes' => 'Safe integration fixture.',
        ];
    }

    private function authorize(array $branches): void
    {
        session()->set([
            'isLoggedIn' => true,
            'accountEnabled' => true,
            'user' => ['id' => $this->actor],
            'session_version' => 1,
            'authorizedBranches' => array_map('strval', $branches),
            'permissions' => [
                'development.view',
                'development.create',
                'development.update',
                'development.progress',
            ],
        ]);
        \Config\Services::resetSingle('authorization');
    }

    private function ensureCoachProfile(int $userId, bool $active): void
    {
        if ($this->database->table('coach_profiles')->where('user_id', $userId)->countAllResults() === 0) {
            $this->database->table('coach_profiles')->insert([
                'user_id' => $userId,
                'is_active' => $active ? 1 : 0,
                'created_at' => '2026-08-30 10:00:00',
                'updated_at' => '2026-08-30 10:00:00',
            ]);
        }
    }

    private function staleCoachId(): int
    {
        $email = 'test.mcc.pd.stale.coach@example.invalid';
        $userId = $this->userId($email);

        if ($userId === 0) {
            $this->database->table('users')->insert([
                'email' => $email,
                'password_hash' => password_hash('MccTest!2026-SeedOnly', PASSWORD_DEFAULT),
                'display_name' => 'TEST_MCC_PD_STALE_COACH',
                'is_enabled' => 1,
                'failed_login_count' => 0,
                'session_version' => 1,
                'locked_until' => null,
                'last_login_at' => null,
                'created_at' => '2026-08-30 10:00:00',
                'updated_at' => '2026-08-30 10:00:00',
            ]);
            $userId = (int) $this->database->insertID();
        }

        $coachRole = (int) $this->database->table('roles')
            ->where('name', 'Coach')
            ->get()
            ->getRow('id');

        if ($this->database->table('user_roles')->where([
            'user_id' => $userId,
            'role_id' => $coachRole,
        ])->countAllResults() === 0) {
            $this->database->table('user_roles')->insert([
                'user_id' => $userId,
                'role_id' => $coachRole,
                'created_at' => '2026-08-30 10:00:00',
            ]);
        }

        if ($this->database->table('user_branches')->where([
            'user_id' => $userId,
            'branch_id' => $this->branch,
        ])->countAllResults() === 0) {
            $this->database->table('user_branches')->insert([
                'user_id' => $userId,
                'branch_id' => $this->branch,
                'created_at' => '2026-08-30 10:00:00',
            ]);
        }

        $this->ensureCoachProfile($userId, false);

        return $userId;
    }

    private function goal(int $goalId): array
    {
        return $this->database->table('development_goals')->where('id', $goalId)->get()->getRowArray();
    }

    private function historyCount(int $goalId): int
    {
        return $this->database->table('development_progress_history')->where('goal_id', $goalId)->countAllResults();
    }

    private function userId(string $email): int
    {
        return (int) $this->database->table('users')->where('email', $email)->get()->getRow('id');
    }

    private function branchId(string $code): int
    {
        return (int) $this->database->table('branches')->where('code', $code)->get()->getRow('id');
    }

    private function playerId(int $branchId): int
    {
        $player = $this->database->table('players')
            ->where('branch_id', $branchId)
            ->orderBy('id')
            ->get()
            ->getRow('id');

        if ($player !== null) {
            return (int) $player;
        }

        $token = strtoupper(bin2hex(random_bytes(6)));
        $this->database->table('persons')->insert([
            'full_name' => 'TEST_MCC_PD_PLAYER_' . $token,
            'email' => 'test.mcc.pd.player.' . strtolower($token) . '@example.invalid',
            'created_at' => '2026-08-30 10:00:00',
            'updated_at' => '2026-08-30 10:00:00',
        ]);
        $personId = (int) $this->database->insertID();

        $this->database->table('players')->insert([
            'person_id' => $personId,
            'branch_id' => $branchId,
            'status' => 'active',
            'created_at' => '2026-08-30 10:00:00',
            'updated_at' => '2026-08-30 10:00:00',
        ]);

        return (int) $this->database->insertID();
    }
}
