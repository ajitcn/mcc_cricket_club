<?php

declare(strict_types=1);

namespace App\Services;

use Config\Database;
use RuntimeException;

final class ParentPortalService
{
    public function dashboard(): array
    {
        $userId = $this->actor();

        if (! service('authorization')->can('parent_portal.view')) {
            throw new RuntimeException('Forbidden');
        }

        $children = Database::connect('default')
            ->table('parent_player_links l')
            ->select('p.id, p.branch_id, p.status, pe.full_name, pe.date_of_birth, b.name AS branch_name')
            ->join('players p', 'p.id = l.player_id')
            ->join('persons pe', 'pe.id = p.person_id')
            ->join('branches b', 'b.id = p.branch_id')
            ->where('l.parent_user_id', $userId)
            ->orderBy('pe.full_name')
            ->get()
            ->getResultArray();

        return [
            'children' => $children,
            'visibility' => $this->visibility(),
        ];
    }

    public function child(int $playerId): array
    {
        $userId = $this->actor();
        $db = Database::connect('default');
        $child = $db->table('parent_player_links l')
            ->select('p.id, p.branch_id, p.status, pe.full_name, pe.date_of_birth, b.name AS branch_name')
            ->join('players p', 'p.id = l.player_id')
            ->join('persons pe', 'pe.id = p.person_id')
            ->join('branches b', 'b.id = p.branch_id')
            ->where(['l.parent_user_id' => $userId, 'p.id' => $playerId])
            ->get()
            ->getRowArray();

        if ($child === null) {
            throw new RuntimeException('Not found.');
        }

        return [
            'child' => $child,
            'visibility' => $this->visibility(),
        ];
    }

    private function visibility(): array
    {
        return [
            'attendance' => 'Not available',
            'performance' => 'Not available',
            'fitness' => 'Not available',
            'development' => 'Not available',
            'high_performance' => 'Not available',
            'documents' => 'Not available',
            'activity_history' => 'Not available',
        ];
    }

    private function actor(): int
    {
        $user = session('user');

        if (! is_array($user) || ! isset($user['id'])) {
            throw new RuntimeException('Authentication required');
        }

        return (int) $user['id'];
    }
}
