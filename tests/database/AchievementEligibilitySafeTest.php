<?php

declare(strict_types=1);

namespace Tests\Database;

use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;

/** Insert-only coverage for draft-only, branch-scoped eligibility evaluation. */
final class AchievementEligibilitySafeTest extends CIUnitTestCase
{
    protected $DBGroup = 'tests';

    private object $database;
    private int $actor;
    private int $branch;
    private int $player;

    protected function setUp(): void
    {
        parent::setUp();
        $this->database = Database::connect('tests');
        $this->actor = (int) $this->database->table('users')
            ->where('email', 'test.mcc.superadmin@example.invalid')
            ->get()
            ->getRow('id');
        $this->branch = (int) $this->database->table('user_branches')
            ->where('user_id', $this->actor)
            ->orderBy('branch_id', 'ASC')
            ->get()
            ->getRow('branch_id');
        $this->player = (int) $this->database->table('players')
            ->where(['branch_id' => $this->branch, 'status' => 'active'])
            ->orderBy('id', 'ASC')
            ->get()
            ->getRow('id');

        session()->set([
            'isLoggedIn' => true,
            'accountEnabled' => true,
            'user' => ['id' => $this->actor],
            'permissions' => [
                'achievement.eligibility.evaluate',
                'achievement.view',
            ],
            'authorizedBranches' => [(string) $this->branch],
        ]);
        \Config\Services::resetSingle('authorization');
    }

    protected function tearDown(): void
    {
        session()->destroy();
        \Config\Services::resetSingle('authorization');
        parent::tearDown();
    }

    public function testPerformanceEligibilityCreatesOnlyOneAuditedDraftAndSupportsRetry(): void
    {
        $now = gmdate('Y-m-d H:i:s');
        $title = 'TEST_MCC_ELIGIBILITY_' . bin2hex(random_bytes(4));
        $this->database->table('achievements')->insert([
            'branch_id' => $this->branch,
            'category' => 'performance',
            'title' => $title,
            'description' => 'Synthetic eligibility achievement.',
            'is_active' => 1,
            'created_by' => $this->actor,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $achievementId = (int) $this->database->insertID();

        $this->database->table('performance_records')->insert([
            'branch_id' => $this->branch,
            'player_id' => $this->player,
            'session_id' => null,
            'record_type' => 'batting',
            'recorded_on' => gmdate('Y-m-d'),
            'runs' => 50,
            'balls_faced' => 30,
            'dismissals' => 0,
            'fours' => 5,
            'sixes' => 1,
            'wickets' => 0,
            'runs_conceded' => 0,
            'legal_balls' => 0,
            'catches' => 0,
            'stumpings' => 0,
            'run_outs' => 0,
            'notes' => 'Private source note.',
            'recorded_by' => $this->actor,
            'approved' => 1,
            'approved_by' => $this->actor,
            'approved_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
        $sourceId = (int) $this->database->insertID();

        $result = service('achievementEligibility')->evaluate([
            'achievement_id' => $achievementId,
            'branch_id' => $this->branch,
            'source_event_type' => 'performance',
            'source_event_id' => $sourceId,
        ]);

        self::assertSame('succeeded', $result['status']);
        self::assertIsInt($result['award_id']);
        $award = $this->database->table('achievement_awards')
            ->where('id', $result['award_id'])
            ->get()
            ->getRowArray();
        self::assertIsArray($award);
        self::assertSame('draft', $award['status']);
        self::assertNull($award['nomination_note']);
        self::assertSame('performance.v1', $award['eligibility_rule_version']);
        self::assertSame('performance:' . $sourceId, $award['eligibility_source_event']);
        self::assertNull($award['approved_by']);
        self::assertNull($award['published_by']);

        $duplicate = service('achievementEligibility')->evaluate([
            'achievement_id' => $achievementId,
            'branch_id' => $this->branch,
            'source_event_type' => 'performance',
            'source_event_id' => $sourceId,
        ]);
        self::assertSame('duplicate', $duplicate['status']);
        self::assertSame(1, $this->database->table('achievement_awards')
            ->where(['achievement_id' => $achievementId, 'status' => 'draft'])
            ->countAllResults());
        self::assertSame(1, $this->database->table('audit_logs')
            ->where([
                'action' => 'achievement_eligibility_draft_created',
                'entity_id' => (string) $result['award_id'],
            ])
            ->countAllResults());
    }

    public function testFailedEvaluationLeavesNoAwardAndRetryIsAuditable(): void
    {
        $now = gmdate('Y-m-d H:i:s');
        $title = 'TEST_MCC_ELIGIBILITY_FAIL_' . bin2hex(random_bytes(4));
        $this->database->table('achievements')->insert([
            'branch_id' => $this->branch,
            'category' => 'performance',
            'title' => $title,
            'description' => 'Synthetic retry achievement.',
            'is_active' => 1,
            'created_by' => $this->actor,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $achievementId = (int) $this->database->insertID();
        $this->database->table('performance_records')->insert([
            'branch_id' => $this->branch,
            'player_id' => $this->player,
            'session_id' => null,
            'record_type' => 'batting',
            'recorded_on' => gmdate('Y-m-d'),
            'runs' => 10,
            'recorded_by' => $this->actor,
            'approved' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
        $sourceId = (int) $this->database->insertID();

        $failed = service('achievementEligibility')->evaluate([
            'achievement_id' => $achievementId,
            'branch_id' => $this->branch,
            'source_event_type' => 'performance',
            'source_event_id' => $sourceId,
        ]);
        self::assertSame('failed', $failed['status']);
        self::assertSame(0, $this->database->table('achievement_awards')
            ->where('achievement_id', $achievementId)
            ->countAllResults());

        $this->database->table('performance_records')
            ->where('id', $sourceId)
            ->update(['approved' => 1, 'approved_by' => $this->actor, 'approved_at' => $now, 'runs' => 50]);
        $retried = service('achievementEligibility')->retry((int) $failed['evaluation_id']);

        self::assertSame('succeeded', $retried['status']);
        self::assertSame(1, $this->database->table('achievement_eligibility_evaluations')
            ->where(['retry_of_id' => $failed['evaluation_id'], 'status' => 'succeeded'])
            ->countAllResults());
        self::assertSame(1, $this->database->table('audit_logs')
            ->where([
                'action' => 'achievement_eligibility_failed',
                'entity_id' => (string) $failed['evaluation_id'],
            ])
            ->countAllResults());
    }
}
