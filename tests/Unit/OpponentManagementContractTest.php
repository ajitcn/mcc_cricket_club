<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class OpponentManagementContractTest extends TestCase
{
    public function testSaveValidatesOpponentFieldsIndividually(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Services/OpponentManagementService.php');
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Matches/Controllers/Opponents.php');

        self::assertIsString($service);
        self::assertIsString($controller);
        self::assertStringContainsString("throw new RuntimeException('Invalid branch.')", $service);
        self::assertStringContainsString("throw new RuntimeException('Invalid opponent name.')", $service);
        self::assertStringContainsString("throw new RuntimeException('Invalid active status.')", $service);
        self::assertStringContainsString("'branch_id' => 'Select an authorized branch.'", $controller);
        self::assertStringContainsString("'is_active' => 'Select a valid active status.'", $controller);
    }

    public function testSavePreservesExplicitCreateAndEditTargets(): void
    {
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Matches/Controllers/Opponents.php');

        self::assertIsString($controller);
        self::assertStringContainsString("'/matches/opponents/new'", $controller);
        self::assertStringContainsString("'/matches/opponents/' . $id . '/edit'", $controller);
        self::assertStringContainsString('withInput()', $controller);
        self::assertStringNotContainsString('redirect()->back()', $controller);
        self::assertStringContainsString("session('user')", $service = file_get_contents(ROOTPATH . 'app/Services/OpponentManagementService.php'));
        self::assertStringContainsString('return $userId > 0 ? $userId : null;', $service);
    }

    public function testEditLookupDistinguishesMissingAndUnauthorizedRecords(): void
    {
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Matches/Controllers/Opponents.php');

        self::assertIsString($controller);
        self::assertStringContainsString("\$status = \$exception->getMessage() === 'Forbidden' ? 403 : 404;", $controller);
        self::assertStringContainsString("? 'Not found.'", $controller);
    }
}
