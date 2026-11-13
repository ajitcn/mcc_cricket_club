<?php

$followUp = is_array($followUp ?? null) ? $followUp : [];
$admissionTests = is_array($admissionTests ?? null) ? $admissionTests : [];
$assignedUsers = is_array($assignedUsers ?? null) ? $assignedUsers : [];
$options = is_array($options ?? null) ? $options : ['types' => [], 'statuses' => []];
$isEdit = ($formMode ?? 'new') === 'edit';
$fieldErrors = session()->getFlashdata('field_errors');
$fieldErrors = is_array($fieldErrors) ? $fieldErrors : [];

$rawValue = static function (string $field) use ($followUp): string {
    return (string) old($field, $followUp[$field] ?? '');
};
$dateValue = static function (string $field) use ($rawValue): string {
    $value = trim(str_replace('T', ' ', $rawValue($field)));
    $timestamp = $value === '' ? false : strtotime($value);

    return $timestamp === false ? $value : date('d-m-Y', $timestamp);
};
$timeValue = static function (string $field) use ($rawValue): string {
    $value = trim(str_replace('T', ' ', $rawValue($field)));
    $timestamp = $value === '' ? false : strtotime($value);

    return $timestamp === false ? '' : date('H:i', $timestamp);
};
$value = static function (string $field) use ($rawValue): string {
    return esc($rawValue($field));
};
$errorFor = static function (string $field) use ($fieldErrors): string {
    return (string) ($fieldErrors[$field] ?? '');
};
$selectedTestId = (string) $rawValue('admission_test_id');
$selectedTest = null;

foreach ($admissionTests as $candidate) {
    if ((string) ($candidate['id'] ?? '') === $selectedTestId) {
        $selectedTest = $candidate;
        break;
    }
}

$summary = $isEdit ? $followUp : $selectedTest;
$summaryTestId = (int) ($summary['id'] ?? $summary['admission_test_id'] ?? 0);
$summaryTrialId = (int) ($summary['trial_id'] ?? 0);
$summaryTestReference = 'Test #' . $summaryTestId;
$summaryTrialReference = 'Trial #' . $summaryTrialId;
$summaryStatus = mcc_follow_up_status_presentation((string) ($summary['status'] ?? ''));
$formAction = $isEdit
    ? site_url('meeting-followups/' . (int) ($followUp['id'] ?? 0) . '/edit')
    : site_url('meeting-followups');
$pageActions = '';
?>
<?= view('staff_header', [
    'title' => $isEdit ? 'Edit Meeting Follow-up' : 'New Meeting Follow-up',
    'nav' => $nav,
    'message' => $message ?? null,
    'error' => $error ?? null,
    'pageActions' => $pageActions,
    'breadcrumbTrail' => $isEdit
        ? [
            ['label' => 'CRM', 'url' => site_url('crm')],
            ['label' => 'Admission Test', 'url' => site_url('admission-tests')],
            ['label' => 'Meeting Follow-up', 'url' => site_url('meeting-followups')],
            ['label' => 'Edit'],
        ]
        : [
            ['label' => 'CRM', 'url' => site_url('crm')],
            ['label' => 'Admission Test', 'url' => site_url('admission-tests')],
            ['label' => 'Meeting Follow-up', 'url' => site_url('meeting-followups')],
            ['label' => 'New'],
        ],
]) ?>

<section class="mcc-card mcc-detail-card mcc-meeting-follow-up-form-header" aria-labelledby="meeting-follow-up-form-heading">
    <div class="mcc-card-heading">
        <div>
            <span class="mcc-eyebrow">Meeting Follow-up</span>
            <h2 id="meeting-follow-up-form-heading"><?= $isEdit ? 'Edit Meeting Follow-up' : 'Create Meeting Follow-up' ?></h2>
            <p class="mcc-muted mb-0">Record a meeting against an authorized Admission Test.</p>
        </div>
        <?php if (! $isEdit): ?>
            <a class="mcc-button mcc-button-secondary" href="<?= site_url('meeting-followups') ?>">
                <i class="fa-solid fa-list" aria-hidden="true"></i> Cancel
            </a>
        <?php endif; ?>
    </div>
</section>

<form class="mcc-card mcc-detail-card mcc-meeting-follow-up-form-card" method="post" action="<?= esc($formAction) ?>" id="meeting-follow-up-form">
    <?= csrf_field() ?>
    <?php if ($isEdit): ?>
        <input type="hidden" name="version" value="<?= (int) ($followUp['version'] ?? 0) ?>">
        <input type="hidden" name="admission_test_id" value="<?= (int) ($followUp['admission_test_id'] ?? 0) ?>">
    <?php endif; ?>

    <section class="mcc-meeting-follow-up-form-section" aria-labelledby="meeting-follow-up-link-heading">
        <div class="mcc-card-heading">
            <div>
                <span class="mcc-eyebrow">Read-only relationship</span>
                <h2 id="meeting-follow-up-link-heading">Admission Test summary</h2>
            </div>
        </div>
        <?php if ($isEdit): ?>
            <div class="mcc-detail-grid mcc-meeting-follow-up-summary-grid" aria-label="Linked Admission Test summary">
                <div class="mcc-summary-item"><strong>Test reference</strong><a href="<?= site_url('admission-tests/' . $summaryTestId) ?>"><?= esc($summaryTestReference) ?></a></div>
                <div class="mcc-summary-item"><strong>Applicant name</strong><span><?= esc((string) ($summary['applicant_full_name'] ?? 'Applicant unavailable')) ?></span></div>
                <div class="mcc-summary-item"><strong>Trial reference</strong><a href="<?= site_url('crm/trials/' . $summaryTrialId) ?>"><?= esc($summaryTrialReference) ?></a></div>
                <div class="mcc-summary-item"><strong>Branch</strong><span><?= esc((string) ($summary['branch_name'] ?? 'Branch unavailable')) ?></span></div>
                <div class="mcc-summary-item"><strong>Test status</strong><span class="mcc-badge mcc-status-badge <?= esc($summaryStatus['class']) ?>"><?= esc($summaryStatus['label']) ?></span></div>
            </div>
        <?php else: ?>
            <div class="mcc-form-field mcc-meeting-follow-up-admission-test-field">
                <label for="meeting-admission-test">Admission Test</label>
                <select class="form-control btSelect" id="meeting-admission-test" name="admission_test_id" data-bt-select data-bt-select-placeholder="Choose admission test" data-bt-select-search-placeholder="Search admission tests" required>
                    <option value="">Choose admission test</option>
                    <?php foreach ($admissionTests as $test): ?>
                        <option value="<?= (int) $test['id'] ?>" <?= $selectedTestId === (string) $test['id'] ? 'selected' : '' ?>>Test #<?= (int) $test['id'] ?> · <?= esc($test['applicant_full_name']) ?> · Trial #<?= (int) $test['trial_id'] ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if ($errorFor('admission_test_id') !== ''): ?><p class="mcc-field-error" role="alert"><?= esc($errorFor('admission_test_id')) ?></p><?php endif; ?>
            </div>
            <?php if ($selectedTest !== null): ?>
                <div class="mcc-detail-grid mcc-meeting-follow-up-summary-grid mt-3" aria-label="Selected Admission Test summary">
                    <div class="mcc-summary-item"><strong>Test reference</strong><span><?= esc($summaryTestReference) ?></span></div>
                    <div class="mcc-summary-item"><strong>Applicant name</strong><span><?= esc($selectedTest['applicant_full_name']) ?></span></div>
                    <div class="mcc-summary-item"><strong>Trial reference</strong><span><?= esc($summaryTrialReference) ?></span></div>
                    <div class="mcc-summary-item"><strong>Branch</strong><span><?= esc($selectedTest['branch_name']) ?></span></div>
                    <div class="mcc-summary-item"><strong>Test status</strong><span class="mcc-badge mcc-status-badge <?= esc($summaryStatus['class']) ?>"><?= esc($summaryStatus['label']) ?></span></div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </section>

    <section class="mcc-meeting-follow-up-form-section" aria-labelledby="meeting-follow-up-details-heading">
        <div class="mcc-card-heading">
            <div>
                <span class="mcc-eyebrow">Editable fields</span>
                <h2 id="meeting-follow-up-details-heading">Follow-up details</h2>
            </div>
        </div>
        <div class="row">
            <div class="mcc-form-field col-12 col-md-6 col-xl-3 mb-3">
                <label for="meeting-type">Follow-up Type</label>
                <select class="form-control" id="meeting-type" name="follow_up_type" required>
                    <?php foreach ($options['types'] as $type): ?>
                        <option value="<?= esc($type) ?>" <?= old('follow_up_type', $followUp['follow_up_type'] ?? 'meeting') === $type ? 'selected' : '' ?>><?= esc(ucwords($type)) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if ($errorFor('follow_up_type') !== ''): ?><p class="mcc-field-error" role="alert"><?= esc($errorFor('follow_up_type')) ?></p><?php endif; ?>
            </div>
            <div class="mcc-form-field col-12 col-md-6 col-xl-3 mb-3">
                <label for="meeting-status-form">Status</label>
                <select class="form-control" id="meeting-status-form" name="status" required>
                    <?php foreach ($options['statuses'] as $status): ?>
                        <option value="<?= esc($status) ?>" <?= old('status', $followUp['status'] ?? 'scheduled') === $status ? 'selected' : '' ?>><?= esc(ucwords($status)) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if ($errorFor('status') !== ''): ?><p class="mcc-field-error" role="alert"><?= esc($errorFor('status')) ?></p><?php endif; ?>
            </div>
            <div class="mcc-form-field col-12 col-md-6 col-xl-3 mb-3">
                <label for="meeting-start">Follow-up Start Date</label>
                <input class="form-control" id="meeting-start" name="start_at" type="text" inputmode="numeric" placeholder="DD-MM-YYYY" autocomplete="off" data-mcc-date-picker data-mcc-date-picker-no-toggle data-mcc-date-format="d-m-Y" data-mcc-datetime-submit-format="Y-m-d" data-mcc-date-picker-time-input="meeting-start-time" value="<?= esc($dateValue('start_at') ?: $dateValue('follow_up_at')) ?>" required>
                <?php if ($errorFor('start_at') !== ''): ?><p class="mcc-field-error" role="alert"><?= esc($errorFor('start_at')) ?></p><?php endif; ?>
            </div>
            <div class="mcc-form-field col-12 col-md-6 col-xl-3 mb-3">
                <label for="meeting-start-time">Start Time</label>
                <input
                    class="form-control"
                    id="meeting-start-time"
                    name="start_time"
                    type="time"
                    value="<?= esc((string) old('start_time', $timeValue('start_at') ?: $timeValue('follow_up_at'))) ?>"
                    <?= $errorFor('start_time') !== '' ? 'aria-invalid="true"' : '' ?>
                    required
                >
                <?php if ($errorFor('start_time') !== ''): ?>
                    <p class="mcc-field-error" role="alert"><?= esc($errorFor('start_time')) ?></p>
                <?php endif; ?>
            </div>
            <div class="mcc-form-field col-12 col-md-6 mb-3">
                <label for="meeting-assigned-form">Assigned User</label>
                <select class="form-control" id="meeting-assigned-form" name="assigned_user_id">
                    <option value="">Unassigned</option>
                    <?php foreach ($assignedUsers as $user): ?>
                        <option value="<?= (int) $user['id'] ?>" <?= (string) old('assigned_user_id', $followUp['assigned_user_id'] ?? '') === (string) $user['id'] ? 'selected' : '' ?>><?= esc($user['display_name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if ($errorFor('assigned_user_id') !== ''): ?><p class="mcc-field-error" role="alert"><?= esc($errorFor('assigned_user_id')) ?></p><?php endif; ?>
            </div>
            <div class="mcc-form-field col-12 col-md-6 mb-3">
                <label for="meeting-notes">Notes</label>
                <textarea class="form-control mcc-meeting-follow-up-textarea" id="meeting-notes" name="notes" rows="5"><?= $value('notes') ?></textarea>
            </div>
            <div class="mcc-form-field col-12 mb-3">
                <label for="meeting-next-action">Next Action</label>
                <textarea class="form-control mcc-meeting-follow-up-textarea" id="meeting-next-action" name="next_action" rows="4"><?= $value('next_action') ?></textarea>
            </div>
        </div>
        <?php if ($isEdit): ?>
            <input type="hidden" name="outcome" value="<?= $value('outcome') ?>">
        <?php else: ?>
            <div class="mcc-form-field mb-3">
                <label for="meeting-outcome">Outcome</label>
                <textarea class="form-control mcc-meeting-follow-up-textarea" id="meeting-outcome" name="outcome" rows="3"><?= $value('outcome') ?></textarea>
            </div>
        <?php endif; ?>
    </section>

    <div class="mcc-form-actions mcc-meeting-follow-up-form-actions">
        <a class="mcc-button mcc-button-secondary" href="<?= $isEdit ? site_url('meeting-followups/' . (int) ($followUp['id'] ?? 0)) : site_url('meeting-followups') ?>">
            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Cancel
        </a>
        <button class="mcc-button mcc-button-primary" type="submit"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Save Follow-up</button>
    </div>
</form>
<?= view('staff_footer') ?>
