<?php

$routes->group('development', ['filter' => 'auth'], static function ($routes): void {
    $routes->get('/', '\\App\\Modules\\Development\\Controllers\\Browser::index', ['filter' => 'permission:development.view']);
    $routes->get('new', '\\App\\Modules\\Development\\Controllers\\Browser::createPage', ['filter' => 'permission:development.create']);
    $routes->get('player-search', '\\App\\Modules\\Development\\Controllers\\Browser::playerSearch', ['filter' => 'permission:development.create']);
    $routes->post('/', '\\App\\Modules\\Development\\Controllers\\Browser::create', ['filter' => 'permission:development.create']);
    $routes->get('(:num)', '\\App\\Modules\\Development\\Controllers\\Browser::showPage/$1', ['filter' => 'permission:development.view']);
    $routes->get('(:num)/edit', '\\App\\Modules\\Development\\Controllers\\Browser::editPage/$1', ['filter' => 'permission:development.update']);
    $routes->post('(:num)', '\\App\\Modules\\Development\\Controllers\\Browser::update/$1', ['filter' => 'permission:development.update']);
    $routes->post('(:num)/goals', '\\App\\Modules\\Development\\Controllers\\Browser::goal/$1', ['filter' => 'permission:development.update']);
    $routes->post('goals/(:num)/progress', '\\App\\Modules\\Development\\Controllers\\Browser::progress/$1', ['filter' => 'permission:development.progress']);
});
