<?php

declare(strict_types=1);

namespace Tests\Database;

use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

final class MarketingDashboardIntegrationTest extends CIUnitTestCase
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
        $this->actor = (int) $this->database->table('users')
            ->where('email', 'test.mcc.superadmin@example.invalid')
            ->get()
            ->getRow('id');
        $this->branch = (int) $this->database->table('branches')
            ->where('code', 'TEST_MCC_VNS')
            ->get()
            ->getRow('id');
        $this->otherBranch = (int) $this->database->table('branches')
            ->where('code', 'TEST_MCC_DEL')
            ->get()
            ->getRow('id');
        self::assertGreaterThan(0, $this->actor);
        self::assertGreaterThan(0, $this->branch);
        self::assertGreaterThan(0, $this->otherBranch);
    }

    protected function tearDown(): void
    {
        session()->destroy();
        \Config\Services::resetSingle('authorization');
        parent::tearDown();
    }

    public function testKpisAndSourceSummaryMatchPersistedBranchScopedData(): void
    {
        $this->asMarketingUser([$this->branch]);
        $result = service('marketing')->dashboard(['branch_id' => $this->branch]);
        $query = $this->database->table('leads')->where('branch_id', $this->branch);

        self::assertSame((int) $query->countAllResults(), $result['kpis']['leads']);
        self::assertNotEmpty($result['lead_source_performance']);

        $first = $result['lead_source_performance'][0];
        $sourceCount = (int) $this->database->table('leads')
            ->where(['branch_id' => $this->branch, 'source' => $first['label']])
            ->countAllResults();
        self::assertSame($sourceCount, (int) $first['total']);
    }

    public function testUnauthorizedBranchFilterIsRejectedServerSide(): void
    {
        $this->asMarketingUser([$this->branch]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Forbidden');
        service('marketing')->dashboard(['branch_id' => $this->otherBranch]);
    }

    public function testMissingPermissionIsRejected(): void
    {
        session()->set([
            'isLoggedIn' => true,
            'accountEnabled' => true,
            'user' => ['id' => $this->actor],
            'authorizedBranches' => [(string) $this->branch],
            'permissions' => [],
        ]);
        \Config\Services::resetSingle('authorization');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Forbidden');
        service('marketing')->dashboard();
    }

    public function testFilterOptionsAndLeadFiltersUsePersistedValues(): void
    {
        $this->asMarketingUser([$this->branch]);
        $lead = $this->database->table('leads')
            ->where('branch_id', $this->branch)
            ->orderBy('id')
            ->get()
            ->getRowArray();
        $admission = $this->database->table('admissions')
            ->where('branch_id', $this->branch)
            ->orderBy('id')
            ->get()
            ->getRowArray();

        self::assertIsArray($lead);
        self::assertIsArray($admission);

        $result = service('marketing')->dashboard();

        self::assertContains($lead['source'], $result['filter_options']['sources']);
        self::assertContains($lead['status'], $result['filter_options']['lead_statuses']);
        self::assertContains($admission['status'], $result['filter_options']['admission_statuses']);
        self::assertSame(
            (int) $this->database->table('leads')
                ->where(['branch_id' => $this->branch, 'source' => $lead['source']])
                ->countAllResults(),
            service('marketing')->dashboard(['source' => $lead['source']])['kpis']['leads']
        );
        self::assertSame(
            (int) $this->database->table('leads')
                ->where(['branch_id' => $this->branch, 'status' => $lead['status']])
                ->countAllResults(),
            service('marketing')->dashboard(['lead_status' => $lead['status']])['kpis']['leads']
        );
        self::assertSame(
            (int) $this->database->table('admissions')
                ->where(['branch_id' => $this->branch, 'status' => $admission['status']])
                ->countAllResults(),
            service('marketing')->dashboard(['admission_status' => $admission['status']])['kpis']['admissions']
        );
        self::assertSame(
            (int) $this->database->table('leads')
                ->where('branch_id', $this->branch)
                ->groupStart()
                ->like('full_name', $lead['full_name'])
                ->orLike('email', $lead['full_name'])
                ->orLike('phone', $lead['full_name'])
                ->groupEnd()
                ->countAllResults(),
            service('marketing')->dashboard(['q' => $lead['full_name']])['kpis']['leads']
        );
    }

    public function testDatePresetsCustomRangeAndClearFilters(): void
    {
        $this->asMarketingUser([$this->branch]);
        $today = new DateTimeImmutable('today', new DateTimeZone('Asia/Kolkata'));

        self::assertSame(
            [$today->format('Y-m-d'), $today->format('Y-m-d')],
            [
                service('marketing')->dashboard(['date_range' => 'today'])['filters']['from'],
                service('marketing')->dashboard(['date_range' => 'today'])['filters']['to'],
            ]
        );
        self::assertSame(
            $today->modify('first day of last month')->format('Y-m-d'),
            service('marketing')->dashboard(['date_range' => 'last_month'])['filters']['from']
        );
        self::assertSame(
            ['custom', '2026-01-01', '2026-01-31'],
            array_values(array_intersect_key(
                service('marketing')->dashboard([
                    'date_range' => 'custom',
                    'from' => '2026-01-01',
                    'to' => '2026-01-31',
                ])['filters'],
                array_flip(['date_range', 'from', 'to'])
            ))
        );

        $cleared = service('marketing')->dashboard([])['filters'];
        self::assertSame('all_time', $cleared['date_range']);
        self::assertSame('', $cleared['source']);
        self::assertSame('', $cleared['lead_status']);
        self::assertSame('', $cleared['admission_status']);
        self::assertSame('', $cleared['q']);
    }

    public function testCustomDateRangeRejectsInvalidOrder(): void
    {
        $this->asMarketingUser([$this->branch]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid date filter.');
        service('marketing')->dashboard([
            'date_range' => 'custom',
            'from' => '2026-02-01',
            'to' => '2026-01-31',
        ]);
    }

    public function testLocalCalendarDatesIncludeUtcBoundaryRecords(): void
    {
        $this->asMarketingUser([$this->branch]);
        $source = 'TEST_MKT_TZ_' . str_replace('.', '', uniqid('', true));

        foreach ([
            '2026-01-14 18:29:59',
            '2026-01-14 18:30:00',
            '2026-01-15 18:30:00',
            '2026-01-16 18:29:59',
            '2026-01-16 18:30:00',
        ] as $createdAt) {
            $this->insertMarketingLead($source, $createdAt, $this->branch);
        }

        self::assertSame(
            1,
            service('marketing')->dashboard([
                'source' => $source,
                'date_range' => 'custom',
                'from' => '2026-01-15',
                'to' => '2026-01-15',
            ])['kpis']['leads']
        );
        self::assertSame(
            3,
            service('marketing')->dashboard([
                'source' => $source,
                'date_range' => 'custom',
                'from' => '2026-01-15',
                'to' => '2026-01-16',
            ])['kpis']['leads']
        );
    }

    public function testLocalCalendarRangeRemainsBranchScoped(): void
    {
        $this->asMarketingUser([$this->branch]);
        $source = 'TEST_MKT_BRANCH_TZ_' . str_replace('.', '', uniqid('', true));
        $createdAt = '2026-01-14 18:30:00';

        $this->insertMarketingLead($source, $createdAt, $this->branch);
        $this->insertMarketingLead($source, $createdAt, $this->otherBranch);

        self::assertSame(
            1,
            service('marketing')->dashboard([
                'source' => $source,
                'date_range' => 'custom',
                'from' => '2026-01-15',
                'to' => '2026-01-15',
            ])['kpis']['leads']
        );
    }

    public function testMalformedCustomDateIsRejected(): void
    {
        $this->asMarketingUser([$this->branch]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid date filter.');
        service('marketing')->dashboard([
            'date_range' => 'custom',
            'from' => '2026-02-30',
            'to' => '2026-03-01',
        ]);
    }

    private function insertMarketingLead(string $source, string $createdAt, int $branch): void
    {
        $this->database->table('leads')->insert([
            'branch_id' => $branch,
            'owner_user_id' => $this->actor,
            'source' => $source,
            'full_name' => $source . '_' . $createdAt,
            'email' => null,
            'phone' => null,
            'date_of_birth' => null,
            'status' => 'created',
            'lost_reason' => null,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }

    private function asMarketingUser(array $branches): void
    {
        session()->set([
            'isLoggedIn' => true,
            'accountEnabled' => true,
            'user' => ['id' => $this->actor],
            'session_version' => 1,
            'authorizedBranches' => array_map('strval', $branches),
            'permissions' => ['marketing.view'],
        ]);
        \Config\Services::resetSingle('authorization');
    }
}
