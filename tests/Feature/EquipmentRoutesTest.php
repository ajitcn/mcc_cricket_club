<?php

declare(strict_types=1);

namespace Tests\Feature;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

final class EquipmentRoutesTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $DBGroup = 'tests';
    protected $migrate = false;

    private int $superAdmin;
    private int $branchAdmin;
    private int $branch;

    protected function setUp(): void
    {
        parent::setUp();
        self::assertSame(
            'mcc_cricket_club_test',
            strtolower((string) $this->db->getDatabase())
        );
        $this->superAdmin = $this->userId('test.mcc.superadmin@example.invalid');
        $this->branchAdmin = $this->userId('test.mcc.manager.vns@example.invalid');
        $this->branch = $this->branchId('TEST_MCC_VNS');
    }

    protected function tearDown(): void
    {
        session()->destroy();
        \Config\Services::resetSingle('authorization');
        parent::tearDown();
    }

    public function testSuperAdminCanOpenTheListAndBranchSelectionForm(): void
    {
        $this->authorize($this->superAdmin);
        $list = $this->withSession(session()->get())->get('equipment');

        self::assertSame(200, $list->response()->getStatusCode());
        self::assertStringContainsString('Equipment inventory', $list->getBody());

        $form = $this->withSession(session()->get())->get('equipment/new');
        self::assertSame(200, $form->response()->getStatusCode());
        self::assertStringContainsString('name="branch_id"', $form->getBody());
        self::assertStringContainsString('MCC Varanasi', $form->getBody());
    }

    public function testBranchAdminSeesFixedAuthorizedBranchWithoutBranchInput(): void
    {
        $this->authorize($this->branchAdmin);
        $form = $this->withSession(session()->get())->get('equipment/new');

        self::assertSame(200, $form->response()->getStatusCode());
        self::assertStringContainsString('MCC Varanasi', $form->getBody());
        self::assertStringNotContainsString('name="branch_id"', $form->getBody());
        self::assertStringNotContainsString('type="hidden" name="branch_id"', $form->getBody());
    }

    public function testEditAndDeleteScreensRequireTheirDedicatedPermissions(): void
    {
        $this->authorize($this->superAdmin, [
            'equipment.view',
            'equipment.create',
        ]);

        $edit = $this->withSession(session()->get())->get('equipment/999/edit');
        $delete = $this->withSession(session()->get())->get('equipment/999/delete');

        self::assertSame(403, $edit->response()->getStatusCode());
        self::assertSame(403, $delete->response()->getStatusCode());
    }

    private function authorize(int $userId, array $permissions = [
        'equipment.view',
        'equipment.create',
    ]): void
    {
        session()->set([
            'isLoggedIn' => true,
            'accountEnabled' => true,
            'user' => ['id' => $userId],
            'session_version' => 1,
            'permissions' => $permissions,
            'authorizedBranches' => [(string) $this->branch],
        ]);
        \Config\Services::resetSingle('authorization');
    }

    private function userId(string $email): int
    {
        return (int) $this->db->table('users')->where('email', $email)->get()->getRow('id');
    }

    private function branchId(string $code): int
    {
        return (int) $this->db->table('branches')->where('code', $code)->get()->getRow('id');
    }
}
