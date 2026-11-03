<?php

declare(strict_types=1);

namespace App\Services;

use App\Libraries\Security\SensitiveDataRedactor;
use Config\Database;
use RuntimeException;

final class AuditPersistenceService
{
    public function record(
        ?int $actor,
        string $action,
        string $type,
        string $id,
        ?int $branch,
        ?array $before = null,
        ?array $after = null,
        ?string $reason = null,
        ?object $db = null,
    ): void {
        $redactor = new SensitiveDataRedactor();
        $before = $before === null ? null : $redactor->redact($before);
        $after = $after === null ? null : $redactor->redact($after);

        $connection = $db ?? Database::connect('default');
        $inserted = $connection->table('audit_logs')->insert([
            'actor_user_id' => $actor,
            'action' => $action,
            'entity_type' => $type,
            'entity_id' => $id,
            'branch_id' => $branch,
            'before_summary' => $before ? json_encode($before, JSON_THROW_ON_ERROR) : null,
            'after_summary' => $after ? json_encode($after, JSON_THROW_ON_ERROR) : null,
            'reason' => $reason,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);

        if ($inserted !== true) {
            throw new RuntimeException('Unable to persist audit event.');
        }
    }
}
