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
            ->select('audit_logs.id,audit_logs.actor_user_id,audit_logs.action,audit_logs.entity_type,audit_logs.entity_id,audit_logs.branch_id,audit_logs.before_summary,audit_logs.after_summary,audit_logs.reason,audit_logs.created_at, branches.name AS branch_name, actor.display_name AS actor_name, actor.email AS actor_email')
            ->join('branches', 'branches.id = audit_logs.branch_id', 'left')
            ->join('users actor', 'actor.id = audit_logs.actor_user_id', 'left')
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

        foreach ($rows as &$row) {
            $row['entity_label'] = $this->entityLabel(
                (string) ($row['entity_type'] ?? ''),
                (string) ($row['entity_id'] ?? ''),
            );
            $row['branch_label'] = (string) ($row['branch_name'] ?? 'Global');
            $row['actor_label'] = (string) ($row['actor_name'] ?? $row['actor_email'] ?? 'System');
        }
        unset($row);

        return $rows;
    }

    private function entityLabel(string $type, string $id): string
    {
        if ($id === '') {
            return ucfirst(str_replace('_', ' ', $type));
        }

        $definitions = [
            'user' => ['table' => 'users', 'select' => 'display_name, email'],
            'branch' => ['table' => 'branches', 'select' => 'name'],
            'player' => ['table' => 'players p', 'select' => 'pe.full_name, p.player_number', 'join' => ['persons pe', 'pe.id = p.person_id'], 'where' => 'p.id'],
            'admission' => ['table' => 'admissions', 'select' => 'admission_number'],
            'lead' => ['table' => 'leads', 'select' => 'full_name, email'],
            'program' => ['table' => 'programs', 'select' => 'name'],
            'batch' => ['table' => 'batches', 'select' => 'name'],
            'coach' => ['table' => 'users', 'select' => 'display_name, email'],
            'notification' => ['table' => 'notifications', 'select' => 'title'],
            'achievement' => ['table' => 'achievements', 'select' => 'title'],
            'match' => ['table' => 'matches', 'select' => 'match_number, opponent'],
            'tournament' => ['table' => 'tournaments', 'select' => 'name'],
        ];
        $definition = $definitions[$type] ?? null;
        if ($definition === null) {
            return ucfirst(str_replace('_', ' ', $type)) . ' #' . $id;
        }

        try {
            $db = Database::connect('default');
            $query = $db->table($definition['table'])->select($definition['select']);
            if (isset($definition['join'])) {
                $query->join($definition['join'][0], $definition['join'][1], 'left');
            }
            $row = $query->where($definition['where'] ?? 'id', $id)->get()->getRowArray();
            if (! is_array($row)) {
                return ucfirst(str_replace('_', ' ', $type)) . ' #' . $id;
            }
            foreach (['display_name', 'name', 'full_name', 'title', 'admission_number', 'match_number', 'player_number'] as $field) {
                if (trim((string) ($row[$field] ?? '')) !== '') {
                    return (string) $row[$field];
                }
            }
        } catch (\Throwable) {
            // Preserve the audit row if an optional entity table is unavailable.
        }

        return ucfirst(str_replace('_', ' ', $type)) . ' #' . $id;
    }
}
