<?php

declare(strict_types=1);

namespace Tests\Feature;

use CodeIgniter\Test\CIUnitTestCase;

final class MeetingFollowUpRoutesTest extends CIUnitTestCase
{
    public function testRoutesExposeListCreateViewAndEditActions(): void
    {
        $routes = file_get_contents(dirname(__DIR__, 2) . '/app/Modules/MeetingFollowUps/Config/Routes.php');

        self::assertIsString($routes);
        self::assertStringContainsString("Browser::index", $routes);
        self::assertStringContainsString("Browser::newPage", $routes);
        self::assertStringContainsString("Browser::create", $routes);
        self::assertStringContainsString("Browser::show/$1", $routes);
        self::assertStringContainsString("Browser::editPage/$1", $routes);
        self::assertStringContainsString("Browser::update/$1", $routes);
        self::assertStringContainsString('permission:admission.view', $routes);
        self::assertStringContainsString('permission:admission.create', $routes);
        self::assertStringContainsString('permission:admission.update', $routes);
    }

    public function testViewsExposeRequiredMeetingFollowUpFieldsAndNoAdmissionFollowUpLabel(): void
    {
        $root = dirname(__DIR__, 2);
        $view = file_get_contents($root . '/app/Modules/MeetingFollowUps/Views/index.php');
        $form = file_get_contents($root . '/app/Modules/MeetingFollowUps/Views/form.php');

        self::assertIsString($view);
        self::assertIsString($form);
        foreach (['Admission Test', 'Applicant', 'Trial', 'Branch', 'Follow-up Type', 'Status', 'Start Date', 'Assigned', 'Next Action', 'Updated', 'Actions'] as $label) {
            self::assertStringContainsString($label, $view);
        }
        self::assertStringContainsString('Meeting Follow-up', $view);
        self::assertStringContainsString('data-filter-toggle', $view);
        self::assertStringContainsString('id="meeting-follow-up-filters"', $view);
        self::assertStringContainsString('name="applicant"', $view);
        self::assertStringContainsString('name="admission_test_id"', $view);
        self::assertStringContainsString('name="trial_id"', $view);
        self::assertStringContainsString('name="branch_id"', $view);
        self::assertStringContainsString('name="status"', $view);
        self::assertStringContainsString('name="assigned_user_id"', $view);
        self::assertStringContainsString('name="date_from"', $view);
        self::assertStringContainsString('name="date_to"', $view);
        self::assertSame(2, substr_count($view, 'data-mcc-date-picker-no-toggle'));
        self::assertStringContainsString('fa-ellipsis-vertical', $view);
        self::assertStringContainsString('meeting-follow-up-actions-', $view);
        self::assertStringContainsString('mcc_follow_up_page_window', $view);
        self::assertStringNotContainsString('<th scope="col">End</th>', $view);
        self::assertStringContainsString('Follow-up Start Date', $form);
        self::assertStringNotContainsString('Meeting Follow-up End Date', $form);
        self::assertStringNotContainsString('End time', $form);
        self::assertStringNotContainsString('Admission Test Follow-ups', $view);
    }

    public function testDetailViewUsesSharedLineageLayoutAndCurrentFields(): void
    {
        $show = file_get_contents(dirname(__DIR__, 2) . '/app/Modules/MeetingFollowUps/Views/show.php');

        self::assertIsString($show);
        foreach (['Meeting Follow-up Details', 'Test reference', 'Applicant name', 'Trial reference', 'Branch', 'Test status', 'Admission number', 'Follow-up type', 'Status', 'Follow-up Start Date', 'Start Time', 'Assigned user', 'Notes', 'Next action', 'Created by', 'Created date', 'Last updated date'] as $label) {
            self::assertStringContainsString($label, $show);
        }
        self::assertStringContainsString('mcc-card', $show);
        self::assertStringContainsString('mcc-detail-hero', $show);
        self::assertStringContainsString('mcc-detail-grid', $show);
        self::assertStringContainsString('mcc-detail-list', $show);
        self::assertStringContainsString('mcc-status-badge', $show);
        self::assertStringContainsString('mcc_follow_up_datetime', $show);
        self::assertStringContainsString('meeting-followups/\' . $followUpId', $show);
        self::assertStringContainsString("crm/trials/' . \$trialId", $show);
        self::assertStringContainsString("admissions/' . \$admissionId", $show);
        self::assertStringContainsString('data-follow-up-actions', $show);
        self::assertStringContainsString('aria-controls="meeting-follow-up-detail-actions"', $show);
        self::assertStringContainsString('mcc-follow-up-detail-actions', $show);
        self::assertStringNotContainsString('Meeting Follow-up End Date', $show);
        self::assertStringNotContainsString('End Time', $show);
        self::assertStringNotContainsString("echo \$followUp['notes']", $show);
    }

    public function testEditFormLocksAdmissionTestAndUsesCurrentMeetingFields(): void
    {
        $form = file_get_contents(dirname(__DIR__, 2) . '/app/Modules/MeetingFollowUps/Views/form.php');

        self::assertIsString($form);
        self::assertStringContainsString("site_url('meeting-followups/' . (int) (\$followUp['id'] ?? 0) . '/edit')", $form);
        self::assertStringContainsString('name="admission_test_id"', $form);
        self::assertStringContainsString('type="hidden" name="admission_test_id"', $form);
        self::assertStringContainsString('name="follow_up_type"', $form);
        self::assertStringContainsString('name="status"', $form);
        self::assertStringContainsString('name="start_at"', $form);
        self::assertStringContainsString('name="start_time"', $form);
        self::assertStringContainsString('name="assigned_user_id"', $form);
        self::assertStringContainsString('name="notes"', $form);
        self::assertStringContainsString('name="next_action"', $form);
        self::assertStringContainsString('data-mcc-date-picker', $form);
        self::assertStringContainsString('data-mcc-date-picker-time-input="meeting-start-time"', $form);
        self::assertStringContainsString('<?= csrf_field() ?>', $form);
        self::assertStringContainsString('mcc-meeting-follow-up-summary-grid', $form);
        self::assertStringContainsString('mcc-meeting-follow-up-form-header', $form);
        self::assertStringContainsString('mcc-meeting-follow-up-form-card', $form);
        self::assertStringContainsString('isolation: isolate', file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/app.css'));
        self::assertSame(1, substr_count($form, 'Save Follow-up'));
        self::assertStringContainsString('<?php if (! $isEdit): ?>', $form);
        self::assertStringNotContainsString('Back to details', $form);
        self::assertStringContainsString('CRM', $form);
        self::assertStringContainsString("['label' => 'Edit']", $form);
        self::assertStringNotContainsString('Meeting Follow-up End Date', $form);
        self::assertStringNotContainsString('name="end_at"', $form);
        self::assertStringNotContainsString('name="end_time"', $form);
        self::assertStringNotContainsString('Next follow-up date', $form);
        self::assertStringNotContainsString('Next follow-up time', $form);
    }
}
