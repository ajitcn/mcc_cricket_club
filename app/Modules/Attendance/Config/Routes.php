<?php
$routes->group('attendance', ['filter' => 'auth'], static function ($routes): void {
    $routes->get('/', '\\App\\Modules\\Attendance\\Controllers\\Attendance::index', ['filter' => 'permission:attendance.view']);
    $routes->get('sessions/(:num)', '\\App\\Modules\\Attendance\\Controllers\\Attendance::sessionPage/$1', ['filter' => 'permission:attendance.view']);
    $routes->get('records/(:num)', '\\App\\Modules\\Attendance\\Controllers\\Attendance::detail/$1', ['filter' => 'permission:attendance.view']);
    $routes->get('(:num)', '\\App\\Modules\\Attendance\\Controllers\\Attendance::show/$1', ['filter' => 'permission:attendance.view']);
    $routes->post('sessions/(:num)/players/(:num)', '\\App\\Modules\\Attendance\\Controllers\\Attendance::record/$1/$2', ['filter' => 'permission:attendance.record']);
    $routes->post('sessions/(:num)/save', '\\App\\Modules\\Attendance\\Controllers\\Attendance::save/$1', ['filter' => 'permission:attendance.record']);
    $routes->post('(:num)/correct', '\\App\\Modules\\Attendance\\Controllers\\Attendance::correct/$1', ['filter' => 'permission:attendance.correct']);
});
