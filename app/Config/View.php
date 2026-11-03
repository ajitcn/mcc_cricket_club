<?php

declare (strict_types=1);
namespace Config;

use CodeIgniter\Config\View as BaseView;
final class View extends BaseView
{
    public $saveData = true;
    public $filters = [];
    public $plugins = [];
    public array $decorators = [];
    public string $appOverridesFolder = 'overrides';
}
