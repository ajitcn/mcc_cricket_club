<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

final class OpponentManagementRoutesTest extends TestCase
{
    public function testOpponentRoutesKeepPermissionsAndFormCsrf(): void
    {
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Matches/Config/Routes.php');
        $form = file_get_contents(ROOTPATH . 'app/Modules/Matches/Views/opponent_form.php');

        self::assertIsString($routes);
        self::assertIsString($form);
        self::assertStringContainsString("permission:opponent.create", $routes);
        self::assertStringContainsString("permission:opponent.update", $routes);
        self::assertStringContainsString('csrf_field()', $form);
        self::assertStringContainsString('mcc-field-error', $form);
        self::assertStringContainsString("old('name'", $form);
        self::assertStringContainsString("old('branch_id'", $form);
    }

    public function testFailureTargetsAreExplicitForCreateAndEdit(): void
    {
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Matches/Controllers/Opponents.php');

        self::assertIsString($controller);
        self::assertStringContainsString('$target = $id === null', $controller);
        self::assertStringContainsString('redirect()->to($target)', $controller);
        self::assertStringContainsString('->with(\'error\', $this->safe($exception))', $controller);
        self::assertStringContainsString('csrf_field()', $form);
    }

    public function testOpponentEditPathKeepsSafeNotFoundAndForbiddenResponses(): void
    {
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Matches/Controllers/Opponents.php');

        self::assertIsString($controller);
        self::assertStringContainsString('setStatusCode($status)', $controller);
        self::assertStringContainsString("'Not found.'", $controller);
        self::assertStringContainsString("? 'Not found.'", $controller);
    }
}
