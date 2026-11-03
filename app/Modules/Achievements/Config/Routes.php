<?php

$routes->group('achievements', ['filter' => 'auth'], static function ($routes): void {
    $routes->get('/', '\\App\\Modules\\Achievements\\Controllers\\Browser::index', [
        'filter' => 'permission:achievement.view',
    ]);
    $routes->get('new', '\\App\\Modules\\Achievements\\Controllers\\Browser::createPage', [
        'filter' => 'permission:achievement.create',
    ]);
    $routes->post('/', '\\App\\Modules\\Achievements\\Controllers\\Browser::create', [
        'filter' => 'permission:achievement.create',
    ]);
    $routes->get('awards', '\\App\\Modules\\Achievements\\Controllers\\Browser::awards', ['filter' => 'permission:achievement.view']);
    $routes->get('awards/new', '\\App\\Modules\\Achievements\\Controllers\\Browser::awardCreatePage', ['filter' => 'permission:achievement.create']);
    $routes->post('awards', '\\App\\Modules\\Achievements\\Controllers\\Browser::createAward', ['filter' => 'permission:achievement.create']);
    $routes->get('awards/(:num)', '\\App\\Modules\\Achievements\\Controllers\\Browser::awardShow/$1', ['filter' => 'permission:achievement.view']);
    $routes->get('awards/(:num)/edit', '\\App\\Modules\\Achievements\\Controllers\\Browser::awardEditPage/$1', ['filter' => 'permission:achievement.update']);
    $routes->post('awards/(:num)', '\\App\\Modules\\Achievements\\Controllers\\Browser::updateAward/$1', ['filter' => 'permission:achievement.update']);
    $routes->post('awards/(:num)/approve', '\\App\\Modules\\Achievements\\Controllers\\Browser::approveAward/$1', ['filter' => 'permission:achievement.approve']);
    $routes->post('awards/(:num)/publish', '\\App\\Modules\\Achievements\\Controllers\\Browser::publishAward/$1', ['filter' => 'permission:achievement.publish']);
    $routes->post('awards/(:num)/revoke', '\\App\\Modules\\Achievements\\Controllers\\Browser::revokeAward/$1', ['filter' => 'permission:achievement.revoke']);
    $routes->post('awards/(:num)/archive', '\\App\\Modules\\Achievements\\Controllers\\Browser::archiveAward/$1', ['filter' => 'permission:achievement.archive']);
    $routes->post('awards/(:num)/restore', '\\App\\Modules\\Achievements\\Controllers\\Browser::restoreAward/$1', ['filter' => 'permission:achievement.restore']);
    $routes->post('awards/(:num)/correct', '\\App\\Modules\\Achievements\\Controllers\\Browser::correctPublishedAward/$1', ['filter' => 'permission:achievement.update']);
    $routes->post('awards/(:num)/certificate', '\\App\\Modules\\Achievements\\Controllers\\Browser::generateCertificate/$1', ['filter' => 'permission:achievement.certificate.generate']);
    $routes->get('awards/(:num)/certificate/download', '\\App\\Modules\\Achievements\\Controllers\\Browser::downloadCertificate/$1', ['filter' => 'permission:achievement.certificate.download']);
    $routes->post('eligibility/evaluate', '\\App\\Modules\\Achievements\\Controllers\\Browser::evaluateEligibility', ['filter' => 'permission:achievement.eligibility.evaluate']);
    $routes->post('eligibility/(:num)/retry', '\\App\\Modules\\Achievements\\Controllers\\Browser::retryEligibility/$1', ['filter' => 'permission:achievement.eligibility.evaluate']);
    $routes->get('reports', '\\App\\Modules\\Achievements\\Controllers\\Browser::reports', ['filter' => 'permission:report.view']);
    $routes->get('reports/csv', '\\App\\Modules\\Achievements\\Controllers\\Browser::reportsCsv', ['filter' => 'permission:report.view']);
});
