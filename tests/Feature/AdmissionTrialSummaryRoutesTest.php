<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

final class AdmissionTrialSummaryRoutesTest extends TestCase
{
    public function testExistingAdmissionDetailRouteRemainsBranchAuthorized(): void
    {
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Admissions/Config/Routes.php');
        $service = file_get_contents(ROOTPATH . 'app/Services/AdmissionService.php');
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Admissions/Controllers/Browser.php');

        self::assertIsString($routes);
        self::assertIsString($service);
        self::assertIsString($controller);
        $id = '$id';
        self::assertStringContainsString("get('(:num)/page'", $routes);
        self::assertStringContainsString('admission.view', $service);
        self::assertStringContainsString("service('admission')->details($id)", $controller);
        self::assertStringContainsString('documents', $controller);
    }

    public function testTrialRatingDetailAndFormContractIsPresent(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Services/TrialService.php');
        $view = file_get_contents(ROOTPATH . 'app/Modules/Trials/Views/show.php');
        $model = file_get_contents(ROOTPATH . 'app/Modules/Trials/Models/TrialModel.php');

        self::assertIsString($service);
        self::assertIsString($view);
        self::assertIsString($model);
        self::assertStringContainsString("\$trial['coach_ratings'] = \$this->ratingProjection(\$trial);", $service);
        self::assertStringContainsString('authorizeTrialDetails', $service);
        self::assertStringContainsString('authorizeCoachRatingUpdate', $service);
        self::assertStringContainsString('canEditCoachRatings', $service);
        self::assertStringContainsString("'Coach assessment rating update'", $service);
        self::assertStringNotContainsString('Coach-provided rating summary', $view);
        self::assertStringContainsString('$canEditCoachRatings', $view);
        self::assertStringNotContainsString('disabled', $view);
        self::assertStringContainsString('Not rated', $view);
        self::assertStringContainsString('Not rated', $view);
        self::assertStringContainsString('for ($rating = 0; $rating <= 100; $rating++)', $view);
        self::assertStringNotContainsString('data-trial-rating-category=', $view);
        self::assertStringContainsString('$ratingOption', $view);
        foreach ([
            'ability_overall',
            'ability_batting',
            'ability_bowling',
            'ability_wicketkeeping',
            'ability_fitness',
        ] as $field) {
            self::assertStringContainsString("'{$field}'", $model);
            self::assertStringContainsString("'{$field}'", $view);
        }
    }

    public function testAdmissionLeadSearchIncludesPendingAdmissionIdentityAndScopedTrialLoading(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Services/AdmissionService.php');
        $new = file_get_contents(ROOTPATH . 'app/Modules/Admissions/Views/new.php');
        $javascript = file_get_contents(ROOTPATH . 'public/assets/js/app.js');

        self::assertIsString($service);
        self::assertIsString($new);
        self::assertIsString($javascript);
        self::assertStringContainsString("whereIn('l.status', ['lead', 'admission pending'])", $service);
        self::assertStringContainsString("ax.status = 'CONVERTED' OR ax.converted_player_id IS NOT NULL", $service);
        self::assertStringContainsString("'data-search-text'", $new);
        self::assertStringContainsString('data-admission-trial-control', $new);
        self::assertStringContainsString('data-lead-id', $new);
        self::assertStringContainsString('filterTrials', $javascript);
        self::assertStringContainsString('ratingsUrl.replace', $javascript);
    }
}
