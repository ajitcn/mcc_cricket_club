<?php

$routes->group('equipment', ['filter' => 'auth'], static function ($routes): void {
    $routes->get('/', '\\App\\Modules\\Equipment\\Controllers\\Browser::index', [
        'filter' => 'permission:equipment.view',
    ]);
    $routes->get('new', '\\App\\Modules\\Equipment\\Controllers\\Browser::createPage', [
        'filter' => 'permission:equipment.create',
    ]);
    $routes->get('assignments', '\\App\\Modules\\Equipment\\Controllers\\Browser::assignments', ['filter' => 'permission:equipment.view']);
    $routes->get('assignments/new', '\\App\\Modules\\Equipment\\Controllers\\Browser::assignmentPage', ['filter' => 'permission:equipment.update']);
    $routes->get('assignments/(:num)', '\\App\\Modules\\Equipment\\Controllers\\Browser::viewAssignmentPage/$1', ['filter' => 'permission:equipment.view']);
    $routes->get('assignments/(:num)/return', '\\App\\Modules\\Equipment\\Controllers\\Browser::returnAssignmentPage/$1', ['filter' => 'permission:equipment.update']);
    $routes->get('assignments/(:num)/edit', '\\App\\Modules\\Equipment\\Controllers\\Browser::editAssignmentPage/$1', ['filter' => 'permission:equipment.update']);
    $routes->post('assignments', '\\App\\Modules\\Equipment\\Controllers\\Browser::createAssignment', ['filter' => 'permission:equipment.update']);
    $routes->post('assignments/(:num)', '\\App\\Modules\\Equipment\\Controllers\\Browser::updateAssignment/$1', ['filter' => 'permission:equipment.update']);
    $routes->post('assignments/(:num)/return', '\\App\\Modules\\Equipment\\Controllers\\Browser::returnAssignment/$1', ['filter' => 'permission:equipment.update']);
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
