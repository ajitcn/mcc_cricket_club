<?php

$routes->group('coaches', ['filter' => 'auth'], static function ($routes): void {
    $routes->get('/', '\\App\\Modules\\Coaches\\Controllers\\Browser::index', [
        'filter' => 'permission:coach.view',
    ]);
    $routes->get('create', '\\App\\Modules\\Coaches\\Controllers\\Browser::createPage', [
        'filter' => 'permission:coach.create',
    ]);
    $routes->post('/', '\\App\\Modules\\Coaches\\Controllers\\Browser::create', [
        'filter' => 'permission:coach.create',
    ]);
    $routes->get('(:num)', '\\App\\Modules\\Coaches\\Controllers\\Browser::showPage/$1', [
        'filter' => 'permission:coach.view',
    ]);
    $routes->get('(:num)/sessions', '\\App\\Modules\\Coaches\\Controllers\\Browser::sessionsPage/$1', [
        'filter' => 'permission:coach.view',
    ]);
    $routes->get('(:num)/sessions/(:num)', '\\App\\Modules\\Coaches\\Controllers\\Browser::sessionDetailPage/$1/$2', [
        'filter' => 'permission:coach.view',
    ]);
    $routes->get('(:num)/players', '\\App\\Modules\\Coaches\\Controllers\\Browser::playersPage/$1', [
        'filter' => 'permission:coach.view',
    ]);
    $routes->get('(:num)/edit', '\\App\\Modules\\Coaches\\Controllers\\Browser::editPage/$1', [
        'filter' => 'permission:coach.update',
    ]);
    $routes->post('(:num)', '\\App\\Modules\\Coaches\\Controllers\\Browser::update/$1', [
        'filter' => 'permission:coach.update',
    ]);
    $routes->post('(:num)/branches', '\\App\\Modules\\Coaches\\Controllers\\Browser::branch/$1', [
        'filter' => 'permission:coach.assign',
    ]);
    $routes->post('(:num)/leave', '\\App\\Modules\\Coaches\\Controllers\\Browser::leave/$1', [
        'filter' => 'permission:coach.update',
    ]);
    $routes->post('(:num)/availability', '\\App\\Modules\\Coaches\\Controllers\\Browser::availability/$1', [
        'filter' => 'permission:coach.update',
    ]);
    $routes->post('(:num)/feedback', '\\App\\Modules\\Coaches\\Controllers\\Browser::feedback/$1', [
        'filter' => 'permission:coach.update',
    ]);
    $routes->post('leave/(:num)/cancel', '\\App\\Modules\\Coaches\\Controllers\\Browser::cancelLeave/$1', [
        'filter' => 'auth',
    ]);
    $routes->post('leave/(:num)/approve', '\\App\\Modules\\Coaches\\Controllers\\Browser::approveLeave/$1', [
        'filter' => 'permission:coach.update',
    ]);
    $routes->post('leave/(:num)/reject', '\\App\\Modules\\Coaches\\Controllers\\Browser::rejectLeave/$1', [
        'filter' => 'permission:coach.update',
    ]);
    $routes->post('availability/(:num)/cancel', '\\App\\Modules\\Coaches\\Controllers\\Browser::cancelAvailability/$1', [
        'filter' => 'permission:coach.update',
    ]);
});
