<?php

$routes->group('admin', ['filter' => 'auth'], static function ($routes): void {

$routes->get('/', '\\App\\Modules\\Admin\\Controllers\\Browser::index', ['filter' => 'permission:rbac.manage']);
$routes->get('branches/create', '\\App\\Modules\\Admin\\Controllers\\Browser::createBranchPage', ['filter' => 'permission:rbac.manage']);
$routes->post('branches/create', '\\App\\Modules\\Admin\\Controllers\\Browser::createBranch', ['filter' => 'permission:rbac.manage']);
$routes->get('branches', '\\App\\Modules\\Admin\\Controllers\\Browser::branches', ['filter' => 'permission:rbac.manage']);
$routes->get('branches/(:num)', '\\App\\Modules\\Admin\\Controllers\\Browser::branch/$1', ['filter' => 'permission:rbac.manage']);
$routes->get('branches/(:num)/edit', '\\App\\Modules\\Admin\\Controllers\\Browser::editBranchPage/$1', ['filter' => 'permission:rbac.manage']);
$routes->post('branches/(:num)', '\\App\\Modules\\Admin\\Controllers\\Browser::updateBranch/$1', ['filter' => 'permission:rbac.manage']);
$routes->post('branches/(:num)/status', '\\App\\Modules\\Admin\\Controllers\\Browser::setBranchActive/$1', ['filter' => 'permission:rbac.manage']);
$routes->get('lead-sources', '\\App\\Modules\\Admin\\Controllers\\Browser::leadSources', ['filter' => 'permission:rbac.manage']);
$routes->get('lead-sources/new', '\\App\\Modules\\Admin\\Controllers\\Browser::leadSourceForm', ['filter' => 'permission:rbac.manage']);
$routes->post('lead-sources', '\\App\\Modules\\Admin\\Controllers\\Browser::createLeadSource', ['filter' => 'permission:rbac.manage']);
$routes->get('lead-sources/(:num)/edit', '\\App\\Modules\\Admin\\Controllers\\Browser::leadSourceEdit/$1', ['filter' => 'permission:rbac.manage']);
$routes->post('lead-sources/(:num)', '\\App\\Modules\\Admin\\Controllers\\Browser::updateLeadSource/$1', ['filter' => 'permission:rbac.manage']);
$routes->post('lead-sources/(:num)/status', '\\App\\Modules\\Admin\\Controllers\\Browser::setLeadSourceActive/$1', ['filter' => 'permission:rbac.manage']);
$routes->get('lead-statuses', '\\App\\Modules\\Admin\\Controllers\\Browser::leadStatuses', ['filter' => 'permission:rbac.manage']);
$routes->get('lead-statuses/new', '\\App\\Modules\\Admin\\Controllers\\Browser::leadStatusForm', ['filter' => 'permission:rbac.manage']);
$routes->post('lead-statuses', '\\App\\Modules\\Admin\\Controllers\\Browser::createLeadStatus', ['filter' => 'permission:rbac.manage']);
$routes->get('lead-statuses/(:num)/edit', '\\App\\Modules\\Admin\\Controllers\\Browser::leadStatusEdit/$1', ['filter' => 'permission:rbac.manage']);
$routes->post('lead-statuses/(:num)', '\\App\\Modules\\Admin\\Controllers\\Browser::updateLeadStatus/$1', ['filter' => 'permission:rbac.manage']);
$routes->post('lead-statuses/(:num)/status', '\\App\\Modules\\Admin\\Controllers\\Browser::setLeadStatusActive/$1', ['filter' => 'permission:rbac.manage']);
$routes->get('users', '\\App\\Modules\\Admin\\Controllers\\Browser::users', ['filter' => 'permission:rbac.manage']);
$routes->get('users/new', '\\App\\Modules\\Admin\\Controllers\\Browser::createUserPage', ['filter' => 'permission:rbac.manage']);
$routes->post('users', '\\App\\Modules\\Admin\\Controllers\\Browser::createUser', ['filter' => 'permission:rbac.manage']);
    $routes->get('users/(:num)', '\\App\\Modules\\Admin\\Controllers\\Browser::user/$1', ['filter' => 'permission:rbac.manage']);
$routes->get('users/(:num)/edit', '\\App\\Modules\\Admin\\Controllers\\Browser::editUserPage/$1', ['filter' => 'permission:rbac.manage']);
$routes->post('users/(:num)', '\\App\\Modules\\Admin\\Controllers\\Browser::updateUser/$1', ['filter' => 'permission:rbac.manage']);
$routes->post('users/(:num)/enabled', '\\App\\Modules\\Admin\\Controllers\\Browser::setEnabled/$1', ['filter' => 'permission:rbac.manage']);
$routes->post('users/(:num)/roles', '\\App\\Modules\\Admin\\Controllers\\Browser::assignRole/$1', ['filter' => 'permission:rbac.manage']);
$routes->post('users/(:num)/branches', '\\App\\Modules\\Admin\\Controllers\\Browser::assignBranch/$1', ['filter' => 'permission:rbac.manage']);
$routes->get('roles', '\\App\\Modules\\Admin\\Controllers\\Browser::roles', ['filter' => 'permission:rbac.manage']);
$routes->get('roles/new', '\\App\\Modules\\Admin\\Controllers\\Browser::createRolePage', ['filter' => 'permission:rbac.manage']);
$routes->post('roles', '\\App\\Modules\\Admin\\Controllers\\Browser::createRole', ['filter' => 'permission:rbac.manage']);
$routes->get('roles/(:num)', '\\App\\Modules\\Admin\\Controllers\\Browser::role/$1', ['filter' => 'permission:rbac.manage']);
$routes->get('roles/(:num)/edit', '\\App\\Modules\\Admin\\Controllers\\Browser::editRolePage/$1', ['filter' => 'permission:rbac.manage']);
$routes->post('roles/(:num)', '\\App\\Modules\\Admin\\Controllers\\Browser::updateRole/$1', ['filter' => 'permission:rbac.manage']);
$routes->post('roles/(:num)/permissions', '\\App\\Modules\\Admin\\Controllers\\Browser::assignPermission/$1', ['filter' => 'permission:rbac.manage']);
$routes->get('permissions', '\\App\\Modules\\Admin\\Controllers\\Browser::permissions', ['filter' => 'permission:rbac.manage']);
$routes->get('permissions/(:any)', '\\App\\Modules\\Admin\\Controllers\\Browser::permission/$1', ['filter' => 'permission:rbac.manage']);
$routes->get('audit', '\\App\\Modules\\Admin\\Controllers\\Browser::auditLogs', ['filter' => 'permission:audit.view']);
});
