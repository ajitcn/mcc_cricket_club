<?php

declare (strict_types=1);
namespace Tests\Database;

use App\Models\UserModel;
use App\Services\AuthService;
use App\Services\RecoveryDeliveryException;
use App\Services\RecoveryDeliveryInterface;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
final class AuthRecoveryIntegrationTest extends CIUnitTestCase
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
        $this->db->table('audit_logs')->emptyTable();
        $this->db->table('password_reset_tokens')->emptyTable();
        $this->db->table('recovery_rate_limit_events')->emptyTable();
        $this->db->table('recovery_rate_limit_keys')->emptyTable();
        $this->db->table('users')->where('email', 'recovery@example.test')->delete();
        $model = new UserModel();
        $model->insert(['email' => 'recovery@example.test', 'password_hash' => password_hash('Original secure password!', PASSWORD_DEFAULT),
    'display_name' => 'Recovery Test User', 'is_enabled' => 1, 'failed_login_count' => 0, 'session_version' => 1, 'created_at' => date('Y-m-d H:i:s'),
    'updated_at' => date('Y-m-d H:i:s')]);
        $this->userId = (int) $model->getInsertID();
    }
    protected function tearDown(): void
    {
        $this->db->table('audit_logs')->emptyTable();
        $this->db->table('password_reset_tokens')->emptyTable();
        $this->db->table('recovery_rate_limit_events')->emptyTable();
        $this->db->table('recovery_rate_limit_keys')->emptyTable();
        $this->db->table('users')->emptyTable();
        parent::tearDown();
    }
    public function testExistingAccountRecoveryCreatesHashedTokenAndAuditsDelivery(): void
    {
        $mailer = new RecordingRecoveryDelivery();
        (new AuthService($mailer))->requestReset('recovery@example.test');
        self::assertSame('recovery@example.test', $mailer->recipient);
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $mailer->token);
        $row = $this->db->table('password_reset_tokens')->where('user_id', $this->userId)->get()->getRowArray();
        self::assertIsArray($row);
        self::assertSame(hash('sha256', $mailer->token), $row['token_hash']);
        self::assertNotSame($mailer->token, $row['token_hash']);
        self::assertSame(1, $this->db->table('audit_logs')->where('action', 'password_recovery_delivery_attempted')->countAllResults());
    }
    public function testNonexistentAccountDoesNotInvokeDeliveryOrRevealStateThroughHttp(): void
    {
        $existing = $this->requestRecovery('recovery@example.test');
        $missing = $this->requestRecovery('missing@example.test');
        self::assertSame(202, $existing->response()->getStatusCode());
        self::assertSame($existing->response()->getBody(), $missing->response()->getBody());
    }
    public function testDisabledAccountHasTheSameSafeResponse(): void
    {
        $this->db->table('users')->where('id', $this->userId)->update(['is_enabled' => 0]);
        $response = $this->requestRecovery('recovery@example.test');
        self::assertSame(202, $response->response()->getStatusCode());
        self::assertStringNotContainsString('disabled', strtolower((string) $response->response()->getBody()));
        self::assertSame(0, $this->db->table('password_reset_tokens')->countAllResults());
    }
    public function testRateLimitedRecoveryKeepsTheGenericResponseAndAuditsSafely(): void
    {
        $responses = [];
        for ($i = 0; $i < 6; $i++) {
            $responses[] = $this->requestRecovery('unknown@example.test');
        }
        self::assertSame(202, $responses[5]->response()->getStatusCode());
        self::assertSame($responses[0]->response()->getBody(), $responses[5]->response()->getBody());
        self::assertSame(1, $this->db->table('audit_logs')->where('action', 'password_recovery_rate_limited')->countAllResults());
        $audit = json_encode($this->db->table('audit_logs')->get()->getResultArray(), JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString('unknown@example.test', $audit);
    }
    public function testInvalidMalformedOversizedAndUnexpectedRecoveryRequestsAreRejected(): void
    {
        $invalid = $this->withBodyFormat('json')->withHeaders(['X-CSRF-TOKEN' => csrf_hash()])->post('auth/recovery/request', ['email' => 'not-an-email']);
        $malformed = $this->withBody('{"email":')->withHeaders(['X-CSRF-TOKEN' => csrf_hash()])->post('auth/recovery/request');
        $unexpected = $this->withBody('{"email":"recovery@example.test","role":"admin"}')->withHeaders(['X-CSRF-TOKEN' =>
    csrf_hash()])->post('auth/recovery/request');
        $oversized = $this->withBody('{"email":"' . str_repeat('a',
    16000) . '@example.test"}')->withHeaders(['X-CSRF-TOKEN' => csrf_hash()])->post('auth/recovery/request');
        foreach ([$invalid, $malformed, $unexpected, $oversized] as $response) {
            self::assertSame(422, $response->response()->getStatusCode());
            self::assertStringNotContainsString('recovery@example.test', (string) $response->response()->getBody());
        }
    }
    public function testSuccessfulResetIsSingleUseAndInvalidatesExistingSession(): void
    {
        $mailer = new RecordingRecoveryDelivery();
        $auth = new AuthService($mailer);
        $auth->requestReset('recovery@example.test');
        $auth->requestReset('recovery@example.test', '198.51.100.11');
        $auth->requestReset('recovery@example.test', '198.51.100.12');
        $auth->requestReset('recovery@example.test', '198.51.100.13');
        $auth->requestReset('recovery@example.test', '198.51.100.14');
        self::assertTrue($auth->attempt('recovery@example.test', 'Original secure password!'));
        $auth->resetPassword($mailer->token, 'Replacement secure password!');
        self::assertFalse($auth->isLoggedIn());
        self::assertSame(5, $this->db->table('password_reset_tokens')->where('used_at IS NOT NULL')->countAllResults());
        self::assertSame(1, $this->db->table('audit_logs')->where('action', 'password_reset_completed')->countAllResults());
        self::assertTrue(service('recoveryRateLimiter', false)->allow('recovery@example.test', '198.51.100.15'));
        $this->expectException(\RuntimeException::class);
        $auth->resetPassword($mailer->token, 'Another replacement password!');
    }
    public function testRecoveryRateLimitIsIndependentOfAuthenticationLockout(): void
    {
        $auth = new AuthService(new RecordingRecoveryDelivery());
        for ($i = 0; $i < 5; $i++) {
            self::assertFalse($auth->attempt('recovery@example.test', 'Wrong password!'));
        }
        self::assertFalse($auth->attempt('recovery@example.test', 'Original secure password!'));
        $mailer = new RecordingRecoveryDelivery();
        (new AuthService($mailer))->requestReset('recovery@example.test', '203.0.113.10');
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $mailer->token);
        self::assertSame(1, $this->db->table('password_reset_tokens')->where('used_at', null)->countAllResults());
    }
    public function testExpiredAndInvalidTokensAreRejectedAndAuditedWithoutSecrets(): void
    {
        $expired = bin2hex(random_bytes(32));
        $this->db->table('password_reset_tokens')->insert(['user_id' => $this->userId, 'token_hash' => hash('sha256', $expired),
    'expires_at' => date('Y-m-d H:i:s', time() - 1), 'created_at' => date('Y-m-d H:i:s')]);
        try {
            (new AuthService(new RecordingRecoveryDelivery()))->resetPassword($expired, 'Replacement secure password!');
            self::fail('Expired token was accepted.');
        } catch (\RuntimeException $exception) {
            self::assertSame('Invalid reset token.', $exception->getMessage());
        }
        try {
            (new AuthService(new RecordingRecoveryDelivery()))->resetPassword(str_repeat('a', 64), 'Replacement secure password!');
            self::fail('Invalid token was accepted.');
        } catch (\RuntimeException $exception) {
            self::assertSame('Invalid reset token.', $exception->getMessage());
        }
        $audit = json_encode($this->db->table('audit_logs')->get()->getResultArray(), JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString($expired, $audit);
        self::assertStringNotContainsString('Replacement secure password!', $audit);
    }
    public function testDeliveryFailureDeletesPendingTokenAndKeepsGenericHttpResponse(): void
    {
        $mailer = new FailingRecoveryDelivery();
        (new AuthService($mailer))->requestReset('recovery@example.test');
        self::assertSame(0, $this->db->table('password_reset_tokens')->where('used_at', null)->countAllResults());
        self::assertSame(1, $this->db->table('audit_logs')->where('action', 'password_recovery_delivery_failed')->countAllResults());
    }
    public function testRecoveryEndpointsRemainCsrfProtectedAndResetIsNotSessionProtected(): void
    {
        try {
            $this->withBody('{"email":"recovery@example.test"}')->post('auth/recovery/request');
            self::fail('The recovery request bypassed CSRF protection.');
        } catch (\CodeIgniter\Security\Exceptions\SecurityException $exception) {
            self::assertSame('The action you requested is not allowed.', $exception->getMessage());
        }
        $mailer = new RecordingRecoveryDelivery();
        (new AuthService($mailer))->requestReset('recovery@example.test');
        $response = $this->withBodyFormat('json')->withHeaders(['X-CSRF-TOKEN' => csrf_hash()])->post('auth/recovery/reset', ['token' => $mailer->token,
    'password' => 'Replacement secure password!']);
        self::assertSame(200, $response->response()->getStatusCode());
    }
    public function testRecoveryAuditsContainNoSecrets(): void
    {
        $mailer = new RecordingRecoveryDelivery();
        (new AuthService($mailer))->requestReset('recovery@example.test');
        $audit = json_encode($this->db->table('audit_logs')->get()->getResultArray(), JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString($mailer->token, $audit);
        self::assertStringNotContainsString('SMTP-secret', $audit);
        self::assertStringNotContainsString('password_hash', strtolower($audit));
    }
    private function requestRecovery(string $email): \CodeIgniter\Test\TestResponse
    {
        return $this->withBodyFormat('json')->withHeaders(['X-CSRF-TOKEN' => csrf_hash()])->post('auth/recovery/request', ['email' => $email]);
    }
}
final class RecordingRecoveryDelivery implements RecoveryDeliveryInterface
{
    public string $recipient = '';
    public string $token = '';
    public function deliver(string $recipient, string $token): void
    {
        $this->recipient = $recipient;
        $this->token = $token;
    }
}
final class FailingRecoveryDelivery implements RecoveryDeliveryInterface
{
    public function deliver(string $recipient, string $token): void
    {
        throw new RecoveryDeliveryException('Controlled SMTP failure.');
    }
}
