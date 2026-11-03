<?php

$routes->group('programs', ['filter' => 'auth'], static function ($routes): void {
    $routes->get('/', '\\App\\Modules\\Programs\\Controllers\\Browser::index', [
        'filter' => 'permission:program.view',
    ]);
    $routes->get('new', '\\App\\Modules\\Programs\\Controllers\\Browser::createPage', [
        'filter' => 'permission:program.create',
    ]);
    $routes->get('(:num)/edit', '\\App\\Modules\\Programs\\Controllers\\Browser::editPage/$1', [
        'filter' => 'permission:program.create',
    ]);
    $routes->post('/', '\\App\\Modules\\Programs\\Controllers\\Browser::create', [
        'filter' => 'permission:program.create',
    ]);
    $routes->post('(:num)', '\\App\\Modules\\Programs\\Controllers\\Browser::update/$1', [
        'filter' => 'permission:program.create',
    ]);
});
