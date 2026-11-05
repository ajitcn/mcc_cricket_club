<?php

$programs = is_array($programs ?? null) ? $programs : [];
$canCreate = (bool) ($canCreate ?? false);
$canEdit = (bool) ($canEdit ?? false);
?>
<?= view('staff_header', [
    'title' => $title,
    'message' => $message ?? null,
    'error' => $error ?? null,
    'breadcrumbTrail' => [
        ['label' => 'Programs'],
    ],
    'pageActions' => $canCreate ? '<div class="mcc-page-actions">'
        . '<a class="mcc-button mcc-button-primary" href="' . site_url('programs/new') . '">'
        . '<i class="fa-solid fa-plus" aria-hidden="true"></i>'
        . '<span class="mcc-button-label">Create program</span>'
        . '</a></div>' : '',
]) ?>
<section class="mcc-card mcc-programs-results-card" aria-labelledby="program-list-heading">
    <div class="mcc-card-heading">
        <div>
            <span class="mcc-eyebrow">Coaching</span>
            <h1 id="program-list-heading">Programs</h1>
            <p class="mcc-card-subtitle">Global coaching programs available for session planning.</p>
        </div>
        <span class="mcc-muted" aria-label="Program count"><?= count($programs) ?> program<?= count($programs) === 1 ? '' : 's' ?></span>
    </div>

    <?php if ($programs === []): ?>
        <div class="mcc-empty-state" role="status">
            <i class="fa-solid fa-layer-group" aria-hidden="true"></i>
            <strong>No programs found</strong>
            <span>Create a program before creating a session.</span>
        </div>
    <?php else: ?>
        <div class="mcc-table-wrap">
            <table class="mcc-table">
                <caption class="sr-only">Available coaching programs</caption>
                <thead>
                    <tr>
                        <th scope="col">Program</th>
                        <th scope="col">Description</th>
                        <?php if ($canEdit): ?><th scope="col">Actions</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($programs as $program): ?>
                        <tr>
                            <th scope="row">
                                <span class="mcc-table-primary-text"><?= esc((string) $program['name']) ?></span>
                                <span class="mcc-table-secondary-text">Program #<?= (int) $program['id'] ?></span>
                            </th>
                            <td class="mcc-wrap-anywhere">
                                <?php if (trim((string) ($program['description'] ?? '')) !== ''): ?>
                                    <?= nl2br(esc((string) $program['description'])) ?>
                                <?php else: ?>
                                    <span class="mcc-muted">No description</span>
                                <?php endif; ?>
                            </td>
                            <?php if ($canEdit): ?>
                                <td data-label="Actions">
                                    <div class="mcc-session-action-menu" data-session-actions>
                                        <button class="mcc-button mcc-button-secondary mcc-button-sm" type="button"
                                            data-session-actions-toggle aria-expanded="false" aria-haspopup="true"
                                            aria-controls="program-actions-<?= (int) $program['id'] ?>">
                                            <i class="fa-solid fa-ellipsis-vertical" aria-hidden="true"></i>
                                            Actions
                                        </button>
                                        <div class="mcc-session-action-dropdown" id="program-actions-<?= (int) $program['id'] ?>"
                                            data-session-actions-menu hidden>
                                            <a href="<?= esc(site_url('programs/' . (int) $program['id'] . '/edit')) ?>">
                                                <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
                                                Edit program
                                            </a>
                                        </div>
                                    </div>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?= view('staff_footer') ?>
