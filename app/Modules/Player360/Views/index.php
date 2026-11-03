<?php

$result = is_array($result ?? null) ? $result : [];
$players = is_array($result['players'] ?? null) ? $result['players'] : [];
$filters = is_array($result['filters'] ?? null) ? $result['filters'] : [];
$hasPlayerSearch = trim((string) ($filters['q'] ?? '')) !== '';
$pageActions = '<div class="mcc-page-actions">
    <button class="mcc-button mcc-button-secondary" type="button" data-filter-toggle
        data-show-label="Show search" data-hide-label="Hide search"
        aria-controls="player-360-filters" aria-expanded="' . ($hasPlayerSearch ? 'true' : 'false') . '">
        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
        <span class="mcc-button-label">' . ($hasPlayerSearch ? 'Hide search' : 'Show search') . '</span>
    </button>
</div>';
?>
<?= view('staff_header', ['title' => $title, 'pageActions' => $pageActions]) ?>
<main class="container py-4 mcc-player-360-index" id="main-content">
    <section id="player-360-filters" class="mcc-card mcc-player-360-filter-card" aria-labelledby="player-360-heading" <?= $hasPlayerSearch ? '' : 'hidden' ?>>
        <div class="mcc-card-heading">
            <div>
                <span class="mcc-eyebrow">Player overview</span>
                <h2 id="player-360-heading">Find a Player</h2>
            </div>
        </div>
        <form method="get" action="<?= esc(site_url('player-360')) ?>" class="mcc-player-360-search-form">
            <label for="player-360-search">Search Player</label>
            <div class="mcc-form-actions">
                <input class="form-control" id="player-360-search" name="q" type="search" maxlength="100" value="<?= esc($filters['q'] ?? '') ?>" placeholder="Name, email, or admission number">
                <button class="mcc-button mcc-button-primary" type="submit">Search</button>
            </div>
        </form>
    </section>

    <section class="mcc-card mcc-player-360-results-card" aria-labelledby="player-360-results-heading">
        <div class="mcc-card-heading">
            <div>
                <span class="mcc-eyebrow">Player overview</span>
                <h2 id="player-360-results-heading">Authorized Players</h2>
            </div>
            <span class="mcc-muted"><?= (int) ($result['total'] ?? count($players)) ?> result<?= (int) ($result['total'] ?? count($players)) === 1 ? '' : 's' ?></span>
        </div>
        <?php if ($players === []): ?>
            <div class="mcc-empty-state" role="status">
                <i class="fa-solid fa-user-slash mcc-empty-icon" aria-hidden="true"></i>
                <h3>No Players found</h3>
                <p>Try a different search term.</p>
            </div>
        <?php else: ?>
            <div class="mcc-table-wrap">
                <table class="mcc-table">
                    <caption class="visually-hidden">Authorized Players for Player 360</caption>
                    <thead>
                        <tr>
                            <th scope="col">Player</th>
                            <th scope="col">Branch</th>
                            <th scope="col">Status</th>
                            <th scope="col">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($players as $player): ?>
                            <tr>
                                <th scope="row" data-label="Player"><?= esc($player['full_name'] ?? 'Not available') ?></th>
                                <td data-label="Branch"><?= esc($player['branch_name'] ?? 'Not available') ?></td>
                                <td data-label="Status">
                                    <span class="mcc-badge mcc-badge-<?= esc((string) ($player['status'] ?? 'unknown') === 'active' ? 'success' : 'neutral') ?>">
                                        <?= esc(ucwords(str_replace('_', ' ', (string) ($player['status'] ?? 'unknown')))) ?>
                                    </span>
                                </td>
                                <td data-label="Action"><a class="mcc-button mcc-button-secondary" href="<?= esc(site_url('player-360/' . (int) ($player['id'] ?? 0))) ?>">Open Player 360°</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</main>
<?= view('staff_footer') ?>
