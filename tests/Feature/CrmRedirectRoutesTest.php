<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

final class CrmRedirectRoutesTest extends TestCase
{
    public function testLeadAndTrialRoutesProvideExplicitReturnTargets(): void
    {
        $routes = file_get_contents(ROOTPATH . 'app/Modules/CRM/Config/Routes.php');
        $trialController = file_get_contents(
            ROOTPATH . 'app/Modules/Trials/Controllers/Browser.php',
        );

        self::assertIsString($routes);
        self::assertIsString($trialController);
        self::assertStringContainsString(
            "\$routes->get('leads/new',",
            $routes,
        );
        self::assertStringContainsString(
            "\$routes->post('leads',",
            $routes,
        );
        self::assertStringContainsString(
            "\$routes->post('leads/(:num)/edit',",
            $routes,
        );
        self::assertStringContainsString(
            'redirectWithTrialError',
            $trialController,
        );
        self::assertStringContainsString(
            "redirect()->to(\$target)->withInput()->with('error'",
            $trialController,
        );
    }
}
