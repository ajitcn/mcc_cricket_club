<?php

$source = ($kind ?? 'source') === 'source';
$label = $source ? 'Lead sources' : 'Lead statuses';
$singular = $source ? 'source' : 'status';
$base = $source ? 'lead-sources' : 'lead-statuses';
$items = is_array($items ?? null) ? $items : [];
?>
<?= view('staff_header', [
    'title' => $label,
    'nav' => $nav,
    'message' => $message ?? null,
    'error' => $error ?? null,
    'pageActions' => '<div class="mcc-page-actions">
        <a class="mcc-button mcc-button-primary" href="' . site_url('admin/' . $base . '/new') . '">
            <i class="fa-solid fa-plus" aria-hidden="true"></i>
            <span class="mcc-button-label">Create ' . esc($singular) . '</span>
        </a>
    </div>',
]) ?>

<section class="mcc-card mcc-admin-config-card" aria-labelledby="config-heading">
    <div class="mcc-card-heading">
        <div>
            <span class="mcc-eyebrow">Administration</span>
            <h1 id="config-heading"><?= esc($label) ?></h1>
            <p class="mcc-card-subtitle">Maintain the selectable values used throughout Lead and CRM workflows.</p>
        </div>
        <span class="mcc-muted" aria-label="Configured value count">
            <?= count($items) ?> value<?= count($items) === 1 ? '' : 's' ?>
        </span>
    </div>

    <?php if ($items === []): ?>
        <div class="mcc-empty-state" role="status">
            <i class="fa-solid fa-list" aria-hidden="true"></i>
            <strong>No <?= esc($label) ?> configured</strong>
            <span>Create a value to make it available in CRM forms.</span>
        </div>
    <?php else: ?>
        <div class="mcc-table-wrap">
            <table class="mcc-table">
                <caption class="sr-only"><?= esc($label) ?></caption>
                <thead>
                    <tr>
                        <th scope="col">Value</th>
                        <th scope="col">Display order</th>
                        <th scope="col">Status</th>
                        <th scope="col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <?php $active = (int) ($item['is_active'] ?? 0) === 1; ?>
                        <tr>
                            <th scope="row" data-label="Value">
                                <span class="mcc-table-primary-text"><?= esc((string) ($item['name'] ?? '')) ?></span>
                            </th>
                            <td data-label="Display order"><?= (int) ($item['sort_order'] ?? 0) ?></td>
                            <td data-label="Status">
                                <span class="mcc-badge mcc-badge-<?= $active ? 'success' : 'neutral' ?>">
                                    <?= $active ? 'Active' : 'Inactive' ?>
                                </span>
                            </td>
                            <td data-label="Actions">
                                <div class="mcc-session-action-menu" data-session-actions>
                                    <button
                                        class="mcc-button mcc-button-secondary mcc-button-sm"
                                        type="button"
                                        data-session-actions-toggle
                                        aria-expanded="false"
                                        aria-haspopup="true"
                                        aria-controls="lead-config-actions-<?= (int) ($item['id'] ?? 0) ?>"
                                    >
                                        <i class="fa-solid fa-ellipsis-vertical" aria-hidden="true"></i>
                                        Actions
                                    </button>
                                    <div
                                        class="mcc-session-action-dropdown"
                                        id="lead-config-actions-<?= (int) ($item['id'] ?? 0) ?>"
                                        data-session-actions-menu
                                        hidden
                                    >
                                        <a href="<?= esc(site_url('admin/' . $base . '/' . (int) $item['id'] . '/edit')) ?>">
                                            <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
                                            Edit <?= esc($singular) ?>
                                        </a>
                                        <form
                                            method="post"
                                            action="<?= esc(site_url('admin/' . $base . '/' . (int) $item['id'] . '/status')) ?>"
                                            onsubmit="return confirm('<?= $active ? 'Deactivate' : 'Reactivate' ?> this <?= esc($singular) ?>?');"
                                        >
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="active" value="<?= $active ? '0' : '1' ?>">
                                            <button type="submit">
                                                <i class="fa-solid fa-power-off" aria-hidden="true"></i>
                                                <?= $active ? 'Deactivate' : 'Reactivate' ?>
                                            </button>
                                        </form>
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
