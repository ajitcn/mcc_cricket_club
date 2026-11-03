<?php

$routes->group('matches', ['filter' => 'auth'], static function ($routes): void {
    $routes->get('/', '\\App\\Modules\\Matches\\Controllers\\Browser::index', ['filter' => 'permission:match.view']);
    $routes->get('new', '\\App\\Modules\\Matches\\Controllers\\Browser::createPage', ['filter' => 'permission:match.create']);
    $routes->post('/', '\\App\\Modules\\Matches\\Controllers\\Browser::create', ['filter' => 'permission:match.create']);
    $routes->post('(:num)/complete', '\\App\\Modules\\Matches\\Controllers\\Browser::complete/$1', ['filter' => 'permission:match.update']);
    $routes->get('(:num)/performance', '\\App\\Modules\\Matches\\Controllers\\Browser::performancePage/$1', ['filter' => 'permission:match.view']);
    $routes->get('(:num)/performance/template', '\\App\\Modules\\Matches\\Controllers\\Browser::performanceTemplate/$1', ['filter' => 'permission:match.view']);
    $routes->post('(:num)/performance/import', '\\App\\Modules\\Matches\\Controllers\\Browser::importPerformance/$1', ['filter' => 'auth']);
    $routes->post('(:num)/performance/(:num)', '\\App\\Modules\\Matches\\Controllers\\Browser::savePlayerPerformance/$1/$2', ['filter' => 'auth']);
    $routes->post('(:num)/performance', '\\App\\Modules\\Matches\\Controllers\\Browser::savePerformance/$1', ['filter' => 'auth']);
    $routes->get('(:num)/edit', '\\App\\Modules\\Matches\\Controllers\\Browser::editPage/$1', ['filter' => 'permission:match.update']);
    $routes->get('(:num)', '\\App\\Modules\\Matches\\Controllers\\Browser::showPage/$1', ['filter' => 'permission:match.view']);
    $routes->post('(:num)', '\\App\\Modules\\Matches\\Controllers\\Browser::update/$1', ['filter' => 'permission:match.update']);
});

$routes->group('matches/opponents', ['filter' => 'auth'], static function ($routes): void {
    $routes->get('/', '\\App\\Modules\\Matches\\Controllers\\Opponents::index', ['filter' => 'permission:opponent.view']);
    $routes->get('new', '\\App\\Modules\\Matches\\Controllers\\Opponents::createPage', ['filter' => 'permission:opponent.create']);
    $routes->post('/', '\\App\\Modules\\Matches\\Controllers\\Opponents::create', ['filter' => 'permission:opponent.create']);
    $routes->get('(:num)/edit', '\\App\\Modules\\Matches\\Controllers\\Opponents::editPage/$1', ['filter' => 'permission:opponent.update']);
    $routes->post('(:num)', '\\App\\Modules\\Matches\\Controllers\\Opponents::update/$1', ['filter' => 'permission:opponent.update']);
});
