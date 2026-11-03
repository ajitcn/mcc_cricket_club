<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;
class Migrations extends BaseConfig
{
    public bool $enabled = true;
    public string $table = 'migrations';
    public string $timestampFormat = 'YmdHis_';
    public bool $lock = false;
}
