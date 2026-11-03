<?php

declare(strict_types=1);

namespace App\Modules\MeetingFollowUps\Controllers;

use App\Controllers\StaffController;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

final class Browser extends StaffController
{
    public function index(): string
    {
        $filters = $this->request->getGet();
        $result = service('meetingFollowUp')->list($filters, $this->pageNumber($filters['page'] ?? null));

        return $this->page('App\\Modules\\MeetingFollowUps\\Views\\index', 'Meeting Follow-up', $result + [
            'branches' => service('crm')->availableBranches(),
            'admissionTests' => service('meetingFollowUp')->admissionTestsForSelection(),
            'assignedUsers' => service('meetingFollowUp')->assignableUsers(),
            'options' => service('meetingFollowUp')->options(),
        ]);
    }

    public function newPage(): string
    {
        return $this->page('App\\Modules\\MeetingFollowUps\\Views\\form', 'New Meeting Follow-up', [
            'followUp' => ['admission_test_id' => $this->request->getGet('admission_test_id')],
            'admissionTests' => service('meetingFollowUp')->admissionTestsForSelection(),
            'assignedUsers' => service('meetingFollowUp')->assignableUsers(),
            'options' => service('meetingFollowUp')->options(),
            'formMode' => 'new',
        ]);
    }

    public function create(): ResponseInterface
    {
        try {
            $id = service('meetingFollowUp')->create($this->request->getPost());
            return redirect()->to(site_url('meeting-followups/' . $id))->with('message', 'Meeting Follow-up recorded.');
        } catch (Throwable $exception) {
            return $this->failure('meeting-followups/new', $exception);
        }
    }

    public function show(int $id): ResponseInterface|string
    {
        try {
            $followUp = service('meetingFollowUp')->find($id);
            return $this->page('App\\Modules\\MeetingFollowUps\\Views\\show', 'Meeting Follow-up', [
                'followUp' => $followUp,
                'canUpdate' => service('authorization')->can('admission.update', (string) $followUp['branch_id']),
            ]);
        } catch (Throwable $exception) {
            return $this->response->setStatusCode($exception->getMessage() === 'Forbidden' ? 403 : 404)->setBody(esc($this->safe($exception)));
        }
    }

    public function editPage(int $id): ResponseInterface|string
    {
        try {
            return $this->page('App\\Modules\\MeetingFollowUps\\Views\\form', 'Edit Meeting Follow-up', [
                'followUp' => service('meetingFollowUp')->find($id),
                'admissionTests' => service('meetingFollowUp')->admissionTestsForSelection(),
                'assignedUsers' => service('meetingFollowUp')->assignableUsers(),
                'options' => service('meetingFollowUp')->options(),
                'formMode' => 'edit',
            ]);
        } catch (Throwable $exception) {
            return $this->response->setStatusCode($exception->getMessage() === 'Forbidden' ? 403 : 404)->setBody(esc($this->safe($exception)));
        }
    }

    public function update(int $id): ResponseInterface
    {
        try {
            service('meetingFollowUp')->update($id, $this->request->getPost());
            return redirect()->to(site_url('meeting-followups/' . $id))->with('message', 'Meeting Follow-up updated.');
        } catch (Throwable $exception) {
            return $this->failure('meeting-followups/' . $id . '/edit', $exception);
        }
    }

    private function pageNumber(mixed $value): int
    {
        return is_scalar($value) && ctype_digit((string) $value) ? max(1, (int) $value) : 1;
    }

    private function failure(string $path, Throwable $exception): ResponseInterface
    {
        $message = $this->safe($exception);
        $fieldErrors = $this->fieldErrors($exception);
        if ($fieldErrors !== []) {
            return redirect()->to(site_url($path))
                ->withInput()
                ->with('error', $message)
                ->with('field_errors', $fieldErrors);
        }

        $unexpected = $message === 'The request could not be completed.'
            || in_array($exception->getMessage(), [
                'Unable to create Meeting Follow-up.',
                'Unable to save Meeting Follow-up.',
            ], true);

        if ($unexpected) {
            $reference = strtoupper(bin2hex(random_bytes(5)));
            log_message('error', 'Meeting Follow-up failure [{reference}] {type}', [
                'reference' => $reference,
                'type' => $exception::class,
            ]);
            $message .= ' Reference: ' . $reference;
        }

        return redirect()->to(site_url($path))->withInput()->with('error', $message);
    }

    /** @return array<string, string> */
    private function fieldErrors(Throwable $exception): array
    {
        return match ($exception->getMessage()) {
            'Invalid Admission Test.' => ['admission_test_id' => 'Select a valid authorized Admission Test.'],
            'Invalid Meeting Follow-up.' => ['follow_up_type' => 'Select a valid follow-up type.', 'status' => 'Select a valid status.'],
            'Invalid follow-up date.' => ['start_at' => 'Enter a valid start date and time.'],
            'Invalid assigned user.' => ['assigned_user_id' => 'Select an authorized assigned user.'],
            default => [],
        };
    }
}
