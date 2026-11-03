<?php

declare(strict_types=1);

namespace Tests\Feature;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

final class DocumentBrowserRoutesTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $DBGroup = 'tests';
    protected $migrate = false;

    private int $actor;

    private int $branch;

    private int $otherBranch;

    protected function setUp(): void
    {
        parent::setUp();

        self::assertSame(
            'mcc_cricket_club_test',
            strtolower((string) $this->db->getDatabase())
        );

        $this->actor = $this->userId('test.mcc.superadmin@example.invalid');
        $this->branch = $this->branchId('TEST_MCC_VNS');
        $this->otherBranch = $this->branchId('TEST_MCC_DEL');

        if (min($this->actor, $this->branch, $this->otherBranch) < 1) {
            self::markTestSkipped('Document route fixtures are not available.');
        }
    }

    protected function tearDown(): void
    {
        session()->destroy();
        \Config\Services::resetSingle('authorization');
        parent::tearDown();
    }

    public function testAuthorizedDocumentDetailRenders(): void
    {
        $document = $this->insertDocument($this->branch);
        $this->authorize([$this->branch]);

        $response = $this->withSession(session()->get())->get('documents/' . $document);

        self::assertSame(200, $response->response()->getStatusCode());
        self::assertStringContainsString('Document detail', $response->getBody());
    }

    public function testCrossBranchDocumentDetailReturnsGenericNotFound(): void
    {
        $document = $this->insertDocument($this->branch);
        $this->authorize([$this->otherBranch]);

        $response = $this->withSession(session()->get())->get('documents/' . $document);

        self::assertSame(404, $response->response()->getStatusCode());
        self::assertStringContainsString('Not found.', $response->getBody());
    }

    /** @param list<int> $branches */
    private function authorize(array $branches): void
    {
        session()->set([
            'isLoggedIn' => true,
            'accountEnabled' => true,
            'user' => ['id' => $this->actor],
            'session_version' => 1,
            'permissions' => ['document.view'],
            'authorizedBranches' => array_map('strval', $branches),
        ]);
        \Config\Services::resetSingle('authorization');
    }

    private function insertDocument(int $branch): int
    {
        $token = bin2hex(random_bytes(6));
        $this->db->table('documents')->insert([
            'branch_id' => $branch,
            'document_type' => 'birth_certificate',
            'original_name' => 'TEST_MCC_DOCUMENT_' . $token . '.pdf',
            'storage_key' => 'documents/' . $branch . '/TEST_MCC_DOCUMENT_' . $token . '.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 12,
            'expires_on' => null,
            'status' => 'active',
            'uploaded_by' => $this->actor,
            'created_at' => '2026-10-01 10:00:00',
            'updated_at' => '2026-10-01 10:00:00',
            'verification_status' => 'PENDING',
            'scanner_status' => 'QUARANTINED',
            'version_number' => 1,
        ]);

        return (int) $this->db->insertID();
    }

    private function userId(string $email): int
    {
        return (int) $this->db->table('users')
            ->where('email', $email)
            ->get()
            ->getRow('id');
    }

    private function branchId(string $code): int
    {
        return (int) $this->db->table('branches')
            ->where('code', $code)
            ->get()
            ->getRow('id');
    }
}
