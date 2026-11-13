<?php
$trial = is_array($trial ?? null) ? $trial : [];
$actions = is_array($trial['actions'] ?? null) ? $trial['actions'] : [];
$canEditCoachRatings = (bool) ($canEditCoachRatings ?? $trial['canEditCoachRatings'] ?? $actions['ratings'] ?? false);
$ratingService = new \App\Services\TrialRatingService();
$statusLabels = ['booked' => 'Booked', 'attended' => 'Attended', 'no_show' => 'No show', 'cancelled' => 'Cancelled', 'rescheduled' => 'Rescheduled'];
$outcomeLabels = ['pending' => 'Pending', 'selected' => 'Selected', 'not_selected' => 'Not selected'];
$eventLabels = [
    'booked' => 'Trial booked',
    'attendance_recorded' => 'Attendance recorded',
    'no_show_recorded' => 'No show recorded',
    'cancelled' => 'Trial cancelled',
    'rescheduled' => 'Trial rescheduled',
    'selection_recorded' => 'Selection recorded',
];
$display = static fn (?string $value, string $fallback = 'Not available'): string => ($value === null || trim($value) === '') ? $fallback : $value;
$showLeadStatus = strtolower(trim((string) ($trial['lead_status'] ?? ''))) !== 'admission pending';
$dateDisplay = static function (?string $value): string {
    if ($value === null || trim($value) === '') {
        return 'Not available';
    }

    $date = DateTimeImmutable::createFromFormat('!Y-m-d', substr($value, 0, 10));

    return $date && $date->format('Y-m-d') === substr($value, 0, 10)
        ? $date->format('d-m-Y')
        : 'Not available';
};
$workflowStages = [
    ['key' => 'booked', 'label' => 'Booked', 'icon' => 'fa-calendar-plus'],
    ['key' => 'attended', 'label' => 'Attended', 'icon' => 'fa-clipboard-check'],
    ['key' => 'selected', 'label' => 'Selected', 'icon' => 'fa-user-check'],
];
$workflowCurrent = match (true) {
    ($trial['selection_outcome'] ?? '') === 'selected' => 'selected',
    ($trial['status'] ?? '') === 'attended' => 'attended',
    default => 'booked',
};
$workflowTerminal = in_array($trial['status'] ?? '', ['cancelled', 'no_show', 'rescheduled'], true);
$fieldErrors = session()->getFlashdata('field_errors');
$fieldErrors = is_array($fieldErrors) ? $fieldErrors : [];
$rescheduleDate = old('trial_date');
if ($rescheduleDate === null || $rescheduleDate === '') {
    $rescheduleDate = mcc_user_date_input($trial['trial_date'] ?? null);
}
$rescheduleTime = old('trial_time', substr((string) ($trial['trial_time'] ?? ''), 0, 5));
$rescheduleLocation = old('location', (string) ($trial['location'] ?? ''));
$rescheduleCoach = old('coach_user_id', (string) ($trial['coach_user_id'] ?? ''));
$rescheduleSecondaryCoach = old('secondary_coach_user_id', (string) ($trial['secondary_coach_user_id'] ?? ''));
$rescheduleRemarks = old('remarks', (string) ($trial['remarks'] ?? ''));
$hasAvailableActions = !empty(array_filter([
    $actions['attendance'] ?? false,
    $actions['cancel'] ?? false,
    $actions['reschedule'] ?? false,
    $actions['selection'] ?? false,
]));
?>
<?= view('staff_header', ['title' => 'Trial Details', 'nav' => $nav ?? [], 'message' => $message ?? null, 'error' => $error ?? null]) ?>
<section class="mcc-card mcc-detail-hero" aria-labelledby="trial-title">
<div>
<span class="mcc-eyebrow">Admissions &amp; CRM</span>
<h2 id="trial-title"><i class="fa-solid fa-calendar-check mr-2" aria-hidden="true"></i>Trial for <?= esc($display($trial['full_name'] ?? null, 'Linked lead')) ?>
</h2>
<div class="mcc-detail-badges" aria-label="Trial status">
<span class="mcc-badge mcc-badge-info">
<?= esc($statusLabels[$trial['status'] ?? ''] ?? 'Status unavailable') ?>
</span>
<span class="mcc-badge mcc-badge-neutral">Outcome: <?= esc($outcomeLabels[$trial['selection_outcome'] ?? ''] ?? 'Unavailable') ?>
</span>
</div>
</div>
<div class="mcc-detail-actions">
<a class="mcc-button mcc-button-primary" href="<?= site_url('crm/leads/' . (int) ($trial['lead_id'] ?? 0)) ?>"><i class="fa-solid fa-user mr-1" aria-hidden="true"></i>View lead details</a>
<?php if (! empty($trial['admission_test_id'])): ?>
<a class="mcc-button mcc-button-secondary" href="<?= site_url('admission-tests/' . (int) $trial['admission_test_id']) ?>">View Admission Test</a>
<?php endif; ?>
<a class="mcc-button mcc-button-secondary" href="<?= site_url('crm/trials') ?>"><i class="fa-solid fa-arrow-left mr-1" aria-hidden="true"></i>Back to trials</a>
</div>
</section>
<div class="mcc-detail-grid">
<section class="mcc-card" aria-labelledby="trial-information-heading">
<div class="mcc-card-heading">
<div>
<span class="mcc-eyebrow">Schedule</span>
<h2 id="trial-information-heading"><i class="fa-solid fa-calendar-days mr-2" aria-hidden="true"></i>Trial information</h2>
</div>
</div>
<dl class="mcc-detail-list">
<dt>Trial date</dt>
<dd>
<?= esc($dateDisplay($trial['trial_date'] ?? null)) ?>
</dd>
<dt>Trial time</dt>
<dd>
<?= esc(isset($trial['trial_time']) ? substr((string) $trial['trial_time'], 0, 5) : 'Not available') ?>
</dd>
<dt>Location / ground</dt>
<dd>
<?= esc($display($trial['location'] ?? null)) ?>
</dd>
<dt>Branch</dt>
<dd>
<?= esc($display($trial['branch_name'] ?? null)) ?>
</dd>
<dt>Batch / session</dt>
<dd>Not linked</dd>
<dt>Primary Coach / Evaluator</dt>
<dd>
<?= esc($display($trial['coach_name'] ?? null)) ?>
</dd>
<dt>Secondary Coach / Evaluator</dt>
<dd>
<?= esc($display($trial['secondary_coach_name'] ?? null, 'Not assigned')) ?>
</dd>
<dt>Created</dt>
<dd>
<?= esc(mcc_user_datetime($trial['created_at'] ?? null)) ?>
</dd>
<dt>Last updated</dt>
<dd>
<?= esc(mcc_user_datetime($trial['updated_at'] ?? null)) ?>
</dd>
</dl>
</section>
<section class="mcc-card" aria-labelledby="lead-information-heading">
<div class="mcc-card-heading">
<div>
<span class="mcc-eyebrow">Linked record</span>
<h2 id="lead-information-heading"><i class="fa-solid fa-user mr-2" aria-hidden="true"></i>Lead information</h2>
</div>
</div>
<dl class="mcc-detail-list">
<dt>Name</dt>
<dd>
<?= esc($display($trial['full_name'] ?? null)) ?>
</dd>
<dt>Email</dt>
<dd>
<?php if (! empty($trial['email'])): ?>
<a href="mailto:<?= esc($trial['email']) ?>">
<?= esc($trial['email']) ?>
</a>
<?php else: ?>Not available<?php endif; ?>
</dd>
<dt>Mobile</dt>
<dd>
<?php if (! empty($trial['phone'])): ?>
<a href="tel:<?= esc($trial['phone']) ?>">
<?= esc($trial['phone']) ?>
</a>
<?php else: ?>Not available<?php endif; ?>
</dd>
<?php if ($showLeadStatus): ?>
<dt>Lead status</dt>
<dd>
<?= esc($display($trial['lead_status'] ?? null)) ?>
</dd>
<?php endif; ?>
<dt>Lead source</dt>
<dd>
<?= esc($display($trial['lead_source'] ?? null)) ?>
</dd>
<dt>Assigned staff</dt>
<dd>
<?= esc($display($trial['owner_name'] ?? null)) ?>
</dd>
<dt>Branch</dt>
<dd>
<?= esc($display($trial['branch_name'] ?? null)) ?>
</dd>
</dl>
</section>
</div>
<?php if (! empty($actions['manage']) || ! empty($actions['secondary_assignment'])): ?>
<section class="mcc-card mcc-detail-card" aria-labelledby="trial-actions-heading">
<div class="mcc-card-heading">
<div>
<span class="mcc-eyebrow">Workflow</span>
<h2 id="trial-actions-heading"><i class="fa-solid fa-sliders mr-2" aria-hidden="true"></i>Trial actions</h2>
</div>
</div>
<div class="mcc-trial-workflow" aria-label="Trial workflow progression">
<ol class="mcc-trial-workflow-steps">
<?php foreach ($workflowStages as $stage): ?>
<?php $stageIndex = array_search($stage['key'], array_column($workflowStages, 'key'), true); ?>
<?php $currentIndex = array_search($workflowCurrent, array_column($workflowStages, 'key'), true); ?>
<?php $stageState = $stageIndex < $currentIndex ? 'completed' : ($stageIndex === $currentIndex ? 'current' : 'upcoming'); ?>
<li class="mcc-trial-workflow-step is-<?= $stageState ?>">
<span class="mcc-trial-workflow-icon" aria-hidden="true"><i class="fa-solid <?= esc($stage['icon']) ?>"></i></span>
<span class="mcc-trial-workflow-label"><?= esc($stage['label']) ?></span>
<?php if ($stageState === 'current'): ?><span class="mcc-badge mcc-badge-info">Current</span><?php endif; ?>
<?php if ($stageState === 'completed'): ?><span class="mcc-badge mcc-badge-success">Completed</span><?php endif; ?>
</li>
<?php endforeach; ?>
</ol>
<?php if ($workflowTerminal): ?>
<p class="mcc-trial-workflow-terminal">
<i class="fa-solid fa-circle-exclamation mr-1" aria-hidden="true"></i>
This trial is <?= esc($statusLabels[$trial['status']] ?? 'closed') ?> and has no further workflow actions.
</p>
<?php endif; ?>
</div>
<?php if ($hasAvailableActions): ?>
<div class="mcc-trial-action-area">
<div class="mcc-card-heading">
<div>
<span class="mcc-eyebrow">Available actions</span>
<h3>Manage this trial</h3>
</div>
</div>
<?php if (! empty($actions['attendance'])): ?>
<form method="post" action="<?= site_url('crm/trials/' . (int) $trial['id'] . '/attendance') ?>" class="mt-3 mcc-trial-workflow-panel">
<div class="row mb-2">
<div class="col-12">
<h3>Record Attendance</h3>
</div>
</div>
<?= csrf_field() ?>
<div class="row">
<div class="col-12 mt-2 text-right">
<div class="mcc-form-actions">
<button class="mcc-button mcc-button-secondary" name="outcome" value="attended" type="submit"><i class="fa-solid fa-clipboard-check" aria-hidden="true"></i><span class="mcc-button-label">Attended</span></button>
<button class="mcc-button mcc-button-secondary" name="outcome" value="no_show" type="submit"><i class="fa-solid fa-circle-xmark" aria-hidden="true"></i>No show</button>
</div>
</div>
</div>
</form>
<?php endif; ?>
<?php if (! empty($actions['cancel'])): ?>
<form method="post" action="<?= site_url('crm/trials/' . (int) $trial['id'] . '/cancel') ?>" class="mt-3 mcc-trial-workflow-panel">
<h3>Cancel trial</h3>
<?= csrf_field() ?>
<div class="row">
<div class="col-12 mb-2">
<label for="cancel-reason">Cancellation reason</label>
<input class="form-control" id="cancel-reason" name="reason" maxlength="255" required>
</div>
<div class="col-12 mt-2 text-right">
<div class="mcc-form-actions">
<button class="mcc-button mcc-button-danger" type="submit"><i class="fa-solid fa-ban" aria-hidden="true"></i>Cancel trial</button>
</div>
</div>
</div>
</form>
<?php endif; ?>
<?php if (! empty($actions['reschedule'])): ?>
<form method="post" action="<?= site_url('crm/trials/' . (int) $trial['id'] . '/reschedule') ?>" class="mt-3 mcc-trial-workflow-panel">
<h3>Reschedule trial</h3>
<?= csrf_field() ?>
<div class="row">
<div class="col-12 col-md-4 mb-2">
<label for="reschedule-date">Trial date</label>
<input class="form-control"
                    id="reschedule-date"
                    name="trial_date"
                    type="text"
                    inputmode="numeric"
                    placeholder="DD-MM-YYYY"
                    data-mcc-date-format="d-m-Y"
                    data-mcc-date-picker
                    value="<?= esc((string) $rescheduleDate) ?>"
                    required
                >
<?php if (isset($fieldErrors['trial_date'])): ?>
<p class="mcc-field-error" role="alert"><?= esc($fieldErrors['trial_date']) ?></p>
<?php endif; ?>
</div>
<div class="col-12 col-md-4 mb-2">
<label for="reschedule-time">Trial time</label>
<input class="form-control" id="reschedule-time" name="trial_time" type="time" value="<?= esc((string) $rescheduleTime) ?>" required>
<?php if (isset($fieldErrors['trial_time'])): ?>
<p class="mcc-field-error" role="alert"><?= esc($fieldErrors['trial_time']) ?></p>
<?php endif; ?>
</div>
<div class="col-12 col-md-4 mb-2">
<label for="reschedule-location">Location / ground <span class="mcc-muted">(optional)</span></label>
<select class="form-control" id="reschedule-location" name="location">
<option value="">Not selected</option>
<?php foreach (($locations ?? []) as $location): ?>
<?php $locationName = (string) ($location['name'] ?? ''); ?>
<option value="<?= esc($locationName) ?>" <?= (string) $rescheduleLocation === $locationName ? 'selected' : '' ?>>
<?= esc($locationName) ?>
</option>
<?php endforeach; ?>
</select>
<?php if (isset($fieldErrors['location'])): ?>
<p class="mcc-field-error" role="alert"><?= esc($fieldErrors['location']) ?></p>
<?php endif; ?>
</div>
<div class="col-12 col-md-4 mb-2">
<label for="reschedule-coach">Primary Coach / Evaluator</label>
<select class="form-control" id="reschedule-coach" name="coach_user_id">
<option value="">Not assigned</option>
<?php foreach (($coaches ?? []) as $coach): ?>
<option value="<?= (int) $coach['id'] ?>" <?= (string) $rescheduleCoach === (string) $coach['id'] ? 'selected' : '' ?>>
<?= esc($coach['display_name']) ?>
</option>
<?php endforeach; ?>
</select>
<?php if (isset($fieldErrors['coach_user_id'])): ?>
<p class="mcc-field-error" role="alert"><?= esc($fieldErrors['coach_user_id']) ?></p>
<?php endif; ?>
</div>
<div class="col-12 col-md-4 mb-2">
<label for="reschedule-secondary-coach">Secondary Coach / Evaluator</label>
<select class="form-control" id="reschedule-secondary-coach" name="secondary_coach_user_id">
<option value="">Not assigned</option>
<?php foreach (($coaches ?? []) as $coach): ?>
<option value="<?= (int) $coach['id'] ?>" <?= (string) $rescheduleSecondaryCoach === (string) $coach['id'] ? 'selected' : '' ?>>
<?= esc($coach['display_name']) ?>
</option>
<?php endforeach; ?>
</select>
<?php if (isset($fieldErrors['secondary_coach_user_id'])): ?>
<p class="mcc-field-error" role="alert"><?= esc($fieldErrors['secondary_coach_user_id']) ?></p>
<?php endif; ?>
</div>
<div class="col-12 col-md-4 mb-2">
<label for="reschedule-remarks">Remarks</label>
<textarea class="form-control" id="reschedule-remarks" name="remarks" maxlength="65535"><?= esc((string) $rescheduleRemarks) ?></textarea>
<?php if (isset($fieldErrors['remarks'])): ?>
<p class="mcc-field-error" role="alert"><?= esc($fieldErrors['remarks']) ?></p>
<?php endif; ?>
</div>
</div>
<div class="row">
<div class="col-12 mt-2 text-right">
<div class="mcc-form-actions">
<button class="mcc-button mcc-button-secondary" type="submit"><i class="fa-solid fa-calendar-days" aria-hidden="true"></i>Reschedule trial</button>
</div>
</div>
</div>
</form>
<?php endif; ?>
<?php if (! empty($actions['selection'])): ?>
<form method="post" action="<?= site_url('crm/trials/' . (int) $trial['id'] . '/selection') ?>" class="mt-3 mcc-trial-workflow-panel">
<h3>Selection outcome</h3>
<?= csrf_field() ?>
<div class="row">
<div class="col-12 mt-2 text-right">
<div class="mcc-form-actions">
<button class="mcc-button mcc-button-primary" name="outcome" value="selected" type="submit"><i class="fa-solid fa-user-check" aria-hidden="true"></i>Selected</button>
<button class="mcc-button mcc-button-secondary" name="outcome" value="not_selected" type="submit"><i class="fa-solid fa-user-xmark" aria-hidden="true"></i>Not selected</button>
</div>
</div>
</div>
</form>
<?php endif; ?>
</div>
<?php else: ?>
<div class="mcc-trial-action-area">
<div class="mcc-card-heading">
<div>
<span class="mcc-eyebrow">Available actions</span>
<h3>No actions available</h3>
</div>
</div>
<p class="mcc-empty-state mb-0">There are no valid next actions for the current trial state.</p>
</div>
<?php endif; ?>
<form method="post" action="<?= site_url('crm/trials/' . (int) $trial['id'] . '/coaches') ?>" class="mt-3 mcc-trial-workflow-panel">
<h3>Secondary Coach / Evaluator</h3>
<p class="mcc-muted">Primary Coach / Evaluator: <?= esc($display($trial['coach_name'] ?? null, 'Not assigned')) ?></p>
<?= csrf_field() ?>
<input type="hidden" name="expected_updated_at" value="<?= esc((string) ($trial['updated_at'] ?? '')) ?>">
<label for="trial-secondary-coach-correction-reason">Correction reason</label>
<textarea class="form-control mb-2" id="trial-secondary-coach-correction-reason" name="correction_reason" maxlength="255" required><?= esc((string) old('correction_reason', '')) ?></textarea>
<?php if (isset($fieldErrors['correction_reason'])): ?>
<p class="mcc-field-error" role="alert"><?= esc($fieldErrors['correction_reason']) ?></p>
<?php endif; ?>
<div class="row">
<div class="col-12 col-md-6 mb-2">
<label for="trial-secondary-coach-update">Secondary Coach / Evaluator</label>
<select class="form-control" id="trial-secondary-coach-update" name="secondary_coach_user_id">
<option value="">Not assigned</option>
<?php foreach (($coaches ?? []) as $coach): ?>
<option value="<?= (int) $coach['id'] ?>" <?= (string) ($trial['secondary_coach_user_id'] ?? '') === (string) $coach['id'] ? 'selected' : '' ?>>
<?= esc($coach['display_name']) ?>
</option>
<?php endforeach; ?>
</select>
<?php if (isset($fieldErrors['secondary_coach_user_id'])): ?>
<p class="mcc-field-error" role="alert"><?= esc($fieldErrors['secondary_coach_user_id']) ?></p>
<?php endif; ?>
</div>
<div class="col-12 mt-2 text-right">
<div class="mcc-form-actions">
<button class="mcc-button mcc-button-secondary" type="submit">Update secondary assignment</button>
</div>
</div>
</div>
</form>
</section>
<?php endif; ?>
<section class="mcc-card mcc-detail-card" aria-labelledby="trial-rating-summary-heading" data-can-edit-coach-ratings="<?= $canEditCoachRatings ? '1' : '0' ?>">
<div class="mcc-card-heading">
<div>
<span class="mcc-eyebrow">Coach assessment</span>
<h2 id="trial-rating-summary-heading">Trial ratings</h2>
</div>
</div>
<form method="post" action="<?= site_url('crm/trials/' . (int) $trial['id'] . '/ratings') ?>" class="row mcc-trial-ratings-form">
<?= csrf_field() ?>
<input type="hidden" name="expected_updated_at" value="<?= esc((string) ($trial['updated_at'] ?? '')) ?>">
<?php foreach ([
    'ability_overall' => 'Overall Rating',
    'ability_batting' => 'Batting Rating',
    'ability_bowling' => 'Bowling Rating',
    'ability_wicketkeeping' => 'Wicketkeeping Rating',
    'ability_fitness' => 'Fitness Rating',
] as $field => $label): ?>
<?php $savedRating = is_array($trial['coach_ratings'] ?? null)
    ? ($trial['coach_ratings'][$field] ?? null)
    : ($trial[$field] ?? null); ?>
<?php
$rawSelectedRating = old($field, $savedRating ?? '');
$selectedRating = null;

if (is_scalar($rawSelectedRating) && $rawSelectedRating !== '') {
    try {
        $selectedRating = $ratingService->numeric($rawSelectedRating);
    } catch (\InvalidArgumentException) {
        $selectedRating = null;
    }
}
?>
<div class="col-12 col-md-6 col-lg-4 mb-2">
<label for="trial-<?= esc(str_replace('_', '-', $field)) ?>"><?= esc($label) ?></label>
<?php if ($canEditCoachRatings): ?>
<select class="form-control" id="trial-<?= esc(str_replace('_', '-', $field)) ?>" name="<?= esc($field) ?>">
<option value=""<?= $rawSelectedRating === '' || $rawSelectedRating === null ? ' selected' : '' ?>>Not rated</option>
<?php for ($rating = 0; $rating <= 100; $rating++): ?>
<?php $ratingValue = number_format($rating / 10, 1, '.', ''); ?>
<?php $ratingOption = $ratingValue . ' - ' . $ratingService->category($ratingValue); ?>
<option value="<?= esc($ratingOption) ?>"<?= $selectedRating !== null && (float) $selectedRating === (float) $ratingValue ? ' selected' : '' ?>><?= esc($ratingOption) ?></option>
<?php endfor; ?>
</select>
<?php else: ?>
<p class="mcc-detail-value"><?= esc($ratingService->formatted($savedRating)) ?></p>
<?php endif; ?>
<?php if (isset($fieldErrors[$field])): ?>
<p class="mcc-field-error" role="alert"><?= esc($fieldErrors[$field]) ?></p>
<?php endif; ?>
</div>
<?php endforeach; ?>
<?php if ($canEditCoachRatings): ?>
<div class="col-12 mt-2 text-right">
<div class="mcc-form-actions">
<button class="mcc-button mcc-button-primary" type="submit"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>Save Ratings</button>
</div>
</div>
<?php endif; ?>
</form>
</section>
<section class="mcc-card mcc-detail-card" aria-labelledby="trial-media-heading">
<div class="mcc-card-heading">
<div>
<span class="mcc-eyebrow">Private storage</span>
<h2 id="trial-media-heading"><i class="fa-solid fa-video mr-2" aria-hidden="true"></i>Trial media</h2>
</div>
</div>
<?php if (! empty($actions['media_upload'])): ?>
<form method="post" action="<?= site_url('crm/trials/' . (int) ($trial['id'] ?? 0) . '/media') ?>" enctype="multipart/form-data" class="mcc-trial-media-upload">
<?= csrf_field() ?>
<div class="row align-items-end">
<div class="col-12 col-md-8 mb-2">
<label for="trial-media-file">Video file</label>
<input class="form-control" id="trial-media-file" name="media" type="file" accept="video/mp4,video/webm,video/quicktime,video/x-msvideo" required>
<small class="form-text text-muted">MP4, WebM, MOV, or AVI up to 100 MB. Files remain private.</small>
</div>
<div class="col-12 col-md-4 mb-2 text-md-right">
<button class="mcc-button mcc-button-primary" type="submit"><i class="fa-solid fa-upload mr-1" aria-hidden="true"></i>Upload video</button>
</div>
</div>
</form>
<?php endif; ?>
<?php if (($trial['media'] ?? []) === []): ?>
<p class="mcc-empty-state">No Trial media has been uploaded.</p>
<?php else: ?>
<div class="table-responsive">
<table class="mcc-table mcc-trial-media-table">
<caption class="visually-hidden">Private Trial media history</caption>
<thead>
<tr>
<th scope="col">File</th>
<th scope="col">Type</th>
<th scope="col">Size</th>
<th scope="col">Uploaded</th>
<th scope="col">Uploader</th>
<th scope="col">Status</th>
<th scope="col">Actions</th>
</tr>
</thead>
<tbody>
<?php foreach (($trial['media'] ?? []) as $media): ?>
<tr>
<th scope="row" class="mcc-trial-media-file-name">
<?= esc((string) ($media['original_name'] ?? 'Not available')) ?>
</th>
<td><?= esc((string) ($media['mime_type'] ?? 'Not available')) ?></td>
<td><?= esc(number_format(((int) ($media['file_size'] ?? 0)) / 1048576, 2) . ' MB') ?></td>
<td><?= esc(mcc_user_datetime($media['created_at'] ?? null, 'Not available')) ?></td>
<td><?= esc((string) ($media['uploader_name'] ?? 'Not available')) ?></td>
<td><span class="mcc-badge mcc-badge-neutral"><?= esc(ucfirst((string) ($media['status'] ?? 'unknown'))) ?></span></td>
<td class="mcc-trial-media-actions-cell">
<div class="mcc-trial-media-actions" aria-label="Media actions">
<a
    class="mcc-button mcc-button-secondary"
    href="<?= site_url('crm/trials/' . (int) ($trial['id'] ?? 0) . '/media/' . (int) ($media['id'] ?? 0) . '/preview') ?>"
    target="_blank"
    rel="noopener"
>
<i class="fa-solid fa-eye" aria-hidden="true"></i>
<span class="mcc-button-label">Preview</span>
</a>
<a
    class="mcc-button mcc-button-secondary"
    href="<?= site_url('crm/trials/' . (int) ($trial['id'] ?? 0) . '/media/' . (int) ($media['id'] ?? 0) . '/download') ?>"
>
<i class="fa-solid fa-download" aria-hidden="true"></i>
<span class="mcc-button-label">Download</span>
</a>
</div>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php endif; ?>
</section>
<section class="mcc-card mcc-detail-card" aria-labelledby="history-heading">
<div class="mcc-card-heading">
<div>
<span class="mcc-eyebrow">Audit trail</span>
<h2 id="history-heading"><i class="fa-solid fa-clock-rotate-left mr-2" aria-hidden="true"></i>Trial timeline</h2>
</div>
</div>
<?php if (($trial['history'] ?? []) === []): ?>
<p class="mcc-empty-state">No trial history is available.</p>
<?php else: ?>
<ol class="mcc-history">
<?php foreach ($trial['history'] as $event): ?>
<li>
<strong>
<?= esc($eventLabels[$event['event_type'] ?? ''] ?? 'Trial event') ?>
</strong>
<?php if (! empty($event['actor_name'])): ?>
<span>By <?= esc($event['actor_name']) ?>
</span>
<?php endif; ?>
<?php if (! empty($event['reason'])): ?>
<span>
<?= esc($event['reason']) ?>
</span>
<?php endif; ?>
<span class="mcc-history-meta">
<?= esc(mcc_user_datetime($event['created_at'] ?? null, 'Timestamp unavailable')) ?>
</span>
</li>
<?php endforeach; ?>
</ol>
<?php endif; ?>
</section>
<?= view('staff_footer') ?>
