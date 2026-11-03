<?php

declare(strict_types=1);

namespace App\Modules\Admissions\Controllers;

use App\Controllers\StaffController;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

final class FollowUps extends StaffController
{
    public function index(): string
    {
        $filters = $this->request->getGet();
        $result = service('admissionFollowUp')->paginated($filters, $this->pageNumber($filters['page'] ?? null));

        return $this->page('App\Modules\Admissions\Views\followups', 'Admission Follow-ups', $result + [
            'branches' => service('crm')->availableBranches(),
            'admissions' => service('admissionFollowUp')->admissionsForSelection(),
            'assignedUsers' => service('admissionFollowUp')->assignableUsers(),
            'options' => service('admissionFollowUp')->options(),
            'summaryCounts' => service('admissionFollowUp')->summaryCounts($filters),
            'canCreate' => service('authorization')->can('admission.create'),
            'canUpdate' => service('authorization')->can('admission.update'),
        ]);
    }

    private function pageNumber(mixed $value): int
    {
        if (is_array($value)) {
            foreach ($value as $candidate) {
                $page = $this->pageNumber($candidate);
                if ($page > 1 || $candidate === '1' || $candidate === 1) {
                    return $page;
                }
            }

            return 1;
        }

        if (! is_scalar($value)) {
            return 1;
        }

        $value = trim((string) $value);
        if ($value === '' || ! ctype_digit($value)) {
            return 1;
        }

        $page = filter_var($value, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        return $page === false ? 1 : $page;
    }

    public function newPage(): string
    {
        $admissionId = filter_var($this->request->getGet('admission_id'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        return $this->page('App\Modules\Admissions\Views\followup_form', 'New Admission Follow-up', [
            'formMode' => 'new',
            'followUp' => $admissionId === false ? [] : ['admission_id' => $admissionId],
            'admissions' => service('admissionFollowUp')->admissionsForSelection(),
            'assignedUsers' => service('admissionFollowUp')->assignableUsers(),
            'options' => service('admissionFollowUp')->options(),
        ]);
    }

    public function create(): ResponseInterface
    {
        try {
            $id = service('admissionFollowUp')->create($this->request->getPost());

            return redirect()->to(site_url('admissions/followups/' . $id))
                ->with('message', 'Admission follow-up recorded.');
        } catch (Throwable $exception) {
            return redirect()->to(site_url('admissions/followups/new'))
                ->withInput()
                ->with('error', $this->followUpError($exception))
                ->with('field_errors', $this->followUpFieldErrors($exception, $this->request->getPost()));
        }
    }

    public function show(int $id): ResponseInterface|string
    {
        try {
            $followUp = service('admissionFollowUp')->find($id);
            $branch = (string) $followUp['branch_id'];
            $admissionFilters = array_filter([
                'admission_number' => trim((string) ($followUp['admission_number'] ?? '')),
                'q' => trim((string) ($followUp['applicant_name'] ?? '')),
            ], static fn ($value): bool => $value !== '');

            return $this->page('App\Modules\Admissions\Views\followup_show', 'Follow-up Details', [
                'followUp' => $followUp,
                'followUpHistory' => service('admissionFollowUp')->forAdmission((int) $followUp['admission_id']),
                'auditHistory' => service('admissionFollowUp')->auditHistory($id, (int) $followUp['branch_id']),
                'canView' => service('authorization')->can('admission.view', $branch),
                'canUpdate' => service('authorization')->can('admission.update', $branch),
                'canCreate' => service('authorization')->can('admission.create', $branch)
                    && service('admissionFollowUp')->canCreateForAdmission($followUp),
                'admissionListUrl' => site_url('admissions') . ($admissionFilters === [] ? '' : '?' . http_build_query($admissionFilters)),
            ]);
        } catch (Throwable $exception) {
            return $this->response
                ->setStatusCode($exception->getMessage() === 'Forbidden' ? 403 : 404)
                ->setBody(esc($this->safe($exception)));
        }
    }

    public function editPage(int $id): ResponseInterface|string
    {
        try {
            $followUp = service('admissionFollowUp')->find($id);

            return $this->page('App\Modules\Admissions\Views\followup_form', 'Edit Admission Follow-up', [
                'formMode' => 'edit',
                'followUp' => $followUp,
                'admissions' => service('admissionFollowUp')->admissionsForSelection(),
                'assignedUsers' => service('admissionFollowUp')->assignableUsers(),
                'options' => service('admissionFollowUp')->options(),
                'canScheduleNext' => service('admissionFollowUp')->canCreateForAdmission($followUp),
            ]);
        } catch (Throwable $exception) {
            return $this->response
                ->setStatusCode($exception->getMessage() === 'Forbidden' ? 403 : 404)
                ->setBody(esc($this->safe($exception)));
        }
    }

    public function update(int $id): ResponseInterface
    {
        try {
            $input = $this->request->getPost();
            $nextId = service('admissionFollowUp')->update($id, $input);
            $savedId = $nextId ?? $id;
            $message = $nextId === null
                ? 'Admission follow-up updated.'
                : 'Admission follow-up updated and next follow-up scheduled.';

            return redirect()->to(site_url('admissions/followups/' . $savedId))
                ->with('message', $message);
        } catch (Throwable $exception) {
            return redirect()->to(site_url('admissions/followups/' . $id . '/edit'))
                ->withInput()
                ->with('error', $this->followUpError($exception))
                ->with('field_errors', $this->followUpFieldErrors($exception, $this->request->getPost()));
        }
    }

    private function followUpFieldErrors(Throwable $exception, array $input): array
    {
        $message = $exception->getMessage();

        if ($message === 'Invalid Admission follow-up.') {
            foreach (['follow_up_type', 'status', 'notes', 'outcome'] as $field) {
                if ($field === 'notes' && trim((string) ($input[$field] ?? '')) === '') {
                    return [$field => 'Enter notes for this follow-up.'];
                }

                if ($field === 'follow_up_type' && ! in_array(
                    strtolower(trim((string) ($input[$field] ?? ''))),
                    service('admissionFollowUp')->options()['types'],
                    true,
                )) {
                    return [$field => 'Select a valid follow-up type.'];
                }

                if ($field === 'status' && ! in_array(
                    strtolower(trim((string) ($input[$field] ?? ''))),
                    service('admissionFollowUp')->options()['statuses'],
                    true,
                )) {
                    return [$field => 'Select a valid follow-up status.'];
                }
            }
        }

        if ($message === 'Invalid next follow-up date.') {
            return [
                'next_follow_up_at' => 'Enter a valid next follow-up date and time.',
                'next_follow_up_time' => 'Enter a valid next follow-up date and time.',
            ];
        }

        if ($message === 'Invalid schedule option.') {
            return ['schedule_next_follow_up' => 'Use a valid scheduling option.'];
        }

        return match ($message) {
            'Invalid Admission.' => ['admission_id' => 'Select an authorized Admission.'],
            'Invalid follow-up date.' => ['follow_up_at' => 'Enter a valid follow-up date and time.'],
            'Invalid assigned user.' => ['assigned_user_id' => 'Select an authorized assigned user.'],
            'Invalid Admission follow-up.' => ['notes' => 'Enter valid follow-up details.'],
            default => [],
        };
    }

    private function followUpError(Throwable $exception): string
    {
        $safe = $this->safe($exception);
        $reference = 'ERR-' . strtoupper(substr(hash('sha256', implode('|', [
            $exception::class,
            $exception->getFile(),
            (string) $exception->getLine(),
            $exception->getMessage(),
        ])), 0, 8));

        log_message(
            'error',
            'Admission follow-up save failed [{reference}] {type}: {message}',
            [
                'reference' => $reference,
                'type' => $exception::class,
                'message' => $exception->getMessage(),
            ],
        );

        if (ENVIRONMENT === 'development' && $exception->getMessage() !== '') {
            return $exception->getMessage() . ' Reference: ' . $reference;
        }

        if ($safe === 'The request could not be completed.') {
            return $safe . ' Reference: ' . $reference;
        }

        return $safe;
    }
}
