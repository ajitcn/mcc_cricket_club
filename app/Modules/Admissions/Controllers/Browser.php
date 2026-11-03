<?php

declare(strict_types=1);

namespace App\Modules\Admissions\Controllers;

use App\Controllers\StaffController;
use CodeIgniter\HTTP\Files\UploadedFile;
use Throwable;

final class Browser extends StaffController
{
    public function index(): string
    {
        $filters = $this->request->getGet();
        $result = service('admission')->paginatedForAuthorizedBranches(
            $filters,
            $this->pageNumber($filters['page'] ?? null),
        );
        $statusOptions = array_map(
            static function (array $option): array {
                if (($option['value'] ?? '') === 'APPROVED') {
                    $option['label'] = 'Approved';
                }

                return $option;
            },
            service('admission')->statusOptions(),
        );
        $statusOptions[] = ['value' => 'COMPLETED', 'label' => 'Completed'];

        return $this->page('App\Modules\Admissions\Views\index', 'Admissions', $result + [
            'statuses' => service('admission')->statuses(),
            'statusOptions' => $statusOptions,
            'statusSummary' => service('admission')->statusSummaryForAuthorizedBranches($filters),
            'filters' => $filters,
            'batches' => service('admission')->availableBatches(),
            'trials' => service('admission')->availableTrials(),
            'can_create' => service('authorization')->can('admission.create'),
        ]);
    }

    public function feeInstallments(): string
    {
        $filters = $this->request->getGet();
        $result = service('admissionInstallments')->paginatedForAuthorizedBranches(
            $filters,
            $this->pageNumber($filters['page'] ?? null),
        );

        return $this->page(
            'App\\Modules\\Admissions\\Views\\fee_installments',
            'Fee Installments',
            $result,
        );
    }

    public function paymentPage(string $type, string|int $installmentId): string|\CodeIgniter\HTTP\ResponseInterface
    {
        $normalizedInstallmentId = $this->normalizePaymentInstallmentId($installmentId);
        if ($normalizedInstallmentId === null) {
            return $this->invalidPaymentResponse();
        }

        try {
            $data = [
                'installment' => service('admissionInstallmentPayment')->paymentForm(
                    $type,
                    $normalizedInstallmentId,
                ),
                'paymentMethods' => service('admissionInstallmentPayment')->paymentMethods(),
                'fieldErrors' => session()->getFlashdata('field_errors') ?: [],
            ];

            return $this->page(
                'App\\Modules\\Admissions\\Views\\fee_installment_payment',
                'Record Payment',
                $data,
            );
        } catch (Throwable $e) {
            return $this->response
                ->setStatusCode($e->getMessage() === 'Forbidden' ? 403 : 404)
                ->setBody(esc($this->paymentError($e)));
        }
    }

    public function paymentMissingId(string $type): \CodeIgniter\HTTP\ResponseInterface
    {
        return $this->invalidPaymentResponse();
    }

    public function recordPayment(string $type, string|int $installmentId): \CodeIgniter\HTTP\ResponseInterface|string
    {
        $normalizedInstallmentId = $this->normalizePaymentInstallmentId($installmentId);
        if ($normalizedInstallmentId === null) {
            return $this->invalidPaymentResponse();
        }

        $target = '/admissions/fee-installments/' . rawurlencode($type) . '/' . $normalizedInstallmentId . '/payment';

        try {
            $input = $this->request->getPost();
            service('admissionInstallmentPayment')->record($type, $normalizedInstallmentId, $input);

            return redirect()
                ->to($target)
                ->with('message', 'Payment receipt recorded successfully.');
        } catch (Throwable $e) {
            $message = $this->paymentError($e);

            return redirect()
                ->to($target)
                ->withInput()
                ->with('error', $message)
                ->with('field_errors', $this->paymentFieldErrors($e));
        }
    }

    private function normalizePaymentInstallmentId(string|int $value): ?int
    {
        if (is_int($value) && $value > 0) {
            return $value;
        }

        if (! is_string($value) || preg_match('/^[1-9][0-9]*$/', $value) !== 1) {
            return null;
        }

        $id = filter_var($value, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        return $id === false ? null : (int) $id;
    }

    private function invalidPaymentResponse(): \CodeIgniter\HTTP\ResponseInterface
    {
        return $this->response
            ->setStatusCode(404)
            ->setBody('The installment record was not found.');
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
        return $this->page('App\Modules\Admissions\Views\new', 'New Admission', [
            'formMode' => 'new',
            'suppressGlobalAlerts' => true,
            'branches' => service('crm')->availableBranches(),
            'leads' => service('admission')->eligibleLeads(),
            'batches' => service('admission')->availableBatches(),
            'trials' => service('admission')->availableTrials(),
            'coaches' => service('admission')->coachesForAuthorizedBranches(),
            'statusOptions' => service('admission')->statusOptions(),
            'admissionOptions' => service('admission')->profileOptions(),
            'admissionMasters' => service('admissionMaster')->activeForAuthorizedBranches(),
            'can_document_upload' => service('authorization')->can('document.upload'),
        ]);
    }
    public function createDirect(): \CodeIgniter\HTTP\ResponseInterface|string
    {
        $data = [];

        try {
            $data = $this->request->getPost();
            $this->assertFormStatus($data);
            $leadValue = trim((string) ($data['lead_id'] ?? ''));
            if ($leadValue !== '' && (! ctype_digit($leadValue) || (int) $leadValue < 1)) {
                throw new \RuntimeException('Invalid Lead.');
            }
            $id = $leadValue !== ''
                ? service('admission')->createFromLead((int) $leadValue, $data)
                : service('admission')->createDirect($data);
            $this->applyFormStatus($id, $data, 'INTAKE');
            if ($this->hasUpload('profile_photo') || $this->hasUpload('document')) {
                $admission = service('admission')->details($id);
                $this->uploadOptional($id, (int) $admission['branch_id'], 'profile_photo', 'player_photo');
                $this->uploadOptional($id, (int) $admission['branch_id'], 'document', (string) ($data['document_type'] ?? ''));
            }
            return redirect()->to('/admissions/' . $id . '/page')->with('message', 'Admission created successfully.');
        } catch (Throwable $e) {
            return redirect()->to('/admissions/new')->withInput()->with('error', $this->admissionError($e))
                ->with('field_errors', $this->admissionFieldErrors($e, $data));
        }
    }
    public function showPage(int $id): \CodeIgniter\HTTP\ResponseInterface|string
    {
        try {
            $a = service('admission')->details($id);
            $branch = (string) $a['branch_id'];
            $data = [
                'admission' => $a,
                'statuses' => service('admission')->statuses(),
                'can_view_player' => service('authorization')->can('player.view', $branch),
                'documents' => service('authorization')->can('document.view', $branch)
                    ? service('document')->forAdmission($id)
                    : [],
                'can_document_upload' => service('authorization')->can('document.upload', $branch),
                'can_add_follow_up' => service('authorization')->can('admission.create', $branch)
                    && service('admissionFollowUp')->canCreateForAdmission($a),
                'can_download' => service('authorization')->can('admission.view', $branch),
                'can_update_coaches' => service('authorization')->can('admission.update', $branch),
            ];

            if ($this->request->getGet('fragment') === '1') {
                $data['fragment'] = true;

                return view('App\Modules\Admissions\Views\show', $data);
            }

            return $this->page('App\Modules\Admissions\Views\show', 'Admission details', $data);
        } catch (Throwable $e) {
            return $this->response->setStatusCode($e->getMessage() === 'Forbidden' ? 403 : 404)->setBody(esc($this->safe($e)));
        }
    }

    public function download(int $id): \CodeIgniter\HTTP\ResponseInterface
    {
        try {
            $admission = service('admission')->details($id);
            $number = trim((string) ($admission['admission_number'] ?? ''));
            $safeNumber = preg_replace('/[^A-Za-z0-9_-]+/', '-', $number) ?: 'Missing-number';
            $safeNumber = trim($safeNumber, '-_') ?: 'Missing-number';
            $filename = 'MCC-Admission-' . $safeNumber . '.pdf';
            $pdf = service('admissionPdf')->render($admission);

            return $this->response
                ->setHeader('Content-Type', 'application/pdf')
                ->setHeader('Content-Length', (string) strlen($pdf))
                ->setHeader('X-Content-Type-Options', 'nosniff')
                ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
                ->setBody($pdf);
        } catch (Throwable $e) {
            return $this->response
                ->setStatusCode($e->getMessage() === 'Forbidden' ? 403 : 404)
                ->setBody($e->getMessage() === 'Forbidden' ? 'Forbidden' : 'Not found.');
        }
    }
    public function editPage(int $id): \CodeIgniter\HTTP\ResponseInterface|string
    {
        try {
            $admission = service('admission')->details($id);
            $admission = service('admission')->applyLinkedAdmissionTestFeeDefaults($admission);
            $data = [
                'admission' => $admission,
                'branches' => service('crm')->availableBranches(),
                'leads' => service('admission')->eligibleLeads((int) ($admission['lead_id'] ?? 0)),
                'batches' => service('admission')->batchesForBranch(
                    (int) $admission['branch_id'],
                    (int) ($admission['batch_id'] ?? 0),
                ),
                'trials' => service('admission')->trialsForBranch((int) $admission['branch_id']),
                'coaches' => service('admission')->coachesForBranch(
                    (int) $admission['branch_id'],
                    (int) ($admission['coach_user_id'] ?? 0),
                    (int) ($admission['personal_coach_user_id'] ?? 0),
                ),
                'statusOptions' => service('admission')->statusOptions(),
                'admissionOptions' => service('admission')->profileOptions(),
                'admissionMasters' => service('admissionMaster')->forAdmissionEdit((int) $admission['branch_id'], $admission),
            ];

            $data['suppressGlobalAlerts'] = true;

            return $this->page('App\Modules\Admissions\Views\edit', 'Edit Admission', $data);
        } catch (Throwable $e) {
            return $this->response->setStatusCode($e->getMessage() === 'Forbidden' ? 403 : 404)->setBody(esc($this->safe($e)));
        }
    }
    public function edit(int $id): \CodeIgniter\HTTP\ResponseInterface|string
    {
        try {
            $data = $this->request->getPost();
            $this->assertFormStatus($data);
            $statusChanged = service('admission')->updateIntake($id, $data);
            $redirect = $statusChanged ? '/admissions/' . $id . '/page' : '/admissions/' . $id . '/edit';

            return redirect()->to($redirect)->with('message', 'Admission updated successfully.');
        } catch (Throwable $e) {
            return redirect()->to('/admissions/' . $id . '/edit')->withInput()->with('error', $this->admissionError($e))
                ->with('field_errors', $this->admissionFieldErrors($e, $this->request->getPost()));
        }
    }

    public function updateCoachesPage(int $id): \CodeIgniter\HTTP\ResponseInterface|string
    {
        try {
            $data = service('admission')->coachCorrectionForm($id);
            $data['fieldErrors'] = session()->getFlashdata('field_errors') ?: [];
            $data['suppressGlobalAlerts'] = true;

            return $this->page(
                'App\\Modules\\Admissions\\Views\\update_coaches',
                'Update Admission Coaches',
                $data,
            );
        } catch (Throwable $e) {
            return $this->response
                ->setStatusCode($e->getMessage() === 'Forbidden' ? 403 : 404)
                ->setBody(esc($this->safe($e)));
        }
    }

    public function updateCoaches(int $id): \CodeIgniter\HTTP\ResponseInterface|string
    {
        try {
            service('admission')->updateCoachAssignments($id, $this->request->getPost());

            return redirect()
                ->to('/admissions/' . $id . '/page')
                ->with('message', 'Admission coaches updated successfully.');
        } catch (Throwable $e) {
            return redirect()
                ->to('/admissions/' . $id . '/update-coaches')
                ->withInput()
                ->with('error', $this->admissionError($e))
                ->with('field_errors', $this->admissionFieldErrors($e, $this->request->getPost()));
        }
    }

    public function renewPage(int $id): \CodeIgniter\HTTP\ResponseInterface|string
    {
        try {
            $data = service('admission')->renewalForm($id);
            $data['admissionOptions'] = service('admission')->profileOptions();
            $data['admissionMasters'] = service('admissionMaster')->forAdmissionEdit(
                (int) ($data['admission']['branch_id'] ?? 0),
                $data['admission'],
            );
            $data['suppressGlobalAlerts'] = false;

            return $this->page('App\\Modules\\Admissions\\Views\\renew', 'Renew Admission', $data);
        } catch (Throwable $e) {
            return $this->response
                ->setStatusCode($e->getMessage() === 'Forbidden' ? 403 : 404)
                ->setBody(esc($this->safe($e)));
        }
    }

    public function renew(int $id): \CodeIgniter\HTTP\ResponseInterface|string
    {
        try {
            service('admission')->renew($id, $this->request->getPost());

            return redirect()
                ->to('/admissions/' . $id . '/page')
                ->with('message', 'Admission renewed successfully.');
        } catch (Throwable $e) {
            return redirect()
                ->to('/admissions/' . $id . '/renew')
                ->withInput()
                ->with('error', $this->admissionError($e))
                ->with('field_errors', $this->admissionFieldErrors($e, $this->request->getPost()));
        }
    }
    public function completePage(int $id): string|\CodeIgniter\HTTP\ResponseInterface
    {
        try {
            $admission = service('admission')->details($id);
            if (empty($admission['can_complete'])) {
                return $this->response->setStatusCode(404)->setBody('Admission is not pending.');
            }
            return $this->page('App\\Modules\\Admissions\\Views\\complete', 'Complete Admission', [
                'admission' => $admission,
                'batches' => service('admission')->batchesForBranch((int) $admission['branch_id']),
                'admissionOptions' => service('admission')->profileOptions(),
            ]);
        } catch (Throwable $e) {
            return $this->response->setStatusCode($e->getMessage() === 'Forbidden' ? 403 : 404)->setBody(esc($this->safe($e)));
        }
    }
    public function complete(int $id): \CodeIgniter\HTTP\ResponseInterface|string
    {
        try {
            $player = service('admission')->complete($id, $this->request->getPost());
            return redirect()->to('/players/' . $player)->with('message', 'Admission completed and player created.');
        } catch (Throwable $e) {
            return redirect()->to('/admissions/' . $id . '/complete')->withInput()->with('error', $this->safe($e))
                ->with('field_errors', $this->admissionFieldErrors($e, $this->request->getPost()));
        }
    }
    public function create(int $lead): \CodeIgniter\HTTP\ResponseInterface|string
    {
        try {
            $id = service('admission')->createFromLead($lead);
            if (!$this->browser()) {
                return $this->response->setStatusCode(201)->setJSON(['id' => $id]);
            }
            return redirect()->to('/admissions/' . $id . '/page')->with('message', 'Admission created successfully.');
        } catch (Throwable $e) {
            if (!$this->browser()) {
                return $this->response->setStatusCode(422)->setJSON(['error' => 'Admission request rejected.']);
            }
            return redirect()
                ->to('/admissions/new')
                ->withInput()
                ->with('error', $this->safe($e));
        }
    }
    public function checklist(int $id): \CodeIgniter\HTTP\ResponseInterface|string
    {
        try {
            $d = $this->request->getPost();
            unset($d[csrf_token()]);
            service('admission')->updateChecklist($id, $d);
            if (!$this->browser()) {
                return $this->response->setJSON(['updated' => true]);
            }
            return redirect()->to('/admissions/' . $id . '/page')->with('message', 'Checklist updated.');
        } catch (Throwable $e) {
            if (!$this->browser()) {
                return $this->response->setStatusCode(422)->setJSON(['error' => 'Admission request rejected.']);
            }
            return redirect()
                ->to('/admissions/' . $id . '/page')
                ->withInput()
                ->with('error', $this->safe($e));
        }
    }
    public function status(int $id): \CodeIgniter\HTTP\ResponseInterface|string
    {
        try {
            $d = $this->request->getPost();
            service('admission')->transition($id, (string) ($d['status'] ?? ''), $d['reason'] ?? null);
            if (!$this->browser()) {
                return $this->response->setJSON(['updated' => true]);
            }
            return redirect()->to('/admissions/' . $id . '/page')->with('message', 'Status updated successfully.');
        } catch (Throwable $e) {
            if (!$this->browser()) {
                return $this->response->setStatusCode(422)->setJSON(['error' => 'Admission request rejected.']);
            }
            return redirect()->to('/admissions/' . $id . '/page')->withInput()->with('error', $this->safe($e));
        }
    }
    public function convert(int $id): \CodeIgniter\HTTP\ResponseInterface|string
    {
        try {
            $player = service('admission')->convert($id);
            if (!$this->browser()) {
                return $this->response->setJSON(['player_id' => $player]);
            }
            return redirect()->to('/players/' . $player)->with('message', 'Admission converted to player.');
        } catch (Throwable $e) {
            if (!$this->browser()) {
                return $this->response->setStatusCode(422)->setJSON(['error' => 'Admission request rejected.']);
            }
            return redirect()
                ->to('/admissions/' . $id . '/page')
                ->with('error', $this->safe($e));
        }
    }
    public function uploadDocument(int $id): \CodeIgniter\HTTP\ResponseInterface|string
    {
        $input = $this->request->getPost();

        try {
            $admission = service('admission')->details($id);
            $input['admission_id'] = $id;
            $input['branch_id'] = (int) $admission['branch_id'];
            $file = $this->request->getFile('document');

            if (($input['document_type'] ?? '') === 'player_photo') {
                $file = $this->request->getFile('profile_photo');
            }

            if (! $file instanceof UploadedFile) {
                throw new \RuntimeException('A document file is required.');
            }

            service('document')->upload($input, $file);

            return redirect()
                ->to('/admissions/' . $id . '/page')
                ->with('message', 'Document uploaded successfully.');
        } catch (Throwable $e) {
            if (($input['return_to'] ?? '') === 'admissions') {
                return redirect()
                    ->to('/admissions')
                    ->withInput()
                    ->with('error', $this->safe($e));
            }

            $target = '/admissions/' . $id . '/edit';

            if ($this->request->getGet('fragment') === '1') {
                $target .= '?fragment=1';
            }

            return redirect()
                ->to($target)
                ->withInput()
                ->with('error', $this->safe($e));
        }
    }

    public function removeDocument(int $id, int $documentId): \CodeIgniter\HTTP\ResponseInterface|string
    {
        try {
            service('document')->removeFromAdmission($documentId, $id);

            return redirect()
                ->to('/admissions/' . $id . '/page')
                ->with('message', 'Document removed successfully.');
        } catch (Throwable $e) {
            return redirect()
                ->to('/admissions/' . $id . '/page')
                ->with('error', $this->safe($e));
        }
    }

    private function admissionFieldErrors(Throwable $exception, array $input): array
    {
        $message = $exception->getMessage();

        if (in_array($message, ['Invalid status.', 'Invalid transition.', 'Reason required.'], true)) {
            return ['status' => $message];
        }

        if ($message === 'Batch and admission date are required.') {
            return [
                'batch_id' => 'Select a batch.',
                'joining_date' => 'Enter an admission date.',
            ];
        }

        if ($message === 'Trial requires a Lead.') {
            return ['trial_id' => 'Select a Lead before selecting a Trial.'];
        }

        if ($message === 'Invalid admission Coach.') {
            return [
                'coach_user_id' => 'Select an authorized Coach for this branch.',
                'personal_coach_user_id' => 'Select an authorized Coach for this branch.',
                'batch_coach_id' => 'Select an authorized Coach for this branch.',
                'personal_coach_id' => 'Select an authorized Coach for this branch.',
            ];
        }

        if ($message === 'Correction reason is required for completed Admission coach changes.') {
            return ['correction_reason' => $message];
        }

        if ($message === 'Correction reason is too long.') {
            return ['correction_reason' => $message];
        }

        if ($message === 'Completed Admission only permits coach assignment changes.') {
            return [
                'coach_user_id' => $message,
                'personal_coach_user_id' => $message,
                'batch_coach_id' => $message,
                'personal_coach_id' => $message,
            ];
        }

        if ($message === 'Invalid admission rating.') {
            foreach ([
                'ability_overall',
                'ability_batting',
                'ability_bowling',
                'ability_wicketkeeping',
                'ability_fitness',
            ] as $field) {
                if (array_key_exists($field, $input)) {
                    return [$field => 'Select a rating from 0 to 10, including decimals.'];
                }
            }
        }

        if ($message === 'Invalid admission date.') {
            foreach ([
                'start_date',
                'end_date',
                'joining_date',
                'year1_start_date',
                'year1_end_date',
                'year2_start_date',
                'year2_end_date',
                'year3_start_date',
                'year3_end_date',
            ] as $field) {
                if (array_key_exists($field, $input) && trim((string) $input[$field]) !== '') {
                    return [$field => 'Enter a valid date.'];
                }
            }
        }

        if ($message === 'Invalid admission signature.') {
            foreach ([
                'student_signature',
                'parent_signature',
                'authorized_signature',
            ] as $field) {
                if (array_key_exists($field, $input)) {
                    return [$field => 'The signature data is invalid.'];
                }
            }
        }

        if ($message === 'Invalid fee amount.') {
            foreach (['admission_fee', 'training_fee', 'hostel_charges', 'total_taxable_fee'] as $field) {
                if (array_key_exists($field, $input)) {
                    return [$field => 'Enter a valid non-negative amount.'];
                }
            }
        }

        if ($message === 'Invalid tax rate.') {
            return ['tax_rate' => 'Enter a tax rate from 0 to 100.'];
        }

        if ($message === 'Invalid payment plan.') {
            return [
                'installment_plan' => 'Select a valid payment plan.',
                'hostel_installment_plan' => 'Select a valid payment plan.',
            ];
        }

        if ($message === 'Invalid hostel payment plan.') {
            return ['hostel_installment_plan' => 'Select a valid hostel payment plan.'];
        }

        if (str_contains($message, 'signature')) {
            return ['student_signature' => $message];
        }

        if ($message === 'Invalid Lead.') {
            return ['lead_id' => 'Select a valid Lead.'];
        }

        if ($message === 'Admission end date must be on or after the start date.') {
            return ['end_date' => 'Admission end date must be on or after the start date.'];
        }

        if (str_contains($message, 'renewal start date')) {
            return [
                'start_date' => 'The renewal start date must be after the current Admission period end date.',
            ];
        }

        if (str_contains($message, 'renewal end date')) {
            return ['end_date' => $message];
        }

        if ($message === 'Please enter the current Admission period end date before renewing.') {
            return ['end_date' => $message];
        }

        if (str_contains($message, 'coaching plan option')) {
            foreach ([
                'coaching_type',
                'personal_coaching_days',
                'personal_coaching_duration',
                'estimated_personal_session',
                'group_coaching_days',
                'coaching_delivery',
                'fitness_plan',
                'gym_fitness_session',
                'match_program',
                'induction_plan',
                'review_frequency',
                'progress_report_frequency',
                'feedback_call_frequency',
                'meditation_sessions',
                'elite_camp_days',
                'elite_camp_duration',
                'estimated_elite_session',
                'student_diary',
                'welcome_kit',
            ] as $field) {
                if (array_key_exists($field, $input)) {
                    return [$field => 'Select an authorized coaching option.'];
                }
            }
        }

        if (str_contains($message, 'Estimated coaching sessions')) {
            foreach (['estimated_personal_session', 'estimated_elite_session'] as $field) {
                if (array_key_exists($field, $input)) {
                    return [$field => $message];
                }
            }
        }

        foreach ([
            'Personal coaching details are required' => 'personal_coaching_days',
            'Group coaching days are required' => 'group_coaching_days',
            'Elite Camp details are required' => 'elite_camp_days',
        ] as $fragment => $field) {
            if (str_contains($message, $fragment)) {
                return [$field => $message];
            }
        }

        if (str_contains(strtolower($message), 'instalment')) {
            $field = ($input['hostel_installment_plan'] ?? '') === 'Instalment Plan'
                ? 'hostel_installment_rows'
                : 'installment_rows';

            return [$field => $message];
        }

        if ($message !== 'Invalid admission option.') {
            return [];
        }

        foreach (service('admission')->profileOptions() as $field => $options) {
            $value = trim((string) ($input[$field] ?? ''));
            if ($value !== '' && ! in_array($value, $options, true)) {
                return [$field => 'Select a valid option.'];
            }
        }

        return [];
    }

    private function admissionError(Throwable $exception): string
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
            'Admission save failed [{reference}] {type}: {message}',
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

    private function paymentError(Throwable $exception): string
    {
        $safeMessages = [
            'Forbidden' => 'You are not authorized to record a payment for this installment.',
            'Authentication required' => 'Authentication is required to record a payment.',
            'Invalid installment.' => 'The installment record is invalid.',
            'Installment not found.' => 'The installment record was not found.',
            'Receipt number already exists.' => 'That receipt number has already been used.',
            'Enter a positive payment amount.' => 'Enter a positive payment amount.',
            'Enter a valid payment date.' => 'Enter a valid payment date.',
            'Select a valid payment method.' => 'Select a valid payment method.',
            'Receipt number is too long.' => 'Enter a shorter receipt number.',
            'Payment details are too long.' => 'Enter shorter payment details.',
        ];
        $message = $exception->getMessage();
        $safe = $safeMessages[$message] ?? 'The payment receipt could not be saved.';

        $reference = 'ERR-' . strtoupper(substr(hash(
            'sha256',
            $exception::class . '|' . $message . '|' . $exception->getFile() . '|' . $exception->getLine(),
        ), 0, 8));

        log_message('error', 'Admission installment payment failed [{reference}] {type}: {message}', [
            'reference' => $reference,
            'type' => $exception::class,
            'message' => $message,
        ]);

        if ($safe !== 'The payment receipt could not be saved.') {
            return $safe;
        }

        return $safe . ' Reference: ' . $reference;
    }

    /** @return array<string, string> */
    private function paymentFieldErrors(Throwable $exception): array
    {
        return match ($exception->getMessage()) {
            'Enter a positive payment amount.' => ['amount_received' => $exception->getMessage()],
            'Enter a valid payment date.' => ['payment_date' => $exception->getMessage()],
            'Select a valid payment method.' => ['payment_method' => $exception->getMessage()],
            'Receipt number is too long.' => ['receipt_number' => $exception->getMessage()],
            default => [],
        };
    }

    private function assertFormStatus(array $input): void
    {
        $status = trim((string) ($input['status'] ?? ''));

        if ($status !== '') {
            service('admission')->assertValidStatus($status);
        }
    }

    private function applyFormStatus(int $id, array $input, ?string $knownCurrentStatus = null): void
    {
        $status = strtoupper(trim((string) ($input['status'] ?? '')));

        if ($status === '') {
            return;
        }

        $currentStatus = $knownCurrentStatus;

        if ($currentStatus === null) {
            $admission = service('admission')->details($id);
            $currentStatus = (string) ($admission['status'] ?? '');
        }

        if ($status !== $currentStatus) {
            service('admission')->transition($id, $status);
        }
    }

    private function uploadOptional(int $admissionId, int $branchId, string $fileField, string $documentType): void
    {
        $file = $this->request->getFile($fileField);

        if (! $file instanceof UploadedFile || $file->getError() === UPLOAD_ERR_NO_FILE) {
            return;
        }

        service('document')->upload([
            'admission_id' => $admissionId,
            'branch_id' => $branchId,
            'document_type' => $documentType,
        ], $file);
    }

    private function hasUpload(string $fileField): bool
    {
        $file = $this->request->getFile($fileField);

        return $file instanceof UploadedFile && $file->getError() !== UPLOAD_ERR_NO_FILE;
    }

    protected function browser(): bool
    {
        return !str_contains(strtolower((string) $this->request->getHeaderLine('Content-Type')), 'application/json');
    }
}
