<?php
use CodeIgniter\Boot; use Config\Paths;
if(version_compare(PHP_VERSION,'8.2','<')){header('HTTP/1.1 503 Service Unavailable.',true,503);echo 'PHP 8.2 or higher is required.';exit(1);}
define('FCPATH',__DIR__.DIRECTORY_SEPARATOR);if(getcwd().DIRECTORY_SEPARATOR!==FCPATH)chdir(FCPATH);require FCPATH.'../app/Config/Paths.php';$paths=new Paths();require $paths->systemDirectory.'/Boot.php';exit(Boot::bootWeb($paths));
