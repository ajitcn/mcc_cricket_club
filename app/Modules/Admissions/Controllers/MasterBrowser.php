<?php

declare(strict_types=1);

namespace App\Modules\Admissions\Controllers;

use App\Controllers\StaffController;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

final class MasterBrowser extends StaffController
{
    public function index(string $type): string|ResponseInterface
    {
        try {
            $definition = service('admissionMaster')->definitions()[$type] ?? null;
            if (! is_array($definition)) {
                throw new \RuntimeException('Invalid Admission master.');
            }

            return $this->page('App\\Modules\\Admissions\\Views\\masters\\index', 'Admission ' . $definition['label'], [
                'type' => $type,
                'definition' => $definition,
                'rows' => service('admissionMaster')->list($type),
                'canManage' => service('authorization')->can('admission.update'),
            ]);
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }
    }

    public function createPage(string $type): string|ResponseInterface
    {
        return $this->form($type, []);
    }

    public function editPage(string $type, int $id): string|ResponseInterface
    {
        try {
            $definition = service('admissionMaster')->definitions()[$type] ?? null;
            $rows = service('admissionMaster')->list($type);
            foreach ($rows as $row) {
                if ((int) ($row['id'] ?? 0) === $id) {
                    return $this->form($type, $row);
                }
            }
            throw new \RuntimeException('Not found.');
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }
    }

    public function save(string $type, ?int $id = null): ResponseInterface
    {
        try {
            $input = $this->request->getPost();
            unset($input[csrf_token()]);
            if ($id === null) {
                service('admissionMaster')->create($type, $input);
            } else {
                service('admissionMaster')->update($type, $id, $input);
            }

            return redirect()->to('/admission-masters/' . $type)->with('message', 'Admission master saved.');
        } catch (Throwable $exception) {
            $target = '/admission-masters/' . $type . ($id === null ? '/new' : '/' . $id . '/edit');

            return redirect()->to($target)
                ->withInput()
                ->with('error', $this->safe($exception));
        }
    }

    public function setActive(string $type, int $id, int $active): ResponseInterface
    {
        try {
            service('admissionMaster')->setActive($type, $id, $active === 1);

            return redirect()->to('/admission-masters/' . $type)->with('message', 'Admission master status updated.');
        } catch (Throwable $exception) {
            return redirect()->back()->with('error', $this->safe($exception));
        }
    }

    private function form(string $type, array $row): string|ResponseInterface
    {
        try {
            $definition = service('admissionMaster')->definitions()[$type] ?? null;
            if (! is_array($definition)) {
                throw new \RuntimeException('Invalid Admission master.');
            }

            return $this->page('App\\Modules\\Admissions\\Views\\masters\\form', 'Admission ' . $definition['label'], [
                'type' => $type,
                'definition' => $definition,
                'row' => $row,
                'programs' => service('program')->list(),
                'batches' => service('batch')->list(),
                'branches' => service('branchContext')->authorizedBranches(true),
            ]);
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }
    }

    private function failure(Throwable $exception): ResponseInterface
    {
        return $this->response
            ->setStatusCode($exception->getMessage() === 'Forbidden' ? 403 : 404)
            ->setBody(esc($this->safe($exception)));
    }
}
