<?php

declare(strict_types=1);

namespace App\Modules\Batches\Controllers;

use App\Controllers\StaffController;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

final class Browser extends StaffController
{
    public function index(): string|ResponseInterface
    {
        try {
            return $this->page(
                'App\\Modules\\Batches\\Views\\index',
                'Batches',
                [
                    'batches' => service('batch')->list(),
                    'canCreate' => service('authorization')->can('batch.create'),
                ]
            );
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }
    }

    public function createPage(): string|ResponseInterface
    {
        try {
            return $this->page(
                'App\\Modules\\Batches\\Views\\create',
                'Create batch',
                [
                    'canCreate' => service('authorization')->can('batch.create'),
                ]
            );
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }
    }

    public function create(): ResponseInterface|string
    {
        $input = $this->request->getPost();
        unset($input[csrf_token()]);
        $rules = [
            'name' => 'required|max_length[150]',
            'is_active' => 'permit_empty|in_list[0,1]',
        ];

        if (! $this->validateData($input, $rules)) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Enter a batch name and select an Active or Inactive status.');
        }

        try {
            service('batch')->create($input);

            return redirect()->to('/batches')->with('message', 'Batch created.');
        } catch (Throwable $exception) {
            return redirect()->back()
                ->withInput()
                ->with('error', $this->safe($exception));
        }
    }

    public function editPage(int $id): string|ResponseInterface
    {
        try {
            $context = service('batch')->editForm($id);
            $context['mode'] = 'edit';
            $context['canCreate'] = true;

            return $this->page(
                'App\\Modules\\Batches\\Views\\create',
                'Edit batch',
                $context,
            );
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }
    }

    public function update(int $id): ResponseInterface|string
    {
        $input = $this->request->getPost();
        unset($input[csrf_token()]);
        $rules = [
            'name' => 'required|max_length[150]',
            'is_active' => 'permit_empty|in_list[0,1]',
        ];

        if (! $this->validateData($input, $rules)) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Enter a batch name and select an Active or Inactive status.');
        }

        try {
            service('batch')->update($id, $input);

            return redirect()->to('/batches')->with('message', 'Batch updated.');
        } catch (Throwable $exception) {
            return redirect()->back()
                ->withInput()
                ->with('error', $this->safe($exception));
        }
    }

    private function failure(Throwable $exception): ResponseInterface
    {
        return $this->response
            ->setStatusCode(match ($exception->getMessage()) {
                'Forbidden' => 403,
                'Not found.' => 404,
                default => 500,
            })
            ->setBody(esc($this->safe($exception)));
    }
}
