<?php

namespace App\Libraries\Security;

use InvalidArgumentException;

final class AuditLogger
{
    /** @var callable(array<string, mixed>): void */
    private $sink;

    public function __construct(?callable $sink = null, ?SensitiveDataRedactor $redactor = null)
    {
        $this->sink = $sink ?? static function (array $payload): void {
            log_message('info', 'audit_event {payload}', ['payload' => json_encode($payload, JSON_THROW_ON_ERROR)]);
        };
        $this->redactor = $redactor ?? new SensitiveDataRedactor();
    }

    private SensitiveDataRedactor $redactor;

    /**
     * Record only generic audit metadata. Business event names and retention are
     * deliberately supplied by approved module policy later.
     *
     * @param array<string, mixed> $event
     * @return array<string, mixed>
     */
    public function record(array $event): array
    {
        foreach (['actor', 'action', 'entity_type', 'entity_id', 'timestamp', 'branch_scope'] as $required) {
            if (! array_key_exists($required, $event) || $event[$required] === '') {
                throw new InvalidArgumentException("Audit field '{$required}' is required.");
            }
        }

        $payload = $this->redactor->redact($event);
        ($this->sink)($payload);

        return $payload;
    }
}
