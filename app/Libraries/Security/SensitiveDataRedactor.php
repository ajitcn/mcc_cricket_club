<?php

namespace App\Libraries\Security;

final class SensitiveDataRedactor
{
    /** @var list<string> */
    private const SENSITIVE_KEYS = [
        'password', 'passphrase', 'token', 'secret', 'credential', 'signed_url',
        'medical', 'aadhaar', 'passport', 'visa', 'document_number',
        'document_content', 'private_contact',
    ];

    /**
     * @param mixed $value
     * @return mixed
     */
    public function redact(mixed $value, ?string $key = null): mixed
    {
        if ($key !== null && $this->isSensitiveKey($key)) {
            return '[REDACTED]';
        }

        if (is_array($value)) {
            $redacted = [];
            foreach ($value as $childKey => $childValue) {
                $redacted[$childKey] = $this->redact($childValue, (string) $childKey);
            }

            return $redacted;
        }

        if (is_object($value)) {
            return '[REDACTED_OBJECT]';
        }

        return $value;
    }

    private function isSensitiveKey(string $key): bool
    {
        $normalized = strtolower(preg_replace('/[^a-z0-9]+/', '_', $key) ?? $key);

        foreach (self::SENSITIVE_KEYS as $sensitiveKey) {
            if ($normalized === $sensitiveKey || str_contains($normalized, $sensitiveKey)) {
                return true;
            }
        }

        return false;
    }
}
