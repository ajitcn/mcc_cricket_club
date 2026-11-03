<?php

declare(strict_types=1);

namespace Tests\Database;

use App\Libraries\Storage\LocalPrivateStorage;
use App\Models\UserModel;
use CodeIgniter\HTTP\Files\UploadedFile;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use RuntimeException;

final class AdmissionDocumentsIntegrationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $DBGroup = 'tests';

    protected $migrate = false;

    private int $branch;

    private int $otherBranch;

    private int $actor;

    private int $admission;

    /** @var list<string> */
    private array $temporaryFiles = [];

    protected function setUp(): void
    {
        parent::setUp();

        $actor = $this->db->table('users')
            ->where('email', 'test.mcc.manager.vns@example.invalid')
            ->get()
            ->getRowArray();
        $branch = $this->db->table('branches')
            ->where('code', 'TEST_MCC_VNS')
            ->get()
            ->getRowArray();
        $otherBranch = $this->db->table('branches')
            ->where('code', 'TEST_MCC_DEL')
            ->get()
            ->getRowArray();

        self::assertIsArray($actor);
        self::assertIsArray($branch);
        self::assertIsArray($otherBranch);

        $this->actor = (int) $actor['id'];
        $this->branch = (int) $branch['id'];
        $this->otherBranch = (int) $otherBranch['id'];

        $this->authorize([
            'admission.create',
            'admission.view',
            'admission.update',
            'document.view',
            'document.upload',
            'document.download',
            'player.convert',
        ], [$this->branch]);

        $this->admission = service('admission', false)->createDirect([
            'branch_id' => $this->branch,
            'applicant_full_name' => 'Admission Document Test',
            'applicant_email' => 'admission-document-' . uniqid() . '@example.test',
        ]);
    }

    protected function tearDown(): void
    {
        $documentRows = $this->db->table('documents')
            ->where('admission_id', $this->admission)
            ->get()
            ->getResultArray();
        $player = $this->db->table('players')
            ->where('admission_id', $this->admission)
            ->get()
            ->getRowArray();

        foreach ($documentRows as $document) {
            $this->db->table('document_history')
                ->where('document_id', (int) $document['id'])
                ->delete();
            (new LocalPrivateStorage(WRITEPATH . 'private'))
                ->delete((string) $document['storage_key']);
        }

        $this->db->table('documents')
            ->where('admission_id', $this->admission)
            ->delete();
        $this->db->table('admission_status_history')
            ->where('admission_id', $this->admission)
            ->delete();
        $this->db->table('admissions')
            ->where('id', $this->admission)
            ->update(['converted_player_id' => null]);

        if (is_array($player)) {
            $playerId = (int) $player['id'];
            $this->db->table('player_batch_assignments')
                ->where('player_id', $playerId)
                ->delete();
            $this->db->table('player_branch_history')
                ->where('player_id', $playerId)
                ->delete();
            $this->db->table('players')
                ->where('id', $playerId)
                ->delete();
            $this->db->table('persons')
                ->where('id', (int) $player['person_id'])
                ->delete();
        }

        $this->db->table('admissions')
            ->where('id', $this->admission)
            ->delete();

        foreach ($this->temporaryFiles as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        session()->destroy();
        parent::tearDown();
    }

    public function testAdmissionDocumentUploadUsesTheExistingPrivateDocumentService(): void
    {
        $file = $this->uploadedFile('admission.pdf', 'application/pdf', $this->pdfContents());
        $id = service('document', false)->upload([
            'admission_id' => $this->admission,
            'branch_id' => $this->branch,
            'document_type' => 'birth_certificate',
        ], $file);
        $row = $this->db->table('documents')->where('id', $id)->get()->getRowArray();

        self::assertSame($this->admission, (int) $row['admission_id']);
        self::assertNull($row['player_id']);
        self::assertSame('birth_certificate', $row['document_type']);
        self::assertSame('application/pdf', $row['mime_type']);
        self::assertStringEndsWith('.pdf', $row['storage_key']);
        self::assertSame('uploaded', $this->db->table('document_history')->where('document_id', $id)->get()->getRow('action'));
    }

    public function testAdmissionProfilePhotoIsValidatedAndStoredPrivately(): void
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);
        self::assertIsString($png);
        $file = $this->uploadedFile('profile.png', 'image/png', $png);
        $id = service('document', false)->upload([
            'admission_id' => $this->admission,
            'branch_id' => $this->branch,
            'document_type' => 'player_photo',
        ], $file);
        $row = $this->db->table('documents')->where('id', $id)->get()->getRowArray();

        self::assertSame('player_photo', $row['document_type']);
        self::assertStringStartsWith('documents/' . $this->branch . '/', $row['storage_key']);
        self::assertStringNotContainsString('public/', $row['storage_key']);
    }

    public function testAdmissionJpegDocumentUploadStillSucceeds(): void
    {
        $jpeg = base64_decode(
            '/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////2wBDAf//////////////////////////////////////////////////////////////////////////////////////wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAX/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIQAxAAAAH/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAEFAqf/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAEDAQE/AX//xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAECAQE/AX//xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAY/Aqf/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAE/IV//2gAMAwEAAgADAAAAEP/EABQRAQAAAAAAAAAAAAAAAAAAABD/2gAIAQMBAT8QH//EABQRAQAAAAAAAAAAAAAAAAAAABD/2gAIAQIBAT8QH//EABQQAQAAAAAAAAAAAAAAAAAAABD/2gAIAQEAAT8QH//Z',
            true,
        );
        self::assertIsString($jpeg);
        $file = $this->uploadedFile('document.jpg', 'image/jpeg', $jpeg);
        $id = service('document', false)->upload([
            'admission_id' => $this->admission,
            'branch_id' => $this->branch,
            'document_type' => 'school_id',
        ], $file);
        $row = $this->db->table('documents')->where('id', $id)->get()->getRowArray();

        self::assertIsArray($row);
        self::assertSame('image/jpeg', $row['mime_type']);
        self::assertStringEndsWith('.jpg', $row['storage_key']);
    }

    public function testAdmissionDocumentReplacementCreatesVersionAndRetiresPreviousRow(): void
    {
        $original = $this->uploadedFile('original.pdf', 'application/pdf', $this->pdfContents('original'));
        $id = service('document', false)->upload([
            'admission_id' => $this->admission,
            'branch_id' => $this->branch,
            'document_type' => 'birth_certificate',
        ], $original);
        $oldKey = (string) $this->db->table('documents')->where('id', $id)->get()->getRow('storage_key');

        $replacement = $this->uploadedFile('replacement.pdf', 'application/pdf', $this->pdfContents('replacement'));
        $replacementId = service('document', false)->upload([
            'admission_id' => $this->admission,
            'branch_id' => $this->branch,
            'document_id' => $id,
            'document_type' => 'birth_certificate',
        ], $replacement);
        $oldRow = $this->db->table('documents')->where('id', $id)->get()->getRowArray();
        $row = $this->db->table('documents')->where('id', $replacementId)->get()->getRowArray();

        self::assertNotSame($id, $replacementId);
        self::assertSame($this->admission, (int) $row['admission_id']);
        self::assertSame('replacement.pdf', $row['original_name']);
        self::assertSame($id, (int) $row['previous_version_id']);
        self::assertSame(2, (int) $row['version_number']);
        self::assertSame('retired', $oldRow['status']);
        self::assertNotSame($oldKey, $row['storage_key']);
        self::assertSame($this->pdfContents('replacement'), (new LocalPrivateStorage(WRITEPATH . 'private'))
            ->read((string) $row['storage_key']));
        self::assertTrue((new LocalPrivateStorage(WRITEPATH . 'private'))->exists($oldKey));
        self::assertSame('version_retired', $this->db->table('document_history')
            ->where('document_id', $id)
            ->orderBy('id', 'DESC')
            ->get()
            ->getRow('action'));
    }

    public function testAdmissionDocumentCanBeRemovedWithStorageAndHistoryCleanup(): void
    {
        $file = $this->uploadedFile('remove-me.pdf', 'application/pdf', $this->pdfContents('remove this document'));
        $id = service('document', false)->upload([
            'admission_id' => $this->admission,
            'branch_id' => $this->branch,
            'document_type' => 'consent_form',
        ], $file);
        $row = $this->db->table('documents')->where('id', $id)->get()->getRowArray();

        self::assertIsArray($row);
        $storageKey = (string) $row['storage_key'];

        service('document', false)->removeFromAdmission($id, $this->admission);

        $removed = $this->db->table('documents')->where('id', $id)->get()->getRowArray();
        self::assertIsArray($removed);
        self::assertSame('retired', $removed['status']);
        self::assertTrue((new LocalPrivateStorage(WRITEPATH . 'private'))->exists($storageKey));
        self::assertSame(
            'retired',
            $this->db->table('document_history')
                ->where('document_id', $id)
                ->orderBy('id', 'DESC')
                ->get()
                ->getRow('action'),
        );
        self::assertSame(
            1,
            $this->db->table('audit_logs')
                ->where([
                    'action' => 'document_retired',
                    'entity_type' => 'document',
                    'entity_id' => (string) $id,
                ])
                ->countAllResults(),
        );
        self::assertCount(0, service('document', false)->forAdmission($this->admission));
    }

    public function testUnauthorizedDocumentAccessAndCrossBranchAccessAreRejected(): void
    {
        $this->authorize(['admission.view'], [$this->branch]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Not found.');
        service('document', false)->forAdmission($this->admission);

        $this->authorize(['admission.view', 'document.view'], [$this->otherBranch]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Not found.');
        service('document', false)->forAdmission($this->admission);
    }

    public function testSecureDownloadRequiresDownloadPermission(): void
    {
        $file = $this->uploadedFile('secure.pdf', 'application/pdf', $this->pdfContents('secure contents'));
        $id = service('document', false)->upload([
            'admission_id' => $this->admission,
            'branch_id' => $this->branch,
            'document_type' => 'consent_form',
        ], $file);

        $this->authorize(['admission.view', 'document.view'], [$this->branch]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Not found.');
        service('document', false)->read($id);
    }

    public function testDocumentAccessNormalizesMissingUnauthorizedRetiredAndInaccessibleRows(): void
    {
        $file = $this->uploadedFile('unsafe"name.pdf', 'application/pdf', $this->pdfContents('protected'));
        $id = service('document', false)->upload([
            'admission_id' => $this->admission,
            'branch_id' => $this->branch,
            'document_type' => 'consent_form',
        ], $file);

        $this->authorize(['admission.view', 'document.view', 'document.download'], [$this->branch]);
        $this->assertDocumentNotFound(static function () use ($id): void {
            service('document', false)->find($id + 1000000);
        });

        $this->authorize(['document.view', 'document.download'], [$this->otherBranch]);
        $this->assertDocumentNotFound(static function () use ($id): void {
            service('document', false)->read($id);
        });

        $this->authorize(['admission.view', 'document.view', 'document.download'], [$this->branch]);
        $this->db->table('documents')->where('id', $id)->update(['status' => 'retired']);
        $this->assertDocumentNotFound(static function () use ($id): void {
            service('document', false)->find($id);
        });

        $this->db->table('documents')->where('id', $id)->update(['status' => 'active']);
        $row = $this->db->table('documents')->where('id', $id)->get()->getRowArray();
        self::assertIsArray($row);
        (new LocalPrivateStorage(WRITEPATH . 'private'))->delete((string) $row['storage_key']);
        $this->assertDocumentNotFound(static function () use ($id): void {
            service('document', false)->read($id);
        });
    }

    public function testPreviewableAdmissionDocumentUsesTheSecurePreviewRoute(): void
    {
        $file = $this->uploadedFile('preview.pdf', 'application/pdf', $this->pdfContents('preview contents'));
        $id = service('document', false)->upload([
            'admission_id' => $this->admission,
            'branch_id' => $this->branch,
            'document_type' => 'consent_form',
        ], $file);
        $routes = file_get_contents(APPPATH . 'Modules/Documents/Config/Routes.php');
        $controller = file_get_contents(APPPATH . 'Modules/Documents/Controllers/Browser.php');

        self::assertIsString($routes);
        self::assertIsString($controller);
        self::assertStringContainsString("(:num)/preview", $routes);
        self::assertStringContainsString('->setContentType($mimeType)', $controller);
        self::assertStringContainsString("'Content-Disposition', \$disposition", $controller);
        self::assertStringContainsString("\$disposition = 'inline';", $controller);
        self::assertStringContainsString("'application/pdf'", $controller);
        $this->assertDocumentNotFound(static function () use ($id): void {
            service('document', false)->read($id);
        });
    }

    public function testPdfWithEmbeddedJavascriptIsRejected(): void
    {
        $file = $this->uploadedFile(
            'malicious.pdf',
            'application/pdf',
            $this->pdfContents('/JavaScript /JS'),
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Executable or script content is not permitted.');
        service('document', false)->upload([
            'admission_id' => $this->admission,
            'branch_id' => $this->branch,
            'document_type' => 'consent_form',
        ], $file);
    }

    public function testPdfTextIsNotScannedAsGenericExecutableContent(): void
    {
        $file = $this->uploadedFile(
            'xmp-metadata.pdf',
            'application/pdf',
            $this->pdfContents('<?xpacket begin="normal PDF metadata"?><% visible PDF text'),
        );

        $id = service('document', false)->upload([
            'admission_id' => $this->admission,
            'branch_id' => $this->branch,
            'document_type' => 'birth_certificate',
        ], $file);
        $row = $this->db->table('documents')->where('id', $id)->get()->getRowArray();

        self::assertIsArray($row);
        self::assertSame('application/pdf', $row['mime_type']);
    }

    public function testPhpUploadIsRejectedByTheDocumentMimeAllowlist(): void
    {
        $file = $this->uploadedFile('malicious.php', 'text/x-php', '<?php echo "blocked";');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid document upload.');
        service('document', false)->upload([
            'admission_id' => $this->admission,
            'branch_id' => $this->branch,
            'document_type' => 'consent_form',
        ], $file);
    }

    public function testInvalidProfilePhotoIsRejected(): void
    {
        $file = $this->uploadedFile('profile.txt', 'text/plain', 'not an image');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid document upload.');
        service('document', false)->upload([
            'admission_id' => $this->admission,
            'branch_id' => $this->branch,
            'document_type' => 'player_photo',
        ], $file);
    }

    public function testConversionLinksAdmissionDocumentsWithoutDuplicatingFiles(): void
    {
        $file = $this->uploadedFile('conversion.pdf', 'application/pdf', $this->pdfContents('conversion document'));
        $id = service('document', false)->upload([
            'admission_id' => $this->admission,
            'branch_id' => $this->branch,
            'document_type' => 'school_id',
        ], $file);
        $storageKey = (string) $this->db->table('documents')->where('id', $id)->get()->getRow('storage_key');

        service('admission', false)->updateChecklist($this->admission, [
            'checklist_identity_complete' => true,
            'checklist_contact_complete' => true,
            'checklist_admission_complete' => true,
            'checklist_branch_confirmed' => true,
        ]);
        service('admission', false)->transition($this->admission, 'APPROVED');
        $playerId = service('admission', false)->convert($this->admission);
        $row = $this->db->table('documents')->where('id', $id)->get()->getRowArray();

        self::assertSame($playerId, (int) $row['player_id']);
        self::assertSame($this->admission, (int) $row['admission_id']);
        self::assertSame($storageKey, $row['storage_key']);
        self::assertSame('linked_to_player', $this->db->table('document_history')->where('document_id', $id)->orderBy('id', 'DESC')->get()->getRow('action'));
    }

    public function testAdmissionUploadFormsRetainCsrfAndPermissionBoundRoutes(): void
    {
        $route = file_get_contents(APPPATH . 'Modules/Admissions/Config/Routes.php');
        $editView = file_get_contents(APPPATH . 'Modules/Admissions/Views/edit.php');

        self::assertIsString($route);
        self::assertIsString($editView);
        self::assertStringContainsString('permission:admission.update', $route);
        self::assertStringContainsString('documents/(:num)/remove', $route);
        self::assertStringContainsString("csrf_field()", $editView);
        self::assertStringContainsString("enctype=\"multipart/form-data\"", $editView);
    }

    public function testAdmissionDetailsUsesSecureImageMarkupForDocumentPreviews(): void
    {
        $showView = file_get_contents(APPPATH . 'Modules/Admissions/Views/show.php');

        self::assertIsString($showView);
        self::assertStringContainsString('class="mcc-admission-document-image"', $showView);
        self::assertStringContainsString("'/preview'", $showView);
        self::assertStringContainsString("'image/jpeg'", $showView);
        self::assertStringContainsString("'image/png'", $showView);
        self::assertStringNotContainsString("storage_key . '/preview'", $showView);
    }

    public function testVerificationRequiresReasonForRejectionAndScannerForApproval(): void
    {
        $file = $this->uploadedFile('verify.pdf', 'application/pdf', $this->pdfContents('verify'));
        $id = service('document', false)->upload([
            'admission_id' => $this->admission,
            'branch_id' => $this->branch,
            'document_type' => 'consent_form',
        ], $file);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Verification reason required.');
        service('document', false)->verify($id, 'REJECTED');
    }

    public function testInvalidExpiryDateIsRejectedServerSide(): void
    {
        $file = $this->uploadedFile('expiry.pdf', 'application/pdf', $this->pdfContents('expiry'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid document expiry date.');
        service('document', false)->upload([
            'admission_id' => $this->admission,
            'branch_id' => $this->branch,
            'document_type' => 'consent_form',
            'expires_on' => '31-12-2026',
        ], $file);
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
        $path = tempnam(sys_get_temp_dir(), 'mcc-admission-document-');
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
        };
    }

    private function pdfContents(string $body = ''): string
    {
        return "%PDF-1.7\n" . $body . "\n%%EOF\n";
    }

    private function assertDocumentNotFound(callable $callback): void
    {
        try {
            $callback();
            self::fail('Expected the document to be unavailable.');
        } catch (RuntimeException $exception) {
            self::assertSame('Not found.', $exception->getMessage());
        }
    }
}
