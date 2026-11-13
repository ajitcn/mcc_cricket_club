<?php

$value = static function (string $field) use ($test): string {
    $value = old($field, $test[$field] ?? null);

    return trim((string) $value) === '' ? 'Not answered' : (string) $value;
};
$inputValue = static function (string $field) use ($test): string {
    $value = trim((string) old($field, $test[$field] ?? ''));

    if (in_array($field, ['applicant_date_of_birth', 'coaching_start'], true) && $value !== '') {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
            $value = $date !== false && $date->format('Y-m-d') === $value
                ? $date->format('d-m-Y')
                : '';
        } elseif (preg_match('/^\d{2}-\d{2}-\d{4}$/', $value) !== 1) {
            $value = '';
        }
    }

    return esc($value);
};
$selected = static function (string $field, string $option) use ($test): string {
    return (string) old($field, $test[$field] ?? '') === $option ? 'selected' : '';
};
$personalDetailsErrors = session()->getFlashdata('personal_details_errors');
$personalDetailsErrors = is_array($personalDetailsErrors) ? $personalDetailsErrors : [];
$fieldErrors = session()->getFlashdata('field_errors');
$fieldErrors = is_array($fieldErrors) ? $fieldErrors : [];
$personalDetailsPanelOpen = $personalDetailsErrors !== [];
$personalError = static function (string $field) use ($personalDetailsErrors): string {
    $message = trim((string) ($personalDetailsErrors[$field] ?? ''));

    if ($message === '') {
        return '';
    }

    return '<div class="invalid-feedback d-block" role="alert">' . esc($message) . '</div>';
};
$dateTime = static function (?string $date): string {
    return mcc_follow_up_datetime($date, 'Not available');
};
$status = strtoupper((string) ($test['status'] ?? ''));
$testStatusPresentation = mcc_admission_test_status_presentation($status);
$responses = [];
foreach ($test['responses'] ?? [] as $response) {
    $responses[(string) ($response['variant'] ?? '')][(int) ($response['question_number'] ?? 0)] = $response;
}
$pageLabels = ['A' => 'Paper Set A', 'B' => 'Paper Set B', 'C' => 'Paper Set C'];
$selectedPaperSet = strtoupper((string) ($test['test_variant'] ?? ''));
$isSubmitted = trim((string) ($test['submitted_at'] ?? '')) !== ''
    || in_array($status, ['TEST_SUBMITTED', 'MEETING_FOLLOW_UP', 'APPROVED_FOR_ADMISSION', 'REJECTED', 'ON_HOLD', 'REJECTED_CLOSED'], true);
$pendingAdmissionId = (int) ($test['admission_id'] ?? $test['pending_admission_id'] ?? 0);
$canCreatePendingAdmission = $status === 'APPROVED_FOR_ADMISSION'
    && $pendingAdmissionId === 0
    && service('authorization')->can('admission.create', (string) ($test['branch_id'] ?? ''));
$assessmentRemarks = trim((string) ($test['assessment_remarks'] ?? ''));
$assessmentRemarksEditable = (bool) ($test['assessment_remarks_editable'] ?? false);
$assessmentRemarksEditableUntil = $test['assessment_remarks_editable_until'] ?? null;
$assessmentRemarksWindowInvalid = (bool) ($test['assessment_remarks_window_invalid'] ?? false);
$canUpdateAssessmentRemarks = service('authorization')->can('admission.update', (string) ($test['branch_id'] ?? ''));
$canChangeStatus = $canUpdateAssessmentRemarks
    && in_array($status, ['TEST_SUBMITTED', 'MEETING_FOLLOW_UP', 'APPROVED_FOR_ADMISSION', 'REJECTED', 'ON_HOLD'], true);
$followUpDateInput = (string) old('follow_up_date', '');
$followUpTimeInput = trim((string) old('follow_up_time', ''));
$followUpTimeValue = DateTimeImmutable::createFromFormat('!H:i', $followUpTimeInput);
if ($followUpTimeValue === false) {
    $followUpTimeValue = DateTimeImmutable::createFromFormat('!h:i A', strtoupper($followUpTimeInput));
}
if ($followUpTimeValue !== false) {
    $followUpTimeInput = $followUpTimeValue->format('H:i');
}
$studentTestLink = session()->getFlashdata('student_test_link');
$studentTestLink = is_string($studentTestLink) ? trim($studentTestLink) : '';
?>
<?= view('staff_header', [
    'title' => 'Admission Test',
    'nav' => $nav,
    'message' => $message ?? null,
    'error' => $error ?? null,
    'breadcrumbTrail' => [
        ['label' => 'CRM', 'url' => site_url('crm')],
        ['label' => 'Admission Test', 'url' => site_url('admission-tests')],
        ['label' => 'Test Details'],
    ],
]) ?>

<div class="mcc-admission-test-detail">
    <section class="mcc-card mcc-detail-card" aria-labelledby="admission-test-summary-heading">
        <div class="mcc-card-heading">
            <div>
                <span class="mcc-eyebrow">Admission Test</span>
                <h2 id="admission-test-summary-heading">Test Details</h2>
            </div>
            <div class="mcc-admission-actions mcc-admission-action-menu" data-admission-actions>
                <button class="mcc-button mcc-button-primary" type="button" data-admission-actions-toggle
                    aria-controls="admission-test-actions" aria-expanded="false" aria-haspopup="true">
                    <i class="fa-solid fa-ellipsis-vertical" aria-hidden="true"></i>
                    <span>Actions</span>
                </button>
                <div class="mcc-admission-action-dropdown" id="admission-test-actions"
                    data-admission-actions-menu role="menu" hidden>
                    <a class="mcc-admission-action-link" role="menuitem"
                        href="<?= site_url('admission-tests/' . (int) $test['id'] . '#personal-details') ?>">
                        <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i> Edit Test
                    </a>
                    <a class="mcc-admission-action-link" role="menuitem"
                        href="<?= site_url('admission-tests/' . (int) $test['id'] . '#meeting-follow-ups') ?>">
                        <i class="fa-solid fa-calendar-plus" aria-hidden="true"></i> Add Meeting Follow-up
                    </a>
                    <a class="mcc-admission-action-link" role="menuitem"
                        href="<?= site_url('crm/trials/' . (int) $test['trial_id']) ?>">
                        <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i> View Trial
                    </a>
                    <?php if (! $isSubmitted && trim((string) ($test['test_variant'] ?? '')) !== '' && $canUpdateAssessmentRemarks): ?>
                        <form method="post" action="<?= site_url('admission-tests/' . (int) $test['id'] . '/student-link') ?>">
                            <?= csrf_field() ?>
                            <button class="mcc-admission-action-link" type="submit" role="menuitem">
                                <i class="fa-solid fa-link" aria-hidden="true"></i> Generate Student Test Link
                            </button>
                        </form>
                    <?php endif; ?>
                    <?php if ($canChangeStatus): ?>
                        <button class="mcc-admission-action-link" type="button" role="menuitem" data-admission-status-open
                            data-admission-status-id="<?= (int) $test['id'] ?>"
                            data-admission-status-action="<?= esc(site_url('admission-tests/' . (int) $test['id'] . '/status')) ?>"
                            data-admission-status-version="<?= (int) ($test['version'] ?? 1) ?>"
                            data-admission-status-current="<?= esc($status) ?>">
                            <i class="fa-solid fa-arrow-right-arrow-left" aria-hidden="true"></i> Change Status
                        </button>
                    <?php endif; ?>
                    <?php if ($canCreatePendingAdmission): ?>
                        <a class="mcc-admission-action-link" role="menuitem"
                            href="<?= site_url('admission-tests/' . (int) $test['id'] . '#pending-admission') ?>">
                        <i class="fa-solid fa-file-circle-check" aria-hidden="true"></i> Move to Pending Admission
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <dl class="mcc-detail-grid mcc-admission-test-summary-grid">
            <div class="mcc-admission-test-summary-primary">
                <dt>Test / Applicant</dt>
                <dd>
                    <span class="mcc-table-primary-text mcc-wrap-anywhere"><?= esc((string) ($test['applicant_full_name'] ?: 'Not available')) ?></span>
                    <span class="mcc-table-secondary-text"><?= esc('#' . (int) $test['id']) ?></span>
                    <?php if ((int) ($test['trial_id'] ?? 0) > 0): ?>
                        <span class="mcc-table-secondary-text"><a href="<?= esc(site_url('crm/trials/' . (int) $test['trial_id'])) ?>"><?= esc('Trial #' . (int) $test['trial_id']) ?></a></span>
                    <?php endif; ?>
                    <?php if (trim((string) ($test['branch_name'] ?? '')) !== ''): ?>
                        <span class="mcc-table-secondary-text"><?= esc((string) $test['branch_name']) ?></span>
                    <?php endif; ?>
                </dd>
            </div>
            <div><dt>Test status</dt><dd><span class="mcc-badge mcc-status-badge <?= esc($testStatusPresentation['class']) ?>" data-admission-status-badge><?= esc($testStatusPresentation['label']) ?></span></dd></div>
            <div><dt>Created date</dt><dd><?= esc($dateTime($test['created_at'] ?? null)) ?></dd></div>
            <div><dt>Last updated date</dt><dd><?= esc($dateTime($test['updated_at'] ?? null)) ?></dd></div>
        </dl>
    </section>

    <?php if ($studentTestLink !== ''): ?>
        <section class="mcc-card mcc-detail-card" aria-labelledby="student-test-link-heading">
            <div class="mcc-card-heading">
                <div>
                    <span class="mcc-eyebrow">One-time access</span>
                    <h2 id="student-test-link-heading">Student Test Link</h2>
                </div>
            </div>
            <p class="mcc-muted">Copy this link now. It is shown only once and becomes unavailable after successful submission.</p>
            <div class="input-group">
                <input class="form-control" type="text" value="<?= esc($studentTestLink) ?>" readonly data-student-test-link>
                <div class="input-group-append">
                    <button class="mcc-button mcc-button-secondary" type="button" data-copy-student-test-link>Copy link</button>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <section class="mcc-card mcc-detail-card" aria-labelledby="lineage-heading">
        <div class="mcc-card-heading"><h2 id="lineage-heading">Trial and Lead lineage</h2></div>
        <dl class="mcc-detail-grid">
            <div><dt>Trial</dt><dd><a href="<?= site_url('crm/trials/' . (int) $test['trial_id']) ?>"><?= esc('Trial #' . (int) $test['trial_id']) ?></a><span class="mcc-table-secondary-text"><?= esc(mcc_user_date($test['trial_date'] ?? null, 'Date not scheduled')) ?></span></dd></div>
            <div><dt>Lead</dt><dd><?php if (! empty($test['lead_id'])): ?><a href="<?= site_url('crm/leads/' . (int) $test['lead_id']) ?>"><?= esc('Lead #' . (int) $test['lead_id']) ?></a><?php else: ?>Not linked<?php endif; ?></dd></div>
            <div><dt>Lead name</dt><dd class="mcc-wrap-anywhere"><?= esc((string) ($test['lead_name'] ?: 'Not available')) ?></dd></div>
            <div><dt>Lead contact</dt><dd class="mcc-wrap-anywhere"><?= esc(implode(' · ', array_filter([(string) ($test['lead_email'] ?? ''), (string) ($test['lead_phone'] ?? '')])) ?: 'Not available') ?></dd></div>
            <div><dt>Trial status</dt><dd><?= esc((string) ($test['trial_status'] ?: 'Not available')) ?></dd></div>
            <div><dt>Trial location</dt><dd class="mcc-wrap-anywhere"><?= esc((string) ($test['location'] ?: 'Not available')) ?></dd></div>
        </dl>
    </section>

    <section class="mcc-card mcc-detail-card" id="personal-details" aria-labelledby="personal-details-heading">
        <div class="mcc-card-heading"><div><span class="mcc-eyebrow">Applicant profile</span><h2 id="personal-details-heading">Personal Details</h2></div></div>
        <dl class="mcc-detail-grid mcc-admission-test-personal-grid">
            <?php foreach ([
                'applicant_full_name' => 'Name of the Player', 'father_name' => 'Father Name', 'father_profession' => 'Father Profession',
                'age' => 'Age', 'applicant_date_of_birth' => 'Date of Birth', 'residence' => 'Residence', 'contact_number' => 'Contact No 1',
                'secondary_contact_number' => 'Contact No 2', 'email' => 'Email ID 1', 'secondary_email' => 'Email ID 2',
                'coaching_start' => 'Date/Month (Wish to start the coaching)', 'discovery_source' => 'How did you come to know about us',
                'represented_as' => 'Represented', 'coaching_type' => 'Type of coaching', 'playing_role' => 'Cricket skill / Ability',
                'nearest_branch' => 'Nearest branch', 'reference_details' => 'Reference (address / contact)', 'achievements' => 'Achievements',
            ] as $field => $label): ?>
                <div><dt><?= esc($label) ?></dt><dd class="mcc-wrap-anywhere"><?= esc($field === 'applicant_date_of_birth' ? mcc_user_date($test[$field] ?? null, 'Not answered') : $value($field)) ?></dd></div>
            <?php endforeach; ?>
        </dl>
        <details class="mcc-admission-test-edit-panel" data-admission-test-personal-details-panel<?= $personalDetailsPanelOpen ? ' open' : '' ?>>
            <summary>Edit personal details</summary>
            <form method="post" action="<?= site_url('admission-tests/' . (int) $test['id'] . '/personal-details') ?>" class="row mt-3" id="admission-test-personal-details-form">
                <?= csrf_field() ?>
                <input type="hidden" name="version" value="<?= (int) $test['version'] ?>">
                <?php foreach (['applicant_full_name' => 'Name of the Player', 'father_name' => 'Father Name', 'father_profession' => 'Father Profession', 'age' => 'Age', 'applicant_date_of_birth' => 'Date of Birth', 'residence' => 'Residence', 'contact_number' => 'Contact No 1', 'secondary_contact_number' => 'Contact No 2', 'email' => 'Email ID 1', 'secondary_email' => 'Email ID 2', 'coaching_start' => 'Date/Month (Wish to start the coaching)', 'discovery_source' => 'How did you come to know about us'] as $field => $label): ?>
                    <?php $isDateField = in_array($field, ['applicant_date_of_birth', 'coaching_start'], true); ?>
                    <?php $isAgeField = $field === 'age'; ?>
                    <div class="col-12 col-md-6 mb-3">
                        <label for="admission-test-<?= esc($field) ?>"><?= esc($label) ?></label>
                        <input class="form-control" id="admission-test-<?= esc($field) ?>" name="<?= esc($field) ?>" value="<?= $inputValue($field) ?>" <?= $isAgeField ? 'type="number" min="0" max="120" step="1" inputmode="numeric"' : '' ?> <?= $isDateField ? 'inputmode="numeric" autocomplete="off" placeholder="DD-MM-YYYY" data-mcc-date-picker data-mcc-date-picker-no-toggle data-mcc-date-format="d-m-Y" data-mcc-date-submit-format="Y-m-d"' : '' ?>>
                        <?php if ($isAgeField): ?><small class="mcc-form-help">Enter a whole number from 0 to 120. Leave blank if unknown.</small><?php endif; ?>
                        <?= $personalError($field) ?>
                    </div>
                <?php endforeach; ?>
                <?php foreach (['reference_details' => 'Reference (address / contact)', 'achievements' => 'Achievements'] as $field => $label): ?>
                    <div class="col-12 mb-3"><label for="admission-test-<?= esc($field) ?>"><?= esc($label) ?></label><textarea class="form-control" id="admission-test-<?= esc($field) ?>" name="<?= esc($field) ?>" rows="3"><?= $inputValue($field) ?></textarea><?= $personalError($field) ?></div>
                <?php endforeach; ?>
                <div class="col-12 col-md-6 mb-3"><label for="admission-test-represented-as">Represented</label><select class="form-control" id="admission-test-represented-as" name="represented_as"><option value="">Select</option><?php foreach (['School', 'Academy', 'District', 'State', 'MCA', 'None'] as $option): ?><option value="<?= esc($option) ?>" <?= $selected('represented_as', $option) ?>><?= esc($option) ?></option><?php endforeach; ?></select><?= $personalError('represented_as') ?></div>
                <div class="col-12 col-md-6 mb-3"><label for="admission-test-coaching-type">Which type of coaching would you like to join</label><select class="form-control" id="admission-test-coaching-type" name="coaching_type"><option value="">Select</option><?php foreach (['Group', 'Personal', 'Short term', 'Advance Training', 'Others'] as $option): ?><option value="<?= esc($option) ?>" <?= $selected('coaching_type', $option) ?>><?= esc($option) ?></option><?php endforeach; ?></select><?= $personalError('coaching_type') ?></div>
                <div class="col-12 col-md-6 mb-3"><label for="admission-test-playing-role">Cricket skill / Ability</label><select class="form-control" id="admission-test-playing-role" name="playing_role"><option value="">Select</option><?php foreach (['Batsman', 'Bowler', 'All-rounder'] as $option): ?><option value="<?= esc($option) ?>" <?= $selected('playing_role', $option) ?>><?= esc($option) ?></option><?php endforeach; ?></select><?= $personalError('playing_role') ?></div>
                <div class="col-12 col-md-6 mb-3"><label for="admission-test-nearest-branch">Nearest branch</label><select class="form-control" id="admission-test-nearest-branch" name="nearest_branch"><option value="">Select</option><?php foreach (['Santa Cruz', 'Thane', 'Bhandup'] as $option): ?><option value="<?= esc($option) ?>" <?= $selected('nearest_branch', $option) ?>><?= esc($option) ?></option><?php endforeach; ?></select><?= $personalError('nearest_branch') ?></div>
                <div class="col-12"><button class="mcc-button mcc-button-primary" type="submit">Save Personal Details</button></div>
            </form>
        </details>
    </section>

    <section class="mcc-card mcc-detail-card" aria-labelledby="selection-test-heading">
        <div class="mcc-card-heading"><div><span class="mcc-eyebrow">Selection Test</span><h2 id="selection-test-heading">Locked Paper Set</h2></div><span class="mcc-badge mcc-badge-success"><?= $selectedPaperSet !== '' ? esc($pageLabels[$selectedPaperSet] ?? $selectedPaperSet) . ' · Read-only' : 'Not selected' ?></span></div>
        <?php if ($selectedPaperSet === ''): ?>
            <div class="mcc-alert mcc-alert-info" role="status">No paper set has been selected. <a href="<?= site_url('admission-tests/' . (int) $test['id'] . '/paper-set') ?>">Select a paper set</a> to continue.</div>
        <?php endif; ?>
        <?php foreach ($questions as $pageVariant => $pageQuestions): ?>
            <section class="mcc-card mcc-admission-test-page" aria-labelledby="admission-test-page-<?= esc($pageVariant) ?>-heading">
                <div class="mcc-card-heading"><h3 id="admission-test-page-<?= esc($pageVariant) ?>-heading"><?= esc($pageLabels[$pageVariant] ?? 'Selection Test ' . $pageVariant) ?></h3><span class="mcc-muted">Selection Test <?= esc($pageVariant) ?></span></div>
                <div class="mcc-admission-test-question-list">
                    <?php foreach ($pageQuestions as $index => $question): ?>
                        <?php
                        $questionNumber = $index + 1;
                        $response = $responses[$pageVariant][$questionNumber] ?? [];
                        $options = $question['options'] ?? [];
                        $selectedAnswer = trim((string) ($response['answer'] ?? ''));
                        $hasSelectedAnswer = $selectedAnswer !== '';
                        ?>
                        <article class="mcc-admission-test-question-block">
                            <div class="mcc-admission-test-question-heading">
                                <span class="mcc-admission-test-question-number" aria-hidden="true">
                                    <?= (int) $questionNumber ?>.
                                </span>
                                <h4 class="mcc-admission-test-question-text">
                                    <?= esc($question['prompt']) ?>
                                </h4>
                            </div>

                            <div class="mcc-admission-test-answer-label">Answer options</div>
                            <?php if ($options !== []): ?>
                                <ul class="mcc-admission-test-options" aria-label="Answer options for question <?= (int) $questionNumber ?>">
                                    <?php foreach ($options as $option): ?>
                                        <?php $isSelected = $hasSelectedAnswer && $selectedAnswer === (string) $option; ?>
                                        <li class="mcc-admission-test-option<?= $isSelected ? ' is-selected' : '' ?>">
                                            <?= esc((string) $option) ?>
                                            <?php if ($isSelected): ?>
                                                <span class="visually-hidden"> (selected answer)</span>
                                            <?php endif; ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <p class="mcc-admission-test-answer is-not-answered">Not available</p>
                            <?php endif; ?>

                            <?php if (! $hasSelectedAnswer && $options !== []): ?>
                                <p class="mcc-admission-test-answer is-not-answered">Not answered</p>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>

        <?php if (! $isSubmitted): ?>
            <details class="mcc-admission-test-edit-panel mt-3">
                <summary>Edit selection test</summary>
                <form method="post" action="<?= site_url('admission-tests/' . (int) $test['id'] . '/basic-test') ?>" id="admission-test-basic-test-form" class="mt-3">
                    <?= csrf_field() ?>
                    <input type="hidden" name="version" value="<?= (int) $test['version'] ?>">
                    <p class="mcc-muted">Paper set: <strong><?= esc($pageLabels[$selectedPaperSet] ?? 'Not selected') ?></strong> (locked and read-only)</p>
                    <div class="mcc-admission-test-edit-actions">
                        <button class="mcc-button mcc-button-secondary" type="submit" formaction="<?= site_url('admission-tests/' . (int) $test['id'] . '/start') ?>">Start Test</button>
                        <button class="mcc-button mcc-button-primary" type="submit">Submit Test</button>
                    </div>
                </form>
            </details>
        <?php endif; ?>
    </section>

    <section class="mcc-card mcc-detail-card" aria-labelledby="review-heading">
        <div class="mcc-card-heading">
            <div><span class="mcc-eyebrow">Assessment</span><h2 id="review-heading">Test Result and Evaluation</h2></div>
            <span class="mcc-badge mcc-status-badge <?= esc($testStatusPresentation['class']) ?>" data-admission-status-badge><?= esc($testStatusPresentation['label']) ?></span>
        </div>
        <dl class="mcc-detail-grid">
            <div><dt>Evaluator</dt><dd><?= ! empty($test['evaluator_user_id']) ? esc('User #' . (int) $test['evaluator_user_id']) : 'Not assigned' ?></dd></div>
            <div><dt>Submitted</dt><dd><?= esc($dateTime($test['submitted_at'] ?? null)) ?></dd></div>
        </dl>
        <?php if ($assessmentRemarks !== ''): ?>
            <div class="mcc-form-field mt-4">
                <div class="mcc-admission-test-answer-label">Assessment remarks</div>
                <p class="mcc-wrap-anywhere mb-0"><?= nl2br(esc($assessmentRemarks)) ?></p>
            </div>
        <?php else: ?>
            <p class="mcc-empty-state mt-4">No assessment remarks recorded.</p>
        <?php endif; ?>
        <?php if (! $isSubmitted): ?>
            <p class="mcc-muted mt-3">Assessment remarks are unavailable until the Admission Test is submitted.</p>
        <?php elseif ($assessmentRemarksWindowInvalid): ?>
            <p class="mcc-alert mcc-alert-warning mt-3" role="alert">Assessment remarks are read-only because the submission timestamp is invalid.</p>
        <?php elseif ($assessmentRemarksEditable && $canUpdateAssessmentRemarks): ?>
            <form method="post" action="<?= site_url('admission-tests/' . (int) $test['id'] . '/assessment-remarks') ?>" id="admission-test-assessment-remarks-form" class="mt-4">
                <?= csrf_field() ?>
                <input type="hidden" name="version" value="<?= (int) ($test['version'] ?? 1) ?>">
                <div class="mcc-form-field">
                    <label for="admission-test-assessment-remarks">Assessment remarks</label>
                    <textarea class="form-control" id="admission-test-assessment-remarks" name="assessment_remarks" rows="5" maxlength="5000"><?= esc((string) old('assessment_remarks', $assessmentRemarks)) ?></textarea>
                </div>
                <p class="mcc-muted mt-2">Remarks editable until <?= esc($assessmentRemarksEditableUntil?->format('d-m-Y h:i A') ?? 'Not available') ?></p>
                <button class="mcc-button mcc-button-primary" type="submit">Save Assessment Remarks</button>
            </form>
        <?php elseif ($isSubmitted && $assessmentRemarksEditable): ?>
            <p class="mcc-muted mt-3">Assessment remarks are editable by authorized users until <?= esc($assessmentRemarksEditableUntil?->format('d-m-Y h:i A') ?? 'Not available') ?>.</p>
        <?php else: ?>
            <p class="mcc-alert mcc-alert-warning mt-3" role="status">Assessment remarks are locked after seven days.</p>
        <?php endif; ?>
        <?php if (($test['submission_history'] ?? []) !== []): ?>
            <h3 class="mt-4">Submission history</h3>
            <div class="mcc-table-wrap">
                <table class="table mcc-table">
                    <thead><tr><th scope="col">Version</th><th scope="col">Variant</th><th scope="col">Score</th><th scope="col">Result</th><th scope="col">Evaluator</th><th scope="col">Submitted</th></tr></thead>
                    <tbody>
                        <?php foreach ($test['submission_history'] as $history): ?>
                            <tr><td><?= (int) ($history['version'] ?? 0) ?></td><td><?= esc((string) ($history['variant'] ?? 'Not available')) ?></td><td><?= esc((string) (($history['score'] ?? '') !== '' && $history['score'] !== null ? $history['score'] : 'Not available')) ?></td><td><?= esc((string) (($history['result'] ?? '') ?: 'Not available')) ?></td><td><?= ! empty($history['evaluator_user_id']) ? esc('User #' . (int) $history['evaluator_user_id']) : 'Not assigned' ?></td><td><?= esc($dateTime($history['submitted_at'] ?? null)) ?></td></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <?php if (in_array($status, ['TEST_SUBMITTED', 'MEETING_FOLLOW_UP', 'APPROVED_FOR_ADMISSION'], true)): ?>
        <section class="mcc-card mcc-detail-card" id="admission-test-fees" aria-labelledby="admission-test-fees-heading">
            <?= view('App\\Modules\\AdmissionTests\\Views\\_fees', ['test' => $test]) ?>
        </section>
    <?php endif; ?>

    <section class="mcc-card mcc-detail-card" id="meeting-follow-ups" aria-labelledby="follow-ups-heading">
        <div class="mcc-card-heading"><div><span class="mcc-eyebrow">History</span><h2 id="follow-ups-heading">Meeting Follow-up history</h2></div><span class="mcc-muted"><?= count($test['follow_ups'] ?? []) ?> recorded</span></div>
        <?php if (($test['follow_ups'] ?? []) === []): ?>
            <p class="mcc-empty-state">No Meeting Follow-up records linked to this Admission Test.</p>
        <?php else: ?>
            <div class="mcc-table-wrap">
                <table class="table mcc-table">
                    <thead><tr><th scope="col">Type</th><th scope="col">Status</th><th scope="col">Follow-up date/time</th><th scope="col">Notes</th><th scope="col">Outcome</th><th scope="col">Next action</th></tr></thead>
                    <tbody>
                        <?php foreach ($test['follow_ups'] as $followUp): ?>
                            <?php $followUpStatus = mcc_follow_up_status_presentation((string) ($followUp['status'] ?? '')); ?>
                            <tr><td><?= esc((string) ($followUp['follow_up_type'] ?? 'Not available')) ?></td><td><span class="mcc-badge mcc-status-badge <?= esc($followUpStatus['class']) ?>"><?= esc($followUpStatus['label']) ?></span></td><td><?= esc($dateTime($followUp['follow_up_at'] ?? null)) ?></td><td class="mcc-wrap-anywhere"><?= nl2br(esc((string) ($followUp['notes'] ?? 'Not available'))) ?></td><td class="mcc-wrap-anywhere"><?= nl2br(esc((string) ($followUp['outcome'] ?? 'Not available') ?: 'Not available')) ?></td><td class="mcc-wrap-anywhere"><?= nl2br(esc((string) ($followUp['next_action'] ?? 'Not available') ?: 'Not available')) ?></td></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        <details class="mcc-admission-test-edit-panel mt-3">
            <summary>Add Meeting Follow-up</summary>
            <form method="post" action="<?= site_url('admission-tests/' . (int) $test['id'] . '/follow-ups') ?>" id="admission-test-follow-up-form" class="row mt-3">
                <?= csrf_field() ?>
                <div class="col-12 col-md-4 mb-3">
                    <label for="follow-up-date">Follow-up date</label>
                    <input class="form-control" id="follow-up-date" name="follow_up_date" type="text" inputmode="numeric" autocomplete="off" placeholder="DD-MM-YYYY" data-mcc-date-picker data-mcc-date-picker-no-toggle data-mcc-date-format="d-m-Y" data-mcc-date-submit-format="Y-m-d" value="<?= esc($followUpDateInput) ?>" required>
                    <?php if (isset($fieldErrors['follow_up_date'])): ?><p class="mcc-field-error" role="alert"><?= esc($fieldErrors['follow_up_date']) ?></p><?php endif; ?>
                </div>
                <div class="col-12 col-md-4 mb-3">
                    <label for="follow-up-time">Follow-up time</label>
                    <input class="form-control" id="follow-up-time" name="follow_up_time" type="time" autocomplete="off" value="<?= esc($followUpTimeInput) ?>" required>
                    <?php if (isset($fieldErrors['follow_up_time'])): ?><p class="mcc-field-error" role="alert"><?= esc($fieldErrors['follow_up_time']) ?></p><?php endif; ?>
                </div>
                <div class="col-12 col-md-4 mb-3"><label for="follow-up-type">Type</label><select class="form-control" id="follow-up-type" name="follow_up_type"><?php foreach ($followUpOptions['types'] as $option): ?><option value="<?= esc($option) ?>"><?= esc($option) ?></option><?php endforeach; ?></select><?php if (isset($fieldErrors['follow_up_type'])): ?><p class="mcc-field-error" role="alert"><?= esc($fieldErrors['follow_up_type']) ?></p><?php endif; ?></div>
                <div class="col-12 col-md-4 mb-3"><label for="follow-up-status">Status</label><select class="form-control" id="follow-up-status" name="status"><?php foreach ($followUpOptions['statuses'] as $option): ?><option value="<?= esc($option) ?>"><?= esc($option) ?></option><?php endforeach; ?></select><?php if (isset($fieldErrors['status'])): ?><p class="mcc-field-error" role="alert"><?= esc($fieldErrors['status']) ?></p><?php endif; ?></div>
                <div class="col-12 mb-3"><label for="follow-up-notes">Notes</label><textarea class="form-control" id="follow-up-notes" name="notes" rows="3" required><?= esc((string) old('notes', '')) ?></textarea><?php if (isset($fieldErrors['notes'])): ?><p class="mcc-field-error" role="alert"><?= esc($fieldErrors['notes']) ?></p><?php endif; ?></div>
                <div class="col-12 mb-3"><label for="follow-up-outcome">Outcome</label><textarea class="form-control" id="follow-up-outcome" name="outcome" rows="2"><?= esc((string) old('outcome', '')) ?></textarea></div>
                <div class="col-12 mb-3"><label for="follow-up-next-action">Next action</label><textarea class="form-control" id="follow-up-next-action" name="next_action" rows="2"><?= esc((string) old('next_action', '')) ?></textarea></div>
                <div class="col-12"><button class="mcc-button mcc-button-secondary" type="submit">Add Follow-up</button></div>
            </form>
        </details>
    </section>

    <section class="mcc-card mcc-detail-card" id="pending-admission" aria-labelledby="pending-admission-heading">
        <div class="mcc-card-heading"><div><span class="mcc-eyebrow">Conversion</span><h2 id="pending-admission-heading">Pending Admission</h2></div></div>
        <?php if ($pendingAdmissionId > 0): ?>
            <div class="mcc-alert mcc-alert-success" role="status">Pending Admission already created. Linked Admission: <a href="<?= site_url('admissions/' . $pendingAdmissionId . '/page') ?>"><?= esc('Admission #' . $pendingAdmissionId) ?></a></div>
        <?php elseif ($canCreatePendingAdmission): ?>
            <p class="mcc-muted">This Admission Test is eligible for Pending Admission conversion.</p>
            <form method="post" action="<?= site_url('admission-tests/' . (int) $test['id'] . '/pending-admission') ?>" id="admission-test-pending-admission-form">
                <?= csrf_field() ?>
                <input type="hidden" name="version" value="<?= (int) ($test['version'] ?? 1) ?>">
                <label class="mcc-checkbox-label"><input type="checkbox" name="agreement_confirmed" value="1" required> Parent/student agreement confirmed</label>
                <button class="mcc-button mcc-button-primary mt-3" type="submit">Move to Pending Admission</button>
            </form>
        <?php elseif ($status !== 'APPROVED_FOR_ADMISSION'): ?>
            <p class="mcc-muted">Approve the test before moving it to Pending Admission.</p>
        <?php elseif (! service('authorization')->can('admission.create', (string) ($test['branch_id'] ?? ''))): ?>
            <p class="mcc-muted">You do not have permission to create Pending Admission.</p>
        <?php else: ?>
            <p class="mcc-muted">Pending Admission is unavailable for this Admission Test.</p>
        <?php endif; ?>
    </section>

</div>
<div class="mcc-alert mcc-alert-success mcc-admission-status-page-message" data-admission-status-page-message role="status" hidden></div>
<div class="mcc-modal mcc-admission-status-modal" data-admission-status-modal hidden aria-hidden="true">
    <div class="mcc-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="admission-status-modal-heading">
        <div class="mcc-modal-heading">
            <div>
                <span class="mcc-eyebrow">Admission Test</span>
                <h2 id="admission-status-modal-heading">Change Status</h2>
            </div>
            <button class="mcc-modal-close" type="button" data-admission-status-close aria-label="Close Change Status modal">&times;</button>
        </div>
        <p class="mcc-modal-instruction">Update the workflow status for Test <strong data-admission-status-reference>#<?= (int) $test['id'] ?></strong>. Test answers and personal details are not changed.</p>
        <div class="mcc-alert mcc-alert-danger" data-admission-status-form-error role="alert" hidden></div>
        <form method="post" action="<?= esc(site_url('admission-tests/' . (int) $test['id'] . '/status')) ?>" class="mcc-admission-status-modal-form" data-admission-status-form>
            <?= csrf_field() ?>
            <input type="hidden" name="version" value="<?= (int) ($test['version'] ?? 1) ?>" data-admission-status-version-input>
            <div class="mcc-form-field">
                <label for="admission-status-modal-status">Status</label>
                <select class="form-control" id="admission-status-modal-status" name="status" data-admission-status-select required>
                    <?php foreach (service('admissionTest')->managedStatuses() as $managedStatus): ?>
                        <option value="<?= esc($managedStatus) ?>" <?= $managedStatus === $status ? 'selected' : '' ?>><?= esc(mcc_admission_test_status_presentation($managedStatus)['label']) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="invalid-feedback" data-admission-status-error="status" role="alert" hidden></div>
            </div>
            <div class="mcc-form-field">
                <label for="admission-status-modal-reason">Reason <span class="mcc-muted">(required for Rejected and On hold)</span></label>
                <textarea class="form-control" id="admission-status-modal-reason" name="reason" rows="4" maxlength="255" data-admission-status-reason placeholder="Add a correction or review note"></textarea>
                <div class="invalid-feedback" data-admission-status-error="reason" role="alert" hidden></div>
            </div>
            <div class="mcc-modal-actions">
                <button class="mcc-button mcc-button-secondary" type="button" data-admission-status-cancel>Cancel</button>
                <button class="mcc-button mcc-button-primary" type="submit" data-admission-status-submit>Save status</button>
            </div>
        </form>
    </div>
</div>
<?= view('staff_footer') ?>
