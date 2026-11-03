<?php
$a = is_array($admission ?? null) ? $admission : [];
$linkedLead = ! empty($a['lead_id']);
$leadEmail = $linkedLead
    ? trim((string) ($a['lead_email'] ?? ''))
    : trim((string) ($a['applicant_email'] ?? ''));
$leadPhone = $linkedLead
    ? trim((string) ($a['lead_phone'] ?? ''))
    : trim((string) ($a['applicant_phone'] ?? ''));
$oldValue = static function (string $field, string $fallback = '') use ($a): string {
    return esc((string) old($field, $a[$field] ?? $fallback));
};
$dateValue = (string) old('joining_date', $a['joining_date'] ?? '');
$admissionOptions = is_array($admissionOptions ?? null) ? $admissionOptions : [];
$fieldErrors = session()->getFlashdata('field_errors');
$fieldErrors = is_array($fieldErrors) ? $fieldErrors : [];
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateValue) === 1) {
    $dateValue = date('d-m-Y', strtotime($dateValue));
}
?>
<?= view('staff_header', [
    'title' => 'Complete Admission',
    'nav' => $nav,
    'message' => $message,
    'error' => $error,
    'suppressGlobalAlerts' => true,
]) ?>
<div class="mcc-admission-complete-page">
<section class="mcc-card mcc-detail-card mcc-admission-complete-heading" aria-labelledby="complete-admission-heading">
    <div class="mcc-card-heading">
        <div>
            <span class="mcc-eyebrow">Admissions</span>
            <h2 id="complete-admission-heading">Complete Admission</h2>
            <p class="mcc-muted mb-0">Confirm the admission details before creating the player record.</p>
        </div>
        <a class="mcc-button mcc-button-secondary" href="<?= site_url('admissions/' . (int) $a['id'] . '/page') ?>">Cancel</a>
    </div>
</section>
<form class="mcc-admission-complete-form" method="post" action="<?= site_url('admissions/' . (int) $a['id'] . '/complete') ?>">
    <?= csrf_field() ?>
    <section class="mcc-card mcc-detail-card" aria-labelledby="player-details-heading">
        <div class="mcc-card-heading">
            <div>
                <span class="mcc-eyebrow">Player details</span>
                <h3 id="player-details-heading">Applicant information</h3>
            </div>
        </div>
        <div class="row">
            <div class="col-12 col-md-4 mb-2">
                <label for="applicant-full-name">Full name</label>
                <input class="form-control" id="applicant-full-name" type="text" value="<?= esc($a['lead_name'] ?? $a['applicant_full_name'] ?? '') ?>" readonly>
            </div>
            <div class="col-12 col-md-4 mb-2">
                <label for="applicant-email">Email</label>
                <input class="form-control" id="applicant-email" type="email" value="<?= esc($leadEmail) ?>" readonly>
                <?php if ($linkedLead && $leadEmail === ''): ?>
                    <small class="form-text text-muted">No email recorded for the linked Lead.</small>
                <?php elseif ($linkedLead): ?>
                    <small class="form-text text-muted">Read-only contact detail from the linked Lead.</small>
                <?php endif; ?>
            </div>
            <div class="col-12 col-md-4 mb-2">
                <label for="applicant-phone">Phone</label>
                <input class="form-control" id="applicant-phone" type="text" value="<?= esc($leadPhone) ?>" readonly>
                <?php if ($linkedLead && $leadPhone === ''): ?>
                    <small class="form-text text-muted">No phone recorded for the linked Lead.</small>
                <?php elseif ($linkedLead): ?>
                    <small class="form-text text-muted">Read-only contact detail from the linked Lead.</small>
                <?php endif; ?>
            </div>
            <div class="col-12 col-md-4 mb-2">
                <label for="applicant-date-of-birth">Date of birth</label>
<input class="form-control" id="applicant-date-of-birth" type="text" value="<?= esc(mcc_user_date($a['lead_date_of_birth'] ?? $a['applicant_date_of_birth'] ?? null, 'Not available')) ?>" readonly>
            </div>
            <div class="col-12 col-md-4 mb-2">
                <label for="admission-branch">Branch</label>
                <input class="form-control" id="admission-branch" type="text" value="<?= esc($a['branch_name'] ?? '') ?>" readonly>
            </div>
        </div>
    </section>
    <section class="mcc-card mcc-detail-card" aria-labelledby="admission-details-heading">
        <div class="mcc-card-heading">
            <div>
                <span class="mcc-eyebrow">Admission details</span>
                <h3 id="admission-details-heading">Batch and admission information</h3>
            </div>
        </div>
        <div class="row">
            <div class="col-12 col-md-4 mb-2">
                <?php
                $batchOptions = array_map(static fn (array $batch): array => [
                    'value' => (string) ((int) $batch['id']),
                    'label' => (string) $batch['name'],
                ], $batches ?? []);
                ?>
                <?= view('components/admission-select', [
                    'id' => 'batch-id',
                    'name' => 'batch_id',
                    'label' => 'Batch / program',
                    'selected' => $oldValue('batch_id'),
                    'options' => $batchOptions,
                    'attributes' => ['required' => true],
                ]) ?>
                <?php if (isset($fieldErrors['batch_id'])): ?>
                    <p class="mcc-field-error" role="alert"><?= esc($fieldErrors['batch_id']) ?></p>
                <?php endif; ?>
            </div>
            <div class="col-12 col-md-4 mb-2">
                <label for="joining-date">Admission date</label>
                <input class="form-control" id="joining-date" name="joining_date" type="text" inputmode="numeric" placeholder="DD-MM-YYYY" autocomplete="off" data-mcc-date-picker data-mcc-date-picker-no-toggle data-mcc-date-format="d-m-Y" value="<?= esc($dateValue) ?>" required>
                <?php if (isset($fieldErrors['joining_date'])): ?>
                    <p class="mcc-field-error" role="alert"><?= esc($fieldErrors['joining_date']) ?></p>
                <?php endif; ?>
            </div>
            <?php foreach (['gender' => 'Gender', 'age_group' => 'Age group', 'blood_group' => 'Blood group', 'jersey_size' => 'Jersey size', 'playing_role' => 'Playing role', 'batting_style' => 'Batting style', 'bowling_style' => 'Bowling style', 'strong_hand' => 'Strong hand'] as $field => $label): ?>
                <div class="col-12 col-md-4 mb-2">
                    <label for="<?= esc($field) ?>"><?= esc($label) ?></label>
                    <?php if (isset($admissionOptions[$field])): ?>
                        <?= view('components/admission-select', [
                            'id' => $field,
                            'name' => $field,
                            'label' => $label,
                            'selected' => $oldValue($field),
                            'options' => array_map(static fn (string $option): array => [
                                'value' => $option,
                                'label' => $option,
                            ], $admissionOptions[$field]),
                            'attributes' => [],
                        ]) ?>
                    <?php else: ?>
                        <input class="form-control" id="<?= esc($field) ?>" name="<?= esc($field) ?>" type="text" value="<?= $oldValue($field) ?>">
                    <?php endif; ?>
                    <?php if (isset($fieldErrors[$field])): ?>
                        <p class="mcc-field-error" role="alert"><?= esc($fieldErrors[$field]) ?></p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            <div class="col-12 col-md-6 mb-2">
                <label for="medical-conditions">Medical conditions</label>
                <textarea class="form-control" id="medical-conditions" name="medical_conditions" rows="3"><?= $oldValue('medical_conditions') ?></textarea>
            </div>
        </div>
    </section>
    <section class="mcc-card mcc-detail-card" aria-labelledby="guardian-details-heading">
        <div class="mcc-card-heading">
            <div>
                <span class="mcc-eyebrow">Required information</span>
                <h3 id="guardian-details-heading">Parent / guardian</h3>
            </div>
        </div>
        <div class="row">
            <?php foreach (['father_name' => 'Father name', 'mother_name' => 'Mother name', 'parent_mobile' => 'Parent mobile', 'parent_email' => 'Parent email', 'emergency_contact' => 'Emergency contact'] as $field => $label): ?>
                <div class="col-12 col-md-4 mb-2">
                    <label for="<?= esc($field) ?>"><?= esc($label) ?></label>
                    <input class="form-control" id="<?= esc($field) ?>" name="<?= esc($field) ?>" type="<?= $field === 'parent_email' ? 'email' : 'text' ?>" value="<?= $oldValue($field) ?>">
                </div>
            <?php endforeach; ?>
        </div>
    </section>
    <section class="mcc-card mcc-detail-card" aria-labelledby="documents-heading">
        <div class="mcc-card-heading">
            <div>
                <span class="mcc-eyebrow">Documents and fees</span>
                <h3 id="documents-heading">Existing requirements</h3>
            </div>
        </div>
        <p class="mcc-muted mb-0">Document upload and admission-fee collection are not available in the current approved schema and workflow.</p>
    </section>
    <div class="row mt-3 mcc-admission-complete-actions">
        <div class="col-12 text-right">
            <?php if (! empty($message)): ?>
                <p class="mcc-alert mcc-alert-success mcc-admission-response" data-admission-response role="status">
                    <?= esc($message) ?>
                </p>
            <?php elseif (! empty($error)): ?>
                <p class="mcc-alert mcc-alert-danger mcc-admission-response" data-admission-response role="alert">
                    <?= esc($error) ?>
                </p>
            <?php else: ?>
                <p class="mcc-alert mcc-admission-response" data-admission-response hidden role="status"></p>
            <?php endif; ?>
            <a class="mcc-button mcc-button-secondary mr-2" href="<?= site_url('admissions/' . (int) $a['id'] . '/page') ?>">Cancel</a>
            <button class="mcc-button mcc-button-primary" type="submit"><i class="fa-solid fa-user-check" aria-hidden="true"></i> Complete Admission</button>
        </div>
    </div>
</form>
</div>
<?= view('staff_footer') ?>
