<?php

$routes->group('fitness', ['filter' => 'auth'], static function ($routes): void {
    $routes->get('/', '\\App\\Modules\\Fitness\\Controllers\\Browser::index', [
        'filter' => 'permission:fitness.view',
    ]);
    $routes->get('new', '\\App\\Modules\\Fitness\\Controllers\\Browser::createPage', [
        'filter' => 'permission:fitness.create',
    ]);
    $routes->get('player-search', '\\App\\Modules\\Fitness\\Controllers\\Browser::playerSearch', [
        'filter' => 'permission:fitness.create',
    ]);
    $routes->get('players/(:num)', '\\App\\Modules\\Fitness\\Controllers\\Browser::player/$1', [
        'filter' => 'permission:fitness.view',
    ]);
    $routes->get('assessments/(:num)/edit', '\\App\\Modules\\Fitness\\Controllers\\Browser::editAssessment/$1', [
        'filter' => 'permission:fitness.update',
    ]);
    $routes->post('players/(:num)/assessments', '\\App\\Modules\\Fitness\\Controllers\\Browser::assessment/$1', [
        'filter' => 'permission:fitness.create',
    ]);
    $routes->post('players/(:num)/plans', '\\App\\Modules\\Fitness\\Controllers\\Browser::plan/$1', [
        'filter' => 'permission:fitness.plan.create',
    ]);
    $routes->post('assessments/(:num)', '\\App\\Modules\\Fitness\\Controllers\\Browser::updateAssessment/$1', [
        'filter' => 'permission:fitness.update',
    ]);
    $routes->post('plans/(:num)', '\\App\\Modules\\Fitness\\Controllers\\Browser::updatePlan/$1', [
        'filter' => 'permission:fitness.plan.update',
    ]);
    $routes->post('assessments/(:num)/delete', '\\App\\Modules\\Fitness\\Controllers\\Browser::deleteAssessment/$1', ['filter' => 'permission:fitness.delete']);
    $routes->post('plans/(:num)/delete', '\\App\\Modules\\Fitness\\Controllers\\Browser::deletePlan/$1', ['filter' => 'permission:fitness.plan.delete']);
});
