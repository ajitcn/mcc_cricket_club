<?php

declare(strict_types=1);

namespace Tests\Database;

use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;

/** Insert-only coverage for branch and relationship-scoped Achievement notifications. */
final class AchievementNotificationSafeTest extends CIUnitTestCase
{
    protected $DBGroup = 'tests';

    private object $database;
    private int $branch;
    private int $actor;
    private int $player;
    private int $student;
    private int $parent;

    protected function setUp(): void
    {
        parent::setUp();
        $this->database = Database::connect('tests');
        $this->actor = (int) $this->database->table('users')->where('email', 'test.mcc.superadmin@example.invalid')->get()->getRow('id');
        $this->student = (int) $this->database->table('users')->where('email', 'test.mcc.player@example.invalid')->get()->getRow('id');
        $this->parent = (int) $this->database->table('users')->where('email', 'test.mcc.parent@example.invalid')->get()->getRow('id');
        $this->branch = (int) $this->database->table('user_branches')
            ->where('user_id', $this->student)
            ->orderBy('branch_id', 'ASC')
            ->get()
            ->getRow('branch_id');
        $this->player = (int) $this->database->table('players p')
            ->select('p.id AS player_id')
            ->where('p.branch_id', $this->branch)
            ->orderBy('p.id', 'ASC')
            ->get()
            ->getRow('player_id');

        session()->set([
            'isLoggedIn' => true,
            'accountEnabled' => true,
            'user' => ['id' => $this->actor],
            'permissions' => ['achievement.publish', 'achievement.revoke'],
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

    public function testPublishedAchievementNotifiesStudentAndGuardianWithoutSensitiveFields(): void
    {
        $now = gmdate('Y-m-d H:i:s');
        if ($this->database->table('player_user_links')->where([
            'player_id' => $this->player,
            'user_id' => $this->student,
        ])->countAllResults() === 0) {
            $this->database->table('player_user_links')->insert([
                'player_id' => $this->player,
                'user_id' => $this->student,
                'branch_id' => $this->branch,
                'created_at' => $now,
            ]);
        }
        if ($this->database->table('parent_player_links')->where([
            'parent_user_id' => $this->parent,
            'player_id' => $this->player,
        ])->countAllResults() === 0) {
            $this->database->table('parent_player_links')->insert([
                'parent_user_id' => $this->parent,
                'player_id' => $this->player,
                'relationship_type' => 'guardian',
                'created_at' => $now,
            ]);
        }

        $title = 'TEST_MCC_NOTIFICATION_' . bin2hex(random_bytes(4));
        $this->database->table('achievements')->insert([
            'branch_id' => $this->branch,
            'category' => 'test',
            'title' => $title,
            'description' => 'Synthetic notification achievement.',
            'is_active' => 1,
            'created_by' => $this->actor,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $achievement = (int) $this->database->insertID();
        $this->database->table('achievement_awards')->insert([
            'achievement_id' => $achievement,
            'branch_id' => $this->branch,
            'recipient_type' => 'player',
            'player_id' => $this->player,
            'coach_id' => null,
            'status' => 'published',
            'nomination_note' => 'PRIVATE_TEST_NOTE',
            'created_by' => $this->actor,
            'updated_by' => $this->actor,
            'correction_reason' => null,
            'created_at' => $now,
            'updated_at' => $now,
            'version' => 1,
            'published_by' => $this->actor,
            'published_at' => $now,
        ]);
        $award = (int) $this->database->insertID();

        $created = service('notification')->notifyAchievementAward((int) $award, 'published', $this->database);
        self::assertGreaterThan(0, $created);

        $rows = $this->database->table('notification_recipients nr')
            ->select('nr.recipient_user_id, n.title, n.body, n.source_type, n.source_id')
            ->join('notifications n', 'n.id = nr.notification_id')
            ->where('n.source_id', (string) $award)
        ->get()
        ->getResultArray();
        self::assertCount(2, $rows);
        $recipientIds = array_map(
            static fn (array $row): int => (int) $row['recipient_user_id'],
            $rows,
        );
        sort($recipientIds);
        $expectedRecipients = [$this->student, $this->parent];
        sort($expectedRecipients);
        self::assertSame($expectedRecipients, $recipientIds);
        self::assertSame('achievement_award_published', $rows[0]['source_type']);
        self::assertSame((string) $award, $rows[0]['source_id']);
        self::assertStringContainsString($title, $rows[0]['body']);
        self::assertStringNotContainsString('PRIVATE_TEST_NOTE', $rows[0]['body']);
        $notificationId = (int) $this->database->table('notifications')
            ->where('source_id', (string) $award)
            ->get()
            ->getRow('id');
        self::assertNull($this->database->table('notification_recipients')
            ->where('notification_id', $notificationId)
            ->get()
            ->getRow('read_at'));
    }
}
