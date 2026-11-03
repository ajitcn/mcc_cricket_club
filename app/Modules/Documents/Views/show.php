<?php

$verificationStatus = strtoupper((string) ($document['verification_status'] ?? 'PENDING'));
$scannerStatus = strtoupper((string) ($document['scanner_status'] ?? 'QUARANTINED'));
$documentStatus = strtolower((string) ($document['status'] ?? 'active'));
$verificationClass = match ($verificationStatus) {
    'VERIFIED' => 'mcc-badge-success',
    'REJECTED' => 'mcc-badge-danger',
    default => 'mcc-badge-warning',
};
$scannerClass = match ($scannerStatus) {
    'CLEAN' => 'mcc-badge-success',
    'FAILED' => 'mcc-badge-danger',
    default => 'mcc-badge-warning',
};
$statusClass = match ($documentStatus) {
    'active' => 'mcc-badge-success',
    'expired' => 'mcc-badge-warning',
    'retired' => 'mcc-badge-neutral',
    default => 'mcc-badge-neutral',
};
$typeLabel = ucwords(str_replace('_', ' ', (string) ($document['document_type'] ?? 'Document')));
$history = is_array($document['history'] ?? null) ? $document['history'] : [];

$safeHistoryMetadata = static function (mixed $value): array {
    if (! is_string($value) || trim($value) === '') {
        return [];
    }

    try {
        $decoded = json_decode($value, true, 8, JSON_THROW_ON_ERROR);
    } catch (Throwable) {
        return [];
    }

    if (! is_array($decoded)) {
        return [];
    }

    $allowed = ['previous_version_id', 'new_version_id', 'scanner_status'];
    $safe = [];
    foreach ($allowed as $key) {
        if (array_key_exists($key, $decoded) && ! is_array($decoded[$key]) && ! is_object($decoded[$key])) {
            $safe[$key] = (string) $decoded[$key];
        }
    }

    return $safe;
};

$historyLabel = static function (string $key): string {
    return match ($key) {
        'previous_version_id' => 'Previous version',
        'new_version_id' => 'New version',
        'scanner_status' => 'Scanner status',
        default => ucwords(str_replace('_', ' ', $key)),
    };
};
?>
<?= view('staff_header', [
    'title' => 'Document detail',
    'pageActions' => '<a class="mcc-button mcc-button-secondary" href="' . site_url('documents') . '">Back to documents</a>',
]) ?>

<section class="mcc-card mcc-document-summary-card" aria-labelledby="document-summary-heading">
    <div class="mcc-card-heading mcc-detail-heading-with-status">
        <div>
            <p class="mcc-eyebrow">Secure document record</p>
            <h2 id="document-summary-heading"><?= esc($typeLabel) ?></h2>
            <p class="mcc-muted">Related to <?= esc((string) ($document['full_name'] ?? 'Authorized record')) ?></p>
        </div>
        <div class="mcc-badge-group">
            <span class="mcc-badge mcc-status-badge <?= esc($statusClass) ?>"><?= esc(ucfirst($documentStatus)) ?></span>
            <span class="mcc-badge mcc-status-badge <?= esc($verificationClass) ?>"><?= esc(ucwords(strtolower($verificationStatus))) ?></span>
            <span class="mcc-badge mcc-status-badge <?= esc($scannerClass) ?>"><?= esc(ucwords(strtolower($scannerStatus))) ?></span>
        </div>
    </div>
    <dl class="mcc-detail-grid">
        <div><dt>Document type</dt><dd><?= esc($typeLabel) ?></dd></div>
        <div><dt>Related record</dt><dd><?= esc((string) ($document['full_name'] ?? 'Authorized record')) ?></dd></div>
        <div><dt>Branch</dt><dd><?= esc((string) ($document['branch_name'] ?? 'Authorized branch')) ?></dd></div>
        <div><dt>Version</dt><dd>v<?= (int) ($document['version_number'] ?? 1) ?></dd></div>
        <div><dt>Expiry</dt><dd><?= esc(mcc_user_date($document['expires_on'] ?? null, 'No expiry')) ?></dd></div>
        <div><dt>Uploaded</dt><dd><?= esc(mcc_user_datetime($document['created_at'] ?? null)) ?></dd></div>
    </dl>
    <div class="mcc-detail-actions-panel">
        <a class="mcc-button mcc-button-secondary" href="<?= site_url('documents/' . (int) $document['id'] . '/preview') ?>">Preview</a>
        <a class="mcc-button mcc-button-secondary" href="<?= site_url('documents/' . (int) $document['id'] . '/download') ?>">Secure download</a>
    </div>
</section>

<section class="mcc-card mcc-document-verification-card" aria-labelledby="verification-heading">
    <div class="mcc-card-heading">
        <div>
            <p class="mcc-eyebrow">Review state</p>
            <h2 id="verification-heading">Verification and scanner state</h2>
        </div>
    </div>
    <dl class="mcc-detail-grid">
        <div><dt>Verification</dt><dd><span class="mcc-badge mcc-status-badge <?= esc($verificationClass) ?>"><?= esc(ucwords(strtolower($verificationStatus))) ?></span></dd></div>
        <div><dt>Scanner</dt><dd><span class="mcc-badge mcc-status-badge <?= esc($scannerClass) ?>"><?= esc(ucwords(strtolower($scannerStatus))) ?></span></dd></div>
        <div><dt>Scanner note</dt><dd><?= esc((string) ($document['scanner_reason'] ?? 'No scanner note available.')) ?></dd></div>
        <div><dt>Verification reason</dt><dd><?= esc((string) ($document['verification_reason'] ?? 'No verification reason recorded.')) ?></dd></div>
    </dl>
    <?php if (! empty($can_verify)): ?>
        <form class="mcc-form-grid mcc-document-verification-form" method="post" action="<?= site_url('documents/' . (int) $document['id'] . '/verify') ?>">
            <?= csrf_field() ?>
            <div class="mcc-form-group">
                <label for="verification-status">Verification state</label>
                <select class="form-control" id="verification-status" name="verification_status" required>
                    <?php foreach (['PENDING', 'VERIFIED', 'REJECTED'] as $state): ?>
                        <option value="<?= esc($state) ?>" <?= $verificationStatus === $state ? 'selected' : '' ?>><?= esc(ucwords(strtolower($state))) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mcc-form-group">
                <label for="verification-reason">Reason</label>
                <textarea class="form-control" id="verification-reason" name="verification_reason" rows="3"><?= esc((string) old('verification_reason', '')) ?></textarea>
            </div>
            <div class="mcc-form-actions">
                <button class="mcc-button mcc-button-primary" type="submit">Save verification</button>
            </div>
        </form>
    <?php endif; ?>
</section>

<section class="mcc-card mcc-document-history-card" aria-labelledby="history-heading">
    <div class="mcc-card-heading">
        <div>
            <p class="mcc-eyebrow">Append-only record</p>
            <h2 id="history-heading">Version history and audit</h2>
        </div>
    </div>
    <?php if ($history === []): ?>
        <p class="mcc-empty-state">No previous versions or audit events are available.</p>
    <?php else: ?>
        <div class="mcc-document-history-list">
            <?php foreach ($history as $event): ?>
                <?php $metadata = $safeHistoryMetadata($event['metadata'] ?? null); ?>
                <article class="mcc-document-history-event">
                    <dl class="mcc-detail-grid">
                        <div><dt>Event</dt><dd><?= esc(ucwords(str_replace('_', ' ', (string) ($event['action'] ?? 'Event')))) ?></dd></div>
                        <div><dt>Actor</dt><dd>User #<?= (int) ($event['actor_user_id'] ?? 0) ?></dd></div>
                        <div><dt>Timestamp</dt><dd><?= esc(mcc_user_datetime($event['created_at'] ?? null)) ?></dd></div>
                        <div><dt>Reason</dt><dd><?= esc((string) ($event['reason'] ?? 'No reason recorded.')) ?></dd></div>
                    </dl>
                    <?php if ($metadata !== []): ?>
                        <div class="mcc-document-history-values">
                            <strong>Recorded values</strong>
                            <?php foreach ($metadata as $key => $value): ?>
                                <div><span><?= esc($historyLabel($key)) ?></span><strong><?= esc($value) ?></strong></div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="mcc-muted">No additional values recorded.</p>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?= view('staff_footer') ?>
