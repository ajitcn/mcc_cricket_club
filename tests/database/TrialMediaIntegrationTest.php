<?php

declare(strict_types=1);

namespace Tests\Database;

use App\Libraries\Storage\LocalPrivateStorage;
use CodeIgniter\HTTP\Files\UploadedFile;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use RuntimeException;

final class TrialMediaIntegrationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    use FeatureTestTrait;

    protected $DBGroup = 'tests';

    protected $migrate = false;

    private int $actor;

    private int $branch;

    private int $trial;

    /** @var list<string> */
    private array $temporaryFiles = [];

    /** @var list<int> */
    private array $mediaIds = [];

    /** @var list<string> */
    private array $storageKeys = [];

    protected function setUp(): void
    {
        parent::setUp();

        self::assertSame('mcc_cricket_club_test', strtolower((string) $this->db->getDatabase()));

        if (! $this->db->tableExists('trial_media')) {
            self::markTestSkipped('Trial media migration is not applied to the test database.');
        }

        $this->actor = (int) $this->db->table('users')
            ->where('email', 'test.mcc.superadmin@example.invalid')
            ->get()
            ->getRow('id');
        $this->branch = (int) $this->db->table('branches')
            ->where('code', 'TEST_MCC_VNS')
            ->get()
            ->getRow('id');
        $this->trial = (int) $this->db->table('trials')
            ->where('branch_id', $this->branch)
            ->orderBy('id', 'DESC')
            ->get()
            ->getRow('id');

        if (min($this->actor, $this->branch, $this->trial) < 1) {
            self::markTestSkipped('Trial media fixtures are not available.');
        }

        $this->authorize(['lead.view', 'lead.update'], [$this->branch]);
    }

    protected function tearDown(): void
    {
        $storage = new LocalPrivateStorage(WRITEPATH . 'private');
        foreach ($this->storageKeys as $key) {
            if ($storage->exists($key)) {
                $storage->delete($key);
            }
        }

        if ($this->mediaIds !== []) {
            $this->db->table('trial_media')->whereIn('id', $this->mediaIds)->delete();
        }

        foreach ($this->temporaryFiles as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }

        session()->destroy();
        \Config\Services::resetSingle('authorization');
        parent::tearDown();
    }

    public function testValidVideoUploadStoresPrivateMetadataAndAuditWithoutChangingTrial(): void
    {
        $before = $this->db->table('trials')->where('id', $this->trial)->get()->getRowArray();
        $file = $this->uploadedFile('trial-performance.mp4', 'video/mp4', 'video bytes');

        $mediaId = service('trialMedia', false)->upload($this->trial, $file);
        $media = $this->db->table('trial_media')->where('id', $mediaId)->get()->getRowArray();
        $after = $this->db->table('trials')->where('id', $this->trial)->get()->getRowArray();

        self::assertSame($this->trial, (int) $media['trial_id']);
        self::assertSame($this->branch, (int) $media['branch_id']);
        self::assertSame('video/mp4', $media['mime_type']);
        self::assertStringStartsWith('trial-media/', (string) $media['storage_key']);
        self::assertStringNotContainsString('public', strtolower((string) $media['storage_key']));
        self::assertSame($before, $after);
        self::assertSame(1, $this->db->table('audit_logs')->where([
            'action' => 'trial_media_uploaded',
            'entity_id' => (string) $mediaId,
        ])->countAllResults());
    }

    public function testAssignedCoachCanUploadAndReviewMediaWithoutLeadView(): void
    {
        $trial = $this->db->table('trials')->where('id', $this->trial)->get()->getRowArray();
        $originalCoach = $trial['coach_user_id'] ?? null;
        $this->db->table('trials')->where('id', $this->trial)->update(['coach_user_id' => $this->actor]);

        try {
            session()->set([
                'user' => ['id' => $this->actor],
                'permissions' => ['performance.update'],
                'authorizedBranches' => [(string) $this->branch],
            ]);
            \Config\Services::resetSingle('authorization');
            $mediaId = $this->insertStoredMedia('assigned.mp4', 'video/mp4', 'assigned coach media');

            self::assertNotEmpty(service('trialMedia', false)->listForTrial($this->trial));
            self::assertSame('assigned coach media', service('trialMedia', false)->read($this->trial, $mediaId)['contents']);
            $uploaded = service('trialMedia', false)->upload(
                $this->trial,
                $this->uploadedFile('assigned-upload.mp4', 'video/mp4', 'uploaded by assigned coach'),
            );
            self::assertGreaterThan(0, $uploaded);
        } finally {
            $this->db->table('trials')->where('id', $this->trial)->update(['coach_user_id' => $originalCoach]);
        }
    }

    public function testInvalidMimeExtensionAndOversizedUploadsAreRejectedWithoutMetadata(): void
    {
        $before = $this->db->table('trial_media')->where('trial_id', $this->trial)->countAllResults();

        foreach ([
            ['trial.txt', 'text/plain', 'not video'],
            ['trial.mp4', 'video/webm', 'wrong extension'],
        ] as [$name, $mime, $contents]) {
            try {
                service('trialMedia', false)->upload(
                    $this->trial,
                    $this->uploadedFile($name, $mime, $contents),
                );
                self::fail('Expected invalid Trial media upload to fail.');
            } catch (RuntimeException $exception) {
                self::assertSame('Invalid Trial media upload.', $exception->getMessage());
            }
        }

        try {
            service('trialMedia', false)->upload(
                $this->trial,
                $this->oversizedUploadedFile(),
            );
            self::fail('Expected an oversized Trial media upload to fail.');
        } catch (RuntimeException $exception) {
            self::assertSame('Invalid Trial media upload.', $exception->getMessage());
        }

        self::assertSame($before, $this->db->table('trial_media')->where('trial_id', $this->trial)->countAllResults());
    }

    public function testBranchAndPermissionChecksProtectTrialMedia(): void
    {
        session()->set('permissions', ['lead.view']);
        \Config\Services::resetSingle('authorization');

        $file = $this->uploadedFile('trial.mp4', 'video/mp4', 'video bytes');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Forbidden');
        service('trialMedia', false)->upload($this->trial, $file);
    }

    public function testAuthorizedPreviewAndDownloadReturnStoredVideoBytesAndSafeHeaders(): void
    {
        $mediaId = $this->insertStoredMedia('../unsafe name.mp4', 'video/mp4', 'private-video-bytes');
        $stored = service('trialMedia', false)->read($this->trial, $mediaId);
        self::assertSame('private-video-bytes', $stored['contents']);

        $preview = $this->withSession(session()->get())->get(
            'crm/trials/' . $this->trial . '/media/' . $mediaId . '/preview',
        );
        self::assertSame(200, $preview->response()->getStatusCode(), $preview->getBody());
        self::assertStringStartsWith('video/mp4', $preview->response()->getHeaderLine('Content-Type'));
        self::assertSame('inline; filename="unsafe_name.mp4"', $preview->response()->getHeaderLine('Content-Disposition'));
        self::assertSame('private-video-bytes', $preview->response()->getBody());

        $download = $this->withSession(session()->get())->get(
            'crm/trials/' . $this->trial . '/media/' . $mediaId . '/download',
        );
        self::assertSame(200, $download->response()->getStatusCode());
        self::assertStringStartsWith('video/mp4', $download->response()->getHeaderLine('Content-Type'));
        self::assertSame('attachment; filename="unsafe_name.mp4"', $download->response()->getHeaderLine('Content-Disposition'));
        self::assertSame('private-video-bytes', $download->response()->getBody());
    }

    public function testMismatchedMissingInvalidAndUnauthorizedMediaReturnGenericNotFound(): void
    {
        $mediaId = $this->insertStoredMedia('video.mp4', 'video/mp4', 'video');

        $wrongTrial = $this->withSession(session()->get())->get(
            'crm/trials/999999999/media/' . $mediaId . '/preview',
        );
        self::assertSame(404, $wrongTrial->response()->getStatusCode());
        self::assertStringContainsString('Not found.', $wrongTrial->response()->getBody());

        $invalidMedia = $this->withSession(session()->get())->get(
            'crm/trials/' . $this->trial . '/media/0/preview',
        );
        self::assertSame(404, $invalidMedia->response()->getStatusCode());
        self::assertStringContainsString('Not found.', $invalidMedia->response()->getBody());

        $missingMedia = $this->insertMissingMedia();
        $missingFile = $this->withSession(session()->get())->get(
            'crm/trials/' . $this->trial . '/media/' . $missingMedia . '/download',
        );
        self::assertSame(404, $missingFile->response()->getStatusCode());
        self::assertStringContainsString('Not found.', $missingFile->response()->getBody());

        session()->set('authorizedBranches', ['999999']);
        \Config\Services::resetSingle('authorization');
        $unauthorized = $this->withSession(session()->get())->get(
            'crm/trials/' . $this->trial . '/media/' . $mediaId . '/preview',
        );
        self::assertSame(404, $unauthorized->response()->getStatusCode());
        self::assertStringContainsString('Not found.', $unauthorized->response()->getBody());
    }

    public function testMediaPreviewAndDownloadRequireTrialViewPermission(): void
    {
        $mediaId = $this->insertStoredMedia('video.mp4', 'video/mp4', 'video');
        session()->set('permissions', []);
        \Config\Services::resetSingle('authorization');

        $response = $this->withSession(session()->get())->get(
            'crm/trials/' . $this->trial . '/media/' . $mediaId . '/preview',
        );
        self::assertSame(403, $response->response()->getStatusCode());
    }

    /** @param list<string> $permissions @param list<int> $branches */
    private function authorize(array $permissions, array $branches): void
    {
        session()->set([
            'isLoggedIn' => true,
            'accountEnabled' => true,
            'user' => ['id' => $this->actor],
            'session_version' => 1,
            'permissions' => $permissions,
            'authorizedBranches' => array_map('strval', $branches),
        ]);
        \Config\Services::resetSingle('authorization');
    }

    private function uploadedFile(string $name, string $mime, string $contents): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'mcc-trial-media-');
        self::assertIsString($path);
        file_put_contents($path, $contents);
        $this->temporaryFiles[] = $path;

        return new class($path, $name, $mime) extends UploadedFile {
            public function isValid(): bool
            {
                return true;
            }

            public function getMimeType(): string
            {
                return $this->originalMimeType;
            }

            public function getError(): int
            {
                return UPLOAD_ERR_OK;
            }
        };
    }

    private function oversizedUploadedFile(): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'mcc-trial-media-large-');
        self::assertIsString($path);
        $handle = fopen($path, 'wb');
        self::assertIsResource($handle);
        ftruncate($handle, 104_857_601);
        fclose($handle);
        $this->temporaryFiles[] = $path;

        return new class($path, 'trial-large.mp4', 'video/mp4') extends UploadedFile {
            public function isValid(): bool
            {
                return true;
            }

            public function getMimeType(): string
            {
                return $this->originalMimeType;
            }

            public function getError(): int
            {
                return UPLOAD_ERR_OK;
            }
        };
    }

    private function insertStoredMedia(string $name, string $mime, string $contents): int
    {
        $key = 'trial-media/' . $this->branch . '/' . $this->trial . '/regression-' . bin2hex(random_bytes(8)) . '.mp4';
        $storage = new LocalPrivateStorage(WRITEPATH . 'private');
        $storage->store($key, $contents);
        $this->storageKeys[] = $key;

        $now = gmdate('Y-m-d H:i:s');
        $this->db->table('trial_media')->insert([
            'trial_id' => $this->trial,
            'branch_id' => $this->branch,
            'original_name' => $name,
            'storage_key' => $key,
            'mime_type' => $mime,
            'file_size' => strlen($contents),
            'uploaded_by' => $this->actor,
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $id = (int) $this->db->insertID();
        $this->mediaIds[] = $id;

        return $id;
    }

    private function insertMissingMedia(): int
    {
        $this->db->table('trial_media')->insert([
            'trial_id' => $this->trial,
            'branch_id' => $this->branch,
            'original_name' => 'missing.mp4',
            'storage_key' => 'trial-media/' . $this->branch . '/' . $this->trial . '/missing-' . bin2hex(random_bytes(8)) . '.mp4',
            'mime_type' => 'video/mp4',
            'file_size' => 10,
            'uploaded_by' => $this->actor,
            'status' => 'active',
            'created_at' => gmdate('Y-m-d H:i:s'),
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ]);
        $id = (int) $this->db->insertID();
        $this->mediaIds[] = $id;

        return $id;
    }
}
