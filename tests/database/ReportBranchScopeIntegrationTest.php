<?php

declare(strict_types=1);

namespace Tests\Database;

use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;
use RuntimeException;

final class ReportBranchScopeIntegrationTest extends CIUnitTestCase
{
    private object $database;
    private int $branch;
    private int $otherBranch;
    private int $actor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->database = Database::connect('tests');
        self::assertSame('mcc_cricket_club_test', strtolower((string) $this->database->getDatabase()));
        $this->actor = (int) $this->database->table('users')->where('email', 'test.mcc.superadmin@example.invalid')->get()->getRow('id');
        $this->branch = (int) $this->database->table('branches')->where('code', 'TEST_MCC_VNS')->get()->getRow('id');
        $this->otherBranch = (int) $this->database->table('branches')->where('code', 'TEST_MCC_DEL')->get()->getRow('id');
    }

    protected function tearDown(): void
    {
        session()->destroy();
        \Config\Services::resetSingle('authorization');
        parent::tearDown();
    }

    public function testRequestedBranchFilterIsApplied(): void
    {
        $this->authorize([$this->branch, $this->otherBranch]);
        $result = service('report')->run('players', ['branch_id' => (string) $this->branch]);

        foreach ($result['rows'] as $row) {
            self::assertSame($this->branch, (int) $row['branch_id']);
        }
    }

    public function testUnauthorizedRequestedBranchIsRejected(): void
    {
        $branchAdmin = (int) $this->database->table('users')
            ->where('email', 'test.mcc.manager.vns@example.invalid')
            ->get()
            ->getRow('id');
        $this->authorize([$this->branch], $branchAdmin);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Forbidden');
        service('report')->run('players', ['branch_id' => (string) $this->otherBranch]);
    }

    public function testMalformedReportDateIsRejected(): void
    {
        $this->authorize([$this->branch]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid report date filter.');
        service('report')->run('players', ['from' => '31-02-2026']);
    }

    public function testReversedReportDateRangeIsRejected(): void
    {
        $this->authorize([$this->branch]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid report date filter.');
        service('report')->run('players', [
            'from' => '2026-09-30',
            'to' => '2026-09-01',
        ]);
    }

    private function authorize(array $branches, ?int $actor = null): void
    {
        session()->set([
            'isLoggedIn' => true,
            'accountEnabled' => true,
            'user' => ['id' => $actor ?? $this->actor],
            'authorizedBranches' => array_map('strval', $branches),
            'permissions' => ['report.view'],
        ]);
        \Config\Services::resetSingle('authorization');
    }
}
