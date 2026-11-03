<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class MeetingFollowUpContractTest extends TestCase
{
    public function testModuleUsesSeparateVisibleLabelAndExplicitRoutes(): void
    {
        $root = dirname(__DIR__, 2);
        $routes = file_get_contents($root . '/app/Modules/MeetingFollowUps/Config/Routes.php');
        $navigation = file_get_contents($root . '/app/Services/NavigationService.php');
        $admissionRoutes = file_get_contents($root . '/app/Modules/Admissions/Config/Routes.php');

        self::assertIsString($routes);
        self::assertIsString($navigation);
        self::assertIsString($admissionRoutes);
        self::assertSame(2, substr_count($navigation, "'Meeting Follow-up'"));
        self::assertStringContainsString("'Admission Test', 'admission-tests'", $navigation);
        self::assertStringContainsString("'Meeting Follow-up', 'meeting-followups'", $navigation);
        self::assertLessThan(
            strpos($navigation, "'Meeting Follow-up', 'meeting-followups'"),
            strpos($navigation, "'Admission Test', 'admission-tests'"),
        );
        self::assertStringContainsString("meeting-followups'", $routes);
        self::assertStringNotContainsString('Admission Test Follow-ups', $navigation);
        self::assertStringNotContainsString('meeting-followups', $admissionRoutes);
    }

    public function testFormsKeepAdmissionTestLinkageAndCsrf(): void
    {
        $root = dirname(__DIR__, 2);
        $form = file_get_contents($root . '/app/Modules/MeetingFollowUps/Views/form.php');
        $service = file_get_contents($root . '/app/Modules/MeetingFollowUps/Services/MeetingFollowUpService.php');

        self::assertIsString($form);
        self::assertIsString($service);
        self::assertStringContainsString("<?= csrf_field() ?>", $form);
        self::assertStringContainsString("name=\"admission_test_id\"", $form);
        self::assertStringContainsString('class="form-control btSelect"', $form);
        self::assertStringContainsString('data-bt-select-placeholder="Choose admission test"', $form);
        self::assertStringContainsString('Test #', $form);
        self::assertStringContainsString('Trial #', $form);
        self::assertStringContainsString('name="start_at"', $form);
        self::assertStringContainsString('data-mcc-date-picker', $form);
        self::assertSame(1, substr_count($form, 'data-mcc-date-picker-no-toggle'));
        self::assertStringContainsString('data-mcc-datetime-submit-format="Y-m-d"', $form);
        self::assertStringContainsString("site_url('meeting-followups')", $form);
        self::assertStringNotContainsString('name="admission_id"', $form);
        self::assertStringNotContainsString('name="end_at"', $form);
        self::assertStringNotContainsString('name="end_time"', $form);
        self::assertStringNotContainsString("input['end_at']", $service);
        self::assertStringContainsString("'notes' => trim((string) (\$input['notes'] ?? ''))", $service);
        self::assertStringContainsString('Meeting Follow-up linkage cannot be changed.', $service);
        self::assertStringContainsString("where('at.branch_id = f.branch_id'", $service);
        self::assertStringContainsString("where('t.branch_id = f.branch_id'", $service);

        $css = file_get_contents($root . '/public/assets/css/app.css');
        self::assertIsString($css);
        self::assertStringContainsString('.mcc-meeting-follow-up-admission-test-field:has(.mcc-bt-select-open)', $css);
        self::assertStringContainsString('z-index: 4001;', $css);
        self::assertStringContainsString('overflow: visible;', $css);

        $controller = file_get_contents($root . '/app/Modules/MeetingFollowUps/Controllers/Browser.php');
        self::assertIsString($controller);
        self::assertStringContainsString("getPost()", $controller);
        self::assertStringContainsString("site_url('meeting-followups/' . \$id)", $controller);
        self::assertStringContainsString("withInput()", $controller);
        self::assertStringContainsString("field_errors", $controller);
        self::assertStringContainsString('random_bytes(5)', $controller);
    }
}
