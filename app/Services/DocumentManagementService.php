<?php

declare(strict_types=1);

namespace App\Services;

use App\Libraries\Storage\LocalPrivateStorage;
use CodeIgniter\HTTP\Files\UploadedFile;
use Config\Database;
use RuntimeException;
use Throwable;

final class DocumentManagementService
{
    private const TYPES = [
        'aadhaar',
        'birth_certificate',
        'school_id',
        'mca_card',
        'medical_certificate',
        'passport',
        'visa',
        'consent_form',
        'player_photo',
    ];

    private const MIME_EXTENSIONS = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
    ];

    private const MAX_SIZE = 10_485_760;

    private const VERIFICATION_STATES = ['PENDING', 'VERIFIED', 'REJECTED'];

    private const SCANNER_STATES = ['QUARANTINED', 'CLEAN', 'FAILED'];

    /** @return list<string> */
    public function types(): array
    {
        return self::TYPES;
    }

    /** @return list<string> */
    public function profilePhotoTypes(): array
    {
        return ['player_photo'];
    }

    public function authorizedProfilePhotoId(int $playerId, int $branchId): ?int
    {
        if ($playerId < 1
            || $branchId < 1
            || ! service('authorization')->can('document.view', (string) $branchId)
            || ! service('authorization')->can('document.download', (string) $branchId)) {
            return null;
        }

        $photo = Database::connect('default')->table('documents')
            ->select('id, expires_on, status, verification_status, scanner_status')
            ->where([
                'player_id' => $playerId,
                'branch_id' => $branchId,
                'document_type' => 'player_photo',
                'status' => 'active',
                'verification_status' => 'VERIFIED',
                'scanner_status' => 'CLEAN',
            ])
            ->whereIn('mime_type', ['image/jpeg', 'image/png'])
            ->orderBy('created_at', 'DESC')
            ->orderBy('id', 'DESC')
            ->get()
            ->getRowArray();

        return is_array($photo) && $this->canUse($photo) ? (int) $photo['id'] : null;
    }

    public function uploadPlayerPhoto(int $playerId, UploadedFile $file): int
    {
        $player = Database::connect('default')->table('players')
            ->select('id, branch_id')
            ->where('id', $playerId)
            ->get()
            ->getRowArray();

        if (! is_array($player)) {
            throw new RuntimeException('Player is not eligible.');
        }

        return $this->upload([
            'branch_id' => (int) $player['branch_id'],
            'player_id' => $playerId,
            'document_type' => 'player_photo',
        ], $file);
    }

    public function list(array $filters = []): array
    {
        $branches = $this->branches();
        $builder = Database::connect('default')->table('documents d')
            ->select('d.*, COALESCE(pe.full_name, a.applicant_full_name) AS full_name, b.name AS branch_name')
            ->join('players p', 'p.id = d.player_id', 'left')
            ->join('persons pe', 'pe.id = p.person_id', 'left')
            ->join('admissions a', 'a.id = d.admission_id', 'left')
            ->join('branches b', 'b.id = d.branch_id')
            ->whereIn('d.branch_id', $branches ?: [0]);

        if ($branches !== []) {
            $this->assertCanViewBranches($branches);
        }

        $query = trim((string) ($filters['q'] ?? ''));
        if ($query !== '') {
            $builder->groupStart()
                ->like('d.original_name', $query)
                ->orLike('pe.full_name', $query)
                ->orLike('a.applicant_full_name', $query)
                ->groupEnd();
        }

        $playerId = filter_var(
            $filters['player_id'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]],
        );
        if ($playerId !== false) {
            $builder->where('d.player_id', $playerId);
        }

        $type = (string) ($filters['document_type'] ?? '');
        if (in_array($type, self::TYPES, true)) {
            $builder->where('d.document_type', $type);
        }

        $status = (string) ($filters['status'] ?? '');
        if (in_array($status, ['active', 'expired', 'retired'], true)) {
            $builder->where('d.status', $status);
        }

        $documents = $builder->orderBy('d.created_at', 'DESC')->get()->getResultArray();
        $documents = array_values(array_filter(
            $documents,
            fn (array $document): bool => (string) $document['document_type'] !== 'medical_certificate'
                || service('authorization')->can('document.medical.view', (string) $document['branch_id'])
        ));

        return [
            'documents' => $documents,
            'filters' => $filters,
        ];
    }

    public function upload(array $input, UploadedFile $file): int
    {
        $branch = (int) ($input['branch_id'] ?? 0);
        $player = (int) ($input['player_id'] ?? 0);
        $admission = (int) ($input['admission_id'] ?? 0);
        $type = (string) ($input['document_type'] ?? '');
        $documentId = (int) ($input['document_id'] ?? 0);

        $this->allow($branch, 'document.upload');
        $this->allowType($type, $branch);

        if ($admission > 0
            && ! service('authorization')->can('admission.create', (string) $branch)
            && ! service('authorization')->can('admission.update', (string) $branch)) {
            throw new RuntimeException('Forbidden');
        }

        $this->validateUpload($type, $file);
        $expiresOn = $this->validateExpiry($input['expires_on'] ?? null);

        $db = Database::connect('default');
        if ($admission > 0) {
            $admissionRow = $db->table('admissions')->where([
                'id' => $admission,
                'branch_id' => $branch,
            ])->get()->getRowArray();

            if (! is_array($admissionRow)) {
                throw new RuntimeException('Admission is not eligible.');
            }

            if ($player > 0 && $db->table('players')->where([
                'id' => $player,
                'admission_id' => $admission,
                'branch_id' => $branch,
            ])->countAllResults() === 0) {
                throw new RuntimeException('Player is not eligible.');
            }

            $player = $player > 0 ? $player : null;
        } elseif ($db->table('players')->where([
            'id' => $player,
            'branch_id' => $branch,
        ])->countAllResults() === 0) {
            throw new RuntimeException('Player is not eligible.');
        }

        $existing = null;
        if ($documentId > 0) {
            $existing = $db->table('documents')->where([
                'id' => $documentId,
                'branch_id' => $branch,
                'admission_id' => $admission > 0 ? $admission : null,
            ])->get()->getRowArray();

            if (! is_array($existing)) {
                throw new RuntimeException('Document is not eligible.');
            }

            if ((string) $existing['document_type'] !== $type) {
                throw new RuntimeException('Document is not eligible.');
            }
        }

        $mime = $file->getMimeType();
        $extension = self::MIME_EXTENSIONS[$mime];
        $key = 'documents/' . $branch . '/' . bin2hex(random_bytes(16)) . '.' . $extension;
        $storage = $this->storage();
        $stored = false;

        try {
            $storage->store($key, $this->contents($file));
            $stored = true;

            $now = gmdate('Y-m-d H:i:s');
            $actor = $this->actor();
            $db->transStart();
            $metadata = [
                'original_name' => $this->safeOriginalName(
                    $file->getClientName(),
                    $extension
                ),
                'storage_key' => $key,
                'mime_type' => $mime,
                'file_size' => (int) $file->getSize(),
                'expires_on' => $expiresOn,
                'status' => 'active',
                'verification_status' => 'PENDING',
                'verified_by' => null,
                'verified_at' => null,
                'verification_reason' => null,
                'scanner_status' => 'QUARANTINED',
                'scanner_checked_at' => $now,
                'scanner_reason' => 'Scanner is not configured.',
                'version_number' => is_array($existing)
                    ? ((int) ($existing['version_number'] ?? 1) + 1)
                    : 1,
                'previous_version_id' => is_array($existing) ? $documentId : null,
                'uploaded_by' => $actor,
                'updated_at' => $now,
            ];

            if (is_array($existing)) {
                $db->table('documents')->where('id', $documentId)->update([
                    'status' => 'retired',
                    'updated_at' => $now,
                ]);
            }

            $db->table('documents')->insert([
                'branch_id' => $branch,
                'player_id' => $player ?: ($existing['player_id'] ?? null),
                'admission_id' => $admission > 0 ? $admission : ($existing['admission_id'] ?? null),
                'document_type' => $type,
                ...$metadata,
                'created_at' => $now,
            ]);
            $id = (int) $db->insertID();
            $action = is_array($existing) ? 'version_created' : 'uploaded';

            $db->table('document_history')->insert([
                'document_id' => $id,
                'action' => $action,
                'actor_user_id' => $actor,
                'metadata' => json_encode([
                    'previous_version_id' => is_array($existing) ? $documentId : null,
                    'scanner_status' => 'QUARANTINED',
                ], JSON_THROW_ON_ERROR),
                'created_at' => $now,
            ]);
            if (is_array($existing)) {
                $db->table('document_history')->insert([
                    'document_id' => $documentId,
                    'action' => 'version_retired',
                    'actor_user_id' => $actor,
                    'metadata' => json_encode(['new_version_id' => $id], JSON_THROW_ON_ERROR),
                    'created_at' => $now,
                ]);
            }
            service('auditPersistence')->record(
                $actor,
                $action === 'version_created' ? 'document_version_created' : 'document_uploaded',
                'document',
                (string) $id,
                $branch,
                is_array($existing) ? ['status' => 'active', 'document_id' => $documentId] : null,
                [
                    'document_type' => $type,
                    'mime_type' => $mime,
                    'file_size' => (int) $file->getSize(),
                    'expires_on' => $expiresOn,
                    'verification_status' => 'PENDING',
                    'scanner_status' => 'QUARANTINED',
                ]
            );
            $db->transComplete();

            if (! $db->transStatus()) {
                throw new RuntimeException('Unable to save document metadata.');
            }

            return $id;
        } catch (Throwable $exception) {
            $db->transRollback();
            $db->resetTransStatus();

            if ($stored) {
                $storage->delete($key);
            }

            throw $exception;
        }
    }

    public function find(int $id): array
    {
        $row = Database::connect('default')->table('documents d')
            ->select('d.*, COALESCE(pe.full_name, a.applicant_full_name) AS full_name, b.name AS branch_name')
            ->join('players p', 'p.id = d.player_id', 'left')
            ->join('persons pe', 'pe.id = p.person_id', 'left')
            ->join('admissions a', 'a.id = d.admission_id', 'left')
            ->join('branches b', 'b.id = d.branch_id')
            ->where('d.id', $id)
            ->get()
            ->getRowArray();

        if ($row === null) {
            throw $this->notFound();
        }

        $this->allowOrNotFound((int) $row['branch_id'], 'document.view');
        $this->allowType((string) $row['document_type'], (int) $row['branch_id']);

        if ((string) $row['status'] === 'retired') {
            throw $this->notFound();
        }

        $row['history'] = Database::connect('default')->table('document_history')
            ->where('document_id', $id)
            ->orderBy('created_at', 'DESC')
            ->get()
            ->getResultArray();

        return $row;
    }

    /** @return list<array<string, mixed>> */
    public function forAdmission(int $admissionId): array
    {
        $admission = Database::connect('default')->table('admissions')
            ->where('id', $admissionId)
            ->get()
            ->getRowArray();

        if (! is_array($admission)) {
            throw $this->notFound();
        }

        $this->allowOrNotFound((int) $admission['branch_id'], 'admission.view');
        $this->allowOrNotFound((int) $admission['branch_id'], 'document.view');

        $rows = Database::connect('default')->table('documents d')
            ->select('d.*, b.name AS branch_name')
            ->join('branches b', 'b.id = d.branch_id')
            ->where('d.admission_id', $admissionId)
            ->where('d.status !=', 'retired')
            ->orderBy('d.created_at', 'DESC')
            ->get()
            ->getResultArray();

        foreach ($rows as &$row) {
            if ((string) $row['document_type'] === 'medical_certificate'
                && ! service('authorization')->can(
                    'document.medical.view',
                    (string) $row['branch_id']
                )) {
                $row['can_view'] = false;
                $row['can_download'] = false;
                $row['can_remove'] = false;
                continue;
            }

            $row['can_view'] = true;
            $row['can_download'] = $this->canUse($row);
            $row['can_remove'] = service('authorization')->can(
                'admission.update',
                (string) $row['branch_id']
            );
        }

        return $rows;
    }

    public function removeFromAdmission(int $documentId, int $admissionId): void
    {
        $db = Database::connect('default');
        $admission = $db->table('admissions')
            ->where('id', $admissionId)
            ->get()
            ->getRowArray();

        if (! is_array($admission)) {
            throw new RuntimeException('Not found.');
        }

        $branch = (int) $admission['branch_id'];
        $this->allow($branch, 'admission.update');
        $this->allow($branch, 'document.view');

        $document = $db->table('documents')
            ->where([
                'id' => $documentId,
                'admission_id' => $admissionId,
                'branch_id' => $branch,
                'status' => 'active',
            ])
            ->get()
            ->getRowArray();

        if (! is_array($document)) {
            throw new RuntimeException('Document is not eligible.');
        }

        $this->allowType((string) $document['document_type'], $branch);

        try {
            $now = gmdate('Y-m-d H:i:s');
            $db->transStart();
            $db->table('documents')
                ->where('id', $documentId)
                ->update([
                    'status' => 'retired',
                    'updated_at' => $now,
                ]);
            $db->table('document_history')->insert([
                'document_id' => $documentId,
                'action' => 'retired',
                'actor_user_id' => $this->actor(),
                'metadata' => '{}',
                'created_at' => $now,
            ]);
            service('auditPersistence')->record(
                $this->actor(),
                'document_retired',
                'document',
                (string) $documentId,
                $branch,
                [
                    'document_type' => (string) $document['document_type'],
                    'mime_type' => (string) $document['mime_type'],
                    'status' => 'active',
                ],
                ['status' => 'retired']
            );
            $db->transComplete();

            if (! $db->transStatus()) {
                throw new RuntimeException('Unable to remove document metadata.');
            }
        } catch (Throwable $exception) {
            $db->transRollback();
            $db->resetTransStatus();

            throw $exception;
        }
    }

    public function linkAdmissionToPlayer(
        object $db,
        int $admissionId,
        int $playerId,
        int $branch,
        int $actor,
        string $now
    ): void {
        $conflict = $db->table('documents')
            ->where('admission_id', $admissionId)
            ->where('player_id IS NOT NULL', null, false)
            ->where('player_id !=', $playerId)
            ->countAllResults();

        if ($conflict > 0) {
            throw new RuntimeException('Conflicting document lineage.');
        }

        $documents = $db->table('documents')
            ->where([
                'admission_id' => $admissionId,
                'player_id' => null,
            ])
            ->get()
            ->getResultArray();

        if ($documents === []) {
            return;
        }

        $db->table('documents')
            ->where([
                'admission_id' => $admissionId,
                'player_id' => null,
            ])
            ->update([
                'player_id' => $playerId,
                'updated_at' => $now,
            ]);

        foreach ($documents as $document) {
            $db->table('document_history')->insert([
                'document_id' => (int) $document['id'],
                'action' => 'linked_to_player',
                'actor_user_id' => $actor,
                'metadata' => json_encode(['player_id' => $playerId], JSON_THROW_ON_ERROR),
                'created_at' => $now,
            ]);
        }

        service('auditPersistence')->record(
            $actor,
            'admission_documents_linked_to_player',
            'admission',
            (string) $admissionId,
            $branch,
            null,
            ['player_id' => $playerId, 'document_count' => count($documents)]
        );
    }

    public function read(int $id): array
    {
        $row = $this->find($id);
        $this->allowOrNotFound((int) $row['branch_id'], 'document.download');

        if (! $this->canUse($row)) {
            throw $this->notFound();
        }

        if (! isset(self::MIME_EXTENSIONS[$row['mime_type']])) {
            throw $this->notFound();
        }

        try {
            $contents = $this->storage()->read($row['storage_key']);
        } catch (Throwable $exception) {
            throw $this->notFound();
        }

        return [
            'document' => $row,
            'contents' => $contents,
        ];
    }

    public function verify(int $id, string $state, ?string $reason = null): void
    {
        $state = strtoupper(trim($state));
        $reason = trim((string) $reason);
        if (! in_array($state, self::VERIFICATION_STATES, true)) {
            throw new RuntimeException('Invalid verification state.');
        }

        $db = Database::connect('default');
        $document = $db->table('documents')->where('id', $id)->get()->getRowArray();
        if (! is_array($document)) {
            throw $this->notFound();
        }

        $branch = (int) $document['branch_id'];
        $this->allowOrNotFound($branch, 'document.upload');
        $this->allowType((string) $document['document_type'], $branch);

        if ((string) $document['status'] === 'retired') {
            throw $this->notFound();
        }

        if ($state === 'REJECTED' && $reason === '') {
            throw new RuntimeException('Verification reason required.');
        }

        if ($state === 'VERIFIED' && (string) ($document['scanner_status'] ?? '') !== 'CLEAN') {
            throw new RuntimeException('Document scan unavailable.');
        }

        $now = gmdate('Y-m-d H:i:s');
        $actor = $this->actor();
        $oldState = (string) ($document['verification_status'] ?? 'PENDING');
        $db->transStart();
        $db->table('documents')->where('id', $id)->update([
            'verification_status' => $state,
            'verified_by' => $actor,
            'verified_at' => $now,
            'verification_reason' => $reason !== '' ? $reason : null,
            'updated_at' => $now,
        ]);
        $metadata = json_encode([
            'old_state' => $oldState,
            'new_state' => $state,
            'reason' => $reason !== '' ? $reason : null,
        ], JSON_THROW_ON_ERROR);
        $db->table('document_history')->insert([
            'document_id' => $id,
            'action' => 'verification_changed',
            'actor_user_id' => $actor,
            'metadata' => $metadata,
            'created_at' => $now,
        ]);
        service('auditPersistence')->record(
            $actor,
            'document_verification_changed',
            'document',
            (string) $id,
            $branch,
            ['verification_status' => $oldState],
            ['verification_status' => $state, 'reason' => $reason !== '' ? $reason : null]
        );
        $db->transComplete();

        if (! $db->transStatus()) {
            throw new RuntimeException('Unable to save document verification.');
        }
    }

    /** @param array<string, mixed> $document */
    private function canUse(array $document): bool
    {
        if ((string) ($document['status'] ?? '') !== 'active'
            || (string) ($document['verification_status'] ?? '') !== 'VERIFIED'
            || (string) ($document['scanner_status'] ?? '') !== 'CLEAN') {
            return false;
        }

        $expiresOn = trim((string) ($document['expires_on'] ?? ''));
        if ($expiresOn === '') {
            return true;
        }

        $timezone = new \DateTimeZone(config('App')->appTimezone ?: 'Asia/Kolkata');
        $expiry = \DateTimeImmutable::createFromFormat('!Y-m-d', $expiresOn, $timezone);
        if (! $expiry || $expiry->format('Y-m-d') !== $expiresOn) {
            return false;
        }

        return $expiry >= new \DateTimeImmutable('today', $timezone);
    }

    private function allowType(string $type, int $branch): void
    {
        if ($type === 'medical_certificate') {
            $this->allowOrNotFound($branch, 'document.medical.view');
        }
    }

    private function validateExpiry(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return null;
        }

        $timezone = new \DateTimeZone(config('App')->appTimezone ?: 'Asia/Kolkata');
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value, $timezone);
        if (! $date || $date->format('Y-m-d') !== $value) {
            throw new RuntimeException('Invalid document expiry date.');
        }

        return $date->format('Y-m-d');
    }

    private function validateUpload(string $type, UploadedFile $file): void
    {
        if (! in_array($type, self::TYPES, true)
            || $file->hasMoved()
            || ! $file->isValid()
            || $file->getSize() === null
            || $file->getSize() < 1
            || $file->getSize() > self::MAX_SIZE) {
            throw new RuntimeException('Invalid document upload.');
        }

        $mime = $file->getMimeType();
        if (! isset(self::MIME_EXTENSIONS[$mime])) {
            throw new RuntimeException('Invalid document upload.');
        }

        if ($type === 'player_photo' && ! in_array($mime, ['image/jpeg', 'image/png'], true)) {
            throw new RuntimeException('Profile photos must be JPEG or PNG images.');
        }

        $contents = $this->contents($file);
        if ($mime === 'application/pdf'
            && strpos(substr($contents, 0, 1024), '%PDF-') === false) {
            throw new RuntimeException('Invalid document upload.');
        }

        if (in_array($mime, ['image/jpeg', 'image/png'], true)) {
            $imageInfo = @getimagesizefromstring($contents);

            if (! is_array($imageInfo)
                || (string) ($imageInfo['mime'] ?? '') !== $mime
                || (int) ($imageInfo[0] ?? 0) < 1
                || (int) ($imageInfo[1] ?? 0) < 1) {
                throw new RuntimeException('Invalid document upload.');
            }

            return;
        }

        if ($mime === 'application/pdf') {
            if (preg_match('#/(?:javascript|js)\b#i', $contents) === 1) {
                throw new RuntimeException('Executable or script content is not permitted.');
            }

            return;
        }

        $lowerContents = strtolower($contents);
        foreach (['<?php', '<?=', '<%', '<script'] as $marker) {
            if (str_contains($lowerContents, $marker)) {
                throw new RuntimeException('Executable or script content is not permitted.');
            }
        }
    }

    private function safeOriginalName(string $name, string $extension): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?? '';
        $name = trim($name);

        if ($name === '') {
            return 'document.' . $extension;
        }

        return substr($name, 0, 255);
    }

    private function contents(UploadedFile $file): string
    {
        $contents = file_get_contents($file->getTempName());

        if ($contents === false) {
            throw new RuntimeException('Unable to read document upload.');
        }

        return $contents;
    }

    private function storage(): LocalPrivateStorage
    {
        return new LocalPrivateStorage(WRITEPATH . 'private');
    }

    private function assertCanViewBranches(array $branches): void
    {
        foreach ($branches as $branch) {
            $this->allow($branch, 'document.view');
        }
    }

    private function allow(int $branch, string $permission): void
    {
        if (! service('authorization')->can($permission, (string) $branch)) {
            throw new RuntimeException('Forbidden');
        }
    }

    private function allowOrNotFound(int $branch, string $permission): void
    {
        try {
            $this->allow($branch, $permission);
        } catch (Throwable $exception) {
            throw $this->notFound();
        }
    }

    private function notFound(): RuntimeException
    {
        return new RuntimeException('Not found.');
    }

    private function branches(): array
    {
        return array_values(array_filter(array_map(
            'intval',
            (array) session('authorizedBranches')
        )));
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
