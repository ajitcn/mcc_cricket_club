<?php
$filters = is_array($filters ?? null) ? $filters : [];
$queryFilters = array_filter($filters, static fn ($value): bool => $value !== null && $value !== '');
$pageUrl = static function (int $page) use ($queryFilters): string {
    $query = http_build_query(array_merge($queryFilters, ['page' => $page]));
    return site_url('crm/leads') . ($query === '' ? '' : '?' . $query);
};
?>
<?php $hasFilters = count($queryFilters) > 0; ?>
<?php
$pageActions = '<div class="mcc-page-actions">
<button class="mcc-button mcc-button-secondary" type="button" data-filter-toggle aria-controls="lead-filters"
    aria-expanded="' . ($hasFilters ? 'true' : 'false') . '">
<i class="fa-solid fa-filter" aria-hidden="true">
</i> Filters</button>' . (!empty($canCreate) ? '<a class="mcc-button mcc-button-secondary" href="#lead-import-modal" data-lead-import-open aria-controls="lead-import-modal">
<i class="fa-solid fa-file-import" aria-hidden="true">
</i> Import Leads</a><a class="mcc-button mcc-button-primary" href="' . site_url('crm/leads/new') . '">
<i class="fa-solid fa-plus" aria-hidden="true">
</i> Create lead</a>' : '') . '</div>';
?>
<?= view('staff_header', ['title' => 'CRM leads', 'nav' => $nav, 'message' => $message, 'error' => $error, 'pageActions' => $pageActions]) ?>
<section id="lead-filters" class="mcc-card" aria-labelledby="lead-filters-heading" <?= $hasFilters ? '' : 'hidden' ?>>
<div class="mcc-card-heading">
<span class="mcc-eyebrow">Search and filter</span>
<h2 id="lead-filters-heading">Lead filters</h2>
</div>
<form method="get" action="<?= site_url('crm/leads') ?>" class="row">
<div class="col-12 col-md-4 mb-2">
<label for="lead-query">Name, email or mobile</label>
<input class="form-control" id="lead-query" name="q" type="search" maxlength="100" value="<?= esc($filters['q'] ?? '') ?>">
</div>
<div class="col-12 col-md-4 mb-2">
<label for="lead-source">Source</label>
<select class="form-control" id="lead-source" name="source">
<option value="">All sources</option>
<?php foreach ($sources as $source): ?>
<option value="<?= esc($source['name']) ?>" <?= ($filters['source'] ?? '') === $source['name'] ? 'selected' : '' ?>>
<?= esc($source['name']) ?>
</option>
<?php endforeach; ?>
</select>
</div>
<div class="col-12 col-md-4 mb-2">
<label for="lead-filter-status">Status</label>
<select class="form-control" id="lead-filter-status" name="status">
<option value="">All statuses</option>
<?php foreach ($statuses as $status): ?>
<option value="<?= esc($status['name']) ?>" <?= ($filters['status'] ?? '') === $status['name'] ? 'selected' : '' ?>>
<?= esc($status['name']) ?>
</option>
<?php endforeach; ?>
</select>
</div>
<div class="col-12 col-md-4 mb-2">
<label for="lead-branch">Branch</label>
<select class="form-control" id="lead-branch" name="branch_id">
<option value="">All authorized branches</option>
<?php foreach ($branches as $branch): ?>
<option value="<?= (int) $branch['id'] ?>" <?= (string) ($filters['branch_id'] ?? '') === (string) $branch['id'] ? 'selected' : '' ?>>
<?= esc($branch['name']) ?>
</option>
<?php endforeach; ?>
</select>
</div>
<div class="col-12 col-md-4 mb-2">
<label for="created-from">Created from</label>
<input class="form-control" id="created-from" name="created_from" type="text" inputmode="numeric" placeholder="DD-MM-YYYY" autocomplete="off" data-mcc-date-picker data-mcc-date-picker-no-toggle data-mcc-date-format="d-m-Y" data-mcc-date-submit-format="Y-m-d"
    value="<?= esc($filters['created_from'] ?? '') ?>">
</div>
<div class="col-12 col-md-4 mb-2">
<label for="created-to">Created to</label>
<input class="form-control" id="created-to" name="created_to" type="text" inputmode="numeric" placeholder="DD-MM-YYYY" autocomplete="off" data-mcc-date-picker data-mcc-date-picker-no-toggle data-mcc-date-format="d-m-Y" data-mcc-date-submit-format="Y-m-d"
    value="<?= esc($filters['created_to'] ?? '') ?>">
</div>
<div class="col-12 mt-2 text-right">
<button class="mcc-button mcc-button-primary" type="submit">Apply filters</button>
<a class="mcc-button mcc-button-secondary" href="<?= site_url('crm/leads') ?>">Clear filters</a>
</div>
</form>
</section>
<section class="mcc-card mcc-detail-card" aria-labelledby="leads-heading">
<div class="mcc-card-heading">
<div>
<span class="mcc-eyebrow">CRM</span>
<h2 id="leads-heading">Leads</h2>
</div>
<span class="mcc-muted">
<?= (int) $total ?> result<?= (int) $total === 1 ? '' : 's' ?>
</span>
</div>
<div class="mcc-table-wrap">
<table class="mcc-table">
<caption class="visually-hidden">Branch-scoped leads</caption>
<thead>
<tr>
<th>Lead Name</th>
<th>Source</th>
<th>Status</th>
<th>Branch</th>
<th>Assigned To</th>
<th>Relationships</th>
<th>Actions</th>
</tr>
</thead>
<tbody>
<?php foreach ($leads as $lead): ?>
<?php
$followUpCount = (int) ($lead['follow_up_count'] ?? 0);
$trialCount = (int) ($lead['trial_count'] ?? 0);
$admissionCount = (int) ($lead['admission_count'] ?? 0);
$relationshipCount = $followUpCount + $trialCount + $admissionCount;
?>
<tr>
<th class="mcc-lead-name-cell" scope="row">
<a class="mcc-lead-name" href="<?= site_url('crm/leads/' . $lead['id']) ?>"
    title="<?= esc((string) $lead['full_name']) ?>">
<?= esc($lead['full_name']) ?>
</a>
<span class="mcc-lead-contact">
<?php if ($lead['email']): ?>
<a href="mailto:<?= esc($lead['email']) ?>">
<?= esc($lead['email']) ?>
</a>
<?php endif; ?>
<?php if ($lead['phone']): ?>
<a href="tel:<?= esc($lead['phone']) ?>">
<?= esc($lead['phone']) ?>
</a>
<?php endif; ?>
</span>
</th>
<td>
<span class="mcc-badge mcc-badge-info">
<?= esc($lead['source']) ?>
</span>
</td>
<td>
<span class="mcc-badge mcc-badge-neutral">
<?= esc(ucwords(str_replace('_', ' ', (string) $lead['status']))) ?>
</span>
</td>
<td>
<?= esc($lead['branch_name'] ?? 'Authorized branch') ?>
</td>
<td>
<?= esc($lead['owner_name'] ?? '—') ?>
</td>
<td class="mcc-lead-relationships">
<?php if ($relationshipCount === 0): ?>
<span class="mcc-muted">—</span>
<?php else: ?>
<ul class="mcc-lead-relationship-list">
<li>
<span>Follow-ups</span>
<strong><?= $followUpCount ?></strong>
</li>
<li>
<span>Trials</span>
<strong><?= $trialCount ?></strong>
</li>
<li>
<span>Admissions</span>
<strong><?= $admissionCount ?></strong>
</li>
</ul>
<?php endif; ?>
<!-- relationship-data -->
<!--
<span class="mcc-muted">
<?= (int) ($lead['follow_up_count'] ?? 0) ?> follow-up<?= (int) ($lead['follow_up_count'] ?? 0) === 1 ? '' : 's' ?> Ã‚Â· <?= (int) ($lead['trial_count'] ??
    0) ?> trial<?= (int) ($lead['trial_count'] ?? 0) === 1 ? '' : 's' ?> Ã‚Â· <?= (int) ($lead['admission_count'] ?? 0) ?> admission<?= (int)
    ($lead['admission_count'] ?? 0) === 1 ? '' : 's' ?>
</span>
-->
</td>
<td>
<div class="mcc-lead-actions">
<a class="mcc-button mcc-button-secondary mcc-lead-action" href="<?= site_url('crm/leads/' . $lead['id']) ?>" title="View lead" aria-label="View lead">
<i class="fa-solid fa-eye" aria-hidden="true"></i>
<span class="mcc-button-label">View</span>
</a>
<?php if (!empty($canUpdate)): ?>
<a class="mcc-button mcc-button-secondary mcc-lead-action" href="<?= site_url('crm/leads/' . $lead['id'] . '/edit') ?>" title="Edit lead" aria-label="Edit lead">
<i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
<span class="mcc-button-label">Edit</span>
</a>
<?php endif; ?>
<?php if (! empty($lead['can_book_trial']) && ! empty($canBookTrial)): ?>
<a class="mcc-button mcc-button-primary mcc-lead-action" href="<?= site_url('crm/trials/from-lead/' . (int) $lead['id']) ?>" title="Book trial" aria-label="Book trial">
<i class="fa-solid fa-calendar-plus" aria-hidden="true"></i>
<span class="mcc-button-label">Book Trial</span>
</a>
<?php endif; ?>
</div>
</td>
</tr>
<?php endforeach; ?>
<?php if ($leads === []): ?>
<tr>
<td colspan="7">
<p class="mcc-empty-state">No leads match the selected filters.</p>
<a class="mcc-button mcc-button-secondary" href="<?= site_url('crm/leads') ?>">Clear filters</a>
</td>
</tr>
<?php endif; ?>
</tbody>
</table>
</div>
<?php if ($pages > 1): ?>
<nav class="mcc-pagination" aria-label="Lead pages">
<span>Page <?= (int) $page ?> of <?= (int) $pages ?>
</span>
<div>
<?php if ($page > 1): ?>
<a class="mcc-button mcc-button-secondary" href="<?= esc($pageUrl($page - 1)) ?>">Previous</a>
<?php endif; ?>
<?php for ($pageNumber = 1; $pageNumber <= $pages; $pageNumber++): ?>
<a class="mcc-button mcc-button-secondary" href="<?= esc($pageUrl($pageNumber)) ?>" <?= $pageNumber === $page ? 'aria-current="page"' : '' ?>>
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
<?php if (!empty($canCreate)): ?>
<div class="mcc-modal" id="lead-import-modal" data-lead-import-modal hidden>
<div class="mcc-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="lead-import-heading">
<div class="mcc-modal-heading">
<div>
<span class="mcc-eyebrow">CRM</span>
<h2 id="lead-import-heading">Import Leads</h2>
</div>
<button class="mcc-icon-button" type="button" data-lead-import-close aria-label="Close import dialog">
<i class="fa-solid fa-xmark" aria-hidden="true"></i>
</button>
</div>
<p class="mcc-modal-instruction">
Import leads from a CSV file using the supported headers below. Branch values must match an authorized branch name or code, and assigned users must be active users in the system.
</p>
<form class="mcc-import-form" method="post" action="<?= site_url('crm/leads/import') ?>" enctype="multipart/form-data" data-lead-import-form>
<?= csrf_field() ?>
<label for="lead-import-file">Lead file</label>
<input class="form-control" id="lead-import-file" name="lead_import_file" type="file" accept=".csv,text/csv" data-lead-import-file required>
<p class="mcc-help">CSV only, maximum 5 MB. Required headers: Name, Branch. Optional headers: Phone, Email, Source, Status, Assigned To, Notes, Date of Birth.</p>
<a class="mcc-button mcc-button-secondary" href="<?= site_url('crm/leads/import/sample') ?>" download="lead-import-sample.csv">
<i class="fa-solid fa-download" aria-hidden="true"></i>
<span class="mcc-button-label">Download Sample CSV</span>
</a>
<p class="mcc-alert mcc-alert-danger mcc-import-result" data-lead-import-validation role="alert" hidden>
<i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
<span data-lead-import-validation-message></span>
</p>
<p class="mcc-alert mcc-alert-success mcc-import-result" data-lead-import-success role="status" hidden>
<i class="fa-solid fa-circle-check" aria-hidden="true"></i>
<span data-lead-import-success-message></span>
</p>
<p class="mcc-alert mcc-alert-warning mcc-import-result" data-lead-import-warning role="status" hidden>
<i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
<span data-lead-import-warning-message></span>
</p>
<p class="mcc-alert mcc-alert-danger mcc-import-result" data-lead-import-error role="alert" hidden>
<i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
<span data-lead-import-error-message></span>
</p>
<div class="mcc-modal-actions">
<button class="mcc-button mcc-button-primary" type="submit" data-lead-import-submit>
<i class="fa-solid fa-file-import" aria-hidden="true"></i>
<span class="mcc-button-label">Import</span>
</button>
<button class="mcc-button mcc-button-secondary" type="button" data-lead-import-close>
Cancel
</button>
</div>
</form>
</div>
</div>
<?php endif; ?>
<?= view('staff_footer') ?>
