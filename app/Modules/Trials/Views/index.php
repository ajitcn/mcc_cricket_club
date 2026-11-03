<?php
$filters = is_array($filters ?? null) ? $filters : [];
$statusCounts = is_array($statusCounts ?? null) ? $statusCounts : [];
$queryFilters = array_filter($filters, static fn ($value): bool => $value !== null && $value !== '' && $value !== []);
$pageUrl = static function (int $targetPage) use ($queryFilters): string {
    $query = http_build_query(array_merge($queryFilters, ['page' => $targetPage]));
    return site_url('crm/trials') . ($query === '' ? '' : '?' . $query);
};
$formatTrialTime = static function (?string $value): string {
    $time = substr(trim((string) $value), 0, 5);
    $parsed = DateTimeImmutable::createFromFormat('!H:i', $time);

    return $parsed === false ? $time : $parsed->format('h:i A');
};
?>
<?= view('staff_header', ['title' => 'Trials', 'nav' => $nav, 'message' => $message ?? null, 'error' => $error ?? null, 'pageActions' =>
    '<div class="mcc-page-actions">
<button class="mcc-button mcc-button-secondary" type="button" data-filter-toggle aria-controls="trial-filters" aria-expanded="false">Filters</button>
<a class="mcc-button mcc-button-primary" href="' . site_url('crm/leads') . '">Book from a lead</a>
</div>']) ?>
<section id="trial-filters" class="mcc-card" aria-labelledby="trial-filters-heading" hidden>
<div class="mcc-card-heading">
<div>
<span class="mcc-eyebrow">Search and filter</span>
<h2 id="trial-filters-heading">Trial filters</h2>
</div>
</div>
<form method="get" action="<?= site_url('crm/trials') ?>" class="row">
<div class="col-12 col-md-4 mb-2">
<label for="trial-query">Lead name, email or mobile</label>
<input class="form-control" id="trial-query" name="q" type="search" maxlength="100" value="<?= esc($filters['q'] ?? '') ?>">
</div>
<div class="col-12 col-md-4 mb-2">
<label for="trial-status">Status</label>
<select class="form-control" id="trial-status" name="status">
<option value="">All statuses</option>
<?php foreach (['booked'=>'Booked','attended'=>'Attended','no_show'=>'No Show','cancelled'=>'Cancelled','rescheduled'=>'Rescheduled'] as $value => $label): ?>
<option value="<?= $value ?>" <?= ($filters['status'] ?? '') === $value ? 'selected' : '' ?>>
<?= $label ?>
</option>
<?php endforeach; ?>
</select>
</div>
<div class="col-12 col-md-4 mb-2">
<label for="trial-branch">Branch</label>
<select class="form-control" id="trial-branch" name="branch_id">
<option value="">All authorized branches</option>
<?php foreach ($branches as $branch): ?>
<option value="<?= (int) $branch['id'] ?>" <?= (string) ($filters['branch_id'] ?? '') === (string) $branch['id'] ? 'selected' : '' ?>>
<?= esc($branch['name']) ?>
</option>
<?php endforeach; ?>
</select>
</div>
<div class="col-12 col-md-4 mb-2">
<label for="trial-date">Trial date</label>
<input class="form-control" id="trial-date" name="trial_date" type="text" inputmode="numeric" placeholder="DD-MM-YYYY" autocomplete="off" data-mcc-date-picker data-mcc-date-format="d-m-Y"
 value="<?= esc(mcc_user_date_input($filters['trial_date'] ?? null)) ?>">
</div>
<div class="col-12 mt-2 text-right">
<button class="mcc-button mcc-button-primary" type="submit">Apply filters</button>
<a class="mcc-button mcc-button-secondary" href="<?= site_url('crm/trials') ?>">Clear filters</a>
</div>
</form>
</section>
<section class="mcc-follow-up-summary-grid" aria-label="Trial status totals">
<?php foreach ([
    ['key' => 'booked', 'label' => 'Booked', 'class' => 'mcc-badge-info'],
    ['key' => 'attended', 'label' => 'Attended', 'class' => 'mcc-badge-success'],
    ['key' => 'no_show', 'label' => 'No show', 'class' => 'mcc-badge-danger'],
    ['key' => 'cancelled', 'label' => 'Cancelled', 'class' => 'mcc-badge-neutral'],
    ['key' => 'rescheduled', 'label' => 'Rescheduled', 'class' => 'mcc-badge-warning'],
] as $card): ?>
<div class="mcc-follow-up-summary-card mcc-status-badge <?= esc($card['class']) ?>">
<span class="mcc-follow-up-summary-label"><?= esc($card['label']) ?></span>
<strong class="mcc-follow-up-summary-count"><?= (int) ($statusCounts[$card['key']] ?? 0) ?></strong>
</div>
<?php endforeach; ?>
</section>
<section class="mcc-card" aria-labelledby="trials-heading">
<div class="mcc-card-heading">
<div>
<span class="mcc-eyebrow">Admissions &amp; CRM</span>
<h2 id="trials-heading">Trials</h2>
</div>
<span class="mcc-muted">
<?= (int) $total ?> result<?= $total === 1 ? '' : 's' ?>
</span>
</div>
<?php if ($trials === []): ?>
<div class="mcc-empty-state">
<span class="mcc-empty-icon" aria-hidden="true">Ã¢â€”â€¹</span>
<h2>No trials found</h2>
<p>No trials match the current authorized scope and filters.</p>
</div>
<?php else: ?>
<div class="mcc-table-wrap">
<table class="mcc-table">
<caption class="visually-hidden">Branch-scoped trials</caption>
<thead>
<tr>
<th scope="col">Lead</th>
<th scope="col">Date and time</th>
<th scope="col">Location</th>
<th scope="col">Branch</th>
<th scope="col">Status</th>
<th scope="col">Outcome</th>
<th scope="col">Coaches / Evaluators</th>
<th scope="col">Actions</th>
</tr>
</thead>
<tbody>
<?php foreach ($trials as $trial): ?>
<tr>
<th scope="row">
<a href="<?= site_url('crm/trials/' . (int) $trial['id']) ?>">
<?= esc($trial['full_name']) ?>
</a>
<span class="mcc-lead-contact">
<?php if (! empty($trial['email'])): ?>
<a href="mailto:<?= esc($trial['email']) ?>">
<?= esc($trial['email']) ?>
</a>
<?php endif; ?>
<?php if (! empty($trial['phone'])): ?>
<a href="tel:<?= esc($trial['phone']) ?>">
<?= esc($trial['phone']) ?>
</a>
<?php endif; ?>
</span>
</th>
<td>
<?= esc(mcc_user_date($trial['trial_date'] ?? null)) ?>
<br>
<span class="mcc-muted">
<?= esc($formatTrialTime($trial['trial_time'] ?? null)) ?>
</span>
</td>
<td>
<?= esc($trial['location']) ?>
</td>
<td>
<?= esc($trial['branch_name']) ?>
</td>
<td>
<span class="mcc-badge mcc-badge-info">
<?= esc(ucwords(str_replace('_', ' ', $trial['status']))) ?>
</span>
</td>
<td>
<span class="mcc-badge mcc-badge-neutral">
<?= esc(ucwords(str_replace('_', ' ', $trial['selection_outcome']))) ?>
</span>
</td>
<td>
<?= esc($trial['coach_name'] ?? 'Not assigned') ?>
<?php if (! empty($trial['secondary_coach_name'])): ?>
<br><span class="mcc-muted"><?= esc($trial['secondary_coach_name']) ?></span>
<?php endif; ?>
</td>
<td>
<div class="mcc-lead-actions">
<a class="mcc-button mcc-button-secondary mcc-lead-action" href="<?= site_url('crm/trials/' . (int) $trial['id']) ?>" title="View trial" aria-label="View trial">
<i class="fa-solid fa-eye" aria-hidden="true"></i>
<span class="mcc-button-label">View</span>
</a>
<?php if (! empty($canStartAdmissionTest)): ?>
<?php if (! empty($trial['admission_test_completed'])): ?>
<span class="mcc-badge mcc-badge-success" title="Admission Test already completed">Test Completed</span>
<?php if (! empty($trial['admission_test_id'])): ?>
<a class="mcc-button mcc-button-secondary mcc-lead-action" href="<?= site_url('admission-tests/' . (int) $trial['admission_test_id']) ?>" title="View completed Admission Test" aria-label="View completed Admission Test">
<i class="fa-solid fa-eye" aria-hidden="true"></i>
<span class="mcc-button-label">View Test</span>
</a>
<?php endif; ?>
<?php else: ?>
<form method="post" action="<?= site_url('crm/trials/' . (int) $trial['id'] . '/admission-test') ?>" class="mcc-inline-form">
<?= csrf_field() ?>
<button class="mcc-button mcc-button-secondary mcc-lead-action" type="submit" title="Continue Admission Test" aria-label="Continue Admission Test">
<i class="fa-solid fa-clipboard-check" aria-hidden="true"></i>
<span class="mcc-button-label">Continue Admission Test</span>
</button>
</form>
<?php endif; ?>
<?php endif; ?>
<?php if (!empty($canUpdate) && ($trial['status'] ?? '') === 'booked'): ?>
<a class="mcc-button mcc-button-secondary mcc-lead-action" href="<?= site_url('crm/trials/' . (int) $trial['id']) ?>#trial-actions-heading" title="Edit trial" aria-label="Edit trial">
<i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
<span class="mcc-button-label">Edit</span>
</a>
<?php endif; ?>
</div>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php endif; ?>
<?php if ($pages > 1): ?>
<nav class="mcc-pagination" aria-label="Trial pages">
<span>Page <?= (int) $page ?> of <?= (int) $pages ?>
</span>
<div>
<?php if ($page > 1): ?>
<a class="mcc-button mcc-button-secondary" href="<?= esc($pageUrl($page - 1)) ?>">Previous</a>
<?php endif; ?>
<?php for ($pageNumber = 1; $pageNumber <= $pages; $pageNumber++): ?>
<a class="mcc-button <?= $pageNumber === $page ? 'mcc-button-primary' : 'mcc-button-secondary' ?>" href="<?= esc($pageUrl($pageNumber)) ?>" <?=
    $pageNumber === $page ? 'aria-current="page"' : '' ?>>
<?= $pageNumber ?>
</a>
<?php endfor; ?>
<?php if ($page < $pages): ?>
<a class="mcc-button mcc-button-secondary" href="<?= esc($pageUrl($page + 1)) ?>">Next</a>
<?php endif; ?>
</div>
</nav>
<?php endif; ?>
</section>
<?= view('staff_footer') ?>
