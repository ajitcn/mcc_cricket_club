<?php

declare(strict_types=1);

namespace App\Services;

use Config\Database;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;
use Throwable;

final class MatchPlayerPerformanceService
{
    private const BATTING_STATUSES = ['batted', 'did_not_bat', 'retired_hurt', 'absent'];
    private const BOWLING_STATUSES = ['bowled', 'did_not_bowl', 'not_available'];
    private const FIELDING_STATUSES = ['fielded', 'did_not_field', 'not_available'];

    public function forMatch(int $matchId): array
    {
        $match = $this->match($matchId);
        $this->allow((int) $match['branch_id'], 'match.view');
        $db = Database::connect('default');
        $match['branch_name'] = $db->table('branches')->where('id', (int) $match['branch_id'])->get()->getRow('name');
        $match['ground_name'] = $match['ground_id'] === null
            ? null
            : $db->table('grounds')
                ->where(['id' => (int) $match['ground_id'], 'branch_id' => (int) $match['branch_id']])
                ->get()
                ->getRow('name');
        $match['starts_at_local'] = $this->local($match['starts_at'] ?? null);
        $match['ends_at_local'] = $this->local($match['ends_at'] ?? null);
        $players = $db->table('match_players mp')
            ->select('mp.player_id, mp.selection_type, pe.full_name')
            ->join('players p', 'p.id = mp.player_id AND p.branch_id = ' . (int) $match['branch_id'])
            ->join('persons pe', 'pe.id = p.person_id')
            ->where('mp.match_id', $matchId)
            ->whereIn('mp.selection_type', ['squad', 'playing_xi'])
            ->orderBy('mp.selection_type')
            ->orderBy('pe.full_name')
            ->get()
            ->getResultArray();
        $rows = $db->table('match_player_performances')
            ->where('match_id', $matchId)
            ->orderBy('innings_number')
            ->orderBy('player_id')
            ->get()
            ->getResultArray();
        $byPlayer = [];
        foreach ($rows as $row) {
            $byPlayer[(int) $row['player_id'] . ':' . (int) $row['innings_number'] . ':' . (string) $row['team_side']] = $row;
        }
        foreach ($players as &$player) {
            $key = (int) $player['player_id'] . ':1:match';
            $player['performance'] = $byPlayer[$key] ?? null;
            $player['metrics'] = $player['performance'] === null
                ? []
                : $this->metrics($player['performance']);
        }
        unset($player);
        $auditHistory = [];
        if ($rows !== []) {
            $auditHistory = $db->table('audit_logs al')
                ->select('al.id, al.action, al.actor_user_id, al.before_summary, al.after_summary, al.reason, al.created_at, u.display_name AS actor_name')
                ->join('users u', 'u.id = al.actor_user_id', 'left')
                ->where('al.entity_type', 'match_player_performance')
                ->where('al.branch_id', (int) $match['branch_id'])
                ->whereIn('al.entity_id', array_map(static fn(array $row): string => (string) $row['id'], $rows))
                ->orderBy('al.id', 'ASC')
                ->get()
                ->getResultArray();
        }
        $readOnly = (string) $match['status'] === 'completed';
        $editPermission = $readOnly ? 'performance.update' : 'match.update';
        return [
            'match' => $match,
            'players' => $players,
            'rows' => $rows,
            'audit_history' => $auditHistory,
            'read_only' => $readOnly,
            'can_edit' => service('authorization')->can($editPermission, (string) $match['branch_id']),
        ];
    }

    public function save(int $matchId, array $input): void
    {
        $this->savePlayers($matchId, $input, true);
    }

    public function savePlayer(int $matchId, int $playerId, array $payload): void
    {
        $this->savePlayers($matchId, ['players' => [$playerId => $payload]], false);
    }

    public function importRows(int $matchId, array $rows): void
    {
        if ($rows === []) {
            throw new RuntimeException('The CSV file does not contain any performance rows.');
        }

        $players = [];
        foreach ($rows as $row) {
            $playerId = (int) ($row['player_id'] ?? 0);
            if ($playerId < 1) {
                throw new RuntimeException('Every CSV row must include a valid player_id.');
            }
            unset($row['player_id'], $row['player_name']);
            $players[$playerId] = $row;
        }

        $this->savePlayers($matchId, ['players' => $players], false);
    }

    public function templateRows(int $matchId): array
    {
        $result = $this->forMatch($matchId);
        $rows = [];
        foreach ($result['players'] as $player) {
            $rows[] = [
                'player_id' => (int) $player['player_id'],
                'player_name' => (string) $player['full_name'],
                'batting_status' => 'batted',
                'runs' => '',
                'balls_faced' => '',
                'fours' => '',
                'sixes' => '',
                'batting_position' => '',
                'dismissal_type' => '',
                'bowling_status' => 'bowled',
                'legal_balls' => '',
                'maidens' => '',
                'runs_conceded' => '',
                'wickets' => '',
                'wides' => '',
                'no_balls' => '',
                'byes' => '',
                'leg_byes' => '',
                'fielding_status' => 'fielded',
                'catches' => '',
                'stumpings' => '',
                'run_outs_completed' => '',
                'run_out_assists' => '',
                'direct_hit_run_outs' => '',
                'dropped_catches' => '',
                'fielding_position' => '',
                'fielding_notes' => '',
                'correction_reason' => '',
                'version' => $player['performance']['version'] ?? '',
            ];
        }

        return $rows;
    }

    private function savePlayers(int $matchId, array $input, bool $requireAllPlayers): void
    {
        $match = $this->match($matchId);
        $branch = (int) $match['branch_id'];
        $completed = (string) $match['status'] === 'completed';
        $permission = $completed ? 'performance.update' : 'match.update';
        $this->allow($branch, $permission);
        if (in_array((string) $match['status'], ['cancelled'], true)) {
            throw new RuntimeException('Match performance cannot be recorded for a cancelled Match.');
        }
        $members = $this->members($matchId, $branch);
        $submitted = $input['players'] ?? [];
        if (!is_array($submitted)) {
            throw new RuntimeException('Invalid Match performance submission.');
        }
        if ($requireAllPlayers) {
            foreach (array_keys($members) as $memberId) {
                if (!array_key_exists($memberId, $submitted)) {
                    throw new RuntimeException('Every selected Match Player requires a performance entry.');
                }
            }
        }
        $positions = [];
        foreach ($submitted as $payload) {
            if (!is_array($payload) || ($payload['batting_position'] ?? '') === '') {
                continue;
            }
            $position = (int) $payload['batting_position'];
            if (isset($positions[$position])) {
                throw new RuntimeException('Duplicate batting positions are not allowed.');
            }
            $positions[$position] = true;
        }
        $db = Database::connect('default');
        $db->transStart();
        try {
            foreach ($submitted as $playerId => $payload) {
                $playerId = (int) $playerId;
                if (!isset($members[$playerId]) || !is_array($payload)) {
                    throw new RuntimeException('Invalid Match performance Player.');
                }
                $before = $db->table('match_player_performances')->where([
                    'match_id' => $matchId,
                    'player_id' => $playerId,
                    'innings_number' => 1,
                    'team_side' => 'match',
                ])->get()->getRowArray();
                $data = $this->validated($payload, $matchId, $playerId, $branch, $members, $before);
                $now = gmdate('Y-m-d H:i:s');
                $data += ['match_id' => $matchId, 'player_id' => $playerId, 'branch_id' => $branch, 'innings_number' => 1, 'team_side' => 'match'];
                if (is_array($before)) {
                    $changed = false;
                    foreach (array_keys($data) as $field) {
                        if ($field === 'correction_reason') {
                            continue;
                        }
                        if (($before[$field] ?? null) != $data[$field]) {
                            $changed = true;
                            break;
                        }
                    }
                    if (!$changed) {
                        continue;
                    }

                    $submittedVersion = $this->submittedVersion($payload);
                    $currentVersion = (int) ($before['version'] ?? 1);
                    if ($submittedVersion !== $currentVersion) {
                        throw new RuntimeException('Stale Match Player Performance submission.');
                    }

                    $nextVersion = $currentVersion + 1;
                    $after = $data + ['version' => $nextVersion];
                    $updated = $db->table('match_player_performances')
                        ->where([
                            'id' => (int) $before['id'],
                            'version' => $currentVersion,
                        ])
                        ->update($after + [
                            'updated_by' => $this->actor(),
                            'updated_at' => $now,
                        ]);
                    if (!$updated || $db->affectedRows() !== 1) {
                        throw new RuntimeException('Stale Match Player Performance submission.');
                    }
                    $event = 'match_player_performance_corrected';
                } else {
                    $after = $data + ['version' => 1];
                    $db->table('match_player_performances')->insert($after + [
                        'created_by' => $this->actor(),
                        'updated_by' => $this->actor(),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    $event = 'match_player_performance_created';
                }
                service('auditPersistence')->record(
                    $this->actor(),
                    $event,
                    'match_player_performance',
                    (string) ($before['id'] ?? $db->insertID()),
                    $branch,
                    $before,
                    $after,
                    $after['correction_reason'] ?? null,
                    $db,
                );
            }
            $db->transComplete();
            if (!$db->transStatus()) {
                throw new RuntimeException('Unable to save Match performance.');
            }
        } catch (Throwable $exception) {
            $db->transRollback();
            $db->resetTransStatus();
            throw $exception;
        }
    }

    private function submittedVersion(array $payload): int
    {
        $value = $payload['version'] ?? null;
        if ($value === null || $value === '' || filter_var($value, FILTER_VALIDATE_INT) === false) {
            throw new RuntimeException('A performance version is required for corrections.');
        }

        $version = (int) $value;
        if ($version < 1) {
            throw new RuntimeException('A performance version is required for corrections.');
        }

        return $version;
    }

    public function metrics(array $row): array
    {
        $runs = (int) ($row['runs'] ?? 0);
        $balls = (int) ($row['balls_faced'] ?? 0);
        $fours = (int) ($row['fours'] ?? 0);
        $sixes = (int) ($row['sixes'] ?? 0);
        $legalBalls = (int) ($row['legal_balls'] ?? 0);
        $wickets = (int) ($row['wickets'] ?? 0);
        $dismissed = !in_array((string) ($row['dismissal_type'] ?? ''), ['', 'not_out', 'not out'], true) ? 1 : 0;
        return [
            'batting_average' => $dismissed > 0 ? round($runs / $dismissed, 2) : null,
            'strike_rate' => $balls > 0 ? round($runs * 100 / $balls, 2) : null,
            'boundary_count' => $fours + $sixes,
            'boundary_runs' => $fours * 4 + $sixes * 6,
            'overs' => $legalBalls > 0 ? intdiv($legalBalls, 6) . '.' . ($legalBalls % 6) : '0.0',
            'economy' => $legalBalls > 0 ? round((int) ($row['runs_conceded'] ?? 0) * 6 / $legalBalls, 2) : null,
            'bowling_average' => $wickets > 0 ? round((int) ($row['runs_conceded'] ?? 0) / $wickets, 2) : null,
            'bowling_strike_rate' => $wickets > 0 ? round($legalBalls / $wickets, 2) : null,
            'dot_balls' => null,
            'fielding_dismissals' => ($row['catches'] ?? 0) + ($row['stumpings'] ?? 0) + ($row['run_outs_completed'] ?? 0),
            'fielding_contributions' => ($row['catches'] ?? 0) + ($row['stumpings'] ?? 0) + ($row['run_outs_completed'] ?? 0) + ($row['run_out_assists'] ?? 0),
            'dropped_catches' => $row['dropped_catches'] ?? null,
        ];
    }

    private function validated(array $payload, int $matchId, int $playerId, int $branch, array $members, ?array $before): array
    {
        $battingStatus = trim((string) ($payload['batting_status'] ?? ''));
        $bowlingStatus = trim((string) ($payload['bowling_status'] ?? ''));
        if (!in_array($battingStatus, self::BATTING_STATUSES, true) || !in_array($bowlingStatus, self::BOWLING_STATUSES, true)) {
            throw new RuntimeException('Select valid batting and bowling statuses.');
        }
        $data = ['batting_status' => $battingStatus, 'bowling_status' => $bowlingStatus];
        foreach (['runs', 'balls_faced', 'fours', 'sixes', 'batting_position', 'legal_balls', 'maidens', 'runs_conceded', 'wickets', 'wides', 'no_balls', 'byes', 'leg_byes', 'catches', 'stumpings', 'run_outs_completed', 'run_out_assists', 'direct_hit_run_outs', 'dropped_catches'] as $field) {
            $value = $payload[$field] ?? null;
            if ($value === '' || $value === null) {
                $data[$field] = null;
                continue;
            }
            if (filter_var($value, FILTER_VALIDATE_INT) === false || (int) $value < 0) {
                if ($field === 'legal_balls') {
                    throw new RuntimeException('Enter legal balls as a whole number greater than or equal to zero.');
                }
                throw new RuntimeException('Match performance values must be non-negative integers.');
            }
            $data[$field] = (int) $value;
        }
        if ($battingStatus === 'did_not_bat' && array_filter([$data['runs'], $data['balls_faced'], $data['fours'], $data['sixes']], static fn($value): bool => $value !== null && $value > 0) !== []) {
            throw new RuntimeException('Did not bat Players cannot have batting totals.');
        }
        if ($bowlingStatus === 'did_not_bowl' && array_filter([$data['legal_balls'], $data['maidens'], $data['runs_conceded'], $data['wickets'], $data['wides'], $data['no_balls'], $data['byes'], $data['leg_byes']], static fn($value): bool => $value !== null && $value > 0) !== []) {
            throw new RuntimeException('Did not bowl Players cannot have bowling totals.');
        }
        if (($data['fours'] ?? 0) * 4 + ($data['sixes'] ?? 0) * 6 > ($data['runs'] ?? 0)) {
            throw new RuntimeException('Boundary runs cannot exceed batting runs.');
        }
        if (($data['maidens'] ?? 0) * 6 > ($data['legal_balls'] ?? 0)) {
            throw new RuntimeException('Maidens cannot exceed legal balls.');
        }
        if (($data['wickets'] ?? 0) > ($data['legal_balls'] ?? 0)) {
            throw new RuntimeException('Wickets cannot exceed legal balls.');
        }
        $fieldingStatus = trim((string) ($payload['fielding_status'] ?? ''));
        if (!in_array($fieldingStatus, self::FIELDING_STATUSES, true)) {
            throw new RuntimeException('Select a valid fielding status.');
        }
        $data['fielding_status'] = $fieldingStatus;
        if ($fieldingStatus === 'did_not_field' && array_filter([
            $data['catches'],
            $data['stumpings'],
            $data['run_outs_completed'],
            $data['run_out_assists'],
            $data['direct_hit_run_outs'],
            $data['dropped_catches'],
        ], static fn($value): bool => $value !== null && $value > 0) !== []) {
            throw new RuntimeException('Did not field Players cannot have fielding totals.');
        }
        $data['fielding_position'] = trim((string) ($payload['fielding_position'] ?? '')) ?: null;
        $data['fielding_notes'] = trim((string) ($payload['fielding_notes'] ?? '')) ?: null;
        $data['dismissal_type'] = trim((string) ($payload['dismissal_type'] ?? '')) ?: null;
        $data['dismissed_by_player_id'] = $this->memberReference($payload['dismissed_by_player_id'] ?? null, $members);
        $data['fielder_player_id'] = $this->memberReference($payload['fielder_player_id'] ?? null, $members);
        $data['correction_reason'] = trim((string) ($payload['correction_reason'] ?? '')) ?: null;
        if ($before !== null) {
            $changed = false;
            foreach (array_keys($data) as $field) {
                if ($field === 'correction_reason') {
                    continue;
                }
                if (($before[$field] ?? null) != $data[$field]) {
                    $changed = true;
                    break;
                }
            }
            if ($changed && $data['correction_reason'] === null) {
                throw new RuntimeException('A correction reason is required.');
            }
        }
        return $data;
    }

    private function memberReference(mixed $value, array $members): ?int
    {
        if ($value === '' || $value === null) {
            return null;
        }
        $id = (int) $value;
        if (!isset($members[$id])) {
            throw new RuntimeException('Dismissal references must belong to the Match Players.');
        }
        return $id;
    }

    private function members(int $matchId, int $branch): array
    {
        $rows = Database::connect('default')->table('match_players mp')->join('players p', 'p.id = mp.player_id AND p.branch_id = ' . $branch)->where('mp.match_id', $matchId)->get()->getResultArray();
        $members = [];
        foreach ($rows as $row) {
            $members[(int) $row['player_id']] = true;
        }
        return $members;
    }

    private function match(int $id): array
    {
        $row = Database::connect('default')->table('matches')->where('id', $id)->get()->getRowArray();
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

    private function local(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        try {
            return (new DateTimeImmutable($value, new DateTimeZone('UTC')))
                ->setTimezone(new DateTimeZone((string) (config('App')->appTimezone ?: 'Asia/Kolkata')))
                ->format('d-m-Y H:i');
        } catch (Throwable) {
            return null;
        }
    }
}
