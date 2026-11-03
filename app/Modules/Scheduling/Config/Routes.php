<?php

$routes->group('sessions', ['filter' => 'auth'], static function ($routes): void {
    $routes->get('/', '\\App\\Modules\\Scheduling\\Controllers\\Browser::index', ['filter' => 'permission:session.update']);
    $routes->get('new', '\\App\\Modules\\Scheduling\\Controllers\\Browser::createPage', ['filter' => 'permission:session.create']);
    $routes->get('availability', '\\App\\Modules\\Scheduling\\Controllers\\Browser::availability');
    $routes->get('(:num)/page', '\\App\\Modules\\Scheduling\\Controllers\\Browser::showPage/$1');
    $routes->get('(:num)/edit', '\\App\\Modules\\Scheduling\\Controllers\\Browser::editPage/$1', ['filter' => 'permission:session.update']);
    $routes->post('/', '\\App\\Modules\\Scheduling\\Controllers\\Browser::create', ['filter' => 'permission:session.create']);
    $routes->post('(:num)/edit', '\\App\\Modules\\Scheduling\\Controllers\\Browser::update/$1', ['filter' => 'permission:session.update']);
    $routes->post('(:num)/coach', '\\App\\Modules\\Scheduling\\Controllers\\Browser::reassignCoach/$1', ['filter' => 'permission:session.update']);
    $routes->post('(:num)/cancel', '\\App\\Modules\\Scheduling\\Controllers\\Browser::cancel/$1', ['filter' => 'permission:session.cancel']);
    $routes->post('(:num)/complete', '\\App\\Modules\\Scheduling\\Controllers\\Browser::complete/$1', ['filter' => 'permission:session.update']);
    $routes->post('(:num)/reschedule', '\\App\\Modules\\Scheduling\\Controllers\\Browser::reschedule/$1', ['filter' => 'permission:session.update']);
});
