<?php

declare(strict_types=1);

namespace App\Modules\MeetingFollowUps\Services;

use RuntimeException;

final class MeetingFollowUpValidationException extends RuntimeException
{
    /**
     * @param array<string, string> $fieldErrors
     */
    public function __construct(private readonly array $fieldErrors)
    {
        $message = 'Invalid meeting follow-up details.';

        if (array_keys($fieldErrors) === ['start_at']) {
            $message = 'Invalid follow-up date.';
        } elseif (array_keys($fieldErrors) === ['assigned_user_id']) {
            $message = 'Invalid assigned user.';
        } elseif (array_keys($fieldErrors) === ['follow_up_type', 'status']) {
            $message = 'Invalid Meeting Follow-up.';
        }

        parent::__construct($message);
    }

    /**
     * @return array<string, string>
     */
    public function fieldErrors(): array
    {
        return $this->fieldErrors;
    }
}
