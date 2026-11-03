<?= view('staff_header', [
    'title' => 'Create Session Plan',
    'nav' => $nav,
    'message' => $message,
    'error' => $error,
    'breadcrumbTrail' => [
        ['label' => 'Players', 'url' => site_url('players')],
        ['label' => 'Session Plans', 'url' => site_url('player-session-plans')],
        ['label' => 'Create Session Plan'],
    ],
]) ?>

<div class="mcc-player-feedback-selector-page mcc-session-plan-player-selector-page">
    <section class="mcc-card mcc-detail-card" aria-labelledby="session-plan-player-selector-heading">
        <div class="mcc-card-heading">
            <div>
                <span class="mcc-eyebrow">Coach workspace</span>
                <h2 id="session-plan-player-selector-heading">Choose a Player</h2>
                <p class="mcc-card-subtitle">Search by Player name or Player ID to start a Session Plan.</p>
            </div>
        </div>
        <div class="mcc-player-feedback-search"
            data-feedback-player-search
            data-feedback-player-search-mode="session-plan"
            data-search-url="<?= esc(site_url('player-session-plans/player-search')) ?>"
            data-create-base="<?= esc(site_url('players')) ?>">
            <label for="session-plan-player-search-input">Search Player</label>
            <input class="form-control" id="session-plan-player-search-input" type="search"
                autocomplete="off" minlength="1" maxlength="100"
                placeholder="Search player by name or ID"
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
                Enter at least one character to search.
            </p>
        </div>
        <div class="mcc-form-actions">
            <a class="mcc-button mcc-button-secondary" href="<?= esc(site_url('player-session-plans')) ?>">Cancel</a>
        </div>
    </section>
</div>

<?= view('staff_footer') ?>
