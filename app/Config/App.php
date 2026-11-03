<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;
class App extends BaseConfig
{
    public string $baseURL = 'http://localhost/cricket-club/public/';
    public array $allowedHostnames = [];
    public string $indexPage = '';
    public string $uriProtocol = 'REQUEST_URI';
    public string $permittedURIChars = 'a-z 0-9~%.:_\-';
    public string $defaultLocale = 'en';
    public bool $negotiateLocale = false;
    public array $supportedLocales = ['en'];
    public string $appTimezone = 'Asia/Kolkata';
    public string $charset = 'UTF-8';
    public bool $forceGlobalSecureRequests = ENVIRONMENT === 'production';
    public bool $cookieSecure = ENVIRONMENT === 'production';
    public bool $cookieHTTPOnly = true;
    public string $cookieSameSite = 'Lax';
    public bool $CSPEnabled = false;
    public array $proxyIPs = [];
}
