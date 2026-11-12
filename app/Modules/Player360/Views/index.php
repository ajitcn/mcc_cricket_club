<?php

$result = is_array($result ?? null) ? $result : [];
$players = is_array($result['players'] ?? null) ? $result['players'] : [];
$filters = is_array($result['filters'] ?? null) ? $result['filters'] : [];
$hasPlayerSearch = trim((string) ($filters['q'] ?? '')) !== '';
$pageActions = '<div class="mcc-page-actions">
    <button class="mcc-button mcc-button-secondary" type="button" data-filter-toggle
        data-show-label="Show Filters" data-hide-label="Hide Filters"
        aria-controls="player-360-filters" aria-expanded="' . ($hasPlayerSearch ? 'true' : 'false') . '">
        <i class="fa-solid fa-filter" aria-hidden="true"></i>
        <span class="mcc-button-label">' . ($hasPlayerSearch ? 'Hide Filters' : 'Show Filters') . '</span>
    </button>
</div>';
?>
<?= view('staff_header', ['title' => $title, 'pageActions' => $pageActions]) ?>
<main class="mcc-player-360-index" id="main-content">
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
                <span class="mcc-eyebrow">Player management</span>
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
            <div class="mcc-table-wrap mcc-table-responsive mcc-player-360-table-wrap">
                <table class="mcc-table mcc-player-360-table">
                    <caption class="visually-hidden">Authorized Players for Player 360</caption>
                    <thead>
                        <tr>
                            <th scope="col">Player</th>
                            <th scope="col">Placement</th>
                            <th scope="col">Assigned coaches</th>
                            <th scope="col">Status</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($players as $player): ?>
                            <?php
                            $playerName = trim((string) ($player['full_name'] ?? '')) ?: 'Unnamed player';
                            $playerReference = trim((string) ($player['player_reference'] ?? ''));
                            $branchName = trim((string) ($player['branch_name'] ?? '')) ?: 'Not available';
                            $batchName = trim((string) ($player['batch_name'] ?? '')) ?: 'Not assigned';
                            $ageGroup = trim((string) ($player['age_group'] ?? '')) ?: 'Not provided';
                            $groupCoach = trim((string) ($player['group_coach_name'] ?? '')) ?: 'Not assigned';
                            $personalCoach = trim((string) ($player['personal_coach_name'] ?? '')) ?: 'Not assigned';
                            $status = (string) ($player['status'] ?? 'unknown');
                            $statusLabel = ucwords(str_replace('_', ' ', $status));
                            ?>
                            <tr>
                                <th scope="row" data-label="Player">
                                    <span class="mcc-table-primary-text"><?= esc($playerName) ?></span>
                                    <span class="mcc-table-secondary-text">
                                        #<?= esc($playerReference !== '' ? $playerReference : (string) ((int) ($player['id'] ?? 0))) ?>
                                    </span>
                                </th>
                                <td data-label="Placement">
                                    <span class="mcc-table-primary-text"><?= esc($branchName) ?></span>
                                    <span class="mcc-table-secondary-text">Batch: <?= esc($batchName) ?></span>
                                    <span class="mcc-table-secondary-text">Age group: <?= esc($ageGroup) ?></span>
                                </td>
                                <td data-label="Assigned coaches">
                                    <span class="mcc-table-secondary-text">Group: <?= esc($groupCoach) ?></span>
                                    <span class="mcc-table-secondary-text">Personal: <?= esc($personalCoach) ?></span>
                                </td>
                                <td data-label="Status">
                                    <span class="mcc-badge mcc-badge-<?= esc($status === 'active' ? 'success' : 'neutral') ?>">
                                        <?= esc($statusLabel) ?>
                                    </span>
                                </td>
                                <td data-label="Actions">
                                    <a class="mcc-button mcc-button-secondary" href="<?= esc(site_url('player-360/' . (int) ($player['id'] ?? 0))) ?>">
                                        Open Player 360°
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
