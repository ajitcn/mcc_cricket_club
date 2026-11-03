<?= view('staff_header', ['title' => 'Permissions', 'nav' => $nav, 'message' => $message ?? null, 'error' => $error ?? null]) ?>
<section class="mcc-card" aria-labelledby="permissions-heading">
<div class="mcc-card-heading">
<div>
<span class="mcc-eyebrow">Administration</span>
<h2 id="permissions-heading">Permissions</h2>
<p class="mcc-muted">Approved permission identifiers used by the implemented application.</p>
</div>
<span class="mcc-muted">
<?= count($permissions) ?> permission<?= count($permissions) === 1 ? '' : 's' ?>
</span>
</div>
<div class="mcc-table-wrap">
<table class="mcc-table">
<caption class="visually-hidden">Approved permissions</caption>
<thead>
<tr>
<th scope="col">Category</th>
<th scope="col">Permission</th>
<th scope="col">Description</th>
<th scope="col">Assigned roles</th>
<th scope="col">Actions</th>
</tr>
</thead>
<tbody>
<?php foreach ($permissions as $permission): ?>
<tr>
<td>
<span class="mcc-badge mcc-badge-info">
<?= esc($permission['category']) ?>
</span>
</td>
<th scope="row">
<?= esc($permission['permission']) ?>
</th>
<td>
<?= esc($permission['description']) ?>
</td>
<td>
<?= esc((string) $permission['role_count']) ?>
</td>
<td>
<a class="mcc-button mcc-button-secondary" href="<?= site_url('admin/permissions/' . rawurlencode($permission['permission'])) ?>">View</a>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</section>
<?= view('staff_footer') ?>
