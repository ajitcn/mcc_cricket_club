<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AdmissionModuleContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = dirname(__DIR__, 2);
    }

    public function testCompleteAndEditFormsUseControlledDropdowns(): void
    {
        $complete = file_get_contents($this->root . '/app/Modules/Admissions/Views/complete.php');
        $edit = file_get_contents($this->root . '/app/Modules/Admissions/Views/edit.php');
        self::assertIsString($complete);
        self::assertIsString($edit);

        foreach (['gender', 'playing_role', 'batting_style', 'bowling_style', 'strong_hand'] as $field) {
            self::assertStringContainsString('name="<?= esc($field) ?>"', $complete);
            self::assertStringContainsString('isset($admissionOptions[$field])', $complete);
            self::assertStringContainsString("'" . $field . "' =>", $edit);
        }

        self::assertStringContainsString("'name' => 'batch_id'", $complete);
        self::assertStringContainsString("'name' => 'batch_id'", $edit);
        self::assertStringContainsString("oldValue('batch_id')", $complete);
        self::assertStringContainsString("oldValue('batch_id')", $edit);
    }

    public function testAdmissionPeriodDatesUseSharedDatepickersAcrossFormsAndDetail(): void
    {
        $new = file_get_contents($this->root . '/app/Modules/Admissions/Views/new.php');
        $edit = file_get_contents($this->root . '/app/Modules/Admissions/Views/edit.php');
        $renew = file_get_contents($this->root . '/app/Modules/Admissions/Views/renew.php');
        $show = file_get_contents($this->root . '/app/Modules/Admissions/Views/show.php');

        self::assertIsString($new);
        self::assertIsString($edit);
        self::assertIsString($renew);
        self::assertIsString($show);

        self::assertSame(1, substr_count($new, 'name="start_date"'));
        self::assertSame(1, substr_count($new, 'name="end_date"'));
        self::assertStringContainsString('Admission Start Date', $new);
        self::assertStringContainsString('Admission End Date', $new);
        self::assertSame(6, substr_count($new, 'data-mcc-date-picker'));
        self::assertStringContainsString('name="start_date"', $edit);
        self::assertStringContainsString('name="end_date"', $edit);
        self::assertSame(6, substr_count($edit, 'data-mcc-date-picker'));
        self::assertStringContainsString('name="start_date"', $renew);
        self::assertStringContainsString('name="end_date"', $renew);
        self::assertSame(2, substr_count($renew, 'data-mcc-date-picker'));
        self::assertStringContainsString('Admission Start Date', $show);
        self::assertStringContainsString('Admission End Date', $show);
    }

    public function testInitialAdmissionLeadAndDatesAreOptionalButRenewalDatesRemainRequired(): void
    {
        $new = (string) file_get_contents($this->root . '/app/Modules/Admissions/Views/new.php');
        $edit = (string) file_get_contents($this->root . '/app/Modules/Admissions/Views/edit.php');
        $renew = (string) file_get_contents($this->root . '/app/Modules/Admissions/Views/renew.php');
        $service = (string) file_get_contents($this->root . '/app/Services/AdmissionService.php');
        $controller = (string) file_get_contents($this->root . '/app/Modules/Admissions/Controllers/Browser.php');

        self::assertDoesNotMatchRegularExpression('/name="start_date"[\s\S]{0,300}\brequired\b/', $new);
        self::assertDoesNotMatchRegularExpression('/name="end_date"[\s\S]{0,300}\brequired\b/', $new);
        self::assertDoesNotMatchRegularExpression('/name="start_date"[\s\S]{0,300}\brequired\b/', $edit);
        self::assertDoesNotMatchRegularExpression('/name="end_date"[\s\S]{0,300}\brequired\b/', $edit);
        self::assertStringContainsString('name="start_date"', $renew);
        self::assertStringContainsString('name="end_date"', $renew);
        self::assertMatchesRegularExpression('/name="start_date"[\s\S]{0,300}\brequired\b/', $renew);
        self::assertMatchesRegularExpression('/name="end_date"[\s\S]{0,300}\brequired\b/', $renew);
        self::assertStringNotContainsString("'attributes' => ['required' => true, 'data-admission-lead-control'", $new);
        self::assertStringContainsString('normalizeOptionalAdmissionDate', $service);
        self::assertStringContainsString("throw new RuntimeException('Trial requires a Lead.')", $service);
        self::assertStringContainsString("service('admission')->createDirect(\$data)", $controller);
    }

    public function testAdmissionValidationAndErrorRedirectsRemainBranchSafe(): void
    {
        $service = file_get_contents($this->root . '/app/Services/AdmissionService.php');
        $controller = file_get_contents($this->root . '/app/Modules/Admissions/Controllers/Browser.php');
        self::assertIsString($service);
        self::assertIsString($controller);
        self::assertStringContainsString('private const PROFILE_OPTIONS', $service);
        self::assertStringContainsString('Invalid admission option.', $service);
        self::assertStringContainsString("'branch_id' => \$branch", $service);
        self::assertStringContainsString("with('field_errors'", $controller);
        self::assertStringContainsString("redirect()->to('/admissions/new')", $controller);
        self::assertStringContainsString("redirect()->to('/admissions/' . \$id . '/complete')", $controller);
    }

    public function testAdmissionNumbersAreGeneratedServerSideAndPreservedOnUpdates(): void
    {
        $service = (string) file_get_contents($this->root . '/app/Services/AdmissionService.php');
        $model = (string) file_get_contents($this->root . '/app/Modules/Admissions/Models/AdmissionModel.php');
        $migration = (string) file_get_contents(
            $this->root . '/app/Database/Migrations/20261016000300_AddAdmissionNumberUniqueness.php',
        );

        self::assertStringContainsString('private function generateAdmissionNumber', $service);
        self::assertStringContainsString("SELECT next_number FROM admission_number_sequences WHERE id = 1 FOR UPDATE", $service);
        self::assertStringContainsString("return 'MCC-' . str_pad", $service);
        self::assertGreaterThanOrEqual(6, substr_count($service, "'admission_number' => \$admissionNumber"));
        self::assertStringContainsString("'admission_number'", $model);
        self::assertStringContainsString('ADD UNIQUE INDEX admissions_admission_number_unique', $migration);
        self::assertStringContainsString('GROUP BY admission_number', $migration);
        self::assertStringContainsString('existing duplicate references require review', $migration);
        self::assertStringNotContainsString("'admission_number' => \$input", $service);
    }

    public function testAdmissionListAndDetailUseTheCanonicalAdmissionNumberProjection(): void
    {
        $service = (string) file_get_contents($this->root . '/app/Services/AdmissionService.php');
        $list = (string) file_get_contents($this->root . '/app/Modules/Admissions/Views/index.php');
        $detail = (string) file_get_contents($this->root . '/app/Modules/Admissions/Views/show.php');
        $controller = (string) file_get_contents($this->root . '/app/Modules/Admissions/Controllers/Browser.php');

        self::assertStringContainsString('a.admission_number AS admission_number', $service);
        self::assertStringContainsString("Admission #<?= esc(\$admissionNumber !== '' ? \$admissionNumber : 'Missing number') ?>", $detail);
        self::assertStringContainsString("\$admission['admission_number'] ?? ''", $list);
        self::assertStringContainsString("'Missing number'", $list);
        self::assertStringContainsString('mcc-admission-identity', $list);
        self::assertStringNotContainsString('<th scope="col">Applicant / lead</th>', $list);
        self::assertStringContainsString('<?= esc($applicantName !== \'\' ? $applicantName : \'Applicant\') ?>', $list);
        self::assertStringContainsString('#<?= esc($admissionNumber !== \'\' ? $admissionNumber : \'Missing number\') ?>', $list);
        self::assertStringContainsString('Lead: <?= esc($leadReference) ?>', $list);
        self::assertStringContainsString("site_url('crm/leads/' . (int) \$admission['lead_id'])", $list);
        self::assertStringNotContainsString(
            '<th scope="row">#<?= (int) $admission[\'id\'] ?>',
            $list,
        );
        self::assertStringNotContainsString("(int) (\$a['id'] ?? 0)", $detail);
        self::assertStringContainsString("'Admission details'", $controller);
    }

    public function testAdmissionListUsesSharedSlidingPaginationWindow(): void
    {
        $service = (string) file_get_contents($this->root . '/app/Services/AdmissionService.php');
        $controller = (string) file_get_contents($this->root . '/app/Modules/Admissions/Controllers/Browser.php');
        $list = (string) file_get_contents($this->root . '/app/Modules/Admissions/Views/index.php');

        self::assertStringContainsString('paginatedForAuthorizedBranches', $service);
        self::assertStringContainsString('countAllResults()', $service);
        self::assertStringContainsString("orderBy('a.id', 'DESC')", $service);
        self::assertStringContainsString('pageNumber', $controller);
        self::assertStringContainsString('mcc_follow_up_page_window', $list);
        self::assertStringContainsString('aria-label="Admission pages"', $list);
        self::assertStringContainsString('Previous', $list);
        self::assertStringContainsString('Next', $list);
        self::assertStringContainsString('mcc-pagination-ellipsis', $list);
        self::assertStringContainsString('array_merge($queryFilters, [\'page\' => $page])', $list);
        self::assertStringContainsString("a.admission_number AS admission_number", $service);
        self::assertStringNotContainsString('<th scope="row">#<?= (int) $admission[\'id\'] ?>', $list);
    }

    public function testAdmissionNumberBackfillIsDeterministicIdempotentAndForwardOnly(): void
    {
        $migration = (string) file_get_contents(
            $this->root . '/app/Database/Migrations/20261016000400_BackfillAdmissionNumbers.php',
        );

        self::assertStringContainsString('ORDER BY created_at, id', $migration);
        self::assertStringContainsString("hash('sha256', \$stableInput)", $migration);
        self::assertStringContainsString("WHERE admission_number IS NULL OR admission_number = ''", $migration);
        self::assertStringContainsString("->where('admission_number IS NULL', null, false)", $migration);
        self::assertStringContainsString('Existing duplicate Admission numbers require review.', $migration);
        self::assertStringContainsString('Deterministic Admission-number collision detected.', $migration);
        self::assertStringContainsString('Forward-only', $migration);
        self::assertStringNotContainsString("->update(['admission_number' => null])", $migration);
    }

    public function testAdmissionNumberConversionUsesStableMccSeriesAndSequenceTable(): void
    {
        $migration = (string) file_get_contents(
            $this->root . '/app/Database/Migrations/20261016000500_ConvertAdmissionNumbersToMccSeries.php',
        );

        self::assertStringContainsString('ORDER BY created_at IS NULL, created_at, id', $migration);
        self::assertStringContainsString("'MCC-' . str_pad", $migration);
        self::assertStringContainsString('admission_number_sequences', $migration);
        self::assertStringContainsString('ensureUniqueIndex', $migration);
        self::assertStringContainsString('Forward-only', $migration);
        self::assertStringContainsString('MCC-TEMP-', $migration);
    }

    public function testPendingAdmissionLeadSearchPreservesBranchAndSearchIdentity(): void
    {
        $service = file_get_contents($this->root . '/app/Services/AdmissionService.php');
        $new = file_get_contents($this->root . '/app/Modules/Admissions/Views/new.php');
        $javascript = file_get_contents($this->root . '/public/assets/js/app.js');

        self::assertIsString($service);
        self::assertIsString($new);
        self::assertIsString($javascript);
        self::assertStringContainsString("whereIn('l.status', ['lead', 'admission pending'])", $service);
        self::assertStringContainsString("ax.status = 'CONVERTED' OR ax.converted_player_id IS NOT NULL", $service);
        self::assertStringContainsString("'data-search-text'", $new);
        self::assertStringContainsString("'email'", $service);
        self::assertStringContainsString("'phone'", $service);
        self::assertStringContainsString("'#' . (string) \$lead['id']", $new);
        self::assertStringContainsString("option.getAttribute('data-search-text')", $javascript);
    }

    public function testAdmissionEditUsesTheStandardPageInsteadOfTheActionModal(): void
    {
        $index = file_get_contents($this->root . '/app/Modules/Admissions/Views/index.php');
        $edit = file_get_contents($this->root . '/app/Modules/Admissions/Views/edit.php');
        $controller = file_get_contents($this->root . '/app/Modules/Admissions/Controllers/Browser.php');

        self::assertIsString($index);
        self::assertIsString($edit);
        self::assertIsString($controller);
        self::assertStringContainsString(
            'href="<?= site_url(\'admissions/\' . $admissionId . \'/edit\') ?>"',
            $index,
        );
        self::assertStringNotContainsString('data-admission-action="edit"', $index);
        self::assertStringNotContainsString('edit?fragment=1', $index);
        self::assertStringContainsString("view('staff_header'", $edit);
        self::assertStringContainsString("view('staff_footer')", $edit);
        self::assertStringNotContainsString('$isFragment', $edit);
        self::assertStringNotContainsString('data-admission-modal-close', $edit);
        self::assertStringNotContainsString('?fragment=1', $edit);
        self::assertStringContainsString("->to('/admissions/' . \$id . '/edit')", $controller);
    }

    public function testAdmissionEditPersistsStatusThroughTheLifecycleService(): void
    {
        $service = (string) file_get_contents($this->root . '/app/Services/AdmissionService.php');
        $controller = (string) file_get_contents($this->root . '/app/Modules/Admissions/Controllers/Browser.php');
        $new = (string) file_get_contents($this->root . '/app/Modules/Admissions/Views/new.php');

        self::assertStringContainsString("'status' => \$requestedStatus", $service);
        self::assertStringNotContainsString('checklistComplete($a)', $service);
        self::assertStringNotContainsString('Admission checklist incomplete.', $service);
        self::assertStringContainsString('admission_status_changed', $service);
        self::assertStringContainsString('recordHistory(', $service);
        self::assertStringNotContainsString('applyFormStatus($id, $data);', $controller);
        self::assertStringContainsString("'selected' => \$old('status')", $new);
        self::assertStringContainsString('forceNativeSelect', $new);
    }

    public function testChecklistIsDetailOnlyAndNotRenderedOnTheAdmissionList(): void
    {
        $index = (string) file_get_contents($this->root . '/app/Modules/Admissions/Views/index.php');
        $show = (string) file_get_contents($this->root . '/app/Modules/Admissions/Views/show.php');

        self::assertStringNotContainsString('>Checklist<', $index);
        self::assertStringNotContainsString('mcc-checklist-state', $index);
        self::assertStringNotContainsString('data-admission-action="checklist"', $index);
        self::assertStringNotContainsString('data-admission-panel="checklist"', $index);
        self::assertStringNotContainsString('Readiness', $show);
        self::assertStringNotContainsString('Phase 1 checklist (AD-02)', $show);
        $routes = (string) file_get_contents($this->root . '/app/Modules/Admissions/Config/Routes.php');
        $service = (string) file_get_contents($this->root . '/app/Services/AdmissionService.php');
        self::assertStringContainsString("post('(:num)/checklist'", $routes);
        self::assertStringContainsString('updateChecklist', $service);
    }

    public function testAdmissionFormsExposeSupportedReferenceFieldsWithSharedControls(): void
    {
        $new = file_get_contents($this->root . '/app/Modules/Admissions/Views/new.php');
        $edit = file_get_contents($this->root . '/app/Modules/Admissions/Views/edit.php');
        $partial = file_get_contents($this->root . '/app/Modules/Admissions/Views/_complete_form_fields.php');
        $actions = file_get_contents($this->root . '/app/Modules/Admissions/Views/_form_actions.php');
        $sections = file_get_contents($this->root . '/app/Modules/Admissions/Views/_form_sections.php');
        $controller = file_get_contents($this->root . '/app/Modules/Admissions/Controllers/Browser.php');

        self::assertIsString($new);
        self::assertIsString($edit);
        self::assertIsString($partial);
        self::assertIsString($controller);

        $controller = str_replace(["\r\n", "\r"], "\n", $controller);

        foreach (['applicant_email', 'applicant_phone', 'start_date', 'end_date', 'branch_id', 'batch_id', 'trial_id'] as $field) {
            if (in_array($field, ['applicant_email', 'applicant_phone'], true)) {
                self::assertStringContainsString("name=\"' . \$field . '\"", $new);
                continue;
            }

            if (in_array($field, ['start_date', 'end_date'], true)) {
                self::assertStringContainsString('name="' . $field . '"', $new);
                continue;
            }

            self::assertStringContainsString("'name' => '" . $field . "'", $new);
        }

        foreach (['applicant_email', 'applicant_phone', 'applicant_date_of_birth'] as $field) {
            self::assertStringContainsString("'name' => '" . $field . "'", $edit);
        }
        foreach (['batch_id', 'trial_id'] as $field) {
            self::assertStringContainsString("'name' => '" . $field . "'", $edit);
        }
        self::assertStringContainsString('name="start_date"', $edit);
        self::assertStringContainsString('name="end_date"', $edit);

        self::assertStringContainsString('data-mcc-date-picker', $new);
        self::assertStringContainsString('data-mcc-date-picker', $edit);
        self::assertStringContainsString("view('components/admission-select'", $new);
        self::assertStringContainsString("view('components/admission-select'", $edit);
        self::assertStringContainsString('_form_actions', $new);
        self::assertStringContainsString('_form_actions', $edit);
        self::assertStringContainsString('mcc-form-actions', $actions);
        self::assertStringContainsString('Personal Details', $sections);
        self::assertStringContainsString('Applicant / personal information', $edit);
        $branchSubsectionPosition = strpos($new, 'data-admission-subsection="coaching-branch"');
        $applicantPosition = strpos($new, 'legend>Applicant information</legend>');
        $leadPosition = strpos($new, '<legend>Lead Information and Ratings</legend>');
        $startDatePosition = strpos($new, 'name="start_date"');
        $endDatePosition = strpos($new, 'name="end_date"');

        self::assertIsInt($branchSubsectionPosition);
        self::assertIsInt($applicantPosition);
        self::assertIsInt($leadPosition);
        self::assertIsInt($startDatePosition);
        self::assertIsInt($endDatePosition);
        self::assertLessThan($applicantPosition, $branchSubsectionPosition);
        self::assertLessThan($leadPosition, $applicantPosition);
        self::assertLessThan($leadPosition, $startDatePosition);
        self::assertLessThan($leadPosition, $endDatePosition);
        self::assertSame(1, substr_count($new, 'name="start_date"'));
        self::assertSame(1, substr_count($new, 'name="end_date"'));
        self::assertStringContainsString(
            'data-admission-subsection="coaching-branch" data-admission-subsection-target="coaching" data-admission-subsection-order="5"',
            $new,
        );
        self::assertStringContainsString('data-mcc-date-picker', $new);
        self::assertStringContainsString('data-mcc-date-submit-format="Y-m-d"', $new);
        $newWithSharedSections = $new . $sections . $partial;
        $editWithSharedSections = $edit . $sections . $partial;
        foreach ([
            'Personal Details',
            'Coaching Plan',
            'Cricket Information',
            'Lead Information and Ratings',
            'Fees',
            'Coaching Instalments',
            'Hostel Instalments',
            'Declarations and Signatures',
        ] as $section) {
            self::assertStringContainsString($section, $newWithSharedSections);
        }
        foreach ([
            'Personal Details',
            'Coaching Plan',
            'Cricket Information',
            'Lead Information and Ratings',
            'Fees',
            'Coaching Instalments',
            'Hostel Instalments',
            'Declarations and Signatures',
        ] as $section) {
            self::assertStringContainsString($section, $editWithSharedSections);
        }
        self::assertSame(8, substr_count($newWithSharedSections, 'data-admission-section="'));
        self::assertSame(8, substr_count($editWithSharedSections, 'data-admission-section="'));
        self::assertStringContainsString('data-admission-subsection-target="lead-ratings"', $edit);
        self::assertStringNotContainsString('id="admission-number"', $edit);
        self::assertStringContainsString('Save Changes', $edit);
        self::assertStringContainsString('mcc-admission-section-summary', $new);
        self::assertStringContainsString('mcc-admission-section-summary', $edit);
        self::assertStringContainsString("'trials' => service('admission')->trialsForBranch", $controller);
        self::assertStringContainsString("->to('/admissions/new')", $controller);
        self::assertStringContainsString("->to('/admissions/' . \$id . '/edit')", $controller);
        self::assertStringNotContainsString('redirect()->back()', $controller);

        self::assertStringContainsString(
            "->to('/admissions/new')\n                ->withInput()",
            $controller
        );
        self::assertStringContainsString('withInput()', $controller);
        self::assertStringContainsString("with('error'", $controller);
    }

    public function testAdmissionContactLinkingIsNormalizedAndLockedForIdempotentRetries(): void
    {
        $playerService = file_get_contents($this->root . '/app/Services/PlayerService.php');
        self::assertIsString($playerService);
        self::assertStringContainsString('FOR UPDATE', $playerService);
        self::assertStringContainsString('normalizeContactIdentity', $playerService);
        self::assertStringContainsString('$existingEmail === \'\' && $normalizedEmail !== \'\'', $playerService);
        self::assertStringContainsString('$existingPhone === \'\' && $normalizedPhone !== \'\'', $playerService);
    }
}
