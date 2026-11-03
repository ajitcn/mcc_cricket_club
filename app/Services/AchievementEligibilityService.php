<?php

declare(strict_types=1);

namespace App\Services;

use Config\Database;
use RuntimeException;
use Throwable;

/** Draft-only, source-driven Achievement eligibility evaluation. */
final class AchievementEligibilityService
{
    private const RULES = [
        'attendance' => [
            'version' => 'attendance.v1',
            'source' => 'attendance',
            'recipient_type' => 'player',
        ],
        'performance' => [
            'version' => 'performance.v1',
            'source' => 'performance',
            'recipient_type' => 'player',
        ],
        'coach' => [
            'version' => 'coach.v1',
            'source' => 'session',
            'recipient_type' => 'coach',
        ],
    ];

    /** @param array<string, mixed> $input */
    public function evaluate(array $input): array
    {
        $values = $this->validatedInput($input);
        $this->allow($values['branch_id']);

        return $this->evaluateTransaction($values, null);
    }

    public function retry(int $evaluationId): array
    {
        $db = Database::connect('default');
        $evaluation = $db->table('achievement_eligibility_evaluations')
            ->where('id', $evaluationId)
            ->get()
            ->getRowArray();
        if (! is_array($evaluation) || (string) $evaluation['status'] !== 'failed') {
            throw new RuntimeException('Only failed eligibility evaluations can be retried.');
        }

        $branchId = (int) $evaluation['branch_id'];
        $this->allow($branchId);

        return $this->evaluateTransaction([
            'achievement_id' => (int) $evaluation['achievement_id'],
            'branch_id' => $branchId,
            'source_event_type' => (string) $evaluation['source_event_type'],
            'source_event_id' => (int) $evaluation['source_event_id'],
        ], $evaluationId);
    }

    /** @param array{achievement_id:int,branch_id:int,source_event_type:string,source_event_id:int} $values */
    private function evaluateTransaction(array $values, ?int $retryOf): array
    {
        $db = Database::connect('default');
        $achievement = $db->table('achievements')
            ->where([
                'id' => $values['achievement_id'],
                'branch_id' => $values['branch_id'],
                'is_active' => 1,
            ])
            ->get()
            ->getRowArray();
        if (! is_array($achievement)) {
            throw new RuntimeException('Achievement is not active for this branch.');
        }

        $category = strtolower(trim((string) $achievement['category']));
        $rule = self::RULES[$category] ?? null;
        if (! is_array($rule) || $rule['source'] !== $values['source_event_type']) {
            throw new RuntimeException('No eligible source rule exists for this Achievement category.');
        }

        $actor = $this->actorId();
        $now = gmdate('Y-m-d H:i:s');
        $attempt = $retryOf === null
            ? 1
            : ((int) $db->table('achievement_eligibility_evaluations')
                ->where('id', $retryOf)
                ->get()
                ->getRow('attempt_number') + 1);

        $db->transBegin();
        try {
            $evaluation = [
                'achievement_id' => $values['achievement_id'],
                'branch_id' => $values['branch_id'],
                'rule_version' => $rule['version'],
                'source_event_type' => $values['source_event_type'],
                'source_event_id' => $values['source_event_id'],
                'status' => 'running',
                'attempt_number' => $attempt,
                'retry_of_id' => $retryOf,
                'evaluated_by' => $actor,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            if ($db->table('achievement_eligibility_evaluations')->insert($evaluation) !== true) {
                throw new RuntimeException('Unable to start eligibility evaluation.');
            }
            $evaluationId = (int) $db->insertID();
            if ($evaluationId < 1) {
                throw new RuntimeException('Unable to start eligibility evaluation.');
            }

            $candidate = $this->resolveCandidate($db, $values, $category);
            if ($candidate === null) {
                $failure = 'Source event does not satisfy the active category rule.';
                $this->finishEvaluation($db, $evaluationId, 'failed', $failure, null, $now);
                service('auditPersistence')->record(
                    $actor,
                    'achievement_eligibility_failed',
                    'achievement_eligibility_evaluation',
                    (string) $evaluationId,
                    $values['branch_id'],
                    null,
                    ['status' => 'failed', 'source_event_type' => $values['source_event_type']],
                    $failure,
                    $db,
                );
                $db->transCommit();

                return $this->evaluationResult($evaluationId, 'failed', null, $failure);
            }

            $db->table('achievement_eligibility_evaluations')
                ->where('id', $evaluationId)
                ->update([
                    'candidate_type' => $candidate['recipient_type'],
                    'candidate_player_id' => $candidate['player_id'],
                    'candidate_coach_id' => $candidate['coach_id'],
                    'updated_at' => $now,
                ]);

            $duplicate = $this->existingDraft($db, $values['achievement_id'], $candidate);
            if ($duplicate !== null) {
                $this->finishEvaluation($db, $evaluationId, 'duplicate', null, $duplicate, $now);
                service('auditPersistence')->record(
                    $actor,
                    'achievement_eligibility_duplicate',
                    'achievement_eligibility_evaluation',
                    (string) $evaluationId,
                    $values['branch_id'],
                    null,
                    ['status' => 'duplicate', 'award_id' => $duplicate],
                    null,
                    $db,
                );
                $db->transCommit();

                return $this->evaluationResult($evaluationId, 'duplicate', $duplicate, null);
            }

            $award = [
                'achievement_id' => $values['achievement_id'],
                'branch_id' => $values['branch_id'],
                'recipient_type' => $candidate['recipient_type'],
                'player_id' => $candidate['player_id'],
                'coach_id' => $candidate['coach_id'],
                'status' => 'draft',
                'nomination_note' => null,
                'created_by' => $actor,
                'updated_by' => $actor,
                'correction_reason' => null,
                'created_at' => $now,
                'updated_at' => $now,
                'eligibility_evaluation_id' => $evaluationId,
                'eligibility_rule_version' => $rule['version'],
                'eligibility_source_event' => $values['source_event_type'] . ':' . $values['source_event_id'],
                'eligibility_evaluated_at' => $now,
            ];
            if ($db->table('achievement_awards')->insert($award) !== true) {
                throw new RuntimeException('Unable to save eligible Achievement draft.');
            }
            $awardId = (int) $db->insertID();
            $this->finishEvaluation($db, $evaluationId, 'succeeded', null, $awardId, $now);
            service('auditPersistence')->record(
                $actor,
                'achievement_eligibility_draft_created',
                'achievement_award',
                (string) $awardId,
                $values['branch_id'],
                null,
                [
                    'status' => 'draft',
                    'recipient_type' => $candidate['recipient_type'],
                    'eligibility_evaluation_id' => $evaluationId,
                    'eligibility_rule_version' => $rule['version'],
                    'eligibility_source_event' => $values['source_event_type'] . ':' . $values['source_event_id'],
                ],
                null,
                $db,
            );
            $db->transCommit();

            return $this->evaluationResult($evaluationId, 'succeeded', $awardId, null);
        } catch (Throwable $exception) {
            $db->transRollback();
            throw $exception;
        }
    }

    /** @param array{achievement_id:int,branch_id:int,source_event_type:string,source_event_id:int} $values */
    private function resolveCandidate(object $db, array $values, string $category): ?array
    {
        if ($category === 'attendance') {
            $row = $db->table('attendance a')
                ->select('a.player_id')
                ->join('players p', 'p.id = a.player_id')
                ->where([
                    'a.id' => $values['source_event_id'],
                    'a.branch_id' => $values['branch_id'],
                    'p.branch_id' => $values['branch_id'],
                    'p.status' => 'active',
                ])
                ->whereIn('a.status', ['present', 'late'])
                ->get()
                ->getRowArray();

            return is_array($row) ? [
                'recipient_type' => 'player',
                'player_id' => (int) $row['player_id'],
                'coach_id' => null,
            ] : null;
        }

        if ($category === 'performance') {
            $row = $db->table('performance_records pr')
                ->select('pr.player_id')
                ->join('players p', 'p.id = pr.player_id')
                ->where([
                    'pr.id' => $values['source_event_id'],
                    'pr.branch_id' => $values['branch_id'],
                    'p.branch_id' => $values['branch_id'],
                    'p.status' => 'active',
                    'pr.approved' => 1,
                ])
                ->where('pr.deleted_at', null)
                ->groupStart()
                ->where('pr.runs >=', 50)
                ->orWhere('pr.wickets >=', 3)
                ->orWhere('pr.catches >=', 2)
                ->groupEnd()
                ->get()
                ->getRowArray();

            return is_array($row) ? [
                'recipient_type' => 'player',
                'player_id' => (int) $row['player_id'],
                'coach_id' => null,
            ] : null;
        }

        $row = $db->table('sessions s')
            ->select('s.coach_user_id')
            ->join('coach_profiles cp', 'cp.user_id = s.coach_user_id')
            ->join('user_branches ub', 'ub.user_id = s.coach_user_id AND ub.branch_id = s.branch_id')
            ->where([
                's.id' => $values['source_event_id'],
                's.branch_id' => $values['branch_id'],
                's.status' => 'completed',
                'cp.is_active' => 1,
            ])
            ->get()
            ->getRowArray();

        return is_array($row) ? [
            'recipient_type' => 'coach',
            'player_id' => null,
            'coach_id' => (int) $row['coach_user_id'],
        ] : null;
    }

    /** @param array{recipient_type:string,player_id:?int,coach_id:?int} $candidate */
    private function existingDraft(object $db, int $achievementId, array $candidate): ?int
    {
        $recipientColumn = $candidate['recipient_type'] === 'player' ? 'player_id' : 'coach_id';
        $recipientId = $candidate['recipient_type'] === 'player'
            ? $candidate['player_id']
            : $candidate['coach_id'];
        $row = $db->table('achievement_awards')
            ->select('id')
            ->where([
                'achievement_id' => $achievementId,
                'recipient_type' => $candidate['recipient_type'],
                'status' => 'draft',
                'is_archived' => 0,
                $recipientColumn => $recipientId,
            ])
            ->get()
            ->getRowArray();

        return is_array($row) ? (int) $row['id'] : null;
    }

    private function finishEvaluation(
        object $db,
        int $id,
        string $status,
        ?string $failure,
        ?int $awardId,
        string $now,
    ): void {
        $db->table('achievement_eligibility_evaluations')
            ->where('id', $id)
            ->update([
                'status' => $status,
                'failure_reason' => $failure,
                'award_id' => $awardId,
                'evaluated_at' => $now,
                'updated_at' => $now,
            ]);
    }

    /** @return array{evaluation_id:int,status:string,award_id:?int,failure_reason:?string} */
    private function evaluationResult(int $id, string $status, ?int $awardId, ?string $failure): array
    {
        return [
            'evaluation_id' => $id,
            'status' => $status,
            'award_id' => $awardId,
            'failure_reason' => $failure,
        ];
    }

    /** @return array{achievement_id:int,branch_id:int,source_event_type:string,source_event_id:int} */
    private function validatedInput(array $input): array
    {
        $achievementId = filter_var($input['achievement_id'] ?? null, FILTER_VALIDATE_INT);
        $branchId = filter_var($input['branch_id'] ?? null, FILTER_VALIDATE_INT);
        $sourceId = filter_var($input['source_event_id'] ?? null, FILTER_VALIDATE_INT);
        $sourceType = trim((string) ($input['source_event_type'] ?? ''));
        if ($achievementId === false || $branchId === false || $sourceId === false
            || $achievementId < 1 || $branchId < 1 || $sourceId < 1
            || ! in_array($sourceType, ['attendance', 'performance', 'session'], true)) {
            throw new RuntimeException('Enter a valid Achievement eligibility source.');
        }

        return [
            'achievement_id' => (int) $achievementId,
            'branch_id' => (int) $branchId,
            'source_event_type' => $sourceType,
            'source_event_id' => (int) $sourceId,
        ];
    }

    private function allow(int $branchId): void
    {
        if (! service('authorization')->can('achievement.eligibility.evaluate')
            || ! service('authorization')->can('achievement.eligibility.evaluate', (string) $branchId)) {
            throw new RuntimeException('Forbidden');
        }
    }

    private function actorId(): int
    {
        $user = session('user');
        $id = is_array($user) ? ($user['id'] ?? null) : null;
        if (! is_scalar($id) || ! ctype_digit((string) $id) || (int) $id < 1) {
            throw new RuntimeException('Forbidden');
        }

        return (int) $id;
    }
}
