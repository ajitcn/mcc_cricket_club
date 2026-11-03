<?php

declare (strict_types=1);
namespace Tests\Database;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Security\Exceptions\SecurityException;
use CodeIgniter\HTTP\Files\UploadedFile;
use Config\Database;
final class CrmLeadBrowserSafeTest extends CIUnitTestCase
{
    use FeatureTestTrait;
    private object $database;
    private int $actor;
    private int $branch;
    private array $leadIds = [];
    private array $trialIds = [];
    private array $statusIds = [];
    protected function setUp(): void
    {
        parent::setUp();
        $this->database = Database::connect('tests');
        self::assertSame('mcc_cricket_club_test', strtolower((string) $this->database->getDatabase()));
        $row = $this->database->table('users')->where('email', 'test.mcc.superadmin@example.invalid')->get()->getRowArray();
        $this->actor = (int) ($row['id'] ?? 0);
        $this->branch = (int) $this->database->table('branches')->where('code', 'TEST_MCC_VNS')->get()->getRow('id');
        self::assertGreaterThan(0, $this->actor);
        self::assertGreaterThan(0, $this->branch);
    }
    protected function tearDown(): void
    {
        foreach ($this->trialIds as $id) {
            $this->database->table('trial_history')->where('trial_id', $id)->delete();
            $this->database->table('trials')->where('id', $id)->delete();
        }
        foreach ($this->leadIds as $id) {
            $this->database->table('lead_status_history')->where('lead_id', $id)->delete();
            $this->database->table('leads')->where('id', $id)->delete();
        }
        foreach ($this->statusIds as $id) {
            $this->database->table('lead_statuses')->where('id', $id)->delete();
        }
        session()->destroy();
        \Config\Services::resetSingle('authorization');
        parent::tearDown();
    }
    public function testServerRenderedLeadFormCreatesLead(): void
    {
        session()->set(['isLoggedIn' => true, 'accountEnabled' => true, 'user' => ['id' => $this->actor], 'session_version' => 1,
    'authorizedBranches' => [(string) $this->branch], 'permissions' => ['lead.create', 'lead.view', 'lead.update']]);
        \Config\Services::resetSingle('authorization');
        $page = $this->withSession(session()->get())->get('crm/leads/new');
        self::assertSame(200, $page->response()->getStatusCode());
        self::assertStringContainsString('<form class="row"', $page->getBody());
        self::assertSame(9, substr_count($page->getBody(), 'class="form-control mb-2"'));
        self::assertSame(8, substr_count($page->getBody(), 'class="col-12 col-md-4"'));
        self::assertMatchesRegularExpression('/<div class="col-12">\s*<label for="lead-remarks">/', $page->getBody());
        self::assertStringContainsString('<div class="col-12 mt-2 text-right">', $page->getBody());
        $response = $this->withSession(session()->get())->post('crm/leads', ['branch_id' => $this->branch, 'source' => 'TEST_MCC_SEED', 'status' => 'lead',
    'full_name' => 'TEST_MCC Lead ' . bin2hex(random_bytes(4)), 'email' => 'test.mcc.lead.' . bin2hex(random_bytes(4)) . '@example.invalid',
    'phone' => '9999999999', 'date_of_birth' => '2012-05-10', 'remarks' => 'TEST_MCC lead remarks', csrf_token() => csrf_hash()]);
        self::assertSame(302, $response->response()->getStatusCode());
        $location = (string) $response->response()->getHeaderLine('Location');
        self::assertStringContainsString('/crm/leads/', $location);
        $id = (int) preg_replace('/.*\/leads\/(\d+)(?:\/page)?(?:[?#].*)?$/', '$1', $location);
        self::assertGreaterThan(0, $id);
        $this->leadIds[] = $id;
        self::assertSame(1, $this->database->table('leads')->where('id', $id)->countAllResults());
        self::assertSame('TEST_MCC lead remarks', $this->database->table('leads')->where('id', $id)->get()->getRow('remarks'));
    }
    public function testUnauthenticatedAndUnauthorizedUsersCannotOpenCreatePage(): void
    {
        session()->destroy();
        \Config\Services::resetSingle('authorization');
        self::assertContains($this->get('crm/leads/new')->response()->getStatusCode(), [302, 401, 403]);
        session()->set(['isLoggedIn' => true, 'accountEnabled' => true, 'user' => ['id' => $this->actor], 'session_version' => 1,
    'authorizedBranches' => [(string) $this->branch], 'permissions' => ['lead.view']]);
        \Config\Services::resetSingle('authorization');
        self::assertContains($this->withSession(session()->get())->get('crm/leads/new')->response()->getStatusCode(), [302, 401, 403]);
    }
    public function testBrowserLeadPostRequiresCsrf(): void
    {
        $this->asAuthorizedUser();
        $this->expectException(SecurityException::class);
        $this->withSession(session()->get())->post('crm/leads', ['branch_id' => $this->branch, 'source' => 'TEST_MCC_SEED', 'status' => 'lead',
    'full_name' => 'TEST_MCC CSRF']);
    }
    public function testBrowserLeadValidationFailureRedirectsWithoutPersistence(): void
    {
        $this->asAuthorizedUser();
        $email = 'test.mcc.invalid.' . bin2hex(random_bytes(4)) . '@example.invalid';
        $response = $this->withSession(session()->get())->post('crm/leads', ['branch_id' => $this->branch, 'source' => '', 'full_name' => '',
    'email' => $email, csrf_token() => csrf_hash()]);
        self::assertSame(302, $response->response()->getStatusCode());
        self::assertSame(0, $this->database->table('leads')->where('email', $email)->countAllResults());
    }
    private function asAuthorizedUser(): void
    {
        session()->set(['isLoggedIn' => true, 'accountEnabled' => true, 'user' => ['id' => $this->actor], 'session_version' => 1,
    'authorizedBranches' => [(string) $this->branch], 'permissions' => ['rbac.manage', 'lead.create', 'lead.view', 'lead.update', 'trial.book']]);
        \Config\Services::resetSingle('authorization');
    }
    public function testAuthorizedUserCanViewAndEditLeadWithContactDetails(): void
    {
        $this->asAuthorizedUser();
        $id = (int) service('crm')->create(['branch_id' => $this->branch, 'source' => 'TEST_MCC_SEED', 'status' => 'lead',
    'full_name' => 'TEST_MCC Edit Lead', 'email' => 'test.mcc.edit.' . bin2hex(random_bytes(3)) . '@example.invalid', 'phone' => '8888888888'])['id'];
        $this->leadIds[] = $id;
        $view = $this->withSession(session()->get())->get('crm/leads/' . $id);
        self::assertSame(200, $view->response()->getStatusCode());
        self::assertStringContainsString('mailto:', $view->getBody());
        self::assertStringContainsString('tel:', $view->getBody());
        $edit = $this->withSession(session()->get())->get('crm/leads/' . $id . '/edit');
        self::assertSame(200, $edit->response()->getStatusCode());
        self::assertMatchesRegularExpression('/<select class="form-control mb-2" id="lead-branch" name="branch_id" disabled>\s*<option value="' . $this->branch . '" selected>/', $edit->getBody());
        $response = $this->withSession(session()->get())->post('crm/leads/' . $id . '/edit', ['full_name' => 'TEST_MCC Edited Lead',
    'email' => 'test.mcc.updated.' . bin2hex(random_bytes(3)) . '@example.invalid', 'phone' => '7777777777', 'date_of_birth' => '2011-01-01',
    'source' => 'TEST_MCC_SEED', csrf_token() => csrf_hash()]);
        self::assertSame(302, $response->response()->getStatusCode());
    }
    public function testLeadDetailsAndEditEnforceBranchAndPermission(): void
    {
        $this->asAuthorizedUser();
        self::assertContains($this->withSession(session()->get())->get('crm/leads/3132')->response()->getStatusCode(), [403, 404]);
        session()->set(['permissions' => ['lead.view']]);
        \Config\Services::resetSingle('authorization');
        self::assertContains($this->withSession(session()->get())->get('crm/leads/3130/edit')->response()->getStatusCode(), [302, 403, 404]);
    }
    public function testActiveAdminStatusCanUpdateLeadAndInvalidOrUnauthorizedStatusIsRejected(): void
    {
        $this->asAuthorizedUser();
        $statusName = 'test_mcc_status_' . bin2hex(random_bytes(4));
        $statusId = service('admin')->createLeadStatus($statusName);
        $this->statusIds[] = $statusId;
        $leadId = (int) service('crm')->create(['branch_id' => $this->branch, 'source' => 'TEST_MCC_SEED', 'status' => 'lead',
    'full_name' => 'TEST_MCC Status Lead ' . bin2hex(random_bytes(3))])['id'];
        $this->leadIds[] = $leadId;
        service('crm')->status($leadId, $this->branch, $statusName);
        $row = $this->database->table('leads')->where('id', $leadId)->get()->getRowArray();
        self::assertSame($statusName, $row['status']);
        self::assertSame(1, $this->database->table('audit_logs')->where(['entity_type' => 'lead', 'entity_id' => (string) $leadId,
    'action' => 'lead_status_changed'])->countAllResults());
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid status.');
        service('crm')->status($leadId, $this->branch, 'TEST_MCC_NONEXISTENT_STATUS');
    }
    public function testUnauthorizedStatusUpdateIsRejected(): void
    {
        $this->asAuthorizedUser();
        $leadId = (int) service('crm')->create(['branch_id' => $this->branch, 'source' => 'TEST_MCC_SEED', 'status' => 'lead',
    'full_name' => 'TEST_MCC Unauthorized Status ' . bin2hex(random_bytes(3))])['id'];
        $this->leadIds[] = $leadId;
        session()->set('permissions', ['lead.view']);
        \Config\Services::resetSingle('authorization');
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Forbidden');
        service('crm')->status($leadId, $this->branch, 'lead');
    }
    public function testBrowserStatusUpdateRedirectsWithOptionalReasonAndAudits(): void
    {
        $this->asAuthorizedUser();
        $statusName = 'test_mcc_callback_' . bin2hex(random_bytes(4));
        $statusId = service('admin')->createLeadStatus($statusName);
        $this->statusIds[] = $statusId;
        $leadId = (int) service('crm')->create(['branch_id' => $this->branch, 'source' => 'TEST_MCC_SEED', 'status' => 'lead',
    'full_name' => 'TEST_MCC Browser Status ' . bin2hex(random_bytes(3))])['id'];
        $this->leadIds[] = $leadId;
        $response = $this->withSession(session()->get())->post('crm/leads/' . $leadId . '/status', ['branch_id' => $this->branch, 'status' => $statusName,
    'reason' => 'TEST_MCC follow-up completed', csrf_token() => csrf_hash()]);
        self::assertSame(302, $response->response()->getStatusCode());
        self::assertStringContainsString('/crm/leads/' . $leadId, (string) $response->response()->getHeaderLine('Location'));
        self::assertSame($statusName, $this->database->table('leads')->where('id', $leadId)->get()->getRow('status'));
        self::assertSame('TEST_MCC follow-up completed', $this->database->table('lead_status_history')->where(['lead_id' => $leadId,
    'new_status' => $statusName])->get()->getRow('reason'));
    }
    public function testInactiveStatusAndMissingLeadAreRejected(): void
    {
        $this->asAuthorizedUser();
        $statusName = 'test_mcc_inactive_' . bin2hex(random_bytes(4));
        $statusId = service('admin')->createLeadStatus($statusName);
        $this->statusIds[] = $statusId;
        service('admin')->setLeadStatusActive($statusId, false);
        $leadId = (int) service('crm')->create(['branch_id' => $this->branch, 'source' => 'TEST_MCC_SEED', 'status' => 'lead',
    'full_name' => 'TEST_MCC Inactive Status ' . bin2hex(random_bytes(3))])['id'];
        $this->leadIds[] = $leadId;
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid status.');
        service('crm')->status($leadId, $this->branch, $statusName);
    }
    public function testStatusUpdateRejectsMissingLead(): void
    {
        $this->asAuthorizedUser();
        $statusName = 'test_mcc_missing_lead_' . bin2hex(random_bytes(4));
        $statusId = service('admin')->createLeadStatus($statusName);
        $this->statusIds[] = $statusId;
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Not found.');
        service('crm')->status(999999999, $this->branch, $statusName);
    }
    public function testLeadListPaginationAndCombinedFiltersStayBranchScoped(): void
    {
        $this->asAuthorizedUser();
        $email = 'test.mcc.page.' . bin2hex(random_bytes(4)) . '@example.invalid';
        $token = bin2hex(random_bytes(4));
        $leadId = (int) service('crm')->create(['branch_id' => $this->branch, 'source' => 'TEST_MCC_SEED', 'status' => 'lead',
    'full_name' => 'TEST_MCC Paginated Lead ' . $token, 'email' => $email])['id'];
        $this->leadIds[] = $leadId;
        $result = service('crm')->paginatedLeads(['q' => $token, 'source' => 'TEST_MCC_SEED', 'status' => 'lead',
    'branch_id' => (string) $this->branch, 'created_from' => date('Y-m-d'), 'created_to' => date('Y-m-d')], 1, 1);
        self::assertSame(1, $result['total']);
        self::assertSame(1, count($result['leads']));
        self::assertSame($leadId, (int) $result['leads'][0]['id']);
        self::assertSame(1, $result['pages']);
        $outsideBranch = service('crm')->paginatedLeads(['branch_id' => '999999999'], 1, 20);
        self::assertSame(0, $outsideBranch['total']);
    }
    public function testLeadListExposesBookTrialWorkflowOnlyUntilBookingExists(): void
    {
        $this->asAuthorizedUser();
        $leadId = (int) service('crm')->create(['branch_id' => $this->branch, 'source' => 'TEST_MCC_SEED', 'status' => 'lead',
    'full_name' => 'TEST_MCC Book Trial ' . bin2hex(random_bytes(3))])['id'];
        $this->leadIds[] = $leadId;
        $page = $this->withSession(session()->get())->get('crm/leads');
        self::assertSame(200, $page->response()->getStatusCode());
        self::assertStringContainsString('Book Trial', $page->getBody());
        $form = $this->withSession(session()->get())->get('crm/trials/from-lead/' . $leadId);
        self::assertSame(200, $form->response()->getStatusCode());
        self::assertStringContainsString('Trial date', $form->getBody());
        $this->database->table('grounds')->insert(['branch_id' => $this->branch, 'name' => 'TEST_MCC Ground', 'ground_type' => 'outdoor_ground',
    'description' => null, 'is_active' => 1, 'created_by' => $this->actor, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
        $groundId = (int) $this->database->insertID();
        $trialId = service('trial')->book($leadId, ['trial_date' => date('Y-m-d', strtotime('+2 days')), 'trial_time' => '10:00',
    'location' => 'TEST_MCC Ground']);
        $this->trialIds[] = $trialId;
        $this->database->table('grounds')->where('id', $groundId)->delete();
        $result = service('crm')->paginatedLeads(['q' => 'TEST_MCC Book Trial'], 1, 20);
        self::assertFalse((bool) $result['leads'][0]['can_book_trial']);
    }

    public function testCsvImportCreatesValidRowsAndReportsRowErrors(): void
    {
        $this->asAuthorizedUser();
        $token = bin2hex(random_bytes(4));
        $csv = "Name,Phone,Email,Source,Status,Branch,Assigned To,Notes,Date of Birth\n"
            . 'TEST_MCC Imported ' . $token . ',9999999999,test.mcc.import.' . $token . '@example.invalid,TEST_MCC_SEED,lead,TEST_MCC_VNS,test.mcc.staff.del@example.invalid,Imported note,2012-05-10' . "\n"
            . ',9999999998,bad.' . $token . '@example.invalid,TEST_MCC_SEED,lead,TEST_MCC_VNS,,,Missing name,2012-05-10\n';
        $path = tempnam(sys_get_temp_dir(), 'mcc-import-');
        file_put_contents($path, $csv);
        try {
            $result = service('crm', false)->importCsv($this->mockUpload($path, 'leads.csv', 'text/csv'));
        } finally {
            unlink($path);
        }

        self::assertSame(2, $result['total_rows']);
        self::assertSame(1, $result['imported']);
        self::assertSame(1, $result['failed']);
        self::assertCount(1, $result['errors']);
        $row = $this->database->table('leads')->where('email', 'test.mcc.import.' . $token . '@example.invalid')->get()->getRowArray();
        self::assertNotEmpty($row);
        self::assertSame('TEST_MCC Imported ' . $token, $row['full_name']);
        self::assertSame($this->branch, (int) $row['branch_id']);
        self::assertSame((int) $this->database->table('users')->where('email', 'test.mcc.staff.del@example.invalid')->get()->getRow('id'), (int) $row['owner_user_id']);
        $this->leadIds[] = (int) $row['id'];
    }

    public function testCsvImportRejectsEmptyAndInvalidFiles(): void
    {
        $this->asAuthorizedUser();
        $emptyPath = tempnam(sys_get_temp_dir(), 'mcc-import-');
        file_put_contents($emptyPath, "Name,Branch\n");
        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('The CSV file contains no data rows.');
            service('crm', false)->importCsv($this->mockUpload($emptyPath, 'empty.csv', 'text/csv'));
        } finally {
            unlink($emptyPath);
        }
    }

    public function testAuthorizedUserCanDownloadTheLeadImportSampleCsv(): void
    {
        $this->asAuthorizedUser();
        $response = $this->withSession(session()->get())->get('crm/leads/import/sample');

        self::assertSame(200, $response->response()->getStatusCode());
        $disposition = strtolower($response->response()->getHeaderLine('Content-Disposition'));
        self::assertStringContainsString('attachment', $disposition);
        self::assertStringContainsString('lead-import-sample.csv', $disposition);
        self::assertStringContainsString('Name,Phone,Email,Source,Status,Branch,Assigned To,Notes,Date of Birth', $response->getBody());
    }

    public function testLeadImportPopupContainsDistinctResultStates(): void
    {
        $this->asAuthorizedUser();
        $page = $this->withSession(session()->get())->get('crm/leads');

        self::assertSame(200, $page->response()->getStatusCode());
        self::assertStringContainsString('data-lead-import-success', $page->getBody());
        self::assertStringContainsString('data-lead-import-warning', $page->getBody());
        self::assertStringContainsString('data-lead-import-error', $page->getBody());
        self::assertStringContainsString('data-lead-import-error-message', $page->getBody());
    }

    public function testCsvImportRejectsNonCsvFiles(): void
    {
        $this->asAuthorizedUser();
        $path = tempnam(sys_get_temp_dir(), 'mcc-import-');
        file_put_contents($path, "Name,Branch\nTEST_MCC Wrong Type,TEST_MCC_VNS\n");
        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('Only CSV files are supported.');
            service('crm', false)->importCsv($this->mockUpload($path, 'leads.txt', 'text/plain'));
        } finally {
            unlink($path);
        }
    }

    public function testCsvImportRejectsInvalidBranchAssignmentAndUnauthorizedImport(): void
    {
        $this->asAuthorizedUser();
        $path = tempnam(sys_get_temp_dir(), 'mcc-import-');
        file_put_contents($path, "Name,Source,Status,Branch,Assigned To\n"
            . "TEST_MCC Invalid Branch,TEST_MCC_SEED,lead,NOT_AUTHORIZED,test.mcc.reception@example.invalid\n"
            . "TEST_MCC Invalid Owner,TEST_MCC_SEED,lead,TEST_MCC_VNS,not-a-user@example.invalid\n");
        try {
            $result = service('crm', false)->importCsv($this->mockUpload($path, 'invalid.csv', 'text/csv'));
        } finally {
            unlink($path);
        }
        self::assertSame(0, $result['imported']);
        self::assertSame(2, $result['failed']);
        self::assertSame('Branch is invalid or unauthorized.', $result['errors'][0]['message']);
        self::assertSame('Assigned user is invalid.', $result['errors'][1]['message']);

        session()->set(['permissions' => ['lead.view']]);
        \Config\Services::resetSingle('authorization');
        $unauthorizedPath = tempnam(sys_get_temp_dir(), 'mcc-import-');
        file_put_contents($unauthorizedPath, "Name,Source,Status,Branch\nTEST_MCC Unauthorized,TEST_MCC_SEED,lead,TEST_MCC_VNS\n");
        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('Forbidden');
            service('crm', false)->importCsv($this->mockUpload($unauthorizedPath, 'unauthorized.csv', 'text/csv'));
        } finally {
            unlink($unauthorizedPath);
        }
    }

    private function mockUpload(string $path, string $name, string $mime): UploadedFile
    {
        $file = $this->createMock(UploadedFile::class);
        $file->method('isValid')->willReturn(true);
        $file->method('hasMoved')->willReturn(false);
        $file->method('getSize')->willReturn(filesize($path));
        $file->method('getClientExtension')->willReturn(pathinfo($name, PATHINFO_EXTENSION));
        $file->method('getMimeType')->willReturn($mime);
        $file->method('getTempName')->willReturn($path);
        return $file;
    }
}
