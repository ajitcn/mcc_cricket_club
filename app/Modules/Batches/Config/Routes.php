<?php

$routes->group('batches', ['filter' => 'auth'], static function ($routes): void {
    $routes->get('/', '\\App\\Modules\\Batches\\Controllers\\Browser::index', [
        'filter' => 'permission:batch.view',
    ]);
    $routes->get('new', '\\App\\Modules\\Batches\\Controllers\\Browser::createPage', [
        'filter' => 'permission:batch.create',
    ]);
    $routes->get('(:num)/edit', '\\App\\Modules\\Batches\\Controllers\\Browser::editPage/$1', [
        'filter' => 'permission:batch.create',
    ]);
    $routes->post('/', '\\App\\Modules\\Batches\\Controllers\\Browser::create', [
        'filter' => 'permission:batch.create',
    ]);
    $routes->post('(:num)', '\\App\\Modules\\Batches\\Controllers\\Browser::update/$1', [
        'filter' => 'permission:batch.create',
    ]);
});
