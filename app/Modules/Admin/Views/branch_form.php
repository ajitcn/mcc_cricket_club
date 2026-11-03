<?php
$editing = ($mode ?? 'create') === 'edit';
$branch = $branch ?? [];
$errors = session()->getFlashdata('errors') ?? [];
?>
<?= view('staff_header', ['title' => $editing ? 'Edit branch' : 'Create branch', 'nav' => $nav, 'message' => $message ?? null, 'error' => $error ?? null]) ?>
<section class="mcc-card" aria-labelledby="branch-form-heading">
<div class="mcc-card-heading">
<span class="mcc-eyebrow">Administration</span>
<h2 id="branch-form-heading">Branch details</h2>
</div>
<p class="mcc-help">
<?= $editing ? 'Update the branch name, code, or address. Active status remains server-controlled.' :
    'Create an active organization branch. Name, code, and address must be unique where applicable.' ?>
</p>
<form class="row mcc-form" method="post" action="<?= site_url($editing ? 'admin/branches/' . (int) $branch['id'] : 'admin/branches/create') ?>">
<?= csrf_field() ?>
<div class="col-12 col-md-6 mb-2">
<label for="branch-name">Name</label>
<input class="form-control" id="branch-name" name="name" type="text" maxlength="120" value="<?= esc(old('name', $branch['name'] ?? '')) ?>" required autocomplete="organization">
<?php if (isset($errors['name'])): ?><div class="invalid-feedback d-block"><?= esc($errors['name']) ?></div><?php endif; ?>
</div>
<div class="col-12 col-md-6 mb-2">
<label for="branch-code">Code</label>
<input class="form-control" id="branch-code" name="code" type="text" maxlength="80" value="<?= esc(old('code', $branch['code'] ?? '')) ?>" required autocomplete="off">
<?php if (isset($errors['code'])): ?><div class="invalid-feedback d-block"><?= esc($errors['code']) ?></div><?php endif; ?>
</div>
<div class="col-12 mb-2">
<label for="branch-address">Address</label>
<textarea class="form-control" id="branch-address" name="address" maxlength="255" required>
<?= esc(old('address', $branch['address'] ?? '')) ?>
</textarea>
<?php if (isset($errors['address'])): ?><div class="invalid-feedback d-block"><?= esc($errors['address']) ?></div><?php endif; ?>
</div>
<div class="col-12 mt-2 text-right">
<button class="mcc-button mcc-button-primary" type="submit">
<?= $editing ? 'Save changes' : 'Create branch' ?>
</button>
<a class="mcc-button mcc-button-secondary ml-2" href="<?= site_url($editing ? 'admin/branches/' . (int) $branch['id'] : 'admin/branches') ?>">Cancel</a>
</div>
</form>
</section>
<?= view('staff_footer') ?>
