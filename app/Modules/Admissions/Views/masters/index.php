<?php

$rows = is_array($rows ?? null) ? $rows : [];
$type = (string) ($type ?? '');
$definition = is_array($definition ?? null) ? $definition : [];
$label = (string) ($definition['label'] ?? 'Master');
$pageActions = '';

if (! empty($canManage)) {
    $pageActions = '<div class="mcc-page-actions">'
        . '<a class="mcc-button mcc-button-primary" href="'
        . site_url('admission-masters/' . $type . '/new')
        . '">'
        . '<i class="fa-solid fa-plus" aria-hidden="true"></i>'
        . ' Create ' . esc($label)
        . '</a></div>';
}
?>
<?= view('staff_header', [
    'title' => 'Admission ' . $label . 's',
    'message' => $message ?? null,
    'error' => $error ?? null,
    'pageActions' => $pageActions,
]) ?>
<section class="mcc-card mcc-detail-card" aria-labelledby="admission-masters-heading">
    <div class="mcc-card-heading">
        <div>
            <span class="mcc-eyebrow">Admissions</span>
            <h2 id="admission-masters-heading"><?= esc($label) ?>s</h2>
        </div>
        <span class="mcc-muted">
            <?= count($rows) ?> result<?= count($rows) === 1 ? '' : 's' ?>
        </span>
    </div>
    <div class="mcc-table-wrap">
        <table class="mcc-table">
            <caption class="visually-hidden">Authorized Admission master plans</caption>
            <thead>
                <tr>
                    <th scope="col"><?= $type === 'academic' ? 'Academic year' : ($type === 'age_group' ? 'Age group' : 'Plan name') ?></th>
                    <?php if ($type === 'age_group'): ?>
                        <th scope="col">Created</th>
                        <th scope="col">Updated</th>
                    <?php endif; ?>
                    <?php if ($type === 'academic'): ?>
                        <th scope="col">Program</th>
                    <?php endif; ?>
                    <?php if ($type !== 'age_group'): ?>
                        <th scope="col">Branch</th>
                    <?php endif; ?>
                    <th scope="col">Status</th>
                    <th scope="col">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <?php $isActive = (int) ($row['is_active'] ?? 0) === 1; ?>
                    <tr>
                        <th scope="row">
                            <?= esc((string) ($row['name'] ?? $row['plan_name'] ?? $row['academic_year'] ?? 'Admission master')) ?>
                            <?php if ($type === 'academic' && ! empty($row['start_date'])): ?>
                                <span class="mcc-table-secondary-text">
                                    <?= esc((string) $row['start_date']) ?>
                                    <?php if (! empty($row['end_date'])): ?>
                                        to <?= esc((string) $row['end_date']) ?>
                                    <?php endif; ?>
                                </span>
                            <?php endif; ?>
                        </th>
                        <?php if ($type === 'age_group'): ?>
                            <td><?= esc((string) ($row['created_at'] ?? '—')) ?></td>
                            <td><?= esc((string) ($row['updated_at'] ?? '—')) ?></td>
                        <?php endif; ?>
                        <?php if ($type === 'academic'): ?>
                            <td><?= esc((string) ($row['program_name'] ?? '—')) ?></td>
                        <?php endif; ?>
                        <?php if ($type !== 'age_group'): ?>
                            <td>
                                <?= $row['branch_id'] === null
                                    ? 'Shared'
                                    : esc((string) ($row['branch_name'] ?? 'Authorized branch')) ?>
                            </td>
                        <?php endif; ?>
                        <td>
                            <span class="mcc-badge <?= $isActive ? 'mcc-badge-success' : 'mcc-badge-danger' ?>">
                                <?= $isActive ? 'Active' : 'Inactive' ?>
                            </span>
                        </td>
                        <td>
                            <?php if (! empty($canManage)): ?>
                                <div class="mcc-lead-actions">
                                    <a
                                        class="mcc-button mcc-button-secondary mcc-lead-action"
                                        href="<?= site_url('admission-masters/' . $type . '/' . (int) $row['id'] . '/edit') ?>"
                                        title="Edit <?= esc($label) ?>"
                                        aria-label="Edit <?= esc($label) ?>"
                                    >
                                        <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
                                        <span class="mcc-button-label">Edit</span>
                                    </a>
                                    <?php if ($isActive): ?>
                                        <form method="post" action="<?= site_url('admission-masters/' . $type . '/' . (int) $row['id'] . '/status/0') ?>">
                                            <?= csrf_field() ?>
                                            <button class="mcc-button mcc-button-secondary mcc-lead-action" type="submit">
                                                <i class="fa-solid fa-box-archive" aria-hidden="true"></i>
                                                <span class="mcc-button-label">
                                                    <?= $type === 'age_group' ? 'Set Inactive' : 'Retire' ?>
                                                </span>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <span class="mcc-muted">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($rows === []): ?>
                    <tr>
                        <td colspan="<?= $type === 'age_group' ? 5 : ($type === 'academic' ? 5 : 4) ?>">
                            <p class="mcc-empty-state">No <?= esc(strtolower($label)) ?>s found.</p>
                            <?php if (! empty($canManage)): ?>
                                <a class="mcc-button mcc-button-secondary" href="<?= site_url('admission-masters/' . $type . '/new') ?>">
                                    Create <?= esc($label) ?>
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?= view('staff_footer') ?>
