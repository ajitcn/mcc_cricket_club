<?= view('staff_header', [
    'title' => 'Add 3-Month Feedback',
    'nav' => $nav,
    'message' => $message,
    'error' => $error,
    'breadcrumbTrail' => [
        ['label' => 'Players', 'url' => site_url('players')],
        ['label' => 'Coach Feedback', 'url' => site_url('player-feedback')],
        ['label' => 'Add feedback'],
    ],
]) ?>

<div class="mcc-player-feedback-selector-page">
    <section class="mcc-card mcc-detail-card" aria-labelledby="feedback-player-selector-heading">
        <div class="mcc-card-heading">
            <div>
                <span class="mcc-eyebrow">Coach workspace</span>
                <h2 id="feedback-player-selector-heading">Choose a Player</h2>
                <p class="mcc-card-subtitle">Search by Player name or Player ID to start feedback.</p>
            </div>
        </div>
        <div class="mcc-player-feedback-search" data-feedback-player-search
            data-search-url="<?= esc(site_url('player-feedback/player-search')) ?>"
            data-create-base="<?= esc(site_url('players')) ?>">
            <label for="feedback-player-search-input">Search Player</label>
            <input class="form-control" id="feedback-player-search-input" type="search"
                autocomplete="off" minlength="1" maxlength="100"
                placeholder="Type a Player name or ID"
                data-feedback-player-search-input>
            <div class="mcc-player-feedback-search-results" data-feedback-player-search-results
                role="listbox" aria-label="Player search results" hidden></div>
            <div class="mcc-empty-state mcc-player-feedback-search-empty" data-feedback-player-search-empty
                role="status" hidden>
                <i class="fa-regular fa-user" aria-hidden="true"></i>
                <strong>No authorized Players found</strong>
                <span>Try a different Player name or ID.</span>
            </div>
            <p class="mcc-muted mcc-player-feedback-search-hint" data-feedback-player-search-hint>
                Enter at least one character to search.
            </p>
        </div>
        <div class="mcc-form-actions">
            <a class="mcc-button mcc-button-secondary" href="<?= esc(site_url('player-feedback')) ?>">Cancel</a>
        </div>
    </section>
</div>

<?= view('staff_footer') ?>
