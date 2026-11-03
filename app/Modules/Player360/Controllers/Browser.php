<?php

declare(strict_types=1);

namespace App\Modules\Player360\Controllers;

use App\Controllers\StaffController;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

final class Browser extends StaffController
{
    public function index(): string
    {
        $query = trim((string) $this->request->getGet('q'));

        return $this->page(
            'App\\Modules\\Player360\\Views\\index',
            'Player 360°',
            [
                'result' => service('player')->list([
                    'q' => $query,
                    'page' => $this->request->getGet('page'),
                ]),
            ],
        );
    }

    public function show(int $id): ResponseInterface|string
    {
        try {
            return $this->page(
                'App\\Modules\\Player360\\Views\\show',
                'Player 360°',
                ['result' => service('player360')->view($id)]
            );
        } catch (Throwable $exception) {
            return $this->response
                ->setStatusCode($exception->getMessage() === 'Forbidden' ? 403 : 404)
                ->setBody(esc($this->safe($exception)));
        }
    }
}
