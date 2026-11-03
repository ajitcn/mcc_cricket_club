<?php

declare (strict_types=1);
namespace App\Modules\Attendance\Controllers;

use App\Controllers\StaffController;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Database\Exceptions\DatabaseException;
use Throwable;
final class Attendance extends StaffController
{
    public function index(): string|ResponseInterface
    {
        try {
            $attendance = service('attendance');
            $result = $attendance->sessionPage($this->request->getGet());
            $options = $attendance->sessionFilterOptions();
            $period = (string) ($this->request->getGet('period') ?: 'monthly');
            $reportDate = $this->request->getGet('report_date');

            return view('App\Modules\Attendance\Views\index', [
                'sessions' => $result['rows'],
                'total' => $result['total'],
                'page' => $result['page'],
                'pages' => $result['pages'],
                'branchOptions' => $options['branches'],
                'batchOptions' => $options['batches'],
                'batchesByBranch' => $options['batchesByBranch'] ?? [],
                'filters' => $this->request->getGet(),
                'attendanceStatuses' => $attendance->statuses(),
                'reportingPeriods' => $attendance->reportingPeriods(),
                'attendanceMetrics' => $attendance->metrics($period, is_string($reportDate) ? $reportDate : null),
                'attendanceAlerts' => $attendance->alerts($period, is_string($reportDate) ? $reportDate : null),
                'nav' => $this->navigation(),
                'error' => session()->getFlashdata('error'),
                'message' => session()->getFlashdata('message'),
            ]);
        } catch (Throwable $exception) {
            return $this->htmlFailure($exception, 422);
        }
    }
    public function sessionPage(int $id): string|ResponseInterface
    {
        try {
            $page = service('attendance')->roster($id);

            return view('App\Modules\Attendance\Views\session', [
                ...$page,
                'nav' => $this->navigation(),
                'error' => session()->getFlashdata('error'),
                'message' => session()->getFlashdata('message'),
                'session_feedback' => service('coach')->sessionFeedback($id),
                'can_feedback_update' => service('authorization')->can(
                    'coach.update',
                    (string) ($page['session']['branch_id'] ?? '')
                ),
                'can_correct' => service('authorization')->can(
                    'attendance.correct',
                    (string) ($page['session']['branch_id'] ?? '')
                ),
            ]);
        } catch (Throwable $exception) {
            $status = match ($exception->getMessage()) {
                'Forbidden' => 403,
                'Session not found.' => 404,
                default => 422,
            };
            return $this->htmlFailure($exception, $status);
        }
    }
    public function show(int $id): ResponseInterface
    {
        try {
            return $this->response->setJSON(service('attendance')->find($id));
        } catch (Throwable $exception) {
            return $this->fail($exception);
        }
    }
    public function detail(int $id): string|ResponseInterface
    {
        try {
            return view('App\Modules\Attendance\Views\detail', ['attendance' => service('attendance')->find($id), 'nav' => $this->navigation(),
    'error' => null, 'message' => null]);
        } catch (Throwable $exception) {
            return $this->htmlFailure($exception, $exception->getMessage() === 'Forbidden' ? 403 : 404);
        }
    }
    public function record(int $session, int $player): ResponseInterface|string
    {
        try {
            $data = $this->input();
            $snapshotId = isset($data['snapshot_id']) && (string) $data['snapshot_id'] !== ''
                ? (int) $data['snapshot_id']
                : null;
            $id = service('attendance')->record(
                $session,
                $player,
                (string) ($data['status'] ?? ''),
                $snapshotId
            );
            return $this->isJsonRequest()
                ? $this->response->setStatusCode(201)->setJSON(['id' => $id])
                : redirect()->to($this->redirectTarget($session))->with('message', 'Attendance recorded.');
        } catch (Throwable $exception) {
            return $this->failure($exception, $session);
        }
    }

    public function save(int $session): ResponseInterface|string
    {
        try {
            $data = $this->input();
            $statuses = $data['statuses'] ?? [];
            $versions = $data['versions'] ?? [];
            if (! is_array($statuses) || ! is_array($versions)) {
                throw new RuntimeException('Invalid attendance request payload.');
            }
            if ($versions !== []) {
                service('attendance')->updateStatuses(
                    $session,
                    $statuses,
                    $versions,
                    (string) ($data['correction_reason'] ?? '')
                );
            } else {
                service('attendance')->save($session, $statuses, null);
            }

            return $this->isJsonRequest()
                ? $this->response->setJSON(['saved' => true])
                : redirect()->to($this->redirectTarget($session))->with('message', 'Attendance saved.');
        } catch (Throwable $exception) {
            return $this->failure($exception, $session);
        }
    }


    public function correct(int $id): ResponseInterface|string
    {
        try {
            $data = $this->input();
            $sessionId = service('attendance')->correct(
                $id,
                (string) ($data['status'] ?? ''),
                (string) ($data['reason'] ?? ''),
                (int) ($data['expected_version'] ?? 0),
                isset($data['snapshot_id']) && (string) $data['snapshot_id'] !== ''
                    ? (int) $data['snapshot_id']
                    : null
            );
            $returnTo = (string) $this->request->getPost('return_to');
            return $this->isJsonRequest()
                ? $this->response->setJSON(['updated' => true])
                : redirect()->to($this->safeReturnTarget($returnTo, $sessionId, true))->with('message', 'Attendance corrected.');
        } catch (Throwable $exception) {
            $returnTo = (string) $this->request->getPost('return_to');
            if ($this->isJsonRequest()) {
                return $this->fail($exception);
            }

            $sessionId = null;
            try {
                $sessionId = service('attendance')->sessionIdFor($id);
            } catch (Throwable) {
                $sessionId = null;
            }

            return redirect()
                ->to($this->safeReturnTarget($returnTo, $sessionId, true))
                ->withInput()
                ->with('error', $this->safeMessage($exception));
        }
    }
    private function input(): array
    {
        $contentType = strtolower((string) $this->request->getHeaderLine('Content-Type'));
        if (str_contains($contentType, 'application/json')) {
            try {
                $json = $this->request->getJSON(true);
                if (is_array($json)) {
                    return $json;
                }
            } catch (Throwable) {
                $post = (array) $this->request->getPost();
                if ($post !== []) {
                    return $post;
                }

                throw new RuntimeException('Invalid attendance request payload.');
            }
        }

        return (array) $this->request->getPost();
    }

    private function isJsonRequest(): bool
    {
        $contentType = strtolower((string) $this->request->getHeaderLine('Content-Type'));
        $accept = strtolower((string) $this->request->getHeaderLine('Accept'));

        return str_contains($contentType, 'application/json')
            && ! str_contains($accept, 'text/html');
    }
    private function failure(Throwable $exception, int $session): ResponseInterface|string
    {
        if ($this->isJsonRequest() || in_array($exception->getMessage(), ['Forbidden', 'Session not found.'], true)) {
            return $this->fail($exception);
        }
        return redirect()->to($this->redirectTarget($session))->withInput()->with('error', $this->safeMessage($exception));
    }

    private function redirectTarget(int $session): string
    {
        return $this->safeReturnTarget((string) $this->request->getPost('return_to'), $session);
    }

    private function safeReturnTarget(
        string $returnTo,
        ?int $session = null,
        bool $sessionDetailFallback = false
    ): string
    {
        if (preg_match('#^/sessions/(\d+)/page$#', $returnTo, $matches)) {
            if ($session === null || (int) $matches[1] === $session) {
                return $returnTo;
            }
        }

        if ($session === null) {
            return '/attendance/sessions';
        }

        return $sessionDetailFallback
            ? '/sessions/' . $session . '/page'
            : '/attendance/sessions/' . $session;
    }
    private function fail(Throwable $exception): ResponseInterface
    {
        $status = match ($exception->getMessage()) {
            'Forbidden' => 403,
            'Attendance not found.', 'Session not found.' => 404,
            'Stale attendance update.' => 409,
            default => 422,
        };
        $message = match ($exception->getMessage()) {
            'Forbidden' => 'Forbidden.',
            'Attendance not found.', 'Session not found.' => 'Not found.',
            'Stale attendance update.' => 'This attendance record changed in another session. Reload the latest attendance data and try again.',
            'Attendance already exists.' => 'Attendance has already been recorded for this Player. Reload the latest attendance data before trying again.',
            'Unable to persist attendance history.' => 'Attendance could not be saved because its history record was not written. Reload Session Details and try again.',
            'Unable to persist audit event.' => 'Attendance could not be saved because its audit record was not written. Reload Session Details and try again.',
            default => 'Attendance request rejected.',
        };

        return $this->response
            ->setStatusCode($status)
            ->setJSON(['error' => $message]);
    }
    private function htmlFailure(Throwable $exception, int $status): ResponseInterface
    {
        return $this->response->setStatusCode($status)->setBody(esc($this->safeMessage($exception)));
    }
    private function safeMessage(Throwable $exception): string
    {
        if ($exception instanceof DatabaseException) {
            $detail = strtolower($exception->getMessage());
            if (str_contains($detail, 'session_id_player_id') || str_contains($detail, 'duplicate')) {
                return 'Attendance has already been recorded for this Player. Reload the latest attendance data before trying again.';
            }
            if (str_contains($detail, 'attendance_history')) {
                return 'Attendance could not be saved because its history record was not written. Reload Session Details and try again.';
            }

            return 'Attendance could not be saved because the record was busy. Reload Session Details and try again.';
        }

        return match ($exception->getMessage()) {
            'Forbidden' => 'You are not authorized to access this attendance record.',
            'Authentication required' => 'Sign in again before changing attendance.',
            'Session not found.' => 'The requested session was not found.',
            'Attendance not found.' => 'The requested attendance record was not found.',
            'Stale attendance update.' => 'This attendance record changed in another session. Reload the latest attendance data and try again.',
            'Attendance is not available for this session.' => 'Attendance cannot be changed for this session.',
            'Attendance roster snapshot is unavailable for this session.' => 'Attendance is not available for this session.',
            'Attendance roster snapshot is outside the session window.' => 'Attendance is outside the session window.',
            'Attendance roster snapshot branch mismatch.' => 'The attendance membership is invalid for this branch.',
            'Attendance roster snapshot required.' => 'Attendance membership is unavailable. Reload the Session and try again.',
            'Attendance roster snapshot mismatch.' => 'Reload the Session before recording attendance.',
            'Attendance roster is empty.' => 'No selected Players are available for this session.',
            'Attendance is outside the recording window.' => 'Attendance can only be recorded during the session window.',
            'Attendance branch mismatch.' => 'The attendance record is inconsistent and cannot be accessed.',
            'Attendance already exists.' => 'Attendance has already been recorded for this player.',
            'Unable to record attendance.' => 'Attendance could not be saved. Reload Session Details and try again.',
            'Unable to persist attendance history.' => 'Attendance could not be saved because its history record was not written. Reload Session Details and try again.',
            'Unable to persist audit event.' => 'Attendance could not be saved because its audit record was not written. Reload Session Details and try again.',
            'Unable to capture attendance roster snapshot.' => 'Attendance could not be prepared. Reload the Session and try again.',
            'Player is not eligible.' => 'This player is not on the persisted session roster.',
            'Invalid attendance status.' => 'Select one of the approved attendance statuses.',
            'Invalid attendance request payload.' => 'The attendance form data is invalid. Reload Session Details and submit it again.',
            'Correction reason required.' => 'A correction reason is required.',
            'Attendance version required.' => 'Reload the attendance record before correcting it.',
            'Unable to correct attendance.' => 'Attendance could not be corrected. Reload Session Details and try again.',
            default => $this->unexpectedFailure($exception),
        };
    }

    private function unexpectedFailure(Throwable $exception): string
    {
        $reference = 'ATT-' . strtoupper(bin2hex(random_bytes(4)));
        $detail = preg_replace(
            '/(?i)(password|token|secret|authorization|cookie)\s*[=:]\s*[^\s,;]+/',
            '$1=[redacted]',
            $exception->getMessage()
        );
        $detail = is_string($detail)
            ? preg_replace("/'[^']*'/", "'[redacted]'", $detail)
            : $detail;
        log_message('error', 'Attendance request failed [{reference}] {class}: {detail}', [
            'reference' => $reference,
            'class' => $exception::class,
            'detail' => $detail === null ? 'Exception detail unavailable.' : $detail,
        ]);

        return 'Attendance request could not be completed. Reference ' . $reference . ' can be provided to support.';
    }
}
