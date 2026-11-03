<?php

$studentPublic = ! empty($studentPublic ?? false);
$wizardPath = $studentPublic
    ? 'admission-tests/student/' . rawurlencode((string) ($studentLinkToken ?? ''))
    : 'admission-tests/' . (int) $test['id'];

$input = static function (string $field) use ($wizardInput, $test): string {
    $default = $test[$field] ?? '';
    if ($field === 'email' && trim((string) $default) === '') {
        $default = $test['applicant_email'] ?? '';
    }
    if ($field === 'contact_number' && trim((string) $default) === '') {
        $default = $test['applicant_phone'] ?? '';
    }

    if (array_key_exists($field, $wizardInput)) {
        return (string) $wizardInput[$field];
    }

    return (string) old($field, $default);
};
$fieldErrorMessage = static function (string $field) use ($fieldErrors): ?string {
    return isset($fieldErrors[$field]) ? (string) $fieldErrors[$field] : null;
};
$selectedPaperSet = strtoupper((string) ($test['test_variant'] ?? ''));
$studentTestLink = is_string($studentTestLink ?? null) ? trim($studentTestLink) : '';
$testSubmitted = ! empty($test['submitted_at']) || in_array(
    strtoupper((string) ($test['status'] ?? '')),
    ['TEST_SUBMITTED', 'MEETING_FOLLOW_UP', 'APPROVED_FOR_ADMISSION', 'REJECTED_CLOSED'],
    true
);
$staffAwaitingStudent = ! $studentPublic && ! $testSubmitted;
$pageLabels = ['A' => 'Paper Set A', 'B' => 'Paper Set B', 'C' => 'Paper Set C'];
$paperSetStepLabel = $studentPublic ? 'Selected Paper Set' : 'Select Paper Set';
$paperSetLegend = $studentPublic ? 'Selected Paper Set' : 'Select one paper set';
$paperSetChoices = $studentPublic
    ? [$selectedPaperSet => $pageLabels[$selectedPaperSet] ?? 'Selected Paper Set']
    : $pageLabels;
$prefillSources = $test['prefill_sources'] ?? [];
$personalFields = [
    'applicant_full_name' => 'Name of the Player',
    'father_name' => 'Father Name',
    'father_profession' => 'Father Profession',
    'age' => 'Age',
    'applicant_date_of_birth' => 'Date of Birth',
    'residence' => 'Residence',
    'contact_number' => 'Contact No 1',
    'secondary_contact_number' => 'Contact No 2',
    'email' => 'Email ID 1',
    'secondary_email' => 'Email ID 2',
    'coaching_start' => 'Date/Month (Wish to start the coaching)',
    'discovery_source' => 'How did you come to know about us',
    'reference_details' => 'Reference (Including address / contact no)',
    'achievements' => 'Achievements',
];
$choiceFields = [
    'represented_as' => ['label' => 'Represented', 'options' => ['School', 'Academy', 'District', 'State', 'MCA', 'None']],
    'coaching_type' => ['label' => 'Which type of coaching would you like to join', 'options' => ['Group', 'Personal', 'Short term', 'Advance Training', 'Others']],
    'playing_role' => ['label' => 'Cricket skill / Ability', 'options' => ['Batsman', 'Bowler', 'All-rounder']],
    'nearest_branch' => ['label' => 'Nearest branch your Residence', 'options' => ['Santa Cruz', 'Thane', 'Bhandup']],
];
$answerValue = static function (string $page, int $number) use ($wizardInput, $test): string {
    $field = 'answer_' . $page . '_' . $number;
    if (array_key_exists($field, $wizardInput)) {
        return (string) $wizardInput[$field];
    }

    foreach ($test['responses'] ?? [] as $response) {
        if ((string) ($response['variant'] ?? '') === $page && (int) ($response['question_number'] ?? 0) === $number) {
            return (string) ($response['answer'] ?? '');
        }
    }

    return '';
};
?>
<?php if ($studentPublic): ?>
    <?= view('admission_test_public_header', ['title' => 'Admission Test']) ?>
<?php else: ?>
    <?= view('staff_header', [
        'title' => 'Admission Test wizard',
        'nav' => $nav,
        'message' => $message ?? null,
        'error' => $error ?? null,
        'breadcrumbTrail' => [
            ['label' => 'CRM', 'url' => site_url('crm')],
            ['label' => 'Trials', 'url' => site_url('crm/trials')],
            ['label' => 'Start Admission Test'],
        ],
    ]) ?>
<?php endif; ?>

<div class="mcc-admission-test-wizard<?= $studentPublic ? ' mcc-public-admission-test-wizard' : '' ?>" data-admission-test-wizard data-initial-step="<?= (int) $wizardStep ?>" data-personal-details-action="<?= esc(site_url($wizardPath . '/personal-details')) ?>">
    <section class="mcc-card mcc-wizard-header" aria-labelledby="admission-test-wizard-heading">
        <div class="mcc-card-heading">
            <div>
                <span class="mcc-eyebrow"><?= $studentPublic ? 'Admission Test' : 'Trial #' . (int) $test['trial_id'] . ' · Test #' . (int) $test['id'] ?></span>
                <h2 id="admission-test-wizard-heading">Start Admission Test</h2>
                <p class="mcc-muted mb-0">Applicant: <?= esc((string) ($test['applicant_full_name'] ?: 'Unnamed applicant')) ?></p>
            </div>
            <?php if (! $studentPublic): ?><span class="mcc-badge mcc-badge-info">Test #<?= (int) $test['id'] ?></span><?php endif; ?>
        </div>
        <ol class="mcc-wizard-progress" aria-label="Admission Test steps, 3 total">
            <?php foreach (['Personal Details', $paperSetStepLabel, 'Answer Questions'] as $index => $label): ?>
                <li data-wizard-progress="<?= $index + 1 ?>" aria-label="Step <?= $index + 1 ?> of 3 — <?= esc($label) ?>"><span><?= $index + 1 ?></span><strong>Step <?= $index + 1 ?> of 3 — <?= esc($label) ?></strong></li>
            <?php endforeach; ?>
        </ol>
    </section>

    <?php if (! $studentPublic && ! empty($canGenerateStudentLink) && $selectedPaperSet !== ''): ?>
        <section class="mcc-card mcc-detail-card" aria-labelledby="wizard-student-link-heading">
            <div class="mcc-card-heading">
                <div>
                    <span class="mcc-eyebrow">Student access</span>
                    <h2 id="wizard-student-link-heading">Secure Student Link</h2>
                </div>
            </div>
            <p class="mcc-muted">Generate a one-time link for the locked <?= esc($pageLabels[$selectedPaperSet] ?? 'paper set') ?>. Generating again revokes the previous unused link.</p>
            <form method="post" action="<?= site_url('admission-tests/' . (int) $test['id'] . '/student-link') ?>" class="mb-3">
                <?= csrf_field() ?>
                <input type="hidden" name="return_to" value="wizard">
                <button class="mcc-button mcc-button-primary" type="submit">
                    <i class="fa-solid fa-link" aria-hidden="true"></i>
                    <?= $studentTestLink === '' ? 'Generate Secure Student Link' : 'Regenerate / Revoke Previous Link' ?>
                </button>
            </form>
            <?php if ($studentTestLink !== ''): ?>
                <div class="input-group" aria-label="Generated secure student link">
                    <input class="form-control mcc-wrap-anywhere" type="text" value="<?= esc($studentTestLink) ?>" readonly data-student-test-link>
                    <div class="input-group-append">
                        <button class="mcc-button mcc-button-secondary" type="button" data-copy-student-test-link>Copy link</button>
                    </div>
                </div>
                <p class="mcc-muted mt-2 mb-0">Copy this URL now. It is shown only once and expires after successful submission or regeneration.</p>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <?php if (! empty($fieldErrors['_form'])): ?>
        <div class="mcc-alert mcc-alert-danger" role="alert"><?= esc($fieldErrors['_form']) ?></div>
    <?php endif; ?>

    <?php if ($staffAwaitingStudent): ?>
        <section class="mcc-card mcc-empty-state" aria-live="polite">
            <h2>Waiting for student submission</h2>
            <p>Share the Secure Student Link above. Personal details and test questions will appear here after the student submits the test.</p>
        </section>
    <?php else: ?>
    <form method="post" action="<?= site_url($wizardPath . '/submit') ?>" id="admission-test-wizard-form" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="version" value="<?= (int) $test['version'] ?>">
        <input type="hidden" name="variant" value="<?= esc($selectedPaperSet) ?>">

        <section class="mcc-card mcc-wizard-step" data-wizard-step="1" aria-labelledby="wizard-personal-heading">
            <div class="mcc-card-heading"><div><span class="mcc-eyebrow">Step 1 of 3</span><h3 id="wizard-personal-heading">Personal Details</h3></div></div>
            <p class="mcc-muted">Fields follow the Back Side.pdf labels. Required indicators are applied only where the approved workflow defines them.</p>
            <div class="row">
                <?php foreach ($personalFields as $field => $label): ?>
                    <?php $fieldError = $fieldErrorMessage($field); $long = in_array($field, ['reference_details', 'achievements'], true); ?>
                    <?php $isDateField = in_array($field, ['applicant_date_of_birth', 'coaching_start'], true); ?>
                    <div class="col-12 <?= $long ? '' : 'col-md-6' ?> mb-3">
                        <label for="wizard-<?= esc($field) ?>">
                            <?= esc($label) ?><?= $field === 'applicant_full_name' ? ' *' : '' ?>
                            <?php if (! $studentPublic && isset($prefillSources[$field]) && ! array_key_exists($field, $wizardInput)): ?>
                                <small class="mcc-prefill-indicator">Prefilled from <?= esc((string) $prefillSources[$field]) ?></small>
                            <?php endif; ?>
                        </label>
                        <?php if ($long): ?>
                            <textarea class="form-control" id="wizard-<?= esc($field) ?>" name="<?= esc($field) ?>" rows="3"><?= esc($input($field)) ?></textarea>
                        <?php else: ?>
                            <input class="form-control" id="wizard-<?= esc($field) ?>" name="<?= esc($field) ?>" value="<?= esc($input($field)) ?>" <?= $field === 'applicant_full_name' ? 'required' : '' ?> <?= $isDateField ? 'data-mcc-date-picker data-mcc-date-picker-no-toggle data-mcc-date-format="d-m-Y" data-mcc-date-submit-format="Y-m-d" inputmode="numeric" autocomplete="off" placeholder="DD-MM-YYYY"' : '' ?>>
                        <?php endif; ?>
                        <?php if ($fieldError !== null): ?><p class="mcc-field-error" role="alert"><?= esc($fieldError) ?></p><?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <?php foreach ($choiceFields as $field => $config): ?>
                    <?php $fieldError = $fieldErrorMessage($field); ?>
                    <div class="col-12 col-md-6 mb-3">
                        <label for="wizard-<?= esc($field) ?>">
                            <?= esc($config['label']) ?>
                            <?php if (! $studentPublic && isset($prefillSources[$field]) && ! array_key_exists($field, $wizardInput)): ?>
                                <small class="mcc-prefill-indicator">Prefilled from <?= esc((string) $prefillSources[$field]) ?></small>
                            <?php endif; ?>
                        </label>
                        <select class="form-control" id="wizard-<?= esc($field) ?>" name="<?= esc($field) ?>">
                            <option value="">Select</option>
                            <?php foreach ($config['options'] as $option): ?><option value="<?= esc($option) ?>" <?= $input($field) === $option ? 'selected' : '' ?>><?= esc($option) ?></option><?php endforeach; ?>
                        </select>
                        <?php if ($fieldError !== null): ?><p class="mcc-field-error" role="alert"><?= esc($fieldError) ?></p><?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="mcc-card mcc-wizard-step mcc-paper-set-step" data-wizard-step="2" aria-labelledby="wizard-paper-set-heading" hidden>
            <?php if (! $studentPublic): ?>
                <div class="mcc-card-heading">
                    <div>
                        <span class="mcc-eyebrow">Step 2 of 3</span>
                        <h3 id="wizard-paper-set-heading"><?= esc($paperSetStepLabel) ?></h3>
                    </div>
                    <span class="mcc-badge mcc-badge-success">Locked</span>
                </div>
            <?php endif; ?>
            <?php if (! $studentPublic): ?>
                <p class="mcc-muted">One paper set is required. The selected set is locked and cannot be changed after selection.</p>
            <?php endif; ?>
            <?php if ($fieldErrorMessage('variant') !== null): ?>
                <p class="mcc-field-error" role="alert"><?= esc((string) $fieldErrorMessage('variant')) ?></p>
            <?php endif; ?>
            <fieldset class="mcc-paper-set-options" aria-describedby="wizard-paper-set-help">
                <legend class="visually-hidden"><?= esc($paperSetLegend) ?></legend>
                <p id="wizard-paper-set-help" class="visually-hidden">Each paper set contains 10 questions.</p>
                <?php foreach ($paperSetChoices as $paperSet => $paperSetLabel): ?>
                    <label class="mcc-paper-set-choice mcc-admission-test-radio-label" for="wizard-paper-set-<?= esc($paperSet) ?>">
                        <input id="wizard-paper-set-<?= esc($paperSet) ?>" type="radio" name="paper_set_display" value="<?= esc($paperSet) ?>" <?= $selectedPaperSet === $paperSet ? 'checked' : '' ?> disabled>
                        <span>
                            <strong><?= esc($paperSetLabel) ?></strong>
                            <small>10 questions<?= $selectedPaperSet === $paperSet ? ' · Selected and locked' : '' ?></small>
                        </span>
                    </label>
                <?php endforeach; ?>
            </fieldset>
        </section>

        <?php foreach ($questions as $page => $pageQuestions): ?>
            <section class="mcc-card mcc-wizard-step" data-wizard-step="3" aria-labelledby="wizard-page-<?= esc($page) ?>-heading" hidden>
                <div class="mcc-card-heading"><div><span class="mcc-eyebrow">Step 3 of 3</span><h3 id="wizard-page-<?= esc($page) ?>-heading">Answer Questions · <?= esc($pageLabels[$page]) ?></h3></div><span class="mcc-badge mcc-badge-success">Locked set</span></div>
                <p class="mcc-muted">Select from the printed options. Questions remain optional where the PDF has no required marker.</p>
                <div class="mcc-wizard-question-list">
                    <?php foreach ($pageQuestions as $index => $question): ?>
                        <?php $number = $index + 1; $field = 'answer_' . $page . '_' . $number; $fieldError = $fieldErrorMessage($field); $answer = $answerValue($page, $number); ?>
                        <fieldset class="mcc-wizard-question">
                            <legend><?= $number ?>. <?= esc($question['prompt']) ?></legend>
                            <div class="mcc-wizard-options">
                                <?php foreach ($question['options'] as $option): ?>
                                    <label class="mcc-wizard-option mcc-admission-test-radio-label" for="<?= esc($field . '_' . md5($option)) ?>">
                                        <input id="<?= esc($field . '_' . md5($option)) ?>" type="radio" name="<?= esc($field) ?>" value="<?= esc($option) ?>" <?= $answer === $option ? 'checked' : '' ?>>
                                        <span><?= esc($option) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <?php if ($fieldError !== null): ?><p class="mcc-field-error" role="alert"><?= esc($fieldError) ?></p><?php endif; ?>
                        </fieldset>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>

        <div class="mcc-wizard-controls">
            <button class="mcc-button mcc-button-secondary" type="button" data-wizard-previous>Back</button>
            <button class="mcc-button mcc-button-primary" type="button" data-wizard-next>Continue</button>
            <button class="mcc-button mcc-button-primary" type="submit" data-wizard-submit>Submit Test</button>
        </div>
    </form>
    <?php endif; ?>
</div>
<?php if ($studentPublic): ?>
    <?= view('admission_test_public_footer') ?>
<?php else: ?>
    <?= view('staff_footer') ?>
<?php endif; ?>
