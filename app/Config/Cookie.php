<?php

declare (strict_types=1);
namespace Config;

use CodeIgniter\Config\BaseConfig;
final class Cookie extends BaseConfig
{
    public string $prefix = '';
    public int $expires = 0;
    public string $path = '/';
    public string $domain = '';
    public bool $secure = ENVIRONMENT === 'production';
    public bool $httponly = true;
    public string $samesite = 'Lax';
    public bool $raw = false;
}
