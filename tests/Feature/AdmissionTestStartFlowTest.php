<?php

declare(strict_types=1);

namespace Tests\Feature;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

final class AdmissionTestStartFlowTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $DBGroup = 'tests';
    protected $migrate = false;

    private int $actor;
    private int $branch;
    private int $trial;
    private int $createdTest = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = (int) $this->db->table('branches')
            ->where('code', 'TEST_MCC_VNS')
            ->get()
            ->getRow('id');
        $this->actor = (int) $this->db->table('users')
            ->where('email', 'test.mcc.manager.vns@example.invalid')
            ->get()
            ->getRow('id');
        $trial = $this->db->query(
            'SELECT t.id FROM trials t '
            . 'WHERE t.branch_id = ? '
            . 'AND NOT EXISTS (SELECT 1 FROM admission_tests at WHERE at.trial_id = t.id) '
            . 'ORDER BY t.id LIMIT 1',
            [$this->branch],
        )->getRowArray();

        if ($this->branch < 1 || $this->actor < 1 || ! is_array($trial)) {
            self::markTestSkipped('A branch-scoped unused Trial fixture is unavailable.');
        }

        $this->trial = (int) $trial['id'];
        $this->authorize(['admission.create', 'admission.view', 'admission.update'], [$this->branch]);
    }

    protected function tearDown(): void
    {
        if ($this->createdTest > 0) {
            $this->db->table('admission_tests')->where('id', $this->createdTest)->delete();
        }

        session()->destroy();
        \Config\Services::resetSingle('authorization');
        parent::tearDown();
    }

    public function testTrialStartReturnsPersonalDetailsThenCompleteQuestionWizard(): void
    {
        $trialBefore = $this->db->table('trials')->where('id', $this->trial)->get()->getRowArray();
        $leadBefore = $this->db->table('leads')->where('id', (int) $trialBefore['lead_id'])->get()->getRowArray();
        $start = $this->withSession(session()->get())->post(
            'crm/trials/' . $this->trial . '/admission-test',
            [csrf_token() => csrf_hash()],
        );

        self::assertSame(302, $start->response()->getStatusCode());
        $location = $start->response()->getHeaderLine('Location');
        self::assertMatchesRegularExpression('#/admission-tests/(\d+)/paper-set$#', $location);
        preg_match('#/admission-tests/(\d+)/paper-set$#', $location, $matches);
        $this->createdTest = (int) $matches[1];

        $draft = $this->db->table('admission_tests')->where('id', $this->createdTest)->get()->getRowArray();
        self::assertSame($this->trial, (int) $draft['trial_id']);
        self::assertSame('DRAFT', $draft['status']);
        self::assertSame($trialBefore, $this->db->table('trials')->where('id', $this->trial)->get()->getRowArray());
        self::assertSame($leadBefore, $this->db->table('leads')->where('id', (int) $trialBefore['lead_id'])->get()->getRowArray());

        $paperSet = $this->withSession(session()->get())->post(
            'admission-tests/' . $this->createdTest . '/paper-set',
            [
                'version' => $draft['version'],
                'paper_set' => 'A',
                csrf_token() => csrf_hash(),
            ],
        );
        self::assertSame(302, $paperSet->response()->getStatusCode());
        self::assertStringContainsString('/admission-tests/' . $this->createdTest . '/wizard', $paperSet->response()->getHeaderLine('Location'));
        $draft = $this->db->table('admission_tests')->where('id', $this->createdTest)->get()->getRowArray();

        $personal = $this->withSession(session()->get())->get('admission-tests/' . $this->createdTest . '/wizard');
        self::assertSame(200, $personal->response()->getStatusCode());
        self::assertStringContainsString('Personal Details', $personal->getBody());
        self::assertStringContainsString('Trial #' . $this->trial, $personal->getBody());
        self::assertStringContainsString('name="father_profession"', $personal->getBody());
        self::assertStringContainsString('Prefilled from Lead', $personal->getBody());
        self::assertStringContainsString('value="' . esc((string) $leadBefore['full_name']) . '"', $personal->getBody());
        if (trim((string) ($leadBefore['email'] ?? '')) !== '') {
            self::assertStringContainsString('value="' . esc((string) $leadBefore['email']) . '"', $personal->getBody());
        }
        self::assertStringContainsString('data-wizard-step="2"', $personal->getBody());
        self::assertStringContainsString('data-wizard-step="3" aria-labelledby="wizard-page-A-heading" hidden', $personal->getBody());

        $invalidPersonal = $this->withSession(session()->get())->post(
            'admission-tests/' . $this->createdTest . '/wizard/personal-details',
            [
                'version' => $draft['version'],
                'applicant_full_name' => 'Submitted Value Wins',
                'age' => 'not-a-number',
                csrf_token() => csrf_hash(),
            ],
        );
        self::assertSame(422, $invalidPersonal->response()->getStatusCode());
        self::assertStringContainsString('value="Submitted Value Wins"', $invalidPersonal->getBody());

        $personalSave = $this->withSession(session()->get())->post(
            'admission-tests/' . $this->createdTest . '/wizard/personal-details',
            [
                'version' => $draft['version'],
                'applicant_full_name' => 'TEST_WIZARD_APPLICANT',
                csrf_token() => csrf_hash(),
            ],
        );

        self::assertSame(302, $personalSave->response()->getStatusCode());
        self::assertStringContainsString('/wizard?step=2', $personalSave->response()->getHeaderLine('Location'));

        $questions = $this->withSession(session()->get())->get(
            'admission-tests/' . $this->createdTest . '/wizard?step=2',
        );
        self::assertSame(200, $questions->response()->getStatusCode());
        self::assertStringContainsString('Generate Secure Student Link', $questions->getBody());
        self::assertStringContainsString('name="return_to" value="wizard"', $questions->getBody());
        self::assertStringContainsString('value="TEST_WIZARD_APPLICANT"', $questions->getBody());
        self::assertStringContainsString('Paper Set A', $questions->getBody());
        self::assertStringContainsString('name="answer_A_1"', $questions->getBody());
        self::assertStringNotContainsString('name="answer_B_10"', $questions->getBody());
        self::assertStringNotContainsString('name="answer_C_10"', $questions->getBody());

        $duplicate = $this->withSession(session()->get())->post(
            'crm/trials/' . $this->trial . '/admission-test',
            [csrf_token() => csrf_hash()],
        );
        self::assertSame(302, $duplicate->response()->getStatusCode());
        self::assertStringContainsString('/admission-tests/' . $this->createdTest . '/wizard', $duplicate->response()->getHeaderLine('Location'));
        self::assertSame(1, $this->db->table('admission_tests')->where('trial_id', $this->trial)->countAllResults());
    }

    public function testGeneratedStudentLinkUsesExistingWizardAndIsConsumedOnce(): void
    {
        $service = service('admissionTest', false);
        $this->createdTest = $service->createFromTrial($this->trial);
        $test = $this->db->table('admission_tests')->where('id', $this->createdTest)->get()->getRowArray();
        $service->selectPaperSet($this->createdTest, [
            'version' => $test['version'],
            'paper_set' => 'A',
        ]);

        $this->authorize([], [$this->branch]);
        $forbidden = $this->withSession(session()->get())->post(
            'admission-tests/' . $this->createdTest . '/student-link',
            [csrf_token() => csrf_hash()],
        );
        self::assertSame(403, $forbidden->response()->getStatusCode());

        $this->authorize(['admission.view', 'admission.update'], [$this->branch]);
        $url = $service->generateStudentLink($this->createdTest);
        $staffWizard = $this->withSession(session()->get())->get(
            'admission-tests/' . $this->createdTest . '/wizard',
        );
        self::assertSame(200, $staffWizard->response()->getStatusCode());
        preg_match('#/admission-tests/student/([a-f0-9]{64})$#', $url, $matches);
        self::assertCount(2, $matches);
        $token = $matches[1];

        $page = $this->get('admission-tests/student/' . $token);
        self::assertSame(200, $page->response()->getStatusCode());
        self::assertStringContainsString('class="mcc-public-test-logo"', $page->getBody());
        self::assertStringContainsString('mcc-public-admission-test-wizard', $page->getBody());
        self::assertStringNotContainsString('class="mcc-sidebar"', $page->getBody());
        self::assertStringNotContainsString('class="mcc-topbar"', $page->getBody());
        self::assertStringNotContainsString('class="mcc-breadcrumbs"', $page->getBody());
        self::assertStringContainsString('Personal Details', $page->getBody());
        self::assertStringContainsString('Selected Paper Set', $page->getBody());
        self::assertStringNotContainsString('id="wizard-paper-set-heading"', $page->getBody());
        self::assertStringNotContainsString('>Locked<', $page->getBody());
        self::assertStringNotContainsString('Select one paper set', $page->getBody());
        self::assertStringNotContainsString('>Select Paper Set<', $page->getBody());
        self::assertStringContainsString('Paper Set A', $page->getBody());
        self::assertStringNotContainsString('Paper Set B', $page->getBody());
        self::assertStringNotContainsString('Paper Set C', $page->getBody());
        self::assertStringContainsString('Generate Secure Student Link', $staffWizard->getBody());
        self::assertStringNotContainsString('Test #' . $this->createdTest, $page->getBody());

        $test = $this->db->table('admission_tests')->where('id', $this->createdTest)->get()->getRowArray();
        $personal = $this->post(
            'admission-tests/student/' . $token . '/personal-details',
            [
                'version' => $test['version'],
                'variant' => 'A',
                'applicant_full_name' => 'TEST_PUBLIC_WIZARD_APPLICANT',
                csrf_token() => csrf_hash(),
            ],
        );
        self::assertSame(302, $personal->response()->getStatusCode());
        self::assertStringContainsString('?step=2', $personal->response()->getHeaderLine('Location'));

        $selectedQuestions = $this->get('admission-tests/student/' . $token . '?step=2');
        self::assertSame(200, $selectedQuestions->response()->getStatusCode());
        self::assertStringContainsString('Paper Set A', $selectedQuestions->getBody());
        self::assertStringNotContainsString('Paper Set B', $selectedQuestions->getBody());
        self::assertStringNotContainsString('Paper Set C', $selectedQuestions->getBody());
        self::assertStringContainsString('name="answer_A_1"', $selectedQuestions->getBody());
        self::assertStringNotContainsString('name="answer_B_1"', $selectedQuestions->getBody());
        self::assertStringNotContainsString('name="answer_C_1"', $selectedQuestions->getBody());

        $test = $this->db->table('admission_tests')->where('id', $this->createdTest)->get()->getRowArray();
        $tampered = $this->post(
            'admission-tests/student/' . $token . '/submit',
            [
                'version' => $test['version'],
                'variant' => 'B',
                'applicant_full_name' => 'TEST_PUBLIC_WIZARD_APPLICANT',
                csrf_token() => csrf_hash(),
            ],
        );
        self::assertSame(422, $tampered->response()->getStatusCode());
        self::assertStringContainsString('The locked paper set cannot be changed.', $tampered->getBody());
        self::assertStringNotContainsString('name="answer_B_1"', $tampered->getBody());

        $test = $this->db->table('admission_tests')->where('id', $this->createdTest)->get()->getRowArray();
        $submission = [
            'version' => $test['version'],
            'variant' => 'A',
            'applicant_full_name' => 'TEST_PUBLIC_WIZARD_APPLICANT',
            csrf_token() => csrf_hash(),
        ];
        foreach (range(1, 10) as $question) {
            $submission['answer_A_' . $question] = '';
        }

        $submitted = $this->post('admission-tests/student/' . $token . '/submit', $submission);
        self::assertSame(200, $submitted->response()->getStatusCode());
        self::assertStringContainsString(
            'Thank you. Your Admission Test has been submitted successfully.',
            $submitted->getBody(),
        );
        self::assertStringContainsString('class="mcc-public-test-logo"', $submitted->getBody());
        self::assertStringNotContainsString('class="mcc-sidebar"', $submitted->getBody());
        self::assertSame('TEST_SUBMITTED', $this->db->table('admission_tests')->where('id', $this->createdTest)->get()->getRow('status'));

        $reused = $this->get('admission-tests/student/' . $token);
        self::assertSame(404, $reused->response()->getStatusCode());
        self::assertStringContainsString('Student test link unavailable or expired.', $reused->getBody());

        $malformed = $this->get('admission-tests/student/not-a-token');
        self::assertSame(404, $malformed->response()->getStatusCode());
        self::assertStringContainsString('Student test link unavailable or expired.', $malformed->getBody());
    }

    public function testPaperSetValidationAndRollback(): void
    {
        $service = service('admissionTest', false);
        self::assertSame(['A' => 'Paper Set A', 'B' => 'Paper Set B', 'C' => 'Paper Set C'], $service->paperSetOptions());

        $this->createdTest = $service->createFromTrial($this->trial);
        $draft = $this->db->table('admission_tests')->where('id', $this->createdTest)->get()->getRowArray();

        try {
            $service->selectPaperSet($this->createdTest, [
                'version' => $draft['version'],
                'paper_set' => 'D',
            ]);
            self::fail('An invalid paper set was accepted.');
        } catch (\RuntimeException $exception) {
            self::assertSame('Select a valid paper set.', $exception->getMessage());
        }

        $unchanged = $this->db->table('admission_tests')->where('id', $this->createdTest)->get()->getRowArray();
        self::assertNull($unchanged['test_variant']);
        self::assertSame($draft['version'], $unchanged['version']);

        $service->selectPaperSet($this->createdTest, [
            'version' => $unchanged['version'],
            'paper_set' => 'C',
        ]);

        self::assertSame('C', $this->db->table('admission_tests')->where('id', $this->createdTest)->get()->getRow('test_variant'));
    }

    public function testStudentLinkExpiresAtThirtyMinutesAndRegenerationRevokesPreviousLink(): void
    {
        $service = service('admissionTest', false);
        $this->createdTest = $service->createFromTrial($this->trial);
        $test = $this->db->table('admission_tests')->where('id', $this->createdTest)->get()->getRowArray();
        $service->selectPaperSet($this->createdTest, [
            'version' => $test['version'],
            'paper_set' => 'A',
        ]);

        $firstUrl = $service->generateStudentLink($this->createdTest);
        preg_match('#/admission-tests/student/([a-f0-9]{64})$#', $firstUrl, $firstMatch);
        $firstToken = $firstMatch[1];
        $firstLink = $this->db->table('admission_test_student_links')
            ->where('token_hash', hash('sha256', $firstToken))
            ->get()
            ->getRowArray();

        self::assertSame(
            (new \DateTimeImmutable($firstLink['created_at'], new \DateTimeZone('UTC')))
                ->add(new \DateInterval('PT30M'))
                ->format('Y-m-d H:i:s'),
            $firstLink['expires_at'],
        );

        $this->db->table('admission_test_student_links')->where('id', $firstLink['id'])->update([
            'expires_at' => gmdate('Y-m-d H:i:s'),
        ]);

        try {
            $service->studentDetails($firstToken);
            self::fail('An expired student link was accepted at the expiry boundary.');
        } catch (\RuntimeException $exception) {
            self::assertSame('Student test link unavailable or expired.', $exception->getMessage());
        }

        try {
            $service->saveStudentPersonalDetails($firstToken, [
                'version' => 1,
                'variant' => 'A',
                'applicant_full_name' => 'TAMPERED TIME',
                'expires_at' => gmdate('Y-m-d H:i:s', strtotime('+1 day')),
            ]);
            self::fail('A client-supplied expiry extended an expired link.');
        } catch (\RuntimeException $exception) {
            self::assertSame('Student test link unavailable or expired.', $exception->getMessage());
        }

        $secondUrl = $service->generateStudentLink($this->createdTest);
        preg_match('#/admission-tests/student/([a-f0-9]{64})$#', $secondUrl, $secondMatch);
        $secondToken = $secondMatch[1];
        $secondLink = $this->db->table('admission_test_student_links')
            ->where('token_hash', hash('sha256', $secondToken))
            ->get()
            ->getRowArray();

        self::assertNotNull($this->db->table('admission_test_student_links')->where('id', $firstLink['id'])->get()->getRow('revoked_at'));
        self::assertNotNull($service->studentDetails($secondToken));
        self::assertGreaterThan($secondLink['created_at'], $secondLink['expires_at']);
        self::assertGreaterThanOrEqual(
            1,
            $this->db->table('audit_logs')
            ->where('entity_type', 'admission_test_student_link')
            ->where('entity_id', (string) $firstLink['id'])
                ->where('action', 'admission_test_student_link_invalid')
            ->where("after_summary LIKE '%expired%'", null, false)
                ->countAllResults(),
        );
    }

    public function testInvalidUnauthorizedAndCrossBranchTrialsAreRejected(): void
    {
        $before = $this->db->table('admission_tests')->countAllResults();
        $invalid = $this->withSession(session()->get())->post(
            'crm/trials/999999999/admission-test',
            [csrf_token() => csrf_hash()],
        );

        self::assertSame(302, $invalid->response()->getStatusCode());
        self::assertStringContainsString('/crm/trials', $invalid->response()->getHeaderLine('Location'));
        self::assertSame($before, $this->db->table('admission_tests')->countAllResults());

        $this->authorize([], [$this->branch]);
        $unauthorized = $this->withSession(session()->get())->post(
            'crm/trials/' . $this->trial . '/admission-test',
            [csrf_token() => csrf_hash()],
        );
        self::assertSame(403, $unauthorized->response()->getStatusCode());

        $otherTrial = $this->db->table('trials')
            ->where('branch_id !=', $this->branch)
            ->whereNotIn('id', [$this->trial])
            ->orderBy('id')
            ->get()
            ->getRowArray();
        if (! is_array($otherTrial)) {
            self::markTestSkipped('A cross-branch Trial fixture is unavailable.');
        }

        $this->authorize(['admission.create'], [$this->branch]);
        $crossBranch = $this->withSession(session()->get())->post(
            'crm/trials/' . (int) $otherTrial['id'] . '/admission-test',
            [csrf_token() => csrf_hash()],
        );

        self::assertSame(302, $crossBranch->response()->getStatusCode());
        self::assertSame(
            0,
            $this->db->table('admission_tests')->where('trial_id', (int) $otherTrial['id'])->countAllResults(),
        );
    }

    public function testCompletedTestCannotBeStartedAgainAndRedirectsToItsDetails(): void
    {
        $this->createdTest = service('admissionTest')->createFromTrial($this->trial);
        $completedAt = gmdate('Y-m-d H:i:s');
        $this->db->table('admission_tests')->where('id', $this->createdTest)->update([
            'status' => 'TEST_SUBMITTED',
            'submitted_at' => $completedAt,
        ]);
        $before = $this->db->table('admission_tests')->where('id', $this->createdTest)->get()->getRowArray();

        $response = $this->withSession(session()->get())->post(
            'crm/trials/' . $this->trial . '/admission-test',
            [csrf_token() => csrf_hash()],
        );

        self::assertSame(302, $response->response()->getStatusCode());
        self::assertStringContainsString(
            '/admission-tests/' . $this->createdTest,
            $response->response()->getHeaderLine('Location'),
        );
        self::assertSame('Admission Test already completed.', session('error'));
        self::assertSame(
            $before,
            $this->db->table('admission_tests')->where('id', $this->createdTest)->get()->getRowArray(),
        );
        self::assertSame(1, $this->db->table('admission_tests')->where('trial_id', $this->trial)->countAllResults());

        $detail = $this->withSession(session()->get())->get('admission-tests/' . $this->createdTest);
        self::assertSame(200, $detail->response()->getStatusCode());
        self::assertStringContainsString('Test submitted', $detail->getBody());
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
}
