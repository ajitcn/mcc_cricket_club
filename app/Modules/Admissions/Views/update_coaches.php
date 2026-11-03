<?php

$a = is_array($admission ?? null) ? $admission : [];
$coaches = is_array($coaches ?? null) ? $coaches : [];
$fieldErrors = is_array($fieldErrors ?? null) ? $fieldErrors : [];
$admissionId = (int) ($a['id'] ?? 0);
$applicantName = trim((string) ($a['applicant_full_name'] ?? $a['lead_name'] ?? 'Not provided'));
$admissionNumber = trim((string) ($a['admission_number'] ?? ''));
$status = trim((string) ($a['status'] ?? 'Not provided'));

$coachOptions = array_map(
    static fn (array $coach): array => [
        'value' => (string) ($coach['id'] ?? 0),
        'label' => (string) ($coach['display_name'] ?? 'Coach'),
    ],
    $coaches,
);

$fieldError = static fn (string $field): string => trim((string) ($fieldErrors[$field] ?? ''));
$selectedCoach = old('batch_coach_id', (string) ($a['coach_user_id'] ?? ''));
$selectedPersonalCoach = old('personal_coach_id', (string) ($a['personal_coach_id'] ?? ''));
$reason = old('correction_reason', '');
?>
<?= view('staff_header', [
    'title' => 'Update Admission Coaches',
    'nav' => $nav ?? [],
    'message' => $message ?? null,
    'error' => $error ?? null,
    'suppressGlobalAlerts' => true,
]) ?>

<section class="mcc-card mcc-detail-card mcc-admission-coach-correction" aria-labelledby="update-coaches-heading">
    <div class="mcc-card-heading">
        <span class="mcc-eyebrow">Admission correction</span>
        <h1 id="update-coaches-heading">Update Coaches</h1>
        <p class="mcc-muted">Update only the Group Coach and Personal Coach assignments for this Admission.</p>
    </div>

    <dl class="mcc-detail-list">
        <dt>Applicant</dt>
        <dd><?= esc($applicantName !== '' ? $applicantName : 'Not provided') ?></dd>
        <dt>Admission number</dt>
        <dd><?= esc($admissionNumber !== '' ? $admissionNumber : 'Not provided') ?></dd>
        <dt>Status</dt>
        <dd><?= esc($status) ?></dd>
    </dl>

    <form method="post" action="<?= esc(site_url('admissions/' . $admissionId . '/update-coaches')) ?>" class="mcc-admission-coach-correction-form">
        <?= csrf_field() ?>
        <input type="hidden" name="expected_version" value="<?= (int) ($a['renewal_version'] ?? 0) ?>">

        <div class="row mcc-admission-fields">
            <div class="mcc-form-field col-12 col-md-6">
                <label for="update-coaches-group">Batch Coach</label>
                <?= view('components/admission-select', [
                    'id' => 'update-coaches-group',
                    'name' => 'batch_coach_id',
                    'label' => 'Batch Coach',
                    'selected' => $selectedCoach,
                    'options' => $coachOptions,
                    'forceBtSelect' => true,
                ]) ?>
                <?php if ($fieldError('batch_coach_id') !== ''): ?>
                    <p class="mcc-field-error" role="alert"><?= esc($fieldError('batch_coach_id')) ?></p>
                <?php endif; ?>
            </div>

            <div class="mcc-form-field col-12 col-md-6">
                <label for="update-coaches-personal">Personal Coach</label>
                <?= view('components/admission-select', [
                    'id' => 'update-coaches-personal',
                    'name' => 'personal_coach_id',
                    'label' => 'Personal Coach',
                    'selected' => $selectedPersonalCoach,
                    'options' => $coachOptions,
                    'forceBtSelect' => true,
                ]) ?>
                <?php if ($fieldError('personal_coach_id') !== ''): ?>
                    <p class="mcc-field-error" role="alert"><?= esc($fieldError('personal_coach_id')) ?></p>
                <?php endif; ?>
            </div>

            <div class="mcc-form-field col-12">
                <label for="update-coaches-reason">Correction reason</label>
                <textarea
                    class="form-control"
                    id="update-coaches-reason"
                    name="correction_reason"
                    maxlength="1000"
                    rows="4"
                    required
                ><?= esc($reason) ?></textarea>
                <?php if ($fieldError('correction_reason') !== ''): ?>
                    <p class="mcc-field-error" role="alert"><?= esc($fieldError('correction_reason')) ?></p>
                <?php endif; ?>
            </div>
        </div>

        <div class="mcc-form-actions">
            <a class="mcc-button mcc-button-secondary" href="<?= esc(site_url('admissions/' . $admissionId . '/page')) ?>">Cancel</a>
            <button class="mcc-button mcc-button-primary" type="submit">Save Coach Assignments</button>
        </div>
    </form>
</section>
