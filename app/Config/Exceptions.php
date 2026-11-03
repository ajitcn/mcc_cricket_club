<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;
use CodeIgniter\Debug\{ExceptionHandler, ExceptionHandlerInterface};
use Psr\Log\LogLevel;
use Throwable;
class Exceptions extends BaseConfig
{
    public bool $log = true;
    public array $ignoreCodes = [404];
    public string $errorViewPath = APPPATH . 'Views/errors';
    public array $sensitiveDataInTrace = [
        'password',
        'password_hash',
        'token',
        'secret',
        'credential',
        'authorization',
    ];
    public bool $logDeprecations = true;
    public string $deprecationLogLevel = LogLevel::WARNING;
    public function handler(int $statusCode, Throwable $exception): ExceptionHandlerInterface
    {
        return new ExceptionHandler($this);
    }
}
