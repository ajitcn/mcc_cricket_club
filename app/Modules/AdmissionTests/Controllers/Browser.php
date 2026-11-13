<?php

declare(strict_types=1);

namespace App\Modules\AdmissionTests\Controllers;

use App\Controllers\StaffController;
use App\Modules\AdmissionTests\Exceptions\CompletedAdmissionTestException;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

final class Browser extends StaffController
{
    public function index(): string
    {
        $filters = $this->request->getGet();
        $page = is_scalar($filters['page'] ?? null) && ctype_digit((string) $filters['page'])
            ? max(1, (int) $filters['page'])
            : 1;
        $result = service('admissionTest')->listForAuthorizedBranches($filters, $page);

        return $this->page('App\\Modules\\AdmissionTests\\Views\\index', 'Admission Test', $result);
    }

    public function start(int $trialId): ResponseInterface
    {
        try {
            $id = service('admissionTest')->createFromTrial($trialId);

            $test = service('admissionTest')->details($id);
            if (trim((string) ($test['test_variant'] ?? '')) === '') {
                return redirect()->to(site_url('admission-tests/' . $id . '/paper-set'))
                    ->with('message', 'Select one paper set to continue the Admission Test.');
            }

            return redirect()->to(site_url('admission-tests/' . $id . '/wizard'))
                ->with('message', 'Admission Test started.');
        } catch (CompletedAdmissionTestException $exception) {
            return redirect()->to(site_url('admission-tests/' . $exception->getAdmissionTestId()))
                ->with('error', 'Admission Test already completed.');
        } catch (Throwable $exception) {
            $reference = $this->logStartFailure($trialId, $exception);

            return redirect()->to(site_url('crm/trials'))
                ->with('error', $this->safe($exception) . ' Support reference: ' . $reference . '.');
        }
    }

    private function logStartFailure(int $trialId, Throwable $exception): string
    {
        $reference = 'ADT-' . strtoupper(bin2hex(random_bytes(5)));

        log_message('error', 'Admission Test start failed [{reference}] for Trial {trialId}: {type}: {message}', [
            'reference' => $reference,
            'trialId' => $trialId,
            'type' => $exception::class,
            'message' => $exception->getMessage(),
        ]);

        return $reference;
    }

    public function paperSet(int $id): ResponseInterface|string
    {
        try {
            $test = service('admissionTest')->details($id);
            if (trim((string) ($test['test_variant'] ?? '')) !== '') {
                return redirect()->to(site_url('admission-tests/' . $id . '/wizard'));
            }

            return $this->page('App\\Modules\\AdmissionTests\\Views\\paper_set', 'Select Admission Test paper set', [
                'test' => $test,
                'paperSets' => service('admissionTest')->paperSetOptions(),
            ]);
        } catch (Throwable $exception) {
            return $this->response
                ->setStatusCode($exception->getMessage() === 'Forbidden' ? 403 : 404)
                ->setBody(esc($this->safe($exception)));
        }
    }

    public function selectPaperSet(int $id): ResponseInterface
    {
        try {
            service('admissionTest')->selectPaperSet($id, $this->request->getPost());

            return redirect()->to(site_url('admission-tests/' . $id . '/wizard'))
                ->with('message', 'Paper set selected and locked.');
        } catch (Throwable $exception) {
            return redirect()->to(site_url('admission-tests/' . $id . '/paper-set'))
                ->withInput()
                ->with('error', $this->safe($exception));
        }
    }

    public function wizard(int $id): ResponseInterface|string
    {
        try {
            $test = service('admissionTest')->details($id);
            if (trim((string) ($test['test_variant'] ?? '')) === '') {
                return redirect()->to(site_url('admission-tests/' . $id . '/paper-set'));
            }
            $step = max(1, min(3, (int) ($this->request->getGet('step') ?? 1)));

            return $this->wizardPage($id, [], $step);
        } catch (Throwable $exception) {
            log_message('error', 'Admission Test wizard render failed: {message}', [
                'message' => $exception->getMessage(),
            ]);

            return $this->response
                ->setStatusCode($exception->getMessage() === 'Forbidden' ? 403 : 404)
                ->setBody(esc($this->safe($exception)));
        }
    }

    public function wizardPersonalDetails(int $id): ResponseInterface|string
    {
        $input = $this->request->getPost();

        try {
            $errors = service('admissionTest')->wizardValidationErrors($id, $input);

            if ($errors !== []) {
                return $this->wizardPage($id, $errors, 1, 422, $input);
            }

            service('admissionTest')->savePersonalDetails($id, $input);

            return redirect()->to(site_url('admission-tests/' . $id . '/wizard?step=2'))
                ->with('message', 'Personal details saved.');
        } catch (Throwable $exception) {
            return $this->wizardPage(
                $id,
                ['_form' => $this->safe($exception)],
                1,
                $exception->getMessage() === 'Forbidden' ? 403 : 422,
                $input,
            );
        }
    }

    public function wizardSubmit(int $id): ResponseInterface|string
    {
        try {
            $input = $this->request->getPost();
            $errors = service('admissionTest')->wizardValidationErrors($id, $input);

            if ($errors !== []) {
                return $this->wizardPage($id, $errors, $this->wizardErrorStep($errors), 422, $input);
            }

            service('admissionTest')->submitWizard($id, $input);

            return redirect()->to(site_url('admission-tests/' . $id))
                ->with('message', 'Admission Test submitted.');
        } catch (Throwable $exception) {
            return $this->wizardPage(
                $id,
                ['_form' => $this->safe($exception)],
                1,
                $exception->getMessage() === 'Forbidden' ? 403 : 422,
                $this->request->getPost(),
            );
        }
    }

    public function generateStudentLink(int $id): ResponseInterface
    {
        try {
            $url = service('admissionTest')->generateStudentLink($id);

            $returnToWizard = $this->request->getPost('return_to') === 'wizard';
            $destination = $returnToWizard
                ? site_url('admission-tests/' . $id . '/wizard')
                : site_url('admission-tests/' . $id);

            return redirect()->to($destination)
                ->with('student_test_link', $url)
                ->with('message', 'Student Test Link generated. Copy it now; it will be shown only once.');
        } catch (Throwable $exception) {
            return redirect()->to(site_url('admission-tests/' . $id))
                ->with('error', $this->safe($exception));
        }
    }

    public function studentWizard(string $token): ResponseInterface|string
    {
        try {
            $test = service('admissionTest')->studentDetails($token);
            if (trim((string) ($test['test_variant'] ?? '')) === '') {
                return $this->studentUnavailableResponse();
            }
            if (service('admissionTest')->isCompleted($test)) {
                return $this->studentUnavailableResponse();
            }

            $step = max(1, min(3, (int) ($this->request->getGet('step') ?? 1)));
            return $this->studentWizardPage($token, [], $step);
        } catch (Throwable) {
            return $this->studentUnavailableResponse();
        }
    }

    public function studentWizardPersonalDetails(string $token): ResponseInterface|string
    {
        $input = $this->request->getPost();

        try {
            $test = service('admissionTest')->studentDetails($token);
            $errors = service('admissionTest')->wizardValidationErrors((int) $test['id'], $input, false);
            if ($errors !== []) {
                return $this->studentWizardPage($token, $errors, 1, 422, $input);
            }

            service('admissionTest')->saveStudentPersonalDetails($token, $input);
            return redirect()->to(site_url('admission-tests/student/' . rawurlencode($token) . '?step=2'));
        } catch (Throwable) {
            return $this->studentUnavailableResponse();
        }
    }

    public function studentWizardSubmit(string $token): ResponseInterface|string
    {
        $input = $this->request->getPost();

        try {
            $test = service('admissionTest')->studentDetails($token);
            $errors = service('admissionTest')->wizardValidationErrors((int) $test['id'], $input, false);
            if ($errors !== []) {
                return $this->studentWizardPage($token, $errors, $this->wizardErrorStep($errors), 422, $input);
            }

            service('admissionTest')->submitStudentWizard($token, $input);
            return $this->studentThankYouPage();
        } catch (Throwable) {
            return $this->studentUnavailableResponse();
        }
    }

    public function show(int $id): ResponseInterface|string
    {
        try {
            $test = service('admissionTest')->details($id);
            return $this->page('App\\Modules\\AdmissionTests\\Views\\show', 'Admission Test', [
                'test' => $test,
                'questions' => service('admissionTest')->questionsForTest($test),
                'statuses' => service('admissionTest')->statuses(),
                'followUpOptions' => service('admissionTest')->followUpOptions(),
                'assignedUsers' => service('admissionTest')->assignableUsers(),
            ]);
        } catch (Throwable $exception) {
            return $this->response
                ->setStatusCode($exception->getMessage() === 'Forbidden' ? 403 : 404)
                ->setBody(esc($this->safe($exception)));
        }
    }

    public function personalDetails(int $id): ResponseInterface
    {
        try {
            service('admissionTest')->savePersonalDetails($id, $this->request->getPost());

            return redirect()->to(site_url('admission-tests/' . $id))
                ->with('message', 'Personal details saved.');
        } catch (Throwable $exception) {
            $fieldErrors = $this->personalDetailsErrors($exception);
            $isValidationFailure = ! isset($fieldErrors['_form']);
            $message = $isValidationFailure
                ? 'Invalid form data. Please correct the highlighted fields and try again.'
                : 'The personal details could not be saved.';

            if (! $isValidationFailure) {
                $reference = 'ADT-' . strtoupper(bin2hex(random_bytes(4)));
                log_message('error', 'Admission Test personal details update failed [{reference}] for test {testId}: {message}', [
                    'reference' => $reference,
                    'testId' => $id,
                    'message' => $exception->getMessage(),
                ]);
                $message .= ' Support reference: ' . $reference . '.';
            }

            return redirect()->to(site_url('admission-tests/' . $id . '#personal-details'))
                ->withInput()
                ->with('personal_details_errors', $fieldErrors)
                ->with('error', $message);
        }
    }

    public function personalDetailsPage(int $id): ResponseInterface
    {
        try {
            $test = service('admissionTest')->details($id);
            if (! service('authorization')->can('admission.update', (string) $test['branch_id'])) {
                return $this->response->setStatusCode(403)->setBody('Forbidden');
            }

            return redirect()->to(site_url('admission-tests/' . $id . '#personal-details'));
        } catch (Throwable $exception) {
            return $this->response
                ->setStatusCode($exception->getMessage() === 'Forbidden' ? 403 : 404)
                ->setBody(esc($this->safe($exception)));
        }
    }

    /** @return array<string, string> */
    private function personalDetailsErrors(Throwable $exception): array
    {
        return match ($exception->getMessage()) {
            'Applicant name is required.' => [
                'applicant_full_name' => 'Name of the Player is required.',
            ],
            'Invalid age.' => [
                'age' => 'Enter an age from 0 to 120.',
            ],
            'Invalid date of birth.' => [
                'applicant_date_of_birth' => 'Enter a valid date of birth as DD-MM-YYYY.',
            ],
            'Invalid Represented value.' => [
                'represented_as' => 'Select a valid representation option.',
            ],
            'Invalid coaching type.' => [
                'coaching_type' => 'Select a valid coaching type.',
            ],
            'Invalid Cricket skill / Ability value.' => [
                'playing_role' => 'Select a valid cricket skill / ability.',
            ],
            'Invalid nearest branch.' => [
                'nearest_branch' => 'Select a valid nearest branch.',
            ],
            'Invalid email address.' => [
                'applicant_email' => 'Enter a valid email address.',
                'email' => 'Enter a valid email address.',
                'secondary_email' => 'Enter a valid email address.',
            ],
            default => [
                '_form' => $this->safe($exception),
            ],
        };
    }

    public function basicTest(int $id): ResponseInterface
    {
        try {
            service('admissionTest')->saveBasicTest($id, $this->request->getPost());

            return redirect()->to(site_url('admission-tests/' . $id))
                ->with('message', 'Basic Test submitted.');
        } catch (Throwable $exception) {
            return $this->failure($id, $exception);
        }
    }

    public function startTest(int $id): ResponseInterface
    {
        try {
            service('admissionTest')->startBasicTest($id, $this->request->getPost());

            return redirect()->to(site_url('admission-tests/' . $id))
                ->with('message', 'Basic Test started.');
        } catch (Throwable $exception) {
            return $this->failure($id, $exception);
        }
    }

    public function followUp(int $id): ResponseInterface
    {
        try {
            service('admissionTest')->createFollowUp($id, $this->request->getPost());

            return redirect()->to(site_url('admission-tests/' . $id))
                ->with('message', 'Meeting follow-up added.');
        } catch (Throwable $exception) {
            return $this->failure($id, $exception);
        }
    }

    public function fees(int $id): ResponseInterface
    {
        try {
            service('admissionTest')->saveFees($id, $this->request->getPost());

            return redirect()->to(site_url('admission-tests/' . $id . '#admission-test-fees'))
                ->with('message', 'Admission Test fees saved.');
        } catch (Throwable $exception) {
            return $this->failure($id, $exception);
        }
    }

    public function assessmentRemarks(int $id): ResponseInterface
    {
        try {
            service('admissionTest')->saveAssessmentRemarks($id, $this->request->getPost());

            return redirect()->to(site_url('admission-tests/' . $id . '#review-heading'))
                ->with('message', 'Assessment remarks saved.');
        } catch (Throwable $exception) {
            return redirect()->to(site_url('admission-tests/' . $id . '#review-heading'))
                ->withInput()
                ->with('error', $this->safe($exception));
        }
    }

    public function pendingAdmission(int $id): ResponseInterface
    {
        try {
            $admissionId = service('admissionTest')->createPendingAdmission($id, $this->request->getPost());

            return redirect()->to(site_url('admission-tests/' . $id))
                ->with('message', 'Pending Admission created from Admission Test.');
        } catch (Throwable $exception) {
            return $this->failure($id, $exception);
        }
    }

    public function changeStatus(int $id): ResponseInterface
    {
        try {
            $result = service('admissionTest')->changeStatus($id, $this->request->getPost());

            if ($this->expectsJson()) {
                $presentation = mcc_admission_test_status_presentation($result['status']);

                return $this->response->setJSON([
                    'status' => true,
                    'message' => 'Admission Test status updated.',
                    'status_code' => $result['status'],
                    'status_label' => $presentation['label'],
                    'status_class' => $presentation['class'],
                    'version' => $result['version'],
                ]);
            }

            return redirect()->to(site_url('admission-tests'))
                ->with('message', 'Admission Test status updated.');
        } catch (Throwable $exception) {
            if ($this->expectsJson()) {
                $statusCode = $exception->getMessage() === 'Forbidden' ? 403 : 422;

                return $this->response
                    ->setStatusCode($statusCode)
                    ->setJSON([
                        'status' => false,
                        'message' => $this->safe($exception),
                        'errors' => $this->statusChangeErrors($exception),
                    ]);
            }

            return redirect()->to(site_url('admission-tests'))
                ->withInput()
                ->with('error', $this->safe($exception));
        }
    }

    private function expectsJson(): bool
    {
        return $this->request->isAJAX()
            || str_contains(strtolower($this->request->getHeaderLine('Accept')), 'application/json');
    }

    /** @return array<string, string> */
    private function statusChangeErrors(Throwable $exception): array
    {
        return match ($exception->getMessage()) {
            'Select a valid Admission Test status.' => ['status' => 'Select a valid status.'],
            'A reason is required for rejected or on-hold Admission Tests.' => ['reason' => 'A reason is required for Rejected and On hold.'],
            'The status reason must not exceed 255 characters.' => ['reason' => 'Keep the reason within 255 characters.'],
            'Admission Test is already in that status.' => ['status' => 'Choose a different status.'],
            'Invalid Admission Test status transition.' => ['status' => 'This status transition is not allowed.'],
            'Update conflict.' => ['_form' => 'This Admission Test changed elsewhere. Refresh the list and try again.'],
            default => ['_form' => $this->safe($exception)],
        };
    }

    private function failure(int $id, Throwable $exception): ResponseInterface
    {
        $message = $this->safe($exception);
        $fieldErrors = $this->followUpFieldErrors($exception);
        $reference = null;

        log_message('error', 'Admission Test action failed [{type}]: {detail}', [
            'type' => $exception::class,
            'detail' => $exception->getMessage(),
        ]);

        if ($message === 'The request could not be completed.') {
            $reference = strtoupper(bin2hex(random_bytes(5)));
            log_message('error', 'Admission Test action support reference {reference} for {type}', [
                'reference' => $reference,
                'type' => $exception::class,
            ]);
            $message .= ' Support reference: ' . $reference . '.';
        }

        $response = redirect()->to(site_url('admission-tests/' . $id))
            ->withInput()
            ->with('error', $message);

        return $fieldErrors === [] ? $response : $response->with('field_errors', $fieldErrors);
    }

    /** @return array<string, string> */
    private function followUpFieldErrors(Throwable $exception): array
    {
        return match ($exception->getMessage()) {
            'Invalid follow-up date.' => ['follow_up_date' => 'Enter a valid follow-up date.'],
            'Invalid follow-up time.' => ['follow_up_time' => 'Enter a valid follow-up time.'],
            'Invalid Admission Test follow-up.' => [
                'follow_up_type' => 'Select a valid follow-up type.',
                'status' => 'Select a valid status.',
                'notes' => 'Enter follow-up notes.',
            ],
            'Invalid assigned user.' => ['assigned_user_id' => 'Select an authorized assigned user.'],
            default => [],
        };
    }

    private function wizardPage(
        int $id,
        array $fieldErrors = [],
        int $step = 1,
        int $statusCode = 200,
        array $input = [],
    ): ResponseInterface|string {
        $test = service('admissionTest')->details($id);
        if (trim((string) ($test['test_variant'] ?? '')) === '') {
            return redirect()->to(site_url('admission-tests/' . $id . '/paper-set'));
        }

        $data = [
            'test' => $test,
            'questions' => service('admissionTest')->questionsForTest($test),
            'fieldErrors' => $fieldErrors,
            'wizardStep' => $step,
            'wizardInput' => $input,
            'canGenerateStudentLink' => service('authorization')->can(
                'admission.update',
                (string) ($test['branch_id'] ?? ''),
            ),
            'studentTestLink' => session()->getFlashdata('student_test_link'),
        ];

        $view = $this->page('App\\Modules\\AdmissionTests\\Views\\wizard', 'Admission Test wizard', $data);

        return $statusCode === 200
            ? $view
            : $this->response->setStatusCode($statusCode)->setBody($view);
    }

    private function studentWizardPage(
        string $token,
        array $fieldErrors = [],
        int $step = 1,
        int $statusCode = 200,
        array $input = [],
    ): ResponseInterface|string {
        $test = service('admissionTest')->studentDetails($token);
        $paperSet = strtoupper(trim((string) ($test['test_variant'] ?? '')));
        if (! array_key_exists($paperSet, service('admissionTest')->paperSetOptions())
            || service('admissionTest')->isCompleted($test)) {
            return $this->studentUnavailableResponse();
        }

        $view = view('App\\Modules\\AdmissionTests\\Views\\wizard', [
            'title' => 'Admission Test',
            'nav' => [],
            'message' => null,
            'error' => null,
            'breadcrumbTrail' => [],
            'test' => $test,
            'questions' => service('admissionTest')->questionsForTest($test),
            'fieldErrors' => $fieldErrors,
            'wizardStep' => $step,
            'wizardInput' => $input,
            'studentPublic' => true,
            'studentLinkToken' => $token,
        ]);

        return $statusCode === 200
            ? $view
            : $this->response->setStatusCode($statusCode)->setBody($view);
    }

    private function studentThankYouPage(): string
    {
        return view('App\\Modules\\AdmissionTests\\Views\\student_thank_you', [
            'title' => 'Admission Test submitted',
            'nav' => [],
            'message' => null,
            'error' => null,
            'breadcrumbTrail' => [],
        ]);
    }

    private function studentUnavailableResponse(): ResponseInterface
    {
        return $this->response
            ->setStatusCode(404)
            ->setBody('Student test link unavailable or expired.');
    }

    private function wizardErrorStep(array $errors): int
    {
        foreach (array_keys($errors) as $field) {
            if (str_starts_with($field, 'answer_')) {
                return 2;
            }
        }

        return 1;
    }
}
