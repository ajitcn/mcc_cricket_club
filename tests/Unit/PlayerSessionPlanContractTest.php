<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class PlayerSessionPlanContractTest extends TestCase
{
    public function testSessionPlanStorageAndRoutesAreForwardOnlyAndPermissionProtected(): void
    {
        $migration = file_get_contents(APPPATH . 'Database/Migrations/20261027000100_CreatePlayerSessionPlans.php');
        $routes = file_get_contents(APPPATH . 'Modules/Players/Config/Routes.php');
        $service = file_get_contents(APPPATH . 'Services/PlayerSessionPlanService.php');
        $navigation = file_get_contents(APPPATH . 'Services/NavigationService.php');

        foreach ([
            'player_session_plans',
            'player_session_plan_history',
            'player_id',
            'coach_user_id',
            'branch_id',
            'version',
            'addUniqueKey([\'plan_id\', \'version\'])',
            'player.session_plan.view',
            'player.session_plan.manage',
            'Session Plan storage is forward-only',
        ] as $contract) {
            self::assertStringContainsString($contract, $migration . $routes . $service . $navigation);
        }

        self::assertStringContainsString('permission:player.session_plan.manage', $routes);
        self::assertStringContainsString('permission:player.session_plan.view', $routes);
        self::assertStringContainsString("'player.session_plan.view', 'Session Plans'", $navigation);
        self::assertStringNotContainsString("addUniqueKey('player_id')", $migration);
        self::assertStringNotContainsString('dropTable', $migration);
        self::assertStringContainsString('player_session_plan_history', $service);
        self::assertStringContainsString('transBegin()', $service);
        self::assertStringContainsString('transRollback()', $service);
        self::assertStringContainsString('affectedRows() !== 1', $service);
        self::assertStringContainsString('auditPersistence', $service);
    }

    public function testSessionPlanViewsExposeEscapedCoachFormAndHistoryContracts(): void
    {
        $form = file_get_contents(APPPATH . 'Modules/Players/Views/session_plan_form.php');
        $selector = file_get_contents(APPPATH . 'Modules/Players/Views/session_plan_player_selector.php');
        $list = file_get_contents(APPPATH . 'Modules/Players/Views/session_plans.php');
        $detail = file_get_contents(APPPATH . 'Modules/Players/Views/session_plan_show.php');
        $styles = file_get_contents(FCPATH . 'assets/css/app.css');
        $javascript = file_get_contents(FCPATH . 'assets/js/app.js');
        $service = file_get_contents(APPPATH . 'Services/PlayerSessionPlanService.php');
        $playerList = file_get_contents(APPPATH . 'Modules/Players/Views/index.php');
        $playerShow = file_get_contents(APPPATH . 'Modules/Players/Views/show.php');

        foreach ([
            'Problem',
            'Outcome',
            'Method',
            'Safety Considerations',
            'Level 1 Practice',
            'Level 2 Practice',
            'Debrief',
            'Coaching Tools',
            'Coaching Focus',
            'data-bt-select',
            'multiple',
            'csrf_field()',
            'expected_version',
            'mcc-pagination',
            'mcc-empty-state',
            'mcc-detail-action-menu',
            'esc(',
            'Previous versions',
            'Player and Plan Details',
            'Player ID',
            'Assigned Coach',
            'mcc-player-session-plan-form-card',
            'mcc-player-session-plan-form-section',
            'mcc-readonly-field',
            'mcc-player-session-plan-form-context',
            'data-session-plan-action-menu',
            'mcc-session-plan-table-group',
            'mcc-session-plan-primary-value',
            'mcc-detail-card',
        ] as $contract) {
            self::assertStringContainsString($contract, $form . $list . $detail . $styles);
        }

        self::assertStringContainsString('grid-template-columns: repeat(2, minmax(0, 1fr));', $styles);
        self::assertStringContainsString('grid-template-columns: 1fr;', $styles);

        self::assertStringContainsString('Session Plans', $playerShow);
        self::assertStringContainsString('can_manage_session_plans', $playerShow);
        self::assertStringContainsString('can_view_session_plans', $playerShow);
        self::assertSame(0, substr_count($playerList, 'Create Session Plan'));
        self::assertStringNotContainsString('site_url(\'players/\' . $playerId . \'/session-plans/new\')', $playerList);
        self::assertStringContainsString('Player ID <?= (int) ($plan[\'player_id\'] ?? 0) ?>', $list);
        self::assertStringContainsString('.mcc-player-action-dropdown a,', $styles);
        self::assertStringContainsString('.mcc-player-action-dropdown a:hover,', $styles);
        self::assertStringContainsString('mcc-player-session-plan-table-wrap', $styles);
        self::assertStringContainsString('mcc-table-responsive', $list);
        self::assertSame(
            2,
            preg_match_all(
                '/data-mcc-date-picker\s+data-mcc-date-picker-no-toggle/',
                $list,
            ),
        );
        self::assertSame(2, substr_count($list, 'data-mcc-date-submit-format="Y-m-d"'));
        self::assertStringContainsString('mcc_user_date_input', $list);
        self::assertStringNotContainsString('type="date"', $list);
        self::assertStringContainsString('table-layout: fixed;', $styles);
        self::assertStringContainsString('grid-template-columns: minmax(0, 1fr);', $styles);
        self::assertStringContainsString('overflow-x: clip;', $styles);
        self::assertStringContainsString('text-align: center;', $styles);
        self::assertStringContainsString('vertical-align: middle;', $styles);
        self::assertStringContainsString('overflow-y: hidden;', $styles);
        self::assertStringContainsString('mcc-player-session-plan-filter:has(.mcc-date-picker.is-open)', $styles);
        self::assertStringContainsString('z-index: 2300', $styles);
        self::assertStringNotContainsString('>Current</span>', $list);
        self::assertStringNotContainsString('>Version <?= $version ?></span>', $list);
        self::assertStringNotContainsString('mcc-badge-info">Version', $detail);
        self::assertStringNotContainsString('>Version <?= (int) ($entry[\'version\'] ?? 0) ?>', $detail);
        self::assertStringContainsString('aria-haspopup="true"', $list);
        self::assertStringContainsString('aria-controls="session-plan-actions-', $list);
        self::assertStringContainsString('data-filter-toggle', $list);
        self::assertStringContainsString('Create New Plan', $list);
        self::assertStringContainsString("site_url('player-session-plans/new')", $list);
        self::assertStringContainsString('mcc-player-session-plans-page .mcc-pagination', $styles);
        self::assertStringContainsString('z-index: 50000', $styles . $javascript);
        self::assertStringContainsString('initSessionPlanActionMenus', $javascript);
        self::assertStringContainsString('document.body.appendChild(dropdown)', $javascript);
        self::assertStringContainsString("dropdown.style.position = 'fixed'", $javascript);
        self::assertStringContainsString("event.key === 'Escape'", $javascript);
        self::assertStringContainsString('data-filter-toggle', $list);
        self::assertStringContainsString('$pageUrl', $list);
        self::assertStringNotContainsString('player-session-plans?player=', $playerList);
        self::assertStringNotContainsString('Player #', $list);
        self::assertStringContainsString('data-feedback-player-search', $selector);
        self::assertStringContainsString('data-feedback-player-search-mode="session-plan"', $selector);
        self::assertStringContainsString('player-session-plans/player-search', $selector);
        self::assertStringContainsString('Search player by name or ID', $selector);
        self::assertStringContainsString('/session-plans/new', $javascript);
        self::assertStringContainsString('searchCreateablePlayers', $service);
        self::assertStringNotContainsString('$player_options', $form);
        self::assertStringNotContainsString('Choose player', $form);
        self::assertStringNotContainsString('name="players"', $form);
        self::assertStringNotContainsString('id="session-plan-players"', $form);
        self::assertStringContainsString('name="coaching_focus[]"', $form);
        self::assertStringNotContainsString("'technical' => 'Technical'", $form);
        self::assertStringNotContainsString("'tactical' => 'Tactical'", $form);
        self::assertStringNotContainsString("'mental' => 'Mental'", $form);
        self::assertStringNotContainsString("'physical' => 'Physical'", $form);
        foreach (['Technical', 'Tactical', 'Mental', 'Physical'] as $focus) {
            self::assertStringContainsString($focus, $service);
        }
        foreach (['Instruct', 'Demonstrate', 'Observe', 'Question', 'Analyse', 'Feedback', 'Use Silence', 'Praise'] as $tool) {
            self::assertStringContainsString($tool, $service);
        }
        self::assertStringContainsString('name="player_id"', $form);
        self::assertStringContainsString('mcc-readonly-field', $form);
        self::assertStringContainsString('nl2br(esc(', $detail);
    }
}
