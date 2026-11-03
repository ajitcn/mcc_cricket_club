<?php

declare(strict_types=1);

namespace App\Services;

use App\Libraries\Storage\LocalPrivateStorage;
use CodeIgniter\HTTP\Files\UploadedFile;
use Config\Database;
use RuntimeException;
use Throwable;

final class TrialMediaService
{
    private const MIME_EXTENSIONS = [
        'video/mp4' => 'mp4',
        'video/webm' => 'webm',
        'video/quicktime' => 'mov',
        'video/x-msvideo' => 'avi',
    ];

    private const MAX_SIZE = 104_857_600;

    /** @return list<array<string, mixed>> */
    public function listForTrial(int $trialId): array
    {
        $trial = $this->loadTrial($trialId);
        $this->authorizeTrialReview($trial);

        return Database::connect('default')
            ->table('trial_media tm')
            ->select('tm.*, u.display_name AS uploader_name')
            ->join('users u', 'u.id = tm.uploaded_by', 'left')
            ->where([
                'tm.trial_id' => $trialId,
                'tm.branch_id' => (int) $trial['branch_id'],
                'tm.status' => 'active',
            ])
            ->orderBy('tm.created_at', 'DESC')
            ->orderBy('tm.id', 'DESC')
            ->get()
            ->getResultArray();
    }

    public function upload(int $trialId, UploadedFile $file): int
    {
        $db = Database::connect('default');
        $trial = $this->loadTrial($trialId, true, $db);
        $branch = (int) $trial['branch_id'];
        $actor = $this->authorizeTrialMediaUpload($trial);
        $this->validateUpload($file);

        $mime = (string) $file->getMimeType();
        $extension = self::MIME_EXTENSIONS[$mime];
        $key = 'trial-media/' . $branch . '/' . $trialId . '/' . bin2hex(random_bytes(16)) . '.' . $extension;
        $storage = $this->storage();
        $stored = false;

        try {
            $storage->store($key, $this->contents($file));
            $stored = true;
            $now = gmdate('Y-m-d H:i:s');

            $db->transStart();
            $db->table('trial_media')->insert([
                'trial_id' => $trialId,
                'branch_id' => $branch,
                'original_name' => $this->safeOriginalName($file->getClientName(), $extension),
                'storage_key' => $key,
                'mime_type' => $mime,
                'file_size' => (int) $file->getSize(),
                'uploaded_by' => $actor,
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $mediaId = (int) $db->insertID();

            service('auditPersistence')->record(
                $actor,
                'trial_media_uploaded',
                'trial_media',
                (string) $mediaId,
                $branch,
                null,
                [
                    'trial_id' => $trialId,
                    'mime_type' => $mime,
                    'file_size' => (int) $file->getSize(),
                    'status' => 'active',
                ],
            );

            $db->transComplete();
            if (! $db->transStatus()) {
                throw new RuntimeException('Unable to save Trial media metadata.');
            }

            return $mediaId;
        } catch (Throwable $exception) {
            $db->transRollback();
            $db->resetTransStatus();

            if ($stored) {
                try {
                    $storage->delete($key);
                } catch (Throwable) {
                    // Preserve the original failure without exposing storage details.
                }
            }

            throw $exception;
        }
    }

    /** @return array{media: array<string, mixed>, contents: string} */
    public function read(int $trialId, int $mediaId): array
    {
        $trial = $this->loadTrial($trialId);
        $this->authorizeTrialReview($trial);

        $media = Database::connect('default')
            ->table('trial_media')
            ->where([
                'id' => $mediaId,
                'trial_id' => $trialId,
                'branch_id' => (int) $trial['branch_id'],
                'status' => 'active',
            ])
            ->get()
            ->getRowArray();

        if (! is_array($media) || ! isset(self::MIME_EXTENSIONS[$media['mime_type']])) {
            throw new RuntimeException('Not found.');
        }

        try {
            $contents = $this->storage()->read((string) $media['storage_key']);
        } catch (Throwable) {
            throw new RuntimeException('Not found.');
        }

        return [
            'media' => $media,
            'contents' => $contents,
        ];
    }

    /** @return array<string, mixed> */
    private function loadTrial(int $trialId, bool $lock = false, ?object $db = null): array
    {
        if ($trialId < 1) {
            throw new RuntimeException('Not found.');
        }

        $db ??= Database::connect('default');
        $sql = 'SELECT id, branch_id, lead_id, coach_user_id, secondary_coach_user_id FROM trials WHERE id = ? LIMIT 1';
        if ($lock) {
            $sql .= ' FOR UPDATE';
        }

        $trial = $db->query($sql, [$trialId])->getRowArray();
        if (! is_array($trial)) {
            throw new RuntimeException('Not found.');
        }

        return $trial;
    }

    /** @param array<string, mixed> $trial */
    private function authorizeTrialReview(array $trial): int
    {
        $branch = (int) ($trial['branch_id'] ?? 0);
        $sessionBranches = session('authorizedBranches');
        $authorizedBranches = is_array($sessionBranches)
            ? array_map('intval', $sessionBranches)
            : service('branchContext')->authorizedBranchIds(true);

        if (! in_array($branch, $authorizedBranches, true)) {
            throw new RuntimeException('Not found.');
        }

        if (service('authorization')->can('lead.view', (string) $branch)) {
            return $this->authorize($branch, 'lead.view');
        }

        $actor = session('user');
        $actorId = is_array($actor) ? (int) ($actor['id'] ?? 0) : 0;
        $assigned = in_array($actorId, [
            (int) ($trial['coach_user_id'] ?? 0),
            (int) ($trial['secondary_coach_user_id'] ?? 0),
        ], true);

        if ($assigned && $actorId > 0 && service('authorization')->can('performance.update', (string) $branch)) {
            return $actorId;
        }

        throw new RuntimeException('Forbidden');
    }

    /** @param array<string, mixed> $trial */
    private function authorizeTrialMediaUpload(array $trial): int
    {
        $branch = (int) ($trial['branch_id'] ?? 0);
        $actor = session('user');
        $actorId = is_array($actor) ? (int) ($actor['id'] ?? 0) : 0;
        $assigned = in_array($actorId, [
            (int) ($trial['coach_user_id'] ?? 0),
            (int) ($trial['secondary_coach_user_id'] ?? 0),
        ], true);

        if ($assigned && service('authorization')->can('performance.update', (string) $branch)) {
            return $actorId;
        }

        return $this->authorize($branch, 'lead.update');
    }

    private function validateUpload(UploadedFile $file): void
    {
        if ($file->hasMoved()
            || ! $file->isValid()
            || $file->getError() !== UPLOAD_ERR_OK
            || $file->getSize() === null
            || $file->getSize() < 1
            || $file->getSize() > self::MAX_SIZE) {
            throw new RuntimeException('Invalid Trial media upload.');
        }

        $mime = (string) $file->getMimeType();
        $extension = strtolower(trim((string) $file->getClientExtension()));
        if (! isset(self::MIME_EXTENSIONS[$mime]) || $extension !== self::MIME_EXTENSIONS[$mime]) {
            throw new RuntimeException('Invalid Trial media upload.');
        }

        if ($this->contents($file) === '') {
            throw new RuntimeException('Invalid Trial media upload.');
        }
    }

    private function contents(UploadedFile $file): string
    {
        $contents = file_get_contents($file->getTempName());
        if ($contents === false) {
            throw new RuntimeException('Unable to read Trial media upload.');
        }

        return $contents;
    }

    private function storage(): LocalPrivateStorage
    {
        return new LocalPrivateStorage(WRITEPATH . 'private');
    }

    private function authorize(int $branch, string $permission): int
    {
        if ($branch < 1 || ! service('authorization')->can($permission, (string) $branch)) {
            throw new RuntimeException('Forbidden');
        }

        $user = session('user');
        if (! is_array($user) || ! isset($user['id'])) {
            throw new RuntimeException('Authentication required');
        }

        return (int) $user['id'];
    }

    private function safeOriginalName(string $name, string $extension): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?? '';
        $name = trim($name);

        return $name === '' ? 'trial-media.' . $extension : substr($name, 0, 255);
    }
}
