<?php

$result = is_array($result ?? null) ? $result : [];
$filters = is_array($result['filters'] ?? null) ? $result['filters'] : [];
$query = trim((string) ($filters['q'] ?? ''));
$hasFilters = $query !== '';
$opponents = is_array($result['opponents'] ?? null) ? $result['opponents'] : [];
$pageActions = '<div class="mcc-page-actions">'
    . '<button class="mcc-button mcc-button-secondary" type="button" data-filter-toggle '
    . 'data-show-label="Show Filters" data-hide-label="Hide Filters" '
    . 'aria-controls="opponent-filters" aria-expanded="' . ($hasFilters ? 'true' : 'false') . '">'
    . '<i class="fa-solid fa-filter" aria-hidden="true"></i>'
    . '<span class="mcc-button-label">' . ($hasFilters ? 'Hide Filters' : 'Show Filters') . '</span>'
    . '</button>'
    . '<a class="mcc-button mcc-button-primary" href="' . esc(site_url('matches/opponents/new')) . '">'
    . '<i class="fa-solid fa-plus" aria-hidden="true"></i>'
    . '<span class="mcc-button-label">Create opponent</span>'
    . '</a>'
    . '</div>';
?>
<?= view('staff_header', [
    'title' => $title,
    'nav' => $nav ?? null,
    'message' => $message ?? null,
    'error' => $error ?? null,
    'pageActions' => $pageActions,
]) ?>
<div class="mcc-opponent-list">
    <section id="opponent-filters" class="mcc-card mcc-scheduling-card mcc-session-filters-card" aria-labelledby="opponent-filters-heading" <?= $hasFilters ? '' : 'hidden' ?>>
        <div class="mcc-card-heading">
            <div>
                <span class="mcc-eyebrow">Search and filter</span>
                <h2 id="opponent-filters-heading">Find opponents</h2>
            </div>
        </div>
        <form method="get" action="<?= esc(site_url('matches/opponents')) ?>" class="row mcc-scheduling-filter-form">
            <div class="col-12 col-md-8 mb-2">
                <label for="opponent-search">Search</label>
                <input class="form-control" id="opponent-search" name="q" type="search" value="<?= esc($query) ?>" placeholder="Opponent or branch">
            </div>
            <div class="col-12 col-md-4 mb-2 mcc-form-actions">
                <button class="mcc-button mcc-button-primary" type="submit">Apply filters</button>
                <a class="mcc-button mcc-button-secondary" href="<?= esc(site_url('matches/opponents')) ?>">Clear</a>
            </div>
        </form>
    </section>

    <section class="mcc-card mcc-scheduling-card mcc-session-results-card" aria-labelledby="opponents-heading">
        <div class="mcc-session-summary" aria-live="polite">
            <div>
                <span class="mcc-eyebrow">Competition masters</span>
                <h2 id="opponents-heading">Opponents</h2>
            </div>
            <div class="mcc-session-summary-meta">
                <span><strong><?= count($opponents) ?></strong> record<?= count($opponents) === 1 ? '' : 's' ?></span>
                <?php if ($hasFilters): ?>
                    <span class="mcc-badge mcc-badge-info">1 active filter</span>
                <?php else: ?>
                    <span class="mcc-muted">All authorized branches</span>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($opponents === []): ?>
            <div class="mcc-empty-state" role="status">
                <i class="fa-solid fa-people-group mcc-empty-icon" aria-hidden="true"></i>
                <h2>No opponents found</h2>
                <p>Try another search or create an opponent for an authorized branch.</p>
            </div>
        <?php else: ?>
            <div class="mcc-table-wrap mcc-session-table-wrap">
                <table class="mcc-table mcc-session-list-table">
                    <caption class="visually-hidden">Authorized opponents</caption>
                    <thead>
                        <tr>
                            <th scope="col">Opponent</th>
                            <th scope="col">Branch</th>
                            <th scope="col">Status</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($opponents as $opponent): ?>
                            <?php $opponentId = (int) ($opponent['id'] ?? 0); ?>
                            <tr>
                                <th scope="row" data-label="Opponent"><?= esc((string) ($opponent['name'] ?? 'Not available')) ?></th>
                                <td data-label="Branch"><?= esc((string) ($opponent['branch_name'] ?? 'Not available')) ?></td>
                                <td data-label="Status"><span class="mcc-badge mcc-badge-<?= (int) ($opponent['is_active'] ?? 0) === 1 ? 'success' : 'neutral' ?>"><?= (int) ($opponent['is_active'] ?? 0) === 1 ? 'Active' : 'Inactive' ?></span></td>
                                <td data-label="Actions">
                                    <div class="mcc-session-action-menu" data-session-actions>
                                        <button class="mcc-button mcc-button-secondary" type="button" data-session-actions-toggle aria-expanded="false" aria-haspopup="true" aria-controls="opponent-actions-<?= $opponentId ?>">
                                            <i class="fa-solid fa-ellipsis-vertical" aria-hidden="true"></i>
                                            <span>Actions</span>
                                        </button>
                                        <div class="mcc-session-action-dropdown" id="opponent-actions-<?= $opponentId ?>" data-session-actions-menu hidden>
                                            <?php if ((bool) ($opponent['can_update'] ?? false)): ?>
                                                <a href="<?= esc(site_url('matches/opponents/' . $opponentId . '/edit')) ?>"><i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>Edit opponent</a>
                                            <?php endif; ?>
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
</div>
<?= view('staff_footer') ?>
