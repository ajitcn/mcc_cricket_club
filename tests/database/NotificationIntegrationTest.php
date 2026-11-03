<?php

declare(strict_types=1);

namespace Tests\Database;

use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;

final class NotificationIntegrationTest extends CIUnitTestCase
{
    protected $DBGroup = 'tests';
    private object $database;
    private int $branch;
    private int $actor;
    private int $recipient;

    protected function setUp(): void
    {
        parent::setUp();
        $this->database = Database::connect('tests');
        $this->branch = (int) $this->database->table('branches')->where('code', 'TEST_MCC_VNS')->get()->getRow('id');
        $this->actor = (int) $this->database->table('users')->where('email', 'test.mcc.superadmin@example.invalid')->get()->getRow('id');
        $this->recipient = $this->actor;
        $this->database->table('notification_history')->emptyTable();
        $this->database->table('notification_recipients')->emptyTable();
        $this->database->table('notifications')->emptyTable();
        session()->set(['isLoggedIn' => true, 'accountEnabled' => true, 'user' => ['id' => $this->actor], 'permissions' => ['notification.view', 'notification.create', 'notification.read'], 'authorizedBranches' => [(string) $this->branch]]);
        \Config\Services::resetSingle('authorization');
    }

    protected function tearDown(): void
    {
        $this->database->table('notification_history')->emptyTable();
        $this->database->table('notification_recipients')->emptyTable();
        $this->database->table('notifications')->emptyTable();
        session()->destroy();
        \Config\Services::resetSingle('authorization');
        parent::tearDown();
    }

    public function testCreateListAndReadAreRecipientScoped(): void
    {
        $id = service('notification')->create(['branch_id' => $this->branch, 'title' => 'Test notice', 'body' => 'Persisted notice', 'category' => 'test', 'recipient_user_ids' => [$this->recipient]]);
        session()->set('user', ['id' => $this->recipient]);
        \Config\Services::resetSingle('authorization');
        $rows = service('notification')->list(['state' => 'unread']);
        self::assertCount(1, $rows['notifications']);
        service('notification')->markRead((int) $rows['notifications'][0]['recipient_id']);
        self::assertCount(0, service('notification')->list(['state' => 'unread'])['notifications']);
        self::assertSame(1, $this->database->table('notification_history')->where('notification_id', $id)->countAllResults());
    }
}
