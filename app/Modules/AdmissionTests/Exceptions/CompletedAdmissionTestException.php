<?php

declare(strict_types=1);

namespace App\Modules\AdmissionTests\Exceptions;

use RuntimeException;

final class CompletedAdmissionTestException extends RuntimeException
{
    public function __construct(private readonly int $admissionTestId)
    {
        parent::__construct('Admission Test already completed.');
    }

    public function getAdmissionTestId(): int
    {
        return $this->admissionTestId;
    }
}
