<?php
$filters = is_array($filters ?? null) ? $filters : [];
$options = is_array($options ?? null) ? $options : ['types' => [], 'statuses' => [], 'outcomes' => []];
$branches = is_array($branches ?? null) ? $branches : [];
$followUps = is_array($followUps ?? null) ? $followUps : [];
$statusCounts = is_array($statusCounts ?? null) ? $statusCounts : [];
$queryFilters = array_filter($filters, static fn ($value): bool => $value !== null && $value !== '');
$pageUrl = static function (int $page) use ($queryFilters): string {
    $query = http_build_query(array_merge($queryFilters, ['page' => $page]));
    return site_url('crm/follow-ups') . ($query === '' ? '' : '?' . $query);
};
$display = static fn ($value): string => $value === null || $value === '' ? 'Ã¢â‚¬â€' : esc((string) $value);
?>
<?= view('staff_header', ['title' => 'Follow-Ups', 'nav' => $nav, 'message' => $message, 'error' => $error, 'pageActions' => '<div class="mcc-page-actions">
<button class="mcc-button mcc-button-secondary" type="button" data-filter-toggle aria-controls="follow-up-filters" aria-expanded="false">Filters</button>
</div>']) ?>
<section
    id="follow-up-filters"
    class="mcc-card mcc-follow-up-filter-card"
    aria-labelledby="follow-up-filters-heading"
    hidden
>
    <div class="mcc-card-heading">
        <div>
            <span class="mcc-eyebrow">Search and filter</span>
            <h2 id="follow-up-filters-heading">Follow-Up filters</h2>
            <p class="mcc-follow-up-filter-help">Narrow the history by lead, status, type, outcome, schedule, or branch.</p>
        </div>
    </div>
    <form
        method="get"
        action="<?= site_url('crm/follow-ups') ?>"
        class="mcc-follow-up-filter-form"
    >
        <div class="mcc-form-field mcc-follow-up-filter-field">
            <label for="follow-up-lead">Lead</label>
            <input
                id="follow-up-lead"
                name="lead"
                type="search"
                maxlength="100"
                value="<?= esc($filters['lead'] ?? '') ?>"
            >
        </div>
        <div class="mcc-form-field mcc-follow-up-filter-field">
            <label for="follow-up-status">Status</label>
            <select id="follow-up-status" name="status">
                <option value="">All statuses</option>
                <?php foreach ($options['statuses'] as $status): ?>
                    <option
                        value="<?= esc($status) ?>"
                        <?= ($filters['status'] ?? '') === $status ? 'selected' : '' ?>
                    >
                        <?= esc($status) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mcc-form-field mcc-follow-up-filter-field">
            <label for="follow-up-type">Follow-Up type</label>
            <select id="follow-up-type" name="follow_up_type">
                <option value="">All types</option>
                <?php foreach ($options['types'] as $type): ?>
                    <option
                        value="<?= esc($type) ?>"
                        <?= ($filters['follow_up_type'] ?? '') === $type ? 'selected' : '' ?>
                    >
                        <?= esc($type) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mcc-form-field mcc-follow-up-filter-field">
            <label for="follow-up-outcome">Outcome</label>
            <select id="follow-up-outcome" name="outcome">
                <option value="">All outcomes</option>
                <?php foreach ($options['outcomes'] as $outcome): ?>
                    <option
                        value="<?= esc($outcome) ?>"
                        <?= ($filters['outcome'] ?? '') === $outcome ? 'selected' : '' ?>
                    >
                        <?= esc($outcome) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mcc-form-field mcc-follow-up-filter-field">
            <label for="scheduled-from">Scheduled from</label>
            <input
                id="scheduled-from"
                name="scheduled_from"
                type="text"
                inputmode="numeric"
                placeholder="DD-MM-YYYY"
                autocomplete="off"
                data-mcc-date-picker
                data-mcc-date-picker-no-toggle
                data-mcc-date-format="d-m-Y"
                data-mcc-date-submit-format="Y-m-d"
                value="<?= esc($filters['scheduled_from'] ?? '') ?>"
            >
        </div>
        <div class="mcc-form-field mcc-follow-up-filter-field">
            <label for="scheduled-to">Scheduled to</label>
            <input
                id="scheduled-to"
                name="scheduled_to"
                type="text"
                inputmode="numeric"
                placeholder="DD-MM-YYYY"
                autocomplete="off"
                data-mcc-date-picker
                data-mcc-date-picker-no-toggle
                data-mcc-date-format="d-m-Y"
                data-mcc-date-submit-format="Y-m-d"
                value="<?= esc($filters['scheduled_to'] ?? '') ?>"
            >
        </div>
        <div class="mcc-form-field mcc-follow-up-filter-field">
            <label for="follow-up-branch">Branch</label>
            <select id="follow-up-branch" name="branch_id">
                <option value="">All authorized branches</option>
                <?php foreach ($branches as $branch): ?>
                    <option
                        value="<?= (int) $branch['id'] ?>"
                        <?= (string) ($filters['branch_id'] ?? '') === (string) $branch['id'] ? 'selected' : '' ?>
                    >
                        <?= esc($branch['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mcc-follow-up-filter-actions">
            <button class="mcc-button mcc-button-primary" type="submit">Apply filters</button>
            <a class="mcc-button mcc-button-secondary" href="<?= site_url('crm/follow-ups') ?>">Clear filters</a>
        </div>
    </form>
</section>
<section class="mcc-follow-up-summary-grid" aria-label="Follow-up status totals">
<?php foreach ([
    ['key' => 'all', 'label' => 'All', 'class' => 'mcc-follow-up-status-all'],
    ['key' => 'pending', 'label' => 'Pending', 'class' => 'mcc-follow-up-status-pending'],
    ['key' => 'scheduled', 'label' => 'Scheduled', 'class' => 'mcc-follow-up-status-scheduled'],
    ['key' => 'completed', 'label' => 'Completed', 'class' => 'mcc-follow-up-status-completed'],
    ['key' => 'cancelled', 'label' => 'Cancelled', 'class' => 'mcc-follow-up-status-cancelled'],
    ['key' => 'overdue', 'label' => 'Overdue', 'class' => 'mcc-follow-up-status-overdue'],
] as $card): ?>
<div class="mcc-follow-up-summary-card mcc-status-badge <?= esc($card['class']) ?>">
<span class="mcc-follow-up-summary-label"><?= esc($card['label']) ?></span>
<strong class="mcc-follow-up-summary-count"><?= (int) ($statusCounts[$card['key']] ?? 0) ?></strong>
</div>
<?php endforeach; ?>
</section>
<section class="mcc-card mcc-detail-card" aria-labelledby="follow-ups-heading">
<div class="mcc-card-heading">
<div>
<span class="mcc-eyebrow">CRM</span>
<h2 id="follow-ups-heading">Follow-Up history</h2>
</div>
<span class="mcc-muted">
<?= (int) $total ?> record<?= (int) $total === 1 ? '' : 's' ?>
</span>
</div>
<div class="mcc-table-wrap">
<table class="mcc-table mcc-follow-up-table">
<caption class="visually-hidden">Branch-scoped lead follow-ups</caption>
<thead>
<tr>
<th><i class="fa-solid fa-user" aria-hidden="true"></i> Lead</th>
<th><i class="fa-solid fa-tag" aria-hidden="true"></i> Type</th>
<th><i class="fa-solid fa-calendar-days" aria-hidden="true"></i> Scheduled</th>
<th><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Status</th>
<th>Outcome</th>
<th><i class="fa-solid fa-user-pen" aria-hidden="true"></i> User / owner</th>
<th><i class="fa-solid fa-note-sticky" aria-hidden="true"></i> Notes</th>
<th>Created</th>
<th>Completed / cancelled</th>
<th>Actions</th>
</tr>
</thead>
<tbody>
<?php foreach ($followUps as $followUp): ?>
<?php $statusPresentation = mcc_follow_up_status_presentation($followUp['status'] ?? null, $followUp['next_follow_up_at'] ?? null); ?>
<tr>
<th scope="row">
<a class="mcc-lead-name" href="<?= site_url('crm/leads/' . (int) $followUp['lead_id']) ?>">
<?= esc((string) $followUp['lead_name']) ?>
</a>
<span class="mcc-lead-contact">
<?= esc((string) ($followUp['branch_name'] ?? '')) ?>
</span>
</th>
<td class="mcc-follow-up-type">
<?= $display($followUp['follow_up_type']) ?>
</td>
<td class="mcc-follow-up-date">
<time datetime="<?= esc((string) ($followUp['follow_up_at'] ?? '')) ?>">
<?= esc(mcc_follow_up_datetime($followUp['follow_up_at'] ?? null, 'Not scheduled')) ?>
</time>
</td>
<td>
<span class="mcc-badge mcc-status-badge <?= esc($statusPresentation['class']) ?>">
<?= esc($statusPresentation['label']) ?>
</span>
</td>
<td>
<?= $display($followUp['outcome']) ?>
</td>
<td class="mcc-follow-up-user">
<?= $display($followUp['actor_name']) ?>
</td>
<td class="mcc-follow-up-note">
<?= $display($followUp['note']) ?>
</td>
<td>
<time datetime="<?= esc((string) ($followUp['created_at'] ?? '')) ?>">
<?= esc(mcc_user_datetime($followUp['created_at'] ?? null)) ?>
</time>
</td>
<td>
<time datetime="<?= esc((string) ($followUp['completed_at'] ?? $followUp['cancelled_at'] ?? $followUp['updated_at'] ?? '')) ?>">
<?= esc(mcc_user_datetime($followUp['completed_at'] ?? $followUp['cancelled_at'] ?? $followUp['updated_at'] ?? null)) ?>
</time>
</td>
<td>
<a class="mcc-button mcc-button-secondary" href="<?= site_url('crm/leads/' . (int) $followUp['lead_id']) ?>" title="View lead" aria-label="View lead">
<i class="fa-solid fa-eye" aria-hidden="true"></i>
<span>View lead</span>
</a>
</td>
</tr>
<?php endforeach; ?>
<?php if ($followUps === []): ?>
<tr>
<td colspan="10">
<p class="mcc-empty-state mcc-follow-up-empty">
<i class="fa-solid fa-calendar-xmark" aria-hidden="true"></i>
<span>No follow-up history matches the selected filters.</span>
</p>
</td>
</tr>
<?php endif; ?>
</tbody>
</table>
</div>
<?php if ($pages > 1): ?>
<nav class="mcc-pagination" aria-label="Follow-Up pages">
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
