<?php

declare(strict_types=1);

namespace Tests\Database;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use RuntimeException;

final class DocumentPolicySafeTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $DBGroup = 'tests';
    protected $migrate = false;

    private object $database;

    private int $actor;

    private int $branch;

    private int $otherBranch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->database = $this->db;

        self::assertSame(
            'mcc_cricket_club_test',
            strtolower((string) $this->database->getDatabase())
        );

        $this->actor = (int) $this->database->table('users')
            ->where('email', 'test.mcc.superadmin@example.invalid')
            ->get()
            ->getRow('id');
        $this->branch = (int) $this->database->table('branches')
            ->where('code', 'TEST_MCC_VNS')
            ->get()
            ->getRow('id');
        $this->otherBranch = (int) $this->database->table('branches')
            ->where('code', 'TEST_MCC_DEL')
            ->get()
            ->getRow('id');
    }

    protected function tearDown(): void
    {
        session()->destroy();
        \Config\Services::resetSingle('authorization');
        parent::tearDown();
    }

    public function testCrossBranchDocumentLookupIsNormalizedToNotFound(): void
    {
        $token = bin2hex(random_bytes(6));
        $this->database->table('documents')->insert([
            'branch_id' => $this->branch,
            'document_type' => 'birth_certificate',
            'original_name' => 'TEST_MCC_DOCUMENT_' . $token . '.pdf',
            'storage_key' => 'documents/' . $this->branch . '/TEST_MCC_DOCUMENT_' . $token . '.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 12,
            'status' => 'active',
            'uploaded_by' => $this->actor,
            'created_at' => '2026-10-01 10:00:00',
            'updated_at' => '2026-10-01 10:00:00',
            'verification_status' => 'PENDING',
            'scanner_status' => 'QUARANTINED',
            'version_number' => 1,
        ]);
        $documentId = (int) $this->database->insertID();

        $this->authorize([$this->otherBranch]);

        try {
            service('document')->find($documentId);
            self::fail('Cross-branch document lookup was allowed.');
        } catch (RuntimeException $exception) {
            self::assertSame('Not found.', $exception->getMessage());
        }
    }

    /** @param list<int> $branches */
    private function authorize(array $branches): void
    {
        session()->set([
            'isLoggedIn' => true,
            'accountEnabled' => true,
            'user' => ['id' => $this->actor],
            'permissions' => ['document.view'],
            'authorizedBranches' => array_map('strval', $branches),
        ]);
        \Config\Services::resetSingle('authorization');
    }
}
