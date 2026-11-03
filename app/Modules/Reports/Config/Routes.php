<?php

$routes->group('reports', ['filter' => 'auth'], static function ($routes): void {
    $routes->get('/', '\\App\\Modules\\Reports\\Controllers\\Browser::index', [
        'filter' => 'permission:report.view',
    ]);
    $routes->get('(:segment)', '\\App\\Modules\\Reports\\Controllers\\Browser::show/$1', [
        'filter' => 'permission:report.view',
    ]);
    $routes->get('(:segment)/csv', '\\App\\Modules\\Reports\\Controllers\\Browser::csv/$1', [
        'filter' => 'permission:report.view',
    ]);
});
