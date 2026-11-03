<?= view('staff_header', ['title' => 'Permission details', 'nav' => $nav, 'message' => $message ?? null, 'error' => $error ?? null]) ?>
<section class="mcc-card mcc-detail-hero" aria-labelledby="permission-details-title">
<div>
<span class="mcc-eyebrow">Administration</span>
<h2 id="permission-details-title">
<?= esc($permission['permission']) ?>
</h2>
<div class="mcc-detail-badges">
<span class="mcc-badge mcc-badge-info">
<?= esc($permission['category']) ?>
</span>
<span class="mcc-badge mcc-badge-neutral">Approved permission</span>
</div>
</div>
<div class="mcc-detail-actions">
<a class="mcc-button mcc-button-secondary" href="<?= site_url('admin/permissions') ?>">Back to permissions</a>
</div>
</section>
<div class="mcc-detail-grid">
<section class="mcc-card" aria-labelledby="permission-information-heading">
<div class="mcc-card-heading">
<div>
<span class="mcc-eyebrow">Definition</span>
<h2 id="permission-information-heading">Permission information</h2>
</div>
</div>
<dl class="mcc-detail-list">
<dt>Identifier</dt>
<dd>
<?= esc($permission['permission']) ?>
</dd>
<dt>Description</dt>
<dd>
<?= esc($permission['description']) ?>
</dd>
<dt>Assigned roles</dt>
<dd>
<?= esc((string) $permission['role_count']) ?>
</dd>
</dl>
</section>
</div>
<?= view('staff_footer') ?>
