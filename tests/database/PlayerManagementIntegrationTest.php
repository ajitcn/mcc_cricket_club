<?php

declare (strict_types=1);
namespace Tests\Database;

use App\Models\UserModel;
use App\Services\AdmissionService;
use App\Services\PlayerService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use RuntimeException;
final class PlayerManagementIntegrationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    protected $DBGroup = 'tests';
    protected $migrate = false;
    private int $branchOne;
    private int $branchTwo;
    private int $actor;
    protected function setUp(): void
    {
        parent::setUp();
        self::assertSame('mcc_cricket_club_test', $this->db->getDatabase());
        foreach (['player_coach_assignments', 'player_batch_assignments', 'batches', 'player_branch_history', 'players', 'persons', 'audit_logs',
    'admission_status_history', 'admissions', 'leads', 'user_branches', 'users', 'branches'] as $table) {
            $this->db->table($table)->emptyTable();
        }
        $now = date('Y-m-d H:i:s');
        $this->db->table('branches')->insert(['name' => 'North', 'code' => 'player-north', 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);
        $this->branchOne = (int) $this->db->insertID();
        $this->db->table('branches')->insert(['name' => 'South', 'code' => 'player-south', 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);
        $this->branchTwo = (int) $this->db->insertID();
        $this->actor = $this->createUser('player.actor@example.test');
        $this->setSession(['player.view', 'player.update', 'player.transfer', 'admission.create', 'admission.update', 'player.convert']);
    }
    protected function tearDown(): void
    {
        session()->destroy();
        parent::tearDown();
    }
    public function testAuthorizedReadReturnsProfileAndPermanentLineage(): void
    {
        [$player, $admission, $lead] = $this->convertedPlayer();
        $view = $this->players()->find($player);
        self::assertSame($player, $view['id']);
        self::assertSame($admission, $view['admission_id']);
        self::assertSame($lead, $view['lead_id']);
        self::assertSame($this->branchOne, $view['branch_id']);
        self::assertSame('active', $view['status']);
        self::assertSame('Player Candidate', $view['profile']['full_name']);
        self::assertSame(1, $this->db->table('player_branch_history')->where('player_id', $player)->countAllResults());
    }

    public function testUnauthorizedAndCrossBranchReadsAndWritesAreDenied(): void
    {
        [$player] = $this->convertedPlayer();
        $this->setSession([]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Forbidden');
        $this->players()->find($player);
    }
    public function testCrossBranchReadAndWriteAreDeniedByAuthoritativePlayerBranch(): void
    {
        [$player] = $this->convertedPlayer();
        $this->setSession(['player.view', 'player.update'], [$this->branchTwo]);
        try {
            $this->players()->find($player);
            self::fail('Cross-branch read accepted.');
        } catch (RuntimeException $exception) {
            self::assertSame('Forbidden', $exception->getMessage());
        }
        try {
            $row = $this->db->table('players')->where('id', $player)->get()->getRowArray();
            $this->players()->updateProfile($player, ['full_name' => 'Blocked'], (string) $row['updated_at'], (int) $row['profile_revision']);
            self::fail('Cross-branch update accepted.');
        } catch (RuntimeException $exception) {
            self::assertSame('Forbidden', $exception->getMessage());
        }
    }
    public function testValidProfileUpdatePersistsAndAuditsWithoutChangingIdentity(): void
    {
        [$player, $admission, $lead] = $this->convertedPlayer();
        $before = $this->db->table('players')->where('id', $player)->get()->getRowArray();
        $this->players()->updateProfile($player, ['full_name' => 'Updated Candidate', 'phone' => '8888888888'], (string) $before['updated_at'],
    (int) $before['profile_revision']);
        $view = $this->players()->find($player);
        self::assertSame($player, $view['id']);
        self::assertSame($admission, $view['admission_id']);
        self::assertSame($lead, $view['lead_id']);
        self::assertSame('Updated Candidate', $view['profile']['full_name']);
        self::assertSame('8888888888', $view['profile']['phone']);
        self::assertSame(1, $this->db->table('audit_logs')->where(['action' => 'player_profile_updated', 'entity_id' => (string) $player])->countAllResults());
    }
    public function testInvalidProfileAndImmutableLineageFieldsAreRejected(): void
    {
        [$player] = $this->convertedPlayer();
        $row = $this->db->table('players')->where('id', $player)->get()->getRowArray();
        try {
            $this->players()->updateProfile($player, ['id' => 9], (string) $row['updated_at'], (int) $row['profile_revision']);
            self::fail('Immutable identity accepted.');
        } catch (RuntimeException $exception) {
            self::assertSame('Invalid player profile.', $exception->getMessage());
        }
        try {
            $this->players()->updateProfile($player, ['email' => 'not-an-email'], (string) $row['updated_at'], (int) $row['profile_revision']);
            self::fail('Invalid profile accepted.');
        } catch (RuntimeException $exception) {
            self::assertSame('Invalid player profile.', $exception->getMessage());
        }
        $person = $this->db->table('persons')->where('id', $row['person_id'])->get()->getRowArray();
        self::assertSame('Player Candidate', $person['full_name']);
    }
    public function testStaleProfileUpdateIsRejectedWithoutSilentOverwrite(): void
    {
        [$player] = $this->convertedPlayer();
        $row = $this->db->table('players')->where('id', $player)->get()->getRowArray();
        $this->players()->updateProfile($player, ['full_name' => 'First Update'], (string) $row['updated_at'], (int) $row['profile_revision']);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Update conflict.');
        $this->players()->updateProfile($player, ['full_name' => 'Stale Update'], (string) $row['updated_at'], (int) $row['profile_revision']);
    }
    public function testConversionRetryDoesNotCreateDuplicatePlayer(): void
    {
        [$player, $admission] = $this->convertedPlayer();
        $retry = $this->admissions()->convert($admission);
        self::assertSame($player, $retry);
        self::assertSame(1, $this->db->table('players')->where('admission_id', $admission)->countAllResults());
        self::assertSame(1, $this->db->table('players')->where('lead_id', $this->db->table('admissions')->where('id',
    $admission)->get()->getRow()->lead_id)->countAllResults());
    }
    public function testAuthorizedBranchTransferPreservesEffectiveHistoryAndAudits(): void
    {
        [$player] = $this->convertedPlayer();
        $before = $this->db->table('players')->where('id', $player)->get()->getRowArray();
        $this->setSession(['player.view', 'player.transfer'], [$this->branchOne, $this->branchTwo]);
        $this->players()->transferBranch($player, $this->branchTwo, '2026-09-01 00:00:00', (string) $before['updated_at'], (int) $before['profile_revision']);
        $view = $this->players()->find($player);
        self::assertSame($this->branchTwo, $view['branch_id']);
        self::assertCount(2, $view['branch_history']);
        self::assertSame($this->branchOne, $view['branch_history'][0]['branch_id']);
        self::assertSame('2026-09-01 00:00:00', $view['branch_history'][0]['effective_to']);
        self::assertSame($this->branchTwo, $view['branch_history'][1]['branch_id']);
        self::assertNull($view['branch_history'][1]['effective_to']);
        self::assertSame(1, $this->db->table('audit_logs')->where(['action' => 'player_branch_transferred',
    'entity_id' => (string) $player])->countAllResults());
    }
    public function testBranchTransferRejectsAnUnknownPlayerBeforeAuthorization(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Not found.');

        $this->players()->transferBranch(
            999999,
            $this->branchTwo,
            '2026-09-01 00:00:00',
            '2026-01-01 00:00:00',
            1,
        );
    }
    public function testBranchTransferRequiresAuthorizationForTheTargetBranch(): void
    {
        [$player] = $this->convertedPlayer();
        $before = $this->db->table('players')->where('id', $player)->get()->getRowArray();
        $this->setSession(['player.view', 'player.transfer'], [$this->branchOne]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Forbidden');

        $this->players()->transferBranch(
            $player,
            $this->branchTwo,
            '2026-09-01 00:00:00',
            (string) $before['updated_at'],
            (int) $before['profile_revision'],
        );
    }
    public function testInvalidOrUnauthorizedTransferDoesNotChangeBranchHistory(): void
    {
        [$player] = $this->convertedPlayer();
        $before = $this->db->table('players')->where('id', $player)->get()->getRowArray();
        try {
            $this->players()->transferBranch($player, $this->branchTwo, '2026-08-01 00:00:00', (string) $before['updated_at'],
    (int) $before['profile_revision']);
            self::fail('Invalid transfer accepted.');
        } catch (RuntimeException $exception) {
            self::assertSame('Forbidden', $exception->getMessage());
        }
        self::assertSame(1, $this->db->table('player_branch_history')->where('player_id', $player)->countAllResults());
        self::assertSame($this->branchOne, (int) $this->db->table('players')->where('id', $player)->get()->getRow()->branch_id);
    }
    public function testDuplicateActiveBranchAssignmentIsRejected(): void
    {
        [$player] = $this->convertedPlayer();
        $before = $this->db->table('players')->where('id', $player)->get()->getRowArray();
        $this->setSession(['player.view', 'player.transfer'], [$this->branchOne, $this->branchTwo]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid branch transfer.');
        $this->players()->transferBranch($player, $this->branchOne, '2026-09-01 00:00:00', (string) $before['updated_at'], (int) $before['profile_revision']);
    }
    public function testStaleTransferRollsBackWithoutPartialHistory(): void
    {
        [$player] = $this->convertedPlayer();
        $before = $this->db->table('players')->where('id', $player)->get()->getRowArray();
        $this->setSession(['player.view', 'player.update', 'player.transfer'], [$this->branchOne, $this->branchTwo]);
        $this->players()->updateProfile($player, ['full_name' => 'Changed First'], (string) $before['updated_at'], (int) $before['profile_revision']);
        try {
            $this->players()->transferBranch($player, $this->branchTwo, '2026-09-01 00:00:00', (string) $before['updated_at'],
    (int) $before['profile_revision']);
            self::fail('Stale transfer accepted.');
        } catch (RuntimeException $exception) {
            self::assertSame('Update conflict.', $exception->getMessage());
        }
        self::assertSame(1, $this->db->table('player_branch_history')->where('player_id', $player)->countAllResults());
        self::assertSame($this->branchOne, (int) $this->db->table('players')->where('id', $player)->get()->getRow()->branch_id);
    }
    public function testAuthorizedBatchAssignmentAndReplacementPreserveHistory(): void
    {
        [$player] = $this->convertedPlayer();
        $batchOne = $this->createBatch($this->branchOne, 'Morning');
        $batchTwo = $this->createBatch($this->branchOne, 'Evening');
        $before = $this->db->table('players')->where('id', $player)->get()->getRowArray();
        $this->setSession(['player.view', 'player.batch.assign'], [$this->branchOne]);
        $this->players()->assignBatch($player, $batchOne, '2026-09-01 09:00:00', null, (string) $before['updated_at'], (int) $before['profile_revision']);
        $after = $this->db->table('players')->where('id', $player)->get()->getRowArray();
        $this->players()->assignBatch($player, $batchTwo, '2026-10-01 09:00:00', null, (string) $after['updated_at'], (int) $after['profile_revision']);
        $history = $this->players()->find($player)['batch_history'];
        self::assertCount(2, $history);
        self::assertSame($batchOne, $history[0]['batch_id']);
        self::assertSame('2026-10-01 09:00:00', $history[0]['effective_to']);
        self::assertSame($batchTwo, $history[1]['batch_id']);
        self::assertSame(2, $this->db->table('audit_logs')->where(['action' => 'player_batch_assignment_changed',
    'entity_id' => (string) $player])->countAllResults());
    }
    public function testBatchRemovalAndDuplicateActiveAssignmentAreControlled(): void
    {
        [$player] = $this->convertedPlayer();
        $batch = $this->createBatch($this->branchOne, 'Morning');
        $before = $this->db->table('players')->where('id', $player)->get()->getRowArray();
        $this->setSession(['player.view', 'player.batch.assign'], [$this->branchOne]);
        $this->players()->assignBatch($player, $batch, '2026-09-01 09:00:00', null, (string) $before['updated_at'], (int) $before['profile_revision']);
        $current = $this->db->table('players')->where('id', $player)->get()->getRowArray();
        try {
            $this->players()->assignBatch($player, $batch, '2026-10-01 09:00:00', null, (string) $current['updated_at'], (int) $current['profile_revision']);
            self::fail('Duplicate accepted.');
        } catch (RuntimeException $e) {
            self::assertSame('Duplicate active assignment.', $e->getMessage());
        }
        $current = $this->db->table('players')->where('id', $player)->get()->getRowArray();
        $this->players()->assignBatch($player, null, '2026-10-01 09:00:00', null, (string) $current['updated_at'], (int) $current['profile_revision']);
        self::assertSame(0, $this->db->table('player_batch_assignments')->where(['player_id' => $player, 'effective_to' => null])->countAllResults());
    }
    public function testCoachAssignmentRequiresAuthorizedBranchAndSupportsExplicitReplacement(): void
    {
        [$player] = $this->convertedPlayer();
        $coachOne = $this->createUser('coach.one@example.test');
        $coachTwo = $this->createUser('coach.two@example.test');
        $now = date('Y-m-d H:i:s');
        $this->db->table('user_branches')->insert(['user_id' => $coachOne, 'branch_id' => $this->branchOne, 'created_at' => $now]);
        $this->db->table('user_branches')->insert(['user_id' => $coachTwo, 'branch_id' => $this->branchOne, 'created_at' => $now]);
        $before = $this->db->table('players')->where('id', $player)->get()->getRowArray();
        $this->setSession(['player.view', 'player.coach.assign'], [$this->branchOne]);
        $this->players()->assignCoach($player, $coachOne, '2026-09-01 09:00:00', null, (string) $before['updated_at'], (int) $before['profile_revision']);
        $after = $this->db->table('players')->where('id', $player)->get()->getRowArray();
        $this->players()->assignCoach($player, $coachTwo, '2026-10-01 09:00:00', null, (string) $after['updated_at'], (int) $after['profile_revision']);
        $history = $this->players()->find($player)['coach_history'];
        self::assertSame($coachOne, $history[0]['coach_user_id']);
        self::assertSame($coachTwo, $history[1]['coach_user_id']);
    }
    public function testAssignmentDeniesUnauthorizedCrossBranchAndStaleWrites(): void
    {
        [$player] = $this->convertedPlayer();
        $batch = $this->createBatch($this->branchTwo, 'South');
        $before = $this->db->table('players')->where('id', $player)->get()->getRowArray();
        $this->setSession(['player.batch.assign'], [$this->branchOne]);
        try {
            $this->players()->assignBatch($player, $batch, '2026-09-01 09:00:00', null, (string) $before['updated_at'], (int) $before['profile_revision']);
            self::fail('Cross-branch assignment accepted.');
        } catch (RuntimeException $e) {
            self::assertSame('Invalid assignment.', $e->getMessage());
        }
        self::assertSame(0, $this->db->table('player_batch_assignments')->countAllResults());
        $this->setSession(['player.batch.assign'], [$this->branchOne]);
        $validBatch = $this->createBatch($this->branchOne, 'North');
        $this->players()->assignBatch($player, $validBatch, '2026-09-01 09:00:00', null, (string) $before['updated_at'], (int) $before['profile_revision']);
        try {
            $this->players()->assignBatch($player, null, '2026-10-01 09:00:00', null, (string) $before['updated_at'], (int) $before['profile_revision']);
            self::fail('Stale assignment accepted.');
        } catch (RuntimeException $e) {
            self::assertSame('Update conflict.', $e->getMessage());
        }
    }
    private function convertedPlayer(): array
    {
        $now = date('Y-m-d H:i:s');
        $this->db->table('leads')->insert(['branch_id' => $this->branchOne, 'owner_user_id' => $this->actor, 'source' => 'website',
    'full_name' => 'Player Candidate', 'email' => 'candidate' . uniqid() . '@example.test', 'phone' => '9999999999', 'date_of_birth' => '2012-05-10',
    'status' => 'lead', 'created_at' => $now, 'updated_at' => $now]);
        $lead = (int) $this->db->insertID();
        $admission = $this->admissions()->createFromLead($lead);
        $this->admissions()->updateChecklist($admission, ['checklist_identity_complete' => true, 'checklist_contact_complete' => true,
    'checklist_admission_complete' => true, 'checklist_branch_confirmed' => true]);
        $this->admissions()->transition($admission, 'APPROVED');
        $player = $this->admissions()->convert($admission);
        return [$player, $admission, $lead];
    }
    private function createUser(string $email): int
    {
        $model = new UserModel();
        $model->insert(['email' => $email, 'password_hash' => password_hash('Test password!', PASSWORD_DEFAULT), 'display_name' => 'Player User',
    'is_enabled' => 1, 'failed_login_count' => 0, 'session_version' => 1, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
        return (int) $model->getInsertID();
    }
    private function createBatch(int $branch, string $name): int
    {
        $now = date('Y-m-d H:i:s');
        $this->db->table('batches')->insert(['branch_id' => $branch, 'name' => $name, 'created_at' => $now, 'updated_at' => $now]);
        return (int) $this->db->insertID();
    }
    private function setSession(array $permissions, ?array $branches = null): void
    {
        \Config\Services::resetSingle('authorization');
        session()->set(['isLoggedIn' => true, 'accountEnabled' => true, 'user' => ['id' => $this->actor], 'session_version' => 1,
    'authorizedBranches' => array_map('strval', $branches ?? [$this->branchOne]), 'permissions' => $permissions]);
    }
    private function admissions(): AdmissionService
    {
        return service('admission', false);
    }
    private function players(): PlayerService
    {
        return service('player', false);
    }
}
