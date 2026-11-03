<?php

$routes->group('crm', ['filter' => 'auth'], static function ($routes): void {
    $routes->get('trials', '\\App\\Modules\\Trials\\Controllers\\Browser::index', ['filter' => 'auth']);
    $routes->get('trials/(:num)', '\\App\\Modules\\Trials\\Controllers\\Browser::show/$1');
    $routes->post('trials/(:num)/admission-test', '\\App\\Modules\\AdmissionTests\\Controllers\\Browser::start/$1', ['filter' => 'permission:admission.create']);
    $routes->post(
        'trials/(:num)/media',
        '\\App\\Modules\\Trials\\Controllers\\Browser::uploadMedia/$1',
        ['filter' => 'auth'],
    );
    $routes->get(
        'trials/(:num)/media/(:num)/download',
        '\\App\\Modules\\Trials\\Controllers\\Browser::downloadMedia/$1/$2',
        ['filter' => 'auth'],
    );
    $routes->get(
        'trials/(:num)/media/(:num)/preview',
        '\\App\\Modules\\Trials\\Controllers\\Browser::previewMedia/$1/$2',
        ['filter' => 'auth'],
    );
    $routes->get('trials/from-lead/(:num)', '\\App\\Modules\\Trials\\Controllers\\Browser::bookPage/$1', ['filter' => 'permission:trial.book']);
    $routes->post('trials/from-lead/(:num)', '\\App\\Modules\\Trials\\Controllers\\Browser::book/$1', ['filter' => 'permission:trial.book']);
    $routes->post('trials/(:num)/reschedule', '\\App\\Modules\\Trials\\Controllers\\Browser::reschedule/$1', ['filter' => 'permission:lead.update']);
    $routes->post('trials/(:num)/coaches', '\\App\\Modules\\Trials\\Controllers\\Browser::coaches/$1', ['filter' => 'auth']);
    $routes->post('trials/(:num)/cancel', '\\App\\Modules\\Trials\\Controllers\\Browser::cancel/$1', ['filter' => 'permission:lead.update']);
    $routes->post('trials/(:num)/attendance', '\\App\\Modules\\Trials\\Controllers\\Browser::attendance/$1', ['filter' => 'permission:lead.update']);
    $routes->post('trials/(:num)/selection', '\\App\\Modules\\Trials\\Controllers\\Browser::selection/$1', ['filter' => 'permission:lead.update']);
    $routes->post('trials/(:num)/admission-pending', '\\App\\Modules\\Trials\\Controllers\\Browser::admissionPending/$1', ['filter' => 'permission:lead.update']);
    $routes->post('trials/(:num)/ratings', '\\App\\Modules\\Trials\\Controllers\\Browser::ratings/$1');
    $routes->get('trials/(:num)/ratings-for-admission', '\\App\\Modules\\Trials\\Controllers\\Browser::ratingsForAdmission/$1', ['filter' => 'permission:admission.create']);

    $routes->get('follow-ups', '\\App\\Modules\\CRM\\Controllers\\Browser::followUps', ['filter' => 'permission:lead.view']);
    $routes->get('leads', '\\App\\Modules\\CRM\\Controllers\\Browser::index', ['filter' => 'permission:lead.view']);
    $routes->get('leads/new', '\\App\\Modules\\CRM\\Controllers\\Browser::createPage', ['filter' => 'permission:lead.create']);
    $routes->get('leads/import/sample', '\\App\\Modules\\CRM\\Controllers\\Browser::sampleImport', ['filter' => 'permission:lead.create']);
    $routes->post('leads', '\\App\\Modules\\CRM\\Controllers\\Browser::create', ['filter' => 'permission:lead.create']);
    $routes->post('leads/import', '\\App\\Modules\\CRM\\Controllers\\Browser::import', ['filter' => 'permission:lead.create']);
    // Legacy detail URL retained as a redirect; the canonical URL is /crm/leads/{id}.
    $routes->get('leads/(:num)/page', '\\App\\Modules\\CRM\\Controllers\\Browser::legacyShow/$1', ['filter' => 'permission:lead.view']);
    $routes->get('leads/(:num)/edit', '\\App\\Modules\\CRM\\Controllers\\Browser::edit/$1', ['filter' => 'permission:lead.update']);
    $routes->post('leads/(:num)/edit', '\\App\\Modules\\CRM\\Controllers\\Browser::update/$1', ['filter' => 'permission:lead.update']);
    $routes->get('leads/(:num)/follow-ups/new', '\\App\\Modules\\CRM\\Controllers\\Browser::followUpPage/$1', ['filter' => 'permission:lead.update']);
    $routes->get('leads/(:num)', '\\App\\Modules\\CRM\\Controllers\\Browser::show/$1', ['filter' => 'permission:lead.view']);
    $routes->post('leads/(:num)/assignment', '\\App\\Modules\\CRM\\Controllers\\Leads::assign/$1', ['filter' => 'permission:lead.update']);
    $routes->post('leads/(:num)/status', '\\App\\Modules\\CRM\\Controllers\\Browser::status/$1', ['filter' => 'permission:lead.update']);
    $routes->post('leads/(:num)/follow-ups', '\\App\\Modules\\CRM\\Controllers\\Browser::followUp/$1', ['filter' => 'permission:lead.update']);
    $routes->post('leads/(:num)/follow-ups/(:num)/edit', '\\App\\Modules\\CRM\\Controllers\\Browser::updateFollowUp/$1/$2', ['filter' => 'permission:lead.update']);
    $routes->post('leads/(:num)/follow-ups/(:num)/complete', '\\App\\Modules\\CRM\\Controllers\\Browser::completeFollowUp/$1/$2', ['filter' => 'permission:lead.update']);
    $routes->post('leads/(:num)/follow-ups/(:num)/cancel', '\\App\\Modules\\CRM\\Controllers\\Browser::cancelFollowUp/$1/$2', ['filter' => 'permission:lead.update']);
    $routes->post('leads/(:num)/follow-ups/(:num)/correct', '\\App\\Modules\\CRM\\Controllers\\Browser::correctFollowUp/$1/$2', ['filter' => 'permission:lead.update']);
});
