<?php

$isEdit = ($formMode ?? 'new') === 'edit';
$canScheduleNext = $isEdit && (bool) ($canScheduleNext ?? true);
$followUp = is_array($followUp ?? null) ? $followUp : [];
$admissions = is_array($admissions ?? null) ? $admissions : [];
$assignedUsers = is_array($assignedUsers ?? null) ? $assignedUsers : [];
$options = is_array($options ?? null) ? $options : ['types' => [], 'statuses' => []];
$fieldErrors = session()->getFlashdata('field_errors');
$fieldErrors = is_array($fieldErrors) ? $fieldErrors : [];

$fieldError = static function (string $field) use ($fieldErrors): string {
    return trim((string) ($fieldErrors[$field] ?? ''));
};

$old = static function (string $field, $fallback = '') use ($followUp): string {
    $value = old($field);

    if ($value !== null) {
        return (string) $value;
    }

    return (string) ($followUp[$field] ?? $fallback);
};

$dateValue = static function (string $field) use ($old): string {
    $value = $old($field);

    if ($value === '') {
        return '';
    }

    $timestamp = strtotime($value);

    return $timestamp === false ? $value : date('d-m-Y', $timestamp);
};

$timeValue = static function (string $field) use ($old): string {
    $value = $old($field);

    if ($value === '') {
        return '';
    }

    $timestamp = strtotime($value);

    return $timestamp === false ? '' : date('H:i', $timestamp);
};

$scheduleNextChecked = $canScheduleNext && in_array(
    strtolower(trim($old('schedule_next_follow_up'))),
    ['1', 'on', 'true'],
    true,
);

$admissionId = $old('admission_id');
$selectedAdmission = null;

foreach ($admissions as $admission) {
    if ((string) ($admission['id'] ?? '') === $admissionId) {
        $selectedAdmission = $admission;
        break;
    }
}

$selectedAdmissionNumber = trim((string) ($selectedAdmission['admission_number'] ?? ''));
$selectedApplicantName = trim((string) ($selectedAdmission['applicant_full_name'] ?? ''));
$selectedBranchName = trim((string) ($selectedAdmission['branch_name'] ?? ''));
$pageTitle = $isEdit ? 'Edit Admission Follow-up' : 'Add Admission Follow-up';
$pageActions = '<a class="mcc-button mcc-button-secondary" href="'
    . site_url('admissions/followups')
    . '">Back to Follow-ups</a>';
?>
<?= view('staff_header', [
    'title' => $pageTitle,
    'nav' => $nav ?? [],
    'message' => $message ?? null,
    'error' => $error ?? null,
    'pageActions' => $pageActions,
]) ?>

<form
    method="post"
    action="<?= site_url($isEdit ? 'admissions/followups/' . (int) ($followUp['id'] ?? 0) . '/edit' : 'admissions/followups') ?>"
    class="mcc-follow-up-form"
>
    <?= csrf_field() ?>

    <?php if ($isEdit): ?>
        <input type="hidden" name="admission_id" value="<?= esc($admissionId) ?>">
        <input type="hidden" name="updated_at" value="<?= esc((string) ($followUp['updated_at'] ?? '')) ?>">
    <?php endif; ?>

    <section class="mcc-card mcc-detail-card" aria-labelledby="follow-up-admission-information-heading">
        <div class="mcc-card-heading">
            <div>
                <span class="mcc-eyebrow">Admission</span>
                <h2 id="follow-up-admission-information-heading">Admission Information</h2>
            </div>
        </div>

        <?php if (! $isEdit): ?>
            <div class="form-group mb-0">
                <?= view('components/admission-select', [
                    'id' => 'admission-follow-up-admission',
                    'name' => 'admission_id',
                    'label' => 'Admission',
                    'placeholder' => 'Choose admission',
                    'selected' => $admissionId,
                    'forceBtSelect' => true,
                    'options' => array_map(static fn (array $admission): array => [
                        'value' => (string) $admission['id'],
                        'label' => (string) (($admission['admission_number'] ?: ('Admission #' . $admission['id']))
                            . ' — '
                            . ($admission['applicant_full_name'] ?: 'Unnamed')
                            . ' — '
                            . $admission['branch_name']),
                        'attributes' => [
                            'data-search-text' => (string) ($admission['admission_number'] . ' '
                                . ' ' . $admission['applicant_full_name'] . ' '
                                . ' ' . $admission['branch_name']),
                        ],
                    ], $admissions),
                ]) ?>
                <?php if ($fieldError('admission_id') !== ''): ?>
                    <p class="mcc-field-error" role="alert">
                        <?= esc($fieldError('admission_id')) ?>
                    </p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($selectedAdmission !== null): ?>
            <p class="mcc-form-context-value mt-3 mb-0">
                <?= esc($selectedApplicantName !== '' ? $selectedApplicantName : 'Applicant unavailable') ?>
                &middot;
                <?= esc($selectedAdmissionNumber !== '' ? '#' . $selectedAdmissionNumber : '#Pending number') ?>
                &middot;
                <?= esc($selectedBranchName !== '' ? $selectedBranchName : 'Branch unavailable') ?>
            </p>
        <?php endif; ?>
    </section>

    <section class="mcc-card mcc-detail-card" aria-labelledby="follow-up-details-heading">
        <div class="mcc-card-heading">
            <div>
                <span class="mcc-eyebrow">Conversation</span>
                <h2 id="follow-up-details-heading">Follow-up Details</h2>
            </div>
        </div>

        <div class="row">
            <div class="col-12 col-md-6 mb-3">
                <label for="admission-follow-up-date">Follow-up date</label>
                <input
                    class="form-control"
                    id="admission-follow-up-date"
                    name="follow_up_at"
                    type="text"
                    inputmode="numeric"
                    placeholder="DD-MM-YYYY"
                    autocomplete="off"
                    required
                    data-mcc-date-picker
                    data-mcc-date-picker-no-toggle
                    data-mcc-date-format="d-m-Y"
                    data-mcc-datetime-submit-format="Y-m-d"
                    data-mcc-date-picker-time-input="admission-follow-up-time"
                    value="<?= esc($dateValue('follow_up_at')) ?>"
                >
                <?php if ($fieldError('follow_up_at') !== ''): ?>
                    <p class="mcc-field-error" role="alert">
                        <?= esc($fieldError('follow_up_at')) ?>
                    </p>
                <?php endif; ?>
            </div>
            <div class="col-12 col-md-6 mb-3">
                <label for="admission-follow-up-time">Follow-up time</label>
                <input
                    class="form-control"
                    id="admission-follow-up-time"
                    name="follow_up_time"
                    type="time"
                    required
                    value="<?= esc($timeValue('follow_up_at')) ?>"
                >
                <?php if ($fieldError('follow_up_time') !== ''): ?>
                    <p class="mcc-field-error" role="alert">
                        <?= esc($fieldError('follow_up_time')) ?>
                    </p>
                <?php endif; ?>
            </div>
            <div class="col-12 col-md-4 mb-3">
                <label for="admission-follow-up-type">Follow-up type</label>
                <select class="form-control" id="admission-follow-up-type" name="follow_up_type" required>
                    <option value="" disabled <?= $old('follow_up_type') === '' ? 'selected' : '' ?>>Choose type</option>
                    <?php foreach ($options['types'] as $type): ?>
                        <option value="<?= esc($type) ?>" <?= $old('follow_up_type') === $type ? 'selected' : '' ?>>
                            <?= esc(ucwords(str_replace('_', ' ', $type))) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($fieldError('follow_up_type') !== ''): ?>
                    <p class="mcc-field-error" role="alert">
                        <?= esc($fieldError('follow_up_type')) ?>
                    </p>
                <?php endif; ?>
            </div>
            <div class="col-12 col-md-4 mb-3">
                <label for="admission-follow-up-status">Status</label>
                <?php if ($isEdit): ?>
                    <select class="form-control" id="admission-follow-up-status" name="status" required>
                        <option value="" disabled <?= $old('status', 'pending') === '' ? 'selected' : '' ?>>Choose status</option>
                        <?php foreach ($options['statuses'] as $status): ?>
                            <option value="<?= esc($status) ?>" <?= $old('status', 'pending') === $status ? 'selected' : '' ?>>
                                <?= esc(ucwords(str_replace('_', ' ', $status))) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($fieldError('status') !== ''): ?>
                        <p class="mcc-field-error" role="alert">
                            <?= esc($fieldError('status')) ?>
                        </p>
                    <?php endif; ?>
                <?php else: ?>
                    <input
                        class="form-control"
                        id="admission-follow-up-status"
                        name="status"
                        type="text"
                        value="Scheduled"
                        readonly
                        aria-readonly="true"
                    >
                <?php endif; ?>
            </div>
            <div class="col-12 col-md-4 mb-3">
                <label for="admission-follow-up-assigned">Assigned user</label>
                <select class="form-control" id="admission-follow-up-assigned" name="assigned_user_id">
                    <option value="">Choose assigned user</option>
                    <?php foreach ($assignedUsers as $user): ?>
                        <option value="<?= (int) $user['id'] ?>" <?= $old('assigned_user_id') === (string) $user['id'] ? 'selected' : '' ?>>
                            <?= esc((string) $user['display_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($fieldError('assigned_user_id') !== ''): ?>
                    <p class="mcc-field-error" role="alert">
                        <?= esc($fieldError('assigned_user_id')) ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="mcc-card mcc-detail-card" aria-labelledby="follow-up-notes-heading">
        <div class="mcc-card-heading">
            <div>
                <span class="mcc-eyebrow">Record</span>
                <h2 id="follow-up-notes-heading">Notes and Outcome</h2>
            </div>
        </div>

        <div class="row">
            <div class="col-12 mb-3">
                <label for="admission-follow-up-notes">Notes</label>
                <textarea class="form-control" id="admission-follow-up-notes" name="notes" rows="5" maxlength="65535" required><?= esc($old('notes')) ?></textarea>
                <?php if ($fieldError('notes') !== ''): ?>
                    <p class="mcc-field-error" role="alert">
                        <?= esc($fieldError('notes')) ?>
                    </p>
                <?php endif; ?>
            </div>
            <div class="col-12 mb-3">
                <label for="admission-follow-up-outcome">Outcome</label>
                <textarea class="form-control" id="admission-follow-up-outcome" name="outcome" rows="3" maxlength="255"><?= esc($old('outcome')) ?></textarea>
                <?php if ($fieldError('outcome') !== ''): ?>
                    <p class="mcc-field-error" role="alert">
                        <?= esc($fieldError('outcome')) ?>
                    </p>
                <?php endif; ?>
            </div>
            <?php if ($canScheduleNext): ?>
                <div class="col-12 mb-3">
                    <label class="mcc-checkbox-label" for="schedule-next-follow-up">
                        <input
                            id="schedule-next-follow-up"
                            name="schedule_next_follow_up"
                            type="checkbox"
                            value="1"
                            data-follow-up-schedule-toggle
                            <?= $scheduleNextChecked ? 'checked' : '' ?>
                        >
                        Schedule Next Follow-up
                    </label>
                    <?php if ($fieldError('schedule_next_follow_up') !== ''): ?>
                        <p class="mcc-field-error" role="alert">
                            <?= esc($fieldError('schedule_next_follow_up')) ?>
                        </p>
                    <?php endif; ?>
                </div>
                <?php if ($canScheduleNext): ?>
                <div
                    class="col-12 mcc-follow-up-next-fields"
                    data-follow-up-next-fields
                    <?= $scheduleNextChecked ? '' : 'hidden' ?>
                >
                    <div class="row">
                        <div class="col-12 col-md-6 mb-3">
                            <label for="admission-next-follow-up-date">Next follow-up date</label>
                            <input
                                class="form-control"
                                id="admission-next-follow-up-date"
                                name="next_follow_up_at"
                                type="text"
                                inputmode="numeric"
                                placeholder="DD-MM-YYYY"
                                autocomplete="off"
                                data-mcc-date-picker
                                data-mcc-date-picker-no-toggle
                                data-mcc-date-format="d-m-Y"
                                data-mcc-datetime-submit-format="Y-m-d"
                                data-mcc-date-picker-time-input="admission-next-follow-up-time"
                                value="<?= esc($dateValue('next_follow_up_at')) ?>"
                                <?= $scheduleNextChecked ? 'required' : 'disabled' ?>
                            >
                            <?php if ($fieldError('next_follow_up_at') !== ''): ?>
                                <p class="mcc-field-error" role="alert">
                                    <?= esc($fieldError('next_follow_up_at')) ?>
                                </p>
                            <?php endif; ?>
                        </div>
                        <div class="col-12 col-md-6 mb-3">
                            <label for="admission-next-follow-up-time">Next follow-up time</label>
                            <input
                                class="form-control"
                                id="admission-next-follow-up-time"
                                name="next_follow_up_time"
                                type="time"
                                value="<?= esc($timeValue('next_follow_up_at')) ?>"
                                <?= $scheduleNextChecked ? 'required' : 'disabled' ?>
                            >
                            <?php if ($fieldError('next_follow_up_time') !== ''): ?>
                                <p class="mcc-field-error" role="alert">
                                    <?= esc($fieldError('next_follow_up_time')) ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </section>

    <div class="mcc-form-actions">
        <button class="mcc-button mcc-button-primary" type="submit">Save Follow-up</button>
        <a class="mcc-button mcc-button-secondary" href="<?= site_url('admissions/followups') ?>">Cancel</a>
        <a class="mcc-button mcc-button-secondary" href="<?= site_url('admissions/followups') ?>">Back to Follow-ups</a>
    </div>
</form>
