<?php

declare (strict_types=1);
namespace Tests\Database;

use App\Services\SchedulingService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;
/** Non-destructive Scheduling coverage; no table cleanup or fixture replacement. */
final class SchedulingSafeWorkflowTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    protected $DBGroup = 'tests';
    protected $migrate = false;
    private int $actor;
    private int $branch;
    private int $otherBranch;
    private int $batch;
    private int $program;
    private int $otherBatch;
    private int $otherProgram;
    private int $ageGroup;
    private int $otherAgeGroup;
    private int $coach;
    private int $player;
    private int $slot;
    private string $fixtureMarker;
    protected function setUp(): void
    {
        parent::setUp();
        self::assertSame('mcc_cricket_club_test', strtolower((string) $this->db->getDatabase()));
        $this->actor = (int) $this->db->table('users')->where('email', 'test.mcc.superadmin@example.invalid')->get()->getRow('id');
        $this->branch = (int) $this->db->table('branches')->where('code', 'TEST_MCC_VNS')->get()->getRow('id');
        $this->otherBranch = (int) $this->db->table('branches')->where('code', 'TEST_MCC_DEL')->get()->getRow('id');
        $this->batch = (int) $this->db->table('batches')->where(['branch_id' => $this->branch, 'name' => 'TEST_MCC_VNS_U15'])->get()->getRow('id');
        $this->program = (int) $this->db->table('batches')->where('id', $this->batch)->get()->getRow('program_id');
        $this->otherBatch = (int) $this->db->table('batches')->where(['branch_id' => $this->otherBranch, 'name' => 'TEST_MCC_DEL_U13'])->get()->getRow('id');
        $this->otherProgram = (int) $this->db->table('batches')->where('id', $this->otherBatch)->get()->getRow('program_id');
        $this->coach = (int) $this->db->table('users')->where('email', 'test.mcc.coach@example.invalid')->get()->getRow('id');
        $this->fixtureMarker = 'TEST_MCC_SCHEDULING_' . bin2hex(random_bytes(6));
        $this->ageGroup = $this->ageGroupFixture('TEST_MCC_U14');
        $this->otherAgeGroup = $this->ageGroupFixture('TEST_MCC_U16');
        $this->player = $this->createEligiblePlayerFixture();
        $this->attachAgeGroupToPlayer($this->player, $this->ageGroup);
        self::assertGreaterThan(0, min($this->actor, $this->branch, $this->otherBranch, $this->batch, $this->otherBatch, $this->coach, $this->player));
        $base = new DateTimeImmutable('2100-01-01 18:30:00', new DateTimeZone('UTC'));
        $latestStart = $this->db->table('sessions')
            ->selectMax('starts_at')
            ->get()
            ->getRow('starts_at');
        $latest = is_string($latestStart) && $latestStart !== ''
            ? new DateTimeImmutable($latestStart, new DateTimeZone('UTC'))
            : $base;
        $this->slot = $latest >= $base
            ? (int) $base->diff($latest)->format('%a') + 14
            : 1;
        $this->ensureCoachAvailabilityFixture();
        $this->authorize(['session.create', 'session.update', 'session.cancel', 'player.view'], [$this->branch, $this->otherBranch]);
    }
    protected function tearDown(): void
    {
        session()->destroy();
        parent::tearDown();
    }
    public function testAuthorizedCreationPersistsUtcBranchRosterAndAudit(): void
    {
        $window = $this->window(0);
        $id = $this->scheduling()->create($this->payload($window));
        $row = $this->db->table('sessions')->where('id', $id)->get()->getRowArray();
        self::assertSame($this->branch, (int) $row['branch_id']);
        self::assertSame($window['utc_start'], $row['starts_at']);
        self::assertSame(1, $this->db->table('session_players')->where(['session_id' => $id, 'player_id' => $this->player])->countAllResults());
        self::assertSame(1, $this->db->table('audit_logs')->where(['action' => 'session_created', 'entity_id' => (string) $id])->countAllResults());
        self::assertSame($window['local_start'], $this->scheduling()->find($id)['starts_at_local']);
    }

    public function testSessionTypeSupportsOneToOneAndGroupAndRejectsMissingOrInvalidValues(): void
    {
        $oneToOneId = $this->scheduling()->create($this->payload($this->window(2700)));
        self::assertSame('one_to_one', $this->db->table('sessions')->where('id', $oneToOneId)->get()->getRow('session_type'));

        $groupId = $this->scheduling()->create(array_merge(
            $this->payload($this->window(2760)),
            ['session_type' => 'group']
        ));
        self::assertSame('group', $this->scheduling()->find($groupId)['session_type']);

        foreach ([null, '', 'solo'] as $invalidType) {
            try {
                $this->scheduling()->create(array_merge(
                    $this->payload($this->window(2820 + (is_string($invalidType) ? strlen($invalidType) : 0))),
                    ['session_type' => $invalidType]
                ));
                self::fail('Invalid Session Type was accepted.');
            } catch (RuntimeException $exception) {
                self::assertSame('Invalid session type.', $exception->getMessage());
            }
        }
    }

    public function testSessionCreationPersistsEveryDistinctSelectedPlayer(): void
    {
        $secondPlayer = (int) $this->db->table('players p')
            ->select('p.id')
            ->join('admissions a', 'a.id = p.admission_id', 'left')
            ->where([
                'p.branch_id' => $this->branch,
                'p.status' => 'active',
            ])
            ->where('p.id <>', $this->player)
            ->groupStart()
            ->where('a.coach_user_id', $this->coach)
            ->orWhere('a.personal_coach_user_id', $this->coach)
            ->groupEnd()
            ->orderBy('p.id', 'ASC')
            ->get()
            ->getRow('id');

        self::assertGreaterThan(0, $secondPlayer);
        $id = $this->scheduling()->create(array_merge(
            $this->payload($this->window(45)),
            [
                'batch_id' => '',
                'player_ids' => [$this->player, $secondPlayer, $this->player],
            ]
        ));

        $storedPlayers = $this->db->table('session_players')
            ->where('session_id', $id)
            ->orderBy('player_id', 'ASC')
            ->get()
            ->getResultArray();

        $expectedPlayerIds = [$this->player, $secondPlayer];
        sort($expectedPlayerIds);
        self::assertSame($expectedPlayerIds, array_map('intval', array_column($storedPlayers, 'player_id')));
        $pageRows = $this->scheduling()->sessionPage([])['rows'];
        $pageRow = array_values(array_filter(
            $pageRows,
            static fn (array $row): bool => (int) $row['id'] === $id
        ));
        self::assertCount(1, $pageRow);
        self::assertSame(2, (int) $pageRow[0]['selected_player_count']);
        self::assertCount(2, $this->scheduling()->find($id)['players']);

        $this->authorize(
            ['attendance.view', 'session.update', 'player.view'],
            [$this->branch]
        );
        $attendanceRoster = service('attendance', false)->roster($id);
        self::assertCount(2, $attendanceRoster['players']);
    }

    public function testAuthorizedCreationDoesNotRequireRosterPayload(): void
    {
        $payload = $this->payload($this->window(30));
        unset($payload['player_ids']);

        $id = $this->scheduling()->create($payload);

        self::assertSame(
            0,
            $this->db->table('session_players')->where('session_id', $id)->countAllResults()
        );
        self::assertSame($this->branch, (int) $this->db->table('sessions')->where('id', $id)->get()->getRow('branch_id'));
    }

    public function testSessionCanBeCreatedWithoutBatchAndPersistsNull(): void
    {
        $id = $this->scheduling()->create(array_merge(
            $this->payload($this->window(60)),
            ['batch_id' => '']
        ));
        $row = $this->db->table('sessions')->where('id', $id)->get()->getRowArray();

        self::assertNull($row['batch_id']);
        self::assertNull($this->scheduling()->find($id)['batch_name']);
        self::assertSame(
            1,
            $this->db->table('audit_logs')->where([
                'action' => 'session_created',
                'entity_id' => (string) $id,
            ])->countAllResults()
        );
    }

    public function testSessionFormsExposeAllActiveGlobalBatchesAndEditPreservesSelection(): void
    {
        $options = $this->scheduling()->sessionFormOptions('session.create', $this->branch);
        $activeBatchIds = array_map(
            'intval',
            array_column(
                $this->db->table('batches')
                    ->select('id')
                    ->where('is_active', 1)
                    ->orderBy('name', 'ASC')
                    ->get()
                    ->getResultArray(),
                'id'
            )
        );

        self::assertSame(
            $activeBatchIds,
            array_map('intval', array_column($options['batches'], 'id'))
        );

        $id = $this->scheduling()->create($this->payload($this->window(90)));
        $current = $this->db->table('sessions')->where('id', $id)->get()->getRowArray();

        $this->scheduling()->update($id, array_merge(
            $this->payload($this->window(135)),
            [
                'batch_id' => $this->batch,
                'expected_updated_at' => $current['updated_at'],
            ]
        ));

        self::assertSame(
            $this->batch,
            (int) $this->db->table('sessions')->where('id', $id)->get()->getRow('batch_id')
        );
        self::assertSame($this->batch, (int) $this->scheduling()->find($id)['batch_id']);
    }

    public function testSessionEditCanClearBatchAndRetainsOtherFields(): void
    {
        $window = $this->window(120);
        $id = $this->scheduling()->create($this->payload($window));
        $current = $this->db->table('sessions')->where('id', $id)->get()->getRowArray();

        $this->scheduling()->update($id, array_merge(
            $this->payload($this->window(180)),
            [
                'batch_id' => '',
                'expected_updated_at' => $current['updated_at'],
            ]
        ));

        $row = $this->db->table('sessions')->where('id', $id)->get()->getRowArray();
        self::assertNull($row['batch_id']);
        self::assertSame($this->branch, (int) $row['branch_id']);
        self::assertSame($this->coach, (int) $row['coach_user_id']);
        self::assertNull($this->scheduling()->find($id)['batch_name']);
    }

    public function testSessionCreateAndEditAllowOptionalEndTime(): void
    {
        $input = $this->payload($this->window(600));
        unset($input['ends_at']);
        $id = $this->scheduling()->create($input);

        $created = $this->db->table('sessions')->where('id', $id)->get()->getRowArray();
        self::assertNull($created['ends_at']);

        $current = $this->db->table('sessions')->where('id', $id)->get()->getRowArray();
        $updated = $this->payload($this->window(660));
        unset($updated['ends_at']);
        $updated['expected_updated_at'] = $current['updated_at'];
        $this->scheduling()->update($id, $updated);

        self::assertNull($this->db->table('sessions')->where('id', $id)->get()->getRow('ends_at'));
    }

    public function testSessionUpdateWithoutProgramColumnDoesNotFailAuditProjection(): void
    {
        $id = $this->scheduling()->create($this->payload($this->window(240)));
        $current = $this->db->table('sessions')->where('id', $id)->get()->getRowArray();
        $input = $this->payload($this->window(300));
        unset($input['program_id']);
        $input['expected_updated_at'] = $current['updated_at'];

        $this->scheduling()->update($id, $input);

        self::assertSame(
            1,
            $this->db->table('audit_logs')->where([
                'action' => 'session_updated',
                'entity_id' => (string) $id,
            ])->countAllResults()
        );
    }

    public function testSessionDescriptionPersistsThroughCreateEditAndBlankUpdate(): void
    {
        $description = 'Bring the <bat> kit and review & progress.';
        $id = $this->scheduling()->create(array_merge(
            $this->payload($this->window(360)),
            ['description' => $description]
        ));

        $created = $this->db->table('sessions')->where('id', $id)->get()->getRowArray();
        self::assertSame($description, $created['description']);
        self::assertSame($description, $this->scheduling()->find($id)['description']);

        $current = $this->db->table('sessions')->where('id', $id)->get()->getRowArray();
        $updatedDescription = 'Updated notes.\nSecond line.';
        $this->scheduling()->update($id, array_merge(
            $this->payload($this->window(420)),
            [
                'description' => $updatedDescription,
                'expected_updated_at' => $current['updated_at'],
            ]
        ));
        self::assertSame(
            $updatedDescription,
            $this->db->table('sessions')->where('id', $id)->get()->getRow('description')
        );

        $current = $this->db->table('sessions')->where('id', $id)->get()->getRowArray();
        $this->scheduling()->update($id, array_merge(
            $this->payload($this->window(480)),
            [
                'description' => '',
                'expected_updated_at' => $current['updated_at'],
            ]
        ));
        self::assertNull($this->db->table('sessions')->where('id', $id)->get()->getRow('description'));
    }

    public function testSession3918EditAcceptsBlankOptionalFieldsAndKeepsSubmittedRoster(): void
    {
        $sessionId = 3918;
        $original = $this->db->table('sessions')->where('id', $sessionId)->get()->getRowArray();

        if (! is_array($original)) {
            self::markTestSkipped('Session 3918 is not available in the approved test database.');
        }

        if ($this->db->table('attendance_roster_snapshots')->where('session_id', $sessionId)->countAllResults() > 0) {
            self::markTestSkipped('Session 3918 is locked by attendance as expected.');
        }

        $originalPlayers = $this->db->table('session_players')
            ->where('session_id', $sessionId)
            ->get()
            ->getResultArray();

        try {
            $this->scheduling()->update($sessionId, [
                'status' => 'scheduled',
                'branch_id' => (int) $original['branch_id'],
                'coach_user_id' => (int) $original['coach_user_id'],
                'ground_id' => (int) ($original['ground_id'] ?? 0),
                'starts_at' => (string) $original['starts_at'],
                'ends_at' => (string) $original['ends_at'],
                'batch_id' => '',
                'skill_area' => '',
                'skill_option_id' => '',
                'player_ids_present' => '1',
                'player_ids' => array_column($originalPlayers, 'player_id'),
                'expected_updated_at' => $original['updated_at'],
            ]);

            $updated = $this->db->table('sessions')->where('id', $sessionId)->get()->getRowArray();
            self::assertNull($updated['batch_id']);
            self::assertNull($updated['skill_area']);
            self::assertNull($updated['skill_option_id']);
            self::assertSame(
                array_map('intval', array_column($originalPlayers, 'player_id')),
                array_map('intval', array_column(
                    $this->db->table('session_players')->where('session_id', $sessionId)->get()->getResultArray(),
                    'player_id'
                ))
            );
        } finally {
            $this->db->table('sessions')->where('id', $sessionId)->update($original);
            $this->db->table('session_players')->where('session_id', $sessionId)->delete();
            if ($originalPlayers !== []) {
                $this->db->table('session_players')->insertBatch($originalPlayers);
            }
        }
    }

    public function testInvalidNonEmptyBatchIsRejected(): void
    {
        $payload = $this->payload($this->window(300));
        $payload['batch_id'] = '999999999';

        try {
            $this->scheduling()->create($payload);
            self::fail('An invalid Batch ID was accepted.');
        } catch (RuntimeException $exception) {
            self::assertSame('Invalid batch.', $exception->getMessage());
        }
    }

    public function testCreateRouteAcceptsBlankBatchAndStoresNull(): void
    {
        $window = $this->window(360);
        $start = new DateTimeImmutable($window['local_start']);
        $end = new DateTimeImmutable($window['local_end']);
        $response = $this->withSession(session()->get())->post('sessions', [
            csrf_token() => csrf_hash(),
            'branch_id' => $this->branch,
            'program_id' => $this->program,
            'batch_id' => '',
            'session_type' => 'one_to_one',
            'skill_area' => '',
            'skill_option_id' => '0',
            'coach_user_id' => $this->coach,
            'ground_id' => '',
            'status' => 'scheduled',
            'starts_at' => $start->format('d-m-Y'),
            'starts_time' => $start->format('H:i'),
            'ends_at' => $end->format('d-m-Y'),
            'ends_time' => $end->format('H:i'),
            'player_ids' => [$this->player],
        ]);

        self::assertSame(302, $response->response()->getStatusCode());
        preg_match('#/sessions/(\d+)/page$#', (string) $response->response()->getHeaderLine('Location'), $matches);
        $id = (int) ($matches[1] ?? 0);
        self::assertGreaterThan(0, $id);
        self::assertNull($this->db->table('sessions')->where('id', $id)->get()->getRow('batch_id'));
    }

    public function testCreateRoutePersistsEndTimeUsingTheStartDate(): void
    {
        $window = $this->window(540);
        $start = new DateTimeImmutable($window['local_start']);
        $end = $start->modify('+90 minutes');
        $response = $this->withSession(session()->get())->post('sessions', [
            csrf_token() => csrf_hash(),
            'branch_id' => $this->branch,
            'program_id' => $this->program,
            'batch_id' => $this->batch,
            'session_type' => 'one_to_one',
            'coach_user_id' => $this->coach,
            'ground_id' => '',
            'status' => 'scheduled',
            'starts_at' => $start->format('d-m-Y'),
            'starts_time' => $start->format('H:i'),
            'ends_time' => $end->format('H:i'),
            'player_ids' => [$this->player],
        ]);

        self::assertSame(302, $response->response()->getStatusCode());
        preg_match('#/sessions/(\d+)/page$#', (string) $response->response()->getHeaderLine('Location'), $matches);
        $id = (int) ($matches[1] ?? 0);
        self::assertGreaterThan(0, $id);
        self::assertSame(
            $end->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
            $this->db->table('sessions')->where('id', $id)->get()->getRow('ends_at')
        );
    }

    public function testSessionCategoryFiltersRosterAndPersistsAgeGroup(): void
    {
        $window = $this->window(900);
        $base = array_merge($this->payload($window), [
            'age_group_id' => $this->ageGroup,
        ]);

        $withoutFilters = $this->scheduling()->sessionFormOptions(
            'session.create',
            $this->branch
        );
        self::assertContains(
            $this->player,
            array_map('intval', array_column($withoutFilters['players'], 'id'))
        );

        $ageOnly = $this->scheduling()->create(array_merge($base, ['batch_id' => '']));
        $ageOnlyRow = $this->db->table('sessions')->where('id', $ageOnly)->get()->getRowArray();
        self::assertSame($this->ageGroup, (int) $ageOnlyRow['age_group_id']);
        self::assertSame($this->ageGroup, (int) $this->scheduling()->find($ageOnly)['age_group_id']);

        $batchOnly = $this->scheduling()->create(array_merge(
            $this->payload($this->window(1020)),
            ['age_group_id' => '']
        ));
        self::assertSame(
            $this->batch,
            (int) $this->db->table('sessions')->where('id', $batchOnly)->get()->getRow('batch_id')
        );

        $both = $this->scheduling()->create(array_merge(
            $this->payload($this->window(1140)),
            ['age_group_id' => $this->ageGroup]
        ));
        self::assertSame($this->ageGroup, (int) $this->scheduling()->find($both)['age_group_id']);
        $categoryRow = null;
        foreach ($this->scheduling()->sessionPage(['branch_id' => $this->branch])['rows'] as $row) {
            if ((int) $row['id'] === $both) {
                $categoryRow = $row;
                break;
            }
        }
        self::assertIsArray($categoryRow);
        self::assertSame('TEST_MCC_VNS_U15', $categoryRow['batch_name']);
        self::assertSame('TEST_MCC_U14', $categoryRow['age_group_name']);

        try {
            $this->scheduling()->create(array_merge(
                $this->payload($this->window(1260)),
                ['age_group_id' => $this->otherAgeGroup]
            ));
            self::fail('A Player outside the selected Age Group was accepted.');
        } catch (RuntimeException $exception) {
            self::assertSame('Player is not eligible.', $exception->getMessage());
        }
    }

    public function testEditRoutePersistsEndTimeAndSessionDetailsKeepsItReadable(): void
    {
        $id = $this->scheduling()->create($this->payload($this->window(600)));
        $current = $this->db->table('sessions')->where('id', $id)->get()->getRowArray();
        $start = new DateTimeImmutable($this->window(660)['local_start']);
        $end = $start->modify('+75 minutes');

        $response = $this->withSession(session()->get())->post('sessions/' . $id . '/edit', [
            csrf_token() => csrf_hash(),
            'branch_id' => $this->branch,
            'program_id' => $this->program,
            'batch_id' => $this->batch,
            'session_type' => 'one_to_one',
            'coach_user_id' => $this->coach,
            'ground_id' => '',
            'status' => 'scheduled',
            'starts_at' => $start->format('d-m-Y'),
            'starts_time' => $start->format('H:i'),
            'ends_time' => $end->format('H:i'),
            'player_ids_present' => '1',
            'player_ids' => [$this->player],
            'expected_updated_at' => $current['updated_at'],
        ]);

        self::assertSame(302, $response->response()->getStatusCode());
        self::assertStringEndsWith('/sessions/' . $id . '/page', (string) $response->response()->getHeaderLine('Location'));
        self::assertSame(
            $end->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
            $this->db->table('sessions')->where('id', $id)->get()->getRow('ends_at')
        );

        $details = $this->withSession(session()->get())->get('sessions/' . $id . '/page');
        self::assertSame(200, $details->response()->getStatusCode());
        self::assertStringContainsString('End time', $details->getBody());
        self::assertStringContainsString($end->format('H:i'), $details->getBody());
    }

    public function testEndTimeValidationRejectsInvalidAndEarlierValues(): void
    {
        $window = $this->window(720);
        $start = new DateTimeImmutable($window['local_start']);
        $basePayload = [
            csrf_token() => csrf_hash(),
            'branch_id' => $this->branch,
            'program_id' => $this->program,
            'batch_id' => '',
            'session_type' => 'one_to_one',
            'coach_user_id' => $this->coach,
            'ground_id' => '',
            'status' => 'scheduled',
            'starts_at' => $start->format('d-m-Y'),
            'starts_time' => $start->format('H:i'),
            'player_ids' => [$this->player],
        ];

        $invalid = $this->withSession(session()->get())->post('sessions', $basePayload + [
            'ends_time' => 'not-a-time',
        ]);
        self::assertSame(302, $invalid->response()->getStatusCode());
        self::assertStringEndsWith('/sessions/new', (string) $invalid->response()->getHeaderLine('Location'));
        self::assertSame('Enter valid session times.', session()->getFlashdata('error'));

        $earlier = $this->withSession(session()->get())->post('sessions', $basePayload + [
            'ends_time' => $start->modify('-15 minutes')->format('H:i'),
        ]);
        self::assertSame(302, $earlier->response()->getStatusCode());
        self::assertStringEndsWith('/sessions/new', (string) $earlier->response()->getHeaderLine('Location'));
        self::assertSame('Enter a valid session window with the start before the end.', session()->getFlashdata('error'));
    }

    public function testSkillAreaAndOptionPairingUsesCanonicalCatalogId(): void
    {
        $options = $this->db->table('session_skill_options')
            ->where('is_active', 1)
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();
        self::assertNotEmpty($options);

        $valid = $options[0];
        $otherArea = null;
        foreach ($options as $option) {
            if ((string) $option['area'] !== (string) $valid['area']) {
                $otherArea = $option;
                break;
            }
        }
        self::assertIsArray($otherArea);

        $validId = $this->scheduling()->create(array_merge(
            $this->payload($this->window(420)),
            [
                'skill_area' => $valid['area'],
                'skill_option_id' => (string) $valid['id'],
                'skill_option_label' => 'Forged label is ignored',
            ]
        ));
        $validRow = $this->db->table('sessions')->where('id', $validId)->get()->getRowArray();
        self::assertSame((string) $valid['area'], $validRow['skill_area']);
        self::assertSame((int) $valid['id'], (int) $validRow['skill_option_id']);
        self::assertSame((string) $valid['name'], $validRow['skill_option_label']);

        try {
            $this->scheduling()->create(array_merge(
                $this->payload($this->window(480)),
                [
                    'skill_area' => $otherArea['area'],
                    'skill_option_id' => (string) $valid['id'],
                ]
            ));
            self::fail('A Skill Option from another area was accepted.');
        } catch (RuntimeException $exception) {
            self::assertSame('Invalid skill pairing.', $exception->getMessage());
        }

        try {
            $this->scheduling()->create(array_merge(
                $this->payload($this->window(540)),
                [
                    'skill_area' => $valid['area'],
                    'skill_option_id' => '999999999',
                ]
            ));
            self::fail('An unknown Skill Option ID was accepted.');
        } catch (RuntimeException $exception) {
            self::assertSame('Invalid skill selection.', $exception->getMessage());
        }
    }

    public function testOptionalSkillFieldsMayBeBlank(): void
    {
        $id = $this->scheduling()->create(array_merge(
            $this->payload($this->window(570)),
            [
                'skill_area' => '',
                'skill_option_id' => '',
            ]
        ));
        $row = $this->db->table('sessions')->where('id', $id)->get()->getRowArray();

        self::assertNull($row['skill_area']);
        self::assertNull($row['skill_option_id']);
        self::assertNull($row['skill_option_label']);

        $idWithBlankOption = $this->scheduling()->create(array_merge(
            $this->payload($this->window(630)),
            [
                'skill_area' => 'batting',
                'skill_option_id' => '',
            ]
        ));
        $rowWithBlankOption = $this->db->table('sessions')
            ->where('id', $idWithBlankOption)
            ->get()
            ->getRowArray();

        self::assertSame('batting', $rowWithBlankOption['skill_area']);
        self::assertNull($rowWithBlankOption['skill_option_id']);
        self::assertNull($rowWithBlankOption['skill_option_label']);
    }

    public function testRetiredSkillOptionRemainsReadableButIsRejectedForNewSelections(): void
    {
        $option = $this->db->table('session_skill_options')
            ->where('is_active', 1)
            ->orderBy('id', 'ASC')
            ->get()
            ->getRowArray();
        self::assertIsArray($option);

        $id = $this->scheduling()->create(array_merge(
            $this->payload($this->window(600)),
            [
                'skill_area' => $option['area'],
                'skill_option_id' => (int) $option['id'],
            ]
        ));
        $this->db->table('session_skill_options')
            ->where('id', (int) $option['id'])
            ->update(['is_active' => 0, 'retired_at' => gmdate('Y-m-d H:i:s')]);

        try {
            $detail = $this->scheduling()->find($id);
            self::assertSame((string) $option['name'], $detail['skill_option_label']);

            $formOptions = $this->scheduling()->sessionFormOptions(
                'session.update',
                $this->branch,
                false,
                (int) $option['id']
            );
            self::assertContains(
                (int) $option['id'],
                array_map('intval', array_column($formOptions['skillOptions'], 'id'))
            );

            $current = $this->db->table('sessions')->where('id', $id)->get()->getRowArray();
            $this->scheduling()->update($id, array_merge(
                $this->payload($this->window(660)),
                [
                    'skill_area' => $option['area'],
                    'skill_option_id' => (int) $option['id'],
                    'expected_updated_at' => $current['updated_at'],
                ]
            ));

            try {
                $this->scheduling()->create(array_merge(
                    $this->payload($this->window(720)),
                    [
                        'skill_area' => $option['area'],
                        'skill_option_id' => (int) $option['id'],
                    ]
                ));
                self::fail('A retired Skill Option was accepted for a new session.');
            } catch (RuntimeException $exception) {
                self::assertSame('Retired skill option.', $exception->getMessage());
            }
        } finally {
            $this->db->table('session_skill_options')
                ->where('id', (int) $option['id'])
                ->update(['is_active' => 1, 'retired_at' => null]);
        }
    }

    public function testCoachMustSubmitBranchAndCoachAndCanUseTheirAuthorizedDefaults(): void
    {
        $this->authorizeAsCoach();
        $options = $this->scheduling()->sessionFormOptions('session.create');

        self::assertSame($this->coach, (int) $options['default_coach_user_id']);
        self::assertSame($this->branch, (int) $options['default_branch_id']);

        $missingBranch = $this->payload($this->window(3600));
        $missingBranch['branch_id'] = '';

        try {
            $this->scheduling()->create($missingBranch);
            self::fail('Coach session creation accepted a blank branch.');
        } catch (RuntimeException $exception) {
            self::assertSame('Please select a branch.', $exception->getMessage());
        }

        $missingCoach = $this->payload($this->window(5400));
        $missingCoach['coach_user_id'] = '';

        try {
            $this->scheduling()->create($missingCoach);
            self::fail('Coach session creation accepted a blank Coach.');
        } catch (RuntimeException $exception) {
            self::assertSame('Please select a coach.', $exception->getMessage());
        }

        $id = $this->scheduling()->create($this->payload($this->window(7200)));
        $session = $this->db->table('sessions')->where('id', $id)->get()->getRowArray();

        self::assertSame($this->branch, (int) $session['branch_id']);
        self::assertSame($this->coach, (int) $session['coach_user_id']);
    }

    public function testCoachCreateRouteUsesTheRenderedFieldsAndReturnsFieldErrorsToNewPage(): void
    {
        $this->authorizeAsCoach();
        $form = $this->withSession(session()->get())->get('sessions/new');

        self::assertSame(200, $form->response()->getStatusCode());
        self::assertStringContainsString('name="branch_id"', $form->getBody());
        self::assertStringContainsString('name="coach_user_id"', $form->getBody());
        self::assertStringContainsString('action="' . site_url('sessions') . '"', $form->getBody());
        self::assertDoesNotMatchRegularExpression(
            '/<select[^>]*id="batch-id"[^>]*required(?:="[^"]*")?/',
            $form->getBody()
        );
        self::assertDoesNotMatchRegularExpression(
            '/<select[^>]*id="batch-id"[^>]*aria-required="true"/',
            $form->getBody()
        );

        $window = $this->window(9000);
        $start = new DateTimeImmutable($window['local_start']);
        $end = new DateTimeImmutable($window['local_end']);
        $basePayload = [
            csrf_token() => csrf_hash(),
            'program_id' => $this->program,
            'batch_id' => $this->batch,
            'session_type' => 'one_to_one',
            'starts_at' => $start->format('d-m-Y'),
            'starts_time' => $start->format('H:i'),
            'ends_at' => $end->format('d-m-Y'),
            'ends_time' => $end->format('H:i'),
            'player_ids' => [$this->player],
        ];

        $missingBranch = $this->withSession(session()->get())->post('sessions', $basePayload + [
            'branch_id' => '',
            'coach_user_id' => $this->coach,
        ]);
        self::assertSame(302, $missingBranch->response()->getStatusCode());
        self::assertStringEndsWith('/sessions/new', (string) $missingBranch->response()->getHeaderLine('Location'));
        self::assertSame('Please select a branch.', session()->getFlashdata('error'));
        self::assertSame($this->coach, (int) old('coach_user_id', 0));

        $missingCoach = $this->withSession(session()->get())->post('sessions', $basePayload + [
            'branch_id' => $this->branch,
            'coach_user_id' => '',
        ]);
        self::assertSame(302, $missingCoach->response()->getStatusCode());
        self::assertStringEndsWith('/sessions/new', (string) $missingCoach->response()->getHeaderLine('Location'));
        self::assertSame('Please select a coach.', session()->getFlashdata('error'));
        self::assertSame($this->branch, (int) old('branch_id', 0));
    }

    public function testCoachScopeRejectsTamperedBranchCoachAndPlayerSelections(): void
    {
        $this->authorizeAsCoach();
        $options = $this->scheduling()->sessionFormOptions('session.create');
        $optionCoachIds = array_map('intval', array_column($options['coaches'], 'id'));
        self::assertSame([$this->coach], $optionCoachIds);

        $optionPlayerIds = array_map('intval', array_column($options['players'], 'id'));
        self::assertContains($this->player, $optionPlayerIds);

        $otherCoach = (int) $this->db->table('users')
            ->where('email', 'test.mcc.fitness@example.invalid')
            ->get()
            ->getRow('id');
        self::assertGreaterThan(0, $otherCoach);

        $branchTamper = $this->payload($this->window(10800));
        $branchTamper['branch_id'] = $this->otherBranch;
        $branchTamper['batch_id'] = $this->otherBatch;
        $branchTamper['program_id'] = $this->otherProgram;
        try {
            $this->scheduling()->create($branchTamper);
            self::fail('Coach accepted a branch outside the assigned scope.');
        } catch (RuntimeException $exception) {
            self::assertSame('Invalid branch.', $exception->getMessage());
        }

        $coachTamper = $this->payload($this->window(12600));
        $coachTamper['coach_user_id'] = $otherCoach;
        try {
            $this->scheduling()->create($coachTamper);
            self::fail('Coach accepted another Coach ID.');
        } catch (RuntimeException $exception) {
            self::assertSame('Coach is not authorized for branch.', $exception->getMessage());
        }

        $playerTamper = $this->payload($this->window(14400));
        $playerTamper['player_ids'] = [$this->player + 999999];
        try {
            $this->scheduling()->create($playerTamper);
            self::fail('Coach accepted an unassigned Player ID.');
        } catch (RuntimeException $exception) {
            self::assertSame('Player is not assigned to this Coach.', $exception->getMessage());
        }
    }

    public function testCompletedSessionRequiresSnapshotAndAttendanceAndIsIdempotent(): void
    {
        $id = $this->scheduling()->create($this->payload($this->window(0)));
        $now = gmdate('Y-m-d H:i:s');
        $this->db->table('sessions')->where('id', $id)->update([
            'starts_at' => gmdate('Y-m-d H:i:s', time() + 3600),
            'ends_at' => gmdate('Y-m-d H:i:s', time() + 5400),
        ]);

        try {
            $this->scheduling()->complete($id);
            self::fail('Completion without a snapshot was accepted.');
        } catch (RuntimeException $exception) {
            self::assertSame('Attendance roster snapshot required.', $exception->getMessage());
        }

        $this->db->table('attendance_roster_snapshots')->insert([
            'session_id' => $id,
            'branch_id' => $this->branch,
            'captured_at' => $now,
        ]);
        $snapshot = (int) $this->db->insertID();
        $this->db->table('attendance_roster_snapshot_members')->insert([
            'snapshot_id' => $snapshot,
            'player_id' => $this->player,
            'branch_id' => $this->branch,
        ]);
        $this->db->table('attendance')->insert([
            'session_id' => $id,
            'player_id' => $this->player,
            'branch_id' => $this->branch,
            'status' => 'PRESENT',
            'recorded_by' => $this->actor,
            'recorded_at' => $now,
            'version' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->scheduling()->complete($id, 'TEST_MCC completion');
        $this->scheduling()->complete($id, 'TEST_MCC duplicate completion');
        self::assertSame('completed', $this->db->table('sessions')->where('id', $id)->get()->getRow('status'));
        self::assertSame(1, $this->db->table('audit_logs')->where([
            'action' => 'session_completed',
            'entity_id' => (string) $id,
            'branch_id' => $this->branch,
        ])->countAllResults());

        $pastSession = $this->prepareCompletionSession(false);
        $this->scheduling()->complete($pastSession, 'TEST_MCC after-end completion');
        self::assertSame(
            'completed',
            $this->db->table('sessions')->where('id', $pastSession)->get()->getRow('status')
        );
    }

    private function prepareCompletionSession(bool $future): int
    {
        $id = $this->scheduling()->create($this->payload($this->window(3600)));
        $startOffset = $future ? 3600 : -3600;
        $endOffset = $future ? 5400 : -1800;
        $now = gmdate('Y-m-d H:i:s');

        $this->db->table('sessions')->where('id', $id)->update([
            'starts_at' => gmdate('Y-m-d H:i:s', time() + $startOffset),
            'ends_at' => gmdate('Y-m-d H:i:s', time() + $endOffset),
        ]);
        $this->db->table('attendance_roster_snapshots')->insert([
            'session_id' => $id,
            'branch_id' => $this->branch,
            'captured_at' => $now,
        ]);
        $snapshot = (int) $this->db->insertID();
        $this->db->table('attendance_roster_snapshot_members')->insert([
            'snapshot_id' => $snapshot,
            'player_id' => $this->player,
            'branch_id' => $this->branch,
        ]);
        $this->db->table('attendance')->insert([
            'session_id' => $id,
            'player_id' => $this->player,
            'branch_id' => $this->branch,
            'status' => 'PRESENT',
            'recorded_by' => $this->actor,
            'recorded_at' => $now,
            'version' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $id;
    }

    public function testCompletionRetainsIncompleteAndCancelledGuards(): void
    {
        $incomplete = $this->scheduling()->create($this->payload($this->window(7200)));
        $now = gmdate('Y-m-d H:i:s');
        $this->db->table('attendance_roster_snapshots')->insert([
            'session_id' => $incomplete,
            'branch_id' => $this->branch,
            'captured_at' => $now,
        ]);
        $snapshot = (int) $this->db->insertID();
        $this->db->table('attendance_roster_snapshot_members')->insert([
            'snapshot_id' => $snapshot,
            'player_id' => $this->player,
            'branch_id' => $this->branch,
        ]);

        try {
            $this->scheduling()->complete($incomplete);
            self::fail('Completion with incomplete attendance was accepted.');
        } catch (RuntimeException $exception) {
            self::assertSame('Attendance is incomplete.', $exception->getMessage());
        }

        $cancelled = $this->scheduling()->create($this->payload($this->window(10800)));
        $this->db->table('sessions')->where('id', $cancelled)->update(['status' => 'cancelled']);

        try {
            $this->scheduling()->complete($cancelled);
            self::fail('Completion of a cancelled session was accepted.');
        } catch (RuntimeException $exception) {
            self::assertSame('Cancelled sessions cannot be completed.', $exception->getMessage());
        }
    }

    public function testCreateBrowserRequestUsesSpecificValidationAndPreservesInput(): void
    {
        $this->authorize(['session.create', 'ground.book', 'player.view'], [$this->branch]);
        $window = $this->window(2520);
        $start = new DateTimeImmutable($window['local_start']);
        $end = new DateTimeImmutable($window['local_end']);
        $payload = [
            csrf_token() => csrf_hash(),
            'branch_id' => $this->branch,
            'program_id' => $this->program,
            'batch_id' => $this->batch,
            'session_type' => 'one_to_one',
            'coach_user_id' => $this->coach,
            'ground_id' => 999999,
            'status' => 'scheduled',
            'starts_at' => $start->format('d-m-Y'),
            'starts_time' => $start->format('H:i'),
            'ends_at' => $end->format('d-m-Y'),
            'ends_time' => $end->format('H:i'),
            'player_ids' => [$this->player],
        ];

        $response = $this->withSession(session()->get())->post('sessions', $payload);

        self::assertSame(302, $response->response()->getStatusCode());
        self::assertStringEndsWith('/sessions/new', (string) $response->response()->getHeaderLine('Location'));
        self::assertSame(
            'The requested record was not found.',
            session()->getFlashdata('error')
        );
        self::assertSame(999999, (int) old('ground_id', 0));
    }

    public function testCreateBrowserRequestPersistsAndReturnsToSessionDetails(): void
    {
        $window = $this->window(2700);
        $start = new DateTimeImmutable($window['local_start']);
        $end = new DateTimeImmutable($window['local_end']);
        $response = $this->withSession(session()->get())->post('sessions', [
            csrf_token() => csrf_hash(),
            'branch_id' => $this->branch,
            'program_id' => $this->program,
            'batch_id' => $this->batch,
            'session_type' => 'one_to_one',
            'coach_user_id' => $this->coach,
            'ground_id' => '',
            'status' => 'scheduled',
            'starts_at' => $start->format('d-m-Y'),
            'starts_time' => $start->format('H:i'),
            'ends_at' => $end->format('d-m-Y'),
            'ends_time' => $end->format('H:i'),
            'player_ids' => [$this->player],
        ]);

        self::assertSame(302, $response->response()->getStatusCode());
        $location = (string) $response->response()->getHeaderLine('Location');
        self::assertMatchesRegularExpression('#/sessions/\d+/page$#', $location);
        preg_match('#/sessions/(\d+)/page$#', $location, $matches);
        $sessionId = (int) ($matches[1] ?? 0);
        self::assertGreaterThan(0, $sessionId);
        self::assertSame(
            1,
            $this->db->table('audit_logs')->where([
                'action' => 'session_created',
                'entity_id' => (string) $sessionId,
            ])->countAllResults()
        );
    }

    public function testCreateBrowserRequestShowsAuthorizationReason(): void
    {
        $this->authorize(['session.create', 'player.view'], [$this->branch]);
        $window = $this->window(2760);
        $start = new DateTimeImmutable($window['local_start']);
        $end = $start->modify('+1 hour');
        $response = $this->withSession(session()->get())->post('sessions', [
            csrf_token() => csrf_hash(),
            'branch_id' => $this->otherBranch,
            'program_id' => $this->otherProgram,
            'batch_id' => $this->otherBatch,
            'session_type' => 'one_to_one',
            'coach_user_id' => $this->coach,
            'ground_id' => '',
            'status' => 'scheduled',
            'starts_at' => $start->format('d-m-Y'),
            'starts_time' => $start->format('H:i'),
            'ends_at' => $end->format('d-m-Y'),
            'ends_time' => $end->format('H:i'),
            'player_ids' => [$this->player],
        ]);

        self::assertSame(302, $response->response()->getStatusCode());
        self::assertSame(
            'You are not authorized to create a session for this branch.',
            session()->getFlashdata('error')
        );
    }

    public function testCreateBrowserRequestDeduplicatesRepeatedStudentIds(): void
    {
        $this->authorize(['session.create', 'player.view'], [$this->branch]);
        $window = $this->window(2880);
        $start = new DateTimeImmutable($window['local_start']);
        $end = new DateTimeImmutable($window['local_end']);
        $response = $this->withSession(session()->get())->post('sessions', [
            csrf_token() => csrf_hash(),
            'branch_id' => $this->branch,
            'program_id' => $this->program,
            'batch_id' => $this->batch,
            'session_type' => 'one_to_one',
            'coach_user_id' => $this->coach,
            'ground_id' => '',
            'status' => 'scheduled',
            'starts_at' => $start->format('d-m-Y'),
            'starts_time' => $start->format('H:i'),
            'ends_at' => $end->format('d-m-Y'),
            'ends_time' => $end->format('H:i'),
            'player_ids' => [$this->player, $this->player],
        ]);

        self::assertSame(302, $response->response()->getStatusCode());
        self::assertNull(session()->getFlashdata('error'));

        $location = (string) $response->response()->getHeaderLine('Location');
        self::assertMatchesRegularExpression('#/sessions/\\d+/page$#', $location);

        $path = parse_url($location, PHP_URL_PATH);
        self::assertIsString($path);
        preg_match('#/sessions/(\\d+)/page$#', $path, $matches);
        $sessionId = (int) ($matches[1] ?? 0);
        self::assertGreaterThan(0, $sessionId);
        self::assertSame(
            1,
            $this->db->table('session_players')
                ->where('session_id', $sessionId)
                ->where('player_id', $this->player)
                ->countAllResults()
        );
        self::assertSame(
            1,
            $this->db->table('session_players')
                ->where('session_id', $sessionId)
                ->countAllResults()
        );
    }

    public function testCreateBrowserRequestUsesSameDayEndTimeWhenLeaveDoesNotOverlap(): void
    {
        $this->authorize(['session.create', 'player.view'], [$this->branch]);
        $timezone = new DateTimeZone('Asia/Kolkata');
        $base = new DateTimeImmutable($this->window(3100)['local_start'], $timezone);
        $start = $base->setTime(22, 30);
        $end = $start->modify('+1 hour');
        $response = $this->withSession(session()->get())->post('sessions', [
            csrf_token() => csrf_hash(),
            'branch_id' => $this->branch,
            'program_id' => $this->program,
            'batch_id' => $this->batch,
            'session_type' => 'one_to_one',
            'coach_user_id' => $this->coach,
            'ground_id' => '',
            'status' => 'scheduled',
            'starts_at' => $start->format('d-m-Y'),
            'starts_time' => $start->format('H:i'),
            'ends_at' => $end->format('d-m-Y'),
            'ends_time' => $end->format('H:i'),
            'player_ids' => [$this->player],
        ]);

        self::assertSame(302, $response->response()->getStatusCode());
        self::assertStringContainsString('/sessions/', (string) $response->response()->getHeaderLine('Location'));
        self::assertNull(session()->getFlashdata('error'));
    }

    public function testAvailabilityEndpointUsesIndiaDateAndReturnsWindowsOrEmptyState(): void
    {
        $this->authorize(['session.create', 'coach.view'], [$this->branch]);
        $window = $this->window(0);
        $date = (new DateTimeImmutable($window['local_start']))->format('d-m-Y');
        $response = $this->withSession(session()->get())->get('sessions/availability?' . http_build_query([
            'coach_user_id' => $this->coach,
            'branch_id' => $this->branch,
            'date' => $date,
        ]));

        self::assertSame(200, $response->response()->getStatusCode());
        $json = json_decode($response->getJSON(), true);
        self::assertIsArray($json);
        self::assertNotEmpty($json['windows']);
        self::assertSame(site_url('coaches/' . $this->coach), $json['manage_url']);

        $empty = $this->withSession(session()->get())->get('sessions/availability?' . http_build_query([
            'coach_user_id' => $this->coach,
            'branch_id' => $this->branch,
            'date' => '15-02-2100',
        ]));
        self::assertSame(200, $empty->response()->getStatusCode());
        self::assertSame([], json_decode($empty->getJSON(), true)['windows']);
    }

    public function testCreateOutsideAvailabilityIsAllowedWhenLeaveDoesNotOverlap(): void
    {
        $this->authorize(['session.create', 'player.view'], [$this->branch]);
        $window = $this->window(3000);
        $date = substr($window['local_start'], 0, 10);
        $this->db->table('coach_availability')->insert([
            'coach_user_id' => $this->coach,
            'branch_id' => $this->branch,
            'weekday' => null,
            'available_date' => $date,
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
            'reason' => 'TEST_MCC availability message fixture',
            'created_by' => $this->actor,
            'created_at' => gmdate('Y-m-d H:i:s'),
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'cancelled_at' => null,
        ]);
        $start = new DateTimeImmutable($date . ' 11:00:00', new DateTimeZone('Asia/Kolkata'));
        $end = $start->modify('+1 hour');
        $id = $this->scheduling()->create([
            'branch_id' => $this->branch,
            'program_id' => $this->program,
            'batch_id' => $this->batch,
            'session_type' => 'one_to_one',
            'coach_user_id' => $this->coach,
            'ground_id' => '',
            'status' => 'scheduled',
            'starts_at' => $start->format('d-m-Y H:i:s'),
            'ends_at' => $end->format('d-m-Y H:i:s'),
            'player_ids' => [$this->player],
        ]);
        self::assertGreaterThan(0, $id);
    }
    public function testCreationRequiresPermissionAndRejectsCrossBranchOrIneligibleRoster(): void
    {
        $this->authorize(['player.view'], [$this->branch]);
        try {
            $this->scheduling()->create($this->payload($this->window(120)));
            self::fail('Unauthorized creation accepted.');
        } catch (RuntimeException $e) {
            self::assertSame('Forbidden', $e->getMessage());
        }
        $this->authorize(['session.create'], [$this->branch]);
        $cross = $this->window(240);
        try {
        $this->scheduling()->create(['branch_id' => $this->otherBranch, 'program_id' => $this->otherProgram, 'batch_id' => $this->otherBatch, 'session_type' => 'one_to_one', 'coach_user_id' => $this->coach, 'starts_at' => $cross['local_start'],
    'ends_at' => $cross['local_end'], 'player_ids' => [$this->player]]);
            self::fail('Cross-branch batch accepted.');
        } catch (RuntimeException $e) {
            self::assertSame('Forbidden', $e->getMessage());
        }
        try {
            $this->scheduling()->create(array_merge($this->payload($this->window(360)), ['player_ids' => [$this->player + 999999]]));
            self::fail('Ineligible player accepted.');
        } catch (RuntimeException $e) {
            self::assertSame('Player is not eligible.', $e->getMessage());
        }

        $crossBatch = $this->payload($this->window(420));
        $crossBatch['batch_id'] = $this->otherBatch;
        try {
            $this->scheduling()->create($crossBatch);
            self::fail('A Player outside the selected Batch was accepted.');
        } catch (RuntimeException $e) {
            self::assertSame('Player is not eligible.', $e->getMessage());
        }
    }
    public function testCancellationAndReschedulePreserveOriginalAndRosterHistory(): void
    {
        $id = $this->scheduling()->create($this->payload($this->window(480)));
        $replacementWindow = $this->window(540);
        $replacement = $this->scheduling()->reschedule($id, $replacementWindow['local_start'], $replacementWindow['local_end'], 'TEST_MCC rescheduled');
        self::assertSame('cancelled', $this->db->table('sessions')->where('id', $id)->get()->getRow('status'));
        self::assertSame($id, (int) $this->db->table('sessions')->where('id', $replacement)->get()->getRow('rescheduled_from_id'));
        self::assertSame(1, $this->db->table('session_players')->where(['session_id' => $replacement, 'player_id' => $this->player])->countAllResults());
        self::assertSame(1, $this->db->table('audit_logs')->where(['action' => 'session_rescheduled',
    'entity_id' => (string) $replacement])->countAllResults());
        $cancelled = $this->scheduling()->create($this->payload($this->window(600)));
        $this->scheduling()->cancel($cancelled, 'TEST_MCC cancelled');
        try {
            $this->scheduling()->cancel($cancelled, 'again');
            self::fail('Cancelled session reused.');
        } catch (RuntimeException $e) {
            self::assertSame('Session is not active.', $e->getMessage());
        }
    }

    public function testControlledSessionStatusRejectsForgedValues(): void
    {
        $this->authorize(['session.create'], [$this->branch]);

        try {
            $this->scheduling()->create(array_merge(
                $this->payload($this->window(720)),
                ['status' => 'cancelled']
            ));
            self::fail('A non-schedulable status was accepted during creation.');
        } catch (RuntimeException $exception) {
            self::assertSame('Invalid session status.', $exception->getMessage());
        }
    }

    public function testAuthorizedSessionHistoryShowsCancellationAuditDetailsOnly(): void
    {
        $id = $this->scheduling()->create($this->payload($this->window(1980)));
        $this->scheduling()->cancel($id, 'TEST_MCC audit cancellation reason');
        $this->db->table('audit_logs')->insert([
            'actor_user_id' => $this->actor,
            'action' => 'player_updated',
            'entity_type' => 'player',
            'entity_id' => (string) $id,
            'branch_id' => $this->branch,
            'before_summary' => json_encode(['status' => 'old']),
            'after_summary' => json_encode(['status' => 'new']),
            'reason' => 'Unrelated event',
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);

        $detail = $this->scheduling()->find($id);
        self::assertNotEmpty($detail['audit_history']);
        $actions = array_column($detail['audit_history'], 'action');
        self::assertContains('session_cancelled', $actions);
        self::assertNotContains('player_updated', $actions);
        $cancelled = array_values(array_filter(
            $detail['audit_history'],
            static fn(array $event): bool => $event['action'] === 'session_cancelled'
        ));
        self::assertSame('TEST_MCC audit cancellation reason', $cancelled[0]['reason']);
        self::assertSame('cancelled', $cancelled[0]['after_metadata']['status']);
        self::assertSame($this->actor, (int) $cancelled[0]['actor_user_id']);
        self::assertNotEmpty($cancelled[0]['created_at']);
        self::assertNotEmpty($cancelled[0]['cancelled_at']);
        self::assertSame('TEST_MCC audit cancellation reason', $detail['history'][0]['cancellation_reason']);
    }

    public function testAuthorizedSessionHistoryShowsRescheduleReplacementLinkage(): void
    {
        $id = $this->scheduling()->create($this->payload($this->window(2160)));
        $replacementWindow = $this->window(2220);
        $replacement = $this->scheduling()->reschedule(
            $id,
            $replacementWindow['local_start'],
            $replacementWindow['local_end'],
            'TEST_MCC audit reschedule reason'
        );

        $detail = $this->scheduling()->find($id);
        $events = array_values(array_filter(
            $detail['audit_history'],
            static fn(array $event): bool => $event['action'] === 'session_rescheduled'
        ));
        self::assertCount(1, $events);
        self::assertSame($id, (int) $events[0]['before_metadata']['original_session_id']);
        self::assertSame($replacement, (int) $events[0]['after_metadata']['replacement_session_id']);
        self::assertSame('TEST_MCC audit reschedule reason', $events[0]['reason']);
        self::assertSame($replacement, (int) $detail['history'][1]['id']);
        self::assertSame($id, (int) $detail['history'][1]['rescheduled_from_id']);
    }

    public function testSessionHistoryProjectsAuthorizedReferenceNamesWithoutChangingAuditValues(): void
    {
        $temporaryProgramId = 0;
        $programRow = $this->db->table('programs')
            ->where('id', $this->program)
            ->get()
            ->getRowArray();
        if (! is_array($programRow)) {
            $now = gmdate('Y-m-d H:i:s');
            $this->db->table('programs')->insert([
                'name' => $this->fixtureMarker . '_PROGRAM',
                'created_by' => $this->actor,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $temporaryProgramId = (int) $this->db->insertID();
            $this->db->table('batches')
                ->where('id', $this->batch)
                ->update(['program_id' => $temporaryProgramId]);
            $this->program = $temporaryProgramId;
        }

        $groundName = $this->fixtureMarker . '_GROUND';
        $now = gmdate('Y-m-d H:i:s');
        $this->db->table('grounds')->insert([
            'branch_id' => $this->branch,
            'name' => $groundName,
            'ground_type' => 'outdoor_ground',
            'description' => null,
            'is_active' => 1,
            'created_by' => $this->actor,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $groundId = (int) $this->db->insertID();
        $sessionId = $this->scheduling()->create($this->payload($this->window(2280)));

        try {
            $this->db->table('audit_logs')->insert([
                'actor_user_id' => $this->actor,
                'action' => 'session_updated',
                'entity_type' => 'session',
                'entity_id' => (string) $sessionId,
                'branch_id' => $this->branch,
                'before_summary' => json_encode([
                    'batch_id' => $this->batch,
                    'coach_user_id' => $this->coach,
                    'branch_id' => $this->branch,
                    'program_id' => $this->program,
                    'ground_id' => $groundId,
                    'player_ids' => [$this->player],
                    'secret_token' => 'must-not-render',
                ], JSON_THROW_ON_ERROR),
                'after_summary' => json_encode([
                    'batch_id' => $this->otherBatch,
                    'coach_user_id' => 999999,
                    'branch_id' => $this->otherBranch,
                    'program_id' => $this->program,
                    'ground_id' => 999999,
                    'player_ids' => [$this->player, 999999],
                ], JSON_THROW_ON_ERROR),
                'reason' => 'TEST_MCC history projection',
                'created_at' => $now,
            ]);

            $detail = $this->scheduling()->find($sessionId);
            $events = array_values(array_filter(
                $detail['audit_history'],
                static fn (array $event): bool => $event['reason'] === 'TEST_MCC history projection',
            ));
            self::assertCount(1, $events);
            $event = $events[0];
            $before = $event['before_metadata'];
            $after = $event['after_metadata'];
            $beforeDisplay = $event['before_display_metadata'];
            $afterDisplay = $event['after_display_metadata'];

            self::assertSame($this->batch, (int) $before['batch_id']);
            self::assertSame('TEST_MCC_VNS_U15', $beforeDisplay['batch_id']);
            self::assertSame($this->coach, (int) $before['coach_user_id']);
            self::assertSame($this->otherBatch, (int) $after['batch_id']);
            self::assertSame(999999, (int) $after['coach_user_id']);
            self::assertSame(
                (string) $this->db->table('users')->where('id', $this->coach)->get()->getRow('display_name'),
                $beforeDisplay['coach_user_id'],
            );
            self::assertSame(
                (string) $this->db->table('branches')->where('id', $this->branch)->get()->getRow('name'),
                $beforeDisplay['branch_id'],
            );
            self::assertSame(
                (string) $this->db->table('programs')->where('id', $this->program)->get()->getRow('name'),
                $beforeDisplay['program_id'],
            );
            self::assertSame($groundName, $beforeDisplay['ground_id']);
            self::assertSame(
                (string) $this->db->table('persons p')
                    ->join('players pl', 'pl.person_id = p.id')
                    ->where('pl.id', $this->player)
                    ->get()
                    ->getRow('full_name'),
                $beforeDisplay['player_ids'][0],
            );
            self::assertSame('Unknown batch', $afterDisplay['batch_id']);
            self::assertSame('Unknown coach', $afterDisplay['coach_user_id']);
            self::assertSame('Unknown branch', $afterDisplay['branch_id']);
            self::assertSame('Unknown ground', $afterDisplay['ground_id']);
            self::assertSame('Unknown player', $afterDisplay['player_ids'][1]);
            self::assertSame('must-not-render', $before['secret_token']);
            self::assertSame($now, $event['created_at']);
            $historyIds = array_map('intval', array_column($detail['audit_history'], 'id'));
            $sortedHistoryIds = $historyIds;
            sort($sortedHistoryIds);
            self::assertSame(
                $sortedHistoryIds,
                $historyIds,
            );
        } finally {
            $this->db->table('grounds')->where('id', $groundId)->delete();
            if ($temporaryProgramId > 0) {
                $this->db->table('batches')
                    ->where('id', $this->batch)
                    ->update(['program_id' => null]);
                $this->db->table('programs')->where('id', $temporaryProgramId)->delete();
            }
        }
    }

    public function testSessionHistoryIsDeniedAcrossBranches(): void
    {
        $id = $this->scheduling()->create($this->payload($this->window(2340)));
        $this->scheduling()->cancel($id, 'TEST_MCC cross branch history');
        $this->authorize(['session.update'], [$this->otherBranch]);

        try {
            $this->scheduling()->find($id);
            self::fail('Cross-branch session history was readable.');
        } catch (RuntimeException $exception) {
            self::assertSame('Forbidden', $exception->getMessage());
        }

    }

    public function testConflictAvailabilityAndInvalidWindowAreRejectedWithoutPartialWrites(): void
    {
        $id = $this->scheduling()->create($this->payload($this->window(720)));
        try {
            $this->scheduling()->create($this->payload($this->window(750)));
            self::fail('Coach conflict accepted.');
        } catch (RuntimeException $e) {
            self::assertSame('Scheduling conflict.', $e->getMessage());
        }
        $unavailable = $this->window(840);
        $this->db->table('coach_unavailability')->insert(['coach_user_id' => $this->coach, 'branch_id' => $this->branch,
    'unavailable_from' => $unavailable['utc_start'], 'unavailable_to' => $unavailable['utc_end'], 'reason' => 'TEST_MCC leave',
    'created_at' => gmdate('Y-m-d H:i:s')]);
        try {
            $this->scheduling()->reschedule($id, $unavailable['local_start'], $unavailable['local_end'], 'TEST_MCC unavailable');
            self::fail('Unavailable reschedule accepted.');
        } catch (RuntimeException $e) {
            self::assertSame('Coach unavailable.', $e->getMessage());
        }
        $invalid = $this->window(960);
        try {
            $this->scheduling()->create($this->payload(['local_start' => $invalid['local_end'], 'local_end' => $invalid['local_start']]));
            self::fail('Invalid window accepted.');
        } catch (RuntimeException $e) {
            self::assertSame('Invalid session window.', $e->getMessage());
        }
        self::assertSame('scheduled', $this->db->table('sessions')->where('id', $id)->get()->getRow('status'));
    }

    public function testCoachAvailabilityIsEnforcedForCreateEditAndReschedule(): void
    {
        $available = $this->window(1200);
        $id = $this->scheduling()->create($this->payload($available));
        self::assertGreaterThan(0, $id);

        $approvedLeave = $this->window(1320);
        $this->db->table('coach_unavailability')->insert([
            'coach_user_id' => $this->coach,
            'branch_id' => $this->branch,
            'unavailable_from' => $approvedLeave['utc_start'],
            'unavailable_to' => $approvedLeave['utc_end'],
            'reason' => 'TEST_MCC approved leave',
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);

        try {
            $this->scheduling()->create($this->payload($approvedLeave));
            self::fail('Creation during approved leave accepted.');
        } catch (RuntimeException $e) {
            self::assertSame('Coach unavailable.', $e->getMessage());
        }

        $current = $this->db->table('sessions')->where('id', $id)->get()->getRowArray();
        try {
            $this->scheduling()->update($id, array_merge(
                $this->payload($approvedLeave),
                ['expected_updated_at' => $current['updated_at']]
            ));
            self::fail('Edit during approved leave accepted.');
        } catch (RuntimeException $e) {
            self::assertSame('Coach unavailable.', $e->getMessage());
        }

        try {
            $this->scheduling()->reschedule(
                $id,
                $approvedLeave['local_start'],
                $approvedLeave['local_end'],
                'TEST_MCC leave conflict'
            );
            self::fail('Reschedule during approved leave accepted.');
        } catch (RuntimeException $e) {
            self::assertSame('Coach unavailable.', $e->getMessage());
        }

        self::assertSame(
            'scheduled',
            $this->db->table('sessions')->where('id', $id)->get()->getRow('status')
        );
        self::assertSame(
            $available['utc_start'],
            $this->db->table('sessions')->where('id', $id)->get()->getRow('starts_at')
        );
    }

    public function testSessionCanBeEditedBeforeAttendanceSnapshotCapture(): void
    {
        $original = $this->window(1440);
        $id = $this->scheduling()->create($this->payload($original));
        $current = $this->db->table('sessions')->where('id', $id)->get()->getRowArray();
        $updated = $this->window(1500);

        $this->scheduling()->update($id, array_merge(
            $this->payload($updated),
            [
                'session_type' => 'group',
                'expected_updated_at' => $current['updated_at'],
            ]
        ));

        self::assertSame(
            $updated['utc_start'],
            $this->db->table('sessions')->where('id', $id)->get()->getRow('starts_at')
        );
        self::assertSame('group', $this->db->table('sessions')->where('id', $id)->get()->getRow('session_type'));
    }

    public function testEditAcceptsIndiaLocalDateAndTimeAndReturnsLocalDisplay(): void
    {
        $original = $this->window(1740);
        $id = $this->scheduling()->create($this->payload($original));
        $current = $this->db->table('sessions')->where('id', $id)->get()->getRowArray();
        $updated = $this->window(1800);
        $localStart = str_replace('T', ' ', substr($updated['local_start'], 0, 19));
        $localEnd = str_replace('T', ' ', substr($updated['local_end'], 0, 19));

        $this->scheduling()->update($id, array_merge(
            $this->payload($updated),
            [
                'starts_at' => $localStart,
                'ends_at' => $localEnd,
                'expected_updated_at' => $current['updated_at'],
            ]
        ));

        self::assertSame(
            $updated['utc_start'],
            $this->db->table('sessions')->where('id', $id)->get()->getRow('starts_at')
        );
        self::assertSame($updated['local_start'], $this->scheduling()->find($id)['starts_at_local']);
    }

    public function testEditAcceptsDatepickerMinutePrecisionPayload(): void
    {
        $original = $this->window(1860);
        $id = $this->scheduling()->create($this->payload($original));
        $current = $this->db->table('sessions')->where('id', $id)->get()->getRowArray();
        $updated = $this->window(1920);

        $this->scheduling()->update($id, array_merge(
            $this->payload($updated),
            [
                'starts_at' => str_replace(' ', 'T', substr($updated['local_start'], 0, 16)),
                'ends_at' => str_replace(' ', 'T', substr($updated['local_end'], 0, 16)),
                'expected_updated_at' => $current['updated_at'],
            ]
        ));

        self::assertSame(
            $updated['utc_start'],
            $this->db->table('sessions')->where('id', $id)->get()->getRow('starts_at')
        );
        self::assertSame($updated['local_start'], $this->scheduling()->find($id)['starts_at_local']);
    }

    public function testSessionEditIsRejectedAfterAttendanceSnapshotCapture(): void
    {
        $original = $this->window(1620);
        $id = $this->scheduling()->create($this->payload($original));
        $db = $this->db;
        $db->table('attendance_roster_snapshots')->insert([
            'session_id' => $id,
            'branch_id' => $this->branch,
            'captured_at' => gmdate('Y-m-d H:i:s'),
        ]);
        $before = $db->table('sessions')->where('id', $id)->get()->getRowArray();
        $updated = $this->window(1680);

        try {
            $this->scheduling()->update($id, array_merge(
                $this->payload($updated),
                ['expected_updated_at' => $before['updated_at']]
            ));
            self::fail('Session with an Attendance snapshot was edited.');
        } catch (RuntimeException $exception) {
            self::assertSame(
                'Session is locked because Attendance has started.',
                $exception->getMessage()
            );
        }

        $after = $db->table('sessions')->where('id', $id)->get()->getRowArray();
        self::assertSame($before['batch_id'], $after['batch_id']);
        self::assertSame($before['starts_at'], $after['starts_at']);
        self::assertSame($before['coach_user_id'], $after['coach_user_id']);
    }

    public function testSessionBranchReadOptionsReconcileStaleContextWithRbac(): void
    {
        $this->authorize(['session.create'], [$this->branch]);
        session()->set('branchIds', [$this->otherBranch]);
        \Config\Services::resetSingle('authorization');

        $options = $this->scheduling()->sessionFormOptions('session.create');
        $batchIds = array_map('intval', array_column($options['batches'], 'id'));

        self::assertContains($this->batch, $batchIds);
        self::assertContains($this->otherBatch, $batchIds);

        $this->authorize([], [$this->branch]);
        self::assertSame([], $this->scheduling()->sessionFormOptions('session.create')['batches']);
    }

    public function testSessionFormOptionsExposeAuthorizedActivePlayersAsPlayerIds(): void
    {
        $this->authorize(['session.create'], [$this->branch]);

        $options = $this->scheduling()->sessionFormOptions('session.create', $this->branch);
        $playerIds = array_map('intval', array_column($options['players'], 'id'));

        self::assertNotEmpty($playerIds);
        self::assertContains($this->player, $playerIds);
        self::assertSame(
            $this->player,
            (int) $this->db->table('players')
                ->where('id', $this->player)
                ->where('branch_id', $this->branch)
                ->where('status', 'active')
                ->get()
                ->getRow('id')
        );
    }

    public function testIndiaLocalDatetimePersistsUtcAndBranchIsolationProtectsDetail(): void
    {
        $timezone = $this->window(1080);
        $localInput = $this->payload([
            'local_start' => str_replace('T', ' ', substr($timezone['local_start'], 0, 19)),
            'local_end' => str_replace('T', ' ', substr($timezone['local_end'], 0, 19)),
        ]);
        $created = $this->scheduling()->create($localInput);
        $stored = $this->db->table('sessions')->where('id', $created)->get()->getRowArray();
        self::assertSame($timezone['utc_start'], $stored['starts_at']);
        self::assertSame($timezone['local_start'], $this->scheduling()->find($created)['starts_at_local']);
        $seeded = $created;
        $this->authorize(['session.update'], [$this->otherBranch]);
        try {
            $this->scheduling()->find($seeded);
            self::fail('Cross-branch detail accepted.');
        } catch (RuntimeException $e) {
            self::assertSame('Forbidden', $e->getMessage());
        }
    }

    public function testCoachViewCanReadSessionDetailWithoutWriteActions(): void
    {
        $this->authorize(['session.create', 'session.update', 'player.view'], [$this->branch]);
        $sessionId = $this->scheduling()->create($this->payload($this->window(0)));
        $this->authorize(['coach.view'], [$this->branch]);

        $response = $this->withSession(session()->get())->get('sessions/' . $sessionId . '/page');
        $body = $response->getBody();

        self::assertSame(200, $response->response()->getStatusCode());
        self::assertStringContainsString('Session details', $body);
        self::assertStringContainsString('Branch', $body);
        self::assertStringContainsString('Program', $body);
        self::assertStringContainsString('Batch', $body);
        self::assertStringContainsString('Coach', $body);
        self::assertStringContainsString('Ground', $body);
        self::assertStringNotContainsString('data-session-action="edit"', $body);
        self::assertStringNotContainsString('data-session-feedback-open', $body);
        self::assertStringNotContainsString('/cancel"', $body);
        self::assertStringContainsString('mcc-scheduling-detail', $body);
        self::assertStringContainsString('mcc-session-information', $body);
    }

    public function testSessionDetailRequiresUpdateOrBranchScopedCoachView(): void
    {
        $sessionId = (int) $this->db->table('sessions')
            ->where('branch_id', $this->branch)
            ->orderBy('id', 'DESC')
            ->get()
            ->getRow('id');
        self::assertGreaterThan(0, $sessionId);
        $this->authorize(['player.view'], [$this->branch]);

        try {
            $this->scheduling()->find($sessionId);
            self::fail('User without session or Coach view access viewed the session.');
        } catch (RuntimeException $exception) {
            self::assertSame('Forbidden', $exception->getMessage());
        }

        $response = $this->withSession(session()->get())->get('sessions/' . $sessionId . '/page');
        self::assertSame(403, $response->response()->getStatusCode());
        self::assertStringNotContainsString('Session details', $response->getBody());
    }

    public function testSessionDateFiltersAcceptBothFormatsAndRejectInvalidCalendarDates(): void
    {
        $this->authorize(['session.create', 'session.update'], [$this->branch]);

        self::assertIsArray($this->scheduling()->sessionPage(['date' => '2026-09-05']));
        self::assertIsArray($this->scheduling()->sessionPage(['date' => '05-09-2026']));

        try {
            $this->scheduling()->sessionPage(['date' => '31-02-2026']);
            self::fail('Invalid calendar date was accepted.');
        } catch (RuntimeException $exception) {
            self::assertSame('Invalid session filter.', $exception->getMessage());
        }

        $invalid = $this->window(1800);
        try {
            $this->scheduling()->create([
                'batch_id' => $this->batch,
                'session_type' => 'one_to_one',
                'coach_user_id' => $this->coach,
                'starts_at' => '31-02-2100T10:00:00+05:30',
                'ends_at' => $invalid['local_end'],
                'player_ids' => [$this->player],
            ]);
            self::fail('Invalid session calendar date was accepted.');
        } catch (RuntimeException $exception) {
            self::assertSame('Invalid calendar date.', $exception->getMessage());
        }
    }

    public function testCanonicalSchedulingPagesUseSharedLayoutAndNoDuplicatePostRoutes(): void
    {
        $this->authorize(['session.update', 'session.create'], [$this->branch]);
        $list = $this->withSession(session()->get())->get('sessions');
        self::assertSame(200, $list->response()->getStatusCode());
        self::assertStringContainsString('mcc-card', $list->getBody());
        self::assertStringContainsString('mcc-table', $list->getBody());
        self::assertStringContainsString('mcc-breadcrumbs', $list->getBody());
        self::assertStringContainsString('mcc-footer', $list->getBody());
        self::assertStringContainsString('Create session', $list->getBody());
        $form = $this->withSession(session()->get())->get('sessions/new');
        self::assertSame(200, $form->response()->getStatusCode());
        self::assertStringContainsString('name="csrf_test_name"', $form->getBody());
        self::assertStringContainsString('class="mcc-session-create-form"', $form->getBody());
        self::assertStringContainsString('mcc-form-actions', $form->getBody());
        self::assertStringNotContainsString('form="session-create-form"', $form->getBody());
        self::assertStringContainsString('type="submit">', $form->getBody());
        $routes = file_get_contents(APPPATH . 'Modules/Scheduling/Config/Routes.php');
        self::assertSame(1, substr_count($routes, "Browser::create',"));
        self::assertSame(1, substr_count($routes, "Browser::cancel/"));
        self::assertSame(1, substr_count($routes, "Browser::reschedule/"));
        self::assertStringNotContainsString('Sessions::', $routes);
    }

    public function testSessionListProjectsNamesCountsAndFiltersWithinAuthorizedBranches(): void
    {
        $this->authorize(['session.create', 'session.update'], [$this->branch]);

        $withPlayer = $this->scheduling()->create($this->payload($this->window(2520)));
        $withoutPlayersPayload = $this->payload($this->window(2640));
        unset($withoutPlayersPayload['player_ids']);
        $withoutPlayers = $this->scheduling()->create($withoutPlayersPayload);

        $page = $this->scheduling()->sessionPage([
            'status' => 'scheduled',
            'batch_id' => (string) $this->batch,
        ]);
        self::assertSame(1, $this->scheduling()->sessionPage(['page' => 0])['page']);
        self::assertSame(1, $this->scheduling()->sessionPage(['page' => -1])['page']);
        $excessivePage = $this->scheduling()->sessionPage(['page' => 999999]);
        self::assertSame($excessivePage['pages'], $excessivePage['page']);
        $rows = [];
        foreach ($page['rows'] as $row) {
            $rows[(int) $row['id']] = $row;
        }

        self::assertArrayHasKey($withPlayer, $rows);
        self::assertArrayHasKey($withoutPlayers, $rows);
        self::assertSame(1, (int) $rows[$withPlayer]['selected_player_count']);
        self::assertSame(0, (int) $rows[$withoutPlayers]['selected_player_count']);
        self::assertSame(
            (string) $this->db->table('programs')->where('id', $this->program)->get()->getRow('name'),
            (string) $rows[$withPlayer]['program_name']
        );
        self::assertSame(
            (string) $this->db->table('users')->where('id', $this->coach)->get()->getRow('display_name'),
            (string) $rows[$withPlayer]['coach_name']
        );
        self::assertSame((string) $this->batch, (string) $rows[$withPlayer]['batch_id']);
        self::assertSame($this->branch, (int) $rows[$withPlayer]['branch_id']);

        $this->authorize([], [$this->branch]);
        $deniedPage = $this->scheduling()->sessionPage([]);
        self::assertSame(0, $deniedPage['total']);
        self::assertSame([], $deniedPage['rows']);
    }

    private function scheduling(): SchedulingService
    {
        return service('scheduling', false);
    }
    private function payload(array $window): array
    {
        return ['branch_id' => $this->branch, 'program_id' => $this->program, 'batch_id' => $this->batch, 'coach_user_id' => $this->coach, 'session_type' => 'one_to_one', 'starts_at' => $window['local_start'], 'ends_at' => $window['local_end'],
    'player_ids' => [$this->player]];
    }
    /** @return array{local_start:string,local_end:string,utc_start:string,utc_end:string} */
    private function window(int $offset): array
    {
        $dayOffset = intdiv($offset, 1440);
        $minuteOffset = $offset % 1440;
        $start = (new DateTimeImmutable('2100-01-01 18:30:00', new DateTimeZone('UTC')))
            ->modify('+' . ($this->slot + $dayOffset) . ' days')
            ->modify('+' . $minuteOffset . ' minutes');
        $end = $start->modify('+1 hour');
        $local = new DateTimeZone('+05:30');
        return ['local_start' => $start->setTimezone($local)->format('Y-m-d H:i:sP'), 'local_end' => $end->setTimezone($local)->format('Y-m-d H:i:sP'),
    'utc_start' => $start->format('Y-m-d H:i:s'), 'utc_end' => $end->format('Y-m-d H:i:s')];
    }
    private function ensureCoachAvailabilityFixture(): void
    {
        if (! $this->db->tableExists('coach_availability')) {
            return;
        }

        $fixtureOffsets = [
            0, 120, 240, 360, 480, 540, 600, 720, 750, 840, 960, 1080,
            1200, 1320, 1440, 1500, 1620, 1680, 1800, 1980, 2160, 2220, 2340,
        ];
        $dates = [];
        foreach ($fixtureOffsets as $offset) {
            $dates[substr($this->window($offset)['local_start'], 0, 10)] = true;
        }

        foreach (array_keys($dates) as $date) {
            $exists = $this->db->table('coach_availability')
                ->where([
                    'coach_user_id' => $this->coach,
                    'branch_id' => $this->branch,
                    'weekday' => null,
                    'available_date' => $date,
                    'start_time' => '00:00:00',
                    'end_time' => '23:59:59',
                    'cancelled_at' => null,
                ])
                ->countAllResults();
            if ($exists > 0) {
                continue;
            }

            $this->db->table('coach_availability')->insert([
                'coach_user_id' => $this->coach,
                'branch_id' => $this->branch,
                'weekday' => null,
                'available_date' => $date,
                'start_time' => '00:00:00',
                'end_time' => '23:59:59',
                'reason' => 'TEST_MCC scheduling availability fixture',
                'created_by' => $this->actor,
                'created_at' => gmdate('Y-m-d H:i:s'),
                'updated_at' => gmdate('Y-m-d H:i:s'),
                'cancelled_at' => null,
            ]);
        }
    }
    private function authorize(array $permissions, array $branches): void
    {
        session()->set(['isLoggedIn' => true, 'accountEnabled' => true, 'user' => ['id' => $this->actor], 'session_version' => 1,
    'permissions' => $permissions, 'authorizedBranches' => array_map('strval', $branches)]);
        \Config\Services::resetSingle('authorization');
    }

    private function authorizeAsCoach(): void
    {
        $sessionVersion = (int) $this->db->table('users')
            ->where('id', $this->coach)
            ->get()
            ->getRow('session_version');
        session()->set([
            'isLoggedIn' => true,
            'accountEnabled' => true,
            'user' => ['id' => $this->coach],
            'session_version' => $sessionVersion,
            'roles' => ['Coach'],
            'permissions' => ['session.create', 'session.update', 'player.view'],
            'authorizedBranches' => [(string) $this->branch],
        ]);
        \Config\Services::resetSingle('authorization');
    }

    private function ageGroupFixture(string $name): int
    {
        $existing = $this->db->table('admission_age_groups')
            ->where(['name' => $name, 'branch_id' => null])
            ->get()
            ->getRow('id');
        if (is_numeric($existing) && (int) $existing > 0) {
            $this->db->table('admission_age_groups')
                ->where('id', (int) $existing)
                ->update(['is_active' => 1]);

            return (int) $existing;
        }

        $now = gmdate('Y-m-d H:i:s');
        $this->db->table('admission_age_groups')->insert([
            'name' => $name,
            'branch_id' => null,
            'is_active' => 1,
            'display_order' => 0,
            'created_by' => $this->actor,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) $this->db->insertID();
    }

    private function attachAgeGroupToPlayer(int $playerId, int $ageGroupId): void
    {
        $source = $this->db->table('admissions')
            ->where('branch_id', $this->branch)
            ->orderBy('id', 'ASC')
            ->get()
            ->getRowArray();
        self::assertIsArray($source);

        unset($source['id']);
        $source['lead_id'] = null;
        $source['admission_number'] = 'TEST-MCC-' . strtoupper(substr($this->fixtureMarker, -10));
        $source['applicant_email'] = strtolower($this->fixtureMarker) . '@example.invalid';
        $source['converted_player_id'] = null;
        $source['age_group_id'] = $ageGroupId;
        if (array_key_exists('coach_user_id', $source)) {
            $source['coach_user_id'] = $this->coach;
        }
        if (array_key_exists('personal_coach_user_id', $source)) {
            $source['personal_coach_user_id'] = $this->coach;
        }
        $source['created_at'] = gmdate('Y-m-d H:i:s');
        $source['updated_at'] = $source['created_at'];
        $this->db->table('admissions')->insert($source);
        $admissionId = (int) $this->db->insertID();
        self::assertGreaterThan(0, $admissionId);

        $this->db->table('players')
            ->where('id', $playerId)
            ->update(['admission_id' => $admissionId]);
    }

    private function createEligiblePlayerFixture(): int
    {
        $now = gmdate('Y-m-d H:i:s');
        $this->db->table('persons')->insert([
            'full_name' => $this->fixtureMarker . '_PLAYER',
            'email' => strtolower($this->fixtureMarker) . '@example.invalid',
            'phone' => '900' . random_int(1000000, 9999999),
            'date_of_birth' => '2012-01-15',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $personId = (int) $this->db->insertID();
        $this->db->table('players')->insert([
            'person_id' => $personId,
            'lead_id' => null,
            'admission_id' => null,
            'branch_id' => $this->branch,
            'status' => 'active',
            'profile_revision' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $playerId = (int) $this->db->insertID();
        $this->db->table('player_branch_history')->insert([
            'player_id' => $playerId,
            'branch_id' => $this->branch,
            'effective_from' => $now,
            'created_at' => $now,
        ]);
        $this->db->table('player_batch_assignments')->insert([
            'player_id' => $playerId,
            'branch_id' => $this->branch,
            'batch_id' => $this->batch,
            'assigned_by' => $this->actor,
            'effective_from' => $now,
            'effective_to' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->db->table('player_coach_assignments')->insert([
            'player_id' => $playerId,
            'branch_id' => $this->branch,
            'coach_user_id' => $this->coach,
            'assigned_by' => $this->actor,
            'effective_from' => $now,
            'effective_to' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $playerId;
    }
}
