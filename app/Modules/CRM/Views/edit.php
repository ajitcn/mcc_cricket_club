<?php
$fieldErrors = session()->getFlashdata('field_errors');
$fieldErrors = is_array($fieldErrors) ? $fieldErrors : [];
$fieldError = static fn (string $field): string => trim((string) ($fieldErrors[$field] ?? ''));
?>
<?= view('staff_header', ['title' => 'Edit lead', 'nav' => $nav, 'message' => $message, 'error' => $error]) ?>
<section class="mcc-card" aria-labelledby="lead-edit-heading">
<div class="mcc-card-heading">
<span class="mcc-eyebrow">CRM</span>
<h2 id="lead-edit-heading">Edit lead</h2>
</div>
<form class="row" method="post" action="<?= site_url('crm/leads/'.$lead['id'].'/edit') ?>">
<?= csrf_field() ?>
<div class="col-12 col-md-4">
<label for="lead-name">Full name</label>
<input class="form-control mb-2" id="lead-name" name="full_name" required maxlength="150" value="<?= esc(old('full_name', $lead['full_name'])) ?>">
<?php if ($fieldError('full_name') !== ''): ?><p class="mcc-field-error" role="alert"><?= esc($fieldError('full_name')) ?></p><?php endif; ?>
</div>
<div class="col-12 col-md-4">
<label for="lead-email">Email</label>
<input class="form-control mb-2" id="lead-email" name="email" type="email" value="<?= esc(old('email', $lead['email'] ?? '')) ?>">
<?php if ($fieldError('email') !== ''): ?><p class="mcc-field-error" role="alert"><?= esc($fieldError('email')) ?></p><?php endif; ?>
</div>
<div class="col-12 col-md-4">
<label for="lead-phone">Phone</label>
<input class="form-control mb-2" id="lead-phone" name="phone" maxlength="40" value="<?= esc(old('phone', $lead['phone'] ?? '')) ?>">
<?php if ($fieldError('phone') !== ''): ?><p class="mcc-field-error" role="alert"><?= esc($fieldError('phone')) ?></p><?php endif; ?>
</div>
<div class="col-12 col-md-4">
<label for="lead-dob">Date of birth</label>
<input class="form-control mb-2" id="lead-dob" name="date_of_birth" type="text" inputmode="numeric" placeholder="DD-MM-YYYY" autocomplete="off" data-mcc-date-picker data-mcc-date-picker-no-toggle data-mcc-date-format="d-m-Y"
    value="<?= esc(old('date_of_birth', $lead['date_of_birth'] ?? '')) ?>">
<?php if ($fieldError('date_of_birth') !== ''): ?><p class="mcc-field-error" role="alert"><?= esc($fieldError('date_of_birth')) ?></p><?php endif; ?>
</div>
<div class="col-12 col-md-4">
<label for="lead-branch">Branch</label>
<select class="form-control mb-2" id="lead-branch" name="branch_id" disabled>
<?php foreach ($branches as $branch): ?>
<option value="<?= (int) $branch['id'] ?>" <?= (int) $branch['id'] === (int) $lead['branch_id'] ? 'selected' : '' ?>>
<?= esc($branch['name']) ?> (<?= esc($branch['code']) ?>)</option>
<?php endforeach; ?>
</select>
<?php if ($fieldError('source') !== ''): ?><p class="mcc-field-error" role="alert"><?= esc($fieldError('source')) ?></p><?php endif; ?>
<span class="mcc-help">Branch ownership is not editable here.</span>
</div>
<div class="col-12 col-md-4">
<label for="lead-source">Source</label>
<select class="form-control mb-2" id="lead-source" name="source" required>
<?php $currentSource = (string) $lead['source']; ?>
<option value="<?= esc($currentSource) ?>" selected>
<?= esc($currentSource) ?> (current)</option>
<?php foreach ($sources as $source): if ($source['name'] === $currentSource) continue; ?>
<option value="<?= esc($source['name']) ?>">
<?= esc($source['name']) ?>
</option>
<?php endforeach; ?>
</select>
<?php if ($fieldError('owner_user_id') !== ''): ?><p class="mcc-field-error" role="alert"><?= esc($fieldError('owner_user_id')) ?></p><?php endif; ?>
</div>
<div class="col-12 col-md-4">
<label for="lead-owner">Assign to</label>
<select class="form-control mb-2" id="lead-owner" name="owner_user_id">
<option value="">Unassigned</option>
<?php foreach ($assignableUsers as $user): ?>
<option value="<?= (int) $user['id'] ?>" <?= (string) old('owner_user_id', $lead['owner_user_id'] ?? '') === (string) $user['id'] ? 'selected' : '' ?>>
<?= esc($user['display_name']) ?> (<?= esc($user['email']) ?>)
</option>
<?php endforeach; ?>
</select>
</div>
<div class="col-12 col-md-4">
<label for="lead-status">Status</label>
<input class="form-control mb-2" id="lead-status" value="<?= esc($lead['status']) ?>" readonly>
<span class="mcc-help">Use the controlled status action on the details page to change status.</span>
</div>
<div class="col-12">
<label for="lead-remarks">Remarks <span class="mcc-muted">(optional)</span></label>
<textarea class="form-control mb-2" id="lead-remarks" name="remarks" maxlength="65535" rows="5"><?= esc(old('remarks', $lead['remarks'] ?? '')) ?></textarea>
</div>
<div class="col-12 mt-2 text-right">
<button class="mcc-button mcc-button-primary" type="submit">Save changes</button>
<a class="mcc-button mcc-button-secondary ml-2" href="<?= site_url('crm/leads/'.$lead['id']) ?>">Cancel</a>
</div>
</form>
</section>
<?= view('staff_footer') ?>
