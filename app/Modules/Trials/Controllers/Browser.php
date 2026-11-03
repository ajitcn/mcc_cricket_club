<?php

declare (strict_types=1);
namespace App\Modules\Trials\Controllers;

use App\Controllers\StaffController;
use CodeIgniter\HTTP\Files\UploadedFile;
use CodeIgniter\HTTP\ResponseInterface;
use RuntimeException;
use Throwable;
final class Browser extends StaffController
{
    public function index(): string
    {
        $filters = $this->request->getGet();
        $result = service('trial')->paginated($filters, (int) ($filters['page'] ?? 1));
        return $this->page('App\Modules\Trials\Views\index', 'Trials', $result + [
            'statusCounts' => service('trial')->statusCounts(),
            'branches' => service('trial')->availableBranches(),
            'canUpdate' => service('authorization')->can('lead.update'),
            'canStartAdmissionTest' => service('authorization')->can('admission.create'),
        ]);
    }
    public function show(int $id): ResponseInterface|string
    {
        try {
            $trial = service('trial')->details($id);
            $locations = [];
            if (! empty($trial['actions']['reschedule'])) {
                $locations = service('trial')->availableLocations(
                    (int) $trial['branch_id'],
                    'lead.update',
                );
            }

            $coaches = [];
            if (! empty($trial['actions']['manage']) || ! empty($trial['actions']['secondary_assignment'])) {
                $coachPermission = service('authorization')->can('lead.view', (string) $trial['branch_id'])
                    ? 'lead.view'
                    : (service('authorization')->can('performance.update', (string) $trial['branch_id'])
                        ? 'performance.update'
                        : 'lead.update');
                $coaches = service('trial')->availableCoaches((int) $trial['branch_id'], $coachPermission);
            }

            return $this->page('App\Modules\Trials\Views\show', 'Trial details', [
                'trial' => $trial,
                'canEditCoachRatings' => (bool) ($trial['canEditCoachRatings'] ?? false),
                'coaches' => $coaches,
                'locations' => $locations,
            ]);
        } catch (Throwable $e) {
            return $this->response->setStatusCode($e->getMessage() === 'Forbidden' ? 403 : 404)->setBody(esc($this->safe($e)));
        }
    }
    public function bookPage(int $leadId): ResponseInterface|string
    {
        try {
            $data = service('trial')->bookingPage($leadId);
            $data['locations'] = service('trial')->availableLocations((int) $data['lead']['branch_id']);
            return $this->page('App\Modules\Trials\Views\book', 'Book trial', $data);
        } catch (Throwable $e) {
            return $this->response->setStatusCode($e->getMessage() === 'Forbidden' ? 403 : 404)->setBody(esc($this->safe($e)));
        }
    }
    public function book(int $leadId): ResponseInterface|string
    {
        $returnContext = (string) $this->request->getPost('return_context');
        $failureTarget = $returnContext === 'lead_detail'
            ? site_url('crm/leads/' . $leadId)
            : site_url('crm/trials/from-lead/' . $leadId);

        try {
            service('trial')->book($leadId, $this->request->getPost());
            return redirect()->to(site_url('crm/leads/' . $leadId))->with('message', 'Trial booked.');
        } catch (Throwable $e) {
            return $this->redirectWithTrialError($failureTarget, $e);
        }
    }
    public function reschedule(int $id): ResponseInterface|string
    {
        $target = site_url('crm/trials/' . $id);
        try {
            $newId = service('trial')->reschedule($id, $this->request->getPost());
            return redirect()->to(site_url('crm/trials/' . $newId))->with('message', 'Trial rescheduled.');
        } catch (Throwable $e) {
            return $this->redirectWithTrialError($target, $e);
        }
    }

    public function coaches(int $id): ResponseInterface|string
    {
        $target = site_url('crm/trials/' . $id);

        try {
            service('trial')->updateSecondaryCoach($id, $this->request->getPost());

            return redirect()->to($target)->with('message', 'Secondary Coach / Evaluator updated.');
        } catch (Throwable $exception) {
            $field = in_array($exception->getMessage(), ['Correction reason required.', 'Correction reason is too long.'], true)
                ? 'correction_reason'
                : 'secondary_coach_user_id';

            return redirect()->to($target)
                ->withInput()
                ->with('field_errors', [$field => $this->safe($exception)])
                ->with('error', $this->safe($exception));
        }
    }
    public function cancel(int $id): ResponseInterface|string
    {
        $target = site_url('crm/trials/' . $id);
        try {
            service('trial')->cancel($id, (string) $this->request->getPost('reason'));
            return redirect()->to($target)->with('message', 'Trial cancelled.');
        } catch (Throwable $e) {
            return redirect()->to($target)->withInput()->with('error', $this->safe($e));
        }
    }
    public function attendance(int $id): ResponseInterface|string
    {
        $target = site_url('crm/trials/' . $id);
        try {
            service('trial')->attendance($id, (string) $this->request->getPost('outcome'));
            return redirect()->to($target)->with('message', 'Trial attendance recorded.');
        } catch (Throwable $e) {
            return redirect()->to($target)->withInput()->with('error', $this->safe($e));
        }
    }
    public function selection(int $id): ResponseInterface|string
    {
        $target = site_url('crm/trials/' . $id);
        try {
            service('trial')->selection($id, (string) $this->request->getPost('outcome'));
            return redirect()->to($target)->with('message', 'Selection outcome recorded.');
        } catch (Throwable $e) {
            return redirect()->to($target)->withInput()->with('error', $this->safe($e));
        }
    }
    public function admissionPending(int $id): ResponseInterface|string
    {
        $target = site_url('crm/trials/' . $id);
        try {
            service('trial')->admissionPending($id);
            return redirect()->to($target)->with('message', 'Lead moved to Admission Pending.');
        } catch (Throwable $e) {
            return redirect()->to($target)->withInput()->with('error', $this->safe($e));
        }
    }

    public function ratings(int $id): ResponseInterface|string
    {
        $target = site_url('crm/trials/' . $id);

        try {
            service('trial')->updateRatings($id, $this->request->getPost());

            return redirect()->to($target)->with('message', 'Trial ratings updated.');
        } catch (Throwable $exception) {
            $fieldErrors = [];
            if ($exception->getMessage() === 'Invalid Trial rating.') {
                foreach (['ability_overall', 'ability_batting', 'ability_bowling', 'ability_wicketkeeping', 'ability_fitness'] as $field) {
                    $fieldErrors[$field] = $this->safe($exception);
                }
            }

            $response = redirect()->to($target)->withInput()->with('error', $this->safe($exception));
            return $fieldErrors === [] ? $response : $response->with('field_errors', $fieldErrors);
        }
    }

    public function ratingsForAdmission(int $id): ResponseInterface
    {
        try {
            return $this->response->setJSON([
                'ratings' => service('trial')->ratingsForAdmission($id),
            ]);
        } catch (Throwable $exception) {
            return $this->response->setStatusCode($exception->getMessage() === 'Forbidden' ? 403 : 404)
                ->setJSON(['error' => $this->safe($exception)]);
        }
    }

    public function uploadMedia(int $id): ResponseInterface|string
    {
        $target = site_url('crm/trials/' . $id);

        try {
            $file = $this->request->getFile('media');
            if (! $file instanceof UploadedFile) {
                throw new RuntimeException('Invalid Trial media upload.');
            }

            service('trialMedia')->upload($id, $file);

            return redirect()
                ->to($target)
                ->with('message', 'Trial media uploaded.');
        } catch (Throwable $exception) {
            return redirect()
                ->to($target)
                ->withInput()
                ->with('error', $this->safe($exception));
        }
    }

    public function downloadMedia(int $trialId, int $mediaId): ResponseInterface
    {
        try {
            $file = service('trialMedia')->read($trialId, $mediaId);
            $media = $file['media'];

            return $this->response
                ->setContentType((string) $media['mime_type'])
                ->setHeader('Content-Length', (string) strlen($file['contents']))
                ->setHeader('X-Content-Type-Options', 'nosniff')
                ->setHeader('Content-Disposition', 'attachment; filename="' . $this->safeMediaName($media) . '"')
                ->setBody($file['contents']);
        } catch (Throwable $exception) {
            if ($exception->getMessage() === 'Forbidden') {
                return $this->response
                    ->setStatusCode(403)
                    ->setContentType('text/plain')
                    ->setBody('Not found.');
            }

            return $this->response
                ->setStatusCode(404)
                ->setContentType('text/plain')
                ->setBody('Not found.');
        }
    }

    public function previewMedia(int $trialId, int $mediaId): ResponseInterface
    {
        try {
            $file = service('trialMedia')->read($trialId, $mediaId);
            $media = $file['media'];

            return $this->response
                ->setContentType((string) $media['mime_type'])
                ->setHeader('Content-Length', (string) strlen($file['contents']))
                ->setHeader('Content-Disposition', 'inline; filename="' . $this->safeMediaName($media) . '"')
                ->setHeader('Cache-Control', 'private, no-store')
                ->setHeader('X-Content-Type-Options', 'nosniff')
                ->setBody($file['contents']);
        } catch (Throwable $exception) {
            if ($exception->getMessage() === 'Forbidden') {
                return $this->response
                    ->setStatusCode(403)
                    ->setContentType('text/plain')
                    ->setBody('Not found.');
            }

            return $this->response
                ->setStatusCode(404)
                ->setContentType('text/plain')
                ->setBody('Not found.');
        }
    }

    /** @param array<string, mixed> $media */
    private function safeMediaName(array $media): string
    {
        $name = basename(str_replace('\\', '/', (string) ($media['original_name'] ?? 'trial-media')));
        $name = preg_replace('/[^A-Za-z0-9._-]/', '_', $name) ?? 'trial-media';

        return trim($name, '._') !== '' ? substr($name, 0, 255) : 'trial-media';
    }

    private function redirectWithTrialError(string $target, Throwable $exception): ResponseInterface
    {
        $message = $this->safe($exception);
        $field = match ($exception->getMessage()) {
            'Invalid trial date.' => 'trial_date',
            'Invalid trial time.' => 'trial_time',
            'Invalid trial location.' => 'location',
            'Invalid coach.' => 'coach_user_id',
            'Invalid secondary coach.' => 'secondary_coach_user_id',
            'Primary and secondary coaches must be different.' => 'secondary_coach_user_id',
            'Invalid trial remarks.' => 'remarks',
            default => null,
        };

        $redirect = redirect()->to($target)->withInput()->with('error', $message);

        if ($field !== null) {
            $redirect = $redirect->with('field_errors', [$field => $message]);
        }

        return $redirect;
    }
}
