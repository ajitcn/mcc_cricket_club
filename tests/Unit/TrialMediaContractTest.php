<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class TrialMediaContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = dirname(__DIR__, 2);
    }

    public function testTrialMediaUsesPrivateStorageAndExplicitRoutes(): void
    {
        $service = file_get_contents($this->root . '/app/Services/TrialMediaService.php');
        $routes = file_get_contents($this->root . '/app/Modules/CRM/Config/Routes.php');
        $storage = file_get_contents($this->root . '/app/Libraries/Storage/LocalPrivateStorage.php');

        self::assertIsString($service);
        self::assertIsString($routes);
        self::assertIsString($storage);
        self::assertStringContainsString("WRITEPATH . 'private'", $service);
        self::assertStringContainsString("'trial-media/'", $service);
        self::assertStringContainsString("trials/(:num)/media", $routes);
        self::assertStringContainsString('downloadMedia/$1/$2', $routes);
        self::assertStringContainsString('previewMedia/$1/$2', $routes);
        self::assertStringContainsString('public web root', $storage);
    }

    public function testTrialMediaValidatesVideoUploadAndBranchAuthorization(): void
    {
        $service = file_get_contents($this->root . '/app/Services/TrialMediaService.php');
        $controller = file_get_contents($this->root . '/app/Modules/Trials/Controllers/Browser.php');
        $view = file_get_contents($this->root . '/app/Modules/Trials/Views/show.php');

        self::assertIsString($service);
        self::assertIsString($controller);
        self::assertIsString($view);
        self::assertStringContainsString("video/mp4", $service);
        self::assertStringContainsString("video/webm", $service);
        self::assertStringContainsString('getError() !== UPLOAD_ERR_OK', $service);
        self::assertStringContainsString('getClientExtension()', $service);
        self::assertStringContainsString("service('authorization')->can(\$permission", $service);
        self::assertStringContainsString("service('trialMedia')->upload", $controller);
        self::assertStringContainsString('enctype="multipart/form-data"', $view);
        self::assertStringContainsString('name="media"', $view);
    }

    public function testTrialMediaReadIsControlledAndAudited(): void
    {
        $service = file_get_contents($this->root . '/app/Services/TrialMediaService.php');
        $controller = file_get_contents($this->root . '/app/Modules/Trials/Controllers/Browser.php');
        $migration = file_get_contents($this->root . '/app/Database/Migrations/20261018000300_CreateTrialMedia.php');

        self::assertIsString($service);
        self::assertIsString($controller);
        self::assertIsString($migration);
        self::assertStringContainsString("'lead.view'", $service);
        self::assertStringContainsString("'lead.update'", $service);
        self::assertStringContainsString("'trial_media'", $migration);
        self::assertStringContainsString("service('auditPersistence')->record", $service);
        self::assertStringContainsString("->setStatusCode(404)", $controller);
        self::assertStringContainsString('transRollback', $service);
        self::assertStringContainsString('$storage->delete($key)', $service);
    }
}
