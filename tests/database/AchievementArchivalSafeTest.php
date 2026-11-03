<?php

declare(strict_types=1);

namespace Tests\Database;

use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;

/** Insert-only fixture coverage for non-destructive Achievement archival. */
final class AchievementArchivalSafeTest extends CIUnitTestCase
{
    protected $DBGroup = 'tests';

    private object $database;
    private int $branch;
    private int $actor;
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
                'achievement.view',
                'achievement.archive',
                'achievement.restore',
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

    public function testArchiveHidesOperationallyRestoresWithAuditAndNeverDeletes(): void
    {
        $now = gmdate('Y-m-d H:i:s');
        $title = 'TEST_MCC_ARCHIVE_' . bin2hex(random_bytes(4));
        $this->database->table('achievements')->insert([
            'branch_id' => $this->branch,
            'category' => 'test',
            'title' => $title,
            'description' => 'Synthetic archival achievement.',
            'is_active' => 1,
            'created_by' => $this->actor,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $achievementId = (int) $this->database->insertID();
        $this->database->table('achievement_awards')->insert([
            'achievement_id' => $achievementId,
            'branch_id' => $this->branch,
            'recipient_type' => 'player',
            'player_id' => $this->player,
            'coach_id' => null,
            'status' => 'published',
            'nomination_note' => 'PRIVATE_ARCHIVE_NOTE',
            'created_by' => $this->actor,
            'updated_by' => $this->actor,
            'correction_reason' => null,
            'created_at' => $now,
            'updated_at' => $now,
            'version' => 1,
            'published_by' => $this->actor,
            'published_at' => $now,
            'is_archived' => 0,
        ]);
        $awardId = (int) $this->database->insertID();
        $fixture = $this->database->table('achievement_awards')->where('id', $awardId)->get()->getRowArray();
        self::assertSame('published', $fixture['status']);
        self::assertSame(0, (int) $fixture['is_archived']);

        service('achievement')->archiveAward($awardId, [
            'version' => 1,
            'reason' => 'Archive after operational completion.',
        ]);

        self::assertSame(0, $this->database->table('achievement_awards')
            ->where(['id' => $awardId, 'is_archived' => 0])
            ->countAllResults());
        self::assertSame(0, $this->countAwardIn(service('achievement')->awardList(), $awardId));
        self::assertSame(1, $this->countAwardIn(service('achievement')->awardList(true), $awardId));
        self::assertSame(1, $this->database->table('achievement_awards')
            ->where('id', $awardId)
            ->countAllResults());
        self::assertSame(1, $this->database->table('audit_logs')
            ->where(['entity_type' => 'achievement_award', 'entity_id' => (string) $awardId, 'action' => 'achievement_award_archived'])
            ->countAllResults());

        service('achievement')->restoreAward($awardId, [
            'version' => 2,
            'reason' => 'Restore for authorized historical review.',
        ]);

        self::assertSame(0, (int) $this->database->table('achievement_awards')
            ->where('id', $awardId)
            ->get()
            ->getRow('is_archived'));
        self::assertSame(1, $this->countAwardIn(service('achievement')->awardList(), $awardId));
        self::assertSame(1, $this->database->table('audit_logs')
            ->where(['entity_type' => 'achievement_award', 'entity_id' => (string) $awardId, 'action' => 'achievement_award_restored'])
            ->countAllResults());
    }

    private function countAwardIn(array $awards, int $awardId): int
    {
        return count(array_filter(
            $awards,
            static fn (array $award): bool => (int) $award['id'] === $awardId,
        ));
    }
}
