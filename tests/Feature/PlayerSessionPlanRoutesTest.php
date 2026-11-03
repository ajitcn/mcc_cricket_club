<?php

declare(strict_types=1);

namespace Tests\Feature;

use CodeIgniter\Test\CIUnitTestCase;

final class PlayerSessionPlanRoutesTest extends CIUnitTestCase
{
    public function testPlayerSessionPlanRoutesAndViewsExposeTheCoachWorkflow(): void
    {
        $routes = file_get_contents(APPPATH . 'Modules/Players/Config/Routes.php');
        $controller = file_get_contents(APPPATH . 'Modules/Players/Controllers/Browser.php');
        $list = file_get_contents(APPPATH . 'Modules/Players/Views/session_plans.php');
        $form = file_get_contents(APPPATH . 'Modules/Players/Views/session_plan_form.php');
        $selector = file_get_contents(APPPATH . 'Modules/Players/Views/session_plan_player_selector.php');
        $detail = file_get_contents(APPPATH . 'Modules/Players/Views/session_plan_show.php');
        $navigation = file_get_contents(APPPATH . 'Services/NavigationService.php');

        foreach ([
            "player-session-plans",
            "sessionPlanCreatePage",
            "sessionPlanUpdate",
            "permission:player.session_plan.manage",
            "permission:player.session_plan.view",
            "data-filter-toggle",
            "mcc-table-responsive",
            "mcc-empty-state",
            "mcc-pagination",
            "data-mcc-date-picker",
            "data-mcc-date-picker-no-toggle",
            "data-mcc-date-submit-format=\"Y-m-d\"",
            "mcc_user_date_input",
            "mcc-detail-action-menu",
            "data-player-actions-toggle",
            "aria-haspopup=\"true\"",
            "View Plan",
            "Edit Plan",
            "csrf_field()",
            "expected_version",
            "Previous versions",
            "Create Session Plan",
            "player_id",
        ] as $contract) {
            self::assertStringContainsString($contract, $routes . $controller . $list . $form . $detail . $selector . $navigation);
        }

        self::assertStringContainsString("(:num)/session-plans/new", $routes);
        self::assertStringContainsString("\$routes->get('new'", $routes);
        self::assertStringContainsString("site_url('player-session-plans/new')", $list);
        self::assertStringContainsString('data-feedback-player-search-mode="session-plan"', $selector);
        self::assertStringContainsString('player-session-plans/player-search', $selector);
        self::assertStringContainsString('mcc-readonly-field', $form);
        self::assertStringNotContainsString('type="date"', $list);
        self::assertStringNotContainsString('mcc-badge-info">Version', $detail);
        self::assertStringNotContainsString('data-label="Version"', $detail);
    }
}
