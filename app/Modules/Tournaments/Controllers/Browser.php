<?php

declare(strict_types=1);

namespace App\Modules\Tournaments\Controllers;

use App\Controllers\StaffController;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

final class Browser extends StaffController
{
    public function index(): string
    {
        $filters = $this->request->getGet();
        try {
            $result = service('tournament')->list($filters);
            $error = null;
        } catch (Throwable $exception) {
            $result = [
                'tournaments' => [],
                'filters' => $filters,
                'page' => 1,
                'pages' => 1,
                'total' => 0,
            ];
            $error = $this->tournamentError($exception);
        }

        return $this->page('App\\Modules\\Tournaments\\Views\\index', 'Tournaments', [
            'result' => $result,
            'can_create' => service('tournament')->hasAuthorizedBranch('tournament.create'),
            'error' => $error,
        ]);
    }

    public function fixtureList(): string
    {
        $filters = $this->request->getGet();
        try {
            $result = service('tournament')->listFixtures($filters);
            $error = null;
        } catch (Throwable $exception) {
            $result = [
                'fixtures' => [],
                'filters' => $filters,
                'page' => 1,
                'pages' => 1,
                'total' => 0,
            ];
            $error = $this->tournamentError($exception);
        }

        return $this->page('App\\Modules\\Tournaments\\Views\\fixture_list', 'Fixtures', [
            'result' => $result,
            'error' => $error,
        ]);
    }

    public function createPage(): string
    {
        return $this->page('App\\Modules\\Tournaments\\Views\\form', 'Create tournament', [
            'tournament' => null,
            'options' => service('tournament')->formOptions('tournament.create') + [
                'selectedPlayerIds' => [],
            ],
        ]);
    }

    public function editPage(int $id): ResponseInterface|string
    {
        try {
            $tournament = service('tournament')->find($id);
            $selectedPlayerIds = array_values(array_map(
                'intval',
                array_column(
                    array_filter(
                        $tournament['participants'] ?? [],
                        static fn (array $participant): bool => ($participant['participant_type'] ?? '') === 'player'
                    ),
                    'player_id'
                )
            ));

            return $this->page('App\\Modules\\Tournaments\\Views\\form', 'Edit tournament', [
                'tournament' => $tournament,
                'options' => service('tournament')->formOptions(
                    'tournament.update',
                    (int) $tournament['branch_id']
                ) + [
                    'selectedPlayerIds' => $selectedPlayerIds,
                ],
            ]);
        } catch (Throwable $exception) {
            return $this->error($exception);
        }
    }

    public function showPage(int $id): ResponseInterface|string
    {
        try {
            $tournament = service('tournament')->find($id);
            return $this->page('App\\Modules\\Tournaments\\Views\\show', 'Tournament detail', [
                'tournament' => $tournament,
                'can_update' => service('authorization')->can('tournament.update', (string) $tournament['branch_id']),
            ]);
        } catch (Throwable $exception) {
            return $this->error($exception);
        }
    }

    public function create(): ResponseInterface|string
    {
        return $this->save(null, 'Tournament created.');
    }

    public function update(int $id): ResponseInterface|string
    {
        return $this->save($id, 'Tournament updated.');
    }

    public function fixture(int $id): ResponseInterface|string
    {
        try {
            $data = $this->request->getPost();
            unset($data[csrf_token()]);
            service('tournament')->fixture($id, $data);
            return redirect()->to('/tournaments/' . $id)->with('message', 'Fixture created.');
        } catch (Throwable $exception) {
            return $this->fixtureFailure(
                $exception,
                '/tournaments/' . $id . '/fixtures/new',
                true
            );
        }
    }

    public function fixtures(int $id): ResponseInterface|string
    {
        try {
            $tournament = service('tournament')->find($id);
            return $this->page('App\\Modules\\Tournaments\\Views\\fixtures', 'Tournament fixtures', [
                'tournament' => $tournament,
                'can_update' => service('authorization')->can('tournament.update', (string) $tournament['branch_id']),
            ]);
        } catch (Throwable $exception) {
            return $this->error($exception);
        }
    }

    public function fixtureCreatePage(int $id): ResponseInterface|string
    {
        try {
            $options = service('tournament')->fixtureOptions($id, 'tournament.update');
            return $this->page('App\\Modules\\Tournaments\\Views\\fixture_form', 'Create fixture', [
                'tournament' => $options['tournament'],
                'fixture' => null,
                'options' => $options,
            ]);
        } catch (Throwable $exception) {
            return $this->error($exception);
        }
    }

    public function fixtureMatchOptions(int $id): ResponseInterface
    {
        try {
            $team1 = trim((string) $this->request->getGet('team1'));
            $team2 = trim((string) $this->request->getGet('team2'));
            return $this->response->setJSON([
                'matches' => service('tournament')->fixtureMatchOptions($id, $team1, $team2),
            ]);
        } catch (Throwable $exception) {
            return $this->response->setStatusCode(404)->setJSON(['matches' => []]);
        }
    }

    public function fixtureShowPage(int $tournamentId, int $fixtureId): ResponseInterface|string
    {
        try {
            $tournament = service('tournament')->find($tournamentId);
            $fixture = $this->fixtureFromTournament($tournament, $fixtureId);
            if ($fixture === null) {
                throw new \RuntimeException('Fixture not found.');
            }
            $canUpdate = service('authorization')->can('tournament.update', (string) $tournament['branch_id']);
            $history = array_values(array_filter(
                $tournament['audit_history'] ?? [],
                static fn (array $event): bool => (string) ($event['entity_type'] ?? '') === 'tournament_fixture'
                    && (int) ($event['entity_id'] ?? 0) === $fixtureId
            ));
            return $this->page('App\\Modules\\Tournaments\\Views\\fixture_show', 'Fixture detail', [
                'tournament' => $tournament,
                'fixture' => $fixture,
                'can_update' => $canUpdate,
                'history' => $history,
                'options' => $canUpdate ? service('tournament')->fixtureOptions($tournamentId, 'tournament.update') : ['matches' => []],
            ]);
        } catch (Throwable $exception) {
            return $this->error($exception);
        }
    }

    public function fixtureEditPage(int $tournamentId, int $fixtureId): ResponseInterface|string
    {
        try {
            $tournament = service('tournament')->find($tournamentId);
            $fixture = $this->fixtureFromTournament($tournament, $fixtureId);
            if ($fixture === null) {
                throw new \RuntimeException('Fixture not found.');
            }
            return $this->page('App\\Modules\\Tournaments\\Views\\fixture_form', 'Edit fixture', [
                'tournament' => $tournament,
                'fixture' => $fixture,
                'options' => service('tournament')->fixtureOptions($tournamentId, 'tournament.update'),
            ]);
        } catch (Throwable $exception) {
            return $this->error($exception);
        }
    }

    public function fixtureUpdate(int $tournamentId, int $fixtureId): ResponseInterface|string
    {
        try {
            $data = $this->request->getPost();
            unset($data[csrf_token()]);
            service('tournament')->fixture($tournamentId, $data, $fixtureId);
            return redirect()->to('/tournaments/' . $tournamentId . '/fixtures/' . $fixtureId)
                ->with('message', 'Fixture updated.');
        } catch (Throwable $exception) {
            return $this->fixtureFailure(
                $exception,
                '/tournaments/' . $tournamentId . '/fixtures/' . $fixtureId . '/edit',
                true
            );
        }
    }

    public function fixturePostpone(int $tournamentId, int $fixtureId): ResponseInterface|string
    {
        return $this->fixtureStatus($tournamentId, $fixtureId, 'postpone');
    }

    public function fixtureCancel(int $tournamentId, int $fixtureId): ResponseInterface|string
    {
        return $this->fixtureStatus($tournamentId, $fixtureId, 'cancel');
    }

    public function fixtureComplete(int $tournamentId, int $fixtureId): ResponseInterface|string
    {
        try {
            $data = $this->request->getPost();
            unset($data[csrf_token()]);
            service('tournament')->completeFixture(
                $tournamentId,
                $fixtureId,
                (int) ($data['match_id'] ?? 0),
                trim((string) ($data['reason'] ?? '')),
                isset($data['expected_version']) ? (int) $data['expected_version'] : null
            );
            return redirect()->to('/tournaments/' . $tournamentId . '/fixtures/' . $fixtureId)
                ->with('message', 'Fixture completed.');
        } catch (Throwable $exception) {
            return $this->fixtureFailure(
                $exception,
                '/tournaments/' . $tournamentId . '/fixtures/' . $fixtureId
            );
        }
    }

    private function fixtureStatus(int $tournamentId, int $fixtureId, string $action): ResponseInterface|string
    {
        try {
            $data = $this->request->getPost();
            unset($data[csrf_token()]);
            $reason = trim((string) ($data['reason'] ?? ''));
            if ($action === 'postpone') {
                service('tournament')->postponeFixture($tournamentId, $fixtureId, $reason);
            } else {
                service('tournament')->cancelFixture($tournamentId, $fixtureId, $reason);
            }
            return redirect()->to('/tournaments/' . $tournamentId . '/fixtures/' . $fixtureId)
                ->with('message', 'Fixture status updated.');
        } catch (Throwable $exception) {
            return $this->fixtureFailure(
                $exception,
                '/tournaments/' . $tournamentId . '/fixtures/' . $fixtureId
            );
        }
    }

    private function fixtureFailure(
        Throwable $exception,
        string $redirectPath,
        bool $includeFields = false
    ): ResponseInterface|string {
        $message = $this->tournamentError($exception);
        $fieldErrors = $includeFields ? $this->fieldErrors($exception) : [];
        $acceptsJson = str_contains(
            strtolower($this->request->getHeaderLine('Accept')),
            'application/json'
        );

        if ($this->request->isAJAX() || $acceptsJson) {
            return $this->response
                ->setStatusCode(422)
                ->setJSON([
                    'message' => $message,
                    'errors' => $fieldErrors,
                ]);
        }

        $response = redirect()->to($redirectPath)
            ->withInput()
            ->with('error', $message);

        if ($includeFields) {
            $response = $response->with('field_errors', $fieldErrors);
        }

        return $response;
    }

    private function fixtureFromTournament(array $tournament, int $fixtureId): ?array
    {
        foreach ($tournament['fixtures'] ?? [] as $fixture) {
            if ((int) ($fixture['id'] ?? 0) === $fixtureId) {
                return $fixture;
            }
        }
        return null;
    }

    private function fieldErrors(Throwable $exception): array
    {
        return match ($exception->getMessage()) {
            'Invalid tournament name.' => ['name' => 'Enter a tournament name up to 150 characters.'],
            'Invalid tournament type.' => ['tournament_type' => 'Select a valid tournament type.'],
            'Invalid tournament status.' => ['status' => 'Select a valid tournament status.'],
            'New tournaments must start in the planned status.' => ['status' => 'New Tournaments must start as planned.'],
            'This Tournament status transition is not allowed.' => ['status' => 'That Tournament status transition is not allowed.'],
            'A reason is required when cancelling a Tournament.' => ['reason' => 'Enter a reason when cancelling the Tournament.'],
            'Tournament dates are required.' => [
                'starts_on' => 'Enter a start date.',
                'ends_on' => 'Enter an end date.',
            ],
            'Invalid tournament start date.' => ['starts_on' => 'Enter a valid start date.'],
            'Invalid tournament end date.' => ['ends_on' => 'Enter a valid end date.'],
            'Invalid tournament dates.' => ['ends_on' => 'End date must be on or after the start date.'],
            'Invalid tournament ground.' => ['ground_id' => 'Select a valid Ground for this branch.'],
            'Invalid tournament branch.' => ['branch_id' => 'Select an active branch you are authorized to manage.'],
            'Enter a valid fixture number, round, and two different teams.' => [
                'fixture_number' => 'Enter a valid fixture number.',
                'round' => 'Enter the fixture round.',
                'home_team' => 'Select Team 1.',
                'away_team' => 'Select Team 2.',
            ],
            'Enter a valid match number, round, and two different teams.' => [
                'match_number' => 'Enter a valid Match Number.',
                'round' => 'Enter the fixture round.',
                'home_team' => 'Select Team 1.',
                'away_team' => 'Select Team 2.',
            ],
            'Home and away teams must belong to this Tournament.' => [
                'home_team' => 'Select Team 1 from this Tournament.',
                'away_team' => 'Select Team 2 from this Tournament.',
            ],
            'Match date and start time are required.' => [
                'match_date' => 'Enter the fixture date.',
                'start_time' => 'Enter the fixture start time.',
            ],
            'Enter a valid fixture date.' => ['match_date' => 'Enter a valid fixture date.'],
            'Enter a valid fixture start time.' => ['start_time' => 'Enter a valid fixture start time.'],
            'Enter a valid fixture date and start time.' => [
                'match_date' => 'Enter a valid fixture date.',
                'start_time' => 'Enter a valid fixture start time.',
            ],
            'Fixture date must be within the Tournament dates.' => [
                'match_date' => 'Choose a date within the Tournament start and end dates.',
            ],
            'A team already has a fixture at that date and time.' => ['match_date' => 'Choose a different date and time for this team.'],
            'A reason is required when cancelling or postponing a fixture.' => ['reason' => 'Enter a reason.'],
            'A reason is required for fixture status changes.' => ['reason' => 'Enter a reason.'],
            'Completed fixture corrections require a reason.' => ['correction_reason' => 'Enter the correction reason.'],
            'Completed fixtures require a Match result reference.' => ['match_id' => 'Select a completed Match result.'],
            'Only completed Fixtures may link a Match result.' => ['match_id' => 'Link a Match only when completing the Fixture.'],
            'Select a scheduled or completed Match from the Tournament branch.' => ['match_id' => 'Select a scheduled or completed Match from this Tournament branch.'],
            'The Connected Match teams must match Team 1 and Team 2 in either direction.' => ['match_id' => 'Choose a Match with the same two teams in either direction.'],
            'This Match is already linked to another fixture.' => ['match_id' => 'Select a Match that is not already linked.'],
            'Select an active Ground for the Tournament branch.' => ['ground_id' => 'Select an active Ground for the Tournament branch.'],
            'Cancelled fixtures cannot be completed.' => ['status' => 'Cancelled fixtures cannot be completed.'],
            'Completed fixtures cannot be changed with this action.' => ['status' => 'Completed fixtures cannot be changed with this action.'],
            'Completed fixtures cannot be reopened.' => ['status' => 'Completed fixtures cannot be reopened.'],
            'A reason is required when rescheduling a fixture.' => ['reason' => 'Enter a reason when rescheduling this Fixture.'],
            'The Fixture correction is stale. Reload before correcting it.', 'The Fixture completion is stale. Reload before completing it.' => [
                'expected_version' => 'Reload this Fixture before continuing.',
            ],
            default => [],
        };
    }

    private function save(?int $id, string $message): ResponseInterface|string
    {
        try {
            $data = $this->request->getPost();
            unset($data[csrf_token()]);
            $saved = service('tournament')->save($data, $id);
            return redirect()->to('/tournaments/' . $saved)->with('message', $message);
        } catch (Throwable $exception) {
            return redirect()->back()
                ->withInput()
                ->with('error', $this->tournamentError($exception))
                ->with('field_errors', $this->fieldErrors($exception));
        }
    }

    private function tournamentError(Throwable $exception): string
    {
        return match ($exception->getMessage()) {
            'Invalid tournament name.' => 'Enter a tournament name up to 150 characters.',
            'Invalid tournament type.' => 'Select a valid tournament type.',
            'Invalid tournament status.' => 'Select a valid tournament status.',
            'New tournaments must start in the planned status.' => 'New Tournaments must start as planned.',
            'This Tournament status transition is not allowed.' => 'That Tournament status transition is not allowed.',
            'A reason is required when cancelling a Tournament.' => 'Enter a reason when cancelling the Tournament.',
            'Tournament dates are required.' => 'Enter both tournament dates.',
            'Invalid tournament start date.' => 'Enter a valid start date.',
            'Invalid tournament end date.' => 'Enter a valid end date.',
            'Invalid tournament dates.' => 'End date must be on or after the start date.',
            'Invalid tournament ground.' => 'Select a valid Ground for this branch.',
            'Invalid tournament branch.' => 'Select an active branch you are authorized to manage.',
            'Invalid tournament page.' => 'Enter a valid page number.',
            'Fixture not found.' => 'The requested fixture was not found.',
            'A reason is required when cancelling or postponing a fixture.' => 'Enter a reason for this status change.',
            'A reason is required for fixture status changes.' => 'Enter a reason for this status change.',
            'Enter a valid fixture date.' => 'Enter a valid fixture date.',
            'Enter a valid fixture start time.' => 'Enter a valid fixture start time.',
            'Enter a valid fixture date and start time.' => 'Enter a valid fixture date and start time.',
            'Completed fixture corrections require a reason.' => 'A reason is required to correct a completed fixture.',
            'Select a completed Match result for this fixture.' => 'Select a completed Match result before completing the fixture.',
            'Completed fixtures require a Match result reference.' => 'Select a completed Match result before saving a completed fixture.',
            'Only completed Fixtures may link a Match result.' => 'Link a Match only when completing the Fixture.',
            'Select a scheduled or completed Match from the Tournament branch.' => 'Select a scheduled or completed Match from this Tournament branch.',
            'This Match is already linked to another fixture.' => 'That Match is already linked to another fixture.',
            'Cancelled fixtures cannot be completed.' => 'Cancelled fixtures cannot be completed.',
            'Completed fixtures cannot be changed with this action.' => 'Completed fixtures cannot be changed with this action.',
            'Completed fixtures cannot be reopened.' => 'Completed fixtures cannot be reopened.',
            'A reason is required when rescheduling a fixture.' => 'Enter a reason when rescheduling this Fixture.',
            'The Fixture correction is stale. Reload before correcting it.' => 'This Fixture was changed by another request. Reload before correcting it.',
            'The Fixture completion is stale. Reload before completing it.' => 'This Fixture was changed by another request. Reload before completing it.',
            'A team already has a fixture at that date and time.' => 'A team already has a fixture at that date and time.',
            'Fixture date must be within the Tournament dates.' => 'The fixture date must be within the Tournament dates.',
            'Select an active Ground for the Tournament branch.' => 'Select an active Ground for the Tournament branch.',
            default => $this->safe($exception),
        };
    }

    private function error(Throwable $exception): ResponseInterface
    {
        return $this->response
            ->setStatusCode($exception->getMessage() === 'Forbidden' ? 403 : 404)
            ->setBody(esc($this->safe($exception)));
    }
}
