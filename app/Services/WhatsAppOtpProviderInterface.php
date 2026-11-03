<?php

declare(strict_types=1);

namespace App\Services;

interface WhatsAppOtpProviderInterface
{
    public function sendOtp(string $mobile, string $otp): bool;
}
