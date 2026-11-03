<?php

$programs = is_array($programs ?? null) ? $programs : [];
$canCreate = (bool) ($canCreate ?? false);
$canEdit = (bool) ($canEdit ?? false);
?>
<?= view('staff_header', [
    'title' => $title,
    'nav' => $nav ?? null,
    'message' => $message ?? null,
    'error' => $error ?? null,
    'pageActions' => $canCreate ? '<a class="mcc-button mcc-button-primary" href="' . site_url('programs/new') . '">'
        . '<i class="fa-solid fa-plus" aria-hidden="true"></i>'
        . '<span class="mcc-button-label">Create program</span>'
        . '</a>' : '',
]) ?>
<main class="container py-4 mcc-programs-page">
    <section class="mcc-card" aria-labelledby="program-list-heading">
        <div class="mcc-card-heading">
            <div>
                <span class="mcc-eyebrow">Coaching</span>
                <h1 id="program-list-heading">Programs</h1>
                <p class="mcc-card-subtitle">Programs available for branch batches.</p>
            </div>
            <span class="mcc-muted" aria-label="Program count"><?= count($programs) ?> program<?= count($programs) === 1 ? '' : 's' ?></span>
        </div>

        <?php if ($programs === []): ?>
            <div class="mcc-empty-state" role="status">
                <i class="fa-solid fa-layer-group" aria-hidden="true"></i>
                <strong>No programs found</strong>
                <span>Create a program before creating a batch.</span>
            </div>
        <?php else: ?>
            <div class="mcc-table-wrap">
                <table class="mcc-table">
                    <caption class="sr-only">Programs</caption>
                    <thead>
                        <tr>
                            <th scope="col">Name</th>
                            <th scope="col">Description</th>
                            <?php if ($canEdit): ?>
                                <th scope="col" class="text-end">Actions</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($programs as $program): ?>
                            <tr>
                                <th scope="row"><?= esc($program['name']) ?></th>
                                <td class="mcc-wrap-anywhere">
                                    <?php if (trim((string) ($program['description'] ?? '')) !== ''): ?>
                                        <?= nl2br(esc((string) $program['description'])) ?>
                                    <?php else: ?>
                                        <span class="mcc-muted">No description</span>
                                    <?php endif; ?>
                                </td>
                                <?php if ($canEdit): ?>
                                    <td class="text-end">
                                        <a class="mcc-button mcc-button-secondary mcc-button-small" href="<?= site_url('programs/' . (int) $program['id'] . '/edit') ?>">
                                            <i class="fa-solid fa-pen" aria-hidden="true"></i>
                                            Edit
                                        </a>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</main>
<?= view('staff_footer') ?>
