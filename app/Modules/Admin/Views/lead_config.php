<?php $source = ($kind ?? 'source') === 'source'; $label = $source ? 'Lead sources' : 'Lead statuses'; $base = $source ? 'lead-sources' : 'lead-statuses'; ?>
<?= view('staff_header', ['title' => $label, 'nav' => $nav, 'message' => $message, 'error' => $error]) ?>
<section class="mcc-card" aria-labelledby="config-heading">
<div class="mcc-card-heading">
<span class="mcc-eyebrow">Administration</span>
<h2 id="config-heading">
<?= esc($label) ?>
</h2>
<a class="mcc-button mcc-button-primary" href="<?= site_url('admin/'.$base.'/new') ?>">Create</a>
</div>
<div class="mcc-table-wrap">
<table class="mcc-table">
<thead>
<tr>
<th>Name</th>
<th>Order</th>
<th>Status</th>
<th>Actions</th>
</tr>
</thead>
<tbody>
<?php foreach ($items as $item): $active = (int) $item['is_active'] === 1; ?>
<tr>
<td>
<?= esc($item['name']) ?>
</td>
<td>
<?= (int) $item['sort_order'] ?>
</td>
<td>
<span class="mcc-badge <?= $active ? 'mcc-badge-success' : 'mcc-badge-muted' ?>">
<?= $active ? 'Active' : 'Inactive' ?>
</span>
</td>
<td>
<a href="<?= site_url('admin/'.$base.'/'.$item['id'].'/edit') ?>">Edit</a>
<form class="d-inline" method="post" action="<?= site_url('admin/'.$base.'/'.$item['id'].'/status') ?>"
    onsubmit="return confirm('<?= $active ? 'Deactivate' : 'Reactivate' ?> this value?');">
<?= csrf_field() ?>
<input type="hidden" name="active" value="<?= $active ? '0' : '1' ?>">
<button class="btn btn-link p-0" type="submit">
<?= $active ? 'Deactivate' : 'Reactivate' ?>
</button>
</form>
</td>
</tr>
<?php endforeach; ?>
<?php if ($items === []): ?>
<tr>
<td colspan="4">No configured values.</td>
</tr>
<?php endif; ?>
</tbody>
</table>
</div>
</section>
<?= view('staff_footer') ?>
