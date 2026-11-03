<?php

declare(strict_types=1);

namespace App\Services;

use Config\Database;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

final class PortalAuthService
{
    private const OTP_TTL_SECONDS = 300;
    private const MAX_OTP_ATTEMPTS = 5;
    private const RESEND_COOLDOWN_SECONDS = 60;

    public function __construct(
        private readonly WhatsAppOtpProviderInterface $provider,
        private readonly PortalOtpDebugService $debug,
    )
    {
    }

    public function requestOtp(string $type, string $mobile): ?string
    {
        $type = $this->accountType($type);
        $mobile = $this->normalizeMobile($mobile);
        $db = $this->storage();
        session()->remove('portal_otp_pending');
        $eligible = $this->eligibleIdentity($db, $type, $mobile) !== null;
        $sessionHash = $this->sessionHash();
        $now = time();
        $nowSql = gmdate('Y-m-d H:i:s', $now);

        $recent = $db->table('portal_otp_challenges')
            ->where(['session_hash' => $sessionHash, 'account_type' => $type])
            ->where('requested_at >=', gmdate('Y-m-d H:i:s', $now - self::RESEND_COOLDOWN_SECONDS))
            ->countAllResults();
        if ($recent > 0) {
            throw new RuntimeException('PORTAL_OTP_COOLDOWN');
        }

        $db->table('portal_otp_challenges')
            ->where(['session_hash' => $sessionHash, 'account_type' => $type])
            ->where('consumed_at IS NULL', null, false)
            ->update(['consumed_at' => $nowSql, 'provider_status' => 'invalidated']);

        if (! $eligible) {
            service('auditPersistence')->record(null, 'portal_otp_requested', 'portal_auth', $type, null, null, ['eligible' => false]);
            return null;
        }

        $otp = (string) random_int(100000, 999999);
        $db->table('portal_otp_challenges')->insert([
            'account_type' => $type,
            'mobile_hash' => hash('sha256', $mobile),
            'session_hash' => $sessionHash,
            'otp_hash' => password_hash($otp, PASSWORD_DEFAULT),
            'expires_at' => gmdate('Y-m-d H:i:s', $now + self::OTP_TTL_SECONDS),
            'attempt_count' => 0,
            'requested_at' => $nowSql,
            'purpose' => 'portal_login',
            'provider_status' => 'pending',
            'created_at' => $nowSql,
        ]);
        $challengeId = (int) $db->insertID();

        if (! $this->provider->sendOtp($mobile, $otp)) {
            $db->table('portal_otp_challenges')->where('id', $challengeId)->update([
                'provider_status' => 'failed',
                'consumed_at' => $nowSql,
            ]);
            throw new PortalOtpDeliveryException('PORTAL_OTP_DELIVERY_FAILED');
        }

        $db->table('portal_otp_challenges')->where('id', $challengeId)->update(['provider_status' => 'sent']);
        session()->set('portal_otp_pending', true);
        service('auditPersistence')->record(null, 'portal_otp_requested', 'portal_auth', $type, null, null, ['eligible' => true]);

        return $this->debug->canDisplay($db) ? $otp : null;
    }

    public function verifyOtp(string $type, string $mobile, string $otp): bool
    {
        $type = $this->accountType($type);
        $mobile = $this->normalizeMobile($mobile);
        $db = $this->storage();
        $challenge = $db->table('portal_otp_challenges')
            ->where([
                'account_type' => $type,
                'mobile_hash' => hash('sha256', $mobile),
                'session_hash' => $this->sessionHash(),
                'purpose' => 'portal_login',
            ])
            ->where('consumed_at IS NULL', null, false)
            ->orderBy('requested_at', 'DESC')
            ->get(1)->getRowArray();

        if (! is_array($challenge)) {
            $this->recordFailedVerification($type, 'missing');
            return false;
        }

        if ((int) $challenge['attempt_count'] >= self::MAX_OTP_ATTEMPTS || $this->challengeExpired((string) $challenge['expires_at'])) {
            $db->table('portal_otp_challenges')->where('id', (int) $challenge['id'])->update([
                'consumed_at' => gmdate('Y-m-d H:i:s'),
                'provider_status' => 'expired',
            ]);
            session()->remove('portal_otp_pending');
            $this->recordFailedVerification($type, 'expired');
            return false;
        }

        $attempts = (int) $challenge['attempt_count'] + 1;
        $otpMatches = password_verify(trim($otp), (string) $challenge['otp_hash']);
        if (! $otpMatches) {
            $db->table('portal_otp_challenges')->where('id', (int) $challenge['id'])->update([
                'attempt_count' => $attempts,
                'consumed_at' => $attempts >= self::MAX_OTP_ATTEMPTS ? gmdate('Y-m-d H:i:s') : null,
            ]);
            $this->recordFailedVerification($type, 'invalid');
            return false;
        }

        $identity = $this->eligibleIdentity($db, $type, $mobile);
        if ($identity === null) {
            $this->recordFailedVerification($type, 'ineligible');
            return false;
        }

        $db->table('portal_otp_challenges')->where('id', (int) $challenge['id'])->update([
            'attempt_count' => $attempts,
            'consumed_at' => gmdate('Y-m-d H:i:s'),
            'provider_status' => 'consumed',
        ]);
        session()->regenerate(true);
        session()->set([
            'portal_authenticated' => true,
            'portal_account_type' => strtolower($type),
            'portal_player_id' => $identity['player_id'],
            'portal_contact_person_id' => $identity['contact_person_id'],
        ]);
        service('auditPersistence')->record(null, 'portal_otp_verified', 'portal_auth', $type, null, null, ['success' => true]);

        return true;
    }

    public function hasPendingOtp(?string $type, ?string $mobile): bool
    {
        if ($type === null || $mobile === null || trim($type) === '' || trim($mobile) === '') {
            return false;
        }

        try {
            $type = $this->accountType($type);
            $mobile = $this->normalizeMobile($mobile);
            $challenge = $this->storage()->table('portal_otp_challenges')
                ->where([
                    'account_type' => $type,
                    'mobile_hash' => hash('sha256', $mobile),
                    'session_hash' => $this->sessionHash(),
                    'purpose' => 'portal_login',
                    'provider_status' => 'sent',
                ])
                ->where('consumed_at IS NULL', null, false)
                ->orderBy('requested_at', 'DESC')
                ->get(1)
                ->getRowArray();

            return is_array($challenge)
                && ! $this->challengeExpired((string) $challenge['expires_at']);
        } catch (RuntimeException) {
            return false;
        }
    }

    public function isAuthenticated(): bool
    {
        if (session('portal_authenticated') !== true) {
            return false;
        }

        $type = strtoupper((string) session('portal_account_type'));
        $db = $this->storage();
        if ($type === 'STUDENT') {
            return $this->activePlayer($db, (int) session('portal_player_id'));
        }
        return $type === 'PARENT' && $this->activeGuardian($db, (int) session('portal_contact_person_id'));
    }

    public function logout(): void
    {
        session()->remove([
            'portal_authenticated', 'portal_account_type',
            'portal_player_id', 'portal_contact_person_id', 'portal_otp_pending',
        ]);
        session()->regenerate(true);
    }

    /** @return array<string, mixed> */
    public function dashboard(): array
    {
        if (! $this->isAuthenticated()) {
            throw new RuntimeException('Authentication required.');
        }

        $type = strtolower((string) session('portal_account_type'));
        $db = $this->storage();
        $playerIds = $type === 'student'
            ? [(int) session('portal_player_id')]
            : $this->parentPlayerIds($db, (int) session('portal_contact_person_id'));
        if ($playerIds === []) {
            return ['type' => $type, 'players' => [], 'sessions' => [], 'attendance' => []];
        }

        $players = $db->table('players p')
            ->select("p.id, p.branch_id, p.status, pe.full_name, b.name AS branch_name, ba.name AS batch_name, pr.name AS program_name,
                (SELECT u.display_name
                    FROM admissions a
                    JOIN users u ON u.id = a.coach_user_id
                    WHERE a.converted_player_id = p.id
                    ORDER BY a.id DESC
                    LIMIT 1) AS group_coach_name,
                (SELECT u.display_name
                    FROM admissions a
                    JOIN users u ON u.id = a.personal_coach_user_id
                    WHERE a.converted_player_id = p.id
                    ORDER BY a.id DESC
                    LIMIT 1) AS personal_coach_name", false)
            ->join('persons pe', 'pe.id = p.person_id')->join('branches b', 'b.id = p.branch_id')
            ->join('player_batch_assignments pba', 'pba.player_id = p.id AND pba.effective_to IS NULL', 'left')
            ->join('batches ba', 'ba.id = pba.batch_id', 'left')->join('programs pr', 'pr.id = ba.program_id', 'left')
            ->whereIn('p.id', $playerIds)->where('p.status', 'active')->get()->getResultArray();
        $sessions = $db->table('session_players sp')
            ->select('s.id, s.starts_at, s.ends_at, s.status, b.name AS branch_name, ba.name AS batch_name')
            ->join('sessions s', 's.id = sp.session_id')->join('branches b', 'b.id = s.branch_id')
            ->join('batches ba', 'ba.id = s.batch_id', 'left')->whereIn('sp.player_id', $playerIds)
            ->where('s.status', 'scheduled')->orderBy('s.starts_at', 'ASC')->limit(20)->get()->getResultArray();
        $attendance = $db->table('attendance a')
            ->select('a.player_id, a.status, a.recorded_at, s.starts_at')
            ->join('sessions s', 's.id = a.session_id AND s.branch_id = a.branch_id')
            ->whereIn('a.player_id', $playerIds)->orderBy('a.recorded_at', 'DESC')->limit(50)->get()->getResultArray();

        $goals = $db->tableExists('player_goals')
            ? $db->table('player_goals g')
                ->select('g.player_id, g.title, g.description, g.created_at, u.display_name AS coach_name')
                ->join('users u', 'u.id = g.coach_user_id', 'left')
                ->whereIn('g.player_id', $playerIds)
                ->orderBy('g.updated_at', 'DESC')
                ->get()->getResultArray()
            : [];
        $sessionPlans = $db->tableExists('player_session_plans')
            ? $db->table('player_session_plans sp')
                ->select('sp.player_id, sp.problem, sp.outcome, sp.created_at, u.display_name AS coach_name')
                ->join('users u', 'u.id = sp.coach_user_id', 'left')
                ->whereIn('sp.player_id', $playerIds)
                ->orderBy('sp.created_at', 'DESC')
                ->limit(10)
                ->get()->getResultArray()
            : [];
        $feedback = $db->tableExists('player_feedbacks')
            ? $db->table('player_feedbacks pf')
                ->select('pf.player_id, pf.feedback_year, pf.feedback_quarter, pf.coach_feedback, pf.next_focus_action, pf.created_at, u.display_name AS coach_name')
                ->join('users u', 'u.id = pf.coach_user_id', 'left')
                ->whereIn('pf.player_id', $playerIds)
                ->orderBy('pf.feedback_year', 'DESC')
                ->orderBy('pf.feedback_quarter', 'DESC')
                ->limit(8)
                ->get()->getResultArray()
            : [];
        $performance = $db->tableExists('performance_records')
            ? $db->table('performance_records pr')
                ->select('pr.player_id, pr.record_type, pr.recorded_on, pr.runs, pr.wickets, pr.catches, pr.notes')
                ->whereIn('pr.player_id', $playerIds)
                ->where('pr.deleted_at IS NULL', null, false)
                ->orderBy('pr.recorded_on', 'DESC')
                ->limit(20)
                ->get()->getResultArray()
            : [];

        return [
            'type' => $type,
            'players' => $players,
            'sessions' => $sessions,
            'attendance' => $attendance,
            'goals' => $goals,
            'session_plans' => $sessionPlans,
            'feedback' => $feedback,
            'performance' => $performance,
        ];
    }

    /**
     * Return only published Player awards visible to the authenticated portal account.
     *
     * USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL. Portal visibility is read-only,
     * relationship-scoped, and excludes drafts, revoked records, and internal metadata.
     *
     * @return list<array<string, mixed>>
     */
    public function publishedAchievements(): array
    {
        if (! $this->isAuthenticated()) {
            throw new RuntimeException('Authentication required.');
        }

        $db = $this->storage();
        $type = strtolower((string) session('portal_account_type'));
        $playerIds = $type === 'student'
            ? [(int) session('portal_player_id')]
            : $this->parentPlayerIds($db, (int) session('portal_contact_person_id'));

        if ($playerIds === []) {
            return [];
        }

        return $db->table('achievement_awards aa')
            ->select('aa.id, aa.player_id, a.title, a.category, a.description, b.name AS branch_name')
            ->join('achievements a', 'a.id = aa.achievement_id')
            ->join('players p', 'p.id = aa.player_id')
            ->join('branches b', 'b.id = aa.branch_id')
            ->where('aa.status', 'published')
            ->where('aa.is_archived', 0)
            ->where('aa.recipient_type', 'player')
            ->whereIn('aa.player_id', $playerIds)
            ->where('p.status', 'active')
            ->where('b.is_active', 1)
            ->where('aa.branch_id = p.branch_id', null, false)
            ->orderBy('a.title', 'ASC')
            ->orderBy('aa.id', 'DESC')
            ->get()
            ->getResultArray();
    }

    private function storage(): object
    {
        $db = Database::connect('default');
        if (! $db->tableExists('portal_otp_challenges') || ! $db->fieldExists('mobile_verified_at', 'persons')) {
            log_message('error', 'Portal OTP authentication is unavailable because its migration is not applied.');
            throw new RuntimeException('PORTAL_OTP_STORAGE_UNAVAILABLE');
        }
        return $db;
    }

    private function accountType(string $type): string
    {
        $type = strtoupper(trim($type));
        if (! in_array($type, ['STUDENT', 'PARENT'], true)) {
            throw new RuntimeException('Invalid portal login type.');
        }
        return $type;
    }

    private function normalizeMobile(string $mobile): string
    {
        $normalized = preg_replace('/[^0-9+]/', '', trim($mobile)) ?? '';
        if (str_starts_with($normalized, '00')) {
            $normalized = '+' . substr($normalized, 2);
        }
        $digits = ltrim($normalized, '+');
        if ($digits === '' || strlen($digits) < 7 || strlen($digits) > 15) {
            throw new RuntimeException('Invalid mobile number.');
        }
        return '+' . $digits;
    }

    private function sessionHash(): string
    {
        session();

        return hash('sha256', (string) session_id());
    }

    /** @return array{player_id:?int, contact_person_id:?int}|null */
    private function eligibleIdentity(object $db, string $type, string $mobile): ?array
    {
        if ($type === 'STUDENT') {
            $rows = $db->table('players p')->select('p.id, pe.phone')
                ->join('persons pe', 'pe.id = p.person_id')->join('branches b', 'b.id = p.branch_id')
                ->where(['p.status' => 'active', 'b.is_active' => 1])
                ->where('pe.mobile_verified_at IS NOT NULL', null, false)->get()->getResultArray();
            foreach ($rows as $row) {
                try {
                    if ($this->normalizeMobile((string) $row['phone']) === $mobile) {
                        return ['player_id' => (int) $row['id'], 'contact_person_id' => null];
                    }
                } catch (RuntimeException) {
                    continue;
                }
            }
            return null;
        }

        $rows = $db->table('player_contacts pc')->select('pc.contact_person_id, cp.phone')
            ->join('persons cp', 'cp.id = pc.contact_person_id')->join('players p', 'p.id = pc.player_id')
            ->join('branches b', 'b.id = p.branch_id')
            ->where(['pc.relationship_type' => 'guardian', 'p.status' => 'active', 'b.is_active' => 1])
            ->where('cp.mobile_verified_at IS NOT NULL', null, false)->get()->getResultArray();
        foreach ($rows as $row) {
            try {
                if ($this->normalizeMobile((string) $row['phone']) === $mobile) {
                    return ['player_id' => null, 'contact_person_id' => (int) $row['contact_person_id']];
                }
            } catch (RuntimeException) {
                continue;
            }
        }
        return null;
    }

    private function activePlayer(object $db, int $id): bool
    {
        return $db->table('players p')->join('branches b', 'b.id = p.branch_id')
            ->where(['p.id' => $id, 'p.status' => 'active', 'b.is_active' => 1])->countAllResults() === 1;
    }

    private function activeGuardian(object $db, int $id): bool
    {
        return $db->table('player_contacts pc')->join('players p', 'p.id = pc.player_id')
            ->join('branches b', 'b.id = p.branch_id')->where([
                'pc.contact_person_id' => $id, 'pc.relationship_type' => 'guardian',
                'p.status' => 'active', 'b.is_active' => 1,
            ])->countAllResults() > 0;
    }

    /** @return list<int> */
    private function parentPlayerIds(object $db, int $contactPersonId): array
    {
        $rows = $db->table('player_contacts pc')->select('pc.player_id')
            ->join('players p', 'p.id = pc.player_id')->join('branches b', 'b.id = p.branch_id')
            ->where(['pc.contact_person_id' => $contactPersonId, 'pc.relationship_type' => 'guardian', 'p.status' => 'active', 'b.is_active' => 1])
            ->get()->getResultArray();
        return array_values(array_map(static fn (array $row): int => (int) $row['player_id'], $rows));
    }

    private function recordFailedVerification(string $type, string $reason): void
    {
        service('auditPersistence')->record(null, 'portal_otp_failed', 'portal_auth', $type, null, null, [
            'reason' => $reason, 'success' => false,
        ]);
    }

    private function challengeExpired(string $expiresAt): bool
    {
        $expires = DateTimeImmutable::createFromFormat(
            '!Y-m-d H:i:s',
            $expiresAt,
            new DateTimeZone('UTC')
        );

        return $expires === false || $expires->getTimestamp() < time();
    }
}
