<?php

declare(strict_types=1);

namespace Tests\Database;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

final class BranchOptionsVisibilityTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $DBGroup = 'tests';
    protected $migrate = false;

    protected function tearDown(): void
    {
        session()->destroy();
        \Config\Services::resetSingle('authorization');
        parent::tearDown();
    }

    public function testSuperAdminReceivesEveryAuthorizedActiveBranch(): void
    {
        $userId = $this->userId('test.mcc.superadmin@example.invalid');
        $this->authorize($userId, ['rbac.manage', 'achievement.create']);

        $branches = service('branchContext')->authorizedBranches(true);
        $codes = array_column($branches, 'code');
        $expectedCodes = array_column(
            $this->db->table('branches')
                ->where('is_active', 1)
                ->orderBy('name', 'ASC')
                ->get()
                ->getResultArray(),
            'code',
        );

        self::assertSame($expectedCodes, $codes);
        self::assertSame($codes, array_values(array_unique($codes)));
    }

    public function testBranchAdminReceivesOnlyAssignedBranches(): void
    {
        $userId = $this->userId('test.mcc.manager.vns@example.invalid');
        $this->authorize($userId, ['rbac.manage']);

        $branches = service('admin')->managedBranches();

        self::assertNotEmpty($branches);
        self::assertSame(['TEST_MCC_VNS'], array_column($branches, 'code'));
    }

    public function testSuperAdminCanAccessEachAssignedActiveBranchAndNotInactiveBranches(): void
    {
        $userId = $this->userId('test.mcc.superadmin@example.invalid');
        $this->authorize($userId, ['rbac.manage']);

        $activeBranches = $this->db->table('branches')
            ->where('is_active', 1)
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();

        foreach ($activeBranches as $branch) {
            $branchId = (int) $branch['id'];
            self::assertTrue(service('branchContext')->isCurrentlyAuthorizedBranch($branchId, true));
            self::assertSame($branchId, (int) service('admin')->branch($branchId)['id']);
        }

        foreach ($this->db->table('branches')->where('is_active', 0)->get()->getResultArray() as $branch) {
            self::assertFalse(service('branchContext')->isCurrentlyAuthorizedBranch((int) $branch['id'], true));
        }
    }

    public function testCrossBranchAccessIsDeniedServerSide(): void
    {
        $userId = $this->userId('test.mcc.manager.vns@example.invalid');
        $otherBranch = (int) $this->db->table('branches')
            ->where('code', 'TEST_MCC_DEL')
            ->get()
            ->getRow('id');
        $this->authorize($userId, ['rbac.manage']);

        $this->expectExceptionMessage('Forbidden');
        service('admin')->branch($otherBranch);
    }

    public function testEmptyAuthorizedBranchStateReturnsNoOptions(): void
    {
        $this->authorize(999999, ['rbac.manage']);

        self::assertSame([], service('branchContext')->authorizedBranches(true));
        self::assertSame([], service('admin')->managedBranches());
    }

    public function testBranchSelectorsRenderNamesForEverySuperAdminBranch(): void
    {
        $userId = $this->userId('test.mcc.superadmin@example.invalid');
        $this->authorize($userId, ['rbac.manage', 'achievement.create', 'achievement.view']);

        $branchPage = $this->withSession(session()->get())->get('admin/branches');
        $achievementPage = $this->withSession(session()->get())->get('achievements/new');

        self::assertSame(200, $branchPage->response()->getStatusCode());
        self::assertSame(200, $achievementPage->response()->getStatusCode());
        self::assertStringContainsString('MCC Varanasi', $branchPage->getBody());
        self::assertStringContainsString('MCC Delhi', $branchPage->getBody());
        self::assertStringContainsString('MCC Varanasi', $achievementPage->getBody());
        self::assertStringContainsString('MCC Delhi', $achievementPage->getBody());
    }

    private function userId(string $email): int
    {
        return (int) $this->db->table('users')->where('email', $email)->get()->getRow('id');
    }

    /** @param list<string> $permissions */
    private function authorize(int $userId, array $permissions): void
    {
        $branchIds = array_column(
            $this->db->table('user_branches')
                ->select('branch_id')
                ->where('user_id', $userId)
                ->get()
                ->getResultArray(),
            'branch_id',
        );

        session()->set([
            'isLoggedIn' => true,
            'accountEnabled' => true,
            'user' => ['id' => $userId],
            'session_version' => 1,
            'permissions' => $permissions,
            'authorizedBranches' => array_map('strval', $branchIds),
        ]);
        \Config\Services::resetSingle('authorization');
    }
}
