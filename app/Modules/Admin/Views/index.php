<?= view('staff_header', ['title'=>'Administration','nav'=>$nav,'message'=>$message??null,'error'=>$error??null]) ?>
<p>Manage users, roles, permissions, and authorized branches.</p>
<div class="card-grid">
<section class="card">
<h2>Users</h2>
<p>
<?= esc((string)$stats['users']) ?> accounts Ãƒâ€šÃ‚Â· <?= esc((string)$stats['enabled_users']) ?> enabled Ãƒâ€šÃ‚Â· <?=
    esc((string)$stats['disabled_users']) ?>
    disabled</p>
<a class="button-link" href="<?= site_url('admin/users') ?>">Manage users</a>
</section>
<section class="card">
<h2>Roles</h2>
<p>
<?= esc((string)$stats['roles']) ?> roles</p>
<a class="button-link" href="<?= site_url('admin/roles') ?>">Manage roles</a>
</section>
<section class="card">
<h2>Permissions</h2>
<p>Approved application identifiers</p>
<a class="button-link" href="<?= site_url('admin/permissions') ?>">View permissions</a>
</section>
<section class="card">
<h2>Branches</h2>
<p>
<?= esc((string)$stats['branches']) ?> active branches available for authorized assignment</p>
</section>
</div>
<p>
<a class="button-link" href="<?= site_url('admin/branches/create') ?>">Create branch</a>
</p>
<?= view('staff_footer') ?>
