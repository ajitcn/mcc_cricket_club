<?php

declare(strict_types=1);

namespace Tests\Feature;

use CodeIgniter\Test\CIUnitTestCase;

final class PlayerFeedbackRoutesTest extends CIUnitTestCase
{
    public function testPlayerFeedbackRoutesAreExplicitAndPermissionProtected(): void
    {
        $routes = file_get_contents(APPPATH . 'Modules/Players/Config/Routes.php');
        $navigation = file_get_contents(APPPATH . 'Services/NavigationService.php');
        $controller = file_get_contents(APPPATH . 'Modules/Players/Controllers/Browser.php');
        $service = file_get_contents(APPPATH . 'Services/PlayerFeedbackService.php');
        $playerList = file_get_contents(APPPATH . 'Modules/Players/Views/index.php');
        $playerShow = file_get_contents(APPPATH . 'Modules/Players/Views/show.php');

        self::assertStringContainsString("player-feedback', ['filter' => 'auth']", $routes);
        self::assertStringContainsString("Browser::feedbackList", $routes);
        self::assertStringContainsString("Browser::feedbackCreateSelector", $routes);
        self::assertStringContainsString("Browser::feedbackPlayerSearch", $routes);
        self::assertStringContainsString("Browser::annualFeedbackPlayerSearch", $routes);
        self::assertStringContainsString("annual-player-search", $routes);
        self::assertStringContainsString("Browser::annualFeedback", $routes);
        self::assertStringContainsString('$routes->get(\'annual\'', $routes);
        self::assertStringContainsString("permission:player.feedback.view", $routes);
        self::assertStringContainsString("permission:player.feedback.manage", $routes);
        self::assertStringContainsString("'player.feedback.view', 'Coach Feedback'", $navigation);
        self::assertStringContainsString("service('playerFeedback')->canManagePlayer", $controller . $playerList . $playerShow);
        self::assertStringContainsString("service('playerFeedback')->canViewPlayer", $controller . $playerList . $playerShow);
        self::assertStringContainsString('Annual Feedback Report', $playerList . $playerShow);
        self::assertStringContainsString('searchCreateablePlayers', $controller);
        self::assertStringContainsString('searchReportPlayers', $controller . $service);
        self::assertStringContainsString('reportPlayerOptions', $controller . file_get_contents(APPPATH . 'Services/PlayerFeedbackService.php'));
        self::assertStringContainsString('feedback/new', file_get_contents(APPPATH . 'Modules/Players/Views/feedbacks.php'));
    }
}
