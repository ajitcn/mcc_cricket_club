<?php

$routes->group('notifications', ['filter' => 'auth'], static function ($routes): void {
    $routes->get('/', '\\App\\Modules\\Notifications\\Controllers\\Browser::index', ['filter' => 'permission:notification.view']);
    $routes->post('create', '\\App\\Modules\\Notifications\\Controllers\\Browser::create', ['filter' => 'permission:notification.create']);
    $routes->post('(:num)/read', '\\App\\Modules\\Notifications\\Controllers\\Browser::read/$1', ['filter' => 'permission:notification.read']);
});
