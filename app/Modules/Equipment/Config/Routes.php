<?php

$routes->group('equipment', ['filter' => 'auth'], static function ($routes): void {
    $routes->get('/', '\\App\\Modules\\Equipment\\Controllers\\Browser::index', [
        'filter' => 'permission:equipment.view',
    ]);
    $routes->get('new', '\\App\\Modules\\Equipment\\Controllers\\Browser::createPage', [
        'filter' => 'permission:equipment.create',
    ]);
    $routes->post('/', '\\App\\Modules\\Equipment\\Controllers\\Browser::create', [
        'filter' => 'permission:equipment.create',
    ]);
    $routes->get('(:num)/edit', '\\App\\Modules\\Equipment\\Controllers\\Browser::editPage/$1', [
        'filter' => 'permission:equipment.update',
    ]);
    $routes->post('(:num)', '\\App\\Modules\\Equipment\\Controllers\\Browser::update/$1', [
        'filter' => 'permission:equipment.update',
    ]);
    $routes->get('(:num)/delete', '\\App\\Modules\\Equipment\\Controllers\\Browser::deletePage/$1', [
        'filter' => 'permission:equipment.delete',
    ]);
    $routes->post('(:num)/delete', '\\App\\Modules\\Equipment\\Controllers\\Browser::delete/$1', [
        'filter' => 'permission:equipment.delete',
    ]);
});
