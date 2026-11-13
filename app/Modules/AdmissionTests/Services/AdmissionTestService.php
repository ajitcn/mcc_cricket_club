<?php

declare(strict_types=1);

namespace App\Modules\AdmissionTests\Services;

use App\Modules\AdmissionTests\Exceptions\CompletedAdmissionTestException;
use App\Modules\AdmissionTests\Models\AdmissionTestFollowUpModel;
use App\Modules\AdmissionTests\Models\AdmissionTestModel;
use App\Modules\AdmissionTests\Models\AdmissionTestResponseModel;
use Config\Database;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;
use Throwable;

final class AdmissionTestService
{
    private const STATUSES = [
        'DRAFT',
        'BASIC_DETAILS_COMPLETED',
        'TEST_IN_PROGRESS',
        'TEST_SUBMITTED',
        'MEETING_FOLLOW_UP',
        'APPROVED_FOR_ADMISSION',
        'REJECTED',
        'ON_HOLD',
        'REJECTED_CLOSED',
    ];

    private const MANAGED_STATUSES = [
        'TEST_SUBMITTED',
        'APPROVED_FOR_ADMISSION',
        'REJECTED',
        'ON_HOLD',
    ];

    private const REPRESENTED = ['School', 'Academy', 'District', 'State', 'MCA', 'None'];
    private const COACHING_TYPES = ['Group', 'Personal', 'Short term', 'Advance Training', 'Others'];
    private const SKILLS = ['Batsman', 'Bowler', 'All-rounder'];
    private const BRANCHES = ['Santa Cruz', 'Thane', 'Bhandup'];
    private const FOLLOW_UP_TYPES = ['call', 'whatsapp', 'email', 'meeting', 'other'];
    private const FOLLOW_UP_STATUSES = ['pending', 'scheduled', 'completed', 'cancelled'];
    private const PAYMENT_PLAN_OPTIONS = ['Full Payment', 'Instalment Plan'];
    private const FEE_SCHEDULE_TABLES = [
        'coaching' => 'admission_test_coaching_installments',
        'hostel' => 'admission_test_hostel_installments',
    ];
    /**
     * Exact Back Side.pdf / Admission Test field mappings only.
     * Lead values are preferred; Admission values are fallback values.
     * Trial has no exact matching personal-detail field in the approved matrix.
     *
     * @var array<string, array{lead: string, admission: string}>
     */
    private const PREFILL_MAPPINGS = [
        'applicant_full_name' => ['lead' => 'full_name', 'admission' => 'applicant_full_name'],
        'applicant_email' => ['lead' => 'email', 'admission' => 'applicant_email'],
        'applicant_phone' => ['lead' => 'phone', 'admission' => 'applicant_phone'],
        'applicant_date_of_birth' => ['lead' => 'date_of_birth', 'admission' => 'applicant_date_of_birth'],
        'father_name' => ['lead' => '', 'admission' => 'father_name'],
        'father_profession' => ['lead' => '', 'admission' => 'father_profession'],
        'age' => ['lead' => '', 'admission' => 'age'],
        'residence' => ['lead' => '', 'admission' => 'residence'],
        'contact_number' => ['lead' => 'phone', 'admission' => 'parent_mobile'],
        'secondary_contact_number' => ['lead' => '', 'admission' => 'secondary_contact_number'],
        'email' => ['lead' => 'email', 'admission' => 'parent_email'],
        'secondary_email' => ['lead' => '', 'admission' => 'secondary_email'],
        'represented_as' => ['lead' => '', 'admission' => 'represented_as'],
        'coaching_start' => ['lead' => '', 'admission' => 'joining_date'],
        'coaching_type' => ['lead' => '', 'admission' => 'coaching_type'],
        'playing_role' => ['lead' => '', 'admission' => 'playing_role'],
        'nearest_branch' => ['lead' => '', 'admission' => 'nearest_branch'],
        'discovery_source' => ['lead' => '', 'admission' => 'discovery_source'],
        'reference_details' => ['lead' => '', 'admission' => 'reference_details'],
        'achievements' => ['lead' => '', 'admission' => 'achievements'],
    ];

    /** @return array<string, list<array{prompt: string, options: list<string>}>> */
    public function questions(): array
    {
        return [
            'A' => [
                ['prompt' => 'Who cracked the Highest score in Ranji Trophy?', 'options' => ['Sachin Tendulkar', 'Virender Sehwag', 'Aakash Chopra', 'Wasim Jaffer']],
                ['prompt' => 'In which direction does the ball of left arm off spinner turn for Right Hand Batsman?', 'options' => ['In', 'Out']],
                ['prompt' => 'who was the MOM for final match? [year NEEDS_DECISION]', 'options' => ['Sandeep Patil', 'Mohinder Amarnath', 'Kapil Dev', 'Sunil Gavaskar']],
                ['prompt' => 'Who won the 2018 U-19 world cup?', 'options' => ['India', 'sri-lanka', 'Pakistan', 'Australia']],
                ['prompt' => 'who bowled the fastest ball in cricket History?', 'options' => ['Zaheer Khan', 'shoaib akhtar', 'Shaun Tait', 'Brett Lee']],
                ['prompt' => 'Who scored 2nd ... [NEEDS_DECISION]', 'options' => ['Virender Sehwag', 'Sunil Gavaskar', 'Karun Nair', 'Rahul Dravid', 'V.V.S Laxman']],
                ['prompt' => 'What is the maximum number of overs a bowler can bowl in T-20 cricket?', 'options' => ['4', '6', '5', '2']],
                ['prompt' => 'In a T-20 match, how many overs of power play is allowed? [paper contains the one-day restriction text]', 'options' => ['6', '5', '4', '10']],
                ['prompt' => 'How long is the duration of one innings of a T-20 match?', 'options' => ['60 minutes', '75 minutes', '20 minutes', '90 minutes']],
                ['prompt' => 'In T-cricket, how many bouncers are allowed in any over? [NEEDS_DECISION]', 'options' => ['2', '0', '3', '1']],
            ],
            'B' => [
                ['prompt' => 'Who got the Highest Left arm wicket in an International cricket?', 'options' => ['Deniel Vettori', 'Rangana Herath', 'Pragyan Ojha', 'Ashley Giles']],
                ['prompt' => 'A new concept in T-20 cricket is that of the football style bowl-out. When is a bowl-out resorted to?', 'options' => ['When the match is interrupted by rain', 'When the match has to be called off', 'When the result of the match is a tie', 'When there is time for only less than 5 overs to be bowled']],
                ['prompt' => 'A bowler bowls a fast full toss (beamer) at the batsman which misses his head, this is immediately called as a no-ball by the umpire. The batsman gets a free hit the next delivery.', 'options' => ['TRUE', 'False']],
                ['prompt' => 'who cracked the Highest score in Ranji Trophy?', 'options' => ['Sachin Tendulkar', 'Virender Sehwag', 'Aakash Chopra', 'Wasim Jaffer']],
                ['prompt' => 'What is the name given to the biennial international Test cricket series played between England and Australia?', 'options' => ['The Sheffield Shield', 'The Cricket World Cup', 'The Trans-Tasman Trophy', 'The Ashes']],
                ['prompt' => 'What does the term LBW stand for in cricket?', 'options' => ['Long blue wickets', 'Lunch bought weekly', 'Leg before wicket', 'Leg before wide']],
                ['prompt' => 'What is the length of a cricket pitch?', 'options' => ['20.12m', '20.50 m', '22.12m', '21.20m']],
                ['prompt' => 'What is the maximum permissible length of bat in cricket?', 'options' => ['38 inches', '42 inches', '35 inches', '40 inches']],
                ['prompt' => 'Who is the youngest cricketer to score in list A in cricket? [literal PDF wording; NEEDS_DECISION]', 'options' => ['Sanju Samson', 'Virender Sehwag', 'Kumar Sangakara', 'Yashasvi Jaiswal']],
                ['prompt' => 'Another new concept of T-20 cricket is the free-hit, wherein a batsman gets a chance to have a free hit at the ball knowing that he cannot be given out. When does the free-hit come into play?', 'options' => ['When the bowler bowls a no ball', 'When the bowler bowls a beamer', 'When the bowler bowls a wide', 'When the bowler sledges the batsman']],
            ],
            'C' => [
                ['prompt' => 'What does the term LBW stand for in cricket?', 'options' => ['Long blue wickets', 'Lunch bought weekly', 'Leg before wicket', 'Leg before wide']],
                ['prompt' => 'Who got the Highest Left arm wicket in an International cricket ?', 'options' => ['Deniel Vettori', 'Rangana Herath', 'Pragyan Ojha', 'Ashley Giles']],
                ['prompt' => 'Who cracked the Highest score in Ranji Trophy?', 'options' => ['Sachin Tendulkar', 'Virender Sehwag', 'Aakash Chopra', 'Wasim Jaffer']],
                ['prompt' => 'Who scored 2... [NEEDS_DECISION]', 'options' => ['Virender Sehwag', 'Sunil Gavaskar', 'Karun Nair', 'Rahul Dravid', 'V.V.S Laxman']],
                ['prompt' => 'Who is the youngest cricketer to score in list A in cricket? [literal PDF wording; NEEDS_DECISION]', 'options' => ['Sanju Samson', 'Virender Sehwag', 'Kumar Sangakara', 'Yashasvi Jaiswal']],
                ['prompt' => 'How long is the duration of one innings of a T-20 match?', 'options' => ['60 minutes', '75 minutes', '20 minutes', '90 minutes']],
                ['prompt' => 'who was the MOM for final match? [year NEEDS_DECISION]', 'options' => ['Sandeep Patil', 'Mohinder Amarnath', 'Kapil Dev', 'Sunil Gavaskar']],
                ['prompt' => 'For how many days is a Test match scheduled?', 'options' => ['50 overs', 'Five days', 'One day', '100 overs']],
                ['prompt' => 'Full form of ICC is:', 'options' => ['India Cricket Council', 'International Cricket Committee', 'International Cricket Council', 'Intercontinental Cricket Corporation']],
                ['prompt' => 'A bowler bowls a fast full toss (beamer) at the batsman which misses his head, this is immediately called as a no-ball by the umpire. The batsman gets a free hit the next delivery.', 'options' => ['TRUE', 'False']],
            ],
        ];
    }

    /** @return array<string, string> */
    public function paperSetOptions(): array
    {
        return [
            'A' => 'Paper Set A',
            'B' => 'Paper Set B',
            'C' => 'Paper Set C',
        ];
    }

    /** @return array<string, list<array{prompt: string, options: list<string>}>> */
    public function questionsForTest(array $test): array
    {
        $paperSet = strtoupper(trim((string) ($test['test_variant'] ?? '')));
        if (! array_key_exists($paperSet, $this->questions())) {
            return [];
        }

        return [$paperSet => $this->questions()[$paperSet]];
    }

    public function selectPaperSet(int $id, array $input): void
    {
        $db = Database::connect('default');
        $current = $this->locked($db, $id);
        $actor = $this->authorize((int) $current['branch_id'], 'admission.update');
        $this->assertAllowedStatus((string) $current['status'], ['DRAFT', 'BASIC_DETAILS_COMPLETED']);
        $this->checkVersion($current, $input);

        $paperSet = strtoupper(trim((string) ($input['paper_set'] ?? '')));
        if (! array_key_exists($paperSet, $this->paperSetOptions())) {
            throw new RuntimeException('Select a valid paper set.');
        }
        if (trim((string) ($current['test_variant'] ?? '')) !== '') {
            throw new RuntimeException('The Admission Test paper set is already locked.');
        }

        $now = gmdate('Y-m-d H:i:s');
        $db->transStart();
        try {
            $nextVersion = (int) $current['version'] + 1;
            $db->table('admission_tests')
                ->where('id', $id)
                ->where('version', (int) $current['version'])
                ->where('test_variant IS NULL', null, false)
                ->update([
                    'test_variant' => $paperSet,
                    'version' => $nextVersion,
                    'updated_by' => $actor,
                    'updated_at' => $now,
                ]);
            if ($db->affectedRows() !== 1) {
                throw new RuntimeException('Update conflict.');
            }

            service('auditPersistence')->record(
                $actor,
                'admission_test_paper_set_selected',
                'admission_test',
                (string) $id,
                (int) $current['branch_id'],
                ['paper_set' => null, 'version' => (int) $current['version']],
                ['paper_set' => $paperSet, 'version' => $nextVersion],
                'Paper set selected and permanently locked.',
                $db,
            );
            $db->transComplete();
            $this->assertTransaction($db, 'Unable to save the Admission Test paper set.');
        } catch (Throwable $exception) {
            $db->transRollback();
            $db->resetTransStatus();
            throw $exception;
        }
    }

    public function createFromTrial(int $trialId): int
    {
        $db = Database::connect('default');
        $trial = $this->trial($db, $trialId, true);
        $actor = $this->authorize((int) $trial['branch_id'], 'admission.create');
        $existing = $db->table('admission_tests')->where('trial_id', $trialId)->get()->getRowArray();

        if (is_array($existing)) {
            if ((int) $existing['branch_id'] !== (int) $trial['branch_id']) {
                throw new RuntimeException('Admission Test branch mismatch.');
            }

            if ($this->isCompleted($existing)) {
                throw new CompletedAdmissionTestException((int) $existing['id']);
            }

            return (int) $existing['id'];
        }

        $lead = $this->linkedLead($db, $trial);
        if ($lead === null) {
            throw new RuntimeException('Invalid Trial source.');
        }
        $admission = $this->linkedAdmission($db, $trial, $lead);

        $now = gmdate('Y-m-d H:i:s');
        $prefill = $this->sourcePrefill($lead, $admission);
        $db->transStart();
        try {
            $model = new AdmissionTestModel();
            $model->insert(array_merge([
                'trial_id' => $trialId,
                'lead_id' => (int) $lead['id'],
                'branch_id' => (int) $trial['branch_id'],
                'created_by' => $actor,
                'created_at' => $now,
                'updated_at' => $now,
            ], $prefill['values']));
            $id = (int) $model->getInsertID();
            if ($id < 1) {
                throw new RuntimeException('Unable to create Admission Test.');
            }

            service('auditPersistence')->record($actor, 'admission_test_created', 'admission_test', (string) $id, (int) $trial['branch_id'], null, ['trial_id' => $trialId], null, $db);
            $db->transComplete();
            $this->assertTransaction($db, 'Unable to create Admission Test.');
        } catch (Throwable $exception) {
            $db->transRollback();
            $db->resetTransStatus();
            throw $exception;
        }

        return $id;
    }

    public function isCompleted(array $test): bool
    {
        return trim((string) ($test['submitted_at'] ?? '')) !== ''
            || in_array((string) ($test['status'] ?? ''), [
                'TEST_SUBMITTED',
                'MEETING_FOLLOW_UP',
                'APPROVED_FOR_ADMISSION',
                'REJECTED',
                'ON_HOLD',
                'REJECTED_CLOSED',
            ], true);
    }

    public function details(int $id): array
    {
        $db = Database::connect('default');
        $row = $this->testRow($db, $id);

        if (! is_array($row)) {
            throw new RuntimeException('Not found.');
        }

        $this->authorize((int) $row['branch_id'], 'admission.view');

        return $this->hydrateDetails($db, $row);
    }

    /** @return array<string, mixed> */
    public function studentDetails(string $token): array
    {
        $db = Database::connect('default');
        $link = $this->studentLink($db, $token, false);
        $row = $this->testRow($db, (int) $link['admission_test_id']);

        if (! is_array($row)) {
            throw new RuntimeException('Student test link unavailable or expired.');
        }

        service('auditPersistence')->record(
            null,
            'admission_test_student_link_opened',
            'admission_test',
            (string) $row['id'],
            (int) $row['branch_id'],
            null,
            [
                'link_id' => (int) $link['id'],
                'expires_at' => $this->studentLinkExpiryValue($link),
            ],
            null,
            $db,
        );

        return $this->hydrateDetails($db, $row);
    }

    public function generateStudentLink(int $id): string
    {
        $db = Database::connect('default');
        $current = $this->locked($db, $id);
        $actor = $this->authorize((int) $current['branch_id'], 'admission.update');
        if (trim((string) ($current['test_variant'] ?? '')) === '') {
            throw new RuntimeException('Select and lock a paper set before generating the student link.');
        }
        if ($this->isCompleted($current)) {
            throw new RuntimeException('Admission Test already completed.');
        }

        $token = bin2hex(random_bytes(32));
        $now = gmdate('Y-m-d H:i:s');
        $expiresAt = $this->studentLinkExpiry($now);
        $db->transStart();
        try {
            $db->table('admission_test_student_links')
                ->where('admission_test_id', $id)
                ->where('used_at IS NULL', null, false)
                ->where('revoked_at IS NULL', null, false)
                ->update(['revoked_at' => $now]);

            $inserted = $db->table('admission_test_student_links')->insert([
                'admission_test_id' => $id,
                'branch_id' => (int) $current['branch_id'],
                'token_hash' => hash('sha256', $token),
                'created_by' => $actor,
                'created_at' => $now,
                'expires_at' => $expiresAt,
            ]);
            if ($inserted !== true) {
                throw new RuntimeException('Unable to generate the student test link.');
            }

            $linkId = (int) $db->insertID();
            service('auditPersistence')->record(
                $actor,
                'admission_test_student_link_generated',
                'admission_test',
                (string) $id,
                (int) $current['branch_id'],
                null,
                ['link_id' => $linkId, 'expires_at' => $expiresAt],
                'Student test link generated without persisting the raw token.',
                $db,
            );
            $db->transComplete();
            $this->assertTransaction($db, 'Unable to generate the student test link.');
        } catch (Throwable $exception) {
            $db->transRollback();
            $db->resetTransStatus();
            throw $exception;
        }

        return site_url('admission-tests/student/' . $token);
    }

    public function saveStudentPersonalDetails(string $token, array $input): void
    {
        $db = Database::connect('default');
        $link = $this->studentLink($db, $token, false);
        $id = (int) $link['admission_test_id'];
        $current = $this->locked($db, $id);
        $errors = $this->wizardValidationErrors($id, $input, false);
        if ($errors !== []) {
            throw new RuntimeException('Complete the highlighted Admission Test fields.');
        }
        $this->assertAllowedStatus((string) $current['status'], ['DRAFT', 'BASIC_DETAILS_COMPLETED', 'TEST_IN_PROGRESS']);
        $this->checkVersion($current, $input);

        $data = $this->personalFields($input);
        $data['status'] = 'BASIC_DETAILS_COMPLETED';
        $now = gmdate('Y-m-d H:i:s');
        $db->transStart();
        try {
            $lockedLink = $this->studentLink($db, $token, true);
            $current = $this->locked($db, (int) $lockedLink['admission_test_id']);
            $this->assertAllowedStatus((string) $current['status'], ['DRAFT', 'BASIC_DETAILS_COMPLETED', 'TEST_IN_PROGRESS']);
            $this->checkVersion($current, $input);
            $data['version'] = (int) $current['version'] + 1;
            $data['updated_by'] = null;
            $data['updated_at'] = $now;
            $updated = $db->table('admission_tests')
                ->where('id', $id)
                ->where('version', (int) $current['version'])
                ->update($data);
            if ($updated !== true || $db->affectedRows() !== 1) {
                throw new RuntimeException('Update conflict.');
            }
            service('auditPersistence')->record(
                null,
                'admission_test_student_personal_details_saved',
                'admission_test',
                (string) $id,
                (int) $current['branch_id'],
                $this->auditSummary($current),
                ['version' => $data['version']],
                null,
                $db,
            );
            $db->transComplete();
            $this->assertTransaction($db, 'Unable to save Admission Test personal details.');
        } catch (Throwable $exception) {
            $db->transRollback();
            $db->resetTransStatus();
            throw $exception;
        }
    }

    /** @return array<string, mixed> */
    private function hydrateDetails(object $db, array $row): array
    {
        $id = (int) $row['id'];
        $lead = $this->linkedLead($db, $row);
        $admission = $this->linkedAdmission($db, $row, $lead);
        $prefill = $this->sourcePrefill($lead, $admission);
        foreach ($prefill['values'] as $field => $value) {
            if ($this->isBlank($row[$field] ?? null)) {
                $row[$field] = $value;
            }
        }
        $row['prefill_sources'] = $prefill['sources'];
        $row['responses'] = (new AdmissionTestResponseModel())->where('admission_test_id', $id)->orderBy('question_number')->findAll();
        $row['follow_ups'] = (new AdmissionTestFollowUpModel())->where('admission_test_id', $id)->orderBy('follow_up_at', 'DESC')->findAll();
        $row['coaching_installment_rows'] = $this->loadFeeRows($db, $id, 'coaching', (int) $row['branch_id']);
        $row['hostel_installment_rows'] = $this->loadFeeRows($db, $id, 'hostel', (int) $row['branch_id']);
        $row['fee_total'] = $this->calculateFeeTotal($row);
        $row['admission_id'] = is_array($admission) ? (int) ($admission['id'] ?? 0) : 0;
        $row['submission_history'] = $db->table('admission_test_submission_history')
            ->where('admission_test_id', $id)
            ->orderBy('version', 'DESC')
            ->get()
            ->getResultArray();
        foreach ($row['submission_history'] as &$history) {
            $history['responses'] = json_decode((string) $history['responses_json'], true) ?: [];
            $history['assessment'] = json_decode((string) $history['assessment_json'], true) ?: [];
        }
        unset($history);

        $remarksWindow = $this->assessmentRemarksWindow($row['submitted_at'] ?? null);
        $row['assessment_remarks_editable'] = $remarksWindow['editable'];
        $row['assessment_remarks_editable_until'] = $remarksWindow['editable_until'];
        $row['assessment_remarks_window_invalid'] = $remarksWindow['invalid'];

        return $row;
    }

    /** @return array<string, mixed>|null */
    private function testRow(object $db, int $id): ?array
    {
        $row = $db->table('admission_tests at')
            ->select('at.*, t.trial_date, t.trial_time, t.location, t.status AS trial_status, t.coach_user_id, t.secondary_coach_user_id, b.name AS branch_name, l.full_name AS lead_name, l.email AS lead_email, l.phone AS lead_phone')
            ->join('trials t', 't.id = at.trial_id')
            ->join('branches b', 'b.id = at.branch_id')
            ->join('leads l', 'l.id = at.lead_id', 'left')
            ->where('at.id', $id)
            ->get()
            ->getRowArray();

        return is_array($row) ? $row : null;
    }

    /**
     * @return array{editable: bool, editable_until: ?DateTimeImmutable, invalid: bool}
     */
    public function assessmentRemarksWindow(?string $submittedAt, ?DateTimeImmutable $now = null): array
    {
        $submittedAt = trim((string) $submittedAt);
        if ($submittedAt === '') {
            return [
                'editable' => false,
                'editable_until' => null,
                'invalid' => false,
            ];
        }

        try {
            $submittedUtc = new DateTimeImmutable($submittedAt, new DateTimeZone('UTC'));
        } catch (Throwable) {
            return [
                'editable' => false,
                'editable_until' => null,
                'invalid' => true,
            ];
        }

        $timezone = new DateTimeZone((string) config('App')->appTimezone);
        $editableUntil = $submittedUtc
            ->setTimezone($timezone)
            ->add(new DateInterval('P7D'));
        $current = ($now ?? new DateTimeImmutable('now', $timezone))->setTimezone($timezone);

        return [
            'editable' => $current <= $editableUntil,
            'editable_until' => $editableUntil,
            'invalid' => false,
        ];
    }

    public function saveAssessmentRemarks(int $id, array $input): void
    {
        $db = Database::connect('default');
        $current = $this->locked($db, $id);
        $actor = $this->authorize((int) $current['branch_id'], 'admission.update');
        $this->assertAllowedStatus((string) $current['status'], [
            'TEST_SUBMITTED',
            'MEETING_FOLLOW_UP',
            'APPROVED_FOR_ADMISSION',
        ]);
        $this->checkVersion($current, $input);

        $remarksWindow = $this->assessmentRemarksWindow($current['submitted_at'] ?? null);
        if ($remarksWindow['invalid']) {
            throw new RuntimeException('The Admission Test submission timestamp is invalid.');
        }
        if (! $remarksWindow['editable']) {
            throw new RuntimeException('Assessment remarks are locked after seven days.');
        }

        $remarks = trim((string) ($input['assessment_remarks'] ?? ''));
        if (strlen($remarks) > 5000) {
            throw new RuntimeException('Assessment remarks must not exceed 5,000 characters.');
        }

        $nextVersion = (int) $current['version'] + 1;
        $now = gmdate('Y-m-d H:i:s');
        $data = [
            'assessment_remarks' => $remarks === '' ? null : $remarks,
            'version' => $nextVersion,
            'updated_by' => $actor,
            'updated_at' => $now,
        ];

        $db->transStart();
        try {
            $db->table('admission_tests')
                ->where('id', $id)
                ->where('version', (int) $current['version'])
                ->update($data);
            if ($db->affectedRows() !== 1) {
                throw new RuntimeException('Update conflict.');
            }

            service('auditPersistence')->record(
                $actor,
                'admission_test_assessment_remarks_updated',
                'admission_test',
                (string) $id,
                (int) $current['branch_id'],
                [
                    'test_id' => $id,
                    'assessment_remarks' => $current['assessment_remarks'] ?? null,
                    'version' => (int) $current['version'],
                ],
                [
                    'test_id' => $id,
                    'assessment_remarks' => $data['assessment_remarks'],
                    'version' => $nextVersion,
                    'timestamp' => $now,
                ],
                null,
                $db,
            );
            $db->transComplete();
            $this->assertTransaction($db, 'Unable to save assessment remarks.');
        } catch (Throwable $exception) {
            $db->transRollback();
            $db->resetTransStatus();
            throw $exception;
        }
    }

    /**
     * Validate the complete wizard payload without changing persistence state.
     *
     * The supplied PDFs do not identify required question fields. Until that
     * requirement is approved, all question answers remain optional while
     * submitted answers are still validated against the exact PDF options.
     *
     * @return array<string, string>
     */
    public function wizardValidationErrors(int $id, array $input, bool $authorize = true): array
    {
        $current = $this->locked(Database::connect('default'), $id);
        if ($authorize) {
            $this->authorize((int) $current['branch_id'], 'admission.update');
        }

        $errors = [];
        $name = trim((string) ($input['applicant_full_name'] ?? ''));
        if ($name === '') {
            $errors['applicant_full_name'] = 'Name of the Player is required.';
        } elseif (strlen($name) > 150) {
            $errors['applicant_full_name'] = 'Name of the Player must not exceed 150 characters.';
        }

        $age = trim((string) ($input['age'] ?? ''));
        if ($age !== '' && (! ctype_digit($age) || (int) $age < 0 || (int) $age > 120)) {
            $errors['age'] = 'Age must be a whole number between 0 and 120.';
        }

        $choices = [
            'represented_as' => self::REPRESENTED,
            'coaching_type' => self::COACHING_TYPES,
            'playing_role' => self::SKILLS,
            'nearest_branch' => self::BRANCHES,
        ];
        foreach ($choices as $field => $allowed) {
            $value = trim((string) ($input[$field] ?? ''));
            if ($value !== '' && ! in_array($value, $allowed, true)) {
                $errors[$field] = 'Select a valid ' . str_replace('_', ' ', $field) . '.';
            }
        }

        foreach (['applicant_email', 'email', 'secondary_email'] as $field) {
            $value = trim((string) ($input[$field] ?? ''));
            if ($value !== '' && filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
                $errors[$field] = 'Enter a valid email address.';
            }
        }

        $variant = strtoupper(trim((string) ($current['test_variant'] ?? '')));
        if (! array_key_exists($variant, $this->questions())) {
            $errors['variant'] = 'Select a paper set before continuing.';
        } elseif (array_key_exists('variant', $input) && strtoupper(trim((string) $input['variant'])) !== $variant) {
            $errors['variant'] = 'The locked paper set cannot be changed.';
        }

        foreach ($this->questions() as $page => $pageQuestions) {
            if ($page !== $variant) {
                foreach ($pageQuestions as $index => $question) {
                    $postedField = 'answer_' . $page . '_' . ($index + 1);
                    if (array_key_exists($postedField, $input) && trim((string) $input[$postedField]) !== '') {
                        $errors[$postedField] = 'Answers for an unselected paper set are not accepted.';
                    }
                }
                continue;
            }
            foreach ($pageQuestions as $index => $question) {
                $field = 'answer_' . $page . '_' . ($index + 1);
                $answer = trim((string) ($input[$field] ?? ''));
                if ($answer !== '' && ! in_array($answer, $question['options'], true)) {
                    $errors[$field] = 'Select one of the printed options.';
                }
            }
        }

        return $errors;
    }

    public function submitWizard(int $id, array $input): void
    {
        $db = Database::connect('default');
        $current = $this->locked($db, $id);
        $actor = $this->authorize((int) $current['branch_id'], 'admission.update');
        $this->assertAllowedStatus((string) $current['status'], ['DRAFT', 'BASIC_DETAILS_COMPLETED', 'TEST_IN_PROGRESS', 'TEST_SUBMITTED']);
        $this->checkVersion($current, $input);

        $errors = $this->wizardValidationErrors($id, $input);
        if ($errors !== []) {
            throw new RuntimeException('Complete the highlighted Admission Test fields.');
        }

        $db->transStart();
        try {
            $this->persistWizardSubmission($db, $current, $id, $input, $actor);
            $db->transComplete();
            $this->assertTransaction($db, 'Unable to submit Admission Test.');
        } catch (Throwable $exception) {
            $db->transRollback();
            $db->resetTransStatus();
            throw $exception;
        }
    }

    public function submitStudentWizard(string $token, array $input): void
    {
        $db = Database::connect('default');
        $link = $this->studentLink($db, $token, false);
        $current = $this->locked($db, (int) $link['admission_test_id']);
        $this->assertAllowedStatus((string) $current['status'], ['DRAFT', 'BASIC_DETAILS_COMPLETED', 'TEST_IN_PROGRESS']);
        $this->checkVersion($current, $input);

        $errors = $this->wizardValidationErrors((int) $link['admission_test_id'], $input, false);
        if ($errors !== []) {
            throw new RuntimeException('Complete the highlighted Admission Test fields.');
        }

        $db->transStart();
        try {
            $lockedLink = $this->studentLink($db, $token, true);
            $current = $this->locked($db, (int) $lockedLink['admission_test_id']);
            $this->assertAllowedStatus((string) $current['status'], ['DRAFT', 'BASIC_DETAILS_COMPLETED', 'TEST_IN_PROGRESS']);
            $this->checkVersion($current, $input);
            $this->persistWizardSubmission(
                $db,
                $current,
                (int) $lockedLink['admission_test_id'],
                $input,
                null,
                (int) $lockedLink['id'],
                $this->studentLinkExpiryValue($lockedLink),
            );
            $db->transComplete();
            $this->assertTransaction($db, 'Unable to submit Admission Test.');
        } catch (Throwable $exception) {
            $db->transRollback();
            $db->resetTransStatus();
            throw $exception;
        }
    }

    private function persistWizardSubmission(
        object $db,
        array $current,
        int $id,
        array $input,
        ?int $actor,
        ?int $studentLinkId = null,
        ?string $studentLinkExpiresAt = null,
    ): void {
        $personal = $this->personalFields($input);
        $variant = strtoupper(trim((string) ($current['test_variant'] ?? '')));
        if (! array_key_exists($variant, $this->questions())) {
            throw new RuntimeException('Select and lock a paper set before submitting the Admission Test.');
        }
        $now = gmdate('Y-m-d H:i:s');

        $this->archiveSubmission($db, $current, $id, $now);
        $db->table('admission_test_responses')->where('admission_test_id', $id)->delete();

        foreach ($this->questionsForTest($current) as $page => $pageQuestions) {
            foreach ($pageQuestions as $index => $question) {
                $answer = trim((string) ($input['answer_' . $page . '_' . ($index + 1)] ?? ''));
                $db->table('admission_test_responses')->insert([
                    'admission_test_id' => $id,
                    'variant' => $page,
                    'question_number' => $index + 1,
                    'prompt' => $question['prompt'],
                    'options_json' => json_encode($question['options'], JSON_THROW_ON_ERROR),
                    'answer' => $answer === '' ? null : $answer,
                    'marks' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        $nextVersion = (int) $current['version'] + 1;
        $updated = $db->table('admission_tests')
            ->where('id', $id)
            ->where('version', (int) $current['version'])
            ->update(array_merge($personal, [
                'test_variant' => $variant,
                'status' => 'TEST_SUBMITTED',
                'evaluator_user_id' => $actor,
                'submitted_at' => $now,
                'version' => $nextVersion,
                'updated_by' => $actor,
                'updated_at' => $now,
            ]));
        if ($updated !== true || $db->affectedRows() !== 1) {
            throw new RuntimeException('Update conflict.');
        }

        if ($studentLinkId !== null) {
            $consumed = $db->table('admission_test_student_links')
                ->where('id', $studentLinkId)
                ->where('used_at IS NULL', null, false)
                ->where('revoked_at IS NULL', null, false)
                ->update(['used_at' => $now]);
            if ($consumed !== true || $db->affectedRows() !== 1) {
                throw new RuntimeException('Student test link unavailable or expired.');
            }
        }

        service('auditPersistence')->record(
            $actor,
            $studentLinkId === null ? 'admission_test_submitted' : 'admission_test_student_submitted',
            'admission_test',
            (string) $id,
            (int) $current['branch_id'],
            $this->auditSummary($current),
            array_filter([
                'variant' => $variant,
                'version' => $nextVersion,
                'expires_at' => $studentLinkExpiresAt,
            ], static fn (mixed $value): bool => $value !== null),
            null,
            $db,
        );
    }

    public function savePersonalDetails(int $id, array $input): void
    {
        $db = Database::connect('default');
        $current = $this->locked($db, $id);
        $actor = $this->authorize((int) $current['branch_id'], 'admission.update');
        $this->assertAllowedStatus((string) $current['status'], [
            'DRAFT',
            'BASIC_DETAILS_COMPLETED',
            'TEST_IN_PROGRESS',
            'TEST_SUBMITTED',
            'MEETING_FOLLOW_UP',
            'APPROVED_FOR_ADMISSION',
        ]);
        $this->checkVersion($current, $input);
        $data = $this->personalFields($input);
        if (! in_array((string) $current['status'], ['TEST_SUBMITTED', 'MEETING_FOLLOW_UP', 'APPROVED_FOR_ADMISSION'], true)) {
            $data['status'] = 'BASIC_DETAILS_COMPLETED';
        }
        $this->saveVersioned($db, $id, $current, $data, $actor, 'admission_test_personal_details_updated');
    }

    public function saveFees(int $id, array $input): void
    {
        $db = Database::connect('default');
        $current = $this->locked($db, $id);
        $actor = $this->authorize((int) $current['branch_id'], 'admission.update');
        $this->assertAllowedStatus((string) $current['status'], ['TEST_SUBMITTED', 'MEETING_FOLLOW_UP', 'APPROVED_FOR_ADMISSION']);
        $this->checkVersion($current, $input);

        $fees = $this->feeFields($input);
        $ignoredCoachingIds = [];
        $ignoredHostelIds = [];
        $coachingRows = $this->normalizeFeeRows($input, 'installment_', $ignoredCoachingIds);
        $hostelRows = $this->normalizeFeeRows($input, 'hostel_installment_', $ignoredHostelIds);
        $removedCoachingIds = array_values(array_unique(array_merge(
            $this->normalizedRemovedInstallmentIds($input, 'removed_installment_ids'),
            $ignoredCoachingIds,
        )));
        $removedHostelIds = array_values(array_unique(array_merge(
            $this->normalizedRemovedInstallmentIds($input, 'removed_hostel_installment_ids'),
            $ignoredHostelIds,
        )));
        $reason = trim((string) ($input['fee_update_reason'] ?? ''));
        if ($reason === '' || strlen($reason) > 500) {
            throw new RuntimeException('A fee update reason is required.');
        }

        $db->transStart();
        try {
            $beforeAudit = $this->feeAuditSummary($current, $db, $id);
            $nextVersion = (int) $current['version'] + 1;
            $db->table('admission_tests')
                ->where('id', $id)
                ->where('version', (int) $current['version'])
                ->update(array_merge($fees, [
                    'version' => $nextVersion,
                    'updated_by' => $actor,
                    'updated_at' => gmdate('Y-m-d H:i:s'),
                ]));
            if ($db->affectedRows() !== 1) {
                throw new RuntimeException('Update conflict.');
            }

            $this->syncFeeRows(
                $db,
                $id,
                (int) $current['branch_id'],
                $coachingRows,
                $removedCoachingIds,
                'coaching',
                $actor,
                $fees['installment_plan'] !== 'Instalment Plan',
            );
            $this->syncFeeRows(
                $db,
                $id,
                (int) $current['branch_id'],
                $hostelRows,
                $removedHostelIds,
                'hostel',
                $actor,
                $fees['hostel_installment_plan'] !== 'Instalment Plan',
            );
            $afterAudit = $this->feeAuditSummary(
                array_merge($current, $fees, ['version' => $nextVersion]),
                $db,
                $id,
            );
            $afterAudit['removed_installment_ids'] = [
                'coaching' => $removedCoachingIds,
                'hostel' => $removedHostelIds,
            ];
            service('auditPersistence')->record(
                $actor,
                'admission_test_fees_updated',
                'admission_test',
                (string) $id,
                (int) $current['branch_id'],
                $beforeAudit,
                $afterAudit,
                $reason,
                $db,
            );
            $db->transComplete();
            $this->assertTransaction($db, 'Unable to save Admission Test fees.');
        } catch (Throwable $exception) {
            $db->transRollback();
            $db->resetTransStatus();
            throw $exception;
        }
    }

    private function feeFields(array $input): array
    {
        $fields = [];
        foreach (['training_fee', 'hostel_charges'] as $field) {
            $value = trim((string) ($input[$field] ?? ''));
            if ($value !== '' && preg_match('/^\d+(?:\.\d{1,2})?$/', $value) !== 1) {
                throw new RuntimeException('Invalid fee amount.');
            }
            $fields[$field] = $value === '' ? null : number_format((float) $value, 2, '.', '');
        }
        foreach (['installment_plan', 'hostel_installment_plan'] as $field) {
            $value = trim((string) ($input[$field] ?? ''));
            if ($value !== '' && ! in_array($value, self::PAYMENT_PLAN_OPTIONS, true)) {
                throw new RuntimeException('Invalid payment plan.');
            }
            $fields[$field] = $value === '' ? null : $value;
        }
        return $fields;
    }

    private function normalizeFeeRows(array $input, string $prefix, array &$ignoredIds = []): array
    {
        $planField = $prefix === 'installment_' ? 'installment_plan' : 'hostel_installment_plan';
        if (($input[$planField] ?? '') !== 'Instalment Plan') {
            return [];
        }
        $years = is_array($input[$prefix . 'year'] ?? null) ? $input[$prefix . 'year'] : [];
        $rows = [];
        $seenYears = [];
        $seenIds = [];
        $idsField = $prefix === 'installment_'
            ? 'coaching_installment_ids'
            : 'hostel_installment_ids';
        $legacyIdsField = $prefix . 'ids';
        $submittedIds = is_array($input[$idsField] ?? null)
            ? $input[$idsField]
            : ($input[$legacyIdsField] ?? []);
        foreach ($years as $index => $year) {
            $row = [
                'installment_ids' => $this->decodeInstallmentIds($submittedIds[$index] ?? null),
            ];
            foreach ($row['installment_ids'] as $installmentId) {
                if (isset($seenIds[$installmentId])) {
                    throw new RuntimeException('Duplicate installment identifier.');
                }
                $seenIds[$installmentId] = true;
            }
            $values = [];
            $hasValue = false;
            for ($number = 1; $number <= 4; $number++) {
                $dateValues = is_array($input[$prefix . $number . '_date'] ?? null)
                    ? $input[$prefix . $number . '_date']
                    : [];
                $amountValues = is_array($input[$prefix . $number] ?? null)
                    ? $input[$prefix . $number]
                    : [];
                $date = trim((string) ($dateValues[$index] ?? ''));
                $amount = trim((string) ($amountValues[$index] ?? ''));
                $values[$number] = [$date, $amount];
                $hasValue = $hasValue || $date !== '' || $amount !== '';
            }
            if (! $hasValue) {
                $ignoredIds = array_merge($ignoredIds, array_values($row['installment_ids']));
                continue;
            }

            $yearNumber = (int) $year;
            if ($yearNumber < 1 || $yearNumber > 99) {
                throw new RuntimeException('Invalid installment year.');
            }
            if (isset($seenYears[$yearNumber])) {
                throw new RuntimeException('Duplicate installment row.');
            }
            $seenYears[$yearNumber] = true;
            $row['year_number'] = $yearNumber;
            for ($number = 1; $number <= 4; $number++) {
                [$date, $amount] = $values[$number];
                if ($date !== '' && (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1
                    || ! checkdate((int) substr($date, 5, 2), (int) substr($date, 8, 2), (int) substr($date, 0, 4)))) {
                    throw new RuntimeException('Invalid installment due date.');
                }
                if ($amount !== '' && preg_match('/^\d+(?:\.\d{1,2})?$/', $amount) !== 1) {
                    throw new RuntimeException('Invalid installment amount.');
                }
                $row['installment_' . $number . '_date'] = $date === '' ? null : $date;
                $row['installment_' . $number] = $amount === ''
                    ? null
                    : number_format((float) $amount, 2, '.', '');
            }
            $rows[] = $row;
        }
        return $rows;
    }

    /** @return list<int> */
    private function normalizedRemovedInstallmentIds(array $input, string $field): array
    {
        $values = is_array($input[$field] ?? null) ? $input[$field] : [];
        $ids = [];
        foreach ($values as $value) {
            $installmentId = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($installmentId === false || in_array((int) $installmentId, $ids, true)) {
                throw new RuntimeException('Invalid removed installment identifier.');
            }
            $ids[] = (int) $installmentId;
        }
        return $ids;
    }

    /** @return list<int> */
    private function decodeInstallmentIds(mixed $value): array
    {
        if ($value === null || trim((string) $value) === '') {
            return [];
        }
        $decoded = json_decode((string) $value, true);
        if (! is_array($decoded)) {
            throw new RuntimeException('Invalid installment identifier.');
        }
        $ids = [];
        $keys = array_keys($decoded);
        $isSequential = $keys === range(0, count($decoded) - 1);
        foreach ($decoded as $key => $rawId) {
            $installmentId = filter_var($rawId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $slot = $isSequential ? count($ids) + 1 : (int) $key;
            if ($installmentId === false || $slot < 1 || $slot > 4 || isset($ids[$slot])) {
                throw new RuntimeException('Invalid installment identifier.');
            }
            $ids[$slot] = (int) $installmentId;
        }
        return $ids;
    }

    private function syncFeeRows(
        object $db,
        int $id,
        int $branch,
        array $rows,
        array $removedIds,
        string $schedule,
        int $actor,
        bool $clearWhenNotRequired,
    ): void
    {
        $table = self::FEE_SCHEDULE_TABLES[$schedule];
        $now = gmdate('Y-m-d H:i:s');
        if (($clearWhenNotRequired || $rows === []) && $removedIds === []) {
            $existingRows = $db->table($table)
                ->select('id')
                ->where('admission_test_id', $id)
                ->where('branch_id', $branch)
                ->get()
                ->getResultArray();
            foreach ($existingRows as $existingRow) {
                $removedIds[] = (int) $existingRow['id'];
            }
        }
        $referencedIds = $removedIds;
        foreach ($rows as $row) {
            foreach ($row['installment_ids'] as $installmentId) {
                if (! in_array($installmentId, $referencedIds, true)) {
                    $referencedIds[] = $installmentId;
                }
            }
        }
        $existingRows = $this->installmentRowsById($db, $table, $id, $branch, $referencedIds);
        $usedIds = $removedIds;
        foreach ($rows as $row) {
            for ($number = 1; $number <= 4; $number++) {
                $installmentId = (int) ($row['installment_ids'][$number] ?? 0);
                $date = $row['installment_' . $number . '_date'] ?? null;
                $amount = $row['installment_' . $number] ?? null;
                if ($date === null && $amount === null) {
                    if ($installmentId > 0 && ! in_array($installmentId, $removedIds, true)) {
                        $removedIds[] = $installmentId;
                    }
                    continue;
                }
                if ($installmentId > 0) {
                    if (in_array($installmentId, $usedIds, true) || ! isset($existingRows[$installmentId])) {
                        throw new RuntimeException('Invalid installment identifier.');
                    }
                    $usedIds[] = $installmentId;
                    $db->table($table)
                        ->where('id', $installmentId)
                        ->where('admission_test_id', $id)
                        ->where('branch_id', $branch)
                        ->update([
                            'year_number' => $row['year_number'],
                            'year_label' => 'Year ' . $row['year_number'],
                            'installment_number' => $number,
                            'due_date' => $date,
                            'amount' => $amount,
                            'updated_at' => $now,
                        ]);
                    continue;
                }
                if (! $db->table($table)->insert([
                    'admission_test_id' => $id,
                    'branch_id' => $branch,
                    'year_number' => $row['year_number'],
                    'year_label' => 'Year ' . $row['year_number'],
                    'installment_number' => $number,
                    'due_date' => $date,
                    'amount' => $amount,
                    'created_by' => $actor,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])) {
                    throw new RuntimeException('Unable to save Admission Test installment schedule.');
                }
            }
        }
        if ($removedIds !== []) {
            $db->table($table)
                ->where('admission_test_id', $id)
                ->where('branch_id', $branch)
                ->whereIn('id', $removedIds)
                ->delete();
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function installmentRowsById(object $db, string $table, int $id, int $branch, array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $rows = $db->table($table)
            ->where('admission_test_id', $id)
            ->where('branch_id', $branch)
            ->whereIn('id', $ids)
            ->get()
            ->getResultArray();
        if (count($rows) !== count($ids)) {
            throw new RuntimeException('Invalid installment identifier.');
        }
        $indexed = [];
        foreach ($rows as $row) {
            $indexed[(int) $row['id']] = $row;
        }
        return $indexed;
    }

    private function loadFeeRows(object $db, int $id, string $schedule, int $branch): array
    {
        $table = self::FEE_SCHEDULE_TABLES[$schedule];
        if (! $db->tableExists($table)) {
            return [];
        }
        $sourceRows = $db->table($table)
            ->where(['admission_test_id' => $id, 'branch_id' => $branch])
            ->orderBy('year_number')
            ->orderBy('installment_number')
            ->get()
            ->getResultArray();
        $rows = [];
        foreach ($sourceRows as $sourceRow) {
            $year = (int) $sourceRow['year_number'];
            $rows[$year] ??= [
                'year_number' => $year,
                'installment_ids' => [],
            ];
            $number = (int) $sourceRow['installment_number'];
            $rows[$year]['installment_ids'][$number] = (int) $sourceRow['id'];
            $rows[$year]['installment_' . $number . '_date'] = $sourceRow['due_date'];
            $rows[$year]['installment_' . $number] = $sourceRow['amount'];
        }
        return array_values($rows);
    }

    private function feeAuditSummary(array $test, object $db, int $id): array
    {
        return [
            'training_fee' => $test['training_fee'] ?? null,
            'installment_plan' => $test['installment_plan'] ?? null,
            'hostel_charges' => $test['hostel_charges'] ?? null,
            'hostel_installment_plan' => $test['hostel_installment_plan'] ?? null,
            'coaching_installment_rows' => $this->loadFeeRows($db, $id, 'coaching', (int) $test['branch_id']),
            'hostel_installment_rows' => $this->loadFeeRows($db, $id, 'hostel', (int) $test['branch_id']),
        ];
    }

    public function calculateFeeTotal(array $record): string
    {
        return number_format(
            (float) ($record['training_fee'] ?? 0) + (float) ($record['hostel_charges'] ?? 0),
            2,
            '.',
            '',
        );
    }

    public function saveBasicTest(int $id, array $input): void
    {
        $db = Database::connect('default');
        $current = $this->locked($db, $id);
        $actor = $this->authorize((int) $current['branch_id'], 'admission.update');
        if (in_array((string) $current['status'], ['TEST_SUBMITTED', 'MEETING_FOLLOW_UP', 'APPROVED_FOR_ADMISSION'], true)) {
            throw new RuntimeException('Submitted Admission Test answers are read-only.');
        }
        $this->assertAllowedStatus((string) $current['status'], ['BASIC_DETAILS_COMPLETED', 'TEST_IN_PROGRESS']);
        $this->checkVersion($current, $input);
        $variant = strtoupper(trim((string) ($current['test_variant'] ?? '')));
        $questions = $this->questions()[$variant] ?? null;
        if (! is_array($questions)) {
            throw new RuntimeException('Select and lock a paper set before submitting the Admission Test.');
        }
        if (array_key_exists('variant', $input) && strtoupper(trim((string) $input['variant'])) !== $variant) {
            throw new RuntimeException('The locked paper set cannot be changed.');
        }

        $now = gmdate('Y-m-d H:i:s');
        $db->transStart();
        try {
            $this->archiveSubmission($db, $current, $id, $now);
            $responseModel = new AdmissionTestResponseModel();
            $responseModel->where('admission_test_id', $id)->delete();
            foreach ($this->questionsForTest($current) as $pageVariant => $pageQuestions) {
                foreach ($pageQuestions as $index => $question) {
                    $newField = 'answer_' . $pageVariant . '_' . ($index + 1);
                    $legacyField = 'answer_' . ($index + 1);
                    $rawAnswer = array_key_exists($newField, $input)
                        ? $input[$newField]
                        : ($pageVariant === $variant ? ($input[$legacyField] ?? '') : '');
                    $answer = trim((string) $rawAnswer);
                    if ($answer !== '' && ! in_array($answer, $question['options'], true)) {
                        throw new RuntimeException('Invalid answer for page ' . $pageVariant . ', question ' . ($index + 1) . '.');
                    }
                    $responseModel->insert([
                        'admission_test_id' => $id,
                        'variant' => $pageVariant,
                        'question_number' => $index + 1,
                        'prompt' => $question['prompt'],
                        'options_json' => json_encode($question['options'], JSON_THROW_ON_ERROR),
                        'answer' => $answer === '' ? null : $answer,
                        'marks' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }

            $nextVersion = (int) $current['version'] + 1;
            $db->table('admission_tests')->where('id', $id)->update([
                'test_variant' => $variant,
                'status' => 'TEST_SUBMITTED',
                'evaluator_user_id' => $actor,
                'submitted_at' => $now,
                'version' => $nextVersion,
                'updated_by' => $actor,
                'updated_at' => $now,
            ]);
            service('auditPersistence')->record($actor, 'admission_test_submitted', 'admission_test', (string) $id, (int) $current['branch_id'], $this->auditSummary($current), ['variant' => $variant, 'version' => $nextVersion], null, $db);
            $db->transComplete();
            $this->assertTransaction($db, 'Unable to submit Admission Test.');
        } catch (Throwable $exception) {
            $db->transRollback();
            $db->resetTransStatus();
            throw $exception;
        }
    }

    public function startBasicTest(int $id, array $input): void
    {
        $db = Database::connect('default');
        $current = $this->locked($db, $id);
        $actor = $this->authorize((int) $current['branch_id'], 'admission.update');
        $this->assertAllowedStatus((string) $current['status'], ['DRAFT', 'BASIC_DETAILS_COMPLETED']);
        $this->checkVersion($current, $input);
        if (! array_key_exists(strtoupper(trim((string) ($current['test_variant'] ?? ''))), $this->questions())) {
            throw new RuntimeException('Select and lock a paper set before starting the Admission Test.');
        }
        $this->saveVersioned(
            $db,
            $id,
            $current,
            ['status' => 'TEST_IN_PROGRESS'],
            $actor,
            'admission_test_started',
        );
    }

    public function createFollowUp(int $id, array $input): int
    {
        $db = Database::connect('default');
        $test = $this->locked($db, $id);
        $actor = $this->authorize((int) $test['branch_id'], 'admission.update');
        $type = strtolower(trim((string) ($input['follow_up_type'] ?? '')));
        $status = strtolower(trim((string) ($input['status'] ?? 'scheduled')));
        $this->assertAllowedStatus((string) $test['status'], ['TEST_SUBMITTED', 'MEETING_FOLLOW_UP', 'APPROVED_FOR_ADMISSION']);
        $at = $this->normalizeFollowUpAt(
            trim((string) ($input['follow_up_date'] ?? '')),
            trim((string) ($input['follow_up_time'] ?? '')),
        );
        if (! in_array($type, self::FOLLOW_UP_TYPES, true) || ! in_array($status, self::FOLLOW_UP_STATUSES, true) || trim((string) ($input['notes'] ?? '')) === '') {
            throw new RuntimeException('Invalid Admission Test follow-up.');
        }
        $assigned = trim((string) ($input['assigned_user_id'] ?? ''));
        $assignedUserId = $assigned === '' ? null : (int) $assigned;
        if ($assigned !== '' && ! ctype_digit($assigned)) {
            throw new RuntimeException('Invalid assigned user.');
        }
        if ($assignedUserId !== null) {
            $assignedUser = $db->table('user_branches')
                ->where(['user_id' => $assignedUserId, 'branch_id' => (int) $test['branch_id']])
                ->get()
                ->getRowArray();
            if (! is_array($assignedUser)) {
                throw new RuntimeException('Invalid assigned user.');
            }
        }
        $now = gmdate('Y-m-d H:i:s');
        $db->transStart();
        try {
            $duplicate = $db->table('admission_test_followups')
                ->where([
                    'admission_test_id' => $id,
                    'trial_id' => (int) $test['trial_id'],
                    'branch_id' => (int) $test['branch_id'],
                    'follow_up_at' => $at,
                    'follow_up_type' => $type,
                    'status' => $status,
                    'notes' => trim((string) $input['notes']),
                ])
                ->where('assigned_user_id', $assignedUserId)
                ->where('outcome', trim((string) ($input['outcome'] ?? '')) ?: null)
                ->where('next_action', trim((string) ($input['next_action'] ?? '')) ?: null)
                ->orderBy('id', 'ASC')
                ->get()
                ->getRowArray();
            if (is_array($duplicate)) {
                $db->transComplete();
                $this->assertTransaction($db, 'Unable to create Admission Test follow-up.');

                return (int) $duplicate['id'];
            }

            $model = new AdmissionTestFollowUpModel();
            $model->insert([
                'admission_test_id' => $id,
                'trial_id' => (int) $test['trial_id'],
                'branch_id' => (int) $test['branch_id'],
                'follow_up_at' => $at,
                'follow_up_type' => $type,
                'status' => $status,
                'assigned_user_id' => $assignedUserId,
                'notes' => trim((string) $input['notes']),
                'outcome' => trim((string) ($input['outcome'] ?? '')) ?: null,
                'next_action' => trim((string) ($input['next_action'] ?? '')) ?: null,
                'created_by' => $actor,
                'updated_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $followUpId = (int) $model->getInsertID();
            if ($followUpId < 1) {
                throw new RuntimeException('Unable to create Admission Test follow-up.');
            }
            service('auditPersistence')->record($actor, 'admission_test_follow_up_created', 'admission_test_follow_up', (string) $followUpId, (int) $test['branch_id'], null, ['admission_test_id' => $id, 'trial_id' => (int) $test['trial_id']], null, $db);
            $db->transComplete();
            $this->assertTransaction($db, 'Unable to create Admission Test follow-up.');
        } catch (Throwable $exception) {
            $db->transRollback();
            $db->resetTransStatus();
            throw $exception;
        }

        return $followUpId;
    }

    public function normalizeFollowUpAt(string $date, string $time): string
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
            throw new RuntimeException('Invalid follow-up date.');
        }

        $timezone = new DateTimeZone((string) (config('App')->appTimezone ?: 'Asia/Kolkata'));
        $dateValue = DateTimeImmutable::createFromFormat('!Y-m-d', $date, $timezone);
        $dateErrors = DateTimeImmutable::getLastErrors();
        if ($dateValue === false || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0)) || $dateValue->format('Y-m-d') !== $date) {
            throw new RuntimeException('Invalid follow-up date.');
        }

        $time = strtoupper(trim($time));
        $isNativeTime = preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/', $time) === 1;
        $timeValue = $isNativeTime
            ? DateTimeImmutable::createFromFormat('!H:i', $time, $timezone)
            : DateTimeImmutable::createFromFormat('!h:i A', $time, $timezone);
        $timeErrors = DateTimeImmutable::getLastErrors();
        $validLegacyTime = preg_match('/^(0[1-9]|1[0-2]):[0-5][0-9] (AM|PM)$/', $time) === 1;
        if ($timeValue === false || ($timeErrors !== false && ($timeErrors['warning_count'] > 0 || $timeErrors['error_count'] > 0)) || (! $isNativeTime && ! $validLegacyTime)) {
            throw new RuntimeException('Invalid follow-up time.');
        }

        $local = $dateValue->setTime(
            (int) $timeValue->format('H'),
            (int) $timeValue->format('i'),
            0,
        );

        return $local->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    public function createPendingAdmission(int $id, array $input): int
    {
        $db = Database::connect('default');
        $test = $this->locked($db, $id);
        $actor = $this->authorize((int) $test['branch_id'], 'admission.create');
        $existingAdmission = $db->table('admissions')
            ->where('admission_test_id', $id)
            ->get()
            ->getRowArray();
        if (is_array($existingAdmission)) {
            return (int) $existingAdmission['id'];
        }
        $this->checkVersion($test, $input);
        if (($input['agreement_confirmed'] ?? '') !== '1') {
            throw new RuntimeException('Parent/student agreement is required.');
        }
        if ((string) $test['status'] !== 'APPROVED_FOR_ADMISSION') {
            throw new RuntimeException('Admission Test is not ready for Pending Admission.');
        }
        $db->transStart();
        try {
            $admissionId = service('admission')->createFromTrialPending((int) $test['trial_id'], (int) $test['lead_id'], (int) $test['branch_id'], $db);
            $now = gmdate('Y-m-d H:i:s');
            $db->table('admissions')->where('id', $admissionId)->update([
                'admission_test_id' => $id,
                'applicant_full_name' => $test['applicant_full_name'],
                'applicant_email' => $test['applicant_email'],
                'applicant_phone' => $test['applicant_phone'],
                'applicant_date_of_birth' => $test['applicant_date_of_birth'],
                'father_name' => $test['father_name'],
                'father_profession' => $test['father_profession'],
                'age' => $test['age'],
                'residence' => $test['residence'],
                'parent_mobile' => $test['contact_number'],
                'secondary_contact_number' => $test['secondary_contact_number'],
                'parent_email' => $test['email'],
                'secondary_email' => $test['secondary_email'],
                'represented_as' => $test['represented_as'],
                'joining_date' => $this->normalizeDateOrNull($test['coaching_start']),
                'coaching_type' => $test['coaching_type'],
                'playing_role' => $test['playing_role'],
                'nearest_branch' => $test['nearest_branch'],
                'discovery_source' => $test['discovery_source'],
                'reference_details' => $test['reference_details'],
                'achievements' => $test['achievements'],
                'training_fee' => $test['training_fee'],
                'installment_plan' => $test['installment_plan'],
                'hostel_charges' => $test['hostel_charges'],
                'hostel_installment_plan' => $test['hostel_installment_plan'],
            'updated_at' => $now,
            ]);
            $this->copyFeeSchedulesToAdmission($db, $id, $admissionId, (int) $test['branch_id'], $actor);
            $nextVersion = (int) $test['version'] + 1;
            $updated = $db->table('admission_tests')
                ->where('id', $id)
                ->where('branch_id', (int) $test['branch_id'])
                ->where('version', (int) $test['version'])
                ->update([
                    'status' => 'APPROVED_FOR_ADMISSION',
                    'agreement_confirmed_at' => $now,
                    'agreement_confirmed_by' => $actor,
                    'version' => $nextVersion,
                    'updated_by' => $actor,
                    'updated_at' => $now,
                ]);
            if ($updated !== true || $db->affectedRows() !== 1) {
                throw new RuntimeException('Update conflict.');
            }
            service('auditPersistence')->record($actor, 'admission_test_pending_admission_created', 'admission_test', (string) $id, (int) $test['branch_id'], null, ['admission_id' => $admissionId, 'version' => $nextVersion], null, $db);
            $db->transComplete();
            $this->assertTransaction($db, 'Unable to create Pending Admission.');
        } catch (Throwable $exception) {
            $db->transRollback();
            $db->resetTransStatus();
            throw $exception;
        }

        return $admissionId;
    }

    public function statuses(): array
    {
        return self::STATUSES;
    }

    /** @return list<string> */
    public function managedStatuses(): array
    {
        return self::MANAGED_STATUSES;
    }

    /** @return array{status: string, version: int} */
    public function changeStatus(int $id, array $input): array
    {
        $db = Database::connect('default');
        $db->transStart();

        try {
            $current = $db->query(
                'SELECT * FROM admission_tests WHERE id = ? LIMIT 1 FOR UPDATE',
                [$id],
            )->getRowArray();
            if (! is_array($current)) {
                throw new RuntimeException('Not found.');
            }

            $actor = $this->authorize((int) $current['branch_id'], 'admission.update');
            $this->checkVersion($current, $input);

            $oldStatus = strtoupper(trim((string) ($current['status'] ?? '')));
            $newStatus = strtoupper(trim((string) ($input['status'] ?? '')));
            if (! in_array($newStatus, self::MANAGED_STATUSES, true)) {
                throw new RuntimeException('Select a valid Admission Test status.');
            }
            if (! in_array($oldStatus, ['TEST_SUBMITTED', 'MEETING_FOLLOW_UP', ...self::MANAGED_STATUSES], true)) {
                throw new RuntimeException('Invalid Admission Test status transition.');
            }
            if ($oldStatus === $newStatus) {
                throw new RuntimeException('Admission Test is already in that status.');
            }

            $reason = trim((string) ($input['reason'] ?? ''));
            if (in_array($newStatus, ['REJECTED', 'ON_HOLD'], true) && $reason === '') {
                throw new RuntimeException('A reason is required for rejected or on-hold Admission Tests.');
            }
            if (strlen($reason) > 255) {
                throw new RuntimeException('The status reason must not exceed 255 characters.');
            }

            $nextVersion = (int) $current['version'] + 1;
            $now = gmdate('Y-m-d H:i:s');
            $updated = $db->table('admission_tests')
                ->where('id', $id)
                ->where('branch_id', (int) $current['branch_id'])
                ->where('version', (int) $current['version'])
                ->update([
                    'status' => $newStatus,
                    'version' => $nextVersion,
                    'updated_by' => $actor,
                    'updated_at' => $now,
                ]);
            if ($updated !== true || $db->affectedRows() !== 1) {
                throw new RuntimeException('Update conflict.');
            }

            service('auditPersistence')->record(
                $actor,
                'admission_test_status_changed',
                'admission_test',
                (string) $id,
                (int) $current['branch_id'],
                ['status' => $oldStatus, 'version' => (int) $current['version']],
                ['status' => $newStatus, 'version' => $nextVersion],
                $reason !== '' ? $reason : null,
                $db,
            );

            $db->transComplete();
            $this->assertTransaction($db, 'Unable to change the Admission Test status.');

            return [
                'status' => $newStatus,
                'version' => $nextVersion,
            ];
        } catch (Throwable $exception) {
            $db->transRollback();
            $db->resetTransStatus();
            throw $exception;
        }
    }

    /** @return array<string, mixed> */
    public function listForAuthorizedBranches(
        array $filters = [],
        int $page = 1,
        int $perPage = 20
    ): array
    {
        $branches = service('branchContext')->authorizedBranchIds(true);
        $filters = $this->normalizeListFilters($filters, $branches);
        $perPage = max(1, $perPage);

        if ($branches === []) {
            return [
                'tests' => [],
                'total' => 0,
                'page' => 1,
                'pages' => 1,
                'filters' => $filters,
                'branches' => [],
                'statuses' => self::STATUSES,
                'paperSets' => $this->paperSetOptions(),
                'statusCounts' => $this->emptyListStatusCounts(),
            ];
        }

        $total = (int) $this->admissionTestListQuery($branches, $filters, false)
            ->countAllResults();
        $pages = max(1, (int) ceil($total / $perPage));
        $page = $this->safeListPage($page, $pages);
        $tests = $this->admissionTestListQuery($branches, $filters, true)
            ->orderBy('at.id', 'DESC')
            ->limit($perPage, ($page - 1) * $perPage)
            ->get()
            ->getResultArray();

        return [
            'tests' => $tests,
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'perPage' => $perPage,
            'filters' => $filters,
            'branches' => $this->listBranchOptions($branches),
            'statuses' => self::STATUSES,
            'paperSets' => $this->paperSetOptions(),
            'statusCounts' => $this->admissionTestStatusCounts($branches, $filters),
        ];
    }

    /** @return array<string, int> */
    private function emptyListStatusCounts(): array
    {
        return [
            'all' => 0,
            'test_submitted' => 0,
            'approved_for_admission' => 0,
            'rejected' => 0,
            'on_hold' => 0,
        ];
    }

    private function safeListPage(int $page, int $pages): int
    {
        return min(max(1, $page), max(1, $pages));
    }

    /** @param list<int> $branches */
    private function listBranchOptions(array $branches): array
    {
        return Database::connect('default')->table('branches')
            ->select('id, name')
            ->whereIn('id', $branches)
            ->where('is_active', 1)
            ->orderBy('name', 'ASC')
            ->get()
            ->getResultArray();
    }

    /** @param list<int> $branches */
    private function admissionTestListQuery(array $branches, array $filters, bool $selectRows): object
    {
        $query = Database::connect('default')->table('admission_tests at')
            ->join('branches b', 'b.id = at.branch_id')
            ->join('trials t', 't.id = at.trial_id')
            ->join('admissions a', 'a.admission_test_id = at.id', 'left')
            ->whereIn('at.branch_id', $branches)
            ->where('t.branch_id = at.branch_id', null, false);

        if ($selectRows) {
            $query->select('at.id, at.trial_id, at.branch_id, at.applicant_full_name, at.status, at.test_variant, at.version, at.created_at, at.updated_at, b.name AS branch_name, t.trial_date, t.trial_time, a.id AS admission_id');
        }

        if (($filters['branch_id'] ?? 0) > 0) {
            $query->where('at.branch_id', (int) $filters['branch_id']);
        }
        if (($filters['test_reference'] ?? 0) > 0) {
            $query->where('at.id', (int) $filters['test_reference']);
        }
        if (($filters['trial_reference'] ?? 0) > 0) {
            $query->where('at.trial_id', (int) $filters['trial_reference']);
        }
        if (($filters['applicant'] ?? '') !== '') {
            $query->like('at.applicant_full_name', $filters['applicant']);
        }
        if (($filters['status'] ?? '') !== '') {
            $query->where('at.status', $filters['status']);
        }
        if (($filters['paper_set'] ?? '') !== '') {
            $query->where('at.test_variant', $filters['paper_set']);
        }
        if (($filters['created_from'] ?? '') !== '') {
            $query->where('at.created_at >=', $filters['created_from'] . ' 00:00:00');
        }
        if (($filters['created_to'] ?? '') !== '') {
            $query->where('at.created_at <', date('Y-m-d', strtotime($filters['created_to'] . ' +1 day')) . ' 00:00:00');
        }
        if (($filters['updated_from'] ?? '') !== '') {
            $query->where('at.updated_at >=', $filters['updated_from'] . ' 00:00:00');
        }
        if (($filters['updated_to'] ?? '') !== '') {
            $query->where('at.updated_at <', date('Y-m-d', strtotime($filters['updated_to'] . ' +1 day')) . ' 00:00:00');
        }

        return $query;
    }

    /** @param list<int> $branches */
    private function admissionTestStatusCounts(array $branches, array $filters): array
    {
        $rows = $this->admissionTestListQuery($branches, $filters, true)
            ->select('at.status, a.id AS admission_id', false)
            ->get()
            ->getResultArray();
        $counts = $this->emptyListStatusCounts();

        foreach ($rows as $row) {
            $status = strtoupper((string) ($row['status'] ?? ''));
            $counts['all']++;
            $countKey = match ($status) {
                'TEST_SUBMITTED' => 'test_submitted',
                'APPROVED_FOR_ADMISSION' => 'approved_for_admission',
                'REJECTED', 'REJECTED_CLOSED' => 'rejected',
                'ON_HOLD' => 'on_hold',
                default => null,
            };
            if ($countKey !== null) {
                $counts[$countKey]++;
            }
        }

        return $counts;
    }

    /** @param list<int> $branches */
    private function normalizeListFilters(array $filters, array $branches): array
    {
        $normalized = [];
        foreach (['test_reference', 'trial_reference', 'branch_id'] as $field) {
            $value = $filters[$field] ?? '';
            if (ctype_digit((string) $value) && (int) $value > 0) {
                $normalized[$field] = (int) $value;
            }
        }
        if (($normalized['branch_id'] ?? 0) > 0 && ! in_array($normalized['branch_id'], $branches, true)) {
            $normalized['branch_id'] = 0;
        }
        $applicant = trim((string) ($filters['applicant'] ?? ''));
        if ($applicant !== '') {
            $normalized['applicant'] = mb_substr($applicant, 0, 100);
        }
        if (in_array(($filters['status'] ?? ''), self::STATUSES, true)) {
            $normalized['status'] = (string) $filters['status'];
        }
        $paperSet = strtoupper(trim((string) ($filters['paper_set'] ?? '')));
        if (in_array($paperSet, ['A', 'B', 'C'], true)) {
            $normalized['paper_set'] = $paperSet;
        }
        foreach (['created_from', 'created_to', 'updated_from', 'updated_to'] as $field) {
            $date = trim((string) ($filters[$field] ?? ''));
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1) {
                $normalized[$field] = $date;
            }
        }

        return $normalized;
    }

    /** @return list<array<string, mixed>> */
    public function assignableUsers(): array
    {
        $branches = service('branchContext')->authorizedBranchIds(true);
        if ($branches === []) {
            return [];
        }

        return Database::connect('default')->table('users u')
            ->distinct()
            ->select('u.id, u.display_name, u.email')
            ->join('user_branches ub', 'ub.user_id = u.id')
            ->whereIn('ub.branch_id', $branches)
            ->where('u.is_enabled', 1)
            ->orderBy('u.display_name')
            ->get()
            ->getResultArray();
    }

    public function followUpOptions(): array
    {
        return ['types' => self::FOLLOW_UP_TYPES, 'statuses' => self::FOLLOW_UP_STATUSES];
    }

    /** @return array<string, mixed> */
    private function linkedLead(object $db, array $record): ?array
    {
        $leadId = (int) ($record['lead_id'] ?? 0);
        $branchId = (int) ($record['branch_id'] ?? 0);
        if ($leadId < 1) {
            return null;
        }

        $lead = $db->table('leads')
            ->where('id', $leadId)
            ->get()
            ->getRowArray();

        if (! is_array($lead) || (int) ($lead['branch_id'] ?? 0) !== $branchId) {
            throw new RuntimeException('Invalid Trial source.');
        }

        return $lead;
    }

    /** @return array<string, mixed>|null */
    private function linkedAdmission(object $db, array $record, ?array $lead): ?array
    {
        $trialId = (int) ($record['trial_id'] ?? 0);
        $leadId = (int) ($lead['id'] ?? 0);
        $admission = $db->table('admissions')
            ->groupStart()
            ->where('trial_id', $trialId)
            ->orWhere('lead_id', $leadId)
            ->groupEnd()
            ->orderBy('id', 'DESC')
            ->get()
            ->getRowArray();

        if (! is_array($admission)) {
            return null;
        }

        if ((int) ($admission['branch_id'] ?? 0) !== (int) ($record['branch_id'] ?? 0)) {
            throw new RuntimeException('Admission branch mismatch.');
        }

        return $admission;
    }

    /**
     * @param array<string, mixed> $lead
     * @param array<string, mixed>|null $admission
     * @return array{values: array<string, mixed>, sources: array<string, string>}
     */
    private function sourcePrefill(?array $lead, ?array $admission): array
    {
        $values = [];
        $sources = [];

        foreach (self::PREFILL_MAPPINGS as $target => $mapping) {
            $leadField = $mapping['lead'];
            if ($lead !== null && $leadField !== '' && ! $this->isBlank($lead[$leadField] ?? null)) {
                $values[$target] = $lead[$leadField];
                $sources[$target] = 'Lead';
                continue;
            }

            $admissionField = $mapping['admission'];
            if ($admission !== null && ! $this->isBlank($admission[$admissionField] ?? null)) {
                $values[$target] = $admission[$admissionField];
                $sources[$target] = 'Admission';
            }
        }

        return ['values' => $values, 'sources' => $sources];
    }

    private function isBlank(mixed $value): bool
    {
        return $value === null || trim((string) $value) === '';
    }

    private function personalFields(array $input): array
    {
        $fields = [
            'applicant_full_name' => trim((string) ($input['applicant_full_name'] ?? '')),
            'applicant_email' => trim((string) ($input['applicant_email'] ?? $input['email'] ?? '')) ?: null,
            'applicant_phone' => trim((string) ($input['applicant_phone'] ?? $input['contact_number'] ?? '')) ?: null,
            'applicant_date_of_birth' => trim((string) ($input['applicant_date_of_birth'] ?? '')) ?: null,
            'father_name' => trim((string) ($input['father_name'] ?? '')) ?: null,
            'father_profession' => trim((string) ($input['father_profession'] ?? '')) ?: null,
            'age' => $this->nullableInt($input['age'] ?? null, 0, 120),
            'residence' => trim((string) ($input['residence'] ?? '')) ?: null,
            'contact_number' => trim((string) ($input['contact_number'] ?? '')) ?: null,
            'secondary_contact_number' => trim((string) ($input['secondary_contact_number'] ?? '')) ?: null,
            'email' => trim((string) ($input['email'] ?? '')) ?: null,
            'secondary_email' => trim((string) ($input['secondary_email'] ?? '')) ?: null,
            'represented_as' => trim((string) ($input['represented_as'] ?? '')) ?: null,
            'coaching_start' => trim((string) ($input['coaching_start'] ?? '')) ?: null,
            'coaching_type' => trim((string) ($input['coaching_type'] ?? '')) ?: null,
            'playing_role' => trim((string) ($input['playing_role'] ?? '')) ?: null,
            'nearest_branch' => trim((string) ($input['nearest_branch'] ?? '')) ?: null,
            'discovery_source' => trim((string) ($input['discovery_source'] ?? '')) ?: null,
            'reference_details' => trim((string) ($input['reference_details'] ?? '')) ?: null,
            'achievements' => trim((string) ($input['achievements'] ?? '')) ?: null,
        ];
        if ($fields['applicant_full_name'] === '' || strlen($fields['applicant_full_name']) > 150) {
            throw new RuntimeException('Applicant name is required.');
        }
        if ($fields['represented_as'] !== null && ! in_array($fields['represented_as'], self::REPRESENTED, true)) {
            throw new RuntimeException('Invalid Represented value.');
        }
        if ($fields['coaching_type'] !== null && ! in_array($fields['coaching_type'], self::COACHING_TYPES, true)) {
            throw new RuntimeException('Invalid coaching type.');
        }
        if ($fields['playing_role'] !== null && ! in_array($fields['playing_role'], self::SKILLS, true)) {
            throw new RuntimeException('Invalid Cricket skill / Ability value.');
        }
        if ($fields['nearest_branch'] !== null && ! in_array($fields['nearest_branch'], self::BRANCHES, true)) {
            throw new RuntimeException('Invalid nearest branch.');
        }
        foreach (['applicant_email', 'email', 'secondary_email'] as $email) {
            if ($fields[$email] !== null && filter_var($fields[$email], FILTER_VALIDATE_EMAIL) === false) {
                throw new RuntimeException('Invalid email address.');
            }
        }

        if ($fields['applicant_date_of_birth'] !== null) {
            $dateOfBirth = $fields['applicant_date_of_birth'];
            $format = preg_match('/^\d{2}-\d{2}-\d{4}$/', $dateOfBirth) === 1
                ? '!d-m-Y'
                : (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateOfBirth) === 1 ? '!Y-m-d' : null);
            $parsedDate = $format === null
                ? false
                : DateTimeImmutable::createFromFormat($format, $dateOfBirth);
            $expectedFormat = $format === '!d-m-Y' ? 'd-m-Y' : 'Y-m-d';

            if ($parsedDate === false || $parsedDate->format($expectedFormat) !== $dateOfBirth) {
                throw new RuntimeException('Invalid date of birth.');
            }

            $fields['applicant_date_of_birth'] = $parsedDate->format('Y-m-d');
        }

        return $fields;
    }

    /** @return array<string, int|null> */
    private function archiveSubmission(object $db, array $current, int $id, string $now): void
    {
        if ((string) ($current['status'] ?? '') !== 'TEST_SUBMITTED' && empty($current['submitted_at'])) {
            return;
        }
        $responses = (new AdmissionTestResponseModel())
            ->where('admission_test_id', $id)
            ->orderBy('question_number')
            ->findAll();
        if ($responses === []) {
            return;
        }
        $db->table('admission_test_submission_history')->insert([
            'admission_test_id' => $id,
            'version' => (int) $current['version'],
            'variant' => (string) ($current['test_variant'] ?? ''),
            'responses_json' => json_encode($responses, JSON_THROW_ON_ERROR),
            'assessment_json' => json_encode([], JSON_THROW_ON_ERROR),
            'score' => $current['score'] ?? null,
            'result' => $current['result'] ?? null,
            'evaluator_user_id' => $current['evaluator_user_id'] ?? null,
            'submitted_at' => $current['submitted_at'] ?? null,
            'created_at' => $now,
        ]);
    }

    private function trial(object $db, int $id, bool $lock): array
    {
        $sql = 'SELECT * FROM trials WHERE id = ? LIMIT 1' . ($lock ? ' FOR UPDATE' : '');
        $row = $db->query($sql, [$id])->getRowArray();
        if (! is_array($row)) {
            throw new RuntimeException('Not found.');
        }
        $this->authorize((int) $row['branch_id'], 'admission.create');

        return $row;
    }

    private function locked(object $db, int $id): array
    {
        $row = $db->table('admission_tests')->where('id', $id)->get()->getRowArray();
        if (! is_array($row)) {
            throw new RuntimeException('Not found.');
        }
        return $row;
    }

    /** @return array<string, mixed> */
    private function studentLink(object $db, string $token, bool $lock): array
    {
        $token = trim($token);
        if (preg_match('/^[a-f0-9]{64}$/', $token) !== 1) {
            $this->recordInvalidStudentLink($db, null, null);
            throw new RuntimeException('Student test link unavailable or expired.');
        }

        $sql = 'SELECT stl.*, at.status, at.submitted_at, at.branch_id AS test_branch_id '
            . 'FROM admission_test_student_links stl '
            . 'INNER JOIN admission_tests at ON at.id = stl.admission_test_id '
            . 'WHERE stl.token_hash = ? LIMIT 1';
        if ($lock) {
            $sql .= ' FOR UPDATE';
        }
        $link = $db->query($sql, [hash('sha256', $token)])->getRowArray();
        $expired = is_array($link) && $this->studentLinkExpired($link);
        if (! is_array($link)
            || $link['used_at'] !== null
            || $link['revoked_at'] !== null
            || $expired
            || $this->isCompleted($link)) {
            $this->recordInvalidStudentLink(
                $db,
                is_array($link) ? (int) $link['id'] : null,
                is_array($link) ? (int) ($link['test_branch_id'] ?? $link['branch_id'] ?? 0) : null,
                $expired ? 'expired' : 'unavailable',
                $expired ? ['expires_at' => $this->studentLinkExpiryValue($link)] : null,
            );
            throw new RuntimeException('Student test link unavailable or expired.');
        }

        if ((int) ($link['test_branch_id'] ?? 0) !== (int) ($link['branch_id'] ?? 0)) {
            $this->recordInvalidStudentLink($db, (int) $link['id'], (int) $link['branch_id']);
            throw new RuntimeException('Student test link unavailable or expired.');
        }

        return $link;
    }

    private function recordInvalidStudentLink(
        object $db,
        ?int $linkId,
        ?int $branch,
        string $reason = 'unavailable',
        ?array $metadata = null,
    ): void
    {
        service('auditPersistence')->record(
            null,
            'admission_test_student_link_invalid',
            'admission_test_student_link',
            $linkId === null ? 'unknown' : (string) $linkId,
            $branch,
            null,
            array_merge(['available' => false, 'reason' => $reason], $metadata ?? []),
            null,
            $db,
        );
    }

    private function studentLinkExpiry(string $createdAt): string
    {
        return (new DateTimeImmutable($createdAt, new DateTimeZone('UTC')))
            ->add(new DateInterval('PT30M'))
            ->format('Y-m-d H:i:s');
    }

    private function studentLinkExpiryValue(array $link): string
    {
        $expiresAt = trim((string) ($link['expires_at'] ?? ''));
        if ($expiresAt !== '') {
            return $expiresAt;
        }

        return $this->studentLinkExpiry((string) ($link['created_at'] ?? ''));
    }

    private function studentLinkExpired(array $link): bool
    {
        return gmdate('Y-m-d H:i:s') >= $this->studentLinkExpiryValue($link);
    }

    private function authorize(int $branch, string $permission): int
    {
        if ($branch < 1 || ! service('authorization')->can($permission, (string) $branch)) {
            throw new RuntimeException('Forbidden');
        }
        $user = session('user');
        if (! is_array($user) || (int) ($user['id'] ?? 0) < 1) {
            throw new RuntimeException('Authentication required');
        }
        return (int) $user['id'];
    }

    private function checkVersion(array $current, array $input): void
    {
        $expected = trim((string) ($input['version'] ?? ''));
        if ($expected !== '' && (int) $expected !== (int) $current['version']) {
            throw new RuntimeException('Update conflict.');
        }
    }

    private function assertAllowedStatus(string $status, array $allowed): void
    {
        if (! in_array($status, $allowed, true)) {
            throw new RuntimeException('Invalid Admission Test status transition.');
        }
    }

    private function saveVersioned(object $db, int $id, array $current, array $data, int $actor, string $auditAction): void
    {
        $now = gmdate('Y-m-d H:i:s');
        $data['version'] = (int) $current['version'] + 1;
        $data['updated_by'] = $actor;
        $data['updated_at'] = $now;
        $db->transStart();
        try {
            $db->table('admission_tests')->where('id', $id)->where('version', (int) $current['version'])->update($data);
            if ($db->affectedRows() !== 1) {
                throw new RuntimeException('Update conflict.');
            }
            service('auditPersistence')->record($actor, $auditAction, 'admission_test', (string) $id, (int) $current['branch_id'], $this->auditSummary($current), $this->auditSummary($data), null, $db);
            $db->transComplete();
            $this->assertTransaction($db, 'Unable to update Admission Test.');
        } catch (Throwable $exception) {
            $db->transRollback();
            $db->resetTransStatus();
            throw $exception;
        }
    }

    private function auditSummary(array $data): array
    {
        return [
            'status' => $data['status'] ?? null,
            'version' => $data['version'] ?? null,
            'trial_id' => $data['trial_id'] ?? null,
        ];
    }

    private function nullableInt(mixed $value, int $min, int $max): ?int
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }
        if (! ctype_digit((string) $value) || (int) $value < $min || (int) $value > $max) {
            throw new RuntimeException('Invalid age.');
        }
        return (int) $value;
    }

    private function normalizeDateOrNull(?string $value): ?string
    {
        if ($value === null || $value === '' || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
            return $value ?: null;
        }
        return null;
    }

    private function assertTransaction(object $db, string $message): void
    {
        if (! $db->transStatus()) {
            $db->resetTransStatus();
            throw new RuntimeException($message);
        }
    }

    private function copyFeeSchedulesToAdmission(object $db, int $testId, int $admissionId, int $branch, int $actor): void
    {
        $period = $db->table('admission_periods')
            ->where(['admission_id' => $admissionId, 'status' => 'current'])
            ->orderBy('period_number', 'DESC')
            ->get()
            ->getRowArray();
        $periodId = is_array($period) ? (int) $period['id'] : null;
        $now = gmdate('Y-m-d H:i:s');

        foreach (self::FEE_SCHEDULE_TABLES as $schedule => $testTable) {
            $admissionTable = $schedule === 'coaching'
                ? 'admission_coaching_installments'
                : 'admission_hostel_installments';
            $rows = $db->table($testTable)
                ->where(['admission_test_id' => $testId, 'branch_id' => $branch])
                ->orderBy('year_number')
                ->orderBy('installment_number')
                ->get()
                ->getResultArray();
            foreach ($rows as $row) {
                if (! $db->table($admissionTable)->insert([
                    'admission_id' => $admissionId,
                    'admission_period_id' => $periodId,
                    'year_number' => $row['year_number'],
                    'year_label' => $row['year_label'],
                    'installment_number' => $row['installment_number'],
                    'due_date' => $row['due_date'],
                    'amount' => $row['amount'],
                    'created_by' => $actor,
                    'version' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])) {
                    throw new RuntimeException('Unable to copy Admission Test fee schedule.');
                }
            }
        }
    }
}
