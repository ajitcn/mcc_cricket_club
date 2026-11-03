<?php

declare (strict_types=1);
namespace Tests\Database;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use RuntimeException;
final class TrialManagementIntegrationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    protected $DBGroup = 'tests';
    protected $migrate = false;
    private string $prefix;
    private int $user;
    private int $branch;
    private array $leads = [];
    private array $trials = [];
    private array $admissions = [];
    private array $branches = [];
    private array $users = [];
    private array $grounds = [];
    protected function setUp(): void
    {
        parent::setUp();
        $this->prefix = 'TEST_MCC_TRIAL_' . strtoupper(bin2hex(random_bytes(6)));
        $this->assertTargetDatabase();
        $this->branch = $this->branch('MAIN');
        $this->user = $this->user('ADMIN', $this->branch);
        $this->setSession([$this->branch]);
    }
    protected function tearDown(): void
    {
        try {
            $this->cleanup();
        } finally {
            session()->destroy();
            parent::tearDown();
        }
    }
    public function testBookingAttendanceSelectionAndAdmissionPendingAreAudited(): void
    {
        $lead = $this->lead($this->branch, 'FLOW');
        $trial = service('trial')->book($lead, $this->schedule('GROUND-A'));
        $this->trials[] = $trial;
        $row = $this->db->table('trials')->where('id', $trial)->get()->getRowArray();
        self::assertSame('booked', $row['status']);
        self::assertSame('pending', $row['selection_outcome']);
        service('trial')->attendance($trial, 'attended');
        service('trial')->selection($trial, 'selected');
        $this->db->table('leads')->where('id', $lead)->update(['status' => 'admission pending']);
        service('trial')->admissionPending($trial);
        $this->admissions[] = (int) $this->db->table('admissions')->where('lead_id', $lead)->get()->getRow()->id;
        $row = $this->db->table('trials')->where('id', $trial)->get()->getRowArray();
        $leadRow = $this->db->table('leads')->where('id', $lead)->get()->getRowArray();
        self::assertSame('attended', $row['status']);
        self::assertSame('selected', $row['selection_outcome']);
        self::assertSame('admission pending', $leadRow['status']);
        $pending = service('admission')->listForAuthorizedBranches(['status' => 'INTAKE']);
        self::assertCount(1, array_filter($pending, static fn(array $admission): bool => (int) $admission['lead_id'] === $lead));
        $pendingAdmission = array_values(array_filter($pending, static fn(array $admission): bool => (int) $admission['lead_id'] === $lead))[0];
        self::assertSame($trial, (int) $pendingAdmission['trial_id']);
        self::assertSame($this->branch, (int) $pendingAdmission['branch_id']);
        self::assertSame($this->prefix . '_FLOW', $pendingAdmission['lead_name']);
        self::assertSame($this->prefix . '_MAIN', $pendingAdmission['branch_name']);
        foreach (['trial_booked', 'trial_attendance_recorded', 'trial_selection_recorded'] as $action) {
            self::assertSame(1, $this->db->table('audit_logs')->where(['action' => $action, 'entity_id' => (string) $trial])->countAllResults());
        }
        self::assertSame(1, $this->db->table('audit_logs')->where(['action' => 'lead_transition_admission_pending',
    'entity_id' => (string) $lead])->countAllResults());
    }

    public function testAssignedCoachCanSaveTrialRatingsAndAdmissionReadsTheSnapshot(): void
    {
        $lead = $this->lead($this->branch, 'RATINGS');
        $trial = service('trial')->book($lead, $this->schedule('GROUND-RATINGS') + ['coach_user_id' => $this->user]);
        $this->trials[] = $trial;
        service('trial')->attendance($trial, 'attended');
        service('trial')->selection($trial, 'selected');
        $this->db->table('leads')->where('id', $lead)->update(['status' => 'admission pending']);

        session()->set('permissions', ['lead.view', 'trial.book', 'admission.create', 'performance.update']);
        \Config\Services::resetSingle('authorization');
        service('trial')->updateRatings($trial, [
            'ability_overall' => '10',
            'ability_batting' => '8',
            'ability_bowling' => '7',
            'ability_wicketkeeping' => '6',
            'ability_fitness' => '9',
        ]);

        $details = service('trial')->details($trial);
        self::assertSame(10, $details['coach_ratings']['ability_overall']);
        self::assertSame(8, $details['coach_ratings']['ability_batting']);
        self::assertSame(7, $details['coach_ratings']['ability_bowling']);
        self::assertSame(6, $details['coach_ratings']['ability_wicketkeeping']);
        self::assertSame(9, $details['coach_ratings']['ability_fitness']);

        $post = $this->withSession(session()->get())->post('crm/trials/' . $trial . '/ratings', [
            'ability_overall' => '10',
            'ability_batting' => '8',
            'ability_bowling' => '7',
            'ability_wicketkeeping' => '6',
            'ability_fitness' => '9',
            csrf_token() => csrf_hash(),
        ]);
        self::assertSame(302, $post->response()->getStatusCode(), $post->getBody());
        self::assertSame(
            site_url('crm/trials/' . $trial),
            $post->response()->getHeaderLine('Location'),
        );

        $firstPage = $this->withSession(session()->get())->get('crm/trials/' . $trial);
        self::assertSame(200, $firstPage->response()->getStatusCode(), $firstPage->getBody());
        self::assertMatchesRegularExpression(
            '/name="ability_overall"[^>]*>.*value="10\.0 - Excellent" selected/s',
            $firstPage->getBody(),
        );

        service('trial')->updateRatings($trial, [
            'ability_overall' => '3',
            'ability_batting' => '2',
            'ability_bowling' => '1',
            'ability_wicketkeeping' => '0',
            'ability_fitness' => '4',
        ]);
        $secondPage = $this->withSession(session()->get())->get('crm/trials/' . $trial);
        self::assertMatchesRegularExpression(
            '/name="ability_overall"[^>]*>.*value="3\.0 - Regular" selected/s',
            $secondPage->getBody(),
        );

        service('trial')->updateRatings($trial, [
            'ability_overall' => '',
            'ability_batting' => '',
            'ability_bowling' => '',
            'ability_wicketkeeping' => '',
            'ability_fitness' => '',
        ]);
        $blankPage = $this->withSession(session()->get())->get('crm/trials/' . $trial);
        self::assertMatchesRegularExpression(
            '/name="ability_overall"[^>]*>\s*<option value="" selected/s',
            $blankPage->getBody(),
        );

        $admission = service('admission')->createFromLead($lead, [
            'trial_id' => (string) $trial,
            'ability_overall' => '10',
            'ability_batting' => '8',
            'ability_bowling' => '7',
            'ability_wicketkeeping' => '6',
            'ability_fitness' => '9',
        ]);
        $row = $this->db->table('admissions')->where('id', $admission)->get()->getRowArray();
        self::assertSame('10', (string) $row['ability_overall']);
        self::assertSame('8', (string) $row['ability_batting']);
        self::assertSame('7', (string) $row['ability_bowling']);
        self::assertSame('6', (string) $row['ability_wicketkeeping']);
        self::assertSame('9', (string) $row['ability_fitness']);

        $this->admissions[] = $admission;
        $this->expectException(RuntimeException::class);
        service('trial')->updateRatings($trial, ['ability_overall' => '11']);
    }

    public function testAuthorizedTrialAdministratorCanUpdateRatingsAndTheVersionAdvances(): void
    {
        $lead = $this->lead($this->branch, 'ADMIN_RATINGS');
        $trial = service('trial')->book($lead, $this->schedule('GROUND-ADMIN-RATINGS'));
        $this->trials[] = $trial;
        $before = $this->db->table('trials')->where('id', $trial)->get()->getRowArray();

        session()->set('permissions', ['lead.view', 'lead.update', 'performance.update']);
        \Config\Services::resetSingle('authorization');
        service('trial')->updateRatings($trial, [
            'ability_overall' => '6',
            'ability_batting' => '',
            'expected_updated_at' => $before['updated_at'],
        ]);

        $after = $this->db->table('trials')->where('id', $trial)->get()->getRowArray();
        self::assertSame('6', (string) $after['ability_overall']);
        self::assertNull($after['ability_batting']);
        self::assertNotSame($before['updated_at'], $after['updated_at']);

        try {
            service('trial')->updateRatings($trial, [
                'ability_overall' => '7',
                'expected_updated_at' => $before['updated_at'],
            ]);
            self::fail('A stale rating update must be rejected.');
        } catch (RuntimeException $exception) {
            self::assertSame('Update conflict.', $exception->getMessage());
        }

        self::assertSame(1, $this->db->table('audit_logs')->where([
            'action' => 'trial_ratings_updated',
            'entity_id' => (string) $trial,
        ])->countAllResults());
    }

    public function testAssignedCoachCanEditRatingsAtEveryExistingTrialStage(): void
    {
        session()->set('permissions', ['lead.view', 'trial.book', 'performance.update']);
        \Config\Services::resetSingle('authorization');

        foreach (['booked', 'attended', 'no_show', 'cancelled', 'rescheduled'] as $status) {
            $lead = $this->lead($this->branch, 'STATUS_' . strtoupper($status));
            $trial = service('trial')->book($lead, $this->schedule('GROUND-STATUS-' . strtoupper($status)) + [
                'coach_user_id' => $this->user,
            ]);
            $this->trials[] = $trial;
            $this->db->table('trials')->where('id', $trial)->update(['status' => $status]);

            $page = $this->withSession(session()->get())->get('crm/trials/' . $trial);
            self::assertSame(200, $page->response()->getStatusCode(), $page->getBody());
            preg_match_all(
                '/<select[^>]+name="ability_(?:overall|batting|bowling|wicketkeeping|fitness)"[^>]*>/s',
                $page->getBody(),
                $ratingControls,
            );
            self::assertCount(5, $ratingControls[0]);
            self::assertStringNotContainsString('name="ability_overall" disabled', $page->getBody());
        }
    }

    public function testAssignedPrimaryAndSecondaryCoachesCanListAndOpenTrialsWithoutLeadView(): void
    {
        $secondary = $this->user('LIST_SECONDARY', $this->branch);
        $lead = $this->lead($this->branch, 'COACH_LIST_ACCESS');
        $trial = service('trial')->book($lead, $this->schedule('GROUND-COACH-LIST') + [
            'coach_user_id' => $this->user,
            'secondary_coach_user_id' => $secondary,
        ]);
        $this->trials[] = $trial;

        foreach ([$this->user, $secondary] as $coach) {
            session()->set([
                'user' => ['id' => $coach],
                'permissions' => ['performance.update'],
                'authorizedBranches' => [(string) $this->branch],
            ]);
            \Config\Services::resetSingle('authorization');

            $list = $this->withSession(session()->get())->get('crm/trials');
            self::assertSame(200, $list->response()->getStatusCode(), $list->getBody());
            self::assertStringContainsString('COACH_LIST_ACCESS', $list->getBody());

            $detail = $this->withSession(session()->get())->get('crm/trials/' . $trial);
            self::assertSame(200, $detail->response()->getStatusCode(), $detail->getBody());
            self::assertStringContainsString('Trial information', $detail->getBody());
        }
    }

    public function testOnlyPrimaryCoachCanChangeSecondaryAssignment(): void
    {
        $secondary = $this->user('ASSIGNMENT_SECONDARY', $this->branch);
        $replacement = $this->user('ASSIGNMENT_REPLACEMENT', $this->branch);
        $lead = $this->lead($this->branch, 'PRIMARY_ASSIGNMENT_RULE');
        $trial = service('trial')->book($lead, $this->schedule('GROUND-PRIMARY-ASSIGNMENT') + [
            'coach_user_id' => $this->user,
            'secondary_coach_user_id' => $secondary,
        ]);
        $this->trials[] = $trial;
        $row = $this->db->table('trials')->where('id', $trial)->get()->getRowArray();

        session()->set([
            'user' => ['id' => $secondary],
            'permissions' => ['performance.update'],
            'authorizedBranches' => [(string) $this->branch],
        ]);
        \Config\Services::resetSingle('authorization');
        try {
            service('trial')->updateSecondaryCoach($trial, [
                'secondary_coach_user_id' => $replacement,
                'expected_updated_at' => $row['updated_at'],
                'correction_reason' => 'Secondary assignment correction.',
            ]);
            self::fail('A Secondary Coach must not change the Secondary Coach assignment.');
        } catch (RuntimeException $exception) {
            self::assertSame('Only the Primary Coach can change the Secondary Coach.', $exception->getMessage());
        }
        self::assertSame($secondary, (int) $this->db->table('trials')->where('id', $trial)->get()->getRow('secondary_coach_user_id'));

        session()->set([
            'user' => ['id' => $this->user],
            'permissions' => ['performance.update'],
            'authorizedBranches' => [(string) $this->branch],
        ]);
        \Config\Services::resetSingle('authorization');
        service('trial')->updateSecondaryCoach($trial, [
            'secondary_coach_user_id' => $replacement,
            'expected_updated_at' => $row['updated_at'],
            'correction_reason' => 'Primary assignment correction.',
        ]);
        self::assertSame($replacement, (int) $this->db->table('trials')->where('id', $trial)->get()->getRow('secondary_coach_user_id'));
    }

    public function testUnauthorizedCoachGetsReadOnlyNotRatedDisplay(): void
    {
        $lead = $this->lead($this->branch, 'READ_ONLY_RATINGS');
        $trial = service('trial')->book($lead, $this->schedule('GROUND-READ-ONLY') + [
            'coach_user_id' => $this->user,
        ]);
        $this->trials[] = $trial;
        $otherCoach = $this->user('READ_ONLY_OTHER', $this->branch);
        session()->set([
            'user' => ['id' => $otherCoach],
            'permissions' => ['lead.view', 'performance.update'],
            'authorizedBranches' => [(string) $this->branch],
        ]);
        \Config\Services::resetSingle('authorization');

        $page = $this->withSession(session()->get())->get('crm/trials/' . $trial);
        self::assertSame(200, $page->response()->getStatusCode());
        self::assertStringNotContainsString('name="ability_overall"', $page->getBody());
        self::assertStringContainsString('Not rated', $page->getBody());
    }

    public function testTrialSupportsPrimaryAndSecondaryCoachesAndPreservesThePrimaryAssignment(): void
    {
        $secondary = $this->user('SECONDARY', $this->branch);
        $lead = $this->lead($this->branch, 'SECONDARY_ASSIGNMENT');
        $trial = service('trial')->book($lead, $this->schedule('GROUND-SECONDARY') + [
            'coach_user_id' => $this->user,
            'secondary_coach_user_id' => $secondary,
        ]);
        $this->trials[] = $trial;

        $row = $this->db->table('trials')->where('id', $trial)->get()->getRowArray();
        self::assertSame($this->user, (int) $row['coach_user_id']);
        self::assertSame($secondary, (int) $row['secondary_coach_user_id']);

        $details = service('trial')->details($trial);
        self::assertSame($this->prefix . '_ADMIN', $details['coach_name']);
        self::assertSame($this->prefix . '_SECONDARY', $details['secondary_coach_name']);
    }

    public function testSecondaryCoachCanBeAddedLaterWithoutChangingThePrimaryCoach(): void
    {
        $secondary = $this->user('LATER_SECONDARY', $this->branch);
        $lead = $this->lead($this->branch, 'LATER_SECONDARY_ASSIGNMENT');
        $trial = service('trial')->book($lead, $this->schedule('GROUND-LATER-SECONDARY') + [
            'coach_user_id' => $this->user,
        ]);
        $this->trials[] = $trial;
        $before = $this->db->table('trials')->where('id', $trial)->get()->getRowArray();

        service('trial')->updateSecondaryCoach($trial, [
            'secondary_coach_user_id' => $secondary,
            'expected_updated_at' => $before['updated_at'],
            'correction_reason' => 'Added secondary evaluator.',
        ]);

        $after = $this->db->table('trials')->where('id', $trial)->get()->getRowArray();
        self::assertSame($this->user, (int) $after['coach_user_id']);
        self::assertSame($secondary, (int) $after['secondary_coach_user_id']);
        self::assertSame(1, $this->db->table('audit_logs')->where([
            'action' => 'trial_secondary_coach_updated',
            'entity_id' => (string) $trial,
        ])->countAllResults());
    }

    public function testSameCrossBranchAndInactiveSecondaryCoachesAreRejected(): void
    {
        $lead = $this->lead($this->branch, 'INVALID_SECONDARY_ASSIGNMENTS');
        $sameCoach = $this->user('SAME_SECONDARY', $this->branch);
        $otherBranch = $this->branch('SECONDARY_OTHER_BRANCH');
        $crossBranchCoach = $this->user('CROSS_BRANCH_SECONDARY', $otherBranch);
        $inactiveCoach = $this->user('INACTIVE_SECONDARY', $this->branch);
        $this->db->table('users')->where('id', $inactiveCoach)->update(['is_enabled' => 0]);

        try {
            service('trial')->book($lead, $this->schedule('GROUND-INVALID-SECONDARY') + [
                'coach_user_id' => $sameCoach,
                'secondary_coach_user_id' => $sameCoach,
            ]);
            self::fail('The same coach must not be assigned twice.');
        } catch (RuntimeException $exception) {
            self::assertSame('Primary and secondary coaches must be different.', $exception->getMessage());
        }

        try {
            service('trial')->book($lead, $this->schedule('GROUND-CROSS-SECONDARY') + [
                'coach_user_id' => $this->user,
                'secondary_coach_user_id' => $crossBranchCoach,
            ]);
            self::fail('A cross-branch secondary coach must be rejected.');
        } catch (RuntimeException $exception) {
            self::assertSame('Invalid secondary coach.', $exception->getMessage());
        }

        try {
            service('trial')->book($lead, $this->schedule('GROUND-INACTIVE-SECONDARY') + [
                'coach_user_id' => $this->user,
                'secondary_coach_user_id' => $inactiveCoach,
            ]);
            self::fail('An inactive secondary coach must be rejected.');
        } catch (RuntimeException $exception) {
            self::assertSame('Invalid secondary coach.', $exception->getMessage());
        }
    }

    public function testSecondaryCoachCanUpdateTrialRatingsWithExistingAuthorization(): void
    {
        $secondary = $this->user('RATING_SECONDARY', $this->branch);
        $lead = $this->lead($this->branch, 'SECONDARY_RATINGS');
        $trial = service('trial')->book($lead, $this->schedule('GROUND-SECONDARY-RATINGS') + [
            'coach_user_id' => $this->user,
            'secondary_coach_user_id' => $secondary,
        ]);
        $this->trials[] = $trial;

        session()->set([
            'user' => ['id' => $secondary],
            'permissions' => ['performance.update'],
            'authorizedBranches' => [(string) $this->branch],
        ]);
        \Config\Services::resetSingle('authorization');
        service('trial')->updateRatings($trial, ['ability_overall' => '9']);

        self::assertSame('9', (string) $this->db->table('trials')->where('id', $trial)->get()->getRow('ability_overall'));
    }

    public function testSecondaryCoachUpdateRequiresCsrfAndOptimisticConcurrency(): void
    {
        $secondary = $this->user('CSRF_SECONDARY', $this->branch);
        $lead = $this->lead($this->branch, 'SECONDARY_CSRF');
        $trial = service('trial')->book($lead, $this->schedule('GROUND-SECONDARY-CSRF'));
        $this->trials[] = $trial;
        $row = $this->db->table('trials')->where('id', $trial)->get()->getRowArray();

        $this->expectException(\CodeIgniter\Security\Exceptions\SecurityException::class);
        $this->withSession(session()->get())->post('crm/trials/' . $trial . '/coaches', [
            'secondary_coach_user_id' => $secondary,
            'expected_updated_at' => $row['updated_at'],
        ]);
    }

    public function testAssignedCoachCanSavePartialRatingsBeforeAttendance(): void
    {
        $lead = $this->lead($this->branch, 'PARTIAL_RATINGS');
        $trial = service('trial')->book($lead, $this->schedule('GROUND-PARTIAL') + ['coach_user_id' => $this->user]);
        $this->trials[] = $trial;

        session()->set('permissions', ['lead.view', 'trial.book', 'performance.update']);
        \Config\Services::resetSingle('authorization');
        service('trial')->updateRatings($trial, ['ability_overall' => '4']);

        $row = $this->db->table('trials')->where('id', $trial)->get()->getRowArray();
        self::assertSame('4', (string) $row['ability_overall']);
        self::assertNull($row['ability_batting']);
        self::assertNull($row['ability_bowling']);
        self::assertNull($row['ability_wicketkeeping']);
        self::assertNull($row['ability_fitness']);

        $audit = $this->db->table('audit_logs')
            ->where(['action' => 'trial_ratings_updated', 'entity_id' => (string) $trial])
            ->get()
            ->getRowArray();
        self::assertSame('Coach assessment rating update', $audit['reason']);
        self::assertStringContainsString('ability_overall', (string) $audit['before_summary']);
        self::assertStringContainsString('ability_overall', (string) $audit['after_summary']);

        session()->set('permissions', ['performance.update']);
        \Config\Services::resetSingle('authorization');
        $coachPage = $this->withSession(session()->get())->get('crm/trials/' . $trial);
        self::assertSame(200, $coachPage->response()->getStatusCode(), $coachPage->getBody());
        self::assertStringContainsString('name="ability_overall"', $coachPage->getBody());
        self::assertStringContainsString('name="ability_batting"', $coachPage->getBody());
        self::assertStringContainsString('name="ability_bowling"', $coachPage->getBody());
        self::assertStringContainsString('name="ability_wicketkeeping"', $coachPage->getBody());
        self::assertStringContainsString('name="ability_fitness"', $coachPage->getBody());
        self::assertGreaterThanOrEqual(5, substr_count($coachPage->getBody(), 'Not rated'));
        self::assertStringContainsString('Save Ratings', $coachPage->getBody());
    }

    public function testUnassignedCoachCannotUpdateTrialRatings(): void
    {
        $lead = $this->lead($this->branch, 'UNASSIGNED_RATINGS');
        $trial = service('trial')->book($lead, $this->schedule('GROUND-UNASSIGNED') + ['coach_user_id' => $this->user]);
        $this->trials[] = $trial;
        $otherUser = $this->user('OTHER_COACH', $this->branch);

        session()->set([
            'user' => ['id' => $otherUser],
            'permissions' => ['performance.update'],
        ]);
        \Config\Services::resetSingle('authorization');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Only the assigned Coach can update Trial ratings.');
        service('trial')->updateRatings($trial, ['ability_overall' => '5']);
    }

    public function testRatingPostRequiresCsrfToken(): void
    {
        $lead = $this->lead($this->branch, 'CSRF_RATINGS');
        $trial = service('trial')->book($lead, $this->schedule('GROUND-CSRF-RATINGS') + ['coach_user_id' => $this->user]);
        $this->trials[] = $trial;

        $this->expectException(\CodeIgniter\Security\Exceptions\SecurityException::class);
        $this->withSession(session()->get())->post('crm/trials/' . $trial . '/ratings', [
            'ability_overall' => '5',
        ]);
    }
    public function testOnlyOneFutureBookingIsAllowed(): void
    {
        $lead = $this->lead($this->branch, 'DUPLICATE');
        $trial = service('trial')->book($lead, $this->schedule('GROUND-A'));
        $this->trials[] = $trial;
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('A future trial is already booked.');
        service('trial')->book($lead, $this->schedule('GROUND-B'));
    }

    public function testAdmissionRatingEndpointIsBranchAuthorized(): void
    {
        $lead = $this->lead($this->branch, 'AJAX_RATINGS');
        $trial = service('trial')->book($lead, $this->schedule('GROUND-AJAX'));
        $this->trials[] = $trial;

        $response = $this->withSession(session()->get())->get(
            'crm/trials/' . $trial . '/ratings-for-admission',
        );
        self::assertSame(200, $response->response()->getStatusCode());
        $payload = json_decode((string) $response->response()->getBody(), true);
        self::assertIsArray($payload, (string) $response->response()->getBody());
        self::assertSame($lead, (int) ($payload['ratings']['lead_id'] ?? 0));

        session()->set('permissions', ['lead.view']);
        \Config\Services::resetSingle('authorization');
        $forbidden = $this->withSession(session()->get())->get(
            'crm/trials/' . $trial . '/ratings-for-admission',
        );
        self::assertSame(403, $forbidden->response()->getStatusCode());
    }

    public function testPendingAdmissionLeadRemainsAvailableWithItsSelectedTrial(): void
    {
        $lead = $this->lead($this->branch, 'PENDING_ADMISSION_SEARCH');
        $trial = service('trial')->book($lead, $this->schedule('GROUND-PENDING-SEARCH'));
        $this->trials[] = $trial;

        service('trial')->attendance($trial, 'attended');
        service('trial')->selection($trial, 'selected');
        session()->set('permissions', ['lead.view', 'lead.update', 'trial.book', 'admission.create']);
        \Config\Services::resetSingle('authorization');
        service('trial')->admissionPending($trial);
        $this->admissions[] = (int) $this->db->table('admissions')->where('lead_id', $lead)->get()->getRow()->id;

        $eligible = service('admission')->eligibleLeads();
        self::assertNotEmpty(array_filter($eligible, static fn (array $row): bool => (int) $row['id'] === $lead));
        self::assertSame('admission pending', $this->db->table('leads')->where('id', $lead)->get()->getRow('status'));
        self::assertSame($lead, (int) $this->db->table('trials')->where('id', $trial)->get()->getRow('lead_id'));
    }

    public function testTrialListDateFilterUsesTrialDateAndRejectsInvalidDates(): void
    {
        $lead = $this->lead($this->branch, 'DATE_FILTER');
        $date = date('Y-m-d', strtotime('+2 days'));
        $this->ground('GROUND-DATE-FILTER', $this->branch);
        $trial = service('trial')->book($lead, [
            'trial_date' => $date,
            'trial_time' => '10:00',
            'location' => 'GROUND-DATE-FILTER',
        ]);
        $this->trials[] = $trial;

        $result = service('trial')->paginated([
            'trial_date' => date('d-m-Y', strtotime($date)),
        ]);

        self::assertSame(1, $result['total']);
        self::assertSame($trial, (int) $result['trials'][0]['id']);
        self::assertSame(0, service('trial')->paginated(['trial_date' => '31-02-2026'])['total']);
    }
    public function testRescheduleAndCancellationPreservePriorTrialEvidence(): void
    {
        $lead = $this->lead($this->branch, 'RESCHEDULE');
        $coach = $this->user('COACH', $this->branch);
        $first = service('trial')->book($lead, $this->schedule('GROUND-A') + ['coach_user_id' => $coach]);
        $this->trials[] = $first;
        $newDate = date('Y-m-d', strtotime('+3 days'));
        $this->ground('GROUND-B', $this->branch);
        $new = service('trial')->reschedule($first, ['trial_date' => $newDate, 'trial_time' => '11:00',
    'location' => 'GROUND-B', 'coach_user_id' => $coach, 'remarks' => 'Updated time']);
        $this->trials[] = $new;
        $updated = $this->db->table('trials')->where('id', $new)->get()->getRowArray();
        service('trial')->cancel($new, 'TEST_MCC schedule change');
        self::assertSame(2, $this->db->table('trials')->where('lead_id', $lead)->countAllResults());
        self::assertSame('rescheduled', $this->db->table('trials')->where('id', $first)->get()->getRowArray()['status']);
        self::assertSame('cancelled', $this->db->table('trials')->where('id', $new)->get()->getRowArray()['status']);
        self::assertSame($newDate, $updated['trial_date']);
        self::assertSame('11:00:00', $updated['trial_time']);
        self::assertSame('GROUND-B', $updated['location']);
        self::assertSame($coach, (int) $updated['coach_user_id']);
        self::assertSame(1, $this->db->table('trial_history')->where(['trial_id' => $first, 'event_type' => 'rescheduled'])->countAllResults());
    }
    public function testNoShowAndCrossBranchAccessAreHandledSafely(): void
    {
        $lead = $this->lead($this->branch, 'NOSHOW');
        $trial = service('trial')->book($lead, $this->schedule('GROUND-A'));
        $this->trials[] = $trial;
        service('trial')->attendance($trial, 'no_show');
        self::assertSame('no_show', $this->db->table('trials')->where('id', $trial)->get()->getRowArray()['status']);
        self::assertSame(1, $this->db->table('audit_logs')->where(['action' => 'trial_no_show_recorded', 'entity_id' => (string) $trial])->countAllResults());
        $otherBranch = $this->branch('OTHER');
        $otherLead = $this->lead($otherBranch, 'CROSS');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Forbidden');
        service('trial')->book($otherLead, $this->schedule('GROUND-C'));
    }
    public function testSelectionRequiresAttendance(): void
    {
        $lead = $this->lead($this->branch, 'GUARDS');
        $trial = service('trial')->book($lead, $this->schedule('GROUND-A'));
        $this->trials[] = $trial;
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Only an attended trial can be selected.');
        service('trial')->selection($trial, 'selected');
    }

    public function testAdmissionPendingDoesNotExposeTheSameTransitionAgain(): void
    {
        $lead = $this->lead($this->branch, 'ADMISSION_PENDING');
        $trial = service('trial')->book($lead, $this->schedule('GROUND-ADMISSION'));
        $this->trials[] = $trial;

        service('trial')->attendance($trial, 'attended');
        service('trial')->selection($trial, 'selected');
        service('trial')->admissionPending($trial);
        $this->admissions[] = (int) $this->db->table('admissions')->where('lead_id', $lead)->get()->getRow()->id;

        $details = service('trial')->details($trial);

        self::assertSame('admission pending', $details['lead_status']);
        self::assertFalse($details['actions']['admission_pending']);
        self::assertFalse($details['actions']['attendance']);
        self::assertFalse($details['actions']['selection']);
    }
    public function testBrowserTrialBookingRequiresCsrf(): void
    {
        $lead = $this->lead($this->branch, 'CSRF');
        $this->expectException(\CodeIgniter\Security\Exceptions\SecurityException::class);
        $this->withSession(session()->get())->post('crm/trials/from-lead/' . $lead, $this->schedule('GROUND-A'));
    }
    public function testBrowserTrialDetailsRendersLinkedLeadHistoryAndHidesActionsWithoutUpdatePermission(): void
    {
        $lead = $this->lead($this->branch, 'DETAILS');
        $trial = service('trial')->book($lead, $this->schedule('GROUND-DETAILS'));
        $this->trials[] = $trial;
        $page = $this->withSession(session()->get())->get('crm/trials/' . $trial);
        self::assertSame(200, $page->response()->getStatusCode());
        self::assertStringContainsString('Trial information', $page->getBody());
        self::assertStringContainsString('Lead information', $page->getBody());
        self::assertStringContainsString('Trial booked', $page->getBody());
        self::assertStringContainsString('Trial ratings', $page->getBody());
        self::assertSame(0, substr_count($page->getBody(), 'name="ability_overall" disabled'));
        self::assertStringContainsString('Not rated', $page->getBody());
        self::assertStringContainsString('GROUND-DETAILS', $page->getBody());
        self::assertStringContainsString($this->prefix . '_ADMIN', $page->getBody());
        session()->set('permissions', ['lead.view']);
        \Config\Services::resetSingle('authorization');
        $readOnlyPage = $this->withSession(session()->get())->get('crm/trials/' . $trial);
        self::assertSame(200, $readOnlyPage->response()->getStatusCode());
        self::assertStringNotContainsString('Record attendance', $readOnlyPage->getBody());
        self::assertStringNotContainsString('Cancel trial', $readOnlyPage->getBody());
    }
    public function testBrowserTrialDetailsUsesSafeNotFoundResponse(): void
    {
        $page = $this->withSession(session()->get())->get('crm/trials/999999999');
        self::assertSame(404, $page->response()->getStatusCode());
        self::assertStringContainsString('requested record was not found', strtolower($page->getBody()));
        self::assertStringNotContainsString('999999999', $page->getBody());
    }

    public function testTrialListEditActionUsesExistingWorkflowPermission(): void
    {
        $lead = $this->lead($this->branch, 'LIST_EDIT');
        $trial = service('trial')->book($lead, $this->schedule('GROUND-LIST-EDIT'));
        $this->trials[] = $trial;

        $page = $this->withSession(session()->get())->get('crm/trials');
        self::assertSame(200, $page->response()->getStatusCode());
        self::assertStringContainsString('Edit trial', $page->getBody());
        self::assertStringContainsString('#trial-actions-heading', $page->getBody());

        session()->set('permissions', ['lead.view']);
        \Config\Services::resetSingle('authorization');
        $readOnlyPage = $this->withSession(session()->get())->get('crm/trials');
        self::assertSame(200, $readOnlyPage->response()->getStatusCode());
        self::assertStringNotContainsString('Edit trial', $readOnlyPage->getBody());
    }
    private function schedule(string $location): array
    {
        if ($location !== '') {
            $this->ground($location, $this->branch);
        }

        return ['trial_date' => date('Y-m-d', strtotime('+2 days')), 'trial_time' => '10:00', 'location' => $location, 'remarks' => null];
    }

    private function ground(string $name, int $branch): int
    {
        $existing = $this->db->table('grounds')->where(['branch_id' => $branch, 'name' => $name])->get()->getRowArray();
        if (is_array($existing)) {
            return (int) $existing['id'];
        }

        $now = date('Y-m-d H:i:s');
        $this->db->table('grounds')->insert([
            'branch_id' => $branch,
            'name' => $name,
            'ground_type' => 'outdoor_ground',
            'description' => null,
            'is_active' => 1,
            'created_by' => $this->user,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $id = (int) $this->db->insertID();
        $this->grounds[] = $id;

        return $id;
    }
    private function branch(string $suffix): int
    {
        $this->assertTargetDatabase();
        $now = date('Y-m-d H:i:s');
        $this->db->table('branches')->insert(['name' => $this->prefix . '_' . $suffix, 'code' => $this->prefix . '_' . $suffix, 'is_active' => 1,
    'created_at' => $now, 'updated_at' => $now]);
        $id = (int) $this->db->insertID();
        $this->branches[] = $id;
        return $id;
    }
    private function user(string $suffix, int $branch): int
    {
        $this->assertTargetDatabase();
        $now = date('Y-m-d H:i:s');
        $this->db->table('users')->insert(['email' => strtolower($this->prefix . '_' . $suffix) . '@example.invalid',
    'password_hash' => password_hash('TEST_MCC_trial_password_2026!', PASSWORD_DEFAULT), 'display_name' => $this->prefix . '_' . $suffix,
    'is_enabled' => 1, 'failed_login_count' => 0, 'session_version' => 1, 'locked_until' => null, 'last_login_at' => null, 'created_at' => $now,
    'updated_at' => $now]);
        $id = (int) $this->db->insertID();
        $this->users[] = $id;
        $this->assertTargetDatabase();
        $this->db->table('user_branches')->insert(['user_id' => $id, 'branch_id' => $branch, 'created_at' => $now]);
        $coachRole = $this->db->table('roles')->where('name', 'Coach')->get()->getRowArray();
        if (is_array($coachRole)) {
            $this->db->table('user_roles')->ignore(true)->insert([
                'user_id' => $id,
                'role_id' => (int) $coachRole['id'],
                'created_at' => $now,
            ]);
        }
        return $id;
    }
    private function lead(int $branch, string $suffix): int
    {
        $this->assertTargetDatabase();
        $now = date('Y-m-d H:i:s');
        $this->db->table('leads')->insert(['branch_id' => $branch, 'owner_user_id' => $this->user, 'source' => 'lead',
    'full_name' => $this->prefix . '_' . $suffix, 'email' => strtolower($this->prefix . '_' . $suffix) . '@example.invalid', 'phone' => null,
    'date_of_birth' => null, 'status' => 'lead', 'lost_reason' => null, 'closed_at' => null, 'created_at' => $now, 'updated_at' => $now]);
        $id = (int) $this->db->insertID();
        $this->leads[] = $id;
        return $id;
    }
    private function setSession(array $branches): void
    {
        session()->set(['isLoggedIn' => true, 'user' => ['id' => $this->user, 'email' => strtolower($this->prefix . '_ADMIN') . '@example.invalid',
    'display_name' => $this->prefix . '_ADMIN'], 'session_version' => 1, 'roles' => [], 'permissions' => ['lead.view', 'lead.update', 'trial.book', 'admission.create'],
    'authorizedBranches' => array_map('strval', $branches), 'accountEnabled' => true]);
        \Config\Services::resetSingle('authorization');
    }
    private function cleanup(): void
    {
        $this->assertTargetDatabase();
        $admissionIds = array_values(array_unique(array_map('intval', $this->admissions)));
        if ($admissionIds !== []) {
            $this->assertTargetDatabase();
            $this->db->table('admission_status_history')->whereIn('admission_id', $admissionIds)->delete();
            $this->assertTargetDatabase();
            $this->db->table('admissions')->whereIn('id', $admissionIds)->delete();
        }
        $trialIds = array_values(array_unique(array_map('intval', $this->trials)));
        $leadIds = array_values(array_unique(array_map('intval', $this->leads)));
        $userIds = array_values(array_unique(array_map('intval', $this->users)));
        $branchIds = array_values(array_unique(array_map('intval', $this->branches)));
        if ($trialIds !== []) {
            $this->assertTargetDatabase();
            $this->db->table('trial_history')->whereIn('trial_id', $trialIds)->delete();
            $this->assertTargetDatabase();
            $this->db->table('trials')->whereIn('id', $trialIds)->delete();
        }
        if ($leadIds !== []) {
            $this->assertTargetDatabase();
            $this->db->table('lead_status_history')->whereIn('lead_id', $leadIds)->delete();
        }
        if ($leadIds !== []) {
            $this->assertTargetDatabase();
            $this->db->table('leads')->whereIn('id', $leadIds)->delete();
        }
        if ($userIds !== []) {
            $this->assertTargetDatabase();
            $this->db->table('user_roles')->whereIn('user_id', $userIds)->delete();
            $this->assertTargetDatabase();
            $this->db->table('user_branches')->whereIn('user_id', $userIds)->delete();
            $this->assertTargetDatabase();
            $this->db->table('users')->whereIn('id', $userIds)->delete();
        }
        if ($this->grounds !== []) {
            $this->assertTargetDatabase();
            $this->db->table('grounds')->whereIn('id', array_unique($this->grounds))->delete();
        }
        if ($branchIds !== []) {
            $this->assertTargetDatabase();
            $this->db->table('branches')->whereIn('id', $branchIds)->delete();
        }
    }
    private function assertTargetDatabase(): void
    {
        self::assertSame('mcc_cricket_club_test', $this->db->getDatabase());
    }
}
