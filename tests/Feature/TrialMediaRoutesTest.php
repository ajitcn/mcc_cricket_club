<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

final class TrialMediaRoutesTest extends TestCase
{
    public function testTrialMediaRoutesUseTheExistingTrialPermissions(): void
    {
        $routes = file_get_contents(ROOTPATH . 'app/Modules/CRM/Config/Routes.php');
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Trials/Controllers/Browser.php');

        self::assertIsString($routes);
        self::assertIsString($controller);
        self::assertStringContainsString("trials/(:num)/media',", $routes);
        self::assertStringContainsString("permission:lead.update", $routes);
        self::assertStringContainsString("permission:lead.view", $routes);
        self::assertStringContainsString('function uploadMedia(int $id)', $controller);
        self::assertStringContainsString('function downloadMedia(int $trialId, int $mediaId)', $controller);
        self::assertStringContainsString('function previewMedia(int $trialId, int $mediaId)', $controller);
    }

    public function testTrialDetailKeepsMediaReadOnlyAndDoesNotExposeStorageKeys(): void
    {
        $view = file_get_contents(ROOTPATH . 'app/Modules/Trials/Views/show.php');

        self::assertIsString($view);
        self::assertStringContainsString('Trial media', $view);
        self::assertStringContainsString('Preview', $view);
        self::assertStringContainsString('Download', $view);
        self::assertStringContainsString('mcc-trial-media-actions', $view);
        self::assertStringContainsString('fa-eye', $view);
        self::assertStringContainsString('fa-download', $view);
        self::assertStringNotContainsString('storage_key', $view);
        self::assertStringNotContainsString('Delete media', $view);
    }
}
