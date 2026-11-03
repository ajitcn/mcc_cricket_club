<?php
$lead = is_array($lead ?? null) ? $lead : [];
$history = is_array($lead['history'] ?? null) ? $lead['history'] : [];
$followUps = is_array($lead['follow_ups'] ?? null) ? $lead['follow_ups'] : [];
$trials = is_array($lead['trials'] ?? null) ? $lead['trials'] : [];
$admissions = is_array($lead['admissions'] ?? null) ? $lead['admissions'] : [];
$canUpdate = (bool) ($canUpdate ?? false);
$canBookTrial = (bool) ($canBookTrial ?? false);
$canViewAdmissions = (bool) ($canViewAdmissions ?? false);
$fieldErrors = session()->getFlashdata('field_errors');
$fieldErrors = is_array($fieldErrors) ? $fieldErrors : [];
$statuses = is_array($statuses ?? null) ? $statuses : [];
$formatDate = static function (?string $value): string {
    if ($value === null || $value === '') {
        return 'Ã¢â‚¬â€';
    }

    $timestamp = strtotime($value);
    return $timestamp === false ? esc($value) : date('d-m-Y, h:i A', $timestamp);
};
$formatDateOfBirth = static function ($value): string {
    $dateOfBirth = trim((string) ($value ?? ''));
    if ($dateOfBirth === '') {
        return '—';
    }

    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $dateOfBirth);
    if ($parsed === false || $parsed->format('Y-m-d') !== $dateOfBirth) {
        return '—';
    }

    return $parsed->format('d-m-Y');
};
$lead['date_of_birth'] = $formatDateOfBirth($lead['date_of_birth'] ?? null);
?>
<?= view('staff_header', ['title' => 'Lead details', 'nav' => $nav ?? [], 'message' => $message ?? null, 'error' => $error ?? null]) ?>
<div class="mcc-lead-detail">
<section class="mcc-card mcc-detail-hero" aria-labelledby="lead-details-title">
<div>
<span class="mcc-eyebrow">CRM lead</span>
<h2 id="lead-details-title">
<?= esc($lead['full_name'] ?? 'Lead') ?>
</h2>
<div class="mcc-detail-badges" aria-label="Lead classification">
<span class="mcc-badge mcc-badge-info">
<?= esc((string) ($lead['source'] ?? 'Ã¢â‚¬â€')) ?>
</span>
<span class="mcc-badge mcc-badge-neutral">
<?= esc((string) ($lead['status'] ?? 'Ã¢â‚¬â€')) ?>
</span>
</div>
</div>
<div class="mcc-detail-actions">
<?php if ($canUpdate): ?>
<a class="mcc-button mcc-button-primary" href="<?= site_url('crm/leads/' . (int) ($lead['id'] ?? 0) . '/edit') ?>" title="Edit lead" aria-label="Edit lead">
<i class="fa-solid fa-pen" aria-hidden="true">
</i><span class="mcc-button-label">Edit</span></a>
<?php endif; ?>
<a class="mcc-button mcc-button-secondary" href="<?= site_url('crm/leads') ?>" title="Back to leads" aria-label="Back to leads">
<i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
<span class="mcc-button-label">Back to Leads</span>
</a>
</div>
</section>
<div class="mcc-detail-grid">
<section class="mcc-card" aria-labelledby="lead-information-heading">
<div class="mcc-card-heading">
<div>
<span class="mcc-eyebrow">Profile</span>
<h2 id="lead-information-heading">Lead information</h2>
</div>
</div>
<dl class="mcc-detail-list">
<dt>Lead name</dt>
<dd>
<?= esc($lead['full_name'] ?? 'Ã¢â‚¬â€') ?>
</dd>
<dt>Date of birth</dt>
<dd>
<?= esc($lead['date_of_birth'] ?? 'Ã¢â‚¬â€') ?>
</dd>
<dt>Remarks</dt>
<dd>
<?= nl2br(esc($lead['remarks'] ?? '')) ?>
</dd>
</dl>
</section>
<section class="mcc-card" aria-labelledby="contact-information-heading">
<div class="mcc-card-heading">
<div>
<span class="mcc-eyebrow">Reachability</span>
<h2 id="contact-information-heading">Contact information</h2>
</div>
</div>
<dl class="mcc-detail-list">
<dt>Email</dt>
<dd>
<a href="mailto:<?= esc((string) ($lead['email'] ?? '')) ?>">
<?= esc($lead['email'] ?? 'Ã¢â‚¬â€') ?>
</a>
</dd>
<dt>Mobile</dt>
<dd>
<a href="tel:<?= esc((string) ($lead['phone'] ?? '')) ?>">
<?= esc($lead['phone'] ?? 'Ã¢â‚¬â€') ?>
</a>
</dd>
</dl>
</section>
</div>
<section class="mcc-card mcc-detail-card" aria-labelledby="classification-heading">
<div class="mcc-card-heading">
<div>
<span class="mcc-eyebrow">Context</span>
<h2 id="classification-heading">Source, status and branch</h2>
</div>
</div>
<dl class="mcc-detail-list">
<dt>Source</dt>
<dd>
<span class="mcc-badge mcc-badge-info">
<?= esc((string) ($lead['source'] ?? 'Ã¢â‚¬â€')) ?>
</span>
</dd>
<dt>Status</dt>
<dd>
<span class="mcc-badge mcc-badge-neutral">
<?= esc((string) ($lead['status'] ?? 'Ã¢â‚¬â€')) ?>
</span>
</dd>
<dt>Branch</dt>
<dd>
<?= esc((string) ($lead['branch_name'] ?: ('Branch #' . ($lead['branch_id'] ?? '')))) ?>
</dd>
<dt>Assigned To</dt>
<dd>
<?php if (!empty($lead['owner_user_id']) && !empty($lead['owner_name'])): ?>
<span><?= esc($lead['owner_name']) ?></span>
<?php if (!empty($lead['owner_email'])): ?>
<span class="mcc-muted">
<a href="mailto:<?= esc($lead['owner_email']) ?>"><?= esc($lead['owner_email']) ?></a>
</span>
<?php endif; ?>
<?php else: ?>
<span class="mcc-muted">—</span>
<?php endif; ?>
</dd>
</dl>
</section>
<section class="mcc-card mcc-detail-card" aria-labelledby="dates-heading">
<div class="mcc-card-heading">
<div>
<span class="mcc-eyebrow">Record history</span>
<h2 id="dates-heading">Created and updated</h2>
</div>
</div>
<dl class="mcc-detail-list">
<dt>Created</dt>
<dd>
<?= $formatDate($lead['created_at'] ?? null) ?>
</dd>
<dt>Updated</dt>
<dd>
<?= $formatDate($lead['updated_at'] ?? null) ?>
</dd>
</dl>
</section>
<section class="mcc-card mcc-detail-card" aria-labelledby="history-heading">
<div class="mcc-card-heading">
<div>
<span class="mcc-eyebrow">Timeline</span>
<h2 id="history-heading">Activity and history</h2>
</div>
</div>
<?php if ($history === []): ?>
<p class="mcc-empty-state">No status history is available for this lead.</p>
<?php else: ?>
<ol class="mcc-history">
<?php foreach ($history as $event): ?>
<li>
<strong>
<?= esc((string) ($event['new_status'] ?? 'Status updated')) ?>
</strong>
<?php if (! empty($event['previous_status'])): ?>
<span>from <?= esc((string) $event['previous_status']) ?>
</span>
<?php endif; ?>
<span class="mcc-history-meta">
<?= $formatDate($event['created_at'] ?? null) ?>
</span>
<?php if (! empty($event['reason'])): ?>
<span>
<?= esc((string) $event['reason']) ?>
</span>
<?php endif; ?>
</li>
<?php endforeach; ?>
</ol>
<?php endif; ?>
</section>
<div class="mcc-detail-grid">
<section class="mcc-card mcc-lead-trials" aria-labelledby="trials-heading">
<div class="mcc-card-heading">
<div>
<span class="mcc-eyebrow">CRM relationship</span>
<h2 id="trials-heading">Trials</h2>
</div>
<div class="mcc-lead-trial-heading-actions">
<span class="mcc-muted">
<?= count($trials) ?> record<?= count($trials) === 1 ? '' : 's' ?>
</span>
<?php if ($canBookTrial): ?>
<a
class="mcc-button mcc-button-primary"
href="<?= site_url('crm/trials/from-lead/' . (int) ($lead['id'] ?? 0)) ?>"
>
<i class="fa-solid fa-calendar-plus" aria-hidden="true"></i>
<span class="mcc-button-label">Book Trial</span>
</a>
<?php endif; ?>
</div>
</div>
<?php if ($trials === []): ?>
<p class="mcc-empty-state">No trial is linked to this Lead.</p>
<?php else: ?>
<div class="mcc-table-wrap">
<table class="mcc-table">
<thead>
<tr>
<th>Date</th>
<th>Status</th>
<th>Outcome</th>
<th>Open</th>
</tr>
</thead>
<tbody>
<?php foreach ($trials as $trial): ?>
<tr>
<th scope="row">
<?= esc(mcc_user_date($trial['trial_date'] ?? null)) ?>
</th>
<td>
<span class="mcc-badge mcc-badge-neutral">
<?= esc(ucwords(str_replace('_', ' ', (string) $trial['status']))) ?>
</span>
</td>
<td>
<?= esc(ucwords(str_replace('_', ' ', (string) ($trial['selection_outcome'] ?? 'pending')))) ?>
</td>
<td>
<a class="mcc-button mcc-button-secondary" href="<?= site_url('crm/trials/' . (int) $trial['id']) ?>" title="View trial" aria-label="View trial">
<i class="fa-solid fa-eye" aria-hidden="true"></i><span class="mcc-button-label">View trial</span>
</a>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php endif; ?>
</section>
<section class="mcc-card" aria-labelledby="admissions-heading">
<div class="mcc-card-heading">
<div>
<span class="mcc-eyebrow">CRM relationship</span>
<h2 id="admissions-heading">Admissions</h2>
</div>
<span class="mcc-muted">
<?= count($admissions) ?> record<?= count($admissions) === 1 ? '' : 's' ?>
</span>
</div>
<?php if ($admissions === []): ?>
<p class="mcc-empty-state">No admission is linked to this Lead. Direct Admissions remain separate.</p>
<?php elseif (!$canViewAdmissions): ?>
<p class="mcc-muted">Linked Admission details are restricted by your permissions.</p>
<?php else: ?>
<div class="mcc-table-wrap">
<table class="mcc-table">
<thead>
<tr>
<th>Admission</th>
<th>Status</th>
<th>Open</th>
</tr>
</thead>
<tbody>
<?php foreach ($admissions as $admission): ?>
<tr>
<th scope="row">#<?= (int) $admission['id'] ?>
</th>
<td>
<span class="mcc-badge mcc-badge-neutral">
<?= esc(ucwords(str_replace('_', ' ', (string) $admission['status']))) ?>
</span>
</td>
<td>
<a class="mcc-button mcc-button-secondary" href="<?= site_url('admissions/' . (int) $admission['id'] . '/page') ?>" title="View admission" aria-label="View admission">
<i class="fa-solid fa-eye" aria-hidden="true"></i><span class="mcc-button-label">View admission</span>
</a>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php endif; ?>
</section>
</div>
<section class="mcc-card mcc-detail-card mcc-follow-up-history" aria-labelledby="follow-ups-heading">
<div class="mcc-card-heading">
<div>
<span class="mcc-eyebrow">CRM activity</span>
<h2 id="follow-ups-heading">Follow-up history</h2>
</div>
<?php if (! empty($lead['can_manage_follow_ups'])): ?>
<a class="mcc-button mcc-button-primary" href="<?= site_url('crm/leads/' . (int) ($lead['id'] ?? 0) . '/follow-ups/new') ?>" title="Add follow-up" aria-label="Add follow-up">
<i class="fa-solid fa-plus" aria-hidden="true"></i><span class="mcc-button-label">Add follow-up</span>
</a>
<?php endif; ?>
</div>
<?php if ($followUps === []): ?>
<p class="mcc-empty-state">No follow-ups are recorded for this lead.</p>
<?php else: ?>
<ol class="mcc-history">
<?php foreach ($followUps as $followUp): $followUpStatus = $followUp['status'] ?: 'pending'; ?>
<li class="mcc-activity-item">
<strong class="mcc-activity-type">
<?= esc(ucwords(str_replace('_', ' ', (string) ($followUp['follow_up_type'] ?: 'other')))) ?>
</strong>
<span class="mcc-badge mcc-badge-neutral mcc-activity-status">
<?= esc(ucwords(str_replace('_', ' ', $followUpStatus))) ?>
</span>
<?php if (! empty($followUp['outcome'])): ?>
<span>Outcome: <?= esc(ucwords(str_replace('_', ' ', $followUp['outcome']))) ?>
</span>
<?php endif; ?>
<span class="mcc-activity-notes">
<?= esc((string) $followUp['note']) ?>
</span>
<?php if (! empty($followUp['follow_up_at'])): ?>
<span class="mcc-activity-schedule"><i class="fa-solid fa-clock" aria-hidden="true"></i> Scheduled: <?= esc($formatDate($followUp['follow_up_at'])) ?>
</span>
<?php endif; ?>
<span class="mcc-history-meta">By <?= esc((string) ($followUp['actor_name'] ?? 'Unknown')) ?> Ã‚Â· <?= esc($formatDate($followUp['created_at'] ?? null)) ?>
</span>
<?php if (! empty($followUp['cancellation_reason'])): ?>
<span>Cancellation reason: <?= esc($followUp['cancellation_reason']) ?>
</span>
<?php endif; ?>
<?php if ($followUpStatus === 'pending' && ! empty($lead['can_manage_follow_ups'])): ?>
    <form method="post" action="<?= site_url('crm/leads/' . (int) $lead['id'] . '/follow-ups/' . (int) $followUp['id'] . '/edit') ?>" class="row">
<?= csrf_field() ?>
<div class="col-12 col-md-4 mb-2">
<label for="follow-up-type-<?= (int) $followUp['id'] ?>">Type / purpose</label>
<select class="form-control" id="follow-up-type-<?= (int) $followUp['id'] ?>" name="follow_up_type" required>
<?php foreach (['call','whatsapp','email','meeting','other'] as $type): ?>
<option value="<?= $type ?>" <?= ($followUp['follow_up_type'] ?: 'other') === $type ? 'selected' : '' ?>>
<?= esc(ucwords($type)) ?>
</option>
<?php endforeach; ?>
</select>
</div>
<div class="col-12 col-md-4 mb-2">
<label for="follow-up-at-<?= (int) $followUp['id'] ?>">Date and time</label>
<input class="form-control" id="follow-up-at-<?= (int) $followUp['id'] ?>" name="follow_up_at" type="text" inputmode="numeric" placeholder="DD-MM-YYYY" autocomplete="off" data-mcc-date-picker data-mcc-date-picker-no-toggle data-mcc-date-format="d-m-Y" data-mcc-date-picker-time-input="follow-up-time-<?= (int) $followUp['id'] ?>"
    value="<?= ! empty($followUp['follow_up_at']) ? esc(date('d-m-Y', strtotime((string) $followUp['follow_up_at']))) : '' ?>">
<input class="form-control mt-2" id="follow-up-time-<?= (int) $followUp['id'] ?>" type="time"
    value="<?= ! empty($followUp['follow_up_at']) ? esc(substr(str_replace(' ', 'T', (string) $followUp['follow_up_at']), 11, 5)) : '' ?>">
</div>
<div class="col-12 col-md-4 mb-2">
<label for="follow-up-note-<?= (int) $followUp['id'] ?>">Notes</label>
<textarea class="form-control" id="follow-up-note-<?= (int) $followUp['id'] ?>" name="note" maxlength="65535" required>
<?= esc((string) $followUp['note']) ?>
</textarea>
</div>
<div class="col-12 mt-2 text-right">
<button class="mcc-button mcc-button-secondary" type="submit">
<i class="fa-solid fa-floppy-disk" aria-hidden="true"></i><span class="mcc-button-label">Save follow-up</span>
</button>
</div>
</form>
<form method="post" action="<?= site_url('crm/leads/' . (int) $lead['id'] . '/follow-ups/' . (int) $followUp['id'] . '/complete') ?>" class="row">
<?= csrf_field() ?>
<div class="col-12 col-md-6 mb-2"><label for="follow-up-outcome-<?= (int) $followUp['id'] ?>">Outcome</label><select class="form-control" id="follow-up-outcome-<?= (int) $followUp['id'] ?>" name="outcome" required>
<option value="">Select outcome</option>
<?php foreach (['no_answer','contacted','interested','callback_requested','not_interested','invalid_contact'] as $outcome): ?>
<option value="<?= $outcome ?>">
<?= esc(ucwords(str_replace('_', ' ', $outcome))) ?>
</option>
<?php endforeach; ?>
</select></div>
<div class="col-12 mt-2 text-right">
<button class="mcc-button mcc-button-primary" type="submit">
<i class="fa-solid fa-check" aria-hidden="true"></i><span class="mcc-button-label">Complete</span>
</button>
</div>
</form>
<form method="post" action="<?= site_url('crm/leads/' . (int) $lead['id'] . '/follow-ups/' . (int) $followUp['id'] . '/cancel') ?>" class="row">
<?= csrf_field() ?>
<div class="col-12 mb-2"><label for="follow-up-reason-<?= (int) $followUp['id'] ?>">Cancellation reason</label><input class="form-control" id="follow-up-reason-<?= (int) $followUp['id'] ?>" name="reason"
    maxlength="255" required>
</div>
<div class="col-12 mt-2 text-right">
<button class="mcc-button mcc-button-danger" type="submit" title="Cancel follow-up" aria-label="Cancel follow-up">
<i class="fa-solid fa-ban" aria-hidden="true"></i><span class="mcc-button-label">Cancel</span>
</button>
</div>
</form>
<?php endif; ?>
</li>
<?php endforeach; ?>
</ol>
<?php endif; ?>
<?php if (! empty($lead['can_manage_follow_ups'])): ?>
<form method="post" action="<?= site_url('crm/leads/' . (int) ($lead['id'] ?? 0) . '/follow-ups') ?>" class="row">
<?= csrf_field() ?>
<div class="col-12 col-md-4 mb-2">
<label for="follow-up-type">Type / purpose</label>
<select class="form-control" id="follow-up-type" name="follow_up_type" required>
<?php foreach (['call','whatsapp','email','meeting','other'] as $type): ?>
<option value="<?= $type ?>">
<?= esc(ucwords($type)) ?>
</option>
<?php endforeach; ?>
</select>
</div>
<div class="col-12 col-md-4 mb-2">
<label for="follow-up-at">Date and time <span class="mcc-muted">(optional)</span>
</label>
<input class="form-control" id="follow-up-at" name="follow_up_at" type="text" inputmode="numeric" placeholder="DD-MM-YYYY" autocomplete="off" data-mcc-date-picker data-mcc-date-picker-no-toggle data-mcc-date-format="d-m-Y" data-mcc-date-picker-time-input="follow-up-time">
<input class="form-control mt-2" id="follow-up-time" type="time" aria-label="Follow-up time">
</div>
<div class="col-12 col-md-4 mb-2">
<label for="follow-up-note">Notes</label>
<textarea class="form-control" id="follow-up-note" name="note" maxlength="65535" required>
</textarea>
</div>
<div class="col-12 mt-2 text-right">
<button class="mcc-button mcc-button-primary" type="submit">
<i class="fa-solid fa-plus" aria-hidden="true"></i><span class="mcc-button-label">Add follow-up</span>
</button>
</div>
</form>
<?php endif; ?>
</section>
<?php if ($canUpdate): ?>
<section class="mcc-card mcc-detail-card" aria-labelledby="lead-actions-heading">
<div class="mcc-card-heading">
<div>
<span class="mcc-eyebrow">Workflow</span>
<h2 id="lead-actions-heading">Lead actions</h2>
</div>
</div>
<form method="post" action="<?= site_url('crm/leads/' . (int) ($lead['id'] ?? 0) . '/status') ?>" class="row">
<?= csrf_field() ?>
<div class="col-12 col-md-4 mb-2">
<label for="lead-status">Update status</label>
<select class="form-control" id="lead-status" name="status">
<?php foreach ($statuses as $status): ?>
<option value="<?= esc((string) $status['name']) ?>" <?= ($lead['status'] ?? '') === $status['name'] ? 'selected' : '' ?>>
<?= esc((string) $status['name']) ?>
</option>
<?php endforeach; ?>
</select>
</div>
<div class="col-12 col-md-4 mb-2">
<label for="lead-status-reason">Reason <span class="mcc-muted">(optional)</span>
</label>
<input class="form-control" id="lead-status-reason" name="reason" type="text" maxlength="255">
</div>
<input type="hidden" name="branch_id" value="<?= esc((string) ($lead['branch_id'] ?? '')) ?>">
<div class="col-12 mt-2 text-right">
<button class="mcc-button mcc-button-primary" type="submit">
<i class="fa-solid fa-arrow-right" aria-hidden="true"></i><span class="mcc-button-label">Update status</span>
</button>
</div>
</form>
</section>
<?php endif; ?>
<?php if ($canBookTrial): ?>
<section class="mcc-card mcc-detail-card" aria-labelledby="trial-booking-heading">
<div class="mcc-card-heading">
<div>
<span class="mcc-eyebrow">Admissions &amp; CRM</span>
<h2 id="trial-booking-heading">Book a trial</h2>
</div>
</div>
<form method="post" action="<?= site_url('crm/trials/from-lead/' . (int) ($lead['id'] ?? 0)) ?>" class="row">
<?= csrf_field() ?>
<input type="hidden" name="return_context" value="lead_detail">
<div class="col-12 col-md-4 mb-2">
<label for="trial-date">Trial date</label>
<input class="form-control" id="trial-date" name="trial_date" type="text" inputmode="numeric" placeholder="DD-MM-YYYY" autocomplete="off" data-mcc-date-picker data-mcc-date-format="d-m-Y" data-mcc-date-submit-format="Y-m-d" required value="<?= esc((string) old('trial_date')) ?>">
<?php if (isset($fieldErrors['trial_date'])): ?>
<p class="mcc-field-error" role="alert"><?= esc($fieldErrors['trial_date']) ?></p>
<?php endif; ?>
</div>
<div class="col-12 col-md-4 mb-2">
<label for="trial-time">Trial time</label>
<input class="form-control" id="trial-time" name="trial_time" type="time" required value="<?= esc((string) old('trial_time')) ?>">
<?php if (isset($fieldErrors['trial_time'])): ?>
<p class="mcc-field-error" role="alert"><?= esc($fieldErrors['trial_time']) ?></p>
<?php endif; ?>
</div>
<div class="col-12 col-md-4 mb-2">
<label for="trial-location">Trial location <span class="mcc-muted">(optional)</span></label>
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
<div class="col-12 col-md-4 mb-2">
<label for="trial-coach">Primary Coach / Evaluator <span class="mcc-muted">(optional)</span>
</label>
<select class="form-control" id="trial-coach" name="coach_user_id">
<option value="">Not assigned</option>
<?php foreach (($coaches ?? []) as $coach): ?>
<option value="<?= (int) $coach['id'] ?>" <?= (string) old('coach_user_id') === (string) $coach['id'] ? 'selected' : '' ?>>
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
<option value="<?= (int) $coach['id'] ?>" <?= (string) old('secondary_coach_user_id') === (string) $coach['id'] ? 'selected' : '' ?>>
<?= esc($coach['display_name']) ?>
</option>
<?php endforeach; ?>
</select>
<?php if (isset($fieldErrors['secondary_coach_user_id'])): ?>
<p class="mcc-field-error" role="alert"><?= esc($fieldErrors['secondary_coach_user_id']) ?></p>
<?php endif; ?>
</div>
<div class="col-12 col-md-4 mb-2">
<label for="trial-remarks">Remarks <span class="mcc-muted">(optional)</span>
</label>
<input class="form-control" id="trial-remarks" name="remarks" maxlength="65535" value="<?= esc((string) old('remarks')) ?>">
<?php if (isset($fieldErrors['remarks'])): ?>
<p class="mcc-field-error" role="alert"><?= esc($fieldErrors['remarks']) ?></p>
<?php endif; ?>
</div>
<div class="col-12 mt-2 text-right">
<button class="mcc-button mcc-button-primary" type="submit">
<i class="fa-solid fa-calendar-plus" aria-hidden="true"></i><span class="mcc-button-label">Book trial</span>
</button>
</div>
</form>
</section>
<?php endif; ?>
</div>
<?= view('staff_footer') ?>
