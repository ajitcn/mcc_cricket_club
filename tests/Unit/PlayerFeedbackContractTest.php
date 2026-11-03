<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class PlayerFeedbackContractTest extends TestCase
{
    public function testFeedbackStorageRoutesPermissionsAndHistoryContractExists(): void
    {
        $migration = file_get_contents(APPPATH . 'Database/Migrations/20261028000100_CreatePlayerFeedbacks.php');
        $service = file_get_contents(APPPATH . 'Services/PlayerFeedbackService.php');
        $routes = file_get_contents(APPPATH . 'Modules/Players/Config/Routes.php');
        $seeder = file_get_contents(APPPATH . 'Database/Seeds/AuthorizationRestoreSeeder.php');

        self::assertStringContainsString("player_feedbacks", $migration);
        self::assertStringContainsString("player_feedback_history", $migration);
        self::assertStringContainsString("addUniqueKey(['player_id', 'feedback_year', 'feedback_quarter'])", $migration);
        self::assertStringContainsString("player.feedback.view", $migration . $routes);
        self::assertStringContainsString("player.feedback.manage", $migration . $routes);
        self::assertStringContainsString("'player.feedback.view'", $seeder);
        self::assertStringContainsString("'player.feedback.manage'", $seeder);
        self::assertStringContainsString("Feedback was changed by another user.", $service);
        self::assertStringContainsString("Correction reason required.", $service);
        self::assertStringContainsString("auditPersistence", $service);
    }

    public function testFeedbackViewsExposeEscapedQuarterlyAndAnnualContracts(): void
    {
        $form = file_get_contents(APPPATH . 'Modules/Players/Views/feedback_form.php');
        $detail = file_get_contents(APPPATH . 'Modules/Players/Views/feedback_show.php');
        $selector = file_get_contents(APPPATH . 'Modules/Players/Views/feedback_player_selector.php');
        $list = file_get_contents(APPPATH . 'Modules/Players/Views/feedbacks.php');
        $annual = file_get_contents(APPPATH . 'Modules/Players/Views/feedback_annual.php');
        $playerList = file_get_contents(APPPATH . 'Modules/Players/Views/index.php');
        $playerShow = file_get_contents(APPPATH . 'Modules/Players/Views/show.php');
        $styles = file_get_contents(FCPATH . 'assets/css/app.css');
        $javascript = file_get_contents(FCPATH . 'assets/js/app.js');
        $service = file_get_contents(APPPATH . 'Services/PlayerFeedbackService.php');

        foreach (['student_parent_feedback', 'coach_feedback', 'next_focus_action', 'correction_reason'] as $field) {
            self::assertStringContainsString($field, $form);
        }
        foreach (['Player', 'Player ID', 'Branch', 'Coach', 'Feedback period', 'Created', 'Updated'] as $label) {
            self::assertStringContainsString($label, $detail);
        }
        foreach (['Student/Parent Feedback', 'Coach Feedback', 'Next Focus/Action'] as $heading) {
            self::assertStringContainsString($heading, $detail);
        }
        self::assertStringContainsString('mcc-player-feedback-summary-grid', $detail);
        self::assertStringContainsString('mcc-player-feedback-content', $detail);
        self::assertStringContainsString('nl2br(esc(', $detail);
        self::assertStringContainsString('Back to Feedback', $detail);
        self::assertStringContainsString('Edit Feedback', $detail);
        self::assertStringContainsString('data-feedback-player-search', $selector);
        self::assertStringContainsString('data-search-url', $selector);
        self::assertStringNotContainsString("'players' =>", $selector);
        self::assertStringContainsString('No feedback recorded', $annual);
        self::assertStringContainsString('data-feedback-player-search-mode="annual"', $annual);
        self::assertStringContainsString('annual-player-search', $annual);
        self::assertStringContainsString('data-feedback-player-search-id', $annual);
        self::assertStringContainsString('data-feedback-player-search-clear', $annual);
        self::assertStringContainsString('data-feedback-player-search-selected', $annual);
        self::assertStringContainsString('data-annual-feedback-form', $annual);
        self::assertStringContainsString('Generate Report', $annual);
        self::assertStringContainsString('No feedback recorded', $annual);
        self::assertStringNotContainsString('$playerOptions', $annual);
        self::assertStringContainsString('Annual Feedback Report', $playerShow . $list);
        self::assertStringContainsString('Add 3-Month Feedback', $playerShow);
        self::assertStringContainsString('Add Feedback', $list);
        self::assertStringNotContainsString('Add Feedback', $playerList);
        self::assertStringNotContainsString('Annual Feedback Report', $playerList);
        self::assertStringContainsString('players/\' . (int) $player[\'id\'] . \'/feedback/new', $playerShow);
        self::assertStringContainsString('player-feedback/annual?player_id=', $playerList . $playerShow);
        self::assertStringContainsString('player-feedback-filters', $list);
        self::assertStringContainsString("site_url('player-feedback/new')", $list);
        self::assertStringContainsString("site_url('player-feedback/annual')", $list);
        self::assertStringContainsString('name="player"', $list);
        self::assertStringContainsString('type="search"', $list);
        self::assertStringContainsString('Search by Player name or ID', $list);
        self::assertStringNotContainsString('name="player_id"', $list);
        self::assertStringContainsString('mcc-player-feedback-report-selector', $annual);
        self::assertStringContainsString('.mcc-player-feedback-page {', $styles);
        self::assertStringContainsString('gap: .75rem;', $styles);
        self::assertStringContainsString('padding: .7rem .75rem;', $styles);
        self::assertStringContainsString('margin-top: .75rem;', $styles);
        self::assertStringContainsString('mcc-player-feedback-report-player-field', $annual);
        self::assertStringContainsString('mcc-player-feedback-report-selector > .mcc-form-field', $styles);
        self::assertStringContainsString('min-height: calc(1.5em + .75rem + 2px);', $styles);
        self::assertStringContainsString('align-items: start;', $styles);
        self::assertStringContainsString('data-feedback-player-search-mode="annual"', $annual);
        self::assertStringContainsString('name="year"', $annual);
        self::assertStringContainsString('<select', $annual);
        self::assertStringContainsString('Generate Report', $annual);
        self::assertStringContainsString('mcc-player-feedback-report-summary', $annual);
        self::assertStringContainsString('No feedback recorded', $annual);
        self::assertStringContainsString('mcc-player-feedback-quarter-content', $annual);
        self::assertStringContainsString('data-player-feedback-action-menu', $list);
        self::assertStringContainsString('data-player-actions', $list);
        self::assertStringContainsString('data-player-actions-toggle', $list);
        self::assertStringContainsString('data-player-actions-menu', $list);
        self::assertStringContainsString('data-player-feedback-action-menu', $playerShow);
        self::assertStringContainsString('mcc-feedback-period-badge', $list);
        self::assertStringContainsString('No feedback recorded', $playerShow . $annual);
        self::assertStringContainsString('mcc-player-table-wrap', $styles);
        self::assertStringContainsString('overflow-x: auto;', $styles);
        self::assertStringContainsString('overflow-x: clip;', $styles);
        self::assertStringContainsString('min-width: 0;', $styles);
        self::assertStringContainsString('grid-template-columns: minmax(0, 1fr);', $styles);
        self::assertStringContainsString('mcc-player-feedback-detail-table-wrap', $styles);
        self::assertStringContainsString('mcc-detail-action-dropdown-portal', $styles);
        self::assertStringContainsString('z-index: 999999 !important;', $styles);
        self::assertStringContainsString('mcc-action-menu-overlay', $styles);
        self::assertStringContainsString('data-action-menu-overlay', $javascript);
        self::assertStringContainsString('document.body.appendChild(dropdown)', $javascript);
        self::assertStringContainsString('MutationObserver', $javascript);
        self::assertStringContainsString('data-player-feedback-action-menu', $javascript);
        self::assertStringContainsString('data-action-menu-placeholder', $javascript);
        self::assertStringContainsString('mcc-detail-action-dropdown-portal', $javascript);
        self::assertStringContainsString('spaceAbove', $javascript);
        self::assertStringContainsString('spaceBelow', $javascript);
        self::assertStringContainsString('preferredLeft', $javascript);
        self::assertStringContainsString('maxLeft', $javascript);
        self::assertStringContainsString('getBoundingClientRect()', $javascript);
        self::assertStringContainsString('maxTop', $javascript);
        self::assertStringContainsString('overflowY', $javascript);
        self::assertStringContainsString('initFeedbackPlayerSearch', $javascript);
        self::assertStringContainsString('fetch(url', $javascript);
        self::assertStringContainsString('->like(\'pe.full_name\', $playerSearch)', $service);
        self::assertStringContainsString('->orLike(\'pf.player_id\', $playerSearch)', $service);
        self::assertStringContainsString('Add Feedback', $list);
        self::assertStringNotContainsString('data-player-action="feedback"', $playerList);
        self::assertStringContainsString('esc(', $form . $list . $annual);
    }
}
