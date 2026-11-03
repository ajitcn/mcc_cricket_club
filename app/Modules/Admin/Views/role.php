<?= view('staff_header', ['title' => 'Role details', 'nav' => $nav, 'message' => $message ?? null, 'error' => $error ?? null]) ?>
<section class="mcc-card mcc-detail-hero" aria-labelledby="role-details-title">
<div>
<span class="mcc-eyebrow">Administration</span>
<h2 id="role-details-title">
<?= esc($role['name']) ?>
</h2>
<div class="mcc-detail-badges">
<span class="mcc-badge mcc-badge-info">Role</span>
<span class="mcc-badge mcc-badge-neutral">
<?= count($role['permissions']) ?> permission<?= count($role['permissions']) === 1 ? '' : 's' ?>
</span>
</div>
</div>
<div class="mcc-detail-actions">
<a class="mcc-button mcc-button-primary" href="<?= site_url('admin/roles/' . $role['id'] . '/edit') ?>">Edit role</a>
<a class="mcc-button mcc-button-secondary" href="<?= site_url('admin/roles') ?>">Back to roles</a>
</div>
</section>
<div class="mcc-detail-grid">
<section class="mcc-card" aria-labelledby="assigned-permissions-heading">
<div class="mcc-card-heading">
<div>
<span class="mcc-eyebrow">Authorization</span>
<h2 id="assigned-permissions-heading">Assigned permissions</h2>
</div>
</div>
<?php if ($role['permissions'] === []): ?>
<p class="mcc-empty-state">No permissions assigned.</p>
<?php else: ?>
<div class="mcc-table-wrap">
<table class="mcc-table">
<thead>
<tr>
<th scope="col">Permission</th>
<th scope="col">Action</th>
</tr>
</thead>
<tbody>
<?php foreach ($role['permissions'] as $assigned): ?>
<tr>
<th scope="row">
<span class="mcc-badge mcc-badge-neutral">
<?= esc($assigned['permission']) ?>
</span>
</th>
<td>
<form method="post" action="<?= site_url('admin/roles/' . $role['id'] . '/permissions') ?>">
<?= csrf_field() ?>
<input type="hidden" name="permission" value="<?= esc($assigned['permission']) ?>">
<input type="hidden" name="assign" value="0">
<button class="mcc-button mcc-button-secondary" type="submit">Remove</button>
</form>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php endif; ?>
<?php
$approvedPermissions = is_array($permissions ?? null) ? $permissions : [];
$assignedPermissionNames = array_map(
    static fn (array $permission): string => (string) ($permission['permission'] ?? ''),
    is_array($role['permissions'] ?? null) ? $role['permissions'] : [],
);
$submittedPermissions = old('permissions', $assignedPermissionNames);
$selectedPermissionNames = is_array($submittedPermissions)
    ? array_values(array_map('strval', $submittedPermissions))
    : $assignedPermissionNames;
?>
<?php if ($approvedPermissions === []): ?>
<div class="mcc-empty-state" role="status">
<i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
<strong>No approved permissions available.</strong>
<span>The centralized permission registry has no assignable permissions.</span>
</div>
<?php else: ?>
<form method="post" action="<?= site_url('admin/roles/' . $role['id'] . '/permissions') ?>" class="row mcc-role-permission-form">
<div class="col-12 mb-2 mcc-form-field">
<label for="permission">Assign approved permission</label>
<select class="form-control selectpicker btSelect" id="permission" name="permissions[]" multiple data-bt-select data-bt-select-clear data-bt-select-search-placeholder="Search permissions" data-bt-select-placeholder="Select approved permissions" title="Assign approved permission" aria-describedby="permission-help">
<?php foreach ($approvedPermissions as $permission): ?>
<?php $permissionName = (string) ($permission['permission'] ?? ''); ?>
<option value="<?= esc($permissionName) ?>" <?= in_array($permissionName, $selectedPermissionNames, true) ? 'selected' : '' ?>>
<?= esc($permissionName) ?>
</option>
<?php endforeach; ?>
</select>
<span class="mcc-form-help" id="permission-help">Select all permissions that should remain assigned to this role.</span>
</div>
<?= csrf_field() ?>
<input type="hidden" name="permission_sync" value="1">
<input type="hidden" name="expected_permission_version" value="<?= esc((string) ($role['permission_version'] ?? '')) ?>">
<div class="col-12 mt-2 text-right">
<button class="mcc-button mcc-button-primary" type="submit">Save permissions</button>
</div>
</form>
<?php endif; ?>
</section>
<section class="mcc-card" aria-labelledby="assigned-users-heading">
<div class="mcc-card-heading">
<div>
<span class="mcc-eyebrow">Accounts</span>
<h2 id="assigned-users-heading">Assigned users</h2>
</div>
</div>
<?php if ($role['users'] === []): ?>
<p class="mcc-empty-state">No users assigned.</p>
<?php else: ?>
<div class="mcc-table-wrap">
<table class="mcc-table">
<thead>
<tr>
<th scope="col">Name</th>
<th scope="col">Email</th>
</tr>
</thead>
<tbody>
<?php foreach ($role['users'] as $user): ?>
<tr>
<th scope="row">
<a href="<?= site_url('admin/users/' . $user['id']) ?>">
<?= esc($user['display_name']) ?>
</a>
</th>
<td>
<a href="mailto:<?= esc($user['email']) ?>">
<?= esc($user['email']) ?>
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
<?= view('staff_footer') ?>
