<?php

declare (strict_types=1);
namespace Tests\Database;

use App\Services\AuthService;
use App\Services\RecoveryDeliveryInterface;
use App\Services\RecoveryRateLimitInterface;
use App\Services\RecoveryRateLimitUnavailableException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
final class RecoveryRateLimiterIntegrationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    protected $DBGroup = 'tests';
    protected $migrate = false;
    protected function setUp(): void
    {
        parent::setUp();
        self::assertSame('mcc_cricket_club_test', $this->db->getDatabase());
        self::assertSame('mcc_cricket_club_test', $this->db->getDatabase());
        $this->db->table('recovery_rate_limit_events')->emptyTable();
        $this->db->table('recovery_rate_limit_keys')->emptyTable();
        $this->db->table('audit_logs')->emptyTable();
    }
    protected function tearDown(): void
    {
        $this->db->table('recovery_rate_limit_events')->emptyTable();
        $this->db->table('recovery_rate_limit_keys')->emptyTable();
        $this->db->table('audit_logs')->emptyTable();
        parent::tearDown();
    }
    public function testAccountLimitIsFiveRequestsPerRollingWindow(): void
    {
        $limiter = service('recoveryRateLimiter', false);
        for ($i = 0; $i < 5; $i++) {
            self::assertTrue($limiter->allow('account-' . $i . '@example.test', '198.51.100.' . $i));
        }
        for ($i = 0; $i < 5; $i++) {
            self::assertTrue($limiter->allow('same@example.test', '198.51.100.' . $i));
        }
        self::assertFalse($limiter->allow('same@example.test', '203.0.113.10'));
    }
    public function testIpLimitIsTenRequestsPerRollingWindow(): void
    {
        $limiter = service('recoveryRateLimiter', false);
        for ($i = 0; $i < 10; $i++) {
            self::assertTrue($limiter->allow('ip-' . $i . '@example.test', '203.0.113.50'));
        }
        self::assertFalse($limiter->allow('ip-final@example.test', '203.0.113.50'));
    }
    public function testAccountAndIpLimitsOperateIndependently(): void
    {
        $limiter = service('recoveryRateLimiter', false);
        for ($i = 0; $i < 5; $i++) {
            self::assertTrue($limiter->allow('independent@example.test', '192.0.2.' . $i));
        }
        self::assertFalse($limiter->allow('independent@example.test', '192.0.2.99'));
        self::assertTrue($limiter->allow('other@example.test', '192.0.2.99'));
        $this->db->table('recovery_rate_limit_events')->emptyTable();
        $this->db->table('recovery_rate_limit_keys')->emptyTable();
        for ($i = 0; $i < 10; $i++) {
            self::assertTrue($limiter->allow('ip-independent-' . $i . '@example.test', '192.0.2.100'));
        }
        self::assertFalse($limiter->allow('another@example.test', '192.0.2.100'));
        self::assertTrue($limiter->allow('another@example.test', '192.0.2.101'));
    }
    public function testRequestsOutsideTheRollingWindowAreRemoved(): void
    {
        $limiter = service('recoveryRateLimiter', false);
        for ($i = 0; $i < 5; $i++) {
            self::assertTrue($limiter->allow('window@example.test', '198.51.100.200'));
        }
        $this->db->table('recovery_rate_limit_events')->update(['requested_at' => date('Y-m-d H:i:s', time() - 901)]);
        self::assertTrue($limiter->allow('window@example.test', '198.51.100.200'));
        self::assertSame(2, $this->db->table('recovery_rate_limit_events')->countAllResults());
    }
    public function testSuccessfulResetClearsAccountRateLimitState(): void
    {
        $limiter = service('recoveryRateLimiter', false);
        for ($i = 0; $i < 5; $i++) {
            self::assertTrue($limiter->allow('clear@example.test', '198.51.100.201'));
        }
        self::assertFalse($limiter->allow('clear@example.test', '198.51.100.202'));
        $limiter->clearAccount('clear@example.test');
        self::assertTrue($limiter->allow('clear@example.test', '198.51.100.202'));
    }
    public function testRateLimitEventsContainOnlyHashedKeysAndTimestamps(): void
    {
        service('recoveryRateLimiter', false)->allow('secret-account@example.test', '198.51.100.203');
        $row = $this->db->table('recovery_rate_limit_events')->get()->getRowArray();
        self::assertIsArray($row);
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $row['key_hash']);
        self::assertStringNotContainsString('secret-account@example.test', json_encode($row, JSON_THROW_ON_ERROR));
        self::assertStringNotContainsString('198.51.100.203', json_encode($row, JSON_THROW_ON_ERROR));
    }
    public function testBackingStoreFailureIsNotSilentlyBypassed(): void
    {
        $failing = new FailingRecoveryRateLimiter();
        $this->expectException(RecoveryRateLimitUnavailableException::class);
        (new AuthService(new NoopRecoveryDelivery(), $failing))->requestReset('failure@example.test', '198.51.100.204');
    }
    public function testMultipleLimiterInstancesShareAtomicPersistedState(): void
    {
        $first = service('recoveryRateLimiter', false);
        $second = service('recoveryRateLimiter', false);
        for ($i = 0; $i < 4; $i++) {
            self::assertTrue($first->allow('shared@example.test', '198.51.100.205'));
        }
        self::assertTrue($second->allow('shared@example.test', '198.51.100.205'));
        self::assertFalse($first->allow('shared@example.test', '198.51.100.205'));
    }
}
final class FailingRecoveryRateLimiter implements RecoveryRateLimitInterface
{
    public function allow(string $email, string $ipAddress): bool
    {
        throw new RecoveryRateLimitUnavailableException('Controlled rate-limit store failure.');
    }
    public function clearAccount(string $email): void
    {
        throw new RecoveryRateLimitUnavailableException('Controlled rate-limit store failure.');
    }
}
final class NoopRecoveryDelivery implements RecoveryDeliveryInterface
{
    public function deliver(string $recipient, string $token): void
    {
    }
}
