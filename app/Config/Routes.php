<?php

use CodeIgniter\Router\RouteCollection;
/** @var RouteCollection $routes */
$routes->get('/', 'Home::index');
foreach (glob(APPPATH . 'Modules/*/Config/Routes.php') ?: [] as $moduleRoute) {
    require $moduleRoute;
}
