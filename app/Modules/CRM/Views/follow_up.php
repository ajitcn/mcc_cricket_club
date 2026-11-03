<?php $lead = is_array($lead ?? null) ? $lead : []; ?>
<?= view('staff_header', ['title' => 'Add follow-up', 'nav' => $nav ?? [], 'message' => $message ?? null, 'error' => $error ?? null]) ?>
<section class="mcc-card" aria-labelledby="follow-up-heading">
<div class="mcc-card-heading">
<div>
<span class="mcc-eyebrow">CRM activity</span>
<h2 id="follow-up-heading">Add follow-up</h2>
<p>Lead: <?= esc((string) ($lead['full_name'] ?? 'Lead')) ?>
</p>
</div>
</div>
<form method="post" action="<?= site_url('crm/leads/' . (int) ($lead['id'] ?? 0) . '/follow-ups') ?>" class="row">
<?= csrf_field() ?>
<div class="col-12 col-md-6 mb-2">
<label for="follow-up-type">Type / purpose</label>
<select class="form-control" id="follow-up-type" name="follow_up_type" required>
<?php foreach (['call','whatsapp','email','meeting','other'] as $type): ?>
<option value="<?= $type ?>">
<?= esc(ucwords($type)) ?>
</option>
<?php endforeach; ?>
</select>
</div>
<div class="col-12 col-md-6 mb-2">
<label for="follow-up-at">Date and time <span class="mcc-muted">(optional)</span>
</label>
<input class="form-control" id="follow-up-at" name="follow_up_at" type="text" inputmode="numeric" placeholder="DD-MM-YYYY" autocomplete="off" data-mcc-date-picker data-mcc-date-picker-no-toggle data-mcc-date-format="d-m-Y" data-mcc-date-picker-time-input="follow-up-time">
<input class="form-control mt-2" id="follow-up-time" type="time" aria-label="Follow-up time">
</div>
<div class="col-12 mb-2">
<label for="follow-up-note">Notes</label>
<textarea class="form-control" id="follow-up-note" name="note" maxlength="65535" required>
<?= esc(old('note')) ?>
</textarea>
</div>
<div class="col-12 mt-2 text-right">
<button class="mcc-button mcc-button-primary" type="submit">Add follow-up</button>
<a class="mcc-button mcc-button-secondary ml-2" href="<?= site_url('crm/leads/' . (int) ($lead['id'] ?? 0)) ?>">Back to lead</a>
</div>
</form>
</section>
<?= view('staff_footer') ?>
