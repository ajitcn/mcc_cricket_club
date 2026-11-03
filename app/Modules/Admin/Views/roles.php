<?= view('staff_header', ['title' => 'Roles', 'nav' => $nav, 'message' => $message ?? null, 'error' => $error ?? null, 'pageActions' =>
    '<div class="mcc-page-actions">
<a class="mcc-button mcc-button-primary" href="' . site_url('admin/roles/new') . '">Create role</a>
</div>']) ?>
<section class="mcc-card" aria-labelledby="roles-heading">
<div class="mcc-card-heading">
<div>
<span class="mcc-eyebrow">Administration</span>
<h2 id="roles-heading">Roles</h2>
</div>
<span class="mcc-muted">
<?= count($roles) ?> role<?= count($roles) === 1 ? '' : 's' ?>
</span>
</div>
<?php if ($roles === []): ?>
<div class="mcc-empty-state">
<span class="mcc-empty-icon" aria-hidden="true">Ã¢â€”â€¹</span>
<h2>No roles available</h2>
<p>Create a role to begin managing approved permissions.</p>
</div>
<?php else: ?>
<div class="mcc-table-wrap">
<table class="mcc-table">
<caption class="visually-hidden">Application roles</caption>
<thead>
<tr>
<th scope="col">Role</th>
<th scope="col">Actions</th>
</tr>
</thead>
<tbody>
<?php foreach ($roles as $role): ?>
<tr>
<th scope="row">
<span class="mcc-badge mcc-badge-info">
<?= esc($role['name']) ?>
</span>
</th>
<td>
<div class="mcc-lead-actions">
<a class="mcc-button mcc-button-secondary" href="<?= site_url('admin/roles/' . $role['id']) ?>">View</a>
<a class="mcc-button mcc-button-secondary" href="<?= site_url('admin/roles/' . $role['id'] . '/edit') ?>">Edit</a>
</div>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php endif; ?>
</section>
<?= view('staff_footer') ?>
