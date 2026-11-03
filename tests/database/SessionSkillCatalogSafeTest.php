<?php

declare(strict_types=1);

namespace Tests\Database;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/** Read-only verification for the centrally managed Individual Session skill catalog. */
final class SessionSkillCatalogSafeTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $DBGroup = 'tests';
    protected $migrate = false;

    public function testCatalogContainsExactlySixActiveOptionsPerArea(): void
    {
        self::assertSame('mcc_cricket_club_test', strtolower((string) $this->db->getDatabase()));
        if (! $this->db->tableExists('session_skill_options')) {
            self::markTestSkipped('The Individual Session skill catalog migration is not applied.');
        }

        $rows = $this->db->table('session_skill_options')
            ->select('area, name, display_order, version, is_active')
            ->where('is_active', 1)
            ->orderBy('area', 'ASC')
            ->orderBy('display_order', 'ASC')
            ->get()
            ->getResultArray();

        self::assertCount(36, $rows);
        self::assertSame(6, count(array_filter($rows, static fn(array $row): bool => $row['area'] === 'batting')));
        self::assertSame(6, count(array_filter($rows, static fn(array $row): bool => $row['area'] === 'bowling')));
        self::assertSame(6, count(array_filter($rows, static fn(array $row): bool => $row['area'] === 'fielding')));
        self::assertSame(6, count(array_filter($rows, static fn(array $row): bool => $row['area'] === 'wicketkeeping')));
        self::assertSame(6, count(array_filter($rows, static fn(array $row): bool => $row['area'] === 'fitness')));
        self::assertSame(6, count(array_filter($rows, static fn(array $row): bool => $row['area'] === 'game_awareness')));
        self::assertSame(36, count(array_filter($rows, static fn(array $row): bool => (int) $row['version'] >= 1)));
    }

    public function testSessionsRetainSkillFieldsForHistoricalLabels(): void
    {
        if (! $this->db->tableExists('sessions')
            || ! $this->db->tableExists('session_skill_options')
        ) {
            self::markTestSkipped('The tests database has not applied the Scheduling schema.');
        }

        self::assertTrue($this->db->fieldExists('skill_area', 'sessions'));
        self::assertTrue($this->db->fieldExists('skill_option_id', 'sessions'));
        self::assertTrue($this->db->fieldExists('skill_option_label', 'sessions'));

        $historicalRows = $this->db->table('sessions')
            ->select('skill_area, skill_option_id, skill_option_label')
            ->where('skill_option_id IS NOT NULL', null, false)
            ->get()
            ->getResultArray();
        if ($historicalRows === []) {
            self::markTestSkipped('No persisted session skill records are available for historical-label verification.');
        }

        foreach ($historicalRows as $row) {
            self::assertGreaterThan(0, (int) $row['skill_option_id']);
            self::assertNotSame('', trim((string) $row['skill_area']));
            self::assertNotSame('', trim((string) $row['skill_option_label']));
        }
    }
}
