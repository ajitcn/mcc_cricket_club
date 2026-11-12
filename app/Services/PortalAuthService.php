<?php

declare(strict_types=1);

namespace App\Services;

use App\Libraries\Storage\LocalPrivateStorage;
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
            return [
                'type' => $type,
                'players' => [],
                'sessions' => [],
                'attendance' => [],
                'goals' => [],
                'session_plans' => [],
                'feedback' => [],
                'performance' => [],
                'matches' => [],
                'tournaments' => [],
                'fees' => [],
                'documents' => [],
                'consent' => [],
                'profile' => [],
                'hostel' => [],
                'medical' => [],
                'admissions' => [],
                'renewals' => [],
            ];
        }

        $hasProgramLink = $db->tableExists('programs') && $db->fieldExists('program_id', 'batches');
        $programSelect = $hasProgramLink ? ', pr.name AS program_name' : ', NULL AS program_name';
        $players = $db->table('players p')
            ->select("p.id, p.branch_id, p.status, pe.full_name, b.name AS branch_name, ba.name AS batch_name{$programSelect},
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
            ->join('batches ba', 'ba.id = pba.batch_id', 'left');
        if ($hasProgramLink) {
            $players->join('programs pr', 'pr.id = ba.program_id', 'left');
        }
        $players = $players
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

        $goals = [];
        if ($db->tableExists('player_goals')) {
            try {
                $goals = $db->table('player_goals g')
                    ->select('g.player_id, g.title, g.description, g.created_at, u.display_name AS coach_name')
                    ->join('users u', 'u.id = g.coach_user_id', 'left')
                    ->whereIn('g.player_id', $playerIds)
                    ->orderBy('g.updated_at', 'DESC')
                    ->get()
                    ->getResultArray();
            } catch (Throwable $exception) {
                log_message('warning', 'Portal goals projection unavailable: {message}', ['message' => $exception->getMessage()]);
            }
        }

        $sessionPlans = [];
        if ($db->tableExists('player_session_plans')) {
            try {
                $sessionPlans = $db->table('player_session_plans sp')
                    ->select('sp.player_id, sp.problem, sp.outcome, sp.created_at, u.display_name AS coach_name')
                    ->join('users u', 'u.id = sp.coach_user_id', 'left')
                    ->whereIn('sp.player_id', $playerIds)
                    ->orderBy('sp.created_at', 'DESC')
                    ->limit(10)
                    ->get()
                    ->getResultArray();
            } catch (Throwable $exception) {
                log_message('warning', 'Portal session-plan projection unavailable: {message}', ['message' => $exception->getMessage()]);
            }
        }

        $feedback = [];
        if ($db->tableExists('player_feedbacks')) {
            try {
                $feedback = $db->table('player_feedbacks pf')
                    ->select('pf.player_id, pf.feedback_year, pf.feedback_quarter, pf.coach_feedback, pf.next_focus_action, pf.created_at, u.display_name AS coach_name')
                    ->join('users u', 'u.id = pf.coach_user_id', 'left')
                    ->whereIn('pf.player_id', $playerIds)
                    ->orderBy('pf.feedback_year', 'DESC')
                    ->orderBy('pf.feedback_quarter', 'DESC')
                    ->limit(8)
                    ->get()
                    ->getResultArray();
            } catch (Throwable $exception) {
                log_message('warning', 'Portal feedback projection unavailable: {message}', ['message' => $exception->getMessage()]);
            }
        }
        $performance = [];
        if ($db->tableExists('performance_records')) {
            $performanceFields = $db->getFieldNames('performance_records');
            $performanceSelect = ['pr.player_id', 'pr.record_type', 'pr.recorded_on'];
            foreach (['runs', 'balls_faced', 'dismissals', 'fours', 'sixes', 'wickets', 'runs_conceded', 'legal_balls', 'catches', 'stumpings', 'run_outs', 'notes'] as $field) {
                if (in_array($field, $performanceFields, true)) {
                    $performanceSelect[] = 'pr.' . $field;
                }
            }
            $performanceQuery = $db->table('performance_records pr')
                ->select(implode(', ', $performanceSelect))
                ->whereIn('pr.player_id', $playerIds)
                ->orderBy('pr.recorded_on', 'DESC')
                ->limit(20);
            if (in_array('deleted_at', $performanceFields, true)) {
                $performanceQuery->where('pr.deleted_at IS NULL', null, false);
            }
            $performance = $performanceQuery->get()->getResultArray();
        }

        $matches = [];
        if ($db->tableExists('matches') && $db->tableExists('match_players')) {
            $matches = $db->table('matches m')
                ->select('m.id, m.opponent, m.match_type, m.status, m.result, m.score_summary, m.starts_at, b.name AS branch_name')
                ->join('match_players mp', 'mp.match_id = m.id')
                ->join('branches b', 'b.id = m.branch_id')
                ->whereIn('mp.player_id', $playerIds)
                ->orderBy('m.starts_at', 'DESC')
                ->limit(20)
                ->get()->getResultArray();
        }

        $tournaments = [];
        if ($db->tableExists('tournaments') && $db->tableExists('tournament_participants')) {
            $tournaments = $db->table('tournaments t')
                ->select('t.id, t.name, t.tournament_type, t.status, t.starts_on, t.ends_on, b.name AS branch_name')
                ->join('tournament_participants tp', 'tp.tournament_id = t.id')
                ->join('branches b', 'b.id = t.branch_id')
                ->whereIn('tp.player_id', $playerIds)
                ->orderBy('t.starts_on', 'DESC')
                ->limit(20)
                ->get()->getResultArray();
        }

        if ($tournaments === [] && $db->tableExists('tournaments')
            && $db->tableExists('tournament_fixtures') && $db->tableExists('match_players')) {
            $tournaments = $db->table('tournaments t')
                ->distinct()
                ->select('t.id, t.name, t.tournament_type, t.status, t.starts_on, t.ends_on, b.name AS branch_name')
                ->join('tournament_fixtures tf', 'tf.tournament_id = t.id')
                ->join('match_players mp', 'mp.match_id = tf.match_id')
                ->join('branches b', 'b.id = t.branch_id')
                ->whereIn('mp.player_id', $playerIds)
                ->orderBy('t.starts_on', 'DESC')
                ->limit(20)
                ->get()->getResultArray();
        }

        $fees = $this->safeProjection('fees', fn (): array => $this->portalFeeRows($db, $playerIds));
        $documents = $this->safeProjection('documents', fn (): array => $this->portalDocumentRows($db, $playerIds));
        $consent = $this->safeProjection('consent', fn (): array => $this->portalConsentRows($db, $playerIds));
        $profile = $this->safeProjection('profile', fn (): array => $this->portalProfileRows($db, $type, $playerIds));
        $hostel = $this->safeProjection('hostel', fn (): array => $this->portalHostelRows($db, $playerIds));
        $medical = $this->safeProjection('medical', fn (): array => $this->portalMedicalRows($db, $playerIds));
        $admissions = $this->safeProjection('admissions', fn (): array => $this->portalAdmissionRows($db, $playerIds));
        $renewals = $this->safeProjection('renewals', fn (): array => $this->portalRenewalRows($db, array_column($admissions, 'id')));

        return [
            'type' => $type,
            'players' => $players,
            'sessions' => $sessions,
            'attendance' => $attendance,
            'goals' => $goals,
            'session_plans' => $sessionPlans,
            'feedback' => $feedback,
            'performance' => $performance,
            'matches' => $matches,
            'tournaments' => $tournaments,
            'fees' => $fees,
            'documents' => $documents,
            'consent' => $consent,
            'profile' => $profile,
            'hostel' => $hostel,
            'medical' => $medical,
            'admissions' => $admissions,
            'renewals' => $renewals,
        ];
    }

    /** @param Closure(): list<array<string, mixed>> $projection @return list<array<string, mixed>> */
    private function safeProjection(string $name, \Closure $projection): array
    {
        try {
            return $projection();
        } catch (Throwable $exception) {
            log_message('warning', 'Portal {name} projection unavailable: {message}', [
                'name' => $name,
                'message' => $exception->getMessage(),
            ]);
            return [];
        }
    }

    /** @param list<int> $playerIds @return list<array<string, mixed>> */
    private function portalAdmissionRows(object $db, array $playerIds): array
    {
        if ($playerIds === [] || ! $db->tableExists('admissions')) {
            return [];
        }

        $fields = $db->getFieldNames('admissions');
        $hiddenFields = [
            'id', 'branch_id', 'lead_id', 'trial_id', 'admission_test_id', 'converted_player_id',
            'approved_by', 'checklist_complete', 'checklist_identity_complete', 'checklist_contact_complete',
            'checklist_admission_complete', 'checklist_branch_confirmed', 'checklist_approval_recorded',
            'student_signature', 'parent_signature', 'authorized_signature', 'documents_submitted',
        ];
        $select = ['a.id', 'a.branch_id', 'a.status', 'a.converted_player_id', 'b.name AS branch_name'];
        foreach ($fields as $field) {
            if (! in_array($field, $hiddenFields, true) && ! in_array('a.' . $field, $select, true)) {
                $select[] = 'a.' . $field;
            }
        }
        if (in_array('coach_user_id', $fields, true)) {
            $select[] = 'group_coach.display_name AS group_coach_name';
        }
        if (in_array('personal_coach_user_id', $fields, true)) {
            $select[] = 'personal_coach.display_name AS personal_coach_name';
        }
        if (in_array('batch_id', $fields, true)) {
            $select[] = 'ba.name AS batch_name';
        }

        try {
            $query = $db->table('admissions a')
                ->select(implode(', ', $select))
                ->join('branches b', 'b.id = a.branch_id', 'left')
                ->whereIn('a.converted_player_id', $playerIds)
                ->orderBy('a.id', 'DESC')
                ->limit(20);
            if (in_array('coach_user_id', $fields, true)) {
                $query->join('users group_coach', 'group_coach.id = a.coach_user_id', 'left');
            }
            if (in_array('personal_coach_user_id', $fields, true)) {
                $query->join('users personal_coach', 'personal_coach.id = a.personal_coach_user_id', 'left');
            }
            if (in_array('batch_id', $fields, true)) {
                $query->join('batches ba', 'ba.id = a.batch_id', 'left');
            }

            return $query->get()->getResultArray();
        } catch (Throwable $exception) {
            log_message('warning', 'Portal admission projection unavailable: {message}', ['message' => $exception->getMessage()]);
            return [];
        }
    }

    /** @param list<int> $admissionIds @return list<array<string, mixed>> */
    private function portalRenewalRows(object $db, array $admissionIds): array
    {
        if ($admissionIds === [] || ! $db->tableExists('admission_periods')) {
            return [];
        }

        $fields = $db->getFieldNames('admission_periods');
        $select = ['p.admission_id'];
        foreach (['period_number', 'start_date', 'end_date', 'status', 'age_group_label', 'coaching_plan_label', 'fee_plan_label', 'hostel_plan_label', 'academic_plan_label', 'created_at'] as $field) {
            if (in_array($field, $fields, true)) {
                $select[] = 'p.' . $field;
            }
        }

        try {
            return $db->table('admission_periods p')
                ->select(implode(', ', $select))
                ->whereIn('p.admission_id', $admissionIds)
                ->orderBy('p.admission_id', 'DESC')
                ->orderBy('p.period_number', 'DESC')
                ->get()
                ->getResultArray();
        } catch (Throwable $exception) {
            log_message('warning', 'Portal renewal projection unavailable: {message}', ['message' => $exception->getMessage()]);
            return [];
        }
    }

    /** @param list<int> $playerIds @return list<array<string, mixed>> */
    private function portalMedicalRows(object $db, array $playerIds): array
    {
        if ($playerIds === [] || ! $db->tableExists('documents')) {
            return [];
        }

        $rows = $db->table('documents d')
            ->select('d.player_id, d.verification_status, d.scanner_status, d.expires_on, b.name AS branch_name')
            ->join('players p', 'p.id = d.player_id')
            ->join('branches b', 'b.id = d.branch_id')
            ->whereIn('d.player_id', $playerIds)
            ->where('d.document_type', 'medical_certificate')
            ->where('d.status', 'active')
            ->where('p.status', 'active')
            ->where('b.is_active', 1)
            ->where('d.branch_id = p.branch_id', null, false)
            ->orderBy('d.created_at', 'DESC')
            ->limit(20)
            ->get()->getResultArray();

        $today = date('Y-m-d');
        foreach ($rows as &$row) {
            $expiry = trim((string) ($row['expires_on'] ?? ''));
            $row['expiry_status'] = $expiry === ''
                ? 'No expiry recorded'
                : ($expiry < $today ? 'Expired' : 'Valid');
        }
        unset($row);

        return $rows;
    }

    /** @param list<int> $playerIds @return list<array<string, mixed>> */
    private function portalHostelRows(object $db, array $playerIds): array
    {
        if ($playerIds === [] || ! $db->tableExists('admissions')) {
            return [];
        }

        return $db->table('admissions a')
            ->select('a.id, a.admission_number, a.converted_player_id, a.status, '
                . 'a.hostel_required, a.hostel_payment_plan, b.name AS branch_name')
            ->join('branches b', 'b.id = a.branch_id')
            ->whereIn('a.converted_player_id', $playerIds)
            ->whereNotIn('a.status', ['INTAKE', 'PENDING'])
            ->orderBy('a.id', 'DESC')
            ->limit(20)
            ->get()
            ->getResultArray();
    }

    /**
     * Return only the authenticated account's contact details for read-only display.
     * Profile and contact changes remain staff-approved; no portal write is exposed.
     *
     * @param list<int> $playerIds
     * @return list<array<string, mixed>>
     */
    private function portalProfileRows(object $db, string $type, array $playerIds): array
    {
        if ($type === 'student') {
            return $db->table('players p')
                ->select('p.id AS player_id, pe.full_name, pe.email, pe.phone, pe.date_of_birth, b.name AS branch_name')
                ->join('persons pe', 'pe.id = p.person_id')
                ->join('branches b', 'b.id = p.branch_id')
                ->whereIn('p.id', $playerIds)
                ->where('p.status', 'active')
                ->where('b.is_active', 1)
                ->orderBy('pe.full_name', 'ASC')
                ->get()
                ->getResultArray();
        }

        $contactPersonId = (int) session('portal_contact_person_id');
        if ($contactPersonId < 1) {
            return [];
        }

        return $db->table('player_contacts pc')
            ->select('pc.contact_person_id, cp.full_name, cp.email, cp.phone, p.id AS player_id, '
                . 'b.name AS branch_name')
            ->join('persons cp', 'cp.id = pc.contact_person_id')
            ->join('players p', 'p.id = pc.player_id')
            ->join('branches b', 'b.id = p.branch_id')
            ->where([
                'pc.contact_person_id' => $contactPersonId,
                'pc.relationship_type' => 'guardian',
                'p.status' => 'active',
                'b.is_active' => 1,
            ])
            ->whereIn('p.id', $playerIds)
            ->orderBy('cp.full_name', 'ASC')
            ->get()
            ->getResultArray();
    }

    /** @param list<int> $playerIds @return list<array<string, mixed>> */
    private function portalConsentRows(object $db, array $playerIds): array
    {
        if ($playerIds === [] || ! $db->tableExists('admissions')) {
            return [];
        }

        return $db->table('admissions a')
            ->select('a.id, a.admission_number, a.status, a.declaration_accepted, '
                . 'a.student_signature, a.parent_signature, a.authorized_signature, a.updated_at')
            ->whereIn('a.converted_player_id', $playerIds)
            ->orderBy('a.id', 'DESC')
            ->limit(10)
            ->get()->getResultArray();
    }

    /** @param list<int> $playerIds @return list<array<string, mixed>> */
    private function portalDocumentRows(object $db, array $playerIds): array
    {
        if ($playerIds === [] || ! $db->tableExists('documents')) {
            return [];
        }

        $sensitiveTypes = ['aadhaar', 'medical', 'passport', 'visa', 'internal', 'confidential'];
        $sensitiveTypeSql = implode(',', array_map(static fn (string $type): string => $db->escape($type), $sensitiveTypes));
        return $db->table('documents d')
            ->select('d.id, d.player_id, d.document_type, d.original_name, d.mime_type, d.expires_on, d.created_at, b.name AS branch_name')
            ->join('players p', 'p.id = d.player_id')
            ->join('branches b', 'b.id = d.branch_id')
            ->whereIn('d.player_id', $playerIds)
            ->where('p.status', 'active')
            ->where('d.status', 'active')
            ->where('d.verification_status', 'VERIFIED')
            ->where('d.scanner_status', 'CLEAN')
            ->where("LOWER(d.document_type) NOT IN ({$sensitiveTypeSql})", null, false)
            ->where('d.branch_id = p.branch_id', null, false)
            ->orderBy('d.created_at', 'DESC')
            ->limit(50)
            ->get()->getResultArray();
    }

    /** @param list<int> $playerIds @return list<array<string, mixed>> */
    private function portalFeeRows(object $db, array $playerIds): array
    {
        if ($playerIds === [] || ! $db->tableExists('admissions')) {
            return [];
        }

        $rows = [];
        foreach ([
            'coaching' => 'admission_coaching_installments',
            'hostel' => 'admission_hostel_installments',
        ] as $type => $table) {
            if (! $db->tableExists($table)) {
                continue;
            }

            $paymentSubquery = $db->tableExists('admission_installment_payments')
                ? ', (SELECT COALESCE(SUM(p.amount_received), 0) FROM admission_installment_payments p '
                    . 'WHERE p.installment_id = i.id AND p.admission_id = i.admission_id '
                    . 'AND p.payment_type = ' . $db->escape($type) . ') AS total_received'
                : ', 0 AS total_received';

            foreach ($db->table($table . ' i')
                ->select('i.id, i.admission_id, i.year_label, i.installment_number, i.due_date, i.amount, '
                    . 'a.admission_number, a.converted_player_id, b.name AS branch_name' . $paymentSubquery)
                ->join('admissions a', 'a.id = i.admission_id')
                ->join('branches b', 'b.id = a.branch_id')
                ->whereIn('a.converted_player_id', $playerIds)
                ->whereNotIn('a.status', ['INTAKE', 'PENDING'])
                ->where('i.due_date IS NOT NULL', null, false)
                ->orderBy('i.due_date', 'ASC')
                ->limit(100)
                ->get()->getResultArray() as $row) {
                $amount = (float) ($row['amount'] ?? 0);
                $received = (float) ($row['total_received'] ?? 0);
                $row['fee_type'] = $type;
                $row['payment_status'] = $received <= 0
                    ? 'Unpaid'
                    : ($received + 0.005 >= $amount ? 'Paid' : 'Partially paid');
                $row['latest_payment_id'] = null;
                $row['latest_receipt_number'] = null;
                $row['latest_payment_date'] = null;
                $row['latest_payment_method'] = null;
                if ($db->tableExists('admission_installment_payments')) {
                    $latestPayment = $db->table('admission_installment_payments')
                        ->select('id, receipt_number, payment_date, payment_method')
                        ->where('installment_id', (int) ($row['id'] ?? 0))
                        ->where('admission_id', (int) ($row['admission_id'] ?? 0))
                        ->where('payment_type', $type)
                        ->orderBy('id', 'DESC')
                        ->get(1)
                        ->getRowArray();
                    if (is_array($latestPayment)) {
                        $row['latest_payment_id'] = (int) ($latestPayment['id'] ?? 0);
                        $row['latest_receipt_number'] = $latestPayment['receipt_number'] ?? null;
                        $row['latest_payment_date'] = $latestPayment['payment_date'] ?? null;
                        $row['latest_payment_method'] = $latestPayment['payment_method'] ?? null;
                    }
                }
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /** @return array{payment: array<string, mixed>, installment: array<string, mixed>} */
    public function readPortalPayment(int $paymentId): array
    {
        if (! $this->isAuthenticated()) {
            throw new RuntimeException('Authentication required.');
        }

        $db = $this->storage();
        if (! $db->tableExists('admission_installment_payments')) {
            throw new RuntimeException('Payment not found.');
        }

        $type = strtolower((string) session('portal_account_type'));
        $playerIds = $type === 'student'
            ? [(int) session('portal_player_id')]
            : $this->parentPlayerIds($db, (int) session('portal_contact_person_id'));
        if ($playerIds === []) {
            throw new RuntimeException('Payment not found.');
        }

        $payment = $db->table('admission_installment_payments p')
            ->select('p.id, p.amount_received, p.payment_date, p.payment_method, p.receipt_number, '
                . 'p.reference_number, p.notes, p.payment_type, i.year_label, i.installment_number, '
                . 'i.due_date, i.amount, a.admission_number, a.converted_player_id, b.name AS branch_name')
            ->join('admissions a', 'a.id = p.admission_id')
            ->join('branches b', 'b.id = a.branch_id')
            ->join('admission_coaching_installments i', 'i.id = p.installment_id AND p.payment_type = ' . $db->escape('coaching'), 'left')
            ->where('p.id', $paymentId)
            ->where('p.payment_type', 'coaching')
            ->whereIn('a.converted_player_id', $playerIds)
            ->get(1)
            ->getRowArray();

        if (! is_array($payment)) {
            $payment = $db->table('admission_installment_payments p')
                ->select('p.id, p.amount_received, p.payment_date, p.payment_method, p.receipt_number, '
                    . 'p.reference_number, p.notes, p.payment_type, i.year_label, i.installment_number, '
                    . 'i.due_date, i.amount, a.admission_number, a.converted_player_id, b.name AS branch_name')
                ->join('admissions a', 'a.id = p.admission_id')
                ->join('branches b', 'b.id = a.branch_id')
                ->join('admission_hostel_installments i', 'i.id = p.installment_id AND p.payment_type = ' . $db->escape('hostel'), 'left')
                ->where('p.id', $paymentId)
                ->where('p.payment_type', 'hostel')
                ->whereIn('a.converted_player_id', $playerIds)
                ->get(1)
                ->getRowArray();
        }

        if (! is_array($payment)) {
            throw new RuntimeException('Payment not found.');
        }

        return [
            'payment' => $payment,
            'installment' => $payment,
        ];
    }

    /** @param array{payment: array<string, mixed>, installment: array<string, mixed>} $receipt */
    public function renderPortalPaymentReceiptPdf(array $receipt): string
    {
        return service('admissionPdf')->renderPaymentReceipt($receipt['payment']);
    }

    /**
     * Return final Player awards visible to the authenticated portal account.
     *
     * USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL. Portal visibility is read-only,
     * relationship-scoped, and excludes drafts, revoked records, and internal metadata.
     * Awards created through the current final-award workflow are stored as
     * approved until an optional publication transition is recorded, so both
     * approved and published player awards are projected here.
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

        if (! $db->tableExists('achievement_awards')
            || ! $db->tableExists('achievements')
            || ! $db->tableExists('players')
            || ! $db->tableExists('branches')) {
            return [];
        }

        $awardFields = $db->getFieldNames('achievement_awards');
        foreach (['is_archived', 'status', 'recipient_type', 'player_id', 'branch_id'] as $field) {
            if (! in_array($field, $awardFields, true)) {
                return [];
            }
        }

        return $db->table('achievement_awards aa')
            ->select('aa.id, aa.player_id, COALESCE(aa.published_at, aa.created_at) AS published_at, aa.created_at, a.title, a.category, a.description, b.name AS branch_name')
            ->join('achievements a', 'a.id = aa.achievement_id')
            ->join('players p', 'p.id = aa.player_id')
            ->join('branches b', 'b.id = aa.branch_id')
            ->whereIn('aa.status', ['approved', 'published'])
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

    /** @return array{document:array<string,mixed>, contents:string} */
    public function readPortalDocument(int $id): array
    {
        if (! $this->isAuthenticated() || $id < 1) {
            throw new RuntimeException('Not found.');
        }

        $db = $this->storage();
        $type = strtolower((string) session('portal_account_type'));
        $playerIds = $type === 'student'
            ? [(int) session('portal_player_id')]
            : $this->parentPlayerIds($db, (int) session('portal_contact_person_id'));
        $sensitiveTypeSql = implode(',', array_map(
            static fn (string $type): string => $db->escape($type),
            ['aadhaar', 'medical', 'passport', 'visa', 'internal', 'confidential'],
        ));
        $document = $db->table('documents d')
            ->select('d.*')
            ->join('players p', 'p.id = d.player_id')
            ->join('branches b', 'b.id = d.branch_id')
            ->where('d.id', $id)
            ->whereIn('d.player_id', $playerIds)
            ->where('p.status', 'active')
            ->where('b.is_active', 1)
            ->where('d.status', 'active')
            ->where('d.verification_status', 'VERIFIED')
            ->where('d.scanner_status', 'CLEAN')
            ->where("LOWER(d.document_type) NOT IN ({$sensitiveTypeSql})", null, false)
            ->where('d.branch_id = p.branch_id', null, false)
            ->get()->getRowArray();

        if (! is_array($document)) {
            throw new RuntimeException('Not found.');
        }

        try {
            $contents = (new LocalPrivateStorage(WRITEPATH . 'private'))->read((string) $document['storage_key']);
        } catch (\Throwable) {
            throw new RuntimeException('Not found.');
        }

        return ['document' => $document, 'contents' => $contents];
    }

    public function createSupportRequest(array $input): int
    {
        if (! $this->isAuthenticated()) {
            throw new RuntimeException('Authentication required.');
        }

        $category = trim((string) ($input['category'] ?? ''));
        $subject = trim((string) ($input['subject'] ?? ''));
        $message = trim((string) ($input['message'] ?? ''));
        $allowedCategories = ['academy', 'fees', 'schedule', 'coach', 'documents', 'profile', 'other'];
        if (! in_array($category, $allowedCategories, true) || $subject === '' || $message === '') {
            throw new RuntimeException('Enter a category, subject, and message.');
        }
        if (strlen($subject) > 180 || strlen($message) > 5000) {
            throw new RuntimeException('The support request is too long.');
        }

        $db = $this->storage();
        $type = strtolower((string) session('portal_account_type'));
        $playerIds = $type === 'student'
            ? [(int) session('portal_player_id')]
            : $this->parentPlayerIds($db, (int) session('portal_contact_person_id'));
        $playerId = (int) ($input['player_id'] ?? ($playerIds[0] ?? 0));
        if (! in_array($playerId, $playerIds, true)) {
            throw new RuntimeException('Invalid linked Player.');
        }
        $player = $db->table('players')->select('id, branch_id')->where('id', $playerId)->where('status', 'active')->get()->getRowArray();
        if (! is_array($player)) {
            throw new RuntimeException('Invalid linked Player.');
        }

        $now = gmdate('Y-m-d H:i:s');
        $db->table('portal_support_requests')->insert([
            'player_id' => $playerId,
            'branch_id' => (int) $player['branch_id'],
            'account_type' => $type,
            'category' => $category,
            'subject' => $subject,
            'message' => $message,
            'status' => 'open',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $id = (int) $db->insertID();
        service('auditPersistence')->record(null, 'portal_support_request_created', 'portal_support_request', (string) $id, (int) $player['branch_id'], null, ['player_id' => $playerId, 'category' => $category]);

        return $id;
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
