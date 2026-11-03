<?php

declare(strict_types=1);

use CodeIgniter\Boot;
use CodeIgniter\Database\Seeder;
use Config\Paths;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Development seeder must be executed from the CLI.\n");
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

if (ENVIRONMENT === 'production') {
    throw new \RuntimeException('Refusing development bootstrap while ENVIRONMENT=production.');
}

$database = db_connect('tests');
$databaseName = (string) $database->getDatabase();
$normalized = strtolower($databaseName);
if ($databaseName !== 'mcc_cricket_club_test' || str_contains($normalized, 'prod')) {
    throw new \RuntimeException('Refusing bootstrap target: ' . $databaseName . '. Expected mcc_cricket_club_test.');
}

echo 'environment=' . ENVIRONMENT . PHP_EOL;
echo 'group=tests' . PHP_EOL;
echo 'host=' . (string) $database->hostname . PHP_EOL;
echo 'database=' . $databaseName . PHP_EOL;
echo 'destructive_operations=none' . PHP_EOL;

$seeder = new Seeder(config('Database'), $database);
$seeder->call('App\\Database\\Seeds\\DevelopmentBootstrapSeeder');
