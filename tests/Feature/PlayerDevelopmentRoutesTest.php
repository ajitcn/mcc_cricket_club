<?php

declare(strict_types=1);

namespace Tests\Feature;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

final class PlayerDevelopmentRoutesTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $DBGroup = 'tests';
    protected $migrate = false;

    private int $actor;

    private int $branch;

    private int $otherBranch;

    private int $player;

    private int $coach;

    private string $coachName = '';

    private int $plan;

    private int $goal;

    protected function setUp(): void
    {
        parent::setUp();

        self::assertSame(
            'mcc_cricket_club_test',
            strtolower((string) $this->db->getDatabase())
        );

        $this->actor = $this->userId('test.mcc.superadmin@example.invalid');
        $this->coach = $this->userId('test.mcc.coach@example.invalid');
        $this->coachName = (string) ($this->db->table('users')
            ->select('display_name')
            ->where('id', $this->coach)
            ->get()
            ->getRow('display_name') ?? '');
        $this->branch = $this->branchId('TEST_MCC_VNS');
        $this->otherBranch = $this->branchId('TEST_MCC_DEL');
        $this->player = (int) $this->db->table('players')
            ->where('branch_id', $this->branch)
            ->orderBy('id')
            ->get()
            ->getRow('id');
        $this->ensureCoachProfile();
        $this->plan = 0;
        $this->goal = 0;

        if (min($this->actor, $this->coach, $this->player) > 0) {
            $now = gmdate('Y-m-d H:i:s');
            $this->db->table('development_plans')->insert([
                'branch_id' => $this->branch,
                'player_id' => $this->player,
                'coach_user_id' => $this->coach,
                'title' => 'TEST_MCC_UI_ROUTE_PLAN_' . strtoupper(bin2hex(random_bytes(5))),
                'status' => 'active',
                'target_date' => '2026-12-31',
                'notes' => 'Persistent route fixture.',
                'created_by' => $this->actor,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $this->plan = (int) $this->db->insertID();

            $this->db->table('development_goals')->insert([
                'plan_id' => $this->plan,
                'goal' => 'TEST_MCC_ROUTE_GOAL_' . strtoupper(bin2hex(random_bytes(4))),
                'status' => 'open',
                'target_date' => '2026-12-31',
                'progress_value' => null,
                'version' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $this->goal = (int) $this->db->insertID();
        }

        if (min($this->actor, $this->branch, $this->otherBranch, $this->plan, $this->goal) < 1) {
            self::markTestSkipped('Development route fixtures are not available.');
        }
    }

    protected function tearDown(): void
    {
        session()->destroy();
        \Config\Services::resetSingle('authorization');
        parent::tearDown();
    }

    public function testAuthorizedUserCanOpenDevelopmentList(): void
    {
        $this->authorize(['development.view'], [$this->branch]);

        $list = $this->withSession(session()->get())->get('development');

        self::assertSame(200, $list->response()->getStatusCode());
        self::assertStringContainsString('Development plans', $list->getBody());
    }

    public function testAuthorizedUserCanOpenSharedDevelopmentPlanForm(): void
    {
        $this->authorize(
            ['development.view', 'development.create'],
            [$this->branch]
        );

        $form = $this->withSession(session()->get())->get('development/new');

        self::assertSame(200, $form->response()->getStatusCode(), $form->getBody());
        self::assertStringContainsString('mcc-page-heading', $form->getBody());
        self::assertStringContainsString('mcc-card-heading', $form->getBody());
        self::assertStringContainsString('name="branch_id"', $form->getBody());
        self::assertStringContainsString('name="player_id"', $form->getBody());
        self::assertStringContainsString('name="coach_user_id"', $form->getBody());
        self::assertStringContainsString('class="form-control selectpicker btSelect"', $form->getBody());
        self::assertStringContainsString('data-bt-select', $form->getBody());
        self::assertStringContainsString('data-live-search="true"', $form->getBody());
        self::assertStringContainsString('data-actions-box="true"', $form->getBody());
        self::assertStringContainsString('title="Select a Player"', $form->getBody());
        self::assertStringContainsString('title="Select a Coach"', $form->getBody());
        self::assertStringContainsString('data-development-coach', $form->getBody());
        self::assertStringContainsString('data-branch-id=', $form->getBody());
        if ($this->coachName !== '') {
            self::assertStringContainsString(esc($this->coachName), $form->getBody());
        }
        self::assertStringContainsString('name="program_id"', $form->getBody());
        self::assertStringContainsString('name="batch_id"', $form->getBody());
        self::assertStringContainsString('csrf_test_name', $form->getBody());
        self::assertStringContainsString('data-mcc-date-picker', $form->getBody());
        self::assertStringContainsString('mcc-form-actions', $form->getBody());
    }

    public function testAuthorizedUserCanOpenDevelopmentDetail(): void
    {
        $this->authorize(['development.view'], [$this->branch]);

        $detail = $this->withSession(session()->get())->get('development/' . $this->plan);

        self::assertSame(200, $detail->response()->getStatusCode(), $detail->getBody());
        self::assertStringContainsString('Development detail', $detail->getBody());
    }

    public function testCompletedPlanEditIsRejectedWithoutPartialWrite(): void
    {
        $this->authorize(['development.view', 'development.update'], [$this->branch]);
        $this->completePlan();
        $before = $this->db->table('development_plans')->where('id', $this->plan)->get()->getRowArray();

        $response = $this->withSession(session()->get())->post('development/' . $this->plan, [
            'branch_id' => $this->branch,
            'player_id' => $this->player,
            'coach_user_id' => $this->coach,
            'title' => 'Rejected completed edit',
            'status' => 'active',
            'target_date' => '2026-12-31',
            csrf_token() => csrf_hash(),
        ]);

        self::assertSame(302, $response->response()->getStatusCode(), $response->getBody());
        self::assertStringEndsWith('/development/' . $this->plan . '/edit', (string) $response->response()->getHeaderLine('Location'));
        self::assertSame('Completed development plans cannot be edited or extended.', session('error'));
        self::assertSame($before['title'], $this->db->table('development_plans')->where('id', $this->plan)->get()->getRow('title'));
        self::assertSame('completed', $this->db->table('development_plans')->where('id', $this->plan)->get()->getRow('status'));
    }

    public function testCompletedPlanEntryAndProgressUpdatesAreRejectedWithoutWrites(): void
    {
        $this->authorize(['development.view', 'development.update', 'development.progress'], [$this->branch]);
        $this->completePlan();
        $goalCount = $this->db->table('development_goals')->where('plan_id', $this->plan)->countAllResults();
        $historyCount = $this->db->table('development_progress_history')->where('goal_id', $this->goal)->countAllResults();
        $version = (int) $this->db->table('development_goals')->where('id', $this->goal)->get()->getRow('version');

        $goalResponse = $this->withSession(session()->get())->post('development/' . $this->plan . '/goals', [
            'goal' => 'Rejected completed goal',
            csrf_token() => csrf_hash(),
        ]);
        $progressResponse = $this->withSession(session()->get())->post('development/goals/' . $this->goal . '/progress', [
            'version' => $version,
            'progress_value' => '99',
            csrf_token() => csrf_hash(),
        ]);

        self::assertStringEndsWith('/development/' . $this->plan, (string) $goalResponse->response()->getHeaderLine('Location'));
        self::assertStringEndsWith('/development/' . $this->plan, (string) $progressResponse->response()->getHeaderLine('Location'));
        self::assertSame($goalCount, $this->db->table('development_goals')->where('plan_id', $this->plan)->countAllResults());
        self::assertSame($historyCount, $this->db->table('development_progress_history')->where('goal_id', $this->goal)->countAllResults());
        self::assertSame($version, (int) $this->db->table('development_goals')->where('id', $this->goal)->get()->getRow('version'));
    }

    public function testCompletedPlanDetailIsViewableAndReadOnly(): void
    {
        $this->authorize(['development.view'], [$this->branch]);
        $this->completePlan();

        $detail = $this->withSession(session()->get())->get('development/' . $this->plan);

        self::assertSame(200, $detail->response()->getStatusCode(), $detail->getBody());
        self::assertStringContainsString('Completed development plans cannot be edited or extended.', $detail->getBody());
        self::assertStringNotContainsString('/development/' . $this->plan . '/edit', $detail->getBody());
        self::assertStringNotContainsString('Add goal', $detail->getBody());
        self::assertStringNotContainsString('Record progress', $detail->getBody());
        self::assertStringNotContainsString('/progress', $detail->getBody());
    }

    public function testValidGoalUpdateReturnsToDevelopmentDetail(): void
    {
        $this->authorize(['development.view', 'development.update'], [$this->branch]);
        $goal = 'TEST_MCC_VALID_GOAL_' . strtoupper(bin2hex(random_bytes(4)));

        $response = $this->withSession(session()->get())->post('development/' . $this->plan . '/goals', [
            'goal' => $goal,
            'target_date' => '2026-12-31',
            csrf_token() => csrf_hash(),
        ]);

        self::assertSame(302, $response->response()->getStatusCode(), $response->getBody());
        self::assertStringEndsWith('/development/' . $this->plan, (string) $response->response()->getHeaderLine('Location'));
        self::assertSame(1, $this->db->table('development_goals')->where('plan_id', $this->plan)->where('goal', $goal)->countAllResults());
    }

    public function testValidProgressUpdateReturnsToDevelopmentDetail(): void
    {
        $this->authorize(['development.view', 'development.progress'], [$this->branch]);

        $response = $this->withSession(session()->get())->post('development/goals/' . $this->goal . '/progress', [
            'version' => 1,
            'progress_value' => '75.5',
            'note' => 'TEST_MCC_PROGRESS_NOTE',
            csrf_token() => csrf_hash(),
        ]);

        self::assertSame(302, $response->response()->getStatusCode(), $response->getBody());
        self::assertStringEndsWith('/development/' . $this->plan, (string) $response->response()->getHeaderLine('Location'));
        self::assertSame(1, $this->db->table('development_progress_history')->where('goal_id', $this->goal)->where('note', 'TEST_MCC_PROGRESS_NOTE')->countAllResults());
    }

    public function testGoalValidationFailureReturnsToDetailAndPreservesInput(): void
    {
        $this->authorize(['development.view', 'development.update'], [$this->branch]);
        $response = $this->withSession(session()->get())->post('development/' . $this->plan . '/goals', [
            'goal' => 'Preserve this submitted goal.',
            'target_date' => 'not-a-date',
            csrf_token() => csrf_hash(),
        ]);

        self::assertSame(302, $response->response()->getStatusCode(), $response->getBody());
        self::assertStringEndsWith('/development/' . $this->plan, (string) $response->response()->getHeaderLine('Location'));
        self::assertSame('target_date', array_key_first((array) session('field_errors')));
        $oldInput = (array) session('_ci_old_input');
        self::assertSame('Preserve this submitted goal.', $oldInput['post']['goal'] ?? null);
        self::assertSame('not-a-date', $oldInput['post']['target_date'] ?? null);
    }

    public function testProgressValidationFailureReturnsToDetailAndPreservesInput(): void
    {
        $this->authorize(['development.view', 'development.progress'], [$this->branch]);
        $response = $this->withSession(session()->get())->post('development/goals/' . $this->goal . '/progress', [
            'version' => 1,
            'progress_value' => 'not-a-number',
            'note' => 'Preserve this progress note.',
            csrf_token() => csrf_hash(),
        ]);

        self::assertSame(302, $response->response()->getStatusCode(), $response->getBody());
        self::assertStringEndsWith('/development/' . $this->plan, (string) $response->response()->getHeaderLine('Location'));
        self::assertSame('progress_value', array_key_first((array) session('field_errors')));
        $oldInput = (array) session('_ci_old_input');
        self::assertSame('not-a-number', $oldInput['post']['progress_value'] ?? null);
        self::assertSame('Preserve this progress note.', $oldInput['post']['note'] ?? null);
    }

    public function testCrossBranchGoalRequestRemainsDenied(): void
    {
        $this->authorize(['development.view', 'development.update'], [$this->otherBranch]);
        $response = $this->withSession(session()->get())->post('development/' . $this->plan . '/goals', [
            'goal' => 'Unauthorized goal',
            csrf_token() => csrf_hash(),
        ]);

        self::assertSame(403, $response->response()->getStatusCode(), $response->getBody());
    }

    public function testUnexpectedProgressReferenceUsesSafeResponse(): void
    {
        $this->authorize(['development.view', 'development.progress'], [$this->branch]);
        $response = $this->withSession(session()->get())->post('development/goals/999999999/progress', [
            'version' => 1,
            'progress_value' => '10',
            csrf_token() => csrf_hash(),
        ]);

        self::assertSame(404, $response->response()->getStatusCode(), $response->getBody());
        self::assertStringContainsString('The requested record was not found.', $response->getBody());
        self::assertStringNotContainsString('SQL', $response->getBody());
    }

    public function testEditFormPreservesSelectedCoachOption(): void
    {
        $this->authorize(['development.view', 'development.update'], [$this->branch]);

        $form = $this->withSession(session()->get())->get('development/' . $this->plan . '/edit');

        self::assertSame(200, $form->response()->getStatusCode(), $form->getBody());
        self::assertMatchesRegularExpression(
            '/<option value="' . $this->coach . '"[^>]*selected[^>]*>/' ,
            $form->getBody()
        );
    }

    public function testValidCreateRedirectsToTheInsertedDevelopmentPlan(): void
    {
        $this->authorize(['development.view', 'development.create'], [$this->branch]);
        $title = 'TEST_MCC_VALID_CREATE_' . strtoupper(bin2hex(random_bytes(4)));

        $response = $this->withSession(session()->get())->post('development', [
            'branch_id' => $this->branch,
            'player_id' => $this->player,
            'coach_user_id' => $this->coach,
            'title' => $title,
            'status' => 'active',
            'target_date' => '2026-12-31',
            'notes' => 'Valid create regression.',
            csrf_token() => csrf_hash(),
        ]);

        self::assertSame(302, $response->response()->getStatusCode(), $response->getBody());
        $plan = $this->db->table('development_plans')->where('title', $title)->get()->getRowArray();
        self::assertIsArray($plan);
        self::assertStringEndsWith('/development/' . (int) $plan['id'], (string) $response->response()->getHeaderLine('Location'));
    }

    public function testValidEditUsesTheExistingPlanId(): void
    {
        $this->authorize(['development.view', 'development.update'], [$this->branch]);
        $title = 'TEST_MCC_VALID_EDIT_' . strtoupper(bin2hex(random_bytes(4)));

        $response = $this->withSession(session()->get())->post('development/' . $this->plan, [
            'branch_id' => $this->branch,
            'player_id' => $this->player,
            'coach_user_id' => $this->coach,
            'title' => $title,
            'status' => 'completed',
            'target_date' => '2026-12-31',
            'notes' => 'Valid edit regression.',
            csrf_token() => csrf_hash(),
        ]);

        self::assertSame(302, $response->response()->getStatusCode(), $response->getBody());
        self::assertSame($title, $this->db->table('development_plans')->where('id', $this->plan)->get()->getRow('title'));
    }

    public function testMissingPlayerReturnsFieldErrorAndPreservesInput(): void
    {
        $this->assertRejectedSelection('', $this->coach, 'player_id');
    }

    public function testMissingCoachReturnsFieldErrorAndPreservesInput(): void
    {
        $this->assertRejectedSelection($this->player, '', 'coach_user_id');
    }

    public function testInvalidPlayerReturnsFieldErrorAndPreservesInput(): void
    {
        $this->assertRejectedSelection(999999999, $this->coach, 'player_id');
    }

    public function testInvalidCoachReturnsFieldErrorAndPreservesInput(): void
    {
        $this->assertRejectedSelection($this->player, 999999999, 'coach_user_id');
    }

    public function testCrossBranchPlayerReturnsFieldErrorAndPreservesInput(): void
    {
        $this->assertRejectedSelection($this->otherBranchPlayer(), $this->coach, 'player_id');
    }

    public function testCrossBranchCoachReturnsFieldErrorAndPreservesInput(): void
    {
        $this->assertRejectedSelection($this->player, $this->otherBranchCoach(), 'coach_user_id');
    }

    private function assertRejectedSelection(int|string $player, int|string $coach, string $field): void
    {
        $this->authorize(['development.view', 'development.create'], [$this->branch]);
        $title = 'TEST_MCC_INVALID_SELECTION_' . strtoupper(bin2hex(random_bytes(4)));
        $response = $this->withSession(session()->get())->post('development', [
            'branch_id' => $this->branch,
            'player_id' => $player,
            'coach_user_id' => $coach,
            'title' => $title,
            'status' => 'active',
            'target_date' => '2026-12-31',
            csrf_token() => csrf_hash(),
        ]);

        self::assertSame(302, $response->response()->getStatusCode(), $response->getBody());
        self::assertStringEndsWith('/development/new', (string) $response->response()->getHeaderLine('Location'));
        $fieldErrors = (array) session('field_errors');
        $oldInput = (array) session('_ci_old_input');
        self::assertSame($title, $oldInput['post']['title'] ?? null);
        self::assertSame($field, array_key_first($fieldErrors));
        self::assertSame(0, $this->db->table('development_plans')->where('title', $title)->countAllResults());
    }

    public function testEditValidationFailureReturnsToTheCorrectEditForm(): void
    {
        $this->authorize(['development.view', 'development.update'], [$this->branch]);
        $title = 'TEST_MCC_EDIT_VALIDATION_' . strtoupper(bin2hex(random_bytes(4)));

        $response = $this->withSession(session()->get())->post('development/' . $this->plan, [
            'branch_id' => $this->branch,
            'player_id' => $this->player,
            'coach_user_id' => 999999999,
            'title' => $title,
            'status' => 'active',
            'target_date' => '2026-12-31',
            'notes' => 'Preserve edit input.',
            csrf_token() => csrf_hash(),
        ]);

        self::assertSame(302, $response->response()->getStatusCode(), $response->getBody());
        self::assertStringEndsWith('/development/' . $this->plan . '/edit', (string) $response->response()->getHeaderLine('Location'));
        $fieldErrors = (array) session('field_errors');
        $oldInput = (array) session('_ci_old_input');
        self::assertSame($title, $oldInput['post']['title'] ?? null);
        self::assertSame('Preserve edit input.', $oldInput['post']['notes'] ?? null);
        self::assertSame('coach_user_id', array_key_first($fieldErrors));
    }

    public function testCrossBranchDevelopmentDetailIsDenied(): void
    {
        $this->authorize(['development.view'], [$this->otherBranch]);

        $response = $this->withSession(session()->get())->get('development/' . $this->plan);

        self::assertSame(403, $response->response()->getStatusCode(), $response->getBody());
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

    private function completePlan(): void
    {
        $this->db->table('development_plans')
            ->where('id', $this->plan)
            ->update(['status' => 'completed']);
    }

    private function userId(string $email): int
    {
        return (int) $this->db->table('users')
            ->where('email', $email)
            ->get()
            ->getRow('id');
    }

    private function branchId(string $code): int
    {
        return (int) $this->db->table('branches')
            ->where('code', $code)
            ->get()
            ->getRow('id');
    }

    private function ensureCoachProfile(): void
    {
        if ($this->coach < 1) {
            return;
        }

        if ($this->db->table('coach_profiles')->where('user_id', $this->coach)->countAllResults() === 0) {
            $now = gmdate('Y-m-d H:i:s');
            $this->db->table('coach_profiles')->insert([
                'user_id' => $this->coach,
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function otherBranchPlayer(): int
    {
        return (int) $this->db->table('players')
            ->where('branch_id', $this->otherBranch)
            ->orderBy('id')
            ->get()
            ->getRow('id');
    }

    private function otherBranchCoach(): int
    {
        $rows = $this->db->table('users u')
            ->select('u.id')
            ->join('user_roles ur', 'ur.user_id = u.id')
            ->join('roles r', 'r.id = ur.role_id')
            ->join('user_branches ub', 'ub.user_id = u.id')
            ->join('coach_profiles cp', 'cp.user_id = u.id')
            ->where([
                'u.is_enabled' => 1,
                'ub.branch_id' => $this->otherBranch,
                'cp.is_active' => 1,
                'r.name' => 'Coach',
            ])
            ->orderBy('u.id')
            ->get()
            ->getResultArray();

        foreach ($rows as $row) {
            if ($this->db->table('user_branches')
                ->where(['user_id' => (int) $row['id'], 'branch_id' => $this->branch])
                ->countAllResults() === 0) {
                return (int) $row['id'];
            }
        }

        return 999999999;
    }
}
