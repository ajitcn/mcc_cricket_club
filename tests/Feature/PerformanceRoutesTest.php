<?php

declare(strict_types=1);

namespace Tests\Feature;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/** Route coverage for the existing Performance record and approval workflows. */
final class PerformanceRoutesTest extends CIUnitTestCase
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

    public function testAuthorizedPerformanceRoutesRenderScopedFormsAndDatepickerFields(): void
    {
        $this->authorize(['performance.view', 'performance.create', 'performance.update']);

        $list = $this->withSession(session()->get())->get('performance');
        $create = $this->withSession(session()->get())->get('performance/new');

        self::assertSame(200, $list->response()->getStatusCode());
        self::assertSame(200, $create->response()->getStatusCode());
        self::assertStringContainsString('csrf', strtolower($create->getBody()));
        self::assertStringContainsString('data-mcc-date-picker', $create->getBody());
        self::assertStringContainsString('name="recorded_on"', $create->getBody());
    }

    public function testPerformanceListRequiresViewPermission(): void
    {
        $this->authorize([]);

        $response = $this->withSession(session()->get())->get('performance');

        self::assertContains($response->response()->getStatusCode(), [401, 403]);
    }

    public function testCreateRouteRequiresCreatePermission(): void
    {
        $this->authorize(['performance.view']);

        $response = $this->withSession(session()->get())->get('performance/new');

        self::assertContains($response->response()->getStatusCode(), [401, 403]);
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
