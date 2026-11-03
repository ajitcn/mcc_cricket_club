<?php

declare (strict_types=1);
namespace App\Services;

use App\Modules\Admissions\Models\AdmissionModel;
use Config\Database;
use RuntimeException;
use Throwable;
final class AdmissionService
{
    private TrialRatingService $ratingService;

    public function __construct()
    {
        $this->ratingService = new TrialRatingService();
    }

    private const INTAKE = 'INTAKE';
    private const IN_REVIEW = 'IN_REVIEW';
    private const DOCUMENTS_PENDING = 'DOCUMENTS_PENDING';
    private const APPROVED = 'APPROVED';
    private const REJECTED = 'REJECTED';
    private const WITHDRAWN = 'WITHDRAWN';
    private const CONVERTED = 'CONVERTED';
    private const COMPLETED = 'COMPLETED';
    /** @var array<string, list<string>> */
    private const TRANSITIONS = [
        self::INTAKE => [
            self::IN_REVIEW,
            self::DOCUMENTS_PENDING,
            self::APPROVED,
            self::REJECTED,
            self::WITHDRAWN,
        ],
        self::IN_REVIEW => [
            self::DOCUMENTS_PENDING,
            self::APPROVED,
            self::REJECTED,
            self::WITHDRAWN,
        ],
        self::DOCUMENTS_PENDING => [
            self::IN_REVIEW,
            self::APPROVED,
            self::REJECTED,
            self::WITHDRAWN,
        ],
        self::APPROVED => [self::CONVERTED, self::WITHDRAWN],
        self::REJECTED => [self::IN_REVIEW],
        self::WITHDRAWN => [self::IN_REVIEW, self::DOCUMENTS_PENDING, self::APPROVED],
        self::CONVERTED => [],
    ];
    /** @var list<string> */
    private const CHECKLIST_FIELDS = ['checklist_identity_complete', 'checklist_contact_complete', 'checklist_admission_complete',
        'checklist_branch_confirmed'];
    /** @var array<string, list<string>> */
    private const PROFILE_OPTIONS = [
        'gender' => ['Male', 'Female', 'Other', 'Prefer Not to Say'],
        'playing_role' => ['Batter', 'Bowler', 'All-Rounder', 'Wicketkeeper-Batter'],
        'batting_style' => ['Right-Handed', 'Left-Handed'],
        'bowling_style' => [
            'Right-Arm Fast', 'Right-Arm Fast-Medium', 'Right-Arm Medium-Fast', 'Right-Arm Medium',
            'Right-Arm Medium-Slow', 'Right-Arm Off-Break', 'Right-Arm Leg-Break', 'Right-Arm Leg-Break Googly',
            'Left-Arm Fast', 'Left-Arm Fast-Medium', 'Left-Arm Medium-Fast', 'Left-Arm Medium',
            'Left-Arm Medium-Slow', 'Left-Arm Orthodox', 'Left-Arm Chinaman', 'Does Not Bowl',
        ],
        'strong_hand' => ['Right', 'Left', 'Ambidextrous'],
    ];
    /** @var array<string, list<string>> */
    private const PDF_ABILITY_OPTIONS = [
        'batting_order' => ['Opening', '2nd', '3rd', 'Middle'],
    ];
    private const RATING_FIELDS = TrialRatingService::FIELDS;
    /** @var array<string, list<string>> */
    private const PDF_PLAN_OPTIONS = [
        'personal_coaching_days' => ['1 Day', '2 Days', '3 Days', '4 Days', '5 Days'],
        'personal_coaching_duration' => ['1 hour', '1.5 hours', '2 hours'],
        'group_coaching_days' => ['2 Days', '3 Days', '4 Days', '6 Days'],
        'induction_plan' => ['Yes', 'No'],
        'review_frequency' => ['3 months', '6 months'],
        'progress_report_frequency' => ['3 months', '6 months'],
        'feedback_call_frequency' => ['3 months', '6 months'],
        'meditation_sessions' => ['Weekly once'],
        'elite_camp' => ['Yes', 'No'],
        // Reuses the approved personal-coaching day and duration vocabulary.
        'elite_camp_days' => ['1 Day', '2 Days', '3 Days', '4 Days', '5 Days'],
        'elite_camp_duration' => ['1 hour', '1.5 hours', '2 hours'],
        'student_diary' => ['Yes', 'No'],
    ];
    /** @var list<string> */
    private const COACHING_TYPE_OPTIONS = ['Personal coaching', 'Group coaching', 'Elite'];
    /** @var array<string, list<string>> */
    private const PDF_MULTI_PLAN_OPTIONS = [
        'coaching_delivery' => ['Indoor', 'Outdoor', 'Indoor+Outdoor'],
        'fitness_plan' => ['Group Strengthening', 'Fitness on Ground'],
        'gym_fitness_session' => ['Strengthening', 'Fitness session at GYM (Personal session)'],
        'match_program' => ['Match Simulation', 'Practice Match', 'Tournaments (as per availability and performance)'],
        'welcome_kit' => ['1 Whites', '2 Whites', '3 Whites', '1 Colored', '2 Colored', '1 Cap', '1 Kit Bag'],
    ];
    /** @var list<string> */
    private const PAYMENT_PLAN_OPTIONS = ['Full Payment', 'Instalment Plan'];
    private const INSTALLMENT_TABLES = [
        'coaching' => 'admission_coaching_installments',
        'hostel' => 'admission_hostel_installments',
    ];
    /** @return list<string> */
    public function statuses(): array
    {
        return [self::INTAKE, self::IN_REVIEW, self::DOCUMENTS_PENDING, self::APPROVED, self::REJECTED, self::WITHDRAWN, self::CONVERTED];
    }
    /** @return list<array{value: string, label: string}> */
    public function statusOptions(): array
    {
        $labels = [
            self::INTAKE => 'Pending Admission',
            self::IN_REVIEW => 'In Review',
            self::DOCUMENTS_PENDING => 'Documents Pending',
            self::APPROVED => 'Completed',
            self::REJECTED => 'Rejected',
            self::WITHDRAWN => 'Withdrawn',
            self::CONVERTED => 'Converted',
        ];

        return array_map(
            static fn (string $status): array => [
                'value' => $status,
                'label' => $labels[$status],
            ],
            $this->statuses(),
        );
    }
    public function assertValidStatus(string $status): void
    {
        if (! in_array(strtoupper(trim($status)), $this->statuses(), true)) {
            throw new RuntimeException('Invalid status.');
        }
    }
    public function calculateFeeTotal(array $record): string
    {
        $coachingCents = $this->moneyToCents($record['training_fee'] ?? null);
        $hostelCents = $this->moneyToCents($record['hostel_charges'] ?? null);

        return $this->centsToMoney($coachingCents + $hostelCents);
    }

    /** @return array{total_taxable_fee: string, tax_rate: ?string, calculated_tax_amount: string, total_including_tax: string} */
    public function calculateTaxInformation(array $record): array
    {
        $feeTotal = $this->calculateFeeTotal($record);
        $taxableFee = trim((string) ($record['total_taxable_fee'] ?? ''));
        $taxableFee = $taxableFee === '' ? $feeTotal : $this->normalizeMoney($taxableFee);
        $taxRate = trim((string) ($record['tax_rate'] ?? ''));

        if ($taxRate === '') {
            return [
                'total_taxable_fee' => $taxableFee,
                'tax_rate' => null,
                'calculated_tax_amount' => '0.00',
                'total_including_tax' => $feeTotal,
            ];
        }

        $taxRate = $this->normalizeTaxRate($taxRate);
        $taxAmount = (int) round(
            $this->moneyToCents($taxableFee) * ((float) $taxRate) / 100,
        );

        return [
            'total_taxable_fee' => $taxableFee,
            'tax_rate' => $taxRate,
            'calculated_tax_amount' => $this->centsToMoney($taxAmount),
            'total_including_tax' => $this->centsToMoney(
                $this->moneyToCents($feeTotal) + $taxAmount,
            ),
        ];
    }
    /** @return list<string> */
    public function allowedTransitions(string $status): array
    {
        return self::TRANSITIONS[$status] ?? [];
    }
    /** Lead options are restricted to authorized branches and leads not already converted. */
    public function eligibleLeads(?int $selectedLeadId = null): array
    {
        $branches = service('branchContext')->authorizedBranchIds(true);
        if ($branches === []) {
            return [];
        }
        $query = Database::connect('default')->table('leads l')
            ->select('l.id, l.full_name, l.email, l.phone, l.branch_id, b.name AS branch_name')
            ->join('branches b', 'b.id = l.branch_id')
            ->whereIn('l.branch_id', $branches)
            ->groupStart()
                ->whereIn('l.status', ['lead', 'admission pending']);

        if (($selectedLeadId ?? 0) > 0) {
            $query->orWhere('l.id', $selectedLeadId);
        }

        return $query->groupEnd()
            ->where("NOT EXISTS (SELECT 1 FROM admissions ax WHERE ax.lead_id = l.id AND (ax.status = 'CONVERTED' OR ax.converted_player_id IS NOT NULL) AND ax.lead_id != " . (int) ($selectedLeadId ?? 0) . ")", null, false)
            ->orderBy('l.full_name')
            ->get()
            ->getResultArray();
    }
    public function availableBatches(): array
    {
        return Database::connect('default')->table('batches')
            ->where('is_active', 1)
            ->orderBy('name')
            ->get()
            ->getResultArray();
    }
    /** @return array<string, list<string>> */
    public function profileOptions(): array
    {
        return self::PROFILE_OPTIONS;
    }
    /** @return list<array<string, mixed>> */
    public function batchesForBranch(int $branch, ?int $selectedBatchId = null): array
    {
        if ($branch < 1 || !service('authorization')->can('admission.view', (string) $branch)) {
            throw new RuntimeException('Forbidden');
        }
        $query = Database::connect('default')->table('batches')
            ->groupStart()
                ->where('is_active', 1);

        if (($selectedBatchId ?? 0) > 0) {
            $query->orWhere('id', $selectedBatchId);
        }

        return $query->groupEnd()
            ->orderBy('name')
            ->get()
            ->getResultArray();
    }
    public function availableTrials(): array
    {
        $branches = service('branchContext')->authorizedBranchIds(true);
        if ($branches === []) {
            return [];
        }
        return Database::connect('default')->table('trials t')->select('t.id,t.trial_date,t.trial_time,t.lead_id,t.ability_overall,t.ability_batting,t.ability_bowling,t.ability_wicketkeeping,t.ability_fitness,l.full_name AS lead_name')
            ->join('leads l', 'l.id=t.lead_id', 'left')->whereIn('t.branch_id', $branches)->orderBy('t.trial_date', 'DESC')->get()->getResultArray();
    }

    /** @return list<array<string, mixed>> */
    public function trialsForBranch(int $branch): array
    {
        if ($branch < 1 || ! service('authorization')->can('admission.view', (string) $branch)) {
            throw new RuntimeException('Forbidden');
        }

        return Database::connect('default')->table('trials t')
            ->select('t.id, t.trial_date, t.trial_time, t.lead_id, t.ability_overall, t.ability_batting, t.ability_bowling, t.ability_wicketkeeping, t.ability_fitness, l.full_name AS lead_name')
            ->join('leads l', 'l.id = t.lead_id', 'left')
            ->where('t.branch_id', $branch)
            ->orderBy('t.trial_date', 'DESC')
            ->get()
            ->getResultArray();
    }

    /** @return list<array<string, mixed>> */
    public function coachesForAuthorizedBranches(): array
    {
        $branches = service('branchContext')->authorizedBranchIds(true);
        if ($branches === []) {
            return [];
        }

        return Database::connect('default')->table('users u')
            ->select('u.id, u.display_name, ub.branch_id, b.name AS branch_name')
            ->join('user_roles ur', 'ur.user_id = u.id')
            ->join('roles r', 'r.id = ur.role_id')
            ->join('user_branches ub', 'ub.user_id = u.id')
            ->join('branches b', 'b.id = ub.branch_id')
            ->join('coach_profiles cp', 'cp.user_id = u.id')
            ->whereIn('r.name', ['Coach', 'Fitness Coach'])
            ->where('u.is_enabled', 1)
            ->where('cp.is_active', 1)
            ->whereIn('ub.branch_id', $branches)
            ->groupBy(['u.id', 'u.display_name', 'ub.branch_id', 'b.name'])
            ->orderBy('u.display_name', 'ASC')
            ->get()
            ->getResultArray();
    }

    /** @return list<array<string, mixed>> */
    public function coachesForBranch(
        int $branch,
        ?int $selectedCoachId = null,
        ?int $selectedPersonalCoachId = null,
    ): array
    {
        if ($branch < 1 || ! service('authorization')->can('admission.view', (string) $branch)) {
            throw new RuntimeException('Forbidden');
        }

        $coaches = array_values(array_filter(
            $this->coachesForAuthorizedBranches(),
            static fn (array $coach): bool => (int) ($coach['branch_id'] ?? 0) === $branch,
        ));

        $selectedIds = array_values(array_unique(array_filter([
            (int) ($selectedCoachId ?? 0),
            (int) ($selectedPersonalCoachId ?? 0),
        ], static fn (int $id): bool => $id > 0)));
        $loadedIds = array_map(static fn (array $coach): int => (int) $coach['id'], $coaches);

        foreach ($selectedIds as $selectedId) {
            if (in_array($selectedId, $loadedIds, true)) {
                continue;
            }

            $historical = Database::connect('default')->table('users u')
                ->select('u.id, u.display_name, ub.branch_id, b.name AS branch_name')
                ->join('user_roles ur', 'ur.user_id = u.id')
                ->join('roles r', 'r.id = ur.role_id')
                ->join('user_branches ub', 'ub.user_id = u.id')
                ->join('branches b', 'b.id = ub.branch_id')
                ->where([
                    'u.id' => $selectedId,
                    'ub.branch_id' => $branch,
                ])
                ->whereIn('r.name', ['Coach', 'Fitness Coach'])
                ->get()
                ->getRowArray();

            if (is_array($historical)) {
                $coaches[] = $historical;
                $loadedIds[] = $selectedId;
            }
        }

        return $coaches;
    }
    /** @return list<array<string, mixed>> */
    public function listForAuthorizedBranches(array $filters = []): array
    {
        $branches = service('branchContext')->authorizedBranchIds(true);
        if ($branches === []) {
            return [];
        }
        $query = $this->authorizedAdmissionsQuery($filters, $branches);

        return $query === null
            ? []
            : $query->orderBy('a.id', 'DESC')->get()->getResultArray();
    }

    /** @return array{admissions: list<array<string, mixed>>, total: int, page: int, pages: int, filters: array<string, mixed>} */
    public function paginatedForAuthorizedBranches(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $branches = service('branchContext')->authorizedBranchIds(true);
        $page = max(1, $page);
        $perPage = min(100, max(1, $perPage));

        if ($branches === []) {
            return [
                'admissions' => [],
                'total' => 0,
                'page' => 1,
                'pages' => 1,
                'filters' => $filters,
            ];
        }

        $countQuery = $this->authorizedAdmissionsQuery($filters, $branches);
        if ($countQuery === null) {
            return [
                'admissions' => [],
                'total' => 0,
                'page' => 1,
                'pages' => 1,
                'filters' => $filters,
            ];
        }

        $total = (int) $countQuery->countAllResults();
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);

        $query = $this->authorizedAdmissionsQuery($filters, $branches);
        $admissions = $query === null
            ? []
            : $query->orderBy('a.id', 'DESC')
                ->limit($perPage, ($page - 1) * $perPage)
                ->get()
                ->getResultArray();

        return [
            'admissions' => $admissions,
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'filters' => $filters,
        ];
    }

    /** @return list<array{key: string, label: string, class: string, filter: string, statuses: list<string>, count: int}> */
    public function statusSummaryForAuthorizedBranches(array $filters = []): array
    {
        $branches = service('branchContext')->authorizedBranchIds(true);
        $summary = $this->admissionStatusSummaryDefinition();

        if ($branches === []) {
            return array_map(
                static fn (array $card): array => $card + ['count' => 0],
                $summary,
            );
        }

        unset($filters['status'], $filters['page']);
        $query = $this->authorizedAdmissionsQuery($filters, $branches);
        if ($query === null) {
            return array_map(
                static fn (array $card): array => $card + ['count' => 0],
                $summary,
            );
        }

        $counts = [];
        foreach ($summary as $card) {
            $counts[$card['key']] = (int) (clone $query)
                ->whereIn('a.status', $card['statuses'])
                ->countAllResults();
        }

        return array_map(
            static fn (array $card): array => $card + ['count' => $counts[$card['key']] ?? 0],
            $summary,
        );
    }

    /** @return list<array{key: string, label: string, class: string, filter: string, statuses: list<string>}> */
    private function admissionStatusSummaryDefinition(): array
    {
        return [
            [
                'key' => 'intake',
                'label' => 'Intake / Pending',
                'class' => 'mcc-status-intake',
                'filter' => self::INTAKE,
                'statuses' => [self::INTAKE, 'PENDING', self::IN_REVIEW, self::DOCUMENTS_PENDING],
            ],
            [
                'key' => 'approved',
                'label' => 'Approved',
                'class' => 'mcc-status-approved',
                'filter' => self::APPROVED,
                'statuses' => [self::APPROVED],
            ],
            [
                'key' => 'completed',
                'label' => 'Completed',
                'class' => 'mcc-status-completed',
                'filter' => self::COMPLETED,
                'statuses' => [self::COMPLETED],
            ],
            [
                'key' => 'converted',
                'label' => 'Converted',
                'class' => 'mcc-status-converted',
                'filter' => self::CONVERTED,
                'statuses' => [self::CONVERTED],
            ],
            [
                'key' => 'rejected-closed',
                'label' => 'Rejected / Closed',
                'class' => 'mcc-status-rejected',
                'filter' => self::REJECTED,
                'statuses' => [self::REJECTED, self::WITHDRAWN, 'CLOSED'],
            ],
        ];
    }

    private function authorizedAdmissionsQuery(array $filters, array $branches): ?object
    {
        $query = Database::connect('default')->table('admissions a')
            ->select('a.id, a.lead_id, a.applicant_full_name, a.admission_number AS admission_number, a.branch_id, a.trial_id, a.status, a.converted_player_id, l.full_name AS lead_name, b.name AS branch_name')
            ->join('leads l', 'l.id = a.lead_id', 'left')
            ->join('branches b', 'b.id = a.branch_id')
            ->groupStart()
            ->where('l.branch_id = a.branch_id', null, false)
            ->orWhere('a.lead_id', null)
            ->groupEnd()
            ->whereIn('a.branch_id', $branches);
        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $query->groupStart()->like('a.applicant_full_name', $q)->orLike('l.full_name', $q)->groupEnd();
        }
        $admissionNumber = trim((string) ($filters['admission_number'] ?? ''));
        if ($admissionNumber !== '') {
            $query->where('a.admission_number', $admissionNumber);
        }
        $status = (string) ($filters['status'] ?? '');
        $filterableStatuses = array_merge(array_keys(self::TRANSITIONS), [self::COMPLETED]);
        if ($status !== '' && ! in_array($status, $filterableStatuses, true)) {
            return null;
        }
        if ($status !== '') {
            $query->where('a.status', $status);
        }
        $branch = trim((string) ($filters['branch_id'] ?? ''));
        if ($branch !== '') {
            if (! ctype_digit($branch) || (int) $branch < 1 || ! in_array((int) $branch, $branches, true)) {
                return null;
            }

            $query->where('a.branch_id', (int) $branch);
        }
        $source = (string) ($filters['source'] ?? '');
        if ($source !== '' && ! in_array($source, ['direct', 'lead'], true)) {
            return null;
        }
        if ($source === 'direct') {
            $query->where('a.lead_id', null);
        }
        if ($source === 'lead') {
            $query->where('a.lead_id IS NOT NULL', null, false);
        }
        foreach (['batch_id', 'trial_id'] as $field) {
            $value = (string) ($filters[$field] ?? '');
            if ($value !== '') {
                if (! ctype_digit($value) || (int) $value < 1) {
                    return null;
                }
                $query->where('a.' . $field, (int) $value);
            }
        }
        $from = trim((string) ($filters['date_from'] ?? ''));
        $to = trim((string) ($filters['date_to'] ?? ''));
        if (($from !== '' && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $from))
            || ($to !== '' && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))) {
            return null;
        }
        if ($from !== '') {
            $query->where('DATE(a.created_at) >=', $from);
        }
        if ($to !== '') {
            $query->where('DATE(a.created_at) <=', $to);
        }

        return $query;
    }
    /** @return array<string, mixed> */
    public function details(int $admissionId): array
    {
        $authoritative = $this->loadAdmission($admissionId, false);
        $this->authorize((int) $authoritative['branch_id'], 'admission.view');
        $admission = Database::connect('default')
            ->query('SELECT a.*, a.admission_number AS admission_number, l.full_name AS lead_name, l.email AS lead_email, l.phone AS lead_phone,
             l.date_of_birth AS lead_date_of_birth, l.source AS lead_source, b.name AS branch_name, batch.name AS batch_name,
             t.trial_date, t.trial_time, t.location AS trial_location, t.status AS trial_status,
             t.selection_outcome AS trial_selection_outcome, t.remarks AS trial_remarks,
             trial_coach.display_name AS trial_coach_name, secondary_trial_coach.display_name AS secondary_trial_coach_name,
             assigned_coach.display_name AS assigned_coach_name,
             personal_coach.display_name AS personal_coach_name,
             approver.display_name AS approved_by_name,
             converted_person.full_name AS converted_player_name,
             (SELECT h.created_at FROM admission_status_history h
                WHERE h.admission_id = a.id AND h.new_status = \'CONVERTED\'
                ORDER BY h.id ASC LIMIT 1) AS conversion_date
             FROM admissions a LEFT JOIN leads l ON l.id = a.lead_id LEFT JOIN trials t ON t.id = a.trial_id INNER JOIN branches b ON b.id = a.branch_id
             LEFT JOIN batches batch ON batch.id = a.batch_id
             LEFT JOIN users trial_coach ON trial_coach.id = t.coach_user_id
             LEFT JOIN users secondary_trial_coach ON secondary_trial_coach.id = t.secondary_coach_user_id
             LEFT JOIN users assigned_coach ON assigned_coach.id = a.coach_user_id
             LEFT JOIN users personal_coach ON personal_coach.id = a.personal_coach_user_id
             LEFT JOIN users approver ON approver.id = a.approved_by
             LEFT JOIN players converted_player ON converted_player.id = a.converted_player_id
             LEFT JOIN persons converted_person ON converted_person.id = converted_player.person_id
             WHERE a.id = ? LIMIT 1', [$admissionId])->getRowArray();
        if (!is_array($admission)) {
            throw new RuntimeException('Not found.');
        }
        $admission['admission_ratings'] = $this->admissionRatingProjection($admission);
        $admission['admission_rating_categories'] = $this->ratingCategories($admission);
        $admission['history'] = Database::connect('default')->table('admission_status_history h')->select('h.*, u.display_name AS actor_name')
            ->join('users u', 'u.id = h.actor_user_id', 'left')->where('h.admission_id', $admissionId)->orderBy('h.id', 'DESC')->get()->getResultArray();
        $admission['follow_ups'] = service('admissionFollowUp')->forAdmission($admissionId);
        $admission['periods'] = $this->periodsForAdmission($admissionId);
        $admission['current_period'] = $admission['periods'][0] ?? null;
        $admission['admission_date_display'] = $admission['admission_date'] ?? null;
        if ((string) $admission['admission_date_display'] === '') {
            $admission['admission_date_display'] = $admission['joining_date'] ?? null;
        }
        if ((string) ($admission['admission_date_display'] ?? '') === ''
            && is_array($admission['current_period'])) {
            $admission['admission_date_display'] = $admission['current_period']['start_date'] ?? null;
        }
        $currentPeriodId = (int) ($admission['current_period']['id'] ?? 0);
        $admission['installment_rows'] = $this->loadInstallmentRows($admissionId, 'coaching', $currentPeriodId);
        $admission['hostel_installment_rows'] = $this->loadInstallmentRows($admissionId, 'hostel', $currentPeriodId);
        $admission['coaching_installment_history'] = $this->loadInstallmentRows($admissionId, 'coaching');
        $admission['hostel_installment_history'] = $this->loadInstallmentRows($admissionId, 'hostel');
        $admission['installment_status_rows'] = service('admissionInstallments')->forAdmission($admissionId);
        $admission['session_progress'] = service('scheduling')->sessionProgressForAdmission($admissionId);
        $admission['total_coaching_hostel_fee'] = $this->calculateFeeTotal($admission);
        $admission = array_merge($admission, $this->calculateTaxInformation($admission));
        $admission['can_update'] = service('authorization')->can('admission.update', (string) $admission['branch_id']);
        $admission['can_renew'] = $admission['can_update'];
        $admission['can_convert'] = service('authorization')->can('player.convert', (string) $admission['branch_id']);
        $admission['can_complete'] = $admission['can_update'] && $admission['can_convert']
            && (string) $admission['status'] === self::INTAKE
            && $admission['converted_player_id'] === null;
        $admission['allowed_transitions'] = $this->allowedTransitions((string) $admission['status']);
        return $admission;
    }

    /** @return array<string, mixed> */
    public function applyLinkedAdmissionTestFeeDefaults(array $admission): array
    {
        $testId = (int) ($admission['admission_test_id'] ?? 0);
        $branchId = (int) ($admission['branch_id'] ?? 0);

        if ($testId < 1 || $branchId < 1) {
            return $admission;
        }

        $db = Database::connect('default');
        $test = $db->table('admission_tests')
            ->where(['id' => $testId, 'branch_id' => $branchId])
            ->get()
            ->getRowArray();

        if (! is_array($test)) {
            return $admission;
        }

        foreach (['training_fee', 'installment_plan', 'hostel_charges', 'hostel_installment_plan'] as $field) {
            if (($admission[$field] ?? null) === null || trim((string) $admission[$field]) === '') {
                $admission[$field] = $test[$field] ?? null;
            }
        }

        if (($admission['installment_rows'] ?? []) === [] && $admission['installment_plan'] === 'Instalment Plan') {
            $admission['installment_rows'] = $this->loadAdmissionTestInstallmentRows(
                $db,
                $testId,
                $branchId,
                'admission_test_coaching_installments',
            );
        }

        if (($admission['hostel_installment_rows'] ?? []) === [] && $admission['hostel_installment_plan'] === 'Instalment Plan') {
            $admission['hostel_installment_rows'] = $this->loadAdmissionTestInstallmentRows(
                $db,
                $testId,
                $branchId,
                'admission_test_hostel_installments',
            );
        }

        return $admission;
    }

    /** @return list<array<string, int|string|null>> */
    private function loadAdmissionTestInstallmentRows(object $db, int $testId, int $branchId, string $table): array
    {
        if (! $db->tableExists($table)) {
            return [];
        }

        $sourceRows = $db->table($table)
            ->where(['admission_test_id' => $testId, 'branch_id' => $branchId])
            ->orderBy('year_number')
            ->orderBy('installment_number')
            ->get()
            ->getResultArray();
        $rows = [];

        foreach ($sourceRows as $sourceRow) {
            $year = (int) $sourceRow['year_number'];
            $rows[$year] ??= ['year_number' => $year];
            $number = (int) $sourceRow['installment_number'];
            $rows[$year]['installment_' . $number . '_date'] = $sourceRow['due_date'];
            $rows[$year]['installment_' . $number] = $sourceRow['amount'];
        }

        return array_values($rows);
    }

    /** @return array<string, mixed> */
    public function renewalForm(int $admissionId): array
    {
        $admission = $this->details($admissionId);
        if (empty($admission['can_renew'])) {
            throw new RuntimeException('Forbidden');
        }

        $current = $this->renewalCurrentPeriod($admission);
        $renewalError = null;
        if (! is_array($current) || $this->periodEndDate($current) === null) {
            $renewalError = 'Please enter the current Admission period end date before renewing.';
        }
        $admission['current_period'] = $current ?? [];

        return [
            'admission' => $admission,
            'renewal_error' => $renewalError,
        ];
    }

    public function renew(int $admissionId, array $input): int
    {
        $db = Database::connect('default');
        $db->transStart();

        try {
            $admission = $this->loadAdmission($admissionId, true);
            $actor = $this->authorize((int) $admission['branch_id'], 'admission.update');
            $periods = $this->periodsForAdmission($admissionId, $db);

            if ($periods === []) {
                $this->createInitialPeriod($db, $admission, $actor);
                $periods = $this->periodsForAdmission($admissionId, $db);
            }

            $current = $this->renewalCurrentPeriod($admission, $db);
            $currentEndDate = is_array($current)
                ? $this->periodEndDate($current)
                : null;
            if (! is_array($current) || $currentEndDate === null) {
                throw new RuntimeException('Please enter the current Admission period end date before renewing.');
            }

            $startDate = $this->normalizeRenewalDate($input['start_date'] ?? null);
            $endDate = $this->normalizeRenewalDate($input['end_date'] ?? null);
            $this->logRenewalDateComparison(
                $admissionId,
                $admission,
                $current,
                $input['start_date'] ?? null,
                $currentEndDate,
                $startDate,
            );
            $renewalValues = $this->normalizeRenewalValues($input, $admission);
            $installmentRows = $this->normalizeInstallmentRows($input, 'installment_');
            $hostelInstallmentRows = $this->normalizeInstallmentRows($input, 'hostel_installment_');
            $masterInput = [
                'age_group_id' => $renewalValues['age_group_id'],
            ];
            $requestHash = $this->renewalRequestHash($admissionId, $input, $renewalValues);
            $existing = $db->table('admission_periods')
                ->where(['admission_id' => $admissionId, 'request_hash' => $requestHash])
                ->get()
                ->getRowArray();

            if (is_array($existing)) {
                $db->transComplete();

                return (int) $existing['id'];
            }

            $expectedVersion = (int) ($input['expected_version'] ?? -1);
            $actualVersion = (int) ($admission['renewal_version'] ?? 0);
            if ($expectedVersion !== $actualVersion) {
                throw new RuntimeException('This Admission was renewed by another user. Reload and try again.');
            }

            if ($startDate === null || $endDate === null || $startDate <= $currentEndDate) {
                throw new RuntimeException('The renewal start date must be after the current Admission period end date.');
            }
            if ($endDate < $startDate) {
                throw new RuntimeException('The renewal end date must be on or after the start date.');
            }

            $overlap = $db->table('admission_periods')
                ->where('admission_id', $admissionId)
                ->where('start_date <=', $endDate)
                ->where('end_date >=', $startDate)
                ->countAllResults();
            if ($overlap > 0) {
                throw new RuntimeException('The renewal period overlaps an existing period.');
            }

            $snapshots = $this->masterSnapshots(
                $masterInput,
                (int) $admission['branch_id'],
                $db,
            );
            $periodNumber = (int) $current['period_number'] + 1;
            $now = date('Y-m-d H:i:s');

            $db->table('admission_periods')
                ->where(['admission_id' => $admissionId, 'status' => 'current'])
                ->update(['status' => 'historical', 'updated_at' => $now]);
            $periodData = array_merge([
                'admission_id' => $admissionId,
                'branch_id' => (int) $admission['branch_id'],
                'period_number' => $periodNumber,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'age_group_id' => $this->nullableInteger($masterInput['age_group_id']),
                'age_group_label' => $snapshots['age_group'],
                'fee_plan_label' => $current['fee_plan_label'] ?? ($admission['fee_plan_label'] ?? null),
                'renewal_values' => json_encode($renewalValues, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'status' => 'current',
                'created_by' => $actor,
                'request_hash' => $requestHash,
                'created_at' => $now,
                'updated_at' => $now,
            ], $this->periodRatingSnapshot($admission));
            if ($db->fieldExists('renewal_values', 'admission_periods')) {
                $periodData['renewal_values'] = json_encode($renewalValues, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            } else {
                unset($periodData['renewal_values']);
            }
            if (! $db->table('admission_periods')->insert($periodData)) {
                throw new RuntimeException($this->databaseFailureMessage($db, 'Unable to save renewal period.'));
            }
            $periodId = (int) $db->insertID();

            $this->saveInstallmentRows($db, $admissionId, $installmentRows, 'coaching', $periodId, $actor);
            $this->saveInstallmentRows($db, $admissionId, $hostelInstallmentRows, 'hostel', $periodId, $actor);

            if (! $db->table('admissions')->where('id', $admissionId)->update(array_merge([
                'joining_date' => $startDate,
                'year1_end_date' => $endDate,
                'age_group_id' => $this->nullableInteger($masterInput['age_group_id']),
                'age_group' => $snapshots['age_group'],
                'renewal_version' => $actualVersion + 1,
                'updated_at' => $now,
            ], $this->renewalAdmissionUpdates($renewalValues)))) {
                throw new RuntimeException($this->databaseFailureMessage($db, 'Unable to update renewed admission.'));
            }
            service('auditPersistence')->record($actor, 'admission_renewed', 'admission', (string) $admissionId,
                (int) $admission['branch_id'], null, [
                    'period_id' => $periodId,
                    'period_number' => $periodNumber,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ]);

            $db->transComplete();
            $this->assertTransactionSucceeded($db, 'Unable to renew admission.');

            return $periodId;
        } catch (Throwable $exception) {
            $db->transRollback();
            $db->resetTransStatus();
            throw $exception;
        }
    }
    public function createFromTrialPending(int $trialId, int $leadId, int $branch, object $db): int
    {
        $actor = $this->authorize($branch, 'admission.create');
        $lead = $db->table('leads')->where(['id' => $leadId, 'branch_id' => $branch])->get()->getRowArray();
        if (!is_array($lead)) {
            throw new RuntimeException('Invalid admission source.');
        }
        $existing = $db->table('admissions')->where('lead_id', $leadId)->get()->getRowArray();
        if (is_array($existing)) {
            if ((int) ($existing['branch_id'] ?? 0) !== $branch) {
                throw new RuntimeException('Admission branch mismatch.');
            }
            return (int) $existing['id'];
        }
        $trial = $db->table('trials')->where(['id' => $trialId, 'lead_id' => $leadId, 'branch_id' => $branch])->get()->getRowArray();
        if (!is_array($trial)) {
            throw new RuntimeException('Invalid admission source.');
        }
        $now = date('Y-m-d H:i:s');
        $admissionNumber = $this->generateAdmissionNumber($db);
        $db->table('admissions')->insert([
            'lead_id' => $leadId,
            'branch_id' => $branch,
            'trial_id' => $trialId,
            'admission_number' => $admissionNumber,
            'status' => self::INTAKE,
            'checklist_complete' => 0,
            'checklist_identity_complete' => 0,
            'checklist_contact_complete' => 0,
            'checklist_admission_complete' => 0,
            'checklist_branch_confirmed' => 0,
            'checklist_approval_recorded' => 0,
            'converted_player_id' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $id = (int) $db->insertID();
        $this->createInitialPeriod($db, [
            'id' => $id,
            'branch_id' => $branch,
            'joining_date' => null,
            'year1_end_date' => null,
        ], $actor);
        $this->recordHistory($db, $id, $actor, null, self::INTAKE, 'Created from selected trial.', $now);
        service('auditPersistence')->record($actor, 'admission_created_from_trial', 'admission', (string) $id, $branch, null, [
            'lead_id' => $leadId,
            'trial_id' => $trialId,
            'admission_number' => $admissionNumber,
            'status' => self::INTAKE,
        ]);
        return $id;
    }
    public function createFromLead(int $leadId, array $input = []): int
    {
        if ($leadId < 1) {
            throw new RuntimeException('Invalid admission.');
        }
        $db = Database::connect('default');
        $db->transStart();
        try {
            $lead = $db->query('SELECT id, branch_id, status FROM leads WHERE id = ? LIMIT 1 FOR UPDATE', [$leadId])->getRowArray();
            if (!is_array($lead)) {
                throw new RuntimeException('Not found.');
            }
            $branch = (int) $lead['branch_id'];
            $actor = $this->authorize($branch, 'admission.create');
            if (!in_array((string) $lead['status'], ['lead', 'admission pending'], true)) {
                throw new RuntimeException('Lead is not eligible.');
            }
            if ($db->table('admissions')->where('lead_id', $leadId)->countAllResults() > 0) {
                throw new RuntimeException('Admission already exists.');
            }
            $profile = $this->intakeFields($input);
            $installmentRows = $this->normalizeInstallmentRows($input, 'installment_');
            $hostelInstallmentRows = $this->normalizeInstallmentRows($input, 'hostel_installment_');
            $this->validateExtendedFields($profile, $branch, $db);
            $this->validateTrialRelationship($profile['trial_id'], $leadId, $branch, $db);
            $profile = array_merge($profile, $this->masterSnapshots($profile, $branch, $db));
            if ($profile['batch_id'] !== null && !is_array($db->table('batches')->where(['id' => (int) $profile['batch_id'], 'is_active' => 1])
                ->get()->getRowArray())) {
                throw new RuntimeException('Invalid admission details.');
            }
            $now = date('Y-m-d H:i:s');
            $admissionNumber = $this->generateAdmissionNumber($db);
            $inserted = $db->table('admissions')->insert(array_merge([
                'lead_id' => $leadId,
                'branch_id' => $branch,
                'admission_number' => $admissionNumber,
                'status' => self::INTAKE,
                'checklist_complete' => 0,
                'checklist_identity_complete' => 0,
                'checklist_contact_complete' => 0,
                'checklist_admission_complete' => 0,
                'checklist_branch_confirmed' => 0,
                'checklist_approval_recorded' => 0,
                'approved_by' => null,
                'approved_at' => null,
                'converted_player_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ], $profile));
            if (! $inserted) {
                throw new RuntimeException($this->databaseFailureMessage($db, 'Unable to insert admission record.'));
            }
            $admissionId = (int) $db->insertID();
            $this->createInitialPeriod($db, array_merge([
                'id' => $admissionId,
                'branch_id' => $branch,
            ], $profile), $actor);
            $periodId = $this->currentPeriodId($db, $admissionId);
            $this->saveInstallmentRows($db, $admissionId, $installmentRows, 'coaching', $periodId, $actor);
            $this->saveInstallmentRows($db, $admissionId, $hostelInstallmentRows, 'hostel', $periodId, $actor);
            $this->recordHistory($db, $admissionId, $actor, null, self::INTAKE, null, $now);
            service('auditPersistence')->record($actor, 'admission_created', 'admission', (string) $admissionId, $branch, null, [
                'lead_id' => $leadId,
                'admission_number' => $admissionNumber,
                'status' => self::INTAKE,
                'tax_information' => [
                    'total_taxable_fee' => $profile['total_taxable_fee'] ?? null,
                    'tax_rate' => $profile['tax_rate'] ?? null,
                    'calculated_tax_amount' => $profile['calculated_tax_amount'] ?? null,
                    'total_including_tax' => $profile['total_including_tax'] ?? null,
                ],
                'coach_user_id' => $profile['coach_user_id'] ?? null,
                'personal_coach_user_id' => $profile['personal_coach_user_id'] ?? null,
            ]);
            $db->transComplete();
            $this->assertTransactionSucceeded($db, 'Unable to create admission.');
            return $admissionId;
        } catch (Throwable $exception) {
            $db->transRollback();
            $db->resetTransStatus();
            throw $exception;
        }
    }
    /** Create an admission directly, using the same approved intake identity fields as persons/leads. */
    public function createDirect(array $input): int
    {
        $name = trim((string) ($input['applicant_full_name'] ?? ''));
        $email = trim((string) ($input['applicant_email'] ?? ''));
        $phone = trim((string) ($input['applicant_phone'] ?? ''));
        $dob = trim((string) ($input['applicant_date_of_birth'] ?? ''));
        $branch = (int) ($input['branch_id'] ?? 0);
        if ($name === '' || strlen($name) > 150 || $email !== '' && (strlen($email) > 190 || filter_var($email,
            FILTER_VALIDATE_EMAIL) === false) || strlen($phone) > 40 || $dob !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob)) {
            throw new RuntimeException('Invalid admission details.');
        }
        $profile = $this->intakeFields($input);
        $installmentRows = $this->normalizeInstallmentRows($input, 'installment_');
        $hostelInstallmentRows = $this->normalizeInstallmentRows($input, 'hostel_installment_');
        $actor = $this->authorize($branch, 'admission.create');
        $db = Database::connect('default');
            $this->validateExtendedFields($profile, $branch, $db);
        $profile = array_merge($profile, $this->masterSnapshots($profile, $branch, $db));
        if ($profile['batch_id'] !== null && !is_array($db->table('batches')->where(['id' => (int) $profile['batch_id'], 'is_active' => 1])
            ->get()->getRowArray())) {
            throw new RuntimeException('Invalid admission details.');
        }
        if ($profile['trial_id'] !== null) {
            throw new RuntimeException('Trial requires a Lead.');
        }
        $db->transStart();
        try {
            $now = date('Y-m-d H:i:s');
            $admissionNumber = $this->generateAdmissionNumber($db);
            $inserted = $db->table('admissions')->insert(array_merge([
                'lead_id' => null,
                'applicant_full_name' => $name,
                'applicant_email' => $email === '' ? null : $email,
                'applicant_phone' => $phone === '' ? null : $phone,
                'applicant_date_of_birth' => $dob === '' ? null : $dob,
                'branch_id' => $branch,
                'admission_number' => $admissionNumber,
                'status' => self::INTAKE,
                'checklist_complete' => 0,
                'checklist_identity_complete' => 0,
                'checklist_contact_complete' => 0,
                'checklist_admission_complete' => 0,
                'checklist_branch_confirmed' => 0,
                'checklist_approval_recorded' => 0,
                'approved_by' => null,
                'approved_at' => null,
                'converted_player_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ], $profile));
            if (! $inserted) {
                throw new RuntimeException($this->databaseFailureMessage($db, 'Unable to insert admission record.'));
            }
            $id = (int) $db->insertID();
            $this->createInitialPeriod($db, array_merge([
                'id' => $id,
                'branch_id' => $branch,
            ], $profile), $actor);
            $periodId = $this->currentPeriodId($db, $id);
            $this->saveInstallmentRows($db, $id, $installmentRows, 'coaching', $periodId, $actor);
            $this->saveInstallmentRows($db, $id, $hostelInstallmentRows, 'hostel', $periodId, $actor);
            $this->recordHistory($db, $id, $actor, null, self::INTAKE, null, $now);
            service('auditPersistence')->record($actor, 'admission_created', 'admission', (string) $id, $branch, null, [
                'lead_id' => null,
                'admission_number' => $admissionNumber,
                'status' => self::INTAKE,
                'direct' => true,
                'tax_information' => [
                    'total_taxable_fee' => $profile['total_taxable_fee'] ?? null,
                    'tax_rate' => $profile['tax_rate'] ?? null,
                    'calculated_tax_amount' => $profile['calculated_tax_amount'] ?? null,
                    'total_including_tax' => $profile['total_including_tax'] ?? null,
                ],
                'coach_user_id' => $profile['coach_user_id'] ?? null,
                'personal_coach_user_id' => $profile['personal_coach_user_id'] ?? null,
            ]);
            $db->transComplete();
            $this->assertTransactionSucceeded($db, 'Unable to create admission.');
            return $id;
        } catch (Throwable $exception) {
            $db->transRollback();
            $db->resetTransStatus();
            throw $exception;
        }
    }
    public function updateIntake(int $id, array $input): bool
    {
        $db = Database::connect('default');
        $db->transStart();
        try {
            $a = $this->loadAdmission($id, true);
            $actor = $this->authorize((int) $a['branch_id'], 'admission.update');
            if (in_array((string) $a['status'], [self::APPROVED, self::COMPLETED, self::CONVERTED], true)) {
                $changed = $this->updateCompletedCoachAssignments($db, $id, $a, $input, $actor);
                $db->transComplete();
                $this->assertTransactionSucceeded($db, 'Unable to update admission coaches.');

                return $changed;
            }
            if (in_array($a['status'], [self::REJECTED, self::CONVERTED], true)) {
                throw new RuntimeException('Admission is terminal.');
            }
            $requestedStatus = array_key_exists('status', $input)
                ? strtoupper(trim((string) $input['status']))
                : (string) $a['status'];
            if ($requestedStatus === '') {
                $requestedStatus = (string) $a['status'];
            }
            $this->assertValidStatus($requestedStatus);
            $statusChanged = $requestedStatus !== (string) $a['status'];
            $statusReason = null;
            $statusUpdates = [];
            if ($statusChanged) {
                if (! in_array($requestedStatus, self::TRANSITIONS[(string) $a['status']], true)) {
                    throw new RuntimeException('Invalid transition.');
                }
                $statusReason = trim((string) ($input['reason'] ?? '')) ?: null;
                $isReopening = (string) $a['status'] === self::REJECTED
                    && $requestedStatus === self::IN_REVIEW;
                if (($isReopening || in_array($requestedStatus, [self::REJECTED, self::WITHDRAWN], true))
                    && $statusReason === null) {
                    throw new RuntimeException('Reason required.');
                }
                $statusUpdates = ['status' => $requestedStatus];
                if ($requestedStatus === self::APPROVED) {
                    $statusReason ??= 'Admission completed.';
                    $statusUpdates += [
                        'approved_by' => $actor,
                        'approved_at' => date('Y-m-d H:i:s'),
                    ];
                }
            }
            $updates = $this->intakeFields($input, $a);
            if ($a['lead_id'] === null && ($updates['trial_id'] ?? null) !== null) {
                throw new RuntimeException('Trial requires a Lead.');
            }
            $installmentRows = $this->normalizeInstallmentRows($input, 'installment_');
            $hostelInstallmentRows = $this->normalizeInstallmentRows($input, 'hostel_installment_');
            foreach (self::RATING_FIELDS as $field) {
                $existing = trim((string) ($a[$field] ?? ''));
                if (array_key_exists($field, $input)
                    && ($input[$field] ?? '') === ''
                    && $existing !== ''
                    && ! ctype_digit($existing)) {
                    unset($updates[$field]);
                }
            }
            $this->validateExtendedFields(
                $updates,
                (int) $a['branch_id'],
                $db,
                (int) ($a['coach_user_id'] ?? 0),
                (int) ($a['personal_coach_user_id'] ?? 0),
            );
            $snapshots = $this->masterSnapshots($updates, (int) $a['branch_id'], $db, (int) ($a['age_group_id'] ?? 0));
            foreach (['age_group_id' => 'age_group'] as $idField => $labelField) {
                if (! array_key_exists($idField, $input)) {
                    unset($snapshots[$labelField]);
                }
            }
            foreach (array_keys($updates) as $field) {
                $dateProjection = in_array($field, ['joining_date', 'year1_start_date', 'year1_end_date'], true)
                    && (array_key_exists('start_date', $input) || array_key_exists('end_date', $input));
                if (!array_key_exists($field, $input) && ! $dateProjection) {
                    unset($updates[$field]);
                }
            }
            if ($a['lead_id'] !== null && array_key_exists('trial_id', $input)) {
                $this->validateTrialRelationship(
                    $updates['trial_id'] ?? null,
                    (int) $a['lead_id'],
                    (int) $a['branch_id'],
                    $db,
                );
            }
            $updates = array_merge($updates, $snapshots);
            foreach (['declaration_accepted'] as $checkbox) {
                if (!array_key_exists($checkbox, $input)) {
                    $updates[$checkbox] = 0;
                }
            }
            $name = trim((string) ($input['applicant_full_name'] ?? $a['applicant_full_name'] ?? ''));
            if ($a['lead_id'] === null) {
                if ($name === '' || strlen($name) > 150) {
                    throw new RuntimeException('Invalid admission details.');
                }
                $updates['applicant_full_name'] = $name;
                $updates['applicant_email'] = trim((string) ($input['applicant_email'] ?? '')) ?: null;
                $updates['applicant_phone'] = trim((string) ($input['applicant_phone'] ?? '')) ?: null;
                $updates['applicant_date_of_birth'] = trim((string) ($input['applicant_date_of_birth'] ?? '')) ?: null;
            }
            $updates = array_merge($updates, $statusUpdates);
            $now = date('Y-m-d H:i:s');
            if ($requestedStatus === self::APPROVED && $statusChanged) {
                $updates['approved_at'] = $now;
            }
            $updates['updated_at'] = $now;
            if (! $db->table('admissions')->where('id', $id)->update($updates)) {
                throw new RuntimeException($this->databaseFailureMessage($db, 'Unable to update admission record.'));
            }
            $periodId = $this->currentPeriodId($db, $id);
            $this->saveInstallmentRows($db, $id, $installmentRows, 'coaching', $periodId, $actor);
            $this->saveInstallmentRows($db, $id, $hostelInstallmentRows, 'hostel', $periodId, $actor);
            service('auditPersistence')->record($actor, 'admission_intake_updated', 'admission', (string) $id, (int) $a['branch_id'], null, [
                'fields' => array_keys($updates),
                'tax_information' => [
                    'before' => [
                        'total_taxable_fee' => $a['total_taxable_fee'] ?? null,
                        'tax_rate' => $a['tax_rate'] ?? null,
                        'calculated_tax_amount' => $a['calculated_tax_amount'] ?? null,
                        'total_including_tax' => $a['total_including_tax'] ?? null,
                    ],
                    'after' => [
                        'total_taxable_fee' => $updates['total_taxable_fee'] ?? $a['total_taxable_fee'] ?? null,
                        'tax_rate' => $updates['tax_rate'] ?? $a['tax_rate'] ?? null,
                        'calculated_tax_amount' => $updates['calculated_tax_amount'] ?? $a['calculated_tax_amount'] ?? null,
                        'total_including_tax' => $updates['total_including_tax'] ?? $a['total_including_tax'] ?? null,
                    ],
                ],
                'coach_changes' => [
                    'batch_coach_user_id' => [
                        'before' => (int) ($a['coach_user_id'] ?? 0) ?: null,
                        'after' => (int) ($updates['coach_user_id'] ?? $a['coach_user_id'] ?? 0) ?: null,
                    ],
                    'personal_coach_user_id' => [
                        'before' => (int) ($a['personal_coach_user_id'] ?? 0) ?: null,
                        'after' => (int) ($updates['personal_coach_user_id'] ?? $a['personal_coach_user_id'] ?? 0) ?: null,
                    ],
                ],
            ]);
            if ($statusChanged) {
                $this->recordHistory(
                    $db,
                    $id,
                    $actor,
                    (string) $a['status'],
                    $requestedStatus,
                    $statusReason,
                    $now,
                );
                service('auditPersistence')->record(
                    $actor,
                    'admission_status_changed',
                    'admission',
                    (string) $id,
                    (int) $a['branch_id'],
                    ['status' => $a['status']],
                    ['status' => $requestedStatus],
                    $statusReason,
                );
            }
            $db->transComplete();
            $this->assertTransactionSucceeded($db, 'Unable to update admission.');
            return $statusChanged;
        } catch (Throwable $e) {
            $db->transRollback();
            $db->resetTransStatus();
            throw $e;
        }
    }

    /**
     * Return the focused coach-correction form data for an Admission.
     *
     * @return array{admission: array<string, mixed>, coaches: array<int, array<string, mixed>>}
     */
    public function coachCorrectionForm(int $id): array
    {
        $admission = $this->details($id);

        return [
            'admission' => $admission,
            'coaches' => $this->coachesForBranch(
                (int) $admission['branch_id'],
                (int) ($admission['coach_user_id'] ?? 0),
                (int) ($admission['personal_coach_user_id'] ?? 0),
            ),
        ];
    }

    /**
     * Update only the Group Coach and Personal Coach assignments.
     */
    public function updateCoachAssignments(int $id, array $input): bool
    {
        $db = Database::connect('default');
        $db->transStart();

        try {
            $allowedFields = [
                csrf_token(),
                'batch_coach_id',
                'personal_coach_id',
                'correction_reason',
                'expected_version',
            ];
            foreach (array_keys($input) as $field) {
                if (! in_array((string) $field, $allowedFields, true)) {
                    throw new RuntimeException('Completed Admission only permits coach assignment changes.');
                }
            }

            $normalizedInput = [
                'coach_user_id' => $input['batch_coach_id'] ?? null,
                'personal_coach_user_id' => $input['personal_coach_id'] ?? null,
                'correction_reason' => $input['correction_reason'] ?? null,
                'expected_version' => $input['expected_version'] ?? null,
            ];
            if (array_key_exists(csrf_token(), $input)) {
                $normalizedInput[csrf_token()] = $input[csrf_token()];
            }

            $admission = $this->loadAdmission($id, true);
            $actor = $this->authorize((int) $admission['branch_id'], 'admission.update');
            $changed = $this->updateCompletedCoachAssignments(
                $db,
                $id,
                $admission,
                $normalizedInput,
                $actor,
            );

            $db->transComplete();
            $this->assertTransactionSucceeded($db, 'Unable to update admission coaches.');

            return $changed;
        } catch (Throwable $exception) {
            $db->transRollback();
            $db->resetTransStatus();
            throw $exception;
        }
    }

    /**
     * Completed Admissions only permit branch-authorized coach corrections.
     *
     * @param array<string, mixed> $admission
     */
    private function updateCompletedCoachAssignments(
        object $db,
        int $admissionId,
        array $admission,
        array $input,
        int $actor,
    ): bool {
        $allowedFields = [
            csrf_token(),
            'coach_user_id',
            'personal_coach_user_id',
            'correction_reason',
            'expected_version',
        ];
        foreach (array_keys($input) as $field) {
            if (! in_array((string) $field, $allowedFields, true)) {
                throw new RuntimeException('Completed Admission only permits coach assignment changes.');
            }
        }

        if (array_key_exists('expected_version', $input)) {
            $expectedVersion = trim((string) $input['expected_version']);
            if (! ctype_digit($expectedVersion)
                || (int) $expectedVersion !== (int) ($admission['renewal_version'] ?? 0)) {
                throw new RuntimeException('Admission version is stale.');
            }
        }

        $currentGroupCoachId = (int) ($admission['coach_user_id'] ?? 0);
        $currentPersonalCoachId = (int) ($admission['personal_coach_user_id'] ?? 0);
        $groupCoachValue = array_key_exists('coach_user_id', $input)
            ? $input['coach_user_id']
            : $currentGroupCoachId;
        $personalCoachValue = array_key_exists('personal_coach_user_id', $input)
            ? $input['personal_coach_user_id']
            : $currentPersonalCoachId;
        $groupCoachId = $this->nullableCoachId($groupCoachValue);
        $personalCoachId = $this->nullableCoachId($personalCoachValue);

        $changed = $groupCoachId !== ($currentGroupCoachId > 0 ? $currentGroupCoachId : null)
            || $personalCoachId !== ($currentPersonalCoachId > 0 ? $currentPersonalCoachId : null);

        if (! $changed) {
            return false;
        }

        $reason = trim((string) ($input['correction_reason'] ?? ''));
        if ($reason === '') {
            throw new RuntimeException('Correction reason is required for completed Admission coach changes.');
        }
        if (strlen($reason) > 1000) {
            throw new RuntimeException('Correction reason is too long.');
        }

        $this->validateExtendedFields(
            [
                'coach_user_id' => $groupCoachId,
                'personal_coach_user_id' => $personalCoachId,
            ],
            (int) $admission['branch_id'],
            $db,
        );

        $updated = $db->table('admissions')
            ->where([
                'id' => $admissionId,
                'branch_id' => (int) $admission['branch_id'],
                'status' => (string) $admission['status'],
            ])
            ->update([
                'coach_user_id' => $groupCoachId,
                'personal_coach_user_id' => $personalCoachId,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

        if ($updated !== true) {
            throw new RuntimeException($this->databaseFailureMessage($db, 'Unable to update admission coaches.'));
        }

        service('auditPersistence')->record(
            $actor,
            'admission_completed_coaches_corrected',
            'admission',
            (string) $admissionId,
            (int) $admission['branch_id'],
            [
                'admission_number' => $admission['admission_number'] ?? null,
                'group_coach_user_id' => $currentGroupCoachId > 0 ? $currentGroupCoachId : null,
                'personal_coach_user_id' => $currentPersonalCoachId > 0 ? $currentPersonalCoachId : null,
            ],
            [
                'admission_number' => $admission['admission_number'] ?? null,
                'group_coach_user_id' => $groupCoachId,
                'personal_coach_user_id' => $personalCoachId,
            ],
            $reason,
            $db,
        );

        return true;
    }

    private function nullableCoachId(mixed $value): ?int
    {
        $coachId = trim((string) ($value ?? ''));

        if ($coachId === '') {
            return null;
        }

        if (! ctype_digit($coachId) || (int) $coachId < 1) {
            throw new RuntimeException('Invalid admission Coach.');
        }

        return (int) $coachId;
    }

    private function generateAdmissionNumber(object $db): string
    {
        $sequence = $db->query(
            'SELECT next_number FROM admission_number_sequences WHERE id = 1 FOR UPDATE'
        )->getRowArray();

        if (! is_array($sequence)) {
            $maxRow = $db->query(
                "SELECT COALESCE(MAX(CAST(SUBSTRING(admission_number, 5) AS UNSIGNED)), 0) AS max_number
                 FROM admissions
                 WHERE admission_number REGEXP '^MCC-[0-9]{6}$'"
            )->getRowArray();
            $nextNumber = ((int) ($maxRow['max_number'] ?? 0)) + 1;

            if ($nextNumber > 999999) {
                throw new RuntimeException('Admission-number sequence is exhausted.');
            }

            $db->table('admission_number_sequences')->ignore(true)->insert([
                'id' => 1,
                'next_number' => $nextNumber,
            ]);
            $sequence = $db->query(
                'SELECT next_number FROM admission_number_sequences WHERE id = 1 FOR UPDATE'
            )->getRowArray();
        }

        if (! is_array($sequence)) {
            throw new RuntimeException('Admission-number sequence is not initialized.');
        }

        $next = (int) ($sequence['next_number'] ?? 0);
        if ($next < 1 || $next > 999999) {
            throw new RuntimeException('Admission-number sequence is exhausted.');
        }

        $updated = $db->table('admission_number_sequences')
            ->where('id', 1)
            ->update(['next_number' => $next + 1]);
        if (! $updated) {
            throw new RuntimeException('Unable to reserve Admission number.');
        }

        return 'MCC-' . str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }

    /** @return array<string, mixed> */
    private function intakeFields(array $input, ?array $existing = null): array
    {
        $fields = ['gender', 'age_group_id', 'batch_id', 'joining_date', 'blood_group', 'medical_conditions', 'jersey_size', 'playing_role',
            'batting_style', 'bowling_style', 'strong_hand', 'father_name', 'mother_name', 'parent_mobile', 'parent_email', 'emergency_contact', 'trial_id',
            'ability_overall', 'ability_batting', 'ability_bowling', 'ability_wicketkeeping', 'ability_fitness'];
        $out = [];
        foreach ($fields as $field) {
            $value = $input[$field] ?? null;
            if ($value === null || $value === '') {
                $out[$field] = null;
                continue;
            }
            if (!is_string($value) && !is_int($value)) {
                throw new RuntimeException('Invalid admission details.');
            }
            $value = trim((string) $value);
            if (strlen($value) > 255) {
                throw new RuntimeException('Invalid admission details.');
            }
            $out[$field] = $value;
        }
        foreach (['joining_date'] as $field) {
            if ($out[$field] !== null && preg_match('/^\d{2}-\d{2}-\d{4}$/', (string) $out[$field]) === 1) {
                $out[$field] = substr((string) $out[$field], 6, 4) . '-' . substr((string) $out[$field], 3, 2) . '-' . substr((string) $out[$field], 0, 2);
            }
            if ($out[$field] !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $out[$field])) {
                throw new RuntimeException('Invalid admission details.');
            }
        }
        if ($out['parent_email'] !== null && filter_var($out['parent_email'], FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException('Invalid admission details.');
        }
        if ($out['batch_id'] !== null && !ctype_digit((string) $out['batch_id'])) {
            throw new RuntimeException('Invalid admission details.');
        }
        if ($out['trial_id'] !== null && !ctype_digit((string) $out['trial_id'])) {
            throw new RuntimeException('Invalid admission details.');
        }
        foreach (self::PROFILE_OPTIONS as $field => $options) {
            if (($out[$field] ?? null) !== null && !in_array($out[$field], $options, true)) {
                throw new RuntimeException('Invalid admission option.');
            }
        }
        foreach ([
            'academic_year', 'school_college', 'previous_coaching_centre',
            'batting_order',
            'year1_age_group', 'year1_plan', 'year2_age_group', 'year2_plan', 'year3_age_group', 'year3_plan',
            'coaching_type', 'personal_coaching_days', 'personal_coaching_duration', 'estimated_personal_session', 'group_coaching_days', 'coaching_delivery',
            'fitness_plan', 'gym_fitness_session', 'match_program', 'induction_plan', 'review_frequency', 'progress_report_frequency',
            'feedback_call_frequency', 'meditation_sessions', 'elite_camp', 'elite_camp_days', 'elite_camp_duration', 'estimated_elite_session',
            'student_diary', 'welcome_kit',
        ] as $field) {
            $value = $input[$field] ?? null;

            if ($field === 'coaching_type') {
                $out[$field] = $this->normalizeMultiPlanValue(
                    $field,
                    $this->legacyCoachingTypes($input),
                    self::COACHING_TYPE_OPTIONS,
                );
                continue;
            }

            if (array_key_exists($field, self::PDF_MULTI_PLAN_OPTIONS)) {
                $out[$field] = $this->normalizeMultiPlanValue(
                    $field,
                    $value,
                    self::PDF_MULTI_PLAN_OPTIONS[$field],
                );
                continue;
            }

            if ($value !== null && ! is_scalar($value)) {
                throw new RuntimeException('Invalid admission details.');
            }
            $out[$field] = $value === null || $value === '' ? null : trim((string) $value);
            if ($out[$field] !== null && strlen((string) $out[$field]) > 255) {
                throw new RuntimeException('Invalid admission details.');
            }
        }

        foreach (self::PDF_ABILITY_OPTIONS as $field => $options) {
            if (($out[$field] ?? null) !== null && ! in_array($out[$field], $options, true)) {
                throw new RuntimeException('Invalid admission ability option.');
            }
        }
        foreach (self::RATING_FIELDS as $field) {
            try {
                $out[$field] = $this->ratingService->normalize($out[$field] ?? null);
            } catch (\InvalidArgumentException $exception) {
                throw new RuntimeException('Invalid admission rating.', 0, $exception);
            }
        }
        $coachingTypes = $this->decodeMultiPlanValue($out['coaching_type']);

        foreach (['personal_coaching_days', 'personal_coaching_duration'] as $field) {
            if (! in_array('Personal coaching', $coachingTypes, true)) {
                $out[$field] = null;
            }
        }
        if (! in_array('Personal coaching', $coachingTypes, true)) {
            $out['estimated_personal_session'] = null;
        }

        if (! in_array('Group coaching', $coachingTypes, true)) {
            $out['group_coaching_days'] = null;
        }

        if (! in_array('Elite', $coachingTypes, true)) {
            $out['elite_camp_days'] = null;
            $out['elite_camp_duration'] = null;
            $out['estimated_elite_session'] = null;
        }

        foreach (self::PDF_PLAN_OPTIONS as $field => $options) {
            if (($out[$field] ?? null) !== null && ! in_array($out[$field], $options, true)) {
                throw new RuntimeException('Invalid coaching plan option.');
            }
        }

        if (in_array('Personal coaching', $coachingTypes, true)
            && ($out['personal_coaching_days'] === null || $out['personal_coaching_duration'] === null)) {
            throw new RuntimeException('Personal coaching details are required when Personal coaching is selected.');
        }

        if (in_array('Group coaching', $coachingTypes, true) && $out['group_coaching_days'] === null) {
            throw new RuntimeException('Group coaching days are required when Group coaching is selected.');
        }

        $out['elite_camp'] = in_array('Elite', $coachingTypes, true) ? 'Yes' : 'No';
        if (in_array('Elite', $coachingTypes, true)) {
            foreach (['elite_camp_days', 'elite_camp_duration'] as $field) {
                if (($out[$field] ?? null) === null) {
                    throw new RuntimeException('Elite Camp details are required when Elite Camp is Yes.');
                }
            }
        } else {
            $out['elite_camp_days'] = null;
            $out['elite_camp_duration'] = null;
            $out['estimated_elite_session'] = null;
        }

        foreach (['estimated_personal_session', 'estimated_elite_session'] as $field) {
            $value = $out[$field] ?? null;
            if ($value !== null && (! ctype_digit((string) $value) || (int) $value < 0 || (int) $value > 9999)) {
                throw new RuntimeException('Estimated coaching sessions must be a non-negative whole number.');
            }
        }

        foreach (['student_signature', 'parent_signature', 'authorized_signature'] as $field) {
            $value = $input[$field] ?? null;
            if ($value !== null && ! is_scalar($value)) {
                throw new RuntimeException('Invalid admission signature.');
            }

            $out[$field] = $value === null || $value === '' ? null : trim((string) $value);
            if ($out[$field] !== null && strlen((string) $out[$field]) > 60000) {
                throw new RuntimeException('Invalid admission signature.');
            }
        }

        foreach ([
            'current_address', 'permanent_address', 'correspondence_address', 'playing_experience',
            'hobbies', 'achievements', 'bowling_options', 'coaching_plan', 'installment_plan',
            'installment_due_dates', 'hostel_payment_plan', 'hostel_installment_plan', 'documents_submitted', 'confirmation_notes',
        ] as $field) {
            $value = $input[$field] ?? null;
            if ($value !== null && ! is_scalar($value)) {
                throw new RuntimeException('Invalid admission details.');
            }
            $out[$field] = $value === null || $value === '' ? null : trim((string) $value);
            if ($out[$field] !== null && strlen((string) $out[$field]) > 5000) {
                throw new RuntimeException('Invalid admission details.');
            }
        }

        if ($out['installment_plan'] !== null
            && ! in_array($out['installment_plan'], self::PAYMENT_PLAN_OPTIONS, true)) {
            throw new RuntimeException('Invalid payment plan.');
        }
        if ($out['hostel_installment_plan'] !== null
            && ! in_array($out['hostel_installment_plan'], self::PAYMENT_PLAN_OPTIONS, true)) {
            throw new RuntimeException('Invalid hostel payment plan.');
        }

        foreach (['admission_date', 'year1_start_date', 'year1_end_date', 'year2_start_date', 'year2_end_date', 'year3_start_date', 'year3_end_date'] as $field) {
            $value = trim((string) ($input[$field] ?? ''));
            if ($value !== '' && preg_match('/^\d{2}-\d{2}-\d{4}$/', $value) === 1) {
                $value = substr($value, 6, 4) . '-' . substr($value, 3, 2) . '-' . substr($value, 0, 2);
            }
            if ($value !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
                throw new RuntimeException('Invalid admission date.');
            }
            $out[$field] = $value === '' ? null : $value;
        }

        if (array_key_exists('start_date', $input) || array_key_exists('end_date', $input)) {
            $startDate = $this->normalizeOptionalAdmissionDate($input['start_date'] ?? null);
            $endDate = $this->normalizeOptionalAdmissionDate($input['end_date'] ?? null);

            if ($startDate !== null && $endDate !== null && $endDate < $startDate) {
                throw new RuntimeException('Admission end date must be on or after the start date.');
            }
            $out['joining_date'] = $startDate;
            $out['year1_start_date'] = $startDate;
            $out['year1_end_date'] = $endDate;
        }

        $age = trim((string) ($input['age'] ?? ''));
        if ($age !== '' && (! ctype_digit($age) || (int) $age > 120)) {
            throw new RuntimeException('Invalid age.');
        }
        $out['age'] = $age === '' ? null : (int) $age;
        foreach (['admission_fee', 'training_fee', 'hostel_charges'] as $field) {
            $value = trim((string) ($input[$field] ?? ''));
            if ($value !== '' && (! is_numeric($value) || (float) $value < 0)) {
                throw new RuntimeException('Invalid fee amount.');
            }
            $out[$field] = $value === '' ? null : $this->normalizeMoney($value);
        }

        $feeTotal = $this->calculateFeeTotal($out);
        if (array_key_exists('total_taxable_fee', $input)) {
            $taxableFee = trim((string) ($input['total_taxable_fee'] ?? ''));
            $out['total_taxable_fee'] = $taxableFee === ''
                ? $feeTotal
                : $this->normalizeMoney($taxableFee);
        } else {
            $existingTaxableFee = trim((string) ($existing['total_taxable_fee'] ?? ''));
            $out['total_taxable_fee'] = $existingTaxableFee !== ''
                ? $this->normalizeMoney($existingTaxableFee)
                : $feeTotal;
        }

        if (array_key_exists('tax_rate', $input)) {
            $taxRate = trim((string) ($input['tax_rate'] ?? ''));
            $out['tax_rate'] = $taxRate === '' ? null : $this->normalizeTaxRate($taxRate);
        } else {
            $existingTaxRate = trim((string) ($existing['tax_rate'] ?? ''));
            $out['tax_rate'] = $existingTaxRate === ''
                ? null
                : $this->normalizeTaxRate($existingTaxRate);
        }

        $out += $this->calculateTaxInformation($out);
        foreach (['coach_user_id', 'personal_coach_user_id'] as $coachField) {
            $coachId = trim((string) ($input[$coachField] ?? ''));
            if ($coachId !== '' && (! ctype_digit($coachId) || (int) $coachId < 1)) {
                throw new RuntimeException('Invalid admission Coach.');
            }
            $out[$coachField] = $coachId === '' ? null : (int) $coachId;
        }

        foreach (['hostel_required', 'declaration_accepted'] as $field) {
            $out[$field] = ! empty($input[$field]) ? 1 : 0;
        }
        if ($out['admission_date'] === null && $out['joining_date'] !== null) {
            $out['admission_date'] = $out['joining_date'];
        }
        if ($out['joining_date'] === null && $out['admission_date'] !== null) {
            $out['joining_date'] = $out['admission_date'];
        }
        return $out;
    }

    private function normalizeOptionalAdmissionDate(mixed $value): ?string
    {
        $date = trim((string) ($value ?? ''));

        if ($date === '') {
            return null;
        }

        if (preg_match('/^\d{2}-\d{2}-\d{4}$/', $date) === 1) {
            $date = substr($date, 6, 4) . '-' . substr($date, 3, 2) . '-' . substr($date, 0, 2);
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
            throw new RuntimeException('Invalid admission date.');
        }

        [$year, $month, $day] = array_map('intval', explode('-', $date));
        if (! checkdate($month, $day, $year)) {
            throw new RuntimeException('Invalid admission date.');
        }

        return $date;
    }

    private function normalizeMoney(string $value): string
    {
        if (preg_match('/^\d+(?:\.\d{1,2})?$/', $value) !== 1) {
            throw new RuntimeException('Invalid fee amount.');
        }

        $parts = explode('.', $value, 2);
        $whole = $parts[0];
        $fraction = $parts[1] ?? '';
        if (strlen($whole) > 10) {
            throw new RuntimeException('Invalid fee amount.');
        }
        $fraction = str_pad($fraction, 2, '0');
        $cents = ((int) $whole * 100) + (int) substr($fraction, 0, 2);

        return $this->centsToMoney($cents);
    }

    private function normalizeTaxRate(string $value): string
    {
        if (preg_match('/^(?:\d{1,3})(?:\.\d{1,2})?$/', $value) !== 1) {
            throw new RuntimeException('Invalid tax rate.');
        }

        $rate = (float) $value;
        if ($rate < 0 || $rate > 100) {
            throw new RuntimeException('Invalid tax rate.');
        }

        return number_format($rate, 2, '.', '');
    }

    private function moneyToCents(mixed $value): int
    {
        $value = trim((string) ($value ?? ''));

        if ($value === '') {
            return 0;
        }

        [$whole, $fraction] = explode('.', $this->normalizeMoney($value), 2);

        return ((int) $whole * 100) + (int) $fraction;
    }

    private function centsToMoney(int $cents): string
    {
        return intdiv($cents, 100) . '.' . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    private function normalizeMultiPlanValue(string $field, mixed $value, array $options): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $wasArray = is_array($value);
        if ($wasArray) {
            $values = $value;
        } elseif (is_scalar($value)) {
            $decoded = json_decode((string) $value, true);
            $values = is_array($decoded) ? $decoded : [(string) $value];
        } else {
            throw new RuntimeException('Invalid admission details.');
        }

        $normalized = [];
        foreach ($values as $selected) {
            if ($selected === '') {
                continue;
            }

            if (! is_scalar($selected)) {
                throw new RuntimeException('Invalid admission details.');
            }

            $selected = trim((string) $selected);
            if (! in_array($selected, $options, true)) {
                throw new RuntimeException('Invalid coaching plan option.');
            }

            if (! in_array($selected, $normalized, true)) {
                $normalized[] = $selected;
            }
        }

        if ($normalized === []) {
            return null;
        }

        if (! $wasArray && count($normalized) === 1) {
            return $normalized[0];
        }

        return json_encode($normalized, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    /** @return list<string> */
    private function legacyCoachingTypes(array $input): array
    {
        if (array_key_exists('coaching_type', $input)) {
            return is_array($input['coaching_type'])
                ? $input['coaching_type']
                : [$input['coaching_type']];
        }

        $types = [];
        if (($input['personal_coaching_days'] ?? null) !== null
            || ($input['personal_coaching_duration'] ?? null) !== null
            || ($input['estimated_personal_session'] ?? null) !== null) {
            $types[] = 'Personal coaching';
        }
        if (($input['group_coaching_days'] ?? null) !== null) {
            $types[] = 'Group coaching';
        }
        if (($input['elite_camp'] ?? null) === 'Yes') {
            $types[] = 'Elite';
        }

        return $types;
    }

    /** @return list<string> */
    private function decodeMultiPlanValue(?string $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? array_values(array_map('strval', $decoded)) : [$value];
    }

    private function validateExtendedFields(
        array $profile,
        int $branch,
        object $db,
        int $historicalCoachId = 0,
        int $historicalPersonalCoachId = 0,
    ): void
    {
        foreach ([
            'coach_user_id' => $historicalCoachId,
            'personal_coach_user_id' => $historicalPersonalCoachId,
        ] as $coachField => $historicalId) {
            $coachId = (int) ($profile[$coachField] ?? 0);
            if ($coachId < 1) {
                continue;
            }

            $isActiveAuthorized = $db->table('user_branches ub')
                ->join('users u', 'u.id = ub.user_id')
                ->join('user_roles ur', 'ur.user_id = u.id')
                ->join('roles r', 'r.id = ur.role_id')
                ->join('coach_profiles cp', 'cp.user_id = u.id')
                ->where([
                    'ub.user_id' => $coachId,
                    'ub.branch_id' => $branch,
                    'u.is_enabled' => 1,
                    'cp.is_active' => 1,
                ])
                ->whereIn('r.name', ['Coach', 'Fitness Coach'])
                ->countAllResults() > 0;

            if (! $isActiveAuthorized) {
                $historicalCoachIsValid = $coachId === $historicalId
                    && $db->table('user_branches ub')
                    ->join('user_roles ur', 'ur.user_id = ub.user_id')
                    ->join('roles r', 'r.id = ur.role_id')
                    ->where([
                        'ub.user_id' => $coachId,
                        'ub.branch_id' => $branch,
                    ])
                    ->whereIn('r.name', ['Coach', 'Fitness Coach'])
                    ->countAllResults() > 0;

                if (! $historicalCoachIsValid) {
                    throw new RuntimeException('Invalid admission Coach.');
                }
            }
        }
    }

    /** @return list<array<string, int|string|null>> */
    private function normalizeInstallmentRows(array $input, string $prefix): array
    {
        $plan = trim((string) ($input[$prefix . 'plan'] ?? ''));
        if ($plan === '' || $plan === 'Full Payment') {
            return [];
        }

        if ($plan !== 'Instalment Plan') {
            throw new RuntimeException('Invalid payment plan.');
        }

        $years = $input[$prefix . 'year'] ?? [];
        if (! is_array($years) || $years === []) {
            return [];
        }

        $columns = [];
        foreach (range(1, 4) as $number) {
            foreach ([$prefix . $number . '_date', $prefix . $number] as $key) {
                $values = $input[$key] ?? [];
                $columns[$key] = is_array($values) ? $values : [];
            }
        }

        $rows = [];
        $seenYears = [];
        foreach ($years as $index => $year) {
            $values = [];
            $hasValue = false;
            foreach (range(1, 4) as $number) {
                $dateValue = trim((string) ($columns[$prefix . $number . '_date'][$index] ?? ''));
                $amountValue = trim((string) ($columns[$prefix . $number][$index] ?? ''));
                $values[$number] = [$dateValue, $amountValue];
                $hasValue = $hasValue || $dateValue !== '' || $amountValue !== '';
            }
            if (! $hasValue) {
                continue;
            }

            if (! is_scalar($year) || ! ctype_digit((string) $year) || (int) $year < 1 || (int) $year > 99) {
                throw new RuntimeException('Invalid instalment year.');
            }

            $yearNumber = (int) $year;
            if (isset($seenYears[$yearNumber])) {
                throw new RuntimeException('Duplicate instalment year.');
            }
            $seenYears[$yearNumber] = true;

            $row = [
                'year_number' => $yearNumber,
            ];
            foreach (range(1, 4) as $number) {
                [$rawDate, $rawAmount] = $values[$number];
                $date = $this->normalizeInstallmentDate($rawDate);
                if ($rawDate !== '' && $date === null) {
                    throw new RuntimeException('Every supplied instalment date must be valid.');
                }
                $row['installment_' . $number . '_date'] = $date;

                if ($rawAmount !== '' && preg_match('/^\d+(?:\.\d{1,2})?$/', $rawAmount) !== 1) {
                    throw new RuntimeException('Every supplied instalment amount must be numeric.');
                }
                $row['installment_' . $number] = $rawAmount === ''
                    ? null
                    : $this->normalizeMoney($rawAmount);
            }
            $rows[] = $row;
        }

        return $rows;
    }

    private function normalizeInstallmentDate(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $date = trim((string) $value);
        if ($date === '') {
            return null;
        }

        if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $date, $matches) === 1) {
            $date = $matches[3] . '-' . $matches[2] . '-' . $matches[1];
        }

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $matches) !== 1
            || ! checkdate((int) $matches[2], (int) $matches[3], (int) $matches[1])) {
            return null;
        }

        return $date;
    }

    /** @return list<array<string, int|string|null>> */
    private function loadInstallmentRows(int $admissionId, string $scheduleType, int $periodId = 0): array
    {
        $table = self::INSTALLMENT_TABLES[$scheduleType] ?? null;
        $db = Database::connect('default');

        if ($table === null || ! $db->tableExists($table)) {
            return [];
        }

        $query = $db->table($table . ' s')
            ->select('s.*, p.period_number')
            ->join('admission_periods p', 'p.id = s.admission_period_id', 'left')
            ->where('s.admission_id', $admissionId);

        if ($periodId > 0) {
            $query->where('s.admission_period_id', $periodId);
        }

        $sourceRows = $query
            ->orderBy('s.admission_period_id', 'DESC')
            ->orderBy('s.year_number')
            ->orderBy('s.installment_number')
            ->get()
            ->getResultArray();
        $rows = [];

        foreach ($sourceRows as $sourceRow) {
            $key = (string) ($sourceRow['admission_period_id'] ?? '0') . ':' . (string) $sourceRow['year_number'];
            if (! isset($rows[$key])) {
                $rows[$key] = [
                    'admission_period_id' => $sourceRow['admission_period_id'] ?? null,
                    'period_number' => $sourceRow['period_number'] ?? null,
                    'year_number' => $sourceRow['year_number'],
                    'year_label' => $sourceRow['year_label'] ?? null,
                ];
            }

            $number = (int) $sourceRow['installment_number'];
            $rows[$key]['installment_' . $number . '_date'] = $sourceRow['due_date'];
            $rows[$key]['installment_' . $number] = $sourceRow['amount'];
        }

        return array_values($rows);
    }

    private function currentPeriodId(object $db, int $admissionId): ?int
    {
        if (! $db->tableExists('admission_periods')) {
            return null;
        }

        $period = $db->table('admission_periods')
            ->where(['admission_id' => $admissionId, 'status' => 'current'])
            ->orderBy('period_number', 'DESC')
            ->get()
            ->getRowArray();

        return is_array($period) ? (int) $period['id'] : null;
    }

    /** @param list<array<string, int|string|null>> $rows */
    private function saveInstallmentRows(
        object $db,
        int $admissionId,
        array $rows,
        string $scheduleType,
        ?int $periodId,
        int $actor,
    ): void {
        $table = self::INSTALLMENT_TABLES[$scheduleType] ?? null;

        if ($table === null || ! $db->tableExists($table)) {
            throw new RuntimeException('Admission installment storage is not ready.');
        }

        $deleteQuery = $db->table($table)->where('admission_id', $admissionId);
        if ($periodId !== null) {
            $deleteQuery->where('admission_period_id', $periodId);
        }
        $deleteQuery->delete();

        $now = date('Y-m-d H:i:s');
        foreach ($rows as $row) {
            $yearNumber = (int) ($row['year_number'] ?? 0);
            foreach (range(1, 4) as $number) {
                $date = $row['installment_' . $number . '_date'] ?? null;
                $amount = $row['installment_' . $number] ?? null;
                if ($date === null && $amount === null) {
                    continue;
                }
                if (! $db->table($table)->insert([
                    'admission_id' => $admissionId,
                    'admission_period_id' => $periodId,
                    'year_number' => $yearNumber,
                    'year_label' => 'Year ' . $yearNumber,
                    'installment_number' => $number,
                    'due_date' => $date,
                    'amount' => $amount === null ? null : $this->normalizeMoney((string) $amount),
                    'created_by' => $actor,
                    'version' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])) {
                    throw new RuntimeException($this->databaseFailureMessage($db, 'Unable to save Admission instalment.'));
                }
            }
        }

        service('auditPersistence')->record($actor, 'admission_installments_saved', 'admission', (string) $admissionId,
            (int) ($db->table('admissions')->select('branch_id')->where('id', $admissionId)->get()->getRow('branch_id') ?? 0), null, [
                'schedule' => $scheduleType,
                'period_id' => $periodId,
                'year_count' => count($rows),
            ]);
    }

    /** @return array<string, ?string> */
    private function masterSnapshots(array $profile, int $branch, object $db, int $historicalAgeGroupId = 0): array
    {
        $maps = [
            'age_group_id' => ['table' => 'admission_age_groups', 'label' => 'name', 'snapshot' => 'age_group'],
        ];
        $snapshots = [];
        foreach ($maps as $field => $definition) {
            $id = (int) ($profile[$field] ?? 0);
            if ($id < 1) {
                $snapshots[$definition['snapshot']] = null;
                continue;
            }
            $query = $db->table($definition['table'])
                ->groupStart()
                ->where('branch_id', $branch)
                ->orWhere('branch_id', null)
                ->groupEnd()
                ->where('id', $id);
            if ($field === 'age_group_id' && $id === $historicalAgeGroupId) {
                $query->groupStart()->where('is_active', 1)->orWhere('is_active', 0)->groupEnd();
            } else {
                $query->where('is_active', 1);
            }
            $row = $query->get()->getRowArray();
            if (! is_array($row)) {
                throw new RuntimeException('Invalid Admission master.');
            }
            $snapshots[$definition['snapshot']] = (string) ($row[$definition['label']] ?? '');
        }

        return $snapshots;
    }
    /** @return array<string, mixed> */
    private function completionFields(array $input): array
    {
        $normalized = $input;
        $joiningDate = trim((string) ($normalized['joining_date'] ?? ''));
        if (preg_match('/^\d{2}-\d{2}-\d{4}$/', $joiningDate) === 1) {
            $normalized['joining_date'] = substr($joiningDate, 6, 4) . '-' . substr($joiningDate, 3, 2) . '-' . substr($joiningDate, 0, 2);
        }
        return $this->intakeFields($normalized);
    }
    public function find(int $admissionId): ?array
    {
        $admission = $this->loadAdmission($admissionId, false);
        $this->authorize((int) $admission['branch_id'], 'admission.view');
        return (new AdmissionModel())->where('id', $admissionId)->first();
    }
    /** @param array<string, mixed> $checklist */
    public function updateChecklist(int $admissionId, array $checklist): void
    {
        $db = Database::connect('default');
        $db->transStart();
        try {
            $admission = $this->loadAdmission($admissionId, true);
            $actor = $this->authorize((int) $admission['branch_id'], 'admission.update');
            if (in_array($admission['status'], [self::REJECTED, self::CONVERTED], true)) {
                throw new RuntimeException('Admission is terminal.');
            }
            $unknown = array_diff(array_keys($checklist), self::CHECKLIST_FIELDS);
            if ($unknown !== [] || $checklist === []) {
                throw new RuntimeException('Invalid checklist.');
            }
            $updates = [];
            foreach (self::CHECKLIST_FIELDS as $field) {
                if (array_key_exists($field, $checklist)) {
                    if (!is_bool($checklist[$field]) && !in_array($checklist[$field], [0, 1, '0', '1'], true)) {
                        throw new RuntimeException('Invalid checklist.');
                    }
                    $updates[$field] = is_bool($checklist[$field]) ? (int) $checklist[$field] : (int) $checklist[$field];
                }
            }
            $merged = [];
            foreach (self::CHECKLIST_FIELDS as $field) {
                $merged[$field] = $updates[$field] ?? (int) $admission[$field];
            }
            $updates['checklist_complete'] = count(array_filter($merged)) === count(self::CHECKLIST_FIELDS) ? 1 : 0;
            $updates['updated_at'] = date('Y-m-d H:i:s');
            $db->table('admissions')->where('id', $admissionId)->update($updates);
            service('auditPersistence')->record($actor, 'admission_checklist_updated', 'admission', (string) $admissionId,
                (int) $admission['branch_id'], null, $this->auditChecklist($merged));
            $db->transComplete();
            $this->assertTransactionSucceeded($db, 'Unable to update checklist.');
        } catch (Throwable $exception) {
            $db->transRollback();
            $db->resetTransStatus();
            throw $exception;
        }
    }
    public function transition(int $admissionId, string $newStatus, ?string $reason = null): void
    {
        $newStatus = strtoupper(trim($newStatus));
        if (!array_key_exists($newStatus, self::TRANSITIONS)) {
            throw new RuntimeException('Invalid status.');
        }
        if (in_array($newStatus, [self::CONVERTED], true)) {
            $current = $this->loadAdmission($admissionId, false);
            if ((string) $current['status'] !== self::APPROVED) {
                throw new RuntimeException('Invalid transition.');
            }
            $this->convert($admissionId);
            return;
        }
        $db = Database::connect('default');
        $db->transStart();
        try {
            $admission = $this->loadAdmission($admissionId, true);
            $actor = $this->authorize((int) $admission['branch_id'], 'admission.update');
            if (!in_array($newStatus, self::TRANSITIONS[(string) $admission['status']], true)) {
                throw new RuntimeException('Invalid transition.');
            }
            $reason = $reason === null ? null : trim($reason);
            $isReopening = (string) $admission['status'] === self::REJECTED && $newStatus === self::IN_REVIEW;
            if (($isReopening || in_array($newStatus, [self::REJECTED, self::WITHDRAWN], true))
                && ($reason === null || $reason === '')) {
                throw new RuntimeException('Reason required.');
            }
            $now = date('Y-m-d H:i:s');
            $updates = ['status' => $newStatus, 'updated_at' => $now];
            if ($newStatus === self::APPROVED) {
                $updates += ['approved_by' => $actor, 'approved_at' => $now];
            }
            $db->table('admissions')->where(['id' => $admissionId, 'status' => $admission['status']])->update($updates);
            $this->recordHistory($db, $admissionId, $actor, (string) $admission['status'], $newStatus, $reason, $now);
            service('auditPersistence')->record($actor, 'admission_status_changed', 'admission', (string) $admissionId, (int) $admission['branch_id'],
                ['status' => $admission['status']], ['status' => $newStatus], $reason);
            $db->transComplete();
            $this->assertTransactionSucceeded($db, 'Unable to change admission status.');
        } catch (Throwable $exception) {
            $db->transRollback();
            $db->resetTransStatus();
            throw $exception;
        }
    }
    public function complete(int $admissionId, array $input): int
    {
        $db = Database::connect('default');
        $db->transStart();
        try {
            $admission = $this->loadAdmission($admissionId, true);
            $actor = $this->authorize((int) $admission['branch_id'], 'admission.update');
            $this->authorize((int) $admission['branch_id'], 'player.convert');
            if ($admission['converted_player_id'] !== null) {
                $this->linkAdmissionContacts($db, $admission, (int) $admission['converted_player_id'], $actor, date('Y-m-d H:i:s'));
                $db->transComplete();
                return (int) $admission['converted_player_id'];
            }
            if ((string) $admission['status'] !== self::INTAKE) {
                throw new RuntimeException('Admission is not pending.');
            }
            $profile = $this->completionFields($input);
            if ($profile['batch_id'] === null || $profile['joining_date'] === null) {
                throw new RuntimeException('Batch and admission date are required.');
            }
            $batch = $db->table('batches')->where(['id' => (int) $profile['batch_id'], 'is_active' => 1])
                ->get()->getRowArray();
            if (!is_array($batch)) {
                throw new RuntimeException('Invalid admission details.');
            }
            $now = date('Y-m-d H:i:s');
            $updates = array_merge($profile, [
                'checklist_complete' => 1,
                'checklist_identity_complete' => 1,
                'checklist_contact_complete' => 1,
                'checklist_admission_complete' => 1,
                'checklist_branch_confirmed' => 1,
                'checklist_approval_recorded' => 1,
                'approved_by' => $actor,
                'approved_at' => $now,
                'status' => self::APPROVED,
                'updated_at' => $now,
            ]);
            $db->table('admissions')->where(['id' => $admissionId, 'status' => self::INTAKE, 'converted_player_id' => null])->update($updates);
            $this->recordHistory($db, $admissionId, $actor, self::INTAKE, self::APPROVED, 'Admission completed.', $now);
            service('auditPersistence')->record($actor, 'admission_completed', 'admission', (string) $admissionId, (int) $admission['branch_id'],
                ['status' => self::INTAKE], ['status' => self::APPROVED, 'batch_id' => (int) $profile['batch_id']]);
            $admission = array_merge($admission, $updates);
            $playerId = $this->convertApprovedWithinTransaction($db, $admission, $actor, $now);
            $db->transComplete();
            $this->assertTransactionSucceeded($db, 'Unable to complete admission.');
            return $playerId;
        } catch (Throwable $exception) {
            $db->transRollback();
            $db->resetTransStatus();
            throw $exception;
        }
    }
    public function convert(int $admissionId): int
    {
        $db = Database::connect('default');
        $db->transStart();
        try {
            $admission = $this->loadAdmission($admissionId, true);
            $actor = $this->authorize((int) $admission['branch_id'], 'player.convert');
            if ($admission['converted_player_id'] !== null) {
                $this->linkAdmissionContacts($db, $admission, (int) $admission['converted_player_id'], $actor, date('Y-m-d H:i:s'));
                $db->transComplete();
                return (int) $admission['converted_player_id'];
            }
            if ((string) $admission['status'] !== self::APPROVED) {
                throw new RuntimeException('Admission is not approved.');
            }
            $now = date('Y-m-d H:i:s');
            $playerId = $this->convertApprovedWithinTransaction($db, $admission, $actor, $now);
            $db->transComplete();
            $this->assertTransactionSucceeded($db, 'Unable to convert admission.');
            return $playerId;
        } catch (Throwable $exception) {
            $db->transRollback();
            throw $exception;
        }
    }
    private function convertApprovedWithinTransaction(object $db, array $admission, int $actor, string $now): int
    {
        $query = $db->table('players')->where('admission_id', (int) $admission['id']);
        if ($admission['lead_id'] !== null) {
            $query->orWhere('lead_id', (int) $admission['lead_id']);
        }
        $existing = $query->get()->getRowArray();
        if (is_array($existing) && (int) ($existing['admission_id'] ?? 0) !== (int) $admission['id']) {
            throw new RuntimeException('Conflicting player lineage.');
        }
        if (is_array($existing)) {
            $playerId = (int) $existing['id'];
        } else {
            $lead = $admission['lead_id'] === null ? null : $db->table('leads')->where('id', (int) $admission['lead_id'])->get()->getRowArray();
            $source = is_array($lead) ? $lead : ['full_name' => $admission['applicant_full_name'], 'email' => $admission['applicant_email'],
                'phone' => $admission['applicant_phone'], 'date_of_birth' => $admission['applicant_date_of_birth']];
            if (trim((string) ($source['full_name'] ?? '')) === '') {
                throw new RuntimeException('Admission identity is incomplete.');
            }
            $db->table('persons')->insert(['full_name' => $source['full_name'], 'email' => $source['email'], 'phone' => $source['phone'],
                'date_of_birth' => $source['date_of_birth'], 'created_at' => $now, 'updated_at' => $now]);
            $personId = (int) $db->insertID();
            $db->table('players')->insert(['person_id' => $personId,
                'lead_id' => $admission['lead_id'] === null ? null : (int) $admission['lead_id'], 'admission_id' => (int) $admission['id'],
                    'branch_id' => (int) $admission['branch_id'], 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
            $playerId = (int) $db->insertID();
            $db->table('player_branch_history')->insert(['player_id' => $playerId, 'branch_id' => (int) $admission['branch_id'],
                'effective_from' => $now, 'created_at' => $now]);
        }
        $this->assertConversionPlacementConsistency($db, $admission);
        service('document')->linkAdmissionToPlayer(
            $db,
            (int) $admission['id'],
            $playerId,
            (int) $admission['branch_id'],
            $actor,
            $now
        );
        $this->linkAdmissionContacts($db, $admission, $playerId, $actor, $now);
        $batchId = $admission['batch_id'] === null ? null : (int) $admission['batch_id'];
        if ($batchId !== null && $db->table('player_batch_assignments')->where(['player_id' => $playerId, 'effective_to' => null])->countAllResults() === 0) {
            $effectiveFrom = ((string) ($admission['joining_date'] ?? '') !== '' ? (string) $admission['joining_date'] . ' 00:00:00' : $now);
            $db->table('player_batch_assignments')->insert(['player_id' => $playerId, 'branch_id' => (int) $admission['branch_id'],
                'batch_id' => $batchId, 'assigned_by' => $actor, 'effective_from' => $effectiveFrom, 'effective_to' => null,
                'created_at' => $now, 'updated_at' => $now]);
        }
        $coachId = (int) ($admission['coach_user_id'] ?? 0);
        if ($coachId > 0
            && $db->table('player_coach_assignments')->where([
                'player_id' => $playerId,
                'effective_to' => null,
            ])->countAllResults() === 0
        ) {
            $db->table('player_coach_assignments')->insert([
                'player_id' => $playerId,
                'branch_id' => (int) $admission['branch_id'],
                'coach_user_id' => $coachId,
                'assigned_by' => $actor,
                'effective_from' => $now,
                'effective_to' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
        $db->table('admissions')->where(['id' => (int) $admission['id'], 'status' => self::APPROVED, 'converted_player_id' => null])
            ->update(['status' => self::CONVERTED, 'converted_player_id' => $playerId, 'updated_at' => $now]);
        $this->recordHistory($db, (int) $admission['id'], $actor, self::APPROVED, self::CONVERTED, null, $now);
        service('auditPersistence')->record($actor, 'admission_converted', 'admission', (string) $admission['id'], (int) $admission['branch_id'],
            ['status' => self::APPROVED], ['status' => self::CONVERTED,
                'lead_id' => $admission['lead_id'] === null ? null : (int) $admission['lead_id'],
                'player_id' => $playerId,
                'batch_id' => $batchId,
                'coach_user_id' => $coachId > 0 ? $coachId : null,
            ]);
        return $playerId;
    }

    /** @param array<string, mixed> $admission */
    private function assertConversionPlacementConsistency(object $db, array $admission): void
    {
        $branchId = (int) ($admission['branch_id'] ?? 0);
        $batchId = (int) ($admission['batch_id'] ?? 0);

        if ($batchId > 0) {
            $batch = $db->table('batches')
                ->where('id', $batchId)
                ->groupStart()
                ->where('is_active', 1)
                ->orWhere('is_active IS NULL', null, false)
                ->groupEnd()
                ->get()
                ->getRowArray();

            if (! is_array($batch)
                || (($batch['branch_id'] ?? null) !== null
                    && (int) $batch['branch_id'] > 0
                    && (int) $batch['branch_id'] !== $branchId)
            ) {
                throw new RuntimeException('Admission batch does not belong to the Admission branch.');
            }
        }

        $this->validateExtendedFields(
            [
                'coach_user_id' => $admission['coach_user_id'] ?? null,
                'personal_coach_user_id' => $admission['personal_coach_user_id'] ?? null,
            ],
            $branchId,
            $db,
        );
    }
    private function linkAdmissionContacts(object $db, array $admission, int $playerId, int $actor, string $now): void
    {
        $playerService = service('player', false);
        $guardianDetailsAvailable = true;
        foreach (['father_name', 'mother_name'] as $field) {
            $name = trim((string) ($admission[$field] ?? ''));
            if ($name === '') {
                continue;
            }
            $created = $playerService->linkContactWithinTransaction(
                $db,
                $playerId,
                (int) $admission['branch_id'],
                $actor,
                'guardian',
                $name,
                $guardianDetailsAvailable ? ($admission['parent_email'] ?? null) : null,
                $guardianDetailsAvailable ? ($admission['parent_mobile'] ?? null) : null,
                (string) $admission['id'],
                $now,
            );
            if ($created) {
                $guardianDetailsAvailable = false;
            }
        }
        $emergency = trim((string) ($admission['emergency_contact'] ?? ''));
        if ($emergency !== '') {
            $playerService->linkContactWithinTransaction(
                $db,
                $playerId,
                (int) $admission['branch_id'],
                $actor,
                'emergency-contact',
                $emergency,
                null,
                null,
                (string) $admission['id'],
                $now,
            );
        }
    }
    /** @return list<array<string, mixed>> */
    private function periodsForAdmission(int $admissionId, ?object $db = null): array
    {
        $db ??= Database::connect('default');

        if (! $db->tableExists('admission_periods')) {
            return [];
        }

        return $db->table('admission_periods')
            ->where('admission_id', $admissionId)
            ->orderBy('period_number', 'DESC')
            ->get()
            ->getResultArray();
    }

    /** @return array<string, mixed>|null */
    private function currentActivePeriod(
        int $admissionId,
        int $branchId,
        ?object $db = null,
    ): ?array
    {
        $db ??= Database::connect('default');

        if (! $db->tableExists('admission_periods')) {
            return null;
        }

        $period = $db->table('admission_periods')
            ->where([
                'admission_id' => $admissionId,
                'branch_id' => $branchId,
                'status' => 'current',
            ])
            ->orderBy('period_number', 'DESC')
            ->get()
            ->getRowArray();

        if (is_array($period)) {
            return $period;
        }

        $rows = $db->table('admission_periods')
            ->where([
                'admission_id' => $admissionId,
                'branch_id' => $branchId,
            ])
            ->orderBy('period_number', 'DESC')
            ->get()
            ->getResultArray();

        foreach ($rows as $row) {
            $status = strtolower(trim((string) ($row['status'] ?? '')));
            if ($status !== 'historical') {
                return $row;
            }
        }

        return null;
    }

    /** @return array<string, mixed>|null */
    private function renewalCurrentPeriod(array $admission, ?object $db = null): ?array
    {
        $db ??= Database::connect('default');
        $admissionId = (int) ($admission['id'] ?? 0);
        $branchId = (int) ($admission['branch_id'] ?? 0);
        $period = $this->currentActivePeriod($admissionId, $branchId, $db);
        $admissionEndDate = $this->admissionEndDate($admission);

        if (is_array($period)) {
            if ($this->periodEndDate($period) !== null) {
                return $period;
            }

            if ($admissionEndDate !== null) {
                $period['_renewal_period_end_raw'] = $period['end_date'] ?? ($period['period_end_date'] ?? null);
                $period['_renewal_end_source'] = 'admissions.' . ($this->admissionEndField($admission) ?? 'unknown');
                $period['end_date'] = $admissionEndDate;

                return $period;
            }

            return $period;
        }

        if ($admissionEndDate === null) {
            return null;
        }

        $periods = $this->periodsForAdmission($admissionId, $db);
        $latest = $periods[0] ?? [];

        return [
            'id' => null,
            'period_number' => (int) ($latest['period_number'] ?? 0),
            'start_date' => $this->admissionStartDate($admission),
            'end_date' => $admissionEndDate,
            'fee_plan_label' => $admission['fee_plan_label'] ?? null,
            'status' => 'current',
            '_renewal_end_source' => 'admissions.' . ($this->admissionEndField($admission) ?? 'unknown'),
        ];
    }

    private function admissionStartDate(array $admission): ?string
    {
        foreach (['admission_start_date', 'joining_date', 'admission_date', 'year1_start_date'] as $field) {
            $date = $this->normalizeRenewalDate($admission[$field] ?? null);
            if ($date !== null) {
                return $date;
            }
        }

        return null;
    }

    private function admissionEndDate(array $admission): ?string
    {
        foreach (['admission_end_date', 'year1_end_date', 'year2_end_date', 'year3_end_date'] as $field) {
            $date = $this->normalizeRenewalDate($admission[$field] ?? null);
            if ($date !== null) {
                return $date;
            }
        }

        return null;
    }

    private function periodEndDate(array $period): ?string
    {
        foreach (['period_end_date', 'end_date'] as $field) {
            $date = $this->normalizeRenewalDate($period[$field] ?? null);
            if ($date !== null) {
                return $date;
            }
        }

        return null;
    }

    private function periodEndField(array $period): ?string
    {
        foreach (['period_end_date', 'end_date'] as $field) {
            if ($this->normalizeRenewalDate($period[$field] ?? null) !== null) {
                return $field;
            }
        }

        return null;
    }

    private function admissionEndField(array $admission): ?string
    {
        foreach (['admission_end_date', 'year1_end_date', 'year2_end_date', 'year3_end_date'] as $field) {
            if ($this->normalizeRenewalDate($admission[$field] ?? null) !== null) {
                return $field;
            }
        }

        return null;
    }

    private function logRenewalDateComparison(
        int $admissionId,
        array $admission,
        ?array $current,
        mixed $submittedStart,
        ?string $currentEndDate,
        ?string $renewalStartDate,
    ): void {
        if (defined('ENVIRONMENT') && ENVIRONMENT !== 'development') {
            return;
        }

        $periodField = is_array($current) ? $this->periodEndField($current) : null;
        $admissionField = $this->admissionEndField($admission);
        $source = is_string($current['_renewal_end_source'] ?? null)
            ? $current['_renewal_end_source']
            : ($periodField !== null
                ? 'admission_periods.' . $periodField
                : ($admissionField !== null ? 'admissions.' . $admissionField : 'none'));
        $periodRaw = $current['_renewal_period_end_raw'] ?? ($periodField !== null ? ($current[$periodField] ?? null) : null);
        $result = $renewalStartDate !== null && $currentEndDate !== null
            ? ($renewalStartDate > $currentEndDate ? 'after' : 'not-after')
            : 'invalid-date';

        log_message('debug', 'Admission renewal date comparison: ' . json_encode([
            'admission_id' => $admissionId,
            'current_period_end_source' => $source,
            'current_period_end_raw' => $periodRaw,
            'admission_fallback_end_source' => $admissionField !== null ? 'admissions.' . $admissionField : null,
            'admission_fallback_end_raw' => $admissionField !== null ? ($admission[$admissionField] ?? null) : null,
            'parsed_current_end_date' => $currentEndDate,
            'submitted_renewal_start' => is_scalar($submittedStart) ? (string) $submittedStart : null,
            'parsed_renewal_start_date' => $renewalStartDate,
            'timezone' => config('App')->appTimezone,
            'comparison' => $result,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /** @param array<string, mixed> $admission */
    private function createInitialPeriod(object $db, array $admission, int $actor): void
    {
        if (! $db->tableExists('admission_periods')) {
            return;
        }

        $admissionId = (int) ($admission['id'] ?? 0);
        if ($admissionId < 1 || $db->table('admission_periods')->where('admission_id', $admissionId)->countAllResults() > 0) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $period = [
            'admission_id' => $admissionId,
            'branch_id' => (int) ($admission['branch_id'] ?? 0),
            'period_number' => 1,
            'start_date' => $admission['joining_date'] ?? null,
            'end_date' => $admission['year1_end_date'] ?? null,
            'age_group_id' => $this->nullableInteger($admission['age_group_id'] ?? null),
            'age_group_label' => $admission['age_group'] ?? null,
            'fee_plan_label' => $admission['fee_plan_label'] ?? null,
            'renewal_values' => json_encode([
                'age_group_id' => $admission['age_group_id'] ?? null,
                'fee_plan_label' => $admission['fee_plan_label'] ?? null,
                'coaching_type' => $admission['coaching_type'] ?? null,
                'personal_coaching_days' => $admission['personal_coaching_days'] ?? null,
                'personal_coaching_duration' => $admission['personal_coaching_duration'] ?? null,
                'estimated_personal_session' => $admission['estimated_personal_session'] ?? null,
                'group_coaching_days' => $admission['group_coaching_days'] ?? null,
                'coaching_delivery' => $admission['coaching_delivery'] ?? null,
                'fitness_plan' => $admission['fitness_plan'] ?? null,
                'gym_fitness_session' => $admission['gym_fitness_session'] ?? null,
                'match_program' => $admission['match_program'] ?? null,
                'induction_plan' => $admission['induction_plan'] ?? null,
                'review_frequency' => $admission['review_frequency'] ?? null,
                'progress_report_frequency' => $admission['progress_report_frequency'] ?? null,
                'feedback_call_frequency' => $admission['feedback_call_frequency'] ?? null,
                'meditation_sessions' => $admission['meditation_sessions'] ?? null,
                'elite_camp' => $admission['elite_camp'] ?? null,
                'elite_camp_days' => $admission['elite_camp_days'] ?? null,
                'elite_camp_duration' => $admission['elite_camp_duration'] ?? null,
                'estimated_elite_session' => $admission['estimated_elite_session'] ?? null,
                'student_diary' => $admission['student_diary'] ?? null,
                'welcome_kit' => $admission['welcome_kit'] ?? null,
                'installment_plan' => $admission['installment_plan'] ?? null,
                'hostel_installment_plan' => $admission['hostel_installment_plan'] ?? null,
            ], JSON_UNESCAPED_UNICODE),
            'status' => 'current',
            'created_by' => $actor,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        if (! $db->fieldExists('renewal_values', 'admission_periods')) {
            unset($period['renewal_values']);
        }

        $db->table('admission_periods')->insert(array_merge($period, $this->periodRatingSnapshot($admission)));
    }

    /** @return array<string, int|float|null> */
    private function periodRatingSnapshot(array $admission): array
    {
        return $this->admissionRatingProjection($admission);
    }

    /** @return array<string, int|float|null> */
    private function admissionRatingProjection(array $admission): array
    {
        $ratings = [];

        foreach (self::RATING_FIELDS as $field) {
            $ratings[$field] = $this->ratingService->numeric($admission[$field] ?? null);
        }

        return $ratings;
    }

    /** @return array<string, string|null> */
    private function ratingCategories(array $admission): array
    {
        $categories = [];

        foreach (self::RATING_FIELDS as $field) {
            $categories[$field] = $this->ratingService->category($admission[$field] ?? null);
        }

        return $categories;
    }

    private function validateTrialRelationship(mixed $trialId, int $leadId, int $branch, object $db): void
    {
        if ($trialId === null || $trialId === '') {
            return;
        }

        if (! is_scalar($trialId) || ! ctype_digit((string) $trialId) || (int) $trialId < 1) {
            throw new RuntimeException('Invalid admission details.');
        }

        $trial = $db->table('trials')
            ->where([
                'id' => (int) $trialId,
                'lead_id' => $leadId,
                'branch_id' => $branch,
            ])
            ->get()
            ->getRowArray();

        if (! is_array($trial)) {
            throw new RuntimeException('Invalid admission details.');
        }

    }

    /** @return array<string, mixed> */
    private function normalizeRenewalValues(array $input, array $admission): array
    {
        $current = $admission['current_period'] ?? [];
        $snapshot = [];
        if (is_array($current) && is_string($current['renewal_values'] ?? null)) {
            $decoded = json_decode($current['renewal_values'], true);
            $snapshot = is_array($decoded) ? $decoded : [];
        }

        $value = static function (string $field) use ($input, $admission, $snapshot): mixed {
            if (array_key_exists($field, $input)) {
                return $input[$field];
            }
            return $admission[$field] ?? ($snapshot[$field] ?? null);
        };

        $coachingTypes = $this->normalizeMultiPlanValue(
            'coaching_type',
            $value('coaching_type'),
            self::COACHING_TYPE_OPTIONS,
        );
        $coachingTypeValues = $this->decodeMultiPlanValue($coachingTypes);
        $values = [
            'age_group_id' => $value('age_group_id'),
            'fee_plan_label' => trim((string) ($admission['fee_plan_label'] ?? '')) ?: null,
            'coaching_type' => $coachingTypes,
        ];

        foreach (['training_fee', 'hostel_charges'] as $field) {
            $fee = trim((string) ($value($field) ?? ''));
            $values[$field] = $fee === '' ? null : $this->normalizeMoney($fee);
        }

        $taxableFee = trim((string) ($value('total_taxable_fee') ?? ''));
        $values['total_taxable_fee'] = $taxableFee === ''
            ? $this->calculateFeeTotal($values)
            : $this->normalizeMoney($taxableFee);
        $taxRate = trim((string) ($value('tax_rate') ?? ''));
        $values['tax_rate'] = $taxRate === '' ? null : $this->normalizeTaxRate($taxRate);
        $values += $this->calculateTaxInformation($values);

        foreach (self::PDF_PLAN_OPTIONS as $field => $options) {
            $normalized = $this->normalizeMultiPlanValue($field, $value($field), $options);
            $values[$field] = $normalized === null ? null : $this->decodeSinglePlanValue($normalized);
        }

        foreach (self::PDF_MULTI_PLAN_OPTIONS as $field => $options) {
            $values[$field] = $this->normalizeMultiPlanValue($field, $value($field), $options);
        }

        foreach (['estimated_personal_session', 'estimated_elite_session'] as $field) {
            $candidate = trim((string) ($value($field) ?? ''));
            if ($candidate !== '' && (! ctype_digit($candidate) || (int) $candidate > 9999)) {
                throw new RuntimeException('Estimated coaching sessions must be a non-negative whole number.');
            }
            $values[$field] = $candidate === '' ? null : $candidate;
        }

        if (! in_array('Personal coaching', $coachingTypeValues, true)) {
            $values['personal_coaching_days'] = null;
            $values['personal_coaching_duration'] = null;
            $values['estimated_personal_session'] = null;
        }
        if (! in_array('Group coaching', $coachingTypeValues, true)) {
            $values['group_coaching_days'] = null;
        }
        if (! in_array('Elite', $coachingTypeValues, true)) {
            $values['elite_camp_days'] = null;
            $values['elite_camp_duration'] = null;
            $values['estimated_elite_session'] = null;
        }
        $values['elite_camp'] = in_array('Elite', $coachingTypeValues, true) ? 'Yes' : 'No';

        if (in_array('Personal coaching', $coachingTypeValues, true)
            && ($values['personal_coaching_days'] === null || $values['personal_coaching_duration'] === null)) {
            throw new RuntimeException('Personal coaching details are required when Personal coaching is selected.');
        }
        if (in_array('Group coaching', $coachingTypeValues, true) && $values['group_coaching_days'] === null) {
            throw new RuntimeException('Group coaching days are required when Group coaching is selected.');
        }
        if (in_array('Elite', $coachingTypeValues, true)
            && ($values['elite_camp_days'] === null || $values['elite_camp_duration'] === null)) {
            throw new RuntimeException('Elite Camp details are required when Elite Camp is Yes.');
        }

        foreach (['installment_plan', 'hostel_installment_plan'] as $field) {
            $candidate = trim((string) ($value($field) ?? ''));
            if ($candidate !== '' && ! in_array($candidate, self::PAYMENT_PLAN_OPTIONS, true)) {
                throw new RuntimeException('Invalid payment plan.');
            }
            $values[$field] = $candidate === '' ? null : $candidate;
        }

        return $values;
    }

    /** @return array<string, mixed> */
    private function renewalAdmissionUpdates(array $values): array
    {
        $updates = [];
        foreach (array_keys($values) as $field) {
            if ($field === 'age_group_id' || $field === 'fee_plan_label') {
                continue;
            }
            $updates[$field] = $values[$field];
        }
        return $updates;
    }

    private function decodeSinglePlanValue(string $value): ?string
    {
        $decoded = json_decode($value, true);
        if (is_array($decoded)) {
            return (string) ($decoded[0] ?? '');
        }

        return $value === '' ? null : $value;
    }

    private function normalizeRenewalDate(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $date = trim((string) $value);
        if ($date === '') {
            return null;
        }

        $timezone = new \DateTimeZone(config('App')->appTimezone);
        $dateOnlyFormats = ['!Y-m-d', '!d-m-Y'];
        foreach ($dateOnlyFormats as $format) {
            $parsed = \DateTimeImmutable::createFromFormat($format, $date, $timezone);
            $errors = \DateTimeImmutable::getLastErrors();
            if ($parsed !== false && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                return $parsed->format('Y-m-d');
            }
        }

        foreach (['!Y-m-d H:i:s', '!d-m-Y H:i:s', \DateTimeInterface::ATOM] as $format) {
            if ($format === \DateTimeInterface::ATOM && ! str_contains($date, 'T')) {
                continue;
            }

            try {
                $parsed = $format === \DateTimeInterface::ATOM
                    ? new \DateTimeImmutable($date, $timezone)
                    : \DateTimeImmutable::createFromFormat($format, $date, $timezone);
            } catch (\Exception) {
                $parsed = false;
            }
            $errors = \DateTimeImmutable::getLastErrors();
            if ($parsed !== false && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                return $parsed->setTimezone($timezone)->format('Y-m-d');
            }
        }

        return null;
    }

    /** @param array<string, mixed> $input */
    private function renewalRequestHash(int $admissionId, array $input, array $masterInput): string
    {
        $payload = [
            'admission_id' => $admissionId,
            'expected_version' => (int) ($input['expected_version'] ?? 0),
            'start_date' => $this->normalizeRenewalDate($input['start_date'] ?? null),
            'end_date' => $this->normalizeRenewalDate($input['end_date'] ?? null),
            'values' => $masterInput,
        ];

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }

    private function nullableInteger(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $string = (string) $value;
        if (! ctype_digit($string) || (int) $string < 1) {
            throw new RuntimeException('Invalid Admission master.');
        }

        return (int) $string;
    }

    private function loadAdmission(int $id, bool $lock): array
    {
        $sql = 'SELECT a.*, l.branch_id AS authoritative_branch_id FROM admissions a LEFT JOIN leads l ON l.id = a.lead_id WHERE a.id = ? LIMIT 1' . ($lock ? ' FOR UPDATE' : '');
        $row = Database::connect('default')->query($sql, [$id])->getRowArray();
        if (!is_array($row)) {
            throw new RuntimeException('Not found.');
        }
        if ($row['authoritative_branch_id'] !== null && (int) $row['branch_id'] !== (int) $row['authoritative_branch_id']) {
            throw new RuntimeException('Admission branch mismatch.');
        }
        return $row;
    }
    private function authorize(int $branch, string $permission): int
    {
        if ($branch < 1 || !service('authorization')->can($permission, (string) $branch)) {
            throw new RuntimeException('Forbidden');
        }
        $user = session('user');
        if (!is_array($user) || !isset($user['id'])) {
            throw new RuntimeException('Authentication required');
        }
        return (int) $user['id'];
    }
    private function recordHistory(object $db, int $id, int $actor, ?string $previous, string $new, ?string $reason, string $now): void
    {
        $db->table('admission_status_history')->insert(['admission_id' => $id, 'actor_user_id' => $actor, 'previous_status' => $previous,
            'new_status' => $new, 'reason' => $reason, 'created_at' => $now]);
    }
    private function auditChecklist(array $checklist): array
    {
        return ['checklist_identity_complete' => $checklist['checklist_identity_complete'],
            'checklist_contact_complete' => $checklist['checklist_contact_complete'],
                'checklist_admission_complete' => $checklist['checklist_admission_complete'],
                    'checklist_branch_confirmed' => $checklist['checklist_branch_confirmed']];
    }
    private function assertTransactionSucceeded(object $db, string $message): void
    {
        if (!$db->transStatus()) {
            throw new RuntimeException($message);
        }
    }

    private function databaseFailureMessage(object $db, string $fallback): string
    {
        $error = $db->error();
        $message = trim((string) ($error['message'] ?? ''));

        return $message !== '' ? $fallback . ' ' . $message : $fallback;
    }
}
