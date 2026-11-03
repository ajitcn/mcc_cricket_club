<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class DatePickerUiContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = dirname(__DIR__, 2);
    }

    public function testSharedDatepickerKeepsInputValueWhileNavigatingAndMarksDates(): void
    {
        $script = file_get_contents($this->root . '/public/assets/js/app.js');

        self::assertIsString($script);
        self::assertStringContainsString('var viewDate = date();', $script);
        self::assertStringContainsString('meta[name="mcc-timezone"]', $script);
        self::assertStringContainsString('timeZone: configuredTimezone', $script);
        self::assertStringContainsString("T(\\d{2}:\\d{2})(?::\\d{2})?([+-]\\d{2}:\\d{2}|Z)?", $script);
        self::assertStringContainsString("button.classList.add('is-today')", $script);
        self::assertStringContainsString("button.classList.add('is-selected')", $script);
        self::assertStringContainsString('data-month', $script);
        self::assertStringContainsString('data-year', $script);
        self::assertStringContainsString('aria-label="Select month"', $script);
        self::assertStringContainsString('aria-label="Select year"', $script);
        self::assertStringContainsString('input.getAttribute(\'min\')', $script);
        self::assertStringContainsString('input.getAttribute(\'max\')', $script);
        self::assertStringContainsString('button.disabled = ! dateAllowed(buttonDate)', $script);
        self::assertStringContainsString('monthSelect.addEventListener(\'change\'', $script);
        self::assertStringContainsString('yearSelect.addEventListener(\'change\'', $script);
        self::assertStringContainsString("popup.addEventListener('click'", $script);
        self::assertStringContainsString('event.stopPropagation();', $script);
        self::assertStringContainsString('popup.contains(event.target)', $script);
        self::assertStringContainsString("popup.addEventListener('keydown'", $script);
        self::assertStringContainsString('closePicker(true);', $script);
        self::assertStringContainsString('closePicker(false);', $script);
        self::assertStringContainsString('viewDate = new Date(year, month - 1, 1);', $script);
        self::assertStringContainsString('viewDate = new Date(year, month + 1, 1);', $script);
        self::assertStringContainsString("previousButton.addEventListener('click'", $script);
        self::assertStringContainsString("nextButton.addEventListener('click'", $script);
        self::assertStringContainsString("popup.querySelector('[data-prev]').focus();", $script);
        self::assertStringContainsString("popup.querySelector('[data-next]').focus();", $script);
        self::assertStringNotContainsString(
            'input.value = formatDate(new Date(year, month - 1, 1));',
            $script
        );
        self::assertStringNotContainsString(
            'input.value = formatDate(new Date(year, month + 1, 1));',
            $script
        );
    }

    public function testEditableDateFieldsUseTheSharedPickerAndSeparateCoachTimes(): void
    {
        $coachView = file_get_contents($this->root . '/app/Modules/Coaches/Views/show.php');
        $attendanceView = file_get_contents($this->root . '/app/Modules/Attendance/Views/index.php');

        self::assertIsString($coachView);
        self::assertIsString($attendanceView);
        self::assertStringContainsString('data-mcc-date-picker', $coachView);
        self::assertStringContainsString('data-mcc-date-picker-time-input="coach-leave-from-time"', $coachView);
        self::assertStringContainsString('data-mcc-date-picker-time-input="coach-leave-to-time"', $coachView);
        self::assertStringContainsString('data-mcc-date-picker', $attendanceView);
        self::assertStringNotContainsString('type="datetime-local"', $coachView);
        self::assertStringNotContainsString('type="date"', $coachView);
        self::assertStringNotContainsString('type="date"', $attendanceView);
    }

    public function testAffectedViewsDoNotExposeTimezoneSuffixes(): void
    {
        $files = [
            '/app/Modules/Attendance/Views/session.php',
            '/app/Modules/Attendance/Views/detail.php',
            '/app/Modules/Scheduling/Views/create.php',
            '/app/Modules/Scheduling/Views/edit.php',
            '/app/Modules/Scheduling/Views/show.php',
            '/app/Modules/Matches/Views/index.php',
            '/app/Modules/Matches/Views/show.php',
            '/app/Modules/Tournaments/Views/show.php',
        ];

        foreach ($files as $file) {
            $contents = file_get_contents($this->root . $file);
            self::assertIsString($contents);
            self::assertStringNotContainsString('Asia/Kolkata', $contents, $file);
            self::assertStringNotContainsString('(UTC)', $contents, $file);
            self::assertStringNotContainsString('MCC time', $contents, $file);
        }
    }
}
