<?= view('staff_header', ['title' => 'Branches', 'nav' => $nav, 'message' => $message ?? null, 'error' => $error ?? null]) ?>
<section class="mcc-card" aria-labelledby="branches-heading">
<div class="mcc-card-heading">
<span class="mcc-eyebrow">Administration</span>
<h2 id="branches-heading">Branches</h2>
<a class="mcc-button mcc-button-primary" href="<?= site_url('admin/branches/create') ?>">Create branch</a>
</div>
<form method="get" class="row">
<label>Search <input class="form-control" name="q" value="<?= esc($filters['q'] ?? '') ?>"></label>
<label>Status
<select class="form-control" name="status">
<option value="">All</option>
<option value="active" <?= ($filters['status'] ?? '') === 'active' ? 'selected' : '' ?>>Active</option>
<option value="inactive" <?= ($filters['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
</select>
</label>
<button type="submit" class="mcc-button mcc-button-secondary">Filter</button>
</form>
<div class="mcc-table-wrap">
<table class="mcc-table">
<caption class="visually-hidden">Organization branches</caption>
<thead>
<tr>
<th>Name</th>
<th>Code</th>
<th>Address</th>
<th>Status</th>
<th>Actions</th>
</tr>
</thead>
<tbody>
<?php foreach ($branches as $branch): ?>
<tr>
<td>
<?= esc($branch['name']) ?>
</td>
<td>
<?= esc($branch['code']) ?>
</td>
<td>
<?= esc($branch['address'] ?? '') ?>
</td>
<td>
<span class="mcc-badge <?= (int) $branch['is_active'] === 1 ? 'mcc-badge-success' : 'mcc-badge-muted' ?>">
<?= (int) $branch['is_active'] === 1 ? 'Active' : 'Inactive' ?>
</span>
</td>
<td>
<a href="<?= site_url('admin/branches/' . (int) $branch['id']) ?>">View</a>
<a href="<?= site_url('admin/branches/' . (int) $branch['id'] . '/edit') ?>">Edit</a>
<form class="d-inline" method="post" action="<?= site_url('admin/branches/' . (int) $branch['id'] . '/status') ?>"
    onsubmit="return confirm('<?= (int) $branch['is_active'] === 1 ? 'Deactivate' : 'Reactivate' ?> this branch?');">
<?= csrf_field() ?>
<input type="hidden" name="active" value="<?= (int) $branch['is_active'] === 1 ? '0' : '1' ?>">
<button type="submit" class="btn btn-link p-0 align-baseline">
<?= (int) $branch['is_active'] === 1 ? 'Deactivate' : 'Reactivate' ?>
</button>
</form>
</td>
</tr>
<?php endforeach; ?>
<?php if ($branches === []): ?>
<tr>
<td colspan="5">No branches found.</td>
</tr>
<?php endif; ?>
</tbody>
</table>
</div>
</section>
<?= view('staff_footer') ?>
