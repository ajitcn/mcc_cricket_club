<?php

declare(strict_types=1);

namespace App\Services;

interface RecoveryDeliveryInterface
{
    public function deliver(string $recipient, string $token): void;
}
