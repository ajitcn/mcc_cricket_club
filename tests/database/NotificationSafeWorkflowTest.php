<?php

declare(strict_types=1);

namespace Tests\Database;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Database;
use RuntimeException;

/** Non-destructive notification coverage using unique persistent fixtures. */
final class NotificationSafeWorkflowTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $DBGroup = 'tests';
    protected $migrate = false;

    private object $database;
    private int $branch;
    private int $otherBranch;
    private int $actor;
    private int $otherUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->database = Database::connect('default');
        self::assertSame('mcc_cricket_club_test', strtolower((string) $this->database->getDatabase()));
        $this->branch = (int) $this->database->table('branches')->where('code', 'TEST_MCC_VNS')->get()->getRow('id');
        $this->otherBranch = (int) $this->database->table('branches')->where('code', 'TEST_MCC_DEL')->get()->getRow('id');
        $this->actor = (int) $this->database->table('users')->where('email', 'test.mcc.superadmin@example.invalid')->get()->getRow('id');
        $this->otherUser = (int) $this->database->table('users u')
            ->join('user_branches ub', 'ub.user_id = u.id')
            ->where('ub.branch_id', $this->branch)
            ->where('u.id !=', $this->actor)
            ->get()->getRow('id');
        $this->authorize(['notification.view', 'notification.create', 'notification.read'], [$this->branch, $this->otherBranch]);
    }

    protected function tearDown(): void
    {
        session()->destroy();
        \Config\Services::resetSingle('authorization');
        parent::tearDown();
    }

    public function testNotificationIsRecipientScopedAndReadStatePreservesHistory(): void
    {
        $category = 'TEST_MCC_SAFE_' . bin2hex(random_bytes(4));
        $id = service('notification')->create([
            'branch_id' => $this->branch,
            'title' => 'TEST_MCC recipient notification',
            'body' => 'Synthetic recipient-scoped notification',
            'category' => $category,
            'recipient_user_ids' => [$this->actor],
        ]);
        service('notification')->create([
            'branch_id' => $this->branch,
            'title' => 'TEST_MCC other recipient notification',
            'body' => 'Synthetic other-recipient notification',
            'category' => $category,
            'recipient_user_ids' => [$this->otherUser],
        ]);

        $rows = service('notification')->list(['state' => 'unread', 'category' => $category]);
        self::assertCount(1, $rows['notifications']);
        self::assertSame('TEST_MCC recipient notification', $rows['notifications'][0]['title']);
        service('notification')->markRead((int) $rows['notifications'][0]['recipient_id']);

        self::assertCount(0, service('notification')->list(['state' => 'unread', 'category' => $category])['notifications']);
        self::assertCount(1, service('notification')->list(['state' => 'read', 'category' => $category])['notifications']);
        self::assertSame(1, $this->database->table('notification_history')->where('notification_id', $id)->countAllResults());
    }

    public function testForgedRecipientReadIsRejected(): void
    {
        $category = 'TEST_MCC_FORGED_' . bin2hex(random_bytes(4));
        service('notification')->create([
            'branch_id' => $this->branch,
            'title' => 'TEST_MCC forged recipient notification',
            'body' => 'Synthetic forged-recipient notification',
            'category' => $category,
            'recipient_user_ids' => [$this->otherUser],
        ]);
        $recipientId = (int) $this->database->table('notification_recipients nr')
            ->join('notifications n', 'n.id = nr.notification_id')
            ->where(['nr.recipient_user_id' => $this->otherUser, 'n.category' => $category])
            ->get()->getRow('id');

        try {
            service('notification')->markRead($recipientId);
            self::fail('A forged recipient read was accepted.');
        } catch (RuntimeException $exception) {
            self::assertSame('Not found.', $exception->getMessage());
        }
    }

    public function testCreationRejectsUnauthorizedBranchWithoutWritingRows(): void
    {
        $this->authorize(['notification.create'], [$this->branch]);
        $category = 'TEST_MCC_BRANCH_' . bin2hex(random_bytes(4));

        try {
            service('notification')->create([
                'branch_id' => $this->otherBranch,
                'title' => 'TEST_MCC unauthorized branch notification',
                'body' => 'Synthetic unauthorized branch notification',
                'category' => $category,
                'recipient_user_ids' => [$this->actor],
            ]);
            self::fail('An unauthorized branch notification was created.');
        } catch (RuntimeException $exception) {
            self::assertSame('Forbidden', $exception->getMessage());
        }

        self::assertSame(0, $this->database->table('notifications')->where('category', $category)->countAllResults());
    }

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
