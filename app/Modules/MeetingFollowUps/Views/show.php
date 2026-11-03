<?php

$followUp = is_array($followUp ?? null) ? $followUp : [];
$followUpId = (int) ($followUp['id'] ?? 0);
$admissionTestId = (int) ($followUp['admission_test_id'] ?? 0);
$trialId = (int) ($followUp['trial_id'] ?? 0);
$admissionId = (int) ($followUp['admission_id'] ?? 0);
$testReference = 'Test #' . $admissionTestId;
$trialReference = 'Trial #' . $trialId;
$admissionNumber = trim((string) ($followUp['admission_number'] ?? ''));
$applicantName = trim((string) ($followUp['applicant_full_name'] ?? ''));
$applicantName = $applicantName === '' ? 'Applicant unavailable' : $applicantName;
$statusPresentation = mcc_follow_up_status_presentation((string) ($followUp['status'] ?? ''));
$testStatusPresentation = mcc_follow_up_status_presentation((string) ($followUp['admission_test_status'] ?? ''));
$startPresentation = mcc_follow_up_datetime($followUp['start_at'] ?? null, 'Not available');
$startParts = explode(', ', $startPresentation, 2);
$startDate = $startParts[0] ?? 'Not available';
$startTime = $startParts[1] ?? 'Not available';
$humanize = static function ($value, string $fallback = 'Not available'): string {
    $value = trim((string) $value);

    return $value === '' ? $fallback : ucwords(str_replace(['_', '-'], ' ', $value));
};
$displayValue = static function ($value, string $fallback = 'Not available'): string {
    $value = trim((string) $value);

    return $value === '' ? $fallback : $value;
};
$createdBy = trim((string) ($followUp['created_by_name'] ?? ''));
if ($createdBy === '') {
    $createdBy = 'Authorized staff';
}

$pageActions = '<div class="mcc-page-actions mcc-follow-up-detail-actions" data-follow-up-actions>
    <button class="mcc-button mcc-button-secondary" type="button" data-follow-up-actions-toggle
        aria-controls="meeting-follow-up-detail-actions" aria-expanded="false" aria-haspopup="true">
        <i class="fa-solid fa-ellipsis-vertical" aria-hidden="true"></i>
        <span class="mcc-button-label">Actions</span>
    </button>
    <div class="mcc-follow-up-action-dropdown" id="meeting-follow-up-detail-actions"
        data-follow-up-actions-menu role="menu" hidden>';

if ((bool) ($canUpdate ?? false)) {
    $pageActions .= '<a role="menuitem" href="' . site_url('meeting-followups/' . $followUpId . '/edit') . '">
        <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i> Edit
    </a>';
}

$pageActions .= '<a role="menuitem" href="' . site_url('crm/trials/' . $trialId) . '">
    <i class="fa-solid fa-baseball-bat-ball" aria-hidden="true"></i> View Trial
</a>';

if ($admissionId > 0 && $admissionNumber !== '') {
    $pageActions .= '<a role="menuitem" href="' . site_url('admissions/' . $admissionId) . '">
        <i class="fa-solid fa-file-signature" aria-hidden="true"></i> View Admission
    </a>';
}

$pageActions .= '<a role="menuitem" href="' . site_url('meeting-followups') . '">
    <i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back to Meeting Follow-ups
</a></div></div>';
?>
<?= view('staff_header', [
    'title' => 'Meeting Follow-up Details',
    'nav' => $nav ?? [],
    'message' => $message ?? null,
    'error' => $error ?? null,
    'pageActions' => $pageActions,
    'breadcrumbTrail' => [
        ['label' => 'CRM', 'url' => site_url('crm')],
        ['label' => 'Admission Test', 'url' => site_url('admission-tests')],
        ['label' => 'Meeting Follow-up', 'url' => site_url('meeting-followups')],
        ['label' => 'Details'],
    ],
]) ?>

<section class="mcc-card mcc-detail-hero mcc-meeting-follow-up-detail-hero" aria-labelledby="meeting-follow-up-details-heading">
    <div>
        <span class="mcc-eyebrow">Meeting Follow-up</span>
        <h2 id="meeting-follow-up-details-heading"><?= esc($applicantName) ?></h2>
        <p class="mcc-muted">Linked record: <?= esc($testReference) ?> · <?= esc($trialReference) ?></p>
    </div>
    <span class="mcc-badge mcc-status-badge <?= esc($statusPresentation['class']) ?>">
        <?= esc($statusPresentation['label']) ?>
    </span>
</section>

<div class="mcc-detail-grid mcc-meeting-follow-up-detail-grid">
    <section class="mcc-card mcc-detail-card" aria-labelledby="meeting-follow-up-lineage-heading">
        <div class="mcc-card-heading">
            <div>
                <span class="mcc-eyebrow">Lineage</span>
                <h2 id="meeting-follow-up-lineage-heading">Admission Test information</h2>
            </div>
        </div>
        <dl class="mcc-detail-list">
            <dt>Test reference</dt>
            <dd><a href="<?= site_url('admission-tests/' . $admissionTestId) ?>"><?= esc($testReference) ?></a></dd>
            <dt>Applicant name</dt>
            <dd class="mcc-meeting-follow-up-applicant-name"><?= esc($applicantName) ?></dd>
            <dt>Trial reference</dt>
            <dd><a href="<?= site_url('crm/trials/' . $trialId) ?>"><?= esc($trialReference) ?></a></dd>
            <dt>Branch</dt>
            <dd><?= esc($displayValue($followUp['branch_name'] ?? null, 'Branch unavailable')) ?></dd>
            <dt>Test status</dt>
            <dd><span class="mcc-badge mcc-status-badge <?= esc($testStatusPresentation['class']) ?>"><?= esc($testStatusPresentation['label']) ?></span></dd>
            <dt>Admission number</dt>
            <dd><?= esc($admissionNumber !== '' ? $admissionNumber : 'Not converted') ?></dd>
        </dl>
    </section>

    <section class="mcc-card mcc-detail-card" aria-labelledby="meeting-follow-up-information-heading">
        <div class="mcc-card-heading">
            <div>
                <span class="mcc-eyebrow">Follow-up</span>
                <h2 id="meeting-follow-up-information-heading">Follow-up information</h2>
            </div>
        </div>
        <dl class="mcc-detail-list">
            <dt>Follow-up type</dt>
            <dd><?= esc($humanize($followUp['follow_up_type'] ?? null)) ?></dd>
            <dt>Status</dt>
            <dd><span class="mcc-badge mcc-status-badge <?= esc($statusPresentation['class']) ?>"><?= esc($statusPresentation['label']) ?></span></dd>
            <dt>Follow-up Start Date</dt>
            <dd><?= esc($startDate) ?></dd>
            <dt>Start Time</dt>
            <dd><?= esc($startTime) ?></dd>
            <dt>Assigned user</dt>
            <dd><?= esc($displayValue($followUp['assigned_user_name'] ?? null, 'Unassigned')) ?></dd>
            <dt>Notes</dt>
            <dd><?= esc($displayValue($followUp['notes'] ?? null, 'No notes recorded')) ?></dd>
            <dt>Next action</dt>
            <dd><?= esc($displayValue($followUp['next_action'] ?? null, 'No next action recorded')) ?></dd>
            <dt>Created by</dt>
            <dd><?= esc($createdBy) ?></dd>
            <dt>Created date</dt>
            <dd><?= esc(mcc_follow_up_datetime($followUp['created_at'] ?? null)) ?></dd>
            <dt>Last updated date</dt>
            <dd><?= esc(mcc_follow_up_datetime($followUp['updated_at'] ?? null)) ?></dd>
        </dl>
    </section>
</div>
<?= view('staff_footer') ?>
