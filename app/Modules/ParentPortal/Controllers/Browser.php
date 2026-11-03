<?php

declare(strict_types=1);

namespace App\Modules\ParentPortal\Controllers;

use App\Controllers\StaffController;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

final class Browser extends StaffController
{
    public function index(): string
    {
        return $this->page('App\\Modules\\ParentPortal\\Views\\index', 'Parent portal', [
            'dashboard' => service('parentPortal')->dashboard(),
        ]);
    }

    public function child(int $id): ResponseInterface|string
    {
        try {
            return $this->page('App\\Modules\\ParentPortal\\Views\\child', 'Child detail', [
                'detail' => service('parentPortal')->child($id),
            ]);
        } catch (Throwable $exception) {
            return $this->response
                ->setStatusCode($exception->getMessage() === 'Forbidden' ? 403 : 404)
                ->setBody(esc($this->safe($exception)));
        }
    }
}
