<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Libraries\Storage\LocalPrivateStorage;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class DocumentManagementContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'mcc-document-test-'
            . bin2hex(random_bytes(6));

        self::assertTrue(mkdir($this->root, 0700, true));
    }

    protected function tearDown(): void
    {
        $storage = new LocalPrivateStorage($this->root);
        if ($storage->exists('documents/test-object.pdf')) {
            $storage->delete('documents/test-object.pdf');
        }

        @rmdir($this->root . DIRECTORY_SEPARATOR . 'documents');
        @rmdir($this->root);
    }

    public function testPrivateDocumentRoutesAndStorageBoundaryExist(): void
    {
        $routes = file_get_contents(APPPATH . 'Modules/Documents/Config/Routes.php');
        $admissionRoutes = file_get_contents(APPPATH . 'Modules/Admissions/Config/Routes.php');
        $service = file_get_contents(APPPATH . 'Services/DocumentManagementService.php');

        self::assertIsString($routes);
        self::assertIsString($admissionRoutes);
        self::assertIsString($service);
        self::assertStringContainsString('permission:document.download', $routes);
        self::assertStringContainsString('(:num)/documents', $admissionRoutes);
        self::assertStringContainsString('permission:admission.update', $admissionRoutes);
        self::assertStringContainsString('LocalPrivateStorage', $service);
        self::assertStringNotContainsString('public/assets', $service);
        self::assertStringContainsString('auditPersistence', $service);
        self::assertStringContainsString('Executable or script content', $service);
        self::assertStringContainsString('admission_id', $service);
        $controller = file_get_contents(APPPATH . 'Modules/Documents/Controllers/Browser.php');

        self::assertStringContainsString('safeDownloadName', $controller);
        self::assertStringContainsString("->setBody('Not found.')", $controller);
        self::assertStringContainsString('public function show(int $id): string|ResponseInterface', $controller);
        self::assertStringContainsString("->setStatusCode(404)", $controller);
        self::assertStringNotContainsString("original_name'])", $controller);
        self::assertStringContainsString("PENDING", $service);
        self::assertStringContainsString("QUARANTINED", $service);
    }

    public function testDocumentViewsUseSharedResponsivePresentationWithoutPrivateMetadata(): void
    {
        $list = file_get_contents(APPPATH . 'Modules/Documents/Views/index.php');
        $detail = file_get_contents(APPPATH . 'Modules/Documents/Views/show.php');

        self::assertIsString($list);
        self::assertIsString($detail);
        foreach (['mcc-card', 'mcc-empty-state', 'mcc-status-badge'] as $class) {
            self::assertStringContainsString($class, $list);
            self::assertStringContainsString($class, $detail);
        }
        self::assertStringContainsString('mcc-table-wrap', $list);
        self::assertStringContainsString('mcc-detail-grid', $detail);
        self::assertStringContainsString('mcc-badge-success', $list);
        self::assertStringContainsString('mcc-badge-warning', $list);
        self::assertStringContainsString('mcc-badge-danger', $detail);
        self::assertStringContainsString('<th scope="col">Branch</th>', $list);
        self::assertStringContainsString('csrf_field()', $detail);
        self::assertStringContainsString('No documents available.', $list);
        self::assertStringContainsString('No previous versions or audit events are available.', $detail);
        self::assertStringNotContainsString("original_name'])", $list);
        self::assertStringNotContainsString("original_name']", $detail);
        self::assertStringNotContainsString('storage_key', $list);
        self::assertStringNotContainsString('storage_key', $detail);
        self::assertStringNotContainsString('View raw metadata', $detail);
        self::assertStringContainsString('name="document_type"', $list);
        self::assertStringContainsString('All document types', $list);
        self::assertStringContainsString('mcc-document-filter-form', $list);
    }

    public function testDocumentPolicyFoundationUsesForwardOnlyVerificationScanAndVersionFields(): void
    {
        $migration = file_get_contents(
            ROOTPATH . 'app/Database/Migrations/20260927000100_AddDocumentPolicyFoundation.php'
        );

        self::assertIsString($migration);
        foreach ([
            'verification_status',
            'verified_by',
            'verified_at',
            'verification_reason',
            'scanner_status',
            'scanner_checked_at',
            'scanner_reason',
            'version_number',
            'previous_version_id',
        ] as $field) {
            self::assertStringContainsString($field, $migration);
        }
        self::assertStringContainsString('QUARANTINED', $migration);
        self::assertStringContainsString('Forward-only', $migration);
    }

    public function testDownloadNameUsesSafeMetadataAndMimeExtension(): void
    {
        $controller = new \ReflectionClass(
            \App\Modules\Documents\Controllers\Browser::class
        );
        $method = $controller->getMethod('safeDownloadName');
        $method->setAccessible(true);

        $safePdfName = $method->invoke(null, [
            'document_type' => '../x"\r\n',
            'mime_type' => 'application/pdf',
            'original_name' => 'unsafe.exe',
        ]);

        self::assertMatchesRegularExpression('/^[A-Za-z0-9._-]+\.pdf$/', $safePdfName);
        self::assertStringNotContainsString('..', $safePdfName);
        self::assertStringNotContainsString('\\', $safePdfName);
        self::assertStringNotContainsString('/', $safePdfName);
        self::assertStringNotContainsString("\r", $safePdfName);
        self::assertStringNotContainsString("\n", $safePdfName);
        self::assertSame(
            'player_photo.png',
            $method->invoke(null, [
                'document_type' => 'player_photo',
                'mime_type' => 'image/png',
                'original_name' => 'photo.php',
            ])
        );
    }

    public function testStorageRejectsTraversalAndReadsOnlyPrivateObjects(): void
    {
        $storage = new LocalPrivateStorage($this->root);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid private storage key.');
        $storage->store('../outside.pdf', 'blocked');
    }

    public function testStorageUsesPrivateFilePermissionsAndSupportsCleanup(): void
    {
        $storage = new LocalPrivateStorage($this->root);
        $storage->store('documents/test-object.pdf', 'private content');

        self::assertSame('private content', $storage->read('documents/test-object.pdf'));
        self::assertTrue($storage->exists('documents/test-object.pdf'));

        $mode = fileperms(
            $this->root
            . DIRECTORY_SEPARATOR
            . 'documents'
            . DIRECTORY_SEPARATOR
            . 'test-object.pdf'
        );
        if (DIRECTORY_SEPARATOR !== '\\') {
            self::assertSame(0600, $mode & 0777);
        }

        $storage->delete('documents/test-object.pdf');
        self::assertFalse($storage->exists('documents/test-object.pdf'));
    }

    public function testStorageRejectsPublicWebRoot(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('public web root');

        new LocalPrivateStorage(FCPATH . 'private-documents');
    }
}
