<?php

declare(strict_types=1);

namespace Tests\Database;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

final class AdmissionStatusSummaryIntegrationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $DBGroup = 'tests';
    protected $migrate = false;

    private int $authorizedBranch;
    private int $unauthorizedBranch;

    protected function setUp(): void
    {
        parent::setUp();

        $authorized = $this->db->table('branches')->where('code', 'TEST_MCC_VNS')->get()->getRowArray();
        $unauthorized = $this->db->table('branches')->where('code', 'TEST_MCC_DEL')->get()->getRowArray();
        self::assertIsArray($authorized);
        self::assertIsArray($unauthorized);

        $actor = $this->db->table('users')
            ->where('email', 'test.mcc.manager.vns@example.invalid')
            ->get()
            ->getRowArray();
        self::assertIsArray($actor);

        $this->authorizedBranch = (int) $authorized['id'];
        $this->unauthorizedBranch = (int) $unauthorized['id'];

        session()->set([
            'isLoggedIn' => true,
            'accountEnabled' => true,
            'user' => ['id' => (int) $actor['id']],
            'session_version' => 1,
            'permissions' => ['admission.view'],
            'authorizedBranches' => [(string) $this->authorizedBranch],
        ]);
    }

    protected function tearDown(): void
    {
        $this->db->table('admissions')
            ->like('applicant_full_name', 'SUMMARY-')
            ->delete();

        parent::tearDown();
    }

    public function testSummaryCountsStatusesAndExcludesUnauthorizedBranches(): void
    {
        $service = service('admission', false);
        $prefix = 'SUMMARY-AUTH-' . bin2hex(random_bytes(3));
        $authorizedRows = $this->seedSummaryRows($this->authorizedBranch, $prefix);
        $this->seedSummaryRows($this->unauthorizedBranch, 'SUMMARY-OTHER-' . bin2hex(random_bytes(3)));

        $summary = $service->statusSummaryForAuthorizedBranches(['q' => $prefix]);
        $counts = array_column($summary, 'count', 'key');

        self::assertSame(3, $counts['intake']);
        self::assertSame(1, $counts['approved']);
        self::assertSame(1, $counts['completed']);
        self::assertSame(1, $counts['converted']);
        self::assertSame(2, $counts['rejected-closed']);

        $authorizedIds = array_column($authorizedRows, 'id');
        self::assertNotEmpty($authorizedIds);
    }

    public function testSummaryRespectsSearchAndBranchFiltersAndReturnsZeroSafely(): void
    {
        $service = service('admission', false);
        $prefix = 'SUMMARY-FILTER-' . bin2hex(random_bytes(3));
        $this->seedSummaryRows($this->authorizedBranch, $prefix);

        $filtered = $service->statusSummaryForAuthorizedBranches([
            'q' => $prefix,
            'branch_id' => (string) $this->authorizedBranch,
        ]);
        $filteredCounts = array_column($filtered, 'count', 'key');

        self::assertSame(3, $filteredCounts['intake']);
        self::assertSame(1, $filteredCounts['approved']);

        $zero = $service->statusSummaryForAuthorizedBranches(['q' => 'SUMMARY-NOT-FOUND-' . bin2hex(random_bytes(3))]);
        self::assertCount(5, $zero);
        self::assertSame([0, 0, 0, 0, 0], array_column($zero, 'count'));
    }

    public function testStatusCardLinksApplyTheFilterWithoutDroppingExistingListFilters(): void
    {
        $prefix = 'SUMMARY-LINK-' . bin2hex(random_bytes(3));
        $this->seedSummaryRows($this->authorizedBranch, $prefix);

        $response = $this->withSession(session()->get())->get(
            'admissions?q=' . urlencode($prefix) . '&source=direct&page=2',
        );

        self::assertSame(200, $response->response()->getStatusCode());
        self::assertStringContainsString(
            'href="' . htmlspecialchars(
                site_url('admissions?q=' . urlencode($prefix) . '&source=direct&status=INTAKE'),
                ENT_QUOTES,
                'UTF-8',
            ) . '"',
            $response->getBody(),
        );
        self::assertStringContainsString(
            'href="' . htmlspecialchars(
                site_url('admissions?q=' . urlencode($prefix) . '&source=direct&status=COMPLETED'),
                ENT_QUOTES,
                'UTF-8',
            ) . '"',
            $response->getBody(),
        );
        self::assertStringNotContainsString('&page=2&status=', $response->getBody());
    }

    /** @return list<array{id: int}> */
    private function seedSummaryRows(int $branch, string $prefix): array
    {
        $now = date('Y-m-d H:i:s');
        $rows = [];

        foreach (['INTAKE', 'IN_REVIEW', 'DOCUMENTS_PENDING', 'APPROVED', 'COMPLETED', 'CONVERTED', 'REJECTED', 'WITHDRAWN'] as $index => $status) {
            $this->db->table('admissions')->insert([
                'applicant_full_name' => $prefix . '-' . $status,
                'branch_id' => $branch,
                'status' => $status,
                'admission_number' => $prefix . '-' . $index . '-' . bin2hex(random_bytes(3)),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $rows[] = ['id' => (int) $this->db->insertID()];
        }

        return $rows;
    }
}
