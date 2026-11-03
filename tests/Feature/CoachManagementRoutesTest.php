<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

final class CoachManagementRoutesTest extends TestCase
{
    public function testDirectionRoutesRemainPermissionProtected(): void
    {
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Coaches/Config/Routes.php');
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Coaches/Controllers/Browser.php');
        $sessionRoutes = file_get_contents(ROOTPATH . 'app/Modules/Scheduling/Config/Routes.php');
        $sessionController = file_get_contents(ROOTPATH . 'app/Modules/Scheduling/Controllers/Browser.php');

        self::assertStringContainsString("permission:coach.assign", $routes);
        self::assertStringContainsString("permission:coach.update", $routes);
        self::assertStringContainsString("/branches'", $routes);
        self::assertStringContainsString("/leave'", $routes);
        self::assertStringContainsString("leave/(:num)/approve", $routes);
        self::assertStringContainsString("leave/(:num)/reject", $routes);
        self::assertStringContainsString("/availability'", $routes);
        self::assertStringContainsString("/feedback'", $routes);
        self::assertStringContainsString("availability/(:num)/cancel", $routes);
        self::assertStringContainsString("permission:session.update", $sessionRoutes);
        self::assertStringContainsString("/coach'", $sessionRoutes);
        self::assertStringContainsString('service(\'coach\')->assignBranch', $controller);
        self::assertStringContainsString('service(\'coach\')->saveLeave', $controller);
        self::assertStringContainsString('service(\'coach\')->cancelLeave', $controller);
        self::assertStringContainsString('service(\'coach\')->approveLeave', $controller);
        self::assertStringContainsString('service(\'coach\')->rejectLeave', $controller);
        self::assertStringContainsString('service(\'coach\')->saveFeedback', $controller);
        self::assertStringContainsString('feedbackReturnTarget', $controller);
        self::assertStringContainsString("^/attendance/sessions/(\\d+)$", $controller);
        self::assertStringContainsString("^/sessions/(\\d+)/page$", $controller);
        self::assertStringContainsString('withInput()', $controller);
        self::assertStringContainsString('sessionFeedback', $sessionController);
        self::assertStringContainsString('service(\'scheduling\')->reassignCoach', $sessionController);
        self::assertStringContainsString("managementBranches('coach.assign')", $controller);
        self::assertStringContainsString("canManageBranch(", $controller);
    }

    public function testCoachAssignmentSummariesUseExplicitAuthorizedDetailRoutes(): void
    {
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Coaches/Config/Routes.php');
        $view = file_get_contents(ROOTPATH . 'app/Modules/Coaches/Views/show.php');
        $sessionsView = file_get_contents(ROOTPATH . 'app/Modules/Coaches/Views/sessions.php');
        $sessionDetailView = file_get_contents(ROOTPATH . 'app/Modules/Coaches/Views/session.php');
        $playersView = file_get_contents(ROOTPATH . 'app/Modules/Coaches/Views/players.php');

        self::assertStringContainsString("(:num)/sessions", $routes);
        self::assertStringContainsString("(:num)/sessions/(:num)", $routes);
        self::assertStringContainsString("(:num)/players", $routes);
        self::assertStringContainsString("filter' => 'permission:coach.view'", $routes);
        self::assertStringContainsString('assigned_players_count', $view);
        self::assertStringContainsString('assigned_sessions_count', $view);
        self::assertStringContainsString('mcc-detail-actions', $view);
        self::assertStringContainsString("'/players')", $view);
        self::assertStringContainsString("'/sessions')", $view);
        self::assertStringNotContainsString('$coach[\'players\']', $view);
        self::assertStringNotContainsString('$coach[\'sessions\']', $view);
        self::assertStringContainsString('mcc-pagination', $sessionsView);
        self::assertStringContainsString('attendance_recorded_count', $sessionsView);
        self::assertStringContainsString('mcc-detail-actions', $sessionsView);
        self::assertStringContainsString('mcc-detail-badges', $sessionsView);
        self::assertStringContainsString('program_name', $sessionsView);
        self::assertStringContainsString('coaches/' . "' . (int) \$coach['id'] . '/sessions/", $sessionsView);
        self::assertStringContainsString('Open attendance', $sessionDetailView);
        self::assertStringContainsString('View attendance', $sessionDetailView);
        self::assertStringContainsString('mcc-detail-actions', $sessionDetailView);
        self::assertStringContainsString('mcc-pagination', $playersView);
        self::assertStringContainsString('No sessions are assigned', $sessionsView);
        self::assertStringContainsString('No active players are assigned', $playersView);
    }

    public function testCoachDirectoryKeepsWideContentInsideContainedTableAndResponsiveFilters(): void
    {
        $view = file_get_contents(ROOTPATH . 'app/Modules/Coaches/Views/index.php');
        $styles = file_get_contents(ROOTPATH . 'public/assets/css/app.css');

        self::assertStringContainsString('mcc-coach-directory-card', $view);
        self::assertStringContainsString('mcc-table-wrap', $view);
        self::assertStringContainsString('.mcc-coach-directory-card .mcc-table-wrap', $styles);
        self::assertStringContainsString('overflow-x: auto', $styles);
        self::assertStringContainsString('.mcc-coach-page > .mcc-card', $styles);
        self::assertStringContainsString('overflow-wrap: anywhere', $styles);
        self::assertStringContainsString('.mcc-coach-filter-form {', $styles);
    }

    public function testWorkflowRoutesPreserveCsrfFormsAndFocusedServiceCalls(): void
    {
        $coachView = file_get_contents(ROOTPATH . 'app/Modules/Coaches/Views/show.php');
        $sessionView = file_get_contents(ROOTPATH . 'app/Modules/Scheduling/Views/show.php');
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Coaches/Controllers/Browser.php');

        self::assertStringContainsString('csrf_field()', $coachView);
        self::assertStringContainsString('csrf_field()', $sessionView);
        self::assertStringContainsString('name="branch_id"', $coachView);
        self::assertStringContainsString('name="unavailable_from"', $coachView);
        self::assertStringContainsString('name="session_id"', $coachView);
        self::assertStringContainsString('name="coach_user_id"', $sessionView);
        self::assertStringContainsString('name="reason"', $sessionView);
        self::assertStringContainsString('leave_permissions', $coachView);
        self::assertStringContainsString('leave_branches', $coachView);
        self::assertStringContainsString('Feedback history', $coachView);
        self::assertStringContainsString('name="feedback_id"', $coachView);
        self::assertStringContainsString('Correction reason', $coachView);
        self::assertStringContainsString('Feedback / remark', $sessionView);
        self::assertStringContainsString('Player Feedback / Remarks', $sessionView);
        self::assertStringContainsString('data-session-feedback-form', $sessionView);
        self::assertStringContainsString('data-session-feedback-version', $sessionView);
        self::assertStringContainsString('name="leave_id"', $coachView);
        self::assertStringContainsString('name="reason"', $coachView);
        self::assertStringContainsString('Approve', $coachView);
        self::assertStringContainsString('Reject', $coachView);
        self::assertStringContainsString('name="correction_reason"', $coachView);
        self::assertStringContainsString('Save correction', $coachView);
        self::assertStringContainsString('Availability', $coachView);
        self::assertStringContainsString('name="available_date"', $coachView);
        self::assertStringContainsString('name="start_time"', $coachView);
        self::assertStringContainsString('name="end_time"', $coachView);
        self::assertStringNotContainsString("service('authorization')->can('coach.assign')", $controller);
        self::assertStringNotContainsString("service('authorization')->can('coach.update')", $controller);
    }
}
