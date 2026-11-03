<?php

declare (strict_types=1);
namespace Tests\Database;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
final class AdmissionsBrowserSafeTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    protected $DBGroup = 'tests';
    protected $migrate = false;
    private int $admission;
    private int $lead;
    private int $branch;
    private int $otherBranch;
    private int $actor;
    private int $otherActor;
    protected function setUp(): void
    {
        parent::setUp();
        self::assertSame('mcc_cricket_club_test', $this->db->getDatabase());
        $actor = $this->db->table('users')->where('email', 'test.mcc.manager.vns@example.invalid')->get()->getRowArray();
        $otherActor = $this->db->table('users')->where('email', 'test.mcc.staff.del@example.invalid')->get()->getRowArray();
        $branch = $this->db->table('branches')->where('code', 'TEST_MCC_VNS')->get()->getRowArray();
        $otherBranch = $this->db->table('branches')->where('code', 'TEST_MCC_DEL')->get()->getRowArray();
        $admission = $this->db->table('admissions')->where('branch_id', (int) ($branch['id'] ?? 0))->orderBy('id', 'ASC')->get()->getRowArray();
        self::assertIsArray($admission);
        self::assertIsArray($actor);
        self::assertIsArray($otherActor);
        self::assertIsArray($branch);
        self::assertIsArray($otherBranch);
        $this->admission = (int) $admission['id'];
        $this->lead = (int) $admission['lead_id'];
        $this->branch = (int) $branch['id'];
        $this->otherBranch = (int) $otherBranch['id'];
        $this->actor = (int) $actor['id'];
        $this->otherActor = (int) $otherActor['id'];
        $this->authorize(['admission.view', 'admission.create', 'admission.update', 'player.convert', 'document.upload'], [$this->branch]);
    }
    public function testCanonicalBrowserListAndDetailUseSharedNavigationAndViews(): void
    {
        $admissionNumber = (string) $this->db->table('admissions')
            ->where('id', $this->admission)
            ->get()
            ->getRow('admission_number');
        $list = $this->withSession(session()->get())->get(
            'admissions?admission_number=' . urlencode($admissionNumber),
        );
        self::assertSame(200, $list->response()->getStatusCode());
        $body = $list->getBody();
        self::assertStringContainsString('Admissions', $body);
        self::assertStringContainsString('class="mcc-table-responsive"', $body);
        self::assertStringContainsString('/admissions/' . $this->admission . '/page', $body);
        self::assertStringContainsString('/edit"', $body);
        self::assertStringNotContainsString('data-admission-action="edit"', $body);
        self::assertStringContainsString('data-admission-action="profile-photo"', $body);
        self::assertStringContainsString('Upload Profile Image', $body);
        self::assertStringContainsString('name="profile_photo"', $body);
        self::assertStringContainsString('accept="image/jpeg,image/png"', $body);
        self::assertStringNotContainsString('/edit?fragment=1', $body);
        self::assertStringContainsString('Actions <span aria-hidden="true">', $body);
        self::assertStringContainsString('data-admission-action="status"', $body);
        self::assertStringContainsString(
            'href="' . site_url('admissions/' . $this->admission . '/update-coaches') . '"',
            $body,
        );
        self::assertStringContainsString('>Update Coaches</span>', $body);
        $tableWrapperPosition = strpos($body, '<div class="mcc-table-responsive">');
        $listSectionEndPosition = strpos($body, '</section>', $tableWrapperPosition);
        $modalPosition = strpos($body, '<div class="mcc-modal"');
        self::assertNotFalse($tableWrapperPosition);
        self::assertNotFalse($listSectionEndPosition);
        self::assertNotFalse($modalPosition);
        self::assertGreaterThan($listSectionEndPosition, $modalPosition);
        foreach ([
            'view' => 'fa-eye',
            'edit' => 'fa-pen-to-square',
            'status' => 'fa-arrows-rotate',
            'complete' => 'fa-circle-check',
            'convert' => 'fa-user-plus',
            'document' => 'fa-file-import',
        ] as $action => $icon) {
            if (str_contains($list->getBody(), 'data-admission-action="' . $action . '"')) {
                self::assertStringContainsString('fa-solid ' . $icon, $list->getBody());
            }
        }
        self::assertStringContainsString('data-admission-modal', $list->getBody());
        self::assertStringNotContainsString('data-admission-feedback', $list->getBody());
        self::assertStringContainsString('data-admission-response', $list->getBody());
        self::assertStringContainsString('data-admission-modal-frame', $list->getBody());
        self::assertStringNotContainsString('data-admission-panel="checklist"', $list->getBody());
        self::assertStringContainsString('data-admission-panel="status"', $list->getBody());
        self::assertStringContainsString('data-admission-panel="convert"', $list->getBody());
        self::assertStringContainsString('mcc-nav-link is-active', $list->getBody());
        $detail = $this->withSession(session()->get())->get('admissions/' . $this->admission . '/page');
        self::assertSame(200, $detail->response()->getStatusCode());
        self::assertStringNotContainsString('Readiness', $detail->getBody());
        self::assertStringNotContainsString('Phase 1 checklist (AD-02)', $detail->getBody());
        self::assertStringContainsString('Status and conversion', $detail->getBody());
        self::assertStringContainsString(
            'href="' . site_url('admissions/' . $this->admission . '/update-coaches') . '"',
            $detail->getBody(),
        );
        $storedAdmission = $this->db->table('admissions')->where('id', $this->admission)->get()->getRowArray();
        $storedAdmissionDate = $storedAdmission['admission_date'] ?? $storedAdmission['joining_date'] ?? null;
        $storedPeriod = $this->db->table('admission_periods')
            ->where('admission_id', $this->admission)
            ->orderBy('period_number', 'DESC')
            ->get()
            ->getRowArray();
        if ((string) $storedAdmissionDate === '') {
            $storedAdmissionDate = $storedPeriod['start_date'] ?? null;
        }
        $expectedAdmissionDate = mcc_user_date($storedAdmissionDate, 'Not recorded');
        self::assertStringContainsString('Admission Date', $detail->getBody());
        self::assertStringContainsString(esc($expectedAdmissionDate), $detail->getBody());
        if (is_array($storedPeriod)) {
            self::assertStringContainsString('Admission Start Date', $detail->getBody());
            self::assertStringContainsString('Admission End Date', $detail->getBody());
        }
        $fragment = $this->withSession(session()->get())->get('admissions/' . $this->admission . '/page?fragment=1');
        self::assertSame(200, $fragment->response()->getStatusCode());
        self::assertStringNotContainsString('Readiness', $fragment->getBody());
        self::assertStringNotContainsString('Phase 1 checklist (AD-02)', $fragment->getBody());
        self::assertStringNotContainsString('mcc-sidebar', $fragment->getBody());
        self::assertStringNotContainsString('mcc-footer', $fragment->getBody());
        self::assertStringContainsString(
            'href="' . site_url('admissions/' . $this->admission . '/update-coaches') . '"',
            $fragment->getBody(),
        );
        $editPage = $this->withSession(session()->get())->get('admissions/' . $this->admission . '/edit');
        self::assertSame(200, $editPage->response()->getStatusCode());
        self::assertStringContainsString('class="mcc-admission-form"', $editPage->getBody());
        self::assertStringContainsString('Save Changes', $editPage->getBody());
        self::assertStringContainsString('data-mcc-date-picker', $editPage->getBody());
        self::assertStringContainsString('data-mcc-date-format="d-m-Y"', $editPage->getBody());
        self::assertStringContainsString('data-admission-response', $editPage->getBody());
        self::assertStringContainsString('mcc-sidebar', $editPage->getBody());
        self::assertStringContainsString('mcc-footer', $editPage->getBody());
        self::assertStringNotContainsString('mcc-modal', $editPage->getBody());
        if ($this->db->table('admissions')->where('id', $this->admission)->get()->getRow('converted_player_id') !== null) {
            self::assertStringContainsString('Player #', $detail->getBody());
        }
        self::assertStringNotContainsString('Save checklist', $detail->getBody());
        self::assertStringNotContainsString('Change status', $detail->getBody());
        self::assertStringNotContainsString('Convert to player', $detail->getBody());
        self::assertStringNotContainsString('Edit admission', $detail->getBody());
        self::assertStringContainsString('Back to Admissions', $detail->getBody());
        $this->expectException(\CodeIgniter\Exceptions\PageNotFoundException::class);
        $this->withSession(session()->get())->get('admissions/' . $this->admission);
    }

    public function testAdmissionListAndDetailRenderStoredNumberNotPrimaryId(): void
    {
        $row = $this->db->table('admissions')->where('id', $this->admission)->get()->getRowArray();
        self::assertIsArray($row);

        $originalNumber = $row['admission_number'] ?? null;
        $knownNumber = 'MCC-999998';

        try {
            $updated = $this->db->table('admissions')
                ->where('id', $this->admission)
                ->update(['admission_number' => $knownNumber]);
            self::assertTrue($updated);

            $list = $this->withSession(session()->get())->get(
                'admissions?admission_number=' . urlencode($knownNumber),
            );
            $detail = $this->withSession(session()->get())->get('admissions/' . $this->admission . '/page');

            self::assertStringContainsString('#' . $knownNumber, $list->getBody());
            self::assertStringContainsString('Admission #' . $knownNumber, $detail->getBody());
            self::assertStringNotContainsString('Admission #' . $this->admission, $detail->getBody());

            $this->db->table('admissions')
                ->where('id', $this->admission)
                ->update(['admission_number' => null]);

            $sourceFilter = $row['lead_id'] === null ? 'direct' : 'lead';
            $missingList = $this->withSession(session()->get())->get(
                'admissions?status=' . urlencode((string) $row['status']) . '&source=' . $sourceFilter,
            );
            $missingDetail = $this->withSession(session()->get())->get('admissions/' . $this->admission . '/page');

            self::assertStringContainsString('#Missing number', $missingList->getBody());
            self::assertStringContainsString('Admission #Missing number', $missingDetail->getBody());
            self::assertStringNotContainsString('Admission #' . $this->admission, $missingDetail->getBody());
        } finally {
            $this->db->table('admissions')
                ->where('id', $this->admission)
                ->update(['admission_number' => $originalNumber]);
        }
    }

    public function testAdmissionPaginationHasStableRowsAndAuthorizedBranchScope(): void
    {
        $service = service('admission', false);
        $filters = ['q' => 'TEST_MCC'];
        $result = $service->paginatedForAuthorizedBranches($filters, 1, 2);
        $allRows = $service->listForAuthorizedBranches($filters);
        $pagedIds = [];

        for ($page = 1; $page <= $result['pages']; $page++) {
            $pageResult = $service->paginatedForAuthorizedBranches($filters, $page, 2);

            self::assertSame($result['total'], $pageResult['total']);
            self::assertSame($page, $pageResult['page']);

            $pagedIds = array_merge(
                $pagedIds,
                array_map(
                    static fn (array $row): int => (int) $row['id'],
                    $pageResult['admissions']
                )
            );

            foreach ($pageResult['admissions'] as $row) {
                self::assertSame($this->branch, (int) $row['branch_id']);
                self::assertSame(
                    (string) $row['admission_number'],
                    (string) $this->db->table('admissions')
                        ->where('id', $row['id'])
                        ->get()
                        ->getRow('admission_number')
                );
            }
        }

        $expectedIds = array_map(
            static fn (array $row): int => (int) $row['id'],
            $allRows
        );

        self::assertSame($expectedIds, $pagedIds);
        self::assertCount(count(array_unique($pagedIds)), $pagedIds);
    }

    public function testAdmissionDownloadUsesCanonicalNumberAndDoesNotChangeAdmissionData(): void
    {
        $before = $this->db->table('admissions')->where('id', $this->admission)->get()->getRowArray();
        $historyBefore = $this->db->table('admission_status_history')->where('admission_id', $this->admission)->countAllResults();
        $followUpsBefore = $this->db->table('admission_followups')->where('admission_id', $this->admission)->countAllResults();

        $download = $this->withSession(session()->get())->get(
            'admissions/' . $this->admission . '/download'
        );

        self::assertSame(200, $download->response()->getStatusCode());
        self::assertStringStartsWith('%PDF-', $download->response()->getBody());
        self::assertStringContainsString(
            'MCC-Admission-' . (string) $before['admission_number'] . '.pdf',
            $download->response()->getHeaderLine('Content-Disposition')
        );
        self::assertSame($before, $this->db->table('admissions')->where('id', $this->admission)->get()->getRowArray());
        self::assertSame($historyBefore, $this->db->table('admission_status_history')->where('admission_id', $this->admission)->countAllResults());
        self::assertSame($followUpsBefore, $this->db->table('admission_followups')->where('admission_id', $this->admission)->countAllResults());
    }

    public function testAdmissionBrowserAccessIsPermissionAndBranchScoped(): void
    {
        $this->authorize(['admission.view'], [$this->branch]);
        $readOnlyList = $this->withSession(session()->get())->get('admissions');
        self::assertStringNotContainsString('/admissions/' . $this->admission . '/edit', $readOnlyList->getBody());
        self::assertStringNotContainsString('data-admission-action="edit"', $readOnlyList->getBody());
        self::assertStringNotContainsString('data-admission-action="checklist"', $readOnlyList->getBody());
        self::assertStringNotContainsString('data-admission-action="status"', $readOnlyList->getBody());
        self::assertStringNotContainsString(
            '/admissions/' . $this->admission . '/update-coaches',
            $readOnlyList->getBody(),
        );
        $readOnly = $this->withSession(session()->get())->get('admissions/' . $this->admission . '/page');
        self::assertSame(200, $readOnly->response()->getStatusCode());
        self::assertStringNotContainsString('Save checklist', $readOnly->getBody());
        self::assertStringNotContainsString('Change status', $readOnly->getBody());
        self::assertSame(
            403,
            $this->withSession(session()->get())->get(
                'admissions/' . $this->admission . '/update-coaches'
            )->response()->getStatusCode(),
        );
        $this->authorize(['lead.view'], [$this->branch]);
        self::assertSame(403, $this->withSession(session()->get())->get('admissions')->response()->getStatusCode());
        $this->authorize(['admission.view'], [$this->otherBranch], $this->otherActor);
        $otherBranch = $this->withSession(session()->get())->get('admissions');
        self::assertSame(200, $otherBranch->response()->getStatusCode());
        $leadName = (string) $this->db->table('leads')->where('id', $this->lead)->get()->getRow('full_name');
        self::assertStringNotContainsString($leadName, $otherBranch->getBody());
        self::assertSame(403, $this->withSession(session()->get())->get('admissions/' . $this->admission . '/page')->response()->getStatusCode());
    }
    public function testDirectAdmissionIsListedAndRenderedWithoutLead(): void
    {
        $direct = $this->db->table('admissions')->where('applicant_email', 'test.mcc.direct.admission@example.invalid')->get()->getRowArray();
        self::assertIsArray($direct);
        $list = $this->withSession(session()->get())->get('admissions');
        self::assertStringContainsString('name="q"', $list->getBody());
        $filtered = $this->withSession(session()->get())->get(
            'admissions?source=direct&admission_number=' . urlencode((string) $direct['admission_number']),
        );
        self::assertSame(200, $filtered->response()->getStatusCode());
        self::assertStringContainsString('Direct Admission', $filtered->getBody());
        self::assertStringContainsString('TEST_MCC_DIRECT_ADMISSION', $filtered->getBody());
        $detail = $this->withSession(session()->get())->get('admissions/' . (int) $direct['id'] . '/page');
        self::assertSame(200, $detail->response()->getStatusCode());
        self::assertStringContainsString('TEST_MCC_DIRECT_ADMISSION', $detail->getBody());
        self::assertStringNotContainsString('crm/leads/0', $detail->getBody());
        $edit = $this->withSession(session()->get())->get('admissions/' . (int) $direct['id'] . '/edit');
        self::assertSame(200, $edit->response()->getStatusCode());
        self::assertStringContainsString('Save Changes', $edit->getBody());
        self::assertStringContainsString('data-admission-submit-feedback', $edit->getBody());
        self::assertStringContainsString('data-admission-response', $edit->getBody());
    }
    public function testAdmissionListUsesPageNavigationForEditAndPopupTargetsForOtherActions(): void
    {
        $list = $this->withSession(session()->get())->get('admissions');
        $body = $list->getBody();

        foreach (['view', 'status'] as $action) {
            self::assertStringContainsString('data-admission-action="' . $action . '"', $body);
        }

        self::assertStringContainsString(
            'href="' . site_url('admissions/' . $this->admission . '/update-coaches') . '"',
            $body,
        );
        self::assertStringContainsString('>Update Coaches</span>', $body);

        self::assertStringNotContainsString('data-admission-action="checklist"', $body);

        self::assertStringContainsString('/edit"', $body);
        self::assertStringNotContainsString('data-admission-action="edit"', $body);

        self::assertStringContainsString('data-admission-modal-close', $body);
        self::assertStringContainsString('data-admission-modal-frame', $body);
        foreach (['status', 'convert', 'document'] as $panel) {
            self::assertStringContainsString('data-admission-panel="' . $panel . '"', $body);
        }
        self::assertStringNotContainsString('data-admission-panel="checklist"', $body);
    }

    public function testUpdateCoachesActionIsVisibleForEveryAdmissionStatus(): void
    {
        $original = $this->db->table('admissions')
            ->where('id', $this->admission)
            ->get()
            ->getRowArray();
        self::assertIsArray($original);
        $admissionNumber = (string) ($original['admission_number'] ?? '');

        try {
            foreach (['INTAKE', 'PENDING', 'APPROVED', 'COMPLETED', 'CONVERTED'] as $status) {
                $this->db->table('admissions')
                    ->where('id', $this->admission)
                    ->update(['status' => $status]);

                $page = $this->withSession(session()->get())->get(
                    'admissions?admission_number=' . urlencode($admissionNumber),
                );
                self::assertSame(200, $page->response()->getStatusCode());
                self::assertStringContainsString(
                    'href="' . site_url('admissions/' . $this->admission . '/update-coaches') . '"',
                    $page->getBody(),
                    'Update Coaches should be visible for ' . $status,
                );
            }
        } finally {
            $this->db->table('admissions')
                ->where('id', $this->admission)
                ->update(['status' => $original['status']]);
        }
    }

    public function testNewAdmissionRouteUsesSharedPageAndOptionalLead(): void
    {
        $page = $this->withSession(session()->get())->get('admissions/new');
        self::assertSame(200, $page->response()->getStatusCode());
        self::assertStringContainsString('>Choose gender</option>', $page->getBody());
        self::assertStringContainsString('>Choose group</option>', $page->getBody());
        self::assertStringContainsString('>Choose branch</option>', $page->getBody());
        self::assertStringContainsString('>Choose batch</option>', $page->getBody());
        self::assertStringContainsString('>Choose coach</option>', $page->getBody());
        self::assertStringContainsString('>Choose lead</option>', $page->getBody());
        self::assertStringContainsString('>Choose trial</option>', $page->getBody());
        self::assertStringNotContainsString('Direct Admission / No Lead', $page->getBody());
        self::assertStringContainsString('action="' . site_url('admissions') . '"', $page->getBody());
        foreach (['gender', 'age_group_id', 'batch_id', 'start_date', 'end_date', 'blood_group', 'medical_conditions', 'jersey_size', 'playing_role', 'batting_style',
    'bowling_style', 'strong_hand', 'father_name', 'mother_name', 'parent_mobile', 'parent_email', 'emergency_contact'] as $field) {
            self::assertStringContainsString('name="' . $field . '"', $page->getBody());
        }
    }

    public function testRemovedAdmissionPlanMasterRoutesReturnNotFound(): void
    {
        $this->authorize(['admission.view'], [$this->branch]);
        foreach (['coaching', 'fee', 'hostel', 'academic'] as $type) {
            try {
                $this->withSession(session()->get())->get('admission-masters/' . $type);
                self::fail('Expected the removed Admission plan route to be not found.');
            } catch (\CodeIgniter\Exceptions\PageNotFoundException) {
                self::assertTrue(true);
            }
        }
    }
    /** @param list<string> $permissions @param list<int> $branches */
    private function authorize(array $permissions, array $branches, ?int $actor = null): void
    {
        session()->set(['isLoggedIn' => true, 'accountEnabled' => true, 'user' => ['id' => $this->actor], 'session_version' => 1,
    'permissions' => $permissions, 'authorizedBranches' => array_map('strval', $branches)]);
        if ($actor !== null) {
            session()->set('user', ['id' => $actor]);
        }
        \Config\Services::resetSingle('authorization');
    }
}
