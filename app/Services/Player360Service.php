<?php

declare(strict_types=1);

namespace App\Services;

use Config\Database;
use RuntimeException;

final class Player360Service
{
    public function view(int $playerId): array
    {
        $player = service('player')->find($playerId);
        $branchId = (int) $player['branch_id'];

        if (! service('authorization')->can('player360.view', (string) $branchId)) {
            throw new RuntimeException('Forbidden');
        }

        $db = Database::connect('default');
        $canViewAdmission = service('authorization')->can('admission.view', (string) $branchId);

        return [
            'player' => $this->profile($player, $canViewAdmission),
            'admission' => [
                'admission_id' => $player['admission_id'],
                'status' => $player['admission_status'],
                'trial_id' => $player['trial_id'],
                'lead_id' => $player['lead_id'],
                'session_progress' => service('scheduling')->sessionProgressForPlayer($playerId),
            ],
            'assignments' => [
                'current_batch' => $player['current_batch'],
                'current_coach' => $player['current_coach'],
                'batch_history' => $player['batch_history'],
                'coach_history' => $player['coach_history'],
            ],
            'status_history' => $player['status_history'],
            'sections' => [
                'attendance' => $this->attendance($db, $playerId, $branchId),
                'sessions' => $this->sessions($db, $playerId, $branchId),
                'matches' => $this->matches($db, $playerId, $branchId),
                'tournaments' => $this->tournaments($db, $playerId, $branchId),
                'development' => $this->development($db, $playerId, $branchId),
                'fitness' => $this->fitness($db, $playerId, $branchId),
                'performance' => $this->performance($playerId, $branchId),
                'high_performance' => $this->unavailable('No high-performance records are available for this Player.'),
                'documents' => $this->unavailable('No document records are available for this Player view.'),
                'medical' => $this->unavailable('No medical records are available for this Player view.'),
            ],
        ];
    }

    private function profile(array $player, bool $canViewAdmission): array
    {
        return [
            'id' => $player['id'],
            'branch_id' => $player['branch_id'],
            'branch_name' => $player['branch_name'],
            'status' => $player['status'],
            'updated_at' => $player['updated_at'],
            'admission_number' => $canViewAdmission ? ($player['admission_number'] ?? null) : null,
            'admission_status' => $canViewAdmission ? ($player['admission_status'] ?? null) : null,
            'admission_age_group' => $canViewAdmission ? ($player['admission_age_group'] ?? null) : null,
            'group_coach_name' => $canViewAdmission ? ($player['group_coach_name'] ?? null) : null,
            'personal_coach_name' => $canViewAdmission ? ($player['personal_coach_name'] ?? null) : null,
            'profile' => $player['profile'],
        ];
    }

    private function attendance(object $db, int $playerId, int $branchId): array
    {
        if (! service('authorization')->can('attendance.view', (string) $branchId)) {
            return $this->unavailable('Attendance permission is not available.');
        }

        $rows = $db->table('attendance a')
            ->select('a.id, a.status, a.recorded_at, s.id AS session_id, s.starts_at, s.ends_at')
            ->join('sessions s', 's.id = a.session_id AND a.branch_id = s.branch_id')
            ->where(['a.player_id' => $playerId, 'a.branch_id' => $branchId, 's.branch_id' => $branchId])
            ->orderBy('s.starts_at', 'DESC')
            ->get()->getResultArray();
        $summary = [];
        foreach ($rows as $row) {
            $summary[$row['status']] = ($summary[$row['status']] ?? 0) + 1;
        }

        return ['available' => true, 'summary' => $summary, 'rows' => $rows];
    }

    private function sessions(object $db, int $playerId, int $branchId): array
    {
        if (! service('authorization')->can('session.view', (string) $branchId)) {
            return $this->unavailable('Session permission is not available.');
        }

        $rows = $db->table('session_players sp')
            ->select('s.id, s.starts_at, s.ends_at, s.status, s.coach_user_id, u.display_name AS coach_name')
            ->join('sessions s', 's.id = sp.session_id')
            ->join('users u', 'u.id = s.coach_user_id', 'left')
            ->where(['sp.player_id' => $playerId, 's.branch_id' => $branchId])
            ->orderBy('s.starts_at', 'DESC')
            ->get()->getResultArray();

        return ['available' => true, 'rows' => $rows];
    }

    private function matches(object $db, int $playerId, int $branchId): array
    {
        if (! service('authorization')->can('match.view', (string) $branchId)) {
            return $this->unavailable('Match permission is not available.');
        }

        $rows = $db->table('match_players mp')
            ->select('m.id, m.opponent, m.match_type, m.status, m.starts_at, m.ends_at, m.result, m.score_summary, mp.selection_type')
            ->join('matches m', 'm.id = mp.match_id')
            ->where(['mp.player_id' => $playerId, 'm.branch_id' => $branchId])
            ->orderBy('m.starts_at', 'DESC')
            ->get()->getResultArray();

        return ['available' => true, 'rows' => $rows];
    }

    private function tournaments(object $db, int $playerId, int $branchId): array
    {
        if (! service('authorization')->can('tournament.view', (string) $branchId)) {
            return $this->unavailable('Tournament permission is not available.');
        }

        $tournaments = $db->table('tournament_participants tp')
            ->select('t.id, t.name, t.tournament_type, t.status, t.starts_on, t.ends_on, tp.participant_type, tp.team_name')
            ->join('tournaments t', 't.id = tp.tournament_id')
            ->where(['tp.player_id' => $playerId, 't.branch_id' => $branchId])
            ->orderBy('t.starts_on', 'DESC')
            ->get()->getResultArray();
        $fixtures = $db->table('tournament_fixtures tf')
            ->select('tf.tournament_id, tf.fixture_number, tf.home_team, tf.away_team, tf.scheduled_at, tf.status, tf.match_id, m.result, m.score_summary')
            ->join('tournament_participants tp', 'tp.tournament_id = tf.tournament_id')
            ->join('tournaments t', 't.id = tf.tournament_id')
            ->join('matches m', 'm.id = tf.match_id', 'left')
            ->where(['tp.player_id' => $playerId, 't.branch_id' => $branchId])
            ->groupBy('tf.id')
            ->orderBy('tf.scheduled_at', 'DESC')
            ->get()->getResultArray();

        return ['available' => true, 'tournaments' => $tournaments, 'fixtures' => $fixtures];
    }

    private function development(object $db, int $playerId, int $branchId): array
    {
        if (! service('authorization')->can('development.view', (string) $branchId)) {
            return $this->unavailable('Development permission is not available.');
        }

        return service('development')->forPlayer($playerId, $branchId);
    }

    private function fitness(object $db, int $playerId, int $branchId): array
    {
        if (! service('authorization')->can('fitness.view', (string) $branchId)) {
            return $this->unavailable('Fitness permission is not available.');
        }

        $assessments = $db->table('fitness_assessments')
            ->select('id, assessment_date, height_cm, weight_kg, body_fat_percent, vertical_jump_cm, sprint_time_seconds, agility_seconds, flexibility_cm, yoyo_level, endurance_minutes')
            ->where(['player_id' => $playerId, 'branch_id' => $branchId])
            ->orderBy('assessment_date', 'DESC')
            ->get()->getResultArray();
        $plans = $db->table('fitness_plans')
            ->select('id, name, starts_on, ends_on, status')
            ->where(['player_id' => $playerId, 'branch_id' => $branchId])
            ->orderBy('starts_on', 'DESC')
            ->get()->getResultArray();

        return ['available' => true, 'assessments' => $assessments, 'plans' => $plans];
    }

    private function performance(int $playerId, int $branchId): array
    {
        if (! service('authorization')->can('performance.view', (string) $branchId)) {
            return $this->unavailable('Performance permission is not available.');
        }

        try {
            $performance = service('performance')->player($playerId);
        } catch (RuntimeException) {
            return $this->unavailable('No performance records are available for this Player.');
        }

        return [
            'available' => true,
            'rows' => $performance['rows'] ?? [],
            'kpis' => $performance['kpis'] ?? [],
        ];
    }

    private function unavailable(string $reason): array
    {
        return ['available' => false, 'value' => 'Not available', 'reason' => $reason];
    }
}
