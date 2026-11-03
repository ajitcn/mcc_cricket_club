<?php

$routes->group('tournaments', ['filter' => 'auth'], static function ($routes): void {
    $routes->get('/', '\\App\\Modules\\Tournaments\\Controllers\\Browser::index', ['filter' => 'permission:tournament.view']);
    $routes->get('new', '\\App\\Modules\\Tournaments\\Controllers\\Browser::createPage', ['filter' => 'permission:tournament.create']);
    $routes->post('/', '\\App\\Modules\\Tournaments\\Controllers\\Browser::create', ['filter' => 'permission:tournament.create']);
    $routes->get('(:num)', '\\App\\Modules\\Tournaments\\Controllers\\Browser::showPage/$1', ['filter' => 'permission:tournament.view']);
    $routes->get('(:num)/edit', '\\App\\Modules\\Tournaments\\Controllers\\Browser::editPage/$1', ['filter' => 'permission:tournament.update']);
    $routes->post('(:num)', '\\App\\Modules\\Tournaments\\Controllers\\Browser::update/$1', ['filter' => 'permission:tournament.update']);
    $routes->post('(:num)/fixtures', '\\App\\Modules\\Tournaments\\Controllers\\Browser::fixture/$1', ['filter' => 'permission:tournament.update']);
    $routes->get('(:num)/fixtures', '\\App\\Modules\\Tournaments\\Controllers\\Browser::fixtures/$1', ['filter' => 'permission:tournament.view']);
    $routes->get('(:num)/fixtures/new', '\\App\\Modules\\Tournaments\\Controllers\\Browser::fixtureCreatePage/$1', ['filter' => 'permission:tournament.update']);
    $routes->get('(:num)/fixtures/match-options', '\\App\\Modules\\Tournaments\\Controllers\\Browser::fixtureMatchOptions/$1', ['filter' => 'permission:tournament.update']);
    $routes->get('(:num)/fixtures/(:num)', '\\App\\Modules\\Tournaments\\Controllers\\Browser::fixtureShowPage/$1/$2', ['filter' => 'permission:tournament.view']);
    $routes->get('(:num)/fixtures/(:num)/edit', '\\App\\Modules\\Tournaments\\Controllers\\Browser::fixtureEditPage/$1/$2', ['filter' => 'permission:tournament.update']);
    $routes->post('(:num)/fixtures/(:num)', '\\App\\Modules\\Tournaments\\Controllers\\Browser::fixtureUpdate/$1/$2', ['filter' => 'permission:tournament.update']);
    $routes->post('(:num)/fixtures/(:num)/postpone', '\\App\\Modules\\Tournaments\\Controllers\\Browser::fixturePostpone/$1/$2', ['filter' => 'permission:tournament.update']);
    $routes->post('(:num)/fixtures/(:num)/cancel', '\\App\\Modules\\Tournaments\\Controllers\\Browser::fixtureCancel/$1/$2', ['filter' => 'permission:tournament.update']);
    $routes->post('(:num)/fixtures/(:num)/complete', '\\App\\Modules\\Tournaments\\Controllers\\Browser::fixtureComplete/$1/$2', ['filter' => 'permission:tournament.update']);
});
