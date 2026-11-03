<?php

$routes->group('marketing', ['filter' => 'auth'], static function ($routes): void {
    $routes->get('/', '\\App\\Modules\\Marketing\\Controllers\\Browser::index', [
        'filter' => 'permission:marketing.view',
    ]);
});
