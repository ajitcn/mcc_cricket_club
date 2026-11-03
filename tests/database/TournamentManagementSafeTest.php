<?php

declare(strict_types=1);

namespace Tests\Database;

use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;

/** Insert-only Tournament coverage for the approved persistent test database. */
final class TournamentManagementSafeTest extends CIUnitTestCase
{
    protected $DBGroup = 'tests';

    private object $database;
    private int $branch;
    private int $actor;
    private int $player;

    protected function setUp(): void
    {
        parent::setUp();

        $this->database = Database::connect('tests');
        self::assertSame('mcc_cricket_club_test', strtolower((string) $this->database->getDatabase()));
        self::assertSame(
            strtolower((string) Database::connect('default')->getDatabase()),
            strtolower((string) $this->database->getDatabase())
        );

        $this->branch = (int) $this->database->table('branches')->where('code', 'TEST_MCC_VNS')->get()->getRow('id');
        $this->actor = (int) $this->database->table('users')->where('email', 'test.mcc.superadmin@example.invalid')->get()->getRow('id');
        $this->player = (int) $this->database->table('players p')
            ->select('p.id')
            ->join('persons pe', 'pe.id = p.person_id')
            ->where('pe.full_name', 'TEST_MCC_CONVERTED_PLAYER')
            ->where('p.branch_id', $this->branch)
            ->where('p.status', 'active')
            ->get()
            ->getRow('id');

        if ($this->branch < 1 || $this->actor < 1 || $this->player < 1) {
            self::markTestSkipped('Approved Tournament test fixtures are unavailable.');
        }

        session()->set([
            'isLoggedIn' => true,
            'accountEnabled' => true,
            'session_version' => 1,
            'user' => ['id' => $this->actor],
            'authorizedBranches' => [(string) $this->branch],
            'permissions' => ['tournament.view', 'tournament.create', 'tournament.update'],
        ]);
        \Config\Services::resetSingle('authorization');
    }

    protected function tearDown(): void
    {
        session()->destroy();
        \Config\Services::resetSingle('authorization');

        parent::tearDown();
    }

    public function testInsertOnlyCrudRoundTripKeepsBranchAndAuditLineage(): void
    {
        $name = 'TEST_MCC safe CRUD ' . bin2hex(random_bytes(5));
        $id = service('tournament')->save([
            'branch_id' => $this->branch,
            'name' => $name,
            'tournament_type' => 'club',
            'status' => 'planned',
            'starts_on' => '01-10-2026',
            'ends_on' => '03-10-2026',
            'player_ids' => [$this->player],
        ]);

        $stored = $this->database->table('tournaments')->where('id', $id)->get()->getRowArray();
        self::assertIsArray($stored);
        self::assertSame($this->branch, (int) $stored['branch_id']);

        $list = service('tournament')->list(['q' => $name]);
        self::assertSame($id, (int) $list['tournaments'][0]['id']);

        $detail = service('tournament')->find($id);
        self::assertSame($name, $detail['name']);
        self::assertSame($this->player, (int) $detail['participants'][0]['player_id']);
        self::assertSame(1, $this->database->table('audit_logs')->where([
            'action' => 'tournament_created',
            'entity_type' => 'tournament',
            'entity_id' => (string) $id,
            'branch_id' => $this->branch,
        ])->countAllResults());
    }

    public function testTournamentLifecycleTransitionsAreBranchScopedReasonedAndTerminal(): void
    {
        $suffix = bin2hex(random_bytes(5));
        $payload = [
            'branch_id' => $this->branch,
            'name' => 'TEST_MCC lifecycle tournament ' . $suffix,
            'tournament_type' => 'club',
            'status' => 'planned',
            'starts_on' => '01-10-2026',
            'ends_on' => '31-10-2026',
            'player_ids' => [$this->player],
        ];
        $id = service('tournament')->save($payload);

        $payload['status'] = 'active';
        service('tournament')->save($payload, $id);

        $payload['status'] = 'completed';
        service('tournament')->save($payload, $id);

        $payload['status'] = 'cancelled';
        try {
            service('tournament')->save($payload, $id);
            self::fail('A completed Tournament was reopened or cancelled.');
        } catch (\RuntimeException $exception) {
            self::assertSame('This Tournament status transition is not allowed.', $exception->getMessage());
        }

        self::assertSame(2, $this->database->table('audit_logs')->where([
            'action' => 'tournament_updated',
            'entity_type' => 'tournament',
            'entity_id' => (string) $id,
            'branch_id' => $this->branch,
        ])->countAllResults());
    }

    public function testTournamentCancellationRequiresReasonAndRecordsIt(): void
    {
        $suffix = bin2hex(random_bytes(5));
        $payload = [
            'branch_id' => $this->branch,
            'name' => 'TEST_MCC cancellation tournament ' . $suffix,
            'tournament_type' => 'club',
            'status' => 'planned',
            'starts_on' => '01-10-2026',
            'ends_on' => '31-10-2026',
        ];
        $id = service('tournament')->save($payload);
        $payload['status'] = 'cancelled';

        try {
            service('tournament')->save($payload, $id);
            self::fail('Cancellation without a reason was accepted.');
        } catch (\RuntimeException $exception) {
            self::assertSame('A reason is required when cancelling a Tournament.', $exception->getMessage());
        }

        $payload['reason'] = 'TEST_MCC cancellation reason';
        service('tournament')->save($payload, $id);
        $audit = $this->database->table('audit_logs')->where([
            'action' => 'tournament_updated',
            'entity_type' => 'tournament',
            'entity_id' => (string) $id,
            'branch_id' => $this->branch,
        ])->orderBy('id', 'DESC')->get()->getRowArray();

        self::assertIsArray($audit);
        self::assertSame('TEST_MCC cancellation reason', $audit['reason']);
    }

    public function testNewTournamentMustStartPlannedAndFixtureMustStayWithinDateBounds(): void
    {
        if (! $this->database->fieldExists('round', 'tournament_fixtures')) {
            self::markTestSkipped('Tournament fixture workflow migration is not applied.');
        }

        $suffix = bin2hex(random_bytes(5));
        $payload = [
            'branch_id' => $this->branch,
            'name' => 'TEST_MCC date boundary tournament ' . $suffix,
            'tournament_type' => 'club',
            'status' => 'active',
            'starts_on' => '01-10-2026',
            'ends_on' => '03-10-2026',
            'teams' => ['Home', 'Away'],
        ];

        try {
            service('tournament')->save($payload);
            self::fail('A new Tournament was created outside the planned starting status.');
        } catch (\RuntimeException $exception) {
            self::assertSame('New tournaments must start in the planned status.', $exception->getMessage());
        }

        $payload['status'] = 'planned';
        $tournament = service('tournament')->save($payload);
        $ground = $this->database->table('grounds')->where([
            'branch_id' => $this->branch,
            'is_active' => 1,
        ])->get()->getRow('id');
        self::assertGreaterThan(0, (int) $ground);

        try {
            service('tournament')->fixture($tournament, [
                'fixture_number' => 1,
                'round' => 'Round 1',
                'home_team' => 'Home',
                'away_team' => 'Away',
                'match_date' => '04-10-2026',
                'start_time' => '10:00',
                'ground_id' => (int) $ground,
            ]);
            self::fail('A fixture outside the Tournament dates was accepted.');
        } catch (\RuntimeException $exception) {
            self::assertSame('Fixture date must be within the Tournament dates.', $exception->getMessage());
        }
    }

    public function testFixtureWorkflowRejectsCrossTournamentTeamLinks(): void
    {
        if (! $this->database->fieldExists('round', 'tournament_fixtures')) {
            self::markTestSkipped('Tournament fixture workflow migration is not applied.');
        }

        $suffix = bin2hex(random_bytes(5));
        $groundName = 'TEST_MCC fixture ground ' . $suffix;
        $now = gmdate('Y-m-d H:i:s');
        $this->database->table('grounds')->insert([
            'branch_id' => $this->branch,
            'name' => $groundName,
            'ground_type' => 'outdoor_ground',
            'description' => 'Insert-only Tournament fixture coverage.',
            'is_active' => 1,
            'created_by' => $this->actor,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $ground = (int) $this->database->insertID();
        $tournament = service('tournament')->save([
            'branch_id' => $this->branch,
            'name' => 'TEST_MCC fixture tournament ' . $suffix,
            'tournament_type' => 'club',
            'status' => 'planned',
            'starts_on' => '01-10-2026',
            'ends_on' => '31-10-2026',
            'teams' => ['TEST MCC A ' . $suffix, 'TEST MCC B ' . $suffix],
        ]);

        $this->expectExceptionMessage('Home and away teams must belong to this Tournament.');
        service('tournament')->fixture($tournament, [
            'fixture_number' => 1,
            'round' => 'Round 1',
            'home_team' => 'Forged Team',
            'away_team' => 'TEST MCC B ' . $suffix,
            'match_date' => '02-10-2026',
            'start_time' => '10:00',
            'ground_id' => $ground,
        ]);
    }

    public function testCompletedFixtureContributesDefaultPointsToStandings(): void
    {
        if (! $this->database->fieldExists('round', 'tournament_fixtures')) {
            self::markTestSkipped('Tournament fixture workflow migration is not applied.');
        }

        $suffix = bin2hex(random_bytes(5));
        $now = gmdate('Y-m-d H:i:s');
        $this->database->table('grounds')->insert([
            'branch_id' => $this->branch,
            'name' => 'TEST_MCC standings ground ' . $suffix,
            'ground_type' => 'outdoor_ground',
            'description' => 'Insert-only standings coverage.',
            'is_active' => 1,
            'created_by' => $this->actor,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $ground = (int) $this->database->insertID();
        $home = 'TEST MCC home ' . $suffix;
        $away = 'TEST MCC away ' . $suffix;
        $idle = 'TEST MCC idle ' . $suffix;
        $tournament = service('tournament')->save([
            'branch_id' => $this->branch,
            'name' => 'TEST_MCC standings tournament ' . $suffix,
            'tournament_type' => 'club',
            'status' => 'planned',
            'starts_on' => '01-10-2026',
            'ends_on' => '31-10-2026',
            'teams' => [$home, $away, $idle],
        ]);
        $fixture = service('tournament')->fixture($tournament, [
            'fixture_number' => 1,
            'round' => 'Round 1',
            'home_team' => $home,
            'away_team' => $away,
            'match_date' => '02-10-2026',
            'start_time' => '10:00',
            'ground_id' => $ground,
        ]);
        $this->database->table('matches')->insert([
            'branch_id' => $this->branch,
            'ground_id' => $ground,
            'coach_user_id' => null,
            'opponent' => $away,
            'match_type' => 'club',
            'status' => 'completed',
            'starts_at' => '2026-10-02 04:30:00',
            'ends_at' => '2026-10-02 07:30:00',
            'result' => 'won',
            'score_summary' => null,
            'coach_remarks' => null,
            'created_by' => $this->actor,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $match = (int) $this->database->insertID();
        service('tournament')->completeFixture($tournament, $fixture, $match);

        $detail = service('tournament')->find($tournament);
        $standings = $detail['standings'];
        self::assertSame($home, $standings[0]['team_name']);
        self::assertSame(2, $standings[0]['points']);
        self::assertSame(1, $standings[0]['won']);
        self::assertSame(0, $standings[1]['points']);
        $idleStanding = array_values(array_filter(
            $standings,
            static fn (array $standing): bool => $standing['team_name'] === $idle,
        ))[0];
        self::assertSame(0, $idleStanding['played']);
        self::assertSame(0, $idleStanding['points']);
    }

    public function testFixtureStatusRulesAuditAndIdempotentCompletion(): void
    {
        $suffix = bin2hex(random_bytes(5));
        $now = gmdate('Y-m-d H:i:s');
        $this->database->table('grounds')->insert([
            'branch_id' => $this->branch,
            'name' => 'TEST_MCC workflow ground ' . $suffix,
            'ground_type' => 'outdoor_ground',
            'description' => 'Insert-only fixture workflow coverage.',
            'is_active' => 1,
            'created_by' => $this->actor,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $ground = (int) $this->database->insertID();
        $home = 'TEST MCC workflow home ' . $suffix;
        $away = 'TEST MCC workflow away ' . $suffix;
        $third = 'TEST MCC workflow third ' . $suffix;
        $tournament = service('tournament')->save([
            'branch_id' => $this->branch,
            'name' => 'TEST_MCC workflow tournament ' . $suffix,
            'tournament_type' => 'club',
            'status' => 'planned',
            'starts_on' => '01-10-2026',
            'ends_on' => '31-10-2026',
            'teams' => [$home, $away, $third],
        ]);

        $fixture = service('tournament')->fixture($tournament, [
            'fixture_number' => 1,
            'round' => 'Round 1',
            'home_team' => $home,
            'away_team' => $away,
            'match_date' => '02-10-2026',
            'start_time' => '10:00',
            'ground_id' => $ground,
        ]);

        try {
            service('tournament')->fixture($tournament, [
                'fixture_number' => 2,
                'round' => 'Round 1',
                'home_team' => $home,
                'away_team' => $third,
                'match_date' => '02-10-2026',
                'start_time' => '10:00',
                'ground_id' => $ground,
            ]);
            self::fail('Duplicate team scheduling was accepted.');
        } catch (\RuntimeException $exception) {
            self::assertSame('A team already has a fixture at that date and time.', $exception->getMessage());
        }

        try {
            service('tournament')->postponeFixture($tournament, $fixture, '');
            self::fail('Postponement without a reason was accepted.');
        } catch (\RuntimeException $exception) {
            self::assertSame('A reason is required for fixture status changes.', $exception->getMessage());
        }

        service('tournament')->postponeFixture($tournament, $fixture, 'Ground maintenance.');
        $stored = $this->database->table('tournament_fixtures')->where('id', $fixture)->get()->getRowArray();
        self::assertSame('postponed', $stored['status']);

        $cancelled = service('tournament')->fixture($tournament, [
            'fixture_number' => 2,
            'round' => 'Round 1',
            'home_team' => $away,
            'away_team' => $third,
            'match_date' => '03-10-2026',
            'start_time' => '10:00',
            'ground_id' => $ground,
        ]);
        service('tournament')->cancelFixture($tournament, $cancelled, 'Weather warning.');

        $completed = service('tournament')->fixture($tournament, [
            'fixture_number' => 3,
            'round' => 'Round 2',
            'home_team' => $home,
            'away_team' => $third,
            'match_date' => '04-10-2026',
            'start_time' => '10:00',
            'ground_id' => $ground,
        ]);
        $this->database->table('matches')->insert([
            'branch_id' => $this->branch,
            'ground_id' => $ground,
            'coach_user_id' => null,
            'opponent' => $third,
            'match_type' => 'club',
            'status' => 'completed',
            'starts_at' => '2026-10-04 04:30:00',
            'ends_at' => '2026-10-04 07:30:00',
            'result' => 'draw',
            'score_summary' => null,
            'coach_remarks' => null,
            'created_by' => $this->actor,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $match = (int) $this->database->insertID();

        service('tournament')->completeFixture($tournament, $completed, $match);
        service('tournament')->completeFixture($tournament, $completed, $match);

        self::assertSame(1, $this->database->table('audit_logs')->where([
            'action' => 'tournament_fixture_completed',
            'entity_type' => 'tournament_fixture',
            'entity_id' => (string) $completed,
            'branch_id' => $this->branch,
        ])->countAllResults());
        self::assertSame(1, $this->database->table('audit_logs')->where([
            'action' => 'tournament_fixture_postponed',
            'entity_id' => (string) $fixture,
        ])->countAllResults());
        self::assertSame(1, $this->database->table('audit_logs')->where([
            'action' => 'tournament_fixture_cancelled',
            'entity_id' => (string) $cancelled,
        ])->countAllResults());

        $detail = service('tournament')->find($tournament);
        $homeStanding = array_values(array_filter(
            $detail['standings'],
            static fn (array $standing): bool => $standing['team_name'] === $home,
        ))[0];
        self::assertSame(1, $homeStanding['played']);
        self::assertSame(1, $homeStanding['tied']);
        self::assertSame(1, $homeStanding['points']);
    }
}
