<?php

$player = is_array($player ?? null) ? $player : null;
$hasPlayer = $player !== null;
?>
<?= view('staff_header', [
    'title' => 'Create New Goal',
    'nav' => $nav,
    'message' => $message,
    'error' => $error,
    'breadcrumbTrail' => [
        ['label' => 'Players', 'url' => site_url('players')],
        ['label' => 'Player Goals', 'url' => site_url('player-goals')],
        ['label' => 'Create New Goal'],
    ],
]) ?>

<div class="mcc-player-goal-create-page">
    <section class="mcc-card mcc-detail-card" aria-labelledby="goal-create-heading">
        <div class="mcc-card-heading">
            <div>
                <span class="mcc-eyebrow">Player development</span>
                <h2 id="goal-create-heading">Create New Goal</h2>
                <p class="mcc-card-subtitle">Choose an authorized Player before entering the goal.</p>
            </div>
        </div>

        <div class="mcc-player-feedback-search mcc-goal-player-search"
            data-feedback-player-search
            data-feedback-player-search-mode="goal"
            data-search-url="<?= esc(site_url('player-goals/player-search')) ?>">
            <label for="goal-player-search-input">Search Player</label>
            <input class="form-control" id="goal-player-search-input" name="player_search" type="search"
                autocomplete="off" minlength="1" maxlength="100"
                placeholder="Search player by name or ID"
                value="<?= esc((string) old('player_search')) ?>"
                data-feedback-player-search-input>
            <div class="mcc-player-feedback-search-results" data-feedback-player-search-results
                role="listbox" aria-label="Authorized Player search results" hidden></div>
            <div class="mcc-empty-state mcc-player-feedback-search-empty" data-feedback-player-search-empty
                role="status" hidden>
                <i class="fa-regular fa-user" aria-hidden="true"></i>
                <strong>No authorized Players found</strong>
                <span>Try a different Player name or ID.</span>
            </div>
            <p class="mcc-muted mcc-player-feedback-search-hint" data-feedback-player-search-hint>
                Enter a Player name or ID to search.
            </p>
        </div>

        <div class="mcc-goal-selected-player" data-goal-player-selected <?= $hasPlayer ? '' : 'hidden' ?>>
            <span class="mcc-eyebrow">Selected Player</span>
            <strong data-goal-player-selected-name><?= esc((string) ($player['player_name'] ?? '')) ?></strong>
            <span class="mcc-muted" data-goal-player-selected-meta>
                <?= $hasPlayer ? 'Player ID ' . (int) $player['id'] . ' · ' . esc((string) $player['branch_name']) : '' ?>
            </span>
            <button class="mcc-button mcc-button-secondary mcc-player-feedback-search-clear" type="button"
                data-feedback-player-search-clear>
                Change Player
            </button>
        </div>

        <form class="mcc-goal-create-form" data-goal-create-form method="post"
            action="<?= esc(site_url('player-goals')) ?>" <?= $hasPlayer ? '' : 'hidden' ?>>
            <?= csrf_field() ?>
            <input type="hidden" name="player_id" data-goal-player-id
                value="<?= (int) ($player['id'] ?? old('player_id', 0)) ?>">

            <div class="mcc-form-section">
                <div class="mcc-card-heading">
                    <div>
                        <span class="mcc-eyebrow">Goal details</span>
                        <h3>Player and Goal</h3>
                    </div>
                </div>
                <?php if ($hasPlayer): ?>
                    <div class="mcc-detail-grid mcc-goal-player-summary">
                        <div>
                            <span class="mcc-detail-label">Player</span>
                            <strong><?= esc((string) $player['player_name']) ?></strong>
                        </div>
                        <div>
                            <span class="mcc-detail-label">Player ID</span>
                            <strong><?= (int) $player['id'] ?></strong>
                        </div>
                        <div>
                            <span class="mcc-detail-label">Branch</span>
                            <strong><?= esc((string) $player['branch_name']) ?></strong>
                        </div>
                    </div>
                <?php endif; ?>
                <div class="mcc-form-field">
                    <label for="goal-title-create">Goal Title</label>
                    <input class="form-control" id="goal-title-create" name="title" type="text"
                        maxlength="150" required value="<?= esc((string) old('title')) ?>">
                </div>
                <div class="mcc-form-field">
                    <label for="goal-description-create">Goal Description</label>
                    <textarea class="form-control" id="goal-description-create" name="description"
                        rows="6" maxlength="10000" required><?= esc((string) old('description')) ?></textarea>
                </div>
            </div>

            <div class="mcc-form-actions">
                <button class="mcc-button mcc-button-primary" type="submit">
                    <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
                    Save Goal
                </button>
                <a class="mcc-button mcc-button-secondary" href="<?= esc(site_url('player-goals')) ?>">
                    Cancel
                </a>
            </div>
        </form>
    </section>
</div>

<?= view('staff_footer') ?>
