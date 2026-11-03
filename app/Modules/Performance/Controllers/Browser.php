<?php

declare(strict_types=1);

namespace App\Modules\Performance\Controllers;

use App\Controllers\StaffController;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

final class Browser extends StaffController
{
    public function index(): string
    {
        return $this->page('App\\Modules\\Performance\\Views\\index', 'Performance', [
            'result' => service('performance')->list($this->request->getGet()),
        ]);
    }

    public function createPage(): string
    {
        return $this->page('App\\Modules\\Performance\\Views\\form', 'Record performance', [
            'record' => null,
            'formOptions' => service('performance')->formOptions(),
        ]);
    }

    public function create(): ResponseInterface|string
    {
        return $this->save(null);
    }

    public function showPage(int $id): ResponseInterface|string
    {
        try {
            return $this->page('App\\Modules\\Performance\\Views\\show', 'Performance record', [
                'record' => service('performance')->find($id),
            ]);
        } catch (Throwable $exception) {
            return $this->error($exception);
        }
    }

    public function editPage(int $id): ResponseInterface|string
    {
        try {
            return $this->page('App\\Modules\\Performance\\Views\\form', 'Edit performance', [
                'record' => service('performance')->find($id),
                'formOptions' => service('performance')->formOptions(),
            ]);
        } catch (Throwable $exception) {
            return $this->error($exception);
        }
    }

    public function update(int $id): ResponseInterface|string
    {
        return $this->save($id);
    }

    public function delete(int $id): ResponseInterface|string
    {
        try {
            service('performance')->delete($id);
            return redirect()->to('/performance')->with('message', 'Performance record removed.');
        } catch (Throwable $exception) {
            return $this->error($exception);
        }
    }

    public function approve(int $id): ResponseInterface|string
    {
        try {
            service('performance')->approve($id, (string) $this->request->getPost('reason'));

            return redirect()->to('/performance/' . $id)->with('message', 'Performance record approved.');
        } catch (Throwable $exception) {
            return redirect()->back()->withInput()->with('error', $this->safe($exception));
        }
    }

    private function save(?int $id): ResponseInterface|string
    {
        try {
            $data = $this->request->getPost();
            unset($data[csrf_token()]);
            $saved = service('performance')->save($data, $id);
            return redirect()->to('/performance/' . $saved)->with('message', 'Performance record saved.');
        } catch (Throwable $exception) {
            return redirect()->back()->withInput()->with('error', $this->safe($exception));
        }
    }

    private function error(Throwable $exception): ResponseInterface
    {
        return $this->response
            ->setStatusCode($exception->getMessage() === 'Forbidden' ? 403 : 404)
            ->setBody(esc($this->safe($exception)));
    }
}
