<?php
$pageActions = '';
$filters = $result['filters'] ?? [];
$queryFilters = array_filter(
    $filters,
    static fn ($value, $key): bool => $key !== 'page' && $value !== '',
    ARRAY_FILTER_USE_BOTH
);
$hasFilters = $queryFilters !== [];
$pageActions = '<div class="mcc-page-actions">'
    . '<button class="mcc-button mcc-button-secondary" type="button" data-filter-toggle aria-controls="tournament-filters" aria-expanded="' . ($hasFilters ? 'true' : 'false') . '">'
    . '<i class="fa-solid fa-filter" aria-hidden="true"></i><span class="mcc-button-label">'
    . ($hasFilters ? 'Hide Filters' : 'Show Filters') . '</span></button>';
if (($can_create ?? false) === true) {
    $pageActions .= '<a class="mcc-button mcc-button-primary" href="' . site_url('tournaments/new') . '">'
        . '<i class="fa-solid fa-plus" aria-hidden="true"></i>'
        . '<span class="mcc-button-label">Create tournament</span>'
        . '</a>';
}
$pageActions .= '</div>';
$statusDefinitions = [
    'planned' => ['label' => 'Planned', 'class' => 'info'],
    'active' => ['label' => 'Active', 'class' => 'success'],
    'completed' => ['label' => 'Completed', 'class' => 'success'],
    'cancelled' => ['label' => 'Cancelled', 'class' => 'danger'],
];
?>
<?= view('staff_header', ['title' => $title, 'pageActions' => $pageActions]) ?>

<div class="mcc-tournament-list">

    <?php
    $pageUrl = static function (int $page) use ($queryFilters): string {
        return site_url('tournaments') . '?' . http_build_query(
            array_merge($queryFilters, ['page' => $page])
        );
    };
    ?>

    <section class="mcc-card mcc-scheduling-card mcc-session-filters-card" id="tournament-filters" aria-labelledby="tournament-filter-heading" <?= $hasFilters ? '' : 'hidden' ?>>
        <div class="mcc-card-heading">
            <div>
                <span class="mcc-eyebrow">Tournaments</span>
                <h2 id="tournament-filter-heading">Search and filter</h2>
            </div>
        </div>
        <form method="get" class="row mcc-filter-form" action="<?= site_url('tournaments') ?>">
        <div class="col-12 col-md-4 mb-2">
            <label for="tournament-search">Search</label>
            <input
                class="form-control"
                id="tournament-search"
                name="q"
                value="<?= esc($filters['q'] ?? '') ?>"
            >
        </div>
        <div class="col-12 col-md-2 mb-2">
            <label for="tournament-date">Date</label>
            <input
                class="form-control"
                id="tournament-date"
                name="date"
                placeholder="DD-MM-YYYY"
                data-mcc-date-picker
                data-mcc-date-format="d-m-Y"
                data-mcc-date-submit-format="Y-m-d"
                value="<?= esc($filters['date'] ?? '') ?>"
            >
        </div>
        <div class="col-12 col-md-2 mb-2">
            <label for="tournament-filter-type">Type</label>
            <select class="form-control" id="tournament-filter-type" name="tournament_type">
                <option value="">All</option>
                <?php foreach (['school', 'club', 'district', 'mca', 'state', 'national', 'international', 'england_tour'] as $type): ?>
                    <option
                        value="<?= $type ?>"
                        <?= ($filters['tournament_type'] ?? '') === $type ? 'selected' : '' ?>
                    >
                        <?= ucfirst(str_replace('_', ' ', $type)) ?>
                    </option>
                <?php endforeach ?>
            </select>
        </div>
        <div class="col-12 col-md-2 mb-2">
            <label for="tournament-filter-status">Status</label>
            <select class="form-control" id="tournament-filter-status" name="status">
                <option value="">All</option>
                <?php foreach (['planned', 'active', 'completed', 'cancelled'] as $status): ?>
                    <option
                        value="<?= $status ?>"
                        <?= ($filters['status'] ?? '') === $status ? 'selected' : '' ?>
                    >
                        <?= ucfirst($status) ?>
                    </option>
                <?php endforeach ?>
            </select>
        </div>
        <div class="col-12 mt-2 mcc-form-actions">
            <button class="mcc-button mcc-button-secondary" type="submit">Filter</button>
        </div>
        </form>
    </section>

    <section class="mcc-card mcc-scheduling-card mcc-session-results-card" aria-labelledby="tournaments-heading">
        <div class="mcc-session-summary" aria-live="polite">
            <div>
                <span class="mcc-eyebrow">Competition records</span>
                <h2 id="tournaments-heading">Tournaments</h2>
            </div>
            <div class="mcc-session-summary-meta">
                <span><strong><?= (int) ($result['total'] ?? 0) ?></strong> result<?= (int) ($result['total'] ?? 0) === 1 ? '' : 's' ?></span>
                <?php if ($hasFilters): ?>
                    <span class="mcc-badge mcc-badge-info"><?= count($queryFilters) ?> active filter<?= count($queryFilters) === 1 ? '' : 's' ?></span>
                <?php else: ?>
                    <span class="mcc-muted">All authorized branches</span>
                <?php endif; ?>
            </div>
        </div>
    <div class="mcc-table-wrap mcc-session-table-wrap">
        <table class="mcc-table mcc-session-list-table">
            <caption class="visually-hidden">Branch-scoped tournaments</caption>
            <thead>
                <tr>
                    <th scope="col">Name</th>
                    <th scope="col">Type</th>
                    <th scope="col">Dates</th>
                    <th scope="col">Branch</th>
                    <th scope="col">Status</th>
                    <th scope="col">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($result['tournaments'] as $tournament): ?>
                    <tr>
                        <th scope="row"><?= esc($tournament['name']) ?></th>
                        <td><span class="mcc-badge mcc-badge-info"><?= esc(ucfirst((string) $tournament['tournament_type'])) ?></span></td>
                        <td>
                            <span><?= esc(mcc_user_date($tournament['starts_on'] ?? null)) ?></span>
                            <span class="mcc-muted mcc-date-muted">to <?= esc(mcc_user_date($tournament['ends_on'] ?? null)) ?></span>
                        </td>
                        <td><?= esc($tournament['branch_name']) ?></td>
                        <td>
                            <?php $statusMeta = $statusDefinitions[strtolower((string) ($tournament['status'] ?? ''))] ?? ['label' => 'Unknown', 'class' => 'neutral']; ?>
                            <span class="mcc-badge mcc-badge-<?= esc($statusMeta['class']) ?>">
                                <?= esc($statusMeta['label']) ?>
                            </span>
                        </td>
                        <td data-label="Actions">
                            <div class="mcc-session-action-menu" data-session-actions>
                                <button class="mcc-button mcc-button-secondary" type="button" data-session-actions-toggle aria-expanded="false" aria-haspopup="true" aria-controls="tournament-actions-<?= (int) $tournament['id'] ?>">
                                    <i class="fa-solid fa-ellipsis-vertical" aria-hidden="true"></i>
                                    <span>Actions</span>
                                </button>
                                <div class="mcc-session-action-dropdown" id="tournament-actions-<?= (int) $tournament['id'] ?>" data-session-actions-menu hidden>
                                    <a href="<?= esc(site_url('tournaments/' . (int) $tournament['id'])) ?>"><i class="fa-solid fa-eye" aria-hidden="true"></i>View tournament</a>
                                    <?php if (service('authorization')->can('tournament.update', (string) ($tournament['branch_id'] ?? 0))): ?>
                                        <a href="<?= esc(site_url('tournaments/' . (int) $tournament['id'] . '/edit')) ?>"><i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>Edit tournament</a>
                                    <?php endif; ?>
                                    <a href="<?= esc(site_url('tournaments/' . (int) $tournament['id'] . '/fixtures')) ?>"><i class="fa-solid fa-calendar-days" aria-hidden="true"></i>Fixtures</a>
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php endforeach ?>
                <?php if ($result['tournaments'] === []): ?>
                    <tr>
                        <td colspan="6" class="mcc-empty-state">
                            No tournaments are available in your authorized branches.
                        </td>
                    </tr>
                <?php endif ?>
            </tbody>
        </table>
    </div>
    </section>

    <?php if ((int) ($result['pages'] ?? 1) > 1): ?>
        <nav class="mcc-pagination" aria-label="Tournament pages">
            <span>
                Page <?= (int) $result['page'] ?> of <?= (int) $result['pages'] ?>
            </span>
            <?php if ((int) $result['page'] > 1): ?>
                <a
                    class="mcc-button mcc-button-secondary"
                    href="<?= esc($pageUrl((int) $result['page'] - 1)) ?>"
                >
                    Previous
                </a>
            <?php endif ?>
            <?php if ((int) $result['page'] < (int) $result['pages']): ?>
                <a
                    class="mcc-button mcc-button-secondary"
                    href="<?= esc($pageUrl((int) $result['page'] + 1)) ?>"
                >
                    Next
                </a>
            <?php endif ?>
        </nav>
    <?php endif ?>
</div>

<?= view('staff_footer') ?>
