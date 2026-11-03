<?php

$routes->group('player-360', ['filter' => 'auth'], static function ($routes): void {
    $routes->get('', '\\App\\Modules\\Player360\\Controllers\\Browser::index', [
        'filter' => 'permission:player360.view',
    ]);
    $routes->get('(:num)', '\\App\\Modules\\Player360\\Controllers\\Browser::show/$1', [
        'filter' => 'permission:player360.view',
    ]);
});
