<?php

declare(strict_types=1);

namespace App\Services;

use Config\Database;
use RuntimeException;

final class NotificationService
{
    public function list(array $filters = []): array
    {
        $this->allow('notification.view');
        $userId = $this->actor();
        $builder = Database::connect('default')->table('notification_recipients nr')
            ->select('n.*, nr.id AS recipient_id, nr.read_at')
            ->join('notifications n', 'n.id = nr.notification_id')
            ->where('nr.recipient_user_id', $userId);
        if (in_array($filters['state'] ?? '', ['read', 'unread'], true)) {
            $filters['state'] === 'read'
                ? $builder->where('nr.read_at IS NOT NULL', null, false)
                : $builder->where('nr.read_at IS NULL', null, false);
        }
        if (($filters['category'] ?? '') !== '') {
            $builder->where('n.category', trim((string) $filters['category']));
        }
        return [
            'notifications' => $builder->orderBy('n.created_at', 'DESC')->get()->getResultArray(),
            'filters' => $filters,
        ];
    }

    public function create(array $input): int
    {
        $branch = (int) ($input['branch_id'] ?? 0);
        $this->allow('notification.create', $branch);

        return $this->persist($input, Database::connect('default'));
    }

    public function notifyAchievementAward(int $awardId, string $status, object $db): int
    {
        if (! in_array($status, ['published', 'revoked'], true)) {
            throw new RuntimeException('Invalid Achievement notification status.');
        }

        $award = $db->table('achievement_awards aa')
            ->select('aa.id, aa.branch_id, aa.player_id, aa.recipient_type, aa.status, a.title')
            ->join('achievements a', 'a.id = aa.achievement_id')
            ->join('players p', 'p.id = aa.player_id', 'left')
            ->join('branches b', 'b.id = aa.branch_id')
            ->where([
                'aa.id' => $awardId,
                'aa.status' => $status,
                'b.is_active' => 1,
            ])
            ->where('p.status', 'active')
            ->get()
            ->getRowArray();

        if (! is_array($award) || $award['recipient_type'] !== 'player') {
            return 0;
        }

        $branchId = (int) $award['branch_id'];
        $permission = $status === 'published' ? 'achievement.publish' : 'achievement.revoke';
        $this->allow($permission, $branchId);
        $recipients = $this->achievementRecipients($db, (int) $award['player_id'], $branchId);
        if ($recipients === []) {
            return 0;
        }

        $event = 'achievement_award_' . $status;
        $existing = $db->table('notification_recipients nr')
            ->join('notifications n', 'n.id = nr.notification_id')
            ->where([
                'n.source_type' => $event,
                'n.source_id' => (string) $awardId,
            ])
            ->whereIn('nr.recipient_user_id', $recipients)
            ->countAllResults();
        if ($existing === count($recipients)) {
            return 0;
        }

        $verb = $status === 'published' ? 'published' : 'revoked';
        $notificationId = $this->persist([
            'branch_id' => $branchId,
            'title' => 'Achievement ' . ucfirst($verb),
            'body' => 'Your achievement "' . trim((string) $award['title']) . '" has been ' . $verb . '.',
            'category' => 'achievement',
            'recipient_user_ids' => $recipients,
            'source_type' => $event,
            'source_id' => (string) $awardId,
        ], $db);

        return $notificationId;
    }

    /** @param array<string, mixed> $input */
    private function persist(array $input, object $db): int
    {
        $branch = (int) ($input['branch_id'] ?? 0);
        $title = trim((string) ($input['title'] ?? ''));
        $body = trim((string) ($input['body'] ?? ''));
        $category = trim((string) ($input['category'] ?? ''));
        $recipients = array_values(array_unique(array_filter(array_map('intval', (array) ($input['recipient_user_ids'] ?? [])))));
        if ($title === '' || strlen($title) > 180 || $body === '' || $category === '' || $recipients === []) {
            throw new RuntimeException('Invalid notification.');
        }
        foreach ($recipients as $recipient) {
            if ($db->table('user_branches')->where(['user_id' => $recipient, 'branch_id' => $branch])->countAllResults() === 0) {
                throw new RuntimeException('Invalid notification recipient.');
            }
        }
        $now = gmdate('Y-m-d H:i:s');
        $db->transStart();
        $db->table('notifications')->insert([
            'branch_id' => $branch,
            'title' => $title,
            'body' => $body,
            'category' => $category,
            'source_type' => ($input['source_type'] ?? '') ?: null,
            'source_id' => ($input['source_id'] ?? '') ?: null,
            'created_by' => $this->actor(),
            'created_at' => $now,
        ]);
        $id = (int) $db->insertID();
        foreach ($recipients as $recipient) {
            $db->table('notification_recipients')->insert([
                'notification_id' => $id,
                'recipient_user_id' => $recipient,
                'created_at' => $now,
            ]);
        }
        $db->table('notification_history')->insert([
            'notification_id' => $id,
            'action' => 'created',
            'actor_user_id' => $this->actor(),
            'metadata' => '{}',
            'created_at' => $now,
        ]);
        $db->transComplete();
        if (! $db->transStatus()) {
            throw new RuntimeException('Unable to create notification.');
        }
        return $id;
    }

    /** @return list<int> */
    private function achievementRecipients(object $db, int $playerId, int $branchId): array
    {
        $student = $db->table('player_user_links pul')
            ->select('pul.user_id')
            ->join('players p', 'p.id = pul.player_id')
            ->join('users u', 'u.id = pul.user_id')
            ->join('user_branches ub', 'ub.user_id = pul.user_id AND ub.branch_id = pul.branch_id')
            ->join('user_roles ur', 'ur.user_id = pul.user_id')
            ->join('roles r', 'r.id = ur.role_id')
            ->where([
                'pul.player_id' => $playerId,
                'pul.branch_id' => $branchId,
                'p.branch_id' => $branchId,
                'p.status' => 'active',
                'u.is_enabled' => 1,
                'r.name' => 'Player',
            ])
            ->get()
            ->getResultArray();
        $parents = $db->table('parent_player_links ppl')
            ->select('ppl.parent_user_id AS user_id')
            ->join('players p', 'p.id = ppl.player_id')
            ->join('users u', 'u.id = ppl.parent_user_id')
            ->join('user_branches ub', 'ub.user_id = ppl.parent_user_id AND ub.branch_id = p.branch_id')
            ->join('user_roles ur', 'ur.user_id = ppl.parent_user_id')
            ->join('roles r', 'r.id = ur.role_id')
            ->where([
                'ppl.player_id' => $playerId,
                'p.branch_id' => $branchId,
                'p.status' => 'active',
                'u.is_enabled' => 1,
                'r.name' => 'Parent',
                'ppl.relationship_type' => 'guardian',
            ])
            ->get()
            ->getResultArray();

        return array_values(array_unique(array_map(
            static fn (array $row): int => (int) ($row['user_id'] ?? 0),
            array_merge($student, $parents),
        )));
    }

    public function markRead(int $recipientId): void
    {
        $this->allow('notification.read');
        $db = Database::connect('default');
        $row = $db->table('notification_recipients')->where([
            'id' => $recipientId,
            'recipient_user_id' => $this->actor(),
        ])->get()->getRowArray();
        if ($row === null) {
            throw new RuntimeException('Not found.');
        }
        $db->table('notification_recipients')->where('id', $recipientId)->update(['read_at' => gmdate('Y-m-d H:i:s')]);
    }

    public function history(int $id): array
    {
        $this->allow('notification.view');
        $db = Database::connect('default');
        $allowed = $db->table('notification_recipients')->where(['notification_id' => $id, 'recipient_user_id' => $this->actor()])->countAllResults();
        if ($allowed === 0) {
            throw new RuntimeException('Not found.');
        }
        return $db->table('notification_history')->where('notification_id', $id)->orderBy('created_at', 'DESC')->get()->getResultArray();
    }

    private function allow(string $permission, ?int $branch = null): void
    {
        if (! service('authorization')->can($permission, $branch === null ? null : (string) $branch)) {
            throw new RuntimeException('Forbidden');
        }
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
