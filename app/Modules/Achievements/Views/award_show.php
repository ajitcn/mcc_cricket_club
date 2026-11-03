<?php

$award = is_array($award ?? null) ? $award : [];
$status = strtolower((string) ($award['status'] ?? 'unknown'));
$isArchived = (int) ($award['is_archived'] ?? 0) === 1;
$branchId = (string) ($award['branch_id'] ?? '');
$statusTone = match ($status) {
    'published' => 'success',
    'revoked' => 'danger',
    'approved' => 'info',
    default => 'neutral',
};
$recipientName = $award['recipient_type'] === 'player'
    ? ($award['player_name'] ?? 'Unavailable')
    : ($award['coach_name'] ?? 'Unavailable');
$authorization = service('authorization');
$canUpdate = $authorization->can('achievement.update', $branchId);
$canApprove = $authorization->can('achievement.approve', $branchId);
$canPublish = $authorization->can('achievement.publish', $branchId);
$canRevoke = $authorization->can('achievement.revoke', $branchId);
$canArchive = $authorization->can('achievement.archive', $branchId);
$canRestore = $authorization->can('achievement.restore', $branchId);
$canGenerateCertificate = $authorization->can('achievement.certificate.generate', $branchId);
$canDownloadCertificate = $authorization->can('achievement.certificate.download', $branchId);
$awardUrl = 'achievements/awards/' . (int) ($award['id'] ?? 0);
?>
<?= view('staff_header', ['title' => $title]) ?>
<main class="container py-4 mcc-achievements-page">
    <div class="mcc-page-heading">
        <div>
            <span class="mcc-eyebrow">Achievement award</span>
            <h1><?= esc($award['achievement_title'] ?? 'Achievement award') ?></h1>
        </div>
        <div class="mcc-page-actions">
            <a class="mcc-button mcc-button-secondary" href="<?= site_url('achievements/awards') ?>">Back to awards</a>
            <?php if ($canUpdate && $status === 'draft' && ! $isArchived): ?>
                <a class="mcc-button mcc-button-primary" href="<?= site_url($awardUrl . '/edit') ?>">Edit draft</a>
            <?php endif; ?>
        </div>
    </div>

    <?php if (! empty($error)): ?>
        <div class="mcc-alert mcc-alert-danger" role="alert"><?= esc($error) ?></div>
    <?php endif; ?>
    <?php if (! empty($message)): ?>
        <div class="mcc-alert mcc-alert-success" role="status"><?= esc($message) ?></div>
    <?php endif; ?>

    <section class="mcc-card" aria-labelledby="award-heading">
        <div class="mcc-card-heading">
            <div>
                <span class="mcc-eyebrow">Award record</span>
                <h2 id="award-heading">Award details</h2>
            </div>
            <div class="mcc-detail-badges" aria-label="Award status">
                <span class="mcc-badge mcc-badge-<?= esc($statusTone) ?>"><?= esc(ucfirst($status)) ?></span>
                <?php if ($isArchived): ?>
                    <span class="mcc-badge mcc-badge-warning">Archived</span>
                <?php endif; ?>
            </div>
        </div>
        <dl class="mcc-detail-list">
            <dt>Achievement</dt>
            <dd><?= esc($award['achievement_title'] ?? 'Unavailable') ?></dd>
            <dt>Recipient</dt>
            <dd><?= esc($recipientName) ?></dd>
            <dt>Recipient type</dt>
            <dd><?= esc(ucfirst((string) ($award['recipient_type'] ?? 'Unavailable'))) ?></dd>
            <dt>Branch</dt>
            <dd><?= esc($award['branch_name'] ?? 'Unavailable') ?></dd>
            <dt>Nomination note</dt>
            <dd><?= esc($award['nomination_note'] ?? 'Not provided') ?></dd>
            <dt>Revision</dt>
            <dd><?= (int) ($award['version'] ?? 1) ?></dd>
        </dl>
    </section>

    <?php if ($status === 'draft' && ! $isArchived && $canApprove): ?>
        <section class="mcc-card" aria-labelledby="approve-heading">
            <div class="mcc-card-heading"><div><span class="mcc-eyebrow">Workflow</span><h2 id="approve-heading">Approve award</h2></div></div>
            <form method="post" action="<?= site_url($awardUrl . '/approve') ?>" class="mcc-form-grid">
                <?= csrf_field() ?>
                <input type="hidden" name="version" value="<?= (int) ($award['version'] ?? 1) ?>">
                <div class="mcc-form-field mcc-form-field-wide"><label for="approve-reason">Approval reason</label><textarea class="form-control" id="approve-reason" name="reason" required maxlength="1000" rows="3"></textarea></div>
                <div class="mcc-form-actions mcc-form-field-wide"><button class="mcc-button mcc-button-primary" type="submit">Approve</button></div>
            </form>
        </section>
    <?php elseif ($status === 'approved' && ! $isArchived && $canPublish): ?>
        <section class="mcc-card" aria-labelledby="publish-heading">
            <div class="mcc-card-heading"><div><span class="mcc-eyebrow">Workflow</span><h2 id="publish-heading">Publish award</h2></div></div>
            <form method="post" action="<?= site_url($awardUrl . '/publish') ?>" class="mcc-form-grid">
                <?= csrf_field() ?>
                <input type="hidden" name="version" value="<?= (int) ($award['version'] ?? 1) ?>">
                <div class="mcc-form-field mcc-form-field-wide"><label for="publish-reason">Publication reason</label><textarea class="form-control" id="publish-reason" name="reason" required maxlength="1000" rows="3"></textarea></div>
                <div class="mcc-form-actions mcc-form-field-wide"><button class="mcc-button mcc-button-primary" type="submit">Publish</button></div>
            </form>
        </section>
    <?php elseif ($status === 'published' && ! $isArchived): ?>
        <section class="mcc-card" aria-labelledby="published-actions-heading">
            <div class="mcc-card-heading"><div><span class="mcc-eyebrow">Published award</span><h2 id="published-actions-heading">Certificate and lifecycle actions</h2></div></div>
            <div class="mcc-form-actions">
                <?php if ($canDownloadCertificate && ! empty($award['certificate_id'])): ?>
                    <a class="mcc-button mcc-button-secondary" href="<?= site_url($awardUrl . '/certificate/download') ?>">Download certificate</a>
                <?php elseif ($canGenerateCertificate): ?>
                    <form method="post" action="<?= site_url($awardUrl . '/certificate') ?>">
                        <?= csrf_field() ?>
                        <button class="mcc-button mcc-button-secondary" type="submit">Generate certificate</button>
                    </form>
                <?php endif; ?>
            </div>
            <?php if ($canRevoke): ?>
                <form method="post" action="<?= site_url($awardUrl . '/revoke') ?>" class="mcc-form-grid">
                    <?= csrf_field() ?>
                    <input type="hidden" name="version" value="<?= (int) ($award['version'] ?? 1) ?>">
                    <div class="mcc-form-field mcc-form-field-wide"><label for="revoke-reason">Revocation reason</label><textarea class="form-control" id="revoke-reason" name="reason" required maxlength="1000" rows="3"></textarea></div>
                    <div class="mcc-form-actions mcc-form-field-wide"><button class="mcc-button mcc-button-secondary" type="submit">Revoke</button></div>
                </form>
            <?php endif; ?>
            <?php if ($canUpdate): ?>
                <form method="post" action="<?= site_url($awardUrl . '/correct') ?>" class="mcc-form-grid">
                    <?= csrf_field() ?>
                    <input type="hidden" name="version" value="<?= (int) ($award['version'] ?? 1) ?>">
                    <div class="mcc-form-field mcc-form-field-wide"><label for="correction-note">Corrected nomination note</label><textarea class="form-control" id="correction-note" name="nomination_note" maxlength="2000" rows="3"><?= esc($award['nomination_note'] ?? '') ?></textarea></div>
                    <div class="mcc-form-field mcc-form-field-wide"><label for="published-correction-reason">Correction reason</label><textarea class="form-control" id="published-correction-reason" name="correction_reason" required maxlength="1000" rows="3"></textarea></div>
                    <div class="mcc-form-actions mcc-form-field-wide"><button class="mcc-button mcc-button-primary" type="submit">Save correction</button></div>
                </form>
            <?php endif; ?>
            <?php if ($canArchive): ?>
                <form method="post" action="<?= site_url($awardUrl . '/archive') ?>" class="mcc-form-grid">
                    <?= csrf_field() ?>
                    <input type="hidden" name="version" value="<?= (int) ($award['version'] ?? 1) ?>">
                    <div class="mcc-form-field mcc-form-field-wide"><label for="published-archive-reason">Archive reason</label><textarea class="form-control" id="published-archive-reason" name="reason" required maxlength="1000" rows="3"></textarea></div>
                    <div class="mcc-form-actions mcc-form-field-wide"><button class="mcc-button mcc-button-secondary" type="submit">Archive</button></div>
                </form>
            <?php endif; ?>
        </section>
    <?php elseif ($status === 'revoked' && ! $isArchived && $canArchive): ?>
        <section class="mcc-card" aria-labelledby="revoked-actions-heading">
            <div class="mcc-card-heading"><div><span class="mcc-eyebrow">Revoked award</span><h2 id="revoked-actions-heading">Archive award</h2></div></div>
            <form method="post" action="<?= site_url($awardUrl . '/archive') ?>" class="mcc-form-grid">
                <?= csrf_field() ?>
                <input type="hidden" name="version" value="<?= (int) ($award['version'] ?? 1) ?>">
                <div class="mcc-form-field mcc-form-field-wide"><label for="revoked-archive-reason">Archive reason</label><textarea class="form-control" id="revoked-archive-reason" name="reason" required maxlength="1000" rows="3"></textarea></div>
                <div class="mcc-form-actions mcc-form-field-wide"><button class="mcc-button mcc-button-secondary" type="submit">Archive</button></div>
            </form>
        </section>
    <?php elseif ($isArchived && $canRestore): ?>
        <section class="mcc-card" aria-labelledby="restore-heading">
            <div class="mcc-card-heading"><div><span class="mcc-eyebrow">Archived award</span><h2 id="restore-heading">Restore award</h2></div></div>
            <form method="post" action="<?= site_url($awardUrl . '/restore') ?>" class="mcc-form-grid">
                <?= csrf_field() ?>
                <input type="hidden" name="version" value="<?= (int) ($award['version'] ?? 1) ?>">
                <div class="mcc-form-field mcc-form-field-wide"><label for="restore-reason">Restore reason</label><textarea class="form-control" id="restore-reason" name="reason" required maxlength="1000" rows="3"></textarea></div>
                <div class="mcc-form-actions mcc-form-field-wide"><button class="mcc-button mcc-button-primary" type="submit">Restore</button></div>
            </form>
        </section>
    <?php endif; ?>
</main>
<?= view('staff_footer') ?>
