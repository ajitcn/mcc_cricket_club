<?php

declare(strict_types=1);

namespace App\Modules\Fitness\Controllers;

use App\Controllers\StaffController;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

final class Browser extends StaffController
{
    public function index(): string
    {
        $filters = [
            'q' => $this->request->getGet('q'),
            'branch_id' => $this->request->getGet('branch_id'),
            'from' => $this->request->getGet('from'),
            'to' => $this->request->getGet('to'),
            'page' => $this->request->getGet('page'),
        ];
        return $this->page('App\\Modules\\Fitness\\Views\\index', 'Fitness assessments', [
            'result' => service('fitness')->list($filters),
        ]);
    }

    public function createPage(): string
    {
        $selectedPlayerId = (int) $this->request->getGet('player_id');
        $query = trim((string) $this->request->getGet('q'));
        $players = [];

        return $this->page('App\\Modules\\Fitness\\Views\\new', 'New fitness assessment', [
            'players' => $players,
            'age_groups' => service('fitness')->ageGroups(),
            'selected_player' => service('fitness')->authorizedPlayerForAssessment($selectedPlayerId),
            'query' => $query,
        ]);
    }

    public function playerSearch(): ResponseInterface
    {
        $query = trim((string) $this->request->getGet('q'));
        if (mb_strlen($query) < 2) {
            return $this->response->setJSON(['players' => []]);
        }

        return $this->response->setJSON([
            'players' => service('fitness')->playersForNewAssessment($query),
        ]);
    }

    public function player(int $id): ResponseInterface|string
    {
        try {
            return $this->page('App\\Modules\\Fitness\\Views\\player', 'Player fitness', [
                'result' => service('fitness')->player($id),
                'age_groups' => service('fitness')->ageGroups(),
            ]);
        } catch (Throwable $exception) {
            return $this->response->setStatusCode($exception->getMessage() === 'Forbidden' ? 403 : 404)
                ->setBody(esc($this->safe($exception)));
        }
    }

    public function editAssessment(int $id): ResponseInterface|string
    {
        try {
            return $this->page('App\\Modules\\Fitness\\Views\\edit', 'Edit fitness assessment', [
                'assessment' => service('fitness')->assessmentForEdit($id),
                'age_groups' => service('fitness')->ageGroups(),
            ]);
        } catch (Throwable $exception) {
            return $this->response->setStatusCode($exception->getMessage() === 'Forbidden' ? 403 : 404)
                ->setBody(esc($this->safe($exception)));
        }
    }

    public function assessment(int $id): ResponseInterface|string
    {
        try {
            $data = $this->request->getPost();
            unset($data[csrf_token()]);
            service('fitness')->saveAssessment($id, $data);

            return redirect()->to('/fitness/players/' . $id)->with('message', 'Fitness assessment saved.');
        } catch (Throwable $exception) {
            return redirect()->to('/fitness/new?player_id=' . $id)
                ->withInput()
                ->with('error', $this->safe($exception));
        }
    }

    public function updateAssessment(int $id): ResponseInterface|string
    {
        try {
            $assessment = db_connect()->table('fitness_assessments')->where('id', $id)->get()->getRowArray();
            if (!is_array($assessment)) {
                throw new \RuntimeException('Not found.');
            }
            service('fitness')->saveAssessment((int) $assessment['player_id'], $this->request->getPost(), $id);
            return redirect()->to('/fitness/players/' . (int) $assessment['player_id'])
                ->with('message', 'Fitness assessment updated.');
        } catch (Throwable $exception) {
            return redirect()->to('/fitness/assessments/' . $id . '/edit')
                ->withInput()
                ->with('error', $this->safe($exception));
        }
    }

    public function plan(int $id): ResponseInterface|string
    {
        return $this->save(fn(array $data): int => service('fitness')->savePlan($id, $data));
    }

    public function updatePlan(int $id): ResponseInterface|string
    {
        try {
            $plan = db_connect()->table('fitness_plans')->where('id', $id)->get()->getRowArray();
            if (!is_array($plan)) {
                throw new \RuntimeException('Not found.');
            }
            service('fitness')->savePlan((int) $plan['player_id'], $this->request->getPost(), $id);
            return redirect()->back()->with('message', 'Fitness plan updated.');
        } catch (Throwable $exception) {
            return redirect()->back()->withInput()->with('error', $this->safe($exception));
        }
    }

    public function deleteAssessment(int $id): ResponseInterface|string
    {
        return $this->deleteRecord(function () use ($id): void {
            service('fitness')->deleteAssessment($id);
        });
    }

    public function deletePlan(int $id): ResponseInterface|string
    {
        return $this->deleteRecord(function () use ($id): void {
            service('fitness')->deletePlan($id);
        });
    }

    private function deleteRecord(callable $callback): ResponseInterface|string
    {
        try {
            $callback();
            return redirect()->back()->with('message', 'Fitness record removed.');
        } catch (Throwable $exception) {
            return redirect()->back()->withInput()->with('error', $this->safe($exception));
        }
    }

    private function save(callable $callback): ResponseInterface|string
    {
        try {
            $data = $this->request->getPost();
            unset($data[csrf_token()]);
            $callback($data);
            return redirect()->back()->with('message', 'Fitness record saved.');
        } catch (Throwable $exception) {
            return redirect()->back()->withInput()->with('error', $this->safe($exception));
        }
    }
}
