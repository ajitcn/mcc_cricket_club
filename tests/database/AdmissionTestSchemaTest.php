<?php

declare(strict_types=1);

namespace Tests\Database;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

final class AdmissionTestSchemaTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $DBGroup = 'tests';
    protected $migrate = false;
    private int $actor;
    private int $studentLinkTest = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $branch = $this->db->table('branches')->where('code', 'TEST_MCC_VNS')->get()->getRowArray();
        $trial = $this->db->table('trials')->where('branch_id', (int) ($branch['id'] ?? 0))->orderBy('id')->get()->getRowArray();
        if (! is_array($branch) || ! is_array($trial)) {
            self::markTestSkipped('Admission Test fixture Trial is unavailable.');
        }
        $actor = $this->db->table('users')->where('email', 'test.mcc.manager.vns@example.invalid')->get()->getRowArray();
        if (! is_array($actor)) {
            self::markTestSkipped('Admission Test fixture user is unavailable.');
        }
        $this->actor = (int) $actor['id'];
        session()->set([
            'isLoggedIn' => true,
            'accountEnabled' => true,
            'user' => ['id' => $this->actor],
            'session_version' => 1,
            'authorizedBranches' => [(string) $branch['id']],
            'permissions' => ['admission.view', 'admission.create', 'admission.update'],
        ]);
        \Config\Services::resetSingle('authorization');
    }

    protected function tearDown(): void
    {
        if ($this->studentLinkTest > 0) {
            $this->db->table('admission_tests')->where('id', $this->studentLinkTest)->delete();
        }

        session()->destroy();
        \Config\Services::resetSingle('authorization');
        parent::tearDown();
    }

    public function testAdmissionTestWorkflowTablesAndLineageColumnsExist(): void
    {
        self::assertTrue($this->db->tableExists('admission_tests'));
        self::assertTrue($this->db->tableExists('admission_test_responses'));
        self::assertTrue($this->db->tableExists('admission_test_followups'));
        self::assertTrue($this->db->fieldExists('admission_test_id', 'admissions'));
        self::assertTrue($this->db->fieldExists('father_profession', 'admission_tests'));
        self::assertTrue($this->db->fieldExists('reference_details', 'admission_tests'));
        self::assertTrue($this->db->tableExists('admission_test_submission_history'));
        self::assertTrue($this->db->fieldExists('overall_mark', 'admission_tests'));
        self::assertTrue($this->db->fieldExists('assessment_remarks', 'admission_tests'));
        self::assertTrue($this->db->fieldExists('training_fee', 'admission_tests'));
        self::assertTrue($this->db->fieldExists('hostel_charges', 'admission_tests'));
        self::assertTrue($this->db->tableExists('admission_test_coaching_installments'));
        self::assertTrue($this->db->tableExists('admission_test_hostel_installments'));
        foreach ([
            'admission_test_coaching_installments',
            'admission_test_hostel_installments',
        ] as $table) {
            $fields = $this->db->getFieldData($table);
            foreach (['due_date', 'amount'] as $name) {
                $field = array_values(array_filter(
                    $fields,
                    static fn (object $candidate): bool => $candidate->name === $name,
                ))[0] ?? null;
                self::assertIsObject($field);
                self::assertContains(strtoupper((string) $field->nullable), ['YES', '1', 'TRUE']);
            }
        }
        self::assertTrue($this->db->tableExists('admission_test_student_links'));
        self::assertTrue($this->db->fieldExists('token_hash', 'admission_test_student_links'));
        self::assertTrue($this->db->fieldExists('used_at', 'admission_test_student_links'));
        self::assertTrue($this->db->fieldExists('expires_at', 'admission_test_student_links'));
    }

    public function testPersonalDetailsFixtureLinksToAnAuthorizedTrialBranch(): void
    {
        $test = $this->db->table('admission_tests')
            ->select('admission_tests.id, admission_tests.trial_id, admission_tests.branch_id')
            ->where('admission_tests.id', 2)
            ->get()
            ->getRowArray();

        if (! is_array($test)) {
            self::markTestSkipped('Admission Test 2 fixture is unavailable.');
        }

        self::assertGreaterThan(0, (int) $test['trial_id']);
        self::assertGreaterThan(0, (int) $test['branch_id']);
        self::assertSame(
            1,
            $this->db->table('trials')
                ->where('id', (int) $test['trial_id'])
                ->where('branch_id', (int) $test['branch_id'])
                ->countAllResults(),
        );
    }

    public function testStudentLinkIsSingleUseAndStoresOnlyItsHash(): void
    {
        $branch = $this->db->table('branches')->where('code', 'TEST_MCC_VNS')->get()->getRowArray();
        $trial = $this->db->query(
            'SELECT t.id FROM trials t '
            . 'WHERE t.branch_id = ? '
            . 'AND NOT EXISTS (SELECT 1 FROM admission_tests at WHERE at.trial_id = t.id) '
            . 'ORDER BY t.id LIMIT 1',
            [(int) $branch['id']],
        )->getRowArray();
        if (! is_array($trial)) {
            self::markTestSkipped('No unused Trial fixture is available.');
        }

        $service = service('admissionTest', false);
        $id = $service->createFromTrial((int) $trial['id']);
        $this->studentLinkTest = $id;
        $test = $this->db->table('admission_tests')->where('id', $id)->get()->getRowArray();
        $service->selectPaperSet($id, ['version' => $test['version'], 'paper_set' => 'A']);

        $url = $service->generateStudentLink($id);
        self::assertMatchesRegularExpression('#/admission-tests/student/([a-f0-9]{64})$#', $url);
        preg_match('#/admission-tests/student/([a-f0-9]{64})$#', $url, $matches);
        $token = $matches[1];
        $link = $this->db->table('admission_test_student_links')->where('admission_test_id', $id)->get()->getRowArray();

        self::assertIsArray($link);
        self::assertSame(hash('sha256', $token), $link['token_hash']);
        self::assertStringNotContainsString($token, json_encode($link, JSON_THROW_ON_ERROR));
        self::assertNull($link['used_at']);
        self::assertSame(
            (new \DateTimeImmutable($link['created_at'], new \DateTimeZone('UTC')))
                ->add(new \DateInterval('PT30M'))
                ->format('Y-m-d H:i:s'),
            $link['expires_at'],
        );

        $test = $this->db->table('admission_tests')->where('id', $id)->get()->getRowArray();
        $payload = [
            'version' => $test['version'],
            'variant' => 'A',
            'applicant_full_name' => 'TEST_STUDENT_LINK_APPLICANT',
        ];
        foreach (range(1, 10) as $question) {
            $payload['answer_A_' . $question] = '';
        }

        $service->saveStudentPersonalDetails($token, $payload);
        $test = $this->db->table('admission_tests')->where('id', $id)->get()->getRowArray();
        $payload['version'] = $test['version'];
        $service->submitStudentWizard($token, $payload);

        $link = $this->db->table('admission_test_student_links')->where('admission_test_id', $id)->get()->getRowArray();
        $submitted = $this->db->table('admission_tests')->where('id', $id)->get()->getRowArray();
        self::assertNotNull($link['used_at']);
        self::assertSame('TEST_SUBMITTED', $submitted['status']);
        self::assertSame(10, $this->db->table('admission_test_responses')->where('admission_test_id', $id)->countAllResults());
        $audits = $this->db->table('audit_logs')
            ->where('entity_id', (string) $id)
            ->whereIn('action', [
                'admission_test_student_link_generated',
                'admission_test_student_link_opened',
                'admission_test_student_personal_details_saved',
                'admission_test_student_submitted',
            ])
            ->get()
            ->getResultArray();
        foreach ($audits as $audit) {
            self::assertStringNotContainsString($token, json_encode($audit, JSON_THROW_ON_ERROR));
        }

        try {
            $service->submitStudentWizard($token, $payload);
            self::fail('A used student link was accepted again.');
        } catch (\RuntimeException $exception) {
            self::assertSame('Student test link unavailable or expired.', $exception->getMessage());
        }
    }

    public function testTrialAdmissionTestCreationIsIdempotentAndStoresStructuredResponses(): void
    {
        $branch = $this->db->table('branches')->where('code', 'TEST_MCC_VNS')->get()->getRowArray();
        $trial = $this->db->query(
            'SELECT t.* FROM trials t WHERE t.branch_id = ? '
            . 'AND NOT EXISTS (SELECT 1 FROM admission_tests at WHERE at.trial_id = t.id) '
            . 'ORDER BY t.id LIMIT 1',
            [(int) $branch['id']],
        )->getRowArray();
        if (! is_array($trial)) {
            self::markTestSkipped('No unused Trial fixture is available.');
        }
        $service = service('admissionTest', false);

        $first = $service->createFromTrial((int) $trial['id']);
        $second = $service->createFromTrial((int) $trial['id']);

        self::assertSame($first, $second);
        self::assertSame(1, $this->db->table('admission_tests')->where('trial_id', (int) $trial['id'])->countAllResults());

        $test = $this->db->table('admission_tests')->where('id', $first)->get()->getRowArray();
        $service->selectPaperSet($first, ['version' => $test['version'], 'paper_set' => 'B']);
        $test = $this->db->table('admission_tests')->where('id', $first)->get()->getRowArray();
        $service->savePersonalDetails($first, [
            'version' => $test['version'],
            'applicant_full_name' => 'TEST_ADMISSION_TEST_APPLICANT',
        ]);
        $test = $this->db->table('admission_tests')->where('id', $first)->get()->getRowArray();
        $payload = ['version' => $test['version'], 'variant' => 'B'];
        foreach (range(1, 10) as $question) {
            $payload['answer_B_' . $question] = '';
        }
        $service->saveBasicTest($first, $payload);

        self::assertSame('TEST_SUBMITTED', $this->db->table('admission_tests')->where('id', $first)->get()->getRowArray()['status']);
        self::assertSame('B', $this->db->table('admission_tests')->where('id', $first)->get()->getRowArray()['test_variant']);
        self::assertSame(10, $this->db->table('admission_test_responses')->where('admission_test_id', $first)->countAllResults());
        $submitted = $this->db->table('admission_tests')->where('id', $first)->get()->getRowArray();

        $feePayload = [
            'version' => $submitted['version'],
            'training_fee' => '400',
            'hostel_charges' => '0',
            'installment_plan' => 'Instalment Plan',
            'hostel_installment_plan' => 'Full Payment',
            'installment_year' => [1],
            'installment_1_date' => ['2027-01-01'],
            'installment_1' => ['100'],
            'installment_2_date' => ['2027-02-01'],
            'installment_2' => ['100'],
            'installment_3_date' => ['2027-03-01'],
            'installment_3' => ['100'],
            'installment_4_date' => ['2027-04-01'],
            'installment_4' => ['100'],
            'fee_update_reason' => 'TEST_MCC initial schedule',
        ];
        $service->saveFees($first, $feePayload);
        $oneRowCount = $this->db->table('admission_test_coaching_installments')
            ->where('admission_test_id', $first)
            ->countAllResults();
        self::assertSame(4, $oneRowCount);
        self::assertSame(1, $this->db->table('admission_test_coaching_installments')
            ->select('year_number')
            ->where('admission_test_id', $first)
            ->groupBy('year_number')
            ->countAllResults());

        $firstRows = $this->db->table('admission_test_coaching_installments')
            ->where('admission_test_id', $first)
            ->orderBy('installment_number')
            ->get()
            ->getResultArray();
        $firstIds = [];
        foreach ($firstRows as $row) {
            $firstIds[(int) $row['installment_number']] = (int) $row['id'];
        }
        $twoRowPayload = $feePayload;
        $twoRowPayload['version'] = $this->db->table('admission_tests')->where('id', $first)->get()->getRowArray()['version'];
        $twoRowPayload['installment_year'] = [1, 2];
        $twoRowPayload['coaching_installment_ids'] = [json_encode($firstIds, JSON_THROW_ON_ERROR), '{}'];
        foreach (range(1, 4) as $number) {
            $twoRowPayload['installment_' . $number . '_date'][] = '2028-0' . $number . '-01';
            $twoRowPayload['installment_' . $number][] = '200';
        }
        $twoRowPayload['fee_update_reason'] = 'TEST_MCC added schedule row';
        $service->saveFees($first, $twoRowPayload);
        self::assertSame(8, $this->db->table('admission_test_coaching_installments')
            ->where('admission_test_id', $first)
            ->countAllResults());
        self::assertSame(2, $this->db->table('admission_test_coaching_installments')
            ->select('year_number')
            ->where('admission_test_id', $first)
            ->groupBy('year_number')
            ->countAllResults());

        $secondRows = $this->db->table('admission_test_coaching_installments')
            ->where(['admission_test_id' => $first, 'year_number' => 2])
            ->get()
            ->getResultArray();
        $secondIds = array_map(static fn (array $row): int => (int) $row['id'], $secondRows);
        $removeSecondPayload = $twoRowPayload;
        $removeSecondPayload['version'] = $this->db->table('admission_tests')->where('id', $first)->get()->getRowArray()['version'];
        $removeSecondPayload['installment_year'] = [1];
        $removeSecondPayload['coaching_installment_ids'] = [json_encode($firstIds, JSON_THROW_ON_ERROR)];
        foreach (range(1, 4) as $number) {
            $removeSecondPayload['installment_' . $number . '_date'] = [$feePayload['installment_' . $number . '_date'][0]];
            $removeSecondPayload['installment_' . $number] = [$feePayload['installment_' . $number][0]];
        }
        $removeSecondPayload['removed_installment_ids'] = $secondIds;
        $removeSecondPayload['fee_update_reason'] = 'TEST_MCC removed schedule row';
        $service->saveFees($first, $removeSecondPayload);
        self::assertSame(4, $this->db->table('admission_test_coaching_installments')
            ->where('admission_test_id', $first)
            ->countAllResults());
        self::assertSame(0, $this->db->table('admission_test_coaching_installments')->whereIn('id', $secondIds)->countAllResults());

        $audit = $this->db->table('audit_logs')
            ->where(['action' => 'admission_test_fees_updated', 'entity_id' => (string) $first])
            ->orderBy('id', 'DESC')
            ->get()
            ->getRowArray();
        self::assertIsArray($audit);
        self::assertStringContainsString((string) $secondIds[0], (string) $audit['after_summary']);

        try {
            $service->saveFees($first, $removeSecondPayload);
            self::fail('A retried fee request with a stale version must be rejected.');
        } catch (\RuntimeException $exception) {
            self::assertSame('Update conflict.', $exception->getMessage());
        }
        self::assertSame($oneRowCount, $this->db->table('admission_test_coaching_installments')
            ->where('admission_test_id', $first)
            ->countAllResults());

        $correction = ['version' => $submitted['version'], 'variant' => 'B'];
        try {
            $service->saveBasicTest($first, $correction);
            self::fail('Submitted Admission Test answers must be read-only.');
        } catch (\RuntimeException $exception) {
            self::assertSame('Submitted Admission Test answers are read-only.', $exception->getMessage());
        }
        self::assertSame(0, $this->db->table('admission_test_submission_history')->where('admission_test_id', $first)->countAllResults());
        self::assertSame(10, $this->db->table('admission_test_responses')->where('admission_test_id', $first)->countAllResults());

        $this->db->table('admission_tests')->where('id', $first)->update([
            'status' => 'APPROVED_FOR_ADMISSION',
        ]);

        $approved = $this->db->table('admission_tests')->where('id', $first)->get()->getRowArray();
        $admission = $service->createPendingAdmission($first, [
            'agreement_confirmed' => '1',
            'version' => $approved['version'],
        ]);
        $retry = $service->createPendingAdmission($first, [
            'agreement_confirmed' => '1',
            'version' => $approved['version'],
        ]);
        self::assertSame($admission, $retry);
        self::assertSame(1, $this->db->table('admissions')->where('admission_test_id', $first)->countAllResults());
    }

    public function testAdmissionTestBlankAndPartialFeeRowsPersistAndRollback(): void
    {
        $branch = $this->db->table('branches')->where('code', 'TEST_MCC_VNS')->get()->getRowArray();
        $trial = $this->db->query(
            'SELECT t.id FROM trials t WHERE t.branch_id = ? '
            . 'AND NOT EXISTS (SELECT 1 FROM admission_tests at WHERE at.trial_id = t.id) '
            . 'ORDER BY t.id LIMIT 1',
            [(int) $branch['id']],
        )->getRowArray();
        if (! is_array($trial)) {
            self::markTestSkipped('No unused Trial fixture is available.');
        }

        $service = service('admissionTest', false);
        $id = $service->createFromTrial((int) $trial['id']);
        $this->studentLinkTest = $id;
        $test = $this->db->table('admission_tests')->where('id', $id)->get()->getRowArray();
        $service->selectPaperSet($id, ['version' => $test['version'], 'paper_set' => 'A']);
        $test = $this->db->table('admission_tests')->where('id', $id)->get()->getRowArray();
        $service->savePersonalDetails($id, [
            'version' => $test['version'],
            'applicant_full_name' => 'TEST_PARTIAL_INSTALLMENT_APPLICANT',
        ]);
        $test = $this->db->table('admission_tests')->where('id', $id)->get()->getRowArray();
        $answers = ['version' => $test['version'], 'variant' => 'A'];
        foreach (range(1, 10) as $question) {
            $answers['answer_A_' . $question] = '';
        }
        $service->saveBasicTest($id, $answers);
        $test = $this->db->table('admission_tests')->where('id', $id)->get()->getRowArray();

        $payload = [
            'version' => $test['version'],
            'training_fee' => '400',
            'hostel_charges' => '500',
            'installment_plan' => 'Instalment Plan',
            'hostel_installment_plan' => 'Instalment Plan',
            'installment_year' => [1],
            'installment_1_date' => ['2027-01-01'],
            'installment_1' => [''],
            'hostel_installment_year' => [1],
            'hostel_installment_1_date' => [''],
            'hostel_installment_1' => ['250'],
            'fee_update_reason' => 'TEST_MCC partial schedule',
        ];
        $service->saveFees($id, $payload);

        $coaching = $this->db->table('admission_test_coaching_installments')
            ->where('admission_test_id', $id)
            ->get()
            ->getResultArray();
        $hostel = $this->db->table('admission_test_hostel_installments')
            ->where('admission_test_id', $id)
            ->get()
            ->getResultArray();
        self::assertCount(1, $coaching);
        self::assertSame('2027-01-01', $coaching[0]['due_date']);
        self::assertNull($coaching[0]['amount']);
        self::assertCount(1, $hostel);
        self::assertNull($hostel[0]['due_date']);
        self::assertSame('250.00', $hostel[0]['amount']);

        $invalid = $payload;
        $invalid['version'] = $this->db->table('admission_tests')->where('id', $id)->get()->getRow('version');
        $invalid['installment_1'] = ['not-a-number'];
        $invalid['fee_update_reason'] = 'TEST_MCC invalid partial schedule';
        try {
            $service->saveFees($id, $invalid);
            self::fail('An invalid supplied Admission Test amount was accepted.');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('amount', strtolower($exception->getMessage()));
        }
        self::assertCount(1, $this->db->table('admission_test_coaching_installments')->where('admission_test_id', $id)->get()->getResultArray());

        $blank = $payload;
        $blank['version'] = $this->db->table('admission_tests')->where('id', $id)->get()->getRow('version');
        $blank['installment_1_date'] = [''];
        $blank['installment_1'] = [''];
        $blank['hostel_installment_1_date'] = [''];
        $blank['hostel_installment_1'] = [''];
        $blank['fee_update_reason'] = 'TEST_MCC clear blank schedule';
        $service->saveFees($id, $blank);
        self::assertSame(0, $this->db->table('admission_test_coaching_installments')->where('admission_test_id', $id)->countAllResults());
        self::assertSame(0, $this->db->table('admission_test_hostel_installments')->where('admission_test_id', $id)->countAllResults());
    }

    public function testAdmissionTestFollowUpUsesAdmissionTestLineageAndNullAssignment(): void
    {
        $test = $this->db->table('admission_tests')->where('id', 118)->get()->getRowArray();
        if (! is_array($test)) {
            self::markTestSkipped('Admission Test 118 fixture is unavailable.');
        }
        if (! in_array((string) ($test['status'] ?? ''), ['TEST_SUBMITTED', 'MEETING_FOLLOW_UP', 'APPROVED_FOR_ADMISSION'], true)) {
            self::markTestSkipped('Admission Test 118 is not eligible for a Meeting Follow-up fixture.');
        }

        $notes = 'TEST_MCC_ADMISSION_TEST_118_' . bin2hex(random_bytes(4));
        $payload = [
            'follow_up_date' => '2027-12-15',
            'follow_up_time' => '09:10',
            'follow_up_type' => 'meeting',
            'status' => 'scheduled',
            'notes' => $notes,
            'assigned_user_id' => '',
        ];
        $service = service('admissionTest', false);

        $first = $service->createFollowUp(118, $payload);
        $second = $service->createFollowUp(118, $payload);
        $row = $this->db->table('admission_test_followups')->where('id', $first)->get()->getRowArray();

        self::assertSame($first, $second);
        self::assertIsArray($row);
        self::assertSame(118, (int) $row['admission_test_id']);
        self::assertSame((int) $test['trial_id'], (int) $row['trial_id']);
        self::assertSame((int) $test['branch_id'], (int) $row['branch_id']);
        self::assertNull($row['assigned_user_id']);
        self::assertSame($service->normalizeFollowUpAt('2027-12-15', '09:10'), $row['follow_up_at']);
        self::assertSame(1, $this->db->table('admission_test_followups')->where('id', $first)->countAllResults());
    }
}
