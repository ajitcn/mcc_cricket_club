<?php

declare (strict_types=1);
namespace App\Modules\CRM\Controllers;

use App\Controllers\BaseAuthenticatedController;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;
final class Leads extends BaseAuthenticatedController
{
    public function create(): ResponseInterface
    {
        try {
            return $this->response->setStatusCode(201)->setJSON(service('crm')->create($this->input()));
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }
    }
    public function show(int $id): ResponseInterface
    {
        try {
            $lead = service('crm')->find($id, (int) $this->request->getGet('branch_id'));
            return $lead === null ? $this->response->setStatusCode(404)->setJSON(['error' => 'Not found.']) : $this->response->setJSON($lead);
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }
    }
    public function assign(int $id): ResponseInterface
    {
        try {
            $data = $this->input();
            $owner = array_key_exists('owner_user_id', $data)
                ? ($data['owner_user_id'] === '' ? null : (int) $data['owner_user_id']) : 0;
            service('crm')->assign($id, (int) ($data['branch_id'] ?? 0), $owner);
            return $this->response->setJSON(['updated' => true]);
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }
    }
    public function status(int $id): ResponseInterface
    {
        try {
            $data = $this->input();
            service('crm')->status($id, (int) ($data['branch_id'] ?? 0), (string) ($data['status'] ?? ''), $data['reason'] ?? null);
            return $this->response->setJSON(['updated' => true]);
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }
    }
    public function followUp(int $id): ResponseInterface
    {
        try {
            $data = $this->input();
            service('crm')->createFollowUp($id, $data);
            return $this->response->setJSON(['created' => true]);
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }
    }
    private function input(): array
    {
        $json = $this->request->getJSON(true);
        return is_array($json) ? $json : (array) $this->request->getPost();
    }
    private function failure(Throwable $exception): ResponseInterface
    {
        if ($exception->getMessage() === 'Forbidden') {
            return $this->response->setStatusCode(403)->setJSON(['error' => 'Forbidden.']);
        }
        if ($exception->getMessage() === 'Not found.') {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'Not found.']);
        }
        return $this->response->setStatusCode(422)->setJSON(['error' => 'Lead request rejected.']);
    }
}
