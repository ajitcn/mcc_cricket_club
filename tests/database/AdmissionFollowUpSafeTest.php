<?php

declare(strict_types=1);

namespace Tests\Database;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Database;
use RuntimeException;
use Throwable;

final class AdmissionFollowUpSafeTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $DBGroup = 'tests';
    protected $migrate = false;

    private int $admission;
    private int $branch;
    private int $otherBranch;
    private int $actor;

    protected function setUp(): void
    {
        parent::setUp();
        $branch = $this->db->table('branches')->where('code', 'TEST_MCC_VNS')->get()->getRowArray();
        $otherBranch = $this->db->table('branches')->where('code', 'TEST_MCC_DEL')->get()->getRowArray();
        $admission = $this->db->table('admissions')->where('branch_id', (int) ($branch['id'] ?? 0))->orderBy('id')->get()->getRowArray();
        $actor = $this->db->table('users')->where('email', 'test.mcc.manager.vns@example.invalid')->get()->getRowArray();
        self::assertIsArray($branch);
        self::assertIsArray($otherBranch);
        self::assertIsArray($admission);
        self::assertIsArray($actor);
        $this->branch = (int) $branch['id'];
        $this->otherBranch = (int) $otherBranch['id'];
        $this->admission = (int) $admission['id'];
        $this->actor = (int) $actor['id'];
        $this->authorize(['admission.view', 'admission.create', 'admission.update'], [$this->branch]);
    }

    public function testMultipleFollowUpsArePreservedAndOrderedOnAdmissionHistory(): void
    {
        $service = service('admissionFollowUp', false);
        $marker = bin2hex(random_bytes(3));
        $first = $service->create($this->input('01-01-2026', '09:00', $marker . '_first'));
        $second = $service->create($this->input('02-01-2026', '09:00', $marker . '_second'));

        self::assertCount(2, $this->db->table('admission_followups')->like('notes', $marker)->get()->getResultArray());
        $history = $service->forAdmission($this->admission);
        $history = array_values(array_filter($history, static fn (array $row): bool => str_contains((string) $row['notes'], $marker)));
        self::assertSame($second, (int) $history[0]['id']);
        self::assertSame($first, (int) $history[1]['id']);
    }

    public function testBrowserDatetimePayloadIsAcceptedAndNullableNextDateRemainsNull(): void
    {
        $id = service('admissionFollowUp', false)->create([
            'admission_id' => (string) $this->admission,
            'follow_up_at' => '2026-01-01 09:00:00',
            'follow_up_time' => '09:00',
            'follow_up_type' => 'call',
            'status' => 'pending',
            'notes' => 'TEST_MCC_BROWSER_DATETIME',
            'next_follow_up_at' => '',
            'next_follow_up_time' => '',
            'assigned_user_id' => '',
        ]);

        $row = $this->db->table('admission_followups')->where('id', $id)->get()->getRowArray();

        self::assertSame('2026-01-01 09:00:00', $row['follow_up_at']);
        self::assertSame('scheduled', $row['status']);
        self::assertNull($row['next_follow_up_at']);
    }

    public function testCrossBranchAdmissionAndUnauthorizedUserAreRejected(): void
    {
        $otherAdmission = $this->db->table('admissions')->where('branch_id', $this->otherBranch)->orderBy('id')->get()->getRowArray();
        self::assertIsArray($otherAdmission);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Forbidden');
        service('admissionFollowUp', false)->create($this->input('01-01-2026', '09:00', 'cross', (int) $otherAdmission['id']));
    }

    public function testInvalidControlledValuesAreRejectedWithoutWriting(): void
    {
        $service = service('admissionFollowUp', false);
        $before = $this->db->table('admission_followups')->countAllResults();

        try {
            $service->create($this->input('not-a-date', '09:00', 'invalid-date'));
            self::fail('An invalid date should be rejected.');
        } catch (RuntimeException $exception) {
            self::assertSame('Invalid follow-up date.', $exception->getMessage());
        }

        self::assertSame($before, $this->db->table('admission_followups')->countAllResults());
    }

    public function testNewFollowUpAlwaysStoresScheduledForForgedStatuses(): void
    {
        $service = service('admissionFollowUp', false);
        $marker = bin2hex(random_bytes(3));

        foreach (['pending', 'completed', 'cancelled', 'reschedule', 'unknown'] as $offset => $status) {
            $payload = $this->input(
                sprintf('%02d-02-2026', 11 + $offset),
                '09:00',
                $marker . '-' . $status,
            );
            $payload['status'] = $status;
            $id = $service->create($payload);
            $row = $this->db->table('admission_followups')->where('id', $id)->get()->getRowArray();

            self::assertIsArray($row);
            self::assertSame('scheduled', $row['status']);
        }
    }

    public function testRescheduleIsNotAnAdmissionFollowUpOptionOrAcceptedValue(): void
    {
        $options = service('admissionFollowUp', false)->options();
        self::assertNotContains('reschedule', $options['types']);
        self::assertNotContains('reschedule', $options['statuses']);

        $before = $this->db->table('admission_followups')->countAllResults();
        $payload = $this->input('01-01-2026', '09:00', 'reschedule-rejected');
        $payload['follow_up_type'] = 'reschedule';

        try {
            service('admissionFollowUp', false)->create($payload);
            self::fail('Reschedule must not be accepted as a follow-up type.');
        } catch (RuntimeException $exception) {
            self::assertSame('Invalid Admission follow-up.', $exception->getMessage());
        }

        self::assertSame($before, $this->db->table('admission_followups')->countAllResults());
    }

    public function testMissingCsrfTokenRejectsBrowserCreate(): void
    {
        $before = $this->db->table('admission_followups')->countAllResults();
        try {
            $response = $this->withSession(session()->get())->post(
                'admissions/followups',
                $this->input('01-01-2026', '09:00', 'missing-csrf')
            );
            self::assertSame(403, $response->response()->getStatusCode());
        } catch (Throwable $exception) {
            self::assertStringContainsString('not allowed', $exception->getMessage());
        }

        self::assertSame($before, $this->db->table('admission_followups')->countAllResults());
    }

    public function testDatabaseFailureRollsBackFollowUpWrite(): void
    {
        $service = service('admissionFollowUp', false);
        $before = $this->db->table('admission_followups')->countAllResults();
        session()->set('user', ['id' => 999999999]);
        \Config\Services::resetSingle('authorization');

        try {
            $service->create($this->input('01-01-2026', '09:00', 'rollback'));
            self::fail('The invalid actor foreign key should fail the transaction.');
        } catch (Throwable $exception) {
            self::assertNotSame('', $exception->getMessage());
        } finally {
            $this->db->resetTransStatus();
            \Config\Database::connect('default')->resetTransStatus();
            $this->authorize(['admission.view', 'admission.create', 'admission.update'], [$this->branch]);
        }

        self::assertSame($before, $this->db->table('admission_followups')->countAllResults());
    }

    public function testEditUpdatesOneRecordWithoutRemovingHistory(): void
    {
        $service = service('admissionFollowUp', false);
        $marker = bin2hex(random_bytes(3));
        $beforeCount = $this->db->table('admission_followups')->where('admission_id', $this->admission)->countAllResults();
        $id = $service->create($this->input('01-01-2026', '09:00', $marker . '_before'));
        $row = $this->db->table('admission_followups')->where('id', $id)->get()->getRowArray();
        $service->update($id, $this->input('01-01-2026', '10:00', 'after') + ['updated_at' => $row['updated_at']]);

        self::assertSame('TEST_MCC_after', $this->db->table('admission_followups')->where('id', $id)->get()->getRow('notes'));
        self::assertSame($beforeCount + 1, $this->db->table('admission_followups')->where('admission_id', $this->admission)->countAllResults());
    }

    public function testBrowserEditSaveSupportsUncheckedAndCheckedScheduling(): void
    {
        $service = service('admissionFollowUp', false);
        $marker = bin2hex(random_bytes(3));
        $id = $service->create($this->input('03-01-2026', '09:00', $marker . '-browser-edit'));
        $row = $this->db->table('admission_followups')->where('id', $id)->get()->getRowArray();
        self::assertIsArray($row);

        $uncheckedNotes = $marker . '-browser-edit-no-next';
        $unchecked = $this->input('03-01-2026', '10:00', $uncheckedNotes);
        $unchecked['updated_at'] = $row['updated_at'];
        $unchecked[csrf_token()] = csrf_hash();
        $response = $this->withSession(session()->get())->post(
            'admissions/followups/' . $id . '/edit',
            $unchecked,
        );
        self::assertSame(302, $response->response()->getStatusCode());
        self::assertSame(
            1,
            $this->db->table('admission_followups')
                ->where('admission_id', $this->admission)
                ->where('notes', 'TEST_MCC_' . $uncheckedNotes)
                ->countAllResults(),
        );

        $updated = $this->db->table('admission_followups')->where('id', $id)->get()->getRowArray();
        self::assertIsArray($updated);
        $checkedNotes = $marker . '-browser-edit-with-next';
        $checked = $this->input('03-01-2026', '10:00', $checkedNotes);
        $checked['updated_at'] = $updated['updated_at'];
        $checked['schedule_next_follow_up'] = '1';
        $checked['next_follow_up_at'] = '25-01-2026';
        $checked['next_follow_up_time'] = '11:30';
        $checked[csrf_token()] = csrf_hash();
        $scheduledBefore = $this->db->table('admission_followups')
            ->where('admission_id', $this->admission)
            ->where('follow_up_at', '2026-01-25 11:30:00')
            ->where('notes', 'TEST_MCC_' . $checkedNotes)
            ->countAllResults();
        $response = $this->withSession(session()->get())->post(
            'admissions/followups/' . $id . '/edit',
            $checked,
        );
        self::assertSame(302, $response->response()->getStatusCode());
        self::assertStringContainsString(
            '/admissions/followups/',
            (string) $response->response()->getHeaderLine('Location'),
        );
        self::assertSame(
            $scheduledBefore + 1,
            $this->db->table('admission_followups')
                ->where('admission_id', $this->admission)
                ->where('follow_up_at', '2026-01-25 11:30:00')
                ->where('notes', 'TEST_MCC_' . $checkedNotes)
                ->countAllResults(),
        );
    }

    public function testEditScheduleRollsBackWhenAuditActorIsInvalid(): void
    {
        $service = service('admissionFollowUp', false);
        $id = $service->create($this->input('04-01-2026', '09:00', 'rollback-schedule'));
        $row = $this->db->table('admission_followups')->where('id', $id)->get()->getRowArray();
        self::assertIsArray($row);
        $before = $this->db->table('admission_followups')->where('id', $id)->get()->getRowArray();

        session()->set('user', ['id' => 999999999]);
        \Config\Services::resetSingle('authorization');

        $input = $this->input('04-01-2026', '09:00', 'rollback-schedule-updated');
        $input['updated_at'] = $row['updated_at'];
        $input['schedule_next_follow_up'] = '1';
        $input['next_follow_up_at'] = '26-01-2026';
        $input['next_follow_up_time'] = '09:30';

        try {
            $service->update($id, $input);
            self::fail('The invalid audit actor should roll back the edit and next record.');
        } catch (Throwable $exception) {
            self::assertNotSame('', $exception->getMessage());
        } finally {
            $this->db->resetTransStatus();
            \Config\Database::connect('default')->resetTransStatus();
            $this->authorize(['admission.view', 'admission.create', 'admission.update'], [$this->branch]);
        }

        self::assertSame(
            $before['notes'],
            $this->db->table('admission_followups')->where('id', $id)->get()->getRow('notes'),
        );
        self::assertSame(
            0,
            $this->db->table('admission_followups')
                ->where('admission_id', $this->admission)
                ->where('follow_up_at', '2026-01-26 09:30:00')
                ->where('notes', 'TEST_MCC_rollback-schedule-updated')
                ->countAllResults(),
        );
    }

    public function testEditSaveCanScheduleASeparateHistoryRecordAndIsIdempotent(): void
    {
        $service = service('admissionFollowUp', false);
        $marker = bin2hex(random_bytes(3));
        $originalId = $service->create($this->input('10-01-2026', '09:00', $marker . '-schedule-source'));
        $original = $this->db->table('admission_followups')->where('id', $originalId)->get()->getRowArray();
        self::assertIsArray($original);

        $scheduleInput = $this->input('10-01-2026', '09:00', $marker . '-schedule-source-edited');
        $scheduleInput['next_follow_up_at'] = '20-01-2026';
        $scheduleInput['next_follow_up_time'] = '10:30';
        $scheduleInput['schedule_next_follow_up'] = '1';
        $scheduleInput['status'] = 'completed';
        $scheduleInput['updated_at'] = $original['updated_at'];

        $nextId = $service->update($originalId, $scheduleInput);
        $next = $this->db->table('admission_followups')->where('id', $nextId)->get()->getRowArray();
        $updated = $this->db->table('admission_followups')->where('id', $originalId)->get()->getRowArray();
        self::assertIsArray($next);
        self::assertIsArray($updated);
        self::assertNotSame($originalId, $nextId);
        self::assertSame('TEST_MCC_' . $marker . '-schedule-source-edited', $updated['notes']);
        self::assertSame($original['follow_up_at'], $updated['follow_up_at']);
        self::assertSame($original['next_follow_up_at'], $updated['next_follow_up_at']);
        self::assertSame($this->admission, (int) $next['admission_id']);
        self::assertSame($this->branch, (int) $next['branch_id']);
        self::assertSame('2026-01-20 10:30:00', $next['follow_up_at']);
        self::assertSame('scheduled', $next['status']);
        self::assertSame('TEST_MCC_' . $marker . '-schedule-source-edited', $next['notes']);
        self::assertNull($next['outcome']);

        $history = $service->forAdmission($this->admission);
        $history = array_values(array_filter(
            $history,
            static fn (array $row): bool => str_contains((string) $row['notes'], $marker),
        ));
        self::assertSame($nextId, (int) $history[0]['id']);
        $listRows = $service->paginated([
            'admission_id' => (string) $this->admission,
            'date_from' => '2026-01-01',
            'date_to' => '2026-01-31',
        ], 1, 100)['followUps'];
        self::assertContains($nextId, array_map(
            static fn (array $row): int => (int) $row['id'],
            $listRows,
        ));

        try {
            $service->update($originalId, $scheduleInput);
            self::fail('A repeated stale submission must be rejected.');
        } catch (RuntimeException $exception) {
            self::assertSame('Update conflict.', $exception->getMessage());
        }
        self::assertSame(1, $this->db->table('admission_followups')->where('id', $nextId)->countAllResults());
        $editForm = $this->withSession(session()->get())->get(
            'admissions/followups/' . $originalId . '/edit',
        );
        self::assertStringContainsString('Schedule Next Follow-up', $editForm->getBody());
        self::assertStringNotContainsString('value="schedule"', $editForm->getBody());
    }

    public function testScheduleNextAlwaysCreatesScheduledStatusForForgedLifecycleValues(): void
    {
        $service = service('admissionFollowUp', false);
        $marker = bin2hex(random_bytes(3));

        foreach (['pending', 'completed', 'cancelled', 'reschedule', 'unknown'] as $offset => $forgedStatus) {
            $source = $service->create($this->input(
                sprintf('%02d-02-2026', 1 + $offset),
                '09:00',
                $marker . '-source-' . $forgedStatus,
            ));
            $sourceRow = $this->db->table('admission_followups')->where('id', $source)->get()->getRowArray();
            self::assertIsArray($sourceRow);

            $payload = $this->input(
                sprintf('%02d-02-2026', 1 + $offset),
                '09:30',
                $marker . '-next-' . $forgedStatus,
            );
            $payload['status'] = $forgedStatus;
            $payload['schedule_next_follow_up'] = '1';
            $payload['next_follow_up_at'] = sprintf('%02d-02-2026', 10 + $offset);
            $payload['next_follow_up_time'] = '10:00';
            $payload['updated_at'] = $sourceRow['updated_at'];

            $nextId = $service->update($source, $payload);
            $next = $this->db->table('admission_followups')->where('id', $nextId)->get()->getRowArray();

            self::assertIsArray($next);
            self::assertSame('scheduled', $next['status']);
            self::assertSame('TEST_MCC_' . $marker . '-next-' . $forgedStatus, $next['notes']);
        }
    }

    public function testBrowserRoutesKeepFiltersAndProtectPermissions(): void
    {
        $list = $this->withSession(session()->get())->get('admissions/followups');
        self::assertSame(200, $list->response()->getStatusCode());
        self::assertStringContainsString('Admission Follow-ups', $list->getBody());
        self::assertStringContainsString('Add follow-up', $list->getBody());
        self::assertStringContainsString('class="mcc-follow-up-admission-cell"', $list->getBody());
        self::assertStringNotContainsString('<th>Branch</th>', $list->getBody());
        self::assertMatchesRegularExpression(
            '/mcc-follow-up-admission-cell.*Applicant.*#.*MCC Varanasi/s',
            $list->getBody(),
        );
        self::assertStringContainsString('data-follow-up-actions-toggle', $list->getBody());
        self::assertStringContainsString('data-follow-up-actions-menu', $list->getBody());
        self::assertStringNotContainsString('<th>Next follow-up</th>', $list->getBody());
        self::assertStringContainsString('mcc-follow-up-status-pending', $list->getBody());
        self::assertStringContainsString('mcc-follow-up-status-scheduled', $list->getBody());
        self::assertStringContainsString('status=scheduled', $list->getBody());
        self::assertStringContainsString('mcc-follow-up-status-completed', $list->getBody());
        self::assertStringContainsString('mcc-follow-up-status-cancelled', $list->getBody());
        self::assertStringNotContainsString('Track every Admission conversation and next action.', $list->getBody());
        self::assertSame(
            substr_count($list->getBody(), 'data-follow-up-actions-toggle'),
            substr_count($list->getBody(), 'data-follow-up-actions-menu role="menu"'),
        );
        self::assertStringNotContainsString('>View</a>', $list->getBody());
        self::assertStringNotContainsString('>Edit</a>', $list->getBody());
        $form = $this->withSession(session()->get())->get('admissions/followups/new');
        self::assertSame(200, $form->response()->getStatusCode());
        self::assertStringContainsString('name="admission_id"', $form->getBody());
        self::assertStringContainsString('Add Admission Follow-up', $form->getBody());
        self::assertStringContainsString('Save Follow-up', $form->getBody());
        self::assertStringNotContainsString('name="schedule_next_follow_up"', $form->getBody());
        self::assertStringNotContainsString('name="next_follow_up_at"', $form->getBody());
        self::assertStringNotContainsString('Reschedule', $form->getBody());
        self::assertStringNotContainsString('reschedule', $form->getBody());
        self::assertStringContainsString('Choose admission', $form->getBody());
        self::assertStringContainsString('Choose assigned user', $form->getBody());
        self::assertStringContainsString('value="Scheduled"', $form->getBody());
        self::assertMatchesRegularExpression(
            '/id="admission-follow-up-status"[^>]*readonly/s',
            $form->getBody(),
        );
        self::assertStringNotContainsString('Derived from Admission', $form->getBody());
        self::assertStringNotContainsString('>Not available</p>', $form->getBody());
        self::assertStringContainsString('mcc-form-actions', $form->getBody());
        self::assertSame(
            1,
            preg_match_all('/data-mcc-date-picker\s+data-mcc-date-picker-no-toggle/', $form->getBody()),
        );
        $followUpId = service('admissionFollowUp', false)->create(
            $this->input('01-01-2026', '09:00', 'TEST_MCC_FORM_EDIT')
        );
        $editForm = $this->withSession(session()->get())->get('admissions/followups/' . $followUpId . '/edit');
        self::assertSame(200, $editForm->response()->getStatusCode());
        self::assertStringContainsString('Edit Admission Follow-up', $editForm->getBody());
        self::assertStringContainsString('value="' . $this->admission . '"', $editForm->getBody());
        self::assertStringContainsString('value="01-01-2026"', $editForm->getBody());
        self::assertStringContainsString('name="schedule_next_follow_up"', $editForm->getBody());
        self::assertStringContainsString('data-follow-up-next-fields', $editForm->getBody());
        self::assertMatchesRegularExpression(
            '/data-follow-up-next-fields\s+hidden/',
            $editForm->getBody(),
        );
        self::assertStringNotContainsString('value="schedule"', $editForm->getBody());
        self::assertStringNotContainsString('Applicant name</span>', $editForm->getBody());
        self::assertStringNotContainsString('Admission number</span>', $editForm->getBody());
        self::assertStringNotContainsString('Derived from Admission', $editForm->getBody());
        self::assertStringContainsString('Save Follow-up', $editForm->getBody());
        self::assertSame(
            2,
            preg_match_all('/data-mcc-date-picker\s+data-mcc-date-picker-no-toggle/', $editForm->getBody()),
        );
        $filtered = $this->withSession(session()->get())->get('admissions/followups?branch_id=' . $this->branch . '&follow_up_type=call');
        self::assertStringContainsString('id="admission-follow-up-filters"', $filtered->getBody());
        self::assertStringContainsString('data-show-label="Show filters"', $filtered->getBody());
        self::assertStringContainsString('aria-label="2 active filters"', $filtered->getBody());
        self::assertStringContainsString('branch_id=' . $this->branch . '&amp;follow_up_type=call&amp;status=pending', $filtered->getBody());
        $searched = $this->withSession(session()->get())->get(
            'admissions/followups?applicant=TEST_MCC&status=pending&date_from=2026-01-01&date_to=2026-01-31'
        );
        self::assertStringContainsString('Search Applicant', $searched->getBody());
        self::assertStringContainsString('name="applicant"', $searched->getBody());
        self::assertStringContainsString('value="TEST_MCC"', $searched->getBody());
        self::assertStringContainsString(
            'applicant=TEST_MCC&amp;date_from=2026-01-01&amp;date_to=2026-01-31&amp;status=pending',
            $searched->getBody(),
        );
        $dateFiltered = $this->withSession(session()->get())->get(
            'admissions/followups?date_from=2026-01-01&date_to=2026-01-31&page=2'
        );
        self::assertStringContainsString('Follow-up Start Date', $dateFiltered->getBody());
        self::assertStringContainsString('Follow-up End Date', $dateFiltered->getBody());
        self::assertStringContainsString('value="01-01-2026"', $dateFiltered->getBody());
        self::assertStringContainsString('value="31-01-2026"', $dateFiltered->getBody());
        self::assertStringContainsString('date_from=2026-01-01&amp;date_to=2026-01-31&amp;page=2', $dateFiltered->getBody());
        $invalidRange = service('admissionFollowUp', false)->paginated([
            'date_from' => '2026-02-01',
            'date_to' => '2026-01-01',
        ]);
        self::assertSame([], $invalidRange['followUps']);
        self::assertArrayHasKey('date_from', $invalidRange['filterErrors']);
        self::assertArrayHasKey('date_to', $invalidRange['filterErrors']);
        session()->set('permissions', ['admission.view']);
        \Config\Services::resetSingle('authorization');
        $denied = $this->withSession(session()->get())->get('admissions/followups/new');
        self::assertSame(403, $denied->response()->getStatusCode());
    }

    public function testCompletedAdmissionsHideCreationActionsAndRejectDirectCreation(): void
    {
        $service = service('admissionFollowUp', false);
        $defaultDb = Database::connect('default');
        $original = $this->db->table('admissions')->where('id', $this->admission)->get()->getRowArray();
        self::assertIsArray($original);
        $existingId = $service->create($this->input('08-01-2026', '09:00', 'closed-history'));

        try {
            foreach (['APPROVED', 'CONVERTED'] as $status) {
                $defaultDb->table('admissions')->where('id', $this->admission)->update(['status' => $status]);

                $detail = $this->withSession(session()->get())->get('admissions/' . $this->admission . '/page');
                self::assertStringNotContainsString('admissions/followups/new?admission_id=', $detail->getBody());

                $followUpDetail = $this->withSession(session()->get())->get('admissions/followups/' . $existingId);
                self::assertStringNotContainsString('Add Another Follow-up', $followUpDetail->getBody());

                $edit = $this->withSession(session()->get())->get('admissions/followups/' . $existingId . '/edit');
                self::assertStringNotContainsString('Schedule Next Follow-up', $edit->getBody());

                $before = $this->db->table('admission_followups')->countAllResults();
                $response = $this->withSession(session()->get())->post(
                    'admissions/followups',
                    $this->input('09-01-2026', '09:00', 'blocked-' . $status)
                        + [csrf_token() => csrf_hash()],
                );
                self::assertSame(302, $response->response()->getStatusCode());
                self::assertSame($before, $this->db->table('admission_followups')->countAllResults());
                self::assertSame(
                    'No new follow-up can be created after the Admission is completed or converted.',
                    session()->getFlashdata('error'),
                );

                $existing = $this->db->table('admission_followups')->where('id', $existingId)->get()->getRowArray();
                self::assertIsArray($existing);
                $beforeSchedule = $this->db->table('admission_followups')->countAllResults();
                $schedulePayload = $this->input('08-01-2026', '09:00', 'blocked-schedule-' . $status);
                $schedulePayload['status'] = 'scheduled';
                $schedulePayload['updated_at'] = $existing['updated_at'];
                $schedulePayload['schedule_next_follow_up'] = '1';
                $schedulePayload['next_follow_up_at'] = '10-01-2026';
                $schedulePayload['next_follow_up_time'] = '09:00';
                $scheduleResponse = $this->withSession(session()->get())->post(
                    'admissions/followups/' . $existingId . '/edit',
                    $schedulePayload + [csrf_token() => csrf_hash()],
                );
                self::assertSame(302, $scheduleResponse->response()->getStatusCode());
                self::assertSame($beforeSchedule, $this->db->table('admission_followups')->countAllResults());
                self::assertSame(
                    'TEST_MCC_closed-history',
                    $this->db->table('admission_followups')->where('id', $existingId)->get()->getRow('notes'),
                );
            }

            $defaultDb->table('admissions')->where('id', $this->admission)->update(['status' => $original['status']]);
            $eligible = $this->withSession(session()->get())->get('admissions/' . $this->admission . '/page');
            self::assertStringContainsString('admissions/followups/new?admission_id=', $eligible->getBody());
        } finally {
            $defaultDb->table('admissions')->where('id', $this->admission)->update([
                'status' => $original['status'],
                'converted_player_id' => $original['converted_player_id'],
            ]);
            $defaultDb->table('admission_followups')->where('id', $existingId)->delete();
        }
    }

    public function testStoredUtcFollowUpTimestampRendersInMccLocalTwelveHourFormat(): void
    {
        $id = service('admissionFollowUp', false)->create(
            $this->input('01-01-2026', '18:30', 'utc-display-check'),
        );
        $row = $this->db->table('admission_followups')->where('id', $id)->get()->getRowArray();

        self::assertSame('2026-01-01 18:30:00', $row['follow_up_at']);

        $list = $this->withSession(session()->get())->get(
            'admissions/followups/' . $id,
        );

        self::assertSame(200, $list->response()->getStatusCode());
        self::assertStringContainsString('02-01-2026, 12:00 AM', $list->getBody());
    }

    public function testApplicantSearchIsPartialCaseInsensitiveAndBranchScoped(): void
    {
        $service = service('admissionFollowUp', false);
        $admission = $this->db->table('admissions')
            ->select('applicant_full_name')
            ->where('id', $this->admission)
            ->get()
            ->getRowArray();
        $otherAdmission = $this->db->table('admissions')
            ->select('id, applicant_full_name')
            ->where('branch_id', $this->otherBranch)
            ->orderBy('id')
            ->get()
            ->getRowArray();
        self::assertIsArray($admission);
        self::assertIsArray($otherAdmission);
        $otherOriginal = $this->db->table('admissions')
            ->where('id', (int) $otherAdmission['id'])
            ->get()
            ->getRowArray();
        self::assertIsArray($otherOriginal);

        $service->create($this->input('11-01-2026', '09:00', 'search-one'));
        $service->create($this->input('12-01-2026', '09:00', 'search-two'));
        $needle = strtolower(substr(trim((string) $admission['applicant_full_name']), 0, 4));
        $result = $service->paginated([
            'applicant' => $needle,
            'status' => 'scheduled',
            'follow_up_type' => 'call',
            'date_from' => '2026-01-01',
            'date_to' => '2026-01-31',
        ], 1, 100);
        self::assertGreaterThanOrEqual(2, count($result['followUps']));
        foreach ($result['followUps'] as $row) {
            self::assertStringContainsString($needle, strtolower((string) $row['applicant_name']));
        }

        self::assertSame([], $service->paginated(['applicant' => 'no-such-applicant-' . bin2hex(random_bytes(2))])['followUps']);

        $this->authorize(['admission.view', 'admission.create', 'admission.update'], [$this->branch, $this->otherBranch]);
        $originalOtherApplicant = $otherAdmission['applicant_full_name'];
        $crossBranchApplicant = 'Cross Branch Search ' . bin2hex(random_bytes(3));

        try {
            Database::connect('default')->table('admissions')
                ->where('id', (int) $otherAdmission['id'])
                ->update([
                    'status' => 'INTAKE',
                    'converted_player_id' => null,
                ]);
            Database::connect('default')->table('admissions')
                ->where('id', (int) $otherAdmission['id'])
                ->update(['applicant_full_name' => $crossBranchApplicant]);
            $service->create($this->input('13-01-2026', '09:00', 'cross-branch-search', (int) $otherAdmission['id']));
            $this->authorize(['admission.view', 'admission.create', 'admission.update'], [$this->branch]);
            $crossBranchResult = $service->paginated([
                'applicant' => strtolower($crossBranchApplicant),
            ]);
            self::assertSame([], $crossBranchResult['followUps']);
        } finally {
            Database::connect('default')->table('admissions')
                ->where('id', (int) $otherAdmission['id'])
                ->update([
                    'applicant_full_name' => $originalOtherApplicant,
                    'status' => $otherOriginal['status'],
                    'converted_player_id' => $otherOriginal['converted_player_id'],
                ]);
        }
    }

    public function testFollowUpDetailsRenderCompleteAuthorizedProjectionAndActions(): void
    {
        $marker = bin2hex(random_bytes(3));
        $admission = $this->db->table('admissions')
            ->select('admission_number, applicant_full_name')
            ->where('id', $this->admission)
            ->get()
            ->getRowArray();
        self::assertIsArray($admission);
        $originalNumber = $admission['admission_number'];
        $originalApplicant = $admission['applicant_full_name'];
        $this->db->table('admissions')->where('id', $this->admission)->update([
            'admission_number' => 'DETAIL-' . $marker,
            'applicant_full_name' => 'Detail Applicant ' . $marker,
        ]);

        try {
            $id = service('admissionFollowUp', false)->create(
                $this->input('01-01-2026', '09:00', 'TEST_MCC_DETAIL_' . $marker)
            );
            $detail = $this->withSession(session()->get())->get('admissions/followups/' . $id);
            $body = $detail->getBody();

            self::assertSame(200, $detail->response()->getStatusCode());
            self::assertStringContainsString('Follow-up Details', $body);
            self::assertStringContainsString('Admission information', $body);
            self::assertStringContainsString('Follow-up information', $body);
            self::assertStringContainsString('Detail Applicant ' . $marker, $body);
            self::assertStringContainsString('#DETAIL-' . $marker, $body);
            self::assertStringContainsString('MCC Varanasi', $body);
            self::assertStringContainsString('Pending', $body);
            self::assertStringContainsString('No outcome was recorded.', $body);
            self::assertStringContainsString('Edit Follow-up', $body);
            self::assertStringContainsString('View Admission', $body);
            self::assertStringContainsString('Add Another Follow-up', $body);
            self::assertStringContainsString('Back to Follow-ups', $body);
            self::assertStringContainsString('admission_number=', $body);
            self::assertStringNotContainsString('/admissions/' . $this->admission . '/page', $body);
        } finally {
            $this->db->table('admissions')->where('id', $this->admission)->update([
                'admission_number' => $originalNumber,
                'applicant_full_name' => $originalApplicant,
            ]);
        }
    }

    public function testListUsesCanonicalAdmissionNumberAndAdmissionsFilterLink(): void
    {
        $admission = $this->db->table('admissions')
            ->select('id, admission_number, applicant_full_name')
            ->where('id', $this->admission)
            ->get()
            ->getRowArray();
        self::assertIsArray($admission);

        $originalNumber = $admission['admission_number'];
        $originalApplicantName = $admission['applicant_full_name'];
        $generatedNumber = 'ADM-001';
        $generatedApplicantName = 'Test Applicant ' . bin2hex(random_bytes(2));
        $this->db->table('admissions')->where('id', $this->admission)->update([
            'admission_number' => $generatedNumber,
            'applicant_full_name' => $generatedApplicantName,
        ]);

        try {
            $list = $this->withSession(session()->get())->get('admissions/followups');
            $body = $list->getBody();
            self::assertStringContainsString('#' . $generatedNumber, $body);
            self::assertStringNotContainsString('#-', $body);
            self::assertStringContainsString($generatedApplicantName, $body);
            self::assertStringContainsString('MCC Varanasi', $body);
            self::assertSame(0, substr_count($body, 'mcc-follow-up-admission-cell">\n                                <a'));
            self::assertStringContainsString('View Admission', $body);
            self::assertStringContainsString(
                'admission_number=' . urlencode($generatedNumber) . '&amp;q=' . urlencode($generatedApplicantName),
                $body,
            );
            self::assertStringNotContainsString(
                'href="' . site_url('admissions/' . $this->admission) . '">View Admission',
                $body,
            );

        } finally {
            $this->db->table('admissions')->where('id', $this->admission)->update([
                'admission_number' => $originalNumber,
                'applicant_full_name' => $originalApplicantName,
            ]);
        }
    }

    public function testRajFollowUpUsesApplicantNumberAndBranchProjection(): void
    {
        $raj = $this->db->table('admissions a')
            ->select('a.id, a.admission_number, a.applicant_full_name')
            ->where('a.applicant_full_name', 'Raj')
            ->where('a.branch_id', $this->branch)
            ->where('a.id IN (SELECT admission_id FROM admission_followups)', null, false)
            ->orderBy('a.id', 'DESC')
            ->get()
            ->getRowArray();
        self::assertIsArray($raj);

        $originalNumber = $raj['admission_number'];
        $this->db->table('admissions')->where('id', (int) $raj['id'])->update([
            'admission_number' => 'RAJ-2345',
        ]);

        try {
            $list = $this->withSession(session()->get())->get('admissions/followups?admission_id=' . (int) $raj['id']);
            $body = $list->getBody();
            self::assertMatchesRegularExpression(
                '/<span class="mcc-follow-up-applicant-name">Raj<\/span>\s*<span>#RAJ-2345<\/span>\s*<span>MCC Varanasi<\/span>/s',
                $body,
            );
            self::assertDoesNotMatchRegularExpression('/<span class="mcc-follow-up-applicant-name">Raj<\/span>.*#Pending number/s', $body);
            self::assertDoesNotMatchRegularExpression('/<span class="mcc-follow-up-applicant-name">Raj<\/span>.*#-/s', $body);
            self::assertSame(
                ['RAJ-2345'],
                array_values(array_unique(array_map(
                    static fn (array $row): string => (string) $row['admission_number'],
                    service('admissionFollowUp', false)->paginated([
                        'admission_id' => (string) $raj['id'],
                    ], 1, 100)['followUps'],
                ))),
            );
        } finally {
            $this->db->table('admissions')->where('id', (int) $raj['id'])->update([
                'admission_number' => $originalNumber,
            ]);
        }
    }

    public function testCanonicalAdmissionNumberSurvivesPendingApprovedAndConvertedStatuses(): void
    {
        $admission = $this->db->table('admissions')
            ->select('admission_number, status')
            ->where('id', $this->admission)
            ->get()
            ->getRowArray();
        self::assertIsArray($admission);

        $originalNumber = $admission['admission_number'];
        $originalStatus = $admission['status'];
        $knownNumber = 'STATUS-' . bin2hex(random_bytes(3));
        $this->db->table('admissions')->where('id', $this->admission)->update([
            'admission_number' => $knownNumber,
        ]);

        try {
            foreach (['PENDING', 'APPROVED', 'CONVERTED'] as $status) {
                $this->db->table('admissions')->where('id', $this->admission)->update([
                    'status' => $status,
                ]);
                $rows = service('admissionFollowUp', false)->paginated([
                    'admission_id' => (string) $this->admission,
                ], 1, 100)['followUps'];
                self::assertNotEmpty($rows);
                self::assertSame($knownNumber, (string) $rows[0]['admission_number']);
            }
        } finally {
            $this->db->table('admissions')->where('id', $this->admission)->update([
                'admission_number' => $originalNumber,
                'status' => $originalStatus,
            ]);
        }
    }

    public function testMissingAdmissionNumberUsesExplicitIntegrityFallback(): void
    {
        $admission = $this->db->table('admissions')
            ->select('admission_number, applicant_full_name')
            ->where('id', $this->admission)
            ->get()
            ->getRowArray();
        self::assertIsArray($admission);

        $originalNumber = $admission['admission_number'];
        $this->db->table('admissions')->where('id', $this->admission)->update([
            'admission_number' => null,
        ]);

        try {
            $list = $this->withSession(session()->get())->get(
                'admissions/followups?admission_id=' . $this->admission,
            );
            $body = $list->getBody();
            self::assertStringContainsString('#Missing number', $body);
            self::assertStringNotContainsString('#Pending number', $body);
            self::assertStringContainsString((string) $admission['applicant_full_name'], $body);
        } finally {
            $this->db->table('admissions')->where('id', $this->admission)->update([
                'admission_number' => $originalNumber,
            ]);
        }
    }

    public function testSummaryCountsUseAuthorizedStatusAndOverdueRules(): void
    {
        $service = service('admissionFollowUp', false);
        $marker = bin2hex(random_bytes(3));
        $before = $service->summaryCounts(['admission_id' => (string) $this->admission]);
        $service->create($this->input('01-01-2026', '09:00', $marker . '_pending'));
        $service->create($this->input('02-01-2026', '09:00', $marker . '_scheduled', null, 'pending', '31-12-2099'));
        $service->create($this->input('03-01-2026', '09:00', $marker . '_overdue', null, 'pending', '01-01-2020'));
        $service->create($this->input('04-01-2026', '09:00', $marker . '_completed', null, 'completed'));
        $service->create($this->input('05-01-2026', '09:00', $marker . '_cancelled', null, 'cancelled'));

        $counts = $service->summaryCounts(['admission_id' => (string) $this->admission]);

        self::assertSame($before['all'] + 5, $counts['all']);
        self::assertSame($before['pending'], $counts['pending']);
        self::assertSame($before['scheduled'] + 5, $counts['scheduled']);
        self::assertSame($before['overdue'], $counts['overdue']);
        self::assertSame($before['completed'], $counts['completed']);
        self::assertSame($before['cancelled'], $counts['cancelled']);
        self::assertSame([], $service->paginated(['branch_id' => (string) $this->otherBranch])['followUps']);
    }

    public function testPaginatedFollowUpsUsesStablePagesAndPreservesFilters(): void
    {
        $service = service('admissionFollowUp', false);
        $created = [];
        $paginationYear = random_int(2050, 2090);
        $admission = $this->db->table('admissions')
            ->where('id', $this->admission)
            ->get()
            ->getRowArray();
        self::assertIsArray($admission);
        $applicant = trim((string) ($admission['applicant_full_name'] ?? ''));

        for ($day = 0; $day < 41; $day++) {
            $followUpDate = (new \DateTimeImmutable($paginationYear . '-01-01'))
                ->modify('+' . $day . ' days')
                ->format('d-m-Y');
            $payload = $this->input(
                $followUpDate,
                '09:00',
                'pagination_' . $day . '_' . bin2hex(random_bytes(3)),
            );
            $payload['assigned_user_id'] = (string) $this->actor;
            $created[] = $service->create($payload);
        }

        $filters = [
            'branch_id' => (string) $this->branch,
            'status' => 'scheduled',
            'follow_up_type' => 'call',
            'assigned_user_id' => (string) $this->actor,
            'date_from' => sprintf('%04d-01-01', $paginationYear),
            'date_to' => sprintf('%04d-02-10', $paginationYear),
        ];
        if ($applicant !== '') {
            $filters['applicant'] = substr($applicant, 0, 3);
        }

        $expected = array_reverse($created);
        $pageRows = [];
        for ($page = 1; $page <= 21; $page++) {
            $result = $service->paginated($filters, $page, 2);
            self::assertSame(41, $result['total']);
            self::assertSame(21, $result['pages']);
            self::assertSame($page, $result['page']);
            $pageRows = array_merge($pageRows, array_map(
                static fn (array $row): int => (int) $row['id'],
                $result['followUps'],
            ));
        }

        self::assertSame($expected, $pageRows);
        self::assertCount(41, array_unique($pageRows));
        $lastPage = $service->paginated($filters, 999, 2);
        self::assertSame(21, $lastPage['page']);
        self::assertSame([$expected[40]], array_map(
            static fn (array $row): int => (int) $row['id'],
            $lastPage['followUps'],
        ));
        $empty = $service->paginated([
            ...$filters,
            'applicant' => 'no-such-pagination-applicant-' . bin2hex(random_bytes(2)),
        ], 4, 2);
        self::assertSame([], $empty['followUps']);
        self::assertSame(0, $empty['total']);
        self::assertSame(1, $empty['page']);
        self::assertSame(1, $empty['pages']);
        self::assertSame([], $service->paginated([
            ...$filters,
            'branch_id' => (string) $this->otherBranch,
        ], 1, 2)['followUps']);

        $query = http_build_query(array_merge($filters, ['page' => 2]));
        $response = $this->withSession(session()->get())->get('admissions/followups?' . $query);
        $body = $response->getBody();
        self::assertSame(200, $response->response()->getStatusCode());
        self::assertStringContainsString('Page 2 of 3', $body);
        self::assertStringContainsString('Previous', $body);
        self::assertStringContainsString('Next', $body);
        self::assertStringNotContainsString('mcc-pagination-ellipsis', $body);
        self::assertMatchesRegularExpression(
            '/<a\s+class="mcc-button mcc-button-primary".*?aria-current="page"/s',
            $body,
        );
        self::assertStringContainsString('page=3', $body);
        foreach ($filters as $key => $value) {
            self::assertStringContainsString(
                rawurlencode($key) . '=' . rawurlencode((string) $value),
                html_entity_decode($body),
            );
        }

        $firstPage = $this->withSession(session()->get())->get(
            'admissions/followups?' . http_build_query(array_merge($filters, ['page' => 1])),
        );
        self::assertStringContainsString('Page 1 of 3', $firstPage->getBody());
        self::assertMatchesRegularExpression('/<span[^>]*aria-disabled="true"[^>]*>\s*Previous\s*<\/span>/s', $firstPage->getBody());
        self::assertMatchesRegularExpression('/<a[^>]*>\s*Next\s*<\/a>/s', $firstPage->getBody());

        $outOfRange = $this->withSession(session()->get())->get(
            'admissions/followups?' . http_build_query(array_merge($filters, ['page' => 999])),
        );
        self::assertStringContainsString('Page 3 of 3', $outOfRange->getBody());
        self::assertMatchesRegularExpression('/<a[^>]*>\s*Previous\s*<\/a>/s', $outOfRange->getBody());
        self::assertMatchesRegularExpression('/<span[^>]*aria-disabled="true"[^>]*>\s*Next\s*<\/span>/s', $outOfRange->getBody());

        $empty = $this->withSession(session()->get())->get(
            'admissions/followups?' . http_build_query([
                ...$filters,
                'applicant' => 'no-such-pagination-applicant-' . bin2hex(random_bytes(2)),
            ]),
        );
        self::assertStringContainsString('No Admission follow-ups match the current filters.', $empty->getBody());
        self::assertStringNotContainsString('class="mcc-pagination"', $empty->getBody());

        $duplicatePage = $this->withSession(session()->get())->get(
            'admissions/followups?' . http_build_query($filters) . '&page[]=invalid&page[]=2',
        );
        self::assertSame(200, $duplicatePage->response()->getStatusCode());
        self::assertStringContainsString('Page 2 of 3', $duplicatePage->getBody());
    }

    public function testCompletedAndConvertedAdmissionsKeepHistoryReadableButRejectCreation(): void
    {
        $service = service('admissionFollowUp', false);
        $original = $this->db->table('admissions')->where('id', $this->admission)->get()->getRowArray();
        self::assertIsArray($original);
        $marker = bin2hex(random_bytes(3));
        $existingId = $service->create($this->input('06-01-2026', '09:00', $marker . '_existing'));

        try {
            foreach (['APPROVED', 'CONVERTED'] as $status) {
                $this->db->table('admissions')->where('id', $this->admission)->update(['status' => $status]);

                try {
                    $service->create($this->input('07-01-2026', '09:00', $marker . '_' . $status));
                    self::fail('A follow-up was created for a closed Admission.');
                } catch (RuntimeException $exception) {
                    self::assertSame(
                        'No new follow-up can be created after the Admission is completed or converted.',
                        $exception->getMessage(),
                    );
                }
            }
            $history = $service->forAdmission($this->admission);
            self::assertContains($existingId, array_map(
                static fn (array $row): int => (int) $row['id'],
                $history,
            ));
            self::assertSame(1, $this->db->table('admission_followups')
                ->where('admission_id', $this->admission)
                ->where('notes', 'TEST_MCC_' . $marker . '_existing')
                ->countAllResults());
        } finally {
            $this->db->table('admissions')->where('id', $this->admission)->update([
                'status' => $original['status'],
            ]);
        }
    }

    private function input(
        string $date,
        string $time,
        string $notes,
        ?int $admission = null,
        string $status = 'pending',
        string $nextDate = '',
    ): array
    {
        return [
            'admission_id' => (string) ($admission ?? $this->admission),
            'follow_up_at' => $date,
            'follow_up_time' => $time,
            'follow_up_type' => 'call',
            'status' => $status,
            'notes' => 'TEST_MCC_' . $notes,
            'next_follow_up_at' => $nextDate,
            'next_follow_up_time' => $nextDate === '' ? '' : '09:00',
            'assigned_user_id' => '',
        ];
    }

    private function authorize(array $permissions, array $branches): void
    {
        session()->set([
            'isLoggedIn' => true,
            'accountEnabled' => true,
            'user' => ['id' => $this->actor],
            'session_version' => 1,
            'authorizedBranches' => array_map('strval', $branches),
            'permissions' => $permissions,
        ]);
        \Config\Services::resetSingle('authorization');
    }
}
