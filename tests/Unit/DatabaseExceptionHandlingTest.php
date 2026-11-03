<?php

declare(strict_types=1);

namespace Tests\Unit;

use CodeIgniter\Database\Exceptions\DatabaseException;
use PHPUnit\Framework\TestCase;

final class DatabaseExceptionHandlingTest extends TestCase
{
    public function testDatabaseExceptionUsesTheCodeIgniterDatabaseExitCode(): void
    {
        self::assertTrue(defined('EXIT_DATABASE'));
        self::assertSame(8, (new DatabaseException('database failure'))->getExitCode());
    }

    public function testProjectErrorViewsDoNotRenderExceptionDetails(): void
    {
        $developmentView = file_get_contents(APPPATH . 'Views/errors/html/error_exception.php');
        $productionView = file_get_contents(APPPATH . 'Views/errors/html/production.php');

        self::assertStringContainsString('ERR-', $developmentView);
        self::assertStringContainsString('ERR-', $productionView);
        self::assertStringNotContainsString('<?= esc($message) ?>', $developmentView);
        self::assertStringNotContainsString('<?= esc($message) ?>', $productionView);
        self::assertStringContainsString('Development error details', $developmentView);
        self::assertStringContainsString('<?= esc((string) ($message ?? \'\')) ?>', $developmentView);
    }

    public function testPortalSchemaFailureProducesTheReportedReference(): void
    {
        $reference = 'ERR-' . strtoupper(substr(hash('sha256', implode('|', [
            'CodeIgniter\\Database\\Exceptions\\DatabaseException',
            'D:\\xampp-8.2\\htdocs\\cricket-club\\vendor\\codeigniter4\\framework\\system\\Database\\BaseConnection.php',
            '863',
            "Table 'mcc_cricket_club_test.portal_accounts' doesn't exist",
        ])), 0, 8));

        self::assertSame('ERR-FC642243', $reference);
    }

    public function testDevelopmentSecurityDiagnosticsExposeTheCsrfFailureLocation(): void
    {
        $security = (string) file_get_contents(
            ROOTPATH . 'app/Config/Security.php'
        );
        $errorView = (string) file_get_contents(
            APPPATH . 'Views/errors/html/error_exception.php'
        );

        self::assertStringContainsString(
            'public bool $regenerate = ENVIRONMENT === \'production\';',
            $security,
        );
        self::assertStringContainsString('Development error details', $errorView);
        self::assertStringContainsString('<?= esc((string) ($file ?? \'\')) ?>', $errorView);
    }
}
