<?php

declare(strict_types=1);

namespace App\Modules\Coaches\Controllers;

use App\Controllers\StaffController;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

final class Browser extends StaffController
{
    public function index(): string
    {
        $filters = [
            'q' => $this->request->getGet('q'),
            'status' => $this->request->getGet('status'),
            'branch_id' => $this->request->getGet('branch_id'),
            'page' => $this->request->getGet('page'),
        ];

        return $this->page(
            'App\\Modules\\Coaches\\Views\\index',
            'Coaches',
            ['result' => service('coach')->list($filters)]
        );
    }

    public function createPage(): string
    {
        return $this->page(
            'App\\Modules\\Coaches\\Views\\form',
            'Create coach profile',
            [
                'mode' => 'create',
                'coach' => [],
                'users' => service('coach')->eligibleUsers(),
            ]
        );
    }

    public function create(): ResponseInterface|string
    {
        try {
            $input = $this->request->getPost();
            $userId = (int) ($input['user_id'] ?? 0);
            unset($input[csrf_token()], $input['user_id']);
            service('coach')->saveProfile($userId, $input, true);

            return redirect()->to('/coaches/' . $userId)
                ->with('message', 'Coach profile created.');
        } catch (Throwable $exception) {
            return redirect()->back()
                ->withInput()
                ->with('error', $this->safe($exception));
        }
    }

    public function showPage(int $id): string|ResponseInterface
    {
        try {
            $coachService = service('coach');
            $coach = $coachService->find($id);
            $feedback = $coachService->feedback($id);
            $assignBranches = $coachService->managementBranches('coach.assign');
            $leaveBranches = array_values(array_filter(
                $coach['branches'],
                static fn(array $branch): bool => $coachService->canManageBranch(
                    (int) $branch['id'],
                    'coach.update'
                )
            ));
            $feedbackOptions = array_values(array_filter(
                $coach['feedback_options'],
                static fn(array $option): bool => $coachService->canManageBranch(
                    (int) $option['branch_id'],
                    'coach.update'
                )
            ));
            $feedbackPermissions = [];
            foreach ($feedback as $feedbackRow) {
                $feedbackPermissions[(int) $feedbackRow['id']]
                    = $coachService->canManageBranch((int) $feedbackRow['branch_id'], 'coach.update');
            }
            $leavePermissions = [];
            $ownPendingLeave = [];
            $currentUser = session('user');
            $currentUserId = is_array($currentUser) ? (int) ($currentUser['id'] ?? 0) : 0;
            $isCoach = in_array('Coach', (array) session('roles'), true);
            foreach ($coach['leave'] as $leave) {
                $leavePermissions[(int) $leave['branch_id']] = $coachService->canManageBranch(
                    (int) $leave['branch_id'],
                    'coach.update'
                );
                $ownPendingLeave[(int) $leave['id']] = $isCoach
                    && $currentUserId === (int) $coach['id']
                    && ($leave['status'] ?? '') === 'PENDING';
            }
            $availabilityPermissions = [];
            foreach ($coach['availability_records'] as $availability) {
                $availabilityPermissions[(int) $availability['id']]
                    = $coachService->canManageBranch((int) $availability['branch_id'], 'coach.update');
            }

            return $this->page(
                'App\\Modules\\Coaches\\Views\\show',
                'Coach profile',
                [
                    'coach' => $coach,
                    'branches' => $assignBranches,
                    'leave_branches' => $leaveBranches,
                    'leave_permissions' => $leavePermissions,
                    'own_pending_leave' => $ownPendingLeave,
                    'availability_permissions' => $availabilityPermissions,
                    'availability_branches' => $leaveBranches,
                    'feedback_options' => $feedbackOptions,
                    'feedback' => $feedback,
                    'feedback_permissions' => $feedbackPermissions,
                    'can_assign' => $assignBranches !== [],
                    'can_update' => $leaveBranches !== [] || $feedbackOptions !== [],
                ]
            );
        } catch (Throwable $exception) {
            return $this->response
                ->setStatusCode($exception->getMessage() === 'Forbidden' ? 403 : 404)
                ->setBody(esc($this->safe($exception)));
        }
    }

    public function sessionsPage(int $id): string
    {
        try {
            $filters = $this->request->getGet();
            $result = service('coach')->sessionsPage($id, $filters);

            return $this->page(
                'App\\Modules\\Coaches\\Views\\sessions',
                'Coach sessions',
                [
                    'coach' => $result['coach'],
                    'result' => $result,
                ]
            );
        } catch (Throwable $exception) {
            return $this->response
                ->setStatusCode($exception->getMessage() === 'Forbidden' ? 403 : 404)
                ->setBody(esc($this->safe($exception)));
        }
    }

    public function sessionDetailPage(int $coachId, int $sessionId): string
    {
        try {
            $session = service('coach')->sessionDetail($coachId, $sessionId);
            $branchId = (string) $session['branch_id'];

            return $this->page(
                'App\\Modules\\Coaches\\Views\\session',
                'Coach session details',
                [
                    'coach' => service('coach')->find($coachId),
                    'session' => $session,
                    'canAttendanceView' => service('authorization')->can('attendance.view', $branchId),
                    'canAttendanceRecord' => service('authorization')->can('attendance.record', $branchId),
                ]
            );
        } catch (Throwable $exception) {
            return $this->response
                ->setStatusCode($exception->getMessage() === 'Forbidden' ? 403 : 404)
                ->setBody(esc($this->safe($exception)));
        }
    }

    public function playersPage(int $id): string
    {
        try {
            $filters = $this->request->getGet();
            $result = service('coach')->playersPage($id, $filters);

            return $this->page(
                'App\\Modules\\Coaches\\Views\\players',
                'Assigned players',
                [
                    'coach' => $result['coach'],
                    'result' => $result,
                ]
            );
        } catch (Throwable $exception) {
            return $this->response
                ->setStatusCode($exception->getMessage() === 'Forbidden' ? 403 : 404)
                ->setBody(esc($this->safe($exception)));
        }
    }

    public function editPage(int $id): string
    {
        try {
            return $this->page(
                'App\\Modules\\Coaches\\Views\\form',
                'Edit coach profile',
                [
                    'mode' => 'edit',
                    'coach' => service('coach')->find($id),
                    'users' => [],
                ]
            );
        } catch (Throwable $exception) {
            return $this->response
                ->setStatusCode($exception->getMessage() === 'Forbidden' ? 403 : 404)
                ->setBody(esc($this->safe($exception)));
        }
    }

    public function update(int $id): ResponseInterface|string
    {
        try {
            $input = $this->request->getPost();
            unset($input[csrf_token()]);
            service('coach')->saveProfile($id, $input);

            return redirect()->to('/coaches/' . $id)
                ->with('message', 'Coach profile updated.');
        } catch (Throwable $exception) {
            return redirect()->back()
                ->withInput()
                ->with('error', $this->safe($exception));
        }
    }

    public function branch(int $id): ResponseInterface|string
    {
        try {
            service('coach')->assignBranch(
                $id,
                (int) $this->request->getPost('branch_id'),
                (string) $this->request->getPost('action') === 'assign',
                (string) $this->request->getPost('reason')
            );

            return redirect()->to('/coaches/' . $id)->with('message', 'Coach branch assignment updated.');
        } catch (Throwable $exception) {
            return redirect()->back()->withInput()->with('error', $this->safe($exception));
        }
    }

    public function leave(int $id): ResponseInterface|string
    {
        try {
            service('coach')->saveLeave(
                $id,
                (int) $this->request->getPost('branch_id'),
                $this->request->getPost(),
                (int) ($this->request->getPost('leave_id') ?: 0) ?: null
            );

            return redirect()->to('/coaches/' . $id)->with('message', 'Coach leave saved.');
        } catch (Throwable $exception) {
            return redirect()->back()->withInput()->with('error', $this->safe($exception));
        }
    }

    public function cancelLeave(int $id): ResponseInterface|string
    {
        try {
            service('coach')->cancelLeave($id, (string) $this->request->getPost('reason'));

            return redirect()->back()->with('message', 'Coach leave cancelled.');
        } catch (Throwable $exception) {
            return redirect()->back()->withInput()->with('error', $this->safe($exception));
        }
    }

    public function approveLeave(int $id): ResponseInterface|string
    {
        try {
            service('coach')->approveLeave($id, (string) $this->request->getPost('reason'));

            return redirect()->back()->with('message', 'Coach leave approved.');
        } catch (Throwable $exception) {
            return redirect()->back()->withInput()->with('error', $this->safe($exception));
        }
    }

    public function rejectLeave(int $id): ResponseInterface|string
    {
        try {
            service('coach')->rejectLeave($id, (string) $this->request->getPost('reason'));

            return redirect()->back()->with('message', 'Coach leave rejected.');
        } catch (Throwable $exception) {
            return redirect()->back()->withInput()->with('error', $this->safe($exception));
        }
    }

    public function availability(int $id): ResponseInterface|string
    {
        try {
            service('coach')->saveAvailability(
                $id,
                (int) $this->request->getPost('branch_id'),
                $this->request->getPost(),
                (int) ($this->request->getPost('availability_id') ?: 0) ?: null
            );

            return redirect()->to('/coaches/' . $id)->with('message', 'Coach availability saved.');
        } catch (Throwable $exception) {
            return redirect()->back()->withInput()->with('error', $this->safe($exception));
        }
    }

    public function cancelAvailability(int $id): ResponseInterface|string
    {
        try {
            service('coach')->cancelAvailability($id, (string) $this->request->getPost('reason'));

            return redirect()->back()->with('message', 'Coach availability cancelled.');
        } catch (Throwable $exception) {
            return redirect()->back()->withInput()->with('error', $this->safe($exception));
        }
    }

    public function feedback(int $id): ResponseInterface|string
    {
        $input = (array) $this->request->getPost();
        $returnTo = $this->feedbackReturnTarget($input, $id);

        try {
            service('coach')->saveFeedback(
                $id,
                $input,
                (int) ($input['feedback_id'] ?? 0) ?: null
            );

            return redirect()->to($returnTo)->with('message', 'Coach feedback saved.');
        } catch (Throwable $exception) {
            return redirect()->to($returnTo)->withInput()->with('error', $this->safe($exception));
        }
    }

    private function feedbackReturnTarget(array $input, int $coachUserId): string
    {
        $sessionId = (int) ($input['session_id'] ?? 0);
        $target = trim((string) ($input['return_to'] ?? ''));
        $sessionFallback = $sessionId > 0 ? '/sessions/' . $sessionId . '/page' : '';

        if ($target === '') {
            return $sessionFallback !== '' ? $sessionFallback : '/coaches/' . $coachUserId;
        }

        if (preg_match('#^/sessions/(\d+)/page$#', $target, $matches) === 1) {
            return (int) $matches[1] === $sessionId && $sessionId > 0
                ? $target
                : ($sessionFallback !== '' ? $sessionFallback : '/coaches/' . $coachUserId);
        }

        if (preg_match('#^/attendance/sessions/(\d+)$#', $target, $matches) === 1) {
            return (int) $matches[1] === $sessionId && $sessionId > 0
                ? $target
                : ($sessionFallback !== '' ? $sessionFallback : '/coaches/' . $coachUserId);
        }

        if (preg_match('#^/coaches/(\d+)$#', $target, $matches) === 1
            && (int) $matches[1] === $coachUserId) {
            return $target;
        }

        return $sessionFallback !== '' ? $sessionFallback : '/coaches/' . $coachUserId;
    }
}
