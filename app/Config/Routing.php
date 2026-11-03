<?php

namespace Config;

use CodeIgniter\Config\Routing as BaseRouting;
class Routing extends BaseRouting
{
    public array $routeFiles = [APPPATH . 'Config/Routes.php'];
    public string $defaultNamespace = 'App\Controllers';
    public string $defaultController = 'Home';
    public string $defaultMethod = 'index';
    public bool $autoRoute = false;
    public bool $useControllerAttributes = true;
}
