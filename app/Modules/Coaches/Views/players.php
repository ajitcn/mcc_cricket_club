<?php

$result = is_array($result ?? null) ? $result : [];
$filters = is_array($result['filters'] ?? null) ? $result['filters'] : [];
$players = is_array($result['players'] ?? null) ? $result['players'] : [];
$queryFilters = array_filter(
    $filters,
    static fn($value, $key): bool => $key !== 'page' && $value !== null && $value !== '',
    ARRAY_FILTER_USE_BOTH
);
$hasFilters = $queryFilters !== [];
$pageUrl = static function (int $targetPage) use ($queryFilters, $coach): string {
    $query = http_build_query(array_merge($queryFilters, ['page' => $targetPage]));

    return site_url('coaches/' . (int) $coach['id'] . '/players')
        . ($query === '' ? '' : '?' . $query);
};
?>
<?= view('staff_header', [
    'title' => 'Assigned players',
    'nav' => $nav,
    'message' => $message,
    'error' => $error,
    'pageActions' => '<div class="mcc-page-actions">
        <button class="mcc-button mcc-button-secondary" type="button" data-filter-toggle
            data-show-label="Show Filters" data-hide-label="Hide Filters"
            aria-controls="coach-player-filters" aria-expanded="' . ($hasFilters ? 'true' : 'false') . '">
            <i class="fa-solid fa-filter" aria-hidden="true"></i>
            <span class="mcc-button-label">' . ($hasFilters ? 'Hide Filters' : 'Show Filters') . '</span>
        </button>
        <a class="mcc-button mcc-button-secondary" href="' . site_url('coaches/' . (int) $coach['id']) . '">
            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
            <span class="mcc-button-label">Back to coach</span>
        </a>
    </div>',
]) ?>
<div class="mcc-coach-detail-page">
    <section id="coach-player-filters" class="mcc-card" aria-labelledby="player-filter-heading" <?= $hasFilters ? '' : 'hidden' ?>>
        <div class="mcc-card-heading">
            <div>
                <span class="mcc-eyebrow">Search and filter</span>
                <h2 id="player-filter-heading">Find assigned players</h2>
            </div>
        </div>
        <form method="get" class="row" aria-label="Filter assigned players">
            <div class="col-12 col-md-6 col-lg-4 mb-3">
                <label for="coach-player-search">Player</label>
                <input
                    class="form-control"
                    id="coach-player-search"
                    name="q"
                    value="<?= esc($filters['q'] ?? '') ?>"
                    placeholder="Search by player name"
                    autocomplete="off"
                >
            </div>
            <div class="col-12 col-md-6 col-lg-4 mb-3">
                <label for="coach-player-status">Status</label>
                <?php $playerStatuses = ['active' => 'Active', 'inactive' => 'Inactive', 'suspended' => 'Suspended', 'left_academy' => 'Left academy', 'alumni' => 'Alumni']; ?>
                <select class="form-control" id="coach-player-status" name="status">
                    <option value="">All statuses</option>
                    <?php foreach ($playerStatuses as $value => $label): ?>
                        <option value="<?= esc($value) ?>" <?= ($filters['status'] ?? '') === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if (count($result['branches'] ?? []) > 1): ?>
                <div class="col-12 col-md-6 col-lg-4 mb-3">
                    <label for="coach-player-branch">Branch</label>
                    <select class="form-control" id="coach-player-branch" name="branch_id">
                        <option value="">All authorized branches</option>
                        <?php foreach (($result['branches'] ?? []) as $branch): ?>
                            <option value="<?= (int) $branch['id'] ?>" <?= (string) ($filters['branch_id'] ?? '') === (string) $branch['id'] ? 'selected' : '' ?>>
                                <?= esc($branch['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            <div class="col-12 col-md-6 col-lg-4 mb-3">
                <label for="coach-player-batch">Batch</label>
                <select class="form-control" id="coach-player-batch" name="batch_id">
                    <option value="">All batches</option>
                    <?php foreach (($result['batchOptions'] ?? []) as $batch): ?>
                        <option
                            value="<?= (int) $batch['id'] ?>"
                            <?= (string) ($filters['batch_id'] ?? '') === (string) $batch['id'] ? 'selected' : '' ?>
                        >
                            <?= esc($batch['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 mcc-form-actions">
                <button class="mcc-button mcc-button-secondary" type="submit">
                    <i class="fa-solid fa-filter" aria-hidden="true"></i> Apply filters
                </button>
                <a class="mcc-button mcc-button-ghost" href="<?= site_url('coaches/' . (int) $coach['id'] . '/players') ?>">Clear</a>
            </div>
        </form>
    </section>
    <section class="mcc-card mcc-coach-related-card" aria-labelledby="player-list-heading">
        <div class="mcc-card-heading">
            <div>
                <span class="mcc-eyebrow"><?= esc($coach['display_name']) ?></span>
                <h2 id="player-list-heading">Assigned players</h2>
            </div>
            <span class="mcc-muted"><?= (int) ($result['total'] ?? 0) ?> result<?= (int) ($result['total'] ?? 0) === 1 ? '' : 's' ?></span>
        </div>
        <?php if ($players === []): ?>
            <p class="mcc-empty-state">No active players are assigned to this coach in the selected scope.</p>
        <?php else: ?>
            <div class="mcc-table-wrap">
                <table class="mcc-table mcc-coach-related-table">
                    <caption class="visually-hidden">Active players assigned to <?= esc($coach['display_name']) ?></caption>
                    <thead>
                        <tr>
                            <th scope="col">Player</th>
                            <th scope="col">Branch</th>
                            <th scope="col">Batch</th>
                            <th scope="col">Program</th>
                            <th scope="col">Status</th>
                            <th scope="col">Assigned</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($players as $player): ?>
                            <tr>
                                <th scope="row">
                                    <a href="<?= site_url('players/' . (int) $player['player_id']) ?>">
                                        <?= esc($player['full_name']) ?>
                                    </a>
                                </th>
                                <td><?= esc($player['branch_name']) ?></td>
                                <td><?= esc($player['batch_name'] ?? 'Not assigned') ?></td>
                                <td><?= esc($player['program_name'] ?? 'Not assigned') ?></td>
                                <td><span class="mcc-badge mcc-badge-neutral"><?= esc((string) $player['status']) ?></span></td>
                                <td><?= esc(mcc_user_date($player['effective_from'] ?? null)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        <?php if ((int) ($result['pages'] ?? 1) > 1): ?>
            <nav class="mcc-pagination" aria-label="Assigned player pages">
                <span>Page <?= (int) ($result['page'] ?? 1) ?> of <?= (int) ($result['pages'] ?? 1) ?></span>
                <div>
                    <?php if ((int) ($result['page'] ?? 1) > 1): ?>
                        <a class="mcc-button mcc-button-secondary" href="<?= esc($pageUrl((int) $result['page'] - 1)) ?>">Previous</a>
                    <?php endif; ?>
                    <?php if ((int) ($result['page'] ?? 1) < (int) ($result['pages'] ?? 1)): ?>
                        <a class="mcc-button mcc-button-secondary" href="<?= esc($pageUrl((int) $result['page'] + 1)) ?>">Next</a>
                    <?php endif; ?>
                </div>
            </nav>
        <?php endif; ?>
    </section>
</div>
<?= view('staff_footer') ?>
