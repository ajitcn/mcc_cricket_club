<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class CoachManagementModuleContractTest extends TestCase
{
    public function testCoachRoutesUseExplicitAuthorization(): void
    {
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Coaches/Config/Routes.php');

        self::assertIsString($routes);
        self::assertStringContainsString("permission:coach.view", $routes);
        self::assertStringContainsString("permission:coach.create", $routes);
        self::assertStringContainsString("permission:coach.update", $routes);
        self::assertStringContainsString("'coaches'", $routes);
    }

    public function testCoachSelectorPatternRequiresAnEnabledCoachProfile(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Services/CoachManagementService.php');
        $development = file_get_contents(ROOTPATH . 'app/Services/PlayerDevelopmentService.php');

        self::assertStringContainsString("'u.is_enabled' => 1", $development);
        self::assertStringContainsString("'cp.is_active' => 1", $development);
        self::assertStringContainsString("'r.name' => 'Coach'", $development);
        self::assertStringContainsString("->where('r.name', self::ROLE)", $service);
    }

    public function testCoachImplementationReusesExistingAssignmentsAndMarksUnavailableKpis(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Services/CoachManagementService.php');
        $view = file_get_contents(ROOTPATH . 'app/Modules/Coaches/Views/show.php');

        self::assertIsString($service);
        self::assertIsString($view);
        self::assertStringContainsString("player_coach_assignments", $service);
        self::assertStringContainsString("sessions", $service);
        self::assertStringContainsString("coach_unavailability", $service);
        self::assertStringContainsString("coach_feedback", $service);
        self::assertStringContainsString("whereIn('s.status', ['scheduled', 'active', 'completed'])", $service);
        self::assertStringContainsString('Attendance roster snapshot required.', $service);
        self::assertStringContainsString('Attendance must be recorded before feedback.', $service);
        self::assertStringContainsString('Feedback version required.', $service);
        self::assertStringContainsString('expected_version', $service);
        self::assertStringContainsString('changed in another session', $service);
        self::assertStringContainsString("'performance_improvement_index' => service('performance', false)->coachImprovementIndex(\$userId)", $service);
        self::assertStringContainsString("Not yet available", $view);
    }

    public function testUserDirectedCoachScopeUsesTextExperienceAndBranchScopedKpis(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Services/CoachManagementService.php');
        $migration = file_get_contents(ROOTPATH . 'app/Database/Migrations/202609250001_ReconcileCoachAttendanceScope.php');

        self::assertIsString($service);
        self::assertIsString($migration);
        self::assertStringContainsString('strlen($experience) > 150', $service);
        self::assertStringContainsString("'coach.kpi.view'", $service);
        self::assertStringContainsString("'type' => 'VARCHAR'", $migration);
    }

    public function testCoachingNavigationUsesFunctionalRoutesAndExistingAssignmentFlows(): void
    {
        $navigation = file_get_contents(ROOTPATH . 'app/Services/NavigationService.php');
        $view = file_get_contents(ROOTPATH . 'app/Modules/Coaches/Views/index.php');

        self::assertIsString($navigation);
        self::assertIsString($view);
        self::assertStringContainsString("'coaches', 'coaches'", $navigation);
        self::assertStringNotContainsString("\$this->planned('Assignments'", $navigation);
        self::assertStringNotContainsString("\$this->planned('Coach KPIs'", $navigation);
        self::assertStringContainsString('data-coach-actions', $view);
        self::assertStringContainsString('data-coach-action="view"', $view);
        self::assertStringContainsString('data-coach-action="edit"', $view);
        self::assertStringContainsString('data-coach-modal', $view);
    }

    public function testCoachDirectionAddsBranchLeaveFeedbackAndSessionCorrectionRoutes(): void
    {
        $coachRoutes = file_get_contents(ROOTPATH . 'app/Modules/Coaches/Config/Routes.php');
        $sessionRoutes = file_get_contents(ROOTPATH . 'app/Modules/Scheduling/Config/Routes.php');
        $service = file_get_contents(ROOTPATH . 'app/Services/CoachManagementService.php');
        $scheduling = file_get_contents(ROOTPATH . 'app/Services/SchedulingService.php');

        self::assertStringContainsString("permission:coach.assign", $coachRoutes);
        self::assertStringContainsString("permission:coach.update", $coachRoutes);
        self::assertStringContainsString("/coach'", $sessionRoutes);
        self::assertStringContainsString('Overlapping leave is not allowed.', $service);
        self::assertStringContainsString('coach_branch_assigned', $service);
        self::assertStringContainsString('session_coach_reassigned', $scheduling);
    }

    public function testCoachAssignmentDetailsUseScopedQueriesAndSummaryCounts(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Services/CoachManagementService.php');

        self::assertStringContainsString('assignedPlayersCount', $service);
        self::assertStringContainsString('assignedSessionsCount', $service);
        self::assertStringContainsString('public function sessionsPage', $service);
        self::assertStringContainsString('public function sessionDetail', $service);
        self::assertStringContainsString('public function playersPage', $service);
        self::assertStringContainsString('whereIn(\'s.branch_id\', $branches)', $service);
        self::assertStringContainsString('attendance_roster_snapshots', $service);
        self::assertStringContainsString('attendance_recorded_count', $service);
        self::assertStringContainsString('whereIn(\'pca.branch_id\', $branches)', $service);
        self::assertStringContainsString('->where(\'pca.effective_to\', null)', $service);
        self::assertStringContainsString('\'pages\' => max(1, (int) ceil($total / $perPage))', $service);
    }

    public function testCoachDirectionContractKeepsBranchAndCorrectionGuards(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Services/CoachManagementService.php');
        $scheduling = file_get_contents(ROOTPATH . 'app/Services/SchedulingService.php');

        self::assertStringContainsString("\$this->authorize(\$branchId, 'coach.assign')", $service);
        self::assertStringContainsString("\$this->authorize(\$branchId, 'coach.update')", $service);
        self::assertStringContainsString("Overlapping leave is not allowed.", $service);
        self::assertStringContainsString("\$this->allow(\$branch, 'session.update')", $scheduling);
        self::assertStringContainsString("A reason is required after the session starts.", $scheduling);
    }

    public function testAvailabilityDirectionUsesForwardMigrationAndSchedulingPrecedence(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Services/CoachManagementService.php');
        $scheduling = file_get_contents(ROOTPATH . 'app/Services/SchedulingService.php');
        $migration = file_get_contents(ROOTPATH . 'app/Database/Migrations/20260925000300_AddCoachAvailability.php');

        self::assertStringContainsString('saveAvailability', $service);
        self::assertStringContainsString('Overlapping availability is not allowed.', $service);
        self::assertStringContainsString('coach_availability_cancelled', $service);
        self::assertStringContainsString('coach_availability', $scheduling);
        self::assertStringContainsString('Coach unavailable.', $scheduling);
        self::assertStringContainsString('Forward-only', $migration);
    }

    public function testLeaveApprovalDirectionUsesExplicitTransitionsAndForwardMetadataMigration(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Services/CoachManagementService.php');
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Coaches/Config/Routes.php');
        $migration = file_get_contents(ROOTPATH . 'app/Database/Migrations/20260925000400_AddCoachLeaveApproval.php');

        self::assertStringContainsString("'status' => 'PENDING'", $service);
        self::assertStringContainsString('approveLeave', $service);
        self::assertStringContainsString('rejectLeave', $service);
        self::assertStringContainsString('A rejection reason is required.', $service);
        self::assertStringContainsString('leave/(:num)/approve', $routes);
        self::assertStringContainsString('leave/(:num)/reject', $routes);
        self::assertStringContainsString('status_changed_by', $migration);
        self::assertStringContainsString('status_reason', $migration);
        self::assertStringContainsString('Forward-only', $migration);
    }
}
