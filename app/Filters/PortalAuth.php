<?php

declare(strict_types=1);

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

final class PortalAuth implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (service('portalAuth')->isAuthenticated()) {
            return null;
        }
        return redirect()->to('/student-login')->with('error', 'Authentication required.');
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
