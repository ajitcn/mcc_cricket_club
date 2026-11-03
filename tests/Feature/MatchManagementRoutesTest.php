<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

final class MatchManagementRoutesTest extends TestCase
{
    public function testMatchWriteRoutesKeepAuthorizationAndControllerErrorHandling(): void
    {
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Matches/Config/Routes.php');
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Matches/Controllers/Browser.php');
        $form = file_get_contents(ROOTPATH . 'app/Modules/Matches/Views/form.php');

        self::assertIsString($routes);
        self::assertIsString($controller);
        self::assertIsString($form);
        self::assertStringContainsString("permission:match.create", $routes);
        self::assertStringContainsString("permission:match.update", $routes);
        self::assertStringContainsString("withInput()", $controller);
        self::assertStringContainsString("\$target = \$id === null ? '/matches/new' : '/matches/' . \$id . '/edit';", $controller);
        self::assertStringContainsString('redirect()->to($target)', $controller);
        self::assertStringContainsString("/matches?created_match=' . \$saved", $controller);
        self::assertStringContainsString("with('field_errors'", $controller);
        self::assertStringContainsString('csrf_field()', $form);
        $script = file_get_contents(ROOTPATH . 'public/assets/js/app.js');
        self::assertStringContainsString("parsed.querySelector('.mcc-alert-danger, .alert-danger')", $script);
    }

    public function testMatchListRendersScopedActionLinksAndResponsiveMarkup(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Services/MatchManagementService.php');
        $view = file_get_contents(ROOTPATH . 'app/Modules/Matches/Views/index.php');

        self::assertStringContainsString("can_update", $service);
        self::assertStringContainsString("matches/' . (int)", $view);
        self::assertStringContainsString('mcc-table-wrap', $view);
        self::assertStringContainsString('mcc-pagination', $view);
        self::assertStringContainsString('colspan="8"', $view);
    }

    public function testMatchFormKeepsCsrfAndDependentPlayerMarkup(): void
    {
        $view = file_get_contents(ROOTPATH . 'app/Modules/Matches/Views/form.php');

        self::assertStringContainsString('csrf_field()', $view);
        self::assertStringContainsString('data-match-branch-option', $view);
        self::assertStringContainsString('data-player-id', $view);
        self::assertStringContainsString('Player #', $view);
        self::assertStringContainsString('No eligible Players are available.', $view);
        self::assertStringContainsString('btSelect', $view);
        self::assertStringContainsString('data-bt-select', $view);
        self::assertStringContainsString('data-match-xi', $view);
    }

    public function testMatchDetailRouteUsesSharedDetailMarkup(): void
    {
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Matches/Config/Routes.php');
        $view = file_get_contents(ROOTPATH . 'app/Modules/Matches/Views/show.php');

        self::assertStringContainsString("Browser::showPage/$1", $routes);
        self::assertStringContainsString('permission:match.view', $routes);
        self::assertStringContainsString('mcc-detail-actions', $view);
        self::assertStringContainsString('matches', $view);
        self::assertStringContainsString('mcc-empty-state', $view);
    }

    public function testMatchCompletionUsesPostRouteAndReturnsToDetail(): void
    {
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Matches/Config/Routes.php');
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Matches/Controllers/Browser.php');

        self::assertStringContainsString("post('(:num)/complete'", $routes);
        self::assertStringContainsString('function complete(int $id)', $controller);
        self::assertStringContainsString("redirect()->to('/matches/' . \$id)", $controller);
        self::assertStringContainsString("service('match')->complete(\$id)", $controller);
    }

    public function testMatchPlayerPerformanceRoutesKeepCsrfAndReturnToMatchContext(): void
    {
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Matches/Config/Routes.php');
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Matches/Controllers/Browser.php');
        $view = file_get_contents(ROOTPATH . 'app/Modules/Matches/Views/performance.php');

        self::assertStringContainsString("get('(:num)/performance'", $routes);
        self::assertStringContainsString("post('(:num)/performance'", $routes);
        self::assertStringContainsString('service(\'matchPlayerPerformance\')->save($id, $data)', $controller);
        self::assertStringContainsString("/matches/' . \$id . '/performance", $controller);
        self::assertStringContainsString('withInput()', $controller);
        self::assertStringContainsString('csrf_field()', $view);
        self::assertStringContainsString('correction_reason', $view);
        self::assertStringContainsString('Explain what was corrected and why.', $view);
        self::assertStringContainsString('mcc-field-error', $view);
        self::assertStringContainsString('mcc-performance-accordion-item', $view);
        self::assertStringContainsString('mcc-performance-accordion-list', $view);
        self::assertStringContainsString('aria-expanded="false"', $view);
        self::assertStringContainsString('class="mcc-performance-accordion-panel" hidden', $view);
        self::assertStringContainsString('aria-expanded', $view);
        self::assertStringContainsString('aria-controls', $view);
        self::assertStringContainsString('mcc-performance-accordion-panel', $view);
        self::assertStringContainsString('mcc-performance-fielding', $view);
        self::assertStringContainsString("post('(:num)/performance/(:num)'", $routes);
        self::assertStringContainsString('savePlayerPerformance', $controller);
        self::assertStringContainsString('Save Player performance', $view);
        self::assertStringContainsString('Stale Match Player Performance submission.', $controller);
        self::assertStringContainsString('Reload the latest performance data before correcting this record.', $controller);
        self::assertStringContainsString('players[<?= $playerId ?>][version]', $view);
        self::assertStringContainsString('Batting', $view);
        self::assertStringContainsString('data-mcc-help-toggle', $view);
        self::assertStringContainsString('aria-haspopup="tooltip"', $view);
        self::assertStringContainsString('Bowling', $view);
        self::assertStringContainsString('Fielding', $view);
    }
}
