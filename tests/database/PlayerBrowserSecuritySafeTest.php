<?php

declare (strict_types=1);
namespace Tests\Database;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
final class PlayerBrowserSecuritySafeTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    protected $DBGroup = 'tests';
    protected $migrate = false;
    private int $player;
    private int $branch;
    private int $otherBranch;
    private int $actor;
    protected function setUp(): void
    {
        parent::setUp();
        self::assertSame('mcc_cricket_club_test', strtolower((string) $this->db->getDatabase()));
        $this->actor = (int) $this->db->table('users')->where('email', 'test.mcc.superadmin@example.invalid')->get()->getRow('id');
        $this->branch = (int) $this->db->table('branches')->where('code', 'TEST_MCC_VNS')->get()->getRow('id');
        $this->otherBranch = (int) $this->db->table('branches')->where('code', 'TEST_MCC_DEL')->get()->getRow('id');
        $this->player = (int) $this->db->table('players')->where('branch_id', $this->branch)->orderBy('id', 'ASC')->get()->getRow('id');
        self::assertGreaterThan(0, $this->actor);
        self::assertGreaterThan(0, $this->branch);
        self::assertGreaterThan(0, $this->otherBranch);
        self::assertGreaterThan(0, $this->player);
    }
    public function testAuthorizedListSearchStatusFilterAndSharedUi(): void
    {
        $this->authorize(['player.view'], [$this->branch]);
        $response = $this->withSession(session()->get())->get('players');
        self::assertSame(200, $response->response()->getStatusCode());
        self::assertStringContainsString('Players', $response->getBody());
        self::assertStringContainsString('mcc-sidebar', $response->getBody());
        self::assertStringContainsString('title="Players"', $response->getBody());
        self::assertStringContainsString('mcc-nav-link is-active', $response->getBody());
        self::assertStringContainsString('Players', $response->getBody());
        self::assertStringContainsString('name="q"', $response->getBody());
        self::assertStringContainsString('name="status"', $response->getBody());
        self::assertStringContainsString('mcc-card', $response->getBody());
        self::assertStringContainsString('View details', $response->getBody());
        self::assertStringContainsString('mcc-player-modal', $response->getBody());
        $name = (string) $this->db->table('players p')->select('pe.full_name')->join('persons pe', 'pe.id = p.person_id')->where('p.id',
    $this->player)->get()->getRow('full_name');
        $search = $this->withSession(session()->get())->get('players?q=' . rawurlencode($name));
        self::assertSame(200, $search->response()->getStatusCode());
        self::assertStringContainsString($name, $search->getBody());
        self::assertStringContainsString(
            'data-player-url="' . site_url('players/' . $this->player) . '"',
            $search->getBody()
        );
        $status = (string) $this->db->table('players')->where('id', $this->player)->get()->getRow('status');
        $filtered = $this->withSession(session()->get())->get('players?q=' . rawurlencode($name) . '&status=' . rawurlencode($status));
        self::assertSame(200, $filtered->response()->getStatusCode());
        self::assertStringContainsString($name, $filtered->getBody());
    }
    public function testUnauthenticatedAndMissingPermissionAreDenied(): void
    {
        session()->destroy();
        \Config\Services::resetSingle('authorization');
        self::assertContains($this->get('players')->response()->getStatusCode(), [302, 401, 403]);
        $this->authorize([], [$this->branch]);
        self::assertSame(403, $this->withSession(session()->get())->get('players')->response()->getStatusCode());
    }
    public function testBranchIsolationDeniesDetailAndHidesListResults(): void
    {
        $this->authorize(['player.view'], [$this->otherBranch]);
        $list = $this->withSession(session()->get())->get('players');
        self::assertSame(200, $list->response()->getStatusCode());
        $name = (string) $this->db->table('players p')->select('pe.full_name')->join('persons pe', 'pe.id = p.person_id')->where('p.id',
    $this->player)->get()->getRow('full_name');
        self::assertStringNotContainsString($name, $list->getBody());
        self::assertSame(403, $this->withSession(session()->get())->get('players/' . $this->player)->response()->getStatusCode());
    }
    public function testCanonicalAndLegacyDetailRenderLineageLifecycleAndFooter(): void
    {
        $this->authorize(['player.view'], [$this->branch]);
        $detail = $this->withSession(session()->get())->get('players/' . $this->player);
        self::assertSame(200, $detail->response()->getStatusCode());
        self::assertStringContainsString('Admission', $detail->getBody());
        self::assertStringContainsString('Lifecycle', $detail->getBody());
        self::assertStringContainsString('status', strtolower($detail->getBody()));
        self::assertStringNotContainsString('/players/' . $this->player . '/profile"', $detail->getBody());
        self::assertStringNotContainsString('/players/' . $this->player . '/status"', $detail->getBody());
        self::assertStringNotContainsString('/players/' . $this->player . '/contacts"', $detail->getBody());
        self::assertStringContainsString('Identity and profile', $detail->getBody());
        self::assertStringContainsString('Contacts and relationships', $detail->getBody());
        self::assertStringContainsString('Placement history', $detail->getBody());
        self::assertStringContainsString('mcc-breadcrumbs', $detail->getBody());
        self::assertStringContainsString('Players', $detail->getBody());
        self::assertStringContainsString('mcc-footer', $detail->getBody());
        $this->authorize(['player.view', 'player.update'], [$this->branch]);
        $editable = $this->withSession(session()->get())->get('players/' . $this->player);
        self::assertStringContainsString('/players/' . $this->player . '/profile"', $editable->getBody());
        self::assertStringContainsString('/players/' . $this->player . '/status"', $editable->getBody());
        self::assertStringContainsString('/players/' . $this->player . '/contacts"', $editable->getBody());
        $legacy = $this->withSession(session()->get())->get('players/' . $this->player . '/page');
        self::assertSame(302, $legacy->response()->getStatusCode());
        self::assertStringContainsString('/players/' . $this->player, (string) $legacy->response()->getHeaderLine('Location'));
    }

    public function testPlayerUpdatePermissionControlsDetailAndAssignmentModalFormsConsistently(): void
    {
        $this->authorize(['player.view', 'player.update'], [$this->branch]);

        $detail = $this->withSession(session()->get())->get('players/' . $this->player);
        self::assertSame(200, $detail->response()->getStatusCode());
        self::assertStringContainsString('/players/' . $this->player . '/batch"', $detail->getBody());
        self::assertStringContainsString('/players/' . $this->player . '/coaches"', $detail->getBody());

        $list = $this->withSession(session()->get())->get('players');
        self::assertSame(200, $list->response()->getStatusCode());
        self::assertStringContainsString('data-player-action="batch"', $list->getBody());
        self::assertStringContainsString('data-player-action="coaches"', $list->getBody());
    }
    public function testPlayerSourceContractsProtectStateChangingBrowserRoutes(): void
    {
        $routes = file_get_contents(APPPATH . 'Modules/Players/Config/Routes.php');
        $service = file_get_contents(APPPATH . 'Services/PlayerService.php');
        self::assertStringContainsString("permission:player.update", $routes);
        self::assertStringContainsString("permission:player.view", $routes);
        self::assertStringContainsString("permission:player.view", $routes);
        self::assertStringContainsString("'player.update'", $service);
        self::assertStringContainsString('authorizeAny', $service);
        self::assertStringContainsString("service('authorization')->can", $service);
        self::assertStringContainsString("'branch_id' => (int) \$player['branch_id']", $service);
        self::assertStringNotContainsString('password_hash', strtolower($service));
    }

    public function testAssignmentRoutesRejectMissingAndInvalidCsrf(): void
    {
        $this->authorize(['player.view', 'player.update'], [$this->branch]);

        try {
            $this->withSession(session()->get())->post(
                'players/' . $this->player . '/coaches',
                [],
            );
            self::fail('Missing CSRF was accepted for Coach assignment.');
        } catch (\CodeIgniter\Security\Exceptions\SecurityException) {
            self::assertTrue(true);
        }

        try {
            $this->withSession(session()->get())->post(
                'players/' . $this->player . '/batch',
                [csrf_token() => 'invalid-token'],
            );
            self::fail('Invalid CSRF was accepted for Batch assignment.');
        } catch (\CodeIgniter\Security\Exceptions\SecurityException) {
            self::assertTrue(true);
        }
    }
    /** @param list<string> $permissions @param list<int> $branches */
    private function authorize(array $permissions, array $branches): void
    {
        session()->set(['isLoggedIn' => true, 'accountEnabled' => true, 'user' => ['id' => $this->actor], 'session_version' => 1,
    'permissions' => $permissions, 'authorizedBranches' => array_map('strval', $branches)]);
        \Config\Services::resetSingle('authorization');
    }
}
