<?php

declare (strict_types=1);
namespace App\Modules\CRM\Controllers;

use App\Controllers\StaffController;
use App\Modules\CRM\Models\LeadModel;
use DateTimeImmutable;
use Throwable;
use CodeIgniter\HTTP\ResponseInterface;
final class Browser extends StaffController
{
    public function index(): string
    {
        $filters = $this->request->getGet();
        $result = service('crm')->paginatedLeads($filters, (int) ($filters['page'] ?? 1));
        return $this->page('App\Modules\CRM\Views\index', 'CRM leads', $result + ['branches' => service('crm')->availableBranches(),
    'sources' => service('crm')->activeLeadSources(), 'statuses' => service('crm')->activeLeadStatuses(),
    'canCreate' => service('authorization')->can('lead.create'),
    'canUpdate' => service('authorization')->can('lead.update'),
    'canBookTrial' => service('authorization')->can('trial.book')]);
    }
    public function followUps(): string
    {
        $filters = $this->request->getGet();
        $result = service('crm')->paginatedFollowUps($filters, (int) ($filters['page'] ?? 1));
        return $this->page('App\Modules\CRM\Views\follow_ups', 'Follow-Ups', $result + ['statusCounts' => service('crm')->followUpStatusCounts(), 'branches' => service('crm')->availableBranches(),
    'options' => service('crm')->followUpFilterOptions()]);
    }
    public function createPage(): string
    {
        return $this->page('App\Modules\CRM\Views\create', 'Create lead', ['branches' => service('crm')->availableBranches(),
    'assignableUsers' => service('crm')->assignableUsers(),
    'sources' => service('crm')->activeLeadSources(), 'statuses' => service('crm')->activeLeadStatuses()]);
    }
    public function show(int $id): ResponseInterface|string
    {
        try {
            $lead = service('crm')->leadDetails($id);
            $canUpdate = service('authorization')->can(
                'lead.update',
                (string) $lead['branch_id']
            );

            $canBookTrial = service('authorization')->can(
                'trial.book',
                (string) $lead['branch_id']
            ) && service('trial')->isLeadBookable($lead);

            return $this->page(
                'App\Modules\CRM\Views\show',
                'Lead details',
                [
                    'lead' => $lead,
                    'statuses' => service('crm')->activeLeadStatuses(),
                    'coaches' => service('trial')->availableCoaches((int) $lead['branch_id']),
                    'locations' => $canBookTrial
                        ? service('trial')->availableLocations((int) $lead['branch_id'])
                        : [],
                    'canUpdate' => $canUpdate,
                    'canBookTrial' => $canBookTrial,
                    'canViewAdmissions' => service('authorization')->can(
                        'admission.view',
                        (string) $lead['branch_id']
                    ),
                ]
            );
        } catch (Throwable $e) {
            return $this->response->setStatusCode($e->getMessage() === 'Forbidden' ? 403 : 404)->setBody(esc($this->safe($e)));
        }
    }
    public function legacyShow(int $id): ResponseInterface
    {
        try {
            service('crm')->leadDetails($id);
            return redirect()->to(site_url('crm/leads/' . $id))->setStatusCode(301);
        } catch (Throwable $e) {
            return $this->response->setStatusCode($e->getMessage() === 'Forbidden' ? 403 : 404)->setBody(esc($this->safe($e)));
        }
    }
    public function edit(int $id): ResponseInterface|string
    {
        try {
            $lead = service('crm')->leadDetails($id);
            return $this->page('App\Modules\CRM\Views\edit', 'Edit lead', ['lead' => $lead, 'branches' => service('crm')->availableBranches(),
    'assignableUsers' => service('crm')->assignableUsers(),
    'sources' => service('crm')->activeLeadSources(), 'statuses' => service('crm')->activeLeadStatuses()]);
        } catch (Throwable $e) {
            return $this->response->setStatusCode($e->getMessage() === 'Forbidden' ? 403 : 404)->setBody(esc($this->safe($e)));
        }
    }
    public function followUpPage(int $id): ResponseInterface|string
    {
        try {
            $lead = service('crm')->leadDetails($id);
            return $this->page('App\Modules\CRM\Views\follow_up', 'Add follow-up', ['lead' => $lead]);
        } catch (Throwable $e) {
            return $this->response->setStatusCode($e->getMessage() === 'Forbidden' ? 403 : 404)->setBody(esc($this->safe($e)));
        }
    }
    public function update(int $id): \CodeIgniter\HTTP\ResponseInterface|string
    {
        $input = $this->request->getPost();
        try {
            service('crm')->updateLead($id, $input);
            return redirect()->to('/crm/leads/' . $id)->with('message', 'Lead updated.');
        } catch (Throwable $e) {
            return redirect()->to('/crm/leads/' . $id . '/edit')
                ->withInput()
                ->with('error', $this->safe($e))
                ->with('field_errors', $this->leadFieldErrors($input, $e, true));
        }
    }
    public function create(): \CodeIgniter\HTTP\ResponseInterface|string
    {
        try {
            $d = $this->request->getPost();
            $result = service('crm')->create($d);
            if (!$this->browser()) {
                return $this->response->setStatusCode(201)->setJSON($result);
            }
            $message = $result['duplicate_warning'] ? 'Lead created. Possible duplicate detected; review before contacting.' : 'Lead created.';
            return redirect()->to('/crm/leads/' . $result['id'])->with('message', $message);
        } catch (Throwable $e) {
            if (!$this->browser()) {
                return $this->response->setStatusCode(422)->setJSON(['error' => 'Lead request rejected.']);
            }
            return redirect()->to('/crm/leads/new')
                ->withInput()
                ->with('error', $this->safe($e))
                ->with('field_errors', $this->leadFieldErrors($d ?? [], $e));
        }
    }

    /** @param array<string, mixed> $input @return array<string, string> */
    private function leadFieldErrors(array $input, Throwable $exception, bool $editing = false): array
    {
        if (! in_array($exception->getMessage(), ['Invalid lead.', 'Invalid owner.', 'Forbidden'], true)) {
            return [];
        }

        $errors = [];
        $name = trim((string) ($input['full_name'] ?? ''));
        if ($name === '' || strlen($name) > 150) {
            $errors['full_name'] = 'Enter a name up to 150 characters.';
        }

        $email = trim((string) ($input['email'] ?? ''));
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'Enter a valid email address.';
        }

        $phone = trim((string) ($input['phone'] ?? ''));
        if (strlen($phone) > 40) {
            $errors['phone'] = 'Enter a phone number with 40 characters or fewer.';
        }

        $dateOfBirth = trim((string) ($input['date_of_birth'] ?? ''));
        if ($dateOfBirth !== '') {
            $validDate = false;
            if (preg_match('/^\d{2}-\d{2}-\d{4}$/', $dateOfBirth)) {
                $parsedDate = DateTimeImmutable::createFromFormat('!d-m-Y', $dateOfBirth);
                $validDate = $parsedDate !== false && $parsedDate->format('d-m-Y') === $dateOfBirth;
            } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateOfBirth)) {
                $parsedDate = DateTimeImmutable::createFromFormat('!Y-m-d', $dateOfBirth);
                $validDate = $parsedDate !== false && $parsedDate->format('Y-m-d') === $dateOfBirth;
            }
            if (! $validDate) {
                $errors['date_of_birth'] = 'Enter a valid date as DD-MM-YYYY.';
            }
        }

        $branchId = (int) ($input['branch_id'] ?? 0);
        $branchIds = $editing ? [] : array_map(
            static fn (array $branch): int => (int) ($branch['id'] ?? 0),
            service('crm')->availableBranches(),
        );
        if (! $editing && (! ctype_digit((string) ($input['branch_id'] ?? '')) || $branchId < 1 || ! in_array($branchId, $branchIds, true))) {
            $errors['branch_id'] = 'Select an authorized branch.';
        }
        $source = trim((string) ($input['source'] ?? ''));
        $sources = array_map(
            static fn (array $option): string => strtolower(trim((string) ($option['name'] ?? ''))),
            service('crm')->activeLeadSources(),
        );
        if ($source === '') {
            $errors['source'] = 'Select a lead source.';
        }
        $status = trim((string) ($input['status'] ?? ''));
        $statuses = array_map(
            static fn (array $option): string => strtolower(trim((string) ($option['name'] ?? ''))),
            service('crm')->activeLeadStatuses(),
        );
        if ($status === '' || ! in_array(strtolower($status), $statuses, true)) {
            $errors['status'] = 'Select a lead status.';
        }

        if ($exception->getMessage() === 'Invalid owner.') {
            $errors['owner_user_id'] = 'Select an enabled user.';
        }
        if ($exception->getMessage() === 'Forbidden') {
            $errors['branch_id'] = 'Select a branch you are authorized to manage.';
        }

        if ($errors === []) {
            $errors['_form'] = 'Review the highlighted lead details.';
        }

        return $errors;
    }

    public function sampleImport(): ResponseInterface
    {
        $path = ROOTPATH . 'docs/samples/lead-import-sample.csv';
        $contents = is_file($path) ? file_get_contents($path) : false;

        if ($contents === false) {
            return $this->response->setStatusCode(404)->setBody('Sample file unavailable.');
        }

        return $this->response
            ->setContentType('text/csv; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="lead-import-sample.csv"')
            ->setBody($contents);
    }

    public function import(): \CodeIgniter\HTTP\ResponseInterface
    {
        try {
            $result = service('crm')->importCsv($this->request->getFile('lead_import_file'));
            return $this->response->setJSON($result);
        } catch (Throwable $exception) {
            $status = $exception->getMessage() === 'Forbidden' ? 403 : 422;
            return $this->response->setStatusCode($status)->setJSON([
                'error' => $this->importError($exception),
            ]);
        }
    }

    private function importError(Throwable $exception): string
    {
        $allowed = [
            'Forbidden', 'The uploaded CSV file is invalid.', 'The CSV file exceeds the 5 MB limit.',
            'Only CSV files are supported.', 'The uploaded CSV file could not be read.', 'The CSV file is empty.',
            'The CSV must include Name and Branch columns.', 'The CSV file contains no data rows.',
        ];
        return in_array($exception->getMessage(), $allowed, true)
            ? $exception->getMessage() : 'The import request could not be processed.';
    }
    public function assign(int $id): \CodeIgniter\HTTP\ResponseInterface|string
    {
        try {
            $d = $this->request->getPost();
            $owner = array_key_exists('owner_user_id', $d)
                ? ($d['owner_user_id'] === '' ? null : (int) $d['owner_user_id']) : 0;
            service('crm')->assign($id, (int) ($d['branch_id'] ?? 0), $owner);
            if (!$this->browser()) {
                return $this->response->setJSON(['updated' => true]);
            }
            return redirect()->to('/crm/leads/' . $id)->with('message', 'Lead assignment updated.');
        } catch (Throwable $e) {
            if (!$this->browser()) {
                return $this->response->setStatusCode(422)->setJSON(['error' => 'Lead request rejected.']);
            }
            return redirect()->to('/crm/leads/' . $id)
                ->withInput()
                ->with('error', $this->safe($e));
        }
    }
    public function status(int $id): \CodeIgniter\HTTP\ResponseInterface|string
    {
        try {
            $d = $this->request->getPost();
            service('crm')->status($id, (int) ($d['branch_id'] ?? 0), (string) ($d['status'] ?? ''), $d['reason'] ?? null);
            if (!$this->browser()) {
                return $this->response->setJSON(['updated' => true]);
            }
            return redirect()->to('/crm/leads/' . $id)->with('message', 'Lead status updated.');
        } catch (Throwable $e) {
            if (!$this->browser()) {
                return $this->response->setStatusCode(422)->setJSON(['error' => 'Lead request rejected.']);
            }
            return redirect()->to('/crm/leads/' . $id)
                ->withInput()
                ->with('error', $this->safe($e));
        }
    }
    public function followUp(int $id): \CodeIgniter\HTTP\ResponseInterface|string
    {
        $input = $this->request->getPost();
        try {
            $d = $input;
            if (!$this->browser()) {
                $json = $this->request->getJSON(true);
                if (is_array($json)) {
                    $d = $json;
                }
            }
            $newId = service('crm')->createFollowUp($id, $d);
            if (!$this->browser()) {
                return $this->response->setStatusCode(201)->setJSON(['created' => true, 'id' => $newId]);
            }
            return redirect()->to('/crm/leads/' . $id)->with('message', 'Follow-up recorded.');
        } catch (Throwable $e) {
            if (!$this->browser()) {
                return $this->response->setStatusCode($e->getMessage() === 'Forbidden' ? 403 : ($e->getMessage() === 'Not found.' ? 404 :
    422))->setJSON(['error' => 'Lead request rejected.']);
            }
            return redirect()->to('/crm/leads/' . $id)
                ->withInput()
                ->with('error', $this->safe($e))
                ->with('field_errors', $this->followUpFieldErrors($input, $e));
        }
    }

    /** @param array<string, mixed> $input @return array<string, string> */
    private function followUpFieldErrors(array $input, Throwable $exception): array
    {
        if ($exception->getMessage() !== 'Invalid follow-up.') {
            return [];
        }

        $errors = [];
        $types = ['call', 'whatsapp', 'email', 'meeting', 'other'];
        if (! in_array(strtolower(trim((string) ($input['follow_up_type'] ?? ''))), $types, true)) {
            $errors['follow_up_type'] = 'Select a follow-up type.';
        }

        $note = trim((string) ($input['note'] ?? ''));
        if ($note === '' || strlen($note) > 65535) {
            $errors['note'] = 'Enter follow-up notes.';
        }

        if (($input['follow_up_at'] ?? '') !== '') {
            $value = trim((string) $input['follow_up_at']);
            $valid = preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}:\d{2}|T\d{2}:\d{2})$/', $value) === 1;
            if (! $valid) {
                $errors['follow_up_at'] = 'Enter a valid follow-up date and time.';
            }
        }

        return $errors;
    }
    public function updateFollowUp(int $leadId, int $followUpId): \CodeIgniter\HTTP\ResponseInterface|string
    {
        try {
            service('crm')->updateFollowUp($followUpId, $this->request->getPost());
            return redirect()->to('/crm/leads/' . $leadId)->with('message', 'Follow-up updated.');
        } catch (Throwable $e) {
            return redirect()->to('/crm/leads/' . $leadId)
                ->withInput()
                ->with('error', $this->safe($e));
        }
    }
    public function completeFollowUp(int $leadId, int $followUpId): \CodeIgniter\HTTP\ResponseInterface|string
    {
        try {
            service('crm')->completeFollowUp($followUpId, (string) $this->request->getPost('outcome'));
            return redirect()->to('/crm/leads/' . $leadId)->with('message', 'Follow-up completed.');
        } catch (Throwable $e) {
            return redirect()->to('/crm/leads/' . $leadId)
                ->withInput()
                ->with('error', $this->safe($e));
        }
    }
    public function cancelFollowUp(int $leadId, int $followUpId): \CodeIgniter\HTTP\ResponseInterface|string
    {
        try {
            service('crm')->cancelFollowUp($followUpId, (string) $this->request->getPost('reason'));
            return redirect()->to('/crm/leads/' . $leadId)->with('message', 'Follow-up cancelled.');
        } catch (Throwable $e) {
            return redirect()->to('/crm/leads/' . $leadId)
                ->withInput()
                ->with('error', $this->safe($e));
        }
    }
    public function correctFollowUp(int $leadId, int $followUpId): \CodeIgniter\HTTP\ResponseInterface|string
    {
        try {
            service('crm')->correctFollowUp($followUpId, $this->request->getPost());
            return redirect()->to('/crm/leads/' . $leadId)->with('message', 'Follow-up correction recorded.');
        } catch (Throwable $e) {
            return redirect()->to('/crm/leads/' . $leadId)
                ->withInput()
                ->with('error', $this->safe($e));
        }
    }
}
