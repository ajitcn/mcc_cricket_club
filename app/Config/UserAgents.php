<?php

declare (strict_types=1);
namespace Config;

use CodeIgniter\Config\BaseConfig;
/** Minimal CI4 UserAgent configuration required by the HTTP runtime. */
final class UserAgents extends BaseConfig
{
    public array $platforms = [];
    public array $browsers = [];
    public array $mobiles = [];
    public array $robots = [];
}
