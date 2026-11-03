<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

final class AchievementAwardRoutesTest extends TestCase
{
    public function testDraftAwardRoutesAndFormsAreExplicitAndCsrfProtected(): void
    {
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Achievements/Config/Routes.php');
        $form = file_get_contents(ROOTPATH . 'app/Modules/Achievements/Views/award_form.php');

        self::assertIsString($routes);
        self::assertIsString($form);
        self::assertStringContainsString("awards/new", $routes);
        self::assertStringContainsString("awards/(:num)/edit", $routes);
        self::assertStringContainsString("permission:achievement.update", $routes);
        self::assertStringContainsString('csrf_field()', $form);
        self::assertStringContainsString('recipient_type', $form);
        self::assertStringContainsString('recipient_id', $form);
        self::assertStringContainsString('correction_reason', $form);
    }

    public function testAwardCreatePageRouteLoadsAuthorizedCreateFormData(): void
    {
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Achievements/Config/Routes.php');
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Achievements/Controllers/Browser.php');
        $service = file_get_contents(ROOTPATH . 'app/Services/AchievementManagementService.php');
        $form = file_get_contents(ROOTPATH . 'app/Modules/Achievements/Views/award_form.php');

        self::assertIsString($routes);
        self::assertIsString($controller);
        self::assertIsString($service);
        self::assertIsString($form);
        self::assertStringContainsString("\$routes->get('awards/new'", $routes);
        self::assertStringContainsString('permission:achievement.create', $routes);
        self::assertStringContainsString('awardCreatePage', $controller);
        self::assertStringContainsString("'achievements' =>", $controller);
        self::assertStringContainsString("'branches' =>", $controller);
        self::assertStringContainsString("'recipients' =>", $controller);
        self::assertStringContainsString("'players' =>", $service);
        self::assertStringContainsString("'coaches' =>", $service);
        self::assertStringContainsString('data-recipient-type="player"', $form);
        self::assertStringContainsString('data-recipient-type="coach"', $form);
        self::assertStringContainsString('csrf_field()', $form);
    }

    public function testAwardCreateFormUsesSeparateResponsiveBtSelectRecipientFields(): void
    {
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Achievements/Controllers/Browser.php');
        $view = file_get_contents(ROOTPATH . 'app/Modules/Achievements/Views/award_form.php');
        $javascript = file_get_contents(ROOTPATH . 'public/assets/js/app.js');

        self::assertIsString($controller);
        self::assertIsString($view);
        self::assertIsString($javascript);
        self::assertStringContainsString("'Create Achievement Award'", $controller);
        self::assertStringContainsString('data-achievement-award-form', $view);
        self::assertStringContainsString('data-award-achievement-selector', $view);
        self::assertStringContainsString('data-award-achievement-empty', $view);
        self::assertStringContainsString('data-branch-id', $view);
        self::assertStringContainsString('Create an Achievement', $view);
        self::assertStringContainsString('activeBranchId', $controller);
        self::assertStringContainsString('data-award-recipient-type', $view);
        self::assertSame(2, substr_count($view, 'data-award-recipient-selector'));
        self::assertSame(3, substr_count($view, 'data-bt-select'));
        self::assertStringContainsString('data-recipient-type="player"', $view);
        self::assertStringContainsString('data-recipient-type="coach"', $view);
        self::assertStringContainsString('Exactly one recipient is required.', $view);
        self::assertStringContainsString('Saved as Draft', $view);
        self::assertStringContainsString('Create Award', $view);
        self::assertStringContainsString('Cancel', $view);
        self::assertStringContainsString('initAchievementAwardForm', $javascript);
        self::assertStringContainsString('select.disabled = !isActiveType', $javascript);
        self::assertStringContainsString('syncAchievements', $javascript);
        self::assertStringContainsString('achievement.dispatchEvent(new Event(\'bt-select-refresh\'))', $javascript);
        self::assertStringNotContainsString('timezone', strtolower($view));
        self::assertStringNotContainsString('json_encode', $view);
    }

    public function testAchievementDropdownHasScopedStackingContract(): void
    {
        $stylesheet = file_get_contents(ROOTPATH . 'public/assets/css/app.css');

        self::assertIsString($stylesheet);
        self::assertStringContainsString('.mcc-achievement-award-form-page', $stylesheet);
        self::assertStringContainsString('.mcc-achievement-award-form .mcc-bt-select-menu', $stylesheet);
        self::assertStringContainsString('z-index: 1000', $stylesheet);
        self::assertStringContainsString('.mcc-achievement-award-form .mcc-form-field:has(#award-achievement)', $stylesheet);
        self::assertStringContainsString('z-index: 1001', $stylesheet);
        self::assertStringContainsString('overflow: visible', $stylesheet);
    }

    public function testLifecycleRoutesUseSeparatePermissionsAndCsrfForms(): void
    {
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Achievements/Config/Routes.php');
        $view = file_get_contents(ROOTPATH . 'app/Modules/Achievements/Views/award_show.php');

        self::assertIsString($routes);
        self::assertIsString($view);
        self::assertStringContainsString("permission:achievement.approve", $routes);
        self::assertStringContainsString("permission:achievement.publish", $routes);
        self::assertStringContainsString("permission:achievement.revoke", $routes);
        self::assertStringContainsString('awards/(:num)/correct', $routes);
        self::assertSame(8, substr_count($view, 'csrf_field()'));
        self::assertStringContainsString('Approval reason', $view);
        self::assertStringContainsString('Publication reason', $view);
        self::assertStringContainsString('Revocation reason', $view);
        self::assertStringContainsString('Correction reason', $view);
    }

    public function testCertificateRoutesArePermissionFilteredAndPublishedOnlyInTheView(): void
    {
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Achievements/Config/Routes.php');
        $view = file_get_contents(ROOTPATH . 'app/Modules/Achievements/Views/award_show.php');

        self::assertIsString($routes);
        self::assertIsString($view);
        self::assertStringContainsString('permission:achievement.certificate.generate', $routes);
        self::assertStringContainsString('permission:achievement.certificate.download', $routes);
        self::assertStringContainsString('Generate certificate', $view);
        self::assertStringContainsString('Download certificate', $view);
        self::assertStringContainsString('csrf_field()', $view);
    }

    public function testAwardsPageUsesReadableResponsiveAwardContract(): void
    {
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Achievements/Controllers/Browser.php');
        $service = file_get_contents(ROOTPATH . 'app/Services/AchievementManagementService.php');
        $view = file_get_contents(ROOTPATH . 'app/Modules/Achievements/Views/awards.php');

        self::assertIsString($controller);
        self::assertIsString($service);
        self::assertIsString($view);
        self::assertStringContainsString("'Achievement Awards'", $controller);
        self::assertStringContainsString('awardCategories', $controller);
        self::assertStringContainsString('array_slice', $controller);
        self::assertStringContainsString('a.category', $service);
        self::assertStringContainsString('groupStart()', $service);
        self::assertStringContainsString('Create Award', $view);
        self::assertStringContainsString('mcc-achievement-filter-card', $view);
        self::assertStringContainsString('mcc-achievement-awards-table', $view);
        self::assertStringContainsString('Achievement', $view);
        self::assertStringContainsString('Category', $view);
        self::assertStringContainsString('Recipient', $view);
        self::assertStringContainsString('Created', $view);
        self::assertStringContainsString('Draft', $view);
        self::assertStringContainsString('Archived', $view);
        self::assertStringContainsString('Edit draft', $view);
        self::assertStringContainsString('Approve', $view);
        self::assertStringContainsString('Publish', $view);
        self::assertStringContainsString('Revoke', $view);
        self::assertStringContainsString('Correct', $view);
        self::assertStringContainsString('Generate certificate', $view);
        self::assertStringContainsString('Download certificate', $view);
        self::assertStringContainsString('Restore', $view);
        self::assertStringContainsString('csrf_field()', $view);
        self::assertStringNotContainsString('<pre', $view);
        self::assertStringNotContainsString('json_encode', $view);
    }

    public function testAchievementReportsUseExistingReportPermissionAndPreserveCsvFilters(): void
    {
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Achievements/Config/Routes.php');
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Achievements/Controllers/Browser.php');
        $view = file_get_contents(ROOTPATH . 'app/Modules/Achievements/Views/reports.php');

        self::assertIsString($routes);
        self::assertIsString($controller);
        self::assertIsString($view);
        self::assertStringContainsString("reports',", $routes);
        self::assertStringContainsString("reports/csv',", $routes);
        self::assertSame(2, substr_count($routes, 'permission:report.view'));
        self::assertStringContainsString('service(\'achievementReport\')->export', $controller);
        self::assertStringContainsString('branch_id', $view);
        self::assertStringContainsString('player_id', $view);
        self::assertStringContainsString('coach_id', $view);
        self::assertStringContainsString('category', $view);
        self::assertStringContainsString('data-mcc-date-picker', $view);
        self::assertStringContainsString('Export CSV', $view);
        self::assertStringNotContainsString('nomination_note', $view);
        self::assertStringNotContainsString('lifecycle_reason', $view);
    }
}
