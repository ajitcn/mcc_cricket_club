<?php

namespace Tests\Unit;

use App\Libraries\Security\AuditLogger;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
final class AuditLoggerTest extends TestCase
{
    public function testRequiredMetadataIsRecordedAndSensitiveFieldsAreRedacted(): void
    {
        $records = [];
        $logger = new AuditLogger(static function (array $record) use (&$records): void {
            $records[] = $record;
        });
        $logger->record(['actor' => 'user-1', 'action' => 'generic.action', 'entity_type' => 'generic.entity', 'entity_id' => 'entity-1',
    'timestamp' => '2026-08-28T12:00:00+05:30', 'branch_scope' => ['branch-a'], 'reason' => 'test', 'password' => 'must-not-appear',
    'medical_notes' => 'private', 'signed_url' => 'https://example.test/private']);
        self::assertCount(1, $records);
        self::assertSame('[REDACTED]', $records[0]['password']);
        self::assertSame('[REDACTED]', $records[0]['medical_notes']);
        self::assertSame('[REDACTED]', $records[0]['signed_url']);
        self::assertSame('generic.action', $records[0]['action']);
    }
    public function testMissingRequiredMetadataIsRejected(): void
    {
        $logger = new AuditLogger(static function (): void {
        });
        $this->expectException(InvalidArgumentException::class);
        $logger->record(['actor' => 'user-1']);
    }
}
