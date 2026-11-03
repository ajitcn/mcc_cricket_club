<?php

declare (strict_types=1);
namespace App\Services;

use App\Modules\Attendance\Models\AttendanceModel;
use App\Modules\Attendance\Models\AttendanceRosterSnapshotModel;
use CodeIgniter\Database\Exceptions\DatabaseException;
use Config\Database;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;
use Throwable;
final class AttendanceService
{
    private const SESSIONS_PER_PAGE = 20;
    public const STATUSES = ['PRESENT', 'ABSENT', 'LEAVE', 'MEDICAL', 'LATE', 'RAIN_CANCELLED', 'COACH_CANCELLED'];

    public function statuses(): array
    {
        return self::STATUSES;
    }

    public function sessions(array $filters = []): array
    {
        return $this->sessionPage($filters)['rows'];
    }

    public function sessionPage(array $filters = []): array
    {
        $model = new AttendanceModel();
        $branchIds = $this->authorizedBranchIds();
        $coachScopeUserId = $this->strictCoachActorId();
        $page = $this->pageNumber($filters['page'] ?? 1);
        $queryFilters = $this->sessionFilters($filters);
        $total = $model->countSessionRows($queryFilters, $branchIds, $coachScopeUserId);
        $pages = max(1, (int) ceil($total / self::SESSIONS_PER_PAGE));
        $page = min($page, $pages);
        $rows = $model->sessionRows(
            $queryFilters,
            $branchIds,
            self::SESSIONS_PER_PAGE,
            ($page - 1) * self::SESSIONS_PER_PAGE,
            $coachScopeUserId
        );

        foreach ($rows as &$row) {
            $row['starts_at_local'] = $this->local((string) $row['starts_at']);
            $row['ends_at_local'] = $this->local((string) $row['ends_at']);
        }

        return [
            'rows' => $rows,
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'perPage' => self::SESSIONS_PER_PAGE,
        ];
    }

    public function sessionFilterOptions(): array
    {
        return (new AttendanceModel())->sessionFilterOptions(
            $this->authorizedBranchIds(),
            $this->strictCoachActorId()
        );
    }
    public function roster(int $sessionId): array
    {
        $model = new AttendanceModel();
        $session = $model->sessionDetails($sessionId);
        if (!is_array($session)) {
            throw new RuntimeException('Session not found.');
        }
        $branch = (int) $session['branch_id'];
        $this->allow($branch, 'attendance.view');
        $this->assertCoachSessionAccess($session, $this->actorId());
        $session['starts_at_local'] = $this->local((string) $session['starts_at']);
        $session['ends_at_local'] = $this->local((string) $session['ends_at']);
        $snapshot = (new AttendanceRosterSnapshotModel())->forSession($sessionId);
        $snapshotReady = is_array($snapshot)
            && (int) $snapshot['branch_id'] === $branch;

        return [
            'session' => $session,
            'players' => $snapshotReady
                ? $model->snapshotRoster($sessionId, $branch)
                : $model->sessionRoster($sessionId),
            'snapshot_status' => $snapshotReady ? 'ready' : 'missing',
            'snapshot' => $snapshotReady ? $snapshot : null,
            'can_capture' => service('authorization')->can(
                'attendance.record',
                (string) $branch
            ),
            'can_record' => service('authorization')->can(
                'attendance.record',
                (string) $branch
            ),
            'can_correct' => service('authorization')->can(
                'attendance.correct',
                (string) $branch
            ),
        ];
    }

    /**
     * Update attendance from the dedicated edit page without changing the
     * immutable roster or attendance history model.
     *
     * @param array<int|string, string> $statuses
     * @param array<int|string, int|string> $versions
     */
    public function updateStatuses(
        int $sessionId,
        array $statuses,
        array $versions,
        string $correctionReason = ''
    ): int {
        if ($statuses === []) {
            throw new RuntimeException('Select an attendance status.');
        }

        $db = Database::connect('default');
        $db->transStart();

        try {
            $session = $this->lockSession($db, $sessionId);
            $actor = $this->allow((int) $session['branch_id'], 'attendance.correct');
            $this->assertCoachSessionAccess($session, $actor);
            if ($session['status'] !== 'scheduled') {
                throw new RuntimeException('Attendance is not available for this session.');
            }

            $memberIds = $this->editableMemberIds($db, $session);
            $submittedIds = [];
            $now = gmdate('Y-m-d H:i:s');
            $updated = 0;

            foreach ($statuses as $playerId => $status) {
                if (! ctype_digit((string) $playerId) || (int) $playerId < 1) {
                    throw new RuntimeException('Player is not eligible.');
                }

                $playerId = (int) $playerId;
                if (isset($submittedIds[$playerId]) || ! isset($memberIds[$playerId])) {
                    throw new RuntimeException('Player is not eligible.');
                }
                $submittedIds[$playerId] = true;
                if (! is_string($status) || $status === '') {
                    throw new RuntimeException('Select an attendance status.');
                }
                $this->status($status);

                $attendance = $db->table('attendance')
                    ->where([
                        'session_id' => $sessionId,
                        'player_id' => $playerId,
                        'branch_id' => (int) $session['branch_id'],
                    ])
                    ->get()
                    ->getRowArray();

                if (! is_array($attendance)) {
                    if (isset($versions[$playerId]) && (string) $versions[$playerId] !== '0') {
                        throw new RuntimeException('Stale attendance update.');
                    }

                    if (! $db->table('attendance')->insert([
                        'session_id' => $sessionId,
                        'player_id' => $playerId,
                        'branch_id' => (int) $session['branch_id'],
                        'status' => $status,
                        'recorded_by' => $actor,
                        'recorded_at' => $now,
                        'version' => 1,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])) {
                        throw new RuntimeException('Unable to record attendance.');
                    }

                    $attendanceId = (int) $db->insertID();
                    $historyInserted = $db->table('attendance_history')->insert([
                        'attendance_id' => $attendanceId,
                        'prior_status' => null,
                        'new_status' => $status,
                        'actor_user_id' => $actor,
                        'reason' => trim($correctionReason) === '' ? null : trim($correctionReason),
                        'changed_at' => $now,
                        'prior_version' => 0,
                        'new_version' => 1,
                    ]);
                    if (! $historyInserted) {
                        throw new RuntimeException('Unable to correct attendance.');
                    }

                    service('auditPersistence')->record(
                        $actor,
                        'attendance_recorded',
                        'attendance',
                        (string) $attendanceId,
                        (int) $session['branch_id'],
                        null,
                        [
                            'session_id' => $sessionId,
                            'player_id' => $playerId,
                            'status' => $status,
                            'source' => 'attendance_edit',
                        ],
                        null,
                        null,
                        $db
                    );
                    $updated++;
                    continue;
                }

                $expectedVersion = $versions[$playerId] ?? null;
                if (! ctype_digit((string) $expectedVersion) || (int) $expectedVersion < 1) {
                    throw new RuntimeException('Attendance version required.');
                }
                if ((int) $attendance['version'] !== (int) $expectedVersion) {
                    throw new RuntimeException('Stale attendance update.');
                }
                if ((string) $attendance['status'] === $status) {
                    continue;
                }
                if (trim($correctionReason) === '') {
                    throw new RuntimeException('Correction reason required.');
                }

                $nextVersion = (int) $attendance['version'] + 1;
                $changed = $db->table('attendance')
                    ->where([
                        'id' => (int) $attendance['id'],
                        'version' => (int) $attendance['version'],
                    ])
                    ->update([
                        'status' => $status,
                        'version' => $nextVersion,
                        'updated_at' => $now,
                    ]);
                if (! $changed) {
                    throw new RuntimeException('Stale attendance update.');
                }

                if (! $db->table('attendance_history')->insert([
                    'attendance_id' => (int) $attendance['id'],
                    'prior_status' => $attendance['status'],
                    'new_status' => $status,
                    'actor_user_id' => $actor,
                    'reason' => trim($correctionReason),
                    'changed_at' => $now,
                    'prior_version' => (int) $attendance['version'],
                    'new_version' => $nextVersion,
                ])) {
                    throw new RuntimeException('Unable to correct attendance.');
                }

                service('auditPersistence')->record(
                    $actor,
                    'attendance_corrected',
                    'attendance',
                    (string) $attendance['id'],
                    (int) $session['branch_id'],
                    [
                        'status' => $attendance['status'],
                        'version' => (int) $attendance['version'],
                    ],
                    [
                        'status' => $status,
                        'version' => $nextVersion,
                        'source' => 'attendance_edit',
                    ],
                    trim($correctionReason),
                    $db
                );
                $updated++;
            }

            $db->transComplete();
            if (! $db->transStatus()) {
                throw new RuntimeException('Unable to correct attendance.');
            }

            return $updated;
        } catch (Throwable $exception) {
            $db->transRollback();
            $db->resetTransStatus();
            throw $exception;
        }
    }
    public function record(int $sessionId, int $playerId, string $status, ?int $snapshotId = null): int
    {
        $this->status($status);
        $db = Database::connect('default');
        $db->transStart();
        try {
            $s = $this->lockSession($db, $sessionId);
            $a = $this->allow((int) $s['branch_id'], 'attendance.record');
            $this->assertCoachSessionAccess($s, $a);
            if ($s['status'] !== 'scheduled') {
                throw new RuntimeException('Attendance is not available for this session.');
            }
            $this->timing($s);
            $this->ensureRosterSnapshot($db, $s, $a);
            $this->eligible($db, $s, $playerId, $snapshotId);
            if (is_array($db->table('attendance')->where(['session_id' => $sessionId, 'player_id' => $playerId])->get()->getRowArray())) {
                throw new RuntimeException('Attendance already exists.');
            }
            $now = gmdate('Y-m-d H:i:s');
            $inserted = $db->table('attendance')->insert([
                'session_id' => $sessionId,
                'player_id' => $playerId,
                'branch_id' => (int) $s['branch_id'],
                'status' => $status,
                'recorded_by' => $a,
                'recorded_at' => $now,
                'version' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            if (! $inserted) {
                throw new RuntimeException('Unable to record attendance.');
            }

            $id = (int) $db->insertID();
            if ($id < 1) {
                $saved = $db->table('attendance')
                    ->select('id')
                    ->where([
                        'session_id' => $sessionId,
                        'player_id' => $playerId,
                        'branch_id' => (int) $s['branch_id'],
                    ])
                    ->get()
                    ->getRowArray();
                $id = (int) ($saved['id'] ?? 0);
            }
            if ($id < 1) {
                throw new RuntimeException('Unable to record attendance.');
            }
            $historyInserted = $db->table('attendance_history')->insert([
                'attendance_id' => $id,
                'prior_status' => null,
                'new_status' => $status,
                'actor_user_id' => $a,
                'reason' => null,
                'changed_at' => $now,
                'prior_version' => 0,
                'new_version' => 1,
            ]);
            if (! $historyInserted) {
                throw new RuntimeException('Unable to persist attendance history.');
            }
            service('auditPersistence')->record(
                $a,
                'attendance_recorded',
                'attendance',
                (string) $id,
                (int) $s['branch_id'],
                null,
                [
                    'session_id' => $sessionId,
                    'player_id' => $playerId,
                    'status' => $status,
                ],
                null,
                null,
                $db
            );
            $db->transComplete();
            if (!$db->transStatus()) {
                throw new RuntimeException('Unable to record attendance.');
            }
            return $id;
        } catch (Throwable $e) {
            $db->transRollback();
            $db->resetTransStatus();

            if (
                $e instanceof DatabaseException
                && str_contains(strtolower($e->getMessage()), 'duplicate')
            ) {
                throw new RuntimeException('Attendance already exists.', 0, $e);
            }

            throw $e;
        }
    }

    /**
     * Record the first attendance status for multiple snapshot members atomically.
     * Existing rows are never overwritten; corrections continue through correct().
     *
     * @param array<int|string, string> $statuses
     */
    public function save(int $sessionId, array $statuses, ?int $snapshotId = null): int
    {
        if ($statuses === []) {
            throw new RuntimeException('Select an attendance status.');
        }

        $db = Database::connect('default');
        $db->transStart();

        try {
            $session = $this->lockSession($db, $sessionId);
            $actor = $this->allow((int) $session['branch_id'], 'attendance.record');
            $this->assertCoachSessionAccess($session, $actor);
            if ($session['status'] !== 'scheduled') {
                throw new RuntimeException('Attendance is not available for this session.');
            }
            $this->timing($session);
            $this->ensureRosterSnapshot($db, $session, $actor);
            $this->eligibleSnapshot($db, $session, $snapshotId);

            $memberRows = $db->table('attendance_roster_snapshot_members m')
                ->select('m.player_id')
                ->join(
                    'attendance_roster_snapshots s',
                    's.id = m.snapshot_id AND s.session_id = ' . (int) $sessionId,
                    'inner'
                )
                ->where('m.branch_id', (int) $session['branch_id'])
                ->get()
                ->getResultArray();
            $memberIds = array_fill_keys(
                array_map('intval', array_column($memberRows, 'player_id')),
                true
            );
            $now = gmdate('Y-m-d H:i:s');
            $saved = 0;

            foreach ($statuses as $playerId => $status) {
                if (! ctype_digit((string) $playerId) || (int) $playerId < 1) {
                    throw new RuntimeException('Player is not eligible.');
                }
                $playerId = (int) $playerId;
                $this->status((string) $status);
                if (! isset($memberIds[$playerId])) {
                    throw new RuntimeException('Player is not eligible.');
                }
                if (is_array($db->table('attendance')->where([
                    'session_id' => $sessionId,
                    'player_id' => $playerId,
                ])->get()->getRowArray())) {
                    throw new RuntimeException('Attendance already exists.');
                }

                if (! $db->table('attendance')->insert([
                    'session_id' => $sessionId,
                    'player_id' => $playerId,
                    'branch_id' => (int) $session['branch_id'],
                    'status' => (string) $status,
                    'recorded_by' => $actor,
                    'recorded_at' => $now,
                    'version' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])) {
                    throw new RuntimeException('Unable to record attendance.');
                }

                $attendanceId = (int) $db->insertID();
                if (! $db->table('attendance_history')->insert([
                    'attendance_id' => $attendanceId,
                    'prior_status' => null,
                    'new_status' => (string) $status,
                    'actor_user_id' => $actor,
                    'reason' => null,
                    'changed_at' => $now,
                    'prior_version' => 0,
                    'new_version' => 1,
                ])) {
                    throw new RuntimeException('Unable to persist attendance history.');
                }

                service('auditPersistence')->record(
                    $actor,
                    'attendance_recorded',
                    'attendance',
                    (string) $attendanceId,
                    (int) $session['branch_id'],
                    null,
                    [
                        'session_id' => $sessionId,
                        'player_id' => $playerId,
                        'status' => (string) $status,
                    ],
                    null,
                    null,
                    $db
                );
                $saved++;
            }

            $db->transComplete();
            if (! $db->transStatus()) {
                throw new RuntimeException('Unable to record attendance.');
            }

            return $saved;
        } catch (Throwable $e) {
            $db->transRollback();
            $db->resetTransStatus();
            throw $e;
        }
    }

    /**
     * Create the immutable attendance membership record on first save.
     * Existing snapshot rows remain the source for historical sessions.
     */
    private function ensureRosterSnapshot(object $db, array $session, int $actor): int
    {
        $snapshot = (new AttendanceRosterSnapshotModel())->forSession((int) $session['id']);
        if (is_array($snapshot)) {
            if ((int) $snapshot['branch_id'] !== (int) $session['branch_id']) {
                throw new RuntimeException('Attendance roster snapshot branch mismatch.');
            }

            return (int) $snapshot['id'];
        }

        $roster = $db->query(
            'SELECT sp.player_id, p.branch_id '
            . 'FROM session_players sp '
            . 'INNER JOIN players p ON p.id = sp.player_id '
            . 'WHERE sp.session_id = ? '
            . 'ORDER BY sp.player_id ASC',
            [(int) $session['id']]
        )->getResultArray();
        if ($roster === []) {
            throw new RuntimeException('Attendance roster is empty.');
        }

        foreach ($roster as $player) {
            if ((int) $player['branch_id'] !== (int) $session['branch_id']) {
                throw new RuntimeException('Attendance roster snapshot branch mismatch.');
            }
        }

        $now = gmdate('Y-m-d H:i:s');
        if (! $db->table('attendance_roster_snapshots')->insert([
            'session_id' => (int) $session['id'],
            'branch_id' => (int) $session['branch_id'],
            'captured_at' => $now,
        ])) {
            throw new RuntimeException('Unable to prepare attendance.');
        }

        $snapshotId = (int) $db->insertID();
        if ($snapshotId < 1) {
            throw new RuntimeException('Unable to prepare attendance.');
        }

        foreach ($roster as $player) {
            if (! $db->table('attendance_roster_snapshot_members')->insert([
                'snapshot_id' => $snapshotId,
                'player_id' => (int) $player['player_id'],
                'branch_id' => (int) $session['branch_id'],
            ])) {
                throw new RuntimeException('Unable to prepare attendance.');
            }
        }

        service('auditPersistence')->record(
            $actor,
            'attendance_membership_prepared',
            'attendance_roster_snapshot',
            (string) $snapshotId,
            (int) $session['branch_id'],
            null,
            [
                'session_id' => (int) $session['id'],
                'member_count' => count($roster),
            ],
            null,
            null,
            $db
        );

        return $snapshotId;
    }

    /**
     * Compatibility API for historical integrations. New UI flows call
     * ensureRosterSnapshot() from the attendance save transaction instead.
     */
    public function captureRosterSnapshot(int $sessionId): int
    {
        $db = Database::connect('default');
        $db->transStart();

        try {
            $session = $this->lockSession($db, $sessionId);
            $actor = $this->allow((int) $session['branch_id'], 'attendance.record');
            $this->assertCoachSessionAccess($session, $actor);
            if ($session['status'] !== 'scheduled') {
                throw new RuntimeException('Attendance roster snapshot is unavailable for this session.');
            }

            $now = gmdate('Y-m-d H:i:s');

            $snapshotModel = new AttendanceRosterSnapshotModel();
            $existing = $snapshotModel->forSession($sessionId);
            if (is_array($existing)) {
                if ((int) $existing['branch_id'] !== (int) $session['branch_id']) {
                    throw new RuntimeException('Attendance roster snapshot branch mismatch.');
                }
                $db->transComplete();
                if (! $db->transStatus()) {
                    throw new RuntimeException('Unable to read attendance roster snapshot.');
                }
                return (int) $existing['id'];
            }

            $roster = $db->query(
                'SELECT sp.player_id, p.branch_id '
                . 'FROM session_players sp '
                . 'INNER JOIN players p ON p.id = sp.player_id '
                . 'WHERE sp.session_id = ? '
                . 'ORDER BY sp.player_id ASC',
                [$sessionId]
            )->getResultArray();
            if ($roster === []) {
                $roster = (new AttendanceModel())->eligiblePlayersForSession(
                    $session,
                    $this->strictCoachActorId()
                );
                $roster = array_map(
                    static fn(array $player): array => [
                        'player_id' => (int) $player['player_id'],
                        'branch_id' => (int) $session['branch_id'],
                    ],
                    $roster
                );
            }
            if ($roster === []) {
                throw new RuntimeException('Attendance roster is empty.');
            }
            foreach ($roster as $player) {
                if ((int) $player['branch_id'] !== (int) $session['branch_id']) {
                    throw new RuntimeException('Attendance roster snapshot branch mismatch.');
                }
            }

            $db->table('attendance_roster_snapshots')->insert([
                'session_id' => $sessionId,
                'branch_id' => (int) $session['branch_id'],
                'captured_at' => $now,
            ]);
            $snapshotId = (int) $db->insertID();
            foreach ($roster as $player) {
                $db->table('attendance_roster_snapshot_members')->insert([
                    'snapshot_id' => $snapshotId,
                    'player_id' => (int) $player['player_id'],
                    'branch_id' => (int) $session['branch_id'],
                ]);
            }

            service('auditPersistence')->record(
                $actor,
                'attendance_roster_snapshot_captured',
                'attendance_roster_snapshot',
                (string) $snapshotId,
                (int) $session['branch_id'],
                null,
                [
                    'session_id' => $sessionId,
                    'member_count' => count($roster),
                    'captured_at' => $now,
                ],
                null,
                $db
            );
            $db->transComplete();
            if (! $db->transStatus()) {
                throw new RuntimeException('Unable to capture attendance roster snapshot.');
            }
            return $snapshotId;
        } catch (Throwable $e) {
            $db->transRollback();
            $db->resetTransStatus();
            throw $e;
        }
    }

    public function correct(int $id, string $status, string $reason, int $version, ?int $snapshotId = null): int
    {
        $this->status($status);
        if (trim($reason) === '') {
            throw new RuntimeException('Correction reason required.');
        }
        if ($version < 1) {
            throw new RuntimeException('Attendance version required.');
        }
        $db = Database::connect('default');
        $db->transStart();
        try {
            $attendance = $db->query(
                'SELECT a.id, a.status AS attendance_status, a.version, a.branch_id, '
                . 'a.player_id, a.session_id, s.status AS session_status, '
                . 's.branch_id AS session_branch '
                . 'FROM attendance a '
                . 'INNER JOIN sessions s ON s.id = a.session_id '
                . 'WHERE a.id = ? LIMIT 1 FOR UPDATE',
                [$id]
            )->getRowArray();
            if (!is_array($attendance)) {
                throw new RuntimeException('Attendance not found.');
            }

            $this->assertBranchLineage($attendance);
            $actor = $this->allow((int) $attendance['session_branch'], 'attendance.correct');
            if ((int) $attendance['version'] !== $version) {
                throw new RuntimeException('Stale attendance update.');
            }
            if ($attendance['session_status'] !== 'scheduled') {
                throw new RuntimeException('Attendance is not available for this session.');
            }
            $this->eligible(
                $db,
                [
                    'id' => (int) $attendance['session_id'],
                    'branch_id' => (int) $attendance['session_branch'],
                ],
                (int) $attendance['player_id'],
                $snapshotId
            );
            $now = gmdate('Y-m-d H:i:s');
            $next = $version + 1;
            $updated = $db->table('attendance')
                ->where([
                    'id' => $id,
                    'version' => $version,
                ])
                ->update([
                    'status' => $status,
                    'version' => $next,
                    'updated_at' => $now,
                ]);

            if (! $updated) {
                throw new RuntimeException('Stale attendance update.');
            }
            $historyInserted = $db->table('attendance_history')->insert([
                'attendance_id' => $id,
                'prior_status' => $attendance['attendance_status'],
                'new_status' => $status,
                'actor_user_id' => $actor,
                'reason' => trim($reason),
                'changed_at' => $now,
                'prior_version' => $version,
                'new_version' => $next,
            ]);
            if (! $historyInserted) {
                throw new RuntimeException('Unable to correct attendance.');
            }
            service('auditPersistence')->record(
                $actor,
                'attendance_corrected',
                'attendance',
                (string) $id,
                (int) $attendance['session_branch'],
                [
                    'status' => $attendance['attendance_status'],
                    'version' => $version,
                ],
                [
                    'status' => $status,
                    'version' => $next,
                ],
                trim($reason),
                $db
            );
            $db->transComplete();
            if (! $db->transStatus()) {
                throw new RuntimeException('Unable to correct attendance.');
            }
            return (int) $attendance['session_id'];
        } catch (Throwable $e) {
            $db->transRollback();
            $db->resetTransStatus();
            throw $e;
        }
    }
    public function find(int $id): array
    {
        $model = new AttendanceModel();
        $r = $model->attendanceDetails($id);
        if (!is_array($r)) {
            throw new RuntimeException('Attendance not found.');
        }
        $this->assertBranchLineage($r);
        $this->allow((int) $r['session_branch'], 'attendance.view');
        $this->assertCoachSessionAccess($r, $this->actorId());
        $r['recorded_at_utc'] = $r['recorded_at'];
        $r['recorded_at_local'] = (new DateTimeImmutable(
            $r['recorded_at'],
            new DateTimeZone('UTC')
        ))
            ->setTimezone(new DateTimeZone(config('App')->appTimezone ?: 'Asia/Kolkata'))
            ->format('Y-m-d H:i:sP');
        $r['starts_at_local'] = $this->local((string) $r['starts_at']);
        $r['ends_at_local'] = $this->local((string) $r['ends_at']);
        $r['history'] = $model->attendanceHistory($id);
        return $r;
    }

    public function sessionIdFor(int $attendanceId): int
    {
        $row = Database::connect('default')
            ->table('attendance')
            ->select('attendance.session_id, sessions.coach_user_id, sessions.branch_id')
            ->join('sessions', 'sessions.id = attendance.session_id')
            ->where('id', $attendanceId)
            ->get()
            ->getRowArray();

        if (! is_array($row)) {
            throw new RuntimeException('Attendance not found.');
        }

        $this->assertCoachSessionAccess($row, $this->actorId());

        return (int) $row['session_id'];
    }

    public function reportingPeriods(): array
    {
        return [
            'daily' => 'Daily',
            'weekly' => 'Weekly',
            'monthly' => 'Monthly',
            'yearly' => 'Yearly',
        ];
    }

    public function metrics(string $period = 'monthly', ?string $date = null): array
    {
        [$from, $to] = $this->periodWindow($period, $date);
        $rows = $this->reportingRows($from, $to);
        $denominator = 0;
        $attended = 0;

        foreach ($rows as $row) {
            $status = strtoupper((string) ($row['attendance_status'] ?? 'ABSENT'));
            if (in_array($status, ['LEAVE', 'MEDICAL', 'RAIN_CANCELLED', 'COACH_CANCELLED'], true)) {
                continue;
            }

            $denominator++;
            if (in_array($status, ['PRESENT', 'LATE'], true)) {
                $attended++;
            }
        }

        return [
            'period' => $period,
            'from' => $from->format('Y-m-d'),
            'to' => $to->modify('-1 day')->format('Y-m-d'),
            'attended' => $attended,
            'denominator' => $denominator,
            'percentage' => $denominator > 0 ? round(($attended / $denominator) * 100, 1) : null,
        ];
    }

    public function alerts(string $period = 'monthly', ?string $date = null): array
    {
        [$from, $to] = $this->periodWindow($period, $date);
        $rows = $this->reportingRows($from, $to);
        $byPlayer = [];

        foreach ($rows as $row) {
            $playerId = (int) $row['player_id'];
            $byPlayer[$playerId] ??= [
                'player_id' => $playerId,
                'player_name' => (string) $row['player_name'],
                'branch_id' => (int) $row['branch_id'],
                'eligible' => 0,
                'attended' => 0,
                'absence_streak' => 0,
                'max_absence_streak' => 0,
                'late' => false,
            ];

            $status = strtoupper((string) ($row['attendance_status'] ?? 'ABSENT'));
            if (in_array($status, ['LEAVE', 'MEDICAL', 'RAIN_CANCELLED', 'COACH_CANCELLED'], true)) {
                continue;
            }

            $byPlayer[$playerId]['eligible']++;
            if (in_array($status, ['PRESENT', 'LATE'], true)) {
                $byPlayer[$playerId]['attended']++;
                $byPlayer[$playerId]['absence_streak'] = 0;
            } else {
                $byPlayer[$playerId]['absence_streak']++;
                $byPlayer[$playerId]['max_absence_streak'] = max(
                    $byPlayer[$playerId]['max_absence_streak'],
                    $byPlayer[$playerId]['absence_streak']
                );
            }
            if ($status === 'LATE') {
                $byPlayer[$playerId]['late'] = true;
            }
        }

        $alerts = [];
        foreach ($byPlayer as $item) {
            $itemAlerts = [];
            if ($item['eligible'] > 0 && ($item['attended'] / $item['eligible']) * 100 < 75) {
                $itemAlerts[] = 'Below 75% attendance';
            }
            if ($item['max_absence_streak'] >= 3) {
                $itemAlerts[] = 'Three consecutive absences';
            }
            if ($item['late']) {
                $itemAlerts[] = 'Late arrival recorded';
            }
            if ($itemAlerts !== []) {
                $alerts[] = [
                    'player_id' => $item['player_id'],
                    'player_name' => $item['player_name'],
                    'branch_id' => $item['branch_id'],
                    'alerts' => $itemAlerts,
                ];
            }
        }

        return $alerts;
    }

    private function reportingRows(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $branches = $this->authorizedBranchIds();
        if ($branches === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($branches), '?'));
        $coachScopeUserId = $this->strictCoachActorId();
        $coachClause = $coachScopeUserId === null ? '' : ' AND s.coach_user_id = ?';
        $sql = 'SELECT sm.player_id, pe.full_name AS player_name, s.branch_id, '
            . 's.starts_at, COALESCE(a.status, \'ABSENT\') AS attendance_status '
            . 'FROM attendance_roster_snapshots rs '
            . 'INNER JOIN attendance_roster_snapshot_members sm ON sm.snapshot_id = rs.id '
            . 'INNER JOIN sessions s ON s.id = rs.session_id AND s.branch_id = rs.branch_id '
            . 'INNER JOIN players p ON p.id = sm.player_id AND p.branch_id = s.branch_id '
            . 'INNER JOIN persons pe ON pe.id = p.person_id '
            . 'LEFT JOIN attendance a ON a.session_id = s.id AND a.player_id = sm.player_id '
            . 'AND a.branch_id = s.branch_id '
            . 'WHERE s.branch_id IN (' . $placeholders . ') '
            . 'AND s.status = ? AND s.starts_at >= ? AND s.starts_at < ? '
            . 'AND s.ends_at <= ?' . $coachClause . ' ORDER BY sm.player_id, s.starts_at';

        $parameters = array_merge(
            $branches,
            ['scheduled', $from->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
                $to->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
                gmdate('Y-m-d H:i:s')]
        );
        if ($coachScopeUserId !== null) {
            $parameters[] = $coachScopeUserId;
        }

        return Database::connect('default')->query($sql, $parameters)->getResultArray();
    }

    private function periodWindow(string $period, ?string $date): array
    {
        if (!array_key_exists($period, $this->reportingPeriods())) {
            throw new RuntimeException('Invalid attendance reporting period.');
        }

        $timezone = new DateTimeZone(config('App')->appTimezone ?: 'Asia/Kolkata');
        $anchor = $date === null || $date === ''
            ? new DateTimeImmutable('now', $timezone)
            : DateTimeImmutable::createFromFormat('!Y-m-d', $date, $timezone);
        if (!$anchor instanceof DateTimeImmutable || ($date !== null && $anchor->format('Y-m-d') !== $date)) {
            throw new RuntimeException('Invalid attendance reporting date.');
        }

        $start = match ($period) {
            'daily' => $anchor->setTime(0, 0),
            'weekly' => $anchor->modify('monday this week')->setTime(0, 0),
            'monthly' => $anchor->modify('first day of this month')->setTime(0, 0),
            'yearly' => $anchor->modify('first day of January')->setTime(0, 0),
        };
        $end = match ($period) {
            'daily' => $start->modify('+1 day'),
            'weekly' => $start->modify('+1 week'),
            'monthly' => $start->modify('+1 month'),
            'yearly' => $start->modify('+1 year'),
        };

        return [$start, $end];
    }

    private function assertBranchLineage(array $attendance): void
    {
        if ((int) $attendance['branch_id'] !== (int) $attendance['session_branch']) {
            throw new RuntimeException('Attendance branch mismatch.');
        }
    }
    private function lockSession(object $db, int $id): array
    {
        $r = $db->query('SELECT * FROM sessions WHERE id=? LIMIT 1 FOR UPDATE', [$id])->getRowArray();
        if (!is_array($r)) {
            throw new RuntimeException('Session not found.');
        }
        return $r;
    }
    private function eligible(object $db, array $s, int $p, ?int $expectedSnapshotId = null): void
    {
        $snapshot = $db->table('attendance_roster_snapshots')
            ->where('session_id', (int) $s['id'])
            ->get()
            ->getRowArray();
        if (! is_array($snapshot)) {
            throw new RuntimeException('Attendance roster snapshot required.');
        }
        if ((int) $snapshot['branch_id'] !== (int) $s['branch_id']) {
            throw new RuntimeException('Attendance roster snapshot branch mismatch.');
        }
        if ($expectedSnapshotId !== null && $expectedSnapshotId !== (int) $snapshot['id']) {
            throw new RuntimeException('Attendance roster snapshot mismatch.');
        }

        $members = $db->table('attendance_roster_snapshot_members m')
            ->select('m.player_id')
            ->join(
                'attendance_roster_snapshots s',
                's.id = m.snapshot_id AND s.session_id = ' . (int) $s['id'],
                'inner'
            )
            ->where('m.branch_id', (int) $s['branch_id'])
            ->get()
            ->getResultArray();
        $row = array_values(array_filter(
            $members,
            static fn(array $member): bool => (int) $member['player_id'] === $p
        ));
        if ($row === []) {
            throw new RuntimeException('Player is not eligible.');
        }
    }

    /**
     * Return the immutable Player membership for a Session, preserving the
     * snapshot source when one already exists.
     *
     * @return array<int, bool>
     */
    private function editableMemberIds(object $db, array $session): array
    {
        $snapshot = $db->table('attendance_roster_snapshots')
            ->where('session_id', (int) $session['id'])
            ->get()
            ->getRowArray();

        if (is_array($snapshot)) {
            if ((int) $snapshot['branch_id'] !== (int) $session['branch_id']) {
                throw new RuntimeException('Attendance roster snapshot branch mismatch.');
            }

            $rows = $db->table('attendance_roster_snapshot_members')
                ->where([
                    'snapshot_id' => (int) $snapshot['id'],
                    'branch_id' => (int) $session['branch_id'],
                ])
                ->get()
                ->getResultArray();
        } else {
            $rows = $db->table('session_players sp')
                ->select('sp.player_id')
                ->join('players p', 'p.id = sp.player_id')
                ->where([
                    'sp.session_id' => (int) $session['id'],
                    'p.branch_id' => (int) $session['branch_id'],
                ])
                ->get()
                ->getResultArray();
        }

        return array_fill_keys(
            array_map(static fn (array $row): int => (int) $row['player_id'], $rows),
            true
        );
    }

    private function eligibleSnapshot(object $db, array $session, ?int $expectedSnapshotId): void
    {
        $snapshot = $db->table('attendance_roster_snapshots')
            ->where('session_id', (int) $session['id'])
            ->get()
            ->getRowArray();
        if (! is_array($snapshot)) {
            throw new RuntimeException('Attendance roster snapshot required.');
        }
        if ((int) $snapshot['branch_id'] !== (int) $session['branch_id']) {
            throw new RuntimeException('Attendance roster snapshot branch mismatch.');
        }
        if ($expectedSnapshotId !== null && $expectedSnapshotId !== (int) $snapshot['id']) {
            throw new RuntimeException('Attendance roster snapshot mismatch.');
        }
    }
    private function timing(array $s): void
    {
        $now = time();
        $start = (new DateTimeImmutable($s['starts_at'], new DateTimeZone('UTC')))->getTimestamp();
        $end = (new DateTimeImmutable($s['ends_at'], new DateTimeZone('UTC')))->getTimestamp();
        if ($now < $start || $now > $end) {
            throw new RuntimeException('Attendance is outside the recording window.');
        }
    }
    private function status(string $s): void
    {
        if (!in_array($s, self::STATUSES, true)) {
            throw new RuntimeException('Invalid attendance status.');
        }
    }
    private function allow(int $b, string $p): int
    {
        if (!service('authorization')->can($p, (string) $b)) {
            throw new RuntimeException('Forbidden');
        }
        $u = session('user');
        if (!is_array($u) || !isset($u['id'])) {
            throw new RuntimeException('Authentication required');
        }
        return (int) $u['id'];
    }
    private function sessionFilters(array $filters): array
    {
        $query = [];

        foreach (['branch_id', 'batch_id', 'coach_user_id', 'session_id'] as $key) {
            if (($filters[$key] ?? '') === '') {
                continue;
            }
            if (! ctype_digit((string) $filters[$key]) || (int) $filters[$key] < 1) {
                throw new RuntimeException('Invalid session filter.');
            }
            $query[$key] = (int) $filters[$key];
        }

        if (($filters['status'] ?? '') !== '') {
            if (! in_array($filters['status'], ['scheduled', 'cancelled'], true)) {
                throw new RuntimeException('Invalid session filter.');
            }
            $query['status'] = (string) $filters['status'];
        }

        if (($filters['attendance_status'] ?? '') !== '') {
            if (! in_array($filters['attendance_status'], self::STATUSES, true)) {
                throw new RuntimeException('Invalid attendance status.');
            }
            $query['attendance_status'] = (string) $filters['attendance_status'];
        }

        if (($filters['player'] ?? '') !== '') {
            $player = trim((string) $filters['player']);
            if (mb_strlen($player) > 100) {
                throw new RuntimeException('Invalid session filter.');
            }
            $query['player'] = $player;
        }

        if (($filters['date'] ?? '') !== '') {
            $date = (string) $filters['date'];
            $dateFormat = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)
                ? 'Y-m-d'
                : (preg_match('/^\d{2}-\d{2}-\d{4}$/', $date) ? 'd-m-Y' : null);
            if ($dateFormat === null) {
                throw new RuntimeException('Invalid session filter.');
            }
            $dateObject = DateTimeImmutable::createFromFormat(
                '!' . $dateFormat,
                $date,
                new DateTimeZone(config('App')->appTimezone ?: 'Asia/Kolkata')
            );
            if ($dateObject === false || $dateObject->format($dateFormat) !== $date) {
                throw new RuntimeException('Invalid session filter.');
            }
            $query['date_from'] = $dateObject
                ->setTimezone(new DateTimeZone('UTC'))
                ->format('Y-m-d H:i:s');
            $query['date_to'] = $dateObject
                ->modify('+1 day')
                ->setTimezone(new DateTimeZone('UTC'))
                ->format('Y-m-d H:i:s');
        }

        return $query;
    }
    private function assertCoachSessionAccess(array $session, int $actor): void
    {
        $roles = (array) session('roles');
        if (in_array('Coach', $roles, true)
            && ! in_array('Super Admin', $roles, true)
            && ! in_array('Branch Admin', $roles, true)
            && ! service('authorization')->can('rbac.manage')
            && (int) ($session['coach_user_id'] ?? 0) !== $actor) {
            throw new RuntimeException('Forbidden');
        }
    }

    private function strictCoachActorId(): ?int
    {
        $roles = (array) session('roles');
        if (! in_array('Coach', $roles, true)
            || in_array('Super Admin', $roles, true)
            || in_array('Branch Admin', $roles, true)) {
            return null;
        }

        return $this->actorId();
    }

    private function actorId(): int
    {
        $user = session('user');
        if (! is_array($user) || ! isset($user['id'])) {
            throw new RuntimeException('Authentication required');
        }

        return (int) $user['id'];
    }

    private function pageNumber(mixed $value): int
    {
        if (! ctype_digit((string) $value) || (int) $value < 1) {
            throw new RuntimeException('Invalid session page.');
        }

        return (int) $value;
    }

    private function authorizedBranchIds(): array
    {
        $branches = service('branchContext')->authorizedBranchIds(true);

        $branchIds = [];
        foreach ($branches as $branch) {
            if (ctype_digit((string) $branch) && (int) $branch > 0
                && service('authorization')->can('attendance.view', (string) $branch)) {
                $branchIds[] = (int) $branch;
            }
        }

        $coachActor = $this->strictCoachActorId();
        if ($coachActor !== null && $branchIds !== []) {
            $assigned = Database::connect('default')
                ->table('user_branches')
                ->select('branch_id')
                ->where('user_id', $coachActor)
                ->get()
                ->getResultArray();
            $assignedIds = array_map(
                static fn(array $row): int => (int) $row['branch_id'],
                $assigned
            );
            $branchIds = array_values(array_intersect($branchIds, $assignedIds));
        }

        return array_values(array_unique($branchIds));
    }
    private function local(string $value): string
    {
        return (new DateTimeImmutable($value, new DateTimeZone('UTC')))
            ->setTimezone(new DateTimeZone(config('App')->appTimezone ?: 'Asia/Kolkata'))
            ->format('Y-m-d H:i:sP');
    }
}
