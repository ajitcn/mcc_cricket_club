<?php

$a = is_array($admission ?? null) ? $admission : [];
$documents = is_array($documents ?? null) ? $documents : [];
$history = is_array($a['history'] ?? null) ? $a['history'] : [];
$followUps = is_array($a['follow_ups'] ?? null) ? $a['follow_ups'] : [];
$sessionProgress = is_array($a['session_progress'] ?? null) ? $a['session_progress'] : [];
$ratingService = new \App\Services\TrialRatingService();

$value = static function (string $field, string $fallback = '—') use ($a): string {
    $fieldValue = $a[$field] ?? null;

    if ($fieldValue === null || $fieldValue === '') {
        return $fallback;
    }

    return esc((string) $fieldValue);
};

$formatDate = static function (?string $date): string {
    return esc(mcc_follow_up_datetime($date, '—'));
};

$status = (string) ($a['status'] ?? '');
$statusClass = strtolower($status);
$admissionNumber = trim((string) ($a['admission_number'] ?? ''));
$humanizeTrialValue = static function (?string $value, string $fallback = 'Not recorded'): string {
    $value = trim((string) $value);

    if ($value === '') {
        return $fallback;
    }

    return ucwords(str_replace(['_', '-'], ' ', $value));
};
$trialBadgeClass = static function (?string $value): string {
    $value = strtolower(trim((string) $value));

    return match (true) {
        $value === '' => 'mcc-badge-neutral',
        str_contains($value, 'cancel') || str_contains($value, 'reject') || str_contains($value, 'no_show') || $value === 'not_selected' => 'mcc-badge-danger',
        str_contains($value, 'select') || str_contains($value, 'attend') || str_contains($value, 'complete') || str_contains($value, 'pass') => 'mcc-badge-success',
        str_contains($value, 'reschedul') || str_contains($value, 'fail') => 'mcc-badge-warning',
        default => 'mcc-badge-info',
    };
};
$trialStatusLabels = [
    'booked' => 'Booked',
    'attended' => 'Attended',
    'no_show' => 'No show',
    'cancelled' => 'Cancelled',
    'rescheduled' => 'Rescheduled',
];
$trialOutcomeLabels = [
    'pending' => 'Pending',
    'selected' => 'Selected',
    'not_selected' => 'Not selected',
];
$trialStatus = trim((string) ($a['trial_status'] ?? ''));
$trialOutcome = trim((string) ($a['trial_selection_outcome'] ?? ''));
$trialStatusLabel = $trialStatusLabels[$trialStatus] ?? $humanizeTrialValue($trialStatus);
$trialOutcomeLabel = $trialOutcomeLabels[$trialOutcome] ?? $humanizeTrialValue($trialOutcome);
$formatMoney = static function (mixed $amount): string {
    $value = trim((string) ($amount ?? ''));

    if ($value === '') {
        return '₹0.00';
    }

    $parts = array_pad(explode('.', $value, 2), 2, '00');
    $whole = $parts[0];
    $fraction = $parts[1];

    return '₹' . number_format((int) $whole, 0, '.', ',') . '.' . str_pad($fraction, 2, '0');
};
$linkedLead = ! empty($a['lead_id']);
$applicantEmail = $linkedLead
    ? trim((string) ($a['lead_email'] ?? ''))
    : trim((string) ($a['applicant_email'] ?? ''));
$applicantPhone = $linkedLead
    ? trim((string) ($a['lead_phone'] ?? ''))
    : trim((string) ($a['applicant_phone'] ?? ''));
$isFragment = ! empty($fragment);
$previewableMimeTypes = [
    'application/pdf',
    'image/jpeg',
    'image/png',
];
?>
<?php if (! $isFragment): ?>
    <?= view('staff_header', [
        'title' => 'Admission details',
        'nav' => $nav ?? [],
        'message' => $message ?? null,
        'error' => $error ?? null,
    ]) ?>
<?php endif; ?>

<div class="mcc-admission-detail">
    <section class="mcc-card mcc-detail-hero mcc-admission-detail-hero" aria-labelledby="admission-details-title">
        <div class="mcc-admission-detail-identity">
            <span class="mcc-eyebrow">Admission profile</span>
            <h2 id="admission-details-title" class="mcc-follow-up-applicant-name">
                <?= $value('lead_name', (string) ($a['applicant_full_name'] ?? 'Direct Admission')) ?>
            </h2>
            <p class="mcc-admission-reference">
                Admission #<?= esc($admissionNumber !== '' ? $admissionNumber : 'Missing number') ?>
            </p>
            <div class="mcc-detail-badges" aria-label="Admission classification">
                <span class="mcc-badge mcc-status-badge mcc-status-<?= esc($statusClass) ?>">
                    <?= $value('status') ?>
                </span>
                <span class="mcc-badge mcc-badge-info">
                    <?= $value('branch_name', 'Authorized branch') ?>
                </span>
                <?php if (($a['lead_id'] ?? null) === null): ?>
                    <span class="mcc-badge mcc-badge-info">Direct Admission</span>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <div class="mcc-detail-grid">
        <section class="mcc-card mcc-detail-card" aria-labelledby="applicant-heading">
            <div class="mcc-card-heading">
                <span class="mcc-eyebrow">Applicant</span>
                <h2 id="applicant-heading">Applicant information</h2>
            </div>
            <dl class="mcc-detail-list">
                <dt>Full name</dt>
                <dd><?= $value('applicant_full_name', (string) ($a['lead_name'] ?? '—')) ?></dd>
                <dt>Email</dt>
                <dd>
                    <?php if ($applicantEmail !== ''): ?>
                        <a href="mailto:<?= esc($applicantEmail) ?>"><?= esc($applicantEmail) ?></a>
                    <?php else: ?>
                        <span class="mcc-muted">
                            <?= $linkedLead ? 'No email recorded for the linked Lead.' : 'No email recorded.' ?>
                        </span>
                    <?php endif; ?>
                </dd>
                <dt>Phone</dt>
                <dd>
                    <?php if ($applicantPhone !== ''): ?>
                        <a href="tel:<?= esc($applicantPhone) ?>"><?= esc($applicantPhone) ?></a>
                    <?php else: ?>
                        <span class="mcc-muted">
                            <?= $linkedLead ? 'No phone recorded for the linked Lead.' : 'No phone recorded.' ?>
                        </span>
                    <?php endif; ?>
                </dd>
                <dt>Date of birth</dt>
                <dd><?= esc(mcc_user_date($a['applicant_date_of_birth'] ?? null, 'â€”')) ?></dd>
                <dt>Age</dt>
                <dd><?= $value('age') ?></dd>
                <dt>Age group</dt>
                <dd><?= $value('age_group') ?></dd>
            </dl>
        </section>

        <section class="mcc-card mcc-detail-card" aria-labelledby="admission-heading">
            <div class="mcc-card-heading">
                <span class="mcc-eyebrow">Admission</span>
                <h2 id="admission-heading">Admission information</h2>
            </div>
            <dl class="mcc-detail-list">
                <dt>Source</dt>
                <dd>
                    <?php if (($a['lead_id'] ?? null) === null): ?>
                        <span class="mcc-badge mcc-badge-info">Direct Admission</span>
                    <?php else: ?>
                        <a href="<?= site_url('crm/leads/' . (int) $a['lead_id']) ?>">
                            Lead: <?= $value('lead_name', 'Lead #' . (int) $a['lead_id']) ?>
                        </a>
                    <?php endif; ?>
                </dd>
                <dt>Trial</dt>
                <dd>
                    <?php if (! empty($a['trial_id'])): ?>
                        <a href="<?= site_url('crm/trials/' . (int) $a['trial_id']) ?>">
                            Trial #<?= (int) $a['trial_id'] ?>
                        </a>
                    <?php else: ?>
                        No Trial linked
                    <?php endif; ?>
                </dd>
                <dt>Admission Test</dt>
                <dd>
                    <?php if (! empty($a['admission_test_id'])): ?>
                        <a href="<?= site_url('admission-tests/' . (int) $a['admission_test_id']) ?>">View Admission Test</a>
                    <?php else: ?>
                        No Admission Test linked
                    <?php endif; ?>
                </dd>
                <dt>Batch</dt>
                <dd><?= $value('batch_name', (string) ($a['batch_id'] ?? '—')) ?></dd>
                <dt>Batch Coach</dt>
                <dd><?= $value('assigned_coach_name', 'Not assigned') ?></dd>
                <dt>Personal Coach</dt>
                <dd><?= $value('personal_coach_name', 'Not assigned') ?></dd>
                <dt>Academic plan</dt>
                <dd><?= $value('academic_plan_label', 'Not applicable') ?></dd>
                <dt>Coaching plan</dt>
                <dd><?= $value('coaching_plan_label', 'Not applicable') ?></dd>
                <dt>Fee plan</dt>
                <dd><?= $value('fee_plan_label', 'Not applicable') ?></dd>
                <dt>Hostel plan</dt>
                <dd><?= $value('hostel_plan_label', 'Not applicable') ?></dd>
                <dt>Admission Date</dt>
                <dd><?= esc(mcc_user_date($a['admission_date_display'] ?? null, 'Not recorded')) ?></dd>
                <dt>Created</dt>
                <dd><?= $formatDate($a['created_at'] ?? null) ?></dd>
                <dt>Updated</dt>
                <dd><?= $formatDate($a['updated_at'] ?? null) ?></dd>
            </dl>
        </section>
    </div>

    <section class="mcc-card mcc-detail-card" aria-labelledby="coaching-options-heading">
        <div class="mcc-card-heading">
            <span class="mcc-eyebrow">Training selection</span>
            <h2 id="coaching-options-heading">Training Selection and Coaching Options</h2>
        </div>
        <dl class="mcc-detail-list">
            <dt>Coaching type</dt>
            <dd><?= $value('coaching_type') ?></dd>
            <dt>Personal Coaching Days</dt>
            <dd><?= $value('personal_coaching_days') ?></dd>
            <dt>Personal Coaching Duration</dt>
            <dd><?= $value('personal_coaching_duration') ?></dd>
            <dt>Estimated Personal Session</dt>
            <dd><?= $value('estimated_personal_session') ?></dd>
            <dt>Elite Camp Days</dt>
            <dd><?= $value('elite_camp_days') ?></dd>
            <dt>Elite Camp Duration</dt>
            <dd><?= $value('elite_camp_duration') ?></dd>
            <dt>Estimated Elite Session</dt>
            <dd><?= $value('estimated_elite_session') ?></dd>
            <?php foreach (['personal' => 'Personal Session', 'elite' => 'Elite Session'] as $progressKey => $progressLabel): ?>
                <?php $progress = is_array($sessionProgress[$progressKey] ?? null) ? $sessionProgress[$progressKey] : []; ?>
                <dt><?= esc($progressLabel) ?> progress</dt>
                <dd>
                    <span class="mcc-session-progress-badge mcc-session-progress-<?= esc($progressKey) ?>">
                        <strong><?= (int) ($progress['completed'] ?? 0) ?>/<?= ($progress['target'] ?? null) === null ? '—' : (int) $progress['target'] ?></strong>
                        <span>completed</span>
                    </span>
                </dd>
            <?php endforeach; ?>
        </dl>
    </section>

    <section class="mcc-card mcc-detail-card" aria-labelledby="fees-heading">
        <div class="mcc-card-heading">
            <span class="mcc-eyebrow">Fees</span>
            <h2 id="fees-heading">Fees</h2>
        </div>
        <dl class="mcc-detail-list">
            <dt>Coaching Fee</dt>
            <dd><?= $formatMoney($a['training_fee'] ?? null) ?></dd>
            <dt>Hostel Fee</dt>
            <dd><?= $formatMoney($a['hostel_charges'] ?? null) ?></dd>
            <dt>Total Coaching + Hostel Fee</dt>
            <dd><?= $formatMoney($a['total_coaching_hostel_fee'] ?? null) ?></dd>
            <dt>Taxable Amount</dt>
            <dd><?= $formatMoney($a['total_taxable_fee'] ?? null) ?></dd>
            <dt>Tax Rate</dt>
            <dd><?= $a['tax_rate'] === null || $a['tax_rate'] === '' ? 'Not recorded' : esc((string) $a['tax_rate']) . '%' ?></dd>
            <dt>Calculated Tax Amount</dt>
            <dd><?= $formatMoney($a['calculated_tax_amount'] ?? null) ?></dd>
        </dl>
    </section>

    <?php $installmentStatusRows = is_array($a['installment_status_rows'] ?? null) ? $a['installment_status_rows'] : []; ?>
    <?php $canRecordInstallmentPayment = ! empty($a['can_update']); ?>
    <section class="mcc-card mcc-detail-card mcc-admission-installment-status-card" aria-labelledby="fee-installment-status-heading">
        <div class="mcc-card-heading">
            <div>
                <span class="mcc-eyebrow">Payment tracking</span>
                <h2 id="fee-installment-status-heading">Fee Installment Status</h2>
            </div>
        </div>
        <?php if ($installmentStatusRows === []): ?>
            <p class="mcc-empty-state mcc-detail-empty-state">No installment rows are recorded for this Admission.</p>
        <?php else: ?>
            <?php foreach (['coaching' => 'Coaching installments', 'hostel' => 'Hostel installments'] as $installmentType => $installmentTitle): ?>
                <?php
                $typeRows = array_values(array_filter(
                    $installmentStatusRows,
                    static fn (array $row): bool => ($row['installment_type'] ?? '') === $installmentType,
                ));
                ?>
                <div class="mcc-admission-installment-status-group">
                    <h3><?= esc($installmentTitle) ?></h3>
                    <?php if ($typeRows === []): ?>
                        <p class="mcc-empty-state mcc-detail-empty-state">No <?= esc(strtolower($installmentTitle)) ?> are recorded.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table mcc-table mcc-admission-installment-status-table">
                                <thead>
                                    <tr>
                                        <th scope="col">Year</th>
                                        <th scope="col">Installment</th>
                                        <th scope="col">Amount</th>
                                        <th scope="col">Due date</th>
                                        <th scope="col">Total received</th>
                                        <th scope="col">Balance</th>
                                        <th scope="col">Payment status</th>
                                        <th scope="col">Latest payment</th>
                                        <?php if ($canRecordInstallmentPayment): ?>
                                            <th scope="col">Actions</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($typeRows as $row): ?>
                                        <?php $paymentStatus = mcc_admission_payment_status_presentation($row['payment_status'] ?? null); ?>
                                        <tr>
                                            <td><?= esc((string) ($row['year_label'] ?? 'Year ' . ($row['year_number'] ?? 'Not provided'))) ?></td>
                                            <td><?= esc((string) ($row['installment_number'] ?? 'Not provided')) ?></td>
                                            <td class="mcc-amount-column"><?= $formatMoney($row['amount'] ?? null) ?></td>
                                            <td><?= esc(mcc_user_date($row['due_date'] ?? null, 'Not provided')) ?></td>
                                            <td class="mcc-amount-column"><?= $formatMoney($row['total_received'] ?? null) ?></td>
                                            <td class="mcc-amount-column"><?= $formatMoney($row['balance'] ?? null) ?></td>
                                            <td>
                                                <span class="mcc-badge mcc-status-badge <?= esc($paymentStatus['class']) ?>">
                                                    <?= esc($paymentStatus['label']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if (! empty($row['latest_payment_date'])): ?>
                                                    <?= esc(mcc_user_date($row['latest_payment_date'], 'Not provided')) ?>
                                                    <?php if (! empty($row['latest_payment_receipt'])): ?>
                                                        <span class="mcc-table-secondary-text">Receipt <?= esc((string) $row['latest_payment_receipt']) ?></span>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    Not captured
                                                <?php endif; ?>
                                            </td>
                                            <?php if ($canRecordInstallmentPayment): ?>
                                                <td>
                                                    <?php $installmentId = (int) ($row['installment_id'] ?? 0); ?>
                                                    <?php if ($installmentId > 0): ?>
                                                        <a class="mcc-button mcc-button-secondary mcc-button-small" href="<?= esc(site_url('admissions/fee-installments/' . rawurlencode((string) ($row['installment_type'] ?? '')) . '/' . $installmentId . '/payment')) ?>">
                                                            Record Payment
                                                        </a>
                                                    <?php else: ?>
                                                        <span class="mcc-table-secondary-text">Not available</span>
                                                    <?php endif; ?>
                                                </td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>

    <section class="mcc-card mcc-detail-card mcc-trial-summary-card" aria-labelledby="trial-summary-heading">
        <div class="mcc-card-heading mcc-detail-heading-with-status">
            <div>
                <span class="mcc-eyebrow">Trial</span>
                <h2 id="trial-summary-heading">Trial summary</h2>
            </div>
            <?php if (! empty($a['trial_id'])): ?>
                <div class="mcc-detail-badges" aria-label="Trial status and outcome">
                    <span class="mcc-badge <?= esc($trialBadgeClass($trialStatus)) ?>">
                        Status: <?= esc($trialStatusLabel) ?>
                    </span>
                    <span class="mcc-badge <?= esc($trialBadgeClass($trialOutcome)) ?>">
                        Outcome: <?= esc($trialOutcomeLabel) ?>
                    </span>
                </div>
            <?php endif; ?>
        </div>
        <?php if (empty($a['trial_id'])): ?>
            <p class="mcc-empty-state mcc-detail-empty-state">No Trial is linked to this admission.</p>
        <?php else: ?>
            <dl class="mcc-trial-summary-grid">
                <div class="mcc-trial-summary-item">
                    <dt>Date</dt>
                    <dd><?= esc(mcc_user_date($a['trial_date'] ?? null, 'Not scheduled')) ?></dd>
                </div>
                <div class="mcc-trial-summary-item">
                    <dt>Time</dt>
                    <dd><?= ! empty($a['trial_time']) ? esc(substr((string) $a['trial_time'], 0, 5)) : 'Not scheduled' ?></dd>
                </div>
                <div class="mcc-trial-summary-item">
                    <dt>Venue</dt>
                    <dd><?= ! empty($a['trial_location']) ? esc((string) $a['trial_location']) : 'Not recorded' ?></dd>
                </div>
                <div class="mcc-trial-summary-item">
                    <dt>Branch</dt>
                    <dd><?= $value('branch_name', 'Authorized branch') ?></dd>
                </div>
                <div class="mcc-trial-summary-item">
                    <dt>Primary Coach / Evaluator</dt>
                    <dd><?= $value('trial_coach_name') ?></dd>
                    <dt>Secondary Coach / Evaluator</dt>
                    <dd><?= $value('secondary_trial_coach_name', 'Not assigned') ?></dd>
                </div>
            </dl>
            <div class="mcc-trial-notes" aria-labelledby="trial-notes-heading">
                <h3 id="trial-notes-heading">Notes</h3>
                <?php if (! empty($a['trial_remarks'])): ?>
                    <p><?= nl2br(esc((string) $a['trial_remarks'])) ?></p>
                <?php else: ?>
                    <p class="mcc-muted">No trial notes recorded.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </section>

    <?php $currentPeriod = $a['current_period'] ?? null; ?>
    <section class="mcc-card mcc-detail-card" aria-labelledby="current-period-heading">
        <div class="mcc-card-heading">
            <span class="mcc-eyebrow">Admission period</span>
            <h2 id="current-period-heading">Current period</h2>
        </div>
        <?php if (is_array($currentPeriod)): ?>
            <dl class="mcc-detail-list">
                <dt>Period</dt>
                <dd><?= (int) ($currentPeriod['period_number'] ?? 0) ?></dd>
                <dt>Admission Start Date</dt>
                <dd><?= esc(mcc_user_date($currentPeriod['start_date'] ?? null, 'Not recorded')) ?></dd>
                <dt>Admission End Date</dt>
                <dd><?= esc(mcc_user_date($currentPeriod['end_date'] ?? null, 'Not recorded')) ?></dd>
                <dt>Status</dt>
                <dd><?= esc((string) ($currentPeriod['status'] ?? 'current')) ?></dd>
            </dl>
        <?php else: ?>
            <p class="mcc-empty-state mcc-detail-empty-state">No period history has been recorded yet.</p>
        <?php endif; ?>
    </section>

    <div class="mcc-detail-grid">
        <section class="mcc-card mcc-detail-card" aria-labelledby="cricket-heading">
            <div class="mcc-card-heading">
                <span class="mcc-eyebrow">Profile</span>
                <h2 id="cricket-heading">Cricket information</h2>
            </div>
            <dl class="mcc-detail-list">
                <dt>Playing role</dt>
                <dd><?= $value('playing_role') ?></dd>
                <dt>Batting style</dt>
                <dd><?= $value('batting_style') ?></dd>
                <dt>Bowling style</dt>
                <dd><?= $value('bowling_style') ?></dd>
                <dt>Strong hand</dt>
                <dd><?= $value('strong_hand') ?></dd>
                <?php foreach ([
                    'ability_overall' => 'Overall Rating',
                    'ability_batting' => 'Batting Rating',
                    'ability_bowling' => 'Bowling Rating',
                    'ability_wicketkeeping' => 'Wicketkeeping Rating',
                    'ability_fitness' => 'Fitness Rating',
                ] as $ratingField => $ratingLabel): ?>
                    <dt><?= esc($ratingLabel) ?></dt>
                    <dd>
                        <?php
                        $ratingValue = is_array($a['admission_ratings'] ?? null)
                            ? ($a['admission_ratings'][$ratingField] ?? null)
                            : ($a[$ratingField] ?? null);
                        ?>
                        <?= $ratingValue === null || $ratingValue === ''
                            ? '&mdash;'
                            : esc($ratingService->formatted($ratingValue)) ?>
                    </dd>
                <?php endforeach; ?>
                <dt>Gender</dt>
                <dd><?= $value('gender') ?></dd>
                <dt>Jersey size</dt>
                <dd><?= $value('jersey_size') ?></dd>
                <dt>Hobbies</dt>
                <dd><?= nl2br($value('hobbies')) ?></dd>
                <dt>Achievements</dt>
                <dd><?= nl2br($value('achievements')) ?></dd>
            </dl>
        </section>

        <section class="mcc-card mcc-detail-card" aria-labelledby="branch-heading">
            <div class="mcc-card-heading">
                <span class="mcc-eyebrow">Ownership</span>
                <h2 id="branch-heading">Branch / ownership</h2>
            </div>
            <dl class="mcc-detail-list">
                <dt>Branch</dt>
                <dd><?= $value('branch_name', 'Authorized branch') ?></dd>
            </dl>
        </section>
    </div>

    <div class="mcc-detail-grid">
        <section class="mcc-card mcc-detail-card" aria-labelledby="medical-heading">
            <div class="mcc-card-heading">
                <span class="mcc-eyebrow">Health</span>
                <h2 id="medical-heading">Medical information</h2>
            </div>
            <dl class="mcc-detail-list">
                <dt>Blood group</dt>
                <dd><?= $value('blood_group') ?></dd>
                <dt>Medical conditions</dt>
                <dd><?= $value('medical_conditions') ?></dd>
            </dl>
        </section>

        <section class="mcc-card mcc-detail-card" aria-labelledby="guardian-heading">
            <div class="mcc-card-heading">
                <span class="mcc-eyebrow">Reachability</span>
                <h2 id="guardian-heading">Parent / guardian</h2>
            </div>
            <dl class="mcc-detail-list">
                <dt>Father</dt>
                <dd><?= $value('father_name') ?></dd>
                <dt>Mother</dt>
                <dd><?= $value('mother_name') ?></dd>
                <dt>Parent mobile</dt>
                <dd><?= $value('parent_mobile') ?></dd>
                <dt>Parent email</dt>
                <dd><?= $value('parent_email') ?></dd>
                <dt>Emergency contact</dt>
                <dd><?= $value('emergency_contact') ?></dd>
            </dl>
        </section>
    </div>

    <?php
    $profilePhoto = null;
    $admissionDocuments = [];
    $imageDocuments = [];
    $nonImageDocuments = [];

    foreach ($documents as $document) {
        if (($document['document_type'] ?? '') === 'player_photo') {
            $profilePhoto = $document;
            continue;
        }

        $admissionDocuments[] = $document;

        if (in_array((string) ($document['mime_type'] ?? ''), ['image/jpeg', 'image/png'], true)) {
            $imageDocuments[] = $document;
            continue;
        }

        $nonImageDocuments[] = $document;
    }
    ?>
    <section class="mcc-card mcc-detail-card mcc-admission-documents-section" aria-labelledby="documents-heading">
        <div class="mcc-card-heading">
            <span class="mcc-eyebrow">Private files</span>
            <h2 id="documents-heading">Documents &amp; Photo</h2>
        </div>
        <div class="mcc-admission-documents-grid">
            <div class="mcc-admission-photo-area">
                <h3>Profile photo</h3>
                <?php if (is_array($profilePhoto) && ! empty($profilePhoto['can_view'])): ?>
                    <?php if (! empty($profilePhoto['can_download'])): ?>
                        <a
                            class="mcc-admission-image-link"
                            href="<?= site_url('documents/' . (int) $profilePhoto['id'] . '/preview') ?>"
                            target="_blank"
                            rel="noopener"
                        >
                            <img
                                class="mcc-admission-photo-preview"
                                src="<?= site_url('documents/' . (int) $profilePhoto['id'] . '/preview') ?>"
                                alt="<?= esc((string) $profilePhoto['original_name']) ?>"
                            >
                        </a>
                    <?php endif; ?>
                    <p class="mcc-admission-file-name"><?= esc((string) $profilePhoto['original_name']) ?></p>
                    <span class="mcc-admission-document-actions">
                        <?php if (! empty($profilePhoto['can_download'])
                            && in_array((string) ($profilePhoto['mime_type'] ?? ''), $previewableMimeTypes, true)): ?>
                            <a
                                class="mcc-button mcc-button-secondary"
                                href="<?= site_url('documents/' . (int) $profilePhoto['id'] . '/preview') ?>"
                                target="_blank"
                                rel="noopener"
                            >
                                Preview
                            </a>
                        <?php else: ?>
                            <span class="mcc-muted">Preview unavailable</span>
                        <?php endif; ?>
                        <?php if (! empty($profilePhoto['can_download'])): ?>
                            <a class="mcc-button mcc-button-secondary" href="<?= site_url('documents/' . (int) $profilePhoto['id'] . '/download') ?>">
                                Download
                            </a>
                        <?php endif; ?>
                    </span>
                <?php else: ?>
                    <p class="mcc-empty-state mcc-detail-empty-state">No profile photo uploaded.</p>
                <?php endif; ?>
            </div>
            <div class="mcc-admission-files-area">
                <h3>Admission documents</h3>
                <?php if ($admissionDocuments === []): ?>
                    <p class="mcc-empty-state mcc-detail-empty-state">No admission documents uploaded.</p>
                <?php else: ?>
                    <?php if ($imageDocuments !== []): ?>
                        <div class="row mcc-admission-image-document-row">
                            <?php foreach ($imageDocuments as $document): ?>
                                <?php
                                $previewUrl = site_url('documents/' . (int) $document['id'] . '/preview');
                                $downloadUrl = site_url('documents/' . (int) $document['id'] . '/download');
                                ?>
                                <div class="col-12 col-md-6 mcc-admission-image-document-column">
                                    <article class="mcc-admission-image-document">
                                        <?php if (! empty($document['can_download'])): ?>
                                            <a
                                                class="mcc-admission-document-preview"
                                                href="<?= esc($previewUrl) ?>"
                                                target="_blank"
                                                rel="noopener"
                                            >
                                                <img
                                                    class="mcc-admission-document-image"
                                                    src="<?= esc($previewUrl) ?>"
                                                    alt="Admission document image"
                                                >
                                            </a>
                                        <?php endif; ?>
                                        <div class="mcc-admission-image-document-meta">
                                            <span>
                                                <?= esc(ucwords(str_replace('_', ' ', (string) $document['document_type']))) ?>
                    &middot; <?= esc(mcc_user_datetime($document['created_at'] ?? null)) ?>
                                            </span>
                                        </div>
                                        <?php if (! empty($document['can_view'])): ?>
                                            <span class="mcc-admission-document-actions">
                                                <?php if (! empty($document['can_download'])): ?>
                                                    <a
                                                        class="mcc-button mcc-button-secondary"
                                                        href="<?= esc($previewUrl) ?>"
                                                        target="_blank"
                                                        rel="noopener"
                                                    >
                                                        Preview
                                                    </a>
                                                <?php else: ?>
                                                    <span class="mcc-muted">Preview unavailable</span>
                                                <?php endif; ?>
                                                <?php if (! empty($document['can_download'])): ?>
                                                    <a
                                                        class="mcc-button mcc-button-secondary"
                                                        href="<?= esc($downloadUrl) ?>"
                                                    >
                                                        Download
                                                    </a>
                                                <?php endif; ?>
                                                <?php if (! empty($document['can_remove'])): ?>
                                                    <form
                                                        method="post"
                                                        action="<?= site_url('admissions/' . (int) $admission['id'] . '/documents/' . (int) $document['id'] . '/remove') ?>"
                                                        onsubmit="return confirm('Remove this document?');"
                                                    >
                                                        <?= csrf_field() ?>
                                                        <button class="mcc-button mcc-button-danger" type="submit">
                                                            Remove Document
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </span>
                                        <?php endif; ?>
                                    </article>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <ul class="mcc-admission-document-list">
                        <?php foreach ($nonImageDocuments as $document): ?>
                            <?php
                            $previewUrl = site_url('documents/' . (int) $document['id'] . '/preview');
                            $downloadUrl = site_url('documents/' . (int) $document['id'] . '/download');
                            ?>
                            <li>
                                <span class="mcc-admission-document-icon" aria-hidden="true">
                                    <i class="fa-solid fa-file-lines"></i>
                                </span>
                                <div>
                                    <strong><?= esc((string) $document['original_name']) ?></strong>
                                    <span>
                                        <?= esc(ucwords(str_replace('_', ' ', (string) $document['document_type']))) ?>
                    · <?= esc(mcc_user_datetime($document['created_at'] ?? null)) ?>
                                    </span>
                                </div>
                                <?php if (! empty($document['can_view'])): ?>
                                    <span class="mcc-admission-document-actions">
                                        <?php if (! empty($document['can_download'])
                                            && in_array((string) ($document['mime_type'] ?? ''), $previewableMimeTypes, true)): ?>
                                            <a
                                                class="mcc-button mcc-button-secondary"
                                                href="<?= esc($previewUrl) ?>"
                                                target="_blank"
                                                rel="noopener"
                                            >
                                                Preview
                                            </a>
                                        <?php else: ?>
                                            <span class="mcc-muted">Preview unavailable</span>
                                        <?php endif; ?>
                                        <?php if (! empty($document['can_download'])): ?>
                                            <a
                                                class="mcc-button mcc-button-secondary"
                                                href="<?= esc($downloadUrl) ?>"
                                            >
                                                Download
                                            </a>
                                        <?php endif; ?>
                                        <?php if (! empty($document['can_remove'])): ?>
                                            <form
                                                method="post"
                                                action="<?= site_url('admissions/' . (int) $admission['id'] . '/documents/' . (int) $document['id'] . '/remove') ?>"
                                                onsubmit="return confirm('Remove this document?');"
                                            >
                                                <?= csrf_field() ?>
                                                <button class="mcc-button mcc-button-danger" type="submit">
                                                    Remove Document
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </span>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="mcc-card mcc-detail-card" aria-labelledby="status-heading">
        <div class="mcc-card-heading">
            <span class="mcc-eyebrow">Current status / lifecycle</span>
            <h2 id="status-heading">Status and conversion</h2>
        </div>
        <p class="mcc-detail-status-line">
            Current status:
            <span class="mcc-badge mcc-status-badge mcc-status-<?= esc($statusClass) ?>"><?= $value('status') ?></span>
        </p>
        <?php if (! empty($a['approved_by_name'])): ?>
            <p class="mcc-muted">
                Approved by <?= esc($a['approved_by_name']) ?>
                <?php if (! empty($a['approved_at'])): ?>
                    on <?= $formatDate($a['approved_at']) ?>
                <?php endif; ?>
            </p>
        <?php endif; ?>
        <?php if (($a['converted_player_id'] ?? null) !== null && ($can_view_player ?? false)): ?>
            <p>
                Linked Player:
                <a href="<?= site_url('players/' . (int) $a['converted_player_id'] . '/page') ?>">
                    <?= esc((string) ($a['converted_player_name'] ?? 'Player')) ?>
                </a>
            </p>
            <dl class="mcc-detail-list">
                <dt>Player reference</dt>
                <dd>Player #<?= (int) $a['converted_player_id'] ?></dd>
                <dt>Conversion date</dt>
                <dd><?= esc($formatDate($a['conversion_date'] ?? null)) ?></dd>
                <dt>Branch</dt>
                <dd><?= $value('branch_name') ?></dd>
                <dt>Batch</dt>
                <dd><?= $value('batch_name', 'Not assigned') ?></dd>
                <dt>Age group</dt>
                <dd><?= $value('age_group', 'Not provided') ?></dd>
                <dt>Coaches</dt>
                <dd>
                    <?= $value('assigned_coach_name', 'Not assigned') ?>
                    <?php if (! empty($a['personal_coach_name'])): ?>
                        / <?= esc((string) $a['personal_coach_name']) ?>
                    <?php endif; ?>
                </dd>
                <dt>Current period</dt>
                <dd>
                    <?php $linkedPeriod = is_array($a['current_period'] ?? null) ? $a['current_period'] : []; ?>
                    <?= esc((string) ($linkedPeriod['period_number'] ?? 'Not available')) ?>
                    <?php if (! empty($linkedPeriod['start_date']) || ! empty($linkedPeriod['end_date'])): ?>
                        (<?= esc(mcc_user_date($linkedPeriod['start_date'] ?? null, 'Not provided')) ?>
                        – <?= esc(mcc_user_date($linkedPeriod['end_date'] ?? null, 'Open')) ?>)
                    <?php endif; ?>
                </dd>
            </dl>
            <a class="mcc-button mcc-button-secondary" href="<?= site_url('admissions/' . (int) $a['id'] . '/page') ?>#status-heading">
                View conversion history/status
            </a>
        <?php endif; ?>
    </section>

    <section class="mcc-card mcc-detail-card mcc-follow-up-history" aria-labelledby="admission-follow-ups-heading">
        <div class="mcc-card-heading">
            <div>
                <span class="mcc-eyebrow">Admission activity</span>
                <h2 id="admission-follow-ups-heading">Follow-up History</h2>
            </div>
            <?php if (! empty($can_add_follow_up)): ?>
                <a class="mcc-button mcc-button-primary" href="<?= site_url('admissions/followups/new?admission_id=' . (int) $a['id']) ?>">Add Follow-up</a>
            <?php endif; ?>
        </div>
        <?php if ($followUps === []): ?>
            <p class="mcc-empty-state">No follow-ups are recorded for this Admission.</p>
        <?php else: ?>
            <div class="mcc-history-list">
                <?php foreach ($followUps as $followUp): ?>
                    <article class="mcc-history-item">
                        <div class="mcc-history-item-heading">
                            <strong><?= esc(ucwords(str_replace('_', ' ', (string) $followUp['follow_up_type']))) ?></strong>
                            <span class="mcc-badge"><?= esc(ucwords(str_replace('_', ' ', (string) $followUp['status']))) ?></span>
                        </div>
                        <p><?= nl2br(esc((string) $followUp['notes'])) ?></p>
                        <p class="mcc-history-meta">
                            <?= esc($formatDate($followUp['follow_up_at'])) ?>
                            · <?= esc((string) ($followUp['assigned_user_name'] ?: 'Unassigned')) ?>
                            <?php if (! empty($followUp['next_follow_up_at'])): ?>
                                · Next: <?= esc($formatDate($followUp['next_follow_up_at'])) ?>
                            <?php endif; ?>
                            · <a href="<?= site_url('admissions/followups/' . (int) $followUp['id']) ?>">View</a>
                        </p>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="mcc-card mcc-detail-card" aria-labelledby="actions-heading">
        <div class="mcc-card-heading">
            <span class="mcc-eyebrow">Available actions</span>
            <h2 id="actions-heading">Existing actions</h2>
        </div>
        <p class="mcc-muted">Admission workflows are available from the Actions menu on the admissions list.</p>
        <div class="mcc-detail-actions mcc-detail-actions-panel">
            <?php if (! empty($can_download)): ?>
                <a class="mcc-button mcc-button-secondary" href="<?= site_url('admissions/' . (int) $a['id'] . '/download') ?>">
                    Download PDF
                </a>
            <?php endif; ?>
            <?php if (! empty($a['can_renew'])): ?>
                <a class="mcc-button mcc-button-primary" href="<?= site_url('admissions/' . (int) $a['id'] . '/renew') ?>">Renew Admission</a>
            <?php endif; ?>
            <?php if (! empty($can_update_coaches)): ?>
                <a class="mcc-button mcc-button-secondary" href="<?= site_url('admissions/' . (int) $a['id'] . '/update-coaches') ?>">Update Coaches</a>
            <?php endif; ?>
            <a class="mcc-button mcc-button-secondary" href="<?= site_url('admissions') ?>">Back to Admissions</a>
        </div>
    </section>

    <?php $periods = is_array($a['periods'] ?? null) ? $a['periods'] : []; ?>
    <section class="mcc-card mcc-detail-card" aria-labelledby="admission-history-heading">
        <div class="mcc-card-heading">
            <span class="mcc-eyebrow">Renewal history</span>
            <h2 id="admission-history-heading">Admission History</h2>
        </div>
        <?php if ($periods === []): ?>
            <p class="mcc-empty-state mcc-detail-empty-state">No Admission periods are available.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table mcc-table">
                    <thead>
                        <tr>
                            <th>Period</th>
                            <th>Start date</th>
                            <th>End date</th>
                            <th>Age Group</th>
                            <th>Coaching Plan</th>
                            <th>Fee Plan</th>
                            <th>Hostel Plan</th>
                            <th>Academic Plan</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($periods as $period): ?>
                            <tr>
                                <td><?= (int) ($period['period_number'] ?? 0) ?></td>
                                <td><?= esc(mcc_user_date($period['start_date'] ?? null, '—')) ?></td>
                                <td><?= esc(mcc_user_date($period['end_date'] ?? null, '—')) ?></td>
                                <td><?= esc((string) ($period['age_group_label'] ?? '—')) ?></td>
                                <td><?= esc((string) ($period['coaching_plan_label'] ?? '—')) ?></td>
                                <td><?= esc((string) ($period['fee_plan_label'] ?? '—')) ?></td>
                                <td><?= esc((string) ($period['hostel_plan_label'] ?? '—')) ?></td>
                                <td><?= esc((string) ($period['academic_plan_label'] ?? '—')) ?></td>
                                <td><?= esc((string) ($period['status'] ?? '—')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="mcc-card mcc-detail-card" aria-labelledby="history-heading">
        <div class="mcc-card-heading">
            <span class="mcc-eyebrow">Timeline</span>
            <h2 id="history-heading">Status history</h2>
        </div>
        <?php if ($history === []): ?>
            <p class="mcc-empty-state mcc-detail-empty-state">No status history is available for this admission.</p>
        <?php else: ?>
            <ol class="mcc-history">
                <?php foreach ($history as $event): ?>
                    <li>
                        <strong><?= esc((string) ($event['new_status'] ?? 'Status updated')) ?></strong>
                        <?php if (! empty($event['previous_status'])): ?>
                            <span>from <?= esc((string) $event['previous_status']) ?></span>
                        <?php endif; ?>
                        <?php if (! empty($event['reason'])): ?>
                            <span><?= esc((string) $event['reason']) ?></span>
                        <?php endif; ?>
                        <?php if (! empty($event['actor_name'])): ?>
                            <span>by <?= esc((string) $event['actor_name']) ?></span>
                        <?php endif; ?>
                        <span class="mcc-history-meta"><?= $formatDate($event['created_at'] ?? null) ?></span>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
    </section>
</div>

<?php if (! $isFragment): ?>
    <?= view('staff_footer') ?>
<?php endif; ?>
