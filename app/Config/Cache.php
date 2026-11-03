<?php

declare (strict_types=1);
namespace Config;

use CodeIgniter\Cache\Handlers\DummyHandler;
use CodeIgniter\Cache\Handlers\FileHandler;
use CodeIgniter\Config\BaseConfig;
final class Cache extends BaseConfig
{
    public string $handler = 'file';
    public string $backupHandler = 'dummy';
    public string $prefix = 'mcc_';
    public int $ttl = 60;
    public string $reservedCharacters = '{}()/\@:';
    public array $file = ['storePath' => WRITEPATH . 'cache/', 'mode' => 0640];
    public array $validHandlers = ['dummy' => DummyHandler::class, 'file' => FileHandler::class];
    public bool|array $cacheQueryString = false;
    public array $cacheStatusCodes = [];
}
