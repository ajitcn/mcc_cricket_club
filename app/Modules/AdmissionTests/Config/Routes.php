<?php

$routes->get('admission-tests/student/(:segment)', '\\App\\Modules\\AdmissionTests\\Controllers\\Browser::studentWizard/$1');
$routes->post('admission-tests/student/(:segment)/personal-details', '\\App\\Modules\\AdmissionTests\\Controllers\\Browser::studentWizardPersonalDetails/$1');
$routes->post('admission-tests/student/(:segment)/submit', '\\App\\Modules\\AdmissionTests\\Controllers\\Browser::studentWizardSubmit/$1');

$routes->group('admission-tests', ['filter' => 'auth'], static function ($routes): void {
    $routes->get('/', '\\App\\Modules\\AdmissionTests\\Controllers\\Browser::index', ['filter' => 'permission:admission.view']);
    $routes->get('(:num)', '\\App\\Modules\\AdmissionTests\\Controllers\\Browser::show/$1', ['filter' => 'permission:admission.view']);
    $routes->get('(:num)/paper-set', '\\App\\Modules\\AdmissionTests\\Controllers\\Browser::paperSet/$1', ['filter' => 'permission:admission.view']);
    $routes->post('(:num)/paper-set', '\\App\\Modules\\AdmissionTests\\Controllers\\Browser::selectPaperSet/$1', ['filter' => 'permission:admission.update']);
    $routes->get('(:num)/wizard', '\\App\\Modules\\AdmissionTests\\Controllers\\Browser::wizard/$1', ['filter' => 'permission:admission.view']);
    $routes->post('(:num)/wizard/personal-details', '\\App\\Modules\\AdmissionTests\\Controllers\\Browser::wizardPersonalDetails/$1', ['filter' => 'permission:admission.update']);
    $routes->get('(:num)/personal-details', '\\App\\Modules\\AdmissionTests\\Controllers\\Browser::personalDetailsPage/$1', ['filter' => 'permission:admission.update']);
    $routes->post('(:num)/wizard/submit', '\\App\\Modules\\AdmissionTests\\Controllers\\Browser::wizardSubmit/$1', ['filter' => 'permission:admission.update']);
    $routes->post('(:num)/personal-details', '\\App\\Modules\\AdmissionTests\\Controllers\\Browser::personalDetails/$1', ['filter' => 'permission:admission.update']);
    $routes->post('(:num)/start', '\\App\\Modules\\AdmissionTests\\Controllers\\Browser::startTest/$1', ['filter' => 'permission:admission.update']);
    $routes->post('(:num)/basic-test', '\\App\\Modules\\AdmissionTests\\Controllers\\Browser::basicTest/$1', ['filter' => 'permission:admission.update']);
    $routes->post('(:num)/follow-ups', '\\App\\Modules\\AdmissionTests\\Controllers\\Browser::followUp/$1', ['filter' => 'permission:admission.update']);
    $routes->post('(:num)/fees', '\\App\\Modules\\AdmissionTests\\Controllers\\Browser::fees/$1', ['filter' => 'permission:admission.update']);
    $routes->post('(:num)/assessment-remarks', '\\App\\Modules\\AdmissionTests\\Controllers\\Browser::assessmentRemarks/$1', ['filter' => 'permission:admission.update']);
    $routes->post('(:num)/pending-admission', '\\App\\Modules\\AdmissionTests\\Controllers\\Browser::pendingAdmission/$1', ['filter' => 'permission:admission.create']);
    $routes->post('(:num)/status', '\\App\\Modules\\AdmissionTests\\Controllers\\Browser::changeStatus/$1', ['filter' => 'permission:admission.update']);
    $routes->post('(:num)/student-link', '\\App\\Modules\\AdmissionTests\\Controllers\\Browser::generateStudentLink/$1', ['filter' => 'permission:admission.update']);
});
