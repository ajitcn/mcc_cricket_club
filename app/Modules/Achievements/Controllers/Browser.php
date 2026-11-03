<?php

declare(strict_types=1);

namespace App\Modules\Achievements\Controllers;

use App\Controllers\StaffController;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

final class Browser extends StaffController
{
    public function index(): string
    {
        return $this->page('App\\Modules\\Achievements\\Views\\index', 'Achievements', [
            'achievements' => service('achievement')->list(),
        ]);
    }

    public function createPage(): string
    {
        return $this->page('App\\Modules\\Achievements\\Views\\form', 'Create Achievement', [
            'achievement' => [],
            'branches' => service('achievement')->availableBranches(),
        ]);
    }

    public function create(): ResponseInterface
    {
        try {
            service('achievement')->create($this->request->getPost());

            return redirect()->to('/achievements')->with('message', 'Achievement created.');
        } catch (Throwable $exception) {
            return redirect()->to('/achievements/new')
                ->withInput()
                ->with('error', $this->safe($exception));
        }
    }

    public function awards(): string
    {
        $includeArchived = $this->request->getGet('history') === '1';
        $filters = [
            'search' => trim((string) $this->request->getGet('search')),
            'category' => trim((string) $this->request->getGet('category')),
            'recipient_type' => trim((string) $this->request->getGet('recipient_type')),
            'status' => trim((string) $this->request->getGet('status')),
            'branch_id' => trim((string) $this->request->getGet('branch_id')),
        ];
        $allAwards = service('achievement')->awardList($includeArchived, $filters);
        $perPage = 10;
        $currentPage = max(1, (int) $this->request->getGet('page'));
        $total = count($allAwards);
        $pageCount = max(1, (int) ceil($total / $perPage));
        $currentPage = min($currentPage, $pageCount);
        $query = array_filter(
            array_merge($filters, ['history' => $includeArchived ? '1' : null]),
            static fn (mixed $value): bool => $value !== '' && $value !== null,
        );

        return $this->page('App\\Modules\\Achievements\\Views\\awards', 'Achievement Awards', [
            'awards' => array_slice($allAwards, ($currentPage - 1) * $perPage, $perPage),
            'includeArchived' => $includeArchived,
            'filters' => $filters,
            'branches' => service('achievement')->availableBranches('achievement.view'),
            'categories' => service('achievement')->awardCategories($includeArchived),
            'pagination' => [
                'current' => $currentPage,
                'pages' => $pageCount,
                'total' => $total,
                'perPage' => $perPage,
                'query' => http_build_query($query),
            ],
        ]);
    }

    public function awardCreatePage(): string
    {
        $achievement = service('achievement');
        $activeBranchId = service('branchContext')->activeBranchId();

        return $this->page('App\\Modules\\Achievements\\Views\\award_form', 'Create Achievement Award', [
            'award' => [],
            'achievements' => $achievement->activeAchievements(),
            'branches' => $achievement->availableBranches('achievement.create'),
            'recipients' => $achievement->recipientOptions('achievement.create'),
            'activeBranchId' => $activeBranchId,
        ]);
    }

    public function createAward(): ResponseInterface
    {
        try {
            $id = service('achievement')->createAward($this->request->getPost());
            return redirect()
                ->to('/achievements/awards/' . $id)
                ->with('message', 'Achievement draft created.');
        } catch (Throwable $exception) {
            $message = $this->awardError($exception);
            if ($message === null) {
                throw $exception;
            }

            return redirect()
                ->to('/achievements/awards/new')
                ->withInput()
                ->with('error', $message)
                ->with('field_errors', $this->awardFieldErrors($exception));
        }
    }

    private function awardError(Throwable $exception): ?string
    {
        return match ($exception->getMessage()) {
            'Enter valid Achievement and recipient details.' => 'Select an Achievement, branch, and recipient.',
            'Select a valid recipient type.' => 'Select whether the recipient is a Player or Coach.',
            'Enter a shorter nomination note.' => 'Enter a nomination note with 2,000 characters or fewer.',
            'Achievement is not active for this branch.' => 'Select an active Achievement from the selected branch.',
            'Recipient is not active in this branch.' => 'Select one active Player or Coach from the selected branch.',
            'Forbidden' => 'You are not authorized for the selected branch.',
            default => null,
        };
    }

    /** @return array<string, string> */
    private function awardFieldErrors(Throwable $exception): array
    {
        return match ($exception->getMessage()) {
            'Enter valid Achievement and recipient details.' => [
                'achievement_id' => 'Select an Achievement.',
                'branch_id' => 'Select a branch.',
                'recipient_id' => 'Select one recipient.',
            ],
            'Select a valid recipient type.' => [
                'recipient_type' => 'Select Player or Coach.',
            ],
            'Enter a shorter nomination note.' => [
                'nomination_note' => 'Use 2,000 characters or fewer.',
            ],
            'Achievement is not active for this branch.' => [
                'achievement_id' => 'Select an active Achievement from the selected branch.',
            ],
            'Recipient is not active in this branch.' => [
                'recipient_id' => 'Select one active Player or Coach from the selected branch.',
            ],
            'Forbidden' => [
                'branch_id' => 'You are not authorized for the selected branch.',
            ],
            default => [],
        };
    }

    public function awardShow(int $id): ResponseInterface|string
    {
        try {
            return $this->page('App\\Modules\\Achievements\\Views\\award_show', 'Achievement draft', [
                'award' => service('achievement')->award($id),
            ]);
        } catch (Throwable $exception) {
            return $this->response->setStatusCode(404)->setBody(esc($this->safe($exception)));
        }
    }

    public function awardEditPage(int $id): ResponseInterface|string
    {
        try {
            $award = service('achievement')->award($id);
            return $this->page('App\\Modules\\Achievements\\Views\\award_form', 'Edit Achievement draft', [
                'award' => $award,
                'achievements' => service('achievement')->activeAchievements(),
                'branches' => service('achievement')->availableBranches('achievement.update'),
                'recipients' => service('achievement')->recipientOptions('achievement.update'),
                'isEdit' => true,
            ]);
        } catch (Throwable $exception) {
            return $this->response->setStatusCode(404)->setBody(esc($this->safe($exception)));
        }
    }

    public function updateAward(int $id): ResponseInterface
    {
        try {
            service('achievement')->updateAward($id, $this->request->getPost());
            return redirect()
                ->to('/achievements/awards/' . $id)
                ->with('message', 'Achievement draft updated.');
        } catch (Throwable $exception) {
            return redirect()
                ->to('/achievements/awards/' . $id . '/edit')
                ->withInput()
                ->with('error', $this->safe($exception));
        }
    }

    public function approveAward(int $id): ResponseInterface
    {
        return $this->lifecycleResponse($id, 'approveAward', 'Achievement draft approved.');
    }

    public function publishAward(int $id): ResponseInterface
    {
        return $this->lifecycleResponse($id, 'publishAward', 'Achievement published.');
    }

    public function revokeAward(int $id): ResponseInterface
    {
        return $this->lifecycleResponse($id, 'revokeAward', 'Achievement revoked.');
    }

    public function archiveAward(int $id): ResponseInterface
    {
        return $this->archiveResponse($id, 'archiveAward', 'Achievement archived.');
    }

    public function restoreAward(int $id): ResponseInterface
    {
        return $this->archiveResponse($id, 'restoreAward', 'Achievement restored.');
    }

    public function correctPublishedAward(int $id): ResponseInterface
    {
        try {
            service('achievement')->correctPublishedAward($id, $this->request->getPost());

            return redirect()
                ->to('/achievements/awards/' . $id)
                ->with('message', 'Published Achievement corrected.');
        } catch (Throwable $exception) {
            return redirect()
                ->to('/achievements/awards/' . $id)
                ->withInput()
                ->with('error', $this->safe($exception));
        }
    }

    private function lifecycleResponse(int $id, string $method, string $message): ResponseInterface
    {
        try {
            service('achievement')->{$method}($id, $this->request->getPost());

            return redirect()
                ->to('/achievements/awards/' . $id)
                ->with('message', $message);
        } catch (Throwable $exception) {
            return redirect()
                ->to('/achievements/awards/' . $id)
                ->withInput()
                ->with('error', $this->safe($exception));
        }
    }

    private function archiveResponse(int $id, string $method, string $message): ResponseInterface
    {
        try {
            service('achievement')->{$method}($id, $this->request->getPost());

            return redirect()
                ->to('/achievements/awards/' . $id)
                ->with('message', $message);
        } catch (Throwable $exception) {
            return redirect()
                ->to('/achievements/awards/' . $id)
                ->withInput()
                ->with('error', $this->safe($exception));
        }
    }

    public function generateCertificate(int $id): ResponseInterface
    {
        try {
            $certificateId = service('achievementCertificate')->generate($id);

            return redirect()
                ->to('/achievements/awards/' . $id)
                ->with('message', 'Certificate generated.')
                ->with('certificate_id', $certificateId);
        } catch (Throwable $exception) {
            return redirect()
                ->to('/achievements/awards/' . $id)
                ->with('error', $this->safe($exception));
        }
    }

    public function downloadCertificate(int $id): ResponseInterface
    {
        try {
            $certificate = service('achievementCertificate')->read($id);

            return $this->response
                ->setContentType($certificate['mime_type'])
                ->setHeader('Content-Disposition', 'attachment; filename="' . $certificate['name'] . '"')
                ->setHeader('Cache-Control', 'private, no-store')
                ->setBody($certificate['contents']);
        } catch (Throwable $exception) {
            return $this->response
                ->setStatusCode(404)
                ->setBody('Not found.');
        }
    }

    public function evaluateEligibility(): ResponseInterface
    {
        try {
            $result = service('achievementEligibility')->evaluate($this->request->getPost());

            return redirect()
                ->to('/achievements/awards')
                ->with('message', 'Eligibility evaluation ' . $result['status'] . '.');
        } catch (Throwable $exception) {
            return redirect()
                ->to('/achievements/awards')
                ->withInput()
                ->with('error', $this->safe($exception));
        }
    }

    public function retryEligibility(int $id): ResponseInterface
    {
        try {
            $result = service('achievementEligibility')->retry($id);

            return redirect()
                ->to('/achievements/awards')
                ->with('message', 'Eligibility retry ' . $result['status'] . '.');
        } catch (Throwable $exception) {
            return redirect()
                ->to('/achievements/awards')
                ->with('error', $this->safe($exception));
        }
    }

    public function reports(): ResponseInterface|string
    {
        try {
            $service = service('achievementReport');
            $filters = $this->request->getGet();
            $result = $service->run($filters);
            $result['options'] = $service->options();

            return $this->page(
                'App\\Modules\\Achievements\\Views\\reports',
                'Achievement reports',
                ['result' => $result]
            );
        } catch (Throwable $exception) {
            if (! in_array($exception->getMessage(), [
                'Forbidden',
                'Invalid Achievement report date filter.',
                'Invalid Achievement report filter.',
            ], true)) {
                throw $exception;
            }

            return $this->response
                ->setStatusCode($exception->getMessage() === 'Forbidden' ? 403 : 400)
                ->setBody(esc($exception->getMessage()));
        }
    }

    public function reportsCsv(): ResponseInterface
    {
        try {
            $csv = service('achievementReport')->export($this->request->getGet());

            return $this->response
                ->setHeader('Content-Type', 'text/csv; charset=UTF-8')
                ->setHeader('Content-Disposition', 'attachment; filename="achievement-awards-report.csv"')
                ->setBody($csv);
        } catch (Throwable $exception) {
            return $this->response
                ->setStatusCode($exception->getMessage() === 'Forbidden' ? 403 : 400)
                ->setBody(esc($exception->getMessage()));
        }
    }
}
