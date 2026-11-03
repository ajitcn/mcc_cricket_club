<?php

declare (strict_types=1);
namespace Config;

use CodeIgniter\Config\BaseConfig;
final class Toolbar extends BaseConfig
{
    public array $collectors = [];
    public bool $collectVarData = false;
    public int $maxHistory = 0;
    public string $viewsPath = SYSTEMPATH . 'Debug/Toolbar/Views/';
    public int $maxQueries = 0;
    public array $watchedDirectories = [];
    public array $watchedExtensions = [];
}
