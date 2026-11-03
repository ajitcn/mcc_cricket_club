<?php
$active = (int) $branch['is_active'] === 1;
$transition = $active ? 'Deactivate' : 'Reactivate';
?>
<?= view('staff_header', ['title' => 'Branch details', 'nav' => $nav, 'message' => $message ?? null, 'error' => $error ?? null]) ?>
<section class="mcc-card" aria-labelledby="branch-heading">
<div class="mcc-card-heading">
<span class="mcc-eyebrow">Administration</span>
<h2 id="branch-heading">
<?= esc($branch['name']) ?>
</h2>
<a class="mcc-button mcc-button-primary" href="<?= site_url('admin/branches/' . (int) $branch['id'] . '/edit') ?>">Edit branch</a>
</div>
<dl class="mcc-detail-list">
<dt>Code</dt>
<dd>
<?= esc($branch['code']) ?>
</dd>
<dt>Address</dt>
<dd>
<?= esc($branch['address'] ?? '') ?>
</dd>
<dt>Status</dt>
<dd>
<?= $active ? 'Active' : 'Inactive' ?>
</dd>
</dl>
<form method="post" action="<?= site_url('admin/branches/' . (int) $branch['id'] . '/status') ?>" onsubmit="return confirm('<?= $transition ?> this branch?');">
<?= csrf_field() ?>
<input type="hidden" name="active" value="<?= $active ? '0' : '1' ?>">
<button class="mcc-button mcc-button-secondary" type="submit">
<?= $transition ?> branch</button>
</form>
<a href="<?= site_url('admin/branches') ?>">Back to branches</a>
</section>
<?= view('staff_footer') ?>
