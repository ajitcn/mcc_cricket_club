<?php

declare(strict_types=1);

namespace App\Modules\Development\Controllers;

use App\Controllers\StaffController;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

final class Browser extends StaffController
{
    public function index(): string
    {
        return $this->page(
            'App\\Modules\\Development\\Views\\index',
            'Development',
            [
                'result' => service('development')->list($this->request->getGet()),
            ]
        );
    }

    public function createPage(): string
    {
        $branchId = $this->request->getGet('branch_id');

        return $this->page(
            'App\\Modules\\Development\\Views\\form',
            'Create development plan',
            [
                'plan' => null,
                'options' => service('development')->formOptions(
                    is_numeric($branchId) ? (int) $branchId : null,
                ),
            ]
        );
    }

    public function playerSearch(): ResponseInterface
    {
        return $this->response->setJSON([
            'players' => service('development')->playersForSearch(
                (string) $this->request->getGet('q'),
                is_numeric($this->request->getGet('branch_id')) ? (int) $this->request->getGet('branch_id') : null,
            ),
        ]);
    }

    public function editPage(int $id): ResponseInterface|string
    {
        try {
            $development = service('development');
            $plan = $development->editable($id);

            return $this->page(
                'App\\Modules\\Development\\Views\\form',
                'Edit development plan',
                [
                    'plan' => $plan,
                    'options' => $development->formOptions((int) $plan['branch_id']),
                ]
            );
        } catch (Throwable $exception) {
            return $this->error($exception);
        }
    }

    public function showPage(int $id): ResponseInterface|string
    {
        try {
            return $this->page(
                'App\\Modules\\Development\\Views\\show',
                'Development detail',
                [
                    'plan' => service('development')->find($id),
                ]
            );
        } catch (Throwable $exception) {
            return $this->error($exception);
        }
    }

    public function create(): ResponseInterface|string
    {
        return $this->save(null);
    }

    public function update(int $id): ResponseInterface|string
    {
        return $this->save($id);
    }

    public function goal(int $id): ResponseInterface|string
    {
        try {
            $data = $this->request->getPost();
            unset($data[csrf_token()]);
            service('development')->goal($id, $data);

            return redirect()->to('/development/' . $id)
                ->with('message', 'Development goal added.');
        } catch (Throwable $exception) {
            return $this->detailFailure($id, $exception);
        }
    }

    public function progress(int $id): ResponseInterface|string
    {
        $planId = null;

        try {
            $development = service('development');
            $planId = $development->planIdForGoal($id);
            $data = $this->request->getPost();
            unset($data[csrf_token()]);
            $development->progress($id, $data);

            return redirect()->to('/development/' . $planId)
                ->with('message', 'Development progress recorded.');
        } catch (Throwable $exception) {
            return $this->detailFailure($planId, $exception);
        }
    }

    private function save(?int $id): ResponseInterface|string
    {
        try {
            $data = $this->request->getPost();
            unset($data[csrf_token()]);
            $saved = service('development')->save($data, $id);

            return redirect()->to('/development/' . $saved)
                ->with('message', 'Development plan saved.');
        } catch (Throwable $exception) {
            $formRoute = $id === null
                ? '/development/new'
                : '/development/' . $id . '/edit';

            return redirect()->to($formRoute)
                ->withInput()
                ->with('field_errors', $this->fieldErrors($exception))
                ->with('error', $this->safe($exception));
        }
    }

    /** @return array<string, string> */
    private function fieldErrors(Throwable $exception): array
    {
        return match ($exception->getMessage()) {
            'Invalid development goal.' => [
                'goal' => 'Enter a goal.',
            ],
            'Invalid development date.' => [
                'target_date' => 'Enter a valid target date.',
            ],
            'Invalid development progress.' => [
                'progress_value' => 'Enter a valid numeric progress value.',
            ],
            'A current development goal version is required.' => [
                'version' => 'Reload this Development detail page before recording progress.',
            ],
            'Player is not eligible.' => [
                'player_id' => 'Select an eligible Player for this branch.',
            ],
            'Invalid coach.' => [
                'coach_user_id' => 'Select an eligible Coach for this branch.',
            ],
            'Invalid development plan.' => [
                'title' => 'Enter a valid plan title.',
                'status' => 'Select a valid plan status.',
            ],
            default => [],
        };
    }

    private function detailFailure(?int $planId, Throwable $exception): ResponseInterface
    {
        if ($planId === null || in_array($exception->getMessage(), ['Forbidden', 'Not found.'], true)) {
            return $this->error($exception);
        }

        return redirect()->to('/development/' . $planId)
            ->withInput()
            ->with('field_errors', $this->fieldErrors($exception))
            ->with('error', $this->safe($exception));
    }

    private function error(Throwable $exception): ResponseInterface
    {
        return $this->response
            ->setStatusCode($exception->getMessage() === 'Forbidden' ? 403 : 404)
            ->setBody(esc($this->safe($exception)));
    }
}
