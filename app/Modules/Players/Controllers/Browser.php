<?php

declare (strict_types=1);
namespace App\Modules\Players\Controllers;

use App\Controllers\StaffController;
use App\Modules\Players\Models\PlayerModel;
use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\HTTP\Files\UploadedFile;
use RuntimeException;
use Throwable;
final class Browser extends StaffController
{
    public function index(): ResponseInterface|string
    {
        $filters = [
            'q' => $this->request->getGet('q'),
            'status' => $this->request->getGet('status'),
            'branch_id' => $this->request->getGet('branch_id'),
            'batch_id' => $this->request->getGet('batch_id'),
            'age_group' => $this->request->getGet('age_group'),
            'coach_id' => $this->request->getGet('coach_id'),
            'page' => $this->request->getGet('page'),
        ];

        try {
            return $this->page(
                'App\Modules\Players\Views\index',
                'Players',
                ['result' => service('player')->list($filters)],
            );
        } catch (Throwable $exception) {
            $reference = 'ERR-' . strtoupper(bin2hex(random_bytes(4)));

            log_message('error', 'Players list failed [{reference}]: {type}: {message}', [
                'reference' => $reference,
                'type' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return $this->response
                ->setStatusCode(503)
                ->setBody('Players list is temporarily unavailable. Support reference: ' . $reference . '.');
        }
    }

    public function goals(): ResponseInterface|string
    {
        try {
            return $this->page(
                'App\Modules\Players\Views\goals',
                'Player Goals',
                ['result' => service('player')->goalList($this->request->getGet())],
            );
        } catch (Throwable $e) {
            return $this->goalError($e);
        }
    }

    public function sessionPlans(): ResponseInterface|string
    {
        try {
            return $this->page(
                'App\\Modules\\Players\\Views\\session_plans',
                'Player Session Plans',
                [
                    'result' => service('playerSessionPlan')->list($this->request->getGet()),
                    'can_manage' => service('authorization')->can('player.session_plan.manage'),
                ],
            );
        } catch (Throwable $e) {
            return $this->sessionPlanError($e);
        }
    }

    public function sessionPlanCreatePage(?int $playerId = null): ResponseInterface|string
    {
        try {
            $playerId ??= (int) $this->request->getGet('player_id');
            if ($playerId < 1) {
                return $this->page(
                    'App\\Modules\\Players\\Views\\session_plan_player_selector',
                    'Create Session Plan',
                );
            }

            return $this->page(
                'App\\Modules\\Players\\Views\\session_plan_form',
                'Create Session Plan',
                ['plan' => null, 'player' => service('playerSessionPlan')->playerForCreate($playerId)],
            );
        } catch (Throwable $e) {
            return $this->sessionPlanError($e);
        }
    }

    public function sessionPlanPlayerSearch(): ResponseInterface
    {
        return $this->response->setJSON([
            'results' => service('playerSessionPlan')->searchCreateablePlayers(
                trim((string) $this->request->getGet('q')),
            ),
        ]);
    }

    public function sessionPlanCreate(): ResponseInterface|string
    {
        return $this->saveSessionPlan(null);
    }

    public function sessionPlanDetails(int $id): ResponseInterface|string
    {
        try {
            $plan = service('playerSessionPlan')->plan($id);

            return $this->page(
                'App\\Modules\\Players\\Views\\session_plan_show',
                'View Session Plan',
                [
                    'plan' => $plan,
                    'can_manage' => service('authorization')->can(
                        'player.session_plan.manage',
                        (string) $plan['branch_id'],
                    ),
                ],
            );
        } catch (Throwable $e) {
            return $this->sessionPlanError($e);
        }
    }

    public function sessionPlanEditPage(int $id): ResponseInterface|string
    {
        try {
            $plan = service('playerSessionPlan')->plan($id, true);

            return $this->page(
                'App\\Modules\\Players\\Views\\session_plan_form',
                'Edit Session Plan',
                ['plan' => $plan, 'player' => $plan],
            );
        } catch (Throwable $e) {
            return $this->sessionPlanError($e);
        }
    }

    public function sessionPlanUpdate(int $id): ResponseInterface|string
    {
        return $this->saveSessionPlan($id);
    }

    public function feedbackList(): ResponseInterface|string
    {
        try {
            return $this->page(
                'App\\Modules\\Players\\Views\\feedbacks',
                'Coach Feedback',
                [
                    'result' => service('playerFeedback')->list($this->request->getGet()),
                    'can_manage' => service('authorization')->can('player.feedback.manage'),
                ],
            );
        } catch (Throwable $e) {
            return $this->feedbackError($e);
        }
    }

    public function feedbackCreatePage(int $playerId): ResponseInterface|string
    {
        try {
            return $this->page(
                'App\\Modules\\Players\\Views\\feedback_form',
                'Add 3-Month Feedback',
                [
                    'feedback' => null,
                    'player' => service('playerFeedback')->playerForCreate($playerId),
                ],
            );
        } catch (Throwable $e) {
            return $this->feedbackError($e);
        }
    }

    public function feedbackCreateSelector(): ResponseInterface|string
    {
        return $this->page(
            'App\\Modules\\Players\\Views\\feedback_player_selector',
            'Add 3-Month Feedback',
        );
    }

    public function feedbackPlayerSearch(): ResponseInterface
    {
        $term = trim((string) $this->request->getGet('q'));

        return $this->response->setJSON([
            'results' => service('playerFeedback')->searchCreateablePlayers($term),
        ]);
    }

    public function annualFeedbackPlayerSearch(): ResponseInterface
    {
        $term = trim((string) $this->request->getGet('q'));

        return $this->response->setJSON([
            'results' => service('playerFeedback')->searchReportPlayers($term),
        ]);
    }

    public function feedbackCreate(): ResponseInterface|string
    {
        return $this->saveFeedback(null);
    }

    public function feedbackDetails(int $id): ResponseInterface|string
    {
        try {
            $feedback = service('playerFeedback')->feedback($id);

            return $this->page(
                'App\\Modules\\Players\\Views\\feedback_show',
                'Coach Feedback',
                [
                    'feedback' => $feedback,
                    'can_manage' => service('authorization')->can('player.feedback.manage', (string) $feedback['branch_id']),
                ],
            );
        } catch (Throwable $e) {
            return $this->feedbackError($e);
        }
    }

    public function feedbackEditPage(int $id): ResponseInterface|string
    {
        try {
            return $this->page(
                'App\\Modules\\Players\\Views\\feedback_form',
                'Edit Coach Feedback',
                ['feedback' => service('playerFeedback')->feedback($id, true)],
            );
        } catch (Throwable $e) {
            return $this->feedbackError($e);
        }
    }

    public function feedbackUpdate(int $id): ResponseInterface|string
    {
        return $this->saveFeedback($id);
    }

    public function annualFeedback(): ResponseInterface|string
    {
        try {
            $playerId = filter_var($this->request->getGet('player_id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $year = filter_var($this->request->getGet('year'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 2000, 'max_range' => 2100]]);
            if ($playerId === false || $year === false) {
                $selectedPlayer = null;
                $playerError = $playerId === false
                    && $this->request->getGet('player_id') !== null
                    ? 'Choose an authorized Player.'
                    : '';
                $yearError = $year === false
                    && $this->request->getGet('year') !== null
                    ? 'Choose a valid calendar year.'
                    : '';
                if ($playerId !== false) {
                    $selectedPlayer = service('playerFeedback')->reportPlayerContext((int) $playerId);
                }

                return $this->page(
                    'App\\Modules\\Players\\Views\\feedback_annual',
                    'Annual Feedback Report',
                    [
                        'report' => null,
                        'selected_player' => $selectedPlayer,
                        'selected_year' => $year !== false ? (int) $year : (int) date('Y'),
                        'player_error' => $playerError,
                        'year_error' => $yearError,
                    ],
                );
            }

            return $this->page(
                'App\\Modules\\Players\\Views\\feedback_annual',
                'Annual Feedback Report',
                ['report' => service('playerFeedback')->annual((int) $playerId, (int) $year)],
            );
        } catch (Throwable $e) {
            return $this->feedbackError($e);
        }
    }

    private function saveFeedback(?int $id): ResponseInterface|string
    {
        try {
            $input = $this->request->getPost();
            unset($input[csrf_token()]);
            $saved = service('playerFeedback')->save($input, $id);

            return redirect()->to('/player-feedback/' . $saved)->with('message', 'Coach feedback saved.');
        } catch (Throwable $e) {
            if (in_array($e->getMessage(), ['Forbidden', 'Coach is not assigned to this player.'], true)) {
                return $this->response->setStatusCode(403)->setBody(esc($this->safe($e)));
            }

            return redirect()->back()->withInput()->with('error', $this->safe($e));
        }
    }

    private function feedbackError(Throwable $e): ResponseInterface
    {
        $status = in_array($e->getMessage(), ['Forbidden', 'Coach is not assigned to this player.'], true)
            ? 403
            : 404;

        return $this->response->setStatusCode($status)->setBody(esc($this->safe($e)));
    }

    private function saveSessionPlan(?int $id): ResponseInterface|string
    {
        try {
            $input = $this->request->getPost();
            unset($input[csrf_token()]);
            $saved = service('playerSessionPlan')->save($input, $id);

            return redirect()->to('/player-session-plans/' . $saved)
                ->with('message', 'Session Plan saved.');
        } catch (Throwable $e) {
            $submittedPlayerId = filter_var(
                $this->request->getPost('player_id'),
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]],
            );
            $formRoute = $id === null && $submittedPlayerId !== false
                ? '/players/' . (int) $submittedPlayerId . '/session-plans/new'
                : ($id === null ? '/players' : '/player-session-plans/' . $id . '/edit');

            return redirect()->to($formRoute)
                ->withInput()
                ->with('error', $this->safe($e));
        }
    }

    private function sessionPlanError(Throwable $e): ResponseInterface
    {
        return $this->response
            ->setStatusCode(in_array($e->getMessage(), ['Forbidden', 'Coach is not assigned to this player.'], true) ? 403 : 404)
            ->setBody(esc($this->safe($e)));
    }

    public function goalDetails(int $id): ResponseInterface|string
    {
        try {
            return $this->page(
                'App\Modules\Players\Views\goal_show',
                'View Goal',
                ['goal' => service('player')->goalDetails($id)],
            );
        } catch (Throwable $e) {
            return $this->goalError($e);
        }
    }

    public function goalCreatePage(): ResponseInterface|string
    {
        try {
            $playerId = filter_var(
                $this->request->getGet('player_id'),
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]],
            );

            return $this->page(
                'App\Modules\Players\Views\goal_create',
                'Create New Goal',
                [
                    'player' => $playerId !== false
                        ? service('player')->goalPlayerContext((int) $playerId)
                        : null,
                ],
            );
        } catch (Throwable $e) {
            return $this->goalError($e);
        }
    }

    public function goalPlayerSearch(): ResponseInterface
    {
        return $this->response->setJSON([
            'results' => service('player')->searchGoalPlayers(
                trim((string) $this->request->getGet('q')),
            ),
        ]);
    }

    public function goalCreate(): ResponseInterface|string
    {
        try {
            $playerId = filter_var(
                $this->request->getPost('player_id'),
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]],
            );
            if ($playerId === false) {
                throw new RuntimeException('Invalid player goal.');
            }

            $input = $this->request->getPost();
            unset($input[csrf_token()]);
            $goalId = service('player')->saveGoal((int) $playerId, $input);

            return redirect()
                ->to('/player-goals/' . $goalId)
                ->with('message', 'Player goal saved.');
        } catch (Throwable $e) {
            if (in_array($e->getMessage(), ['Forbidden', 'Coach is not assigned to this player.'], true)) {
                return $this->response
                    ->setStatusCode(403)
                    ->setBody(esc($this->safe($e)));
            }

            $playerId = filter_var(
                $this->request->getPost('player_id'),
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]],
            );

            return redirect()
                ->to($playerId !== false ? '/player-goals/new?player_id=' . (int) $playerId : '/player-goals/new')
                ->withInput()
                ->with('error', $this->safe($e));
        }
    }

    public function goalEdit(int $id): ResponseInterface|string
    {
        try {
            return $this->page(
                'App\Modules\Players\Views\goal_edit',
                'Edit Goal',
                ['goal' => service('player')->goalDetails($id)],
            );
        } catch (Throwable $e) {
            return $this->goalError($e);
        }
    }

    public function goalEditSave(int $id): ResponseInterface|string
    {
        try {
            $goal = service('player')->goalDetails($id);
            service('player')->saveGoal((int) $goal['player_id'], $this->request->getPost());

            return redirect()
                ->to('/player-goals')
                ->with('message', 'Player goal saved.');
        } catch (Throwable $e) {
            if (in_array($e->getMessage(), ['Forbidden', 'Coach is not assigned to this player.'], true)) {
                return $this->response
                    ->setStatusCode(403)
                    ->setBody(esc($this->safe($e)));
            }

            return redirect()
                ->back()
                ->withInput()
                ->with('error', $this->safe($e));
        }
    }

    private function goalError(Throwable $e): ResponseInterface
    {
        $status = $e->getMessage() === 'Forbidden' ? 403 : 404;

        return $this->response
            ->setStatusCode($status)
            ->setBody(esc($this->safe($e)));
    }

    public function showPage(int $id): \CodeIgniter\HTTP\ResponseInterface|string
    {
        try {
            $player = service('player')->find($id);
            $branch = (string) $player['branch_id'];
            $canTransfer = service('player')->canManageBranchAssignment($id);
            $canBatch = service('player')->canManageBatchAssignment($id);
            $canCoach = service('player')->canManageCoachAssignment($id);
            $canGoal = service('player')->canSetGoal($id);

            return $this->page(
                'App\Modules\Players\Views\show',
                'Player profile',
                [
                    'player' => $player,
                    'can_update' => service('authorization')->can('player.update', $branch),
                    'can_transfer' => $canTransfer,
                    'can_batch' => $canBatch,
                    'can_coach' => $canCoach,
                    'can_goal' => $canGoal,
                    'can_view_session_plans' => service('authorization')->can(
                        'player.session_plan.view',
                        $branch,
                    ),
                    'can_manage_session_plans' => service('authorization')->can(
                        'player.session_plan.manage',
                        $branch,
                    ),
                    'can_view_feedback' => service('playerFeedback')->canViewPlayer($id),
                    'can_manage_feedback' => service('playerFeedback')->canManagePlayer($id),
                    'session_plans' => service('authorization')->can(
                        'player.session_plan.view',
                        $branch,
                    )
                        ? service('playerSessionPlan')->currentForPlayer($id)
                        : [],
                    'feedbacks' => service('playerFeedback')->canViewPlayer($id)
                        ? service('playerFeedback')->currentForPlayer($id)
                        : [],
                    'can_upload_photo' => service('authorization')->can('player.update', $branch)
                        && service('authorization')->can('document.upload', $branch),
                    'can_360' => service('authorization')->can('player360.view', $branch),
                    'can_documents' => service('authorization')->can('document.view', $branch),
                    'can_performance' => service('authorization')->can('performance.view', $branch),
                    'assignment_options' => service('player')->assignmentOptions(
                        (int) $player['branch_id'],
                        is_array($player['current_batch'] ?? null)
                            ? (int) $player['current_batch']['batch_id']
                            : null,
                    ),
                    'coach_correction' => $canCoach
                        ? service('player')->coachCorrectionForm($id)
                        : null,
                ],
            );
        } catch (Throwable $e) {
            return $this->response
                ->setStatusCode($e->getMessage() === 'Forbidden' ? 403 : 404)
                ->setBody(esc($this->safe($e)));
        }
    }
    public function goal(int $id): \CodeIgniter\HTTP\ResponseInterface|string
    {
        try {
            service('player')->saveGoal($id, $this->request->getPost());

            if ($this->expectsJson()) {
                return $this->response->setJSON(['updated' => true]);
            }

            return $this->done('/players/' . $id, 'Player goal saved.');
        } catch (Throwable $e) {
            if ($this->expectsJson()) {
                $status = in_array(
                    $e->getMessage(),
                    ['Forbidden', 'Coach is not assigned to this player.'],
                    true,
                ) ? 403 : 422;

                return $this->response
                    ->setStatusCode($status)
                    ->setJSON(['error' => $this->safe($e)]);
            }

            if (in_array(
                $e->getMessage(),
                ['Forbidden', 'Coach is not assigned to this player.'],
                true,
            )) {
                return $this->response
                    ->setStatusCode(403)
                    ->setBody(esc($this->safe($e)));
            }

            return $this->fail($e);
        }
    }
    public function canonical(int $id): \CodeIgniter\HTTP\RedirectResponse
    {
        return redirect()->to('/players/' . $id);
    }
    public function update(int $id): \CodeIgniter\HTTP\ResponseInterface|string
    {
        try {
            $photo = $this->request->getFile('profile_photo');

            if ($photo instanceof UploadedFile && $photo->getError() !== UPLOAD_ERR_NO_FILE) {
                service('document')->uploadPlayerPhoto($id, $photo);

                return $this->done('/players/' . $id, 'Player profile image uploaded.');
            }

            $d = $this->request->getPost();
            unset($d[csrf_token()]);
            service('player')->updateProfile(
                $id,
                $d,
                (string) ($d['expected_updated_at'] ?? ''),
                (int) ($d['expected_profile_revision'] ?? 0)
            );

            return $this->done('/players/' . $id, 'Player profile updated.');
        } catch (Throwable $e) {
            return $this->fail($e);
        }
    }
    public function branch(int $id): \CodeIgniter\HTTP\ResponseInterface|string
    {
        try {
            $d = $this->request->getPost();
            service('player')->transferBranch($id, (int) ($d['branch_id'] ?? 0), (string) ($d['effective_from'] ?? ''),
    (string) ($d['expected_updated_at'] ?? ''), (int) ($d['expected_profile_revision'] ?? 0));
            return $this->done('/players/' . $id, 'Player branch updated.');
        } catch (Throwable $e) {
            return $this->assignmentFail($e);
        }
    }
    public function batch(int $id): \CodeIgniter\HTTP\ResponseInterface|string
    {
        try {
            $d = $this->request->getPost();
            $effectiveTo = trim((string) ($d['effective_to'] ?? ''));
            $batchId = array_key_exists('batch_id', $d)
                ? ($d['batch_id'] !== '' ? (int) $d['batch_id'] : null)
                : (($d['current_batch_id'] ?? '') !== '' ? (int) $d['current_batch_id'] : null);
            service('player')->assignBatch($id, $batchId,
    (string) ($d['effective_from'] ?? ''), $effectiveTo === '' ? null : $effectiveTo,
    (string) ($d['expected_updated_at'] ?? ''),
    (int) ($d['expected_profile_revision'] ?? 0));
            return $this->done('/players/' . $id, 'Batch assignment updated.');
        } catch (Throwable $e) {
            return $this->assignmentFail($e);
        }
    }
    public function coaches(int $id): \CodeIgniter\HTTP\ResponseInterface|string
    {
        try {
            service('player')->updateCoachAssignments($id, $this->request->getPost());

            return $this->done('/players/' . $id, 'Player coaches updated.');
        } catch (Throwable $e) {
            return $this->assignmentFail($e);
        }
    }
    public function status(int $id): \CodeIgniter\HTTP\ResponseInterface|string
    {
        try {
            $d = $this->request->getPost();
            service('player')->changeStatus($id, (string) ($d['status'] ?? ''), $d['reason'] ?? null, (string) ($d['expected_updated_at'] ?? ''),
    (int) ($d['expected_profile_revision'] ?? 0));
            return $this->done('/players/' . $id, 'Player status updated.');
        } catch (Throwable $e) {
            return $this->fail($e);
        }
    }
    public function contact(int $id): \CodeIgniter\HTTP\ResponseInterface|string
    {
        try {
            service('player')->addContact($id, $this->request->getPost());
            return $this->done('/players/' . $id, 'Player contact added.');
        } catch (Throwable $e) {
            return $this->fail($e);
        }
    }
    private function done(string $url, string $msg): \CodeIgniter\HTTP\ResponseInterface|string
    {
        if ($this->expectsJson()) {
            return $this->response->setJSON([
                'updated' => true,
                'message' => $msg,
            ]);
        }

        if (!$this->browser()) {
            return $this->response->setJSON(['updated' => true]);
        }
        return redirect()->to($url)->with('message', $msg);
    }

    private function expectsJson(): bool
    {
        return str_contains(
            strtolower((string) $this->request->getHeaderLine('Accept')),
            'application/json',
        );
    }
    private function fail(Throwable $e): \CodeIgniter\HTTP\ResponseInterface|string
    {
        if ($e instanceof DatabaseException) {
            log_message('error', 'Player request database failure {class}: {detail}', [
                'class' => $e::class,
                'detail' => $e->getMessage(),
            ]);
        }

        if ($this->expectsJson() || !$this->browser()) {
            return $this->response->setStatusCode(422)->setJSON(['error' => 'Player request rejected.']);
        }
        return redirect()->back()->withInput()->with('error', $this->safe($e));
    }

    private function assignmentFail(Throwable $e): \CodeIgniter\HTTP\ResponseInterface|string
    {
        if ($this->expectsJson()) {
            return $this->response
                ->setStatusCode(422)
                ->setJSON(['error' => $this->safe($e)]);
        }

        if (in_array($e->getMessage(), ['Forbidden', 'Coach is not assigned to this player.'], true)) {
            return $this->response
                ->setStatusCode(403)
                ->setBody(esc($this->safe($e)));
        }

        return $this->fail($e);
    }
}
