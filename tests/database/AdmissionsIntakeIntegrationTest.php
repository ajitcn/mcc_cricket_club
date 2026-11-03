<?php

declare (strict_types=1);
namespace Tests\Database;

use App\Models\UserModel;
use App\Services\AdmissionService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use RuntimeException;
use Throwable;
final class AdmissionsIntakeIntegrationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    protected $DBGroup = 'tests';
    protected $migrate = false;
    private int $branchOne;
    private int $branchTwo;
    private int $actor;
    private int $admissionsBefore;
    private int $historyBefore;
    private int $playersBefore;
    private array $batches = [];
    protected function setUp(): void
    {
        parent::setUp();
        self::assertSame('mcc_cricket_club_test', $this->db->getDatabase());
        $now = date('Y-m-d H:i:s');
        $this->db->table('branches')->insert(['name' => 'TEST_MCC_ADMISSION_NORTH_' . uniqid(), 'code' => 'TEST_MCC_ADMISSION_N_' . uniqid(),
    'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);
        $this->branchOne = (int) $this->db->insertID();
        $this->db->table('branches')->insert(['name' => 'TEST_MCC_ADMISSION_SOUTH_' . uniqid(), 'code' => 'TEST_MCC_ADMISSION_S_' . uniqid(),
    'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);
        $this->branchTwo = (int) $this->db->insertID();
        $this->actor = $this->createUser('test_mcc_admission_actor_' . uniqid() . '@example.test');
        $this->db->table('user_branches')->insert(['user_id' => $this->actor, 'branch_id' => $this->branchOne, 'created_at' => $now]);
        $this->admissionsBefore = $this->db->table('admissions')->countAllResults();
        $this->historyBefore = $this->db->table('admission_status_history')->countAllResults();
        $this->playersBefore = $this->db->table('players')->countAllResults();
        $this->setSession(['admission.create', 'admission.view', 'admission.update', 'player.convert']);
    }
    protected function tearDown(): void
    {
        session()->destroy();
        $this->cleanupOwnFixtures();
        parent::tearDown();
    }
    private function cleanupOwnFixtures(): void
    {
        $branchIds = [$this->branchOne, $this->branchTwo];
        $admissionIds = array_map('intval', array_column($this->db->table('admissions')->whereIn('branch_id', $branchIds)->get()->getResultArray(), 'id'));
        $players = $this->db->table('players')->whereIn('branch_id', $branchIds)->get()->getResultArray();
        $playerIds = array_map('intval', array_column($players, 'id'));
        $personIds = array_map('intval', array_column($players, 'person_id'));
        if ($admissionIds !== []) {
            $this->db->table('admission_status_history')->whereIn('admission_id', $admissionIds)->delete();
            $this->db->table('admissions')->whereIn('id', $admissionIds)->update(['converted_player_id' => null]);
        }
        if ($playerIds !== []) {
            $this->db->table('player_batch_assignments')->whereIn('player_id', $playerIds)->delete();
            $this->db->table('player_coach_assignments')->whereIn('player_id', $playerIds)->delete();
            $this->db->table('session_players')->whereIn('player_id', $playerIds)->delete();
            $this->db->table('players')->whereIn('id', $playerIds)->delete();
        }
        if ($personIds !== []) {
            $this->db->table('persons')->whereIn('id', $personIds)->delete();
        }
        if ($this->batches !== []) {
            $this->db->table('batches')->whereIn('id', $this->batches)->delete();
        }
        $this->db->table('admissions')->whereIn('branch_id', $branchIds)->delete();
        $this->db->table('leads')->whereIn('branch_id', $branchIds)->delete();
        $this->db->table('user_branches')->where('user_id', $this->actor)->delete();
        $this->db->table('users')->where('id', $this->actor)->delete();
        $this->db->table('branches')->whereIn('id', $branchIds)->delete();
    }
    public function testAuthorizedIntakePreservesLeadAndStartsAtIntake(): void
    {
        $lead = $this->createLead($this->branchOne, 'lead');
        $id = $this->admissions()->createFromLead($lead);
        $row = $this->db->table('admissions')->where('id', $id)->get()->getRowArray();
        self::assertSame($lead, (int) $row['lead_id']);
        self::assertSame($this->branchOne, (int) $row['branch_id']);
        self::assertSame('INTAKE', $row['status']);
        self::assertMatchesRegularExpression('/^MCC-\d{6}$/', (string) $row['admission_number']);
        self::assertSame($this->playersBefore, $this->db->table('players')->countAllResults());
    }

    public function testDirectAdmissionsGenerateUniqueNumbersAndEditsPreserveThem(): void
    {
        $firstId = $this->admissions()->createDirect([
            'branch_id' => $this->branchOne,
            'applicant_full_name' => 'Generated Number One',
        ]);
        $secondId = $this->admissions()->createDirect([
            'branch_id' => $this->branchOne,
            'applicant_full_name' => 'Generated Number Two',
        ]);

        $first = $this->db->table('admissions')->where('id', $firstId)->get()->getRowArray();
        $second = $this->db->table('admissions')->where('id', $secondId)->get()->getRowArray();

        self::assertMatchesRegularExpression('/^MCC-\d{6}$/', (string) $first['admission_number']);
        self::assertMatchesRegularExpression('/^MCC-\d{6}$/', (string) $second['admission_number']);
        self::assertNotSame($first['admission_number'], $second['admission_number']);
        self::assertSame(
            (int) substr((string) $first['admission_number'], 4) + 1,
            (int) substr((string) $second['admission_number'], 4),
        );

        $this->admissions()->updateIntake($firstId, [
            'applicant_full_name' => 'Updated Generated Number One',
            'admission_number' => 'FORGED-REFERENCE',
        ]);

        $updated = $this->db->table('admissions')->where('id', $firstId)->get()->getRowArray();

        self::assertSame($first['admission_number'], $updated['admission_number']);
        self::assertSame('Updated Generated Number One', $updated['applicant_full_name']);
    }

    public function testInitialAdmissionAllowsMissingLeadAndBothDates(): void
    {
        $id = $this->admissions()->createDirect([
            'branch_id' => $this->branchOne,
            'applicant_full_name' => 'Optional Admission',
        ]);

        $row = $this->db->table('admissions')->where('id', $id)->get()->getRowArray();

        self::assertNull($row['lead_id']);
        self::assertNull($row['joining_date']);
        self::assertNull($row['year1_end_date']);
    }

    public function testDirectAdmissionRatingsPersistWithoutLeadOrTrial(): void
    {
        $id = $this->admissions()->createDirect([
            'branch_id' => $this->branchOne,
            'applicant_full_name' => 'Manual Rating Admission',
            'ability_overall' => '4',
            'ability_batting' => '5',
            'ability_bowling' => '6',
            'ability_wicketkeeping' => '7',
            'ability_fitness' => '8',
        ]);

        $row = $this->db->table('admissions')->where('id', $id)->get()->getRowArray();

        self::assertNull($row['lead_id']);
        self::assertNull($row['trial_id']);
        self::assertSame('4', (string) $row['ability_overall']);
        self::assertSame('8', (string) $row['ability_fitness']);

        $this->admissions()->updateIntake($id, [
            'ability_overall' => '9',
            'ability_batting' => '',
            'ability_bowling' => '2',
            'ability_wicketkeeping' => '',
            'ability_fitness' => '1',
        ]);

        $updated = $this->db->table('admissions')->where('id', $id)->get()->getRowArray();

        self::assertSame('9', (string) $updated['ability_overall']);
        self::assertNull($updated['ability_batting']);
        self::assertSame('2', (string) $updated['ability_bowling']);
        self::assertNull($updated['ability_wicketkeeping']);
        self::assertSame('1', (string) $updated['ability_fitness']);

        $edit = $this->withSession(session()->get())->get('admissions/' . $id . '/edit');

        self::assertSame(200, $edit->response()->getStatusCode(), $edit->getBody());
        self::assertMatchesRegularExpression(
            '/name="ability_overall"[^>]*>.*value="9\.0 - Excellent" selected/s',
            $edit->getBody(),
        );
        self::assertMatchesRegularExpression(
            '/name="ability_batting"[^>]*>.*Choose rating/s',
            $edit->getBody(),
        );
    }

    public function testInitialAdmissionAllowsEitherDateIndependently(): void
    {
        $withoutStart = $this->admissions()->createDirect([
            'branch_id' => $this->branchOne,
            'applicant_full_name' => 'Missing Start Date',
            'end_date' => '31-12-2026',
        ]);
        $withoutEnd = $this->admissions()->createDirect([
            'branch_id' => $this->branchOne,
            'applicant_full_name' => 'Missing End Date',
            'start_date' => '01-01-2026',
        ]);

        $first = $this->db->table('admissions')->where('id', $withoutStart)->get()->getRowArray();
        $second = $this->db->table('admissions')->where('id', $withoutEnd)->get()->getRowArray();

        self::assertNull($first['joining_date']);
        self::assertSame('2026-12-31', $first['year1_end_date']);
        self::assertSame('2026-01-01', $second['joining_date']);
        self::assertNull($second['year1_end_date']);
    }

    public function testInitialAdmissionValidatesDateOrderAndAllowsLaterEdit(): void
    {
        $id = $this->admissions()->createDirect([
            'branch_id' => $this->branchOne,
            'applicant_full_name' => 'Date Order Admission',
            'start_date' => '01-01-2026',
            'end_date' => '31-12-2026',
        ]);

        try {
            $this->admissions()->createDirect([
                'branch_id' => $this->branchOne,
                'applicant_full_name' => 'Invalid Date Order',
                'start_date' => '31-12-2026',
                'end_date' => '01-01-2026',
            ]);
            self::fail('Invalid date ordering was accepted.');
        } catch (RuntimeException $exception) {
            self::assertSame('Admission end date must be on or after the start date.', $exception->getMessage());
        }

        $this->admissions()->updateIntake($id, [
            'start_date' => '01-02-2026',
            'end_date' => '31-01-2027',
        ]);
        $row = $this->db->table('admissions')->where('id', $id)->get()->getRowArray();

        self::assertSame('2026-02-01', $row['joining_date']);
        self::assertSame('2027-01-31', $row['year1_end_date']);
    }

    public function testTrialCannotBeSuppliedWithoutLead(): void
    {
        try {
            $this->admissions()->createDirect([
                'branch_id' => $this->branchOne,
                'applicant_full_name' => 'Trial Without Lead',
                'trial_id' => '1',
            ]);
            self::fail('Trial without Lead was accepted.');
        } catch (RuntimeException $exception) {
            self::assertSame('Trial requires a Lead.', $exception->getMessage());
        }
    }

    public function testValidAgePersistsAndAdmissionDataRemainsPreservedOnEdit(): void
    {
        $admission = $this->admissions()->createFromLead(
            $this->createLead($this->branchOne, 'lead'),
            [
                'age' => '15',
                'gender' => 'Female',
                'father_name' => 'Age Test Father',
                'student_signature' => 'student-signature',
                'parent_signature' => 'parent-signature',
                'authorized_signature' => 'club-signature',
            ],
        );

        $this->admissions()->updateIntake($admission, ['age' => '16']);
        $row = $this->db->table('admissions')->where('id', $admission)->get()->getRowArray();

        self::assertSame(16, (int) $row['age']);
        self::assertSame('Female', $row['gender']);
        self::assertSame('Age Test Father', $row['father_name']);
        self::assertSame('student-signature', $row['student_signature']);
        self::assertSame('parent-signature', $row['parent_signature']);
        self::assertSame('club-signature', $row['authorized_signature']);
        self::assertSame('INTAKE', $row['status']);
    }

    public function testCoachingSelectionsPersistAsValidatedJsonAndReloadOnEdit(): void
    {
        $admission = $this->admissions()->createFromLead(
            $this->createLead($this->branchOne, 'lead'),
            [
                'coaching_type' => ['Personal coaching', 'Group coaching'],
                'personal_coaching_days' => '2 Days',
                'personal_coaching_duration' => '1.5 hours',
                'estimated_personal_session' => '8',
                'group_coaching_days' => '3 Days',
                'coaching_delivery' => 'Indoor+Outdoor',
                'fitness_plan' => ['Group Strengthening', 'Fitness on Ground'],
                'gym_fitness_session' => ['Strengthening', 'Fitness session at GYM (Personal session)'],
                'match_program' => ['Match Simulation', 'Practice Match'],
                'induction_plan' => 'Yes',
                'elite_camp' => 'No',
                'student_diary' => 'Yes',
                'welcome_kit' => ['1 Whites', '1 Kit Bag'],
            ],
        );

        $row = $this->db->table('admissions')->where('id', $admission)->get()->getRowArray();

        self::assertSame('Indoor+Outdoor', $row['coaching_delivery']);
        self::assertSame(
            ['Personal coaching', 'Group coaching'],
            json_decode($row['coaching_type'], true),
        );
        self::assertSame('2 Days', $row['personal_coaching_days']);
        self::assertSame('1.5 hours', $row['personal_coaching_duration']);
        self::assertSame('8', $row['estimated_personal_session']);
        self::assertSame('3 Days', $row['group_coaching_days']);
        self::assertSame(['Group Strengthening', 'Fitness on Ground'], json_decode($row['fitness_plan'], true));
        self::assertSame(
            ['Strengthening', 'Fitness session at GYM (Personal session)'],
            json_decode($row['gym_fitness_session'], true),
        );
        self::assertSame(['Match Simulation', 'Practice Match'], json_decode($row['match_program'], true));
        self::assertSame('Yes', $row['induction_plan']);
        self::assertSame('No', $row['elite_camp']);
        self::assertSame('Yes', $row['student_diary']);
        self::assertSame(['1 Whites', '1 Kit Bag'], json_decode($row['welcome_kit'], true));

        $page = $this->withSession(session()->get())->get('admissions/' . $admission . '/edit');

        self::assertSame(200, $page->response()->getStatusCode());
        self::assertStringContainsString('value="Group Strengthening" checked', $page->getBody());
        self::assertStringContainsString('value="Fitness on Ground" checked', $page->getBody());
        self::assertStringContainsString('value="1 Kit Bag" checked', $page->getBody());
        self::assertStringContainsString('value="Indoor+Outdoor" checked', $page->getBody());
        self::assertStringContainsString('value="Personal coaching" checked', $page->getBody());
        self::assertStringContainsString('value="Group coaching" checked', $page->getBody());
    }

    public function testInstallmentPlanRowsPersistReloadAndClearForFullPayment(): void
    {
        $admission = $this->admissions()->createFromLead(
            $this->createLead($this->branchOne, 'lead'),
            [
                'installment_plan' => 'Instalment Plan',
                'installment_year' => ['1', '2'],
                'installment_1_date' => ['01-01-2027', '01-01-2028'],
                'installment_1' => ['1000', '1200'],
                'installment_2_date' => ['01-02-2027', '01-02-2028'],
                'installment_2' => ['1100', '1300'],
                'installment_3_date' => ['01-03-2027', '01-03-2028'],
                'installment_3' => ['1200', '1400'],
                'installment_4_date' => ['01-04-2027', '01-04-2028'],
                'installment_4' => ['1300', '1500'],
            ],
        );

        $rows = $this->db->table('admission_coaching_installments')
            ->where('admission_id', $admission)
            ->orderBy('year_number')
            ->orderBy('installment_number')
            ->get()
            ->getResultArray();

        self::assertCount(8, $rows);
        self::assertSame(1, (int) $rows[0]['year_number']);
        self::assertSame(1, (int) $rows[0]['installment_number']);
        self::assertSame('1000.00', $rows[0]['amount']);
        self::assertSame('2027-01-01', $rows[0]['due_date']);
        self::assertSame(4, (int) $rows[3]['installment_number']);
        self::assertSame('1500.00', $rows[7]['amount']);
        self::assertSame('2028-04-01', $rows[7]['due_date']);
        self::assertSame(0, $this->db->table('admission_hostel_installments')->where('admission_id', $admission)->countAllResults());

        $page = $this->withSession(session()->get())->get('admissions/' . $admission . '/edit');
        self::assertSame(200, $page->response()->getStatusCode());
        self::assertStringContainsString('value="1000.00"', $page->getBody());
        self::assertStringContainsString('value="1500.00"', $page->getBody());
        self::assertStringContainsString('value="01-01-2027"', $page->getBody());
        self::assertStringContainsString('value="01-04-2028"', $page->getBody());

        $this->admissions()->updateIntake($admission, [
            'installment_plan' => 'Full Payment',
            'installment_year' => ['forged'],
            'installment_1_date' => ['not-a-date'],
            'installment_1' => ['forged'],
        ]);

        self::assertSame(
            0,
            $this->db->table('admission_coaching_installments')->where('admission_id', $admission)->countAllResults(),
        );
    }

    public function testBlankAndPartialInstallmentRowsPersistAndFailedUpdatesRollback(): void
    {
        $admission = $this->admissions()->createFromLead(
            $this->createLead($this->branchOne, 'lead'),
            [
                'installment_plan' => 'Instalment Plan',
                'installment_year' => ['1', '2'],
                'installment_1_date' => ['01-01-2027', ''],
                'installment_1' => ['', ''],
                'installment_2_date' => ['', ''],
                'installment_2' => ['', ''],
                'installment_3_date' => ['', ''],
                'installment_3' => ['', ''],
                'installment_4_date' => ['', ''],
                'installment_4' => ['', ''],
                'hostel_installment_plan' => 'Instalment Plan',
                'hostel_installment_year' => ['1'],
                'hostel_installment_1_date' => [''],
                'hostel_installment_1' => ['500'],
                'hostel_installment_2_date' => [''],
                'hostel_installment_2' => [''],
                'hostel_installment_3_date' => [''],
                'hostel_installment_3' => [''],
                'hostel_installment_4_date' => [''],
                'hostel_installment_4' => [''],
            ],
        );

        $coaching = $this->db->table('admission_coaching_installments')
            ->where('admission_id', $admission)
            ->get()
            ->getResultArray();
        $hostel = $this->db->table('admission_hostel_installments')
            ->where('admission_id', $admission)
            ->get()
            ->getResultArray();

        self::assertCount(1, $coaching);
        self::assertSame('2027-01-01', $coaching[0]['due_date']);
        self::assertNull($coaching[0]['amount']);
        self::assertCount(1, $hostel);
        self::assertNull($hostel[0]['due_date']);
        self::assertSame('500.00', $hostel[0]['amount']);

        try {
            $this->admissions()->updateIntake($admission, [
                'installment_plan' => 'Instalment Plan',
                'installment_year' => ['1'],
                'installment_1_date' => ['02-01-2027'],
                'installment_1' => ['not-a-number'],
            ]);
            self::fail('An invalid supplied installment amount was accepted.');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('amount', strtolower($exception->getMessage()));
        }

        $afterFailure = $this->db->table('admission_coaching_installments')
            ->where('admission_id', $admission)
            ->get()
            ->getResultArray();
        self::assertSame($coaching, $afterFailure);

        $this->admissions()->updateIntake($admission, [
            'installment_plan' => 'Instalment Plan',
            'installment_year' => ['1'],
            'installment_1_date' => [''],
            'installment_1' => [''],
        ]);

        self::assertSame(
            0,
            $this->db->table('admission_coaching_installments')->where('admission_id', $admission)->countAllResults(),
        );
    }

    public function testCoachingAndHostelFeesUseDecimalSafeAuthoritativeTotal(): void
    {
        $admission = $this->admissions()->createFromLead(
            $this->createLead($this->branchOne, 'lead'),
            [
                'admission_fee' => '999.99',
                'training_fee' => '100.10',
                'hostel_charges' => '200.20',
            ],
        );

        $details = $this->admissions()->details($admission);

        self::assertSame('300.30', $details['total_coaching_hostel_fee']);
        self::assertStringNotContainsString('999', $details['total_coaching_hostel_fee']);
    }

    public function testTaxInformationPersistsAndDerivedValuesIgnorePostedCalculations(): void
    {
        $admission = $this->admissions()->createFromLead(
            $this->createLead($this->branchOne, 'lead'),
            [
                'training_fee' => '1000.00',
                'hostel_charges' => '500.00',
                'total_taxable_fee' => '1200.00',
                'tax_rate' => '18',
                'calculated_tax_amount' => '1.00',
                'total_including_tax' => '2.00',
            ],
        );

        $row = $this->db->table('admissions')->where('id', $admission)->get()->getRowArray();
        self::assertSame('1200.00', $row['total_taxable_fee']);
        self::assertSame('18.00', $row['tax_rate']);
        self::assertSame('216.00', $row['calculated_tax_amount']);
        self::assertSame('1716.00', $row['total_including_tax']);

        $this->admissions()->updateIntake($admission, [
            'training_fee' => '2000.00',
            'hostel_charges' => '500.00',
            'total_taxable_fee' => '1200.00',
            'tax_rate' => '18',
        ]);

        $details = $this->admissions()->details($admission);
        self::assertSame('1200.00', $details['total_taxable_fee']);
        self::assertSame('216.00', $details['calculated_tax_amount']);
        self::assertSame('2716.00', $details['total_including_tax']);
    }

    public function testInstallmentPlanRejectsMalformedAndDuplicateRowsWithoutWrites(): void
    {
        foreach ([
            [
                'installment_year' => ['1', '1'],
                'installment_1_date' => ['01-01-2027', '01-01-2028'],
                'installment_1' => ['100', '200'],
                'installment_2_date' => ['01-02-2027', '01-02-2028'],
                'installment_2' => ['100', '200'],
                'installment_3_date' => ['01-03-2027', '01-03-2028'],
                'installment_3' => ['100', '200'],
                'installment_4_date' => ['01-04-2027', '01-04-2028'],
                'installment_4' => ['100', '200'],
            ],
            [
                'installment_year' => ['1'],
                'installment_1_date' => ['01-01-2027'],
                'installment_1' => ['forged'],
                'installment_2_date' => ['01-02-2027'],
                'installment_2' => ['100'],
                'installment_3_date' => ['01-03-2027'],
                'installment_3' => ['100'],
                'installment_4_date' => ['01-04-2027'],
                'installment_4' => ['100'],
            ],
            [
                'installment_year' => ['1'],
                'installment_1_date' => ['31-02-2027'],
                'installment_1' => ['100'],
                'installment_2_date' => ['01-02-2027'],
                'installment_2' => ['100'],
                'installment_3_date' => ['01-03-2027'],
                'installment_3' => ['100'],
                'installment_4_date' => ['01-04-2027'],
                'installment_4' => ['100'],
            ],
        ] as $input) {
            $lead = $this->createLead($this->branchOne, 'lead');
            $installmentsBefore = $this->db->table('admission_coaching_installments')->countAllResults();

            try {
                $this->admissions()->createFromLead(
                    $lead,
                    array_merge(['installment_plan' => 'Instalment Plan'], $input),
                );
                self::fail('Invalid instalment rows were accepted.');
            } catch (RuntimeException $exception) {
                self::assertNotSame('', $exception->getMessage());
            }

            self::assertSame(0, $this->db->table('admissions')->where('lead_id', $lead)->countAllResults());
            self::assertSame($installmentsBefore, $this->db->table('admission_coaching_installments')->countAllResults());
        }
    }

    public function testHostelInstallmentPlanRowsPersistSeparatelyAndClearForFullPayment(): void
    {
        $admission = $this->admissions()->createFromLead(
            $this->createLead($this->branchOne, 'lead'),
            [
                'hostel_installment_plan' => 'Instalment Plan',
                'hostel_installment_year' => ['1'],
                'hostel_installment_1_date' => ['05-01-2027'],
                'hostel_installment_1' => ['500'],
                'hostel_installment_2_date' => ['05-02-2027'],
                'hostel_installment_2' => ['600'],
                'hostel_installment_3_date' => ['05-03-2027'],
                'hostel_installment_3' => ['700'],
                'hostel_installment_4_date' => ['05-04-2027'],
                'hostel_installment_4' => ['800'],
            ],
        );

        $row = $this->db->table('admission_hostel_installments')
            ->where(['admission_id' => $admission, 'installment_number' => 1])
            ->get()
            ->getRowArray();

        self::assertIsArray($row);
        self::assertSame('2027-01-05', $row['due_date']);
        self::assertSame('500.00', $row['amount']);
        self::assertSame(4, $this->db->table('admission_hostel_installments')->where('admission_id', $admission)->countAllResults());
        self::assertSame(0, $this->db->table('admission_coaching_installments')->where('admission_id', $admission)->countAllResults());

        $page = $this->withSession(session()->get())->get('admissions/' . $admission . '/edit');
        self::assertSame(200, $page->response()->getStatusCode());
        self::assertStringContainsString('value="05-01-2027"', $page->getBody());
        self::assertStringNotContainsString('name="hostel_payment_plan"', $page->getBody());
        self::assertStringContainsString('name="hostel_installment_plan"', $page->getBody());
        self::assertStringContainsString('data-admission-installment-section="coaching-installments"', $page->getBody());
        self::assertStringContainsString('data-admission-installment-section="hostel-installments"', $page->getBody());
        self::assertStringNotContainsString('data-admission-subsection="coaching-fee-schedule"', $page->getBody());
        self::assertStringNotContainsString('data-admission-subsection="hostel-schedule"', $page->getBody());

        $this->admissions()->updateIntake($admission, [
            'hostel_installment_plan' => 'Full Payment',
            'hostel_installment_year' => ['forged'],
            'hostel_installment_1_date' => ['not-a-date'],
            'hostel_installment_1' => ['forged'],
        ]);

        self::assertSame(
            0,
            $this->db->table('admission_hostel_installments')
                ->where(['admission_id' => $admission])
                ->countAllResults(),
        );
    }

    public function testRemovedHostelRequirementInputIsOptionalAndHistoricalValueIsPreserved(): void
    {
        $admission = $this->admissions()->createFromLead(
            $this->createLead($this->branchOne, 'lead'),
            ['hostel_required' => 1],
        );

        $this->admissions()->updateIntake($admission, [
            'applicant_full_name' => 'Updated without hostel requirement field',
        ]);

        $row = $this->db->table('admissions')->where('id', $admission)->get()->getRowArray();

        self::assertIsArray($row);
        self::assertSame(1, (int) $row['hostel_required']);
    }

    public function testEliteCampDetailsAreRequiredPersistedAndClearedWhenDisabled(): void
    {
        $lead = $this->createLead($this->branchOne, 'lead');
        $admission = $this->admissions()->createFromLead($lead, [
            'coaching_type' => ['Elite'],
            'elite_camp_days' => '3 Days',
            'elite_camp_duration' => '1.5 hours',
            'estimated_elite_session' => '12',
        ]);

        $row = $this->db->table('admissions')->where('id', $admission)->get()->getRowArray();
        self::assertSame('Yes', $row['elite_camp']);
        self::assertSame(['Elite'], json_decode($row['coaching_type'], true));
        self::assertSame('3 Days', $row['elite_camp_days']);
        self::assertSame('1.5 hours', $row['elite_camp_duration']);
        self::assertSame('12', $row['estimated_elite_session']);

        $page = $this->withSession(session()->get())->get('admissions/' . $admission . '/edit');
        self::assertSame(200, $page->response()->getStatusCode());
        self::assertStringContainsString('value="3 Days" selected', $page->getBody());
        self::assertStringContainsString('value="1.5 hours" selected', $page->getBody());
        self::assertStringNotContainsString('id="admission-elite-camp-days" name="elite_camp_days" hidden', $page->getBody());

        $this->admissions()->updateIntake($admission, [
            'elite_camp' => 'No',
            'elite_camp_days' => '3 Days',
            'elite_camp_duration' => '1.5 hours',
            'estimated_elite_session' => '12',
        ]);

        $row = $this->db->table('admissions')->where('id', $admission)->get()->getRowArray();
        self::assertSame('No', $row['elite_camp']);
        self::assertNull($row['elite_camp_days']);
        self::assertNull($row['elite_camp_duration']);
        self::assertNull($row['estimated_elite_session']);
    }

    public function testEliteCampRejectsMissingAndForgedOptionsWithoutWrites(): void
    {
        foreach ([
            ['elite_camp' => 'Yes'],
            ['elite_camp' => 'Yes', 'elite_camp_days' => 'Forged', 'elite_camp_duration' => '1 hour'],
            ['elite_camp' => 'Yes', 'elite_camp_days' => '1 Day', 'elite_camp_duration' => 'Forged'],
        ] as $index => $input) {
            $lead = $this->createLead($this->branchOne, 'lead');

            try {
                $this->admissions()->createFromLead($lead, $input);
                self::fail('Invalid Elite Camp details were accepted.');
            } catch (RuntimeException $exception) {
                if ($index === 0) {
                    self::assertSame('Elite Camp details are required when Elite Camp is Yes.', $exception->getMessage());
                } else {
                    self::assertSame('Invalid coaching plan option.', $exception->getMessage());
                }
            }

            self::assertSame(0, $this->db->table('admissions')->where('lead_id', $lead)->countAllResults());
        }
    }

    public function testEstimatedCoachingSessionsRejectInvalidValuesWithoutWrites(): void
    {
        foreach (['-1', '1.5', 'many'] as $estimate) {
            $lead = $this->createLead($this->branchOne, 'lead');

            try {
                $this->admissions()->createFromLead($lead, [
                    'coaching_type' => ['Personal coaching'],
                    'personal_coaching_days' => '2 Days',
                    'personal_coaching_duration' => '1 hour',
                    'estimated_personal_session' => $estimate,
                ]);
                self::fail('Invalid estimated coaching sessions were accepted.');
            } catch (RuntimeException $exception) {
                self::assertSame(
                    'Estimated coaching sessions must be a non-negative whole number.',
                    $exception->getMessage(),
                );
            }

            self::assertSame(0, $this->db->table('admissions')->where('lead_id', $lead)->countAllResults());
        }
    }

    public function testUnselectedCoachingFieldsAreIgnoredAndCleared(): void
    {
        $lead = $this->createLead($this->branchOne, 'lead');
        $admission = $this->admissions()->createFromLead($lead, [
            'coaching_type' => ['Group coaching'],
            'personal_coaching_days' => 'Forged personal days',
            'personal_coaching_duration' => 'Forged personal duration',
            'group_coaching_days' => '3 Days',
            'elite_camp_days' => 'Forged elite days',
            'elite_camp_duration' => 'Forged elite duration',
            'estimated_personal_session' => 'Forged personal estimate',
            'estimated_elite_session' => 'Forged elite estimate',
        ]);

        $row = $this->db->table('admissions')->where('id', $admission)->get()->getRowArray();

        self::assertSame(['Group coaching'], json_decode($row['coaching_type'], true));
        self::assertNull($row['personal_coaching_days']);
        self::assertNull($row['personal_coaching_duration']);
        self::assertSame('3 Days', $row['group_coaching_days']);
        self::assertNull($row['elite_camp_days']);
        self::assertNull($row['elite_camp_duration']);
        self::assertNull($row['estimated_personal_session']);
        self::assertNull($row['estimated_elite_session']);
        self::assertSame('No', $row['elite_camp']);
    }

    public function testForgedCoachingSelectionsAreRejectedWithoutWrites(): void
    {
        $lead = $this->createLead($this->branchOne, 'lead');

        try {
            $this->admissions()->createFromLead($lead, [
                'fitness_plan' => ['Group Strengthening', 'Forged option'],
            ]);
            self::fail('Forged coaching selection was accepted.');
        } catch (RuntimeException $exception) {
            self::assertSame('Invalid coaching plan option.', $exception->getMessage());
        }

        self::assertSame(0, $this->db->table('admissions')->where('lead_id', $lead)->countAllResults());

        foreach ([
            ['coaching_delivery' => 'Choose option'],
            ['induction_plan' => 'Maybe'],
            ['review_frequency' => 'Monthly'],
            ['progress_report_frequency' => 'Monthly'],
            ['feedback_call_frequency' => 'Monthly'],
            ['student_diary' => 'Maybe'],
            ['fitness_plan' => ['Choose option']],
        ] as $placeholderInput) {
            $placeholderLead = $this->createLead($this->branchOne, 'lead');

            try {
                $this->admissions()->createFromLead($placeholderLead, $placeholderInput);
                self::fail('Placeholder coaching selection was accepted.');
            } catch (RuntimeException $exception) {
                self::assertSame('Invalid coaching plan option.', $exception->getMessage());
            }

            self::assertSame(
                0,
                $this->db->table('admissions')->where('lead_id', $placeholderLead)->countAllResults(),
            );
        }
    }

    public function testNegativeAndOutOfBoundsAgeValuesAreRejectedWithoutWrites(): void
    {
        foreach (['-1', '121'] as $age) {
            $lead = $this->createLead($this->branchOne, 'lead');

            try {
                $this->admissions()->createFromLead($lead, ['age' => $age]);
                self::fail('Invalid age was accepted: ' . $age);
            } catch (RuntimeException $exception) {
                self::assertSame('Invalid age.', $exception->getMessage());
            }

            self::assertSame(
                0,
                $this->db->table('admissions')->where('lead_id', $lead)->countAllResults(),
            );
        }
    }

    public function testAgeInputDoesNotBypassBranchAuthorization(): void
    {
        $lead = $this->createLead($this->branchTwo, 'lead');

        try {
            $this->admissions()->createFromLead($lead, [
                'age' => '15',
                'student_signature' => 'cross-branch-signature',
            ]);
            self::fail('Cross-branch age input was accepted.');
        } catch (RuntimeException $exception) {
            self::assertSame('Forbidden', $exception->getMessage());
        }

        self::assertSame(0, $this->db->table('admissions')->where('lead_id', $lead)->countAllResults());
    }

    public function testEditPagePrefillsPopulatedPendingAdmissionRelationships(): void
    {
        $now = date('Y-m-d H:i:s');
        $this->db->table('batches')->insert(['branch_id' => $this->branchOne, 'name' => 'Prefilled U15 Batch', 'created_at' => $now, 'updated_at' => $now]);
        $batch = (int) $this->db->insertID();
        $this->batches[] = $batch;
        $admission = $this->admissions()->createFromLead($this->createLead($this->branchOne, 'lead'), [
            'gender' => 'Female',
            'age_group' => 'U15',
            'batch_id' => $batch,
            'joining_date' => '2026-09-15',
            'blood_group' => 'O+',
            'medical_conditions' => 'None recorded',
            'father_name' => 'Existing Father',
            'parent_mobile' => '9999999998',
            'parent_email' => 'parent@example.test',
        ]);
        $page = $this->withSession(session()->get())->get('admissions/' . $admission . '/edit');
        self::assertSame(200, $page->response()->getStatusCode());
        self::assertStringContainsString('value="Admission Candidate"', $page->getBody());
        self::assertStringContainsString('Batch Coach', $page->getBody());
        self::assertStringContainsString('Personal Coach', $page->getBody());
        self::assertStringContainsString('value="' . $this->branchOne . '" selected', $page->getBody());
        self::assertStringContainsString('>Prefilled U15 Batch</option>', $page->getBody());
        self::assertStringContainsString('value="15-09-2026"', $page->getBody());
        self::assertStringContainsString('value="Female"', $page->getBody());
        self::assertStringContainsString('value="Existing Father"', $page->getBody());
        self::assertStringContainsString('name="batch_id"', $page->getBody());
        self::assertStringContainsString('value="' . $batch . '" selected', $page->getBody());
    }
    public function testPartialEditPreservesExistingAdmissionValues(): void
    {
        $now = date('Y-m-d H:i:s');
        $this->db->table('batches')->insert(['branch_id' => $this->branchOne, 'name' => 'Preserved Batch', 'created_at' => $now, 'updated_at' => $now]);
        $batch = (int) $this->db->insertID();
        $this->batches[] = $batch;
        $admission = $this->admissions()->createFromLead($this->createLead($this->branchOne, 'lead'), [
            'batch_id' => $batch,
            'joining_date' => '2026-09-20',
            'father_name' => 'Preserved Father',
            'medical_conditions' => 'Preserved condition',
        ]);
        $this->admissions()->updateIntake($admission, ['gender' => 'Female']);
        $row = $this->db->table('admissions')->where('id', $admission)->get()->getRowArray();
        self::assertSame('Female', $row['gender']);
        self::assertSame($batch, (int) $row['batch_id']);
        self::assertSame('2026-09-20', $row['joining_date']);
        self::assertSame('Preserved Father', $row['father_name']);
        self::assertSame('Preserved condition', $row['medical_conditions']);
    }

    public function testCricketHobbiesAndAchievementsPersistAndRejectOverlongValues(): void
    {
        $admission = $this->admissions()->createFromLead($this->createLead($this->branchOne, 'lead'), [
            'hobbies' => 'Reading and chess',
            'achievements' => 'District tournament winner',
        ]);

        $row = $this->db->table('admissions')->where('id', $admission)->get()->getRowArray();
        self::assertSame('Reading and chess', $row['hobbies']);
        self::assertSame('District tournament winner', $row['achievements']);

        foreach (['hobbies', 'achievements'] as $field) {
            try {
                $this->admissions()->updateIntake($admission, [$field => str_repeat('x', 5001)]);
                self::fail('Overlong ' . $field . ' value was accepted.');
            } catch (RuntimeException $exception) {
                self::assertSame('Invalid admission details.', $exception->getMessage());
            }
        }

        $preserved = $this->db->table('admissions')->where('id', $admission)->get()->getRowArray();
        self::assertSame('Reading and chess', $preserved['hobbies']);
        self::assertSame('District tournament winner', $preserved['achievements']);
    }

    public function testAdmissionStoresIndependentRatingInput(): void
    {
        $admission = $this->admissions()->createFromLead($this->createLead($this->branchOne, 'lead'), [
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

        $this->admissions()->updateIntake($admission, [
            'ability_overall' => '3',
            'ability_batting' => '2',
            'ability_bowling' => '1',
            'ability_wicketkeeping' => '0',
            'ability_fitness' => '4',
        ]);
        $detail = $this->withSession(session()->get())->get('admissions/' . $admission . '/page');
        self::assertSame(200, $detail->response()->getStatusCode(), $detail->getBody());
        self::assertStringContainsString('<dt>Overall Rating</dt>', $detail->getBody());
        self::assertMatchesRegularExpression('/<dd>\s*3\.0 - Regular\s*<\/dd>/', $detail->getBody());

        $edit = $this->withSession(session()->get())->get('admissions/' . $admission . '/edit');
        self::assertMatchesRegularExpression(
            '/name="ability_overall"[^>]*>.*value="3\.0 - Regular" selected/s',
            $edit->getBody(),
        );

        $this->admissions()->updateIntake($admission, [
            'ability_overall' => '',
            'ability_batting' => '',
            'ability_bowling' => '',
            'ability_wicketkeeping' => '',
            'ability_fitness' => '',
        ]);
        $blankDetail = $this->withSession(session()->get())->get('admissions/' . $admission . '/page');
        self::assertSame(200, $blankDetail->response()->getStatusCode(), $blankDetail->getBody());
        self::assertStringContainsString('<dt>Overall Rating</dt>', $blankDetail->getBody());
        self::assertMatchesRegularExpression('/<dd>\s*&mdash;\s*<\/dd>/', $blankDetail->getBody());

        try {
            $this->admissions()->createFromLead($this->createLead($this->branchOne, 'lead'), [
                'ability_batting' => '11',
            ]);
            self::fail('Out-of-range rating was accepted.');
        } catch (RuntimeException $exception) {
            self::assertSame('Invalid admission rating.', $exception->getMessage());
        }
    }

    public function testBrowserEditPostUpdatesAndRedirectsToEditPage(): void
    {
        $admission = $this->admissions()->createFromLead($this->createLead($this->branchOne, 'lead'));
        $response = $this->withSession(session()->get())->post(
            'admissions/' . $admission . '/edit',
            [
                csrf_token() => csrf_hash(),
                'gender' => 'Female',
                'status' => 'INTAKE',
            ],
        );

        self::assertSame(302, $response->response()->getStatusCode());
        self::assertSame(
            site_url('admissions/' . $admission . '/edit'),
            $response->response()->getHeaderLine('Location'),
        );
        self::assertSame(
            'Female',
            $this->db->table('admissions')->where('id', $admission)->get()->getRow('gender'),
        );
        self::assertSame(
            'INTAKE',
            $this->db->table('admissions')->where('id', $admission)->get()->getRow('status'),
        );
    }

    public function testAdmissionEditLoadsPersistedStatusAndCompletesThroughLifecycle(): void
    {
        $admission = $this->admissions()->createFromLead($this->createLead($this->branchOne, 'lead'));
        $this->completeChecklist($admission);
        $edit = $this->withSession(session()->get())->get('admissions/' . $admission . '/edit');

        self::assertMatchesRegularExpression(
            '/<option value="INTAKE" selected>Pending Admission<\/option>/s',
            $edit->getBody(),
        );

        $historyBefore = $this->db->table('admission_status_history')
            ->where('admission_id', $admission)
            ->countAllResults();
        $auditBefore = $this->db->table('audit_logs')
            ->where(['action' => 'admission_status_changed', 'entity_id' => (string) $admission])
            ->countAllResults();

        $response = $this->withSession(session()->get())->post(
            'admissions/' . $admission . '/edit',
            [
                csrf_token() => csrf_hash(),
                'status' => 'APPROVED',
            ],
        );

        self::assertSame(302, $response->response()->getStatusCode());
        self::assertSame(site_url('admissions/' . $admission . '/page'), $response->response()->getHeaderLine('Location'));
        self::assertSame(
            'APPROVED',
            $this->db->table('admissions')->where('id', $admission)->get()->getRow('status'),
        );
        self::assertSame($historyBefore + 1, $this->db->table('admission_status_history')
            ->where('admission_id', $admission)
            ->countAllResults());
        self::assertSame($auditBefore + 1, $this->db->table('audit_logs')
            ->where(['action' => 'admission_status_changed', 'entity_id' => (string) $admission])
            ->countAllResults());

        $history = $this->db->table('admission_status_history')
            ->where(['admission_id' => $admission, 'new_status' => 'APPROVED'])
            ->orderBy('id', 'DESC')
            ->get()
            ->getRowArray();
        self::assertSame($this->actor, (int) $history['actor_user_id']);
        self::assertSame('INTAKE', $history['previous_status']);
        self::assertSame('Admission completed.', $history['reason']);
        self::assertNotEmpty($history['created_at']);

        $audit = $this->db->table('audit_logs')
            ->where(['action' => 'admission_status_changed', 'entity_id' => (string) $admission])
            ->orderBy('id', 'DESC')
            ->get()
            ->getRowArray();
        self::assertSame($this->actor, (int) $audit['actor_user_id']);
        self::assertSame($this->branchOne, (int) $audit['branch_id']);
        self::assertSame('Admission completed.', $audit['reason']);
        self::assertSame('INTAKE', json_decode((string) $audit['before_summary'], true)['status']);
        self::assertSame('APPROVED', json_decode((string) $audit['after_summary'], true)['status']);
        self::assertNotEmpty($audit['created_at']);

        $detail = $this->withSession(session()->get())->get('admissions/' . $admission . '/page');
        $list = $this->withSession(session()->get())->get('admissions?status=APPROVED');
        self::assertStringContainsString('APPROVED', $detail->getBody());
        self::assertStringContainsString('APPROVED', $list->getBody());
    }

    public function testAdmissionEditCompletionDoesNotRequireChecklistData(): void
    {
        $admission = $this->admissions()->createFromLead($this->createLead($this->branchOne, 'lead'));

        $response = $this->withSession(session()->get())->post(
            'admissions/' . $admission . '/edit',
            [
                csrf_token() => csrf_hash(),
                'status' => 'APPROVED',
                'gender' => 'Female',
            ],
        );

        self::assertSame(302, $response->response()->getStatusCode());
        $after = $this->db->table('admissions')->where('id', $admission)->get()->getRowArray();
        self::assertSame('APPROVED', $after['status']);
        self::assertSame('Female', $after['gender']);
        self::assertSame(site_url('admissions/' . $admission . '/page'), $response->response()->getHeaderLine('Location'));
    }

    public function testRepeatedAdmissionEditCompletionDoesNotDuplicateLifecycleEvents(): void
    {
        $admission = $this->admissions()->createFromLead($this->createLead($this->branchOne, 'lead'));
        $payload = [
            csrf_token() => csrf_hash(),
            'status' => 'APPROVED',
        ];

        $this->withSession(session()->get())->post('admissions/' . $admission . '/edit', $payload);
        $historyAfterFirst = $this->db->table('admission_status_history')
            ->where(['admission_id' => $admission, 'new_status' => 'APPROVED'])
            ->countAllResults();
        $auditAfterFirst = $this->db->table('audit_logs')
            ->where(['action' => 'admission_status_changed', 'entity_id' => (string) $admission])
            ->countAllResults();

        $this->withSession(session()->get())->post('admissions/' . $admission . '/edit', $payload);

        self::assertSame($historyAfterFirst, $this->db->table('admission_status_history')
            ->where(['admission_id' => $admission, 'new_status' => 'APPROVED'])
            ->countAllResults());
        self::assertSame($auditAfterFirst, $this->db->table('audit_logs')
            ->where(['action' => 'admission_status_changed', 'entity_id' => (string) $admission])
            ->countAllResults());
    }

    public function testBrowserEditRejectsInvalidSubmittedStatus(): void
    {
        $admission = $this->admissions()->createFromLead($this->createLead($this->branchOne, 'lead'));
        $response = $this->withSession(session()->get())->post(
            'admissions/' . $admission . '/edit',
            [
                csrf_token() => csrf_hash(),
                'status' => 'NOT_A_STATUS',
            ],
        );

        self::assertSame(302, $response->response()->getStatusCode());
        self::assertSame(
            'INTAKE',
            $this->db->table('admissions')->where('id', $admission)->get()->getRow('status'),
        );
        self::assertSame('Select a valid admission status.', session()->getFlashdata('error'));
    }

    public function testBrowserEditCompletionWithoutCsrfDoesNotChangeStatus(): void
    {
        $admission = $this->admissions()->createFromLead($this->createLead($this->branchOne, 'lead'));
        try {
            $response = $this->withSession(session()->get())->post(
                'admissions/' . $admission . '/edit',
                ['status' => 'APPROVED'],
            );
            self::assertSame(403, $response->response()->getStatusCode());
        } catch (\CodeIgniter\Security\Exceptions\SecurityException $exception) {
            self::assertSame('The action you requested is not allowed.', $exception->getMessage());
        }
        self::assertSame(
            'INTAKE',
            $this->db->table('admissions')->where('id', $admission)->get()->getRow('status'),
        );
    }

    public function testBrowserEditValidationFailureReturnsToEditPage(): void
    {
        $admission = $this->admissions()->createFromLead($this->createLead($this->branchOne, 'lead'));
        $response = $this->withSession(session()->get())->post(
            'admissions/' . $admission . '/edit',
            [
                csrf_token() => csrf_hash(),
                'joining_date' => 'not-a-date',
            ],
        );

        self::assertSame(302, $response->response()->getStatusCode());
        self::assertSame(
            site_url('admissions/' . $admission . '/edit'),
            $response->response()->getHeaderLine('Location'),
        );
        self::assertSame(
            'INTAKE',
            $this->db->table('admissions')->where('id', $admission)->get()->getRow('status'),
        );
        self::assertSame('Enter valid admission details.', session()->getFlashdata('error'));
    }

    public function testBrowserEditKeepsTerminalAdmissionLocked(): void
    {
        $admission = $this->admissions()->createFromLead($this->createLead($this->branchOne, 'lead'));
        $this->admissions()->transition($admission, 'REJECTED', 'Applicant withdrew');

        $response = $this->withSession(session()->get())->post(
            'admissions/' . $admission . '/edit',
            [
                csrf_token() => csrf_hash(),
                'gender' => 'Female',
            ],
        );

        self::assertSame(302, $response->response()->getStatusCode());
        self::assertSame(
            site_url('admissions/' . $admission . '/edit'),
            $response->response()->getHeaderLine('Location'),
        );
        self::assertSame(
            'This admission is no longer editable.',
            session()->getFlashdata('error'),
        );
        self::assertSame(
            'REJECTED',
            $this->db->table('admissions')->where('id', $admission)->get()->getRow('status'),
        );
    }

    public function testBrowserStatusChangePersistsAndReturnsToDetails(): void
    {
        $admission = $this->approvedAdmission();
        $historyBefore = $this->db->table('admission_status_history')
            ->where('admission_id', $admission)
            ->countAllResults();
        $auditBefore = $this->db->table('audit_logs')
            ->where(['action' => 'admission_status_changed', 'entity_id' => (string) $admission])
            ->countAllResults();

        $response = $this->withSession(session()->get())->post(
            'admissions/' . $admission . '/status',
            [
                csrf_token() => csrf_hash(),
                'status' => 'WITHDRAWN',
                'reason' => 'Applicant requested withdrawal',
            ],
        );

        self::assertSame(302, $response->response()->getStatusCode());
        self::assertSame(
            site_url('admissions/' . $admission . '/page'),
            $response->response()->getHeaderLine('Location'),
        );
        self::assertSame(
            'WITHDRAWN',
            $this->db->table('admissions')->where('id', $admission)->get()->getRow('status'),
        );
        self::assertSame($historyBefore + 1, $this->db->table('admission_status_history')
            ->where('admission_id', $admission)
            ->countAllResults());
        self::assertSame($auditBefore + 1, $this->db->table('audit_logs')
            ->where(['action' => 'admission_status_changed', 'entity_id' => (string) $admission])
            ->countAllResults());
    }

    public function testBrowserStatusValidationFailureReturnsToDetails(): void
    {
        $admission = $this->approvedAdmission();
        $response = $this->withSession(session()->get())->post(
            'admissions/' . $admission . '/status',
            [
                csrf_token() => csrf_hash(),
                'status' => 'WITHDRAWN',
            ],
        );

        self::assertSame(302, $response->response()->getStatusCode());
        self::assertSame(
            site_url('admissions/' . $admission . '/page'),
            $response->response()->getHeaderLine('Location'),
        );
        self::assertSame('A reason is required.', session()->getFlashdata('error'));
        self::assertSame(
            'APPROVED',
            $this->db->table('admissions')->where('id', $admission)->get()->getRow('status'),
        );
    }

    public function testWithdrawnAdmissionCanReturnToApprovedThroughBrowserStatusFlow(): void
    {
        $admission = $this->approvedAdmission();
        $this->admissions()->transition($admission, 'WITHDRAWN', 'Applicant requested withdrawal');

        $page = $this->withSession(session()->get())->get('admissions/' . $admission . '/page');
        self::assertStringNotContainsString('Change status', $page->getBody());
        self::assertStringContainsString('Current status / lifecycle', $page->getBody());
        $list = $this->withSession(session()->get())->get('admissions');
        self::assertStringContainsString('data-admission-action="status"', $list->getBody());
        self::assertStringContainsString('APPROVED', $list->getBody());
        $historyBefore = $this->db->table('admission_status_history')
            ->where('admission_id', $admission)
            ->countAllResults();
        $auditBefore = $this->db->table('audit_logs')
            ->where(['action' => 'admission_status_changed', 'entity_id' => (string) $admission])
            ->countAllResults();

        $response = $this->withSession(session()->get())->post(
            'admissions/' . $admission . '/status',
            [
                csrf_token() => csrf_hash(),
                'status' => 'APPROVED',
            ],
        );

        self::assertSame(302, $response->response()->getStatusCode());
        self::assertSame(
            site_url('admissions/' . $admission . '/page'),
            $response->response()->getHeaderLine('Location'),
        );
        self::assertSame(
            'APPROVED',
            $this->db->table('admissions')->where('id', $admission)->get()->getRow('status'),
        );
        self::assertSame($historyBefore + 1, $this->db->table('admission_status_history')
            ->where('admission_id', $admission)
            ->countAllResults());
        self::assertSame($auditBefore + 1, $this->db->table('audit_logs')
            ->where(['action' => 'admission_status_changed', 'entity_id' => (string) $admission])
            ->countAllResults());
        self::assertSame(1, $this->db->table('admission_status_history')->where([
            'admission_id' => $admission,
            'previous_status' => 'WITHDRAWN',
            'new_status' => 'APPROVED',
        ])->countAllResults());
    }

    public function testUnauthorizedAndCrossBranchChangesAreDenied(): void
    {
        $lead = $this->createLead($this->branchOne, 'lead');
        $this->setSession([]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Forbidden');
        $this->admissions()->createFromLead($lead);
    }
    public function testCrossBranchLeadCannotBeIntaked(): void
    {
        $lead = $this->createLead($this->branchTwo, 'lead');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Forbidden');
        $this->admissions()->createFromLead($lead);
    }
    public function testInvalidNonexistentAndIneligibleLeadsRollback(): void
    {
        try {
            $this->admissions()->createFromLead(999999);
            self::fail('Missing lead accepted.');
        } catch (RuntimeException $exception) {
            self::assertSame('Not found.', $exception->getMessage());
        }
        $lost = $this->createLead($this->branchOne, 'lost');
        try {
            $this->admissions()->createFromLead($lost);
            self::fail('Lost lead accepted.');
        } catch (RuntimeException $exception) {
            self::assertSame('Lead is not eligible.', $exception->getMessage());
        }
        self::assertSame(0, $this->db->table('admissions')->where('lead_id', $lost)->countAllResults());
        self::assertSame($this->historyBefore, $this->db->table('admission_status_history')->countAllResults());
    }
    public function testDuplicateIntakeIsPrevented(): void
    {
        $lead = $this->createLead($this->branchOne, 'lead');
        $this->admissions()->createFromLead($lead);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Admission already exists.');
        $this->admissions()->createFromLead($lead);
    }
    public function testEveryAllowedNonConversionTransitionIsAccepted(): void
    {
        $direct = ['IN_REVIEW', 'DOCUMENTS_PENDING', 'REJECTED', 'WITHDRAWN'];
        foreach ($direct as $status) {
            $id = $this->newAdmission();
            $this->admissions()->transition($id, $status, in_array($status, ['REJECTED', 'WITHDRAWN'], true) ? 'Reason' : null);
        }
        foreach (['DOCUMENTS_PENDING', 'REJECTED', 'WITHDRAWN', 'APPROVED'] as $status) {
            $id = $this->newAdmission();
            $this->admissions()->transition($id, 'IN_REVIEW');
            if ($status === 'APPROVED') {
                $this->completeChecklist($id);
            }
            $this->admissions()->transition($id, $status, in_array($status, ['REJECTED', 'WITHDRAWN'], true) ? 'Reason' : null);
        }
        foreach (['IN_REVIEW', 'REJECTED', 'WITHDRAWN', 'APPROVED'] as $status) {
            $id = $this->newAdmission();
            $this->admissions()->transition($id, 'DOCUMENTS_PENDING');
            if ($status === 'APPROVED') {
                $this->completeChecklist($id);
            }
            $this->admissions()->transition($id, $status, in_array($status, ['REJECTED', 'WITHDRAWN'], true) ? 'Reason' : null);
        }
        $id = $this->approvedAdmission();
        $this->admissions()->transition($id, 'WITHDRAWN', 'Applicant withdrew');
        self::assertSame($this->admissionsBefore + 13, $this->db->table('admissions')->countAllResults());
    }
    public function testEveryForbiddenTransitionAndTerminalProtectionIsRejected(): void
    {
        $states = ['INTAKE' => $this->newAdmission(), 'IN_REVIEW' => $this->moveTo('IN_REVIEW'), 'DOCUMENTS_PENDING' => $this->moveTo('DOCUMENTS_PENDING'),
    'APPROVED' => $this->approvedAdmission(), 'REJECTED' => $this->rejectedAdmission(), 'WITHDRAWN' => $this->withdrawnAdmission()];
        $allowed = [
            'INTAKE' => ['IN_REVIEW', 'DOCUMENTS_PENDING', 'APPROVED', 'REJECTED', 'WITHDRAWN'],
            'IN_REVIEW' => ['DOCUMENTS_PENDING', 'APPROVED', 'REJECTED', 'WITHDRAWN'],
            'DOCUMENTS_PENDING' => ['IN_REVIEW', 'APPROVED', 'REJECTED', 'WITHDRAWN'],
            'APPROVED' => ['CONVERTED', 'WITHDRAWN'],
            'REJECTED' => ['IN_REVIEW'],
            'WITHDRAWN' => ['IN_REVIEW', 'DOCUMENTS_PENDING', 'APPROVED'],
        ];
        foreach ($states as $current => $id) {
            foreach (['INTAKE', 'IN_REVIEW', 'DOCUMENTS_PENDING', 'APPROVED', 'REJECTED', 'WITHDRAWN', 'CONVERTED'] as $candidate) {
                if (!in_array($candidate, $allowed[$current], true)) {
                    $this->assertInvalidTransition($id, $candidate);
                }
            }
        }
        $converted = $this->approvedAdmission();
        $this->admissions()->convert($converted);
        foreach (['INTAKE', 'IN_REVIEW', 'DOCUMENTS_PENDING', 'APPROVED', 'REJECTED', 'WITHDRAWN', 'CONVERTED'] as $candidate) {
            $this->assertInvalidTransition($converted, $candidate);
        }
    }
    public function testCompletedTransitionDoesNotRequireChecklist(): void
    {
        $id = $this->newAdmission();
        $this->admissions()->transition($id, 'APPROVED');

        self::assertSame('APPROVED', $this->db->table('admissions')->where('id', $id)->get()->getRow('status'));
        self::assertSame(1, $this->db->table('admission_status_history')->where([
            'admission_id' => $id,
            'new_status' => 'APPROVED',
        ])->countAllResults());
    }
    public function testCompletedTransitionRecordsApprovalWithoutChecklistFlags(): void
    {
        $id = $this->newAdmission();
        $before = $this->db->table('admissions')->where('id', $id)->get()->getRowArray();
        $this->admissions()->transition($id, 'APPROVED');
        $row = $this->db->table('admissions')->where('id', $id)->get()->getRowArray();
        self::assertSame('APPROVED', $row['status']);
        self::assertSame($before['checklist_complete'], $row['checklist_complete']);
        self::assertSame($before['checklist_approval_recorded'], $row['checklist_approval_recorded']);
        self::assertSame($this->actor, (int) $row['approved_by']);
        self::assertNotNull($row['approved_at']);
    }
    public function testRejectionAndWithdrawalRequireReasons(): void
    {
        foreach (['REJECTED', 'WITHDRAWN'] as $status) {
            $id = $this->newAdmission();
            $this->assertReasonRequired($id, $status);
            $this->admissions()->transition($id, $status, 'Applicant requested closure');
        }
        self::assertSame(2, $this->db->table('admission_status_history')->where('actor_user_id', $this->actor)->whereIn('new_status', ['REJECTED',
    'WITHDRAWN'])->countAllResults());
    }
    public function testConversionCreatesOnePlayerWithLineageAndIsIdempotent(): void
    {
        $id = $this->approvedAdmission();
        $first = $this->admissions()->convert($id);
        $second = $this->admissions()->convert($id);
        self::assertSame($first, $second);
        self::assertSame(1, $this->db->table('players')->where('admission_id', $id)->countAllResults());
        $player = $this->db->table('players')->where('id', $first)->get()->getRowArray();
        self::assertSame($id, (int) $player['admission_id']);
        $admission = $this->db->table('admissions')->where('id', $id)->get()->getRowArray();
        self::assertSame('CONVERTED', $admission['status']);
        self::assertSame(1, $this->db->table('admission_status_history')->where(['admission_id' => $id, 'new_status' => 'CONVERTED'])->countAllResults());
        self::assertSame(1, $this->db->table('audit_logs')->where(['action' => 'admission_converted', 'entity_id' => (string) $id])->countAllResults());
    }
    public function testConversionRequiresApprovedStatusAndFailureRollsBack(): void
    {
        $id = $this->newAdmission();
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Admission is not approved.');
        try {
            $this->admissions()->convert($id);
        } finally {
            self::assertSame(0, $this->db->table('players')->where('admission_id', $id)->countAllResults());
            self::assertSame('INTAKE', $this->db->table('admissions')->where('id', $id)->get()->getRow()->status);
        }
    }
    public function testRejectedAdmissionReopensOnlyToInReviewWithReasonAndPreservesChecklist(): void
    {
        $id = $this->newAdmission();
        $this->admissions()->updateChecklist($id, ['checklist_identity_complete' => true]);
        $this->admissions()->transition($id, 'REJECTED', 'Initial rejection');

        try {
            $this->admissions()->transition($id, 'IN_REVIEW');
            self::fail('Rejected admission reopened without a reason.');
        } catch (RuntimeException $exception) {
            self::assertSame('Reason required.', $exception->getMessage());
        }

        $this->admissions()->transition($id, 'IN_REVIEW', 'Documents received for reconsideration');
        $row = $this->db->table('admissions')->where('id', $id)->get()->getRowArray();
        self::assertSame('IN_REVIEW', $row['status']);
        self::assertSame(1, (int) $row['checklist_identity_complete']);
        self::assertSame(0, (int) $row['checklist_complete']);

        $history = $this->db->table('admission_status_history')->where([
            'admission_id' => $id,
            'previous_status' => 'REJECTED',
            'new_status' => 'IN_REVIEW',
        ])->get()->getRowArray();
        self::assertSame('Documents received for reconsideration', $history['reason']);
        $audit = $this->db->table('audit_logs')->where([
            'action' => 'admission_status_changed',
            'entity_id' => (string) $id,
        ])->orderBy('id', 'DESC')->get()->getRowArray();
        self::assertSame('Documents received for reconsideration', $audit['reason']);
    }
    public function testPendingAdmissionCompletionConvertsOnceAndAssignsBatch(): void
    {
        $now = date('Y-m-d H:i:s');
        $this->db->table('batches')->insert(['branch_id' => $this->branchOne, 'name' => 'Test Completion Batch', 'created_at' => $now, 'updated_at' => $now]);
        $batch = (int) $this->db->insertID();
        $this->batches[] = $batch;
        $admission = $this->admissions()->createFromLead($this->createLead($this->branchOne, 'lead'));
        $player = $this->admissions()->complete($admission, ['batch_id' => $batch, 'joining_date' => '15-09-2026']);
        $samePlayer = $this->admissions()->complete($admission, ['batch_id' => $batch, 'joining_date' => '15-09-2026']);
        self::assertSame($player, $samePlayer);
        self::assertSame('CONVERTED', $this->db->table('admissions')->where('id', $admission)->get()->getRow()->status);
        self::assertSame(1, $this->db->table('players')->where('admission_id', $admission)->countAllResults());
        self::assertSame(1, $this->db->table('player_batch_assignments')->where(['batch_id' => $batch, 'effective_to' => null])->countAllResults());
        self::assertSame(0, $this->db->table('admissions')->where(['id' => $admission, 'status' => 'INTAKE'])->countAllResults());
        self::assertSame(1, $this->db->table('admission_status_history')->where(['admission_id' => $admission, 'new_status' => 'CONVERTED'])->countAllResults());
    }
    public function testPendingAdmissionCompletionRequiresBatchAndDateWithoutPartialWrites(): void
    {
        $admission = $this->admissions()->createFromLead($this->createLead($this->branchOne, 'lead'));
        try {
            $this->admissions()->complete($admission, []);
            self::fail('Incomplete admission was accepted.');
        } catch (RuntimeException $exception) {
            self::assertSame('Batch and admission date are required.', $exception->getMessage());
        }
        self::assertSame('INTAKE', $this->db->table('admissions')->where('id', $admission)->get()->getRow()->status);
        self::assertSame(0, $this->db->table('players')->where('admission_id', $admission)->countAllResults());
    }
    public function testPendingAdmissionCompletionRejectsCrossBranchBatch(): void
    {
        $now = date('Y-m-d H:i:s');
        $this->db->table('batches')->insert(['branch_id' => $this->branchTwo, 'name' => 'Other Branch Batch', 'created_at' => $now, 'updated_at' => $now]);
        $batch = (int) $this->db->insertID();
        $this->batches[] = $batch;
        $admission = $this->admissions()->createFromLead($this->createLead($this->branchOne, 'lead'));
        try {
            $this->admissions()->complete($admission, [
                'batch_id' => $batch,
                'joining_date' => '15-09-2026',
            ]);
            self::fail('Cross-branch batch conversion was accepted.');
        } catch (RuntimeException $exception) {
            self::assertSame(
                'Admission batch does not belong to the Admission branch.',
                $exception->getMessage(),
            );
        }
        self::assertSame('INTAKE', $this->db->table('admissions')->where('id', $admission)->get()->getRow()->status);
        self::assertSame(0, $this->db->table('players')->where('admission_id', $admission)->countAllResults());
    }
    public function testConflictingPlayerLineagePreventsDuplicateAndLeavesAdmissionApproved(): void
    {
        $id = $this->approvedAdmission();
        $lead = $this->db->table('leads')->where('id', $this->db->table('admissions')->where('id', $id)->get()->getRow()->lead_id)->get()->getRowArray();
        $now = date('Y-m-d H:i:s');
        $this->db->table('persons')->insert(['full_name' => 'Existing Player', 'created_at' => $now, 'updated_at' => $now]);
        $person = (int) $this->db->insertID();
        $this->db->table('players')->insert(['person_id' => $person, 'lead_id' => $lead['id'], 'admission_id' => null, 'branch_id' => $this->branchOne,
    'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
        try {
            $this->admissions()->convert($id);
            self::fail('Conflicting player was silently duplicated.');
        } catch (Throwable $exception) {
            self::assertSame('APPROVED', $this->db->table('admissions')->where('id', $id)->get()->getRow()->status);
            self::assertNull($this->db->table('admissions')->where('id', $id)->get()->getRow()->converted_player_id);
            self::assertSame(1, $this->db->table('players')->where('person_id', $person)->countAllResults());
        }
    }
    private function approvedAdmission(): int
    {
        $id = $this->newAdmission();
        $this->admissions()->updateChecklist($id, ['checklist_identity_complete' => true, 'checklist_contact_complete' => true,
    'checklist_admission_complete' => true, 'checklist_branch_confirmed' => true]);
        $this->admissions()->transition($id, 'APPROVED');
        return $id;
    }
    private function completeChecklist(int $id): void
    {
        $this->admissions()->updateChecklist($id, ['checklist_identity_complete' => true, 'checklist_contact_complete' => true,
    'checklist_admission_complete' => true, 'checklist_branch_confirmed' => true]);
    }
    private function moveTo(string $status): int
    {
        $id = $this->newAdmission();
        if ($status === 'IN_REVIEW') {
            $this->admissions()->transition($id, $status);
        } else {
            $this->admissions()->transition($id, 'IN_REVIEW');
            $this->admissions()->transition($id, $status);
        }
        return $id;
    }
    private function rejectedAdmission(): int
    {
        $id = $this->newAdmission();
        $this->admissions()->transition($id, 'REJECTED', 'Rejected');
        return $id;
    }
    private function withdrawnAdmission(): int
    {
        $id = $this->newAdmission();
        $this->admissions()->transition($id, 'WITHDRAWN', 'Withdrawn');
        return $id;
    }
    private function newAdmission(): int
    {
        return $this->admissions()->createFromLead($this->createLead($this->branchOne, 'lead'));
    }
    private function assertInvalidTransition(int $id, string $status): void
    {
        try {
            $this->admissions()->transition($id, $status);
            self::fail('Forbidden transition accepted: ' . $status);
        } catch (RuntimeException $exception) {
            self::assertSame('Invalid transition.', $exception->getMessage());
        }
    }
    private function assertReasonRequired(int $id, string $status): void
    {
        try {
            $this->admissions()->transition($id, $status);
            self::fail('Missing reason accepted.');
        } catch (RuntimeException $exception) {
            self::assertSame('Reason required.', $exception->getMessage());
        }
    }
    private function admissions(): AdmissionService
    {
        return service('admission', false);
    }
    private function createLead(int $branch, string $status): int
    {
        $now = date('Y-m-d H:i:s');
        $this->db->table('leads')->insert(['branch_id' => $branch, 'owner_user_id' => $this->actor, 'source' => 'website',
    'full_name' => 'Admission Candidate', 'email' => uniqid('candidate', true) . '@example.test', 'phone' => '9999999999', 'date_of_birth' => '2012-05-10',
    'status' => $status, 'lost_reason' => $status === 'lost' ? 'No response' : null, 'created_at' => $now, 'updated_at' => $now]);
        return (int) $this->db->insertID();
    }
    private function createUser(string $email): int
    {
        $model = new UserModel();
        $model->insert(['email' => $email, 'password_hash' => password_hash('Test password!', PASSWORD_DEFAULT), 'display_name' => 'Admission User',
    'is_enabled' => 1, 'failed_login_count' => 0, 'session_version' => 1, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
        return (int) $model->getInsertID();
    }
    private function setSession(array $permissions): void
    {
        \Config\Services::resetSingle('authorization');
        session()->set(['isLoggedIn' => true, 'accountEnabled' => true, 'user' => ['id' => $this->actor], 'session_version' => 1,
    'authorizedBranches' => [(string) $this->branchOne], 'permissions' => $permissions]);
    }
}
