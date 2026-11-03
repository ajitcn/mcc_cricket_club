<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;
final class Kint extends BaseConfig
{
    public int $maxDepth = 6;
    public bool $displayCalledFrom = false;
    public bool $expanded = false;
    public string $richTheme = 'aante-light.css';
    public bool $richFolder = false;
    public array $richObjectPlugins = [];
    public array $richTabPlugins = [];
    public bool $cliColors = true;
    public bool $cliForceUTF8 = true;
    public bool $cliDetectWidth = true;
    public int $cliMinWidth = 80;
}
