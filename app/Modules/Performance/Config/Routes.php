<?php

$routes->group('performance', ['filter' => 'auth'], static function ($routes): void {
    $routes->get('/', '\\App\\Modules\\Performance\\Controllers\\Browser::index', ['filter' => 'permission:performance.view']);
    $routes->get('new', '\\App\\Modules\\Performance\\Controllers\\Browser::createPage', ['filter' => 'permission:performance.create']);
    $routes->post('/', '\\App\\Modules\\Performance\\Controllers\\Browser::create', ['filter' => 'permission:performance.create']);
    $routes->get('(:num)', '\\App\\Modules\\Performance\\Controllers\\Browser::showPage/$1', ['filter' => 'permission:performance.view']);
    $routes->get('(:num)/edit', '\\App\\Modules\\Performance\\Controllers\\Browser::editPage/$1', ['filter' => 'permission:performance.update']);
    $routes->post('(:num)', '\\App\\Modules\\Performance\\Controllers\\Browser::update/$1', ['filter' => 'permission:performance.update']);
    $routes->post('(:num)/approve', '\\App\\Modules\\Performance\\Controllers\\Browser::approve/$1', ['filter' => 'permission:performance.update']);
    $routes->post('(:num)/delete', '\\App\\Modules\\Performance\\Controllers\\Browser::delete/$1', ['filter' => 'permission:performance.delete']);
});
