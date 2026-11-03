<?php

declare (strict_types=1);
namespace App\Modules\Scheduling\Controllers;

use App\Controllers\StaffController;
use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;
final class Browser extends StaffController
{
    protected function safe(Throwable $e, string $operation = 'request'): string
    {
        if ($e instanceof DatabaseException) {
            return $this->unexpectedFailure($e, $operation);
        }

        return match ($e->getMessage()) {
            'Forbidden' => 'You are not authorized to create a session for this branch.',
            'Not found.' => 'The requested record was not found.',
            'The action you requested is not allowed.' => 'Your form session expired. Reload the form and submit it again.',
            'Missing required field.' => 'Complete all required fields before saving the session.',
            'Required field.' => 'Complete all required fields before saving the session.',
            'Please select a branch.' => 'Please select a branch.',
            'Please select a coach.' => 'Please select a coach.',
            'Invalid branch.' => 'Select an authorized active branch.',
            'Invalid program.' => 'Select a valid Program for the selected branch.',
            'Invalid batch.' => 'Select a valid active Batch.',
            'Invalid age group.' => 'Select a valid active Age Group.',
            'Invalid skill selection.' => 'Select a valid skill option.',
            'Invalid skill pairing.' => 'Select a skill that belongs to the selected skill area.',
            'Retired skill option.' => 'Retired skill options cannot be selected for a new session.',
            'Skill catalog unavailable.' => 'Skill options are temporarily unavailable.',
            'Invalid coach.' => 'Select an active Coach assigned to the selected branch.',
            'Coach is not authorized for branch.' => 'Select an active Coach assigned to the selected branch.',
            'Ground is not authorized for branch.', 'Invalid ground.' => 'Select an active Ground for the selected branch.',
            'Ground is not available.' => 'Select an active Ground for the selected branch.',
            'Ground unavailable.' => 'The selected Ground is unavailable during this session.',
            'Ground scheduling conflict.' => 'The selected Ground already has a conflicting session.',
            'Invalid roster.' => 'Select at least one valid Player.',
            'Invalid student.' => 'Select a valid active Student for the selected branch and Batch.',
            'Player is not assigned to this Coach.' => 'Select only Players assigned to you.',
            'Duplicate student.' => 'Select each Student only once.',
            'Player is not eligible.' => 'Select only active Players eligible for the selected Batch.',
            'Invalid session status.' => 'Select a valid session status.',
            'Invalid session type.' => 'Choose a valid Session Type.',
            'Invalid session time.' => 'Enter valid session times.',
            'Invalid calendar date.' => 'Enter valid session dates in DD-MM-YYYY format.',
            'Invalid session window.' => 'Enter a valid session window with the start before the end.',
            'Coach unavailable.' => 'The selected Coach has approved leave during this session.',
            'Scheduling conflict.' => 'The selected Coach already has a conflicting session.',
            'Player scheduling conflict.' => 'A selected Player already has a conflicting session.',
            'Unable to create session.' => 'The session could not be saved. No partial records were kept.',
            'Unable to update session.' => 'The session could not be updated. No partial changes were kept.',
            'Session is locked because Attendance has started.' => 'Attendance has started for this session, so its schedule and Players cannot be edited.',
            'Update conflict.' => 'This session changed while you were editing it. Reload the Edit Session page and try again.',
            'Attendance roster snapshot required.' => 'Attendance membership is unavailable. Reload Session Details and try again.',
            'Attendance roster is empty.' => 'Select at least one eligible Player before completing the session.',
            'Attendance is incomplete.' => 'Record attendance for every selected Player before completing the session.',
            'Cancelled sessions cannot be completed.' => 'Cancelled sessions cannot be completed.',
            'Session is not active.' => 'Only scheduled sessions can be completed.',
            'Unable to complete session.' => 'The session could not be completed. Reload Session Details and try again.',
            default => $this->unexpectedFailure($e, $operation),
        };
    }

    private function unexpectedFailure(Throwable $e, string $operation): string
    {
        $reference = 'SCH-' . strtoupper(bin2hex(random_bytes(4)));
        $detail = preg_replace(
            '/(?i)(password|token|secret|authorization|cookie)\s*[=:]\s*[^\s,;]+/',
            '$1=[redacted]',
            $e->getMessage()
        );
        $detail = is_string($detail)
            ? preg_replace("/'[^']*'/", "'[redacted]'", $detail)
            : $detail;
        log_message('error', 'Scheduling {operation} failed [{reference}] {class}: {detail}', [
            'reference' => $reference,
            'operation' => $operation,
            'class' => $e::class,
            'detail' => $detail === null ? 'Exception detail unavailable.' : $detail,
        ]);

        return 'The session could not be saved. Reference ' . $reference . ' can be provided to support.';
    }

    public function index(): string|ResponseInterface
    {
        try {
            $scheduling = service('scheduling');
            $result = $scheduling->sessionPage($this->request->getGet());
            $options = $scheduling->sessionFilterOptions();

            return $this->page('App\Modules\Scheduling\Views\index', 'Sessions', [
                'sessions' => $result['rows'],
                'total' => $result['total'],
                'page' => $result['page'],
                'pages' => $result['pages'],
                'branchOptions' => $options['branches'],
                'batchOptions' => $options['batches'],
                'coachOptions' => $options['coaches'],
                'groundOptions' => $options['grounds'],
                'filters' => $this->request->getGet(),
            ]);
        } catch (Throwable $e) {
            $options = service('scheduling')->sessionFilterOptions();

            return $this->page('App\Modules\Scheduling\Views\index', 'Sessions', [
                'sessions' => [],
                'total' => 0,
                'page' => 1,
                'pages' => 1,
                'branchOptions' => $options['branches'],
                'batchOptions' => $options['batches'],
                'coachOptions' => $options['coaches'],
                'groundOptions' => $options['grounds'],
                'filters' => $this->request->getGet(),
                'error' => $this->safe($e),
            ]);
        }
    }
    public function createPage(): string
    {
        $branchId = (int) ($this->request->getGet('branch_id') ?? 0);
        $options = service('scheduling')->sessionFormOptions(
            'session.create',
            $branchId > 0 ? $branchId : null,
            true
        );
        $branchContext = service('branchContext');

        return $this->page(
            'App\Modules\Scheduling\Views\create',
            'Create Session',
            [
                'formOptions' => $options,
                'isBranchAdmin' => $branchContext->isBranchAdminContext(),
                'isCoachScoped' => service('scheduling')->isCoachScoped(),
                'activeBranchId' => $branchContext->activeBranchId(),
            ]
        );
    }

    public function availability(): ResponseInterface
    {
        try {
            $result = service('scheduling')->availabilityWindows(
                (int) $this->request->getGet('coach_user_id'),
                (int) $this->request->getGet('branch_id'),
                (string) $this->request->getGet('date')
            );

            return $this->response->setJSON($result);
        } catch (Throwable $e) {
            return $this->response
                ->setStatusCode($e->getMessage() === 'Forbidden' ? 403 : 422)
                ->setJSON(['error' => $this->safe($e)]);
        }
    }
    public function showPage(int $id): string|ResponseInterface
    {
        try {
            $scheduling = service('scheduling');
            $session = $scheduling->find($id);
            $branch = (string) $session['branch_id'];
            $authorization = service('authorization');
            $canUpdate = $authorization->can('session.update', $branch);
            $canCancel = $authorization->can('session.cancel', $branch);
            $canAttendanceView = $authorization->can('attendance.view', $branch);
            $canAttendanceCapture = $authorization->can('attendance.record', $branch);
            $canComplete = $scheduling->completionEligible($id);
            $attendanceRoster = $canAttendanceView
                ? service('attendance')->roster($id)
                : ['players' => [], 'snapshot_status' => 'missing'];
            $sessionFeedback = ($canAttendanceView || $canUpdate)
                ? service('coach')->sessionFeedback($id)
                : [];
            $canAuditView = $authorization->can('session.update', $branch)
                || $authorization->can('audit.view', $branch);
            return $this->page(
                'App\Modules\Scheduling\Views\show',
                'Session #' . $id,
                [
                    'session' => $session,
                    'formOptions' => $canUpdate
                        ? $scheduling->sessionFormOptions(
                            'session.update',
                            (int) $session['branch_id'],
                            false,
                            (int) ($session['skill_option_id'] ?? 0),
                            (int) ($session['age_group_id'] ?? 0)
                        )
                        : ['coaches' => []],
                    'can_update' => $canUpdate,
                    'can_cancel' => $canCancel,
                    'can_attendance_view' => $canAttendanceView,
                    'can_attendance_capture' => $canAttendanceCapture,
                    'can_complete' => $canComplete,
                    'attendance_roster' => $attendanceRoster,
                    'attendance_statuses' => service('attendance')->statuses(),
                    'session_feedback' => $sessionFeedback,
                    'can_feedback_update' => $authorization->can('coach.update', $branch),
                    'can_audit_view' => $canAuditView,
                    'pageActions' => $this->detailPageActions(
                        $id,
                        $canUpdate,
                        $session['status'] === 'scheduled',
                        $canCancel,
                        $canAttendanceView || $canAttendanceCapture,
                        $canComplete
                    ),
                ]
            );
        } catch (Throwable $e) {
            return $this->response->setStatusCode($e->getMessage() === 'Forbidden' ? 403 : 404)->setBody(esc($this->safe($e)));
        }
    }
    public function editPage(int $id): string
    {
        try {
            $scheduling = service('scheduling');
            $session = $scheduling->find($id);

            return $this->page(
                'App\\Modules\\Scheduling\\Views\\edit',
            'Edit Session',
                [
                    'session' => $session,
                    'formOptions' => $scheduling->sessionFormOptions(
                        'session.update',
                        (int) $session['branch_id'],
                        false,
                        (int) ($session['skill_option_id'] ?? 0),
                        (int) ($session['age_group_id'] ?? 0)
                    ),
                    'isCoachScoped' => $scheduling->isCoachScoped(),
                ]
            );
        } catch (Throwable $e) {
            return $this->response
                ->setStatusCode($e->getMessage() === 'Forbidden' ? 403 : 404)
                ->setBody(esc($this->safe($e)));
        }
    }

    private function detailPageActions(
        int $id,
        bool $canUpdate,
        bool $scheduled,
        bool $canCancel,
        bool $canAttendance,
        bool $canComplete
    ): string
    {
        $actions = '<div class="mcc-page-actions mcc-detail-actions">';
        $actions .= '<a class="mcc-button mcc-button-secondary" href="' . site_url('sessions') . '">';
        $actions .= '<i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back to sessions</a>';

        if ($canUpdate && $scheduled) {
            $actions .= '<a class="mcc-button mcc-button-primary" href="'
                . esc(site_url('sessions/' . $id . '/edit'))
                . '">';
            $actions .= '<i class="fa-solid fa-pen-to-square" aria-hidden="true"></i> Edit session</a>';
        }

        if ($canAttendance) {
            $actions .= '<a class="mcc-button mcc-button-secondary" href="'
                . site_url('attendance/sessions/' . $id)
                . '"><i class="fa-solid fa-clipboard-list" aria-hidden="true"></i>'
                . ' Attendance</a>';
        }

        if ($canComplete) {
            $actions .= '<form method="post" action="' . site_url('sessions/' . $id . '/complete') . '"';
            $actions .= ' class="mcc-page-action-form" onsubmit="return confirm(\'Complete this session?\');">';
            $actions .= csrf_field();
            $actions .= '<button class="mcc-button mcc-button-primary" type="submit">';
            $actions .= '<i class="fa-solid fa-check" aria-hidden="true"></i> Complete session</button></form>';
        }

        if ($canUpdate && $scheduled) {
            $actions .= '<a class="mcc-button mcc-button-secondary" href="#coach-assignment-heading">'
                . '<i class="fa-solid fa-user-pen" aria-hidden="true"></i> Reassign Coach</a>';
        }

        if ($canCancel && $scheduled) {
            $actions .= '<a class="mcc-button mcc-button-danger" href="#session-actions-heading">'
                . '<i class="fa-solid fa-ban" aria-hidden="true"></i> Cancel session</a>';
        }

        return $actions . '</div>';
    }
    public function update(int $id): \CodeIgniter\HTTP\ResponseInterface|string
    {
        try {
            $input = $this->normalizeDateTimeInput($this->request->getPost());
            $updated = service('scheduling')->update($id, $input);
            if (!$this->browser()) {
                return $this->response->setJSON(['updated' => $updated]);
            }
            return redirect()->to('/sessions/' . $id . '/page')->with('message', 'Session updated.');
        } catch (Throwable $e) {
            return $this->editFailure($e, $id);
        }
    }

    public function reassignCoach(int $id): \CodeIgniter\HTTP\ResponseInterface|string
    {
        try {
            service('scheduling')->reassignCoach(
                $id,
                (int) $this->request->getPost('coach_user_id'),
                (string) $this->request->getPost('reason')
            );

            return redirect()->to('/sessions/' . $id . '/page')->with('message', 'Session Coach reassigned.');
        } catch (Throwable $e) {
            return redirect()->to('/sessions/' . $id . '/page')
                ->withInput()
                ->with('error', $this->safe($e))
                ->with('field_errors', $this->fieldErrors($e->getMessage()));
        }
    }
    public function create(): \CodeIgniter\HTTP\ResponseInterface|string
    {
        try {
            $d = $this->normalizeDateTimeInput($this->request->getPost());
            $id = service('scheduling')->create($d);
            if (!$this->browser()) {
                return $this->response->setStatusCode(201)->setJSON(['id' => $id]);
            }
            return redirect()->to('/sessions/' . $id . '/page')->with('message', 'Session created.');
        } catch (Throwable $e) {
            if (!$this->browser()) {
                return $this->response->setStatusCode(422)->setJSON(['error' => 'Session request rejected.']);
            }
            return redirect()->to('/sessions/new')
                ->withInput()
                ->with('error', $this->safe($e))
                ->with('field_errors', $this->fieldErrors($e->getMessage()));
        }
    }
    public function cancel(int $id): \CodeIgniter\HTTP\ResponseInterface|string
    {
        try {
            service('scheduling')->cancel($id, (string) $this->request->getPost('reason'));
            if (!$this->browser()) {
                return $this->response->setJSON(['updated' => true]);
            }
            return redirect()->to('/sessions/' . $id . '/page')->with('message', 'Session cancelled.');
        } catch (Throwable $e) {
            return $this->failure($e, $id);
        }
    }
    public function reschedule(int $id): \CodeIgniter\HTTP\ResponseInterface|string
    {
        try {
            $d = $this->normalizeDateTimeInput($this->request->getPost());
            $new = service('scheduling')->reschedule($id, (string) ($d['starts_at'] ?? ''), null, (string) ($d['reason'] ?? ''));
            if (!$this->browser()) {
                return $this->response->setJSON(['id' => $new]);
            }
            return redirect()->to('/sessions/' . $new . '/page')->with('message', 'Session rescheduled.');
        } catch (Throwable $e) {
            return $this->failure($e, $id);
        }
    }
    private function failure(Throwable $e, int $id): \CodeIgniter\HTTP\ResponseInterface|string
    {
        if (!$this->browser()) {
            $status = match ($e->getMessage()) {
                'Forbidden' => 403,
                'Update conflict.' => 409,
                default => 422,
            };

            return $this->response
                ->setStatusCode($status)
                ->setJSON(['error' => 'Session request rejected.']);
        }
        return redirect()->to('/sessions/' . $id . '/page')
            ->withInput()
            ->with('error', $this->safe($e))
            ->with('field_errors', $this->fieldErrors($e->getMessage()));
    }

    public function complete(int $id): \CodeIgniter\HTTP\ResponseInterface|string
    {
        try {
            service('scheduling')->complete($id, (string) $this->request->getPost('reason'));
            return redirect()->to('/sessions/' . $id . '/page')->with('message', 'Session completed.');
        } catch (Throwable $e) {
            return $this->failure($e, $id);
        }
    }

    private function editFailure(Throwable $e, int $id): \CodeIgniter\HTTP\ResponseInterface|string
    {
        log_message('error', 'Scheduling update rejected {class}: {detail}', [
            'class' => $e::class,
            'detail' => $e->getMessage(),
        ]);

        if (!$this->browser()) {
            return $this->failure($e, $id);
        }

        return redirect()->to('/sessions/' . $id . '/edit')
            ->withInput()
            ->with('error', $this->safe($e, 'update'))
            ->with('field_errors', $this->fieldErrors($e->getMessage()));
    }

    private function fieldErrors(string $message): array
    {
        $fields = [
            'Please select a branch.' => ['branch_id'],
            'Please select a coach.' => ['coach_user_id'],
            'Invalid branch.' => ['branch_id'],
            'Invalid program.' => ['program_id'],
            'Invalid batch.' => ['batch_id'],
            'Invalid age group.' => ['age_group_id'],
            'Invalid skill selection.' => ['skill_area', 'skill_option_id'],
            'Invalid skill pairing.' => ['skill_area', 'skill_option_id'],
            'Retired skill option.' => ['skill_option_id'],
            'Skill catalog unavailable.' => ['skill_option_id'],
            'Invalid coach.' => ['coach_user_id'],
            'Coach is not authorized for branch.' => ['coach_user_id'],
            'Ground is not authorized for branch.' => ['ground_id'],
            'Invalid ground.' => ['ground_id'],
            'Invalid session status.' => ['status'],
            'Invalid session type.' => ['session_type'],
            'Invalid roster.' => ['player_ids'],
            'Invalid student.' => ['player_ids'],
            'Player is not assigned to this Coach.' => ['player_ids'],
            'Duplicate student.' => ['player_ids'],
            'Player is not eligible.' => ['player_ids'],
            'Coach unavailable.' => ['starts_at', 'coach_user_id'],
            'Invalid session window.' => ['starts_at', 'ends_time'],
            'Datetime timezone is required.' => ['starts_at'],
            'Invalid calendar date.' => ['starts_at'],
            'Invalid session time.' => ['starts_time', 'ends_time'],
            'Ground is not available.' => ['ground_id'],
            'Ground unavailable.' => ['ground_id'],
            'Ground scheduling conflict.' => ['ground_id', 'starts_at'],
            'The action you requested is not allowed.' => [],
        ];

        $result = [];
        $fieldsForMessage = $fields[$message] ?? [];
        foreach ($fieldsForMessage as $field) {
            $result[$field] = $message;
        }

        return $result;
    }

    private function normalizeDateTimeInput(array $input): array
    {
        unset($input['ends_at']);

        foreach (['starts_at'] as $field) {
            $timeField = 'starts_time';
            $dateValue = trim((string) ($input[$field] ?? ''));
            $timeValue = trim((string) ($input[$timeField] ?? ''));

            if ($timeValue !== '' && ! preg_match('/^\d{2}:\d{2}$/', $timeValue)) {
                throw new \RuntimeException('Invalid session time.');
            }

            if ($timeValue !== '' && ! preg_match('/(?:T| )\d{2}:\d{2}(?::\d{2})?/', $dateValue)) {
                $input[$field] = $dateValue . ' ' . $timeValue . ':00';
            }
        }

        $endTimeValue = trim((string) ($input['ends_time'] ?? ''));
        if ($endTimeValue !== '' && ! preg_match('/^\d{2}:\d{2}$/', $endTimeValue)) {
            throw new \RuntimeException('Invalid session time.');
        }

        if ($endTimeValue === '') {
            $input['ends_at'] = null;
        } else {
            $startValue = trim((string) ($input['starts_at'] ?? ''));
            preg_match('/^(\d{2}-\d{2}-\d{4}|\d{4}-\d{2}-\d{2})/', $startValue, $dateMatch);
            $endDateValue = (string) ($dateMatch[1] ?? $startValue);
            $input['ends_at'] = $endDateValue . ' ' . $endTimeValue . ':00';
        }

        return $input;
    }
}
