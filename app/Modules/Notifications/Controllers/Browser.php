<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Controllers;

use App\Controllers\StaffController;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

final class Browser extends StaffController
{
    public function index(): string
    {
        return $this->page('App\\Modules\\Notifications\\Views\\index', 'Notifications', [
            'result' => service('notification')->list($this->request->getGet()),
        ]);
    }

    public function read(int $id): ResponseInterface
    {
        $state = trim((string) $this->request->getPost('state'));
        $category = trim((string) $this->request->getPost('category'));
        $location = $this->notificationLocation($state, $category);

        try {
            service('notification')->markRead($id);
        } catch (Throwable $exception) {
            return redirect()
                ->to($location)
                ->with('error', $this->safe($exception));
        }

        return redirect()->to($location);
    }

    private function notificationLocation(string $state, string $category): string
    {
        $filters = [];

        if (in_array($state, ['read', 'unread'], true)) {
            $filters['state'] = $state;
        }

        if ($category !== '') {
            $filters['category'] = $category;
        }

        $query = http_build_query($filters);

        return site_url('notifications') . ($query !== '' ? '?' . $query : '');
    }
}
