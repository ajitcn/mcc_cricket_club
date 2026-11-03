<?php

declare(strict_types=1);

namespace Tests\Feature;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

final class ProgramBatchBrowserRoutesTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $DBGroup = 'tests';
    protected $migrate = false;

    private int $actor;

    private int $coach;

    private int $branch;

    private int $otherBranch;

    private int $batch;

    private int $program;

    protected function setUp(): void
    {
        parent::setUp();

        self::assertSame(
            'mcc_cricket_club_test',
            strtolower((string) $this->db->getDatabase())
        );

        $this->actor = $this->userId('test.mcc.superadmin@example.invalid');
        $this->coach = $this->userId('test.mcc.coach@example.invalid');
        $this->branch = $this->branchId('TEST_MCC_VNS');
        $this->otherBranch = $this->branchId('TEST_MCC_DEL');
        $this->program = (int) $this->db->table('programs')
            ->orderBy('id', 'ASC')
            ->get()
            ->getRow('id');
        $this->batch = (int) $this->db->table('batches')
            ->where('branch_id', $this->branch)
            ->orderBy('id', 'ASC')
            ->get()
            ->getRow('id');

        if (min($this->actor, $this->coach, $this->branch, $this->otherBranch, $this->program, $this->batch) < 1) {
            self::markTestSkipped('Program and Batch route fixtures are not available.');
        }
    }

    protected function tearDown(): void
    {
        session()->destroy();
        \Config\Services::resetSingle('authorization');
        parent::tearDown();
    }

    public function testAuthorizedUserCanOpenProgramAndBatchListsAndEditForms(): void
    {
        $this->authorize([$this->branch]);

        $programs = $this->withSession(session()->get())->get('programs');
        $programEdit = $this->withSession(session()->get())->get('programs/' . $this->program . '/edit');
        $batches = $this->withSession(session()->get())->get('batches');
        $batchEdit = $this->withSession(session()->get())->get('batches/' . $this->batch . '/edit');

        self::assertSame(200, $programs->response()->getStatusCode());
        self::assertSame(200, $programEdit->response()->getStatusCode());
        self::assertSame(200, $batches->response()->getStatusCode());
        self::assertSame(200, $batchEdit->response()->getStatusCode());
        self::assertStringContainsString('Edit program', $programEdit->getBody());
        self::assertStringContainsString('Edit batch', $batchEdit->getBody());
        self::assertStringContainsString('csrf', strtolower($programEdit->getBody()));
        self::assertStringContainsString('csrf', strtolower($batchEdit->getBody()));
    }

    public function testUnauthorizedUserCannotOpenGlobalBatchEditForm(): void
    {
        $this->authorize([$this->branch], []);

        $response = $this->withSession(session()->get())->get('batches/' . $this->batch . '/edit');

        self::assertContains($response->response()->getStatusCode(), [403, 404]);
    }

    public function testCoachCanOpenTheIntendedGlobalProgramWorkflow(): void
    {
        $this->authorize(
            [$this->branch],
            ['program.view', 'program.create'],
            $this->coach,
            ['Coach'],
        );

        $programs = $this->withSession(session()->get())->get('programs');
        $create = $this->withSession(session()->get())->get('programs/new');
        $edit = $this->withSession(session()->get())->get('programs/' . $this->program . '/edit');

        self::assertSame(200, $programs->response()->getStatusCode());
        self::assertSame(200, $create->response()->getStatusCode());
        self::assertSame(200, $edit->response()->getStatusCode());
        self::assertStringContainsString('Create program', $programs->getBody());
        self::assertStringContainsString('name="description"', $create->getBody());
        self::assertStringContainsString('Edit program', $edit->getBody());
    }

    public function testCoachWithoutProgramPermissionIsDenied(): void
    {
        $this->authorize([$this->branch], [], $this->coach, ['Coach']);

        $response = $this->withSession(session()->get())->get('programs');

        self::assertContains($response->response()->getStatusCode(), [403, 404]);
    }

    /** @param list<int> $branches */
    private function authorize(
        array $branches,
        array $permissions = ['program.view', 'program.create', 'batch.view', 'batch.create'],
        ?int $actor = null,
        array $roles = ['Super Admin'],
    ): void
    {
        $resolvedActor = $actor ?? $this->actor;
        $sessionVersion = (int) $this->db
            ->table('users')
            ->where('id', $resolvedActor)
            ->get()
            ->getRow('session_version');

        session()->set([
            'isLoggedIn' => true,
            'accountEnabled' => true,
            'user' => ['id' => $resolvedActor],
            'roles' => $roles,
            'session_version' => $sessionVersion,
            'permissions' => [
                ...$permissions,
            ],
            'authorizedBranches' => array_map('strval', $branches),
        ]);
        \Config\Services::resetSingle('authorization');
    }

    private function userId(string $email): int
    {
        return (int) $this->db->table('users')
            ->where('email', $email)
            ->get()
            ->getRow('id');
    }

    private function branchId(string $code): int
    {
        return (int) $this->db->table('branches')
            ->where('code', $code)
            ->get()
            ->getRow('id');
    }
}
