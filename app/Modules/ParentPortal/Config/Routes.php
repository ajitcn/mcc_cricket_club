<?php
$routes->group('parent-portal', ['filter' => 'auth'], static function ($routes): void {
    $routes->get('/', '\\App\\Modules\\ParentPortal\\Controllers\\Browser::index', ['filter' => 'permission:parent_portal.view']);
    $routes->get('children/(:num)', '\\App\\Modules\\ParentPortal\\Controllers\\Browser::child/$1', ['filter' => 'permission:parent_portal.view']);
});
