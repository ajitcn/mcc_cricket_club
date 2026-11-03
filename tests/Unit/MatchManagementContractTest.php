<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class MatchManagementContractTest extends TestCase
{
    public function testMatchModuleUsesPersistedSelectionAndExplicitAuthorization(): void
    {
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Matches/Config/Routes.php');
        $service = file_get_contents(ROOTPATH . 'app/Services/MatchManagementService.php');

        self::assertIsString($routes);
        self::assertIsString($service);
        self::assertStringContainsString('permission:match.view', $routes);
        self::assertStringContainsString('Playing XI must be selected from squad.', $service);
        self::assertStringContainsString('match_players', $service);
        self::assertStringContainsString('tournament_fixtures', $service);
    }

    public function testMatchReadsAndJoinsAreBranchScoped(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Services/MatchManagementService.php');

        self::assertIsString($service);
        self::assertStringContainsString(
            <<<'CODE'
g.id = m.ground_id AND g.branch_id = m.branch_id
CODE,
            $service
        );
        self::assertStringContainsString(
            <<<'CODE'
p.id = mp.player_id AND p.branch_id = ' . $branch
CODE,
            $service
        );
        self::assertStringContainsString("'match.view'", $service);
        self::assertStringContainsString("->table('branches')", $service);
        self::assertStringNotContainsString("session('authorizedBranches')", $service);
    }

    public function testMatchWritesUseLocksTransactionsAndAtomicAudit(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Services/MatchManagementService.php');

        self::assertIsString($service);
        self::assertStringContainsString(
            <<<'CODE'
$db->transStart();
CODE,
            $service
        );
        self::assertStringContainsString(
            <<<'CODE'
'SELECT GET_LOCK(?, 5) AS acquired'
CODE,
            $service
        );
        self::assertStringContainsString(
            <<<'CODE'
$this->replaceSelection($db, $matchId, $branch, $input);
CODE,
            $service
        );
        self::assertStringContainsString(
            <<<'CODE'
$db
            );
CODE,
            $service
        );
        self::assertStringContainsString(
            <<<'CODE'
$db->table('match_players')->where('match_id', $matchId)->delete();
CODE,
            $service
        );
    }

    public function testMatchConflictChecksIncludeGroundLineageAndCoachResources(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Services/MatchManagementService.php');

        self::assertIsString($service);
        self::assertStringContainsString(
            <<<'CODE'
'ground_id' => $groundId,
CODE,
            $service
        );
        self::assertStringContainsString(
            <<<'CODE'
'branch_id' => $branch,
CODE,
            $service
        );
        self::assertStringContainsString(
            <<<'CODE'
'coach_user_id' => $coach,
CODE,
            $service
        );
        self::assertStringContainsString(
            <<<'CODE'
'Coach scheduling conflict.'
CODE,
            $service
        );
    }

    public function testMatchMediumWorkflowsAreBranchScopedAndAuditable(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Services/MatchManagementService.php');
        $form = file_get_contents(ROOTPATH . 'app/Modules/Matches/Views/form.php');
        $list = file_get_contents(ROOTPATH . 'app/Modules/Matches/Views/index.php');
        $detail = file_get_contents(ROOTPATH . 'app/Modules/Matches/Views/show.php');

        self::assertIsString($service);
        self::assertIsString($form);
        self::assertIsString($list);
        self::assertIsString($detail);
        self::assertStringContainsString("'al.entity_type' => 'match'", $service);
        self::assertStringContainsString("'al.branch_id' => ", $service);
        self::assertStringContainsString('data-match-submit-form', $form);
        self::assertStringContainsString('data-mcc-date-picker', $list);
        self::assertStringContainsString('mcc-pagination', $list);
        self::assertStringContainsString('Match history', $detail);
    }

    public function testMatchSaveReturnsFieldErrorsAndPreservesFormInput(): void
    {
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Matches/Controllers/Browser.php');
        $form = file_get_contents(ROOTPATH . 'app/Modules/Matches/Views/form.php');

        self::assertIsString($controller);
        self::assertIsString($form);
        self::assertStringContainsString("with('field_errors'", $controller);
        self::assertStringContainsString('matchFieldErrors', $controller);
        self::assertStringContainsString("'Invalid opponent.'", $controller);
        self::assertStringContainsString("'Invalid match window.'", $controller);
        self::assertStringContainsString("old('opponent_id'", $form);
        self::assertStringContainsString('name="your_team_opponent_id"', $form);
        self::assertStringContainsString('name="your_team_opponent_id" required', $form);
        self::assertStringContainsString('Select opponent', $form);
        self::assertStringContainsString('No active opponents are available for this branch.', $form);
        self::assertStringContainsString('name="match_category"', $form);
        self::assertStringContainsString('Tournament', $form);
        self::assertStringContainsString("'match_category'", $service);
        self::assertStringContainsString("old('starts_date'", $form);
        self::assertStringContainsString('mcc-field-error', $form);
    }

    public function testMatchListUsesSharedToolbarResponsiveTableAndScopedActions(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Services/MatchManagementService.php');
        $view = file_get_contents(ROOTPATH . 'app/Modules/Matches/Views/index.php');
        $styles = file_get_contents(ROOTPATH . 'public/assets/css/app.css');

        self::assertIsString($service);
        self::assertIsString($view);
        self::assertIsString($styles);
        self::assertStringContainsString("u.display_name AS coach_name", $service);
        self::assertStringContainsString('tf.match_number', $service);
        self::assertStringContainsString("match.update", $service);
        self::assertStringContainsString('mcc-page-actions', $view);
        self::assertStringContainsString('mcc-match-actions', $view);
        self::assertStringContainsString('mcc-empty-state', $view);
        self::assertStringContainsString('Match #', $view);
        self::assertStringContainsString('mcc-match-list', $styles);
        self::assertStringContainsString('overflow-x: auto', $styles);
    }

    public function testMatchFormUsesSeparateLocalDateTimeAndPlayerMultiSelects(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Services/MatchManagementService.php');
        $form = file_get_contents(ROOTPATH . 'app/Modules/Matches/Views/form.php');
        $script = file_get_contents(ROOTPATH . 'public/assets/js/app.js');

        self::assertStringContainsString('starts_date', $form);
        self::assertStringContainsString('starts_time', $form);
        self::assertStringContainsString('ends_date', $form);
        self::assertStringContainsString('ends_time', $form);
        self::assertStringNotContainsString('name="starts_at"', $form);
        self::assertStringContainsString('selectpicker', $form);
        self::assertStringContainsString('btSelect', $form);
        self::assertStringContainsString('data-bt-select', $form);
        self::assertStringContainsString('data-match-xi', $form);
        self::assertStringContainsString('selectedSquad', $form);
        self::assertStringContainsString('localInputToUtc', $service);
        self::assertStringContainsString("'Invalid match ' . \$label . ' date/time.'", $service);
        self::assertStringContainsString("/^(\\d{4})-(\\d{2})-(\\d{2})$/", $service);
        self::assertStringContainsString('data-match-xi', $script);
        self::assertStringContainsString('function initBtSelect', $script);
        self::assertStringContainsString('refreshBtSelects', $script);
    }

    public function testMatchDetailUsesScopedActionsReadablePlayersAndSafeHistoryMarkup(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Services/MatchManagementService.php');
        $view = file_get_contents(ROOTPATH . 'app/Modules/Matches/Views/show.php');
        $styles = file_get_contents(ROOTPATH . 'public/assets/css/app.css');

        self::assertStringContainsString("\$match['can_update']", $service);
        self::assertStringContainsString('mcc-detail-actions', $view);
        self::assertStringContainsString('mcc-match-players-table', $view);
        self::assertStringContainsString('mcc-match-history-table', $view);
        self::assertStringContainsString('Previous values', $view);
        self::assertStringContainsString('New values', $view);
        self::assertStringNotContainsString('<pre>', $view);
        self::assertStringContainsString('mcc-match-metadata-list', $view);
        self::assertStringContainsString('detailProjection', $service);
        self::assertStringContainsString('displayAuditMetadata', $service);
        self::assertStringContainsString('performance_players', $service);
        foreach (['Branch Id', 'Opponent Id', 'Coach User Id', 'Ground Id', 'Program Id', 'Batch Id', 'Player Id'] as $rawLabel) {
            self::assertStringNotContainsString($rawLabel, $view);
        }
        self::assertStringContainsString('mcc-match-detail', $styles);
        self::assertStringContainsString('overflow-wrap: anywhere', $styles);
    }

    public function testMatchCompletionWorkflowIsScopedAuditedAndIdempotent(): void
    {
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Matches/Config/Routes.php');
        $service = file_get_contents(ROOTPATH . 'app/Services/MatchManagementService.php');
        $view = file_get_contents(ROOTPATH . 'app/Modules/Matches/Views/show.php');

        self::assertStringContainsString("(:num)/complete", $routes);
        self::assertStringContainsString("permission:match.update", $routes);
        self::assertStringContainsString('function complete(int $id)', $service);
        self::assertStringContainsString("'match_completed'", $service);
        self::assertStringContainsString("if ((string) \$existing['status'] === 'completed')", $service);
        self::assertStringContainsString('Complete match', $view);
        self::assertStringContainsString('csrf_field()', $view);
        self::assertStringContainsString("\$status === 'scheduled'", $view);
    }

    public function testMatchPlayerPerformanceUsesScopedSourceFieldsAndSafeForm(): void
    {
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Matches/Config/Routes.php');
        $service = file_get_contents(ROOTPATH . 'app/Services/MatchPlayerPerformanceService.php');
        $view = file_get_contents(ROOTPATH . 'app/Modules/Matches/Views/performance.php');
        $migration = file_get_contents(ROOTPATH . 'app/Database/Migrations/20261003000300_CreateMatchPlayerPerformances.php');
        $versionMigration = file_get_contents(ROOTPATH . 'app/Database/Migrations/20261011000400_AddMatchPlayerPerformanceVersion.php');

        self::assertStringContainsString("(:num)/performance", $routes);
        self::assertStringContainsString("'match.update'", $service);
        self::assertStringContainsString("'performance.update'", $service);
        self::assertStringContainsString("match_player_performances", $service);
        self::assertStringContainsString("Did not bat Players cannot have batting totals.", $service);
        self::assertStringContainsString("Boundary runs cannot exceed batting runs.", $service);
        self::assertStringContainsString('Enter legal balls as a whole number greater than or equal to zero.', $service);
        self::assertStringContainsString('Maidens cannot exceed legal balls.', $service);
        self::assertStringContainsString("csrf_field()", $view);
        self::assertStringContainsString('players[<?= $playerId ?>]', $view);
        self::assertStringContainsString('Legal balls bowled', $view);
        self::assertStringContainsString('Overs bowled:', $view);
        self::assertStringContainsString('mcc-performance-unit-input', $view);
        self::assertStringContainsString('data-mcc-help-toggle', $view);
        self::assertStringContainsString('aria-haspopup="tooltip"', $view);
        self::assertStringContainsString('aria-expanded="false"', $view);
        self::assertStringContainsString('Wides and no-balls are not counted.', $view);
        self::assertStringContainsString('Example: 18 legal balls = 3 overs.', $view);
        self::assertStringContainsString("match_player_performances", $migration);
        self::assertStringContainsString("innings_number", $migration);
        self::assertStringContainsString("'version'", $versionMigration);
        self::assertStringContainsString('Stale Match Player Performance submission.', $service);
        self::assertStringContainsString('A performance version is required for corrections.', $service);
        self::assertStringContainsString('name="players[<?= $playerId ?>][version]"', $view);
    }

    public function testMatchPlayerPerformanceCalculationsUseNotAvailableForZeroDenominators(): void
    {
        $service = new \App\Services\MatchPlayerPerformanceService();
        $metrics = $service->metrics([
            'runs' => 20,
            'balls_faced' => 0,
            'fours' => 2,
            'sixes' => 1,
            'legal_balls' => 0,
            'wickets' => 0,
            'dismissal_type' => 'not_out',
        ]);

        self::assertNull($metrics['batting_average']);
        self::assertNull($metrics['strike_rate']);
        self::assertSame(3, $metrics['boundary_count']);
        self::assertSame(14, $metrics['boundary_runs']);
        self::assertSame('0.0', $metrics['overs']);
        self::assertNull($metrics['economy']);
        self::assertNull($metrics['bowling_average']);
        self::assertNull($metrics['bowling_strike_rate']);
        self::assertNull($metrics['dot_balls']);

        self::assertSame('1.3', $service->metrics(['legal_balls' => 9])['overs']);
        self::assertSame('1.4', $service->metrics(['legal_balls' => 10])['overs']);
        self::assertSame('2.0', $service->metrics(['legal_balls' => 12])['overs']);
    }

    public function testMatchPlayerPerformancePageUsesAlignedPlayerCardsAndFieldingEmptyState(): void
    {
        $view = file_get_contents(ROOTPATH . 'app/Modules/Matches/Views/performance.php');
        $styles = file_get_contents(ROOTPATH . 'public/assets/css/app.css');

        self::assertStringContainsString('Back to match', $view);
        self::assertStringContainsString('Batting', $view);
        self::assertStringContainsString('Bowling', $view);
        self::assertStringContainsString('Fielding', $view);
        self::assertStringContainsString('fielding_status', $view);
        self::assertStringContainsString('run_out_assists', $view);
        self::assertStringContainsString('Total dismissals:', $view);
        self::assertStringContainsString('Total contributions:', $view);
        self::assertStringContainsString('fielding_status', $service = file_get_contents(ROOTPATH . 'app/Services/MatchPlayerPerformanceService.php'));
        self::assertStringContainsString('Did not field Players cannot have fielding totals.', $service);
        self::assertStringContainsString('fielding_contributions', $service);
        self::assertStringContainsString('Boundary runs:', $view);
        self::assertStringContainsString('Bowling calculations', $view);
        self::assertStringContainsString('Correction reason', $view);
        self::assertStringContainsString('correction_reason', $view);
        self::assertStringContainsString('Explain what was corrected and why.', $view);
        self::assertStringContainsString('required aria-describedby="correction-reason-help-', $view);
        self::assertStringContainsString("'correction_reason' => 'Explain what was corrected and why.'", file_get_contents(ROOTPATH . 'app/Modules/Matches/Controllers/Browser.php'));
        self::assertStringContainsString('mcc-match-performance', $view);
        self::assertStringContainsString('mcc-detail-grid', $view);
        self::assertStringContainsString('Performance history', $view);
        self::assertStringContainsString('mcc-performance-metrics', $styles);
        self::assertStringContainsString('mcc-performance-accordion-item', $view);
        self::assertStringContainsString('mcc-performance-accordion-list', $view);
        self::assertStringContainsString('aria-expanded="false"', $view);
        self::assertStringContainsString('class="mcc-performance-accordion-panel" hidden', $view);
        self::assertStringNotContainsString("\$index === 0 ? 'true' : 'false'", $view);
        self::assertStringContainsString('mcc-performance-accordion-trigger', $view);
        self::assertStringContainsString('aria-controls', $view);
        self::assertStringContainsString('mcc-performance-accordion-panel', $view);
        self::assertStringContainsString('Save Player performance', $view);
        self::assertStringContainsString("/performance/' . \$playerId", $view);
        self::assertStringContainsString('savePlayer', $service = file_get_contents(ROOTPATH . 'app/Services/MatchPlayerPerformanceService.php'));
        self::assertStringContainsString('mcc-performance-fielding', $view);
        self::assertStringContainsString('mcc-performance-accordion-panel > .mcc-detail-grid', $styles);
        self::assertStringContainsString('grid-template-columns: repeat(3, minmax(0, 1fr));', $styles);
        self::assertStringContainsString('grid-template-columns: minmax(0, 1fr);', $styles);
        self::assertStringContainsString('initPerformanceAccordion', file_get_contents(ROOTPATH . 'public/assets/js/app.js'));
        self::assertStringContainsString('mcc-performance-accordion-panel', $styles);
        self::assertStringContainsString('.mcc-performance-accordion-list {', $styles);
        self::assertStringContainsString('display: block;', $styles);
        self::assertStringContainsString('padding: 0;', $styles);
        self::assertStringContainsString('padding: .35rem .5rem;', $styles);
        self::assertStringContainsString('padding: .3rem;', $styles);
        self::assertStringContainsString('margin-bottom: 4px;', $styles);
        self::assertStringContainsString('.mcc-performance-accordion-list .mcc-performance-accordion-item {', $styles);
        self::assertStringContainsString('overflow-wrap: anywhere', $styles);
        self::assertStringNotContainsString('(Asia/Kolkata)', $view);
        self::assertStringNotContainsString('<pre>', $view);
    }
}
