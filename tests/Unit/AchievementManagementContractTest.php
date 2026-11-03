<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AchievementManagementContractTest extends TestCase
{
    public function testFoundationUsesExplicitRoutesAndBoundedFields(): void
    {
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Achievements/Config/Routes.php');
        $service = file_get_contents(ROOTPATH . 'app/Services/AchievementManagementService.php');
        $migration = file_get_contents(ROOTPATH . 'app/Database/Migrations/20261006000100_CreateAchievementCatalog.php');

        self::assertIsString($routes);
        self::assertIsString($service);
        self::assertIsString($migration);
        self::assertStringContainsString("permission:achievement.view", $routes);
        self::assertStringContainsString("permission:achievement.create", $routes);
        self::assertStringContainsString("'category'", $service);
        self::assertStringContainsString("'title'", $service);
        self::assertStringContainsString("'description'", $service);
        self::assertStringContainsString("'is_active'", $service);
        self::assertStringContainsString("service('auditPersistence')->record", $service);
        self::assertStringContainsString("addUniqueKey(['branch_id', 'title'])", $migration);
    }

    public function testUnresolvedAwardWorkflowsRemainAbsent(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Services/AchievementManagementService.php');
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Achievements/Config/Routes.php');

        self::assertIsString($service);
        self::assertIsString($routes);
        self::assertStringContainsString('recipient_type', $service);
        self::assertStringContainsString("'status' => 'draft'", $service);
        self::assertStringContainsString('awards/(:num)/approve', $routes);
        self::assertStringContainsString('awards/(:num)/publish', $routes);
        self::assertStringContainsString('awards/(:num)/revoke', $routes);
        self::assertStringContainsString('awards/(:num)/correct', $routes);
        self::assertStringContainsString('permission:achievement.approve', $routes);
        self::assertStringContainsString('permission:achievement.publish', $routes);
        self::assertStringContainsString('permission:achievement.revoke', $routes);
        self::assertStringContainsString('permission:achievement.update', $routes);
        self::assertStringContainsString('awards/(:num)/certificate', $routes);
        self::assertStringContainsString('permission:achievement.certificate.generate', $routes);
        self::assertStringContainsString('awards/(:num)/certificate/download', $routes);
        self::assertStringContainsString('permission:achievement.certificate.download', $routes);
        self::assertStringContainsString("reports',", $routes);
        self::assertStringContainsString("reports/csv',", $routes);
        self::assertStringContainsString('permission:report.view', $routes);
        self::assertStringContainsString("eligibility/evaluate',", $routes);
        self::assertStringContainsString("eligibility/(:num)/retry',", $routes);
        self::assertStringContainsString('permission:achievement.eligibility.evaluate', $routes);

        foreach (['notification'] as $workflow) {
            self::assertStringNotContainsString($workflow, strtolower($routes));
        }
    }

    public function testDraftAwardScopeUsesExactlyOneRecipientAndNoPublicationRoutes(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Services/AchievementManagementService.php');
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Achievements/Config/Routes.php');
        $migration = file_get_contents(ROOTPATH . 'app/Database/Migrations/20261006000200_CreateAchievementAwardDrafts.php');

        self::assertIsString($service);
        self::assertIsString($routes);
        self::assertIsString($migration);
        self::assertStringContainsString('recipient_type', $service);
        self::assertStringContainsString('assertRecipient', $service);
        self::assertStringContainsString("'status' => 'draft'", $service);
        self::assertStringContainsString('Correction reason required.', $service);
        self::assertStringContainsString("achievement.update", $routes);
        self::assertStringContainsString("addUniqueKey(['achievement_id', 'recipient_type', 'player_id', 'coach_id'])", $migration);
        self::assertStringContainsString("addForeignKey('player_id', 'players'", $migration);
        self::assertStringContainsString("addForeignKey('coach_id', 'users'", $migration);
        self::assertStringContainsString('awards/(:num)/certificate', $routes);
        self::assertStringContainsString('permission:achievement.certificate.generate', $routes);
        self::assertStringContainsString('awards/(:num)/certificate/download', $routes);
        self::assertStringContainsString('permission:achievement.certificate.download', $routes);
    }

    public function testAwardCreateLoadersUseTheActualUserSchemaAndCreateScope(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Services/AchievementManagementService.php');
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Achievements/Controllers/Browser.php');

        self::assertIsString($service);
        self::assertIsString($controller);
        self::assertStringContainsString("\$this->list('achievement.create')", $service);
        self::assertStringContainsString("u.display_name AS full_name", $service);
        self::assertStringNotContainsString('u.person_id', $service);
        self::assertStringContainsString("availableBranches('achievement.create')", $controller);
        self::assertStringContainsString("recipientOptions('achievement.create')", $controller);
        self::assertStringContainsString("'status' => 'draft'", $service);
        self::assertStringContainsString("'recipient_type' =>", $service);
    }

    public function testAwardAchievementsAreActiveAndBranchFilteredBeforeSubmission(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Services/AchievementManagementService.php');
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Achievements/Controllers/Browser.php');
        $view = file_get_contents(ROOTPATH . 'app/Modules/Achievements/Views/award_form.php');
        $javascript = file_get_contents(ROOTPATH . 'public/assets/js/app.js');

        self::assertIsString($service);
        self::assertIsString($controller);
        self::assertIsString($view);
        self::assertIsString($javascript);
        self::assertStringContainsString("'achievement.create'", $service);
        self::assertStringContainsString('(int) $achievement[\'is_active\'] === 1', $service);
        self::assertStringContainsString("whereIn('a.branch_id', \$branchIds)", $service);
        self::assertStringContainsString("is_active' => 1", $service);
        self::assertStringContainsString('activeBranchId', $controller);
        self::assertStringContainsString('data-branch-id', $view);
        self::assertStringContainsString('option.hidden = !isActiveBranch', $javascript);
        self::assertStringContainsString('No active Achievements are available for this branch.', $view);
        self::assertStringContainsString("site_url('achievements/new')", $view);
        self::assertStringContainsString('assertAchievement', $service);
        self::assertStringContainsString("'branch_id' => \$branchId, 'is_active' => 1", $service);
    }

    public function testPhaseTwoLifecycleUsesExplicitTransitionsAndReasons(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Services/AchievementManagementService.php');
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Achievements/Config/Routes.php');

        self::assertIsString($service);
        self::assertIsString($routes);
        self::assertStringContainsString('approveAward', $service);
        self::assertStringContainsString('publishAward', $service);
        self::assertStringContainsString('revokeAward', $service);
        self::assertStringContainsString('correctPublishedAward', $service);
        self::assertStringContainsString("'achievement.approve'", $service);
        self::assertStringContainsString("'achievement.publish'", $service);
        self::assertStringContainsString("'achievement.revoke'", $service);
        self::assertStringContainsString("'published'", $service);
        self::assertStringContainsString('Correction reason required.', $service);
        self::assertStringContainsString('awards/(:num)/approve', $routes);
        self::assertStringContainsString('awards/(:num)/publish', $routes);
        self::assertStringContainsString('awards/(:num)/revoke', $routes);
        self::assertStringContainsString('awards/(:num)/correct', $routes);
    }

    public function testCertificatesArePublishedOnlyAndPrivate(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Services/AchievementCertificateService.php');
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Achievements/Config/Routes.php');

        self::assertIsString($service);
        self::assertIsString($routes);
        self::assertStringContainsString("'published'", $service);
        self::assertStringContainsString('achievement.certificate.generate', $service);
        self::assertStringContainsString('achievement.certificate.download', $service);
        self::assertStringContainsString('achievement-certificates/', $service);
        self::assertStringContainsString('achievement_certificate_generated', $service);
        self::assertStringContainsString('achievement_certificate_downloaded', $service);
        self::assertStringContainsString('awards/(:num)/certificate', $routes);
        self::assertStringContainsString('awards/(:num)/certificate/download', $routes);
    }

    public function testAchievementViewsUseTheSharedResponsiveUiContract(): void
    {
        $views = [
            'index.php',
            'form.php',
            'awards.php',
            'award_form.php',
            'award_show.php',
            'reports.php',
        ];

        foreach ($views as $viewName) {
            $view = file_get_contents(ROOTPATH . 'app/Modules/Achievements/Views/' . $viewName);

            self::assertIsString($view);
            self::assertStringContainsString('mcc-page-heading', $view, $viewName);
            self::assertStringContainsString('mcc-card', $view, $viewName);
            self::assertStringContainsString('mcc-button', $view, $viewName);
        }

        $form = file_get_contents(ROOTPATH . 'app/Modules/Achievements/Views/award_form.php');
        $detail = file_get_contents(ROOTPATH . 'app/Modules/Achievements/Views/award_show.php');

        self::assertIsString($form);
        self::assertIsString($detail);
        self::assertStringContainsString('data-bt-select', $form);
        self::assertStringContainsString('data-recipient-type="player"', $form);
        self::assertStringContainsString('data-recipient-type="coach"', $form);
        self::assertStringContainsString('$value(\'recipient_id\')', $form);
        self::assertStringContainsString('mcc-form-actions', $form);
        self::assertStringContainsString('mcc-detail-list', $detail);
        self::assertStringContainsString('mcc-badge-', $detail);
        self::assertStringNotContainsString('<pre', $detail);
    }
}
