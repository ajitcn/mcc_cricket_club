<?php

declare(strict_types=1);

use CodeIgniter\Boot;
use CodeIgniter\Database\MigrationRunner;
use Config\Paths;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Migration runner must be executed from the CLI.\n");
    exit(1);
}

require dirname(__DIR__) . '/app/Config/Paths.php';
$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (! is_file($autoload)) {
    fwrite(STDERR, "Composer autoload file is unavailable. Run composer install first.\n");
    exit(1);
}
require $autoload;
$paths = new Paths();
require rtrim($paths->systemDirectory, '/\\') . DIRECTORY_SEPARATOR . 'Boot.php';
define('APPPATH', realpath($paths->appDirectory) . DIRECTORY_SEPARATOR);
define('ROOTPATH', realpath(APPPATH . '..') . DIRECTORY_SEPARATOR);
define('SYSTEMPATH', realpath($paths->systemDirectory) . DIRECTORY_SEPARATOR);
define('WRITEPATH', realpath($paths->writableDirectory) . DIRECTORY_SEPARATOR);
define('TESTPATH', realpath($paths->testsDirectory) . DIRECTORY_SEPARATOR);
define('SUPPORTPATH', TESTPATH . '_support' . DIRECTORY_SEPARATOR);
define('FCPATH', ROOTPATH . 'public' . DIRECTORY_SEPARATOR);
// bootConsole() expects ENVIRONMENT to be defined by the caller of a standalone CLI entry point.
require_once $paths->systemDirectory . '/Config/DotEnv.php';
(new CodeIgniter\Config\DotEnv($paths->envDirectory))->load();
define('ENVIRONMENT', $_ENV['CI_ENVIRONMENT'] ?? $_SERVER['CI_ENVIRONMENT'] ?? getenv('CI_ENVIRONMENT') ?: 'production');
Boot::bootConsole($paths);

$arguments = [];
foreach (array_slice($argv, 1) as $argument) {
    if (str_starts_with($argument, '--') && str_contains($argument, '=')) {
        [$key, $value] = explode('=', substr($argument, 2), 2);
        $arguments[$key] = $value;
    } elseif (str_starts_with($argument, '--')) {
        $arguments[substr($argument, 2)] = true;
    } else {
        $arguments['command'] = $argument;
    }
}

$group = (string) ($arguments['group'] ?? 'tests');
$command = (string) ($arguments['command'] ?? 'latest');
$confirmation = (string) ($arguments['confirm'] ?? '');

if ($group === '' || $group === 'default') {
    fwrite(STDERR, "Refusing migration target: an explicit non-default group is required.\n");
    exit(1);
}
if (defined('ENVIRONMENT') && ENVIRONMENT === 'production') {
    fwrite(STDERR, "Refusing migrations while ENVIRONMENT=production.\n");
    exit(1);
}
$allowedGroup = getenv('MCC_MIGRATION_ALLOW_GROUP');
if ($group !== 'tests' && ($allowedGroup !== $group || $confirmation !== 'YES')) {
    fwrite(STDERR, "Refusing non-test migration target. Set MCC_MIGRATION_ALLOW_GROUP and pass --confirm=YES explicitly.\n");
    exit(1);
}

try {
    $database = db_connect($group);
    $databaseName = (string) $database->getDatabase();
    $normalizedDatabase = strtolower($databaseName);
    if ($normalizedDatabase === 'mcc_cricket_club'
        || str_contains($normalizedDatabase, 'production')
        || str_contains($normalizedDatabase, 'prod')) {
        throw new RuntimeException('Refusing a production-like database target: ' . $databaseName);
    }

    $runner = new MigrationRunner(config('Migrations'), $group);
    $available = $runner->findMigrations();
    $historyTable = config('Migrations')->table;
    echo "environment=" . (defined('ENVIRONMENT') ? ENVIRONMENT : 'unknown') . PHP_EOL;
    echo "group=" . $group . PHP_EOL;
    echo "database=" . $databaseName . PHP_EOL;
    echo "available_migrations=" . count($available) . PHP_EOL;

    if ($command === 'status') {
        $runner->ensureTable();
        $history = $database->table($historyTable)->orderBy('version', 'ASC')->get()->getResultArray();
        echo "applied_migrations=" . count($history) . PHP_EOL;
        foreach ($history as $migration) {
            echo $migration['version'] . ' ' . $migration['class'] . ' batch=' . $migration['batch'] . PHP_EOL;
        }
        exit(0);
    }
    if ($command !== 'latest') {
        throw new InvalidArgumentException('Only latest and status commands are supported.');
    }

    $before = $database->table($historyTable)->countAllResults();
    if ($runner->latest($group) !== true) {
        throw new RuntimeException('Migration runner returned failure.');
    }
    $after = $database->table($historyTable)->orderBy('version', 'ASC')->get()->getResultArray();
    echo "migration_result=success" . PHP_EOL;
    echo "history_before=" . $before . PHP_EOL;
    echo "history_after=" . count($after) . PHP_EOL;
    foreach ($after as $migration) {
        echo $migration['version'] . ' ' . $migration['class'] . ' batch=' . $migration['batch'] . PHP_EOL;
    }
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, "migration_result=failure\n" . $exception::class . ': ' . $exception->getMessage() . "\n");
    exit(1);
}
