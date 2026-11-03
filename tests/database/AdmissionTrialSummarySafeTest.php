<?php

declare(strict_types=1);

namespace Tests\Database;

use PHPUnit\Framework\TestCase;

final class AdmissionTrialSummarySafeTest extends TestCase
{
    public function testTrialProjectionUsesExistingBranchScopedRelationships(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Services/AdmissionService.php');

        self::assertIsString($service);
        self::assertStringContainsString('LEFT JOIN trials t', $service);
        self::assertStringContainsString('INNER JOIN branches b', $service);
        self::assertStringContainsString('WHERE a.id = ?', $service);
        self::assertStringContainsString("\$this->authorize((int) \$authoritative['branch_id'], 'admission.view')", $service);
    }
}
