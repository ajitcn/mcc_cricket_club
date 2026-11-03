<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\AdmissionFollowUpService;
use CodeIgniter\Test\CIUnitTestCase;

final class AdmissionFollowUpContractTest extends CIUnitTestCase
{
    public function testClosedAdmissionsCannotCreateFollowUps(): void
    {
        $service = new AdmissionFollowUpService();

        foreach (['APPROVED', 'COMPLETED', 'CONVERTED'] as $status) {
            self::assertFalse($service->canCreateForAdmission(['status' => $status]));
        }

        self::assertFalse($service->canCreateForAdmission([
            'status' => 'INTAKE',
            'converted_player_id' => 42,
        ]));
        self::assertTrue($service->canCreateForAdmission(['status' => 'INTAKE']));
    }

    public function testFollowUpDateTimeUsesMccTimezoneAndTwelveHourFormat(): void
    {
        self::assertSame(
            '02-01-2026, 12:00 AM',
            mcc_follow_up_datetime('2026-01-01 18:30:00'),
        );
        self::assertSame(
            '01-01-2026, 12:00 PM',
            mcc_follow_up_datetime('2026-01-01 06:30:00'),
        );
        self::assertSame(
            '01-01-2026, 01:30 PM',
            mcc_follow_up_datetime('2026-01-01 08:00:00'),
        );
        self::assertSame(
            '01-01-2026, 11:45 PM',
            mcc_follow_up_datetime('2026-01-01 18:15:00'),
        );
        self::assertSame('—', mcc_follow_up_datetime(null, '—'));
    }

    public function testFollowUpPaginationWindowIsCompactAndUnique(): void
    {
        self::assertSame(range(1, 10), mcc_follow_up_page_window(4, 10));
        self::assertSame(
            [1, 2, 3, 4, 5, '…', 26, 27, 28, 29, 30],
            mcc_follow_up_page_window(1, 30),
        );
        self::assertSame(
            [5, 6, 7, 8, 9, '…', 26, 27, 28, 29, 30],
            mcc_follow_up_page_window(5, 30),
        );
        self::assertSame(
            [6, 7, 8, 9, 10, '…', 26, 27, 28, 29, 30],
            mcc_follow_up_page_window(6, 30),
        );
        self::assertSame(
            [10, 11, 12, 13, 14, '…', 26, 27, 28, 29, 30],
            mcc_follow_up_page_window(10, 30),
        );
        self::assertSame(
            [24, 25, 26, 27, 28, 29, 30],
            mcc_follow_up_page_window(24, 30),
        );
        $pageWindow = mcc_follow_up_page_window(10, 30);
        $pageNumbers = array_values(array_filter($pageWindow, 'is_int'));
        self::assertCount(count(array_unique($pageNumbers)), $pageNumbers);
    }

    public function testAdmissionFollowUpModuleUsesBranchScopedRoutesAndDedicatedTable(): void
    {
        $routes = (string) file_get_contents(ROOTPATH . 'app/Modules/Admissions/Config/Routes.php');
        $migration = (string) file_get_contents(ROOTPATH . 'app/Database/Migrations/20261016000200_CreateAdmissionFollowups.php');
        $service = (string) file_get_contents(ROOTPATH . 'app/Services/AdmissionFollowUpService.php');

        self::assertStringContainsString("followups/new", $routes);
        self::assertStringContainsString("followups/(:num)/edit", $routes);
        self::assertStringContainsString("permission:admission.view", $routes);
        self::assertStringContainsString("permission:admission.create", $routes);
        self::assertStringContainsString("permission:admission.update", $routes);
        self::assertStringContainsString("admission_followups", $migration);
        self::assertStringContainsString("addKey(['admission_id', 'follow_up_at'])", $migration);
        self::assertStringContainsString("whereIn('admission_followups.branch_id', \$branches)", $service);
        self::assertStringContainsString("service('auditPersistence')->record", $service);
        self::assertStringContainsString('summaryCounts', $service);
    }

    public function testFollowUpViewsExposeRequiredHistoryAndFormFields(): void
    {
        $form = (string) file_get_contents(ROOTPATH . 'app/Modules/Admissions/Views/followup_form.php');
        $list = (string) file_get_contents(ROOTPATH . 'app/Modules/Admissions/Views/followups.php');
        $detail = (string) file_get_contents(ROOTPATH . 'app/Modules/Admissions/Views/followup_show.php');
        $admission = (string) file_get_contents(ROOTPATH . 'app/Modules/Admissions/Views/show.php');
        $service = (string) file_get_contents(ROOTPATH . 'app/Services/AdmissionFollowUpService.php');
        $controller = (string) file_get_contents(ROOTPATH . 'app/Modules/Admissions/Controllers/FollowUps.php');
        $appJs = (string) file_get_contents(ROOTPATH . 'public/assets/js/app.js');
        $appCss = (string) file_get_contents(ROOTPATH . 'public/assets/css/app.css');

        self::assertStringContainsString("'name' => 'admission_id'", $form);
        self::assertStringContainsString('<?= csrf_field() ?>', $form);
        self::assertStringContainsString('data-mcc-datetime-submit-format="Y-m-d"', $form);
        self::assertStringContainsString('Add Admission Follow-up', $form);
        self::assertStringContainsString('Edit Admission Follow-up', $form);
        self::assertStringContainsString('Admission Information', $form);
        self::assertStringContainsString('Follow-up Details', $form);
        self::assertStringContainsString('Notes and Outcome', $form);
        self::assertStringContainsString('mcc-form-actions', $form);
        self::assertStringContainsString('Save Follow-up', $form);
        self::assertStringContainsString('Back to Follow-ups', $form);
        self::assertStringContainsString('placeholder' . "' => 'Choose admission'", $form);
        self::assertStringContainsString('>Choose type</option>', $form);
        self::assertStringContainsString('>Choose status</option>', $form);
        self::assertStringContainsString('value="Scheduled"', $form);
        self::assertStringContainsString('aria-readonly="true"', $form);
        self::assertStringContainsString('>Choose assigned user</option>', $form);
        self::assertStringContainsString("'label' => 'Admission'", $form);
        self::assertStringContainsString("'placeholder' => 'Choose admission'", $form);
        self::assertStringNotContainsString('Applicant name</span>', $form);
        self::assertStringNotContainsString('Admission number</span>', $form);
        self::assertStringNotContainsString('>Not available</p>', $form);
        self::assertStringNotContainsString('Derived from Admission', $form);
        self::assertStringContainsString('selectedApplicantName', $form);
        self::assertStringContainsString('selectedAdmissionNumber', $form);
        self::assertStringContainsString('selectedBranchName', $form);
        self::assertSame(
            2,
            preg_match_all('/data-mcc-date-picker\s+data-mcc-date-picker-no-toggle/', $form),
        );
        foreach (['follow_up_at', 'follow_up_type', 'status', 'notes', 'outcome', 'next_follow_up_at', 'assigned_user_id'] as $field) {
            self::assertStringContainsString('name="' . $field . '"', $form);
        }
        self::assertStringContainsString('Admission Follow-ups', $list);
        self::assertStringContainsString('Show filters', $list);
        self::assertStringContainsString('data-filter-toggle', $list);
        self::assertStringContainsString('mcc-follow-up-summary-card', $list);
        self::assertStringContainsString('aria-label="<?= $card[\'key\'] === \'all\' ? \'Show all follow-ups\' : \'Show \' . esc(strtolower($card[\'label\'])) . \' follow-ups\' ?>"', $list);
        self::assertStringNotContainsString('Apply <?= esc(strtolower($card[\'label\'])) ?> filter', $list);
        self::assertStringContainsString('data-show-label="Show filters"', $list);
        self::assertStringContainsString('data-hide-label="Hide filters"', $list);
        self::assertStringContainsString('mcc-follow-up-action-menu', $list);
        self::assertStringContainsString('data-follow-up-actions-toggle', $list);
        self::assertStringContainsString('View Follow-up', $list);
        self::assertStringContainsString('View Admission', $list);
        self::assertStringContainsString('mcc-follow-up-status-overdue', $list);
        self::assertStringContainsString('mcc-follow-up-status-pending', $list);
        self::assertStringContainsString('mcc-follow-up-status-scheduled', $list);
        self::assertStringContainsString('mcc-follow-up-status-completed', $list);
        self::assertStringContainsString('mcc-follow-up-status-cancelled', $list);
        self::assertStringNotContainsString('Track every Admission conversation and next action.', $list);
        self::assertStringContainsString('mcc_follow_up_status_presentation', $list);
        self::assertStringContainsString('mcc_follow_up_status_presentation', $detail);
        self::assertStringContainsString('pageNumber', $controller);
        self::assertStringContainsString('FILTER_VALIDATE_INT', $controller);
        self::assertStringContainsString("is_array(\$value)", $controller);
        self::assertStringContainsString('mcc-follow-up-status-neutral', file_get_contents(ROOTPATH . 'app/Helpers/common_helper.php'));
        self::assertStringContainsString('mcc_follow_up_datetime', file_get_contents(ROOTPATH . 'app/Helpers/common_helper.php'));
        self::assertStringContainsString('mcc_follow_up_datetime', $list);
        self::assertStringContainsString('mcc_follow_up_datetime', $detail);
        self::assertStringContainsString('mcc_follow_up_datetime', $admission);
        self::assertStringNotContainsString('<th>Next follow-up</th>', $list);
        self::assertStringContainsString('Schedule Next Follow-up', $form);
        self::assertStringContainsString('name="schedule_next_follow_up"', $form);
        self::assertStringContainsString('data-follow-up-schedule-toggle', $form);
        self::assertStringContainsString('data-follow-up-next-fields', $form);
        self::assertStringContainsString('class="col-12 mcc-follow-up-next-fields"', $form);
        self::assertMatchesRegularExpression(
            '/data-follow-up-next-fields[\s\S]*?<div class="row">\s*<div class="col-12 col-md-6 mb-3">/',
            $form,
        );
        self::assertMatchesRegularExpression(
            '/<div class="col-12 col-md-6 mb-3">\s*<label for="admission-next-follow-up-time">/',
            $form,
        );
        self::assertStringContainsString('fields.hidden = ! visible;', $appJs);
        self::assertStringContainsString('input.disabled = ! visible;', $appJs);
        self::assertStringContainsString("input.value = '';", $appJs);
        self::assertStringContainsString('position: fixed', $appCss);
        self::assertStringContainsString('z-index: 1101', $appCss);
        self::assertStringNotContainsString('name="follow_up_action"', $form);
        self::assertStringNotContainsString('Reschedule', $form);
        self::assertStringNotContainsString('reschedule', $form);
        self::assertStringNotContainsString('public function scheduleNext', $service);
        self::assertStringContainsString("admission_follow_up_scheduled", $service);
        self::assertStringContainsString("'status' => 'scheduled'", $service);
        self::assertStringContainsString("private const CREATION_STATUS = 'scheduled'", $service);
        self::assertStringContainsString('self::CREATION_STATUS', $service);
        self::assertStringContainsString(
            "true,\n                self::CREATION_STATUS,",
            $service,
        );
        self::assertStringContainsString(
            "'status' => 'scheduled'",
            $service,
        );
        self::assertStringContainsString('$forcedStatus = null', $service);
        self::assertStringContainsString("->where('status', 'scheduled')", $service);
        self::assertStringContainsString("FILTER_STATUSES = ['pending', 'scheduled', 'completed', 'cancelled']", $service);
        self::assertStringNotContainsString("service('admissionFollowUp')->scheduleNext", $controller);
        self::assertStringContainsString("\$savedId = \$nextId ?? \$id", $controller);
        self::assertStringNotContainsString('Schedule Next Follow-up', $list);
        self::assertStringContainsString('Follow-up Start Date', $list);
        self::assertStringContainsString('Follow-up End Date', $list);
        self::assertStringContainsString('name="date_from"', $list);
        self::assertStringContainsString('name="date_to"', $list);
        self::assertStringContainsString('Search Applicant', $list);
        self::assertStringContainsString('name="applicant"', $list);
        self::assertStringContainsString("'date_from', 'date_to', 'next_due', 'applicant'", $service);
        self::assertStringContainsString('LOWER(a.applicant_full_name) LIKE', $service);
        self::assertStringContainsString('array_merge($queryFilters, [\'page\' => $page])', $list);
        self::assertStringContainsString('Previous', $list);
        self::assertStringContainsString('Next', $list);
        self::assertStringContainsString('aria-current="page"', $list);
        self::assertStringContainsString('currentPage - 1', $list);
        self::assertStringContainsString('currentPage + 1', $list);
        self::assertStringContainsString('mcc_follow_up_page_window', $list);
        self::assertStringContainsString('mcc-pagination-ellipsis', $list);
        self::assertStringContainsString('aria-disabled="true"', $list);
        self::assertStringContainsString('countAllResults()', $service);
        self::assertStringContainsString("orderBy('admission_followups.follow_up_at', 'DESC')", $service);
        self::assertStringContainsString("orderBy('admission_followups.id', 'DESC')", $service);
        self::assertSame(
            2,
            preg_match_all('/data-mcc-date-picker\s+data-mcc-date-picker-no-toggle/', $list),
        );
        self::assertStringContainsString("'date_from' => 'Enter a valid Follow-up Start Date.'", $service);
        self::assertStringContainsString("'date_to' => 'Enter a valid Follow-up End Date.'", $service);
        self::assertStringNotContainsString('<th>Branch</th>', $list);
        self::assertStringContainsString('class="mcc-follow-up-admission-cell"', $list);
        self::assertStringContainsString('class="mcc-follow-up-applicant-name"', $list);
        self::assertStringContainsString("#<?= esc((string) (\$followUp['admission_number'] ?: '", $list);
        self::assertStringContainsString("?: 'Missing number'", $list);
        self::assertStringContainsString(
            "<span class=\"mcc-follow-up-applicant-name\"><?= esc((string) (\$followUp['applicant_name'] ?: 'Applicant')) ?></span>",
            $list,
        );
        self::assertStringContainsString("<span><?= esc((string) \$followUp['branch_name']) ?></span>", $list);
        self::assertStringNotContainsString("site_url('admissions/' . (int) \$followUp['admission_id'])", $list);
        self::assertStringNotContainsString('class="mcc-follow-up-admission-cell">\n                                <a', $list);
        self::assertMatchesRegularExpression(
            '/class="mcc-follow-up-admission-cell".*applicant_name.*admission_number.*branch_name/s',
            $list,
        );
        self::assertStringNotContainsString("\$followUp['admission_number'] ?: 'Admission'", $list);
        self::assertStringContainsString("a.admission_number AS linked_admission_number", $service);
        self::assertStringContainsString("\$row['admission_number'] = \$row['linked_admission_number'] ?? null", $service);
        self::assertStringContainsString("a.applicant_full_name AS applicant_name", $service);
        self::assertStringContainsString('FOLLOW_UP_PROJECTION', $service);
        self::assertStringContainsString("where('a.branch_id = admission_followups.branch_id', null, false)", $service);
        self::assertStringContainsString("whereNotIn('a.status', ['APPROVED', 'COMPLETED', 'CONVERTED'])", $service);
        self::assertStringContainsString("where('a.converted_player_id IS NULL', null, false)", $service);
        self::assertStringContainsString('assertAdmissionCanCreateFollowUp', $service);
        self::assertStringContainsString(
            'No new follow-up can be created after the Admission is completed or converted.',
            $service,
        );
        self::assertStringContainsString("['APPROVED', 'COMPLETED', 'CONVERTED']", $service);
        self::assertStringNotContainsString("?: '—'", $list);
        self::assertStringContainsString("'admission_number' => trim((string) (\$followUp['admission_number'] ?? ''))", $list);
        self::assertStringContainsString("http_build_query(\$admissionListQuery)", $list);
        self::assertStringContainsString("site_url('admissions')", $list);
        self::assertStringContainsString("can('admission.update', \$branchId)", $list);
        self::assertStringContainsString("can('admission.create', \$branchId)", $list);
        self::assertStringContainsString('data-follow-up-actions-menu', $list);
        self::assertStringNotContainsString('<th>Applicant</th>', $list);
        self::assertStringNotContainsString('>View</a>', $list);
        self::assertStringNotContainsString('>Edit</a>', $list);
        self::assertStringContainsString('Follow-up History', $admission);
        self::assertStringContainsString('class="mcc-follow-up-applicant-name"', $admission);
        self::assertStringContainsString('Add Follow-up', $admission);
        self::assertStringContainsString('mcc-follow-up-history', $detail . $admission);
        self::assertStringContainsString('class="mcc-follow-up-applicant-name"', $detail);
        self::assertStringContainsString('Follow-up Details', $detail);
        self::assertStringContainsString('Admission information', $detail);
        self::assertStringContainsString('Follow-up information', $detail);
        self::assertStringContainsString('No notes were recorded.', $detail);
        self::assertStringContainsString('No outcome was recorded.', $detail);
        self::assertStringNotContainsString('Schedule Next Follow-up', $detail);
        self::assertStringContainsString('followUpHistory', $detail);
        self::assertStringContainsString('Follow-up History', $detail);
        self::assertStringContainsString("followUp['admission_status']", $detail);
        self::assertStringContainsString("followUp['applicant_email']", $detail);
        self::assertStringContainsString('Add Another Follow-up', $detail);
        self::assertStringContainsString('admissionListUrl', $detail);
        self::assertStringContainsString('auditHistory', $detail);
        self::assertStringContainsString('a.status AS admission_status', $service);
        self::assertStringContainsString('a.converted_player_id AS converted_player_id', $service);
        self::assertStringContainsString("\$type === 'reschedule'", $service);
        self::assertStringContainsString("\$status === 'reschedule'", $service);
    }

    public function testFollowUpStatusPresentationUsesSharedClasses(): void
    {
        self::assertSame(
            'mcc-follow-up-status-pending',
            mcc_follow_up_status_presentation('pending')['class'],
        );
        self::assertSame(
            'mcc-follow-up-status-scheduled',
            mcc_follow_up_status_presentation('pending', '31-12-2099 09:00:00')['class'],
        );
        self::assertSame(
            'mcc-follow-up-status-scheduled',
            mcc_follow_up_status_presentation('scheduled')['class'],
        );
        self::assertSame(
            'mcc-follow-up-status-overdue',
            mcc_follow_up_status_presentation('pending', '01-01-2020 09:00:00')['class'],
        );
        self::assertSame(
            'mcc-follow-up-status-completed',
            mcc_follow_up_status_presentation('completed')['class'],
        );
        self::assertSame(
            'mcc-follow-up-status-cancelled',
            mcc_follow_up_status_presentation('cancelled')['class'],
        );
        self::assertSame(
            'mcc-follow-up-status-neutral',
            mcc_follow_up_status_presentation('other')['class'],
        );
    }
}
