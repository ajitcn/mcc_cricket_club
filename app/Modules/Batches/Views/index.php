<?php

$batches = is_array($batches ?? null) ? $batches : [];
$canCreate = (bool) ($canCreate ?? false);
?>
<?php
$pageActions = $canCreate
    ? '<a class="mcc-button mcc-button-primary" href="' . site_url('batches/new') . '"><i class="fa-solid fa-plus" aria-hidden="true"></i><span class="mcc-button-label">Create batch</span></a>'
    : '';
?>
<?= view('staff_header', ['title' => $title, 'pageActions' => $pageActions]) ?>
<main class="container py-4 mcc-batches-page">
    <section class="mcc-card mcc-batches-results-card" aria-labelledby="batch-list-heading">
        <div class="mcc-card-heading">
            <div>
                <span class="mcc-eyebrow">Coaching</span>
                <h2 id="batch-list-heading">Batches</h2>
                <p class="mcc-card-subtitle">Global Batch master records.</p>
            </div>
        </div>

        <?php if ($batches === []): ?>
            <div class="mcc-empty-state" role="status">
                <i class="fa-solid fa-people-group" aria-hidden="true"></i>
                <strong>No batches found</strong>
                <span>No global Batches are available.</span>
            </div>
        <?php else: ?>
            <div class="mcc-table-wrap">
                <table class="mcc-table">
                    <caption class="sr-only">Global Batches</caption>
                    <thead>
                        <tr>
                            <th scope="col">Name</th>
                            <th scope="col">Status</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($batches as $batch): ?>
                            <tr>
                                <th scope="row" data-label="Name"><?= esc($batch['name']) ?></th>
                                <td data-label="Status">
                                    <span
                                        class="mcc-status-badge <?= (int) ($batch['is_active'] ?? 1) === 1 ? 'mcc-status-active' : 'mcc-status-inactive' ?>"
                                    >
                                        <?= (int) ($batch['is_active'] ?? 1) === 1 ? 'Active' : 'Inactive' ?>
                                    </span>
                                </td>
                                <td data-label="Actions">
                                    <a class="mcc-button mcc-button-secondary" href="<?= site_url('batches/' . (int) $batch['id'] . '/edit') ?>">
                                        <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
                                        Edit
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</main>
<?= view('staff_footer') ?>
