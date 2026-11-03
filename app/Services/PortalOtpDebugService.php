<?php

declare(strict_types=1);

namespace App\Services;

final class PortalOtpDebugService
{
    public function canDisplay(object $database, ?string $environment = null, ?bool $debugFlag = null): bool
    {
        if (! in_array(strtolower($environment ?? (string) ENVIRONMENT), ['development', 'testing'], true)) {
            return false;
        }

        if (strtolower((string) $database->getDatabase()) !== 'mcc_cricket_club_test') {
            return false;
        }

        return $debugFlag ?? (filter_var(env('PORTAL_OTP_DEBUG', 'false'), FILTER_VALIDATE_BOOLEAN) === true);
    }
}
