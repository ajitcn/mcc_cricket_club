<?php

$result = $result ?? ['documents' => [], 'filters' => []];
$documents = $result['documents'] ?? [];
$filters = $result['filters'] ?? [];
$hasDocumentFilters = trim((string) ($filters['q'] ?? '')) !== ''
    || trim((string) ($filters['document_type'] ?? '')) !== ''
    || trim((string) ($filters['status'] ?? '')) !== '';

$statusMeta = static function (string $status): array {
    return match (strtolower($status)) {
        'active' => ['label' => 'Active', 'class' => 'mcc-badge-success'],
        'expired' => ['label' => 'Expired', 'class' => 'mcc-badge-warning'],
        'retired' => ['label' => 'Retired', 'class' => 'mcc-badge-neutral'],
        default => ['label' => ucwords(str_replace('_', ' ', strtolower($status))), 'class' => 'mcc-badge-neutral'],
    };
};

$verificationMeta = static function (string $status): array {
    return match (strtoupper($status)) {
        'VERIFIED' => ['label' => 'Verified', 'class' => 'mcc-badge-success'],
        'REJECTED' => ['label' => 'Rejected', 'class' => 'mcc-badge-danger'],
        default => ['label' => 'Pending', 'class' => 'mcc-badge-warning'],
    };
};

$scannerMeta = static function (string $status): array {
    return match (strtoupper($status)) {
        'CLEAN' => ['label' => 'Clean', 'class' => 'mcc-badge-success'],
        'FAILED' => ['label' => 'Failed', 'class' => 'mcc-badge-danger'],
        default => ['label' => 'Quarantined', 'class' => 'mcc-badge-warning'],
    };
};
?>
<?= view('staff_header', [
    'title' => 'Documents',
    'pageActions' => '<div class="mcc-page-actions">
        <button class="mcc-button mcc-button-secondary" type="button" data-filter-toggle
            data-show-label="Show filters" data-hide-label="Hide filters"
            aria-controls="document-filters" aria-expanded="' . ($hasDocumentFilters ? 'true' : 'false') . '">
            <i class="fa-solid fa-filter" aria-hidden="true"></i>
            <span class="mcc-button-label">' . ($hasDocumentFilters ? 'Hide filters' : 'Show filters') . '</span>
        </button>
    </div>',
]) ?>

<section id="document-filters" class="mcc-card mcc-document-filter-card" aria-labelledby="document-filter-heading" <?= $hasDocumentFilters ? '' : 'hidden' ?>>
    <div class="mcc-card-heading">
        <div>
            <p class="mcc-eyebrow">Secure records</p>
            <h2 id="document-filter-heading">Find documents</h2>
        </div>
    </div>
    <form class="mcc-form-grid mcc-document-filter-form" method="get" action="<?= site_url('documents') ?>">
        <div class="mcc-form-group">
            <label for="document-query">Player or document</label>
            <input
                class="form-control"
                id="document-query"
                name="q"
                type="search"
                value="<?= esc((string) ($filters['q'] ?? '')) ?>"
                placeholder="Search by Player name or document"
            >
        </div>
        <div class="mcc-form-group">
            <label for="document-type">Document type</label>
            <select class="form-control" id="document-type" name="document_type">
                <option value="">All document types</option>
                <?php foreach ([
                    'aadhaar' => 'Aadhaar',
                    'birth_certificate' => 'Birth certificate',
                    'school_id' => 'School ID',
                    'mca_card' => 'MCA card',
                    'medical_certificate' => 'Medical certificate',
                    'passport' => 'Passport',
                    'visa' => 'Visa',
                    'consent_form' => 'Consent form',
                    'player_photo' => 'Player photo',
                ] as $value => $label): ?>
                    <option value="<?= esc($value) ?>" <?= ($filters['document_type'] ?? '') === $value ? 'selected' : '' ?>>
                        <?= esc($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mcc-form-group">
            <label for="document-status">Status</label>
            <select class="form-control" id="document-status" name="status">
                <option value="">All statuses</option>
                <?php foreach (['active' => 'Active', 'expired' => 'Expired', 'retired' => 'Retired'] as $value => $label): ?>
                    <option value="<?= esc($value) ?>" <?= ($filters['status'] ?? '') === $value ? 'selected' : '' ?>>
                        <?= esc($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mcc-form-actions">
            <button class="mcc-button mcc-button-primary" type="submit">Apply filters</button>
            <a class="mcc-button mcc-button-secondary" href="<?= site_url('documents') ?>">Clear</a>
        </div>
    </form>
</section>

<section class="mcc-card mcc-document-list-card" aria-labelledby="document-list-heading">
    <div class="mcc-card-heading">
        <div>
            <p class="mcc-eyebrow">Authorized branch records</p>
            <h2 id="document-list-heading">Available documents</h2>
        </div>
        <span class="mcc-muted"><?= count($documents) ?> record<?= count($documents) === 1 ? '' : 's' ?></span>
    </div>

    <?php if ($documents === []): ?>
        <p class="mcc-empty-state">No documents available.</p>
    <?php else: ?>
        <div class="mcc-table-wrap mcc-document-table-wrap">
            <table class="mcc-table mcc-document-table">
                <caption class="visually-hidden">Authorized document records</caption>
                <thead>
                    <tr>
                        <th scope="col">Related record</th>
                        <th scope="col">Branch</th>
                        <th scope="col">Document type</th>
                        <th scope="col">Verification</th>
                        <th scope="col">Scanner</th>
                        <th scope="col">Version</th>
                        <th scope="col">Expiry</th>
                        <th scope="col">Uploaded</th>
                        <th scope="col">Available actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($documents as $document): ?>
                        <?php
                        $status = $statusMeta((string) ($document['status'] ?? ''));
                        $verification = $verificationMeta((string) ($document['verification_status'] ?? 'PENDING'));
                        $scanner = $scannerMeta((string) ($document['scanner_status'] ?? 'QUARANTINED'));
                        $type = ucwords(str_replace('_', ' ', (string) ($document['document_type'] ?? 'Document')));
                        ?>
                        <tr>
                            <th scope="row">
                                <span class="mcc-document-primary"><?= esc((string) ($document['full_name'] ?? 'Authorized record')) ?></span>
                            </th>
                            <td><?= esc((string) ($document['branch_name'] ?? 'Authorized branch')) ?></td>
                            <td><?= esc($type) ?></td>
                            <td><span class="mcc-badge mcc-status-badge <?= esc($verification['class']) ?>"><?= esc($verification['label']) ?></span></td>
                            <td><span class="mcc-badge mcc-status-badge <?= esc($scanner['class']) ?>"><?= esc($scanner['label']) ?></span></td>
                            <td>v<?= (int) ($document['version_number'] ?? 1) ?></td>
                            <td>
                                <?php if (! empty($document['expires_on'])): ?>
                                    <?= esc(mcc_user_date($document['expires_on'], 'Not set')) ?>
                                <?php else: ?>
                                    <span class="mcc-muted">No expiry</span>
                                <?php endif; ?>
                                <span class="mcc-badge mcc-status-badge <?= esc($status['class']) ?>"><?= esc($status['label']) ?></span>
                            </td>
                            <td><?= esc(mcc_user_datetime($document['created_at'] ?? null)) ?></td>
                            <td>
                                <a class="mcc-button mcc-button-secondary mcc-button-small" href="<?= site_url('documents/' . (int) $document['id']) ?>">View</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?= view('staff_footer') ?>
