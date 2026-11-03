<?php

declare (strict_types=1);
namespace Tests\Unit;

use App\Libraries\Security\AuditLogger;
use App\Services\AuthorizationService;
use PHPUnit\Framework\TestCase;
final class Ops01AuditPolicyTest extends TestCase
{
    public function testAuditReadRequiresExplicitPermission(): void
    {
        $service = new AuthorizationService(['isLoggedIn' => true, 'permissions' => [], 'authorizedBranches' => ['1']]);
        self::assertFalse($service->can('audit.view'));
    }
    public function testAuditReadIsBranchScopedByAuthorization(): void
    {
        $service = new AuthorizationService(['isLoggedIn' => true, 'permissions' => ['audit.view'], 'authorizedBranches' => ['1']]);
        self::assertTrue($service->can('audit.view', '1'));
        self::assertFalse($service->can('audit.view', '2'));
    }
    public function testAuditLoggerRedactsApprovedSecretCategories(): void
    {
        $captured = [];
        (new AuditLogger(static function (array $event) use (&$captured): void {
            $captured = $event;
        }))->record(['actor' => '1', 'action' => 'test', 'entity_type' => 'test', 'entity_id' => '1', 'timestamp' => '2026-08-31T00:00:00Z',
    'branch_scope' => ['1'], 'password' => 'secret', 'reset_token' => 'token', 'smtp_credential' => 'credential']);
        self::assertSame('[REDACTED]', $captured['password']);
        self::assertSame('[REDACTED]', $captured['reset_token']);
        self::assertSame('[REDACTED]', $captured['smtp_credential']);
    }
    public function testAuditBoundaryDoesNotExposeMutationMethods(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/app/Services/AuditPersistenceService.php');
        self::assertIsString($source);
        self::assertStringContainsString("table('audit_logs')->insert", $source);
        self::assertDoesNotMatchRegularExpression('/audit_logs.*->(update|delete)/s', $source);
    }
    public function testDatabaseProtectionMigrationDeclaresUpdateAndDeleteGuards(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/app/Database/Migrations/20260831000400_CreateAuditAppendOnlyProtection.php');
        self::assertIsString($source);
        self::assertStringContainsString('audit_logs_no_update', $source);
        self::assertStringContainsString('audit_logs_no_delete', $source);
        self::assertStringNotContainsString('DROP TABLE', strtoupper($source));
    }
    public function testTrialAuditUsesTheCentralBoundary(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/app/Services/TrialService.php');
        self::assertIsString($source);
        self::assertStringContainsString("service('auditPersistence')->record", $source);
        self::assertDoesNotMatchRegularExpression("/table\\('audit_logs'\\)->insert/", $source);
    }
}
