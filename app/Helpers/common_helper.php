<?php

if (! function_exists('display_session_msg')) {
    function display_session_msg(): string
    {
        return (string) (session('msg') ?? '');
    }
}

if (! function_exists('mcc_user_date')) {
    function mcc_user_date(?string $value, string $fallback = 'Not available'): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return $fallback;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', substr($value, 0, 10));

        if ($date === false || $date->format('Y-m-d') !== substr($value, 0, 10)) {
            $date = DateTimeImmutable::createFromFormat('!d-m-Y', substr($value, 0, 10));
        }

        return $date === false ? $value : $date->format('d-m-Y');
    }
}

if (! function_exists('mcc_user_datetime')) {
    function mcc_user_datetime(?string $value, string $fallback = 'Not available'): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return $fallback;
        }

        $date = date_create($value);

        return $date === false ? $value : $date->format('d-m-Y h:i A');
    }
}

if (! function_exists('mcc_follow_up_datetime')) {
    function mcc_follow_up_datetime(?string $value, string $fallback = 'Not available'): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return $fallback;
        }

        $utc = new DateTimeZone('UTC');
        $local = new DateTimeZone(config('App')->appTimezone ?: 'Asia/Kolkata');
        $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value, $utc);

        if ($date === false || $date->format('Y-m-d H:i:s') !== $value) {
            return $value;
        }

        return $date->setTimezone($local)->format('d-m-Y, h:i A');
    }
}

if (! function_exists('mcc_user_date_input')) {
    function mcc_user_date_input(?string $value): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return '';
        }

        return mcc_user_date($value, '');
    }
}

if (! function_exists('mcc_user_datetime_input')) {
    function mcc_user_datetime_input(?string $value): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return '';
        }

        $date = date_create($value);

        return $date === false ? $value : $date->format('d-m-Y\TH:i:sP');
    }
}

if (! function_exists('mcc_follow_up_status_presentation')) {
    /** @return array{label: string, class: string, overdue: bool} */
    function mcc_follow_up_status_presentation(?string $status, ?string $nextFollowUpAt = null): array
    {
        $status = strtolower(trim((string) $status));
        $nextFollowUpAt = trim((string) $nextFollowUpAt);
        $isPending = $status === 'pending';
        $nextTimestamp = $nextFollowUpAt === '' ? false : strtotime($nextFollowUpAt);
        $isOverdue = $isPending
            && $nextTimestamp !== false
            && $nextTimestamp <= time();
        $isScheduled = $isPending
            && ! $isOverdue
            && $nextTimestamp !== false
            && $nextTimestamp > time();

        if ($isOverdue) {
            return [
                'label' => 'Overdue',
                'class' => 'mcc-follow-up-status-overdue',
                'overdue' => true,
            ];
        }

        if ($isScheduled) {
            return [
                'label' => 'Scheduled',
                'class' => 'mcc-follow-up-status-scheduled',
                'overdue' => false,
            ];
        }

        $presentation = match ($status) {
            'pending' => ['label' => 'Pending', 'class' => 'mcc-follow-up-status-pending'],
            'scheduled' => ['label' => 'Scheduled', 'class' => 'mcc-follow-up-status-scheduled'],
            'completed' => ['label' => 'Completed', 'class' => 'mcc-follow-up-status-completed'],
            'cancelled' => ['label' => 'Cancelled', 'class' => 'mcc-follow-up-status-cancelled'],
            default => ['label' => $status === '' ? 'Unknown' : ucwords(str_replace('_', ' ', $status)), 'class' => 'mcc-follow-up-status-neutral'],
        };

        return $presentation + ['overdue' => false];
    }
}

if (! function_exists('mcc_admission_test_status_presentation')) {
    /** @return array{label: string, class: string} */
    function mcc_admission_test_status_presentation(?string $status): array
    {
        return match (strtoupper(trim((string) $status))) {
            'DRAFT' => ['label' => 'Draft', 'class' => 'mcc-badge-info'],
            'BASIC_DETAILS_COMPLETED', 'TEST_IN_PROGRESS' => ['label' => 'In progress', 'class' => 'mcc-badge-info'],
            'TEST_SUBMITTED' => ['label' => 'Test submitted', 'class' => 'mcc-badge-warning'],
            'MEETING_FOLLOW_UP' => ['label' => 'Meeting follow-up', 'class' => 'mcc-badge-warning'],
            'APPROVED_FOR_ADMISSION' => ['label' => 'Approved for admission', 'class' => 'mcc-badge-success'],
            'REJECTED' => ['label' => 'Rejected', 'class' => 'mcc-badge-danger'],
            'REJECTED_CLOSED' => ['label' => 'Rejected / Closed', 'class' => 'mcc-badge-danger'],
            'ON_HOLD' => ['label' => 'On hold', 'class' => 'mcc-badge-neutral'],
            default => ['label' => 'Not available', 'class' => 'mcc-badge-neutral'],
        };
    }
}

if (! function_exists('mcc_admission_status_presentation')) {
    /** @return array{label: string, class: string} */
    function mcc_admission_status_presentation(?string $status): array
    {
        return match (strtoupper(trim((string) $status))) {
            'INTAKE', 'PENDING' => ['label' => 'Pending Admission', 'class' => 'mcc-status-intake'],
            'IN_REVIEW' => ['label' => 'In Review', 'class' => 'mcc-status-in_review'],
            'DOCUMENTS_PENDING' => ['label' => 'Documents Pending', 'class' => 'mcc-status-documents_pending'],
            'APPROVED' => ['label' => 'Approved', 'class' => 'mcc-status-approved'],
            'COMPLETED' => ['label' => 'Completed', 'class' => 'mcc-status-completed'],
            'CONVERTED' => ['label' => 'Converted', 'class' => 'mcc-status-converted'],
            'REJECTED', 'WITHDRAWN', 'CLOSED' => ['label' => 'Rejected / Closed', 'class' => 'mcc-status-rejected'],
            default => ['label' => 'Not available', 'class' => 'mcc-status-neutral'],
        };
    }
}

if (! function_exists('mcc_admission_payment_status_presentation')) {
    /** @return array{label: string, class: string} */
    function mcc_admission_payment_status_presentation(?string $status): array
    {
        return match (strtolower(trim((string) $status))) {
            'partially paid' => [
                'label' => 'Partially paid',
                'class' => 'mcc-status-payment-partial',
            ],
            'paid' => [
                'label' => 'Paid',
                'class' => 'mcc-status-payment-paid',
            ],
            'overpaid' => [
                'label' => 'Overpaid',
                'class' => 'mcc-status-payment-overpaid',
            ],
            default => [
                'label' => 'Not captured',
                'class' => 'mcc-status-payment-neutral',
            ],
        };
    }
}

if (! function_exists('mcc_follow_up_page_window')) {
    /** @return list<int|string> */
    function mcc_follow_up_page_window(int $currentPage, int $totalPages): array
    {
        $totalPages = max(1, $totalPages);
        $currentPage = min(max(1, $currentPage), $totalPages);

        if ($totalPages <= 10) {
            return range(1, $totalPages);
        }

        $lastPageStart = $totalPages - 4;
        $windowStart = min($currentPage, $lastPageStart);
        $visiblePages = array_merge(
            range($windowStart, min($windowStart + 4, $totalPages)),
            range($lastPageStart, $totalPages),
        );
        $visiblePages = array_values(array_unique($visiblePages));
        sort($visiblePages, SORT_NUMERIC);

        $window = [];
        $previousPage = null;
        foreach ($visiblePages as $page) {
            if ($previousPage !== null && $page > $previousPage + 1) {
                $window[] = '…';
            }

            $window[] = $page;
            $previousPage = $page;
        }

        return $window;
    }
}
