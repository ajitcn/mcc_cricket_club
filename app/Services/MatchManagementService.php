<?php

declare(strict_types=1);

namespace App\Services;

use App\Modules\Matches\Models\MatchModel;
use Config\Database;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;
use Throwable;

final class MatchManagementService
{
    private const MATCHES_PER_PAGE = 20;
    private const MCC_TIMEZONE = 'Asia/Kolkata';
    private const TYPES = ['friendly', 'practice', 'league', 'knockout'];
    private const STATUSES = ['scheduled', 'completed', 'cancelled'];
    private const RESULTS = ['won', 'lost', 'draw', 'no_result'];

    public function list(array $filters = []): array
    {
        $branches = $this->branches();
        if ($branches === []) {
            return ['matches' => [], 'filters' => $filters, 'page' => 1, 'pages' => 1, 'total' => 0];
        }
        $db = Database::connect('default');
        $hasFixtureMatchNumber = $db->fieldExists('match_number', 'tournament_fixtures');
        $fixtureMatchNumberSelect = $hasFixtureMatchNumber
            ? ', tf.match_number'
            : '';
        $builder = $db->table('matches m')
            ->select('m.*, COALESCE(o.name, m.opponent) AS opponent, b.name AS branch_name, g.name AS ground_name, u.display_name AS coach_name, t.name AS tournament_name, tf.fixture_number, tf.round AS fixture_round' . $fixtureMatchNumberSelect)
            ->join('branches b', 'b.id = m.branch_id')
            ->join('opponents o', 'o.id = m.opponent_id AND o.branch_id = m.branch_id', 'left')
            ->join('grounds g', 'g.id = m.ground_id AND g.branch_id = m.branch_id', 'left')
            ->join('users u', 'u.id = m.coach_user_id', 'left')
            ->join('tournament_fixtures tf', 'tf.match_id = m.id', 'left')
            ->join('tournaments t', 't.id = tf.tournament_id AND t.branch_id = m.branch_id', 'left')
            ->whereIn('m.branch_id', $branches);
        $query = trim((string) ($filters['q'] ?? ''));
        if ($query !== '') {
            $builder->groupStart()
                ->groupStart()->like('o.name', $query)->orLike('m.opponent', $query)->groupEnd()
                ->orLike('m.match_type', $query)
                ->orLike('b.name', $query)
                ->orLike('t.name', $query)
                ->groupEnd();
        }
        if (in_array($filters['status'] ?? '', self::STATUSES, true)) {
            $builder->where('m.status', $filters['status']);
        }
        if (in_array($filters['match_type'] ?? '', self::TYPES, true)) {
            $builder->where('m.match_type', $filters['match_type']);
        }
        if (in_array($filters['match_category'] ?? '', ['regular', 'tournament'], true) && $db->fieldExists('match_category', 'matches')) {
            $builder->where('m.match_category', $filters['match_category']);
        }
        if (ctype_digit((string) ($filters['tournament_id'] ?? ''))) {
            $builder->where('t.id', (int) $filters['tournament_id']);
        }
        if ($hasFixtureMatchNumber && ctype_digit((string) ($filters['match_number'] ?? ''))) {
            $matchNumber = (int) $filters['match_number'];
            $builder->groupStart()
                ->where('m.id', $matchNumber)
                ->orWhere('tf.match_number', $matchNumber)
                ->groupEnd();
        }
        if (($filters['date'] ?? '') !== '') {
            $date = $this->calendarDate((string) $filters['date']);
            $from = $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
            $to = $date->modify('+1 day')->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
            $builder->where('m.starts_at >=', $from)->where('m.starts_at <', $to);
        }
        $total = (clone $builder)->countAllResults();
        $pages = max(1, (int) ceil($total / self::MATCHES_PER_PAGE));
        $page = $this->pageNumber($filters['page'] ?? 1);
        $page = min($page, $pages);
        $matches = $builder
                ->orderBy('m.starts_at', 'DESC')
                ->orderBy('m.id', 'DESC')
                ->limit(self::MATCHES_PER_PAGE, ($page - 1) * self::MATCHES_PER_PAGE)
                ->get()
                ->getResultArray();
        $authorization = service('authorization');
        foreach ($matches as &$match) {
            $match['can_update'] = $authorization->can('match.update', (string) $match['branch_id']);
        }
        unset($match);
        return [
            'matches' => $matches,
            'filters' => $filters,
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
        ];
    }

    public function tournamentFilterOptions(): array
    {
        $branches = $this->branches();
        if ($branches === []) {
            return [];
        }

        return Database::connect('default')
            ->table('tournaments')
            ->select('id, name')
            ->whereIn('branch_id', $branches)
            ->orderBy('name', 'ASC')
            ->get()
            ->getResultArray();
    }

    public function formOptions(string $permission = 'match.create'): array
    {
        $branchIds = $this->authorizedBranches($permission);
        if ($branchIds === []) {
            return ['branches' => [], 'grounds' => [], 'coaches' => [], 'players' => [], 'opponents' => []];
        }
        $db = Database::connect('default');

        return [
            'branches' => $db->table('branches')
                ->whereIn('id', $branchIds)
                ->where('is_active', 1)
                ->orderBy('name')
                ->get()
                ->getResultArray(),
            'opponents' => service('opponent')->activeForBranches($branchIds),
            'grounds' => $db->table('grounds')->whereIn('branch_id', $branchIds)->where('is_active', 1)->orderBy('name')->get()->getResultArray(),
            'coaches' => $db->table('user_branches ub')
                ->select('u.id, u.display_name, ub.branch_id')
                ->join('users u', 'u.id = ub.user_id')
                ->join('user_roles ur', 'ur.user_id = ub.user_id')
                ->join('roles r', 'r.id = ur.role_id')
                ->whereIn('ub.branch_id', $branchIds)
                ->whereIn('r.name', ['Coach', 'Fitness Coach'])
                ->distinct()
                ->orderBy('u.display_name')
                ->get()
                ->getResultArray(),
            'players' => $db->table('players p')
                ->select('p.id, pe.full_name, p.branch_id')
                ->join('persons pe', 'pe.id = p.person_id')
                ->whereIn('p.branch_id', $branchIds)
                ->orderBy('pe.full_name')
                ->get()
                ->getResultArray(),
        ];
    }

    public function find(int $id): array
    {
        $match = $this->match($id);
        $this->allow((int) $match['branch_id'], 'match.view');
        $matchBranch = (int) $match['branch_id'];
        $db = Database::connect('default');
        $match = $this->detailProjection($db, $match, $id, $matchBranch);
        $match['can_update'] = service('authorization')->can('match.update', (string) $matchBranch);
        $match['starts_at_local'] = $this->local($match['starts_at'] ?? null);
        $match['ends_at_local'] = $this->local($match['ends_at'] ?? null);
        $match['audit_history'] = $this->auditHistory($db, $id, $matchBranch);
        return $match;
    }

    private function detailProjection(object $db, array $match, int $matchId, int $branch): array
    {
        $match['branch_name'] = $db->table('branches')
            ->where('id', $branch)
            ->get()
            ->getRow('name');
        $match['opponent_name'] = $this->branchName($db, 'opponents', $match['opponent_id'] ?? null, $branch);
        $match['ground_name'] = $this->branchName($db, 'grounds', $match['ground_id'] ?? null, $branch);
        $match['coach_name'] = $this->coachName($db, $match['coach_user_id'] ?? null, $branch);
        $match['program_name'] = $this->programName($db, $match['program_id'] ?? null, $match['batch_id'] ?? null, $branch);
        $match['batch_name'] = $this->branchName($db, 'batches', $match['batch_id'] ?? null, $branch);
        $match['players'] = $this->detailPlayers($db, $matchId, $branch);
        $match['performance_players'] = $this->performancePlayers($db, $matchId, $branch);
        $match['tournament_name'] = null;
        $match['match_number'] = null;
        $match['fixture_details'] = null;

        $fixtureMatchNumberSelect = $db->fieldExists('match_number', 'tournament_fixtures')
            ? ', tf.match_number'
            : '';
        $fixture = $db->table('tournament_fixtures tf')
            ->select('tf.fixture_number, tf.home_team, tf.away_team, tf.status, t.name AS tournament_name' . $fixtureMatchNumberSelect)
            ->join('tournaments t', 't.id = tf.tournament_id AND t.branch_id = ' . $branch)
            ->where('tf.match_id', $matchId)
            ->orderBy('tf.id', 'DESC')
            ->get()
            ->getRowArray();
        if (is_array($fixture)) {
            $match['tournament_name'] = $fixture['tournament_name'] ?? null;
            $match['match_number'] = $fixture['match_number'] ?? null;
            $match['fixture_details'] = sprintf(
                'Fixture %d: %s vs %s (%s)',
                (int) ($fixture['fixture_number'] ?? 0),
                (string) ($fixture['home_team'] ?? 'Unavailable'),
                (string) ($fixture['away_team'] ?? 'Unavailable'),
                ucfirst((string) ($fixture['status'] ?? 'Unavailable')),
            );
        }

        return $match;
    }

    private function detailPlayers(object $db, int $matchId, int $branch): array
    {
        return $db->table('match_players mp')
            ->select('mp.player_id, mp.selection_type, pe.full_name')
            ->join('players p', 'p.id = mp.player_id AND p.branch_id = ' . $branch, 'left')
            ->join('persons pe', 'pe.id = p.person_id', 'left')
            ->where('mp.match_id', $matchId)
            ->orderBy('mp.selection_type')
            ->orderBy('pe.full_name')
            ->get()
            ->getResultArray();
    }

    private function performancePlayers(object $db, int $matchId, int $branch): array
    {
        return $db->table('match_player_performances mpp')
            ->select('mpp.innings_number, mpp.team_side, mpp.player_id, pe.full_name')
            ->join('players p', 'p.id = mpp.player_id AND p.branch_id = ' . $branch, 'left')
            ->join('persons pe', 'pe.id = p.person_id', 'left')
            ->where(['mpp.match_id' => $matchId, 'mpp.branch_id' => $branch])
            ->orderBy('mpp.innings_number')
            ->orderBy('pe.full_name')
            ->get()
            ->getResultArray();
    }

    private function branchName(object $db, string $table, mixed $id, int $branch): ?string
    {
        $value = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($value === false) {
            return null;
        }

        $row = $db->table($table)
            ->where(['id' => $value, 'branch_id' => $branch])
            ->get()
            ->getRowArray();

        return is_array($row) ? (string) ($row['name'] ?? '') : null;
    }

    private function coachName(object $db, mixed $id, int $branch): ?string
    {
        $value = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($value === false) {
            return null;
        }

        $row = $db->table('users u')
            ->select('u.display_name')
            ->join('user_branches ub', 'ub.user_id = u.id AND ub.branch_id = ' . $branch)
            ->where('u.id', $value)
            ->get()
            ->getRowArray();

        return is_array($row) ? (string) ($row['display_name'] ?? '') : null;
    }

    private function programName(object $db, mixed $programId, mixed $batchId, int $branch): ?string
    {
        $program = filter_var($programId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $batch = filter_var($batchId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($program === false || $batch === false) {
            return null;
        }

        $row = $db->table('batches ba')
            ->select('p.name')
            ->join('programs p', 'p.id = ba.program_id')
            ->where([
                'ba.id' => $batch,
                'ba.branch_id' => $branch,
                'ba.program_id' => $program,
            ])
            ->get()
            ->getRowArray();

        return is_array($row) ? (string) ($row['name'] ?? '') : null;
    }

    public function save(array $input, ?int $id = null): int
    {
        $branch = (int) ($input['branch_id'] ?? 0);
        if ($branch < 1) {
            throw new RuntimeException('Invalid branch.');
        }
        $existing = null;
        if ($id !== null) {
            $existing = $this->match($id);
            $this->allow((int) $existing['branch_id'], 'match.update');
            if ($branch !== (int) $existing['branch_id']) {
                throw new RuntimeException('Invalid match.');
            }
        } else {
            $this->allow($branch, 'match.create');
        }
        $db = Database::connect('default');
        $db->transStart();
        $lockedResources = [];
        try {
            $groundId = (int) ($input['ground_id'] ?? 0);
            $coachId = (int) ($input['coach_user_id'] ?? 0);
            $lockedResources = $this->lockResources($db, $coachId, $groundId);
            $data = $this->validatedMatch($db, $input, $branch, $id);

            if ($id !== null) {
                $this->validateCompletedCorrection($existing, $input, $data);
                $update = $db->table('matches')
                    ->where('id', $id);
                if ((string) ($existing['status'] ?? '') === 'completed') {
                    $update->where('version', (int) ($input['expected_version'] ?? 0));
                }
                $update->set($data + ['updated_at' => gmdate('Y-m-d H:i:s')]);
                $update->set('version', 'version + 1', false);
                $update->update();
                if ((string) ($existing['status'] ?? '') === 'completed' && $db->affectedRows() < 1) {
                    throw new RuntimeException('The Match was changed by another request. Reload before correcting it.');
                }
                $matchId = $id;
            } else {
                $db->table('matches')->insert($data + [
                    'created_by' => $this->actor(),
                    'created_at' => gmdate('Y-m-d H:i:s'),
                    'updated_at' => gmdate('Y-m-d H:i:s'),
                ]);
                $matchId = (int) $db->insertID();
            }
            if (array_key_exists('player_ids', $input) || array_key_exists('playing_xi_ids', $input)) {
                $this->replaceSelection($db, $matchId, $branch, $input);
            }
            service('auditPersistence')->record(
                $this->actor(),
                $id === null ? 'match_created' : 'match_updated',
                'match',
                (string) $matchId,
                $branch,
                $existing,
                $data,
                trim((string) ($input['correction_reason'] ?? '')) ?: null,
                $db
            );
            $db->transComplete();
            if (!$db->transStatus()) {
                throw new RuntimeException('Unable to save match.');
            }
            $this->releaseResources($db, $lockedResources);
            return $matchId;
        } catch (Throwable $exception) {
            $db->transRollback();
            $db->resetTransStatus();
            $this->releaseResources($db, $lockedResources);
            throw $exception;
        }
    }

    public function complete(int $id): int
    {
        $existing = $this->match($id);
        $branch = (int) $existing['branch_id'];
        $this->allow($branch, 'match.update');

        if ((string) $existing['status'] === 'completed') {
            return $id;
        }
        if ((string) $existing['status'] !== 'scheduled') {
            throw new RuntimeException('Match cannot be completed from current status.');
        }

        $db = Database::connect('default');
        $db->transStart();
        try {
            $db->table('matches')
                ->where(['id' => $id, 'status' => 'scheduled'])
                ->set([
                    'status' => 'completed',
                    'updated_at' => gmdate('Y-m-d H:i:s'),
                ])
                ->set('version', 'version + 1', false)
                ->update();
            if ($db->affectedRows() < 1) {
                $current = $this->match($id);
                if ((string) $current['status'] !== 'completed') {
                    throw new RuntimeException('Match cannot be completed from current status.');
                }
                $db->transComplete();
                return $id;
            }

            service('auditPersistence')->record(
                $this->actor(),
                'match_completed',
                'match',
                (string) $id,
                $branch,
                $existing,
                ['id' => $id, 'status' => 'completed'],
                null,
                $db
            );
            $db->transComplete();
            if (! $db->transStatus()) {
                throw new RuntimeException('Unable to complete match.');
            }

            return $id;
        } catch (Throwable $exception) {
            $db->transRollback();
            $db->resetTransStatus();
            throw $exception;
        }
    }

    private function validatedMatch(object $db, array $input, int $branch, ?int $exceptMatch = null): array
    {
        $opponentId = (int) ($input['opponent_id'] ?? 0);
        $yourTeamOpponentId = (int) ($input['your_team_opponent_id'] ?? 0);
        $type = trim((string) ($input['match_type'] ?? ''));
        $category = trim((string) ($input['match_category'] ?? 'regular'));
        $status = trim((string) ($input['status'] ?? 'scheduled'));
        $opponentRow = $db->table('opponents')->where(['id' => $opponentId, 'branch_id' => $branch, 'is_active' => 1])->get()->getRowArray();
        if (! is_array($opponentRow)) {
            throw new RuntimeException('Invalid opponent.');
        }
        if ($yourTeamOpponentId <= 0) {
            throw new RuntimeException('Your team is required.');
        }
        $yourTeamRow = null;
        if ($yourTeamOpponentId > 0) {
            $yourTeamRow = $db->table('opponents')
                ->where(['id' => $yourTeamOpponentId, 'branch_id' => $branch, 'is_active' => 1])
                ->get()
                ->getRowArray();
            if (! is_array($yourTeamRow)) {
                throw new RuntimeException('Invalid your team.');
            }
        }
        if (!in_array($type, self::TYPES, true)) {
            throw new RuntimeException('Invalid match type.');
        }
        if (! in_array($category, ['regular', 'tournament'], true)) {
            throw new RuntimeException('Invalid match category.');
        }
        if (! $db->fieldExists('match_category', 'matches') && $category !== 'regular') {
            throw new RuntimeException('Match category migration is pending.');
        }
        if (!in_array($status, self::STATUSES, true)) {
            throw new RuntimeException('Invalid match status.');
        }
        $starts = $this->localInputToUtc($input, 'starts', 'start');
        $ends = $this->localInputToUtc($input, 'ends', 'end');
        if ($ends <= $starts) {
            throw new RuntimeException('Invalid match window.');
        }
        $groundId = (int) ($input['ground_id'] ?? 0);
        if ($groundId > 0) {
            $ground = $db->table('grounds')
                ->where(['id' => $groundId, 'branch_id' => $branch, 'is_active' => 1])
                ->get()
                ->getRowArray();
            if (!is_array($ground)) {
                throw new RuntimeException('Ground is not available.');
            }
            $groundUnavailable = $db->table('ground_unavailability')
                ->where(['ground_id' => $groundId, 'branch_id' => $branch])
                ->where('starts_at <', $ends)
                ->where('ends_at >', $starts)
                ->countAllResults();
            if ($groundUnavailable > 0) {
                throw new RuntimeException('Ground is unavailable.');
            }
            $sessionConflict = $db->table('sessions')
                ->where([
                    'ground_id' => $groundId,
                    'branch_id' => $branch,
                    'status' => 'scheduled',
                ])
                ->where('starts_at <', $ends)
                ->where('ends_at >', $starts)
                ->countAllResults();
            if ($sessionConflict > 0) {
                throw new RuntimeException('Ground scheduling conflict.');
            }
            $groundMatches = $db->table('matches')
                ->where([
                    'ground_id' => $groundId,
                    'branch_id' => $branch,
                    'status' => 'scheduled',
                ])
                ->where('starts_at <', $ends)
                ->where('ends_at >', $starts);
            if ($exceptMatch !== null) {
                $groundMatches->where('id !=', $exceptMatch);
            }
            $matchConflict = $groundMatches->countAllResults();
            if ($matchConflict > 0) {
                throw new RuntimeException('Ground match conflict.');
            }
        }
        $coach = (int) ($input['coach_user_id'] ?? 0);
        $coachIsEligible = $db
            ->table('user_branches ub')
            ->join('user_roles ur', 'ur.user_id = ub.user_id')
            ->join('roles r', 'r.id = ur.role_id')
            ->where([
                'ub.user_id' => $coach,
                'ub.branch_id' => $branch,
            ])
            ->whereIn('r.name', ['Coach', 'Fitness Coach'])
            ->countAllResults() > 0;
        if ($coach > 0 && !$coachIsEligible) {
            throw new RuntimeException('Invalid coach.');
        }
        if ($coach > 0) {
            service('scheduling')->validateCoachAvailabilityAndConflicts(
                $db,
                $coach,
                $branch,
                $starts,
                $ends
            );
            $coachMatches = $db->table('matches')
                ->where([
                    'coach_user_id' => $coach,
                    'branch_id' => $branch,
                    'status' => 'scheduled',
                ])
                ->where('starts_at <', $ends)
                ->where('ends_at >', $starts);
            if ($exceptMatch !== null) {
                $coachMatches->where('id !=', $exceptMatch);
            }
            if ($coachMatches->countAllResults() > 0) {
                throw new RuntimeException('Coach scheduling conflict.');
            }
        }
        $result = trim((string) ($input['result'] ?? ''));
        if ($result !== '' && !in_array($result, self::RESULTS, true)) {
            throw new RuntimeException('Invalid match result.');
        }
        $data = [
            'branch_id' => $branch,
            'ground_id' => $groundId > 0 ? $groundId : null,
            'coach_user_id' => $coach > 0 ? $coach : null,
            'opponent_id' => $opponentId,
            'opponent' => (string) $opponentRow['name'],
            'match_type' => $type,
            'status' => $status,
            'starts_at' => $starts,
            'ends_at' => $ends,
            'result' => $result === '' ? null : $result,
            'score_summary' => $this->text($input['score_summary'] ?? null, 255),
            'coach_remarks' => $this->text($input['coach_remarks'] ?? null, 10000),
        ];
        if ($db->fieldExists('match_category', 'matches')) {
            $data['match_category'] = $category;
        }
        if (! $db->fieldExists('your_team_opponent_id', 'matches')) {
            throw new RuntimeException('Your team migration is pending.');
        }
        $data['your_team'] = (string) $yourTeamRow['name'];
        $data['your_team_opponent_id'] = $yourTeamOpponentId;
        if (! $db->fieldExists('your_team', 'matches')) {
            unset($data['your_team']);
        }

        return $data;
    }

    /** @param array<string, mixed> $existing @param array<string, mixed> $input @param array<string, mixed> $data */
    private function validateCompletedCorrection(array $existing, array $input, array $data): void
    {
        if ((string) ($existing['status'] ?? '') !== 'completed') {
            return;
        }

        if ((string) ($data['status'] ?? '') !== 'completed') {
            throw new RuntimeException('Completed Matches cannot be reopened through ordinary editing.');
        }

        $reason = trim((string) ($input['correction_reason'] ?? ''));
        if ($reason === '') {
            throw new RuntimeException('Completed Match corrections require a reason.');
        }

        $expectedVersion = (int) ($input['expected_version'] ?? 0);
        if ($expectedVersion < 1 || $expectedVersion !== (int) ($existing['version'] ?? 1)) {
            throw new RuntimeException('The Match correction is stale. Reload before correcting it.');
        }
    }

    private function replaceSelection(object $db, int $matchId, int $branch, array $input): void
    {
        $squad = $this->ids($input['player_ids'] ?? []);
        $xi = $this->ids($input['playing_xi_ids'] ?? []);
        if (array_diff($xi, $squad) !== []) {
            throw new RuntimeException('Playing XI must be selected from squad.');
        }
        if (count($xi) > 11) {
            throw new RuntimeException('Playing XI cannot exceed 11 players.');
        }
        $db->table('match_players')->where('match_id', $matchId)->delete();
        foreach ($squad as $playerId) {
            $valid = $db->table('players')->where(['id' => $playerId, 'branch_id' => $branch])->countAllResults();
            if ($valid === 0) {
                throw new RuntimeException('Player is not eligible for match.');
            }
            $db->table('match_players')->ignore(true)->insert([
                'match_id' => $matchId,
                'player_id' => $playerId,
                'selection_type' => in_array($playerId, $xi, true)
                    ? 'playing_xi'
                    : 'squad',
                'created_at' => gmdate('Y-m-d H:i:s'),
            ]);
        }
    }

    private function match(int $id): array
    {
        $row = (new MatchModel())->find($id);
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

    private function actor(): int
    {
        $user = session('user');
        if (!is_array($user) || !isset($user['id'])) {
            throw new RuntimeException('Authentication required');
        }
        return (int) $user['id'];
    }

    private function branches(): array
    {
        return $this->authorizedBranches('match.view');
    }

    private function authorizedBranches(string $permission): array
    {
        $authorization = service('authorization');
        return array_values(array_filter(
            service('branchContext')->authorizedBranchIds(true),
            static fn (int $branch): bool => $authorization->can($permission, (string) $branch)
        ));
    }

    private function pageNumber(mixed $value): int
    {
        if (!ctype_digit((string) $value) || (int) $value < 1) {
            throw new RuntimeException('Invalid match page.');
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
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $iso)) {
            throw new RuntimeException('Invalid match date.');
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $iso, new DateTimeZone(self::MCC_TIMEZONE));
        $errors = DateTimeImmutable::getLastErrors();
        if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) || $date->format('Y-m-d') !== $iso) {
            throw new RuntimeException('Invalid match date.');
        }

        return $date;
    }

    private function auditHistory(object $db, int $matchId, int $branch): array
    {
        $rows = $db->table('audit_logs al')
            ->select('al.id, al.action, al.actor_user_id, al.before_summary, al.after_summary, al.reason, al.created_at, u.display_name AS actor_name')
            ->join('users u', 'u.id = al.actor_user_id', 'left')
            ->where([
                'al.entity_type' => 'match',
                'al.entity_id' => (string) $matchId,
                'al.branch_id' => $branch,
            ])
            ->whereIn('al.action', ['match_created', 'match_updated'])
            ->orderBy('al.id', 'ASC')
            ->get()
            ->getResultArray();
        foreach ($rows as &$row) {
            $row['before_metadata'] = $this->displayAuditMetadata(
                $this->auditMetadata($row['before_summary'] ?? null),
                $db,
                $branch,
            );
            $row['after_metadata'] = $this->displayAuditMetadata(
                $this->auditMetadata($row['after_summary'] ?? null),
                $db,
                $branch,
            );
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

    private function displayAuditMetadata(array $metadata, object $db, int $branch): array
    {
        $display = [];
        foreach ($metadata as $key => $value) {
            $normalized = strtolower((string) $key);
            $label = match ($normalized) {
                'branch_id' => 'Branch',
                'opponent_id' => 'Opponent',
                'coach_user_id' => 'Coach',
                'ground_id' => 'Ground',
                'program_id' => 'Program',
                'batch_id' => 'Batch',
                'player_id' => 'Player',
                'player_ids' => 'Players',
                'playing_xi_ids' => 'Playing XI Players',
                default => $this->auditLabel($normalized),
            };
            $display[$label] = $this->displayAuditValue($normalized, $value, $db, $branch);
        }

        return $display;
    }

    private function displayAuditValue(string $key, mixed $value, object $db, int $branch): mixed
    {
        if (in_array($key, ['branch_id', 'opponent_id', 'ground_id', 'batch_id'], true)) {
            $table = [
                'branch_id' => 'branches',
                'opponent_id' => 'opponents',
                'ground_id' => 'grounds',
                'batch_id' => 'batches',
            ][$key];
            if ($key === 'branch_id') {
                return (string) ($db->table('branches')->where('id', (int) $value)->get()->getRow('name') ?? 'Unavailable');
            }

            return $this->branchName($db, $table, $value, $branch) ?? 'Unavailable';
        }
        if ($key === 'coach_user_id') {
            return $this->coachName($db, $value, $branch) ?? 'Unavailable';
        }
        if ($key === 'program_id') {
            return 'Unavailable';
        }
        if ($key === 'player_id') {
            return $this->playerName($db, $value, $branch) ?? 'Unavailable';
        }
        if ($key === 'id') {
            return 'Unavailable';
        }
        if (in_array($key, ['player_ids', 'playing_xi_ids'], true) && is_array($value)) {
            return array_values(array_map(
                fn (mixed $playerId): string => $this->playerName($db, $playerId, $branch) ?? 'Unavailable',
                $value,
            ));
        }
        if (is_array($value)) {
            return $this->displayAuditMetadata($value, $db, $branch);
        }
        if (str_ends_with($key, '_id')) {
            return 'Unavailable';
        }

        return $value;
    }

    private function playerName(object $db, mixed $id, int $branch): ?string
    {
        $value = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($value === false) {
            return null;
        }

        $row = $db->table('players p')
            ->select('pe.full_name')
            ->join('persons pe', 'pe.id = p.person_id')
            ->where(['p.id' => $value, 'p.branch_id' => $branch])
            ->get()
            ->getRowArray();

        return is_array($row) ? (string) ($row['full_name'] ?? '') : null;
    }

    private function auditLabel(string $key): string
    {
        if ($key === 'id') {
            return 'Reference';
        }

        $label = ucwords(str_replace(['_', '-'], ' ', $key));

        return preg_replace('/\s+Id$/i', '', $label) ?: $label;
    }

    private function lockResources(object $db, int $coach, int $ground): array
    {
        $resourceNames = [];
        if ($coach > 0) {
            $resourceNames[] = 'mcc:scheduling:coach:' . $coach;
        }
        if ($ground > 0) {
            $resourceNames[] = 'mcc:scheduling:ground:' . $ground;
        }
        sort($resourceNames, SORT_STRING);

        $locked = [];
        try {
            foreach ($resourceNames as $resourceName) {
                $row = $db->query(
                    'SELECT GET_LOCK(?, 5) AS acquired',
                    [$resourceName]
                )->getRowArray();
                if (!is_array($row) || (int) ($row['acquired'] ?? 0) !== 1) {
                    throw new RuntimeException('Scheduling conflict.');
                }
                $locked[] = $resourceName;
            }
        } catch (Throwable $exception) {
            $this->releaseResources($db, $locked);
            throw $exception;
        }

        return $locked;
    }

    private function releaseResources(object $db, array $resourceNames): void
    {
        foreach ($resourceNames as $resourceName) {
            $db->query('SELECT RELEASE_LOCK(?)', [$resourceName]);
        }
    }

    private function text(mixed $value, int $max): ?string
    {
        $text = trim((string) ($value ?? ''));
        if (strlen($text) > $max) {
            throw new RuntimeException('Invalid match.');
        }
        return $text === '' ? null : $text;
    }

    private function ids(mixed $value): array
    {
        if (is_string($value)) {
            $value = preg_split('/[\s,]+/', trim($value), -1, PREG_SPLIT_NO_EMPTY);
        }
        if (!is_array($value)) {
            throw new RuntimeException('Invalid match squad.');
        }
        $ids = array_map('intval', $value);
        if (count($ids) !== count(array_unique($ids)) || in_array(0, $ids, true)) {
            throw new RuntimeException('Invalid match squad.');
        }
        return $ids;
    }

    private function utc(string $value, string $label = 'datetime'): string
    {
        if (!preg_match('/(?:Z|[+-]\d{2}:\d{2})$/', $value)) {
            throw new RuntimeException('Invalid match ' . $label . ' date/time.');
        }
        $datePart = substr($value, 0, 10);
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $datePart, $parts)) {
            $year = (int) $parts[1];
            $month = (int) $parts[2];
            $day = (int) $parts[3];
        } elseif (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $datePart, $parts)) {
            $year = (int) $parts[3];
            $month = (int) $parts[2];
            $day = (int) $parts[1];
        } else {
            throw new RuntimeException('Invalid match ' . $label . ' date/time.');
        }
        if (!checkdate($month, $day, $year)) {
            throw new RuntimeException('Invalid match ' . $label . ' date/time.');
        }
        try {
            return (new DateTimeImmutable($value))
                ->setTimezone(new DateTimeZone('UTC'))
                ->format('Y-m-d H:i:s');
        } catch (Throwable) {
            throw new RuntimeException('Invalid match ' . $label . ' date/time.');
        }
    }

    private function localInputToUtc(array $input, string $prefix, string $label): string
    {
        $date = trim((string) ($input[$prefix . '_date'] ?? ''));
        $time = trim((string) ($input[$prefix . '_time'] ?? ''));
        if ($date !== '' || $time !== '') {
            $dateFormat = null;
            if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $date, $parts)) {
                $dateFormat = '!d-m-Y H:i';
                $day = (int) $parts[1];
                $month = (int) $parts[2];
                $year = (int) $parts[3];
            } elseif (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $parts)) {
                $dateFormat = '!Y-m-d H:i';
                $year = (int) $parts[1];
                $month = (int) $parts[2];
                $day = (int) $parts[3];
            }
            if ($dateFormat === null || ! preg_match('/^(\d{2}):(\d{2})$/', $time, $clock)) {
                throw new RuntimeException('Invalid match ' . $label . ' date/time.');
            }
            if (! checkdate($month, $day, $year) || (int) $clock[1] > 23 || (int) $clock[2] > 59) {
                throw new RuntimeException('Invalid match ' . $label . ' date/time.');
            }
            $local = DateTimeImmutable::createFromFormat(
                $dateFormat,
                $date . ' ' . $time,
                new DateTimeZone((string) (config('App')->appTimezone ?: self::MCC_TIMEZONE))
            );
            if ($local === false) {
                throw new RuntimeException('Invalid match ' . $label . ' date/time.');
            }

            return $local->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        }

        return $this->utc((string) ($input[$prefix . '_at'] ?? ''), $label);
    }

    private function local(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        try {
            return (new DateTimeImmutable($value, new DateTimeZone('UTC')))
                ->setTimezone(new DateTimeZone((string) (config('App')->appTimezone ?: self::MCC_TIMEZONE)))
                ->format('d-m-Y H:i');
        } catch (Throwable) {
            return $value;
        }
    }
}
