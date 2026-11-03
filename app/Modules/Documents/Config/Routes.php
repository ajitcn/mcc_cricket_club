<?php

$routes->group('documents', ['filter' => 'auth'], static function ($routes): void {
    $routes->get(
        '/',
        '\\App\\Modules\\Documents\\Controllers\\Browser::index',
        ['filter' => 'permission:document.view']
    );
    $routes->get(
        '(:num)',
        '\\App\\Modules\\Documents\\Controllers\\Browser::show/$1',
        ['filter' => 'permission:document.view']
    );
    $routes->get(
        '(:num)/download',
        '\\App\\Modules\\Documents\\Controllers\\Browser::download/$1',
        ['filter' => 'permission:document.download']
    );
    $routes->get(
        '(:num)/preview',
        '\\App\\Modules\\Documents\\Controllers\\Browser::preview/$1',
        ['filter' => 'permission:document.download']
    );
    $routes->post(
        '/',
        '\\App\\Modules\\Documents\\Controllers\\Browser::upload',
        ['filter' => 'permission:document.upload']
    );
    $routes->post(
        '(:num)/verify',
        '\\App\\Modules\\Documents\\Controllers\\Browser::verify/$1',
        ['filter' => 'permission:document.upload']
    );
});
