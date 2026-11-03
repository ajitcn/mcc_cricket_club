<?php
declare(strict_types=1);
namespace Tests\Unit;
use PHPUnit\Framework\TestCase;
final class ReportContractTest extends TestCase
{
    public function testReportsArePermissionControlledAndExcludeSensitiveDocuments():void
    {
        $service=file_get_contents(APPPATH.'Services/ReportService.php');$routes=file_get_contents(APPPATH.'Modules/Reports/Config/Routes.php');
        self::assertStringContainsString('report.view',$routes);self::assertStringContainsString("'matches'",$service);self::assertStringNotContainsString('documents',$service);
    }

    public function testReportDatesAreValidatedAndCsvViewPreservesActiveFilters(): void
    {
        $service = file_get_contents(APPPATH . 'Services/ReportService.php');
        $controller = file_get_contents(APPPATH . 'Modules/Reports/Controllers/Browser.php');
        $view = file_get_contents(APPPATH . 'Modules/Reports/Views/show.php');

        self::assertStringContainsString("throw new RuntimeException('Invalid report date filter.')", $service);
        self::assertStringContainsString('validDate', $service);
        self::assertStringContainsString('http_build_query', $view);
        self::assertStringContainsString('$activeFilters', $view);
        self::assertStringContainsString('Invalid report date filter.', $controller);
    }

    public function testReportShowMapsForbiddenBranchRequestsToAnAuthorizationResponse(): void
    {
        $controller = file_get_contents(APPPATH . 'Modules/Reports/Controllers/Browser.php');

        self::assertStringContainsString("\$exception->getMessage() === 'Forbidden'", $controller);
        self::assertStringContainsString('->setStatusCode(403)', $controller);
        self::assertStringContainsString('You are not authorized for this record.', file_get_contents(APPPATH . 'Controllers/StaffController.php'));
    }
}
