<?php

declare(strict_types=1);

namespace App\Services;

use App\Modules\Achievements\Models\AchievementModel;
use App\Modules\Admin\Models\BranchModel;
use Config\Database;
use RuntimeException;
use Throwable;

/**
 * Bounded Achievement catalog foundation.
 *
 * USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL. Approval, publication,
 * revocation, and published corrections are limited to the recorded lifecycle
 * defaults. Achievement notifications are limited to in-app published and
 * revoked events for authorized Player and Parent recipients.
 */
final class AchievementManagementService
{
    /** @return list<array<string, mixed>> */
    public function list(string $permission = 'achievement.view', array $filters = []): array
    {
        $branchIds = $this->authorizedBranchIds($permission);
        if ($branchIds === []) {
            return [];
        }

        $query = Database::connect('default')
            ->table('achievements a')
            ->select('a.*, b.name AS branch_name')
            ->join('branches b', 'b.id = a.branch_id')
            ->whereIn('a.branch_id', $branchIds);

        if (($filters['branch_id'] ?? '') !== '') {
            $query->where('a.branch_id', (int) $filters['branch_id']);
        }

        if (trim((string) ($filters['category'] ?? '')) !== '') {
            $query->where('a.category', trim((string) $filters['category']));
        }

        if (($filters['status'] ?? '') === 'active') {
            $query->where('a.is_active', 1);
        } elseif (($filters['status'] ?? '') === 'inactive') {
            $query->where('a.is_active', 0);
        }

        return $query
            ->orderBy('a.title', 'ASC')
            ->orderBy('a.id', 'DESC')
            ->get()
            ->getResultArray();
    }

    /** @return list<array<string, mixed>> */
    public function availableBranches(string $permission = 'achievement.create'): array
    {
        $branchIds = $this->authorizedBranchIds($permission);
        if ($branchIds === []) {
            return [];
        }

        return (new BranchModel())
            ->whereIn('id', $branchIds)
            ->orderBy('name', 'ASC')
            ->findAll();
    }

    /** @param array<string, mixed> $input */
    public function create(array $input): int
    {
        $values = $this->validated($input);
        $this->allow($values['branch_id'], 'achievement.create');
        $actor = $this->actorId();
        $db = Database::connect('default');
        $db->transBegin();

        try {
            $duplicate = $db->table('achievements')
                ->where(['branch_id' => $values['branch_id'], 'title' => $values['title']])
                ->countAllResults();
            if ($duplicate > 0) {
                throw new RuntimeException('Achievement title already exists in this branch.');
            }

            $now = gmdate('Y-m-d H:i:s');
            $id = (new AchievementModel())->insert([
                'branch_id' => $values['branch_id'],
                'category' => $values['category'],
                'title' => $values['title'],
                'description' => $values['description'],
                'is_active' => $values['is_active'],
                'created_by' => $actor,
                'created_at' => $now,
                'updated_at' => $now,
            ], true);
            if (! is_numeric($id) || (int) $id < 1) {
                throw new RuntimeException('Unable to save Achievement.');
            }

            service('auditPersistence')->record(
                $actor,
                'achievement_created',
                'achievement',
                (string) $id,
                $values['branch_id'],
                null,
                $values,
                null,
                $db,
            );
            $db->transCommit();

            return (int) $id;
        } catch (Throwable $exception) {
            $db->transRollback();
            throw $exception;
        }
    }

    /** @return array<string, mixed> */
    public function findForEdit(int $id): array
    {
        $permission = $this->achievementManagementPermission();
        if ($id < 1 || $permission === null) {
            throw new RuntimeException('Forbidden');
        }

        $branchIds = $this->authorizedBranchIds($permission);
        $achievement = Database::connect('default')
            ->table('achievements a')
            ->select('a.*, b.name AS branch_name')
            ->join('branches b', 'b.id = a.branch_id')
            ->where('a.id', $id)
            ->whereIn('a.branch_id', $branchIds)
            ->get()
            ->getRowArray();

        if (! is_array($achievement)) {
            throw new RuntimeException('Achievement not found.');
        }

        return $achievement;
    }

    /** @return array<string, mixed> */
    public function findForView(int $id): array
    {
        if ($id < 1) {
            throw new RuntimeException('Achievement not found.');
        }

        $branchIds = $this->authorizedBranchIds('achievement.view');
        $achievement = Database::connect('default')
            ->table('achievements a')
            ->select('a.*, b.name AS branch_name')
            ->join('branches b', 'b.id = a.branch_id')
            ->where('a.id', $id)
            ->whereIn('a.branch_id', $branchIds)
            ->get()
            ->getRowArray();

        if (! is_array($achievement)) {
            throw new RuntimeException('Achievement not found.');
        }

        return $achievement;
    }

    /** @param array<string, mixed> $input */
    public function update(int $id, array $input): void
    {
        $values = $this->validated($input);
        $permission = $this->achievementManagementPermission();
        if ($permission === null) {
            throw new RuntimeException('Forbidden');
        }
        $this->allow($values['branch_id'], $permission);
        $existing = $this->findForEdit($id);
        $db = Database::connect('default');
        $duplicate = $db->table('achievements')
            ->where('branch_id', $values['branch_id'])
            ->where('title', $values['title'])
            ->where('id !=', $id)
            ->countAllResults();
        if ($duplicate > 0) {
            throw new RuntimeException('Achievement title already exists in this branch.');
        }

        $now = gmdate('Y-m-d H:i:s');
        $db->transBegin();
        try {
            $updated = (new AchievementModel())->update($id, [
                'branch_id' => $values['branch_id'],
                'category' => $values['category'],
                'title' => $values['title'],
                'description' => $values['description'],
                'is_active' => $values['is_active'],
                'updated_at' => $now,
            ]);
            if ($updated !== true) {
                throw new RuntimeException('Unable to update Achievement.');
            }

            service('auditPersistence')->record(
                $this->actorId(),
                'achievement_updated',
                'achievement',
                (string) $id,
                $values['branch_id'],
                $existing,
                $values,
                null,
                $db,
            );
            $db->transCommit();
        } catch (Throwable $exception) {
            $db->transRollback();
            throw $exception;
        }
    }

    /** @return list<array<string, mixed>> */
    public function awardList(bool $includeArchived = false, array $filters = []): array
    {
        $filters += [
            'search' => '',
            'category' => '',
            'recipient_type' => '',
            'status' => '',
            'branch_id' => '',
            'date_from' => '',
            'date_to' => '',
        ];
        $branchIds = $this->authorizedBranchIds('achievement.view');
        if ($branchIds === []) {
            return [];
        }

        $builder = Database::connect('default')->table('achievement_awards aa')
            ->select('aa.*, a.title AS achievement_title, a.category, pe.full_name AS player_name, cu.display_name AS coach_name, b.name AS branch_name')
            ->join('achievements a', 'a.id = aa.achievement_id')
            ->join('players p', 'p.id = aa.player_id', 'left')
            ->join('persons pe', 'pe.id = p.person_id', 'left')
            ->join('users cu', 'cu.id = aa.coach_id', 'left')
            ->join('branches b', 'b.id = aa.branch_id')
            ->join('achievement_certificates ac', 'ac.award_id = aa.id', 'left')
            ->select('ac.id AS certificate_id')
            ->whereIn('aa.branch_id', $branchIds);

        if (! $includeArchived) {
            $builder->where('aa.is_archived', 0);
        } else {
            $this->allowHistoricalAccess($branchIds);
        }

        if ($filters['branch_id'] !== '' && ctype_digit((string) $filters['branch_id'])) {
            $builder->where('aa.branch_id', (int) $filters['branch_id']);
        }
        if ($filters['category'] !== '') {
            $builder->where('a.category', (string) $filters['category']);
        }
        if (in_array($filters['recipient_type'], ['player', 'coach'], true)) {
            $builder->where('aa.recipient_type', (string) $filters['recipient_type']);
        }
        if (in_array($filters['status'], ['draft', 'approved', 'published', 'revoked'], true)) {
            $builder->where('aa.status', (string) $filters['status']);
        }
        if ($filters['search'] !== '') {
            $search = (string) $filters['search'];
            $builder->groupStart()
                ->like('a.title', $search)
                ->orLike('pe.full_name', $search)
                ->orLike('cu.display_name', $search)
                ->orLike('b.name', $search)
                ->groupEnd();
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $filters['date_from']) === 1) {
            $builder->where('aa.created_at >=', $filters['date_from'] . ' 00:00:00');
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $filters['date_to']) === 1) {
            $builder->where('aa.created_at <=', $filters['date_to'] . ' 23:59:59');
        }

        return $builder
            ->orderBy('aa.id', 'DESC')
            ->get()
            ->getResultArray();
    }

    /** @return list<string> */
    public function awardCategories(bool $includeArchived = false): array
    {
        $awards = $this->awardList($includeArchived);
        $categories = array_map(
            static fn (array $award): string => (string) ($award['category'] ?? ''),
            $awards,
        );
        $categories = array_values(array_filter(array_unique($categories)));
        sort($categories);

        return $categories;
    }

    /** @return list<array<string, mixed>> */
    public function activeAchievements(): array
    {
        return array_values(array_filter(
            $this->list('achievement.create'),
            static fn (array $achievement): bool => (int) $achievement['is_active'] === 1,
        ));
    }

    /** @return list<array<string, mixed>> */
    public function recipients(int $branchId, string $type, string $permission = 'achievement.create'): array
    {
        $this->allow($branchId, $permission);
        $db = Database::connect('default');
        if ($type === 'player') {
            return $db->table('players p')
                ->select('p.id, pe.full_name')
                ->join('persons pe', 'pe.id = p.person_id')
                ->where('p.branch_id', $branchId)
                ->where('p.status', 'active')
                ->orderBy('pe.full_name')
                ->get()
                ->getResultArray();
        }
        if ($type === 'coach') {
            return $db->table('coach_profiles cp')
                ->select('cp.user_id AS id, u.display_name AS full_name')
                ->join('users u', 'u.id = cp.user_id')
                ->join(
                    'user_branches ub',
                    'ub.user_id = cp.user_id AND ub.branch_id = ' . $db->escape($branchId),
                )
                ->where('cp.is_active', 1)
                ->where('u.is_enabled', 1)
                ->orderBy('u.display_name')
                ->get()
                ->getResultArray();
        }

        throw new RuntimeException('Select a valid recipient type.');
    }

    /** @return array{players: list<array<string, mixed>>, coaches: list<array<string, mixed>>} */
    public function recipientOptions(string $permission = 'achievement.create'): array
    {
        $branchIds = $this->authorizedBranchIds($permission);
        if ($branchIds === []) {
            return ['players' => [], 'coaches' => []];
        }

        $db = Database::connect('default');

        return [
            'players' => $db->table('players p')
                ->select('p.id, pe.full_name, p.branch_id')
                ->join('persons pe', 'pe.id = p.person_id')
                ->whereIn('p.branch_id', $branchIds)
                ->where('p.status', 'active')
                ->orderBy('pe.full_name')
                ->get()
                ->getResultArray(),
            'coaches' => $db->table('coach_profiles cp')
                ->select('cp.user_id AS id, u.display_name AS full_name, ub.branch_id')
                ->join('users u', 'u.id = cp.user_id')
                ->join('user_branches ub', 'ub.user_id = cp.user_id')
                ->whereIn('ub.branch_id', $branchIds)
                ->where('cp.is_active', 1)
                ->where('u.is_enabled', 1)
                ->distinct()
                ->orderBy('u.display_name')
                ->get()
                ->getResultArray(),
        ];
    }

    /** @param array<string, mixed> $input */
    public function createAward(array $input): int
    {
        $values = $this->validatedAward($input, false);
        $this->allow($values['branch_id'], 'achievement.create');
        $actor = $this->actorId();
        $db = Database::connect('default');
        $db->transBegin();
        try {
            $this->assertAchievement($db, $values['achievement_id'], $values['branch_id']);
            $this->assertRecipient(
                $db,
                $values['recipient_type'],
                $values['recipient_id'],
                $values['branch_id'],
            );
            $data = $this->awardData($values, $actor, $actor, null, 'approved');
            $id = $db->table('achievement_awards')->insert($data, true)
                ? (int) $db->insertID()
                : 0;
            if ($id < 1) {
                throw new RuntimeException('Unable to save Achievement award.');
            }
            service('auditPersistence')->record(
                $actor,
                'achievement_award_created',
                'achievement_award',
                (string) $id,
                $values['branch_id'],
                null,
                $data,
                null,
                $db,
            );
            $db->transCommit();
            return $id;
        } catch (Throwable $exception) {
            $db->transRollback();
            throw $exception;
        }
    }

    /** @return array<string, mixed> */
    public function award(int $id): array
    {
        $record = $this->awardQuery()->where('aa.id', $id)->get()->getRowArray();
        if (! is_array($record) || ! in_array((int) $record['branch_id'], $this->authorizedBranchIds('achievement.view'), true)) {
            throw new RuntimeException('Not found.');
        }
        return $record;
    }

    /** @param array<string, mixed> $input */
    public function updateAward(int $id, array $input): void
    {
        $values = $this->validatedAward($input, true);
        $permission = $this->achievementManagementPermission();
        if ($permission === null) {
            throw new RuntimeException('Forbidden');
        }
        $this->allow($values['branch_id'], $permission);
        $before = $this->award($id);
        if (! in_array((string) $before['status'], ['draft', 'approved'], true) || (int) $before['branch_id'] !== $values['branch_id']) {
            throw new RuntimeException('Only same-branch Draft or Approved awards can be edited.');
        }
        $db = Database::connect('default');
        $db->transBegin();
        try {
            $this->assertAchievement($db, $values['achievement_id'], $values['branch_id']);
            $this->assertRecipient(
                $db,
                $values['recipient_type'],
                $values['recipient_id'],
                $values['branch_id'],
            );
            $actor = $this->actorId();
            $data = $this->awardData(
                $values,
                $actor,
                $actor,
                $values['correction_reason'],
                (string) $before['status'],
            );
            $version = (int) ($input['version'] ?? 0);
            $this->updateWithVersion($db, $id, (string) $before['status'], $values['branch_id'], $version, $data);
            service('auditPersistence')->record(
                $actor,
                'achievement_award_draft_updated',
                'achievement_award',
                (string) $id,
                $values['branch_id'],
                $before,
                $data,
                $values['correction_reason'],
                $db,
            );
            $db->transCommit();
        } catch (Throwable $exception) {
            $db->transRollback();
            throw $exception;
        }
    }

    public function deleteAward(int $id): void
    {
        $permission = $this->achievementManagementPermission();
        if ($permission === null) {
            throw new RuntimeException('Forbidden');
        }
        $this->allowAwardPermission($id, $permission);
        $before = $this->award($id);
        $db = Database::connect('default');
        $db->transBegin();

        try {
            if ($db->table('achievement_awards')->where('id', $id)->delete() !== true) {
                throw new RuntimeException('Unable to delete Achievement award.');
            }

            service('auditPersistence')->record(
                $this->actorId(),
                'achievement_award_deleted',
                'achievement_award',
                (string) $id,
                (int) $before['branch_id'],
                $before,
                null,
                'Permanent award deletion',
                $db,
            );
            $db->transCommit();
        } catch (Throwable $exception) {
            $db->transRollback();
            throw $exception;
        }
    }

    /** @param array<string, mixed> $input */
    public function approveAward(int $id, array $input): void
    {
        $this->transitionAward($id, 'draft', 'approved', 'achievement.approve', $input, 'achievement_award_approved');
    }

    /** @param array<string, mixed> $input */
    public function publishAward(int $id, array $input): void
    {
        $this->transitionAward($id, 'approved', 'published', 'achievement.publish', $input, 'achievement_award_published');
    }

    /** @param array<string, mixed> $input */
    public function revokeAward(int $id, array $input): void
    {
        $this->transitionAward($id, 'published', 'revoked', 'achievement.revoke', $input, 'achievement_award_revoked');
    }

    /** @param array<string, mixed> $input */
    public function correctPublishedAward(int $id, array $input): void
    {
        $before = $this->award($id);
        if ((string) $before['status'] !== 'published' || (int) $before['is_archived'] === 1) {
            throw new RuntimeException('Only published awards can be corrected.');
        }

        $reason = trim((string) ($input['correction_reason'] ?? ''));
        if ($reason === '') {
            throw new RuntimeException('Correction reason required.');
        }

        $branchId = (int) $before['branch_id'];
        $this->allow($branchId, 'achievement.update');
        $values = $this->validatedAward([
            'achievement_id' => $before['achievement_id'],
            'branch_id' => $branchId,
            'recipient_type' => $before['recipient_type'],
            'recipient_id' => $before['recipient_type'] === 'player'
                ? $before['player_id']
                : $before['coach_id'],
            'nomination_note' => $input['nomination_note'] ?? $before['nomination_note'],
            'correction_reason' => $reason,
        ], true);
        $db = Database::connect('default');
        $db->transBegin();
        try {
            $this->assertAchievement($db, $values['achievement_id'], $branchId);
            $this->assertRecipient($db, $values['recipient_type'], $values['recipient_id'], $branchId);
            $actor = $this->actorId();
            $data = [
                'nomination_note' => $values['nomination_note'],
                'updated_by' => $actor,
                'updated_at' => gmdate('Y-m-d H:i:s'),
                'correction_reason' => $reason,
            ];
            $this->updateWithVersion(
                $db,
                $id,
                'published',
                $branchId,
                (int) ($input['version'] ?? 0),
                $data,
            );
            if (in_array($to, ['published', 'revoked'], true)) {
                service('notification')->notifyAchievementAward($id, $to, $db);
            }
            service('auditPersistence')->record(
                $actor,
                'achievement_award_published_corrected',
                'achievement_award',
                (string) $id,
                $branchId,
                $before,
                $data,
                $reason,
                $db,
            );
            $db->transCommit();
        } catch (Throwable $exception) {
            $db->transRollback();
            throw $exception;
        }
    }

    /** @param array<string, mixed> $input */
    public function archiveAward(int $id, array $input): void
    {
        $this->changeArchiveState($id, true, $input);
    }

    /** @param array<string, mixed> $input */
    public function restoreAward(int $id, array $input): void
    {
        $this->changeArchiveState($id, false, $input);
    }

    /** @param array<string, mixed> $input */
    private function changeArchiveState(int $id, bool $archive, array $input): void
    {
        $before = $this->award($id);
        $status = (string) $before['status'];
        $currentlyArchived = (int) $before['is_archived'] === 1;
        if (! in_array($status, ['published', 'revoked'], true) || $currentlyArchived === $archive) {
            throw new RuntimeException('Invalid Achievement archival state change.');
        }

        $branchId = (int) $before['branch_id'];
        $permission = $archive ? 'achievement.archive' : 'achievement.restore';
        $this->allow($branchId, $permission);
        $reason = trim((string) ($input['reason'] ?? ''));
        if ($reason === '') {
            throw new RuntimeException('An archival reason is required.');
        }

        $actor = $this->actorId();
        $now = gmdate('Y-m-d H:i:s');
        $data = $archive
            ? [
                'is_archived' => 1,
                'archived_by' => $actor,
                'archived_at' => $now,
                'archive_reason' => $reason,
            ]
            : [
                'is_archived' => 0,
                'restored_by' => $actor,
                'restored_at' => $now,
                'restore_reason' => $reason,
            ];
        $event = $archive ? 'achievement_award_archived' : 'achievement_award_restored';
        $db = Database::connect('default');
        $db->transBegin();

        try {
            $this->updateWithVersion($db, $id, $status, $branchId, (int) ($input['version'] ?? 0), $data);
            service('auditPersistence')->record(
                $actor,
                $event,
                'achievement_award',
                (string) $id,
                $branchId,
                $before,
                $data,
                $reason,
                $db,
            );
            $db->transCommit();
        } catch (Throwable $exception) {
            $db->transRollback();
            throw $exception;
        }
    }

    /** @param array<string, mixed> $input */
    private function transitionAward(
        int $id,
        string $from,
        string $to,
        string $permission,
        array $input,
        string $event,
    ): void {
        $before = $this->award($id);
        if ((string) $before['status'] !== $from) {
            throw new RuntimeException('Invalid Achievement award status transition.');
        }

        $branchId = (int) $before['branch_id'];
        $this->allow($branchId, $permission);
        $reason = trim((string) ($input['reason'] ?? ''));
        if ($reason === '') {
            throw new RuntimeException('A reason is required for this lifecycle change.');
        }

        $db = Database::connect('default');
        $db->transBegin();
        try {
            $actor = $this->actorId();
            $now = gmdate('Y-m-d H:i:s');
            $data = [
                'status' => $to,
                'updated_by' => $actor,
                'updated_at' => $now,
                'lifecycle_reason' => $reason,
            ];
            if ($to === 'approved') {
                $data['approved_by'] = $actor;
                $data['approved_at'] = $now;
            } elseif ($to === 'published') {
                $data['published_by'] = $actor;
                $data['published_at'] = $now;
            } else {
                $data['revoked_by'] = $actor;
                $data['revoked_at'] = $now;
            }
            $this->updateWithVersion(
                $db,
                $id,
                $from,
                $branchId,
                (int) ($input['version'] ?? 0),
                $data,
            );
            service('auditPersistence')->record(
                $actor,
                $event,
                'achievement_award',
                (string) $id,
                $branchId,
                $before,
                $data,
                $reason,
                $db,
            );
            $db->transCommit();
        } catch (Throwable $exception) {
            $db->transRollback();
            throw $exception;
        }
    }

    private function updateWithVersion(
        object $db,
        int $id,
        string $status,
        int $branchId,
        int $version,
        array $data,
    ): void {
        if ($version < 1) {
            throw new RuntimeException('The award version is required. Reload the latest award.');
        }

        $updated = $db->table('achievement_awards')
            ->where([
                'id' => $id,
                'status' => $status,
                'branch_id' => $branchId,
                'version' => $version,
            ])
            ->set('version', 'version + 1', false)
            ->update($data);
        if ($updated !== true || $db->affectedRows() !== 1) {
            throw new RuntimeException('This Achievement award changed in another session. Reload and try again.');
        }
    }

    private function awardQuery(): object
    {
        return Database::connect('default')->table('achievement_awards aa')
            ->select('aa.*, a.title AS achievement_title, a.category, pe.full_name AS player_name, cu.display_name AS coach_name, b.name AS branch_name, ac.id AS certificate_id')
            ->join('achievements a', 'a.id = aa.achievement_id')
            ->join('players p', 'p.id = aa.player_id', 'left')
            ->join('persons pe', 'pe.id = p.person_id', 'left')
            ->join('users cu', 'cu.id = aa.coach_id', 'left')
            ->join('branches b', 'b.id = aa.branch_id')
            ->join('achievement_certificates ac', 'ac.award_id = aa.id', 'left');
    }

    /** @return array<string, mixed> */
    private function validatedAward(array $input, bool $editing): array
    {
        $achievementId = filter_var($input['achievement_id'] ?? null, FILTER_VALIDATE_INT);
        $branchId = filter_var($input['branch_id'] ?? null, FILTER_VALIDATE_INT);
        $type = (string) ($input['recipient_type'] ?? '');
        $recipientId = filter_var($input['recipient_id'] ?? null, FILTER_VALIDATE_INT);
        $note = trim((string) ($input['nomination_note'] ?? ''));
        $reason = trim((string) ($input['correction_reason'] ?? ''));
        if (
            $achievementId === false
            || $branchId === false
            || $recipientId === false
            || $branchId < 1
            || $achievementId < 1
            || $recipientId < 1
        ) {
            throw new RuntimeException('Enter valid Achievement and recipient details.');
        }
        if (! in_array($type, ['player', 'coach'], true)) {
            throw new RuntimeException('Select a valid recipient type.');
        }
        if (mb_strlen($note, 'UTF-8') > 2000) {
            throw new RuntimeException('Enter a shorter nomination note.');
        }
        if ($editing && $reason === '') {
            throw new RuntimeException('Correction reason required.');
        }
        return [
            'achievement_id' => $achievementId,
            'branch_id' => $branchId,
            'recipient_type' => $type,
            'recipient_id' => $recipientId,
            'nomination_note' => $note === '' ? null : $note,
            'correction_reason' => $reason === '' ? null : $reason,
        ];
    }

    private function assertAchievement(object $db, int $id, int $branchId): void
    {
        if ($db->table('achievements')->where(['id' => $id, 'branch_id' => $branchId, 'is_active' => 1])->countAllResults() !== 1) {
            throw new RuntimeException('Achievement is not active for this branch.');
        }
    }

    private function assertRecipient(object $db, string $type, int $id, int $branchId): void
    {
        if ($type === 'player') {
            $count = $db->table('players')
                ->where([
                    'id' => $id,
                    'branch_id' => $branchId,
                    'status' => 'active',
                ])
                ->countAllResults();
        } else {
            $count = $db->table('coach_profiles cp')
                ->join('user_branches ub', 'ub.user_id = cp.user_id')
                ->where([
                    'cp.user_id' => $id,
                    'ub.branch_id' => $branchId,
                    'cp.is_active' => 1,
                ])
                ->countAllResults();
        }
        if ($count !== 1) {
            throw new RuntimeException('Recipient is not active in this branch.');
        }
    }

    private function awardData(
        array $values,
        int $createdBy,
        int $updatedBy,
        ?string $reason,
        string $status,
    ): array
    {
        return [
            'achievement_id' => $values['achievement_id'],
            'branch_id' => $values['branch_id'],
            'recipient_type' => $values['recipient_type'],
            'player_id' => $values['recipient_type'] === 'player'
                ? $values['recipient_id']
                : null,
            'coach_id' => $values['recipient_type'] === 'coach'
                ? $values['recipient_id']
                : null,
            'status' => $status,
            'nomination_note' => $values['nomination_note'],
            'created_by' => $createdBy,
            'updated_by' => $updatedBy,
            'correction_reason' => $reason,
            'created_at' => gmdate('Y-m-d H:i:s'),
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ];
    }

    /** @return array{branch_id: int, category: string, title: string, description: ?string, is_active: int} */
    private function validated(array $input): array
    {
        $branch = $input['branch_id'] ?? null;
        $category = trim((string) ($input['category'] ?? ''));
        $title = trim((string) ($input['title'] ?? ''));
        $description = trim((string) ($input['description'] ?? ''));
        $active = $input['is_active'] ?? '1';

        if (! is_scalar($branch) || ! ctype_digit((string) $branch) || (int) $branch < 1) {
            throw new RuntimeException('Select an authorized branch.');
        }
        if ($category === '' || mb_strlen($category, 'UTF-8') > 100) {
            throw new RuntimeException('Enter a valid Achievement category.');
        }
        if ($title === '' || mb_strlen($title, 'UTF-8') > 150) {
            throw new RuntimeException('Enter a valid Achievement title.');
        }
        if (mb_strlen($description, 'UTF-8') > 5000) {
            throw new RuntimeException('Enter a shorter Achievement description.');
        }
        if (! in_array((string) $active, ['0', '1'], true)) {
            throw new RuntimeException('Select a valid Achievement status.');
        }

        return [
            'branch_id' => (int) $branch,
            'category' => $category,
            'title' => $title,
            'description' => $description === '' ? null : $description,
            'is_active' => (int) $active,
        ];
    }

    private function achievementManagementPermission(): ?string
    {
        if (service('authorization')->can('achievement.update')) {
            return 'achievement.update';
        }

        return service('authorization')->can('achievement.create')
            ? 'achievement.create'
            : null;
    }

    private function allowAwardPermission(int $id, string $permission): void
    {
        if (! service('authorization')->can($permission)) {
            throw new RuntimeException('Forbidden');
        }

        $award = $this->award($id);
        $this->allow((int) $award['branch_id'], $permission);
    }

    /** @return list<int> */
    private function authorizedBranchIds(string $permission): array
    {
        if (! service('authorization')->can($permission)) {
            return [];
        }

        $ids = [];
        foreach (service('branchContext')->authorizedBranchIds(true) as $branchId) {
            if (
                is_numeric($branchId)
                && (int) $branchId > 0
                && service('authorization')->can($permission, (string) $branchId)
            ) {
                $ids[] = (int) $branchId;
            }
        }

        return array_values(array_unique($ids));
    }

    private function allow(int $branchId, string $permission): void
    {
        if (! in_array($branchId, $this->authorizedBranchIds($permission), true)) {
            throw new RuntimeException('Forbidden');
        }
    }

    /** @param list<int> $branchIds */
    private function allowHistoricalAccess(array $branchIds): void
    {
        if ($branchIds !== []) {
            return;
        }

        throw new RuntimeException('Forbidden');
    }

    private function actorId(): int
    {
        $user = session('user');
        $id = is_array($user) ? ($user['id'] ?? null) : null;
        if (! is_scalar($id) || ! ctype_digit((string) $id) || (int) $id < 1) {
            throw new RuntimeException('Forbidden');
        }

        return (int) $id;
    }
}
