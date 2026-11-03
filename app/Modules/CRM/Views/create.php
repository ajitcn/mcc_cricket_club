<?= view('staff_header', ['title' => 'Create lead', 'nav' => $nav, 'message' => $message, 'error' => $error]) ?>
<section class="mcc-card" aria-labelledby="lead-create-heading">
<div class="mcc-card-heading">
<span class="mcc-eyebrow">CRM</span>
<h2 id="lead-create-heading">Create lead</h2>
</div>
<form class="row" method="post" action="<?= site_url('crm/leads') ?>">
<?= csrf_field() ?>
<div class="col-12 col-md-4">
<label for="lead-name">Full name</label>
<input class="form-control mb-2" id="lead-name" name="full_name" required maxlength="150" value="<?= esc(old('full_name')) ?>">
</div>
<div class="col-12 col-md-4">
<label for="lead-email">Email</label>
<input class="form-control mb-2" id="lead-email" name="email" type="email" value="<?= esc(old('email')) ?>">
</div>
<div class="col-12 col-md-4">
<label for="lead-phone">Phone</label>
<input class="form-control mb-2" id="lead-phone" name="phone" maxlength="40" value="<?= esc(old('phone')) ?>">
</div>
<div class="col-12 col-md-4">
<label for="lead-dob">Date of birth</label>
<input class="form-control mb-2" id="lead-dob" name="date_of_birth" type="text" inputmode="numeric" placeholder="DD-MM-YYYY" autocomplete="off" data-mcc-date-picker data-mcc-date-picker-no-toggle data-mcc-date-format="d-m-Y"
    value="<?= esc(old('date_of_birth')) ?>">
</div>
<div class="col-12 col-md-4">
<label for="lead-branch">Branch</label>
<select class="form-control mb-2" id="lead-branch" name="branch_id" required>
<option value="">Select an authorized branch</option>
<?php foreach ($branches as $branch): ?>
<option value="<?= (int) $branch['id'] ?>" <?= old('branch_id') == $branch['id'] ? 'selected' : '' ?>>
<?= esc($branch['name']) ?> (<?= esc($branch['code']) ?>)</option>
<?php endforeach; ?>
</select>
</div>
<div class="col-12 col-md-4">
<label for="lead-source">Source</label>
<select class="form-control mb-2" id="lead-source" name="source" required>
<option value="">Select source</option>
<?php foreach ($sources as $source): ?>
<option value="<?= esc($source['name']) ?>" <?= old('source') === $source['name'] ? 'selected' : '' ?>>
<?= esc($source['name']) ?>
</option>
<?php endforeach; ?>
</select>
</div>
<div class="col-12 col-md-4">
<label for="lead-owner">Assign to <span class="mcc-muted">(optional)</span></label>
<select class="form-control mb-2" id="lead-owner" name="owner_user_id">
<option value="">Keep with me</option>
<?php foreach ($assignableUsers as $user): ?>
<option value="<?= (int) $user['id'] ?>" <?= old('owner_user_id') == $user['id'] ? 'selected' : '' ?>>
<?= esc($user['display_name']) ?> (<?= esc($user['email']) ?>)
</option>
<?php endforeach; ?>
</select>
</div>
<div class="col-12 col-md-4">
<label for="lead-status">Status</label>
<select class="form-control mb-2" id="lead-status" name="status" required>
<option value="">Select status</option>
<?php foreach ($statuses as $status): ?>
<option value="<?= esc($status['name']) ?>" <?= old('status', 'lead') === $status['name'] ? 'selected' : '' ?>>
<?= esc($status['name']) ?>
</option>
<?php endforeach; ?>
</select>
</div>
<div class="col-12">
<label for="lead-remarks">Remarks <span class="mcc-muted">(optional)</span></label>
<textarea class="form-control mb-2" id="lead-remarks" name="remarks" maxlength="65535" rows="5"><?= esc(old('remarks')) ?></textarea>
</div>
<div class="col-12 mt-2 text-right">
<button class="mcc-button mcc-button-primary" type="submit">Create lead</button>
<a class="mcc-button mcc-button-secondary ml-2" href="<?= site_url('crm/leads') ?>">Cancel</a>
</div>
</form>
</section>
<?= view('staff_footer') ?>
