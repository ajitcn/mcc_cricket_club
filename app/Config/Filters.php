<?php

namespace Config;

use CodeIgniter\Config\Filters as BaseFilters;
use CodeIgniter\Filters\{CSRF, DebugToolbar, ForceHTTPS, PageCache, PerformanceMetrics};
class Filters extends BaseFilters
{
    public array $aliases = ['csrf' => CSRF::class, 'toolbar' => DebugToolbar::class, 'forcehttps' => ForceHTTPS::class, 'pagecache' => PageCache::class,
    'performance' => PerformanceMetrics::class, 'auth' => \App\Filters\Auth::class, 'portal' => \App\Filters\PortalAuth::class, 'permission' => \App\Filters\Permission::class];
    public array $required = ['before' => ['forcehttps', 'pagecache'], 'after' => ['pagecache', 'performance', 'toolbar']];
    public array $globals = ['before' => ['csrf'], 'after' => []];
    public array $methods = [];
    public array $filters = [];
}
