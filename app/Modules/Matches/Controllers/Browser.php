<?php

declare(strict_types=1);

namespace App\Modules\Matches\Controllers;

use App\Controllers\StaffController;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

final class Browser extends StaffController
{
    public function index(): string
    {
        return $this->page('App\\Modules\\Matches\\Views\\index', 'Matches', [
            'result' => service('match')->list($this->request->getGet()),
            'tournaments' => service('match')->tournamentFilterOptions(),
        ]);
    }

    public function createPage(): string
    {
        return $this->page('App\\Modules\\Matches\\Views\\form', 'Create match', [
            'match' => null,
            'options' => service('match')->formOptions('match.create'),
        ]);
    }

    public function editPage(int $id): ResponseInterface|string
    {
        try {
            return $this->page('App\\Modules\\Matches\\Views\\form', 'Edit match', [
                'match' => service('match')->find($id),
                'options' => service('match')->formOptions('match.update'),
            ]);
        } catch (Throwable $exception) {
            return $this->response
                ->setStatusCode($exception->getMessage() === 'Forbidden' ? 403 : 404)
                ->setBody(esc($this->safe($exception)));
        }
    }

    public function create(): ResponseInterface|string
    {
        return $this->save(null, 'Match created.');
    }

    public function showPage(int $id): ResponseInterface|string
    {
        try {
            return $this->page('App\\Modules\\Matches\\Views\\show', 'Match detail', ['match' => service('match')->find($id)]);
        } catch (Throwable $exception) {
            return $this->response->setStatusCode($exception->getMessage() === 'Forbidden' ? 403 : 404)->setBody(esc($this->safe($exception)));
        }
    }

    public function update(int $id): ResponseInterface|string
    {
        return $this->save($id, 'Match updated.');
    }

    public function complete(int $id): ResponseInterface|string
    {
        try {
            service('match')->complete($id);

            return redirect()->to('/matches/' . $id)->with('message', 'Match completed successfully.');
        } catch (Throwable $exception) {
            return redirect()->to('/matches/' . $id)
                ->with('error', $this->matchWorkflowError($exception));
        }
    }

    public function performancePage(int $id): ResponseInterface|string
    {
        try {
            return $this->page(
                'App\\Modules\\Matches\\Views\\performance',
                'Match Player Performance',
                ['result' => service('matchPlayerPerformance')->forMatch($id)]
            );
        } catch (Throwable $exception) {
            return $this->response
                ->setStatusCode($exception->getMessage() === 'Forbidden' ? 403 : 404)
                ->setBody(esc($this->safe($exception)));
        }
    }

    public function savePerformance(int $id): ResponseInterface|string
    {
        try {
            $data = $this->request->getPost();
            unset($data[csrf_token()]);
            service('matchPlayerPerformance')->save($id, $data);

            return redirect()->to('/matches/' . $id . '/performance')
                ->with('message', 'Match Player Performance saved successfully.');
        } catch (Throwable $exception) {
            return redirect()->to('/matches/' . $id . '/performance')
                ->withInput()
                ->with('error', $this->performanceError($exception));
        }
    }

    public function performanceTemplate(int $id): ResponseInterface|string
    {
        try {
            $rows = service('matchPlayerPerformance')->templateRows($id);
            $headers = $rows === [] ? ['player_id', 'player_name'] : array_keys($rows[0]);
            $handle = fopen('php://temp', 'w+');
            fputcsv($handle, $headers);
            foreach ($rows as $row) {
                fputcsv($handle, array_map(static fn ($header) => $row[$header] ?? '', $headers));
            }
            rewind($handle);
            $csv = stream_get_contents($handle);
            fclose($handle);

            return $this->response
                ->setHeader('Content-Type', 'text/csv; charset=UTF-8')
                ->setHeader('Content-Disposition', 'attachment; filename="match-' . $id . '-performance-template.csv"')
                ->setBody((string) $csv);
        } catch (Throwable $exception) {
            return $this->response->setStatusCode(404)->setBody(esc($this->safe($exception)));
        }
    }

    public function importPerformance(int $id): ResponseInterface|string
    {
        try {
            $file = $this->request->getFile('performance_csv');
            if ($file === null || ! $file->isValid()) {
                throw new RuntimeException('Choose a valid CSV file.');
            }
            $handle = fopen($file->getTempName(), 'rb');
            $headers = fgetcsv($handle);
            if (! is_array($headers)) {
                fclose($handle);
                throw new RuntimeException('The CSV file is empty.');
            }
            $rows = [];
            while (($values = fgetcsv($handle)) !== false) {
                if (count(array_filter($values, static fn ($value) => trim((string) $value) !== '')) === 0) {
                    continue;
                }
                $combined = array_combine($headers, array_pad(array_slice($values, 0, count($headers)), count($headers), ''));
                if (! is_array($combined)) {
                    fclose($handle);
                    throw new \RuntimeException('The CSV columns do not match the downloaded template.');
                }
                $rows[] = $combined;
            }
            fclose($handle);
            service('matchPlayerPerformance')->importRows($id, $rows);

            return redirect()->to('/matches/' . $id . '/performance')->with('message', 'Performance CSV imported successfully.');
        } catch (Throwable $exception) {
            return redirect()->to('/matches/' . $id . '/performance')
                ->with('error', $this->performanceError($exception));
        }
    }

    public function savePlayerPerformance(int $id, int $playerId): ResponseInterface|string
    {
        try {
            $data = $this->request->getPost();
            unset($data[csrf_token()]);
            $payload = is_array($data['players'][$playerId] ?? null) ? $data['players'][$playerId] : [];
            service('matchPlayerPerformance')->savePlayer($id, $playerId, $payload);

            return redirect()->to('/matches/' . $id . '/performance')
                ->with('message', 'Player performance saved successfully.')
                ->with('performance_feedback', [
                    'player_id' => $playerId,
                    'type' => 'success',
                    'message' => 'Player performance saved successfully.',
                ]);
        } catch (Throwable $exception) {
            $message = $this->performanceError($exception);
            $fieldErrors = $exception->getMessage() === 'A correction reason is required.'
                ? ['correction_reason' => 'Explain what was corrected and why.']
                : [];

            return redirect()->to('/matches/' . $id . '/performance')
                ->withInput()
                ->with('error', $message)
                ->with('field_errors', $fieldErrors)
                ->with('performance_feedback', [
                    'player_id' => $playerId,
                    'type' => 'error',
                    'message' => $message,
                ]);
        }
    }

    private function performanceError(Throwable $exception): string
    {
        return match ($exception->getMessage()) {
            'Forbidden' => 'You are not authorized to manage this Match performance.',
            'Not found.' => 'The requested Match was not found.',
            'Match performance cannot be recorded for a cancelled Match.' => 'Cancelled Matches cannot have performance entries.',
            'A correction reason is required.' => 'Enter a correction reason for this update.',
            'A performance version is required for corrections.' => 'Reload the latest performance data before correcting this record.',
            'Stale Match Player Performance submission.' => 'This performance record changed. Reload the latest data before retrying.',
            'Select valid batting and bowling statuses.' => 'Select valid batting and bowling statuses.',
            'Enter legal balls as a whole number greater than or equal to zero.' => 'Enter legal balls as a whole number greater than or equal to zero.',
            'Match performance values must be non-negative integers.' => 'Performance values must be non-negative integers.',
            'Boundary runs cannot exceed batting runs.' => 'Boundary runs cannot exceed batting runs.',
            'Maidens cannot exceed legal balls.' => 'Maidens cannot exceed legal balls.',
            'Wickets cannot exceed legal balls.' => 'Wickets cannot exceed legal balls.',
            'Did not bat Players cannot have batting totals.' => 'Did not bat Players cannot have batting totals.',
            'Did not bowl Players cannot have bowling totals.' => 'Did not bowl Players cannot have bowling totals.',
            'Invalid Match performance Player.' => 'Select only Players in this Match.',
            'Every selected Match Player requires a performance entry.' => 'Enter batting and bowling status for every selected Player.',
            'Select a valid fielding status.' => 'Select a valid fielding status.',
            'Did not field Players cannot have fielding totals.' => 'Did not field Players cannot have fielding totals.',
            'Dismissal references must belong to the Match Players.' => 'Dismissal references must belong to Match Players.',
            'Duplicate batting positions are not allowed.' => 'Each batting position may be used only once per innings.',
            default => $this->unexpectedMatchError($exception),
        };
    }

    private function matchWorkflowError(Throwable $exception): string
    {
        return match ($exception->getMessage()) {
            'Forbidden' => 'You are not authorized to complete this Match.',
            'Not found.' => 'The requested Match was not found.',
            'Match cannot be completed from current status.' => 'This Match cannot be completed from its current status.',
            default => $this->unexpectedMatchError($exception),
        };
    }

    private function save(?int $id, string $message): ResponseInterface|string
    {
        try {
            $data = $this->request->getPost();
            unset($data[csrf_token()]);
            $saved = service('match')->save($data, $id);
            if ($id === null) {
                return redirect()
                    ->to('/matches?created_match=' . $saved)
                    ->with('message', 'Match #' . $saved . ' created successfully.');
            }

            return redirect()->to('/matches/' . $saved)->with('message', $message);
        } catch (Throwable $exception) {
            $target = $id === null ? '/matches/new' : '/matches/' . $id . '/edit';

            return redirect()->to($target)
                ->withInput()
                ->with('error', $this->matchError($exception))
                ->with('field_errors', $this->matchFieldErrors($exception->getMessage()));
        }
    }

    private function matchError(Throwable $exception): string
    {
        return match ($exception->getMessage()) {
            'Invalid opponent.' => 'Select an active opponent for this branch.',
            'Invalid match type.' => 'Select a valid Match type.',
            'Invalid match category.' => 'Select a valid Match category.',
            'Match category migration is pending.' => 'Match category storage is not ready. Apply the latest development migration and try again.',
            'Invalid match status.' => 'Select a valid Match status.',
            'Invalid branch.' => 'Select an authorized Branch.',
            'Invalid match window.' => 'The Match end must be after its start.',
            'Invalid match start date/time.' => 'Enter a valid Match start date and time.',
            'Invalid match end date/time.' => 'Enter a valid Match end date and time.',
            'Ground is not available.', 'Ground is unavailable.', 'Ground scheduling conflict.', 'Ground match conflict.' => 'Select a valid available ground.',
            'Invalid coach.' => 'Select an authorized Coach.',
            'Coach scheduling conflict.' => 'The selected Coach has a scheduling conflict.',
            'Player is not eligible for match.' => 'Select eligible Players from the selected branch.',
            'Playing XI must be selected from squad.' => 'Playing XI players must also be in the squad.',
            'Playing XI cannot exceed 11 players.' => 'Playing XI cannot contain more than 11 Players.',
            'Invalid match squad.' => 'Select a valid squad without duplicate Players.',
            'Invalid player.' => 'Select valid eligible Players from the selected branch.',
            'Invalid match result.' => 'Select a valid Match result.',
            'Completed Match corrections require a reason.' => 'Enter a correction reason for this completed Match.',
            'Completed Matches cannot be reopened through ordinary editing.' => 'Completed Matches cannot be reopened through ordinary editing.',
            'The Match correction is stale. Reload before correcting it.' => 'This Match was changed by another request. Reload before correcting it.',
            'The Match was changed by another request. Reload before correcting it.' => 'This Match was changed by another request. Reload before correcting it.',
            'Forbidden' => 'You are not authorized to manage this Match branch.',
            default => $this->unexpectedMatchError($exception),
        };
    }

    private function unexpectedMatchError(Throwable $exception): string
    {
        $reference = 'MAT-' . strtoupper(bin2hex(random_bytes(4)));
        log_message(
            'error',
            'Match save failed [{reference}] {exception}: {message}',
            [
                'reference' => $reference,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]
        );

        return 'The Match could not be saved. Reference ' . $reference . ' can be provided to support.';
    }

    private function matchFieldErrors(string $message): array
    {
        return match ($message) {
            'Invalid opponent.' => ['opponent_id' => 'Select an active opponent for this branch.'],
            'Your team is required.' => ['your_team_opponent_id' => 'Select Your team.'],
            'Invalid your team.' => ['your_team_opponent_id' => 'Select an active Your team from this branch.'],
            'Your team migration is pending.' => ['your_team_opponent_id' => 'Apply the latest development migration before selecting Your team.'],
            'Invalid match type.' => ['match_type' => 'Select a valid Match type.'],
            'Invalid match category.' => ['match_category' => 'Select a valid Match category.'],
            'Match category migration is pending.' => ['match_category' => 'Apply the latest development migration before selecting Tournament.'],
            'Invalid match status.' => ['status' => 'Select a valid Match status.'],
            'Invalid branch.' => ['branch_id' => 'Select an authorized Branch.'],
            'Invalid match window.' => ['ends_at' => 'The Match end must be after its start.'],
            'Invalid match start date/time.' => ['starts_at' => 'Enter a valid Match start date and time.'],
            'Invalid match end date/time.' => ['ends_at' => 'Enter a valid Match end date and time.'],
            'Ground is not available.', 'Ground is unavailable.', 'Ground scheduling conflict.', 'Ground match conflict.' => [
                'ground_id' => 'Select an available ground.',
            ],
            'Invalid coach.', 'Coach scheduling conflict.' => [
                'coach_user_id' => 'Select an authorized and available Coach.',
            ],
            'Player is not eligible for match.', 'Invalid player.', 'Invalid match squad.' => [
                'player_ids' => 'Select valid eligible Players.',
            ],
            'Playing XI must be selected from squad.', 'Playing XI cannot exceed 11 players.' => [
                'playing_xi_ids' => 'Select a valid Playing XI.',
            ],
            'Invalid match result.' => [
                'result' => 'Select a valid Match result.',
            ],
            'Completed Match corrections require a reason.' => [
                'correction_reason' => 'Enter a correction reason for this completed Match.',
            ],
            'The Match correction is stale. Reload before correcting it.', 'The Match was changed by another request. Reload before correcting it.' => [
                'expected_version' => 'Reload this Match before correcting it.',
            ],
            default => [],
        };
    }
}
