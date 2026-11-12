<?php

declare(strict_types=1);

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

final class PortalAuth implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        try {
            if (service('portalAuth')->isAuthenticated()) {
                return null;
            }
        } catch (Throwable $exception) {
            log_message('error', 'Portal authentication check failed: {message}', [
                'message' => $exception->getMessage(),
            ]);
        }

        return redirect()->to('/student-login')->with('error', 'Authentication required.');
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
