<?php

declare(strict_types=1);

namespace App\Services;

use App\Libraries\Storage\LocalPrivateStorage;
use Config\Database;
use RuntimeException;
use Throwable;

/** USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL. Published certificates only. */
final class AchievementCertificateService
{
    public function generate(int $awardId): int
    {
        $award = service('achievement')->award($awardId);
        $branchId = (int) $award['branch_id'];
        $this->allow($branchId, 'achievement.certificate.generate');
        if ((string) $award['status'] !== 'published' || (int) ($award['is_archived'] ?? 0) === 1) {
            throw new RuntimeException('Certificates are available only for published Achievements.');
        }

        $db = Database::connect('default');
        $existing = $db->table('achievement_certificates')
            ->where('award_id', $awardId)
            ->get()
            ->getRowArray();
        $storage = $this->storage();
        if (is_array($existing) && $storage->exists((string) $existing['storage_key'])) {
            return (int) $existing['id'];
        }

        $recipient = (string) ($award['recipient_type'] === 'player'
            ? ($award['player_name'] ?? 'Player')
            : ($award['coach_name'] ?? 'Coach'));
        $title = (string) ($award['achievement_title'] ?? 'Achievement');
        $html = $this->certificateHtml($recipient, $title);
        $key = 'achievement-certificates/' . $branchId . '/' . $awardId . '.html';
        $storage->store($key, $html);
        $actor = $this->actorId();
        $now = gmdate('Y-m-d H:i:s');

        $db->transBegin();
        try {
            $data = [
                'award_id' => $awardId,
                'branch_id' => $branchId,
                'storage_key' => $key,
                'mime_type' => 'text/html',
                'file_size' => strlen($html),
                'generated_by' => $actor,
                'generated_at' => $now,
                'updated_at' => $now,
            ];
            if (is_array($existing)) {
                $db->table('achievement_certificates')
                    ->where('id', (int) $existing['id'])
                    ->update($data);
                $certificateId = (int) $existing['id'];
            } else {
                $db->table('achievement_certificates')->insert($data);
                $certificateId = (int) $db->insertID();
            }
            if ($certificateId < 1) {
                throw new RuntimeException('Unable to record the certificate.');
            }
            service('auditPersistence')->record(
                $actor,
                'achievement_certificate_generated',
                'achievement_certificate',
                (string) $certificateId,
                $branchId,
                $existing,
                $data,
                null,
                $db,
            );
            $db->transCommit();
            return $certificateId;
        } catch (Throwable $exception) {
            $db->transRollback();
            $storage->delete($key);
            throw $exception;
        }
    }

    /** @return array{contents: string, mime_type: string, name: string} */
    public function read(int $awardId): array
    {
        $row = Database::connect('default')->table('achievement_certificates')
            ->where('award_id', $awardId)
            ->get()
            ->getRowArray();
        if (! is_array($row)) {
            throw new RuntimeException('Not found.');
        }
        $this->allow((int) $row['branch_id'], 'achievement.certificate.download');
        $actor = $this->actorId();
        service('auditPersistence')->record(
            $actor,
            'achievement_certificate_downloaded',
            'achievement_certificate',
            (string) $row['id'],
            (int) $row['branch_id'],
            null,
            [
                'award_id' => (int) $row['award_id'],
                'mime_type' => (string) $row['mime_type'],
            ],
            null,
        );
        return [
            'contents' => $this->storage()->read((string) $row['storage_key']),
            'mime_type' => (string) $row['mime_type'],
            'name' => 'achievement-certificate-' . $awardId . '.html',
        ];
    }

    private function certificateHtml(string $recipient, string $title): string
    {
        return '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>'
            . $this->escape($title)
            . '</title></head><body><main><h1>Achievement Certificate</h1><p>This certifies that <strong>'
            . $this->escape($recipient)
            . '</strong> received <strong>'
            . $this->escape($title)
            . '</strong>.</p></main></body></html>';
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function storage(): LocalPrivateStorage
    {
        return new LocalPrivateStorage(WRITEPATH . 'private');
    }

    private function allow(int $branchId, string $permission): void
    {
        if (! service('authorization')->can($permission, (string) $branchId)) {
            throw new RuntimeException('Not found.');
        }
    }

    private function actorId(): int
    {
        $user = session('user');
        $id = is_array($user) ? ($user['id'] ?? null) : null;
        if (! is_scalar($id) || ! ctype_digit((string) $id) || (int) $id < 1) {
            throw new RuntimeException('Authentication required.');
        }
        return (int) $id;
    }
}
