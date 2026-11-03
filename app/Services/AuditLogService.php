<?php

declare(strict_types=1);

namespace App\Services;

use Config\Database;
use RuntimeException;

final class AuditLogService
{
    /** @return list<array<string, mixed>> */
    public function listForAuthorizedScope(array $filters = []): array
    {
        if (! service('authorization')->can('audit.view')) {
            throw new RuntimeException('Forbidden');
        }

        $branches = array_values(array_filter(array_map('intval', (array) session('authorizedBranches'))));
        $query = Database::connect('default')->table('audit_logs')
            ->select('audit_logs.id,audit_logs.actor_user_id,audit_logs.action,audit_logs.entity_type,audit_logs.entity_id,audit_logs.branch_id,audit_logs.before_summary,audit_logs.after_summary,audit_logs.reason,audit_logs.created_at')
            ->orderBy('audit_logs.id', 'DESC');

        if ($branches === []) {
            $query->where('audit_logs.branch_id', null);
        } else {
            $query->groupStart()->where('audit_logs.branch_id', null)->orWhereIn('audit_logs.branch_id', $branches)->groupEnd();
        }

        $branch = trim((string) ($filters['branch_id'] ?? ''));
        if ($branch !== '') {
            if (! ctype_digit($branch) || ! service('authorization')->can('audit.view', $branch)) {
                throw new RuntimeException('Forbidden');
            }
            $query->where('audit_logs.branch_id', (int) $branch);
        }

        $rows = $query->get()->getResultArray();
        $actor = session('user');
        service('auditPersistence')->record(
            is_array($actor) && isset($actor['id']) ? (int) $actor['id'] : null,
            'audit_log_read',
            'audit_log',
            'collection',
            $branch === '' ? null : (int) $branch,
            null,
            ['branch_id' => $branch === '' ? null : (int) $branch, 'record_count' => count($rows)],
        );

        return $rows;
    }
}
