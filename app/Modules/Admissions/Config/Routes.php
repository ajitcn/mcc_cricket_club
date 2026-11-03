<?php

$routes->group('admissions', ['filter' => 'auth'], static function ($routes): void {
    $routes->get('followups', '\\App\\Modules\\Admissions\\Controllers\\FollowUps::index', ['filter' => 'permission:admission.view']);
    $routes->get('followups/new', '\\App\\Modules\\Admissions\\Controllers\\FollowUps::newPage', ['filter' => 'permission:admission.create']);
    $routes->post('followups', '\\App\\Modules\\Admissions\\Controllers\\FollowUps::create', ['filter' => 'permission:admission.create']);
    $routes->get('followups/(:num)', '\\App\\Modules\\Admissions\\Controllers\\FollowUps::show/$1', ['filter' => 'permission:admission.view']);
    $routes->get('followups/(:num)/edit', '\\App\\Modules\\Admissions\\Controllers\\FollowUps::editPage/$1', ['filter' => 'permission:admission.update']);
    $routes->post('followups/(:num)/edit', '\\App\\Modules\\Admissions\\Controllers\\FollowUps::update/$1', ['filter' => 'permission:admission.update']);
    $routes->get('/', '\\App\\Modules\\Admissions\\Controllers\\Browser::index', ['filter' => 'permission:admission.view']);
    $routes->get('fee-installments', '\\App\\Modules\\Admissions\\Controllers\\Browser::feeInstallments', ['filter' => 'permission:admission.view']);
    $routes->get('fee-installments/(:segment)/payment', '\\App\\Modules\\Admissions\\Controllers\\Browser::paymentMissingId/$1', ['filter' => 'permission:admission.update']);
    $routes->get('fee-installments/(:segment)/(:segment)/payment', '\\App\\Modules\\Admissions\\Controllers\\Browser::paymentPage/$1/$2', ['filter' => 'permission:admission.update']);
    $routes->post('fee-installments/(:segment)/payment', '\\App\\Modules\\Admissions\\Controllers\\Browser::paymentMissingId/$1', ['filter' => 'permission:admission.update']);
    $routes->post('fee-installments/(:segment)/(:segment)/payment', '\\App\\Modules\\Admissions\\Controllers\\Browser::recordPayment/$1/$2', ['filter' => 'permission:admission.update']);
    $routes->get('new', '\\App\\Modules\\Admissions\\Controllers\\Browser::newPage', ['filter' => 'permission:admission.create']);
    $routes->post('/', '\\App\\Modules\\Admissions\\Controllers\\Browser::createDirect', ['filter' => 'permission:admission.create']);
    $routes->get('(:num)/page', '\\App\\Modules\\Admissions\\Controllers\\Browser::showPage/$1', ['filter' => 'permission:admission.view']);
    $routes->get('(:num)/download', '\\App\\Modules\\Admissions\\Controllers\\Browser::download/$1', ['filter' => 'permission:admission.view']);
    $routes->get('(:num)/edit', '\\App\\Modules\\Admissions\\Controllers\\Browser::editPage/$1', ['filter' => 'permission:admission.update']);
    $routes->post('(:num)/edit', '\\App\\Modules\\Admissions\\Controllers\\Browser::edit/$1', ['filter' => 'permission:admission.update']);
    $routes->get('(:num)/update-coaches', '\\App\\Modules\\Admissions\\Controllers\\Browser::updateCoachesPage/$1', ['filter' => 'permission:admission.update']);
    $routes->post('(:num)/update-coaches', '\\App\\Modules\\Admissions\\Controllers\\Browser::updateCoaches/$1', ['filter' => 'permission:admission.update']);
    $routes->get('(:num)/renew', '\\App\\Modules\\Admissions\\Controllers\\Browser::renewPage/$1', ['filter' => 'permission:admission.update']);
    $routes->post('(:num)/renew', '\\App\\Modules\\Admissions\\Controllers\\Browser::renew/$1', ['filter' => 'permission:admission.update']);
    $routes->get('(:num)/complete', '\\App\\Modules\\Admissions\\Controllers\\Browser::completePage/$1', ['filter' => 'permission:admission.update']);
    $routes->post('(:num)/complete', '\\App\\Modules\\Admissions\\Controllers\\Browser::complete/$1', ['filter' => 'permission:admission.update']);
    $routes->post('from-lead/(:num)', '\\App\\Modules\\Admissions\\Controllers\\Browser::create/$1', ['filter' => 'permission:admission.create']);
    $routes->post('(:num)/checklist', '\\App\\Modules\\Admissions\\Controllers\\Browser::checklist/$1', ['filter' => 'permission:admission.update']);
    $routes->post('(:num)/status', '\\App\\Modules\\Admissions\\Controllers\\Browser::status/$1', ['filter' => 'permission:admission.update']);
    $routes->post('(:num)/convert', '\\App\\Modules\\Admissions\\Controllers\\Browser::convert/$1', ['filter' => 'permission:player.convert']);
    $routes->post('(:num)/documents', '\\App\\Modules\\Admissions\\Controllers\\Browser::uploadDocument/$1', ['filter' => 'permission:admission.update']);
    $routes->post('(:num)/documents/(:num)/remove', '\\App\\Modules\\Admissions\\Controllers\\Browser::removeDocument/$1/$2', ['filter' => 'permission:admission.update']);
});

$routes->group('admission-masters', ['filter' => 'auth'], static function ($routes): void {
    $routes->get('age_group', '\\App\\Modules\\Admissions\\Controllers\\MasterBrowser::index/age_group', [
        'filter' => 'permission:admission.view',
    ]);
    $routes->get('age_group/new', '\\App\\Modules\\Admissions\\Controllers\\MasterBrowser::createPage/age_group', [
        'filter' => 'permission:admission.update',
    ]);
    $routes->post('age_group', '\\App\\Modules\\Admissions\\Controllers\\MasterBrowser::save/age_group', [
        'filter' => 'permission:admission.update',
    ]);
    $routes->get('age_group/(:num)/edit', '\\App\\Modules\\Admissions\\Controllers\\MasterBrowser::editPage/age_group/$1', [
        'filter' => 'permission:admission.update',
    ]);
    $routes->post('age_group/(:num)', '\\App\\Modules\\Admissions\\Controllers\\MasterBrowser::save/age_group/$1', [
        'filter' => 'permission:admission.update',
    ]);
    $routes->post('age_group/(:num)/status/(:num)', '\\App\\Modules\\Admissions\\Controllers\\MasterBrowser::setActive/age_group/$1/$2', [
        'filter' => 'permission:admission.update',
    ]);
});
