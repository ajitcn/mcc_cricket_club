<?php

$batches = is_array($batches ?? null) ? $batches : [];
$canCreate = (bool) ($canCreate ?? false);
?>
<?php
$pageActions = $canCreate
    ? '<div class="mcc-page-actions"><a class="mcc-button mcc-button-primary" href="' . site_url('batches/new') . '"><i class="fa-solid fa-plus" aria-hidden="true"></i><span class="mcc-button-label">Create batch</span></a></div>'
    : '';
?>
<?= view('staff_header', [
    'title' => $title,
    'pageActions' => $pageActions,
    'breadcrumbTrail' => [
        ['label' => 'Batches'],
    ],
]) ?>
<section class="mcc-card mcc-batches-results-card" aria-labelledby="batch-list-heading">
    <div class="mcc-card-heading">
        <div>
            <span class="mcc-eyebrow">Admissions</span>
            <h1 id="batch-list-heading">Batches</h1>
            <p class="mcc-card-subtitle">Global batch master records used across the academy.</p>
        </div>
        <span class="mcc-muted" aria-label="Batch count"><?= count($batches) ?> batch<?= count($batches) === 1 ? '' : 'es' ?></span>
    </div>

    <?php if ($batches === []): ?>
        <div class="mcc-empty-state" role="status">
            <i class="fa-solid fa-people-group" aria-hidden="true"></i>
            <strong>No batches found</strong>
            <span>No global batches are available.</span>
        </div>
    <?php else: ?>
        <div class="mcc-table-wrap">
            <table class="mcc-table">
                <caption class="sr-only">Global batches</caption>
                <thead>
                    <tr>
                        <th scope="col">Batch</th>
                        <th scope="col">Status</th>
                        <th scope="col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($batches as $batch): ?>
                        <?php $active = (int) ($batch['is_active'] ?? 1) === 1; ?>
                        <tr>
                            <th scope="row" data-label="Batch">
                                <span class="mcc-table-primary-text"><?= esc((string) $batch['name']) ?></span>
                                <span class="mcc-table-secondary-text">Batch #<?= (int) $batch['id'] ?></span>
                            </th>
                            <td data-label="Status">
                                <span class="mcc-badge mcc-badge-<?= $active ? 'success' : 'neutral' ?>">
                                    <?= $active ? 'Active' : 'Inactive' ?>
                                </span>
                            </td>
                            <td data-label="Actions">
                                <div class="mcc-session-action-menu" data-session-actions>
                                    <button class="mcc-button mcc-button-secondary mcc-button-sm" type="button"
                                        data-session-actions-toggle aria-expanded="false" aria-haspopup="true"
                                        aria-controls="batch-actions-<?= (int) $batch['id'] ?>">
                                        <i class="fa-solid fa-ellipsis-vertical" aria-hidden="true"></i>
                                        Actions
                                    </button>
                                    <div class="mcc-session-action-dropdown" id="batch-actions-<?= (int) $batch['id'] ?>"
                                        data-session-actions-menu hidden>
                                        <a href="<?= esc(site_url('batches/' . (int) $batch['id'] . '/edit')) ?>">
                                            <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
                                            Edit batch
                                        </a>
                                    </div>
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
