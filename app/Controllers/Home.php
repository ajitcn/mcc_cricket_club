<?php
declare(strict_types=1);
namespace App\Controllers;
final class Home extends BaseController
{
    public function index(): string
    {
        if (! service('auth')->isLoggedIn()) return view('login', ['error'=>session()->getFlashdata('error'),'email'=>session()->getFlashdata('email')]);
        return view('dashboard', array_merge(
            ['nav' => service('navigation')->dashboard()],
            service('dashboard')->data($this->request->getGet())
        ));
    }
}
