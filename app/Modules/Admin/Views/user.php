<?php $enabled = (int) ($user['is_enabled'] ?? 0) === 1; ?>
<?= view('staff_header', ['title' => 'User details', 'nav' => $nav, 'message' => $message ?? null, 'error' => $error ?? null]) ?>
<section class="mcc-card mcc-detail-hero" aria-labelledby="user-details-title">
<div>
<span class="mcc-eyebrow">Administration</span>
<h2 id="user-details-title">
<?= esc($user['display_name']) ?>
</h2>
<div class="mcc-detail-badges">
<span class="mcc-badge mcc-badge-info">User account</span>
<span class="mcc-badge <?= $enabled ? 'mcc-badge-success' : 'mcc-badge-danger' ?>">
<?= $enabled ? 'Enabled' : 'Disabled' ?>
</span>
</div>
</div>
<div class="mcc-detail-actions">
<a class="mcc-button mcc-button-primary" href="<?= site_url('admin/users/' . $user['id'] . '/edit') ?>">Edit profile</a>
<a class="mcc-button mcc-button-secondary" href="<?= site_url('admin/users') ?>">Back to users</a>
</div>
</section>
<div class="mcc-detail-grid">
<section class="mcc-card" aria-labelledby="user-information-heading">
<div class="mcc-card-heading">
<div>
<span class="mcc-eyebrow">Account</span>
<h2 id="user-information-heading">User information</h2>
</div>
</div>
<dl class="mcc-detail-list">
<dt>Name</dt>
<dd>
<?= esc($user['display_name']) ?>
</dd>
<dt>Email</dt>
<dd>
<a href="mailto:<?= esc($user['email']) ?>">
<?= esc($user['email']) ?>
</a>
</dd>
<dt>Last login</dt>
<dd>
                <?= esc($user['last_login_at'] ? mcc_user_datetime($user['last_login_at']) : 'Never') ?>
</dd>
</dl>
</section>
<section class="mcc-card" aria-labelledby="account-status-heading">
<div class="mcc-card-heading">
<div>
<span class="mcc-eyebrow">Access</span>
<h2 id="account-status-heading">Account status</h2>
</div>
</div>
<dl class="mcc-detail-list">
<dt>Current status</dt>
<dd>
<span class="mcc-badge <?= $enabled ? 'mcc-badge-success' : 'mcc-badge-danger' ?>">
<?= $enabled ? 'Enabled' : 'Disabled' ?>
</span>
</dd>
</dl>
<form method="post" action="<?= site_url('admin/users/' . $user['id'] . '/enabled') ?>" class="col-12 mt-2 text-right">
<?= csrf_field() ?>
<input type="hidden" name="enabled" value="<?= $enabled ? '0' : '1' ?>">
<button class="mcc-button <?= $enabled ? 'mcc-button-secondary' : 'mcc-button-primary' ?>" type="submit">
<?= $enabled ? 'Disable account' : 'Enable account' ?>
</button>
</form>
</section>
</div>
<section class="mcc-card mcc-detail-card" aria-labelledby="roles-heading">
<div class="mcc-card-heading">
<div>
<span class="mcc-eyebrow">Authorization</span>
<h2 id="roles-heading">Roles</h2>
</div>
</div>
<?php if ($user['roles'] === []): ?>
<p class="mcc-empty-state">No roles assigned.</p>
<?php else: ?>
<div class="mcc-table-wrap">
<table class="mcc-table">
<thead>
<tr>
<th scope="col">Role</th>
<th scope="col">Action</th>
</tr>
</thead>
<tbody>
<?php foreach ($user['roles'] as $role): ?>
<tr>
<td>
<span class="mcc-badge mcc-badge-info">
<?= esc($role['name']) ?>
</span>
</td>
<td>
<form method="post" action="<?= site_url('admin/users/' . $user['id'] . '/roles') ?>">
<?= csrf_field() ?>
<input type="hidden" name="role_id" value="<?= (int) $role['id'] ?>">
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
<form method="post" action="<?= site_url('admin/users/' . $user['id'] . '/roles') ?>" class="row">
<div class="col-12 col-md-4 mb-2">
<label for="role_id">Assign role</label>
<select class="form-control" id="role_id" name="role_id" required>
<?php foreach ($roles as $role): ?>
<option value="<?= (int) $role['id'] ?>">
<?= esc($role['name']) ?>
</option>
<?php endforeach; ?>
</select>
</div>
<input type="hidden" name="<?= csrf_token() ?>" value="<?= csrf_hash() ?>">
<input type="hidden" name="assign" value="1">
<div class="col-12 mt-2 text-right">
<button class="mcc-button mcc-button-primary" type="submit">Assign role</button>
</div>
</form>
</section>
<section class="mcc-card mcc-detail-card" aria-labelledby="branches-heading">
<div class="mcc-card-heading">
<div>
<span class="mcc-eyebrow">Scope</span>
<h2 id="branches-heading">Authorized branches</h2>
</div>
</div>
<?php if ($user['branches'] === []): ?>
<p class="mcc-empty-state">No branches assigned.</p>
<?php else: ?>
<div class="mcc-table-wrap">
<table class="mcc-table">
<thead>
<tr>
<th scope="col">Branch</th>
<th scope="col">Code</th>
<th scope="col">Action</th>
</tr>
</thead>
<tbody>
<?php foreach ($user['branches'] as $branch): ?>
<tr>
<td>
<?= esc($branch['name']) ?>
</td>
<td>
<?= esc($branch['code']) ?>
</td>
<td>
<form method="post" action="<?= site_url('admin/users/' . $user['id'] . '/branches') ?>">
<?= csrf_field() ?>
<input type="hidden" name="branch_id" value="<?= (int) $branch['id'] ?>">
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
<form method="post" action="<?= site_url('admin/users/' . $user['id'] . '/branches') ?>" class="row">
<div class="col-12 col-md-4 mb-2">
<label for="branch_id">Assign branch</label>
<select class="form-control" id="branch_id" name="branch_id" required>
<?php foreach ($branches as $branch): ?>
<option value="<?= (int) $branch['id'] ?>">
<?= esc($branch['name']) ?>
</option>
<?php endforeach; ?>
</select>
</div>
<input type="hidden" name="<?= csrf_token() ?>" value="<?= csrf_hash() ?>">
<input type="hidden" name="assign" value="1">
<div class="col-12 mt-2 text-right">
<button class="mcc-button mcc-button-primary" type="submit">Assign branch</button>
</div>
</form>
</section>
<section class="mcc-card mcc-detail-card" aria-labelledby="permissions-heading">
<div class="mcc-card-heading">
<div>
<span class="mcc-eyebrow">Effective access</span>
<h2 id="permissions-heading">Permissions</h2>
</div>
</div>
<?php if ($user['permissions'] === []): ?>
<p class="mcc-empty-state">No permissions assigned.</p>
<?php else: ?>
<div class="mcc-detail-badges" aria-label="Effective permissions">
<?php foreach ($user['permissions'] as $permission): ?>
<span class="mcc-badge mcc-badge-neutral">
<?= esc($permission['permission']) ?>
</span>
<?php endforeach; ?>
</div>
<?php endif; ?>
</section>
<?= view('staff_footer') ?>
