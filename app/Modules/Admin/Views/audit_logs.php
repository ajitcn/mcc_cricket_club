<?= view('staff_header', ['title' => 'Audit logs', 'nav' => $nav, 'message' => $message ?? null, 'error' => $error ?? null]) ?>
<section class="mcc-card" aria-labelledby="audit-heading">
<div class="mcc-card-heading">
<span class="mcc-eyebrow">Administration</span>
<h2 id="audit-heading">Authorized audit records</h2>
</div>
<?php if ($audit_logs === []): ?>
<p class="mcc-empty-state">No audit records are available in your authorized scope.</p>
<?php else: ?>
<div class="mcc-table-wrap">
<table class="mcc-table">
<caption class="visually-hidden">Authorized audit records</caption>
<thead>
<tr>
<th scope="col">Time</th>
<th scope="col">Action</th>
<th scope="col">Entity</th>
<th scope="col">Branch</th>
<th scope="col">Actor</th>
<th scope="col">Reason</th>
</tr>
</thead>
<tbody>
<?php foreach ($audit_logs as $row): ?>
<tr>
<td>
                <?= esc(mcc_user_datetime($row['created_at'] ?? null)) ?>
</td>
<td>
<?= esc($row['action']) ?>
</td>
<td>
<?= esc($row['entity_type'].' #'.$row['entity_id']) ?>
</td>
<td>
<?= esc((string) ($row['branch_id'] ?? 'Global')) ?>
</td>
<td>
<?= esc((string) ($row['actor_user_id'] ?? 'System')) ?>
</td>
<td>
<?= esc((string) ($row['reason'] ?? '')) ?>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php endif; ?>
</section>
<?= view('staff_footer') ?>
