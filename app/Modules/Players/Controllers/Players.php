<?php

declare (strict_types=1);
namespace App\Modules\Players\Controllers;

use App\Controllers\BaseAuthenticatedController;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;
final class Players extends BaseAuthenticatedController
{
    public function show(int $id): ResponseInterface
    {
        try {
            return $this->response->setJSON(service('player')->find($id));
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }
    }
    public function updateProfile(int $id): ResponseInterface
    {
        try {
            $input = $this->request->getJSON(true);
            $data = is_array($input) ? $input : (array) $this->request->getPost();
            service('player')->updateProfile($id, $data, (string) ($data['expected_updated_at'] ?? ''), (int) ($data['expected_profile_revision'] ?? 0));
            return $this->response->setJSON(['updated' => true]);
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }
    }
    public function transferBranch(int $id): ResponseInterface
    {
        try {
            $input = $this->request->getJSON(true);
            $data = is_array($input) ? $input : (array) $this->request->getPost();
            service('player')->transferBranch($id, (int) ($data['branch_id'] ?? 0), (string) ($data['effective_from'] ?? ''),
    (string) ($data['expected_updated_at'] ?? ''), (int) ($data['expected_profile_revision'] ?? 0));
            return $this->response->setJSON(['updated' => true]);
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }
    }
    public function assignBatch(int $id): ResponseInterface
    {
        try {
            $data = $this->input();
            $effectiveTo = trim((string) ($data['effective_to'] ?? ''));
            service('player')->assignBatch($id, array_key_exists('batch_id',
    $data) && $data['batch_id'] !== null ? (int) $data['batch_id'] : null, (string) ($data['effective_from'] ?? ''),
    $effectiveTo === '' ? null : $effectiveTo, (string) ($data['expected_updated_at'] ?? ''),
    (int) ($data['expected_profile_revision'] ?? 0));
            return $this->response->setJSON(['updated' => true]);
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }
    }
    public function assignCoach(int $id): ResponseInterface
    {
        try {
            $data = $this->input();
            service('player')->assignCoach($id, array_key_exists('coach_user_id',
    $data) && $data['coach_user_id'] !== null ? (int) $data['coach_user_id'] : null, (string) ($data['effective_from'] ?? ''),
    $data['effective_to'] ?? null, (string) ($data['expected_updated_at'] ?? ''), (int) ($data['expected_profile_revision'] ?? 0));
            return $this->response->setJSON(['updated' => true]);
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
        $status = match ($exception->getMessage()) {
            'Forbidden' => 403,
            'Not found.' => 404,
            'Update conflict.' => 409,
            default => 422,
        };
        return $this->response->setStatusCode($status)->setJSON(['error' => $status === 403 ? 'Forbidden.' : ($status === 404 ? 'Not found.' : ($status
    === 409 ? 'Update conflict.' : 'Player request rejected.'))]);
    }
}
