<?php

declare(strict_types=1);

namespace Tests\Feature;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/** Route coverage for the existing in-app notification workflow. */
final class NotificationRoutesTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $DBGroup = 'tests';
    protected $migrate = false;

    private int $branch;
    private int $actor;

    protected function setUp(): void
    {
        parent::setUp();
        self::assertSame('mcc_cricket_club_test', strtolower((string) $this->db->getDatabase()));
        $this->branch = (int) $this->db->table('branches')->where('code', 'TEST_MCC_VNS')->get()->getRow('id');
        $this->actor = (int) $this->db->table('users')->where('email', 'test.mcc.superadmin@example.invalid')->get()->getRow('id');
    }

    protected function tearDown(): void
    {
        session()->destroy();
        \Config\Services::resetSingle('authorization');
        parent::tearDown();
    }

    public function testAuthorizedListRendersRecipientNotificationsAndCsrfForm(): void
    {
        $this->authorize(['notification.view', 'notification.create', 'notification.read']);
        $category = 'TEST_MCC_ROUTE_' . bin2hex(random_bytes(4));
        $title = 'TEST_MCC route notification ' . bin2hex(random_bytes(4));
        service('notification')->create([
            'branch_id' => $this->branch,
            'title' => $title,
            'body' => 'Synthetic route notification',
            'category' => $category,
            'recipient_user_ids' => [$this->actor],
        ]);

        $response = $this->withSession(session()->get())->get('notifications?state=unread&category=' . urlencode($category));

        self::assertSame(200, $response->response()->getStatusCode());
        self::assertStringContainsString($title, $response->getBody());
        self::assertStringContainsString('name="state"', $response->getBody());
        self::assertStringContainsString('name="category"', $response->getBody());
        self::assertStringContainsString('csrf', strtolower($response->getBody()));
    }

    public function testListRouteRequiresViewPermission(): void
    {
        $this->authorize([]);

        $response = $this->withSession(session()->get())->get('notifications');

        self::assertContains($response->response()->getStatusCode(), [401, 403]);
    }

    public function testMarkReadRedirectPreservesStateAndCategoryFilters(): void
    {
        $this->authorize(['notification.view', 'notification.create', 'notification.read']);
        $category = 'TEST_MCC_REDIRECT_' . bin2hex(random_bytes(4));
        service('notification')->create([
            'branch_id' => $this->branch,
            'title' => 'TEST_MCC redirect notification',
            'body' => 'Synthetic redirect notification',
            'category' => $category,
            'recipient_user_ids' => [$this->actor],
        ]);
        $recipientId = (int) $this->db->table('notification_recipients nr')
            ->select('nr.id AS recipient_id')
            ->join('notifications n', 'n.id = nr.notification_id')
            ->where(['nr.recipient_user_id' => $this->actor, 'n.category' => $category])
            ->get()->getRow('recipient_id');

        $response = $this->withSession(session()->get())->post('notifications/' . $recipientId . '/read', [
            'state' => 'unread',
            'category' => $category,
            csrf_token() => csrf_hash(),
        ]);

        self::assertSame(302, $response->response()->getStatusCode());
        self::assertStringContainsString('state=unread', (string) $response->response()->getHeaderLine('Location'));
        self::assertStringContainsString('category=' . urlencode($category), (string) $response->response()->getHeaderLine('Location'));
    }

    public function testForgedMarkReadReturnsToFilteredListSafely(): void
    {
        $this->authorize(['notification.view', 'notification.create', 'notification.read']);
        $category = 'TEST_MCC_FORGED_ROUTE_' . bin2hex(random_bytes(4));
        service('notification')->create([
            'branch_id' => $this->branch,
            'title' => 'TEST_MCC forged route notification',
            'body' => 'Synthetic forged route notification',
            'category' => $category,
            'recipient_user_ids' => [$this->actor],
        ]);
        $recipientId = (int) $this->db->table('notification_recipients nr')
            ->join('notifications n', 'n.id = nr.notification_id')
            ->where(['nr.recipient_user_id' => $this->actor, 'n.category' => $category])
            ->get()
            ->getRow('id');

        $response = $this->withSession(session()->get())->post(
            'notifications/' . ($recipientId + 999999) . '/read',
            [
                'state' => 'unread',
                'category' => $category,
                csrf_token() => csrf_hash(),
            ]
        );

        self::assertSame(302, $response->response()->getStatusCode());
        $location = (string) $response->response()->getHeaderLine('Location');
        self::assertStringContainsString('notifications?state=unread', $location);
        self::assertStringContainsString('category=' . urlencode($category), $location);
    }

    private function authorize(array $permissions): void
    {
        session()->set([
            'isLoggedIn' => true,
            'accountEnabled' => true,
            'user' => ['id' => $this->actor],
            'session_version' => 1,
            'permissions' => $permissions,
            'authorizedBranches' => [(string) $this->branch],
        ]);
        \Config\Services::resetSingle('authorization');
    }
}
