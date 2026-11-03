<?php

declare (strict_types=1);
namespace Tests\Database;

use App\Models\UserModel;
use App\Services\AuthService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
final class AuthLifecycleIntegrationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    protected $DBGroup = 'tests';
    protected $migrate = false;
    private int $userId;
    protected function setUp(): void
    {
        parent::setUp();
        self::assertSame('mcc_cricket_club_test', $this->db->getDatabase());
        $this->db->table('users')->where('email', 'auth@example.test')->delete();
        $this->userId = $this->createUser();
    }
    protected function tearDown(): void
    {
        $this->db->table('audit_logs')->emptyTable();
        $this->db->table('password_reset_tokens')->emptyTable();
        $this->db->table('users')->emptyTable();
        parent::tearDown();
    }
    public function testValidLoginUsesPersistedUserAndRegeneratesSession(): void
    {
        self::assertTrue(service('auth')->attempt('auth@example.test', 'Correct horse battery staple!'));
        self::assertTrue(service('auth')->isLoggedIn());
        self::assertTrue(service('session')->didRegenerate);
        self::assertSame(0, (int) $this->db->table('users')->where('id', $this->userId)->get()->getRow()->failed_login_count);
        self::assertNull(session('user.password_hash'));
        self::assertSame(1, $this->db->table('audit_logs')->where(['action' => 'login', 'actor_user_id' => $this->userId])->countAllResults());
    }
    public function testBrowserLoginPageAndInvalidSubmissionUseSafeHtmlFlow(): void
    {
        $page = $this->get('auth/login');
        $page->assertStatus(200);
        $page->assertSee('Sign in');
        $response = $this->withSession(session()->get())->post('auth/login', ['email' => 'missing@example.test', 'password' => 'wrong-password',
    csrf_token() => csrf_hash()]);
        $response->assertStatus(302);
        $next = $this->withSession($_SESSION)->get('auth/login');
        $next->assertStatus(200);
        $next->assertSee('Invalid credentials.');
        self::assertStringNotContainsString('password_hash', $next->getBody());
    }
    public function testInvalidAndNonexistentCredentialsHaveSameSafeResult(): void
    {
        self::assertFalse(service('auth')->attempt('auth@example.test', 'wrong-password'));
        $existing = $this->db->table('audit_logs')->where('action', 'login_failed')->get()->getRowArray();
        self::assertNotNull($existing);
        self::assertFalse(service('auth')->attempt('missing@example.test', 'wrong-password'));
        self::assertSame(2, $this->db->table('audit_logs')->where('action', 'login_failed')->countAllResults());
        self::assertSame('unknown', $this->db->table('audit_logs')->where('action', 'login_failed')->where('actor_user_id', null)->get()->getRow()->entity_id);
    }
    public function testDisabledUserCannotAuthenticate(): void
    {
        $this->db->table('users')->where('id', $this->userId)->update(['is_enabled' => 0]);
        self::assertFalse(service('auth')->attempt('auth@example.test', 'Correct horse battery staple!'));
        self::assertSame(0, (int) $this->db->table('users')->where('id', $this->userId)->get()->getRow()->is_enabled);
    }
    public function testLogoutDestroysSessionAndStaleSessionIsRejected(): void
    {
        self::assertTrue(service('auth')->attempt('auth@example.test', 'Correct horse battery staple!'));
        service('auth')->logout();
        self::assertFalse(service('auth')->isLoggedIn());
        self::assertNull(session('user'));
        self::assertSame(1, $this->db->table('audit_logs')->where(['action' => 'logout', 'actor_user_id' => $this->userId])->countAllResults());
    }
    public function testAccountDisablementInvalidatesExistingSessionVersion(): void
    {
        self::assertTrue(service('auth')->attempt('auth@example.test', 'Correct horse battery staple!'));
        $before = (int) $this->db->table('users')->where('id', $this->userId)->get()->getRow()->session_version;
        service('auth')->disable($this->userId, $this->userId);
        self::assertSame($before + 1, (int) $this->db->table('users')->where('id', $this->userId)->get()->getRow()->session_version);
        self::assertFalse(service('auth')->isLoggedIn());
        self::assertSame(1, $this->db->table('audit_logs')->where(['action' => 'account_disabled', 'actor_user_id' => $this->userId])->countAllResults());
    }
    public function testPasswordResetInvalidatesExistingSessionAndIsSingleUse(): void
    {
        self::assertTrue(service('auth')->attempt('auth@example.test', 'Correct horse battery staple!'));
        $token = bin2hex(random_bytes(32));
        $this->db->table('password_reset_tokens')->insert(['user_id' => $this->userId, 'token_hash' => hash('sha256', $token),
    'expires_at' => date('Y-m-d H:i:s', time() + 900), 'created_at' => date('Y-m-d H:i:s')]);
        service('auth')->resetPassword($token, 'A newer secure password!');
        self::assertFalse(service('auth')->isLoggedIn());
        self::assertSame(1, $this->db->table('password_reset_tokens')->where('used_at IS NOT NULL')->countAllResults());
        self::assertSame(1, $this->db->table('audit_logs')->where(['action' => 'password_reset_completed',
    'actor_user_id' => $this->userId])->countAllResults());
        $this->expectException(\RuntimeException::class);
        service('auth')->resetPassword($token, 'Another newer password!');
    }
    public function testPerAccountLockoutIsPersistedAfterFiveFailures(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            self::assertFalse(service('auth')->attempt('auth@example.test', 'wrong-password'));
        }
        $user = $this->db->table('users')->where('id', $this->userId)->get()->getRowArray();
        self::assertSame(5, (int) $user['failed_login_count']);
        self::assertNotNull($user['locked_until']);
        self::assertFalse(service('auth')->attempt('auth@example.test', 'Correct horse battery staple!'));
    }
    public function testLockoutExpiryAllowsLoginAndResetsFailureState(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            service('auth')->attempt('auth@example.test', 'wrong-password');
        }
        $this->db->table('users')->where('id', $this->userId)->update(['locked_until' => date('Y-m-d H:i:s', time() - 1)]);
        self::assertTrue(service('auth')->attempt('auth@example.test', 'Correct horse battery staple!'));
        $user = $this->db->table('users')->where('id', $this->userId)->get()->getRowArray();
        self::assertSame(0, (int) $user['failed_login_count']);
        self::assertNull($user['locked_until']);
    }
    public function testSuccessfulLoginResetsPriorFailedAttempts(): void
    {
        service('auth')->attempt('auth@example.test', 'wrong-password');
        service('auth')->attempt('auth@example.test', 'wrong-password');
        self::assertTrue(service('auth')->attempt('auth@example.test', 'Correct horse battery staple!'));
        self::assertSame(0, (int) $this->db->table('users')->where('id', $this->userId)->get()->getRow()->failed_login_count);
    }
    public function testExpiredResetTokenIsRejected(): void
    {
        $token = bin2hex(random_bytes(32));
        $this->db->table('password_reset_tokens')->insert(['user_id' => $this->userId, 'token_hash' => hash('sha256', $token),
    'expires_at' => date('Y-m-d H:i:s', time() - 1), 'created_at' => date('Y-m-d H:i:s')]);
        $this->expectException(\RuntimeException::class);
        service('auth')->resetPassword($token, 'A newer secure password!');
    }
    public function testAuthenticationAuditDoesNotPersistSecrets(): void
    {
        service('auth')->attempt('auth@example.test', 'wrong-password');
        $audit = json_encode($this->db->table('audit_logs')->get()->getResultArray(), JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString('wrong-password', $audit);
        self::assertStringNotContainsString('password_hash', $audit);
        self::assertStringNotContainsString('token', strtolower($audit));
        self::assertStringNotContainsString('session', strtolower($audit));
    }
    public function testProtectedRouteIsDeniedThroughTheRealAuthFilterPath(): void
    {
        $response = $this->withRoutes([['GET', 'test/protected', static fn() => service('response')->setJSON(['protected' => true]),
    ['filter' => 'auth']]])->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])->get('test/protected');
        self::assertSame(401, $response->response()->getStatusCode());
        $body = json_decode((string) $response->response()->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertFalse($body['status']);
    }
    public function testAuthenticatedRequestReachesCentralAuthorizationFilter(): void
    {
        self::assertTrue(service('auth')->attempt('auth@example.test', 'Correct horse battery staple!'));
        $session = session()->get();
        $response = $this->withRoutes([['GET', 'test/authorized', static fn() => service('response')->setJSON(['authorized' => true]),
    ['filter' => ['auth', 'permission:player.view']]]])->withSession($session)->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])->get('test/authorized');
        self::assertSame(403, $response->response()->getStatusCode());
    }
    public function testValidLoginRunsThroughTheRealHttpRoute(): void
    {
        $response = $this->withBodyFormat('json')->withHeaders(['X-CSRF-TOKEN' => csrf_hash()])->post('auth/login', ['email' => 'auth@example.test',
    'password' => 'Correct horse battery staple!']);
        self::assertSame(200, $response->response()->getStatusCode());
        $body = json_decode((string) $response->response()->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($body['data']['authenticated']);
        self::assertTrue(service('auth')->isLoggedIn());
    }
    public function testMalformedJsonIsRejectedByTheRealHttpRoute(): void
    {
        $response = $this->withBody('{"email":')->withHeaders(['X-CSRF-TOKEN' => csrf_hash()])->post('auth/login');
        self::assertSame(422, $response->response()->getStatusCode());
        $body = json_decode((string) $response->response()->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertFalse($body['status']);
    }
    public function testExistingAndNonexistentLoginResponsesAreIndistinguishable(): void
    {
        $existing = $this->withBodyFormat('json')->withHeaders(['X-CSRF-TOKEN' => csrf_hash()])->post('auth/login', ['email' => 'auth@example.test',
    'password' => 'wrong-password']);
        $missing = $this->withBodyFormat('json')->withHeaders(['X-CSRF-TOKEN' => csrf_hash()])->post('auth/login', ['email' => 'missing@example.test',
    'password' => 'wrong-password']);
        self::assertSame($existing->response()->getStatusCode(), $missing->response()->getStatusCode());
        self::assertSame($existing->response()->getBody(), $missing->response()->getBody());
    }
    private function createUser(): int
    {
        $model = new UserModel();
        $model->insert(['email' => 'auth@example.test', 'password_hash' => password_hash('Correct horse battery staple!', PASSWORD_DEFAULT),
    'display_name' => 'Authentication Test User', 'is_enabled' => 1, 'failed_login_count' => 0, 'session_version' => 1,
    'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
        return (int) $model->getInsertID();
    }
}
