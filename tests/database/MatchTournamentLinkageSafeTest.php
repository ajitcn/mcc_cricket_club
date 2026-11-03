<?php

declare(strict_types=1);

namespace Tests\Database;

use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;
use RuntimeException;

/** Insert-only Match/Tournament linkage and standings-source coverage. */
final class MatchTournamentLinkageSafeTest extends CIUnitTestCase
{
    private object $database;
    private int $branch;
    private int $otherBranch;
    private int $actor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->database = Database::connect('tests');
        self::assertSame('mcc_cricket_club_test', strtolower((string) $this->database->getDatabase()));
        $this->branch = (int) $this->database->table('branches')->where('code', 'TEST_MCC_VNS')->get()->getRow('id');
        $this->otherBranch = (int) $this->database->table('branches')->where('code', 'TEST_MCC_DEL')->get()->getRow('id');
        $this->actor = (int) $this->database->table('users')->where('email', 'test.mcc.superadmin@example.invalid')->get()->getRow('id');
        session()->set([
            'isLoggedIn' => true,
            'accountEnabled' => true,
            'session_version' => 1,
            'user' => ['id' => $this->actor],
            'authorizedBranches' => [(string) $this->branch, (string) $this->otherBranch],
            'permissions' => ['match.view', 'match.create', 'match.update', 'tournament.view', 'tournament.create', 'tournament.update'],
        ]);
        \Config\Services::resetSingle('authorization');
    }

    protected function tearDown(): void
    {
        session()->destroy();
        \Config\Services::resetSingle('authorization');
        parent::tearDown();
    }

    public function testOnlyCompletedVerifiedMatchFeedsStandingsAndMatchLinkIsUnique(): void
    {
        [$tournament, $ground, $home, $away] = $this->createTournament('source');
        $fixture = $this->createFixture($tournament, $ground, $home, $away, 1, 'scheduled');
        $scheduledMatch = $this->createMatch($this->branch, 'scheduled', 'won');

        try {
            service('tournament')->completeFixture($tournament, $fixture, $scheduledMatch);
            self::fail('An incomplete Match was accepted as a Fixture result.');
        } catch (RuntimeException $exception) {
            self::assertSame('Select a completed Match result for this fixture.', $exception->getMessage());
        }

        $before = service('tournament')->find($tournament)['standings'];
        self::assertSame(0, $this->standing($before, $home)['points']);

        $this->database->table('matches')->where('id', $scheduledMatch)->update([
            'status' => 'completed',
            'result' => 'won',
            'version' => 2,
        ]);
        service('tournament')->completeFixture($tournament, $fixture, $scheduledMatch);
        service('tournament')->completeFixture($tournament, $fixture, $scheduledMatch);

        $after = service('tournament')->find($tournament)['standings'];
        self::assertSame(2, $this->standing($after, $home)['points']);
        self::assertSame(0, $this->standing($after, $away)['points']);

        $second = $this->createFixture($tournament, $ground, $home, $away, 2, 'scheduled');
        $secondMatch = $this->createMatch($this->branch, 'completed', 'draw');
        $this->expectExceptionMessage('This Match is already linked to another fixture.');
        service('tournament')->completeFixture($tournament, $second, $scheduledMatch);
        unset($secondMatch);
    }

    public function testPostponedAndCancelledFixturesRemainOutOfStandingsWithAudit(): void
    {
        [$tournament, $ground, $home, $away] = $this->createTournament('status');
        $postponed = $this->createFixture($tournament, $ground, $home, $away, 1, 'scheduled');
        service('tournament')->postponeFixture($tournament, $postponed, 'TEST_MCC weather delay');

        service('tournament')->fixture($tournament, [
            'fixture_number' => 1,
            'round' => 'Round 1',
            'home_team' => $home,
            'away_team' => $away,
            'match_date' => '05-10-2031',
            'start_time' => '10:00',
            'ground_id' => $ground,
            'status' => 'scheduled',
            'reason' => 'TEST_MCC rescheduled',
        ], $postponed);

        $cancelled = $this->createFixture($tournament, $ground, $away, $home, 2, 'scheduled');
        service('tournament')->cancelFixture($tournament, $cancelled, 'TEST_MCC cancellation');

        $detail = service('tournament')->find($tournament);
        self::assertSame(0, $this->standing($detail['standings'], $home)['played']);
        self::assertSame(0, $this->standing($detail['standings'], $home)['points']);
        self::assertSame(1, $this->database->table('audit_logs')->where([
            'action' => 'tournament_fixture_postponed',
            'entity_id' => (string) $postponed,
        ])->countAllResults());
        self::assertSame(1, $this->database->table('audit_logs')->where([
            'action' => 'tournament_fixture_cancelled',
            'entity_id' => (string) $cancelled,
        ])->countAllResults());
    }

    public function testCompletedFixtureCorrectionRequiresVersionReasonAndAudit(): void
    {
        [$tournament, $ground, $home, $away] = $this->createTournament('correction');
        $fixture = $this->createFixture($tournament, $ground, $home, $away, 1, 'scheduled');
        $match = $this->createMatch($this->branch, 'completed', 'won');
        service('tournament')->completeFixture($tournament, $fixture, $match);
        $stored = $this->database->table('tournament_fixtures')->where('id', $fixture)->get()->getRowArray();

        $payload = [
            'fixture_number' => 1,
            'round' => 'Round 1 corrected',
            'home_team' => $home,
            'away_team' => $away,
            'match_date' => '02-10-2031',
            'start_time' => '10:00',
            'ground_id' => $ground,
            'status' => 'completed',
            'match_id' => $match,
            'correction_reason' => 'TEST_MCC correction',
            'expected_version' => (int) $stored['version'],
        ];
        service('tournament')->fixture($tournament, $payload, $fixture);
        $updated = $this->database->table('tournament_fixtures')->where('id', $fixture)->get()->getRowArray();
        self::assertSame((int) $stored['version'] + 1, (int) $updated['version']);

        $payload['round'] = 'Stale correction';
        try {
            service('tournament')->fixture($tournament, $payload, $fixture);
            self::fail('A stale Fixture correction was accepted.');
        } catch (RuntimeException $exception) {
            self::assertSame('The Fixture correction is stale. Reload before correcting it.', $exception->getMessage());
        }
        self::assertSame(1, $this->database->table('audit_logs')->where([
            'action' => 'tournament_fixture_corrected',
            'entity_id' => (string) $fixture,
            'reason' => 'TEST_MCC correction',
        ])->countAllResults());
    }

    public function testCrossBranchMatchCannotCompleteFixture(): void
    {
        [$tournament, $ground, $home, $away] = $this->createTournament('branch');
        $fixture = $this->createFixture($tournament, $ground, $home, $away, 1, 'scheduled');
        $foreignMatch = $this->createMatch($this->otherBranch, 'completed', 'won');

        $this->expectExceptionMessage('Select a completed Match result for this fixture.');
        service('tournament')->completeFixture($tournament, $fixture, $foreignMatch);
    }

    private function createMatch(int $branch, string $status, string $result): int
    {
        $now = gmdate('Y-m-d H:i:s');
        $this->database->table('matches')->insert([
            'branch_id' => $branch,
            'ground_id' => null,
            'coach_user_id' => null,
            'opponent' => 'TEST_MCC linkage opponent ' . bin2hex(random_bytes(4)),
            'match_type' => 'friendly',
            'status' => $status,
            'version' => 1,
            'starts_at' => '2031-10-02 04:30:00',
            'ends_at' => '2031-10-02 07:30:00',
            'result' => $result,
            'score_summary' => null,
            'coach_remarks' => null,
            'created_by' => $this->actor,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) $this->database->insertID();
    }

    private function createTournament(string $label): array
    {
        $suffix = bin2hex(random_bytes(4));
        $ground = $this->createGround($suffix);
        $home = 'TEST_MCC ' . $label . ' home ' . $suffix;
        $away = 'TEST_MCC ' . $label . ' away ' . $suffix;
        $tournament = service('tournament')->save([
            'branch_id' => $this->branch,
            'name' => 'TEST_MCC linkage ' . $label . ' ' . $suffix,
            'tournament_type' => 'club',
            'status' => 'planned',
            'starts_on' => '01-10-2031',
            'ends_on' => '31-10-2031',
            'teams' => [$home, $away],
        ]);

        return [$tournament, $ground, $home, $away];
    }

    private function createFixture(int $tournament, int $ground, string $home, string $away, int $number, string $status): int
    {
        return service('tournament')->fixture($tournament, [
            'fixture_number' => $number,
            'round' => 'Round ' . $number,
            'home_team' => $home,
            'away_team' => $away,
            'match_date' => sprintf('%02d-10-2031', $number + 1),
            'start_time' => '10:00',
            'ground_id' => $ground,
            'status' => $status,
        ]);
    }

    private function createGround(string $suffix): int
    {
        $now = gmdate('Y-m-d H:i:s');
        $this->database->table('grounds')->insert([
            'branch_id' => $this->branch,
            'name' => 'TEST_MCC linkage ground ' . $suffix,
            'ground_type' => 'outdoor_ground',
            'description' => 'Insert-only Match/Tournament linkage coverage.',
            'is_active' => 1,
            'created_by' => $this->actor,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) $this->database->insertID();
    }

    private function standing(array $standings, string $team): array
    {
        foreach ($standings as $standing) {
            if (($standing['team_name'] ?? '') === $team) {
                return $standing;
            }
        }

        self::fail('Standing was not found for ' . $team . '.');
    }
}
