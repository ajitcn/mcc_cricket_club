<?php

declare(strict_types=1);

use App\Database\Seeds\SuperAdminRestoreSeeder;
use CodeIgniter\Boot;
use Config\Paths;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Super Admin restore must be executed from the CLI.\n");
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

define(
    'ENVIRONMENT',
    $_ENV['CI_ENVIRONMENT']
        ?? $_SERVER['CI_ENVIRONMENT']
        ?? getenv('CI_ENVIRONMENT')
        ?: 'production',
);

Boot::bootConsole($paths);

if (! in_array(ENVIRONMENT, ['development', 'testing'], true)) {
    throw new RuntimeException('Refusing Super Admin restore outside development/testing.');
}

$database = db_connect('tests');
$databaseName = strtolower((string) $database->getDatabase());
if ($databaseName !== 'mcc_cricket_club_test') {
    throw new RuntimeException('Refusing restore target: ' . $database->getDatabase());
}

echo "environment=" . ENVIRONMENT . PHP_EOL;
echo "database=mcc_cricket_club_test" . PHP_EOL;
(new SuperAdminRestoreSeeder(config('Database')))->run();
