<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Controllers;

use App\Controllers\StaffController;

final class Browser extends StaffController
{
    public function index(): string
    {
        return $this->page(
            'App\\Modules\\Marketing\\Views\\index',
            'Marketing Dashboard',
            ['result' => service('marketing')->dashboard($this->request->getGet())]
        );
    }
}
