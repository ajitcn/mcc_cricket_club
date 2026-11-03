<?php

declare(strict_types=1);

namespace Tests\Database;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

final class AdmissionPlanRemovalSafeTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $DBGroup = 'tests';
    protected $migrate = false;

    public function testDevelopmentSchemaRemovesPlanTablesAndReferencesOnly(): void
    {
        self::assertSame('mcc_cricket_club_test', $this->db->getDatabase());

        foreach ([
            'admission_coaching_plans',
            'admission_fee_plans',
            'admission_hostel_plans',
            'admission_academic_plans',
        ] as $table) {
            self::assertFalse($this->db->tableExists($table));
        }

        foreach ([
            'coaching_plan_id',
            'fee_plan_id',
            'hostel_plan_id',
            'academic_plan_id',
        ] as $column) {
            self::assertFalse($this->db->fieldExists($column, 'admissions'));
            self::assertFalse($this->db->fieldExists($column, 'admission_periods'));
        }

        self::assertGreaterThanOrEqual(0, $this->db->table('admissions')->countAllResults());
        self::assertGreaterThanOrEqual(0, $this->db->table('admission_periods')->countAllResults());
    }
}
