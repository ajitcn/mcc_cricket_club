<?php
$routes->get('auth/login', '\\App\\Modules\\Auth\\Controllers\\Auth::loginPage');

$routes->post('auth/login', '\\App\\Modules\\Auth\\Controllers\\Auth::login');
$routes->post('auth/logout', '\\App\\Modules\\Auth\\Controllers\\Auth::logout');
$routes->get('auth/password', '\\App\\Modules\\Auth\\Controllers\\Auth::passwordPage', ['filter' => 'auth']);
$routes->post('auth/password', '\\App\\Modules\\Auth\\Controllers\\Auth::updatePassword', ['filter' => 'auth']);
$routes->post('auth/active-branch', '\\App\\Modules\\Auth\\Controllers\\Auth::selectActiveBranch', [
    'filter' => 'auth',
]);
$routes->post('auth/recovery/request', '\\App\\Modules\\Auth\\Controllers\\Auth::recoveryRequest');
$routes->post('auth/recovery/reset', '\\App\\Modules\\Auth\\Controllers\\Auth::recoveryReset');
