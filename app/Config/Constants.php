<?php

defined('APP_NAMESPACE') || define('APP_NAMESPACE', 'App');
defined('COMPOSER_PATH') || define('COMPOSER_PATH', ROOTPATH . 'vendor/autoload.php');
if (!defined('SUPPORTPATH') && is_dir(TESTPATH . '_support')) {
    define('SUPPORTPATH', realpath(TESTPATH . '_support') . DIRECTORY_SEPARATOR);
}
defined('SECOND') || define('SECOND', 1);
defined('MINUTE') || define('MINUTE', 60);
defined('HOUR') || define('HOUR', 3600);
defined('DAY') || define('DAY', 86400);
defined('EXIT_SUCCESS') || define('EXIT_SUCCESS', 0);
defined('EXIT_ERROR') || define('EXIT_ERROR', 1);
defined('EXIT_CONFIG') || define('EXIT_CONFIG', 2);
defined('EXIT_UNKNOWN') || define('EXIT_UNKNOWN', 4);
defined('EXIT_DATABASE') || define('EXIT_DATABASE', 8);
defined('EXIT__USAGE') || define('EXIT__USAGE', 64);
