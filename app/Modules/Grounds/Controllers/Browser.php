<?php

declare(strict_types=1);

namespace App\Modules\Grounds\Controllers;

use App\Controllers\StaffController;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

final class Browser extends StaffController
{
    public function index(): string
    {
        return $this->page('App\\Modules\\Grounds\\Views\\index', 'Grounds', [
            'result' => service('ground')->list($this->request->getGet()),
        ]);
    }

    public function createPage(): string
    {
        return $this->page('App\\Modules\\Grounds\\Views\\form', 'Create ground', [
            'ground' => [],
            'isEdit' => false,
            'branches' => service('ground')->availableBranchesForCreation(),
        ]);
    }

    public function editPage(int $id): ResponseInterface|string
    {
        try {
            return $this->page(
                'App\\Modules\\Grounds\\Views\\form',
                'Edit ground',
                [
                    'ground' => service('ground')->find($id),
                    'isEdit' => true,
                ]
            );
        } catch (Throwable $exception) {
            return $this->response
                ->setStatusCode($exception->getMessage() === 'Forbidden' ? 403 : 404)
                ->setBody(esc($this->safe($exception)));
        }
    }

    public function create(): ResponseInterface|string
    {
        return $this->save(null, 'Ground created.');
    }

    public function showPage(int $id): ResponseInterface|string
    {
        try {
            return $this->page('App\\Modules\\Grounds\\Views\\show', 'Ground detail', ['ground' => service('ground')->find($id)]);
        } catch (Throwable $exception) {
            return $this->response->setStatusCode($exception->getMessage() === 'Forbidden' ? 403 : 404)->setBody(esc($this->safe($exception)));
        }
    }

    public function update(int $id): ResponseInterface|string
    {
        return $this->save($id, 'Ground updated.');
    }

    public function maintenance(int $id): ResponseInterface|string
    {
        try {
            service('ground')->addMaintenance($id, $this->request->getPost());
            return redirect()->to('/grounds/' . $id)->with('message', 'Maintenance period added.');
        } catch (Throwable $exception) {
            return redirect()->back()->withInput()->with('error', $this->safe($exception));
        }
    }

    private function save(?int $id, string $message): ResponseInterface|string
    {
        try {
            $data = $this->request->getPost();
            unset($data[csrf_token()]);
            $saved = service('ground')->save($data, $id);
            return redirect()->to('/grounds/' . $saved)->with('message', $message);
        } catch (Throwable $exception) {
            return redirect()->back()->withInput()->with('error', $this->safe($exception));
        }
    }
}
