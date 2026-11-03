<?php

declare(strict_types=1);

namespace Tests\Feature;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Security\Exceptions\SecurityException;

final class AdmissionRoutesTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $DBGroup = 'tests';
    protected $migrate = false;

    private int $actor;
    private int $admission;
    private int $branch;
    private int $otherBranch;

    protected function setUp(): void
    {
        parent::setUp();

        self::assertSame(
            'mcc_cricket_club_test',
            strtolower((string) $this->db->getDatabase())
        );

        $this->actor = $this->userId('test.mcc.manager.vns@example.invalid');
        $this->branch = $this->branchId('TEST_MCC_VNS');
        $this->otherBranch = $this->branchId('TEST_MCC_DEL');
        $this->admission = (int) $this->db->table('admissions')
            ->where('branch_id', $this->branch)
            ->orderBy('id', 'ASC')
            ->get()
            ->getRow('id');

        self::assertGreaterThan(0, min(
            $this->actor,
            $this->admission,
            $this->branch,
            $this->otherBranch
        ));
    }

    protected function tearDown(): void
    {
        session()->destroy();
        \Config\Services::resetSingle('authorization');
        parent::tearDown();
    }

    public function testAuthorizedUserCanOpenAdmissionListDetailAndEditRoutes(): void
    {
        $this->authorize(['admission.view', 'admission.update'], [$this->branch]);

        $list = $this->withSession(session()->get())->get('admissions');
        $installments = $this->withSession(session()->get())->get('admissions/fee-installments');
        $detail = $this->withSession(session()->get())->get(
            'admissions/' . $this->admission . '/page'
        );
        $edit = $this->withSession(session()->get())->get(
            'admissions/' . $this->admission . '/edit'
        );
        $editPage = $this->withSession(session()->get())->get(
            'admissions/' . $this->admission . '/edit'
        );

        self::assertSame(200, $list->response()->getStatusCode());
        self::assertSame(200, $installments->response()->getStatusCode());
        self::assertSame(200, $detail->response()->getStatusCode());
        self::assertSame(200, $edit->response()->getStatusCode());
        self::assertSame(200, $editPage->response()->getStatusCode());
        self::assertStringContainsString('Admissions', $list->getBody());
        self::assertStringContainsString('mcc-admission-identity', $list->getBody());
        self::assertStringNotContainsString('<th scope="col">Applicant / lead</th>', $list->getBody());
        self::assertStringContainsString('Fee Installments', $installments->getBody());
        self::assertStringContainsString('mcc-status-payment-neutral', $installments->getBody());
        self::assertStringContainsString('Track coaching and hostel installment records', $installments->getBody());
        self::assertStringContainsString('Show Filters', $installments->getBody());
        self::assertStringContainsString('Hide Filters', $installments->getBody());
        self::assertStringContainsString('id="admission-installment-filters"', $installments->getBody());
        self::assertStringContainsString('id="admission-installment-filters" class="mcc-card mcc-admission-filters mcc-admission-installment-filters" aria-labelledby="admission-installment-filters-heading" hidden', $installments->getBody());
        self::assertStringContainsString('All installments', $installments->getBody());
        self::assertStringContainsString('mcc-installment-due-badge', $installments->getBody());
        self::assertStringContainsString('data-admission-actions-toggle', $installments->getBody());
        self::assertStringContainsString('name="installment_type"', $installments->getBody());
        self::assertStringContainsString('name="due_state"', $installments->getBody());
        self::assertStringContainsString('name="payment_status"', $installments->getBody());
        self::assertStringContainsString('Record Payment', $installments->getBody());
        self::assertStringNotContainsString('<th scope="col">Checklist</th>', $list->getBody());
        self::assertStringNotContainsString('data-admission-action="checklist"', $list->getBody());
        self::assertStringNotContainsString('Readiness', $detail->getBody());
        self::assertStringNotContainsString('Phase 1 checklist (AD-02)', $detail->getBody());
        self::assertStringContainsString('Fee Installment Status', $detail->getBody());
        self::assertStringContainsString('Training Selection and Coaching Options', $detail->getBody());
        self::assertStringContainsString('Estimated Personal Session', $detail->getBody());
        self::assertStringContainsString('Estimated Elite Session', $detail->getBody());
        self::assertStringContainsString('Update Coaches', $detail->getBody());
        self::assertStringNotContainsString('Planned schedule', $detail->getBody());
        self::assertSame(1, substr_count($detail->getBody(), 'Fee Installment Status'));
        self::assertStringContainsString('name="batch_id"', $edit->getBody());
        self::assertStringContainsString('name="trial_id"', $edit->getBody());
        self::assertStringContainsString('name="age"', $edit->getBody());
        self::assertStringContainsString('min="0"', $edit->getBody());
        self::assertStringContainsString('data-bt-select', $edit->getBody());
        self::assertStringContainsString('class="mcc-app-shell"', $edit->getBody());
        self::assertStringContainsString('class="mcc-breadcrumbs"', $edit->getBody());
        self::assertStringContainsString('name="start_date"', $edit->getBody());
        self::assertStringContainsString('name="end_date"', $edit->getBody());
        preg_match('/<select class="form-control" id="admission-status"[^>]*>/', $edit->getBody(), $editStatusSelect);
        self::assertNotEmpty($editStatusSelect);
        self::assertStringNotContainsString('data-bt-select', $editStatusSelect[0]);
        self::assertStringNotContainsString('class="mcc-modal"', $edit->getBody());
        self::assertStringNotContainsString('data-admission-modal', $edit->getBody());
        self::assertStringNotContainsString('Documents &amp; Photo', $editPage->getBody());
        self::assertStringContainsString('<dt>Age</dt>', $detail->getBody());
        self::assertStringContainsString('<dt>Hobbies</dt>', $detail->getBody());
        self::assertStringContainsString('<dt>Achievements</dt>', $detail->getBody());
        self::assertLessThan(
            strpos($edit->getBody(), 'id="age"'),
            strpos($edit->getBody(), 'id="applicant-date-of-birth"')
        );
        self::assertStringContainsString(
            'csrf_field()',
            (string) file_get_contents(ROOTPATH . 'app/Modules/Admissions/Views/edit.php')
        );
    }

    public function testAuthorizedUserCanOpenFocusedCoachCorrectionPage(): void
    {
        $this->authorize(['admission.view', 'admission.update'], [$this->branch]);

        $page = $this->withSession(session()->get())->get(
            'admissions/' . $this->admission . '/update-coaches'
        );

        self::assertSame(200, $page->response()->getStatusCode());
        self::assertStringContainsString('Update Coaches', $page->getBody());
        self::assertStringContainsString('name="batch_coach_id"', $page->getBody());
        self::assertStringContainsString('name="personal_coach_id"', $page->getBody());
        self::assertStringNotContainsString('name="coach_user_id"', $page->getBody());
        self::assertStringNotContainsString('name="personal_coach_user_id"', $page->getBody());
        self::assertStringContainsString('name="correction_reason"', $page->getBody());
        self::assertStringContainsString(
            'admissions/' . $this->admission . '/update-coaches',
            $page->getBody(),
        );
        self::assertStringNotContainsString('name="status"', $page->getBody());
        self::assertStringNotContainsString('name="admission_number"', $page->getBody());
    }

    public function testCompletedAdmissionDedicatedCoachFormSubmitsToCoachRoute(): void
    {
        $this->authorize(['admission.update'], [$this->branch]);
        $before = $this->db->table('admissions')->where('id', $this->admission)->get()->getRowArray();
        self::assertIsArray($before);
        $coachIds = $this->db->table('users u')
            ->select('u.id')
            ->join('user_branches ub', 'ub.user_id = u.id')
            ->join('user_roles ur', 'ur.user_id = u.id')
            ->join('roles r', 'r.id = ur.role_id')
            ->join('coach_profiles cp', 'cp.user_id = u.id')
            ->where([
                'ub.branch_id' => $this->branch,
                'u.is_enabled' => 1,
                'cp.is_active' => 1,
            ])
            ->whereIn('r.name', ['Coach', 'Fitness Coach'])
            ->groupBy('u.id')
            ->orderBy('u.id', 'ASC')
            ->get()
            ->getResultArray();
        self::assertGreaterThanOrEqual(2, count($coachIds));

        try {
            $this->db->table('admissions')
                ->where('id', $this->admission)
                ->update(['status' => 'COMPLETED']);

            $response = $this->withSession(session()->get())->post(
                'admissions/' . $this->admission . '/update-coaches',
                [
                    'batch_coach_id' => (int) $coachIds[0]['id'],
                    'personal_coach_id' => (int) $coachIds[1]['id'],
                    'correction_reason' => 'Feature dedicated coach update.',
                    'expected_version' => (int) ($before['renewal_version'] ?? 0),
                    csrf_token() => csrf_hash(),
                ],
            );

            self::assertSame(302, $response->response()->getStatusCode());
            self::assertStringEndsWith(
                '/admissions/' . $this->admission . '/page',
                (string) $response->response()->getHeaderLine('Location'),
            );
        } finally {
            $this->db->table('admissions')
                ->where('id', $this->admission)
                ->update([
                    'status' => $before['status'],
                    'coach_user_id' => $before['coach_user_id'],
                    'personal_coach_user_id' => $before['personal_coach_user_id'],
                ]);
        }
    }

    public function testCompletedAdmissionEditExposesOnlyCoachCorrectionControls(): void
    {
        $this->authorize(['admission.view', 'admission.update'], [$this->branch]);
        $before = $this->db->table('admissions')->where('id', $this->admission)->get()->getRowArray();
        self::assertIsArray($before);

        try {
            $this->db->table('admissions')
                ->where('id', $this->admission)
                ->update(['status' => 'APPROVED']);

            $edit = $this->withSession(session()->get())->get(
                'admissions/' . $this->admission . '/edit'
            );

            self::assertSame(200, $edit->response()->getStatusCode());
            self::assertStringContainsString('name="coach_user_id"', $edit->getBody());
            self::assertStringContainsString('name="personal_coach_user_id"', $edit->getBody());
            self::assertStringContainsString('name="correction_reason"', $edit->getBody());
            self::assertStringContainsString('Save Coach Assignments', $edit->getBody());
            self::assertStringContainsString('name="expected_version"', $edit->getBody());
            self::assertStringContainsString('disabled', $edit->getBody());
            self::assertCoachSelectorIsEnabled($edit->getBody(), 'coach_user_id');
            self::assertCoachSelectorIsEnabled($edit->getBody(), 'personal_coach_user_id');
        } finally {
            $this->db->table('admissions')
                ->where('id', $this->admission)
                ->update([
                    'status' => $before['status'],
                    'coach_user_id' => $before['coach_user_id'],
                    'personal_coach_user_id' => $before['personal_coach_user_id'],
                ]);
        }
    }

    public function testConvertedAdmissionEditKeepsCoachCorrectionControlsEnabled(): void
    {
        $this->authorize(['admission.view', 'admission.update'], [$this->branch]);
        $before = $this->db->table('admissions')->where('id', $this->admission)->get()->getRowArray();
        self::assertIsArray($before);

        try {
            $this->db->table('admissions')
                ->where('id', $this->admission)
                ->update(['status' => 'CONVERTED']);

            $edit = $this->withSession(session()->get())->get(
                'admissions/' . $this->admission . '/edit'
            );

            self::assertSame(200, $edit->response()->getStatusCode());
            self::assertStringContainsString('name="correction_reason"', $edit->getBody());
            self::assertStringContainsString('Save Coach Assignments', $edit->getBody());
            self::assertCoachSelectorIsEnabled($edit->getBody(), 'coach_user_id');
            self::assertCoachSelectorIsEnabled($edit->getBody(), 'personal_coach_user_id');
        } finally {
            $this->db->table('admissions')
                ->where('id', $this->admission)
                ->update([
                    'status' => $before['status'],
                    'coach_user_id' => $before['coach_user_id'],
                    'personal_coach_user_id' => $before['personal_coach_user_id'],
                ]);
        }
    }

    private static function assertCoachSelectorIsEnabled(string $html, string $name): void
    {
        preg_match(
            '/<select[^>]+name="' . preg_quote($name, '/') . '"[^>]*>/i',
            $html,
            $matches,
        );

        self::assertNotEmpty($matches, 'Expected coach selector was not rendered.');
        self::assertStringNotContainsString('disabled', strtolower($matches[0]));
    }

    public function testAuthorizedUserCanReadAdmissionTermsOnNewForm(): void
    {
        $this->authorize(['admission.create', 'admission.view'], [$this->branch]);

        $page = $this->withSession(session()->get())->get('admissions/new');

        self::assertSame(200, $page->response()->getStatusCode());
        self::assertStringContainsString('Read Terms and Conditions', $page->getBody());
        self::assertStringContainsString('data-admission-terms-modal', $page->getBody());
        self::assertStringContainsString('Rules And Regulations:', $page->getBody());
        self::assertStringContainsString('Request for refund shall not be entertained under any circumstances.', $page->getBody());
        self::assertStringContainsString('Due dates for the payment of coaching fees mentioned to you during the admission remain the same', $page->getBody());
        self::assertStringContainsString('Enclosure: 1) Copy of Birth Certificate 2) Copy of Aadhar Card 3) 2 Photographs affix', $page->getBody());
    }

    public function testAdmissionDownloadIsAuthorizedUsesCanonicalNumberAndDoesNotExposeMissingRecords(): void
    {
        $this->authorize(['admission.view'], [$this->branch]);
        $number = (string) $this->db->table('admissions')
            ->where('id', $this->admission)
            ->get()
            ->getRow('admission_number');

        $download = $this->withSession(session()->get())->get(
            'admissions/' . $this->admission . '/download'
        );

        self::assertSame(200, $download->response()->getStatusCode());
        self::assertSame('application/pdf', $download->response()->getHeaderLine('Content-Type'));
        self::assertStringContainsString(
            'mcc-' . strtolower((string) $number) . '.pdf',
            strtolower($download->response()->getHeaderLine('Content-Disposition'))
        );
        self::assertStringStartsWith('%PDF-', $download->response()->getBody());

        $this->authorize(['lead.view'], [$this->branch]);
        self::assertSame(
            403,
            $this->withSession(session()->get())->get(
                'admissions/' . $this->admission . '/download'
            )->response()->getStatusCode()
        );

        $this->authorize(['admission.view'], [$this->otherBranch]);
        self::assertSame(
            403,
            $this->withSession(session()->get())->get(
                'admissions/' . $this->admission . '/download'
            )->response()->getStatusCode()
        );
        self::assertSame(
            404,
            $this->withSession(session()->get())->get('admissions/999999999/download')
                ->response()->getStatusCode()
        );
    }

    public function testAdmissionPaginationUsesSlidingWindowAndPreservesFilters(): void
    {
        $this->authorize(['admission.view'], [$this->branch]);

        $filters = ['q' => 'TEST_MCC'];
        $result = service('admission')->paginatedForAuthorizedBranches($filters, 1, 20);

        self::assertSame(
            count(service('admission')->listForAuthorizedBranches($filters)),
            $result['total']
        );

        $first = $this->withSession(session()->get())->get('admissions?q=TEST_MCC&page=1');
        self::assertSame(200, $first->response()->getStatusCode());
        self::assertStringContainsString('aria-label="Admission pages"', $first->getBody());
        self::assertStringContainsString('q=TEST_MCC&amp;page=1', $first->getBody());
        self::assertStringContainsString('Previous', $first->getBody());

        if ($result['pages'] > 1) {
            $last = $this->withSession(session()->get())->get(
                'admissions?q=TEST_MCC&page=' . $result['pages']
            );

            self::assertStringContainsString(
                'Page ' . $result['pages'] . ' of ' . $result['pages'],
                $last->getBody()
            );
            self::assertStringContainsString('q=TEST_MCC&amp;page=' . $result['pages'], $last->getBody());
            self::assertStringContainsString('aria-current="page"', $last->getBody());

            $outOfRange = $this->withSession(session()->get())->get('admissions?q=TEST_MCC&page=999');
            self::assertStringContainsString(
                'Page ' . $result['pages'] . ' of ' . $result['pages'],
                $outOfRange->getBody()
            );
        }

        if ($result['pages'] >= 5) {
            $pageFive = $this->withSession(session()->get())->get('admissions?q=TEST_MCC&page=5');
            self::assertStringContainsString('aria-current="page"', $pageFive->getBody());
            self::assertStringContainsString('q=TEST_MCC&amp;page=5', $pageFive->getBody());
        }
    }

    public function testAuthorizedUserCanOpenRenewalFormAndSeeReadOnlyHistory(): void
    {
        $this->authorize(['admission.view', 'admission.update'], [$this->branch]);

        $renew = $this->withSession(session()->get())->get(
            'admissions/' . $this->admission . '/renew'
        );

        self::assertSame(200, $renew->response()->getStatusCode());
        self::assertStringContainsString('Renew Admission', $renew->getBody());
        self::assertStringContainsString('Admission History', $renew->getBody());
        self::assertStringContainsString('name="expected_version"', $renew->getBody());
        self::assertStringContainsString('data-mcc-date-picker', $renew->getBody());
        self::assertStringContainsString('Total Coaching + Hostel Fee', $renew->getBody());
        self::assertStringContainsString('Coaching Instalments', $renew->getBody());
        self::assertStringContainsString('Hostel Instalments', $renew->getBody());
    }

    public function testAdmissionRoutesDenyMissingPermissionAndCrossBranchDetailAccess(): void
    {
        $this->authorize(['lead.view'], [$this->branch]);

        self::assertSame(
            403,
            $this->withSession(session()->get())->get('admissions/fee-installments')->response()->getStatusCode(),
        );
        self::assertSame(
            403,
            $this->withSession(session()->get())->get(
                'admissions/' . $this->admission . '/update-coaches'
            )->response()->getStatusCode(),
        );
        $forbidden = $this->withSession(session()->get())->get('admissions');
        self::assertSame(403, $forbidden->response()->getStatusCode());

        $this->authorize(['admission.view'], [$this->otherBranch]);
        $crossBranchDetail = $this->withSession(session()->get())->get(
            'admissions/' . $this->admission . '/page'
        );

        self::assertSame(403, $crossBranchDetail->response()->getStatusCode());
        self::assertSame(
            403,
            $this->withSession(session()->get())->get(
                'admissions/' . $this->admission . '/update-coaches'
            )->response()->getStatusCode(),
        );
    }

    public function testAuthorizedUserCanOpenAnInstallmentPaymentForm(): void
    {
        if (! $this->db->tableExists('admission_installment_payments')) {
            self::markTestSkipped('The admission installment payment migration has not been applied.');
        }

        $installment = $this->db->table('admission_coaching_installments i')
            ->select('i.id')
            ->join('admissions a', 'a.id = i.admission_id')
            ->where('a.branch_id', $this->branch)
            ->orderBy('i.id', 'ASC')
            ->get()
            ->getRowArray();
        if (! is_array($installment)) {
            self::markTestSkipped('No coaching installment is available for the payment form contract.');
        }

        $this->authorize(['admission.update'], [$this->branch]);
        $response = $this->withSession(session()->get())->get(
            'admissions/fee-installments/coaching/' . (int) $installment['id'] . '/payment'
        );

        self::assertSame(200, $response->response()->getStatusCode());
        self::assertStringContainsString('Record Payment', $response->getBody());
        self::assertStringContainsString('name="amount_received"', $response->getBody());
        self::assertStringContainsString('name="receipt_number"', $response->getBody());
        self::assertStringContainsString('Payment history', $response->getBody());
    }

    public function testAuthorizedUserCanOpenAdmissionCreateFormWithAgeField(): void
    {
        $this->authorize(['admission.create', 'admission.view'], [$this->branch]);

        $create = $this->withSession(session()->get())->get('admissions/new');

        self::assertSame(200, $create->response()->getStatusCode());
        self::assertStringContainsString('name="age"', $create->getBody());
        self::assertStringContainsString('name="age_group_id"', $create->getBody());
        self::assertStringContainsString('name="status"', $create->getBody());
        self::assertStringContainsString('Choose status', $create->getBody());
        self::assertStringContainsString('Pending Admission', $create->getBody());
        preg_match('/<select class="form-control" id="admission-status"[^>]*>/', $create->getBody(), $createStatusSelect);
        self::assertNotEmpty($createStatusSelect);
        self::assertStringNotContainsString('data-bt-select', $createStatusSelect[0]);
        self::assertStringContainsString('col-12 col-md-6', $create->getBody());
        self::assertStringNotContainsString(
            'Use touch or mouse to sign. A saved signature is retained after validation errors.',
            $create->getBody()
        );
        self::assertStringContainsString('id="admission-batch-coach"', $create->getBody());
        self::assertStringContainsString('id="admission-personal-coach"', $create->getBody());
        self::assertStringContainsString('min="0" max="120"', $create->getBody());
        self::assertStringContainsString('name="applicant_date_of_birth"', $create->getBody());
        self::assertStringNotContainsString('Uploaded documents and photo', $create->getBody());
        self::assertStringNotContainsString('name="profile_photo"', $create->getBody());
        self::assertStringNotContainsString('name="document"', $create->getBody());
        self::assertDoesNotMatchRegularExpression('/name="start_date"[\s\S]{0,300}\brequired\b/', $create->getBody());
        self::assertDoesNotMatchRegularExpression('/name="end_date"[\s\S]{0,300}\brequired\b/', $create->getBody());
        self::assertDoesNotMatchRegularExpression('/name="lead_id"[\s\S]{0,300}\brequired\b/', $create->getBody());
        self::assertLessThan(
            strpos($create->getBody(), 'id="age"'),
            strpos($create->getBody(), 'id="applicant-date-of-birth"')
        );
    }

    public function testTrainingSelectionSectionIsNotRenderedOnCreateOrEdit(): void
    {
        $this->authorize(['admission.create', 'admission.update', 'admission.view'], [$this->branch]);

        $create = $this->withSession(session()->get())->get('admissions/new');
        $edit = $this->withSession(session()->get())->get(
            'admissions/' . $this->admission . '/edit'
        );

        foreach ([$create->getBody(), $edit->getBody()] as $body) {
            self::assertStringNotContainsString('<h3>Training Selection</h3>', $body);
            self::assertStringNotContainsString('data-admission-subsection="coaching-training"', $body);
            foreach (['year1_start_date', 'year1_age_group', 'year1_plan'] as $field) {
                self::assertStringNotContainsString('name="' . $field . '"', $body);
            }
            self::assertStringContainsString('Coaching Plan', $body);
            self::assertStringContainsString('Declarations and Signatures', $body);
            self::assertStringContainsString('mcc-admission-actions', $body);
        }
    }

    public function testCoachingTypeAndConditionalFieldsArePresentOnCreateAndEdit(): void
    {
        $this->authorize(['admission.create', 'admission.update', 'admission.view'], [$this->branch]);

        $create = $this->withSession(session()->get())->get('admissions/new');
        $edit = $this->withSession(session()->get())->get(
            'admissions/' . $this->admission . '/edit'
        );

        foreach ([$create->getBody(), $edit->getBody()] as $body) {
            self::assertStringContainsString('id="admission-coaching-type"', $body);
            self::assertStringContainsString('name="coaching_type[]"', $body);
            self::assertStringContainsString('value="Personal coaching"', $body);
            self::assertStringContainsString('value="Group coaching"', $body);
            self::assertStringContainsString('value="Elite"', $body);
            self::assertStringContainsString('id="admission-elite-camp-days"', $body);
            self::assertStringContainsString('id="admission-elite-camp-duration"', $body);
            self::assertStringContainsString('id="admission-personal-coaching-days"', $body);
            self::assertStringContainsString('id="admission-personal-coaching-duration"', $body);
            self::assertStringContainsString('id="admission-estimated-personal-session"', $body);
            self::assertStringContainsString('id="admission-group-coaching-days"', $body);
            self::assertStringContainsString('id="admission-estimated-elite-session"', $body);
            self::assertSame(8, substr_count($body, 'data-admission-coaching-dependent'));
            $coachingTypeStart = strpos($body, 'id="admission-coaching-type"');
            $coachingTypeEnd = strpos($body, '</fieldset>', $coachingTypeStart);
            self::assertNotFalse($coachingTypeStart);
            self::assertNotFalse($coachingTypeEnd);
            foreach ([
                'admission-personal-coaching-days',
                'admission-personal-coaching-duration',
                'admission-estimated-personal-session',
                'admission-group-coaching-days',
                'admission-elite-camp-days',
                'admission-elite-camp-duration',
                'admission-estimated-elite-session',
            ] as $conditionalId) {
                $conditionalPosition = strpos($body, 'id="' . $conditionalId . '"');
                self::assertNotFalse($conditionalPosition);
                self::assertLessThan($coachingTypeEnd, $conditionalPosition);
            }
            foreach ([
                'coaching_delivery' => 3,
                'induction_plan' => 2,
                'review_frequency' => 2,
                'progress_report_frequency' => 2,
                'feedback_call_frequency' => 2,
                'student_diary' => 2,
            ] as $field => $optionCount) {
                self::assertSame(
                    $optionCount,
                    substr_count($body, 'name="' . $field . '" type="radio"'),
                );
            }
            foreach ([
                '>Choose group</option>',
                '>Choose branch</option>',
                '>Choose batch</option>',
                '>Choose coach</option>',
                '>Choose lead</option>',
                '>Choose trial</option>',
                '>Choose order</option>',
                '>Choose plan</option>',
            ] as $placeholder) {
                self::assertStringContainsString($placeholder, $body);
            }
        }

    }

    public function testAgeGroupIsRenderedAfterAgeOnCreateAndEdit(): void
    {
        $this->authorize(['admission.create', 'admission.update', 'admission.view'], [$this->branch]);

        $create = $this->withSession(session()->get())->get('admissions/new');
        $edit = $this->withSession(session()->get())->get(
            'admissions/' . $this->admission . '/edit'
        );

        foreach ([$create->getBody(), $edit->getBody()] as $body) {
            self::assertLessThan(
                strpos($body, 'id="age-group"'),
                strpos($body, 'id="age"'),
                'positions age=' . strpos($body, 'id="age"') . ' age-group=' . strpos($body, 'id="age-group"')
            );
            self::assertStringContainsString('name="age_group_id"', $body);
        }
    }

    public function testAdmissionCreateAndEditExposeApprovedSectionStructures(): void
    {
        $this->authorize(['admission.create', 'admission.update', 'admission.view'], [$this->branch]);

        $create = $this->withSession(session()->get())->get('admissions/new');
        $edit = $this->withSession(session()->get())->get(
            'admissions/' . $this->admission . '/edit'
        );

        $createBody = $create->getBody();
        $editBody = $edit->getBody();

        self::assertSame(8, substr_count($createBody, 'data-admission-section="'));
        self::assertSame(8, substr_count($editBody, 'data-admission-section="'));
        self::assertStringContainsString('Cricket Information', $createBody);
        self::assertStringContainsString('Cricket Information', $editBody);
        self::assertStringContainsString('Lead Information and Ratings', $editBody);
        self::assertStringContainsString('Declarations and Signatures', $editBody);
        self::assertStringNotContainsString('id="admission-number"', $editBody);
        self::assertStringContainsString('Save Changes', $editBody);

        foreach ([$createBody, $editBody] as $body) {
            self::assertStringNotContainsString('admission_number', $body);
            foreach ([
                'Coaching Plan',
                'Personal Details',
                'Fees',
                'Coaching Instalments',
                'Hostel Instalments',
            ] as $heading) {
                self::assertStringContainsString($heading, $body);
            }
            self::assertStringContainsString('name="training_fee"', $body);
            self::assertStringContainsString('class="row mcc-admission-fields mcc-admission-branch-fields"', $body);
            self::assertSame(1, substr_count($body, 'mcc-admission-branch-fields'));

            $sectionOrder = [
                'personal',
                'coaching',
                'cricket',
                'lead-ratings',
                'fees',
                'coaching-installments',
                'hostel-installments',
                'declarations',
            ];
            $sectionPositions = array_map(
                static fn (string $section): int|false => strpos(
                    $body,
                    'data-admission-section="' . $section . '"',
                ),
                $sectionOrder,
            );
            foreach ($sectionPositions as $position) {
                self::assertNotFalse($position);
            }
            for ($index = 1, $count = count($sectionPositions); $index < $count; $index++) {
                self::assertTrue($sectionPositions[$index - 1] < $sectionPositions[$index]);
            }

            $coachingSectionPosition = strpos($body, 'data-admission-section="coaching"');
            $branchSubsectionPosition = strpos($body, 'data-admission-subsection="coaching-branch"');
            $trainingOptionsPosition = strpos($body, 'data-admission-subsection="coaching-training-options"');
            self::assertNotFalse($coachingSectionPosition);
            self::assertNotFalse($branchSubsectionPosition);
            self::assertNotFalse($trainingOptionsPosition);
            self::assertLessThan($branchSubsectionPosition, $coachingSectionPosition);
            self::assertLessThan($trainingOptionsPosition, $branchSubsectionPosition);

            self::assertStringContainsString('Coaching Fee', $body);
            self::assertStringContainsString('name="hostel_charges"', $body);
            self::assertStringContainsString('Hostel Fee', $body);
            self::assertStringContainsString('Total Coaching + Hostel Fee', $body);
            self::assertStringContainsString('data-admission-fee-total', $body);
            self::assertStringContainsString('name="total_taxable_fee"', $body);
            self::assertStringContainsString('name="tax_rate"', $body);
            self::assertStringContainsString('data-admission-calculated-tax', $body);
            self::assertStringNotContainsString('data-admission-total-including-tax', $body);
            self::assertStringContainsString('Taxable Amount', $body);
            self::assertStringContainsString('Calculated Tax Amount', $body);
            self::assertSame(3, substr_count($body, 'mcc-admission-fee-tax-column'));
            self::assertStringContainsString('placeholder="Enter tax rate"', $body);
            self::assertStringContainsString('class="col-12 col-md-6 mcc-admission-fee-column"', $body);
            self::assertStringNotContainsString('Fee Summary', $body);
            self::assertStringNotContainsString('Hostel Requirement', $body);
            self::assertStringNotContainsString('name="hostel_required"', $body);
            self::assertStringNotContainsString('name="admission_fee"', $body);
            self::assertStringContainsString('name="installment_plan"', $body);
            self::assertStringContainsString('value="Full Payment"', $body);
            self::assertStringContainsString('value="Instalment Plan"', $body);
            self::assertStringContainsString('data-admission-installment-schedule', $body);
            self::assertSame(4, substr_count($body, 'data-admission-installment-section='));
            self::assertStringNotContainsString('Coaching Instalment Schedule</h3>', $body);
            self::assertStringNotContainsString('Hostel Instalment Schedule</h3>', $body);
            self::assertStringNotContainsString('data-admission-subsection="coaching-fee-schedule"', $body);
            self::assertStringNotContainsString('data-admission-subsection="hostel-schedule"', $body);
            self::assertStringContainsString('Not applicable &mdash; Coaching fee is not on an instalment plan.', $body);
            self::assertStringContainsString('Not applicable &mdash; Hostel fee is not on an instalment plan.', $body);
            self::assertSame(2, substr_count($body, 'data-admission-installment-empty'));
            self::assertStringContainsString('name="installment_year[]"', $body);
            self::assertStringContainsString('name="installment_1_date[]"', $body);
            self::assertStringContainsString('name="installment_4_date[]"', $body);
            self::assertStringContainsString('name="installment_1[]"', $body);
            self::assertStringContainsString('name="installment_4[]"', $body);
            self::assertStringContainsString('data-mcc-date-picker', $body);
            self::assertStringContainsString('name="hostel_installment_plan"', $body);
            self::assertStringNotContainsString('name="hostel_payment_plan"', $body);
            self::assertStringContainsString('id="admission-installment-plan"', $body);
            self::assertStringContainsString('id="admission-hostel-installment-plan"', $body);
            self::assertTrue(strpos($body, 'name="installment_plan"') < strpos($body, 'name="training_fee"'));
            self::assertTrue(strpos($body, 'name="hostel_installment_plan"') < strpos($body, 'name="hostel_charges"'));
            self::assertStringContainsString('name="hostel_installment_year[]"', $body);
            self::assertStringContainsString('name="hostel_installment_1_date[]"', $body);
            self::assertStringContainsString('name="hostel_installment_4[]"', $body);
            self::assertStringContainsString('data-admission-installment-prefix="hostel_installment_"', $body);
            self::assertStringContainsString('Add More Year', $body);
            self::assertStringContainsString('data-admission-installment-add', $body);
            self::assertStringContainsString('data-admission-installment-remove', $body);
            foreach ([
                'installment_1_date[]',
                'installment_1[]',
                'installment_4_date[]',
                'installment_4[]',
                'hostel_installment_1_date[]',
                'hostel_installment_1[]',
                'hostel_installment_4_date[]',
                'hostel_installment_4[]',
            ] as $field) {
                self::assertDoesNotMatchRegularExpression(
                    '/name="' . preg_quote($field, '/') . '"[^>]*\\brequired\\b/',
                    $body,
                );
            }
            $coachingPosition = strpos($body, 'data-admission-section="coaching"');
            $feesPosition = strpos($body, 'data-admission-section="fees"');
            $coachingInstallmentsPosition = strpos($body, 'data-admission-section="coaching-installments"');
            $hostelInstallmentsPosition = strpos($body, 'data-admission-section="hostel-installments"');
            self::assertNotFalse($coachingPosition);
            self::assertNotFalse($feesPosition);
            self::assertNotFalse($coachingInstallmentsPosition);
            self::assertNotFalse($hostelInstallmentsPosition);
            self::assertTrue($coachingPosition < $feesPosition);
            self::assertTrue($feesPosition < $coachingInstallmentsPosition);
            self::assertTrue($coachingInstallmentsPosition < $hostelInstallmentsPosition);
            self::assertStringContainsString('data-admission-section-slot="personal"', $body);
            self::assertStringContainsString('data-mcc-date-picker', $body);
            self::assertStringContainsString('id="admission-batch-coach"', $body);
            self::assertStringContainsString('id="admission-personal-coach"', $body);
            self::assertStringContainsString('data-mcc-signature-canvas', $body);
            self::assertStringContainsString('data-mcc-signature-clear', $body);
            self::assertStringContainsString('>Ratings</h3>', $body);
            self::assertStringContainsString('data-admission-trial-rating="ability_overall"', $body);
            self::assertStringContainsString('data-admission-trial-rating="ability_batting"', $body);
            self::assertStringContainsString('data-admission-trial-rating="ability_bowling"', $body);
            self::assertStringContainsString('data-admission-trial-rating="ability_wicketkeeping"', $body);
            self::assertStringContainsString('data-admission-trial-rating="ability_fitness"', $body);
            self::assertStringNotContainsString('Additional Cricket Information', $body);
            self::assertStringNotContainsString('Cricket Background', $body);
            self::assertStringNotContainsString('Cricket Profile', $body);
            self::assertStringNotContainsString('Cricket profile', $body);
            self::assertStringNotContainsString('Cricket Selections', $body);
            self::assertStringNotContainsString('Cricket selections', $body);
            self::assertStringNotContainsString('Academic and Application Details', $body);
            self::assertStringNotContainsString('data-admission-subsection="personal-application-records"', $body);
            self::assertStringContainsString('>Cricket Information</h3>', $body);
            self::assertStringContainsString('Lead Information and Ratings', $body);
            self::assertStringNotContainsString('Admission information</legend>', $body);
            self::assertStringContainsString('name="lead_id"', $body);
            self::assertStringContainsString('name="trial_id"', $body);
            self::assertStringNotContainsString('name="bowling_options"', $body);
            self::assertSame(3, substr_count($body, 'data-mcc-signature-canvas'));
            self::assertSame(3, substr_count($body, 'data-mcc-signature-clear'));
            self::assertSame(3, substr_count($body, 'mcc-button-danger'));
            self::assertStringContainsString('Clear Signature', $body);
            self::assertStringNotContainsString('Redraw', $body);
            self::assertStringContainsString('name="student_signature"', $body);
            self::assertStringContainsString('name="parent_signature"', $body);
            self::assertStringContainsString('name="authorized_signature"', $body);
            self::assertStringContainsString('mcc-admission-bottom-block', $body);
            self::assertTrue(
                strpos($body, 'mcc-admission-bottom-block') < strpos($body, 'mcc-form-actions')
            );

            foreach ([
                'applicant_full_name', 'applicant_date_of_birth', 'age', 'gender',
                'applicant_email', 'applicant_phone', 'father_name', 'mother_name',
                'parent_email', 'parent_mobile', 'current_address', 'permanent_address',
                'school_college', 'academic_year', 'playing_experience',
                'previous_coaching_centre', 'hobbies', 'achievements', 'ability_overall',
                'ability_batting', 'ability_bowling', 'ability_wicketkeeping',
                'ability_fitness', 'branch_id', 'trial_id', 'coach_user_id', 'personal_coach_user_id', 'batch_id',
                'training_fee', 'installment_plan', 'installment_due_dates',
                'hostel_charges', 'hostel_payment_plan',
            ] as $field) {
                self::assertLessThanOrEqual(1, substr_count($body, 'name="' . $field . '"'), $field . ' must not render more than once.');
            }

            self::assertSame(1, substr_count($body, 'name="current_address"'));
            self::assertSame(1, substr_count($body, 'name="permanent_address"'));
            self::assertStringNotContainsString('>Correspondence address<', $body);
            self::assertSame(1, substr_count($body, 'name="father_name"'));
            self::assertTrue(
                strpos($body, 'id="father-name"') < strpos($body, 'id="applicant-date-of-birth"')
            );
        }
        self::assertStringNotContainsString('Document Record', $create->getBody());
        self::assertStringNotContainsString('name="documents_submitted"', $create->getBody());
        self::assertStringNotContainsString('Document Record', $edit->getBody());
        self::assertStringNotContainsString('name="documents_submitted"', $edit->getBody());
    }

    public function testStateChangingAdmissionRoutesUsePostAndCentralPermissions(): void
    {
        $routes = $this->routeSource();

        self::assertStringContainsString("\$routes->post('/',", $routes);
        self::assertStringContainsString("\$routes->post('(:num)/edit',", $routes);
        self::assertStringContainsString("\$routes->post('(:num)/update-coaches',", $routes);
        self::assertStringContainsString("\$routes->get('(:num)/update-coaches',", $routes);
        self::assertStringContainsString("\$routes->post('(:num)/checklist',", $routes);
        self::assertStringContainsString("\$routes->post('(:num)/status',", $routes);
        self::assertStringContainsString('permission:admission.create', $routes);
        self::assertStringContainsString('permission:admission.update', $routes);
        self::assertStringContainsString('permission:player.convert', $routes);
    }

    public function testAdmissionEditRejectsTheStaleOrMissingCsrfPayloadBeforeSave(): void
    {
        $this->authorize(['admission.update'], [$this->branch]);

        $this->expectException(SecurityException::class);

        $this->withSession(session()->get())->post(
            'admissions/' . $this->admission . '/edit',
            [
                'status' => 'INTAKE',
                'start_date' => '01-01-2027',
                'end_date' => '31-12-2027',
                'coach_user_id' => '',
                'personal_coach_user_id' => '',
                'ability_overall' => '',
                'ability_batting' => '',
                'ability_bowling' => '',
                'ability_wicketkeeping' => '',
                'ability_fitness' => '',
            ],
        );
    }

    public function testFocusedCoachUpdateRejectsMissingCsrfPayloadBeforeSave(): void
    {
        $this->authorize(['admission.update'], [$this->branch]);

        $this->expectException(SecurityException::class);

        $this->withSession(session()->get())->post(
            'admissions/' . $this->admission . '/update-coaches',
            [
                'coach_user_id' => '',
                'personal_coach_user_id' => '',
                'correction_reason' => 'Missing CSRF must be rejected.',
            ],
        );
    }

    private function routeSource(): string
    {
        $routes = file_get_contents(
            ROOTPATH . 'app/Modules/Admissions/Config/Routes.php'
        );

        self::assertIsString($routes);

        return $routes;
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
