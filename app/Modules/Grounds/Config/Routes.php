<?php

$routes->group('grounds', ['filter' => 'auth'], static function ($routes): void {
    $routes->get('/', '\\App\\Modules\\Grounds\\Controllers\\Browser::index', ['filter' => 'permission:ground.view']);
    $routes->get('new', '\\App\\Modules\\Grounds\\Controllers\\Browser::createPage', ['filter' => 'permission:ground.create']);
    $routes->post('/', '\\App\\Modules\\Grounds\\Controllers\\Browser::create', ['filter' => 'permission:ground.create']);
    $routes->get('(:num)', '\\App\\Modules\\Grounds\\Controllers\\Browser::showPage/$1', ['filter' => 'permission:ground.view']);
    $routes->get('(:num)/edit', '\\App\\Modules\\Grounds\\Controllers\\Browser::editPage/$1', ['filter' => 'permission:ground.update']);
    $routes->post('(:num)', '\\App\\Modules\\Grounds\\Controllers\\Browser::update/$1', ['filter' => 'permission:ground.update']);
    $routes->post('(:num)/maintenance', '\\App\\Modules\\Grounds\\Controllers\\Browser::maintenance/$1', ['filter' => 'permission:ground.maintenance']);
});
