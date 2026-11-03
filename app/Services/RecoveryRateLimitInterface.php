<?php

declare(strict_types=1);

namespace App\Services;

interface RecoveryRateLimitInterface
{
    public function allow(string $email, string $ipAddress): bool;

    public function clearAccount(string $email): void;
}
