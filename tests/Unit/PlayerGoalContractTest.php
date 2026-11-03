<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class PlayerGoalContractTest extends TestCase
{
    public function testGoalWorkflowHasPermissionCsrfConcurrencyAndEscapedDetailContracts(): void
    {
        $routes = file_get_contents(APPPATH . 'Modules/Players/Config/Routes.php');
        $service = file_get_contents(APPPATH . 'Services/PlayerService.php');
        $navigation = file_get_contents(APPPATH . 'Services/NavigationService.php');
        $goals = file_get_contents(APPPATH . 'Modules/Players/Views/goals.php');
        $goalCreate = file_get_contents(APPPATH . 'Modules/Players/Views/goal_create.php');
        $goalShow = file_get_contents(APPPATH . 'Modules/Players/Views/goal_show.php');
        $goalEdit = file_get_contents(APPPATH . 'Modules/Players/Views/goal_edit.php');
        $show = file_get_contents(APPPATH . 'Modules/Players/Views/show.php');
        $index = file_get_contents(APPPATH . 'Modules/Players/Views/index.php');
        $browser = file_get_contents(APPPATH . 'Modules/Players/Controllers/Browser.php');
        $script = file_get_contents(FCPATH . 'assets/js/app.js');
        $styles = file_get_contents(FCPATH . 'assets/css/app.css');

        self::assertStringContainsString("(:num)/goal", $routes);
        self::assertStringContainsString('player.goal.set', $service);
        self::assertStringContainsString('player_coach_assignments', $service);
        self::assertStringContainsString('expected_version', $service);
        self::assertStringContainsString('player_goal_created', $service);
        self::assertStringContainsString('player_goal_updated', $service);
        self::assertStringContainsString('csrf_field()', $show);
        self::assertStringNotContainsString('data-player-action="goal"', $index);
        self::assertStringContainsString('Goal Title', $show);
        self::assertStringContainsString('Goal Description', $show);
        self::assertStringContainsString('nl2br(esc(', $show);
        self::assertStringContainsString("goal: 'goal'", $script);
        self::assertStringContainsString('data-player-goal-cancel', $script);
        self::assertStringContainsString("Accept: 'application/json'", $script);
        self::assertStringContainsString('window.location.reload()', $script);
        self::assertStringContainsString('private function goalActor', $service);
        self::assertStringNotContainsString('assignmentActor(\n', substr($service, strpos($service, 'private function goalActor')));
        self::assertStringNotContainsString('Player assignment', $show);

        $goalController = substr($browser, strpos($browser, 'public function goal('));
        self::assertNotFalse($goalController);
        self::assertStringContainsString('expectsJson()', $goalController);
        self::assertStringNotContainsString('assignmentFail', substr($goalController, 0, strpos($goalController, 'public function canonical(')));
        self::assertStringContainsString("group('player-goals'", $routes);
        self::assertStringContainsString("goalCreatePage", $routes);
        self::assertStringContainsString("goalPlayerSearch", $routes);
        self::assertStringContainsString("goalCreate'", $routes);
        self::assertStringContainsString('permission:player.goal.set', $routes);
        self::assertStringContainsString('goalEditSave', $routes);
        self::assertStringContainsString("->to('/player-goals')", $browser);
        self::assertStringContainsString("with('message', 'Player goal saved.')", $browser);
        self::assertStringContainsString('withInput()', $browser);
        self::assertStringContainsString("'player.goal.set', 'Goals', 'player-goals'", $navigation);
        foreach (['Player name or ID', 'Branch', 'Coach', 'Goal title', 'created_from', 'updated_to', 'mcc-pagination', 'View Goal', 'Edit Goal'] as $contract) {
            self::assertStringContainsString($contract, $goals);
        }
        self::assertStringContainsString('mcc-goal-table-group', $goals);
        self::assertStringContainsString('mcc-goal-table-value', $goals);
        self::assertStringContainsString('mcc-detail-action-menu', $goals);
        self::assertStringContainsString('mcc-goal-timeline', $goals);
        self::assertStringContainsString('Updated ', $goals);
        self::assertStringContainsString("'player.goal.set'", $goals);
        self::assertStringContainsString('Create New Goal', $goals);
        self::assertStringContainsString("site_url('player-goals/new')", $goals);
        self::assertStringContainsString("'breadcrumbTrail'", $goals);
        self::assertStringContainsString('hidden>', $goals);
        self::assertStringContainsString('mcc-player-goals-table', $goals);
        foreach (['<th scope="col">Player ID</th>', '<th scope="col">Branch</th>', '<th scope="col">Created</th>', '<th scope="col">Updated</th>'] as $standaloneColumn) {
            self::assertStringNotContainsString($standaloneColumn, $goals);
        }
        self::assertStringContainsString('nl2br(esc(', $goalShow);
        self::assertStringContainsString('expected_version', $goalEdit);
        self::assertStringContainsString("site_url('player-goals/'", $goalEdit);
        self::assertStringContainsString('goalList', $service);
        self::assertStringContainsString('applyGoalActorScope', $service);
        self::assertStringContainsString('searchGoalPlayers', $service);
        self::assertStringContainsString('goalPlayerContext', $service);
        foreach ([
            'data-feedback-player-search',
            'data-feedback-player-search-mode="goal"',
            'data-feedback-player-search-input',
            'data-feedback-player-search-results',
            'data-feedback-player-search-clear',
            'data-goal-player-id',
            'data-goal-create-form',
            'name="title"',
            'name="description"',
            'Save Goal',
            'Choose an authorized Player',
        ] as $contract) {
            self::assertStringContainsString($contract, $goalCreate);
        }
        self::assertStringContainsString('Search player by name or ID', $goalCreate);
        self::assertStringNotContainsString('data-goal-player-search', $goalCreate);
        self::assertStringNotContainsString('<select', $goalCreate);
        self::assertStringContainsString('initFeedbackPlayerSearch', $script);
        self::assertStringContainsString('isGoalSearch', $script);
        self::assertStringContainsString('player-goals/player-search', $goalCreate);
        self::assertStringContainsString('encodeURIComponent(term)', $script);
        foreach (['ArrowDown', 'ArrowUp', 'scrollIntoView', 'aria-selected', 'aria-activedescendant', 'mouseenter', 'event.key === \'Enter\'', 'event.key === \'Escape\''] as $keyboardContract) {
            self::assertStringContainsString($keyboardContract, $script);
        }
        self::assertStringContainsString('.mcc-player-feedback-search-results', $styles);
        self::assertStringContainsString('z-index: 99999', $styles);
        self::assertStringContainsString('mcc-feedback-player-search-result.is-active', $styles);
        self::assertStringContainsString('background: var(--app-primary-soft', $styles);
        self::assertStringContainsString('color: var(--app-primary)', $styles);
        self::assertStringContainsString('aria-selected', $goalCreate . $script);
    }

    public function testGoalStorageMigrationIsAdditiveAndVersioned(): void
    {
        $migration = file_get_contents(
            APPPATH . 'Database/Migrations/20261026000800_CreatePlayerGoals.php',
        );

        foreach (['player_id', 'coach_user_id', 'title', 'description', 'version', 'created_at', 'updated_at'] as $field) {
            self::assertStringContainsString("'" . $field . "'", $migration);
        }

        self::assertStringContainsString("addUniqueKey('player_id')", $migration);
        self::assertStringContainsString('forward-only', $migration);
        self::assertStringNotContainsString('dropTable', $migration);
    }
}
