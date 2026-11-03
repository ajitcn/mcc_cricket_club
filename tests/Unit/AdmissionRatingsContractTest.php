<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AdmissionRatingsContractTest extends TestCase
{
    public function testAdmissionRendersEditableRatingDropdowns(): void
    {
        $root = dirname(__DIR__, 2);
        $view = (string) file_get_contents($root . '/app/Modules/Admissions/Views/_lead_ratings_fields.php');
        $service = (string) file_get_contents($root . '/app/Services/AdmissionService.php');
        $javascript = (string) file_get_contents($root . '/public/assets/js/app.js');

        self::assertStringContainsString('<h3>Ratings</h3>', $view);
        self::assertStringContainsString('Choose rating', $view);
        self::assertStringContainsString('data-admission-rating-empty hidden role="status"', $view);
        self::assertStringContainsString(
            '<div class="row admission-rating-grid mcc-admission-rating-grid"',
            $view,
        );
        self::assertSame(1, substr_count($view, '<div class="col-12 col-md-4">'));
        self::assertStringContainsString('foreach ($ratingFields as $field => $label)', $view);
        self::assertStringContainsString('class="mcc-form-field"', $view);
        self::assertStringContainsString('<select class="form-control"', $view);
        self::assertStringContainsString('data-admission-trial-rating="', $view);
        self::assertStringContainsString('range(0, 100)', $view);
        self::assertStringContainsString('$rating . \' - \' . $ratingService->category($rating)', $view);
        self::assertStringNotContainsString('data-admission-rating-category=', $view);
        self::assertStringNotContainsString('function ratingCategory', $javascript);
        self::assertStringNotContainsString("view('components/admission-select'", $view);
        self::assertStringNotContainsString('data-bt-select', $view);
        self::assertStringNotContainsString('type="radio"', $view);
        self::assertStringNotContainsString('field_class', $view);
        self::assertStringContainsString('<div class="col-12 col-md-4">', $view);
        self::assertStringContainsString('<div class="mcc-form-field">', $view);

        foreach ([
            'ability_overall' => 'Overall Rating',
            'ability_batting' => 'Batting Rating',
            'ability_bowling' => 'Bowling Rating',
            'ability_wicketkeeping' => 'Wicketkeeping Rating',
            'ability_fitness' => 'Fitness Rating',
        ] as $field => $label) {
            self::assertStringContainsString($field, $view);
            self::assertStringContainsString($label, $view);
            self::assertStringContainsString("'$field'", $service);
        }

        $styles = (string) file_get_contents($root . '/public/assets/css/app.css');
        self::assertStringContainsString(
            'grid-template-columns: repeat(3, minmax(0, 1fr));',
            $styles,
        );
        self::assertStringContainsString(
            "margin-top: 0;\n    min-width: 0;\n    padding: .9rem;",
            $styles,
        );
        self::assertStringContainsString(
            "display: grid;\n    gap: .75rem;\n    grid-template-columns: repeat(3, minmax(0, 1fr));",
            $styles,
        );
        self::assertStringContainsString(
            '@media (max-width: 575.98px)',
            $styles,
        );
        self::assertStringContainsString(
            'grid-template-columns: minmax(0, 1fr);',
            $styles,
        );
        self::assertStringNotContainsString(
            '.mcc-admission-form-shell .mcc-admission-rating-grid > .mcc-form-field',
            $styles,
        );
        self::assertStringContainsString('display: block;', $styles);
        self::assertStringContainsString('width: 100%;', $styles);
        self::assertStringContainsString('min-width: 0;', $styles);
        self::assertStringContainsString('white-space: normal;', $styles);
        self::assertStringContainsString('max-width: none !important;', $styles);
        self::assertStringContainsString('width: 100% !important;', $styles);
        self::assertStringContainsString(
            '.mcc-admission-form-shell [data-admission-rating-empty] {',
            $styles,
        );
        self::assertStringContainsString('display: none;', $styles);
        self::assertStringContainsString('margin: 0;', $styles);
        self::assertStringContainsString('padding: 0;', $styles);
        self::assertStringContainsString(
            '[data-admission-rating-empty]:not([hidden])',
            $styles,
        );
        self::assertStringContainsString('empty.textContent.trim() ===', $javascript);
        self::assertStringNotContainsString(
            "control.dispatchEvent(new Event('bt-select-refresh'))",
            $javascript,
        );
        self::assertStringContainsString(
            "trial.dispatchEvent(new Event('bt-select-refresh'))",
            $javascript,
        );
        self::assertStringNotContainsString(
            "ratings.querySelectorAll('[data-admission-trial-rating]')",
            $javascript,
        );
        self::assertStringNotContainsString('.mcc-admission-rating-grid .mcc-bt-select', $styles);
        self::assertStringContainsString(
            '.mcc-admission-form-shell .mcc-admission-rating-grid .mcc-form-field > label',
            $styles,
        );
        self::assertStringNotContainsString(
            '.mcc-admission-form-shell .mcc-admission-rating-grid label {',
            $styles,
        );
    }

    public function testLeadAndRatingsAreOneAdmissionSection(): void
    {
        $root = dirname(__DIR__, 2);
        $partial = (string) file_get_contents($root . '/app/Modules/Admissions/Views/_complete_form_fields.php');
        $new = (string) file_get_contents($root . '/app/Modules/Admissions/Views/new.php');
        $edit = (string) file_get_contents($root . '/app/Modules/Admissions/Views/edit.php');

        self::assertStringNotContainsString('data-admission-subsection="cricket-ratings"', $partial);
        self::assertStringNotContainsString('<h3>Ratings</h3>', $partial . $new . $edit);
        foreach ([$new, $edit] as $form) {
            self::assertStringContainsString('Lead Information and Ratings', $form);
            self::assertStringContainsString('_lead_ratings_fields', $form);
            self::assertGreaterThanOrEqual(1, substr_count($form, 'Lead Information and Ratings'));
        }
    }

    public function testTrialRatingWorkflowHasCoachOnlyUpdateContract(): void
    {
        $root = dirname(__DIR__, 2);
        $service = (string) file_get_contents($root . '/app/Services/TrialService.php');
        $controller = (string) file_get_contents($root . '/app/Modules/Trials/Controllers/Browser.php');
        $routes = (string) file_get_contents($root . '/app/Modules/CRM/Config/Routes.php');
        $show = (string) file_get_contents($root . '/app/Modules/Trials/Views/show.php');
        $migration = (string) file_get_contents($root . '/app/Database/Migrations/20261015000200_AddTrialRatingsAndAdmissionRatingSnapshots.php');
        $javascript = (string) file_get_contents($root . '/public/assets/js/app.js');

        self::assertStringContainsString('function updateRatings', $service);
        self::assertStringContainsString('ratingsForAdmission', $service);
        self::assertStringContainsString('performance.update', $service);
        self::assertStringContainsString('lead.update', $service);
        self::assertStringContainsString('authorizeCoachRatingUpdate', $service);
        self::assertStringContainsString('Only the assigned Coach can update Trial ratings.', $service);
        self::assertStringContainsString('authorizeTrialDetails', $service);
        self::assertStringContainsString('canEditCoachRatings', $service);
        self::assertStringContainsString("'Coach assessment rating update'", $service);
        self::assertStringContainsString("\$value === null || \$value === ''", $service);
        self::assertStringContainsString('trial_ratings_updated', $service);
        self::assertStringContainsString('function ratings', $controller);
        self::assertStringContainsString('trials/(:num)/ratings', $routes);
        self::assertStringContainsString('ratings-for-admission', $routes);
        self::assertStringContainsString('fetch(ratingsUrl', $javascript);
        self::assertStringContainsString('Trial ratings', $show);
        self::assertStringNotContainsString('Coach-provided rating summary', $show);
        self::assertStringContainsString('$canEditCoachRatings', $show);
        self::assertStringContainsString('name="<?= esc($field) ?>"', $show);
        self::assertStringNotContainsString('name="<?= esc($field) ?>" required', $show);
        self::assertStringNotContainsString('disabled', $show);
        self::assertStringContainsString('Not rated', $show);
        self::assertStringContainsString('expected_updated_at', $show);
        self::assertStringContainsString("\$trial['coach_ratings'] = \$this->ratingProjection(\$trial);", $service);
        self::assertStringContainsString('AddTrialRatingsAndAdmissionRatingSnapshots', $migration);
        self::assertStringContainsString('AllowDecimalTrialRatings', file_get_contents($root . '/app/Database/Migrations/20261028000200_AllowDecimalTrialRatings.php'));
    }
}
