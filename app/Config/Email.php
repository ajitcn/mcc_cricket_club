<?php

declare (strict_types=1);
namespace Config;

use CodeIgniter\Config\BaseConfig;
final class Email extends BaseConfig
{
    public string $fromEmail = '';
    public string $fromName = '';
    public string $protocol = 'smtp';
    public string $SMTPHost = '';
    public string $SMTPAuthMethod = '';
    public string $SMTPUser = '';
    public string $SMTPPass = '';
    public int $SMTPPort = 0;
    public int $SMTPTimeout = 0;
    public bool $SMTPKeepAlive = false;
    public string $SMTPCrypto = '';
    public string $mailType = 'text';
    public string $charset = 'UTF-8';
    public bool $validate = true;
    public string $recoveryResetPath = '/auth/recovery/reset';
    public function __construct()
    {
        parent::__construct();
        $this->fromEmail = (string) env('email.fromEmail', '');
        $this->fromName = (string) env('email.fromName', '');
        $this->protocol = (string) env('email.protocol', 'smtp');
        $this->SMTPHost = (string) env('email.SMTPHost', '');
        $this->SMTPAuthMethod = (string) env('email.SMTPAuthMethod', '');
        $this->SMTPUser = (string) env('email.SMTPUser', '');
        $this->SMTPPass = (string) env('email.SMTPPass', '');
        $this->SMTPPort = (int) env('email.SMTPPort', 0);
        $this->SMTPTimeout = (int) env('email.SMTPTimeout', 0);
        $this->SMTPKeepAlive = filter_var(env('email.SMTPKeepAlive', false), FILTER_VALIDATE_BOOLEAN);
        $this->SMTPCrypto = (string) env('email.SMTPCrypto', '');
    }
}
