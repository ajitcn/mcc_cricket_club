<?php

$followUp = is_array($followUp ?? null) ? $followUp : [];
$followUpHistory = is_array($followUpHistory ?? null) ? $followUpHistory : [];
$auditHistory = is_array($auditHistory ?? null) ? $auditHistory : [];
$canView = (bool) ($canView ?? false);
$canUpdate = (bool) ($canUpdate ?? false);
$canCreate = (bool) ($canCreate ?? false);
$canCreateAnother = $canCreate && ! in_array(
    strtoupper(trim((string) ($followUp['admission_status'] ?? ''))),
    ['APPROVED', 'COMPLETED', 'CONVERTED'],
    true,
) && ($followUp['converted_player_id'] ?? null) === null;

$displayDate = static function ($value): string {
    return mcc_follow_up_datetime(
        $value === null ? null : (string) $value,
        'Not available',
    );
};

$humanize = static function ($value, string $fallback = 'Not available'): string {
    $value = trim((string) $value);

    return $value === '' ? $fallback : ucwords(str_replace(['_', '-'], ' ', $value));
};

$nextFollowUp = trim((string) ($followUp['next_follow_up_at'] ?? ''));
$statusPresentation = mcc_follow_up_status_presentation(
    (string) ($followUp['status'] ?? ''),
    $nextFollowUp,
);
$statusLabel = $statusPresentation['label'];
$statusClass = $statusPresentation['class'];
$isOverdue = $statusPresentation['overdue'];

$admissionNumber = trim((string) ($followUp['admission_number'] ?? ''));
$applicantName = trim((string) ($followUp['applicant_name'] ?? ''));
$branchName = trim((string) ($followUp['branch_name'] ?? ''));
$admissionStatus = $humanize($followUp['admission_status'] ?? null);
$typeLabel = $humanize($followUp['follow_up_type'] ?? null);
$admissionListUrl = (string) ($admissionListUrl ?? site_url('admissions'));

$pageActions = '<div class="mcc-page-actions">';

if ($canUpdate) {
    $pageActions .= '<a class="mcc-button mcc-button-primary" href="'
        . site_url('admissions/followups/' . (int) ($followUp['id'] ?? 0) . '/edit')
        . '">Edit Follow-up</a>';
}

if ($canView) {
    $pageActions .= '<a class="mcc-button mcc-button-secondary" href="'
        . esc($admissionListUrl)
        . '">View Admission</a>';
}

if ($canCreateAnother) {
    $pageActions .= '<a class="mcc-button mcc-button-secondary" href="'
        . site_url('admissions/followups/new?admission_id=' . (int) ($followUp['admission_id'] ?? 0))
        . '">Add Another Follow-up</a>';
}

$pageActions .= '<a class="mcc-button mcc-button-secondary" href="'
    . site_url('admissions/followups')
    . '">Back to Follow-ups</a></div>';
?>
<?= view('staff_header', [
    'title' => 'Follow-up Details',
    'nav' => $nav ?? [],
    'message' => $message ?? null,
    'error' => $error ?? null,
    'pageActions' => $pageActions,
]) ?>

<section class="mcc-card mcc-detail-hero" aria-labelledby="follow-up-details-heading">
    <div>
        <span class="mcc-eyebrow">Admissions</span>
        <h2 id="follow-up-details-heading">Follow-up Details</h2>
        <p class="mcc-muted">
            Follow-up #<?= (int) ($followUp['id'] ?? 0) ?>
        </p>
    </div>
    <span class="mcc-badge mcc-status-badge <?= esc($statusClass) ?>">
        <?= esc($statusLabel) ?>
    </span>
</section>

<div class="mcc-detail-grid mcc-follow-up-detail-grid">
    <section class="mcc-card mcc-detail-card" aria-labelledby="admission-information-heading">
        <div class="mcc-card-heading">
            <div>
                <span class="mcc-eyebrow">Admission</span>
                <h2 id="admission-information-heading">Admission information</h2>
            </div>
        </div>
        <dl class="mcc-detail-list">
            <dt>Applicant</dt>
            <dd class="mcc-follow-up-applicant-name">
                <?= esc($applicantName !== '' ? $applicantName : 'Applicant unavailable') ?>
            </dd>

            <dt>Admission number</dt>
            <dd>#<?= esc($admissionNumber !== '' ? $admissionNumber : 'Pending number') ?></dd>

            <dt>Branch</dt>
            <dd><?= esc($branchName !== '' ? $branchName : 'Branch unavailable') ?></dd>

            <dt>Admission status</dt>
            <dd><?= esc($admissionStatus) ?></dd>

            <?php if ($canView): ?>
                <dt>Applicant email</dt>
                <dd>
                    <?php $email = trim((string) ($followUp['applicant_email'] ?? '')); ?>
                    <?= esc($email !== '' ? $email : 'Not provided') ?>
                </dd>

                <dt>Applicant phone</dt>
                <dd>
                    <?php $phone = trim((string) ($followUp['applicant_phone'] ?? '')); ?>
                    <?= esc($phone !== '' ? $phone : 'Not provided') ?>
                </dd>
            <?php endif; ?>
        </dl>
    </section>

    <section class="mcc-card mcc-detail-card" aria-labelledby="follow-up-information-heading">
        <div class="mcc-card-heading">
            <div>
                <span class="mcc-eyebrow">Follow-up</span>
                <h2 id="follow-up-information-heading">Follow-up information</h2>
            </div>
        </div>
        <dl class="mcc-detail-list">
            <dt>Follow-up date and time</dt>
            <dd><?= esc($displayDate($followUp['follow_up_at'] ?? null)) ?></dd>

            <dt>Follow-up type</dt>
            <dd><?= esc($typeLabel) ?></dd>

            <dt>Status</dt>
            <dd><?= esc($statusLabel) ?></dd>

            <dt>Assigned user</dt>
            <dd><?= esc(trim((string) ($followUp['assigned_user_name'] ?? '')) ?: 'Unassigned') ?></dd>

            <dt>Created</dt>
            <dd><?= esc($displayDate($followUp['created_at'] ?? null)) ?></dd>

            <dt>Last updated</dt>
            <dd><?= esc($displayDate($followUp['updated_at'] ?? null)) ?></dd>

            <dt>Next follow-up</dt>
            <dd><?= esc($nextFollowUp === '' ? 'Not scheduled' : $displayDate($nextFollowUp)) ?></dd>
        </dl>

        <?php if ($isOverdue): ?>
            <div class="mcc-alert mcc-alert-warning" role="status">
                This follow-up is overdue.
            </div>
        <?php endif; ?>
    </section>
</div>

<div class="mcc-detail-grid mcc-follow-up-detail-grid">
    <section class="mcc-card mcc-detail-card" aria-labelledby="follow-up-notes-heading">
        <div class="mcc-card-heading">
            <div>
                <span class="mcc-eyebrow">Conversation</span>
                <h2 id="follow-up-notes-heading">Notes</h2>
            </div>
        </div>
        <?php if (trim((string) ($followUp['notes'] ?? '')) === ''): ?>
            <p class="mcc-muted">No notes were recorded.</p>
        <?php else: ?>
            <p class="mcc-follow-up-detail-text">
                <?= nl2br(esc((string) $followUp['notes'])) ?>
            </p>
        <?php endif; ?>
    </section>

    <section class="mcc-card mcc-detail-card" aria-labelledby="follow-up-outcome-heading">
        <div class="mcc-card-heading">
            <div>
                <span class="mcc-eyebrow">Result</span>
                <h2 id="follow-up-outcome-heading">Outcome</h2>
            </div>
        </div>
        <?php if (trim((string) ($followUp['outcome'] ?? '')) === ''): ?>
            <p class="mcc-muted">No outcome was recorded.</p>
        <?php else: ?>
            <p class="mcc-follow-up-detail-text">
                <?= nl2br(esc((string) $followUp['outcome'])) ?>
            </p>
        <?php endif; ?>
    </section>
</div>

<section class="mcc-card mcc-detail-card" aria-labelledby="follow-up-record-history-heading">
    <div class="mcc-card-heading">
        <div>
            <span class="mcc-eyebrow">Admission</span>
            <h2 id="follow-up-record-history-heading">Follow-up History</h2>
        </div>
    </div>

    <?php if ($followUpHistory === []): ?>
        <p class="mcc-muted">No follow-up history is available.</p>
    <?php else: ?>
        <ol class="mcc-history">
            <?php foreach ($followUpHistory as $historyItem): ?>
                <?php
                $historyStatus = mcc_follow_up_status_presentation(
                    (string) ($historyItem['status'] ?? ''),
                    (string) ($historyItem['next_follow_up_at'] ?? ''),
                );
                ?>
                <li>
                    <strong>
                        <a href="<?= site_url('admissions/followups/' . (int) ($historyItem['id'] ?? 0)) ?>">
                            <?= esc($displayDate($historyItem['follow_up_at'] ?? null)) ?>
                        </a>
                    </strong>
                    <span><?= esc(ucwords(str_replace('_', ' ', (string) ($historyItem['follow_up_type'] ?? '')))) ?></span>
                    <span class="mcc-badge mcc-status-badge <?= esc($historyStatus['class']) ?>">
                        <?= esc($historyStatus['label']) ?>
                    </span>
                    <?php if (trim((string) ($historyItem['notes'] ?? '')) !== ''): ?>
                        <p><?= nl2br(esc((string) $historyItem['notes'])) ?></p>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ol>
    <?php endif; ?>
</section>

<section class="mcc-card mcc-detail-card" aria-labelledby="follow-up-audit-history-heading">
    <div class="mcc-card-heading">
        <div>
            <span class="mcc-eyebrow">Audit</span>
            <h2 id="follow-up-audit-history-heading">Audit history</h2>
        </div>
    </div>

    <?php if ($auditHistory === []): ?>
        <p class="mcc-muted">No additional history is available.</p>
    <?php else: ?>
        <ol class="mcc-history">
            <?php foreach ($auditHistory as $event): ?>
                <li>
                    <strong><?= esc(trim((string) ($event['actor_name'] ?? '')) ?: 'System') ?></strong>
                    <span><?= esc(ucwords(str_replace('_', ' ', (string) ($event['action'] ?? 'Updated')))) ?></span>
                    <time datetime="<?= esc((string) ($event['created_at'] ?? '')) ?>">
                        <?= esc($displayDate($event['created_at'] ?? null)) ?>
                    </time>
                    <?php if (trim((string) ($event['reason'] ?? '')) !== ''): ?>
                        <p><?= esc((string) $event['reason']) ?></p>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ol>
    <?php endif; ?>
</section>
