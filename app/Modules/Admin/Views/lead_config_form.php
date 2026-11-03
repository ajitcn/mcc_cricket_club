<?php
$source = ($kind ?? 'source') === 'source';
$label = $source ? 'source' : 'status';
$base = $source ? 'lead-sources' : 'lead-statuses';
$editing = ($mode ?? 'create') === 'edit';
?>
<?= view('staff_header', ['title' => ($editing ? 'Edit lead ' : 'Create lead ') . $label, 'nav' => $nav, 'message' => $message, 'error' => $error]) ?>
<section class="mcc-card" aria-labelledby="config-form-heading">
<div class="mcc-card-heading">
<span class="mcc-eyebrow">Administration</span>
<h2 id="config-form-heading">
<?= $editing ? 'Edit' : 'Create' ?> lead <?= esc($label) ?>
</h2>
</div>
<form class="row" method="post" action="<?= site_url('admin/' . $base . ($editing ? '/' . $item['id'] : '')) ?>">
<?= csrf_field() ?>
<div class="col-12 col-md-6 mb-2">
<label for="config-name">Name</label>
<input class="form-control" id="config-name" name="name" required maxlength="<?= $source ? '100' : '40' ?>" value="<?= esc(old('name', $item['name'] ?? '')) ?>">
</div>
<div class="col-12 col-md-6 mb-2">
<label for="config-order">Display order</label>
<input class="form-control" id="config-order" name="sort_order" type="number" min="0" value="<?= (int) old('sort_order', $item['sort_order'] ?? 0) ?>">
</div>
<div class="col-12 mt-2 text-right">
<button class="mcc-button mcc-button-primary" type="submit">Save</button>
<a class="mcc-button mcc-button-secondary ml-2" href="<?= site_url('admin/' . $base) ?>">Cancel</a>
</div>
</form>
</section>
<?= view('staff_footer') ?>
