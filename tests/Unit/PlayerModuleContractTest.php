<?php

declare (strict_types=1);
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
final class PlayerModuleContractTest extends TestCase
{
    public function testPlayerBrowserRoutesUseOneCanonicalController(): void
    {
        $routes = file_get_contents(APPPATH . 'Modules/Players/Config/Routes.php');
        self::assertStringContainsString('$routes->get(\'(:num)\', \'\\\\App\\\\Modules\\\\Players\\\\Controllers\\\\Browser::showPage/$1\'', $routes);
        self::assertStringNotContainsString('Players::show', $routes);
        self::assertStringContainsString('Browser::canonical/$1', $routes);
    }
    public function testApprovedPlayerServiceBoundariesExist(): void
    {
        $source = file_get_contents(APPPATH . 'Services/PlayerService.php');
        foreach (['function list(', 'function find(', 'function updateProfile(', 'function changeStatus(', 'function addContact(',
    'player_status_history'] as $contract) {
            self::assertStringContainsString($contract, $source);
        }
        self::assertStringNotContainsString('password', strtolower($source));
    }
    public function testPlayerViewsUseStaffLayoutAndApprovedRelationshipTypes(): void
    {
        $index = file_get_contents(APPPATH . 'Modules/Players/Views/index.php');
        $show = file_get_contents(APPPATH . 'Modules/Players/Views/show.php');
        self::assertStringContainsString("view('staff_header'", $index);
        self::assertStringContainsString("view('staff_footer'", $show);
        self::assertStringContainsString('guardian', $show);
        self::assertStringContainsString('emergency-contact', $show);
        self::assertStringContainsString('mcc-player-photo-upload', $show);
    }

    public function testPlayerListStacksAndLabelsContactValues(): void
    {
        $index = file_get_contents(APPPATH . 'Modules/Players/Views/index.php');
        $styles = file_get_contents(FCPATH . 'assets/css/app.css');

        self::assertStringContainsString('mcc-player-contact-summary', $index);
        self::assertStringContainsString('mcc-player-contact-line', $index);
        self::assertStringContainsString('fa-envelope', $index);
        self::assertStringContainsString('fa-phone', $index);
        self::assertStringContainsString('mailto:', $index);
        self::assertStringContainsString('tel:', $index);
        self::assertStringContainsString('.mcc-player-contact-summary', $styles);
        self::assertStringContainsString('overflow-wrap: anywhere', $styles);
        self::assertStringContainsString('No players match the selected filters.', $index);
        self::assertStringContainsString('mcc-pagination', $index);
        self::assertStringContainsString('mcc-player-action-menu', $index);
        self::assertStringContainsString('name="branch_id"', $index);
        self::assertStringContainsString("if (\$page === '…')", $index);
        $browser = file_get_contents(APPPATH . 'Modules/Players/Controllers/Browser.php');
        self::assertStringContainsString('Support reference', $browser);
        self::assertStringContainsString("->setStatusCode(503)", $browser);
        self::assertStringContainsString("Players list failed", $browser);
    }

    public function testPlayerFilterDropdownsUseResponsiveThreeColumnGrid(): void
    {
        $index = file_get_contents(APPPATH . 'Modules/Players/Views/index.php');
        $styles = file_get_contents(FCPATH . 'assets/css/app.css');

        self::assertStringContainsString('mcc-player-filter-grid', $index);
        self::assertSame(6, substr_count($index, 'col-12 col-md-6 col-lg-4 mb-2 mcc-player-filter-field'));
        self::assertStringContainsString('.mcc-player-filter-grid', $styles);
        self::assertStringContainsString('.mcc-player-filter-field > select', $styles);
        self::assertStringContainsString('min-width: 0', $styles);
        self::assertStringContainsString('text-overflow: ellipsis', $styles);
    }

    public function testPlayerListUsesRequestedColumnsStatusesAndSharedPagination(): void
    {
        $index = file_get_contents(APPPATH . 'Modules/Players/Views/index.php');
        $service = file_get_contents(APPPATH . 'Services/PlayerService.php');

        foreach (['Player name', 'Player ID / reference', 'Branch', 'Batch', 'Age group', 'Assigned coaches', 'Status', 'Actions'] as $heading) {
            self::assertStringContainsString($heading, $index);
        }

        foreach (['mcc-player-status-active', 'mcc-player-status-inactive', 'mcc-player-status-suspended', 'mcc-player-status-left_academy', 'mcc-player-status-alumni'] as $class) {
            self::assertStringContainsString($class, $index);
        }

        self::assertStringContainsString('mcc_follow_up_page_window', $index);
        self::assertStringContainsString("a.admission_number AS player_reference", $service);
        self::assertStringContainsString("'status_counts' => \$statusCounts", $service);
        self::assertStringContainsString("'batch_id' => \$this->request->getGet('batch_id')", file_get_contents(APPPATH . 'Modules/Players/Controllers/Browser.php'));
    }

    public function testPlayerStatusBoardMatchesAdmissionSummaryStructure(): void
    {
        $index = file_get_contents(APPPATH . 'Modules/Players/Views/index.php');
        $styles = file_get_contents(FCPATH . 'assets/css/app.css');

        foreach (['mcc-admission-status-summary', 'mcc-follow-up-summary-grid', 'mcc-admission-status-summary-grid', 'mcc-follow-up-summary-card', 'mcc-follow-up-summary-label', 'mcc-follow-up-summary-count', 'is-active'] as $class) {
            self::assertStringContainsString($class, $index);
        }

        self::assertStringContainsString("unset(\$summaryFilters['status'])", $index);
        self::assertStringContainsString("['status' => \$status]", $index);
        self::assertStringContainsString('.mcc-follow-up-summary-grid', $styles);
        self::assertStringContainsString('.mcc-follow-up-summary-card', $styles);
        self::assertStringContainsString('.mcc-follow-up-summary-count', $styles);
        self::assertStringNotContainsString('mcc-status-summary-card', $index);
    }

    public function testPlayerActionsMenuUsesOnlySupportedActionsAndSafeIdPropagation(): void
    {
        $index = file_get_contents(APPPATH . 'Modules/Players/Views/index.php');
        $script = file_get_contents(FCPATH . 'assets/js/app.js');
        $styles = file_get_contents(FCPATH . 'assets/css/app.css');

        foreach (['view', 'profile', 'status', 'branch', 'batch', 'coaches', 'contacts', '360'] as $action) {
            self::assertStringContainsString('data-player-action="' . $action . '"', $index);
        }

        self::assertSame(1, substr_count($index, 'data-player-action="coaches"'));
        self::assertStringNotContainsString('data-player-action="coach"', $index);
        self::assertStringNotContainsString('Assign coach', $index);

        self::assertStringContainsString("site_url('players/' . \$playerId)", $index);
        self::assertStringContainsString("filter_var(\$player['id'] ?? null", $index);
        self::assertStringContainsString("fetch(baseUrl + '/' + playerId", $script);
        self::assertStringContainsString("fetch(player360BaseUrl + '/' + trigger.getAttribute('data-player-id')", $script);
        self::assertStringContainsString("event.key === 'ArrowDown'", $script);
        self::assertStringContainsString("event.key === 'ArrowUp'", $script);
        self::assertStringContainsString('max-height: min(70vh, 26rem)', $styles);
        self::assertStringContainsString('overflow-y: auto', $styles);
        foreach (['attendance', 'sessions', 'performance', 'documents', 'admission', 'personal-coach'] as $unsupportedAction) {
            self::assertStringNotContainsString(
                'data-player-action="' . $unsupportedAction . '"',
                $index,
            );
        }
        self::assertStringNotContainsString('players/0', $index);
        self::assertStringNotContainsString('players/null', strtolower($index));
        self::assertStringNotContainsString('admission_number', strtolower($script));
    }

    public function testPlayerModalFormsAreUniqueAndNormalizeOptionalAssignmentDates(): void
    {
        $show = file_get_contents(APPPATH . 'Modules/Players/Views/show.php');
        $browser = file_get_contents(APPPATH . 'Modules/Players/Controllers/Browser.php');
        $api = file_get_contents(APPPATH . 'Modules/Players/Controllers/Players.php');
        $service = file_get_contents(APPPATH . 'Services/PlayerService.php');
        $script = file_get_contents(FCPATH . 'assets/js/app.js');

        foreach (['profile', 'status', 'contacts', 'branch', 'batch', 'coaches'] as $form) {
            self::assertStringContainsString('id="player-' . $form . '-form-', $show);
        }

        self::assertStringNotContainsString('id="player-coach-form-', $show);
        self::assertStringNotContainsString("/coach'", $show);

        self::assertStringContainsString(
            '$effectiveTo = trim((string) ($d[\'effective_to\'] ?? \'\'))',
            $browser,
        );
        self::assertStringContainsString('$effectiveTo === \'\' ? null : $effectiveTo', $browser);
        self::assertStringContainsString(
            '$effectiveTo = trim((string) ($data[\'effective_to\'] ?? \'\'))',
            $api,
        );
        self::assertStringContainsString('$effectiveTo === \'\' ? null : $effectiveTo', $api);
        self::assertStringContainsString("var expectedUrl = baseUrl + '/' + playerId + '/' + expectedAction;", $script);
        self::assertStringContainsString('candidateUrl === expectedUrl', $script);
        self::assertStringContainsString("fragment.id = 'player-modal-' + action + '-form-' + playerId;", $script);
        self::assertStringContainsString('input[name="csrf_test_name"], input[name="csrf_token"]', $script);
        self::assertStringContainsString('assignmentBatchOptions', $service);
        self::assertStringContainsString("->where('is_active', 1)", $service);
        self::assertStringContainsString('csrf_field()', $show);
    }

    public function testPlayerCoachCorrectionUsesDedicatedFormAndAuthorizedCoachFields(): void
    {
        $show = file_get_contents(APPPATH . 'Modules/Players/Views/show.php');
        $service = file_get_contents(APPPATH . 'Services/PlayerService.php');
        $routes = file_get_contents(APPPATH . 'Modules/Players/Config/Routes.php');

        self::assertStringContainsString('data-player-action="coaches"', file_get_contents(APPPATH . 'Modules/Players/Views/index.php'));
        self::assertStringContainsString('action="<?= esc(site_url(\'players/\' . (int) $player[\'id\'] . \'/coaches\')) ?>"', $show);
        self::assertStringContainsString('name="group_coach_id"', $show);
        self::assertStringContainsString('name="personal_coach_id"', $show);
        self::assertSame(3, substr_count($show, 'class="form-control selectpicker btSelect"'));
        self::assertStringContainsString('name="batch_id"', $show);
        self::assertStringContainsString('current_batch_id', $show);
        self::assertStringContainsString('name="correction_reason"', $show);
        self::assertStringContainsString('name="expected_profile_revision"', $show);
        self::assertStringContainsString('data-player-coach-form', $show);
        self::assertStringContainsString('csrf_field()', $show);
        self::assertStringContainsString('group_coach_name', $service);
        self::assertStringContainsString('personal_coach_name', $service);
        self::assertStringNotContainsString("'admission_coach_name'", $service);
        self::assertStringNotContainsString("'admission_personal_coach_name'", $service);
        self::assertStringContainsString('r.name IN (\'Coach\', \'Fitness Coach\')', $service);
        self::assertStringContainsString('player_coach_assignments_changed', $service);
        self::assertStringContainsString('validatePlayerCoaches', $service);
        self::assertStringNotContainsString('Group Coach and Personal Coach must be different.', $service);
        self::assertStringContainsString('array_unique(array_filter($coachIds))', $service);
        self::assertStringContainsString("\$routes->post('(:num)/coaches'", $routes);
        self::assertStringNotContainsString("\$routes->post('(:num)/coach'", $routes);
    }

    public function testPlayerCoachModalShowsSuccessAndReloadsAfterSave(): void
    {
        $controller = file_get_contents(APPPATH . 'Modules/Players/Controllers/Browser.php');
        $script = file_get_contents(FCPATH . 'assets/js/app.js');

        self::assertStringContainsString("form.matches('[data-player-coach-form]')", $script);
        self::assertStringContainsString('Player coaches updated successfully.', $script);
        self::assertStringContainsString("'mcc-player-coach-success'", $script);
        self::assertStringContainsString('window.location.reload();', $script);
        self::assertStringContainsString("'message' => $msg", $controller);
        self::assertStringContainsString('$this->expectsJson()', $controller);
    }

    public function testPlayerBatchAssignmentIsIndependentOfProgramAndUsesCanonicalBatchOptions(): void
    {
        $service = file_get_contents(APPPATH . 'Services/PlayerService.php');
        $show = file_get_contents(APPPATH . 'Modules/Players/Views/show.php');

        self::assertStringContainsString('assignmentBatchOptions', $service);
        self::assertStringContainsString("->where('is_active', 1)", $service);
        self::assertStringContainsString("->table('batches')", $service);
        self::assertStringNotContainsString(
            '->where(\'branch_id\', $branchId)',
            $service,
        );
        self::assertStringNotContainsString("where('program_id'", $service);
        self::assertStringNotContainsString('data-program-id', $show);
        self::assertStringContainsString('class="form-control selectpicker btSelect" id="batch-id"', $show);
        self::assertStringContainsString('historical', $show);
    }

    public function testPlayerDetailUsesSharedStructureCanonicalReferenceAndPermissionAwareActions(): void
    {
        $show = file_get_contents(APPPATH . 'Modules/Players/Views/show.php');
        $browser = file_get_contents(APPPATH . 'Modules/Players/Controllers/Browser.php');
        $styles = file_get_contents(FCPATH . 'assets/css/app.css');

        foreach ([
            'mcc-detail-hero',
            'mcc-player-summary-grid',
            'mcc-detail-grid',
            'mcc-card-heading',
            'mcc-detail-action-menu',
            'mcc-detail-action-dropdown',
            'mcc-table-wrap',
            'mcc-empty-state',
        ] as $class) {
            self::assertStringContainsString($class, $show);
        }

        self::assertStringContainsString("\$player['admission_number']", $show);
        self::assertStringContainsString('Admission reference', $show);
        self::assertStringContainsString("\$player['group_coach_name']", $show);
        self::assertStringContainsString("\$player['personal_coach_name']", $show);
        self::assertStringContainsString('aria-label="Group Coach"', $show);
        self::assertStringContainsString('aria-label="Personal Coach"', $show);
        self::assertStringNotContainsString("\$player['admission_coach_name']", $show);
        self::assertStringNotContainsString("\$player['admission_personal_coach_name']", $show);
        self::assertStringNotContainsString('<dt>Group Coach</dt>', $show);
        self::assertStringNotContainsString('<dt>Personal Coach</dt>', $show);
        self::assertStringContainsString("\$currentBatch['batch_name']", $show);
        self::assertStringContainsString('mcc-player-status-', $show);
        self::assertStringNotContainsString("'Lead #'", $show);
        self::assertStringNotContainsString("? '#' . (int) \$player['trial_id']", $show);

        foreach ([
            'View player',
            'Edit player',
            'Update Coaches',
            'Assign Branch',
            'Assign Batch',
            'Documents',
            'View Admission',
        ] as $action) {
            self::assertStringContainsString($action, $show);
        }

        self::assertStringContainsString("site_url('players/' . (int) \$player['id'])", $show);
        self::assertStringContainsString("site_url('admissions/' . (int) \$player['admission_id'] . '/page')", $show);
        self::assertStringContainsString("site_url('documents?player_id=' . (int) \$player['id'])", $show);
        self::assertStringContainsString("'can_documents' => service('authorization')->can('document.view', \$branch)", $browser);
        self::assertStringContainsString('.mcc-detail-action-dropdown', $styles);
        self::assertStringContainsString('z-index: 1200', $styles);
        self::assertStringContainsString('mcc-player-placement-table', $show);
    }
}
