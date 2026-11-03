<?php

declare(strict_types=1);

namespace Tests\Database;

use PHPUnit\Framework\TestCase;

final class AdmissionCompleteFormSafeTest extends TestCase
{
    public function testCompleteAdmissionMigrationIsForwardOnlyAndIdempotent(): void
    {
        $migration = (string) file_get_contents(ROOTPATH . 'app/Database/Migrations/20261011000300_AddCompleteAdmissionFormFields.php');

        self::assertStringContainsString('final class AddCompleteAdmissionFormFields', $migration);
        self::assertStringContainsString("if (! in_array('coach_user_id', \$fields, true))", $migration);
        self::assertStringContainsString('Forward-only', $migration);
        self::assertStringNotContainsString('dropTable', $migration);
        self::assertStringNotContainsString('truncate', strtolower($migration));
        self::assertStringNotContainsString('DELETE FROM', $migration);
        self::assertStringContainsString('FOREIGN KEY (coach_user_id)', $migration);
        self::assertStringContainsString("'age'", $migration);
        self::assertStringContainsString("'SMALLINT'", $migration);
        self::assertStringContainsString("'null' => \$name === 'age'", $migration);
    }

    public function testPersonalCoachMigrationIsAdditiveAndIndexed(): void
    {
        $migration = (string) file_get_contents(ROOTPATH . 'app/Database/Migrations/20261016000100_AddAdmissionPersonalCoach.php');

        self::assertStringContainsString('final class AddAdmissionPersonalCoach', $migration);
        self::assertStringContainsString("personal_coach_user_id", $migration);
        self::assertStringContainsString('admissions_personal_coach_idx', $migration);
        self::assertStringContainsString('FOREIGN KEY (personal_coach_user_id)', $migration);
        self::assertStringContainsString('Forward-only', $migration);
        self::assertStringNotContainsString('dropColumn', $migration);
        self::assertStringNotContainsString('dropTable', $migration);
    }

    public function testAdmissionSignatureStorageUsesExistingFieldsWithoutDestructiveMigration(): void
    {
        $migration = (string) file_get_contents(ROOTPATH . 'app/Database/Migrations/20261012000100_ExpandAdmissionSignatureFields.php');

        foreach (['student_signature', 'parent_signature', 'authorized_signature'] as $field) {
            self::assertStringContainsString($field, $migration);
        }
        self::assertStringContainsString("'type' => 'TEXT'", $migration);
        self::assertStringContainsString('Forward-only', $migration);
        self::assertStringNotContainsString('dropColumn', $migration);
        self::assertStringNotContainsString('truncate', strtolower($migration));
    }

    public function testSeparateInstallmentMigrationIsForwardOnlyAndKeepsLegacySource(): void
    {
        $migration = (string) file_get_contents(ROOTPATH . 'app/Database/Migrations/20261014000200_CreateSeparateAdmissionInstallmentTables.php');

        self::assertStringContainsString('admission_coaching_installments', $migration);
        self::assertStringContainsString('admission_hostel_installments', $migration);
        self::assertStringContainsString('admission_period_id', $migration);
        self::assertStringContainsString('installment_number', $migration);
        self::assertStringContainsString('INSERT IGNORE', $migration);
        self::assertStringContainsString('Forward-only', $migration);
        self::assertStringNotContainsString('dropTable', $migration);
        self::assertStringNotContainsString('truncate', strtolower($migration));
        self::assertStringNotContainsString('DELETE FROM', $migration);
    }
}
