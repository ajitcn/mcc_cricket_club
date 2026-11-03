<?php

declare(strict_types=1);

use CodeIgniter\Boot;
use Config\Paths;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Development verification must be executed from the CLI.\n");
    exit(1);
}

require dirname(__DIR__) . '/app/Config/Paths.php';
$paths = new Paths();
require rtrim($paths->systemDirectory, '/\\') . DIRECTORY_SEPARATOR . 'Boot.php';
define('APPPATH', realpath($paths->appDirectory) . DIRECTORY_SEPARATOR);
define('ROOTPATH', realpath(APPPATH . '..') . DIRECTORY_SEPARATOR);
define('SYSTEMPATH', realpath($paths->systemDirectory) . DIRECTORY_SEPARATOR);
define('WRITEPATH', realpath($paths->writableDirectory) . DIRECTORY_SEPARATOR);
define('TESTPATH', realpath($paths->testsDirectory) . DIRECTORY_SEPARATOR);
define('SUPPORTPATH', TESTPATH . '_support' . DIRECTORY_SEPARATOR);
define('FCPATH', ROOTPATH . 'public' . DIRECTORY_SEPARATOR);
require_once $paths->systemDirectory . '/Config/DotEnv.php';
(new CodeIgniter\Config\DotEnv($paths->envDirectory))->load();
define('ENVIRONMENT', $_ENV['CI_ENVIRONMENT'] ?? $_SERVER['CI_ENVIRONMENT'] ?? getenv('CI_ENVIRONMENT') ?: 'production');
Boot::bootConsole($paths);

$db = db_connect('tests');
$databaseName = (string) $db->getDatabase();
if (ENVIRONMENT === 'production' || $databaseName !== 'mcc_cricket_club_test') {
    throw new \RuntimeException('Refusing verification target.');
}

$tables = ['users', 'roles', 'role_permissions', 'branches', 'user_roles', 'user_branches', 'lead_sources', 'lead_statuses', 'leads', 'lead_status_history', 'persons', 'player_contacts', 'players', 'batches', 'sessions', 'trials', 'trial_history'];
$counts = [];
foreach ($tables as $table) {
    $counts[$table] = (int) $db->table($table)->countAllResults();
}

$checks = [
    'players_with_guardian' => (int) $db->table('players p')->join('player_contacts pc', 'pc.player_id=p.id')->where('pc.relationship_type', 'guardian')->countAllResults(),
    'trials_with_history' => (int) $db->table('trials t')->join('trial_history th', 'th.trial_id=t.id')->countAllResults(),
    'leads_with_branches' => (int) $db->table('leads l')->join('branches b', 'b.id=l.branch_id')->where('l.full_name LIKE', 'TEST_MCC%')->countAllResults(),
    'users_with_roles' => (int) $db->table('users u')->join('user_roles ur', 'ur.user_id=u.id')->where('u.display_name LIKE', 'TEST_MCC%')->countAllResults(),
];

echo json_encode(['database' => $databaseName, 'counts' => $counts, 'relationship_checks' => $checks, 'records_deleted' => 0], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT) . PHP_EOL;
