<?php

$routes->group('meeting-followups', ['filter' => 'auth'], static function ($routes): void {
    $routes->get('/', '\App\Modules\MeetingFollowUps\Controllers\Browser::index', ['filter' => 'permission:admission.view']);
    $routes->get('new', '\App\Modules\MeetingFollowUps\Controllers\Browser::newPage', ['filter' => 'permission:admission.create']);
    $routes->post('/', '\App\Modules\MeetingFollowUps\Controllers\Browser::create', ['filter' => 'permission:admission.create']);
    $routes->get('(:num)', '\App\Modules\MeetingFollowUps\Controllers\Browser::show/$1', ['filter' => 'permission:admission.view']);
    $routes->get('(:num)/edit', '\App\Modules\MeetingFollowUps\Controllers\Browser::editPage/$1', ['filter' => 'permission:admission.update']);
    $routes->post('(:num)/edit', '\App\Modules\MeetingFollowUps\Controllers\Browser::update/$1', ['filter' => 'permission:admission.update']);
});
