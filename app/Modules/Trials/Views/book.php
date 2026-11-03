<?php $lead = is_array($lead ?? null) ? $lead : []; ?>
<?php $fieldErrors = session()->getFlashdata('field_errors'); ?>
<?php $fieldErrors = is_array($fieldErrors) ? $fieldErrors : []; ?>
<?= view('staff_header', ['title' => 'Book trial', 'nav' => $nav ?? [], 'message' => $message ?? null, 'error' => $error ?? null]) ?>
<section class="mcc-card" aria-labelledby="trial-booking-heading">
<div class="mcc-card-heading">
<div>
<span class="mcc-eyebrow">Admissions &amp; CRM</span>
<h2 id="trial-booking-heading">Book a trial</h2>
<p>Lead: <?= esc($lead['full_name'] ?? '') ?> · Branch: <?= esc($lead['branch_name'] ?? '') ?></p>
</div>
</div>
<form method="post" action="<?= site_url('crm/trials/from-lead/' . (int) ($lead['id'] ?? 0)) ?>" class="row">
<?= csrf_field() ?>
<input type="hidden" name="return_context" value="standalone">
<div class="col-12 col-md-4 mb-2">
<label for="trial-date">Trial date</label>
<input class="form-control" id="trial-date" name="trial_date" type="text" inputmode="numeric" placeholder="DD-MM-YYYY" autocomplete="off" data-mcc-date-picker data-mcc-date-format="d-m-Y" data-mcc-date-submit-format="Y-m-d" required value="<?= esc(old('trial_date')) ?>">
<?php if (isset($fieldErrors['trial_date'])): ?>
<p class="mcc-field-error" role="alert"><?= esc($fieldErrors['trial_date']) ?></p>
<?php endif; ?>
</div>
<div class="col-12 col-md-4 mb-2">
<label for="trial-time">Trial time</label>
<input class="form-control" id="trial-time" name="trial_time" type="time" required value="<?= esc(old('trial_time')) ?>">
<?php if (isset($fieldErrors['trial_time'])): ?>
<p class="mcc-field-error" role="alert"><?= esc($fieldErrors['trial_time']) ?></p>
<?php endif; ?>
</div>
<div class="col-12 col-md-4 mb-2">
<label for="trial-location">Trial location / ground <span class="mcc-muted">(optional)</span></label>
<?php $oldLocation = trim((string) old('location')); ?>
<select class="form-control" id="trial-location" name="location">
<option value="">Not selected</option>
<?php foreach (($locations ?? []) as $location): ?>
<?php $locationName = (string) ($location['name'] ?? ''); ?>
<option
value="<?= esc($locationName) ?>"
<?= $oldLocation === $locationName ? 'selected' : '' ?>
><?= esc($locationName) ?></option>
<?php endforeach; ?>
</select>
<?php if (isset($fieldErrors['location'])): ?>
<p class="mcc-field-error" role="alert"><?= esc($fieldErrors['location']) ?></p>
<?php endif; ?>
</div>
<div class="col-12 col-md-6 mb-2">
<label for="trial-coach">Primary Coach / Evaluator <span class="mcc-muted">(optional)</span></label>
<select class="form-control" id="trial-coach" name="coach_user_id">
<option value="">Not assigned</option>
<?php foreach (($coaches ?? []) as $coach): ?>
<option value="<?= (int) $coach['id'] ?>" <?= old('coach_user_id') == $coach['id'] ? 'selected' : '' ?>>
<?= esc($coach['display_name']) ?>
</option>
<?php endforeach; ?>
</select>
<?php if (isset($fieldErrors['coach_user_id'])): ?>
<p class="mcc-field-error" role="alert"><?= esc($fieldErrors['coach_user_id']) ?></p>
<?php endif; ?>
</div>
<div class="col-12 col-md-6 mb-2">
<label for="trial-secondary-coach">Secondary Coach / Evaluator <span class="mcc-muted">(optional)</span></label>
<select class="form-control" id="trial-secondary-coach" name="secondary_coach_user_id">
<option value="">Not assigned</option>
<?php foreach (($coaches ?? []) as $coach): ?>
<option value="<?= (int) $coach['id'] ?>" <?= old('secondary_coach_user_id') == $coach['id'] ? 'selected' : '' ?>>
<?= esc($coach['display_name']) ?>
</option>
<?php endforeach; ?>
</select>
<?php if (isset($fieldErrors['secondary_coach_user_id'])): ?>
<p class="mcc-field-error" role="alert"><?= esc($fieldErrors['secondary_coach_user_id']) ?></p>
<?php endif; ?>
</div>
<div class="col-12 col-md-6 mb-2">
<label for="trial-remarks">Remarks <span class="mcc-muted">(optional)</span></label>
<input class="form-control" id="trial-remarks" name="remarks" maxlength="65535" value="<?= esc(old('remarks')) ?>">
<?php if (isset($fieldErrors['remarks'])): ?>
<p class="mcc-field-error" role="alert"><?= esc($fieldErrors['remarks']) ?></p>
<?php endif; ?>
</div>
<div class="col-12 mt-2 text-right">
<button class="mcc-button mcc-button-primary" type="submit">Book trial</button>
<a class="mcc-button mcc-button-secondary ml-2" href="<?= site_url('crm/leads') ?>">Cancel</a>
</div>
</form>
</section>
<?= view('staff_footer') ?>
