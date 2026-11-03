<?php

declare(strict_types=1);

namespace Tests\Feature;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/** Non-destructive HTTP coverage for the existing dashboard behavior. */
final class DashboardRoutesTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $DBGroup = 'tests';
    protected $migrate = false;

    private int $actor;
    private int $branch;

    protected function setUp(): void
    {
        parent::setUp();

        self::assertSame(
            'mcc_cricket_club_test',
            strtolower((string) $this->db->getDatabase())
        );

        $this->actor = $this->userId('test.mcc.superadmin@example.invalid');
        $this->branch = $this->branchId('TEST_MCC_VNS');

        if (min($this->actor, $this->branch) < 1) {
            self::markTestSkipped('Dashboard route fixtures are not available.');
        }
    }

    protected function tearDown(): void
    {
        session()->destroy();
        \Config\Services::resetSingle('authorization');
        parent::tearDown();
    }

    public function testAuthorizedDashboardRendersScopedCardsAndAttendanceEmptyState(): void
    {
        $this->authorize([
            'player.view',
            'lead.view',
            'admission.view',
            'session.update',
            'attendance.view',
        ], [$this->branch]);

        $response = $this->withSession(session()->get())->get('/');

        self::assertSame(200, $response->response()->getStatusCode());
        self::assertStringContainsString('Operational overview', $response->getBody());
        self::assertStringContainsString('Attendance activity', $response->getBody());
        self::assertStringContainsString('No attendance records have been captured.', $response->getBody());
        self::assertStringNotContainsString('EXCUSED', $response->getBody());
    }

    public function testDashboardHidesPermissionControlledSections(): void
    {
        $this->authorize(['player.view'], [$this->branch]);

        $response = $this->withSession(session()->get())->get('/');

        self::assertSame(200, $response->response()->getStatusCode());
        self::assertStringContainsString('Active players', $response->getBody());
        self::assertStringNotContainsString('Attendance activity', $response->getBody());
    }

    public function testDashboardRendersDirectorPanelsWithSourceBackedEmptyStates(): void
    {
        $this->authorize(['coach.view', 'session.update'], [$this->branch]);

        $response = $this->withSession(session()->get())->get('/');

        self::assertSame(200, $response->response()->getStatusCode());
        self::assertStringContainsString('Coaching-type distribution', $response->getBody());
        self::assertStringContainsString('Coach list', $response->getBody());
        self::assertStringContainsString('Age-group distribution - Not available on this dashboard', $response->getBody());
        self::assertStringContainsString('dashboard-director-grid', $response->getBody());
        self::assertStringContainsString('dashboard-filter-summary', $response->getBody());
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

    private function branchId(string $code): int
    {
        return (int) $this->db->table('branches')->where('code', $code)->get()->getRow('id');
    }

    private function userId(string $email): int
    {
        return (int) $this->db->table('users')->where('email', $email)->get()->getRow('id');
    }
}
