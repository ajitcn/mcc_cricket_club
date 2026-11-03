<?php

declare(strict_types=1);

namespace Tests\Feature;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/** HTTP coverage for existing branch-scoped report behavior. */
final class ReportBrowserRoutesTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $DBGroup = 'tests';
    protected $migrate = false;

    private int $actor;
    private int $branch;
    private int $otherBranch;

    protected function setUp(): void
    {
        parent::setUp();

        self::assertSame(
            'mcc_cricket_club_test',
            strtolower((string) $this->db->getDatabase())
        );

        $this->actor = $this->userId('test.mcc.superadmin@example.invalid');
        $this->branch = $this->branchId('TEST_MCC_VNS');
        $this->otherBranch = $this->branchId('TEST_MCC_DEL');

        if (min($this->actor, $this->branch, $this->otherBranch) < 1) {
            self::markTestSkipped('Report route fixtures are not available.');
        }
    }

    protected function tearDown(): void
    {
        session()->destroy();
        \Config\Services::resetSingle('authorization');
        parent::tearDown();
    }

    public function testAuthorizedReportRouteRendersScopedReportAndCsvLink(): void
    {
        $this->authorize([$this->branch]);

        $response = $this->withSession(session()->get())->get(
            'reports/players?branch_id=' . $this->branch . '&from=2026-09-01&to=2026-09-30'
        );

        self::assertSame(200, $response->response()->getStatusCode());
        self::assertStringContainsString('Players report', $response->getBody());
        self::assertStringContainsString('branch_id=' . $this->branch, $response->getBody());
        self::assertStringContainsString('from=2026-09-01', $response->getBody());
        self::assertStringContainsString('to=2026-09-30', $response->getBody());
    }

    public function testUnauthorizedRequestedBranchReturnsSafeForbiddenResponse(): void
    {
        $this->authorize([$this->branch]);

        $response = $this->withSession(session()->get())->get(
            'reports/players?branch_id=' . $this->otherBranch
        );

        self::assertSame(403, $response->response()->getStatusCode());
        self::assertSame(
            'You are not authorized for this record.',
            trim(strip_tags($response->getBody()))
        );
    }

    public function testInvalidReportDatesReturnToTheReportPageWithAnActionableError(): void
    {
        $this->authorize([$this->branch]);

        $response = $this->withSession(session()->get())->get(
            'reports/players?branch_id=' . $this->branch . '&from=31-02-2026'
        );

        self::assertSame(200, $response->response()->getStatusCode());
        self::assertStringContainsString(
            'Enter valid report dates with From on or before To.',
            $response->getBody()
        );
    }

    /** @param list<int> $branches */
    private function authorize(array $branches): void
    {
        session()->set([
            'isLoggedIn' => true,
            'accountEnabled' => true,
            'user' => ['id' => $this->actor],
            'session_version' => 1,
            'permissions' => ['report.view'],
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
