<?php

$routes->group('players', ['filter' => 'auth'], static function ($routes): void {
    $routes->get('/', '\\App\\Modules\\Players\\Controllers\\Browser::index', ['filter' => 'permission:player.view']);
    $routes->get('(:num)/page', '\\App\\Modules\\Players\\Controllers\\Browser::canonical/$1', ['filter' => 'permission:player.view']);
    $routes->get('(:num)', '\\App\\Modules\\Players\\Controllers\\Browser::showPage/$1', ['filter' => 'permission:player.view']);
    $routes->get('(:num)/session-plans/new', '\\App\\Modules\\Players\\Controllers\\Browser::sessionPlanCreatePage/$1', ['filter' => 'permission:player.session_plan.manage']);
    $routes->get('(:num)/feedback/new', '\\App\\Modules\\Players\\Controllers\\Browser::feedbackCreatePage/$1', ['filter' => 'permission:player.feedback.manage']);
    $routes->post('(:num)/goal', '\\App\\Modules\\Players\\Controllers\\Browser::goal/$1', ['filter' => 'permission:player.goal.set']);
    $routes->post('(:num)/profile', '\\App\\Modules\\Players\\Controllers\\Browser::update/$1', ['filter' => 'permission:player.update']);
    $routes->post('(:num)/branch', '\\App\\Modules\\Players\\Controllers\\Browser::branch/$1', ['filter' => 'permission:player.view']);
    $routes->post('(:num)/batch', '\\App\\Modules\\Players\\Controllers\\Browser::batch/$1', ['filter' => 'permission:player.view']);
    $routes->post('(:num)/coaches', '\\App\\Modules\\Players\\Controllers\\Browser::coaches/$1', ['filter' => 'permission:player.view']);
    $routes->post('(:num)/status', '\\App\\Modules\\Players\\Controllers\\Browser::status/$1', ['filter' => 'permission:player.update']);
    $routes->post('(:num)/contacts', '\\App\\Modules\\Players\\Controllers\\Browser::contact/$1', ['filter' => 'permission:player.update']);
});

$routes->group('player-feedback', ['filter' => 'auth'], static function ($routes): void {
    $routes->get('/', '\\App\\Modules\\Players\\Controllers\\Browser::feedbackList', ['filter' => 'permission:player.feedback.view']);
    $routes->get('new', '\\App\\Modules\\Players\\Controllers\\Browser::feedbackCreateSelector', ['filter' => 'permission:player.feedback.manage']);
    $routes->get('player-search', '\\App\\Modules\\Players\\Controllers\\Browser::feedbackPlayerSearch', ['filter' => 'permission:player.feedback.manage']);
    $routes->get('annual-player-search', '\\App\\Modules\\Players\\Controllers\\Browser::annualFeedbackPlayerSearch', ['filter' => 'permission:player.feedback.view']);
    $routes->post('/', '\\App\\Modules\\Players\\Controllers\\Browser::feedbackCreate', ['filter' => 'permission:player.feedback.manage']);
    $routes->get('annual', '\\App\\Modules\\Players\\Controllers\\Browser::annualFeedback', ['filter' => 'permission:player.feedback.view']);
    $routes->get('(:num)', '\\App\\Modules\\Players\\Controllers\\Browser::feedbackDetails/$1', ['filter' => 'permission:player.feedback.view']);
    $routes->get('(:num)/edit', '\\App\\Modules\\Players\\Controllers\\Browser::feedbackEditPage/$1', ['filter' => 'permission:player.feedback.manage']);
    $routes->post('(:num)', '\\App\\Modules\\Players\\Controllers\\Browser::feedbackUpdate/$1', ['filter' => 'permission:player.feedback.manage']);
});

$routes->group('player-session-plans', ['filter' => 'auth'], static function ($routes): void {
    $routes->get('/', '\\App\\Modules\\Players\\Controllers\\Browser::sessionPlans', ['filter' => 'permission:player.session_plan.view']);
    $routes->get('new', '\\App\\Modules\\Players\\Controllers\\Browser::sessionPlanCreatePage', ['filter' => 'permission:player.session_plan.manage']);
    $routes->get('player-search', '\\App\\Modules\\Players\\Controllers\\Browser::sessionPlanPlayerSearch', ['filter' => 'permission:player.session_plan.manage']);
    $routes->post('/', '\\App\\Modules\\Players\\Controllers\\Browser::sessionPlanCreate', ['filter' => 'permission:player.session_plan.manage']);
    $routes->get('(:num)', '\\App\\Modules\\Players\\Controllers\\Browser::sessionPlanDetails/$1', ['filter' => 'permission:player.session_plan.view']);
    $routes->get('(:num)/edit', '\\App\\Modules\\Players\\Controllers\\Browser::sessionPlanEditPage/$1', ['filter' => 'permission:player.session_plan.manage']);
    $routes->post('(:num)', '\\App\\Modules\\Players\\Controllers\\Browser::sessionPlanUpdate/$1', ['filter' => 'permission:player.session_plan.manage']);
});

$routes->group('player-goals', ['filter' => 'auth'], static function ($routes): void {
    $routes->get('/', '\\App\\Modules\\Players\\Controllers\\Browser::goals', ['filter' => 'permission:player.goal.set']);
    $routes->get('new', '\\App\\Modules\\Players\\Controllers\\Browser::goalCreatePage', ['filter' => 'permission:player.goal.set']);
    $routes->get('player-search', '\\App\\Modules\\Players\\Controllers\\Browser::goalPlayerSearch', ['filter' => 'permission:player.goal.set']);
    $routes->post('/', '\\App\\Modules\\Players\\Controllers\\Browser::goalCreate', ['filter' => 'permission:player.goal.set']);
    $routes->get('(:num)', '\\App\\Modules\\Players\\Controllers\\Browser::goalDetails/$1', ['filter' => 'permission:player.goal.set']);
    $routes->get('(:num)/edit', '\\App\\Modules\\Players\\Controllers\\Browser::goalEdit/$1', ['filter' => 'permission:player.goal.set']);
    $routes->post('(:num)/edit', '\\App\\Modules\\Players\\Controllers\\Browser::goalEditSave/$1', ['filter' => 'permission:player.goal.set']);
});
