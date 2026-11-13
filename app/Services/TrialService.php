<?php

declare (strict_types=1);
namespace App\Services;

use App\Modules\Trials\Models\TrialHistoryModel;
use App\Modules\Trials\Models\TrialModel;
use Config\Database;
use DateTimeImmutable;
use RuntimeException;
use Throwable;
final class TrialService
{
    private const BOOKED = 'booked';
    private const ATTENDED = 'attended';
    private const NO_SHOW = 'no_show';
    private const CANCELLED = 'cancelled';
    private const RESCHEDULED = 'rescheduled';
    private const RATING_FIELDS = TrialRatingService::FIELDS;

    private TrialRatingService $ratingService;

    public function __construct()
    {
        $this->ratingService = new TrialRatingService();
    }
    public function paginated(array $filters, int $page = 1, int $perPage = 20): array
    {
        $branches = $this->authorizedBranches();
        $page = max(1, $page);
        $perPage = min(100, max(1, $perPage));
        $filters = array_intersect_key($filters, array_flip(['q', 'status', 'branch_id', 'trial_date']));
        if ($branches === []) {
            return ['trials' => [], 'total' => 0, 'page' => 1, 'pages' => 1, 'filters' => $filters];
        }
        $query = (new TrialModel())->select(
            'trials.*, leads.full_name, leads.email, leads.phone, branches.name AS branch_name, '
            . 'primary_coach.display_name AS coach_name, secondary_coach.display_name AS secondary_coach_name, '
            . 'admission_test.id AS admission_test_id, admission_test.status AS admission_test_status, '
            . 'admission_test.submitted_at AS admission_test_submitted_at, '
            . "CASE WHEN admission_test.submitted_at IS NOT NULL OR admission_test.status IN ('TEST_SUBMITTED', 'MEETING_FOLLOW_UP', 'APPROVED_FOR_ADMISSION', 'REJECTED_CLOSED') THEN 1 ELSE 0 END AS admission_test_completed"
        )
            ->join('leads', 'leads.id = trials.lead_id')->join('branches', 'branches.id = trials.branch_id')
            ->join('users primary_coach', 'primary_coach.id = trials.coach_user_id', 'left')
            ->join('users secondary_coach', 'secondary_coach.id = trials.secondary_coach_user_id', 'left')
            ->join('admission_tests admission_test', 'admission_test.trial_id = trials.id AND admission_test.branch_id = trials.branch_id', 'left')
            ->whereIn('trials.branch_id', $branches);
        $actor = session('user');
        $actorId = is_array($actor) ? (int) ($actor['id'] ?? 0) : 0;
        if (! $this->canViewAllTrials($branches)) {
            $query->groupStart()
                ->where('trials.coach_user_id', $actorId)
                ->orWhere('trials.secondary_coach_user_id', $actorId)
                ->groupEnd();
        }
        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $query->groupStart()->like('leads.full_name', substr($q, 0, 100))->orLike('leads.email', substr($q, 0, 100))
                ->orLike('leads.phone', substr($q, 0, 100))->groupEnd();
        }
        if (($filters['status'] ?? '') !== '') {
            if (!in_array($filters['status'], [self::BOOKED, self::ATTENDED, self::NO_SHOW, self::CANCELLED, self::RESCHEDULED], true)) {
                return ['trials' => [], 'total' => 0, 'page' => 1, 'pages' => 1, 'filters' => $filters];
            }
            $query->where('trials.status', $filters['status']);
        }
        if (($filters['branch_id'] ?? '') !== '') {
            $branch = filter_var($filters['branch_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($branch === false || !in_array($branch, $branches, true)) {
                return ['trials' => [], 'total' => 0, 'page' => 1, 'pages' => 1, 'filters' => $filters];
            }
            $query->where('trials.branch_id', $branch);
        }
        $trialDate = trim((string) ($filters['trial_date'] ?? ''));
        if ($trialDate !== '') {
            $parsedTrialDate = DateTimeImmutable::createFromFormat('!d-m-Y', $trialDate);
            if (!$parsedTrialDate || $parsedTrialDate->format('d-m-Y') !== $trialDate) {
                return ['trials' => [], 'total' => 0, 'page' => 1, 'pages' => 1, 'filters' => $filters];
            }
            $query->where('trials.trial_date', $parsedTrialDate->format('Y-m-d'));
        }
        $total = $query->countAllResults(false);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);
        $rows = $query->orderBy('trials.trial_date', 'DESC')->orderBy('trials.trial_time', 'DESC')->limit($perPage, ($page - 1) * $perPage)->get()
            ->getResultArray();
        return ['trials' => $rows, 'total' => $total, 'page' => $page, 'pages' => $pages, 'filters' => $filters];
    }

    /** @return array<string, int> */
    public function statusCounts(): array
    {
        $counts = array_fill_keys([
            self::BOOKED,
            self::ATTENDED,
            self::NO_SHOW,
            self::CANCELLED,
            self::RESCHEDULED,
        ], 0);
        $branches = $this->authorizedBranches();

        if ($branches === []) {
            return $counts;
        }

        $query = (new TrialModel())
            ->select('trials.status, COUNT(*) AS total', false)
            ->whereIn('trials.branch_id', $branches);
        $actor = session('user');
        $actorId = is_array($actor) ? (int) ($actor['id'] ?? 0) : 0;

        if (! $this->canViewAllTrials($branches)) {
            $query->groupStart()
                ->where('trials.coach_user_id', $actorId)
                ->orWhere('trials.secondary_coach_user_id', $actorId)
                ->groupEnd();
        }

        foreach ($query->groupBy('trials.status')->get()->getResultArray() as $row) {
            $status = (string) ($row['status'] ?? '');
            if (array_key_exists($status, $counts)) {
                $counts[$status] = (int) $row['total'];
            }
        }

        return $counts;
    }
    public function availableBranches(): array
    {
        $ids = $this->authorizedBranches();
        return $ids === [] ? [] : Database::connect('default')->table('branches')
            ->whereIn('id', $ids)
            ->where('is_active', 1)
            ->orderBy('name')
            ->get()
            ->getResultArray();
    }
    public function availableCoaches(int $branch, string $permission = 'lead.view'): array
    {
        $this->authorize($branch, $permission);
        return Database::connect('default')->table('users u')->select('u.id,u.display_name')->distinct()
            ->join('user_branches ub', 'ub.user_id=u.id')
            ->join('user_roles ur', 'ur.user_id=u.id')
            ->join('roles r', 'r.id=ur.role_id')
            ->where(['ub.branch_id' => $branch, 'u.is_enabled' => 1])
            ->whereIn('r.name', ['Coach', 'Fitness Coach'])
            ->orderBy('u.display_name')->get()->getResultArray();
    }
    public function availableLocations(int $branch, string $permission = 'trial.book'): array
    {
        $this->authorize($branch, $permission);
        return Database::connect('default')->table('grounds')
            ->select('id, name')
            ->where(['branch_id' => $branch, 'is_active' => 1])
            ->orderBy('name', 'ASC')
            ->get()
            ->getResultArray();
    }
    public function isLeadBookable(array $lead): bool
    {
        return !$this->futureBookingExists(Database::connect('default'), (int) ($lead['id'] ?? 0));
    }
    public function bookingPage(int $leadId): array
    {
        $lead = Database::connect('default')
            ->table('leads')
            ->select('leads.*, branches.name AS branch_name')
            ->join('branches', 'branches.id = leads.branch_id')
            ->where('leads.id', $leadId)
            ->get()
            ->getRowArray();
        if (!is_array($lead)) {
            throw new RuntimeException('Not found.');
        }
        $this->authorize((int) $lead['branch_id'], 'trial.book');
        if (!$this->isLeadBookable($lead)) {
            throw new RuntimeException('Lead is not eligible for a trial.');
        }
        return ['lead' => $lead, 'coaches' => $this->availableCoaches((int) $lead['branch_id'])];
    }
    public function details(int $id): array
    {
        $trial = $this->load($id, false);
        $this->authorizeTrialDetails($trial);
        $trial['coach_ratings'] = $this->ratingProjection($trial);
        $trial['coach_rating_categories'] = $this->ratingCategories($trial);
        $trial['history'] = (new TrialHistoryModel())->select('trial_history.*, users.display_name AS actor_name')
            ->join('users', 'users.id = trial_history.actor_user_id', 'left')->where('trial_history.trial_id', $id)
                ->orderBy('trial_history.id', 'ASC')->findAll();
        $canManage = service('authorization')->can('lead.update', (string) $trial['branch_id']);
        $canView = service('authorization')->can('lead.view', (string) $trial['branch_id']);
        $actor = session('user');
        $actorId = is_array($actor) ? (int) ($actor['id'] ?? 0) : 0;
        $canRate = $this->canEditCoachRatings($trial, $actorId);
        $canAssignSecondary = $this->canAssignSecondaryCoach($trial, $actorId);
        $trial['canEditCoachRatings'] = $canRate;
        $trial['media'] = [];
        if (($canView || $canRate) && Database::connect('default')->tableExists('trial_media')) {
            $trial['media'] = service('trialMedia')->listForTrial($id);
        }
        $trial['actions'] = ['manage' => $canManage, 'secondary_assignment' => $canAssignSecondary,
            'attendance' => $canManage && $trial['status'] === self::BOOKED,
            'cancel' => $canManage && $trial['status'] === self::BOOKED, 'reschedule' => $canManage && $trial['status'] === self::BOOKED,
                'selection' => $canManage && $trial['status'] === self::ATTENDED && $trial['selection_outcome'] === 'pending',
                    'admission_pending' => $canManage && $trial['status'] === self::ATTENDED && $trial['selection_outcome'] === 'selected'
                        && $trial['lead_status'] !== 'admission pending',
                    'ratings' => $canRate, 'media_upload' => $canManage || $this->canReviewTrial($trial, $actorId)];
        return $trial;
    }

    /** @return array<string, int|float|null>|array{categories: array<string, string|null>} */
    public function ratingsForAdmission(int $id): array
    {
        $trial = $this->load($id, false);
        $this->authorize((int) $trial['branch_id'], 'admission.create');

        $ratings = [
            'lead_id' => (int) $trial['lead_id'],
            'branch_id' => (int) $trial['branch_id'],
        ];

        foreach ($this->ratingProjection($trial) as $field => $value) {
            $ratings[$field] = $value;
        }

        $ratings['categories'] = $this->ratingCategories($trial);

        return $ratings;
    }

    /** @return array<string, int|float|null> */
    private function ratingProjection(array $trial): array
    {
        $ratings = [];

        foreach (self::RATING_FIELDS as $field) {
            try {
                $ratings[$field] = $this->ratingService->numeric($trial[$field] ?? null);
            } catch (\InvalidArgumentException) {
                // Invalid legacy values must not prevent staff from opening a Trial.
                $ratings[$field] = null;
            }
        }

        return $ratings;
    }

    /** @return array<string, string|null> */
    private function ratingCategories(array $trial): array
    {
        $categories = [];

        foreach (self::RATING_FIELDS as $field) {
            try {
                $categories[$field] = $this->ratingService->category($trial[$field] ?? null);
            } catch (\InvalidArgumentException) {
                // Keep an invalid legacy value from breaking the detail projection.
                $categories[$field] = null;
            }
        }

        return $categories;
    }
    public function book(int $leadId, array $input): int
    {
        $db = Database::connect('default');
        $db->transStart();
        try {
            $lead = $db->query('SELECT id, branch_id FROM leads WHERE id = ? LIMIT 1 FOR UPDATE', [$leadId])->getRowArray();
            if (!is_array($lead)) {
                throw new RuntimeException('Not found.');
            }
            $branch = (int) $lead['branch_id'];
            $actor = $this->authorize($branch, 'trial.book');
            $data = $this->validatedSchedule($input, $branch);
            $this->assertCoachInBranch($db, $data['coach_user_id'], $branch);
            $this->assertCoachInBranch($db, $data['secondary_coach_user_id'], $branch, 'Invalid secondary coach.');
            if ($this->futureBookingExists($db, $leadId)) {
                throw new RuntimeException('A future trial is already booked.');
            }
            $now = date('Y-m-d H:i:s');
            $db->table('trials')->insert(['lead_id' => $leadId, 'branch_id' => $branch, 'trial_date' => $data['trial_date'],
                'trial_time' => $data['trial_time'], 'location' => $data['location'], 'coach_user_id' => $data['coach_user_id'],
                    'secondary_coach_user_id' => $data['secondary_coach_user_id'],
                    'status' => self::BOOKED, 'selection_outcome' => 'pending', 'remarks' => $data['remarks'], 'cancellation_reason' => null,
                        'rescheduled_from_id' => null, 'created_by' => $actor, 'created_at' => $now, 'updated_at' => $now]);
            $id = (int) $db->insertID();
            $this->history($db, $id, $leadId, $actor, 'booked', null, $data['trial_date'], null, $data['trial_time'], null, self::BOOKED, null, null);
            $this->setLeadStatus($db, $leadId, $branch, $actor, 'trial booked');
            $this->audit($db, $actor, 'trial_booked', 'trial', (string) $id, $branch, null, ['lead_id' => $leadId, 'trial_date' => $data['trial_date'],
                'trial_time' => $data['trial_time'], 'location' => $data['location'], 'coach_user_id' => $data['coach_user_id'],
                'secondary_coach_user_id' => $data['secondary_coach_user_id']]);
            $db->transComplete();
            $this->assertTransaction($db);
            return $id;
        } catch (Throwable $e) {
            $db->transRollback();
            $db->resetTransStatus();
            throw $e;
        }
    }
    public function reschedule(int $id, array $input): int
    {
        $db = Database::connect('default');
        $db->transStart();
        try {
            $old = $this->load($id, true);
            $actor = $this->authorize((int) $old['branch_id'], 'lead.update');
            if ($old['status'] !== self::BOOKED) {
                throw new RuntimeException('Trial cannot be rescheduled.');
            }
            $data = $this->validatedSchedule($input, (int) $old['branch_id']);
            $this->assertCoachInBranch($db, $data['coach_user_id'], (int) $old['branch_id']);
            $this->assertCoachInBranch($db, $data['secondary_coach_user_id'], (int) $old['branch_id'], 'Invalid secondary coach.');
            if ($this->futureBookingExists($db, (int) $old['lead_id'], $id)) {
                throw new RuntimeException('A future trial is already booked.');
            }
            $now = date('Y-m-d H:i:s');
            $db->table('trials')->where(['id' => $id, 'status' => self::BOOKED])->update(['status' => self::RESCHEDULED, 'updated_at' => $now]);
            $db->table('trials')->insert(['lead_id' => $old['lead_id'], 'branch_id' => $old['branch_id'], 'trial_date' => $data['trial_date'],
                'trial_time' => $data['trial_time'], 'location' => $data['location'],
                    'coach_user_id' => $data['coach_user_id'] ?? $old['coach_user_id'],
                        'secondary_coach_user_id' => $data['secondary_coach_user_id'], 'status' => self::BOOKED, 'selection_outcome' => 'pending',
                        'remarks' => $data['remarks'] ?? $old['remarks'], 'cancellation_reason' => null, 'rescheduled_from_id' => $id,
                            'created_by' => $actor, 'created_at' => $now, 'updated_at' => $now]);
            $newId = (int) $db->insertID();
            $this->history($db, $id, (int) $old['lead_id'], $actor, 'rescheduled', $old['trial_date'], $data['trial_date'], $old['trial_time'],
                $data['trial_time'], self::BOOKED, self::RESCHEDULED, null, null);
            $this->history($db, $newId, (int) $old['lead_id'], $actor, 'booked', null, $data['trial_date'], null, $data['trial_time'], null,
                self::BOOKED, null, null);
            $this->audit($db, $actor, 'trial_rescheduled', 'trial', (string) $newId, (int) $old['branch_id'], ['trial_id' => $id,
                'trial_date' => $old['trial_date'], 'trial_time' => $old['trial_time'], 'coach_user_id' => $old['coach_user_id'],
                'secondary_coach_user_id' => $old['secondary_coach_user_id']], ['trial_id' => $newId, 'trial_date' => $data['trial_date'],
                    'trial_time' => $data['trial_time'], 'coach_user_id' => $data['coach_user_id'],
                    'secondary_coach_user_id' => $data['secondary_coach_user_id']]);
            $db->transComplete();
            $this->assertTransaction($db);
            return $newId;
        } catch (Throwable $e) {
            $db->transRollback();
            $db->resetTransStatus();
            throw $e;
        }
    }

    public function updateSecondaryCoach(int $id, array $input): void
    {
        $db = Database::connect('default');
        $db->transStart();

        try {
            $trial = $this->load($id, true);
            $actor = $this->authorizeSecondaryCoachChange($trial);
            $expectedUpdatedAt = trim((string) ($input['expected_updated_at'] ?? ''));

            if ($expectedUpdatedAt === '' || $expectedUpdatedAt !== (string) $trial['updated_at']) {
                throw new RuntimeException('Update conflict.');
            }

            $correctionReason = trim((string) ($input['correction_reason'] ?? ''));
            if ($correctionReason === '') {
                throw new RuntimeException('Correction reason required.');
            }
            if (strlen($correctionReason) > 255) {
                throw new RuntimeException('Correction reason is too long.');
            }

            $secondary = $input['secondary_coach_user_id'] ?? null;
            $secondary = $secondary === null || $secondary === '' ? null : (int) $secondary;

            if ($secondary !== null && $secondary < 1) {
                throw new RuntimeException('Invalid secondary coach.');
            }

            if ($secondary !== null && $secondary === (int) ($trial['coach_user_id'] ?? 0)) {
                throw new RuntimeException('Primary and secondary coaches must be different.');
            }

            $this->assertCoachInBranch($db, $secondary, (int) $trial['branch_id'], 'Invalid secondary coach.');
            $now = date('Y-m-d H:i:s');
            $updated = $db->table('trials')
                ->where(['id' => $id, 'updated_at' => $expectedUpdatedAt])
                ->update(['secondary_coach_user_id' => $secondary, 'updated_at' => $now]);

            if (! $updated) {
                throw new RuntimeException('Update conflict.');
            }

            $this->audit(
                $db,
                $actor,
                'trial_secondary_coach_updated',
                'trial',
                (string) $id,
                (int) $trial['branch_id'],
                ['secondary_coach_user_id' => $trial['secondary_coach_user_id'] ?? null],
                ['secondary_coach_user_id' => $secondary],
                $correctionReason,
            );

            $db->transComplete();
            $this->assertTransaction($db);
        } catch (Throwable $exception) {
            $db->transRollback();
            $db->resetTransStatus();
            throw $exception;
        }
    }
    public function cancel(int $id, string $reason): void
    {
        $db = Database::connect('default');
        $db->transStart();
        try {
            $trial = $this->load($id, true);
            $actor = $this->authorize((int) $trial['branch_id'], 'lead.update');
            if ($trial['status'] !== self::BOOKED || trim($reason) === '' || strlen(trim($reason)) > 255) {
                throw new RuntimeException('Cancellation reason required.');
            }
            $now = date('Y-m-d H:i:s');
            $db->table('trials')->where(['id' => $id, 'status' => self::BOOKED])
                ->update(['status' => self::CANCELLED, 'cancellation_reason' => trim($reason), 'updated_at' => $now]);
            $this->history($db, $id, (int) $trial['lead_id'], $actor, 'cancelled', $trial['trial_date'], null, $trial['trial_time'], null,
                self::BOOKED, self::CANCELLED, null, trim($reason));
            $this->audit($db, $actor, 'trial_cancelled', 'trial', (string) $id, (int) $trial['branch_id'], ['status' => self::BOOKED],
                ['status' => self::CANCELLED], trim($reason));
            $db->transComplete();
            $this->assertTransaction($db);
        } catch (Throwable $e) {
            $db->transRollback();
            $db->resetTransStatus();
            throw $e;
        }
    }
    public function attendance(int $id, string $outcome): void
    {
        $outcome = strtolower(trim($outcome));
        if (!in_array($outcome, [self::ATTENDED, self::NO_SHOW], true)) {
            throw new RuntimeException('Invalid attendance.');
        }
        $db = Database::connect('default');
        $db->transStart();
        try {
            $trial = $this->load($id, true);
            $actor = $this->authorize((int) $trial['branch_id'], 'lead.update');
            if ($trial['status'] !== self::BOOKED) {
                throw new RuntimeException('Trial attendance is not available.');
            }
            $now = date('Y-m-d H:i:s');
            $db->table('trials')->where(['id' => $id, 'status' => self::BOOKED])->update(['status' => $outcome, 'updated_at' => $now]);
            $this->history($db, $id, (int) $trial['lead_id'], $actor, $outcome === self::ATTENDED ? 'attendance_recorded' : 'no_show_recorded',
                $trial['trial_date'], null, $trial['trial_time'], null, self::BOOKED, $outcome, null, null);
            if ($outcome === self::ATTENDED) {
                $this->setLeadStatus($db, (int) $trial['lead_id'], (int) $trial['branch_id'], $actor, 'trial attended');
            }
            $this->audit($db, $actor, $outcome === self::ATTENDED ? 'trial_attendance_recorded' : 'trial_no_show_recorded', 'trial', (string) $id,
                (int) $trial['branch_id'], ['status' => self::BOOKED], ['status' => $outcome]);
            $db->transComplete();
            $this->assertTransaction($db);
        } catch (Throwable $e) {
            $db->transRollback();
            $db->resetTransStatus();
            throw $e;
        }
    }
    public function selection(int $id, string $outcome): void
    {
        $outcome = strtolower(trim($outcome));
        if (!in_array($outcome, ['selected', 'not_selected'], true)) {
            throw new RuntimeException('Invalid selection outcome.');
        }
        $db = Database::connect('default');
        $db->transStart();
        try {
            $trial = $this->load($id, true);
            $actor = $this->authorize((int) $trial['branch_id'], 'lead.update');
            if ($trial['status'] !== self::ATTENDED || $trial['selection_outcome'] !== 'pending') {
                throw new RuntimeException('Only an attended trial can be selected.');
            }
            $db->table('trials')->where(['id' => $id, 'status' => self::ATTENDED])
                ->update(['selection_outcome' => $outcome, 'updated_at' => date('Y-m-d H:i:s')]);
            $this->history($db, $id, (int) $trial['lead_id'], $actor, 'selection_recorded', null, null, null, null, self::ATTENDED, self::ATTENDED,
                $outcome, null);
            $this->audit($db, $actor, 'trial_selection_recorded', 'trial', (string) $id, (int) $trial['branch_id'],
                ['selection_outcome' => $trial['selection_outcome']], ['selection_outcome' => $outcome]);
            if ($outcome === 'selected') {
                $this->setLeadStatus($db, (int) $trial['lead_id'], (int) $trial['branch_id'], $actor, 'selected');
            }
            $db->transComplete();
            $this->assertTransaction($db);
        } catch (Throwable $e) {
            $db->transRollback();
            $db->resetTransStatus();
            throw $e;
        }
    }
    public function admissionPending(int $id): void
    {
        $db = Database::connect('default');
        $db->transStart();
        try {
            $trial = $this->load($id, true);
            $actor = $this->authorize((int) $trial['branch_id'], 'lead.update');
            if ($trial['status'] !== self::ATTENDED || $trial['selection_outcome'] !== 'selected') {
                throw new RuntimeException('Only a selected trial can move to admission pending.');
            }
            service('admission')->createFromTrialPending((int) $trial['id'], (int) $trial['lead_id'], (int) $trial['branch_id'], $db);
            $this->setLeadStatus($db, (int) $trial['lead_id'], (int) $trial['branch_id'], $actor, 'admission pending');
            $this->audit($db, $actor, 'lead_transition_admission_pending', 'lead', (string) $trial['lead_id'], (int) $trial['branch_id'],
                ['status' => 'selected'], ['status' => 'admission pending']);
            $db->transComplete();
            $this->assertTransaction($db);
        } catch (Throwable $e) {
            $db->transRollback();
            $db->resetTransStatus();
            throw $e;
        }
    }

    public function updateRatings(int $id, array $input): void
    {
        $db = Database::connect('default');
        $db->transStart();

        try {
            $trial = $this->load($id, true);
            $actor = $this->authorizeCoachRatingUpdate($trial);

            $ratings = $this->validatedRatings($input);
            $expectedUpdatedAt = trim((string) ($input['expected_updated_at'] ?? ''));
            if ($expectedUpdatedAt !== '' && $expectedUpdatedAt !== (string) $trial['updated_at']) {
                throw new RuntimeException('Update conflict.');
            }

            $before = [];
            foreach (self::RATING_FIELDS as $field) {
                $before[$field] = $trial[$field] ?? null;
            }

            $now = date('Y-m-d H:i:s');
            if ($now <= (string) $trial['updated_at']) {
                $now = date('Y-m-d H:i:s', strtotime((string) $trial['updated_at']) + 1);
            }

            $query = $db->table('trials')->where('id', $id);
            if ($expectedUpdatedAt !== '') {
                $query->where('updated_at', $expectedUpdatedAt);
            }

            if ($query->update(array_merge($ratings, ['updated_at' => $now])) < 1) {
                throw new RuntimeException('Update conflict.');
            }
            $this->audit(
                $db,
                $actor,
                'trial_ratings_updated',
                'trial',
                (string) $id,
                (int) $trial['branch_id'],
                $before,
                $ratings,
                'Coach assessment rating update',
            );

            $db->transComplete();
            $this->assertTransaction($db);
        } catch (Throwable $exception) {
            $db->transRollback();
            $db->resetTransStatus();
            throw $exception;
        }
    }
    private function load(int $id, bool $lock): array
    {
        $row = Database::connect('default')
            ->query('SELECT t.*, l.full_name, l.email, l.phone, l.status AS lead_status, l.source AS lead_source, l.owner_user_id, b.name AS branch_name, owner.display_name AS owner_name, coach.display_name AS coach_name, secondary_coach.display_name AS secondary_coach_name, at.id AS admission_test_id FROM trials t INNER JOIN leads l ON l.id=t.lead_id INNER JOIN branches b ON b.id=t.branch_id LEFT JOIN users owner ON owner.id=l.owner_user_id LEFT JOIN users coach ON coach.id=t.coach_user_id LEFT JOIN users secondary_coach ON secondary_coach.id=t.secondary_coach_user_id LEFT JOIN admission_tests at ON at.trial_id=t.id WHERE t.id=? LIMIT 1' . ($lock ? ' FOR UPDATE' : ''), [
                $id,
            ])->getRowArray();
        if (!is_array($row)) {
            throw new RuntimeException('Not found.');
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

    private function authorizeTrialDetails(array $trial): void
    {
        $branch = (int) ($trial['branch_id'] ?? 0);
        $actor = session('user');
        $actorId = is_array($actor) ? (int) ($actor['id'] ?? 0) : 0;
        $canView = service('authorization')->can('lead.view', (string) $branch);
        $canRate = $this->canEditCoachRatings($trial, $actorId);

        if ($branch < 1 || (! $canView && ! $canRate)) {
            throw new RuntimeException('Forbidden');
        }

        if ($actorId < 1) {
            throw new RuntimeException('Authentication required');
        }
    }

    private function canEditCoachRatings(array $trial, ?int $actorId = null): bool
    {
        if ($actorId === null) {
            $actor = session('user');
            $actorId = is_array($actor) ? (int) ($actor['id'] ?? 0) : 0;
        }

        $hasRatingPermission = service('authorization')->can('performance.update', (string) ($trial['branch_id'] ?? 0))
            || service('authorization')->can('lead.update', (string) ($trial['branch_id'] ?? 0));

        $assigned = in_array($actorId, [
            (int) ($trial['coach_user_id'] ?? 0),
            (int) ($trial['secondary_coach_user_id'] ?? 0),
        ], true);
        $isAuthorizedTrialAdministrator = service('authorization')->can(
            'lead.update',
            (string) ($trial['branch_id'] ?? 0),
        );

        return $hasRatingPermission
            && $actorId > 0
            && ($assigned || $isAuthorizedTrialAdministrator);
    }

    private function authorizeCoachRatingUpdate(array $trial): int
    {
        $actor = session('user');
        $actorId = is_array($actor) ? (int) ($actor['id'] ?? 0) : 0;

        $assigned = $actorId > 0 && in_array($actorId, [
            (int) ($trial['coach_user_id'] ?? 0),
            (int) ($trial['secondary_coach_user_id'] ?? 0),
        ], true);
        $isAuthorizedTrialAdministrator = service('authorization')->can(
            'lead.update',
            (string) ($trial['branch_id'] ?? 0),
        );

        if ($actorId < 1 || (! $assigned && ! $isAuthorizedTrialAdministrator)) {
            throw new RuntimeException('Only the assigned Coach can update Trial ratings.');
        }

        if (! $this->canEditCoachRatings($trial, $actorId)) {
            throw new RuntimeException('Forbidden');
        }

        return $actorId;
    }

    private function canViewAllTrials(array $branches): bool
    {
        if (service('branchContext')->isBranchAdminContext() || service('branchContext')->isSuperAdmin()) {
            return true;
        }

        foreach ($branches as $branch) {
            if (! service('authorization')->can('performance.update', (string) $branch)
                && (service('authorization')->can('lead.view', (string) $branch)
                    || service('authorization')->can('lead.update', (string) $branch))) {
                return true;
            }
        }

        return false;
    }

    private function canReviewTrial(array $trial, int $actorId): bool
    {
        if ($actorId < 1 || ! service('authorization')->can('performance.update', (string) ($trial['branch_id'] ?? 0))) {
            return false;
        }

        return in_array($actorId, [
            (int) ($trial['coach_user_id'] ?? 0),
            (int) ($trial['secondary_coach_user_id'] ?? 0),
        ], true);
    }

    private function canAssignSecondaryCoach(array $trial, int $actorId): bool
    {
        if (service('authorization')->can('lead.update', (string) ($trial['branch_id'] ?? 0))) {
            return true;
        }

        return $actorId > 0
            && $actorId === (int) ($trial['coach_user_id'] ?? 0)
            && $this->canReviewTrial($trial, $actorId);
    }

    private function authorizeSecondaryCoachChange(array $trial): int
    {
        $actor = session('user');
        $actorId = is_array($actor) ? (int) ($actor['id'] ?? 0) : 0;
        $primaryId = (int) ($trial['coach_user_id'] ?? 0);
        $secondaryId = (int) ($trial['secondary_coach_user_id'] ?? 0);
        $isBranchAdmin = service('branchContext')->isBranchAdminContext();
        $isSuperAdmin = service('branchContext')->isSuperAdmin();

        if ($actorId === $secondaryId && $actorId !== $primaryId && ! $isBranchAdmin && ! $isSuperAdmin) {
            throw new RuntimeException('Only the Primary Coach can change the Secondary Coach.');
        }

        if (! $this->canAssignSecondaryCoach($trial, $actorId) && ! $isBranchAdmin && ! $isSuperAdmin) {
            throw new RuntimeException('Only the Primary Coach can change the Secondary Coach.');
        }

        if ($actorId < 1) {
            throw new RuntimeException('Authentication required');
        }

        return $actorId;
    }
    private function authorizedBranches(): array
    {
        return service('branchContext')->authorizedBranchIds(true);
    }
    private function validatedSchedule(array $input, ?int $branch = null): array
    {
        $date = trim((string) ($input['trial_date'] ?? ''));
        $time = trim((string) ($input['trial_time'] ?? ''));
        $location = trim((string) ($input['location'] ?? ''));
        if (preg_match('/^(\d{4}-\d{2}-\d{2}|\d{2}-\d{2}-\d{4})$/', $date, $matches) !== 1) {
            throw new RuntimeException('Invalid trial date.');
        }

        $date = $matches[1];
        $isIsoDate = $date[4] === '-';
        $dateFormat = $isIsoDate ? '!Y-m-d' : '!d-m-Y';
        $timezone = new \DateTimeZone((string) (config('App')->appTimezone ?: 'Asia/Kolkata'));
        $dateObject = DateTimeImmutable::createFromFormat($dateFormat, $date, $timezone);
        $normalizedDate = $dateObject?->format('Y-m-d');
        $expectedDate = $dateObject?->format($isIsoDate ? 'Y-m-d' : 'd-m-Y');
        $timeObject = DateTimeImmutable::createFromFormat('!H:i', $time, $timezone);
        if (!$dateObject || $normalizedDate === false || $expectedDate !== $date) {
            throw new RuntimeException('Invalid trial date.');
        }
        if (!$timeObject || $timeObject->format('H:i') !== $time) {
            throw new RuntimeException('Invalid trial time.');
        }
        if (strlen($location) > 255) {
            throw new RuntimeException('Invalid trial location.');
        }
        if ($location !== '' && $branch !== null) {
            $locationExists = Database::connect('default')->table('grounds')
                ->where(['branch_id' => $branch, 'is_active' => 1, 'name' => $location])
                ->countAllResults() === 1;
            if (!$locationExists) {
                throw new RuntimeException('Invalid trial location.');
            }
        }
        $coach = $input['coach_user_id'] ?? null;
        if ($coach !== null && $coach !== '') {
            $coach = (int) $coach;
        } else {
            $coach = null;
        }
        if ($coach !== null && $coach < 1) {
            throw new RuntimeException('Invalid coach.');
        }
        $secondaryCoach = $input['secondary_coach_user_id'] ?? null;
        if ($secondaryCoach !== null && $secondaryCoach !== '') {
            $secondaryCoach = (int) $secondaryCoach;
        } else {
            $secondaryCoach = null;
        }
        if ($secondaryCoach !== null && $secondaryCoach < 1) {
            throw new RuntimeException('Invalid secondary coach.');
        }
        if ($coach !== null && $secondaryCoach !== null && $coach === $secondaryCoach) {
            throw new RuntimeException('Primary and secondary coaches must be different.');
        }
        $remarks = trim((string) ($input['remarks'] ?? ''));
        if (strlen($remarks) > 65535) {
            throw new RuntimeException('Invalid trial remarks.');
        }
        return ['trial_date' => $normalizedDate, 'trial_time' => $time . ':00', 'location' => $location === '' ? null : $location, 'coach_user_id' => $coach,
            'secondary_coach_user_id' => $secondaryCoach, 'remarks' => $remarks === '' ? null : $remarks];
    }
    private function futureBookingExists(object $db, int $leadId, ?int $ignore = null): bool
    {
        $sql = 'SELECT id FROM trials WHERE lead_id = ? AND status = ? AND CONCAT(trial_date, " ", trial_time) > ?';
        $timezone = new \DateTimeZone((string) (config('App')->appTimezone ?: 'Asia/Kolkata'));
        $params = [$leadId, self::BOOKED, (new DateTimeImmutable('now', $timezone))->format('Y-m-d H:i:s')];
        if ($ignore !== null) {
            $sql .= ' AND id != ?';
            $params[] = $ignore;
        }
        return is_array($db->query($sql . ' LIMIT 1', $params)->getRowArray());
    }
    private function assertCoachInBranch(
        object $db,
        ?int $coach,
        int $branch,
        string $errorMessage = 'Invalid coach.',
    ): void
    {
        if ($coach === null) {
            return;
        }
        $validCoach = $db->table('user_branches ub')
            ->select('ub.user_id')
            ->join('users u', 'u.id = ub.user_id')
            ->join('user_roles ur', 'ur.user_id=ub.user_id')
            ->join('roles r', 'r.id=ur.role_id')
            ->where(['ub.user_id' => $coach, 'ub.branch_id' => $branch, 'u.is_enabled' => 1])
            ->whereIn('r.name', ['Coach', 'Fitness Coach'])
            ->countAllResults();

        if ($validCoach !== 1) {
            throw new RuntimeException($errorMessage);
        }
    }
    private function setLeadStatus(object $db, int $leadId, int $branch, int $actor, string $status): void
    {
        $configured = $db->table('lead_statuses')->where(['name' => $status, 'is_active' => 1])->get()->getRowArray();
        if (!is_array($configured)) {
            throw new RuntimeException('Required lead status configuration is missing or inactive.');
        }
        $lead = $db->table('leads')->where(['id' => $leadId, 'branch_id' => $branch])->get()->getRowArray();
        if (!is_array($lead)) {
            throw new RuntimeException('Not found.');
        }
        if ((string) $lead['status'] === $status) {
            return;
        }
        $now = date('Y-m-d H:i:s');
        $db->table('leads')->where(['id' => $leadId, 'branch_id' => $branch])->update(['status' => $status, 'updated_at' => $now]);
        $db->table('lead_status_history')->insert(['lead_id' => $leadId, 'actor_user_id' => $actor, 'previous_status' => $lead['status'],
            'new_status' => $status, 'reason' => null, 'created_at' => $now]);
        $this->audit($db, $actor, 'lead_status_changed', 'lead', (string) $leadId, $branch, ['status' => $lead['status']], ['status' => $status]);
    }

    /** @return array<string, int|null> */
    private function validatedRatings(array $input): array
    {
        $ratings = [];

        foreach (self::RATING_FIELDS as $field) {
            $value = $input[$field] ?? null;
            if ($value === null || $value === '') {
                $ratings[$field] = null;
                continue;
            }

            try {
                $ratings[$field] = $this->ratingService->normalize($value);
            } catch (\InvalidArgumentException $exception) {
                throw new RuntimeException('Invalid Trial rating.', 0, $exception);
            }
        }

        return $ratings;
    }
    private function history(object $db, int $trial, int $lead, int $actor, string $event, ?string $previousDate, ?string $newDate,
        ?string $previousTime, ?string $newTime, ?string $previousStatus, ?string $newStatus, ?string $outcome, ?string $reason): void
    {
        $db->table('trial_history')->insert(['trial_id' => $trial, 'lead_id' => $lead, 'actor_user_id' => $actor, 'event_type' => $event,
            'previous_date' => $previousDate, 'previous_time' => $previousTime, 'new_date' => $newDate, 'new_time' => $newTime,
                'previous_status' => $previousStatus, 'new_status' => $newStatus, 'selection_outcome' => $outcome, 'reason' => $reason,
                    'created_at' => date('Y-m-d H:i:s')]);
    }
    private function assertTransaction(object $db): void
    {
        if (!$db->transStatus()) {
            throw new RuntimeException('Unable to complete trial request.');
        }
    }
    private function audit(object $db, int $actor, string $action, string $type, string $id, ?int $branch, ?array $before = null, ?array $after = null,
        ?string $reason = null): void
    {
        service('auditPersistence')->record($actor, $action, $type, $id, $branch, $before, $after, $reason);
    }
}
