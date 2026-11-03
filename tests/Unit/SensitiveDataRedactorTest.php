<?php

namespace Tests\Unit;

use App\Libraries\Security\SensitiveDataRedactor;
use PHPUnit\Framework\TestCase;
final class SensitiveDataRedactorTest extends TestCase
{
    public function testNestedSensitiveValuesAreRedacted(): void
    {
        $result = (new SensitiveDataRedactor())->redact(['actor' => 'user-1', 'context' => ['access_token' => 'secret-token',
    'medical_notes' => 'private note', 'document_number' => 'private number']]);
        self::assertSame('user-1', $result['actor']);
        self::assertSame('[REDACTED]', $result['context']['access_token']);
        self::assertSame('[REDACTED]', $result['context']['medical_notes']);
        self::assertSame('[REDACTED]', $result['context']['document_number']);
    }
}
