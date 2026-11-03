<?php

declare(strict_types=1);

namespace App\Services;

final class MockWhatsAppOtpProvider implements WhatsAppOtpProviderInterface
{
    public function __construct(
        private readonly object $database,
        private readonly PortalOtpDebugService $debug,
    ) {
    }

    public function sendOtp(string $mobile, string $otp): bool
    {
        return $this->debug->canDisplay($this->database);
    }
}
