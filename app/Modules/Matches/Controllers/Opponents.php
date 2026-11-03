<?php

declare(strict_types=1);

namespace App\Modules\Matches\Controllers;

use App\Controllers\StaffController;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

final class Opponents extends StaffController
{
    public function index(): string
    {
        return $this->page('App\\Modules\\Matches\\Views\\opponents', 'Opponents', [
            'result' => service('opponent')->list($this->request->getGet()),
        ]);
    }

    public function createPage(): string
    {
        return $this->page('App\\Modules\\Matches\\Views\\opponent_form', 'Create opponent', [
            'opponent' => [],
            'branches' => service('opponent')->formOptions()['branches'],
            'editing' => false,
        ]);
    }

    public function editPage(int $id): ResponseInterface|string
    {
        try {
            $opponent = service('opponent')->find($id);
            return $this->page('App\\Modules\\Matches\\Views\\opponent_form', 'Edit opponent', [
                'opponent' => $opponent,
                'branches' => [],
                'editing' => true,
            ]);
        } catch (Throwable $exception) {
            $status = $exception->getMessage() === 'Forbidden' ? 403 : 404;
            $message = $exception->getMessage() === 'Not found.'
                ? 'Not found.'
                : $this->safe($exception);

            return $this->response->setStatusCode($status)->setBody(esc($message));
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

    private function save(?int $id): ResponseInterface|string
    {
        $data = $this->request->getPost();
        unset($data[csrf_token()]);
        $target = $id === null
            ? '/matches/opponents/new'
            : '/matches/opponents/' . $id . '/edit';

        try {
            $saved = service('opponent')->save($data, $id);
            return redirect()->to('/matches/opponents')->with('message', $id === null ? 'Opponent created.' : 'Opponent updated.');
        } catch (Throwable $exception) {
            return redirect()->to($target)
                ->withInput()
                ->with('error', $this->safe($exception))
                ->with('field_errors', $this->fieldErrors($exception));
        }
    }

    /** @return array<string, string> */
    private function fieldErrors(Throwable $exception): array
    {
        $message = $exception->getMessage();

        if (in_array($message, ['Forbidden', 'Invalid branch.'], true)) {
            return ['branch_id' => 'Select an authorized branch.'];
        }

        if (in_array($message, ['Invalid opponent.', 'Invalid opponent name.', 'Opponent name already exists in this branch.'], true)) {
            return ['name' => $message];
        }

        if ($message === 'Invalid active status.') {
            return ['is_active' => 'Select a valid active status.'];
        }

        return [];
    }
}
