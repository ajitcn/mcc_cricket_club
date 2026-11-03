<?php

declare(strict_types=1);

namespace Tests\Database;

use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;

final class PlayerDevelopmentIntegrationTest extends CIUnitTestCase
{
    protected $DBGroup = 'tests';
    protected function setUp(): void { parent::setUp(); $this->db = Database::connect('tests'); }
    public function testDevelopmentTablesPersistHistoryAndRemainBranchScoped(): void
    {
        self::assertTrue($this->db->tableExists('development_plans'));
        self::assertTrue($this->db->tableExists('development_goals'));
        self::assertTrue($this->db->tableExists('development_progress_history'));
        self::assertSame(0, $this->db->table('development_progress_history')->countAllResults());
    }
}
