<?php

declare(strict_types=1);

namespace App\Services;

use App\Modules\Tournaments\Models\TournamentModel;
use Config\Database;
use DateTimeImmutable;
use RuntimeException;
use Throwable;

final class TournamentManagementService
{
    private const TOURNAMENTS_PER_PAGE = 20;
    private const MCC_TIMEZONE = 'Asia/Kolkata';
    private const TYPES = ['school', 'club', 'district', 'mca', 'state', 'national', 'international', 'england_tour'];
    private const STATUSES = ['planned', 'active', 'completed', 'cancelled'];

    public function list(array $filters = []): array
    {
        $branches = $this->branches();
        $db = Database::connect('default');
        $builder = $db->table('tournaments t')
            ->select('t.*, b.name AS branch_name, g.name AS ground_name')
            ->join('branches b', 'b.id = t.branch_id')
            ->join('grounds g', 'g.id = t.ground_id AND g.branch_id = t.branch_id', 'left')
            ->whereIn('t.branch_id', $branches ?: [0]);
        $query = trim((string) ($filters['q'] ?? ''));
        if ($query !== '') {
            $builder->groupStart()
                ->like('t.name', $query)
                ->orLike('t.tournament_type', $query)
                ->orLike('b.name', $query)
                ->groupEnd();
        }
        if (in_array($filters['status'] ?? '', self::STATUSES, true)) {
            $builder->where('t.status', $filters['status']);
        }
        if (in_array($filters['tournament_type'] ?? '', self::TYPES, true)) {
            $builder->where('t.tournament_type', $filters['tournament_type']);
        }
        if (($filters['date'] ?? '') !== '') {
            $date = $this->calendarDate((string) $filters['date']);
            $builder->where('t.starts_on <=', $date->format('Y-m-d'))
                ->where('t.ends_on >=', $date->format('Y-m-d'));
        }
        $total = (clone $builder)->countAllResults();
        $pages = max(1, (int) ceil($total / self::TOURNAMENTS_PER_PAGE));
        $page = $this->pageNumber($filters['page'] ?? 1);
        $page = min($page, $pages);
        return [
            'tournaments' => $builder
                ->orderBy('t.starts_on', 'DESC')
                ->orderBy('t.id', 'DESC')
                ->limit(self::TOURNAMENTS_PER_PAGE, ($page - 1) * self::TOURNAMENTS_PER_PAGE)
                ->get()
                ->getResultArray(),
            'filters' => $filters,
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
        ];
    }

    /** @return array<string, mixed> */
    public function listFixtures(array $filters = []): array
    {
        $branches = $this->branches();
        $db = Database::connect('default');
        $builder = $db->table('tournament_fixtures tf')
            ->select('tf.*, t.name AS tournament_name, t.branch_id, b.name AS branch_name, g.name AS ground_name')
            ->join('tournaments t', 't.id = tf.tournament_id', 'inner')
            ->join('branches b', 'b.id = t.branch_id', 'inner')
            ->join('grounds g', 'g.id = tf.ground_id AND g.branch_id = t.branch_id', 'left')
            ->whereIn('t.branch_id', $branches ?: [0]);

        $query = trim((string) ($filters['q'] ?? ''));
        if ($query !== '') {
            $builder->groupStart()
                ->like('t.name', $query)
                ->orLike('tf.home_team', $query)
                ->orLike('tf.away_team', $query)
                ->orLike('tf.round', $query)
                ->groupEnd();
        }

        $status = strtolower(trim((string) ($filters['status'] ?? '')));
        if (in_array($status, ['scheduled', 'postponed', 'completed', 'cancelled'], true)) {
            $builder->where('tf.status', $status === 'scheduled' ? 'planned' : $status);
        }

        $total = (clone $builder)->countAllResults();
        $pages = max(1, (int) ceil($total / 20));
        $page = min($this->pageNumber($filters['page'] ?? 1), $pages);
        $rows = $builder
            ->orderBy('tf.scheduled_at', 'DESC')
            ->orderBy('tf.id', 'DESC')
            ->limit(20, ($page - 1) * 20)
            ->get()
            ->getResultArray();

        foreach ($rows as &$fixture) {
            if (($fixture['status'] ?? '') === 'planned') {
                $fixture['status'] = 'scheduled';
            }
            $local = $this->local($fixture['scheduled_at'] ?? null);
            $fixture['display_datetime'] = $local?->format('d-m-Y h:i A');
        }
        unset($fixture);

        return [
            'fixtures' => $rows,
            'filters' => $filters,
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
        ];
    }

    public function formOptions(string $permission = 'tournament.create', ?int $branchId = null): array
    {
        $branchIds = $this->authorizedBranches($permission);
        if ($branchId !== null) {
            if (! in_array($branchId, $branchIds, true)) {
                return ['branches' => [], 'grounds' => [], 'players' => [], 'availablePlayers' => [], 'opponents' => []];
            }

            $optionBranchIds = [$branchId];
        } else {
            $optionBranchIds = $branchIds;
        }

        if ($optionBranchIds === []) {
            return ['branches' => [], 'grounds' => [], 'players' => [], 'availablePlayers' => [], 'opponents' => []];
        }
        $db = Database::connect('default');
        $players = $db->table('players p')
            ->select('p.id, p.branch_id, pe.full_name')
            ->join('persons pe', 'pe.id = p.person_id')
            ->whereIn('p.branch_id', $optionBranchIds)
            ->where('p.status', 'active')
            ->orderBy('pe.full_name')
            ->get()
            ->getResultArray();

        return [
            'branches' => $db->table('branches')
                ->whereIn('id', $branchIds)
                ->where('is_active', 1)
                ->orderBy('name')
                ->get()
                ->getResultArray(),
            'grounds' => $db->table('grounds')
                ->whereIn('branch_id', $optionBranchIds)
                ->where('is_active', 1)
                ->orderBy('name')
                ->get()
                ->getResultArray(),
            'opponents' => $db->table('opponents')
                ->whereIn('branch_id', $optionBranchIds)
                ->where('is_active', 1)
                ->orderBy('name')
                ->get()
                ->getResultArray(),
            'players' => $players,
            'availablePlayers' => $players,
        ];
    }

    public function hasAuthorizedBranch(string $permission): bool
    {
        return $this->authorizedBranches($permission) !== [];
    }

    public function find(int $id): array
    {
        $tournament = $this->tournament($id);
        $this->allow((int) $tournament['branch_id'], 'tournament.view');
        $db = Database::connect('default');
        $branch = (int) $tournament['branch_id'];
        $tournament['branch_name'] = $db->table('branches')->where('id', $branch)->get()->getRow('name');
        $tournament['ground_name'] = $tournament['ground_id'] === null ? null : $db->table('grounds')
            ->where(['id' => (int) $tournament['ground_id'], 'branch_id' => $branch])
            ->get()->getRow('name');
        $hasOpponentColumn = $db->fieldExists('opponent_id', 'tournament_participants');
        $participantQuery = $db->table('tournament_participants tp')
            ->select('tp.*, p.branch_id AS player_branch_id, pe.full_name AS player_name');
        if ($hasOpponentColumn) {
            $participantQuery
                ->select('o.name AS opponent_name')
                ->join('opponents o', 'o.id = tp.opponent_id AND o.branch_id = ' . $branch, 'left');
        }
        $tournament['participants'] = $participantQuery
            ->join('players p', 'p.id = tp.player_id AND p.branch_id = ' . $branch, 'left')
            ->join('persons pe', 'pe.id = p.person_id', 'left')
            ->where('tp.tournament_id', $id)
            ->groupStart()
                ->where('tp.participant_type', 'team')
                ->orWhere('p.id IS NOT NULL', null, false)
            ->groupEnd()
            ->orderBy('tp.participant_type')
            ->orderBy('tp.team_name')
            ->get()
            ->getResultArray();
        $tournament['fixtures'] = $db->table('tournament_fixtures tf')
            ->select('tf.*, g.name AS ground_name, m.opponent, m.status AS match_status, m.result AS match_result, m.score_summary')
            ->join('matches m', 'm.id = tf.match_id AND m.branch_id = ' . $branch, 'left')
            ->join('grounds g', 'g.id = tf.ground_id AND g.branch_id = ' . $branch, 'left')
            ->where('tf.tournament_id', $id)
            ->orderBy('tf.fixture_number')
            ->get()
            ->getResultArray();
        foreach ($tournament['fixtures'] as &$fixture) {
            if (($fixture['status'] ?? '') === 'planned') {
                $fixture['status'] = 'scheduled';
            }
            $local = $this->local($fixture['scheduled_at'] ?? null);
            $fixture['match_date'] = $local?->format('Y-m-d');
            $fixture['start_time'] = $local?->format('H:i');
            $fixture['display_datetime'] = $local?->format('d-m-Y h:i A');
        }
        unset($fixture);
        $tournament['standings'] = $this->standings($id, $branch);
        $tournament['audit_history'] = $this->auditHistory($db, $id, $branch);
        return $tournament;
    }

    public function save(array $input, ?int $id = null): int
    {
        $branch = (int) ($input['branch_id'] ?? 0);
        $db = Database::connect('default');
        $existing = $id === null ? null : $this->tournament($id);
        if ($existing !== null) {
            $this->allow((int) $existing['branch_id'], 'tournament.update');
            if ($branch !== (int) $existing['branch_id']) {
                throw new RuntimeException('Invalid tournament.');
            }
        } else {
            $this->allow($branch, 'tournament.create');
            if ($db->table('branches')->where(['id' => $branch, 'is_active' => 1])->countAllResults() === 0) {
                throw new RuntimeException('Invalid tournament branch.');
            }
        }
        $db->transStart();
        try {
            $data = $this->validated($db, $input, $branch);
            $reason = $this->validateStatusTransition($existing, (string) $data['status'], $input);
            if ($id !== null) {
                $this->validateExistingFixtures($db, $id, $branch, $data);
            }
            if ($id === null) {
                $now = gmdate('Y-m-d H:i:s');
                $db->table('tournaments')->insert($data + [
                    'created_by' => $this->actor(),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $id = (int) $db->insertID();
            } else {
                $db->table('tournaments')
                    ->where('id', $id)
                    ->update($data + ['updated_at' => gmdate('Y-m-d H:i:s')]);
            }
            $this->participants($db, $id, $branch, $input);
            service('auditPersistence')->record(
                $this->actor(),
                $existing === null ? 'tournament_created' : 'tournament_updated',
                'tournament',
                (string) $id,
                $branch,
                $existing,
                $data,
                $reason,
                $db
            );
            $db->transComplete();
            if (!$db->transStatus()) {
                throw new RuntimeException('Unable to save tournament.');
            }
            return $id;
        } catch (Throwable $exception) {
            $db->transRollback();
            $db->resetTransStatus();
            throw $exception;
        }
    }

    /** @return array<string, mixed> */
    public function fixtureOptions(int $tournamentId, string $permission = 'tournament.view'): array
    {
        $tournament = $this->tournament($tournamentId);
        $branch = (int) $tournament['branch_id'];
        $this->allow($branch, $permission);
        $db = Database::connect('default');

        $teamRows = $db->table('tournament_participants')
            ->select('team_name')
            ->where(['tournament_id' => $tournamentId, 'participant_type' => 'team'])
            ->where('team_name IS NOT NULL', null, false)
            ->orderBy('team_name')
            ->get()
            ->getResultArray();
        $teams = [];
        foreach ($teamRows as $teamRow) {
            foreach (preg_split('/\R/', (string) ($teamRow['team_name'] ?? '')) ?: [] as $teamName) {
                $teamName = trim($teamName);
                if ($teamName !== '') {
                    $teams[$teamName] = ['team_name' => $teamName];
                }
            }
        }

        $matchOptions = $db->table('matches')
            ->where(['branch_id' => $branch])
            ->whereIn('status', ['scheduled', 'completed']);
        if ($db->fieldExists('match_category', 'matches')) {
            $matchOptions->where('match_category', 'tournament');
        } else {
            $matchOptions->where('id', 0);
        }

        return [
            'tournament' => $tournament,
            'teams' => array_values($teams),
            'grounds' => $db->table('grounds')
                ->where(['branch_id' => $branch, 'is_active' => 1])
                ->orderBy('name')
                ->get()
                ->getResultArray(),
            'matches' => $matchOptions
                ->orderBy('starts_at', 'DESC')
                ->get()
                ->getResultArray(),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function fixtureMatchOptions(int $tournamentId, string $team1, string $team2): array
    {
        if ($team1 === '' || $team2 === '' || $team1 === $team2) {
            return [];
        }

        $options = $this->fixtureOptions($tournamentId, 'tournament.update')['matches'] ?? [];
        $matches = array_values(array_filter(
            $options,
            static function (array $match) use ($team1, $team2): bool {
                $matchTeam1 = (string) ($match['your_team'] ?? '');
                $matchTeam2 = (string) ($match['opponent'] ?? '');

                return (strcasecmp($matchTeam1, $team1) === 0 && strcasecmp($matchTeam2, $team2) === 0)
                    || (strcasecmp($matchTeam1, $team2) === 0 && strcasecmp($matchTeam2, $team1) === 0);
            },
        ));

        foreach ($matches as &$match) {
            $match['starts_at_display'] = mcc_user_datetime($match['starts_at'] ?? null);
        }
        unset($match);

        return $matches;
    }

    public function fixture(int $tournamentId, array $input, ?int $fixtureId = null): int
    {
        $tournament = $this->tournament($tournamentId);
        $branch = (int) $tournament['branch_id'];
        $this->allow($branch, 'tournament.update');
        $db = Database::connect('default');
        $existing = $fixtureId === null ? null : $this->fixtureRow($db, $tournamentId, $fixtureId);
        if ($fixtureId !== null && $existing === null) {
            throw new RuntimeException('Fixture not found.');
        }
        if ($existing !== null && (string) $existing['status'] === 'completed') {
            $reason = trim((string) ($input['correction_reason'] ?? ''));
            if ($reason === '') {
                throw new RuntimeException('Completed fixture corrections require a reason.');
            }
            $this->assertFixtureVersion($existing, $input);
        }

        $data = $this->validatedFixture($db, $tournament, $input, $fixtureId);
        $this->assertFixtureConflict(
            $db,
            $tournamentId,
            $data['scheduled_at'],
            (string) $data['home_team'],
            (string) $data['away_team'],
            $fixtureId
        );
        if ($existing !== null && (string) $existing['status'] === 'completed'
            && (string) ($data['status'] ?? '') !== 'completed') {
            throw new RuntimeException('Completed fixtures cannot be reopened.');
        }
        if ($existing !== null && (string) $existing['status'] === 'postponed'
            && (string) ($data['scheduled_at'] ?? '') !== (string) ($existing['scheduled_at'] ?? '')
            && trim((string) ($input['reason'] ?? '')) === '') {
            throw new RuntimeException('A reason is required when rescheduling a fixture.');
        }
        $db->transStart();
        try {
            $now = gmdate('Y-m-d H:i:s');
            if ($fixtureId === null) {
                $data['created_at'] = $now;
                $data['updated_at'] = $now;
                $db->table('tournament_fixtures')->insert($data);
                $fixtureId = (int) $db->insertID();
                $action = 'tournament_fixture_created';
            } else {
                $data['updated_at'] = $now;
                $update = $db->table('tournament_fixtures')->where('id', $fixtureId);
                if ($existing !== null && (string) $existing['status'] === 'completed') {
                    $update->where('version', (int) ($input['expected_version'] ?? 0));
                }
                $update->set($data)->set('version', 'version + 1', false)->update();
                if ($existing !== null && (string) $existing['status'] === 'completed'
                    && $db->affectedRows() < 1) {
                    throw new RuntimeException('The Fixture correction is stale. Reload before correcting it.');
                }
                $action = ($existing !== null && (string) $existing['status'] === 'completed')
                    ? 'tournament_fixture_corrected'
                    : 'tournament_fixture_updated';
            }
            service('auditPersistence')->record(
                $this->actor(),
                $action,
                'tournament_fixture',
                (string) $fixtureId,
                $branch,
                $existing,
                $data,
                trim((string) ($input['reason'] ?? $input['correction_reason'] ?? '')) ?: null,
                $db
            );
            $db->transComplete();
            if (! $db->transStatus()) {
                throw new RuntimeException('Unable to save fixture.');
            }
            return $fixtureId;
        } catch (Throwable $exception) {
            $db->transRollback();
            $db->resetTransStatus();
            throw $exception;
        }
    }

    public function postponeFixture(int $tournamentId, int $fixtureId, string $reason): void
    {
        $this->changeFixtureStatus($tournamentId, $fixtureId, 'postponed', $reason);
    }

    public function cancelFixture(int $tournamentId, int $fixtureId, string $reason): void
    {
        $this->changeFixtureStatus($tournamentId, $fixtureId, 'cancelled', $reason);
    }

    public function completeFixture(
        int $tournamentId,
        int $fixtureId,
        int $matchId,
        string $reason = '',
        ?int $expectedVersion = null
    ): void
    {
        $tournament = $this->tournament($tournamentId);
        $branch = (int) $tournament['branch_id'];
        $this->allow($branch, 'tournament.update');
        $db = Database::connect('default');
        $existing = $this->fixtureRow($db, $tournamentId, $fixtureId);
        if ($existing === null) {
            throw new RuntimeException('Fixture not found.');
        }
        if ((string) $existing['status'] === 'completed') {
            return;
        }
        if ((string) $existing['status'] === 'cancelled') {
            throw new RuntimeException('Cancelled fixtures cannot be completed.');
        }
        $match = $db->table('matches')
            ->where(['id' => $matchId, 'branch_id' => $branch, 'status' => 'completed'])
            ->whereIn('result', ['won', 'lost', 'draw', 'no_result'])
            ->get()
            ->getRowArray();
        if (! is_array($match)) {
            throw new RuntimeException('Select a completed Match result for this fixture.');
        }
        if ($db->table('tournament_fixtures')
            ->where(['match_id' => $matchId, 'id !=' => $fixtureId])
            ->countAllResults() > 0) {
            throw new RuntimeException('This Match is already linked to another fixture.');
        }
        $db->transStart();
        try {
            $update = $db->table('tournament_fixtures')->where('id', $fixtureId);
            if ($expectedVersion !== null) {
                $update->where('version', $expectedVersion);
            }
            $update->set([
                'match_id' => $matchId,
                'status' => 'completed',
                'updated_at' => gmdate('Y-m-d H:i:s'),
            ])->set('version', 'version + 1', false)->update();
            if ($expectedVersion !== null && $db->affectedRows() < 1) {
                throw new RuntimeException('The Fixture completion is stale. Reload before completing it.');
            }
            service('auditPersistence')->record(
                $this->actor(),
                'tournament_fixture_completed',
                'tournament_fixture',
                (string) $fixtureId,
                $branch,
                $existing,
                ['match_id' => $matchId, 'status' => 'completed'],
                $reason !== '' ? $reason : null,
                $db
            );
            $db->transComplete();
            if (! $db->transStatus()) {
                throw new RuntimeException('Unable to complete fixture.');
            }
        } catch (Throwable $exception) {
            $db->transRollback();
            $db->resetTransStatus();
            throw $exception;
        }
    }

    private function changeFixtureStatus(int $tournamentId, int $fixtureId, string $status, string $reason): void
    {
        $tournament = $this->tournament($tournamentId);
        $branch = (int) $tournament['branch_id'];
        $this->allow($branch, 'tournament.update');
        $reason = trim($reason);
        if ($reason === '') {
            throw new RuntimeException('A reason is required for fixture status changes.');
        }
        $db = Database::connect('default');
        $existing = $this->fixtureRow($db, $tournamentId, $fixtureId);
        if ($existing === null) {
            throw new RuntimeException('Fixture not found.');
        }
        if ((string) $existing['status'] === $status) {
            return;
        }
        if ((string) $existing['status'] === 'completed') {
            throw new RuntimeException('Completed fixtures cannot be changed with this action.');
        }
        $db->transStart();
        try {
            $db->table('tournament_fixtures')->where('id', $fixtureId)
                ->set([
                'status' => $status,
                'updated_at' => gmdate('Y-m-d H:i:s'),
                ])->set('version', 'version + 1', false)->update();
            service('auditPersistence')->record(
                $this->actor(),
                'tournament_fixture_' . $status,
                'tournament_fixture',
                (string) $fixtureId,
                $branch,
                $existing,
                ['status' => $status],
                $reason,
                $db
            );
            $db->transComplete();
            if (! $db->transStatus()) {
                throw new RuntimeException('Unable to update fixture status.');
            }
        } catch (Throwable $exception) {
            $db->transRollback();
            $db->resetTransStatus();
            throw $exception;
        }
    }

    private function standings(int $id, int $branch): array
    {
        $db = Database::connect('default');
        $teams = $db->table('tournament_participants')
            ->select('team_name')
            ->where([
                'tournament_id' => $id,
                'participant_type' => 'team',
            ])
            ->where('team_name IS NOT NULL', null, false)
            ->get()
            ->getResultArray();

        $table = [];
        foreach ($teams as $team) {
            $name = trim((string) ($team['team_name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $table[$name] = $this->emptyStanding($name);
        }

        $rows = $db->table('tournament_fixtures tf')
            ->select('tf.id, tf.home_team, tf.away_team, tf.status, m.status AS match_status, m.result, m.branch_id AS match_branch_id')
            ->join('matches m', 'm.id = tf.match_id AND m.branch_id = ' . $branch . ' AND m.status = \'completed\'', 'left')
            ->where('tf.tournament_id', $id)
            ->where('tf.status', 'completed')
            ->get()
            ->getResultArray();

        $seenFixtures = [];
        foreach ($rows as $row) {
            $fixtureId = (int) ($row['id'] ?? 0);
            $home = trim((string) ($row['home_team'] ?? ''));
            $away = trim((string) ($row['away_team'] ?? ''));
            $result = (string) ($row['result'] ?? '');
            if (
                $fixtureId < 1
                || isset($seenFixtures[$fixtureId])
                || $home === ''
                || $away === ''
                || $home === $away
                || ! isset($table[$home], $table[$away])
                || $row['match_branch_id'] === null
                || ! in_array($result, ['won', 'lost', 'draw', 'no_result'], true)
            ) {
                continue;
            }

            $seenFixtures[$fixtureId] = true;
            $table[$home]['played']++;
            $table[$away]['played']++;
            if ($result === 'draw') {
                $table[$home]['tied']++;
                $table[$away]['tied']++;
                $table[$home]['points']++;
                $table[$away]['points']++;
            } elseif ($result === 'no_result') {
                $table[$home]['no_result']++;
                $table[$away]['no_result']++;
                $table[$home]['points']++;
                $table[$away]['points']++;
            } elseif ($result === 'won') {
                $table[$home]['won']++;
                $table[$away]['lost']++;
                $table[$home]['points'] += 2;
            } else {
                $table[$home]['lost']++;
                $table[$away]['won']++;
                $table[$away]['points'] += 2;
            }
        }
        $table = array_values($table);
        usort($table, static function (array $left, array $right): int {
            return [$right['points'], $right['won'], $left['team_name']]
                <=> [$left['points'], $left['won'], $right['team_name']];
        });

        foreach ($table as $index => &$standing) {
            $standing['position'] = $index + 1;
        }
        unset($standing);

        return $table;
    }

    /** @return array<string, int|string> */
    private function emptyStanding(string $team): array
    {
        return [
            'team_name' => $team,
            'played' => 0,
            'won' => 0,
            'lost' => 0,
            'tied' => 0,
            'no_result' => 0,
            'points' => 0,
        ];
    }

    /** @return array<string, mixed> */
    private function validatedFixture(object $db, array $tournament, array $input, ?int $fixtureId): array
    {
        $tournamentId = (int) $tournament['id'];
        $branch = (int) $tournament['branch_id'];
        $home = trim((string) ($input['home_team'] ?? ''));
        $away = trim((string) ($input['away_team'] ?? ''));
        $round = trim((string) ($input['round'] ?? ''));
        $matchNumberInput = trim((string) ($input['match_number'] ?? ''));
        $matchNumber = $matchNumberInput === '' ? null : (int) $matchNumberInput;
        $number = (int) ($input['fixture_number'] ?? 0);
        if ($number < 1 || ($matchNumber !== null && $matchNumber < 1)
            || $home === '' || $away === '' || $home === $away || $round === '') {
            throw new RuntimeException('Enter a valid fixture number, round, and two different teams.');
        }
        $teams = array_column(
            $db->table('tournament_participants')
                ->select('team_name')
                ->where(['tournament_id' => $tournamentId, 'participant_type' => 'team'])
                ->get()
                ->getResultArray(),
            'team_name'
        );
        if (! in_array($home, $teams, true) || ! in_array($away, $teams, true)) {
            throw new RuntimeException('Home and away teams must belong to this Tournament.');
        }
        $date = trim((string) ($input['match_date'] ?? ''));
        $time = trim((string) ($input['start_time'] ?? ''));
        if ($date === '' || $time === '') {
            throw new RuntimeException('Match date and start time are required.');
        }
        $scheduled = $this->localFixtureDateTime($date, $time);
        $scheduledDate = $scheduled->format('Y-m-d');
        if ($scheduledDate < (string) $tournament['starts_on'] || $scheduledDate > (string) $tournament['ends_on']) {
            throw new RuntimeException('Fixture date must be within the Tournament dates.');
        }
        $groundId = (int) ($input['ground_id'] ?? 0);
        if ($groundId > 0 && $db->table('grounds')->where([
            'id' => $groundId,
            'branch_id' => $branch,
            'is_active' => 1,
        ])->countAllResults() < 1) {
            throw new RuntimeException('Select an active Ground for the Tournament branch.');
        }
        $status = strtolower(trim((string) ($input['status'] ?? 'scheduled')));
        if ($status === 'planned') {
            $status = 'scheduled';
        }
        if (! in_array($status, ['scheduled', 'completed', 'cancelled', 'postponed'], true)) {
            throw new RuntimeException('Select a valid fixture status.');
        }
        if (in_array($status, ['cancelled', 'postponed'], true)
            && trim((string) ($input['reason'] ?? $input['correction_reason'] ?? '')) === '') {
            throw new RuntimeException('A reason is required when cancelling or postponing a fixture.');
        }
        $matchId = (int) ($input['match_id'] ?? 0);
        if ($matchId > 0) {
            $match = $db->table('matches')->where([
                'id' => $matchId,
                'branch_id' => $branch,
            ])->whereIn('status', ['scheduled', 'completed'])->get()->getRowArray();
            if (! is_array($match)) {
                throw new RuntimeException('Select a scheduled or completed Match from the Tournament branch.');
            }
            $matchTeam1 = trim((string) ($match['your_team'] ?? ''));
            $matchTeam2 = trim((string) ($match['opponent'] ?? ''));
            $fixtureTeam1 = trim($home);
            $fixtureTeam2 = trim($away);
            $sameDirection = strcasecmp($matchTeam1, $fixtureTeam1) === 0
                && strcasecmp($matchTeam2, $fixtureTeam2) === 0;
            $reverseDirection = strcasecmp($matchTeam1, $fixtureTeam2) === 0
                && strcasecmp($matchTeam2, $fixtureTeam1) === 0;
            if ($matchTeam1 !== '' && $matchTeam2 !== '' && ! $sameDirection && ! $reverseDirection) {
                throw new RuntimeException('The Connected Match teams must match Team 1 and Team 2 in either direction.');
            }
            $linked = $db->table('tournament_fixtures')
                ->where('match_id', $matchId);
            if ($fixtureId !== null) {
                $linked->where('id !=', $fixtureId);
            }
            if ($linked->countAllResults() > 0) {
                throw new RuntimeException('This Match is already linked to another fixture.');
            }
        }

        return [
            'tournament_id' => $tournamentId,
            'fixture_number' => $number,
            'round' => $round,
            'match_number' => $matchNumber,
            'home_team' => $home,
            'away_team' => $away,
            'match_id' => $matchId > 0 ? $matchId : null,
            'ground_id' => $groundId > 0 ? $groundId : null,
            'scheduled_at' => $scheduled->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
            'status' => $status,
        ];
    }

    private function assertFixtureConflict(
        object $db,
        int $tournamentId,
        string $scheduledAt,
        string $homeTeam,
        string $awayTeam,
        ?int $fixtureId
    ): void
    {
        $query = $db->table('tournament_fixtures')
            ->where(['tournament_id' => $tournamentId, 'scheduled_at' => $scheduledAt]);
        if ($fixtureId !== null) {
            $query->where('id !=', $fixtureId);
        }
        foreach ($query->get()->getResultArray() as $fixture) {
            if (
                in_array($homeTeam, [(string) $fixture['home_team'], (string) $fixture['away_team']], true)
                || in_array($awayTeam, [(string) $fixture['home_team'], (string) $fixture['away_team']], true)
            ) {
                throw new RuntimeException('A team already has a fixture at that date and time.');
            }
        }
    }

    /** @return array<string, mixed>|null */
    private function fixtureRow(object $db, int $tournamentId, int $fixtureId): ?array
    {
        $row = $db->table('tournament_fixtures tf')
            ->select('tf.*, g.name AS ground_name, m.result AS match_result')
            ->join('tournaments t', 't.id = tf.tournament_id', 'inner')
            ->join('grounds g', 'g.id = tf.ground_id AND g.branch_id = t.branch_id', 'left')
            ->join('matches m', 'm.id = tf.match_id AND m.branch_id = t.branch_id', 'left')
            ->where(['tf.id' => $fixtureId, 'tf.tournament_id' => $tournamentId])
            ->get()
            ->getRowArray();
        return is_array($row) ? $row : null;
    }

    private function localFixtureDateTime(string $date, string $time): DateTimeImmutable
    {
        try {
            $calendar = $this->calendarDate($date)->format('Y-m-d');
        } catch (RuntimeException) {
            throw new RuntimeException('Enter a valid fixture date.');
        }
        if (! preg_match('/^\d{2}:\d{2}$/', $time)) {
            throw new RuntimeException('Enter a valid fixture start time.');
        }
        $value = $calendar . ' ' . $time . ':00';
        $dateTime = DateTimeImmutable::createFromFormat(
            '!Y-m-d H:i:s',
            $value,
            new \DateTimeZone($this->timezone())
        );
        $errors = DateTimeImmutable::getLastErrors();
        if (
            $dateTime === false
            || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $dateTime->format('Y-m-d H:i:s') !== $value
        ) {
            throw new RuntimeException('Enter a valid fixture date and start time.');
        }
        return $dateTime;
    }

    private function local(?string $value): ?DateTimeImmutable
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        try {
            return (new DateTimeImmutable($value, new \DateTimeZone('UTC')))
                ->setTimezone(new \DateTimeZone($this->timezone()));
        } catch (Throwable) {
            return null;
        }
    }

    private function timezone(): string
    {
        $configured = trim((string) config('App')->appTimezone);

        return $configured !== '' ? $configured : self::MCC_TIMEZONE;
    }

    /** @param array<string, mixed> $existing @param array<string, mixed> $input */
    private function assertFixtureVersion(array $existing, array $input): void
    {
        $expectedVersion = (int) ($input['expected_version'] ?? 0);
        if ($expectedVersion < 1 || $expectedVersion !== (int) ($existing['version'] ?? 1)) {
            throw new RuntimeException('The Fixture correction is stale. Reload before correcting it.');
        }
    }

    private function participants(object $db, int $id, int $branch, array $input): void
    {
        $hasOpponentColumn = $db->fieldExists('opponent_id', 'tournament_participants');
        $teams = [];
        $opponents = [];
        $opponentInputProvided = array_key_exists('opponent_ids', $input);
        if ($opponentInputProvided) {
            $opponentIds = array_values(array_unique(array_filter(
                array_map('intval', (array) $input['opponent_ids']),
                static fn (int $opponentId): bool => $opponentId > 0
            )));
            if ($opponentIds !== []) {
                $opponents = $db->table('opponents')
                    ->select('id, name')
                    ->whereIn('id', $opponentIds)
                    ->where(['branch_id' => $branch, 'is_active' => 1])
                    ->orderBy('name')
                    ->get()
                    ->getResultArray();
                if (count($opponents) !== count($opponentIds)) {
                    throw new RuntimeException('Select only active opponents from this Tournament branch.');
                }
            }
        }
        $rawTeams = $input['teams'] ?? [];
        $teamLines = is_array($rawTeams) ? $rawTeams : preg_split('/\R/', (string) $rawTeams);

        foreach ($teamLines ?: [] as $teamLine) {
            foreach (preg_split('/\R/', (string) $teamLine) ?: [] as $team) {
                $name = trim($team);
                if ($name !== '') {
                    $teams[] = $name;
                }
            }
        }
        $teams = array_values(array_unique($teams));
        if ($opponentInputProvided && $opponents !== []) {
            $teams = [];
        }
        if ($opponentInputProvided && $opponents === [] && $teams === [] && $id > 0) {
            $teams = array_values(array_filter(array_map(
                'strval',
                array_column(
                    $db->table('tournament_participants')
                        ->select('team_name')
                        ->where(['tournament_id' => $id, 'participant_type' => 'team'])
                        ->where('team_name IS NOT NULL', null, false)
                        ->get()
                        ->getResultArray(),
                    'team_name'
                )
            )));
        }
        $playerInputProvided = array_key_exists('player_ids', $input);
        $players = $playerInputProvided
            ? array_values(array_unique(array_map('intval', (array) $input['player_ids'])))
            : [];

        if (! $playerInputProvided && $id > 0) {
            $players = array_values(array_unique(array_map(
                'intval',
                array_column(
                    $db->table('tournament_participants')
                        ->select('player_id')
                        ->where(['tournament_id' => $id, 'participant_type' => 'player'])
                        ->where('player_id IS NOT NULL', null, false)
                        ->get()
                        ->getResultArray(),
                    'player_id'
                )
            )));
        }
        foreach ($players as $player) {
            if ($db->table('players')->where([
                'id' => $player,
                'branch_id' => $branch,
                'status' => 'active',
            ])->countAllResults() === 0) {
                throw new RuntimeException('Player is not eligible for tournament.');
            }
        }
        $db->table('tournament_participants')->where('tournament_id', $id)->delete();
        foreach ($opponents as $opponent) {
            $participant = [
                'tournament_id' => $id,
                'participant_type' => 'team',
                'team_name' => $opponent['name'],
                'created_at' => gmdate('Y-m-d H:i:s'),
            ];
            if ($hasOpponentColumn) {
                $participant['opponent_id'] = (int) $opponent['id'];
            }
            $db->table('tournament_participants')->insert($participant);
        }
        foreach ($teams as $name) {
            $db->table('tournament_participants')->insert([
                'tournament_id' => $id,
                'participant_type' => 'team',
                'team_name' => $name,
                'created_at' => gmdate('Y-m-d H:i:s'),
            ]);
        }
        foreach ($players as $player) {
            $db->table('tournament_participants')->insert([
                'tournament_id' => $id,
                'participant_type' => 'player',
                'player_id' => $player,
                'created_at' => gmdate('Y-m-d H:i:s'),
            ]);
        }
    }

    private function validateExistingFixtures(object $db, int $tournamentId, int $branch, array $data): void
    {
        $fixtures = $db->table('tournament_fixtures tf')
            ->select('tf.scheduled_at, m.branch_id AS match_branch_id, g.branch_id AS ground_branch_id')
            ->join('matches m', 'm.id = tf.match_id', 'left')
            ->join('grounds g', 'g.id = tf.ground_id', 'left')
            ->where('tf.tournament_id', $tournamentId)
            ->get()
            ->getResultArray();
        foreach ($fixtures as $fixture) {
            if ($fixture['scheduled_at'] !== null) {
                $date = $this->local((string) $fixture['scheduled_at'])?->format('Y-m-d');
                if ($date !== null && ($date < $data['starts_on'] || $date > $data['ends_on'])) {
                    throw new RuntimeException('Tournament dates cannot exclude an existing fixture.');
                }
            }
            if ($fixture['match_branch_id'] !== null && (int) $fixture['match_branch_id'] !== $branch) {
                throw new RuntimeException('Existing fixture has invalid Match lineage.');
            }
            if ($fixture['ground_branch_id'] !== null && (int) $fixture['ground_branch_id'] !== $branch) {
                throw new RuntimeException('Existing fixture has invalid Ground lineage.');
            }
        }
    }

    private function validated(object $db, array $input, int $branch): array
    {
        $name = trim((string) ($input['name'] ?? ''));
        $type = trim((string) ($input['tournament_type'] ?? ''));
        $status = trim((string) ($input['status'] ?? 'planned'));
        $start = trim((string) ($input['starts_on'] ?? ''));
        $end = trim((string) ($input['ends_on'] ?? ''));
        if ($name === '' || strlen($name) > 150) {
            throw new RuntimeException('Invalid tournament name.');
        }
        if (! in_array($type, self::TYPES, true)) {
            throw new RuntimeException('Invalid tournament type.');
        }
        if (! in_array($status, self::STATUSES, true)) {
            throw new RuntimeException('Invalid tournament status.');
        }
        if ($start === '' || $end === '') {
            throw new RuntimeException('Tournament dates are required.');
        }
        try {
            $start = $this->calendarDate($start)->format('Y-m-d');
        } catch (RuntimeException) {
            throw new RuntimeException('Invalid tournament start date.');
        }
        try {
            $end = $this->calendarDate($end)->format('Y-m-d');
        } catch (RuntimeException) {
            throw new RuntimeException('Invalid tournament end date.');
        }
        if ($end < $start) {
            throw new RuntimeException('Invalid tournament dates.');
        }
        $groundId = (int) ($input['ground_id'] ?? 0);
        if ($groundId > 0 && $db->table('grounds')->where([
            'id' => $groundId,
            'branch_id' => $branch,
            'is_active' => 1,
        ])->countAllResults() === 0) {
            throw new RuntimeException('Invalid tournament ground.');
        }
        return [
            'branch_id' => $branch, 'name' => $name, 'tournament_type' => $type,
            'status' => $status, 'starts_on' => $start, 'ends_on' => $end,
            'ground_id' => $groundId ?: null,
            'description' => trim((string) ($input['description'] ?? '')) ?: null,
        ];
    }

    /**
     * Validate the approved user-directed Tournament lifecycle without
     * changing the existing CRUD storage shape.
     *
     * @param array<string, mixed>|null $existing
     * @param array<string, mixed> $input
     */
    private function validateStatusTransition(?array $existing, string $requestedStatus, array $input): ?string
    {
        $reason = trim((string) ($input['reason'] ?? $input['correction_reason'] ?? ''));

        if ($existing === null) {
            if ($requestedStatus !== 'planned') {
                throw new RuntimeException('New tournaments must start in the planned status.');
            }

            return null;
        }

        $currentStatus = (string) ($existing['status'] ?? '');
        if ($currentStatus === $requestedStatus) {
            return $reason !== '' ? $reason : null;
        }

        $transitions = [
            'planned' => ['active', 'cancelled'],
            'active' => ['completed', 'cancelled'],
            'completed' => [],
            'cancelled' => [],
        ];
        if (! in_array($requestedStatus, $transitions[$currentStatus] ?? [], true)) {
            throw new RuntimeException('This Tournament status transition is not allowed.');
        }

        if ($requestedStatus === 'cancelled' && $reason === '') {
            throw new RuntimeException('A reason is required when cancelling a Tournament.');
        }

        return $reason !== '' ? $reason : null;
    }

    private function pageNumber(mixed $value): int
    {
        if (! ctype_digit((string) $value) || (int) $value < 1) {
            throw new RuntimeException('Invalid tournament page.');
        }

        return (int) $value;
    }

    private function calendarDate(string $value): DateTimeImmutable
    {
        $value = trim($value);
        $iso = $value;

        if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $value, $parts)) {
            $iso = $parts[3] . '-' . $parts[2] . '-' . $parts[1];
        }

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $iso)) {
            throw new RuntimeException('Invalid tournament date.');
        }

        $date = DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $iso,
            new \DateTimeZone(self::MCC_TIMEZONE)
        );
        $errors = DateTimeImmutable::getLastErrors();

        if (
            $date === false
            || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $date->format('Y-m-d') !== $iso
        ) {
            throw new RuntimeException('Invalid tournament date.');
        }

        return $date;
    }

    private function auditHistory(object $db, int $tournamentId, int $branch): array
    {
        $rows = $db->table('audit_logs al')
            ->select('al.id, al.entity_type, al.entity_id, al.action, al.actor_user_id, al.before_summary, al.after_summary, al.reason, al.created_at, u.display_name AS actor_name')
            ->join('users u', 'u.id = al.actor_user_id', 'left')
            ->join('tournament_fixtures tf', "tf.id = CAST(al.entity_id AS UNSIGNED) AND al.entity_type = 'tournament_fixture'", 'left')
            ->where('al.branch_id', $branch)
            ->groupStart()
                ->where(['al.entity_type' => 'tournament', 'al.entity_id' => (string) $tournamentId])
                ->orGroupStart()
                    ->where('al.entity_type', 'tournament_fixture')
                    ->where('tf.tournament_id', $tournamentId)
                ->groupEnd()
            ->groupEnd()
            ->whereIn('al.action', [
                'tournament_created',
                'tournament_updated',
                'tournament_fixture_created',
                'tournament_fixture_updated',
                'tournament_fixture_corrected',
                'tournament_fixture_postponed',
                'tournament_fixture_cancelled',
                'tournament_fixture_completed',
            ])
            ->orderBy('al.id', 'ASC')
            ->get()
            ->getResultArray();
        foreach ($rows as &$row) {
            $row['before_metadata'] = $this->auditMetadata($row['before_summary'] ?? null);
            $row['after_metadata'] = $this->auditMetadata($row['after_summary'] ?? null);
            unset($row['before_summary'], $row['after_summary']);
        }
        unset($row);

        return $rows;
    }

    private function auditMetadata(?string $summary): array
    {
        if ($summary === null || $summary === '') {
            return [];
        }
        $metadata = json_decode($summary, true);

        return is_array($metadata) ? $metadata : [];
    }

    private function tournament(int $id): array
    {
        $row = (new TournamentModel())->find($id);
        if (!is_array($row)) {
            throw new RuntimeException('Not found.');
        }
        return $row;
    }

    private function allow(int $branch, string $permission): void
    {
        if (!service('authorization')->can($permission, (string) $branch)) {
            throw new RuntimeException('Forbidden');
        }
    }

    private function branches(): array
    {
        return $this->authorizedBranches('tournament.view');
    }

    /**
     * Resolve branch IDs through the central authorization service.
     *
     * The list page and form options use different permissions, so the
     * permission must be supplied by the caller instead of relying on a
     * session-provided branch list.
     *
     * @return list<int>
     */
    private function authorizedBranches(string $permission): array
    {
        $authorization = service('authorization');
        return array_values(array_filter(
            service('branchContext')->authorizedBranchIds(true),
            static fn (int $branch): bool => $authorization->can($permission, (string) $branch)
        ));
    }

    private function actor(): int
    {
        $user = session('user');
        if (!is_array($user) || !isset($user['id'])) {
            throw new RuntimeException('Authentication required');
        }
        return (int) $user['id'];
    }
}
