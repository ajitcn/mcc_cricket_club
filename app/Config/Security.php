<?php

declare (strict_types=1);
namespace Config;

use CodeIgniter\Config\BaseConfig;
final class Security extends BaseConfig
{
    public string $csrfProtection = 'cookie';
    public bool $tokenRandomize = false;
    public string $tokenName = 'csrf_test_name';
    public string $headerName = 'X-CSRF-TOKEN';
    public string $cookieName = 'csrf_cookie_name';
    public int $expires = 7200;
    /**
     * Keep browser forms valid across tabs while retaining token validation.
     *
     * Regenerating after every POST invalidates an already-rendered Admission
     * form when another tab submits a request first. The token remains random
     * and is still required for every state-changing request.
     */
    public bool $regenerate = ENVIRONMENT === 'production';
    public bool $redirect = ENVIRONMENT === 'production';
}
