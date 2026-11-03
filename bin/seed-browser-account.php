<?php

declare(strict_types=1);

use CodeIgniter\Boot;
use Config\Paths;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Browser account fixture must be executed from the CLI.\n");
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

$email = trim((string) (getenv('MCC_BROWSER_TEST_EMAIL') ?: $_ENV['MCC_BROWSER_TEST_EMAIL'] ?? ''));
$password = (string) (getenv('MCC_BROWSER_TEST_PASSWORD') ?: $_ENV['MCC_BROWSER_TEST_PASSWORD'] ?? '');
if ($email === '' || $password === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false || strlen($password) < 16) {
    fwrite(STDERR, "Set MCC_BROWSER_TEST_EMAIL and MCC_BROWSER_TEST_PASSWORD (minimum 16 characters).\n");
    exit(1);
}

if (ENVIRONMENT === 'production') {
    fwrite(STDERR, "Refusing to seed a browser account in production.\n");
    exit(1);
}

try {
    $db = db_connect('default');
    $database = strtolower((string) $db->getDatabase());
    if ($database !== 'mcc_cricket_club_test' || str_contains($database, 'prod')) {
        throw new RuntimeException('Refusing non-designated test database: ' . $db->getDatabase());
    }

    $now = date('Y-m-d H:i:s');
    $db->transStart();

    $role = $db->table('roles')->where('name', 'Browser Smoke Staff')->get()->getRowArray();
    if (! is_array($role)) {
        $db->table('roles')->insert(['name' => 'Browser Smoke Staff', 'created_at' => $now, 'updated_at' => $now]);
        $roleId = (int) $db->insertID();
    } else {
        $roleId = (int) $role['id'];
    }

    $permissions = ['rbac.manage', 'lead.view', 'admission.view', 'player.view', 'session.update', 'attendance.view'];
    foreach ($permissions as $permission) {
        $exists = $db->table('role_permissions')->where(['role_id' => $roleId, 'permission' => $permission])->countAllResults();
        if ($exists === 0) {
            $db->table('role_permissions')->insert(['role_id' => $roleId, 'permission' => $permission, 'created_at' => $now]);
        }
    }

    $branch = $db->table('branches')->where('code', 'BROWSER-SMOKE')->get()->getRowArray();
    if (! is_array($branch)) {
        $db->table('branches')->insert(['name' => 'Browser Smoke Branch', 'code' => 'BROWSER-SMOKE', 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);
        $branchId = (int) $db->insertID();
    } else {
        $branchId = (int) $branch['id'];
    }

    $user = $db->table('users')->where('email', strtolower($email))->get()->getRowArray();
    $userData = [
        'email' => strtolower($email),
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'display_name' => 'MCC Browser Smoke Staff (NON-PRODUCTION)',
        'is_enabled' => 1,
        'failed_login_count' => 0,
        'session_version' => is_array($user) ? (int) $user['session_version'] + 1 : 1,
        'locked_until' => null,
        'last_login_at' => null,
        'updated_at' => $now,
    ];
    if (! is_array($user)) {
        $userData['created_at'] = $now;
        $db->table('users')->insert($userData);
        $userId = (int) $db->insertID();
    } else {
        $userId = (int) $user['id'];
        $db->table('users')->where('id', $userId)->update($userData);
    }

    if ($db->table('user_roles')->where(['user_id' => $userId, 'role_id' => $roleId])->countAllResults() === 0) {
        $db->table('user_roles')->insert(['user_id' => $userId, 'role_id' => $roleId, 'created_at' => $now]);
    }
    if ($db->table('user_branches')->where(['user_id' => $userId, 'branch_id' => $branchId])->countAllResults() === 0) {
        $db->table('user_branches')->insert(['user_id' => $userId, 'branch_id' => $branchId, 'created_at' => $now]);
    }

    $db->transComplete();
    if (! $db->transStatus()) {
        throw new RuntimeException('Browser account fixture transaction failed.');
    }

    echo "seed_result=success\nuser_email=" . $email . "\nuser_id=" . $userId . "\nbranch_id=" . $branchId . "\nrole=Browser Smoke Staff\n";
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, "seed_result=failure\n" . $exception::class . ': ' . $exception->getMessage() . "\n");
    exit(1);
}
