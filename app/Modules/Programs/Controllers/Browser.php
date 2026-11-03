<?php

declare(strict_types=1);

namespace App\Modules\Programs\Controllers;

use App\Controllers\StaffController;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

final class Browser extends StaffController
{
    public function index(): string|ResponseInterface
    {
        try {
            return $this->page(
                'App\\Modules\\Programs\\Views\\index',
                'Programs',
                [
                    'programs' => service('program')->list(),
                    'canCreate' => service('authorization')->can('program.create'),
                    'canEdit' => service('authorization')->can('program.create'),
                ]
            );
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }
    }

    public function createPage(): string
    {
        return $this->page('App\\Modules\\Programs\\Views\\create', 'Create program');
    }

    public function editPage(int $id): string|ResponseInterface
    {
        try {
            $context = service('program')->editForm($id);
            $context['mode'] = 'edit';

            return $this->page(
                'App\\Modules\\Programs\\Views\\create',
                'Edit program',
                $context,
            );
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }
    }

    public function create(): ResponseInterface|string
    {
        $input = $this->request->getPost();
        unset($input[csrf_token()]);

        if (! $this->validateData($input, [
            'name' => 'required|max_length[150]',
            'description' => 'permit_empty|max_length[5000]',
        ])) {
            return redirect()->back()
                ->withInput()
                ->with('field_errors', $this->validator->getErrors())
                ->with('error', 'Enter a program name up to 150 characters and a description up to 5,000 characters.');
        }

        try {
            service('program')->create($input);

            return redirect()->to('/programs')->with('message', 'Program created.');
        } catch (Throwable $exception) {
            $message = $this->safe($exception);
            $fieldErrors = match ($exception->getMessage()) {
                'Invalid program.' => ['name' => $message],
                'Invalid program description.',
                'Program description must not exceed 5,000 characters.' => ['description' => $message],
                default => [],
            };

            return redirect()->back()
                ->withInput()
                ->with('field_errors', $fieldErrors)
                ->with('error', $message);
        }
    }

    public function update(int $id): ResponseInterface|string
    {
        $input = $this->request->getPost();
        unset($input[csrf_token()]);

        if (! $this->validateData($input, [
            'name' => 'required|max_length[150]',
            'description' => 'permit_empty|max_length[5000]',
        ])) {
            return redirect()->back()
                ->withInput()
                ->with('field_errors', $this->validator->getErrors())
                ->with('error', 'Enter a program name up to 150 characters and a description up to 5,000 characters.');
        }

        try {
            service('program')->update($id, $input);

            return redirect()->to('/programs')->with('message', 'Program updated.');
        } catch (Throwable $exception) {
            $message = $this->safe($exception);
            $fieldErrors = match ($exception->getMessage()) {
                'Invalid program.', 'Program already exists.' => ['name' => $message],
                'Invalid program description.',
                'Program description must not exceed 5,000 characters.' => ['description' => $message],
                default => [],
            };

            return redirect()->back()
                ->withInput()
                ->with('field_errors', $fieldErrors)
                ->with('error', $message);
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
