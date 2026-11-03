<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class SchedulingGroupSessionUiContractTest extends TestCase
{
    public function testGroupSessionFormsExposeReferenceLayoutAndExistingDataFlow(): void
    {
        $create = file_get_contents(ROOTPATH . 'app/Modules/Scheduling/Views/create.php');
        $edit = file_get_contents(ROOTPATH . 'app/Modules/Scheduling/Views/edit.php');
        $detail = file_get_contents(ROOTPATH . 'app/Modules/Scheduling/Views/show.php');
        $attendance = file_get_contents(ROOTPATH . 'app/Modules/Attendance/Views/session.php');
        $script = file_get_contents(ROOTPATH . 'public/assets/js/app.js');
        $service = file_get_contents(ROOTPATH . 'app/Services/SchedulingService.php');
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Scheduling/Controllers/Browser.php');

        foreach ([$create, $edit, $detail, $attendance, $script, $service, $controller] as $source) {
            self::assertIsString($source);
        }

        self::assertStringContainsString('view(\'staff_header\'', $create);
        self::assertStringContainsString('mcc-session-edit-form', $edit);

        self::assertStringContainsString('mcc-session-create-form', $create);
        self::assertStringContainsString('mcc-session-create-grid', $create);
        foreach ([$create, $edit] as $form) {
            self::assertStringContainsString('name="branch_id"', $form);
            self::assertStringContainsString('name="program_id"', $form);
            self::assertStringContainsString('name="batch_id"', $form);
            self::assertStringContainsString('name="coach_user_id"', $form);
            self::assertStringContainsString('name="ground_id"', $form);
            if ($form === $create) {
                self::assertStringContainsString('name="starts_at"', $form);
                self::assertStringContainsString('name="starts_time"', $form);
                self::assertStringContainsString('name="ends_time"', $form);
                self::assertStringNotContainsString('name="ends_at"', $form);
            } else {
                self::assertStringContainsString('name="starts_at"', $form);
                self::assertStringContainsString('name="starts_time"', $form);
                self::assertStringContainsString('name="ends_time"', $form);
                self::assertStringNotContainsString('name="ends_at"', $form);
            }
            self::assertStringContainsString('name="player_ids[]"', $form);
            self::assertStringContainsString('data-bt-select', $form);
            self::assertStringContainsString('data-bt-select-placeholder="Choose players"', $form);
            self::assertStringContainsString('data-mcc-date-picker', $form);
            self::assertStringNotContainsString('data-session-player-details', $form);
            self::assertStringContainsString('csrf_field()', $form);
            self::assertStringContainsString('mcc-form-actions', $form);
        }

        self::assertStringContainsString('Branch', $detail);
        self::assertStringContainsString('Coach', $detail);
        self::assertStringContainsString('Batch', $detail);
        self::assertStringContainsString('Ground', $detail);
        self::assertStringNotContainsString('Selected Players', $detail);
        self::assertStringContainsString('Attendance summary', $detail);
        self::assertStringContainsString('$attendancePlayers', $detail);
        self::assertStringContainsString('Open attendance', $detail);
        self::assertStringNotContainsString('Attendance status for', $detail);
        self::assertStringNotContainsString('Attendance status', $detail);
        self::assertStringContainsString('name="status"', $detail);
        self::assertStringContainsString('mcc-attendance-session-table', $attendance);
        self::assertStringContainsString('data-attendance-bulk-form', $attendance);
        self::assertStringContainsString('Save Attendance', $attendance);
        self::assertStringNotContainsString('Attendance status for', $attendance);
        self::assertStringNotContainsString('Attendance status', $attendance);
        self::assertStringContainsString('data-attendance-player', $attendance);
        self::assertStringContainsString('full_name', $attendance);
        self::assertStringContainsString('attendance-status-select', $attendance);
        self::assertStringNotContainsString('Record attendance', $attendance);
        self::assertStringNotContainsString('Submit correction', $attendance);
        $tableStart = strpos($attendance, 'mcc-attendance-session-table');
        $tableEnd = $tableStart === false
            ? false
            : strpos($attendance, '</table>', $tableStart);
        $helperPosition = strpos($attendance, 'Choose one status for each selected Player.');
        $savePosition = strpos($attendance, 'Save Attendance');
        self::assertIsInt($tableEnd);
        self::assertIsInt($helperPosition);
        self::assertIsInt($savePosition);
        self::assertLessThan($helperPosition, $tableEnd);
        self::assertLessThan($savePosition, $helperPosition);
        self::assertSame(1, substr_count($attendance, 'Save Attendance'));
        $styles = file_get_contents(ROOTPATH . 'public/assets/css/app.css');
        self::assertIsString($styles);
        self::assertStringContainsString('.mcc-attendance-session-page > .mcc-alert', $styles);
        self::assertStringContainsString('.mcc-attendance-session-page .attendance-status-select', $styles);
        self::assertStringContainsString('flex-direction: column', $styles);
        foreach (['PRESENT', 'ABSENT', 'LEAVE', 'MEDICAL', 'LATE'] as $status) {
            self::assertStringContainsString($status, $attendance);
        }
        self::assertStringContainsString('data-attendance-bulk-form', $attendance);
        self::assertStringContainsString('Please select a branch.', $controller);
        self::assertStringContainsString('Please select a coach.', $controller);
        self::assertStringContainsString('default_coach_user_id', $service);
        self::assertStringContainsString('default_branch_id', $service);
        self::assertStringContainsString('$selectedCoachUserId', $create);
        self::assertStringContainsString('data-bt-select-placeholder="Choose coach"', $create);
        self::assertStringContainsString('class="form-control btSelect"', $create);
        self::assertStringContainsString('data-bt-select-placeholder="Choose batch"', $create);
        self::assertStringContainsString('data-bt-select-search-placeholder="Search batches"', $create);
        self::assertStringContainsString("option.selected = option.value === '';", $script);
        self::assertStringContainsString("select.addEventListener('bt-select-refresh', sync);", $script);
        self::assertStringContainsString('Player is not assigned to this Coach.', $service);
        self::assertStringContainsString('assertCoachPlayerScope', $service);
    }

    public function testSessionEditPageUsesSharedResponsiveFormLayout(): void
    {
        $edit = file_get_contents(ROOTPATH . 'app/Modules/Scheduling/Views/edit.php');
        $detail = file_get_contents(ROOTPATH . 'app/Modules/Scheduling/Views/show.php');
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Scheduling/Controllers/Browser.php');
        $script = file_get_contents(ROOTPATH . 'public/assets/js/app.js');
        $styles = file_get_contents(ROOTPATH . 'public/assets/css/app.css');

        foreach ([$edit, $detail, $controller, $script, $styles] as $source) {
            self::assertIsString($source);
        }

        self::assertStringNotContainsString('data-session-modal', $detail);
        self::assertStringContainsString("'Edit Session'", $controller);
        self::assertStringNotContainsString('data-session-action="edit"', $controller);

        foreach (['branch_id', 'program_id', 'batch_id', 'coach_user_id', 'ground_id', 'status', 'skill_area', 'skill_option_id'] as $field) {
            self::assertStringContainsString('name="' . $field . '"', $edit);
        }
        self::assertStringContainsString('name="starts_at"', $edit);
        self::assertStringContainsString('name="starts_time"', $edit);
        self::assertStringContainsString('name="ends_time"', $edit);

        self::assertStringContainsString('Batch <span class="mcc-form-optional">(optional)</span>', $edit);
        self::assertStringContainsString('data-bt-select-placeholder="Choose batch"', $edit);
        self::assertStringContainsString('data-bt-select-search-placeholder="Search batches"', $edit);
        self::assertStringContainsString('old(\'batch_id\', $session[\'batch_id\'] ?? \'\')', $edit);
        self::assertStringContainsString('Skill area <span class="mcc-form-optional">(optional)</span>', $edit);
        self::assertStringContainsString('Skill / Shot <span class="mcc-form-optional">(optional)</span>', $edit);
        self::assertStringContainsString('data-bt-select', $edit);
        self::assertStringContainsString('data-mcc-date-picker', $edit);
        self::assertStringContainsString('Save Session', $edit);
        self::assertStringNotContainsString('data-session-modal-close', $edit);
        self::assertStringContainsString('mcc-session-form-actions mcc-form-actions', $edit);
        self::assertStringContainsString('name="player_ids[]"', $edit);
        self::assertStringContainsString('data-session-group-size', $edit);
        self::assertStringNotContainsString('data-session-player-details', $edit);

        $actionPosition = strpos($edit, 'mcc-session-form-actions mcc-form-actions');
        $feedbackPosition = strpos($edit, 'data-session-response');
        self::assertIsInt($actionPosition);
        self::assertIsInt($feedbackPosition);
        self::assertGreaterThan($feedbackPosition, $actionPosition);
        self::assertStringContainsString('position: sticky', $styles);
        self::assertStringContainsString('.mcc-session-modal .mcc-bt-select-menu', $styles);
        self::assertStringContainsString('z-index: 2301', $styles);
        self::assertStringContainsString('.mcc-date-picker-popup[data-mcc-date-picker-overlay]', $styles);
        self::assertStringContainsString('overflow: visible', $styles);
        self::assertStringContainsString('.mcc-session-batch-field:has(.mcc-bt-select-open)', $styles);
        self::assertStringContainsString('max-width: min(300px, calc(100vw - 2rem));', $styles);
        self::assertStringContainsString('width: 100%;', $styles);
        self::assertStringContainsString('overflow-wrap: anywhere;', $styles);
        self::assertStringContainsString('white-space: normal;', $styles);
        self::assertStringContainsString('z-index: 4101;', $styles);
        self::assertStringContainsString('grid-template-columns: minmax(0, 1fr)', $styles);
    }

    public function testSessionPlayerSelectionUsesAuthorizedMultiSelectAndRefreshesWithBranchCoachScope(): void
    {
        $create = file_get_contents(ROOTPATH . 'app/Modules/Scheduling/Views/create.php');
        $edit = file_get_contents(ROOTPATH . 'app/Modules/Scheduling/Views/edit.php');
        $script = file_get_contents(ROOTPATH . 'public/assets/js/app.js');
        $service = file_get_contents(ROOTPATH . 'app/Services/SchedulingService.php');
        $detail = file_get_contents(ROOTPATH . 'app/Modules/Scheduling/Views/show.php');
        $attendance = file_get_contents(ROOTPATH . 'app/Modules/Attendance/Views/session.php');

        foreach ([$create, $edit, $script, $service, $detail, $attendance] as $source) {
            self::assertIsString($source);
        }

        foreach ([$create, $edit] as $form) {
            self::assertStringContainsString('name="player_ids[]"', $form);
            self::assertStringContainsString('multiple', $form);
            self::assertStringContainsString('data-bt-select-placeholder="Choose players"', $form);
            self::assertStringContainsString('data-session-group-size', $form);
            self::assertStringContainsString('data-coach-ids=', $form);
            self::assertStringContainsString('data-player-batch=', $form);
            self::assertStringContainsString('data-player-age-group=', $form);
            self::assertStringContainsString('old(\'player_ids\'', $form);
        }

        self::assertStringContainsString("'p.id, pe.full_name", $service);
        self::assertStringContainsString('p.status', $service);
        self::assertStringContainsString('coach_user_ids', $service);
        self::assertStringContainsString('a.coach_user_id', $service);
        self::assertStringContainsString('a.personal_coach_user_id', $service);
        self::assertStringContainsString('a.id IS NOT NULL', $service);
        self::assertStringContainsString('->where(\'pba.batch_id\', $batchId)', $service);
        self::assertStringContainsString('assertCoachPlayerScope($db, $playerIds, $branch, $coach)', $service);
        self::assertStringContainsString('validateRoster(', $service);
        self::assertStringContainsString('data-session-branch-options', $create);
        self::assertStringContainsString("var coach = form.querySelector('[name=\"coach_user_id\"]');", $script);
        self::assertStringContainsString("var batch = form.querySelector('[name=\"batch_id\"]');", $script);
        self::assertStringContainsString("var ageGroup = form.querySelector('[name=\"age_group_id\"]');", $script);
        self::assertStringContainsString('option.dataset.coachIds', $script);
        self::assertStringContainsString('option.dataset.playerBatch !== batch.value', $script);
        self::assertStringContainsString('option.dataset.playerAgeGroup !== ageGroup.value', $script);
        self::assertStringContainsString('players.disabled = branch.value ===', $script);
        self::assertStringContainsString("coach.value !== ''", $script);
        self::assertStringContainsString("batch.addEventListener('change', filter);", $script);
        self::assertStringContainsString("ageGroup.addEventListener('change', filter);", $script);
        self::assertStringContainsString('No active Players match the selected Session Category', $script);
        self::assertStringContainsString('bt-select-refresh', $script);
        self::assertStringContainsString('option.selected = false;', $script);
        self::assertStringContainsString('refreshBtSelects(form);', $script);
        self::assertStringContainsString('attendancePlayers', $detail);
        self::assertStringContainsString('$players', $attendance);
    }

    public function testSessionSkillSelectorUsesTheCompleteCatalogAndRefreshesDependentOptions(): void
    {
        $create = file_get_contents(ROOTPATH . 'app/Modules/Scheduling/Views/create.php');
        $edit = file_get_contents(ROOTPATH . 'app/Modules/Scheduling/Views/edit.php');
        $script = file_get_contents(ROOTPATH . 'public/assets/js/app.js');
        $service = file_get_contents(ROOTPATH . 'app/Services/SchedulingService.php');

        foreach ([$create, $edit, $script, $service] as $source) {
            self::assertIsString($source);
        }

        foreach (['batting', 'bowling', 'fielding', 'wicketkeeping', 'fitness', 'game_awareness'] as $area) {
            self::assertStringContainsString("'{$area}'", $create);
        }
        self::assertStringContainsString('data-skill-area', $create);
        self::assertStringContainsString('data-skill-active', $create);
        self::assertStringContainsString('data-skill-retired', $edit);
        self::assertStringContainsString("select('id, area, name, is_active, retired_at, display_order')", $service);
        self::assertStringContainsString("optionSelect.querySelectorAll('option[data-skill-area]')", $script);
        self::assertStringContainsString('option.dataset.skillArea === area', $script);
        self::assertStringContainsString('optionSelect.value = \'\';', $script);
        self::assertStringContainsString('optionSelect.dispatchEvent(new Event(\'bt-select-refresh\'))', $script);
        self::assertStringContainsString('initBtSelect(form);', $script);
        self::assertStringContainsString('initSessionSkillSelectors();', $script);
        self::assertStringContainsString("select.matches('[data-session-skill-option]')", $script);
        self::assertStringContainsString('mcc-bt-select-menu-portal', $script);
        self::assertStringContainsString('document.body.appendChild(panel)', $script);
        self::assertStringContainsString('getBoundingClientRect()', $script);
        self::assertStringContainsString("window.addEventListener('resize', positionPortalPanel)", $script);
        self::assertStringContainsString("window.addEventListener('scroll', positionPortalPanel, true)", $script);
        self::assertStringContainsString('z-index: 10000 !important', file_get_contents(ROOTPATH . 'public/assets/css/app.css'));
        self::assertStringContainsString('position: fixed !important', file_get_contents(ROOTPATH . 'public/assets/css/app.css'));
    }

    public function testSessionListFiltersAreCollapsibleAndPreserveExistingControls(): void
    {
        $view = file_get_contents(ROOTPATH . 'app/Modules/Scheduling/Views/index.php');

        self::assertIsString($view);
        self::assertStringContainsString('$hasFilters = $queryFilters !== [];', $view);
        self::assertStringContainsString('data-filter-toggle', $view);
        self::assertStringContainsString('data-show-label="Show Filters"', $view);
        self::assertStringContainsString('data-hide-label="Hide Filters"', $view);
        self::assertStringContainsString('aria-controls="session-filters"', $view);
        self::assertStringContainsString(
            "aria-expanded=\"' . (\$hasFilters ? 'true' : 'false') . '\"",
            $view,
        );
        self::assertStringContainsString(
            'id="session-filters"',
            $view,
        );

        foreach (['q', 'date', 'status', 'branch_id', 'batch_id', 'coach_user_id', 'ground_id'] as $field) {
            self::assertStringContainsString('name="' . $field . '"', $view);
        }

        self::assertStringContainsString('href="<?= esc(site_url(\'sessions\')) ?>">Clear filters</a>', $view);
        self::assertStringContainsString('array_merge($queryFilters, [\'page\' => $targetPage])', $view);
        self::assertStringContainsString('$pageWindow = mcc_follow_up_page_window($currentPage, $pageCount);', $view);
        self::assertStringContainsString('mcc-pagination-ellipsis', $view);
        self::assertStringContainsString('aria-label="Previous page"', $view);
        self::assertStringContainsString('aria-label="Next page"', $view);
        self::assertStringContainsString('aria-disabled="true"', $view);
        self::assertStringContainsString("'page' => " . '$' . 'targetPage', $view);
        self::assertStringContainsString('mcc-button-primary', $view);
    }

    public function testCreateSessionUsesSharedStructuredResponsiveFormContract(): void
    {
        $create = file_get_contents(ROOTPATH . 'app/Modules/Scheduling/Views/create.php');
        $styles = file_get_contents(ROOTPATH . 'public/assets/css/app.css');

        self::assertIsString($create);
        self::assertIsString($styles);
        self::assertStringContainsString("'title' => 'Create Session'", $create);
        self::assertStringContainsString('mcc-session-create-card', $create);
        self::assertStringContainsString('mcc-session-create-form', $create);

        foreach ([
            'Session Details',
            'Branch / Session Category / Coach',
            'Date and Time',
            '>Ground</h3>',
            'Skill Area and Skill / Shot',
        ] as $section) {
            self::assertStringContainsString($section, $create);
        }

        foreach ([
            'name="branch_id"',
            'name="program_id"',
            'name="batch_id"',
            'name="age_group_id"',
            'name="coach_user_id"',
            'name="ground_id"',
            'name="starts_at"',
            'name="starts_time"',
            'name="skill_area"',
            'name="skill_option_id"',
        ] as $field) {
            self::assertStringContainsString($field, $create);
        }

        foreach ([
            'Choose branch',
            'Choose program',
            'Choose batch',
            'Choose coach',
            'Choose ground',
            'Choose skill area',
            'Choose skill',
        ] as $placeholder) {
            self::assertStringContainsString($placeholder, $create);
        }

        self::assertStringContainsString('Batch <span class="mcc-form-optional">(optional)</span>', $create);
        self::assertStringContainsString('Skill area <span class="mcc-form-optional">(optional)</span>', $create);
        self::assertStringContainsString('Skill / Shot <span class="mcc-form-optional">(optional)</span>', $create);
        self::assertStringContainsString('<input type="hidden" name="status" value="scheduled">', $create);
        self::assertStringContainsString('csrf_field()', $create);
        self::assertSame(1, substr_count($create, 'data-mcc-date-picker-time-input='));
        self::assertStringContainsString('data-mcc-date-picker-time-input="starts-time"', $create);
        self::assertStringNotContainsString('data-mcc-date-picker-time-input="ends-time"', $create);
        self::assertStringContainsString('Save Session', $create);
        self::assertSame(1, substr_count($create, 'Save Session'));
        self::assertSame(1, substr_count($create, 'Cancel'));
        self::assertStringContainsString('name="player_ids[]"', $create);
        self::assertStringContainsString('data-session-group-size', $create);
        self::assertStringContainsString('mcc-session-empty-state', $create);
        self::assertStringNotContainsString('pageActions', $create);
        self::assertStringNotContainsString('name="batch_id" data-session-branch-options', $create);
        self::assertStringContainsString('mcc-card mcc-session-form-section', $create);
        self::assertStringNotContainsString('roster', strtolower($create));
        self::assertStringNotContainsString('snapshot', strtolower($create));

        foreach ([
            'mcc-session-create-grid',
            'mcc-session-datetime-grid',
            'mcc-session-form-actions',
            'grid-template-columns: minmax(0, 1fr)',
            '.mcc-session-create-card .mcc-bt-select-menu',
            'z-index: 4001',
            'overflow: visible',
        ] as $style) {
            self::assertStringContainsString($style, $styles);
        }

        self::assertStringContainsString('mcc-session-skill-field', $create);
        self::assertStringContainsString('mcc-session-skill-field', $styles);
        self::assertStringContainsString('mcc-session-coach-field', $create);
        self::assertStringContainsString('mcc-session-coach-field', $styles);
        self::assertStringContainsString('.mcc-session-create-card .mcc-session-coach-field .mcc-bt-select-menu', $styles);
        self::assertStringContainsString('max-width: 300px', $styles);
        self::assertStringContainsString('width: 100%', $styles);
        self::assertStringContainsString('z-index: 1201', $styles);
        self::assertStringContainsString('white-space: normal', $styles);
        self::assertStringContainsString('overflow-wrap: anywhere', $styles);

        $startsInput = strpos($create, 'id="starts-at"');
        $startsError = strpos($create, 'id="starts-at-error"');
        self::assertIsInt($startsInput);
        self::assertIsInt($startsError);
        self::assertStringContainsString('aria-describedby="starts-at-error"', $create);
        self::assertStringContainsString('name="ends_time"', $create);
        self::assertStringContainsString('type="time"', $create);
        self::assertStringNotContainsString('name="ends_at"', $create);
    }

    public function testSessionListProjectsAuthorizedDisplayNamesAndDistinctPlayerCounts(): void
    {
        $view = file_get_contents(ROOTPATH . 'app/Modules/Scheduling/Views/index.php');
        $model = file_get_contents(ROOTPATH . 'app/Modules/Scheduling/Models/SessionModel.php');
        $service = file_get_contents(ROOTPATH . 'app/Services/SchedulingService.php');

        foreach ([$view, $model, $service] as $source) {
            self::assertIsString($source);
        }

        foreach (['Selected Players', 'Program', 'Coach', 'mcc-session-list-table'] as $label) {
            self::assertStringContainsString($label, $view);
        }

        self::assertStringContainsString('selected_player_count', $view);
        self::assertStringContainsString("\$session['program_name'] ?? null", $view);
        self::assertStringContainsString("\$session['coach_name'] ?? null", $view);
        self::assertStringContainsString('Session/date', $view);
        self::assertStringContainsString('Actions', $view);
        self::assertStringContainsString('mcc-table-wrap mcc-session-table-wrap', $view);
        self::assertStringContainsString("'scheduled' => ['label' => 'Scheduled', 'class' => 'info']", $view);
        self::assertStringContainsString("'active' => ['label' => 'Active', 'class' => 'success']", $view);
        self::assertStringContainsString("'completed' => ['label' => 'Completed', 'class' => 'success']", $view);
        self::assertStringContainsString("'cancelled' => ['label' => 'Cancelled', 'class' => 'danger']", $view);
        self::assertStringContainsString("'postponed' => ['label' => 'Postponed', 'class' => 'warning']", $view);
        self::assertStringContainsString("'label' => 'Unknown', 'class' => 'neutral'", $view);
        self::assertStringContainsString("\$date->format('d-m-Y, h:i A')", $view);
        self::assertStringContainsString('No sessions match your filters', $view);
        self::assertStringContainsString('mcc-pagination', $view);
        self::assertStringContainsString("service('authorization')->can('session.update'", $view);
        self::assertStringContainsString("service('authorization')->can('attendance.view'", $view);
        self::assertStringNotContainsString("\$session['player_name']", $view);
        self::assertStringNotContainsString("\$session['player_names']", $view);

        self::assertStringContainsString("p.name AS program_name", $model);
        self::assertStringContainsString("CASE WHEN ub.user_id IS NULL THEN NULL ELSE u.display_name END AS coach_name", $model);
        self::assertStringContainsString('COUNT(DISTINCT sp.player_id)', $model);
        self::assertStringContainsString("lp.branch_id = s.branch_id", $model);
        self::assertStringContainsString("user_branches ub", $model);
        self::assertStringContainsString("ub.branch_id = s.branch_id", $model);
        self::assertStringContainsString('$model->sessionRows(', $service);
        self::assertStringContainsString('$model->countSessionRows(', $service);
    }

    public function testSessionHistoryProjectsRelatedNamesOnlyForDisplay(): void
    {
        $view = file_get_contents(ROOTPATH . 'app/Modules/Scheduling/Views/show.php');
        $service = file_get_contents(ROOTPATH . 'app/Services/SchedulingService.php');

        self::assertIsString($view);
        self::assertIsString($service);
        self::assertStringContainsString('$beforeDisplay = $event[\'before_display_metadata\'] ?? $before;', $view);
        self::assertStringContainsString('$afterDisplay = $event[\'after_display_metadata\'] ?? $after;', $view);
        self::assertStringContainsString('$safeBefore = $filterMetadata($beforeDisplay);', $view);
        self::assertStringContainsString('$safeAfter = $filterMetadata($afterDisplay);', $view);
        self::assertStringContainsString("'before_display_metadata'", $service);
        self::assertStringContainsString("'after_display_metadata'", $service);
        self::assertStringContainsString('private function projectHistoryMetadata', $service);
        self::assertStringContainsString('private function historyReferenceMaps', $service);
        self::assertStringContainsString("'batch' => []", $service);
        self::assertStringContainsString("'coach' => []", $service);
        self::assertStringContainsString("'branch' => []", $service);
        self::assertStringContainsString("'program' => []", $service);
        self::assertStringContainsString("'ground' => []", $service);
        self::assertStringContainsString("'player' => []", $service);
        self::assertStringContainsString("'batchid' => 'batch'", $service);
        self::assertStringContainsString("'coachuserid' => 'coach'", $service);
        self::assertStringContainsString("'branchid' => 'branch'", $service);
        self::assertStringContainsString("'programid' => 'program'", $service);
        self::assertStringContainsString("'groundid' => 'ground'", $service);
        self::assertStringContainsString("'playerid', 'playerids'", $service);
        self::assertStringContainsString("'Unknown ' . \$inheritedType", $service);
        self::assertStringContainsString("\$db->table('players p')", $service);
        self::assertStringContainsString("->where('p.branch_id', \$branch)", $service);
        self::assertStringContainsString("\$db->table('user_branches ub')", $service);
        self::assertStringContainsString("->where('ub.branch_id', \$branch)", $service);
        self::assertStringContainsString("\$db->table('grounds')", $service);
        self::assertStringContainsString("->where('branch_id', \$branch)", $service);
        self::assertStringContainsString('$renderMetadata($safeBefore)', $view);
        self::assertStringContainsString('$renderMetadata($safeAfter)', $view);
        self::assertStringContainsString('$historyDateTime($event[\'created_at\'] ?? null)', $view);
        self::assertStringContainsString('orderBy(\'al.id\', \'ASC\')', $service);
    }

    public function testSessionActionsUseAccessibleSingleRowMenusAndFullPageEditLinks(): void
    {
        $view = file_get_contents(ROOTPATH . 'app/Modules/Scheduling/Views/index.php');
        $script = file_get_contents(ROOTPATH . 'public/assets/js/app.js');
        $styles = file_get_contents(ROOTPATH . 'public/assets/css/app.css');

        self::assertIsString($view);
        self::assertIsString($script);
        self::assertIsString($styles);
        self::assertStringContainsString('data-session-actions', $view);
        self::assertStringContainsString('data-session-actions-toggle', $view);
        self::assertStringContainsString('data-session-actions-menu', $view);
        self::assertStringContainsString('aria-haspopup="true"', $view);
        self::assertStringContainsString("site_url('sessions/' . \$sessionId . '/edit')", $view);
        self::assertStringNotContainsString('data-session-action="edit"', $view);
        self::assertStringContainsString('closeMenus();', $script);
        self::assertStringContainsString('document.body.appendChild(dropdown);', $script);
        self::assertStringContainsString('window.addEventListener(\'resize\'', $script);
        self::assertStringContainsString('document.addEventListener(\'scroll\'', $script);
        self::assertStringContainsString('.mcc-session-action-dropdown {', $styles);
        self::assertStringContainsString('z-index: 1103;', $styles);
    }
}
