<?php

declare (strict_types=1);
namespace Tests\Database;

use App\Modules\Admin\Models\AdminUserModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
final class DashboardIntegrationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    protected $DBGroup = 'tests';
    protected $migrate = false;
    private int $user;
    private int $branch;
    protected function setUp(): void
    {
        parent::setUp();
        foreach (['user_branches', 'user_roles', 'role_permissions', 'users', 'roles', 'branches'] as $table) {
            $this->db->table($table)->emptyTable();
        }
        $now = date('Y-m-d H:i:s');
        $this->db->table('branches')->insert(['name' => 'Dashboard Branch', 'code' => 'dashboard-test', 'is_active' => 1, 'created_at' => $now,
    'updated_at' => $now]);
        $this->branch = (int) $this->db->insertID();
        $model = new AdminUserModel();
        $model->insert(['email' => 'dashboard@example.test', 'password_hash' => password_hash('Test password!', PASSWORD_DEFAULT),
    'display_name' => 'Dashboard User', 'is_enabled' => 1, 'failed_login_count' => 0, 'session_version' => 1, 'locked_until' => null,
    'last_login_at' => null, 'created_at' => $now, 'updated_at' => $now]);
        $this->user = (int) $model->getInsertID();
        $this->db->table('user_branches')->insert(['user_id' => $this->user, 'branch_id' => $this->branch, 'created_at' => $now]);
        session()->set(['isLoggedIn' => true, 'user' => ['id' => $this->user, 'email' => 'dashboard@example.test', 'display_name' => 'Dashboard User'],
    'session_version' => 1, 'permissions' => ['lead.view', 'admission.view', 'player.view', 'session.update', 'attendance.view'],
    'authorizedBranches' => [(string) $this->branch], 'accountEnabled' => true]);
        \Config\Services::resetSingle('authorization');
    }
    protected function tearDown(): void
    {
        session()->destroy();
        parent::tearDown();
    }
    public function testDashboardRendersPermissionScopedStaffExperience(): void
    {
        $response = $this->withSession(session()->get())->get('/');
        $response->assertStatus(200);
        $response->assertSee('MCC Cricket Club Management System');
        $response->assertSee('Operational overview');
        $response->assertSee('Total leads');
        $response->assertSee('CRM');
        $response->assertSee('Admissions');
        $response->assertSee('Players');
        $response->assertSee('Scheduling');
        $response->assertSee('Attendance');
        $response->assertDontSee('Administration');
        $response->assertDontSee('Project foundation is ready');
    }
    public function testDashboardRequiresAuthentication(): void
    {
        session()->destroy();
        \Config\Services::resetSingle('authorization');
        $response = $this->get('/');
        $response->assertStatus(200);
        $response->assertSee('Sign in');
    }
}
