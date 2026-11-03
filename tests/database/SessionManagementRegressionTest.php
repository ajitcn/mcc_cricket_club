<?php

declare(strict_types=1);

namespace Tests\Database;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

final class SessionManagementRegressionTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $DBGroup = 'tests';
    protected $migrate = false;

    public function testPlayerOverlapAndNonCoachAssignmentAreRejected(): void
    {
        $db = $this->db;
        $branch = (int) $db->table('branches')->where('code', 'TEST_MCC_VNS')->get()->getRow('id');
        $player = (int) $db->table('players p')->select('p.id')->join('persons pe', 'pe.id = p.person_id')
            ->where('pe.full_name', 'TEST_MCC_CONVERTED_PLAYER')->get()->getRow('id');
        $batch = (int) $db->table('batches')->where(['branch_id' => $branch, 'name' => 'TEST_MCC_VNS_U15'])->get()->getRow('id');
        $program = (int) $db->table('batches')->where('id', $batch)->get()->getRow('program_id');
        $existing = (int) $db->table('sessions')->where(['branch_id' => $branch, 'status' => 'scheduled'])->get()->getRow('id');
        $existingSession = $db->table('sessions')->where('id', $existing)->get()->getRowArray();
        $fitnessCoach = (int) $db->table('users')->where('email', 'test.mcc.fitness@example.invalid')->get()->getRow('id');
        $parent = (int) $db->table('users')->where('email', 'test.mcc.parent@example.invalid')->get()->getRow('id');
        self::assertGreaterThan(0, min($branch, $player, $batch, $existing, $fitnessCoach, $parent));
        $this->ensureEligiblePlayerAssignment($db, $player, $branch, $batch, $fitnessCoach);

        $localStart = (new DateTimeImmutable((string) $existingSession['starts_at'], new DateTimeZone('UTC')))
            ->setTimezone(new DateTimeZone(config('App')->appTimezone));
        $localEnd = (new DateTimeImmutable((string) $existingSession['ends_at'], new DateTimeZone('UTC')))
            ->setTimezone(new DateTimeZone(config('App')->appTimezone));
        if ($db->tableExists('coach_availability')) {
            $db->table('coach_availability')->insert([
                'branch_id' => $branch,
                'coach_user_id' => $fitnessCoach,
                'weekday' => null,
                'available_date' => $localStart->format('Y-m-d'),
                'start_time' => $localStart->format('H:i:s'),
                'end_time' => $localEnd->format('H:i:s'),
                'reason' => 'TEST_MCC regression availability ' . uniqid('', true),
                'created_by' => $fitnessCoach,
                'created_at' => gmdate('Y-m-d H:i:s'),
                'updated_at' => gmdate('Y-m-d H:i:s'),
                'cancelled_at' => null,
            ]);
        }

        session()->set([
            'isLoggedIn' => true,
            'accountEnabled' => true,
            'user' => ['id' => $fitnessCoach],
            'authorizedBranches' => [(string) $branch],
            'permissions' => ['session.create'],
            'roles' => ['Fitness Coach'],
        ]);
        \Config\Services::resetSingle('authorization');
        try {
            service('scheduling', false)->create([
                'branch_id' => $branch,
                'program_id' => $program,
                'batch_id' => $batch,
                'coach_user_id' => $fitnessCoach,
                'starts_at' => $localStart->format('Y-m-d\TH:i:sP'),
                'ends_at' => $localEnd->format('Y-m-d\TH:i:sP'),
                'player_ids' => [$player],
            ]);
            self::fail('Player overlap accepted.');
        } catch (RuntimeException $exception) {
            self::assertSame('Player scheduling conflict.', $exception->getMessage());
        }

        session()->set([
            'user' => ['id' => $parent],
            'authorizedBranches' => [(string) $branch],
            'permissions' => ['session.create'],
            'roles' => ['Parent'],
        ]);
        \Config\Services::resetSingle('authorization');
        $this->expectExceptionMessage('Coach is not authorized for branch.');
        service('scheduling', false)->create([
            'branch_id' => $branch,
            'program_id' => $program,
            'batch_id' => $batch,
            'coach_user_id' => $parent,
            'starts_at' => '2026-09-05T11:00:00+05:30',
            'ends_at' => '2026-09-05T12:00:00+05:30',
            'player_ids' => [$player],
        ]);
    }

    private function ensureEligiblePlayerAssignment(object $db, int $player, int $branch, int $batch, int $assignedBy): void
    {
        $assignment = $db->table('player_batch_assignments')
            ->where([
                'player_id' => $player,
                'branch_id' => $branch,
                'batch_id' => $batch,
                'effective_to' => null,
            ])
            ->get()
            ->getRowArray();
        if (is_array($assignment)) {
            return;
        }

        $now = gmdate('Y-m-d H:i:s');
        $db->table('player_batch_assignments')->insert([
            'player_id' => $player,
            'branch_id' => $branch,
            'batch_id' => $batch,
            'assigned_by' => $assignedBy,
            'effective_from' => $now,
            'effective_to' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
