<?= view('staff_header', ['title' => $mode === 'create' ? 'Create role' : 'Edit role', 'nav' => $nav, 'message' => $message ?? null, 'error' => $error ??
    null]) ?>
<section class="mcc-card" aria-labelledby="role-form-heading">
<div class="mcc-card-heading">
<div>
<span class="mcc-eyebrow">Administration</span>
<h2 id="role-form-heading">
<?= $mode === 'create' ? 'Create role' : 'Edit role' ?>
</h2>
</div>
</div>
<form method="post" action="<?= $mode === 'create' ? site_url('admin/roles') : site_url('admin/roles/' . $role['id']) ?>" class="row">
<?= csrf_field() ?>
<div class="col-12 mb-2">
<label for="name">Role name</label>
<input class="form-control" id="name" name="name" required maxlength="80" value="<?= esc($role['name'] ?? old('name')) ?>">
</div>
<div class="col-12 mt-2 text-right">
<button class="mcc-button mcc-button-primary" type="submit">
<?= $mode === 'create' ? 'Create role' : 'Save changes' ?>
</button>
<a class="mcc-button mcc-button-secondary ml-2" href="<?= site_url('admin/roles') ?>">Cancel</a>
</div>
</form>
</section>
<?= view('staff_footer') ?>
